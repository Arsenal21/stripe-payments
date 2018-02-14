<?php

function asp_ipn_completed( $errMsg = '' ) {
    if ( ! empty( $errMsg ) ) {
	$aspData		 = array( 'error_msg' => $errMsg );
	ASP_Debug_Logger::log( $errMsg . "\r\n", false ); //Log the error
	$_SESSION[ 'asp_data' ]	 = $aspData;

	//send email to notify site admin (if option enabled)
	$opt = get_option( 'AcceptStripePayments-settings' );
	if ( isset( $opt[ 'send_email_on_error' ] ) && $opt[ 'send_email_on_error' ] ) {
	    $to	 = $opt[ 'send_email_on_error_to' ];
	    $from	 = get_option( 'admin_email' );
	    $headers = 'From: ' . $from . "\r\n";
	    $subj	 = __( 'Stripe Payments Error', 'stripe-payments' );
	    $body	 = __( 'Following error occured during payment processing:', 'stripe-payments' ) . "\r\n\r\n";
	    $body	 .= $errMsg . "\r\n\r\n";
	    $body	 .= __( 'Debug data:', 'stripe-payments' ) . "\r\n";
	    $body	 .= json_encode( $_POST );
	    wp_mail( $to, $subj, $body, $headers );
	}
    } else {
	ASP_Debug_Logger::log( 'Payment has been processed successfully.' . "\r\n" );
    }
    global $aspRedirectURL;
    wp_redirect( $aspRedirectURL );
    exit;
}

unset( $_SESSION[ 'asp_data' ] );

$asp_class = AcceptStripePayments::get_instance();

global $aspRedirectURL;

ASP_Debug_Logger::log( 'Payment processing started.' );

$aspRedirectURL = (isset( $_POST[ 'thankyou_page_url' ] ) && empty( $_POST[ 'thankyou_page_url' ] )) ? $asp_class->get_setting( 'checkout_url' ) : base64_decode( $_POST[ 'thankyou_page_url' ] );

ASP_Debug_Logger::log( 'Triggering hook for addons to process posted data if needed.' );
$process_result = apply_filters( 'asp_before_payment_processing', array(), $_POST );

if ( isset( $process_result ) && ! empty( $process_result ) ) {
    if ( isset( $process_result[ 'error' ] ) && ! empty( $process_result[ 'error' ] ) ) {
	asp_ipn_completed( $process_result[ 'error' ] );
    }
}

//Check nonce
ASP_Debug_Logger::log( 'Checking received data.' );
$nonce = $_REQUEST[ '_wpnonce' ];
if ( ! wp_verify_nonce( $nonce, 'stripe_payments' ) ) {
    //nonce check failed
    asp_ipn_completed( "Nonce check failed." );
}

if ( ! isset( $_POST[ 'item_name' ] ) || empty( $_POST[ 'item_name' ] ) ) {
    asp_ipn_completed( 'Invalid Item name' );
}
if ( ! isset( $_POST[ 'stripeToken' ] ) || empty( $_POST[ 'stripeToken' ] ) ) {
    asp_ipn_completed( 'Invalid Stripe Token' );
}
if ( ! isset( $_POST[ 'stripeTokenType' ] ) || empty( $_POST[ 'stripeTokenType' ] ) ) {
    asp_ipn_completed( 'Invalid Stripe Token Type' );
}
if ( ! isset( $_POST[ 'stripeEmail' ] ) || empty( $_POST[ 'stripeEmail' ] ) ) {
    asp_ipn_completed( 'Invalid Request' );
}
if ( ! isset( $_POST[ 'currency_code' ] ) || empty( $_POST[ 'currency_code' ] ) ) {
    asp_ipn_completed( 'Invalid Currency Code' );
}

