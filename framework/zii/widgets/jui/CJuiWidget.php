<?php
abstract class CJuiWidget extends CWidget
{
public $scriptUrl;
public $themeUrl;
public $theme='base';
public $scriptFile='jquery-ui.min.js';
public $cssFile='jquery-ui.css';
public $options=array();
public $htmlOptions=array();
public function init()
{
$this->resolvePackagePath();
$this->registerCoreScripts();
parent::init();
}
protected function resolvePackagePath()
{
if($this->scriptUrl===null || $this->themeUrl===null)
{
$cs=Yii::app()->getClientScript();
if($this->scriptUrl===null)
$this->scriptUrl=$cs->getCoreScriptUrl().'/jui/js';
if($this->themeUrl===null)
$this->themeUrl=$cs->getCoreScriptUrl().'/jui/css';
}
}
protected function registerCoreScripts()
{
$cs=Yii::app()->getClientScript();
if(is_string($this->cssFile))
$cs->registerCssFile($this->themeUrl.'/'.$this->theme.'/'.$this->cssFile);
else if(is_array($this->cssFile))
{
foreach($this->cssFile as $cssFile)
$cs->registerCssFile($this->themeUrl.'/'.$this->theme.'/'.$cssFile);
}
$cs->registerCoreScript('jquery');
if(is_string($this->scriptFile))
$this->registerScriptFile($this->scriptFile);
else if(is_array($this->scriptFile))
{
foreach($this->scriptFile as $scriptFile)
$this->registerScriptFile($scriptFile);
}
}
protected function registerScriptFile($fileName,$position=CClientScript::POS_END)
{
Yii::app()->getClientScript()->registerScriptFile($this->scriptUrl.'/'.$fileName,$position);
}
}
