<?php
Yii::import('zii.widgets.jui.CJuiWidget');
class CJuiProgressBar extends CJuiWidget
{
public $tagName = 'div';
public $value = 0;
public function run()
{
$id=$this->getId();
if (isset($this->htmlOptions['id']))
$id = $this->htmlOptions['id'];
else
$this->htmlOptions['id']=$id;
echo CHtml::openTag($this->tagName,$this->htmlOptions);
echo CHtml::closeTag($this->tagName);
$this->options['value']=$this->value;
$options=CJavaScript::encode($this->options);
Yii::app()->getClientScript()->registerScript(__CLASS__.'#'.$id,"jQuery('#{$id}').progressbar($options);");
}
}