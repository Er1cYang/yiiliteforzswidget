<?php
class CCacheDependency extends CComponent implements ICacheDependency
{
public $reuseDependentData=false;
private static $_reusableData=array();
private $_hash;
private $_data;
public function evaluateDependency()
{
if ($this->reuseDependentData)
{
$hash=$this->getHash();
if (!isset(self::$_reusableData[$hash]['dependentData']))
self::$_reusableData[$hash]['dependentData']=$this->generateDependentData();
$this->_data=self::$_reusableData[$hash]['dependentData'];
}
else
$this->_data=$this->generateDependentData();
}
public function getHasChanged()
{
if ($this->reuseDependentData)
{
$hash=$this->getHash();
if (!isset(self::$_reusableData[$hash]['hasChanged']))
{
if (!isset(self::$_reusableData[$hash]['dependentData']))
self::$_reusableData[$hash]['dependentData']=$this->generateDependentData();
self::$_reusableData[$hash]['hasChanged']=self::$_reusableData[$hash]['dependentData']!=$this->_data;
}
return self::$_reusableData[$hash]['hasChanged'];
}
else
return $this->generateDependentData()!=$this->_data;
}
public function getDependentData()
{
return $this->_data;
}
protected function generateDependentData()
{
return null;
}
private function getHash()
{
if($this->_hash===null)
$this->_hash=sha1(serialize($this));
return $this->_hash;
}
}