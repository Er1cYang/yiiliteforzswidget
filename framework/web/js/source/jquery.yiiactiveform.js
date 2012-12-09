(function ($) {
var getAFValue = function (o) {
var type,
c = [];
if (!o.length) {
return undefined;
}
if (o[0].tagName.toLowerCase() === 'span') {
o.find(':checked').each(function () {
c.push(this.value);
});
return c.join(',');
}
type = o.attr('type');
if (type === 'checkbox' || type === 'radio') {
return o.filter(':checked').val();
} else {
return o.val();
}
};
$.fn.yiiactiveform = function (options) {
return this.each(function () {
var settings = $.extend({}, $.fn.yiiactiveform.defaults, options || {}),
$form = $(this);
if (settings.validationUrl === undefined) {
settings.validationUrl = $form.attr('action');
}
$.each(settings.attributes, function (i) {
this.value = getAFValue($form.find('#'+this.inputID));
settings.attributes[i] = $.extend({}, {
validationDelay: settings.validationDelay,
validateOnChange: settings.validateOnChange,
validateOnType: settings.validateOnType,
hideErrorMessage: settings.hideErrorMessage,
inputContainer: settings.inputContainer,
errorCssClass: settings.errorCssClass,
successCssClass: settings.successCssClass,
beforeValidateAttribute: settings.beforeValidateAttribute,
afterValidateAttribute: settings.afterValidateAttribute,
validatingCssClass: settings.validatingCssClass
}, this);
});
$form.data('settings', settings);
settings.submitting = false;//whether it is waiting for ajax submission result
var validate = function (attribute, forceValidate) {
if (forceValidate) {
attribute.status = 2;
}
$.each(settings.attributes, function () {
if (this.value !== getAFValue($form.find('#'+this.inputID))) {
this.status = 2;
forceValidate = true;
}
});
if (!forceValidate) {
return;
}
if (settings.timer !== undefined) {
clearTimeout(settings.timer);
}
settings.timer = setTimeout(function () {
if (settings.submitting || $form.is(':hidden')) {
return;
}
if (attribute.beforeValidateAttribute === undefined || attribute.beforeValidateAttribute($form, attribute)) {
$.each(settings.attributes, function () {
if (this.status === 2) {
this.status = 3;
$.fn.yiiactiveform.getInputContainer(this, $form).addClass(this.validatingCssClass);
}
});
$.fn.yiiactiveform.validate($form, function (data) {
var hasError = false;
$.each(settings.attributes, function () {
if (this.status === 2 || this.status === 3) {
hasError = $.fn.yiiactiveform.updateInput(this, data, $form) || hasError;
}
});
if (attribute.afterValidateAttribute !== undefined) {
attribute.afterValidateAttribute($form, attribute, data, hasError);
}
});
}
}, attribute.validationDelay);
};
$.each(settings.attributes, function (i, attribute) {
if (this.validateOnChange) {
$form.find('#'+this.inputID).change(function () {
validate(attribute, false);
}).blur(function () {
if (attribute.status !== 2 && attribute.status !== 3) {
validate(attribute, !attribute.status);
}
});
}
if (this.validateOnType) {
$form.find('#'+this.inputID).keyup(function () {
if (attribute.value !== getAFValue($(this))) {
validate(attribute, false);
}
});
}
});
if (settings.validateOnSubmit) {
$form.on('mouseup keyup', ':submit', function () {
$form.data('submitObject', $(this));
});
var validated = false;
$form.submit(function () {
if (validated) {
validated = false;
return true;
}
if (settings.timer !== undefined) {
clearTimeout(settings.timer);
}
settings.submitting = true;
if (settings.beforeValidate === undefined || settings.beforeValidate($form)) {
$.fn.yiiactiveform.validate($form, function (data) {
var hasError = false;
$.each(settings.attributes, function () {
hasError = $.fn.yiiactiveform.updateInput(this, data, $form) || hasError;
});
$.fn.yiiactiveform.updateSummary($form, data);
if (settings.afterValidate === undefined || settings.afterValidate($form, data, hasError)) {
if (!hasError) {
validated = true;
var $button = $form.data('submitObject') || $form.find(':submit:first');
if ($button.length) {
$button.click();
} else {//no submit button in the form
$form.submit();
}
return;
}
}
settings.submitting = false;
});
} else {
settings.submitting = false;
}
return false;
});
}
$form.bind('reset', function () {
setTimeout(function () {
$.each(settings.attributes, function () {
this.status = 0;
var $error = $form.find('#'+this.errorID),
$container = $.fn.yiiactiveform.getInputContainer(this, $form);
$container.removeClass(
this.validatingCssClass+' '+this.errorCssClass+' '+this.successCssClass
);
$error.html('').hide();
this.value = getAFValue($form.find('#'+this.inputID));
});
$form.find('label, input').each(function () {
$(this).removeClass('error');
});
$('#'+settings.summaryID).hide().find('ul').html('');
if (settings.focus !== undefined && !window.location.hash) {
$form.find(settings.focus).focus();
}
}, 1);
});
if (settings.focus !== undefined && !window.location.hash) {
$form.find(settings.focus).focus();
}
});
};
$.fn.yiiactiveform.getInputContainer = function (attribute, form) {
if (attribute.inputContainer === undefined) {
return form.find('#'+attribute.inputID).closest('div');
} else {
return form.find(attribute.inputContainer).filter(':has("#'+attribute.inputID+'")');
}
};
$.fn.yiiactiveform.updateInput = function (attribute, messages, form) {
attribute.status = 1;
var $error, $container,
hasError = false,
$el = form.find('#'+attribute.inputID);
if ($el.length) {
hasError = messages !== null && $.isArray(messages[attribute.id]) && messages[attribute.id].length > 0;
$error = form.find('#'+attribute.errorID);
$container = $.fn.yiiactiveform.getInputContainer(attribute, form);
$container.removeClass(
attribute.validatingCssClass+' '+attribute.errorCssClass+' '+attribute.successCssClass
);
if (hasError) {
$error.html(messages[attribute.id][0]);
$container.addClass(attribute.errorCssClass);
} else if (attribute.enableAjaxValidation || attribute.clientValidation) {
$container.addClass(attribute.successCssClass);
}
if (!attribute.hideErrorMessage) {
$error.toggle(hasError);
}
attribute.value = getAFValue($el);
}
return hasError;
};
$.fn.yiiactiveform.updateSummary = function (form, messages) {
var settings = $(form).data('settings'),
content = '';
if (settings.summaryID === undefined) {
return;
}
if (messages) {
$.each(settings.attributes, function () {
if ($.isArray(messages[this.id])) {
$.each(messages[this.id], function (j, message) {
content = content+'<li>'+message+'</li>';
});
}
});
}
$('#'+settings.summaryID).toggle(content !== '').find('ul').html(content);
};
$.fn.yiiactiveform.validate = function (form, successCallback, errorCallback) {
var $form = $(form),
settings = $form.data('settings'),
needAjaxValidation = false,
messages = {};
$.each(settings.attributes, function () {
var value,
msg = [];
if (this.clientValidation !== undefined && (settings.submitting || this.status === 2 || this.status === 3)) {
value = getAFValue($form.find('#'+this.inputID));
this.clientValidation(value, msg, this);
if (msg.length) {
messages[this.id] = msg;
}
}
if (this.enableAjaxValidation && !msg.length && (settings.submitting || this.status === 2 || this.status === 3)) {
needAjaxValidation = true;
}
});
if (!needAjaxValidation || settings.submitting && !$.isEmptyObject(messages)) {
if (settings.submitting) {
setTimeout(function () {
successCallback(messages);
}, 200);
} else {
successCallback(messages);
}
return;
}
var $button = $form.data('submitObject'),
extData = '&'+settings.ajaxVar+'='+$form.attr('id');
if ($button && $button.length) {
extData+= '&'+$button.attr('name')+'='+$button.attr('value');
}
$.ajax({
url : settings.validationUrl,
type : $form.attr('method'),
data : $form.serialize()+extData,
dataType : 'json',
success : function (data) {
if (data !== null && typeof data === 'object') {
$.each(settings.attributes, function () {
if (!this.enableAjaxValidation) {
delete data[this.id];
}
});
successCallback($.extend({}, messages, data));
} else {
successCallback(messages);
}
},
error : function () {
if (errorCallback !== undefined) {
errorCallback();
}
}
});
};
$.fn.yiiactiveform.getSettings = function (form) {
return $(form).data('settings');
};
$.fn.yiiactiveform.defaults = {
ajaxVar: 'ajax',
validationUrl: undefined,
validationDelay: 200,
validateOnSubmit : false,
validateOnChange : true,
validateOnType : false,
hideErrorMessage : false,
inputContainer : undefined,
errorCssClass : 'error',
successCssClass : 'success',
validatingCssClass : 'validating',
summaryID : undefined,
timer: undefined,
beforeValidateAttribute: undefined,//function (form, attribute) : boolean
afterValidateAttribute: undefined,//function (form, attribute, data, hasError)
beforeValidate: undefined,//function (form) : boolean
afterValidate: undefined,//function (form, data, hasError) : boolean
attributes : []
};
})(jQuery);