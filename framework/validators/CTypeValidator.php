<?php
class CTypeValidator extends CValidator
{
public $type='string';
public $dateFormat='MM/dd/yyyy';
public $timeFormat='hh:mm';
public $datetimeFormat='MM/dd/yyyy hh:mm';
public $allowEmpty=true;
protected function validateAttribute($object,$attribute)
{
$value=$object->$attribute;
if($this->allowEmpty && $this->isEmpty($value))
return;
if($this->type==='integer')
$valid=preg_match('/^[-+]?[0-9]+$/',trim($value));
else if($this->type==='float')
$valid=preg_match('/^[-+]?([0-9]*\.)?[0-9]+([eE][-+]?[0-9]+)?$/',trim($value));
else if($this->type==='date')
$valid=CDateTimeParser::parse($value,$this->dateFormat,array('month'=>1,'day'=>1,'hour'=>0,'minute'=>0,'second'=>0))!==false;
else if($this->type==='time')
$valid=CDateTimeParser::parse($value,$this->timeFormat)!==false;
else if($this->type==='datetime')
$valid=CDateTimeParser::parse($value,$this->datetimeFormat, array('month'=>1,'day'=>1,'hour'=>0,'minute'=>0,'second'=>0))!==false;
else if($this->type==='array')
$valid=is_array($value);
else
return;
if(!$valid)
{
$message=$this->message!==null?$this->message : Yii::t('yii','{attribute} must be {type}.');
$this->addError($object,$attribute,$message,array('{type}'=>$this->type));
}
}
}
