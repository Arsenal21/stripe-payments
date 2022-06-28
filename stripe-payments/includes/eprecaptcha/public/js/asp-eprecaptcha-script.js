function wp_asp_button_clicked_recaptcha(data) {	
    if (typeof (data.eprecaptchaChecked) === "undefined" || !data.eprecaptchaChecked) {
	data.canProceed = false;
	jQuery('#asp-recaptcha-modal-' + data.uniq_id).iziModal({
	    title: 'Enterprise reCaptcha',
	    transitionIn: 'fadeInUp',
	    transitionOut: 'fadeOutDown',
	    closeOnEscape: false,
	    overlayClose: false,
	    fullscreen: false,
	    restoreDefaultContent: false,
	});

	jQuery('#asp-eprecaptcha-modal-' + data.uniq_id).iziModal('open');

	if (typeof (data.eprecaptchaID) === "undefined") {
	    data.eprecaptchaID = grecaptcha.enterprise.render('asp-eprecaptcha-container-' + data.uniq_id, {
		'sitekey': data.eprecaptchaSiteKey,
		'callback': function (key) {
		    if (key !== '') {
			data.eprecaptchaChecked = true;
			data.canProceed = true;

			jQuery('form#stripe_form_' + data.uniq_id).append('<input type="hidden" name="eprecaptchaKey" value="' + key + '">');

			grecaptcha.enterprise.reset(data.eprecaptchaID);
			jQuery('#asp-eprecaptcha-modal-' + data.uniq_id).iziModal('close');
			jQuery('#asp-eprecaptcha-container-' + data.uniq_id).html('');
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