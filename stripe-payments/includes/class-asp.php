<?php

class AcceptStripePayments {

	public $footer_scripts = '';
	var $zeroCents         = array( 'JPY', 'MGA', 'VND', 'KRW' );
	var $minAmounts        = array(
		'USD' => 50,
		'AUD' => 50,
		'BGN' => 100,
		'BRL' => 50,
		'CAD' => 50,
		'CHF' => 50,
		'COP' => 2000,
		'DKK' => 250,
		'EUR' => 50,
		'GBP' => 30,
		'HKD' => 400,
		'INR' => 50,
		'JPY' => 50,
		'MXN' => 1000,
		'MYR' => 200,
		'NOK' => 300,
		'NZD' => 50,
		'PLN' => 200,
		'RON' => 200,
		'SEK' => 300,
		'SGD' => 50,
	);
	public $APISecKey      = '';
	public $APIPubKey      = '';
	public $APIPubKeyTest  = '';
	public $APIPubKeyLive  = '';
	public $APISecKeyTest  = '';
	public $APISecKeyLive  = '';
	public $is_live        = false;

	public static $pp_slug = 'asp-payment-box';

	/**
	 * Plugin version, used for cache-busting of style and script file references.
	 *
	 * @since   1.0.0
	 *
	 * @var     string
	 */
	const VERSION = '1.0.0';

	/**
	 *
	 * Unique identifier for your plugin.
	 *
	 * The variable name is used as the text domain when internationalizing strings
	 * of text. Its value should match the Text Domain file header in the main
	 * plugin file.
	 *
	 * @since    1.0.0
	 *
	 * @var      string
	 */
	protected $plugin_slug = 'accept_stripe_payment'; //Do not change this value.

	/**
	 * Instance of this class.
	 *
	 * @since    1.0.0
	 *
	 * @var      object
	 */
	protected static $instance = null;
	private $settings          = null;

	/**
	 * Initialize the plugin by setting localization and loading public scripts
	 * and styles.
	 *
	 * @since     1.0.0
	 */
	private function __construct() {

		self::$instance = $this;

		add_action( 'asp_send_scheduled_email', array( $this, 'send_scheduled_email' ), 10, 4 );

		if ( is_admin() ) {
			if ( ! function_exists( 'is_plugin_active' ) ) {
				require_once( ABSPATH . '/wp-admin/includes/plugin.php' );
			}
			if ( is_plugin_active( 'stripe-payments-recaptcha/asp-recaptcha-main.php' ) ) {
				deactivate_plugins( 'stripe-payments-recaptcha/asp-recaptcha-main.php' );
			}
		}
		if ( ! class_exists( 'ASPRECAPTCHA_main' ) ) {
			require_once WP_ASP_PLUGIN_PATH . 'includes/recaptcha/asp-recaptcha-main.php';
		}

		$this->settings = (array) get_option( 'AcceptStripePayments-settings' );

		if ( $this->get_setting( 'is_live' ) == 0 ) {
			//use test keys
			$this->is_live   = false;
			$this->APIPubKey = $this->get_setting( 'api_publishable_key_test' );
			$this->APISecKey = $this->get_setting( 'api_secret_key_test' );
		} else {
			//use live keys
			$this->is_live   = true;
			$this->APIPubKey = $this->get_setting( 'api_publishable_key' );
			$this->APISecKey = $this->get_setting( 'api_secret_key' );
		}
		$this->APIPubKeyTest = $this->get_setting( 'api_publishable_key_test' );
		$this->APIPubKeyLive = $this->get_setting( 'api_publishable_key' );
		$this->APISecKeyTest = $this->get_setting( 'api_secret_key_test' );
		$this->APISecKeyLive = $this->get_setting( 'api_secret_key' );

		// Load plugin text domain
		add_action( 'init', array( $this, 'load_asp_plugin_textdomain' ) );

		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );

		//handle self hooks
		require_once WP_ASP_PLUGIN_PATH . 'includes/class-asp-self-hooks-handler.php';

