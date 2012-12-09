<?php
class CChoiceFormat
{
public static function format($messages, $number)
{
$n=preg_match_all('/\s*([^#]*)\s*#([^\|]*)\|/',$messages.'|',$matches);
if($n===0)
return $messages;
for($i=0;$i<$n;++$i)
{
$expression=$matches[1][$i];
$message=$matches[2][$i];
if($expression===(string)(int)$expression)
{
if($expression==$number)
return $message;
}
else if(self::evaluate(str_replace('n','$n',$expression),$number))
return $message;
}
return $message;//return the last choice
}
protected static function evaluate($expression,$n)
{
return @eval("return $expression;");
}
}