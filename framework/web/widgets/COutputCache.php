<?php
class COutputCache extends CFilterWidget
{
const CACHE_KEY_PREFIX='Yii.COutputCache.';
public $duration=60;
public $varyByRoute=true;
public $varyBySession=false;
public $varyByParam;
public $varyByExpression;
public $requestTypes;
public $cacheID='cache';
public $dependency;
private $_key;
private $_cache;
private $_contentCached;
private $_content;
private $_actions;
public function filter($filterChain)
{
if(!$this->getIsContentCached())
$filterChain->run();
$this->run();
}
public function init()
{
if($this->getIsContentCached())
$this->replayActions();
else if($this->_cache!==null)
{
$this->getController()->getCachingStack()->push($this);
ob_start();
ob_implicit_flush(false);
}
}
public function run()
{
if($this->getIsContentCached())
{
if($this->getController()->isCachingStackEmpty())
echo $this->getController()->processDynamicOutput($this->_content);
else
echo $this->_content;
}
else if($this->_cache!==null)
{
$this->_content=ob_get_clean();
$this->getController()->getCachingStack()->pop();
$data=array($this->_content,$this->_actions);
if(is_array($this->dependency))
$this->dependency=Yii::createComponent($this->dependency);
$this->_cache->set($this->getCacheKey(),$data,$this->duration,$this->dependency);
if($this->getController()->isCachingStackEmpty())
echo $this->getController()->processDynamicOutput($this->_content);
else
echo $this->_content;
}
}
public function getIsContentCached()
{
if($this->_contentCached!==null)
return $this->_contentCached;
else
return $this->_contentCached=$this->checkContentCache();
}
protected function checkContentCache()
{
if((empty($this->requestTypes) || in_array(Yii::app()->getRequest()->getRequestType(),$this->requestTypes))
&& ($this->_cache=$this->getCache())!==null)
{
if($this->duration>0 && ($data=$this->_cache->get($this->getCacheKey()))!==false)
{
$this->_content=$data[0];
$this->_actions=$data[1];
return true;
}
if($this->duration==0)
$this->_cache->delete($this->getCacheKey());
if($this->duration<=0)
$this->_cache=null;
}
return false;
}
protected function getCache()
{
return Yii::app()->getComponent($this->cacheID);
}
protected function getBaseCacheKey()
{
return self::CACHE_KEY_PREFIX.$this->getId().'.';
}
protected function getCacheKey()
{
if($this->_key!==null)
return $this->_key;
else
{
$key=$this->getBaseCacheKey().'.';
if($this->varyByRoute)
{
$controller=$this->getController();
$key.=$controller->getUniqueId().'/';
if(($action=$controller->getAction())!==null)
$key.=$action->getId();
}
$key.='.';
if($this->varyBySession)
$key.=Yii::app()->getSession()->getSessionID();
$key.='.';
if(is_array($this->varyByParam) && isset($this->varyByParam[0]))
{
$params=array();
foreach($this->varyByParam as $name)
{
if(isset($_GET[$name]))
$params[$name]=$_GET[$name];
else
$params[$name]='';
}
$key.=serialize($params);
}
$key.='.';
if($this->varyByExpression!==null)
$key.=$this->evaluateExpression($this->varyByExpression);
$key.='.';
return $this->_key=$key;
}
}
public function recordAction($context,$method,$params)
{
$this->_actions[]=array($context,$method,$params);
}
protected function replayActions()
{
if(empty($this->_actions))
return;
$controller=$this->getController();
$cs=Yii::app()->getClientScript();
foreach($this->_actions as $action)
{
if($action[0]==='clientScript')
$object=$cs;
else if($action[0]==='')
$object=$controller;
else
$object=$controller->{$action[0]};
if(method_exists($object,$action[1]))
call_user_func_array(array($object,$action[1]),$action[2]);
else if($action[0]==='' && function_exists($action[1]))
call_user_func_array($action[1],$action[2]);
else
throw new CException(Yii::t('yii','Unable to replay the action "{object}.{method}". The method does not exist.',
array('object'=>$action[0],
'method'=>$action[1])));
}
}
}
