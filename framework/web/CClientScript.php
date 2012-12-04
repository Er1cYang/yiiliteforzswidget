<?php
class CClientScript extends CApplicationComponent
{
const POS_HEAD=0;
const POS_BEGIN=1;
const POS_END=2;
const POS_LOAD=3;
const POS_READY=4;
public $enableJavaScript=true;
public $scriptMap=array();
public $packages=array();
public $corePackages;
public $scripts=array();
protected $cssFiles=array();
protected $scriptFiles=array();
protected $metaTags=array();
protected $linkTags=array();
protected $css=array();
protected $hasScripts=false;
protected $coreScripts=array();
public $coreScriptPosition=self::POS_HEAD;
public $defaultScriptFilePosition=self::POS_HEAD;
public $defaultScriptPosition=self::POS_READY;
private $_baseUrl;
public function reset()
{
$this->hasScripts=false;
$this->coreScripts=array();
$this->cssFiles=array();
$this->css=array();
$this->scriptFiles=array();
$this->scripts=array();
$this->metaTags=array();
$this->linkTags=array();
$this->recordCachingAction('clientScript','reset',array());
}
public function render(&$output)
{
if(!$this->hasScripts)
return;
$this->renderCoreScripts();
if(!empty($this->scriptMap))
$this->remapScripts();
$this->unifyScripts();
$this->renderHead($output);
if($this->enableJavaScript)
{
$this->renderBodyBegin($output);
$this->renderBodyEnd($output);
}
}
protected function unifyScripts()
{
if(!$this->enableJavaScript)
return;
$map=array();
if(isset($this->scriptFiles[self::POS_HEAD]))
$map=$this->scriptFiles[self::POS_HEAD];
if(isset($this->scriptFiles[self::POS_BEGIN]))
{
foreach($this->scriptFiles[self::POS_BEGIN] as $key=>$scriptFile)
{
if(isset($map[$scriptFile]))
unset($this->scriptFiles[self::POS_BEGIN][$key]);
else
$map[$scriptFile]=true;
}
}
if(isset($this->scriptFiles[self::POS_END]))
{
foreach($this->scriptFiles[self::POS_END] as $key=>$scriptFile)
{
if(isset($map[$scriptFile]))
unset($this->scriptFiles[self::POS_END][$key]);
}
}
}
protected function remapScripts()
{
$cssFiles=array();
foreach($this->cssFiles as $url=>$media)
{
$name=basename($url);
if(isset($this->scriptMap[$name]))
{
if($this->scriptMap[$name]!==false)
$cssFiles[$this->scriptMap[$name]]=$media;
}
else if(isset($this->scriptMap['*.css']))
{
if($this->scriptMap['*.css']!==false)
$cssFiles[$this->scriptMap['*.css']]=$media;
}
else
$cssFiles[$url]=$media;
}
$this->cssFiles=$cssFiles;
$jsFiles=array();
foreach($this->scriptFiles as $position=>$scripts)
{
$jsFiles[$position]=array();
foreach($scripts as $key=>$script)
{
$name=basename($script);
if(isset($this->scriptMap[$name]))
{
if($this->scriptMap[$name]!==false)
$jsFiles[$position][$this->scriptMap[$name]]=$this->scriptMap[$name];
}
else if(isset($this->scriptMap['*.js']))
{
if($this->scriptMap['*.js']!==false)
$jsFiles[$position][$this->scriptMap['*.js']]=$this->scriptMap['*.js'];
}
else
$jsFiles[$position][$key]=$script;
}
}
$this->scriptFiles=$jsFiles;
}
public function renderCoreScripts()
{
if($this->coreScripts===null)
return;
$cssFiles=array();
$jsFiles=array();
foreach($this->coreScripts as $name=>$package)
{
$baseUrl=$this->getPackageBaseUrl($name);
if(!empty($package['js']))
{
foreach($package['js'] as $js)
$jsFiles[$baseUrl.'/'.$js]=$baseUrl.'/'.$js;
}
if(!empty($package['css']))
{
foreach($package['css'] as $css)
$cssFiles[$baseUrl.'/'.$css]='';
}
}
if($cssFiles!==array())
{
foreach($this->cssFiles as $cssFile=>$media)
$cssFiles[$cssFile]=$media;
$this->cssFiles=$cssFiles;
}
if($jsFiles!==array())
{
if(isset($this->scriptFiles[$this->coreScriptPosition]))
{
foreach($this->scriptFiles[$this->coreScriptPosition] as $url)
$jsFiles[$url]=$url;
}
$this->scriptFiles[$this->coreScriptPosition]=$jsFiles;
}
}
public function renderHead(&$output)
{
$html='';
foreach($this->metaTags as $meta)
$html.=CHtml::metaTag($meta['content'],null,null,$meta)."\n";
foreach($this->linkTags as $link)
$html.=CHtml::linkTag(null,null,null,null,$link)."\n";
foreach($this->cssFiles as $url=>$media)
$html.=CHtml::cssFile($url,$media)."\n";
foreach($this->css as $css)
$html.=CHtml::css($css[0],$css[1])."\n";
if($this->enableJavaScript)
{
if(isset($this->scriptFiles[self::POS_HEAD]))
{
foreach($this->scriptFiles[self::POS_HEAD] as $scriptFile)
$html.=CHtml::scriptFile($scriptFile)."\n";
}
if(isset($this->scripts[self::POS_HEAD]))
$html.=CHtml::script(implode("\n",$this->scripts[self::POS_HEAD]))."\n";
}
if($html!=='')
{
$count=0;
$output=preg_replace('/(<title\b[^>]*>|<\\/head\s*>)/is','<###head###>$1',$output,1,$count);
if($count)
$output=str_replace('<###head###>',$html,$output);
else
$output=$html.$output;
}
}
public function renderBodyBegin(&$output)
{
$html='';
if(isset($this->scriptFiles[self::POS_BEGIN]))
{
foreach($this->scriptFiles[self::POS_BEGIN] as $scriptFile)
$html.=CHtml::scriptFile($scriptFile)."\n";
}
if(isset($this->scripts[self::POS_BEGIN]))
$html.=CHtml::script(implode("\n",$this->scripts[self::POS_BEGIN]))."\n";
if($html!=='')
{
$count=0;
$output=preg_replace('/(<body\b[^>]*>)/is','$1<###begin###>',$output,1,$count);
if($count)
$output=str_replace('<###begin###>',$html,$output);
else
$output=$html.$output;
}
}
public function renderBodyEnd(&$output)
{
if(!isset($this->scriptFiles[self::POS_END]) && !isset($this->scripts[self::POS_END])
&& !isset($this->scripts[self::POS_READY]) && !isset($this->scripts[self::POS_LOAD]))
return;
$fullPage=0;
$output=preg_replace('/(<\\/body\s*>)/is','<###end###>$1',$output,1,$fullPage);
$html='';
if(isset($this->scriptFiles[self::POS_END]))
{
foreach($this->scriptFiles[self::POS_END] as $scriptFile)
$html.=CHtml::scriptFile($scriptFile)."\n";
}
$scripts=isset($this->scripts[self::POS_END]) ? $this->scripts[self::POS_END] : array();
if(isset($this->scripts[self::POS_READY]))
{
if($fullPage)
$scripts[]="jQuery(function($) {\n".implode("\n",$this->scripts[self::POS_READY])."\n});";
else
$scripts[]=implode("\n",$this->scripts[self::POS_READY]);
}
if(isset($this->scripts[self::POS_LOAD]))
{
if($fullPage)
$scripts[]="jQuery(window).load(function() {\n".implode("\n",$this->scripts[self::POS_LOAD])."\n});";
else
$scripts[]=implode("\n",$this->scripts[self::POS_LOAD]);
}
if(!empty($scripts))
$html.=CHtml::script(implode("\n",$scripts))."\n";
if($fullPage)
$output=str_replace('<###end###>',$html,$output);
else
$output=$output.$html;
}
public function getCoreScriptUrl()
{
if($this->_baseUrl!==null)
return $this->_baseUrl;
else
return $this->_baseUrl=Yii::app()->getAssetManager()->publish(YII_PATH.'/web/js/source');
}
public function setCoreScriptUrl($value)
{
$this->_baseUrl=$value;
}
public function getPackageBaseUrl($name)
{
if(!isset($this->coreScripts[$name]))
return false;
$package=$this->coreScripts[$name];
if(isset($package['baseUrl']))
{
$baseUrl=$package['baseUrl'];
if($baseUrl==='' || $baseUrl[0]!=='/' && strpos($baseUrl,'://')===false)
$baseUrl=Yii::app()->getRequest()->getBaseUrl().'/'.$baseUrl;
$baseUrl=rtrim($baseUrl,'/');
}
else if(isset($package['basePath']))
$baseUrl=Yii::app()->getAssetManager()->publish(Yii::getPathOfAlias($package['basePath']));
else
$baseUrl=$this->getCoreScriptUrl();
return $this->coreScripts[$name]['baseUrl']=$baseUrl;
}
public function registerPackage($name)
{
return $this->registerCoreScript($name);
}
public function registerCoreScript($name)
{
if(isset($this->coreScripts[$name]))
return $this;
if(isset($this->packages[$name]))
$package=$this->packages[$name];
else
{
if($this->corePackages===null)
$this->corePackages=require(YII_PATH.'/web/js/packages.php');
if(isset($this->corePackages[$name]))
$package=$this->corePackages[$name];
}
if(isset($package))
{
if(!empty($package['depends']))
{
foreach($package['depends'] as $p)
$this->registerCoreScript($p);
}
$this->coreScripts[$name]=$package;
$this->hasScripts=true;
$params=func_get_args();
$this->recordCachingAction('clientScript','registerCoreScript',$params);
}
return $this;
}
public function registerCssFile($url,$media='')
{
$this->hasScripts=true;
$this->cssFiles[$url]=$media;
$params=func_get_args();
$this->recordCachingAction('clientScript','registerCssFile',$params);
return $this;
}
public function registerCss($id,$css,$media='')
{
$this->hasScripts=true;
$this->css[$id]=array($css,$media);
$params=func_get_args();
$this->recordCachingAction('clientScript','registerCss',$params);
return $this;
}
public function registerScriptFile($url,$position=null)
{
if($position===null)
$position=$this->defaultScriptFilePosition;
$this->hasScripts=true;
$this->scriptFiles[$position][$url]=$url;
$params=func_get_args();
$this->recordCachingAction('clientScript','registerScriptFile',$params);
return $this;
}
public function registerScript($id,$script,$position=null)
{
if($position===null)
$position=$this->defaultScriptPosition;
$this->hasScripts=true;
$this->scripts[$position][$id]=$script;
if($position===self::POS_READY || $position===self::POS_LOAD)
$this->registerCoreScript('jquery');
$params=func_get_args();
$this->recordCachingAction('clientScript','registerScript',$params);
return $this;
}
public function registerMetaTag($content,$name=null,$httpEquiv=null,$options=array())
{
$this->hasScripts=true;
if($name!==null)
$options['name']=$name;
if($httpEquiv!==null)
$options['http-equiv']=$httpEquiv;
$options['content']=$content;
$this->metaTags[serialize($options)]=$options;
$params=func_get_args();
$this->recordCachingAction('clientScript','registerMetaTag',$params);
return $this;
}
public function registerLinkTag($relation=null,$type=null,$href=null,$media=null,$options=array())
{
$this->hasScripts=true;
if($relation!==null)
$options['rel']=$relation;
if($type!==null)
$options['type']=$type;
if($href!==null)
$options['href']=$href;
if($media!==null)
$options['media']=$media;
$this->linkTags[serialize($options)]=$options;
$params=func_get_args();
$this->recordCachingAction('clientScript','registerLinkTag',$params);
return $this;
}
public function isCssFileRegistered($url)
{
return isset($this->cssFiles[$url]);
}
public function isCssRegistered($id)
{
return isset($this->css[$id]);
}
public function isScriptFileRegistered($url,$position=self::POS_HEAD)
{
return isset($this->scriptFiles[$position][$url]);
}
public function isScriptRegistered($id,$position=self::POS_READY)
{
return isset($this->scripts[$position][$id]);
}
protected function recordCachingAction($context,$method,$params)
{
if(($controller=Yii::app()->getController())!==null)
$controller->recordCachingAction($context,$method,$params);
}
public function addPackage($name,$definition)
{
$this->packages[$name]=$definition;
return $this;
}
}