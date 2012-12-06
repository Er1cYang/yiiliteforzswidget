<?php
class CStatePersister extends CApplicationComponent implements IStatePersister
{
public $stateFile;
public $cacheID='cache';
public function init()
{
parent::init();
if($this->stateFile===null)
$this->stateFile=Yii::app()->getRuntimePath().DIRECTORY_SEPARATOR.'state.bin';
$dir=dirname($this->stateFile);
if(!is_dir($dir) || !is_writable($dir))
throw new CException(Yii::t('yii','Unable to create application state file "{file}". Make sure the directory containing the file exists and is writable by the Web server process.',
array('{file}'=>$this->stateFile)));
}
public function load()
{
$stateFile=$this->stateFile;
if($this->cacheID!==false && ($cache=Yii::app()->getComponent($this->cacheID))!==null)
{
$cacheKey='Yii.CStatePersister.'.$stateFile;
if(($value=$cache->get($cacheKey))!==false)
return unserialize($value);
else if(($content=@file_get_contents($stateFile))!==false)
{
$cache->set($cacheKey,$content,0,new CFileCacheDependency($stateFile));
return unserialize($content);
}
else
return null;
}
else if(($content=@file_get_contents($stateFile))!==false)
return unserialize($content);
else
return null;
}
public function save($state)
{
file_put_contents($this->stateFile,serialize($state),LOCK_EX);
}
}
