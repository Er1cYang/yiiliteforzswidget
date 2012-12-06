<?php
class CMaskedTextField extends CInputWidget
{
public $mask;
public $charMap;
public $placeholder;
public $completed;
public function run()
{
if($this->mask=='')
throw new CException(Yii::t('yii','Property CMaskedTextField.mask cannot be empty.'));
list($name,$id)=$this->resolveNameID();
if(isset($this->htmlOptions['id']))
$id=$this->htmlOptions['id'];
else
$this->htmlOptions['id']=$id;
if(isset($this->htmlOptions['name']))
$name=$this->htmlOptions['name'];
$this->registerClientScript();
if($this->hasModel())
echo CHtml::activeTextField($this->model,$this->attribute,$this->htmlOptions);
else
echo CHtml::textField($name,$this->value,$this->htmlOptions);
}
public function registerClientScript()
{
$id=$this->htmlOptions['id'];
$miOptions=$this->getClientOptions();
$options=$miOptions!==array() ? ','.CJavaScript::encode($miOptions) : '';
$js='';
if(is_array($this->charMap))
$js.='jQuery.mask.definitions='.CJavaScript::encode($this->charMap).";\n";
$js.="jQuery(\"#{$id}\").mask(\"{$this->mask}\"{$options});";
$cs=Yii::app()->getClientScript();
$cs->registerCoreScript('maskedinput');
$cs->registerScript('Yii.CMaskedTextField#'.$id,$js);
}
protected function getClientOptions()
{
$options=array();
if($this->placeholder!==null)
$options['placeholder']=$this->placeholder;
if($this->completed!==null)
{
if($this->completed instanceof CJavaScriptExpression)
$options['completed']=$this->completed;
else
$options['completed']=new CJavaScriptExpression($this->completed);
}
return $options;
}
}