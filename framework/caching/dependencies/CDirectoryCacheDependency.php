<?php
class CDirectoryCacheDependency extends CCacheDependency
{
public $directory;
public $recursiveLevel=-1;
public $namePattern;
public function __construct($directory=null)
{
$this->directory=$directory;
}
protected function generateDependentData()
{
if($this->directory!==null)
return $this->generateTimestamps($this->directory);
else
throw new CException(Yii::t('yii','CDirectoryCacheDependency.directory cannot be empty.'));
}
protected function generateTimestamps($directory,$level=0)
{
if(($dir=@opendir($directory))===false)
throw new CException(Yii::t('yii','"{path}" is not a valid directory.',
array('{path}'=>$directory)));
$timestamps=array();
while(($file=readdir($dir))!==false)
{
$path=$directory.DIRECTORY_SEPARATOR.$file;
if($file==='.' || $file==='..')
continue;
if($this->namePattern!==null && !preg_match($this->namePattern,$file))
continue;
if(is_file($path))
{
if($this->validateFile($path))
$timestamps[$path]=filemtime($path);
}
else
{
if(($this->recursiveLevel<0 || $level<$this->recursiveLevel) && $this->validateDirectory($path))
$timestamps=array_merge($timestamps, $this->generateTimestamps($path,$level+1));
}
}
closedir($dir);
return $timestamps;
}
protected function validateFile($fileName)
{
return true;
}
protected function validateDirectory($directory)
{
return true;
}
}
