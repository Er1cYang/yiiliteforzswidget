<?php
class CFilterValidator extends CValidator
{
public $filter;
protected function validateAttribute($object,$attribute)
{
if($this->filter===null || !is_callable($this->filter))
throw new CException(Yii::t('yii','The "filter" property must be specified with a valid callback.'));
$object->$attribute=call_user_func_array($this->filter,array($object->$attribute));
}
}
