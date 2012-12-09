<?php
class CMultiFileUpload extends CInputWidget
{
	
	public $accept;
	
	public $max=-1;
	
	public $remove;
	
	public $denied;
	
	public $selected;
	
	public $duplicate;
	
	public $file;
	
	public $options=array();
	
	public function run()
	{
		list($name,$id)=$this->resolveNameID();
		if(substr($name,-2)!=='[]')
			$name.='[]';
		if(isset($this->htmlOptions['id']))
			$id=$this->htmlOptions['id'];
		else
			$this->htmlOptions['id']=$id;
		$this->registerClientScript();
		echo CHtml::fileField($name,'',$this->htmlOptions);
	}
	
	public function registerClientScript()
	{
		$id=$this->htmlOptions['id'];
		$options=$this->getClientOptions();
		$options=$options===array()? '' : CJavaScript::encode($options);
		$cs=Yii::app()->getClientScript();
		$cs->registerCoreScript('multifile');
		$cs->registerScript('Yii.CMultiFileUpload#'.$id,"jQuery(\"#{$id}\").MultiFile({$options});");
	}
	
	protected function getClientOptions()
	{
		$options=$this->options;
		foreach(array('onFileRemove','afterFileRemove','onFileAppend','afterFileAppend','onFileSelect','afterFileSelect') as $event)
		{
			if(isset($options[$event]) && !($options[$event] instanceof CJavaScriptExpression))
				$options[$event]=new CJavaScriptExpression($options[$event]);
		}
		if($this->accept!==null)
			$options['accept']=$this->accept;
		if($this->max>0)
			$options['max']=$this->max;
		$messages=array();
		foreach(array('remove','denied','selected','duplicate','file') as $messageName)
		{
			if($this->$messageName!==null)
				$messages[$messageName]=$this->$messageName;
		}
		if($messages!==array())
			$options['STRING']=$messages;
		return $options;
	}
}
