/* eslint-disable no-undef */
var errorCont = document.getElementById('global-error');
if (vars.fatal_error) {
	showPopup();
	throw new Error(vars.fatal_error);
}
try {
	var stripe = Stripe(vars.stripe_key);
	var elements = stripe.elements({ locale: vars.data.checkout_lang });
} catch (error) {
	showPopup();
	errorCont.innerHTML = error;
	errorCont.style.display = 'block';
	jQuery('#payment-form').hide();
	throw new Error(error);
}
vars.data.temp = [];

if (vars.data.amount_variable && vars.data.hide_amount_input !== '1') {
	var amountInput = document.getElementById('amount');
	var amountErr = document.getElementById('amount-error');
	amountInput.addEventListener('change', function () {
		amount = validate_custom_amount();
		if (amount !== false) {
			vars.data.item_price = amount;
			updateAllAmounts();
		}
	});
}
if (vars.data.custom_quantity) {
	var quantityInput = document.getElementById('quantity');
	var quantityErr = document.getElementById('quantity-error');
	quantityInput.addEventListener('change', function () {
		quantity = validate_custom_quantity();
		if (quantity !== false) {
			vars.data.quantity = quantity;
			updateAllAmounts();
		}
	});
}

if (vars.data.currency_variable) {
	var currencyInput = document.getElementById('currency');
	currencyInput.addEventListener('change', function () {
		vars.data.currency = currencyInput.value || currencyInput.options[currencyInput.selectedIndex];
		vars.currencyFormat.s = currencyInput.options[currencyInput.selectedIndex].getAttribute('data-asp-curr-sym');
		updateAllAmounts();
	});
}

if (vars.data.custom_field) {
	var customFieldInput = document.getElementById('asp-custom-field');
	var customFieldErr = document.getElementById('custom-field-error');
	if (customFieldInput) {
		customFieldInput.addEventListener('change', function () {
			validate_custom_field();
		});
	}
}

if (vars.data.coupons_enabled) {
	var couponBtn = document.getElementById('apply-coupon-btn');
	var couponRemoveBtn = document.getElementById('remove-coupon-btn');
	var couponResCont = document.getElementById('coupon-res-cont');
	var couponInputCont = document.getElementById('coupon-input-cont');
	var couponInput = document.getElementById('coupon-code');
	var couponErr = document.getElementById('coupon-err');
	var couponInfo = document.getElementById('coupon-info');
	couponInput.addEventListener('keydown', function (e) {
		if (e.keyCode === 13) {
			e.preventDefault();
			couponBtn.click();
			return false;
		}
	});
	couponBtn.addEventListener('click', function (e) {
		e.preventDefault();
		couponErr.style.display = 'none';
		if (couponInput.value === '') {
			return false;
		}
		couponBtn.disabled = true;
		smokeScreen(true);
		var ajaxData = 'action=asp_pp_check_coupon&product_id=' + vars.data.product_id + '&coupon_code=' + couponInput.value;
		new ajaxRequest(vars.ajaxURL, ajaxData,
			function (res) {
				console.log(res);
				var resp = JSON.parse(res.responseText);
				if (resp.err) {
					delete (vars.data.coupon);
					console.log(resp.err);
					showFormInputErr(resp.err, couponErr, couponInput);
				} else {
					vars.data.coupon = resp;
					console.log(vars.data.coupon);
					calcTotal();
					couponInfo.innerHTML = vars.data.coupon.code + ': ' + ' - ';
					if (vars.data.coupon.discount_type === 'perc') {
						couponInfo.innerHTML = couponInfo.innerHTML + vars.data.coupon.discount + '%';
					} else {
						couponInfo.innerHTML = couponInfo.innerHTML + formatMoney(vars.data.coupon.discount_amount);
					}
					couponResCont.style.display = 'block';
					couponInputCont.style.display = 'none';
					if (is_full_discount()) {
						jQuery('[data-pm-id="def"]').click();
						jQuery('#pm-select-cont').hide();
						jQuery('#card-cont').hide();
					}
				}
				updateAllAmounts();
				couponBtn.disabled = false;
				smokeScreen(false);
			},
			function (res, errMsg) {
				errorCont.innerHTML = errMsg;
				errorCont.style.display = 'block';
				couponBtn.disabled = false;
				smokeScreen(false);
			}
		);
	});
	couponRemoveBtn.addEventListener('click', function () {
		if (is_full_discount()) {
			jQuery('#pm-select-cont').show();
			jQuery('#card-cont').show();
			if (vars.data.shipping_orig) {
				vars.data.shipping = vars.data.shipping_orig;
			}
		}
		delete (vars.data.coupon);
		jQuery('#order-coupon-line').remove();
		couponInput.value = '';
		couponResCont.style.display = 'none';
		couponInputCont.style.display = 'block';
		updateAllAmounts();
	});
}

