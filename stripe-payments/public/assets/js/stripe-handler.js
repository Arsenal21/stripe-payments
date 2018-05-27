var wp_asp_prefetched = false;

stripehandler.log = (function ($mod, $msg) {
    if (this.debug) {
	console.log('[StripePayments]' + $mod + ': ' + $msg);
    }
});

stripehandler.apply_tax = (function (amount, data) {
    if (data.tax !== 0) {
	var tax = Math.round(amount * parseInt(data.tax) / 100);
	amount = parseInt(amount) + parseInt(tax);
    }
    return amount;
});

stripehandler.apply_tax_and_shipping = (function (amount, data) {
    amount = stripehandler.apply_tax(amount, data);
    if (data.shipping !== 0) {
	amount = amount + parseInt(data.shipping);
    }
    return amount;
});

stripehandler.cents_to_amount = (function (amount, curr) {
    if (stripehandler.zeroCents.indexOf(curr) === -1) {
	amount = amount / 100;
    }
    return amount;
});

jQuery(document).ready(function () {
    jQuery('input[data-stripe-button-uid]').each(function (ind, obj) {
	var uid = jQuery(obj).data('stripeButtonUid');
	if (typeof (window['stripehandler' + uid]) !== 'undefined') {
	    if (!window['stripehandler' + uid].data.core_attached) {
		wp_asp_add_stripe_handler(window['stripehandler' + uid].data);
		window['stripehandler' + uid].data.core_attached = true;
	    }
	}
    });
});

function wp_asp_validate_custom_quantity(data) {
    var custom_quantity_orig = jQuery('input#stripeCustomQuantity_' + data.uniq_id).val();
    var custom_quantity = parseInt(custom_quantity_orig);
    if (isNaN(custom_quantity)) {
	jQuery('#error_explanation_quantity_' + data.uniq_id).hide().html(stripehandler.strEnterQuantity).fadeIn('slow');
	return false;
    } else if (custom_quantity_orig % 1 !== 0) {
	jQuery('#error_explanation_quantity_' + data.uniq_id).hide().html(stripehandler.strQuantityIsFloat).fadeIn('slow');
	return false;
    } else if (custom_quantity === 0) {
	jQuery('#error_explanation_quantity_' + data.uniq_id).hide().html(stripehandler.strQuantityIsZero).fadeIn('slow');
	return false;
    } else {
	return custom_quantity;
    }
}

function wp_asp_validate_custom_amount(data, noTaxAndShipping) {
    var amount = jQuery('input#stripeAmount_' + data.uniq_id).val();
    if (stripehandler.amountOpts.applySepOpts != 0) {
	amount = amount.replace(stripehandler.amountOpts.thousandSep, '');
	amount = amount.replace(stripehandler.amountOpts.decimalSep, '.');
    } else {
	amount = amount.replace(/\$/g, '');
	amount = amount.replace(/\,/g, '');
    }
    amount = parseFloat(amount);

    if (isNaN(amount)) {
	jQuery('#error_explanation_' + data.uniq_id).hide().html(stripehandler.strEnterValidAmount).fadeIn('slow');
	return false;
    }

    var displayAmount = amount.toString();
    if (stripehandler.amountOpts.applySepOpts != 0) {
	displayAmount = displayAmount.replace('.', stripehandler.amountOpts.decimalSep);
    }
    if (data.zeroCents.indexOf(data.currency) <= -1) {
	amount = Math.round(amount * 100);
    }
    if (typeof stripehandler.minAmounts[data.currency] !== 'undefined') {
	if (stripehandler.minAmounts[data.currency] > amount) {
	    jQuery('#error_explanation_' + data.uniq_id).hide().html(stripehandler.strMinAmount + ' ' + stripehandler.cents_to_amount(stripehandler.minAmounts[data.currency], data.currency)).fadeIn('slow');
	    return false;
	}
    } else if (50 > amount) {
	jQuery('#error_explanation_' + data.uniq_id).hide().html(stripehandler.strMinAmount + ' 0.5').fadeIn('slow');
	return false;
    }
    jQuery('#error_explanation_' + data.uniq_id).html('');
    jQuery('input#stripeAmount_' + data.uniq_id).val(displayAmount);

    if (typeof noTaxAndShipping === 'undefined') {
	noTaxAndShipping = false;
    }
    if (!noTaxAndShipping) {
	amount = stripehandler.apply_tax_and_shipping(amount, data);
    }
    return amount;
}

