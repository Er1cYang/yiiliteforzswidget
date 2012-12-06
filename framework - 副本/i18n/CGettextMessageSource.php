<?php
class CGettextMessageSource extends CMessageSource
{
const CACHE_KEY_PREFIX='Yii.CGettextMessageSource.';
const MO_FILE_EXT='.mo';
const PO_FILE_EXT='.po';
public $cachingDuration=0;
public $cacheID='cache';
public $basePath;
public $useMoFile=true;
public $useBigEndian=false;
public $catalog='messages';
public function init()
{
parent::init();
if($this->basePath===null)
$this->basePath=Yii::getPathOfAlias('application.messages');
}
protected function loadMessages($category, $language)
{
$messageFile=$this->basePath . DIRECTORY_SEPARATOR . $language . DIRECTORY_SEPARATOR . $this->catalog;
if($this->useMoFile)
$messageFile.=self::MO_FILE_EXT;
else
$messageFile.=self::PO_FILE_EXT;
if ($this->cachingDuration > 0 && $this->cacheID!==false && ($cache=Yii::app()->getComponent($this->cacheID))!==null)
{
$key = self::CACHE_KEY_PREFIX . $messageFile;
if (($data=$cache->get($key)) !== false)
return unserialize($data);
}
if (is_file($messageFile))
{
if($this->useMoFile)
$file=new CGettextMoFile($this->useBigEndian);
else
$file=new CGettextPoFile();
$messages=$file->load($messageFile,$category);
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
