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

	function __construct()
	{
		$this->AcceptStripePayments = AcceptStripePayments::get_instance();
		$this->text_domain = 'stripe-payments';
	}

	public function register_post_type()
	{
		$labels = array(
			'name'                => _x( 'Orders', 'Post Type General Name', 'stripe-payments' ),
			'singular_name'       => _x( 'Order', 'Post Type Singular Name', 'stripe-payments' ),
			'menu_name'           => __( 'Stripe Orders', 'stripe-payments' ),
			'parent_item_colon'   => __( 'Parent Order:', 'stripe-payments' ),
			'all_items'           => __( 'All Orders', 'stripe-payments' ),
			'view_item'           => __( 'View Order', 'stripe-payments' ),
			'add_new_item'        => __( 'Add New Order', 'stripe-payments' ),
			'add_new'             => __( 'Add New', 'stripe-payments' ),
			'edit_item'           => __( 'Edit Order', 'stripe-payments' ),
			'update_item'         => __( 'Update Order', 'stripe-payments' ),
			'search_items'        => __( 'Search Order', 'stripe-payments' ),
			'not_found'           => __( 'Not found', 'stripe-payments' ),
			'not_found_in_trash'  => __( 'Not found in Trash', 'stripe-payments' ),
		);
                
                $menu_icon = WP_ASP_PLUGIN_URL . '/assets/asp-dashboard-menu-icon.png';                
		$args = array(
			'label'               => __( 'orders', 'stripe-payments' ),
			'description'         => __( 'Stripe Orders', 'stripe-payments' ),
			'labels'              => $labels,
			'supports'            => array( 'title', 'editor', 'excerpt', 'revisions', 'custom-fields', ),
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => true,
			'show_in_menu'        => true,
			'show_in_nav_menus'   => true,
			'show_in_admin_bar'   => true,
			'menu_position'       => 80,
			'menu_icon'           => $menu_icon,
			'can_export'          => true,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capability_type'     => 'post',
			'capabilities' => array(
   				'create_posts' => false, // Removes support for the "Add New" function
  			),
  			'map_meta_cap' => true,
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
	public function insert($order_details, $charge_details)
	{
		$post = array();
		$post['post_title'] = $order_details['item_quantity'].' '.$order_details['item_name'].' - '.$order_details['item_price'].' '.$order_details['currency_code'];
		$post['post_status'] = 'pending';

		$output = '';

		// Add error info in case of failure
		if( !empty($charge_details->failure_code) ) {

			$output .= "<h2>Payment Failure Details</h2>"."\n";
			$output .= $charge_details->failure_code.": ".$charge_details->failure_message;
			$output .= "\n\n";
		}
		else {
			$post['post_status'] = 'publish';
		}

		$output .= __("<h2>Order Details</h2>", "stripe-payments")."\n";
		$output .= __("Order Time: ", "stripe-payments").date("F j, Y, g:i a",strtotime('now'))."\n";
		$output .= __("Transaction ID: ", "stripe-payments").$charge_details->id."\n";
		$output .= __("Stripe Token: ", "stripe-payments").$order_details['stripeToken']."\n";
                $output .= __("Description: ", "stripe-payments").$order_details['charge_description']."\n";
		$output .= "--------------------------------"."\n";
		$output .= __("Product Name: ", "stripe-payments").$order_details['item_name']."\n";
		$output .= __("Quantity: ", "stripe-payments"). $order_details['item_quantity']."\n";
		$output .= __("Amount: ", "stripe-payments"). $order_details['item_price'].' '.$order_details['currency_code']."\n";
		$output .= "--------------------------------"."\n";
		$output .= __("Total Amount: ", "stripe-payments"). ($order_details['item_price']*$order_details['item_quantity']).' '.$order_details['currency_code']."\n";

		
		$output .= "\n\n";

		$output .= __("<h2>Customer Details</h2>", "stripe-payments")."\n";
		$output .= __("E-Mail Address: ", "stripe-payments").$order_details['stripeEmail']."\n";
                
                //Billing address data (if any)
                if(strlen($order_details['billing_address']) > 5){
                    $output .= __("<h2>Billing Address</h2>", "stripe-payments")."\n";
                    $output .= $order_details['billing_address'];
                }

                //Shipping address data (if any)
                if(strlen($order_details['shipping_address']) > 5){
                    $output .= __("<h2>Shipping Address</h2>", "stripe-payments")."\n";
                    $output .= $order_details['shipping_address'];
                }
                
		$post['post_content'] = $output;
		$post['post_type'] = 'stripe_order';

		return wp_insert_post( $post );//Return post ID
	}

}

