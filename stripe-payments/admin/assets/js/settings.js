var wp_asp_urlHash = window.location.hash.substr(1);
var wp_asp_transHash = aspSettingsData.transHash;

var wp_asp_currencies = aspSettingsData.currencies;

if (wp_asp_urlHash === '') {
	if (wp_asp_transHash !== '') {
		wp_asp_urlHash = wp_asp_transHash;
	} else {
		wp_asp_urlHash = 'general';
	}
}
jQuery(function ($) {
	var wp_asp_activeTab = "";
	$('div.asp-settings-spinner-container').remove();
	$('a.nav-tab').click(function (e) {
		if ($(this).attr('data-tab-name') !== wp_asp_activeTab) {
			$('div.wp-asp-tab-container[data-tab-name="' + wp_asp_activeTab + '"]').hide();
			$('a.nav-tab[data-tab-name="' + wp_asp_activeTab + '"]').removeClass('nav-tab-active');
			wp_asp_activeTab = $(this).attr('data-tab-name');
			$('div.wp-asp-tab-container[data-tab-name="' + wp_asp_activeTab + '"]').show();
			$(this).addClass('nav-tab-active');
			$('input#wp-asp-urlHash').val(wp_asp_activeTab);
			if (window.location.hash !== wp_asp_activeTab) {
				window.location.hash = wp_asp_activeTab;
			}
		}
	});

	$('.wp-asp-curr-sel-all-btn').click(function (e) {
		e.preventDefault();
		$('.wp-asp-allowed-currencies').find('input[type="checkbox"]').prop('checked', true);
	});
	$('.wp-asp-curr-sel-none-btn').click(function (e) {
		e.preventDefault();
		$('.wp-asp-allowed-currencies').find('input[type="checkbox"]').prop('checked', false);
	});
	$('.wp-asp-curr-sel-invert-btn').click(function (e) {
		e.preventDefault();
		$('.wp-asp-allowed-currencies').find('input[type="checkbox"]').each(function (ind, el) {
			$(el).prop('checked', !$(el).prop('checked'));
		});
	});

	$('select[name="AcceptStripePayments-settings[custom_field_type]"]').change(function () {
		if ($(this).val() === 'text') {
			$(this).parents('tr').next().show();
		} else {
			$(this).parents('tr').next().hide();
		}
	});

	$('select[name="AcceptStripePayments-settings[custom_field_validation]"]').change(function () {
		if ($(this).val() === 'custom') {
			$('div.wp-asp-custom-field-validation-custom-input-cont').show();
		} else {
			$('div.wp-asp-custom-field-validation-custom-input-cont').hide();
		}
	});

	$('#asp_clear_log_btn').click(function (e) {
		e.preventDefault();
		if (confirm(aspSettingsData.str.logClearConfirm)) {
			var req = jQuery.ajax({
				url: ajaxurl,
				type: "post",
				data: { action: "asp_clear_log" }
			});
			req.done(function (data) {
				if (data === '1') {
					alert(aspSettingsData.str.logCleared);
				} else {
					alert(aspSettingsData.str.errorOccured + ' ' + data);
				}
			});
		}
	});

	$('#wp_asp_curr_code').change(function () {
		$('#wp_asp_curr_symb').val(wp_asp_currencies[$('#wp_asp_curr_code').val()][1]);
	});

	$('#wp_asp_curr_code').change();

	$('select[name="AcceptStripePayments-settings[custom_field_validation]"]').change();

	$('select[name="AcceptStripePayments-settings[custom_field_type]"]').change();

	$('a.nav-tab[data-tab-name="' + wp_asp_urlHash + '"]').trigger('click');

	$('div.wp-asp-settings-cont').show();
});
