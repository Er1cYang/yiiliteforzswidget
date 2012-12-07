<?php
Yii::import('zii.widgets.jui.CJuiWidget');
class CJuiAccordion extends CJuiWidget
{
public $panels=array();
public $tagName='div';
public $headerTemplate='<h3><a href="#">{title}</a></h3>';
public $contentTemplate='<div>{content}</div>';
public function run()
{
$id=$this->getId();
if (isset($this->htmlOptions['id']))
$id = $this->htmlOptions['id'];
else
$this->htmlOptions['id']=$id;
echo CHtml::openTag($this->tagName,$this->htmlOptions)."\n";
foreach($this->panels as $title=>$content)
{
echo strtr($this->headerTemplate,array('{title}'=>$title))."\n";
echo strtr($this->contentTemplate,array('{content}'=>$content))."\n";
}
echo CHtml::closeTag($this->tagName);
$options=empty($this->options) ? '' : CJavaScript::encode($this->options);
Yii::app()->getClientScript()->registerScript(__CLASS__.'#'.$id,"jQuery('#{$id}').accordion($options);");
}
}
