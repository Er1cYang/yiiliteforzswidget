<?php
class CJavaScriptExpression
{
public $code;
public function __construct($code)
{
if(!is_string($code))
throw new CException('Value passed to CJavaScriptExpression should be a string.');
if(strpos($code, 'js:')===0)
$code=substr($code,3);
$this->code=$code;
}
public function __toString()
{
return $this->code;
}
}