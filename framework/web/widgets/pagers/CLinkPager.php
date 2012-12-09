<?php
class CLinkPager extends CBasePager
{
const CSS_FIRST_PAGE='first';
const CSS_LAST_PAGE='last';
const CSS_PREVIOUS_PAGE='previous';
const CSS_NEXT_PAGE='next';
const CSS_INTERNAL_PAGE='page';
const CSS_HIDDEN_PAGE='hidden';
const CSS_SELECTED_PAGE='selected';
public $firstPageCssClass=self::CSS_FIRST_PAGE;
public $lastPageCssClass=self::CSS_LAST_PAGE;
public $previousPageCssClass=self::CSS_PREVIOUS_PAGE;
public $nextPageCssClass=self::CSS_NEXT_PAGE;
public $internalPageCssClass=self::CSS_INTERNAL_PAGE;
public $hiddenPageCssClass=self::CSS_HIDDEN_PAGE;
public $selectedPageCssClass=self::CSS_SELECTED_PAGE;
public $maxButtonCount=10;
public $nextPageLabel;
public $prevPageLabel;
public $firstPageLabel;
public $lastPageLabel;
public $header;
public $footer='';
public $cssFile;
public $htmlOptions=array();
public function init()
{
if($this->nextPageLabel===null)
$this->nextPageLabel=Yii::t('yii','Next &gt;');
if($this->prevPageLabel===null)
$this->prevPageLabel=Yii::t('yii','&lt; Previous');
if($this->firstPageLabel===null)
$this->firstPageLabel=Yii::t('yii','&lt;&lt; First');
if($this->lastPageLabel===null)
$this->lastPageLabel=Yii::t('yii','Last &gt;&gt;');
if($this->header===null)
$this->header=Yii::t('yii','Go to page: ');
if(!isset($this->htmlOptions['id']))
$this->htmlOptions['id']=$this->getId();
if(!isset($this->htmlOptions['class']))
$this->htmlOptions['class']='yiiPager';
}
public function run()
{
$this->registerClientScript();
$buttons=$this->createPageButtons();
if(empty($buttons))
return;
echo $this->header;
echo CHtml::tag('ul',$this->htmlOptions,implode("\n",$buttons));
echo $this->footer;
}
protected function createPageButtons()
{
if(($pageCount=$this->getPageCount())<=1)
return array();
list($beginPage,$endPage)=$this->getPageRange();
$currentPage=$this->getCurrentPage(false);//currentPage is calculated in getPageRange()
$buttons=array();
$buttons[]=$this->createPageButton($this->firstPageLabel,0,$this->firstPageCssClass,$currentPage<=0,false);
if(($page=$currentPage-1)<0)
$page=0;
$buttons[]=$this->createPageButton($this->prevPageLabel,$page,$this->previousPageCssClass,$currentPage<=0,false);
for($i=$beginPage;$i<=$endPage;++$i)
$buttons[]=$this->createPageButton($i+1,$i,$this->internalPageCssClass,false,$i==$currentPage);
if(($page=$currentPage+1)>=$pageCount-1)
$page=$pageCount-1;
$buttons[]=$this->createPageButton($this->nextPageLabel,$page,$this->nextPageCssClass,$currentPage>=$pageCount-1,false);
$buttons[]=$this->createPageButton($this->lastPageLabel,$pageCount-1,$this->lastPageCssClass,$currentPage>=$pageCount-1,false);
return $buttons;
}
protected function createPageButton($label,$page,$class,$hidden,$selected)
{
if($hidden || $selected)
$class.=' '.($hidden ? $this->hiddenPageCssClass : $this->selectedPageCssClass);
return '<li class="'.$class.'">'.CHtml::link($label,$this->createPageUrl($page)).'</li>';
}
protected function getPageRange()
{
$currentPage=$this->getCurrentPage();
$pageCount=$this->getPageCount();
$beginPage=max(0, $currentPage-(int)($this->maxButtonCount/2));
if(($endPage=$beginPage+$this->maxButtonCount-1)>=$pageCount)
{
$endPage=$pageCount-1;
$beginPage=max(0,$endPage-$this->maxButtonCount+1);
}
return array($beginPage,$endPage);
}
public function registerClientScript()
{
if($this->cssFile!==false)
self::registerCssFile($this->cssFile);
}
public static function registerCssFile($url=null)
{
if($url===null)
$url=CHtml::asset(Yii::getPathOfAlias('system.web.widgets.pagers.pager').'.css');
Yii::app()->getClientScript()->registerCssFile($url);
}
}
