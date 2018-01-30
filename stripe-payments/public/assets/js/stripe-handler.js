var wp_asp_prefetched = false;

stripehandler.log = (function ($mod, $msg) {
    if (this.debug) {
	console.log('[StripePayments]' + $mod + ': ' + $msg);
    }
});

jQuery(document).ready(function () {
    jQuery('input[data-stripe-button-uid]').each(function (ind, obj) {
	var uid = jQuery(obj).data('stripeButtonUid');
	wp_asp_add_stripe_handler(window['stripehandler' + uid].data);
    });
});

function wp_asp_validate_custom_amount(data) {
    var amount = jQuery('input#stripeAmount_' + data.uniq_id).val();
    amount = amount.replace(/\$/g, '');
    amount = amount.replace(/\,/g, '');
    amount = parseFloat(amount);
    if (isNaN(amount)) {
	jQuery('#error_explanation_' + data.uniq_id).hide().html(stripehandler.strEnterValidAmount).fadeIn('slow');
	return false;
    } else if (amount < 0.5) {
	jQuery('#error_explanation_' + data.uniq_id).hide().html(stripehandler.strMinAmount).fadeIn('slow');
	return false;
    } else {
	jQuery('#error_explanation_' + data.uniq_id).html('');
	jQuery('input#stripeAmount_' + data.uniq_id).val(amount);
	if (data.zeroCents.indexOf(data.currency) <= -1) {
	    amount = amount * 100;
	}
    }
    return amount;
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

    if (data.addonHooks) {
	data.addonHooks.forEach(function (hookName) {
	    if (typeof window["wp_asp_before_form_submit_" + hookName] === "function") {
		window["wp_asp_before_form_submit_" + hookName](data);
	    }
	});
    }

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
		url: data.url,
		allowRememberMe: data.allowRememberMe,
		token: function (token, args) {
		    wp_asp_hadnle_token(data, token, args);
		}
	    };

	    if (data.billingAddress) {
		handler_opts.billingAddress = data.billingAddress;
		handler_opts.shippingAddress = data.shippingAddress;
	    }

	    if (data.addonHooks) {
		data.addonHooks.forEach(function (hookName) {
		    if (typeof window["wp_asp_before_handler_configure_" + hookName] === "function") {
			handler_opts = window["wp_asp_before_handler_configure_" + hookName](handler_opts, data);
		    }
		});
	    }
	    data.handler = StripeCheckout.configure(handler_opts);
	}
    }

    jQuery('#stripe_button_' + data.uniq_id).on('click', function (e) {

	function button_clicked_hooks(data) {
	    if (data.addonHooks) {
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

	e.preventDefault();

	data.canProceed = true;

	wp_asp_check_handler(data);
	if (!data.variable && data.custom_quantity !== '1' && data.custom_field != 1) {
	    data = button_clicked_hooks(data);
	    if (!data.canProceed) {
		return false;
	    }
	    data.handler.open();
	    return true;
	}

	var description = data.description;

	if (data.variable) {
	    var amount = wp_asp_validate_custom_amount(data);
	    if (amount === false) {
		return false;
	    }
	}

	if (data.custom_quantity === "1") {
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
		if (!isNaN(amount)) {
		    amount = custom_quantity * amount;
		} else {
		    amount = custom_quantity * data.amount;
		}
	    }
	}

	if (description === '') {

	    var descr_amount = amount;

	    var descr_quantity = 1;

	    if (typeof custom_quantity !== "undefined") {
		descr_quantity = custom_quantity;
	    } else {
		descr_quantity = data.quantity;
	    }

	    descr_amount = descr_amount / descr_quantity;

	    if (data.zeroCents.indexOf(data.currency) <= -1) {
		descr_amount = descr_amount / 100;
	    }
	    description = descr_quantity + ' X ' + descr_amount + ' ' + data.currency;
	}

	if (data.custom_field != '0') {

	    var customInput = jQuery('#asp-custom-field-' + data.uniq_id);
	    if (typeof (customInput.attr('data-asp-custom-mandatory')) !== "undefined") {
		if (customInput.attr('type') === 'text' && customInput.val() === '') {
		    jQuery('#custom_field_error_explanation_' + data.uniq_id).hide().html('Please fill in this field.').fadeIn('slow');
		    return false;
		}
		if (customInput.attr('type') === 'checkbox' && customInput.prop('checked') !== true) {
		    jQuery('#custom_field_error_explanation_' + data.uniq_id).hide().html('Please check this checkbox.').fadeIn('slow');
		    return false;
		}
	    }
	}

	data = button_clicked_hooks(data);

	if (!data.canProceed) {
	    return false;
	}

	data.handler.open({
	    amount: amount,
	    description: description,
	});
    });
}