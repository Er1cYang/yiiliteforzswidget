<?php
class CCompareValidator extends CValidator
{
public $compareAttribute;
public $compareValue;
public $strict=false;
public $allowEmpty=false;
public $operator='=';
protected function validateAttribute($object,$attribute)
{
$value=$object->$attribute;
if($this->allowEmpty && $this->isEmpty($value))
return;
if($this->compareValue!==null)
$compareTo=$compareValue=$this->compareValue;
else
{
$compareAttribute=$this->compareAttribute===null ? $attribute.'_repeat' : $this->compareAttribute;
$compareValue=$object->$compareAttribute;
$compareTo=$object->getAttributeLabel($compareAttribute);
}
switch($this->operator)
{
case '=':
case '==':
if(($this->strict && $value!==$compareValue) || (!$this->strict && $value!=$compareValue))
{
$message=$this->message!==null?$this->message:Yii::t('yii','{attribute} must be repeated exactly.');
$this->addError($object,$attribute,$message,array('{compareAttribute}'=>$compareTo));
}
break;
case '!=':
if(($this->strict && $value===$compareValue) || (!$this->strict && $value==$compareValue))
{
$message=$this->message!==null?$this->message:Yii::t('yii','{attribute} must not be equal to "{compareValue}".');
$this->addError($object,$attribute,$message,array('{compareAttribute}'=>$compareTo,'{compareValue}'=>$compareValue));
}
break;
case '>':
if($value<=$compareValue)
{
$message=$this->message!==null?$this->message:Yii::t('yii','{attribute} must be greater than "{compareValue}".');
$this->addError($object,$attribute,$message,array('{compareAttribute}'=>$compareTo,'{compareValue}'=>$compareValue));
}
break;
case '>=':
if($value<$compareValue)
{
$message=$this->message!==null?$this->message:Yii::t('yii','{attribute} must be greater than or equal to "{compareValue}".');
$this->addError($object,$attribute,$message,array('{compareAttribute}'=>$compareTo,'{compareValue}'=>$compareValue));
}
break;
case '<':
if($value>=$compareValue)
{
$message=$this->message!==null?$this->message:Yii::t('yii','{attribute} must be less than "{compareValue}".');
$this->addError($object,$attribute,$message,array('{compareAttribute}'=>$compareTo,'{compareValue}'=>$compareValue));
}
break;
case '<=':
if($value>$compareValue)
{
$message=$this->message!==null?$this->message:Yii::t('yii','{attribute} must be less than or equal to "{compareValue}".');
$this->addError($object,$attribute,$message,array('{compareAttribute}'=>$compareTo,'{compareValue}'=>$compareValue));
}
break;
default:
throw new CException(Yii::t('yii','Invalid operator "{operator}".',array('{operator}'=>$this->operator)));
}
}
public function clientValidateAttribute($object,$attribute)
{
if($this->compareValue !== null)
{
$compareTo=$this->compareValue;
$compareValue=CJSON::encode($this->compareValue);
}
else
{
$compareAttribute=$this->compareAttribute === null ? $attribute . '_repeat' : $this->compareAttribute;
$compareValue="\$('#" . (CHtml::activeId($object, $compareAttribute)) . "').val()";
$compareTo=$object->getAttributeLabel($compareAttribute);
}
$message=$this->message;
switch($this->operator)
{
case '=':
case '==':
if($message===null)
$message=Yii::t('yii','{attribute} must be repeated exactly.');
$condition='value!='.$compareValue;
break;
case '!=':
if($message===null)
$message=Yii::t('yii','{attribute} must not be equal to "{compareValue}".');
$condition='value=='.$compareValue;
break;
case '>':
if($message===null)
$message=Yii::t('yii','{attribute} must be greater than "{compareValue}".');
$condition='parseFloat(value)<=parseFloat('.$compareValue.')';
break;
case '>=':
if($message===null)
$message=Yii::t('yii','{attribute} must be greater than or equal to "{compareValue}".');
$condition='parseFloat(value)<parseFloat('.$compareValue.')';
break;
case '<':
if($message===null)
$message=Yii::t('yii','{attribute} must be less than "{compareValue}".');
$condition='parseFloat(value)>=parseFloat('.$compareValue.')';
break;
case '<=':
if($message===null)
$message=Yii::t('yii','{attribute} must be less than or equal to "{compareValue}".');
$condition='parseFloat(value)>parseFloat('.$compareValue.')';
break;
default:
throw new CException(Yii::t('yii','Invalid operator "{operator}".',array('{operator}'=>$this->operator)));
}
$message=strtr($message,array(
'{attribute}'=>$object->getAttributeLabel($attribute),
'{compareValue}'=>$compareTo,
));
return "
if(".($this->allowEmpty ? "$.trim(value)!='' && " : '').$condition.") {
messages.push(".CJSON::encode($message).");
}
";
}
}
