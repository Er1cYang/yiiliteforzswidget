<?php
class CHtml
{
const ID_PREFIX='yt';
public static $errorSummaryCss='errorSummary';
public static $errorMessageCss='errorMessage';
public static $errorCss='error';
public static $requiredCss='required';
public static $beforeRequiredLabel='';
public static $afterRequiredLabel=' <span class="required">*</span>';
public static $count=0;
public static $liveEvents = true;
public static function encode($text)
{
return htmlspecialchars($text,ENT_QUOTES,Yii::app()->charset);
}
public static function decode($text)
{
return htmlspecialchars_decode($text,ENT_QUOTES);
}
public static function encodeArray($data)
{
$d=array();
foreach($data as $key=>$value)
{
if(is_string($key))
$key=htmlspecialchars($key,ENT_QUOTES,Yii::app()->charset);
if(is_string($value))
$value=htmlspecialchars($value,ENT_QUOTES,Yii::app()->charset);
else if(is_array($value))
$value=self::encodeArray($value);
$d[$key]=$value;
}
return $d;
}
public static function tag($tag,$htmlOptions=array(),$content=false,$closeTag=true)
{
$html='<' . $tag . self::renderAttributes($htmlOptions);
if($content===false)
return $closeTag ? $html.'/>' : $html.'>';
else
return $closeTag ? $html.'>'.$content.'</'.$tag.'>' : $html.'>'.$content;
}
public static function openTag($tag,$htmlOptions=array())
{
return '<' . $tag . self::renderAttributes($htmlOptions) . '>';
}
public static function closeTag($tag)
{
return '</'.$tag.'>';
}
public static function cdata($text)
{
return '<![CDATA[' . $text . ']]>';
}
public static function metaTag($content,$name=null,$httpEquiv=null,$options=array())
{
if($name!==null)
$options['name']=$name;
if($httpEquiv!==null)
$options['http-equiv']=$httpEquiv;
$options['content']=$content;
return self::tag('meta',$options);
}
public static function linkTag($relation=null,$type=null,$href=null,$media=null,$options=array())
{
if($relation!==null)
$options['rel']=$relation;
if($type!==null)
$options['type']=$type;
if($href!==null)
$options['href']=$href;
if($media!==null)
$options['media']=$media;
return self::tag('link',$options);
}
public static function css($text,$media='')
{
if($media!=='')
$media=' media="'.$media.'"';
return "<style type=\"text/css\"{$media}>\n\n{$text}\n\n</style>";
}
public static function refresh($seconds, $url='')
{
$content="$seconds";
if($url!=='')
$content.=';'.self::normalizeUrl($url);
Yii::app()->clientScript->registerMetaTag($content,null,'refresh');
}
public static function cssFile($url,$media='')
{
if($media!=='')
$media=' media="'.$media.'"';
return '<link rel="stylesheet" type="text/css" href="'.self::encode($url).'"'.$media.'/>';
}
public static function script($text)
{
return "<script type=\"text/javascript\">\n\n{$text}\n\n</script>";
}
public static function scriptFile($url)
{
return '<script type="text/javascript" src="'.self::encode($url).'"></script>';
}
public static function form($action='',$method='post',$htmlOptions=array())
{
return self::beginForm($action,$method,$htmlOptions);
}
public static function beginForm($action='',$method='post',$htmlOptions=array())
{
$htmlOptions['action']=$url=self::normalizeUrl($action);
$htmlOptions['method']=$method;
$form=self::tag('form',$htmlOptions,false,false);
$hiddens=array();
if(!strcasecmp($method,'get') && ($pos=strpos($url,'?'))!==false)
{
foreach(explode('&',substr($url,$pos+1)) as $pair)
{
if(($pos=strpos($pair,'='))!==false)
$hiddens[]=self::hiddenField(urldecode(substr($pair,0,$pos)),urldecode(substr($pair,$pos+1)),array('id'=>false));
}
}
$request=Yii::app()->request;
if($request->enableCsrfValidation && !strcasecmp($method,'post'))
$hiddens[]=self::hiddenField($request->csrfTokenName,$request->getCsrfToken(),array('id'=>false));
if($hiddens!==array())
$form.="\n".self::tag('div',array('style'=>'display:none'),implode("\n",$hiddens));
return $form;
}
public static function endForm()
{
return '</form>';
}
public static function statefulForm($action='',$method='post',$htmlOptions=array())
{
return self::form($action,$method,$htmlOptions)."\n".
self::tag('div',array('style'=>'display:none'),self::pageStateField(''));
}
public static function pageStateField($value)
{
return '<input type="hidden" name="'.CController::STATE_INPUT_NAME.'" value="'.$value.'"/>';
}
public static function link($text,$url='#',$htmlOptions=array())
{
if($url!=='')
$htmlOptions['href']=self::normalizeUrl($url);
self::clientChange('click',$htmlOptions);
return self::tag('a',$htmlOptions,$text);
}
public static function mailto($text,$email='',$htmlOptions=array())
{
if($email==='')
$email=$text;
return self::link($text,'mailto:'.$email,$htmlOptions);
}
public static function image($src,$alt='',$htmlOptions=array())
{
$htmlOptions['src']=$src;
$htmlOptions['alt']=$alt;
return self::tag('img',$htmlOptions);
}
public static function button($label='button',$htmlOptions=array())
{
if(!isset($htmlOptions['name']))
{
if(!array_key_exists('name',$htmlOptions))
$htmlOptions['name']=self::ID_PREFIX.self::$count++;
}
if(!isset($htmlOptions['type']))
$htmlOptions['type']='button';
if(!isset($htmlOptions['value']))
$htmlOptions['value']=$label;
self::clientChange('click',$htmlOptions);
return self::tag('input',$htmlOptions);
}
public static function htmlButton($label='button',$htmlOptions=array())
{
if(!isset($htmlOptions['name']))
$htmlOptions['name']=self::ID_PREFIX.self::$count++;
if(!isset($htmlOptions['type']))
$htmlOptions['type']='button';
self::clientChange('click',$htmlOptions);
return self::tag('button',$htmlOptions,$label);
}
public static function submitButton($label='submit',$htmlOptions=array())
{
$htmlOptions['type']='submit';
return self::button($label,$htmlOptions);
}
public static function resetButton($label='reset',$htmlOptions=array())
{
$htmlOptions['type']='reset';
return self::button($label,$htmlOptions);
}
public static function imageButton($src,$htmlOptions=array())
{
$htmlOptions['src']=$src;
$htmlOptions['type']='image';
return self::button('submit',$htmlOptions);
}
public static function linkButton($label='submit',$htmlOptions=array())
{
if(!isset($htmlOptions['submit']))
$htmlOptions['submit']=isset($htmlOptions['href']) ? $htmlOptions['href'] : '';
return self::link($label,'#',$htmlOptions);
}
public static function label($label,$for,$htmlOptions=array())
{
if($for===false)
unset($htmlOptions['for']);
else
$htmlOptions['for']=$for;
if(isset($htmlOptions['required']))
{
if($htmlOptions['required'])
{
if(isset($htmlOptions['class']))
$htmlOptions['class'].=' '.self::$requiredCss;
else
$htmlOptions['class']=self::$requiredCss;
$label=self::$beforeRequiredLabel.$label.self::$afterRequiredLabel;
}
unset($htmlOptions['required']);
}
return self::tag('label',$htmlOptions,$label);
}
public static function textField($name,$value='',$htmlOptions=array())
{
self::clientChange('change',$htmlOptions);
return self::inputField('text',$name,$value,$htmlOptions);
}
public static function hiddenField($name,$value='',$htmlOptions=array())
{
return self::inputField('hidden',$name,$value,$htmlOptions);
}
public static function passwordField($name,$value='',$htmlOptions=array())
{
self::clientChange('change',$htmlOptions);
return self::inputField('password',$name,$value,$htmlOptions);
}
public static function fileField($name,$value='',$htmlOptions=array())
{
return self::inputField('file',$name,$value,$htmlOptions);
}
public static function textArea($name,$value='',$htmlOptions=array())
{
$htmlOptions['name']=$name;
if(!isset($htmlOptions['id']))
$htmlOptions['id']=self::getIdByName($name);
else if($htmlOptions['id']===false)
unset($htmlOptions['id']);
self::clientChange('change',$htmlOptions);
return self::tag('textarea',$htmlOptions,isset($htmlOptions['encode']) && !$htmlOptions['encode'] ? $value : self::encode($value));
}
public static function radioButton($name,$checked=false,$htmlOptions=array())
{
if($checked)
$htmlOptions['checked']='checked';
else
unset($htmlOptions['checked']);
$value=isset($htmlOptions['value']) ? $htmlOptions['value'] : 1;
self::clientChange('click',$htmlOptions);
if(array_key_exists('uncheckValue',$htmlOptions))
{
$uncheck=$htmlOptions['uncheckValue'];
unset($htmlOptions['uncheckValue']);
}
else
$uncheck=null;
if($uncheck!==null)
{
if(isset($htmlOptions['id']) && $htmlOptions['id']!==false)
$uncheckOptions=array('id'=>self::ID_PREFIX.$htmlOptions['id']);
else
$uncheckOptions=array('id'=>false);
$hidden=self::hiddenField($name,$uncheck,$uncheckOptions);
}
else
$hidden='';
return $hidden . self::inputField('radio',$name,$value,$htmlOptions);
}
public static function checkBox($name,$checked=false,$htmlOptions=array())
{
if($checked)
$htmlOptions['checked']='checked';
else
unset($htmlOptions['checked']);
$value=isset($htmlOptions['value']) ? $htmlOptions['value'] : 1;
self::clientChange('click',$htmlOptions);
if(array_key_exists('uncheckValue',$htmlOptions))
{
$uncheck=$htmlOptions['uncheckValue'];
unset($htmlOptions['uncheckValue']);
}
else
$uncheck=null;
if($uncheck!==null)
{
if(isset($htmlOptions['id']) && $htmlOptions['id']!==false)
$uncheckOptions=array('id'=>self::ID_PREFIX.$htmlOptions['id']);
else
$uncheckOptions=array('id'=>false);
$hidden=self::hiddenField($name,$uncheck,$uncheckOptions);
}
else
$hidden='';
return $hidden . self::inputField('checkbox',$name,$value,$htmlOptions);
}
public static function dropDownList($name,$select,$data,$htmlOptions=array())
{
$htmlOptions['name']=$name;
if(!isset($htmlOptions['id']))
$htmlOptions['id']=self::getIdByName($name);
else if($htmlOptions['id']===false)
unset($htmlOptions['id']);
self::clientChange('change',$htmlOptions);
$options="\n".self::listOptions($select,$data,$htmlOptions);
return self::tag('select',$htmlOptions,$options);
}
public static function listBox($name,$select,$data,$htmlOptions=array())
{
if(!isset($htmlOptions['size']))
$htmlOptions['size']=4;
if(isset($htmlOptions['multiple']))
{
if(substr($name,-2)!=='[]')
$name.='[]';
}
return self::dropDownList($name,$select,$data,$htmlOptions);
}
public static function checkBoxList($name,$select,$data,$htmlOptions=array())
{
$template=isset($htmlOptions['template'])?$htmlOptions['template']:'{input} {label}';
$separator=isset($htmlOptions['separator'])?$htmlOptions['separator']:"<br/>\n";
$container=isset($htmlOptions['container'])?$htmlOptions['container']:'span';
unset($htmlOptions['template'],$htmlOptions['separator'],$htmlOptions['container']);
if(substr($name,-2)!=='[]')
$name.='[]';
if(isset($htmlOptions['checkAll']))
{
$checkAllLabel=$htmlOptions['checkAll'];
$checkAllLast=isset($htmlOptions['checkAllLast']) && $htmlOptions['checkAllLast'];
}
unset($htmlOptions['checkAll'],$htmlOptions['checkAllLast']);
$labelOptions=isset($htmlOptions['labelOptions'])?$htmlOptions['labelOptions']:array();
unset($htmlOptions['labelOptions']);
$items=array();
$baseID=self::getIdByName($name);
$id=0;
$checkAll=true;
foreach($data as $value=>$label)
{
$checked=!is_array($select) && !strcmp($value,$select) || is_array($select) && in_array($value,$select);
$checkAll=$checkAll && $checked;
$htmlOptions['value']=$value;
$htmlOptions['id']=$baseID.'_'.$id++;
$option=self::checkBox($name,$checked,$htmlOptions);
$label=self::label($label,$htmlOptions['id'],$labelOptions);
$items[]=strtr($template,array('{input}'=>$option,'{label}'=>$label));
}
if(isset($checkAllLabel))
{
$htmlOptions['value']=1;
$htmlOptions['id']=$id=$baseID.'_all';
$option=self::checkBox($id,$checkAll,$htmlOptions);
$label=self::label($checkAllLabel,$id,$labelOptions);
$item=strtr($template,array('{input}'=>$option,'{label}'=>$label));
if($checkAllLast)
$items[]=$item;
else
array_unshift($items,$item);
$name=strtr($name,array('['=>'\\[',']'=>'\\]'));
$js=<<<EOD
$('#$id').click(function() {
$("input[name='$name']").prop('checked', this.checked);
});
$("input[name='$name']").click(function() {
$('#$id').prop('checked', !$("input[name='$name']:not(:checked)").length);
});
$('#$id').prop('checked', !$("input[name='$name']:not(:checked)").length);
EOD;
$cs=Yii::app()->getClientScript();
$cs->registerCoreScript('jquery');
$cs->registerScript($id,$js);
}
if(empty($container))
return implode($separator,$items);
else
return self::tag($container,array('id'=>$baseID),implode($separator,$items));
}
public static function radioButtonList($name,$select,$data,$htmlOptions=array())
{
$template=isset($htmlOptions['template'])?$htmlOptions['template']:'{input} {label}';
$separator=isset($htmlOptions['separator'])?$htmlOptions['separator']:"<br/>\n";
$container=isset($htmlOptions['container'])?$htmlOptions['container']:'span';
unset($htmlOptions['template'],$htmlOptions['separator'],$htmlOptions['container']);
$labelOptions=isset($htmlOptions['labelOptions'])?$htmlOptions['labelOptions']:array();
unset($htmlOptions['labelOptions']);
$items=array();
$baseID=self::getIdByName($name);
$id=0;
foreach($data as $value=>$label)
{
$checked=!strcmp($value,$select);
$htmlOptions['value']=$value;
$htmlOptions['id']=$baseID.'_'.$id++;
$option=self::radioButton($name,$checked,$htmlOptions);
$label=self::label($label,$htmlOptions['id'],$labelOptions);
$items[]=strtr($template,array('{input}'=>$option,'{label}'=>$label));
}
if(empty($container))
return implode($separator,$items);
else
return self::tag($container,array('id'=>$baseID),implode($separator,$items));
}
public static function ajaxLink($text,$url,$ajaxOptions=array(),$htmlOptions=array())
{
if(!isset($htmlOptions['href']))
$htmlOptions['href']='#';
$ajaxOptions['url']=$url;
$htmlOptions['ajax']=$ajaxOptions;
self::clientChange('click',$htmlOptions);
return self::tag('a',$htmlOptions,$text);
}
public static function ajaxButton($label,$url,$ajaxOptions=array(),$htmlOptions=array())
{
$ajaxOptions['url']=$url;
$htmlOptions['ajax']=$ajaxOptions;
return self::button($label,$htmlOptions);
}
public static function ajaxSubmitButton($label,$url,$ajaxOptions=array(),$htmlOptions=array())
{
$ajaxOptions['type']='POST';
$htmlOptions['type']='submit';
return self::ajaxButton($label,$url,$ajaxOptions,$htmlOptions);
}
public static function ajax($options)
{
Yii::app()->getClientScript()->registerCoreScript('jquery');
if(!isset($options['url']))
$options['url']=new CJavaScriptExpression('location.href');
else
$options['url']=self::normalizeUrl($options['url']);
if(!isset($options['cache']))
$options['cache']=false;
if(!isset($options['data']) && isset($options['type']))
$options['data']=new CJavaScriptExpression('jQuery(this).parents("form").serialize()');
foreach(array('beforeSend','complete','error','success') as $name)
{
if(isset($options[$name]) && !($options[$name] instanceof CJavaScriptExpression))
$options[$name]=new CJavaScriptExpression($options[$name]);
}
if(isset($options['update']))
{
if(!isset($options['success']))
$options['success']=new CJavaScriptExpression('function(html){jQuery("'.$options['update'].'").html(html)}');
unset($options['update']);
}
if(isset($options['replace']))
{
if(!isset($options['success']))
$options['success']=new CJavaScriptExpression('function(html){jQuery("'.$options['replace'].'").replaceWith(html)}');
unset($options['replace']);
}
return 'jQuery.ajax('.CJavaScript::encode($options).');';
}
public static function asset($path,$hashByName=false)
{
return Yii::app()->getAssetManager()->publish($path,$hashByName);
}
public static function normalizeUrl($url)
{
if(is_array($url))
{
if(isset($url[0]))
{
if(($c=Yii::app()->getController())!==null)
$url=$c->createUrl($url[0],array_splice($url,1));
else
$url=Yii::app()->createUrl($url[0],array_splice($url,1));
}
else
$url='';
}
return $url==='' ? Yii::app()->getRequest()->getUrl() : $url;
}
protected static function inputField($type,$name,$value,$htmlOptions)
{
$htmlOptions['type']=$type;
$htmlOptions['value']=$value;
$htmlOptions['name']=$name;
if(!isset($htmlOptions['id']))
$htmlOptions['id']=self::getIdByName($name);
else if($htmlOptions['id']===false)
unset($htmlOptions['id']);
return self::tag('input',$htmlOptions);
}
public static function activeLabel($model,$attribute,$htmlOptions=array())
{
if(isset($htmlOptions['for']))
{
$for=$htmlOptions['for'];
unset($htmlOptions['for']);
}
else
$for=self::getIdByName(self::resolveName($model,$attribute));
if(isset($htmlOptions['label']))
{
if(($label=$htmlOptions['label'])===false)
return '';
unset($htmlOptions['label']);
}
else
$label=$model->getAttributeLabel($attribute);
if($model->hasErrors($attribute))
self::addErrorCss($htmlOptions);
return self::label($label,$for,$htmlOptions);
}
public static function activeLabelEx($model,$attribute,$htmlOptions=array())
{
$realAttribute=$attribute;
self::resolveName($model,$attribute);//strip off square brackets if any
$htmlOptions['required']=$model->isAttributeRequired($attribute);
return self::activeLabel($model,$realAttribute,$htmlOptions);
}
public static function activeTextField($model,$attribute,$htmlOptions=array())
{
self::resolveNameID($model,$attribute,$htmlOptions);
self::clientChange('change',$htmlOptions);
return self::activeInputField('text',$model,$attribute,$htmlOptions);
}
public static function activeUrlField($model,$attribute,$htmlOptions=array())
{
self::resolveNameID($model,$attribute,$htmlOptions);
self::clientChange('change',$htmlOptions);
return self::activeInputField('url',$model,$attribute,$htmlOptions);
}
public static function activeEmailField($model,$attribute,$htmlOptions=array())
{
self::resolveNameID($model,$attribute,$htmlOptions);
self::clientChange('change',$htmlOptions);
return self::activeInputField('email',$model,$attribute,$htmlOptions);
}
public static function activeNumberField($model,$attribute,$htmlOptions=array())
{
self::resolveNameID($model,$attribute,$htmlOptions);
self::clientChange('change',$htmlOptions);
return self::activeInputField('number',$model,$attribute,$htmlOptions);
}
public static function activeRangeField($model,$attribute,$htmlOptions=array())
{
self::resolveNameID($model,$attribute,$htmlOptions);
self::clientChange('change',$htmlOptions);
return self::activeInputField('range',$model,$attribute,$htmlOptions);
}
public static function activeDateField($model,$attribute,$htmlOptions=array())
{
self::resolveNameID($model,$attribute,$htmlOptions);
self::clientChange('change',$htmlOptions);
return self::activeInputField('date',$model,$attribute,$htmlOptions);
}
public static function activeHiddenField($model,$attribute,$htmlOptions=array())
{
self::resolveNameID($model,$attribute,$htmlOptions);
return self::activeInputField('hidden',$model,$attribute,$htmlOptions);
}
public static function activePasswordField($model,$attribute,$htmlOptions=array())
{
self::resolveNameID($model,$attribute,$htmlOptions);
self::clientChange('change',$htmlOptions);
return self::activeInputField('password',$model,$attribute,$htmlOptions);
}
public static function activeTextArea($model,$attribute,$htmlOptions=array())
{
self::resolveNameID($model,$attribute,$htmlOptions);
self::clientChange('change',$htmlOptions);
if($model->hasErrors($attribute))
self::addErrorCss($htmlOptions);
$text=self::resolveValue($model,$attribute);
return self::tag('textarea',$htmlOptions,isset($htmlOptions['encode']) && !$htmlOptions['encode'] ? $text : self::encode($text));
}
public static function activeFileField($model,$attribute,$htmlOptions=array())
{
self::resolveNameID($model,$attribute,$htmlOptions);
$hiddenOptions=isset($htmlOptions['id']) ? array('id'=>self::ID_PREFIX.$htmlOptions['id']) : array('id'=>false);
return self::hiddenField($htmlOptions['name'],'',$hiddenOptions)
. self::activeInputField('file',$model,$attribute,$htmlOptions);
}
public static function activeRadioButton($model,$attribute,$htmlOptions=array())
{
self::resolveNameID($model,$attribute,$htmlOptions);
if(!isset($htmlOptions['value']))
$htmlOptions['value']=1;
if(!isset($htmlOptions['checked']) && self::resolveValue($model,$attribute)==$htmlOptions['value'])
$htmlOptions['checked']='checked';
self::clientChange('click',$htmlOptions);
if(array_key_exists('uncheckValue',$htmlOptions))
{
$uncheck=$htmlOptions['uncheckValue'];
unset($htmlOptions['uncheckValue']);
}
else
$uncheck='0';
$hiddenOptions=isset($htmlOptions['id']) ? array('id'=>self::ID_PREFIX.$htmlOptions['id']) : array('id'=>false);
$hidden=$uncheck!==null ? self::hiddenField($htmlOptions['name'],$uncheck,$hiddenOptions) : '';
return $hidden . self::activeInputField('radio',$model,$attribute,$htmlOptions);
}
public static function activeCheckBox($model,$attribute,$htmlOptions=array())
{
self::resolveNameID($model,$attribute,$htmlOptions);
if(!isset($htmlOptions['value']))
$htmlOptions['value']=1;
if(!isset($htmlOptions['checked']) && self::resolveValue($model,$attribute)==$htmlOptions['value'])
$htmlOptions['checked']='checked';
self::clientChange('click',$htmlOptions);
if(array_key_exists('uncheckValue',$htmlOptions))
{
$uncheck=$htmlOptions['uncheckValue'];
unset($htmlOptions['uncheckValue']);
}
else
$uncheck='0';
$hiddenOptions=isset($htmlOptions['id']) ? array('id'=>self::ID_PREFIX.$htmlOptions['id']) : array('id'=>false);
$hidden=$uncheck!==null ? self::hiddenField($htmlOptions['name'],$uncheck,$hiddenOptions) : '';
return $hidden . self::activeInputField('checkbox',$model,$attribute,$htmlOptions);
}
public static function activeDropDownList($model,$attribute,$data,$htmlOptions=array())
{
self::resolveNameID($model,$attribute,$htmlOptions);
$selection=self::resolveValue($model,$attribute);
$options="\n".self::listOptions($selection,$data,$htmlOptions);
self::clientChange('change',$htmlOptions);
if($model->hasErrors($attribute))
self::addErrorCss($htmlOptions);
if(isset($htmlOptions['multiple']))
{
if(substr($htmlOptions['name'],-2)!=='[]')
$htmlOptions['name'].='[]';
}
return self::tag('select',$htmlOptions,$options);
}
public static function activeListBox($model,$attribute,$data,$htmlOptions=array())
{
if(!isset($htmlOptions['size']))
$htmlOptions['size']=4;
return self::activeDropDownList($model,$attribute,$data,$htmlOptions);
}
public static function activeCheckBoxList($model,$attribute,$data,$htmlOptions=array())
{
self::resolveNameID($model,$attribute,$htmlOptions);
$selection=self::resolveValue($model,$attribute);
if($model->hasErrors($attribute))
self::addErrorCss($htmlOptions);
$name=$htmlOptions['name'];
unset($htmlOptions['name']);
if(array_key_exists('uncheckValue',$htmlOptions))
{
$uncheck=$htmlOptions['uncheckValue'];
unset($htmlOptions['uncheckValue']);
}
else
$uncheck='';
$hiddenOptions=isset($htmlOptions['id']) ? array('id'=>self::ID_PREFIX.$htmlOptions['id']) : array('id'=>false);
$hidden=$uncheck!==null ? self::hiddenField($name,$uncheck,$hiddenOptions) : '';
return $hidden . self::checkBoxList($name,$selection,$data,$htmlOptions);
}
public static function activeRadioButtonList($model,$attribute,$data,$htmlOptions=array())
{
self::resolveNameID($model,$attribute,$htmlOptions);
$selection=self::resolveValue($model,$attribute);
if($model->hasErrors($attribute))
self::addErrorCss($htmlOptions);
$name=$htmlOptions['name'];
unset($htmlOptions['name']);
if(array_key_exists('uncheckValue',$htmlOptions))
{
$uncheck=$htmlOptions['uncheckValue'];
unset($htmlOptions['uncheckValue']);
}
else
$uncheck='';
$hiddenOptions=isset($htmlOptions['id']) ? array('id'=>self::ID_PREFIX.$htmlOptions['id']) : array('id'=>false);
$hidden=$uncheck!==null ? self::hiddenField($name,$uncheck,$hiddenOptions) : '';
return $hidden . self::radioButtonList($name,$selection,$data,$htmlOptions);
}
public static function errorSummary($model,$header=null,$footer=null,$htmlOptions=array())
{
$content='';
if(!is_array($model))
$model=array($model);
if(isset($htmlOptions['firstError']))
{
$firstError=$htmlOptions['firstError'];
unset($htmlOptions['firstError']);
}
else
$firstError=false;
foreach($model as $m)
{
foreach($m->getErrors() as $errors)
{
foreach($errors as $error)
{
if($error!='')
$content.="<li>$error</li>\n";
if($firstError)
break;
}
}
}
if($content!=='')
{
if($header===null)
$header='<p>'.Yii::t('yii','Please fix the following input errors:').'</p>';
if(!isset($htmlOptions['class']))
$htmlOptions['class']=self::$errorSummaryCss;
return self::tag('div',$htmlOptions,$header."\n<ul>\n$content</ul>".$footer);
}
else
return '';
}
public static function error($model,$attribute,$htmlOptions=array())
{
self::resolveName($model,$attribute);//turn [a][b]attr into attr
$error=$model->getError($attribute);
if($error!='')
{
if(!isset($htmlOptions['class']))
$htmlOptions['class']=self::$errorMessageCss;
return self::tag('div',$htmlOptions,$error);
}
else
return '';
}
public static function listData($models,$valueField,$textField,$groupField='')
{
$listData=array();
if($groupField==='')
{
foreach($models as $model)
{
$value=self::value($model,$valueField);
$text=self::value($model,$textField);
$listData[$value]=$text;
}
}
else
{
foreach($models as $model)
{
$group=self::value($model,$groupField);
$value=self::value($model,$valueField);
$text=self::value($model,$textField);
$listData[$group][$value]=$text;
}
}
return $listData;
}
public static function value($model,$attribute,$defaultValue=null)
{
foreach(explode('.',$attribute) as $name)
{
if(is_object($model))
$model=$model->$name;
else if(is_array($model) && isset($model[$name]))
$model=$model[$name];
else
return $defaultValue;
}
return $model;
}
public static function getIdByName($name)
{
return str_replace(array('[]', '][', '[', ']', ' '), array('', '_', '_', '', '_'), $name);
}
public static function activeId($model,$attribute)
{
return self::getIdByName(self::activeName($model,$attribute));
}
public static function activeName($model,$attribute)
{
$a=$attribute;//because the attribute name may be changed by resolveName
return self::resolveName($model,$a);
}
protected static function activeInputField($type,$model,$attribute,$htmlOptions)
{
$htmlOptions['type']=$type;
if($type==='text' || $type==='password')
{
if(!isset($htmlOptions['maxlength']))
{
foreach($model->getValidators($attribute) as $validator)
{
if($validator instanceof CStringValidator && $validator->max!==null)
{
$htmlOptions['maxlength']=$validator->max;
break;
}
}
}
else if($htmlOptions['maxlength']===false)
unset($htmlOptions['maxlength']);
}
if($type==='file')
unset($htmlOptions['value']);
else if(!isset($htmlOptions['value']))
$htmlOptions['value']=self::resolveValue($model,$attribute);
if($model->hasErrors($attribute))
self::addErrorCss($htmlOptions);
return self::tag('input',$htmlOptions);
}
public static function listOptions($selection,$listData,&$htmlOptions)
{
$raw=isset($htmlOptions['encode']) && !$htmlOptions['encode'];
$content='';
if(isset($htmlOptions['prompt']))
{
$content.='<option value="">'.strtr($htmlOptions['prompt'],array('<'=>'&lt;', '>'=>'&gt;'))."</option>\n";
unset($htmlOptions['prompt']);
}
if(isset($htmlOptions['empty']))
{
if(!is_array($htmlOptions['empty']))
$htmlOptions['empty']=array(''=>$htmlOptions['empty']);
foreach($htmlOptions['empty'] as $value=>$label)
$content.='<option value="'.self::encode($value).'">'.strtr($label,array('<'=>'&lt;', '>'=>'&gt;'))."</option>\n";
unset($htmlOptions['empty']);
}
if(isset($htmlOptions['options']))
{
$options=$htmlOptions['options'];
unset($htmlOptions['options']);
}
else
$options=array();
$key=isset($htmlOptions['key']) ? $htmlOptions['key'] : 'primaryKey';
if(is_array($selection))
{
foreach($selection as $i=>$item)
{
if(is_object($item))
$selection[$i]=$item->$key;
}
}
else if(is_object($selection))
$selection=$selection->$key;
foreach($listData as $key=>$value)
{
if(is_array($value))
{
$content.='<optgroup label="'.($raw?$key : self::encode($key))."\">\n";
$dummy=array('options'=>$options);
if(isset($htmlOptions['encode']))
$dummy['encode']=$htmlOptions['encode'];
$content.=self::listOptions($selection,$value,$dummy);
$content.='</optgroup>'."\n";
}
else
{
$attributes=array('value'=>(string)$key, 'encode'=>!$raw);
if(!is_array($selection) && !strcmp($key,$selection) || is_array($selection) && in_array($key,$selection))
$attributes['selected']='selected';
if(isset($options[$key]))
$attributes=array_merge($attributes,$options[$key]);
$content.=self::tag('option',$attributes,$raw?(string)$value : self::encode((string)$value))."\n";
}
}
unset($htmlOptions['key']);
return $content;
}
protected static function clientChange($event,&$htmlOptions)
{
if(!isset($htmlOptions['submit']) && !isset($htmlOptions['confirm']) && !isset($htmlOptions['ajax']))
return;
if(isset($htmlOptions['live']))
{
$live=$htmlOptions['live'];
unset($htmlOptions['live']);
}
else
$live = self::$liveEvents;
if(isset($htmlOptions['return']) && $htmlOptions['return'])
$return='return true';
else
$return='return false';
if(isset($htmlOptions['on'.$event]))
{
$handler=trim($htmlOptions['on'.$event],';').';';
unset($htmlOptions['on'.$event]);
}
else
$handler='';
if(isset($htmlOptions['id']))
$id=$htmlOptions['id'];
else
$id=$htmlOptions['id']=isset($htmlOptions['name'])?$htmlOptions['name']:self::ID_PREFIX.self::$count++;
$cs=Yii::app()->getClientScript();
$cs->registerCoreScript('jquery');
if(isset($htmlOptions['submit']))
{
$cs->registerCoreScript('yii');
$request=Yii::app()->getRequest();
if($request->enableCsrfValidation && isset($htmlOptions['csrf']) && $htmlOptions['csrf'])
$htmlOptions['params'][$request->csrfTokenName]=$request->getCsrfToken();
if(isset($htmlOptions['params']))
$params=CJavaScript::encode($htmlOptions['params']);
else
$params='{}';
if($htmlOptions['submit']!=='')
$url=CJavaScript::quote(self::normalizeUrl($htmlOptions['submit']));
else
$url='';
$handler.="jQuery.yii.submitForm(this,'$url',$params);{$return};";
}
if(isset($htmlOptions['ajax']))
$handler.=self::ajax($htmlOptions['ajax'])."{$return};";
if(isset($htmlOptions['confirm']))
{
$confirm='confirm(\''.CJavaScript::quote($htmlOptions['confirm']).'\')';
if($handler!=='')
$handler="if($confirm) {".$handler."} else return false;";
else
$handler="return $confirm;";
}
if($live)
$cs->registerScript('Yii.CHtml.#' . $id, "$('body').on('$event','#$id',function(){{$handler}});");
else
$cs->registerScript('Yii.CHtml.#' . $id, "$('#$id').on('$event', function(){{$handler}});");
unset($htmlOptions['params'],$htmlOptions['submit'],$htmlOptions['ajax'],$htmlOptions['confirm'],$htmlOptions['return'],$htmlOptions['csrf']);
}
public static function resolveNameID($model,&$attribute,&$htmlOptions)
{
if(!isset($htmlOptions['name']))
$htmlOptions['name']=self::resolveName($model,$attribute);
if(!isset($htmlOptions['id']))
$htmlOptions['id']=self::getIdByName($htmlOptions['name']);
else if($htmlOptions['id']===false)
unset($htmlOptions['id']);
}
public static function resolveName($model,&$attribute)
{
if(($pos=strpos($attribute,'['))!==false)
{
if($pos!==0)//e.g. name[a][b]
return get_class($model).'['.substr($attribute,0,$pos).']'.substr($attribute,$pos);
if(($pos=strrpos($attribute,']'))!==false && $pos!==strlen($attribute)-1)//e.g. [a][b]name
{
$sub=substr($attribute,0,$pos+1);
$attribute=substr($attribute,$pos+1);
return get_class($model).$sub.'['.$attribute.']';
}
if(preg_match('/\](\w+\[.*)$/',$attribute,$matches))
{
$name=get_class($model).'['.str_replace(']','][',trim(strtr($attribute,array(']['=>']','['=>']')),']')).']';
$attribute=$matches[1];
return $name;
}
}
return get_class($model).'['.$attribute.']';
}
public static function resolveValue($model,$attribute)
{
if(($pos=strpos($attribute,'['))!==false)
{
if($pos===0)//[a]name[b][c], should ignore [a]
{
if(preg_match('/\](\w+(\[.+)?)/',$attribute,$matches))
$attribute=$matches[1];//we get: name[b][c]
if(($pos=strpos($attribute,'['))===false)
return $model->$attribute;
}
$name=substr($attribute,0,$pos);
$value=$model->$name;
foreach(explode('][',rtrim(substr($attribute,$pos+1),']')) as $id)
{
if((is_array($value) || $value instanceof ArrayAccess) && isset($value[$id]))
$value=$value[$id];
else
return null;
}
return $value;
}
else
return $model->$attribute;
}
protected static function addErrorCss(&$htmlOptions)
{
if(isset($htmlOptions['class']))
$htmlOptions['class'].=' '.self::$errorCss;
else
$htmlOptions['class']=self::$errorCss;
}
public static function renderAttributes($htmlOptions)
{
static $specialAttributes=array(
'checked'=>1,
'declare'=>1,
'defer'=>1,
'disabled'=>1,
'ismap'=>1,
'multiple'=>1,
'nohref'=>1,
'noresize'=>1,
'readonly'=>1,
'selected'=>1,
);
if($htmlOptions===array())
return '';
$html='';
if(isset($htmlOptions['encode']))
{
$raw=!$htmlOptions['encode'];
unset($htmlOptions['encode']);
}
else
$raw=false;
foreach($htmlOptions as $name=>$value)
{
if(isset($specialAttributes[$name]))
{
if($value)
$html .= ' ' . $name . '="' . $name . '"';
}
else if($value!==null)
$html .= ' ' . $name . '="' . ($raw ? $value : self::encode($value)) . '"';
}
return $html;
}
}
