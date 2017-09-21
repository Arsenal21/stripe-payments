<?php

function asp_ipn_completed($errMsg = '') {
    if (!empty($errMsg)) {
        $aspData = array('error_msg' => $errMsg);
        $_SESSION['asp_data'] = $aspData;
    }
    global $aspRedirectURL;
    wp_redirect($aspRedirectURL);
    exit;
}

$asp_class = AcceptStripePayments::get_instance();

global $aspRedirectURL;

$aspRedirectURL = (isset($_POST['thankyou_page_url']) && empty($_POST['thankyou_page_url'])) ? $asp_class->get_setting('checkout_url') : base64_decode($_POST['thankyou_page_url']);

//Check nonce
$nonce = $_REQUEST['_wpnonce'];
if (!wp_verify_nonce($nonce, 'stripe_payments')) {
    //nonce check failed
    asp_ipn_completed("Nonce check failed.");
}
if (!isset($_POST['item_name']) || empty($_POST['item_name'])) {
    asp_ipn_completed('Invalid Item name');
}
if (!isset($_POST['stripeToken']) || empty($_POST['stripeToken'])) {
    asp_ipn_completed('Invalid Stripe Token');
}
if (!isset($_POST['stripeTokenType']) || empty($_POST['stripeTokenType'])) {
    asp_ipn_completed('Invalid Stripe Token Type');
}
if (!isset($_POST['stripeEmail']) || empty($_POST['stripeEmail'])) {
    asp_ipn_completed('Invalid Request');
}
if (!isset($_POST['currency_code']) || empty($_POST['currency_code'])) {
    asp_ipn_completed('Invalid Currency Code');
}

$item_name = sanitize_text_field($_POST['item_name']);
$stripeToken = sanitize_text_field($_POST['stripeToken']);
$stripeTokenType = sanitize_text_field($_POST['stripeTokenType']);
$stripeEmail = sanitize_email($_POST['stripeEmail']);
$item_quantity = sanitize_text_field($_POST['item_quantity']);
$item_url = sanitize_text_field($_POST['item_url']);
$charge_description = sanitize_text_field($_POST['charge_description']);

//$item_price = sanitize_text_field($_POST['item_price']);
$trans_name = 'stripe-payments-' . sanitize_title_with_dashes($item_name);
$item_price = get_transient($trans_name); //Read the price for this item from the system.
if (!is_numeric($item_price)) {
    echo ('Invalid item price');
    asp_ipn_completed();
}
if ($item_price == 0) { //Custom amount
    $item_price = floatval($_POST['stripeAmount']);
    if (!is_numeric($item_price)) {
        echo ('Invalid item price');
        asp_ipn_completed();
    }
}
$currency_code = sanitize_text_field($_POST['currency_code']);
$paymentAmount = ($item_quantity !== "NA" ? ($item_price * $item_quantity) : $item_price);

$currencyCodeType = strtolower($currency_code);

Stripe::setApiKey($asp_class->get_setting('api_secret_key'));

$GLOBALS['asp_payment_success'] = false;

$opt = get_option('AcceptStripePayments-settings');

if (in_array($currency_code, $asp_class->zeroCents)) {
    $amount = $paymentAmount;
} else {
    $amount = $paymentAmount * 100;
}

