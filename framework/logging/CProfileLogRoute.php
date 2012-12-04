<?php
class CProfileLogRoute extends CWebLogRoute
{
public $groupByToken=true;
private $_report='summary';
public function init()
{
$this->levels=CLogger::LEVEL_PROFILE;
}
public function getReport()
{
return $this->_report;
}
public function setReport($value)
{
if($value==='summary' || $value==='callstack')
$this->_report=$value;
else
throw new CException(Yii::t('yii','CProfileLogRoute.report "{report}" is invalid. Valid values include "summary" and "callstack".',
array('{report}'=>$value)));
}
public function processLogs($logs)
{
$app=Yii::app();
if(!($app instanceof CWebApplication) || $app->getRequest()->getIsAjaxRequest())
return;
if($this->getReport()==='summary')
$this->displaySummary($logs);
else
$this->displayCallstack($logs);
}
protected function displayCallstack($logs)
{
$stack=array();
$results=array();
$n=0;
foreach($logs as $log)
{
if($log[1]!==CLogger::LEVEL_PROFILE)
continue;
$message=$log[0];
if(!strncasecmp($message,'begin:',6))
{
$log[0]=substr($message,6);
$log[4]=$n;
$stack[]=$log;
$n++;
}
else if(!strncasecmp($message,'end:',4))
{
$token=substr($message,4);
if(($last=array_pop($stack))!==null && $last[0]===$token)
{
$delta=$log[3]-$last[3];
$results[$last[4]]=array($token,$delta,count($stack));
}
else
throw new CException(Yii::t('yii','CProfileLogRoute found a mismatching code block "{token}". Make sure the calls to Yii::beginProfile() and Yii::endProfile() be properly nested.',
array('{token}'=>$token)));
}
}
$now=microtime(true);
while(($last=array_pop($stack))!==null)
$results[$last[4]]=array($last[0],$now-$last[3],count($stack));
ksort($results);
$this->render('profile-callstack',$results);
}
protected function displaySummary($logs)
{
$stack=array();
foreach($logs as $log)
{
if($log[1]!==CLogger::LEVEL_PROFILE)
continue;
$message=$log[0];
if(!strncasecmp($message,'begin:',6))
{
$log[0]=substr($message,6);
$stack[]=$log;
}
else if(!strncasecmp($message,'end:',4))
{
$token=substr($message,4);
if(($last=array_pop($stack))!==null && $last[0]===$token)
{
$delta=$log[3]-$last[3];
if(!$this->groupByToken)
$token=$log[2];
if(isset($results[$token]))
$results[$token]=$this->aggregateResult($results[$token],$delta);
else
$results[$token]=array($token,1,$delta,$delta,$delta);
}
else
throw new CException(Yii::t('yii','CProfileLogRoute found a mismatching code block "{token}". Make sure the calls to Yii::beginProfile() and Yii::endProfile() be properly nested.',
array('{token}'=>$token)));
}
}
$now=microtime(true);
while(($last=array_pop($stack))!==null)
{
$delta=$now-$last[3];
$token=$this->groupByToken ? $last[0] : $last[2];
if(isset($results[$token]))
$results[$token]=$this->aggregateResult($results[$token],$delta);
else
$results[$token]=array($token,1,$delta,$delta,$delta);
}
$entries=array_values($results);
$func=create_function('$a,$b','return $a[4]<$b[4]?1:0;');
usort($entries,$func);
$this->render('profile-summary',$entries);
}
protected function aggregateResult($result,$delta)
{
list($token,$calls,$min,$max,$total)=$result;
if($delta<$min)
$min=$delta;
else if($delta>$max)
$max=$delta;
$calls++;
$total+=$delta;
return array($token,$calls,$min,$max,$total);
}
}