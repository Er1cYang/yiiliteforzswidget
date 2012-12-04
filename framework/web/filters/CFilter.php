<?php
class CFilter extends CComponent implements IFilter
{
public function filter($filterChain)
{
if($this->preFilter($filterChain))
{
$filterChain->run();
$this->postFilter($filterChain);
}
}
public function init()
{
}
protected function preFilter($filterChain)
{
return true;
}
protected function postFilter($filterChain)
{
}
}