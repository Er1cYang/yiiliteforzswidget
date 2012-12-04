<?php
class CDbCommandBuilder extends CComponent
{
const PARAM_PREFIX=':yp';
private $_schema;
private $_connection;
public function __construct($schema)
{
$this->_schema=$schema;
$this->_connection=$schema->getDbConnection();
}
public function getDbConnection()
{
return $this->_connection;
}
public function getSchema()
{
return $this->_schema;
}
public function getLastInsertID($table)
{
$this->ensureTable($table);
if($table->sequenceName!==null)
return $this->_connection->getLastInsertID($table->sequenceName);
else
return null;
}
public function createFindCommand($table,$criteria,$alias='t')
{
$this->ensureTable($table);
$select=is_array($criteria->select) ? implode(', ',$criteria->select) : $criteria->select;
if($criteria->alias!='')
$alias=$criteria->alias;
$alias=$this->_schema->quoteTableName($alias);
if($select==='*' && !empty($criteria->join))
{
$prefix=$alias.'.';
$select=array();
foreach($table->getColumnNames() as $name)
$select[]=$prefix.$this->_schema->quoteColumnName($name);
$select=implode(', ',$select);
}
$sql=($criteria->distinct ? 'SELECT DISTINCT':'SELECT')." {$select} FROM {$table->rawName} $alias";
$sql=$this->applyJoin($sql,$criteria->join);
$sql=$this->applyCondition($sql,$criteria->condition);
$sql=$this->applyGroup($sql,$criteria->group);
$sql=$this->applyHaving($sql,$criteria->having);
$sql=$this->applyOrder($sql,$criteria->order);
$sql=$this->applyLimit($sql,$criteria->limit,$criteria->offset);
$command=$this->_connection->createCommand($sql);
$this->bindValues($command,$criteria->params);
return $command;
}
public function createCountCommand($table,$criteria,$alias='t')
{
$this->ensureTable($table);
if($criteria->alias!='')
$alias=$criteria->alias;
$alias=$this->_schema->quoteTableName($alias);
if(!empty($criteria->group) || !empty($criteria->having))
{
$select=is_array($criteria->select) ? implode(', ',$criteria->select) : $criteria->select;
if($criteria->alias!='')
$alias=$criteria->alias;
$sql=($criteria->distinct ? 'SELECT DISTINCT':'SELECT')." {$select} FROM {$table->rawName} $alias";
$sql=$this->applyJoin($sql,$criteria->join);
$sql=$this->applyCondition($sql,$criteria->condition);
$sql=$this->applyGroup($sql,$criteria->group);
$sql=$this->applyHaving($sql,$criteria->having);
$sql="SELECT COUNT(*) FROM ($sql) sq";
}
else
{
if(is_string($criteria->select) && stripos($criteria->select,'count')===0)
$sql="SELECT ".$criteria->select;
else if($criteria->distinct)
{
if(is_array($table->primaryKey))
{
$pk=array();
foreach($table->primaryKey as $key)
$pk[]=$alias.'.'.$key;
$pk=implode(', ',$pk);
}
else
$pk=$alias.'.'.$table->primaryKey;
$sql="SELECT COUNT(DISTINCT $pk)";
}
else
$sql="SELECT COUNT(*)";
$sql.=" FROM {$table->rawName} $alias";
$sql=$this->applyJoin($sql,$criteria->join);
$sql=$this->applyCondition($sql,$criteria->condition);
}
$command=$this->_connection->createCommand($sql);
$this->bindValues($command,$criteria->params);
return $command;
}
public function createDeleteCommand($table,$criteria)
{
$this->ensureTable($table);
$sql="DELETE FROM {$table->rawName}";
$sql=$this->applyJoin($sql,$criteria->join);
$sql=$this->applyCondition($sql,$criteria->condition);
$sql=$this->applyGroup($sql,$criteria->group);
$sql=$this->applyHaving($sql,$criteria->having);
$sql=$this->applyOrder($sql,$criteria->order);
$sql=$this->applyLimit($sql,$criteria->limit,$criteria->offset);
$command=$this->_connection->createCommand($sql);
$this->bindValues($command,$criteria->params);
return $command;
}
public function createInsertCommand($table,$data)
{
$this->ensureTable($table);
$fields=array();
$values=array();
$placeholders=array();
$i=0;
foreach($data as $name=>$value)
{
if(($column=$table->getColumn($name))!==null && ($value!==null || $column->allowNull))
{
$fields[]=$column->rawName;
if($value instanceof CDbExpression)
{
$placeholders[]=$value->expression;
foreach($value->params as $n=>$v)
$values[$n]=$v;
}
else
{
$placeholders[]=self::PARAM_PREFIX.$i;
$values[self::PARAM_PREFIX.$i]=$column->typecast($value);
$i++;
}
}
}
if($fields===array())
{
$pks=is_array($table->primaryKey) ? $table->primaryKey : array($table->primaryKey);
foreach($pks as $pk)
{
$fields[]=$table->getColumn($pk)->rawName;
$placeholders[]='NULL';
}
}
$sql="INSERT INTO {$table->rawName} (".implode(', ',$fields).') VALUES ('.implode(', ',$placeholders).')';
$command=$this->_connection->createCommand($sql);
foreach($values as $name=>$value)
$command->bindValue($name,$value);
return $command;
}
public function createUpdateCommand($table,$data,$criteria)
{
$this->ensureTable($table);
$fields=array();
$values=array();
$bindByPosition=isset($criteria->params[0]);
$i=0;
foreach($data as $name=>$value)
{
if(($column=$table->getColumn($name))!==null)
{
if($value instanceof CDbExpression)
{
$fields[]=$column->rawName.'='.$value->expression;
foreach($value->params as $n=>$v)
$values[$n]=$v;
}
else if($bindByPosition)
{
$fields[]=$column->rawName.'=?';
$values[]=$column->typecast($value);
}
else
{
$fields[]=$column->rawName.'='.self::PARAM_PREFIX.$i;
$values[self::PARAM_PREFIX.$i]=$column->typecast($value);
$i++;
}
}
}
if($fields===array())
throw new CDbException(Yii::t('yii','No columns are being updated for table "{table}".',
array('{table}'=>$table->name)));
$sql="UPDATE {$table->rawName} SET ".implode(', ',$fields);
$sql=$this->applyJoin($sql,$criteria->join);
$sql=$this->applyCondition($sql,$criteria->condition);
$sql=$this->applyOrder($sql,$criteria->order);
$sql=$this->applyLimit($sql,$criteria->limit,$criteria->offset);
$command=$this->_connection->createCommand($sql);
$this->bindValues($command,array_merge($values,$criteria->params));
return $command;
}
public function createUpdateCounterCommand($table,$counters,$criteria)
{
$this->ensureTable($table);
$fields=array();
foreach($counters as $name=>$value)
{
if(($column=$table->getColumn($name))!==null)
{
$value=(int)$value;
if($value<0)
$fields[]="{$column->rawName}={$column->rawName}-".(-$value);
else
$fields[]="{$column->rawName}={$column->rawName}+".$value;
}
}
if($fields!==array())
{
$sql="UPDATE {$table->rawName} SET ".implode(', ',$fields);
$sql=$this->applyJoin($sql,$criteria->join);
$sql=$this->applyCondition($sql,$criteria->condition);
$sql=$this->applyOrder($sql,$criteria->order);
$sql=$this->applyLimit($sql,$criteria->limit,$criteria->offset);
$command=$this->_connection->createCommand($sql);
$this->bindValues($command,$criteria->params);
return $command;
}
else
throw new CDbException(Yii::t('yii','No counter columns are being updated for table "{table}".',
array('{table}'=>$table->name)));
}
public function createSqlCommand($sql,$params=array())
{
$command=$this->_connection->createCommand($sql);
$this->bindValues($command,$params);
return $command;
}
public function applyJoin($sql,$join)
{
if($join!='')
return $sql.' '.$join;
else
return $sql;
}
public function applyCondition($sql,$condition)
{
if($condition!='')
return $sql.' WHERE '.$condition;
else
return $sql;
}
public function applyOrder($sql,$orderBy)
{
if($orderBy!='')
return $sql.' ORDER BY '.$orderBy;
else
return $sql;
}
public function applyLimit($sql,$limit,$offset)
{
if($limit>=0)
$sql.=' LIMIT '.(int)$limit;
if($offset>0)
$sql.=' OFFSET '.(int)$offset;
return $sql;
}
public function applyGroup($sql,$group)
{
if($group!='')
return $sql.' GROUP BY '.$group;
else
return $sql;
}
public function applyHaving($sql,$having)
{
if($having!='')
return $sql.' HAVING '.$having;
else
return $sql;
}
public function bindValues($command, $values)
{
if(($n=count($values))===0)
return;
if(isset($values[0])) // question mark placeholders
{
for($i=0;$i<$n;++$i)
$command->bindValue($i+1,$values[$i]);
}
else // named placeholders
{
foreach($values as $name=>$value)
{
if($name[0]!==':')
$name=':'.$name;
$command->bindValue($name,$value);
}
}
}
public function createCriteria($condition='',$params=array())
{
if(is_array($condition))
$criteria=new CDbCriteria($condition);
else if($condition instanceof CDbCriteria)
$criteria=clone $condition;
else
{
$criteria=new CDbCriteria;
$criteria->condition=$condition;
$criteria->params=$params;
}
return $criteria;
}
public function createPkCriteria($table,$pk,$condition='',$params=array(),$prefix=null)
{
$this->ensureTable($table);
$criteria=$this->createCriteria($condition,$params);
if($criteria->alias!='')
$prefix=$this->_schema->quoteTableName($criteria->alias).'.';
if(!is_array($pk)) // single key
$pk=array($pk);
if(is_array($table->primaryKey) && !isset($pk[0]) && $pk!==array()) // single composite key
$pk=array($pk);
$condition=$this->createInCondition($table,$table->primaryKey,$pk,$prefix);
if($criteria->condition!='')
$criteria->condition=$condition.' AND ('.$criteria->condition.')';
else
$criteria->condition=$condition;
return $criteria;
}
public function createPkCondition($table,$values,$prefix=null)
{
$this->ensureTable($table);
return $this->createInCondition($table,$table->primaryKey,$values,$prefix);
}
public function createColumnCriteria($table,$columns,$condition='',$params=array(),$prefix=null)
{
$this->ensureTable($table);
$criteria=$this->createCriteria($condition,$params);
if($criteria->alias!='')
$prefix=$this->_schema->quoteTableName($criteria->alias).'.';
$bindByPosition=isset($criteria->params[0]);
$conditions=array();
$values=array();
$i=0;
if($prefix===null)
$prefix=$table->rawName.'.';
foreach($columns as $name=>$value)
{
if(($column=$table->getColumn($name))!==null)
{
if(is_array($value))
$conditions[]=$this->createInCondition($table,$name,$value,$prefix);
else if($value!==null)
{
if($bindByPosition)
{
$conditions[]=$prefix.$column->rawName.'=?';
$values[]=$value;
}
else
{
$conditions[]=$prefix.$column->rawName.'='.self::PARAM_PREFIX.$i;
$values[self::PARAM_PREFIX.$i]=$value;
$i++;
}
}
else
$conditions[]=$prefix.$column->rawName.' IS NULL';
}
else
throw new CDbException(Yii::t('yii','Table "{table}" does not have a column named "{column}".',
array('{table}'=>$table->name,'{column}'=>$name)));
}
$criteria->params=array_merge($values,$criteria->params);
if(isset($conditions[0]))
{
if($criteria->condition!='')
$criteria->condition=implode(' AND ',$conditions).' AND ('.$criteria->condition.')';
else
$criteria->condition=implode(' AND ',$conditions);
}
return $criteria;
}
public function createSearchCondition($table,$columns,$keywords,$prefix=null,$caseSensitive=true)
{
$this->ensureTable($table);
if(!is_array($keywords))
$keywords=preg_split('/\s+/u',$keywords,-1,PREG_SPLIT_NO_EMPTY);
if(empty($keywords))
return '';
if($prefix===null)
$prefix=$table->rawName.'.';
$conditions=array();
foreach($columns as $name)
{
if(($column=$table->getColumn($name))===null)
throw new CDbException(Yii::t('yii','Table "{table}" does not have a column named "{column}".',
array('{table}'=>$table->name,'{column}'=>$name)));
$condition=array();
foreach($keywords as $keyword)
{
$keyword='%'.strtr($keyword,array('%'=>'\%', '_'=>'\_')).'%';
if($caseSensitive)
$condition[]=$prefix.$column->rawName.' LIKE '.$this->_connection->quoteValue('%'.$keyword.'%');
else
$condition[]='LOWER('.$prefix.$column->rawName.') LIKE LOWER('.$this->_connection->quoteValue('%'.$keyword.'%').')';
}
$conditions[]=implode(' AND ',$condition);
}
return '('.implode(' OR ',$conditions).')';
}
public function createInCondition($table,$columnName,$values,$prefix=null)
{
if(($n=count($values))<1)
return '0=1';
$this->ensureTable($table);
if($prefix===null)
$prefix=$table->rawName.'.';
$db=$this->_connection;
if(is_array($columnName) && count($columnName)===1)
$columnName=reset($columnName);
if(is_string($columnName)) // simple key
{
if(!isset($table->columns[$columnName]))
throw new CDbException(Yii::t('yii','Table "{table}" does not have a column named "{column}".',
array('{table}'=>$table->name, '{column}'=>$columnName)));
$column=$table->columns[$columnName];
foreach($values as &$value)
{
$value=$column->typecast($value);
if(is_string($value))
$value=$db->quoteValue($value);
}
if($n===1)
return $prefix.$column->rawName.($values[0]===null?' IS NULL':'='.$values[0]);
else
return $prefix.$column->rawName.' IN ('.implode(', ',$values).')';
}
else if(is_array($columnName)) // composite key: $values=array(array('pk1'=>'v1','pk2'=>'v2'),array(...))
{
foreach($columnName as $name)
{
if(!isset($table->columns[$name]))
throw new CDbException(Yii::t('yii','Table "{table}" does not have a column named "{column}".',
array('{table}'=>$table->name, '{column}'=>$name)));
for($i=0;$i<$n;++$i)
{
if(isset($values[$i][$name]))
{
$value=$table->columns[$name]->typecast($values[$i][$name]);
if(is_string($value))
$values[$i][$name]=$db->quoteValue($value);
else
$values[$i][$name]=$value;
}
else
throw new CDbException(Yii::t('yii','The value for the column "{column}" is not supplied when querying the table "{table}".',
array('{table}'=>$table->name,'{column}'=>$name)));
}
}
if(count($values)===1)
{
$entries=array();
foreach($values[0] as $name=>$value)
$entries[]=$prefix.$table->columns[$name]->rawName.($value===null?' IS NULL':'='.$value);
return implode(' AND ',$entries);
}
return $this->createCompositeInCondition($table,$values,$prefix);
}
else
throw new CDbException(Yii::t('yii','Column name must be either a string or an array.'));
}
protected function createCompositeInCondition($table,$values,$prefix)
{
$keyNames=array();
foreach(array_keys($values[0]) as $name)
$keyNames[]=$prefix.$table->columns[$name]->rawName;
$vs=array();
foreach($values as $value)
$vs[]='('.implode(', ',$value).')';
return '('.implode(', ',$keyNames).') IN ('.implode(', ',$vs).')';
}
protected function ensureTable(&$table)
{
if(is_string($table) && ($table=$this->_schema->getTable($tableName=$table))===null)
throw new CDbException(Yii::t('yii','Table "{table}" does not exist.',
array('{table}'=>$tableName)));
}
}