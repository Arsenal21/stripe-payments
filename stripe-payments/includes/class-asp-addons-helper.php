<?php

class ASP_Addons_Helper {

	public $addon = null;
	public $section;
	public $ASPAdmin;

	public function __construct( $addon ) {
		$this->addon = $addon;
	}

	public function init_tasks() {
		add_action( 'init', array( $this, 'load_text_domain' ) );
		if ( is_admin() ) {
			$this->add_settings_link();
			$this->check_updates();
		}
	}

	public function log( $msg, $success = true ) {
		if ( method_exists( 'ASP_Debug_Logger', 'log' ) ) {
			ASP_Debug_Logger::log( $msg, $success, $this->addon->ADDON_SHORT_NAME );
		}
	}

	public function check_updates() {
		$lib_path = WP_ASP_PLUGIN_PATH . 'includes/plugin-update-checker/plugin-update-checker.php';
		if ( file_exists( $lib_path ) ) {
			if ( ! class_exists( 'Puc_v4_Factory' ) ) {
				require_once $lib_path;
			}
			$my_update_checker = Puc_v4_Factory::buildUpdateChecker(
				'https://s-plugins.com:8080/?action=get_metadata&slug=' . $this->addon->SLUG,
				$this->addon->file,
				$this->addon->SLUG
			);
		}
	}

	public function check_ver() {
		if ( version_compare( WP_ASP_PLUGIN_VERSION, $this->addon->MIN_ASP_VER ) < 0 ) {
			add_action( 'admin_notices', array( $this, 'display_min_version_error' ) );
			return false;
		}
		return true;
	}

	public function add_settings_link() {
		add_filter( 'plugin_action_links', array( $this, 'settings_link' ), 10, 2 );
	}

	public function load_text_domain() {
		if ( ! empty( $this->addon->file ) && ! empty( $this->addon->textdomain ) ) {
			load_plugin_textdomain( $this->addon->textdomain, false, dirname( plugin_basename( $this->addon->file ) ) . '/languages/' );
		}
	}

	public function settings_link( $links, $file ) {
		if ( $file === plugin_basename( $this->addon->file ) ) {
			$settings_link = sprintf( '<a href="edit.php?post_type=' . ASPMain::$products_slug . '&page=stripe-payments-settings#%s">%s</a>', $this->addon->SETTINGS_TAB_NAME, __( 'Settings', 'stripe-payments' ) );
			array_unshift( $links, $settings_link );
		}
		return $links;
	}

	public function display_min_version_error() {
		$class   = 'notice notice-error';
		$message = sprintf( __( '%1$s requires Stripe Payments plugin minimum version to be %2$s (you have version %3$s installed). Please update Stripe Payments plugin.', 'stripe-payments' ), $this->addon->ADDON_FULL_NAME, $this->addon->MIN_ASP_VER, WP_ASP_PLUGIN_VERSION );

		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
	}

	public function add_settings_section( $title, $descr_callback = null ) {
		//$descr_callback added since 1.9.8
		$this->ASPAdmin = AcceptStripePayments_Admin::get_instance();
		add_settings_section( 'AcceptStripePayments-' . $this->addon->SETTINGS_TAB_NAME . '-section', $title, $descr_callback, $this->ASPAdmin->plugin_slug . '-' . $this->addon->SETTINGS_TAB_NAME );
		$this->section = 'AcceptStripePayments-' . $this->addon->SETTINGS_TAB_NAME . '-section';
	}

	public function add_settings_field( $name, $title, $desc, $size = 10 ) {
		$this->ASPAdmin = AcceptStripePayments_Admin::get_instance();
		add_settings_field(
			$name,
			$title,
			array( $this->ASPAdmin, 'settings_field_callback' ),
			$this->ASPAdmin->plugin_slug . '-' . $this->addon->SETTINGS_TAB_NAME,
			$this->section,
			array(
				'field' => $name,
				'size'  => $size,
				'desc'  => $desc,
			)
		);
	}

}

class_alias( 'ASP_Addons_Helper', 'ASPAddonsHelper' );
