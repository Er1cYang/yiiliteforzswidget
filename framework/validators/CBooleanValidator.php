<?php
class CBooleanValidator extends CValidator
{
public $trueValue='1';
public $falseValue='0';
public $strict=false;
public $allowEmpty=true;
protected function validateAttribute($object,$attribute)
{
$value=$object->$attribute;
if($this->allowEmpty && $this->isEmpty($value))
return;
if(!$this->strict && $value!=$this->trueValue && $value!=$this->falseValue
|| $this->strict && $value!==$this->trueValue && $value!==$this->falseValue)
{
$message=$this->message!==null?$this->message:Yii::t('yii','{attribute} must be either {true} or {false}.');
$this->addError($object,$attribute,$message,array(
'{true}'=>$this->trueValue,
'{false}'=>$this->falseValue,
));
}
}
public function clientValidateAttribute($object,$attribute)
{
$message=$this->message!==null ? $this->message : Yii::t('yii','{attribute} must be either {true} or {false}.');
$message=strtr($message, array(
'{attribute}'=>$object->getAttributeLabel($attribute),
'{true}'=>$this->trueValue,
'{false}'=>$this->falseValue,
));
return "
if(".($this->allowEmpty ? "$.trim(value)!='' && " : '')."value!=".CJSON::encode($this->trueValue)." && value!=".CJSON::encode($this->falseValue).") {
messages.push(".CJSON::encode($message).");
}
";
}
}
