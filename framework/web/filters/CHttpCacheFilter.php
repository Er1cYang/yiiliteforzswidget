<?php
class CHttpCacheFilter extends CFilter
{
public $lastModified;
public $lastModifiedExpression;
public $etagSeed;
public $etagSeedExpression;
public $cacheControl = 'max-age=3600, public';
public function preFilter($filterChain)
{
if(!in_array(Yii::app()->getRequest()->getRequestType(), array('GET', 'HEAD')))
return true;
$lastModified=$this->getLastModifiedValue();
$etag=$this->getEtagValue();
if($etag===false&&$lastModified===false)
return true;
if($etag)
header('ETag: '.$etag);
if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])&&isset($_SERVER['HTTP_IF_NONE_MATCH']))
{
if($this->checkLastModified($lastModified)&&$this->checkEtag($etag))
{
$this->send304Header();
$this->sendCacheControlHeader();
return false;
}
}
else if(isset($_SERVER['HTTP_IF_MODIFIED_SINCE']))
{
if($this->checkLastModified($lastModified))
{
$this->send304Header();
$this->sendCacheControlHeader();
return false;
}
}
else if(isset($_SERVER['HTTP_IF_NONE_MATCH']))
{
if($this->checkEtag($etag))
{
$this->send304Header();
$this->sendCacheControlHeader();
return false;
}
}
if($lastModified)
header('Last-Modified: '.date('r', $lastModified));
$this->sendCacheControlHeader();
return true;
}
protected function getLastModifiedValue()
{
if($this->lastModifiedExpression)
{
$value=$this->evaluateExpression($this->lastModifiedExpression);
if(is_numeric($value)&&$value==(int)$value)
return $value;
else if(($lastModified=strtotime($value))===false)
throw new CException(Yii::t('yii','Invalid expression for CHttpCacheFilter.lastModifiedExpression: The evaluation result "{value}" could not be understood by strtotime()',
array('{value}'=>$value)));
return $lastModified;
}
if($this->lastModified)
{
if(is_numeric($this->lastModified)&&$this->lastModified==(int)$this->lastModified)
return $this->lastModified;
else if(($lastModified=strtotime($this->lastModified))===false)
throw new CException(Yii::t('yii','CHttpCacheFilter.lastModified contained a value that could not be understood by strtotime()'));
return $lastModified;
}
return false;
}
protected function getEtagValue()
{
if($this->etagSeedExpression)
return $this->generateEtag($this->evaluateExpression($this->etagSeedExpression));
else if($this->etagSeed)
return $this->generateEtag($this->etagSeed);
return false;		
}
protected function checkEtag($etag)
{
return isset($_SERVER['HTTP_IF_NONE_MATCH'])&&$_SERVER['HTTP_IF_NONE_MATCH']==$etag;
}
protected function checkLastModified($lastModified)
{
return isset($_SERVER['HTTP_IF_MODIFIED_SINCE'])&&@strtotime($_SERVER['HTTP_IF_MODIFIED_SINCE'])>=$lastModified;
}
protected function send304Header()
{
header('HTTP/1.1 304 Not Modified');
}
protected function sendCacheControlHeader()
{
header('Cache-Control: '.$this->cacheControl, true);
}
protected function generateEtag($seed)
{
return '"'.base64_encode(sha1(serialize($seed), true)).'"';
}
}
