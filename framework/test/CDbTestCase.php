<?php
Yii::import('system.test.CTestCase');
abstract class CDbTestCase extends CTestCase
{
protected $fixtures=false;
public function __get($name)
{
if(is_array($this->fixtures) && ($rows=$this->getFixtureManager()->getRows($name))!==false)
return $rows;
else
throw new Exception("Unknown property '$name' for class '".get_class($this)."'.");
}
public function __call($name,$params)
{
if(is_array($this->fixtures) && isset($params[0]) && ($record=$this->getFixtureManager()->getRecord($name,$params[0]))!==false)
return $record;
else
throw new Exception("Unknown method '$name' for class '".get_class($this)."'.");
}
public function getFixtureManager()
{
return Yii::app()->getComponent('fixture');
}
public function getFixtureData($name)
{
return $this->getFixtureManager()->getRows($name);
}
public function getFixtureRecord($name,$alias)
{
return $this->getFixtureManager()->getRecord($name,$alias);
}
protected function setUp()
{
parent::setUp();
if(is_array($this->fixtures))
$this->getFixtureManager()->load($this->fixtures);
}
}