<?php
Yii::import('zii.widgets.jui.CJuiInputWidget');
class CJuiSliderInput extends CJuiInputWidget
{
public $tagName = 'div';
public $value;
public $event = 'slide';
public $maxAttribute;
public function run()
{
list($name,$id)=$this->resolveNameID();
$isRange=isset($this->options['range']) && $this->options['range'];
if(isset($this->htmlOptions['id']))
$id=$this->htmlOptions['id'];
else
$this->htmlOptions['id']=$id;
if(isset($this->htmlOptions['name']))
$name=$this->htmlOptions['name'];
if($this->hasModel())
{
$attribute=$this->attribute;
if ($isRange)
{
$options=$this->htmlOptions;
echo CHtml::activeHiddenField($this->model,$this->attribute,$options);
$options['id']=$options['id'].'_end';
echo CHtml::activeHiddenField($this->model,$this->maxAttribute,$options);
$attrMax=$this->maxAttribute;
$this->options['values']=array($this->model->$attribute,$this->model->$attrMax);
}
else
{
echo CHtml::activeHiddenField($this->model,$this->attribute,$this->htmlOptions);
$this->options['value']=$this->model->$attribute;
}
}
else
{
echo CHtml::hiddenField($name,$this->value,$this->htmlOptions);
if($this->value!==null)
$this->options['value']=$this->value;
}
$idHidden = $this->htmlOptions['id'];
$this->htmlOptions['id']=$idHidden.'_slider';
echo CHtml::tag($this->tagName,$this->htmlOptions,'');
$this->options[$this->event]= $isRange ?
new CJavaScriptExpression("function(e,ui){ v=ui.values; jQuery('#{$idHidden}').val(v[0]); jQuery('#{$idHidden}_end').val(v[1]); }"):
new CJavaScriptExpression('function(event, ui) { jQuery(\'#'. $idHidden .'\').val(ui.value); }');
$options=empty($this->options) ? '' : CJavaScript::encode($this->options);
$js = "jQuery('#{$id}_slider').slider($options);\n";
Yii::app()->getClientScript()->registerScript(__CLASS__.'#'.$id, $js);
}
}
