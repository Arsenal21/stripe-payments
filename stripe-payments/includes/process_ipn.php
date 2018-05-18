<?php

function asp_ipn_completed( $errMsg = '' ) {
    if ( ! empty( $errMsg ) ) {
	$aspData = array( 'error_msg' => $errMsg );
	ASP_Debug_Logger::log( $errMsg, false ); //Log the error

	$msg_before_process	 = __( "Error occured before user interacted with payment popup. This might be caused by JavaScript errors on page.", 'stripe-payments' );
	$msg_after_process	 = __( "Error occured after user interacted with popup.", 'stripe-payments' );

	if ( isset( $_POST[ 'clickProcessed' ] ) ) {
	    $additional_msg = $msg_after_process . "\r\n";
	} else {
	    $additional_msg = $msg_before_process . "\r\n";
	}

	ASP_Debug_Logger::log( $additional_msg, false );

	$_SESSION[ 'asp_data' ] = $aspData;

	//send email to notify site admin (if option enabled)
	$opt = get_option( 'AcceptStripePayments-settings' );
	if ( isset( $opt[ 'send_email_on_error' ] ) && $opt[ 'send_email_on_error' ] ) {
	    $to	 = $opt[ 'send_email_on_error_to' ];
	    $from	 = get_option( 'admin_email' );
	    $headers = 'From: ' . $from . "\r\n";
	    $subj	 = __( 'Stripe Payments Error', 'stripe-payments' );
	    $body	 = __( 'Following error occured during payment processing:', 'stripe-payments' ) . "\r\n\r\n";
	    $body	 .= $errMsg . "\r\n\r\n";
	    $body	 .= $additional_msg . "\r\n";
	    $body	 .= __( 'Debug data:', 'stripe-payments' ) . "\r\n";
	    foreach ( $_POST as $key => $value ) {
		$body .= $key . ': ' . $value . "\r\n";
	    }
	    wp_mail( $to, $subj, $body, $headers );
	    $asp_class = AcceptStripePayments::get_instance();
	    wp_redirect( $asp_class->get_setting( 'checkout_url' ) );
	}
    } else {
	ASP_Debug_Logger::log( 'Payment has been processed successfully.' . "\r\n" );
	global $aspRedirectURL;
	wp_redirect( $aspRedirectURL );
    }
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

if ( ! isset( $_POST[ 'stripeToken' ] ) || empty( $_POST[ 'stripeToken' ] ) ) {
    asp_ipn_completed( 'Invalid Stripe Token' );
}
if ( ! isset( $_POST[ 'stripeTokenType' ] ) || empty( $_POST[ 'stripeTokenType' ] ) ) {
    asp_ipn_completed( 'Invalid Stripe Token Type' );
}

if ( ! isset( $_POST[ 'stripeEmail' ] ) || empty( $_POST[ 'stripeEmail' ] ) ) {
    asp_ipn_completed( 'Invalid Request' );
}

$got_product_data_from_db = false;

if ( isset( $_POST[ 'stripeProductId' ] ) && ! empty( $_POST[ 'stripeProductId' ] ) ) {
    //got product ID. Let's try to get required data from database instead of $_POST data
    $id	 = intval( $_POST[ 'stripeProductId' ] );
    ASP_Debug_Logger::log( 'Got product ID: ' . $id . '. Trying to get info from database.' );
    $post	 = get_post( $id );
    if ( ! $post || get_post_type( $id ) != ASPMain::$products_slug ) {
	//this is not Stripe Payments product
	asp_ipn_completed( 'Invalid product ID: ' . $id );
    }
    $item_name	 = $post->post_title;
    $item_url	 = get_post_meta( $id, 'asp_product_upload', true );

    if ( ! empty( $item_url ) ) {
	$item_url = base64_encode( $item_url );
    } else {
	$item_url = '';
    }

    $currency_code = get_post_meta( $id, 'asp_product_currency', true );

    if ( ! $currency_code ) {
	$currency_code = $asp_class->get_setting( 'currency_code' );
    }

    $item_quantity = get_post_meta( $id, 'asp_product_quantity', true );

    $item_custom_quantity = get_post_meta( $id, 'asp_product_custom_quantity', true );

    if ( $item_custom_quantity ) {
	//custom quantity. Let's get the value from $_POST data
	$item_custom_quantity = intval( $_POST[ 'stripeCustomQuantity' ] );
    } else {
	$item_custom_quantity = false;
    }

    $variable = false;

    $item_price = get_post_meta( $id, 'asp_product_price', true );

    if ( empty( $item_price ) ) {
	//this is probably custom price
	$variable	 = true;
	$item_price	 = floatval( $_POST[ 'stripeAmount' ] );
    }

    //apply tax and shipping if needed

    $tax = get_post_meta( $id, 'asp_product_tax', true );

    $shipping = floatval( get_post_meta( $id, 'asp_product_shipping', true ) );

    //let's apply filter so addons can change price, currency and shipping if needed
    $price_arr	 = array( 'price' => $item_price, 'currency' => $currency_code, 'shipping' => empty( $shipping ) ? false : $shipping, 'variable' => $variable );
    $price_arr	 = apply_filters( 'asp_modify_price_currency_shipping', $price_arr );
    extract( $price_arr, EXTR_OVERWRITE );
    $item_price	 = $price;
    $currency_code	 = $currency;

    $got_product_data_from_db = true;
    ASP_Debug_Logger::log( 'Got required product info from database.' );
}

if ( ! $got_product_data_from_db ) {
    //couldn't get data from database by product ID for some reason. Getting data from $_POST instead

    if ( ! isset( $_POST[ 'item_name' ] ) || empty( $_POST[ 'item_name' ] ) ) {
	asp_ipn_completed( 'Invalid Item name' );
    }

    if ( ! isset( $_POST[ 'currency_code' ] ) || empty( $_POST[ 'currency_code' ] ) ) {
	asp_ipn_completed( 'Invalid Currency Code' );
    }

    $item_name		 = sanitize_text_field( $_POST[ 'item_name' ] );
    $item_quantity		 = sanitize_text_field( $_POST[ 'item_quantity' ] );
    $item_custom_quantity	 = isset( $_POST[ 'stripeCustomQuantity' ] ) ? intval( $_POST[ 'stripeCustomQuantity' ] ) : false;
    $item_url		 = sanitize_text_field( $_POST[ 'item_url' ] );
    $button_key		 = $_POST[ 'stripeButtonKey' ];
    $reported_price		 = $_POST[ 'stripeItemPrice' ];

    ASP_Debug_Logger::log( 'Checking price consistency.' );
    $calculated_button_key = md5( htmlspecialchars_decode( $_POST[ 'item_name' ] ) . $reported_price );

    if ( $button_key !== $calculated_button_key ) {
	asp_ipn_completed( 'Button Key mismatch. Expected ' . $button_key . ', calculated: ' . $calculated_button_key );
    }
    $trans_name	 = 'stripe-payments-' . $button_key;
    $trans		 = get_transient( $trans_name ); //Read the price for this item from the system.
    $item_price	 = $trans[ 'price' ];

    $tax = isset( $trans[ 'tax' ] ) ? $trans[ 'tax' ] : 0;

    $shipping = isset( $trans[ 'shipping' ] ) ? $trans[ 'shipping' ] : 0;

    $currency_code = strtoupper( sanitize_text_field( $_POST[ 'currency_code' ] ) );

    if ( ! AcceptStripePayments::is_zero_cents( $currency_code ) ) {
	$shipping = $shipping / 100;
    }
}

if ( empty( $item_price ) ) { //Custom amount
    $item_price = floatval( $_POST[ 'stripeAmount' ] );
}

if ( ! is_numeric( $item_price ) ) {
    asp_ipn_completed( 'Invalid item price: ' . $item_price );
}

$currencyCodeType = strtolower( $currency_code );

$stripeToken		 = sanitize_text_field( $_POST[ 'stripeToken' ] );
$stripeTokenType	 = sanitize_text_field( $_POST[ 'stripeTokenType' ] );
$stripeEmail		 = sanitize_email( $_POST[ 'stripeEmail' ] );
$charge_description	 = sanitize_text_field( $_POST[ 'charge_description' ] );

$amount = $item_price;

//apply tax if needed
$amount = AcceptStripePayments::apply_tax( $amount, $tax, AcceptStripePayments::is_zero_cents( $currency_code ) );

if ( $item_custom_quantity !== false ) { //custom quantity
    $item_quantity = $item_custom_quantity;
}

if ( empty( $item_quantity ) ) {
    $item_quantity = 1;
}

$amount = ($item_quantity !== "NA" ? ($amount * $item_quantity) : $amount);

//add shipping cost
$amount = AcceptStripePayments::apply_shipping( $amount, $shipping );

$amount_in_cents = $amount;

if ( ! AcceptStripePayments::is_zero_cents( $currency_code ) ) {
    $amount_in_cents = $amount_in_cents * 100;
}

ASP_Debug_Logger::log( 'Getting API keys and trying to create a charge.' );

ASPMain::load_stripe_lib();

\Stripe\Stripe::setApiKey( $asp_class->APISecKey );

$GLOBALS[ 'asp_payment_success' ] = false;

$opt = get_option( 'AcceptStripePayments-settings' );

$data				 = array();
$data[ 'product_id' ]		 = isset( $_POST[ 'stripeProductId' ] ) && ! empty( $_POST[ 'stripeProductId' ] ) ? intval( $_POST[ 'stripeProductId' ] ) : '';
$data[ 'is_live' ]		 = $asp_class->get_setting( 'is_live' );
$data[ 'item_name' ]		 = $item_name;
$data[ 'stripeToken' ]		 = $stripeToken;
$data[ 'stripeTokenType' ]	 = $stripeTokenType;
$data[ 'stripeEmail' ]		 = $stripeEmail;
$data[ 'item_quantity' ]	 = $item_quantity;
$data[ 'item_price' ]		 = $item_price;
$data[ 'paid_amount' ]		 = $amount;
$data[ 'amount_in_cents' ]	 = $amount_in_cents;
$data[ 'currency_code' ]	 = $currency_code;
$data[ 'charge_description' ]	 = $charge_description;
$data[ 'addonName' ]		 = isset( $_POST[ 'stripeAddonName' ] ) ? sanitize_text_field( $_POST[ 'stripeAddonName' ] ) : '';
$data[ 'button_key' ]		 = isset( $button_key ) ? $button_key : '';
if ( isset( $_POST[ 'stripeCustomField' ] ) ) {
    $data[ 'custom_field_value' ]	 = $_POST[ 'stripeCustomField' ];
    $data[ 'custom_field_name' ]	 = $_POST[ 'stripeCustomFieldName' ];
}

ob_start();

//let addons process payment if needed
ASP_Debug_Logger::log( 'Firing pre-payment hook.' );
$data = apply_filters( 'asp_process_charge', $data );

if ( empty( $data[ 'charge' ] ) ) {
    ASP_Debug_Logger::log( 'Processing payment.' );

    try {

	$charge_opts = array(
	    'amount'	 => $amount_in_cents,
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

	//Check if we need to include custom field in metadata
	if ( isset( $_POST[ 'stripeCustomField' ] ) ) {
	    $metadata			 = array(
		'custom_field_value'	 => isset( $_POST[ 'stripeCustomField' ] ) ? $_POST[ 'stripeCustomField' ] : '',
		'custom_field_name'	 => $_POST[ 'stripeCustomFieldName' ],
	    );
	    $charge_opts[ 'metadata' ]	 = $metadata;
	}

	$data[ 'charge' ] = \Stripe\Charge::create( $charge_opts );
    } catch ( Exception $e ) {
	//If the charge fails (payment unsuccessful), this code will get triggered.
	if ( ! empty( $data[ 'charge' ]->failure_code ) )
	    $GLOBALS[ 'asp_error' ] = $data[ 'charge' ]->failure_code . ": " . $data[ 'charge' ]->failure_message;
	else {
	    $GLOBALS[ 'asp_error' ] = $e->getMessage();
	}
	asp_ipn_completed( $GLOBALS[ 'asp_error' ] );
    }
}

//Grab the charge ID and set it as the transaction ID.
$txn_id			 = $data[ 'charge' ]->id; //$charge->balance_transaction;
//Core transaction data
$data[ 'txn_id' ]	 = $txn_id; //The Stripe charge ID

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

$post_data[ 'additional_items' ] = array();

if ( isset( $tax ) && ! empty( $tax ) ) {
    $tax_amt								 = AcceptStripePayments::get_tax_amount( $post_data[ 'item_price' ], $tax, AcceptStripePayments::is_zero_cents( $currency_code ) );
    $post_data[ 'additional_items' ][ __( 'Tax', 'stripe-payments' ) ]	 = $tax_amt;
    $post_data[ 'tax_perc' ]						 = $tax;
    $post_data[ 'tax' ]							 = $tax_amt;
}

if ( isset( $shipping ) && ! empty( $shipping ) ) {
    $post_data[ 'additional_items' ][ __( 'Shipping', 'stripe-payments' ) ]	 = $shipping;
    $post_data[ 'shipping' ]						 = $shipping;
}

//Insert the order data to the custom post
$order		 = ASPOrder::get_instance();
$order_post_id	 = $order->insert( $post_data, $data[ 'charge' ] );

$post_data[ 'order_post_id' ] = $order_post_id;

// handle download item url
$item_url		 = apply_filters( 'asp_item_url_process', $item_url, $post_data );
$item_url		 = base64_decode( $item_url );
$post_data[ 'item_url' ] = $item_url;

ASP_Debug_Logger::log( 'Firing post-payment hooks.' );

//Action hook with the checkout post data parameters.
do_action( 'asp_stripe_payment_completed', $post_data, $data[ 'charge' ] );

//Action hook with the order object.
do_action( 'AcceptStripePayments_payment_completed', $order, $data[ 'charge' ] );

$GLOBALS[ 'asp_payment_success' ] = true;

//check if we need to deal with stock
if ( ! empty( $data[ 'product_id' ] ) && get_post_meta( $data[ 'product_id' ], 'asp_product_enable_stock', true ) ) {
    $stock_items	 = intval( get_post_meta( $data[ 'product_id' ], 'asp_product_stock_items', true ) );
    $stock_items	 = $stock_items - $data[ 'item_quantity' ];
    if ( $stock_items < 0 ) {
	$stock_items = 0;
    }
    update_post_meta( $data[ 'product_id' ], 'asp_product_stock_items', $stock_items );
    $data[ 'stock_items' ] = $stock_items;
}

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
	ASP_Debug_Logger::log( 'Notification email sent to buyer: ' . $to . ', From email address used: ' . $from );
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
	ASP_Debug_Logger::log( 'Notification email sent to seller: ' . $to . ', From email address used: ' . $from );
    }
}

