<?php
class CExceptionEvent extends CEvent
{
public $exception;
public function __construct($sender,$exception)
{
$this->exception=$exception;
parent::__construct($sender);
}
}