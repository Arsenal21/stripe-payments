function updateAllAmounts() {
	calcTotal();
	submitBtn.innerHTML = vars.payBtnText.replace(/%s/g, formatMoney(vars.data.amount));
}

function calcTotal() {
	var itemSubt = vars.data.item_price;
	var tAmount = 0;
	var grpId;
	if (vars.data.variations.applied) {
		for (grpId = 0; grpId < vars.data.variations.applied.length; ++grpId) {
			itemSubt = itemSubt + amount_to_cents(vars.data.variations.prices[grpId][vars.data.variations.applied[grpId]], vars.data.currency);
		}
	}
	if (vars.data.coupon) {
		var discountAmount = 0;
		if (vars.data.coupon.discount_type === 'perc') {
			discountAmount = Math.round(itemSubt * (vars.data.coupon.discount / 100));
		} else {
			if (is_zero_cents(vars.data.currency)) {
				discountAmount = vars.data.coupon.discount;
			} else {
				discountAmount = vars.data.coupon.discount * 100;
			}
		}
		itemSubt = itemSubt - discountAmount;
		vars.data.coupon.discount_amount = discountAmount;
	}
	if (vars.data.tax) {
		var tax = Math.round(itemSubt * parseFloat(vars.data.tax) / 100);
		itemSubt = parseInt(itemSubt) + parseInt(tax);
	}

	tAmount = itemSubt * vars.data.quantity;

	if (vars.data.shipping) {
		tAmount = tAmount + parseInt(vars.data.shipping);
	}
	vars.data.amount = tAmount;
}

function is_zero_cents(curr) {
	if (vars.zeroCents.indexOf(curr) === -1) {
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
	if (!is_zero_cents(curr)) {
		amount = amount * 100;
	}
	return amount;
}

function showFormInputErr(msg, el, inp) {
	el.innerHTML = msg;
	el.style.display = "block";
	inp.focus();
}

function smokeScreen(show) {
	if (show) {
		display = "block";
	} else {
		display = "none";
	}
	document.getElementById('smoke-screen').style.display = display;
}

function formatMoney(n) {
	n = cents_to_amount(n, vars.data.currency);
	var c = isNaN(c = Math.abs(vars.currencyFormat.c)) ? 2 : vars.currencyFormat.c,
		d = d == undefined ? "." : vars.currencyFormat.d,
		t = t == undefined ? "," : vars.currencyFormat.t,
		s = n < 0 ? "-" : "",
		i = String(parseInt(n = Math.abs(Number(n) || 0).toFixed(c))),
		j = (j = i.length) > 3 ? j % 3 : 0;

	var result = s + (j ? i.substr(0, j) + t : "") + i.substr(j).replace(/(\d{3})(?=\d)/g, "$1" + t) + (c ? d + Math.abs(n - i).toFixed(c).slice(2) : "");
	return (vars.currencyFormat.pos !== "right" ? vars.currencyFormat.s + result : result + vars.currencyFormat.s);
}

function inIframe() {
	try {
		return window.self !== window.top;
	} catch (e) {
		return true;
	}
}

function triggerEvent(el, type) {
	if ('createEvent' in document) {
		var e = document.createEvent('HTMLEvents');
		e.initEvent(type, false, true);
		el.dispatchEvent(e);
	} else {
		var e = document.createEventObject();
		e.eventType = type;
		el.fireEvent('on' + e.eventType, e);
	}
}

function validate_custom_quantity() {
	var custom_quantity_orig = quantityInput.value;
	var custom_quantity = parseInt(custom_quantity_orig);
	if (isNaN(custom_quantity)) {
		showFormInputErr(vars.str.strEnterQuantity, quantityErr, quantityInput);
		return false;
	} else if (custom_quantity_orig % 1 !== 0) {
		showFormInputErr(vars.str.strQuantityIsFloat, quantityErr, quantityInput);
		return false;
	} else if (custom_quantity <= 0) {
		showFormInputErr(vars.str.strQuantityIsZero, quantityErr, quantityInput);
		return false;
	} else if (vars.data.stock_control_enabled === true && custom_quantity > vars.data.stock_items) {
		showFormInputErr(vars.str.strStockNotAvailable.replace('%d', vars.data.stock_items), quantityErr, quantityInput);
		return false;
	}
	quantityErr.style.display = "none";
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
		cAmount = cAmount.replace(/\,/g, '');
		cAmount = cAmount.replace(/\ /g, '');
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
		cAmount = Math.round(cAmount * 100);
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
	amountErr.style.display = "none";
	amountInput.value = displayAmount;
	return cAmount;
}

var errorCont = document.getElementById('global-error');
if (vars.fatal_error) {
	throw new Error(vars.fatal_error);
}

if (vars.data.amount_variable) {
	var amountInput = document.getElementById('amount');
	var amountErr = document.getElementById('amount-error');
	amountInput.addEventListener('change', function (e) {
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
	quantityInput.addEventListener('change', function (e) {
		quantity = validate_custom_quantity();
		if (quantity !== false) {
			vars.data.quantity = quantity;
			updateAllAmounts();
		}
	});
}

if (vars.data.currency_variable) {
	var currencyInput = document.getElementById('currency');
	currencyInput.addEventListener('change', function (e) {
		vars.data.currency = currencyInput.value || currencyInput.options[currencyInput.selectedIndex];
		vars.currencyFormat.s = currencyInput.options[currencyInput.selectedIndex].getAttribute('data-asp-curr-sym');
		updateAllAmounts();
	});
}

if (vars.data.coupons_enabled) {
	var couponBtn = document.getElementById('apply-coupon-btn');
	var couponRemoveBtn = document.getElementById('remove-coupon-btn');
	var couponResCont = document.getElementById('coupon-res-cont');
	var couponInputCont = document.getElementById('coupon-input-cont');
	var couponInput = document.getElementById('coupon-code');
	var couponErr = document.getElementById('coupon-err');
	var couponInfo = document.getElementById('coupon-info');
	var couponSpinner = document.getElementById('coupon-spinner');
	couponInput.addEventListener('keydown', function (e) {
		if (e.keyCode === 13) {
			e.preventDefault();
			couponBtn.click();
			return false;
		}
	});
	couponBtn.addEventListener('click', function (e) {
		e.preventDefault();
		couponErr.style.display = "none";
		if (couponInput.value === '') {
			return false;
		}
		couponBtn.disabled = true;
		couponSpinner.style.display = 'block';
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
				}
				updateAllAmounts();
				couponSpinner.style.display = 'none';
				couponBtn.disabled = false;
				smokeScreen(false);
			},
			function (res, errMsg) {
				errorCont.innerHTML = errMsg;
				errorCont.style.display = 'block';
				couponBtn.disabled = false;
				smokeScreen(false);
			}
		)
	});
	couponRemoveBtn.addEventListener('click', function (e) {
		delete (vars.data.coupon);
		couponInput.value = '';
		couponResCont.style.display = 'none';
		couponInputCont.style.display = 'block';
		updateAllAmounts();
	});
}

