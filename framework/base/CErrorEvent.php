<?php
class CErrorEvent extends CEvent
{
public $code;
public $message;
public $file;
public $line;
public function __construct($sender,$code,$message,$file,$line)
{
$this->code=$code;
$this->message=$message;
$this->file=$file;
$this->line=$line;
parent::__construct($sender);
}
}
