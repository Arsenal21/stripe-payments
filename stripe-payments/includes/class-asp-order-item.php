<?php

class ASP_Order_Item {

	protected $id = false;

	private $asp_main;

	public function __construct() {
		$this->asp_main = AcceptStripePayments::get_instance();
	}

	public function create( $product_id, $pi_id = false ) {
		$item = new ASP_Product_Item( $product_id );

		$post               = array();
		$post['post_title'] = '[Incomplete]' . $item->get_name();
		if ( ! $this->asp_main->is_live ) {
			//Test Mode is on, we should add this to post title
			$post['post_title'] = '[Test Mode] ' . $post['post_title'];
		}

		$post['post_status'] = 'pending';
		$post['post_type']   = 'stripe_order';

		$this->id = wp_insert_post( $post );

		if ( false !== $pi_id ) {
			update_post_meta( $this->id, 'pi_id', $pi_id );
		}

		update_post_meta( $this->id, 'asp_product_id', $product_id );

		$this->change_status( 'incomplete' );

		//let's insert WP user ID into order details. Can be used to display user's transaction history.
		$user_id = get_current_user_id();
		if ( $user_id ) {
			update_post_meta( $this->id, 'asp_user_id', $user_id );
		}

		return $this->id;
	}

	public function find( $meta, $value ) {
		$order = get_posts(
			array(
				'post_type'   => 'stripe_order',
				'meta_key'    => $meta,
				'meta_value'  => $value,
				'post_status' => 'any',
			)
		);

		if ( $order ) {
			$this->id = $order[0]->ID;
			return $this->id;
		}

		return false;
	}

	public function set_id( $id ) {
		$this->id = $id;
	}

	public function change_status( $new_status, $comment = false, $date = false ) {
		update_post_meta( $this->id, 'asp_order_status', $new_status );

		$order_events = get_post_meta( $this->id, 'asp_order_events', true );
		if ( empty( $order_events ) ) {
			$order_events = array();
		}

		$order_events[] = array(
			'status'  => $new_status,
			'comment' => $comment,
			'date'    => false === $date ? time() : $date,
		);

		update_post_meta( $this->id, 'asp_order_events', $order_events );
	}

	public function get_id() {
		return $this->id;
	}

	public function get_status() {
		return get_post_meta( $this->id, 'asp_order_status', true );
	}

	public function can_create( $prod_id = false ) {
		$dont_create_order = $this->asp_main->get_setting( 'dont_create_order' );
		return ! $dont_create_order;
	}

	public function update_legacy( $order_details, $charge_details ) {
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
		// translators: %s is email address
		$output .= sprintf( __( 'E-Mail Address: %s', 'stripe-payments' ), $order_details['stripeEmail'] ) . "\n";

		if (isset($order_details['customer_name']) && !empty($order_details['customer_name'])){
			$output .= sprintf( __( 'Customer\'s Name: %s', 'stripe-payments' ), $order_details['customer_name'] ) . "\n";
		}

		// translators: %s is payment source (e.g. 'card' etc)
		$output .= sprintf( __( 'Payment Source: %s', 'stripe-payments' ), $order_details['stripeTokenType'] ) . "\n";

		if (isset($order_details['logged_in_user_id']) && !empty($order_details['logged_in_user_id'])) {
			// translators: %s is ID
			$output .= __( 'Logged-in User\'s Type: ', 'stripe-payments' ) . $order_details['logged_in_user_type'] . "\n";
			$output .= __( 'Logged-in User\'s User ID: ', 'stripe-payments' ) . $order_details['logged_in_user_id'] . "\n";
		}
		if (isset($order_details['logged_in_user_name']) && !empty($order_details['logged_in_user_name'])) {
			// translators: %s is username
			$output .= __( 'Logged-in User\'s Username: ', 'stripe-payments' ) . $order_details['logged_in_user_name'] . "\n";
		}

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
		$tos_enabled = $this->asp_main->get_setting( 'tos_enabled' );
		$tos_store   = $this->asp_main->get_setting( 'tos_store_ip' );

		if ( $tos_enabled && $tos_store ) {
			$ip = ! empty( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( $_SERVER['REMOTE_ADDR'] ) : __( 'Unknown', 'stripe-payments' );
			// translators: %s is IP address
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

		$post = apply_filters( 'asp_order_before_insert', $post, $order_details, $charge_details );

		$c_post = get_post( $this->id );

		$c_post->post_title   = $post['post_title'];
		$c_post->post_status  = $post['post_status'];
		$c_post->post_content = $post['post_content'];

		wp_update_post( $c_post, true );

		//let's insert WP user ID into order details. Can be used to display user's transaction history.
		$user_id = get_current_user_id();
		if ( $user_id ) {
			update_post_meta( $this->id, 'asp_user_id', $user_id );
		}

		return $this->id;
	}

}
