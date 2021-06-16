<?php

class ASP_PP_Ajax {

	protected $asp_main;

	public function __construct() {
		if ( wp_doing_ajax() ) {
			//          ASP_Utils::set_custom_lang_if_needed();
			$this->asp_main = AcceptStripePayments::get_instance();
			add_action( 'init', array( $this, 'add_ajax_handlers' ), 2147483647 );
		}
	}

	public function add_ajax_handlers() {
		add_action( 'wp_ajax_asp_pp_create_pi', array( $this, 'handle_create_pi' ) );
		add_action( 'wp_ajax_nopriv_asp_pp_create_pi', array( $this, 'handle_create_pi' ) );

		add_action( 'wp_ajax_asp_pp_confirm_pi', array( $this, 'handle_confirm_pi' ) );
		add_action( 'wp_ajax_nopriv_asp_pp_confirm_pi', array( $this, 'handle_confirm_pi' ) );

		add_action( 'wp_ajax_asp_3ds_result', array( $this, 'handle_3ds_result' ) );
		add_action( 'wp_ajax_nopriv_asp_3ds_result', array( $this, 'handle_3ds_result' ) );

		add_action( 'wp_ajax_asp_pp_save_form_data', array( $this, 'save_form_data' ) );
		add_action( 'wp_ajax_nopriv_asp_pp_save_form_data', array( $this, 'save_form_data' ) );

		add_action( 'wp_ajax_asp_pp_payment_error', array( $this, 'pp_payment_error' ) );
		add_action( 'wp_ajax_nopriv_asp_pp_payment_error', array( $this, 'pp_payment_error' ) );

		add_action( 'wp_ajax_asp_pp_check_coupon', array( $this, 'handle_check_coupon' ) );
		add_action( 'wp_ajax_nopriv_asp_pp_check_coupon', array( $this, 'handle_check_coupon' ) );
	}

	public function handle_3ds_result() {
		$pi_cs = filter_input( INPUT_GET, 'payment_intent_client_secret', FILTER_SANITIZE_STRING );
		$pi_cs = empty( $pi_cs ) ? '' : $pi_cs;
		?>
<!DOCTYPE html>
<html>
<head>
	<style>
	body,html {
		background-color: transparent !important;
		width: 100%;
		height: 100%;
	}
	</style>
</head>
<body>
	<script>
	parent.ThreeDSCompleted('<?php echo esc_js( $pi_cs ); ?>');
	</script>
</body>
</html>
		<?php
		exit;
	}

