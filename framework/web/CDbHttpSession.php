<?php
class CDbHttpSession extends CHttpSession
{
public $connectionID;
public $sessionTableName='YiiSession';
public $autoCreateSessionTable=true;
private $_db;
public function getUseCustomStorage()
{
return true;
}
public function regenerateID($deleteOldSession=false)
{
$oldID=session_id();
if(empty($oldID))
return;
parent::regenerateID(false);
$newID=session_id();
$db=$this->getDbConnection();
$row=$db->createCommand()
->select()
->from($this->sessionTableName)
->where('id=:id',array(':id'=>$oldID))
->queryRow();
if($row!==false)
{
if($deleteOldSession)
$db->createCommand()->update($this->sessionTableName,array(
'id'=>$newID
),'id=:oldID',array(':oldID'=>$oldID));
else
{
$row['id']=$newID;
$db->createCommand()->insert($this->sessionTableName, $row);
}
}
else
{
$db->createCommand()->insert($this->sessionTableName, array(
'id'=>$newID,
'expire'=>time()+$this->getTimeout(),
));
}
}
protected function createSessionTable($db,$tableName)
{
$driver=$db->getDriverName();
if($driver==='mysql')
$blob='LONGBLOB';
else if($driver==='pgsql')
$blob='BYTEA';
else
$blob='BLOB';
$db->createCommand()->createTable($tableName,array(
'id'=>'CHAR(32) PRIMARY KEY',
'expire'=>'integer',
'data'=>$blob,
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
throw new CException(Yii::t('yii','CDbHttpSession.connectionID "{id}" is invalid. Please make sure it refers to the ID of a CDbConnection application component.',
array('{id}'=>$id)));
}
else
{
$dbFile=Yii::app()->getRuntimePath().DIRECTORY_SEPARATOR.'session-'.Yii::getVersion().'.db';
return $this->_db=new CDbConnection('sqlite:'.$dbFile);
}
}
public function openSession($savePath,$sessionName)
{
if($this->autoCreateSessionTable)
{
$db=$this->getDbConnection();
$db->setActive(true);
try
{
$db->createCommand()->delete($this->sessionTableName,'expire<:expire',array(':expire'=>time()));
}
catch(Exception $e)
{
$this->createSessionTable($db,$this->sessionTableName);
}
}
return true;
}
public function readSession($id)
{
$data=$this->getDbConnection()->createCommand()
->select('data')
->from($this->sessionTableName)
->where('expire>:expire AND id=:id',array(':expire'=>time(),':id'=>$id))
->queryScalar();
return $data===false?'':$data;
}
public function writeSession($id,$data)
{
try
{
$expire=time()+$this->getTimeout();
$db=$this->getDbConnection();
if($db->createCommand()->select('id')->from($this->sessionTableName)->where('id=:id',array(':id'=>$id))->queryScalar()===false)
$db->createCommand()->insert($this->sessionTableName,array(
'id'=>$id,
'data'=>$data,
'expire'=>$expire,
));
else
$db->createCommand()->update($this->sessionTableName,array(
'data'=>$data,
'expire'=>$expire
),'id=:id',array(':id'=>$id));
}
catch(Exception $e)
{
if(YII_DEBUG)
echo $e->getMessage();
return false;
}
return true;
}
public function destroySession($id)
{
$this->getDbConnection()->createCommand()
->delete($this->sessionTableName,'id=:id',array(':id'=>$id));
return true;
}
public function gcSession($maxLifetime)
{
$this->getDbConnection()->createCommand()
->delete($this->sessionTableName,'expire<:expire',array(':expire'=>time()));
return true;
}
}
