<?php
class CController extends CBaseController
{
const STATE_INPUT_NAME='YII_PAGE_STATE';
public $layout;
public $defaultAction='index';
private $_id;
private $_action;
private $_pageTitle;
private $_cachingStack;
private $_clips;
private $_dynamicOutput;
private $_pageStates;
private $_module;
public function __construct($id,$module=null)
{
$this->_id=$id;
$this->_module=$module;
$this->attachBehaviors($this->behaviors());
}
public function init()
{
}
public function filters()
{
return array();
}
public function actions()
{
return array();
}
public function behaviors()
{
return array();
}
public function accessRules()
{
return array();
}
public function run($actionID)
{
if(($action=$this->createAction($actionID))!==null)
{
if(($parent=$this->getModule())===null)
$parent=Yii::app();
if($parent->beforeControllerAction($this,$action))
{
$this->runActionWithFilters($action,$this->filters());
$parent->afterControllerAction($this,$action);
}
}
else
$this->missingAction($actionID);
}
public function runActionWithFilters($action,$filters)
{
if(empty($filters))
$this->runAction($action);
else
{
$priorAction=$this->_action;
$this->_action=$action;
CFilterChain::create($this,$action,$filters)->run();
$this->_action=$priorAction;
}
}
public function runAction($action)
{
$priorAction=$this->_action;
$this->_action=$action;
if($this->beforeAction($action))
{
if($action->runWithParams($this->getActionParams())===false)
$this->invalidActionParams($action);
else
$this->afterAction($action);
}
$this->_action=$priorAction;
}
public function getActionParams()
{
return $_GET;
}
public function invalidActionParams($action)
{
throw new CHttpException(400,Yii::t('yii','Your request is invalid.'));
}
public function processOutput($output)
{
Yii::app()->getClientScript()->render($output);
if($this->_dynamicOutput!==null && $this->isCachingStackEmpty())
{
$output=$this->processDynamicOutput($output);
$this->_dynamicOutput=null;
}
if($this->_pageStates===null)
$this->_pageStates=$this->loadPageStates();
if(!empty($this->_pageStates))
$this->savePageStates($this->_pageStates,$output);
return $output;
}
public function processDynamicOutput($output)
{
if($this->_dynamicOutput)
{
$output=preg_replace_callback('/<###dynamic-(\d+)###>/',array($this,'replaceDynamicOutput'),$output);
}
return $output;
}
protected function replaceDynamicOutput($matches)
{
$content=$matches[0];
if(isset($this->_dynamicOutput[$matches[1]]))
{
$content=$this->_dynamicOutput[$matches[1]];
$this->_dynamicOutput[$matches[1]]=null;
}
return $content;
}
public function createAction($actionID)
{
if($actionID==='')
$actionID=$this->defaultAction;
if(method_exists($this,'action'.$actionID) && strcasecmp($actionID,'s'))//we have actions method
return new CInlineAction($this,$actionID);
else
{
$action=$this->createActionFromMap($this->actions(),$actionID,$actionID);
if($action!==null && !method_exists($action,'run'))
throw new CException(Yii::t('yii', 'Action class {class} must implement the "run" method.', array('{class}'=>get_class($action))));
return $action;
}
}
protected function createActionFromMap($actionMap,$actionID,$requestActionID,$config=array())
{
if(($pos=strpos($actionID,'.'))===false && isset($actionMap[$actionID]))
{
$baseConfig=is_array($actionMap[$actionID]) ? $actionMap[$actionID] : array('class'=>$actionMap[$actionID]);
return Yii::createComponent(empty($config)?$baseConfig:array_merge($baseConfig,$config),$this,$requestActionID);
}
else if($pos===false)
return null;
$prefix=substr($actionID,0,$pos+1);
if(!isset($actionMap[$prefix]))
return null;
$actionID=(string)substr($actionID,$pos+1);
$provider=$actionMap[$prefix];
if(is_string($provider))
$providerType=$provider;
else if(is_array($provider) && isset($provider['class']))
{
$providerType=$provider['class'];
if(isset($provider[$actionID]))
{
if(is_string($provider[$actionID]))
$config=array_merge(array('class'=>$provider[$actionID]),$config);
else
$config=array_merge($provider[$actionID],$config);
}
}
else
throw new CException(Yii::t('yii','Object configuration must be an array containing a "class" element.'));
$class=Yii::import($providerType,true);
$map=call_user_func(array($class,'actions'));
return $this->createActionFromMap($map,$actionID,$requestActionID,$config);
}
public function missingAction($actionID)
{
throw new CHttpException(404,Yii::t('yii','The system is unable to find the requested action "{action}".',
array('{action}'=>$actionID==''?$this->defaultAction:$actionID)));
}
public function getAction()
{
return $this->_action;
}
public function setAction($value)
{
$this->_action=$value;
}
public function getId()
{
return $this->_id;
}
public function getUniqueId()
{
return $this->_module ? $this->_module->getId().'/'.$this->_id : $this->_id;
}
public function getRoute()
{
if(($action=$this->getAction())!==null)
return $this->getUniqueId().'/'.$action->getId();
else
return $this->getUniqueId();
}
public function getModule()
{
return $this->_module;
}
public function getViewPath()
{
if(($module=$this->getModule())===null)
$module=Yii::app();
return $module->getViewPath().DIRECTORY_SEPARATOR.$this->getId();
}
public function getViewFile($viewName)
{
if(($theme=Yii::app()->getTheme())!==null && ($viewFile=$theme->getViewFile($this,$viewName))!==false)
return $viewFile;
$moduleViewPath=$basePath=Yii::app()->getViewPath();
if(($module=$this->getModule())!==null)
$moduleViewPath=$module->getViewPath();
return $this->resolveViewFile($viewName,$this->getViewPath(),$basePath,$moduleViewPath);
}
public function getLayoutFile($layoutName)
{
if($layoutName===false)
return false;
if(($theme=Yii::app()->getTheme())!==null && ($layoutFile=$theme->getLayoutFile($this,$layoutName))!==false)
return $layoutFile;
if(empty($layoutName))
{
$module=$this->getModule();
while($module!==null)
{
if($module->layout===false)
return false;
if(!empty($module->layout))
break;
$module=$module->getParentModule();
}
if($module===null)
$module=Yii::app();
$layoutName=$module->layout;
}
else if(($module=$this->getModule())===null)
$module=Yii::app();
return $this->resolveViewFile($layoutName,$module->getLayoutPath(),Yii::app()->getViewPath(),$module->getViewPath());
}
public function resolveViewFile($viewName,$viewPath,$basePath,$moduleViewPath=null)
{
if(empty($viewName))
return false;
if($moduleViewPath===null)
$moduleViewPath=$basePath;
if(($renderer=Yii::app()->getViewRenderer())!==null)
$extension=$renderer->fileExtension;
else
$extension='.php';
if($viewName[0]==='/')
{
if(strncmp($viewName,'//',2)===0)
$viewFile=$basePath.$viewName;
else
$viewFile=$moduleViewPath.$viewName;
}
else if(strpos($viewName,'.'))
$viewFile=Yii::getPathOfAlias($viewName);
else
$viewFile=$viewPath.DIRECTORY_SEPARATOR.$viewName;
if(is_file($viewFile.$extension))
return Yii::app()->findLocalizedFile($viewFile.$extension);
else if($extension!=='.php' && is_file($viewFile.'.php'))
return Yii::app()->findLocalizedFile($viewFile.'.php');
else
return false;
}
public function getClips()
{
if($this->_clips!==null)
return $this->_clips;
else
return $this->_clips=new CMap;
}
public function forward($route,$exit=true)
{
if(strpos($route,'/')===false)
$this->run($route);
else
{
if($route[0]!=='/' && ($module=$this->getModule())!==null)
$route=$module->getId().'/'.$route;
Yii::app()->runController($route);
}
if($exit)
Yii::app()->end();
}
public function render($view,$data=null,$return=false)
{
if($this->beforeRender($view))
{
$output=$this->renderPartial($view,$data,true);
if(($layoutFile=$this->getLayoutFile($this->layout))!==false)
$output=$this->renderFile($layoutFile,array('content'=>$output),true);
$this->afterRender($view,$output);
$output=$this->processOutput($output);
if($return)
return $output;
else
echo $output;
}
}
protected function beforeRender($view)
{
return true;
}
protected function afterRender($view, &$output)
{
}
public function renderText($text,$return=false)
{
if(($layoutFile=$this->getLayoutFile($this->layout))!==false)
$text=$this->renderFile($layoutFile,array('content'=>$text),true);
$text=$this->processOutput($text);
if($return)
return $text;
else
echo $text;
}
public function renderPartial($view,$data=null,$return=false,$processOutput=false)
{
if(($viewFile=$this->getViewFile($view))!==false)
{
$output=$this->renderFile($viewFile,$data,true);
if($processOutput)
$output=$this->processOutput($output);
if($return)
return $output;
else
echo $output;
}
else
throw new CException(Yii::t('yii','{controller} cannot find the requested view "{view}".',
array('{controller}'=>get_class($this), '{view}'=>$view)));
}
public function renderClip($name,$params=array(),$return=false)
{
$text=isset($this->clips[$name]) ? strtr($this->clips[$name], $params) : '';
if($return)
return $text;
else
echo $text;
}
public function renderDynamic($callback)
{
$n=count($this->_dynamicOutput);
echo "<###dynamic-$n###>";
$params=func_get_args();
array_shift($params);
$this->renderDynamicInternal($callback,$params);
}
public function renderDynamicInternal($callback,$params)
{
$this->recordCachingAction('','renderDynamicInternal',array($callback,$params));
if(is_string($callback) && method_exists($this,$callback))
$callback=array($this,$callback);
$this->_dynamicOutput[]=call_user_func_array($callback,$params);
}
public function createUrl($route,$params=array(),$ampersand='&')
{
if($route==='')
$route=$this->getId().'/'.$this->getAction()->getId();
else if(strpos($route,'/')===false)
$route=$this->getId().'/'.$route;
if($route[0]!=='/' && ($module=$this->getModule())!==null)
$route=$module->getId().'/'.$route;
return Yii::app()->createUrl(trim($route,'/'),$params,$ampersand);
}
public function createAbsoluteUrl($route,$params=array(),$schema='',$ampersand='&')
{
$url=$this->createUrl($route,$params,$ampersand);
if(strpos($url,'http')===0)
return $url;
else
return Yii::app()->getRequest()->getHostInfo($schema).$url;
}
public function getPageTitle()
{
if($this->_pageTitle!==null)
return $this->_pageTitle;
else
{
$name=ucfirst(basename($this->getId()));
if($this->getAction()!==null && strcasecmp($this->getAction()->getId(),$this->defaultAction))
return $this->_pageTitle=Yii::app()->name.'-'.ucfirst($this->getAction()->getId()).' '.$name;
else
return $this->_pageTitle=Yii::app()->name.'-'.$name;
}
}
public function setPageTitle($value)
{
$this->_pageTitle=$value;
}
public function redirect($url,$terminate=true,$statusCode=302)
{
if(is_array($url))
{
$route=isset($url[0]) ? $url[0] : '';
$url=$this->createUrl($route,array_splice($url,1));
}
Yii::app()->getRequest()->redirect($url,$terminate,$statusCode);
}
public function refresh($terminate=true,$anchor='')
{
$this->redirect(Yii::app()->getRequest()->getUrl().$anchor,$terminate);
}
public function recordCachingAction($context,$method,$params)
{
if($this->_cachingStack)//record only when there is an active output cache
{
foreach($this->_cachingStack as $cache)
$cache->recordAction($context,$method,$params);
}
}
public function getCachingStack($createIfNull=true)
{
if(!$this->_cachingStack)
$this->_cachingStack=new CStack;
return $this->_cachingStack;
}
public function isCachingStackEmpty()
{
return $this->_cachingStack===null || !$this->_cachingStack->getCount();
}
protected function beforeAction($action)
{
return true;
}
protected function afterAction($action)
{
}
public function filterPostOnly($filterChain)
{
if(Yii::app()->getRequest()->getIsPostRequest())
$filterChain->run();
else
throw new CHttpException(400,Yii::t('yii','Your request is invalid.'));
}
public function filterAjaxOnly($filterChain)
{
if(Yii::app()->getRequest()->getIsAjaxRequest())
$filterChain->run();
else
throw new CHttpException(400,Yii::t('yii','Your request is invalid.'));
}
public function filterAccessControl($filterChain)
{
$filter=new CAccessControlFilter;
$filter->setRules($this->accessRules());
$filter->filter($filterChain);
}
public function getPageState($name,$defaultValue=null)
{
if($this->_pageStates===null)
$this->_pageStates=$this->loadPageStates();
return isset($this->_pageStates[$name])?$this->_pageStates[$name]:$defaultValue;
}
public function setPageState($name,$value,$defaultValue=null)
{
if($this->_pageStates===null)
$this->_pageStates=$this->loadPageStates();
if($value===$defaultValue)
unset($this->_pageStates[$name]);
else
$this->_pageStates[$name]=$value;
$params=func_get_args();
$this->recordCachingAction('','setPageState',$params);
}
public function clearPageStates()
{
$this->_pageStates=array();
}
protected function loadPageStates()
{
if(!empty($_POST[self::STATE_INPUT_NAME]))
{
if(($data=base64_decode($_POST[self::STATE_INPUT_NAME]))!==false)
{
if(extension_loaded('zlib'))
$data=@gzuncompress($data);
if(($data=Yii::app()->getSecurityManager()->validateData($data))!==false)
return unserialize($data);
}
}
return array();
}
protected function savePageStates($states,&$output)
{
$data=Yii::app()->getSecurityManager()->hashData(serialize($states));
if(extension_loaded('zlib'))
$data=gzcompress($data);
$value=base64_encode($data);
$output=str_replace(CHtml::pageStateField(''),CHtml::pageStateField($value),$output);
}
}
