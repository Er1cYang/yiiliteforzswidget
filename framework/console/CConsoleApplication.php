<?php
class CConsoleApplication extends CApplication
{
public $commandMap=array();
private $_commandPath;
private $_runner;
protected function init()
{
parent::init();
if(!isset($_SERVER['argv']))//|| strncasecmp(php_sapi_name(),'cli',3))
die('This script must be run from the command line.');
$this->_runner=$this->createCommandRunner();
$this->_runner->commands=$this->commandMap;
$this->_runner->addCommands($this->getCommandPath());
}
public function processRequest()
{
$exitCode=$this->_runner->run($_SERVER['argv']);
if(is_int($exitCode))
$this->end($exitCode);
}
protected function createCommandRunner()
{
return new CConsoleCommandRunner;
}
public function displayError($code,$message,$file,$line)
{
echo "PHP Error[$code]: $message\n";
echo "    in file $file at line $line\n";
$trace=debug_backtrace();
if(count($trace)>4)
$trace=array_slice($trace,4);
foreach($trace as $i=>$t)
{
if(!isset($t['file']))
$t['file']='unknown';
if(!isset($t['line']))
$t['line']=0;
if(!isset($t['function']))
$t['function']='unknown';
echo "#$i {$t['file']}({$t['line']}): ";
if(isset($t['object']) && is_object($t['object']))
echo get_class($t['object']).'->';
echo "{$t['function']}()\n";
}
}
public function displayException($exception)
{
echo $exception;
}
public function getCommandPath()
{
$applicationCommandPath = $this->getBasePath().DIRECTORY_SEPARATOR.'commands';
if($this->_commandPath===null && file_exists($applicationCommandPath))
$this->setCommandPath($applicationCommandPath);
return $this->_commandPath;
}
public function setCommandPath($value)
{
if(($this->_commandPath=realpath($value))===false || !is_dir($this->_commandPath))
throw new CException(Yii::t('yii','The command path "{path}" is not a valid directory.',
array('{path}'=>$value)));
}
public function getCommandRunner()
{
return $this->_runner;
}
}
