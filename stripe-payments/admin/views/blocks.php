<?php

class AcceptStripePayments_Blocks {

    function __construct() {
	add_action( 'init', array( $this, 'register_block' ) );
    }

    function register_block() {
	if ( ! function_exists( 'register_block_type' ) ) {
	    // Gutenberg is not active.
	    return;
	}

	wp_register_script(
	'stripe-payments-product-block', WP_ASP_PLUGIN_URL . '/admin/assets/js/blocks/product-block.js', array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-editor' ), WP_ASP_PLUGIN_VERSION
	);

	wp_localize_script( 'stripe-payments-product-block', 'aspProdOpts', $this->get_products_array() );
	wp_localize_script( 'stripe-payments-product-block', 'aspBlockProdStr', array(
	    'title'			 => 'Stripe Payments Product',
	    'product'		 => __( 'Product', 'stripe-payments' ),
	    'button_only'		 => __( 'Show Button Only', 'stripe-payments' ),
	    'button_only_help'	 => __( 'Check this box if you just want to show the button only without any additional product info.', 'stripe-payments' ),
	) );

	register_block_type( 'stripe-payments/product-block', array(
	    'attributes'		 => array(
		'prodId'	 => array(
		    'type'		 => 'string',
		    'default'	 => 0,
		),
		'btnOnly'	 => array(
		    'type'		 => 'boolean',
		    'default'	 => false,
		),
	    ),
	    'editor_script'		 => 'stripe-payments-product-block',
	    'render_callback'	 => array( $this, 'render_product_block' ),
	) );
    }

    function render_product_block( $atts ) {

	$prodId = ! empty( $atts[ 'prodId' ] ) ? intval( $atts[ 'prodId' ] ) : 0;

	if ( empty( $prodId ) ) {
	    return '<p>' . __( 'Select product to view', 'stripe-payments' ) . '</p>';
	}

	$sc_str	 = 'asp_product id="%d"';
	$sc_str	 = sprintf( $sc_str, $prodId );

	if ( ! empty( $atts[ 'btnOnly' ] ) ) {
	    $sc_str .= ' button_only="1"';
	}

	return do_shortcode( '[' . $sc_str . ']' );
    }

    private function get_products_array() {
	$q	 = get_posts( array(
	    'post_type'	 => ASPMain::$products_slug,
	    'post_status'	 => 'publish',
	    'posts_per_page' => -1,
	    'orderby'	 => 'title',
	    'order'		 => 'ASC',
	) );
	$prodArr = array( array( 'label' => __( '(Select product)', 'stripe-payments' ), 'value' => 0 ) );
	foreach ( $q as $post ) {
	    $title		 = html_entity_decode( $post->post_title );
	    $prodArr[]	 = array( 'label' => esc_attr( $title ), 'value' => $post->ID );
	}
	wp_reset_postdata();
	return $prodArr;
    }

}

new AcceptStripePayments_Blocks();
