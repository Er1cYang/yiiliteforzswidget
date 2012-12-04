<?php
class CPhpMessageSource extends CMessageSource
{
const CACHE_KEY_PREFIX='Yii.CPhpMessageSource.';
public $cachingDuration=0;
public $cacheID='cache';
public $basePath;
private $_files=array();
public function init()
{
parent::init();
if($this->basePath===null)
$this->basePath=Yii::getPathOfAlias('application.messages');
}
protected function getMessageFile($category,$language)
{
if(!isset($this->_files[$category][$language]))
{
if(($pos=strpos($category,'.'))!==false)
{
$moduleClass=substr($category,0,$pos);
$moduleCategory=substr($category,$pos+1);
$class=new ReflectionClass($moduleClass);
$this->_files[$category][$language]=dirname($class->getFileName()).DIRECTORY_SEPARATOR.'messages'.DIRECTORY_SEPARATOR.$language.DIRECTORY_SEPARATOR.$moduleCategory.'.php';
}
else
$this->_files[$category][$language]=$this->basePath.DIRECTORY_SEPARATOR.$language.DIRECTORY_SEPARATOR.$category.'.php';
}
return $this->_files[$category][$language];
}
protected function loadMessages($category,$language)
{
$messageFile=$this->getMessageFile($category,$language);
if($this->cachingDuration>0 && $this->cacheID!==false && ($cache=Yii::app()->getComponent($this->cacheID))!==null)
{
$key=self::CACHE_KEY_PREFIX . $messageFile;
if(($data=$cache->get($key))!==false)
return unserialize($data);
}
if(is_file($messageFile))
{
$messages=include($messageFile);
if(!is_array($messages))
$messages=array();
if(isset($cache))
{
$dependency=new CFileCacheDependency($messageFile);
$cache->set($key,serialize($messages),$this->cachingDuration,$dependency);
}
return $messages;
}
else
return array();
}
}