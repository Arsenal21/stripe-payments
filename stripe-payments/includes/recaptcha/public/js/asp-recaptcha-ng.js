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
				//'size': (vars.data.recaptchaInvisible) ? 'invisible' : null,
				'size':  null,
				'callback': function (resp) {
					if (resp !== '' && !parent.data.recaptchaChecked) {
						smokeScreen(true);
						parent.errCont.hide();
						console.log('reCaptcha doing backend check');
						var ajaxData = 'action=asp_recaptcha_check&recaptcha_response=' + resp;
						new ajaxRequest(vars.ajaxURL, ajaxData,
							function (res) {
								var resp = JSON.parse(res.responseText);
								if (resp.error) {
									console.log('fail');
									smokeScreen(false);
									parent.data.recaptchaChecked = false;
									parent.errCont.html(resp.error).hide().fadeIn();
									grecaptcha.reset();
								} else {
									console.log('success');
									parent.errCont.hide();
									parent.data.recaptchaChecked = true;
									// if (vars.data.recaptchaInvisible) {
									// 	if (vars.data.reConfirmToken) {
									// 		vars.data.reConfirmToken = false;
									// 		vars.data.canProceed = true;
									// 		handlePayment();
									// 		return;
									// 	}
									// 	smokeScreen(false);
									// 	triggerEvent(form, 'submit');
									// 	return;
									// }
									smokeScreen(false);
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

	parent.csBeforeRegen = function () {
		if (vars.data.recaptchaInvisible && !parent.data.recaptchaChecked) {
			smokeScreen(true);
			vars.data.doNotProceed = true;
			vars.data.reConfirmToken = true;
			grecaptcha.execute();
		}		
	}

	parent.confirmToken = function () {
		if (vars.data.recaptchaInvisible && !parent.data.recaptchaChecked) {
			smokeScreen(true);
			vars.data.canProceed = false;
			vars.data.reConfirmToken = true;
			grecaptcha.execute();
		}
	}

	parent.submitCanProceed = function () {
		if (parent.data.recaptchaChecked === false) {
			vars.data.canProceed = false;
			if (vars.data.isEvent && vars.data.recaptchaInvisible) {
				//vars.data.canProceed = true;
				vars.data.canProceed =false;
				return;
			}
			// if (vars.data.recaptchaInvisible) {
			// 	smokeScreen(true);
			// 	grecaptcha.execute();
			// } else {
			// 	parent.errCont.html(vars.str.strPleaseCheckCheckbox).hide().fadeIn();
			// }

			parent.errCont.html(vars.str.strPleaseCheckCheckbox).hide().fadeIn();
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