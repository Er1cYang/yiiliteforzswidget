<?php
class CTypedList extends CList
{
private $_type;
public function __construct($type)
{
$this->_type=$type;
}
public function insertAt($index,$item)
{
if($item instanceof $this->_type)
parent::insertAt($index,$item);
else
throw new CException(Yii::t('yii','CTypedList<{type}> can only hold objects of {type} class.',
array('{type}'=>$this->_type)));
}
}
