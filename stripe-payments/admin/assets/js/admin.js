jQuery(function ($) {

	$('input.asp-select-on-click,textarea.asp-select-on-click').click(function (e) {
		$(this).select();
	});

	$('a.wp-asp-toggle').click(function (e) {
		e.preventDefault();
		var div = $(this).siblings('div');
		if (div.is(':visible')) {
			$(this).removeClass('toggled-on');
			$(this).addClass('toggled-off');
		} else {
			$(this).removeClass('toggled-off');
			$(this).addClass('toggled-on');
		}
		div.slideToggle('fast');
	});
});