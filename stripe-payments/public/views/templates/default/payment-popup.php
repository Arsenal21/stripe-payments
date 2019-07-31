<!DOCTYPE html>
<html>

<head>
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta charset="utf-8">
    <title><?php echo $a['page_title'] ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="stylesheet" href="<?php echo $a['plugin_url'] ?>/public/views/templates/default/pure.css">
    <link rel="stylesheet" href="<?php echo $a['plugin_url'] ?>/public/views/templates/default/pp-style.css">
    <?php
    foreach ($a['styles'] as $style) {
        if (!$style['footer']) {
            printf('<link rel="stylesheet" href="%s">', $style['src']);
        }
    }
    foreach ($a['vars'] as $var => $data) {
        printf("<script type='text/javascript'>
            /* <![CDATA[ */
            var %s = %s;
            /* ]]> */
            </script>", $var, json_encode($data));
    }

    foreach ($a['scripts'] as $script) {
        if ($script['footer']) {
            printf('<script src="%s"></script>', $script['src']);
        }
    }
    ?>
    <!--[if lt IE 9]>
<script src="//cdnjs.cloudflare.com/ajax/libs/html5shiv/3.7.2/html5shiv.min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/respond.js/1.4.2/respond.min.js"></script>
<![endif]-->
</head>

<body>
    <div id="Aligner" class="Aligner">

        <div class="Aligner-item">
            <div id="modal-header">
                <span id="modal-close-btn" title="<?php _e('Close', 'stripe-payments') ?>"><img src="<?php echo $a['plugin_url'] ?>/public/views/templates/default/close-btn.png"></span>
                <div id="item-name"><?php echo $a['item_name'] ?></div>
            </div>
            <div id="modal-body">
                <div class="pure-g">
                    <div class="pure-u-1">
                        <div id="global-error" <?php if (isset($a['fatal_error'])) echo 'style="display: block"' ?>><?php if (isset($a['fatal_error'])) echo $a['fatal_error']; ?></div>
                    </div>
                    <div id="form-container" class="pure-u-1" <?php if (isset($a['fatal_error'])) echo ' style="display: none;"' ?>>
                        <form method="post" id="payment-form" class="pure-form pure-form-stacked">
                            <?php if ($a['amount_variable']) { ?>
                                <label for="amount">Enter Amount</label>
                                <input class="pure-input-1" id="amount" name="amount">
                                <div id="amount-error" class="form-err" role="alert"></div>
                            <?php } ?>
                            <?php if ($a['currency_variable']) { } ?> <?php if (isset($a['custom_fields'])) { ?>
                                <div class="pure-u-1">
                                    <?php echo $a['custom_fields'] ?>
                                </div>
                            <?php } ?>
                            <div class="pure-u-1">
                                <fieldset>
                                    <div class="pure-g">
                                        <div class="pure-u-1 pure-u-md-11-24">
                                            <label for="billing_name">Name</label>
                                            <input class="pure-input-1" type="text" id="billing-name" name="billing_name" required>
                                        </div>
                                        <div class="pure-u-md-2-24"></div>
                                        <div class="pure-u-1 pure-u-md-11-24">
                                            <label for="email">Email</label>
                                            <input class="pure-input-1" type="email" id="email" name="email" required>
                                        </div>
                                    </div>
                                    <label for="card-element">Credit or debit card</label>
                                    <div id="card-element">
                                    </div>
                                    <div id="card-errors" class="form-err" role="alert"></div>
                                </fieldset>
                            </div>
                            <div class="pure-u-5-5 centered">
                                <div id="submit-btn-cont">
                                    <button type="submit" id="submit-btn" class="pure-button pure-button-primary" disabled><?php echo $a['pay_btn_text'] ?></button>
                                    <span id="btn-spinner" class="small-spinner"></span>
                                </div>
                            </div>
                            <input type="hidden" id="payment-intent" name="payment_intent" value="">
                            <input type="hidden" id="product-id" name="product_id" value="<?php echo $a['prod_id'] ?>">
                            <input type="hidden" name="process_ipn" value="1">
                            <input type="hidden" name="is_live" value="<?php echo $a['is_live'] ? 'true' : 'false' ?>">
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
<script src="https://js.stripe.com/v3/"></script>
<script>
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

    function showFormInputErr(msg, el) {
        el.innerHTML = msg;
        el.style.display = "block";
        el.focus();
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
            showFormInputErr(vars.str.strEnterValidAmount, amountErr);
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
                showFormInputErr(vars.str.strMinAmount + ' ' + cents_to_amount(vars.minAmounts[currency], currency), amountErr);
                return false;
            }
        } else if (50 > cAmount) {
            showFormInputErr(vars.str.strMinAmount + ' 0.5', amountErr);
            return false;
        }
        amountErr.style.display = "none";
        amountInput.value = displayAmount;
        return cAmount;
    }
    var fatalError = <?php echo isset($a['fatal_error']) ?  'true' : 'false' ?>;
    var errorCont = document.getElementById('global-error');
    if (fatalError) {
        throw new Error('<?php echo isset($a['fatal_error']) ?  esc_js($a['fatal_error']) : '' ?>');
    }
    var amountVariable = <?php echo $a['amount_variable'] ? 'true' : 'false' ?>;
    if (amountVariable) {
        var amountInput = document.getElementById('amount');
        var amountErr = document.getElementById('amount-error');
        amountInput.addEventListener('change', function(e) {
            amount = validate_custom_amount();
            if (amount !== false) {
                submitBtn.innerHTML = vars.payBtnText.replace(/%s/g, formatMoney(amount));
            }
        });
    }
    var amount = 0;
    var currency = '<?php echo $a['currency'] ?>';
    var clientSecAmount = 0;
    var formCont = document.getElementById('form-container');
    var background = document.getElementById('Aligner');
    var clientSecret = '<?php echo isset($a['client_secret']) ? esc_js($a['client_secret']) : '' ?>';
    var stripe = Stripe('<?php echo esc_js($a['stripe_key']) ?>');
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

    card.on('ready', function() {
        submitBtn.disabled = false;
    });

    card.mount('#card-element');

    card.addEventListener('change', function(event) {
        errorCont.style.display = 'none';
        if (event.error) {
            cardErrorCont.textContent = event.error.message;
        } else {
            cardErrorCont.textContent = '';
        }
    });

    var form = document.getElementById('payment-form');

    submitBtn.addEventListener('click', function(e) {
        if (amountVariable) {
            amount = validate_custom_amount();
            if (amount === false) {
                event.preventDefault();
                return false;
            };
        }
    });

    form.addEventListener('submit', function(event) {
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

        if (clientSecret === '' || amount != clientSecAmount) {
            console.log('Regen CS');
            requestCS();
            return false;
        }

        handlePayment();
    });

    function handlePayment() {
        stripe.handleCardPayment(
            clientSecret, card, {
                payment_method_data: {
                    billing_details: {
                        name: billingNameInput.value,
                        email: emailInput.value,
                    }
                }
            }
        ).then(function(result) {
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
        httpReqCS.send('action=asp_pp_req_token&amount=' + amount + '&curr=' + currency);
    }

    function alertContents() {
        try {
            if (httpReqCS.readyState === XMLHttpRequest.DONE) {
                if (httpReqCS.status === 200) {
                    console.log(httpReqCS.responseText);
                    var resp = JSON.parse(httpReqCS.responseText);
                    console.log(resp);
                    clientSecret = resp.clientSecret;
                    clientSecAmount = amount;
                    handlePayment();
                } else {
                    alert('There was a problem with the request.');
                }
            }
        } catch (e) {
            alert('Caught Exception: ' + e.description);
        }
    }
</script>
<?php
foreach ($a['scripts'] as $script) {
    if ($script['footer']) {
        printf('<script src="%s"></script>', $script['src']);
    }
}

foreach ($a['styles'] as $style) {
    if ($style['footer']) {
        printf('<link rel="stylesheet" href="%s">', $style['src']);
    }
}
?>

</html>