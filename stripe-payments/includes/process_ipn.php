<?php

class AcceptStripePayments_Process_IPN {


	protected static $instance = null;
	var $aspRedirectURL;
	var $sess;

	function __construct() {
		self::$instance = $this;
		add_action( 'init', array( $this, 'init' ) );
	}

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	function init() {
		if ( isset( $_POST['asp_action'] ) ) {
			if ( 'process_ipn' === $_POST['asp_action'] ) {
				//check if Legacy API is enabled
				$opt = get_option( 'AcceptStripePayments-settings' );
				if ( isset( $opt['use_old_checkout_api1'] ) && $opt['use_old_checkout_api1'] ) {
					$this->sess = ASP_Session::get_instance();
					$this->process_ipn();
				} else {
					//Legacy API is disabled, but request was made to it
					ASP_Debug_Logger::log( 'Legacy API backend accessed while it is disabled.', false );
					wp_die( 'Access denied.' );
				}
			}
		}
	}

	function ipn_completed( $errMsg = '' ) {
		if ( ! empty( $errMsg ) ) {
			$aspData = array( 'error_msg' => $errMsg );
			ASP_Debug_Logger::log( $errMsg, false ); //Log the error

			$msg_before_process = __( 'Error occurred before user interacted with payment popup. This might be caused by JavaScript errors on page.', 'stripe-payments' );
			$msg_after_process  = __( 'Error occurred after user interacted with popup.', 'stripe-payments' );

			if ( isset( $_POST['clickProcessed'] ) ) {
				$additional_msg = $msg_after_process;
			} else {
				$additional_msg = $msg_before_process;
			}

			ASP_Debug_Logger::log( $additional_msg, false );

			$this->sess->set_transient_data( 'asp_data', $aspData );

			//send email to notify site admin (if option enabled)
			$opt = get_option( 'AcceptStripePayments-settings' );
			if ( isset( $opt['send_email_on_error'] ) && $opt['send_email_on_error'] ) {
				$to      = $opt['send_email_on_error_to'];
				$from    = get_option( 'admin_email' );
				$headers = 'From: ' . $from . "\r\n";
				$subj    = __( 'Stripe Payments Error Details', 'stripe-payments' );
				$body    = __( 'Following error occurred during payment processing:', 'stripe-payments' ) . "\r\n\r\n";
				$body   .= $errMsg . "\r\n\r\n";
				$body   .= $additional_msg . "\r\n";
				$body   .= __( 'Debug data:', 'stripe-payments' ) . "\r\n";
				foreach ( $_POST as $key => $value ) {
					$value = is_array( $value ) ? json_encode( $value ) : $value;
					$body .= $key . ': ' . $value . "\r\n";
				}
				wp_mail( $to, $subj, $body, $headers );
			}
		} else {
			ASP_Debug_Logger::log( 'Payment has been processed successfully.' );
		}
		ASP_Debug_Logger::log( sprintf( 'Redirecting to results page "%s"', $this->aspRedirectURL ) . "\r\n" );
		wp_redirect( $this->aspRedirectURL );
		exit;
	}

