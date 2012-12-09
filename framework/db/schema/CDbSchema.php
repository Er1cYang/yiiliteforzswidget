<?php
abstract class CDbSchema extends CComponent
{
public $columnTypes=array();
private $_tableNames=array();
private $_tables=array();
private $_connection;
private $_builder;
private $_cacheExclude=array();
abstract protected function loadTable($name);
public function __construct($conn)
{
$this->_connection=$conn;
foreach($conn->schemaCachingExclude as $name)
$this->_cacheExclude[$name]=true;
}
public function getDbConnection()
{
return $this->_connection;
}
public function getTable($name,$refresh=false)
{
if($refresh===false && isset($this->_tables[$name]))
return $this->_tables[$name];
else
{
if($this->_connection->tablePrefix!==null && strpos($name,'{{')!==false)
$realName=preg_replace('/\{\{(.*?)\}\}/',$this->_connection->tablePrefix.'$1',$name);
else
$realName=$name;
if($this->_connection->queryCachingDuration>0)
{
$qcDuration=$this->_connection->queryCachingDuration;
$this->_connection->queryCachingDuration=0;
}
if(!isset($this->_cacheExclude[$name]) && ($duration=$this->_connection->schemaCachingDuration)>0 && $this->_connection->schemaCacheID!==false && ($cache=Yii::app()->getComponent($this->_connection->schemaCacheID))!==null)
{
$key='yii:dbschema'.$this->_connection->connectionString.':'.$this->_connection->username.':'.$name;
$table=$cache->get($key);
if($refresh===true || $table===false)
{
$table=$this->loadTable($realName);
if($table!==null)
$cache->set($key,$table,$duration);
}
$this->_tables[$name]=$table;
}
else
$this->_tables[$name]=$table=$this->loadTable($realName);
if(isset($qcDuration))//re-enable query caching
$this->_connection->queryCachingDuration=$qcDuration;
return $table;
}
}
public function getTables($schema='')
{
$tables=array();
foreach($this->getTableNames($schema) as $name)
{
if(($table=$this->getTable($name))!==null)
$tables[$name]=$table;
}
return $tables;
}
public function getTableNames($schema='')
{
if(!isset($this->_tableNames[$schema]))
$this->_tableNames[$schema]=$this->findTableNames($schema);
return $this->_tableNames[$schema];
}
public function getCommandBuilder()
{
if($this->_builder!==null)
return $this->_builder;
else
return $this->_builder=$this->createCommandBuilder();
}
public function refresh()
{
if(($duration=$this->_connection->schemaCachingDuration)>0 && $this->_connection->schemaCacheID!==false && ($cache=Yii::app()->getComponent($this->_connection->schemaCacheID))!==null)
{
foreach(array_keys($this->_tables) as $name)
{
if(!isset($this->_cacheExclude[$name]))
{
$key='yii:dbschema'.$this->_connection->connectionString.':'.$this->_connection->username.':'.$name;
$cache->delete($key);
}
}
}
$this->_tables=array();
$this->_tableNames=array();
$this->_builder=null;
}
public function quoteTableName($name)
{
if(strpos($name,'.')===false)
return $this->quoteSimpleTableName($name);
$parts=explode('.',$name);
foreach($parts as $i=>$part)
$parts[$i]=$this->quoteSimpleTableName($part);
return implode('.',$parts);
}
public function quoteSimpleTableName($name)
{
return "'".$name."'";
}
public function quoteColumnName($name)
{
if(($pos=strrpos($name,'.'))!==false)
{
$prefix=$this->quoteTableName(substr($name,0,$pos)).'.';
$name=substr($name,$pos+1);
}
else
$prefix='';
return $prefix . ($name==='*' ? $name : $this->quoteSimpleColumnName($name));
}
public function quoteSimpleColumnName($name)
{
return '"'.$name.'"';
}
public function compareTableNames($name1,$name2)
{
$name1=str_replace(array('"','`',"'"),'',$name1);
$name2=str_replace(array('"','`',"'"),'',$name2);
if(($pos=strrpos($name1,'.'))!==false)
$name1=substr($name1,$pos+1);
if(($pos=strrpos($name2,'.'))!==false)
$name2=substr($name2,$pos+1);
if($this->_connection->tablePrefix!==null)
{
if(strpos($name1,'{')!==false)
$name1=$this->_connection->tablePrefix.str_replace(array('{','}'),'',$name1);
if(strpos($name2,'{')!==false)
$name2=$this->_connection->tablePrefix.str_replace(array('{','}'),'',$name2);
}
return $name1===$name2;
}
public function resetSequence($table,$value=null)
{
}
public function checkIntegrity($check=true,$schema='')
{
}
protected function createCommandBuilder()
{
return new CDbCommandBuilder($this);
}
protected function findTableNames($schema='')
{
throw new CDbException(Yii::t('yii','{class} does not support fetching all table names.',
array('{class}'=>get_class($this))));
}
public function getColumnType($type)
{
if(isset($this->columnTypes[$type]))
return $this->columnTypes[$type];
else if(($pos=strpos($type,' '))!==false)
{
$t=substr($type,0,$pos);
return (isset($this->columnTypes[$t]) ? $this->columnTypes[$t] : $t).substr($type,$pos);
}
else
return $type;
}
public function createTable($table, $columns, $options=null)
{
$cols=array();
foreach($columns as $name=>$type)
{
if(is_string($name))
$cols[]="\t".$this->quoteColumnName($name).' '.$this->getColumnType($type);
else
$cols[]="\t".$type;
}
$sql="CREATE TABLE ".$this->quoteTableName($table)." (\n".implode(",\n",$cols)."\n)";
return $options===null ? $sql : $sql.' '.$options;
}
public function renameTable($table, $newName)
{
return 'RENAME TABLE ' . $this->quoteTableName($table) . ' TO ' . $this->quoteTableName($newName);
}
public function dropTable($table)
{
return "DROP TABLE ".$this->quoteTableName($table);
}
public function truncateTable($table)
{
return "TRUNCATE TABLE ".$this->quoteTableName($table);
}
public function addColumn($table, $column, $type)
{
return 'ALTER TABLE ' . $this->quoteTableName($table)
. ' ADD ' . $this->quoteColumnName($column) . ' '
. $this->getColumnType($type);
}
public function dropColumn($table, $column)
{
return "ALTER TABLE ".$this->quoteTableName($table)
." DROP COLUMN ".$this->quoteColumnName($column);
}
public function renameColumn($table, $name, $newName)
{
return "ALTER TABLE ".$this->quoteTableName($table)
. " RENAME COLUMN ".$this->quoteColumnName($name)
. " TO ".$this->quoteColumnName($newName);
}
public function alterColumn($table, $column, $type)
{
return 'ALTER TABLE ' . $this->quoteTableName($table) . ' CHANGE '
. $this->quoteColumnName($column) . ' '
. $this->quoteColumnName($column) . ' '
. $this->getColumnType($type);
}
public function addForeignKey($name, $table, $columns, $refTable, $refColumns, $delete=null, $update=null)
{
$columns=preg_split('/\s*,\s*/',$columns,-1,PREG_SPLIT_NO_EMPTY);
foreach($columns as $i=>$col)
$columns[$i]=$this->quoteColumnName($col);
$refColumns=preg_split('/\s*,\s*/',$refColumns,-1,PREG_SPLIT_NO_EMPTY);
foreach($refColumns as $i=>$col)
$refColumns[$i]=$this->quoteColumnName($col);
$sql='ALTER TABLE '.$this->quoteTableName($table)
.' ADD CONSTRAINT '.$this->quoteColumnName($name)
.' FOREIGN KEY ('.implode(', ', $columns).')'
.' REFERENCES '.$this->quoteTableName($refTable)
.' ('.implode(', ', $refColumns).')';
if($delete!==null)
$sql.=' ON DELETE '.$delete;
if($update!==null)
$sql.=' ON UPDATE '.$update;
return $sql;
}
public function dropForeignKey($name, $table)
{
return 'ALTER TABLE '.$this->quoteTableName($table)
.' DROP CONSTRAINT '.$this->quoteColumnName($name);
}
public function createIndex($name, $table, $column, $unique=false)
{
$cols=array();
$columns=preg_split('/\s*,\s*/',$column,-1,PREG_SPLIT_NO_EMPTY);
foreach($columns as $col)
{
if(strpos($col,'(')!==false)
$cols[]=$col;
else
$cols[]=$this->quoteColumnName($col);
}
return ($unique ? 'CREATE UNIQUE INDEX ' : 'CREATE INDEX ')
. $this->quoteTableName($name).' ON '
. $this->quoteTableName($table).' ('.implode(', ',$cols).')';
}
public function dropIndex($name, $table)
{
return 'DROP INDEX '.$this->quoteTableName($name).' ON '.$this->quoteTableName($table);
}
}
