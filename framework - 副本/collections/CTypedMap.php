<?php
class CTypedMap extends CMap
{
private $_type;
public function __construct($type)
{
$this->_type=$type;
}
public function add($index,$item)
{
if($item instanceof $this->_type)
parent::add($index,$item);
else
throw new CException(Yii::t('yii','CTypedMap<{type}> can only hold objects of {type} class.',
array('{type}'=>$this->_type)));
}
}
