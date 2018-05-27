<?php

/**
 * Plugin Name: Stripe Payments
 * Description: Easily accept credit card payments via Stripe payment gateway in WordPress.
 * Version: 1.8.9
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

define( 'WP_ASP_PLUGIN_VERSION', '1.8.9' );
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

}

$ASPMain = new ASPMain();

/* ----------------------------------------------------------------------------*
 * Public-Facing Functionality
 * ---------------------------------------------------------------------------- */
require_once( WP_ASP_PLUGIN_PATH . 'includes/class-debug-logger.php' );
require_once( WP_ASP_PLUGIN_PATH . 'public/class-asp.php' );
require_once( WP_ASP_PLUGIN_PATH . 'public/includes/class-shortcode-asp.php' );
require_once( WP_ASP_PLUGIN_PATH . 'admin/includes/class-products.php' );
require_once( WP_ASP_PLUGIN_PATH . 'admin/includes/class-order.php' );
require_once( WP_ASP_PLUGIN_PATH . 'includes/addons-helper-class.php' );

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
	require_once(WP_ASP_PLUGIN_PATH . 'admin/includes/class-products-meta-boxes.php');
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
