<?php
class HTMLPurifier_Lexer_PH5P extends HTMLPurifier_Lexer_DOMLex {
public function tokenizeHTML($html, $config, $context) {
$new_html = $this->normalize($html, $config, $context);
$new_html = $this->wrapHTML($new_html, $config, $context);
try {
$parser = new HTML5($new_html);
$doc = $parser->save();
} catch (DOMException $e) {
$lexer = new HTMLPurifier_Lexer_DirectLex();
$context->register('PH5PError', $e); // save the error, so we can detect it
return $lexer->tokenizeHTML($html, $config, $context); // use original HTML
}
$tokens = array();
$this->tokenizeDOM(
$doc->getElementsByTagName('html')->item(0)-> // <html>
getElementsByTagName('body')->item(0)-> //   <body>
getElementsByTagName('div')->item(0)    //     <div>
, $tokens);
return $tokens;
}
}
class HTML5 {
private $data;
private $char;
private $EOF;
private $state;
private $tree;
private $token;
private $content_model;
private $escape = false;
private $entities = array('AElig;','AElig','AMP;','AMP','Aacute;','Aacute',
'Acirc;','Acirc','Agrave;','Agrave','Alpha;','Aring;','Aring','Atilde;',
'Atilde','Auml;','Auml','Beta;','COPY;','COPY','Ccedil;','Ccedil','Chi;',
'Dagger;','Delta;','ETH;','ETH','Eacute;','Eacute','Ecirc;','Ecirc','Egrave;',
'Egrave','Epsilon;','Eta;','Euml;','Euml','GT;','GT','Gamma;','Iacute;',
'Iacute','Icirc;','Icirc','Igrave;','Igrave','Iota;','Iuml;','Iuml','Kappa;',
'LT;','LT','Lambda;','Mu;','Ntilde;','Ntilde','Nu;','OElig;','Oacute;',
'Oacute','Ocirc;','Ocirc','Ograve;','Ograve','Omega;','Omicron;','Oslash;',
'Oslash','Otilde;','Otilde','Ouml;','Ouml','Phi;','Pi;','Prime;','Psi;',
'QUOT;','QUOT','REG;','REG','Rho;','Scaron;','Sigma;','THORN;','THORN',
'TRADE;','Tau;','Theta;','Uacute;','Uacute','Ucirc;','Ucirc','Ugrave;',
'Ugrave','Upsilon;','Uuml;','Uuml','Xi;','Yacute;','Yacute','Yuml;','Zeta;',
'aacute;','aacute','acirc;','acirc','acute;','acute','aelig;','aelig',
'agrave;','agrave','alefsym;','alpha;','amp;','amp','and;','ang;','apos;',
'aring;','aring','asymp;','atilde;','atilde','auml;','auml','bdquo;','beta;',
'brvbar;','brvbar','bull;','cap;','ccedil;','ccedil','cedil;','cedil',
'cent;','cent','chi;','circ;','clubs;','cong;','copy;','copy','crarr;',
'cup;','curren;','curren','dArr;','dagger;','darr;','deg;','deg','delta;',
'diams;','divide;','divide','eacute;','eacute','ecirc;','ecirc','egrave;',
'egrave','empty;','emsp;','ensp;','epsilon;','equiv;','eta;','eth;','eth',
'euml;','euml','euro;','exist;','fnof;','forall;','frac12;','frac12',
'frac14;','frac14','frac34;','frac34','frasl;','gamma;','ge;','gt;','gt',
'hArr;','harr;','hearts;','hellip;','iacute;','iacute','icirc;','icirc',
'iexcl;','iexcl','igrave;','igrave','image;','infin;','int;','iota;',
'iquest;','iquest','isin;','iuml;','iuml','kappa;','lArr;','lambda;','lang;',
'laquo;','laquo','larr;','lceil;','ldquo;','le;','lfloor;','lowast;','loz;',
'lrm;','lsaquo;','lsquo;','lt;','lt','macr;','macr','mdash;','micro;','micro',
'middot;','middot','minus;','mu;','nabla;','nbsp;','nbsp','ndash;','ne;',
'ni;','not;','not','notin;','nsub;','ntilde;','ntilde','nu;','oacute;',
'oacute','ocirc;','ocirc','oelig;','ograve;','ograve','oline;','omega;',
'omicron;','oplus;','or;','ordf;','ordf','ordm;','ordm','oslash;','oslash',
'otilde;','otilde','otimes;','ouml;','ouml','para;','para','part;','permil;',
'perp;','phi;','pi;','piv;','plusmn;','plusmn','pound;','pound','prime;',
'prod;','prop;','psi;','quot;','quot','rArr;','radic;','rang;','raquo;',
'raquo','rarr;','rceil;','rdquo;','real;','reg;','reg','rfloor;','rho;',
'rlm;','rsaquo;','rsquo;','sbquo;','scaron;','sdot;','sect;','sect','shy;',
'shy','sigma;','sigmaf;','sim;','spades;','sub;','sube;','sum;','sup1;',
'sup1','sup2;','sup2','sup3;','sup3','sup;','supe;','szlig;','szlig','tau;',
'there4;','theta;','thetasym;','thinsp;','thorn;','thorn','tilde;','times;',
'times','trade;','uArr;','uacute;','uacute','uarr;','ucirc;','ucirc',
'ugrave;','ugrave','uml;','uml','upsih;','upsilon;','uuml;','uuml','weierp;',
'xi;','yacute;','yacute','yen;','yen','yuml;','yuml','zeta;','zwj;','zwnj;');
const PCDATA    = 0;
const RCDATA    = 1;
const CDATA     = 2;
const PLAINTEXT = 3;
const DOCTYPE  = 0;
const STARTTAG = 1;
const ENDTAG   = 2;
const COMMENT  = 3;
const CHARACTR = 4;
const EOF      = 5;
public function __construct($data) {
$this->data = $data;
$this->char = -1;
$this->EOF  = strlen($data);
$this->tree = new HTML5TreeConstructer;
$this->content_model = self::PCDATA;
$this->state = 'data';
while($this->state !== null) {
$this->{$this->state.'State'}();
}
}
public function save() {
return $this->tree->save();
}
private function char() {
return ($this->char < $this->EOF)
? $this->data[$this->char]
: false;
}
private function character($s, $l = 0) {
if($s + $l < $this->EOF) {
if($l === 0) {
return $this->data[$s];
} else {
return substr($this->data, $s, $l);
}
}
}
private function characters($char_class, $start) {
return preg_replace('#^(['.$char_class.']+).*#s', '\\1', substr($this->data, $start));
}
private function dataState() {
$this->char++;
$char = $this->char();
if($char === '&' && ($this->content_model === self::PCDATA || $this->content_model === self::RCDATA)) {
$this->state = 'entityData';
} elseif($char === '-') {
if(($this->content_model === self::RCDATA || $this->content_model ===
self::CDATA) && $this->escape === false &&
$this->char >= 3 && $this->character($this->char - 4, 4) === '<!--') {
$this->escape = true;
}
$this->emitToken(array(
'type'=>self::CHARACTR,
'data'=>$char
));
} elseif($char === '<' && ($this->content_model === self::PCDATA ||
(($this->content_model === self::RCDATA ||
$this->content_model === self::CDATA) && $this->escape === false))) {
$this->state = 'tagOpen';
} elseif($char === '>') {
if(($this->content_model === self::RCDATA ||
$this->content_model === self::CDATA) && $this->escape === true &&
$this->character($this->char, 3) === '-->') {
$this->escape = false;
}
$this->emitToken(array(
'type'=>self::CHARACTR,
'data'=>$char
));
} elseif($this->char === $this->EOF) {
$this->EOF();
} elseif($this->content_model === self::PLAINTEXT) {
$this->emitToken(array(
'type'=>self::CHARACTR,
'data'=>substr($this->data, $this->char)
));
$this->EOF();
} else {
$len  = strcspn($this->data, '<&', $this->char);
$char = substr($this->data, $this->char, $len);
$this->char += $len - 1;
$this->emitToken(array(
'type'=>self::CHARACTR,
'data'=>$char
));
$this->state = 'data';
}
}
private function entityDataState() {
$entity = $this->entity();
$char = (!$entity) ? '&' : $entity;
$this->emitToken(array(
'type'=>self::CHARACTR,
'data'=>$char
));
$this->state = 'data';
}
private function tagOpenState() {
switch($this->content_model) {
case self::RCDATA:
case self::CDATA:
if($this->character($this->char + 1) === '/') {
$this->char++;
$this->state = 'closeTagOpen';
} else {
$this->emitToken(array(
'type'=>self::CHARACTR,
'data'=>'<'
));
$this->state = 'data';
}
break;
case self::PCDATA:
$this->char++;
$char = $this->char();
if($char === '!') {
$this->state = 'markupDeclarationOpen';
} elseif($char === '/') {
$this->state = 'closeTagOpen';
} elseif(preg_match('/^[A-Za-z]$/', $char)) {
$this->token = array(
'name'=>strtolower($char),
'type'=>self::STARTTAG,
'attr'=>array()
);
$this->state = 'tagName';
} elseif($char === '>') {
$this->emitToken(array(
'type'=>self::CHARACTR,
'data'=>'<>'
));
$this->state = 'data';
} elseif($char === '?') {
$this->state = 'bogusComment';
} else {
$this->emitToken(array(
'type'=>self::CHARACTR,
'data'=>'<'
));
$this->char--;
$this->state = 'data';
}
break;
}
}
private function closeTagOpenState() {
$next_node = strtolower($this->characters('A-Za-z', $this->char + 1));
$the_same = count($this->tree->stack) > 0 && $next_node === end($this->tree->stack)->nodeName;
if(($this->content_model === self::RCDATA || $this->content_model === self::CDATA) &&
(!$the_same || ($the_same && (!preg_match('/[\t\n\x0b\x0c >\/]/',
$this->character($this->char + 1 + strlen($next_node))) || $this->EOF === $this->char)))) {
$this->emitToken(array(
'type'=>self::CHARACTR,
'data'=>'</'
));
$this->state = 'data';
} else {
$this->char++;
$char = $this->char();
if(preg_match('/^[A-Za-z]$/', $char)) {
$this->token = array(
'name'=>strtolower($char),
'type'=>self::ENDTAG
);
$this->state = 'tagName';
} elseif($char === '>') {
$this->state = 'data';
} elseif($this->char === $this->EOF) {
$this->emitToken(array(
'type'=>self::CHARACTR,
'data'=>'</'
));
$this->char--;
$this->state = 'data';
} else {
$this->state = 'bogusComment';
}
}
}
private function tagNameState() {
$this->char++;
$char = $this->character($this->char);
if(preg_match('/^[\t\n\x0b\x0c ]$/', $char)) {
$this->state = 'beforeAttributeName';
} elseif($char === '>') {
$this->emitToken($this->token);
$this->state = 'data';
} elseif($this->char === $this->EOF) {
$this->emitToken($this->token);
$this->char--;
$this->state = 'data';
} elseif($char === '/') {
$this->state = 'beforeAttributeName';
} else {
$this->token['name'] .= strtolower($char);
$this->state = 'tagName';
}
}
private function beforeAttributeNameState() {
$this->char++;
$char = $this->character($this->char);
if(preg_match('/^[\t\n\x0b\x0c ]$/', $char)) {
$this->state = 'beforeAttributeName';
} elseif($char === '>') {
$this->emitToken($this->token);
$this->state = 'data';
} elseif($char === '/') {
$this->state = 'beforeAttributeName';
} elseif($this->char === $this->EOF) {
$this->emitToken($this->token);
$this->char--;
$this->state = 'data';
} else {
$this->token['attr'][] = array(
'name'=>strtolower($char),
'value'=>null
);
$this->state = 'attributeName';
}
}
private function attributeNameState() {
$this->char++;
$char = $this->character($this->char);
if(preg_match('/^[\t\n\x0b\x0c ]$/', $char)) {
$this->state = 'afterAttributeName';
} elseif($char === '=') {
$this->state = 'beforeAttributeValue';
} elseif($char === '>') {
$this->emitToken($this->token);
$this->state = 'data';
} elseif($char === '/' && $this->character($this->char + 1) !== '>') {
$this->state = 'beforeAttributeName';
} elseif($this->char === $this->EOF) {
$this->emitToken($this->token);
$this->char--;
$this->state = 'data';
} else {
$last = count($this->token['attr']) - 1;
$this->token['attr'][$last]['name'] .= strtolower($char);
$this->state = 'attributeName';
}
}
private function afterAttributeNameState() {
$this->char++;
$char = $this->character($this->char);
if(preg_match('/^[\t\n\x0b\x0c ]$/', $char)) {
$this->state = 'afterAttributeName';
} elseif($char === '=') {
$this->state = 'beforeAttributeValue';
} elseif($char === '>') {
$this->emitToken($this->token);
$this->state = 'data';
} elseif($char === '/' && $this->character($this->char + 1) !== '>') {
$this->state = 'beforeAttributeName';
} elseif($this->char === $this->EOF) {
$this->emitToken($this->token);
$this->char--;
$this->state = 'data';
} else {
$this->token['attr'][] = array(
'name'=>strtolower($char),
'value'=>null
);
$this->state = 'attributeName';
}
}
private function beforeAttributeValueState() {
$this->char++;
$char = $this->character($this->char);
if(preg_match('/^[\t\n\x0b\x0c ]$/', $char)) {
$this->state = 'beforeAttributeValue';
} elseif($char === '"') {
$this->state = 'attributeValueDoubleQuoted';
} elseif($char === '&') {
$this->char--;
$this->state = 'attributeValueUnquoted';
} elseif($char === '\'') {
$this->state = 'attributeValueSingleQuoted';
} elseif($char === '>') {
$this->emitToken($this->token);
$this->state = 'data';
} else {
$last = count($this->token['attr']) - 1;
$this->token['attr'][$last]['value'] .= $char;
$this->state = 'attributeValueUnquoted';
}
}
private function attributeValueDoubleQuotedState() {
$this->char++;
$char = $this->character($this->char);
if($char === '"') {
$this->state = 'beforeAttributeName';
} elseif($char === '&') {
$this->entityInAttributeValueState('double');
} elseif($this->char === $this->EOF) {
$this->emitToken($this->token);
$this->char--;
$this->state = 'data';
} else {
$last = count($this->token['attr']) - 1;
$this->token['attr'][$last]['value'] .= $char;
$this->state = 'attributeValueDoubleQuoted';
}
}
private function attributeValueSingleQuotedState() {
$this->char++;
$char = $this->character($this->char);
if($char === '\'') {
$this->state = 'beforeAttributeName';
} elseif($char === '&') {
$this->entityInAttributeValueState('single');
} elseif($this->char === $this->EOF) {
$this->emitToken($this->token);
$this->char--;
$this->state = 'data';
} else {
$last = count($this->token['attr']) - 1;
$this->token['attr'][$last]['value'] .= $char;
$this->state = 'attributeValueSingleQuoted';
}
}
private function attributeValueUnquotedState() {
$this->char++;
$char = $this->character($this->char);
if(preg_match('/^[\t\n\x0b\x0c ]$/', $char)) {
$this->state = 'beforeAttributeName';
} elseif($char === '&') {
$this->entityInAttributeValueState();
} elseif($char === '>') {
$this->emitToken($this->token);
$this->state = 'data';
} else {
$last = count($this->token['attr']) - 1;
$this->token['attr'][$last]['value'] .= $char;
$this->state = 'attributeValueUnquoted';
}
}
private function entityInAttributeValueState() {
$entity = $this->entity();
$char = (!$entity)
? '&'
: $entity;
$last = count($this->token['attr']) - 1;
$this->token['attr'][$last]['value'] .= $char;
}
private function bogusCommentState() {
$data = $this->characters('^>', $this->char);
$this->emitToken(array(
'data'=>$data,
'type'=>self::COMMENT
));
$this->char += strlen($data);
$this->state = 'data';
if($this->char === $this->EOF) {
$this->char = $this->EOF - 1;
}
}
private function markupDeclarationOpenState() {
if($this->character($this->char + 1, 2) === '--') {
$this->char += 2;
$this->state = 'comment';
$this->token = array(
'data'=>null,
'type'=>self::COMMENT
);
} elseif(strtolower($this->character($this->char + 1, 7)) === 'doctype') {
$this->char += 7;
$this->state = 'doctype';
} else {
$this->char++;
$this->state = 'bogusComment';
}
}
private function commentState() {
$this->char++;
$char = $this->char();
if($char === '-') {
$this->state = 'commentDash';
} elseif($this->char === $this->EOF) {
$this->emitToken($this->token);
$this->char--;
$this->state = 'data';
} else {
$this->token['data'] .= $char;
}
}
private function commentDashState() {
$this->char++;
$char = $this->char();
if($char === '-') {
$this->state = 'commentEnd';
} elseif($this->char === $this->EOF) {
$this->emitToken($this->token);
$this->char--;
$this->state = 'data';
} else {
$this->token['data'] .= '-'.$char;
$this->state = 'comment';
}
}
private function commentEndState() {
$this->char++;
$char = $this->char();
if($char === '>') {
$this->emitToken($this->token);
$this->state = 'data';
} elseif($char === '-') {
$this->token['data'] .= '-';
} elseif($this->char === $this->EOF) {
$this->emitToken($this->token);
$this->char--;
$this->state = 'data';
} else {
$this->token['data'] .= '--'.$char;
$this->state = 'comment';
}
}
private function doctypeState() {
$this->char++;
$char = $this->char();
if(preg_match('/^[\t\n\x0b\x0c ]$/', $char)) {
$this->state = 'beforeDoctypeName';
} else {
$this->char--;
$this->state = 'beforeDoctypeName';
}
}
private function beforeDoctypeNameState() {
$this->char++;
$char = $this->char();
if(preg_match('/^[\t\n\x0b\x0c ]$/', $char)) {
} elseif(preg_match('/^[a-z]$/', $char)) {
$this->token = array(
'name'=>strtoupper($char),
'type'=>self::DOCTYPE,
'error'=>true
);
$this->state = 'doctypeName';
} elseif($char === '>') {
$this->emitToken(array(
'name'=>null,
'type'=>self::DOCTYPE,
'error'=>true
));
$this->state = 'data';
} elseif($this->char === $this->EOF) {
$this->emitToken(array(
'name'=>null,
'type'=>self::DOCTYPE,
'error'=>true
));
$this->char--;
$this->state = 'data';
} else {
$this->token = array(
'name'=>$char,
'type'=>self::DOCTYPE,
'error'=>true
);
$this->state = 'doctypeName';
}
}
private function doctypeNameState() {
$this->char++;
$char = $this->char();
if(preg_match('/^[\t\n\x0b\x0c ]$/', $char)) {
$this->state = 'AfterDoctypeName';
} elseif($char === '>') {
$this->emitToken($this->token);
$this->state = 'data';
} elseif(preg_match('/^[a-z]$/', $char)) {
$this->token['name'] .= strtoupper($char);
} elseif($this->char === $this->EOF) {
$this->emitToken($this->token);
$this->char--;
$this->state = 'data';
} else {
$this->token['name'] .= $char;
}
$this->token['error'] = ($this->token['name'] === 'HTML')
? false
: true;
}
private function afterDoctypeNameState() {
$this->char++;
$char = $this->char();
if(preg_match('/^[\t\n\x0b\x0c ]$/', $char)) {
} elseif($char === '>') {
$this->emitToken($this->token);
$this->state = 'data';
} elseif($this->char === $this->EOF) {
$this->emitToken($this->token);
$this->char--;
$this->state = 'data';
} else {
$this->token['error'] = true;
$this->state = 'bogusDoctype';
}
}
private function bogusDoctypeState() {
$this->char++;
$char = $this->char();
if($char === '>') {
$this->emitToken($this->token);
$this->state = 'data';
} elseif($this->char === $this->EOF) {
$this->emitToken($this->token);
$this->char--;
$this->state = 'data';
} else {
}
}
private function entity() {
$start = $this->char;
switch($this->character($this->char + 1)) {
case '#':
switch($this->character($this->char + 1)) {
case 'x':
case 'X':
$char = 1;
$char_class = '0-9A-Fa-f';
break;
default:
$char = 0;
$char_class = '0-9';
break;
}
$this->char++;
$e_name = $this->characters($char_class, $this->char + $char + 1);
$entity = $this->character($start, $this->char);
$cond = strlen($e_name) > 0;
break;
default:
$e_name = $this->characters('0-9A-Za-z;', $this->char + 1);
$len = strlen($e_name);
for($c = 1; $c <= $len; $c++) {
$id = substr($e_name, 0, $c);
$this->char++;
if(in_array($id, $this->entities)) {
if ($e_name[$c-1] !== ';') {
if ($c < $len && $e_name[$c] == ';') {
$this->char++; // consume extra semicolon
}
}
$entity = $id;
break;
}
}
$cond = isset($entity);
break;
}
if(!$cond) {
$this->char = $start;
return false;
}
return html_entity_decode('&'.$entity.';', ENT_QUOTES, 'UTF-8');
}
private function emitToken($token) {
$emit = $this->tree->emitToken($token);
if(is_int($emit)) {
$this->content_model = $emit;
} elseif($token['type'] === self::ENDTAG) {
$this->content_model = self::PCDATA;
}
}
private function EOF() {
$this->state = null;
$this->tree->emitToken(array(
'type'=>self::EOF
));
}
}
class HTML5TreeConstructer {
public $stack = array();
private $phase;
private $mode;
private $dom;
private $foster_parent = null;
private $a_formatting  = array();
private $head_pointer = null;
private $form_pointer = null;
private $scoping = array('button','caption','html','marquee','object','table','td','th');
private $formatting = array('a','b','big','em','font','i','nobr','s','small','strike','strong','tt','u');
private $special = array('address','area','base','basefont','bgsound',
'blockquote','body','br','center','col','colgroup','dd','dir','div','dl',
'dt','embed','fieldset','form','frame','frameset','h1','h2','h3','h4','h5',
'h6','head','hr','iframe','image','img','input','isindex','li','link',
'listing','menu','meta','noembed','noframes','noscript','ol','optgroup',
'option','p','param','plaintext','pre','script','select','spacer','style',
'tbody','textarea','tfoot','thead','title','tr','ul','wbr');
const INIT_PHASE = 0;
const ROOT_PHASE = 1;
const MAIN_PHASE = 2;
const END_PHASE  = 3;
const BEFOR_HEAD = 0;
const IN_HEAD    = 1;
const AFTER_HEAD = 2;
const IN_BODY    = 3;
const IN_TABLE   = 4;
const IN_CAPTION = 5;
const IN_CGROUP  = 6;
const IN_TBODY   = 7;
const IN_ROW     = 8;
const IN_CELL    = 9;
const IN_SELECT  = 10;
const AFTER_BODY = 11;
const IN_FRAME   = 12;
const AFTR_FRAME = 13;
const SPECIAL    = 0;
const SCOPING    = 1;
const FORMATTING = 2;
const PHRASING   = 3;
const MARKER     = 0;
public function __construct() {
$this->phase = self::INIT_PHASE;
$this->mode = self::BEFOR_HEAD;
$this->dom = new DOMDocument;
$this->dom->encoding = 'UTF-8';
$this->dom->preserveWhiteSpace = true;
$this->dom->substituteEntities = true;
$this->dom->strictErrorChecking = false;
}
public function emitToken($token) {
switch($this->phase) {
case self::INIT_PHASE: return $this->initPhase($token); break;
case self::ROOT_PHASE: return $this->rootElementPhase($token); break;
case self::MAIN_PHASE: return $this->mainPhase($token); break;
case self::END_PHASE : return $this->trailingEndPhase($token); break;
}
}
private function initPhase($token) {
if((isset($token['error']) && $token['error']) ||
$token['type'] === HTML5::COMMENT ||
$token['type'] === HTML5::STARTTAG ||
$token['type'] === HTML5::ENDTAG ||
$token['type'] === HTML5::EOF ||
($token['type'] === HTML5::CHARACTR && isset($token['data']) &&
!preg_match('/^[\t\n\x0b\x0c ]+$/', $token['data']))) {
$this->phase = self::ROOT_PHASE;
return $this->rootElementPhase($token);
} elseif(isset($token['error']) && !$token['error']) {
$doctype = new DOMDocumentType(null, null, 'HTML');
$this->phase = self::ROOT_PHASE;
} elseif(isset($token['data']) && preg_match('/^[\t\n\x0b\x0c ]+$/',
$token['data'])) {
$text = $this->dom->createTextNode($token['data']);
$this->dom->appendChild($text);
}
}
private function rootElementPhase($token) {
if($token['type'] === HTML5::DOCTYPE) {
} elseif($token['type'] === HTML5::COMMENT) {
$comment = $this->dom->createComment($token['data']);
$this->dom->appendChild($comment);
} elseif($token['type'] === HTML5::CHARACTR &&
preg_match('/^[\t\n\x0b\x0c ]+$/', $token['data'])) {
$text = $this->dom->createTextNode($token['data']);
$this->dom->appendChild($text);
} elseif(($token['type'] === HTML5::CHARACTR &&
!preg_match('/^[\t\n\x0b\x0c ]+$/', $token['data'])) ||
$token['type'] === HTML5::STARTTAG ||
$token['type'] === HTML5::ENDTAG ||
$token['type'] === HTML5::EOF) {
$html = $this->dom->createElement('html');
$this->dom->appendChild($html);
$this->stack[] = $html;
$this->phase = self::MAIN_PHASE;
return $this->mainPhase($token);
}
}
private function mainPhase($token) {
if($token['type'] === HTML5::DOCTYPE) {
} elseif($token['type'] === HTML5::STARTTAG && $token['name'] === 'html') {
foreach($token['attr'] as $attr) {
if(!$this->stack[0]->hasAttribute($attr['name'])) {
$this->stack[0]->setAttribute($attr['name'], $attr['value']);
}
}
} elseif($token['type'] === HTML5::EOF) {
$this->generateImpliedEndTags();
} else {
switch($this->mode) {
case self::BEFOR_HEAD: return $this->beforeHead($token); break;
case self::IN_HEAD:    return $this->inHead($token); break;
case self::AFTER_HEAD: return $this->afterHead($token); break;
case self::IN_BODY:    return $this->inBody($token); break;
case self::IN_TABLE:   return $this->inTable($token); break;
case self::IN_CAPTION: return $this->inCaption($token); break;
case self::IN_CGROUP:  return $this->inColumnGroup($token); break;
case self::IN_TBODY:   return $this->inTableBody($token); break;
case self::IN_ROW:     return $this->inRow($token); break;
case self::IN_CELL:    return $this->inCell($token); break;
case self::IN_SELECT:  return $this->inSelect($token); break;
case self::AFTER_BODY: return $this->afterBody($token); break;
case self::IN_FRAME:   return $this->inFrameset($token); break;
case self::AFTR_FRAME: return $this->afterFrameset($token); break;
case self::END_PHASE:  return $this->trailingEndPhase($token); break;
}
}
}
private function beforeHead($token) {
if($token['type'] === HTML5::CHARACTR &&
preg_match('/^[\t\n\x0b\x0c ]+$/', $token['data'])) {
$this->insertText($token['data']);
} elseif($token['type'] === HTML5::COMMENT) {
$this->insertComment($token['data']);
} elseif($token['type'] === HTML5::STARTTAG && $token['name'] === 'head') {
$element = $this->insertElement($token);
$this->head_pointer = $element;
$this->mode = self::IN_HEAD;
} elseif($token['type'] === HTML5::STARTTAG ||
($token['type'] === HTML5::ENDTAG && $token['name'] === 'html') ||
($token['type'] === HTML5::CHARACTR && !preg_match('/^[\t\n\x0b\x0c ]$/',
$token['data']))) {
$this->beforeHead(array(
'name'=>'head',
'type'=>HTML5::STARTTAG,
'attr'=>array()
));
return $this->inHead($token);
} elseif($token['type'] === HTML5::ENDTAG) {
}
}
private function inHead($token) {
if(($token['type'] === HTML5::CHARACTR &&
preg_match('/^[\t\n\x0b\x0c ]+$/', $token['data'])) || (
$token['type'] === HTML5::CHARACTR && in_array(end($this->stack)->nodeName,
array('title', 'style', 'script')))) {
$this->insertText($token['data']);
} elseif($token['type'] === HTML5::COMMENT) {
$this->insertComment($token['data']);
} elseif($token['type'] === HTML5::ENDTAG &&
in_array($token['name'], array('title', 'style', 'script'))) {
array_pop($this->stack);
return HTML5::PCDATA;
} elseif($token['type'] === HTML5::STARTTAG && $token['name'] === 'title') {
if($this->head_pointer !== null) {
$element = $this->insertElement($token, false);
$this->head_pointer->appendChild($element);
} else {
$element = $this->insertElement($token);
}
return HTML5::RCDATA;
} elseif($token['type'] === HTML5::STARTTAG && $token['name'] === 'style') {
if($this->head_pointer !== null) {
$element = $this->insertElement($token, false);
$this->head_pointer->appendChild($element);
} else {
$this->insertElement($token);
}
return HTML5::CDATA;
} elseif($token['type'] === HTML5::STARTTAG && $token['name'] === 'script') {
$element = $this->insertElement($token, false);
$this->head_pointer->appendChild($element);
return HTML5::CDATA;
} elseif($token['type'] === HTML5::STARTTAG && in_array($token['name'],
array('base', 'link', 'meta'))) {
if($this->head_pointer !== null) {
$element = $this->insertElement($token, false);
$this->head_pointer->appendChild($element);
array_pop($this->stack);
} else {
$this->insertElement($token);
}
} elseif($token['type'] === HTML5::ENDTAG && $token['name'] === 'head') {
if($this->head_pointer->isSameNode(end($this->stack))) {
array_pop($this->stack);
} else {
}
$this->mode = self::AFTER_HEAD;
} elseif(($token['type'] === HTML5::STARTTAG && $token['name'] === 'head') ||
($token['type'] === HTML5::ENDTAG && $token['name'] !== 'html')) {
} else {
if($this->head_pointer->isSameNode(end($this->stack))) {
$this->inHead(array(
'name'=>'head',
'type'=>HTML5::ENDTAG
));
} else {
$this->mode = self::AFTER_HEAD;
}
return $this->afterHead($token);
}
}
private function afterHead($token) {
if($token['type'] === HTML5::CHARACTR &&
preg_match('/^[\t\n\x0b\x0c ]+$/', $token['data'])) {
$this->insertText($token['data']);
} elseif($token['type'] === HTML5::COMMENT) {
$this->insertComment($token['data']);
} elseif($token['type'] === HTML5::STARTTAG && $token['name'] === 'body') {
$this->insertElement($token);
$this->mode = self::IN_BODY;
} elseif($token['type'] === HTML5::STARTTAG && $token['name'] === 'frameset') {
$this->insertElement($token);
$this->mode = self::IN_FRAME;
} elseif($token['type'] === HTML5::STARTTAG && in_array($token['name'],
array('base', 'link', 'meta', 'script', 'style', 'title'))) {
$this->mode = self::IN_HEAD;
return $this->inHead($token);
} else {
$this->afterHead(array(
'name'=>'body',
'type'=>HTML5::STARTTAG,
'attr'=>array()
));
return $this->inBody($token);
}
}
private function inBody($token) {
switch($token['type']) {
case HTML5::CHARACTR:
$this->reconstructActiveFormattingElements();
$this->insertText($token['data']);
break;
case HTML5::COMMENT:
$this->insertComment($token['data']);
break;
case HTML5::STARTTAG:
switch($token['name']) {
case 'script': case 'style':
return $this->inHead($token);
break;
case 'base': case 'link': case 'meta': case 'title':
return $this->inHead($token);
break;
case 'body':
if(count($this->stack) === 1 || $this->stack[1]->nodeName !== 'body') {
} else {
foreach($token['attr'] as $attr) {
if(!$this->stack[1]->hasAttribute($attr['name'])) {
$this->stack[1]->setAttribute($attr['name'], $attr['value']);
}
}
}
break;
case 'address': case 'blockquote': case 'center': case 'dir':
case 'div': case 'dl': case 'fieldset': case 'listing':
case 'menu': case 'ol': case 'p': case 'ul':
if($this->elementInScope('p')) {
$this->emitToken(array(
'name'=>'p',
'type'=>HTML5::ENDTAG
));
}
$this->insertElement($token);
break;
case 'form':
if($this->form_pointer !== null) {
} else {
if($this->elementInScope('p')) {
$this->emitToken(array(
'name'=>'p',
'type'=>HTML5::ENDTAG
));
}
$element = $this->insertElement($token);
$this->form_pointer = $element;
}
break;
case 'li': case 'dd': case 'dt':
if($this->elementInScope('p')) {
$this->emitToken(array(
'name'=>'p',
'type'=>HTML5::ENDTAG
));
}
$stack_length = count($this->stack) - 1;
for($n = $stack_length; 0 <= $n; $n--) {
$stop = false;
$node = $this->stack[$n];
$cat  = $this->getElementCategory($node->tagName);
if($token['name'] === $node->tagName ||    ($token['name'] !== 'li'
&& ($node->tagName === 'dd' || $node->tagName === 'dt'))) {
for($x = $stack_length; $x >= $n ; $x--) {
array_pop($this->stack);
}
break;
}
if($cat !== self::FORMATTING && $cat !== self::PHRASING &&
$node->tagName !== 'address' && $node->tagName !== 'div') {
break;
}
}
$this->insertElement($token);
break;
case 'plaintext':
if($this->elementInScope('p')) {
$this->emitToken(array(
'name'=>'p',
'type'=>HTML5::ENDTAG
));
}
$this->insertElement($token);
return HTML5::PLAINTEXT;
break;
case 'h1': case 'h2': case 'h3': case 'h4': case 'h5': case 'h6':
if($this->elementInScope('p')) {
$this->emitToken(array(
'name'=>'p',
'type'=>HTML5::ENDTAG
));
}
while($this->elementInScope(array('h1', 'h2', 'h3', 'h4', 'h5', 'h6'))) {
array_pop($this->stack);
}
$this->insertElement($token);
break;
case 'a':
$leng = count($this->a_formatting);
for($n = $leng - 1; $n >= 0; $n--) {
if($this->a_formatting[$n] === self::MARKER) {
break;
} elseif($this->a_formatting[$n]->nodeName === 'a') {
$this->emitToken(array(
'name'=>'a',
'type'=>HTML5::ENDTAG
));
break;
}
}
$this->reconstructActiveFormattingElements();
$el = $this->insertElement($token);
$this->a_formatting[] = $el;
break;
case 'b': case 'big': case 'em': case 'font': case 'i':
case 'nobr': case 's': case 'small': case 'strike':
case 'strong': case 'tt': case 'u':
$this->reconstructActiveFormattingElements();
$el = $this->insertElement($token);
$this->a_formatting[] = $el;
break;
case 'button':
if($this->elementInScope('button')) {
$this->inBody(array(
'name'=>'button',
'type'=>HTML5::ENDTAG
));
}
$this->reconstructActiveFormattingElements();
$this->insertElement($token);
$this->a_formatting[] = self::MARKER;
break;
case 'marquee': case 'object':
$this->reconstructActiveFormattingElements();
$this->insertElement($token);
$this->a_formatting[] = self::MARKER;
break;
case 'xmp':
$this->reconstructActiveFormattingElements();
$this->insertElement($token);
return HTML5::CDATA;
break;
case 'table':
if($this->elementInScope('p')) {
$this->emitToken(array(
'name'=>'p',
'type'=>HTML5::ENDTAG
));
}
$this->insertElement($token);
$this->mode = self::IN_TABLE;
break;
case 'area': case 'basefont': case 'bgsound': case 'br':
case 'embed': case 'img': case 'param': case 'spacer':
case 'wbr':
$this->reconstructActiveFormattingElements();
$this->insertElement($token);
array_pop($this->stack);
break;
case 'hr':
if($this->elementInScope('p')) {
$this->emitToken(array(
'name'=>'p',
'type'=>HTML5::ENDTAG
));
}
$this->insertElement($token);
array_pop($this->stack);
break;
case 'image':
$token['name'] = 'img';
return $this->inBody($token);
break;
case 'input':
$this->reconstructActiveFormattingElements();
$element = $this->insertElement($token, false);
$this->form_pointer !== null
? $this->form_pointer->appendChild($element)
: end($this->stack)->appendChild($element);
array_pop($this->stack);
break;
case 'isindex':
if($this->form_pointer === null) {
$this->inBody(array(
'name'=>'body',
'type'=>HTML5::STARTTAG,
'attr'=>array()
));
$this->inBody(array(
'name'=>'hr',
'type'=>HTML5::STARTTAG,
'attr'=>array()
));
$this->inBody(array(
'name'=>'p',
'type'=>HTML5::STARTTAG,
'attr'=>array()
));
$this->inBody(array(
'name'=>'label',
'type'=>HTML5::STARTTAG,
'attr'=>array()
));
$this->insertText('This is a searchable index. '.
'Insert your search keywords here: ');
$attr = $token['attr'];
$attr[] = array('name'=>'name', 'value'=>'isindex');
$this->inBody(array(
'name'=>'input',
'type'=>HTML5::STARTTAG,
'attr'=>$attr
));
$this->insertText('This is a searchable index. '.
'Insert your search keywords here: ');
$this->inBody(array(
'name'=>'label',
'type'=>HTML5::ENDTAG
));
$this->inBody(array(
'name'=>'p',
'type'=>HTML5::ENDTAG
));
$this->inBody(array(
'name'=>'hr',
'type'=>HTML5::ENDTAG
));
$this->inBody(array(
'name'=>'form',
'type'=>HTML5::ENDTAG
));
}
break;
case 'textarea':
$this->insertElement($token);
return HTML5::RCDATA;
break;
case 'iframe': case 'noembed': case 'noframes':
$this->insertElement($token);
return HTML5::CDATA;
break;
case 'select':
$this->reconstructActiveFormattingElements();
$this->insertElement($token);
$this->mode = self::IN_SELECT;
break;
case 'caption': case 'col': case 'colgroup': case 'frame':
case 'frameset': case 'head': case 'option': case 'optgroup':
case 'tbody': case 'td': case 'tfoot': case 'th': case 'thead':
case 'tr':
break;
case 'event-source': case 'section': case 'nav': case 'article':
case 'aside': case 'header': case 'footer': case 'datagrid':
case 'command':
break;
default:
$this->reconstructActiveFormattingElements();
$this->insertElement($token, true, true);
break;
}
break;
case HTML5::ENDTAG:
switch($token['name']) {
case 'body':
if(count($this->stack) < 2 || $this->stack[1]->nodeName !== 'body') {
} elseif(end($this->stack)->nodeName !== 'body') {
}
$this->mode = self::AFTER_BODY;
break;
case 'html':
$this->inBody(array(
'name'=>'body',
'type'=>HTML5::ENDTAG
));
return $this->afterBody($token);
break;
case 'address': case 'blockquote': case 'center': case 'dir':
case 'div': case 'dl': case 'fieldset': case 'listing':
case 'menu': case 'ol': case 'pre': case 'ul':
if($this->elementInScope($token['name'])) {
$this->generateImpliedEndTags();
for($n = count($this->stack) - 1; $n >= 0; $n--) {
if($this->stack[$n]->nodeName === $token['name']) {
$n = -1;
}
array_pop($this->stack);
}
}
break;
case 'form':
if($this->elementInScope($token['name'])) {
$this->generateImpliedEndTags();
} 
if(end($this->stack)->nodeName !== $token['name']) {
} else {
array_pop($this->stack);
}
$this->form_pointer = null;
break;
case 'p':
if($this->elementInScope('p')) {
$this->generateImpliedEndTags(array('p'));
for($n = count($this->stack) - 1; $n >= 0; $n--) {
if($this->elementInScope('p')) {
array_pop($this->stack);
} else {
break;
}
}
}
break;
case 'dd': case 'dt': case 'li':
if($this->elementInScope($token['name'])) {
$this->generateImpliedEndTags(array($token['name']));
for($n = count($this->stack) - 1; $n >= 0; $n--) {
if($this->stack[$n]->nodeName === $token['name']) {
$n = -1;
}
array_pop($this->stack);
}
}
break;
case 'h1': case 'h2': case 'h3': case 'h4': case 'h5': case 'h6':
$elements = array('h1', 'h2', 'h3', 'h4', 'h5', 'h6');
if($this->elementInScope($elements)) {
$this->generateImpliedEndTags();
while($this->elementInScope($elements)) {
array_pop($this->stack);
}
}
break;
case 'a': case 'b': case 'big': case 'em': case 'font':
case 'i': case 'nobr': case 's': case 'small': case 'strike':
case 'strong': case 'tt': case 'u':
while(true) {
for($a = count($this->a_formatting) - 1; $a >= 0; $a--) {
if($this->a_formatting[$a] === self::MARKER) {
break;
} elseif($this->a_formatting[$a]->tagName === $token['name']) {
$formatting_element = $this->a_formatting[$a];
$in_stack = in_array($formatting_element, $this->stack, true);
$fe_af_pos = $a;
break;
}
}
if(!isset($formatting_element) || ($in_stack &&
!$this->elementInScope($token['name']))) {
break;
} elseif(isset($formatting_element) && !$in_stack) {
unset($this->a_formatting[$fe_af_pos]);
$this->a_formatting = array_merge($this->a_formatting);
break;
}
$fe_s_pos = array_search($formatting_element, $this->stack, true);
$length = count($this->stack);
for($s = $fe_s_pos + 1; $s < $length; $s++) {
$category = $this->getElementCategory($this->stack[$s]->nodeName);
if($category !== self::PHRASING && $category !== self::FORMATTING) {
$furthest_block = $this->stack[$s];
}
}
if(!isset($furthest_block)) {
for($n = $length - 1; $n >= $fe_s_pos; $n--) {
array_pop($this->stack);
}
unset($this->a_formatting[$fe_af_pos]);
$this->a_formatting = array_merge($this->a_formatting);
break;
}
$common_ancestor = $this->stack[$fe_s_pos - 1];
if($furthest_block->parentNode !== null) {
$furthest_block->parentNode->removeChild($furthest_block);
}
$bookmark = $fe_af_pos;
$node = $furthest_block;
$last_node = $furthest_block;
while(true) {
for($n = array_search($node, $this->stack, true) - 1; $n >= 0; $n--) {
$node = $this->stack[$n];
if(!in_array($node, $this->a_formatting, true)) {
unset($this->stack[$n]);
$this->stack = array_merge($this->stack);
} else {
break;
}
}
if($node === $formatting_element) {
break;
} elseif($last_node === $furthest_block) {
$bookmark = array_search($node, $this->a_formatting, true) + 1;
}
if($node->hasChildNodes()) {
$clone = $node->cloneNode();
$s_pos = array_search($node, $this->stack, true);
$a_pos = array_search($node, $this->a_formatting, true);
$this->stack[$s_pos] = $clone;
$this->a_formatting[$a_pos] = $clone;
$node = $clone;
}
if($last_node->parentNode !== null) {
$last_node->parentNode->removeChild($last_node);
}
$node->appendChild($last_node);
$last_node = $node;
}
if($last_node->parentNode !== null) {
$last_node->parentNode->removeChild($last_node);
}
$common_ancestor->appendChild($last_node);
$clone = $formatting_element->cloneNode();
while($furthest_block->hasChildNodes()) {
$child = $furthest_block->firstChild;
$furthest_block->removeChild($child);
$clone->appendChild($child);
}
$furthest_block->appendChild($clone);
$fe_af_pos = array_search($formatting_element, $this->a_formatting, true);
unset($this->a_formatting[$fe_af_pos]);
$this->a_formatting = array_merge($this->a_formatting);
$af_part1 = array_slice($this->a_formatting, 0, $bookmark - 1);
$af_part2 = array_slice($this->a_formatting, $bookmark, count($this->a_formatting));
$this->a_formatting = array_merge($af_part1, array($clone), $af_part2);
$fe_s_pos = array_search($formatting_element, $this->stack, true);
$fb_s_pos = array_search($furthest_block, $this->stack, true);
unset($this->stack[$fe_s_pos]);
$s_part1 = array_slice($this->stack, 0, $fb_s_pos);
$s_part2 = array_slice($this->stack, $fb_s_pos + 1, count($this->stack));
$this->stack = array_merge($s_part1, array($clone), $s_part2);
unset($formatting_element, $fe_af_pos, $fe_s_pos, $furthest_block);
}
break;
case 'button': case 'marquee': case 'object':
if($this->elementInScope($token['name'])) {
$this->generateImpliedEndTags();
for($n = count($this->stack) - 1; $n >= 0; $n--) {
if($this->stack[$n]->nodeName === $token['name']) {
$n = -1;
}
array_pop($this->stack);
}
$marker = end(array_keys($this->a_formatting, self::MARKER, true));
for($n = count($this->a_formatting) - 1; $n > $marker; $n--) {
array_pop($this->a_formatting);
}
}
break;
case 'area': case 'basefont': case 'bgsound': case 'br':
case 'embed': case 'hr': case 'iframe': case 'image':
case 'img': case 'input': case 'isindex': case 'noembed':
case 'noframes': case 'param': case 'select': case 'spacer':
case 'table': case 'textarea': case 'wbr':
break;
default:
for($n = count($this->stack) - 1; $n >= 0; $n--) {
$node = end($this->stack);
if($token['name'] === $node->nodeName) {
$this->generateImpliedEndTags();
for($x = count($this->stack) - $n; $x >= $n; $x--) {
array_pop($this->stack);
}
} else {
$category = $this->getElementCategory($node);
if($category !== self::SPECIAL && $category !== self::SCOPING) {
return false;
}
}
}
break;
}
break;
}
}
private function inTable($token) {
$clear = array('html', 'table');
if($token['type'] === HTML5::CHARACTR &&
preg_match('/^[\t\n\x0b\x0c ]+$/', $token['data'])) {
$text = $this->dom->createTextNode($token['data']);
end($this->stack)->appendChild($text);
} elseif($token['type'] === HTML5::COMMENT) {
$comment = $this->dom->createComment($token['data']);
end($this->stack)->appendChild($comment);
} elseif($token['type'] === HTML5::STARTTAG &&
$token['name'] === 'caption') {
$this->clearStackToTableContext($clear);
$this->a_formatting[] = self::MARKER;
$this->insertElement($token);
$this->mode = self::IN_CAPTION;
} elseif($token['type'] === HTML5::STARTTAG &&
$token['name'] === 'colgroup') {
$this->clearStackToTableContext($clear);
$this->insertElement($token);
$this->mode = self::IN_CGROUP;
} elseif($token['type'] === HTML5::STARTTAG &&
$token['name'] === 'col') {
$this->inTable(array(
'name'=>'colgroup',
'type'=>HTML5::STARTTAG,
'attr'=>array()
));
$this->inColumnGroup($token);
} elseif($token['type'] === HTML5::STARTTAG && in_array($token['name'],
array('tbody', 'tfoot', 'thead'))) {
$this->clearStackToTableContext($clear);
$this->insertElement($token);
$this->mode = self::IN_TBODY;
} elseif($token['type'] === HTML5::STARTTAG &&
in_array($token['name'], array('td', 'th', 'tr'))) {
$this->inTable(array(
'name'=>'tbody',
'type'=>HTML5::STARTTAG,
'attr'=>array()
));
return $this->inTableBody($token);
} elseif($token['type'] === HTML5::STARTTAG &&
$token['name'] === 'table') {
$this->inTable(array(
'name'=>'table',
'type'=>HTML5::ENDTAG
));
return $this->mainPhase($token);
} elseif($token['type'] === HTML5::ENDTAG &&
$token['name'] === 'table') {
if(!$this->elementInScope($token['name'], true)) {
return false;
} else {
$this->generateImpliedEndTags();
while(true) {
$current = end($this->stack)->nodeName;
array_pop($this->stack);
if($current === 'table') {
break;
}
}
$this->resetInsertionMode();
}
} elseif($token['type'] === HTML5::ENDTAG && in_array($token['name'],
array('body', 'caption', 'col', 'colgroup', 'html', 'tbody', 'td',
'tfoot', 'th', 'thead', 'tr'))) {
} else {
if(in_array(end($this->stack)->nodeName,
array('table', 'tbody', 'tfoot', 'thead', 'tr'))) {
for($n = count($this->stack) - 1; $n >= 0; $n--) {
if($this->stack[$n]->nodeName === 'table') {
$table = $this->stack[$n];
break;
}
}
if(isset($table) && $table->parentNode !== null) {
$this->foster_parent = $table->parentNode;
} elseif(!isset($table)) {
$this->foster_parent = $this->stack[0];
} elseif(isset($table) && ($table->parentNode === null ||
$table->parentNode->nodeType !== XML_ELEMENT_NODE)) {
$this->foster_parent = $this->stack[$n - 1];
}
}
$this->inBody($token);
}
}
private function inCaption($token) {
if($token['type'] === HTML5::ENDTAG && $token['name'] === 'caption') {
if(!$this->elementInScope($token['name'], true)) {
} else {
$this->generateImpliedEndTags();
while(true) {
$node = end($this->stack)->nodeName;
array_pop($this->stack);
if($node === 'caption') {
break;
}
}
$this->clearTheActiveFormattingElementsUpToTheLastMarker();
$this->mode = self::IN_TABLE;
}
} elseif(($token['type'] === HTML5::STARTTAG && in_array($token['name'],
array('caption', 'col', 'colgroup', 'tbody', 'td', 'tfoot', 'th',
'thead', 'tr'))) || ($token['type'] === HTML5::ENDTAG &&
$token['name'] === 'table')) {
$this->inCaption(array(
'name'=>'caption',
'type'=>HTML5::ENDTAG
));
return $this->inTable($token);
} elseif($token['type'] === HTML5::ENDTAG && in_array($token['name'],
array('body', 'col', 'colgroup', 'html', 'tbody', 'tfoot', 'th',
'thead', 'tr'))) {
} else {
$this->inBody($token);
}
}
private function inColumnGroup($token) {
if($token['type'] === HTML5::CHARACTR &&
preg_match('/^[\t\n\x0b\x0c ]+$/', $token['data'])) {
$text = $this->dom->createTextNode($token['data']);
end($this->stack)->appendChild($text);
} elseif($token['type'] === HTML5::COMMENT) {
$comment = $this->dom->createComment($token['data']);
end($this->stack)->appendChild($comment);
} elseif($token['type'] === HTML5::STARTTAG && $token['name'] === 'col') {
$this->insertElement($token);
array_pop($this->stack);
} elseif($token['type'] === HTML5::ENDTAG &&
$token['name'] === 'colgroup') {
if(end($this->stack)->nodeName === 'html') {
} else {
array_pop($this->stack);
$this->mode = self::IN_TABLE;
}
} elseif($token['type'] === HTML5::ENDTAG && $token['name'] === 'col') {
} else {
$this->inColumnGroup(array(
'name'=>'colgroup',
'type'=>HTML5::ENDTAG
));
return $this->inTable($token);
}
}
private function inTableBody($token) {
$clear = array('tbody', 'tfoot', 'thead', 'html');
if($token['type'] === HTML5::STARTTAG && $token['name'] === 'tr') {
$this->clearStackToTableContext($clear);
$this->insertElement($token);
$this->mode = self::IN_ROW;
} elseif($token['type'] === HTML5::STARTTAG &&
($token['name'] === 'th' ||    $token['name'] === 'td')) {
$this->inTableBody(array(
'name'=>'tr',
'type'=>HTML5::STARTTAG,
'attr'=>array()
));
return $this->inRow($token);
} elseif($token['type'] === HTML5::ENDTAG &&
in_array($token['name'], array('tbody', 'tfoot', 'thead'))) {
if(!$this->elementInScope($token['name'], true)) {
} else {
$this->clearStackToTableContext($clear);
array_pop($this->stack);
$this->mode = self::IN_TABLE;
}
} elseif(($token['type'] === HTML5::STARTTAG && in_array($token['name'],
array('caption', 'col', 'colgroup', 'tbody', 'tfoor', 'thead'))) ||
($token['type'] === HTML5::STARTTAG && $token['name'] === 'table')) {
if(!$this->elementInScope(array('tbody', 'thead', 'tfoot'), true)) {
} else {
$this->clearStackToTableContext($clear);
$this->inTableBody(array(
'name'=>end($this->stack)->nodeName,
'type'=>HTML5::ENDTAG
));
return $this->mainPhase($token);
}
} elseif($token['type'] === HTML5::ENDTAG && in_array($token['name'],
array('body', 'caption', 'col', 'colgroup', 'html', 'td', 'th', 'tr'))) {
} else {
$this->inTable($token);
}
}
private function inRow($token) {
$clear = array('tr', 'html');
if($token['type'] === HTML5::STARTTAG &&
($token['name'] === 'th' || $token['name'] === 'td')) {
$this->clearStackToTableContext($clear);
$this->insertElement($token);
$this->mode = self::IN_CELL;
$this->a_formatting[] = self::MARKER;
} elseif($token['type'] === HTML5::ENDTAG && $token['name'] === 'tr') {
if(!$this->elementInScope($token['name'], true)) {
} else {
$this->clearStackToTableContext($clear);
array_pop($this->stack);
$this->mode = self::IN_TBODY;
}
} elseif($token['type'] === HTML5::STARTTAG && in_array($token['name'],
array('caption', 'col', 'colgroup', 'tbody', 'tfoot', 'thead', 'tr'))) {
$this->inRow(array(
'name'=>'tr',
'type'=>HTML5::ENDTAG
));
return $this->inCell($token);
} elseif($token['type'] === HTML5::ENDTAG &&
in_array($token['name'], array('tbody', 'tfoot', 'thead'))) {
if(!$this->elementInScope($token['name'], true)) {
} else {
$this->inRow(array(
'name'=>'tr',
'type'=>HTML5::ENDTAG
));
return $this->inCell($token);
}
} elseif($token['type'] === HTML5::ENDTAG && in_array($token['name'],
array('body', 'caption', 'col', 'colgroup', 'html', 'td', 'th', 'tr'))) {
} else {
$this->inTable($token);
}
}
private function inCell($token) {
if($token['type'] === HTML5::ENDTAG &&
($token['name'] === 'td' || $token['name'] === 'th')) {
if(!$this->elementInScope($token['name'], true)) {
} else {
$this->generateImpliedEndTags(array($token['name']));
while(true) {
$node = end($this->stack)->nodeName;
array_pop($this->stack);
if($node === $token['name']) {
break;
}
}
$this->clearTheActiveFormattingElementsUpToTheLastMarker();
$this->mode = self::IN_ROW;
}
} elseif($token['type'] === HTML5::STARTTAG && in_array($token['name'],
array('caption', 'col', 'colgroup', 'tbody', 'td', 'tfoot', 'th',
'thead', 'tr'))) {
if(!$this->elementInScope(array('td', 'th'), true)) {
} else {
$this->closeCell();
return $this->inRow($token);
}
} elseif($token['type'] === HTML5::STARTTAG && in_array($token['name'],
array('caption', 'col', 'colgroup', 'tbody', 'td', 'tfoot', 'th',
'thead', 'tr'))) {
if(!$this->elementInScope(array('td', 'th'), true)) {
} else {
$this->closeCell();
return $this->inRow($token);
}
} elseif($token['type'] === HTML5::ENDTAG && in_array($token['name'],
array('body', 'caption', 'col', 'colgroup', 'html'))) {
} elseif($token['type'] === HTML5::ENDTAG && in_array($token['name'],
array('table', 'tbody', 'tfoot', 'thead', 'tr'))) {
if(!$this->elementInScope($token['name'], true)) {
} else {
$this->closeCell();
return $this->inRow($token);
}
} else {
$this->inBody($token);
}
}
private function inSelect($token) {
if($token['type'] === HTML5::CHARACTR) {
$this->insertText($token['data']);
} elseif($token['type'] === HTML5::COMMENT) {
$this->insertComment($token['data']);
} elseif($token['type'] === HTML5::STARTTAG &&
$token['name'] === 'option') {
if(end($this->stack)->nodeName === 'option') {
$this->inSelect(array(
'name'=>'option',
'type'=>HTML5::ENDTAG
));
}
$this->insertElement($token);
} elseif($token['type'] === HTML5::STARTTAG &&
$token['name'] === 'optgroup') {
if(end($this->stack)->nodeName === 'option') {
$this->inSelect(array(
'name'=>'option',
'type'=>HTML5::ENDTAG
));
}
if(end($this->stack)->nodeName === 'optgroup') {
$this->inSelect(array(
'name'=>'optgroup',
'type'=>HTML5::ENDTAG
));
}
$this->insertElement($token);
} elseif($token['type'] === HTML5::ENDTAG &&
$token['name'] === 'optgroup') {
$elements_in_stack = count($this->stack);
if($this->stack[$elements_in_stack - 1]->nodeName === 'option' &&
$this->stack[$elements_in_stack - 2]->nodeName === 'optgroup') {
$this->inSelect(array(
'name'=>'option',
'type'=>HTML5::ENDTAG
));
}
if($this->stack[$elements_in_stack - 1] === 'optgroup') {
array_pop($this->stack);
}
} elseif($token['type'] === HTML5::ENDTAG &&
$token['name'] === 'option') {
if(end($this->stack)->nodeName === 'option') {
array_pop($this->stack);
}
} elseif($token['type'] === HTML5::ENDTAG &&
$token['name'] === 'select') {
if(!$this->elementInScope($token['name'], true)) {
} else {
while(true) {
$current = end($this->stack)->nodeName;
array_pop($this->stack);
if($current === 'select') {
break;
}
}
$this->resetInsertionMode();
}
} elseif($token['name'] === 'select' &&
$token['type'] === HTML5::STARTTAG) {
$this->inSelect(array(
'name'=>'select',
'type'=>HTML5::ENDTAG
));
} elseif(in_array($token['name'], array('caption', 'table', 'tbody',
'tfoot', 'thead', 'tr', 'td', 'th')) && $token['type'] === HTML5::ENDTAG) {
if($this->elementInScope($token['name'], true)) {
$this->inSelect(array(
'name'=>'select',
'type'=>HTML5::ENDTAG
));
$this->mainPhase($token);
}
} else {
}
}
private function afterBody($token) {
if($token['type'] === HTML5::CHARACTR &&
preg_match('/^[\t\n\x0b\x0c ]+$/', $token['data'])) {
$this->inBody($token);
} elseif($token['type'] === HTML5::COMMENT) {
$comment = $this->dom->createComment($token['data']);
$this->stack[0]->appendChild($comment);
} elseif($token['type'] === HTML5::ENDTAG && $token['name'] === 'html') {
$this->phase = self::END_PHASE;
} else {
$this->mode = self::IN_BODY;
return $this->inBody($token);
}
}
private function inFrameset($token) {
if($token['type'] === HTML5::CHARACTR &&
preg_match('/^[\t\n\x0b\x0c ]+$/', $token['data'])) {
$this->insertText($token['data']);
} elseif($token['type'] === HTML5::COMMENT) {
$this->insertComment($token['data']);
} elseif($token['name'] === 'frameset' &&
$token['type'] === HTML5::STARTTAG) {
$this->insertElement($token);
} elseif($token['name'] === 'frameset' &&
$token['type'] === HTML5::ENDTAG) {
if(end($this->stack)->nodeName === 'html') {
} else {
array_pop($this->stack);
$this->mode = self::AFTR_FRAME;
}
} elseif($token['name'] === 'frame' &&
$token['type'] === HTML5::STARTTAG) {
$this->insertElement($token);
array_pop($this->stack);
} elseif($token['name'] === 'noframes' &&
$token['type'] === HTML5::STARTTAG) {
$this->inBody($token);
} else {
}
}
private function afterFrameset($token) {
if($token['type'] === HTML5::CHARACTR &&
preg_match('/^[\t\n\x0b\x0c ]+$/', $token['data'])) {
$this->insertText($token['data']);
} elseif($token['type'] === HTML5::COMMENT) {
$this->insertComment($token['data']);
} elseif($token['name'] === 'html' &&
$token['type'] === HTML5::ENDTAG) {
$this->phase = self::END_PHASE;
} elseif($token['name'] === 'noframes' &&
$token['type'] === HTML5::STARTTAG) {
$this->inBody($token);
} else {
}
}
private function trailingEndPhase($token) {
if($token['type'] === HTML5::DOCTYPE) {
} elseif($token['type'] === HTML5::COMMENT) {
$comment = $this->dom->createComment($token['data']);
$this->dom->appendChild($comment);
} elseif($token['type'] === HTML5::CHARACTR &&
preg_match('/^[\t\n\x0b\x0c ]+$/', $token['data'])) {
$this->mainPhase($token);
} elseif(($token['type'] === HTML5::CHARACTR &&
preg_match('/^[\t\n\x0b\x0c ]+$/', $token['data'])) ||
$token['type'] === HTML5::STARTTAG || $token['type'] === HTML5::ENDTAG) {
$this->phase = self::MAIN_PHASE;
return $this->mainPhase($token);
} elseif($token['type'] === HTML5::EOF) {
}
}
private function insertElement($token, $append = true, $check = false) {
if ($check) {
$token['name'] = preg_replace('/[^a-z0-9-]/i', '', $token['name']);
$token['name'] = ltrim($token['name'], '-0..9');
if ($token['name'] === '') $token['name'] = 'span'; // arbitrary generic choice
}
$el = $this->dom->createElement($token['name']);
foreach($token['attr'] as $attr) {
if(!$el->hasAttribute($attr['name'])) {
$el->setAttribute($attr['name'], $attr['value']);
}
}
$this->appendToRealParent($el);
$this->stack[] = $el;
return $el;
}
private function insertText($data) {
$text = $this->dom->createTextNode($data);
$this->appendToRealParent($text);
}
private function insertComment($data) {
$comment = $this->dom->createComment($data);
$this->appendToRealParent($comment);
}
private function appendToRealParent($node) {
if($this->foster_parent === null) {
end($this->stack)->appendChild($node);
} elseif($this->foster_parent !== null) {
for($n = count($this->stack) - 1; $n >= 0; $n--) {
if($this->stack[$n]->nodeName === 'table' &&
$this->stack[$n]->parentNode !== null) {
$table = $this->stack[$n];
break;
}
}
if(isset($table) && $this->foster_parent->isSameNode($table->parentNode))
$this->foster_parent->insertBefore($node, $table);
else
$this->foster_parent->appendChild($node);
$this->foster_parent = null;
}
}
private function elementInScope($el, $table = false) {
if(is_array($el)) {
foreach($el as $element) {
if($this->elementInScope($element, $table)) {
return true;
}
}
return false;
}
$leng = count($this->stack);
for($n = 0; $n < $leng; $n++) {
$node = $this->stack[$leng - 1 - $n];
if($node->tagName === $el) {
return true;
} elseif($node->tagName === 'table') {
return false;
} elseif($table === true && in_array($node->tagName, array('caption', 'td',
'th', 'button', 'marquee', 'object'))) {
return false;
} elseif($node === $node->ownerDocument->documentElement) {
return false;
}
}
}
private function reconstructActiveFormattingElements() {
$formatting_elements = count($this->a_formatting);
if($formatting_elements === 0) {
return false;
}
$entry = end($this->a_formatting);
if($entry === self::MARKER || in_array($entry, $this->stack, true)) {
return false;
}
for($a = $formatting_elements - 1; $a >= 0; true) {
if($a === 0) {
$step_seven = false;
break;
}
$a--;
$entry = $this->a_formatting[$a];
if($entry === self::MARKER || in_array($entry, $this->stack, true)) {
break;
}
}
while(true) {
if(isset($step_seven) && $step_seven === true) {
$a++;
$entry = $this->a_formatting[$a];
}
$clone = $entry->cloneNode();
end($this->stack)->appendChild($clone);
$this->stack[] = $clone;
$this->a_formatting[$a] = $clone;
if(end($this->a_formatting) !== $clone) {
$step_seven = true;
} else {
break;
}
}
}
private function clearTheActiveFormattingElementsUpToTheLastMarker() {
while(true) {
$entry = end($this->a_formatting);
array_pop($this->a_formatting);
if($entry === self::MARKER) {
break;
}
}
}
private function generateImpliedEndTags($exclude = array()) {
$node = end($this->stack);
$elements = array_diff(array('dd', 'dt', 'li', 'p', 'td', 'th', 'tr'), $exclude);
while(in_array(end($this->stack)->nodeName, $elements)) {
array_pop($this->stack);
}
}
private function getElementCategory($node) {
$name = $node->tagName;
if(in_array($name, $this->special))
return self::SPECIAL;
elseif(in_array($name, $this->scoping))
return self::SCOPING;
elseif(in_array($name, $this->formatting))
return self::FORMATTING;
else
return self::PHRASING;
}
private function clearStackToTableContext($elements) {
while(true) {
$node = end($this->stack)->nodeName;
if(in_array($node, $elements)) {
break;
} else {
array_pop($this->stack);
}
}
}
private function resetInsertionMode() {
$last = false;
$leng = count($this->stack);
for($n = $leng - 1; $n >= 0; $n--) {
$node = $this->stack[$n];
if($this->stack[0]->isSameNode($node)) {
$last = true;
}
if($node->nodeName === 'select') {
$this->mode = self::IN_SELECT;
break;
} elseif($node->nodeName === 'td' || $node->nodeName === 'th') {
$this->mode = self::IN_CELL;
break;
} elseif($node->nodeName === 'tr') {
$this->mode = self::IN_ROW;
break;
} elseif(in_array($node->nodeName, array('tbody', 'thead', 'tfoot'))) {
$this->mode = self::IN_TBODY;
break;
} elseif($node->nodeName === 'caption') {
$this->mode = self::IN_CAPTION;
break;
} elseif($node->nodeName === 'colgroup') {
$this->mode = self::IN_CGROUP;
break;
} elseif($node->nodeName === 'table') {
$this->mode = self::IN_TABLE;
break;
} elseif($node->nodeName === 'head') {
$this->mode = self::IN_BODY;
break;
} elseif($node->nodeName === 'body') {
$this->mode = self::IN_BODY;
break;
} elseif($node->nodeName === 'frameset') {
$this->mode = self::IN_FRAME;
break;
} elseif($node->nodeName === 'html') {
$this->mode = ($this->head_pointer === null)
? self::BEFOR_HEAD
: self::AFTER_HEAD;
break;
} elseif($last) {
$this->mode = self::IN_BODY;
break;
}
}
}
private function closeCell() {
foreach(array('td', 'th') as $cell) {
if($this->elementInScope($cell, true)) {
$this->inCell(array(
'name'=>$cell,
'type'=>HTML5::ENDTAG
));
break;
}
}
}
public function save() {
return $this->dom;
}
}
?>
