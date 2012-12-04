<?php
Yii::import('zii.widgets.grid.CGridColumn');
class CButtonColumn extends CGridColumn
{
public $htmlOptions=array('class'=>'button-column');
public $headerHtmlOptions=array('class'=>'button-column');
public $footerHtmlOptions=array('class'=>'button-column');
public $template='{view} {update} {delete}';
public $viewButtonLabel;
public $viewButtonImageUrl;
public $viewButtonUrl='Yii::app()->controller->createUrl("view",array("id"=>$data->primaryKey))';
public $viewButtonOptions=array('class'=>'view');
public $updateButtonLabel;
public $updateButtonImageUrl;
public $updateButtonUrl='Yii::app()->controller->createUrl("update",array("id"=>$data->primaryKey))';
public $updateButtonOptions=array('class'=>'update');
public $deleteButtonLabel;
public $deleteButtonImageUrl;
public $deleteButtonUrl='Yii::app()->controller->createUrl("delete",array("id"=>$data->primaryKey))';
public $deleteButtonOptions=array('class'=>'delete');
public $deleteConfirmation;
public $afterDelete;
public $buttons=array();
public function init()
{
$this->initDefaultButtons();
foreach($this->buttons as $id=>$button)
{
if(strpos($this->template,'{'.$id.'}')===false)
unset($this->buttons[$id]);
else if(isset($button['click']))
{
if(!isset($button['options']['class']))
$this->buttons[$id]['options']['class']=$id;
if(!($button['click'] instanceof CJavaScriptExpression))
$this->buttons[$id]['click']=new CJavaScriptExpression($button['click']);
}
}
$this->registerClientScript();
}
protected function initDefaultButtons()
{
if($this->viewButtonLabel===null)
$this->viewButtonLabel=Yii::t('zii','View');
if($this->updateButtonLabel===null)
$this->updateButtonLabel=Yii::t('zii','Update');
if($this->deleteButtonLabel===null)
$this->deleteButtonLabel=Yii::t('zii','Delete');
if($this->viewButtonImageUrl===null)
$this->viewButtonImageUrl=$this->grid->baseScriptUrl.'/view.png';
if($this->updateButtonImageUrl===null)
$this->updateButtonImageUrl=$this->grid->baseScriptUrl.'/update.png';
if($this->deleteButtonImageUrl===null)
$this->deleteButtonImageUrl=$this->grid->baseScriptUrl.'/delete.png';
if($this->deleteConfirmation===null)
$this->deleteConfirmation=Yii::t('zii','Are you sure you want to delete this item?');
foreach(array('view','update','delete') as $id)
{
$button=array(
'label'=>$this->{$id.'ButtonLabel'},
'url'=>$this->{$id.'ButtonUrl'},
'imageUrl'=>$this->{$id.'ButtonImageUrl'},
'options'=>$this->{$id.'ButtonOptions'},
);
if(isset($this->buttons[$id]))
$this->buttons[$id]=array_merge($button,$this->buttons[$id]);
else
$this->buttons[$id]=$button;
}
if(!isset($this->buttons['delete']['click']))
{
if(is_string($this->deleteConfirmation))
$confirmation="if(!confirm(".CJavaScript::encode($this->deleteConfirmation).")) return false;";
else
$confirmation='';
if(Yii::app()->request->enableCsrfValidation)
{
$csrfTokenName = Yii::app()->request->csrfTokenName;
$csrfToken = Yii::app()->request->csrfToken;
$csrf = "\n\t\tdata:{ '$csrfTokenName':'$csrfToken' },";
}
else
$csrf = '';
if($this->afterDelete===null)
$this->afterDelete='function(){}';
$this->buttons['delete']['click']=<<<EOD
function() {
$confirmation
var th=this;
var afterDelete=$this->afterDelete;
$.fn.yiiGridView.update('{$this->grid->id}', {
type:'POST',
url:$(this).attr('href'),$csrf
success:function(data) {
$.fn.yiiGridView.update('{$this->grid->id}');
afterDelete(th,true,data);
},
error:function(XHR) {
return afterDelete(th,false,XHR);
}
});
return false;
}
EOD;
}
}
protected function registerClientScript()
{
$js=array();
foreach($this->buttons as $id=>$button)
{
if(isset($button['click']))
{
$function=CJavaScript::encode($button['click']);
$class=preg_replace('/\s+/','.',$button['options']['class']);
$js[]="$(document).on('click','#{$this->grid->id} a.{$class}',$function);";
}
}
if($js!==array())
Yii::app()->getClientScript()->registerScript(__CLASS__.'#'.$this->id, implode("\n",$js));
}
protected function renderDataCellContent($row,$data)
{
$tr=array();
ob_start();
foreach($this->buttons as $id=>$button)
{
$this->renderButton($id,$button,$row,$data);
$tr['{'.$id.'}']=ob_get_contents();
ob_clean();
}
ob_end_clean();
echo strtr($this->template,$tr);
}
protected function renderButton($id,$button,$row,$data)
{
if (isset($button['visible']) && !$this->evaluateExpression($button['visible'],array('row'=>$row,'data'=>$data)))
return;
$label=isset($button['label']) ? $button['label'] : $id;
$url=isset($button['url']) ? $this->evaluateExpression($button['url'],array('data'=>$data,'row'=>$row)) : '#';
$options=isset($button['options']) ? $button['options'] : array();
if(!isset($options['title']))
$options['title']=$label;
if(isset($button['imageUrl']) && is_string($button['imageUrl']))
echo CHtml::link(CHtml::image($button['imageUrl'],$label),$url,$options);
else
echo CHtml::link($label,$url,$options);
}
}
