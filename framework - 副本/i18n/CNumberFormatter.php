<?php
class CNumberFormatter extends CComponent
{
private $_locale;
private $_formats=array();
public function __construct($locale)
{
if(is_string($locale))
$this->_locale=CLocale::getInstance($locale);
else
$this->_locale=$locale;
}
public function format($pattern,$value,$currency=null)
{
$format=$this->parseFormat($pattern);
$result=$this->formatNumber($format,$value);
if($currency===null)
return $result;
else if(($symbol=$this->_locale->getCurrencySymbol($currency))===null)
$symbol=$currency;
return str_replace('¤',$symbol,$result);
}
public function formatCurrency($value,$currency)
{
return $this->format($this->_locale->getCurrencyFormat(),$value,$currency);
}
public function formatPercentage($value)
{
return $this->format($this->_locale->getPercentFormat(),$value);
}
public function formatDecimal($value)
{
return $this->format($this->_locale->getDecimalFormat(),$value);
}
protected function formatNumber($format,$value)
{
$negative=$value<0;
$value=abs($value*$format['multiplier']);
if($format['maxDecimalDigits']>=0)
$value=round($value,$format['maxDecimalDigits']);
$value="$value";
if(($pos=strpos($value,'.'))!==false)
{
$integer=substr($value,0,$pos);
$decimal=substr($value,$pos+1);
}
else
{
$integer=$value;
$decimal='';
}
if($format['decimalDigits']>strlen($decimal))
$decimal=str_pad($decimal,$format['decimalDigits'],'0');
if(strlen($decimal)>0)
$decimal=$this->_locale->getNumberSymbol('decimal').$decimal;
$integer=str_pad($integer,$format['integerDigits'],'0',STR_PAD_LEFT);
if($format['groupSize1']>0 && strlen($integer)>$format['groupSize1'])
{
$str1=substr($integer,0,-$format['groupSize1']);
$str2=substr($integer,-$format['groupSize1']);
$size=$format['groupSize2']>0?$format['groupSize2']:$format['groupSize1'];
$str1=str_pad($str1,(int)((strlen($str1)+$size-1)/$size)*$size,' ',STR_PAD_LEFT);
$integer=ltrim(implode($this->_locale->getNumberSymbol('group'),str_split($str1,$size))).$this->_locale->getNumberSymbol('group').$str2;
}
if($negative)
$number=$format['negativePrefix'].$integer.$decimal.$format['negativeSuffix'];
else
$number=$format['positivePrefix'].$integer.$decimal.$format['positiveSuffix'];
return strtr($number,array('%'=>$this->_locale->getNumberSymbol('percentSign'),'‰'=>$this->_locale->getNumberSymbol('perMille')));
}
protected function parseFormat($pattern)
{
if(isset($this->_formats[$pattern]))
return $this->_formats[$pattern];
$format=array();
$patterns=explode(';',$pattern);
$format['positivePrefix']=$format['positiveSuffix']=$format['negativePrefix']=$format['negativeSuffix']='';
if(preg_match('/^(.*?)[#,\.0]+(.*?)$/',$patterns[0],$matches))
{
$format['positivePrefix']=$matches[1];
$format['positiveSuffix']=$matches[2];
}
if(isset($patterns[1]) && preg_match('/^(.*?)[#,\.0]+(.*?)$/',$patterns[1],$matches))  // with a negative pattern
{
$format['negativePrefix']=$matches[1];
$format['negativeSuffix']=$matches[2];
}
else
{
$format['negativePrefix']=$this->_locale->getNumberSymbol('minusSign').$format['positivePrefix'];
$format['negativeSuffix']=$format['positiveSuffix'];
}
$pat=$patterns[0];
if(strpos($pat,'%')!==false)
$format['multiplier']=100;
else if(strpos($pat,'‰')!==false)
$format['multiplier']=1000;
else
$format['multiplier']=1;
if(($pos=strpos($pat,'.'))!==false)
{
if(($pos2=strrpos($pat,'0'))>$pos)
$format['decimalDigits']=$pos2-$pos;
else
$format['decimalDigits']=0;
if(($pos3=strrpos($pat,'#'))>=$pos2)
$format['maxDecimalDigits']=$pos3-$pos;
else
$format['maxDecimalDigits']=$format['decimalDigits'];
$pat=substr($pat,0,$pos);
}
else   // no decimal part
{
$format['decimalDigits']=0;
$format['maxDecimalDigits']=0;
}
$p=str_replace(',','',$pat);
if(($pos=strpos($p,'0'))!==false)
$format['integerDigits']=strrpos($p,'0')-$pos+1;
else
$format['integerDigits']=0;
$p=str_replace('#','0',$pat);
if(($pos=strrpos($pat,','))!==false)
{
$format['groupSize1']=strrpos($p,'0')-$pos;
if(($pos2=strrpos(substr($p,0,$pos),','))!==false)
$format['groupSize2']=$pos-$pos2-1;
else
$format['groupSize2']=0;
}
else
$format['groupSize1']=$format['groupSize2']=0;
return $this->_formats[$pattern]=$format;
}
}