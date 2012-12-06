<?php
class COutputProcessor extends CFilterWidget
{
public function init()
{
ob_start();
ob_implicit_flush(false);
}
public function run()
{
$output=ob_get_clean();
$this->processOutput($output);
}
public function processOutput($output)
{
if($this->hasEventHandler('onProcessOutput'))
{
$event=new COutputEvent($this,$output);
$this->onProcessOutput($event);
if(!$event->handled)
echo $output;
}
else
echo $output;
}
public function onProcessOutput($event)
{
$this->raiseEvent('onProcessOutput',$event);
}
}
