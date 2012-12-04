<?php
abstract class CLogRoute extends CComponent
{
public $enabled=true;
public $levels='';
public $categories='';
public $filter;
public $logs=array();
public function init()
{
}
protected function formatLogMessage($message,$level,$category,$time)
{
return @date('Y/m/d H:i:s',$time)." [$level] [$category] $message\n";
}
public function collectLogs($logger, $processLogs=false)
{
$logs=$logger->getLogs($this->levels,$this->categories);
$this->logs=empty($this->logs) ? $logs : array_merge($this->logs,$logs);
if($processLogs && !empty($this->logs))
{
if($this->filter!==null)
Yii::createComponent($this->filter)->filter($this->logs);
if($this->logs!==array())
$this->processLogs($this->logs);
$this->logs=array();
}
}
abstract protected function processLogs($logs);
}
