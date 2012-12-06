<?php
class CFileHelper
{
public static function getExtension($path)
{
return pathinfo($path, PATHINFO_EXTENSION);
}
public static function copyDirectory($src,$dst,$options=array())
{
$fileTypes=array();
$exclude=array();
$level=-1;
extract($options);
self::copyDirectoryRecursive($src,$dst,'',$fileTypes,$exclude,$level,$options);
}
public static function findFiles($dir,$options=array())
{
$fileTypes=array();
$exclude=array();
$level=-1;
extract($options);
$list=self::findFilesRecursive($dir,'',$fileTypes,$exclude,$level);
sort($list);
return $list;
}
protected static function copyDirectoryRecursive($src,$dst,$base,$fileTypes,$exclude,$level,$options)
{
if(!is_dir($dst))
mkdir($dst);
if(isset($options['newDirMode']))
@chmod($dst,$options['newDirMode']);
else
@chmod($dst,0777);
$folder=opendir($src);
while(($file=readdir($folder))!==false)
{
if($file==='.' || $file==='..')
continue;
$path=$src.DIRECTORY_SEPARATOR.$file;
$isFile=is_file($path);
if(self::validatePath($base,$file,$isFile,$fileTypes,$exclude))
{
if($isFile)
{
copy($path,$dst.DIRECTORY_SEPARATOR.$file);
if(isset($options['newFileMode']))
@chmod($dst.DIRECTORY_SEPARATOR.$file, $options['newFileMode']);
}
else if($level)
self::copyDirectoryRecursive($path,$dst.DIRECTORY_SEPARATOR.$file,$base.'/'.$file,$fileTypes,$exclude,$level-1,$options);
}
}
closedir($folder);
}
protected static function findFilesRecursive($dir,$base,$fileTypes,$exclude,$level)
{
$list=array();
$handle=opendir($dir);
while(($file=readdir($handle))!==false)
{
if($file==='.' || $file==='..')
continue;
$path=$dir.DIRECTORY_SEPARATOR.$file;
$isFile=is_file($path);
if(self::validatePath($base,$file,$isFile,$fileTypes,$exclude))
{
if($isFile)
$list[]=$path;
else if($level)
$list=array_merge($list,self::findFilesRecursive($path,$base.'/'.$file,$fileTypes,$exclude,$level-1));
}
}
closedir($handle);
return $list;
}
protected static function validatePath($base,$file,$isFile,$fileTypes,$exclude)
{
foreach($exclude as $e)
{
if($file===$e || strpos($base.'/'.$file,$e)===0)
return false;
}
if(!$isFile || empty($fileTypes))
return true;
if(($type=pathinfo($file, PATHINFO_EXTENSION))!=='')
return in_array($type,$fileTypes);
else
return false;
}
public static function getMimeType($file,$magicFile=null,$checkExtension=true)
{
if(function_exists('finfo_open'))
{
$options=defined('FILEINFO_MIME_TYPE') ? FILEINFO_MIME_TYPE : FILEINFO_MIME;
$info=$magicFile===null ? finfo_open($options) : finfo_open($options,$magicFile);
if($info && ($result=finfo_file($info,$file))!==false)
return $result;
}
if(function_exists('mime_content_type') && ($result=mime_content_type($file))!==false)
return $result;
return $checkExtension ? self::getMimeTypeByExtension($file) : null;
}
public static function getMimeTypeByExtension($file,$magicFile=null)
{
static $extensions;
if($extensions===null)
$extensions=$magicFile===null ? require(Yii::getPathOfAlias('system.utils.mimeTypes').'.php') : $magicFile;
if(($ext=pathinfo($file, PATHINFO_EXTENSION))!=='')
{
$ext=strtolower($ext);
if(isset($extensions[$ext]))
return $extensions[$ext];
}
return null;
}
}
