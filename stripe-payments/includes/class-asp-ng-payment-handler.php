<?php

class ASPPaymentHandlerNG {

    private $ASPClass;
    protected static $instance = null;

    function __construct() {
	$this->ASPClass = AcceptStripePayments::get_instance();
	if ( wp_doing_ajax() ) {
	    add_action( 'wp_ajax_asp_ng_get_token', array( $this, 'get_token' ) );
	    add_action( 'wp_ajax_nopriv_asp_ng_get_token', array( $this, 'get_token' ) );
	}
	if ( ! is_admin() ) {
	    $asp_result = filter_input( INPUT_GET, 'asp_result', FILTER_SANITIZE_STRING );
	    if ( $asp_result === 'success' ) {
		add_action( 'plugins_loaded', array( $this, 'process_payment_success' ) );
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

    public function process_payment_success() {
	var_dump( $_POST );
	die;
    }

    public function get_token() {
	$prod_id = filter_input( INPUT_POST, 'product_id', FILTER_SANITIZE_NUMBER_INT );
	if ( empty( $prod_id ) ) {
	    wp_send_json( array( 'success' => false, 'errMsg' => 'No product ID sent' ) );
	}
	$item = new ASPItem( $prod_id );
	if ( ! empty( $item->get_last_error() ) ) {
	    wp_send_json( array( 'success' => false, 'errMsg' => "Can't load product info" ) );
	}
	ASPMain::load_stripe_lib();

	\Stripe\Stripe::setApiKey( $this->ASPClass->APISecKeyTest );

	$site_url = get_site_url();

	$sData = array(
	    'payment_method_types'	 => array( 'card' ),
	    'success_url'		 => add_query_arg( 'asp_result', 'success', $site_url ),
	    'cancel_url'		 => add_query_arg( 'asp_result', 'cancel', $site_url ),
	);

	$sData[ 'line_items' ] = $item->gen_item_data();

	$session = \Stripe\Checkout\Session::create( $sData );

	wp_send_json( array( 'success' => true, 'checkoutSessionId' => $session->id ) );
    }

}

ASPPaymentHandlerNG::get_instance();
