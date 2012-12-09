<?php
class CUrlValidator extends CValidator
{
public $pattern='/^{schemes}:\/\/(([A-Z0-9][A-Z0-9_-]*)(\.[A-Z0-9][A-Z0-9_-]*)+)/i';
public $validSchemes=array('http','https');
public $defaultScheme;
public $allowEmpty=true;
protected function validateAttribute($object,$attribute)
{
$value=$object->$attribute;
if($this->allowEmpty && $this->isEmpty($value))
return;
if(($value=$this->validateValue($value))!==false)
$object->$attribute=$value;
else
{
$message=$this->message!==null?$this->message:Yii::t('yii','{attribute} is not a valid URL.');
$this->addError($object,$attribute,$message);
}
}
public function validateValue($value)
{
if(is_string($value) && strlen($value)<2000)//make sure the length is limited to avoid DOS attacks
{
if($this->defaultScheme!==null && strpos($value,'://')===false)
$value=$this->defaultScheme.'://'.$value;
if(strpos($this->pattern,'{schemes}')!==false)
$pattern=str_replace('{schemes}','('.implode('|',$this->validSchemes).')',$this->pattern);
else
$pattern=$this->pattern;
if(preg_match($pattern,$value))
return $value;
}
return false;
}
public function clientValidateAttribute($object,$attribute)
{
$message=$this->message!==null ? $this->message : Yii::t('yii','{attribute} is not a valid URL.');
$message=strtr($message, array(
'{attribute}'=>$object->getAttributeLabel($attribute),
));
if(strpos($this->pattern,'{schemes}')!==false)
$pattern=str_replace('{schemes}','('.implode('|',$this->validSchemes).')',$this->pattern);
else
$pattern=$this->pattern;
$js="
if(!value.match($pattern)) {
messages.push(".CJSON::encode($message).");
}
";
if($this->defaultScheme!==null)
{
$js="
if(!value.match(/:\\/\\//)) {
value=".CJSON::encode($this->defaultScheme)."+'://'+value;
}
$js
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
