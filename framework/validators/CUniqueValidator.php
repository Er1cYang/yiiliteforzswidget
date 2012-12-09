<?php
class CUniqueValidator extends CValidator
{
public $caseSensitive=true;
public $allowEmpty=true;
public $className;
public $attributeName;
public $criteria=array();
public $message;
public $skipOnError=true;
protected function validateAttribute($object,$attribute)
{
$value=$object->$attribute;
if($this->allowEmpty && $this->isEmpty($value))
return;
$className=$this->className===null?get_class($object):Yii::import($this->className);
$attributeName=$this->attributeName===null?$attribute:$this->attributeName;
$finder=CActiveRecord::model($className);
$table=$finder->getTableSchema();
if(($column=$table->getColumn($attributeName))===null)
throw new CException(Yii::t('yii','Table "{table}" does not have a column named "{column}".',
array('{column}'=>$attributeName,'{table}'=>$table->name)));
$columnName=$column->rawName;
$criteria=new CDbCriteria();
if($this->criteria!==array())
$criteria->mergeWith($this->criteria);
$tableAlias = empty($criteria->alias) ? $finder->getTableAlias(true) : $criteria->alias;
$valueParamName = CDbCriteria::PARAM_PREFIX.CDbCriteria::$paramCount++;
$criteria->addCondition($this->caseSensitive ? "{$tableAlias}.{$columnName}={$valueParamName}" : "LOWER({$tableAlias}.{$columnName})=LOWER({$valueParamName})");
$criteria->params[$valueParamName] = $value;
if(!$object instanceof CActiveRecord || $object->isNewRecord || $object->tableName()!==$finder->tableName())
$exists=$finder->exists($criteria);
else
{
$criteria->limit=2;
$objects=$finder->findAll($criteria);
$n=count($objects);
if($n===1)
{
if($column->isPrimaryKey)//primary key is modified and not unique
$exists=$object->getOldPrimaryKey()!=$object->getPrimaryKey();
else
{
$exists=array_shift($objects)->getPrimaryKey()!=$object->getOldPrimaryKey();
}
}
else
$exists=$n>1;
}
if($exists)
{
$message=$this->message!==null?$this->message:Yii::t('yii','{attribute} "{value}" has already been taken.');
$this->addError($object,$attribute,$message,array('{value}'=>CHtml::encode($value)));
}
}
}
