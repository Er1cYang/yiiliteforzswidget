<?php
class CInlineFilter extends CFilter
{
public $name;
public static function create($controller,$filterName)
{
if(method_exists($controller,'filter'.$filterName))
{
$filter=new CInlineFilter;
$filter->name=$filterName;
return $filter;
}
else
throw new CException(Yii::t('yii','Filter "{filter}" is invalid. Controller "{class}" does not have the filter method "filter{filter}".',
array('{filter}'=>$filterName, '{class}'=>get_class($controller))));
}
public function filter($filterChain)
{
$method='filter'.$this->name;
$filterChain->controller->$method($filterChain);
}
}
