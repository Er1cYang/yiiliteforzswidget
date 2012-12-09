<?php
Yii::import('CHtml',true);
class CErrorHandler extends CApplicationComponent
{
public $maxSourceLines=25;
public $maxTraceSourceLines = 10;
public $adminInfo='the webmaster';
public $discardOutput=true;
public $errorAction;
private $_error;
public function handle($event)
{
$event->handled=true;
if($this->discardOutput)
{
$gzHandler=false;
foreach(ob_list_handlers() as $h)
{
if(strpos($h,'gzhandler')!==false)
$gzHandler=true;
}
for($level=ob_get_level();$level>0;--$level)
{
if(!@ob_end_clean())
ob_clean();
}
if($gzHandler && !headers_sent() && ob_list_handlers()===array())
{
if(function_exists('header_remove'))//php >= 5.3
{
header_remove('Vary');
header_remove('Content-Encoding');
}
else
{
header('Vary:');
header('Content-Encoding:');
}
}
}
if($event instanceof CExceptionEvent)
$this->handleException($event->exception);
else//CErrorEvent
$this->handleError($event);
}
public function getError()
{
return $this->_error;
}
protected function handleException($exception)
{
$app=Yii::app();
if($app instanceof CWebApplication)
{
if(($trace=$this->getExactTrace($exception))===null)
{
$fileName=$exception->getFile();
$errorLine=$exception->getLine();
}
else
{
$fileName=$trace['file'];
$errorLine=$trace['line'];
}
$trace = $exception->getTrace();
foreach($trace as $i=>$t)
{
if(!isset($t['file']))
$trace[$i]['file']='unknown';
if(!isset($t['line']))
$trace[$i]['line']=0;
if(!isset($t['function']))
$trace[$i]['function']='unknown';
unset($trace[$i]['object']);
}
$this->_error=$data=array(
'code'=>($exception instanceof CHttpException)?$exception->statusCode:500,
'type'=>get_class($exception),
'errorCode'=>$exception->getCode(),
'message'=>$exception->getMessage(),
'file'=>$fileName,
'line'=>$errorLine,
'trace'=>$exception->getTraceAsString(),
'traces'=>$trace,
);
if(!headers_sent())
header("HTTP/1.0 {$data['code']} ".$this->getHttpHeader($data['code'], get_class($exception)));
if($exception instanceof CHttpException || !YII_DEBUG)
$this->render('error',$data);
else
{
if($this->isAjaxRequest())
$app->displayException($exception);
else
$this->render('exception',$data);
}
}
else
$app->displayException($exception);
}
protected function handleError($event)
{
$trace=debug_backtrace();
if(count($trace)>3)
$trace=array_slice($trace,3);
$traceString='';
foreach($trace as $i=>$t)
{
if(!isset($t['file']))
$trace[$i]['file']='unknown';
if(!isset($t['line']))
$trace[$i]['line']=0;
if(!isset($t['function']))
$trace[$i]['function']='unknown';
$traceString.="#$i {$trace[$i]['file']}({$trace[$i]['line']}): ";
if(isset($t['object']) && is_object($t['object']))
$traceString.=get_class($t['object']).'->';
$traceString.="{$trace[$i]['function']}()\n";
unset($trace[$i]['object']);
}
$app=Yii::app();
if($app instanceof CWebApplication)
{
switch($event->code)
{
case E_WARNING:
$type = 'PHP warning';
break;
case E_NOTICE:
$type = 'PHP notice';
break;
case E_USER_ERROR:
$type = 'User error';
break;
case E_USER_WARNING:
$type = 'User warning';
break;
case E_USER_NOTICE:
$type = 'User notice';
break;
case E_RECOVERABLE_ERROR:
$type = 'Recoverable error';
break;
default:
$type = 'PHP error';
}
$this->_error=$data=array(
'code'=>500,
'type'=>$type,
'message'=>$event->message,
'file'=>$event->file,
'line'=>$event->line,
'trace'=>$traceString,
'traces'=>$trace,
);
if(!headers_sent())
header("HTTP/1.0 500 Internal Server Error");
if($this->isAjaxRequest())
$app->displayError($event->code,$event->message,$event->file,$event->line);
else if(YII_DEBUG)
$this->render('exception',$data);
else
$this->render('error',$data);
}
else
$app->displayError($event->code,$event->message,$event->file,$event->line);
}
protected function isAjaxRequest()
{
return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH']==='XMLHttpRequest';
}
protected function getExactTrace($exception)
{
$traces=$exception->getTrace();
foreach($traces as $trace)
{
if(isset($trace['function']) && ($trace['function']==='__get' || $trace['function']==='__set'))
return $trace;
}
return null;
}
protected function render($view,$data)
{
if($view==='error' && $this->errorAction!==null)
Yii::app()->runController($this->errorAction);
else
{
$data['version']=$this->getVersionInfo();
$data['time']=time();
$data['admin']=$this->adminInfo;
include($this->getViewFile($view,$data['code']));
}
}
protected function getViewFile($view,$code)
{
$viewPaths=array(
Yii::app()->getTheme()===null ? null :  Yii::app()->getTheme()->getSystemViewPath(),
Yii::app() instanceof CWebApplication ? Yii::app()->getSystemViewPath() : null,
YII_PATH.DIRECTORY_SEPARATOR.'views',
);
foreach($viewPaths as $i=>$viewPath)
{
if($viewPath!==null)
{
$viewFile=$this->getViewFileInternal($viewPath,$view,$code,$i===2?'en_us':null);
if(is_file($viewFile))
return $viewFile;
}
}
}
protected function getViewFileInternal($viewPath,$view,$code,$srcLanguage=null)
{
$app=Yii::app();
if($view==='error')
{
$viewFile=$app->findLocalizedFile($viewPath.DIRECTORY_SEPARATOR."error{$code}.php",$srcLanguage);
if(!is_file($viewFile))
$viewFile=$app->findLocalizedFile($viewPath.DIRECTORY_SEPARATOR.'error.php',$srcLanguage);
}
else
$viewFile=$viewPath.DIRECTORY_SEPARATOR."exception.php";
return $viewFile;
}
protected function getVersionInfo()
{
if(YII_DEBUG)
{
$version='<a href="http://www.yiiframework.com/">Yii Framework</a>/'.Yii::getVersion();
if(isset($_SERVER['SERVER_SOFTWARE']))
$version=$_SERVER['SERVER_SOFTWARE'].' '.$version;
}
else
$version='';
return $version;
}
protected function argumentsToString($args)
{
$count=0;
$isAssoc=$args!==array_values($args);
foreach($args as $key=>$value)
{
$count++;
if($count>=5)
{
if($count>5)
unset($args[$key]);
else
$args[$key]='...';
continue;
}
if(is_object($value))
$args[$key] = get_class($value);
else if(is_bool($value))
$args[$key] = $value ? 'true' : 'false';
else if(is_string($value))
{
if(strlen($value)>64)
$args[$key] = '"'.substr($value,0,64).'..."';
else
$args[$key] = '"'.$value.'"';
}
else if(is_array($value))
$args[$key] = 'array('.$this->argumentsToString($value).')';
else if($value===null)
$args[$key] = 'null';
else if(is_resource($value))
$args[$key] = 'resource';
if(is_string($key))
{
$args[$key] = '"'.$key.'"=>'.$args[$key];
}
else if($isAssoc)
{
$args[$key] = $key.'=>'.$args[$key];
}
}
$out = implode(", ", $args);
return $out;
}
protected function isCoreCode($trace)
{
if(isset($trace['file']))
{
$systemPath=realpath(dirname(__FILE__).'/..');
return $trace['file']==='unknown' || strpos(realpath($trace['file']),$systemPath.DIRECTORY_SEPARATOR)===0;
}
return false;
}
protected function renderSourceCode($file,$errorLine,$maxLines)
{
$errorLine--;//adjust line number to 0-based from 1-based
if($errorLine<0 || ($lines=@file($file))===false || ($lineCount=count($lines))<=$errorLine)
return '';
$halfLines=(int)($maxLines/2);
$beginLine=$errorLine-$halfLines>0 ? $errorLine-$halfLines:0;
$endLine=$errorLine+$halfLines<$lineCount?$errorLine+$halfLines:$lineCount-1;
$lineNumberWidth=strlen($endLine+1);
$output='';
for($i=$beginLine;$i<=$endLine;++$i)
{
$isErrorLine = $i===$errorLine;
$code=sprintf("<span class=\"ln".($isErrorLine?' error-ln':'')."\">%0{$lineNumberWidth}d</span>%s",$i+1,CHtml::encode(str_replace("\t",'    ',$lines[$i])));
if(!$isErrorLine)
$output.=$code;
else
$output.='<span class="error">'.$code.'</span>';
}
return '<div class="code"><pre>'.$output.'</pre></div>';
}
protected function getHttpHeader($httpCode, $replacement='')
{
$httpCodes = array(
100=>'Continue',
101=>'Switching Protocols',
102=>'Processing',
118=>'Connection timed out',
200=>'OK',
201=>'Created',
202=>'Accepted',
203=>'Non-Authoritative',
204=>'No Content',
205=>'Reset Content',
206=>'Partial Content',
207=>'Multi-Status',
210=>'Content Different',
300=>'Multiple Choices',
301=>'Moved Permanently',
302=>'Found',
303=>'See Other',
304=>'Not Modified',
305=>'Use Proxy',
307=>'Temporary Redirect',
310=>'Too many Redirect',
400=>'Bad Request',
401=>'Unauthorized',
402=>'Payment Required',
403=>'Forbidden',
404=>'Not Found',
405=>'Method Not Allowed',
406=>'Not Acceptable',
407=>'Proxy Authentication Required',
408=>'Request Time-out',
409=>'Conflict',
410=>'Gone',
411=>'Length Required',
412=>'Precondition Failed',
413=>'Request Entity Too Large',
414=>'Request-URI Too Long',
415=>'Unsupported Media Type',
416=>'Requested range unsatisfiable',
417=>'Expectation failed',
418=>'I’m a teapot',
422=>'Unprocessable entity',
423=>'Locked',
424=>'Method failure',
425=>'Unordered Collection',
426=>'Upgrade Required',
449=>'Retry With',
450=>'Blocked by Windows Parental Controls',
500=>'Internal Server Error',
501=>'Not Implemented',
502=>'Bad Gateway ou Proxy Error',
503=>'Service Unavailable',
504=>'Gateway Time-out',
505=>'HTTP Version not supported',
507=>'Insufficient storage',
509=>'Bandwidth Limit Exceeded',
);
if(isset($httpCodes[$httpCode]))
return $httpCodes[$httpCode];
else
return $replacement;
}
}
