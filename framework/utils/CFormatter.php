<?php
class CFormatter extends CApplicationComponent
{
private $_htmlPurifier;
public $dateFormat='Y/m/d';
public $timeFormat='h:i:s A';
public $datetimeFormat='Y/m/d h:i:s A';
public $numberFormat=array('decimals'=>null, 'decimalSeparator'=>null, 'thousandSeparator'=>null);
public $booleanFormat=array('No','Yes');
public $sizeFormat=array(
'base'=>1024,
'decimals'=>2,
);
public function __call($name,$parameters)
{
if(method_exists($this,'format'.$name))
return call_user_func_array(array($this,'format'.$name),$parameters);
else
return parent::__call($name,$parameters);
}
public function format($value,$type)
{
$method='format'.$type;
if(method_exists($this,$method))
return $this->$method($value);
else
throw new CException(Yii::t('yii','Unknown type "{type}".',array('{type}'=>$type)));
}
public function formatRaw($value)
{
return $value;
}
public function formatText($value)
{
return CHtml::encode($value);
}
public function formatNtext($value)
{
return nl2br(CHtml::encode($value));
}
public function formatHtml($value)
{
return $this->getHtmlPurifier()->purify($value);
}
public function formatDate($value)
{
return date($this->dateFormat,$value);
}
public function formatTime($value)
{
return date($this->timeFormat,$value);
}
public function formatDatetime($value)
{
return date($this->datetimeFormat,$value);
}
public function formatBoolean($value)
{
return $value ? $this->booleanFormat[1] : $this->booleanFormat[0];
}
public function formatEmail($value)
{
return CHtml::mailto($value);
}
public function formatImage($value)
{
return CHtml::image($value);
}
public function formatUrl($value)
{
$url=$value;
if(strpos($url,'http://')!==0 && strpos($url,'https://')!==0)
$url='http://'.$url;
return CHtml::link(CHtml::encode($value),$url);
}
public function formatNumber($value)
{
return number_format($value,$this->numberFormat['decimals'],$this->numberFormat['decimalSeparator'],$this->numberFormat['thousandSeparator']);
}
public function getHtmlPurifier()
{
if($this->_htmlPurifier===null)
$this->_htmlPurifier=new CHtmlPurifier;
return $this->_htmlPurifier;
}
public function formatSize($value,$verbose=false)
{
$base=$this->sizeFormat['base'];
for($i=0; $base<=$value && $i<5; $i++)
$value=$value/$base;
$value=round($value, $this->sizeFormat['decimals']);
switch($i)
{
case 0:
return $verbose ? Yii::t('size_units', '{n} Bytes', $value) : Yii::t('size_units', '{n} B', $value);
case 1:
return $verbose ? Yii::t('size_units', '{n} KiloBytes', $value) : Yii::t('size_units', '{n} KB', $value);
case 2:
return $verbose ? Yii::t('size_units', '{n} MegaBytes', $value) : Yii::t('size_units', '{n} MB', $value);
case 3:
return $verbose ? Yii::t('size_units', '{n} GigaBytes', $value) : Yii::t('size_units', '{n} GB', $value);
default:
return $verbose ? Yii::t('size_units', '{n} TeraBytes', $value) : Yii::t('size_units', '{n} TB', $value);
}
}
}
