<?php
Yii::import('zii.widgets.jui.CJuiWidget');
class CJuiResizable extends CJuiWidget
{
public $tagName='div';
public function init()
{
parent::init();
$id=$this->getId();
if (isset($this->htmlOptions['id']))
$id = $this->htmlOptions['id'];
else
$this->htmlOptions['id']=$id;
$options=empty($this->options) ? '' : CJavaScript::encode($this->options);
Yii::app()->getClientScript()->registerScript(__CLASS__.'#'.$id,"jQuery('#{$id}').resizable($options);");
echo CHtml::openTag($this->tagName,$this->htmlOptions)."\n";
}
public function run(){
echo CHtml::closeTag($this->tagName);
}
}
