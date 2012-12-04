<?php
class CGlobalStateCacheDependency extends CCacheDependency
{
public $stateName;
public function __construct($name=null)
{
$this->stateName=$name;
}
protected function generateDependentData()
{
if($this->stateName!==null)
return Yii::app()->getGlobalState($this->stateName);
else
throw new CException(Yii::t('yii','CGlobalStateCacheDependency.stateName cannot be empty.'));
}
}
