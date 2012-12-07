<?php
class CPradoViewRenderer extends CViewRenderer
{
private $_input;
private $_output;
private $_sourceFile;
protected function generateViewFile($sourceFile,$viewFile)
{
static $regexRules=array(
'<%=?\s*(.*?)\s*%>',		// PHP statements or expressions
'<\/?(com|cache|clip):([\w\.]+)\s*((?:\s*\w+\s*=\s*\'.*?(?<!\\\\)\'|\s*\w+\s*=\s*".*?(?<!\\\\)"|\s*\w+\s*=\s*\{.*?\})*)\s*\/?>', // component tags
'<!---.*?--->',	// template comments
);
$this->_sourceFile=$sourceFile;
$this->_input=file_get_contents($sourceFile);
$n=preg_match_all('/'.implode('|',$regexRules).'/msS',$this->_input,$matches,PREG_SET_ORDER|PREG_OFFSET_CAPTURE);
$textStart=0;
$this->_output="<?php  ?>\n";
for($i=0;$i<$n;++$i)
{
$match=&$matches[$i];
$str=$match[0][0];
$matchStart=$match[0][1];
$matchEnd=$matchStart+strlen($str)-1;
if($matchStart>$textStart)
$this->_output.=substr($this->_input,$textStart,$matchStart-$textStart);
$textStart=$matchEnd+1;
if(strpos($str,'<com:')===0)	// opening component tag
{
$type=$match[3][0];
if($str[strlen($str)-2]!=='/')  // open tag
$this->_output.=$this->processBeginWidget($type,$match[4][0],$match[2][1]);
else
$this->_output.=$this->processWidget($type,$match[4][0],$match[2][1]);
}
else if(strpos($str,'</com:')===0)	// closing component tag
$this->_output.=$this->processEndWidget($match[3][0],$match[2][1]);
else if(strpos($str,'<cache:')===0)	// opening cache tag
{
$id=$match[3][0];
if($str[strlen($str)-2]!=='/')  // open tag
$this->_output.=$this->processBeginCache($id,$match[4][0],$match[2][1]);
else
$this->_output.=$this->processCache($id,$match[4][0],$match[2][1]);
}
else if(strpos($str,'</cache:')===0)	// closing cache tag
$this->_output.=$this->processEndCache($match[3][0],$match[2][1]);
else if(strpos($str,'<clip:')===0)	// opening clip tag
{
$id=$match[3][0];
if($str[strlen($str)-2]!=='/')  // open tag
$this->_output.=$this->processBeginClip($id,$match[4][0],$match[2][1]);
else
$this->_output.=$this->processClip($id,$match[4][0],$match[2][1]);
}
else if(strpos($str,'</clip:')===0)	// closing clip tag
$this->_output.=$this->processEndClip($match[3][0],$match[2][1]);
else if(strpos($str,'<%=')===0)	// expression
$this->_output.=$this->processExpression($match[1][0],$match[1][1]);
else if(strpos($str,'<%')===0)	// statement
$this->_output.=$this->processStatement($match[1][0],$match[1][1]);
}
if($textStart<strlen($this->_input))
$this->_output.=substr($this->_input,$textStart);
file_put_contents($viewFile,$this->_output);
}
private function processWidget($type,$attributes,$offset)
{
$attrs=$this->processAttributes($attributes);
if(empty($attrs))
return $this->generatePhpCode("\$this->widget('$type');",$offset);
else
return $this->generatePhpCode("\$this->widget('$type', array($attrs));",$offset);
}
private function processBeginWidget($type,$attributes,$offset)
{
$attrs=$this->processAttributes($attributes);
if(empty($attrs))
return $this->generatePhpCode("\$this->beginWidget('$type');",$offset);
else
return $this->generatePhpCode("\$this->beginWidget('$type', array($attrs));",$offset);
}
private function processEndWidget($type,$offset)
{
return $this->generatePhpCode("\$this->endWidget('$type');",$offset);
}
private function processCache($id,$attributes,$offset)
{
return $this->processBeginCache($id,$attributes,$offset) . $this->processEndCache($id,$offset);
}
private function processBeginCache($id,$attributes,$offset)
{
$attrs=$this->processAttributes($attributes);
if(empty($attrs))
return $this->generatePhpCode("if(\$this->beginCache('$id')):",$offset);
else
return $this->generatePhpCode("if(\$this->beginCache('$id', array($attrs))):",$offset);
}
private function processEndCache($id,$offset)
{
return $this->generatePhpCode("\$this->endCache('$id'); endif;",$offset);
}
private function processClip($id,$attributes,$offset)
{
return $this->processBeginClip($id,$attributes,$offset) . $this->processEndClip($id,$offset);
}
private function processBeginClip($id,$attributes,$offset)
{
$attrs=$this->processAttributes($attributes);
if(empty($attrs))
return $this->generatePhpCode("\$this->beginClip('$id');",$offset);
else
return $this->generatePhpCode("\$this->beginClip('$id', array($attrs));",$offset);
}
private function processEndClip($id,$offset)
{
return $this->generatePhpCode("\$this->endClip('$id');",$offset);
}
private function processExpression($expression,$offset)
{
return $this->generatePhpCode('echo '.$expression,$offset);
}
private function processStatement($statement,$offset)
{
return $this->generatePhpCode($statement,$offset);
}
private function generatePhpCode($code,$offset)
{
$line=$this->getLineNumber($offset);
$code=str_replace('__FILE__',var_export($this->_sourceFile,true),$code);
return "<?php  $code ?>";
}
private function processAttributes($str)
{
static $pattern='/(\w+)\s*=\s*(\'.*?(?<!\\\\)\'|".*?(?<!\\\\)"|\{.*?\})/msS';
$attributes=array();
$n=preg_match_all($pattern,$str,$matches,PREG_SET_ORDER);
for($i=0;$i<$n;++$i)
{
$match=&$matches[$i];
$name=$match[1];
$value=$match[2];
if($value[0]==='{')
$attributes[]="'$name'=>".str_replace('__FILE__',$this->_sourceFile,substr($value,1,-1));
else
$attributes[]="'$name'=>$value";
}
return implode(', ',$attributes);
}
private function getLineNumber($offset)
{
return count(explode("\n",substr($this->_input,0,$offset)));
}
}
