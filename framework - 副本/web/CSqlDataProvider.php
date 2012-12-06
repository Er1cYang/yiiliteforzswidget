<?php
class CSqlDataProvider extends CDataProvider
{
public $db;
public $sql;
public $params=array();
public $keyField='id';
public function __construct($sql,$config=array())
{
$this->sql=$sql;
foreach($config as $key=>$value)
$this->$key=$value;
}
protected function fetchData()
{
$sql=$this->sql;
$db=$this->db===null ? Yii::app()->db : $this->db;
$db->active=true;
if(($sort=$this->getSort())!==false)
{
$order=$sort->getOrderBy();
if(!empty($order))
{
if(preg_match('/\s+order\s+by\s+[\w\s,]+$/i',$sql))
$sql.=', '.$order;
else
$sql.=' ORDER BY '.$order;
}
}
if(($pagination=$this->getPagination())!==false)
{
$pagination->setItemCount($this->getTotalItemCount());
$limit=$pagination->getLimit();
$offset=$pagination->getOffset();
$sql=$db->getCommandBuilder()->applyLimit($sql,$limit,$offset);
}
$command=$db->createCommand($sql);
foreach($this->params as $name=>$value)
$command->bindValue($name,$value);
return $command->queryAll();
}
protected function fetchKeys()
{
$keys=array();
foreach($this->getData() as $i=>$data)
$keys[$i]=$data[$this->keyField];
return $keys;
}
protected function calculateTotalItemCount()
{
return 0;
}
}