var amount = vars.data.amount;
var clientSecAmount = 0;
var clientSecCurrency = '';

var style = {
	base: {
		fontSize: '16px',
	}
};

var submitBtn = document.getElementById('submit-btn');
var piInput = document.getElementById('payment-intent');
var cardErrorCont = document.getElementById('card-errors');
var form = document.getElementById('payment-form');

if (vars.data.tos) {
	var tosInput = document.getElementById('tos');
	var tosInputErr = document.getElementById('tos-error');
	tosInput.addEventListener('change', function () {
		tosInputErr.style.display = 'none';
	});
}

if (!jQuery.isEmptyObject(vars.data.variations)) {
	var varInputs = document.getElementsByClassName('variations-input');
	vars.data.temp.prePopupDisplayVariationsUpdate = true;
	for (var i = 0; i < varInputs.length; i++) {
		(function (index) {
			varInputs[index].addEventListener('change', function () {
				var grpId = this.getAttribute('data-asp-variations-group-id');
				var varId = this.value;
				if (Object.getOwnPropertyNames(vars.data.variations).length !== 0) {
					if (!vars.data.variations.applied) {
						vars.data.variations.applied = [];
					}
					vars.data.variations.applied[grpId] = varId;
					if (!vars.data.temp.prePopupDisplayVariationsUpdate) {
						updateAllAmounts();
					}
				}
			});
			if (varInputs[index].checked || varInputs[index].tagName === 'SELECT') {
				triggerEvent(varInputs[index], 'change');
			}
		})(i);
	}
	vars.data.temp.prePopupDisplayVariationsUpdate = false;
	updateAllAmounts();
}

if (vars.data.billing_address && vars.data.shipping_address) {
	var billshipSwitch = document.getElementById('same-bill-ship-addr');
	var billaddrCont = document.getElementById('billing-addr-cont');
	var shipaddrCont = document.getElementById('shipping-addr-cont');
	var baddrToggles = document.getElementsByClassName('baddr-toggle');
	var baddrHide = document.getElementsByClassName('baddr-hide');
	var saddrRequired = document.getElementsByClassName('saddr-required');
	var itemsArr = [];
	for (var i = 0; i < baddrToggles.length; i++) {
		(function (index) {
			itemsArr.push(baddrToggles[index]);
		})(i);
	}

	billshipSwitch.addEventListener('change', function () {
		var i;
		if (billshipSwitch.checked) {
			for (i = 0; i < itemsArr.length; i++) {
				(function (index) {
					attr = itemsArr[index].getAttribute('data-class-save');
					itemsArr[index].className = attr;
				})(i);
			}
			for (i = 0; i < baddrHide.length; i++) {
				(function (index) {
					baddrHide[index].style.display = 'inline-block';
				})(i);
			}
			for (i = 0; i < saddrRequired.length; i++) {
				(function (index) {
					saddrRequired[index].required = false;
				})(i);
			}
			billaddrCont.className = '';
			shipaddrCont.style.display = 'none';
		} else {
			for (i = 0; i < itemsArr.length; i++) {
				(function (index) {
					itemsArr[index].setAttribute('data-class-save', itemsArr[index].className);
					itemsArr[index].className = 'pure-u-1';
				})(i);
			}
			for (i = 0; i < baddrHide.length; i++) {
				(function (index) {
					baddrHide[index].style.display = 'none';
				})(i);
			}
			for (i = 0; i < saddrRequired.length; i++) {
				(function (index) {
					saddrRequired[index].required = true;
				})(i);
			}
			billaddrCont.className = 'half-width';
			shipaddrCont.style.display = 'inline-block';
		}
	});
}

var card = elements.create('card', {
	style: style,
	hidePostalCode: !(vars.data.verify_zip && !vars.data.billing_address)
});

card.on('ready', function () {
	submitBtn.disabled = false;
});

card.mount('#card-element');

card.addEventListener('change', function (event) {
	errorCont.style.display = 'none';
	if (event.error) {
		cardErrorCont.textContent = event.error.message;
	} else {
		cardErrorCont.textContent = '';
	}
	vars.data.cardComplete = event.complete;
});

submitBtn.addEventListener('click', function () {
	if (!vars.data.isEvent) {
		vars.data.buttonClicked = '';
	}
});

