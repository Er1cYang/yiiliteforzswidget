<?php
class CGettextMoFile extends CGettextFile
{
public $useBigEndian=false;
public function __construct($useBigEndian=false)
{
$this->useBigEndian=$useBigEndian;
}
public function load($file,$context)
{
if(!($fr=@fopen($file,'rb')))
throw new CException(Yii::t('yii','Unable to read file "{file}".',
array('{file}'=>$file)));
if(!@flock($fr,LOCK_SH))
throw new CException(Yii::t('yii','Unable to lock file "{file}" for reading.',
array('{file}'=>$file)));
$magic=current($array=unpack('c',$this->readByte($fr,4)));
if($magic==-34)
$this->useBigEndian=false;
else if($magic==-107)
$this->useBigEndian=true;
else
throw new CException(Yii::t('yii','Invalid MO file: {file} (magic: {magic}).',
array('{file}'=>$file,'{magic}'=>$magic)));
if(($revision=$this->readInteger($fr))!=0)
throw new CException(Yii::t('yii','Invalid MO file revision: {revision}.',
array('{revision}'=>$revision)));
$count=$this->readInteger($fr);
$sourceOffset=$this->readInteger($fr);
$targetOffset=$this->readInteger($fr);
$sourceLengths=array();
$sourceOffsets=array();
fseek($fr,$sourceOffset);
for($i=0;$i<$count;++$i)
{
$sourceLengths[]=$this->readInteger($fr);
$sourceOffsets[]=$this->readInteger($fr);
}
$targetLengths=array();
$targetOffsets=array();
fseek($fr,$targetOffset);
for($i=0;$i<$count;++$i)
{
$targetLengths[]=$this->readInteger($fr);
$targetOffsets[]=$this->readInteger($fr);
}
$messages=array();
for($i=0;$i<$count;++$i)
{
$id=$this->readString($fr,$sourceLengths[$i],$sourceOffsets[$i]);
$pos = strpos($id,chr(4));
if(($context && $pos!==false && substr($id,0,$pos)===$context) || (!$context && $pos===false))
{
if($pos !== false)
$id=substr($id,$pos+1);
$message=$this->readString($fr,$targetLengths[$i],$targetOffsets[$i]);
$messages[$id]=$message;
}
}
@flock($fr,LOCK_UN);
@fclose($fr);
return $messages;
}
public function save($file,$messages)
{
if(!($fw=@fopen($file,'wb')))
throw new CException(Yii::t('yii','Unable to write file "{file}".',
array('{file}'=>$file)));
if(!@flock($fw,LOCK_EX))
throw new CException(Yii::t('yii','Unable to lock file "{file}" for writing.',
array('{file}'=>$file)));
if($this->useBigEndian)
$this->writeByte($fw,pack('c*', 0x95, 0x04, 0x12, 0xde));
else
$this->writeByte($fw,pack('c*', 0xde, 0x12, 0x04, 0x95));
$this->writeInteger($fw,0);
$n=count($messages);
$this->writeInteger($fw,$n);
$offset=28;
$this->writeInteger($fw,$offset);
$offset+=($n*8);
$this->writeInteger($fw,$offset);
$this->writeInteger($fw,0);
$offset+=($n*8);
$this->writeInteger($fw,$offset);
foreach(array_keys($messages) as $id)
{
$len=strlen($id);
$this->writeInteger($fw,$len);
$this->writeInteger($fw,$offset);
$offset+=$len+1;
}
foreach($messages as $message)
{
$len=strlen($message);
$this->writeInteger($fw,$len);
$this->writeInteger($fw,$offset);
$offset+=$len+1;
}
foreach(array_keys($messages) as $id)
$this->writeString($fw,$id);
foreach($messages as $message)
$this->writeString($fw,$message);
@flock($fw,LOCK_UN);
@fclose($fw);
}
protected function readByte($fr,$n=1)
{
if($n>0)
return fread($fr,$n);
}
protected function writeByte($fw,$data)
{
return fwrite($fw,$data);
}
protected function readInteger($fr)
{
return current($array=unpack($this->useBigEndian ? 'N' : 'V', $this->readByte($fr,4)));
}
protected function writeInteger($fw,$data)
{
return $this->writeByte($fw,pack($this->useBigEndian ? 'N' : 'V', (int)$data));
}
protected function readString($fr,$length,$offset=null)
{
if($offset!==null)
fseek($fr,$offset);
return $this->readByte($fr,$length);
}
protected function writeString($fw,$data)
{
return $this->writeByte($fw,$data."\0");
}
}