function wp_asp_can_proceed(data, openHandler) {

    function button_clicked_hooks(data) {
	if (data.addonHooks.length !== 0) {
	    data.addonHooks.forEach(function (hookName) {
		if (typeof window["wp_asp_button_clicked_" + hookName] === "function") {
		    data.executingHook = "wp_asp_button_clicked_" + hookName;
		    data = window["wp_asp_button_clicked_" + hookName](data);
		    data.executingHook = "";
		}
	    });
	}
	return data;
    }

    if (data.variable) {
	var amount = wp_asp_validate_custom_amount(data, false);
	if (amount === false) {
	    return false;
	}
	data.item_price = wp_asp_validate_custom_amount(data, true);
    }

    if (data.custom_quantity === "1") {
	var custom_quantity = wp_asp_validate_custom_quantity(data);
	if (custom_quantity !== false) {
	    amount = custom_quantity * data.item_price;
	    amount = stripehandler.apply_tax_and_shipping(amount, data);
	} else {
	    return false;
	}
    }

    var description = data.description;

    if (description === '') {

	var descr_quantity = 1;

	if (typeof custom_quantity !== "undefined") {
	    descr_quantity = custom_quantity;
	} else {
	    descr_quantity = data.quantity;
	}

	description = ' X ' + descr_quantity;
    }

    if (data.custom_field != '0') {

	var customInput = jQuery('#asp-custom-field-' + data.uniq_id);
	if (typeof (customInput.attr('data-asp-custom-mandatory')) !== "undefined") {
	    if (customInput.attr('type') === 'text' && customInput.val() === '') {
		jQuery('#custom_field_error_explanation_' + data.uniq_id).hide().html(stripehandler.strPleaseFillIn).fadeIn('slow');
		return false;
	    }
	    if (customInput.attr('type') === 'checkbox' && customInput.prop('checked') !== true) {
		jQuery('#custom_field_error_explanation_' + data.uniq_id).hide().html(stripehandler.strPleaseCheckCheckbox).fadeIn('slow');
		return false;
	    }
	}
    }

    if (data.tos == 1) {
	if (jQuery('#asp-tos-' + data.uniq_id).prop('checked') !== true) {
	    jQuery('#tos_error_explanation_' + data.uniq_id).hide().html(stripehandler.strMustAcceptTos).fadeIn('slow');
	    return false;
	}
    }

    data.canProceed = true;

    data = button_clicked_hooks(data);

    if (!data.canProceed) {
	return false;
    }

    if (!openHandler) {
	return true;
    }

    if (typeof (amount) === "undefined") {
	data.handler.open({
	    description: description
	});
    } else {
	data.handler.open({
	    amount: amount,
	    description: description
	});
    }

    return true;
}

function wp_asp_hadnle_token(data, token, args) {
    jQuery('input#stripeToken_' + data.uniq_id).val(token.id);
    jQuery('input#stripeTokenType_' + data.uniq_id).val(token.type);
    jQuery('input#stripeEmail_' + data.uniq_id).val(token.email);
    form = jQuery('form#stripe_form_' + data.uniq_id);
    for (var key in args) {
	inputName = key.replace(/[^_]+/g, function (word) {
	    return word.replace(/^./, function (first) {
		return first.toUpperCase();
	    });
	});
	inputName = 'stripe' + inputName.replace(/[_]/g, '');
	form.append('<input type="hidden" name="' + inputName + '" value="' + args[key] + '">')
    }
    jQuery('#stripe_button_' + data.uniq_id).html(jQuery('.asp-processing-cont').html());
    jQuery('#stripe_button_' + data.uniq_id).prop('disabled', true);

    if (data.addonHooks.length !== 0) {
	data.addonHooks.forEach(function (hookName) {
	    if (typeof window["wp_asp_before_form_submit_" + hookName] === "function") {
		window["wp_asp_before_form_submit_" + hookName](data);
	    }
	});
    }

    form.append('<input type="hidden" name="clickProcessed" value="1">');
    form.off('submit');
    form.submit();
}

function wp_asp_add_stripe_handler(data) {

    if (!wp_asp_prefetched) {
	wp_asp_prefetched = true;
	wp_asp_check_handler(data);
    }

    function wp_asp_check_handler(data) {
	if (typeof (data.handler) == "undefined") {

	    var handler_opts = {
		key: stripehandler.key,
		amount: data.amount,
		locale: data.locale,
		description: data.description,
		name: data.name,
		currency: data.currency,
		image: data.image,
		allowRememberMe: data.allowRememberMe,
		token: function (token, args) {
		    wp_asp_hadnle_token(data, token, args);
		}
	    };

	    if (data.url !== '') {
		handler_opts.url = data.url;
	    }

	    if (data.billingAddress) {
		handler_opts.billingAddress = data.billingAddress;
		handler_opts.shippingAddress = data.shippingAddress;
	    }

	    if (data.customer_email !== '') {
		handler_opts.email = data.customer_email;
	    }

	    if (data.addonHooks.length !== 0) {
		data.addonHooks.forEach(function (hookName) {
		    if (typeof window["wp_asp_before_handler_configure_" + hookName] === "function") {
			handler_opts = window["wp_asp_before_handler_configure_" + hookName](handler_opts, data);
		    }
		});
	    }
	    data.handler = StripeCheckout.configure(handler_opts);
	}
    }

    jQuery('#stripe_form_' + data.uniq_id).on('submit', function (e) {
	e.preventDefault();
	jQuery('#stripe_button_' + data.uniq_id).click();
    });

    jQuery('#stripe_button_' + data.uniq_id).on('click', function (e) {

	e.preventDefault();

	wp_asp_check_handler(data);

	return wp_asp_can_proceed(data, true);

    });
    jQuery('#stripe_button_' + data.uniq_id).prop("disabled", false);
}