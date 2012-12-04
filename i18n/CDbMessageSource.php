<?php
class CDbMessageSource extends CMessageSource
{
const CACHE_KEY_PREFIX='Yii.CDbMessageSource.';
public $connectionID='db';
public $sourceMessageTable='SourceMessage';
public $translatedMessageTable='Message';
public $cachingDuration=0;
public $cacheID='cache';
protected function loadMessages($category,$language)
{
if($this->cachingDuration>0 && $this->cacheID!==false && ($cache=Yii::app()->getComponent($this->cacheID))!==null)
{
$key=self::CACHE_KEY_PREFIX.'.messages.'.$category.'.'.$language;
if(($data=$cache->get($key))!==false)
return unserialize($data);
}
$messages=$this->loadMessagesFromDb($category,$language);
if(isset($cache))
$cache->set($key,serialize($messages),$this->cachingDuration);
return $messages;
}
private $_db;
public function getDbConnection()
{
if($this->_db===null)
{
$this->_db=Yii::app()->getComponent($this->connectionID);
if(!$this->_db instanceof CDbConnection)
throw new CException(Yii::t('yii','CDbMessageSource.connectionID is invalid. Please make sure "{id}" refers to a valid database application component.',
array('{id}'=>$this->connectionID)));
}
return $this->_db;
}
protected function loadMessagesFromDb($category,$language)
{
$sql=<<<EOD
SELECT t1.message AS message, t2.translation AS translation
FROM {$this->sourceMessageTable} t1, {$this->translatedMessageTable} t2
WHERE t1.id=t2.id AND t1.category=:category AND t2.language=:language
EOD;
$command=$this->getDbConnection()->createCommand($sql);
$command->bindValue(':category',$category);
$command->bindValue(':language',$language);
$messages=array();
foreach($command->queryAll() as $row)
$messages[$row['message']]=$row['translation'];
return $messages;
}
}