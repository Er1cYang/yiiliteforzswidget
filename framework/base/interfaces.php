<?php
interface IApplicationComponent
{
public function init();
public function getIsInitialized();
}
interface ICache
{
public function get($id);
public function mget($ids);
public function set($id,$value,$expire=0,$dependency=null);
public function add($id,$value,$expire=0,$dependency=null);
public function delete($id);
public function flush();
}
interface ICacheDependency
{
public function evaluateDependency();
public function getHasChanged();
}
interface IStatePersister
{
public function load();
public function save($state);
}
interface IFilter
{
public function filter($filterChain);
}
interface IAction
{
public function getId();
public function getController();
}
interface IWebServiceProvider
{
public function beforeWebMethod($service);
public function afterWebMethod($service);
}
interface IViewRenderer
{
public function renderFile($context,$file,$data,$return);
}
interface IUserIdentity
{
public function authenticate();
public function getIsAuthenticated();
public function getId();
public function getName();
public function getPersistentStates();
}
interface IWebUser
{
public function getId();
public function getName();
public function getIsGuest();
public function checkAccess($operation,$params=array());
public function loginRequired();
}
interface IAuthManager
{
public function checkAccess($itemName,$userId,$params=array());
public function createAuthItem($name,$type,$description='',$bizRule=null,$data=null);
public function removeAuthItem($name);
public function getAuthItems($type=null,$userId=null);
public function getAuthItem($name);
public function saveAuthItem($item,$oldName=null);
public function addItemChild($itemName,$childName);
public function removeItemChild($itemName,$childName);
public function hasItemChild($itemName,$childName);
public function getItemChildren($itemName);
public function assign($itemName,$userId,$bizRule=null,$data=null);
public function revoke($itemName,$userId);
public function isAssigned($itemName,$userId);
public function getAuthAssignment($itemName,$userId);
public function getAuthAssignments($userId);
public function saveAuthAssignment($assignment);
public function clearAll();
public function clearAuthAssignments();
public function save();
public function executeBizRule($bizRule,$params,$data);
}
interface IBehavior
{
public function attach($component);
public function detach($component);
public function getEnabled();
public function setEnabled($value);
}
interface IWidgetFactory
{
public function createWidget($owner,$className,$properties=array());
}
interface IDataProvider
{
public function getId();
public function getItemCount($refresh=false);
public function getTotalItemCount($refresh=false);
public function getData($refresh=false);
public function getKeys($refresh=false);
public function getSort();
public function getPagination();
}
interface ILogFilter
{
public function filter(&$logs);
}
