<?php
abstract class CViewRenderer extends CApplicationComponent implements IViewRenderer
{
public $useRuntimePath=true;
public $filePermission=0755;
public $fileExtension='.php';
abstract protected function generateViewFile($sourceFile,$viewFile);
public function renderFile($context,$sourceFile,$data,$return)
{
if(!is_file($sourceFile) || ($file=realpath($sourceFile))===false)
throw new CException(Yii::t('yii','View file "{file}" does not exist.',array('{file}'=>$sourceFile)));
$viewFile=$this->getViewFile($sourceFile);
if(@filemtime($sourceFile)>@filemtime($viewFile))
{
$this->generateViewFile($sourceFile,$viewFile);
@chmod($viewFile,$this->filePermission);
}
return $context->renderInternal($viewFile,$data,$return);
}
protected function getViewFile($file)
{
if($this->useRuntimePath)
{
$crc=sprintf('%x', crc32(get_class($this).Yii::getVersion().dirname($file)));
$viewFile=Yii::app()->getRuntimePath().DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.$crc.DIRECTORY_SEPARATOR.basename($file);
if(!is_file($viewFile))
@mkdir(dirname($viewFile),$this->filePermission,true);
return $viewFile;
}
else
return $file.'c';
}
}
