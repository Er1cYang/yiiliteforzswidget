
(function($,window){
'$:nomunge';//Used by YUI compressor.
var undefined,
aps = Array.prototype.slice,
decode = decodeURIComponent,
jq_param = $.param,
jq_param_fragment,
jq_deparam,
jq_deparam_fragment,
jq_bbq = $.bbq = $.bbq || {},
jq_bbq_pushState,
jq_bbq_getState,
jq_elemUrlAttr,
jq_event_special = $.event.special,
str_hashchange = 'hashchange',
str_querystring = 'querystring',
str_fragment = 'fragment',
str_elemUrlAttr = 'elemUrlAttr',
str_location = 'location',
str_href = 'href',
str_src = 'src',
re_trim_querystring =/^.*\?|#.*$/g,
re_trim_fragment =/^.*\#/,
re_no_escape,
elemUrlAttr_cache = {};
function is_string( arg ) {
return typeof arg === 'string';
};
function curry( func ) {
var args = aps.call( arguments, 1 );
return function() {
return func.apply( this, args.concat( aps.call( arguments ) ) );
};
};
function get_fragment( url ) {
return url.replace(/^[^#]*#?(.*)$/, '$1' );
};
function get_querystring( url ) {
return url.replace(/(?:^[^?#]*\?([^#]*).*$)?.*/, '$1' );
};
function jq_param_sub( is_fragment, get_func, url, params, merge_mode ) {
var result,
qs,
matches,
url_params,
hash;
if ( params !== undefined ) {
matches = url.match( is_fragment ?/^([^#]*)\#?(.*)$/:/^([^#?]*)\??([^#]*)(#?.*)/);
hash = matches[3] || '';
if ( merge_mode === 2 && is_string( params ) ) {
qs = params.replace( is_fragment ? re_trim_fragment : re_trim_querystring, '' );
} else {
url_params = jq_deparam( matches[2] );
params = is_string( params )
? jq_deparam[ is_fragment ? str_fragment : str_querystring ]( params )
: params;
qs = merge_mode === 2 ? params//passed params replace url params
: merge_mode === 1  ? $.extend( {}, params, url_params )//url params override passed params
: $.extend( {}, url_params, params );//passed params override url params
qs = jq_param( qs );
if ( is_fragment ) {
qs = qs.replace( re_no_escape, decode );
}
}
result = matches[1]+( is_fragment ? '#' : qs || !matches[1] ? '?' : '' )+qs+hash;
} else {
result = get_func( url !== undefined ? url : window[ str_location ][ str_href ] );
}
return result;
};
jq_param[ str_querystring ]                  = curry( jq_param_sub, 0, get_querystring );
jq_param[ str_fragment ] = jq_param_fragment = curry( jq_param_sub, 1, get_fragment );
jq_param_fragment.noEscape = function( chars ) {
chars = chars || '';
var arr = $.map( chars.split(''), encodeURIComponent );
re_no_escape = new RegExp( arr.join('|'), 'g' );
};
jq_param_fragment.noEscape( ',/' );
$.deparam = jq_deparam = function( params, coerce ) {
var obj = {},
coerce_types = { 'true': !0, 'false': !1, 'null': null };
$.each( params.replace(/\+/g, ' ' ).split( '&' ), function(j,v){
var param = v.split( '=' ),
key = decode( param[0] ),
val,
cur = obj,
i = 0,
keys = key.split( '][' ),
keys_last = keys.length-1;
if (/\[/.test( keys[0] ) &&/\]$/.test( keys[ keys_last ] ) ) {
keys[ keys_last ] = keys[ keys_last ].replace(/\]$/, '' );
keys = keys.shift().split('[').concat( keys );
keys_last = keys.length-1;
} else {
keys_last = 0;
}
if ( param.length === 2 ) {
val = decode( param[1] );
if ( coerce ) {
val = val && !isNaN(val)            ?+val//number
: val === 'undefined'             ? undefined//undefined
: coerce_types[val] !== undefined ? coerce_types[val]//true, false, null
: val;//string
}
if ( keys_last ) {
for ( ; i <= keys_last; i++) {
key = keys[i] === '' ? cur.length : keys[i];
cur = cur[key] = i < keys_last
? cur[key] || ( keys[i+1] && isNaN( keys[i+1] ) ? {} : [] )
: val;
}
} else {
if ( $.isArray( obj[key] ) ) {
obj[key].push( val );
} else if ( obj[key] !== undefined ) {
obj[key] = [ obj[key], val ];
} else {
obj[key] = val;
}
}
} else if ( key ) {
obj[key] = coerce
? undefined
: '';
}
});
return obj;
};
function jq_deparam_sub( is_fragment, url_or_params, coerce ) {
if ( url_or_params === undefined || typeof url_or_params === 'boolean' ) {
coerce = url_or_params;
url_or_params = jq_param[ is_fragment ? str_fragment : str_querystring ]();
} else {
url_or_params = is_string( url_or_params )
? url_or_params.replace( is_fragment ? re_trim_fragment : re_trim_querystring, '' )
: url_or_params;
}
return jq_deparam( url_or_params, coerce );
};
jq_deparam[ str_querystring ]                    = curry( jq_deparam_sub, 0 );
jq_deparam[ str_fragment ] = jq_deparam_fragment = curry( jq_deparam_sub, 1 );
$[ str_elemUrlAttr ] || ($[ str_elemUrlAttr ] = function( obj ) {
return $.extend( elemUrlAttr_cache, obj );
})({
a: str_href,
base: str_href,
iframe: str_src,
img: str_src,
input: str_src,
form: 'action',
link: str_href,
script: str_src
});
jq_elemUrlAttr = $[ str_elemUrlAttr ];
function jq_fn_sub( mode, force_attr, params, merge_mode ) {
if ( !is_string( params ) && typeof params !== 'object' ) {
merge_mode = params;
params = force_attr;
force_attr = undefined;
}
return this.each(function(){
var that = $(this),
attr = force_attr || jq_elemUrlAttr()[ ( this.nodeName || '' ).toLowerCase() ] || '',
url = attr && that.attr( attr ) || '';
that.attr( attr, jq_param[ mode ]( url, params, merge_mode ) );
});
};
$.fn[ str_querystring ] = curry( jq_fn_sub, str_querystring );
$.fn[ str_fragment ]    = curry( jq_fn_sub, str_fragment );
jq_bbq.pushState = jq_bbq_pushState = function( params, merge_mode ) {
if ( is_string( params ) &&/^#/.test( params ) && merge_mode === undefined ) {
merge_mode = 2;
}
var has_args = params !== undefined,
url = jq_param_fragment( window[ str_location ][ str_href ],
has_args ? params : {}, has_args ? merge_mode : 2 );
window[ str_location ][ str_href ] = url+(/#/.test( url ) ? '' : '#' );
};
jq_bbq.getState = jq_bbq_getState = function( key, coerce ) {
return key === undefined || typeof key === 'boolean'
? jq_deparam_fragment( key )//'key' really means 'coerce' here
: jq_deparam_fragment( coerce )[ key ];
};
jq_bbq.removeState = function( arr ) {
var state = {};
if ( arr !== undefined ) {
state = jq_bbq_getState();
$.each( $.isArray( arr ) ? arr : arguments, function(i,v){
delete state[ v ];
});
}
jq_bbq_pushState( state, 2 );
};
jq_event_special[ str_hashchange ] = $.extend( jq_event_special[ str_hashchange ], {
add: function( handleObj ) {
var old_handler;
function new_handler(e) {
var hash = e[ str_fragment ] = jq_param_fragment();
e.getState = function( key, coerce ) {
return key === undefined || typeof key === 'boolean'
? jq_deparam( hash, key )//'key' really means 'coerce' here
: jq_deparam( hash, coerce )[ key ];
};
old_handler.apply( this, arguments );
};
if ( $.isFunction( handleObj ) ) {
old_handler = handleObj;
return new_handler;
} else {
old_handler = handleObj.handler;
handleObj.handler = new_handler;
}
}
});
})(jQuery,this);//////Project Home-http://benalman.com/projects/jquery-hashchange-plugin///GitHub-http://github.com/cowboy/jquery-hashchange///Source-http://github.com/cowboy/jquery-hashchange/raw/master/jquery.ba-hashchange.js//(Minified)-http://github.com/cowboy/jquery-hashchange/raw/master/jquery.ba-hashchange.min.js (1.1kb)////About: License////Copyright (c) 2010 "Cowboy" Ben Alman,//Dual licensed under the MIT and GPL licenses.//http://benalman.com/about/license/////About: Examples////This working example, complete with fully commented code, illustrate one way//in which this plugin can be used.////hashchange event-http://benalman.com/code/projects/jquery-hashchange/examples/hashchange/////About: Support and Testing////Information about what version or versions of jQuery this plugin has been//tested with, what browsers it has been tested in, and where the unit tests//reside (so you can test it yourself).////jQuery Versions-1.3.2, 1.4.1, 1.4.2//Browsers Tested-Internet Explorer 6-8, Firefox 2-3.7, Safari 3-4, Chrome, Opera 9.6-10.1.//Unit Tests-http://benalman.com/code/projects/jquery-hashchange/unit/////About: Known issues////While this jQuery hashchange event implementation is quite stable and robust,//there are a few unfortunate browser bugs surrounding expected hashchange//event-based behaviors, independent of any JavaScript window.onhashchange//abstraction. See the following examples for more information:////Chrome: Back Button-http://benalman.com/code/projects/jquery-hashchange/examples/bug-chrome-back-button///Firefox: Remote XMLHttpRequest-http://benalman.com/code/projects/jquery-hashchange/examples/bug-firefox-remote-xhr///WebKit: Back Button in an Iframe-http://benalman.com/code/projects/jquery-hashchange/examples/bug-webkit-hash-iframe///Safari: Back Button from a different domain-http://benalman.com/code/projects/jquery-hashchange/examples/bug-safari-back-from-diff-domain/////About: Release History////1.2-(2/11/2010) Fixed a bug where coming back to a page using this plugin//from a page on another domain would cause an error in Safari 4. Also,//IE6/7 Iframe is now inserted after the body (this actually works),//which prevents the page from scrolling when the event is first bound.//Event can also now be bound before DOM ready, but it won't be usable//before then in IE6/7.//1.1-(1/21/2010) Incorporated document.documentMode test to fix IE8 bug//where browser version is incorrectly reported as 8.0, despite//inclusion of the X-UA-Compatible IE=EmulateIE7 meta tag.//1.0-(1/9/2010) Initial Release. Broke out the jQuery BBQ event.special//window.onhashchange functionality into a separate plugin for users//who want just the basic event & back button support, without all the//extra awesomeness that BBQ provides. This plugin will be included as//part of jQuery BBQ, but also be available separately.
(function($,window,undefined){
'$:nomunge';//Used by YUI compressor.
var fake_onhashchange,
jq_event_special = $.event.special,
str_location = 'location',
str_hashchange = 'hashchange',
str_href = 'href',
browser = $.browser,
mode = document.documentMode,
is_old_ie = browser.msie && ( mode === undefined || mode < 8 ),
supports_onhashchange = 'on'+str_hashchange in window && !is_old_ie;
function get_fragment( url ) {
url = url || window[ str_location ][ str_href ];
return url.replace(/^[^#]*#?(.*)$/, '$1' );
};
$[ str_hashchange+'Delay' ] = 100;
jq_event_special[ str_hashchange ] = $.extend( jq_event_special[ str_hashchange ], {
setup: function() {
if ( supports_onhashchange ) { return false; }
$( fake_onhashchange.start );
},
teardown: function() {
if ( supports_onhashchange ) { return false; }
$( fake_onhashchange.stop );
}
});
fake_onhashchange = (function(){
var self = {},
timeout_id,
iframe,
set_history,
get_history;
function init(){
set_history = get_history = function(val){ return val; };
if ( is_old_ie ) {
iframe = $('<iframe src="javascript:0"/>').hide().insertAfter( 'body' )[0].contentWindow;
get_history = function() {
return get_fragment( iframe.document[ str_location ][ str_href ] );
};
set_history = function( hash, history_hash ) {
if ( hash !== history_hash ) {
var doc = iframe.document;
doc.open().close();
doc[ str_location ].hash = '#'+hash;
}
};
set_history( get_fragment() );
}
};
self.start = function() {
if ( timeout_id ) { return; }
var last_hash = get_fragment();
set_history || init();
(function loopy(){
var hash = get_fragment(),
history_hash = get_history( last_hash );
if ( hash !== last_hash ) {
set_history( last_hash = hash, history_hash );
$(window).trigger( str_hashchange );
} else if ( history_hash !== last_hash ) {
window[ str_location ][ str_href ] = window[ str_location ][ str_href ].replace(/#.*/, '' )+'#'+history_hash;
}
timeout_id = setTimeout( loopy, $[ str_hashchange+'Delay' ] );
})();
};
self.stop = function() {
if ( !iframe ) {
timeout_id && clearTimeout( timeout_id );
timeout_id = 0;
}
};
return self;
})();
})(jQuery,this);