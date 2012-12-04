<?php
abstract class CBaseController extends CComponent
{
private $_widgetStack=array();
abstract public function getViewFile($viewName);
public function renderFile($viewFile,$data=null,$return=false)
{
$widgetCount=count($this->_widgetStack);
if(($renderer=Yii::app()->getViewRenderer())!==null && $renderer->fileExtension==='.'.CFileHelper::getExtension($viewFile))
$content=$renderer->renderFile($this,$viewFile,$data,$return);
else
$content=$this->renderInternal($viewFile,$data,$return);
if(count($this->_widgetStack)===$widgetCount)
return $content;
else
{
$widget=end($this->_widgetStack);
throw new CException(Yii::t('yii','{controller} contains improperly nested widget tags in its view "{view}". A {widget} widget does not have an endWidget() call.',
array('{controller}'=>get_class($this), '{view}'=>$viewFile, '{widget}'=>get_class($widget))));
}
}
public function renderInternal($_viewFile_,$_data_=null,$_return_=false)
{
if(is_array($_data_))
extract($_data_,EXTR_PREFIX_SAME,'data');
else
$data=$_data_;
if($_return_)
{
ob_start();
ob_implicit_flush(false);
require($_viewFile_);
return ob_get_clean();
}
else
require($_viewFile_);
}
public function createWidget($className,$properties=array())
{
$widget=Yii::app()->getWidgetFactory()->createWidget($this,$className,$properties);
$widget->init();
return $widget;
}
public function widget($className,$properties=array(),$captureOutput=false)
{
if($captureOutput)
{
ob_start();
ob_implicit_flush(false);
$widget=$this->createWidget($className,$properties);
$widget->run();
return ob_get_clean();
}
else
{
$widget=$this->createWidget($className,$properties);
$widget->run();
return $widget;
}
}
public function beginWidget($className,$properties=array())
{
$widget=$this->createWidget($className,$properties);
$this->_widgetStack[]=$widget;
return $widget;
}
public function endWidget($id='')
{
if(($widget=array_pop($this->_widgetStack))!==null)
{
$widget->run();
return $widget;
}
else
throw new CException(Yii::t('yii','{controller} has an extra endWidget({id}) call in its view.',
array('{controller}'=>get_class($this),'{id}'=>$id)));
}
public function beginClip($id,$properties=array())
{
$properties['id']=$id;
$this->beginWidget('CClipWidget',$properties);
}
public function endClip()
{
$this->endWidget('CClipWidget');
}
public function beginCache($id,$properties=array())
{
$properties['id']=$id;
$cache=$this->beginWidget('COutputCache',$properties);
if($cache->getIsContentCached())
{
$this->endCache();
return false;
}
else
return true;
}
public function endCache()
{
$this->endWidget('COutputCache');
}
public function beginContent($view=null,$data=array())
{
$this->beginWidget('CContentDecorator',array('view'=>$view, 'data'=>$data));
}
public function endContent()
{
$this->endWidget('CContentDecorator');
}
}
