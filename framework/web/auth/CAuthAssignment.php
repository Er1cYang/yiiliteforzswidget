<?php
class CAuthAssignment extends CComponent
{
private $_auth;
private $_itemName;
private $_userId;
private $_bizRule;
private $_data;
public function __construct($auth,$itemName,$userId,$bizRule=null,$data=null)
{
$this->_auth=$auth;
$this->_itemName=$itemName;
$this->_userId=$userId;
$this->_bizRule=$bizRule;
$this->_data=$data;
}
public function getUserId()
{
return $this->_userId;
}
public function getItemName()
{
return $this->_itemName;
}
public function getBizRule()
{
return $this->_bizRule;
}
public function setBizRule($value)
{
if($this->_bizRule!==$value)
{
$this->_bizRule=$value;
$this->_auth->saveAuthAssignment($this);
}
}
public function getData()
{
return $this->_data;
}
public function setData($value)
{
if($this->_data!==$value)
{
$this->_data=$value;
$this->_auth->saveAuthAssignment($this);
}
}
}