		//handle IPN stuff if needed
		require_once WP_ASP_PLUGIN_PATH . 'includes/process_ipn.php';
		require_once WP_ASP_PLUGIN_PATH . 'includes/class-asp-process-ipn-ng.php';

		//handle payment popup display if needed
		require_once WP_ASP_PLUGIN_PATH . 'includes/class-asp-pp-display.php';

		//handle payment popup ajax
		require_once WP_ASP_PLUGIN_PATH . 'includes/class-asp-pp-ajax.php';

		// Activate plugin when new blog is added
		add_action( 'wpmu_new_blog', array( $this, 'activate_new_site' ) );

		// Load public-facing style sheet and JavaScript.
		// add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts_styles' ) );
		add_action( 'after_switch_theme', array( $this, 'rewrite_flush' ) );
	}

	public function plugins_loaded() {
		//check if we have view_log request with token
		$action = filter_input( INPUT_GET, 'asp_action', FILTER_SANITIZE_STRING );
		$token  = filter_input( INPUT_GET, 'token', FILTER_SANITIZE_STRING );
		if ( isset( $action ) && 'view_log' === $action && isset( $token ) ) {
			//let's check token
			if ( $this->get_setting( 'debug_log_access_token' ) === $token ) {
				ASP_Debug_Logger::view_log();
			}
		}

		if ( ! is_admin() ) {
			add_action( 'wp_print_footer_scripts', array( $this, 'frontend_print_footer_scripts' ) );
		}
	}

	public function send_scheduled_email( $to, $subj, $body, $headers ) {
		ASP_Debug_Logger::log( sprintf( 'Sending scheduled email to %s.', $to ) );
		wp_mail( $to, $subj, $body, $headers );
	}

	public function get_setting( $field, $default = false ) {
		$this->settings = (array) get_option( 'AcceptStripePayments-settings' );
		return isset( $this->settings[ $field ] ) ? $this->settings[ $field ] : $default;
	}

	/**
	 * Return the plugin slug.
	 *
	 * @since    1.0.0
	 *
	 * @return    Plugin slug variable.
	 */
	public function get_plugin_slug() {
		return $this->plugin_slug;
	}

	/**
	 * Return an instance of this class.
	 *
	 * @since     1.0.0
	 *
	 * @return    object    A single instance of this class.
	 */
	public static function get_instance() {
		// If the single instance hasn't been set, set it now.
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Fired when the plugin is activated.
	 *
	 * @since    1.0.0
	 *
	 * @param    boolean    $network_wide    True if WPMU superadmin uses
	 *                                       "Network Activate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       activated on an individual blog.
	 */
	public static function activate( $network_wide ) {

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( $network_wide ) {

				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {
					switch_to_blog( $blog_id );
					self::single_activate();
				}

				restore_current_blog();
			} else {
				self::single_activate();
			}
		} else {
			self::single_activate();
		}
	}

	/**
	 * Fired when the plugin is deactivated.
	 *
	 * @since    1.0.0
	 *
	 * @param    boolean    $network_wide    True if WPMU superadmin uses
	 *                                       "Network Deactivate" action, false if
	 *                                       WPMU is disabled or plugin is
	 *                                       deactivated on an individual blog.
	 */
	public static function deactivate( $network_wide ) {

		if ( function_exists( 'is_multisite' ) && is_multisite() ) {

			if ( $network_wide ) {

				// Get all blog ids
				$blog_ids = self::get_blog_ids();

				foreach ( $blog_ids as $blog_id ) {

					switch_to_blog( $blog_id );
					self::single_deactivate();
				}

				restore_current_blog();
			} else {
				self::single_deactivate();
			}
		} else {
			self::single_deactivate();
		}
	}

	/**
	 * Fired when a new site is activated with a WPMU environment.
	 *
	 * @since    1.0.0
	 *
	 * @param    int    $blog_id    ID of the new blog.
	 */
	public function activate_new_site( $blog_id ) {

		if ( 1 !== did_action( 'wpmu_new_blog' ) ) {
			return;
		}

		switch_to_blog( $blog_id );
		self::single_activate();
		restore_current_blog();
	}

	/**
	 * Get all blog ids of blogs in the current network that are:
	 * - not archived
	 * - not spam
	 * - not deleted
	 *
	 * @since    1.0.0
	 *
	 * @return   array|false    The blog ids, false if no matches.
	 */
	private static function get_blog_ids() {
		global $wpdb;

		return $wpdb->get_col(
			"SELECT blog_id FROM $wpdb->blogs
		WHERE archived = '0' AND spam = '0'
		AND deleted = '0'"
		);
	}

	/**
	 * Fired for each blog when the plugin is activated.
	 *
	 * @since    1.0.0
	 */
	private static function single_activate() {
		$admin_email = get_option( 'admin_email' );
		if ( ! $admin_email ) {
			$admin_email = '';
		}
		$default = array(
			'is_live'                         => 0,
			'debug_log_enable'                => 0,
			'dont_save_card'                  => 0,
			'currency_code'                   => 'USD',
			'button_text'                     => __( 'Buy Now', 'stripe-payments' ),
			// translators: %s is not a placeholder
			'popup_button_text'               => __( 'Pay %s', 'stripe-payments' ),
			'use_new_button_method'           => 0,
			'checkout_url'                    => site_url( 'checkout' ),
			'from_email_address'              => get_bloginfo( 'name' ) . ' <sales@your-domain.com>',
			'buyer_email_subject'             => __( 'Thank you for the purchase', 'stripe-payments' ),
			'buyer_email_body'                => __( 'Hello', 'stripe-payments' ) . "\r\n\r\n"
				. __( 'Thank you for your purchase! You ordered the following item(s):', 'stripe-payments' ) . "\r\n\r\n"
				. '{product_details}',
			'seller_notification_email'       => get_bloginfo( 'admin_email' ),
			'seller_email_subject'            => __( 'Notification of product sale', 'stripe-payments' ),
			'seller_email_body'               => __( 'Dear Seller', 'stripe-payments' ) . "\r\n\r\n"
				. __( 'This mail is to notify you of a product sale.', 'stripe-payments' ) . "\r\n\r\n"
				. "{product_details}\r\n\r\n"
				. __( 'The sale was made to', 'stripe-payments' ) . " {payer_email}\r\n\r\n"
				. __( 'Thanks', 'stripe-payments' ),
			'price_currency_pos'              => 'left',
			'price_decimal_sep'               => '.',
			'price_thousand_sep'              => ',',
			'price_decimals_num'              => '2',
			'api_keys_separated'              => true,
			'stripe_receipt_email'            => 0,
			'custom_field_enabled'            => 0,
			'custom_field_name'               => '',
			'custom_field_descr'              => '',
			'custom_field_type'               => 'text',
			'custom_field_mandatory'          => 0,
			'send_email_on_error'             => 0,
			'send_email_on_error_to'          => $admin_email,
			'use_old_checkout_api1'           => 0,
			'disable_buttons_before_js_loads' => 0,
			'tos_text'                        => __( 'I accept the <a href="https://example.com/terms-and-conditions/" target="_blank">Terms and Conditions</a>', 'stripe-payments' ),
		);
		$opt     = get_option( 'AcceptStripePayments-settings' );
		// Check if its a first install
		$first_install = false;
		if ( ! is_array( $opt ) ) {
			//this is first install
			$first_install = true;
			$opt           = $default;
		}
		$opt = array_merge( $default, $opt );
		//force remove PHP warning dismissal
		delete_option( 'wp_asp_php_warning_dismissed' );
		update_option( 'AcceptStripePayments-settings', $opt );

		//lets add default values for some settings that were added after plugin update
		//let's separate Test and Live API keys (introduced in version 1.6.6)
		if ( $opt['is_live'] == 0 && ! isset( $opt['api_keys_separated'] ) ) {
			//current keys are test keys. Let's set them and clear the old values
			if ( isset( $opt['api_secret_key'] ) ) {
				$opt['api_secret_key_test'] = $opt['api_secret_key'];
				$opt['api_secret_key']      = '';
			}
			if ( isset( $opt['api_publishable_key'] ) ) {
				$opt['api_publishable_key_test'] = $opt['api_publishable_key'];
				$opt['api_publishable_key']      = '';
			}
			//let's also set an indicator value in order for the plugin to not do that anymore
			$opt['api_keys_separated'] = true;
		}

		//Enabled "Hide State Field" for existing installs, but only if wasn't set before
		if ( ! $first_install && ! isset( $opt['hide_state_field'] ) ) {
			$opt['hide_state_field'] = 1;
		}

		$opt_diff = array_diff_key( $default, $opt );
		if ( ! empty( $opt_diff ) ) {
			foreach ( $opt_diff as $key => $value ) {
				$opt[ $key ] = $default[ $key ];
			}
		}
		update_option( 'AcceptStripePayments-settings', $opt );

		//create checkout page
		$args             = array(
			'post_type' => 'page',
		);
		$pages            = get_pages( $args );
		$checkout_page_id = '';
		foreach ( $pages as $page ) {
			if ( strpos( $page->post_content, 'accept_stripe_payment_checkout' ) !== false ) {
				$checkout_page_id = $page->ID;
			}
		}
		if ( '' === $checkout_page_id ) {
			$checkout_page_id  = self::create_post( 'page', 'Checkout-Result', 'Stripe-Checkout-Result', '[accept_stripe_payment_checkout]' );
			$checkout_page     = get_post( $checkout_page_id );
			$checkout_page_url = $checkout_page->guid;
			$asp_settings      = get_option( 'AcceptStripePayments-settings' );
			if ( ! empty( $asp_settings ) ) {
				$asp_settings['checkout_url']     = $checkout_page_url;
				$asp_settings['checkout_page_id'] = $checkout_page_id;
				update_option( 'AcceptStripePayments-settings', $asp_settings );
			}
		}

		//Create all products/shop page
		$args             = array(
			'post_type' => 'page',
		);
		$pages            = get_pages( $args );
		$products_page_id = '';
		foreach ( $pages as $page ) {
			if ( strpos( $page->post_content, '[asp_show_all_products' ) !== false ) {
				$products_page_id = $page->ID;
			}
		}
		if ( '' === $products_page_id ) {
			$products_page_id = self::create_post( 'page', 'Products', 'Products', '[asp_show_all_products]' );

			//Save the newly created products page ID so it can be used later.
			$asp_settings = get_option( 'AcceptStripePayments-settings' );
			if ( ! empty( $asp_settings ) ) {
				$asp_settings['products_page_id'] = $products_page_id;
				update_option( 'AcceptStripePayments-settings', $asp_settings );
			}
		}
		//Flush rewrite rules so new pages and slugs are properly handled
		$asp_products = ASP_Admin_Products::get_instance();
		$asp_products->register_post_type();
		$asp_order = ASPOrder::get_instance();
		$asp_order->register_post_type();
		flush_rewrite_rules();
	}

	public static function create_post( $post_type, $title, $name, $content, $parent_id = null ) {
		$post = array(
			'post_title'     => $title,
			'post_name'      => $name,
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
			'post_content'   => $content,
			'post_status'    => 'publish',
			'post_type'      => $post_type,
		);

		if ( null !== $parent_id ) {
			$post['post_parent'] = $parent_id;
		}
		$post_id = wp_insert_post( $post );
		return $post_id;
	}

	/**
	 * Fired for each blog when the plugin is deactivated.
	 */
	private static function single_deactivate() {
		// @TODO: Define deactivation functionality here
	}

	/**
	 * Load the plugin text domain for translation.
	 */
	public function load_asp_plugin_textdomain() {
		load_plugin_textdomain( 'stripe-payments', false, dirname( plugin_basename( ASPMain::$file ) ) . '/languages/' );
	}

	/**
	 * @since    1.0.0
	 */
	public function rewrite_flush() {
		flush_rewrite_rules();
	}

	public static function get_currencies() {
		$currencies = ASP_Utils::get_currencies();
		return $currencies;
	}

	/**
	 * Use ASP_Utils::formatted_price() instead.
	 */
	public static function formatted_price( $price, $curr = '', $price_is_cents = false ) {
		return ASP_Utils::formatted_price( $price, $curr, $price_is_cents );
	}

	public static function apply_tax( $price, $tax, $is_zero_cents = false ) {
		if ( ! empty( $tax ) ) {
			$prec = 2;
			if ( $is_zero_cents ) {
				$prec = 0;
			}
			$tax_amount = round( ( $price * $tax / 100 ), $prec );
			$price     += $tax_amount;
		}
		return $price;
	}

	public static function apply_shipping( $price, $shipping, $is_zero_cents = false ) {
		if ( ! empty( $shipping ) ) {
			$prec = 2;
			if ( $is_zero_cents ) {
				$prec = 0;
			}
			$price += floatval( $shipping );
			$price  = round( $price, $prec );
		}
		return $price;
	}

	public static function get_tax_amount( $price, $tax, $is_zero_cents = false ) {
		if ( ! empty( $tax ) ) {
			$prec = 2;
			if ( $is_zero_cents ) {
				$prec = 0;
			}
			$tax_amount = round( ( $price * $tax / 100 ), $prec );
			return $tax_amount;
		} else {
			return 0;
		}
	}

	public static function is_zero_cents( $curr ) {
		$zero_cents = array( 'JPY', 'MGA', 'VND', 'KRW' );
		return in_array( strtoupper( $curr ), $zero_cents, true );
	}

	public static function from_cents( $amount, $currency ) {
		$prec = 2;
		if ( self::is_zero_cents( $currency ) ) {
				$prec = 0;
		}
		$res = round( $amount / 100, $prec );
		return $res;
	}

	public static function gen_additional_items( $data, $sep = "\n" ) {
		$out = '';
		if ( ! empty( $data['additional_items'] ) ) {
			foreach ( $data['additional_items'] as $item => $price ) {
				if ( $price < 0 ) {
					$amnt_str = '-' . self::formatted_price( abs( $price ), $data['currency_code'] );
				} else {
					$amnt_str = self::formatted_price( $price, $data['currency_code'] );
				}
				$out .= $item . ': ' . $amnt_str . $sep;
			}
		}
		return $out;
	}

	public static function tofloat( $num ) {
		$dot_pos   = strrpos( $num, '.' );
		$comma_pos = strrpos( $num, ',' );
		$sep       = ( ( $dot_pos > $comma_pos ) && $dot_pos ) ? $dot_pos : ( ( ( $comma_pos > $dot_pos ) && $comma_pos ) ? $comma_pos : false );

		if ( ! $sep ) {
			return floatval( preg_replace( '/[^0-9]/', '', $num ) );
		}

		return floatval(
			preg_replace( '/[^0-9]/', '', substr( $num, 0, $sep ) ) . '.' .
			preg_replace( '/[^0-9]/', '', substr( $num, $sep + 1, strlen( $num ) ) )
		);
	}

	public function enqueue_frontend_scripts_styles() {
		wp_register_script( 'stripe-handler-ng', WP_ASP_PLUGIN_URL . '/public/assets/js/stripe-handler-ng.js', array( 'jquery' ), WP_ASP_PLUGIN_VERSION, true );

		$iframe_url = ASP_Utils::get_base_pp_url();

		$prefetch = $this->get_setting( 'frontend_prefetch_scripts' );

		wp_localize_script(
			'stripe-handler-ng',
			'wpASPNG',
			array(
				'iframeUrl' => $iframe_url,
				'ppSlug'    => AcceptStripePayments::$pp_slug,
				'prefetch'  => $prefetch,
				'ckey'      => ASP_Utils::get_ckey(),
			)
		);

		wp_enqueue_script( 'stripe-handler-ng' );
		wp_register_style( 'stripe-handler-ng-style', WP_ASP_PLUGIN_URL . '/public/assets/css/public.css', array(), WP_ASP_PLUGIN_VERSION );
		wp_enqueue_style( 'stripe-handler-ng-style' );
	}

	public function frontend_print_footer_scripts() {
		if ( ! empty( $this->footer_scripts ) ) {
			echo $this->footer_scripts;
		}
	}

}
