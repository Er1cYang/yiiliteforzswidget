<?php
abstract class CConsoleCommand extends CComponent
{
public $defaultAction='index';
private $_name;
private $_runner;
public function __construct($name,$runner)
{
$this->_name=$name;
$this->_runner=$runner;
$this->attachBehaviors($this->behaviors());
}
public function init()
{
}
public function behaviors()
{
return array();
}
public function run($args)
{
list($action, $options, $args)=$this->resolveRequest($args);
$methodName='action'.$action;
if(!preg_match('/^\w+$/',$action) || !method_exists($this,$methodName))
$this->usageError("Unknown action: ".$action);
$method=new ReflectionMethod($this,$methodName);
$params=array();
foreach($method->getParameters() as $i=>$param)
{
$name=$param->getName();
if(isset($options[$name]))
{
if($param->isArray())
$params[]=is_array($options[$name]) ? $options[$name] : array($options[$name]);
else if(!is_array($options[$name]))
$params[]=$options[$name];
else
$this->usageError("Option --$name requires a scalar. Array is given.");
}
else if($name==='args')
$params[]=$args;
else if($param->isDefaultValueAvailable())
$params[]=$param->getDefaultValue();
else
$this->usageError("Missing required option --$name.");
unset($options[$name]);
}
if(!empty($options))
{
$class=new ReflectionClass(get_class($this));
foreach($options as $name=>$value)
{
if($class->hasProperty($name))
{
$property=$class->getProperty($name);
if($property->isPublic() && !$property->isStatic())
{
$this->$name=$value;
unset($options[$name]);
}
}
}
}
if(!empty($options))
$this->usageError("Unknown options: ".implode(', ',array_keys($options)));
$exitCode=0;
if($this->beforeAction($action,$params))
{
$exitCode=$method->invokeArgs($this,$params);
$exitCode=$this->afterAction($action,$params,is_int($exitCode)?$exitCode:0);
}
return $exitCode;
}
protected function beforeAction($action,$params)
{
if($this->hasEventHandler('onBeforeAction'))
{
$event = new CConsoleCommandEvent($this,$params,$action);
$this->onBeforeAction($event);
return !$event->stopCommand;
}
else
{
return true;
}
}
protected function afterAction($action,$params,$exitCode=0)
{
$event=new CConsoleCommandEvent($this,$params,$action,$exitCode);
if($this->hasEventHandler('onAfterAction'))
$this->onAfterAction($event);
return $event->exitCode;
}
protected function resolveRequest($args)
{
$options=array();	// named parameters
$params=array();	// unnamed parameters
foreach($args as $arg)
{
if(preg_match('/^--(\w+)(=(.*))?$/',$arg,$matches))  // an option
{
$name=$matches[1];
$value=isset($matches[3]) ? $matches[3] : true;
if(isset($options[$name]))
{
if(!is_array($options[$name]))
$options[$name]=array($options[$name]);
$options[$name][]=$value;
}
else
$options[$name]=$value;
}
else if(isset($action))
$params[]=$arg;
else
$action=$arg;
}
if(!isset($action))
$action=$this->defaultAction;
return array($action,$options,$params);
}
public function getName()
{
return $this->_name;
}
public function getCommandRunner()
{
return $this->_runner;
}
public function getHelp()
{
$help='Usage: '.$this->getCommandRunner()->getScriptName().' '.$this->getName();
$options=$this->getOptionHelp();
if(empty($options))
return $help;
if(count($options)===1)
return $help.' '.$options[0];
$help.=" <action>\nActions:\n";
foreach($options as $option)
$help.='    '.$option."\n";
return $help;
}
public function getOptionHelp()
{
$options=array();
$class=new ReflectionClass(get_class($this));
foreach($class->getMethods(ReflectionMethod::IS_PUBLIC) as $method)
{
$name=$method->getName();
if(!strncasecmp($name,'action',6) && strlen($name)>6)
{
$name=substr($name,6);
$name[0]=strtolower($name[0]);
$help=$name;
foreach($method->getParameters() as $param)
{
$optional=$param->isDefaultValueAvailable();
$defaultValue=$optional ? $param->getDefaultValue() : null;
$name=$param->getName();
if($optional)
$help.=" [--$name=$defaultValue]";
else
$help.=" --$name=value";
}
$options[]=$help;
}
}
return $options;
}
public function usageError($message)
{
echo "Error: $message\n\n".$this->getHelp()."\n";
exit(1);
}
public function copyFiles($fileList)
{
$overwriteAll=false;
foreach($fileList as $name=>$file)
{
$source=strtr($file['source'],'/\\',DIRECTORY_SEPARATOR);
$target=strtr($file['target'],'/\\',DIRECTORY_SEPARATOR);
$callback=isset($file['callback']) ? $file['callback'] : null;
$params=isset($file['params']) ? $file['params'] : null;
if(is_dir($source))
{
$this->ensureDirectory($target);
continue;
}
if($callback!==null)
$content=call_user_func($callback,$source,$params);
else
$content=file_get_contents($source);
if(is_file($target))
{
if($content===file_get_contents($target))
{
echo "  unchanged $name\n";
continue;
}
if($overwriteAll)
echo "  overwrite $name\n";
else
{
echo "      exist $name\n";
echo "            ...overwrite? [Yes|No|All|Quit] ";
$answer=trim(fgets(STDIN));
if(!strncasecmp($answer,'q',1))
return;
else if(!strncasecmp($answer,'y',1))
echo "  overwrite $name\n";
else if(!strncasecmp($answer,'a',1))
{
echo "  overwrite $name\n";
$overwriteAll=true;
}
else
{
echo "       skip $name\n";
continue;
}
}
}
else
{
$this->ensureDirectory(dirname($target));
echo "   generate $name\n";
}
file_put_contents($target,$content);
}
}
public function buildFileList($sourceDir, $targetDir, $baseDir='', $ignoreFiles=array(), $renameMap=array())
{
$list=array();
$handle=opendir($sourceDir);
while(($file=readdir($handle))!==false)
{
if(in_array($file,array('.','..','.svn','.gitignore')) || in_array($file,$ignoreFiles))
continue;
$sourcePath=$sourceDir.DIRECTORY_SEPARATOR.$file;
$targetPath=$targetDir.DIRECTORY_SEPARATOR.strtr($file,$renameMap);
$name=$baseDir===''?$file : $baseDir.'/'.$file;
$list[$name]=array('source'=>$sourcePath, 'target'=>$targetPath);
if(is_dir($sourcePath))
$list=array_merge($list,$this->buildFileList($sourcePath,$targetPath,$name,$ignoreFiles,$renameMap));
}
closedir($handle);
return $list;
}
public function ensureDirectory($directory)
{
if(!is_dir($directory))
{
$this->ensureDirectory(dirname($directory));
echo "      mkdir ".strtr($directory,'\\','/')."\n";
mkdir($directory);
}
}
public function renderFile($_viewFile_,$_data_=null,$_return_=false)
{
if(is_array($_data_))
extract($_data_,EXTR_PREFIX_SAME,'data');
else
$data=$_data_;
if($_return_)
{
ob_start();
ob_implicit_flush(false);
require($_viewFile_);
return ob_get_clean();
}
else
require($_viewFile_);
}
public function pluralize($name)
{
$rules=array(
'/move$/i'=>'moves',
'/foot$/i'=>'feet',
'/child$/i'=>'children',
'/human$/i'=>'humans',
'/man$/i'=>'men',
'/tooth$/i'=>'teeth',
'/person$/i'=>'people',
'/([m|l])ouse$/i'=>'\1ice',
'/(x|ch|ss|sh|us|as|is|os)$/i'=>'\1es',
'/([^aeiouy]|qu)y$/i'=>'\1ies',
'/(?:([^f])fe|([lr])f)$/i'=>'\1\2ves',
'/(shea|lea|loa|thie)f$/i'=>'\1ves',
'/([ti])um$/i'=>'\1a',
'/(tomat|potat|ech|her|vet)o$/i'=>'\1oes',
'/(bu)s$/i'=>'\1ses',
'/(ax|test)is$/i'=>'\1es',
'/s$/'=>'s',
);
foreach($rules as $rule=>$replacement)
{
if(preg_match($rule,$name))
return preg_replace($rule,$replacement,$name);
}
return $name.'s';
}
public function prompt($message,$default=null)
{
if($default!==null)
$message.=" [$default] ";
else
$message.=' ';
if(extension_loaded('readline'))
{
$input=readline($message);
if($input!==false)
readline_add_history($input);
}
else
{
echo $message;
$input=fgets(STDIN);
}
if($input===false)
return false;
else{
$input=trim($input);
return ($input==='' && $default!==null) ? $default : $input;
}
}
public function confirm($message,$default=false)
{
echo $message.' (yes|no) [' . ($default ? 'yes' : 'no') . ']:';
$input = trim(fgets(STDIN));
return empty($input) ? $default : !strncasecmp($input,'y',1);
}
public function onBeforeAction($event)
{
$this->raiseEvent('onBeforeAction',$event);
}
public function onAfterAction($event)
{
$this->raiseEvent('onAfterAction',$event);
}
}
