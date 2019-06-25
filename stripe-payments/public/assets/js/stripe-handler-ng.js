var stripeHandlerNG = function (data) {
    var parent = this;
    parent.data = data;
    parent.processing = false;

    this.getFormData = function () {
	var unindexed_array = parent.aspForm.serializeArray();
	var indexed_array = {};

	jQuery.map(unindexed_array, function (n, i) {
	    indexed_array[n['name']] = n['value'];
	});

	return indexed_array;
    };

    this.calc_total = function () {
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
		return false;
	    }
	} else if (50 > amount) {
	    jQuery('#error_explanation_' + parent.data.uniq_id).hide().html(aspFrontVars.strMinAmount + ' 0.5').fadeIn('slow');
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
	parent.doCheckout();
    });

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
	parent.calc_total();
    });

};