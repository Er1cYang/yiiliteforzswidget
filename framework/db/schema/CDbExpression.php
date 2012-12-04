<?php
class CDbExpression extends CComponent
{
public $expression;
public $params=array();
public function __construct($expression,$params=array())
{
$this->expression=$expression;
$this->params=$params;
}
public function __toString()
{
return $this->expression;
}
}