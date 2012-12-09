<?php
function htmlpurifier_filter_extractstyleblocks_muteerrorhandler() {}
class HTMLPurifier_Filter_ExtractStyleBlocks extends HTMLPurifier_Filter
{
public $name = 'ExtractStyleBlocks';
private $_styleMatches = array();
private $_tidy;
private $_id_attrdef;
private $_class_attrdef;
private $_enum_attrdef;
public function __construct() {
$this->_tidy = new csstidy();
$this->_id_attrdef = new HTMLPurifier_AttrDef_HTML_ID(true);
$this->_class_attrdef = new HTMLPurifier_AttrDef_CSS_Ident();
$this->_enum_attrdef = new HTMLPurifier_AttrDef_Enum(array('first-child', 'link', 'visited', 'active', 'hover', 'focus'));
}
protected function styleCallback($matches) {
$this->_styleMatches[] = $matches[1];
}
public function preFilter($html, $config, $context) {
$tidy = $config->get('Filter.ExtractStyleBlocks.TidyImpl');
if ($tidy !== null) $this->_tidy = $tidy;
$html = preg_replace_callback('#<style(?:\s.*)?>(.+)</style>#isU', array($this, 'styleCallback'), $html);
$style_blocks = $this->_styleMatches;
$this->_styleMatches = array();//reset
$context->register('StyleBlocks', $style_blocks);//$context must not be reused
if ($this->_tidy) {
foreach ($style_blocks as &$style) {
$style = $this->cleanCSS($style, $config, $context);
}
}
return $html;
}
public function cleanCSS($css, $config, $context) {
$scope = $config->get('Filter.ExtractStyleBlocks.Scope');
if ($scope !== null) {
$scopes = array_map('trim', explode(',', $scope));
} else {
$scopes = array();
}
$css = trim($css);
if (strncmp('<!--', $css, 4) === 0) {
$css = substr($css, 4);
}
if (strlen($css) > 3 && substr($css,-3) == '-->') {
$css = substr($css, 0,-3);
}
$css = trim($css);
set_error_handler('htmlpurifier_filter_extractstyleblocks_muteerrorhandler');
$this->_tidy->parse($css);
restore_error_handler();
$css_definition = $config->getDefinition('CSS');
$html_definition = $config->getDefinition('HTML');
$new_css = array();
foreach ($this->_tidy->css as $k=>$decls) {
$new_decls = array();
foreach ($decls as $selector=>$style) {
$selector = trim($selector);
if ($selector === '') continue;//should not happen
$selectors = array_map('trim', explode(',', $selector));
$new_selectors = array();
foreach ($selectors as $sel) {
$basic_selectors = preg_split('/\s*([+> ])\s*/', $sel,-1, PREG_SPLIT_DELIM_CAPTURE);
$nsel = null;
$delim = null;//guaranteed to be non-null after
for ($i = 0, $c = count($basic_selectors); $i < $c; $i++) {
$x = $basic_selectors[$i];
if ($i%2) {
if ($x === ' ') {
$delim = ' ';
} else {
$delim = ' ' . $x . ' ';
}
} else {
$components = preg_split('/([#.:])/', $x,-1, PREG_SPLIT_DELIM_CAPTURE);
$sdelim = null;
$nx = null;
for ($j = 0, $cc = count($components); $j < $cc; $j++) {
$y = $components[$j];
if ($j === 0) {
if ($y === '*' || isset($html_definition->info[$y = strtolower($y)])) {
$nx = $y;
} else {
}
} elseif ($j%2) {
$sdelim = $y;
} else {
$attrdef = null;
if ($sdelim === '#') {
$attrdef = $this->_id_attrdef;
} elseif ($sdelim === '.') {
$attrdef = $this->_class_attrdef;
} elseif ($sdelim === ':') {
$attrdef = $this->_enum_attrdef;
} else {
throw new HTMLPurifier_Exception('broken invariant sdelim and preg_split');
}
$r = $attrdef->validate($y, $config, $context);
if ($r !== false) {
if ($r !== true) {
$y = $r;
}
if ($nx === null) {
$nx = '';
}
$nx .= $sdelim . $y;
}
}
}
if ($nx !== null) {
if ($nsel === null) {
$nsel = $nx;
} else {
$nsel .= $delim . $nx;
}
} else {
}
}
}
if ($nsel !== null) {
if (!empty($scopes)) {
foreach ($scopes as $s) {
$new_selectors[] = "$s $nsel";
}
} else {
$new_selectors[] = $nsel;
}
}
}
if (empty($new_selectors)) continue;
$selector = implode(', ', $new_selectors);
foreach ($style as $name=>$value) {
if (!isset($css_definition->info[$name])) {
unset($style[$name]);
continue;
}
$def = $css_definition->info[$name];
$ret = $def->validate($value, $config, $context);
if ($ret === false) unset($style[$name]);
else $style[$name] = $ret;
}
$new_decls[$selector] = $style;
}
$new_css[$k] = $new_decls;
}
$this->_tidy->css = $new_css;
$this->_tidy->import = array();
$this->_tidy->charset = null;
$this->_tidy->namespace = null;
$css = $this->_tidy->print->plain();
if ($config->get('Filter.ExtractStyleBlocks.Escaping')) {
$css = str_replace(
array('<',    '>',    '&'),
array('\3C ', '\3E ', '\26 '),
$css
);
}
return $css;
}
}
