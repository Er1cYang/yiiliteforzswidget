<?php
class CLogFilter extends CComponent implements ILogFilter
{
public $prefixSession=false;
public $prefixUser=false;
public $logUser=true;
public $logVars=array('_GET','_POST','_FILES','_COOKIE','_SESSION','_SERVER');
public function filter(&$logs)
{
if (!empty($logs))
{
if(($message=$this->getContext())!=='')
array_unshift($logs,array($message,CLogger::LEVEL_INFO,'application',YII_BEGIN_TIME));
$this->format($logs);
}
return $logs;
}
protected function format(&$logs)
{
$prefix='';
if($this->prefixSession && ($id=session_id())!=='')
$prefix.="[$id]";
if($this->prefixUser && ($user=Yii::app()->getComponent('user',false))!==null)
$prefix.='['.$user->getName().']['.$user->getId().']';
if($prefix!=='')
{
foreach($logs as &$log)
$log[0]=$prefix.' '.$log[0];
}
}
protected function getContext()
{
$context=array();
if($this->logUser && ($user=Yii::app()->getComponent('user',false))!==null)
$context[]='User: '.$user->getName().' (ID: '.$user->getId().')';
foreach($this->logVars as $name)
{
if(!empty($GLOBALS[$name]))
$context[]="\${$name}=".var_export($GLOBALS[$name],true);
}
return implode("\n\n",$context);
}
}