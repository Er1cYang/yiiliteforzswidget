<?php
class CHttpRequest extends CApplicationComponent
{
public $enableCookieValidation=false;
public $enableCsrfValidation=false;
public $csrfTokenName='YII_CSRF_TOKEN';
public $csrfCookie;
private $_requestUri;
private $_pathInfo;
private $_scriptFile;
private $_scriptUrl;
private $_hostInfo;
private $_baseUrl;
private $_cookies;
private $_preferredLanguage;
private $_csrfToken;
private $_deleteParams;
private $_putParams;
public function init()
{
parent::init();
$this->normalizeRequest();
}
protected function normalizeRequest()
{
if(function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc())
{
if(isset($_GET))
$_GET=$this->stripSlashes($_GET);
if(isset($_POST))
$_POST=$this->stripSlashes($_POST);
if(isset($_REQUEST))
$_REQUEST=$this->stripSlashes($_REQUEST);
if(isset($_COOKIE))
$_COOKIE=$this->stripSlashes($_COOKIE);
}
if($this->enableCsrfValidation)
Yii::app()->attachEventHandler('onBeginRequest',array($this,'validateCsrfToken'));
}
public function stripSlashes(&$data)
{
return is_array($data)?array_map(array($this,'stripSlashes'),$data):stripslashes($data);
}
public function getParam($name,$defaultValue=null)
{
return isset($_GET[$name]) ? $_GET[$name] : (isset($_POST[$name]) ? $_POST[$name] : $defaultValue);
}
public function getQuery($name,$defaultValue=null)
{
return isset($_GET[$name]) ? $_GET[$name] : $defaultValue;
}
public function getPost($name,$defaultValue=null)
{
return isset($_POST[$name]) ? $_POST[$name] : $defaultValue;
}
public function getDelete($name,$defaultValue=null)
{
if($this->getIsDeleteViaPostRequest())
return $this->getPost($name, $defaultValue);
if($this->_deleteParams===null)
$this->_deleteParams=$this->getIsDeleteRequest() ? $this->getRestParams() : array();
return isset($this->_deleteParams[$name]) ? $this->_deleteParams[$name] : $defaultValue;
}
public function getPut($name,$defaultValue=null)
{
if($this->getIsPutViaPostRequest())
return $this->getPost($name, $defaultValue);
if($this->_putParams===null)
$this->_putParams=$this->getIsPutRequest() ? $this->getRestParams() : array();
return isset($this->_putParams[$name]) ? $this->_putParams[$name] : $defaultValue;
}
protected function getRestParams()
{
$result=array();
if(function_exists('mb_parse_str'))
mb_parse_str(file_get_contents('php://input'), $result);
else
parse_str(file_get_contents('php://input'), $result);
return $result;
}
public function getUrl()
{
return $this->getRequestUri();
}
public function getHostInfo($schema='')
{
if($this->_hostInfo===null)
{
if($secure=$this->getIsSecureConnection())
$http='https';
else
$http='http';
if(isset($_SERVER['HTTP_HOST']))
$this->_hostInfo=$http.'://'.$_SERVER['HTTP_HOST'];
else
{
$this->_hostInfo=$http.'://'.$_SERVER['SERVER_NAME'];
$port=$secure ? $this->getSecurePort() : $this->getPort();
if(($port!==80 && !$secure) || ($port!==443 && $secure))
$this->_hostInfo.=':'.$port;
}
}
if($schema!=='')
{
$secure=$this->getIsSecureConnection();
if($secure && $schema==='https' || !$secure && $schema==='http')
return $this->_hostInfo;
$port=$schema==='https' ? $this->getSecurePort() : $this->getPort();
if($port!==80 && $schema==='http' || $port!==443 && $schema==='https')
$port=':'.$port;
else
$port='';
$pos=strpos($this->_hostInfo,':');
return $schema.substr($this->_hostInfo,$pos,strcspn($this->_hostInfo,':',$pos+1)+1).$port;
}
else
return $this->_hostInfo;
}
public function setHostInfo($value)
{
$this->_hostInfo=rtrim($value,'/');
}
public function getBaseUrl($absolute=false)
{
if($this->_baseUrl===null)
$this->_baseUrl=rtrim(dirname($this->getScriptUrl()),'\\/');
return $absolute ? $this->getHostInfo() . $this->_baseUrl : $this->_baseUrl;
}
public function setBaseUrl($value)
{
$this->_baseUrl=$value;
}
public function getScriptUrl()
{
if($this->_scriptUrl===null)
{
$scriptName=basename($_SERVER['SCRIPT_FILENAME']);
if(basename($_SERVER['SCRIPT_NAME'])===$scriptName)
$this->_scriptUrl=$_SERVER['SCRIPT_NAME'];
else if(basename($_SERVER['PHP_SELF'])===$scriptName)
$this->_scriptUrl=$_SERVER['PHP_SELF'];
else if(isset($_SERVER['ORIG_SCRIPT_NAME']) && basename($_SERVER['ORIG_SCRIPT_NAME'])===$scriptName)
$this->_scriptUrl=$_SERVER['ORIG_SCRIPT_NAME'];
else if(($pos=strpos($_SERVER['PHP_SELF'],'/'.$scriptName))!==false)
$this->_scriptUrl=substr($_SERVER['SCRIPT_NAME'],0,$pos).'/'.$scriptName;
else if(isset($_SERVER['DOCUMENT_ROOT']) && strpos($_SERVER['SCRIPT_FILENAME'],$_SERVER['DOCUMENT_ROOT'])===0)
$this->_scriptUrl=str_replace('\\','/',str_replace($_SERVER['DOCUMENT_ROOT'],'',$_SERVER['SCRIPT_FILENAME']));
else
throw new CException(Yii::t('yii','CHttpRequest is unable to determine the entry script URL.'));
}
return $this->_scriptUrl;
}
public function setScriptUrl($value)
{
$this->_scriptUrl='/'.trim($value,'/');
}
public function getPathInfo()
{
if($this->_pathInfo===null)
{
$pathInfo=$this->getRequestUri();
if(($pos=strpos($pathInfo,'?'))!==false)
$pathInfo=substr($pathInfo,0,$pos);
$pathInfo=$this->decodePathInfo($pathInfo);
$scriptUrl=$this->getScriptUrl();
$baseUrl=$this->getBaseUrl();
if(strpos($pathInfo,$scriptUrl)===0)
$pathInfo=substr($pathInfo,strlen($scriptUrl));
else if($baseUrl==='' || strpos($pathInfo,$baseUrl)===0)
$pathInfo=substr($pathInfo,strlen($baseUrl));
else if(strpos($_SERVER['PHP_SELF'],$scriptUrl)===0)
$pathInfo=substr($_SERVER['PHP_SELF'],strlen($scriptUrl));
else
throw new CException(Yii::t('yii','CHttpRequest is unable to determine the path info of the request.'));
$this->_pathInfo=trim($pathInfo,'/');
}
return $this->_pathInfo;
}
protected function decodePathInfo($pathInfo)
{
$pathInfo = urldecode($pathInfo);
if(preg_match('%^(?:
[\x09\x0A\x0D\x20-\x7E]            # ASCII
| [\xC2-\xDF][\x80-\xBF]             # non-overlong 2-byte
| \xE0[\xA0-\xBF][\x80-\xBF]         # excluding overlongs
| [\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}  # straight 3-byte
| \xED[\x80-\x9F][\x80-\xBF]         # excluding surrogates
| \xF0[\x90-\xBF][\x80-\xBF]{2}      # planes 1-3
| [\xF1-\xF3][\x80-\xBF]{3}          # planes 4-15
| \xF4[\x80-\x8F][\x80-\xBF]{2}      # plane 16
)*$%xs', $pathInfo))
{
return $pathInfo;
}
else
{
return utf8_encode($pathInfo);
}
}
public function getRequestUri()
{
if($this->_requestUri===null)
{
if(isset($_SERVER['HTTP_X_REWRITE_URL'])) // IIS
$this->_requestUri=$_SERVER['HTTP_X_REWRITE_URL'];
else if(isset($_SERVER['REQUEST_URI']))
{
$this->_requestUri=$_SERVER['REQUEST_URI'];
if(!empty($_SERVER['HTTP_HOST']))
{
if(strpos($this->_requestUri,$_SERVER['HTTP_HOST'])!==false)
$this->_requestUri=preg_replace('/^\w+:\/\/[^\/]+/','',$this->_requestUri);
}
else
$this->_requestUri=preg_replace('/^(http|https):\/\/[^\/]+/i','',$this->_requestUri);
}
else if(isset($_SERVER['ORIG_PATH_INFO']))  // IIS 5.0 CGI
{
$this->_requestUri=$_SERVER['ORIG_PATH_INFO'];
if(!empty($_SERVER['QUERY_STRING']))
$this->_requestUri.='?'.$_SERVER['QUERY_STRING'];
}
else
throw new CException(Yii::t('yii','CHttpRequest is unable to determine the request URI.'));
}
return $this->_requestUri;
}
public function getQueryString()
{
return isset($_SERVER['QUERY_STRING'])?$_SERVER['QUERY_STRING']:'';
}
public function getIsSecureConnection()
{
return !empty($_SERVER['HTTPS']) && strcasecmp($_SERVER['HTTPS'],'off');
}
public function getRequestType()
{
if(isset($_POST['_method']))
return strtoupper($_POST['_method']);
return strtoupper(isset($_SERVER['REQUEST_METHOD'])?$_SERVER['REQUEST_METHOD']:'GET');
}
public function getIsPostRequest()
{
return isset($_SERVER['REQUEST_METHOD']) && !strcasecmp($_SERVER['REQUEST_METHOD'],'POST');
}
public function getIsDeleteRequest()
{
return (isset($_SERVER['REQUEST_METHOD']) && !strcasecmp($_SERVER['REQUEST_METHOD'],'DELETE')) || $this->getIsDeleteViaPostRequest();
}
protected function getIsDeleteViaPostRequest()
{
return isset($_POST['_method']) && !strcasecmp($_POST['_method'],'DELETE');
}
public function getIsPutRequest()
{
return (isset($_SERVER['REQUEST_METHOD']) && !strcasecmp($_SERVER['REQUEST_METHOD'],'PUT')) || $this->getIsPutViaPostRequest();
}
protected function getIsPutViaPostRequest()
{
return isset($_POST['_method']) && !strcasecmp($_POST['_method'],'PUT');
}
public function getIsAjaxRequest()
{
return isset($_SERVER['HTTP_X_REQUESTED_WITH']) && $_SERVER['HTTP_X_REQUESTED_WITH']==='XMLHttpRequest';
}
public function getIsFlashRequest()
{
return isset($_SERVER['HTTP_USER_AGENT']) && (stripos($_SERVER['HTTP_USER_AGENT'],'Shockwave')!==false || stripos($_SERVER['HTTP_USER_AGENT'],'Flash')!==false);
}
public function getServerName()
{
return $_SERVER['SERVER_NAME'];
}
public function getServerPort()
{
return $_SERVER['SERVER_PORT'];
}
public function getUrlReferrer()
{
return isset($_SERVER['HTTP_REFERER'])?$_SERVER['HTTP_REFERER']:null;
}
public function getUserAgent()
{
return isset($_SERVER['HTTP_USER_AGENT'])?$_SERVER['HTTP_USER_AGENT']:null;
}
public function getUserHostAddress()
{
return isset($_SERVER['REMOTE_ADDR'])?$_SERVER['REMOTE_ADDR']:'127.0.0.1';
}
public function getUserHost()
{
return isset($_SERVER['REMOTE_HOST'])?$_SERVER['REMOTE_HOST']:null;
}
public function getScriptFile()
{
if($this->_scriptFile!==null)
return $this->_scriptFile;
else
return $this->_scriptFile=realpath($_SERVER['SCRIPT_FILENAME']);
}
public function getBrowser($userAgent=null)
{
return get_browser($userAgent,true);
}
public function getAcceptTypes()
{
return isset($_SERVER['HTTP_ACCEPT'])?$_SERVER['HTTP_ACCEPT']:null;
}
private $_port;
public function getPort()
{
if($this->_port===null)
$this->_port=!$this->getIsSecureConnection() && isset($_SERVER['SERVER_PORT']) ? (int)$_SERVER['SERVER_PORT'] : 80;
return $this->_port;
}
public function setPort($value)
{
$this->_port=(int)$value;
$this->_hostInfo=null;
}
private $_securePort;
public function getSecurePort()
{
if($this->_securePort===null)
$this->_securePort=$this->getIsSecureConnection() && isset($_SERVER['SERVER_PORT']) ? (int)$_SERVER['SERVER_PORT'] : 443;
return $this->_securePort;
}
public function setSecurePort($value)
{
$this->_securePort=(int)$value;
$this->_hostInfo=null;
}
public function getCookies()
{
if($this->_cookies!==null)
return $this->_cookies;
else
return $this->_cookies=new CCookieCollection($this);
}
public function redirect($url,$terminate=true,$statusCode=302)
{
if(strpos($url,'/')===0)
$url=$this->getHostInfo().$url;
header('Location: '.$url, true, $statusCode);
if($terminate)
Yii::app()->end();
}
public function getPreferredLanguage()
{
if($this->_preferredLanguage===null)
{
if(isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) && ($n=preg_match_all('/([\w\-_]+)\s*(;\s*q\s*=\s*(\d*\.\d*))?/',$_SERVER['HTTP_ACCEPT_LANGUAGE'],$matches))>0)
{
$languages=array();
for($i=0;$i<$n;++$i)
$languages[$matches[1][$i]]=empty($matches[3][$i]) ? 1.0 : floatval($matches[3][$i]);
arsort($languages);
foreach($languages as $language=>$pref)
return $this->_preferredLanguage=CLocale::getCanonicalID($language);
}
return $this->_preferredLanguage=false;
}
return $this->_preferredLanguage;
}
public function sendFile($fileName,$content,$mimeType=null,$terminate=true)
{
if($mimeType===null)
{
if(($mimeType=CFileHelper::getMimeTypeByExtension($fileName))===null)
$mimeType='text/plain';
}
header('Pragma: public');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header("Content-type: $mimeType");
if(ob_get_length()===false)
header('Content-Length: '.(function_exists('mb_strlen') ? mb_strlen($content,'8bit') : strlen($content)));
header("Content-Disposition: attachment; filename=\"$fileName\"");
header('Content-Transfer-Encoding: binary');
if($terminate)
{
Yii::app()->end(0,false);
echo $content;
exit(0);
}
else
echo $content;
}
public function xSendFile($filePath, $options=array())
{
if(!isset($options['forceDownload']) || $options['forceDownload'])
$disposition='attachment';
else
$disposition='inline';
if(!isset($options['saveName']))
$options['saveName']=basename($filePath);
if(!isset($options['mimeType']))
{
if(($options['mimeType']=CFileHelper::getMimeTypeByExtension($filePath))===null)
$options['mimeType']='text/plain';
}
if(!isset($options['xHeader']))
$options['xHeader']='X-Sendfile';
if($options['mimeType'] !== null)
header('Content-type: '.$options['mimeType']);
header('Content-Disposition: '.$disposition.'; filename="'.$options['saveName'].'"');
if(isset($options['addHeaders']))
{
foreach($options['addHeaders'] as $header=>$value)
header($header.': '.$value);
}
header(trim($options['xHeader']).': '.$filePath);
if(!isset($options['terminate']) || $options['terminate'])
Yii::app()->end();
}
public function getCsrfToken()
{
if($this->_csrfToken===null)
{
$cookie=$this->getCookies()->itemAt($this->csrfTokenName);
if(!$cookie || ($this->_csrfToken=$cookie->value)==null)
{
$cookie=$this->createCsrfCookie();
$this->_csrfToken=$cookie->value;
$this->getCookies()->add($cookie->name,$cookie);
}
}
return $this->_csrfToken;
}
protected function createCsrfCookie()
{
$cookie=new CHttpCookie($this->csrfTokenName,sha1(uniqid(mt_rand(),true)));
if(is_array($this->csrfCookie))
{
foreach($this->csrfCookie as $name=>$value)
$cookie->$name=$value;
}
return $cookie;
}
public function validateCsrfToken($event)
{
if($this->getIsPostRequest())
{
$cookies=$this->getCookies();
if($cookies->contains($this->csrfTokenName) && isset($_POST[$this->csrfTokenName]))
{
$tokenFromCookie=$cookies->itemAt($this->csrfTokenName)->value;
$tokenFromPost=$_POST[$this->csrfTokenName];
$valid=$tokenFromCookie===$tokenFromPost;
}
else
$valid=false;
if(!$valid)
throw new CHttpException(400,Yii::t('yii','The CSRF token could not be verified.'));
}
}
}
class CCookieCollection extends CMap
{
private $_request;
private $_initialized=false;
public function __construct(CHttpRequest $request)
{
$this->_request=$request;
$this->copyfrom($this->getCookies());
$this->_initialized=true;
}
public function getRequest()
{
return $this->_request;
}
protected function getCookies()
{
$cookies=array();
if($this->_request->enableCookieValidation)
{
$sm=Yii::app()->getSecurityManager();
foreach($_COOKIE as $name=>$value)
{
if(is_string($value) && ($value=$sm->validateData($value))!==false)
$cookies[$name]=new CHttpCookie($name,@unserialize($value));
}
}
else
{
foreach($_COOKIE as $name=>$value)
$cookies[$name]=new CHttpCookie($name,$value);
}
return $cookies;
}
public function add($name,$cookie)
{
if($cookie instanceof CHttpCookie)
{
$this->remove($name);
parent::add($name,$cookie);
if($this->_initialized)
$this->addCookie($cookie);
}
else
throw new CException(Yii::t('yii','CHttpCookieCollection can only hold CHttpCookie objects.'));
}
public function remove($name,$options=array())
{
if(($cookie=parent::remove($name))!==null)
{
if($this->_initialized)
{
$cookie->configure($options);
$this->removeCookie($cookie);
}
}
return $cookie;
}
protected function addCookie($cookie)
{
$value=$cookie->value;
if($this->_request->enableCookieValidation)
$value=Yii::app()->getSecurityManager()->hashData(serialize($value));
if(version_compare(PHP_VERSION,'5.2.0','>='))
setcookie($cookie->name,$value,$cookie->expire,$cookie->path,$cookie->domain,$cookie->secure,$cookie->httpOnly);
else
setcookie($cookie->name,$value,$cookie->expire,$cookie->path,$cookie->domain,$cookie->secure);
}
protected function removeCookie($cookie)
{
if(version_compare(PHP_VERSION,'5.2.0','>='))
setcookie($cookie->name,'',0,$cookie->path,$cookie->domain,$cookie->secure,$cookie->httpOnly);
else
setcookie($cookie->name,'',0,$cookie->path,$cookie->domain,$cookie->secure);
}
}
