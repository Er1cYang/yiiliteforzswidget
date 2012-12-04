<?php
abstract class CApplication extends CModule
{
public $name='My Application';
public $charset='UTF-8';
public $sourceLanguage='en_us';
private $_id;
private $_basePath;
private $_runtimePath;
private $_extensionPath;
private $_globalState;
private $_stateChanged;
private $_ended=false;
private $_language;
private $_homeUrl;
abstract public function processRequest();
public function __construct($config=null)
{
Yii::setApplication($this);
if(is_string($config))
$config=require($config);
if(isset($config['basePath']))
{
$this->setBasePath($config['basePath']);
unset($config['basePath']);
}
else
$this->setBasePath('protected');
Yii::setPathOfAlias('application',$this->getBasePath());
Yii::setPathOfAlias('webroot',dirname($_SERVER['SCRIPT_FILENAME']));
Yii::setPathOfAlias('ext',$this->getBasePath().DIRECTORY_SEPARATOR.'extensions');
$this->preinit();
$this->initSystemHandlers();
$this->registerCoreComponents();
$this->configure($config);
$this->attachBehaviors($this->behaviors);
$this->preloadComponents();
$this->init();
}
public function run()
{
if($this->hasEventHandler('onBeginRequest'))
$this->onBeginRequest(new CEvent($this));
$this->processRequest();
if($this->hasEventHandler('onEndRequest'))
$this->onEndRequest(new CEvent($this));
}
public function end($status=0, $exit=true)
{
if($this->hasEventHandler('onEndRequest'))
$this->onEndRequest(new CEvent($this));
if($exit)
exit($status);
}
public function onBeginRequest($event)
{
$this->raiseEvent('onBeginRequest',$event);
}
public function onEndRequest($event)
{
if(!$this->_ended)
{
$this->_ended=true;
$this->raiseEvent('onEndRequest',$event);
}
}
public function getId()
{
if($this->_id!==null)
return $this->_id;
else
return $this->_id=sprintf('%x',crc32($this->getBasePath().$this->name));
}
public function setId($id)
{
$this->_id=$id;
}
public function getBasePath()
{
return $this->_basePath;
}
public function setBasePath($path)
{
if(($this->_basePath=realpath($path))===false || !is_dir($this->_basePath))
throw new CException(Yii::t('yii','Application base path "{path}" is not a valid directory.',
array('{path}'=>$path)));
}
public function getRuntimePath()
{
if($this->_runtimePath!==null)
return $this->_runtimePath;
else
{
$this->setRuntimePath($this->getBasePath().DIRECTORY_SEPARATOR.'runtime');
return $this->_runtimePath;
}
}
public function setRuntimePath($path)
{
if(($runtimePath=realpath($path))===false || !is_dir($runtimePath) || !is_writable($runtimePath))
throw new CException(Yii::t('yii','Application runtime path "{path}" is not valid. Please make sure it is a directory writable by the Web server process.',
array('{path}'=>$path)));
$this->_runtimePath=$runtimePath;
}
public function getExtensionPath()
{
return Yii::getPathOfAlias('ext');
}
public function setExtensionPath($path)
{
if(($extensionPath=realpath($path))===false || !is_dir($extensionPath))
throw new CException(Yii::t('yii','Extension path "{path}" does not exist.',
array('{path}'=>$path)));
Yii::setPathOfAlias('ext',$extensionPath);
}
public function getLanguage()
{
return $this->_language===null ? $this->sourceLanguage : $this->_language;
}
public function setLanguage($language)
{
$this->_language=$language;
}
public function getTimeZone()
{
return date_default_timezone_get();
}
public function setTimeZone($value)
{
date_default_timezone_set($value);
}
public function findLocalizedFile($srcFile,$srcLanguage=null,$language=null)
{
if($srcLanguage===null)
$srcLanguage=$this->sourceLanguage;
if($language===null)
$language=$this->getLanguage();
if($language===$srcLanguage)
return $srcFile;
$desiredFile=dirname($srcFile).DIRECTORY_SEPARATOR.$language.DIRECTORY_SEPARATOR.basename($srcFile);
return is_file($desiredFile) ? $desiredFile : $srcFile;
}
public function getLocale($localeID=null)
{
return CLocale::getInstance($localeID===null?$this->getLanguage():$localeID);
}
public function getLocaleDataPath()
{
return CLocale::$dataPath===null ? Yii::getPathOfAlias('system.i18n.data') : CLocale::$dataPath;
}
public function setLocaleDataPath($value)
{
CLocale::$dataPath=$value;
}
public function getNumberFormatter()
{
return $this->getLocale()->getNumberFormatter();
}
public function getDateFormatter()
{
return $this->getLocale()->getDateFormatter();
}
public function getDb()
{
return $this->getComponent('db');
}
public function getErrorHandler()
{
return $this->getComponent('errorHandler');
}
public function getSecurityManager()
{
return $this->getComponent('securityManager');
}
public function getStatePersister()
{
return $this->getComponent('statePersister');
}
public function getCache()
{
return $this->getComponent('cache');
}
public function getCoreMessages()
{
return $this->getComponent('coreMessages');
}
public function getMessages()
{
return $this->getComponent('messages');
}
public function getRequest()
{
return $this->getComponent('request');
}
public function getUrlManager()
{
return $this->getComponent('urlManager');
}
public function getController()
{
return null;
}
public function createUrl($route,$params=array(),$ampersand='&')
{
return $this->getUrlManager()->createUrl($route,$params,$ampersand);
}
public function createAbsoluteUrl($route,$params=array(),$schema='',$ampersand='&')
{
$url=$this->createUrl($route,$params,$ampersand);
if(strpos($url,'http')===0)
return $url;
else
return $this->getRequest()->getHostInfo($schema).$url;
}
public function getBaseUrl($absolute=false)
{
return $this->getRequest()->getBaseUrl($absolute);
}
public function getHomeUrl()
{
if($this->_homeUrl===null)
{
if($this->getUrlManager()->showScriptName)
return $this->getRequest()->getScriptUrl();
else
return $this->getRequest()->getBaseUrl().'/';
}
else
return $this->_homeUrl;
}
public function setHomeUrl($value)
{
$this->_homeUrl=$value;
}
public function getGlobalState($key,$defaultValue=null)
{
if($this->_globalState===null)
$this->loadGlobalState();
if(isset($this->_globalState[$key]))
return $this->_globalState[$key];
else
return $defaultValue;
}
public function setGlobalState($key,$value,$defaultValue=null)
{
if($this->_globalState===null)
$this->loadGlobalState();
$changed=$this->_stateChanged;
if($value===$defaultValue)
{
if(isset($this->_globalState[$key]))
{
unset($this->_globalState[$key]);
$this->_stateChanged=true;
}
}
else if(!isset($this->_globalState[$key]) || $this->_globalState[$key]!==$value)
{
$this->_globalState[$key]=$value;
$this->_stateChanged=true;
}
if($this->_stateChanged!==$changed)
$this->attachEventHandler('onEndRequest',array($this,'saveGlobalState'));
}
public function clearGlobalState($key)
{
$this->setGlobalState($key,true,true);
}
public function loadGlobalState()
{
$persister=$this->getStatePersister();
if(($this->_globalState=$persister->load())===null)
$this->_globalState=array();
$this->_stateChanged=false;
$this->detachEventHandler('onEndRequest',array($this,'saveGlobalState'));
}
public function saveGlobalState()
{
if($this->_stateChanged)
{
$this->_stateChanged=false;
$this->detachEventHandler('onEndRequest',array($this,'saveGlobalState'));
$this->getStatePersister()->save($this->_globalState);
}
}
public function handleException($exception)
{
restore_error_handler();
restore_exception_handler();
$category='exception.'.get_class($exception);
if($exception instanceof CHttpException)
$category.='.'.$exception->statusCode;
$message=$exception->__toString();
if(isset($_SERVER['REQUEST_URI']))
$message.="\nREQUEST_URI=".$_SERVER['REQUEST_URI'];
if(isset($_SERVER['HTTP_REFERER']))
$message.="\nHTTP_REFERER=".$_SERVER['HTTP_REFERER'];
$message.="\n---";
Yii::log($message,CLogger::LEVEL_ERROR,$category);
try
{
$event=new CExceptionEvent($this,$exception);
$this->onException($event);
if(!$event->handled)
{
if(($handler=$this->getErrorHandler())!==null)
$handler->handle($event);
else
$this->displayException($exception);
}
}
catch(Exception $e)
{
$this->displayException($e);
}
try
{
$this->end(1);
}
catch(Exception $e)
{
$msg = get_class($e).': '.$e->getMessage().' ('.$e->getFile().':'.$e->getLine().")\n";
$msg .= $e->getTraceAsString()."\n";
$msg .= "Previous exception:\n";
$msg .= get_class($exception).': '.$exception->getMessage().' ('.$exception->getFile().':'.$exception->getLine().")\n";
$msg .= $exception->getTraceAsString()."\n";
$msg .= '$_SERVER='.var_export($_SERVER,true);
error_log($msg);
exit(1);
}
}
public function handleError($code,$message,$file,$line)
{
if($code & error_reporting())
{
restore_error_handler();
restore_exception_handler();
$log="$message ($file:$line)\nStack trace:\n";
$trace=debug_backtrace();
if(count($trace)>3)
$trace=array_slice($trace,3);
foreach($trace as $i=>$t)
{
if(!isset($t['file']))
$t['file']='unknown';
if(!isset($t['line']))
$t['line']=0;
if(!isset($t['function']))
$t['function']='unknown';
$log.="#$i {$t['file']}({$t['line']}): ";
if(isset($t['object']) && is_object($t['object']))
$log.=get_class($t['object']).'->';
$log.="{$t['function']}()\n";
}
if(isset($_SERVER['REQUEST_URI']))
$log.='REQUEST_URI='.$_SERVER['REQUEST_URI'];
Yii::log($log,CLogger::LEVEL_ERROR,'php');
try
{
Yii::import('CErrorEvent',true);
$event=new CErrorEvent($this,$code,$message,$file,$line);
$this->onError($event);
if(!$event->handled)
{
if(($handler=$this->getErrorHandler())!==null)
$handler->handle($event);
else
$this->displayError($code,$message,$file,$line);
}
}
catch(Exception $e)
{
$this->displayException($e);
}
try
{
$this->end(1);
}
catch(Exception $e)
{
$msg = get_class($e).': '.$e->getMessage().' ('.$e->getFile().':'.$e->getLine().")\n";
$msg .= $e->getTraceAsString()."\n";
$msg .= "Previous error:\n";
$msg .= $log."\n";
$msg .= '$_SERVER='.var_export($_SERVER,true);
error_log($msg);
exit(1);
}
}
}
public function onException($event)
{
$this->raiseEvent('onException',$event);
}
public function onError($event)
{
$this->raiseEvent('onError',$event);
}
public function displayError($code,$message,$file,$line)
{
if(YII_DEBUG)
{
echo "<h1>PHP Error [$code]</h1>\n";
echo "<p>$message ($file:$line)</p>\n";
echo '<pre>';
$trace=debug_backtrace();
if(count($trace)>3)
$trace=array_slice($trace,3);
foreach($trace as $i=>$t)
{
if(!isset($t['file']))
$t['file']='unknown';
if(!isset($t['line']))
$t['line']=0;
if(!isset($t['function']))
$t['function']='unknown';
echo "#$i {$t['file']}({$t['line']}): ";
if(isset($t['object']) && is_object($t['object']))
echo get_class($t['object']).'->';
echo "{$t['function']}()\n";
}
echo '</pre>';
}
else
{
echo "<h1>PHP Error [$code]</h1>\n";
echo "<p>$message</p>\n";
}
}
public function displayException($exception)
{
if(YII_DEBUG)
{
echo '<h1>'.get_class($exception)."</h1>\n";
echo '<p>'.$exception->getMessage().' ('.$exception->getFile().':'.$exception->getLine().')</p>';
echo '<pre>'.$exception->getTraceAsString().'</pre>';
}
else
{
echo '<h1>'.get_class($exception)."</h1>\n";
echo '<p>'.$exception->getMessage().'</p>';
}
}
protected function initSystemHandlers()
{
if(YII_ENABLE_EXCEPTION_HANDLER)
set_exception_handler(array($this,'handleException'));
if(YII_ENABLE_ERROR_HANDLER)
set_error_handler(array($this,'handleError'),error_reporting());
}
protected function registerCoreComponents()
{
$components=array(
'coreMessages'=>array(
'class'=>'CPhpMessageSource',
'language'=>'en_us',
'basePath'=>YII_PATH.DIRECTORY_SEPARATOR.'messages',
),
'db'=>array(
'class'=>'CDbConnection',
),
'messages'=>array(
'class'=>'CPhpMessageSource',
),
'errorHandler'=>array(
'class'=>'CErrorHandler',
),
'securityManager'=>array(
'class'=>'CSecurityManager',
),
'statePersister'=>array(
'class'=>'CStatePersister',
),
'urlManager'=>array(
'class'=>'CUrlManager',
),
'request'=>array(
'class'=>'CHttpRequest',
),
'format'=>array(
'class'=>'CFormatter',
),
);
$this->setComponents($components);
}
}
