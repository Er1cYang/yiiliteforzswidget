<?php
abstract class CAuthManager extends CApplicationComponent implements IAuthManager
{
public $showErrors = false;
public $defaultRoles=array();
public function createRole($name,$description='',$bizRule=null,$data=null)
{
return $this->createAuthItem($name,CAuthItem::TYPE_ROLE,$description,$bizRule,$data);
}
public function createTask($name,$description='',$bizRule=null,$data=null)
{
return $this->createAuthItem($name,CAuthItem::TYPE_TASK,$description,$bizRule,$data);
}
public function createOperation($name,$description='',$bizRule=null,$data=null)
{
return $this->createAuthItem($name,CAuthItem::TYPE_OPERATION,$description,$bizRule,$data);
}
public function getRoles($userId=null)
{
return $this->getAuthItems(CAuthItem::TYPE_ROLE,$userId);
}
public function getTasks($userId=null)
{
return $this->getAuthItems(CAuthItem::TYPE_TASK,$userId);
}
public function getOperations($userId=null)
{
return $this->getAuthItems(CAuthItem::TYPE_OPERATION,$userId);
}
public function executeBizRule($bizRule,$params,$data)
{
return $bizRule==='' || $bizRule===null || ($this->showErrors ? eval($bizRule)!=0 : @eval($bizRule)!=0);
}
protected function checkItemChildType($parentType,$childType)
{
static $types=array('operation','task','role');
if($parentType < $childType)
throw new CException(Yii::t('yii','Cannot add an item of type "{child}" to an item of type "{parent}".',
array('{child}'=>$types[$childType], '{parent}'=>$types[$parentType])));
}
}
