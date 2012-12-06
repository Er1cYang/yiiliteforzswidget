<?php
Yii::import('zii.widgets.CBaseListView');
class CListView extends CBaseListView
{
public $itemView;
public $separator;
public $viewData=array();
public $sortableAttributes;
public $template="{summary}\n{sorter}\n{items}\n{pager}";
public $loadingCssClass='list-view-loading';
public $sorterCssClass='sorter';
public $sorterHeader;
public $sorterFooter='';
public $ajaxUpdate;
public $updateSelector;
public $ajaxVar='ajax';
public $ajaxUrl;
public $beforeAjaxUpdate;
public $afterAjaxUpdate;
public $baseScriptUrl;
public $cssFile;
public $itemsTagName='div';
public $enableHistory=false;
public function init()
{
if($this->itemView===null)
throw new CException(Yii::t('zii','The property "itemView" cannot be empty.'));
parent::init();
if(!isset($this->htmlOptions['class']))
$this->htmlOptions['class']='list-view';
if($this->baseScriptUrl===null)
$this->baseScriptUrl=Yii::app()->getAssetManager()->publish(Yii::getPathOfAlias('zii.widgets.assets')).'/listview';
if($this->cssFile!==false)
{
if($this->cssFile===null)
$this->cssFile=$this->baseScriptUrl.'/styles.css';
Yii::app()->getClientScript()->registerCssFile($this->cssFile);
}
}
public function registerClientScript()
{
$id=$this->getId();
if($this->ajaxUpdate===false)
$ajaxUpdate=array();
else
$ajaxUpdate=array_unique(preg_split('/\s*,\s*/',$this->ajaxUpdate.','.$id,-1,PREG_SPLIT_NO_EMPTY));
$options=array(
'ajaxUpdate'=>$ajaxUpdate,
'ajaxVar'=>$this->ajaxVar,
'pagerClass'=>$this->pagerCssClass,
'loadingClass'=>$this->loadingCssClass,
'sorterClass'=>$this->sorterCssClass,
'enableHistory'=>$this->enableHistory
);
if($this->ajaxUrl!==null)
$options['url']=CHtml::normalizeUrl($this->ajaxUrl);
if($this->updateSelector!==null)
$options['updateSelector']=$this->updateSelector;
foreach(array('beforeAjaxUpdate', 'afterAjaxUpdate') as $event)
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
$cs->registerScriptFile($this->baseScriptUrl.'/jquery.yiilistview.js',CClientScript::POS_END);
$cs->registerScript(__CLASS__.'#'.$id,"jQuery('#$id').yiiListView($options);");
}
public function renderItems()
{
echo CHtml::openTag($this->itemsTagName,array('class'=>$this->itemsCssClass))."\n";
$data=$this->dataProvider->getData();
if(($n=count($data))>0)
{
$owner=$this->getOwner();
$viewFile=$owner->getViewFile($this->itemView);
$j=0;
foreach($data as $i=>$item)
{
$data=$this->viewData;
$data['index']=$i;
$data['data']=$item;
$data['widget']=$this;
$owner->renderFile($viewFile,$data);
if($j++ < $n-1)
echo $this->separator;
}
}
else
$this->renderEmptyText();
echo CHtml::closeTag($this->itemsTagName);
}
public function renderSorter()
{
if($this->dataProvider->getItemCount()<=0 || !$this->enableSorting || empty($this->sortableAttributes))
return;
echo CHtml::openTag('div',array('class'=>$this->sorterCssClass))."\n";
echo $this->sorterHeader===null ? Yii::t('zii','Sort by: ') : $this->sorterHeader;
echo "<ul>\n";
$sort=$this->dataProvider->getSort();
foreach($this->sortableAttributes as $name=>$label)
{
echo "<li>";
if(is_integer($name))
echo $sort->link($label);
else
echo $sort->link($name,$label);
echo "</li>\n";
}
echo "</ul>";
echo $this->sorterFooter;
echo CHtml::closeTag('div');
}
}
