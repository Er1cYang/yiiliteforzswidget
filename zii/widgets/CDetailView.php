<?php
class CDetailView extends CWidget
{
private $_formatter;
public $data;
public $attributes;
public $nullDisplay;
public $tagName='table';
public $itemTemplate="<tr class=\"{class}\"><th>{label}</th><td>{value}</td></tr>\n";
public $itemCssClass=array('odd','even');
public $htmlOptions=array('class'=>'detail-view');
public $baseScriptUrl;
public $cssFile;
public function init()
{
if($this->data===null)
throw new CException(Yii::t('zii','Please specify the "data" property.'));
if($this->attributes===null)
{
if($this->data instanceof CModel)
$this->attributes=$this->data->attributeNames();
else if(is_array($this->data))
$this->attributes=array_keys($this->data);
else
throw new CException(Yii::t('zii','Please specify the "attributes" property.'));
}
if($this->nullDisplay===null)
$this->nullDisplay='<span class="null">'.Yii::t('zii','Not set').'</span>';
$this->htmlOptions['id']=$this->getId();
if($this->baseScriptUrl===null)
$this->baseScriptUrl=Yii::app()->getAssetManager()->publish(Yii::getPathOfAlias('zii.widgets.assets')).'/detailview';
if($this->cssFile!==false)
{
if($this->cssFile===null)
$this->cssFile=$this->baseScriptUrl.'/styles.css';
Yii::app()->getClientScript()->registerCssFile($this->cssFile);
}
}
public function run()
{
$formatter=$this->getFormatter();
if ($this->tagName!==null)
echo CHtml::openTag($this->tagName,$this->htmlOptions);
$i=0;
$n=is_array($this->itemCssClass) ? count($this->itemCssClass) : 0;
foreach($this->attributes as $attribute)
{
if(is_string($attribute))
{
if(!preg_match('/^([\w\.]+)(:(\w*))?(:(.*))?$/',$attribute,$matches))
throw new CException(Yii::t('zii','The attribute must be specified in the format of "Name:Type:Label", where "Type" and "Label" are optional.'));
$attribute=array(
'name'=>$matches[1],
'type'=>isset($matches[3]) ? $matches[3] : 'text',
);
if(isset($matches[5]))
$attribute['label']=$matches[5];
}
if(isset($attribute['visible']) && !$attribute['visible'])
continue;
$tr=array('{label}'=>'', '{class}'=>$n ? $this->itemCssClass[$i%$n] : '');
if(isset($attribute['cssClass']))
$tr['{class}']=$attribute['cssClass'].' '.($n ? $tr['{class}'] : '');
if(isset($attribute['label']))
$tr['{label}']=$attribute['label'];
else if(isset($attribute['name']))
{
if($this->data instanceof CModel)
$tr['{label}']=$this->data->getAttributeLabel($attribute['name']);
else
$tr['{label}']=ucwords(trim(strtolower(str_replace(array('-','_','.'),' ',preg_replace('/(?<![A-Z])[A-Z]/', ' \0', $attribute['name'])))));
}
if(!isset($attribute['type']))
$attribute['type']='text';
if(isset($attribute['value']))
$value=$attribute['value'];
else if(isset($attribute['name']))
$value=CHtml::value($this->data,$attribute['name']);
else
$value=null;
$tr['{value}']=$value===null ? $this->nullDisplay : $formatter->format($value,$attribute['type']);
$this->renderItem($attribute, $tr);
$i++;
}
if ($this->tagName!==null)
echo CHtml::closeTag($this->tagName);
}
protected function renderItem($options,$templateData)
{
echo strtr(isset($options['template']) ? $options['template'] : $this->itemTemplate,$templateData);
}
public function getFormatter()
{
if($this->_formatter===null)
$this->_formatter=Yii::app()->format;
return $this->_formatter;
}
public function setFormatter($value)
{
$this->_formatter=$value;
}
}
