<?php
class CTheme extends CComponent
{
private $_name;
private $_basePath;
private $_baseUrl;
public function __construct($name,$basePath,$baseUrl)
{
$this->_name=$name;
$this->_baseUrl=$baseUrl;
$this->_basePath=$basePath;
}
public function getName()
{
return $this->_name;
}
public function getBaseUrl()
{
return $this->_baseUrl;
}
public function getBasePath()
{
return $this->_basePath;
}
public function getViewPath()
{
return $this->_basePath.DIRECTORY_SEPARATOR.'views';
}
public function getSystemViewPath()
{
return $this->getViewPath().DIRECTORY_SEPARATOR.'system';
}
public function getSkinPath()
{
return $this->getViewPath().DIRECTORY_SEPARATOR.'skins';
}
public function getViewFile($controller,$viewName)
{
$moduleViewPath=$this->getViewPath();
if(($module=$controller->getModule())!==null)
$moduleViewPath.='/'.$module->getId();
return $controller->resolveViewFile($viewName,$this->getViewPath().'/'.$controller->getUniqueId(),$this->getViewPath(),$moduleViewPath);
}
public function getLayoutFile($controller,$layoutName)
{
$moduleViewPath=$basePath=$this->getViewPath();
$module=$controller->getModule();
if(empty($layoutName))
{
while($module!==null)
{
if($module->layout===false)
return false;
if(!empty($module->layout))
break;
$module=$module->getParentModule();
}
if($module===null)
$layoutName=Yii::app()->layout;
else
{
$layoutName=$module->layout;
$moduleViewPath.='/'.$module->getId();
}
}
else if($module!==null)
$moduleViewPath.='/'.$module->getId();
return $controller->resolveViewFile($layoutName,$moduleViewPath.'/layouts',$basePath,$moduleViewPath);
}
}
