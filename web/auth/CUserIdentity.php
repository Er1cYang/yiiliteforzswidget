<?php
class CUserIdentity extends CBaseUserIdentity
{
public $username;
public $password;
public function __construct($username,$password)
{
$this->username=$username;
$this->password=$password;
}
public function authenticate()
{
throw new CException(Yii::t('yii','{class}::authenticate() must be implemented.',array('{class}'=>get_class($this))));
}
public function getId()
{
return $this->username;
}
public function getName()
{
return $this->username;
}
}
