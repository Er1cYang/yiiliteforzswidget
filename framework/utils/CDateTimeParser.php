<?php
class CDateTimeParser
{
public static function parse($value,$pattern='MM/dd/yyyy',$defaults=array())
{
$tokens=self::tokenize($pattern);
$i=0;
$n=strlen($value);
foreach($tokens as $token)
{
switch($token)
{
case 'yyyy':
{
if(($year=self::parseInteger($value,$i,4,4))===false)
return false;
$i+=4;
break;
}
case 'yy':
{
if(($year=self::parseInteger($value,$i,1,2))===false)
return false;
$i+=strlen($year);
break;
}
case 'MMM':
{
if(($month=self::parseShortMonth($value,$i))===false)
return false;
$i+=3;
break;
}
case 'MM':
{
if(($month=self::parseInteger($value,$i,2,2))===false)
return false;
$i+=2;
break;
}
case 'M':
{
if(($month=self::parseInteger($value,$i,1,2))===false)
return false;
$i+=strlen($month);
break;
}
case 'dd':
{
if(($day=self::parseInteger($value,$i,2,2))===false)
return false;
$i+=2;
break;
}
case 'd':
{
if(($day=self::parseInteger($value,$i,1,2))===false)
return false;
$i+=strlen($day);
break;
}
case 'h':
case 'H':
{
if(($hour=self::parseInteger($value,$i,1,2))===false)
return false;
$i+=strlen($hour);
break;
}
case 'hh':
case 'HH':
{
if(($hour=self::parseInteger($value,$i,2,2))===false)
return false;
$i+=2;
break;
}
case 'm':
{
if(($minute=self::parseInteger($value,$i,1,2))===false)
return false;
$i+=strlen($minute);
break;
}
case 'mm':
{
if(($minute=self::parseInteger($value,$i,2,2))===false)
return false;
$i+=2;
break;
}
case 's':
{
if(($second=self::parseInteger($value,$i,1,2))===false)
return false;
$i+=strlen($second);
break;
}
case 'ss':
{
if(($second=self::parseInteger($value,$i,2,2))===false)
return false;
$i+=2;
break;
}
case 'a':
{
if(($ampm=self::parseAmPm($value,$i))===false)
return false;
if(isset($hour))
{
if($hour==12 && $ampm==='am')
$hour=0;
else if($hour<12 && $ampm==='pm')
$hour+=12;
}
$i+=2;
break;
}
default:
{
$tn=strlen($token);
if($i>=$n || ($token{0}!='?' && substr($value,$i,$tn)!==$token))
return false;
$i+=$tn;
break;
}
}
}
if($i<$n)
return false;
if(!isset($year))
$year=isset($defaults['year']) ? $defaults['year'] : date('Y');
if(!isset($month))
$month=isset($defaults['month']) ? $defaults['month'] : date('n');
if(!isset($day))
$day=isset($defaults['day']) ? $defaults['day'] : date('j');
if(strlen($year)===2)
{
if($year>=70)
$year+=1900;
else
$year+=2000;
}
$year=(int)$year;
$month=(int)$month;
$day=(int)$day;
if(
!isset($hour) && !isset($minute) && !isset($second)
&& !isset($defaults['hour']) && !isset($defaults['minute']) && !isset($defaults['second'])
)
$hour=$minute=$second=0;
else
{
if(!isset($hour))
$hour=isset($defaults['hour']) ? $defaults['hour'] : date('H');
if(!isset($minute))
$minute=isset($defaults['minute']) ? $defaults['minute'] : date('i');
if(!isset($second))
$second=isset($defaults['second']) ? $defaults['second'] : date('s');
$hour=(int)$hour;
$minute=(int)$minute;
$second=(int)$second;
}
if(CTimestamp::isValidDate($year,$month,$day) && CTimestamp::isValidTime($hour,$minute,$second))
return CTimestamp::getTimestamp($hour,$minute,$second,$month,$day,$year);
else
return false;
}
private static function tokenize($pattern)
{
if(!($n=strlen($pattern)))
return array();
$tokens=array();
for($c0=$pattern[0],$start=0,$i=1;$i<$n;++$i)
{
if(($c=$pattern[$i])!==$c0)
{
$tokens[]=substr($pattern,$start,$i-$start);
$c0=$c;
$start=$i;
}
}
$tokens[]=substr($pattern,$start,$n-$start);
return $tokens;
}
protected static function parseInteger($value,$offset,$minLength,$maxLength)
{
for($len=$maxLength;$len>=$minLength;--$len)
{
$v=substr($value,$offset,$len);
if(ctype_digit($v) && strlen($v)>=$minLength)
return $v;
}
return false;
}
protected static function parseAmPm($value, $offset)
{
$v=strtolower(substr($value,$offset,2));
return $v==='am' || $v==='pm' ? $v : false;
}
protected static function parseShortMonth($value, $offset)
{
static $titles=array('jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec');
$v=array_search(strtolower(substr($value,$offset,3)), $titles);
return $v===false ? false : $v+1;
}
}
