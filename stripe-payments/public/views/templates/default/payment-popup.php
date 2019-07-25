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
            <div class="pure-g">
                <div class="pure-u-1">
                    <div id="global-error"></div>
                </div>
                <div class="pure-u-1">
                    <form method="post" id="payment-form" class="pure-form pure-form-stacked">
                        <?php if (isset($a['custom_fields'])) { ?>
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
                                <div id="card-errors" role="alert"></div>
                            </fieldset>
                        </div>
                        <div class="pure-u-5-5 centered">
                            <button type="submit" id="submit-btn" class="pure-button pure-button-primary" disabled>Submit Payment</button>
                        </div>
                        <input type="hidden" id="payment-intent" name="payment_intent" value="">
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
<script src="https://js.stripe.com/v3/"></script>
<script>
    var background = document.getElementById('Aligner');
    var clientSecret = '<?php echo esc_js($a['client_secret']) ?>';
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
    var billingNameInput = document.getElementById('billing-name');
    var emailInput = document.getElementById('email');
    var piInput = document.getElementById('payment-intent');
    var errorCont = document.getElementById('global-error');
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
    form.addEventListener('submit', function(event) {
        event.preventDefault();
        if (piInput.value !== '') {
            return false;
        }
        errorCont.style.display = 'none';
        submitBtn.disabled = true;
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
                errorCont.innerHTML = result.error.message;
                errorCont.style.display = 'block';
            } else {
                var form = document.getElementById('payment-form');
                piInput.value = result.paymentIntent.id;
                form.dispatchEvent(new Event('submit'));
            }
        });

    });
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