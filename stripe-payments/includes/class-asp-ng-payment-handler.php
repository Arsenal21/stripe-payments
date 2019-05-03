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

    public function process_payment_success() {
	$sess = ASP_Session::get_instance();

	ASPMain::load_stripe_lib();

	\Stripe\Stripe::setApiKey( $this->ASPClass->APISecKeyTest );

	$events = \Stripe\Event::all( [
	    'type'		 => 'checkout.session.completed',
	    'created'	 => [
		'gte' => time() - 24 * 60,
	    ],
	] );

	$trans_data = $sess->get_transient_data( 'trans_info' );

	if ( empty( $trans_data ) ) {
	    $trans_id = $_GET[ 'asp_trans_id' ];
	} else {
	    $trans_id	 = $trans_data[ 'trans_id' ];
	    $prod_id	 = $trans_data[ 'prod_id' ];
	}


	$pi_id = false;

	foreach ( $events->autoPagingIterator() as $event ) {
	    $session = $event->data->object;
	    if ( isset( $session->client_reference_id ) && $session->client_reference_id === $trans_id ) {
		$pi_id = $session->payment_intent;
		break;
	    }
	}
	if ( $pi_id !== false ) {
	    $item			 = new ASPItem( $prod_id );
	    $pi			 = \Stripe\PaymentIntent::retrieve( $pi_id );
	    $data			 = array();
	    $data[ 'paid_amount' ]	 = $pi->amount_received;
	    $data[ 'currency_code' ] = $pi->currency;
	    $data[ 'item_quantity' ]	 = $item->get_quantity();
	    $data[ 'charge' ]	 = $pi->charges[ 0 ];
	    $data[ 'item_name' ]	 = $item->get_name();
	    $data[ 'item_price' ]	 = $item->get_price();
	    $sess->set_transient_data( 'asp_data', $data );

	    $redir_url = $this->ASPClass->get_setting( 'checkout_url' );

	    wp_redirect( $redir_url );
	    exit;
	}
	wp_die( 'No info found yet. Refresh page.' );
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

	$site_url = get_home_url( null, '/' );

	$sess		 = ASP_Session::get_instance();
	$trans_id	 = md5( uniqid( 'asp_trans_id', true ) );

	$sess->set_transient_data( 'trans_info', array( 'prod_id' => $prod_id, 'trans_id' => $trans_id ) );

	$sData = array(
	    'payment_method_types'	 => array( 'card' ),
	    'success_url'		 => add_query_arg( array( 'asp_result' => 'success', 'asp_trans_id' => $trans_id ), $site_url ),
	    'cancel_url'		 => add_query_arg( array( 'asp_result' => 'cancel' ), $site_url ),
	    'client_reference_id'	 => $trans_id,
	);

	$sData[ 'line_items' ] = $item->gen_item_data();

	$session = \Stripe\Checkout\Session::create( $sData );

	wp_send_json( array( 'success' => true, 'checkoutSessionId' => $session->id ) );
    }

}

ASPPaymentHandlerNG::get_instance();
