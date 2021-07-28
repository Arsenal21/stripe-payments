<?php

/**
 * Plugin Name: Accept Stripe Payments
 * Description: Easily accept credit card payments via Stripe payment gateway in WordPress.
 * Version: 2.0.47
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

define( 'WP_ASP_PLUGIN_VERSION', '2.0.47' );
define( 'WP_ASP_MIN_PHP_VERSION', '5.6' );
define( 'WP_ASP_PLUGIN_URL', plugins_url( '', __FILE__ ) );
define( 'WP_ASP_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'WP_ASP_PLUGIN_FILE', __FILE__ );

class ASPMain {


	public static $products_slug;
	public static $temp_prod_slug;
	public static $posts_processed = array();
	public static $file;
	public static $stripe_api_ver = '2020-03-02';

	public function __construct() {
		self::$products_slug  = 'asp-products';
		self::$temp_prod_slug = 'asp-products-temp';
		self::$file           = __FILE__;

		require_once WP_ASP_PLUGIN_PATH . 'includes/class-asp-utils.php';
		require_once WP_ASP_PLUGIN_PATH . 'includes/class-asp-debug-logger.php';
		require_once WP_ASP_PLUGIN_PATH . 'includes/class-asp.php';
		require_once WP_ASP_PLUGIN_PATH . 'includes/class-asp-stripe-api.php';
		require_once WP_ASP_PLUGIN_PATH . 'admin/includes/class-asp-admin-products.php';
		require_once WP_ASP_PLUGIN_PATH . 'admin/includes/class-coupons.php';
		require_once WP_ASP_PLUGIN_PATH . 'admin/includes/class-order.php';
		require_once WP_ASP_PLUGIN_PATH . 'admin/views/blocks.php';
		require_once WP_ASP_PLUGIN_PATH . 'includes/class-asp-addons-helper.php';
		require_once WP_ASP_PLUGIN_PATH . 'includes/class-asp-product-item.php';
		require_once WP_ASP_PLUGIN_PATH . 'includes/class-asp-payment-data.php';
		require_once WP_ASP_PLUGIN_PATH . 'includes/class-asp-order-item.php';
		require_once WP_ASP_PLUGIN_PATH . 'admin/includes/class-variations.php';

		register_activation_hook( __FILE__, array( 'AcceptStripePayments', 'activate' ) );
		register_deactivation_hook( __FILE__, array( 'AcceptStripePayments', 'deactivate' ) );

		add_action( 'plugins_loaded', array( 'AcceptStripePayments', 'get_instance' ) );

		if ( is_admin() ) {
			require_once WP_ASP_PLUGIN_PATH . 'admin/class-asp-admin.php';
			add_action( 'plugins_loaded', array( 'AcceptStripePayments_Admin', 'get_instance' ) );
		}

		require_once WP_ASP_PLUGIN_PATH . 'includes/session-handler-class.php';
		require_once WP_ASP_PLUGIN_PATH . 'includes/shortcodes/class-shortcode-asp.php';
		require_once WP_ASP_PLUGIN_PATH . 'includes/shortcodes/class-asp-shortcode-ng.php';

		add_action( 'init', array( $this, 'init_handler' ), 0 );

		// register custom post type
		$asp_products = ASP_Admin_Products::get_instance();
		add_action( 'init', array( $asp_products, 'register_post_type' ), 0 );
		$asp_order = ASPOrder::get_instance();
		add_action( 'init', array( $asp_order, 'register_post_type' ), 0 );

		add_action( 'init', array( 'AcceptStripePaymentsShortcode', 'get_instance' ) );
		add_action( 'init', array( 'ASP_Shortcode_NG', 'get_instance' ) );
	}

	public function init_handler() {
		// hook to change product slug
		self::$products_slug = apply_filters( 'asp_change_products_slug', self::$products_slug );
	}

	/**
	 * Use ASP_Utils::load_stripe_lib() instead
	 */
	public static function load_stripe_lib() {
		ASP_Utils::load_stripe_lib();
	}
}

new ASPMain();
