<?php
class CBehavior extends CComponent implements IBehavior
{
private $_enabled;
private $_owner;
public function events()
{
return array();
}
public function attach($owner)
{
$this->_owner=$owner;
foreach($this->events() as $event=>$handler)
$owner->attachEventHandler($event,array($this,$handler));
}
public function detach($owner)
{
foreach($this->events() as $event=>$handler)
$owner->detachEventHandler($event,array($this,$handler));
$this->_owner=null;
}
public function getOwner()
{
return $this->_owner;
}
public function getEnabled()
{
return $this->_enabled;
}
public function setEnabled($value)
{
if($this->_enabled!=$value && $this->_owner)
{
if($value)
{
foreach($this->events() as $event=>$handler)
$this->_owner->attachEventHandler($event,array($this,$handler));
}
else
{
foreach($this->events() as $event=>$handler)
$this->_owner->detachEventHandler($event,array($this,$handler));
}
}
$this->_enabled=$value;
}
}