form.addEventListener('submit', function (event) {
	event.preventDefault();

	if (!canProceed()) {
		return false;
	}

	if (!is_full_discount() && ('def' === vars.data.currentPM || !vars.data.currentPM) && !vars.data.cardComplete) {
		event.preventDefault();
		card.focus();
		return false;
	}

	errorCont.style.display = 'none';
	submitBtn.disabled = true;

	updateAllAmounts();

	smokeScreen(true);

	doAddonAction('readyToProceed');

	handlePayment();

});

vars.data.initShowPopup = true;

vars.data.currentPM = 'def';

doAddonAction('init');

jQuery('select#currency').on('change', function () {
	if (!vars.data.addons || !vars.data.currentPM) {
		return true;
	}
	vars.data.addons.forEach(function (addon) {
		if (addon.supported_curr) {
			if (!addon.supported_curr.includes(jQuery('select#currency').val())) {
				jQuery('input[data-pm-id="' + addon.name + '"]').attr('disabled', true).parent().addClass('pm-disabled').css('position', 'relative');
				if (vars.data.currentPM === addon.name) {
					submitBtn.disabled = true;
					errorCont.innerHTML = vars.str.strCurrencyNotSupported;
					errorCont.style.display = 'block';
				}
			} else {
				jQuery('input[data-pm-id="' + addon.name + '"]').attr('disabled', false).parent().removeClass('pm-disabled');
				if (vars.data.currentPM === addon.name) {
					submitBtn.disabled = false;
					errorCont.style.display = 'none';
				}
			}
		}
	});
});

jQuery('select#currency').change();

if (vars.data.initShowPopup) {
	showPopup();
}

jQuery('.pm-select-btn').click(function () {
	vars.data.currentPM = jQuery(this).data('pm-id');
	jQuery('.pm-select-btn').parent().removeClass('pure-menu-selected');
	jQuery(this).parent().addClass('pure-menu-selected');
	vars.data.dont_hide_button = false;
	doAddonAction('pmSelectClicked');
	var sel = jQuery('#payment-form').find('[data-pm-name][data-pm-name!="' + vars.data.currentPM + '"]');
	if (vars.data.dont_hide_button) {
		jQuery('#submit-btn-cont').show();
		sel = jQuery(sel.not('#submit-btn-cont'));
	}
	sel.hide();
	jQuery('#payment-form').find('[data-pm-name="' + vars.data.currentPM + '"]').show();
});


function updateAllAmounts() {
	calcTotal();

	if (is_full_discount()) {
		submitBtn.innerHTML = vars.str.strGetForFree;
	} else {
		submitBtn.innerHTML = vars.payBtnText.replace(/%s/g, formatMoney(vars.data.amount));
	}

	if (vars.data.show_your_order === 1) {
		jQuery('#order-total').html(formatMoney(vars.data.amount));
		jQuery('#order-item-price').html(formatMoney(vars.data.item_price * vars.data.quantity));
		jQuery('#order-quantity').html(vars.data.quantity);
		jQuery('#order-tax').html(formatMoney(vars.data.taxAmount * vars.data.quantity));
		jQuery('#shipping').html(formatMoney(vars.data.shipping));
		if (vars.data.coupon) {
			if (jQuery('tr#order-coupon-line').length === 0) {
				var couponOrderLine = '<tr id="order-coupon-line"><td>Coupon "' + vars.data.coupon.code + '"</td><td>- <span id="order-coupon"></span></td></tr>';
				if (jQuery('tr.variation-line').last().length !== 0) {
					jQuery('tr.variation-line').last().after(couponOrderLine);
				} else {
					jQuery('tr#order-item-line').after(couponOrderLine);
				}
			}
			jQuery('#order-coupon').html(formatMoney(vars.data.coupon.discount_amount * vars.data.quantity));
		}
		if (vars.data.variations.applied) {
			for (grpId = 0; grpId < vars.data.variations.applied.length; ++grpId) {
				if (jQuery('#order-variation-' + grpId + '-line').length === 0) {
					jQuery('tr#order-item-line').after('<tr id="order-variation-' + grpId + '-line" class="variation-line"><td class="variation-name"></td><td class="variation-price"></td></tr>');
				}
				jQuery('#order-variation-' + grpId + '-line').find('.variation-name').html(vars.data.variations.groups[grpId] + '<br>' + vars.data.variations.names[grpId][vars.data.variations.applied[grpId]]);
				jQuery('#order-variation-' + grpId + '-line').find('.variation-price').html(formatMoney(amount_to_cents(vars.data.variations.prices[grpId][vars.data.variations.applied[grpId]], vars.data.currency) * vars.data.quantity));
			}
		}
	}
	doAddonAction('allAmountsUpdated');
}

