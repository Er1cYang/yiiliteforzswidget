<?php
class CDbColumnSchema extends CComponent
{
public $name;
public $rawName;
public $allowNull;
public $dbType;
public $type;
public $defaultValue;
public $size;
public $precision;
public $scale;
public $isPrimaryKey;
public $isForeignKey;
public $autoIncrement=false;
public function init($dbType, $defaultValue)
{
$this->dbType=$dbType;
$this->extractType($dbType);
$this->extractLimit($dbType);
if($defaultValue!==null)
$this->extractDefault($defaultValue);
}
protected function extractType($dbType)
{
if(stripos($dbType,'int')!==false && stripos($dbType,'unsigned int')===false)
$this->type='integer';
else if(stripos($dbType,'bool')!==false)
$this->type='boolean';
else if(preg_match('/(real|floa|doub)/i',$dbType))
$this->type='double';
else
$this->type='string';
}
protected function extractLimit($dbType)
{
if(strpos($dbType,'(') && preg_match('/\((.*)\)/',$dbType,$matches))
{
$values=explode(',',$matches[1]);
$this->size=$this->precision=(int)$values[0];
if(isset($values[1]))
$this->scale=(int)$values[1];
}
}
protected function extractDefault($defaultValue)
{
$this->defaultValue=$this->typecast($defaultValue);
}
public function typecast($value)
{
if(gettype($value)===$this->type || $value===null || $value instanceof CDbExpression)
return $value;
if($value==='' && $this->allowNull)
return $this->type==='string' ? '' : null;
switch($this->type)
{
case 'string': return (string)$value;
case 'integer': return (integer)$value;
case 'boolean': return (boolean)$value;
case 'double':
default: return $value;
}
}
}
