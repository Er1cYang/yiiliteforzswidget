<?php
class CDefaultValueValidator extends CValidator
{
public $value;
public $setOnEmpty=true;
protected function validateAttribute($object,$attribute)
{
if(!$this->setOnEmpty)
$object->$attribute=$this->value;
else
{
$value=$object->$attribute;
if($value===null || $value==='')
$object->$attribute=$this->value;
}
}
}
