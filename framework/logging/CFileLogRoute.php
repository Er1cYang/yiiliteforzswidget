<?php
class CFileLogRoute extends CLogRoute
{
private $_maxFileSize=1024;//in KB
private $_maxLogFiles=5;
private $_logPath;
private $_logFile='application.log';
public function init()
{
parent::init();
if($this->getLogPath()===null)
$this->setLogPath(Yii::app()->getRuntimePath());
}
public function getLogPath()
{
return $this->_logPath;
}
public function setLogPath($value)
{
$this->_logPath=realpath($value);
if($this->_logPath===false || !is_dir($this->_logPath) || !is_writable($this->_logPath))
throw new CException(Yii::t('yii','CFileLogRoute.logPath "{path}" does not point to a valid directory. Make sure the directory exists and is writable by the Web server process.',
array('{path}'=>$value)));
}
public function getLogFile()
{
return $this->_logFile;
}
public function setLogFile($value)
{
$this->_logFile=$value;
}
public function getMaxFileSize()
{
return $this->_maxFileSize;
}
public function setMaxFileSize($value)
{
if(($this->_maxFileSize=(int)$value)<1)
$this->_maxFileSize=1;
}
public function getMaxLogFiles()
{
return $this->_maxLogFiles;
}
public function setMaxLogFiles($value)
{
if(($this->_maxLogFiles=(int)$value)<1)
$this->_maxLogFiles=1;
}
protected function processLogs($logs)
{
$logFile=$this->getLogPath().DIRECTORY_SEPARATOR.$this->getLogFile();
if(@filesize($logFile)>$this->getMaxFileSize()*1024)
$this->rotateFiles();
$fp=@fopen($logFile,'a');
@flock($fp,LOCK_EX);
foreach($logs as $log)
@fwrite($fp,$this->formatLogMessage($log[0],$log[1],$log[2],$log[3]));
@flock($fp,LOCK_UN);
@fclose($fp);
}
protected function rotateFiles()
{
$file=$this->getLogPath().DIRECTORY_SEPARATOR.$this->getLogFile();
$max=$this->getMaxLogFiles();
for($i=$max;$i>0;--$i)
{
$rotateFile=$file.'.'.$i;
if(is_file($rotateFile))
{
if($i===$max)
@unlink($rotateFile);
else
@rename($rotateFile,$file.'.'.($i+1));
}
}
if(is_file($file))
@rename($file,$file.'.1');//suppress errors because it's possible multiple processes enter into this section
}
}
