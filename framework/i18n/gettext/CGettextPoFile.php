<?php
class CGettextPoFile extends CGettextFile
{
public function load($file,$context)
{
$pattern='/(msgctxt\s+"(.*?(?<!\\\\))")?'
. '\s+msgid\s+"(.*?(?<!\\\\))"'
. '\s+msgstr\s+"(.*?(?<!\\\\))"/';
$content=file_get_contents($file);
$n=preg_match_all($pattern,$content,$matches);
$messages=array();
for($i=0;$i<$n;++$i)
{
if($matches[2][$i]===$context)
{
$id=$this->decode($matches[3][$i]);
$message=$this->decode($matches[4][$i]);
$messages[$id]=$message;
}
}
return $messages;
}
public function save($file,$messages)
{
$content='';
foreach($messages as $id=>$message)
{
if(($pos=strpos($id,chr(4)))!==false)
{
$content.='msgctxt "'.substr($id,0,$pos)."\"\n";
$id=substr($id,$pos+1);
}
$content.='msgid "'.$this->encode($id)."\"\n";
$content.='msgstr "'.$this->encode($message)."\"\n\n";
}
file_put_contents($file,$content);
}
protected function encode($string)
{
return str_replace(array('"', "\n", "\t", "\r"),array('\\"', "\\n", '\\t', '\\r'),$string);
}
protected function decode($string)
{
return str_replace(array('\\"', "\\n", '\\t', '\\r'),array('"', "\n", "\t", "\r"),$string);
}
}