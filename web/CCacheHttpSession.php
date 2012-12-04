<?php
class CCacheHttpSession extends CHttpSession
{
const CACHE_KEY_PREFIX='Yii.CCacheHttpSession.';
public $cacheID='cache';
private $_cache;
public function init()
{
$this->_cache=Yii::app()->getComponent($this->cacheID);
if(!($this->_cache instanceof ICache))
throw new CException(Yii::t('yii','CCacheHttpSession.cacheID is invalid. Please make sure "{id}" refers to a valid cache application component.',
array('{id}'=>$this->cacheID)));
parent::init();
}
public function getUseCustomStorage()
{
return true;
}
public function readSession($id)
{
$data=$this->_cache->get($this->calculateKey($id));
return $data===false?'':$data;
}
public function writeSession($id,$data)
{
return $this->_cache->set($this->calculateKey($id),$data,$this->getTimeout());
}
public function destroySession($id)
{
return $this->_cache->delete($this->calculateKey($id));
}
protected function calculateKey($id)
{
return self::CACHE_KEY_PREFIX.$id;
}
}
