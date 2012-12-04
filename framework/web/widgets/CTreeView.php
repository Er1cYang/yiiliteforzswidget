<?php
class CTreeView extends CWidget
{
public $data;
public $cssFile;
public $url;
public $animated;
public $collapsed;
public $control;
public $unique;
public $toggle;
public $persist;
public $cookieId;
public $prerendered;
public $options=array();
public $htmlOptions;
public function init()
{
if(isset($this->htmlOptions['id']))
$id=$this->htmlOptions['id'];
else
$id=$this->htmlOptions['id']=$this->getId();
if($this->url!==null)
$this->url=CHtml::normalizeUrl($this->url);
$cs=Yii::app()->getClientScript();
$cs->registerCoreScript('treeview');
$options=$this->getClientOptions();
$options=$options===array()?'{}' : CJavaScript::encode($options);
$cs->registerScript('Yii.CTreeView#'.$id,"jQuery(\"#{$id}\").treeview($options);");
if($this->cssFile===null)
$cs->registerCssFile($cs->getCoreScriptUrl().'/treeview/jquery.treeview.css');
else if($this->cssFile!==false)
$cs->registerCssFile($this->cssFile);
echo CHtml::tag('ul',$this->htmlOptions,false,false)."\n";
echo self::saveDataAsHtml($this->data);
}
public function run()
{
echo "</ul>";
}
protected function getClientOptions()
{
$options=$this->options;
foreach(array('url','animated','collapsed','control','unique','toggle','persist','cookieId','prerendered') as $name)
{
if($this->$name!==null)
$options[$name]=$this->$name;
}
return $options;
}
public static function saveDataAsHtml($data)
{
$html='';
if(is_array($data))
{
foreach($data as $node)
{
if(!isset($node['text']))
continue;
if(isset($node['expanded']))
$css=$node['expanded'] ? 'open' : 'closed';
else
$css='';
if(isset($node['hasChildren']) && $node['hasChildren'])
{
if($css!=='')
$css.=' ';
$css.='hasChildren';
}
$options=isset($node['htmlOptions']) ? $node['htmlOptions'] : array();
if($css!=='')
{
if(isset($options['class']))
$options['class'].=' '.$css;
else
$options['class']=$css;
}
if(isset($node['id']))
$options['id']=$node['id'];
$html.=CHtml::tag('li',$options,$node['text'],false);
if(!empty($node['children']))
{
$html.="\n<ul>\n";
$html.=self::saveDataAsHtml($node['children']);
$html.="</ul>\n";
}
$html.=CHtml::closeTag('li')."\n";
}
}
return $html;
}
public static function saveDataAsJson($data)
{
if(empty($data))
return '[]';
else
return CJavaScript::jsonEncode($data);
}
}
