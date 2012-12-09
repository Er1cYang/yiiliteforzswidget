(function ($) {
var selectCheckedRows, methods,
gridSettings = [];
selectCheckedRows = function (gridId) {
var settings = gridSettings[gridId],
table = $('#'+gridId).children('.'+settings.tableClass);
table.children('tbody').find('input.select-on-check').filter(':checked').each(function () {
$(this).closest('tr').addClass('selected');
});
table.children('thead').find('th input').filter('[type="checkbox"]').each(function () {
var name = this.name.substring(0, this.name.length-4)+'[]',//.. remove '_all' and add '[]''
$checks = $("input[name='"+name+"']", table);
this.checked = $checks.length > 0 && $checks.length === $checks.filter(':checked').length;
});
return this;
};
methods = {
init: function (options) {
var settings = $.extend({
ajaxUpdate: [],
ajaxVar: 'ajax',
pagerClass: 'pager',
loadingClass: 'loading',
filterClass: 'filters',
tableClass: 'items',
selectableRows: 1
}, options || {});
settings.tableClass = settings.tableClass.replace(/\s+/g, '.');
return this.each(function () {
var eventType,
$grid = $(this),
id = $grid.attr('id'),
pagerSelector = '#'+id+' .'+settings.pagerClass.replace(/\s+/g, '.')+' a',
sortSelector = '#'+id+' .'+settings.tableClass+' thead th a.sort-link',
inputSelector = '#'+id+' .'+settings.filterClass+' input, '+'#'+id+' .'+settings.filterClass+' select';
settings.updateSelector = settings.updateSelector
.replace('{page}', pagerSelector)
.replace('{sort}', sortSelector);
gridSettings[id] = settings;
if (settings.ajaxUpdate.length > 0) {
$(document).on('click.yiiGridView', settings.updateSelector, function () {
if (settings.enableHistory && window.History.enabled) {
var url = $(this).attr('href'),
params = $.deparam.querystring(url);
delete params[settings.ajaxVar];
window.History.pushState(null, document.title, $.param.querystring(url.substr(0, url.indexOf('?')), params));
} else {
$('#'+id).yiiGridView('update', {url: $(this).attr('href')});
}
return false;
});
}
$(document).on('change.yiiGridView keydown.yiiGridView', inputSelector, function (event) {
if (event.type === 'keydown') {
if( event.keyCode !== 13) {
return;//only react to enter key
} else {
eventType = 'keydown';
}
} else {
if (eventType === 'keydown') {
eventType = '';
return;
}
}
var data = $(inputSelector).serialize();
if (settings.pageVar !== undefined) {
data+= '&'+settings.pageVar+'=1';
}
if (settings.enableHistory && settings.ajaxUpdate !== false && window.History.enabled) {
var url = $('#'+id).yiiGridView('getUrl'),
params = $.deparam.querystring($.param.querystring(url, data));
delete params[settings.ajaxVar];
window.History.pushState(null, document.title, $.param.querystring(url.substr(0, url.indexOf('?')), params));
} else {
$('#'+id).yiiGridView('update', {data: data});
}
});
if (settings.enableHistory && settings.ajaxUpdate !== false && window.History.enabled) {
$(window).bind('statechange', function() {//Note: We are using statechange instead of popstate
var State = window.History.getState();//Note: We are using History.getState() instead of event.state
$('#'+id).yiiGridView('update', {url: State.url});
});
}
if (settings.selectableRows > 0) {
selectCheckedRows(this.id);
$(document).on('click.yiiGridView', '#'+id+' .'+settings.tableClass+' > tbody > tr', function (e) {
var $currentGrid, $row, isRowSelected, $checks,
$target = $(e.target);
if ($target.closest('td').is('.empty,.button-column') || (e.target.type === 'checkbox' && !$target.hasClass('select-on-check'))) {
return;
}
$row = $(this);
$currentGrid = $('#'+id);
$checks = $('input.select-on-check', $currentGrid);
isRowSelected = $row.toggleClass('selected').hasClass('selected');
if (settings.selectableRows === 1) {
$row.siblings().removeClass('selected');
$checks.prop('checked', false);
}
$('input.select-on-check', $row).prop('checked', isRowSelected);
$("input.select-on-check-all", $currentGrid).prop('checked', $checks.length === $checks.filter(':checked').length);
if (settings.selectionChanged !== undefined) {
settings.selectionChanged(id);
}
});
if (settings.selectableRows > 1) {
$(document).on('click.yiiGridView', '#'+id+' .select-on-check-all', function () {
var $currentGrid = $('#'+id),
$checks = $('input.select-on-check', $currentGrid),
$checksAll = $('input.select-on-check-all', $currentGrid),
$rows = $currentGrid.children('.'+settings.tableClass).children('tbody').children();
if (this.checked) {
$rows.addClass('selected');
$checks.prop('checked', true);
$checksAll.prop('checked', true);
} else {
$rows.removeClass('selected');
$checks.prop('checked', false);
$checksAll.prop('checked', false);
}
if (settings.selectionChanged !== undefined) {
settings.selectionChanged(id);
}
});
}
} else {
$(document).on('click.yiiGridView', '#'+id+' .select-on-check', false);
}
});
},
getKey: function (row) {
return this.children('.keys').children('span').eq(row).text();
},
getUrl: function () {
var sUrl = gridSettings[this.attr('id')].url;
return sUrl || this.children('.keys').attr('title');
},
getRow: function (row) {
var sClass = gridSettings[this.attr('id')].tableClass;
return this.children('.'+sClass).children('tbody').children('tr').eq(row).children();
},
getColumn: function (column) {
var sClass = gridSettings[this.attr('id')].tableClass;
return this.children('.'+sClass).children('tbody').children('tr').children('td:nth-child('+(column+1)+')');
},
update: function (options) {
var customError;
if (options && options.error !== undefined) {
customError = options.error;
delete options.error;
}
return this.each(function () {
var $form,
$grid = $(this),
id = $grid.attr('id'),
settings = gridSettings[id];
$grid.addClass(settings.loadingClass);
options = $.extend({
type: 'GET',
url: $grid.yiiGridView('getUrl'),
success: function (data) {
var $data = $('<div>'+data+'</div>');
$grid.removeClass(settings.loadingClass);
$.each(settings.ajaxUpdate, function (i, el) {
var updateId = '#'+el;
$(updateId).replaceWith($(updateId, $data));
});
if (settings.afterAjaxUpdate !== undefined) {
settings.afterAjaxUpdate(id, data);
}
if (settings.selectableRows > 0) {
selectCheckedRows(id);
}
},
error: function (XHR, textStatus, errorThrown) {
var ret, err;
$grid.removeClass(settings.loadingClass);
if (XHR.readyState === 0 || XHR.status === 0) {
return;
}
if (customError !== undefined) {
ret = customError(XHR);
if (ret !== undefined && !ret) {
return;
}
}
switch (textStatus) {
case 'timeout':
err = 'The request timed out!';
break;
case 'parsererror':
err = 'Parser error!';
break;
case 'error':
if (XHR.status && !/^\s*$/.test(XHR.status)) {
err = 'Error '+XHR.status;
} else {
err = 'Error';
}
if (XHR.responseText && !/^\s*$/.test(XHR.responseText)) {
err = err+': '+XHR.responseText;
}
break;
}
if (settings.ajaxUpdateError !== undefined) {
settings.ajaxUpdateError(XHR, textStatus, errorThrown, err);
} else if (err) {
alert(err);
}
}
}, options || {});
if (options.data !== undefined && options.type === 'GET') {
options.url = $.param.querystring(options.url, options.data);
options.data = {};
}
if (settings.ajaxUpdate !== false) {
options.url = $.param.querystring(options.url, settings.ajaxVar+'='+id);
if (settings.beforeAjaxUpdate !== undefined) {
settings.beforeAjaxUpdate(id, options);
}
$.ajax(options);
} else {//non-ajax mode
if (options.type === 'GET') {
window.location.href = options.url;
} else {//POST mode
$form = $('<form action="'+options.url+'" method="post"></form>').appendTo('body');
if (options.data === undefined) {
options.data = {};
}
if (options.data.returnUrl === undefined) {
options.data.returnUrl = window.location.href;
}
$.each(options.data, function (name, value) {
$form.append($('<input type="hidden" name="t" value=""/>').attr('name', name).val(value));
});
$form.submit();
}
}
});
},
getSelection: function () {
var settings = gridSettings[this.attr('id')],
keys = this.find('.keys span'),
selection = [];
this.find('.'+settings.tableClass).children('tbody').children().each(function (i) {
if ($(this).hasClass('selected')) {
selection.push(keys.eq(i).text());
}
});
return selection;
},
getChecked: function (column_id) {
var settings = gridSettings[this.attr('id')],
keys = this.find('.keys span'),
checked = [];
if (column_id.substring(column_id.length-2) !== '[]') {
column_id = column_id+'[]';
}
this.find('.'+settings.tableClass).children('tbody').children('tr').children('td').children('input[name="'+column_id+'"]').each(function (i) {
if (this.checked) {
checked.push(keys.eq(i).text());
}
});
return checked;
}
};
$.fn.yiiGridView = function (method) {
if (methods[method]) {
return methods[method].apply(this, Array.prototype.slice.call(arguments, 1));
} else if (typeof method === 'object' || !method) {
return methods.init.apply(this, arguments);
} else {
$.error('Method '+method+' does not exist on jQuery.yiiGridView');
return false;
}
};
$.fn.yiiGridView.settings = gridSettings;
$.fn.yiiGridView.getKey = function (id, row) {
return $('#'+id).yiiGridView('getKey', row);
};
$.fn.yiiGridView.getUrl = function (id) {
return $('#'+id).yiiGridView('getUrl');
};
$.fn.yiiGridView.getRow = function (id, row) {
return $('#'+id).yiiGridView('getRow', row);
};
$.fn.yiiGridView.getColumn = function (id, column) {
return $('#'+id).yiiGridView('getColumn', column);
};
$.fn.yiiGridView.update = function (id, options) {
$('#'+id).yiiGridView('update', options);
};
$.fn.yiiGridView.getSelection = function (id) {
return $('#'+id).yiiGridView('getSelection');
};
$.fn.yiiGridView.getChecked = function (id, column_id) {
return $('#'+id).yiiGridView('getChecked', column_id);
};
})(jQuery);