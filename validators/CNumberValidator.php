<?php
class CNumberValidator extends CValidator
{
public $integerOnly=false;
public $allowEmpty=true;
public $max;
public $min;
public $tooBig;
public $tooSmall;
public $integerPattern='/^\s*[+-]?\d+\s*$/';
public $numberPattern='/^\s*[-+]?[0-9]*\.?[0-9]+([eE][-+]?[0-9]+)?\s*$/';
protected function validateAttribute($object,$attribute)
{
$value=$object->$attribute;
if($this->allowEmpty && $this->isEmpty($value))
return;
if($this->integerOnly)
{
if(!preg_match($this->integerPattern,"$value"))
{
$message=$this->message!==null?$this->message:Yii::t('yii','{attribute} must be an integer.');
$this->addError($object,$attribute,$message);
}
}
else
{
if(!preg_match($this->numberPattern,"$value"))
{
$message=$this->message!==null?$this->message:Yii::t('yii','{attribute} must be a number.');
$this->addError($object,$attribute,$message);
}
}
if($this->min!==null && $value<$this->min)
{
$message=$this->tooSmall!==null?$this->tooSmall:Yii::t('yii','{attribute} is too small (minimum is {min}).');
$this->addError($object,$attribute,$message,array('{min}'=>$this->min));
}
if($this->max!==null && $value>$this->max)
{
$message=$this->tooBig!==null?$this->tooBig:Yii::t('yii','{attribute} is too big (maximum is {max}).');
$this->addError($object,$attribute,$message,array('{max}'=>$this->max));
}
}
public function clientValidateAttribute($object,$attribute)
{
$label=$object->getAttributeLabel($attribute);
if(($message=$this->message)===null)
$message=$this->integerOnly ? Yii::t('yii','{attribute} must be an integer.') : Yii::t('yii','{attribute} must be a number.');
$message=strtr($message, array(
'{attribute}'=>$label,
));
if(($tooBig=$this->tooBig)===null)
$tooBig=Yii::t('yii','{attribute} is too big (maximum is {max}).');
$tooBig=strtr($tooBig, array(
'{attribute}'=>$label,
'{max}'=>$this->max,
));
if(($tooSmall=$this->tooSmall)===null)
$tooSmall=Yii::t('yii','{attribute} is too small (minimum is {min}).');
$tooSmall=strtr($tooSmall, array(
'{attribute}'=>$label,
'{min}'=>$this->min,
));
$pattern=$this->integerOnly ? $this->integerPattern : $this->numberPattern;
$js="
if(!value.match($pattern)) {
messages.push(".CJSON::encode($message).");
}
";
if($this->min!==null)
{
$js.="
if(value<{$this->min}) {
messages.push(".CJSON::encode($tooSmall).");
}
";
}
if($this->max!==null)
{
$js.="
if(value>{$this->max}) {
messages.push(".CJSON::encode($tooBig).");
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
