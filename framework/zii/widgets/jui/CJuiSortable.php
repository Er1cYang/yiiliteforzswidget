<?php
Yii::import('zii.widgets.jui.CJuiWidget');
class CJuiSortable extends CJuiWidget
{
public $items=array();
public $tagName='ul';
public $itemTemplate='<li id="{id}">{content}</li>';
public function run()
{
$id=$this->getId();
if (isset($this->htmlOptions['id']))
$id = $this->htmlOptions['id'];
else
$this->htmlOptions['id']=$id;
$options=empty($this->options) ? '' : CJavaScript::encode($this->options);
Yii::app()->getClientScript()->registerScript(__CLASS__.'#'.$id,"jQuery('#{$id}').sortable({$options});");
echo CHtml::openTag($this->tagName,$this->htmlOptions)."\n";
foreach($this->items as $id=>$content)
{
echo strtr($this->itemTemplate,array('{id}'=>$id,'{content}'=>$content))."\n";
}
echo CHtml::closeTag($this->tagName);
}
}
