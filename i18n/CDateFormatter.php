<?php
class CDateFormatter extends CComponent
{
private static $_formatters=array(
'G'=>'formatEra',
'y'=>'formatYear',
'M'=>'formatMonth',
'L'=>'formatMonth',
'd'=>'formatDay',
'h'=>'formatHour12',
'H'=>'formatHour24',
'm'=>'formatMinutes',
's'=>'formatSeconds',
'E'=>'formatDayInWeek',
'c'=>'formatDayInWeek',
'e'=>'formatDayInWeek',
'D'=>'formatDayInYear',
'F'=>'formatDayInMonth',
'w'=>'formatWeekInYear',
'W'=>'formatWeekInMonth',
'a'=>'formatPeriod',
'k'=>'formatHourInDay',
'K'=>'formatHourInPeriod',
'z'=>'formatTimeZone',
'Z'=>'formatTimeZone',
'v'=>'formatTimeZone',
);
private $_locale;
public function __construct($locale)
{
if(is_string($locale))
$this->_locale=CLocale::getInstance($locale);
else
$this->_locale=$locale;
}
public function format($pattern,$time)
{
if($time===null)
return null;
if(is_string($time))
{
if(ctype_digit($time))
$time=(int)$time;
else
$time=strtotime($time);
}
$date=CTimestamp::getDate($time,false,false);
$tokens=$this->parseFormat($pattern);
foreach($tokens as &$token)
{
if(is_array($token)) // a callback: method name, sub-pattern
$token=$this->{$token[0]}($token[1],$date);
}
return implode('',$tokens);
}
public function formatDateTime($timestamp,$dateWidth='medium',$timeWidth='medium')
{
if(!empty($dateWidth))
$date=$this->format($this->_locale->getDateFormat($dateWidth),$timestamp);
if(!empty($timeWidth))
$time=$this->format($this->_locale->getTimeFormat($timeWidth),$timestamp);
if(isset($date) && isset($time))
{
$dateTimePattern=$this->_locale->getDateTimeFormat();
return strtr($dateTimePattern,array('{0}'=>$time,'{1}'=>$date));
}
else if(isset($date))
return $date;
else if(isset($time))
return $time;
}
protected function parseFormat($pattern)
{
static $formats=array();  // cache
if(isset($formats[$pattern]))
return $formats[$pattern];
$tokens=array();
$n=strlen($pattern);
$isLiteral=false;
$literal='';
for($i=0;$i<$n;++$i)
{
$c=$pattern[$i];
if($c==="'")
{
if($i<$n-1 && $pattern[$i+1]==="'")
{
$tokens[]="'";
$i++;
}
else if($isLiteral)
{
$tokens[]=$literal;
$literal='';
$isLiteral=false;
}
else
{
$isLiteral=true;
$literal='';
}
}
else if($isLiteral)
$literal.=$c;
else
{
for($j=$i+1;$j<$n;++$j)
{
if($pattern[$j]!==$c)
break;
}
$p=str_repeat($c,$j-$i);
if(isset(self::$_formatters[$c]))
$tokens[]=array(self::$_formatters[$c],$p);
else
$tokens[]=$p;
$i=$j-1;
}
}
if($literal!=='')
$tokens[]=$literal;
return $formats[$pattern]=$tokens;
}
protected function formatYear($pattern,$date)
{
$year=$date['year'];
if($pattern==='yy')
return str_pad($year%100,2,'0',STR_PAD_LEFT);
else
return str_pad($year,strlen($pattern),'0',STR_PAD_LEFT);
}
protected function formatMonth($pattern,$date)
{
$month=$date['mon'];
switch($pattern)
{
case 'M':
return $month;
case 'MM':
return str_pad($month,2,'0',STR_PAD_LEFT);
case 'MMM':
return $this->_locale->getMonthName($month,'abbreviated');
case 'MMMM':
return $this->_locale->getMonthName($month,'wide');
case 'MMMMM':
return $this->_locale->getMonthName($month,'narrow');
case 'L':
return $month;
case 'LL':
return str_pad($month,2,'0',STR_PAD_LEFT);
case 'LLL':
return $this->_locale->getMonthName($month,'abbreviated', true);
case 'LLLL':
return $this->_locale->getMonthName($month,'wide', true);
case 'LLLLL':
return $this->_locale->getMonthName($month,'narrow', true);
default:
throw new CException(Yii::t('yii','The pattern for month must be "M", "MM", "MMM", "MMMM", "L", "LL", "LLL" or "LLLL".'));
}
}
protected function formatDay($pattern,$date)
{
$day=$date['mday'];
if($pattern==='d')
return $day;
else if($pattern==='dd')
return str_pad($day,2,'0',STR_PAD_LEFT);
else
throw new CException(Yii::t('yii','The pattern for day of the month must be "d" or "dd".'));
}
protected function formatDayInYear($pattern,$date)
{
$day=$date['yday'];
if(($n=strlen($pattern))<=3)
return str_pad($day,$n,'0',STR_PAD_LEFT);
else
throw new CException(Yii::t('yii','The pattern for day in year must be "D", "DD" or "DDD".'));
}
protected function formatDayInMonth($pattern,$date)
{
if($pattern==='F')
return (int)(($date['mday']+6)/7);
else
throw new CException(Yii::t('yii','The pattern for day in month must be "F".'));
}
protected function formatDayInWeek($pattern,$date)
{
$day=$date['wday'];
switch($pattern)
{
case 'E':
case 'EE':
case 'EEE':
case 'eee':
return $this->_locale->getWeekDayName($day,'abbreviated');
case 'EEEE':
case 'eeee':
return $this->_locale->getWeekDayName($day,'wide');
case 'EEEEE':
case 'eeeee':
return $this->_locale->getWeekDayName($day,'narrow');
case 'e':
case 'ee':
case 'c':
return $day ? $day : 7;
case 'ccc':
return $this->_locale->getWeekDayName($day,'abbreviated',true);
case 'cccc':
return $this->_locale->getWeekDayName($day,'wide',true);
case 'ccccc':
return $this->_locale->getWeekDayName($day,'narrow',true);
default:
throw new CException(Yii::t('yii','The pattern for day of the week must be "E", "EE", "EEE", "EEEE", "EEEEE", "e", "ee", "eee", "eeee", "eeeee", "c", "cccc" or "ccccc".'));
}
}
protected function formatPeriod($pattern,$date)
{
if($pattern==='a')
{
if(intval($date['hours']/12))
return $this->_locale->getPMName();
else
return $this->_locale->getAMName();
}
else
throw new CException(Yii::t('yii','The pattern for AM/PM marker must be "a".'));
}
protected function formatHour24($pattern,$date)
{
$hour=$date['hours'];
if($pattern==='H')
return $hour;
else if($pattern==='HH')
return str_pad($hour,2,'0',STR_PAD_LEFT);
else
throw new CException(Yii::t('yii','The pattern for 24 hour format must be "H" or "HH".'));
}
protected function formatHour12($pattern,$date)
{
$hour=$date['hours'];
$hour=($hour==12|$hour==0)?12:($hour)%12;
if($pattern==='h')
return $hour;
else if($pattern==='hh')
return str_pad($hour,2,'0',STR_PAD_LEFT);
else
throw new CException(Yii::t('yii','The pattern for 12 hour format must be "h" or "hh".'));
}
protected function formatHourInDay($pattern,$date)
{
$hour=$date['hours']==0?24:$date['hours'];
if($pattern==='k')
return $hour;
else if($pattern==='kk')
return str_pad($hour,2,'0',STR_PAD_LEFT);
else
throw new CException(Yii::t('yii','The pattern for hour in day must be "k" or "kk".'));
}
protected function formatHourInPeriod($pattern,$date)
{
$hour=$date['hours']%12;
if($pattern==='K')
return $hour;
else if($pattern==='KK')
return str_pad($hour,2,'0',STR_PAD_LEFT);
else
throw new CException(Yii::t('yii','The pattern for hour in AM/PM must be "K" or "KK".'));
}
protected function formatMinutes($pattern,$date)
{
$minutes=$date['minutes'];
if($pattern==='m')
return $minutes;
else if($pattern==='mm')
return str_pad($minutes,2,'0',STR_PAD_LEFT);
else
throw new CException(Yii::t('yii','The pattern for minutes must be "m" or "mm".'));
}
protected function formatSeconds($pattern,$date)
{
$seconds=$date['seconds'];
if($pattern==='s')
return $seconds;
else if($pattern==='ss')
return str_pad($seconds,2,'0',STR_PAD_LEFT);
else
throw new CException(Yii::t('yii','The pattern for seconds must be "s" or "ss".'));
}
protected function formatWeekInYear($pattern,$date)
{
if($pattern==='w')
return @date('W',@mktime(0,0,0,$date['mon'],$date['mday'],$date['year']));
else
throw new CException(Yii::t('yii','The pattern for week in year must be "w".'));
}
protected function formatWeekInMonth($pattern,$date)
{
if($pattern==='W')
return @date('W',@mktime(0,0,0,$date['mon'], $date['mday'],$date['year']))-date('W', mktime(0,0,0,$date['mon'],1,$date['year']))+1;
else
throw new CException(Yii::t('yii','The pattern for week in month must be "W".'));
}
protected function formatTimeZone($pattern,$date)
{
if($pattern[0]==='z' || $pattern[0]==='v')
return @date('T', @mktime($date['hours'], $date['minutes'], $date['seconds'], $date['mon'], $date['mday'], $date['year']));
elseif($pattern[0]==='Z')
return @date('O', @mktime($date['hours'], $date['minutes'], $date['seconds'], $date['mon'], $date['mday'], $date['year']));
else
throw new CException(Yii::t('yii','The pattern for time zone must be "z" or "v".'));
}
protected function formatEra($pattern,$date)
{
$era=$date['year']>0 ? 1 : 0;
switch($pattern)
{
case 'G':
case 'GG':
case 'GGG':
return $this->_locale->getEraName($era,'abbreviated');
case 'GGGG':
return $this->_locale->getEraName($era,'wide');
case 'GGGGG':
return $this->_locale->getEraName($era,'narrow');
default:
throw new CException(Yii::t('yii','The pattern for era must be "G", "GG", "GGG", "GGGG" or "GGGGG".'));
}
}
}
