<?php
class CRequiredValidator extends CValidator
{
public $requiredValue;
public $strict=false;
protected function validateAttribute($object,$attribute)
{
$value=$object->$attribute;
if($this->requiredValue!==null)
{
if(!$this->strict && $value!=$this->requiredValue || $this->strict && $value!==$this->requiredValue)
{
$message=$this->message!==null?$this->message:Yii::t('yii','{attribute} must be {value}.',
array('{value}'=>$this->requiredValue));
$this->addError($object,$attribute,$message);
}
}
else if($this->isEmpty($value,true))
{
$message=$this->message!==null?$this->message:Yii::t('yii','{attribute} cannot be blank.');
$this->addError($object,$attribute,$message);
}
}
public function clientValidateAttribute($object,$attribute)
{
$message=$this->message;
if($this->requiredValue!==null)
{
if($message===null)
$message=Yii::t('yii','{attribute} must be {value}.');
$message=strtr($message, array(
'{value}'=>$this->requiredValue,
'{attribute}'=>$object->getAttributeLabel($attribute),
));
return "
if(value!=" . CJSON::encode($this->requiredValue) . ") {
messages.push(".CJSON::encode($message).");
}
";
}
else
{
if($message===null)
$message=Yii::t('yii','{attribute} cannot be blank.');
$message=strtr($message, array(
'{attribute}'=>$object->getAttributeLabel($attribute),
));
return "
if($.trim(value)=='') {
messages.push(".CJSON::encode($message).");
}
";
}
}
}
