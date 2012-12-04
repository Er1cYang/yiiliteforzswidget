<?php
abstract class CGridColumn extends CComponent
{
public $id;
public $grid;
public $header;
public $footer;
public $visible=true;
public $cssClassExpression;
public $htmlOptions=array();
public $filterHtmlOptions=array();
public $headerHtmlOptions=array();
public $footerHtmlOptions=array();
public function __construct($grid)
{
$this->grid=$grid;
}
public function init()
{
}
public function getHasFooter()
{
return $this->footer!==null;
}
public function renderFilterCell()
{
echo CHtml::openTag('td',$this->filterHtmlOptions);
$this->renderFilterCellContent();
echo "</td>";
}
public function renderHeaderCell()
{
$this->headerHtmlOptions['id']=$this->id;
echo CHtml::openTag('th',$this->headerHtmlOptions);
$this->renderHeaderCellContent();
echo "</th>";
}
public function renderDataCell($row)
{
$data=$this->grid->dataProvider->data[$row];
$options=$this->htmlOptions;
if($this->cssClassExpression!==null)
{
$class=$this->evaluateExpression($this->cssClassExpression,array('row'=>$row,'data'=>$data));
if(!empty($class))
{
if(isset($options['class']))
$options['class'].=' '.$class;
else
$options['class']=$class;
}
}
echo CHtml::openTag('td',$options);
$this->renderDataCellContent($row,$data);
echo '</td>';
}
public function renderFooterCell()
{
echo CHtml::openTag('td',$this->footerHtmlOptions);
$this->renderFooterCellContent();
echo '</td>';
}
protected function renderHeaderCellContent()
{
echo trim($this->header)!=='' ? $this->header : $this->grid->blankDisplay;
}
protected function renderFooterCellContent()
{
echo trim($this->footer)!=='' ? $this->footer : $this->grid->blankDisplay;
}
protected function renderDataCellContent($row,$data)
{
echo $this->grid->blankDisplay;
}
protected function renderFilterCellContent()
{
echo $this->grid->blankDisplay;
}
}
