var stripeHandlerNG = function (data) {

	jQuery('input#stripeAmount_' + data.uniq_id).keydown(function (e) {
		if (e.keyCode === 13) {
			e.preventDefault();
			jQuery('#asp_ng_button_' + data.uniq_id).click();
			return false;
		}
	});

	this.validateAmount = function () {
		var amount = jQuery('input#stripeAmount_' + data.uniq_id).val();
		data.amountOpts = { applySepOpts: 0 };
		data.minAmounts = [];
		if (data.amountOpts.applySepOpts != 0) {
			amount = amount.replace(data.amountOpts.thousandSep, '');
			amount = amount.replace(data.amountOpts.decimalSep, '.');
		} else {
			amount = amount.replace(/\$/g, '');
			amount = amount.replace(/\,/g, '');
			amount = amount.replace(/\ /g, '');
		}
		amount = parseFloat(amount);

		if (isNaN(amount)) {
			jQuery('#error_explanation_' + data.uniq_id).hide().html('Enter valid amount').fadeIn('slow');
			return false;
		}

		var displayAmount = amount.toFixed(2).toString();
		if (data.amountOpts.applySepOpts != 0) {
			displayAmount = displayAmount.replace('.', data.amountOpts.decimalSep);
		}
		if (data.zeroCents.indexOf(data.currency) <= -1) {
			//			amount = Math.round(amount);
		}
		// if (typeof data.minAmounts[data.currency] !== 'undefined') {
		// 	if (data.minAmounts[data.currency] > amount) {
		// 		jQuery('#error_explanation_' + data.uniq_id).hide().html(data.strMinAmount + ' ' + parent.cents_to_amount(stripehandler.minAmounts[data.currency], data.currency)).fadeIn('slow');
		// 		return false;
		// 	}
		// } else if (50 > amount) {
		// 	jQuery('#error_explanation_' + data.uniq_id).hide().html(data.strMinAmount + ' 0.5').fadeIn('slow');
		// 	return false;
		// }
		jQuery('#error_explanation_' + data.uniq_id).html('');
		jQuery('input#stripeAmount_' + data.uniq_id).val(displayAmount);

		return amount;
	}

	this.handleModal = function (show) {
		if (parent.data.show_custom_amount_input) {
			var pass_amount = parent.validateAmount();
			if (!pass_amount) {
				return false;
			}
		}

		if (!parent.modal) {
			parent.modal = jQuery('div[data-asp-iframe-prod-id="' + parent.data.product_id + '"][id="asp-payment-popup-' + parent.data.uniq_id + '"]');
			if (parent.modal.length === 0) {
				jQuery('body').append('<div id="asp-payment-popup-' + parent.data.uniq_id + '" style="display: none;" data-asp-iframe-prod-id="' + parent.data.product_id + '" class="asp-popup-iframe-cont"><iframe frameborder="0" allowtransparency="true" class="asp-popup-iframe" allow="payment" allowpaymentrequest="true" src="' + parent.data.iframe_url + '"></iframe></div>');
				parent.modal = jQuery('#asp-payment-popup-' + parent.data.uniq_id);
			}
			if (show) {
				parent.modal.css('display', 'flex').hide().fadeIn();
			}
			var iframe = parent.modal.find('iframe');
			parent.iframe = iframe;
			iframe.on('load', function () {
				if (parent.redirectToResult) {
					window.location.href = iframe[0].contentWindow.location.href;
					return false;
				}

				if (pass_amount) {
					iframe.contents().find('#amount').val(pass_amount);
					iframe[0].contentWindow.triggerEvent(iframe.contents().find('#amount')[0], 'change');
				}

				iframe[0].contentWindow['doSelfSubmit'] = data.doSelfSubmit;
				var closebtn = iframe.contents().find('#modal-close-btn');
				if (show) {
					closebtn.fadeIn();
				} else {
					closebtn.css('display', 'inline');
				}
				closebtn.on('click', function () {
					jQuery('html').css('overflow', parent.documentElementOrigOverflow);
					parent.modal.fadeOut();
				});
				parent.iForm = iframe.contents().find('form#payment-form');
				parent.iForm.on('submit', function (e) {
					e.preventDefault();
					if (parent.form_submitted) {
						return false;
					}
					var token = parent.iForm.find('input#payment-intent').val();
					if (token !== '') {
						if (parent.form.length === 0) {
							console.log('Waiting for iframe to complete loading');
							parent.redirectToResult = true;
							return true;
						}
						var hiddenInputsDiv = parent.form.find('div.asp-child-hidden-fields');
						parent.iForm.find('[name!=""]').each(function () {
							if (jQuery(this).attr('name')) {
								jQuery(this).attr('name', 'asp_' + jQuery(this).attr('name'));
								var clonedItem = jQuery(this).clone();
								if (jQuery(this).is('select')) {
									clonedItem.prop('selectedIndex', jQuery(this).prop('selectedIndex'));
								}
								hiddenInputsDiv.append(clonedItem);
							}
						});
						console.log('Parent form submit');
						parent.form_submitted = true;
						parent.form.submit();
					}
					return false;
				});
			});
		} else {
			if (pass_amount) {
				parent.iframe.contents().find('#amount').val(pass_amount);
				parent.iframe[0].contentWindow.triggerEvent(parent.iframe.contents().find('#amount')[0], 'change');
			}
			parent.modal.css('display', 'flex').hide().fadeIn();
		}

	};

	var parent = this;
	parent.preload = false;
	parent.data = data;
	parent.form = jQuery('form#asp_ng_form_' + parent.data.uniq_id);
	parent.documentElementOrigOverflow = jQuery('html').css('overflow');
	jQuery('#asp_ng_button_' + parent.data.uniq_id).prop('disabled', false);
	if (parent.preload) {
		parent.handleModal(false);
	}
	jQuery('#asp_ng_button_' + parent.data.uniq_id).click(function (e) {
		jQuery('html').css('overflow', 'hidden');
		e.preventDefault();
		parent.handleModal(true);
	});
};

