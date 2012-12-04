<?php
class CHttpCookie extends CComponent
{
public $name;
public $value='';
public $domain='';
public $expire=0;
public $path='/';
public $secure=false;
public $httpOnly=false;
public function __construct($name,$value,$options=array())
{
$this->name=$name;
$this->value=$value;
$this->configure($options);
}
public function configure($options=array())
{
foreach($options as $name=>$value)
{
if($name==='name'||$name==='value')
continue;
$this->$name=$value;
}
}
public function __toString()
{
return (string)$this->value;
}
}
