<?php
Yii::import('zii.widgets.jui.CJuiInputWidget');
class CJuiButton extends CJuiInputWidget
{
public $buttonType = 'submit';
public $htmlTag = 'div';
public $url = null;
public $value;
public $caption="";
public $onclick;
public function init(){
parent::init();
if ($this->buttonType=='buttonset')
{
list($name,$id)=$this->resolveNameID();
if(isset($this->htmlOptions['id']))
$id=$this->htmlOptions['id'];
else
$this->htmlOptions['id']=$id;
if(isset($this->htmlOptions['name']))
$name=$this->htmlOptions['name'];
else
$this->htmlOptions['name']=$name;
echo CHtml::openTag($this->htmlTag, $this->htmlOptions);
}
}
public function run()
{
$cs = Yii::app()->getClientScript();
list($name,$id)=$this->resolveNameID();
if(isset($this->htmlOptions['id']))
$id=$this->htmlOptions['id'];
else
$this->htmlOptions['id']=$id;
if(isset($this->htmlOptions['name']))
$name=$this->htmlOptions['name'];
else
$this->htmlOptions['name']=$name;
if ($this->buttonType=='buttonset')
{
echo CHtml::closeTag($this->htmlTag);
$cs->registerScript(__CLASS__.'#'.$id,"jQuery('#{$id}').buttonset();");
}
else
{
switch($this->buttonType)
{
case 'submit':
echo CHtml::submitButton($this->caption, $this->htmlOptions) . "\n";
break;
case 'button':
echo CHtml::htmlButton($this->caption, $this->htmlOptions) . "\n";
break;
case 'link':
echo CHtml::link($this->caption, $this->url, $this->htmlOptions) . "\n";
break;
case 'radio':
if ($this->hasModel())
{
echo CHtml::activeRadioButton($this->model, $this->attribute, $this->htmlOptions);
echo CHtml::label($this->caption, CHtml::activeId($this->model, $this->attribute)) . "\n";
}
else
{
echo CHtml::radioButton($name, $this->value, $this->htmlOptions);
echo CHtml::label($this->caption, $id) . "\n";
}
break;
case 'checkbox':
if ($this->hasModel())
{
echo CHtml::activeCheckbox($this->model, $this->attribute, $this->htmlOptions);
echo CHtml::label($this->caption, CHtml::activeId($this->model, $this->attribute)) . "\n";
}
else
{
echo CHtml::checkbox($name, $this->value, $this->htmlOptions);
echo CHtml::label($this->caption, $id) . "\n";
}
break;
default:
throw new CException(Yii::t('zii','The button type "{type}" is not supported.',array('{type}'=>$this->buttonType)));
}
$options=empty($this->options) ? '' : CJavaScript::encode($this->options);
if($this->onclick!==null)
{
if(!($this->onclick instanceof CJavaScriptExpression))
$this->onclick=new CJavaScriptExpression($this->onclick);
$click = CJavaScript::encode($this->onclick);
$cs->registerScript(__CLASS__.'#'.$id,"jQuery('#{$id}').button($options).click($click);");
}
else
{
$cs->registerScript(__CLASS__.'#'.$id,"jQuery('#{$id}').button($options);");
}
}
}
}
