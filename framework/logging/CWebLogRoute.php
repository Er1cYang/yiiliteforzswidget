<?php
class CWebLogRoute extends CLogRoute
{
public $showInFireBug=false;
public $ignoreAjaxInFireBug=true;
public $ignoreFlashInFireBug=true;
public function processLogs($logs)
{
$this->render('log',$logs);
}
protected function render($view,$data)
{
$app=Yii::app();
$isAjax=$app->getRequest()->getIsAjaxRequest();
$isFlash=$app->getRequest()->getIsFlashRequest();
if($this->showInFireBug)
{
if($isAjax && $this->ignoreAjaxInFireBug || $isFlash && $this->ignoreFlashInFireBug)
return;
$view.='-firebug';
}
else if(!($app instanceof CWebApplication) || $isAjax || $isFlash)
return;
$viewFile=YII_PATH.DIRECTORY_SEPARATOR.'views'.DIRECTORY_SEPARATOR.$view.'.php';
include($app->findLocalizedFile($viewFile,'en'));
}
}