<?php

/**
 * Plugin class. This class should ideally be used to work with the
 * public-facing side of the WordPress site.
 *
 * If you're interested in introducing administrative or dashboard
 * functionality, then refer to `class-plugin-name-admin.php`
 *
 *
 */
class AcceptStripePayments {


	var $zeroCents     = array( 'JPY', 'MGA', 'VND', 'KRW' );
	var $minAmounts    = array(
		'USD' => 50,
		'AUD' => 50,
		'BRL' => 50,
		'CAD' => 50,
		'CHF' => 50,
		'DKK' => 250,
		'EUR' => 50,
		'GBP' => 30,
		'HKD' => 400,
		'JPY' => 50,
		'MXN' => 1000,
		'NOK' => 300,
		'NZD' => 50,
		'SEK' => 300,
		'SGD' => 50,
	);
	var $APISecKey     = '';
	var $APIPubKey     = '';
	var $APIPubKeyTest = '';
	var $APISecKeyLive = '';
	var $APISecKeyTest = '';
	var $is_live       = false;

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

		add_action( 'asp_send_scheduled_email', array( $this, 'send_scheduled_email' ), 10, 4 );

		$this->settings = (array) get_option( 'AcceptStripePayments-settings' );

		if ( ! isset( $this->settings['use_old_checkout_api1'] ) ) {
			$this->settings['use_old_checkout_api1'] = 1;
			update_option( 'AcceptStripePayments-settings', $this->settings );
		}

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
		$this->APISecKeyLive = $this->get_setting( 'api_secret_key' );
		$this->APISecKeyTest = $this->get_setting( 'api_secret_key_test' );

		// Load plugin text domain
		add_action( 'init', array( $this, 'load_asp_plugin_textdomain' ) );

		add_action( 'plugins_loaded', array( $this, 'plugins_loaded' ) );

		//handle IPN stuff if needed
		require_once WP_ASP_PLUGIN_PATH . 'includes/process_ipn.php';
		require_once WP_ASP_PLUGIN_PATH . 'includes/class-asp-process-ipn-ng.php';

		//handle payment popup display if needed
		require_once WP_ASP_PLUGIN_PATH . 'includes/class-asp-pp-handler.php';

		// Activate plugin when new blog is added
		add_action( 'wpmu_new_blog', array( $this, 'activate_new_site' ) );

