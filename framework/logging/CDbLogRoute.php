<?php
class CDbLogRoute extends CLogRoute
{
public $connectionID;
public $logTableName='YiiLog';
public $autoCreateLogTable=true;
private $_db;
public function init()
{
parent::init();
if($this->autoCreateLogTable)
{
$db=$this->getDbConnection();
try
{
$db->createCommand()->delete($this->logTableName,'0=1');
}
catch(Exception $e)
{
$this->createLogTable($db,$this->logTableName);
}
}
}
protected function createLogTable($db,$tableName)
{
$db->createCommand()->createTable($tableName, array(
'id'=>'pk',
'level'=>'varchar(128)',
'category'=>'varchar(128)',
'logtime'=>'integer',
'message'=>'text',
));
}
protected function getDbConnection()
{
if($this->_db!==null)
return $this->_db;
else if(($id=$this->connectionID)!==null)
{
if(($this->_db=Yii::app()->getComponent($id)) instanceof CDbConnection)
return $this->_db;
else
throw new CException(Yii::t('yii','CDbLogRoute.connectionID "{id}" does not point to a valid CDbConnection application component.',
array('{id}'=>$id)));
}
else
{
$dbFile=Yii::app()->getRuntimePath().DIRECTORY_SEPARATOR.'log-'.Yii::getVersion().'.db';
return $this->_db=new CDbConnection('sqlite:'.$dbFile);
}
}
protected function processLogs($logs)
{
$command=$this->getDbConnection()->createCommand();
foreach($logs as $log)
{
$command->insert($this->logTableName,array(
'level'=>$log[1],
'category'=>$log[2],
'logtime'=>(int)$log[3],
'message'=>$log[0],
));
}
}
}
