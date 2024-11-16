<?php
class ASP_Process_IPN_NG {

	public $asp_redirect_url = '';
	public $item;
	public $err = '';
	public $asp_class;
	public $sess;
	public $p_data;
	protected static $instance = null;

	public function __construct() {
		self::$instance = $this;

		$this->asp_class        = AcceptStripePayments::get_instance();
		$this->sess             = ASP_Session::get_instance();
		$this->asp_redirect_url = $this->asp_class->get_setting( 'checkout_url' );

		$process_ipn = filter_input( INPUT_POST, 'asp_process_ipn', FILTER_SANITIZE_NUMBER_INT );
		if ( $process_ipn ) {
			add_action( 'asp_ng_process_ipn_payment_data_item_override', array( $this, 'payment_data_override' ), 10, 2 );
			add_action( 'wp_loaded', array( $this, 'process_ipn' ), 2147483647 );
		}

		if ( wp_doing_ajax() ) {
			add_action( 'wp_ajax_asp_next_action_results', array( $this, 'handle_next_action_results' ) );
			add_action( 'wp_ajax_nopriv_asp_next_action_results', array( $this, 'handle_next_action_results' ) );
		}

	}

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function handle_next_action_results() {
        ASP_Debug_Logger::log( 'handle_next_action_results() - processing.', true);
                
		$pi_id = isset( $_GET['payment_intent'] ) ? sanitize_text_field( stripslashes ( $_GET['payment_intent'] ) ) : '';

		$is_live = isset( $_GET['is_live'] ) ? sanitize_text_field( stripslashes ( $_GET['is_live'] ) ) : '';
		$is_live = 'false' === $is_live ? 0 : 1;

		$sess      = ASP_Session::get_instance();
		$form_data = $sess->get_transient_data( 'asp_pp_form_data' );

		foreach ( $form_data as $name => $value ) {
			$post_data[ 'asp_' . $name ] = $value;
		}

		unset( $post_data['asp_process_ipn'] );

		$post_data['asp_payment_intent'] = $pi_id;

		$_POST = $post_data;

		$this->post_data = $post_data;

		$product_id = $this->get_post_var( 'asp_product_id', FILTER_SANITIZE_NUMBER_INT );

		do_action( 'asp_ng_product_mode_keys', $product_id );

		try {

			ASPMain::load_stripe_lib();
			$key = $is_live ? $this->asp_class->APISecKey : $this->asp_class->APISecKeyTest;
			\Stripe\Stripe::setApiKey( $key );

			if ( ASP_Utils::use_internal_api() ) {

				$api = ASP_Stripe_API::get_instance();
				$api->set_api_key( $key );

				$intent = $api->get( 'payment_intents/' . $pi_id );

				if ( 'succeeded' !== $intent->status ) {
					$res = $api->post(
						'payment_intents/' . $pi_id . '/confirm',
						array()
					);
					if ( false === $res ) {
						$err       = $api->get_last_error();
						$this->err = $err['message'];
					}
				}
			} else {
				$intent = \Stripe\PaymentIntent::retrieve( $pi_id );
				if ( 'succeeded' !== $intent->status ) {
					$intent->confirm();
				}
			}
		} catch ( \Throwable $e ) {
			$this->err = $e->getMessage();
		}

		$this->process_ipn( $post_data );
	}

