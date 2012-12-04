<?php
class CBreadcrumbs extends CWidget
{
public $tagName='div';
public $htmlOptions=array('class'=>'breadcrumbs');
public $encodeLabel=true;
public $homeLink;
public $links=array();
public $activeLinkTemplate='<a href="{url}">{label}</a>';
public $inactiveLinkTemplate='<span>{label}</span>';
public $separator=' &raquo; ';
public function run()
{
if(empty($this->links))
return;
echo CHtml::openTag($this->tagName,$this->htmlOptions)."\n";
$links=array();
if($this->homeLink===null)
$links[]=CHtml::link(Yii::t('zii','Home'),Yii::app()->homeUrl);
else if($this->homeLink!==false)
$links[]=$this->homeLink;
foreach($this->links as $label=>$url)
{
if(is_string($label) || is_array($url))
$links[]=strtr($this->activeLinkTemplate,array(
'{url}'=>CHtml::normalizeUrl($url),
'{label}'=>$this->encodeLabel ? CHtml::encode($label) : $label,
));
else
$links[]=str_replace('{label}',$this->encodeLabel ? CHtml::encode($url) : $url,$this->inactiveLinkTemplate);
}
echo implode($this->separator,$links);
echo CHtml::closeTag($this->tagName);
}
}