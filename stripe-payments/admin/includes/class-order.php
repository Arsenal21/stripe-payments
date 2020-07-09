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
	private $order_status_tpl  = '<span class="asp-order-status%s">%s</span>';


	public function __construct() {
		self::$instance = $this;

		$this->AcceptStripePayments = AcceptStripePayments::get_instance();

		if ( is_admin() ) {
			//products meta boxes handler
			require_once WP_ASP_PLUGIN_PATH . 'admin/includes/class-asp-admin-order-meta-boxes.php';

			$show_incomplete = $this->AcceptStripePayments->get_setting( 'show_incomplete_orders' );

			if ( ! $show_incomplete ) {
				add_filter( 'pre_get_posts', array( $this, 'filter_posts' ) );
				add_filter( 'views_edit-stripe_order', array( $this, 'remove_views' ) );
			}
		}
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

		add_filter( 'manage_stripe_order_posts_columns', array( $this, 'manage_columns' ) );
		add_action( 'manage_stripe_order_posts_custom_column', array( $this, 'manage_custom_columns' ), 10, 2 );
		//set custom columns sortable
		//      add_filter( 'manage_edit-stripe_order_sortable_columns', array( $this, 'manage_sortable_columns' ) );

		if ( wp_doing_ajax() ) {
			add_action( 'wp_ajax_asp_order_capture_confirm', array( $this, 'order_capture_confirm' ) );
			add_action( 'wp_ajax_asp_order_capture_cancel', array( $this, 'order_capture_cancel' ) );
		}

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

	public function filter_posts( $wp_query ) {
		global $pagenow;

		if ( 'edit.php' !== $pagenow ) {
			return;
		}

		if ( ! is_admin() ) {
			return;
		}

		if ( $wp_query->is_main_query() && 'stripe_order' === $wp_query->query['post_type'] ) {
			if ( empty( $wp_query->query_vars['post_status'] ) ) {
				$wp_query->query_vars['post_status'] = array(
					'publish',
					'private',
					'trash',
				);
			}
		}
	}

	public function remove_views( $views ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return $views;
		}

		$remove_views = array( 'mine', 'pending', 'draft' );

		foreach ( (array) $remove_views as $view ) {
			if ( isset( $views[ $view ] ) ) {
				unset( $views[ $view ] );
			}
		}
		return $views;
	}

	public function manage_columns( $columns ) {
		$custom_columns = array(
			'total'  => __( 'Total', 'stripe-payments' ),
			'status' => __( 'Status', 'stripe-payments' ),
		);
		$columns        = array_merge( $columns, $custom_columns );
		return $columns;
	}

	public function manage_custom_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'status':
				$data   = get_post_meta( $post_id, 'order_data', true );
				$status = get_post_meta( $post_id, 'asp_order_status', true );

				if ( empty( $status ) ) {

					if ( ! isset( $data['charge']->paid ) ||
					( isset( $data['charge']->paid ) && $data['charge']->paid &&
					$data['charge']->captured ) ) {
						echo sprintf( $this->order_status_tpl, ' paid', __( 'Paid', 'stripe-payments' ) );
					} else {
						if ( isset( $data['charge']->captured ) && false === $data['charge']->captured &&
						empty( $data['charge']->amount_refunded ) ) {
							echo sprintf( $this->order_status_tpl, ' authorized', __( 'Authorized', 'stripe-payments' ) );
							$action_nonce = wp_create_nonce( 'asp-order-actions-' . $post_id );
							echo '<div class="asp-order-actions-cont">';
							echo sprintf( '<a class="asp-order-action" data-action="confirm" href="#" data-order-id="%d" data-nonce="%s">' . __( 'Capture', 'stripe-payments' ) . '</a> | ', $post_id, $action_nonce );
							echo sprintf( '<a class= "asp-order-action" data-action="cancel" style="color:#a00;" href="#" data-order-id="%d" data-nonce="%s">' . __( 'Cancel', 'stripe-payments' ) . '</a>', $post_id, $action_nonce );
							echo '</div>';
						} else {
							echo sprintf( $this->order_status_tpl, ' canceled', __( 'Canceled', 'stripe-payments' ) );
						}
					}
				} else {
					//we have status set
					switch ( $status ) {
						case 'authorized':
							echo sprintf( $this->order_status_tpl, ' ' . $status, self::get_status_str( $status ) );
							$action_nonce = wp_create_nonce( 'asp-order-actions-' . $post_id );
							echo '<div class="asp-order-actions-cont">';
							echo sprintf( '<a class="asp-order-action" data-action="confirm" href="#" data-order-id="%d" data-nonce="%s">' . __( 'Capture', 'stripe-payments' ) . '</a> | ', $post_id, $action_nonce );
							echo sprintf( '<a class= "asp-order-action" data-action="cancel" style="color:#a00;" href="#" data-order-id="%d" data-nonce="%s">' . __( 'Cancel', 'stripe-payments' ) . '</a>', $post_id, $action_nonce );
							echo '</div>';
							break;
						case 'error':
							$order_events = get_post_meta( $post_id, 'asp_order_events', false );
							if ( ! empty( $order_events ) && is_array( $order_events ) ) {
								echo sprintf( '<span class="asp-order-status%s" data-balloon-length="medium" data-balloon-pos="up" aria-label="%s">%s</span>', ' ' . $status, end( $order_events[0] )['comment'], self::get_status_str( $status ) );
							} else {
								echo sprintf( $this->order_status_tpl, ' ' . $status, self::get_status_str( $status ) );
							}
							break;
						default:
							echo sprintf( $this->order_status_tpl, ' ' . $status, self::get_status_str( $status ) );
							break;
					}
				}
				break;
			case 'total':
				$data = get_post_meta( $post_id, 'order_data', true );
				if ( $data ) {
					echo ASP_Utils::formatted_price( $data['paid_amount'], $data['currency_code'] );
				} else {
					echo '—';
				}
				break;
		}
	}

	public function order_capture_confirm() {
		$post_id = filter_input( INPUT_POST, 'order_id', FILTER_SANITIZE_NUMBER_INT );
		if ( empty( $post_id ) ) {
			wp_send_json(
				array(
					'success' => false,
					'err_msg' => __( 'Invalid order ID', 'stripe-payments' ),
				)
			);
		}

		$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_STRING );
		if ( empty( $nonce ) || false === wp_verify_nonce( $nonce, 'asp-order-actions-' . $post_id ) ) {
			wp_send_json(
				array(
					'success' => false,
					'err_msg' => __( 'Nonce check failed', 'stripe-payments' ),
				)
			);
		}

		$order = new ASP_Order_Item();
		$order->set_id( $post_id );

		$data = get_post_meta( $post_id, 'order_data', true );

		$asp_main = AcceptStripePayments::get_instance();

		try {
			ASP_Utils::load_stripe_lib();
			$key = $data['is_live'] ? $asp_main->APISecKey : $asp_main->APISecKeyTest;
			\Stripe\Stripe::setApiKey( $key );

			$api = ASP_Stripe_API::get_instance();
			$api->set_api_key( $key );

			if ( ASP_Utils::use_internal_api() ) {
				$intent = $api->get( 'payment_intents/' . $data['charge']->payment_intent );
			} else {
				$intent = \Stripe\PaymentIntent::retrieve( $data['charge']->payment_intent );
			}

			if ( 'requires_capture' !== $intent->status ) {
				//already captured or canceled
				$data['charge'] = $intent->charges->data[0];
				update_post_meta( $post_id, 'order_data', $data );

				if ( 'canceled' === $intent->status ) {
					$order->change_status( 'canceled' );
				}

				if ( 'succeeded' === $intent->status ) {
					$order->change_status( 'paid' );
				}

				wp_send_json(
					array(
						'success'      => false,
						'err_msg'      => __( 'Funds already captured or refunded', 'stripe-payments' ),
						'order_status' => sprintf( $this->order_status_tpl, ' ' . $order->get_status(), self::get_status_str( $order->get_status() ) ),
					)
				);
			}

			if ( ASP_Utils::use_internal_api() ) {
				$intent = $api->post( 'payment_intents/' . $data['charge']->payment_intent . '/capture' );
			} else {
				$intent->capture();
			}
		} catch ( \Exception $e ) {
			wp_send_json(
				array(
					'success' => false,
					'err_msg' => $e->getMessage(),
				)
			);
		}

		$data['charge'] = $intent->charges->data[0];
		update_post_meta( $post_id, 'order_data', $data );

		$order->change_status( 'paid' );

		wp_send_json(
			array(
				'success'      => true,
				'order_status' => sprintf( $this->order_status_tpl, ' ' . $order->get_status(), self::get_status_str( $order->get_status() ) ),
			)
		);
	}

	public function order_capture_cancel() {
		$post_id = filter_input( INPUT_POST, 'order_id', FILTER_SANITIZE_NUMBER_INT );
		if ( empty( $post_id ) ) {
			wp_send_json(
				array(
					'success' => false,
					'err_msg' => __( 'Invalid order ID', 'stripe-payments' ),
				)
			);
		}

		$nonce = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_STRING );
		if ( empty( $nonce ) || false === wp_verify_nonce( $nonce, 'asp-order-actions-' . $post_id ) ) {
			wp_send_json(
				array(
					'success' => false,
					'err_msg' => __( 'Nonce check failed', 'stripe-payments' ),
				)
			);
		}

		$order = new ASP_Order_Item();
		$order->set_id( $post_id );

		$data = get_post_meta( $post_id, 'order_data', true );

		$asp_main = AcceptStripePayments::get_instance();

		try {
			ASP_Utils::load_stripe_lib();
			$key = $data['is_live'] ? $asp_main->APISecKey : $asp_main->APISecKeyTest;
			\Stripe\Stripe::setApiKey( $key );

			$api = ASP_Stripe_API::get_instance();
			$api->set_api_key( $key );

			if ( ASP_Utils::use_internal_api() ) {
				$intent = $api->get( 'payment_intents/' . $data['charge']->payment_intent );
			} else {
				$intent = \Stripe\PaymentIntent::retrieve( $data['charge']->payment_intent );
			}

			if ( 'requires_capture' !== $intent->status ) {
				//already captured or canceled
				$data['charge'] = $intent->charges->data[0];
				update_post_meta( $post_id, 'order_data', $data );

				if ( 'canceled' === $intent->status ) {
					$order->change_status( 'canceled' );
				}

				if ( 'succeeded' === $intent->status ) {
					$order->change_status( 'paid' );
				}

				wp_send_json(
					array(
						'success'      => false,
						'err_msg'      => __( 'Funds already captured or refunded', 'stripe-payments' ),
						'order_status' => sprintf( $this->order_status_tpl, ' ' . $order->get_status(), self::get_status_str( $order->get_status() ) ),
					)
				);
			}

			if ( ASP_Utils::use_internal_api() ) {
				$intent = $api->post( 'payment_intents/' . $data['charge']->payment_intent . '/cancel' );
			} else {
				$intent->cancel();
			}
		} catch ( \Exception $e ) {
			wp_send_json(
				array(
					'success' => false,
					'err_msg' => $e->getMessage(),
				)
			);
		}

		$data['charge'] = $intent->charges->data[0];
		update_post_meta( $post_id, 'order_data', $data );

		$order->change_status( 'canceled' );

		wp_send_json(
			array(
				'success'      => true,
				'order_status' => sprintf( $this->order_status_tpl, ' ' . $order->get_status(), self::get_status_str( $order->get_status() ) ),
			)
		);
	}

	public static function get_status_str( $status ) {
		$status_str = array(
			'incomplete' => __( 'Incomplete', 'stripe-payments' ),
			'paid'       => __( 'Paid', 'stripe-payments' ),
			'authorized' => __( 'Authorized', 'stripe-payments' ),
			'canceled'   => __( 'Canceled', 'stripe-payments' ),
			'error'      => __( 'Error', 'stripe-payments' ),
		);
		if ( isset( $status_str[ $status ] ) ) {
			return $status_str[ $status ];
		}
		return false;
	}

}
