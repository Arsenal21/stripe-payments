<?php

/**
 * Plugin Name: Stripe Payments
 * Description: Easily accept credit card payments via Stripe payment gateway in WordPress.
 * Version: 2.0.12
 * Author: Tips and Tricks HQ, wptipsntricks
 * Author URI: https://www.tipsandtricks-hq.com/
 * Plugin URI: https://s-plugins.com
 * License: GPLv2 or later
 * Text Domain: stripe-payments
 * Domain Path: /languages
 */

use stripepayments\convertKit\subscriber;

//Slug - asp
// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	exit; //Exit if accessed directly
}

define( 'WP_ASP_PLUGIN_VERSION', '2.0.12' );
define( 'WP_ASP_MIN_PHP_VERSION', '5.4' );
define( 'WP_ASP_PLUGIN_URL', plugins_url( '', __FILE__ ) );
define( 'WP_ASP_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_ASP_PLUGIN_FILE', __FILE__ );

class ASPMain {


	public static $products_slug;
	public static $temp_prod_slug;
	public static $posts_processed = array();

	public function __construct() {
		self::$products_slug  = 'asp-products';
		self::$temp_prod_slug = 'asp-products-temp';

		require_once WP_ASP_PLUGIN_PATH . 'includes/class-asp-utils.php';
		require_once WP_ASP_PLUGIN_PATH . 'includes/class-debug-logger.php';
		require_once WP_ASP_PLUGIN_PATH . 'public/class-asp.php';
		require_once WP_ASP_PLUGIN_PATH . 'admin/includes/class-products.php';
		require_once WP_ASP_PLUGIN_PATH . 'admin/includes/class-coupons.php';
		require_once WP_ASP_PLUGIN_PATH . 'admin/includes/class-order.php';
		require_once WP_ASP_PLUGIN_PATH . 'admin/views/blocks.php';
		require_once WP_ASP_PLUGIN_PATH . 'includes/addons-helper-class.php';
		require_once WP_ASP_PLUGIN_PATH . 'includes/class-asp-product-item.php';
		require_once WP_ASP_PLUGIN_PATH . 'includes/class-asp-payment-data.php';
		require_once WP_ASP_PLUGIN_PATH . 'admin/includes/class-variations.php';

		register_activation_hook( __FILE__, array( 'AcceptStripePayments', 'activate' ) );
		register_deactivation_hook( __FILE__, array( 'AcceptStripePayments', 'deactivate' ) );

		add_action( 'plugins_loaded', array( 'AcceptStripePayments', 'get_instance' ) );

		if ( is_admin() ) {
			require_once WP_ASP_PLUGIN_PATH . 'admin/class-asp-admin.php';
			add_action( 'plugins_loaded', array( 'AcceptStripePayments_Admin', 'get_instance' ) );
		}

		require_once WP_ASP_PLUGIN_PATH . 'includes/session-handler-class.php';
		require_once WP_ASP_PLUGIN_PATH . 'public/includes/class-shortcode-asp.php';
		require_once WP_ASP_PLUGIN_PATH . 'public/includes/class-asp-shortcode-ng.php';

		add_action( 'init', array( 'AcceptStripePaymentsShortcode', 'get_instance' ) );
		add_action( 'init', array( 'ASP_Shortcode_NG', 'get_instance' ) );

		add_action( 'init', array( $this, 'init_handler' ), 0 );

		// register custom post type
		$asp_products = ASPProducts::get_instance();
		add_action( 'init', array( $asp_products, 'register_post_type' ), 0 );
		$asp_order = ASPOrder::get_instance();
		add_action( 'init', array( $asp_order, 'register_post_type' ), 0 );
	}

	public function init_handler() {
		// hook to change product slug
		self::$products_slug = apply_filters( 'asp_change_products_slug', self::$products_slug );
	}

	public static function load_stripe_lib() {
		if ( ! class_exists( '\Stripe\Stripe' ) ) {
			require_once WP_ASP_PLUGIN_PATH . 'includes/stripe/init.php';
			\Stripe\Stripe::setAppInfo( 'Stripe Payments', WP_ASP_PLUGIN_VERSION, 'https://wordpress.org/plugins/stripe-payments/', 'pp_partner_Fvas9OJ0jQ2oNQ' );
		} else {
			$declared = new \ReflectionClass( '\Stripe\Stripe' );
			$path     = $declared->getFileName();
			$own_path = WP_ASP_PLUGIN_PATH . 'includes/stripe/lib/Stripe.php';
			if ( strtolower( $path ) !== strtolower( $own_path ) ) {
				// Stripe library is loaded from other location
				// Let's only log one warning per 6 hours in order to not flood the log
				$lib_warning_last_logged_time = get_option( 'asp_lib_warning_last_logged_time' );
				$time                         = time();
				if ( $time - ( 60 * 60 * 6 ) > $lib_warning_last_logged_time ) {
					$opts = get_option( 'AcceptStripePayments-settings' );
					if ( $opts['debug_log_enable'] ) {
						ASP_Debug_Logger::log( sprintf( "WARNING: Stripe PHP library conflict! Another Stripe PHP SDK library is being used. Please disable plugin or theme that provides it as it can cause issues during payment process.\r\nLibrary path: %s", $path ) );
						update_option( 'asp_lib_warning_last_logged_time', $time );
					}
				}
			}
		}
	}
}

new ASPMain();
