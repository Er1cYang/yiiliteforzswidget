<?php
class CEmailLogRoute extends CLogRoute
{
private $_email=array();
private $_subject;
private $_from;
private $_headers=array();
protected function processLogs($logs)
{
$message='';
foreach($logs as $log)
$message.=$this->formatLogMessage($log[0],$log[1],$log[2],$log[3]);
$message=wordwrap($message,70);
$subject=$this->getSubject();
if($subject===null)
$subject=Yii::t('yii','Application Log');
foreach($this->getEmails() as $email)
$this->sendEmail($email,$subject,$message);
}
protected function sendEmail($email,$subject,$message)
{
$headers=$this->getHeaders();
if(($from=$this->getSentFrom())!==null)
$headers[]="From: {$from}";
mail($email,$subject,$message,implode("\r\n",$headers));
}
public function getEmails()
{
return $this->_email;
}
public function setEmails($value)
{
if(is_array($value))
$this->_email=$value;
else
$this->_email=preg_split('/[\s,]+/',$value,-1,PREG_SPLIT_NO_EMPTY);
}
public function getSubject()
{
return $this->_subject;
}
public function setSubject($value)
{
$this->_subject=$value;
}
public function getSentFrom()
{
return $this->_from;
}
public function setSentFrom($value)
{
$this->_from=$value;
}
public function getHeaders()
{
return $this->_headers;
}
public function setHeaders($value)
{
if (is_array($value))
$this->_headers=$value;
else
$this->_headers=preg_split('/\r\n|\n/',$value,-1,PREG_SPLIT_NO_EMPTY);
}
}