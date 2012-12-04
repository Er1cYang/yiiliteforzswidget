<?php
abstract class CModule extends CComponent
{
public $preload=array();
public $behaviors=array();
private $_id;
private $_parentModule;
private $_basePath;
private $_modulePath;
private $_params;
private $_modules=array();
private $_moduleConfig=array();
private $_components=array();
private $_componentConfig=array();
public function __construct($id,$parent,$config=null)
{
$this->_id=$id;
$this->_parentModule=$parent;
if(is_string($config))
$config=require($config);
if(isset($config['basePath']))
{
$this->setBasePath($config['basePath']);
unset($config['basePath']);
}
Yii::setPathOfAlias($id,$this->getBasePath());
$this->preinit();
$this->configure($config);
$this->attachBehaviors($this->behaviors);
$this->preloadComponents();
$this->init();
}
public function __get($name)
{
if($this->hasComponent($name))
return $this->getComponent($name);
else
return parent::__get($name);
}
public function __isset($name)
{
if($this->hasComponent($name))
return $this->getComponent($name)!==null;
else
return parent::__isset($name);
}
public function getId()
{
return $this->_id;
}
public function setId($id)
{
$this->_id=$id;
}
public function getBasePath()
{
if($this->_basePath===null)
{
$class=new ReflectionClass(get_class($this));
$this->_basePath=dirname($class->getFileName());
}
return $this->_basePath;
}
public function setBasePath($path)
{
if(($this->_basePath=realpath($path))===false || !is_dir($this->_basePath))
throw new CException(Yii::t('yii','Base path "{path}" is not a valid directory.',
array('{path}'=>$path)));
}
public function getParams()
{
if($this->_params!==null)
return $this->_params;
else
{
$this->_params=new CAttributeCollection;
$this->_params->caseSensitive=true;
return $this->_params;
}
}
public function setParams($value)
{
$params=$this->getParams();
foreach($value as $k=>$v)
$params->add($k,$v);
}
public function getModulePath()
{
if($this->_modulePath!==null)
return $this->_modulePath;
else
return $this->_modulePath=$this->getBasePath().DIRECTORY_SEPARATOR.'modules';
}
public function setModulePath($value)
{
if(($this->_modulePath=realpath($value))===false || !is_dir($this->_modulePath))
throw new CException(Yii::t('yii','The module path "{path}" is not a valid directory.',
array('{path}'=>$value)));
}
public function setImport($aliases)
{
foreach($aliases as $alias)
Yii::import($alias);
}
public function setAliases($mappings)
{
foreach($mappings as $name=>$alias)
{
if(($path=Yii::getPathOfAlias($alias))!==false)
Yii::setPathOfAlias($name,$path);
else
Yii::setPathOfAlias($name,$alias);
}
}
public function getParentModule()
{
return $this->_parentModule;
}
public function getModule($id)
{
if(isset($this->_modules[$id]) || array_key_exists($id,$this->_modules))
return $this->_modules[$id];
else if(isset($this->_moduleConfig[$id]))
{
$config=$this->_moduleConfig[$id];
if(!isset($config['enabled']) || $config['enabled'])
{
Yii::trace("Loading \"$id\" module",'system.base.CModule');
$class=$config['class'];
unset($config['class'], $config['enabled']);
if($this===Yii::app())
$module=Yii::createComponent($class,$id,null,$config);
else
$module=Yii::createComponent($class,$this->getId().'/'.$id,$this,$config);
return $this->_modules[$id]=$module;
}
}
}
public function hasModule($id)
{
return isset($this->_moduleConfig[$id]) || isset($this->_modules[$id]);
}
public function getModules()
{
return $this->_moduleConfig;
}
public function setModules($modules)
{
foreach($modules as $id=>$module)
{
if(is_int($id))
{
$id=$module;
$module=array();
}
if(!isset($module['class']))
{
Yii::setPathOfAlias($id,$this->getModulePath().DIRECTORY_SEPARATOR.$id);
$module['class']=$id.'.'.ucfirst($id).'Module';
}
if(isset($this->_moduleConfig[$id]))
$this->_moduleConfig[$id]=CMap::mergeArray($this->_moduleConfig[$id],$module);
else
$this->_moduleConfig[$id]=$module;
}
}
public function hasComponent($id)
{
return isset($this->_components[$id]) || isset($this->_componentConfig[$id]);
}
public function getComponent($id,$createIfNull=true)
{
if(isset($this->_components[$id]))
return $this->_components[$id];
else if(isset($this->_componentConfig[$id]) && $createIfNull)
{
$config=$this->_componentConfig[$id];
if(!isset($config['enabled']) || $config['enabled'])
{
Yii::trace("Loading \"$id\" application component",'system.CModule');
unset($config['enabled']);
$component=Yii::createComponent($config);
$component->init();
return $this->_components[$id]=$component;
}
}
}
public function setComponent($id,$component)
{
if($component===null)
unset($this->_components[$id]);
else
{
$this->_components[$id]=$component;
if(!$component->getIsInitialized())
$component->init();
}
}
public function getComponents($loadedOnly=true)
{
if($loadedOnly)
return $this->_components;
else
return array_merge($this->_componentConfig, $this->_components);
}
public function setComponents($components,$merge=true)
{
foreach($components as $id=>$component)
{
if($component instanceof IApplicationComponent)
$this->setComponent($id,$component);
else if(isset($this->_componentConfig[$id]) && $merge)
$this->_componentConfig[$id]=CMap::mergeArray($this->_componentConfig[$id],$component);
else
$this->_componentConfig[$id]=$component;
}
}
public function configure($config)
{
if(is_array($config))
{
foreach($config as $key=>$value)
$this->$key=$value;
}
}
protected function preloadComponents()
{
foreach($this->preload as $id)
$this->getComponent($id);
}
protected function preinit()
{
}
protected function init()
{
}
}
