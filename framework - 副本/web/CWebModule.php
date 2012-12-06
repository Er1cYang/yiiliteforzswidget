<?php
class CWebModule extends CModule
{
public $defaultController='default';
public $layout;
public $controllerNamespace;
public $controllerMap=array();
private $_controllerPath;
private $_viewPath;
private $_layoutPath;
public function getName()
{
return basename($this->getId());
}
public function getDescription()
{
return '';
}
public function getVersion()
{
return '1.0';
}
public function getControllerPath()
{
if($this->_controllerPath!==null)
return $this->_controllerPath;
else
return $this->_controllerPath=$this->getBasePath().DIRECTORY_SEPARATOR.'controllers';
}
public function setControllerPath($value)
{
if(($this->_controllerPath=realpath($value))===false || !is_dir($this->_controllerPath))
throw new CException(Yii::t('yii','The controller path "{path}" is not a valid directory.',
array('{path}'=>$value)));
}
public function getViewPath()
{
if($this->_viewPath!==null)
return $this->_viewPath;
else
return $this->_viewPath=$this->getBasePath().DIRECTORY_SEPARATOR.'views';
}
public function setViewPath($path)
{
if(($this->_viewPath=realpath($path))===false || !is_dir($this->_viewPath))
throw new CException(Yii::t('yii','The view path "{path}" is not a valid directory.',
array('{path}'=>$path)));
}
public function getLayoutPath()
{
if($this->_layoutPath!==null)
return $this->_layoutPath;
else
return $this->_layoutPath=$this->getViewPath().DIRECTORY_SEPARATOR.'layouts';
}
public function setLayoutPath($path)
{
if(($this->_layoutPath=realpath($path))===false || !is_dir($this->_layoutPath))
throw new CException(Yii::t('yii','The layout path "{path}" is not a valid directory.',
array('{path}'=>$path)));
}
public function beforeControllerAction($controller,$action)
{
if(($parent=$this->getParentModule())===null)
$parent=Yii::app();
return $parent->beforeControllerAction($controller,$action);
}
public function afterControllerAction($controller,$action)
{
if(($parent=$this->getParentModule())===null)
$parent=Yii::app();
$parent->afterControllerAction($controller,$action);
}
}
