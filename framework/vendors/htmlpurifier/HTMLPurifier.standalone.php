<?php
class HTMLPurifier
{
public $version = '4.4.0';
const VERSION = '4.4.0';
public $config;
private $filters = array();
private static $instance;
protected $strategy, $generator;
public $context;
public function __construct($config = null) {
$this->config = HTMLPurifier_Config::create($config);
$this->strategy     = new HTMLPurifier_Strategy_Core();
}
public function addFilter($filter) {
trigger_error('HTMLPurifier->addFilter() is deprecated, use configuration directives in the Filter namespace or Filter.Custom', E_USER_WARNING);
$this->filters[] = $filter;
}
public function purify($html, $config = null) {
$config = $config ? HTMLPurifier_Config::create($config) : $this->config;
$lexer = HTMLPurifier_Lexer::create($config);
$context = new HTMLPurifier_Context();
$this->generator = new HTMLPurifier_Generator($config, $context);
$context->register('Generator', $this->generator);
if ($config->get('Core.CollectErrors')) {
$language_factory = HTMLPurifier_LanguageFactory::instance();
$language = $language_factory->create($config, $context);
$context->register('Locale', $language);
$error_collector = new HTMLPurifier_ErrorCollector($context);
$context->register('ErrorCollector', $error_collector);
}
$id_accumulator = HTMLPurifier_IDAccumulator::build($config, $context);
$context->register('IDAccumulator', $id_accumulator);
$html = HTMLPurifier_Encoder::convertToUTF8($html, $config, $context);
$filter_flags = $config->getBatch('Filter');
$custom_filters = $filter_flags['Custom'];
unset($filter_flags['Custom']);
$filters = array();
foreach ($filter_flags as $filter=>$flag) {
if (!$flag) continue;
if (strpos($filter, '.') !== false) continue;
$class = "HTMLPurifier_Filter_$filter";
$filters[] = new $class;
}
foreach ($custom_filters as $filter) {
$filters[] = $filter;
}
$filters = array_merge($filters, $this->filters);
for ($i = 0, $filter_size = count($filters); $i < $filter_size; $i++) {
$html = $filters[$i]->preFilter($html, $config, $context);
}
$html =
$this->generator->generateFromTokens(
$this->strategy->execute(
$lexer->tokenizeHTML(
$html, $config, $context
),
$config, $context
)
);
for ($i = $filter_size - 1; $i >= 0; $i--) {
$html = $filters[$i]->postFilter($html, $config, $context);
}
$html = HTMLPurifier_Encoder::convertFromUTF8($html, $config, $context);
$this->context =& $context;
return $html;
}
public function purifyArray($array_of_html, $config = null) {
$context_array = array();
foreach ($array_of_html as $key=>$html) {
$array_of_html[$key] = $this->purify($html, $config);
$context_array[$key] = $this->context;
}
$this->context = $context_array;
return $array_of_html;
}
public static function instance($prototype = null) {
if (!self::$instance || $prototype) {
if ($prototype instanceof HTMLPurifier) {
self::$instance = $prototype;
} elseif ($prototype) {
self::$instance = new HTMLPurifier($prototype);
} else {
self::$instance = new HTMLPurifier();
}
}
return self::$instance;
}
public static function getInstance($prototype = null) {
return HTMLPurifier::instance($prototype);
}
}
class HTMLPurifier_AttrCollections
{
public $info = array();
public function __construct($attr_types, $modules) {
foreach ($modules as $module) {
foreach ($module->attr_collections as $coll_i=>$coll) {
if (!isset($this->info[$coll_i])) {
$this->info[$coll_i] = array();
}
foreach ($coll as $attr_i=>$attr) {
if ($attr_i === 0 && isset($this->info[$coll_i][$attr_i])) {
$this->info[$coll_i][$attr_i] = array_merge(
$this->info[$coll_i][$attr_i], $attr);
continue;
}
$this->info[$coll_i][$attr_i] = $attr;
}
}
}
foreach ($this->info as $name=>$attr) {
$this->performInclusions($this->info[$name]);
$this->expandIdentifiers($this->info[$name], $attr_types);
}
}
public function performInclusions(&$attr) {
if (!isset($attr[0])) return;
$merge = $attr[0];
$seen  = array(); // recursion guard
for ($i = 0; isset($merge[$i]); $i++) {
if (isset($seen[$merge[$i]])) continue;
$seen[$merge[$i]] = true;
if (!isset($this->info[$merge[$i]])) continue;
foreach ($this->info[$merge[$i]] as $key=>$value) {
if (isset($attr[$key])) continue; // also catches more inclusions
$attr[$key] = $value;
}
if (isset($this->info[$merge[$i]][0])) {
$merge = array_merge($merge, $this->info[$merge[$i]][0]);
}
}
unset($attr[0]);
}
public function expandIdentifiers(&$attr, $attr_types) {
$processed = array();
foreach ($attr as $def_i=>$def) {
if ($def_i === 0) continue;
if (isset($processed[$def_i])) continue;
if ($required = (strpos($def_i, '*') !== false)) {
unset($attr[$def_i]);
$def_i = trim($def_i, '*');
$attr[$def_i] = $def;
}
$processed[$def_i] = true;
if (is_object($def)) {
$attr[$def_i]->required = ($required || $attr[$def_i]->required);
continue;
}
if ($def === false) {
unset($attr[$def_i]);
continue;
}
if ($t = $attr_types->get($def)) {
$attr[$def_i] = $t;
$attr[$def_i]->required = $required;
} else {
unset($attr[$def_i]);
}
}
}
}
abstract class HTMLPurifier_AttrDef
{
public $minimized = false;
public $required = false;
abstract public function validate($string, $config, $context);
public function parseCDATA($string) {
$string = trim($string);
$string = str_replace(array("\n", "\t", "\r"), ' ', $string);
return $string;
}
public function make($string) {
return $this;
}
protected function mungeRgb($string) {
return preg_replace('/rgb\((\d+)\s*,\s*(\d+)\s*,\s*(\d+)\)/', 'rgb(\1,\2,\3)', $string);
}
protected function expandCSSEscape($string) {
$ret = '';
for ($i = 0, $c = strlen($string); $i < $c; $i++) {
if ($string[$i] === '\\') {
$i++;
if ($i >= $c) {
$ret .= '\\';
break;
}
if (ctype_xdigit($string[$i])) {
$code = $string[$i];
for ($a = 1, $i++; $i < $c && $a < 6; $i++, $a++) {
if (!ctype_xdigit($string[$i])) break;
$code .= $string[$i];
}
$char = HTMLPurifier_Encoder::unichr(hexdec($code));
if (HTMLPurifier_Encoder::cleanUTF8($char) === '') continue;
$ret .= $char;
if ($i < $c && trim($string[$i]) !== '') $i--;
continue;
}
if ($string[$i] === "\n") continue;
}
$ret .= $string[$i];
}
return $ret;
}
}
abstract class HTMLPurifier_AttrTransform
{
abstract public function transform($attr, $config, $context);
public function prependCSS(&$attr, $css) {
$attr['style'] = isset($attr['style']) ? $attr['style'] : '';
$attr['style'] = $css . $attr['style'];
}
public function confiscateAttr(&$attr, $key) {
if (!isset($attr[$key])) return null;
$value = $attr[$key];
unset($attr[$key]);
return $value;
}
}
class HTMLPurifier_AttrTypes
{
protected $info = array();
public function __construct() {
$this->info['Enum']    = new HTMLPurifier_AttrDef_Enum();
$this->info['Bool']    = new HTMLPurifier_AttrDef_HTML_Bool();
$this->info['CDATA']    = new HTMLPurifier_AttrDef_Text();
$this->info['ID']       = new HTMLPurifier_AttrDef_HTML_ID();
$this->info['Length']   = new HTMLPurifier_AttrDef_HTML_Length();
$this->info['MultiLength'] = new HTMLPurifier_AttrDef_HTML_MultiLength();
$this->info['NMTOKENS'] = new HTMLPurifier_AttrDef_HTML_Nmtokens();
$this->info['Pixels']   = new HTMLPurifier_AttrDef_HTML_Pixels();
$this->info['Text']     = new HTMLPurifier_AttrDef_Text();
$this->info['URI']      = new HTMLPurifier_AttrDef_URI();
$this->info['LanguageCode'] = new HTMLPurifier_AttrDef_Lang();
$this->info['Color']    = new HTMLPurifier_AttrDef_HTML_Color();
$this->info['IAlign']   = self::makeEnum('top,middle,bottom,left,right');
$this->info['LAlign']   = self::makeEnum('top,bottom,left,right');
$this->info['FrameTarget'] = new HTMLPurifier_AttrDef_HTML_FrameTarget();
$this->info['ContentType'] = new HTMLPurifier_AttrDef_Text();
$this->info['ContentTypes'] = new HTMLPurifier_AttrDef_Text();
$this->info['Charsets'] = new HTMLPurifier_AttrDef_Text();
$this->info['Character'] = new HTMLPurifier_AttrDef_Text();
$this->info['Class'] = new HTMLPurifier_AttrDef_HTML_Class();
$this->info['Number']   = new HTMLPurifier_AttrDef_Integer(false, false, true);
}
private static function makeEnum($in) {
return new HTMLPurifier_AttrDef_Clone(new HTMLPurifier_AttrDef_Enum(explode(',', $in)));
}
public function get($type) {
if (strpos($type, '#') !== false) list($type, $string) = explode('#', $type, 2);
else $string = '';
if (!isset($this->info[$type])) {
trigger_error('Cannot retrieve undefined attribute type ' . $type, E_USER_ERROR);
return;
}
return $this->info[$type]->make($string);
}
public function set($type, $impl) {
$this->info[$type] = $impl;
}
}
class HTMLPurifier_AttrValidator
{
public function validateToken(&$token, &$config, $context) {
$definition = $config->getHTMLDefinition();
$e =& $context->get('ErrorCollector', true);
$ok =& $context->get('IDAccumulator', true);
if (!$ok) {
$id_accumulator = HTMLPurifier_IDAccumulator::build($config, $context);
$context->register('IDAccumulator', $id_accumulator);
}
$current_token =& $context->get('CurrentToken', true);
if (!$current_token) $context->register('CurrentToken', $token);
if (
!$token instanceof HTMLPurifier_Token_Start &&
!$token instanceof HTMLPurifier_Token_Empty
) return $token;
$d_defs = $definition->info_global_attr;
$attr = $token->attr;
foreach ($definition->info_attr_transform_pre as $transform) {
$attr = $transform->transform($o = $attr, $config, $context);
if ($e) {
if ($attr != $o) $e->send(E_NOTICE, 'AttrValidator: Attributes transformed', $o, $attr);
}
}
foreach ($definition->info[$token->name]->attr_transform_pre as $transform) {
$attr = $transform->transform($o = $attr, $config, $context);
if ($e) {
if ($attr != $o) $e->send(E_NOTICE, 'AttrValidator: Attributes transformed', $o, $attr);
}
}
$defs = $definition->info[$token->name]->attr;
$attr_key = false;
$context->register('CurrentAttr', $attr_key);
foreach ($attr as $attr_key=>$value) {
if ( isset($defs[$attr_key]) ) {
if ($defs[$attr_key] === false) {
$result = false;
} else {
$result = $defs[$attr_key]->validate(
$value, $config, $context
);
}
} elseif ( isset($d_defs[$attr_key]) ) {
$result = $d_defs[$attr_key]->validate(
$value, $config, $context
);
} else {
$result = false;
}
if ($result === false || $result === null) {
if ($e) $e->send(E_ERROR, 'AttrValidator: Attribute removed');
unset($attr[$attr_key]);
} elseif (is_string($result)) {
$attr[$attr_key] = $result;
} else {
}
}
$context->destroy('CurrentAttr');
foreach ($definition->info_attr_transform_post as $transform) {
$attr = $transform->transform($o = $attr, $config, $context);
if ($e) {
if ($attr != $o) $e->send(E_NOTICE, 'AttrValidator: Attributes transformed', $o, $attr);
}
}
foreach ($definition->info[$token->name]->attr_transform_post as $transform) {
$attr = $transform->transform($o = $attr, $config, $context);
if ($e) {
if ($attr != $o) $e->send(E_NOTICE, 'AttrValidator: Attributes transformed', $o, $attr);
}
}
$token->attr = $attr;
if (!$current_token) $context->destroy('CurrentToken');
}
}
if (!defined('HTMLPURIFIER_PREFIX')) {
define('HTMLPURIFIER_PREFIX', dirname(__FILE__) . '/standalone');
set_include_path(HTMLPURIFIER_PREFIX . PATH_SEPARATOR . get_include_path());
}
// borrowed from PHP_Compat, LGPL licensed, by Aidan Lister <aidan@php.net>
if (!defined('PHP_EOL')) {
switch (strtoupper(substr(PHP_OS, 0, 3))) {
case 'WIN':
define('PHP_EOL', "\r\n");
break;
case 'DAR':
define('PHP_EOL', "\r");
break;
default:
define('PHP_EOL', "\n");
}
}
class HTMLPurifier_Bootstrap
{
public static function autoload($class) {
$file = HTMLPurifier_Bootstrap::getPath($class);
if (!$file) return false;
require_once HTMLPURIFIER_PREFIX . '/' . $file;
return true;
}
public static function getPath($class) {
if (strncmp('HTMLPurifier', $class, 12) !== 0) return false;
if (strncmp('HTMLPurifier_Language_', $class, 22) === 0) {
$code = str_replace('_', '-', substr($class, 22));
$file = 'HTMLPurifier/Language/classes/' . $code . '.php';
} else {
$file = str_replace('_', '/', $class) . '.php';
}
if (!file_exists(HTMLPURIFIER_PREFIX . '/' . $file)) return false;
return $file;
}
public static function registerAutoload() {
$autoload = array('HTMLPurifier_Bootstrap', 'autoload');
if ( ($funcs = spl_autoload_functions()) === false ) {
spl_autoload_register($autoload);
} elseif (function_exists('spl_autoload_unregister')) {
$buggy  = version_compare(PHP_VERSION, '5.2.11', '<');
$compat = version_compare(PHP_VERSION, '5.1.2', '<=') &&
version_compare(PHP_VERSION, '5.1.0', '>=');
foreach ($funcs as $func) {
if ($buggy && is_array($func)) {
$reflector = new ReflectionMethod($func[0], $func[1]);
if (!$reflector->isStatic()) {
throw new Exception('
HTML Purifier autoloader registrar is not compatible
with non-static object methods due to PHP Bug #44144;
Please do not use HTMLPurifier.autoload.php (or any
file that includes this file); instead, place the code:
spl_autoload_register(array(\'HTMLPurifier_Bootstrap\', \'autoload\'))
after your own autoloaders.
');
}
if ($compat) $func = implode('::', $func);
}
spl_autoload_unregister($func);
}
spl_autoload_register($autoload);
foreach ($funcs as $func) spl_autoload_register($func);
}
}
}
abstract class HTMLPurifier_Definition
{
public $setup = false;
public $optimized = null;
public $type;
abstract protected function doSetup($config);
public function setup($config) {
if ($this->setup) return;
$this->setup = true;
$this->doSetup($config);
}
}
class HTMLPurifier_CSSDefinition extends HTMLPurifier_Definition
{
public $type = 'CSS';
public $info = array();
protected function doSetup($config) {
$this->info['text-align'] = new HTMLPurifier_AttrDef_Enum(
array('left', 'right', 'center', 'justify'), false);
$border_style =
$this->info['border-bottom-style'] =
$this->info['border-right-style'] =
$this->info['border-left-style'] =
$this->info['border-top-style'] =  new HTMLPurifier_AttrDef_Enum(
array('none', 'hidden', 'dotted', 'dashed', 'solid', 'double',
'groove', 'ridge', 'inset', 'outset'), false);
$this->info['border-style'] = new HTMLPurifier_AttrDef_CSS_Multiple($border_style);
$this->info['clear'] = new HTMLPurifier_AttrDef_Enum(
array('none', 'left', 'right', 'both'), false);
$this->info['float'] = new HTMLPurifier_AttrDef_Enum(
array('none', 'left', 'right'), false);
$this->info['font-style'] = new HTMLPurifier_AttrDef_Enum(
array('normal', 'italic', 'oblique'), false);
$this->info['font-variant'] = new HTMLPurifier_AttrDef_Enum(
array('normal', 'small-caps'), false);
$uri_or_none = new HTMLPurifier_AttrDef_CSS_Composite(
array(
new HTMLPurifier_AttrDef_Enum(array('none')),
new HTMLPurifier_AttrDef_CSS_URI()
)
);
$this->info['list-style-position'] = new HTMLPurifier_AttrDef_Enum(
array('inside', 'outside'), false);
$this->info['list-style-type'] = new HTMLPurifier_AttrDef_Enum(
array('disc', 'circle', 'square', 'decimal', 'lower-roman',
'upper-roman', 'lower-alpha', 'upper-alpha', 'none'), false);
$this->info['list-style-image'] = $uri_or_none;
$this->info['list-style'] = new HTMLPurifier_AttrDef_CSS_ListStyle($config);
$this->info['text-transform'] = new HTMLPurifier_AttrDef_Enum(
array('capitalize', 'uppercase', 'lowercase', 'none'), false);
$this->info['color'] = new HTMLPurifier_AttrDef_CSS_Color();
$this->info['background-image'] = $uri_or_none;
$this->info['background-repeat'] = new HTMLPurifier_AttrDef_Enum(
array('repeat', 'repeat-x', 'repeat-y', 'no-repeat')
);
$this->info['background-attachment'] = new HTMLPurifier_AttrDef_Enum(
array('scroll', 'fixed')
);
$this->info['background-position'] = new HTMLPurifier_AttrDef_CSS_BackgroundPosition();
$border_color =
$this->info['border-top-color'] =
$this->info['border-bottom-color'] =
$this->info['border-left-color'] =
$this->info['border-right-color'] =
$this->info['background-color'] = new HTMLPurifier_AttrDef_CSS_Composite(array(
new HTMLPurifier_AttrDef_Enum(array('transparent')),
new HTMLPurifier_AttrDef_CSS_Color()
));
$this->info['background'] = new HTMLPurifier_AttrDef_CSS_Background($config);
$this->info['border-color'] = new HTMLPurifier_AttrDef_CSS_Multiple($border_color);
$border_width =
$this->info['border-top-width'] =
$this->info['border-bottom-width'] =
$this->info['border-left-width'] =
$this->info['border-right-width'] = new HTMLPurifier_AttrDef_CSS_Composite(array(
new HTMLPurifier_AttrDef_Enum(array('thin', 'medium', 'thick')),
new HTMLPurifier_AttrDef_CSS_Length('0') //disallow negative
));
$this->info['border-width'] = new HTMLPurifier_AttrDef_CSS_Multiple($border_width);
$this->info['letter-spacing'] = new HTMLPurifier_AttrDef_CSS_Composite(array(
new HTMLPurifier_AttrDef_Enum(array('normal')),
new HTMLPurifier_AttrDef_CSS_Length()
));
$this->info['word-spacing'] = new HTMLPurifier_AttrDef_CSS_Composite(array(
new HTMLPurifier_AttrDef_Enum(array('normal')),
new HTMLPurifier_AttrDef_CSS_Length()
));
$this->info['font-size'] = new HTMLPurifier_AttrDef_CSS_Composite(array(
new HTMLPurifier_AttrDef_Enum(array('xx-small', 'x-small',
'small', 'medium', 'large', 'x-large', 'xx-large',
'larger', 'smaller')),
new HTMLPurifier_AttrDef_CSS_Percentage(),
new HTMLPurifier_AttrDef_CSS_Length()
));
$this->info['line-height'] = new HTMLPurifier_AttrDef_CSS_Composite(array(
new HTMLPurifier_AttrDef_Enum(array('normal')),
new HTMLPurifier_AttrDef_CSS_Number(true), // no negatives
new HTMLPurifier_AttrDef_CSS_Length('0'),
new HTMLPurifier_AttrDef_CSS_Percentage(true)
));
$margin =
$this->info['margin-top'] =
$this->info['margin-bottom'] =
$this->info['margin-left'] =
$this->info['margin-right'] = new HTMLPurifier_AttrDef_CSS_Composite(array(
new HTMLPurifier_AttrDef_CSS_Length(),
new HTMLPurifier_AttrDef_CSS_Percentage(),
new HTMLPurifier_AttrDef_Enum(array('auto'))
));
$this->info['margin'] = new HTMLPurifier_AttrDef_CSS_Multiple($margin);
$padding =
$this->info['padding-top'] =
$this->info['padding-bottom'] =
$this->info['padding-left'] =
$this->info['padding-right'] = new HTMLPurifier_AttrDef_CSS_Composite(array(
new HTMLPurifier_AttrDef_CSS_Length('0'),
new HTMLPurifier_AttrDef_CSS_Percentage(true)
));
$this->info['padding'] = new HTMLPurifier_AttrDef_CSS_Multiple($padding);
$this->info['text-indent'] = new HTMLPurifier_AttrDef_CSS_Composite(array(
new HTMLPurifier_AttrDef_CSS_Length(),
new HTMLPurifier_AttrDef_CSS_Percentage()
));
$trusted_wh = new HTMLPurifier_AttrDef_CSS_Composite(array(
new HTMLPurifier_AttrDef_CSS_Length('0'),
new HTMLPurifier_AttrDef_CSS_Percentage(true),
new HTMLPurifier_AttrDef_Enum(array('auto'))
));
$max = $config->get('CSS.MaxImgLength');
$this->info['width'] =
$this->info['height'] =
$max === null ?
$trusted_wh :
new HTMLPurifier_AttrDef_Switch('img',
new HTMLPurifier_AttrDef_CSS_Composite(array(
new HTMLPurifier_AttrDef_CSS_Length('0', $max),
new HTMLPurifier_AttrDef_Enum(array('auto'))
)),
$trusted_wh
);
$this->info['text-decoration'] = new HTMLPurifier_AttrDef_CSS_TextDecoration();
$this->info['font-family'] = new HTMLPurifier_AttrDef_CSS_FontFamily();
$this->info['font-weight'] = new HTMLPurifier_AttrDef_Enum(
array('normal', 'bold', 'bolder', 'lighter', '100', '200', '300',
'400', '500', '600', '700', '800', '900'), false);
$this->info['font'] = new HTMLPurifier_AttrDef_CSS_Font($config);
$this->info['border'] =
$this->info['border-bottom'] =
$this->info['border-top'] =
$this->info['border-left'] =
$this->info['border-right'] = new HTMLPurifier_AttrDef_CSS_Border($config);
$this->info['border-collapse'] = new HTMLPurifier_AttrDef_Enum(array(
'collapse', 'separate'));
$this->info['caption-side'] = new HTMLPurifier_AttrDef_Enum(array(
'top', 'bottom'));
$this->info['table-layout'] = new HTMLPurifier_AttrDef_Enum(array(
'auto', 'fixed'));
$this->info['vertical-align'] = new HTMLPurifier_AttrDef_CSS_Composite(array(
new HTMLPurifier_AttrDef_Enum(array('baseline', 'sub', 'super',
'top', 'text-top', 'middle', 'bottom', 'text-bottom')),
new HTMLPurifier_AttrDef_CSS_Length(),
new HTMLPurifier_AttrDef_CSS_Percentage()
));
$this->info['border-spacing'] = new HTMLPurifier_AttrDef_CSS_Multiple(new HTMLPurifier_AttrDef_CSS_Length(), 2);
$this->info['white-space'] = new HTMLPurifier_AttrDef_Enum(array('nowrap'));
if ($config->get('CSS.Proprietary')) {
$this->doSetupProprietary($config);
}
if ($config->get('CSS.AllowTricky')) {
$this->doSetupTricky($config);
}
if ($config->get('CSS.Trusted')) {
$this->doSetupTrusted($config);
}
$allow_important = $config->get('CSS.AllowImportant');
foreach ($this->info as $k=>$v) {
$this->info[$k] = new HTMLPurifier_AttrDef_CSS_ImportantDecorator($v, $allow_important);
}
$this->setupConfigStuff($config);
}
protected function doSetupProprietary($config) {
$this->info['scrollbar-arrow-color']        = new HTMLPurifier_AttrDef_CSS_Color();
$this->info['scrollbar-base-color']         = new HTMLPurifier_AttrDef_CSS_Color();
$this->info['scrollbar-darkshadow-color']   = new HTMLPurifier_AttrDef_CSS_Color();
$this->info['scrollbar-face-color']         = new HTMLPurifier_AttrDef_CSS_Color();
$this->info['scrollbar-highlight-color']    = new HTMLPurifier_AttrDef_CSS_Color();
$this->info['scrollbar-shadow-color']       = new HTMLPurifier_AttrDef_CSS_Color();
$this->info['opacity']          = new HTMLPurifier_AttrDef_CSS_AlphaValue();
$this->info['-moz-opacity']     = new HTMLPurifier_AttrDef_CSS_AlphaValue();
$this->info['-khtml-opacity']   = new HTMLPurifier_AttrDef_CSS_AlphaValue();
$this->info['filter'] = new HTMLPurifier_AttrDef_CSS_Filter();
}
protected function doSetupTricky($config) {
$this->info['display'] = new HTMLPurifier_AttrDef_Enum(array(
'inline', 'block', 'list-item', 'run-in', 'compact',
'marker', 'table', 'inline-table', 'table-row-group',
'table-header-group', 'table-footer-group', 'table-row',
'table-column-group', 'table-column', 'table-cell', 'table-caption', 'none'
));
$this->info['visibility'] = new HTMLPurifier_AttrDef_Enum(array(
'visible', 'hidden', 'collapse'
));
$this->info['overflow'] = new HTMLPurifier_AttrDef_Enum(array('visible', 'hidden', 'auto', 'scroll'));
}
protected function doSetupTrusted($config) {
$this->info['position'] = new HTMLPurifier_AttrDef_Enum(array(
'static', 'relative', 'absolute', 'fixed'
));
$this->info['top'] =
$this->info['left'] =
$this->info['right'] =
$this->info['bottom'] = new HTMLPurifier_AttrDef_CSS_Composite(array(
new HTMLPurifier_AttrDef_CSS_Length(),
new HTMLPurifier_AttrDef_CSS_Percentage(),
new HTMLPurifier_AttrDef_Enum(array('auto')),
));
$this->info['z-index'] = new HTMLPurifier_AttrDef_CSS_Composite(array(
new HTMLPurifier_AttrDef_Integer(),
new HTMLPurifier_AttrDef_Enum(array('auto')),
));
}
protected function setupConfigStuff($config) {
$support = "(for information on implementing this, see the ".
"support forums) ";
$allowed_properties = $config->get('CSS.AllowedProperties');
if ($allowed_properties !== null) {
foreach ($this->info as $name=>$d) {
if(!isset($allowed_properties[$name])) unset($this->info[$name]);
unset($allowed_properties[$name]);
}
foreach ($allowed_properties as $name=>$d) {
$name = htmlspecialchars($name);
trigger_error("Style attribute '$name' is not supported $support", E_USER_WARNING);
}
}
$forbidden_properties = $config->get('CSS.ForbiddenProperties');
if ($forbidden_properties !== null) {
foreach ($this->info as $name=>$d) {
if (isset($forbidden_properties[$name])) {
unset($this->info[$name]);
}
}
}
}
}
abstract class HTMLPurifier_ChildDef
{
public $type;
public $allow_empty;
public $elements = array();
public function getAllowedElements($config) {
return $this->elements;
}
abstract public function validateChildren($tokens_of_children, $config, $context);
}
class HTMLPurifier_Config
{
public $version = '4.4.0';
public $autoFinalize = true;
protected $serials = array();
protected $serial;
protected $parser = null;
public $def;
protected $definitions;
protected $finalized = false;
protected $plist;
private $aliasMode;
public $chatty = true;
private $lock;
public function __construct($definition, $parent = null) {
$parent = $parent ? $parent : $definition->defaultPlist;
$this->plist = new HTMLPurifier_PropertyList($parent);
$this->def = $definition; // keep a copy around for checking
$this->parser = new HTMLPurifier_VarParser_Flexible();
}
public static function create($config, $schema = null) {
if ($config instanceof HTMLPurifier_Config) {
return $config;
}
if (!$schema) {
$ret = HTMLPurifier_Config::createDefault();
} else {
$ret = new HTMLPurifier_Config($schema);
}
if (is_string($config)) $ret->loadIni($config);
elseif (is_array($config)) $ret->loadArray($config);
return $ret;
}
public static function inherit(HTMLPurifier_Config $config) {
return new HTMLPurifier_Config($config->def, $config->plist);
}
public static function createDefault() {
$definition = HTMLPurifier_ConfigSchema::instance();
$config = new HTMLPurifier_Config($definition);
return $config;
}
public function get($key, $a = null) {
if ($a !== null) {
$this->triggerError("Using deprecated API: use \$config->get('$key.$a') instead", E_USER_WARNING);
$key = "$key.$a";
}
if (!$this->finalized) $this->autoFinalize();
if (!isset($this->def->info[$key])) {
$this->triggerError('Cannot retrieve value of undefined directive ' . htmlspecialchars($key),
E_USER_WARNING);
return;
}
if (isset($this->def->info[$key]->isAlias)) {
$d = $this->def->info[$key];
$this->triggerError('Cannot get value from aliased directive, use real name ' . $d->key,
E_USER_ERROR);
return;
}
if ($this->lock) {
list($ns) = explode('.', $key);
if ($ns !== $this->lock) {
$this->triggerError('Cannot get value of namespace ' . $ns . ' when lock for ' . $this->lock . ' is active, this probably indicates a Definition setup method is accessing directives that are not within its namespace', E_USER_ERROR);
return;
}
}
return $this->plist->get($key);
}
public function getBatch($namespace) {
if (!$this->finalized) $this->autoFinalize();
$full = $this->getAll();
if (!isset($full[$namespace])) {
$this->triggerError('Cannot retrieve undefined namespace ' . htmlspecialchars($namespace),
E_USER_WARNING);
return;
}
return $full[$namespace];
}
public function getBatchSerial($namespace) {
if (empty($this->serials[$namespace])) {
$batch = $this->getBatch($namespace);
unset($batch['DefinitionRev']);
$this->serials[$namespace] = md5(serialize($batch));
}
return $this->serials[$namespace];
}
public function getSerial() {
if (empty($this->serial)) {
$this->serial = md5(serialize($this->getAll()));
}
return $this->serial;
}
public function getAll() {
if (!$this->finalized) $this->autoFinalize();
$ret = array();
foreach ($this->plist->squash() as $name=>$value) {
list($ns, $key) = explode('.', $name, 2);
$ret[$ns][$key] = $value;
}
return $ret;
}
public function set($key, $value, $a = null) {
if (strpos($key, '.') === false) {
$namespace = $key;
$directive = $value;
$value = $a;
$key = "$key.$directive";
$this->triggerError("Using deprecated API: use \$config->set('$key', ...) instead", E_USER_NOTICE);
} else {
list($namespace) = explode('.', $key);
}
if ($this->isFinalized('Cannot set directive after finalization')) return;
if (!isset($this->def->info[$key])) {
$this->triggerError('Cannot set undefined directive ' . htmlspecialchars($key) . ' to value',
E_USER_WARNING);
return;
}
$def = $this->def->info[$key];
if (isset($def->isAlias)) {
if ($this->aliasMode) {
$this->triggerError('Double-aliases not allowed, please fix '.
'ConfigSchema bug with' . $key, E_USER_ERROR);
return;
}
$this->aliasMode = true;
$this->set($def->key, $value);
$this->aliasMode = false;
$this->triggerError("$key is an alias, preferred directive name is {$def->key}", E_USER_NOTICE);
return;
}
$rtype = is_int($def) ? $def : $def->type;
if ($rtype < 0) {
$type = -$rtype;
$allow_null = true;
} else {
$type = $rtype;
$allow_null = isset($def->allow_null);
}
try {
$value = $this->parser->parse($value, $type, $allow_null);
} catch (HTMLPurifier_VarParserException $e) {
$this->triggerError('Value for ' . $key . ' is of invalid type, should be ' . HTMLPurifier_VarParser::getTypeName($type), E_USER_WARNING);
return;
}
if (is_string($value) && is_object($def)) {
if (isset($def->aliases[$value])) {
$value = $def->aliases[$value];
}
if (isset($def->allowed) && !isset($def->allowed[$value])) {
$this->triggerError('Value not supported, valid values are: ' .
$this->_listify($def->allowed), E_USER_WARNING);
return;
}
}
$this->plist->set($key, $value);
if ($namespace == 'HTML' || $namespace == 'CSS' || $namespace == 'URI') {
$this->definitions[$namespace] = null;
}
$this->serials[$namespace] = false;
}
private function _listify($lookup) {
$list = array();
foreach ($lookup as $name=>$b) $list[] = $name;
return implode(', ', $list);
}
public function getHTMLDefinition($raw = false, $optimized = false) {
return $this->getDefinition('HTML', $raw, $optimized);
}
public function getCSSDefinition($raw = false, $optimized = false) {
return $this->getDefinition('CSS', $raw, $optimized);
}
public function getURIDefinition($raw = false, $optimized = false) {
return $this->getDefinition('URI', $raw, $optimized);
}
public function getDefinition($type, $raw = false, $optimized = false) {
if ($optimized && !$raw) {
throw new HTMLPurifier_Exception("Cannot set optimized = true when raw = false");
}
if (!$this->finalized) $this->autoFinalize();
$lock = $this->lock;
$this->lock = null;
$factory = HTMLPurifier_DefinitionCacheFactory::instance();
$cache = $factory->create($type, $this);
$this->lock = $lock;
if (!$raw) {
if (!empty($this->definitions[$type])) {
$def = $this->definitions[$type];
if ($def->setup) {
return $def;
} else {
$def->setup($this);
if ($def->optimized) $cache->add($def, $this);
return $def;
}
}
$def = $cache->get($this);
if ($def) {
$this->definitions[$type] = $def;
return $def;
}
$def = $this->initDefinition($type);
$this->lock = $type;
$def->setup($this);
$this->lock = null;
$cache->add($def, $this);
return $def;
} else {
$def = null;
if ($optimized) {
if (is_null($this->get($type . '.DefinitionID'))) {
throw new HTMLPurifier_Exception("Cannot retrieve raw version without specifying %$type.DefinitionID");
}
}
if (!empty($this->definitions[$type])) {
$def = $this->definitions[$type];
if ($def->setup && !$optimized) {
$extra = $this->chatty ? " (try moving this code block earlier in your initialization)" : "";
throw new HTMLPurifier_Exception("Cannot retrieve raw definition after it has already been setup" . $extra);
}
if ($def->optimized === null) {
$extra = $this->chatty ? " (try flushing your cache)" : "";
throw new HTMLPurifier_Exception("Optimization status of definition is unknown" . $extra);
}
if ($def->optimized !== $optimized) {
$msg = $optimized ? "optimized" : "unoptimized";
$extra = $this->chatty ? " (this backtrace is for the first inconsistent call, which was for a $msg raw definition)" : "";
throw new HTMLPurifier_Exception("Inconsistent use of optimized and unoptimized raw definition retrievals" . $extra);
}
}
if ($def) {
if ($def->setup) {
return null;
} else {
return $def;
}
}
if ($optimized) {
$def = $cache->get($this);
if ($def) {
$this->definitions[$type] = $def;
return null;
}
}
if (!$optimized) {
if (!is_null($this->get($type . '.DefinitionID'))) {
if ($this->chatty) {
$this->triggerError("Due to a documentation error in previous version of HTML Purifier, your definitions are not being cached.  If this is OK, you can remove the %$type.DefinitionRev and %$type.DefinitionID declaration.  Otherwise, modify your code to use maybeGetRawDefinition, and test if the returned value is null before making any edits (if it is null, that means that a cached version is available, and no raw operations are necessary).  See <a href='http://htmlpurifier.org/docs/enduser-customize.html#optimized'>Customize</a> for more details", E_USER_WARNING);
} else {
$this->triggerError("Useless DefinitionID declaration", E_USER_WARNING);
}
}
}
$def = $this->initDefinition($type);
$def->optimized = $optimized;
return $def;
}
throw new HTMLPurifier_Exception("The impossible happened!");
}
private function initDefinition($type) {
if ($type == 'HTML') {
$def = new HTMLPurifier_HTMLDefinition();
} elseif ($type == 'CSS') {
$def = new HTMLPurifier_CSSDefinition();
} elseif ($type == 'URI') {
$def = new HTMLPurifier_URIDefinition();
} else {
throw new HTMLPurifier_Exception("Definition of $type type not supported");
}
$this->definitions[$type] = $def;
return $def;
}
public function maybeGetRawDefinition($name) {
return $this->getDefinition($name, true, true);
}
public function maybeGetRawHTMLDefinition() {
return $this->getDefinition('HTML', true, true);
}
public function maybeGetRawCSSDefinition() {
return $this->getDefinition('CSS', true, true);
}
public function maybeGetRawURIDefinition() {
return $this->getDefinition('URI', true, true);
}
public function loadArray($config_array) {
if ($this->isFinalized('Cannot load directives after finalization')) return;
foreach ($config_array as $key=>$value) {
$key = str_replace('_', '.', $key);
if (strpos($key, '.') !== false) {
$this->set($key, $value);
} else {
$namespace = $key;
$namespace_values = $value;
foreach ($namespace_values as $directive=>$value) {
$this->set($namespace .'.'. $directive, $value);
}
}
}
}
public static function getAllowedDirectivesForForm($allowed, $schema = null) {
if (!$schema) {
$schema = HTMLPurifier_ConfigSchema::instance();
}
if ($allowed !== true) {
if (is_string($allowed)) $allowed = array($allowed);
$allowed_ns = array();
$allowed_directives = array();
$blacklisted_directives = array();
foreach ($allowed as $ns_or_directive) {
if (strpos($ns_or_directive, '.') !== false) {
if ($ns_or_directive[0] == '-') {
$blacklisted_directives[substr($ns_or_directive, 1)] = true;
} else {
$allowed_directives[$ns_or_directive] = true;
}
} else {
$allowed_ns[$ns_or_directive] = true;
}
}
}
$ret = array();
foreach ($schema->info as $key=>$def) {
list($ns, $directive) = explode('.', $key, 2);
if ($allowed !== true) {
if (isset($blacklisted_directives["$ns.$directive"])) continue;
if (!isset($allowed_directives["$ns.$directive"]) && !isset($allowed_ns[$ns])) continue;
}
if (isset($def->isAlias)) continue;
if ($directive == 'DefinitionID' || $directive == 'DefinitionRev') continue;
$ret[] = array($ns, $directive);
}
return $ret;
}
public static function loadArrayFromForm($array, $index = false, $allowed = true, $mq_fix = true, $schema = null) {
$ret = HTMLPurifier_Config::prepareArrayFromForm($array, $index, $allowed, $mq_fix, $schema);
$config = HTMLPurifier_Config::create($ret, $schema);
return $config;
}
public function mergeArrayFromForm($array, $index = false, $allowed = true, $mq_fix = true) {
$ret = HTMLPurifier_Config::prepareArrayFromForm($array, $index, $allowed, $mq_fix, $this->def);
$this->loadArray($ret);
}
public static function prepareArrayFromForm($array, $index = false, $allowed = true, $mq_fix = true, $schema = null) {
if ($index !== false) $array = (isset($array[$index]) && is_array($array[$index])) ? $array[$index] : array();
$mq = $mq_fix && function_exists('get_magic_quotes_gpc') && get_magic_quotes_gpc();
$allowed = HTMLPurifier_Config::getAllowedDirectivesForForm($allowed, $schema);
$ret = array();
foreach ($allowed as $key) {
list($ns, $directive) = $key;
$skey = "$ns.$directive";
if (!empty($array["Null_$skey"])) {
$ret[$ns][$directive] = null;
continue;
}
if (!isset($array[$skey])) continue;
$value = $mq ? stripslashes($array[$skey]) : $array[$skey];
$ret[$ns][$directive] = $value;
}
return $ret;
}
public function loadIni($filename) {
if ($this->isFinalized('Cannot load directives after finalization')) return;
$array = parse_ini_file($filename, true);
$this->loadArray($array);
}
public function isFinalized($error = false) {
if ($this->finalized && $error) {
$this->triggerError($error, E_USER_ERROR);
}
return $this->finalized;
}
public function autoFinalize() {
if ($this->autoFinalize) {
$this->finalize();
} else {
$this->plist->squash(true);
}
}
public function finalize() {
$this->finalized = true;
$this->parser = null;
}
protected function triggerError($msg, $no) {
$extra = '';
if ($this->chatty) {
$trace = debug_backtrace();
for ($i = 0, $c = count($trace); $i < $c - 1; $i++) {
if ($trace[$i + 1]['class'] === 'HTMLPurifier_Config') {
continue;
}
$frame = $trace[$i];
$extra = " invoked on line {$frame['line']} in file {$frame['file']}";
break;
}
}
trigger_error($msg . $extra, $no);
}
public function serialize() {
$this->getDefinition('HTML');
$this->getDefinition('CSS');
$this->getDefinition('URI');
return serialize($this);
}
}
class HTMLPurifier_ConfigSchema {
public $defaults = array();
public $defaultPlist;
public $info = array();
static protected $singleton;
public function __construct() {
$this->defaultPlist = new HTMLPurifier_PropertyList();
}
public static function makeFromSerial() {
$contents = file_get_contents(HTMLPURIFIER_PREFIX . '/HTMLPurifier/ConfigSchema/schema.ser');
$r = unserialize($contents);
if (!$r) {
$hash = sha1($contents);
trigger_error("Unserialization of configuration schema failed, sha1 of file was $hash", E_USER_ERROR);
}
return $r;
}
public static function instance($prototype = null) {
if ($prototype !== null) {
HTMLPurifier_ConfigSchema::$singleton = $prototype;
} elseif (HTMLPurifier_ConfigSchema::$singleton === null || $prototype === true) {
HTMLPurifier_ConfigSchema::$singleton = HTMLPurifier_ConfigSchema::makeFromSerial();
}
return HTMLPurifier_ConfigSchema::$singleton;
}
public function add($key, $default, $type, $allow_null) {
$obj = new stdclass();
$obj->type = is_int($type) ? $type : HTMLPurifier_VarParser::$types[$type];
if ($allow_null) $obj->allow_null = true;
$this->info[$key] = $obj;
$this->defaults[$key] = $default;
$this->defaultPlist->set($key, $default);
}
public function addValueAliases($key, $aliases) {
if (!isset($this->info[$key]->aliases)) {
$this->info[$key]->aliases = array();
}
foreach ($aliases as $alias=>$real) {
$this->info[$key]->aliases[$alias] = $real;
}
}
public function addAllowedValues($key, $allowed) {
$this->info[$key]->allowed = $allowed;
}
public function addAlias($key, $new_key) {
$obj = new stdclass;
$obj->key = $new_key;
$obj->isAlias = true;
$this->info[$key] = $obj;
}
public function postProcess() {
foreach ($this->info as $key=>$v) {
if (count((array) $v) == 1) {
$this->info[$key] = $v->type;
} elseif (count((array) $v) == 2 && isset($v->allow_null)) {
$this->info[$key] = -$v->type;
}
}
}
}
class HTMLPurifier_ContentSets
{
public $info = array();
public $lookup = array();
protected $keys = array();
protected $values = array();
public function __construct($modules) {
if (!is_array($modules)) $modules = array($modules);
foreach ($modules as $module_i=>$module) {
foreach ($module->content_sets as $key=>$value) {
$temp = $this->convertToLookup($value);
if (isset($this->lookup[$key])) {
$this->lookup[$key] = array_merge($this->lookup[$key], $temp);
} else {
$this->lookup[$key] = $temp;
}
}
}
$old_lookup = false;
while ($old_lookup !== $this->lookup) {
$old_lookup = $this->lookup;
foreach ($this->lookup as $i=>$set) {
$add = array();
foreach ($set as $element=>$x) {
if (isset($this->lookup[$element])) {
$add += $this->lookup[$element];
unset($this->lookup[$i][$element]);
}
}
$this->lookup[$i] += $add;
}
}
foreach ($this->lookup as $key=>$lookup) {
$this->info[$key] = implode(' | ', array_keys($lookup));
}
$this->keys   = array_keys($this->info);
$this->values = array_values($this->info);
}
public function generateChildDef(&$def, $module) {
if (!empty($def->child)) return; // already done!
$content_model = $def->content_model;
if (is_string($content_model)) {
$def->content_model = preg_replace_callback(
'/\b(' . implode('|', $this->keys) . ')\b/',
array($this, 'generateChildDefCallback'),
$content_model
);
}
$def->child = $this->getChildDef($def, $module);
}
public function generateChildDefCallback($matches) {
return $this->info[$matches[0]];
}
public function getChildDef($def, $module) {
$value = $def->content_model;
if (is_object($value)) {
trigger_error(
'Literal object child definitions should be stored in '.
'ElementDef->child not ElementDef->content_model',
E_USER_NOTICE
);
return $value;
}
switch ($def->content_model_type) {
case 'required':
return new HTMLPurifier_ChildDef_Required($value);
case 'optional':
return new HTMLPurifier_ChildDef_Optional($value);
case 'empty':
return new HTMLPurifier_ChildDef_Empty();
case 'custom':
return new HTMLPurifier_ChildDef_Custom($value);
}
$return = false;
if ($module->defines_child_def) { // save a func call
$return = $module->getChildDef($def);
}
if ($return !== false) return $return;
trigger_error(
'Could not determine which ChildDef class to instantiate',
E_USER_ERROR
);
return false;
}
protected function convertToLookup($string) {
$array = explode('|', str_replace(' ', '', $string));
$ret = array();
foreach ($array as $i=>$k) {
$ret[$k] = true;
}
return $ret;
}
}
class HTMLPurifier_Context
{
private $_storage = array();
public function register($name, &$ref) {
if (isset($this->_storage[$name])) {
trigger_error("Name $name produces collision, cannot re-register",
E_USER_ERROR);
return;
}
$this->_storage[$name] =& $ref;
}
public function &get($name, $ignore_error = false) {
if (!isset($this->_storage[$name])) {
if (!$ignore_error) {
trigger_error("Attempted to retrieve non-existent variable $name",
E_USER_ERROR);
}
$var = null; // so we can return by reference
return $var;
}
return $this->_storage[$name];
}
public function destroy($name) {
if (!isset($this->_storage[$name])) {
trigger_error("Attempted to destroy non-existent variable $name",
E_USER_ERROR);
return;
}
unset($this->_storage[$name]);
}
public function exists($name) {
return isset($this->_storage[$name]);
}
public function loadArray($context_array) {
foreach ($context_array as $key=>$discard) {
$this->register($key, $context_array[$key]);
}
}
}
abstract class HTMLPurifier_DefinitionCache
{
public $type;
public function __construct($type) {
$this->type = $type;
}
public function generateKey($config) {
return $config->version . ',' . // possibly replace with function calls
$config->getBatchSerial($this->type) . ',' .
$config->get($this->type . '.DefinitionRev');
}
public function isOld($key, $config) {
if (substr_count($key, ',') < 2) return true;
list($version, $hash, $revision) = explode(',', $key, 3);
$compare = version_compare($version, $config->version);
if ($compare != 0) return true;
if (
$hash == $config->getBatchSerial($this->type) &&
$revision < $config->get($this->type . '.DefinitionRev')
) return true;
return false;
}
public function checkDefType($def) {
if ($def->type !== $this->type) {
trigger_error("Cannot use definition of type {$def->type} in cache for {$this->type}");
return false;
}
return true;
}
abstract public function add($def, $config);
abstract public function set($def, $config);
abstract public function replace($def, $config);
abstract public function get($config);
abstract public function remove($config);
abstract public function flush($config);
abstract public function cleanup($config);
}
class HTMLPurifier_DefinitionCacheFactory
{
protected $caches = array('Serializer'=>array());
protected $implementations = array();
protected $decorators = array();
public function setup() {
$this->addDecorator('Cleanup');
}
public static function instance($prototype = null) {
static $instance;
if ($prototype !== null) {
$instance = $prototype;
} elseif ($instance === null || $prototype === true) {
$instance = new HTMLPurifier_DefinitionCacheFactory();
$instance->setup();
}
return $instance;
}
public function register($short, $long) {
$this->implementations[$short] = $long;
}
public function create($type, $config) {
$method = $config->get('Cache.DefinitionImpl');
if ($method === null) {
return new HTMLPurifier_DefinitionCache_Null($type);
}
if (!empty($this->caches[$method][$type])) {
return $this->caches[$method][$type];
}
if (
isset($this->implementations[$method]) &&
class_exists($class = $this->implementations[$method], false)
) {
$cache = new $class($type);
} else {
if ($method != 'Serializer') {
trigger_error("Unrecognized DefinitionCache $method, using Serializer instead", E_USER_WARNING);
}
$cache = new HTMLPurifier_DefinitionCache_Serializer($type);
}
foreach ($this->decorators as $decorator) {
$new_cache = $decorator->decorate($cache);
unset($cache);
$cache = $new_cache;
}
$this->caches[$method][$type] = $cache;
return $this->caches[$method][$type];
}
public function addDecorator($decorator) {
if (is_string($decorator)) {
$class = "HTMLPurifier_DefinitionCache_Decorator_$decorator";
$decorator = new $class;
}
$this->decorators[$decorator->name] = $decorator;
}
}
class HTMLPurifier_Doctype
{
public $name;
public $modules = array();
public $tidyModules = array();
public $xml = true;
public $aliases = array();
public $dtdPublic;
public $dtdSystem;
public function __construct($name = null, $xml = true, $modules = array(),
$tidyModules = array(), $aliases = array(), $dtd_public = null, $dtd_system = null
) {
$this->name         = $name;
$this->xml          = $xml;
$this->modules      = $modules;
$this->tidyModules  = $tidyModules;
$this->aliases      = $aliases;
$this->dtdPublic    = $dtd_public;
$this->dtdSystem    = $dtd_system;
}
}
class HTMLPurifier_DoctypeRegistry
{
protected $doctypes;
protected $aliases;
public function register($doctype, $xml = true, $modules = array(),
$tidy_modules = array(), $aliases = array(), $dtd_public = null, $dtd_system = null
) {
if (!is_array($modules)) $modules = array($modules);
if (!is_array($tidy_modules)) $tidy_modules = array($tidy_modules);
if (!is_array($aliases)) $aliases = array($aliases);
if (!is_object($doctype)) {
$doctype = new HTMLPurifier_Doctype(
$doctype, $xml, $modules, $tidy_modules, $aliases, $dtd_public, $dtd_system
);
}
$this->doctypes[$doctype->name] = $doctype;
$name = $doctype->name;
foreach ($doctype->aliases as $alias) {
if (isset($this->doctypes[$alias])) continue;
$this->aliases[$alias] = $name;
}
if (isset($this->aliases[$name])) unset($this->aliases[$name]);
return $doctype;
}
public function get($doctype) {
if (isset($this->aliases[$doctype])) $doctype = $this->aliases[$doctype];
if (!isset($this->doctypes[$doctype])) {
trigger_error('Doctype ' . htmlspecialchars($doctype) . ' does not exist', E_USER_ERROR);
$anon = new HTMLPurifier_Doctype($doctype);
return $anon;
}
return $this->doctypes[$doctype];
}
public function make($config) {
return clone $this->get($this->getDoctypeFromConfig($config));
}
public function getDoctypeFromConfig($config) {
$doctype = $config->get('HTML.Doctype');
if (!empty($doctype)) return $doctype;
$doctype = $config->get('HTML.CustomDoctype');
if (!empty($doctype)) return $doctype;
if ($config->get('HTML.XHTML')) {
$doctype = 'XHTML 1.0';
} else {
$doctype = 'HTML 4.01';
}
if ($config->get('HTML.Strict')) {
$doctype .= ' Strict';
} else {
$doctype .= ' Transitional';
}
return $doctype;
}
}
class HTMLPurifier_ElementDef
{
public $standalone = true;
public $attr = array();
public $attr_transform_pre = array();
public $attr_transform_post = array();
public $child;
public $content_model;
public $content_model_type;
public $descendants_are_inline = false;
public $required_attr = array();
public $excludes = array();
public $autoclose = array();
public $wrap;
public $formatting;
public static function create($content_model, $content_model_type, $attr) {
$def = new HTMLPurifier_ElementDef();
$def->content_model = $content_model;
$def->content_model_type = $content_model_type;
$def->attr = $attr;
return $def;
}
public function mergeIn($def) {
foreach($def->attr as $k=>$v) {
if ($k === 0) {
foreach ($v as $v2) {
$this->attr[0][] = $v2;
}
continue;
}
if ($v === false) {
if (isset($this->attr[$k])) unset($this->attr[$k]);
continue;
}
$this->attr[$k] = $v;
}
$this->_mergeAssocArray($this->attr_transform_pre, $def->attr_transform_pre);
$this->_mergeAssocArray($this->attr_transform_post, $def->attr_transform_post);
$this->_mergeAssocArray($this->excludes, $def->excludes);
if(!empty($def->content_model)) {
$this->content_model =
str_replace("#SUPER", $this->content_model, $def->content_model);
$this->child = false;
}
if(!empty($def->content_model_type)) {
$this->content_model_type = $def->content_model_type;
$this->child = false;
}
if(!is_null($def->child)) $this->child = $def->child;
if(!is_null($def->formatting)) $this->formatting = $def->formatting;
if($def->descendants_are_inline) $this->descendants_are_inline = $def->descendants_are_inline;
}
private function _mergeAssocArray(&$a1, $a2) {
foreach ($a2 as $k=>$v) {
if ($v === false) {
if (isset($a1[$k])) unset($a1[$k]);
continue;
}
$a1[$k] = $v;
}
}
}
class HTMLPurifier_Encoder
{
private function __construct() {
trigger_error('Cannot instantiate encoder, call methods statically', E_USER_ERROR);
}
public static function muteErrorHandler() {}
public static function unsafeIconv($in, $out, $text) {
set_error_handler(array('HTMLPurifier_Encoder', 'muteErrorHandler'));
$r = iconv($in, $out, $text);
restore_error_handler();
return $r;
}
public static function iconv($in, $out, $text, $max_chunk_size = 8000) {
$code = self::testIconvTruncateBug();
if ($code == self::ICONV_OK) {
return self::unsafeIconv($in, $out, $text);
} elseif ($code == self::ICONV_TRUNCATES) {
if ($in == 'utf-8') {
if ($max_chunk_size < 4) {
trigger_error('max_chunk_size is too small', E_USER_WARNING);
return false;
}
if (($c = strlen($text)) <= $max_chunk_size) {
return self::unsafeIconv($in, $out, $text);
}
$r = '';
$i = 0;
while (true) {
if ($i + $max_chunk_size >= $c) {
$r .= self::unsafeIconv($in, $out, substr($text, $i));
break;
}
if (0x80 != (0xC0 & ord($text[$i + $max_chunk_size]))) {
$chunk_size = $max_chunk_size;
} elseif (0x80 != (0xC0 & ord($text[$i + $max_chunk_size - 1]))) {
$chunk_size = $max_chunk_size - 1;
} elseif (0x80 != (0xC0 & ord($text[$i + $max_chunk_size - 2]))) {
$chunk_size = $max_chunk_size - 2;
} elseif (0x80 != (0xC0 & ord($text[$i + $max_chunk_size - 3]))) {
$chunk_size = $max_chunk_size - 3;
} else {
return false; // rather confusing UTF-8...
}
$chunk = substr($text, $i, $chunk_size); // substr doesn't mind overlong lengths
$r .= self::unsafeIconv($in, $out, $chunk);
$i += $chunk_size;
}
return $r;
} else {
return false;
}
} else {
return false;
}
}
public static function cleanUTF8($str, $force_php = false) {
if (preg_match('/^[\x{9}\x{A}\x{D}\x{20}-\x{7E}\x{A0}-\x{D7FF}\x{E000}-\x{FFFD}\x{10000}-\x{10FFFF}]*$/Du', $str)) {
return $str;
}
$mState = 0; // cached expected number of octets after the current octet
$mUcs4  = 0; // cached Unicode character
$mBytes = 1; // cached expected number of octets in the current sequence
$out = '';
$char = '';
$len = strlen($str);
for($i = 0; $i < $len; $i++) {
$in = ord($str{$i});
$char .= $str[$i]; // append byte to char
if (0 == $mState) {
if (0 == (0x80 & ($in))) {
if (($in <= 31 || $in == 127) &&
!($in == 9 || $in == 13 || $in == 10) // save \r\t\n
) {
} else {
$out .= $char;
}
$char = '';
$mBytes = 1;
} elseif (0xC0 == (0xE0 & ($in))) {
$mUcs4 = ($in);
$mUcs4 = ($mUcs4 & 0x1F) << 6;
$mState = 1;
$mBytes = 2;
} elseif (0xE0 == (0xF0 & ($in))) {
$mUcs4 = ($in);
$mUcs4 = ($mUcs4 & 0x0F) << 12;
$mState = 2;
$mBytes = 3;
} elseif (0xF0 == (0xF8 & ($in))) {
$mUcs4 = ($in);
$mUcs4 = ($mUcs4 & 0x07) << 18;
$mState = 3;
$mBytes = 4;
} elseif (0xF8 == (0xFC & ($in))) {
$mUcs4 = ($in);
$mUcs4 = ($mUcs4 & 0x03) << 24;
$mState = 4;
$mBytes = 5;
} elseif (0xFC == (0xFE & ($in))) {
$mUcs4 = ($in);
$mUcs4 = ($mUcs4 & 1) << 30;
$mState = 5;
$mBytes = 6;
} else {
$mState = 0;
$mUcs4  = 0;
$mBytes = 1;
$char = '';
}
} else {
if (0x80 == (0xC0 & ($in))) {
$shift = ($mState - 1) * 6;
$tmp = $in;
$tmp = ($tmp & 0x0000003F) << $shift;
$mUcs4 |= $tmp;
if (0 == --$mState) {
if (((2 == $mBytes) && ($mUcs4 < 0x0080)) ||
((3 == $mBytes) && ($mUcs4 < 0x0800)) ||
((4 == $mBytes) && ($mUcs4 < 0x10000)) ||
(4 < $mBytes) ||
(($mUcs4 & 0xFFFFF800) == 0xD800) ||
($mUcs4 > 0x10FFFF)
) {
} elseif (0xFEFF != $mUcs4 && // omit BOM
(
0x9 == $mUcs4 ||
0xA == $mUcs4 ||
0xD == $mUcs4 ||
(0x20 <= $mUcs4 && 0x7E >= $mUcs4) ||
(0xA0 <= $mUcs4 && 0xD7FF >= $mUcs4) ||
(0x10000 <= $mUcs4 && 0x10FFFF >= $mUcs4)
)
) {
$out .= $char;
}
$mState = 0;
$mUcs4  = 0;
$mBytes = 1;
$char = '';
}
} else {
$mState = 0;
$mUcs4  = 0;
$mBytes = 1;
$char ='';
}
}
}
return $out;
}
public static function unichr($code) {
if($code > 1114111 or $code < 0 or
($code >= 55296 and $code <= 57343) ) {
return '';
}
$x = $y = $z = $w = 0;
if ($code < 128) {
$x = $code;
} else {
$x = ($code & 63) | 128;
if ($code < 2048) {
$y = (($code & 2047) >> 6) | 192;
} else {
$y = (($code & 4032) >> 6) | 128;
if($code < 65536) {
$z = (($code >> 12) & 15) | 224;
} else {
$z = (($code >> 12) & 63) | 128;
$w = (($code >> 18) & 7)  | 240;
}
}
}
$ret = '';
if($w) $ret .= chr($w);
if($z) $ret .= chr($z);
if($y) $ret .= chr($y);
$ret .= chr($x);
return $ret;
}
public static function iconvAvailable() {
static $iconv = null;
if ($iconv === null) {
$iconv = function_exists('iconv') && self::testIconvTruncateBug() != self::ICONV_UNUSABLE;
}
return $iconv;
}
public static function convertToUTF8($str, $config, $context) {
$encoding = $config->get('Core.Encoding');
if ($encoding === 'utf-8') return $str;
static $iconv = null;
if ($iconv === null) $iconv = self::iconvAvailable();
if ($iconv && !$config->get('Test.ForceNoIconv')) {
$str = self::unsafeIconv($encoding, 'utf-8//IGNORE', $str);
if ($str === false) {
trigger_error('Invalid encoding ' . $encoding, E_USER_ERROR);
return '';
}
$str = strtr($str, self::testEncodingSupportsASCII($encoding));
return $str;
} elseif ($encoding === 'iso-8859-1') {
$str = utf8_encode($str);
return $str;
}
trigger_error('Encoding not supported, please install iconv', E_USER_ERROR);
}
public static function convertFromUTF8($str, $config, $context) {
$encoding = $config->get('Core.Encoding');
if ($escape = $config->get('Core.EscapeNonASCIICharacters')) {
$str = self::convertToASCIIDumbLossless($str);
}
if ($encoding === 'utf-8') return $str;
static $iconv = null;
if ($iconv === null) $iconv = self::iconvAvailable();
if ($iconv && !$config->get('Test.ForceNoIconv')) {
$ascii_fix = self::testEncodingSupportsASCII($encoding);
if (!$escape && !empty($ascii_fix)) {
$clear_fix = array();
foreach ($ascii_fix as $utf8=>$native) $clear_fix[$utf8] = '';
$str = strtr($str, $clear_fix);
}
$str = strtr($str, array_flip($ascii_fix));
$str = self::iconv('utf-8', $encoding . '//IGNORE', $str);
return $str;
} elseif ($encoding === 'iso-8859-1') {
$str = utf8_decode($str);
return $str;
}
trigger_error('Encoding not supported', E_USER_ERROR);
}
public static function convertToASCIIDumbLossless($str) {
$bytesleft = 0;
$result = '';
$working = 0;
$len = strlen($str);
for( $i = 0; $i < $len; $i++ ) {
$bytevalue = ord( $str[$i] );
if( $bytevalue <= 0x7F ) { //0xxx xxxx
$result .= chr( $bytevalue );
$bytesleft = 0;
} elseif( $bytevalue <= 0xBF ) { //10xx xxxx
$working = $working << 6;
$working += ($bytevalue & 0x3F);
$bytesleft--;
if( $bytesleft <= 0 ) {
$result .= "&#" . $working . ";";
}
} elseif( $bytevalue <= 0xDF ) { //110x xxxx
$working = $bytevalue & 0x1F;
$bytesleft = 1;
} elseif( $bytevalue <= 0xEF ) { //1110 xxxx
$working = $bytevalue & 0x0F;
$bytesleft = 2;
} else { //1111 0xxx
$working = $bytevalue & 0x07;
$bytesleft = 3;
}
}
return $result;
}
const ICONV_OK = 0;
const ICONV_TRUNCATES = 1;
const ICONV_UNUSABLE = 2;
public static function testIconvTruncateBug() {
static $code = null;
if ($code === null) {
$r = self::unsafeIconv('utf-8', 'ascii//IGNORE', "\xCE\xB1" . str_repeat('a', 9000));
if ($r === false) {
$code = self::ICONV_UNUSABLE;
} elseif (($c = strlen($r)) < 9000) {
$code = self::ICONV_TRUNCATES;
} elseif ($c > 9000) {
trigger_error('Your copy of iconv is extremely buggy. Please notify HTML Purifier maintainers: include your iconv version as per phpversion()', E_USER_ERROR);
} else {
$code = self::ICONV_OK;
}
}
return $code;
}
public static function testEncodingSupportsASCII($encoding, $bypass = false) {
static $encodings = array();
if (!$bypass) {
if (isset($encodings[$encoding])) return $encodings[$encoding];
$lenc = strtolower($encoding);
switch ($lenc) {
case 'shift_jis':
return array("\xC2\xA5"=>'\\', "\xE2\x80\xBE"=>'~');
case 'johab':
return array("\xE2\x82\xA9"=>'\\');
}
if (strpos($lenc, 'iso-8859-') === 0) return array();
}
$ret = array();
if (self::unsafeIconv('UTF-8', $encoding, 'a') === false) return false;
for ($i = 0x20; $i <= 0x7E; $i++) { // all printable ASCII chars
$c = chr($i); // UTF-8 char
$r = self::unsafeIconv('UTF-8', "$encoding//IGNORE", $c); // initial conversion
if (
$r === '' ||
($r === $c && self::unsafeIconv($encoding, 'UTF-8//IGNORE', $r) !== $c)
) {
$ret[self::unsafeIconv($encoding, 'UTF-8//IGNORE', $c)] = $c;
}
}
$encodings[$encoding] = $ret;
return $ret;
}
}
class HTMLPurifier_EntityLookup {
public $table;
public function setup($file = false) {
if (!$file) {
$file = HTMLPURIFIER_PREFIX . '/HTMLPurifier/EntityLookup/entities.ser';
}
$this->table = unserialize(file_get_contents($file));
}
public static function instance($prototype = false) {
static $instance = null;
if ($prototype) {
$instance = $prototype;
} elseif (!$instance) {
$instance = new HTMLPurifier_EntityLookup();
$instance->setup();
}
return $instance;
}
}
// of global data (probably trigger_error) because it's impossible to pass
// $config or $context to the callback functions.
class HTMLPurifier_EntityParser
{
protected $_entity_lookup;
protected $_substituteEntitiesRegex =
'/&(?:[#]x([a-fA-F0-9]+)|[#]0*(\d+)|([A-Za-z_:][A-Za-z0-9.\-_:]*));?/';
//     1. hex             2. dec      3. string (XML style)
protected $_special_dec2str =
array(
34=>'"',
38=>'&',
39=>"'",
60=>'<',
62=>'>'
);
protected $_special_ent2dec =
array(
'quot'=>34,
'amp'=>38,
'lt'=>60,
'gt'=>62
);
public function substituteNonSpecialEntities($string) {
return preg_replace_callback(
$this->_substituteEntitiesRegex,
array($this, 'nonSpecialEntityCallback'),
$string
);
}
protected function nonSpecialEntityCallback($matches) {
$entity = $matches[0];
$is_num = (@$matches[0][1] === '#');
if ($is_num) {
$is_hex = (@$entity[2] === 'x');
$code = $is_hex ? hexdec($matches[1]) : (int) $matches[2];
if (isset($this->_special_dec2str[$code]))  return $entity;
return HTMLPurifier_Encoder::unichr($code);
} else {
if (isset($this->_special_ent2dec[$matches[3]])) return $entity;
if (!$this->_entity_lookup) {
$this->_entity_lookup = HTMLPurifier_EntityLookup::instance();
}
if (isset($this->_entity_lookup->table[$matches[3]])) {
return $this->_entity_lookup->table[$matches[3]];
} else {
return $entity;
}
}
}
public function substituteSpecialEntities($string) {
return preg_replace_callback(
$this->_substituteEntitiesRegex,
array($this, 'specialEntityCallback'),
$string);
}
protected function specialEntityCallback($matches) {
$entity = $matches[0];
$is_num = (@$matches[0][1] === '#');
if ($is_num) {
$is_hex = (@$entity[2] === 'x');
$int = $is_hex ? hexdec($matches[1]) : (int) $matches[2];
return isset($this->_special_dec2str[$int]) ?
$this->_special_dec2str[$int] :
$entity;
} else {
return isset($this->_special_ent2dec[$matches[3]]) ?
$this->_special_ent2dec[$matches[3]] :
$entity;
}
}
}
class HTMLPurifier_ErrorCollector
{
const LINENO   = 0;
const SEVERITY = 1;
const MESSAGE  = 2;
const CHILDREN = 3;
protected $errors;
protected $_current;
protected $_stacks = array(array());
protected $locale;
protected $generator;
protected $context;
protected $lines = array();
public function __construct($context) {
$this->locale    =& $context->get('Locale');
$this->context   = $context;
$this->_current  =& $this->_stacks[0];
$this->errors    =& $this->_stacks[0];
}
public function send($severity, $msg) {
$args = array();
if (func_num_args() > 2) {
$args = func_get_args();
array_shift($args);
unset($args[0]);
}
$token = $this->context->get('CurrentToken', true);
$line  = $token ? $token->line : $this->context->get('CurrentLine', true);
$col   = $token ? $token->col  : $this->context->get('CurrentCol',  true);
$attr  = $this->context->get('CurrentAttr', true);
$subst = array();
if (!is_null($token)) {
$args['CurrentToken'] = $token;
}
if (!is_null($attr)) {
$subst['$CurrentAttr.Name'] = $attr;
if (isset($token->attr[$attr])) $subst['$CurrentAttr.Value'] = $token->attr[$attr];
}
if (empty($args)) {
$msg = $this->locale->getMessage($msg);
} else {
$msg = $this->locale->formatMessage($msg, $args);
}
if (!empty($subst)) $msg = strtr($msg, $subst);
$error = array(
self::LINENO=>$line,
self::SEVERITY=>$severity,
self::MESSAGE=>$msg,
self::CHILDREN=>array()
);
$this->_current[] = $error;
$struct = null;
$new_struct = new HTMLPurifier_ErrorStruct();
$new_struct->type = HTMLPurifier_ErrorStruct::TOKEN;
if ($token) $new_struct->value = clone $token;
if (is_int($line) && is_int($col)) {
if (isset($this->lines[$line][$col])) {
$struct = $this->lines[$line][$col];
} else {
$struct = $this->lines[$line][$col] = $new_struct;
}
ksort($this->lines[$line], SORT_NUMERIC);
} else {
if (isset($this->lines[-1])) {
$struct = $this->lines[-1];
} else {
$struct = $this->lines[-1] = $new_struct;
}
}
ksort($this->lines, SORT_NUMERIC);
if (!empty($attr)) {
$struct = $struct->getChild(HTMLPurifier_ErrorStruct::ATTR, $attr);
if (!$struct->value) {
$struct->value = array($attr, 'PUT VALUE HERE');
}
}
if (!empty($cssprop)) {
$struct = $struct->getChild(HTMLPurifier_ErrorStruct::CSSPROP, $cssprop);
if (!$struct->value) {
$struct->value = array($cssprop, 'PUT VALUE HERE');
}
}
$struct->addError($severity, $msg);
}
public function getRaw() {
return $this->errors;
}
public function getHTMLFormatted($config, $errors = null) {
$ret = array();
$this->generator = new HTMLPurifier_Generator($config, $this->context);
if ($errors === null) $errors = $this->errors;
foreach ($this->lines as $line=>$col_array) {
if ($line == -1) continue;
foreach ($col_array as $col=>$struct) {
$this->_renderStruct($ret, $struct, $line, $col);
}
}
if (isset($this->lines[-1])) {
$this->_renderStruct($ret, $this->lines[-1]);
}
if (empty($errors)) {
return '<p>' . $this->locale->getMessage('ErrorCollector: No errors') . '</p>';
} else {
return '<ul><li>' . implode('</li><li>', $ret) . '</li></ul>';
}
}
private function _renderStruct(&$ret, $struct, $line = null, $col = null) {
$stack = array($struct);
$context_stack = array(array());
while ($current = array_pop($stack)) {
$context = array_pop($context_stack);
foreach ($current->errors as $error) {
list($severity, $msg) = $error;
$string = '';
$string .= '<div>';
$error = $this->locale->getErrorName($severity);
$string .= "<span class=\"error e$severity\"><strong>$error</strong></span> ";
if (!is_null($line) && !is_null($col)) {
$string .= "<em class=\"location\">Line $line, Column $col: </em> ";
} else {
$string .= '<em class="location">End of Document: </em> ';
}
$string .= '<strong class="description">' . $this->generator->escape($msg) . '</strong> ';
$string .= '</div>';
$ret[] = $string;
}
foreach ($current->children as $type=>$array) {
$context[] = $current;
$stack = array_merge($stack, array_reverse($array, true));
for ($i = count($array); $i > 0; $i--) {
$context_stack[] = $context;
}
}
}
}
}
class HTMLPurifier_ErrorStruct
{
const TOKEN     = 0;
const ATTR      = 1;
const CSSPROP   = 2;
public $type;
public $value;
public $errors = array();
public $children = array();
public function getChild($type, $id) {
if (!isset($this->children[$type][$id])) {
$this->children[$type][$id] = new HTMLPurifier_ErrorStruct();
$this->children[$type][$id]->type = $type;
}
return $this->children[$type][$id];
}
public function addError($severity, $message) {
$this->errors[] = array($severity, $message);
}
}
class HTMLPurifier_Exception extends Exception
{
}
class HTMLPurifier_Filter
{
public $name;
public function preFilter($html, $config, $context) {
return $html;
}
public function postFilter($html, $config, $context) {
return $html;
}
}
class HTMLPurifier_Generator
{
private $_xhtml = true;
private $_scriptFix = false;
private $_def;
private $_sortAttr;
private $_flashCompat;
private $_innerHTMLFix;
private $_flashStack = array();
protected $config;
public function __construct($config, $context) {
$this->config = $config;
$this->_scriptFix = $config->get('Output.CommentScriptContents');
$this->_innerHTMLFix = $config->get('Output.FixInnerHTML');
$this->_sortAttr = $config->get('Output.SortAttr');
$this->_flashCompat = $config->get('Output.FlashCompat');
$this->_def = $config->getHTMLDefinition();
$this->_xhtml = $this->_def->doctype->xml;
}
public function generateFromTokens($tokens) {
if (!$tokens) return '';
$html = '';
for ($i = 0, $size = count($tokens); $i < $size; $i++) {
if ($this->_scriptFix && $tokens[$i]->name === 'script'
&& $i + 2 < $size && $tokens[$i+2] instanceof HTMLPurifier_Token_End) {
$html .= $this->generateFromToken($tokens[$i++]);
$html .= $this->generateScriptFromToken($tokens[$i++]);
}
$html .= $this->generateFromToken($tokens[$i]);
}
if (extension_loaded('tidy') && $this->config->get('Output.TidyFormat')) {
$tidy = new Tidy;
$tidy->parseString($html, array(
'indent'=>true,
'output-xhtml'=>$this->_xhtml,
'show-body-only'=>true,
'indent-spaces'=>2,
'wrap'=>68,
), 'utf8');
$tidy->cleanRepair();
$html = (string) $tidy; // explicit cast necessary
}
if ($this->config->get('Core.NormalizeNewlines')) {
$nl = $this->config->get('Output.Newline');
if ($nl === null) $nl = PHP_EOL;
if ($nl !== "\n") $html = str_replace("\n", $nl, $html);
}
return $html;
}
public function generateFromToken($token) {
if (!$token instanceof HTMLPurifier_Token) {
trigger_error('Cannot generate HTML from non-HTMLPurifier_Token object', E_USER_WARNING);
return '';
} elseif ($token instanceof HTMLPurifier_Token_Start) {
$attr = $this->generateAttributes($token->attr, $token->name);
if ($this->_flashCompat) {
if ($token->name == "object") {
$flash = new stdclass();
$flash->attr = $token->attr;
$flash->param = array();
$this->_flashStack[] = $flash;
}
}
return '<' . $token->name . ($attr ? ' ' : '') . $attr . '>';
} elseif ($token instanceof HTMLPurifier_Token_End) {
$_extra = '';
if ($this->_flashCompat) {
if ($token->name == "object" && !empty($this->_flashStack)) {
}
}
return $_extra . '</' . $token->name . '>';
} elseif ($token instanceof HTMLPurifier_Token_Empty) {
if ($this->_flashCompat && $token->name == "param" && !empty($this->_flashStack)) {
$this->_flashStack[count($this->_flashStack)-1]->param[$token->attr['name']] = $token->attr['value'];
}
$attr = $this->generateAttributes($token->attr, $token->name);
return '<' . $token->name . ($attr ? ' ' : '') . $attr .
( $this->_xhtml ? ' /': '' ) // <br /> v. <br>
. '>';
} elseif ($token instanceof HTMLPurifier_Token_Text) {
return $this->escape($token->data, ENT_NOQUOTES);
} elseif ($token instanceof HTMLPurifier_Token_Comment) {
return '<!--' . $token->data . '-->';
} else {
return '';
}
}
public function generateScriptFromToken($token) {
if (!$token instanceof HTMLPurifier_Token_Text) return $this->generateFromToken($token);
$data = preg_replace('#//\s*$#', '', $token->data);
return '<!--//--><![CDATA[//><!--' . "\n" . trim($data) . "\n" . '//--><!]]>';
}
public function generateAttributes($assoc_array_of_attributes, $element = false) {
$html = '';
if ($this->_sortAttr) ksort($assoc_array_of_attributes);
foreach ($assoc_array_of_attributes as $key=>$value) {
if (!$this->_xhtml) {
if (strpos($key, ':') !== false) continue;
if ($element && !empty($this->_def->info[$element]->attr[$key]->minimized)) {
$html .= $key . ' ';
continue;
}
}
if ($this->_innerHTMLFix) {
if (strpos($value, '`') !== false) {
if (strcspn($value, '"\' <>') === strlen($value)) {
$value .= ' ';
}
}
}
$html .= $key.'="'.$this->escape($value).'" ';
}
return rtrim($html);
}
public function escape($string, $quote = null) {
if ($quote === null) $quote = ENT_COMPAT;
return htmlspecialchars($string, $quote, 'UTF-8');
}
}
class HTMLPurifier_HTMLDefinition extends HTMLPurifier_Definition
{
public $info = array();
public $info_global_attr = array();
public $info_parent = 'div';
public $info_parent_def;
public $info_block_wrapper = 'p';
public $info_tag_transform = array();
public $info_attr_transform_pre = array();
public $info_attr_transform_post = array();
public $info_content_sets = array();
public $info_injector = array();
public $doctype;
public function addAttribute($element_name, $attr_name, $def) {
$module = $this->getAnonymousModule();
if (!isset($module->info[$element_name])) {
$element = $module->addBlankElement($element_name);
} else {
$element = $module->info[$element_name];
}
$element->attr[$attr_name] = $def;
}
public function addElement($element_name, $type, $contents, $attr_collections, $attributes = array()) {
$module = $this->getAnonymousModule();
$element = $module->addElement($element_name, $type, $contents, $attr_collections, $attributes);
return $element;
}
public function addBlankElement($element_name) {
$module  = $this->getAnonymousModule();
$element = $module->addBlankElement($element_name);
return $element;
}
public function getAnonymousModule() {
if (!$this->_anonModule) {
$this->_anonModule = new HTMLPurifier_HTMLModule();
$this->_anonModule->name = 'Anonymous';
}
return $this->_anonModule;
}
private $_anonModule = null;
public $type = 'HTML';
public $manager; 
public function __construct() {
$this->manager = new HTMLPurifier_HTMLModuleManager();
}
protected function doSetup($config) {
$this->processModules($config);
$this->setupConfigStuff($config);
unset($this->manager);
foreach ($this->info as $k=>$v) {
unset($this->info[$k]->content_model);
unset($this->info[$k]->content_model_type);
}
}
protected function processModules($config) {
if ($this->_anonModule) {
$this->manager->addModule($this->_anonModule);
unset($this->_anonModule);
}
$this->manager->setup($config);
$this->doctype = $this->manager->doctype;
foreach ($this->manager->modules as $module) {
foreach($module->info_tag_transform as $k=>$v) {
if ($v === false) unset($this->info_tag_transform[$k]);
else $this->info_tag_transform[$k] = $v;
}
foreach($module->info_attr_transform_pre as $k=>$v) {
if ($v === false) unset($this->info_attr_transform_pre[$k]);
else $this->info_attr_transform_pre[$k] = $v;
}
foreach($module->info_attr_transform_post as $k=>$v) {
if ($v === false) unset($this->info_attr_transform_post[$k]);
else $this->info_attr_transform_post[$k] = $v;
}
foreach ($module->info_injector as $k=>$v) {
if ($v === false) unset($this->info_injector[$k]);
else $this->info_injector[$k] = $v;
}
}
$this->info = $this->manager->getElements();
$this->info_content_sets = $this->manager->contentSets->lookup;
}
protected function setupConfigStuff($config) {
$block_wrapper = $config->get('HTML.BlockWrapper');
if (isset($this->info_content_sets['Block'][$block_wrapper])) {
$this->info_block_wrapper = $block_wrapper;
} else {
trigger_error('Cannot use non-block element as block wrapper',
E_USER_ERROR);
}
$parent = $config->get('HTML.Parent');
$def = $this->manager->getElement($parent, true);
if ($def) {
$this->info_parent = $parent;
$this->info_parent_def = $def;
} else {
trigger_error('Cannot use unrecognized element as parent',
E_USER_ERROR);
$this->info_parent_def = $this->manager->getElement($this->info_parent, true);
}
$support = "(for information on implementing this, see the ".
"support forums) ";
$allowed_elements = $config->get('HTML.AllowedElements');
$allowed_attributes = $config->get('HTML.AllowedAttributes'); // retrieve early
if (!is_array($allowed_elements) && !is_array($allowed_attributes)) {
$allowed = $config->get('HTML.Allowed');
if (is_string($allowed)) {
list($allowed_elements, $allowed_attributes) = $this->parseTinyMCEAllowedList($allowed);
}
}
if (is_array($allowed_elements)) {
foreach ($this->info as $name=>$d) {
if(!isset($allowed_elements[$name])) unset($this->info[$name]);
unset($allowed_elements[$name]);
}
foreach ($allowed_elements as $element=>$d) {
$element = htmlspecialchars($element); // PHP doesn't escape errors, be careful!
trigger_error("Element '$element' is not supported $support", E_USER_WARNING);
}
}
$allowed_attributes_mutable = $allowed_attributes; // by copy!
if (is_array($allowed_attributes)) {
foreach ($this->info_global_attr as $attr=>$x) {
$keys = array($attr, "*@$attr", "*.$attr");
$delete = true;
foreach ($keys as $key) {
if ($delete && isset($allowed_attributes[$key])) {
$delete = false;
}
if (isset($allowed_attributes_mutable[$key])) {
unset($allowed_attributes_mutable[$key]);
}
}
if ($delete) unset($this->info_global_attr[$attr]);
}
foreach ($this->info as $tag=>$info) {
foreach ($info->attr as $attr=>$x) {
$keys = array("$tag@$attr", $attr, "*@$attr", "$tag.$attr", "*.$attr");
$delete = true;
foreach ($keys as $key) {
if ($delete && isset($allowed_attributes[$key])) {
$delete = false;
}
if (isset($allowed_attributes_mutable[$key])) {
unset($allowed_attributes_mutable[$key]);
}
}
if ($delete) {
if ($this->info[$tag]->attr[$attr]->required) {
trigger_error("Required attribute '$attr' in element '$tag' was not allowed, which means '$tag' will not be allowed either", E_USER_WARNING);
}
unset($this->info[$tag]->attr[$attr]);
}
}
}
foreach ($allowed_attributes_mutable as $elattr=>$d) {
$bits = preg_split('/[.@]/', $elattr, 2);
$c = count($bits);
switch ($c) {
case 2:
if ($bits[0] !== '*') {
$element = htmlspecialchars($bits[0]);
$attribute = htmlspecialchars($bits[1]);
if (!isset($this->info[$element])) {
trigger_error("Cannot allow attribute '$attribute' if element '$element' is not allowed/supported $support");
} else {
trigger_error("Attribute '$attribute' in element '$element' not supported $support",
E_USER_WARNING);
}
break;
}
case 1:
$attribute = htmlspecialchars($bits[0]);
trigger_error("Global attribute '$attribute' is not ".
"supported in any elements $support",
E_USER_WARNING);
break;
}
}
}
$forbidden_elements   = $config->get('HTML.ForbiddenElements');
$forbidden_attributes = $config->get('HTML.ForbiddenAttributes');
foreach ($this->info as $tag=>$info) {
if (isset($forbidden_elements[$tag])) {
unset($this->info[$tag]);
continue;
}
foreach ($info->attr as $attr=>$x) {
if (
isset($forbidden_attributes["$tag@$attr"]) ||
isset($forbidden_attributes["*@$attr"]) ||
isset($forbidden_attributes[$attr])
) {
unset($this->info[$tag]->attr[$attr]);
continue;
} // this segment might get removed eventually
elseif (isset($forbidden_attributes["$tag.$attr"])) {
trigger_error("Error with $tag.$attr: tag.attr syntax not supported for HTML.ForbiddenAttributes; use tag@attr instead", E_USER_WARNING);
}
}
}
foreach ($forbidden_attributes as $key=>$v) {
if (strlen($key) < 2) continue;
if ($key[0] != '*') continue;
if ($key[1] == '.') {
trigger_error("Error with $key: *.attr syntax not supported for HTML.ForbiddenAttributes; use attr instead", E_USER_WARNING);
}
}
foreach ($this->info_injector as $i=>$injector) {
if ($injector->checkNeeded($config) !== false) {
unset($this->info_injector[$i]);
}
}
}
public function parseTinyMCEAllowedList($list) {
$list = str_replace(array(' ', "\t"), '', $list);
$elements = array();
$attributes = array();
$chunks = preg_split('/(,|[\n\r]+)/', $list);
foreach ($chunks as $chunk) {
if (empty($chunk)) continue;
if (!strpos($chunk, '[')) {
$element = $chunk;
$attr = false;
} else {
list($element, $attr) = explode('[', $chunk);
}
if ($element !== '*') $elements[$element] = true;
if (!$attr) continue;
$attr = substr($attr, 0, strlen($attr) - 1); // remove trailing ]
$attr = explode('|', $attr);
foreach ($attr as $key) {
$attributes["$element.$key"] = true;
}
}
return array($elements, $attributes);
}
}
class HTMLPurifier_HTMLModule
{
public $name;
public $elements = array();
public $info = array();
public $content_sets = array();
public $attr_collections = array();
public $info_tag_transform = array();
public $info_attr_transform_pre = array();
public $info_attr_transform_post = array();
public $info_injector = array();
public $defines_child_def = false;
public $safe = true;
public function getChildDef($def) {return false;}
public function addElement($element, $type, $contents, $attr_includes = array(), $attr = array()) {
$this->elements[] = $element;
list($content_model_type, $content_model) = $this->parseContents($contents);
$this->mergeInAttrIncludes($attr, $attr_includes);
if ($type) $this->addElementToContentSet($element, $type);
$this->info[$element] = HTMLPurifier_ElementDef::create(
$content_model, $content_model_type, $attr
);
if (!is_string($contents)) $this->info[$element]->child = $contents;
return $this->info[$element];
}
public function addBlankElement($element) {
if (!isset($this->info[$element])) {
$this->elements[] = $element;
$this->info[$element] = new HTMLPurifier_ElementDef();
$this->info[$element]->standalone = false;
} else {
trigger_error("Definition for $element already exists in module, cannot redefine");
}
return $this->info[$element];
}
public function addElementToContentSet($element, $type) {
if (!isset($this->content_sets[$type])) $this->content_sets[$type] = '';
else $this->content_sets[$type] .= ' | ';
$this->content_sets[$type] .= $element;
}
public function parseContents($contents) {
if (!is_string($contents)) return array(null, null); // defer
switch ($contents) {
case 'Empty':
return array('empty', '');
case 'Inline':
return array('optional', 'Inline | #PCDATA');
case 'Flow':
return array('optional', 'Flow | #PCDATA');
}
list($content_model_type, $content_model) = explode(':', $contents);
$content_model_type = strtolower(trim($content_model_type));
$content_model = trim($content_model);
return array($content_model_type, $content_model);
}
public function mergeInAttrIncludes(&$attr, $attr_includes) {
if (!is_array($attr_includes)) {
if (empty($attr_includes)) $attr_includes = array();
else $attr_includes = array($attr_includes);
}
$attr[0] = $attr_includes;
}
public function makeLookup($list) {
if (is_string($list)) $list = func_get_args();
$ret = array();
foreach ($list as $value) {
if (is_null($value)) continue;
$ret[$value] = true;
}
return $ret;
}
public function setup($config) {}
}
class HTMLPurifier_HTMLModuleManager
{
public $doctypes;
public $doctype;
public $attrTypes;
public $modules = array();
public $registeredModules = array();
public $userModules = array();
public $elementLookup = array();
public $prefixes = array('HTMLPurifier_HTMLModule_');
public $contentSets;     
public $attrCollections; 
public $trusted = false;
public function __construct() {
$this->attrTypes = new HTMLPurifier_AttrTypes();
$this->doctypes  = new HTMLPurifier_DoctypeRegistry();
$common = array(
'CommonAttributes', 'Text', 'Hypertext', 'List',
'Presentation', 'Edit', 'Bdo', 'Tables', 'Image',
'StyleAttribute',
'Scripting', 'Object', 'Forms',
'Name',
);
$transitional = array('Legacy', 'Target', 'Iframe');
$xml = array('XMLCommonAttributes');
$non_xml = array('NonXMLCommonAttributes');
$this->doctypes->register(
'HTML 4.01 Transitional', false,
array_merge($common, $transitional, $non_xml),
array('Tidy_Transitional', 'Tidy_Proprietary'),
array(),
'-//W3C//DTD HTML 4.01 Transitional//EN',
'http://www.w3.org/TR/html4/loose.dtd'
);
$this->doctypes->register(
'HTML 4.01 Strict', false,
array_merge($common, $non_xml),
array('Tidy_Strict', 'Tidy_Proprietary', 'Tidy_Name'),
array(),
'-//W3C//DTD HTML 4.01//EN',
'http://www.w3.org/TR/html4/strict.dtd'
);
$this->doctypes->register(
'XHTML 1.0 Transitional', true,
array_merge($common, $transitional, $xml, $non_xml),
array('Tidy_Transitional', 'Tidy_XHTML', 'Tidy_Proprietary', 'Tidy_Name'),
array(),
'-//W3C//DTD XHTML 1.0 Transitional//EN',
'http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd'
);
$this->doctypes->register(
'XHTML 1.0 Strict', true,
array_merge($common, $xml, $non_xml),
array('Tidy_Strict', 'Tidy_XHTML', 'Tidy_Strict', 'Tidy_Proprietary', 'Tidy_Name'),
array(),
'-//W3C//DTD XHTML 1.0 Strict//EN',
'http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd'
);
$this->doctypes->register(
'XHTML 1.1', true,
array_merge($common, $xml, array('Ruby', 'Iframe')),
array('Tidy_Strict', 'Tidy_XHTML', 'Tidy_Proprietary', 'Tidy_Strict', 'Tidy_Name'), // Tidy_XHTML1_1
array(),
'-//W3C//DTD XHTML 1.1//EN',
'http://www.w3.org/TR/xhtml11/DTD/xhtml11.dtd'
);
}
public function registerModule($module, $overload = false) {
if (is_string($module)) {
$original_module = $module;
$ok = false;
foreach ($this->prefixes as $prefix) {
$module = $prefix . $original_module;
if (class_exists($module)) {
$ok = true;
break;
}
}
if (!$ok) {
$module = $original_module;
if (!class_exists($module)) {
trigger_error($original_module . ' module does not exist',
E_USER_ERROR);
return;
}
}
$module = new $module();
}
if (empty($module->name)) {
trigger_error('Module instance of ' . get_class($module) . ' must have name');
return;
}
if (!$overload && isset($this->registeredModules[$module->name])) {
trigger_error('Overloading ' . $module->name . ' without explicit overload parameter', E_USER_WARNING);
}
$this->registeredModules[$module->name] = $module;
}
public function addModule($module) {
$this->registerModule($module);
if (is_object($module)) $module = $module->name;
$this->userModules[] = $module;
}
public function addPrefix($prefix) {
$this->prefixes[] = $prefix;
}
public function setup($config) {
$this->trusted = $config->get('HTML.Trusted');
$this->doctype = $this->doctypes->make($config);
$modules = $this->doctype->modules;
$lookup = $config->get('HTML.AllowedModules');
$special_cases = $config->get('HTML.CoreModules');
if (is_array($lookup)) {
foreach ($modules as $k=>$m) {
if (isset($special_cases[$m])) continue;
if (!isset($lookup[$m])) unset($modules[$k]);
}
}
if ($config->get('HTML.Proprietary')) {
$modules[] = 'Proprietary';
}
if ($config->get('HTML.SafeObject')) {
$modules[] = 'SafeObject';
}
if ($config->get('HTML.SafeEmbed')) {
$modules[] = 'SafeEmbed';
}
if ($config->get('HTML.Nofollow')) {
$modules[] = 'Nofollow';
}
if ($config->get('HTML.TargetBlank')) {
$modules[] = 'TargetBlank';
}
$modules = array_merge($modules, $this->userModules);
foreach ($modules as $module) {
$this->processModule($module);
$this->modules[$module]->setup($config);
}
foreach ($this->doctype->tidyModules as $module) {
$this->processModule($module);
$this->modules[$module]->setup($config);
}
foreach ($this->modules as $module) {
$n = array();
foreach ($module->info_injector as $i=>$injector) {
if (!is_object($injector)) {
$class = "HTMLPurifier_Injector_$injector";
$injector = new $class;
}
$n[$injector->name] = $injector;
}
$module->info_injector = $n;
}
foreach ($this->modules as $module) {
foreach ($module->info as $name=>$def) {
if (!isset($this->elementLookup[$name])) {
$this->elementLookup[$name] = array();
}
$this->elementLookup[$name][] = $module->name;
}
}
$this->contentSets = new HTMLPurifier_ContentSets(
$this->modules
);
$this->attrCollections = new HTMLPurifier_AttrCollections(
$this->attrTypes,
$this->modules
);
}
public function processModule($module) {
if (!isset($this->registeredModules[$module]) || is_object($module)) {
$this->registerModule($module);
}
$this->modules[$module] = $this->registeredModules[$module];
}
public function getElements() {
$elements = array();
foreach ($this->modules as $module) {
if (!$this->trusted && !$module->safe) continue;
foreach ($module->info as $name=>$v) {
if (isset($elements[$name])) continue;
$elements[$name] = $this->getElement($name);
}
}
foreach ($elements as $n=>$v) {
if ($v === false) unset($elements[$n]);
}
return $elements;
}
public function getElement($name, $trusted = null) {
if (!isset($this->elementLookup[$name])) {
return false;
}
$def = false;
if ($trusted === null) $trusted = $this->trusted;
foreach($this->elementLookup[$name] as $module_name) {
$module = $this->modules[$module_name];
if (!$trusted && !$module->safe) {
continue;
}
$new_def = clone $module->info[$name];
if (!$def && $new_def->standalone) {
$def = $new_def;
} elseif ($def) {
$def->mergeIn($new_def);
} else {
continue;
}
$this->attrCollections->performInclusions($def->attr);
$this->attrCollections->expandIdentifiers($def->attr, $this->attrTypes);
if (is_string($def->content_model) &&
strpos($def->content_model, 'Inline') !== false) {
if ($name != 'del' && $name != 'ins') {
$def->descendants_are_inline = true;
}
}
$this->contentSets->generateChildDef($def, $module);
}
if (!$def) return false;
foreach ($def->attr as $attr_name=>$attr_def) {
if ($attr_def->required) {
$def->required_attr[] = $attr_name;
}
}
return $def;
}
}
class HTMLPurifier_IDAccumulator
{
public $ids = array();
public static function build($config, $context) {
$id_accumulator = new HTMLPurifier_IDAccumulator();
$id_accumulator->load($config->get('Attr.IDBlacklist'));
return $id_accumulator;
}
public function add($id) {
if (isset($this->ids[$id])) return false;
return $this->ids[$id] = true;
}
public function load($array_of_ids) {
foreach ($array_of_ids as $id) {
$this->ids[$id] = true;
}
}
}
abstract class HTMLPurifier_Injector
{
public $name;
protected $htmlDefinition;
protected $currentNesting;
protected $inputTokens;
protected $inputIndex;
public $needed = array();
protected $rewind = false;
public function rewind($index) {
$this->rewind = $index;
}
public function getRewind() {
$r = $this->rewind;
$this->rewind = false;
return $r;
}
public function prepare($config, $context) {
$this->htmlDefinition = $config->getHTMLDefinition();
$result = $this->checkNeeded($config);
if ($result !== false) return $result;
$this->currentNesting =& $context->get('CurrentNesting');
$this->inputTokens    =& $context->get('InputTokens');
$this->inputIndex     =& $context->get('InputIndex');
return false;
}
public function checkNeeded($config) {
$def = $config->getHTMLDefinition();
foreach ($this->needed as $element=>$attributes) {
if (is_int($element)) $element = $attributes;
if (!isset($def->info[$element])) return $element;
if (!is_array($attributes)) continue;
foreach ($attributes as $name) {
if (!isset($def->info[$element]->attr[$name])) return "$element.$name";
}
}
return false;
}
public function allowsElement($name) {
if (!empty($this->currentNesting)) {
$parent_token = array_pop($this->currentNesting);
$this->currentNesting[] = $parent_token;
$parent = $this->htmlDefinition->info[$parent_token->name];
} else {
$parent = $this->htmlDefinition->info_parent_def;
}
if (!isset($parent->child->elements[$name]) || isset($parent->excludes[$name])) {
return false;
}
for ($i = count($this->currentNesting) - 2; $i >= 0; $i--) {
$node = $this->currentNesting[$i];
$def  = $this->htmlDefinition->info[$node->name];
if (isset($def->excludes[$name])) return false;
}
return true;
}
protected function forward(&$i, &$current) {
if ($i === null) $i = $this->inputIndex + 1;
else $i++;
if (!isset($this->inputTokens[$i])) return false;
$current = $this->inputTokens[$i];
return true;
}
protected function forwardUntilEndToken(&$i, &$current, &$nesting) {
$result = $this->forward($i, $current);
if (!$result) return false;
if ($nesting === null) $nesting = 0;
if     ($current instanceof HTMLPurifier_Token_Start) $nesting++;
elseif ($current instanceof HTMLPurifier_Token_End) {
if ($nesting <= 0) return false;
$nesting--;
}
return true;
}
protected function backward(&$i, &$current) {
if ($i === null) $i = $this->inputIndex - 1;
else $i--;
if ($i < 0) return false;
$current = $this->inputTokens[$i];
return true;
}
protected function current(&$i, &$current) {
if ($i === null) $i = $this->inputIndex;
$current = $this->inputTokens[$i];
}
public function handleText(&$token) {}
public function handleElement(&$token) {}
public function handleEnd(&$token) {
$this->notifyEnd($token);
}
public function notifyEnd($token) {}
}
class HTMLPurifier_Language
{
public $code = 'en';
public $fallback = false;
public $messages = array();
public $errorNames = array();
public $error = false;
public $_loaded = false;
protected $config, $context;
public function __construct($config, $context) {
$this->config  = $config;
$this->context = $context;
}
public function load() {
if ($this->_loaded) return;
$factory = HTMLPurifier_LanguageFactory::instance();
$factory->loadLanguage($this->code);
foreach ($factory->keys as $key) {
$this->$key = $factory->cache[$this->code][$key];
}
$this->_loaded = true;
}
public function getMessage($key) {
if (!$this->_loaded) $this->load();
if (!isset($this->messages[$key])) return "[$key]";
return $this->messages[$key];
}
public function getErrorName($int) {
if (!$this->_loaded) $this->load();
if (!isset($this->errorNames[$int])) return "[Error: $int]";
return $this->errorNames[$int];
}
public function listify($array) {
$sep      = $this->getMessage('Item separator');
$sep_last = $this->getMessage('Item separator last');
$ret = '';
for ($i = 0, $c = count($array); $i < $c; $i++) {
if ($i == 0) {
} elseif ($i + 1 < $c) {
$ret .= $sep;
} else {
$ret .= $sep_last;
}
$ret .= $array[$i];
}
return $ret;
}
public function formatMessage($key, $args = array()) {
if (!$this->_loaded) $this->load();
if (!isset($this->messages[$key])) return "[$key]";
$raw = $this->messages[$key];
$subst = array();
$generator = false;
foreach ($args as $i=>$value) {
if (is_object($value)) {
if ($value instanceof HTMLPurifier_Token) {
if (!$generator) $generator = $this->context->get('Generator');
if (isset($value->name)) $subst['$'.$i.'.Name'] = $value->name;
if (isset($value->data)) $subst['$'.$i.'.Data'] = $value->data;
$subst['$'.$i.'.Compact'] =
$subst['$'.$i.'.Serialized'] = $generator->generateFromToken($value);
if (!empty($value->attr)) {
$stripped_token = clone $value;
$stripped_token->attr = array();
$subst['$'.$i.'.Compact'] = $generator->generateFromToken($stripped_token);
}
$subst['$'.$i.'.Line'] = $value->line ? $value->line : 'unknown';
}
continue;
} elseif (is_array($value)) {
$keys = array_keys($value);
if (array_keys($keys) === $keys) {
$subst['$'.$i] = $this->listify($value);
} else {
$subst['$'.$i.'.Keys'] = $this->listify($keys);
$subst['$'.$i.'.Values'] = $this->listify(array_values($value));
}
continue;
}
$subst['$' . $i] = $value;
}
return strtr($raw, $subst);
}
}
class HTMLPurifier_LanguageFactory
{
public $cache;
public $keys = array('fallback', 'messages', 'errorNames');
protected $validator;
protected $dir;
protected $mergeable_keys_map = array('messages'=>true, 'errorNames'=>true);
protected $mergeable_keys_list = array();
public static function instance($prototype = null) {
static $instance = null;
if ($prototype !== null) {
$instance = $prototype;
} elseif ($instance === null || $prototype == true) {
$instance = new HTMLPurifier_LanguageFactory();
$instance->setup();
}
return $instance;
}
public function setup() {
$this->validator = new HTMLPurifier_AttrDef_Lang();
$this->dir = HTMLPURIFIER_PREFIX . '/HTMLPurifier';
}
public function create($config, $context, $code = false) {
if ($code === false) {
$code = $this->validator->validate(
$config->get('Core.Language'), $config, $context
);
} else {
$code = $this->validator->validate($code, $config, $context);
}
if ($code === false) $code = 'en'; // malformed code becomes English
$pcode = str_replace('-', '_', $code); // make valid PHP classname
static $depth = 0; // recursion protection
if ($code == 'en') {
$lang = new HTMLPurifier_Language($config, $context);
} else {
$class = 'HTMLPurifier_Language_' . $pcode;
$file  = $this->dir . '/Language/classes/' . $code . '.php';
if (file_exists($file) || class_exists($class, false)) {
$lang = new $class($config, $context);
} else {
$raw_fallback = $this->getFallbackFor($code);
$fallback = $raw_fallback ? $raw_fallback : 'en';
$depth++;
$lang = $this->create($config, $context, $fallback);
if (!$raw_fallback) {
$lang->error = true;
}
$depth--;
}
}
$lang->code = $code;
return $lang;
}
public function getFallbackFor($code) {
$this->loadLanguage($code);
return $this->cache[$code]['fallback'];
}
public function loadLanguage($code) {
static $languages_seen = array(); // recursion guard
if (isset($this->cache[$code])) return;
$filename = $this->dir . '/Language/messages/' . $code . '.php';
$fallback = ($code != 'en') ? 'en' : false;
if (!file_exists($filename)) {
$filename = $this->dir . '/Language/messages/en.php';
$cache = array();
} else {
include $filename;
$cache = compact($this->keys);
}
if (!empty($fallback)) {
if (isset($languages_seen[$code])) {
trigger_error('Circular fallback reference in language ' .
$code, E_USER_ERROR);
$fallback = 'en';
}
$language_seen[$code] = true;
$this->loadLanguage($fallback);
$fallback_cache = $this->cache[$fallback];
foreach ( $this->keys as $key ) {
if (isset($cache[$key]) && isset($fallback_cache[$key])) {
if (isset($this->mergeable_keys_map[$key])) {
$cache[$key] = $cache[$key] + $fallback_cache[$key];
} elseif (isset($this->mergeable_keys_list[$key])) {
$cache[$key] = array_merge( $fallback_cache[$key], $cache[$key] );
}
} else {
$cache[$key] = $fallback_cache[$key];
}
}
}
$this->cache[$code] = $cache;
return;
}
}
class HTMLPurifier_Length
{
protected $n;
protected $unit;
protected $isValid;
protected static $allowedUnits = array(
'em'=>true, 'ex'=>true, 'px'=>true, 'in'=>true,
'cm'=>true, 'mm'=>true, 'pt'=>true, 'pc'=>true
);
public function __construct($n = '0', $u = false) {
$this->n = (string) $n;
$this->unit = $u !== false ? (string) $u : false;
}
static public function make($s) {
if ($s instanceof HTMLPurifier_Length) return $s;
$n_length = strspn($s, '1234567890.+-');
$n = substr($s, 0, $n_length);
$unit = substr($s, $n_length);
if ($unit === '') $unit = false;
return new HTMLPurifier_Length($n, $unit);
}
protected function validate() {
if ($this->n === '+0' || $this->n === '-0') $this->n = '0';
if ($this->n === '0' && $this->unit === false) return true;
if (!ctype_lower($this->unit)) $this->unit = strtolower($this->unit);
if (!isset(HTMLPurifier_Length::$allowedUnits[$this->unit])) return false;
$def = new HTMLPurifier_AttrDef_CSS_Number();
$result = $def->validate($this->n, false, false);
if ($result === false) return false;
$this->n = $result;
return true;
}
public function toString() {
if (!$this->isValid()) return false;
return $this->n . $this->unit;
}
public function getN() {return $this->n;}
public function getUnit() {return $this->unit;}
public function isValid() {
if ($this->isValid === null) $this->isValid = $this->validate();
return $this->isValid;
}
public function compareTo($l) {
if ($l === false) return false;
if ($l->unit !== $this->unit) {
$converter = new HTMLPurifier_UnitConverter();
$l = $converter->convert($l, $this->unit);
if ($l === false) return false;
}
return $this->n - $l->n;
}
}
class HTMLPurifier_Lexer
{
public $tracksLineNumbers = false;
public static function create($config) {
if (!($config instanceof HTMLPurifier_Config)) {
$lexer = $config;
trigger_error("Passing a prototype to
HTMLPurifier_Lexer::create() is deprecated, please instead
use %Core.LexerImpl", E_USER_WARNING);
} else {
$lexer = $config->get('Core.LexerImpl');
}
$needs_tracking =
$config->get('Core.MaintainLineNumbers') ||
$config->get('Core.CollectErrors');
$inst = null;
if (is_object($lexer)) {
$inst = $lexer;
} else {
if (is_null($lexer)) { do {
if ($needs_tracking) {
$lexer = 'DirectLex';
break;
}
if (
class_exists('DOMDocument') &&
method_exists('DOMDocument', 'loadHTML') &&
!extension_loaded('domxml')
) {
$lexer = 'DOMLex';
} else {
$lexer = 'DirectLex';
}
} while(0); } // do..while so we can break
switch ($lexer) {
case 'DOMLex':
$inst = new HTMLPurifier_Lexer_DOMLex();
break;
case 'DirectLex':
$inst = new HTMLPurifier_Lexer_DirectLex();
break;
case 'PH5P':
$inst = new HTMLPurifier_Lexer_PH5P();
break;
default:
throw new HTMLPurifier_Exception("Cannot instantiate unrecognized Lexer type " . htmlspecialchars($lexer));
}
}
if (!$inst) throw new HTMLPurifier_Exception('No lexer was instantiated');
if ($needs_tracking && !$inst->tracksLineNumbers) {
throw new HTMLPurifier_Exception('Cannot use lexer that does not support line numbers with Core.MaintainLineNumbers or Core.CollectErrors (use DirectLex instead)');
}
return $inst;
}
public function __construct() {
$this->_entity_parser = new HTMLPurifier_EntityParser();
}
protected $_special_entity2str =
array(
'&quot;'=>'"',
'&amp;'=>'&',
'&lt;'=>'<',
'&gt;'=>'>',
'&#39;'=>"'",
'&#039;'=>"'",
'&#x27;'=>"'"
);
public function parseData($string) {
if ($string === '') return '';
$num_amp = substr_count($string, '&') - substr_count($string, '& ') -
($string[strlen($string)-1] === '&' ? 1 : 0);
if (!$num_amp) return $string; // abort if no entities
$num_esc_amp = substr_count($string, '&amp;');
$string = strtr($string, $this->_special_entity2str);
$num_amp_2 = substr_count($string, '&') - substr_count($string, '& ') -
($string[strlen($string)-1] === '&' ? 1 : 0);
if ($num_amp_2 <= $num_esc_amp) return $string;
$string = $this->_entity_parser->substituteSpecialEntities($string);
return $string;
}
public function tokenizeHTML($string, $config, $context) {
trigger_error('Call to abstract class', E_USER_ERROR);
}
protected static function escapeCDATA($string) {
return preg_replace_callback(
'/<!\[CDATA\[(.+?)\]\]>/s',
array('HTMLPurifier_Lexer', 'CDATACallback'),
$string
);
}
protected static function escapeCommentedCDATA($string) {
return preg_replace_callback(
'#<!--//--><!\[CDATA\[//><!--(.+?)//--><!\]\]>#s',
array('HTMLPurifier_Lexer', 'CDATACallback'),
$string
);
}
protected static function removeIEConditional($string) {
return preg_replace(
'#<!--\[if [^>]+\]>.*?<!\[endif\]-->#si', // probably should generalize for all strings
'',
$string
);
}
protected static function CDATACallback($matches) {
return htmlspecialchars($matches[1], ENT_COMPAT, 'UTF-8');
}
public function normalize($html, $config, $context) {
if ($config->get('Core.NormalizeNewlines')) {
$html = str_replace("\r\n", "\n", $html);
$html = str_replace("\r", "\n", $html);
}
if ($config->get('HTML.Trusted')) {
$html = $this->escapeCommentedCDATA($html);
}
$html = $this->escapeCDATA($html);
$html = $this->removeIEConditional($html);
if ($config->get('Core.ConvertDocumentToFragment')) {
$e = false;
if ($config->get('Core.CollectErrors')) {
$e =& $context->get('ErrorCollector');
}
$new_html = $this->extractBody($html);
if ($e && $new_html != $html) {
$e->send(E_WARNING, 'Lexer: Extracted body');
}
$html = $new_html;
}
$html = $this->_entity_parser->substituteNonSpecialEntities($html);
$html = HTMLPurifier_Encoder::cleanUTF8($html);
if ($config->get('Core.RemoveProcessingInstructions')) {
$html = preg_replace('#<\?.+?\?>#s', '', $html);
}
return $html;
}
public function extractBody($html) {
$matches = array();
$result = preg_match('!<body[^>]*>(.*)</body>!is', $html, $matches);
if ($result) {
return $matches[1];
} else {
return $html;
}
}
}
class HTMLPurifier_PercentEncoder
{
protected $preserve = array();
public function __construct($preserve = false) {
for ($i = 48; $i <= 57;  $i++) $this->preserve[$i] = true; // digits
for ($i = 65; $i <= 90;  $i++) $this->preserve[$i] = true; // upper-case
for ($i = 97; $i <= 122; $i++) $this->preserve[$i] = true; // lower-case
$this->preserve[45] = true; // Dash         -
$this->preserve[46] = true; // Period       .
$this->preserve[95] = true; // Underscore   _
$this->preserve[126]= true; // Tilde        ~
if ($preserve !== false) {
for ($i = 0, $c = strlen($preserve); $i < $c; $i++) {
$this->preserve[ord($preserve[$i])] = true;
}
}
}
public function encode($string) {
$ret = '';
for ($i = 0, $c = strlen($string); $i < $c; $i++) {
if ($string[$i] !== '%' && !isset($this->preserve[$int = ord($string[$i])]) ) {
$ret .= '%' . sprintf('%02X', $int);
} else {
$ret .= $string[$i];
}
}
return $ret;
}
public function normalize($string) {
if ($string == '') return '';
$parts = explode('%', $string);
$ret = array_shift($parts);
foreach ($parts as $part) {
$length = strlen($part);
if ($length < 2) {
$ret .= '%25' . $part;
continue;
}
$encoding = substr($part, 0, 2);
$text     = substr($part, 2);
if (!ctype_xdigit($encoding)) {
$ret .= '%25' . $part;
continue;
}
$int = hexdec($encoding);
if (isset($this->preserve[$int])) {
$ret .= chr($int) . $text;
continue;
}
$encoding = strtoupper($encoding);
$ret .= '%' . $encoding . $text;
}
return $ret;
}
}
class HTMLPurifier_PropertyList
{
protected $data = array();
protected $parent;
protected $cache;
public function __construct($parent = null) {
$this->parent = $parent;
}
public function get($name) {
if ($this->has($name)) return $this->data[$name];
if ($this->parent) return $this->parent->get($name);
throw new HTMLPurifier_Exception("Key '$name' not found");
}
public function set($name, $value) {
$this->data[$name] = $value;
}
public function has($name) {
return array_key_exists($name, $this->data);
}
public function reset($name = null) {
if ($name == null) $this->data = array();
else unset($this->data[$name]);
}
public function squash($force = false) {
if ($this->cache !== null && !$force) return $this->cache;
if ($this->parent) {
return $this->cache = array_merge($this->parent->squash($force), $this->data);
} else {
return $this->cache = $this->data;
}
}
public function getParent() {
return $this->parent;
}
public function setParent($plist) {
$this->parent = $plist;
}
}
class HTMLPurifier_PropertyListIterator extends FilterIterator
{
protected $l;
protected $filter;
public function __construct(Iterator $iterator, $filter = null) {
parent::__construct($iterator);
$this->l = strlen($filter);
$this->filter = $filter;
}
public function accept() {
$key = $this->getInnerIterator()->key();
if( strncmp($key, $this->filter, $this->l) !== 0 ) {
return false;
}
return true;
}
}
abstract class HTMLPurifier_Strategy
{
abstract public function execute($tokens, $config, $context);
}
class HTMLPurifier_StringHash extends ArrayObject
{
protected $accessed = array();
public function offsetGet($index) {
$this->accessed[$index] = true;
return parent::offsetGet($index);
}
public function getAccessed() {
return $this->accessed;
}
public function resetAccessed() {
$this->accessed = array();
}
}
class HTMLPurifier_StringHashParser
{
public $default = 'ID';
public function parseFile($file) {
if (!file_exists($file)) return false;
$fh = fopen($file, 'r');
if (!$fh) return false;
$ret = $this->parseHandle($fh);
fclose($fh);
return $ret;
}
public function parseMultiFile($file) {
if (!file_exists($file)) return false;
$ret = array();
$fh = fopen($file, 'r');
if (!$fh) return false;
while (!feof($fh)) {
$ret[] = $this->parseHandle($fh);
}
fclose($fh);
return $ret;
}
protected function parseHandle($fh) {
$state   = false;
$single  = false;
$ret     = array();
do {
$line = fgets($fh);
if ($line === false) break;
$line = rtrim($line, "\n\r");
if (!$state && $line === '') continue;
if ($line === '----') break;
if (strncmp('--#', $line, 3) === 0) {
continue;
} elseif (strncmp('--', $line, 2) === 0) {
$state = trim($line, '- ');
if (!isset($ret[$state])) $ret[$state] = '';
continue;
} elseif (!$state) {
$single = true;
if (strpos($line, ':') !== false) {
list($state, $line) = explode(':', $line, 2);
$line = trim($line);
} else {
$state  = $this->default;
}
}
if ($single) {
$ret[$state] = $line;
$single = false;
$state  = false;
} else {
$ret[$state] .= "$line\n";
}
} while (!feof($fh));
return $ret;
}
}
abstract class HTMLPurifier_TagTransform
{
public $transform_to;
abstract public function transform($tag, $config, $context);
protected function prependCSS(&$attr, $css) {
$attr['style'] = isset($attr['style']) ? $attr['style'] : '';
$attr['style'] = $css . $attr['style'];
}
}
class HTMLPurifier_Token {
public $line; 
public $col;  
public $armor = array();
public $skip;
public $rewind;
public $carryover;
public function __get($n) {
if ($n === 'type') {
trigger_error('Deprecated type property called; use instanceof', E_USER_NOTICE);
switch (get_class($this)) {
case 'HTMLPurifier_Token_Start':      return 'start';
case 'HTMLPurifier_Token_Empty':      return 'empty';
case 'HTMLPurifier_Token_End':        return 'end';
case 'HTMLPurifier_Token_Text':       return 'text';
case 'HTMLPurifier_Token_Comment':    return 'comment';
default: return null;
}
}
}
public function position($l = null, $c = null) {
$this->line = $l;
$this->col  = $c;
}
public function rawPosition($l, $c) {
if ($c === -1) $l++;
$this->line = $l;
$this->col  = $c;
}
}
class HTMLPurifier_TokenFactory
{
private $p_start, $p_end, $p_empty, $p_text, $p_comment;
public function __construct() {
$this->p_start  = new HTMLPurifier_Token_Start('', array());
$this->p_end    = new HTMLPurifier_Token_End('');
$this->p_empty  = new HTMLPurifier_Token_Empty('', array());
$this->p_text   = new HTMLPurifier_Token_Text('');
$this->p_comment= new HTMLPurifier_Token_Comment('');
}
public function createStart($name, $attr = array()) {
$p = clone $this->p_start;
$p->__construct($name, $attr);
return $p;
}
public function createEnd($name) {
$p = clone $this->p_end;
$p->__construct($name);
return $p;
}
public function createEmpty($name, $attr = array()) {
$p = clone $this->p_empty;
$p->__construct($name, $attr);
return $p;
}
public function createText($data) {
$p = clone $this->p_text;
$p->__construct($data);
return $p;
}
public function createComment($data) {
$p = clone $this->p_comment;
$p->__construct($data);
return $p;
}
}
class HTMLPurifier_URI
{
public $scheme, $userinfo, $host, $port, $path, $query, $fragment;
public function __construct($scheme, $userinfo, $host, $port, $path, $query, $fragment) {
$this->scheme = is_null($scheme) || ctype_lower($scheme) ? $scheme : strtolower($scheme);
$this->userinfo = $userinfo;
$this->host = $host;
$this->port = is_null($port) ? $port : (int) $port;
$this->path = $path;
$this->query = $query;
$this->fragment = $fragment;
}
public function getSchemeObj($config, $context) {
$registry = HTMLPurifier_URISchemeRegistry::instance();
if ($this->scheme !== null) {
$scheme_obj = $registry->getScheme($this->scheme, $config, $context);
if (!$scheme_obj) return false; // invalid scheme, clean it out
} else {
$def = $config->getDefinition('URI');
$scheme_obj = $def->getDefaultScheme($config, $context);
if (!$scheme_obj) {
trigger_error(
'Default scheme object "' . $def->defaultScheme . '" was not readable',
E_USER_WARNING
);
return false;
}
}
return $scheme_obj;
}
public function validate($config, $context) {
$chars_sub_delims = '!$&\'()*+,;=';
$chars_gen_delims = ':/?#[]@';
$chars_pchar = $chars_sub_delims . ':@';
if (!is_null($this->host)) {
$host_def = new HTMLPurifier_AttrDef_URI_Host();
$this->host = $host_def->validate($this->host, $config, $context);
if ($this->host === false) $this->host = null;
}
if (!is_null($this->scheme) && is_null($this->host) || $this->host === '') {
$def = $config->getDefinition('URI');
if ($def->defaultScheme === $this->scheme) {
$this->scheme = null;
}
}
if (!is_null($this->userinfo)) {
$encoder = new HTMLPurifier_PercentEncoder($chars_sub_delims . ':');
$this->userinfo = $encoder->encode($this->userinfo);
}
if (!is_null($this->port)) {
if ($this->port < 1 || $this->port > 65535) $this->port = null;
}
$path_parts = array();
$segments_encoder = new HTMLPurifier_PercentEncoder($chars_pchar . '/');
if (!is_null($this->host)) { // this catches $this->host === ''
$this->path = $segments_encoder->encode($this->path);
} elseif ($this->path !== '') {
if ($this->path[0] === '/') {
if (strlen($this->path) >= 2 && $this->path[1] === '/') {
$this->path = '';
} else {
$this->path = $segments_encoder->encode($this->path);
}
} elseif (!is_null($this->scheme)) {
$this->path = $segments_encoder->encode($this->path);
} else {
$segment_nc_encoder = new HTMLPurifier_PercentEncoder($chars_sub_delims . '@');
$c = strpos($this->path, '/');
if ($c !== false) {
$this->path =
$segment_nc_encoder->encode(substr($this->path, 0, $c)) .
$segments_encoder->encode(substr($this->path, $c));
} else {
$this->path = $segment_nc_encoder->encode($this->path);
}
}
} else {
$this->path = ''; // just to be safe
}
$qf_encoder = new HTMLPurifier_PercentEncoder($chars_pchar . '/?');
if (!is_null($this->query)) {
$this->query = $qf_encoder->encode($this->query);
}
if (!is_null($this->fragment)) {
$this->fragment = $qf_encoder->encode($this->fragment);
}
return true;
}
public function toString() {
$authority = null;
if (!is_null($this->host)) {
$authority = '';
if(!is_null($this->userinfo)) $authority .= $this->userinfo . '@';
$authority .= $this->host;
if(!is_null($this->port))     $authority .= ':' . $this->port;
}
$result = '';
if (!is_null($this->scheme))    $result .= $this->scheme . ':';
if (!is_null($authority))       $result .=  '//' . $authority;
$result .= $this->path;
if (!is_null($this->query))     $result .= '?' . $this->query;
if (!is_null($this->fragment))  $result .= '#' . $this->fragment;
return $result;
}
public function isLocal($config, $context) {
if ($this->host === null) return true;
$uri_def = $config->getDefinition('URI');
if ($uri_def->host === $this->host) return true;
return false;
}
public function isBenign($config, $context) {
if (!$this->isLocal($config, $context)) return false;
$scheme_obj = $this->getSchemeObj($config, $context);
if (!$scheme_obj) return false; // conservative approach
$current_scheme_obj = $config->getDefinition('URI')->getDefaultScheme($config, $context);
if ($current_scheme_obj->secure) {
if (!$scheme_obj->secure) {
return false;
}
}
return true;
}
}
class HTMLPurifier_URIDefinition extends HTMLPurifier_Definition
{
public $type = 'URI';
protected $filters = array();
protected $postFilters = array();
protected $registeredFilters = array();
public $base;
public $host;
public $defaultScheme;
public function __construct() {
$this->registerFilter(new HTMLPurifier_URIFilter_DisableExternal());
$this->registerFilter(new HTMLPurifier_URIFilter_DisableExternalResources());
$this->registerFilter(new HTMLPurifier_URIFilter_HostBlacklist());
$this->registerFilter(new HTMLPurifier_URIFilter_SafeIframe());
$this->registerFilter(new HTMLPurifier_URIFilter_MakeAbsolute());
$this->registerFilter(new HTMLPurifier_URIFilter_Munge());
}
public function registerFilter($filter) {
$this->registeredFilters[$filter->name] = $filter;
}
public function addFilter($filter, $config) {
$r = $filter->prepare($config);
if ($r === false) return; // null is ok, for backwards compat
if ($filter->post) {
$this->postFilters[$filter->name] = $filter;
} else {
$this->filters[$filter->name] = $filter;
}
}
protected function doSetup($config) {
$this->setupMemberVariables($config);
$this->setupFilters($config);
}
protected function setupFilters($config) {
foreach ($this->registeredFilters as $name=>$filter) {
if ($filter->always_load) {
$this->addFilter($filter, $config);
} else {
$conf = $config->get('URI.' . $name);
if ($conf !== false && $conf !== null) {
$this->addFilter($filter, $config);
}
}
}
unset($this->registeredFilters);
}
protected function setupMemberVariables($config) {
$this->host = $config->get('URI.Host');
$base_uri = $config->get('URI.Base');
if (!is_null($base_uri)) {
$parser = new HTMLPurifier_URIParser();
$this->base = $parser->parse($base_uri);
$this->defaultScheme = $this->base->scheme;
if (is_null($this->host)) $this->host = $this->base->host;
}
if (is_null($this->defaultScheme)) $this->defaultScheme = $config->get('URI.DefaultScheme');
}
public function getDefaultScheme($config, $context) {
return HTMLPurifier_URISchemeRegistry::instance()->getScheme($this->defaultScheme, $config, $context);
}
public function filter(&$uri, $config, $context) {
foreach ($this->filters as $name=>$f) {
$result = $f->filter($uri, $config, $context);
if (!$result) return false;
}
return true;
}
public function postFilter(&$uri, $config, $context) {
foreach ($this->postFilters as $name=>$f) {
$result = $f->filter($uri, $config, $context);
if (!$result) return false;
}
return true;
}
}
abstract class HTMLPurifier_URIFilter
{
public $name;
public $post = false;
public $always_load = false;
public function prepare($config) {return true;}
abstract public function filter(&$uri, $config, $context);
}
class HTMLPurifier_URIParser
{
protected $percentEncoder;
public function __construct() {
$this->percentEncoder = new HTMLPurifier_PercentEncoder();
}
public function parse($uri) {
$uri = $this->percentEncoder->normalize($uri);
$r_URI = '!'.
'(([^:/?#"<>]+):)?'. // 2. Scheme
'(//([^/?#"<>]*))?'. // 4. Authority
'([^?#"<>]*)'.       // 5. Path
'(\?([^#"<>]*))?'.   // 7. Query
'(#([^"<>]*))?'.     // 8. Fragment
'!';
$matches = array();
$result = preg_match($r_URI, $uri, $matches);
if (!$result) return false; // *really* invalid URI
$scheme     = !empty($matches[1]) ? $matches[2] : null;
$authority  = !empty($matches[3]) ? $matches[4] : null;
$path       = $matches[5]; // always present, can be empty
$query      = !empty($matches[6]) ? $matches[7] : null;
$fragment   = !empty($matches[8]) ? $matches[9] : null;
if ($authority !== null) {
$r_authority = "/^((.+?)@)?(\[[^\]]+\]|[^:]*)(:(\d*))?/";
$matches = array();
preg_match($r_authority, $authority, $matches);
$userinfo   = !empty($matches[1]) ? $matches[2] : null;
$host       = !empty($matches[3]) ? $matches[3] : '';
$port       = !empty($matches[4]) ? (int) $matches[5] : null;
} else {
$port = $host = $userinfo = null;
}
return new HTMLPurifier_URI(
$scheme, $userinfo, $host, $port, $path, $query, $fragment);
}
}
abstract class HTMLPurifier_URIScheme
{
public $default_port = null;
public $browsable = false;
public $secure = false;
public $hierarchical = false;
public $may_omit_host = false;
public abstract function doValidate(&$uri, $config, $context);
public function validate(&$uri, $config, $context) {
if ($this->default_port == $uri->port) $uri->port = null;
if (!$this->may_omit_host &&
(!is_null($uri->scheme) && ($uri->host === '' || is_null($uri->host))) ||
(is_null($uri->scheme) && $uri->host === '')
) {
do {
if (is_null($uri->scheme)) {
if (substr($uri->path, 0, 2) != '//') {
$uri->host = null;
break;
}
}
$host = $config->get('URI.Host');
if (!is_null($host)) {
$uri->host = $host;
} else {
return false;
}
} while (false);
}
return $this->doValidate($uri, $config, $context);
}
}
class HTMLPurifier_URISchemeRegistry
{
public static function instance($prototype = null) {
static $instance = null;
if ($prototype !== null) {
$instance = $prototype;
} elseif ($instance === null || $prototype == true) {
$instance = new HTMLPurifier_URISchemeRegistry();
}
return $instance;
}
protected $schemes = array();
public function getScheme($scheme, $config, $context) {
if (!$config) $config = HTMLPurifier_Config::createDefault();
$allowed_schemes = $config->get('URI.AllowedSchemes');
if (!$config->get('URI.OverrideAllowedSchemes') &&
!isset($allowed_schemes[$scheme])
) {
return;
}
if (isset($this->schemes[$scheme])) return $this->schemes[$scheme];
if (!isset($allowed_schemes[$scheme])) return;
$class = 'HTMLPurifier_URIScheme_' . $scheme;
if (!class_exists($class)) return;
$this->schemes[$scheme] = new $class();
return $this->schemes[$scheme];
}
public function register($scheme, $scheme_obj) {
$this->schemes[$scheme] = $scheme_obj;
}
}
class HTMLPurifier_UnitConverter
{
const ENGLISH = 1;
const METRIC = 2;
const DIGITAL = 3;
protected static $units = array(
self::ENGLISH=>array(
'px'=>3, // This is as per CSS 2.1 and Firefox. Your mileage may vary
'pt'=>4,
'pc'=>48,
'in'=>288,
self::METRIC=>array('pt', '0.352777778', 'mm'),
),
self::METRIC=>array(
'mm'=>1,
'cm'=>10,
self::ENGLISH=>array('mm', '2.83464567', 'pt'),
),
);
protected $outputPrecision;
protected $internalPrecision;
private $bcmath;
public function __construct($output_precision = 4, $internal_precision = 10, $force_no_bcmath = false) {
$this->outputPrecision = $output_precision;
$this->internalPrecision = $internal_precision;
$this->bcmath = !$force_no_bcmath && function_exists('bcmul');
}
public function convert($length, $to_unit) {
if (!$length->isValid()) return false;
$n    = $length->getN();
$unit = $length->getUnit();
if ($n === '0' || $unit === false) {
return new HTMLPurifier_Length('0', false);
}
$state = $dest_state = false;
foreach (self::$units as $k=>$x) {
if (isset($x[$unit])) $state = $k;
if (isset($x[$to_unit])) $dest_state = $k;
}
if (!$state || !$dest_state) return false;
$sigfigs = $this->getSigFigs($n);
if ($sigfigs < $this->outputPrecision) $sigfigs = $this->outputPrecision;
$log = (int) floor(log(abs($n), 10));
$cp = ($log < 0) ? $this->internalPrecision - $log : $this->internalPrecision; // internal precision
for ($i = 0; $i < 2; $i++) {
if ($dest_state === $state) {
$dest_unit = $to_unit;
} else {
$dest_unit = self::$units[$state][$dest_state][0];
}
if ($dest_unit !== $unit) {
$factor = $this->div(self::$units[$state][$unit], self::$units[$state][$dest_unit], $cp);
$n = $this->mul($n, $factor, $cp);
$unit = $dest_unit;
}
if ($n === '') {
$n = '0';
$unit = $to_unit;
break;
}
if ($dest_state === $state) {
break;
}
if ($i !== 0) {
return false;
}
$n = $this->mul($n, self::$units[$state][$dest_state][1], $cp);
$unit = self::$units[$state][$dest_state][2];
$state = $dest_state;
}
if ($unit !== $to_unit) return false;
$n = $this->round($n, $sigfigs);
if (strpos($n, '.') !== false) $n = rtrim($n, '0');
$n = rtrim($n, '.');
return new HTMLPurifier_Length($n, $unit);
}
public function getSigFigs($n) {
$n = ltrim($n, '0+-');
$dp = strpos($n, '.'); // decimal position
if ($dp === false) {
$sigfigs = strlen(rtrim($n, '0'));
} else {
$sigfigs = strlen(ltrim($n, '0.')); // eliminate extra decimal character
if ($dp !== 0) $sigfigs--;
}
return $sigfigs;
}
private function add($s1, $s2, $scale) {
if ($this->bcmath) return bcadd($s1, $s2, $scale);
else return $this->scale($s1 + $s2, $scale);
}
private function mul($s1, $s2, $scale) {
if ($this->bcmath) return bcmul($s1, $s2, $scale);
else return $this->scale($s1 * $s2, $scale);
}
private function div($s1, $s2, $scale) {
if ($this->bcmath) return bcdiv($s1, $s2, $scale);
else return $this->scale($s1 / $s2, $scale);
}
private function round($n, $sigfigs) {
$new_log = (int) floor(log(abs($n), 10)); // Number of digits left of decimal - 1
$rp = $sigfigs - $new_log - 1; // Number of decimal places needed
$neg = $n < 0 ? '-' : ''; // Negative sign
if ($this->bcmath) {
if ($rp >= 0) {
$n = bcadd($n, $neg . '0.' .  str_repeat('0', $rp) . '5', $rp + 1);
$n = bcdiv($n, '1', $rp);
} else {
$n = bcadd($n, $neg . '5' . str_repeat('0', $new_log - $sigfigs), 0);
$n = substr($n, 0, $sigfigs + strlen($neg)) . str_repeat('0', $new_log - $sigfigs + 1);
}
return $n;
} else {
return $this->scale(round($n, $sigfigs - $new_log - 1), $rp + 1);
}
}
private function scale($r, $scale) {
if ($scale < 0) {
$r = sprintf('%.0f', (float) $r);
$precise = (string) round(substr($r, 0, strlen($r) + $scale), -1);
return substr($precise, 0, -1) . str_repeat('0', -$scale + 1);
}
return sprintf('%.' . $scale . 'f', (float) $r);
}
}
class HTMLPurifier_VarParser
{
const STRING    = 1;
const ISTRING   = 2;
const TEXT      = 3;
const ITEXT     = 4;
const INT       = 5;
const FLOAT     = 6;
const BOOL      = 7;
const LOOKUP    = 8;
const ALIST     = 9;
const HASH      = 10;
const MIXED     = 11;
static public $types = array(
'string'=>self::STRING,
'istring'=>self::ISTRING,
'text'=>self::TEXT,
'itext'=>self::ITEXT,
'int'=>self::INT,
'float'=>self::FLOAT,
'bool'=>self::BOOL,
'lookup'=>self::LOOKUP,
'list'=>self::ALIST,
'hash'=>self::HASH,
'mixed'=>self::MIXED
);
static public $stringTypes = array(
self::STRING=>true,
self::ISTRING=>true,
self::TEXT=>true,
self::ITEXT=>true,
);
final public function parse($var, $type, $allow_null = false) {
if (is_string($type)) {
if (!isset(HTMLPurifier_VarParser::$types[$type])) {
throw new HTMLPurifier_VarParserException("Invalid type '$type'");
} else {
$type = HTMLPurifier_VarParser::$types[$type];
}
}
$var = $this->parseImplementation($var, $type, $allow_null);
if ($allow_null && $var === null) return null;
switch ($type) {
case (self::STRING):
case (self::ISTRING):
case (self::TEXT):
case (self::ITEXT):
if (!is_string($var)) break;
if ($type == self::ISTRING || $type == self::ITEXT) $var = strtolower($var);
return $var;
case (self::INT):
if (!is_int($var)) break;
return $var;
case (self::FLOAT):
if (!is_float($var)) break;
return $var;
case (self::BOOL):
if (!is_bool($var)) break;
return $var;
case (self::LOOKUP):
case (self::ALIST):
case (self::HASH):
if (!is_array($var)) break;
if ($type === self::LOOKUP) {
foreach ($var as $k) if ($k !== true) $this->error('Lookup table contains value other than true');
} elseif ($type === self::ALIST) {
$keys = array_keys($var);
if (array_keys($keys) !== $keys) $this->error('Indices for list are not uniform');
}
return $var;
case (self::MIXED):
return $var;
default:
$this->errorInconsistent(get_class($this), $type);
}
$this->errorGeneric($var, $type);
}
protected function parseImplementation($var, $type, $allow_null) {
return $var;
}
protected function error($msg) {
throw new HTMLPurifier_VarParserException($msg);
}
protected function errorInconsistent($class, $type) {
throw new HTMLPurifier_Exception("Inconsistency in $class: ".HTMLPurifier_VarParser::getTypeName($type)." not implemented");
}
protected function errorGeneric($var, $type) {
$vtype = gettype($var);
$this->error("Expected type ".HTMLPurifier_VarParser::getTypeName($type).", got $vtype");
}
static public function getTypeName($type) {
static $lookup;
if (!$lookup) {
$lookup = array_flip(HTMLPurifier_VarParser::$types);
}
if (!isset($lookup[$type])) return 'unknown';
return $lookup[$type];
}
}
class HTMLPurifier_VarParserException extends HTMLPurifier_Exception
{
}
class HTMLPurifier_AttrDef_CSS extends HTMLPurifier_AttrDef
{
public function validate($css, $config, $context) {
$css = $this->parseCDATA($css);
$definition = $config->getCSSDefinition();
$declarations = explode(';', $css);
$propvalues = array();
$property = false;
$context->register('CurrentCSSProperty', $property);
foreach ($declarations as $declaration) {
if (!$declaration) continue;
if (!strpos($declaration, ':')) continue;
list($property, $value) = explode(':', $declaration, 2);
$property = trim($property);
$value    = trim($value);
$ok = false;
do {
if (isset($definition->info[$property])) {
$ok = true;
break;
}
if (ctype_lower($property)) break;
$property = strtolower($property);
if (isset($definition->info[$property])) {
$ok = true;
break;
}
} while(0);
if (!$ok) continue;
if (strtolower(trim($value)) !== 'inherit') {
$result = $definition->info[$property]->validate(
$value, $config, $context );
} else {
$result = 'inherit';
}
if ($result === false) continue;
$propvalues[$property] = $result;
}
$context->destroy('CurrentCSSProperty');
$new_declarations = '';
foreach ($propvalues as $prop=>$value) {
$new_declarations .= "$prop:$value;";
}
return $new_declarations ? $new_declarations : false;
}
}
class HTMLPurifier_AttrDef_Clone extends HTMLPurifier_AttrDef
{
protected $clone;
public function __construct($clone) {
$this->clone = $clone;
}
public function validate($v, $config, $context) {
return $this->clone->validate($v, $config, $context);
}
public function make($string) {
return clone $this->clone;
}
}
class HTMLPurifier_AttrDef_Enum extends HTMLPurifier_AttrDef
{
public $valid_values   = array();
protected $case_sensitive = false; // values according to W3C spec
public function __construct(
$valid_values = array(), $case_sensitive = false
) {
$this->valid_values = array_flip($valid_values);
$this->case_sensitive = $case_sensitive;
}
public function validate($string, $config, $context) {
$string = trim($string);
if (!$this->case_sensitive) {
$string = ctype_lower($string) ? $string : strtolower($string);
}
$result = isset($this->valid_values[$string]);
return $result ? $string : false;
}
public function make($string) {
if (strlen($string) > 2 && $string[0] == 's' && $string[1] == ':') {
$string = substr($string, 2);
$sensitive = true;
} else {
$sensitive = false;
}
$values = explode(',', $string);
return new HTMLPurifier_AttrDef_Enum($values, $sensitive);
}
}
class HTMLPurifier_AttrDef_Integer extends HTMLPurifier_AttrDef
{
protected $negative = true;
protected $zero = true;
protected $positive = true;
public function __construct(
$negative = true, $zero = true, $positive = true
) {
$this->negative = $negative;
$this->zero     = $zero;
$this->positive = $positive;
}
public function validate($integer, $config, $context) {
$integer = $this->parseCDATA($integer);
if ($integer === '') return false;
if ( $this->negative && $integer[0] === '-' ) {
$digits = substr($integer, 1);
if ($digits === '0') $integer = '0'; // rm minus sign for zero
} elseif( $this->positive && $integer[0] === '+' ) {
$digits = $integer = substr($integer, 1); // rm unnecessary plus
} else {
$digits = $integer;
}
if (!ctype_digit($digits)) return false;
if (!$this->zero     && $integer == 0) return false;
if (!$this->positive && $integer > 0) return false;
if (!$this->negative && $integer < 0) return false;
return $integer;
}
}
class HTMLPurifier_AttrDef_Lang extends HTMLPurifier_AttrDef
{
public function validate($string, $config, $context) {
$string = trim($string);
if (!$string) return false;
$subtags = explode('-', $string);
$num_subtags = count($subtags);
if ($num_subtags == 0) return false; // sanity check
$length = strlen($subtags[0]);
switch ($length) {
case 0:
return false;
case 1:
if (! ($subtags[0] == 'x' || $subtags[0] == 'i') ) {
return false;
}
break;
case 2:
case 3:
if (! ctype_alpha($subtags[0]) ) {
return false;
} elseif (! ctype_lower($subtags[0]) ) {
$subtags[0] = strtolower($subtags[0]);
}
break;
default:
return false;
}
$new_string = $subtags[0];
if ($num_subtags == 1) return $new_string;
$length = strlen($subtags[1]);
if ($length == 0 || ($length == 1 && $subtags[1] != 'x') || $length > 8 || !ctype_alnum($subtags[1])) {
return $new_string;
}
if (!ctype_lower($subtags[1])) $subtags[1] = strtolower($subtags[1]);
$new_string .= '-' . $subtags[1];
if ($num_subtags == 2) return $new_string;
for ($i = 2; $i < $num_subtags; $i++) {
$length = strlen($subtags[$i]);
if ($length == 0 || $length > 8 || !ctype_alnum($subtags[$i])) {
return $new_string;
}
if (!ctype_lower($subtags[$i])) {
$subtags[$i] = strtolower($subtags[$i]);
}
$new_string .= '-' . $subtags[$i];
}
return $new_string;
}
}
class HTMLPurifier_AttrDef_Switch
{
protected $tag;
protected $withTag, $withoutTag;
public function __construct($tag, $with_tag, $without_tag) {
$this->tag = $tag;
$this->withTag = $with_tag;
$this->withoutTag = $without_tag;
}
public function validate($string, $config, $context) {
$token = $context->get('CurrentToken', true);
if (!$token || $token->name !== $this->tag) {
return $this->withoutTag->validate($string, $config, $context);
} else {
return $this->withTag->validate($string, $config, $context);
}
}
}
class HTMLPurifier_AttrDef_Text extends HTMLPurifier_AttrDef
{
public function validate($string, $config, $context) {
return $this->parseCDATA($string);
}
}
class HTMLPurifier_AttrDef_URI extends HTMLPurifier_AttrDef
{
protected $parser;
protected $embedsResource;
public function __construct($embeds_resource = false) {
$this->parser = new HTMLPurifier_URIParser();
$this->embedsResource = (bool) $embeds_resource;
}
public function make($string) {
$embeds = ($string === 'embedded');
return new HTMLPurifier_AttrDef_URI($embeds);
}
public function validate($uri, $config, $context) {
if ($config->get('URI.Disable')) return false;
$uri = $this->parseCDATA($uri);
$uri = $this->parser->parse($uri);
if ($uri === false) return false;
$context->register('EmbeddedURI', $this->embedsResource);
$ok = false;
do {
$result = $uri->validate($config, $context);
if (!$result) break;
$uri_def = $config->getDefinition('URI');
$result = $uri_def->filter($uri, $config, $context);
if (!$result) break;
$scheme_obj = $uri->getSchemeObj($config, $context);
if (!$scheme_obj) break;
if ($this->embedsResource && !$scheme_obj->browsable) break;
$result = $scheme_obj->validate($uri, $config, $context);
if (!$result) break;
$result = $uri_def->postFilter($uri, $config, $context);
if (!$result) break;
$ok = true;
} while (false);
$context->destroy('EmbeddedURI');
if (!$ok) return false;
return $uri->toString();
}
}
class HTMLPurifier_AttrDef_CSS_Number extends HTMLPurifier_AttrDef
{
protected $non_negative = false;
public function __construct($non_negative = false) {
$this->non_negative = $non_negative;
}
public function validate($number, $config, $context) {
$number = $this->parseCDATA($number);
if ($number === '') return false;
if ($number === '0') return '0';
$sign = '';
switch ($number[0]) {
case '-':
if ($this->non_negative) return false;
$sign = '-';
case '+':
$number = substr($number, 1);
}
if (ctype_digit($number)) {
$number = ltrim($number, '0');
return $number ? $sign . $number : '0';
}
if (strpos($number, '.') === false) return false;
list($left, $right) = explode('.', $number, 2);
if ($left === '' && $right === '') return false;
if ($left !== '' && !ctype_digit($left)) return false;
$left  = ltrim($left,  '0');
$right = rtrim($right, '0');
if ($right === '') {
return $left ? $sign . $left : '0';
} elseif (!ctype_digit($right)) {
return false;
}
return $sign . $left . '.' . $right;
}
}
class HTMLPurifier_AttrDef_CSS_AlphaValue extends HTMLPurifier_AttrDef_CSS_Number
{
public function __construct() {
parent::__construct(false); // opacity is non-negative, but we will clamp it
}
public function validate($number, $config, $context) {
$result = parent::validate($number, $config, $context);
if ($result === false) return $result;
$float = (float) $result;
if ($float < 0.0) $result = '0';
if ($float > 1.0) $result = '1';
return $result;
}
}
class HTMLPurifier_AttrDef_CSS_Background extends HTMLPurifier_AttrDef
{
protected $info;
public function __construct($config) {
$def = $config->getCSSDefinition();
$this->info['background-color'] = $def->info['background-color'];
$this->info['background-image'] = $def->info['background-image'];
$this->info['background-repeat'] = $def->info['background-repeat'];
$this->info['background-attachment'] = $def->info['background-attachment'];
$this->info['background-position'] = $def->info['background-position'];
}
public function validate($string, $config, $context) {
$string = $this->parseCDATA($string);
if ($string === '') return false;
$string = $this->mungeRgb($string);
$bits = explode(' ', strtolower($string)); // bits to process
$caught = array();
$caught['color']    = false;
$caught['image']    = false;
$caught['repeat']   = false;
$caught['attachment'] = false;
$caught['position'] = false;
$i = 0; // number of catches
$none = false;
foreach ($bits as $bit) {
if ($bit === '') continue;
foreach ($caught as $key=>$status) {
if ($key != 'position') {
if ($status !== false) continue;
$r = $this->info['background-' . $key]->validate($bit, $config, $context);
} else {
$r = $bit;
}
if ($r === false) continue;
if ($key == 'position') {
if ($caught[$key] === false) $caught[$key] = '';
$caught[$key] .= $r . ' ';
} else {
$caught[$key] = $r;
}
$i++;
break;
}
}
if (!$i) return false;
if ($caught['position'] !== false) {
$caught['position'] = $this->info['background-position']->
validate($caught['position'], $config, $context);
}
$ret = array();
foreach ($caught as $value) {
if ($value === false) continue;
$ret[] = $value;
}
if (empty($ret)) return false;
return implode(' ', $ret);
}
}
// percentages or something, but it's not necessary
class HTMLPurifier_AttrDef_CSS_BackgroundPosition extends HTMLPurifier_AttrDef
{
protected $length;
protected $percentage;
public function __construct() {
$this->length     = new HTMLPurifier_AttrDef_CSS_Length();
$this->percentage = new HTMLPurifier_AttrDef_CSS_Percentage();
}
public function validate($string, $config, $context) {
$string = $this->parseCDATA($string);
$bits = explode(' ', $string);
$keywords = array();
$keywords['h'] = false; // left, right
$keywords['v'] = false; // top, bottom
$keywords['ch'] = false; // center (first word)
$keywords['cv'] = false; // center (second word)
$measures = array();
$i = 0;
$lookup = array(
'top'=>'v',
'bottom'=>'v',
'left'=>'h',
'right'=>'h',
'center'=>'c'
);
foreach ($bits as $bit) {
if ($bit === '') continue;
$lbit = ctype_lower($bit) ? $bit : strtolower($bit);
if (isset($lookup[$lbit])) {
$status = $lookup[$lbit];
if ($status == 'c') {
if ($i == 0) {
$status = 'ch';
} else {
$status = 'cv';
}
}
$keywords[$status] = $lbit;
$i++;
}
$r = $this->length->validate($bit, $config, $context);
if ($r !== false) {
$measures[] = $r;
$i++;
}
$r = $this->percentage->validate($bit, $config, $context);
if ($r !== false) {
$measures[] = $r;
$i++;
}
}
if (!$i) return false; // no valid values were caught
$ret = array();
if     ($keywords['h'])     $ret[] = $keywords['h'];
elseif ($keywords['ch']) {
$ret[] = $keywords['ch'];
$keywords['cv'] = false; // prevent re-use: center = center center
}
elseif (count($measures))   $ret[] = array_shift($measures);
if     ($keywords['v'])     $ret[] = $keywords['v'];
elseif ($keywords['cv'])    $ret[] = $keywords['cv'];
elseif (count($measures))   $ret[] = array_shift($measures);
if (empty($ret)) return false;
return implode(' ', $ret);
}
}
class HTMLPurifier_AttrDef_CSS_Border extends HTMLPurifier_AttrDef
{
protected $info = array();
public function __construct($config) {
$def = $config->getCSSDefinition();
$this->info['border-width'] = $def->info['border-width'];
$this->info['border-style'] = $def->info['border-style'];
$this->info['border-top-color'] = $def->info['border-top-color'];
}
public function validate($string, $config, $context) {
$string = $this->parseCDATA($string);
$string = $this->mungeRgb($string);
$bits = explode(' ', $string);
$done = array(); // segments we've finished
$ret = ''; // return value
foreach ($bits as $bit) {
foreach ($this->info as $propname=>$validator) {
if (isset($done[$propname])) continue;
$r = $validator->validate($bit, $config, $context);
if ($r !== false) {
$ret .= $r . ' ';
$done[$propname] = true;
break;
}
}
}
return rtrim($ret);
}
}
class HTMLPurifier_AttrDef_CSS_Color extends HTMLPurifier_AttrDef
{
public function validate($color, $config, $context) {
static $colors = null;
if ($colors === null) $colors = $config->get('Core.ColorKeywords');
$color = trim($color);
if ($color === '') return false;
$lower = strtolower($color);
if (isset($colors[$lower])) return $colors[$lower];
if (strpos($color, 'rgb(') !== false) {
$length = strlen($color);
if (strpos($color, ')') !== $length - 1) return false;
$triad = substr($color, 4, $length - 4 - 1);
$parts = explode(',', $triad);
if (count($parts) !== 3) return false;
$type = false; // to ensure that they're all the same type
$new_parts = array();
foreach ($parts as $part) {
$part = trim($part);
if ($part === '') return false;
$length = strlen($part);
if ($part[$length - 1] === '%') {
if (!$type) {
$type = 'percentage';
} elseif ($type !== 'percentage') {
return false;
}
$num = (float) substr($part, 0, $length - 1);
if ($num < 0) $num = 0;
if ($num > 100) $num = 100;
$new_parts[] = "$num%";
} else {
if (!$type) {
$type = 'integer';
} elseif ($type !== 'integer') {
return false;
}
$num = (int) $part;
if ($num < 0) $num = 0;
if ($num > 255) $num = 255;
$new_parts[] = (string) $num;
}
}
$new_triad = implode(',', $new_parts);
$color = "rgb($new_triad)";
} else {
if ($color[0] === '#') {
$hex = substr($color, 1);
} else {
$hex = $color;
$color = '#' . $color;
}
$length = strlen($hex);
if ($length !== 3 && $length !== 6) return false;
if (!ctype_xdigit($hex)) return false;
}
return $color;
}
}
class HTMLPurifier_AttrDef_CSS_Composite extends HTMLPurifier_AttrDef
{
public $defs;
public function __construct($defs) {
$this->defs = $defs;
}
public function validate($string, $config, $context) {
foreach ($this->defs as $i=>$def) {
$result = $this->defs[$i]->validate($string, $config, $context);
if ($result !== false) return $result;
}
return false;
}
}
class HTMLPurifier_AttrDef_CSS_DenyElementDecorator extends HTMLPurifier_AttrDef
{
public $def, $element;
public function __construct($def, $element) {
$this->def = $def;
$this->element = $element;
}
public function validate($string, $config, $context) {
$token = $context->get('CurrentToken', true);
if ($token && $token->name == $this->element) return false;
return $this->def->validate($string, $config, $context);
}
}
class HTMLPurifier_AttrDef_CSS_Filter extends HTMLPurifier_AttrDef
{
protected $intValidator;
public function __construct() {
$this->intValidator = new HTMLPurifier_AttrDef_Integer();
}
public function validate($value, $config, $context) {
$value = $this->parseCDATA($value);
if ($value === 'none') return $value;
$function_length = strcspn($value, '(');
$function = trim(substr($value, 0, $function_length));
if ($function !== 'alpha' &&
$function !== 'Alpha' &&
$function !== 'progid:DXImageTransform.Microsoft.Alpha'
) return false;
$cursor = $function_length + 1;
$parameters_length = strcspn($value, ')', $cursor);
$parameters = substr($value, $cursor, $parameters_length);
$params = explode(',', $parameters);
$ret_params = array();
$lookup = array();
foreach ($params as $param) {
list($key, $value) = explode('=', $param);
$key   = trim($key);
$value = trim($value);
if (isset($lookup[$key])) continue;
if ($key !== 'opacity') continue;
$value = $this->intValidator->validate($value, $config, $context);
if ($value === false) continue;
$int = (int) $value;
if ($int > 100) $value = '100';
if ($int < 0) $value = '0';
$ret_params[] = "$key=$value";
$lookup[$key] = true;
}
$ret_parameters = implode(',', $ret_params);
$ret_function = "$function($ret_parameters)";
return $ret_function;
}
}
class HTMLPurifier_AttrDef_CSS_Font extends HTMLPurifier_AttrDef
{
protected $info = array();
public function __construct($config) {
$def = $config->getCSSDefinition();
$this->info['font-style']   = $def->info['font-style'];
$this->info['font-variant'] = $def->info['font-variant'];
$this->info['font-weight']  = $def->info['font-weight'];
$this->info['font-size']    = $def->info['font-size'];
$this->info['line-height']  = $def->info['line-height'];
$this->info['font-family']  = $def->info['font-family'];
}
public function validate($string, $config, $context) {
static $system_fonts = array(
'caption'=>true,
'icon'=>true,
'menu'=>true,
'message-box'=>true,
'small-caption'=>true,
'status-bar'=>true
);
$string = $this->parseCDATA($string);
if ($string === '') return false;
$lowercase_string = strtolower($string);
if (isset($system_fonts[$lowercase_string])) {
return $lowercase_string;
}
$bits = explode(' ', $string); // bits to process
$stage = 0; // this indicates what we're looking for
$caught = array(); // which stage 0 properties have we caught?
$stage_1 = array('font-style', 'font-variant', 'font-weight');
$final = ''; // output
for ($i = 0, $size = count($bits); $i < $size; $i++) {
if ($bits[$i] === '') continue;
switch ($stage) {
case 0:
foreach ($stage_1 as $validator_name) {
if (isset($caught[$validator_name])) continue;
$r = $this->info[$validator_name]->validate(
$bits[$i], $config, $context);
if ($r !== false) {
$final .= $r . ' ';
$caught[$validator_name] = true;
break;
}
}
if (count($caught) >= 3) $stage = 1;
if ($r !== false) break;
case 1:
$found_slash = false;
if (strpos($bits[$i], '/') !== false) {
list($font_size, $line_height) =
explode('/', $bits[$i]);
if ($line_height === '') {
$line_height = false;
$found_slash = true;
}
} else {
$font_size = $bits[$i];
$line_height = false;
}
$r = $this->info['font-size']->validate(
$font_size, $config, $context);
if ($r !== false) {
$final .= $r;
if ($line_height === false) {
for ($j = $i + 1; $j < $size; $j++) {
if ($bits[$j] === '') continue;
if ($bits[$j] === '/') {
if ($found_slash) {
return false;
} else {
$found_slash = true;
continue;
}
}
$line_height = $bits[$j];
break;
}
} else {
$found_slash = true;
$j = $i;
}
if ($found_slash) {
$i = $j;
$r = $this->info['line-height']->validate(
$line_height, $config, $context);
if ($r !== false) {
$final .= '/' . $r;
}
}
$final .= ' ';
$stage = 2;
break;
}
return false;
case 2:
$font_family =
implode(' ', array_slice($bits, $i, $size - $i));
$r = $this->info['font-family']->validate(
$font_family, $config, $context);
if ($r !== false) {
$final .= $r . ' ';
return rtrim($final);
}
return false;
}
}
return false;
}
}
class HTMLPurifier_AttrDef_CSS_FontFamily extends HTMLPurifier_AttrDef
{
protected $mask = null;
public function __construct() {
$this->mask = '- ';
for ($c = 'a'; $c <= 'z'; $c++) $this->mask .= $c;
for ($c = 'A'; $c <= 'Z'; $c++) $this->mask .= $c;
for ($c = '0'; $c <= '9'; $c++) $this->mask .= $c; // cast-y, but should be fine
for ($i = 0x80; $i <= 0xFF; $i++) {
$this->mask .= chr($i);
}
}
public function validate($string, $config, $context) {
static $generic_names = array(
'serif'=>true,
'sans-serif'=>true,
'monospace'=>true,
'fantasy'=>true,
'cursive'=>true
);
$allowed_fonts = $config->get('CSS.AllowedFonts');
$fonts = explode(',', $string);
$final = '';
foreach($fonts as $font) {
$font = trim($font);
if ($font === '') continue;
if (isset($generic_names[$font])) {
if ($allowed_fonts === null || isset($allowed_fonts[$font])) {
$final .= $font . ', ';
}
continue;
}
if ($font[0] === '"' || $font[0] === "'") {
$length = strlen($font);
if ($length <= 2) continue;
$quote = $font[0];
if ($font[$length - 1] !== $quote) continue;
$font = substr($font, 1, $length - 2);
}
$font = $this->expandCSSEscape($font);
if ($allowed_fonts !== null && !isset($allowed_fonts[$font])) {
continue;
}
if (ctype_alnum($font) && $font !== '') {
$final .= $font . ', ';
continue;
}
$font = str_replace(array("\n", "\t", "\r", "\x0C"), ' ', $font);
if (strspn($font, $this->mask) !== strlen($font)) {
continue;
}
$final .= "'$font', ";
}
$final = rtrim($final, ', ');
if ($final === '') return false;
return $final;
}
}
class HTMLPurifier_AttrDef_CSS_Ident extends HTMLPurifier_AttrDef
{
public function validate($string, $config, $context) {
$string = trim($string);
if (!$string) return false;
$pattern = '/^(-?[A-Za-z_][A-Za-z_\-0-9]*)$/';
if (!preg_match($pattern, $string)) return false;
return $string;
}
}
class HTMLPurifier_AttrDef_CSS_ImportantDecorator extends HTMLPurifier_AttrDef
{
public $def, $allow;
public function __construct($def, $allow = false) {
$this->def = $def;
$this->allow = $allow;
}
public function validate($string, $config, $context) {
$string = trim($string);
$is_important = false;
if (strlen($string) >= 9 && substr($string, -9) === 'important') {
$temp = rtrim(substr($string, 0, -9));
if (strlen($temp) >= 1 && substr($temp, -1) === '!') {
$string = rtrim(substr($temp, 0, -1));
$is_important = true;
}
}
$string = $this->def->validate($string, $config, $context);
if ($this->allow && $is_important) $string .= ' !important';
return $string;
}
}
class HTMLPurifier_AttrDef_CSS_Length extends HTMLPurifier_AttrDef
{
protected $min, $max;
public function __construct($min = null, $max = null) {
$this->min = $min !== null ? HTMLPurifier_Length::make($min) : null;
$this->max = $max !== null ? HTMLPurifier_Length::make($max) : null;
}
public function validate($string, $config, $context) {
$string = $this->parseCDATA($string);
if ($string === '') return false;
if ($string === '0') return '0';
if (strlen($string) === 1) return false;
$length = HTMLPurifier_Length::make($string);
if (!$length->isValid()) return false;
if ($this->min) {
$c = $length->compareTo($this->min);
if ($c === false) return false;
if ($c < 0) return false;
}
if ($this->max) {
$c = $length->compareTo($this->max);
if ($c === false) return false;
if ($c > 0) return false;
}
return $length->toString();
}
}
class HTMLPurifier_AttrDef_CSS_ListStyle extends HTMLPurifier_AttrDef
{
protected $info;
public function __construct($config) {
$def = $config->getCSSDefinition();
$this->info['list-style-type']     = $def->info['list-style-type'];
$this->info['list-style-position'] = $def->info['list-style-position'];
$this->info['list-style-image'] = $def->info['list-style-image'];
}
public function validate($string, $config, $context) {
$string = $this->parseCDATA($string);
if ($string === '') return false;
$bits = explode(' ', strtolower($string)); // bits to process
$caught = array();
$caught['type']     = false;
$caught['position'] = false;
$caught['image']    = false;
$i = 0; // number of catches
$none = false;
foreach ($bits as $bit) {
if ($i >= 3) return; // optimization bit
if ($bit === '') continue;
foreach ($caught as $key=>$status) {
if ($status !== false) continue;
$r = $this->info['list-style-' . $key]->validate($bit, $config, $context);
if ($r === false) continue;
if ($r === 'none') {
if ($none) continue;
else $none = true;
if ($key == 'image') continue;
}
$caught[$key] = $r;
$i++;
break;
}
}
if (!$i) return false;
$ret = array();
if ($caught['type']) $ret[] = $caught['type'];
if ($caught['image']) $ret[] = $caught['image'];
if ($caught['position']) $ret[] = $caught['position'];
if (empty($ret)) return false;
return implode(' ', $ret);
}
}
class HTMLPurifier_AttrDef_CSS_Multiple extends HTMLPurifier_AttrDef
{
public $single;
public $max;
public function __construct($single, $max = 4) {
$this->single = $single;
$this->max = $max;
}
public function validate($string, $config, $context) {
$string = $this->parseCDATA($string);
if ($string === '') return false;
$parts = explode(' ', $string); // parseCDATA replaced \r, \t and \n
$length = count($parts);
$final = '';
for ($i = 0, $num = 0; $i < $length && $num < $this->max; $i++) {
if (ctype_space($parts[$i])) continue;
$result = $this->single->validate($parts[$i], $config, $context);
if ($result !== false) {
$final .= $result . ' ';
$num++;
}
}
if ($final === '') return false;
return rtrim($final);
}
}
class HTMLPurifier_AttrDef_CSS_Percentage extends HTMLPurifier_AttrDef
{
protected $number_def;
public function __construct($non_negative = false) {
$this->number_def = new HTMLPurifier_AttrDef_CSS_Number($non_negative);
}
public function validate($string, $config, $context) {
$string = $this->parseCDATA($string);
if ($string === '') return false;
$length = strlen($string);
if ($length === 1) return false;
if ($string[$length - 1] !== '%') return false;
$number = substr($string, 0, $length - 1);
$number = $this->number_def->validate($number, $config, $context);
if ($number === false) return false;
return "$number%";
}
}
class HTMLPurifier_AttrDef_CSS_TextDecoration extends HTMLPurifier_AttrDef
{
public function validate($string, $config, $context) {
static $allowed_values = array(
'line-through'=>true,
'overline'=>true,
'underline'=>true,
);
$string = strtolower($this->parseCDATA($string));
if ($string === 'none') return $string;
$parts = explode(' ', $string);
$final = '';
foreach ($parts as $part) {
if (isset($allowed_values[$part])) {
$final .= $part . ' ';
}
}
$final = rtrim($final);
if ($final === '') return false;
return $final;
}
}
class HTMLPurifier_AttrDef_CSS_URI extends HTMLPurifier_AttrDef_URI
{
public function __construct() {
parent::__construct(true); // always embedded
}
public function validate($uri_string, $config, $context) {
$uri_string = $this->parseCDATA($uri_string);
if (strpos($uri_string, 'url(') !== 0) return false;
$uri_string = substr($uri_string, 4);
$new_length = strlen($uri_string) - 1;
if ($uri_string[$new_length] != ')') return false;
$uri = trim(substr($uri_string, 0, $new_length));
if (!empty($uri) && ($uri[0] == "'" || $uri[0] == '"')) {
$quote = $uri[0];
$new_length = strlen($uri) - 1;
if ($uri[$new_length] !== $quote) return false;
$uri = substr($uri, 1, $new_length - 1);
}
$uri = $this->expandCSSEscape($uri);
$result = parent::validate($uri, $config, $context);
if ($result === false) return false;
$result = str_replace(array('"', "\\", "\n", "\x0c", "\r"), "", $result);
$result = str_replace(array('(', ')', "'"), array('%28', '%29', '%27'), $result);
return "url(\"$result\")";
}
}
class HTMLPurifier_AttrDef_HTML_Bool extends HTMLPurifier_AttrDef
{
protected $name;
public $minimized = true;
public function __construct($name = false) {$this->name = $name;}
public function validate($string, $config, $context) {
if (empty($string)) return false;
return $this->name;
}
public function make($string) {
return new HTMLPurifier_AttrDef_HTML_Bool($string);
}
}
class HTMLPurifier_AttrDef_HTML_Nmtokens extends HTMLPurifier_AttrDef
{
public function validate($string, $config, $context) {
$string = trim($string);
if (!$string) return false;
$tokens = $this->split($string, $config, $context);
$tokens = $this->filter($tokens, $config, $context);
if (empty($tokens)) return false;
return implode(' ', $tokens);
}
protected function split($string, $config, $context) {
$pattern = '/(?:(?<=\s)|\A)'. // look behind for space or string start
'((?:--|-?[A-Za-z_])[A-Za-z_\-0-9]*)'.
'(?:(?=\s)|\z)/'; // look ahead for space or string end
preg_match_all($pattern, $string, $matches);
return $matches[1];
}
protected function filter($tokens, $config, $context) {
return $tokens;
}
}
class HTMLPurifier_AttrDef_HTML_Class extends HTMLPurifier_AttrDef_HTML_Nmtokens
{
protected function split($string, $config, $context) {
$name = $config->getDefinition('HTML')->doctype->name;
if ($name == "XHTML 1.1" || $name == "XHTML 2.0") {
return parent::split($string, $config, $context);
} else {
return preg_split('/\s+/', $string);
}
}
protected function filter($tokens, $config, $context) {
$allowed = $config->get('Attr.AllowedClasses');
$forbidden = $config->get('Attr.ForbiddenClasses');
$ret = array();
foreach ($tokens as $token) {
if (
($allowed === null || isset($allowed[$token])) &&
!isset($forbidden[$token]) &&
!in_array($token, $ret, true)
) {
$ret[] = $token;
}
}
return $ret;
}
}
class HTMLPurifier_AttrDef_HTML_Color extends HTMLPurifier_AttrDef
{
public function validate($string, $config, $context) {
static $colors = null;
if ($colors === null) $colors = $config->get('Core.ColorKeywords');
$string = trim($string);
if (empty($string)) return false;
if (isset($colors[strtolower($string)])) return $colors[$string];
if ($string[0] === '#') $hex = substr($string, 1);
else $hex = $string;
$length = strlen($hex);
if ($length !== 3 && $length !== 6) return false;
if (!ctype_xdigit($hex)) return false;
if ($length === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
return "#$hex";
}
}
class HTMLPurifier_AttrDef_HTML_FrameTarget extends HTMLPurifier_AttrDef_Enum
{
public $valid_values = false; // uninitialized value
protected $case_sensitive = false;
public function __construct() {}
public function validate($string, $config, $context) {
if ($this->valid_values === false) $this->valid_values = $config->get('Attr.AllowedFrameTargets');
return parent::validate($string, $config, $context);
}
}
class HTMLPurifier_AttrDef_HTML_ID extends HTMLPurifier_AttrDef
{
protected $selector;
public function __construct($selector = false) {
$this->selector = $selector;
}
public function validate($id, $config, $context) {
if (!$this->selector && !$config->get('Attr.EnableID')) return false;
$id = trim($id); // trim it first
if ($id === '') return false;
$prefix = $config->get('Attr.IDPrefix');
if ($prefix !== '') {
$prefix .= $config->get('Attr.IDPrefixLocal');
if (strpos($id, $prefix) !== 0) $id = $prefix . $id;
} elseif ($config->get('Attr.IDPrefixLocal') !== '') {
trigger_error('%Attr.IDPrefixLocal cannot be used unless '.
'%Attr.IDPrefix is set', E_USER_WARNING);
}
if (!$this->selector) {
$id_accumulator =& $context->get('IDAccumulator');
if (isset($id_accumulator->ids[$id])) return false;
}
if (ctype_alpha($id)) {
$result = true;
} else {
if (!ctype_alpha(@$id[0])) return false;
$trim = trim( // primitive style of regexps, I suppose
$id,
'A..Za..z0..9:-._'
);
$result = ($trim === '');
}
$regexp = $config->get('Attr.IDBlacklistRegexp');
if ($regexp && preg_match($regexp, $id)) {
return false;
}
if (!$this->selector && $result) $id_accumulator->add($id);
return $result ? $id : false;
}
}
class HTMLPurifier_AttrDef_HTML_Pixels extends HTMLPurifier_AttrDef
{
protected $max;
public function __construct($max = null) {
$this->max = $max;
}
public function validate($string, $config, $context) {
$string = trim($string);
if ($string === '0') return $string;
if ($string === '')  return false;
$length = strlen($string);
if (substr($string, $length - 2) == 'px') {
$string = substr($string, 0, $length - 2);
}
if (!is_numeric($string)) return false;
$int = (int) $string;
if ($int < 0) return '0';
if ($this->max !== null && $int > $this->max) return (string) $this->max;
return (string) $int;
}
public function make($string) {
if ($string === '') $max = null;
else $max = (int) $string;
$class = get_class($this);
return new $class($max);
}
}
class HTMLPurifier_AttrDef_HTML_Length extends HTMLPurifier_AttrDef_HTML_Pixels
{
public function validate($string, $config, $context) {
$string = trim($string);
if ($string === '') return false;
$parent_result = parent::validate($string, $config, $context);
if ($parent_result !== false) return $parent_result;
$length = strlen($string);
$last_char = $string[$length - 1];
if ($last_char !== '%') return false;
$points = substr($string, 0, $length - 1);
if (!is_numeric($points)) return false;
$points = (int) $points;
if ($points < 0) return '0%';
if ($points > 100) return '100%';
return ((string) $points) . '%';
}
}
class HTMLPurifier_AttrDef_HTML_LinkTypes extends HTMLPurifier_AttrDef
{
protected $name;
public function __construct($name) {
$configLookup = array(
'rel'=>'AllowedRel',
'rev'=>'AllowedRev'
);
if (!isset($configLookup[$name])) {
trigger_error('Unrecognized attribute name for link '.
'relationship.', E_USER_ERROR);
return;
}
$this->name = $configLookup[$name];
}
public function validate($string, $config, $context) {
$allowed = $config->get('Attr.' . $this->name);
if (empty($allowed)) return false;
$string = $this->parseCDATA($string);
$parts = explode(' ', $string);
$ret_lookup = array();
foreach ($parts as $part) {
$part = strtolower(trim($part));
if (!isset($allowed[$part])) continue;
$ret_lookup[$part] = true;
}
if (empty($ret_lookup)) return false;
$string = implode(' ', array_keys($ret_lookup));
return $string;
}
}
class HTMLPurifier_AttrDef_HTML_MultiLength extends HTMLPurifier_AttrDef_HTML_Length
{
public function validate($string, $config, $context) {
$string = trim($string);
if ($string === '') return false;
$parent_result = parent::validate($string, $config, $context);
if ($parent_result !== false) return $parent_result;
$length = strlen($string);
$last_char = $string[$length - 1];
if ($last_char !== '*') return false;
$int = substr($string, 0, $length - 1);
if ($int == '') return '*';
if (!is_numeric($int)) return false;
$int = (int) $int;
if ($int < 0) return false;
if ($int == 0) return '0';
if ($int == 1) return '*';
return ((string) $int) . '*';
}
}
abstract class HTMLPurifier_AttrDef_URI_Email extends HTMLPurifier_AttrDef
{
function unpack($string) {
}
}
class HTMLPurifier_AttrDef_URI_Host extends HTMLPurifier_AttrDef
{
protected $ipv4;
protected $ipv6;
public function __construct() {
$this->ipv4 = new HTMLPurifier_AttrDef_URI_IPv4();
$this->ipv6 = new HTMLPurifier_AttrDef_URI_IPv6();
}
public function validate($string, $config, $context) {
$length = strlen($string);
if ($string === '') return '';
if ($length > 1 && $string[0] === '[' && $string[$length-1] === ']') {
$ip = substr($string, 1, $length - 2);
$valid = $this->ipv6->validate($ip, $config, $context);
if ($valid === false) return false;
return '['. $valid . ']';
}
$ipv4 = $this->ipv4->validate($string, $config, $context);
if ($ipv4 !== false) return $ipv4;
$a   = '[a-z]';     // alpha
$an  = '[a-z0-9]';  // alphanum
$and = '[a-z0-9-]'; // alphanum | "-"
$domainlabel   = "$an($and*$an)?";
$toplabel      = "$a($and*$an)?";
if (preg_match("/^($domainlabel\.)*$toplabel\.?$/i", $string)) {
return $string;
}
if ($config->get('Core.EnableIDNA')) {
$idna = new Net_IDNA2(array('encoding'=>'utf8', 'overlong'=>false, 'strict'=>true));
$parts = explode('.', $string);
try {
$new_parts = array();
foreach ($parts as $part) {
$encodable = false;
for ($i = 0, $c = strlen($part); $i < $c; $i++) {
if (ord($part[$i]) > 0x7a) {
$encodable = true;
break;
}
}
if (!$encodable) {
$new_parts[] = $part;
} else {
$new_parts[] = $idna->encode($part);
}
}
$string = implode('.', $new_parts);
if (preg_match("/^($domainlabel\.)*$toplabel\.?$/i", $string)) {
return $string;
}
} catch (Exception $e) {
}
}
return false;
}
}
class HTMLPurifier_AttrDef_URI_IPv4 extends HTMLPurifier_AttrDef
{
protected $ip4;
public function validate($aIP, $config, $context) {
if (!$this->ip4) $this->_loadRegex();
if (preg_match('#^' . $this->ip4 . '$#s', $aIP))
{
return $aIP;
}
return false;
}
protected function _loadRegex() {
$oct = '(?:25[0-5]|2[0-4][0-9]|1[0-9]{2}|[1-9][0-9]|[0-9])'; // 0-255
$this->ip4 = "(?:{$oct}\\.{$oct}\\.{$oct}\\.{$oct})";
}
}
class HTMLPurifier_AttrDef_URI_IPv6 extends HTMLPurifier_AttrDef_URI_IPv4
{
public function validate($aIP, $config, $context) {
if (!$this->ip4) $this->_loadRegex();
$original = $aIP;
$hex = '[0-9a-fA-F]';
$blk = '(?:' . $hex . '{1,4})';
$pre = '(?:/(?:12[0-8]|1[0-1][0-9]|[1-9][0-9]|[0-9]))';   // /0 - /128
if (strpos($aIP, '/') !== false)
{
if (preg_match('#' . $pre . '$#s', $aIP, $find))
{
$aIP = substr($aIP, 0, 0-strlen($find[0]));
unset($find);
}
else
{
return false;
}
}
if (preg_match('#(?<=:'.')' . $this->ip4 . '$#s', $aIP, $find))
{
$aIP = substr($aIP, 0, 0-strlen($find[0]));
$ip = explode('.', $find[0]);
$ip = array_map('dechex', $ip);
$aIP .= $ip[0] . $ip[1] . ':' . $ip[2] . $ip[3];
unset($find, $ip);
}
$aIP = explode('::', $aIP);
$c = count($aIP);
if ($c > 2)
{
return false;
}
elseif ($c == 2)
{
list($first, $second) = $aIP;
$first = explode(':', $first);
$second = explode(':', $second);
if (count($first) + count($second) > 8)
{
return false;
}
while(count($first) < 8)
{
array_push($first, '0');
}
array_splice($first, 8 - count($second), 8, $second);
$aIP = $first;
unset($first,$second);
}
else
{
$aIP = explode(':', $aIP[0]);
}
$c = count($aIP);
if ($c != 8)
{
return false;
}
foreach ($aIP as $piece)
{
if (!preg_match('#^[0-9a-fA-F]{4}$#s', sprintf('%04s', $piece)))
{
return false;
}
}
return $original;
}
}
class HTMLPurifier_AttrDef_URI_Email_SimpleCheck extends HTMLPurifier_AttrDef_URI_Email
{
public function validate($string, $config, $context) {
if ($string == '') return false;
$string = trim($string);
$result = preg_match('/^[A-Z0-9._%-]+@[A-Z0-9.-]+\.[A-Z]{2,4}$/i', $string);
return $result ? $string : false;
}
}
class HTMLPurifier_AttrTransform_Background extends HTMLPurifier_AttrTransform {
public function transform($attr, $config, $context) {
if (!isset($attr['background'])) return $attr;
$background = $this->confiscateAttr($attr, 'background');
$this->prependCSS($attr, "background-image:url($background);");
return $attr;
}
}
class HTMLPurifier_AttrTransform_BdoDir extends HTMLPurifier_AttrTransform
{
public function transform($attr, $config, $context) {
if (isset($attr['dir'])) return $attr;
$attr['dir'] = $config->get('Attr.DefaultTextDir');
return $attr;
}
}
class HTMLPurifier_AttrTransform_BgColor extends HTMLPurifier_AttrTransform {
public function transform($attr, $config, $context) {
if (!isset($attr['bgcolor'])) return $attr;
$bgcolor = $this->confiscateAttr($attr, 'bgcolor');
$this->prependCSS($attr, "background-color:$bgcolor;");
return $attr;
}
}
class HTMLPurifier_AttrTransform_BoolToCSS extends HTMLPurifier_AttrTransform {
protected $attr;
protected $css;
public function __construct($attr, $css) {
$this->attr = $attr;
$this->css  = $css;
}
public function transform($attr, $config, $context) {
if (!isset($attr[$this->attr])) return $attr;
unset($attr[$this->attr]);
$this->prependCSS($attr, $this->css);
return $attr;
}
}
class HTMLPurifier_AttrTransform_Border extends HTMLPurifier_AttrTransform {
public function transform($attr, $config, $context) {
if (!isset($attr['border'])) return $attr;
$border_width = $this->confiscateAttr($attr, 'border');
$this->prependCSS($attr, "border:{$border_width}px solid;");
return $attr;
}
}
class HTMLPurifier_AttrTransform_EnumToCSS extends HTMLPurifier_AttrTransform {
protected $attr;
protected $enumToCSS = array();
protected $caseSensitive = false;
public function __construct($attr, $enum_to_css, $case_sensitive = false) {
$this->attr = $attr;
$this->enumToCSS = $enum_to_css;
$this->caseSensitive = (bool) $case_sensitive;
}
public function transform($attr, $config, $context) {
if (!isset($attr[$this->attr])) return $attr;
$value = trim($attr[$this->attr]);
unset($attr[$this->attr]);
if (!$this->caseSensitive) $value = strtolower($value);
if (!isset($this->enumToCSS[$value])) {
return $attr;
}
$this->prependCSS($attr, $this->enumToCSS[$value]);
return $attr;
}
}
class HTMLPurifier_AttrTransform_ImgRequired extends HTMLPurifier_AttrTransform
{
public function transform($attr, $config, $context) {
$src = true;
if (!isset($attr['src'])) {
if ($config->get('Core.RemoveInvalidImg')) return $attr;
$attr['src'] = $config->get('Attr.DefaultInvalidImage');
$src = false;
}
if (!isset($attr['alt'])) {
if ($src) {
$alt = $config->get('Attr.DefaultImageAlt');
if ($alt === null) {
$attr['alt'] = substr(basename($attr['src']),0,40);
} else {
$attr['alt'] = $alt;
}
} else {
$attr['alt'] = $config->get('Attr.DefaultInvalidImageAlt');
}
}
return $attr;
}
}
class HTMLPurifier_AttrTransform_ImgSpace extends HTMLPurifier_AttrTransform {
protected $attr;
protected $css = array(
'hspace'=>array('left', 'right'),
'vspace'=>array('top', 'bottom')
);
public function __construct($attr) {
$this->attr = $attr;
if (!isset($this->css[$attr])) {
trigger_error(htmlspecialchars($attr) . ' is not valid space attribute');
}
}
public function transform($attr, $config, $context) {
if (!isset($attr[$this->attr])) return $attr;
$width = $this->confiscateAttr($attr, $this->attr);
if (!isset($this->css[$this->attr])) return $attr;
$style = '';
foreach ($this->css[$this->attr] as $suffix) {
$property = "margin-$suffix";
$style .= "$property:{$width}px;";
}
$this->prependCSS($attr, $style);
return $attr;
}
}
class HTMLPurifier_AttrTransform_Input extends HTMLPurifier_AttrTransform {
protected $pixels;
public function __construct() {
$this->pixels = new HTMLPurifier_AttrDef_HTML_Pixels();
}
public function transform($attr, $config, $context) {
if (!isset($attr['type'])) $t = 'text';
else $t = strtolower($attr['type']);
if (isset($attr['checked']) && $t !== 'radio' && $t !== 'checkbox') {
unset($attr['checked']);
}
if (isset($attr['maxlength']) && $t !== 'text' && $t !== 'password') {
unset($attr['maxlength']);
}
if (isset($attr['size']) && $t !== 'text' && $t !== 'password') {
$result = $this->pixels->validate($attr['size'], $config, $context);
if ($result === false) unset($attr['size']);
else $attr['size'] = $result;
}
if (isset($attr['src']) && $t !== 'image') {
unset($attr['src']);
}
if (!isset($attr['value']) && ($t === 'radio' || $t === 'checkbox')) {
$attr['value'] = '';
}
return $attr;
}
}
class HTMLPurifier_AttrTransform_Lang extends HTMLPurifier_AttrTransform
{
public function transform($attr, $config, $context) {
$lang     = isset($attr['lang']) ? $attr['lang'] : false;
$xml_lang = isset($attr['xml:lang']) ? $attr['xml:lang'] : false;
if ($lang !== false && $xml_lang === false) {
$attr['xml:lang'] = $lang;
} elseif ($xml_lang !== false) {
$attr['lang'] = $xml_lang;
}
return $attr;
}
}
class HTMLPurifier_AttrTransform_Length extends HTMLPurifier_AttrTransform
{
protected $name;
protected $cssName;
public function __construct($name, $css_name = null) {
$this->name = $name;
$this->cssName = $css_name ? $css_name : $name;
}
public function transform($attr, $config, $context) {
if (!isset($attr[$this->name])) return $attr;
$length = $this->confiscateAttr($attr, $this->name);
if(ctype_digit($length)) $length .= 'px';
$this->prependCSS($attr, $this->cssName . ":$length;");
return $attr;
}
}
class HTMLPurifier_AttrTransform_Name extends HTMLPurifier_AttrTransform
{
public function transform($attr, $config, $context) {
if ($config->get('HTML.Attr.Name.UseCDATA')) return $attr;
if (!isset($attr['name'])) return $attr;
$id = $this->confiscateAttr($attr, 'name');
if ( isset($attr['id']))   return $attr;
$attr['id'] = $id;
return $attr;
}
}
class HTMLPurifier_AttrTransform_NameSync extends HTMLPurifier_AttrTransform
{
public function __construct() {
$this->idDef = new HTMLPurifier_AttrDef_HTML_ID();
}
public function transform($attr, $config, $context) {
if (!isset($attr['name'])) return $attr;
$name = $attr['name'];
if (isset($attr['id']) && $attr['id'] === $name) return $attr;
$result = $this->idDef->validate($name, $config, $context);
if ($result === false) unset($attr['name']);
else $attr['name'] = $result;
return $attr;
}
}
class HTMLPurifier_AttrTransform_Nofollow extends HTMLPurifier_AttrTransform
{
private $parser;
public function __construct() {
$this->parser = new HTMLPurifier_URIParser();
}
public function transform($attr, $config, $context) {
if (!isset($attr['href'])) {
return $attr;
}
$url = $this->parser->parse($attr['href']);
$scheme = $url->getSchemeObj($config, $context);
if ($scheme->browsable && !$url->isLocal($config, $context)) {
if (isset($attr['rel'])) {
$rels = explode(' ', $attr);
if (!in_array('nofollow', $rels)) {
$rels[] = 'nofollow';
}
$attr['rel'] = implode(' ', $rels);
} else {
$attr['rel'] = 'nofollow';
}
}
return $attr;
}
}
class HTMLPurifier_AttrTransform_SafeEmbed extends HTMLPurifier_AttrTransform
{
public $name = "SafeEmbed";
public function transform($attr, $config, $context) {
$attr['allowscriptaccess'] = 'never';
$attr['allownetworking'] = 'internal';
$attr['type'] = 'application/x-shockwave-flash';
return $attr;
}
}
class HTMLPurifier_AttrTransform_SafeObject extends HTMLPurifier_AttrTransform
{
public $name = "SafeObject";
function transform($attr, $config, $context) {
if (!isset($attr['type'])) $attr['type'] = 'application/x-shockwave-flash';
return $attr;
}
}
class HTMLPurifier_AttrTransform_SafeParam extends HTMLPurifier_AttrTransform
{
public $name = "SafeParam";
private $uri;
public function __construct() {
$this->uri = new HTMLPurifier_AttrDef_URI(true); // embedded
$this->wmode = new HTMLPurifier_AttrDef_Enum(array('window', 'opaque', 'transparent'));
}
public function transform($attr, $config, $context) {
switch ($attr['name']) {
case 'allowScriptAccess':
$attr['value'] = 'never';
break;
case 'allowNetworking':
$attr['value'] = 'internal';
break;
case 'allowFullScreen':
if ($config->get('HTML.FlashAllowFullScreen')) {
$attr['value'] = ($attr['value'] == 'true') ? 'true' : 'false';
} else {
$attr['value'] = 'false';
}
break;
case 'wmode':
$attr['value'] = $this->wmode->validate($attr['value'], $config, $context);
break;
case 'movie':
case 'src':
$attr['name'] = "movie";
$attr['value'] = $this->uri->validate($attr['value'], $config, $context);
break;
case 'flashvars':
break;
default:
$attr['name'] = $attr['value'] = null;
}
return $attr;
}
}
class HTMLPurifier_AttrTransform_ScriptRequired extends HTMLPurifier_AttrTransform
{
public function transform($attr, $config, $context) {
if (!isset($attr['type'])) {
$attr['type'] = 'text/javascript';
}
return $attr;
}
}
class HTMLPurifier_AttrTransform_TargetBlank extends HTMLPurifier_AttrTransform
{
private $parser;
public function __construct() {
$this->parser = new HTMLPurifier_URIParser();
}
public function transform($attr, $config, $context) {
if (!isset($attr['href'])) {
return $attr;
}
$url = $this->parser->parse($attr['href']);
$scheme = $url->getSchemeObj($config, $context);
if ($scheme->browsable && !$url->isBenign($config, $context)) {
$attr['target'] = 'blank';
}
return $attr;
}
}
class HTMLPurifier_AttrTransform_Textarea extends HTMLPurifier_AttrTransform
{
public function transform($attr, $config, $context) {
if (!isset($attr['cols'])) $attr['cols'] = '22';
if (!isset($attr['rows'])) $attr['rows'] = '3';
return $attr;
}
}
class HTMLPurifier_ChildDef_Chameleon extends HTMLPurifier_ChildDef
{
public $inline;
public $block;
public $type = 'chameleon';
public function __construct($inline, $block) {
$this->inline = new HTMLPurifier_ChildDef_Optional($inline);
$this->block  = new HTMLPurifier_ChildDef_Optional($block);
$this->elements = $this->block->elements;
}
public function validateChildren($tokens_of_children, $config, $context) {
if ($context->get('IsInline') === false) {
return $this->block->validateChildren(
$tokens_of_children, $config, $context);
} else {
return $this->inline->validateChildren(
$tokens_of_children, $config, $context);
}
}
}
class HTMLPurifier_ChildDef_Custom extends HTMLPurifier_ChildDef
{
public $type = 'custom';
public $allow_empty = false;
public $dtd_regex;
private $_pcre_regex;
public function __construct($dtd_regex) {
$this->dtd_regex = $dtd_regex;
$this->_compileRegex();
}
protected function _compileRegex() {
$raw = str_replace(' ', '', $this->dtd_regex);
if ($raw{0} != '(') {
$raw = "($raw)";
}
$el = '[#a-zA-Z0-9_.-]+';
$reg = $raw;
preg_match_all("/$el/", $reg, $matches);
foreach ($matches[0] as $match) {
$this->elements[$match] = true;
}
$reg = preg_replace("/$el/", '(,\\0)', $reg);
$reg = preg_replace("/([^,(|]\(+),/", '\\1', $reg);
$reg = preg_replace("/,\(/", '(', $reg);
$this->_pcre_regex = $reg;
}
public function validateChildren($tokens_of_children, $config, $context) {
$list_of_children = '';
$nesting = 0; // depth into the nest
foreach ($tokens_of_children as $token) {
if (!empty($token->is_whitespace)) continue;
$is_child = ($nesting == 0); // direct
if ($token instanceof HTMLPurifier_Token_Start) {
$nesting++;
} elseif ($token instanceof HTMLPurifier_Token_End) {
$nesting--;
}
if ($is_child) {
$list_of_children .= $token->name . ',';
}
}
$list_of_children = ',' . rtrim($list_of_children, ',');
$okay =
preg_match(
'/^,?'.$this->_pcre_regex.'$/',
$list_of_children
);
return (bool) $okay;
}
}
class HTMLPurifier_ChildDef_Empty extends HTMLPurifier_ChildDef
{
public $allow_empty = true;
public $type = 'empty';
public function __construct() {}
public function validateChildren($tokens_of_children, $config, $context) {
return array();
}
}
class HTMLPurifier_ChildDef_List extends HTMLPurifier_ChildDef
{
public $type = 'list';
public $elements = array('li'=>true, 'ul'=>true, 'ol'=>true);
public function validateChildren($tokens_of_children, $config, $context) {
$this->whitespace = false;
if (empty($tokens_of_children)) return false;
$result = array();
$nesting = 0;
$all_whitespace = true;
$seen_li = false;
$need_close_li = false;
foreach ($tokens_of_children as $token) {
if (!empty($token->is_whitespace)) {
$result[] = $token;
continue;
}
$all_whitespace = false; // phew, we're not talking about whitespace
if ($nesting == 1 && $need_close_li) {
$result[] = new HTMLPurifier_Token_End('li');
$nesting--;
$need_close_li = false;
}
$is_child = ($nesting == 0);
if ($token instanceof HTMLPurifier_Token_Start) {
$nesting++;
} elseif ($token instanceof HTMLPurifier_Token_End) {
$nesting--;
}
if ($is_child) {
if ($token->name === 'li') {
$seen_li = true;
} elseif ($token->name === 'ul' || $token->name === 'ol') {
$need_close_li = true;
$nesting++;
if (!$seen_li) {
$result[] = new HTMLPurifier_Token_Start('li');
} else {
while(true) {
$t = array_pop($result);
if ($t instanceof HTMLPurifier_Token_End) {
if ($t->name !== 'li') {
trigger_error("Only li present invariant violated in List ChildDef", E_USER_ERROR);
return false;
}
break;
} elseif ($t instanceof HTMLPurifier_Token_Empty) { // bleagh
if ($t->name !== 'li') {
trigger_error("Only li present invariant violated in List ChildDef", E_USER_ERROR);
return false;
}
$result[] = new HTMLPurifier_Token_Start('li', $t->attr, $t->line, $t->col, $t->armor);
break;
} else {
if (!$t->is_whitespace) {
trigger_error("Only whitespace present invariant violated in List ChildDef", E_USER_ERROR);
return false;
}
}
}
}
} else {
$result[] = new HTMLPurifier_Token_Start('li');
$nesting++;
$seen_li = true;
$need_close_li = true;
}
}
$result[] = $token;
}
if ($need_close_li) {
$result[] = new HTMLPurifier_Token_End('li');
}
if (empty($result)) return false;
if ($all_whitespace) {
return false;
}
if ($tokens_of_children == $result) return true;
return $result;
}
}
class HTMLPurifier_ChildDef_Required extends HTMLPurifier_ChildDef
{
public $elements = array();
protected $whitespace = false;
public function __construct($elements) {
if (is_string($elements)) {
$elements = str_replace(' ', '', $elements);
$elements = explode('|', $elements);
}
$keys = array_keys($elements);
if ($keys == array_keys($keys)) {
$elements = array_flip($elements);
foreach ($elements as $i=>$x) {
$elements[$i] = true;
if (empty($i)) unset($elements[$i]); // remove blank
}
}
$this->elements = $elements;
}
public $allow_empty = false;
public $type = 'required';
public function validateChildren($tokens_of_children, $config, $context) {
$this->whitespace = false;
if (empty($tokens_of_children)) return false;
$result = array();
$nesting = 0;
$is_deleting = false;
$pcdata_allowed = isset($this->elements['#PCDATA']);
$all_whitespace = true;
$escape_invalid_children = $config->get('Core.EscapeInvalidChildren');
$gen = new HTMLPurifier_Generator($config, $context);
foreach ($tokens_of_children as $token) {
if (!empty($token->is_whitespace)) {
$result[] = $token;
continue;
}
$all_whitespace = false; // phew, we're not talking about whitespace
$is_child = ($nesting == 0);
if ($token instanceof HTMLPurifier_Token_Start) {
$nesting++;
} elseif ($token instanceof HTMLPurifier_Token_End) {
$nesting--;
}
if ($is_child) {
$is_deleting = false;
if (!isset($this->elements[$token->name])) {
$is_deleting = true;
if ($pcdata_allowed && $token instanceof HTMLPurifier_Token_Text) {
$result[] = $token;
} elseif ($pcdata_allowed && $escape_invalid_children) {
$result[] = new HTMLPurifier_Token_Text(
$gen->generateFromToken($token)
);
}
continue;
}
}
if (!$is_deleting || ($pcdata_allowed && $token instanceof HTMLPurifier_Token_Text)) {
$result[] = $token;
} elseif ($pcdata_allowed && $escape_invalid_children) {
$result[] =
new HTMLPurifier_Token_Text(
$gen->generateFromToken($token)
);
} else {
}
}
if (empty($result)) return false;
if ($all_whitespace) {
$this->whitespace = true;
return false;
}
if ($tokens_of_children == $result) return true;
return $result;
}
}
class HTMLPurifier_ChildDef_Optional extends HTMLPurifier_ChildDef_Required
{
public $allow_empty = true;
public $type = 'optional';
public function validateChildren($tokens_of_children, $config, $context) {
$result = parent::validateChildren($tokens_of_children, $config, $context);
if ($result === false) {
if (empty($tokens_of_children)) return true;
elseif ($this->whitespace) return $tokens_of_children;
else return array();
}
return $result;
}
}
class HTMLPurifier_ChildDef_StrictBlockquote extends HTMLPurifier_ChildDef_Required
{
protected $real_elements;
protected $fake_elements;
public $allow_empty = true;
public $type = 'strictblockquote';
protected $init = false;
public function getAllowedElements($config) {
$this->init($config);
return $this->fake_elements;
}
public function validateChildren($tokens_of_children, $config, $context) {
$this->init($config);
$this->elements = $this->fake_elements;
$result = parent::validateChildren($tokens_of_children, $config, $context);
$this->elements = $this->real_elements;
if ($result === false) return array();
if ($result === true) $result = $tokens_of_children;
$def = $config->getHTMLDefinition();
$block_wrap_start = new HTMLPurifier_Token_Start($def->info_block_wrapper);
$block_wrap_end   = new HTMLPurifier_Token_End(  $def->info_block_wrapper);
$is_inline = false;
$depth = 0;
$ret = array();
foreach ($result as $i=>$token) {
$token = $result[$i];
if (!$is_inline) {
if (!$depth) {
if (
($token instanceof HTMLPurifier_Token_Text && !$token->is_whitespace) ||
(!$token instanceof HTMLPurifier_Token_Text && !isset($this->elements[$token->name]))
) {
$is_inline = true;
$ret[] = $block_wrap_start;
}
}
} else {
if (!$depth) {
if ($token instanceof HTMLPurifier_Token_Start || $token instanceof HTMLPurifier_Token_Empty) {
if (isset($this->elements[$token->name])) {
$ret[] = $block_wrap_end;
$is_inline = false;
}
}
}
}
$ret[] = $token;
if ($token instanceof HTMLPurifier_Token_Start) $depth++;
if ($token instanceof HTMLPurifier_Token_End)   $depth--;
}
if ($is_inline) $ret[] = $block_wrap_end;
return $ret;
}
private function init($config) {
if (!$this->init) {
$def = $config->getHTMLDefinition();
$this->real_elements = $this->elements;
$this->fake_elements = $def->info_content_sets['Flow'];
$this->fake_elements['#PCDATA'] = true;
$this->init = true;
}
}
}
class HTMLPurifier_ChildDef_Table extends HTMLPurifier_ChildDef
{
public $allow_empty = false;
public $type = 'table';
public $elements = array('tr'=>true, 'tbody'=>true, 'thead'=>true,
'tfoot'=>true, 'caption'=>true, 'colgroup'=>true, 'col'=>true);
public function __construct() {}
public function validateChildren($tokens_of_children, $config, $context) {
if (empty($tokens_of_children)) return false;
$tokens_of_children[] = false;
$caption = false;
$thead   = false;
$tfoot   = false;
$cols    = array();
$content = array();
$nesting = 0; // current depth so we can determine nodes
$is_collecting = false; // are we globbing together tokens to package
$collection = array(); // collected nodes
$tag_index = 0; // the first node might be whitespace,
$tbody_mode = false; // if true, then we need to wrap any stray
foreach ($tokens_of_children as $token) {
$is_child = ($nesting == 0);
if ($token === false) {
} elseif ($token instanceof HTMLPurifier_Token_Start) {
$nesting++;
} elseif ($token instanceof HTMLPurifier_Token_End) {
$nesting--;
}
if ($is_collecting) {
if ($is_child) {
switch ($collection[$tag_index]->name) {
case 'tbody':
$tbody_mode = true;
case 'tr':
$content[] = $collection;
break;
case 'caption':
if ($caption !== false) break;
$caption = $collection;
break;
case 'thead':
case 'tfoot':
$tbody_mode = true;
$var = $collection[$tag_index]->name;
if ($$var === false) {
$$var = $collection;
} else {
$collection[$tag_index]->name = 'tbody';
$collection[count($collection)-1]->name = 'tbody';
$content[] = $collection;
}
break;
case 'colgroup':
$cols[] = $collection;
break;
}
$collection = array();
$is_collecting = false;
$tag_index = 0;
} else {
$collection[] = $token;
}
}
if ($token === false) break;
if ($is_child) {
if ($token->name == 'col') {
$cols[] = array_merge($collection, array($token));
$collection = array();
$tag_index = 0;
continue;
}
switch($token->name) {
case 'caption':
case 'colgroup':
case 'thead':
case 'tfoot':
case 'tbody':
case 'tr':
$is_collecting = true;
$collection[] = $token;
continue;
default:
if (!empty($token->is_whitespace)) {
$collection[] = $token;
$tag_index++;
}
continue;
}
}
}
if (empty($content)) return false;
$ret = array();
if ($caption !== false) $ret = array_merge($ret, $caption);
if ($cols !== false)    foreach ($cols as $token_array) $ret = array_merge($ret, $token_array);
if ($thead !== false)   $ret = array_merge($ret, $thead);
if ($tfoot !== false)   $ret = array_merge($ret, $tfoot);
if ($tbody_mode) {
$inside_tbody = false;
foreach ($content as $token_array) {
foreach ($token_array as $t) {
if ($t->name === 'tr' || $t->name === 'tbody') {
break;
}
} // iterator variable carries over
if ($t->name === 'tr') {
if ($inside_tbody) {
$ret = array_merge($ret, $token_array);
} else {
$ret[] = new HTMLPurifier_Token_Start('tbody');
$ret = array_merge($ret, $token_array);
$inside_tbody = true;
}
} elseif ($t->name === 'tbody') {
if ($inside_tbody) {
$ret[] = new HTMLPurifier_Token_End('tbody');
$inside_tbody = false;
$ret = array_merge($ret, $token_array);
} else {
$ret = array_merge($ret, $token_array);
}
} else {
trigger_error("tr/tbody in content invariant failed in Table ChildDef", E_USER_ERROR);
}
}
if ($inside_tbody) {
$ret[] = new HTMLPurifier_Token_End('tbody');
}
} else {
foreach ($content as $token_array) {
$ret = array_merge($ret, $token_array);
}
}
if (!empty($collection) && $is_collecting == false){
$ret = array_merge($ret, $collection);
}
array_pop($tokens_of_children); // remove phantom token
return ($ret === $tokens_of_children) ? true : $ret;
}
}
class HTMLPurifier_DefinitionCache_Decorator extends HTMLPurifier_DefinitionCache
{
public $cache;
public function __construct() {}
public function decorate(&$cache) {
$decorator = $this->copy();
$decorator->cache =& $cache;
$decorator->type  = $cache->type;
return $decorator;
}
public function copy() {
return new HTMLPurifier_DefinitionCache_Decorator();
}
public function add($def, $config) {
return $this->cache->add($def, $config);
}
public function set($def, $config) {
return $this->cache->set($def, $config);
}
public function replace($def, $config) {
return $this->cache->replace($def, $config);
}
public function get($config) {
return $this->cache->get($config);
}
public function remove($config) {
return $this->cache->remove($config);
}
public function flush($config) {
return $this->cache->flush($config);
}
public function cleanup($config) {
return $this->cache->cleanup($config);
}
}
class HTMLPurifier_DefinitionCache_Null extends HTMLPurifier_DefinitionCache
{
public function add($def, $config) {
return false;
}
public function set($def, $config) {
return false;
}
public function replace($def, $config) {
return false;
}
public function remove($config) {
return false;
}
public function get($config) {
return false;
}
public function flush($config) {
return false;
}
public function cleanup($config) {
return false;
}
}
class HTMLPurifier_DefinitionCache_Serializer extends
HTMLPurifier_DefinitionCache
{
public function add($def, $config) {
if (!$this->checkDefType($def)) return;
$file = $this->generateFilePath($config);
if (file_exists($file)) return false;
if (!$this->_prepareDir($config)) return false;
return $this->_write($file, serialize($def), $config);
}
public function set($def, $config) {
if (!$this->checkDefType($def)) return;
$file = $this->generateFilePath($config);
if (!$this->_prepareDir($config)) return false;
return $this->_write($file, serialize($def), $config);
}
public function replace($def, $config) {
if (!$this->checkDefType($def)) return;
$file = $this->generateFilePath($config);
if (!file_exists($file)) return false;
if (!$this->_prepareDir($config)) return false;
return $this->_write($file, serialize($def), $config);
}
public function get($config) {
$file = $this->generateFilePath($config);
if (!file_exists($file)) return false;
return unserialize(file_get_contents($file));
}
public function remove($config) {
$file = $this->generateFilePath($config);
if (!file_exists($file)) return false;
return unlink($file);
}
public function flush($config) {
if (!$this->_prepareDir($config)) return false;
$dir = $this->generateDirectoryPath($config);
$dh  = opendir($dir);
while (false !== ($filename = readdir($dh))) {
if (empty($filename)) continue;
if ($filename[0] === '.') continue;
unlink($dir . '/' . $filename);
}
}
public function cleanup($config) {
if (!$this->_prepareDir($config)) return false;
$dir = $this->generateDirectoryPath($config);
$dh  = opendir($dir);
while (false !== ($filename = readdir($dh))) {
if (empty($filename)) continue;
if ($filename[0] === '.') continue;
$key = substr($filename, 0, strlen($filename) - 4);
if ($this->isOld($key, $config)) unlink($dir . '/' . $filename);
}
}
public function generateFilePath($config) {
$key = $this->generateKey($config);
return $this->generateDirectoryPath($config) . '/' . $key . '.ser';
}
public function generateDirectoryPath($config) {
$base = $this->generateBaseDirectoryPath($config);
return $base . '/' . $this->type;
}
public function generateBaseDirectoryPath($config) {
$base = $config->get('Cache.SerializerPath');
$base = is_null($base) ? HTMLPURIFIER_PREFIX . '/HTMLPurifier/DefinitionCache/Serializer' : $base;
return $base;
}
private function _write($file, $data, $config) {
$result = file_put_contents($file, $data);
if ($result !== false) {
$chmod = $config->get('Cache.SerializerPermissions');
if (!$chmod) {
$chmod = 0644; // invalid config or simpletest
}
$chmod = $chmod & 0666;
chmod($file, $chmod);
}
return $result;
}
private function _prepareDir($config) {
$directory = $this->generateDirectoryPath($config);
$chmod = $config->get('Cache.SerializerPermissions');
if (!$chmod) {
$chmod = 0755; // invalid config or simpletest
}
if (!is_dir($directory)) {
$base = $this->generateBaseDirectoryPath($config);
if (!is_dir($base)) {
trigger_error('Base directory '.$base.' does not exist,
please create or change using %Cache.SerializerPath',
E_USER_WARNING);
return false;
} elseif (!$this->_testPermissions($base, $chmod)) {
return false;
}
$old = umask(0000);
mkdir($directory, $chmod);
umask($old);
} elseif (!$this->_testPermissions($directory, $chmod)) {
return false;
}
return true;
}
private function _testPermissions($dir, $chmod) {
if (is_writable($dir)) return true;
if (!is_dir($dir)) {
trigger_error('Directory '.$dir.' does not exist',
E_USER_WARNING);
return false;
}
if (function_exists('posix_getuid')) {
if (fileowner($dir) === posix_getuid()) {
$chmod = $chmod | 0700;
if (chmod($dir, $chmod)) return true;
} elseif (filegroup($dir) === posix_getgid()) {
$chmod = $chmod | 0070;
} else {
$chmod = $chmod | 0777;
}
trigger_error('Directory '.$dir.' not writable, '.
'please chmod to ' . decoct($chmod),
E_USER_WARNING);
} else {
trigger_error('Directory '.$dir.' not writable, '.
'please alter file permissions',
E_USER_WARNING);
}
return false;
}
}
class HTMLPurifier_DefinitionCache_Decorator_Cleanup extends
HTMLPurifier_DefinitionCache_Decorator
{
public $name = 'Cleanup';
public function copy() {
return new HTMLPurifier_DefinitionCache_Decorator_Cleanup();
}
public function add($def, $config) {
$status = parent::add($def, $config);
if (!$status) parent::cleanup($config);
return $status;
}
public function set($def, $config) {
$status = parent::set($def, $config);
if (!$status) parent::cleanup($config);
return $status;
}
public function replace($def, $config) {
$status = parent::replace($def, $config);
if (!$status) parent::cleanup($config);
return $status;
}
public function get($config) {
$ret = parent::get($config);
if (!$ret) parent::cleanup($config);
return $ret;
}
}
class HTMLPurifier_DefinitionCache_Decorator_Memory extends
HTMLPurifier_DefinitionCache_Decorator
{
protected $definitions;
public $name = 'Memory';
public function copy() {
return new HTMLPurifier_DefinitionCache_Decorator_Memory();
}
public function add($def, $config) {
$status = parent::add($def, $config);
if ($status) $this->definitions[$this->generateKey($config)] = $def;
return $status;
}
public function set($def, $config) {
$status = parent::set($def, $config);
if ($status) $this->definitions[$this->generateKey($config)] = $def;
return $status;
}
public function replace($def, $config) {
$status = parent::replace($def, $config);
if ($status) $this->definitions[$this->generateKey($config)] = $def;
return $status;
}
public function get($config) {
$key = $this->generateKey($config);
if (isset($this->definitions[$key])) return $this->definitions[$key];
$this->definitions[$key] = parent::get($config);
return $this->definitions[$key];
}
}
class HTMLPurifier_HTMLModule_Bdo extends HTMLPurifier_HTMLModule
{
public $name = 'Bdo';
public $attr_collections = array(
'I18N'=>array('dir'=>false)
);
public function setup($config) {
$bdo = $this->addElement(
'bdo', 'Inline', 'Inline', array('Core', 'Lang'),
array(
'dir'=>'Enum#ltr,rtl', // required
)
);
$bdo->attr_transform_post['required-dir'] = new HTMLPurifier_AttrTransform_BdoDir();
$this->attr_collections['I18N']['dir'] = 'Enum#ltr,rtl';
}
}
class HTMLPurifier_HTMLModule_CommonAttributes extends HTMLPurifier_HTMLModule
{
public $name = 'CommonAttributes';
public $attr_collections = array(
'Core'=>array(
0=>array('Style'),
'class'=>'Class',
'id'=>'ID',
'title'=>'CDATA',
),
'Lang'=>array(),
'I18N'=>array(
0=>array('Lang'), // proprietary, for xml:lang/lang
),
'Common'=>array(
0=>array('Core', 'I18N')
)
);
}
class HTMLPurifier_HTMLModule_Edit extends HTMLPurifier_HTMLModule
{
public $name = 'Edit';
public function setup($config) {
$contents = 'Chameleon: #PCDATA | Inline ! #PCDATA | Flow';
$attr = array(
'cite'=>'URI',
);
$this->addElement('del', 'Inline', $contents, 'Common', $attr);
$this->addElement('ins', 'Inline', $contents, 'Common', $attr);
}
public $defines_child_def = true;
public function getChildDef($def) {
if ($def->content_model_type != 'chameleon') return false;
$value = explode('!', $def->content_model);
return new HTMLPurifier_ChildDef_Chameleon($value[0], $value[1]);
}
}
class HTMLPurifier_HTMLModule_Forms extends HTMLPurifier_HTMLModule
{
public $name = 'Forms';
public $safe = false;
public $content_sets = array(
'Block'=>'Form',
'Inline'=>'Formctrl',
);
public function setup($config) {
$form = $this->addElement('form', 'Form',
'Required: Heading | List | Block | fieldset', 'Common', array(
'accept'=>'ContentTypes',
'accept-charset'=>'Charsets',
'action*'=>'URI',
'method'=>'Enum#get,post',
'enctype'=>'Enum#application/x-www-form-urlencoded,multipart/form-data',
));
$form->excludes = array('form'=>true);
$input = $this->addElement('input', 'Formctrl', 'Empty', 'Common', array(
'accept'=>'ContentTypes',
'accesskey'=>'Character',
'alt'=>'Text',
'checked'=>'Bool#checked',
'disabled'=>'Bool#disabled',
'maxlength'=>'Number',
'name'=>'CDATA',
'readonly'=>'Bool#readonly',
'size'=>'Number',
'src'=>'URI#embedded',
'tabindex'=>'Number',
'type'=>'Enum#text,password,checkbox,button,radio,submit,reset,file,hidden,image',
'value'=>'CDATA',
));
$input->attr_transform_post[] = new HTMLPurifier_AttrTransform_Input();
$this->addElement('select', 'Formctrl', 'Required: optgroup | option', 'Common', array(
'disabled'=>'Bool#disabled',
'multiple'=>'Bool#multiple',
'name'=>'CDATA',
'size'=>'Number',
'tabindex'=>'Number',
));
$this->addElement('option', false, 'Optional: #PCDATA', 'Common', array(
'disabled'=>'Bool#disabled',
'label'=>'Text',
'selected'=>'Bool#selected',
'value'=>'CDATA',
));
$textarea = $this->addElement('textarea', 'Formctrl', 'Optional: #PCDATA', 'Common', array(
'accesskey'=>'Character',
'cols*'=>'Number',
'disabled'=>'Bool#disabled',
'name'=>'CDATA',
'readonly'=>'Bool#readonly',
'rows*'=>'Number',
'tabindex'=>'Number',
));
$textarea->attr_transform_pre[] = new HTMLPurifier_AttrTransform_Textarea();
$button = $this->addElement('button', 'Formctrl', 'Optional: #PCDATA | Heading | List | Block | Inline', 'Common', array(
'accesskey'=>'Character',
'disabled'=>'Bool#disabled',
'name'=>'CDATA',
'tabindex'=>'Number',
'type'=>'Enum#button,submit,reset',
'value'=>'CDATA',
));
$button->excludes = $this->makeLookup(
'form', 'fieldset', // Form
'input', 'select', 'textarea', 'label', 'button', // Formctrl
'a', // as per HTML 4.01 spec, this is omitted by modularization
'isindex', 'iframe' // legacy items
);
$this->addElement('fieldset', 'Form', 'Custom: (#WS?,legend,(Flow|#PCDATA)*)', 'Common');
$label = $this->addElement('label', 'Formctrl', 'Optional: #PCDATA | Inline', 'Common', array(
'accesskey'=>'Character',
));
$label->excludes = array('label'=>true);
$this->addElement('legend', false, 'Optional: #PCDATA | Inline', 'Common', array(
'accesskey'=>'Character',
));
$this->addElement('optgroup', false, 'Required: option', 'Common', array(
'disabled'=>'Bool#disabled',
'label*'=>'Text',
));
}
}
class HTMLPurifier_HTMLModule_Hypertext extends HTMLPurifier_HTMLModule
{
public $name = 'Hypertext';
public function setup($config) {
$a = $this->addElement(
'a', 'Inline', 'Inline', 'Common',
array(
'href'=>'URI',
'rel'=>new HTMLPurifier_AttrDef_HTML_LinkTypes('rel'),
'rev'=>new HTMLPurifier_AttrDef_HTML_LinkTypes('rev'),
)
);
$a->formatting = true;
$a->excludes = array('a'=>true);
}
}
class HTMLPurifier_HTMLModule_Iframe extends HTMLPurifier_HTMLModule
{
public $name = 'Iframe';
public $safe = false;
public function setup($config) {
if ($config->get('HTML.SafeIframe')) {
$this->safe = true;
}
$this->addElement(
'iframe', 'Inline', 'Flow', 'Common',
array(
'src'=>'URI#embedded',
'width'=>'Length',
'height'=>'Length',
'name'=>'ID',
'scrolling'=>'Enum#yes,no,auto',
'frameborder'=>'Enum#0,1',
'longdesc'=>'URI',
'marginheight'=>'Pixels',
'marginwidth'=>'Pixels',
)
);
}
}
class HTMLPurifier_HTMLModule_Image extends HTMLPurifier_HTMLModule
{
public $name = 'Image';
public function setup($config) {
$max = $config->get('HTML.MaxImgLength');
$img = $this->addElement(
'img', 'Inline', 'Empty', 'Common',
array(
'alt*'=>'Text',
'height'=>'Pixels#' . $max,
'width'=>'Pixels#' . $max,
'longdesc'=>'URI',
'src*'=>new HTMLPurifier_AttrDef_URI(true), // embedded
)
);
if ($max === null || $config->get('HTML.Trusted')) {
$img->attr['height'] =
$img->attr['width'] = 'Length';
}
$img->attr_transform_pre[] =
$img->attr_transform_post[] =
new HTMLPurifier_AttrTransform_ImgRequired();
}
}
class HTMLPurifier_HTMLModule_Legacy extends HTMLPurifier_HTMLModule
{
public $name = 'Legacy';
public function setup($config) {
$this->addElement('basefont', 'Inline', 'Empty', false, array(
'color'=>'Color',
'face'=>'Text', // extremely broad, we should
'size'=>'Text', // tighten it
'id'=>'ID'
));
$this->addElement('center', 'Block', 'Flow', 'Common');
$this->addElement('dir', 'Block', 'Required: li', 'Common', array(
'compact'=>'Bool#compact'
));
$this->addElement('font', 'Inline', 'Inline', array('Core', 'I18N'), array(
'color'=>'Color',
'face'=>'Text', // extremely broad, we should
'size'=>'Text', // tighten it
));
$this->addElement('menu', 'Block', 'Required: li', 'Common', array(
'compact'=>'Bool#compact'
));
$s = $this->addElement('s', 'Inline', 'Inline', 'Common');
$s->formatting = true;
$strike = $this->addElement('strike', 'Inline', 'Inline', 'Common');
$strike->formatting = true;
$u = $this->addElement('u', 'Inline', 'Inline', 'Common');
$u->formatting = true;
$align = 'Enum#left,right,center,justify';
$address = $this->addBlankElement('address');
$address->content_model = 'Inline | #PCDATA | p';
$address->content_model_type = 'optional';
$address->child = false;
$blockquote = $this->addBlankElement('blockquote');
$blockquote->content_model = 'Flow | #PCDATA';
$blockquote->content_model_type = 'optional';
$blockquote->child = false;
$br = $this->addBlankElement('br');
$br->attr['clear'] = 'Enum#left,all,right,none';
$caption = $this->addBlankElement('caption');
$caption->attr['align'] = 'Enum#top,bottom,left,right';
$div = $this->addBlankElement('div');
$div->attr['align'] = $align;
$dl = $this->addBlankElement('dl');
$dl->attr['compact'] = 'Bool#compact';
for ($i = 1; $i <= 6; $i++) {
$h = $this->addBlankElement("h$i");
$h->attr['align'] = $align;
}
$hr = $this->addBlankElement('hr');
$hr->attr['align'] = $align;
$hr->attr['noshade'] = 'Bool#noshade';
$hr->attr['size'] = 'Pixels';
$hr->attr['width'] = 'Length';
$img = $this->addBlankElement('img');
$img->attr['align'] = 'IAlign';
$img->attr['border'] = 'Pixels';
$img->attr['hspace'] = 'Pixels';
$img->attr['vspace'] = 'Pixels';
$li = $this->addBlankElement('li');
$li->attr['value'] = new HTMLPurifier_AttrDef_Integer();
$li->attr['type']  = 'Enum#s:1,i,I,a,A,disc,square,circle';
$ol = $this->addBlankElement('ol');
$ol->attr['compact'] = 'Bool#compact';
$ol->attr['start'] = new HTMLPurifier_AttrDef_Integer();
$ol->attr['type'] = 'Enum#s:1,i,I,a,A';
$p = $this->addBlankElement('p');
$p->attr['align'] = $align;
$pre = $this->addBlankElement('pre');
$pre->attr['width'] = 'Number';
$table = $this->addBlankElement('table');
$table->attr['align'] = 'Enum#left,center,right';
$table->attr['bgcolor'] = 'Color';
$tr = $this->addBlankElement('tr');
$tr->attr['bgcolor'] = 'Color';
$th = $this->addBlankElement('th');
$th->attr['bgcolor'] = 'Color';
$th->attr['height'] = 'Length';
$th->attr['nowrap'] = 'Bool#nowrap';
$th->attr['width'] = 'Length';
$td = $this->addBlankElement('td');
$td->attr['bgcolor'] = 'Color';
$td->attr['height'] = 'Length';
$td->attr['nowrap'] = 'Bool#nowrap';
$td->attr['width'] = 'Length';
$ul = $this->addBlankElement('ul');
$ul->attr['compact'] = 'Bool#compact';
$ul->attr['type'] = 'Enum#square,disc,circle';
$form = $this->addBlankElement('form');
$form->content_model = 'Flow | #PCDATA';
$form->content_model_type = 'optional';
$form->attr['target'] = 'FrameTarget';
$input = $this->addBlankElement('input');
$input->attr['align'] = 'IAlign';
$legend = $this->addBlankElement('legend');
$legend->attr['align'] = 'LAlign';
}
}
class HTMLPurifier_HTMLModule_List extends HTMLPurifier_HTMLModule
{
public $name = 'List';
public $content_sets = array('Flow'=>'List');
public function setup($config) {
$ol = $this->addElement('ol', 'List', new HTMLPurifier_ChildDef_List(), 'Common');
$ul = $this->addElement('ul', 'List', new HTMLPurifier_ChildDef_List(), 'Common');
$ol->wrap = 'li';
$ul->wrap = 'li';
$this->addElement('dl', 'List', 'Required: dt | dd', 'Common');
$this->addElement('li', false, 'Flow', 'Common');
$this->addElement('dd', false, 'Flow', 'Common');
$this->addElement('dt', false, 'Inline', 'Common');
}
}
class HTMLPurifier_HTMLModule_Name extends HTMLPurifier_HTMLModule
{
public $name = 'Name';
public function setup($config) {
$elements = array('a', 'applet', 'form', 'frame', 'iframe', 'img', 'map');
foreach ($elements as $name) {
$element = $this->addBlankElement($name);
$element->attr['name'] = 'CDATA';
if (!$config->get('HTML.Attr.Name.UseCDATA')) {
$element->attr_transform_post['NameSync'] = new HTMLPurifier_AttrTransform_NameSync();
}
}
}
}
class HTMLPurifier_HTMLModule_Nofollow extends HTMLPurifier_HTMLModule
{
public $name = 'Nofollow';
public function setup($config) {
$a = $this->addBlankElement('a');
$a->attr_transform_post[] = new HTMLPurifier_AttrTransform_Nofollow();
}
}
class HTMLPurifier_HTMLModule_NonXMLCommonAttributes extends HTMLPurifier_HTMLModule
{
public $name = 'NonXMLCommonAttributes';
public $attr_collections = array(
'Lang'=>array(
'lang'=>'LanguageCode',
)
);
}
class HTMLPurifier_HTMLModule_Object extends HTMLPurifier_HTMLModule
{
public $name = 'Object';
public $safe = false;
public function setup($config) {
$this->addElement('object', 'Inline', 'Optional: #PCDATA | Flow | param', 'Common',
array(
'archive'=>'URI',
'classid'=>'URI',
'codebase'=>'URI',
'codetype'=>'Text',
'data'=>'URI',
'declare'=>'Bool#declare',
'height'=>'Length',
'name'=>'CDATA',
'standby'=>'Text',
'tabindex'=>'Number',
'type'=>'ContentType',
'width'=>'Length'
)
);
$this->addElement('param', false, 'Empty', false,
array(
'id'=>'ID',
'name*'=>'Text',
'type'=>'Text',
'value'=>'Text',
'valuetype'=>'Enum#data,ref,object'
)
);
}
}
class HTMLPurifier_HTMLModule_Presentation extends HTMLPurifier_HTMLModule
{
public $name = 'Presentation';
public function setup($config) {
$this->addElement('hr',     'Block',  'Empty',  'Common');
$this->addElement('sub',    'Inline', 'Inline', 'Common');
$this->addElement('sup',    'Inline', 'Inline', 'Common');
$b = $this->addElement('b',      'Inline', 'Inline', 'Common');
$b->formatting = true;
$big = $this->addElement('big',    'Inline', 'Inline', 'Common');
$big->formatting = true;
$i = $this->addElement('i',      'Inline', 'Inline', 'Common');
$i->formatting = true;
$small = $this->addElement('small',  'Inline', 'Inline', 'Common');
$small->formatting = true;
$tt = $this->addElement('tt',     'Inline', 'Inline', 'Common');
$tt->formatting = true;
}
}
class HTMLPurifier_HTMLModule_Proprietary extends HTMLPurifier_HTMLModule
{
public $name = 'Proprietary';
public function setup($config) {
$this->addElement('marquee', 'Inline', 'Flow', 'Common',
array(
'direction'=>'Enum#left,right,up,down',
'behavior'=>'Enum#alternate',
'width'=>'Length',
'height'=>'Length',
'scrolldelay'=>'Number',
'scrollamount'=>'Number',
'loop'=>'Number',
'bgcolor'=>'Color',
'hspace'=>'Pixels',
'vspace'=>'Pixels',
)
);
}
}
class HTMLPurifier_HTMLModule_Ruby extends HTMLPurifier_HTMLModule
{
public $name = 'Ruby';
public function setup($config) {
$this->addElement('ruby', 'Inline',
'Custom: ((rb, (rt | (rp, rt, rp))) | (rbc, rtc, rtc?))',
'Common');
$this->addElement('rbc', false, 'Required: rb', 'Common');
$this->addElement('rtc', false, 'Required: rt', 'Common');
$rb = $this->addElement('rb', false, 'Inline', 'Common');
$rb->excludes = array('ruby'=>true);
$rt = $this->addElement('rt', false, 'Inline', 'Common', array('rbspan'=>'Number'));
$rt->excludes = array('ruby'=>true);
$this->addElement('rp', false, 'Optional: #PCDATA', 'Common');
}
}
class HTMLPurifier_HTMLModule_SafeEmbed extends HTMLPurifier_HTMLModule
{
public $name = 'SafeEmbed';
public function setup($config) {
$max = $config->get('HTML.MaxImgLength');
$embed = $this->addElement(
'embed', 'Inline', 'Empty', 'Common',
array(
'src*'=>'URI#embedded',
'type'=>'Enum#application/x-shockwave-flash',
'width'=>'Pixels#' . $max,
'height'=>'Pixels#' . $max,
'allowscriptaccess'=>'Enum#never',
'allownetworking'=>'Enum#internal',
'flashvars'=>'Text',
'wmode'=>'Enum#window,transparent,opaque',
'name'=>'ID',
)
);
$embed->attr_transform_post[] = new HTMLPurifier_AttrTransform_SafeEmbed();
}
}
class HTMLPurifier_HTMLModule_SafeObject extends HTMLPurifier_HTMLModule
{
public $name = 'SafeObject';
public function setup($config) {
$max = $config->get('HTML.MaxImgLength');
$object = $this->addElement(
'object',
'Inline',
'Optional: param | Flow | #PCDATA',
'Common',
array(
'type'=>'Enum#application/x-shockwave-flash',
'width'=>'Pixels#' . $max,
'height'=>'Pixels#' . $max,
'data'=>'URI#embedded',
'codebase'=>new HTMLPurifier_AttrDef_Enum(array(
'http://download.macromedia.com/pub/shockwave/cabs/flash/swflash.cab#version=6,0,40,0')),
)
);
$object->attr_transform_post[] = new HTMLPurifier_AttrTransform_SafeObject();
$param = $this->addElement('param', false, 'Empty', false,
array(
'id'=>'ID',
'name*'=>'Text',
'value'=>'Text'
)
);
$param->attr_transform_post[] = new HTMLPurifier_AttrTransform_SafeParam();
$this->info_injector[] = 'SafeObject';
}
}
class HTMLPurifier_HTMLModule_Scripting extends HTMLPurifier_HTMLModule
{
public $name = 'Scripting';
public $elements = array('script', 'noscript');
public $content_sets = array('Block'=>'script | noscript', 'Inline'=>'script | noscript');
public $safe = false;
public function setup($config) {
$this->info['noscript'] = new HTMLPurifier_ElementDef();
$this->info['noscript']->attr = array( 0=>array('Common') );
$this->info['noscript']->content_model = 'Heading | List | Block';
$this->info['noscript']->content_model_type = 'required';
$this->info['script'] = new HTMLPurifier_ElementDef();
$this->info['script']->attr = array(
'defer'=>new HTMLPurifier_AttrDef_Enum(array('defer')),
'src'=>new HTMLPurifier_AttrDef_URI(true),
'type'=>new HTMLPurifier_AttrDef_Enum(array('text/javascript'))
);
$this->info['script']->content_model = '#PCDATA';
$this->info['script']->content_model_type = 'optional';
$this->info['script']->attr_transform_pre['type'] =
$this->info['script']->attr_transform_post['type'] =
new HTMLPurifier_AttrTransform_ScriptRequired();
}
}
class HTMLPurifier_HTMLModule_StyleAttribute extends HTMLPurifier_HTMLModule
{
public $name = 'StyleAttribute';
public $attr_collections = array(
'Style'=>array('style'=>false), // see constructor
'Core'=>array(0=>array('Style'))
);
public function setup($config) {
$this->attr_collections['Style']['style'] = new HTMLPurifier_AttrDef_CSS();
}
}
class HTMLPurifier_HTMLModule_Tables extends HTMLPurifier_HTMLModule
{
public $name = 'Tables';
public function setup($config) {
$this->addElement('caption', false, 'Inline', 'Common');
$this->addElement('table', 'Block',
new HTMLPurifier_ChildDef_Table(),  'Common',
array(
'border'=>'Pixels',
'cellpadding'=>'Length',
'cellspacing'=>'Length',
'frame'=>'Enum#void,above,below,hsides,lhs,rhs,vsides,box,border',
'rules'=>'Enum#none,groups,rows,cols,all',
'summary'=>'Text',
'width'=>'Length'
)
);
$cell_align = array(
'align'=>'Enum#left,center,right,justify,char',
'charoff'=>'Length',
'valign'=>'Enum#top,middle,bottom,baseline',
);
$cell_t = array_merge(
array(
'abbr'=>'Text',
'colspan'=>'Number',
'rowspan'=>'Number',
'scope'=>'Enum#row,col,rowgroup,colgroup',
),
$cell_align
);
$this->addElement('td', false, 'Flow', 'Common', $cell_t);
$this->addElement('th', false, 'Flow', 'Common', $cell_t);
$this->addElement('tr', false, 'Required: td | th', 'Common', $cell_align);
$cell_col = array_merge(
array(
'span'=>'Number',
'width'=>'MultiLength',
),
$cell_align
);
$this->addElement('col',      false, 'Empty',         'Common', $cell_col);
$this->addElement('colgroup', false, 'Optional: col', 'Common', $cell_col);
$this->addElement('tbody', false, 'Required: tr', 'Common', $cell_align);
$this->addElement('thead', false, 'Required: tr', 'Common', $cell_align);
$this->addElement('tfoot', false, 'Required: tr', 'Common', $cell_align);
}
}
class HTMLPurifier_HTMLModule_Target extends HTMLPurifier_HTMLModule
{
public $name = 'Target';
public function setup($config) {
$elements = array('a');
foreach ($elements as $name) {
$e = $this->addBlankElement($name);
$e->attr = array(
'target'=>new HTMLPurifier_AttrDef_HTML_FrameTarget()
);
}
}
}
class HTMLPurifier_HTMLModule_TargetBlank extends HTMLPurifier_HTMLModule
{
public $name = 'TargetBlank';
public function setup($config) {
$a = $this->addBlankElement('a');
$a->attr_transform_post[] = new HTMLPurifier_AttrTransform_TargetBlank();
}
}
class HTMLPurifier_HTMLModule_Text extends HTMLPurifier_HTMLModule
{
public $name = 'Text';
public $content_sets = array(
'Flow'=>'Heading | Block | Inline'
);
public function setup($config) {
$this->addElement('abbr',    'Inline', 'Inline', 'Common');
$this->addElement('acronym', 'Inline', 'Inline', 'Common');
$this->addElement('cite',    'Inline', 'Inline', 'Common');
$this->addElement('dfn',     'Inline', 'Inline', 'Common');
$this->addElement('kbd',     'Inline', 'Inline', 'Common');
$this->addElement('q',       'Inline', 'Inline', 'Common', array('cite'=>'URI'));
$this->addElement('samp',    'Inline', 'Inline', 'Common');
$this->addElement('var',     'Inline', 'Inline', 'Common');
$em = $this->addElement('em',      'Inline', 'Inline', 'Common');
$em->formatting = true;
$strong = $this->addElement('strong',  'Inline', 'Inline', 'Common');
$strong->formatting = true;
$code = $this->addElement('code',    'Inline', 'Inline', 'Common');
$code->formatting = true;
$this->addElement('span', 'Inline', 'Inline', 'Common');
$this->addElement('br',   'Inline', 'Empty',  'Core');
$this->addElement('address',     'Block', 'Inline', 'Common');
$this->addElement('blockquote',  'Block', 'Optional: Heading | Block | List', 'Common', array('cite'=>'URI') );
$pre = $this->addElement('pre', 'Block', 'Inline', 'Common');
$pre->excludes = $this->makeLookup(
'img', 'big', 'small', 'object', 'applet', 'font', 'basefont' );
$this->addElement('h1', 'Heading', 'Inline', 'Common');
$this->addElement('h2', 'Heading', 'Inline', 'Common');
$this->addElement('h3', 'Heading', 'Inline', 'Common');
$this->addElement('h4', 'Heading', 'Inline', 'Common');
$this->addElement('h5', 'Heading', 'Inline', 'Common');
$this->addElement('h6', 'Heading', 'Inline', 'Common');
$p = $this->addElement('p', 'Block', 'Inline', 'Common');
$p->autoclose = array_flip(array("address", "blockquote", "center", "dir", "div", "dl", "fieldset", "ol", "p", "ul"));
$this->addElement('div', 'Block', 'Flow', 'Common');
}
}
class HTMLPurifier_HTMLModule_Tidy extends HTMLPurifier_HTMLModule
{
public $levels = array(0=>'none', 'light', 'medium', 'heavy');
public $defaultLevel = null;
public $fixesForLevel = array(
'light'=>array(),
'medium'=>array(),
'heavy'=>array()
);
public function setup($config) {
$fixes = $this->makeFixes();
$this->makeFixesForLevel($fixes);
$level = $config->get('HTML.TidyLevel');
$fixes_lookup = $this->getFixesForLevel($level);
$add_fixes    = $config->get('HTML.TidyAdd');
$remove_fixes = $config->get('HTML.TidyRemove');
foreach ($fixes as $name=>$fix) {
if (
isset($remove_fixes[$name]) ||
(!isset($add_fixes[$name]) && !isset($fixes_lookup[$name]))
) {
unset($fixes[$name]);
}
}
$this->populate($fixes);
}
public function getFixesForLevel($level) {
if ($level == $this->levels[0]) {
return array();
}
$activated_levels = array();
for ($i = 1, $c = count($this->levels); $i < $c; $i++) {
$activated_levels[] = $this->levels[$i];
if ($this->levels[$i] == $level) break;
}
if ($i == $c) {
trigger_error(
'Tidy level ' . htmlspecialchars($level) . ' not recognized',
E_USER_WARNING
);
return array();
}
$ret = array();
foreach ($activated_levels as $level) {
foreach ($this->fixesForLevel[$level] as $fix) {
$ret[$fix] = true;
}
}
return $ret;
}
public function makeFixesForLevel($fixes) {
if (!isset($this->defaultLevel)) return;
if (!isset($this->fixesForLevel[$this->defaultLevel])) {
trigger_error(
'Default level ' . $this->defaultLevel . ' does not exist',
E_USER_ERROR
);
return;
}
$this->fixesForLevel[$this->defaultLevel] = array_keys($fixes);
}
public function populate($fixes) {
foreach ($fixes as $name=>$fix) {
list($type, $params) = $this->getFixType($name);
switch ($type) {
case 'attr_transform_pre':
case 'attr_transform_post':
$attr = $params['attr'];
if (isset($params['element'])) {
$element = $params['element'];
if (empty($this->info[$element])) {
$e = $this->addBlankElement($element);
} else {
$e = $this->info[$element];
}
} else {
$type = "info_$type";
$e = $this;
}
$f =& $e->$type;
$f[$attr] = $fix;
break;
case 'tag_transform':
$this->info_tag_transform[$params['element']] = $fix;
break;
case 'child':
case 'content_model_type':
$element = $params['element'];
if (empty($this->info[$element])) {
$e = $this->addBlankElement($element);
} else {
$e = $this->info[$element];
}
$e->$type = $fix;
break;
default:
trigger_error("Fix type $type not supported", E_USER_ERROR);
break;
}
}
}
public function getFixType($name) {
$property = $attr = null;
if (strpos($name, '#') !== false) list($name, $property) = explode('#', $name);
if (strpos($name, '@') !== false) list($name, $attr)     = explode('@', $name);
$params = array();
if ($name !== '')    $params['element'] = $name;
if (!is_null($attr)) $params['attr'] = $attr;
if (!is_null($attr)) {
if (is_null($property)) $property = 'pre';
$type = 'attr_transform_' . $property;
return array($type, $params);
}
if (is_null($property)) {
return array('tag_transform', $params);
}
return array($property, $params);
}
public function makeFixes() {}
}
class HTMLPurifier_HTMLModule_XMLCommonAttributes extends HTMLPurifier_HTMLModule
{
public $name = 'XMLCommonAttributes';
public $attr_collections = array(
'Lang'=>array(
'xml:lang'=>'LanguageCode',
)
);
}
class HTMLPurifier_HTMLModule_Tidy_Name extends HTMLPurifier_HTMLModule_Tidy
{
public $name = 'Tidy_Name';
public $defaultLevel = 'heavy';
public function makeFixes() {
$r = array();
$r['img@name'] =
$r['a@name'] = new HTMLPurifier_AttrTransform_Name();
return $r;
}
}
class HTMLPurifier_HTMLModule_Tidy_Proprietary extends HTMLPurifier_HTMLModule_Tidy
{
public $name = 'Tidy_Proprietary';
public $defaultLevel = 'light';
public function makeFixes() {
$r = array();
$r['table@background'] = new HTMLPurifier_AttrTransform_Background();
$r['td@background']    = new HTMLPurifier_AttrTransform_Background();
$r['th@background']    = new HTMLPurifier_AttrTransform_Background();
$r['tr@background']    = new HTMLPurifier_AttrTransform_Background();
$r['thead@background'] = new HTMLPurifier_AttrTransform_Background();
$r['tfoot@background'] = new HTMLPurifier_AttrTransform_Background();
$r['tbody@background'] = new HTMLPurifier_AttrTransform_Background();
$r['table@height']     = new HTMLPurifier_AttrTransform_Length('height');
return $r;
}
}
class HTMLPurifier_HTMLModule_Tidy_XHTMLAndHTML4 extends HTMLPurifier_HTMLModule_Tidy
{
public function makeFixes() {
$r = array();
$r['font']   = new HTMLPurifier_TagTransform_Font();
$r['menu']   = new HTMLPurifier_TagTransform_Simple('ul');
$r['dir']    = new HTMLPurifier_TagTransform_Simple('ul');
$r['center'] = new HTMLPurifier_TagTransform_Simple('div',  'text-align:center;');
$r['u']      = new HTMLPurifier_TagTransform_Simple('span', 'text-decoration:underline;');
$r['s']      = new HTMLPurifier_TagTransform_Simple('span', 'text-decoration:line-through;');
$r['strike'] = new HTMLPurifier_TagTransform_Simple('span', 'text-decoration:line-through;');
$r['caption@align'] =
new HTMLPurifier_AttrTransform_EnumToCSS('align', array(
'left'=>'text-align:left;',
'right'=>'text-align:right;',
'top'=>'caption-side:top;',
'bottom'=>'caption-side:bottom;' // not supported by IE
));
$r['img@align'] =
new HTMLPurifier_AttrTransform_EnumToCSS('align', array(
'left'=>'float:left;',
'right'=>'float:right;',
'top'=>'vertical-align:top;',
'middle'=>'vertical-align:middle;',
'bottom'=>'vertical-align:baseline;',
));
$r['table@align'] =
new HTMLPurifier_AttrTransform_EnumToCSS('align', array(
'left'=>'float:left;',
'center'=>'margin-left:auto;margin-right:auto;',
'right'=>'float:right;'
));
$r['hr@align'] =
new HTMLPurifier_AttrTransform_EnumToCSS('align', array(
'left'=>'margin-left:0;margin-right:auto;text-align:left;',
'center'=>'margin-left:auto;margin-right:auto;text-align:center;',
'right'=>'margin-left:auto;margin-right:0;text-align:right;'
));
$align_lookup = array();
$align_values = array('left', 'right', 'center', 'justify');
foreach ($align_values as $v) $align_lookup[$v] = "text-align:$v;";
$r['h1@align'] =
$r['h2@align'] =
$r['h3@align'] =
$r['h4@align'] =
$r['h5@align'] =
$r['h6@align'] =
$r['p@align']  =
$r['div@align'] =
new HTMLPurifier_AttrTransform_EnumToCSS('align', $align_lookup);
$r['table@bgcolor'] =
$r['td@bgcolor'] =
$r['th@bgcolor'] =
new HTMLPurifier_AttrTransform_BgColor();
$r['img@border'] = new HTMLPurifier_AttrTransform_Border();
$r['br@clear'] =
new HTMLPurifier_AttrTransform_EnumToCSS('clear', array(
'left'=>'clear:left;',
'right'=>'clear:right;',
'all'=>'clear:both;',
'none'=>'clear:none;',
));
$r['td@height'] =
$r['th@height'] =
new HTMLPurifier_AttrTransform_Length('height');
$r['img@hspace'] = new HTMLPurifier_AttrTransform_ImgSpace('hspace');
$r['hr@noshade'] =
new HTMLPurifier_AttrTransform_BoolToCSS(
'noshade',
'color:#808080;background-color:#808080;border:0;'
);
$r['td@nowrap'] =
$r['th@nowrap'] =
new HTMLPurifier_AttrTransform_BoolToCSS(
'nowrap',
'white-space:nowrap;'
);
$r['hr@size'] = new HTMLPurifier_AttrTransform_Length('size', 'height');
$ul_types = array(
'disc'=>'list-style-type:disc;',
'square'=>'list-style-type:square;',
'circle'=>'list-style-type:circle;'
);
$ol_types = array(
'1'=>'list-style-type:decimal;',
'i'=>'list-style-type:lower-roman;',
'I'=>'list-style-type:upper-roman;',
'a'=>'list-style-type:lower-alpha;',
'A'=>'list-style-type:upper-alpha;'
);
$li_types = $ul_types + $ol_types;
$r['ul@type'] = new HTMLPurifier_AttrTransform_EnumToCSS('type', $ul_types);
$r['ol@type'] = new HTMLPurifier_AttrTransform_EnumToCSS('type', $ol_types, true);
$r['li@type'] = new HTMLPurifier_AttrTransform_EnumToCSS('type', $li_types, true);
$r['img@vspace'] = new HTMLPurifier_AttrTransform_ImgSpace('vspace');
$r['td@width'] =
$r['th@width'] =
$r['hr@width'] = new HTMLPurifier_AttrTransform_Length('width');
return $r;
}
}
class HTMLPurifier_HTMLModule_Tidy_Strict extends HTMLPurifier_HTMLModule_Tidy_XHTMLAndHTML4
{
public $name = 'Tidy_Strict';
public $defaultLevel = 'light';
public function makeFixes() {
$r = parent::makeFixes();
$r['blockquote#content_model_type'] = 'strictblockquote';
return $r;
}
public $defines_child_def = true;
public function getChildDef($def) {
if ($def->content_model_type != 'strictblockquote') return parent::getChildDef($def);
return new HTMLPurifier_ChildDef_StrictBlockquote($def->content_model);
}
}
class HTMLPurifier_HTMLModule_Tidy_Transitional extends HTMLPurifier_HTMLModule_Tidy_XHTMLAndHTML4
{
public $name = 'Tidy_Transitional';
public $defaultLevel = 'heavy';
}
class HTMLPurifier_HTMLModule_Tidy_XHTML extends HTMLPurifier_HTMLModule_Tidy
{
public $name = 'Tidy_XHTML';
public $defaultLevel = 'medium';
public function makeFixes() {
$r = array();
$r['@lang'] = new HTMLPurifier_AttrTransform_Lang();
return $r;
}
}
class HTMLPurifier_Injector_AutoParagraph extends HTMLPurifier_Injector
{
public $name = 'AutoParagraph';
public $needed = array('p');
private function _pStart() {
$par = new HTMLPurifier_Token_Start('p');
$par->armor['MakeWellFormed_TagClosedError'] = true;
return $par;
}
public function handleText(&$token) {
$text = $token->data;
if ($this->allowsElement('p')) {
if (empty($this->currentNesting) || strpos($text, "\n\n") !== false) {
$i = $nesting = null;
if (!$this->forwardUntilEndToken($i, $current, $nesting) && $token->is_whitespace) {
} else {
if (!$token->is_whitespace || $this->_isInline($current)) {
$token = array($this->_pStart());
$this->_splitText($text, $token);
} else {
}
}
} else {
if ($this->_pLookAhead()) {
$token = array($this->_pStart(), $token);
} else {
}
}
} elseif (
!empty($this->currentNesting) &&
$this->currentNesting[count($this->currentNesting)-1]->name == 'p'
) {
$token = array();
$this->_splitText($text, $token);
} else {
}
}
public function handleElement(&$token) {
if ($this->allowsElement('p')) {
if (!empty($this->currentNesting)) {
if ($this->_isInline($token)) {
$i = null;
$this->backward($i, $prev);
if (!$prev instanceof HTMLPurifier_Token_Start) {
if (
$prev instanceof HTMLPurifier_Token_Text &&
substr($prev->data, -2) === "\n\n"
) {
$token = array($this->_pStart(), $token);
} else {
}
} else {
if ($this->_pLookAhead()) {
$token = array($this->_pStart(), $token);
} else {
}
}
} else {
}
} else {
if ($this->_isInline($token)) {
$token = array($this->_pStart(), $token);
} else {
}
$i = null;
if ($this->backward($i, $prev)) {
if (
!$prev instanceof HTMLPurifier_Token_Text
) {
if (!is_array($token)) $token = array($token);
array_unshift($token, new HTMLPurifier_Token_Text("\n\n"));
} else {
}
}
}
} else {
}
}
private function _splitText($data, &$result) {
$raw_paragraphs = explode("\n\n", $data);
$paragraphs  = array(); // without empty paragraphs
$needs_start = false;
$needs_end   = false;
$c = count($raw_paragraphs);
if ($c == 1) {
$result[] = new HTMLPurifier_Token_Text($data);
return;
}
for ($i = 0; $i < $c; $i++) {
$par = $raw_paragraphs[$i];
if (trim($par) !== '') {
$paragraphs[] = $par;
} else {
if ($i == 0) {
if (empty($result)) {
$result[] = new HTMLPurifier_Token_End('p');
$result[] = new HTMLPurifier_Token_Text("\n\n");
$needs_start = true;
} else {
array_unshift($result, new HTMLPurifier_Token_Text("\n\n"));
}
} elseif ($i + 1 == $c) {
$needs_end = true;
}
}
}
if (empty($paragraphs)) {
return;
}
if ($needs_start) {
$result[] = $this->_pStart();
}
foreach ($paragraphs as $par) {
$result[] = new HTMLPurifier_Token_Text($par);
$result[] = new HTMLPurifier_Token_End('p');
$result[] = new HTMLPurifier_Token_Text("\n\n");
$result[] = $this->_pStart();
}
array_pop($result);
if (!$needs_end) {
array_pop($result); // removes \n\n
array_pop($result); // removes </p>
}
}
private function _isInline($token) {
return isset($this->htmlDefinition->info['p']->child->elements[$token->name]);
}
private function _pLookAhead() {
$this->current($i, $current);
if ($current instanceof HTMLPurifier_Token_Start) $nesting = 1;
else $nesting = 0;
$ok = false;
while ($this->forwardUntilEndToken($i, $current, $nesting)) {
$result = $this->_checkNeedsP($current);
if ($result !== null) {
$ok = $result;
break;
}
}
return $ok;
}
private function _checkNeedsP($current) {
if ($current instanceof HTMLPurifier_Token_Start){
if (!$this->_isInline($current)) {
return false;
}
} elseif ($current instanceof HTMLPurifier_Token_Text) {
if (strpos($current->data, "\n\n") !== false) {
return true;
} else {
}
}
return null;
}
}
class HTMLPurifier_Injector_DisplayLinkURI extends HTMLPurifier_Injector
{
public $name = 'DisplayLinkURI';
public $needed = array('a');
public function handleElement(&$token) {
}
public function handleEnd(&$token) {
if (isset($token->start->attr['href'])){
$url = $token->start->attr['href'];
unset($token->start->attr['href']);
$token = array($token, new HTMLPurifier_Token_Text(" ($url)"));
} else {
}
}
}
class HTMLPurifier_Injector_Linkify extends HTMLPurifier_Injector
{
public $name = 'Linkify';
public $needed = array('a'=>array('href'));
public function handleText(&$token) {
if (!$this->allowsElement('a')) return;
if (strpos($token->data, '://') === false) {
return;
}
$bits = preg_split('#((?:https?|ftp)://[^\s\'"<>()]+)#S', $token->data, -1, PREG_SPLIT_DELIM_CAPTURE);
$token = array();
for ($i = 0, $c = count($bits), $l = false; $i < $c; $i++, $l = !$l) {
if (!$l) {
if ($bits[$i] === '') continue;
$token[] = new HTMLPurifier_Token_Text($bits[$i]);
} else {
$token[] = new HTMLPurifier_Token_Start('a', array('href'=>$bits[$i]));
$token[] = new HTMLPurifier_Token_Text($bits[$i]);
$token[] = new HTMLPurifier_Token_End('a');
}
}
}
}
class HTMLPurifier_Injector_PurifierLinkify extends HTMLPurifier_Injector
{
public $name = 'PurifierLinkify';
public $docURL;
public $needed = array('a'=>array('href'));
public function prepare($config, $context) {
$this->docURL = $config->get('AutoFormat.PurifierLinkify.DocURL');
return parent::prepare($config, $context);
}
public function handleText(&$token) {
if (!$this->allowsElement('a')) return;
if (strpos($token->data, '%') === false) return;
$bits = preg_split('#%([a-z0-9]+\.[a-z0-9]+)#Si', $token->data, -1, PREG_SPLIT_DELIM_CAPTURE);
$token = array();
for ($i = 0, $c = count($bits), $l = false; $i < $c; $i++, $l = !$l) {
if (!$l) {
if ($bits[$i] === '') continue;
$token[] = new HTMLPurifier_Token_Text($bits[$i]);
} else {
$token[] = new HTMLPurifier_Token_Start('a',
array('href'=>str_replace('%s', $bits[$i], $this->docURL)));
$token[] = new HTMLPurifier_Token_Text('%' . $bits[$i]);
$token[] = new HTMLPurifier_Token_End('a');
}
}
}
}
class HTMLPurifier_Injector_RemoveEmpty extends HTMLPurifier_Injector
{
private $context, $config, $attrValidator, $removeNbsp, $removeNbspExceptions;
public function prepare($config, $context) {
parent::prepare($config, $context);
$this->config = $config;
$this->context = $context;
$this->removeNbsp = $config->get('AutoFormat.RemoveEmpty.RemoveNbsp');
$this->removeNbspExceptions = $config->get('AutoFormat.RemoveEmpty.RemoveNbsp.Exceptions');
$this->attrValidator = new HTMLPurifier_AttrValidator();
}
public function handleElement(&$token) {
if (!$token instanceof HTMLPurifier_Token_Start) return;
$next = false;
for ($i = $this->inputIndex + 1, $c = count($this->inputTokens); $i < $c; $i++) {
$next = $this->inputTokens[$i];
if ($next instanceof HTMLPurifier_Token_Text) {
if ($next->is_whitespace) continue;
if ($this->removeNbsp && !isset($this->removeNbspExceptions[$token->name])) {
$plain = str_replace("\xC2\xA0", "", $next->data);
$isWsOrNbsp = $plain === '' || ctype_space($plain);
if ($isWsOrNbsp) continue;
}
}
break;
}
if (!$next || ($next instanceof HTMLPurifier_Token_End && $next->name == $token->name)) {
if ($token->name == 'colgroup') return;
$this->attrValidator->validateToken($token, $this->config, $this->context);
$token->armor['ValidateAttributes'] = true;
if (isset($token->attr['id']) || isset($token->attr['name'])) return;
$token = $i - $this->inputIndex + 1;
for ($b = $this->inputIndex - 1; $b > 0; $b--) {
$prev = $this->inputTokens[$b];
if ($prev instanceof HTMLPurifier_Token_Text && $prev->is_whitespace) continue;
break;
}
$this->rewind($b - 1);
return;
}
}
}
class HTMLPurifier_Injector_RemoveSpansWithoutAttributes extends HTMLPurifier_Injector
{
public $name = 'RemoveSpansWithoutAttributes';
public $needed = array('span');
private $attrValidator;
private $config;
private $context;
public function prepare($config, $context) {
$this->attrValidator = new HTMLPurifier_AttrValidator();
$this->config = $config;
$this->context = $context;
return parent::prepare($config, $context);
}
public function handleElement(&$token) {
if ($token->name !== 'span' || !$token instanceof HTMLPurifier_Token_Start) {
return;
}
$this->attrValidator->validateToken($token, $this->config, $this->context);
$token->armor['ValidateAttributes'] = true;
if (!empty($token->attr)) {
return;
}
$nesting = 0;
$spanContentTokens = array();
while ($this->forwardUntilEndToken($i, $current, $nesting)) {}
if ($current instanceof HTMLPurifier_Token_End && $current->name === 'span') {
$current->markForDeletion = true;
$token = false;
}
}
public function handleEnd(&$token) {
if ($token->markForDeletion) {
$token = false;
}
}
}
class HTMLPurifier_Injector_SafeObject extends HTMLPurifier_Injector
{
public $name = 'SafeObject';
public $needed = array('object', 'param');
protected $objectStack = array();
protected $paramStack  = array();
protected $addParam = array(
'allowScriptAccess'=>'never',
'allowNetworking'=>'internal',
);
protected $allowedParam = array(
'wmode'=>true,
'movie'=>true,
'flashvars'=>true,
'src'=>true,
'allowFullScreen'=>true, // if omitted, assume to be 'false'
);
public function prepare($config, $context) {
parent::prepare($config, $context);
}
public function handleElement(&$token) {
if ($token->name == 'object') {
$this->objectStack[] = $token;
$this->paramStack[] = array();
$new = array($token);
foreach ($this->addParam as $name=>$value) {
$new[] = new HTMLPurifier_Token_Empty('param', array('name'=>$name, 'value'=>$value));
}
$token = $new;
} elseif ($token->name == 'param') {
$nest = count($this->currentNesting) - 1;
if ($nest >= 0 && $this->currentNesting[$nest]->name === 'object') {
$i = count($this->objectStack) - 1;
if (!isset($token->attr['name'])) {
$token = false;
return;
}
$n = $token->attr['name'];
if (!isset($this->objectStack[$i]->attr['data']) &&
($token->attr['name'] == 'movie' || $token->attr['name'] == 'src')) {
$this->objectStack[$i]->attr['data'] = $token->attr['value'];
}
if (
!isset($this->paramStack[$i][$n]) &&
isset($this->addParam[$n]) &&
$token->attr['name'] === $this->addParam[$n]
) {
$this->paramStack[$i][$n] = true;
} elseif (isset($this->allowedParam[$n])) {
} else {
$token = false;
}
} else {
$token = false;
}
}
}
public function handleEnd(&$token) {
if ($token->name == 'object') {
array_pop($this->objectStack);
array_pop($this->paramStack);
}
}
}
class HTMLPurifier_Lexer_DOMLex extends HTMLPurifier_Lexer
{
private $factory;
public function __construct() {
parent::__construct();
$this->factory = new HTMLPurifier_TokenFactory();
}
public function tokenizeHTML($html, $config, $context) {
$html = $this->normalize($html, $config, $context);
if ($config->get('Core.AggressivelyFixLt')) {
$char = '[^a-z!\/]';
$comment = "/<!--(.*?)(-->|\z)/is";
$html = preg_replace_callback($comment, array($this, 'callbackArmorCommentEntities'), $html);
do {
$old = $html;
$html = preg_replace("/<($char)/i", '&lt;\\1', $html);
} while ($html !== $old);
$html = preg_replace_callback($comment, array($this, 'callbackUndoCommentSubst'), $html); // fix comments
}
$html = $this->wrapHTML($html, $config, $context);
$doc = new DOMDocument();
$doc->encoding = 'UTF-8'; // theoretically, the above has this covered
set_error_handler(array($this, 'muteErrorHandler'));
$doc->loadHTML($html);
restore_error_handler();
$tokens = array();
$this->tokenizeDOM(
$doc->getElementsByTagName('html')->item(0)-> // <html>
getElementsByTagName('body')->item(0)-> //   <body>
getElementsByTagName('div')->item(0)    //     <div>
, $tokens);
return $tokens;
}
protected function tokenizeDOM($node, &$tokens) {
$level = 0;
$nodes = array($level=>array($node));
$closingNodes = array();
do {
while (!empty($nodes[$level])) {
$node = array_shift($nodes[$level]); // FIFO
$collect = $level > 0 ? true : false;
$needEndingTag = $this->createStartNode($node, $tokens, $collect);
if ($needEndingTag) {
$closingNodes[$level][] = $node;
}
if ($node->childNodes && $node->childNodes->length) {
$level++;
$nodes[$level] = array();
foreach ($node->childNodes as $childNode) {
array_push($nodes[$level], $childNode);
}
}
}
$level--;
if ($level && isset($closingNodes[$level])) {
while($node = array_pop($closingNodes[$level])) {
$this->createEndNode($node, $tokens);
}
}
} while ($level > 0);
}
protected function createStartNode($node, &$tokens, $collect) {
if ($node->nodeType === XML_TEXT_NODE) {
$tokens[] = $this->factory->createText($node->data);
return false;
} elseif ($node->nodeType === XML_CDATA_SECTION_NODE) {
$last = end($tokens);
$data = $node->data;
if ($last instanceof HTMLPurifier_Token_Start && ($last->name == 'script' || $last->name == 'style')) {
$new_data = trim($data);
if (substr($new_data, 0, 4) === '<!--') {
$data = substr($new_data, 4);
if (substr($data, -3) === '-->') {
$data = substr($data, 0, -3);
} else {
}
}
}
$tokens[] = $this->factory->createText($this->parseData($data));
return false;
} elseif ($node->nodeType === XML_COMMENT_NODE) {
$tokens[] = $this->factory->createComment($node->data);
return false;
} elseif (
$node->nodeType !== XML_ELEMENT_NODE
) {
return false;
}
$attr = $node->hasAttributes() ? $this->transformAttrToAssoc($node->attributes) : array();
if (!$node->childNodes->length) {
if ($collect) {
$tokens[] = $this->factory->createEmpty($node->tagName, $attr);
}
return false;
} else {
if ($collect) {
$tokens[] = $this->factory->createStart(
$tag_name = $node->tagName, // somehow, it get's dropped
$attr
);
}
return true;
}
}
protected function createEndNode($node, &$tokens) {
$tokens[] = $this->factory->createEnd($node->tagName);
}
protected function transformAttrToAssoc($node_map) {
if ($node_map->length === 0) return array();
$array = array();
foreach ($node_map as $attr) {
$array[$attr->name] = $attr->value;
}
return $array;
}
public function muteErrorHandler($errno, $errstr) {}
public function callbackUndoCommentSubst($matches) {
return '<!--' . strtr($matches[1], array('&amp;'=>'&','&lt;'=>'<')) . $matches[2];
}
public function callbackArmorCommentEntities($matches) {
return '<!--' . str_replace('&', '&amp;', $matches[1]) . $matches[2];
}
protected function wrapHTML($html, $config, $context) {
$def = $config->getDefinition('HTML');
$ret = '';
if (!empty($def->doctype->dtdPublic) || !empty($def->doctype->dtdSystem)) {
$ret .= '<!DOCTYPE html ';
if (!empty($def->doctype->dtdPublic)) $ret .= 'PUBLIC "' . $def->doctype->dtdPublic . '" ';
if (!empty($def->doctype->dtdSystem)) $ret .= '"' . $def->doctype->dtdSystem . '" ';
$ret .= '>';
}
$ret .= '<html><head>';
$ret .= '<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />';
$ret .= '</head><body><div>'.$html.'</div></body></html>';
return $ret;
}
}
class HTMLPurifier_Lexer_DirectLex extends HTMLPurifier_Lexer
{
public $tracksLineNumbers = true;
protected $_whitespace = "\x20\x09\x0D\x0A";
protected function scriptCallback($matches) {
return $matches[1] . htmlspecialchars($matches[2], ENT_COMPAT, 'UTF-8') . $matches[3];
}
public function tokenizeHTML($html, $config, $context) {
if ($config->get('HTML.Trusted')) {
$html = preg_replace_callback('#(<script[^>]*>)(\s*[^<].+?)(</script>)#si',
array($this, 'scriptCallback'), $html);
}
$html = $this->normalize($html, $config, $context);
$cursor = 0; // our location in the text
$inside_tag = false; // whether or not we're parsing the inside of a tag
$array = array(); // result array
$maintain_line_numbers = $config->get('Core.MaintainLineNumbers');
if ($maintain_line_numbers === null) {
$maintain_line_numbers = $config->get('Core.CollectErrors');
}
if ($maintain_line_numbers) {
$current_line = 1;
$current_col  = 0;
$length = strlen($html);
} else {
$current_line = false;
$current_col  = false;
$length = false;
}
$context->register('CurrentLine', $current_line);
$context->register('CurrentCol',  $current_col);
$nl = "\n";
$synchronize_interval = $config->get('Core.DirectLexLineNumberSyncInterval');
$e = false;
if ($config->get('Core.CollectErrors')) {
$e =& $context->get('ErrorCollector');
}
$loops = 0;
while(++$loops) {
if ($maintain_line_numbers) {
$rcursor = $cursor - (int) $inside_tag;
$nl_pos = strrpos($html, $nl, $rcursor - $length);
$current_col = $rcursor - (is_bool($nl_pos) ? 0 : $nl_pos + 1);
if (
$synchronize_interval &&  // synchronization is on
$cursor > 0 &&            // cursor is further than zero
$loops % $synchronize_interval === 0 // time to synchronize!
) {
$current_line = 1 + $this->substrCount($html, $nl, 0, $cursor);
}
}
$position_next_lt = strpos($html, '<', $cursor);
$position_next_gt = strpos($html, '>', $cursor);
if ($position_next_lt === $cursor) {
$inside_tag = true;
$cursor++;
}
if (!$inside_tag && $position_next_lt !== false) {
$token = new
HTMLPurifier_Token_Text(
$this->parseData(
substr(
$html, $cursor, $position_next_lt - $cursor
)
)
);
if ($maintain_line_numbers) {
$token->rawPosition($current_line, $current_col);
$current_line += $this->substrCount($html, $nl, $cursor, $position_next_lt - $cursor);
}
$array[] = $token;
$cursor  = $position_next_lt + 1;
$inside_tag = true;
continue;
} elseif (!$inside_tag) {
if ($cursor === strlen($html)) break;
$token = new
HTMLPurifier_Token_Text(
$this->parseData(
substr(
$html, $cursor
)
)
);
if ($maintain_line_numbers) $token->rawPosition($current_line, $current_col);
$array[] = $token;
break;
} elseif ($inside_tag && $position_next_gt !== false) {
$strlen_segment = $position_next_gt - $cursor;
if ($strlen_segment < 1) {
$token = new HTMLPurifier_Token_Text('<');
$cursor++;
continue;
}
$segment = substr($html, $cursor, $strlen_segment);
if ($segment === false) {
break;
}
if (
substr($segment, 0, 3) === '!--'
) {
$position_comment_end = strpos($html, '-->', $cursor);
if ($position_comment_end === false) {
if ($e) $e->send(E_WARNING, 'Lexer: Unclosed comment');
$position_comment_end = strlen($html);
$end = true;
} else {
$end = false;
}
$strlen_segment = $position_comment_end - $cursor;
$segment = substr($html, $cursor, $strlen_segment);
$token = new
HTMLPurifier_Token_Comment(
substr(
$segment, 3, $strlen_segment - 3
)
);
if ($maintain_line_numbers) {
$token->rawPosition($current_line, $current_col);
$current_line += $this->substrCount($html, $nl, $cursor, $strlen_segment);
}
$array[] = $token;
$cursor = $end ? $position_comment_end : $position_comment_end + 3;
$inside_tag = false;
continue;
}
$is_end_tag = (strpos($segment,'/') === 0);
if ($is_end_tag) {
$type = substr($segment, 1);
$token = new HTMLPurifier_Token_End($type);
if ($maintain_line_numbers) {
$token->rawPosition($current_line, $current_col);
$current_line += $this->substrCount($html, $nl, $cursor, $position_next_gt - $cursor);
}
$array[] = $token;
$inside_tag = false;
$cursor = $position_next_gt + 1;
continue;
}
if (!ctype_alpha($segment[0])) {
if ($e) $e->send(E_NOTICE, 'Lexer: Unescaped lt');
$token = new HTMLPurifier_Token_Text('<');
if ($maintain_line_numbers) {
$token->rawPosition($current_line, $current_col);
$current_line += $this->substrCount($html, $nl, $cursor, $position_next_gt - $cursor);
}
$array[] = $token;
$inside_tag = false;
continue;
}
$is_self_closing = (strrpos($segment,'/') === $strlen_segment-1);
if ($is_self_closing) {
$strlen_segment--;
$segment = substr($segment, 0, $strlen_segment);
}
$position_first_space = strcspn($segment, $this->_whitespace);
if ($position_first_space >= $strlen_segment) {
if ($is_self_closing) {
$token = new HTMLPurifier_Token_Empty($segment);
} else {
$token = new HTMLPurifier_Token_Start($segment);
}
if ($maintain_line_numbers) {
$token->rawPosition($current_line, $current_col);
$current_line += $this->substrCount($html, $nl, $cursor, $position_next_gt - $cursor);
}
$array[] = $token;
$inside_tag = false;
$cursor = $position_next_gt + 1;
continue;
}
$type = substr($segment, 0, $position_first_space);
$attribute_string =
trim(
substr(
$segment, $position_first_space
)
);
if ($attribute_string) {
$attr = $this->parseAttributeString(
$attribute_string
, $config, $context
);
} else {
$attr = array();
}
if ($is_self_closing) {
$token = new HTMLPurifier_Token_Empty($type, $attr);
} else {
$token = new HTMLPurifier_Token_Start($type, $attr);
}
if ($maintain_line_numbers) {
$token->rawPosition($current_line, $current_col);
$current_line += $this->substrCount($html, $nl, $cursor, $position_next_gt - $cursor);
}
$array[] = $token;
$cursor = $position_next_gt + 1;
$inside_tag = false;
continue;
} else {
if ($e) $e->send(E_WARNING, 'Lexer: Missing gt');
$token = new
HTMLPurifier_Token_Text(
'<' .
$this->parseData(
substr($html, $cursor)
)
);
if ($maintain_line_numbers) $token->rawPosition($current_line, $current_col);
$array[] = $token;
break;
}
break;
}
$context->destroy('CurrentLine');
$context->destroy('CurrentCol');
return $array;
}
protected function substrCount($haystack, $needle, $offset, $length) {
static $oldVersion;
if ($oldVersion === null) {
$oldVersion = version_compare(PHP_VERSION, '5.1', '<');
}
if ($oldVersion) {
$haystack = substr($haystack, $offset, $length);
return substr_count($haystack, $needle);
} else {
return substr_count($haystack, $needle, $offset, $length);
}
}
public function parseAttributeString($string, $config, $context) {
$string = (string) $string; // quick typecast
if ($string == '') return array(); // no attributes
$e = false;
if ($config->get('Core.CollectErrors')) {
$e =& $context->get('ErrorCollector');
}
$num_equal = substr_count($string, '=');
$has_space = strpos($string, ' ');
if ($num_equal === 0 && !$has_space) {
return array($string=>$string);
} elseif ($num_equal === 1 && !$has_space) {
list($key, $quoted_value) = explode('=', $string);
$quoted_value = trim($quoted_value);
if (!$key) {
if ($e) $e->send(E_ERROR, 'Lexer: Missing attribute key');
return array();
}
if (!$quoted_value) return array($key=>'');
$first_char = @$quoted_value[0];
$last_char  = @$quoted_value[strlen($quoted_value)-1];
$same_quote = ($first_char == $last_char);
$open_quote = ($first_char == '"' || $first_char == "'");
if ( $same_quote && $open_quote) {
$value = substr($quoted_value, 1, strlen($quoted_value) - 2);
} else {
if ($open_quote) {
if ($e) $e->send(E_ERROR, 'Lexer: Missing end quote');
$value = substr($quoted_value, 1);
} else {
$value = $quoted_value;
}
}
if ($value === false) $value = '';
return array($key=>$this->parseData($value));
}
$array  = array(); // return assoc array of attributes
$cursor = 0; // current position in string (moves forward)
$size   = strlen($string); // size of the string (stays the same)
$string .= ' ';
while(true) {
if ($cursor >= $size) {
break;
}
$cursor += ($value = strspn($string, $this->_whitespace, $cursor));
$key_begin = $cursor; //we're currently at the start of the key
$cursor += strcspn($string, $this->_whitespace . '=', $cursor);
$key_end = $cursor; // now at the end of the key
$key = substr($string, $key_begin, $key_end - $key_begin);
if (!$key) {
if ($e) $e->send(E_ERROR, 'Lexer: Missing attribute key');
$cursor += strcspn($string, $this->_whitespace, $cursor + 1); // prevent infinite loop
continue; // empty key
}
$cursor += strspn($string, $this->_whitespace, $cursor);
if ($cursor >= $size) {
$array[$key] = $key;
break;
}
$first_char = @$string[$cursor];
if ($first_char == '=') {
$cursor++;
$cursor += strspn($string, $this->_whitespace, $cursor);
if ($cursor === false) {
$array[$key] = '';
break;
}
$char = @$string[$cursor];
if ($char == '"' || $char == "'") {
$cursor++;
$value_begin = $cursor;
$cursor = strpos($string, $char, $cursor);
$value_end = $cursor;
} else {
$value_begin = $cursor;
$cursor += strcspn($string, $this->_whitespace, $cursor);
$value_end = $cursor;
}
if ($cursor === false) {
$cursor = $size;
$value_end = $cursor;
}
$value = substr($string, $value_begin, $value_end - $value_begin);
if ($value === false) $value = '';
$array[$key] = $this->parseData($value);
$cursor++;
} else {
if ($key !== '') {
$array[$key] = $key;
} else {
if ($e) $e->send(E_ERROR, 'Lexer: Missing attribute key');
}
}
}
return $array;
}
}
abstract class HTMLPurifier_Strategy_Composite extends HTMLPurifier_Strategy
{
protected $strategies = array();
public function execute($tokens, $config, $context) {
foreach ($this->strategies as $strategy) {
$tokens = $strategy->execute($tokens, $config, $context);
}
return $tokens;
}
}
class HTMLPurifier_Strategy_Core extends HTMLPurifier_Strategy_Composite
{
public function __construct() {
$this->strategies[] = new HTMLPurifier_Strategy_RemoveForeignElements();
$this->strategies[] = new HTMLPurifier_Strategy_MakeWellFormed();
$this->strategies[] = new HTMLPurifier_Strategy_FixNesting();
$this->strategies[] = new HTMLPurifier_Strategy_ValidateAttributes();
}
}
class HTMLPurifier_Strategy_FixNesting extends HTMLPurifier_Strategy
{
public function execute($tokens, $config, $context) {
$definition = $config->getHTMLDefinition();
$parent_name = $definition->info_parent;
array_unshift($tokens, new HTMLPurifier_Token_Start($parent_name));
$tokens[] = new HTMLPurifier_Token_End($parent_name);
$is_inline = $definition->info_parent_def->descendants_are_inline;
$context->register('IsInline', $is_inline);
$e =& $context->get('ErrorCollector', true);
$stack = array();
$exclude_stack = array();
$start_token = false;
$context->register('CurrentToken', $start_token);
for ($i = 0, $size = count($tokens) ; $i < $size; ) {
$child_tokens = array();
for ($j = $i, $depth = 0; ; $j++) {
if ($tokens[$j] instanceof HTMLPurifier_Token_Start) {
$depth++;
if ($depth == 1) continue;
} elseif ($tokens[$j] instanceof HTMLPurifier_Token_End) {
$depth--;
if ($depth == 0) break;
}
$child_tokens[] = $tokens[$j];
}
$start_token = $tokens[$i]; // to make token available via CurrentToken
if ($count = count($stack)) {
$parent_index = $stack[$count-1];
$parent_name  = $tokens[$parent_index]->name;
if ($parent_index == 0) {
$parent_def   = $definition->info_parent_def;
} else {
$parent_def   = $definition->info[$parent_name];
}
} else {
$parent_index = $parent_name = $parent_def = null;
}
if ($is_inline === false) {
if (!empty($parent_def) && $parent_def->descendants_are_inline) {
$is_inline = $count - 1;
}
} else {
if ($count === $is_inline) {
$is_inline = false;
}
}
$excluded = false;
if (!empty($exclude_stack)) {
foreach ($exclude_stack as $lookup) {
if (isset($lookup[$tokens[$i]->name])) {
$excluded = true;
break;
}
}
}
if ($excluded) {
$result = false;
$excludes = array(); // not used, but good to initialize anyway
} else {
if ($i === 0) {
$def = $definition->info_parent_def;
} else {
$def = $definition->info[$tokens[$i]->name];
}
if (!empty($def->child)) {
$result = $def->child->validateChildren(
$child_tokens, $config, $context);
} else {
$result = false;
}
$excludes = $def->excludes;
}
if ($result === true || $child_tokens === $result) {
$stack[] = $i;
if (!empty($excludes)) $exclude_stack[] = $excludes;
$i++;
} elseif($result === false) {
if ($e) {
if ($excluded) {
$e->send(E_ERROR, 'Strategy_FixNesting: Node excluded');
} else {
$e->send(E_ERROR, 'Strategy_FixNesting: Node removed');
}
}
$length = $j - $i + 1;
array_splice($tokens, $i, $length);
$size -= $length;
if (!$parent_def->child->allow_empty) {
$i = $parent_index;
array_pop($stack);
}
} else {
$length = $j - $i - 1;
if ($e) {
if (empty($result) && $length) {
$e->send(E_ERROR, 'Strategy_FixNesting: Node contents removed');
} else {
$e->send(E_WARNING, 'Strategy_FixNesting: Node reorganized');
}
}
array_splice($tokens, $i + 1, $length, $result);
$size -= $length;
$size += count($result);
$stack[] = $i;
if (!empty($excludes)) $exclude_stack[] = $excludes;
$i++;
}
$size = count($tokens);
while ($i < $size and !$tokens[$i] instanceof HTMLPurifier_Token_Start) {
if ($tokens[$i] instanceof HTMLPurifier_Token_End) {
array_pop($stack);
if ($i == 0 || $i == $size - 1) {
$s_excludes = $definition->info_parent_def->excludes;
} else {
$s_excludes = $definition->info[$tokens[$i]->name]->excludes;
}
if ($s_excludes) {
array_pop($exclude_stack);
}
}
$i++;
}
}
array_shift($tokens);
array_pop($tokens);
$context->destroy('IsInline');
$context->destroy('CurrentToken');
return $tokens;
}
}
class HTMLPurifier_Strategy_MakeWellFormed extends HTMLPurifier_Strategy
{
protected $tokens;
protected $t;
protected $stack;
protected $injectors;
protected $config;
protected $context;
public function execute($tokens, $config, $context) {
$definition = $config->getHTMLDefinition();
$generator = new HTMLPurifier_Generator($config, $context);
$escape_invalid_tags = $config->get('Core.EscapeInvalidTags');
$global_parent_allowed_elements = array();
if (isset($definition->info[$definition->info_parent])) {
$global_parent_allowed_elements = $definition->info[$definition->info_parent]->child->getAllowedElements($config);
}
$e = $context->get('ErrorCollector', true);
$t = false; // token index
$i = false; // injector index
$token      = false; // the current token
$reprocess  = false; // whether or not to reprocess the same token
$stack = array();
$this->stack   =& $stack;
$this->t       =& $t;
$this->tokens  =& $tokens;
$this->config  = $config;
$this->context = $context;
$context->register('CurrentNesting', $stack);
$context->register('InputIndex',     $t);
$context->register('InputTokens',    $tokens);
$context->register('CurrentToken',   $token);
$this->injectors = array();
$injectors = $config->getBatch('AutoFormat');
$def_injectors = $definition->info_injector;
$custom_injectors = $injectors['Custom'];
unset($injectors['Custom']); // special case
foreach ($injectors as $injector=>$b) {
if (strpos($injector, '.') !== false) continue;
$injector = "HTMLPurifier_Injector_$injector";
if (!$b) continue;
$this->injectors[] = new $injector;
}
foreach ($def_injectors as $injector) {
$this->injectors[] = $injector;
}
foreach ($custom_injectors as $injector) {
if (!$injector) continue;
if (is_string($injector)) {
$injector = "HTMLPurifier_Injector_$injector";
$injector = new $injector;
}
$this->injectors[] = $injector;
}
foreach ($this->injectors as $ix=>$injector) {
$error = $injector->prepare($config, $context);
if (!$error) continue;
array_splice($this->injectors, $ix, 1); // rm the injector
trigger_error("Cannot enable {$injector->name} injector because $error is not allowed", E_USER_WARNING);
}
for (
$t = 0;
$t == 0 || isset($tokens[$t - 1]);
$reprocess ? $reprocess = false : $t++
) {
if (is_int($i) && $i >= 0) {
$rewind_to = $this->injectors[$i]->getRewind();
if (is_int($rewind_to) && $rewind_to < $t) {
if ($rewind_to < 0) $rewind_to = 0;
while ($t > $rewind_to) {
$t--;
$prev = $tokens[$t];
unset($prev->skip[$i]);
$prev->rewind = $i;
if ($prev instanceof HTMLPurifier_Token_Start) array_pop($this->stack);
elseif ($prev instanceof HTMLPurifier_Token_End) $this->stack[] = $prev->start;
}
}
$i = false;
}
if (!isset($tokens[$t])) {
if (empty($this->stack)) break;
$top_nesting = array_pop($this->stack);
$this->stack[] = $top_nesting;
if ($e && !isset($top_nesting->armor['MakeWellFormed_TagClosedError'])) {
$e->send(E_NOTICE, 'Strategy_MakeWellFormed: Tag closed by document end', $top_nesting);
}
$tokens[] = new HTMLPurifier_Token_End($top_nesting->name);
$reprocess = true;
continue;
}
$token = $tokens[$t];
if (empty($token->is_tag)) {
if ($token instanceof HTMLPurifier_Token_Text) {
foreach ($this->injectors as $i=>$injector) {
if (isset($token->skip[$i])) continue;
if ($token->rewind !== null && $token->rewind !== $i) continue;
$injector->handleText($token);
$this->processToken($token, $i);
$reprocess = true;
break;
}
}
continue;
}
if (isset($definition->info[$token->name])) {
$type = $definition->info[$token->name]->child->type;
} else {
$type = false; // Type is unknown, treat accordingly
}
$ok = false;
if ($type === 'empty' && $token instanceof HTMLPurifier_Token_Start) {
$token = new HTMLPurifier_Token_Empty($token->name, $token->attr, $token->line, $token->col, $token->armor);
$ok = true;
} elseif ($type && $type !== 'empty' && $token instanceof HTMLPurifier_Token_Empty) {
$this->swap(new HTMLPurifier_Token_End($token->name));
$this->insertBefore(new HTMLPurifier_Token_Start($token->name, $token->attr, $token->line, $token->col, $token->armor));
$reprocess = true;
continue;
} elseif ($token instanceof HTMLPurifier_Token_Empty) {
$ok = true;
} elseif ($token instanceof HTMLPurifier_Token_Start) {
if (!empty($this->stack)) {
$parent = array_pop($this->stack);
$this->stack[] = $parent;
if (isset($definition->info[$parent->name])) {
$elements = $definition->info[$parent->name]->child->getAllowedElements($config);
$autoclose = !isset($elements[$token->name]);
} else {
$autoclose = false;
}
if ($autoclose && $definition->info[$token->name]->wrap) {
$wrapname = $definition->info[$token->name]->wrap;
$wrapdef = $definition->info[$wrapname];
$elements = $wrapdef->child->getAllowedElements($config);
$parent_elements = $definition->info[$parent->name]->child->getAllowedElements($config);
if (isset($elements[$token->name]) && isset($parent_elements[$wrapname])) {
$newtoken = new HTMLPurifier_Token_Start($wrapname);
$this->insertBefore($newtoken);
$reprocess = true;
continue;
}
}
$carryover = false;
if ($autoclose && $definition->info[$parent->name]->formatting) {
$carryover = true;
}
if ($autoclose) {
$autoclose_ok = isset($global_parent_allowed_elements[$token->name]);
if (!$autoclose_ok) {
foreach ($this->stack as $ancestor) {
$elements = $definition->info[$ancestor->name]->child->getAllowedElements($config);
if (isset($elements[$token->name])) {
$autoclose_ok = true;
break;
}
if ($definition->info[$token->name]->wrap) {
$wrapname = $definition->info[$token->name]->wrap;
$wrapdef = $definition->info[$wrapname];
$wrap_elements = $wrapdef->child->getAllowedElements($config);
if (isset($wrap_elements[$token->name]) && isset($elements[$wrapname])) {
$autoclose_ok = true;
break;
}
}
}
}
if ($autoclose_ok) {
$new_token = new HTMLPurifier_Token_End($parent->name);
$new_token->start = $parent;
if ($carryover) {
$element = clone $parent;
$element->armor['MakeWellFormed_TagClosedError'] = true;
$element->carryover = true;
$this->processToken(array($new_token, $token, $element));
} else {
$this->insertBefore($new_token);
}
if ($e && !isset($parent->armor['MakeWellFormed_TagClosedError'])) {
if (!$carryover) {
$e->send(E_NOTICE, 'Strategy_MakeWellFormed: Tag auto closed', $parent);
} else {
$e->send(E_NOTICE, 'Strategy_MakeWellFormed: Tag carryover', $parent);
}
}
} else {
$this->remove();
}
$reprocess = true;
continue;
}
}
$ok = true;
}
if ($ok) {
foreach ($this->injectors as $i=>$injector) {
if (isset($token->skip[$i])) continue;
if ($token->rewind !== null && $token->rewind !== $i) continue;
$injector->handleElement($token);
$this->processToken($token, $i);
$reprocess = true;
break;
}
if (!$reprocess) {
$this->swap($token);
if ($token instanceof HTMLPurifier_Token_Start) {
$this->stack[] = $token;
} elseif ($token instanceof HTMLPurifier_Token_End) {
throw new HTMLPurifier_Exception('Improper handling of end tag in start code; possible error in MakeWellFormed');
}
}
continue;
}
if (!$token instanceof HTMLPurifier_Token_End) {
throw new HTMLPurifier_Exception('Unaccounted for tag token in input stream, bug in HTML Purifier');
}
if (empty($this->stack)) {
if ($escape_invalid_tags) {
if ($e) $e->send(E_WARNING, 'Strategy_MakeWellFormed: Unnecessary end tag to text');
$this->swap(new HTMLPurifier_Token_Text(
$generator->generateFromToken($token)
));
} else {
$this->remove();
if ($e) $e->send(E_WARNING, 'Strategy_MakeWellFormed: Unnecessary end tag removed');
}
$reprocess = true;
continue;
}
$current_parent = array_pop($this->stack);
if ($current_parent->name == $token->name) {
$token->start = $current_parent;
foreach ($this->injectors as $i=>$injector) {
if (isset($token->skip[$i])) continue;
if ($token->rewind !== null && $token->rewind !== $i) continue;
$injector->handleEnd($token);
$this->processToken($token, $i);
$this->stack[] = $current_parent;
$reprocess = true;
break;
}
continue;
}
$this->stack[] = $current_parent;
$size = count($this->stack);
$skipped_tags = false;
for ($j = $size - 2; $j >= 0; $j--) {
if ($this->stack[$j]->name == $token->name) {
$skipped_tags = array_slice($this->stack, $j);
break;
}
}
if ($skipped_tags === false) {
if ($escape_invalid_tags) {
$this->swap(new HTMLPurifier_Token_Text(
$generator->generateFromToken($token)
));
if ($e) $e->send(E_WARNING, 'Strategy_MakeWellFormed: Stray end tag to text');
} else {
$this->remove();
if ($e) $e->send(E_WARNING, 'Strategy_MakeWellFormed: Stray end tag removed');
}
$reprocess = true;
continue;
}
$c = count($skipped_tags);
if ($e) {
for ($j = $c - 1; $j > 0; $j--) {
if (!isset($skipped_tags[$j]->armor['MakeWellFormed_TagClosedError'])) {
$e->send(E_NOTICE, 'Strategy_MakeWellFormed: Tag closed by element end', $skipped_tags[$j]);
}
}
}
$replace = array($token);
for ($j = 1; $j < $c; $j++) {
$new_token = new HTMLPurifier_Token_End($skipped_tags[$j]->name);
$new_token->start = $skipped_tags[$j];
array_unshift($replace, $new_token);
if (isset($definition->info[$new_token->name]) && $definition->info[$new_token->name]->formatting) {
$element = clone $skipped_tags[$j];
$element->carryover = true;
$element->armor['MakeWellFormed_TagClosedError'] = true;
$replace[] = $element;
}
}
$this->processToken($replace);
$reprocess = true;
continue;
}
$context->destroy('CurrentNesting');
$context->destroy('InputTokens');
$context->destroy('InputIndex');
$context->destroy('CurrentToken');
unset($this->injectors, $this->stack, $this->tokens, $this->t);
return $tokens;
}
protected function processToken($token, $injector = -1) {
if (is_object($token)) $token = array(1, $token);
if (is_int($token))    $token = array($token);
if ($token === false)  $token = array(1);
if (!is_array($token)) throw new HTMLPurifier_Exception('Invalid token type from injector');
if (!is_int($token[0])) array_unshift($token, 1);
if ($token[0] === 0) throw new HTMLPurifier_Exception('Deleting zero tokens is not valid');
$delete = array_shift($token);
$old = array_splice($this->tokens, $this->t, $delete, $token);
if ($injector > -1) {
$oldskip = isset($old[0]) ? $old[0]->skip : array();
foreach ($token as $object) {
$object->skip = $oldskip;
$object->skip[$injector] = true;
}
}
}
private function insertBefore($token) {
array_splice($this->tokens, $this->t, 0, array($token));
}
private function remove() {
array_splice($this->tokens, $this->t, 1);
}
private function swap($token) {
$this->tokens[$this->t] = $token;
}
}
class HTMLPurifier_Strategy_RemoveForeignElements extends HTMLPurifier_Strategy
{
public function execute($tokens, $config, $context) {
$definition = $config->getHTMLDefinition();
$generator = new HTMLPurifier_Generator($config, $context);
$result = array();
$escape_invalid_tags = $config->get('Core.EscapeInvalidTags');
$remove_invalid_img  = $config->get('Core.RemoveInvalidImg');
$trusted = $config->get('HTML.Trusted');
$comment_lookup = $config->get('HTML.AllowedComments');
$comment_regexp = $config->get('HTML.AllowedCommentsRegexp');
$check_comments = $comment_lookup !== array() || $comment_regexp !== null;
$remove_script_contents = $config->get('Core.RemoveScriptContents');
$hidden_elements     = $config->get('Core.HiddenElements');
if ($remove_script_contents === true) {
$hidden_elements['script'] = true;
} elseif ($remove_script_contents === false && isset($hidden_elements['script'])) {
unset($hidden_elements['script']);
}
$attr_validator = new HTMLPurifier_AttrValidator();
$remove_until = false;
$textify_comments = false;
$token = false;
$context->register('CurrentToken', $token);
$e = false;
if ($config->get('Core.CollectErrors')) {
$e =& $context->get('ErrorCollector');
}
foreach($tokens as $token) {
if ($remove_until) {
if (empty($token->is_tag) || $token->name !== $remove_until) {
continue;
}
}
if (!empty( $token->is_tag )) {
if (
isset($definition->info_tag_transform[$token->name])
) {
$original_name = $token->name;
$token = $definition->
info_tag_transform[$token->name]->
transform($token, $config, $context);
if ($e) $e->send(E_NOTICE, 'Strategy_RemoveForeignElements: Tag transform', $original_name);
}
if (isset($definition->info[$token->name])) {
if (
($token instanceof HTMLPurifier_Token_Start || $token instanceof HTMLPurifier_Token_Empty) &&
$definition->info[$token->name]->required_attr &&
($token->name != 'img' || $remove_invalid_img) // ensure config option still works
) {
$attr_validator->validateToken($token, $config, $context);
$ok = true;
foreach ($definition->info[$token->name]->required_attr as $name) {
if (!isset($token->attr[$name])) {
$ok = false;
break;
}
}
if (!$ok) {
if ($e) $e->send(E_ERROR, 'Strategy_RemoveForeignElements: Missing required attribute', $name);
continue;
}
$token->armor['ValidateAttributes'] = true;
}
if (isset($hidden_elements[$token->name]) && $token instanceof HTMLPurifier_Token_Start) {
$textify_comments = $token->name;
} elseif ($token->name === $textify_comments && $token instanceof HTMLPurifier_Token_End) {
$textify_comments = false;
}
} elseif ($escape_invalid_tags) {
if ($e) $e->send(E_WARNING, 'Strategy_RemoveForeignElements: Foreign element to text');
$token = new HTMLPurifier_Token_Text(
$generator->generateFromToken($token)
);
} else {
if (isset($hidden_elements[$token->name])) {
if ($token instanceof HTMLPurifier_Token_Start) {
$remove_until = $token->name;
} elseif ($token instanceof HTMLPurifier_Token_Empty) {
} else {
$remove_until = false;
}
if ($e) $e->send(E_ERROR, 'Strategy_RemoveForeignElements: Foreign meta element removed');
} else {
if ($e) $e->send(E_ERROR, 'Strategy_RemoveForeignElements: Foreign element removed');
}
continue;
}
} elseif ($token instanceof HTMLPurifier_Token_Comment) {
if ($textify_comments !== false) {
$data = $token->data;
$token = new HTMLPurifier_Token_Text($data);
} elseif ($trusted || $check_comments) {
$trailing_hyphen = false;
if ($e) {
if (substr($token->data, -1) == '-') {
$trailing_hyphen = true;
}
}
$token->data = rtrim($token->data, '-');
$found_double_hyphen = false;
while (strpos($token->data, '--') !== false) {
$found_double_hyphen = true;
$token->data = str_replace('--', '-', $token->data);
}
if ($trusted || !empty($comment_lookup[trim($token->data)]) || ($comment_regexp !== NULL && preg_match($comment_regexp, trim($token->data)))) {
if ($e) {
if ($trailing_hyphen) {
$e->send(E_NOTICE, 'Strategy_RemoveForeignElements: Trailing hyphen in comment removed');
}
if ($found_double_hyphen) {
$e->send(E_NOTICE, 'Strategy_RemoveForeignElements: Hyphens in comment collapsed');
}
}
} else {
if ($e) {
$e->send(E_NOTICE, 'Strategy_RemoveForeignElements: Comment removed');
}
continue;
}
} else {
if ($e) $e->send(E_NOTICE, 'Strategy_RemoveForeignElements: Comment removed');
continue;
}
} elseif ($token instanceof HTMLPurifier_Token_Text) {
} else {
continue;
}
$result[] = $token;
}
if ($remove_until && $e) {
$e->send(E_ERROR, 'Strategy_RemoveForeignElements: Token removed to end', $remove_until);
}
$context->destroy('CurrentToken');
return $result;
}
}
class HTMLPurifier_Strategy_ValidateAttributes extends HTMLPurifier_Strategy
{
public function execute($tokens, $config, $context) {
$validator = new HTMLPurifier_AttrValidator();
$token = false;
$context->register('CurrentToken', $token);
foreach ($tokens as $key=>$token) {
if (!$token instanceof HTMLPurifier_Token_Start && !$token instanceof HTMLPurifier_Token_Empty) continue;
if (!empty($token->armor['ValidateAttributes'])) continue;
$validator->validateToken($token, $config, $context);
$tokens[$key] = $token; // for PHP 4
}
$context->destroy('CurrentToken');
return $tokens;
}
}
class HTMLPurifier_TagTransform_Font extends HTMLPurifier_TagTransform
{
public $transform_to = 'span';
protected $_size_lookup = array(
'0'=>'xx-small',
'1'=>'xx-small',
'2'=>'small',
'3'=>'medium',
'4'=>'large',
'5'=>'x-large',
'6'=>'xx-large',
'7'=>'300%',
'-1'=>'smaller',
'-2'=>'60%',
'+1'=>'larger',
'+2'=>'150%',
'+3'=>'200%',
'+4'=>'300%'
);
public function transform($tag, $config, $context) {
if ($tag instanceof HTMLPurifier_Token_End) {
$new_tag = clone $tag;
$new_tag->name = $this->transform_to;
return $new_tag;
}
$attr = $tag->attr;
$prepend_style = '';
if (isset($attr['color'])) {
$prepend_style .= 'color:' . $attr['color'] . ';';
unset($attr['color']);
}
if (isset($attr['face'])) {
$prepend_style .= 'font-family:' . $attr['face'] . ';';
unset($attr['face']);
}
if (isset($attr['size'])) {
if ($attr['size'] !== '') {
if ($attr['size']{0} == '+' || $attr['size']{0} == '-') {
$size = (int) $attr['size'];
if ($size < -2) $attr['size'] = '-2';
if ($size > 4)  $attr['size'] = '+4';
} else {
$size = (int) $attr['size'];
if ($size > 7) $attr['size'] = '7';
}
}
if (isset($this->_size_lookup[$attr['size']])) {
$prepend_style .= 'font-size:' .
$this->_size_lookup[$attr['size']] . ';';
}
unset($attr['size']);
}
if ($prepend_style) {
$attr['style'] = isset($attr['style']) ?
$prepend_style . $attr['style'] :
$prepend_style;
}
$new_tag = clone $tag;
$new_tag->name = $this->transform_to;
$new_tag->attr = $attr;
return $new_tag;
}
}
class HTMLPurifier_TagTransform_Simple extends HTMLPurifier_TagTransform
{
protected $style;
public function __construct($transform_to, $style = null) {
$this->transform_to = $transform_to;
$this->style = $style;
}
public function transform($tag, $config, $context) {
$new_tag = clone $tag;
$new_tag->name = $this->transform_to;
if (!is_null($this->style) &&
($new_tag instanceof HTMLPurifier_Token_Start || $new_tag instanceof HTMLPurifier_Token_Empty)
) {
$this->prependCSS($new_tag->attr, $this->style);
}
return $new_tag;
}
}
class HTMLPurifier_Token_Comment extends HTMLPurifier_Token
{
public $data; 
public $is_whitespace = true;
public function __construct($data, $line = null, $col = null) {
$this->data = $data;
$this->line = $line;
$this->col  = $col;
}
}
class HTMLPurifier_Token_Tag extends HTMLPurifier_Token
{
public $is_tag = true;
public $name;
public $attr = array();
public function __construct($name, $attr = array(), $line = null, $col = null, $armor = array()) {
$this->name = ctype_lower($name) ? $name : strtolower($name);
foreach ($attr as $key=>$value) {
if (!ctype_lower($key)) {
$new_key = strtolower($key);
if (!isset($attr[$new_key])) {
$attr[$new_key] = $attr[$key];
}
if ($new_key !== $key) {
unset($attr[$key]);
}
}
}
$this->attr = $attr;
$this->line = $line;
$this->col  = $col;
$this->armor = $armor;
}
}
class HTMLPurifier_Token_Empty extends HTMLPurifier_Token_Tag
{
}
class HTMLPurifier_Token_End extends HTMLPurifier_Token_Tag
{
public $start;
}
class HTMLPurifier_Token_Start extends HTMLPurifier_Token_Tag
{
}
class HTMLPurifier_Token_Text extends HTMLPurifier_Token
{
public $name = '#PCDATA'; 
public $data; 
public $is_whitespace; 
public function __construct($data, $line = null, $col = null) {
$this->data = $data;
$this->is_whitespace = ctype_space($data);
$this->line = $line;
$this->col  = $col;
}
}
class HTMLPurifier_URIFilter_DisableExternal extends HTMLPurifier_URIFilter
{
public $name = 'DisableExternal';
protected $ourHostParts = false;
public function prepare($config) {
$our_host = $config->getDefinition('URI')->host;
if ($our_host !== null) $this->ourHostParts = array_reverse(explode('.', $our_host));
}
public function filter(&$uri, $config, $context) {
if (is_null($uri->host)) return true;
if ($this->ourHostParts === false) return false;
$host_parts = array_reverse(explode('.', $uri->host));
foreach ($this->ourHostParts as $i=>$x) {
if (!isset($host_parts[$i])) return false;
if ($host_parts[$i] != $this->ourHostParts[$i]) return false;
}
return true;
}
}
class HTMLPurifier_URIFilter_DisableExternalResources extends HTMLPurifier_URIFilter_DisableExternal
{
public $name = 'DisableExternalResources';
public function filter(&$uri, $config, $context) {
if (!$context->get('EmbeddedURI', true)) return true;
return parent::filter($uri, $config, $context);
}
}
class HTMLPurifier_URIFilter_DisableResources extends HTMLPurifier_URIFilter
{
public $name = 'DisableResources';
public function filter(&$uri, $config, $context) {
return !$context->get('EmbeddedURI', true);
}
}
// do not have canonical forms anymore. As far as I can tell, it's
// not a problem (punycoding should be identity when no Unicode
// points are involved), but I'm not 100% sure
class HTMLPurifier_URIFilter_HostBlacklist extends HTMLPurifier_URIFilter
{
public $name = 'HostBlacklist';
protected $blacklist = array();
public function prepare($config) {
$this->blacklist = $config->get('URI.HostBlacklist');
return true;
}
public function filter(&$uri, $config, $context) {
foreach($this->blacklist as $blacklisted_host_fragment) {
if (strpos($uri->host, $blacklisted_host_fragment) !== false) {
return false;
}
}
return true;
}
}
class HTMLPurifier_URIFilter_MakeAbsolute extends HTMLPurifier_URIFilter
{
public $name = 'MakeAbsolute';
protected $base;
protected $basePathStack = array();
public function prepare($config) {
$def = $config->getDefinition('URI');
$this->base = $def->base;
if (is_null($this->base)) {
trigger_error('URI.MakeAbsolute is being ignored due to lack of value for URI.Base configuration', E_USER_WARNING);
return false;
}
$this->base->fragment = null; // fragment is invalid for base URI
$stack = explode('/', $this->base->path);
array_pop($stack); // discard last segment
$stack = $this->_collapseStack($stack); // do pre-parsing
$this->basePathStack = $stack;
return true;
}
public function filter(&$uri, $config, $context) {
if (is_null($this->base)) return true; // abort early
if (
$uri->path === '' && is_null($uri->scheme) &&
is_null($uri->host) && is_null($uri->query) && is_null($uri->fragment)
) {
$uri = clone $this->base;
return true;
}
if (!is_null($uri->scheme)) {
if (!is_null($uri->host)) return true;
$scheme_obj = $uri->getSchemeObj($config, $context);
if (!$scheme_obj) {
return false;
}
if (!$scheme_obj->hierarchical) {
return true;
}
}
if (!is_null($uri->host)) {
return true;
}
if ($uri->path === '') {
$uri->path = $this->base->path;
} elseif ($uri->path[0] !== '/') {
$stack = explode('/', $uri->path);
$new_stack = array_merge($this->basePathStack, $stack);
if ($new_stack[0] !== '' && !is_null($this->base->host)) {
array_unshift($new_stack, '');
}
$new_stack = $this->_collapseStack($new_stack);
$uri->path = implode('/', $new_stack);
} else {
$uri->path = implode('/', $this->_collapseStack(explode('/', $uri->path)));
}
$uri->scheme = $this->base->scheme;
if (is_null($uri->userinfo)) $uri->userinfo = $this->base->userinfo;
if (is_null($uri->host))     $uri->host     = $this->base->host;
if (is_null($uri->port))     $uri->port     = $this->base->port;
return true;
}
private function _collapseStack($stack) {
$result = array();
$is_folder = false;
for ($i = 0; isset($stack[$i]); $i++) {
$is_folder = false;
if ($stack[$i] == '' && $i && isset($stack[$i+1])) continue;
if ($stack[$i] == '..') {
if (!empty($result)) {
$segment = array_pop($result);
if ($segment === '' && empty($result)) {
$result[] = '';
} elseif ($segment === '..') {
$result[] = '..'; // cannot remove .. with ..
}
} else {
$result[] = '..';
}
$is_folder = true;
continue;
}
if ($stack[$i] == '.') {
$is_folder = true;
continue;
}
$result[] = $stack[$i];
}
if ($is_folder) $result[] = '';
return $result;
}
}
class HTMLPurifier_URIFilter_Munge extends HTMLPurifier_URIFilter
{
public $name = 'Munge';
public $post = true;
private $target, $parser, $doEmbed, $secretKey;
protected $replace = array();
public function prepare($config) {
$this->target    = $config->get('URI.' . $this->name);
$this->parser    = new HTMLPurifier_URIParser();
$this->doEmbed   = $config->get('URI.MungeResources');
$this->secretKey = $config->get('URI.MungeSecretKey');
return true;
}
public function filter(&$uri, $config, $context) {
if ($context->get('EmbeddedURI', true) && !$this->doEmbed) return true;
$scheme_obj = $uri->getSchemeObj($config, $context);
if (!$scheme_obj) return true; // ignore unknown schemes, maybe another postfilter did it
if (!$scheme_obj->browsable) return true; // ignore non-browseable schemes, since we can't munge those in a reasonable way
if ($uri->isBenign($config, $context)) return true; // don't redirect if a benign URL
$this->makeReplace($uri, $config, $context);
$this->replace = array_map('rawurlencode', $this->replace);
$new_uri = strtr($this->target, $this->replace);
$new_uri = $this->parser->parse($new_uri);
if ($uri->host === $new_uri->host) return true;
$uri = $new_uri; // overwrite
return true;
}
protected function makeReplace($uri, $config, $context) {
$string = $uri->toString();
$this->replace['%s'] = $string;
$this->replace['%r'] = $context->get('EmbeddedURI', true);
$token = $context->get('CurrentToken', true);
$this->replace['%n'] = $token ? $token->name : null;
$this->replace['%m'] = $context->get('CurrentAttr', true);
$this->replace['%p'] = $context->get('CurrentCSSProperty', true);
if ($this->secretKey) $this->replace['%t'] = sha1($this->secretKey . ':' . $string);
}
}
class HTMLPurifier_URIFilter_SafeIframe extends HTMLPurifier_URIFilter
{
public $name = 'SafeIframe';
public $always_load = true;
protected $regexp = NULL;
public function prepare($config) {
$this->regexp = $config->get('URI.SafeIframeRegexp');
return true;
}
public function filter(&$uri, $config, $context) {
if (!$config->get('HTML.SafeIframe')) return true;
if (!$context->get('EmbeddedURI', true)) return true;
$token = $context->get('CurrentToken', true);
if (!($token && $token->name == 'iframe')) return true;
if ($this->regexp === null) return false;
return preg_match($this->regexp, $uri->toString());
}
}
class HTMLPurifier_URIScheme_data extends HTMLPurifier_URIScheme {
public $browsable = true;
public $allowed_types = array(
'image/jpeg'=>true,
'image/gif'=>true,
'image/png'=>true,
);
public $may_omit_host = true;
public function doValidate(&$uri, $config, $context) {
$result = explode(',', $uri->path, 2);
$is_base64 = false;
$charset = null;
$content_type = null;
if (count($result) == 2) {
list($metadata, $data) = $result;
$metas = explode(';', $metadata);
while(!empty($metas)) {
$cur = array_shift($metas);
if ($cur == 'base64') {
$is_base64 = true;
break;
}
if (substr($cur, 0, 8) == 'charset=') {
if ($charset !== null) continue; // garbage
$charset = substr($cur, 8); // not used
} else {
if ($content_type !== null) continue; // garbage
$content_type = $cur;
}
}
} else {
$data = $result[0];
}
if ($content_type !== null && empty($this->allowed_types[$content_type])) {
return false;
}
if ($charset !== null) {
$charset = null;
}
$data = rawurldecode($data);
if ($is_base64) {
$raw_data = base64_decode($data);
} else {
$raw_data = $data;
}
$file = tempnam("/tmp", "");
file_put_contents($file, $raw_data);
if (function_exists('exif_imagetype')) {
$image_code = exif_imagetype($file);
} elseif (function_exists('getimagesize')) {
set_error_handler(array($this, 'muteErrorHandler'));
$info = getimagesize($file);
restore_error_handler();
if ($info == false) return false;
$image_code = $info[2];
} else {
trigger_error("could not find exif_imagetype or getimagesize functions", E_USER_ERROR);
}
$real_content_type = image_type_to_mime_type($image_code);
if ($real_content_type != $content_type) {
if (empty($this->allowed_types[$real_content_type])) return false;
$content_type = $real_content_type;
}
$uri->userinfo = null;
$uri->host = null;
$uri->port = null;
$uri->fragment = null;
$uri->query = null;
$uri->path = "$content_type;base64," . base64_encode($raw_data);
return true;
}
public function muteErrorHandler($errno, $errstr) {}
}
class HTMLPurifier_URIScheme_file extends HTMLPurifier_URIScheme {
public $browsable = false;
public $may_omit_host = true;
public function doValidate(&$uri, $config, $context) {
$uri->userinfo = null;
$uri->port     = null;
$uri->query    = null;
return true;
}
}
class HTMLPurifier_URIScheme_ftp extends HTMLPurifier_URIScheme {
public $default_port = 21;
public $browsable = true; // usually
public $hierarchical = true;
public function doValidate(&$uri, $config, $context) {
$uri->query    = null;
$semicolon_pos = strrpos($uri->path, ';'); // reverse
if ($semicolon_pos !== false) {
$type = substr($uri->path, $semicolon_pos + 1); // no semicolon
$uri->path = substr($uri->path, 0, $semicolon_pos);
$type_ret = '';
if (strpos($type, '=') !== false) {
list($key, $typecode) = explode('=', $type, 2);
if ($key !== 'type') {
$uri->path .= '%3B' . $type;
} elseif ($typecode === 'a' || $typecode === 'i' || $typecode === 'd') {
$type_ret = ";type=$typecode";
}
} else {
$uri->path .= '%3B' . $type;
}
$uri->path = str_replace(';', '%3B', $uri->path);
$uri->path .= $type_ret;
}
return true;
}
}
class HTMLPurifier_URIScheme_http extends HTMLPurifier_URIScheme {
public $default_port = 80;
public $browsable = true;
public $hierarchical = true;
public function doValidate(&$uri, $config, $context) {
$uri->userinfo = null;
return true;
}
}
class HTMLPurifier_URIScheme_https extends HTMLPurifier_URIScheme_http {
public $default_port = 443;
public $secure = true;
}
// email is valid, but be careful!
class HTMLPurifier_URIScheme_mailto extends HTMLPurifier_URIScheme {
public $browsable = false;
public $may_omit_host = true;
public function doValidate(&$uri, $config, $context) {
$uri->userinfo = null;
$uri->host     = null;
$uri->port     = null;
return true;
}
}
class HTMLPurifier_URIScheme_news extends HTMLPurifier_URIScheme {
public $browsable = false;
public $may_omit_host = true;
public function doValidate(&$uri, $config, $context) {
$uri->userinfo = null;
$uri->host     = null;
$uri->port     = null;
$uri->query    = null;
return true;
}
}
class HTMLPurifier_URIScheme_nntp extends HTMLPurifier_URIScheme {
public $default_port = 119;
public $browsable = false;
public function doValidate(&$uri, $config, $context) {
$uri->userinfo = null;
$uri->query    = null;
return true;
}
}
class HTMLPurifier_VarParser_Flexible extends HTMLPurifier_VarParser
{
protected function parseImplementation($var, $type, $allow_null) {
if ($allow_null && $var === null) return null;
switch ($type) {
case self::MIXED :
case self::ISTRING :
case self::STRING :
case self::TEXT :
case self::ITEXT :
return $var;
case self::INT :
if (is_string($var) && ctype_digit($var)) $var = (int) $var;
return $var;
case self::FLOAT :
if ((is_string($var) && is_numeric($var)) || is_int($var)) $var = (float) $var;
return $var;
case self::BOOL :
if (is_int($var) && ($var === 0 || $var === 1)) {
$var = (bool) $var;
} elseif (is_string($var)) {
if ($var == 'on' || $var == 'true' || $var == '1') {
$var = true;
} elseif ($var == 'off' || $var == 'false' || $var == '0') {
$var = false;
} else {
throw new HTMLPurifier_VarParserException("Unrecognized value '$var' for $type");
}
}
return $var;
case self::ALIST :
case self::HASH :
case self::LOOKUP :
if (is_string($var)) {
if ($var == '') return array();
if (strpos($var, "\n") === false && strpos($var, "\r") === false) {
$var = explode(',',$var);
} else {
$var = preg_split('/(,|[\n\r]+)/', $var);
}
foreach ($var as $i=>$j) $var[$i] = trim($j);
if ($type === self::HASH) {
$nvar = array();
foreach ($var as $keypair) {
$c = explode(':', $keypair, 2);
if (!isset($c[1])) continue;
$nvar[trim($c[0])] = trim($c[1]);
}
$var = $nvar;
}
}
if (!is_array($var)) break;
$keys = array_keys($var);
if ($keys === array_keys($keys)) {
if ($type == self::ALIST) return $var;
elseif ($type == self::LOOKUP) {
$new = array();
foreach ($var as $key) {
$new[$key] = true;
}
return $new;
} else break;
}
if ($type === self::ALIST) {
trigger_error("Array list did not have consecutive integer indexes", E_USER_WARNING);
return array_values($var);
}
if ($type === self::LOOKUP) {
foreach ($var as $key=>$value) {
if ($value !== true) {
trigger_error("Lookup array has non-true value at key '$key'; maybe your input array was not indexed numerically", E_USER_WARNING);
}
$var[$key] = true;
}
}
return $var;
default:
$this->errorInconsistent(__CLASS__, $type);
}
$this->errorGeneric($var, $type);
}
}
class HTMLPurifier_VarParser_Native extends HTMLPurifier_VarParser
{
protected function parseImplementation($var, $type, $allow_null) {
return $this->evalExpression($var);
}
protected function evalExpression($expr) {
$var = null;
$result = eval("\$var = $expr;");
if ($result === false) {
throw new HTMLPurifier_VarParserException("Fatal error in evaluated code");
}
return $var;
}
}