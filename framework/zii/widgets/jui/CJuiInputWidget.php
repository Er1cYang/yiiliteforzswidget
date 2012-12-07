<?php
Yii::import('zii.widgets.jui.CJuiWidget');
abstract class CJuiInputWidget extends CJuiWidget
{
public $model;
public $attribute;
public $name;
public $value;
protected function resolveNameID()
{
if($this->name!==null)
$name=$this->name;
else if(isset($this->htmlOptions['name']))
$name=$this->htmlOptions['name'];
else if($this->hasModel())
$name=CHtml::activeName($this->model,$this->attribute);
else
throw new CException(Yii::t('zii','{class} must specify "model" and "attribute" or "name" property values.',array('{class}'=>get_class($this))));
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
