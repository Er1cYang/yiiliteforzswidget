<?php
abstract class CModel extends CComponent implements IteratorAggregate, ArrayAccess
{
private $_errors=array();//attribute name=>array of errors
private $_validators;//validators
private $_scenario='';//scenario
abstract public function attributeNames();
public function rules()
{
return array();
}
public function behaviors()
{
return array();
}
public function attributeLabels()
{
return array();
}
public function validate($attributes=null, $clearErrors=true)
{
if($clearErrors)
$this->clearErrors();
if($this->beforeValidate())
{
foreach($this->getValidators() as $validator)
$validator->validate($this,$attributes);
$this->afterValidate();
return !$this->hasErrors();
}
else
return false;
}
protected function afterConstruct()
{
if($this->hasEventHandler('onAfterConstruct'))
$this->onAfterConstruct(new CEvent($this));
}
protected function beforeValidate()
{
$event=new CModelEvent($this);
$this->onBeforeValidate($event);
return $event->isValid;
}
protected function afterValidate()
{
$this->onAfterValidate(new CEvent($this));
}
public function onAfterConstruct($event)
{
$this->raiseEvent('onAfterConstruct',$event);
}
public function onBeforeValidate($event)
{
$this->raiseEvent('onBeforeValidate',$event);
}
public function onAfterValidate($event)
{
$this->raiseEvent('onAfterValidate',$event);
}
public function getValidatorList()
{
if($this->_validators===null)
$this->_validators=$this->createValidators();
return $this->_validators;
}
public function getValidators($attribute=null)
{
if($this->_validators===null)
$this->_validators=$this->createValidators();
$validators=array();
$scenario=$this->getScenario();
foreach($this->_validators as $validator)
{
if($validator->applyTo($scenario))
{
if($attribute===null || in_array($attribute,$validator->attributes,true))
$validators[]=$validator;
}
}
return $validators;
}
public function createValidators()
{
$validators=new CList;
foreach($this->rules() as $rule)
{
if(isset($rule[0],$rule[1]))//attributes, validator name
$validators->add(CValidator::createValidator($rule[1],$this,$rule[0],array_slice($rule,2)));
else
throw new CException(Yii::t('yii','{class} has an invalid validation rule. The rule must specify attributes to be validated and the validator name.',
array('{class}'=>get_class($this))));
}
return $validators;
}
public function isAttributeRequired($attribute)
{
foreach($this->getValidators($attribute) as $validator)
{
if($validator instanceof CRequiredValidator)
return true;
}
return false;
}
public function isAttributeSafe($attribute)
{
$attributes=$this->getSafeAttributeNames();
return in_array($attribute,$attributes);
}
public function getAttributeLabel($attribute)
{
$labels=$this->attributeLabels();
if(isset($labels[$attribute]))
return $labels[$attribute];
else
return $this->generateAttributeLabel($attribute);
}
public function hasErrors($attribute=null)
{
if($attribute===null)
return $this->_errors!==array();
else
return isset($this->_errors[$attribute]);
}
public function getErrors($attribute=null)
{
if($attribute===null)
return $this->_errors;
else
return isset($this->_errors[$attribute]) ? $this->_errors[$attribute] : array();
}
public function getError($attribute)
{
return isset($this->_errors[$attribute]) ? reset($this->_errors[$attribute]) : null;
}
public function addError($attribute,$error)
{
$this->_errors[$attribute][]=$error;
}
public function addErrors($errors)
{
foreach($errors as $attribute=>$error)
{
if(is_array($error))
{
foreach($error as $e)
$this->addError($attribute, $e);
}
else
$this->addError($attribute, $error);
}
}
public function clearErrors($attribute=null)
{
if($attribute===null)
$this->_errors=array();
else
unset($this->_errors[$attribute]);
}
public function generateAttributeLabel($name)
{
return ucwords(trim(strtolower(str_replace(array('-','_','.'),' ',preg_replace('/(?<![A-Z])[A-Z]/', ' \0', $name)))));
}
public function getAttributes($names=null)
{
$values=array();
foreach($this->attributeNames() as $name)
$values[$name]=$this->$name;
if(is_array($names))
{
$values2=array();
foreach($names as $name)
$values2[$name]=isset($values[$name]) ? $values[$name] : null;
return $values2;
}
else
return $values;
}
public function setAttributes($values,$safeOnly=true)
{
if(!is_array($values))
return;
$attributes=array_flip($safeOnly ? $this->getSafeAttributeNames() : $this->attributeNames());
foreach($values as $name=>$value)
{
if(isset($attributes[$name]))
$this->$name=$value;
else if($safeOnly)
$this->onUnsafeAttribute($name,$value);
}
}
public function unsetAttributes($names=null)
{
if($names===null)
$names=$this->attributeNames();
foreach($names as $name)
$this->$name=null;
}
public function onUnsafeAttribute($name,$value)
{
if(YII_DEBUG)
Yii::log(Yii::t('yii','Failed to set unsafe attribute "{attribute}" of "{class}".',array('{attribute}'=>$name, '{class}'=>get_class($this))),CLogger::LEVEL_WARNING);
}
public function getScenario()
{
return $this->_scenario;
}
public function setScenario($value)
{
$this->_scenario=$value;
}
public function getSafeAttributeNames()
{
$attributes=array();
$unsafe=array();
foreach($this->getValidators() as $validator)
{
if(!$validator->safe)
{
foreach($validator->attributes as $name)
$unsafe[]=$name;
}
else
{
foreach($validator->attributes as $name)
$attributes[$name]=true;
}
}
foreach($unsafe as $name)
unset($attributes[$name]);
return array_keys($attributes);
}
public function getIterator()
{
$attributes=$this->getAttributes();
return new CMapIterator($attributes);
}
public function offsetExists($offset)
{
return property_exists($this,$offset);
}
public function offsetGet($offset)
{
return $this->$offset;
}
public function offsetSet($offset,$item)
{
$this->$offset=$item;
}
public function offsetUnset($offset)
{
unset($this->$offset);
}
}
