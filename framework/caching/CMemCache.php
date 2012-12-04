<?php
class CMemCache extends CCache
{
public $useMemcached=false;
private $_cache=null;
private $_servers=array();
public function init()
{
parent::init();
$servers=$this->getServers();
$cache=$this->getMemCache();
if(count($servers))
{
foreach($servers as $server)
{
if($this->useMemcached)
$cache->addServer($server->host,$server->port,$server->weight);
else
$cache->addServer($server->host,$server->port,$server->persistent,$server->weight,$server->timeout,$server->status);
}
}
else
$cache->addServer('localhost',11211);
}
public function getMemCache()
{
if($this->_cache!==null)
return $this->_cache;
else
{
$extension=$this->useMemcached ? 'memcached' : 'memcache';
if(!extension_loaded($extension))
throw new CException(Yii::t('yii',"CMemCache requires PHP {extension} extension to be loaded.",
array('{extension}'=>$extension)));
return $this->_cache=$this->useMemcached ? new Memcached : new Memcache;
}
}
public function getServers()
{
return $this->_servers;
}
public function setServers($config)
{
foreach($config as $c)
$this->_servers[]=new CMemCacheServerConfiguration($c);
}
protected function getValue($key)
{
return $this->_cache->get($key);
}
protected function getValues($keys)
{
return $this->useMemcached ? $this->_cache->getMulti($keys) : $this->_cache->get($keys);
}
protected function setValue($key,$value,$expire)
{
if($expire>0)
$expire+=time();
else
$expire=0;
return $this->useMemcached ? $this->_cache->set($key,$value,$expire) : $this->_cache->set($key,$value,0,$expire);
}
protected function addValue($key,$value,$expire)
{
if($expire>0)
$expire+=time();
else
$expire=0;
return $this->useMemcached ? $this->_cache->add($key,$value,$expire) : $this->_cache->add($key,$value,0,$expire);
}
protected function deleteValue($key)
{
return $this->_cache->delete($key, 0);
}
protected function flushValues()
{
return $this->_cache->flush();
}
}
class CMemCacheServerConfiguration extends CComponent
{
public $host;
public $port=11211;
public $persistent=true;
public $weight=1;
public $timeout=15;
public $retryInterval=15;
public $status=true;
public function __construct($config)
{
if(is_array($config))
{
foreach($config as $key=>$value)
$this->$key=$value;
if($this->host===null)
throw new CException(Yii::t('yii','CMemCache server configuration must have "host" value.'));
}
else
throw new CException(Yii::t('yii','CMemCache server configuration must be an array.'));
}
}
