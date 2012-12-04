<?php
class CDbConnection extends CApplicationComponent
{
public $connectionString;
public $username='';
public $password='';
public $schemaCachingDuration=0;
public $schemaCachingExclude=array();
public $schemaCacheID='cache';
public $queryCachingDuration=0;
public $queryCachingDependency;
public $queryCachingCount=0;
public $queryCacheID='cache';
public $autoConnect=true;
public $charset;
public $emulatePrepare;
public $enableParamLogging=false;
public $enableProfiling=false;
public $tablePrefix;
public $initSQLs;
public $driverMap=array(
'pgsql'=>'CPgsqlSchema',    // PostgreSQL
'mysqli'=>'CMysqlSchema',   // MySQL
'mysql'=>'CMysqlSchema',    // MySQL
'sqlite'=>'CSqliteSchema',  // sqlite 3
'sqlite2'=>'CSqliteSchema', // sqlite 2
'mssql'=>'CMssqlSchema',    // Mssql driver on windows hosts
'dblib'=>'CMssqlSchema',    // dblib drivers on linux (and maybe others os) hosts
'sqlsrv'=>'CMssqlSchema',   // Mssql
'oci'=>'COciSchema',        // Oracle driver
);
public $pdoClass = 'PDO';
private $_attributes=array();
private $_active=false;
private $_pdo;
private $_transaction;
private $_schema;
public function __construct($dsn='',$username='',$password='')
{
$this->connectionString=$dsn;
$this->username=$username;
$this->password=$password;
}
public function __sleep()
{
$this->close();
return array_keys(get_object_vars($this));
}
public static function getAvailableDrivers()
{
return PDO::getAvailableDrivers();
}
public function init()
{
parent::init();
if($this->autoConnect)
$this->setActive(true);
}
public function getActive()
{
return $this->_active;
}
public function setActive($value)
{
if($value!=$this->_active)
{
if($value)
$this->open();
else
$this->close();
}
}
public function cache($duration, $dependency=null, $queryCount=1)
{
$this->queryCachingDuration=$duration;
$this->queryCachingDependency=$dependency;
$this->queryCachingCount=$queryCount;
return $this;
}
protected function open()
{
if($this->_pdo===null)
{
if(empty($this->connectionString))
throw new CDbException('CDbConnection.connectionString cannot be empty.');
try
{
Yii::trace('Opening DB connection','system.db.CDbConnection');
$this->_pdo=$this->createPdoInstance();
$this->initConnection($this->_pdo);
$this->_active=true;
}
catch(PDOException $e)
{
if(YII_DEBUG)
{
throw new CDbException('CDbConnection failed to open the DB connection: '.
$e->getMessage(),(int)$e->getCode(),$e->errorInfo);
}
else
{
Yii::log($e->getMessage(),CLogger::LEVEL_ERROR,'exception.CDbException');
throw new CDbException('CDbConnection failed to open the DB connection.',(int)$e->getCode(),$e->errorInfo);
}
}
}
}
protected function close()
{
Yii::trace('Closing DB connection','system.db.CDbConnection');
$this->_pdo=null;
$this->_active=false;
$this->_schema=null;
}
protected function createPdoInstance()
{
$pdoClass=$this->pdoClass;
if(($pos=strpos($this->connectionString,':'))!==false)
{
$driver=strtolower(substr($this->connectionString,0,$pos));
if($driver==='mssql' || $driver==='dblib' || $driver==='sqlsrv')
$pdoClass='CMssqlPdoAdapter';
}
return new $pdoClass($this->connectionString,$this->username,
$this->password,$this->_attributes);
}
protected function initConnection($pdo)
{
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
if($this->emulatePrepare!==null && constant('PDO::ATTR_EMULATE_PREPARES'))
$pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES,$this->emulatePrepare);
if($this->charset!==null)
{
$driver=strtolower($pdo->getAttribute(PDO::ATTR_DRIVER_NAME));
if(in_array($driver,array('pgsql','mysql','mysqli')))
$pdo->exec('SET NAMES '.$pdo->quote($this->charset));
}
if($this->initSQLs!==null)
{
foreach($this->initSQLs as $sql)
$pdo->exec($sql);
}
}
public function getPdoInstance()
{
return $this->_pdo;
}
public function createCommand($query=null)
{
$this->setActive(true);
return new CDbCommand($this,$query);
}
public function getCurrentTransaction()
{
if($this->_transaction!==null)
{
if($this->_transaction->getActive())
return $this->_transaction;
}
return null;
}
public function beginTransaction()
{
Yii::trace('Starting transaction','system.db.CDbConnection');
$this->setActive(true);
$this->_pdo->beginTransaction();
return $this->_transaction=new CDbTransaction($this);
}
public function getSchema()
{
if($this->_schema!==null)
return $this->_schema;
else
{
$driver=$this->getDriverName();
if(isset($this->driverMap[$driver]))
return $this->_schema=Yii::createComponent($this->driverMap[$driver], $this);
else
throw new CDbException(Yii::t('yii','CDbConnection does not support reading schema for {driver} database.',
array('{driver}'=>$driver)));
}
}
public function getCommandBuilder()
{
return $this->getSchema()->getCommandBuilder();
}
public function getLastInsertID($sequenceName='')
{
$this->setActive(true);
return $this->_pdo->lastInsertId($sequenceName);
}
public function quoteValue($str)
{
if(is_int($str) || is_float($str))
return $str;
$this->setActive(true);
if(($value=$this->_pdo->quote($str))!==false)
return $value;
else  // the driver doesn't support quote (e.g. oci)
return "'" . addcslashes(str_replace("'", "''", $str), "\000\n\r\\\032") . "'";
}
public function quoteTableName($name)
{
return $this->getSchema()->quoteTableName($name);
}
public function quoteColumnName($name)
{
return $this->getSchema()->quoteColumnName($name);
}
public function getPdoType($type)
{
static $map=array
(
'boolean'=>PDO::PARAM_BOOL,
'integer'=>PDO::PARAM_INT,
'string'=>PDO::PARAM_STR,
'NULL'=>PDO::PARAM_NULL,
);
return isset($map[$type]) ? $map[$type] : PDO::PARAM_STR;
}
public function getColumnCase()
{
return $this->getAttribute(PDO::ATTR_CASE);
}
public function setColumnCase($value)
{
$this->setAttribute(PDO::ATTR_CASE,$value);
}
public function getNullConversion()
{
return $this->getAttribute(PDO::ATTR_ORACLE_NULLS);
}
public function setNullConversion($value)
{
$this->setAttribute(PDO::ATTR_ORACLE_NULLS,$value);
}
public function getAutoCommit()
{
return $this->getAttribute(PDO::ATTR_AUTOCOMMIT);
}
public function setAutoCommit($value)
{
$this->setAttribute(PDO::ATTR_AUTOCOMMIT,$value);
}
public function getPersistent()
{
return $this->getAttribute(PDO::ATTR_PERSISTENT);
}
public function setPersistent($value)
{
return $this->setAttribute(PDO::ATTR_PERSISTENT,$value);
}
public function getDriverName()
{
if(($pos=strpos($this->connectionString, ':'))!==false)
return strtolower(substr($this->connectionString, 0, $pos));
}
public function getClientVersion()
{
return $this->getAttribute(PDO::ATTR_CLIENT_VERSION);
}
public function getConnectionStatus()
{
return $this->getAttribute(PDO::ATTR_CONNECTION_STATUS);
}
public function getPrefetch()
{
return $this->getAttribute(PDO::ATTR_PREFETCH);
}
public function getServerInfo()
{
return $this->getAttribute(PDO::ATTR_SERVER_INFO);
}
public function getServerVersion()
{
return $this->getAttribute(PDO::ATTR_SERVER_VERSION);
}
public function getTimeout()
{
return $this->getAttribute(PDO::ATTR_TIMEOUT);
}
public function getAttribute($name)
{
$this->setActive(true);
return $this->_pdo->getAttribute($name);
}
public function setAttribute($name,$value)
{
if($this->_pdo instanceof PDO)
$this->_pdo->setAttribute($name,$value);
else
$this->_attributes[$name]=$value;
}
public function getAttributes()
{
return $this->_attributes;
}
public function setAttributes($values)
{
foreach($values as $name=>$value)
$this->_attributes[$name]=$value;
}
public function getStats()
{
$logger=Yii::getLogger();
$timings=$logger->getProfilingResults(null,'system.db.CDbCommand.query');
$count=count($timings);
$time=array_sum($timings);
$timings=$logger->getProfilingResults(null,'system.db.CDbCommand.execute');
$count+=count($timings);
$time+=array_sum($timings);
return array($count,$time);
}
}
