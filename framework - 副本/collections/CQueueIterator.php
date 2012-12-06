<?php
class CQueueIterator implements Iterator
{
private $_d;
private $_i;
private $_c;
public function __construct(&$data)
{
$this->_d=&$data;
$this->_i=0;
$this->_c=count($this->_d);
}
public function rewind()
{
$this->_i=0;
}
public function key()
{
return $this->_i;
}
public function current()
{
return $this->_d[$this->_i];
}
public function next()
{
$this->_i++;
}
public function valid()
{
return $this->_i<$this->_c;
}
}
