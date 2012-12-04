<?php
class COutputEvent extends CEvent
{
public $output;
public function __construct($sender,$output)
{
parent::__construct($sender);
$this->output=$output;
}
}
