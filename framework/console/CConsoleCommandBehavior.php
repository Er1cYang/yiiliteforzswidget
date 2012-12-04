<?php
class CConsoleCommandBehavior extends CBehavior
{
public function events()
{
return array(
'onBeforeAction'=>'beforeAction',
'onAfterAction'=>'afterAction'
);
}
protected function beforeAction($event)
{
}
protected function afterAction($event)
{
}
}