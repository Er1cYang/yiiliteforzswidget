<?php
class CRegularExpressionValidator extends CValidator
{
public $pattern;
public $allowEmpty=true;
public $not=false;
protected function validateAttribute($object,$attribute)
{
$value=$object->$attribute;
if($this->allowEmpty && $this->isEmpty($value))
return;
if($this->pattern===null)
throw new CException(Yii::t('yii','The "pattern" property must be specified with a valid regular expression.'));
if((!$this->not && !preg_match($this->pattern,$value)) || ($this->not && preg_match($this->pattern,$value)))
{
$message=$this->message!==null?$this->message:Yii::t('yii','{attribute} is invalid.');
$this->addError($object,$attribute,$message);
}
}
public function clientValidateAttribute($object,$attribute)
{
if($this->pattern===null)
throw new CException(Yii::t('yii','The "pattern" property must be specified with a valid regular expression.'));
$message=$this->message!==null ? $this->message : Yii::t('yii','{attribute} is invalid.');
$message=strtr($message, array(
'{attribute}'=>$object->getAttributeLabel($attribute),
));
$pattern=$this->pattern;
$pattern=preg_replace('/\\\\x\{?([0-9a-fA-F]+)\}?/', '\u$1', $pattern);
$delim=substr($pattern, 0, 1);
$endpos=strrpos($pattern, $delim, 1);
$flag=substr($pattern, $endpos + 1);
if ($delim!=='/')
$pattern='/' . str_replace('/', '\\/', substr($pattern, 1, $endpos - 1)) . '/';
else
$pattern = substr($pattern, 0, $endpos + 1);
if (!empty($flag))
$pattern .= preg_replace('/[^igm]/', '', $flag);
return "
if(".($this->allowEmpty ? "$.trim(value)!='' && " : '').($this->not ? '' : '!')."value.match($pattern)) {
messages.push(".CJSON::encode($message).");
}
";
}
}