	public function handle_confirm_pi() {
		$product_id = filter_input( INPUT_POST, 'product_id', FILTER_SANITIZE_NUMBER_INT );

		$item = new ASP_Product_Item( $product_id );

		if ( $item->get_last_error() ) {
			$out['err'] = __( 'Error occurred:', 'stripe-payments' ) . ' ' . $item->get_last_error();
			wp_send_json( $out );
		}

		$item = apply_filters( 'asp_ng_pp_product_item_override', $item );

		do_action( 'asp_ng_before_token_request', $item );

		do_action( 'asp_ng_product_mode_keys', $product_id );

		$pi_id = filter_input( INPUT_POST, 'pi_id', FILTER_SANITIZE_STRING );
		$opts  = filter_input( INPUT_POST, 'opts', FILTER_SANITIZE_STRING );

		if ( ! empty( $opts ) ) {
			$opts = html_entity_decode( $opts );
			$opts = json_decode( $opts, true );
		} else {
			$opts = array();
		}

		$opts['use_stripe_sdk'] = false;

		$home_url = admin_url( 'admin-ajax.php' );

		$disable_3ds_iframe = $this->asp_main->get_setting( 'disable_3ds_iframe' );

		if ( ! $disable_3ds_iframe ) {
			$url_opts = array( 'action' => 'asp_3ds_result' );
		} else {
			$url_opts = array(
				'action'  => 'asp_next_action_results',
				'is_live' => $this->asp_main->is_live,
			);
		}

		$return_url = add_query_arg( $url_opts, $home_url );

		$opts['return_url'] = $return_url;

		$opts = apply_filters( 'asp_ng_confirm_pi_opts', $opts, $pi_id );

		try {
			ASP_Utils::load_stripe_lib();
			$key = $this->asp_main->is_live ? $this->asp_main->APISecKey : $this->asp_main->APISecKeyTest;
			\Stripe\Stripe::setApiKey( $key );

			$api = ASP_Stripe_API::get_instance();
			$api->set_api_key( $key );

			if ( ! ASP_Utils::use_internal_api() ) {
				$pi = \Stripe\PaymentIntent::retrieve( $pi_id );
			} else {
				$pi = $api->get( 'payment_intents/' . $pi_id );
				if ( false === $pi ) {
					$err = $api->get_last_error();
					throw new \Exception( $err['message'], isset( $err['error_code'] ) ? $err['error_code'] : null );
				}
			}
			if ( 'succeeded' === $pi->status ) {
				$out['pi_id'] = $pi->id;
				wp_send_json( $out );
			}
			if ( ! ASP_Utils::use_internal_api() ) {
				$pi->confirm( $opts );
			} else {
				$pi = $api->post(
					'payment_intents/' . $pi_id . '/confirm',
					$opts
				);
				if ( false === $pi ) {
					$err = $api->get_last_error();
					throw new \Exception( $err['message'], isset( $err['error_code'] ) ? $err['error_code'] : null );
				}
			}
		} catch ( \Throwable $e ) {
			$out['err'] = __( 'Stripe API error occurred:', 'stripe-payments' ) . ' ' . $e->getMessage();
			$order      = new ASP_Order_Item();
			if ( false !== $order->find( 'pi_id', $pi_id ) ) {
				$order->change_status( 'error', $out['err'] );
			}
			$body  = __( 'Following error occurred during payment processing:', 'stripe-payments' ) . "\r\n\r\n";
			$body .= $out['err'] . "\r\n\r\n";
			$body .= __( 'Debug data:', 'stripe-payments' ) . "\r\n";
			$body .= wp_json_encode( $_POST );
			ASP_Debug_Logger::log( __( 'Following error occurred during payment processing:', 'stripe-payments' ) . ' ' . $out['err'], false );
			ASP_Utils::send_error_email( $body );
			wp_send_json( $out );
		}

		$out['pi_id'] = $pi->id;

		if ( isset( $pi->next_action ) ) {
			$out['redirect_to'] = $pi->next_action->redirect_to_url->url;
			$out['use_iframe']  = ! $disable_3ds_iframe;
		}

		$out = apply_filters( 'asp_ng_confirm_pi_result_out', $out, $pi_id );

		wp_send_json( $out );
	}

