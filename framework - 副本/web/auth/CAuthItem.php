<?php
class CAuthItem extends CComponent
{
const TYPE_OPERATION=0;
const TYPE_TASK=1;
const TYPE_ROLE=2;
private $_auth;
private $_type;
private $_name;
private $_description;
private $_bizRule;
private $_data;
public function __construct($auth,$name,$type,$description='',$bizRule=null,$data=null)
{
$this->_type=(int)$type;
$this->_auth=$auth;
$this->_name=$name;
$this->_description=$description;
$this->_bizRule=$bizRule;
$this->_data=$data;
}
public function checkAccess($itemName,$params=array())
{
Yii::trace('Checking permission "'.$this->_name.'"','system.web.auth.CAuthItem');
if($this->_auth->executeBizRule($this->_bizRule,$params,$this->_data))
{
if($this->_name==$itemName)
return true;
foreach($this->_auth->getItemChildren($this->_name) as $item)
{
if($item->checkAccess($itemName,$params))
return true;
}
}
return false;
}
public function getAuthManager()
{
return $this->_auth;
}
public function getType()
{
return $this->_type;
}
public function getName()
{
return $this->_name;
}
public function setName($value)
{
if($this->_name!==$value)
{
$oldName=$this->_name;
$this->_name=$value;
$this->_auth->saveAuthItem($this,$oldName);
}
}
public function getDescription()
{
return $this->_description;
}
public function setDescription($value)
{
if($this->_description!==$value)
{
$this->_description=$value;
$this->_auth->saveAuthItem($this);
}
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
$this->_auth->saveAuthItem($this);
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
$this->_auth->saveAuthItem($this);
}
}
public function addChild($name)
{
return $this->_auth->addItemChild($this->_name,$name);
}
public function removeChild($name)
{
return $this->_auth->removeItemChild($this->_name,$name);
}
public function hasChild($name)
{
return $this->_auth->hasItemChild($this->_name,$name);
}
public function getChildren()
{
return $this->_auth->getItemChildren($this->_name);
}
public function assign($userId,$bizRule=null,$data=null)
{
return $this->_auth->assign($this->_name,$userId,$bizRule,$data);
}
public function revoke($userId)
{
return $this->_auth->revoke($this->_name,$userId);
}
public function isAssigned($userId)
{
return $this->_auth->isAssigned($this->_name,$userId);
}
public function getAssignment($userId)
{
return $this->_auth->getAuthAssignment($this->_name,$userId);
}
}
