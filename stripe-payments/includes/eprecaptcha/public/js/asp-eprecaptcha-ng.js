var epreCaptchaLoaded = false;

var epreCaptchaHandlerNG = function (data) {
	var parent = this;
	parent.data = data;
	parent.data.eprecaptchaChecked = false;
	parent.errCont = jQuery('#eprecaptcha-error');

	parent.init = function () {
		if (epreCaptchaLoaded) {
			parent.epreCaptchaRender();
		}
	}

	parent.epreCaptchaRender = function () {		
		
		if (typeof (parent.eprecaptchaID) === "undefined") {
			parent.eprecaptchaID = grecaptcha.enterprise.render('asp-eprecaptcha-container', {
				'sitekey': vars.data.eprecaptchaSiteKey,
				'badge': 'inline',				
				'callback': function (resp) {
					if (resp !== '' && !parent.data.eprecaptchaChecked) {
						smokeScreen(true);
						parent.errCont.hide();
						console.log('Enterprise reCaptcha doing backend check');
						var ajaxData = 'action=asp_eprecaptcha_check&eprecaptcha_response=' + resp;
						new ajaxRequest(vars.ajaxURL, ajaxData,
							function (res) {
								var resp = JSON.parse(res.responseText);
								if (resp.error) {
									console.log('fail');
									smokeScreen(false);
									parent.data.eprecaptchaChecked = false;
									parent.errCont.html(resp.error).hide().fadeIn();
									grecaptcha.enterprise.reset();
								} else {
									console.log('success');
									parent.errCont.hide();
									parent.data.eprecaptchaChecked = true;									
									smokeScreen(false);
								}
							},
							function (res, errMsg) {
								smokeScreen(false);
								parent.errCont.html(resp.err).hide().fadeIn();
								grecaptcha.enterprise.reset();
							}
						);
					}
				},
				'expired-callback': function () {
					parent.data.eprecaptchaChecked = false;
					parent.data.eprecaptchaKey = '';
					if (!vars.data.recaptchaInvisible) {
						parent.errCont.html(vars.str.strPleaseCheckCheckbox).hide().fadeIn();
					}
				},
			});
		}
	}

	parent.csBeforeRegen = function () {
		if (vars.data.eprecaptchaInvisible && !parent.data.eprecaptchaChecked) {
			smokeScreen(true);
			vars.data.doNotProceed = true;
			vars.data.reConfirmToken = true;
			grecaptcha.enterprise.execute();
		}		
	}

	parent.confirmToken = function () {
		if (vars.data.eprecaptchaInvisible && !parent.data.eprecaptchaChecked) {
			smokeScreen(true);
			vars.data.canProceed = false;
			vars.data.reConfirmToken = true;
			grecaptcha.enterprise.execute();
		}
	}

	parent.submitCanProceed = function () {
		if (parent.data.eprecaptchaChecked === false) {
			vars.data.canProceed = false;
			if (vars.data.isEvent && vars.data.eprecaptchaInvisible) {
				vars.data.canProceed = true;
				return;
			}
			if (vars.data.eprecaptchaInvisible) {
				smokeScreen(true);
				grecaptcha.enterprise.execute();
			} else {
				parent.errCont.html(vars.str.strPleaseCheckCheckbox).hide().fadeIn();
			}
		}
	}
}

function onloadCallback() {	
	epreCaptchaLoaded = true;	
	console.log(vars.data.addons);
	vars.data.addons.forEach(function (addon) {
		if (addon.name === 'epreCaptcha') {
			if (addon.obj !== 'undefined') {				
				addon.obj.epreCaptchaRender();
			} else {
				epreCaptchaLoaded = true;
			}
		}
	});
}