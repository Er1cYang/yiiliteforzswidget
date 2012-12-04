;(function($) {
$.yii = {
version : '1.0',
submitForm : function (element, url, params) {
var f = $(element).parents('form')[0];
if (!f) {
f = document.createElement('form');
f.style.display = 'none';
element.parentNode.appendChild(f);
f.method = 'POST';
}
if (typeof url == 'string' && url != '') {
f.action = url;
}
if (element.target != null) {
f.target = element.target;
}
var inputs = [];
$.each(params, function(name, value) {
var input = document.createElement("input");
input.setAttribute("type", "hidden");
input.setAttribute("name", name);
input.setAttribute("value", value);
f.appendChild(input);
inputs.push(input);
});
$(f).data('submitObject', $(element));
$(f).trigger('submit');
$.each(inputs, function() {
f.removeChild(this);
});
}
};
})(jQuery);
