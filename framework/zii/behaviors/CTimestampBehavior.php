<?php
class CTimestampBehavior extends CActiveRecordBehavior {
public $createAttribute = 'create_time';
public $updateAttribute = 'update_time';
public $setUpdateOnCreate = false;
public $timestampExpression;
protected static $map = array(
'datetime'=>'NOW()',
'timestamp'=>'NOW()',
'date'=>'NOW()',
);
public function beforeSave($event) {
if ($this->getOwner()->getIsNewRecord() && ($this->createAttribute !== null)) {
$this->getOwner()->{$this->createAttribute} = $this->getTimestampByAttribute($this->createAttribute);
}
if ((!$this->getOwner()->getIsNewRecord() || $this->setUpdateOnCreate) && ($this->updateAttribute !== null)) {
$this->getOwner()->{$this->updateAttribute} = $this->getTimestampByAttribute($this->updateAttribute);
}
}
protected function getTimestampByAttribute($attribute) {
if ($this->timestampExpression instanceof CDbExpression)
return $this->timestampExpression;
else if ($this->timestampExpression !== null)
return @eval('return '.$this->timestampExpression.';');
$columnType = $this->getOwner()->getTableSchema()->getColumn($attribute)->dbType;
return $this->getTimestampByColumnType($columnType);
}
protected function getTimestampByColumnType($columnType) {
return isset(self::$map[$columnType]) ? new CDbExpression(self::$map[$columnType]) : time();
}
}