$item_name		 = sanitize_text_field( $_POST[ 'item_name' ] );
$stripeToken		 = sanitize_text_field( $_POST[ 'stripeToken' ] );
$stripeTokenType	 = sanitize_text_field( $_POST[ 'stripeTokenType' ] );
$stripeEmail		 = sanitize_email( $_POST[ 'stripeEmail' ] );
$item_quantity		 = sanitize_text_field( $_POST[ 'item_quantity' ] );
$item_custom_quantity	 = isset( $_POST[ 'stripeCustomQuantity' ] ) ? intval( $_POST[ 'stripeCustomQuantity' ] ) : false;
$item_url		 = sanitize_text_field( $_POST[ 'item_url' ] );
$charge_description	 = sanitize_text_field( $_POST[ 'charge_description' ] );
$button_key		 = $_POST[ 'stripeButtonKey' ];
$reported_price		 = $_POST[ 'stripeItemPrice' ];

//$item_price = sanitize_text_field($_POST['item_price']);
ASP_Debug_Logger::log( 'Checking price consistency.' );
$calculated_button_key = md5( htmlspecialchars_decode( $_POST[ 'item_name' ] ) . $reported_price );

if ( $button_key !== $calculated_button_key ) {
    asp_ipn_completed( 'Button Key mismatch. Expected ' . $button_key . ', calculated: ' . $calculated_button_key );
}
$trans_name	 = 'stripe-payments-' . $button_key;
$item_price	 = get_transient( $trans_name ); //Read the price for this item from the system.

if ( $item_price === '0' || $item_price === '' ) { //Custom amount
    $item_price = floatval( $_POST[ 'stripeAmount' ] );
}

if ( ! is_numeric( $item_price ) ) {
    asp_ipn_completed( 'Invalid item price: ' . $item_price );
}

$currency_code = strtoupper( sanitize_text_field( $_POST[ 'currency_code' ] ) );

$currencyCodeType = strtolower( $currency_code );

$amount = $item_price;

if ( ! in_array( $currency_code, $asp_class->zeroCents ) ) {
    $amount = $amount * 100;
}

if ( $item_custom_quantity !== false ) { //custom quantity
    $item_quantity = $item_custom_quantity;
}

$amount = ($item_quantity !== "NA" ? ($amount * $item_quantity) : $amount);

ASP_Debug_Logger::log( 'Getting API keys and trying to create a charge.' );

if ( $asp_class->get_setting( 'is_live' ) == 0 ) {
    //use test keys
    $key = $asp_class->get_setting( 'api_secret_key_test' );
} else {
    //use live keys
    $key = $asp_class->get_setting( 'api_secret_key' );
}

ASPMain::load_stripe_lib();

\Stripe\Stripe::setApiKey( $key );

$GLOBALS[ 'asp_payment_success' ] = false;

$opt = get_option( 'AcceptStripePayments-settings' );

