<?php
class CWidget extends CBaseController
{
public $actionPrefix;
public $skin='default';
private static $_viewPaths;
private static $_counter=0;
private $_id;
private $_owner;
public static function actions()
{
return array();
}
public function __construct($owner=null)
{
$this->_owner=$owner===null?Yii::app()->getController():$owner;
}
public function getOwner()
{
return $this->_owner;
}
public function getId($autoGenerate=true)
{
if($this->_id!==null)
return $this->_id;
else if($autoGenerate)
return $this->_id='yw'.self::$_counter++;
}
public function setId($value)
{
$this->_id=$value;
}
public function getController()
{
if($this->_owner instanceof CController)
return $this->_owner;
else
return Yii::app()->getController();
}
public function init()
{
}
public function run()
{
}
public function getViewPath($checkTheme=false)
{
$className=get_class($this);
if(isset(self::$_viewPaths[$className]))
return self::$_viewPaths[$className];
else
{
if($checkTheme && ($theme=Yii::app()->getTheme())!==null)
{
$path=$theme->getViewPath().DIRECTORY_SEPARATOR;
if(strpos($className,'\\')!==false) // namespaced class
$path.=str_replace('\\','_',ltrim($className,'\\'));
else
$path.=$className;
if(is_dir($path))
return self::$_viewPaths[$className]=$path;
}
$class=new ReflectionClass($className);
return self::$_viewPaths[$className]=dirname($class->getFileName()).DIRECTORY_SEPARATOR.'views';
}
}
public function getViewFile($viewName)
{
if(($renderer=Yii::app()->getViewRenderer())!==null)
$extension=$renderer->fileExtension;
else
$extension='.php';
if(strpos($viewName,'.')) // a path alias
$viewFile=Yii::getPathOfAlias($viewName);
else
{
$viewFile=$this->getViewPath(true).DIRECTORY_SEPARATOR.$viewName;
if(is_file($viewFile.$extension))
return Yii::app()->findLocalizedFile($viewFile.$extension);
else if($extension!=='.php' && is_file($viewFile.'.php'))
return Yii::app()->findLocalizedFile($viewFile.'.php');
$viewFile=$this->getViewPath(false).DIRECTORY_SEPARATOR.$viewName;
}
if(is_file($viewFile.$extension))
return Yii::app()->findLocalizedFile($viewFile.$extension);
else if($extension!=='.php' && is_file($viewFile.'.php'))
return Yii::app()->findLocalizedFile($viewFile.'.php');
else
return false;
}
public function render($view,$data=null,$return=false)
{
if(($viewFile=$this->getViewFile($view))!==false)
return $this->renderFile($viewFile,$data,$return);
else
throw new CException(Yii::t('yii','{widget} cannot find the view "{view}".',
array('{widget}'=>get_class($this), '{view}'=>$view)));
}
}