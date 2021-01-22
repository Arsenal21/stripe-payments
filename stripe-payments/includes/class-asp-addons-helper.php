<?php

class ASP_Addons_Helper {

	public $addon = null;
	public $section;
	public $asp_admin;

	protected $addons = array(
		array( 'stripe-payments-country-autodetect', 'stripe-payments-country-autodetect/asp-country-autodetect-main.php', 'stripe-payments-country-autodetect', '' ),
		array( 'stripe-payments-custom-messages', 'stripe-payments-custom-messages/asp-custmsg-main.php', 'stripe-payments-custom-messages', 'stripe-custom-messages-addon.png' ),
	);

	private $icons_path = WP_ASP_PLUGIN_URL . '/admin/assets/images/';
	private $icons      = array();

	public function __construct( $addon ) {
		$this->addon = $addon;

		if ( is_admin() ) {
			$this->asp_admin = AcceptStripePayments_Admin::get_instance();

			foreach ( $this->addons as $addon ) {
				if ( ! empty( $addon[3] ) ) {
					$this->icons[ $addon[2] ] = $this->icons_path . $addon[3];
					add_filter( 'puc_request_info_result-' . $addon[2], array( $this, 'set_icon' ) );
				}
			}
		}
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

	public function set_icon( $data ) {
		if ( isset( $this->icons[ $data->slug ] ) ) {
			$data->icons = array( 'default' => $this->icons[ $data->slug ] );
		}
		return $data;
	}

	public function check_updates() {
		if ( ! is_admin() ) {
			return;
		}
		if ( class_exists( 'ASP_Addons_Update_Checker' ) ) {
			if ( empty( $this->addon->SLUG ) || empty( $this->addon->file ) ) {
				return;
			}
			ASP_Addons_Update_Checker::check_updates( $this->addon->SLUG, $this->addon->file );
		} else {
			// let's display admin notice to install Addons Update Checker (if the message is not yet dismissed)
			$notice_dismissed = get_option( 'asp_dismiss_auc_msg' );
			if ( ! empty( $notice_dismissed ) ) {
				return;
			}
			$admin_url   = get_admin_url();
			$dismiss_url = add_query_arg( 'asp_dismiss_auc_msg', '1', $admin_url );
			$dismiss_url = wp_nonce_url( $dismiss_url, 'asp_dismiss_auc_msg' );
			$dismiss_msg = '<div class="asp_dismiss_notice_update_checker"><a style="text-decoration: none; border-bottom: 1px dashed;font-size:0.9em;" href="' . $dismiss_url . '">' . __( 'Don\'t show this message again', 'stripe-payments' ) . '</a></div>';
			AcceptStripePayments_Admin::add_admin_notice(
				'warning',
				// translators: %s is replaced by a link to plugin page
				sprintf( __( 'Please install the <a target="_blank" href="%s">Stripe Payments Addons Update Checker</a> plugin to keep your addons upto date.', 'stripe-payments' ), 'https://s-plugins.com/update-checker-plugin-for-the-addons/' ) .
				$dismiss_msg,
				false
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
		if ( plugin_basename( $this->addon->file ) === $file && ! empty( $this->addon->SETTINGS_TAB_NAME ) ) {
			$settings_link = sprintf( '<a href="edit.php?post_type=' . ASPMain::$products_slug . '&page=stripe-payments-settings#%s">%s</a>', $this->addon->SETTINGS_TAB_NAME, __( 'Settings', 'stripe-payments' ) );
			array_unshift( $links, $settings_link );
		}
		return $links;
	}

	public function display_min_version_error() {
		$class = 'notice notice-error';
		// translators: %1$s - plugin name, %2$s - min core plugin version, %3$s - installed core plugin version
		$message = sprintf( __( '%1$s requires Stripe Payments plugin minimum version to be %2$s (you have version %3$s installed). Please update Stripe Payments plugin.', 'stripe-payments' ), $this->addon->ADDON_FULL_NAME, $this->addon->MIN_ASP_VER, WP_ASP_PLUGIN_VERSION );

		printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
	}

	public function add_settings_section( $title, $descr_callback = null ) {
		//$descr_callback added since 1.9.8
		add_settings_section( 'AcceptStripePayments-' . $this->addon->SETTINGS_TAB_NAME . '-section', $title, $descr_callback, $this->asp_admin->plugin_slug . '-' . $this->addon->SETTINGS_TAB_NAME );
		$this->section = 'AcceptStripePayments-' . $this->addon->SETTINGS_TAB_NAME . '-section';
	}

	public function add_settings_field( $name, $title, $desc, $size = 10 ) {
		$this->asp_admin = AcceptStripePayments_Admin::get_instance();
		add_settings_field(
			$name,
			$title,
			array( $this->asp_admin, 'settings_field_callback' ),
			$this->asp_admin->plugin_slug . '-' . $this->addon->SETTINGS_TAB_NAME,
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
