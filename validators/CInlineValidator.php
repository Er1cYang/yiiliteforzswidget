<?php
class CInlineValidator extends CValidator
{
public $method;
public $params;
public $clientValidate;
protected function validateAttribute($object,$attribute)
{
$method=$this->method;
$object->$method($attribute,$this->params);
}
public function clientValidateAttribute($object,$attribute)
{
if($this->clientValidate!==null)
{
$method=$this->clientValidate;
return $object->$method($attribute);
}
}
}
