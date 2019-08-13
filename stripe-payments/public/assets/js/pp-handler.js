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

function showFormInputErr(msg, el, inp) {
    el.innerHTML = msg;
    el.style.display = "block";
    inp.focus();
}

function formatMoney(n) {
    n = cents_to_amount(n, currency);
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
        showFormInputErr(vars.str.strStockNotAvailable.replace('%d', data.stock_items), quantityErr, quantityInput);
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
    if (!is_zero_cents(currency)) {
        cAmount = Math.round(cAmount * 100);
    }
    if (typeof vars.minAmounts[currency] !== 'undefined') {
        if (vars.minAmounts[currency] > cAmount) {
            showFormInputErr(vars.str.strMinAmount + ' ' + cents_to_amount(vars.minAmounts[currency], currency), amountErr, amountInput);
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

var fatalError = vars.fatal_error;
var errorCont = document.getElementById('global-error');
if (fatalError) {
    throw new Error(vars.fatal_error);
}
var amountVariable = vars.data.amount_variable;
if (amountVariable) {
    var amountInput = document.getElementById('amount');
    var amountErr = document.getElementById('amount-error');
    amountInput.addEventListener('change', function (e) {
        amount = validate_custom_amount();
        if (amount !== false) {
            submitBtn.innerHTML = vars.payBtnText.replace(/%s/g, formatMoney(amount * vars.data.quantity));
        }
    });
}
if (vars.data.custom_quantity) {
    var quantityInput = document.getElementById('quantity');
    var quantityErr = document.getElementById('quantity-error');
    quantityInput.addEventListener('change', function (e) {
        quantity = validate_custom_quantity();
        if (quantity !== false) {
            submitBtn.innerHTML = vars.payBtnText.replace(/%s/g, formatMoney(amount * vars.data.quantity));
        }
    });
}
var amount = vars.data.amount;
var currency = vars.data.currency;
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

var card = elements.create('card', {
    style: style
});
var submitBtn = document.getElementById('submit-btn');
var btnSpinner = document.getElementById('btn-spinner');
var billingNameInput = document.getElementById('billing-name');
var emailInput = document.getElementById('email');
var piInput = document.getElementById('payment-intent');
var cardErrorCont = document.getElementById('card-errors');

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

var form = document.getElementById('payment-form');

submitBtn.addEventListener('click', function (e) {
    if (amountVariable) {
        amount = validate_custom_amount();
        if (amount === false) {
            event.preventDefault();
            return false;
        };
    }
    if (vars.data.custom_quantity) {
        quantity = validate_custom_quantity();
        if (quantity === false) {
            event.preventDefault();
            return false;
        }
    }
});

form.addEventListener('submit', function (event) {
    event.preventDefault();

    if (amountVariable) {
        amount = validate_custom_amount();
        if (amount === false) {
            return false;
        };
    }

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

    errorCont.style.display = 'none';
    submitBtn.disabled = true;
    btnSpinner.style.display = "inline-block";

    if (vars.data.client_secret === '' || amount * vars.data.quantity != clientSecAmount) {
        console.log('Regen CS');
        requestCS();
        return false;
    }

    handlePayment();
});

function handlePayment() {
    stripe.handleCardPayment(
        vars.data.client_secret, card, {
            payment_method_data: {
                billing_details: {
                    name: billingNameInput.value,
                    email: emailInput.value,
                }
            }
        }
    ).then(function (result) {
        console.log(result);
        if (result.error) {
            submitBtn.disabled = false;
            btnSpinner.style.display = "none";
            errorCont.innerHTML = result.error.message;
            errorCont.style.display = 'block';
        } else {
            piInput.value = result.paymentIntent.id;
            form.dispatchEvent(new Event('submit'));
        }
    });
}

var httpReqCS;

function requestCS() {
    httpReqCS = new XMLHttpRequest();

    if (!httpReqCS) {
        alert('Giving up :( Cannot create an XMLHTTP instance');
        return false;
    }
    httpReqCS.onreadystatechange = alertContents;
    httpReqCS.open('POST', vars.ajaxURL);
    httpReqCS.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    var reqStr = 'action=asp_pp_req_token&amount=' + amount + '&curr=' + currency + '&quantity=' + vars.data.quantity;
    if (vars.data.client_secret !== '') {
        reqStr = reqStr + '&pi=' + vars.data.pi_id;
    }
    httpReqCS.send(reqStr);
}

function alertContents() {
    try {
        if (httpReqCS.readyState === XMLHttpRequest.DONE) {
            if (httpReqCS.status === 200) {
                var resp = JSON.parse(httpReqCS.responseText);
                console.log(resp);
                vars.data.client_secret = resp.clientSecret;
                vars.data.pi_id = resp.pi_id;
                clientSecAmount = amount * vars.data.quantity;
                handlePayment();
            } else {
                alert('There was a problem with the request.');
            }
        }
    } catch (e) {
        alert('Caught Exception: ' + e.description);
    }
}