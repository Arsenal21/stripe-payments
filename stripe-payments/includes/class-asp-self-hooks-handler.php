<?php

class ASP_Self_Hooks_Handler {

	private $main;

	public function __construct() {
		$this->main = AcceptStripePayments::get_instance();
		add_action( 'asp_ng_product_mode_keys', array( $this, 'ng_product_mode_keys_handler' ) );

		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ), 0 );

		add_filter( 'asp_ng_before_pi_create_update', array( $this, 'pi_update' ) );

		add_filter( 'asp_ng_available_currencies', array( $this, 'filter_available_currencies' ) );

		add_action( 'asp_ng_before_token_request', array( $this, 'check_token' ), 100 );
		add_action( 'asp_ng_before_token_request', array( $this, 'check_rate_limit' ), 101 );

		add_action( 'asp_ng_before_token_request', array( $this, 'tax_variations' ) );

		add_filter( 'asp_ng_pp_data_ready', array( $this, 'tax_variations_addon' ), 100, 2 );
	}

	public function plugins_loaded() {
		//WP eMember integration
		if ( function_exists( 'wp_emember_install' ) ) {
			add_action( 'asp_stripe_payment_completed', array( $this, 'handle_eMember_signup' ), 10, 2 );
		}
		//Simple Membership integration
		if ( defined( 'SIMPLE_WP_MEMBERSHIP_VER' ) ) {
			add_action( 'asp_stripe_payment_completed', array( $this, 'handle_swpm_signup' ), 10, 2 );
		}
		//WP PDF Stamper integration
		if ( function_exists( 'pdf_stamper_stamp_internal_file' ) ) {
			add_action( 'asp_ng_payment_completed', array( $this, 'handle_wp_pdf_stamper' ), 1000, 2 );
		}
	}

	public function pi_update( $pi_params ) {
		$product_id = filter_input( INPUT_POST, 'product_id', FILTER_SANITIZE_NUMBER_INT );

		$plan_id = get_post_meta( $product_id, 'asp_sub_plan_id', true );
		if ( ! empty( $plan_id ) ) {
			//ignoring option for Subscription product
			return $pi_params;
		}

		$auth_only = get_post_meta( $product_id, 'asp_product_authorize_only', true );
		if ( $auth_only ) {
			$pi_params['capture_method'] = 'manual';
		}
		return $pi_params;
	}

	public function filter_available_currencies( $curr_arr ) {
		$allowed_curr = $this->main->get_setting( 'allowed_currencies' );
		$allowed_curr = empty( $allowed_curr ) ? array() : json_decode( html_entity_decode( $allowed_curr ), true );

		if ( empty( $allowed_curr ) || empty( array_diff_key( $curr_arr, $allowed_curr ) ) ) {
			return $curr_arr;
		}

		foreach ( $curr_arr as $key => $value ) {
			if ( ! isset( $allowed_curr[ $key ] ) ) {
				unset( $curr_arr[ $key ] );
			}
		}

		return $curr_arr;
	}

	public function ng_product_mode_keys_handler( $product_id ) {
		if ( empty( $product_id ) ) {
			return;
		}

		$product = get_post( $product_id );

		if ( ! $product ) {
			return;
		}

		$prod = new ASP_Product_Item( $product_id );

		$type = $prod->get_type();

		if ( 'subscription' !== $type ) {
			$use_other_acc = get_post_meta( $product_id, 'asp_use_other_stripe_acc', true );
			if ( ! empty( $use_other_acc ) ) {
				$this->main->APIPubKeyTest = get_post_meta( $product_id, 'asp_stripe_test_pub_key', true );
				$this->main->APIPubKeyLive = get_post_meta( $product_id, 'asp_stripe_live_pub_key', true );
				$this->main->APISecKeyTest = get_post_meta( $product_id, 'asp_stripe_test_sec_key', true );
				$this->main->APISecKeyLive = get_post_meta( $product_id, 'asp_stripe_live_sec_key', true );
				$this->main->APIPubKey     = $this->main->APIPubKeyLive;
				$this->main->APISecKey     = $this->main->APISecKeyLive;
			}
		}

		$plan_id = get_post_meta( $product_id, 'asp_sub_plan_id', true );

		if ( ! empty( $plan_id ) ) {
			//check if Subs addon enabled
			if ( class_exists( 'ASPSUB_main' ) ) {
				$asp_sub = ASPSUB_main::get_instance();
				$plan    = $asp_sub->get_plan_data( $plan_id );
				if ( ! ( $plan ) || $plan->livemode ) {
					return;
				}
			}
		} else {
			//check if force test mode option set for the product
			$force_test = get_post_meta( $product_id, 'asp_product_force_test_mode', true );
			if ( empty( $force_test ) ) {
				return;
			}
		}

		$this->main->is_live   = false;
		$this->main->APIPubKey = $this->main->APIPubKeyTest;
		$this->main->APISecKey = $this->main->APISecKeyTest;
	}

	public function handle_emember_signup( $data, $charge ) {

		if ( empty( $data['product_id'] ) ) {
			return;
		}

		//let's check if Membership Level is set for this product
		$level_id = get_post_meta( $data['product_id'], 'asp_product_emember_level', true );
		if ( empty( $level_id ) ) {
			return;
		}

		//let's form data required for eMember_handle_subsc_signup_stand_alone function and call it
		$first_name = '';
		$last_name  = '';
		if ( ! empty( $data['customer_name'] ) ) {
			// let's try to create first name and last name from full name
			$parts      = explode( ' ', $data['customer_name'] );
			$last_name  = array_pop( $parts );
			$first_name = implode( ' ', $parts );
		}
		$addr_street  = isset( $_POST['stripeBillingAddressLine1'] ) ? sanitize_text_field( $_POST['stripeBillingAddressLine1'] ) : '';
		$addr_zip     = isset( $_POST['stripeBillingAddressZip'] ) ? sanitize_text_field( $_POST['stripeBillingAddressZip'] ) : '';
		$addr_city    = isset( $_POST['stripeBillingAddressCity'] ) ? sanitize_text_field( $_POST['stripeBillingAddressCity'] ) : '';
		$addr_state   = isset( $_POST['stripeBillingAddressState'] ) ? sanitize_text_field( $_POST['stripeBillingAddressState'] ) : '';
		$addr_country = isset( $_POST['stripeBillingAddressCountry'] ) ? sanitize_text_field( $_POST['stripeBillingAddressCountry'] ) : '';

		if ( empty( $addr_street ) && ! empty( $charge->source->address_line1 ) ) {
			$addr_street = $charge->source->address_line1;
		}

		if ( empty( $addr_zip ) && ! empty( $charge->source->address_zip ) ) {
			$addr_zip = $charge->source->address_zip;
		}

		if ( empty( $addr_city ) && ! empty( $charge->source->address_city ) ) {
			$addr_city = $charge->source->address_city;
		}

		if ( empty( $addr_state ) && ! empty( $charge->source->address_state ) ) {
			$addr_state = $charge->source->address_state;
		}

		if ( empty( $addr_country ) && ! empty( $charge->source->address_country ) ) {
			$addr_country = $charge->source->address_country;
		}

		//get address from new API payment data
		$ipn = ASP_Process_IPN_NG::get_instance();

		if ( isset( $ipn->p_data ) ) {
			$addr = $ipn->p_data->get_billing_details();
			if ( $addr ) {
				if ( empty( $addr_street ) && ! empty( $addr->line1 ) ) {
					$addr_street = $addr->line1;
				}
				if ( empty( $addr_zip ) && ! empty( $addr->postal_code ) ) {
					$addr_zip = $addr->postal_code;
				}

				if ( empty( $addr_city ) && ! empty( $addr->city ) ) {
					$addr_city = $addr->city;
				}

				if ( empty( $addr_state ) && ! empty( $addr->state ) ) {
					$addr_state = $addr->state;
				}

				if ( empty( $addr_country ) && ! empty( $addr->country ) ) {
					$addr_country = $addr->country;
				}
			}
		}

		if ( ! empty( $addr_country ) ) {
			//convert country code to country name
			$countries = ASP_Utils::get_countries_untranslated();
			if ( isset( $countries[ $addr_country ] ) ) {
				$addr_country = $countries[ $addr_country ];
			}
		}

		$ipn_data = array(
			'payer_email'     => $data['stripeEmail'],
			'first_name'      => $first_name,
			'last_name'       => $last_name,
			'txn_id'          => $data['txn_id'],
			'address_street'  => $addr_street,
			'address_city'    => $addr_city,
			'address_state'   => $addr_state,
			'address_zip'     => $addr_zip,
			'address_country' => $addr_country,
		);

		ASP_Debug_Logger::log( 'Calling eMember_handle_subsc_signup_stand_alone' );

		$emember_id = '';
		if ( class_exists( 'Emember_Auth' ) ) {
			//Check if the user is logged in as a member.
			$emember_auth = Emember_Auth::getInstance();
			$emember_id   = $emember_auth->getUserInfo( 'member_id' );
		}

		if ( defined( 'WP_EMEMBER_PATH' ) ) {
			require_once WP_EMEMBER_PATH . 'ipn/eMember_handle_subsc_ipn_stand_alone.php';
			eMember_handle_subsc_signup_stand_alone( $ipn_data, $level_id, $data['txn_id'], $emember_id );
		}

	}

	public function handle_swpm_signup( $data, $charge ) {

		if ( empty( $data['product_id'] ) ) {
			return;
		}

		//let's check if Membership Level is set for this product
		$level_id = get_post_meta( $data['product_id'], 'asp_product_swpm_level', true );
		if ( empty( $level_id ) ) {
			return;
		}

		//let's form data required for eMember_handle_subsc_signup_stand_alone function and call it
		$first_name = '';
		$last_name  = '';
		if ( ! empty( $data['customer_name'] ) ) {
			// let's try to create first name and last name from full name
			$parts      = explode( ' ', $data['customer_name'] );
			$last_name  = array_pop( $parts );
			$first_name = implode( ' ', $parts );
		}
		$addr_street  = isset( $_POST['stripeBillingAddressLine1'] ) ? sanitize_text_field( $_POST['stripeBillingAddressLine1'] ) : '';
		$addr_zip     = isset( $_POST['stripeBillingAddressZip'] ) ? sanitize_text_field( $_POST['stripeBillingAddressZip'] ) : '';
		$addr_city    = isset( $_POST['stripeBillingAddressCity'] ) ? sanitize_text_field( $_POST['stripeBillingAddressCity'] ) : '';
		$addr_state   = isset( $_POST['stripeBillingAddressState'] ) ? sanitize_text_field( $_POST['stripeBillingAddressState'] ) : '';
		$addr_country = isset( $_POST['stripeBillingAddressCountry'] ) ? sanitize_text_field( $_POST['stripeBillingAddressCountry'] ) : '';

		if ( empty( $addr_street ) && ! empty( $charge->source->address_line1 ) ) {
			$addr_street = $charge->source->address_line1;
		}

		if ( empty( $addr_zip ) && ! empty( $charge->source->address_zip ) ) {
			$addr_zip = $charge->source->address_zip;
		}

		if ( empty( $addr_city ) && ! empty( $charge->source->address_city ) ) {
			$addr_city = $charge->source->address_city;
		}

		if ( empty( $addr_state ) && ! empty( $charge->source->address_state ) ) {
			$addr_state = $charge->source->address_state;
		}

		if ( empty( $addr_country ) && ! empty( $charge->source->address_country ) ) {
			$addr_country = $charge->source->address_country;
		}

		//get address from new API payment data
		$ipn = ASP_Process_IPN_NG::get_instance();

		if ( isset( $ipn->p_data ) ) {
			$addr = $ipn->p_data->get_billing_details();
			if ( $addr ) {
				if ( empty( $addr_street ) && ! empty( $addr->line1 ) ) {
					$addr_street = $addr->line1;
				}
				if ( empty( $addr_zip ) && ! empty( $addr->postal_code ) ) {
					$addr_zip = $addr->postal_code;
				}

				if ( empty( $addr_city ) && ! empty( $addr->city ) ) {
					$addr_city = $addr->city;
				}

				if ( empty( $addr_state ) && ! empty( $addr->state ) ) {
					$addr_state = $addr->state;
				}

				if ( empty( $addr_country ) && ! empty( $addr->country ) ) {
					$addr_country = $addr->country;
				}
			}
		}

		if ( ! empty( $addr_country ) ) {
			//convert country code to country name
			$countries = ASP_Utils::get_countries_untranslated();
			if ( isset( $countries[ $addr_country ] ) ) {
				$addr_country = $countries[ $addr_country ];
			}
		}

		$ipn_data = array(
			'payer_email'     => $data['stripeEmail'],
			'first_name'      => $first_name,
			'last_name'       => $last_name,
			'txn_id'          => $data['txn_id'],
			'address_street'  => $addr_street,
			'address_city'    => $addr_city,
			'address_state'   => $addr_state,
			'address_zip'     => $addr_zip,
			'address_country' => $addr_country,
		);

		ASP_Debug_Logger::log( 'Calling swpm_handle_subsc_signup_stand_alone' );

		$swpm_id = '';
		if ( SwpmMemberUtils::is_member_logged_in() ) {
			$swpm_id = SwpmMemberUtils::get_logged_in_members_id();
		}

		if ( defined( 'SIMPLE_WP_MEMBERSHIP_PATH' ) ) {
			require SIMPLE_WP_MEMBERSHIP_PATH . 'ipn/swpm_handle_subsc_ipn.php';
			swpm_handle_subsc_signup_stand_alone( $ipn_data, $level_id, $data['txn_id'], $swpm_id );
		}

	}

	public function handle_wp_pdf_stamper( $data, $prod_id ) {
		$pdf_stamper_enabled = get_post_meta( $prod_id, 'asp_product_pdf_stamper_enabled', true );
		$item_url            = get_post_meta( $prod_id, 'asp_product_upload', true );

		if ( $pdf_stamper_enabled && ! empty( $item_url ) && strpos( strtolower( basename( $item_url ) ), '.pdf' ) !== false ) {
			$ipn = ASP_Process_IPN_NG::get_instance();

			$billing_addr = $ipn->p_data->get_billing_details();

			$b_addr = $billing_addr->line1 . ', ' . $billing_addr->city . ', ' . ( isset( $billing_addr->state ) ? $billing_addr->state . ', ' : '' ) . $billing_addr->postal_code . ', ' . $billing_addr->country;

			$additional_params = array(
				'product_name'   => $data['item_name'],
				'transaction_id' => $data['txn_id'],
			);

			$res = pdf_stamper_stamp_internal_file( $item_url, $data['customer_name'], $data['stripeEmail'], '', $b_addr, '', '', '', '', $additional_params );

			if ( empty( $res ) ) {
				return $data;
			}

			$res_arr = explode( " \n", $res );

			if ( isset( $res_arr[0] ) && 'Success!' === $res_arr[0] ) {
				$data['item_url'] = $res_arr[1];
			}
		}
		return $data;
	}

	public function check_token( $item ) {
		$disable_token_check = $this->main->get_setting( 'disable_security_token_check' );

		if ( $disable_token_check ) {
			return;
		}

		$button_key = $item->get_button_key();
		$token      = filter_input( INPUT_POST, 'token', FILTER_SANITIZE_STRING );
		$g_token    = ASP_Utils::get_visitor_token( $button_key );
		if ( empty( $token ) || $g_token !== $token ) {
			$out            = array();
			$out['success'] = false;
			$out['err']     = __( 'Invalid security token.', 'stripe-payments' );
			wp_send_json( $out );
		}
	}

	public function check_rate_limit() {
		//disabled for now
		return;

		$limit = 5;

		$tdata = get_transient( 'asp_ng_throttle_data' );
		$tdata = empty( $tdata ) ? array() : $tdata;

		array_push( $tdata, time() );

		foreach ( $tdata as $key => $req ) {
			if ( $req < ( time() + 60 ) ) {
				unset( $tdata[ $key ] );
			}
		}

		set_transient( 'asp_ng_throttle_data', $tdata );

		if ( count( $tdata ) > $limit ) {
			$out            = array();
			$out['success'] = false;
			$out['err']     = __( 'Rate limit exceeded. Please try again later.', 'stripe-payments' );
			wp_send_json( $out );
		}

	}

	public function tax_variations( $item ) {

		$tax_variations_arr = $item->get_meta( 'asp_product_tax_variations' );

		if ( empty( $tax_variations_arr ) ) {
			return $item;
		}

		$this->tax_variations_arr = $tax_variations_arr;

		$this->item = $item;

		add_filter( 'asp_ng_before_customer_create_update', array( $this, 'tax_variations_check_apply' ), 10, 2 );

		add_filter( 'asp_ng_sub_confirm_token_customer_opts', array( $this, 'tax_variations_check_apply' ) );

		return $this->item;

	}

	public function tax_variations_check_apply( $cust_opts ) {
		ASP_Debug_Logger::log_array_data( $cust_opts );

		if ( empty( $cust_opts['address'] ) ) {
			return $cust_opts;
		}

		if ( empty( $this->tax_variations_arr ) ) {
			return $cust_opts;
		}

		$new_tax = ASP_Utils::get_tax_variations_tax(
			$this->tax_variations_arr,
			empty( $cust_opts['address']['country'] ) ? '' : $cust_opts['address']['country'],
			empty( $cust_opts['address']['state'] ) ? '' : $cust_opts['address']['state'],
			empty( $cust_opts['address']['city'] ) ? '' : $cust_opts['address']['city']
		);

		if ( false !== $new_tax ) {
			$this->item->set_tax( $new_tax );
		}

		return $cust_opts;
	}

	public function tax_variations_addon( $data, $prod_id ) {
		if ( empty( $data['tax_variations'] ) ) {
			return $data;
		}
		$addon            = array(
			'name'    => 'aspTaxVariations',
			'handler' => 'aspTaxVariationsNG',
		);
		$data['addons'][] = $addon;

		return $data;
	}

}

new ASP_Self_Hooks_Handler();
