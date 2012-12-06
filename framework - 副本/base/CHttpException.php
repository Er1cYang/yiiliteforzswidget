<?php
class CHttpException extends CException
{
public $statusCode;
public function __construct($status,$message=null,$code=0)
{
$this->statusCode=$status;
parent::__construct($message,$code);
}
}
