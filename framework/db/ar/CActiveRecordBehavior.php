<?php
class CActiveRecordBehavior extends CModelBehavior
{
public function events()
{
return array_merge(parent::events(), array(
'onBeforeSave'=>'beforeSave',
'onAfterSave'=>'afterSave',
'onBeforeDelete'=>'beforeDelete',
'onAfterDelete'=>'afterDelete',
'onBeforeFind'=>'beforeFind',
'onAfterFind'=>'afterFind',
));
}
public function beforeSave($event)
{
}
public function afterSave($event)
{
}
public function beforeDelete($event)
{
}
public function afterDelete($event)
{
}
public function beforeFind($event)
{
}
public function afterFind($event)
{
}
}
