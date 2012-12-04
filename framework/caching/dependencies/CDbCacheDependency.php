<?php
class CDbCacheDependency extends CCacheDependency
{
public $connectionID='db';
public $sql;
public $params;
private $_db;
public function __construct($sql=null)
{
$this->sql=$sql;
}
public function __sleep()
{
$this->_db=null;
return array_keys((array)$this);
}
protected function generateDependentData()
{
if($this->sql!==null)
{
$db=$this->getDbConnection();
$command=$db->createCommand($this->sql);
if(is_array($this->params))
{
foreach($this->params as $name=>$value)
$command->bindValue($name,$value);
}
if($db->queryCachingDuration>0)
{
$duration=$db->queryCachingDuration;
$db->queryCachingDuration=0;
$result=$command->queryRow();
$db->queryCachingDuration=$duration;
}
else
$result=$command->queryRow();
return $result;
}
else
throw new CException(Yii::t('yii','CDbCacheDependency.sql cannot be empty.'));
}
protected function getDbConnection()
{
if($this->_db!==null)
return $this->_db;
else
{
if(($this->_db=Yii::app()->getComponent($this->connectionID)) instanceof CDbConnection)
return $this->_db;
else
throw new CException(Yii::t('yii','CDbCacheDependency.connectionID "{id}" is invalid. Please make sure it refers to the ID of a CDbConnection application component.',
array('{id}'=>$this->connectionID)));
}
}
}