var amount = vars.data.amount;
var clientSecAmount = 0;
var formCont = document.getElementById('form-container');
var background = document.getElementById('Aligner');
var stripe = Stripe(vars.stripe_key);
var elements = stripe.elements();

var style = {
	base: {
		fontSize: '16px',
	}
};

var submitBtn = document.getElementById('submit-btn');
var btnSpinner = document.getElementById('btn-spinner');
var billingNameInput = document.getElementById('billing-name');
var emailInput = document.getElementById('email');
var piInput = document.getElementById('payment-intent');
var cardErrorCont = document.getElementById('card-errors');
var form = document.getElementById('payment-form');

if (vars.data.tos) {
	var tosInput = document.getElementById('tos');
	var tosInputErr = document.getElementById('tos-error');
	tosInput.addEventListener('change', function (event) {
		tosInputErr.style.display = 'none';
	})
}

if (vars.data.variations) {
	var varInputs = document.getElementsByClassName('variations-input');
	for (var i = 0; i < varInputs.length; i++) {
		(function (index) {
			varInputs[index].addEventListener("change", function () {
				var grpId = this.getAttribute('data-asp-variations-group-id');
				var varId = this.value;
				if (Object.getOwnPropertyNames(vars.data.variations).length !== 0) {
					if (!vars.data.variations.applied) {
						vars.data.variations.applied = [];
					}
					vars.data.variations.applied[grpId] = varId;
					updateAllAmounts();
				}
			})
			if (varInputs[index].checked || varInputs[index].tagName === "SELECT") {
				triggerEvent(varInputs[index], 'change');
			}
		})(i);
	}
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

	billshipSwitch.addEventListener('change', function (e) {
		if (billshipSwitch.checked) {
			for (var i = 0; i < itemsArr.length; i++) {
				(function (index) {
					attr = itemsArr[index].getAttribute('data-class-save');
					itemsArr[index].className = attr;
				})(i);
			}
			for (var i = 0; i < baddrHide.length; i++) {
				(function (index) {
					baddrHide[index].style.display = "inline-block";
				})(i);
			}
			for (var i = 0; i < saddrRequired.length; i++) {
				(function (index) {
					saddrRequired[index].required = false;
				})(i);
			}
			billaddrCont.className = "";
			shipaddrCont.style.display = "none";
		} else {
			for (var i = 0; i < itemsArr.length; i++) {
				(function (index) {
					itemsArr[index].setAttribute('data-class-save', itemsArr[index].className);
					itemsArr[index].className = "pure-u-1";
				})(i);
			}
			for (var i = 0; i < baddrHide.length; i++) {
				(function (index) {
					baddrHide[index].style.display = "none";
				})(i);
			}
			for (var i = 0; i < saddrRequired.length; i++) {
				(function (index) {
					saddrRequired[index].required = true;
				})(i);
			}
			billaddrCont.className = "half-width";
			shipaddrCont.style.display = "inline-block";
		}
	})
}

