<?php
class CQueue extends CComponent implements IteratorAggregate,Countable
{
private $_d=array();
private $_c=0;
public function __construct($data=null)
{
if($data!==null)
$this->copyFrom($data);
}
public function toArray()
{
return $this->_d;
}
public function copyFrom($data)
{
if(is_array($data) || ($data instanceof Traversable))
{
$this->clear();
foreach($data as $item)
{
$this->_d[]=$item;
++$this->_c;
}
}
else if($data!==null)
throw new CException(Yii::t('yii','Queue data must be an array or an object implementing Traversable.'));
}
public function clear()
{
$this->_c=0;
$this->_d=array();
}
public function contains($item)
{
return array_search($item,$this->_d,true)!==false;
}
public function peek()
{
if($this->_c===0)
throw new CException(Yii::t('yii','The queue is empty.'));
else
return $this->_d[0];
}
public function dequeue()
{
if($this->_c===0)
throw new CException(Yii::t('yii','The queue is empty.'));
else
{
--$this->_c;
return array_shift($this->_d);
}
}
public function enqueue($item)
{
++$this->_c;
$this->_d[]=$item;
}
public function getIterator()
{
return new CQueueIterator($this->_d);
}
public function getCount()
{
return $this->_c;
}
public function count()
{
return $this->getCount();
}
}
