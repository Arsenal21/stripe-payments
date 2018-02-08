<?php

/**
 * Plugin Name: Stripe Payments
 * Description: Easily accept credit card payments via Stripe payment gateway in WordPress.
 * Version: 1.7.7_testing2
 * Author: Tips and Tricks HQ, wptipsntricks
 * Author URI: https://www.tipsandtricks-hq.com/
 * Plugin URI: https://stripe-plugins.com
 * License: GPLv2 or later
 * Text Domain: stripe-payments
 * Domain Path: /languages
 */
//Slug - asp
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; //Exit if accessed directly
}

define( 'WP_ASP_PLUGIN_VERSION', '1.7.7' );
define( 'WP_ASP_PLUGIN_URL', plugins_url( '', __FILE__ ) );
define( 'WP_ASP_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );

class ASPMain {

    static $products_slug;
    static $posts_processed = array();

    function __construct() {
	ASPMain::$products_slug = 'asp-products';
    }

    static function load_stripe_lib() {
	if ( ! class_exists( '\Stripe\Stripe' ) ) {
	    require_once( plugin_dir_path( __FILE__ ) . 'includes/stripe/init.php' );
	    \Stripe\Stripe::setAppInfo( "Stripe Payments", WP_ASP_PLUGIN_VERSION, "https://wordpress.org/plugins/stripe-payments/" );
	}
    }

    static function log( $msg, $success = true, $addon_name = '', $overwrite = false ) {
	$opts = get_option( 'AcceptStripePayments-settings' );
	if ( ! $opts[ 'debug_log_enable' ] && ! $overwrite ) {
	    return true;
	}
	$log_file = get_option( 'asp_log_file_name' );
	if ( ! $log_file ) {
	    //let's generate new log file name
	    $log_file = uniqid() . '_debug_log.txt';
	    update_option( 'asp_log_file_name', $log_file );
	}

	if ( ! $success ) {
	    $msg = 'FAILURE: ' . $msg;
	}

	if ( ! empty( $addon_name ) ) {
	    $msg = '[' . $addon_name . '] ' . $msg;
	}

	if ( ! file_put_contents( plugin_dir_path( __FILE__ ) . $log_file, $msg . "\r\n", ( ! $overwrite ? FILE_APPEND : 0 ) ) ) {
	    return false;
	}

	return true;
    }

    static function view_log() {
	$log_file = get_option( 'asp_log_file_name' );
	if ( ! file_exists( plugin_dir_path( __FILE__ ) . $log_file ) ) {
	    if ( ASPMain::log( "Stripe Payments debug log file\r\n\r\n" ) === false ) {
		wp_die( 'Can\'t write to log file. Check if plugin directory  (' . plugin_dir_path( __FILE__ ) . ') is writeable.' );
	    };
	}
	$logfile = fopen( plugin_dir_path( __FILE__ ) . $log_file, 'rb' );
	if ( ! $logfile ) {
	    wp_die( 'Can\'t open log file.' );
	}
	header( 'Content-Type: text/plain' );
	fpassthru( $logfile );
	die;
    }

    static function clear_log() {
	if ( ASPMAIN::log( "Stripe Payments debug log file\r\n\r\n", true, '', true ) !== false ) {
	    echo '1';
	} else {
	    echo 'Can\'t clear log - log file is not writeable.';
	}
	wp_die();
    }

}

$ASPMain = new ASPMain();

/* ----------------------------------------------------------------------------*
 * Public-Facing Functionality
 * ---------------------------------------------------------------------------- */
require_once( plugin_dir_path( __FILE__ ) . 'public/class-asp.php' );
require_once( plugin_dir_path( __FILE__ ) . 'public/includes/class-shortcode-asp.php' );
require_once( WP_ASP_PLUGIN_PATH . 'admin/includes/class-products.php' );
require_once( plugin_dir_path( __FILE__ ) . 'admin/includes/class-order.php' );

/*
 * Register hooks that are fired when the plugin is activated or deactivated.
 * When the plugin is deleted, the uninstall.php file is loaded.
 *
 */

register_activation_hook( __FILE__, array( 'AcceptStripePayments', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'AcceptStripePayments', 'deactivate' ) );

/*
 */
add_action( 'plugins_loaded', array( 'AcceptStripePayments', 'get_instance' ) );
add_action( 'plugins_loaded', array( 'AcceptStripePaymentsShortcode', 'get_instance' ) );

/* ----------------------------------------------------------------------------*
 * Dashboard and Administrative Functionality
 * ---------------------------------------------------------------------------- */

/*
 * If you want to include Ajax within the dashboard, change the following
 * conditional to:
 *
 * if ( is_admin() ) {
 *   ...
 * }
 *
 * The code below is intended to to give the lightest footprint possible.
 */
if ( is_admin() ) {

    require_once( plugin_dir_path( __FILE__ ) . 'admin/class-asp-admin.php' );
    add_action( 'plugins_loaded', array( 'AcceptStripePayments_Admin', 'get_instance' ) );
}

/* Add a link to the settings page in the plugins listing page */

function asp_stripe_add_settings_link( $links, $file ) {
    if ( $file == plugin_basename( __FILE__ ) ) {
	$settings_link = '<a href="edit.php?post_type=stripe_order&page=stripe-payments-settings">Settings</a>';
	array_unshift( $links, $settings_link );
    }
    return $links;
}

add_filter( 'plugin_action_links', 'asp_stripe_add_settings_link', 10, 2 );
//check and redirect old Settings page
add_action( 'init', 'asp_init_handler' );

register_activation_hook( __FILE__, 'asp_activation_hook_handler' );

// register custom post type
$ASPProducts	 = ASPProducts::get_instance();
add_action( 'init', array( $ASPProducts, 'register_post_type' ), 0 );
$ASPOrder	 = ASPOrder::get_instance();
add_action( 'init', array( $ASPOrder, 'register_post_type' ), 0 );

if ( session_id() == '' && ! wp_doing_ajax() ) {
    session_start();
}

function asp_activation_hook_handler() {
    $ASPProducts	 = ASPProducts::get_instance();
    $ASPProducts->register_post_type();
    $ASPOrder	 = ASPOrder::get_instance();
    $ASPOrder->register_post_type();
    flush_rewrite_rules();
}

function asp_init_handler() {
    global $pagenow;
    if ( is_admin() ) {
	//check if we need redirect old Settings page
	if ( ($pagenow == "options-general.php" && isset( $_GET[ 'page' ] ) && $_GET[ 'page' ] == 'accept_stripe_payment') ||
	($pagenow == "edit.php" && (isset( $_GET[ 'post_type' ] ) && $_GET[ 'post_type' ] == 'stripe_order') && (isset( $_GET[ 'page' ] ) && $_GET[ 'page' ] == 'stripe-payments-settings') ) ) {
	    //let's redirect old Settings page to new
	    wp_redirect( get_admin_url() . 'edit.php?post_type=' . ASPMain::$products_slug . '&page=stripe-payments-settings', 301 );
	    exit;
	}

	//products meta boxes handler
	//TODO: only require this file if user is on add\edit product page
	require_once(WP_ASP_PLUGIN_PATH . 'admin/includes/class-products-meta-boxes.php');

	//products post save action
	add_action( 'save_post_' . ASPMain::$products_slug, 'asp_save_product_handler', 10, 3 );
    }
}

function asp_save_product_handler( $post_id, $post, $update ) {
    if ( ! isset( $_POST[ 'action' ] ) ) {
	//this is probably not edit or new post creation event
	return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
	return;
    }
    if ( isset( $post_id ) ) {
	update_post_meta( $post_id, 'asp_product_price', sanitize_text_field( $_POST[ 'asp_product_price' ] ) );
	update_post_meta( $post_id, 'asp_product_currency', sanitize_text_field( $_POST[ 'asp_product_currency' ] ) );
	update_post_meta( $post_id, 'asp_product_quantity', sanitize_text_field( $_POST[ 'asp_product_quantity' ] ) );
	update_post_meta( $post_id, 'asp_product_custom_quantity', isset( $_POST[ 'asp_product_custom_quantity' ] ) ? "1" : false  );
	update_post_meta( $post_id, 'asp_product_custom_field', isset( $_POST[ 'asp_product_custom_field' ] ) ? sanitize_text_field( $_POST[ 'asp_product_custom_field' ] ) : "0"  );
	update_post_meta( $post_id, 'asp_product_button_text', sanitize_text_field( $_POST[ 'asp_product_button_text' ] ) );
	update_post_meta( $post_id, 'asp_product_description', sanitize_text_field( $_POST[ 'asp_product_description' ] ) );
	update_post_meta( $post_id, 'asp_product_upload', esc_url( $_POST[ 'asp_product_upload' ] ) );
	update_post_meta( $post_id, 'asp_product_thumbnail', esc_url( $_POST[ 'asp_product_thumbnail' ] ) );
	$shipping_addr = false;
	if ( isset( $_POST[ 'asp_product_collect_shipping_addr' ] ) ) {
	    $shipping_addr = $_POST[ 'asp_product_collect_shipping_addr' ];
	}
	update_post_meta( $post_id, 'asp_product_collect_shipping_addr', $shipping_addr );
	update_post_meta( $post_id, 'asp_product_collect_billing_addr', isset( $_POST[ 'asp_product_collect_billing_addr' ] ) ? "1" : false  );

	do_action( 'asp_save_product_handler', $post_id, $post, $update );
    }
}

add_filter( 'the_content', 'asp_filter_post_type_content' );

function asp_filter_post_type_content( $content ) {
    global $post;
    if ( isset( $post ) ) {
	if ( $post->post_type == ASPMain::$products_slug ) {//Handle the content for product type post
	    return do_shortcode( '[asp_product id="' . $post->ID . '" is_post_tpl="1" in_the_loop="' . +in_the_loop() . '"]' );
	}
    }
    return $content;
}
