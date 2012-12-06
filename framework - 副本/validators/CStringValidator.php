<?php
class CStringValidator extends CValidator
{
public $max;
public $min;
public $is;
public $tooShort;
public $tooLong;
public $allowEmpty=true;
public $encoding;
protected function validateAttribute($object,$attribute)
{
$value=$object->$attribute;
if($this->allowEmpty && $this->isEmpty($value))
return;
if(function_exists('mb_strlen') && $this->encoding!==false)
$length=mb_strlen($value, $this->encoding ? $this->encoding : Yii::app()->charset);
else
$length=strlen($value);
if($this->min!==null && $length<$this->min)
{
$message=$this->tooShort!==null?$this->tooShort:Yii::t('yii','{attribute} is too short (minimum is {min} characters).');
$this->addError($object,$attribute,$message,array('{min}'=>$this->min));
}
if($this->max!==null && $length>$this->max)
{
$message=$this->tooLong!==null?$this->tooLong:Yii::t('yii','{attribute} is too long (maximum is {max} characters).');
$this->addError($object,$attribute,$message,array('{max}'=>$this->max));
}
if($this->is!==null && $length!==$this->is)
{
$message=$this->message!==null?$this->message:Yii::t('yii','{attribute} is of the wrong length (should be {length} characters).');
$this->addError($object,$attribute,$message,array('{length}'=>$this->is));
}
}
public function clientValidateAttribute($object,$attribute)
{
$label=$object->getAttributeLabel($attribute);
if(($message=$this->message)===null)
$message=Yii::t('yii','{attribute} is of the wrong length (should be {length} characters).');
$message=strtr($message, array(
'{attribute}'=>$label,
'{length}'=>$this->is,
));
if(($tooShort=$this->tooShort)===null)
$tooShort=Yii::t('yii','{attribute} is too short (minimum is {min} characters).');
$tooShort=strtr($tooShort, array(
'{attribute}'=>$label,
'{min}'=>$this->min,
));
if(($tooLong=$this->tooLong)===null)
$tooLong=Yii::t('yii','{attribute} is too long (maximum is {max} characters).');
$tooLong=strtr($tooLong, array(
'{attribute}'=>$label,
'{max}'=>$this->max,
));
$js='';
if($this->min!==null)
{
$js.="
if(value.length<{$this->min}) {
messages.push(".CJSON::encode($tooShort).");
}
";
}
if($this->max!==null)
{
$js.="
if(value.length>{$this->max}) {
messages.push(".CJSON::encode($tooLong).");
}
";
}
if($this->is!==null)
{
$js.="
if(value.length!={$this->is}) {
messages.push(".CJSON::encode($message).");
}
";
}
if($this->allowEmpty)
{
$js="
if($.trim(value)!='') {
$js
}
";
}
return $js;
}
}
