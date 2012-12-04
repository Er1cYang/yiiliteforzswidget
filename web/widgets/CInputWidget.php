<?php
abstract class CInputWidget extends CWidget
{
public $model;
public $attribute;
public $name;
public $value;
public $htmlOptions=array();
protected function resolveNameID()
{
if($this->name!==null)
$name=$this->name;
else if(isset($this->htmlOptions['name']))
$name=$this->htmlOptions['name'];
else if($this->hasModel())
$name=CHtml::activeName($this->model,$this->attribute);
else
throw new CException(Yii::t('yii','{class} must specify "model" and "attribute" or "name" property values.',array('{class}'=>get_class($this))));
if(($id=$this->getId(false))===null)
{
if(isset($this->htmlOptions['id']))
$id=$this->htmlOptions['id'];
else
$id=CHtml::getIdByName($name);
}
return array($name,$id);
}
protected function hasModel()
{
return $this->model instanceof CModel && $this->attribute!==null;
}
}