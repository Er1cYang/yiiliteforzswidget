<?php
class CTimestamp
{
public static function getDayofWeek($year, $month, $day)
{
if ($year <= 1582)
{
if ($year < 1582 ||
($year == 1582 && ($month < 10 || ($month == 10 && $day < 15))))
{
$greg_correction = 3;
}
else
{
$greg_correction = 0;
}
}
else
{
$greg_correction = 0;
}
if($month > 2)
$month -= 2;
else
{
$month += 10;
$year--;
}
$day =  floor((13 * $month - 1) / 5) +
$day + ($year % 100) +
floor(($year % 100) / 4) +
floor(($year / 100) / 4) - 2 *
floor($year / 100) + 77 + $greg_correction;
return $day - 7 * floor($day / 7);
}
public static function isLeapYear($year)
{
$year = self::digitCheck($year);
if ($year % 4 != 0)
return false;
if ($year % 400 == 0)
return true;
else if ($year > 1582 && $year % 100 == 0 )
return false;
return true;
}
protected static function digitCheck($y)
{
if ($y < 100){
$yr = (integer) date("Y");
$century = (integer) ($yr /100);
if ($yr%100 > 50) {
$c1 = $century + 1;
$c0 = $century;
} else {
$c1 = $century;
$c0 = $century - 1;
}
$c1 *= 100;
if (($y + $c1) < $yr+30) $y = $y + $c1;
else $y = $y + $c0*100;
}
return $y;
}
public static function get4DigitYear($y)
{
return self::digitCheck($y);
}
public static function getGMTDiff()
{
static $TZ;
if (isset($TZ)) return $TZ;
$TZ = mktime(0,0,0,1,2,1970) - gmmktime(0,0,0,1,2,1970);
return $TZ;
}
public static function getDate($d=false,$fast=false,$gmt=false)
{
if($d===false)
$d=time();
if($gmt)
{
$tz = date_default_timezone_get();
date_default_timezone_set('GMT');
$result = getdate($d);
date_default_timezone_set($tz);
}
else
{
$result = getdate($d);
}
return $result;
}
public static function isValidDate($y,$m,$d)
{
return checkdate($m, $d, $y);
}
public static function isValidTime($h,$m,$s,$hs24=true)
{
if($hs24 && ($h < 0 || $h > 23) || !$hs24 && ($h < 1 || $h > 12)) return false;
if($m > 59 || $m < 0) return false;
if($s > 59 || $s < 0) return false;
return true;
}
public static function formatDate($fmt,$d=false,$is_gmt=false)
{
if ($d === false)
return ($is_gmt)? @gmdate($fmt): @date($fmt);
if ((abs($d) <= 0x7FFFFFFF))
{
if ($d >= 0)
return ($is_gmt)? @gmdate($fmt,$d): @date($fmt,$d);
}
$_day_power = 86400;
$arr = self::getDate($d,true,$is_gmt);
$year = $arr['year'];
$month = $arr['mon'];
$day = $arr['mday'];
$hour = $arr['hours'];
$min = $arr['minutes'];
$secs = $arr['seconds'];
$max = strlen($fmt);
$dates = '';
for ($i=0; $i < $max; $i++)
{
switch($fmt[$i])
{
case 'T': $dates .= date('T');break;
case 'L': $dates .= $arr['leap'] ? '1' : '0'; break;
case 'r': // Thu, 21 Dec 2000 16:01:07 +0200
$dates .= gmdate('D',$_day_power*(3+self::getDayOfWeek($year,$month,$day))).', '
. ($day<10?'0'.$day:$day) . ' '.date('M',mktime(0,0,0,$month,2,1971)).' '.$year.' ';
if ($hour < 10) $dates .= '0'.$hour; else $dates .= $hour;
if ($min < 10) $dates .= ':0'.$min; else $dates .= ':'.$min;
if ($secs < 10) $dates .= ':0'.$secs; else $dates .= ':'.$secs;
$gmt = self::getGMTDiff();
$dates .= sprintf(' %s%04d',($gmt<=0)?'+':'-',abs($gmt)/36);
break;
case 'Y': $dates .= $year; break;
case 'y': $dates .= substr($year,strlen($year)-2,2); break;
case 'm': if ($month<10) $dates .= '0'.$month; else $dates .= $month; break;
case 'Q': $dates .= ($month+3)>>2; break;
case 'n': $dates .= $month; break;
case 'M': $dates .= date('M',mktime(0,0,0,$month,2,1971)); break;
case 'F': $dates .= date('F',mktime(0,0,0,$month,2,1971)); break;
case 't': $dates .= $arr['ndays']; break;
case 'z': $dates .= $arr['yday']; break;
case 'w': $dates .= self::getDayOfWeek($year,$month,$day); break;
case 'l': $dates .= gmdate('l',$_day_power*(3+self::getDayOfWeek($year,$month,$day))); break;
case 'D': $dates .= gmdate('D',$_day_power*(3+self::getDayOfWeek($year,$month,$day))); break;
case 'j': $dates .= $day; break;
case 'd': if ($day<10) $dates .= '0'.$day; else $dates .= $day; break;
case 'S':
$d10 = $day % 10;
if ($d10 == 1) $dates .= 'st';
else if ($d10 == 2 && $day != 12) $dates .= 'nd';
else if ($d10 == 3) $dates .= 'rd';
else $dates .= 'th';
break;
case 'Z':
$dates .= ($is_gmt) ? 0 : -self::getGMTDiff(); break;
case 'O':
$gmt = ($is_gmt) ? 0 : self::getGMTDiff();
$dates .= sprintf('%s%04d',($gmt<=0)?'+':'-',abs($gmt)/36);
break;
case 'H':
if ($hour < 10) $dates .= '0'.$hour;
else $dates .= $hour;
break;
case 'h':
if ($hour > 12) $hh = $hour - 12;
else {
if ($hour == 0) $hh = '12';
else $hh = $hour;
}
if ($hh < 10) $dates .= '0'.$hh;
else $dates .= $hh;
break;
case 'G':
$dates .= $hour;
break;
case 'g':
if ($hour > 12) $hh = $hour - 12;
else {
if ($hour == 0) $hh = '12';
else $hh = $hour;
}
$dates .= $hh;
break;
case 'i': if ($min < 10) $dates .= '0'.$min; else $dates .= $min; break;
case 'U': $dates .= $d; break;
case 's': if ($secs < 10) $dates .= '0'.$secs; else $dates .= $secs; break;
case 'a':
if ($hour>=12) $dates .= 'pm';
else $dates .= 'am';
break;
case 'A':
if ($hour>=12) $dates .= 'PM';
else $dates .= 'AM';
break;
default:
$dates .= $fmt[$i]; break;
case "\\":
$i++;
if ($i < $max) $dates .= $fmt[$i];
break;
}
}
return $dates;
}
public static function getTimestamp($hr,$min,$sec,$mon=false,$day=false,$year=false,$is_gmt=false)
{
if ($mon === false)
return $is_gmt? @gmmktime($hr,$min,$sec): @mktime($hr,$min,$sec);
return $is_gmt ? @gmmktime($hr,$min,$sec,$mon,$day,$year) : @mktime($hr,$min,$sec,$mon,$day,$year);
}
}