function calcTotal() {
	var itemSubt = vars.data.item_price;
	var tAmount = 0;
	var grpId;
	var i;
	if (vars.data.items) {
		for (i = 0; i < vars.data.items.length; ++i) {
			itemSubt = itemSubt + amount_to_cents(vars.data.items[i]['price'], vars.data.currency);
		}
	}
	if (vars.data.variations.applied) {
		for (grpId = 0; grpId < vars.data.variations.applied.length; ++grpId) {
			itemSubt = itemSubt + amount_to_cents(vars.data.variations.prices[grpId][vars.data.variations.applied[grpId]], vars.data.currency);
		}
	}
	if (vars.data.coupon) {
		var discountAmount = 0;
		if (vars.data.coupon.discount_type === 'perc') {
			discountAmount = PHP_round(itemSubt * (vars.data.coupon.discount / 100), 0);
		} else {
			if (is_zero_cents(vars.data.currency)) {
				discountAmount = vars.data.coupon.discount;
			} else {
				discountAmount = vars.data.coupon.discount * 100;
			}
		}
		itemSubt = itemSubt - discountAmount;
		vars.data.coupon.discount_amount = discountAmount;
		if (is_full_discount() && vars.data.shipping) {
			vars.data.shipping_orig = vars.data.shipping;
			vars.data.shipping = 0;
		}
	}

	if (vars.data.tax) {
		var tax = PHP_round(itemSubt * vars.data.tax / 100, 0);
		vars.data.taxAmount = tax;
		itemSubt = itemSubt + tax;
	}

	tAmount = itemSubt * vars.data.quantity;

	if (vars.data.shipping) {
		tAmount = tAmount + vars.data.shipping;
	}
	vars.data.amount = PHP_round(tAmount, 0);
}

function PHP_round(num, dec) {
	var num_sign = num >= 0 ? 1 : -1;
	return parseFloat((Math.round((num * Math.pow(10, dec)) + (num_sign * 0.0001)) / Math.pow(10, dec)).toFixed(dec));
}

function is_zero_cents(curr) {
	if (vars.zeroCents.indexOf(curr.toUpperCase()) === -1) {
		return false;
	}
	return true;
}

function cents_to_amount(amount, curr) {
	if (!is_zero_cents(curr)) {
		amount = amount / 100;
	}
	return amount;
}

function amount_to_cents(amount, curr) {
	amount = parseFloat(amount);
	if (!is_zero_cents(curr)) {
		amount = amount * 100;
	}
	return amount;
}

function showFormInputErr(msg, el, inp) {
	el.innerHTML = msg;
	jQuery(el).show().hide().fadeIn();
	jQuery(inp).focus();
}

function showPopup() {
	jQuery('#global-spinner').hide();
	jQuery('#Aligner-item').addClass('popup-show').hide().fadeIn();
}

function smokeScreen(show) {
	if (show) {
		display = 'flex';
	} else {
		display = 'none';
	}
	document.getElementById('smoke-screen').style.display = display;
}

function formatMoney(n) {
	var negative = false;
	if (n < 0) {
		n = (0 - n);
		negative = true;
	}
	n = cents_to_amount(n, vars.data.currency);
	var c = isNaN(c = Math.abs(vars.currencyFormat.c)) ? 2 : vars.currencyFormat.c,
		d = d == undefined ? '.' : vars.currencyFormat.d,
		t = t == undefined ? ',' : vars.currencyFormat.t,
		s = n < 0 ? '-' : '',
		i = String(parseInt(n = Math.abs(Number(n) || 0).toFixed(c))),
		j = (j = i.length) > 3 ? j % 3 : 0;

	var result = s + (j ? i.substr(0, j) + t : '') + i.substr(j).replace(/(\d{3})(?=\d)/g, '$1' + t) + (c ? d + Math.abs(n - i).toFixed(c).slice(2) : '');
	result = (vars.currencyFormat.pos !== 'right' ? vars.currencyFormat.s + result : result + vars.currencyFormat.s);
	return (negative ? '- ' + result : result);
}

function inIframe() {
	try {
		return window.self !== window.top;
	} catch (e) {
		return true;
	}
}

function is_full_discount() {
	if (vars.data.coupon && vars.data.coupon.discount_type === 'perc' && parseFloat(vars.data.coupon.discount) === 100) {
		return true;
	}
	return false;
}

function triggerEvent(el, type) {
	var e;
	if ('createEvent' in document) {
		e = document.createEvent('HTMLEvents');
		e.initEvent(type, false, true);
		el.dispatchEvent(e);
	} else {
		e = document.createEventObject();
		e.eventType = type;
		el.fireEvent('on' + e.eventType, e);
	}
}

