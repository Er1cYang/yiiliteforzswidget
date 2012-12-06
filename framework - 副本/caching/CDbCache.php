<?php
class CDbCache extends CCache
{
public $connectionID;
public $cacheTableName='YiiCache';
public $autoCreateCacheTable=true;
private $_db;
private $_gcProbability=100;
private $_gced=false;
public function init()
{
parent::init();
$db=$this->getDbConnection();
$db->setActive(true);
if($this->autoCreateCacheTable)
{
$sql="DELETE FROM {$this->cacheTableName} WHERE expire>0 AND expire<".time();
try
{
$db->createCommand($sql)->execute();
}
catch(Exception $e)
{
$this->createCacheTable($db,$this->cacheTableName);
}
}
}
public function getGCProbability()
{
return $this->_gcProbability;
}
public function setGCProbability($value)
{
$value=(int)$value;
if($value<0)
$value=0;
if($value>1000000)
$value=1000000;
$this->_gcProbability=$value;
}
protected function createCacheTable($db,$tableName)
{
$driver=$db->getDriverName();
if($driver==='mysql')
$blob='LONGBLOB';
else if($driver==='pgsql')
$blob='BYTEA';
else
$blob='BLOB';
$sql=<<<EOD
CREATE TABLE $tableName
(
id CHAR(128) PRIMARY KEY,
expire INTEGER,
value $blob
)
EOD;
$db->createCommand($sql)->execute();
}
public function getDbConnection()
{
if($this->_db!==null)
return $this->_db;
else if(($id=$this->connectionID)!==null)
{
if(($this->_db=Yii::app()->getComponent($id)) instanceof CDbConnection)
return $this->_db;
else
throw new CException(Yii::t('yii','CDbCache.connectionID "{id}" is invalid. Please make sure it refers to the ID of a CDbConnection application component.',
array('{id}'=>$id)));
}
else
{
$dbFile=Yii::app()->getRuntimePath().DIRECTORY_SEPARATOR.'cache-'.Yii::getVersion().'.db';
return $this->_db=new CDbConnection('sqlite:'.$dbFile);
}
}
public function setDbConnection($value)
{
$this->_db=$value;
}
protected function getValue($key)
{
$time=time();
$sql="SELECT value FROM {$this->cacheTableName} WHERE id='$key' AND (expire=0 OR expire>$time)";
$db=$this->getDbConnection();
if($db->queryCachingDuration>0)
{
$duration=$db->queryCachingDuration;
$db->queryCachingDuration=0;
$result=$db->createCommand($sql)->queryScalar();
$db->queryCachingDuration=$duration;
return $result;
}
else
return $db->createCommand($sql)->queryScalar();
}
protected function getValues($keys)
{
if(empty($keys))
return array();
$ids=implode("','",$keys);
$time=time();
$sql="SELECT id, value FROM {$this->cacheTableName} WHERE id IN ('$ids') AND (expire=0 OR expire>$time)";
$db=$this->getDbConnection();
if($db->queryCachingDuration>0)
{
$duration=$db->queryCachingDuration;
$db->queryCachingDuration=0;
$rows=$db->createCommand($sql)->queryAll();
$db->queryCachingDuration=$duration;
}
else
$rows=$db->createCommand($sql)->queryAll();
$results=array();
foreach($keys as $key)
$results[$key]=false;
foreach($rows as $row)
$results[$row['id']]=$row['value'];
return $results;
}
protected function setValue($key,$value,$expire)
{
$this->deleteValue($key);
return $this->addValue($key,$value,$expire);
}
protected function addValue($key,$value,$expire)
{
if(!$this->_gced && mt_rand(0,1000000)<$this->_gcProbability)
{
$this->gc();
$this->_gced=true;
}
if($expire>0)
$expire+=time();
else
$expire=0;
$sql="INSERT INTO {$this->cacheTableName} (id,expire,value) VALUES ('$key',$expire,:value)";
try
{
$command=$this->getDbConnection()->createCommand($sql);
$command->bindValue(':value',$value,PDO::PARAM_LOB);
$command->execute();
return true;
}
catch(Exception $e)
{
return false;
}
}
protected function deleteValue($key)
{
$sql="DELETE FROM {$this->cacheTableName} WHERE id='$key'";
$this->getDbConnection()->createCommand($sql)->execute();
return true;
}
protected function gc()
{
$this->getDbConnection()->createCommand("DELETE FROM {$this->cacheTableName} WHERE expire>0 AND expire<".time())->execute();
}
protected function flushValues()
{
$this->getDbConnection()->createCommand("DELETE FROM {$this->cacheTableName}")->execute();
return true;
}
}
