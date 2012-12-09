<?php
class CConsoleCommandRunner extends CComponent
{
public $commands=array();
private $_scriptName;
public function run($args)
{
$this->_scriptName=$args[0];
array_shift($args);
if(isset($args[0]))
{
$name=$args[0];
array_shift($args);
}
else
$name='help';
if(($command=$this->createCommand($name))===null)
$command=$this->createCommand('help');
$command->init();
return $command->run($args);
}
public function getScriptName()
{
return $this->_scriptName;
}
public function findCommands($path)
{
if(($dir=@opendir($path))===false)
return array();
$commands=array();
while(($name=readdir($dir))!==false)
{
$file=$path.DIRECTORY_SEPARATOR.$name;
if(!strcasecmp(substr($name,-11),'Command.php') && is_file($file))
$commands[strtolower(substr($name,0,-11))]=$file;
}
closedir($dir);
return $commands;
}
public function addCommands($path)
{
if(($commands=$this->findCommands($path))!==array())
{
foreach($commands as $name=>$file)
{
if(!isset($this->commands[$name]))
$this->commands[$name]=$file;
}
}
}
public function createCommand($name)
{
$name=strtolower($name);
if(isset($this->commands[$name]))
{
if(is_string($this->commands[$name]))//class file path or alias
{
if(strpos($this->commands[$name],'/')!==false || strpos($this->commands[$name],'\\')!==false)
{
$className=substr(basename($this->commands[$name]),0,-4);
if(!class_exists($className,false))
require_once($this->commands[$name]);
}
else//an alias
$className=Yii::import($this->commands[$name]);
return new $className($name,$this);
}
else//an array configuration
return Yii::createComponent($this->commands[$name],$name,$this);
}
else if($name==='help')
return new CHelpCommand('help',$this);
else
return null;
}
}