function validate_custom_field() {
	if (!customFieldInput) {
		return true;
	}
	if (vars.custom_field_validation_regex !== '') {
		try {
			var re = new RegExp(vars.data.custom_field_validation_regex);
		} catch (error) {
			showFormInputErr(vars.str.strInvalidCFValidationRegex + ' ' + vars.data.custom_field_validation_regex + '\n' + error, errorCont, customFieldInput);
			return false;
		}
	}
	if (customFieldInput.type === 'text' && customFieldInput.value && !re.test(customFieldInput.value)) {
		showFormInputErr(vars.data.custom_field_validation_err_msg, customFieldErr, customFieldInput);
		return false;
	}
	customFieldErr.style.display = 'none';
	return true;
}

function validate_custom_quantity() {
	var custom_quantity_orig;
	var errObj;
	if (vars.data.custom_quantity) {
		errObj = quantityErr;
		custom_quantity_orig = quantityInput.value;
	} else {
		errObj = errorCont;
		custom_quantity_orig = vars.data.quantity;
	}
	var custom_quantity = parseInt(custom_quantity_orig);
	if (isNaN(custom_quantity)) {
		showFormInputErr(vars.str.strEnterQuantity, errObj, quantityInput);
		return false;
	} else if (custom_quantity_orig % 1 !== 0) {
		showFormInputErr(vars.str.strQuantityIsFloat, errObj, quantityInput);
		return false;
	} else if (custom_quantity <= 0) {
		showFormInputErr(vars.str.strQuantityIsZero, errObj, quantityInput);
		return false;
	} else if (vars.data.stock_control_enabled === true && custom_quantity > vars.data.stock_items) {
		showFormInputErr(vars.str.strStockNotAvailable.replace('%d', vars.data.stock_items), errObj, quantityInput);
		return false;
	}
	errObj.style.display = 'none';
	vars.data.quantity = custom_quantity;
	return custom_quantity;
}

function validate_custom_amount() {
	var cAmount = amountInput.value;
	if (vars.amountOpts.applySepOpts != 0) {
		cAmount = cAmount.replace(vars.amountOpts.thousandSep, '');
		cAmount = cAmount.replace(vars.amountOpts.decimalSep, '.');
	} else {
		cAmount = cAmount.replace(/\$/g, '');
		cAmount = cAmount.replace(/,/g, '');
		cAmount = cAmount.replace(/ /g, '');
	}
	cAmount = parseFloat(cAmount);

	if (isNaN(cAmount)) {
		showFormInputErr(vars.str.strEnterValidAmount, amountErr, amountInput);
		return false;
	}
	var displayAmount = cAmount.toFixed(2).toString();
	if (vars.amountOpts.applySepOpts != 0) {
		displayAmount = displayAmount.replace('.', vars.amountOpts.decimalSep);
	}
	if (!is_zero_cents(vars.data.currency)) {
		cAmount = PHP_round(cAmount * 100, 0);
	}
	if (typeof vars.minAmounts[vars.data.currency] !== 'undefined') {
		if (vars.minAmounts[vars.data.currency] > cAmount) {
			showFormInputErr(vars.str.strMinAmount + ' ' + cents_to_amount(vars.minAmounts[vars.data.currency], vars.data.currency), amountErr, amountInput);
			return false;
		}
	} else if (50 > cAmount) {
		showFormInputErr(vars.str.strMinAmount + ' 0.5', amountErr, amountInput);
		return false;
	}
	amountErr.style.display = 'none';
	amountInput.value = displayAmount;
	return cAmount;
}

