<?php
class CAccessControlFilter extends CFilter
{
public $message;
private $_rules=array();
public function getRules()
{
return $this->_rules;
}
public function setRules($rules)
{
foreach($rules as $rule)
{
if(is_array($rule) && isset($rule[0]))
{
$r=new CAccessRule;
$r->allow=$rule[0]==='allow';
foreach(array_slice($rule,1) as $name=>$value)
{
if($name==='expression' || $name==='roles' || $name==='message' || $name==='deniedCallback')
$r->$name=$value;
else
$r->$name=array_map('strtolower',$value);
}
$this->_rules[]=$r;
}
}
}
protected function preFilter($filterChain)
{
$app=Yii::app();
$request=$app->getRequest();
$user=$app->getUser();
$verb=$request->getRequestType();
$ip=$request->getUserHostAddress();
foreach($this->getRules() as $rule)
{
if(($allow=$rule->isUserAllowed($user,$filterChain->controller,$filterChain->action,$ip,$verb))>0) // allowed
break;
else if($allow<0) // denied
{
if(isset($rule->deniedCallback))
call_user_func($rule->deniedCallback, $rule);
else
$this->accessDenied($user,$this->resolveErrorMessage($rule));
return false;
}
}
return true;
}
protected function resolveErrorMessage($rule)
{
if($rule->message!==null)
return $rule->message;
else if($this->message!==null)
return $this->message;
else
return Yii::t('yii','You are not authorized to perform this action.');
}
protected function accessDenied($user,$message)
{
if($user->getIsGuest())
$user->loginRequired();
else
throw new CHttpException(403,$message);
}
}
class CAccessRule extends CComponent
{
public $allow;
public $actions;
public $controllers;
public $users;
public $roles;
public $ips;
public $verbs;
public $expression;
public $message;
public $deniedCallback;
public function isUserAllowed($user,$controller,$action,$ip,$verb)
{
if($this->isActionMatched($action)
&& $this->isUserMatched($user)
&& $this->isRoleMatched($user)
&& $this->isIpMatched($ip)
&& $this->isVerbMatched($verb)
&& $this->isControllerMatched($controller)
&& $this->isExpressionMatched($user))
return $this->allow ? 1 : -1;
else
return 0;
}
protected function isActionMatched($action)
{
return empty($this->actions) || in_array(strtolower($action->getId()),$this->actions);
}
protected function isControllerMatched($controller)
{
return empty($this->controllers) || in_array(strtolower($controller->getId()),$this->controllers);
}
protected function isUserMatched($user)
{
if(empty($this->users))
return true;
foreach($this->users as $u)
{
if($u==='*')
return true;
else if($u==='?' && $user->getIsGuest())
return true;
else if($u==='@' && !$user->getIsGuest())
return true;
else if(!strcasecmp($u,$user->getName()))
return true;
}
return false;
}
protected function isRoleMatched($user)
{
if(empty($this->roles))
return true;
foreach($this->roles as $key=>$role)
{
if(is_numeric($key))
{
if($user->checkAccess($role))
return true;
}
else
{
if($user->checkAccess($key,$role))
return true;
}
}
return false;
}
protected function isIpMatched($ip)
{
if(empty($this->ips))
return true;
foreach($this->ips as $rule)
{
if($rule==='*' || $rule===$ip || (($pos=strpos($rule,'*'))!==false && !strncmp($ip,$rule,$pos)))
return true;
}
return false;
}
protected function isVerbMatched($verb)
{
return empty($this->verbs) || in_array(strtolower($verb),$this->verbs);
}
protected function isExpressionMatched($user)
{
if($this->expression===null)
return true;
else
return $this->evaluateExpression($this->expression, array('user'=>$user));
}
}
