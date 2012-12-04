<?php
class CCaptcha extends CWidget
{
public $captchaAction='captcha';
public $showRefreshButton=true;
public $clickableImage=false;
public $buttonLabel;
public $buttonType='link';
public $imageOptions=array();
public $buttonOptions=array();
public function run()
{
if(self::checkRequirements())
{
$this->renderImage();
$this->registerClientScript();
}
else
throw new CException(Yii::t('yii','GD and FreeType PHP extensions are required.'));
}
protected function renderImage()
{
if(!isset($this->imageOptions['id']))
$this->imageOptions['id']=$this->getId();
$url=$this->getController()->createUrl($this->captchaAction,array('v'=>uniqid()));
$alt=isset($this->imageOptions['alt'])?$this->imageOptions['alt']:'';
echo CHtml::image($url,$alt,$this->imageOptions);
}
public function registerClientScript()
{
$cs=Yii::app()->clientScript;
$id=$this->imageOptions['id'];
$url=$this->getController()->createUrl($this->captchaAction,array(CCaptchaAction::REFRESH_GET_VAR=>true));
$js="";
if($this->showRefreshButton)
{
$cs->registerScript('Yii.CCaptcha#'.$id,'// dummy');
$label=$this->buttonLabel===null?Yii::t('yii','Get a new code'):$this->buttonLabel;
$options=$this->buttonOptions;
if(isset($options['id']))
$buttonID=$options['id'];
else
$buttonID=$options['id']=$id.'_button';
if($this->buttonType==='button')
$html=CHtml::button($label, $options);
else
$html=CHtml::link($label, $url, $options);
$js="jQuery('#$id').after(".CJSON::encode($html).");";
$selector="#$buttonID";
}
if($this->clickableImage)
$selector=isset($selector) ? "$selector, #$id" : "#$id";
if(!isset($selector))
return;
$js.="
$(document).on('click', '$selector', function(){
$.ajax({
url: ".CJSON::encode($url).",
dataType: 'json',
cache: false,
success: function(data) {
$('#$id').attr('src', data['url']);
$('body').data('{$this->captchaAction}.hash', [data['hash1'], data['hash2']]);
}
});
return false;
});
";
$cs->registerScript('Yii.CCaptcha#'.$id,$js);
}
public static function checkRequirements()
{
if (extension_loaded('gd'))
{
$gdinfo=gd_info();
if( $gdinfo['FreeType Support'])
return true;
}
return false;
}
}
