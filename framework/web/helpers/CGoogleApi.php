<?php
class CGoogleApi
{
public static $bootstrapUrl='//www.google.com/jsapi';
public static function init($apiKey=null)
{
if($apiKey===null)
return CHtml::scriptFile(self::$bootstrapUrl);
else
return CHtml::scriptFile(self::$bootstrapUrl.'?key='.$apiKey);
}
public static function load($name,$version='1',$options=array())
{
if(empty($options))
return "google.load(\"{$name}\",\"{$version}\");";
else
return "google.load(\"{$name}\",\"{$version}\",".CJavaScript::encode($options).");";
}
public static function register($name,$version='1',$options=array(),$apiKey=null)
{
$cs=Yii::app()->getClientScript();
$url=$apiKey===null?self::$bootstrapUrl:self::$bootstrapUrl.'?key='.$apiKey;
$cs->registerScriptFile($url);
$js=self::load($name,$version,$options);
$cs->registerScript($name,$js,CClientScript::POS_HEAD);
}
}