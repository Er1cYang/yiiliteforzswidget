<?php
class CSecurityManager extends CApplicationComponent
{
const STATE_VALIDATION_KEY='Yii.CSecurityManager.validationkey';
const STATE_ENCRYPTION_KEY='Yii.CSecurityManager.encryptionkey';
public $hashAlgorithm='sha1';
public $cryptAlgorithm='des';
private $_validationKey;
private $_encryptionKey;
private $_mbstring;
public function init()
{
parent::init();
$this->_mbstring=extension_loaded('mbstring');
}
protected function generateRandomKey()
{
return sprintf('%08x%08x%08x%08x',mt_rand(),mt_rand(),mt_rand(),mt_rand());
}
public function getValidationKey()
{
if($this->_validationKey!==null)
return $this->_validationKey;
else
{
if(($key=Yii::app()->getGlobalState(self::STATE_VALIDATION_KEY))!==null)
$this->setValidationKey($key);
else
{
$key=$this->generateRandomKey();
$this->setValidationKey($key);
Yii::app()->setGlobalState(self::STATE_VALIDATION_KEY,$key);
}
return $this->_validationKey;
}
}
public function setValidationKey($value)
{
if(!empty($value))
$this->_validationKey=$value;
else
throw new CException(Yii::t('yii','CSecurityManager.validationKey cannot be empty.'));
}
public function getEncryptionKey()
{
if($this->_encryptionKey!==null)
return $this->_encryptionKey;
else
{
if(($key=Yii::app()->getGlobalState(self::STATE_ENCRYPTION_KEY))!==null)
$this->setEncryptionKey($key);
else
{
$key=$this->generateRandomKey();
$this->setEncryptionKey($key);
Yii::app()->setGlobalState(self::STATE_ENCRYPTION_KEY,$key);
}
return $this->_encryptionKey;
}
}
public function setEncryptionKey($value)
{
if(!empty($value))
$this->_encryptionKey=$value;
else
throw new CException(Yii::t('yii','CSecurityManager.encryptionKey cannot be empty.'));
}
public function getValidation()
{
return $this->hashAlgorithm;
}
public function setValidation($value)
{
$this->hashAlgorithm=$value;
}
public function encrypt($data,$key=null)
{
$module=$this->openCryptModule();
$key=$this->substr($key===null ? md5($this->getEncryptionKey()) : $key,0,mcrypt_enc_get_key_size($module));
srand();
$iv=mcrypt_create_iv(mcrypt_enc_get_iv_size($module), MCRYPT_RAND);
mcrypt_generic_init($module,$key,$iv);
$encrypted=$iv.mcrypt_generic($module,$data);
mcrypt_generic_deinit($module);
mcrypt_module_close($module);
return $encrypted;
}
public function decrypt($data,$key=null)
{
$module=$this->openCryptModule();
$key=$this->substr($key===null ? md5($this->getEncryptionKey()) : $key,0,mcrypt_enc_get_key_size($module));
$ivSize=mcrypt_enc_get_iv_size($module);
$iv=$this->substr($data,0,$ivSize);
mcrypt_generic_init($module,$key,$iv);
$decrypted=mdecrypt_generic($module,$this->substr($data,$ivSize,$this->strlen($data)));
mcrypt_generic_deinit($module);
mcrypt_module_close($module);
return rtrim($decrypted,"\0");
}
protected function openCryptModule()
{
if(extension_loaded('mcrypt'))
{
if(is_array($this->cryptAlgorithm))
$module=@call_user_func_array('mcrypt_module_open',$this->cryptAlgorithm);
else
$module=@mcrypt_module_open($this->cryptAlgorithm,'', MCRYPT_MODE_CBC,'');
if($module===false)
throw new CException(Yii::t('yii','Failed to initialize the mcrypt module.'));
return $module;
}
else
throw new CException(Yii::t('yii','CSecurityManager requires PHP mcrypt extension to be loaded in order to use data encryption feature.'));
}
public function hashData($data,$key=null)
{
return $this->computeHMAC($data,$key).$data;
}
public function validateData($data,$key=null)
{
$len=$this->strlen($this->computeHMAC('test'));
if($this->strlen($data)>=$len)
{
$hmac=$this->substr($data,0,$len);
$data2=$this->substr($data,$len,$this->strlen($data));
return $hmac===$this->computeHMAC($data2,$key)?$data2:false;
}
else
return false;
}
protected function computeHMAC($data,$key=null)
{
if($key===null)
$key=$this->getValidationKey();
if(function_exists('hash_hmac'))
return hash_hmac($this->hashAlgorithm, $data, $key);
if(!strcasecmp($this->hashAlgorithm,'sha1'))
{
$pack='H40';
$func='sha1';
}
else
{
$pack='H32';
$func='md5';
}
if($this->strlen($key) > 64)
$key=pack($pack, $func($key));
if($this->strlen($key) < 64)
$key=str_pad($key, 64, chr(0));
$key=$this->substr($key,0,64);
return $func((str_repeat(chr(0x5C), 64) ^ $key) . pack($pack, $func((str_repeat(chr(0x36), 64) ^ $key) . $data)));
}
private function strlen($string)
{
return $this->_mbstring ? mb_strlen($string,'8bit') : strlen($string);
}
private function substr($string,$start,$length)
{
return $this->_mbstring ? mb_substr($string,$start,$length,'8bit') : substr($string,$start,$length);
}
}
