<?php

class ASPPaymentHandlerNG {

    private $ASPClass;
    protected static $instance = null;

    function __construct() {
	$this->ASPClass = AcceptStripePayments::get_instance();
	add_action( 'init', array( $this, 'init_tasks' ) );
	if ( ! is_admin() ) {
	    $asp_result = filter_input( INPUT_GET, 'asp_result', FILTER_SANITIZE_STRING );
	    if ( $asp_result === 'success' ) {
		add_action( 'init', array( $this, 'process_payment_success' ) );
	    }
	}
    }

    public static function get_instance() {

	// If the single instance hasn't been set, set it now.
	if ( null == self::$instance ) {
	    self::$instance = new self;
	}

	return self::$instance;
    }

    public static function init_tasks() {
	if ( wp_doing_ajax() ) {
	    add_action( 'wp_ajax_asp_ng_get_token', array( $this, 'get_token' ) );
	    add_action( 'wp_ajax_nopriv_asp_ng_get_token', array( $this, 'get_token' ) );
	}
    }

    public function process_payment_success() {
	$sess = ASP_Session::get_instance();

	$trans_data = $sess->get_transient_data( 'trans_info' );

	if ( empty( $trans_data[ 'trans_id' ] ) ) {
	    $trans_id	 = filter_input( INPUT_GET, 'asp_trans_id', FILTER_SANITIZE_STRING );
	    $trans_info	 = explode( '|', $trans_id );
	    if ( empty( $trans_info[ 1 ] ) ) {
		wp_die( 'Cannot find transaction info.' );
	    } else {
		$prod_id = intval( $trans_info[ 1 ] );
	    }
	} else {
	    $trans_id	 = $trans_data[ 'trans_id' ];
	    $prod_id	 = $trans_data[ 'prod_id' ];
	}

	$is_live = $trans_data[ 'is_live' ];

	$pi_id = false;

	ASPMain::load_stripe_lib();

	$key = $is_live ? $this->ASPClass->APISecKey : $this->ASPClass->APISecKeyTest;

	\Stripe\Stripe::setApiKey( $key );

	$events = \Stripe\Event::all( [
	    'type'		 => 'checkout.session.completed',
	    'created'	 => [
		'gte' => time() - 24 * 60,
	    ],
	] );

	foreach ( $events->autoPagingIterator() as $event ) {
	    $session = $event->data->object;
	    if ( isset( $session->client_reference_id ) && $session->client_reference_id === $trans_id ) {
		$pi_id = $session->payment_intent;
		break;
	    }
	}
	if ( $pi_id !== false ) {
	    $item		 = new ASPItem( $prod_id );
	    $redir_url	 = $item->get_redir_url();
	    //check if transaction has been processed
	    $completed_order = get_posts( array(
		'post_type'	 => 'stripe_order',
		'meta_key'	 => 'trans_id',
		'meta_value'	 => $trans_id )
	    );

	    $sess->set_transient_data( 'trans_info', array() );

	    if ( ! empty( $completed_order ) ) {
		//already processed - let's redirect to results page
		wp_redirect( $redir_url );
		exit;
	    }
	    $pi				 = \Stripe\PaymentIntent::retrieve( $pi_id );
	    $charge				 = $pi->charges;
	    $data				 = array();
	    $data[ 'paid_amount' ]		 = AcceptStripePayments::from_cents( $pi->amount_received, $pi->currency );
	    $data[ 'currency_code' ]	 = strtoupper( $pi->currency );
	    $data[ 'item_quantity' ]	 = $item->get_quantity();
	    $data[ 'charge' ]		 = $charge->data[ 0 ];
	    $data[ 'stripeToken' ]		 = '';
	    $data[ 'stripeTokenType' ]	 = 'card';
	    $data[ 'is_live' ]		 = $is_live;
	    $data[ 'charge_description' ]	 = $item->get_description();
	    $data[ 'item_name' ]		 = $item->get_name();
	    $price				 = $item->get_price();
	    if ( empty( $price ) ) {
		$price	 = $session->display_items[ 0 ]->amount;
		$price	 = AcceptStripePayments::from_cents( $price, $item->get_currency() );
		$item->set_price( $price );
	    }
	    $data[ 'item_price' ]	 = $price;
	    $data[ 'stripeEmail' ]	 = $charge->data[ 0 ]->billing_details->email;
	    $data[ 'customer_name' ] = $charge->data[ 0 ]->billing_details->name;

	    $purchase_date	 = date( 'Y-m-d H:i:s', $charge->data[ 0 ]->created );
	    $purchase_date	 = get_date_from_gmt( $purchase_date, get_option( 'date_format' ) . ', ' . get_option( 'time_format' ) );

	    $data[ 'purchase_date' ]	 = $purchase_date;
	    $data[ 'charge_date' ]		 = $purchase_date;
	    $data[ 'charge_date_raw' ]	 = $charge->data[ 0 ]->created;

	    $data[ 'txn_id' ] = $charge->data[ 0 ]->id;

	    $data[ 'additional_items' ] = array();

	    if ( ! empty( $item->get_tax() ) ) {
		$taxStr							 = apply_filters( 'asp_customize_text_msg', __( 'Tax', 'stripe-payments' ), 'tax_str' );
		$tax_amt						 = $item->get_tax_amount( false );
		$data[ 'additional_items' ][ ucfirst( $taxStr ) ]	 = $tax_amt;
		$data[ 'tax_perc' ]					 = $item->get_tax();
		$data[ 'tax' ]						 = $tax_amt;
	    }

	    if ( ! empty( $item->get_shipping() ) ) {
		$shipStr						 = apply_filters( 'asp_customize_text_msg', __( 'Shipping', 'stripe-payments' ), 'shipping_str' );
		$data[ 'additional_items' ][ ucfirst( $shipStr ) ]	 = $item->get_shipping();
		$data[ 'shipping' ]					 = $item->get_shipping();
	    }

	    //custom fields
	    $custom_fields = $sess->get_transient_data( 'custom_fields' );
	    if ( ! empty( $custom_fields ) ) {
		$data[ 'custom_fields' ] = $custom_fields;
	    }

	    $product_details = __( "Product Name: ", "stripe-payments" ) . $data[ 'item_name' ] . "\n";
	    $product_details .= __( "Quantity: ", "stripe-payments" ) . $data[ 'item_quantity' ] . "\n";
	    $product_details .= __( "Item Price: ", "stripe-payments" ) . AcceptStripePayments::formatted_price( $data[ 'item_price' ], $data[ 'currency_code' ] ) . "\n";
	    //check if there are any additional items available like tax and shipping cost
	    $product_details .= AcceptStripePayments::gen_additional_items( $data );
	    $product_details .= "--------------------------------" . "\n";
	    $product_details .= __( "Total Amount: ", "stripe-payments" ) . AcceptStripePayments::formatted_price( $data[ 'paid_amount' ], $data[ 'currency_code' ] ) . "\n";

	    $data[ 'product_details' ] = nl2br( $product_details );

	    //Insert the order data to the custom post
	    $dont_create_order = $this->ASPClass->get_setting( 'dont_create_order' );
	    if ( ! $dont_create_order ) {
		$order			 = ASPOrder::get_instance();
		$order_post_id		 = $order->insert( $data, $data[ 'charge' ] );
		$data[ 'order_post_id' ] = $order_post_id;
		update_post_meta( $order_post_id, 'order_data', $data );
		update_post_meta( $order_post_id, 'charge_data', $data[ 'charge' ] );
		update_post_meta( $order_post_id, 'trans_id', $trans_id );
	    }

	    $sess->set_transient_data( 'asp_data', $data );

	    wp_redirect( $redir_url );
	    exit;
	}
	wp_die( 'No info found yet. Refresh page.' );
    }