function WPASPAttachToAElement(el) {
	var hrefStr = jQuery(el).attr('href');
	if (!hrefStr) {
		return false;
	}
	var meinHref = hrefStr.match(/asp_action=show_pp&product_id=[0-9]*(.*)/);
	if (meinHref[0]) {
		var productId = meinHref[0].match(/product_id=([0-9]+)/);
		if (productId[1]) {
			var params = '';
			if (meinHref[1]) {
				params = meinHref[1];
			}
			WPASPAttach(el, productId[1], params);
		}
	}
	return true;
}

function WPASPAttach(el, prodId, params) {

	function elHandler(e) {
		e.preventDefault();
		sg.handleModal(true);
	}

	var uniqId = Math.random().toString(36).substr(2, 9);
	var item_price = jQuery(el).data('asp-price');
	if (item_price) {
		params += '&price=' + item_price;
	}
	var sg = new stripeHandlerNG({ 'uniq_id': uniqId, 'product_id': prodId, 'doSelfSubmit': true, 'iframe_url': wpASPNG.iframeUrl + '&product_id=' + prodId + params });
	jQuery(el).off('click');
	jQuery(el).on('click', el, elHandler);
}

function WPASPDocReady(callbackFunc) {
	if (document.readyState !== 'loading') {
		callbackFunc();
	} else if (document.addEventListener) {
		document.addEventListener('DOMContentLoaded', callbackFunc);
	} else {
		document.attachEvent('onreadystatechange', function () {
			if (document.readyState === 'complete') {
				callbackFunc();
			}
		});
	}
}

WPASPDocReady(function () {
	if (typeof wpaspInitOnDocReady !== 'undefined') {
		console.log('ASP: Creating buttons on page load');
		wpaspInitOnDocReady.forEach(function (data) {
			new stripeHandlerNG(data);
		});
	}
	jQuery('[class*="asp-attach-product-"]').each(function (id, el) {
		var classStr = jQuery(el).attr('class');
		var meinClass = classStr.match(/asp-attach-product-[0-9]*/);
		if (meinClass[0]) {
			var productId = meinClass[0].match(/([0-9].*)/);
			if (productId[0]) {
				WPASPAttach(el, productId[0], '');
			}
		}
	});

	jQuery('a[href*="asp_action=show_pp&product_id="]').each(function (id, el) {
		WPASPAttachToAElement(el);
	});

});