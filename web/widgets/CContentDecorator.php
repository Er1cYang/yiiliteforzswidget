<?php
class CContentDecorator extends COutputProcessor
{
public $view;
public $data=array();
public function processOutput($output)
{
$output=$this->decorate($output);
parent::processOutput($output);
}
protected function decorate($content)
{
$owner=$this->getOwner();
if($this->view===null)
$viewFile=Yii::app()->getController()->getLayoutFile(null);
else
$viewFile=$owner->getViewFile($this->view);
if($viewFile!==false)
{
$data=$this->data;
$data['content']=$content;
return $owner->renderFile($viewFile,$data,true);
}
else
return $content;
}
}
