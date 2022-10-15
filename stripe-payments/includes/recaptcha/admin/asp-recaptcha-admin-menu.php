<?php

class ASP_RECAPTCHA_Admin_Menu {

	public $plugin_slug;
	public $asp_admin;

	public function __construct() {
		$this->asp_admin   = AcceptStripePayments_Admin::get_instance();
		$this->plugin_slug = $this->asp_admin->plugin_slug;

		if ( file_exists( WP_PLUGIN_DIR . '/stripe-payments-recaptcha/asp-recaptcha-main.php' ) ) {
			AcceptStripePayments_Admin::add_admin_notice(
				'warning',
				'<b>Stripe Payments:</b> ' .
				__( 'reCaptcha add-on is a part of core plugin now. Please delete "Stripe Payments reCapthca Addon" plugin to prevent potential issues.', 'stripe-payments' ) .
				'<br><br><a href="' . get_admin_url() . 'plugins.php?s=Stripe+Payments+reCAPTCHA+Addon&plugin_status=all">' . esc_html( __( 'Click here to see the plugin on Plugins page.', 'stripe-payments' ) ) . '</a>',
				false
			);
		}

		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'asp-admin-settings-addon-field-display', array( $this, 'field_display' ), 10, 2 );
		add_filter( 'apm-admin-settings-sanitize-field', array( $this, 'sanitize_settings' ), 10, 2 );
	}

	public function sanitize_settings( $output, $input ) {
		//$output['recaptcha_invisible'] = isset( $input['recaptcha_invisible'] ) ? 1 : 0;
		$output['recaptcha_invisible'] = 0;

		$output['recaptcha_site_key'] = sanitize_text_field( $input['recaptcha_site_key'] );

		$output['recaptcha_secret_key'] = sanitize_text_field( $input['recaptcha_secret_key'] );

		if ( $output['captcha_type'] === 'recaptcha' ) {
			if ( empty( $output['recaptcha_site_key'] ) ) {
				add_settings_error( 'AcceptStripePayments-settings', 'invalid-credentials', __( 'Please enter reCaptcha Site Key.', 'stripe-payments' ) );
			}
			if ( empty( $output['recaptcha_secret_key'] ) ) {
				add_settings_error( 'AcceptStripePayments-settings', 'invalid-credentials', __( 'Please enter reCaptcha Secret Key.', 'stripe-payments' ) );
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
		add_settings_section( 'AcceptStripePayments-recaptcha-section', __( 'reCAPTCHA Settings', 'stripe-payments' ), array( $this, 'show_settings_description' ), $this->plugin_slug . '-recaptcha' );

		// add_settings_field(
		// 	'recaptcha_invisible',
		// 	__( 'Use Invisible reCAPTCHA Badge', 'stripe-payments' ),
		// 	array( $this->asp_admin, 'settings_field_callback' ),
		// 	$this->plugin_slug . '-recaptcha',
		// 	'AcceptStripePayments-recaptcha-section',
		// 	array(
		// 		'field' => 'recaptcha_invisible',
		// 		'size'  => 10,
		// 		'desc'  => __( 'It is recommended to use the "I am not a robot" checkbox captcha option for better payment button protection. However, if you want to enable this option then you must enter reCAPTCHA v2 Invisible badge API Keys below.', 'stripe-payments' ),
		// 	)
		// );

		add_settings_field(
			'recaptcha_site_key',
			__( 'Site Key', 'stripe-payments' ),
			array( $this->asp_admin, 'settings_field_callback' ),
			$this->plugin_slug . '-recaptcha',
			'AcceptStripePayments-recaptcha-section',
			array(
				'field' => 'recaptcha_site_key',
				'size'  => 50,
				'desc'  => __( 'Your reCaptcha Site Key.', 'stripe-payments' ),
			)
		);

		add_settings_field(
			'recaptcha_secret_key',
			__( 'Secret Key', 'stripe-payments' ),
			array( $this->asp_admin, 'settings_field_callback' ),
			$this->plugin_slug . '-recaptcha',
			'AcceptStripePayments-recaptcha-section',
			array(
				'field' => 'recaptcha_secret_key',
				'size'  => 50,
				'desc'  => __( 'Your reCaptcha Secret Key.', 'stripe-payments' ),
			)
		);
	}

	public function show_settings_description() {
		echo __( '<a href="https://s-plugins.com/stripe-payments-recaptcha-addon/" target="_blank">Click here</a> to read the documentation to learn how to configure this and get API keys for your website.', 'stripe-payments' );
	}

}
