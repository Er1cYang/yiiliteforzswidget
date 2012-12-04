<?php
class CMysqlSchema extends CDbSchema
{
public $columnTypes=array(
'pk'=>'int(11) NOT NULL AUTO_INCREMENT PRIMARY KEY',
'string'=>'varchar(255)',
'text'=>'text',
'integer'=>'int(11)',
'float'=>'float',
'decimal'=>'decimal',
'datetime'=>'datetime',
'timestamp'=>'timestamp',
'time'=>'time',
'date'=>'date',
'binary'=>'blob',
'boolean'=>'tinyint(1)',
'money'=>'decimal(19,4)',
);
public function quoteSimpleTableName($name)
{
return '`'.$name.'`';
}
public function quoteSimpleColumnName($name)
{
return '`'.$name.'`';
}
public function compareTableNames($name1,$name2)
{
return parent::compareTableNames(strtolower($name1),strtolower($name2));
}
public function resetSequence($table,$value=null)
{
if($table->sequenceName!==null)
{
if($value===null)
$value=$this->getDbConnection()->createCommand("SELECT MAX(`{$table->primaryKey}`) FROM {$table->rawName}")->queryScalar()+1;
else
$value=(int)$value;
$this->getDbConnection()->createCommand("ALTER TABLE {$table->rawName} AUTO_INCREMENT=$value")->execute();
}
}
public function checkIntegrity($check=true,$schema='')
{
$this->getDbConnection()->createCommand('SET FOREIGN_KEY_CHECKS='.($check?1:0))->execute();
}
protected function loadTable($name)
{
$table=new CMysqlTableSchema;
$this->resolveTableNames($table,$name);
if($this->findColumns($table))
{
$this->findConstraints($table);
return $table;
}
else
return null;
}
protected function resolveTableNames($table,$name)
{
$parts=explode('.',str_replace('`','',$name));
if(isset($parts[1]))
{
$table->schemaName=$parts[0];
$table->name=$parts[1];
$table->rawName=$this->quoteTableName($table->schemaName).'.'.$this->quoteTableName($table->name);
}
else
{
$table->name=$parts[0];
$table->rawName=$this->quoteTableName($table->name);
}
}
protected function findColumns($table)
{
$sql='SHOW COLUMNS FROM '.$table->rawName;
try
{
$columns=$this->getDbConnection()->createCommand($sql)->queryAll();
}
catch(Exception $e)
{
return false;
}
foreach($columns as $column)
{
$c=$this->createColumn($column);
$table->columns[$c->name]=$c;
if($c->isPrimaryKey)
{
if($table->primaryKey===null)
$table->primaryKey=$c->name;
else if(is_string($table->primaryKey))
$table->primaryKey=array($table->primaryKey,$c->name);
else
$table->primaryKey[]=$c->name;
if($c->autoIncrement)
$table->sequenceName='';
}
}
return true;
}
protected function createColumn($column)
{
$c=new CMysqlColumnSchema;
$c->name=$column['Field'];
$c->rawName=$this->quoteColumnName($c->name);
$c->allowNull=$column['Null']==='YES';
$c->isPrimaryKey=strpos($column['Key'],'PRI')!==false;
$c->isForeignKey=false;
$c->init($column['Type'],$column['Default']);
$c->autoIncrement=strpos(strtolower($column['Extra']),'auto_increment')!==false;
return $c;
}
protected function getServerVersion()
{
$version=$this->getDbConnection()->getAttribute(PDO::ATTR_SERVER_VERSION);
$digits=array();
preg_match('/(\d+)\.(\d+)\.(\d+)/', $version, $digits);
return floatval($digits[1].'.'.$digits[2].$digits[3]);
}
protected function findConstraints($table)
{
$row=$this->getDbConnection()->createCommand('SHOW CREATE TABLE '.$table->rawName)->queryRow();
$matches=array();
$regexp='/FOREIGN KEY\s+\(([^\)]+)\)\s+REFERENCES\s+([^\(^\s]+)\s*\(([^\)]+)\)/mi';
foreach($row as $sql)
{
if(preg_match_all($regexp,$sql,$matches,PREG_SET_ORDER))
break;
}
foreach($matches as $match)
{
$keys=array_map('trim',explode(',',str_replace('`','',$match[1])));
$fks=array_map('trim',explode(',',str_replace('`','',$match[3])));
foreach($keys as $k=>$name)
{
$table->foreignKeys[$name]=array(str_replace('`','',$match[2]),$fks[$k]);
if(isset($table->columns[$name]))
$table->columns[$name]->isForeignKey=true;
}
}
}
protected function findTableNames($schema='')
{
if($schema==='')
return $this->getDbConnection()->createCommand('SHOW TABLES')->queryColumn();
$names=$this->getDbConnection()->createCommand('SHOW TABLES FROM '.$this->quoteTableName($schema))->queryColumn();
foreach($names as &$name)
$name=$schema.'.'.$name;
return $names;
}
public function renameColumn($table, $name, $newName)
{
$db=$this->getDbConnection();
$row=$db->createCommand('SHOW CREATE TABLE '.$db->quoteTableName($table))->queryRow();
if($row===false)
throw new CDbException(Yii::t('yii','Unable to find "{column}" in table "{table}".',array('{column}'=>$name,'{table}'=>$table)));
if(isset($row['Create Table']))
$sql=$row['Create Table'];
else
{
$row=array_values($row);
$sql=$row[1];
}
if(preg_match_all('/^\s*`(.*?)`\s+(.*?),?$/m',$sql,$matches))
{
foreach($matches[1] as $i=>$c)
{
if($c===$name)
{
return "ALTER TABLE ".$db->quoteTableName($table)
. " CHANGE ".$db->quoteColumnName($name)
. ' '.$db->quoteColumnName($newName).' '.$matches[2][$i];
}
}
}
return "ALTER TABLE ".$db->quoteTableName($table)
. " CHANGE ".$db->quoteColumnName($name).' '.$newName;
}
public function dropForeignKey($name, $table)
{
return 'ALTER TABLE '.$this->quoteTableName($table)
.' DROP FOREIGN KEY '.$this->quoteColumnName($name);
}
}
