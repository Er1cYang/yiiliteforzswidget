<?php
abstract class CAction extends CComponent implements IAction
{
private $_id;
private $_controller;
public function __construct($controller,$id)
{
$this->_controller=$controller;
$this->_id=$id;
}
public function getController()
{
return $this->_controller;
}
public function getId()
{
return $this->_id;
}
public function runWithParams($params)
{
$method=new ReflectionMethod($this, 'run');
if($method->getNumberOfParameters()>0)
return $this->runWithParamsInternal($this, $method, $params);
else
return $this->run();
}
protected function runWithParamsInternal($object, $method, $params)
{
$ps=array();
foreach($method->getParameters() as $i=>$param)
{
$name=$param->getName();
if(isset($params[$name]))
{
if($param->isArray())
$ps[]=is_array($params[$name]) ? $params[$name] : array($params[$name]);
else if(!is_array($params[$name]))
$ps[]=$params[$name];
else
return false;
}
else if($param->isDefaultValueAvailable())
$ps[]=$param->getDefaultValue();
else
return false;
}
$method->invokeArgs($object,$ps);
return true;
}
}
