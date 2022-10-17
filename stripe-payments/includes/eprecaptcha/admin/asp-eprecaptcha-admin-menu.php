<?php

class ASP_EPRECAPTCHA_Admin_Menu {

	public $plugin_slug;
	public $asp_admin;

	public function __construct() {
		$this->asp_admin   = AcceptStripePayments_Admin::get_instance();
		$this->plugin_slug = $this->asp_admin->plugin_slug;

		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'asp-admin-settings-addon-field-display', array( $this, 'field_display' ), 10, 2 );
		add_filter( 'apm-admin-settings-sanitize-field', array( $this, 'sanitize_settings' ), 10, 2 );
	}

	public function sanitize_settings( $output, $input ) {
		
		$output['eprecaptcha_site_key'] = sanitize_text_field( $input['eprecaptcha_site_key'] );

		$output['eprecaptcha_api_key'] = sanitize_text_field( $input['eprecaptcha_api_key'] );

		$output['eprecaptcha_project_id'] = sanitize_text_field( $input['eprecaptcha_project_id'] );

		if ( $output['captcha_type'] === 'eprecaptcha' ) {
			if ( empty( $output['eprecaptcha_site_key'] ) ) {
				add_settings_error( 'AcceptStripePayments-settings', 'invalid-credentials', __( 'Please enter Enterprise reCaptcha Site Key.', 'stripe-payments' ) );
			}
			if ( empty( $output['eprecaptcha_api_key'] ) ) {
				add_settings_error( 'AcceptStripePayments-settings', 'invalid-credentials', __( 'Please enter Enterprise reCaptcha Api Key.', 'stripe-payments' ) );
			}
			if ( empty( $output['eprecaptcha_project_id'] ) ) {
				add_settings_error( 'AcceptStripePayments-settings', 'invalid-credentials', __( 'Please enter Enterprise reCaptcha Project Id.', 'stripe-payments' ) );
			}
		}
		return $output;
	}

	public function field_display( $field, $field_value ) {
		$ret = array();
		
		switch ( $field ) {
			case 'recaptcha_invisible':
				$ret['field']      = 'checkbox';
				$ret['field_name'] = $field;
				break;
			default:
				break;
		}
		if ( ! empty( $ret ) ) {
			return $ret;
		} else {
			return $field;
		}
	}

	public function register_settings() {
		add_settings_section( 'AcceptStripePayments-eprecaptcha-section', __( 'Enterprise reCAPTCHA Settings', 'stripe-payments' ), array( $this, 'show_settings_description' ), $this->plugin_slug . '-eprecaptcha' );

		add_settings_field(
			'eprecaptcha_site_key',
			__( 'Site Key', 'stripe-payments' ),
			array( $this->asp_admin, 'settings_field_callback' ),
			$this->plugin_slug . '-eprecaptcha',
			'AcceptStripePayments-eprecaptcha-section',
			array(
				'field' => 'eprecaptcha_site_key',
				'size'  => 63,
				'desc'  => __( 'Your Enterprise reCaptcha Site Key.', 'stripe-payments' ),
			)
		);

		add_settings_field(
			'eprecaptcha_api_key',
			__( 'API Key', 'stripe-payments' ),
			array( $this->asp_admin, 'settings_field_callback' ),
			$this->plugin_slug . '-eprecaptcha',
			'AcceptStripePayments-eprecaptcha-section',
			array(
				'field' => 'eprecaptcha_api_key',
				'size'  => 63,
				'desc'  => __( 'Your Enterprise reCaptcha API Key.', 'stripe-payments' ),
			)
		);

		add_settings_field(
			'eprecaptcha_project_id',
			__( 'Project Id', 'stripe-payments' ),
			array( $this->asp_admin, 'settings_field_callback' ),
			$this->plugin_slug . '-eprecaptcha',
			'AcceptStripePayments-eprecaptcha-section',
			array(
				'field' => 'eprecaptcha_project_id',
				'size'  => 30,
				'desc'  => __( 'Your Enterprise reCaptcha Project Id.', 'stripe-payments' ),
			)
		);		
	}

	public function show_settings_description() {
		echo __( '<a href="https://s-plugins.com/stripe-payments-enterprise-recaptcha-feature/" target="_blank">Click here</a> to read the documentation to learn how to configure this and get API keys for your website.', 'stripe-payments' );
	}

}