	public function handle_create_pi() {
		$out            = array();
		$out['success'] = false;
		$product_id     = filter_input( INPUT_POST, 'product_id', FILTER_SANITIZE_NUMBER_INT );
		$amount         = filter_input( INPUT_POST, 'amount', FILTER_SANITIZE_NUMBER_FLOAT );
		$curr           = filter_input( INPUT_POST, 'curr', FILTER_SANITIZE_STRING );
		$pi_id          = filter_input( INPUT_POST, 'pi', FILTER_SANITIZE_STRING );
		$cust_id        = filter_input( INPUT_POST, 'cust_id', FILTER_SANITIZE_STRING );
		$quantity       = filter_input( INPUT_POST, 'quantity', FILTER_SANITIZE_NUMBER_INT );

		$item = new ASP_Product_Item( $product_id );

		if ( $item->get_last_error() ) {
			$out['err'] = __( 'Error occurred:', 'stripe-payments' ) . ' ' . $item->get_last_error();
			wp_send_json( $out );
		}

		if ( $item->stock_control_enabled() ) {
			$stock_items        = $item->get_stock_items();
			$out['stock_items'] = $stock_items;
			if ( $quantity > $stock_items ) {
				// translators: %d is number of items in stock
				$msg        = apply_filters( 'asp_customize_text_msg', __( 'You cannot order more items than available: %d', 'stripe-payments' ), 'stock_not_available' );
				$out['err'] = sprintf( $msg, $stock_items );
				wp_send_json( $out );
			}
		}

		$min_amount = $item->get_min_amount( true );
		$prod_type  = $item->get_type();

		if ( 'donation' === $prod_type && 0 !== $min_amount && $min_amount > $amount ) {
			$msg        = apply_filters( 'asp_customize_text_msg', __( 'Minimum amount is', 'stripe-payments' ), 'min_amount_is' );
			$out['err'] = $msg . ' ' . ASP_Utils::formatted_price( $min_amount, $curr, true );
			wp_send_json( $out );
		}

		do_action( 'asp_ng_before_token_request', $item );

		do_action( 'asp_ng_product_mode_keys', $product_id );

		try {
			ASP_Utils::load_stripe_lib();
			$key = $this->asp_main->is_live ? $this->asp_main->APISecKey : $this->asp_main->APISecKeyTest;
			\Stripe\Stripe::setApiKey( $key );

			$api = ASP_Stripe_API::get_instance();

			$api->set_api_key( $key );

		} catch ( \Exception $e ) {
			$out['err'] = __( 'Stripe API error occurred:', 'stripe-payments' ) . ' ' . $e->getMessage();
			wp_send_json( $out );
		} catch ( \Throwable $e ) {
			$out['err'] = __( 'Stripe API error occurred:', 'stripe-payments' ) . ' ' . $e->getMessage();
			wp_send_json( $out );
		}

		$metadata = array();

		try {

			$pi_params = array(
				'amount'              => $amount,
				'currency'            => $curr,
				'confirmation_method' => 'manual',
			);

			$post_billing_details = filter_input( INPUT_POST, 'billing_details', FILTER_SANITIZE_STRING );

			$post_shipping_details = filter_input( INPUT_POST, 'shipping_details', FILTER_SANITIZE_STRING );

			if ( isset( $post_billing_details ) ) {
				$post_billing_details = html_entity_decode( $post_billing_details );

				$billing_details = json_decode( $post_billing_details );
			}

			$dont_save_card = $this->asp_main->get_setting( 'dont_save_card' );

			if ( ! $dont_save_card ) {
				$customer_opts = array();

				if ( isset( $billing_details ) ) {

					if ( ! empty( $billing_details->name ) ) {
						$customer_opts['name'] = $billing_details->name;
					}

					if ( ! empty( $billing_details->email ) ) {
						$customer_opts['email'] = $billing_details->email;
					}

					if ( isset( $billing_details->address ) && isset( $billing_details->address->line1 ) ) {
						//we have address
						$addr = array(
							'line1'   => $billing_details->address->line1,
							'state'   => isset( $billing_details->address->state ) ? $billing_details->address->state : null,
							'city'    => isset( $billing_details->address->city ) ? $billing_details->address->city : null,
							'country' => isset( $billing_details->address->country ) ? $billing_details->address->country : null,
						);

						if ( isset( $billing_details->address->postal_code ) ) {
							$addr['postal_code'] = $billing_details->address->postal_code;
						}

						$customer_opts['address'] = $addr;
					}
				}

				if ( isset( $post_shipping_details ) ) {
					$post_shipping_details = html_entity_decode( $post_shipping_details );

					$shipping_details = json_decode( $post_shipping_details );

					$shipping = array();

					if ( $shipping_details->name ) {
						$shipping['name'] = $shipping_details->name;
					} elseif ( ! empty( $customer_opts['name'] ) ) {
						$shipping['name'] = $customer_opts['name'];
					}

					if ( isset( $shipping_details->address ) && isset( $shipping_details->address->line1 ) ) {
						//we have address
						$addr = array(
							'line1'   => $shipping_details->address->line1,
							'state'   => isset( $shipping_details->address->state ) ? $shipping_details->address->state : null,
							'city'    => isset( $shipping_details->address->city ) ? $shipping_details->address->city : null,
							'country' => isset( $shipping_details->address->country ) ? $shipping_details->address->country : null,
						);

						if ( isset( $shipping_details->address->postal_code ) ) {
							$addr['postal_code'] = $shipping_details->address->postal_code;
						}

						$shipping['address'] = $addr;

						if ( ! empty( $shipping['name'] ) ) {
							$customer_opts['shipping'] = $shipping;
						}
					}
				}

				$customer_opts = apply_filters( 'asp_ng_before_customer_create_update', $customer_opts, empty( $cust_id ) ? false : $cust_id );

				if ( empty( $cust_id ) ) {
					if ( ASP_Utils::use_internal_api() ) {
						$api = ASP_Stripe_API::get_instance();

						$customer = $api->post( 'customers', $customer_opts );

					} else {
						$customer = \Stripe\Customer::create( $customer_opts );
					}

					$cust_id = $customer->id;
				} else {

					if ( ASP_Utils::use_internal_api() ) {
						$api = ASP_Stripe_API::get_instance();

						$customer = $api->post( 'customers/' . $cust_id, $customer_opts );

					} else {
						$customer = \Stripe\Customer::update( $cust_id, $customer_opts );
					}
				}
				$pi_params['customer'] = $cust_id;
			}

			$metadata['Product Name'] = $item->get_name();
			$metadata['Product ID']   = $product_id;

			if ( isset( $metadata ) && ! empty( $metadata ) ) {
				$pi_params['metadata'] = $metadata;
			}
			$description = $item->get_description();
			if ( ! empty( $description ) ) {
				$pi_params['description'] = $description;
			} else {
				$pi_params['description'] = $item->get_name();
			}

			$stripe_receipt_email = $this->asp_main->get_setting( 'stripe_receipt_email' );

			if ( $stripe_receipt_email ) {
				if ( isset( $billing_details ) && isset( $billing_details->email ) && ! empty( $billing_details->email ) ) {
					$pi_params['receipt_email'] = $billing_details->email;
				}
			}

			$order = new ASP_Order_Item();

			if ( $order->can_create( $product_id ) ) {

				if ( ! $pi_id ) {
					//create new incomplete order for this payment
					$order->create( $product_id, $pi_id );
				} else {
					//find order for this PaymentIntent
					$order->find( 'pi_id', $pi_id );
				}
			}

			$pi_params = apply_filters( 'asp_ng_before_pi_create_update', $pi_params );
			if ( $pi_id ) {
				if ( ASP_Utils::use_internal_api() ) {
					$api = ASP_Stripe_API::get_instance();

					$intent = $api->post( 'payment_intents/' . $pi_id, $pi_params );
				} else {
					$intent = \Stripe\PaymentIntent::update( $pi_id, $pi_params );
				}
			} else {
				if ( ASP_Utils::use_internal_api() ) {
					$api = ASP_Stripe_API::get_instance();

					$intent = $api->post( 'payment_intents', $pi_params );
				} else {
					$intent = \Stripe\PaymentIntent::create( $pi_params );
				}
			}
		} catch ( \Exception $e ) {
			$out['shipping'] = wp_json_encode( $shipping );
			$out['err']      = __( 'Error occurred:', 'stripe-payments' ) . ' ' . $e->getMessage();
			wp_send_json( $out );
		} catch ( \Throwable $e ) {
			$out['shipping'] = wp_json_encode( $shipping );
			$out['err']      = __( 'Error occurred:', 'stripe-payments' ) . ' ' . $e->getMessage();
			wp_send_json( $out );
		}

		if ( $order->get_id() ) {
			update_post_meta( $order->get_id(), 'pi_id', $intent->id );
		}

		$out['success']      = true;
		$out['clientSecret'] = $intent->client_secret;
		$out['pi_id']        = $intent->id;
		$out['cust_id']      = $cust_id;
		$out                 = apply_filters( 'asp_ng_before_pi_result_send', $out, $intent );
		wp_send_json( $out );
	}