ob_start();
try {

    $charge_opts = array(
	'amount'	 => $amount,
	'currency'	 => $currencyCodeType,
	'description'	 => $charge_description,
    );

    //Check if we need to add Receipt Email parameter
    if ( isset( $opt[ 'stripe_receipt_email' ] ) && $opt[ 'stripe_receipt_email' ] == 1 ) {
	$charge_opts[ 'receipt_email' ] = $stripeEmail;
    }

    //Check if we need to add Don't Save Card parameter
    if ( $opt[ 'dont_save_card' ] == 1 ) {
	$charge_opts[ 'source' ] = $stripeToken;
    } else {

	$customer = \Stripe\Customer::create( array(
	    'email'	 => $stripeEmail,
	    'card'	 => $stripeToken
	) );

	$charge_opts[ 'customer' ] = $customer->id;
    }

    $charge				 = \Stripe\Charge::create( $charge_opts );
    //Grab the charge ID and set it as the transaction ID.
    $txn_id				 = $charge->id; //$charge->balance_transaction;
    //Core transaction data
    $data				 = array();
    $data[ 'is_live' ]		 = $asp_class->get_setting( 'is_live' );
    $data[ 'item_name' ]		 = $item_name;
    $data[ 'stripeToken' ]		 = $stripeToken;
    $data[ 'stripeTokenType' ]	 = $stripeTokenType;
    $data[ 'stripeEmail' ]		 = $stripeEmail;
    $data[ 'item_quantity' ]	 = $item_quantity;
    $data[ 'item_price' ]		 = $item_price;
    $data [ 'paid_amount' ]		 = $data[ 'item_price' ] * $data[ 'item_quantity' ];
    $data[ 'currency_code' ]	 = $currency_code;
    $data[ 'txn_id' ]		 = $txn_id; //The Stripe charge ID
    $data[ 'charge_description' ]	 = $charge_description;
    $data[ 'addonName' ]		 = isset( $_POST[ 'stripeAddonName' ] ) ? sanitize_text_field( $_POST[ 'stripeAddonName' ] ) : '';
    $data[ 'button_key' ]		 = $button_key;

    if ( isset( $_POST[ 'stripeCustomField' ] ) ) {
	$data[ 'custom_field_value' ]	 = $_POST[ 'stripeCustomField' ];
	$data[ 'custom_field_name' ]	 = $_POST[ 'stripeCustomFieldName' ];
    }

    $post_data = array_map( 'sanitize_text_field', $data );

    $_POST = filter_input_array( INPUT_POST, FILTER_SANITIZE_STRING );

    //Billing address data (if any)
    $billing_address		 = "";
    $billing_address		 .= isset( $_POST[ 'stripeBillingName' ] ) ? $_POST[ 'stripeBillingName' ] . "\n" : '';
    $billing_address		 .= isset( $_POST[ 'stripeBillingAddressLine1' ] ) ? $_POST[ 'stripeBillingAddressLine1' ] . "\n" : '';
    $billing_address		 .= isset( $_POST[ 'stripeBillingAddressApt' ] ) ? $_POST[ 'stripeBillingAddressApt' ] . "\n" : '';
    $billing_address		 .= isset( $_POST[ 'stripeBillingAddressZip' ] ) ? $_POST[ 'stripeBillingAddressZip' ] . "\n" : '';
    $billing_address		 .= isset( $_POST[ 'stripeBillingAddressCity' ] ) ? $_POST[ 'stripeBillingAddressCity' ] . "\n" : '';
    $billing_address		 .= isset( $_POST[ 'stripeBillingAddressState' ] ) ? $_POST[ 'stripeBillingAddressState' ] . "\n" : '';
    $billing_address		 .= isset( $_POST[ 'stripeBillingAddressCountry' ] ) ? $_POST[ 'stripeBillingAddressCountry' ] . "\n" : '';
    $post_data[ 'billing_address' ]	 = $billing_address;

    //Shipping address data (if any)
    $shipping_address		 = "";
    $shipping_address		 .= isset( $_POST[ 'stripeShippingName' ] ) ? $_POST[ 'stripeShippingName' ] . "\n" : '';
    $shipping_address		 .= isset( $_POST[ 'stripeShippingAddressLine1' ] ) ? $_POST[ 'stripeShippingAddressLine1' ] . "\n" : '';
    $shipping_address		 .= isset( $_POST[ 'stripeShippingAddressApt' ] ) ? $_POST[ 'stripeShippingAddressApt' ] . "\n" : '';
    $shipping_address		 .= isset( $_POST[ 'stripeShippingAddressZip' ] ) ? $_POST[ 'stripeShippingAddressZip' ] . "\n" : '';
    $shipping_address		 .= isset( $_POST[ 'stripeShippingAddressCity' ] ) ? $_POST[ 'stripeShippingAddressCity' ] . "\n" : '';
    $shipping_address		 .= isset( $_POST[ 'stripeShippingAddressState' ] ) ? $_POST[ 'stripeShippingAddressState' ] . "\n" : '';
    $shipping_address		 .= isset( $_POST[ 'stripeShippingAddressCountry' ] ) ? $_POST[ 'stripeShippingAddressCountry' ] . "\n" : '';
    $post_data[ 'shipping_address' ] = $shipping_address;

    //Insert the order data to the custom post
    $order		 = ASPOrder::get_instance();
    $order_post_id	 = $order->insert( $post_data, $charge );

    $post_data[ 'order_post_id' ] = $order_post_id;

    // handle download item url
    $item_url		 = apply_filters( 'asp_item_url_process', $item_url, $post_data );
    $item_url		 = base64_decode( $item_url );
    $post_data[ 'item_url' ] = $item_url;

    ASP_Debug_Logger::log( 'Firing post-payment hooks.' );

    //Action hook with the checkout post data parameters.
    do_action( 'asp_stripe_payment_completed', $post_data, $charge );

    //Action hook with the order object.
    do_action( 'AcceptStripePayments_payment_completed', $order, $charge );

    $GLOBALS[ 'asp_payment_success' ] = true;

    //Let's handle email sending stuff

    if ( isset( $opt[ 'send_emails_to_buyer' ] ) ) {
	if ( $opt[ 'send_emails_to_buyer' ] ) {
	    $from	 = $opt[ 'from_email_address' ];
	    $to	 = $post_data[ 'stripeEmail' ];
	    $subj	 = $opt[ 'buyer_email_subject' ];
	    $body	 = asp_apply_dynamic_tags_on_email_body( $opt[ 'buyer_email_body' ], $post_data );
	    $headers = 'From: ' . $from . "\r\n";

	    $subj	 = apply_filters( 'asp_buyer_email_subject', $subj, $post_data );
	    $body	 = apply_filters( 'asp_buyer_email_body', $body, $post_data );
	    wp_mail( $to, $subj, $body, $headers );
	}
    }
    if ( isset( $opt[ 'send_emails_to_seller' ] ) ) {
	if ( $opt[ 'send_emails_to_seller' ] ) {
	    $from	 = $opt[ 'from_email_address' ];
	    $to	 = $opt[ 'seller_notification_email' ];
	    $subj	 = $opt[ 'seller_email_subject' ];
	    $body	 = asp_apply_dynamic_tags_on_email_body( $opt[ 'seller_email_body' ], $post_data );
	    $headers = 'From: ' . $from . "\r\n";

	    $subj	 = apply_filters( 'asp_seller_email_subject', $subj, $post_data );
	    $body	 = apply_filters( 'asp_seller_email_body', $body, $post_data );
	    wp_mail( $to, $subj, $body, $headers );
	}
    }
} catch ( Exception $e ) {
    //If the charge fails (payment unsuccessful), this code will get triggered.
    if ( ! empty( $charge->failure_code ) )
	$GLOBALS[ 'asp_error' ] = $charge->failure_code . ": " . $charge->failure_message;
    else {
	$GLOBALS[ 'asp_error' ] = $e->getMessage();
    }
    asp_ipn_completed( $GLOBALS[ 'asp_error' ] );
}

