<?php
class CInlineAction extends CAction
{
public function run()
{
$method='action'.$this->getId();
$this->getController()->$method();
}
public function runWithParams($params)
{
$methodName='action'.$this->getId();
$controller=$this->getController();
$method=new ReflectionMethod($controller, $methodName);
if($method->getNumberOfParameters()>0)
return $this->runWithParamsInternal($controller, $method, $params);
else
return $controller->$methodName();
}
}
