<?php
class CExtController extends CController
{
private $_viewPath;
public function getViewPath()
{
if($this->_viewPath===null)
{
$class=new ReflectionClass(get_class($this));
$this->_viewPath=dirname($class->getFileName()).DIRECTORY_SEPARATOR.'views';
}
return $this->_viewPath;
}
public function setViewPath($value)
{
$this->_viewPath=$value;
}
}
