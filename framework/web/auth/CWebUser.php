<?php
class CWebUser extends CApplicationComponent implements IWebUser
{
const FLASH_KEY_PREFIX='Yii.CWebUser.flash.';
const FLASH_COUNTERS='Yii.CWebUser.flashcounters';
const STATES_VAR='__states';
const AUTH_TIMEOUT_VAR='__timeout';
public $allowAutoLogin=false;
public $guestName='Guest';
public $loginUrl=array('/site/login');
public $identityCookie;
public $authTimeout;
public $autoRenewCookie=false;
public $autoUpdateFlash=true;
public $loginRequiredAjaxResponse;
private $_keyPrefix;
private $_access=array();
public function __get($name)
{
if($this->hasState($name))
return $this->getState($name);
else
return parent::__get($name);
}
public function __set($name,$value)
{
if($this->hasState($name))
$this->setState($name,$value);
else
parent::__set($name,$value);
}
public function __isset($name)
{
if($this->hasState($name))
return $this->getState($name)!==null;
else
return parent::__isset($name);
}
public function __unset($name)
{
if($this->hasState($name))
$this->setState($name,null);
else
parent::__unset($name);
}
public function init()
{
parent::init();
Yii::app()->getSession()->open();
if($this->getIsGuest() && $this->allowAutoLogin)
$this->restoreFromCookie();
else if($this->autoRenewCookie && $this->allowAutoLogin)
$this->renewCookie();
if($this->autoUpdateFlash)
$this->updateFlash();
$this->updateAuthStatus();
}
public function login($identity,$duration=0)
{
$id=$identity->getId();
$states=$identity->getPersistentStates();
if($this->beforeLogin($id,$states,false))
{
$this->changeIdentity($id,$identity->getName(),$states);
if($duration>0)
{
if($this->allowAutoLogin)
$this->saveToCookie($duration);
else
throw new CException(Yii::t('yii','{class}.allowAutoLogin must be set true in order to use cookie-based authentication.',
array('{class}'=>get_class($this))));
}
$this->afterLogin(false);
}
return !$this->getIsGuest();
}
public function logout($destroySession=true)
{
if($this->beforeLogout())
{
if($this->allowAutoLogin)
{
Yii::app()->getRequest()->getCookies()->remove($this->getStateKeyPrefix());
if($this->identityCookie!==null)
{
$cookie=$this->createIdentityCookie($this->getStateKeyPrefix());
$cookie->value=null;
$cookie->expire=0;
Yii::app()->getRequest()->getCookies()->add($cookie->name,$cookie);
}
}
if($destroySession)
Yii::app()->getSession()->destroy();
else
$this->clearStates();
$this->afterLogout();
}
}
public function getIsGuest()
{
return $this->getState('__id')===null;
}
public function getId()
{
return $this->getState('__id');
}
public function setId($value)
{
$this->setState('__id',$value);
}
public function getName()
{
if(($name=$this->getState('__name'))!==null)
return $name;
else
return $this->guestName;
}
public function setName($value)
{
$this->setState('__name',$value);
}
public function getReturnUrl($defaultUrl=null)
{
return $this->getState('__returnUrl', $defaultUrl===null ? Yii::app()->getRequest()->getScriptUrl() : CHtml::normalizeUrl($defaultUrl));
}
public function setReturnUrl($value)
{
$this->setState('__returnUrl',$value);
}
public function loginRequired()
{
$app=Yii::app();
$request=$app->getRequest();
if(!$request->getIsAjaxRequest())
$this->setReturnUrl($request->getUrl());
elseif(isset($this->loginRequiredAjaxResponse))
{
echo $this->loginRequiredAjaxResponse;
Yii::app()->end();
}
if(($url=$this->loginUrl)!==null)
{
if(is_array($url))
{
$route=isset($url[0]) ? $url[0] : $app->defaultController;
$url=$app->createUrl($route,array_splice($url,1));
}
$request->redirect($url);
}
else
throw new CHttpException(403,Yii::t('yii','Login Required'));
}
protected function beforeLogin($id,$states,$fromCookie)
{
return true;
}
protected function afterLogin($fromCookie)
{
}
protected function beforeLogout()
{
return true;
}
protected function afterLogout()
{
}
protected function restoreFromCookie()
{
$app=Yii::app();
$request=$app->getRequest();
$cookie=$request->getCookies()->itemAt($this->getStateKeyPrefix());
if($cookie && !empty($cookie->value) && is_string($cookie->value) && ($data=$app->getSecurityManager()->validateData($cookie->value))!==false)
{
$data=@unserialize($data);
if(is_array($data) && isset($data[0],$data[1],$data[2],$data[3]))
{
list($id,$name,$duration,$states)=$data;
if($this->beforeLogin($id,$states,true))
{
$this->changeIdentity($id,$name,$states);
if($this->autoRenewCookie)
{
$cookie->expire=time()+$duration;
$request->getCookies()->add($cookie->name,$cookie);
}
$this->afterLogin(true);
}
}
}
}
protected function renewCookie()
{
$request=Yii::app()->getRequest();
$cookies=$request->getCookies();
$cookie=$cookies->itemAt($this->getStateKeyPrefix());
if($cookie && !empty($cookie->value) && ($data=Yii::app()->getSecurityManager()->validateData($cookie->value))!==false)
{
$data=@unserialize($data);
if(is_array($data) && isset($data[0],$data[1],$data[2],$data[3]))
{
$cookie->expire=time()+$data[2];
$cookies->add($cookie->name,$cookie);
}
}
}
protected function saveToCookie($duration)
{
$app=Yii::app();
$cookie=$this->createIdentityCookie($this->getStateKeyPrefix());
$cookie->expire=time()+$duration;
$data=array(
$this->getId(),
$this->getName(),
$duration,
$this->saveIdentityStates(),
);
$cookie->value=$app->getSecurityManager()->hashData(serialize($data));
$app->getRequest()->getCookies()->add($cookie->name,$cookie);
}
protected function createIdentityCookie($name)
{
$cookie=new CHttpCookie($name,'');
if(is_array($this->identityCookie))
{
foreach($this->identityCookie as $name=>$value)
$cookie->$name=$value;
}
return $cookie;
}
public function getStateKeyPrefix()
{
if($this->_keyPrefix!==null)
return $this->_keyPrefix;
else
return $this->_keyPrefix=md5('Yii.'.get_class($this).'.'.Yii::app()->getId());
}
public function setStateKeyPrefix($value)
{
$this->_keyPrefix=$value;
}
public function getState($key,$defaultValue=null)
{
$key=$this->getStateKeyPrefix().$key;
return isset($_SESSION[$key]) ? $_SESSION[$key] : $defaultValue;
}
public function setState($key,$value,$defaultValue=null)
{
$key=$this->getStateKeyPrefix().$key;
if($value===$defaultValue)
unset($_SESSION[$key]);
else
$_SESSION[$key]=$value;
}
public function hasState($key)
{
$key=$this->getStateKeyPrefix().$key;
return isset($_SESSION[$key]);
}
public function clearStates()
{
$keys=array_keys($_SESSION);
$prefix=$this->getStateKeyPrefix();
$n=strlen($prefix);
foreach($keys as $key)
{
if(!strncmp($key,$prefix,$n))
unset($_SESSION[$key]);
}
}
public function getFlashes($delete=true)
{
$flashes=array();
$prefix=$this->getStateKeyPrefix().self::FLASH_KEY_PREFIX;
$keys=array_keys($_SESSION);
$n=strlen($prefix);
foreach($keys as $key)
{
if(!strncmp($key,$prefix,$n))
{
$flashes[substr($key,$n)]=$_SESSION[$key];
if($delete)
unset($_SESSION[$key]);
}
}
if($delete)
$this->setState(self::FLASH_COUNTERS,array());
return $flashes;
}
public function getFlash($key,$defaultValue=null,$delete=true)
{
$value=$this->getState(self::FLASH_KEY_PREFIX.$key,$defaultValue);
if($delete)
$this->setFlash($key,null);
return $value;
}
public function setFlash($key,$value,$defaultValue=null)
{
$this->setState(self::FLASH_KEY_PREFIX.$key,$value,$defaultValue);
$counters=$this->getState(self::FLASH_COUNTERS,array());
if($value===$defaultValue)
unset($counters[$key]);
else
$counters[$key]=0;
$this->setState(self::FLASH_COUNTERS,$counters,array());
}
public function hasFlash($key)
{
return $this->getFlash($key, null, false)!==null;
}
protected function changeIdentity($id,$name,$states)
{
Yii::app()->getSession()->regenerateID(true);
$this->setId($id);
$this->setName($name);
$this->loadIdentityStates($states);
}
protected function saveIdentityStates()
{
$states=array();
foreach($this->getState(self::STATES_VAR,array()) as $name=>$dummy)
$states[$name]=$this->getState($name);
return $states;
}
protected function loadIdentityStates($states)
{
$names=array();
if(is_array($states))
{
foreach($states as $name=>$value)
{
$this->setState($name,$value);
$names[$name]=true;
}
}
$this->setState(self::STATES_VAR,$names);
}
protected function updateFlash()
{
$counters=$this->getState(self::FLASH_COUNTERS);
if(!is_array($counters))
return;
foreach($counters as $key=>$count)
{
if($count)
{
unset($counters[$key]);
$this->setState(self::FLASH_KEY_PREFIX.$key,null);
}
else
$counters[$key]++;
}
$this->setState(self::FLASH_COUNTERS,$counters,array());
}
protected function updateAuthStatus()
{
if($this->authTimeout!==null && !$this->getIsGuest())
{
$expires=$this->getState(self::AUTH_TIMEOUT_VAR);
if ($expires!==null && $expires < time())
$this->logout(false);
else
$this->setState(self::AUTH_TIMEOUT_VAR,time()+$this->authTimeout);
}
}
public function checkAccess($operation,$params=array(),$allowCaching=true)
{
if($allowCaching && $params===array() && isset($this->_access[$operation]))
return $this->_access[$operation];
$access=Yii::app()->getAuthManager()->checkAccess($operation,$this->getId(),$params);
if($allowCaching && $params===array())
$this->_access[$operation]=$access;
return $access;
}
}
