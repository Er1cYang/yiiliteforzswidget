<?php
abstract class CBaseListView extends CWidget
{
public $dataProvider;
public $tagName='div';
public $htmlOptions=array();
public $enableSorting=true;
public $enablePagination=true;
public $pager=array('class'=>'CLinkPager');
public $template="{summary}\n{items}\n{pager}";
public $summaryText;
public $emptyText;
public $itemsCssClass='items';
public $summaryCssClass='summary';
public $pagerCssClass='pager';
public $loadingCssClass='loading';
public function init()
{
if($this->dataProvider===null)
throw new CException(Yii::t('zii','The "dataProvider" property cannot be empty.'));
$this->dataProvider->getData();
$this->htmlOptions['id']=$this->getId();
if($this->enableSorting && $this->dataProvider->getSort()===false)
$this->enableSorting=false;
if($this->enablePagination && $this->dataProvider->getPagination()===false)
$this->enablePagination=false;
}
public function run()
{
$this->registerClientScript();
echo CHtml::openTag($this->tagName,$this->htmlOptions)."\n";
$this->renderContent();
$this->renderKeys();
echo CHtml::closeTag($this->tagName);
}
public function renderContent()
{
ob_start();
echo preg_replace_callback("/{(\w+)}/",array($this,'renderSection'),$this->template);
ob_end_flush();
}
protected function renderSection($matches)
{
$method='render'.$matches[1];
if(method_exists($this,$method))
{
$this->$method();
$html=ob_get_contents();
ob_clean();
return $html;
}
else
return $matches[0];
}
public function renderEmptyText()
{
$emptyText=$this->emptyText===null ? Yii::t('zii','No results found.') : $this->emptyText;
echo CHtml::tag('span', array('class'=>'empty'), $emptyText);
}
public function renderKeys()
{
echo CHtml::openTag('div',array(
'class'=>'keys',
'style'=>'display:none',
'title'=>Yii::app()->getRequest()->getUrl(),
));
foreach($this->dataProvider->getKeys() as $key)
echo "<span>".CHtml::encode($key)."</span>";
echo "</div>\n";
}
public function renderSummary()
{
if(($count=$this->dataProvider->getItemCount())<=0)
return;
echo '<div class="'.$this->summaryCssClass.'">';
if($this->enablePagination)
{
$pagination=$this->dataProvider->getPagination();
$total=$this->dataProvider->getTotalItemCount();
$start=$pagination->currentPage*$pagination->pageSize+1;
$end=$start+$count-1;
if($end>$total)
{
$end=$total;
$start=$end-$count+1;
}
if(($summaryText=$this->summaryText)===null)
$summaryText=Yii::t('zii','Displaying {start}-{end} of 1 result.|Displaying {start}-{end} of {count} results.',$total);
echo strtr($summaryText,array(
'{start}'=>$start,
'{end}'=>$end,
'{count}'=>$total,
'{page}'=>$pagination->currentPage+1,
'{pages}'=>$pagination->pageCount,
));
}
else
{
if(($summaryText=$this->summaryText)===null)
$summaryText=Yii::t('zii','Total 1 result.|Total {count} results.',$count);
echo strtr($summaryText,array(
'{count}'=>$count,
'{start}'=>1,
'{end}'=>$count,
'{page}'=>1,
'{pages}'=>1,
));
}
echo '</div>';
}
public function renderPager()
{
if(!$this->enablePagination)
return;
$pager=array();
$class='CLinkPager';
if(is_string($this->pager))
$class=$this->pager;
else if(is_array($this->pager))
{
$pager=$this->pager;
if(isset($pager['class']))
{
$class=$pager['class'];
unset($pager['class']);
}
}
$pager['pages']=$this->dataProvider->getPagination();
if($pager['pages']->getPageCount()>1)
{
echo '<div class="'.$this->pagerCssClass.'">';
$this->widget($class,$pager);
echo '</div>';
}
else
$this->widget($class,$pager);
}
public function registerClientScript()
{
}
abstract public function renderItems();
}
