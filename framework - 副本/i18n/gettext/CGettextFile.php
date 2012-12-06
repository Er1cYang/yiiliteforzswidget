<?php
abstract class CGettextFile extends CComponent
{
abstract public function load($file,$context);
abstract public function save($file,$messages);
}
