<?php
Yii::import('zii.widgets.CBaseListView');
Yii::import('zii.widgets.grid.CDataColumn');
Yii::import('zii.widgets.grid.CLinkColumn');
Yii::import('zii.widgets.grid.CButtonColumn');
Yii::import('zii.widgets.grid.CCheckBoxColumn');
class CGridView extends CBaseListView
{
const FILTER_POS_HEADER='header';
const FILTER_POS_FOOTER='footer';
const FILTER_POS_BODY='body';
private $_formatter;
public $columns=array();
public $rowCssClass=array('odd','even');
public $rowCssClassExpression;
public $showTableOnEmpty=true;
public $ajaxUpdate;
public $updateSelector='{page}, {sort}';
public $ajaxUpdateError;
public $ajaxVar='ajax';
public $ajaxUrl;
public $beforeAjaxUpdate;
public $afterAjaxUpdate;
public $selectionChanged;
public $selectableRows=1;
public $baseScriptUrl;
public $cssFile;
public $nullDisplay='&nbsp;';
public $blankDisplay='&nbsp;';
public $loadingCssClass='grid-view-loading';
public $filterCssClass='filters';
public $filterPosition='body';
public $filter;
public $hideHeader=false;
public $enableHistory=false;
public function init()
{
parent::init();
if(empty($this->updateSelector))
throw new CException(Yii::t('zii','The property updateSelector should be defined.'));
if(!isset($this->htmlOptions['class']))
$this->htmlOptions['class']='grid-view';
if($this->baseScriptUrl===null)
$this->baseScriptUrl=Yii::app()->getAssetManager()->publish(Yii::getPathOfAlias('zii.widgets.assets')).'/gridview';
if($this->cssFile!==false)
{
if($this->cssFile===null)
$this->cssFile=$this->baseScriptUrl.'/styles.css';
Yii::app()->getClientScript()->registerCssFile($this->cssFile);
}
$this->initColumns();
}
protected function initColumns()
{
if($this->columns===array())
{
if($this->dataProvider instanceof CActiveDataProvider)
$this->columns=$this->dataProvider->model->attributeNames();
else if($this->dataProvider instanceof IDataProvider)
{
$data=$this->dataProvider->getData();
if(isset($data[0]) && is_array($data[0]))
$this->columns=array_keys($data[0]);
}
}
$id=$this->getId();
foreach($this->columns as $i=>$column)
{
if(is_string($column))
$column=$this->createDataColumn($column);
else
{
if(!isset($column['class']))
$column['class']='CDataColumn';
$column=Yii::createComponent($column, $this);
}
if(!$column->visible)
{
unset($this->columns[$i]);
continue;
}
if($column->id===null)
$column->id=$id.'_c'.$i;
$this->columns[$i]=$column;
}
foreach($this->columns as $column)
$column->init();
}
protected function createDataColumn($text)
{
if(!preg_match('/^([\w\.]+)(:(\w*))?(:(.*))?$/',$text,$matches))
throw new CException(Yii::t('zii','The column must be specified in the format of "Name:Type:Label", where "Type" and "Label" are optional.'));
$column=new CDataColumn($this);
$column->name=$matches[1];
if(isset($matches[3]) && $matches[3]!=='')
$column->type=$matches[3];
if(isset($matches[5]))
$column->header=$matches[5];
return $column;
}
public function registerClientScript()
{
$id=$this->getId();
if($this->ajaxUpdate===false)
$ajaxUpdate=false;
else
$ajaxUpdate=array_unique(preg_split('/\s*,\s*/',$this->ajaxUpdate.','.$id,-1,PREG_SPLIT_NO_EMPTY));
$options=array(
'ajaxUpdate'=>$ajaxUpdate,
'ajaxVar'=>$this->ajaxVar,
'pagerClass'=>$this->pagerCssClass,
'loadingClass'=>$this->loadingCssClass,
'filterClass'=>$this->filterCssClass,
'tableClass'=>$this->itemsCssClass,
'selectableRows'=>$this->selectableRows,
'enableHistory'=>$this->enableHistory,
'updateSelector'=>$this->updateSelector
);
if($this->ajaxUrl!==null)
$options['url']=CHtml::normalizeUrl($this->ajaxUrl);
if($this->enablePagination)
$options['pageVar']=$this->dataProvider->getPagination()->pageVar;
foreach(array('beforeAjaxUpdate', 'afterAjaxUpdate', 'ajaxUpdateError', 'selectionChanged') as $event)
{
if($this->$event!==null)
{
if($this->$event instanceof CJavaScriptExpression)
$options[$event]=$this->$event;
else
$options[$event]=new CJavaScriptExpression($this->$event);
}
}
$options=CJavaScript::encode($options);
$cs=Yii::app()->getClientScript();
$cs->registerCoreScript('jquery');
$cs->registerCoreScript('bbq');
if($this->enableHistory)
$cs->registerCoreScript('history');
$cs->registerScriptFile($this->baseScriptUrl.'/jquery.yiigridview.js',CClientScript::POS_END);
$cs->registerScript(__CLASS__.'#'.$id,"jQuery('#$id').yiiGridView($options);");
}
public function renderItems()
{
if($this->dataProvider->getItemCount()>0 || $this->showTableOnEmpty)
{
echo "<table class=\"{$this->itemsCssClass}\">\n";
$this->renderTableHeader();
ob_start();
$this->renderTableBody();
$body=ob_get_clean();
$this->renderTableFooter();
echo $body; // TFOOT must appear before TBODY according to the standard.
echo "</table>";
}
else
$this->renderEmptyText();
}
public function renderTableHeader()
{
if(!$this->hideHeader)
{
echo "<thead>\n";
if($this->filterPosition===self::FILTER_POS_HEADER)
$this->renderFilter();
echo "<tr>\n";
foreach($this->columns as $column)
$column->renderHeaderCell();
echo "</tr>\n";
if($this->filterPosition===self::FILTER_POS_BODY)
$this->renderFilter();
echo "</thead>\n";
}
else if($this->filter!==null && ($this->filterPosition===self::FILTER_POS_HEADER || $this->filterPosition===self::FILTER_POS_BODY))
{
echo "<thead>\n";
$this->renderFilter();
echo "</thead>\n";
}
}
public function renderFilter()
{
if($this->filter!==null)
{
echo "<tr class=\"{$this->filterCssClass}\">\n";
foreach($this->columns as $column)
$column->renderFilterCell();
echo "</tr>\n";
}
}
public function renderTableFooter()
{
$hasFilter=$this->filter!==null && $this->filterPosition===self::FILTER_POS_FOOTER;
$hasFooter=$this->getHasFooter();
if($hasFilter || $hasFooter)
{
echo "<tfoot>\n";
if($hasFooter)
{
echo "<tr>\n";
foreach($this->columns as $column)
$column->renderFooterCell();
echo "</tr>\n";
}
if($hasFilter)
$this->renderFilter();
echo "</tfoot>\n";
}
}
public function renderTableBody()
{
$data=$this->dataProvider->getData();
$n=count($data);
echo "<tbody>\n";
if($n>0)
{
for($row=0;$row<$n;++$row)
$this->renderTableRow($row);
}
else
{
echo '<tr><td colspan="'.count($this->columns).'" class="empty">';
$this->renderEmptyText();
echo "</td></tr>\n";
}
echo "</tbody>\n";
}
public function renderTableRow($row)
{
if($this->rowCssClassExpression!==null)
{
$data=$this->dataProvider->data[$row];
$class=$this->evaluateExpression($this->rowCssClassExpression,array('row'=>$row,'data'=>$data));
}
else if(is_array($this->rowCssClass) && ($n=count($this->rowCssClass))>0)
$class=$this->rowCssClass[$row%$n];
else
$class='';
echo empty($class) ? '<tr>' : '<tr class="'.$class.'">';
foreach($this->columns as $column)
$column->renderDataCell($row);
echo "</tr>\n";
}
public function getHasFooter()
{
foreach($this->columns as $column)
if($column->getHasFooter())
return true;
return false;
}
public function getFormatter()
{
if($this->_formatter===null)
$this->_formatter=Yii::app()->format;
return $this->_formatter;
}
public function setFormatter($value)
{
$this->_formatter=$value;
}
}
