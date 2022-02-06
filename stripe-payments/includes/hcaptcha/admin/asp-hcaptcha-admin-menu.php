<?php

class ASP_HCAPTCHA_Admin_Menu {

	public $plugin_slug;
	public $asp_admin;

	public function __construct() {
		$this->asp_admin   = AcceptStripePayments_Admin::get_instance();
		$this->plugin_slug = $this->asp_admin->plugin_slug;

		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'asp-admin-settings-addon-field-display', array( $this, 'field_display' ), 10, 2 );
		add_filter( 'asp_admin_settings_sanitize_field_end', array( $this, 'sanitize_settings' ), 10, 2 );
	}

	public function sanitize_settings( $output, $input ) {
		$output['hcaptcha_invisible'] = isset( $input['hcaptcha_invisible'] ) ? 1 : 0;

		$output['hcaptcha_site_key'] = sanitize_text_field( $input['hcaptcha_site_key'] );

		$output['hcaptcha_secret_key'] = sanitize_text_field( $input['hcaptcha_secret_key'] );

		if ( $output['captcha_type'] === 'hcaptcha' ) {
			if ( empty( $output['hcaptcha_site_key'] ) ) {
				add_settings_error( 'AcceptStripePayments-settings', 'invalid-credentials', __( 'Please enter hCaptcha Site Key.', 'stripe-payments' ) );
			}
			if ( empty( $output['hcaptcha_secret_key'] ) ) {
				add_settings_error( 'AcceptStripePayments-settings', 'invalid-credentials', __( 'Please enter hCaptcha Secret Key.', 'stripe-payments' ) );
			}
		}
		return $output;
	}

	public function field_display( $field, $field_value ) {
		$ret = array();
		switch ( $field ) {
			case 'hcaptcha_invisible':
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
		add_settings_section( 'AcceptStripePayments-hcaptcha-section', __( 'hCaptcha Settings', 'stripe-payments' ), array( $this, 'show_settings_description' ), $this->plugin_slug . '-hcaptcha' );

		add_settings_field(
			'hcaptcha_invisible',
			__( 'Use Invisible hCaptcha Badge', 'stripe-payments' ),
			array( $this->asp_admin, 'settings_field_callback' ),
			$this->plugin_slug . '-hcaptcha',
			'AcceptStripePayments-hcaptcha-section',
			array(
				'field' => 'hcaptcha_invisible',
				'size'  => 10,
				'desc'  => __( 'When enabled, hCaptcha client/server interactions occur in the background, and the user will only be presented with a hCaptcha challenge if that user meets challenge criteria.', 'stripe-payments' ),
			)
		);

		add_settings_field(
			'hcaptcha_site_key',
			__( 'Site Key', 'stripe-payments' ),
			array( $this->asp_admin, 'settings_field_callback' ),
			$this->plugin_slug . '-hcaptcha',
			'AcceptStripePayments-hcaptcha-section',
			array(
				'field' => 'hcaptcha_site_key',
				'size'  => 50,
				'desc'  => __( 'Your hCaptcha Site Key.', 'stripe-payments' ),
			)
		);

		add_settings_field(
			'hcaptcha_secret_key',
			__( 'Secret Key', 'stripe-payments' ),
			array( $this->asp_admin, 'settings_field_callback' ),
			$this->plugin_slug . '-hcaptcha',
			'AcceptStripePayments-hcaptcha-section',
			array(
				'field' => 'hcaptcha_secret_key',
				'size'  => 50,
				'desc'  => __( 'Your hCaptcha Secret Key.', 'stripe-payments' ),
			)
		);
	}

	public function show_settings_description() {
		echo __( '<a href="https://s-plugins.com/stripe-payments-hcaptcha-integration/" target="_blank">Click here</a> to read the documentation to learn how to configure this and get API keys for your website.', 'stripe-payments' );
	}

}
