<?php
class CPortlet extends CWidget
{
public $tagName='div';
public $htmlOptions=array('class'=>'portlet');
public $title;
public $decorationCssClass='portlet-decoration';
public $titleCssClass='portlet-title';
public $contentCssClass='portlet-content';
public $hideOnEmpty=true;
private $_openTag;
public function init()
{
ob_start();
ob_implicit_flush(false);
$this->htmlOptions['id']=$this->getId();
echo CHtml::openTag($this->tagName,$this->htmlOptions)."\n";
$this->renderDecoration();
echo "<div class=\"{$this->contentCssClass}\">\n";
$this->_openTag=ob_get_contents();
ob_clean();
}
public function run()
{
$this->renderContent();
$content=ob_get_clean();
if($this->hideOnEmpty && trim($content)==='')
return;
echo $this->_openTag;
echo $content;
echo "</div>\n";
echo CHtml::closeTag($this->tagName);
}
protected function renderDecoration()
{
if($this->title!==null)
{
echo "<div class=\"{$this->decorationCssClass}\">\n";
echo "<div class=\"{$this->titleCssClass}\">{$this->title}</div>\n";
echo "</div>\n";
}
}
protected function renderContent()
{
}
}