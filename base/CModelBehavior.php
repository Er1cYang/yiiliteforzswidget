<?php
class CModelBehavior extends CBehavior
{
public function events()
{
return array(
'onAfterConstruct'=>'afterConstruct',
'onBeforeValidate'=>'beforeValidate',
'onAfterValidate'=>'afterValidate',
);
}
public function afterConstruct($event)
{
}
public function beforeValidate($event)
{
}
public function afterValidate($event)
{
}
}
