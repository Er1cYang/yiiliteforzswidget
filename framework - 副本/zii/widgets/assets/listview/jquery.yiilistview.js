;(function($) {
$.fn.yiiListView = function(options) {
return this.each(function(){
var settings = $.extend({}, $.fn.yiiListView.defaults, options || {}),
$this = $(this),
id = $this.attr('id');
if(settings.updateSelector == undefined) {
settings.updateSelector = '#'+id+' .'+settings.pagerClass.replace(/\s+/g,'.')+' a, #'+id+' .'+settings.sorterClass.replace(/\s+/g,'.')+' a';
}
$.fn.yiiListView.settings[id] = settings;
if(settings.ajaxUpdate.length > 0) {
$(document).on('click.yiiListView', settings.updateSelector,function(){
if (settings.enableHistory && window.History.enabled) {
var url = $(this).attr('href'),
params = $.deparam.querystring(url);
delete params[settings.ajaxVar];
window.History.pushState(null, null, $.param.querystring(url.substr(0, url.indexOf('?')), params));
} else {
$.fn.yiiListView.update(id, {url: $(this).attr('href')});
}
return false;
});
}
if (settings.enableHistory && settings.ajaxUpdate !== false && window.History.enabled) {
$(window).bind('statechange', function() { // Note: We are using statechange instead of popstate
var State = window.History.getState(); // Note: We are using History.getState() instead of event.state
$.fn.yiiListView.update(id, {url: State.url});
});
}
});
};
$.fn.yiiListView.defaults = {
ajaxUpdate: [],
ajaxVar: 'ajax',
pagerClass: 'pager',
loadingClass: 'loading',
sorterClass: 'sorter'
};
$.fn.yiiListView.settings = {};
$.fn.yiiListView.getKey = function(id, index) {
return $('#'+id+' > div.keys > span:eq('+index+')').text();
};
$.fn.yiiListView.getUrl = function(id) {
var settings = $.fn.yiiListView.settings[id];
return settings.url || $('#'+id+' > div.keys').attr('title');
};
$.fn.yiiListView.update = function(id, options) {
var settings = $.fn.yiiListView.settings[id];
$('#'+id).addClass(settings.loadingClass);
options = $.extend({
type: 'GET',
url: $.fn.yiiListView.getUrl(id),
success: function(data,status) {
$.each(settings.ajaxUpdate, function(i,v) {
var id='#'+v;
$(id).replaceWith($(id,'<div>'+data+'</div>'));
});
if(settings.afterAjaxUpdate != undefined)
settings.afterAjaxUpdate(id, data);
$('#'+id).removeClass(settings.loadingClass);
},
error: function(XMLHttpRequest, textStatus, errorThrown) {
$('#'+id).removeClass(settings.loadingClass);
alert(XMLHttpRequest.responseText);
}
}, options || {});
if(options.data!=undefined && options.type=='GET') {
options.url = $.param.querystring(options.url, options.data);
options.data = {};
}
options.url = $.param.querystring(options.url, settings.ajaxVar+'='+id);
if(settings.beforeAjaxUpdate != undefined)
settings.beforeAjaxUpdate(id);
$.ajax(options);
};
})(jQuery);