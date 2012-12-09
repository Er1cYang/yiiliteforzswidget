<?php
class CActiveForm extends CWidget
{
public $action='';
public $method='post';
public $stateful=false;
public $errorMessageCssClass='errorMessage';
public $htmlOptions=array();
public $clientOptions=array();
public $enableAjaxValidation=false;
public $enableClientValidation=false;
public $focus;
protected $attributes=array();
protected $summaryID;
public function init()
{
if(!isset($this->htmlOptions['id']))
$this->htmlOptions['id']=$this->id;
else
$this->id=$this->htmlOptions['id'];
if($this->stateful)
echo CHtml::statefulForm($this->action, $this->method, $this->htmlOptions);
else
echo CHtml::beginForm($this->action, $this->method, $this->htmlOptions);
}
public function run()
{
if(is_array($this->focus))
$this->focus="#".CHtml::activeId($this->focus[0],$this->focus[1]);
echo CHtml::endForm();
$cs=Yii::app()->clientScript;
if(!$this->enableAjaxValidation && !$this->enableClientValidation || empty($this->attributes))
{
if($this->focus!==null)
{
$cs->registerCoreScript('jquery');
$cs->registerScript('CActiveForm#focus',"
if(!window.location.hash)
$('".$this->focus."').focus();
");
}
return;
}
$options=$this->clientOptions;
if(isset($this->clientOptions['validationUrl']) && is_array($this->clientOptions['validationUrl']))
$options['validationUrl']=CHtml::normalizeUrl($this->clientOptions['validationUrl']);
$options['attributes']=array_values($this->attributes);
if($this->summaryID!==null)
$options['summaryID']=$this->summaryID;
if($this->focus!==null)
$options['focus']=$this->focus;
$options=CJavaScript::encode($options);
$cs->registerCoreScript('yiiactiveform');
$id=$this->id;
$cs->registerScript(__CLASS__.'#'.$id,"\$('#$id').yiiactiveform($options);");
}
public function error($model,$attribute,$htmlOptions=array(),$enableAjaxValidation=true,$enableClientValidation=true)
{
if(!$this->enableAjaxValidation)
$enableAjaxValidation=false;
if(!$this->enableClientValidation)
$enableClientValidation=false;
if(!isset($htmlOptions['class']))
$htmlOptions['class']=$this->errorMessageCssClass;
if(!$enableAjaxValidation && !$enableClientValidation)
return CHtml::error($model,$attribute,$htmlOptions);
$id=CHtml::activeId($model,$attribute);
$inputID=isset($htmlOptions['inputID']) ? $htmlOptions['inputID'] : $id;
unset($htmlOptions['inputID']);
if(!isset($htmlOptions['id']))
$htmlOptions['id']=$inputID.'_em_';
$option=array(
'id'=>$id,
'inputID'=>$inputID,
'errorID'=>$htmlOptions['id'],
'model'=>get_class($model),
'name'=>$attribute,
'enableAjaxValidation'=>$enableAjaxValidation,
);
$optionNames=array(
'validationDelay',
'validateOnChange',
'validateOnType',
'hideErrorMessage',
'inputContainer',
'errorCssClass',
'successCssClass',
'validatingCssClass',
'beforeValidateAttribute',
'afterValidateAttribute',
);
foreach($optionNames as $name)
{
if(isset($htmlOptions[$name]))
{
$option[$name]=$htmlOptions[$name];
unset($htmlOptions[$name]);
}
}
if($model instanceof CActiveRecord && !$model->isNewRecord)
$option['status']=1;
if($enableClientValidation)
{
$validators=isset($htmlOptions['clientValidation']) ? array($htmlOptions['clientValidation']) : array();
$attributeName = $attribute;
if(($pos=strrpos($attribute,']'))!==false && $pos!==strlen($attribute)-1)//e.g. [a]name
{
$attributeName=substr($attribute,$pos+1);
}
foreach($model->getValidators($attributeName) as $validator)
{
if($validator->enableClientValidation)
{
if(($js=$validator->clientValidateAttribute($model,$attributeName))!='')
$validators[]=$js;
}
}
if($validators!==array())
$option['clientValidation']=new CJavaScriptExpression("function(value, messages, attribute) {\n".implode("\n",$validators)."\n}");
}
$html=CHtml::error($model,$attribute,$htmlOptions);
if($html==='')
{
if(isset($htmlOptions['style']))
$htmlOptions['style']=rtrim($htmlOptions['style'],';').';display:none';
else
$htmlOptions['style']='display:none';
$html=CHtml::tag('div',$htmlOptions,'');
}
$this->attributes[$inputID]=$option;
return $html;
}
public function errorSummary($models,$header=null,$footer=null,$htmlOptions=array())
{
if(!$this->enableAjaxValidation && !$this->enableClientValidation)
return CHtml::errorSummary($models,$header,$footer,$htmlOptions);
if(!isset($htmlOptions['id']))
$htmlOptions['id']=$this->id.'_es_';
$html=CHtml::errorSummary($models,$header,$footer,$htmlOptions);
if($html==='')
{
if($header===null)
$header='<p>'.Yii::t('yii','Please fix the following input errors:').'</p>';
if(!isset($htmlOptions['class']))
$htmlOptions['class']=CHtml::$errorSummaryCss;
$htmlOptions['style']=isset($htmlOptions['style']) ? rtrim($htmlOptions['style'],';').';display:none' : 'display:none';
$html=CHtml::tag('div',$htmlOptions,$header."\n<ul><li>dummy</li></ul>".$footer);
}
$this->summaryID=$htmlOptions['id'];
return $html;
}
public function label($model,$attribute,$htmlOptions=array())
{
return CHtml::activeLabel($model,$attribute,$htmlOptions);
}
public function labelEx($model,$attribute,$htmlOptions=array())
{
return CHtml::activeLabelEx($model,$attribute,$htmlOptions);
}
public function urlField($model,$attribute,$htmlOptions=array())
{
return CHtml::activeUrlField($model,$attribute,$htmlOptions);
}
public function emailField($model,$attribute,$htmlOptions=array())
{
return CHtml::activeEmailField($model,$attribute,$htmlOptions);
}
public function numberField($model,$attribute,$htmlOptions=array())
{
return CHtml::activeNumberField($model,$attribute,$htmlOptions);
}
public function rangeField($model,$attribute,$htmlOptions=array())
{
return CHtml::activeRangeField($model,$attribute,$htmlOptions);
}
public function dateField($model,$attribute,$htmlOptions=array())
{
return CHtml::activeDateField($model,$attribute,$htmlOptions);
}
public function textField($model,$attribute,$htmlOptions=array())
{
return CHtml::activeTextField($model,$attribute,$htmlOptions);
}
public function hiddenField($model,$attribute,$htmlOptions=array())
{
return CHtml::activeHiddenField($model,$attribute,$htmlOptions);
}
public function passwordField($model,$attribute,$htmlOptions=array())
{
return CHtml::activePasswordField($model,$attribute,$htmlOptions);
}
public function textArea($model,$attribute,$htmlOptions=array())
{
return CHtml::activeTextArea($model,$attribute,$htmlOptions);
}
public function fileField($model,$attribute,$htmlOptions=array())
{
return CHtml::activeFileField($model,$attribute,$htmlOptions);
}
public function radioButton($model,$attribute,$htmlOptions=array())
{
return CHtml::activeRadioButton($model,$attribute,$htmlOptions);
}
public function checkBox($model,$attribute,$htmlOptions=array())
{
return CHtml::activeCheckBox($model,$attribute,$htmlOptions);
}
public function dropDownList($model,$attribute,$data,$htmlOptions=array())
{
return CHtml::activeDropDownList($model,$attribute,$data,$htmlOptions);
}
public function listBox($model,$attribute,$data,$htmlOptions=array())
{
return CHtml::activeListBox($model,$attribute,$data,$htmlOptions);
}
public function checkBoxList($model,$attribute,$data,$htmlOptions=array())
{
return CHtml::activeCheckBoxList($model,$attribute,$data,$htmlOptions);
}
public function radioButtonList($model,$attribute,$data,$htmlOptions=array())
{
return CHtml::activeRadioButtonList($model,$attribute,$data,$htmlOptions);
}
public static function validate($models, $attributes=null, $loadInput=true)
{
$result=array();
if(!is_array($models))
$models=array($models);
foreach($models as $model)
{
if($loadInput && isset($_POST[get_class($model)]))
$model->attributes=$_POST[get_class($model)];
$model->validate($attributes);
foreach($model->getErrors() as $attribute=>$errors)
$result[CHtml::activeId($model,$attribute)]=$errors;
}
return function_exists('json_encode') ? json_encode($result) : CJSON::encode($result);
}
public static function validateTabular($models, $attributes=null, $loadInput=true)
{
$result=array();
if(!is_array($models))
$models=array($models);
foreach($models as $i=>$model)
{
if($loadInput && isset($_POST[get_class($model)][$i]))
$model->attributes=$_POST[get_class($model)][$i];
$model->validate($attributes);
foreach($model->getErrors() as $attribute=>$errors)
$result[CHtml::activeId($model,'['.$i.']'.$attribute)]=$errors;
}
return function_exists('json_encode') ? json_encode($result) : CJSON::encode($result);
}
}