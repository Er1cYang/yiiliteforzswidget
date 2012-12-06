<?php
class CAutoComplete extends CInputWidget
{
public $textArea=false;
public $data;
public $url='';
public $cssFile;
public $minChars;
public $delay;
public $cacheLength;
public $matchSubset;
public $matchCase;
public $matchContains;
public $mustMatch;
public $selectFirst;
public $extraParams;
public $formatItem;
public $formatMatch;
public $formatResult;
public $multiple;
public $multipleSeparator;
public $width;
public $autoFill;
public $max;
public $highlight;
public $scroll;
public $scrollHeight;
public $inputClass;
public $resultsClass;
public $loadingClass;
public $options=array();
public $methodChain;
public function init()
{
list($name,$id)=$this->resolveNameID();
if(isset($this->htmlOptions['id']))
$id=$this->htmlOptions['id'];
else
$this->htmlOptions['id']=$id;
if(isset($this->htmlOptions['name']))
$name=$this->htmlOptions['name'];
$this->registerClientScript();
if($this->hasModel())
{
$field=$this->textArea ? 'activeTextArea' : 'activeTextField';
echo CHtml::$field($this->model,$this->attribute,$this->htmlOptions);
}
else
{
$field=$this->textArea ? 'textArea' : 'textField';
echo CHtml::$field($name,$this->value,$this->htmlOptions);
}
}
public function registerClientScript()
{
$id=$this->htmlOptions['id'];
$acOptions=$this->getClientOptions();
$options=$acOptions===array()?'{}' : CJavaScript::encode($acOptions);
$cs=Yii::app()->getClientScript();
$cs->registerCoreScript('autocomplete');
if($this->data!==null)
$data=CJavaScript::encode($this->data);
else
{
$url=CHtml::normalizeUrl($this->url);
$data='"'.$url.'"';
}
$cs->registerScript('Yii.CAutoComplete#'.$id,"jQuery(\"#{$id}\").legacyautocomplete($data,{$options}){$this->methodChain};");
if($this->cssFile!==false)
self::registerCssFile($this->cssFile);
}
public static function registerCssFile($url=null)
{
$cs=Yii::app()->getClientScript();
if($url===null)
$url=$cs->getCoreScriptUrl().'/autocomplete/jquery.autocomplete.css';
$cs->registerCssFile($url);
}
protected function getClientOptions()
{
static $properties=array(
'minChars', 'delay', 'cacheLength', 'matchSubset',
'matchCase', 'matchContains', 'mustMatch', 'selectFirst',
'extraParams', 'multiple', 'multipleSeparator', 'width',
'autoFill', 'max', 'scroll', 'scrollHeight', 'inputClass',
'resultsClass', 'loadingClass');
static $functions=array('formatItem', 'formatMatch', 'formatResult', 'highlight');
$options=$this->options;
foreach($properties as $property)
{
if($this->$property!==null)
$options[$property]=$this->$property;
}
foreach($functions as $func)
{
if($this->$func!==null)
{
if($this->$func instanceof CJavaScriptExpression)
$options[$func]=$this->$func;
else
$options[$func]=new CJavaScriptExpression($this->$func);
}
}
return $options;
}
}
