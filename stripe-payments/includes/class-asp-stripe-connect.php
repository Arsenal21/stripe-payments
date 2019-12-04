<?php

class ASP_Stripe_Connect {
	private $asp_main;
	public function __construct() {
		$this->asp_main = AcceptStripePayments::get_instance();

		//Handle Connect reply
		add_action( 'wp_ajax_asp_handle_connect_reply', array( $this, 'handle_connect_reply' ) );
		add_action( 'wp_ajax_nopriv_asp_handle_connect_reply', array( $this, 'handle_connect_reply' ) );

		//Handle Disconnect request
		add_action( 'wp_ajax_asp_req_disconnect_data', array( $this, 'handle_disconnect_data' ) );
		add_action( 'wp_ajax_nopriv_asp_req_disconnect_data', array( $this, 'handle_disconnect_data' ) );

		//Handle Disconnect confirm
		add_action( 'wp_ajax_asp_confirm_disconnect_data', array( $this, 'handle_disconnect_data_confirm' ) );
		add_action( 'wp_ajax_nopriv_asp_confirm_disconnect_data', array( $this, 'handle_disconnect_data_confirm' ) );
	}

	public function handle_disconnect_data_confirm() {
		$nonce = FILTER_INPUT( INPUT_POST, 'nonce', FILTER_SANITIZE_STRING );
		if ( ! wp_verify_nonce( $nonce, 'asp_connect_disconnect_confirm_nonce' ) ) {
			AcceptStripePayments_Admin::add_admin_notice( 'error', __( 'Error occurred during Stripe account connection.', 'stripe-payments' ) );
			wp_send_json(
				array(
					'success'     => false,
					'err_msg'     => __( 'Nonce check failed', 'stripe-payments' ),
					'redirect_to' => $redirect_to,
				)
			);
		}
		$this->asp_main->set_setting( 'connect', array() );
		$this->asp_main->set_setting( 'api_secret_key_test', '' );
		$this->asp_main->set_setting( 'api_publishable_key_test', '' );

		AcceptStripePayments_Admin::add_admin_notice( 'success', __( 'Your Stripe account has been disconnected.', 'stripe-payments' ) );
		exit;
	}

	public function handle_disconnect_data() {
		$admin_url   = admin_url( 'edit.php' );
		$redirect_to = add_query_arg(
			array(
				'post_type' => ASPMain::$products_slug,
				'page'      => 'stripe-payments-settings#general',
			),
			$admin_url
		);

		$nonce = FILTER_INPUT( INPUT_POST, 'nonce', FILTER_SANITIZE_STRING );
		if ( ! wp_verify_nonce( $nonce, 'asp_connect_disconnect_nonce' ) ) {
			AcceptStripePayments_Admin::add_admin_notice( 'error', __( 'Error occurred during Stripe account connection.', 'stripe-payments' ) );
			wp_send_json(
				array(
					'success'     => false,
					'err_msg'     => __( 'Nonce check failed', 'stripe-payments' ),
					'redirect_to' => $redirect_to,
				)
			);
		}

		$out = array();

		$connect_opts = $this->asp_main->get_setting( 'connect', array() );

		if ( isset( $connect_opts['livemode'] ) ) {
			$confirm_nonce = ASP_Utils::create_nonce( 'asp_connect_disconnect_confirm_nonce' );
			$out           = array(
				'success'       => true,
				'user_id'       => $connect_opts['stripe_user_id'],
				'confirm_nonce' => $confirm_nonce,
				'redirect_to'   => $redirect_to,
			);
		} else {
			//not connected
		}

		wp_send_json( $out );
	}

	public function handle_connect_reply() {
		$admin_url   = admin_url( 'edit.php' );
		$redirect_to = add_query_arg(
			array(
				'post_type' => ASPMain::$products_slug,
				'page'      => 'stripe-payments-settings#general',
			),
			$admin_url
		);

		$nonce = FILTER_INPUT( INPUT_POST, 'nonce', FILTER_SANITIZE_STRING );
		if ( ! wp_verify_nonce( $nonce, 'asp_handle_connect_reply' ) ) {
			AcceptStripePayments_Admin::add_admin_notice( 'error', __( 'Error occurred during Stripe account connection.', 'stripe-payments' ) );
			wp_send_json(
				array(
					'success'     => false,
					'err_msg'     => __( 'Nonce check failed', 'stripe-payments' ),
					'redirect_to' => $redirect_to,
				)
			);
		}

		$connect_opts = $this->asp_main->get_setting( 'connect', array() );

		$access_token                 = filter_input( INPUT_POST, 'sec_key', FILTER_SANITIZE_STRING );
		$connect_opts['access_token'] = $access_token;

		$publishable_key                 = filter_input( INPUT_POST, 'pub_key', FILTER_SANITIZE_STRING );
		$connect_opts['publishable_key'] = $publishable_key;

		$stripe_user_id                 = filter_input( INPUT_POST, 'user_id', FILTER_SANITIZE_STRING );
		$connect_opts['stripe_user_id'] = $stripe_user_id;

		$refresh_token                 = filter_input( INPUT_POST, 'refresh_token', FILTER_SANITIZE_STRING );
		$connect_opts['refresh_token'] = $refresh_token;

		$livemode                 = filter_input( INPUT_POST, 'livemode', FILTER_SANITIZE_NUMBER_INT );
		$livemode                 = empty( $livemode ) ? false : true;
		$connect_opts['livemode'] = $livemode;

		if ( false === $livemode ) {
			//we got test credentials only
			$this->asp_main->set_setting( 'api_secret_key_test', $access_token );
			$this->asp_main->set_setting( 'api_publishable_key_test', $publishable_key );
		} else {
			//we got live and test credentials
			$this->asp_main->set_setting( 'api_secret_key', $access_token );
			$this->asp_main->set_setting( 'api_publishable_key', $publishable_key );

			$api_secret_key_test = filter_input( INPUT_POST, 'sec_key_test', FILTER_SANITIZE_STRING );
			$api_pub_key_test    = filter_input( INPUT_POST, 'pub_key_test', FILTER_SANITIZE_STRING );

			$this->asp_main->set_setting( 'api_secret_key_test', $api_secret_key_test );
			$this->asp_main->set_setting( 'api_publishable_key_test', $api_pub_key_test );
		}

		$this->asp_main->set_setting( 'connect', $connect_opts );

		AcceptStripePayments_Admin::add_admin_notice( 'success', __( 'Your Stripe account has been successfully connected. You can accept payments now.', 'stripe-payments' ) );
		wp_send_json(
			array(
				'success'     => true,
				'redirect_to' => $redirect_to,
			)
		);
	}
}

new ASP_Stripe_Connect();
