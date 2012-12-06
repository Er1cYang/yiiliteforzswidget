<?php
abstract class CActiveRecord extends CModel
{
const BELONGS_TO='CBelongsToRelation';
const HAS_ONE='CHasOneRelation';
const HAS_MANY='CHasManyRelation';
const MANY_MANY='CManyManyRelation';
const STAT='CStatRelation';
const CASCADE_DELETE = 1;
const CASCADE_VALIDATE = 2;
const CASCADE_SAVE = 4;
const CASCADE_ALL = 7;
public static $db;
protected static $_models=array();			// class name=>model
protected $_md;								// meta data
private $_new=false;						// whether this instance is new or not
private $_attributes=array();				// attribute name=>attribute value
private $_related=array();					// attribute name=>related objects
private $_c;								// query criteria (used by finder only)
private $_pk;								// old primary key value
private $_alias='t';						// the table alias being used for query
public function __construct($scenario='insert')
{
if($scenario===null) // internally used by populateRecord() and model()
return;
$this->setScenario($scenario);
$this->setIsNewRecord(true);
$this->_attributes=$this->getMetaData()->attributeDefaults;
$this->init();
$this->attachBehaviors($this->behaviors());
$this->afterConstruct();
}
public function setAttributes($values, $safeOnly = true) {
if(!is_array($values)) return;
$attributes=array_flip($safeOnly ? $this->getSafeAttributeNames() : $this->attributeNames());
$r = $this->getMetaData()->relations;
foreach($values as $name=>$value) {
if(isset($attributes[$name])) {
$this->setAttribute($name, $value);
} else if(isset($r[$name])) {
$r[$name]->setAttributes($this, $value, $safeOnly);
} else if($safeOnly) {
$this->onUnsafeAttribute($name,$value);
}
}
}
public function init()
{
}
public function cache($duration, $dependency=null, $queryCount=1)
{
$this->getDbConnection()->cache($duration, $dependency, $queryCount);
return $this;
}
public function __sleep()
{
$this->_md=null;
return array_keys((array)$this);
}
public function __get($name)
{
if(isset($this->_attributes[$name]))
return $this->_attributes[$name];
else if(isset($this->getMetaData()->columns[$name]))
return null;
else if(isset($this->_related[$name]))
return $this->_related[$name];
else if(isset($this->getMetaData()->relations[$name]))
return $this->getRelated($name);
else
return parent::__get($name);
}
public function __set($name,$value)
{
if($this->setAttribute($name,$value)===false)
{
if(isset($this->getMetaData()->relations[$name]))
$this->_related[$name]=$value;
else
parent::__set($name,$value);
}
}
public function __isset($name)
{
if(isset($this->_attributes[$name]))
return true;
else if(isset($this->getMetaData()->columns[$name]))
return false;
else if(isset($this->_related[$name]))
return true;
else if(isset($this->getMetaData()->relations[$name]))
return $this->getRelated($name)!==null;
else
return parent::__isset($name);
}
public function __unset($name)
{
if(isset($this->getMetaData()->columns[$name]))
unset($this->_attributes[$name]);
else if(isset($this->getMetaData()->relations[$name]))
unset($this->_related[$name]);
else
parent::__unset($name);
}
public function __call($name,$parameters)
{
if(isset($this->getMetaData()->relations[$name]))
{
if(empty($parameters))
return $this->getRelated($name,false);
else
return $this->getRelated($name,false,$parameters[0]);
}
$scopes=$this->scopes();
if(isset($scopes[$name]))
{
$this->getDbCriteria()->mergeWith($scopes[$name]);
return $this;
}
return parent::__call($name,$parameters);
}
public function getRelated($name,$refresh=false,$params=array())
{
if(!$refresh && $params===array() && (isset($this->_related[$name]) || array_key_exists($name,$this->_related)))
return $this->_related[$name];
$md=$this->getMetaData();
if(!isset($md->relations[$name]))
throw new CDbException(Yii::t('yii','{class} does not have relation "{name}".',
array('{class}'=>get_class($this), '{name}'=>$name)));
Yii::trace('lazy loading '.get_class($this).'.'.$name,'system.db.ar.CActiveRecord');
$relation=$md->relations[$name];
if($this->getIsNewRecord() && !$refresh && ($relation instanceof CHasOneRelation || $relation instanceof CHasManyRelation))
return $relation instanceof CHasOneRelation ? null : array();
if($params!==array()) // dynamic query
{
$exists=isset($this->_related[$name]) || array_key_exists($name,$this->_related);
if($exists)
$save=$this->_related[$name];
if($params instanceof CDbCriteria)
$params = $params->toArray();
$r=array($name=>$params);
}
else
$r=$name;
unset($this->_related[$name]);
$finder=new CActiveFinder($this,$r);
$finder->lazyFind($this);
if(!isset($this->_related[$name]))
{
if($relation instanceof CHasManyRelation)
$this->_related[$name]=array();
else if($relation instanceof CStatRelation)
$this->_related[$name]=$relation->defaultValue;
else
$this->_related[$name]=null;
}
if($params!==array())
{
$results=$this->_related[$name];
if($exists)
$this->_related[$name]=$save;
else
unset($this->_related[$name]);
return $results;
}
else
return $this->_related[$name];
}
public function hasRelated($name)
{
return isset($this->_related[$name]) || array_key_exists($name,$this->_related);
}
public function getDbCriteria($createIfNull=true)
{
if($this->_c===null)
{
if(($c=$this->defaultScope())!==array() || $createIfNull)
$this->_c=new CDbCriteria($c);
}
return $this->_c;
}
public function setDbCriteria($criteria)
{
$this->_c=$criteria;
}
public function defaultScope()
{
return array();
}
public function resetScope($resetDefault=true)
{
if($resetDefault)
$this->_c=new CDbCriteria();
else
$this->_c=null;
return $this;
}
public static function model($className=__CLASS__)
{
if(isset(self::$_models[$className]))
return self::$_models[$className];
else
{
$model=self::$_models[$className]=new $className(null);
$filename = Yii::getPathOfAlias('application.data.table.metadata').'/'.get_class($model).'.md';
if(!file_exists(dirname($filename))) {
@mkdir(dirname($filename), 0777, true);
}
$md = null;
if(YII_DEV_ENV == 'remote') {
if(!file_exists($filename))
new CException('The model class "'.get_class($model).'"\'s metadata is not found, please try again!');
$mdString = file_get_contents($filename);
$md = unserialize($mdString);
} else {
$md = new CActiveRecordMetaData($model);
$mdString = serialize($md);
file_put_contents($filename, $mdString);
}
$model->_md=$md;
$model->attachBehaviors($model->behaviors());
return $model;
}
}
public function getMetaData()
{
if($this->_md!==null)
return $this->_md;
else
return $this->_md=self::model(get_class($this))->_md;
}
public function refreshMetaData()
{
$finder=self::model(get_class($this));
$finder->_md=new CActiveRecordMetaData($finder);
if($this!==$finder)
$this->_md=$finder->_md;
}
public function tableName()
{
return get_class($this);
}
public function primaryKey()
{
}
public function relations()
{
return array();
}
public function scopes()
{
return array();
}
public function attributeNames()
{
return array_keys($this->getMetaData()->columns);
}
public function getAttributeLabel($attribute)
{
$labels=$this->attributeLabels();
if(isset($labels[$attribute]))
return $labels[$attribute];
else if(strpos($attribute,'.')!==false)
{
$segs=explode('.',$attribute);
$name=array_pop($segs);
$model=$this;
foreach($segs as $seg)
{
$relations=$model->getMetaData()->relations;
if(isset($relations[$seg]))
$model=CActiveRecord::model($relations[$seg]->className);
else
break;
}
return $model->getAttributeLabel($name);
}
else
return $this->generateAttributeLabel($attribute);
}
public function getDbConnection()
{
if(self::$db!==null)
return self::$db;
else
{
self::$db=Yii::app()->getDb();
if(self::$db instanceof CDbConnection)
return self::$db;
else
throw new CDbException(Yii::t('yii','Active Record requires a "db" CDbConnection application component.'));
}
}
public function getActiveRelation($name)
{
return isset($this->getMetaData()->relations[$name]) ? $this->getMetaData()->relations[$name] : null;
}
public function getTableSchema()
{
return $this->getMetaData()->tableSchema;
}
public function getCommandBuilder()
{
return $this->getDbConnection()->getSchema()->getCommandBuilder();
}
public function hasAttribute($name)
{
return isset($this->getMetaData()->columns[$name]);
}
public function getAttribute($name)
{
if(property_exists($this,$name))
return $this->$name;
else if(isset($this->_attributes[$name]))
return $this->_attributes[$name];
}
public function setAttribute($name,$value)
{
if(property_exists($this,$name))
$this->$name=$value;
else if(isset($this->getMetaData()->columns[$name]))
$this->_attributes[$name]=$value;
else
return false;
return true;
}
public function addRelatedRecord($name,$record,$index)
{
if($index!==false)
{
if(!isset($this->_related[$name]))
$this->_related[$name]=array();
if($record instanceof CActiveRecord)
{
if($index===true)
$this->_related[$name][]=$record;
else
$this->_related[$name][$index]=$record;
}
}
else if(!isset($this->_related[$name]))
$this->_related[$name]=$record;
}
public function getAttributes($names=true)
{
$attributes=$this->_attributes;
foreach($this->getMetaData()->columns as $name=>$column)
{
if(property_exists($this,$name))
$attributes[$name]=$this->$name;
else if($names===true && !isset($attributes[$name]))
$attributes[$name]=null;
}
if(is_array($names))
{
$attrs=array();
foreach($names as $name)
{
if(property_exists($this,$name))
$attrs[$name]=$this->$name;
else
$attrs[$name]=isset($attributes[$name])?$attributes[$name]:null;
}
return $attrs;
}
else
return $attributes;
}
public function save($runValidation=true,$attributes=null)
{
if(!$runValidation || $this->validate($attributes))
return $this->getIsNewRecord() ? $this->insert($attributes) : $this->update($attributes);
else
return false;
}
public function getIsNewRecord()
{
return $this->_new;
}
public function setIsNewRecord($value)
{
$this->_new=$value;
}
public function onBeforeSave($event)
{
$this->raiseEvent('onBeforeSave',$event);
}
public function onAfterSave($event)
{
$this->raiseEvent('onAfterSave',$event);
}
public function onBeforeDelete($event)
{
$this->raiseEvent('onBeforeDelete',$event);
}
public function onAfterDelete($event)
{
$this->raiseEvent('onAfterDelete',$event);
}
public function onBeforeFind($event)
{
$this->raiseEvent('onBeforeFind',$event);
}
public function onAfterFind($event)
{
$this->raiseEvent('onAfterFind',$event);
}
protected function beforeSave()
{
$result = true;
if($this->hasEventHandler('onBeforeSave')) {
$event=new CModelEvent($this);
$this->onBeforeSave($event);
$result = $event->isValid;
}
if($result) {
foreach($this->getMetaData()->relations as $r) {
if($r instanceof CBelongsToRelation && $r->cascade & self::CASCADE_SAVE)
$result &= $r->save($this);
}
}
return $result;
}
protected function afterSave()
{
if($this->hasEventHandler('onAfterSave'))
$this->onAfterSave(new CEvent($this));
foreach($this->getMetaData()->relations as $r) {
if(!($r instanceof CBelongsToRelation) && ($r->cascade & self::CASCADE_SAVE))
$r->save($this);
}
}
protected function afterValidate() {
foreach($this->getMetaData()->relations as $name=>$r) {
if($r->cascade & self::CASCADE_VALIDATE && !$r->validate($this)) {
$this->addError($name, 
$this->getAttributeLabel($name) . ' 未通过验证.');
}
}
parent::afterValidate();
}
protected function beforeDelete()
{
if($this->hasEventHandler('onBeforeDelete'))
{
$event=new CModelEvent($this);
$this->onBeforeDelete($event);
return $event->isValid;
}
else
return true;
}
protected function afterDelete()
{
if($this->hasEventHandler('onAfterDelete'))
$this->onAfterDelete(new CEvent($this));
foreach($this->getMetaData()->relations as $r) {
if($r->cascade & self::CASCADE_DELETE)
$r->delete($this);
}
}
protected function beforeFind()
{
if($this->hasEventHandler('onBeforeFind'))
{
$event=new CModelEvent($this);
$this->onBeforeFind($event);
}
}
protected function afterFind()
{
if($this->hasEventHandler('onAfterFind'))
$this->onAfterFind(new CEvent($this));
}
public function beforeFindInternal()
{
$this->beforeFind();
}
public function afterFindInternal()
{
$this->afterFind();
}
public function insert($attributes=null)
{
if(!$this->getIsNewRecord())
throw new CDbException(Yii::t('yii','The active record cannot be inserted to database because it is not new.'));
if($this->beforeSave())
{
Yii::trace(get_class($this).'.insert()','system.db.ar.CActiveRecord');
$builder=$this->getCommandBuilder();
$table=$this->getMetaData()->tableSchema;
$command=$builder->createInsertCommand($table,$this->getAttributes($attributes));
if($command->execute())
{
$primaryKey=$table->primaryKey;
if($table->sequenceName!==null)
{
if(is_string($primaryKey) && $this->$primaryKey===null)
$this->$primaryKey=$builder->getLastInsertID($table);
else if(is_array($primaryKey))
{
foreach($primaryKey as $pk)
{
if($this->$pk===null)
{
$this->$pk=$builder->getLastInsertID($table);
break;
}
}
}
}
$this->_pk=$this->getPrimaryKey();
$this->afterSave();
$this->setIsNewRecord(false);
$this->setScenario('update');
return true;
}
}
return false;
}
public function update($attributes=null)
{
if($this->getIsNewRecord())
throw new CDbException(Yii::t('yii','The active record cannot be updated because it is new.'));
if($this->beforeSave())
{
Yii::trace(get_class($this).'.update()','system.db.ar.CActiveRecord');
if($this->_pk===null)
$this->_pk=$this->getPrimaryKey();
$this->updateByPk($this->getOldPrimaryKey(),$this->getAttributes($attributes));
$this->_pk=$this->getPrimaryKey();
$this->afterSave();
return true;
}
else
return false;
}
public function saveAttributes($attributes)
{
if(!$this->getIsNewRecord())
{
Yii::trace(get_class($this).'.saveAttributes()','system.db.ar.CActiveRecord');
$values=array();
foreach($attributes as $name=>$value)
{
if(is_integer($name))
$values[$value]=$this->$value;
else
$values[$name]=$this->$name=$value;
}
if($this->_pk===null)
$this->_pk=$this->getPrimaryKey();
if($this->updateByPk($this->getOldPrimaryKey(),$values)>0)
{
$this->_pk=$this->getPrimaryKey();
return true;
}
else
return false;
}
else
throw new CDbException(Yii::t('yii','The active record cannot be updated because it is new.'));
}
public function saveCounters($counters)
{
Yii::trace(get_class($this).'.saveCounters()','system.db.ar.CActiveRecord');
$builder=$this->getCommandBuilder();
$table=$this->getTableSchema();
$criteria=$builder->createPkCriteria($table,$this->getOldPrimaryKey());
$command=$builder->createUpdateCounterCommand($this->getTableSchema(),$counters,$criteria);
if($command->execute())
{
foreach($counters as $name=>$value)
$this->$name=$this->$name+$value;
return true;
}
else
return false;
}
public function delete()
{
if(!$this->getIsNewRecord())
{
Yii::trace(get_class($this).'.delete()','system.db.ar.CActiveRecord');
if($this->beforeDelete())
{
$result=$this->deleteByPk($this->getPrimaryKey())>0;
$this->afterDelete();
return $result;
}
else
return false;
}
else
throw new CDbException(Yii::t('yii','The active record cannot be deleted because it is new.'));
}
public function refresh()
{
Yii::trace(get_class($this).'.refresh()','system.db.ar.CActiveRecord');
if(($record=$this->findByPk($this->getPrimaryKey()))!==null)
{
$this->_attributes=array();
$this->_related=array();
foreach($this->getMetaData()->columns as $name=>$column)
{
if(property_exists($this,$name))
$this->$name=$record->$name;
else
$this->_attributes[$name]=$record->$name;
}
return true;
}
else
return false;
}
public function equals($record)
{
return $this->tableName()===$record->tableName() && $this->getPrimaryKey()===$record->getPrimaryKey();
}
public function getPrimaryKey()
{
$table=$this->getMetaData()->tableSchema;
if(is_string($table->primaryKey))
return $this->{$table->primaryKey};
else if(is_array($table->primaryKey))
{
$values=array();
foreach($table->primaryKey as $name)
$values[$name]=$this->$name;
return $values;
}
else
return null;
}
public function setPrimaryKey($value)
{
$this->_pk=$this->getPrimaryKey();
$table=$this->getMetaData()->tableSchema;
if(is_string($table->primaryKey))
$this->{$table->primaryKey}=$value;
else if(is_array($table->primaryKey))
{
foreach($table->primaryKey as $name)
$this->$name=$value[$name];
}
}
public function getOldPrimaryKey()
{
return $this->_pk;
}
public function setOldPrimaryKey($value)
{
$this->_pk=$value;
}
protected function query($criteria,$all=false)
{
$this->beforeFind();
$this->applyScopes($criteria);
if(empty($criteria->with))
{
if(!$all)
$criteria->limit=1;
$command=$this->getCommandBuilder()->createFindCommand($this->getTableSchema(),$criteria);
return $all ? $this->populateRecords($command->queryAll(), true, $criteria->index) : $this->populateRecord($command->queryRow());
}
else
{
$finder=new CActiveFinder($this,$criteria->with);
return $finder->query($criteria,$all);
}
}
public function applyScopes(&$criteria)
{
if(!empty($criteria->scopes))
{
$scs=$this->scopes();
$c=$this->getDbCriteria();
foreach((array)$criteria->scopes as $k=>$v)
{
if(is_integer($k))
{
if(is_string($v))
{
if(isset($scs[$v]))
{
$c->mergeWith($scs[$v],true);
continue;
}
$scope=$v;
$params=array();
}
else if(is_array($v))
{
$scope=key($v);
$params=current($v);
}
}
else if(is_string($k))
{
$scope=$k;
$params=$v;
}
call_user_func_array(array($this,$scope),(array)$params);
}
}
if(isset($c) || ($c=$this->getDbCriteria(false))!==null)
{
$c->mergeWith($criteria);
$criteria=$c;
$this->resetScope(false);
}
}
public function getTableAlias($quote=false, $checkScopes=true)
{
if($checkScopes && ($criteria=$this->getDbCriteria(false))!==null && $criteria->alias!='')
$alias=$criteria->alias;
else
$alias=$this->_alias;
return $quote ? $this->getDbConnection()->getSchema()->quoteTableName($alias) : $alias;
}
public function setTableAlias($alias)
{
$this->_alias=$alias;
}
public function find($condition='',$params=array())
{
Yii::trace(get_class($this).'.find()','system.db.ar.CActiveRecord');
$criteria=$this->getCommandBuilder()->createCriteria($condition,$params);
return $this->query($criteria);
}
public function findAll($condition='',$params=array())
{
Yii::trace(get_class($this).'.findAll()','system.db.ar.CActiveRecord');
$criteria=$this->getCommandBuilder()->createCriteria($condition,$params);
return $this->query($criteria,true);
}
public function findByPk($pk,$condition='',$params=array())
{
Yii::trace(get_class($this).'.findByPk()','system.db.ar.CActiveRecord');
$prefix=$this->getTableAlias(true).'.';
$criteria=$this->getCommandBuilder()->createPkCriteria($this->getTableSchema(),$pk,$condition,$params,$prefix);
return $this->query($criteria);
}
public function findAllByPk($pk,$condition='',$params=array())
{
Yii::trace(get_class($this).'.findAllByPk()','system.db.ar.CActiveRecord');
$prefix=$this->getTableAlias(true).'.';
$criteria=$this->getCommandBuilder()->createPkCriteria($this->getTableSchema(),$pk,$condition,$params,$prefix);
return $this->query($criteria,true);
}
public function findByAttributes($attributes,$condition='',$params=array())
{
Yii::trace(get_class($this).'.findByAttributes()','system.db.ar.CActiveRecord');
$prefix=$this->getTableAlias(true).'.';
$criteria=$this->getCommandBuilder()->createColumnCriteria($this->getTableSchema(),$attributes,$condition,$params,$prefix);
return $this->query($criteria);
}
public function findAllByAttributes($attributes,$condition='',$params=array())
{
Yii::trace(get_class($this).'.findAllByAttributes()','system.db.ar.CActiveRecord');
$prefix=$this->getTableAlias(true).'.';
$criteria=$this->getCommandBuilder()->createColumnCriteria($this->getTableSchema(),$attributes,$condition,$params,$prefix);
return $this->query($criteria,true);
}
public function findBySql($sql,$params=array())
{
Yii::trace(get_class($this).'.findBySql()','system.db.ar.CActiveRecord');
$this->beforeFind();
if(($criteria=$this->getDbCriteria(false))!==null && !empty($criteria->with))
{
$this->resetScope(false);
$finder=new CActiveFinder($this,$criteria->with);
return $finder->findBySql($sql,$params);
}
else
{
$command=$this->getCommandBuilder()->createSqlCommand($sql,$params);
return $this->populateRecord($command->queryRow());
}
}
public function findAllBySql($sql,$params=array())
{
Yii::trace(get_class($this).'.findAllBySql()','system.db.ar.CActiveRecord');
$this->beforeFind();
if(($criteria=$this->getDbCriteria(false))!==null && !empty($criteria->with))
{
$this->resetScope(false);
$finder=new CActiveFinder($this,$criteria->with);
return $finder->findAllBySql($sql,$params);
}
else
{
$command=$this->getCommandBuilder()->createSqlCommand($sql,$params);
return $this->populateRecords($command->queryAll());
}
}
public function count($condition='',$params=array())
{
Yii::trace(get_class($this).'.count()','system.db.ar.CActiveRecord');
$builder=$this->getCommandBuilder();
$criteria=$builder->createCriteria($condition,$params);
$this->applyScopes($criteria);
if(empty($criteria->with))
return $builder->createCountCommand($this->getTableSchema(),$criteria)->queryScalar();
else
{
$finder=new CActiveFinder($this,$criteria->with);
return $finder->count($criteria);
}
}
public function countByAttributes($attributes,$condition='',$params=array())
{
Yii::trace(get_class($this).'.countByAttributes()','system.db.ar.CActiveRecord');
$prefix=$this->getTableAlias(true).'.';
$builder=$this->getCommandBuilder();
$criteria=$builder->createColumnCriteria($this->getTableSchema(),$attributes,$condition,$params,$prefix);
$this->applyScopes($criteria);
if(empty($criteria->with))
return $builder->createCountCommand($this->getTableSchema(),$criteria)->queryScalar();
else
{
$finder=new CActiveFinder($this,$criteria->with);
return $finder->count($criteria);
}
}
public function countBySql($sql,$params=array())
{
Yii::trace(get_class($this).'.countBySql()','system.db.ar.CActiveRecord');
return $this->getCommandBuilder()->createSqlCommand($sql,$params)->queryScalar();
}
public function exists($condition='',$params=array())
{
Yii::trace(get_class($this).'.exists()','system.db.ar.CActiveRecord');
$builder=$this->getCommandBuilder();
$criteria=$builder->createCriteria($condition,$params);
$table=$this->getTableSchema();
$criteria->select='1';
$criteria->limit=1;
$this->applyScopes($criteria);
if(empty($criteria->with))
return $builder->createFindCommand($table,$criteria)->queryRow()!==false;
else
{
$criteria->select='*';
$finder=new CActiveFinder($this,$criteria->with);
return $finder->count($criteria)>0;
}
}
public function with()
{
if(func_num_args()>0)
{
$with=func_get_args();
if(is_array($with[0]))  // the parameter is given as an array
$with=$with[0];
if(!empty($with))
$this->getDbCriteria()->mergeWith(array('with'=>$with));
}
return $this;
}
public function together()
{
$this->getDbCriteria()->together=true;
return $this;
}
public function updateByPk($pk,$attributes,$condition='',$params=array())
{
Yii::trace(get_class($this).'.updateByPk()','system.db.ar.CActiveRecord');
$builder=$this->getCommandBuilder();
$table=$this->getTableSchema();
$criteria=$builder->createPkCriteria($table,$pk,$condition,$params);
$command=$builder->createUpdateCommand($table,$attributes,$criteria);
return $command->execute();
}
public function updateAll($attributes,$condition='',$params=array())
{
Yii::trace(get_class($this).'.updateAll()','system.db.ar.CActiveRecord');
$builder=$this->getCommandBuilder();
$criteria=$builder->createCriteria($condition,$params);
$command=$builder->createUpdateCommand($this->getTableSchema(),$attributes,$criteria);
return $command->execute();
}
public function updateCounters($counters,$condition='',$params=array())
{
Yii::trace(get_class($this).'.updateCounters()','system.db.ar.CActiveRecord');
$builder=$this->getCommandBuilder();
$criteria=$builder->createCriteria($condition,$params);
$command=$builder->createUpdateCounterCommand($this->getTableSchema(),$counters,$criteria);
return $command->execute();
}
public function deleteByPk($pk,$condition='',$params=array())
{
Yii::trace(get_class($this).'.deleteByPk()','system.db.ar.CActiveRecord');
$builder=$this->getCommandBuilder();
$criteria=$builder->createPkCriteria($this->getTableSchema(),$pk,$condition,$params);
$command=$builder->createDeleteCommand($this->getTableSchema(),$criteria);
return $command->execute();
}
public function deleteAll($condition='',$params=array())
{
Yii::trace(get_class($this).'.deleteAll()','system.db.ar.CActiveRecord');
$builder=$this->getCommandBuilder();
$criteria=$builder->createCriteria($condition,$params);
$command=$builder->createDeleteCommand($this->getTableSchema(),$criteria);
return $command->execute();
}
public function deleteAllByAttributes($attributes,$condition='',$params=array())
{
Yii::trace(get_class($this).'.deleteAllByAttributes()','system.db.ar.CActiveRecord');
$builder=$this->getCommandBuilder();
$table=$this->getTableSchema();
$criteria=$builder->createColumnCriteria($table,$attributes,$condition,$params);
$command=$builder->createDeleteCommand($table,$criteria);
return $command->execute();
}
public function populateRecord($attributes,$callAfterFind=true)
{
if($attributes!==false)
{
$record=$this->instantiate($attributes);
$record->setScenario('update');
$record->init();
$md=$record->getMetaData();
foreach($attributes as $name=>$value)
{
if(property_exists($record,$name))
$record->$name=$value;
else if(isset($md->columns[$name]))
$record->_attributes[$name]=$value;
}
$record->_pk=$record->getPrimaryKey();
$record->attachBehaviors($record->behaviors());
if($callAfterFind)
$record->afterFind();
return $record;
}
else
return null;
}
public function populateRecords($data,$callAfterFind=true,$index=null)
{
$records=array();
foreach($data as $attributes)
{
if(($record=$this->populateRecord($attributes,$callAfterFind))!==null)
{
if($index===null)
$records[]=$record;
else
$records[$record->$index]=$record;
}
}
return $records;
}
protected function instantiate($attributes)
{
$class=get_class($this);
$model=new $class(null);
return $model;
}
public function offsetExists($offset)
{
return $this->__isset($offset);
}
}
class CBaseActiveRelation extends CComponent
{
public $name;
public $className;
public $foreignKey;
public $select='*';
public $condition='';
public $params=array();
public $group='';
public $join='';
public $having='';
public $order='';
public function __construct($name,$className,$foreignKey,$options=array())
{
$this->name=$name;
$this->className=$className;
$this->foreignKey=$foreignKey;
foreach($options as $name=>$value)
$this->$name=$value;
}
public function mergeWith($criteria,$fromScope=false)
{
if($criteria instanceof CDbCriteria)
$criteria=$criteria->toArray();
if(isset($criteria['select']) && $this->select!==$criteria['select'])
{
if($this->select==='*')
$this->select=$criteria['select'];
else if($criteria['select']!=='*')
{
$select1=is_string($this->select)?preg_split('/\s*,\s*/',trim($this->select),-1,PREG_SPLIT_NO_EMPTY):$this->select;
$select2=is_string($criteria['select'])?preg_split('/\s*,\s*/',trim($criteria['select']),-1,PREG_SPLIT_NO_EMPTY):$criteria['select'];
$this->select=array_merge($select1,array_diff($select2,$select1));
}
}
if(isset($criteria['condition']) && $this->condition!==$criteria['condition'])
{
if($this->condition==='')
$this->condition=$criteria['condition'];
else if($criteria['condition']!=='')
$this->condition="({$this->condition}) AND ({$criteria['condition']})";
}
if(isset($criteria['params']) && $this->params!==$criteria['params'])
$this->params=array_merge($this->params,$criteria['params']);
if(isset($criteria['order']) && $this->order!==$criteria['order'])
{
if($this->order==='')
$this->order=$criteria['order'];
else if($criteria['order']!=='')
$this->order=$criteria['order'].', '.$this->order;
}
if(isset($criteria['group']) && $this->group!==$criteria['group'])
{
if($this->group==='')
$this->group=$criteria['group'];
else if($criteria['group']!=='')
$this->group.=', '.$criteria['group'];
}
if(isset($criteria['join']) && $this->join!==$criteria['join'])
{
if($this->join==='')
$this->join=$criteria['join'];
else if($criteria['join']!=='')
$this->join.=' '.$criteria['join'];
}
if(isset($criteria['having']) && $this->having!==$criteria['having'])
{
if($this->having==='')
$this->having=$criteria['having'];
else if($criteria['having']!=='')
$this->having="({$this->having}) AND ({$criteria['having']})";
}
}
}
class CStatRelation extends CBaseActiveRelation
{
public $select='COUNT(*)';
public $defaultValue=0;
public function mergeWith($criteria,$fromScope=false)
{
if($criteria instanceof CDbCriteria)
$criteria=$criteria->toArray();
parent::mergeWith($criteria,$fromScope);
if(isset($criteria['defaultValue']))
$this->defaultValue=$criteria['defaultValue'];
}
}
class CActiveRelation extends CBaseActiveRelation
{
public $joinType='LEFT OUTER JOIN';
public $on='';
public $alias;
public $with=array();
public $together;
public $scopes;
public $cascade;
protected $_isAttributesSet;
public function setAttributes(CActiveRecord $parent, $values, $safeOnly = true) {
$this->_isAttributesSet = true;
$this->setAttributesInternal($parent, $values);
return true;
}
protected function setAttributesInternal(CActiveRecord $parent, $values, $safeOnly = true) {
}
public function getIsAttributesSet() {
return $this->_isAttributesSet;
}
public function validate(CActiveRecord $parent, $attributes = null, $clearErrors = true) {
if($this->getIsAttributesSet()) {
return $this->validateInternal($parent, $attributes, $clearErrors);
}
return true;
}
protected function validateInternal(CActiveRecord $parent, $attributes = null, $clearErrors = true) {
return true;
}
public function delete(CActiveRecord $parent) {
return $this->deleteInternal($parent);
}
protected function deleteInternal(CActiveRecord $parent) {
return true;
}
public function save(CActiveRecord $parent, $runValidation = true, $attributes = null) {
if($this->getIsAttributesSet()) {
return $this->saveInternal($parent, $runValidation, $attributes);
}
return true;
}
protected function saveInternal(CActiveRecord $parent, $runValidation = true, $attributes = null) {
return true;
}
public function mergeWith($criteria,$fromScope=false)
{
if($criteria instanceof CDbCriteria)
$criteria=$criteria->toArray();
if($fromScope)
{
if(isset($criteria['condition']) && $this->on!==$criteria['condition'])
{
if($this->on==='')
$this->on=$criteria['condition'];
else if($criteria['condition']!=='')
$this->on="({$this->on}) AND ({$criteria['condition']})";
}
unset($criteria['condition']);
}
parent::mergeWith($criteria);
if(isset($criteria['joinType']))
$this->joinType=$criteria['joinType'];
if(isset($criteria['on']) && $this->on!==$criteria['on'])
{
if($this->on==='')
$this->on=$criteria['on'];
else if($criteria['on']!=='')
$this->on="({$this->on}) AND ({$criteria['on']})";
}
if(isset($criteria['with']))
$this->with=$criteria['with'];
if(isset($criteria['alias']))
$this->alias=$criteria['alias'];
if(isset($criteria['together']))
$this->together=$criteria['together'];
}
}
class CBelongsToRelation extends CActiveRelation
{
protected function saveInternal(CActiveRecord $parent, $runValidation = true, $attributes = null) {
if(!$this->isAttributesSet) return true;
$model = $parent->getRelated($this->name);
$isSuccess = true;
if($model) {
if(($isSuccess = $model->save($runValidation, $attributes)) !== false) {
$foreignKey = $this->foreignKey;
$parent->$foreignKey = $model->primaryKey;
}
}
return $isSuccess;
}
}
class CHasOneRelation extends CActiveRelation
{
public $through;
public function setAttributesInternal(CActiveRecord $parent, $value, $safeOnly = true) {
$rClass = $this->className;
$rM = new $rClass;
$model = $parent->getRelated($this->name, true);
if(!$model) {
$model = $rM->populateRecord($value);
$model->setScenario('insert');
$model->setIsNewRecord(true);
}
$model->setAttributes($value);
$parent->{$this->name} = $model;
}
public function saveInternal(CActiveRecord $parent, $runValidation = true, $attributes = null) {
if(!$this->getIsAttributesSet()) return true;
$model = $parent->getRelated($this->name);
$isSuccess = true;
if($model) {
$model->{$this->foreignKey} = $parent->primaryKey;
$isSuccess = $model->save($runValidation, $attributes);
}
return $isSuccess;
}
protected function validateInternal(CActiveRecord $parent, $attributes = null, $clearErrors = true) {
$model = $parent->getRelated($this->name);
return $model ? $model->validate($attributes, $clearErrors) : true;
}
protected function deleteInternal(CActiveRecord $parent) {
if($parent->{$this->name}) {
if(!$parent->{$this->name}->isNewRecord)
return $parent->{$this->name}->delete();
}
return true;
}
}
class CHasManyRelation extends CActiveRelation
{
public $limit=-1;
public $offset=-1;
public $index;
public $through;
protected function setAttributesInternal(CActiveRecord $parent, $values, $safeOnly = true) {
$rClass = $this->className;
$models = (array)$parent->getRelated($this->name, true);
$updateModels = array();
$rM = new $rClass;
$rPkName = $rM->getMetadata()->tableSchema->primaryKey;
foreach($values as $mIndex=>$mAttributes) {
$model = null;
if(isset($mAttributes[$rPkName])) {
$model = $rM->findByPk($mAttributes[$rPkName]);
} elseif(isset($models[$mIndex])) {
$model = $models[$mIndex];
}
if(!$model) {
$model = $rM->populateRecord($mAttributes);
$model->setScenario('insert');
$model->setIsNewRecord(true);
}
$model->setAttributes($mAttributes);
$updateModels[$mIndex] = $model;
}
$parent->{$this->name} = $updateModels;
}
protected function validateInternal(CActiveRecord $parent, $attributes = null, $clearErrors = true) {
$isSuccess = true;
foreach((array)$this->model->getRelated($this->name) as $model) {
if(!$model->validate($attributes, $clearErrors))
$isSuccess = false;
}
return $isSuccess;
}
protected function saveInternal(CActiveRecord $parent, $runValidation = true, $attributes = null) {
$existsModels = $this->getExistsRelatedModels($parent);
$models = (array)$parent->getRelated($this->name);
$fk = $this->getRelation()->foreignKey;
$pk = $parent->primaryKey;
$isSuccess = true;
foreach($existsModels as $eIndex=>$existsModel) {
if(!isset($models[$eIndex])) {
$existsModel->delete();
}
}
foreach($models as $mIndex=>$model) {
$model->{$fk} = $pk;
$isSuccess &= $model->save($runValidation, $attributes);
}
return $isSuccess;
}
protected function getExistsRelatedModels($parent) {
$cloneModel = clone $parent;
return (array)$cloneModel->getRelated($this->name, true);
}
protected function deleteInternal($parent) {
$isSuccess = true;
foreach((array)$parent->{$this->name} as $model) {
if(!$model->isNewRecord)
$isSuccess &= $model->delete();
}
return $isSuccess;
}
public function mergeWith($criteria,$fromScope=false)
{
if($criteria instanceof CDbCriteria)
$criteria=$criteria->toArray();
parent::mergeWith($criteria,$fromScope);
if(isset($criteria['limit']) && $criteria['limit']>0)
$this->limit=$criteria['limit'];
if(isset($criteria['offset']) && $criteria['offset']>=0)
$this->offset=$criteria['offset'];
if(isset($criteria['index']))
$this->index=$criteria['index'];
}
}
class CManyManyRelation extends CHasManyRelation
{
private $_junctionTableName=null;
private $_junctionForeignKeys=null;
private $_foreignInfo = null;
protected function saveInternal(CActiveRecord $parent, $runValidation = true, $attributes = null) {
$existsModels = $this->getExistsRelatedModels($parent);
$models = $parent->getRelated($this->name);
$unlinkIds = array();
foreach($existsModels as $eIndex=>$existsModel) {
if(!$existsModel->isNewRecord) {
$unlinkIds[$existsModel->primaryKey] = $existsModel->primaryKey;
}
}
$isSuccess = true;
foreach($models as $mIndex=>$model) {
if($model->isNewRecord) $model->save();
if(isset($unlinkIds[$model->primaryKey]))
unset($unlinkIds[$model->primaryKey]);
$isSuccess &= $this->link($model);
}
if(!empty($unlinkIds)) {
$this->unlink($parent, $unlinkIds);
}
return $isSuccess;
}
protected function validateInternal(CActiveRecord $parent, $attributes = null, $clearErrors = true) {
$isSuccess = true;
foreach((array)$parent->getRelated($this->name) as $model) {
if($model->isNewRecord && !$model->validate($attributes, $clearErrors))
$isSuccess = false;
}
return $isSuccess;
}
protected function deleteInternal(CActiveRecord $parent) {
return $this->unlink($parent);
}
protected function link($parent, $model) {
$fi = $this->getForeignInfo($parent);
try {
$SQL = "
INSERT INTO {$fi['table']} ({$fi['model_fk']}, {$fi['relation_fk']}) 
VALUES (:fk,:rFk)
";
$command = $parent->getDbConnection()->createCommand($SQL);
$command->bindValues(array(
":fk"=>$parent->primaryKey,
":rFk"=>$model->primaryKey,
));
$command->execute();
} catch (Exception $e) {
return false;
}
return true;
}
protected function unlink($parent, $ids = array()) {
$fi = $this->getForeignInfo($parent);
try {
$SQL = "
DELETE FROM {$fi['table']} 
WHERE {$fi['model_fk']} = :fk 
";
if(!empty($ids)) {
$SQL .= "AND {$fi['relation_fk']} IN('" . implode("','", $ids) . "')";
} 
$command = $parent->getDbConnection()->createCommand($SQL);
$command->bindValues(array(
":fk"=>$this->model->primaryKey,
));
$command->execute();
} catch(Exception $e) {
return false;
}
return true;
}
protected function getForeignInfo($model) {
if(!$this->_foreignInfo) {
$this->_foreignInfo = $this->parseForeignKey($model, $this->foreignKey);
}
return $this->_foreignInfo;
}
protected function parseForeignKey($model, $key) {
if (preg_match('/(?P<table>.*?)\((?P<model_fk>.*?),(?P<relation_fk>.*?)\)/is', $key, $matches))
{
return array(
'table'=>$model->getDbConnection()->quoteTableName(trim($matches['table'])),
'model_fk'=>$model->getDbConnection()->quoteColumnName(trim($matches['model_fk'])),
'relation_fk'=>$model->getDbConnection()->quoteColumnName(trim($matches['relation_fk'])),
);
}
return null;
}
public function getJunctionTableName()
{
if ($this->_junctionTableName===null)
$this->initJunctionData();
return $this->_junctionTableName;
}
public function getJunctionForeignKeys()
{
if ($this->_junctionForeignKeys===null)
$this->initJunctionData();
return $this->_junctionForeignKeys;
}
private function initJunctionData()
{
if(!preg_match('/^\s*(.*?)\((.*)\)\s*$/',$this->foreignKey,$matches))
throw new CDbException(Yii::t('yii','The relation "{relation}" in active record class "{class}" is specified with an invalid foreign key. The format of the foreign key must be "joinTable(fk1,fk2,...)".',
array('{class}'=>$this->className,'{relation}'=>$this->name)));
$this->_junctionTableName=$matches[1];
$this->_junctionForeignKeys=preg_split('/\s*,\s*/',$matches[2],-1,PREG_SPLIT_NO_EMPTY);
}
}
class CActiveRecordMetaData
{
public $tableSchema;
public $columns;
public $relations=array();
public $attributeDefaults=array();
private $_model;
public function __construct($model)
{
$this->_model=$model;
$tableName=$model->tableName();
if(($table=$model->getDbConnection()->getSchema()->getTable($tableName))===null)
throw new CDbException(Yii::t('yii','The table "{table}" for active record class "{class}" cannot be found in the database.',
array('{class}'=>get_class($model),'{table}'=>$tableName)));
if($table->primaryKey===null)
{
$table->primaryKey=$model->primaryKey();
if(is_string($table->primaryKey) && isset($table->columns[$table->primaryKey]))
$table->columns[$table->primaryKey]->isPrimaryKey=true;
else if(is_array($table->primaryKey))
{
foreach($table->primaryKey as $name)
{
if(isset($table->columns[$name]))
$table->columns[$name]->isPrimaryKey=true;
}
}
}
$this->tableSchema=$table;
$this->columns=$table->columns;
foreach($table->columns as $name=>$column)
{
if(!$column->isPrimaryKey && $column->defaultValue!==null)
$this->attributeDefaults[$name]=$column->defaultValue;
}
foreach($model->relations() as $name=>$config)
{
$this->addRelation($name,$config);
}
}
public function addRelation($name,$config)
{
if(isset($config[0],$config[1],$config[2]))  // relation class, AR class, FK
$this->relations[$name]=new $config[0]($name,$config[1],$config[2],array_slice($config,3));
else
throw new CDbException(Yii::t('yii','Active record "{class}" has an invalid configuration for relation "{relation}". It must specify the relation type, the related active record class and the foreign key.', array('{class}'=>get_class($this->_model),'{relation}'=>$name)));
}
public function hasRelation($name)
{
return isset($this->relations[$name]);
}
public function removeRelation($name)
{
unset($this->relations[$name]);
}
}