$_SESSION[ 'asp_data' ] = $post_data;

//Show the "payment success" or "payment failure" info on the checkout complete page.
//include (WP_ASP_PLUGIN_PATH . 'public/views/checkout.php');
//echo ob_get_clean();

asp_ipn_completed();

function asp_apply_dynamic_tags_on_email_body( $body, $post ) {
    $product_details = __( "Product Name: ", "stripe-payments" ) . $post[ 'item_name' ] . "\n";
    $product_details .= __( "Quantity: ", "stripe-payments" ) . $post[ 'item_quantity' ] . "\n";
    $product_details .= __( "Price: ", "stripe-payments" ) . AcceptStripePayments::formatted_price( $post[ 'item_price' ], $post[ 'currency_code' ] ) . "\n";
    $product_details .= "--------------------------------" . "\n";
    $product_details .= __( "Total Amount: ", "stripe-payments" ) . AcceptStripePayments::formatted_price( ($post[ 'item_price' ] * $post[ 'item_quantity' ] ), $post[ 'currency_code' ] ) . "\n";
    if ( ! empty( $post[ 'item_url' ] ) )
	$product_details .= "\n\n" . __( "Download link: ", "stripe-payments" ) . $post[ 'item_url' ];

    $custom_field = '';
    if ( isset( $post[ 'custom_field_value' ] ) ) {
	$custom_field = $post[ 'custom_field_name' ] . ': ' . $post[ 'custom_field_value' ];
    }

    $tags	 = array( "{product_details}", "{payer_email}", "{transaction_id}", "{purchase_amt}", "{purchase_date}", "{shipping_address}", "{billing_address}", '{custom_field}' );
    $vals	 = array( $product_details, $post[ 'stripeEmail' ], $post[ 'txn_id' ], $post[ 'item_price' ], date( "F j, Y, g:i a", strtotime( 'now' ) ), $post[ 'shipping_address' ], $post[ 'billing_address' ], $custom_field );

    $body = stripslashes( str_replace( $tags, $vals, $body ) );

    return $body;
}
