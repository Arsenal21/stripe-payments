var hCaptchaLoaded = false;

var hCaptchaHandlerNG = function (data) {
	var parent = this;
	parent.data = data;
	parent.data.hcaptchaChecked = false;
	parent.errCont = jQuery('#hcaptcha-error');

	parent.init = function () {
		if (hCaptchaLoaded) {
			parent.hCaptchaRender();
		}
	}

	parent.hCaptchaRender = function () {
		if (typeof (parent.hcaptchaID) !== "undefined") {
			return;
		}
		parent.hcaptchaID = hcaptcha.render('asp-hcaptcha-container', {
			'sitekey': vars.data.hcaptchaSiteKey,
			'badge': 'inline',
			'size': (vars.data.hcaptchaInvisible) ? 'invisible' : null,
			'callback': function (resp) {
				if (resp !== '' && !parent.data.hcaptchaChecked) {
					smokeScreen(true);
					parent.errCont.hide();
					console.log('hCaptcha doing backend check');
					var ajaxData = 'action=asp_hcaptcha_check&hcaptcha_response=' + resp;
					new ajaxRequest(vars.ajaxURL, ajaxData,
						function (res) {
							var resp = JSON.parse(res.responseText);
							if (resp.error) {
								console.log('fail');
								smokeScreen(false);
								parent.data.hcaptchaChecked = false;
								parent.errCont.html(resp.error).hide().fadeIn();
								hcaptcha.reset();
							} else {
								console.log('success');
								parent.errCont.hide();
								parent.data.hcaptchaChecked = true;
								if (vars.data.hcaptchaInvisible) {
									if (vars.data.reConfirmToken) {
										vars.data.reConfirmToken = false;
										vars.data.canProceed = true;
										handlePayment();
										return;
									}
									smokeScreen(false);
									triggerEvent(form, 'submit');
									return;
								}
								smokeScreen(false);
							}
						},
						function (res, errMsg) {
							smokeScreen(false);
							parent.errCont.html(resp.err).hide().fadeIn();
							hcaptcha.reset();
						}
					);
				}
			},
			'expired-callback': function () {
				parent.data.hcaptchaChecked = false;
				parent.data.hcaptchaKey = '';
				if (!vars.data.hcaptchaInvisible) {
					parent.errCont.html(vars.str.strPleaseCheckCheckbox).hide().fadeIn();
				}
			},
		});
	}

	parent.csBeforeRegen = function () {
		if (vars.data.hcaptchaInvisible && !parent.data.hcaptchaChecked) {
			smokeScreen(true);
			vars.data.doNotProceed = true;
			vars.data.reConfirmToken = true;
			hcaptcha.execute();
		}
	}

	parent.confirmToken = function () {
		if (vars.data.hcaptchaInvisible && !parent.data.hcaptchaChecked) {
			smokeScreen(true);
			vars.data.canProceed = false;
			vars.data.reConfirmToken = true;
			hcaptcha.execute();
		}
	}

	parent.submitCanProceed = function () {
		if (parent.data.hcaptchaChecked === false) {
			vars.data.canProceed = false;
			if (vars.data.isEvent && vars.data.hcaptchaInvisible) {
				vars.data.canProceed = true;
				return;
			}
			if (vars.data.hcaptchaInvisible) {
				smokeScreen(true);
				hcaptcha.execute();
			} else {
				parent.errCont.html(vars.str.strPleaseCheckCheckbox).hide().fadeIn();
			}
		}
	}
}

function onloadCallback() {
	console.log('hCaptcha onload');
	vars.data.addons.forEach(function (addon) {
		if (addon.name === 'hCaptcha') {
			if (addon.obj !== 'undefined') {
				addon.obj.hCaptchaRender();
			} else {
				hCaptchaLoaded = true;
			}
		}
	});
}