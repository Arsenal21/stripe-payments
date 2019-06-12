jQuery(document).ready(function ($) {

    stripeHandlerNG.getFormData = function ($form) {
	var unindexed_array = $form.serializeArray();
	var indexed_array = {};

	$.map(unindexed_array, function (n, i) {
	    indexed_array[n['name']] = n['value'];
	});

	return indexed_array;
    }

    stripeHandlerNG.cents_to_amount = (function (amount, curr) {
	if (!this.is_zero_cents(curr)) {
	    amount = amount / 100;
	}
	return amount;
    });

    stripeHandlerNG.is_zero_cents = (function (curr) {
	if (this.zeroCents.indexOf(curr) === -1) {
	    return false;
	}
	return true;
    });

    stripeHandlerNG.apply_tax = function (amount, data) {
	if (data.tax !== 0) {
	    var tax = Math.round(amount * parseFloat(data.tax) / 100);
	    amount = parseInt(amount) + parseInt(tax);
	}
	return amount;
    };

    stripeHandlerNG.apply_tax_and_shipping = function (amount, data) {
	amount = this.apply_tax(amount, data);
	if (data.shipping !== 0) {
	    amount = amount + parseInt(data.shipping);
	}
	return amount;
    };


    stripeHandlerNG.validate_custom_amount = function (btnId, noTaxAndShipping) {
	var data = window['aspItemDataNG' + btnId];

	var amount = jQuery('input#stripeAmount_' + btnId).val();
	if (this.amountOpts.applySepOpts !== 0) {
	    amount = amount.replace(this.amountOpts.thousandSep, '');
	    amount = amount.replace(this.amountOpts.decimalSep, '.');
	} else {
	    amount = amount.replace(/\$/g, '');
	    amount = amount.replace(/\,/g, '');
	    amount = amount.replace(/\ /g, '');
	}
	amount = parseFloat(amount);

	if (isNaN(amount)) {
	    if (!this.dontShowValidationErrors) {
		jQuery('#error_explanation_' + btnId).hide().html(stripeHandlerNG.strEnterValidAmount).fadeIn('slow');
	    }
	    return false;
	}

	var displayAmount = amount.toFixed(2).toString();
	if (this.amountOpts.applySepOpts !== 0) {
	    displayAmount = displayAmount.replace('.', this.amountOpts.decimalSep);
	}
	if (!this.is_zero_cents(data.currency)) {
	    amount = Math.round(amount * 100);
	}
	if (typeof this.minAmounts[data.currency] !== 'undefined') {
	    if (this.minAmounts[data.currency] > amount) {
		jQuery('#error_explanation_' + data.uniq_id).hide().html(this.strMinAmount + ' ' + this.cents_to_amount(this.minAmounts[data.currency], data.currency)).fadeIn('slow');
		return false;
	    }
	} else if (50 > amount) {
	    jQuery('#error_explanation_' + btnId).hide().html(this.strMinAmount + ' 0.5').fadeIn('slow');
	    return false;
	}
	jQuery('#error_explanation_' + btnId).html('');
	jQuery('input#stripeAmount_' + btnId).val(displayAmount);

	if (typeof noTaxAndShipping === 'undefined') {
	    noTaxAndShipping = false;
	}
	if (!noTaxAndShipping) {
	    amount = this.apply_tax_and_shipping(amount, data);
	}
	return amount;
    }

    stripeHandlerNG.toggle_spinner = function (btnId, show) {
	if (show) {
	    $('div#asp-all-buttons-container-' + btnId).hide();
	    $('div#asp-btn-spinner-container-' + btnId).show();
	} else {
	    $('div#asp-btn-spinner-container-' + btnId).hide();
	    $('div#asp-all-buttons-container-' + btnId).show();
	}
    };

    stripeHandlerNG.doCheckout = function (itemData) {
	var formData = this.getFormData($('form[data-asp-ng-form-id="' + itemData.uniq_id + '"]'));
	var payloadData = {
	    'action': 'asp_ng_get_token',
	    'product_id': itemData.productId,
	    'current_url': this.current_url,
	    'is_live': itemData.is_live,
	    'form_data': formData
	};

	var stripe = Stripe(stripeHandlerNG.pubKey);

	$.post(stripeHandlerNG.ajaxURL, payloadData, function (response) {
	    console.log(response);
	    if (response.success) {
		stripe.redirectToCheckout({
		    sessionId: response.checkoutSessionId
		}).then(function (result) {
		    alert(stripeHandlerNG.strErrorOccurred + ': ' + result.error.message);
		    stripeHandlerNG.toggle_spinner(itemData.uniq_id, false);
		});
	    } else {
		alert(stripeHandlerNG.strErrorOccurred + ': ' + response.errMsg);
		stripeHandlerNG.toggle_spinner(itemData.uniq_id, false);
	    }
	}).fail(function (res) {
	    alert(stripeHandlerNG.strErrorOccurred + ': ' + res.responseText);
	    stripeHandlerNG.toggle_spinner(itemData.uniq_id, false);
	});
    };

    $('[data-asp-ng-button-id]').click(function (e) {
	e.preventDefault();
	var btnId = $(this).data('asp-ng-button-id');
	var itemData = window['aspItemDataNG' + btnId];
	if (itemData.variable) {
	    var amt = stripeHandlerNG.validate_custom_amount(btnId, true);
	    if (!amt) {
		return false;
	    }
	}
	stripeHandlerNG.toggle_spinner(btnId, true);
	stripeHandlerNG.doCheckout(itemData);
    });

    var aspForms = $('form[data-asp-ng-form-id]');

    aspForms.submit(function (e) {
	e.preventDefault();
	var btnId = $(this).data('asp-ng-form-id');
	$('[data-asp-ng-button-id="' + btnId + '"]').click();
    });

    aspForms.find('.asp_product_item_amount_input').change(function () {
	var btnId = $(this).closest('form[data-asp-ng-form-id]').data('asp-ng-form-id');
	var amt = stripeHandlerNG.validate_custom_amount(btnId, true);
	if (!amt) {

	}
    });

});