$_SESSION[ 'asp_data' ] = $post_data;

//Show the "payment success" or "payment failure" info on the checkout complete page.
//include (WP_ASP_PLUGIN_PATH . 'public/views/checkout.php');
//echo ob_get_clean();

asp_ipn_completed();

function asp_apply_dynamic_tags_on_email_body( $body, $post ) {
    $product_details = __( "Product Name: ", "stripe-payments" ) . $post[ 'item_name' ] . "\n";
    $product_details .= __( "Quantity: ", "stripe-payments" ) . $post[ 'item_quantity' ] . "\n";
    $product_details .= __( "Item Price: ", "stripe-payments" ) . AcceptStripePayments::formatted_price( $post[ 'item_price' ], $post[ 'currency_code' ] ) . "\n";
    //check if there are any additional items available like tax and shipping cost
    if ( ! empty( $post[ 'additional_items' ] ) ) {
	foreach ( $post[ 'additional_items' ] as $item => $price ) {
	    $product_details .= $item . ": " . AcceptStripePayments::formatted_price( $price, $post[ 'currency_code' ] ) . "\n";
	}
    }
    $product_details .= "--------------------------------" . "\n";
    $product_details .= __( "Total Amount: ", "stripe-payments" ) . AcceptStripePayments::formatted_price( $post[ 'paid_amount' ], $post[ 'currency_code' ] ) . "\n";
    if ( ! empty( $post[ 'item_url' ] ) ) {
	$product_details .= "\n\n" . __( "Download link: ", "stripe-payments" ) . $post[ 'item_url' ];
    }

    $custom_field = '';
    if ( isset( $post[ 'custom_field_value' ] ) ) {
	$custom_field = $post[ 'custom_field_name' ] . ': ' . $post[ 'custom_field_value' ];
    }

    $curr = $post[ 'currency_code' ];

    $currencies = AcceptStripePayments::get_currencies();
    if ( isset( $currencies[ $curr ] ) ) {
	$curr_sym = $currencies[ $curr ][ 1 ];
    } else {
	$curr_sym = '';
    }

    $item_price = AcceptStripePayments::formatted_price( $post[ 'item_price' ], false );

    $item_price_curr = AcceptStripePayments::formatted_price( $post[ 'item_price' ], $post[ 'currency_code' ] );

    $purchase_amt = AcceptStripePayments::formatted_price( $post[ 'paid_amount' ], false );

    $purchase_amt_curr = AcceptStripePayments::formatted_price( $post[ 'paid_amount' ], $post[ 'currency_code' ] );


    $tax		 = 0;
    $tax_amt	 = 0;
    $shipping	 = 0;

    if ( isset( $post[ 'tax_perc' ] ) && ! empty( $post[ 'tax_perc' ] ) ) {
	$tax = $post[ 'tax_perc' ] . '%';
    }
    if ( isset( $post[ 'tax' ] ) && ! empty( $post[ 'tax' ] ) ) {
	$tax_amt = AcceptStripePayments::formatted_price( $post[ 'tax' ], $post[ 'currency_code' ] );
    }
    if ( isset( $post[ 'shipping' ] ) && ! empty( $post[ 'shipping' ] ) ) {
	$shipping = AcceptStripePayments::formatted_price( $post[ 'shipping' ], $post[ 'currency_code' ] );
    }

    $tags	 = array(
	"{product_details}",
	"{payer_email}",
	"{transaction_id}",
	"{item_price}",
	"{item_price_curr}",
	"{purchase_amt}",
	"{purchase_amt_curr}",
	"{tax}",
	"{tax_amt}",
	"{shipping_amt}",
	"{currency}",
	"{currency_code}",
	"{purchase_date}",
	"{shipping_address}",
	"{billing_address}",
	'{custom_field}'
    );
    $vals	 = array(
	$product_details,
	$post[ 'stripeEmail' ],
	$post[ 'txn_id' ],
	$item_price,
	$item_price_curr,
	$purchase_amt,
	$purchase_amt_curr,
	$tax,
	$tax_amt,
	$shipping,
	$curr_sym,
	$curr,
	date( "F j, Y, g:i a", strtotime( 'now' ) ),
	$post[ 'shipping_address' ],
	$post[ 'billing_address' ],
	$custom_field );

    $body = stripslashes( str_replace( $tags, $vals, $body ) );

    return $body;
}
