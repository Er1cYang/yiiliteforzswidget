<?php
class CThemeManager extends CApplicationComponent
{
const DEFAULT_BASEPATH='themes';
public $themeClass='CTheme';
private $_basePath=null;
private $_baseUrl=null;
public function getTheme($name)
{
$themePath=$this->getBasePath().DIRECTORY_SEPARATOR.$name;
if(is_dir($themePath))
{
$class=Yii::import($this->themeClass, true);
return new $class($name,$themePath,$this->getBaseUrl().'/'.$name);
}
else
return null;
}
public function getThemeNames()
{
static $themes;
if($themes===null)
{
$themes=array();
$basePath=$this->getBasePath();
$folder=@opendir($basePath);
while(($file=@readdir($folder))!==false)
{
if($file!=='.' && $file!=='..' && $file!=='.svn' && $file!=='.gitignore' && is_dir($basePath.DIRECTORY_SEPARATOR.$file))
$themes[]=$file;
}
closedir($folder);
sort($themes);
}
return $themes;
}
public function getBasePath()
{
if($this->_basePath===null)
$this->setBasePath(dirname(Yii::app()->getRequest()->getScriptFile()).DIRECTORY_SEPARATOR.self::DEFAULT_BASEPATH);
return $this->_basePath;
}
public function setBasePath($value)
{
$this->_basePath=realpath($value);
if($this->_basePath===false || !is_dir($this->_basePath))
throw new CException(Yii::t('yii','Theme directory "{directory}" does not exist.',array('{directory}'=>$value)));
}
public function getBaseUrl()
{
if($this->_baseUrl===null)
$this->_baseUrl=Yii::app()->getBaseUrl().'/'.self::DEFAULT_BASEPATH;
return $this->_baseUrl;
}
public function setBaseUrl($value)
{
$this->_baseUrl=rtrim($value,'/');
}
}
