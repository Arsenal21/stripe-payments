<?php

/**
 * Plugin Name: Stripe Payments
 * Description: Easily accept credit card payments via Stripe payment gateway in WordPress.
 * Version: 1.9.25
 * Author: Tips and Tricks HQ, wptipsntricks
 * Author URI: https://www.tipsandtricks-hq.com/
 * Plugin URI: https://s-plugins.com
 * License: GPLv2 or later
 * Text Domain: stripe-payments
 * Domain Path: /languages
 */
//Slug - asp
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
    exit; //Exit if accessed directly
}

define( 'WP_ASP_PLUGIN_VERSION', '1.9.25' );
define( 'WP_ASP_MIN_PHP_VERSION', '5.4' );
define( 'WP_ASP_PLUGIN_URL', plugins_url( '', __FILE__ ) );
define( 'WP_ASP_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_ASP_PLUGIN_FILE', __FILE__ );

class ASPMain {

    static $products_slug;
    static $posts_processed = array();

    function __construct() {
	ASPMain::$products_slug = 'asp-products';

	require_once( WP_ASP_PLUGIN_PATH . 'includes/class-debug-logger.php' );
	require_once( WP_ASP_PLUGIN_PATH . 'public/class-asp.php' );
	require_once( WP_ASP_PLUGIN_PATH . 'admin/includes/class-products.php' );
	require_once( WP_ASP_PLUGIN_PATH . 'admin/includes/class-coupons.php' );
	require_once( WP_ASP_PLUGIN_PATH . 'admin/includes/class-order.php' );
	require_once( WP_ASP_PLUGIN_PATH . 'admin/views/blocks.php' );
	require_once( WP_ASP_PLUGIN_PATH . 'includes/addons-helper-class.php' );
	require_once(WP_ASP_PLUGIN_PATH . '/admin/includes/class-variations.php');

	register_activation_hook( __FILE__, array( 'AcceptStripePayments', 'activate' ) );
	register_deactivation_hook( __FILE__, array( 'AcceptStripePayments', 'deactivate' ) );

	add_action( 'plugins_loaded', array( 'AcceptStripePayments', 'get_instance' ) );

	if ( is_admin() ) {
	    require_once( WP_ASP_PLUGIN_PATH . 'admin/class-asp-admin.php' );
	    add_action( 'plugins_loaded', array( 'AcceptStripePayments_Admin', 'get_instance' ) );
	}

	require_once( WP_ASP_PLUGIN_PATH . 'includes/session-handler-class.php');
	require_once( WP_ASP_PLUGIN_PATH . 'public/includes/class-shortcode-asp.php' );

	add_action( 'init', array( 'AcceptStripePaymentsShortcode', 'get_instance' ) );

	// register custom post type
	$ASPProducts	 = ASPProducts::get_instance();
	add_action( 'init', array( $ASPProducts, 'register_post_type' ), 0 );
	$ASPOrder	 = ASPOrder::get_instance();
	add_action( 'init', array( $ASPOrder, 'register_post_type' ), 0 );
    }

    static function load_stripe_lib() {
	if ( ! class_exists( '\Stripe\Stripe' ) ) {
	    require_once( WP_ASP_PLUGIN_PATH . 'includes/stripe/init.php' );
	    \Stripe\Stripe::setAppInfo( "Stripe Payments", WP_ASP_PLUGIN_VERSION, "https://wordpress.org/plugins/stripe-payments/" );
	}
    }

}

new ASPMain();
