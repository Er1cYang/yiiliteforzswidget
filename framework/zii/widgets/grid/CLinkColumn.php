<?php
Yii::import('zii.widgets.grid.CGridColumn');
class CLinkColumn extends CGridColumn
{
public $label='Link';
public $labelExpression;
public $imageUrl;
public $url='javascript:void(0)';
public $urlExpression;
public $htmlOptions=array('class'=>'link-column');
public $headerHtmlOptions=array('class'=>'link-column');
public $footerHtmlOptions=array('class'=>'link-column');
public $linkHtmlOptions=array();
protected function renderDataCellContent($row,$data)
{
if($this->urlExpression!==null)
$url=$this->evaluateExpression($this->urlExpression,array('data'=>$data,'row'=>$row));
else
$url=$this->url;
if($this->labelExpression!==null)
$label=$this->evaluateExpression($this->labelExpression,array('data'=>$data,'row'=>$row));
else
$label=$this->label;
$options=$this->linkHtmlOptions;
if(is_string($this->imageUrl))
echo CHtml::link(CHtml::image($this->imageUrl,$label),$url,$options);
else
echo CHtml::link($label,$url,$options);
}
}
