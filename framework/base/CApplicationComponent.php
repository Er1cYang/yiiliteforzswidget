<?php
abstract class CApplicationComponent extends CComponent implements IApplicationComponent
{
public $behaviors=array();
private $_initialized=false;
public function init()
{
$this->attachBehaviors($this->behaviors);
$this->_initialized=true;
}
public function getIsInitialized()
{
return $this->_initialized;
}
}
