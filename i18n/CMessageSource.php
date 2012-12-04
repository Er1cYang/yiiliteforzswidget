<?php
abstract class CMessageSource extends CApplicationComponent
{
public $forceTranslation=false;
private $_language;
private $_messages=array();
abstract protected function loadMessages($category,$language);
public function getLanguage()
{
return $this->_language===null ? Yii::app()->sourceLanguage : $this->_language;
}
public function setLanguage($language)
{
$this->_language=CLocale::getCanonicalID($language);
}
public function translate($category,$message,$language=null)
{
if($language===null)
$language=Yii::app()->getLanguage();
if($this->forceTranslation || $language!==$this->getLanguage())
return $this->translateMessage($category,$message,$language);
else
return $message;
}
protected function translateMessage($category,$message,$language)
{
$key=$language.'.'.$category;
if(!isset($this->_messages[$key]))
$this->_messages[$key]=$this->loadMessages($category,$language);
if(isset($this->_messages[$key][$message]) && $this->_messages[$key][$message]!=='')
return $this->_messages[$key][$message];
else if($this->hasEventHandler('onMissingTranslation'))
{
$event=new CMissingTranslationEvent($this,$category,$message,$language);
$this->onMissingTranslation($event);
return $event->message;
}
else
return $message;
}
public function onMissingTranslation($event)
{
$this->raiseEvent('onMissingTranslation',$event);
}
}
class CMissingTranslationEvent extends CEvent
{
public $message;
public $category;
public $language;
public function __construct($sender,$category,$message,$language)
{
parent::__construct($sender);
$this->message=$message;
$this->category=$category;
$this->language=$language;
}
}
