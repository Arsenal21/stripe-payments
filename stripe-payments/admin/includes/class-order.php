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

	public function __construct() {
		self::$instance = $this;

		$this->AcceptStripePayments = AcceptStripePayments::get_instance();
	}

	public function register_post_type() {
		$labels = array(
			'name'               => _x( 'Orders', 'Post Type General Name', 'stripe-payments' ),
			'singular_name'      => _x( 'Order', 'Post Type Singular Name', 'stripe-payments' ),
			'parent_item_colon'  => __( 'Parent Order:', 'stripe-payments' ),
			'all_items'          => __( 'Orders', 'stripe-payments' ),
			'view_item'          => __( 'View Order', 'stripe-payments' ),
			'add_new_item'       => __( 'Add New Order', 'stripe-payments' ),
			'add_new'            => __( 'Add New', 'stripe-payments' ),
			'edit_item'          => __( 'Edit Order', 'stripe-payments' ),
			'update_item'        => __( 'Update Order', 'stripe-payments' ),
			'search_items'       => __( 'Search Order', 'stripe-payments' ),
			'not_found'          => __( 'Not found', 'stripe-payments' ),
			'not_found_in_trash' => __( 'Not found in Trash', 'stripe-payments' ),
		);
		$args   = array(
			'label'               => __( 'orders', 'stripe-payments' ),
			'description'         => __( 'Stripe Orders', 'stripe-payments' ),
			'labels'              => $labels,
			'supports'            => array( 'title', 'editor', 'excerpt', 'revisions', 'custom-fields' ),
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => 'edit.php?post_type=' . ASPMain::$products_slug,
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capability_type'     => 'post',
			'capabilities'        => array(
				'create_posts' => false, // Removes support for the "Add New" function
			),
			'map_meta_cap'        => true,
		);

		$args = apply_filters( 'asp_stripe_order_register_post_type_args', $args );

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
		if ( null === self::$instance ) {
			self::$instance = new self();
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
		$post               = array();
		$post['post_title'] = $order_details['item_quantity'] . ' x ' . $order_details['item_name'] . ' - ' . AcceptStripePayments::formatted_price( $order_details['paid_amount'], $order_details['currency_code'] );
		if ( $order_details['is_live'] == 0 ) {
			//Test Mode is on, we should add this to post title
			$post['post_title'] = '[Test Mode] ' . $post['post_title'];
		}

		$post['post_status'] = 'pending';

		$output = '';

		// Add error info in case of failure
		if ( ! empty( $charge_details->failure_code ) ) {

			$output .= '<h2>Payment Failure Details</h2>' . "\n";
			$output .= $charge_details->failure_code . ': ' . $charge_details->failure_message;
			$output .= "\n\n";
		} else {
			$post['post_status'] = 'publish';
		}

		$order_date = date( 'Y-m-d H:i:s', $charge_details->created );
		$order_date = get_date_from_gmt( $order_date, get_option( 'date_format' ) . ', ' . get_option( 'time_format' ) );

		$output .= '<h2>' . __( 'Order Details', 'stripe-payments' ) . "</h2>\n";
		$output .= __( 'Order Time', 'stripe-payments' ) . ': ' . $order_date . "\n";
		$output .= __( 'Transaction ID', 'stripe-payments' ) . ': ' . $charge_details->id . "\n";
		$output .= __( 'Stripe Token', 'stripe-payments' ) . ': ' . $order_details['stripeToken'] . "\n";
		$output .= __( 'Description', 'stripe-payments' ) . ': ' . $order_details['charge_description'] . "\n";
		$output .= '--------------------------------' . "\n";
		$output .= __( 'Product Name', 'stripe-payments' ) . ': ' . $order_details['item_name'] . "\n";
		$output .= __( 'Quantity', 'stripe-payments' ) . ': ' . $order_details['item_quantity'] . "\n";
		$output .= __( 'Item Price', 'stripe-payments' ) . ': ' . AcceptStripePayments::formatted_price( $order_details['item_price'], $order_details['currency_code'] ) . "\n";
		//check if there are any additional items available like tax and shipping cost
		$output .= AcceptStripePayments::gen_additional_items( $order_details );
		$output .= '--------------------------------' . "\n";
		$output .= __( 'Total Amount', 'stripe-payments' ) . ': ' . AcceptStripePayments::formatted_price( ( $order_details['paid_amount'] ), $order_details['currency_code'] ) . "\n";

		$output .= "\n\n";

		$output .= '<h2>' . __( 'Customer Details', 'stripe-payments' ) . "</h2>\n";
		$output .= sprintf( __( 'E-Mail Address: %s', 'stripe-payments' ), $order_details['stripeEmail'] ) . "\n";
		$output .= sprintf( __( 'Payment Source: %s', 'stripe-payments' ), $order_details['stripeTokenType'] ) . "\n";

		//Custom Fields (if set)
		if ( isset( $order_details['custom_fields'] ) ) {
			$custom_fields = '';
			foreach ( $order_details['custom_fields'] as $cf ) {
				$custom_fields .= $cf['name'] . ': ' . $cf['value'] . "\r\n";
			}
			$custom_fields = rtrim( $custom_fields, "\r\n" );
			$output       .= $custom_fields;
		}

		//Check if we have TOS enabled and need to store customer's IP and timestamp
		$tos_enabled = $this->AcceptStripePayments->get_setting( 'tos_enabled' );
		$tos_store   = $this->AcceptStripePayments->get_setting( 'tos_store_ip' );

		if ( $tos_enabled && $tos_store ) {
			$ip      = ! empty( $_SERVER['REMOTE_ADDR'] ) ? $_SERVER['REMOTE_ADDR'] : __( 'Unknown', 'stripe-payments' );
			$output .= sprintf( __( 'IP Address: %s', 'stripe-payments' ), $ip ) . "\n";
		}

		//Billing address data (if any)
		if ( isset( $order_details['billing_address'] ) && strlen( $order_details['billing_address'] ) > 5 ) {
			$output .= '<h2>' . __( 'Billing Address', 'stripe-payments' ) . "</h2>\n";
			$output .= $order_details['billing_address'];
		}

		//Shipping address data (if any)
		if ( isset( $order_details['shipping_address'] ) && strlen( $order_details['shipping_address'] ) > 5 ) {
			$output .= '<h2>' . __( 'Shipping Address', 'stripe-payments' ) . "</h2>\n";
			$output .= $order_details['shipping_address'];
		}

		$post['post_content'] = $output;
		$post['post_type']    = 'stripe_order';

		$post = apply_filters( 'asp_order_before_insert', $post, $order_details, $charge_details );

		$post_id = wp_insert_post( $post );

		//let's insert WP user ID into order details. Can be used to display user's transaction history.
		$user_id = get_current_user_id();
		if ( $user_id ) {
			update_post_meta( $post_id, 'asp_user_id', $user_id );
		}

		return $post_id;
	}

}
