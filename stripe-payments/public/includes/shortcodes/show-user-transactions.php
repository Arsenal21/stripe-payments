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
	$curr_page = filter_input( INPUT_GET, 'asp_page', FILTER_SANITIZE_NUMBER_INT );
	if ( ! $curr_page ) {
	    $curr_page = 1;
	}
	$args		 = array(
	    'posts_per_page' => $atts[ 'items_per_page' ],
	    'offset'	 => ($curr_page - 1) * $atts[ 'items_per_page' ],
	    'post_type'	 => 'stripe_order',
	    'meta_key'	 => 'asp_user_id',
	    'meta_value'	 => $this->user_id,
	);
	$transactions	 = get_posts( $args );
	$res		 = new WP_Query( $args );
	$transactions	 = $res->posts;
	if ( ! $transactions ) {
	    //no transactions found
	    $out .= __( 'No transactions found.', 'stripe-payments' );
	    return $out;
	}

	$atts[ 'curr_page' ]	 = $curr_page;
	$atts[ 'total_pages' ]	 = ceil( $res->found_posts / $atts[ 'items_per_page' ] );

	require_once(WP_ASP_PLUGIN_PATH . 'public/views/templates/default/tpl-show-user-transactions.php');
	$tpl = new AcceptStripePayments_tplUserTransactions( $atts );

	$items = array();
	foreach ( $transactions as $trans ) {
	    $order_details = get_post_meta( $trans->ID, 'order_details', true );
	    if ( $order_details ) {
		$product_id	 = $order_details[ 'product_id' ];
		$amount		 = AcceptStripePayments::formatted_price( $order_details[ 'paid_amount' ], $order_details[ 'currency_code' ] );
		$product_name	 = $order_details[ 'item_name' ];
	    } else {
		$product_id	 = "-";
		$amount		 = "-";
		$product_name	 = $trans->post_title;
	    }

	    $item	 = array(
		'product_name'	 => $product_name,
		'product_id'	 => $product_id,
		'date'		 => $trans->post_date,
		'amount'	 => $amount
	    );
	    $items[] = $item;
	}
	$out .= $tpl->build_tpl( $items );
	return $out;
    }

}
