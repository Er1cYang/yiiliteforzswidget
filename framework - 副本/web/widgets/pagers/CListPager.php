<?php
class CListPager extends CBasePager
{
public $header;
public $footer;
public $promptText;
public $pageTextFormat;
public $htmlOptions=array();
public function init()
{
if($this->header===null)
$this->header=Yii::t('yii','Go to page: ');
if(!isset($this->htmlOptions['id']))
$this->htmlOptions['id']=$this->getId();
if($this->promptText!==null)
$this->htmlOptions['prompt']=$this->promptText;
if(!isset($this->htmlOptions['onchange']))
$this->htmlOptions['onchange']="if(this.value!='') {window.location=this.value;};";
}
public function run()
{
if(($pageCount=$this->getPageCount())<=1)
return;
$pages=array();
for($i=0;$i<$pageCount;++$i)
$pages[$this->createPageUrl($i)]=$this->generatePageText($i);
$selection=$this->createPageUrl($this->getCurrentPage());
echo $this->header;
echo CHtml::dropDownList($this->getId(),$selection,$pages,$this->htmlOptions);
echo $this->footer;
}
protected function generatePageText($page)
{
if($this->pageTextFormat!==null)
return sprintf($this->pageTextFormat,$page+1);
else
return $page+1;
}
}