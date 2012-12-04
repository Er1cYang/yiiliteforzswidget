<?php
class CDbTableSchema extends CComponent
{
public $name;
public $rawName;
public $primaryKey;
public $sequenceName;
public $foreignKeys=array();
public $columns=array();
public function getColumn($name)
{
return isset($this->columns[$name]) ? $this->columns[$name] : null;
}
public function getColumnNames()
{
return array_keys($this->columns);
}
}
