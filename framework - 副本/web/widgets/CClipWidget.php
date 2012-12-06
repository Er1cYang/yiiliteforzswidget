<?php
class CClipWidget extends CWidget
{
public $renderClip=false;
public function init()
{
ob_start();
ob_implicit_flush(false);
}
public function run()
{
$clip=ob_get_clean();
if($this->renderClip)
echo $clip;
$this->getController()->getClips()->add($this->getId(),$clip);
}
}