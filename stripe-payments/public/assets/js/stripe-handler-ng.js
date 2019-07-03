var stripeHandlerNG = function (data) {
	var parent = this;
	parent.data = data;
	parent.processing = false;
	parent.data.origPrice = data.item_price;
	parent.dsp = {};
	parent.dsp.Tax = false;
	parent.dsp.Total = false;
	parent.dsp.Price = false;
	parent.dsp.newPrice = false;

	this.getFormData = function () {
		var unindexed_array = parent.aspForm.serializeArray();
		var indexed_array = {};

		jQuery.map(unindexed_array, function (n, i) {
			indexed_array[n['name']] = n['value'];
		});

		return indexed_array;
	};

	this.formatMoney = function (n) {
		n = parent.cents_to_amount(n);
		var c = isNaN(c = Math.abs(aspFrontVars.currencyFormat.c)) ? 2 : aspFrontVars.currencyFormat.c,
			d = d === undefined ? "." : aspFrontVars.currencyFormat.d,
			t = t === undefined ? "," : aspFrontVars.currencyFormat.t,
			s = n < 0 ? "-" : "",
			i = String(parseInt(n = Math.abs(Number(n) || 0).toFixed(c))),
			j = (j = i.length) > 3 ? j % 3 : 0;

		var result = s + (j ? i.substr(0, j) + t : "") + i.substr(j).replace(/(\d{3})(?=\d)/g, "$1" + t) + (c ? d + Math.abs(n - i).toFixed(c).slice(2) : "");
		return (aspFrontVars.currencyFormat.pos !== "right" ? aspFrontVars.currencyFormat.s + result : result + aspFrontVars.currencyFormat.s);
	};

	this.calc_total = function () {
		parent.data.item_price = parent.apply_coupon(parent.data.item_price);
		parent.data.taxAmount = Math.round(parent.data.item_price * parseFloat(parent.data.tax) / 100) * parent.data.quantity;
		parent.data.total = parent.apply_tax_and_shipping(parent.data.item_price);
	};

	this.cents_to_amount = function (amount) {
		if (!parent.is_zero_cents()) {
			amount = amount / 100;
		}
		return amount;
	};

	this.is_zero_cents = function () {
		if (aspFrontVars.zeroCents.indexOf(parent.data.currency) === -1) {
			return false;
		}
		return true;
	};

	this.apply_tax = function (amount) {
		if (parent.data.tax !== 0) {
			var tax = Math.round(amount * parseFloat(parent.data.tax) / 100);
			amount = parseInt(amount) + parseInt(tax);
		}
		return amount;
	};

	this.apply_tax_and_shipping = function (amount) {
		amount = parent.apply_tax(amount);
		if (parent.data.shipping !== 0) {
			amount = amount + parseInt(parent.data.shipping);
		}
		return amount;
	};

	this.apply_coupon = (function (amount) {
		var discountAmount = 0;
		if (typeof parent.data.discount !== "undefined") {
			if (parent.data.discountType === 'perc') {
				discountAmount = Math.round(amount * (parent.data.discount / 100));
			} else {
				discountAmount = parent.data.discount * 100;
				if (parent.is_zero_cents(parent.data.currency)) {
					discountAmount = Math.round(discountAmount / 100);
				}
			}
			amount = amount - discountAmount;
			parent.data.discountAmount = discountAmount;
		}
		return amount;
	});

	this.updateAmountsDisplay = function () {
		parent.calc_total();

		if (parent.dsp.Price) {
			if (parent.data.discountAmount) {
				parent.dsp.Price.addClass('asp_line_through');
				parent.dsp.Price.html(parent.formatMoney(parent.data.origPrice));
				parent.dsp.newPrice.html(parent.formatMoney(parent.data.item_price));
			} else {
				parent.dsp.Price.removeClass('asp_line_through');
				parent.dsp.newPrice.html('');
				parent.dsp.Price.html(parent.formatMoney(parent.data.item_price));
			}
		}

		if (parent.dsp.Tax) {
			parent.dsp.Tax.html(parent.formatMoney(parent.data.taxAmount));
		}

		if (parent.dsp.Total) {
			parent.dsp.Total.show();
			parent.dsp.Total.find('.asp_tot_current_price').html(parent.formatMoney(parent.data.total));
		}
	};

	this.validate_custom_amount = function (noTaxAndShipping) {

		var amount = jQuery('input#stripeAmount_' + parent.data.uniq_id).val();
		if (aspFrontVars.amountOpts.applySepOpts != 0) {
			amount = amount.replace(this.aspFrontVars.amountOpts.thousandSep, '');
			amount = amount.replace(this.aspFrontVars.amountOpts.decimalSep, '.');
		} else {
			amount = amount.replace(/\$/g, '');
			amount = amount.replace(/\,/g, '');
			amount = amount.replace(/\ /g, '');
		}
		amount = parseFloat(amount);

		if (isNaN(amount)) {
			if (!aspFrontVars.dontShowValidationErrors) {
				jQuery('#error_explanation_' + parent.data.uniq_id).hide().html(aspFrontVars.strEnterValidAmount).fadeIn('slow');
				jQuery('input#stripeAmount_' + parent.data.uniq_id).focus();
			}
			return false;
		}

		var displayAmount = amount.toFixed(2).toString();
		if (aspFrontVars.amountOpts.applySepOpts !== 0) {
			displayAmount = displayAmount.replace('.', aspFrontVars.amountOpts.decimalSep);
		}
		if (!parent.is_zero_cents()) {
			amount = Math.round(amount * 100);
		}
		if (typeof aspFrontVars.minAmounts[parent.data.currency] !== 'undefined') {
			if (aspFrontVars.minAmounts[parent.data.currency] > amount) {
				jQuery('#error_explanation_' + parent.data.uniq_id).hide().html(aspFrontVars.strMinAmount + ' ' +
					parent.cents_to_amount(aspFrontVars.minAmounts[parent.data.currency])).fadeIn('slow');
				jQuery('input#stripeAmount_' + parent.data.uniq_id).focus();
				return false;
			}
		} else if (50 > amount) {
			jQuery('#error_explanation_' + parent.data.uniq_id).hide().html(aspFrontVars.strMinAmount + ' 0.5').fadeIn('slow');
			jQuery('input#stripeAmount_' + parent.data.uniq_id).focus();
			return false;
		}
		jQuery('#error_explanation_' + parent.data.uniq_id).html('');
		jQuery('input#stripeAmount_' + parent.data.uniq_id).val(displayAmount);

		if (typeof noTaxAndShipping === 'undefined') {
			noTaxAndShipping = false;
		}
		if (!noTaxAndShipping) {
			amount = parent.apply_tax_and_shipping(amount);
		}
		parent.data.item_price = amount;
		parent.calc_total();
		parent.updateAmountsDisplay();
		return amount;
	};

	this.toggle_spinner = function (show) {
		if (show) {
			jQuery('div#asp-all-buttons-container-' + parent.data.uniq_id).hide();
			jQuery('div#asp-btn-spinner-container-' + parent.data.uniq_id).show();
		} else {
			jQuery('div#asp-btn-spinner-container-' + parent.data.uniq_id).hide();
			jQuery('div#asp-all-buttons-container-' + parent.data.uniq_id).show();
		}
	};

	this.doCheckout = function () {
		parent.toggle_spinner(true);
		var formData = this.getFormData();
		var payloadData = {
			'action': 'asp_ng_get_token',
			'product_id': parent.data.productId,
			'current_url': aspFrontVars.current_url,
			'is_live': parent.data.is_live,
			'form_data': formData
		};

		var stripe = Stripe(aspFrontVars.pubKey);
		parent.processing = true;

		jQuery.post(aspFrontVars.ajaxURL, payloadData, function (response) {
			if (response.success) {
				stripe.redirectToCheckout({
					sessionId: response.checkoutSessionId
				}).then(function (result) {
					alert(aspFrontVars.strErrorOccurred + ': ' + result.error.message);
					parent.processing = false;
					parent.toggle_spinner(false);
				});
			} else {
				alert(aspFrontVars.strErrorOccurred + ': ' + response.errMsg);
				parent.processing = false;
				parent.toggle_spinner(false);
			}
		}).fail(function (res) {
			alert(aspFrontVars.strErrorOccurred + ': ' + res.responseText);
			parent.processing = false;
			parent.toggle_spinner(false);
		});
	};

	this.validateCustomFields = function (singleInput) {
		if (parent.data.custom_field != '1') {
			return true;
		}
		parent.aspForm.find('.asp_product_custom_field_error').hide();
		var valid = true;
		if (typeof (parent.data.custom_field_validation_regex) !== "undefined" && parent.data.custom_field_validation_regex !== '') {
			try {
				var re = new RegExp(parent.data.custom_field_validation_regex);
			} catch (error) {
				alert(aspFrontVars.strInvalidCFValidationRegex + ' ' + parent.data.custom_field_validation_regex + "\n" + error);
				return valid = false;
			}
		}
		var inputs;
		if (singleInput) {
			inputs = singleInput;
		} else {
			inputs = parent.customInputs;
		}
		jQuery.each(inputs, function (id, customInput) {
			var customInput = jQuery(customInput);
			if (typeof (customInput.attr('data-asp-custom-mandatory')) !== "undefined") {
				if (customInput.attr('type') === 'text' && customInput.val() === '') {
					jQuery(this).siblings('.asp_product_custom_field_error').hide().html(aspFrontVars.strPleaseFillIn).fadeIn('slow');
					jQuery(this).focus();
					return valid = false;
				}
				if (customInput.attr('type') === 'checkbox' && customInput.prop('checked') !== true) {
					jQuery(this).parent().siblings('.asp_product_custom_field_error').hide().html(stripehandler.strPleaseCheckCheckbox).fadeIn('slow');
					jQuery(this).focus();
					return valid = false;
				}
			}
			if (customInput.attr('class') === 'asp_product_custom_field_input' && customInput.attr('type') === 'text' && typeof re !== "undefined") {
				if (customInput.val() && !re.test(customInput.val())) {
					jQuery(this).siblings('.asp_product_custom_field_error').hide().html(parent.data.custom_field_validation_err_msg).fadeIn('slow');
					jQuery(this).focus();
					return valid = false;
				}
			}
		});
		return valid;
	}

	jQuery('[data-asp-ng-button-id="' + parent.data.uniq_id + '"]').click(function (e) {
		e.preventDefault();
		if (parent.processing) {
			return false;
		}
		if (parent.data.variable) {
			var amt = parent.validate_custom_amount(true);
			if (!amt) {
				return false;
			}
		}
		var canProceed = false;
		canProceed = parent.validateCustomFields();
		if (!canProceed) {
			return false;
		}
		parent.doCheckout();
	});

	this.cont = jQuery('[data-asp-ng-cont-id="' + this.data.uniq_id + '"]');
	parent.dsp.Price = parent.cont.find('.asp_price_amount');
	parent.dsp.newPrice = parent.cont.find('.asp_new_price_amount');
	parent.dsp.Tax = parent.cont.find('.asp_price_tax_section').find('span');
	parent.dsp.Total = parent.cont.find('.asp_price_full_total');

	this.aspForm = jQuery('form[data-asp-ng-form-id="' + this.data.uniq_id + '"]');

	this.aspForm.submit(function (e) {
		e.preventDefault();
		if (parent.processing) {
			return false;
		}
		jQuery('[data-asp-ng-button-id="' + parent.data.uniq_id + '"]').click();
	});

	this.aspForm.find('.asp_product_item_amount_input').change(function () {
		if (parent.processing) {
			return false;
		}
		parent.validate_custom_amount(true);
	});

	jQuery('button#asp-redeem-coupon-btn-' + parent.data.uniq_id).click(function (e) {
		e.preventDefault();
		if (!parent.oCouponInfo) {
			parent.oCouponInfo = parent.aspForm.find('div#asp-coupon-info-' + parent.data.uniq_id + ' span');
		}
		if (!parent.oRemoveCouponBtn) {
			parent.oRemoveCouponBtn = parent.aspForm.find('#asp-remove-coupon-' + parent.data.uniq_id);
		}
		if (!parent.oCouponInput) {
			parent.oCouponInput = parent.aspForm.find('input#asp-coupon-field-' + parent.data.uniq_id);
		}

		var couponCode = parent.oCouponInput.val();
		if (!couponCode) {
			return false;
		}
		var aspCouponBtn = jQuery(this);
		var aspCouponSpinner = jQuery(jQuery.parseHTML('<div class="asp-spinner">Loading...</div>'));
		parent.oCouponInfo.html('');
		aspCouponBtn.prop('disabled', true);
		aspCouponBtn.after(aspCouponSpinner);
		var ajaxData = {
			'action': 'asp_check_coupon',
			'product_id': parent.data.product_id,
			'coupon_code': couponCode,
			'curr': parent.data.currency,
			'amount': parent.data.item_price,
			'tax': parent.data.tax,
			'shipping': parent.data.shipping
		};
		jQuery.post(aspFrontVars.ajaxURL, ajaxData, function (response) {
			if (response.success) {
				parent.data.discount = response.discount;
				parent.data.discountType = response.discountType;
				parent.data.couponCode = response.code;
				parent.oCouponInfo.html(response.discountStr);
				aspCouponBtn.hide();
				parent.oCouponInput.parent().hide();
				parent.oRemoveCouponBtn.show();
				parent.updateAmountsDisplay();
				parent.oRemoveCouponBtn.on('click', function (e) {
					e.preventDefault();
					parent.oCouponInfo.html('');
					parent.oRemoveCouponBtn.hide();
					parent.oCouponInput.val('');
					parent.oCouponInput.parent().show();
					aspCouponBtn.show();
					delete parent.data.discount;
					delete parent.data.discountType;
					delete parent.data.couponCode;
					delete parent.data.newAmountFmt;
					parent.data.item_price = parent.data.origPrice;
					parent.updateAmountsDisplay();
				});
			} else {
				parent.oCouponInfo.html(response.msg);
			}
			aspCouponSpinner.remove();
			aspCouponBtn.prop('disabled', false);
		});
	});

	jQuery('input#asp-coupon-field-' + parent.data.uniq_id).keydown(function (e) {
		if (e.keyCode === 13) {
			e.preventDefault();
			jQuery('button#asp-redeem-coupon-btn-' + parent.data.uniq_id).click();
			return false;
		}
	});

	parent.customInputs = parent.aspForm.find('.asp_product_custom_field_input').toArray();

	jQuery(parent.customInputs).change(function () {
		parent.validateCustomFields(jQuery(this));
	});

};