function canProceed() {

	if (vars.data.amount_variable && vars.data.hide_amount_input !== '1') {
		amount = validate_custom_amount();
		if (amount === false) {
			event.preventDefault();
			return false;
		}
		vars.data.item_price = amount;
	}
	if (vars.data.custom_quantity) {
		quantity = validate_custom_quantity();
		if (quantity === false) {
			event.preventDefault();
			return false;
		}
		vars.data.quantity = quantity;
	}

	if (vars.data.custom_field) {
		var custom_field_valid = validate_custom_field();
		if (custom_field_valid === false) {
			event.preventDefault();
			return false;
		}
	}

	if (piInput.value !== '') {
		jQuery('#btn-spinner').hide();
		jQuery('#checkmark-cont').css('display', 'flex');
		setTimeout(function () {
			jQuery('#Aligner-item').fadeOut(function () {
				smokeScreen(false);
				jQuery('#global-spinner').show();
			});
		}, 1500);
		if (!inIframe() || window.doSelfSubmit) {
			console.log('Self-submitting');
			for (var i = 0; i < form.elements.length; i++) {
				if (form.elements[i].name) {
					form.elements[i].setAttribute('name', 'asp_' + form.elements[i].name);
				}
			}
			form.submit();
		}
		return false;
	}

	if (vars.data.amount_variable && vars.data.hide_amount_input !== '1') {
		amount = validate_custom_amount();
		if (amount === false) {
			return false;
		}
		vars.data.item_price = amount;
	}

	var autoRequiredEmpty = false;

	jQuery('#payment-form').find('[required]:visible').each(function (id, el) {
		if (jQuery(el).val() === '' || (jQuery(el).attr('type') === 'checkbox' && !jQuery(el).prop('checked'))) {
			jQuery(el).focus();
			jQuery(el).one('keyup change', function () {
				jQuery(this).siblings('.auto-required-err-msg').remove();
				jQuery(this).parent().siblings('.auto-required-err-msg').remove();
			});
			autoRequiredEmpty = true;
			jQuery('#payment-form').find('.auto-required-err-msg').remove();
			if (jQuery(el).attr('type') === 'checkbox') {
				jQuery(el).parent().after(jQuery('<span class="form-err auto-required-err-msg" role="alert">' + vars.str.strPleaseCheckCheckbox + '</span>').fadeIn());
			} else {
				jQuery(el).after(jQuery('<span class="form-err auto-required-err-msg" role="alert">' + vars.str.strPleaseFillIn + '</span>').fadeIn());
			}
			return false;
		}
	});

	if (autoRequiredEmpty) {
		return false;
	}

	if (vars.data.tos) {
		if (!tosInput.checked) {
			showFormInputErr(vars.str.strMustAcceptTos, tosInputErr, tosInput);
			return false;
		}
	}

	vars.data.canProceed = true;

	doAddonAction('submitCanProceed');

	if (!vars.data.canProceed) {
		return false;
	}

	return true;
}