	function process_ipn() {
		$asp_class = AcceptStripePayments::get_instance();

		ASP_Debug_Logger::log( 'Payment processing started.' );

		$post_thankyou_page_url = isset( $_POST['thankyou_page_url'] ) ? sanitize_text_field( $_POST['thankyou_page_url'] ) : false;

		$this->aspRedirectURL = empty( $post_thankyou_page_url ) ? $asp_class->get_setting( 'checkout_url' ) : base64_decode( $post_thankyou_page_url );

		ASP_Debug_Logger::log( 'Triggering hook for addons to process posted data if needed.' );
		$process_result = apply_filters( 'asp_before_payment_processing', array(), $_POST );

		if ( isset( $process_result ) && ! empty( $process_result ) ) {
			if ( isset( $process_result['error'] ) && ! empty( $process_result['error'] ) ) {
				$this->ipn_completed( $process_result['error'] );
			}
		}

		//Check nonce
		ASP_Debug_Logger::log( 'Checking received data.' );

		if ( ! isset( $_POST['stripeToken'] ) || empty( $_POST['stripeToken'] ) ) {
			$this->ipn_completed( 'Invalid Stripe Token' );
		}
		if ( ! isset( $_POST['stripeTokenType'] ) || empty( $_POST['stripeTokenType'] ) ) {
			$this->ipn_completed( 'Invalid Stripe Token Type' );
		}

		if ( ! isset( $_POST['stripeEmail'] ) || empty( $_POST['stripeEmail'] ) ) {
			$this->ipn_completed( 'Invalid Request' );
		}

		$got_product_data_from_db = false;

		if ( isset( $_POST['stripeProductId'] ) && ! empty( $_POST['stripeProductId'] ) ) {
			//got product ID. Let's try to get required data from database instead of $_POST data
			$prod_id = intval( $_POST['stripeProductId'] );
			ASP_Debug_Logger::log( 'Got product ID: ' . $prod_id . '. Trying to get info from database.' );
			$post = get_post( $prod_id );
			if ( ! $post || get_post_type( $prod_id ) != ASPMain::$products_slug ) {
				//this is not Stripe Payments product
				$this->ipn_completed( 'Invalid product ID: ' . $prod_id );
			}
			$item_name = $post->post_title;

			$currency_code = get_post_meta( $prod_id, 'asp_product_currency', true );

			$currency_variable = get_post_meta( $prod_id, 'asp_product_currency_variable', true );

			if ( $currency_variable ) {
				$currency_code = sanitize_text_field( $_POST['stripeCurrency'] );
			}

			if ( ! $currency_code ) {
				$currency_code = $asp_class->get_setting( 'currency_code' );
			}

			$item_quantity = get_post_meta( $prod_id, 'asp_product_quantity', true );

			$item_custom_quantity = get_post_meta( $prod_id, 'asp_product_custom_quantity', true );

			if ( $item_custom_quantity ) {
				//custom quantity. Let's get the value from $_POST data
				$item_custom_quantity = intval( $_POST['stripeCustomQuantity'] );
			} else {
				$item_custom_quantity = false;
			}

			$variable = false;

			$item_price = get_post_meta( $prod_id, 'asp_product_price', true );

			if ( empty( $item_price ) ) {
				//this is probably custom price
				$variable   = true;
				$item_price = floatval( $_POST['stripeAmount'] );
			}

			//get tax and shipping amounts if applicable

			$tax = get_post_meta( $prod_id, 'asp_product_tax', true );

			$shipping = floatval( get_post_meta( $prod_id, 'asp_product_shipping', true ) );

			//let's apply filter so addons can change price, currency and shipping if needed
			$price_arr = array(
				'price'    => $item_price,
				'currency' => $currency_code,
				'shipping' => empty( $shipping ) ? false : $shipping,
				'variable' => $variable,
			);
			$price_arr = apply_filters( 'asp_modify_price_currency_shipping', $price_arr );
			extract( $price_arr, EXTR_OVERWRITE );
			$item_price    = $price;
			$currency_code = $currency;

			//handle item url
			$item_url = get_post_meta( $prod_id, 'asp_product_upload', true );

			if ( ! empty( $item_url ) ) {
				$item_url = base64_encode( $item_url );
			} else {
				$item_url = '';
			}

			$post_item_url = isset( $_POST['item_url'] ) ? sanitize_text_field( $_POST['item_url'] ) : false;

			$button_key = isset( $_POST['stripeButtonKey'] ) ? sanitize_text_field( $_POST['stripeButtonKey'] ) : false;

			if ( empty( $button_key ) ) {
				//let's generate our own button key
				$price          = AcceptStripePayments::apply_tax( $item_price, $tax, AcceptStripePayments::is_zero_cents( $currency_code ) );
				$price          = AcceptStripePayments::apply_shipping( $price, $shipping, AcceptStripePayments::is_zero_cents( $currency_code ) );
				$price_in_cents = $price;
				if ( ! AcceptStripePayments::is_zero_cents( $currency_code ) ) {
					$price_in_cents = $price_in_cents * 100;
				}
				$button_key = md5( htmlspecialchars_decode( $item_name ) . $price_in_cents );
			}

			if ( ! empty( $post_item_url ) ) {
				if ( $item_url !== $post_item_url ) {
					$item_url = $post_item_url;
				}
			}

			$got_product_data_from_db = true;

			ASP_Debug_Logger::log( 'Got required product info from database.' );
		}

		if ( ! $got_product_data_from_db ) {
			//couldn't get data from database by product ID for some reason. Getting data from $_POST instead

			if ( ! isset( $_POST['item_name'] ) || empty( $_POST['item_name'] ) ) {
				$this->ipn_completed( 'Invalid Item name' );
			}

			if ( ! isset( $_POST['currency_code'] ) || empty( $_POST['currency_code'] ) ) {
				$this->ipn_completed( 'Invalid Currency Code' );
			}

			$item_name            = stripslashes( sanitize_text_field( $_POST['item_name'] ) );
			$item_quantity        = sanitize_text_field( $_POST['item_quantity'] );
			$item_custom_quantity = isset( $_POST['stripeCustomQuantity'] ) ? intval( $_POST['stripeCustomQuantity'] ) : false;
			$item_url             = sanitize_text_field( $_POST['item_url'] );
			$button_key           = sanitize_text_field($_POST['stripeButtonKey']);
			$reported_price       = sanitize_text_field($_POST['stripeItemPrice']);

			ASP_Debug_Logger::log( 'Checking price consistency.' );
			$calculated_button_key = md5( htmlspecialchars_decode( $item_name ) . $reported_price );

			if ( $button_key !== $calculated_button_key ) {
				$this->ipn_completed( 'Button Key mismatch. Expected ' . $button_key . ', calculated: ' . $calculated_button_key );
			}
			$trans_name = 'stripe-payments-' . $button_key;
			$trans      = get_transient( $trans_name ); //Read the price for this item from the system.

			if ( empty( $trans ) ) {
				$this->ipn_completed( "Can't check payment validity. Aborting." );
			}

			$item_price = $trans['price'];

			$tax = isset( $trans['tax'] ) ? $trans['tax'] : 0;

			$shipping = isset( $trans['shipping'] ) ? $trans['shipping'] : 0;

			$currency_code = strtoupper( sanitize_text_field( $_POST['currency_code'] ) );

			if ( ! AcceptStripePayments::is_zero_cents( $currency_code ) ) {
				$shipping = $shipping / 100;
			}
		}

		if ( empty( $item_price ) ) { //Custom amount
			$item_price = floatval( $_POST['stripeAmount'] );
		}

		if ( ! is_numeric( $item_price ) ) {
			$this->ipn_completed( 'Invalid item price: ' . $item_price );
		}

		$currencyCodeType = strtolower( $currency_code );

		$stripeToken        = sanitize_text_field( $_POST['stripeToken'] );
		$stripeTokenType    = sanitize_text_field( $_POST['stripeTokenType'] );
		$stripeEmail        = sanitize_email( $_POST['stripeEmail'] );
		$charge_description = sanitize_text_field( $_POST['charge_description'] );

		$orig_item_price = $item_price;

		//check if we have variatons selected for the product
		$variations = array();
		$varApplied = array();
		if ( $got_product_data_from_db && isset( $_POST['stripeVariations'] ) ) {
			// we got variations posted. Let's get variations from product
			$v = new ASPVariations( $prod_id );
			if ( ! empty( $v->variations ) ) {
				//there are variations configured for the product
				$posted_v = $_POST['stripeVariations'];
				foreach ( $posted_v as $grp_id => $var_id ) {
					$var = $v->get_variation( $grp_id, $var_id[0] );
					if ( ! empty( $var ) ) {
						$item_price   = $item_price + $var['price'];
						$variations[] = array( $var['group_name'] . ' - ' . $var['name'], $var['price'] );
						$varApplied[] = $var;
					}
				}
			} else {
				//no variations configured for the product
			}
		}

		//check if we we need to apply coupon
		if ( isset( $prod_id ) && ! empty( $_POST['stripeCoupon'] ) ) {
			$coupon_code = strtoupper( $_POST['stripeCoupon'] );
			ASP_Debug_Logger::log( sprintf( 'Coupon provided "%s"', $coupon_code ) );
			$coupon = AcceptStripePayments_CouponsAdmin::get_coupon( $coupon_code );
			if ( $coupon['valid'] ) {
				if ( ! AcceptStripePayments_CouponsAdmin::is_coupon_allowed_for_product( $coupon['id'], $prod_id ) ) {
					//coupon not allowed for this product
					ASP_Debug_Logger::log( 'Coupon is not allowed for this product' );
					unset( $coupon );
				} else {
					if ( $coupon['discountType'] === 'perc' ) {
						$perc            = AcceptStripePayments::is_zero_cents( $currency_code ) ? 0 : 2;
						$discount_amount = round( $item_price * ( $coupon['discount'] / 100 ), $perc );
					} else {
						$discount_amount = $coupon['discount'];
					}
					ASP_Debug_Logger::log( sprintf( 'Coupon is valid. Discount amount: %s', $discount_amount ) );
					$coupon['discountAmount'] = $discount_amount;
					$item_price               = $item_price - $discount_amount;
				}
			} else {
				ASP_Debug_Logger::log( sprintf( 'Invalid coupon "%s", reason: %s', $coupon_code, $coupon['err_msg'] ) );
				unset( $coupon );
			}
		}

		$amount = $item_price;

		//apply tax if needed
		$tax_amt = AcceptStripePayments::get_tax_amount( $amount, $tax, AcceptStripePayments::is_zero_cents( $currency_code ) );

		$amount = AcceptStripePayments::apply_tax( $amount, $tax, AcceptStripePayments::is_zero_cents( $currency_code ) );

		if ( $item_custom_quantity !== false ) { //custom quantity
			$item_quantity = $item_custom_quantity;
		}

		if ( empty( $item_quantity ) ) {
			$item_quantity = 1;
		}

		$amount = ( $item_quantity !== 'NA' ? ( $amount * $item_quantity ) : $amount );

		//add shipping cost
		$amount = AcceptStripePayments::apply_shipping( $amount, $shipping, AcceptStripePayments::is_zero_cents( $currency_code ) );

		$amount_in_cents = $amount;

		if ( ! AcceptStripePayments::is_zero_cents( $currency_code ) ) {
			$amount_in_cents = $amount_in_cents * 100;
		}

		$opt = get_option( 'AcceptStripePayments-settings' );

		$data                        = array();
		$data['product_id']          = isset( $_POST['stripeProductId'] ) && ! empty( $_POST['stripeProductId'] ) ? intval( $_POST['stripeProductId'] ) : '';
		$data['is_live']             = $asp_class->get_setting( 'is_live' );
		$data['item_name']           = $item_name;
		$data['stripeToken']         = $stripeToken;
		$data['stripeTokenType']     = $stripeTokenType;
		$data['stripeEmail']         = $stripeEmail;
		$data['item_quantity']       = $item_quantity;
		$data['item_price']          = $orig_item_price;
		$data['discount_item_price'] = $item_price;
		$data['paid_amount']         = $amount;
		$data['amount_in_cents']     = $amount_in_cents;
		$data['currency_code']       = $currency_code;
		$data['charge_description']  = $charge_description;
		$data['addonName']           = isset( $_POST['stripeAddonName'] ) ? sanitize_text_field( $_POST['stripeAddonName'] ) : '';
		$data['button_key']          = isset( $button_key ) ? $button_key : '';

		//Coupon
		if ( isset( $coupon ) ) {
			$data['coupon'] = $coupon;
		}

		//Custom Field
		$data['custom_fields'] = array();
		if ( isset( $_POST['stripeCustomField'] ) ) {
			$data['custom_fields'][] = array(
				'name'  => sanitize_text_field($_POST['stripeCustomFieldName']),
				'value' => sanitize_text_field($_POST['stripeCustomField']),
			);
		}
		$data['custom_fields'] = apply_filters( 'asp_process_custom_fields', $data['custom_fields'], $data );

		//Filter so addons can modify applied variations if needed
		$variations = apply_filters( 'asp_filter_variations_display', $variations, $data );

		ob_start();

		ASP_Debug_Logger::log( 'Getting API keys and trying to create a charge.' );

		ASP_Utils::load_stripe_lib();

		if ( $data['is_live'] ) {
			$sec_key = $asp_class->APISecKeyLive;
		} else {
			$sec_key = $asp_class->APISecKeyTest;
		}

		\Stripe\Stripe::setApiKey( $sec_key );

		//let addons process payment if needed
		ASP_Debug_Logger::log( 'Firing pre-payment hook.' );
		$data = apply_filters( 'asp_process_charge', $data );

		if ( empty( $data['charge'] ) && $amount_in_cents == 0 ) {
			//looks like we have zero amount. We won't be really processing the charge as it would result in error,
			//so we just make it look like it was actually processed.
			$data['charge']          = new stdClass();
			$data['charge']->id      = 0;
			$data['charge']->created = time();
		}

		if ( empty( $data['charge'] ) ) {
			ASP_Debug_Logger::log( 'Processing payment.' );

			try {

				$charge_opts = array(
					'amount'      => $amount_in_cents,
					'currency'    => $currencyCodeType,
					'description' => $charge_description,
				);

				//Check if we need to add Receipt Email parameter
				if ( isset( $opt['stripe_receipt_email'] ) && $opt['stripe_receipt_email'] == 1 ) {
					$charge_opts['receipt_email'] = $stripeEmail;
				}

				//Check if we need to add Don't Save Card parameter
				if ( $opt['dont_save_card'] == 1 ) {
					$charge_opts['source'] = $stripeToken;
				} else {

					$customer_data = array(
						'email' => $stripeEmail,
						'card'  => $stripeToken,
					);

					$customer_data = apply_filters( 'asp_customer_data_before_create', $customer_data );

					$customer = \Stripe\Customer::create( $customer_data );

					$charge_opts['customer'] = $customer->id;
				}

				$charge_opts['metadata'] = array();

				//Check if we need to include custom field in metadata
				if ( ! empty( $data['custom_fields'] ) ) {
					$cfStr = '';
					foreach ( $data['custom_fields'] as $cf ) {
						$cfStr .= $cf['name'] . ': ' . $cf['value'] . ' | ';
					}
					$cfStr = rtrim( $cfStr, ' | ' );
					//trim the string as metadata value cannot exceed 500 chars
					$cfStr                                    = substr( $cfStr, 0, 499 );
					$charge_opts['metadata']['Custom Fields'] = $cfStr;
				}

				//Check if we need to include variations data into metadata
				if ( ! empty( $variations ) ) {
					$varStr = '';
					foreach ( $variations as $variation ) {
						$varStr .= '[' . $variation[0] . '], ';
					}
					$varStr = rtrim( $varStr, ', ' );
					//trim the string as metadata value cannot exceed 500 chars
					$varStr                                = substr( $varStr, 0, 499 );
					$charge_opts['metadata']['Variations'] = $varStr;
				}

				//Shipping address data (if any)
				$shipping_address         = '';
				$shipping_address        .= isset( $_POST['stripeShippingName'] ) ? sanitize_text_field($_POST['stripeShippingName']) . "\n" : '';
				$shipping_address        .= isset( $_POST['stripeShippingAddressLine1'] ) ? sanitize_text_field($_POST['stripeShippingAddressLine1']) . "\n" : '';
				$shipping_address        .= isset( $_POST['stripeShippingAddressApt'] ) ? sanitize_text_field($_POST['stripeShippingAddressApt']) . "\n" : '';
				$shipping_address        .= isset( $_POST['stripeShippingAddressZip'] ) ? sanitize_text_field($_POST['stripeShippingAddressZip']) . "\n" : '';
				$shipping_address        .= isset( $_POST['stripeShippingAddressCity'] ) ? sanitize_text_field($_POST['stripeShippingAddressCity']) . "\n" : '';
				$shipping_address        .= isset( $_POST['stripeShippingAddressState'] ) ? sanitize_text_field($_POST['stripeShippingAddressState']) . "\n" : '';
				$shipping_address        .= isset( $_POST['stripeShippingAddressCountry'] ) ? sanitize_text_field($_POST['stripeShippingAddressCountry']) . "\n" : '';
				$data['shipping_address'] = $shipping_address;

				if ( ! empty( $shipping_address ) ) {
					//add shipping address to metadata
					$shipping_address                            = str_replace( "\n", ', ', $shipping_address );
					$shipping_address                            = rtrim( $shipping_address, ', ' );
					$charge_opts['metadata']['Shipping Address'] = $shipping_address;
				}

				$data['charge'] = \Stripe\Charge::create( $charge_opts );
			} catch ( Exception $e ) {
				//If the charge fails (payment unsuccessful), this code will get triggered.
				if ( ! empty( $data['charge']->failure_code ) ) {
					$err_msg = $data['charge']->failure_code . ': ' . $data['charge']->failure_message;
				} else {
					$err_msg = $e->getMessage();
				}
				$this->ipn_completed( $err_msg );
			}
		}

		//Grab the charge ID and set it as the transaction ID.
		$txn_id = $data['charge']->id; //$charge->balance_transaction;
		//Core transaction data
		$data['txn_id'] = $txn_id; //The Stripe charge ID

		$post_data = $data;

		//Billing address data (if any)
		$billing_address  = '';
		$billing_address .= isset( $_POST['stripeBillingName'] ) ? sanitize_text_field($_POST['stripeBillingName']) . "\n" : '';
		$billing_address .= isset( $_POST['stripeBillingAddressLine1'] ) ? sanitize_text_field($_POST['stripeBillingAddressLine1']) . "\n" : '';
		$billing_address .= isset( $_POST['stripeBillingAddressApt'] ) ? sanitize_text_field($_POST['stripeBillingAddressApt']) . "\n" : '';
		$billing_address .= isset( $_POST['stripeBillingAddressZip'] ) ? sanitize_text_field($_POST['stripeBillingAddressZip']) . "\n" : '';
		$billing_address .= isset( $_POST['stripeBillingAddressCity'] ) ? sanitize_text_field($_POST['stripeBillingAddressCity']) . "\n" : '';
		$billing_address .= isset( $_POST['stripeBillingAddressState'] ) ? sanitize_text_field($_POST['stripeBillingAddressState']) . "\n" : '';
		$billing_address .= isset( $_POST['stripeBillingAddressCountry'] ) ? sanitize_text_field($_POST['stripeBillingAddressCountry']) . "\n" : '';

		if ( empty( $billing_address ) && ( isset( $data['product_id'] ) && get_post_meta( $data['product_id'], 'asp_product_collect_billing_addr', true ) ) ) {
			//let's try to fetch billing address from payment data
			$billing_address           .= ! empty( $data['charge']->source->name ) ? $data['charge']->source->name . "\n" : '';
			$_POST['stripeBillingName'] = ! empty( $billing_address ) ? $billing_address : null;
			$billing_address           .= ! empty( $data['charge']->source->address_line1 ) ? $data['charge']->source->address_line1 . "\n" : '';
			$billing_address           .= ! empty( $data['charge']->source->address_line2 ) ? $data['charge']->source->address_line2 . "\n" : '';
			$billing_address           .= ! empty( $data['charge']->source->address_zip ) ? $data['charge']->source->address_zip . "\n" : '';
			$billing_address           .= ! empty( $data['charge']->source->address_city ) ? $data['charge']->source->address_city . "\n" : '';
			$billing_address           .= ! empty( $data['charge']->source->address_state ) ? $data['charge']->source->address_state . "\n" : '';
			$billing_address           .= ! empty( $data['charge']->source->address_country ) ? $data['charge']->source->address_country . "\n" : '';
		}

		$post_data['billing_address'] = $billing_address;

		//get customer name
		$name = isset( $_POST['stripeBillingName'] ) ? sanitize_text_field( $_POST['stripeBillingName'] ) : '';
		if ( empty( $name ) && ! empty( $data['charge']->source->name ) ) {
			$name = $data['charge']->source->name;
		}
		$post_data['customer_name'] = $name;

		$purchase_date = date( 'Y-m-d H:i:s', $data['charge']->created );
		$purchase_date = get_date_from_gmt( $purchase_date, get_option( 'date_format' ) . ', ' . get_option( 'time_format' ) );

		$post_data['purchase_date'] = $purchase_date;

		$post_data['additional_items'] = array();

		//check if we need to add variations
		if ( ! empty( $variations ) ) {
			foreach ( $variations as $variation ) {
				$post_data['additional_items'][ $variation[0] ] = $variation[1];
			}
		}

		//check if we need to increase redeem coupon count
		if ( isset( $coupon ) && $coupon['valid'] ) {
			$curr_redeem_cnt = get_post_meta( $coupon['id'], 'asp_coupon_red_count', true );
			$curr_redeem_cnt++;
			update_post_meta( $coupon['id'], 'asp_coupon_red_count', $curr_redeem_cnt );
			if ( isset( $data['is_trial'] ) ) {
				//trial Subscription
				$coupon['discountAmount']    = 0;
				$data['discount_item_price'] = 0;
			}
			$post_data['coupon'] = $coupon;
			$post_data['additional_items'][ sprintf( __( 'Coupon "%s"', 'stripe-payments' ), $coupon['code'] ) ] = floatval( '-' . $coupon['discountAmount'] );
			$post_data['additional_items'][ __( 'Subtotal', 'stripe-payments' ) ]                                = $data['discount_item_price'];
		}

		if ( isset( $tax ) && ! empty( $tax ) ) {
			$taxStr = apply_filters( 'asp_customize_text_msg', __( 'Tax', 'stripe-payments' ), 'tax_str' );
			$post_data['additional_items'][ ucfirst( $taxStr ) ] = $tax_amt;
			$post_data['tax_perc']                               = $tax;
			$post_data['tax']                                    = $tax_amt;
		}

		if ( isset( $shipping ) && ! empty( $shipping ) ) {
			$shipStr = apply_filters( 'asp_customize_text_msg', __( 'Shipping', 'stripe-payments' ), 'shipping_str' );
			$post_data['additional_items'][ ucfirst( $shipStr ) ] = $shipping;
			$post_data['shipping']                                = $shipping;
		}

		//Insert the order data to the custom post
		$dont_create_order = $asp_class->get_setting( 'dont_create_order' );
		if ( ! $dont_create_order ) {
			$order = new ASP_Order_Item();
			$order->create( empty( $data['product_id'] ) ? 0 : $data['product_id'] );
			$order_post_id = $order->update_legacy( $post_data, $data['charge'] );
			$order->change_status( 'paid' );
			$post_data['order_post_id'] = $order_post_id;
		}

		// handle download item url
		$item_url              = apply_filters( 'asp_item_url_process', $item_url, $post_data );
		$item_url              = base64_decode( $item_url );
		$post_data['item_url'] = $item_url;

		if ( ! empty( $varApplied ) ) {
			//process variations URLs if needed
			foreach ( $varApplied as $key => $var ) {
				if ( ! empty( $var['url'] ) ) {
					$var                = apply_filters( 'asp_variation_url_process', $var, $post_data );
					$varApplied[ $key ] = $var;
				}
			}
		}

		//add variations to the resulting array
		$post_data['var_applied'] = $varApplied;

		ASP_Debug_Logger::log( 'Firing post-payment hooks.' );

		//Action hook with the checkout post data parameters.
		do_action( 'asp_stripe_payment_completed', $post_data, $data['charge'] );

		//insert payment data into order info
		if ( isset( $order_post_id ) ) {
			update_post_meta( $order_post_id, 'order_data', $post_data );
			update_post_meta( $order_post_id, 'charge_data', $data['charge'] );
		}

		//Action hook with the order object.
		if ( ! $dont_create_order ) {
			do_action( 'AcceptStripePayments_payment_completed', $order, $data['charge'] );
		}

		if ( ! empty( $data['product_id'] ) ) {
			//check if we need to deal with stock
			if ( get_post_meta( $data['product_id'], 'asp_product_enable_stock', true ) ) {
				$stock_items = intval( get_post_meta( $data['product_id'], 'asp_product_stock_items', true ) );
				$stock_items = $stock_items - $data['item_quantity'];
				if ( $stock_items < 0 ) {
					$stock_items = 0;
				}
				update_post_meta( $data['product_id'], 'asp_product_stock_items', $stock_items );
				$data['stock_items'] = $stock_items;
			}
		}

		//Let's handle email sending stuff
		if ( isset( $opt['send_emails_to_buyer'] ) ) {
			if ( $opt['send_emails_to_buyer'] ) {
				$from = $opt['from_email_address'];
				$to   = $post_data['stripeEmail'];
				$subj = $opt['buyer_email_subject'];
				$body = asp_apply_dynamic_tags_on_email_body( $opt['buyer_email_body'], $post_data );

				$subj = apply_filters( 'asp_buyer_email_subject', $subj, $post_data );
				$body = apply_filters( 'asp_buyer_email_body', $body, $post_data );
				$from = apply_filters( 'asp_buyer_email_from', $from, $post_data );

				$headers = array();
				if ( ! empty( $opt['buyer_email_type'] ) && $opt['buyer_email_type'] === 'html' ) {
					$headers[] = 'Content-Type: text/html; charset=UTF-8';
					$body      = nl2br( $body );
				}
				$headers[] = 'From: ' . $from;

				wp_mail( $to, $subj, $body, $headers );
				ASP_Debug_Logger::log( 'Notification email sent to buyer: ' . $to . ', From email address used: ' . $from );
			}
		}
		if ( isset( $opt['send_emails_to_seller'] ) ) {
			if ( $opt['send_emails_to_seller'] ) {
				$from = $opt['from_email_address'];
				$to   = $opt['seller_notification_email'];
				$subj = $opt['seller_email_subject'];
				$body = asp_apply_dynamic_tags_on_email_body( $opt['seller_email_body'], $post_data, true );

				$subj = apply_filters( 'asp_seller_email_subject', $subj, $post_data );
				$body = apply_filters( 'asp_seller_email_body', $body, $post_data );
				$from = apply_filters( 'asp_seller_email_from', $from, $post_data );

				$headers = array();
				if ( ! empty( $opt['seller_email_type'] ) && $opt['seller_email_type'] === 'html' ) {
					$headers[] = 'Content-Type: text/html; charset=UTF-8';
					$body      = nl2br( $body );
				}
				$headers[] = 'From: ' . $from;

				wp_mail( $to, $subj, $body, $headers );
				ASP_Debug_Logger::log( 'Notification email sent to seller: ' . $to . ', From email address used: ' . $from );
			}
		}

		$post_data['charge_date_raw'] = $data['charge']->created;

		$charge_date = date( 'Y-m-d H:i:s', $data['charge']->created );
		$charge_date = get_date_from_gmt( $charge_date, get_option( 'date_format' ) . ', ' . get_option( 'time_format' ) );

		$post_data['charge_date'] = $charge_date;

		$this->sess->set_transient_data( 'asp_data', $post_data );

		$this->ipn_completed();
	}
}

