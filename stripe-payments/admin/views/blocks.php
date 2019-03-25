<?php

class AcceptStripePayments_Blocks {

    function __construct() {
	//Gutenberg blocks related
	add_action( 'init', array( $this, 'register_block' ) );
    }

    function register_block() {
	if ( ! function_exists( 'register_block_type' ) ) {
	    // Gutenberg is not active.
	    return;
	}

	wp_register_script(
	'stripe-payments-block', WP_ASP_PLUGIN_URL . '/admin/assets/js/blocks/blocks.js', array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-editor' ), WP_ASP_PLUGIN_VERSION
	);

	$this->get_products_array();

	wp_localize_script( 'stripe-payments-block', 'aspProdOpts', $this->get_products_array() );

	register_block_type( 'stripe-payments/block', array(
	    'attributes'		 => array(
		'prodId' => array(
		    'type'		 => 'integer',
		    'default'	 => 0,
		),
	    ),
	    'editor_script'		 => 'stripe-payments-block',
	    'render_callback'	 => array( $this, 'render_block' ),
	) );
    }

    function render_block( $atts ) {

	$prodId = ! empty( $atts[ 'prodId' ] ) ? intval( $atts[ 'prodId' ] ) : 0;

	if ( empty( $prodId ) ) {
	    return '<p>Select product to view</p>';
	}

	return do_shortcode( sprintf( '[asp_product id=%d]', $prodId ) );
    }

    private function get_products_array() {
	$query	 = new WP_Query( array(
	    'post_type'	 => ASPMain::$products_slug,
	    'post_status'	 => 'publish',
	    'posts_per_page' => -1,
	) );
	$prodArr = array( array( 'label' => '{Select product)', 'value' => 0 ) );
	while ( $query->have_posts() ) {
	    $query->the_post();
	    $prodArr[] = array( 'label' => get_the_title(), 'value' => get_the_ID() );
	}
	wp_reset_query();
	return $prodArr;
    }

}

new AcceptStripePayments_Blocks();
