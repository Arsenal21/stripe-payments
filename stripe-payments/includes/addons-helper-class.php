<?php

class ASPAddonsHelper {

    var $addon = null;

    function __construct( $addon ) {
	$this->addon = $addon;
    }

    function init_tasks() {
	if ( is_admin() ) {
	    $this->add_settings_link();
	    $this->check_updates();
	}
    }

    function log( $msg, $success = true ) {
	if ( method_exists( 'ASP_Debug_Logger', 'log' ) ) {
	    ASP_Debug_Logger::log( $msg, $success, $this->addon->ADDON_SHORT_NAME );
	}
    }

    function check_updates() {
	$lib_path = plugin_dir_path( $this->addon->file ) . 'lib/plugin-update-checker/plugin-update-checker.php';
	if ( file_exists( $lib_path ) ) {
	    include_once($lib_path);
	    $myUpdateChecker = Puc_v4_Factory::buildUpdateChecker(
	    'https://stripe-plugins.com/updates/?action=get_metadata&slug=' . $this->addon->SLUG, $this->addon->file, $this->addon->SLUG );
	}
    }

    function check_ver() {
	if ( version_compare( WP_ASP_PLUGIN_VERSION, $this->addon->MIN_ASP_VER ) < 0 ) {
	    add_action( 'admin_notices', array( $this, 'display_min_version_error' ) );
	    return false;
	}
	return true;
    }

    function add_settings_link() {
	add_filter( 'plugin_action_links', array( $this, 'settings_link' ), 10, 2 );
    }

    function settings_link( $links, $file ) {
	if ( $file === plugin_basename( $this->addon->file ) ) {
	    $settings_link = sprintf( '<a href="edit.php?post_type=asp-products&page=stripe-payments-settings#%s">%s</a>', $this->addon->SETTINGS_TAB_NAME, __( 'Settings', 'stripe-payments' ) );
	    array_unshift( $links, $settings_link );
	}
	return $links;
    }

    function display_min_version_error() {
	$class	 = 'notice notice-error';
	$message = sprintf( __( '%s requires Stripe Payments plugin minimum version to be %s (you have version %s installed). Please update Stripe Payments plugin.', 'stripe-payments' ), $this->addon->ADDON_FULL_NAME, $this->addon->MIN_ASP_VER, WP_ASP_PLUGIN_VERSION );

	printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( $class ), esc_html( $message ) );
    }

}
