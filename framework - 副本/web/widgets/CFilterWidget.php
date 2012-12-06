<?php
class CFilterWidget extends CWidget implements IFilter
{
public $stopAction=false;
private $_isFilter;
public function __construct($owner=null)
{
parent::__construct($owner);
$this->_isFilter=($owner===null);
}
public function getIsFilter()
{
return $this->_isFilter;
}
public function filter($filterChain)
{
$this->init();
if(!$this->stopAction)
{
$filterChain->run();
$this->run();
}
}
}