		// Load public-facing style sheet and JavaScript.
		// add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_scripts_styles' ) );
		add_action( 'after_switch_theme', array( $this, 'rewrite_flush' ) );
	}

	function plugins_loaded() {
		 //check if we have view_log request with token
		$action = filter_input( INPUT_GET, 'asp_action', FILTER_SANITIZE_STRING );
		$token  = filter_input( INPUT_GET, 'token', FILTER_SANITIZE_STRING );
		if ( isset( $action ) && $action === 'view_log' && isset( $token ) ) {
			//let's check token
			if ( $this->get_setting( 'debug_log_access_token' ) === $token ) {
				ASP_Debug_Logger::view_log();
			}
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
		if ( null == self::$instance ) {
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

		// get an array of blog ids
		$sql = "SELECT blog_id FROM $wpdb->blogs
			WHERE archived = '0' AND spam = '0'
			AND deleted = '0'";

		return $wpdb->get_col( $sql );
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
		// Check if its a first install
		$default = array(
			'is_live'                         => 0,
			'debug_log_enable'                => 0,
			'dont_save_card'                  => 0,
			'currency_code'                   => 'USD',
			'button_text'                     => __( 'Buy Now', 'stripe-payments' ),
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
		if ( ! is_array( $opt ) ) {
			$opt = $default;
		}
		$opt = array_merge( $default, $opt );
		//force remove PHP warning dismissal
		delete_option( 'wp_asp_php_warning_dismissed' );
		update_option( 'AcceptStripePayments-settings', $opt );
		if ( empty( $opt ) ) {
			//	    add_option( 'AcceptStripePayments-settings', $default );
		} else { //lets add default values for some settings that were added after plugin update
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
			$opt_diff = array_diff_key( $default, $opt );
			if ( ! empty( $opt_diff ) ) {
				foreach ( $opt_diff as $key => $value ) {
					$opt[ $key ] = $default[ $key ];
				}
			}
			update_option( 'AcceptStripePayments-settings', $opt );
		}
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
		if ( $checkout_page_id == '' ) {
			$checkout_page_id              = self::create_post( 'page', 'Checkout-Result', 'Stripe-Checkout-Result', '[accept_stripe_payment_checkout]' );
			$checkout_page                 = get_post( $checkout_page_id );
			$checkout_page_url             = $checkout_page->guid;
			$AcceptStripePayments_settings = get_option( 'AcceptStripePayments-settings' );
			if ( ! empty( $AcceptStripePayments_settings ) ) {
				$AcceptStripePayments_settings['checkout_url']     = $checkout_page_url;
				$AcceptStripePayments_settings['checkout_page_id'] = $checkout_page_id;
				update_option( 'AcceptStripePayments-settings', $AcceptStripePayments_settings );
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
		if ( $products_page_id == '' ) {
			$products_page_id = self::create_post( 'page', 'Products', 'Products', '[asp_show_all_products]' );

			//Save the newly created products page ID so it can be used later.
			$AcceptStripePayments_settings = get_option( 'AcceptStripePayments-settings' );
			if ( ! empty( $AcceptStripePayments_settings ) ) {
				$AcceptStripePayments_settings['products_page_id'] = $products_page_id;
				update_option( 'AcceptStripePayments-settings', $AcceptStripePayments_settings );
			}
		}
		//Flush rewrite rules so new pages and slugs are properly handled
		$ASPProducts = ASPProducts::get_instance();
		$ASPProducts->register_post_type();
		$ASPOrder = ASPOrder::get_instance();
		$ASPOrder->register_post_type();
		flush_rewrite_rules();
	}

	public static function create_post( $postType, $title, $name, $content, $parentId = null ) {
		$post = array(
			'post_title'     => $title,
			'post_name'      => $name,
			'comment_status' => 'closed',
			'ping_status'    => 'closed',
			'post_content'   => $content,
			'post_status'    => 'publish',
			'post_type'      => $postType,
		);

		if ( $parentId !== null ) {
			$post['post_parent'] = $parentId;
		}
		$postId = wp_insert_post( $post );
		return $postId;
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
		load_plugin_textdomain( 'stripe-payments', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
	}

	/**
	 * @since    1.0.0
	 */
	public function rewrite_flush() {
		flush_rewrite_rules();
	}

	static function get_currencies() {
		$currencies = ASP_Utils::get_currencies();

		return $currencies;
	}

	static function formatted_price( $price, $curr = '', $price_is_cents = false ) {

		if ( empty( $price ) ) {
			$price = 0;
		}

		$opts = get_option( 'AcceptStripePayments-settings' );

		if ( $curr === false ) {
			//if curr set to false, we format price without currency symbol or code
			$curr_sym = '';
		} else {

			if ( $curr === '' ) {
				//if currency not specified, let's use default currency set in options
				$curr = $opts['currency_code'];
			}

			$curr = strtoupper( $curr );

			$currencies = self::get_currencies();
			if ( isset( $currencies[ $curr ] ) ) {
				$curr_sym = $currencies[ $curr ][1];
			} else {
				//no currency code found, let's just use currency code instead of symbol
				$curr_sym = $curr;
			}
		}

		//check if price is in cents
		if ( $price_is_cents && ! self::is_zero_cents( $curr ) ) {
			$price = intval( $price ) / 100;
		}

		$out = number_format( $price, $opts['price_decimals_num'], $opts['price_decimal_sep'], $opts['price_thousand_sep'] );

		switch ( $opts['price_currency_pos'] ) {
			case 'left':
				$out = $curr_sym . '' . $out;
				break;
			case 'right':
				$out .= '' . $curr_sym;
				break;
			default:
				$out .= '' . $curr_sym;
				break;
		}

		return $out;
	}

	static function apply_tax( $price, $tax, $is_zero_cents = false ) {
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

	static function apply_shipping( $price, $shipping, $is_zero_cents = false ) {
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

	static function get_tax_amount( $price, $tax, $is_zero_cents = false ) {
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

	static function is_zero_cents( $curr ) {
		$zeroCents = array( 'JPY', 'MGA', 'VND', 'KRW' );
		return in_array( strtoupper( $curr ), $zeroCents );
	}

	static function from_cents( $amount, $currency ) {
		$prec = 2;
		if ( self::is_zero_cents( $currency ) ) {
				$prec = 0;
		}
		$res = round( $amount / 100, $prec );
		return $res;
	}

	static function gen_additional_items( $data, $sep = "\n" ) {
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

	static function get_small_product_thumb( $prod_id, $force_regen = false ) {
		$ret = '';
		//check if we have a thumbnail
		$curr_thumb = get_post_meta( $prod_id, 'asp_product_thumbnail', true );
		if ( empty( $curr_thumb ) ) {
			return $ret;
		}
		$ret = $curr_thumb;
		//check if we have 100x100 preview generated
		$thumb_thumb = get_post_meta( $prod_id, 'asp_product_thumbnail_thumb', true );
		if ( empty( $thumb_thumb ) || $force_regen ) {
			//looks like we don't have one. Let's generate it
			$thumb_thumb = '';
			$image       = wp_get_image_editor( $curr_thumb );
			if ( ! is_wp_error( $image ) ) {
				$image->resize( 100, 100, true );
				$upload_dir = wp_upload_dir();
				$ext        = pathinfo( $curr_thumb, PATHINFO_EXTENSION );
				$file_name  = 'asp_product_' . $prod_id . '_thumb_' . md5( $curr_thumb ) . '.' . $ext;
				$res        = $image->save( $upload_dir['path'] . '/' . $file_name );
				if ( ! is_wp_error( $res ) ) {
					$thumb_thumb = $upload_dir['url'] . '/' . $file_name;
				} else {
					//error saving thumb image
					return $ret;
				}
			} else {
				//error occured during image load
				return $ret;
			}
			update_post_meta( $prod_id, 'asp_product_thumbnail_thumb', $thumb_thumb );
			$ret = $thumb_thumb;
		} else {
			// we have one. Let's return it
			$ret = $thumb_thumb;
		}
		return $ret;
	}

	public static function tofloat( $num ) {
		$dotPos   = strrpos( $num, '.' );
		$commaPos = strrpos( $num, ',' );
		$sep      = ( ( $dotPos > $commaPos ) && $dotPos ) ? $dotPos : ( ( ( $commaPos > $dotPos ) && $commaPos ) ? $commaPos : false );

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
		$home_url = get_home_url( null, '/' );

		$iframe_url = add_query_arg(
			array(
				'asp_action' => 'show_pp',
			),
			$home_url
		);
		wp_localize_script( 'stripe-handler-ng', 'wpASPNG', array( 'iframeUrl' => $iframe_url ) );
		wp_enqueue_script( 'stripe-handler-ng' );
		wp_register_style( 'stripe-handler-ng-style', WP_ASP_PLUGIN_URL . '/public/assets/css/public.css', array(), WP_ASP_PLUGIN_VERSION );
		wp_enqueue_style( 'stripe-handler-ng-style' );
	}

}
