<?php
class CCaptchaAction extends CAction
{
const REFRESH_GET_VAR='refresh';
const SESSION_VAR_PREFIX='Yii.CCaptchaAction.';
public $testLimit = 3;
public $width = 120;
public $height = 50;
public $padding = 2;
public $backColor = 0xFFFFFF;
public $foreColor = 0x2040A0;
public $transparent = false;
public $minLength = 6;
public $maxLength = 7;
public $offset =-2;
public $fontFile;
public $fixedVerifyCode;
public function run()
{
if(isset($_GET[self::REFRESH_GET_VAR]))//AJAX request for regenerating code
{
$code=$this->getVerifyCode(true);
echo CJSON::encode(array(
'hash1'=>$this->generateValidationHash($code),
'hash2'=>$this->generateValidationHash(strtolower($code)),
'url'=>$this->getController()->createUrl($this->getId(),array('v'=>uniqid())),
));
}
else
$this->renderImage($this->getVerifyCode());
Yii::app()->end();
}
public function generateValidationHash($code)
{
for($h=0,$i=strlen($code)-1;$i>=0;--$i)
$h+=ord($code[$i]);
return $h;
}
public function getVerifyCode($regenerate=false)
{
if($this->fixedVerifyCode !== null)
return $this->fixedVerifyCode;
$session = Yii::app()->session;
$session->open();
$name = $this->getSessionKey();
if($session[$name] === null || $regenerate)
{
$session[$name] = $this->generateVerifyCode();
$session[$name . 'count'] = 1;
}
return $session[$name];
}
public function validate($input,$caseSensitive)
{
$code = $this->getVerifyCode();
$valid = $caseSensitive ? ($input === $code) : !strcasecmp($input,$code);
$session = Yii::app()->session;
$session->open();
$name = $this->getSessionKey() . 'count';
$session[$name] = $session[$name]+1;
if($session[$name] > $this->testLimit && $this->testLimit > 0)
$this->getVerifyCode(true);
return $valid;
}
protected function generateVerifyCode()
{
if($this->minLength < 3)
$this->minLength = 3;
if($this->maxLength > 20)
$this->maxLength = 20;
if($this->minLength > $this->maxLength)
$this->maxLength = $this->minLength;
$length = mt_rand($this->minLength,$this->maxLength);
$letters = 'bcdfghjklmnpqrstvwxyz';
$vowels = 'aeiou';
$code = '';
for($i = 0; $i < $length;++$i)
{
if($i%2 && mt_rand(0,10) > 2 || !($i%2) && mt_rand(0,10) > 9)
$code.=$vowels[mt_rand(0,4)];
else
$code.=$letters[mt_rand(0,20)];
}
return $code;
}
protected function getSessionKey()
{
return self::SESSION_VAR_PREFIX . Yii::app()->getId() . '.' . $this->getController()->getUniqueId() . '.' . $this->getId();
}
protected function renderImage($code)
{
$image = imagecreatetruecolor($this->width,$this->height);
$backColor = imagecolorallocate($image,
(int)($this->backColor%0x1000000/0x10000),
(int)($this->backColor%0x10000/0x100),
$this->backColor%0x100);
imagefilledrectangle($image,0,0,$this->width,$this->height,$backColor);
imagecolordeallocate($image,$backColor);
if($this->transparent)
imagecolortransparent($image,$backColor);
$foreColor = imagecolorallocate($image,
(int)($this->foreColor%0x1000000/0x10000),
(int)($this->foreColor%0x10000/0x100),
$this->foreColor%0x100);
if($this->fontFile === null)
$this->fontFile = dirname(__FILE__) . '/Duality.ttf';
$length = strlen($code);
$box = imagettfbbox(30,0,$this->fontFile,$code);
$w = $box[4]-$box[0]+$this->offset*($length-1);
$h = $box[1]-$box[5];
$scale = min(($this->width-$this->padding*2)/$w,($this->height-$this->padding*2)/$h);
$x = 10;
$y = round($this->height*27/40);
for($i = 0; $i < $length;++$i)
{
$fontSize = (int)(rand(26,32)*$scale*0.8);
$angle = rand(-10,10);
$letter = $code[$i];
$box = imagettftext($image,$fontSize,$angle,$x,$y,$foreColor,$this->fontFile,$letter);
$x = $box[2]+$this->offset;
}
imagecolordeallocate($image,$foreColor);
header('Pragma: public');
header('Expires: 0');
header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
header('Content-Transfer-Encoding: binary');
header("Content-type: image/png");
imagepng($image);
imagedestroy($image);
}
}