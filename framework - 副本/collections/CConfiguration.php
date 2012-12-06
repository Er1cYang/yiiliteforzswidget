<?php
class CConfiguration extends CMap
{
public function __construct($data=null)
{
if(is_string($data))
parent::__construct(require($data));
else
parent::__construct($data);
}
public function loadFromFile($configFile)
{
$data=require($configFile);
if($this->getCount()>0)
$this->mergeWith($data);
else
$this->copyFrom($data);
}
public function saveAsString()
{
return str_replace("\r",'',var_export($this->toArray(),true));
}
public function applyTo($object)
{
foreach($this->toArray() as $key=>$value)
$object->$key=$value;
}
}
