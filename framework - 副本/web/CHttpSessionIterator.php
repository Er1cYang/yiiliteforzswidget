<?php
class CHttpSessionIterator implements Iterator
{
private $_keys;
private $_key;
public function __construct()
{
$this->_keys=array_keys($_SESSION);
}
public function rewind()
{
$this->_key=reset($this->_keys);
}
public function key()
{
return $this->_key;
}
public function current()
{
return isset($_SESSION[$this->_key])?$_SESSION[$this->_key]:null;
}
public function next()
{
do
{
$this->_key=next($this->_keys);
}
while(!isset($_SESSION[$this->_key]) && $this->_key!==false);
}
public function valid()
{
return $this->_key!==false;
}
}
