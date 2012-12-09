<?php
class CHelpCommand extends CConsoleCommand
{
public function run($args)
{
$runner=$this->getCommandRunner();
$commands=$runner->commands;
if(isset($args[0]))
$name=strtolower($args[0]);
if(!isset($args[0]) || !isset($commands[$name]))
{
if(!empty($commands))
{
echo "Yii command runner (based on Yii v".Yii::getVersion().")\n";
echo "Usage: ".$runner->getScriptName()." <command-name> [parameters...]\n";
echo "\nThe following commands are available:\n";
$commandNames=array_keys($commands);
sort($commandNames);
echo '-'.implode("\n-",$commandNames);
echo "\n\nTo see individual command help, use the following:\n";
echo "   ".$runner->getScriptName()." help <command-name>\n";
}
else
{
echo "No available commands.\n";
echo "Please define them under the following directory:\n";
echo "\t".Yii::app()->getCommandPath()."\n";
}
}
else
echo $runner->createCommand($name)->getHelp();
return 1;
}
public function getHelp()
{
return parent::getHelp().' [command-name]';
}
}