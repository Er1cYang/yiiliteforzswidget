<?php
Yii::import('zii.widgets.grid.CGridColumn');
class CDataColumn extends CGridColumn
{
public $name;
public $value;
public $type='text';
public $sortable=true;
public $filter;
public function init()
{
parent::init();
if($this->name===null)
$this->sortable=false;
if($this->name===null && $this->value===null)
throw new CException(Yii::t('zii','Either "name" or "value" must be specified for CDataColumn.'));
}
protected function renderFilterCellContent()
{
if(is_string($this->filter))
echo $this->filter;
else if($this->filter!==false && $this->grid->filter!==null && $this->name!==null && strpos($this->name,'.')===false)
{
if(is_array($this->filter))
echo CHtml::activeDropDownList($this->grid->filter, $this->name, $this->filter, array('id'=>false,'prompt'=>''));
else if($this->filter===null)
echo CHtml::activeTextField($this->grid->filter, $this->name, array('id'=>false));
}
else
parent::renderFilterCellContent();
}
protected function renderHeaderCellContent()
{
if($this->grid->enableSorting && $this->sortable && $this->name!==null)
echo $this->grid->dataProvider->getSort()->link($this->name,$this->header,array('class'=>'sort-link'));
else if($this->name!==null && $this->header===null)
{
if($this->grid->dataProvider instanceof CActiveDataProvider)
echo CHtml::encode($this->grid->dataProvider->model->getAttributeLabel($this->name));
else
echo CHtml::encode($this->name);
}
else
parent::renderHeaderCellContent();
}
protected function renderDataCellContent($row,$data)
{
if($this->value!==null)
$value=$this->evaluateExpression($this->value,array('data'=>$data,'row'=>$row));
else if($this->name!==null)
$value=CHtml::value($data,$this->name);
echo $value===null ? $this->grid->nullDisplay : $this->grid->getFormatter()->format($value,$this->type);
}
}
