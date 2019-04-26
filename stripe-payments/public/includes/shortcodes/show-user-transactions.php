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
	$res		 = new WP_Query( $args );
	$transactions	 = $res->posts;
	wp_reset_postdata();
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
	    $additional_data = array();
	    $order_data	 = get_post_meta( $trans->ID, 'order_data', true );
	    if ( $order_data ) {
		$product_id = $order_data[ 'product_id' ];

		$additional_data = apply_filters( 'asp_sc_show_user_transactions_additional_data', $additional_data, $order_data, $atts );

		$amount		 = AcceptStripePayments::formatted_price( $order_data[ 'paid_amount' ], $order_data[ 'currency_code' ] );
		$product_name	 = $order_data[ 'item_name' ];
		if ( $atts[ 'show_download_link' ] && ! empty( $order_data[ 'item_url' ] ) ) {
		    $additional_data[] = array( __( "Download link:", 'stripe-payments' ) => sprintf( '<a href="%s" target="_blank">' . __( 'Click here to download', 'stripe-payments' ) . '</a>', $order_data[ 'item_url' ] ) );
		}
	    } else {
		$product_id	 = "-";
		$amount		 = "-";
		$product_name	 = $trans->post_title;
	    }

	    $item	 = array(
		'product_name'		 => $product_name,
		'product_id'		 => $product_id,
		'date'			 => $trans->post_date,
		'amount'		 => $amount,
		'additional_data'	 => $additional_data,
	    );
	    $items[] = $item;
	}
	$out .= $tpl->build_tpl( $items );
	return $out;
    }

}
