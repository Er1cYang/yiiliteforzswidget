<?php
class CFileCache extends CCache
{
public $cachePath;
public $cacheFileSuffix='.bin';
public $directoryLevel=0;
private $_gcProbability=100;
private $_gced=false;
public function init()
{
parent::init();
if($this->cachePath===null)
$this->cachePath=Yii::app()->getRuntimePath().DIRECTORY_SEPARATOR.'cache';
if(!is_dir($this->cachePath))
mkdir($this->cachePath,0777,true);
}
public function getGCProbability()
{
return $this->_gcProbability;
}
public function setGCProbability($value)
{
$value=(int)$value;
if($value<0)
$value=0;
if($value>1000000)
$value=1000000;
$this->_gcProbability=$value;
}
protected function flushValues()
{
$this->gc(false);
return true;
}
protected function getValue($key)
{
$cacheFile=$this->getCacheFile($key);
if(($time=@filemtime($cacheFile))>time())
return @file_get_contents($cacheFile);
else if($time>0)
@unlink($cacheFile);
return false;
}
protected function setValue($key,$value,$expire)
{
if(!$this->_gced && mt_rand(0,1000000)<$this->_gcProbability)
{
$this->gc();
$this->_gced=true;
}
if($expire<=0)
$expire=31536000;//1 year
$expire+=time();
$cacheFile=$this->getCacheFile($key);
if($this->directoryLevel>0)
@mkdir(dirname($cacheFile),0777,true);
if(@file_put_contents($cacheFile,$value,LOCK_EX)!==false)
{
@chmod($cacheFile,0777);
return @touch($cacheFile,$expire);
}
else
return false;
}
protected function addValue($key,$value,$expire)
{
$cacheFile=$this->getCacheFile($key);
if(@filemtime($cacheFile)>time())
return false;
return $this->setValue($key,$value,$expire);
}
protected function deleteValue($key)
{
$cacheFile=$this->getCacheFile($key);
return @unlink($cacheFile);
}
protected function getCacheFile($key)
{
if($this->directoryLevel>0)
{
$base=$this->cachePath;
for($i=0;$i<$this->directoryLevel;++$i)
{
if(($prefix=substr($key,$i+$i,2))!==false)
$base.=DIRECTORY_SEPARATOR.$prefix;
}
return $base.DIRECTORY_SEPARATOR.$key.$this->cacheFileSuffix;
}
else
return $this->cachePath.DIRECTORY_SEPARATOR.$key.$this->cacheFileSuffix;
}
public function gc($expiredOnly=true,$path=null)
{
if($path===null)
$path=$this->cachePath;
if(($handle=opendir($path))===false)
return;
while(($file=readdir($handle))!==false)
{
if($file[0]==='.')
continue;
$fullPath=$path.DIRECTORY_SEPARATOR.$file;
if(is_dir($fullPath))
$this->gc($expiredOnly,$fullPath);
else if($expiredOnly && @filemtime($fullPath)<time() || !$expiredOnly)
@unlink($fullPath);
}
closedir($handle);
}
}
