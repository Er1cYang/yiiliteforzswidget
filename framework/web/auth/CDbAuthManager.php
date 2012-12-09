<?php
class CDbAuthManager extends CAuthManager
{
public $connectionID='db';
public $itemTable='AuthItem';
public $itemChildTable='AuthItemChild';
public $assignmentTable='AuthAssignment';
public $db;
private $_usingSqlite;
public function init()
{
parent::init();
$this->_usingSqlite=!strncmp($this->getDbConnection()->getDriverName(),'sqlite',6);
}
public function checkAccess($itemName,$userId,$params=array())
{
$assignments=$this->getAuthAssignments($userId);
return $this->checkAccessRecursive($itemName,$userId,$params,$assignments);
}
protected function checkAccessRecursive($itemName,$userId,$params,$assignments)
{
if(($item=$this->getAuthItem($itemName))===null)
return false;
Yii::trace('Checking permission "'.$item->getName().'"','system.web.auth.CDbAuthManager');
if(!isset($params['userId']))
$params['userId'] = $userId;
if($this->executeBizRule($item->getBizRule(),$params,$item->getData()))
{
if(in_array($itemName,$this->defaultRoles))
return true;
if(isset($assignments[$itemName]))
{
$assignment=$assignments[$itemName];
if($this->executeBizRule($assignment->getBizRule(),$params,$assignment->getData()))
return true;
}
$parents=$this->db->createCommand()->select('parent')->from($this->itemChildTable)->where('child=:name', array(':name'=>$itemName))->queryColumn();
foreach($parents as $parent)
{
if($this->checkAccessRecursive($parent,$userId,$params,$assignments))
return true;
}
}
return false;
}
public function addItemChild($itemName,$childName)
{
if($itemName===$childName)
throw new CException(Yii::t('yii','Cannot add "{name}" as a child of itself.',
array('{name}'=>$itemName)));
$rows=$this->db->createCommand()->select()->from($this->itemTable)->where('name=:name1 OR name=:name2', array(
':name1'=>$itemName,
':name2'=>$childName
))->queryAll();
if(count($rows)==2)
{
if($rows[0]['name']===$itemName)
{
$parentType=$rows[0]['type'];
$childType=$rows[1]['type'];
}
else
{
$childType=$rows[0]['type'];
$parentType=$rows[1]['type'];
}
$this->checkItemChildType($parentType,$childType);
if($this->detectLoop($itemName,$childName))
throw new CException(Yii::t('yii','Cannot add "{child}" as a child of "{name}". A loop has been detected.',
array('{child}'=>$childName,'{name}'=>$itemName)));
$this->db->createCommand()->insert($this->itemChildTable, array(
'parent'=>$itemName,
'child'=>$childName,
));
return true;
}
else
throw new CException(Yii::t('yii','Either "{parent}" or "{child}" does not exist.',array('{child}'=>$childName,'{parent}'=>$itemName)));
}
public function removeItemChild($itemName,$childName)
{
return $this->db->createCommand()->delete($this->itemChildTable, 'parent=:parent AND child=:child', array(
':parent'=>$itemName,
':child'=>$childName
)) > 0;
}
public function hasItemChild($itemName,$childName)
{
return $this->db->createCommand()->select('parent')->from($this->itemChildTable)->where('parent=:parent AND child=:child', array(
':parent'=>$itemName,
':child'=>$childName))->queryScalar() !== false;
}
public function getItemChildren($names)
{
if(is_string($names))
$condition='parent='.$this->db->quoteValue($names);
else if(is_array($names) && $names!==array())
{
foreach($names as &$name)
$name=$this->db->quoteValue($name);
$condition='parent IN ('.implode(', ',$names).')';
}
$rows=$this->db->createCommand()->select('name, type, description, bizrule, data')->from(array(
$this->itemTable,
$this->itemChildTable
))->where($condition.' AND name=child')->queryAll();
$children=array();
foreach($rows as $row)
{
if(($data=@unserialize($row['data']))===false)
$data=null;
$children[$row['name']]=new CAuthItem($this,$row['name'],$row['type'],$row['description'],$row['bizrule'],$data);
}
return $children;
}
public function assign($itemName,$userId,$bizRule=null,$data=null)
{
if($this->usingSqlite() && $this->getAuthItem($itemName)===null)
throw new CException(Yii::t('yii','The item "{name}" does not exist.',array('{name}'=>$itemName)));
$this->db->createCommand()->insert($this->assignmentTable, array(
'itemname'=>$itemName,
'userid'=>$userId,
'bizrule'=>$bizRule,
'data'=>serialize($data)
));
return new CAuthAssignment($this,$itemName,$userId,$bizRule,$data);
}
public function revoke($itemName,$userId)
{
return $this->db->createCommand()->delete($this->assignmentTable, 'itemname=:itemname AND userid=:userid', array(
':itemname'=>$itemName,
':userid'=>$userId
)) > 0;
}
public function isAssigned($itemName,$userId)
{
return $this->db->createCommand()->select('itemname')->from($this->assignmentTable)->where('itemname=:itemname AND userid=:userid', array(
':itemname'=>$itemName,
':userid'=>$userId))->queryScalar() !== false;
}
public function getAuthAssignment($itemName,$userId)
{
$row=$this->db->createCommand()->select()->from($this->assignmentTable)->where('itemname=:itemname AND userid=:userid', array(
':itemname'=>$itemName,
':userid'=>$userId))->queryRow();
if($row!==false)
{
if(($data=@unserialize($row['data']))===false)
$data=null;
return new CAuthAssignment($this,$row['itemname'],$row['userid'],$row['bizrule'],$data);
}
else
return null;
}
public function getAuthAssignments($userId)
{
$rows=$this->db->createCommand()->select()->from($this->assignmentTable)->where('userid=:userid', array(':userid'=>$userId))->queryAll();
$assignments=array();
foreach($rows as $row)
{
if(($data=@unserialize($row['data']))===false)
$data=null;
$assignments[$row['itemname']]=new CAuthAssignment($this,$row['itemname'],$row['userid'],$row['bizrule'],$data);
}
return $assignments;
}
public function saveAuthAssignment($assignment)
{
$this->db->createCommand()->update($this->assignmentTable, array(
'bizrule'=>$assignment->getBizRule(),
'data'=>serialize($assignment->getData()),
), 'itemname=:itemname AND userid=:userid', array(
'itemname'=>$assignment->getItemName(),
'userid'=>$assignment->getUserId()
));
}
public function getAuthItems($type=null,$userId=null)
{
if($type===null && $userId===null)
{
$command=$this->db->createCommand()->select()->from($this->itemTable);
}
else if($userId===null)
{
$command=$this->db->createCommand()->select()->from($this->itemTable)->where('type=:type', array(':type'=>$type));
}
else if($type===null)
{
$command=$this->db->createCommand()->select('name,type,description,t1.bizrule,t1.data')->from(array(
$this->itemTable.' t1',
$this->assignmentTable.' t2'
))->where('name=itemname AND userid=:userid', array(':userid'=>$userId));
}
else
{
$command=$this->db->createCommand()->select('name,type,description,t1.bizrule,t1.data')->from(array(
$this->itemTable.' t1',
$this->assignmentTable.' t2'
))->where('name=itemname AND type=:type AND userid=:userid', array(
':type'=>$type,
':userid'=>$userId
));
}
$items=array();
foreach($command->queryAll() as $row)
{
if(($data=@unserialize($row['data']))===false)
$data=null;
$items[$row['name']]=new CAuthItem($this,$row['name'],$row['type'],$row['description'],$row['bizrule'],$data);
}
return $items;
}
public function createAuthItem($name,$type,$description='',$bizRule=null,$data=null)
{
$this->db->createCommand()->insert($this->itemTable, array(
'name'=>$name,
'type'=>$type,
'description'=>$description,
'bizrule'=>$bizRule,
'data'=>serialize($data)
));
return new CAuthItem($this,$name,$type,$description,$bizRule,$data);
}
public function removeAuthItem($name)
{
if($this->usingSqlite())
{
$this->db->createCommand()->delete($this->itemChildTable, 'parent=:name1 OR child=:name2', array(
':name1'=>$name,
':name2'=>$name
));
$this->db->createCommand()->delete($this->assignmentTable, 'itemname=:name', array(
':name'=>$name,
));
}
return $this->db->createCommand()->delete($this->itemTable, 'name=:name', array(
':name'=>$name
)) > 0;
}
public function getAuthItem($name)
{
$row=$this->db->createCommand()->select()->from($this->itemTable)->where('name=:name', array(':name'=>$name))->queryRow();
if($row!==false)
{
if(($data=@unserialize($row['data']))===false)
$data=null;
return new CAuthItem($this,$row['name'],$row['type'],$row['description'],$row['bizrule'],$data);
}
else
return null;
}
public function saveAuthItem($item,$oldName=null)
{
if($this->usingSqlite() && $oldName!==null && $item->getName()!==$oldName)
{
$this->db->createCommand()->update($this->itemChildTable, array(
'parent'=>$item->getName(),
), 'parent=:whereName', array(
':whereName'=>$oldName,
));
$this->db->createCommand()->update($this->itemChildTable, array(
'child'=>$item->getName(),
), 'child=:whereName', array(
':whereName'=>$oldName,
));
$this->db->createCommand()->update($this->assignmentTable, array(
'itemname'=>$item->getName(),
), 'itemname=:whereName', array(
':whereName'=>$oldName,
));
}
$this->db->createCommand()->update($this->itemTable, array(
'name'=>$item->getName(),
'type'=>$item->getType(),
'description'=>$item->getDescription(),
'bizrule'=>$item->getBizRule(),
'data'=>serialize($item->getData()),
), 'name=:whereName', array(
':whereName'=>$oldName===null?$item->getName():$oldName,
));
}
public function save()
{
}
public function clearAll()
{
$this->clearAuthAssignments();
$this->db->createCommand()->delete($this->itemChildTable);
$this->db->createCommand()->delete($this->itemTable);
}
public function clearAuthAssignments()
{
$this->db->createCommand()->delete($this->assignmentTable);
}
protected function detectLoop($itemName,$childName)
{
if($childName===$itemName)
return true;
foreach($this->getItemChildren($childName) as $child)
{
if($this->detectLoop($itemName,$child->getName()))
return true;
}
return false;
}
protected function getDbConnection()
{
if($this->db!==null)
return $this->db;
else if(($this->db=Yii::app()->getComponent($this->connectionID)) instanceof CDbConnection)
return $this->db;
else
throw new CException(Yii::t('yii','CDbAuthManager.connectionID "{id}" is invalid. Please make sure it refers to the ID of a CDbConnection application component.',
array('{id}'=>$this->connectionID)));
}
protected function usingSqlite()
{
return $this->_usingSqlite;
}
}
