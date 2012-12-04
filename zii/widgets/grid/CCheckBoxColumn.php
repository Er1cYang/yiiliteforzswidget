<?php
Yii::import('zii.widgets.grid.CGridColumn');
class CCheckBoxColumn extends CGridColumn
{
public $name;
public $value;
public $checked;
public $htmlOptions=array('class'=>'checkbox-column');
public $headerHtmlOptions=array('class'=>'checkbox-column');
public $footerHtmlOptions=array('class'=>'checkbox-column');
public $checkBoxHtmlOptions=array();
public $selectableRows=null;
public $headerTemplate='{item}';
public function init()
{
if(isset($this->checkBoxHtmlOptions['name']))
$name=$this->checkBoxHtmlOptions['name'];
else
{
$name=$this->id;
if(substr($name,-2)!=='[]')
$name.='[]';
$this->checkBoxHtmlOptions['name']=$name;
}
$name=strtr($name,array('['=>"\\[",']'=>"\\]"));
if($this->selectableRows===null)
{
if(isset($this->checkBoxHtmlOptions['class']))
$this->checkBoxHtmlOptions['class'].=' select-on-check';
else
$this->checkBoxHtmlOptions['class']='select-on-check';
return;
}
$cball=$cbcode='';
if($this->selectableRows==0)
{
$cbcode="return false;";
}
elseif($this->selectableRows==1)
{
$cbcode="$(\"input:not(#\"+this.id+\")[name='$name']\").prop('checked',false);";
}
elseif(strpos($this->headerTemplate,'{item}')!==false)
{
$cball=<<<CBALL
$(document).on('click','#{$this->id}_all',function() {
var checked=this.checked;
$("input[name='$name']").each(function() {this.checked=checked;});
});
CBALL;
$cbcode="$('#{$this->id}_all').prop('checked', $(\"input[name='$name']\").length==$(\"input[name='$name']:checked\").length);";
}
if($cbcode!=='')
{
$js=$cball;
$js.=<<<EOD
$(document).on('click', "input[name='$name']", function() {
$cbcode
});
EOD;
Yii::app()->getClientScript()->registerScript(__CLASS__.'#'.$this->id,$js);
}
}
protected function renderHeaderCellContent()
{
if(trim($this->headerTemplate)==='')
{
echo $this->grid->blankDisplay;
return;
}
$item = '';
if($this->selectableRows===null && $this->grid->selectableRows>1)
$item = CHtml::checkBox($this->id.'_all',false,array('class'=>'select-on-check-all'));
else if($this->selectableRows>1)
$item = CHtml::checkBox($this->id.'_all',false);
else
{
ob_start();
parent::renderHeaderCellContent();
$item = ob_get_clean();
}
echo strtr($this->headerTemplate,array(
'{item}'=>$item,
));
}
protected function renderDataCellContent($row,$data)
{
if($this->value!==null)
$value=$this->evaluateExpression($this->value,array('data'=>$data,'row'=>$row));
else if($this->name!==null)
$value=CHtml::value($data,$this->name);
else
$value=$this->grid->dataProvider->keys[$row];
$checked = false;
if($this->checked!==null)
$checked=$this->evaluateExpression($this->checked,array('data'=>$data,'row'=>$row));
$options=$this->checkBoxHtmlOptions;
$name=$options['name'];
unset($options['name']);
$options['value']=$value;
$options['id']=$this->id.'_'.$row;
echo CHtml::checkBox($name,$checked,$options);
}
}
