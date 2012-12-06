<?php
if(!class_exists('HTMLPurifier_Bootstrap',false))
{
require_once(Yii::getPathOfAlias('system.vendors.htmlpurifier').DIRECTORY_SEPARATOR.'HTMLPurifier.standalone.php');
HTMLPurifier_Bootstrap::registerAutoload();
}
class CHtmlPurifier extends COutputProcessor
{
public $options=null;
public function processOutput($output)
{
$output=$this->purify($output);
parent::processOutput($output);
}
public function purify($content)
{
$purifier=new HTMLPurifier($this->options);
$purifier->config->set('Cache.SerializerPath',Yii::app()->getRuntimePath());
return $purifier->purify($content);
}
}