    public function get_token() {
	$prod_id = filter_input( INPUT_POST, 'product_id', FILTER_SANITIZE_NUMBER_INT );
	if ( empty( $prod_id ) ) {
	    wp_send_json( array( 'success' => false, 'errMsg' => 'No product ID set' ) );
	}
	$item = new ASPItem( $prod_id );
	if ( ! empty( $item->get_last_error() ) ) {
	    wp_send_json( array( 'success' => false, 'errMsg' => "Can't load product info" ) );
	}

	$form_data = filter_input( INPUT_POST, 'form_data', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );

	$is_live = filter_input( INPUT_POST, 'is_live', FILTER_SANITIZE_NUMBER_INT );

	ASPMain::load_stripe_lib();

	$key = $is_live ? $this->ASPClass->APISecKey : $this->ASPClass->APISecKeyTest;

	\Stripe\Stripe::setApiKey( $key );

	$site_url = get_home_url( null, '/' );

	$sess		 = ASP_Session::get_instance();
	$trans_id	 = md5( uniqid( 'asp_trans_id', true ) ) . '|' . $prod_id;

	$sess->set_transient_data( 'trans_info', array( 'prod_id' => $prod_id, 'trans_id' => $trans_id, 'is_live' => $is_live ) );

	$current_url = filter_input( INPUT_POST, 'current_url', FILTER_SANITIZE_URL );

	$current_url = empty( $current_url ) ? $site_url : $current_url;

	$sData = array(
	    'payment_method_types'	 => array( 'card' ),
	    'success_url'		 => add_query_arg( array( 'asp_result' => 'success', 'asp_trans_id' => $trans_id ), $site_url ),
	    'cancel_url'		 => $current_url,
	    'client_reference_id'	 => $trans_id,
	);

	if ( $item->collect_billing_addr() ) {
	    $sData[ 'billing_address_collection' ] = 'required';
	}

	//check for variable price
	$price = $item->get_price();
	if ( empty( $price ) ) {
	    //variable price. Get price from form data
	    if ( isset( $form_data[ 'stripeAmount' ] ) && ! empty( $form_data[ 'stripeAmount' ] ) ) {
		$price = floatval( $form_data[ 'stripeAmount' ] );
		$item->set_price( $price );
	    }
	    if ( empty( $price ) ) {
		wp_send_json( array( 'success' => false, 'errMsg' => "Invalid amount provided" ) );
		exit;
	    }
	}

	$sData[ 'line_items' ] = $item->gen_item_data();

	$metadata = array();

	//Custom Field
	$custom_fields = array();
	if ( isset( $form_data[ 'stripeCustomField' ] ) ) {
	    $custom_fields[] = array( 'name' => $form_data[ 'stripeCustomFieldName' ], 'value' => $form_data[ 'stripeCustomField' ] );
	}

	//compatability with ACF addon
	$postArr = array();
	foreach ( $form_data as $key => $val ) {
	    if ( preg_match( '/^stripeCustomFields\[(.*)/', $key, $m ) ) {
		$postArr[ $m[ 1 ] ] = $val;
	    }
	}

	if ( ! empty( $postArr ) ) {
	    $_POST[ 'stripeCustomFields' ] = $postArr;
	}

	$custom_fields = apply_filters( 'asp_process_custom_fields', $custom_fields, array( 'product_id' => $prod_id ) );

	//add to session
	$sess->set_transient_data( 'custom_fields', $custom_fields );

	if ( ! empty( $custom_fields ) ) {
	    //add to metadata
	    $cfStr = '';
	    foreach ( $custom_fields as $cf ) {
		$cfStr .= $cf[ 'name' ] . ': ' . $cf[ 'value' ] . ' | ';
	    }
	    $cfStr				 = rtrim( $cfStr, ' | ' );
	    //trim the string as metadata value cannot exceed 500 chars
	    $cfStr				 = substr( $cfStr, 0, 499 );
	    $metadata[ 'Custom Fields' ]	 = $cfStr;
	}

	if ( ! empty( $metadata ) ) {
	    $sData[ 'payment_intent_data' ]			 = array();
	    $sData[ 'payment_intent_data' ][ 'metadata' ]	 = $metadata;
	}

	$session = \Stripe\Checkout\Session::create( $sData );

	wp_send_json( array( 'success' => true, 'checkoutSessionId' => $session->id, 'sData' => $sData ) );
	exit;
    }

}

ASPPaymentHandlerNG::get_instance();
