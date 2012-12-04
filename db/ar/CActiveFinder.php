<?php
class CActiveFinder extends CComponent
{
public $joinAll=false;
public $baseLimited=false;
private $_joinCount=0;
private $_joinTree;
private $_builder;
public function __construct($model,$with)
{
$this->_builder=$model->getCommandBuilder();
$this->_joinTree=new CJoinElement($this,$model);
$this->buildJoinTree($this->_joinTree,$with);
}
public function query($criteria,$all=false)
{
$this->joinAll=$criteria->together===true;
$this->_joinTree->beforeFind(false);
if($criteria->alias!='')
{
$this->_joinTree->tableAlias=$criteria->alias;
$this->_joinTree->rawTableAlias=$this->_builder->getSchema()->quoteTableName($criteria->alias);
}
$this->_joinTree->find($criteria);
$this->_joinTree->afterFind();
if($all)
{
$result = array_values($this->_joinTree->records);
if ($criteria->index!==null)
{
$index=$criteria->index;
$array=array();
foreach($result as $object)
$array[$object->$index]=$object;
$result=$array;
}
}
else if(count($this->_joinTree->records))
$result = reset($this->_joinTree->records);
else
$result = null;
$this->destroyJoinTree();
return $result;
}
public function findBySql($sql,$params=array())
{
Yii::trace(get_class($this->_joinTree->model).'.findBySql() eagerly','system.db.ar.CActiveRecord');
if(($row=$this->_builder->createSqlCommand($sql,$params)->queryRow())!==false)
{
$baseRecord=$this->_joinTree->model->populateRecord($row,false);
$this->_joinTree->beforeFind(false);
$this->_joinTree->findWithBase($baseRecord);
$this->_joinTree->afterFind();
$this->destroyJoinTree();
return $baseRecord;
}
else
$this->destroyJoinTree();
}
public function findAllBySql($sql,$params=array())
{
Yii::trace(get_class($this->_joinTree->model).'.findAllBySql() eagerly','system.db.ar.CActiveRecord');
if(($rows=$this->_builder->createSqlCommand($sql,$params)->queryAll())!==array())
{
$baseRecords=$this->_joinTree->model->populateRecords($rows,false);
$this->_joinTree->beforeFind(false);
$this->_joinTree->findWithBase($baseRecords);
$this->_joinTree->afterFind();
$this->destroyJoinTree();
return $baseRecords;
}
else
{
$this->destroyJoinTree();
return array();
}
}
public function count($criteria)
{
Yii::trace(get_class($this->_joinTree->model).'.count() eagerly','system.db.ar.CActiveRecord');
$this->joinAll=$criteria->together!==true;
$alias=$criteria->alias===null ? 't' : $criteria->alias;
$this->_joinTree->tableAlias=$alias;
$this->_joinTree->rawTableAlias=$this->_builder->getSchema()->quoteTableName($alias);
$n=$this->_joinTree->count($criteria);
$this->destroyJoinTree();
return $n;
}
public function lazyFind($baseRecord)
{
$this->_joinTree->lazyFind($baseRecord);
if(!empty($this->_joinTree->children))
{
foreach($this->_joinTree->children as $child) {
$child->afterFind();
}
}
$this->destroyJoinTree();
}
private function destroyJoinTree()
{
if($this->_joinTree!==null)
$this->_joinTree->destroy();
$this->_joinTree=null;
}
private function buildJoinTree($parent,$with,$options=null)
{
if($parent instanceof CStatElement)
throw new CDbException(Yii::t('yii','The STAT relation "{name}" cannot have child relations.',
array('{name}'=>$parent->relation->name)));
if(is_string($with))
{
if(($pos=strrpos($with,'.'))!==false)
{
$parent=$this->buildJoinTree($parent,substr($with,0,$pos));
$with=substr($with,$pos+1);
}
$scopes=array();
if(($pos=strpos($with,':'))!==false)
{
$scopes=explode(':',substr($with,$pos+1));
$with=substr($with,0,$pos);
}
if(isset($parent->children[$with]) && $parent->children[$with]->master===null)
return $parent->children[$with];
if(($relation=$parent->model->getActiveRelation($with))===null)
throw new CDbException(Yii::t('yii','Relation "{name}" is not defined in active record class "{class}".',
array('{class}'=>get_class($parent->model), '{name}'=>$with)));
$relation=clone $relation;
$model=CActiveRecord::model($relation->className);
if($relation instanceof CActiveRelation)
{
$oldAlias=$model->getTableAlias(false,false);
if(isset($options['alias']))
$model->setTableAlias($options['alias']);
else if($relation->alias===null)
$model->setTableAlias($relation->name);
else
$model->setTableAlias($relation->alias);
}
if(!empty($relation->scopes))
$scopes=array_merge($scopes,(array)$relation->scopes); // no need for complex merging
if(!empty($options['scopes']))
$scopes=array_merge($scopes,(array)$options['scopes']); // no need for complex merging
$model->resetScope(false);
$criteria=$model->getDbCriteria();
$criteria->scopes=$scopes;
$model->applyScopes($criteria);
$relation->mergeWith($criteria,true);
if($options!==null)
$relation->mergeWith($options);
if($relation instanceof CActiveRelation)
$model->setTableAlias($oldAlias);
if($relation instanceof CStatRelation)
return new CStatElement($this,$relation,$parent);
else
{
if(isset($parent->children[$with]))
{
$element=$parent->children[$with];
$element->relation=$relation;
}
else
$element=new CJoinElement($this,$relation,$parent,++$this->_joinCount);
if(!empty($relation->through))
{
$slave=$this->buildJoinTree($parent,$relation->through,array('select'=>false));
$slave->master=$element;
$element->slave=$slave;
}
$parent->children[$with]=$element;
if(!empty($relation->with))
$this->buildJoinTree($element,$relation->with);
return $element;
}
}
foreach($with as $key=>$value)
{
if(is_string($value))  // the value is a relation name
$this->buildJoinTree($parent,$value);
else if(is_string($key) && is_array($value))
$this->buildJoinTree($parent,$key,$value);
}
}
}
class CJoinElement
{
public $id;
public $relation;
public $master;
public $slave;
public $model;
public $records=array();
public $children=array();
public $stats=array();
public $tableAlias;
public $rawTableAlias;
private $_finder;
private $_builder;
private $_parent;
private $_pkAlias;  				// string or name=>alias
private $_columnAliases=array();	// name=>alias
private $_joined=false;
private $_table;
private $_related=array();			// PK, relation name, related PK=>true
public function __construct($finder,$relation,$parent=null,$id=0)
{
$this->_finder=$finder;
$this->id=$id;
if($parent!==null)
{
$this->relation=$relation;
$this->_parent=$parent;
$this->model=CActiveRecord::model($relation->className);
$this->_builder=$this->model->getCommandBuilder();
$this->tableAlias=$relation->alias===null?$relation->name:$relation->alias;
$this->rawTableAlias=$this->_builder->getSchema()->quoteTableName($this->tableAlias);
$this->_table=$this->model->getTableSchema();
}
else  // root element, the first parameter is the model.
{
$this->model=$relation;
$this->_builder=$relation->getCommandBuilder();
$this->_table=$relation->getTableSchema();
$this->tableAlias=$this->model->getTableAlias();
$this->rawTableAlias=$this->_builder->getSchema()->quoteTableName($this->tableAlias);
}
$table=$this->_table;
if($this->model->getDbConnection()->getDriverName()==='oci')  // Issue 482
$prefix='T'.$id.'_C';
else
$prefix='t'.$id.'_c';
foreach($table->getColumnNames() as $key=>$name)
{
$alias=$prefix.$key;
$this->_columnAliases[$name]=$alias;
if($table->primaryKey===$name)
$this->_pkAlias=$alias;
else if(is_array($table->primaryKey) && in_array($name,$table->primaryKey))
$this->_pkAlias[$name]=$alias;
}
}
public function destroy()
{
if(!empty($this->children))
{
foreach($this->children as $child)
$child->destroy();
}
unset($this->_finder, $this->_parent, $this->model, $this->relation, $this->master, $this->slave, $this->records, $this->children, $this->stats);
}
public function find($criteria=null)
{
if($this->_parent===null) // root element
{
$query=new CJoinQuery($this,$criteria);
$this->_finder->baseLimited=($criteria->offset>=0 || $criteria->limit>=0);
$this->buildQuery($query);
$this->_finder->baseLimited=false;
$this->runQuery($query);
}
else if(!$this->_joined && !empty($this->_parent->records)) // not joined before
{
$query=new CJoinQuery($this->_parent);
$this->_joined=true;
$query->join($this);
$this->buildQuery($query);
$this->_parent->runQuery($query);
}
foreach($this->children as $child) // find recursively
$child->find();
foreach($this->stats as $stat)
$stat->query();
}
public function lazyFind($baseRecord)
{
if(is_string($this->_table->primaryKey))
$this->records[$baseRecord->{$this->_table->primaryKey}]=$baseRecord;
else
{
$pk=array();
foreach($this->_table->primaryKey as $name)
$pk[$name]=$baseRecord->$name;
$this->records[serialize($pk)]=$baseRecord;
}
foreach($this->stats as $stat)
$stat->query();
switch(count($this->children))
{
case 0:
return;
break;
case 1:
$child=reset($this->children);
break;
default: // bridge(s) inside
$child=end($this->children);
break;
}
$query=new CJoinQuery($child);
$query->selects=array();
$query->selects[]=$child->getColumnSelect($child->relation->select);
$query->conditions=array();
$query->conditions[]=$child->relation->condition;
$query->conditions[]=$child->relation->on;
$query->groups[]=$child->relation->group;
$query->joins[]=$child->relation->join;
$query->havings[]=$child->relation->having;
$query->orders[]=$child->relation->order;
if(is_array($child->relation->params))
$query->params=$child->relation->params;
$query->elements[$child->id]=true;
if($child->relation instanceof CHasManyRelation)
{
$query->limit=$child->relation->limit;
$query->offset=$child->relation->offset;
}
$child->beforeFind();
$child->applyLazyCondition($query,$baseRecord);
$this->_joined=true;
$child->_joined=true;
$this->_finder->baseLimited=false;
$child->buildQuery($query);
$child->runQuery($query);
foreach($child->children as $c)
$c->find();
if(empty($child->records))
return;
if($child->relation instanceof CHasOneRelation || $child->relation instanceof CBelongsToRelation)
$baseRecord->addRelatedRecord($child->relation->name,reset($child->records),false);
else // has_many and many_many
{
foreach($child->records as $record)
{
if($child->relation->index!==null)
$index=$record->{$child->relation->index};
else
$index=true;
$baseRecord->addRelatedRecord($child->relation->name,$record,$index);
}
}
}
private function applyLazyCondition($query,$record)
{
$schema=$this->_builder->getSchema();
$parent=$this->_parent;
if($this->relation instanceof CManyManyRelation)
{
$joinTableName=$this->relation->getJunctionTableName();
if(($joinTable=$schema->getTable($joinTableName))===null)
throw new CDbException(Yii::t('yii','The relation "{relation}" in active record class "{class}" is not specified correctly: the join table "{joinTable}" given in the foreign key cannot be found in the database.',
array('{class}'=>get_class($parent->model), '{relation}'=>$this->relation->name, '{joinTable}'=>$joinTableName)));
$fks=$this->relation->getJunctionForeignKeys();
$joinAlias=$schema->quoteTableName($this->relation->name.'_'.$this->tableAlias);
$parentCondition=array();
$childCondition=array();
$count=0;
$params=array();
$fkDefined=true;
foreach($fks as $i=>$fk)
{
if(isset($joinTable->foreignKeys[$fk]))  // FK defined
{
list($tableName,$pk)=$joinTable->foreignKeys[$fk];
if(!isset($parentCondition[$pk]) && $schema->compareTableNames($parent->_table->rawName,$tableName))
{
$parentCondition[$pk]=$joinAlias.'.'.$schema->quoteColumnName($fk).'=:ypl'.$count;
$params[':ypl'.$count]=$record->$pk;
$count++;
}
else if(!isset($childCondition[$pk]) && $schema->compareTableNames($this->_table->rawName,$tableName))
$childCondition[$pk]=$this->getColumnPrefix().$schema->quoteColumnName($pk).'='.$joinAlias.'.'.$schema->quoteColumnName($fk);
else
{
$fkDefined=false;
break;
}
}
else
{
$fkDefined=false;
break;
}
}
if(!$fkDefined)
{
$parentCondition=array();
$childCondition=array();
$count=0;
$params=array();
foreach($fks as $i=>$fk)
{
if($i<count($parent->_table->primaryKey))
{
$pk=is_array($parent->_table->primaryKey) ? $parent->_table->primaryKey[$i] : $parent->_table->primaryKey;
$parentCondition[$pk]=$joinAlias.'.'.$schema->quoteColumnName($fk).'=:ypl'.$count;
$params[':ypl'.$count]=$record->$pk;
$count++;
}
else
{
$j=$i-count($parent->_table->primaryKey);
$pk=is_array($this->_table->primaryKey) ? $this->_table->primaryKey[$j] : $this->_table->primaryKey;
$childCondition[$pk]=$this->getColumnPrefix().$schema->quoteColumnName($pk).'='.$joinAlias.'.'.$schema->quoteColumnName($fk);
}
}
}
if($parentCondition!==array() && $childCondition!==array())
{
$join='INNER JOIN '.$joinTable->rawName.' '.$joinAlias.' ON ';
$join.='('.implode(') AND (',$parentCondition).') AND ('.implode(') AND (',$childCondition).')';
if(!empty($this->relation->on))
$join.=' AND ('.$this->relation->on.')';
$query->joins[]=$join;
foreach($params as $name=>$value)
$query->params[$name]=$value;
}
else
throw new CDbException(Yii::t('yii','The relation "{relation}" in active record class "{class}" is specified with an incomplete foreign key. The foreign key must consist of columns referencing both joining tables.',
array('{class}'=>get_class($parent->model), '{relation}'=>$this->relation->name)));
}
else
{
$element=$this;
while($element->slave!==null)
{
$query->joins[]=$element->slave->joinOneMany($element->slave,$element->relation->foreignKey,$element,$parent);
$element=$element->slave;
}
$fks=is_array($element->relation->foreignKey) ? $element->relation->foreignKey : preg_split('/\s*,\s*/',$element->relation->foreignKey,-1,PREG_SPLIT_NO_EMPTY);
$prefix=$element->getColumnPrefix();
$params=array();
foreach($fks as $i=>$fk)
{
if(!is_int($i))
{
$pk=$fk;
$fk=$i;
}
if($this->relation instanceof CBelongsToRelation)
{
if(is_int($i))
{
if(isset($parent->_table->foreignKeys[$fk]))  // FK defined
$pk=$parent->_table->foreignKeys[$fk][1];
else if(is_array($this->_table->primaryKey)) // composite PK
$pk=$this->_table->primaryKey[$i];
else
$pk=$this->_table->primaryKey;
}
$params[$pk]=$record->$fk;
}
else
{
if(is_int($i))
{
if(isset($this->_table->foreignKeys[$fk]))  // FK defined
$pk=$this->_table->foreignKeys[$fk][1];
else if(is_array($parent->_table->primaryKey)) // composite PK
$pk=$parent->_table->primaryKey[$i];
else
$pk=$parent->_table->primaryKey;
}
$params[$fk]=$record->$pk;
}
}
$count=0;
foreach($params as $name=>$value)
{
$query->conditions[]=$prefix.$schema->quoteColumnName($name).'=:ypl'.$count;
$query->params[':ypl'.$count]=$value;
$count++;
}
}
}
public function findWithBase($baseRecords)
{
if(!is_array($baseRecords))
$baseRecords=array($baseRecords);
if(is_string($this->_table->primaryKey))
{
foreach($baseRecords as $baseRecord)
$this->records[$baseRecord->{$this->_table->primaryKey}]=$baseRecord;
}
else
{
foreach($baseRecords as $baseRecord)
{
$pk=array();
foreach($this->_table->primaryKey as $name)
$pk[$name]=$baseRecord->$name;
$this->records[serialize($pk)]=$baseRecord;
}
}
$query=new CJoinQuery($this);
$this->buildQuery($query);
if(count($query->joins)>1)
$this->runQuery($query);
foreach($this->children as $child)
$child->find();
foreach($this->stats as $stat)
$stat->query();
}
public function count($criteria=null)
{
$query=new CJoinQuery($this,$criteria);
$this->_finder->baseLimited=false;
$this->_finder->joinAll=true;
$this->buildQuery($query);
$select=is_array($criteria->select) ? implode(',',$criteria->select) : $criteria->select;
if($select!=='*' && !strncasecmp($select,'count',5))
$query->selects=array($select);
else if(is_string($this->_table->primaryKey))
{
$prefix=$this->getColumnPrefix();
$schema=$this->_builder->getSchema();
$column=$prefix.$schema->quoteColumnName($this->_table->primaryKey);
$query->selects=array("COUNT(DISTINCT $column)");
}
else
$query->selects=array("COUNT(*)");
$query->orders=$query->groups=$query->havings=array();
$query->limit=$query->offset=-1;
$command=$query->createCommand($this->_builder);
return $command->queryScalar();
}
public function beforeFind($isChild=true)
{
if($isChild)
$this->model->beforeFindInternal();
foreach($this->children as $child)
$child->beforeFind(true);
}
public function afterFind()
{
foreach($this->records as $record)
$record->afterFindInternal();
foreach($this->children as $child)
$child->afterFind();
$this->children = null;
}
public function buildQuery($query)
{
foreach($this->children as $child)
{
if($child->master!==null)
$child->_joined=true;
else if($child->relation instanceof CHasOneRelation || $child->relation instanceof CBelongsToRelation
|| $this->_finder->joinAll || $child->relation->together || (!$this->_finder->baseLimited && $child->relation->together===null))
{
$child->_joined=true;
$query->join($child);
$child->buildQuery($query);
}
}
}
public function runQuery($query)
{
$command=$query->createCommand($this->_builder);
foreach($command->queryAll() as $row)
$this->populateRecord($query,$row);
}
private function populateRecord($query,$row)
{
if(is_string($this->_pkAlias))  // single key
{
if(isset($row[$this->_pkAlias]))
$pk=$row[$this->_pkAlias];
else	// no matching related objects
return null;
}
else // is_array, composite key
{
$pk=array();
foreach($this->_pkAlias as $name=>$alias)
{
if(isset($row[$alias]))
$pk[$name]=$row[$alias];
else	// no matching related objects
return null;
}
$pk=serialize($pk);
}
if(isset($this->records[$pk]))
$record=$this->records[$pk];
else
{
$attributes=array();
$aliases=array_flip($this->_columnAliases);
foreach($row as $alias=>$value)
{
if(isset($aliases[$alias]))
$attributes[$aliases[$alias]]=$value;
}
$record=$this->model->populateRecord($attributes,false);
foreach($this->children as $child)
{
if(!empty($child->relation->select))
$record->addRelatedRecord($child->relation->name,null,$child->relation instanceof CHasManyRelation);
}
$this->records[$pk]=$record;
}
foreach($this->children as $child)
{
if(!isset($query->elements[$child->id]) || empty($child->relation->select))
continue;
$childRecord=$child->populateRecord($query,$row);
if($child->relation instanceof CHasOneRelation || $child->relation instanceof CBelongsToRelation)
$record->addRelatedRecord($child->relation->name,$childRecord,false);
else // has_many and many_many
{
if($childRecord instanceof CActiveRecord)
$fpk=serialize($childRecord->getPrimaryKey());
else
$fpk=0;
if(!isset($this->_related[$pk][$child->relation->name][$fpk]))
{
if($childRecord instanceof CActiveRecord && $child->relation->index!==null)
$index=$childRecord->{$child->relation->index};
else
$index=true;
$record->addRelatedRecord($child->relation->name,$childRecord,$index);
$this->_related[$pk][$child->relation->name][$fpk]=true;
}
}
}
return $record;
}
public function getTableNameWithAlias()
{
if($this->tableAlias!==null)
return $this->_table->rawName . ' ' . $this->rawTableAlias;
else
return $this->_table->rawName;
}
public function getColumnSelect($select='*')
{
$schema=$this->_builder->getSchema();
$prefix=$this->getColumnPrefix();
$columns=array();
if($select==='*')
{
foreach($this->_table->getColumnNames() as $name)
$columns[]=$prefix.$schema->quoteColumnName($name).' AS '.$schema->quoteColumnName($this->_columnAliases[$name]);
}
else
{
if(is_string($select))
$select=explode(',',$select);
$selected=array();
foreach($select as $name)
{
$name=trim($name);
$matches=array();
if(($pos=strrpos($name,'.'))!==false)
$key=substr($name,$pos+1);
else
$key=$name;
$key=trim($key,'\'"`');
if($key==='*')
{
foreach($this->_table->columns as $name=>$column)
{
$alias=$this->_columnAliases[$name];
if(!isset($selected[$alias]))
{
$columns[]=$prefix.$column->rawName.' AS '.$schema->quoteColumnName($alias);
$selected[$alias]=1;
}
}
continue;
}
if(isset($this->_columnAliases[$key]))  // simple column names
{
$columns[]=$prefix.$schema->quoteColumnName($key).' AS '.$schema->quoteColumnName($this->_columnAliases[$key]);
$selected[$this->_columnAliases[$key]]=1;
}
else if(preg_match('/^(.*?)\s+AS\s+(\w+)$/im',$name,$matches)) // if the column is already aliased
{
$alias=$matches[2];
if(!isset($this->_columnAliases[$alias]) || $this->_columnAliases[$alias]!==$alias)
{
$this->_columnAliases[$alias]=$alias;
$columns[]=$name;
$selected[$alias]=1;
}
}
else
throw new CDbException(Yii::t('yii','Active record "{class}" is trying to select an invalid column "{column}". Note, the column must exist in the table or be an expression with alias.',
array('{class}'=>get_class($this->model), '{column}'=>$name)));
}
if(is_string($this->_pkAlias) && !isset($selected[$this->_pkAlias]))
$columns[]=$prefix.$schema->quoteColumnName($this->_table->primaryKey).' AS '.$schema->quoteColumnName($this->_pkAlias);
else if(is_array($this->_pkAlias))
{
foreach($this->_table->primaryKey as $name)
if(!isset($selected[$name]))
$columns[]=$prefix.$schema->quoteColumnName($name).' AS '.$schema->quoteColumnName($this->_pkAlias[$name]);
}
}
return implode(', ',$columns);
}
public function getPrimaryKeySelect()
{
$schema=$this->_builder->getSchema();
$prefix=$this->getColumnPrefix();
$columns=array();
if(is_string($this->_pkAlias))
$columns[]=$prefix.$schema->quoteColumnName($this->_table->primaryKey).' AS '.$schema->quoteColumnName($this->_pkAlias);
else if(is_array($this->_pkAlias))
{
foreach($this->_pkAlias as $name=>$alias)
$columns[]=$prefix.$schema->quoteColumnName($name).' AS '.$schema->quoteColumnName($alias);
}
return implode(', ',$columns);
}
public function getPrimaryKeyRange()
{
if(empty($this->records))
return '';
$values=array_keys($this->records);
if(is_array($this->_table->primaryKey))
{
foreach($values as &$value)
$value=unserialize($value);
}
return $this->_builder->createInCondition($this->_table,$this->_table->primaryKey,$values,$this->getColumnPrefix());
}
public function getColumnPrefix()
{
if($this->tableAlias!==null)
return $this->rawTableAlias.'.';
else
return $this->_table->rawName.'.';
}
public function getJoinCondition()
{
$parent=$this->_parent;
if($this->relation instanceof CManyManyRelation)
{
$schema=$this->_builder->getSchema();
$joinTableName=$this->relation->getJunctionTableName();
if(($joinTable=$schema->getTable($joinTableName))===null)
throw new CDbException(Yii::t('yii','The relation "{relation}" in active record class "{class}" is not specified correctly: the join table "{joinTable}" given in the foreign key cannot be found in the database.',
array('{class}'=>get_class($parent->model), '{relation}'=>$this->relation->name, '{joinTable}'=>$joinTableName)));
$fks=$this->relation->getJunctionForeignKeys();
return $this->joinManyMany($joinTable,$fks,$parent);
}
else
{
$fks=is_array($this->relation->foreignKey) ? $this->relation->foreignKey : preg_split('/\s*,\s*/',$this->relation->foreignKey,-1,PREG_SPLIT_NO_EMPTY);
if($this->relation instanceof CBelongsToRelation)
{
$pke=$this;
$fke=$parent;
}
else if($this->slave===null)
{
$pke=$parent;
$fke=$this;
}
else
{
$pke=$this;
$fke=$this->slave;
}
return $this->joinOneMany($fke,$fks,$pke,$parent);
}
}
private function joinOneMany($fke,$fks,$pke,$parent)
{
$schema=$this->_builder->getSchema();
$joins=array();
if(is_string($fks))
$fks=preg_split('/\s*,\s*/',$fks,-1,PREG_SPLIT_NO_EMPTY);
foreach($fks as $i=>$fk)
{
if(!is_int($i))
{
$pk=$fk;
$fk=$i;
}
if(!isset($fke->_table->columns[$fk]))
throw new CDbException(Yii::t('yii','The relation "{relation}" in active record class "{class}" is specified with an invalid foreign key "{key}". There is no such column in the table "{table}".',
array('{class}'=>get_class($parent->model), '{relation}'=>$this->relation->name, '{key}'=>$fk, '{table}'=>$fke->_table->name)));
if(is_int($i))
{
if(isset($fke->_table->foreignKeys[$fk]) && $schema->compareTableNames($pke->_table->rawName, $fke->_table->foreignKeys[$fk][0]))
$pk=$fke->_table->foreignKeys[$fk][1];
else // FK constraints undefined
{
if(is_array($pke->_table->primaryKey)) // composite PK
$pk=$pke->_table->primaryKey[$i];
else
$pk=$pke->_table->primaryKey;
}
}
$joins[]=$fke->getColumnPrefix().$schema->quoteColumnName($fk) . '=' . $pke->getColumnPrefix().$schema->quoteColumnName($pk);
}
if(!empty($this->relation->on))
$joins[]=$this->relation->on;
return $this->relation->joinType . ' ' . $this->getTableNameWithAlias() . ' ON (' . implode(') AND (',$joins).')';
}
private function joinManyMany($joinTable,$fks,$parent)
{
$schema=$this->_builder->getSchema();
$joinAlias=$schema->quoteTableName($this->relation->name.'_'.$this->tableAlias);
$parentCondition=array();
$childCondition=array();
$fkDefined=true;
foreach($fks as $i=>$fk)
{
if(!isset($joinTable->columns[$fk]))
throw new CDbException(Yii::t('yii','The relation "{relation}" in active record class "{class}" is specified with an invalid foreign key "{key}". There is no such column in the table "{table}".',
array('{class}'=>get_class($parent->model), '{relation}'=>$this->relation->name, '{key}'=>$fk, '{table}'=>$joinTable->name)));
if(isset($joinTable->foreignKeys[$fk]))
{
list($tableName,$pk)=$joinTable->foreignKeys[$fk];
if(!isset($parentCondition[$pk]) && $schema->compareTableNames($parent->_table->rawName,$tableName))
$parentCondition[$pk]=$parent->getColumnPrefix().$schema->quoteColumnName($pk).'='.$joinAlias.'.'.$schema->quoteColumnName($fk);
else if(!isset($childCondition[$pk]) && $schema->compareTableNames($this->_table->rawName,$tableName))
$childCondition[$pk]=$this->getColumnPrefix().$schema->quoteColumnName($pk).'='.$joinAlias.'.'.$schema->quoteColumnName($fk);
else
{
$fkDefined=false;
break;
}
}
else
{
$fkDefined=false;
break;
}
}
if(!$fkDefined)
{
$parentCondition=array();
$childCondition=array();
foreach($fks as $i=>$fk)
{
if($i<count($parent->_table->primaryKey))
{
$pk=is_array($parent->_table->primaryKey) ? $parent->_table->primaryKey[$i] : $parent->_table->primaryKey;
$parentCondition[$pk]=$parent->getColumnPrefix().$schema->quoteColumnName($pk).'='.$joinAlias.'.'.$schema->quoteColumnName($fk);
}
else
{
$j=$i-count($parent->_table->primaryKey);
$pk=is_array($this->_table->primaryKey) ? $this->_table->primaryKey[$j] : $this->_table->primaryKey;
$childCondition[$pk]=$this->getColumnPrefix().$schema->quoteColumnName($pk).'='.$joinAlias.'.'.$schema->quoteColumnName($fk);
}
}
}
if($parentCondition!==array() && $childCondition!==array())
{
$join=$this->relation->joinType.' '.$joinTable->rawName.' '.$joinAlias;
$join.=' ON ('.implode(') AND (',$parentCondition).')';
$join.=' '.$this->relation->joinType.' '.$this->getTableNameWithAlias();
$join.=' ON ('.implode(') AND (',$childCondition).')';
if(!empty($this->relation->on))
$join.=' AND ('.$this->relation->on.')';
return $join;
}
else
throw new CDbException(Yii::t('yii','The relation "{relation}" in active record class "{class}" is specified with an incomplete foreign key. The foreign key must consist of columns referencing both joining tables.',
array('{class}'=>get_class($parent->model), '{relation}'=>$this->relation->name)));
}
}
class CJoinQuery
{
public $selects=array();
public $distinct=false;
public $joins=array();
public $conditions=array();
public $orders=array();
public $groups=array();
public $havings=array();
public $limit=-1;
public $offset=-1;
public $params=array();
public $elements=array();
public function __construct($joinElement,$criteria=null)
{
if($criteria!==null)
{
$this->selects[]=$joinElement->getColumnSelect($criteria->select);
$this->joins[]=$joinElement->getTableNameWithAlias();
$this->joins[]=$criteria->join;
$this->conditions[]=$criteria->condition;
$this->orders[]=$criteria->order;
$this->groups[]=$criteria->group;
$this->havings[]=$criteria->having;
$this->limit=$criteria->limit;
$this->offset=$criteria->offset;
$this->params=$criteria->params;
if(!$this->distinct && $criteria->distinct)
$this->distinct=true;
}
else
{
$this->selects[]=$joinElement->getPrimaryKeySelect();
$this->joins[]=$joinElement->getTableNameWithAlias();
$this->conditions[]=$joinElement->getPrimaryKeyRange();
}
$this->elements[$joinElement->id]=true;
}
public function join($element)
{
if($element->slave!==null)
$this->join($element->slave);
if(!empty($element->relation->select))
$this->selects[]=$element->getColumnSelect($element->relation->select);
$this->conditions[]=$element->relation->condition;
$this->orders[]=$element->relation->order;
$this->joins[]=$element->getJoinCondition();
$this->joins[]=$element->relation->join;
$this->groups[]=$element->relation->group;
$this->havings[]=$element->relation->having;
if(is_array($element->relation->params))
{
if(is_array($this->params))
$this->params=array_merge($this->params,$element->relation->params);
else
$this->params=$element->relation->params;
}
$this->elements[$element->id]=true;
}
public function createCommand($builder)
{
$sql=($this->distinct ? 'SELECT DISTINCT ':'SELECT ') . implode(', ',$this->selects);
$sql.=' FROM ' . implode(' ',$this->joins);
$conditions=array();
foreach($this->conditions as $condition)
if($condition!=='')
$conditions[]=$condition;
if($conditions!==array())
$sql.=' WHERE (' . implode(') AND (',$conditions).')';
$groups=array();
foreach($this->groups as $group)
if($group!=='')
$groups[]=$group;
if($groups!==array())
$sql.=' GROUP BY ' . implode(', ',$groups);
$havings=array();
foreach($this->havings as $having)
if($having!=='')
$havings[]=$having;
if($havings!==array())
$sql.=' HAVING (' . implode(') AND (',$havings).')';
$orders=array();
foreach($this->orders as $order)
if($order!=='')
$orders[]=$order;
if($orders!==array())
$sql.=' ORDER BY ' . implode(', ',$orders);
$sql=$builder->applyLimit($sql,$this->limit,$this->offset);
$command=$builder->getDbConnection()->createCommand($sql);
$builder->bindValues($command,$this->params);
return $command;
}
}
class CStatElement
{
public $relation;
private $_finder;
private $_parent;
public function __construct($finder,$relation,$parent)
{
$this->_finder=$finder;
$this->_parent=$parent;
$this->relation=$relation;
$parent->stats[]=$this;
}
public function query()
{
if(preg_match('/^\s*(.*?)\((.*)\)\s*$/',$this->relation->foreignKey,$matches))
$this->queryManyMany($matches[1],$matches[2]);
else
$this->queryOneMany();
}
private function queryOneMany()
{
$relation=$this->relation;
$model=CActiveRecord::model($relation->className);
$builder=$model->getCommandBuilder();
$schema=$builder->getSchema();
$table=$model->getTableSchema();
$parent=$this->_parent;
$pkTable=$parent->model->getTableSchema();
$fks=preg_split('/\s*,\s*/',$relation->foreignKey,-1,PREG_SPLIT_NO_EMPTY);
if(count($fks)!==count($pkTable->primaryKey))
throw new CDbException(Yii::t('yii','The relation "{relation}" in active record class "{class}" is specified with an invalid foreign key. The columns in the key must match the primary keys of the table "{table}".',
array('{class}'=>get_class($parent->model), '{relation}'=>$relation->name, '{table}'=>$pkTable->name)));
$map=array();  // pk=>fk
foreach($fks as $i=>$fk)
{
if(!isset($table->columns[$fk]))
throw new CDbException(Yii::t('yii','The relation "{relation}" in active record class "{class}" is specified with an invalid foreign key "{key}". There is no such column in the table "{table}".',
array('{class}'=>get_class($parent->model), '{relation}'=>$relation->name, '{key}'=>$fk, '{table}'=>$table->name)));
if(isset($table->foreignKeys[$fk]))
{
list($tableName,$pk)=$table->foreignKeys[$fk];
if($schema->compareTableNames($pkTable->rawName,$tableName))
$map[$pk]=$fk;
else
throw new CDbException(Yii::t('yii','The relation "{relation}" in active record class "{class}" is specified with a foreign key "{key}" that does not point to the parent table "{table}".',
array('{class}'=>get_class($parent->model), '{relation}'=>$relation->name, '{key}'=>$fk, '{table}'=>$pkTable->name)));
}
else  // FK constraints undefined
{
if(is_array($pkTable->primaryKey)) // composite PK
$map[$pkTable->primaryKey[$i]]=$fk;
else
$map[$pkTable->primaryKey]=$fk;
}
}
$records=$this->_parent->records;
$join=empty($relation->join)?'' : ' '.$relation->join;
$where=empty($relation->condition)?' WHERE ' : ' WHERE ('.$relation->condition.') AND ';
$group=empty($relation->group)?'' : ', '.$relation->group;
$having=empty($relation->having)?'' : ' HAVING ('.$relation->having.')';
$order=empty($relation->order)?'' : ' ORDER BY '.$relation->order;
$c=$schema->quoteColumnName('c');
$s=$schema->quoteColumnName('s');
$tableAlias=$model->getTableAlias(true);
if(count($fks)===1)  // single column FK
{
$col=$table->columns[$fks[0]]->rawName;
$sql="SELECT $col AS $c, {$relation->select} AS $s FROM {$table->rawName} ".$tableAlias.$join
.$where.'('.$builder->createInCondition($table,$fks[0],array_keys($records),$tableAlias.'.').')'
." GROUP BY $col".$group
.$having.$order;
$command=$builder->getDbConnection()->createCommand($sql);
if(is_array($relation->params))
$builder->bindValues($command,$relation->params);
$stats=array();
foreach($command->queryAll() as $row)
$stats[$row['c']]=$row['s'];
}
else  // composite FK
{
$keys=array_keys($records);
foreach($keys as &$key)
{
$key2=unserialize($key);
$key=array();
foreach($pkTable->primaryKey as $pk)
$key[$map[$pk]]=$key2[$pk];
}
$cols=array();
foreach($pkTable->primaryKey as $n=>$pk)
{
$name=$table->columns[$map[$pk]]->rawName;
$cols[$name]=$name.' AS '.$schema->quoteColumnName('c'.$n);
}
$sql='SELECT '.implode(', ',$cols).", {$relation->select} AS $s FROM {$table->rawName} ".$tableAlias.$join
.$where.'('.$builder->createInCondition($table,$fks,$keys,$tableAlias.'.').')'
.' GROUP BY '.implode(', ',array_keys($cols)).$group
.$having.$order;
$command=$builder->getDbConnection()->createCommand($sql);
if(is_array($relation->params))
$builder->bindValues($command,$relation->params);
$stats=array();
foreach($command->queryAll() as $row)
{
$key=array();
foreach($pkTable->primaryKey as $n=>$pk)
$key[$pk]=$row['c'.$n];
$stats[serialize($key)]=$row['s'];
}
}
foreach($records as $pk=>$record)
$record->addRelatedRecord($relation->name,isset($stats[$pk])?$stats[$pk]:$relation->defaultValue,false);
}
private function queryManyMany($joinTableName,$keys)
{
$relation=$this->relation;
$model=CActiveRecord::model($relation->className);
$table=$model->getTableSchema();
$builder=$model->getCommandBuilder();
$schema=$builder->getSchema();
$pkTable=$this->_parent->model->getTableSchema();
$tableAlias=$model->getTableAlias(true);
if(($joinTable=$builder->getSchema()->getTable($joinTableName))===null)
throw new CDbException(Yii::t('yii','The relation "{relation}" in active record class "{class}" is not specified correctly. The join table "{joinTable}" given in the foreign key cannot be found in the database.',
array('{class}'=>get_class($this->_parent->model), '{relation}'=>$relation->name, '{joinTable}'=>$joinTableName)));
$fks=preg_split('/\s*,\s*/',$keys,-1,PREG_SPLIT_NO_EMPTY);
if(count($fks)!==count($table->primaryKey)+count($pkTable->primaryKey))
throw new CDbException(Yii::t('yii','The relation "{relation}" in active record class "{class}" is specified with an incomplete foreign key. The foreign key must consist of columns referencing both joining tables.',
array('{class}'=>get_class($this->_parent->model), '{relation}'=>$relation->name)));
$joinCondition=array();
$map=array();
$fkDefined=true;
foreach($fks as $i=>$fk)
{
if(!isset($joinTable->columns[$fk]))
throw new CDbException(Yii::t('yii','The relation "{relation}" in active record class "{class}" is specified with an invalid foreign key "{key}". There is no such column in the table "{table}".',
array('{class}'=>get_class($this->_parent->model), '{relation}'=>$relation->name, '{key}'=>$fk, '{table}'=>$joinTable->name)));
if(isset($joinTable->foreignKeys[$fk]))
{
list($tableName,$pk)=$joinTable->foreignKeys[$fk];
if(!isset($joinCondition[$pk]) && $schema->compareTableNames($table->rawName,$tableName))
$joinCondition[$pk]=$tableAlias.'.'.$schema->quoteColumnName($pk).'='.$joinTable->rawName.'.'.$schema->quoteColumnName($fk);
else if(!isset($map[$pk]) && $schema->compareTableNames($pkTable->rawName,$tableName))
$map[$pk]=$fk;
else
{
$fkDefined=false;
break;
}
}
else
{
$fkDefined=false;
break;
}
}
if(!$fkDefined)
{
$joinCondition=array();
$map=array();
foreach($fks as $i=>$fk)
{
if($i<count($pkTable->primaryKey))
{
$pk=is_array($pkTable->primaryKey) ? $pkTable->primaryKey[$i] : $pkTable->primaryKey;
$map[$pk]=$fk;
}
else
{
$j=$i-count($pkTable->primaryKey);
$pk=is_array($table->primaryKey) ? $table->primaryKey[$j] : $table->primaryKey;
$joinCondition[$pk]=$tableAlias.'.'.$schema->quoteColumnName($pk).'='.$joinTable->rawName.'.'.$schema->quoteColumnName($fk);
}
}
}
if($joinCondition===array() || $map===array())
throw new CDbException(Yii::t('yii','The relation "{relation}" in active record class "{class}" is specified with an incomplete foreign key. The foreign key must consist of columns referencing both joining tables.',
array('{class}'=>get_class($this->_parent->model), '{relation}'=>$relation->name)));
$records=$this->_parent->records;
$cols=array();
foreach(is_string($pkTable->primaryKey)?array($pkTable->primaryKey):$pkTable->primaryKey as $n=>$pk)
{
$name=$joinTable->rawName.'.'.$schema->quoteColumnName($map[$pk]);
$cols[$name]=$name.' AS '.$schema->quoteColumnName('c'.$n);
}
$keys=array_keys($records);
if(is_array($pkTable->primaryKey))
{
foreach($keys as &$key)
{
$key2=unserialize($key);
$key=array();
foreach($pkTable->primaryKey as $pk)
$key[$map[$pk]]=$key2[$pk];
}
}
$join=empty($relation->join)?'' : ' '.$relation->join;
$where=empty($relation->condition)?'' : ' WHERE ('.$relation->condition.')';
$group=empty($relation->group)?'' : ', '.$relation->group;
$having=empty($relation->having)?'' : ' AND ('.$relation->having.')';
$order=empty($relation->order)?'' : ' ORDER BY '.$relation->order;
$sql='SELECT '.$this->relation->select.' AS '.$schema->quoteColumnName('s').', '.implode(', ',$cols)
.' FROM '.$table->rawName.' '.$tableAlias.' INNER JOIN '.$joinTable->rawName
.' ON ('.implode(') AND (',$joinCondition).')'.$join
.$where
.' GROUP BY '.implode(', ',array_keys($cols)).$group
.' HAVING ('.$builder->createInCondition($joinTable,$map,$keys).')'
.$having.$order;
$command=$builder->getDbConnection()->createCommand($sql);
if(is_array($relation->params))
$builder->bindValues($command,$relation->params);
$stats=array();
foreach($command->queryAll() as $row)
{
if(is_array($pkTable->primaryKey))
{
$key=array();
foreach($pkTable->primaryKey as $n=>$k)
$key[$k]=$row['c'.$n];
$stats[serialize($key)]=$row['s'];
}
else
$stats[$row['c0']]=$row['s'];
}
foreach($records as $pk=>$record)
$record->addRelatedRecord($relation->name,isset($stats[$pk])?$stats[$pk]:$this->relation->defaultValue,false);
}
}