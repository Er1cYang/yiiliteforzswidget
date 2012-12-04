<?php
class CMysqlColumnSchema extends CDbColumnSchema
{
protected function extractType($dbType)
{
if(strncmp($dbType,'enum',4)===0)
$this->type='string';
else if(strpos($dbType,'float')!==false || strpos($dbType,'double')!==false)
$this->type='double';
else if(strpos($dbType,'bool')!==false)
$this->type='boolean';
else if(strpos($dbType,'int')===0 && strpos($dbType,'unsigned')===false || preg_match('/(bit|tinyint|smallint|mediumint)/',$dbType))
$this->type='integer';
else
$this->type='string';
}
protected function extractDefault($defaultValue)
{
if($this->dbType==='timestamp' && $defaultValue==='CURRENT_TIMESTAMP')
$this->defaultValue=null;
else
parent::extractDefault($defaultValue);
}
protected function extractLimit($dbType)
{
if (strncmp($dbType, 'enum', 4)===0 && preg_match('/\((.*)\)/',$dbType,$matches))
{
$values = explode(',', $matches[1]);
$size = 0;
foreach($values as $value)
{
if(($n=strlen($value)) > $size)
$size=$n;
}
$this->size = $this->precision = $size-2;
}
else
parent::extractLimit($dbType);
}
}