AcceptStripePayments_Process_IPN::get_instance();

function asp_apply_dynamic_tags_on_email_body( $body, $post, $seller_email = false ) {

	$product_details  = __( 'Product Name', 'stripe-payments' ) . ': {item_name}' . "\n";
	$product_details .= __( 'Quantity', 'stripe-payments' ) . ': {item_quantity}' . "\n";
	$product_details .= __( 'Item Price', 'stripe-payments' ) . ': {item_price_curr}' . "\n";
	//check if there are any additional items available like tax and shipping cost
	$product_details .= AcceptStripePayments::gen_additional_items( $post );
	$product_details .= '--------------------------------' . "\n";
	$product_details .= __( 'Total Amount', 'stripe-payments' ) . ': {purchase_amt_curr}' . "\n";
	$varUrls          = array();
	// check if we have variations applied with download links
	if ( ! empty( $post['var_applied'] ) ) {
		foreach ( $post['var_applied'] as $var ) {
			if ( ! empty( $var['url'] ) ) {
				$varUrls[] = $var['url'];
			}
		}
	}
	$download_str = '';
	if ( ! empty( $post['item_url'] ) ) {
		$download_str = "\n\n" . __( 'Download link', 'stripe-payments' ) . ': ' . $post['item_url'];
	}
	if ( ! empty( $varUrls ) ) {
		//show variations download link(s)
		//those links will replace the one set for the product
		if ( count( $varUrls ) === 1 ) {
			$download_str = __( 'Download link', 'stripe-payments' ) . ': ';
		} else {
			$download_str = __( 'Download links', 'stripe-payments' ) . ":\n";
		}
		foreach ( $varUrls as $url ) {
			$download_str .= $url . "\n";
		}
	}

	$product_details .= rtrim( $download_str, "\n" );

	//Add link to order info if this is email to the seller
	if ( $seller_email && isset( $post['order_post_id'] ) ) {
		$product_details .= "\n\n" . __( 'Order Info: ', 'stripe-payments' ) . admin_url( 'post.php?post=' . $post['order_post_id'] . '&action=edit' );
	}

	$post['product_details'] = $product_details;

	$custom_field = '';
	if ( isset( $post['custom_fields'] ) ) {
		foreach ( $post['custom_fields'] as $cf ) {
			$custom_field .= $cf['name'] . ': ' . $cf['value'] . "\r\n";
		}
		$custom_field = rtrim( $custom_field, "\r\n" );
	}

	$curr = $post['currency_code'];

	$currencies = AcceptStripePayments::get_currencies();
	if ( isset( $currencies[ $curr ] ) ) {
		$curr_sym = $currencies[ $curr ][1];
	} else {
		$curr_sym = '';
	}

	$item_price = AcceptStripePayments::formatted_price( $post['item_price'], false );

	$item_price_curr         = AcceptStripePayments::formatted_price( $post['item_price'], $post['currency_code'] );
	$post['item_price_curr'] = $item_price_curr;

	$purchase_amt = AcceptStripePayments::formatted_price( $post['paid_amount'], false );

	$purchase_amt_curr         = AcceptStripePayments::formatted_price( $post['paid_amount'], $post['currency_code'] );
	$post['purchase_amt_curr'] = $purchase_amt_curr;

	$tax      = 0;
	$tax_amt  = 0;
	$shipping = 0;

	if ( isset( $post['tax_perc'] ) && ! empty( $post['tax_perc'] ) ) {
		$tax = $post['tax_perc'] . '%';
	}
	if ( isset( $post['tax'] ) && ! empty( $post['tax'] ) ) {
		$tax_amt = AcceptStripePayments::formatted_price( $post['tax'], $post['currency_code'] );
	}
	if ( isset( $post['shipping'] ) && ! empty( $post['shipping'] ) ) {
		$shipping = AcceptStripePayments::formatted_price( $post['shipping'], $post['currency_code'] );
	}

	if ( isset( $post['charge']->object ) && 'charge' !== $post['charge']->object ) {
		//this is most likely subs product
		$ipn = ASP_Process_IPN_NG::get_instance();
		if ( isset( $ipn->p_data ) ) {
			$obj = $ipn->p_data->get_obj();

			if ( isset( $obj->latest_invoice )
			&& isset( $obj->latest_invoice->charge )
			&& is_object( $obj->latest_invoice->charge ) ) {
				$post['charge'] = $obj->latest_invoice->charge;
			}
		}
	}

	$pm_type    = '';
	$card_brand = '';
	$card_last4 = '';

	if ( isset( $post['charge']->object ) && 'charge' === $post['charge']->object ) {

		$pm_type = $post['charge']->payment_method_details->type;

		if ( isset( $post['charge']->payment_method_details->card ) ) {
			$card_brand = $post['charge']->payment_method_details->card->brand;
			$card_last4 = $post['charge']->payment_method_details->card->last4;
		}
	}

	$tags = array(
		'{item_name}',
		'{item_short_desc}',
		'{item_quantity}',
		'{item_url}',
		'{payer_email}',
		'{customer_name}',
		'{transaction_id}',
		'{item_price}',
		'{item_price_curr}',
		'{purchase_amt}',
		'{purchase_amt_curr}',
		'{tax}',
		'{tax_amt}',
		'{shipping_amt}',
		'{currency}',
		'{currency_code}',
		'{purchase_date}',
		'{shipping_address}',
		'{billing_address}',
		'{custom_field}',
		'{coupon_code}',
		'{card_brand}',
		'{card_last_4}',
		'{payment_method}',
	);
	$vals = array(
		$post['item_name'],
		$post['charge_description'],
		$post['item_quantity'],
		! empty( $post['item_url'] ) ? $post['item_url'] : '',
		$post['stripeEmail'],
		$post['customer_name'],
		$post['txn_id'],
		$item_price,
		$post['item_price_curr'],
		$purchase_amt,
		$post['purchase_amt_curr'],
		$tax,
		$tax_amt,
		$shipping,
		$curr_sym,
		$curr,
		$post['purchase_date'],
		isset( $post['shipping_address'] ) ? $post['shipping_address'] : '',
		isset( $post['billing_address'] ) ? $post['billing_address'] : '',
		$custom_field,
		! empty( $post['coupon_code'] ) ? $post['coupon_code'] : '',
		$card_brand,
		$card_last4,
		$pm_type,
	);

	//let's combine tags and vals into one array so we can apply filters on it
	$tags_vals = array(
		'tags' => $tags,
		'vals' => $vals,
	);
	$tags_vals = apply_filters( 'asp_email_body_tags_vals_before_replace', $tags_vals, $post );
	$tags      = $tags_vals['tags'];
	$vals      = $tags_vals['vals'];

	$product_details = str_replace( $tags, $vals, $product_details );
	$tags[]          = '{product_details}';
	$vals[]          = $product_details;

	$body = stripslashes( str_replace( $tags, $vals, $body ) );

	//let's apply filters for email body

	$body = apply_filters( 'asp_email_body_after_replace', $body );

	//make tags and vals available for checkout results page by storing those in inner session
	if ( ! $seller_email ) {
		$sess = ASP_Session::get_instance();
		$sess->set_transient_data( 'asp_checkout_data_tags', $tags );
		$sess->set_transient_data( 'asp_checkout_data_vals', $vals );
	}

	return $body;
}
