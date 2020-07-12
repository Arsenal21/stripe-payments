var reCaptchaLoaded = false;

var reCaptchaHandlerNG = function (data) {
	var parent = this;
	parent.data = data;
	parent.data.recaptchaChecked = false;
	parent.errCont = jQuery('#recaptcha-error');

	parent.init = function () {
		if (reCaptchaLoaded) {
			parent.reCaptchaRender();
		}
	}

	parent.reCaptchaRender = function () {
		if (typeof (parent.recaptchaID) === "undefined") {
			parent.recaptchaID = grecaptcha.render('asp-recaptcha-container', {
				'sitekey': vars.data.recaptchaSiteKey,
				'badge': 'inline',
				'size': (vars.data.recaptchaInvisible) ? 'invisible' : null,
				'callback': function (resp) {
					if (resp !== '' && !parent.data.recaptchaChecked) {
						smokeScreen(true);
						parent.errCont.hide();
						console.log('reCaptcha doing backend check');
						var ajaxData = 'action=asp_recaptcha_check&recaptcha_response=' + resp;
						new ajaxRequest(vars.ajaxURL, ajaxData,
							function (res) {
								smokeScreen(false);
								var resp = JSON.parse(res.responseText);
								if (resp.error) {
									parent.data.recaptchaChecked = false;
									parent.errCont.html(resp.error).hide().fadeIn();
									grecaptcha.reset();
								} else {
									parent.errCont.hide();
									parent.data.recaptchaChecked = true;
									if (vars.data.recaptchaInvisible) {
										triggerEvent(form, 'submit');
										if (vars.data.reShowPR) {
											if (typeof vars.data.apmPR !== 'undefined') {
												vars.data.apmPR.show();
											} else {
												smokeScreen(false);
											}
											vars.data.reShowPR = false;
										}
									}
								}
							},
							function (res, errMsg) {
								smokeScreen(false);
								parent.errCont.html(resp.err).hide().fadeIn();
								grecaptcha.reset();
							}
						);
					}
				},
				'expired-callback': function () {
					parent.data.recaptchaChecked = false;
					parent.data.recaptchaKey = '';
					if (!vars.data.recaptchaInvisible) {
						parent.errCont.html(vars.str.strPleaseCheckCheckbox).hide().fadeIn();
					}
				},
			});
		}
	}

	parent.submitCanProceed = function () {
		if (parent.data.recaptchaChecked === false) {
			parent.data.canProceed = false;
			if (vars.data.isEvent) {
				vars.data.reShowPR = true;
			}
			if (vars.data.recaptchaInvisible) {
				smokeScreen(true);
				grecaptcha.execute();
			} else {
				parent.errCont.html(vars.str.strPleaseCheckCheckbox).hide().fadeIn();
			}
		}
	}
}

function onloadCallback() {
	console.log('reCaptcha onload');
	vars.data.addons.forEach(function (addon) {
		if (addon.name === 'reCaptcha') {
			if (addon.obj !== 'undefined') {
				addon.obj.reCaptchaRender();
			} else {
				reCaptchaLoaded = true;
			}
		}
	});
}