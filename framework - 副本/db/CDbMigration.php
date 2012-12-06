<?php
abstract class CDbMigration extends CComponent
{
private $_db;
public function up()
{
$transaction=$this->getDbConnection()->beginTransaction();
try
{
if($this->safeUp()===false)
{
$transaction->rollback();
return false;
}
$transaction->commit();
}
catch(Exception $e)
{
echo "Exception: ".$e->getMessage().' ('.$e->getFile().':'.$e->getLine().")\n";
echo $e->getTraceAsString()."\n";
$transaction->rollback();
return false;
}
}
public function down()
{
$transaction=$this->getDbConnection()->beginTransaction();
try
{
if($this->safeDown()===false)
{
$transaction->rollback();
return false;
}
$transaction->commit();
}
catch(Exception $e)
{
echo "Exception: ".$e->getMessage().' ('.$e->getFile().':'.$e->getLine().")\n";
echo $e->getTraceAsString()."\n";
$transaction->rollback();
return false;
}
}
public function safeUp()
{
}
public function safeDown()
{
}
public function getDbConnection()
{
if($this->_db===null)
{
$this->_db=Yii::app()->getComponent('db');
if(!$this->_db instanceof CDbConnection)
throw new CException(Yii::t('yii', 'The "db" application component must be configured to be a CDbConnection object.'));
}
return $this->_db;
}
public function setDbConnection($db)
{
$this->_db=$db;
}
public function execute($sql, $params=array())
{
echo "    > execute SQL: $sql ...";
$time=microtime(true);
$this->getDbConnection()->createCommand($sql)->execute($params);
echo " done (time: ".sprintf('%.3f', microtime(true)-$time)."s)\n";
}
public function insert($table, $columns)
{
echo "    > insert into $table ...";
$time=microtime(true);
$this->getDbConnection()->createCommand()->insert($table, $columns);
echo " done (time: ".sprintf('%.3f', microtime(true)-$time)."s)\n";
}
public function update($table, $columns, $conditions='', $params=array())
{
echo "    > update $table ...";
$time=microtime(true);
$this->getDbConnection()->createCommand()->update($table, $columns, $conditions, $params);
echo " done (time: ".sprintf('%.3f', microtime(true)-$time)."s)\n";
}
public function delete($table, $conditions='', $params=array())
{
echo "    > delete from $table ...";
$time=microtime(true);
$this->getDbConnection()->createCommand()->delete($table, $conditions, $params);
echo " done (time: ".sprintf('%.3f', microtime(true)-$time)."s)\n";
}
public function createTable($table, $columns, $options=null)
{
echo "    > create table $table ...";
$time=microtime(true);
$this->getDbConnection()->createCommand()->createTable($table, $columns, $options);
echo " done (time: ".sprintf('%.3f', microtime(true)-$time)."s)\n";
}
public function renameTable($table, $newName)
{
echo "    > rename table $table to $newName ...";
$time=microtime(true);
$this->getDbConnection()->createCommand()->renameTable($table, $newName);
echo " done (time: ".sprintf('%.3f', microtime(true)-$time)."s)\n";
}
public function dropTable($table)
{
echo "    > drop table $table ...";
$time=microtime(true);
$this->getDbConnection()->createCommand()->dropTable($table);
echo " done (time: ".sprintf('%.3f', microtime(true)-$time)."s)\n";
}
public function truncateTable($table)
{
echo "    > truncate table $table ...";
$time=microtime(true);
$this->getDbConnection()->createCommand()->truncateTable($table);
echo " done (time: ".sprintf('%.3f', microtime(true)-$time)."s)\n";
}
public function addColumn($table, $column, $type)
{
echo "    > add column $column $type to table $table ...";
$time=microtime(true);
$this->getDbConnection()->createCommand()->addColumn($table, $column, $type);
echo " done (time: ".sprintf('%.3f', microtime(true)-$time)."s)\n";
}
public function dropColumn($table, $column)
{
echo "    > drop column $column from table $table ...";
$time=microtime(true);
$this->getDbConnection()->createCommand()->dropColumn($table, $column);
echo " done (time: ".sprintf('%.3f', microtime(true)-$time)."s)\n";
}
public function renameColumn($table, $name, $newName)
{
echo "    > rename column $name in table $table to $newName ...";
$time=microtime(true);
$this->getDbConnection()->createCommand()->renameColumn($table, $name, $newName);
echo " done (time: ".sprintf('%.3f', microtime(true)-$time)."s)\n";
}
public function alterColumn($table, $column, $type)
{
echo "    > alter column $column in table $table to $type ...";
$time=microtime(true);
$this->getDbConnection()->createCommand()->alterColumn($table, $column, $type);
echo " done (time: ".sprintf('%.3f', microtime(true)-$time)."s)\n";
}
public function addForeignKey($name, $table, $columns, $refTable, $refColumns, $delete=null, $update=null)
{
echo "    > add foreign key $name: $table ($columns) references $refTable ($refColumns) ...";
$time=microtime(true);
$this->getDbConnection()->createCommand()->addForeignKey($name, $table, $columns, $refTable, $refColumns, $delete, $update);
echo " done (time: ".sprintf('%.3f', microtime(true)-$time)."s)\n";
}
public function dropForeignKey($name, $table)
{
echo "    > drop foreign key $name from table $table ...";
$time=microtime(true);
$this->getDbConnection()->createCommand()->dropForeignKey($name, $table);
echo " done (time: ".sprintf('%.3f', microtime(true)-$time)."s)\n";
}
public function createIndex($name, $table, $column, $unique=false)
{
echo "    > create".($unique ? ' unique':'')." index $name on $table ($column) ...";
$time=microtime(true);
$this->getDbConnection()->createCommand()->createIndex($name, $table, $column, $unique);
echo " done (time: ".sprintf('%.3f', microtime(true)-$time)."s)\n";
}
public function dropIndex($name, $table)
{
echo "    > drop index $name ...";
$time=microtime(true);
$this->getDbConnection()->createCommand()->dropIndex($name, $table);
echo " done (time: ".sprintf('%.3f', microtime(true)-$time)."s)\n";
}
public function refreshTableSchema($table)
{
echo "    > refresh table $table schema cache ...";
$time=microtime(true);
$this->getDbConnection()->getSchema()->getTable($table,true);
echo " done (time: ".sprintf('%.3f', microtime(true)-$time)."s)\n";
}
}