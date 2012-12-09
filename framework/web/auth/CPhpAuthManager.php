<?php
class CPhpAuthManager extends CAuthManager
{
public $authFile;
private $_items=array();//itemName=>item
private $_children=array();//itemName, childName=>child
private $_assignments=array();//userId, itemName=>assignment
public function init()
{
parent::init();
if($this->authFile===null)
$this->authFile=Yii::getPathOfAlias('application.data.auth').'.php';
$this->load();
}
public function checkAccess($itemName,$userId,$params=array())
{
if(!isset($this->_items[$itemName]))
return false;
$item=$this->_items[$itemName];
Yii::trace('Checking permission "'.$item->getName().'"','system.web.auth.CPhpAuthManager');
if(!isset($params['userId']))
$params['userId'] = $userId;
if($this->executeBizRule($item->getBizRule(),$params,$item->getData()))
{
if(in_array($itemName,$this->defaultRoles))
return true;
if(isset($this->_assignments[$userId][$itemName]))
{
$assignment=$this->_assignments[$userId][$itemName];
if($this->executeBizRule($assignment->getBizRule(),$params,$assignment->getData()))
return true;
}
foreach($this->_children as $parentName=>$children)
{
if(isset($children[$itemName]) && $this->checkAccess($parentName,$userId,$params))
return true;
}
}
return false;
}
public function addItemChild($itemName,$childName)
{
if(!isset($this->_items[$childName],$this->_items[$itemName]))
throw new CException(Yii::t('yii','Either "{parent}" or "{child}" does not exist.',array('{child}'=>$childName,'{name}'=>$itemName)));
$child=$this->_items[$childName];
$item=$this->_items[$itemName];
$this->checkItemChildType($item->getType(),$child->getType());
if($this->detectLoop($itemName,$childName))
throw new CException(Yii::t('yii','Cannot add "{child}" as a child of "{parent}". A loop has been detected.',
array('{child}'=>$childName,'{parent}'=>$itemName)));
if(isset($this->_children[$itemName][$childName]))
throw new CException(Yii::t('yii','The item "{parent}" already has a child "{child}".',
array('{child}'=>$childName,'{parent}'=>$itemName)));
$this->_children[$itemName][$childName]=$this->_items[$childName];
return true;
}
public function removeItemChild($itemName,$childName)
{
if(isset($this->_children[$itemName][$childName]))
{
unset($this->_children[$itemName][$childName]);
return true;
}
else
return false;
}
public function hasItemChild($itemName,$childName)
{
return isset($this->_children[$itemName][$childName]);
}
public function getItemChildren($names)
{
if(is_string($names))
return isset($this->_children[$names]) ? $this->_children[$names] : array();
$children=array();
foreach($names as $name)
{
if(isset($this->_children[$name]))
$children=array_merge($children,$this->_children[$name]);
}
return $children;
}
public function assign($itemName,$userId,$bizRule=null,$data=null)
{
if(!isset($this->_items[$itemName]))
throw new CException(Yii::t('yii','Unknown authorization item "{name}".',array('{name}'=>$itemName)));
else if(isset($this->_assignments[$userId][$itemName]))
throw new CException(Yii::t('yii','Authorization item "{item}" has already been assigned to user "{user}".',
array('{item}'=>$itemName,'{user}'=>$userId)));
else
return $this->_assignments[$userId][$itemName]=new CAuthAssignment($this,$itemName,$userId,$bizRule,$data);
}
public function revoke($itemName,$userId)
{
if(isset($this->_assignments[$userId][$itemName]))
{
unset($this->_assignments[$userId][$itemName]);
return true;
}
else
return false;
}
public function isAssigned($itemName,$userId)
{
return isset($this->_assignments[$userId][$itemName]);
}
public function getAuthAssignment($itemName,$userId)
{
return isset($this->_assignments[$userId][$itemName])?$this->_assignments[$userId][$itemName]:null;
}
public function getAuthAssignments($userId)
{
return isset($this->_assignments[$userId])?$this->_assignments[$userId]:array();
}
public function getAuthItems($type=null,$userId=null)
{
if($type===null && $userId===null)
return $this->_items;
$items=array();
if($userId===null)
{
foreach($this->_items as $name=>$item)
{
if($item->getType()==$type)
$items[$name]=$item;
}
}
else if(isset($this->_assignments[$userId]))
{
foreach($this->_assignments[$userId] as $assignment)
{
$name=$assignment->getItemName();
if(isset($this->_items[$name]) && ($type===null || $this->_items[$name]->getType()==$type))
$items[$name]=$this->_items[$name];
}
}
return $items;
}
public function createAuthItem($name,$type,$description='',$bizRule=null,$data=null)
{
if(isset($this->_items[$name]))
throw new CException(Yii::t('yii','Unable to add an item whose name is the same as an existing item.'));
return $this->_items[$name]=new CAuthItem($this,$name,$type,$description,$bizRule,$data);
}
public function removeAuthItem($name)
{
if(isset($this->_items[$name]))
{
foreach($this->_children as &$children)
unset($children[$name]);
foreach($this->_assignments as &$assignments)
unset($assignments[$name]);
unset($this->_items[$name]);
return true;
}
else
return false;
}
public function getAuthItem($name)
{
return isset($this->_items[$name])?$this->_items[$name]:null;
}
public function saveAuthItem($item,$oldName=null)
{
if($oldName!==null && ($newName=$item->getName())!==$oldName)//name changed
{
if(isset($this->_items[$newName]))
throw new CException(Yii::t('yii','Unable to change the item name. The name "{name}" is already used by another item.',array('{name}'=>$newName)));
if(isset($this->_items[$oldName]) && $this->_items[$oldName]===$item)
{
unset($this->_items[$oldName]);
$this->_items[$newName]=$item;
if(isset($this->_children[$oldName]))
{
$this->_children[$newName]=$this->_children[$oldName];
unset($this->_children[$oldName]);
}
foreach($this->_children as &$children)
{
if(isset($children[$oldName]))
{
$children[$newName]=$children[$oldName];
unset($children[$oldName]);
}
}
foreach($this->_assignments as &$assignments)
{
if(isset($assignments[$oldName]))
{
$assignments[$newName]=$assignments[$oldName];
unset($assignments[$oldName]);
}
}
}
}
}
public function saveAuthAssignment($assignment)
{
}
public function save()
{
$items=array();
foreach($this->_items as $name=>$item)
{
$items[$name]=array(
'type'=>$item->getType(),
'description'=>$item->getDescription(),
'bizRule'=>$item->getBizRule(),
'data'=>$item->getData(),
);
if(isset($this->_children[$name]))
{
foreach($this->_children[$name] as $child)
$items[$name]['children'][]=$child->getName();
}
}
foreach($this->_assignments as $userId=>$assignments)
{
foreach($assignments as $name=>$assignment)
{
if(isset($items[$name]))
{
$items[$name]['assignments'][$userId]=array(
'bizRule'=>$assignment->getBizRule(),
'data'=>$assignment->getData(),
);
}
}
}
$this->saveToFile($items,$this->authFile);
}
public function load()
{
$this->clearAll();
$items=$this->loadFromFile($this->authFile);
foreach($items as $name=>$item)
$this->_items[$name]=new CAuthItem($this,$name,$item['type'],$item['description'],$item['bizRule'],$item['data']);
foreach($items as $name=>$item)
{
if(isset($item['children']))
{
foreach($item['children'] as $childName)
{
if(isset($this->_items[$childName]))
$this->_children[$name][$childName]=$this->_items[$childName];
}
}
if(isset($item['assignments']))
{
foreach($item['assignments'] as $userId=>$assignment)
{
$this->_assignments[$userId][$name]=new CAuthAssignment($this,$name,$userId,$assignment['bizRule'],$assignment['data']);
}
}
}
}
public function clearAll()
{
$this->clearAuthAssignments();
$this->_children=array();
$this->_items=array();
}
public function clearAuthAssignments()
{
$this->_assignments=array();
}
protected function detectLoop($itemName,$childName)
{
if($childName===$itemName)
return true;
if(!isset($this->_children[$childName], $this->_items[$itemName]))
return false;
foreach($this->_children[$childName] as $child)
{
if($this->detectLoop($itemName,$child->getName()))
return true;
}
return false;
}
protected function loadFromFile($file)
{
if(is_file($file))
return require($file);
else
return array();
}
protected function saveToFile($data,$file)
{
file_put_contents($file,"<?php\nreturn ".var_export($data,true).";\n");
}
}
