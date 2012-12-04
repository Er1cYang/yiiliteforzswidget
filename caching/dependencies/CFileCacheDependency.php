<?php
class CFileCacheDependency extends CCacheDependency
{
public $fileName;
public function __construct($fileName=null)
{
$this->fileName=$fileName;
}
protected function generateDependentData()
{
if($this->fileName!==null)
return @filemtime($this->fileName);
else
throw new CException(Yii::t('yii','CFileCacheDependency.fileName cannot be empty.'));
}
}
