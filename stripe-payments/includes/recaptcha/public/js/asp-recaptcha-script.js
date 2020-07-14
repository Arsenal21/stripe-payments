function wp_asp_button_clicked_recaptcha(data) {
    if (typeof (data.recaptchaChecked) === "undefined" || !data.recaptchaChecked) {
	data.canProceed = false;
	jQuery('#asp-recaptcha-modal-' + data.uniq_id).iziModal({
	    title: 'reCaptcha',
	    transitionIn: 'fadeInUp',
	    transitionOut: 'fadeOutDown',
	    closeOnEscape: false,
	    overlayClose: false,
	    fullscreen: false,
	    restoreDefaultContent: false,
	});
	jQuery('#asp-recaptcha-modal-' + data.uniq_id).iziModal('open');
	if (typeof (data.recaptchaID) === "undefined") {
	    data.recaptchaID = grecaptcha.render('asp-recaptcha-container-' + data.uniq_id, {
		'sitekey': data.recaptchaSiteKey,
		'callback': function (key) {
		    if (key !== '') {
			data.recaptchaChecked = true;
			data.canProceed = true;
			jQuery('form#stripe_form_' + data.uniq_id).append('<input type="hidden" name="recaptchaKey" value="' + key + '">');
			grecaptcha.reset(data.recaptchaID);
			jQuery('#asp-recaptcha-modal-' + data.uniq_id).iziModal('close');
			jQuery('#asp-recaptcha-container-' + data.uniq_id).html('');
			if (typeof data.buttonClicked !== "undefined") {
			    if (typeof data.buttonClicked.click === 'function') {
				data.buttonClicked.click();
			    }
			} else {
			    jQuery('#stripe_button_' + data.uniq_id).click();
			}
		    }
		},
	    });
	}
    }
    return data;
}