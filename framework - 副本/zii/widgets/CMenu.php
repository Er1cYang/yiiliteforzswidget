<?php
class CMenu extends CWidget
{
public $items=array();
public $itemTemplate;
public $encodeLabel=true;
public $activeCssClass='active';
public $activateItems=true;
public $activateParents=false;
public $hideEmptyItems=true;
public $htmlOptions=array();
public $submenuHtmlOptions=array();
public $linkLabelWrapper;
public $firstItemCssClass;
public $lastItemCssClass;
public $itemCssClass;
public function init()
{
$this->htmlOptions['id']=$this->getId();
$route=$this->getController()->getRoute();
$this->items=$this->normalizeItems($this->items,$route,$hasActiveChild);
}
public function run()
{
$this->renderMenu($this->items);
}
protected function renderMenu($items)
{
if(count($items))
{
echo CHtml::openTag('ul',$this->htmlOptions)."\n";
$this->renderMenuRecursive($items);
echo CHtml::closeTag('ul');
}
}
protected function renderMenuRecursive($items)
{
$count=0;
$n=count($items);
foreach($items as $item)
{
$count++;
$options=isset($item['itemOptions']) ? $item['itemOptions'] : array();
$class=array();
if($item['active'] && $this->activeCssClass!='')
$class[]=$this->activeCssClass;
if($count===1 && $this->firstItemCssClass!==null)
$class[]=$this->firstItemCssClass;
if($count===$n && $this->lastItemCssClass!==null)
$class[]=$this->lastItemCssClass;
if($this->itemCssClass!==null)
$class[]=$this->itemCssClass;
if($class!==array())
{
if(empty($options['class']))
$options['class']=implode(' ',$class);
else
$options['class'].=' '.implode(' ',$class);
}
echo CHtml::openTag('li', $options);
$menu=$this->renderMenuItem($item);
if(isset($this->itemTemplate) || isset($item['template']))
{
$template=isset($item['template']) ? $item['template'] : $this->itemTemplate;
echo strtr($template,array('{menu}'=>$menu));
}
else
echo $menu;
if(isset($item['items']) && count($item['items']))
{
echo "\n".CHtml::openTag('ul',isset($item['submenuOptions']) ? $item['submenuOptions'] : $this->submenuHtmlOptions)."\n";
$this->renderMenuRecursive($item['items']);
echo CHtml::closeTag('ul')."\n";
}
echo CHtml::closeTag('li')."\n";
}
}
protected function renderMenuItem($item)
{
if(isset($item['url']))
{
$label=$this->linkLabelWrapper===null ? $item['label'] : '<'.$this->linkLabelWrapper.'>'.$item['label'].'</'.$this->linkLabelWrapper.'>';
return CHtml::link($label,$item['url'],isset($item['linkOptions']) ? $item['linkOptions'] : array());
}
else
return CHtml::tag('span',isset($item['linkOptions']) ? $item['linkOptions'] : array(), $item['label']);
}
protected function normalizeItems($items,$route,&$active)
{
foreach($items as $i=>$item)
{
if(isset($item['visible']) && !$item['visible'])
{
unset($items[$i]);
continue;
}
if(!isset($item['label']))
$item['label']='';
if($this->encodeLabel)
$items[$i]['label']=CHtml::encode($item['label']);
$hasActiveChild=false;
if(isset($item['items']))
{
$items[$i]['items']=$this->normalizeItems($item['items'],$route,$hasActiveChild);
if(empty($items[$i]['items']) && $this->hideEmptyItems)
{
unset($items[$i]['items']);
if(!isset($item['url']))
{
unset($items[$i]);
continue;
}
}
}
if(!isset($item['active']))
{
if($this->activateParents && $hasActiveChild || $this->activateItems && $this->isItemActive($item,$route))
$active=$items[$i]['active']=true;
else
$items[$i]['active']=false;
}
else if($item['active'])
$active=true;
}
return array_values($items);
}
protected function isItemActive($item,$route)
{
if(isset($item['url']) && is_array($item['url']) && !strcasecmp(trim($item['url'][0],'/'),$route))
{
unset($item['url']['#']);
if(count($item['url'])>1)
{
foreach(array_splice($item['url'],1) as $name=>$value)
{
if(!isset($_GET[$name]) || $_GET[$name]!=$value)
return false;
}
}
return true;
}
return false;
}
}