<?php

class ASPRECAPTCHA_admin_menu {

	public $plugin_slug;
	public $asp_admin;

	public function __construct() {
		$this->asp_admin   = AcceptStripePayments_Admin::get_instance();
		$this->plugin_slug = $this->asp_admin->plugin_slug;

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
				add_settings_error( 'AcceptStripePayments-settings', 'invalid-credentials', __( 'Please enter reCaptcha Site Key.', 'asp-recaptcha' ) );
			}
			if ( empty( $output['recaptcha_secret_key'] ) ) {
				add_settings_error( 'AcceptStripePayments-settings', 'invalid-credentials', __( 'Please enter reCaptcha Secret Key.', 'asp-recaptcha' ) );
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
		add_settings_section( 'AcceptStripePayments-recaptcha-section', __( 'reCAPTCHA Settings', 'asp-recaptcha' ), array( $this, 'show_settings_description' ), $this->plugin_slug . '-recaptcha' );

		add_settings_field(
			'recaptcha_enabled',
			__( 'Enable reCAPTCHA', 'asp-recaptcha' ),
			array( $this->asp_admin, 'settings_field_callback' ),
			$this->plugin_slug . '-recaptcha',
			'AcceptStripePayments-recaptcha-section',
			array(
				'field' => 'recaptcha_enabled',
				'size'  => 10,
				'desc'  => __( 'Enables the reCAPTCHA feature. Enter reCAPTCHA v2 API Keys below.', 'asp-recaptcha' ),
			)
		);

		add_settings_field(
			'recaptcha_invisible',
			__( 'Use Invisible reCAPTCHA Badge', 'asp-recaptcha' ),
			array( $this->asp_admin, 'settings_field_callback' ),
			$this->plugin_slug . '-recaptcha',
			'AcceptStripePayments-recaptcha-section',
			array(
				'field' => 'recaptcha_invisible',
				'size'  => 10,
				'desc'  => __( 'If you enable this option then you must enter reCAPTCHA v2 Invisible badge API Keys below.', 'asp-recaptcha' ),
			)
		);

		add_settings_field(
			'recaptcha_site_key',
			__( 'Site Key', 'asp-recaptcha' ),
			array( $this->asp_admin, 'settings_field_callback' ),
			$this->plugin_slug . '-recaptcha',
			'AcceptStripePayments-recaptcha-section',
			array(
				'field' => 'recaptcha_site_key',
				'size'  => 50,
				'desc'  => __( 'Your reCaptcha Site Key.', 'asp-recaptcha' ),
			)
		);

		add_settings_field(
			'recaptcha_secret_key',
			__( 'Secret Key', 'asp-recaptcha' ),
			array( $this->asp_admin, 'settings_field_callback' ),
			$this->plugin_slug . '-recaptcha',
			'AcceptStripePayments-recaptcha-section',
			array(
				'field' => 'recaptcha_secret_key',
				'size'  => 50,
				'desc'  => __( 'Your reCaptcha Secret Key.', 'asp-recaptcha' ),
			)
		);
	}

	public function show_settings_description() {
		echo __( '<a href="https://s-plugins.com/stripe-payments-recaptcha-addon/" target="_blank">Click here</a> to read the documentation to learn how to configure this addon and get API keys for your website.', 'asp-recaptcha' );
	}

	public function after_tabs_menu() {
		?>
	<a href="#recaptcha" data-tab-name="recaptcha" class="nav-tab"><?php echo __( 'reCAPTCHA', 'asp-recaptcha' ); ?></a>
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
