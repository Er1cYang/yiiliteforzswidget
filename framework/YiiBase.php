<?php
defined('YII_BEGIN_TIME') or define('YII_BEGIN_TIME',microtime(true));
defined('YII_DEBUG') or define('YII_DEBUG',false);
defined('YII_TRACE_LEVEL') or define('YII_TRACE_LEVEL',0);
defined('YII_ENABLE_EXCEPTION_HANDLER') or define('YII_ENABLE_EXCEPTION_HANDLER',true);
defined('YII_ENABLE_ERROR_HANDLER') or define('YII_ENABLE_ERROR_HANDLER',true);
defined('YII_PATH') or define('YII_PATH',dirname(__FILE__));
defined('YII_ZII_PATH') or define('YII_ZII_PATH',YII_PATH.DIRECTORY_SEPARATOR.'zii');
class YiiBase
{
public static $classMap=array();
public static $enableIncludePath=true;
private static $_aliases=array('system'=>YII_PATH,'zii'=>YII_ZII_PATH);//alias=>path
private static $_imports=array();//alias=>class name or directory
private static $_includePaths;//list of include paths
private static $_app;
private static $_logger;
public static function getVersion()
{
return '1.1.12';
}
public static function createWebApplication($config=null)
{
return self::createApplication('CWebApplication',$config);
}
public static function createConsoleApplication($config=null)
{
return self::createApplication('CConsoleApplication',$config);
}
public static function createApplication($class,$config=null)
{
return new $class($config);
}
public static function app()
{
return self::$_app;
}
public static function setApplication($app)
{
if(self::$_app===null || $app===null)
self::$_app=$app;
else
throw new CException(Yii::t('yii','Yii application can only be created once.'));
}
public static function getFrameworkPath()
{
return YII_PATH;
}
public static function createComponent($config)
{
if(is_string($config))
{
$type=$config;
$config=array();
}
else if(isset($config['class']))
{
$type=$config['class'];
unset($config['class']);
}
else
throw new CException(Yii::t('yii','Object configuration must be an array containing a "class" element.'));
if(!class_exists($type,false))
$type=Yii::import($type,true);
if(($n=func_num_args())>1)
{
$args=func_get_args();
if($n===2)
$object=new $type($args[1]);
else if($n===3)
$object=new $type($args[1],$args[2]);
else if($n===4)
$object=new $type($args[1],$args[2],$args[3]);
else
{
unset($args[0]);
$class=new ReflectionClass($type);
$object=call_user_func_array(array($class,'newInstance'),$args);
}
}
else
$object=new $type;
foreach($config as $key=>$value)
$object->$key=$value;
return $object;
}
public static function import($alias,$forceInclude=false)
{
if(isset(self::$_imports[$alias]))//previously imported
return self::$_imports[$alias];
if(class_exists($alias,false) || interface_exists($alias,false))
return self::$_imports[$alias]=$alias;
if(($pos=strrpos($alias,'\\'))!==false)//a class name in PHP 5.3 namespace format
{
$namespace=str_replace('\\','.',ltrim(substr($alias,0,$pos),'\\'));
if(($path=self::getPathOfAlias($namespace))!==false)
{
$classFile=$path.DIRECTORY_SEPARATOR.substr($alias,$pos+1).'.php';
if($forceInclude)
{
if(is_file($classFile))
require($classFile);
else
throw new CException(Yii::t('yii','Alias "{alias}" is invalid. Make sure it points to an existing PHP file and the file is readable.',array('{alias}'=>$alias)));
self::$_imports[$alias]=$alias;
}
else
self::$classMap[$alias]=$classFile;
return $alias;
}
else
throw new CException(Yii::t('yii','Alias "{alias}" is invalid. Make sure it points to an existing directory.',
array('{alias}'=>$namespace)));
}
if(($pos=strrpos($alias,'.'))===false)//a simple class name
{
if($forceInclude && self::autoload($alias))
self::$_imports[$alias]=$alias;
return $alias;
}
$className=(string)substr($alias,$pos+1);
$isClass=$className!=='*';
if($isClass && (class_exists($className,false) || interface_exists($className,false)))
return self::$_imports[$alias]=$className;
if(($path=self::getPathOfAlias($alias))!==false)
{
if($isClass)
{
if($forceInclude)
{
if(is_file($path.'.php'))
require($path.'.php');
else
throw new CException(Yii::t('yii','Alias "{alias}" is invalid. Make sure it points to an existing PHP file and the file is readable.',array('{alias}'=>$alias)));
self::$_imports[$alias]=$className;
}
else
self::$classMap[$className]=$path.'.php';
return $className;
}
else//a directory
{
if(self::$_includePaths===null)
{
self::$_includePaths=array_unique(explode(PATH_SEPARATOR,get_include_path()));
if(($pos=array_search('.',self::$_includePaths,true))!==false)
unset(self::$_includePaths[$pos]);
}
array_unshift(self::$_includePaths,$path);
if(self::$enableIncludePath && set_include_path('.'.PATH_SEPARATOR.implode(PATH_SEPARATOR,self::$_includePaths))===false)
self::$enableIncludePath=false;
return self::$_imports[$alias]=$path;
}
}
else
throw new CException(Yii::t('yii','Alias "{alias}" is invalid. Make sure it points to an existing directory or file.',
array('{alias}'=>$alias)));
}
public static function getPathOfAlias($alias)
{
if(isset(self::$_aliases[$alias]))
return self::$_aliases[$alias];
else if(($pos=strpos($alias,'.'))!==false)
{
$rootAlias=substr($alias,0,$pos);
if(isset(self::$_aliases[$rootAlias]))
return self::$_aliases[$alias]=rtrim(self::$_aliases[$rootAlias].DIRECTORY_SEPARATOR.str_replace('.',DIRECTORY_SEPARATOR,substr($alias,$pos+1)),'*'.DIRECTORY_SEPARATOR);
else if(self::$_app instanceof CWebApplication)
{
if(self::$_app->findModule($rootAlias)!==null)
return self::getPathOfAlias($alias);
}
}
return false;
}
public static function setPathOfAlias($alias,$path)
{
if(empty($path))
unset(self::$_aliases[$alias]);
else
self::$_aliases[$alias]=rtrim($path,'\\/');
}
public static function autoload($className)
{
if(isset(self::$classMap[$className]))
include(self::$classMap[$className]);
else if(isset(self::$_coreClasses[$className]))
include(YII_PATH.self::$_coreClasses[$className]);
else
{
if(strpos($className,'\\')===false)//class without namespace
{
if(self::$enableIncludePath===false)
{
foreach(self::$_includePaths as $path)
{
$classFile=$path.DIRECTORY_SEPARATOR.$className.'.php';
if(is_file($classFile))
{
include($classFile);
if(YII_DEBUG && basename(realpath($classFile))!==$className.'.php')
throw new CException(Yii::t('yii','Class name "{class}" does not match class file "{file}".', array(
'{class}'=>$className,
'{file}'=>$classFile,
)));
break;
}
}
}
else
include($className.'.php');
}
else//class name with namespace in PHP 5.3
{
$namespace=str_replace('\\','.',ltrim($className,'\\'));
if(($path=self::getPathOfAlias($namespace))!==false)
include($path.'.php');
else
return false;
}
return class_exists($className,false) || interface_exists($className,false);
}
return true;
}
public static function trace($msg,$category='application')
{
if(YII_DEBUG)
self::log($msg,CLogger::LEVEL_TRACE,$category);
}
public static function log($msg,$level=CLogger::LEVEL_INFO,$category='application')
{
if(self::$_logger===null)
self::$_logger=new CLogger;
if(YII_DEBUG && YII_TRACE_LEVEL>0 && $level!==CLogger::LEVEL_PROFILE)
{
$traces=debug_backtrace();
$count=0;
foreach($traces as $trace)
{
if(isset($trace['file'],$trace['line']) && strpos($trace['file'],YII_PATH)!==0)
{
$msg.="\nin ".$trace['file'].' ('.$trace['line'].')';
if(++$count>=YII_TRACE_LEVEL)
break;
}
}
}
self::$_logger->log($msg,$level,$category);
}
public static function beginProfile($token,$category='application')
{
self::log('begin:'.$token,CLogger::LEVEL_PROFILE,$category);
}
public static function endProfile($token,$category='application')
{
self::log('end:'.$token,CLogger::LEVEL_PROFILE,$category);
}
public static function getLogger()
{
if(self::$_logger!==null)
return self::$_logger;
else
return self::$_logger=new CLogger;
}
public static function setLogger($logger)
{
self::$_logger=$logger;
}
public static function powered()
{
return Yii::t('yii','Powered by {yii}.', array('{yii}'=>'<a href="http://www.yiiframework.com/" rel="external">Yii Framework</a>'));
}
public static function t($category,$message,$params=array(),$source=null,$language=null)
{
if(self::$_app!==null)
{
if($source===null)
$source=($category==='yii'||$category==='zii')?'coreMessages':'messages';
if(($source=self::$_app->getComponent($source))!==null)
$message=$source->translate($category,$message,$language);
}
if($params===array())
return $message;
if(!is_array($params))
$params=array($params);
if(isset($params[0]))//number choice
{
if(strpos($message,'|')!==false)
{
if(strpos($message,'#')===false)
{
$chunks=explode('|',$message);
$expressions=self::$_app->getLocale($language)->getPluralRules();
if($n=min(count($chunks),count($expressions)))
{
for($i=0;$i<$n;$i++)
$chunks[$i]=$expressions[$i].'#'.$chunks[$i];
$message=implode('|',$chunks);
}
}
$message=CChoiceFormat::format($message,$params[0]);
}
if(!isset($params['{n}']))
$params['{n}']=$params[0];
unset($params[0]);
}
return $params!==array() ? strtr($message,$params) : $message;
}
public static function registerAutoloader($callback, $append=false)
{
if($append)
{
self::$enableIncludePath=false;
spl_autoload_register($callback);
}
else
{
spl_autoload_unregister(array('YiiBase','autoload'));
spl_autoload_register($callback);
spl_autoload_register(array('YiiBase','autoload'));
}
}
private static $_coreClasses=array(
'CApplication'=>'/base/CApplication.php',
'CApplicationComponent'=>'/base/CApplicationComponent.php',
'CBehavior'=>'/base/CBehavior.php',
'CComponent'=>'/base/CComponent.php',
'CErrorEvent'=>'/base/CErrorEvent.php',
'CErrorHandler'=>'/base/CErrorHandler.php',
'CException'=>'/base/CException.php',
'CExceptionEvent'=>'/base/CExceptionEvent.php',
'CHttpException'=>'/base/CHttpException.php',
'CModel'=>'/base/CModel.php',
'CModelBehavior'=>'/base/CModelBehavior.php',
'CModelEvent'=>'/base/CModelEvent.php',
'CModule'=>'/base/CModule.php',
'CSecurityManager'=>'/base/CSecurityManager.php',
'CStatePersister'=>'/base/CStatePersister.php',
'CApcCache'=>'/caching/CApcCache.php',
'CCache'=>'/caching/CCache.php',
'CDbCache'=>'/caching/CDbCache.php',
'CDummyCache'=>'/caching/CDummyCache.php',
'CEAcceleratorCache'=>'/caching/CEAcceleratorCache.php',
'CFileCache'=>'/caching/CFileCache.php',
'CMemCache'=>'/caching/CMemCache.php',
'CWinCache'=>'/caching/CWinCache.php',
'CXCache'=>'/caching/CXCache.php',
'CZendDataCache'=>'/caching/CZendDataCache.php',
'CCacheDependency'=>'/caching/dependencies/CCacheDependency.php',
'CChainedCacheDependency'=>'/caching/dependencies/CChainedCacheDependency.php',
'CDbCacheDependency'=>'/caching/dependencies/CDbCacheDependency.php',
'CDirectoryCacheDependency'=>'/caching/dependencies/CDirectoryCacheDependency.php',
'CExpressionDependency'=>'/caching/dependencies/CExpressionDependency.php',
'CFileCacheDependency'=>'/caching/dependencies/CFileCacheDependency.php',
'CGlobalStateCacheDependency'=>'/caching/dependencies/CGlobalStateCacheDependency.php',
'CAttributeCollection'=>'/collections/CAttributeCollection.php',
'CConfiguration'=>'/collections/CConfiguration.php',
'CList'=>'/collections/CList.php',
'CListIterator'=>'/collections/CListIterator.php',
'CMap'=>'/collections/CMap.php',
'CMapIterator'=>'/collections/CMapIterator.php',
'CQueue'=>'/collections/CQueue.php',
'CQueueIterator'=>'/collections/CQueueIterator.php',
'CStack'=>'/collections/CStack.php',
'CStackIterator'=>'/collections/CStackIterator.php',
'CTypedList'=>'/collections/CTypedList.php',
'CTypedMap'=>'/collections/CTypedMap.php',
'CConsoleApplication'=>'/console/CConsoleApplication.php',
'CConsoleCommand'=>'/console/CConsoleCommand.php',
'CConsoleCommandBehavior'=>'/console/CConsoleCommandBehavior.php',
'CConsoleCommandEvent'=>'/console/CConsoleCommandEvent.php',
'CConsoleCommandRunner'=>'/console/CConsoleCommandRunner.php',
'CHelpCommand'=>'/console/CHelpCommand.php',
'CDbCommand'=>'/db/CDbCommand.php',
'CDbConnection'=>'/db/CDbConnection.php',
'CDbDataReader'=>'/db/CDbDataReader.php',
'CDbException'=>'/db/CDbException.php',
'CDbMigration'=>'/db/CDbMigration.php',
'CDbTransaction'=>'/db/CDbTransaction.php',
'CActiveFinder'=>'/db/ar/CActiveFinder.php',
'CActiveRecord'=>'/db/ar/CActiveRecord.php',
'CActiveRecordBehavior'=>'/db/ar/CActiveRecordBehavior.php',
'CDbColumnSchema'=>'/db/schema/CDbColumnSchema.php',
'CDbCommandBuilder'=>'/db/schema/CDbCommandBuilder.php',
'CDbCriteria'=>'/db/schema/CDbCriteria.php',
'CDbExpression'=>'/db/schema/CDbExpression.php',
'CDbSchema'=>'/db/schema/CDbSchema.php',
'CDbTableSchema'=>'/db/schema/CDbTableSchema.php',
'CMssqlColumnSchema'=>'/db/schema/mssql/CMssqlColumnSchema.php',
'CMssqlCommandBuilder'=>'/db/schema/mssql/CMssqlCommandBuilder.php',
'CMssqlPdoAdapter'=>'/db/schema/mssql/CMssqlPdoAdapter.php',
'CMssqlSchema'=>'/db/schema/mssql/CMssqlSchema.php',
'CMssqlTableSchema'=>'/db/schema/mssql/CMssqlTableSchema.php',
'CMysqlColumnSchema'=>'/db/schema/mysql/CMysqlColumnSchema.php',
'CMysqlSchema'=>'/db/schema/mysql/CMysqlSchema.php',
'CMysqlTableSchema'=>'/db/schema/mysql/CMysqlTableSchema.php',
'COciColumnSchema'=>'/db/schema/oci/COciColumnSchema.php',
'COciCommandBuilder'=>'/db/schema/oci/COciCommandBuilder.php',
'COciSchema'=>'/db/schema/oci/COciSchema.php',
'COciTableSchema'=>'/db/schema/oci/COciTableSchema.php',
'CPgsqlColumnSchema'=>'/db/schema/pgsql/CPgsqlColumnSchema.php',
'CPgsqlSchema'=>'/db/schema/pgsql/CPgsqlSchema.php',
'CPgsqlTableSchema'=>'/db/schema/pgsql/CPgsqlTableSchema.php',
'CSqliteColumnSchema'=>'/db/schema/sqlite/CSqliteColumnSchema.php',
'CSqliteCommandBuilder'=>'/db/schema/sqlite/CSqliteCommandBuilder.php',
'CSqliteSchema'=>'/db/schema/sqlite/CSqliteSchema.php',
'CChoiceFormat'=>'/i18n/CChoiceFormat.php',
'CDateFormatter'=>'/i18n/CDateFormatter.php',
'CDbMessageSource'=>'/i18n/CDbMessageSource.php',
'CGettextMessageSource'=>'/i18n/CGettextMessageSource.php',
'CLocale'=>'/i18n/CLocale.php',
'CMessageSource'=>'/i18n/CMessageSource.php',
'CNumberFormatter'=>'/i18n/CNumberFormatter.php',
'CPhpMessageSource'=>'/i18n/CPhpMessageSource.php',
'CGettextFile'=>'/i18n/gettext/CGettextFile.php',
'CGettextMoFile'=>'/i18n/gettext/CGettextMoFile.php',
'CGettextPoFile'=>'/i18n/gettext/CGettextPoFile.php',
'CDbLogRoute'=>'/logging/CDbLogRoute.php',
'CEmailLogRoute'=>'/logging/CEmailLogRoute.php',
'CFileLogRoute'=>'/logging/CFileLogRoute.php',
'CLogFilter'=>'/logging/CLogFilter.php',
'CLogRoute'=>'/logging/CLogRoute.php',
'CLogRouter'=>'/logging/CLogRouter.php',
'CLogger'=>'/logging/CLogger.php',
'CProfileLogRoute'=>'/logging/CProfileLogRoute.php',
'CWebLogRoute'=>'/logging/CWebLogRoute.php',
'CDateTimeParser'=>'/utils/CDateTimeParser.php',
'CFileHelper'=>'/utils/CFileHelper.php',
'CFormatter'=>'/utils/CFormatter.php',
'CMarkdownParser'=>'/utils/CMarkdownParser.php',
'CPropertyValue'=>'/utils/CPropertyValue.php',
'CTimestamp'=>'/utils/CTimestamp.php',
'CVarDumper'=>'/utils/CVarDumper.php',
'CBooleanValidator'=>'/validators/CBooleanValidator.php',
'CCaptchaValidator'=>'/validators/CCaptchaValidator.php',
'CCompareValidator'=>'/validators/CCompareValidator.php',
'CDateValidator'=>'/validators/CDateValidator.php',
'CDefaultValueValidator'=>'/validators/CDefaultValueValidator.php',
'CEmailValidator'=>'/validators/CEmailValidator.php',
'CExistValidator'=>'/validators/CExistValidator.php',
'CFileValidator'=>'/validators/CFileValidator.php',
'CFilterValidator'=>'/validators/CFilterValidator.php',
'CInlineValidator'=>'/validators/CInlineValidator.php',
'CNumberValidator'=>'/validators/CNumberValidator.php',
'CRangeValidator'=>'/validators/CRangeValidator.php',
'CRegularExpressionValidator'=>'/validators/CRegularExpressionValidator.php',
'CRequiredValidator'=>'/validators/CRequiredValidator.php',
'CSafeValidator'=>'/validators/CSafeValidator.php',
'CStringValidator'=>'/validators/CStringValidator.php',
'CTypeValidator'=>'/validators/CTypeValidator.php',
'CUniqueValidator'=>'/validators/CUniqueValidator.php',
'CUnsafeValidator'=>'/validators/CUnsafeValidator.php',
'CUrlValidator'=>'/validators/CUrlValidator.php',
'CValidator'=>'/validators/CValidator.php',
'CActiveDataProvider'=>'/web/CActiveDataProvider.php',
'CArrayDataProvider'=>'/web/CArrayDataProvider.php',
'CAssetManager'=>'/web/CAssetManager.php',
'CBaseController'=>'/web/CBaseController.php',
'CCacheHttpSession'=>'/web/CCacheHttpSession.php',
'CClientScript'=>'/web/CClientScript.php',
'CController'=>'/web/CController.php',
'CDataProvider'=>'/web/CDataProvider.php',
'CDbHttpSession'=>'/web/CDbHttpSession.php',
'CExtController'=>'/web/CExtController.php',
'CFormModel'=>'/web/CFormModel.php',
'CHttpCookie'=>'/web/CHttpCookie.php',
'CHttpRequest'=>'/web/CHttpRequest.php',
'CHttpSession'=>'/web/CHttpSession.php',
'CHttpSessionIterator'=>'/web/CHttpSessionIterator.php',
'COutputEvent'=>'/web/COutputEvent.php',
'CPagination'=>'/web/CPagination.php',
'CSort'=>'/web/CSort.php',
'CSqlDataProvider'=>'/web/CSqlDataProvider.php',
'CTheme'=>'/web/CTheme.php',
'CThemeManager'=>'/web/CThemeManager.php',
'CUploadedFile'=>'/web/CUploadedFile.php',
'CUrlManager'=>'/web/CUrlManager.php',
'CWebApplication'=>'/web/CWebApplication.php',
'CWebModule'=>'/web/CWebModule.php',
'CWidgetFactory'=>'/web/CWidgetFactory.php',
'CAction'=>'/web/actions/CAction.php',
'CInlineAction'=>'/web/actions/CInlineAction.php',
'CViewAction'=>'/web/actions/CViewAction.php',
'CAccessControlFilter'=>'/web/auth/CAccessControlFilter.php',
'CAuthAssignment'=>'/web/auth/CAuthAssignment.php',
'CAuthItem'=>'/web/auth/CAuthItem.php',
'CAuthManager'=>'/web/auth/CAuthManager.php',
'CBaseUserIdentity'=>'/web/auth/CBaseUserIdentity.php',
'CDbAuthManager'=>'/web/auth/CDbAuthManager.php',
'CPhpAuthManager'=>'/web/auth/CPhpAuthManager.php',
'CUserIdentity'=>'/web/auth/CUserIdentity.php',
'CWebUser'=>'/web/auth/CWebUser.php',
'CFilter'=>'/web/filters/CFilter.php',
'CFilterChain'=>'/web/filters/CFilterChain.php',
'CHttpCacheFilter'=>'/web/filters/CHttpCacheFilter.php',
'CInlineFilter'=>'/web/filters/CInlineFilter.php',
'CForm'=>'/web/form/CForm.php',
'CFormButtonElement'=>'/web/form/CFormButtonElement.php',
'CFormElement'=>'/web/form/CFormElement.php',
'CFormElementCollection'=>'/web/form/CFormElementCollection.php',
'CFormInputElement'=>'/web/form/CFormInputElement.php',
'CFormStringElement'=>'/web/form/CFormStringElement.php',
'CGoogleApi'=>'/web/helpers/CGoogleApi.php',
'CHtml'=>'/web/helpers/CHtml.php',
'CJSON'=>'/web/helpers/CJSON.php',
'CJavaScript'=>'/web/helpers/CJavaScript.php',
'CJavaScriptExpression'=>'/web/helpers/CJavaScriptExpression.php',
'CPradoViewRenderer'=>'/web/renderers/CPradoViewRenderer.php',
'CViewRenderer'=>'/web/renderers/CViewRenderer.php',
'CWebService'=>'/web/services/CWebService.php',
'CWebServiceAction'=>'/web/services/CWebServiceAction.php',
'CWsdlGenerator'=>'/web/services/CWsdlGenerator.php',
'CActiveForm'=>'/web/widgets/CActiveForm.php',
'CAutoComplete'=>'/web/widgets/CAutoComplete.php',
'CClipWidget'=>'/web/widgets/CClipWidget.php',
'CContentDecorator'=>'/web/widgets/CContentDecorator.php',
'CFilterWidget'=>'/web/widgets/CFilterWidget.php',
'CFlexWidget'=>'/web/widgets/CFlexWidget.php',
'CHtmlPurifier'=>'/web/widgets/CHtmlPurifier.php',
'CInputWidget'=>'/web/widgets/CInputWidget.php',
'CMarkdown'=>'/web/widgets/CMarkdown.php',
'CMaskedTextField'=>'/web/widgets/CMaskedTextField.php',
'CMultiFileUpload'=>'/web/widgets/CMultiFileUpload.php',
'COutputCache'=>'/web/widgets/COutputCache.php',
'COutputProcessor'=>'/web/widgets/COutputProcessor.php',
'CStarRating'=>'/web/widgets/CStarRating.php',
'CTabView'=>'/web/widgets/CTabView.php',
'CTextHighlighter'=>'/web/widgets/CTextHighlighter.php',
'CTreeView'=>'/web/widgets/CTreeView.php',
'CWidget'=>'/web/widgets/CWidget.php',
'CCaptcha'=>'/web/widgets/captcha/CCaptcha.php',
'CCaptchaAction'=>'/web/widgets/captcha/CCaptchaAction.php',
'CBasePager'=>'/web/widgets/pagers/CBasePager.php',
'CLinkPager'=>'/web/widgets/pagers/CLinkPager.php',
'CListPager'=>'/web/widgets/pagers/CListPager.php',
);
}
spl_autoload_register(array('YiiBase','autoload'));
require(YII_PATH.'/base/interfaces.php');
