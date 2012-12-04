<?php
abstract class CCache extends CApplicationComponent implements ICache, ArrayAccess
{
public $keyPrefix;
public $hashKey=true;
public $serializer;
public function init()
{
parent::init();
if($this->keyPrefix===null)
$this->keyPrefix=Yii::app()->getId();
}
protected function generateUniqueKey($key)
{
return $this->hashKey ? md5($this->keyPrefix.$key) : $this->keyPrefix.$key;
}
public function get($id)
{
$value = $this->getValue($this->generateUniqueKey($id));
if($value===false || $this->serializer===false)
return $value;
if($this->serializer===null)
$value=unserialize($value);
else
$value=call_user_func($this->serializer[1], $value);
if(is_array($value) && (!$value[1] instanceof ICacheDependency || !$value[1]->getHasChanged()))
{
Yii::trace('Serving "'.$id.'" from cache','system.caching.'.get_class($this));
return $value[0];
}
else
return false;
}
public function mget($ids)
{
$uids = array();
foreach ($ids as $id)
$uids[$id] = $this->generateUniqueKey($id);
$values = $this->getValues($uids);
$results = array();
if($this->serializer === false)
{
foreach ($uids as $id=>$uid)
$results[$id] = isset($values[$uid]) ? $values[$uid] : false;
}
else
{
foreach($uids as $id=>$uid)
{
$results[$id] = false;
if(isset($values[$uid]))
{
$value = $this->serializer === null ? unserialize($values[$uid]) : call_user_func($this->serializer[1], $values[$uid]);
if(is_array($value) && (!$value[1] instanceof ICacheDependency || !$value[1]->getHasChanged()))
{
Yii::trace('Serving "'.$id.'" from cache','system.caching.'.get_class($this));
$results[$id] = $value[0];
}
}
}
}
return $results;
}
public function set($id,$value,$expire=0,$dependency=null)
{
Yii::trace('Saving "'.$id.'" to cache','system.caching.'.get_class($this));
if ($dependency !== null && $this->serializer !== false)
$dependency->evaluateDependency();
if ($this->serializer === null)
$value = serialize(array($value,$dependency));
elseif ($this->serializer !== false)
$value = call_user_func($this->serializer[0], array($value,$dependency));
return $this->setValue($this->generateUniqueKey($id), $value, $expire);
}
public function add($id,$value,$expire=0,$dependency=null)
{
Yii::trace('Adding "'.$id.'" to cache','system.caching.'.get_class($this));
if ($dependency !== null && $this->serializer !== false)
$dependency->evaluateDependency();
if ($this->serializer === null)
$value = serialize(array($value,$dependency));
elseif ($this->serializer !== false)
$value = call_user_func($this->serializer[0], array($value,$dependency));
return $this->addValue($this->generateUniqueKey($id), $value, $expire);
}
public function delete($id)
{
Yii::trace('Deleting "'.$id.'" from cache','system.caching.'.get_class($this));
return $this->deleteValue($this->generateUniqueKey($id));
}
public function flush()
{
Yii::trace('Flushing cache','system.caching.'.get_class($this));
return $this->flushValues();
}
protected function getValue($key)
{
throw new CException(Yii::t('yii','{className} does not support get() functionality.',
array('{className}'=>get_class($this))));
}
protected function getValues($keys)
{
$results=array();
foreach($keys as $key)
$results[$key]=$this->getValue($key);
return $results;
}
protected function setValue($key,$value,$expire)
{
throw new CException(Yii::t('yii','{className} does not support set() functionality.',
array('{className}'=>get_class($this))));
}
protected function addValue($key,$value,$expire)
{
throw new CException(Yii::t('yii','{className} does not support add() functionality.',
array('{className}'=>get_class($this))));
}
protected function deleteValue($key)
{
throw new CException(Yii::t('yii','{className} does not support delete() functionality.',
array('{className}'=>get_class($this))));
}
protected function flushValues()
{
throw new CException(Yii::t('yii','{className} does not support flushValues() functionality.',
array('{className}'=>get_class($this))));
}
public function offsetExists($id)
{
return $this->get($id)!==false;
}
public function offsetGet($id)
{
return $this->get($id);
}
public function offsetSet($id, $value)
{
$this->set($id, $value);
}
public function offsetUnset($id)
{
$this->delete($id);
}
}