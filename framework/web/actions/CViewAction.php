<?php
class CViewAction extends CAction
{
public $viewParam='view';
public $defaultView='index';
public $view;
public $basePath='pages';
public $layout;
public $renderAsText=false;
private $_viewPath;
public function getRequestedView()
{
if($this->_viewPath===null)
{
if(!empty($_GET[$this->viewParam]))
$this->_viewPath=$_GET[$this->viewParam];
else
$this->_viewPath=$this->defaultView;
}
return $this->_viewPath;
}
protected function resolveView($viewPath)
{
if(preg_match('/^\w[\w\.\-]*$/',$viewPath))
{
$view=strtr($viewPath,'.','/');
if(!empty($this->basePath))
$view=$this->basePath.'/'.$view;
if($this->getController()->getViewFile($view)!==false)
{
$this->view=$view;
return;
}
}
throw new CHttpException(404,Yii::t('yii','The requested view "{name}" was not found.',
array('{name}'=>$viewPath)));
}
public function run()
{
$this->resolveView($this->getRequestedView());
$controller=$this->getController();
if($this->layout!==null)
{
$layout=$controller->layout;
$controller->layout=$this->layout;
}
$this->onBeforeRender($event=new CEvent($this));
if(!$event->handled)
{
if($this->renderAsText)
{
$text=file_get_contents($controller->getViewFile($this->view));
$controller->renderText($text);
}
else
$controller->render($this->view);
$this->onAfterRender(new CEvent($this));
}
if($this->layout!==null)
$controller->layout=$layout;
}
public function onBeforeRender($event)
{
$this->raiseEvent('onBeforeRender',$event);
}
public function onAfterRender($event)
{
$this->raiseEvent('onAfterRender',$event);
}
}