ob_start();
try {

    if ($opt['dont_save_card'] == 1) {
        $charge = Stripe_Charge::create(array(
                    'amount' => $amount,
                    'currency' => $currencyCodeType,
                    'description' => $charge_description,
                    'source' => $stripeToken,
        ));
    } else {

        $customer = Stripe_Customer::create(array(
                    'email' => $stripeEmail,
                    'card' => $stripeToken
        ));

        $charge = Stripe_Charge::create(array(
                    'customer' => $customer->id,
                    'amount' => $amount,
                    'currency' => $currencyCodeType,
                    'description' => $charge_description,
        ));
    }
    //Grab the charge ID and set it as the transaction ID.
    $txn_id = $charge->id; //$charge->balance_transaction;
    //Core transaction data
    $data = array();
    $data['item_name'] = $item_name;
    $data['stripeToken'] = $stripeToken;
    $data['stripeTokenType'] = $stripeTokenType;
    $data['stripeEmail'] = $stripeEmail;
    $data['item_quantity'] = $item_quantity;
    $data['item_price'] = $item_price;
    $data['currency_code'] = $currency_code;
    $data['txn_id'] = $txn_id; //The Stripe charge ID
    $data['charge_description'] = $charge_description;

    $post_data = array_map('sanitize_text_field', $data);

    //Billing address data (if any)
    $billing_address = "";
    $billing_address .= sanitize_text_field((isset($_POST['stripeBillingName']) ? $_POST['stripeBillingName'] : '')) . "\n";
    $billing_address .= sanitize_text_field((isset($_POST['stripeBillingAddressLine1']) ? $_POST['stripeBillingAddressLine1'] : '')) . sanitize_text_field(isset($_POST['stripeBillingAddressApt']) ? ' ' . $_POST['stripeBillingAddressApt'] : '') . "\n";
    $billing_address .= sanitize_text_field((isset($_POST['stripeBillingAddressZip']) ? $_POST['stripeBillingAddressZip'] : '')) . "\n";
    $billing_address .= sanitize_text_field((isset($_POST['stripeBillingAddressCity']) ? $_POST['stripeBillingAddressCity'] : '')) . "\n";
    $billing_address .= sanitize_text_field((isset($_POST['stripeBillingAddressState']) ? $_POST['stripeBillingAddressState'] : '')) . "\n";
    $billing_address .= sanitize_text_field((isset($_POST['stripeBillingAddressCountry']) ? $_POST['stripeBillingAddressCountry'] : '')) . "\n";
    $post_data['billing_address'] = $billing_address;

    //Shipping address data (if any)
    $shipping_address = "";
    $shipping_address .= sanitize_text_field((isset($_POST['stripeShippingName']) ? $_POST['stripeShippingName'] : '')) . "\n";
    $shipping_address .= sanitize_text_field(isset($_POST['stripeShippingAddressLine1']) ? $_POST['stripeShippingAddressLine1'] : '') . sanitize_text_field(isset($_POST['stripeShippingAddressApt']) ? ' ' . $_POST['stripeShippingAddressApt'] : '') . "\n";
    $shipping_address .= sanitize_text_field((isset($_POST['stripeShippingAddressZip']) ? $_POST['stripeShippingAddressZip'] : '')) . "\n";
    $shipping_address .= sanitize_text_field((isset($_POST['stripeShippingAddressCity']) ? $_POST['stripeShippingAddressCity'] : '')) . "\n";
    $shipping_address .= sanitize_text_field((isset($_POST['stripeShippingAddressState']) ? $_POST['stripeShippingAddressState'] : '')) . "\n";
    $shipping_address .= sanitize_text_field((isset($_POST['stripeShippingAddressCountry']) ? $_POST['stripeShippingAddressCountry'] : '')) . "\n";
    $post_data['shipping_address'] = $shipping_address;

    //Insert the order data to the custom post
    $order = ASPOrder::get_instance();
    $order_post_id = $order->insert($post_data, $charge);

    $post_data['order_post_id'] = $order_post_id;

    //Action hook with the checkout post data parameters.
    do_action('asp_stripe_payment_completed', $post_data, $charge);

    //Action hook with the order object.
    do_action('AcceptStripePayments_payment_completed', $order, $charge);

    $GLOBALS['asp_payment_success'] = true;
    $item_url = base64_decode($item_url);
    $post_data['item_url'] = $item_url;

    //Let's handle email sending stuff

    if (isset($opt['send_emails_to_buyer'])) {
        if ($opt['send_emails_to_buyer']) {
            $from = $opt['from_email_address'];
            $to = $post_data['stripeEmail'];
            $subj = $opt['buyer_email_subject'];
            $body = asp_apply_dynamic_tags_on_email_body($opt['buyer_email_body'], $post_data);
            $headers = 'From: ' . $from . "\r\n";

            $subj = apply_filters('asp_buyer_email_subject', $subj, $post_data);
            $body = apply_filters('asp_buyer_email_body', $body, $post_data);
            wp_mail($to, $subj, $body, $headers);
        }
    }
    if (isset($opt['send_emails_to_seller'])) {
        if ($opt['send_emails_to_seller']) {
            $from = $opt['from_email_address'];
            $to = $opt['seller_notification_email'];
            $subj = $opt['seller_email_subject'];
            $body = asp_apply_dynamic_tags_on_email_body($opt['seller_email_body'], $post_data);
            $headers = 'From: ' . $from . "\r\n";

            $subj = apply_filters('asp_seller_email_subject', $subj, $post_data);
            $body = apply_filters('asp_seller_email_body', $body, $post_data);
            wp_mail($to, $subj, $body, $headers);
        }
    }
} catch (Exception $e) {
    //If the charge fails (payment unsuccessful), this code will get triggered.
    if (!empty($charge->failure_code))
        $GLOBALS['asp_error'] = $charge->failure_code . ": " . $charge->failure_message;
    else {
        $GLOBALS['asp_error'] = $e->getMessage();
    }
    asp_ipn_completed($GLOBALS['asp_error']);
}

$_SESSION['asp_data'] = $post_data;

//Show the "payment success" or "payment failure" info on the checkout complete page.
//include (WP_ASP_PLUGIN_PATH . 'public/views/checkout.php');
//echo ob_get_clean();

asp_ipn_completed();

function asp_apply_dynamic_tags_on_email_body($body, $post) {
    $product_details = __("Product Name: ", "stripe-payments") . $post['item_name'] . "\n";
    $product_details .= __("Quantity: ", "stripe-payments") . $post['item_quantity'] . "\n";
    $product_details .= __("Amount: ", "stripe-payments") . $post['item_price'] . ' ' . $post['currency_code'] . "\n";
    $product_details .= "--------------------------------" . "\n";
    $product_details .= __("Total Amount: ", "stripe-payments") . ($post['item_price'] * $post['item_quantity']) . ' ' . $post['currency_code'] . "\n";
    if (!empty($post['item_url']))
        $product_details .= "\n\n" . __("Download link: ", "stripe-payments") . $post['item_url'];

    $tags = array("{product_details}", "{payer_email}", "{transaction_id}", "{purchase_amt}", "{purchase_date}", "{shipping_address}", "{billing_address}");
    $vals = array($product_details, $post['stripeEmail'], $post['txn_id'], $post['item_price'], date("F j, Y, g:i a", strtotime('now')), $post['shipping_address'], $post['billing_address']);

    $body = stripslashes(str_replace($tags, $vals, $body));

    return $body;
}
