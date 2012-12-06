<?php
class CChainedCacheDependency extends CComponent implements ICacheDependency
{
private $_dependencies=null;
public function __construct($dependencies=array())
{
if(!empty($dependencies))
$this->setDependencies($dependencies);
}
public function getDependencies()
{
if($this->_dependencies===null)
$this->_dependencies=new CTypedList('ICacheDependency');
return $this->_dependencies;
}
public function setDependencies($values)
{
$dependencies=$this->getDependencies();
foreach($values as $value)
{
if(is_array($value))
$value=Yii::createComponent($value);
$dependencies->add($value);
}
}
public function evaluateDependency()
{
if($this->_dependencies!==null)
{
foreach($this->_dependencies as $dependency)
$dependency->evaluateDependency();
}
}
public function getHasChanged()
{
if($this->_dependencies!==null)
{
foreach($this->_dependencies as $dependency)
if($dependency->getHasChanged())
return true;
}
return false;
}
}
