<?php

class ASPRECAPTCHA_admin_menu {

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
				'<br><br><a href="' . get_admin_url() . 'plugins.php?s=Stripe+Payments+reCAPTCHA+Addon&plugin_status=all' . '">' . esc_html( __( 'Click here to see the plugin on Plugins page.', 'stripe-payments' ) ) . '</a>',
				false
			);
		}

		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'asp-settings-page-after-tabs-menu', array( $this, 'after_tabs_menu' ), 0 );
		add_action( 'asp-settings-page-after-tabs', array( $this, 'after_tabs' ) );
		add_filter( 'asp-admin-settings-addon-field-display', array( $this, 'field_display' ), 10, 2 );
		add_filter( 'apm-admin-settings-sanitize-field', array( $this, 'sanitize_settings' ), 10, 2 );
	}

	public function sanitize_settings( $output, $input ) {
		$output['recaptcha_enabled'] = isset( $input['recaptcha_enabled'] ) ? 1 : 0;

		$output['recaptcha_invisible'] = isset( $input['recaptcha_invisible'] ) ? 1 : 0;

		$output['recaptcha_site_key'] = sanitize_text_field( $input['recaptcha_site_key'] );

		$output['recaptcha_secret_key'] = sanitize_text_field( $input['recaptcha_secret_key'] );

		if ( 1 === $output['recaptcha_enabled'] ) {
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
			case 'recaptcha_enabled':
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

		add_settings_field(
			'recaptcha_enabled',
			__( 'Enable reCAPTCHA', 'stripe-payments' ),
			array( $this->asp_admin, 'settings_field_callback' ),
			$this->plugin_slug . '-recaptcha',
			'AcceptStripePayments-recaptcha-section',
			array(
				'field' => 'recaptcha_enabled',
				'size'  => 10,
				'desc'  => __( 'Enables the reCAPTCHA feature. Enter reCAPTCHA v2 API Keys below.', 'stripe-payments' ),
			)
		);

		add_settings_field(
			'recaptcha_invisible',
			__( 'Use Invisible reCAPTCHA Badge', 'stripe-payments' ),
			array( $this->asp_admin, 'settings_field_callback' ),
			$this->plugin_slug . '-recaptcha',
			'AcceptStripePayments-recaptcha-section',
			array(
				'field' => 'recaptcha_invisible',
				'size'  => 10,
				'desc'  => __( 'If you enable this option then you must enter reCAPTCHA v2 Invisible badge API Keys below.', 'stripe-payments' ),
			)
		);

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

	public function after_tabs_menu() {
		?>
	<a href="#recaptcha" data-tab-name="recaptcha" class="nav-tab"><?php echo __( 'reCAPTCHA', 'stripe-payments' ); ?></a>
		<?php
	}

	public function after_tabs() {
		?>
	<div class="wp-asp-tab-container asp-recaptcha-container" data-tab-name="recaptcha">
		<?php do_settings_sections( $this->plugin_slug . '-recaptcha' ); ?>
	</div>
		<?php
	}

}
