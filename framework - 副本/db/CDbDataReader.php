<?php
class CDbDataReader extends CComponent implements Iterator, Countable
{
private $_statement;
private $_closed=false;
private $_row;
private $_index=-1;
public function __construct(CDbCommand $command)
{
$this->_statement=$command->getPdoStatement();
$this->_statement->setFetchMode(PDO::FETCH_ASSOC);
}
public function bindColumn($column, &$value, $dataType=null)
{
if($dataType===null)
$this->_statement->bindColumn($column,$value);
else
$this->_statement->bindColumn($column,$value,$dataType);
}
public function setFetchMode($mode)
{
$params=func_get_args();
call_user_func_array(array($this->_statement,'setFetchMode'),$params);
}
public function read()
{
return $this->_statement->fetch();
}
public function readColumn($columnIndex)
{
return $this->_statement->fetchColumn($columnIndex);
}
public function readObject($className,$fields)
{
return $this->_statement->fetchObject($className,$fields);
}
public function readAll()
{
return $this->_statement->fetchAll();
}
public function nextResult()
{
if(($result=$this->_statement->nextRowset())!==false)
$this->_index=-1;
return $result;
}
public function close()
{
$this->_statement->closeCursor();
$this->_closed=true;
}
public function getIsClosed()
{
return $this->_closed;
}
public function getRowCount()
{
return $this->_statement->rowCount();
}
public function count()
{
return $this->getRowCount();
}
public function getColumnCount()
{
return $this->_statement->columnCount();
}
public function rewind()
{
if($this->_index<0)
{
$this->_row=$this->_statement->fetch();
$this->_index=0;
}
else
throw new CDbException(Yii::t('yii','CDbDataReader cannot rewind. It is a forward-only reader.'));
}
public function key()
{
return $this->_index;
}
public function current()
{
return $this->_row;
}
public function next()
{
$this->_row=$this->_statement->fetch();
$this->_index++;
}
public function valid()
{
return $this->_row!==false;
}
}
