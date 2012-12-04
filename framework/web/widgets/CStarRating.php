<?php
class CStarRating extends CInputWidget
{
public $starCount=5;
public $minRating=1;
public $maxRating=10;
public $ratingStepSize=1;
public $cssFile;
public $titles;
public $resetText;
public $resetValue;
public $allowEmpty;
public $starWidth;
public $readOnly;
public $focus;
public $blur;
public $callback;
public function run()
{
list($name,$id)=$this->resolveNameID();
if(isset($this->htmlOptions['id']))
$id=$this->htmlOptions['id'];
else
$this->htmlOptions['id']=$id;
if(isset($this->htmlOptions['name']))
$name=$this->htmlOptions['name'];
$this->registerClientScript($id);
echo CHtml::openTag('span',$this->htmlOptions)."\n";
$this->renderStars($id,$name);
echo "</span>";
}
public function registerClientScript($id)
{
$jsOptions=$this->getClientOptions();
$jsOptions=empty($jsOptions) ? '' : CJavaScript::encode($jsOptions);
$js="jQuery('#{$id} > input').rating({$jsOptions});";
$cs=Yii::app()->getClientScript();
$cs->registerCoreScript('rating');
$cs->registerScript('Yii.CStarRating#'.$id,$js);
if($this->cssFile!==false)
self::registerCssFile($this->cssFile);
}
public static function registerCssFile($url=null)
{
$cs=Yii::app()->getClientScript();
if($url===null)
$url=$cs->getCoreScriptUrl().'/rating/jquery.rating.css';
$cs->registerCssFile($url);
}
protected function renderStars($id,$name)
{
$inputCount=(int)(($this->maxRating-$this->minRating)/$this->ratingStepSize+1);
$starSplit=(int)($inputCount/$this->starCount);
if($this->hasModel())
{
$attr=$this->attribute;
CHtml::resolveName($this->model,$attr);
$selection=$this->model->$attr;
}
else
$selection=$this->value;
$options=$starSplit>1 ? array('class'=>"{split:{$starSplit}}") : array();
for($value=$this->minRating, $i=0;$i<$inputCount; ++$i, $value+=$this->ratingStepSize)
{
$options['id']=$id.'_'.$i;
$options['value']=$value;
if(isset($this->titles[$value]))
$options['title']=$this->titles[$value];
else
unset($options['title']);
echo CHtml::radioButton($name,!strcmp($value,$selection),$options) . "\n";
}
}
protected function getClientOptions()
{
$options=array();
if($this->resetText!==null)
$options['cancel']=$this->resetText;
if($this->resetValue!==null)
$options['cancelValue']=$this->resetValue;
if($this->allowEmpty===false)
$options['required']=true;
if($this->starWidth!==null)
$options['starWidth']=$this->starWidth;
if($this->readOnly===true)
$options['readOnly']=true;
foreach(array('focus', 'blur', 'callback') as $event)
{
if($this->$event!==null)
{
if($this->$event instanceof CJavaScriptExpression)
$options[$event]=$this->$event;
else
$options[$event]=new CJavaScriptExpression($this->$event);
}
}
return $options;
}
}