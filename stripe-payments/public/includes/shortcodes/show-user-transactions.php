<?php

class AcceptStripePayments_scUserTransactions {

    private $user_id = 0;

    public function __construct() {
	$this->user_id = get_current_user_id();
    }

    public function process_shortcode( $atts ) {
	$out = '';
	if ( ! $this->user_id ) {
	    //user not logged in
	    $redirect	 = get_permalink();
	    $login_url	 = wp_login_url( $redirect );
	    $out		 .= sprintf( __( 'Please <a href="%s">login</a> to see your transactions history.', 'stripe-payments' ), $login_url );
	    return $out;
	}
	//let's find all user transactions
	$args		 = array(
	    'posts_per_page' => 20,
	    'offset'	 => 0,
	    'post_type'	 => 'stripe_order',
	    'meta_key'	 => 'asp_user_id',
	    'meta_value'	 => $this->user_id,
	);
	$transactions	 = get_posts( $args );
	if ( ! $transactions ) {
	    //no transactions found
	    $out .= __( 'No transactions found.', 'stripe-payments' );
	    return $out;
	}
	foreach ( $transactions as $trans ) {
	    $out .= sprintf( '<p>%s</p>',$trans->post_title );
	}
	return $out;
    }

}
