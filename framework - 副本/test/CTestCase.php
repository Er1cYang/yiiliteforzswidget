<?php
require_once('PHPUnit/Util/Filesystem.php'); // workaround for PHPUnit <= 3.6.11
require_once('PHPUnit/Autoload.php');
spl_autoload_unregister('phpunit_autoload');
Yii::registerAutoloader('phpunit_autoload');
abstract class CTestCase extends PHPUnit_Framework_TestCase
{
}
