<?php
class CRangeValidator extends CValidator
{
public $range;
public $strict=false;
public $allowEmpty=true;
public $not=false;
protected function validateAttribute($object,$attribute)
{
$value=$object->$attribute;
if($this->allowEmpty && $this->isEmpty($value))
return;
if(!is_array($this->range))
throw new CException(Yii::t('yii','The "range" property must be specified with a list of values.'));
if(!$this->not && !in_array($value,$this->range,$this->strict))
{
$message=$this->message!==null?$this->message:Yii::t('yii','{attribute} is not in the list.');
$this->addError($object,$attribute,$message);
}
else if($this->not && in_array($value,$this->range,$this->strict))
{
$message=$this->message!==null?$this->message:Yii::t('yii','{attribute} is in the list.');
$this->addError($object,$attribute,$message);
}
}
public function clientValidateAttribute($object,$attribute)
{
if(!is_array($this->range))
throw new CException(Yii::t('yii','The "range" property must be specified with a list of values.'));
if(($message=$this->message)===null)
$message=$this->not ? Yii::t('yii','{attribute} is in the list.') : Yii::t('yii','{attribute} is not in the list.');
$message=strtr($message,array(
'{attribute}'=>$object->getAttributeLabel($attribute),
));
$range=array();
foreach($this->range as $value)
$range[]=(string)$value;
$range=CJSON::encode($range);
return "
if(".($this->allowEmpty ? "$.trim(value)!='' && " : '').($this->not ? "$.inArray(value, $range)>=0" : "$.inArray(value, $range)<0").") {
messages.push(".CJSON::encode($message).");
}
";
}
}