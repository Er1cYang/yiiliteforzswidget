<?php
class CLocale extends CComponent
{
public static $dataPath;
private $_id;
private $_data;
private $_dateFormatter;
private $_numberFormatter;
public static function getInstance($id)
{
static $locales=array();
if(isset($locales[$id]))
return $locales[$id];
else
return $locales[$id]=new CLocale($id);
}
public static function getLocaleIDs()
{
static $locales;
if($locales===null)
{
$locales=array();
$dataPath=self::$dataPath===null ? dirname(__FILE__).DIRECTORY_SEPARATOR.'data' : self::$dataPath;
$folder=@opendir($dataPath);
while(($file=@readdir($folder))!==false)
{
$fullPath=$dataPath.DIRECTORY_SEPARATOR.$file;
if(substr($file,-4)==='.php' && is_file($fullPath))
$locales[]=substr($file,0,-4);
}
closedir($folder);
sort($locales);
}
return $locales;
}
protected function __construct($id)
{
$this->_id=self::getCanonicalID($id);
$dataPath=self::$dataPath===null ? dirname(__FILE__).DIRECTORY_SEPARATOR.'data' : self::$dataPath;
$dataFile=$dataPath.DIRECTORY_SEPARATOR.$this->_id.'.php';
if(is_file($dataFile))
$this->_data=require($dataFile);
else
throw new CException(Yii::t('yii','Unrecognized locale "{locale}".',array('{locale}'=>$id)));
}
public static function getCanonicalID($id)
{
return strtolower(str_replace('-','_',$id));
}
public function getId()
{
return $this->_id;
}
public function getNumberFormatter()
{
if($this->_numberFormatter===null)
$this->_numberFormatter=new CNumberFormatter($this);
return $this->_numberFormatter;
}
public function getDateFormatter()
{
if($this->_dateFormatter===null)
$this->_dateFormatter=new CDateFormatter($this);
return $this->_dateFormatter;
}
public function getCurrencySymbol($currency)
{
return isset($this->_data['currencySymbols'][$currency]) ? $this->_data['currencySymbols'][$currency] : null;
}
public function getNumberSymbol($name)
{
return isset($this->_data['numberSymbols'][$name]) ? $this->_data['numberSymbols'][$name] : null;
}
public function getDecimalFormat()
{
return $this->_data['decimalFormat'];
}
public function getCurrencyFormat()
{
return $this->_data['currencyFormat'];
}
public function getPercentFormat()
{
return $this->_data['percentFormat'];
}
public function getScientificFormat()
{
return $this->_data['scientificFormat'];
}
public function getMonthName($month,$width='wide',$standAlone=false)
{
if($standAlone)
return isset($this->_data['monthNamesSA'][$width][$month]) ? $this->_data['monthNamesSA'][$width][$month] : $this->_data['monthNames'][$width][$month];
else
return isset($this->_data['monthNames'][$width][$month]) ? $this->_data['monthNames'][$width][$month] : $this->_data['monthNamesSA'][$width][$month];
}
public function getMonthNames($width='wide',$standAlone=false)
{
if($standAlone)
return isset($this->_data['monthNamesSA'][$width]) ? $this->_data['monthNamesSA'][$width] : $this->_data['monthNames'][$width];
else
return isset($this->_data['monthNames'][$width]) ? $this->_data['monthNames'][$width] : $this->_data['monthNamesSA'][$width];
}
public function getWeekDayName($day,$width='wide',$standAlone=false)
{
if($standAlone)
return isset($this->_data['weekDayNamesSA'][$width][$day]) ? $this->_data['weekDayNamesSA'][$width][$day] : $this->_data['weekDayNames'][$width][$day];
else
return isset($this->_data['weekDayNames'][$width][$day]) ? $this->_data['weekDayNames'][$width][$day] : $this->_data['weekDayNamesSA'][$width][$day];
}
public function getWeekDayNames($width='wide',$standAlone=false)
{
if($standAlone)
return isset($this->_data['weekDayNamesSA'][$width]) ? $this->_data['weekDayNamesSA'][$width] : $this->_data['weekDayNames'][$width];
else
return isset($this->_data['weekDayNames'][$width]) ? $this->_data['weekDayNames'][$width] : $this->_data['weekDayNamesSA'][$width];
}
public function getEraName($era,$width='wide')
{
return $this->_data['eraNames'][$width][$era];
}
public function getAMName()
{
return $this->_data['amName'];
}
public function getPMName()
{
return $this->_data['pmName'];
}
public function getDateFormat($width='medium')
{
return $this->_data['dateFormats'][$width];
}
public function getTimeFormat($width='medium')
{
return $this->_data['timeFormats'][$width];
}
public function getDateTimeFormat()
{
return $this->_data['dateTimeFormat'];
}
public function getOrientation()
{
return isset($this->_data['orientation']) ? $this->_data['orientation'] : 'ltr';
}
public function getPluralRules()
{
return isset($this->_data['pluralRules']) ? $this->_data['pluralRules'] : array();
}
public function getLanguageID($id)
{
$id = $this->getCanonicalID($id);
if(($underscorePosition=strpos($id, '_'))!== false)
{
$id = substr($id, 0, $underscorePosition);
}
return $id;
}
public function getScriptID($id)
{
$id = $this->getCanonicalID($id);
if(($underscorePosition=strpos($id, '_'))!==false)
{
$subTag = explode('_', $id);
if (strlen($subTag[1])===4)
{
$id = $subTag[1];
}
else
{
$id = null;
}
}
else
{
$id = null;
}
return $id;
}
public function getTerritoryID($id)
{
$id = $this->getCanonicalID($id);
if (($underscorePosition=strpos($id, '_'))!== false)
{
$subTag = explode('_', $id);
if (strlen($subTag[1])<4)
{
$id = $subTag[1];
}
else
{
$id = null;
}
}
else
{
$id = null;
}
return $id;
}
public function getLocaleDisplayName($id=null, $category='languages')
{
$id = $this->getCanonicalID($id);
if (isset($this->_data[$category][$id]))
{
return $this->_data[$category][$id];
}
else if (($category == 'languages') && ($id=$this->getLanguageID($id)) && (isset($this->_data[$category][$id])))
{
return $this->_data[$category][$id];
}
else if (($category == 'scripts') && ($id=$this->getScriptID($id)) && (isset($this->_data[$category][$id])))
{
return $this->_data[$category][$id];
}
else if (($category == 'territories') && ($id=$this->getTerritoryID($id)) && (isset($this->_data[$category][$id])))
{
return $this->_data[$category][$id];
}
else {
return null;
}
}
public function getLanguage($id)
{
return $this->getLocaleDisplayName($id, 'languages');
}
public function getScript($id)
{
return $this->getLocaleDisplayName($id, 'scripts');
}
public function getTerritory($id)
{
return $this->getLocaleDisplayName($id, 'territories');
}
}