function handlePayment() {
	var billingNameInput = document.getElementById('billing-name');
	var emailInput = document.getElementById('email');
	var billingDetails = {
		name: billingNameInput.value,
		email: emailInput.value,
	};
	if (vars.data.billing_address) {
		var bAddr = document.getElementById('address');
		var bCity = document.getElementById('city');
		var bCountry = document.getElementById('country');
		var bState = document.getElementById('state');
		var bPostcode = document.getElementById('postcode');
		billingDetails.address = {
			line1: bAddr.value,
			city: bCity.value,
			state: bState === null ? null : bState.value,
			country: bCountry.value || bCountry.options[bCountry.selectedIndex].value,
		};
		var postal_code = bPostcode.value;
		if (postal_code) {
			billingDetails.address.postal_code = postal_code;
		}
	}
	if (vars.data.shipping_address) {
		var shippingDetails = {
			name: billingNameInput.value
		};
		var sAddr = document.getElementById('shipping_address');
		var sCity = document.getElementById('shipping_city');
		var sCountry = document.getElementById('shipping_country');
		var sState = document.getElementById('shipping_state');
		var sPostcode = document.getElementById('shipping_postcode');
		shippingDetails.address = {
			line1: sAddr.value,
			city: sCity.value,
			state: sState === null ? null : sState.value,
			country: sCountry.value || sCountry.options[sCountry.selectedIndex].value,
		};
		var spostal_code = sPostcode.value;
		if (spostal_code) {
			shippingDetails.address.postal_code = spostal_code;
		}
	}
	if (vars.data.billing_address && vars.data.shipping_address && billshipSwitch.checked) {
		shippingDetails = JSON.parse(JSON.stringify(billingDetails));
		delete (shippingDetails.email);
	}
	var opts = {
		payment_method: {
			card: card,
			billing_details: billingDetails
		}
	};
	if (shippingDetails) {
		opts.shipping = shippingDetails;
	}

	vars.data.billingDetails = billingDetails;

	doAddonAction('csBeforeRegen');

	if (vars.data.doNotProceed) {
		return false;
	}

	//regen cs
	if (!is_full_discount() && !vars.data.token_not_required && (vars.data.client_secret === '' || vars.data.amount !== clientSecAmount || vars.data.currency !== clientSecCurrency)) {
		var reqStr = 'action=asp_pp_req_token&amount=' + vars.data.amount + '&curr=' + vars.data.currency + '&product_id=' + vars.data.product_id;
		reqStr = reqStr + '&quantity=' + vars.data.quantity;
		if (vars.data.cust_id) {
			reqStr = reqStr + '&cust_id=' + vars.data.cust_id;
		}
		if (vars.data.client_secret !== '') {
			reqStr = reqStr + '&pi=' + vars.data.pi_id;
		}
		reqStr = reqStr + '&billing_details=' + JSON.stringify(billingDetails);
		if (shippingDetails) {
			reqStr = reqStr + '&shipping_details=' + JSON.stringify(shippingDetails);
		}
		vars.data.csRegenParams = reqStr;
		doAddonAction('csBeforeRegenParams');
		console.log('Regen CS');
		new ajaxRequest(vars.ajaxURL, vars.data.csRegenParams,
			function (res) {
				try {
					var resp = JSON.parse(res.responseText);
					console.log(resp);
					if (typeof resp.stock_items !== 'undefined') {
						if (vars.data.stock_items !== resp.stock_items) {
							vars.data.stock_items = resp.stock_items;
							validate_custom_quantity();
						}
					}
					if (!resp.success) {
						submitBtn.disabled = false;
						errorCont.innerHTML = resp.err;
						errorCont.style.display = 'block';
						smokeScreen(false);
						return false;
					}
					vars.data.client_secret = resp.clientSecret;
					vars.data.pi_id = resp.pi_id;
					vars.data.cust_id = resp.cust_id;
					clientSecAmount = vars.data.amount;
					clientSecCurrency = vars.data.currency;
					doAddonAction('csRegenCompleted');
					if (vars.data.doNotProceed) {
						return false;
					}
					handlePayment();
					return true;
				} catch (e) {
					console.log(e);
					alert('Caught Exception: ' + e);
				}
			},
			function (res, errMsg) {
				submitBtn.disabled = false;
				errorCont.innerHTML = errMsg;
				errorCont.style.display = 'block';
				smokeScreen(false);
			}
		);
		return false;
	}

	doAddonAction('csReady');

	if (vars.data.doNotProceed) {
		return false;
	}

	if (!is_full_discount() && vars.data.create_token) {
		console.log('Creating token');
		opts = {
			name: billingNameInput.value
		};
		if (vars.data.billing_address) {
			opts.address_line1 = bAddr.value;
			opts.address_city = bCity.value;
			opts.address_state = bState === null ? '' : bState.value,
			opts.address_country = bCountry.value || bCountry.options[bCountry.selectedIndex].value;
			if (postal_code) {
				opts.address_zip = postal_code;
			}
		}
		stripe.createToken(card, opts).then(function (result) {
			console.log(result);
			if (result.error) {
				submitBtn.disabled = false;
				errorCont.innerHTML = result.error.message;
				errorCont.style.display = 'block';
				smokeScreen(false);
			} else {
				var reqStr = 'action=asp_pp_confirm_token&asp_token_id=' + result.token.id + '&product_id=' + vars.data.product_id;
				if (vars.data.cust_id) {
					reqStr = reqStr + '&cust_id=' + vars.data.cust_id;
				}
				if (vars.data.currency_variable) {
					reqStr = reqStr + '&currency=' + vars.data.currency;
				}
				if (vars.data.amount_variable && vars.data.hide_amount_input !== '1') {
					reqStr = reqStr + '&amount=' + vars.data.item_price;
				}
				if (vars.data.quantity > 1) {
					reqStr = reqStr + '&quantity=' + vars.data.quantity;
				}
				if (vars.data.coupon) {
					reqStr = reqStr + '&coupon=' + vars.data.coupon.code;
				}
				reqStr = reqStr + '&billing_details=' + JSON.stringify(billingDetails);
				if (shippingDetails) {
					reqStr = reqStr + '&shipping_details=' + JSON.stringify(shippingDetails);
				}
				console.log('Doing action asp_pp_confirm_token');
				new ajaxRequest(vars.ajaxURL, reqStr,
					function (res) {
						try {
							var resp = JSON.parse(res.responseText);
							console.log(resp);
							if (!resp.success) {
								submitBtn.disabled = false;
								errorCont.innerHTML = resp.err;
								errorCont.style.display = 'block';
								smokeScreen(false);
								return false;
							}
							var inputSubId = document.getElementById('sub_id');
							inputSubId.value = resp.sub_id;
							if (resp.cust_id) {
								vars.data.cust_id = resp.cust_id;
							}
							if (resp.pi_cs) {
								vars.data.client_secret = resp.pi_cs;
								vars.data.create_token = false;
								if (resp.do_card_setup) {
									vars.data.do_card_setup = true;
								}
								handlePayment();
							} else {
								piInput.value = resp.pi_id;
								if (resp.no_action_required) {
									vars.data.no_action_required = true;
								}
								if (!vars.data.coupon && couponInput) {
									couponInput.value = '';
								}
								triggerEvent(form, 'submit');
							}
						} catch (e) {
							console.log(e);
							alert('Caught Exception: ' + e);
						}
					},
					function (res, errMsg) {
						submitBtn.disabled = false;
						errorCont.innerHTML = errMsg;
						errorCont.style.display = 'block';
						smokeScreen(false);
					}
				);
			}
		});
		return false;
	}

	if (vars.data.no_action_required) {
		return true;
	}

	if (is_full_discount()) {
		handleCardPaymentResult({ paymentIntent: { id: vars.data.coupon.zero_value_id } });
		return false;
	}

	if (vars.data.do_card_setup) {
		if (opts.shipping) {
			opts.shipping = undefined;
		}
		console.log('Doing confirmCardSetup()');
		stripe.confirmCardSetup(
			vars.data.client_secret, opts)
			.then(function (result) {
				console.log(result);
				if (result.error) {
					submitBtn.disabled = false;
					errorCont.innerHTML = result.error.message;
					errorCont.style.display = 'block';
					smokeScreen(false);
				} else {
					piInput.value = document.getElementById('sub_id').value;
					if (!vars.data.coupon && couponInput) {
						couponInput.value = '';
					}
					triggerEvent(form, 'submit');
				}
			});

	} else {
		if (!vars.data.dont_save_card) {
			opts.save_payment_method = true;
			opts.setup_future_usage = 'off_session';
		}
		if (vars.data.stripe_receipt_email) {
			opts.receipt_email = emailInput.value;
		}
		console.log('Doing confirmCardPayment()');
		stripe.confirmCardPayment(vars.data.client_secret, opts)
			.then(function (result) {
				console.log(result);
				handleCardPaymentResult(result);
			});
	}
}