	public function ipn_completed( $err_msg = '' ) {
		if ( ! empty( $err_msg ) ) {
			$asp_data = array( 'error_msg' => $err_msg );
			ASP_Debug_Logger::log( $err_msg, false ); //Log the error

			$this->sess->set_transient_data( 'asp_data', $asp_data );

			//Trigger an action hook for this error condition.
			//The $_POST data is available in the global $_POST variable which may contain additional details.
			do_action( 'asp_stripe_process_ipn_error', $err_msg );		

			//send email to notify site admin (if option enabled)
			$opt = get_option( 'AcceptStripePayments-settings' );
			if ( isset( $opt['send_email_on_error'] ) && $opt['send_email_on_error'] ) {
				$body  = '';
				$body .= __( 'Following error occurred during payment processing:', 'stripe-payments' ) . "\r\n\r\n";
				$body .= $err_msg . "\r\n\r\n";
				$body .= __( 'Debug data:', 'stripe-payments' ) . "\r\n";
                $post  = filter_var( $_POST, FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY );
				foreach ( $post as $key => $value ) {
					//Make sure the value is not an array.
					$value = is_array( $value ) ? wp_json_encode( $value ) : $value;

					$key = sanitize_text_field( stripslashes( $key ));
					$value = sanitize_text_field( stripslashes( $value ));

					$body .= $key . ': ' . $value . "\r\n";
				}
				ASP_Utils::send_error_email( $body );
			}
		} else {
			ASP_Debug_Logger::log( 'Payment has been processed successfully.' );
		}

		$structure = get_option( 'permalink_structure' );

		$url_host      = str_replace( 'www.', '', parse_url( $this->asp_redirect_url, PHP_URL_HOST ) );
		$home_url_host = str_replace( 'www.', '', parse_url( home_url(), PHP_URL_HOST ) );

		if ( empty( $structure && ( $url_host && $url_host === $home_url_host ) ) ) {
			$path   = basename( parse_url( $this->asp_redirect_url, PHP_URL_PATH ) );
			$r_post = get_page_by_path( $path );
			if ( ! empty( $r_post ) ) {
				$this->asp_redirect_url = get_permalink( $r_post->ID );
			}
		}

		if ( is_ssl() ) {
			$this->asp_redirect_url = ASP_Utils::url_to_https( $this->asp_redirect_url );
		}

		ASP_Debug_Logger::log( sprintf( 'Redirecting to results page "%s"', $this->asp_redirect_url ) . "\r\n" );
		wp_redirect( $this->asp_redirect_url );
		exit;
	}

	public function get_post_var( $var, $filter = FILTER_UNSAFE_RAW, $opts = 0 ) {
		if ( isset( $this->post_data ) ) {
			if ( isset( $this->post_data[ $var ] ) ) {
				return filter_var( $this->post_data[ $var ], $filter, $opts );
			} else {
				return null;
			}
		}
		$val = filter_input( INPUT_POST, $var, $filter, $opts );
		return $val;
	}

	private function paid_amount_valid( $expected_amount_in_cents, $amount_paid, $item ) {
		//Check if paid amount is less than expected amount.
		if ( $amount_paid < $expected_amount_in_cents ) {
			//Incorrect amount paid. Flag the transaction.

			//Check if this is a subs product
			if ( method_exists( $item, 'get_plan_id' ) ) {
				//This is a subsription product. Let's check if subs addon version is prior to 2.0.1.
				if ( version_compare( ASPSUB_main::ADDON_VER, '2.0.1' ) < 0 ) {
					//subs addon version is prior to 2.0.1. This means error is most likely not legit.
					return true;
				}
			}
			return false;
		}
		//Paid amount looks good.
		return true;
	}

	private function paid_currency_valid( $p_curr, $configured_currency, $item ){
		//$item object can be used to retreive the product details.
		//Trigger filter that can be used to check this from the subscription addon (if needed).
		$configured_currency = apply_filters( 'asp_ipn_check_currency_configured', $configured_currency, $p_curr, $item );
		
		if ( strtolower($p_curr) !== strtolower($configured_currency) ) {
			return false;
		}
		return true;
	}

