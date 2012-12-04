<?php
class CLogRouter extends CApplicationComponent
{
private $_routes=array();
public function init()
{
parent::init();
foreach($this->_routes as $name=>$route)
{
$route=Yii::createComponent($route);
$route->init();
$this->_routes[$name]=$route;
}
Yii::getLogger()->attachEventHandler('onFlush',array($this,'collectLogs'));
Yii::app()->attachEventHandler('onEndRequest',array($this,'processLogs'));
}
public function getRoutes()
{
return new CMap($this->_routes);
}
public function setRoutes($config)
{
foreach($config as $name=>$route)
$this->_routes[$name]=$route;
}
public function collectLogs($event)
{
$logger=Yii::getLogger();
$dumpLogs=isset($event->params['dumpLogs']) && $event->params['dumpLogs'];
foreach($this->_routes as $route)
{
if($route->enabled)
$route->collectLogs($logger,$dumpLogs);
}
}
public function processLogs($event)
{
$logger=Yii::getLogger();
foreach($this->_routes as $route)
{
if($route->enabled)
$route->collectLogs($logger,true);
}
}
}
