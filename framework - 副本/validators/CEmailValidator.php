<?php
class CEmailValidator extends CValidator
{
public $pattern='/^[a-zA-Z0-9!#$%&\'*+\\/=?^_`{|}~-]+(?:\.[a-zA-Z0-9!#$%&\'*+\\/=?^_`{|}~-]+)*@(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?\.)+[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?$/';
public $fullPattern='/^[^@]*<[a-zA-Z0-9!#$%&\'*+\\/=?^_`{|}~-]+(?:\.[a-zA-Z0-9!#$%&\'*+\\/=?^_`{|}~-]+)*@(?:[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?\.)+[a-zA-Z0-9](?:[a-zA-Z0-9-]*[a-zA-Z0-9])?>$/';
public $allowName=false;
public $checkMX=false;
public $checkPort=false;
public $allowEmpty=true;
protected function validateAttribute($object,$attribute)
{
$value=$object->$attribute;
if($this->allowEmpty && $this->isEmpty($value))
return;
if(!$this->validateValue($value))
{
$message=$this->message!==null?$this->message:Yii::t('yii','{attribute} is not a valid email address.');
$this->addError($object,$attribute,$message);
}
}
public function validateValue($value)
{
$valid=is_string($value) && strlen($value)<=254 && (preg_match($this->pattern,$value) || $this->allowName && preg_match($this->fullPattern,$value));
if($valid)
$domain=rtrim(substr($value,strpos($value,'@')+1),'>');
if($valid && $this->checkMX && function_exists('checkdnsrr'))
$valid=checkdnsrr($domain,'MX');
if($valid && $this->checkPort && function_exists('fsockopen') && function_exists('dns_get_record'))
$valid=$this->checkMxPorts($domain);
return $valid;
}
public function clientValidateAttribute($object,$attribute)
{
$message=$this->message!==null ? $this->message : Yii::t('yii','{attribute} is not a valid email address.');
$message=strtr($message, array(
'{attribute}'=>$object->getAttributeLabel($attribute),
));
$condition="!value.match({$this->pattern})";
if($this->allowName)
$condition.=" && !value.match({$this->fullPattern})";
return "
if(".($this->allowEmpty ? "$.trim(value)!='' && " : '').$condition.") {
messages.push(".CJSON::encode($message).");
}
";
}
protected function checkMxPorts($domain)
{
$records=dns_get_record($domain, DNS_MX);
if($records===false || empty($records))
return false;
usort($records,array($this,'mxSort'));
foreach($records as $record)
{
$handle=fsockopen($record['target'],25);
if($handle!==false)
{
fclose($handle);
return true;
}
}
return false;
}
protected function mxSort($a, $b)
{
if($a['pri']==$b['pri'])
return 0;
return ($a['pri']<$b['pri'])?-1:1;
}
}
