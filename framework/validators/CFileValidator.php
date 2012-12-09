<?php
class CFileValidator extends CValidator
{
public $allowEmpty=false;
public $types;
public $mimeTypes;
public $minSize;
public $maxSize;
public $tooLarge;
public $tooSmall;
public $wrongType;
public $wrongMimeType;
public $maxFiles=1;
public $tooMany;
public $safe=false;
protected function validateAttribute($object, $attribute)
{
if($this->maxFiles > 1)
{
$files=$object->$attribute;
if(!is_array($files) || !isset($files[0]) || !$files[0] instanceof CUploadedFile)
$files = CUploadedFile::getInstances($object, $attribute);
if(array()===$files)
return $this->emptyAttribute($object, $attribute);
if(count($files) > $this->maxFiles)
{
$message=$this->tooMany!==null?$this->tooMany : Yii::t('yii', '{attribute} cannot accept more than {limit} files.');
$this->addError($object, $attribute, $message, array('{attribute}'=>$attribute, '{limit}'=>$this->maxFiles));
}
else
foreach($files as $file)
$this->validateFile($object, $attribute, $file);
}
else
{
$file = $object->$attribute;
if(!$file instanceof CUploadedFile)
{
$file = CUploadedFile::getInstance($object, $attribute);
if(null===$file)
return $this->emptyAttribute($object, $attribute);
}
$this->validateFile($object, $attribute, $file);
}
}
protected function validateFile($object, $attribute, $file)
{
if(null===$file || ($error=$file->getError())==UPLOAD_ERR_NO_FILE)
return $this->emptyAttribute($object, $attribute);
else if($error==UPLOAD_ERR_INI_SIZE || $error==UPLOAD_ERR_FORM_SIZE || $this->maxSize!==null && $file->getSize()>$this->maxSize)
{
$message=$this->tooLarge!==null?$this->tooLarge : Yii::t('yii','The file "{file}" is too large. Its size cannot exceed {limit} bytes.');
$this->addError($object,$attribute,$message,array('{file}'=>$file->getName(), '{limit}'=>$this->getSizeLimit()));
}
else if($error==UPLOAD_ERR_PARTIAL)
throw new CException(Yii::t('yii','The file "{file}" was only partially uploaded.',array('{file}'=>$file->getName())));
else if($error==UPLOAD_ERR_NO_TMP_DIR)
throw new CException(Yii::t('yii','Missing the temporary folder to store the uploaded file "{file}".',array('{file}'=>$file->getName())));
else if($error==UPLOAD_ERR_CANT_WRITE)
throw new CException(Yii::t('yii','Failed to write the uploaded file "{file}" to disk.',array('{file}'=>$file->getName())));
else if(defined('UPLOAD_ERR_EXTENSION') && $error==UPLOAD_ERR_EXTENSION)//available for PHP 5.2.0 or above
throw new CException(Yii::t('yii','File upload was stopped by extension.'));
if($this->minSize!==null && $file->getSize()<$this->minSize)
{
$message=$this->tooSmall!==null?$this->tooSmall : Yii::t('yii','The file "{file}" is too small. Its size cannot be smaller than {limit} bytes.');
$this->addError($object,$attribute,$message,array('{file}'=>$file->getName(), '{limit}'=>$this->minSize));
}
if($this->types!==null)
{
if(is_string($this->types))
$types=preg_split('/[\s,]+/',strtolower($this->types),-1,PREG_SPLIT_NO_EMPTY);
else
$types=$this->types;
if(!in_array(strtolower($file->getExtensionName()),$types))
{
$message=$this->wrongType!==null?$this->wrongType : Yii::t('yii','The file "{file}" cannot be uploaded. Only files with these extensions are allowed: {extensions}.');
$this->addError($object,$attribute,$message,array('{file}'=>$file->getName(), '{extensions}'=>implode(', ',$types)));
}
}
if($this->mimeTypes!==null)
{
if(function_exists('finfo_open'))
{
$mimeType=false;
if($info=finfo_open(defined('FILEINFO_MIME_TYPE') ? FILEINFO_MIME_TYPE : FILEINFO_MIME))
$mimeType=finfo_file($info,$file->getTempName());
}
else if(function_exists('mime_content_type'))
$mimeType=mime_content_type($file->getTempName());
else
throw new CException(Yii::t('yii','In order to use MIME-type validation provided by CFileValidator fileinfo PECL extension should be installed.'));
if(is_string($this->mimeTypes))
$mimeTypes=preg_split('/[\s,]+/',strtolower($this->mimeTypes),-1,PREG_SPLIT_NO_EMPTY);
else
$mimeTypes=$this->mimeTypes;
if($mimeType===false || !in_array(strtolower($mimeType),$mimeTypes))
{
$message=$this->wrongMimeType!==null?$this->wrongMimeType : Yii::t('yii','The file "{file}" cannot be uploaded. Only files of these MIME-types are allowed: {mimeTypes}.');
$this->addError($object,$attribute,$message,array('{file}'=>$file->getName(), '{mimeTypes}'=>implode(', ',$mimeTypes)));
}
}
}
protected function emptyAttribute($object, $attribute)
{
if(!$this->allowEmpty)
{
$message=$this->message!==null?$this->message : Yii::t('yii','{attribute} cannot be blank.');
$this->addError($object,$attribute,$message);
}
}
protected function getSizeLimit()
{
$limit=ini_get('upload_max_filesize');
$limit=$this->sizeToBytes($limit);
if($this->maxSize!==null && $limit>0 && $this->maxSize<$limit)
$limit=$this->maxSize;
if(isset($_POST['MAX_FILE_SIZE']) && $_POST['MAX_FILE_SIZE']>0 && $_POST['MAX_FILE_SIZE']<$limit)
$limit=$_POST['MAX_FILE_SIZE'];
return $limit;
}
public function sizeToBytes($sizeStr)
{
switch (strtolower(substr($sizeStr,-1)))
{
case 'm': return (int)$sizeStr*1048576;//1024*1024
case 'k': return (int)$sizeStr*1024;//1024
case 'g': return (int)$sizeStr*1073741824;//1024*1024*1024
default: return (int)$sizeStr;//do nothing
}
}
}