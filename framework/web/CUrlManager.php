<?php
class CUrlManager extends CApplicationComponent
{
const CACHE_KEY='Yii.CUrlManager.rules';
const GET_FORMAT='get';
const PATH_FORMAT='path';
public $rules=array();
public $urlSuffix='';
public $showScriptName=true;
public $appendParams=true;
public $routeVar='r';
public $caseSensitive=true;
public $matchValue=false;
public $cacheID='cache';
public $useStrictParsing=false;
public $urlRuleClass='CUrlRule';
private $_urlFormat=self::GET_FORMAT;
private $_rules=array();
private $_baseUrl;
public function init()
{
parent::init();
$this->processRules();
}
protected function processRules()
{
if(empty($this->rules) || $this->getUrlFormat()===self::GET_FORMAT)
return;
if($this->cacheID!==false && ($cache=Yii::app()->getComponent($this->cacheID))!==null)
{
$hash=md5(serialize($this->rules));
if(($data=$cache->get(self::CACHE_KEY))!==false && isset($data[1]) && $data[1]===$hash)
{
$this->_rules=$data[0];
return;
}
}
foreach($this->rules as $pattern=>$route)
$this->_rules[]=$this->createUrlRule($route,$pattern);
if(isset($cache))
$cache->set(self::CACHE_KEY,array($this->_rules,$hash));
}
public function addRules($rules,$append=true)
{
if ($append)
{
foreach($rules as $pattern=>$route)
$this->_rules[]=$this->createUrlRule($route,$pattern);
}
else
{
$rules=array_reverse($rules);
foreach($rules as $pattern=>$route)
array_unshift($this->_rules, $this->createUrlRule($route,$pattern));
}
}
protected function createUrlRule($route,$pattern)
{
if(is_array($route) && isset($route['class']))
return $route;
else
return new $this->urlRuleClass($route,$pattern);
}
public function createUrl($route,$params=array(),$ampersand='&')
{
unset($params[$this->routeVar]);
foreach($params as $i=>$param)
if($param===null)
$params[$i]='';
if(isset($params['#']))
{
$anchor='#'.$params['#'];
unset($params['#']);
}
else
$anchor='';
$route=trim($route,'/');
foreach($this->_rules as $i=>$rule)
{
if(is_array($rule))
$this->_rules[$i]=$rule=Yii::createComponent($rule);
if(($url=$rule->createUrl($this,$route,$params,$ampersand))!==false)
{
if($rule->hasHostInfo)
return $url==='' ? '/'.$anchor : $url.$anchor;
else
return $this->getBaseUrl().'/'.$url.$anchor;
}
}
return $this->createUrlDefault($route,$params,$ampersand).$anchor;
}
protected function createUrlDefault($route,$params,$ampersand)
{
if($this->getUrlFormat()===self::PATH_FORMAT)
{
$url=rtrim($this->getBaseUrl().'/'.$route,'/');
if($this->appendParams)
{
$url=rtrim($url.'/'.$this->createPathInfo($params,'/','/'),'/');
return $route==='' ? $url : $url.$this->urlSuffix;
}
else
{
if($route!=='')
$url.=$this->urlSuffix;
$query=$this->createPathInfo($params,'=',$ampersand);
return $query==='' ? $url : $url.'?'.$query;
}
}
else
{
$url=$this->getBaseUrl();
if(!$this->showScriptName)
$url.='/';
if($route!=='')
{
$url.='?'.$this->routeVar.'='.$route;
if(($query=$this->createPathInfo($params,'=',$ampersand))!=='')
$url.=$ampersand.$query;
}
else if(($query=$this->createPathInfo($params,'=',$ampersand))!=='')
$url.='?'.$query;
return $url;
}
}
public function parseUrl($request)
{
if($this->getUrlFormat()===self::PATH_FORMAT)
{
$rawPathInfo=$request->getPathInfo();
$pathInfo=$this->removeUrlSuffix($rawPathInfo,$this->urlSuffix);
foreach($this->_rules as $i=>$rule)
{
if(is_array($rule))
$this->_rules[$i]=$rule=Yii::createComponent($rule);
if(($r=$rule->parseUrl($this,$request,$pathInfo,$rawPathInfo))!==false)
return isset($_GET[$this->routeVar]) ? $_GET[$this->routeVar] : $r;
}
if($this->useStrictParsing)
throw new CHttpException(404,Yii::t('yii','Unable to resolve the request "{route}".',
array('{route}'=>$pathInfo)));
else
return $pathInfo;
}
else if(isset($_GET[$this->routeVar]))
return $_GET[$this->routeVar];
else if(isset($_POST[$this->routeVar]))
return $_POST[$this->routeVar];
else
return '';
}
public function parsePathInfo($pathInfo)
{
if($pathInfo==='')
return;
$segs=explode('/',$pathInfo.'/');
$n=count($segs);
for($i=0;$i<$n-1;$i+=2)
{
$key=$segs[$i];
if($key==='') continue;
$value=$segs[$i+1];
if(($pos=strpos($key,'['))!==false && ($m=preg_match_all('/\[(.*?)\]/',$key,$matches))>0)
{
$name=substr($key,0,$pos);
for($j=$m-1;$j>=0;--$j)
{
if($matches[1][$j]==='')
$value=array($value);
else
$value=array($matches[1][$j]=>$value);
}
if(isset($_GET[$name]) && is_array($_GET[$name]))
$value=CMap::mergeArray($_GET[$name],$value);
$_REQUEST[$name]=$_GET[$name]=$value;
}
else
$_REQUEST[$key]=$_GET[$key]=$value;
}
}
public function createPathInfo($params,$equal,$ampersand, $key=null)
{
$pairs = array();
foreach($params as $k=>$v)
{
if ($key!==null)
$k = $key.'['.$k.']';
if (is_array($v))
$pairs[]=$this->createPathInfo($v,$equal,$ampersand, $k);
else
$pairs[]=urlencode($k).$equal.urlencode($v);
}
return implode($ampersand,$pairs);
}
public function removeUrlSuffix($pathInfo,$urlSuffix)
{
if($urlSuffix!=='' && substr($pathInfo,-strlen($urlSuffix))===$urlSuffix)
return substr($pathInfo,0,-strlen($urlSuffix));
else
return $pathInfo;
}
public function getBaseUrl()
{
if($this->_baseUrl!==null)
return $this->_baseUrl;
else
{
if($this->showScriptName)
$this->_baseUrl=Yii::app()->getRequest()->getScriptUrl();
else
$this->_baseUrl=Yii::app()->getRequest()->getBaseUrl();
return $this->_baseUrl;
}
}
public function setBaseUrl($value)
{
$this->_baseUrl=$value;
}
public function getUrlFormat()
{
return $this->_urlFormat;
}
public function setUrlFormat($value)
{
if($value===self::PATH_FORMAT || $value===self::GET_FORMAT)
$this->_urlFormat=$value;
else
throw new CException(Yii::t('yii','CUrlManager.UrlFormat must be either "path" or "get".'));
}
}
abstract class CBaseUrlRule extends CComponent
{
public $hasHostInfo=false;
abstract public function createUrl($manager,$route,$params,$ampersand);
abstract public function parseUrl($manager,$request,$pathInfo,$rawPathInfo);
}
class CUrlRule extends CBaseUrlRule
{
public $urlSuffix;
public $caseSensitive;
public $defaultParams=array();
public $matchValue;
public $verb;
public $parsingOnly=false;
public $route;
public $references=array();
public $routePattern;
public $pattern;
public $template;
public $params=array();
public $append;
public $hasHostInfo;
public function __construct($route,$pattern)
{
if(is_array($route))
{
foreach(array('urlSuffix', 'caseSensitive', 'defaultParams', 'matchValue', 'verb', 'parsingOnly') as $name)
{
if(isset($route[$name]))
$this->$name=$route[$name];
}
if(isset($route['pattern']))
$pattern=$route['pattern'];
$route=$route[0];
}
$this->route=trim($route,'/');
$tr2['/']=$tr['/']='\\/';
if(strpos($route,'<')!==false && preg_match_all('/<(\w+)>/',$route,$matches2))
{
foreach($matches2[1] as $name)
$this->references[$name]="<$name>";
}
$this->hasHostInfo=!strncasecmp($pattern,'http://',7) || !strncasecmp($pattern,'https://',8);
if($this->verb!==null)
$this->verb=preg_split('/[\s,]+/',strtoupper($this->verb),-1,PREG_SPLIT_NO_EMPTY);
if(preg_match_all('/<(\w+):?(.*?)?>/',$pattern,$matches))
{
$tokens=array_combine($matches[1],$matches[2]);
foreach($tokens as $name=>$value)
{
if($value==='')
$value='[^\/]+';
$tr["<$name>"]="(?P<$name>$value)";
if(isset($this->references[$name]))
$tr2["<$name>"]=$tr["<$name>"];
else
$this->params[$name]=$value;
}
}
$p=rtrim($pattern,'*');
$this->append=$p!==$pattern;
$p=trim($p,'/');
$this->template=preg_replace('/<(\w+):?.*?>/','<$1>',$p);
$this->pattern='/^'.strtr($this->template,$tr).'\/';
if($this->append)
$this->pattern.='/u';
else
$this->pattern.='$/u';
if($this->references!==array())
$this->routePattern='/^'.strtr($this->route,$tr2).'$/u';
if(YII_DEBUG && @preg_match($this->pattern,'test')===false)
throw new CException(Yii::t('yii','The URL pattern "{pattern}" for route "{route}" is not a valid regular expression.',
array('{route}'=>$route,'{pattern}'=>$pattern)));
}
public function createUrl($manager,$route,$params,$ampersand)
{
if($this->parsingOnly)
return false;
if($manager->caseSensitive && $this->caseSensitive===null || $this->caseSensitive)
$case='';
else
$case='i';
$tr=array();
if($route!==$this->route)
{
if($this->routePattern!==null && preg_match($this->routePattern.$case,$route,$matches))
{
foreach($this->references as $key=>$name)
$tr[$name]=$matches[$key];
}
else
return false;
}
foreach($this->defaultParams as $key=>$value)
{
if(isset($params[$key]))
{
if($params[$key]==$value)
unset($params[$key]);
else
return false;
}
}
foreach($this->params as $key=>$value)
if(!isset($params[$key]))
return false;
if($manager->matchValue && $this->matchValue===null || $this->matchValue)
{
foreach($this->params as $key=>$value)
{
if(!preg_match('/\A'.$value.'\z/u'.$case,$params[$key]))
return false;
}
}
foreach($this->params as $key=>$value)
{
$tr["<$key>"]=urlencode($params[$key]);
unset($params[$key]);
}
$suffix=$this->urlSuffix===null ? $manager->urlSuffix : $this->urlSuffix;
$url=strtr($this->template,$tr);
if($this->hasHostInfo)
{
$hostInfo=Yii::app()->getRequest()->getHostInfo();
if(stripos($url,$hostInfo)===0)
$url=substr($url,strlen($hostInfo));
}
if(empty($params))
return $url!=='' ? $url.$suffix : $url;
if($this->append)
$url.='/'.$manager->createPathInfo($params,'/','/').$suffix;
else
{
if($url!=='')
$url.=$suffix;
$url.='?'.$manager->createPathInfo($params,'=',$ampersand);
}
return $url;
}
public function parseUrl($manager,$request,$pathInfo,$rawPathInfo)
{
if($this->verb!==null && !in_array($request->getRequestType(), $this->verb, true))
return false;
if($manager->caseSensitive && $this->caseSensitive===null || $this->caseSensitive)
$case='';
else
$case='i';
if($this->urlSuffix!==null)
$pathInfo=$manager->removeUrlSuffix($rawPathInfo,$this->urlSuffix);
if($manager->useStrictParsing && $pathInfo===$rawPathInfo)
{
$urlSuffix=$this->urlSuffix===null ? $manager->urlSuffix : $this->urlSuffix;
if($urlSuffix!='' && $urlSuffix!=='/')
return false;
}
if($this->hasHostInfo)
$pathInfo=strtolower($request->getHostInfo()).rtrim('/'.$pathInfo,'/');
$pathInfo.='/';
if(preg_match($this->pattern.$case,$pathInfo,$matches))
{
foreach($this->defaultParams as $name=>$value)
{
if(!isset($_GET[$name]))
$_REQUEST[$name]=$_GET[$name]=$value;
}
$tr=array();
foreach($matches as $key=>$value)
{
if(isset($this->references[$key]))
$tr[$this->references[$key]]=$value;
else if(isset($this->params[$key]))
$_REQUEST[$key]=$_GET[$key]=$value;
}
if($pathInfo!==$matches[0])//there're additional GET params
$manager->parsePathInfo(ltrim(substr($pathInfo,strlen($matches[0])),'/'));
if($this->routePattern!==null)
return strtr($this->route,$tr);
else
return $this->route;
}
else
return false;
}
}
