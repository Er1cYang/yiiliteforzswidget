<?php
class CPropertyValue
{
public static function ensureBoolean($value)
{
if (is_string($value))
return !strcasecmp($value,'true') || $value!=0;
else
return (boolean)$value;
}
public static function ensureString($value)
{
if (is_bool($value))
return $value?'true':'false';
else
return (string)$value;
}
public static function ensureInteger($value)
{
return (integer)$value;
}
public static function ensureFloat($value)
{
return (float)$value;
}
public static function ensureArray($value)
{
if(is_string($value))
{
$value = trim($value);
$len = strlen($value);
if ($len >= 2 && $value[0] == '(' && $value[$len-1] == ')')
{
eval('$array=array'.$value.';');
return $array;
}
else
return $len>0?array($value):array();
}
else
return (array)$value;
}
public static function ensureObject($value)
{
return (object)$value;
}
public static function ensureEnum($value,$enumType)
{
static $types=array();
if(!isset($types[$enumType]))
$types[$enumType]=new ReflectionClass($enumType);
if($types[$enumType]->hasConstant($value))
return $value;
else
throw new CException(Yii::t('yii','Invalid enumerable value "{value}". Please make sure it is among ({enum}).',
array('{value}'=>$value, '{enum}'=>implode(', ',$types[$enumType]->getConstants()))));
}
}
