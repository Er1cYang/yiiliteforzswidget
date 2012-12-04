<?php
class CAssetManager extends CApplicationComponent
{
const DEFAULT_BASEPATH='assets';
public $linkAssets=false;
public $excludeFiles=array('.svn','.gitignore');
public $newFileMode=0666;
public $newDirMode=0777;
public $forceCopy=false;
private $_basePath;
private $_baseUrl;
private $_published=array();
public function getBasePath()
{
if($this->_basePath===null)
{
$request=Yii::app()->getRequest();
$this->setBasePath(dirname($request->getScriptFile()).DIRECTORY_SEPARATOR.self::DEFAULT_BASEPATH);
}
return $this->_basePath;
}
public function setBasePath($value)
{
if(($basePath=realpath($value))!==false && is_dir($basePath) && is_writable($basePath))
$this->_basePath=$basePath;
else
throw new CException(Yii::t('yii','CAssetManager.basePath "{path}" is invalid. Please make sure the directory exists and is writable by the Web server process.',
array('{path}'=>$value)));
}
public function getBaseUrl()
{
if($this->_baseUrl===null)
{
$request=Yii::app()->getRequest();
$this->setBaseUrl($request->getBaseUrl().'/'.self::DEFAULT_BASEPATH);
}
return $this->_baseUrl;
}
public function setBaseUrl($value)
{
$this->_baseUrl=rtrim($value,'/');
}
public function publish($path,$hashByName=false,$level=-1,$forceCopy=null)
{
if($forceCopy===null)
$forceCopy=$this->forceCopy;
if(isset($this->_published[$path]))
return $this->_published[$path];
else if(($src=realpath($path))!==false)
{
if(is_file($src))
{
$dir=$this->hash($hashByName ? basename($src) : dirname($src).filemtime($src));
$fileName=basename($src);
$dstDir=$this->getBasePath().DIRECTORY_SEPARATOR.$dir;
$dstFile=$dstDir.DIRECTORY_SEPARATOR.$fileName;
if($this->linkAssets)
{
if(!is_file($dstFile))
{
if(!is_dir($dstDir))
{
mkdir($dstDir);
@chmod($dstDir, $this->newDirMode);
}
symlink($src,$dstFile);
}
}
else if(@filemtime($dstFile)<@filemtime($src))
{
if(!is_dir($dstDir))
{
mkdir($dstDir);
@chmod($dstDir, $this->newDirMode);
}
copy($src,$dstFile);
@chmod($dstFile, $this->newFileMode);
}
return $this->_published[$path]=$this->getBaseUrl()."/$dir/$fileName";
}
else if(is_dir($src))
{
$dir=$this->hash($hashByName ? basename($src) : $src.filemtime($src));
$dstDir=$this->getBasePath().DIRECTORY_SEPARATOR.$dir;
if($this->linkAssets)
{
if(!is_dir($dstDir))
symlink($src,$dstDir);
}
else if(!is_dir($dstDir) || $forceCopy)
{
CFileHelper::copyDirectory($src,$dstDir,array(
'exclude'=>$this->excludeFiles,
'level'=>$level,
'newDirMode'=>$this->newDirMode,
'newFileMode'=>$this->newFileMode,
));
}
return $this->_published[$path]=$this->getBaseUrl().'/'.$dir;
}
}
throw new CException(Yii::t('yii','The asset "{asset}" to be published does not exist.',
array('{asset}'=>$path)));
}
public function getPublishedPath($path,$hashByName=false)
{
if(($path=realpath($path))!==false)
{
$base=$this->getBasePath().DIRECTORY_SEPARATOR;
if(is_file($path))
return $base . $this->hash($hashByName ? basename($path) : dirname($path).filemtime($path)) . DIRECTORY_SEPARATOR . basename($path);
else
return $base . $this->hash($hashByName ? basename($path) : $path.filemtime($path));
}
else
return false;
}
public function getPublishedUrl($path,$hashByName=false)
{
if(isset($this->_published[$path]))
return $this->_published[$path];
if(($path=realpath($path))!==false)
{
if(is_file($path))
return $this->getBaseUrl().'/'.$this->hash($hashByName ? basename($path) : dirname($path).filemtime($path)).'/'.basename($path);
else
return $this->getBaseUrl().'/'.$this->hash($hashByName ? basename($path) : $path.filemtime($path));
}
else
return false;
}
protected function hash($path)
{
return sprintf('%x',crc32($path.Yii::getVersion()));
}
}