function handleCardPaymentResult(result) {
	if (result.error) {
		submitBtn.disabled = false;
		errorCont.innerHTML = result.error.message;
		errorCont.style.display = 'block';
		smokeScreen(false);
	} else {
		piInput.value = result.paymentIntent.id;
		if (!vars.data.coupon && couponInput) {
			couponInput.value = '';
		}
		triggerEvent(form, 'submit');
	}
}

function doAddonAction(action) {
	vars.data.doNotProceed = false;
	if (vars.data.addons) {
		vars.data.addons.forEach(function (addon) {
			if (typeof addon.obj === 'undefined' && typeof window[addon.handler] !== 'undefined') {
				addon.obj = new window[addon.handler](vars.data);
			}
			if (typeof addon.obj !== 'undefined' && typeof addon.obj[action] === 'function') {
				console.log(addon.name + ': ' + action);
				addon.obj[action]();
			}
		});
	}
}

// eslint-disable-next-line no-unused-vars
function toggleRequiredElements(els, hide) {
	els.forEach(function (el) {
		if (hide) {
			jQuery('#' + el).hide();
		} else {
			jQuery('#' + el).show();
		}
	});
	if (hide) {
		jQuery('#payment-form').find('[required]').not(':visible').each(function (id, el) {
			jQuery(el).prop('required', false);
			jQuery(el).attr('data-required-hidden', 1);
		});
	} else {
		jQuery('#payment-form').find('[data-required-hidden="1"]').each(function (id, el) {
			jQuery(el).prop('required', true);
			jQuery(el).attr('data-required-hidden', 0);
		});
	}
}

function saveFormData(success_cb, error_cb) {
	var reqStr = 'action=asp_pp_save_form_data&form_data=' + encodeURIComponent(jQuery(form).serialize());
	new ajaxRequest(vars.ajaxURL, reqStr, success_cb, error_cb);
}

var ajaxRequest = function (URL, reqStr, doneFunc, failFunc) {
	var parent = this;
	this.URL = URL;
	this.reqStr = reqStr;
	this.doneFunc = doneFunc;
	this.failFunc = failFunc;
	this.XMLHttpReq = new XMLHttpRequest();
	if (!this.XMLHttpReq) {
		alert('Cannot create an XMLHTTP instance');
		return false;
	}

	parent.XMLHttpReq.onreadystatechange = function () {
		if (parent.XMLHttpReq.readyState === XMLHttpRequest.DONE) {
			if (parent.XMLHttpReq.status === 200) {
				parent.doneFunc(parent.XMLHttpReq);
			} else {
				console.log('ajaxRequest failed');
				console.log(parent.XMLHttpReq);
				var errMsg = 'Error occurred:' + ' ' + parent.XMLHttpReq.statusText + '\n';
				errMsg += 'URL: ' + parent.XMLHttpReq.responseURL + '\n';
				errMsg += 'Code: ' + parent.XMLHttpReq.status;
				if (parent.failFunc) {
					parent.failFunc(parent.XMLHttpReq, errMsg);
				}
			}
		}
	};
	parent.XMLHttpReq.open('POST', parent.URL);
	parent.XMLHttpReq.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
	parent.XMLHttpReq.send(reqStr);
};