	public function process_ipn( $post_data = array() ) {
		ASP_Debug_Logger::log( 'Payment processing started.' );

		if ( ! empty( $post_data ) ) {
			$post_data = filter_var( $post_data, FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY );
			$post_data_str = http_build_query( $post_data, '', '; ' );
			ASP_Debug_Logger::log( 'Custom $_POST data: ' . $post_data_str );
			$this->post_data = $post_data;
		} else {
			$post_data = filter_var( $_POST, FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY );
			$post_data_str = http_build_query( $post_data, '', '; ' );
			//ASP_Debug_Logger::log( 'Original $_POST data: ' . $post_data_str );
		}

		do_action( 'asp_ng_before_payment_processing', $post_data );

		$this->sess = ASP_Session::get_instance();

		$post_thankyou_page_url = $this->get_post_var( 'asp_thankyou_page_url' );
		$post_thankyou_page_url = sanitize_text_field( stripslashes( $post_thankyou_page_url ) );
		$this->asp_redirect_url = empty( $post_thankyou_page_url ) ? $this->asp_class->get_setting( 'checkout_url' ) : base64_decode( $post_thankyou_page_url );

		$prod_id = $this->get_post_var( 'asp_product_id', FILTER_SANITIZE_NUMBER_INT );

		if ( ! empty( $prod_id ) ) {
			ASP_Debug_Logger::log( sprintf( 'Got product ID: %d', $prod_id ) );
		}

		$item = new ASP_Product_Item( $prod_id );

		ASP_Debug_Logger::log( 'Firing asp_ng_process_ipn_product_item_override filter.' );

		$item = apply_filters( 'asp_ng_process_ipn_product_item_override', $item );

		$err = $item->get_last_error();

		if ( ! empty( $err ) ) {
			$this->ipn_completed( $err );
		}

		$this->item = $item;

		if ( empty( $this->asp_redirect_url ) && $item->get_redir_url() ) {
			$this->asp_redirect_url = $item->get_redir_url();
		}

		if ( $this->err ) {
			$this->ipn_completed( $this->err );
		}

		$pi = $this->get_post_var( 'asp_payment_intent' );
		$pi = sanitize_text_field( stripslashes( $pi ) );

		$completed_order = get_posts(
			array(
				'post_type'  => 'stripe_order',
				'meta_key'   => 'pi_id',
				'meta_value' => $pi,
			)
		);

		wp_reset_postdata();

		if ( ! empty( $completed_order ) ) {
			//already processed - let's redirect to results page
			$this->ipn_completed();
			exit;
		}

		$is_live = $this->get_post_var( 'asp_is_live', FILTER_VALIDATE_BOOLEAN );

		do_action( 'asp_ng_product_mode_keys', $prod_id );

		ASP_Utils::load_stripe_lib();
		$key = $is_live ? $this->asp_class->APISecKey : $this->asp_class->APISecKeyTest;
		\Stripe\Stripe::setApiKey( $key );

		$api = ASP_Stripe_API::get_instance();
		$api->set_api_key( $key );

		//Get Payment Data
		ASP_Debug_Logger::log( 'Firing asp_ng_process_ipn_payment_data_item_override filter.' );

		$p_data = apply_filters( 'asp_ng_process_ipn_payment_data_item_override', false, $pi );

		if ( false === $p_data ) {
			//Payment data override filter did not return any data. Let's get the data from the payment intent object.
			//The billing details [example: ASP_Payment_Data->get_billing_details()] and some other transaction data are read from the payment intent object within the ASP_Payment_Data class.
			$p_data = new ASP_Payment_Data( $pi );
		}

		$p_last_err = $p_data->get_last_error();

		if ( ! empty( $p_last_err ) ) {
			$this->ipn_completed( $p_last_err );
		}

		$this->p_data = $p_data;
		//End retrieval of payment data

		//Mechanism to lock the txn that is being processed.
		$txn_being_processed = get_option( 'asp_ng_ipn_txn_being_processed' );
		$notification_txn_id = $p_data->get_trans_id();
		ASP_Debug_Logger::log( 'The transaction ID of this notification is: ' . $notification_txn_id );
		if ( ! empty( $txn_being_processed ) && $txn_being_processed === $notification_txn_id ) {
			//No need to process this transaction as it is already being processed.
			ASP_Debug_Logger::log( 'This transaction (' . $notification_txn_id . ') is already being procesed. This is likely a duplicate notification. Nothing to do.' );
			return true;
		}
		update_option( 'asp_ng_ipn_txn_being_processed', $notification_txn_id );
		//End of transaction processing lock mechanism

		//Button key
		$button_key = $item->get_button_key();

		//Item quantity
		$post_quantity = $this->get_post_var( 'asp_quantity', FILTER_SANITIZE_NUMBER_INT );
		if ( $post_quantity ) {
			$item->set_quantity( $post_quantity );
		}

		//Item price
		$price = $item->get_price();
		$curr = $item->get_currency();
		$shipping = $item->get_shipping();

		if ( ! method_exists( $item, 'get_plan_id' ) ) {
			$price_arr = apply_filters(
				'asp_modify_price_currency_shipping',
				array(
					'price'    => $price,
					'currency' => $curr,
					'shipping' => empty( $shipping ) ? false : $shipping,
					'variable' => empty( $price ) ? true : false,
				)
			);
			$item->set_price( $price_arr['price'] );
			$item->set_currency( $price_arr['currency'] );
			$item->set_shipping( $price_arr['shipping'] );
		}

		$tax_variations_arr = $item->get_meta( 'asp_product_tax_variations' );

		$tax_variations_type = $this->item->get_meta( 'asp_product_tax_variations_type' );

		$tax_variations_type = empty( $tax_variations_type ) ? 'b' : $tax_variations_type;

		if ( 'b' === $tax_variations_type ) {
			$bs_details = $p_data->get_billing_details();
		} else {
			$bs_details = $p_data->get_shipping_details();
		}

		if ( ! empty( $bs_details ) && ! empty( $tax_variations_arr ) ) {
			$new_tax = ASP_Utils::get_tax_variations_tax(
				$tax_variations_arr,
				empty( $bs_details->country ) ? '' : $bs_details->country,
				empty( $bs_details->state ) ? '' : $bs_details->state,
				empty( $bs_details->city ) ? '' : $bs_details->city
			);
			if ( false !== $new_tax ) {
				$item->set_tax( $new_tax );
			}
		}

		if ( empty( $price ) ) {
			$post_price = $this->get_post_var( 'asp_amount', FILTER_SANITIZE_NUMBER_FLOAT );
			if ( $post_price ) {
				$price = $post_price;
			} else {
				if ( ! $item->get_meta( 'asp_product_hide_amount_input' ) ) {
					$price = $p_data->get_price();
				} else {
					$price = 0;
				}
			}
			$price = AcceptStripePayments::from_cents( $price, $item->get_currency() );
			$item->set_price( $price );
		}

		$item_price = $item->get_price();

		//Variatoions
		$variations = array();
		$posted_variations = $this->get_post_var( 'asp_stripeVariations', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
		if ( $posted_variations ) {
			// we got variations posted. Let's get variations from product
			$v = new ASPVariations( $prod_id );
			if ( ! empty( $v->variations ) ) {
				//there are variations configured for the product
				ASP_Debug_Logger::log( 'Processing variations.' );
				foreach ( $posted_variations as $grp_id => $var_id ) {
					if ( is_array( $var_id ) && ! empty( $var_id ) ) {
						foreach ( $var_id as $p_var ) {
							$var = $v->get_variation( $grp_id, $p_var );
							if ( ! empty( $var ) ) {
								$item->add_item( $var['name'], $var['price'] );
								$variations[]  = array( $var['group_name'] . ' - ' . $var['name'], $var['price'] );
								$var_applied[] = $var;
							}
						}
					}
				}
			}
		}

		//Coupon
		$coupon_code = $this->get_post_var( 'asp_coupon-code' );
		$coupon_valid = false;
		if ( isset($coupon_code) && !empty($coupon_code)) {
			$coupon_code = sanitize_text_field( stripslashes( $coupon_code ));
			ASP_Debug_Logger::log( sprintf( 'Coupon code provided: %s', $coupon_code ) );
			$coupon_valid = $item->check_coupon( $coupon_code );
			
			if ( $coupon_valid ) {
				ASP_Debug_Logger::log( 'Coupon is valid for the product.' );
			} else {
				ASP_Debug_Logger::log( 'Coupon is invalid for the product.' );
			}
		}

		$amount_in_cents = intval( $item->get_total( true ) );
		$amount_paid     = intval( $p_data->get_amount() );

		$configured_currency = $item->get_currency();//Currency configured in product/general settings
		$is_currency_variable = $item->is_currency_variable();
		$p_curr = $p_data->get_currency();//Currency from payment data

		//Check currency
		$paid_currency_valid = $this->paid_currency_valid( $p_curr, $configured_currency, $item );
		//Trigger filter that can be used to check this from the subscription addon (if needed).
		$paid_currency_valid = apply_filters( 'asp_process_ipn_paid_currency_valid', $paid_currency_valid, $item );
		if ( !$paid_currency_valid ) {
			//Check if the currency variable option is enabled.
			$is_currency_variable = apply_filters( 'asp_process_ipn_is_currency_variable', $is_currency_variable, $item );
			if ( $is_currency_variable ){
				//The currency variable option is enabled. We will allow this request to go through.
				//ASP_Debug_Logger::log( 'Note! The currency variable option is enabled in the product settings', true );
			} else {
				$err = sprintf(
					// translators: placeholders are expected and received currencies
					__( 'Invalid currency received. Expected %1$s, got %2$s.', 'stripe-payments' ),
					$configured_currency,
					$p_curr
				);
				//The following function will also log the error to the debug log.
				$this->ipn_completed( $err );
			}
		}

		//Check paid amount
		$paid_amount_valid = $this->paid_amount_valid( $amount_in_cents, $amount_paid, $item );
		if ( ! $paid_amount_valid ) {
			$err  = sprintf(
				// translators: placeholders are expected and received amounts
				__( 'Invalid payment amount received. Expected %1$s, got %2$s.', 'stripe-payments' ),
				AcceptStripePayments::formatted_price( $amount_in_cents, $p_curr, true ),
				AcceptStripePayments::formatted_price( $amount_paid, $p_curr, true )
			);
			//The following function will also log the error to the debug log.
			$this->ipn_completed( $err );
		}

		$opt = get_option( 'AcceptStripePayments-settings' );

		ASP_Debug_Logger::log( 'Constructing checkout result and order data.' );

		$p_curr            = $p_data->get_currency();
		$p_amount          = $p_data->get_amount();
		$p_charge_data     = $p_data->get_charge_data();
		$p_charge_created  = $p_data->get_charge_created();
		$p_trans_id        = $p_data->get_trans_id();
		$p_billing_details = $p_data->get_billing_details();
		$p_customer_details = $p_data->get_customer_details();

		if ( empty( $p_billing_details->email ) ) {
			$email = $this->get_post_var( 'asp_email', FILTER_SANITIZE_EMAIL );
			if ( ! empty( $email ) ) {
				$p_billing_details->email = $email;
			}
		}

		if ( empty( $p_billing_details->name ) ) {
			$name = $this->get_post_var( 'asp_billing_name' );
			$name = sanitize_text_field( stripslashes( $name ));
			if ( ! empty( $name ) ) {
				$p_billing_details->name = $name;
			}
		}

		$data                       = array();
		$data['product_id']         = $prod_id ? $prod_id : null;
		$data['paid_amount']        = AcceptStripePayments::is_zero_cents( $p_curr ) ? $p_amount : AcceptStripePayments::from_cents( $p_amount, $p_curr );
		$data['currency_code']      = strtoupper( $p_curr );
		$data['item_quantity']      = $item->get_quantity();
		$data['charge']             = $p_charge_data;
		$data['stripeToken']        = '';
		$data['stripeTokenType']    = 'card';
		$data['is_live']            = $is_live;
		$data['charge_description'] = $item->get_description();
		$data['item_name']          = $item->get_name();
		$data['item_price']         = $item->get_price( AcceptStripePayments::is_zero_cents( $data['currency_code'] ) );
		$data['stripeEmail']        = $p_billing_details->email;
		$data['customer_name']      = $p_billing_details->name;
		$purchase_date              = gmdate( 'Y-m-d H:i:s', $p_charge_created );
		$purchase_date              = get_date_from_gmt( $purchase_date, get_option( 'date_format' ) . ', ' . get_option( 'time_format' ) );
		$data['purchase_date']      = $purchase_date;
		$data['charge_date']        = $purchase_date;
		$data['charge_date_raw']    = $p_charge_created;
		$data['txn_id']             = $p_trans_id;
		$data['button_key']         = $button_key;

		$customer_metadata = isset($p_customer_details->metadata) ? $p_customer_details->metadata : array();
		if ( !empty($customer_metadata) ){
			$data['customer_first_name'] = isset($customer_metadata['First Name']) ? sanitize_text_field($customer_metadata['First Name']) : '';
			$data['customer_last_name'] = isset($customer_metadata['Last Name']) ? sanitize_text_field($customer_metadata['Last Name']) : '';
		}

		$item_url = $item->get_download_url();

		$data['item_url'] = $item_url;

		$data['billing_address'] = $p_data->get_billing_addr_str();

		$data['shipping_address'] = $p_data->get_shipping_addr_str();

		//Check if there is a logged in user who is making the purchase.
		$logged_in_user_info = ASP_Utils::get_logged_in_user_info();
		if ( is_array($logged_in_user_info) && !empty($logged_in_user_info) ) {
			$data['logged_in_user_type'] = isset($logged_in_user_info['type']) ? $logged_in_user_info['type'] : '';
			$data['logged_in_user_id'] = isset($logged_in_user_info['id']) ? $logged_in_user_info['id'] : '';
			$data['logged_in_user_name'] = isset($logged_in_user_info['username']) ? $logged_in_user_info['username'] : '';
			ASP_Debug_Logger::log( 'Logged-in user\'s Username: ' . $data['logged_in_user_name'] );
		}

		$data['additional_items'] = array();

		ASP_Debug_Logger::log( 'Firing asp_ng_payment_completed filter.' );

		$data = apply_filters( 'asp_ng_payment_completed', $data, $prod_id );

		$currency_code = $item->get_currency();
		$item_price    = $item->get_price( AcceptStripePayments::is_zero_cents( $currency_code ) );

		$custom_fields = array();
		$cf_name = $this->get_post_var( 'asp_stripeCustomFieldName' );
		if ( $cf_name ) {
			$cf_name = sanitize_text_field( stripslashes( $cf_name ));

			$cf_value = $this->get_post_var( 'asp_stripeCustomField' );
			$cf_value = sanitize_text_field( stripslashes( $cf_value ));
			$custom_fields[] = array(
				'name'  => $cf_name,
				'value' => $cf_value,
			);
		}

		//Compatability with the ACF addon
                //Note: This $acf_fields input is now handled directly in the new version of the ACF addon. So it is commented out here.
		//$acf_fields = $this->get_post_var( 'asp_stripeCustomFields', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );

		$custom_fields = apply_filters( 'asp_process_custom_fields', $custom_fields, array( 'product_id' => $prod_id ) );

		if ( ! empty( $custom_fields ) ) {
			$data['custom_fields'] = $custom_fields;
		}

		if ( ! empty( $var_applied ) ) {
			//process variations URLs if needed
			foreach ( $var_applied as $key => $var ) {
				if ( ! empty( $var['url'] ) ) {
					$var                 = apply_filters( 'asp_variation_url_process', $var, $data );
					$var_applied[ $key ] = $var;
				}
			}
			$data['var_applied'] = $var_applied;
			foreach ( $variations as $variation ) {
				$data['additional_items'][ $variation[0] ] = $variation[1];
			}
		}

		//check if coupon was used
		if ( $coupon_valid ) {
			$coupon = $item->get_coupon();
		}
		if ( isset( $coupon ) ) {
			$data['coupon']      = $coupon;
			$data['coupon_code'] = $coupon['code'];

			$coupon_discount_str = apply_filters( 'asp_ng_coupon_discount_str', floatval( '-' . $item->get_coupon_discount_amount() ), $coupon );
			// translators: %s is coupon code
			$data['additional_items'][ sprintf( __( 'Coupon "%s"', 'stripe-payments' ), $coupon['code'] ) ] = $coupon_discount_str;

			$subtotal = $item->get_price( false, true ) + $item->get_items_total( false, true );
			$subtotal = $subtotal < 0 ? 0 : $subtotal;
			$data['additional_items'][ __( 'Subtotal', 'stripe-payments' ) ] = $subtotal;
			//increase coupon redeem count
			$curr_redeem_cnt = get_post_meta( $coupon['id'], 'asp_coupon_red_count', true );
			$curr_redeem_cnt++;
			update_post_meta( $coupon['id'], 'asp_coupon_red_count', $curr_redeem_cnt );
		}
		$tax = $item->get_tax();
		if ( ! empty( $tax ) ) {
			$tax_str = apply_filters( 'asp_customize_text_msg', __( 'Tax', 'stripe-payments' ), 'tax_str' );
			$tax_amt = $item->get_tax_amount( false, true );
			$data['additional_items'][ ucfirst( $tax_str ) ] = $tax_amt;
			$data['tax_perc']                                = $item->get_tax();
			$data['tax']                                     = $tax_amt;
		}
		$ship = $item->get_shipping();
		if ( ! empty( $ship ) ) {
			$ship_str = apply_filters( 'asp_customize_text_msg', __( 'Shipping', 'stripe-payments' ), 'shipping_str' );
			$data['additional_items'][ ucfirst( $ship_str ) ] = $item->get_shipping();
			$data['shipping']                                 = $item->get_shipping();
		}

		$product_type = $item->get_type();
		//Check if surcharge is enabled for this product (if one-time or donations type product).
		if ( in_array($product_type, array('one_time', 'donation')) ){
			//Surcharge feature is currently supported for one_time and donation products only.
			$surcharge_amount = $p_data->get_surcharge_data('amount');
			if ( !empty($surcharge_amount) ){
				$surcharge_label = $p_data->get_surcharge_data('label');
				$data['additional_items'][$surcharge_label] = $surcharge_amount;
			}
		}

		//custom fields
		$custom_fields = $this->sess->get_transient_data( 'custom_fields' );
		if ( ! empty( $custom_fields ) ) {
			$data['custom_fields'] = $custom_fields;
			$this->sess->set_transient_data( 'custom_fields', array() );
		}

		$metadata = array();

		//Check if we need to include custom field in metadata
		if ( ! empty( $data['custom_fields'] ) ) {
			$cf_str = '';
			foreach ( $data['custom_fields'] as $cf ) {
				$cf_str .= $cf['name'] . ': ' . $cf['value'] . ' | ';
			}
			$cf_str = rtrim( $cf_str, ' | ' );
			//trim the string as metadata value cannot exceed 500 chars
			$cf_str                    = substr( $cf_str, 0, 499 );
			$metadata['Custom Fields'] = $cf_str;
		}

		//Check if we need to include variations data into metadata
		if ( ! empty( $variations ) ) {
			$var_str = '';
			foreach ( $variations as $variation ) {
				$var_str .= '[' . $variation[0] . '], ';
			}
			$var_str = rtrim( $var_str, ', ' );
			//trim the string as metadata value cannot exceed 500 chars
			$var_str                = substr( $var_str, 0, 499 );
			$metadata['Variations'] = $var_str;
		}

		if ( ! empty( $data['shipping_address'] ) ) {
			//add shipping address to metadata
			$shipping_address             = str_replace( "\n", ', ', $data['shipping_address'] );
			$shipping_address             = rtrim( $shipping_address, ', ' );
			$metadata['Shipping Address'] = $shipping_address;
		}

		//Save coupon info to metadata if applicable
		if ( $coupon_valid ) {
			$metadata['Coupon Code'] = strtoupper( $coupon['code'] );
		}

		$update_opts = array();

		if ( ! empty( $metadata ) ) {
			ASP_Debug_Logger::log( 'Firing asp_ng_handle_metadata filter.' );
			$metadata_handled = apply_filters( 'asp_ng_handle_metadata', $metadata );
			if ( true !== $metadata_handled ) {
				ASP_Debug_Logger::log( 'Updating payment metadata.' );
				$update_opts['metadata'] = $metadata;
			}
		}

		ASP_Debug_Logger::log( 'Firing asp_ng_payment_completed_update_pi filter.' );
		$update_opts = apply_filters( 'asp_ng_payment_completed_update_pi', $update_opts, $data );

		if ( ! empty( $update_opts && ! $p_data->is_zero_value ) ) {
			ASP_Debug_Logger::log( 'Updating payment intent data.' );
			if ( ASP_Utils::use_internal_api() ) {
				$intent = $api->post( 'payment_intents/' . $pi, $update_opts );
			} else {
				$intent = \Stripe\PaymentIntent::update( $pi, $update_opts );
			}
		}

		$product_details  = __( 'Product Name: ', 'stripe-payments' ) . $data['item_name'] . "\n";
		$product_details .= __( 'Quantity: ', 'stripe-payments' ) . $data['item_quantity'] . "\n";
		$product_details .= __( 'Item Price: ', 'stripe-payments' ) . AcceptStripePayments::formatted_price( $data['item_price'], $data['currency_code'] ) . "\n";

		//check if there are any additional items available like tax and shipping cost
		$product_details        .= AcceptStripePayments::gen_additional_items( $data );
		$product_details        .= '--------------------------------' . "\n";
		$product_details        .= __( 'Total Amount: ', 'stripe-payments' ) . AcceptStripePayments::formatted_price( $data['paid_amount'], $data['currency_code'] ) . "\n";
		$data['product_details'] = nl2br( $product_details );

		//Insert the order data to the custom post
		$order = new ASP_Order_Item();
		if ( $order->can_create() ) {
			$order_post_id = $order->find( 'pi_id', $pi );

			if ( false === $order_post_id ) {
				//no order was created. Let's create one
				$order->create( $prod_id, $pi );
			}

			$order_post_id = $order->update_legacy( $data, $data['charge'] );

			$intent = $p_data->get_obj();
			if ( isset( $intent ) && isset( $intent->status ) && 'requires_capture' === $intent->status ) {
				$order->change_status( 'authorized' );
			} else {
				$order->change_status( 'paid' );
			}

			$data['order_post_id'] = $order_post_id;
			update_post_meta( $order_post_id, 'order_data', $data );
			update_post_meta( $order_post_id, 'charge_data', $data['charge'] );
			update_post_meta( $order_post_id, 'trans_id', $p_trans_id );
		}

		//stock control
		if ( get_post_meta( $data['product_id'], 'asp_product_enable_stock', true ) ) {
			$stock_items = intval( get_post_meta( $data['product_id'], 'asp_product_stock_items', true ) );
			$stock_items = $stock_items - $data['item_quantity'];
			if ( $stock_items < 0 ) {
				$stock_items = 0;
			}
			update_post_meta( $data['product_id'], 'asp_product_stock_items', $stock_items );
			$data['stock_items'] = $stock_items;
		}

		//Action hook with the checkout post data parameters.
		ASP_Debug_Logger::log( 'Firing asp_stripe_payment_completed action.' );
		do_action( 'asp_stripe_payment_completed', $data, $data['charge'] );

		//Let's handle email sending stuff
		if ( ! empty( $opt['send_emails_to_buyer'] ) ) {
			$from = $opt['from_email_address'];
			$to   = $data['stripeEmail'];
			$subj = $opt['buyer_email_subject'];
			$body = $opt['buyer_email_body'];

			// * since 2.0.47
			$email_data = array(
				'from' => $from,
				'to'   => $to,
				'subj' => $subj,
				'body' => $body,
			);
			$email_data = apply_filters( 'asp_buyer_email_data', $email_data, $data );

			$from = $email_data['from'];
			$to   = $email_data['to'];
			$subj = $email_data['subj'];
			$body = $email_data['body'];
			// * end since

			$body = asp_apply_dynamic_tags_on_email_body( $body, $data );

			$subj = apply_filters( 'asp_buyer_email_subject', $subj, $data );
			$body = apply_filters( 'asp_buyer_email_body', $body, $data );
			$from = apply_filters( 'asp_buyer_email_from', $from, $data );

			$headers = array();
			if ( ! empty( $opt['buyer_email_type'] ) && 'html' === $opt['buyer_email_type'] ) {
				$headers[] = 'Content-Type: text/html; charset=UTF-8';
				$body      = nl2br( $body );
			}
			$headers[] = 'From: ' . $from;

			$schedule_result = ASP_Utils::mail( $to, $subj, $body, $headers );

			if ( ! $schedule_result ) {
				ASP_Debug_Logger::log( 'Notification email sent to buyer: ' . $to . ', from email address used: ' . $from );
			} else {
				ASP_Debug_Logger::log( 'Notification email to buyer scheduled: ' . $to . ', from email address used: ' . $from );
			}
		}

		if ( ! empty( $opt['send_emails_to_seller'] ) ) {
			$from = $opt['from_email_address'];
			$to   = $opt['seller_notification_email'];
			$subj = $opt['seller_email_subject'];
			$body = $opt['seller_email_body'];

			// * since 2.0.47
			$email_data = array(
				'from' => $from,
				'to'   => $to,
				'subj' => $subj,
				'body' => $body,
			);
			$email_data = apply_filters( 'asp_seller_email_data', $email_data, $data );

			$from = $email_data['from'];
			$to   = $email_data['to'];
			$subj = $email_data['subj'];
			$body = $email_data['body'];
			// * end since

			$body = asp_apply_dynamic_tags_on_email_body( $body, $data, true );

			$subj = apply_filters( 'asp_seller_email_subject', $subj, $data );
			$body = apply_filters( 'asp_seller_email_body', $body, $data );
			$from = apply_filters( 'asp_seller_email_from', $from, $data );

			$headers = array();
			if ( ! empty( $opt['seller_email_type'] ) && 'html' === $opt['seller_email_type'] ) {
				$headers[] = 'Content-Type: text/html; charset=UTF-8';
				$body      = nl2br( $body );
			}
			$headers[] = 'From: ' . $from;

			$schedule_result = ASP_Utils::mail( $to, $subj, $body, $headers );
			if ( ! $schedule_result ) {
				ASP_Debug_Logger::log( 'Notification email sent to seller: ' . $to . ', from email address used: ' . $from );
			} else {
				ASP_Debug_Logger::log( 'Notification email to seller scheduled: ' . $to . ', from email address used: ' . $from );
			}
		}

		$this->sess->set_transient_data( 'asp_data', $data );

		$this->sess->set_transient_data( 'asp_pp_form_data', array() );

		//Clear the txn lock
		update_option( 'asp_ng_ipn_txn_being_processed', '' );

		$this->ipn_completed();
	}

	public function payment_data_override( $p_data, $pi ) {
		//check if this is zero-value transaction
		if ( 'free' === substr( $pi, 0, 4 ) ) {
			//this is zero-value transaction
			$coupon_code = $this->get_post_var( 'asp_coupon-code' );
			$coupon_code = sanitize_text_field( stripslashes( $coupon_code ));
			if ( empty( $coupon_code ) ) {
				return $p_data;
			}
			$coupon_valid = $this->item->check_coupon( $coupon_code );

			if ( ! $coupon_valid ) {
				return $p_data;
			}

			$coupon_discount_amount = $this->item->get_coupon_discount_amount();

			$price_no_discount = $this->item->get_price();

			if ( $coupon_discount_amount < $price_no_discount ) {
				return $p_data;
			}

			$prod_id = $this->item->get_product_id();

			$order = new ASP_Order_Item();

			if ( $order->can_create( $prod_id ) ) {
				$order->create( $prod_id, $pi );
			}

			$p_data = new ASP_Payment_Data( $pi, true );
		}
		return $p_data;
	}
}

new ASP_Process_IPN_NG();
