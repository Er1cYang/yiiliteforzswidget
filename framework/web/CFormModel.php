<?php
class CFormModel extends CModel
{
private static $_names=array();
public function __construct($scenario='')
{
$this->setScenario($scenario);
$this->init();
$this->attachBehaviors($this->behaviors());
$this->afterConstruct();
}
public function init()
{
}
public function attributeNames()
{
$className=get_class($this);
if(!isset(self::$_names[$className]))
{
$class=new ReflectionClass(get_class($this));
$names=array();
foreach($class->getProperties() as $property)
{
$name=$property->getName();
if($property->isPublic() && !$property->isStatic())
$names[]=$name;
}
return self::$_names[$className]=$names;
}
else
return self::$_names[$className];
}
}