	public function save_form_data() {
		$out['success'] = true;
		$sess           = ASP_Session::get_instance();
		parse_str( $_POST['form_data'], $form_data );
		$sess->set_transient_data( 'asp_pp_form_data', $form_data );
		ASP_Debug_Logger::log( 'Saved form data: ' . json_encode( $form_data ) );
		wp_send_json( $out );
	}

	public function handle_check_coupon() {
		$product_id = filter_input( INPUT_POST, 'product_id', FILTER_SANITIZE_NUMBER_INT );
		$item       = new ASP_Product_Item( $product_id );

		if ( $item->get_last_error() ) {
			$out['err'] = __( 'Error occurred:', 'stripe-payments' ) . ' ' . $item->get_last_error();
			wp_send_json( $out );
		}

		$coupon_code = filter_input( INPUT_POST, 'coupon_code', FILTER_SANITIZE_STRING );

		$coupon_valid = $item->check_coupon( $coupon_code );
		if ( ! $coupon_valid ) {
			$out['err'] = $item->get_last_error();
			wp_send_json( $out );
		}

		$coupon = $item->get_coupon();

		$zero_value_id           = str_replace( '.', '', uniqid( 'free_', true ) );
		$coupon['zero_value_id'] = $zero_value_id;

		wp_send_json( $coupon );

	}

}

new ASP_PP_Ajax();
