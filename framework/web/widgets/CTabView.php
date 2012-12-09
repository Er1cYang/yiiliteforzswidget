<?php
class CTabView extends CWidget
{
const CSS_CLASS='yiiTab';
public $cssFile;
public $activeTab;
public $viewData;
public $htmlOptions;
public $tabs=array();
public function run()
{
foreach($this->tabs as $id=>$tab)
if(isset($tab['visible']) && $tab['visible']==false)
unset($this->tabs[$id]);
if(empty($this->tabs))
return;
if($this->activeTab===null || !isset($this->tabs[$this->activeTab]))
{
reset($this->tabs);
list($this->activeTab, )=each($this->tabs);
}
$htmlOptions=$this->htmlOptions;
$htmlOptions['id']=$this->getId();
if(!isset($htmlOptions['class']))
$htmlOptions['class']=self::CSS_CLASS;
$this->registerClientScript();
echo CHtml::openTag('div',$htmlOptions)."\n";
$this->renderHeader();
$this->renderBody();
echo CHtml::closeTag('div');
}
public function registerClientScript()
{
$cs=Yii::app()->getClientScript();
$cs->registerCoreScript('yiitab');
$id=$this->getId();
$cs->registerScript('Yii.CTabView#'.$id,"jQuery(\"#{$id}\").yiitab();");
if($this->cssFile!==false)
self::registerCssFile($this->cssFile);
}
public static function registerCssFile($url=null)
{
$cs=Yii::app()->getClientScript();
if($url===null)
$url=$cs->getCoreScriptUrl().'/yiitab/jquery.yiitab.css';
$cs->registerCssFile($url,'screen');
}
protected function renderHeader()
{
echo "<ul class=\"tabs\">\n";
foreach($this->tabs as $id=>$tab)
{
$title=isset($tab['title'])?$tab['title']:'undefined';
$active=$id===$this->activeTab?' class="active"' : '';
$url=isset($tab['url'])?$tab['url']:"#{$id}";
echo "<li><a href=\"{$url}\"{$active}>{$title}</a></li>\n";
}
echo "</ul>\n";
}
protected function renderBody()
{
foreach($this->tabs as $id=>$tab)
{
$inactive=$id!==$this->activeTab?' style="display:none"' : '';
echo "<div class=\"view\" id=\"{$id}\"{$inactive}>\n";
if(isset($tab['content']))
echo $tab['content'];
else if(isset($tab['view']))
{
if(isset($tab['data']))
{
if(is_array($this->viewData))
$data=array_merge($this->viewData, $tab['data']);
else
$data=$tab['data'];
}
else
$data=$this->viewData;
$this->getController()->renderPartial($tab['view'], $data);
}
echo "</div><!--{$id}-->\n";
}
}
}
