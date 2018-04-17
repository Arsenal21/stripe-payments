<?php

class ASPOrder {

    /**
     * Instance of this class.
     *
     * @since    1.0.0
     *
     * @var      object
     */
    protected static $instance = null;

    function __construct() {
	$this->AcceptStripePayments	 = AcceptStripePayments::get_instance();
	$this->text_domain		 = 'stripe-payments';
    }

    public function register_post_type() {
	$labels	 = array(
	    'name'			 => _x( 'Orders', 'Post Type General Name', 'stripe-payments' ),
	    'singular_name'		 => _x( 'Order', 'Post Type Singular Name', 'stripe-payments' ),
	    'parent_item_colon'	 => __( 'Parent Order:', 'stripe-payments' ),
	    'all_items'		 => __( 'Orders', 'stripe-payments' ),
	    'view_item'		 => __( 'View Order', 'stripe-payments' ),
	    'add_new_item'		 => __( 'Add New Order', 'stripe-payments' ),
	    'add_new'		 => __( 'Add New', 'stripe-payments' ),
	    'edit_item'		 => __( 'Edit Order', 'stripe-payments' ),
	    'update_item'		 => __( 'Update Order', 'stripe-payments' ),
	    'search_items'		 => __( 'Search Order', 'stripe-payments' ),
	    'not_found'		 => __( 'Not found', 'stripe-payments' ),
	    'not_found_in_trash'	 => __( 'Not found in Trash', 'stripe-payments' ),
	);
	$args	 = array(
	    'label'			 => __( 'orders', 'stripe-payments' ),
	    'description'		 => __( 'Stripe Orders', 'stripe-payments' ),
	    'labels'		 => $labels,
	    'supports'		 => array( 'title', 'editor', 'excerpt', 'revisions', 'custom-fields', ),
	    'hierarchical'		 => false,
	    'public'		 => false,
	    'show_ui'		 => true,
	    'show_in_menu'		 => 'edit.php?post_type=' . ASPMain::$products_slug,
	    'can_export'		 => true,
	    'has_archive'		 => false,
	    'exclude_from_search'	 => true,
	    'publicly_queryable'	 => false,
	    'capability_type'	 => 'post',
	    'capabilities'		 => array(
		'create_posts' => false, // Removes support for the "Add New" function
	    ),
	    'map_meta_cap'		 => true,
	);

	register_post_type( 'stripe_order', $args );
    }

    /**
     * Return an instance of this class.
     *
     * @since     1.0.0
     *
     * @return    object    A single instance of this class.
     */
    public static function get_instance() {

	// If the single instance hasn't been set, set it now.
	if ( null == self::$instance ) {
	    self::$instance = new self;
	}

	return self::$instance;
    }

    /**
     * Receive Response of GetExpressCheckout and ConfirmPayment function returned data.
     * Returns the order ID.
     *
     * @since     1.0.0
     *
     * @return    Numeric    Post or Order ID.
     */
    public function insert( $order_details, $charge_details ) {
	$post			 = array();
	$post[ 'post_title' ]	 = $order_details[ 'item_quantity' ] . ' x ' . $order_details[ 'item_name' ] . ' - ' . AcceptStripePayments::formatted_price( $order_details[ 'paid_amount' ], $order_details[ 'currency_code' ] );
	if ( $order_details[ 'is_live' ] == 0 ) {
	    //Test Mode is on, we should add this to post title
	    $post[ 'post_title' ] = '[Test Mode] ' . $post[ 'post_title' ];
	}

	$post[ 'post_status' ] = 'pending';

	$output = '';

	// Add error info in case of failure
	if ( ! empty( $charge_details->failure_code ) ) {

	    $output	 .= "<h2>Payment Failure Details</h2>" . "\n";
	    $output	 .= $charge_details->failure_code . ": " . $charge_details->failure_message;
	    $output	 .= "\n\n";
	} else {
	    $post[ 'post_status' ] = 'publish';
	}

	$output	 .= "<h2>" . __( "Order Details", "stripe-payments" ) . "</h2>\n";
	$output	 .= __( "Order Time: ", "stripe-payments" ) . date( "F j, Y, g:i a", strtotime( 'now' ) ) . "\n";
	$output	 .= __( "Transaction ID: ", "stripe-payments" ) . $charge_details->id . "\n";
	$output	 .= __( "Stripe Token: ", "stripe-payments" ) . $order_details[ 'stripeToken' ] . "\n";
	$output	 .= __( "Description: ", "stripe-payments" ) . $order_details[ 'charge_description' ] . "\n";
	$output	 .= "--------------------------------" . "\n";
	$output	 .= __( "Product Name: ", "stripe-payments" ) . $order_details[ 'item_name' ] . "\n";
	$output	 .= __( "Quantity: ", "stripe-payments" ) . $order_details[ 'item_quantity' ] . "\n";
	$output	 .= __( "Item Price: ", "stripe-payments" ) . AcceptStripePayments::formatted_price( $order_details[ 'item_price' ], $order_details[ 'currency_code' ] ) . "\n";
	//check if there are any additional items available like tax and shipping cost
	if ( ! empty( $order_details[ 'additional_items' ] ) ) {
	    foreach ( $order_details[ 'additional_items' ] as $item => $price ) {
		$output .= $item . ": " . AcceptStripePayments::formatted_price( $price, $order_details[ 'currency_code' ] ) . "\n";
	    }
	}
	$output	 .= "--------------------------------" . "\n";
	$output	 .= __( "Total Amount: ", "stripe-payments" ) . AcceptStripePayments::formatted_price( ($order_details[ 'paid_amount' ] ), $order_details[ 'currency_code' ] ) . "\n";


	$output .= "\n\n";

	$output	 .= "<h2>" . __( "Customer Details", "stripe-payments" ) . "</h2>\n";
	$output	 .= sprintf( __( "E-Mail Address: %s", "stripe-payments" ), $order_details[ 'stripeEmail' ] ) . "\n";
	$output	 .= sprintf( __( "Payment Source: %s", "stripe-payments" ), $order_details[ 'stripeTokenType' ] ) . "\n";

	//Billing address data (if any)
	if ( strlen( $order_details[ 'billing_address' ] ) > 5 ) {
	    $output	 .= "<h2>" . __( "Billing Address", "stripe-payments" ) . "</h2>\n";
	    $output	 .= $order_details[ 'billing_address' ];
	}

	//Shipping address data (if any)
	if ( strlen( $order_details[ 'shipping_address' ] ) > 5 ) {
	    $output	 .= "<h2>" . __( "Shipping Address", "stripe-payments" ) . "</h2>\n";
	    $output	 .= $order_details[ 'shipping_address' ];
	}

	//Custom Field (if set)
	if ( isset( $order_details[ 'custom_field_value' ] ) ) {
	    $output .= $order_details[ 'custom_field_name' ] . ': ' . $order_details[ 'custom_field_value' ];
	}

	$post[ 'post_content' ]	 = $output;
	$post[ 'post_type' ]	 = 'stripe_order';

	$post = apply_filters( 'asp_order_before_insert', $post, $order_details, $charge_details );

	$post_id = wp_insert_post( $post );

	//let's save order and charge details in post meta data
	update_post_meta( $post_id, 'order_details', $order_details );
	update_post_meta( $post_id, 'charge_details', $charge_details );

	return $post_id;
    }

}
