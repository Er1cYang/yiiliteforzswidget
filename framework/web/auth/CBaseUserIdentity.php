<?php
abstract class CBaseUserIdentity extends CComponent implements IUserIdentity
{
const ERROR_NONE=0;
const ERROR_USERNAME_INVALID=1;
const ERROR_PASSWORD_INVALID=2;
const ERROR_UNKNOWN_IDENTITY=100;
public $errorCode=self::ERROR_UNKNOWN_IDENTITY;
public $errorMessage='';
private $_state=array();
public function getId()
{
return $this->getName();
}
public function getName()
{
return '';
}
public function getPersistentStates()
{
return $this->_state;
}
public function setPersistentStates($states)
{
$this->_state = $states;
}
public function getIsAuthenticated()
{
return $this->errorCode==self::ERROR_NONE;
}
public function getState($name,$defaultValue=null)
{
return isset($this->_state[$name])?$this->_state[$name]:$defaultValue;
}
public function setState($name,$value)
{
$this->_state[$name]=$value;
}
public function clearState($name)
{
unset($this->_state[$name]);
}
}
