<?php
class CArrayDataProvider extends CDataProvider
{
public $keyField='id';
public $rawData=array();
public function __construct($rawData,$config=array())
{
$this->rawData=$rawData;
foreach($config as $key=>$value)
$this->$key=$value;
}
protected function fetchData()
{
if(($sort=$this->getSort())!==false && ($order=$sort->getOrderBy())!='')
$this->sortData($this->getSortDirections($order));
if(($pagination=$this->getPagination())!==false)
{
$pagination->setItemCount($this->getTotalItemCount());
return array_slice($this->rawData, $pagination->getOffset(), $pagination->getLimit());
}
else
return $this->rawData;
}
protected function fetchKeys()
{
if($this->keyField===false)
return array_keys($this->rawData);
$keys=array();
foreach($this->getData() as $i=>$data)
$keys[$i]=is_object($data) ? $data->{$this->keyField} : $data[$this->keyField];
return $keys;
}
protected function calculateTotalItemCount()
{
return count($this->rawData);
}
protected function sortData($directions)
{
if(empty($directions))
return;
$args=array();
$dummy=array();
foreach($directions as $name=>$descending)
{
$column=array();
$fields_array=preg_split('/\.+/',$name,-1,PREG_SPLIT_NO_EMPTY);
foreach($this->rawData as $index=>$data)
$column[$index]=$this->getSortingFieldValue($data, $fields_array);
$args[]=&$column;
$dummy[]=&$column;
unset($column);
$direction=$descending ? SORT_DESC : SORT_ASC;
$args[]=&$direction;
$dummy[]=&$direction;
unset($direction);
}
$args[]=&$this->rawData;
call_user_func_array('array_multisort', $args);
}
protected function getSortingFieldValue($data, $fields)
{
foreach ($fields as $field)
{
$data = is_object($data) ? $data->$field : $data[$field];
}
return $data;
}
protected function getSortDirections($order)
{
$segs=explode(',',$order);
$directions=array();
foreach($segs as $seg)
{
if(preg_match('/(.*?)(\s+(desc|asc))?$/i',trim($seg),$matches))
$directions[$matches[1]]=isset($matches[3]) && !strcasecmp($matches[3],'desc');
else
$directions[trim($seg)]=false;
}
return $directions;
}
}