var card = elements.create('card', {
	style: style,
	hidePostalCode: true
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
});

submitBtn.addEventListener('click', function (e) {
	if (vars.data.amount_variable) {
		amount = validate_custom_amount();
		if (amount === false) {
			event.preventDefault();
			return false;
		};
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
});

form.addEventListener('submit', function (event) {
	event.preventDefault();

	if (piInput.value !== '') {
		if (!inIframe()) {
			console.log('Self-submitting');
			for (var i = 0; i < form.elements.length; i++) {
				if (form.elements[i].name) {
					form.elements[i].setAttribute("name", 'asp_' + form.elements[i].name);
				}
			}
			form.submit();
		}
		return false;
	}

	if (vars.data.amount_variable) {
		amount = validate_custom_amount();
		if (amount === false) {
			return false;
		}
		vars.data.item_price = amount;
	}

	if (vars.data.tos) {
		if (!tosInput.checked) {
			showFormInputErr(vars.str.strMustAcceptTos, tosInputErr, tosInput);
			return false;
		}
	}

	errorCont.style.display = 'none';
	submitBtn.disabled = true;
	btnSpinner.style.display = "inline-block";

	smokeScreen(true);

	updateAllAmounts();

	handlePayment();

});

function handlePayment() {
	var billingDetails = {
		name: billingNameInput.value,
		email: emailInput.value,
	}
	if (vars.data.billing_address) {
		var bAddr = document.getElementById('address');
		var bCity = document.getElementById('city');
		var bCountry = document.getElementById('country');
		var bPostcode = document.getElementById('postcode');
		billingDetails.address = {
			line1: bAddr.value,
			city: bCity.value,
			country: bCountry.value || bCountry.options[bCountry.selectedIndex].value,
		}
		var postal_code = bPostcode.value;
		if (postal_code) {
			billingDetails.address.postal_code = postal_code;
		}
	}
	if (vars.data.shipping_address) {
		var shippingDetails = {
			name: billingNameInput.value
		}
		var sAddr = document.getElementById('shipping_address');
		var sCity = document.getElementById('shipping_city');
		var sCountry = document.getElementById('shipping_country');
		var sPostcode = document.getElementById('shipping_postcode');
		shippingDetails.address = {
			line1: sAddr.value,
			city: sCity.value,
			country: sCountry.value || sCountry.options[sCountry.selectedIndex].value,
		}
		var spostal_code = sPostcode.value;
		if (spostal_code) {
			shippingDetails.address.postal_code = spostal_code;
		}
	}
	if (vars.data.billing_address && vars.data.shipping_address && billshipSwitch.checked) {
		var shippingDetails = JSON.parse(JSON.stringify(billingDetails));
		delete (shippingDetails.email);
	}
	var opts = {
		payment_method_data: {
			billing_details: billingDetails
		}
	}
	if (shippingDetails) {
		opts.shipping = shippingDetails;
	}

	//regen cs
	if (!vars.data.create_token && (vars.data.client_secret === '' || vars.data.amount != clientSecAmount)) {
		console.log('Regen CS');
		var reqStr = 'action=asp_pp_req_token&amount=' + vars.data.amount + '&curr=' + vars.data.currency + '&product_id=' + vars.data.product_id;
		reqStr = reqStr + '&quantity=' + vars.data.quantity;
		if (vars.data.cust_id) {
			reqStr = reqStr + '&cust_id' + vars.data.cust_id;
		}
		if (vars.data.client_secret !== '') {
			reqStr = reqStr + '&pi=' + vars.data.pi_id;
		}
		reqStr = reqStr + '&billing_details=' + JSON.stringify(billingDetails);
		new ajaxRequest(vars.ajaxURL, reqStr,
			function (res) {
				try {
					var resp = JSON.parse(res.responseText);
					console.log(resp);
					if (typeof resp.stock_items !== "undefined") {
						if (vars.data.stock_items !== resp.stock_items) {
							vars.data.stock_items = resp.stock_items;
							validate_custom_quantity();
						}
					}
					if (!resp.success) {
						submitBtn.disabled = false;
						btnSpinner.style.display = "none";
						errorCont.innerHTML = resp.err;
						errorCont.style.display = 'block';
						smokeScreen(false);
						return false;
					}
					vars.data.client_secret = resp.clientSecret;
					vars.data.pi_id = resp.pi_id;
					vars.data.cust_id = resp.cust_id;
					clientSecAmount = vars.data.amount;
					handlePayment();
					return true;
				} catch (e) {
					console.log(e);
					alert('Caught Exception: ' + e.description);
				}
			},
			function (res, errMsg) {
				submitBtn.disabled = false;
				btnSpinner.style.display = "none";
				errorCont.innerHTML = errMsg;
				errorCont.style.display = 'block';
				smokeScreen(false);
			}
		);
		return false;
	}

	if (vars.data.create_token) {
		console.log('Creating token');
		var opts = {
			name: billingNameInput.value
		}
		if (vars.data.billing_address) {
			opts.address_line1 = bAddr.value;
			opts.address_city = bCity.value;
			opts.address_country = bCountry.value || bCountry.options[bCountry.selectedIndex].value;
			if (postal_code) {
				opts.address_zip = postal_code;
			}
		}
		stripe.createToken(card).then(function (result) {
			console.log(result);
			if (result.error) {
				submitBtn.disabled = false;
				btnSpinner.style.display = "none";
				errorCont.innerHTML = result.error.message;
				errorCont.style.display = 'block';
				smokeScreen(false);
			} else {
				var reqStr = 'action=asp_pp_confirm_token&asp_token_id=' + result.token.id + '&product_id=' + vars.data.product_id;
				if (vars.data.currency_variable) {
					reqStr = reqStr + '&currency=' + data.currency;
				}
				if (vars.data.amount_variable) {
					reqStr = reqStr + '&amount=' + vars.data.item_price;
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
								btnSpinner.style.display = "none";
								errorCont.innerHTML = resp.err;
								errorCont.style.display = 'block';
								smokeScreen(false);
								return false;
							}
							var inputSubId = document.getElementById('sub_id');
							inputSubId.value = resp.sub_id;
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
								form.dispatchEvent(new Event('submit'));
							}
						} catch (e) {
							console.log(e);
							alert('Caught Exception: ' + e.description);
						}
					},
					function (res, errMsg) {
						submitBtn.disabled = false;
						btnSpinner.style.display = "none";
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

	if (vars.data.do_card_setup) {
		console.log('Doing handleCardSetup()');
		stripe.handleCardSetup(
			vars.data.client_secret, card, opts)
			.then(function (result) {
				console.log(result);
				if (result.error) {
					submitBtn.disabled = false;
					btnSpinner.style.display = "none";
					errorCont.innerHTML = result.error.message;
					errorCont.style.display = 'block';
					smokeScreen(false);
				} else {
					piInput.value = document.getElementById('sub_id').value;
					if (!vars.data.coupon && couponInput) {
						couponInput.value = '';
					}
					form.dispatchEvent(new Event('submit'));
				}
			});

	} else {
		if (vars.data.dont_save_card !== false) {
			opts.save_payment_method = true;
			opts.setup_future_usage = "off_session";
		}
		console.log('Doing handleCardPayment()');
		stripe.handleCardPayment(
			vars.data.client_secret, card, opts)
			.then(function (result) {
				console.log(result);
				if (result.error) {
					submitBtn.disabled = false;
					btnSpinner.style.display = "none";
					errorCont.innerHTML = result.error.message;
					errorCont.style.display = 'block';
					smokeScreen(false);
				} else {
					piInput.value = result.paymentIntent.id;
					if (!vars.data.coupon && couponInput) {
						couponInput.value = '';
					}
					form.dispatchEvent(new Event('submit'));
				}
			});
	}
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
				var errMsg = "Error occurred:" + ' ' + parent.XMLHttpReq.statusText + "\n";
				errMsg += 'URL: ' + parent.XMLHttpReq.responseURL + '\n';
				errMsg += 'Code: ' + parent.XMLHttpReq.status;
				if (parent.failFunc) {
					parent.failFunc(parent.XMLHttpReq, errMsg);
				}
			}
		}
	}
	parent.XMLHttpReq.open('POST', parent.URL);
	parent.XMLHttpReq.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
	parent.XMLHttpReq.send(reqStr);
}
