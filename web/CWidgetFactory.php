<?php
class CWidgetFactory extends CApplicationComponent implements IWidgetFactory
{
public $enableSkin=false;
public $widgets=array();
public $skinnableWidgets;
public $skinPath;
private $_skins=array();  // class name, skin name, property name=>value
public function init()
{
parent::init();
if($this->enableSkin && $this->skinPath===null)
$this->skinPath=Yii::app()->getViewPath().DIRECTORY_SEPARATOR.'skins';
}
public function createWidget($owner,$className,$properties=array())
{
$className=Yii::import($className,true);
$widget=new $className($owner);
if(isset($this->widgets[$className]))
$properties=$properties===array() ? $this->widgets[$className] : CMap::mergeArray($this->widgets[$className],$properties);
if($this->enableSkin)
{
if($this->skinnableWidgets===null || in_array($className,$this->skinnableWidgets))
{
$skinName=isset($properties['skin']) ? $properties['skin'] : 'default';
if($skinName!==false && ($skin=$this->getSkin($className,$skinName))!==array())
$properties=$properties===array() ? $skin : CMap::mergeArray($skin,$properties);
}
}
foreach($properties as $name=>$value)
$widget->$name=$value;
return $widget;
}
protected function getSkin($className,$skinName)
{
if(!isset($this->_skins[$className][$skinName]))
{
$skinFile=$this->skinPath.DIRECTORY_SEPARATOR.$className.'.php';
if(is_file($skinFile))
$this->_skins[$className]=require($skinFile);
else
$this->_skins[$className]=array();
if(($theme=Yii::app()->getTheme())!==null)
{
$skinFile=$theme->getSkinPath().DIRECTORY_SEPARATOR.$className.'.php';
if(is_file($skinFile))
{
$skins=require($skinFile);
foreach($skins as $name=>$skin)
$this->_skins[$className][$name]=$skin;
}
}
if(!isset($this->_skins[$className][$skinName]))
$this->_skins[$className][$skinName]=array();
}
return $this->_skins[$className][$skinName];
}
}