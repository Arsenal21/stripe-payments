<?php

class AcceptStripePayments_Admin {

	/**
	 * Instance of this class.
	 *
	 * @since    1.0.0
	 *
	 * @var      object
	 */
	protected static $instance     = null;
	private $requiered_php_modules = array( 'curl', 'zlib', 'json', 'mbstring' );

	/**
	 * Slug of the plugin screen.
	 *
	 * @since    1.0.0
	 *
	 * @var      string
	 */
	protected $plugin_screen_hook_suffix = null;

	/**
	 * Initialize the plugin by loading admin scripts & styles and adding a
	 * settings page and menu.
	 *
	 * @since     1.0.0
	 */
	private function __construct() {
		self::$instance = $this;
		/*
		* Call $plugin_slug from public plugin class.
		*/
		$plugin            = AcceptStripePayments::get_instance();
		$this->plugin_slug = $plugin->get_plugin_slug();
		$this->asp_main    = $plugin;

		// add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
		// Add the options page and menu item.
		add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );

		//Enqueue required scripts and styles
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		//Add any required inline JS code in the admin dashboard side.
		add_action( 'admin_print_scripts', array( $this, 'asp_print_admin_scripts' ) );

		add_action( 'admin_init', array( $this, 'admin_init' ) );

		add_action( 'admin_notices', array( $this, 'show_admin_notices' ), 1 );

		//TinyMCE button related
		add_action( 'init', array( $this, 'tinymce_shortcode_button' ) );
		add_action( 'current_screen', array( $this, 'check_current_screen' ) );
		add_action( 'wp_ajax_asp_tinymce_get_settings', array( $this, 'tinymce_ajax_handler' ) ); // Add ajax action handler for tinymce
		//Settings link
		add_filter( 'plugin_action_links_' . plugin_basename( WP_ASP_PLUGIN_FILE ), array( $this, 'add_settings_link' ) );
	}

	public function add_settings_link( $links ) {
		$settings_link = sprintf( '<a href="edit.php?post_type=%s&page=stripe-payments-settings#general">', ASPMain::$products_slug ) . __( 'Settings', 'stripe-payments' ) . '</a>';
		array_unshift( $links, $settings_link );
		return $links;
	}

	function enqueue_scripts( $hook ) {
		//this script is requested on all plugin admin pages
		wp_register_script( 'asp-admin-general-js', WP_ASP_PLUGIN_URL . '/admin/assets/js/admin.js', array( 'jquery' ), WP_ASP_PLUGIN_VERSION, true );
		//include admin style
		wp_register_style( 'asp-admin-styles', WP_ASP_PLUGIN_URL . '/admin/assets/css/admin.css', array(), WP_ASP_PLUGIN_VERSION );

		switch ( $hook ) {
			case ASPMain::$products_slug . '_page_stripe-payments-settings':
			case ASPMain::$products_slug . '_page_stripe-payments-addons':
				//settings page
				wp_register_script( 'asp-admin-settings-js', WP_ASP_PLUGIN_URL . '/admin/assets/js/settings.js', array( 'jquery' ), WP_ASP_PLUGIN_VERSION, true );
				wp_enqueue_script( 'asp-admin-general-js' );
				wp_enqueue_style( 'asp-admin-styles' );
				break;
			case 'post.php':
			case 'edit.php':
			case 'post-new.php':
				global $post_type;
				if ( ASPMain::$products_slug === $post_type ) {
					wp_enqueue_script( 'asp-admin-general-js' );
					wp_enqueue_style( 'asp-admin-styles' );
					wp_register_script( 'asp-admin-edit-product-js', WP_ASP_PLUGIN_URL . '/admin/assets/js/edit-product.js', array( 'jquery' ), WP_ASP_PLUGIN_VERSION, true );
				}

				if ( 'stripe_order' === $post_type ) {
					wp_enqueue_script( 'asp-admin-general-js' );
					wp_enqueue_style( 'asp-admin-styles' );
					//Confirm capturing authorized funds for order #%s
					wp_register_script( 'asp-admin-orders-js', WP_ASP_PLUGIN_URL . '/admin/assets/js/orders.js', array( 'jquery' ), WP_ASP_PLUGIN_VERSION, true );
					wp_localize_script(
						'asp-admin-orders-js',
						'aspOrdersVars',
						array(
							'str' => array(
								// translators: %s is order ID
								'confirmCapture' => __( 'Confirm capturing authorized funds for order #%s', 'stripe-payments' ),
								// translators: %s is order ID
								'confirmCancel'  => __( 'Confirm cancel authorized funds for order #%s', 'stripe-payments' ),
								'errorOccurred'  => __( 'Error occurred during request. Please refresh page and try again.', 'stripe-payments' ),
							),
						)
					);
					wp_enqueue_script( 'asp-admin-orders-js' );

					wp_register_style( 'asp-admin-orders-styles', WP_ASP_PLUGIN_URL . '/admin/assets/css/orders.css', array(), WP_ASP_PLUGIN_VERSION );
					wp_enqueue_style( 'asp-admin-orders-styles' );

					wp_register_style( 'asp-admin-balloon-css', WP_ASP_PLUGIN_URL . '/admin/assets/css/balloon.min.css', array(), WP_ASP_PLUGIN_VERSION );
					wp_enqueue_style( 'asp-admin-balloon-css' );
				}
				break;
		}
	}

	function show_admin_notices() {
		//check minimum add-ons versions
		// translators: %s is add-on name
		$addon_update_str = __( 'Please update <b>%s</b> to latest version.', 'stripe-payments' );
		//check minimum Subscriptions add-on version
		$addon_name = 'Stripe Payments Subscriptions Addon';
		if ( class_exists( 'ASPSUB_Main' ) && version_compare( ASPSUB_main::ADDON_VER, '2.0.26' ) < 0 ) {
			self::add_admin_notice(
				'warning',
				sprintf(
					$addon_update_str,
					'<a href="' . add_query_arg( 's', $addon_name, admin_url( 'plugins.php' ) ) . '">' . $addon_name . '</a>'
				),
				false
			);
		}
		//check minimum APM add-on version
		$addon_name = 'Stripe Additional Payment Methods Addon';
		if ( class_exists( 'ASPAPM_main' ) && version_compare( ASPAPM_main::ADDON_VER, '2.0.15' ) < 0 ) {
			self::add_admin_notice(
				'warning',
				sprintf(
					$addon_update_str,
					'<a href="' . add_query_arg( 's', $addon_name, admin_url( 'plugins.php' ) ) . '">' . $addon_name . '</a>'
				),
				false
			);
		}

		$msg_arr = get_transient( 'asp_admin_msg_arr' );
		if ( ! empty( $msg_arr ) ) {
			delete_transient( 'asp_admin_msg_arr' );
			$tpl = '<div class="notice notice-%1$s%3$s"><p>%2$s</p></div>';
			foreach ( $msg_arr as $msg ) {
				if ( ! empty( $msg ) ) {
					echo sprintf( $tpl, $msg['type'], $msg['text'], true === $msg['dism'] ? ' is-dismissible' : '' );
				}
			}
		}

		$notice_dismissed_get = filter_input( INPUT_GET, 'asp_dismiss_new_api_msg', FILTER_SANITIZE_NUMBER_INT );
		if ( $notice_dismissed_get ) {
			update_option( 'asp_new_api_notice_dismissed1', true );
		}

		$notice_dismissed_get = filter_input( INPUT_GET, 'asp_dismiss_auc_msg', FILTER_SANITIZE_NUMBER_INT );
		if ( ! empty( $notice_dismissed_get ) && check_admin_referer( 'asp_dismiss_auc_msg' ) ) {
			$user_id = get_current_user_id();
			if ( ! empty( $user_id ) ) {
				update_user_meta( $user_id, 'asp_dismiss_auc_msg', true );
				wp_safe_redirect( get_admin_url() );
				exit;
			}
		}

		//show new API notice
		$opt = get_option( 'AcceptStripePayments-settings' );
		if ( isset( $opt['use_old_checkout_api1'] ) && $opt['use_old_checkout_api1'] ) {
			$notice_dismissed = get_option( 'asp_new_api_notice_dismissed1' );
			if ( ! $notice_dismissed ) {
				$tpl = '<div class="notice notice-%1$s%3$s">%2$s</div>';
				$msg = '<p>The new version of the Stripe Payments plugin has the SCA compliant API support. However, you\'re still using the legacy API.<br/><br/>' .
				'<a href="https://stripe.com/docs/strong-customer-authentication/doineed" target="_blank">Click here</a> to check whether your business needs to support Strong Customer Authentication (SCA). If it does, disable legacy API by  unchecking the "Enable Legacy Checkout API" checkbox in the <a href="edit.php?post_type=asp-products&page=stripe-payments-settings#advanced">Advanced Settings tab</a> of the plugin.</p>';
				//here's link to advanced settings tab you can use in the message:
				// <a href="edit.php?post_type=asp-products&page=stripe-payments-settings#advanced">advanced settings</a>
				$admin_url   = get_admin_url();
				$dismiss_url = add_query_arg( 'asp_dismiss_new_api_msg', '1', $admin_url );
				$msg        .= '<p><a style="text-decoration: none; border-bottom: 1px dashed;" href="' . $dismiss_url . '">Don\'t show this message again</a></p>';
				echo sprintf( $tpl, 'warning', $msg, '' );
			}
		}
	}


	static function add_admin_notice( $type, $text, $dism = true ) {
		$msg_arr  = get_transient( 'asp_admin_msg_arr' );
		$msg_arr  = empty( $msg_arr ) ? array() : $msg_arr;
		$arr_item = array(
			'type' => $type,
			'text' => $text,
			'dism' => $dism,
		);

		$item_hash = md5( json_encode( $arr_item ) );

		if ( ! isset( $msg_arr[ $item_hash ] ) ) {
			$msg_arr[ $item_hash ] = $arr_item;
			set_transient( 'asp_admin_msg_arr', $msg_arr );
		}

		return $item_hash;
	}

	static function remove_admin_notice_by_hash( $item_hash ) {
		$msg_arr = get_transient( 'asp_admin_msg_arr' );
		$msg_arr = empty( $msg_arr ) ? array() : $msg_arr;

		if ( isset( $msg_arr[ $item_hash ] ) ) {
			$msg_arr[ $item_hash ] = '';
			set_transient( 'asp_admin_msg_arr', $msg_arr );
		}
	}

	function admin_init() {
		if ( current_user_can( 'manage_options' ) ) {
			add_action( 'wp_ajax_asp_clear_log', array( 'ASP_Debug_Logger', 'clear_log' ) );
			//view log file
			$asp_action = filter_input( INPUT_GET, 'asp_action', FILTER_SANITIZE_STRING );
			if ( ! empty( $asp_action ) ) {
				if ( 'view_log' === $asp_action ) {
					ASP_Debug_Logger::view_log();
				}
			}
		}
		if ( ! wp_doing_ajax() && is_admin() ) {
			//check if PHP version meets minimum required
			if ( version_compare( PHP_VERSION, WP_ASP_MIN_PHP_VERSION, '<' ) ) {
				add_action( 'admin_notices', array( $this, 'add_php_version_notice' ) );
			}
			//check if required php modules are installed
			$this->check_php_modules();
		}
	}

	private function check_php_modules() {
		$missing_modules = array();
		$php_modules     = apply_filters( 'asp_required_php_modules_array', $this->requiered_php_modules );
		foreach ( $php_modules as $module ) {
			if ( ! extension_loaded( $module ) ) {
				$missing_modules[] = $module;
			}
		}
		if ( ! empty( $missing_modules ) ) {
			$msg  = __( '<b>Stripe Payments:</b> following extentions are required by the plugin to operate properly but aren\'t installed on your system:', 'stripe-payments' );
			$msg .= '<p><strong>' . implode( ', ', $missing_modules ) . '</strong></p>';
			$msg .= '<p>' . __( 'You need to communicate this information to your system administrator or hosting provider.', 'stripe-payments' ) . '</p>';
			self::add_admin_notice( 'error', $msg );
		}
	}

	public function add_php_version_notice() {
		$msg  = '';
		$msg .= '<h3>' . __( 'Warning: Stripe Payments plugin', 'stripe-payments' ) . '</h3>';
		$msg .= '<p>' . __( "PHP version installed on your server doesn't meet minimum required for Stripe Payments plugin to operate properly.", 'stripe-payments' ) . '</p>';
		$msg .= '<p>' . __( 'You need to communicate this information to your system administrator or hosting provider.', 'stripe-payments' ) . '</p>';
		$msg .= '<p><strong>' . __( 'PHP Version Installed:', 'stripe-payments' ) . '</strong> %s</p>';
		$msg .= '<p><strong>' . __( 'PHP Version Required:', 'stripe-payments' ) . '</strong> %s ' . _x( 'or higher.', 'Used in "PHP Version Required: X.X or higher"', 'stripe-payments' ) . '</p>';
		$msg  = sprintf( $msg, PHP_VERSION, WP_ASP_MIN_PHP_VERSION );
		self::add_admin_notice( 'error', $msg, false );
	}

	public function check_current_screen() {
		if ( function_exists( 'get_current_screen' ) ) {
			$screen = get_current_screen();
			if ( 'post' === $screen->base ) {
				//we're on post edit page, let's do some things for shortcode inserter
				if ( ! wp_doing_ajax() ) {
					// Load admin style sheet
					add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_styles' ) );
				}
			}
		}
	}

	public function asp_print_admin_scripts() {
		//The following is used by the TinyMCE button.
		?>
	<script type="text/javascript">
		var asp_admin_ajax_url = '<?php echo esc_js( admin_url( 'admin-ajax.php?action=ajax' ) ); ?>';
	</script>
		<?php
	}

	public function tinymce_ajax_handler() {
		ob_start();
		require_once WP_ASP_PLUGIN_PATH . 'admin/views/shortcode-inserter.php';
		$content      = ob_get_clean();
		$query        = new WP_Query(
			array(
				'post_type'      => ASPMain::$products_slug,
				'post_status'    => 'publish',
				'posts_per_page' => -1,
			)
		);
		$products_sel = '';

		while ( $query->have_posts() ) {
			$query->the_post();
			$products_sel .= '<option value="' . get_the_ID() . '">' . get_the_title() . '</option>';
		}
		wp_reset_postdata();

		$opt                  = get_option( 'AcceptStripePayments-settings' );
		$ret['button_text']   = $opt['button_text'];
		$ret['currency_opts'] = self::get_currency_options();
		$ret['content']       = $content;
		$ret['products_sel']  = $products_sel;
		echo json_encode( $ret );
		wp_die();
	}

	public function tinymce_shortcode_button() {

		add_filter( 'mce_external_plugins', array( $this, 'add_shortcode_button' ) );
		add_filter( 'mce_buttons', array( $this, 'register_shortcode_button' ) );
	}

	public function add_shortcode_button( $plugin_array ) {

		$plugin_array['asp_shortcode'] = plugins_url( 'assets/js/tinymce/asp_editor_plugin.js', __FILE__ );
		return $plugin_array;
	}

	public function register_shortcode_button( $buttons ) {

		$buttons[] = 'asp_shortcode';
		return $buttons;
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
	 * Register and enqueue admin-specific style sheet.
	 *
	 * @since     1.0.0
	 *
	 * @return    null    Return early if no settings page is registered.
	 */
	public function enqueue_admin_styles() {

		//        if (!isset($this->plugin_screen_hook_suffix)) {
		//            return;
		//        }
		//
		//        $screen = get_current_screen();
		//        if ($this->plugin_screen_hook_suffix == $screen->id) {
		wp_enqueue_style( 'asp-admin-styles' );
		//        }
	}

	/**
	 * Register and enqueue admin-specific JavaScript.
	 * @return    null    Return early if no settings page is registered.
	 */
	public function enqueue_admin_scripts() {

		if ( ! isset( $this->plugin_screen_hook_suffix ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( $this->plugin_screen_hook_suffix === $screen->id ) {
			wp_enqueue_script( $this->plugin_slug . '-admin-script', plugins_url( 'assets/js/admin.js', __FILE__ ), array( 'jquery' ), AcceptStripePayments::VERSION );
		}
	}

	/**
	 * Register the administration menu for this plugin into the WordPress Dashboard menu.
	 *
	 * @since    1.0.0
	 */
	public function add_plugin_admin_menu() {

		/*
		* Add a settings page for this plugin to the Settings menu.
		*
		* NOTE:  Alternative menu locations are available via WordPress administration menu functions.
		*
		*        Administration Menus: http://codex.wordpress.org/Administration_Menus
		*
		* @TODO:
		*
		* - Change 'Page Title' to the title of your plugin admin page
		* - Change 'Menu Text' to the text for menu item for the plugin settings page
		* - Change 'manage_options' to the capability you see fit
		*   For reference: http://codex.wordpress.org/Roles_and_Capabilities
		*/
		//Products submenu
		//  add_submenu_page( 'edit.php?post_type=stripe_order', __( 'Products', 'stripe-payments' ), __( 'Products', 'stripe-payments' ), 'manage_options', 'edit.php?post_type=stripe_order', array( $this, 'display_plugin_admin_page' ) );
		$this->plugin_screen_hook_suffix = add_submenu_page(
			'edit.php?post_type=' . ASPMain::$products_slug,
			__( 'Settings', 'stripe-payments' ),
			__( 'Settings', 'stripe-payments' ),
			'manage_options',
			'stripe-payments-settings',
			array( $this, 'display_plugin_admin_page' )
		);

		add_submenu_page( 'edit.php?post_type=' . ASPMain::$products_slug, __( 'Add-ons', 'stripe-payments' ), __( 'Add-ons', 'stripe-payments' ), 'manage_options', 'stripe-payments-addons', array( $this, 'display_addons_menu_page' ) );

		add_action( 'admin_init', array( &$this, 'register_settings' ) );
	}

	/**
	 * Register Admin page settings
	 *
	 * @since    1.0.0
	 */
	public function register_settings( $value = '' ) {

		$new_api_str = '<br><sub class="asp-new-api-only">' . __( 'New API only', 'stripe-payments' ) . '</sub>';

		register_setting( 'AcceptStripePayments-settings-group', 'AcceptStripePayments-settings', array( &$this, 'settings_sanitize_field_callback' ) );

		// Add/define the various section/groups (the fields will go under these sections).

		add_settings_section( 'AcceptStripePayments-global-section', __( 'Global Settings', 'stripe-payments' ), null, $this->plugin_slug );
		add_settings_section( 'AcceptStripePayments-credentials-section', __( 'Credentials', 'stripe-payments' ), null, $this->plugin_slug );
		add_settings_section( 'AcceptStripePayments-debug-section', __( 'Debug', 'stripe-payments' ), null, $this->plugin_slug );

		add_settings_section( 'AcceptStripePayments-email-section', __( 'Email Settings', 'stripe-payments' ), null, $this->plugin_slug . '-email' );
		add_settings_section( 'AcceptStripePayments-error-email-section', __( 'Transaction Error Email Settings', 'stripe-payments' ), null, $this->plugin_slug . '-email' );
		add_settings_section( 'AcceptStripePayments-additional-email-section', __( 'Additional Email Settings', 'stripe-payments' ), null, $this->plugin_slug . '-email' );

		add_settings_section( 'AcceptStripePayments-price-display', __( 'Price Display Settings', 'stripe-payments' ), null, $this->plugin_slug . '-advanced' );
		add_settings_section( 'AcceptStripePayments-custom-field', __( 'Custom Field Settings', 'stripe-payments' ), null, $this->plugin_slug . '-advanced' );
		add_settings_section( 'AcceptStripePayments-tos', __( 'Terms and Conditions', 'stripe-payments' ), array( $this, 'tos_description' ), $this->plugin_slug . '-advanced' );
		add_settings_section( 'AcceptStripePayments-additional-settings', __( 'Additional Settings', 'stripe-payments' ), null, $this->plugin_slug . '-advanced' );
		add_settings_section( 'AcceptStripePayments-experimental-settings', __( 'Experemintal Settings', 'stripe-payments' ), array( $this, 'experemintal_section_description' ), $this->plugin_slug . '-advanced' );

		// Global section
		add_settings_field(
			'checkout_url',
			__( 'Checkout Result Page URL', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug,
			'AcceptStripePayments-global-section',
			array(
				'field' => 'checkout_url',
				'desc'  => __( 'This is the thank you page. This page is automatically created for you when you install the plugin. Do not delete this page as the plugin will send the customer to this page after the payment.', 'stripe-payments' ) . '<br /><b><i>' . __( 'Important Notice:', 'stripe-payments' ) . '</i></b> ' . __( 'if you are using caching plugins on your site (similar to W3 Total Cache, WP Rocket etc), you must exclude checkout results page from caching. Failing to do so will result in unpredictable checkout results output.', 'stripe-payments' ),
				'size'  => 100,
			)
		);
		add_settings_field(
			'products_page_id',
			__( 'Products Page URL', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug,
			'AcceptStripePayments-global-section',
			array(
				'field' => 'products_page_id',
				'desc'  => __( 'All your products will be listed here in a grid display. When you create new products, they will show up in this page. This page is automatically created for you when you install the plugin. You can add this page to your navigation menu if you want the site visitors to find it easily.', 'stripe-payments' ),
				'size'  => 100,
			)
		);

		add_settings_field(
			'currency_code',
			__( 'Currency', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug,
			'AcceptStripePayments-global-section',
			array(
				'field' => 'currency_code',
				'desc'  => '',
				'size'  => 10,
			)
		);
		add_settings_field(
			'currency_symbol',
			__( 'Currency Symbol', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug,
			'AcceptStripePayments-global-section',
			array(
				'field' => 'currency_symbol',
				'desc'  => __( 'Example: $, €, £ etc.', 'stripe-payments' ),
				'size'  => 10,
			)
		);
		add_settings_field(
			'button_text',
			__( 'Button Text', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug,
			'AcceptStripePayments-global-section',
			array(
				'field' => 'button_text',
				'desc'  => __(
					'Example: Buy Now, Pay Now etc.',
					'stripe-payments'
				),
			)
		);
		add_settings_field(
			'popup_button_text',
			__( 'Payment Popup Button Text', 'stripe-payments' ) . $new_api_str,
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug,
			'AcceptStripePayments-global-section',
			array(
				'field' => 'popup_button_text',
				'desc'  => __(
					'&percnt;s is replaced by formatted payment amount (example: Pay $29.90). If this field is empty, it defaults to "Pay &percnt;s"', //phpcs:ignore
					'stripe-payments'
				),
			)
		);
		add_settings_field(
			'dont_save_card',
			__( 'Do Not Save Card Data on Stripe', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug,
			'AcceptStripePayments-global-section',
			array(
				'field' => 'dont_save_card',
				'desc'  => __(
					'When this checkbox is checked, the transaction won\'t create the customer (no card will be saved for that).',
					'stripe-payments'
				),
			)
		);
		add_settings_field(
			'disable_remember_me',
			__( 'Turn Off "Remember me" Option', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug,
			'AcceptStripePayments-global-section',
			array(
				'field' => 'disable_remember_me',
				'desc'  => __(
					'When enabled, "Remember me" checkbox will be removed from Stripe\'s checkout popup.',
					'stripe-payments'
				),
			)
		);
		add_settings_field(
			'enable_zip_validation',
			__( 'Validate ZIP Code', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug,
			'AcceptStripePayments-global-section',
			array(
				'field' => 'enable_zip_validation',
				'desc'  => sprintf(
					// translators: %s is link to Stripe Radar rules page
					__(
						'For additional protection, you can opt to have Stripe collect the billing ZIP code. Make sure that ZIP code verification is turned on for your account. <a href="%s" target="_blank">Click here</a> to check it in your Stripe Dashboard.',
						'stripe-payments'
					),
					'https://dashboard.stripe.com/radar/rules'
				),
			)
		);
		//  add_settings_field( 'use_new_button_method', __( 'Use New Method To Display Buttons', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug, 'AcceptStripePayments-global-section', array( 'field'     => 'use_new_button_method',
		//      'desc'   => __( 'Use new method to display Stripe buttons. It makes connection to Stripe website only when button is clicked, which makes the page with buttons load faster. A little drawback is that Stripe pop-up is displayed with a small delay after button click. If you have more than one button on a page, enabling this option is highly recommended.', 'stripe-payments' ) . '<br /><b>' . __( 'Note:', 'stripe-payments' ) . '</b> ' . __( 'old method doesn\'t support custom price and quantity. If your shortcode or product is using one of those features, the new method will be used automatically for that entity.', 'stripe-payments' ) ) );
		add_settings_field(
			'checkout_lang',
			__( 'Stripe Checkout Language', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug,
			'AcceptStripePayments-global-section',
			array(
				'field' => 'checkout_lang',
				'desc'  => __(
					'Specify language to be used in Stripe checkout pop-up or select "Autodetect" to let Stripe handle it.',
					'stripe-payments'
				) . '<br>Note this currently only affects "Card Number", "MM/YY" and "CVC" placeholders.',
			)
		);
		$country_autodetect_addon_txt = '';
		if ( ! class_exists( 'ASPCOUNTRYAUTODETECT_main' ) ) {
			$country_autodetect_addon_txt = sprintf(
				// translators: %s is link to Country Autodetect add-on page
				'<br>' . __( 'You can install the free <a href="%s" target="_blank">Country Autodetect Addon</a> to detect customer\'s country automatically.', 'stripe-payments' ),
				'https://s-plugins.com/stripe-country-autodetect-addon/'
			);
		}
		add_settings_field(
			'popup_default_country',
			__( 'Popup Default Country', 'stripe-payments' ) . $new_api_str,
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug,
			'AcceptStripePayments-global-section',
			array(
				'field' => 'popup_default_country',
				'desc'  => __(
					'Select the default country that should be set on the payment popup window for billing and shipping address.',
					'stripe-payments'
				) . $country_autodetect_addon_txt,
			)
		);

		add_settings_field(
			'hide_state_field',
			__( 'Hide the State Field', 'stripe-payments' ) . $new_api_str,
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug,
			'AcceptStripePayments-global-section',
			array(
				'field' => 'hide_state_field',
				'desc'  => __(
					'Hide the State field on the payment popup window. The State field for the address is an optional field.',
					'stripe-payments'
				),
			)
		);

		add_settings_field(
			'prefill_wp_user_details',
			__( 'Prefill Logged In User Name and Email', 'stripe-payments' ) . $new_api_str,
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug,
			'AcceptStripePayments-global-section',
			array(
				'field' => 'prefill_wp_user_details',
				'desc'  => __(
					'When payment is made by logged in WordPress user, his\her name and email are prefilled to corresponding payment popup fields.',
					'stripe-payments'
				),
			)
		);

		// Credentials section
		add_settings_field(
			'is_live',
			__( 'Live Mode', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug,
			'AcceptStripePayments-credentials-section',
			array(
				'field' => 'is_live',
				'desc'  => __(
					'Check this to run the transaction in live mode. When unchecked it will run in test mode.',
					'stripe-payments'
				),
			)
		);
		add_settings_field(
			'api_publishable_key',
			__( 'Live Stripe Publishable Key', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug,
			'AcceptStripePayments-credentials-section',
			array(
				'field' => 'api_publishable_key',
				'desc'  => '',
			)
		);
		add_settings_field(
			'api_secret_key',
			__( 'Live Stripe Secret Key', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug,
			'AcceptStripePayments-credentials-section',
			array(
				'field' => 'api_secret_key',
				'desc'  => '',
			)
		);
		add_settings_field(
			'api_publishable_key_test',
			__( 'Test Stripe Publishable Key', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug,
			'AcceptStripePayments-credentials-section',
			array(
				'field' => 'api_publishable_key_test',
				'desc'  => '',
			)
		);
		add_settings_field(
			'api_secret_key_test',
			__( 'Test Stripe Secret Key', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug,
			'AcceptStripePayments-credentials-section',
			array(
				'field' => 'api_secret_key_test',
				'desc'  => '',
			)
		);

		//Debug section
		add_settings_field(
			'debug_log_enable',
			__( 'Enable Debug Logging', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug,
			'AcceptStripePayments-debug-section',
			array(
				'field' => 'debug_log_enable',
				'desc'  => __( 'Check this option to enable debug logging. This is useful for troubleshooting post payment failures.', 'stripe-payments' ) .
				'<br /><a href="' . admin_url() . '?asp_action=view_log" target="_blank">' . __( 'View Log', 'stripe-payments' ) . '</a> | <a style="color: red;" id="asp_clear_log_btn" href="#0">' . __( 'Clear Log', 'stripe-payments' ) . '</a>',
			)
		);
		add_settings_field(
			'debug_log_link',
			__( 'Debug Log Shareable Link', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug,
			'AcceptStripePayments-debug-section',
			array(
				'field' => 'debug_log_link',
				'desc'  => __(
					'Normally, the debug log is only accessible to you if you are logged-in as admin. However, in some situations it might be required for support personnel to view it without having admin credentials. This link can be helpful in that situation.',
					'stripe-payments'
				),
			)
		);

		// Email section
		add_settings_field(
			'stripe_receipt_email',
			__( 'Send Receipt Email From Stripe', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug . '-email',
			'AcceptStripePayments-email-section',
			array(
				'field' => 'stripe_receipt_email',
				'desc'  => __( 'If checked, Stripe will send email receipts to your customers whenever they make successful payment.<br /><b>Note:</b> Receipts are not sent in test mode.', 'stripe-payments' ),
			)
		);

		add_settings_field(
			'send_emails_to_buyer',
			__( 'Send Emails to Buyer After Purchase', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug . '-email',
			'AcceptStripePayments-email-section',
			array(
				'field' => 'send_emails_to_buyer',
				'desc'  => __( 'If checked the plugin will send an email to the buyer with the sale details. If digital goods are purchased then the email will contain the download links for the purchased products.', 'stripe-payments' ),
			)
		);
		add_settings_field(
			'buyer_email_type',
			__( 'Buyer Email Content Type', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug . '-email',
			'AcceptStripePayments-email-section',
			array(
				'field' => 'buyer_email_type',
				'desc'  => __( 'Choose which format of email to send.', 'stripe-payments' ),
			)
		);
		add_settings_field(
			'from_email_address',
			__( 'From Email Address', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug . '-email',
			'AcceptStripePayments-email-section',
			array(
				'field' => 'from_email_address',
				'desc'  => __( 'Example: Your Name &lt;sales@your-domain.com&gt; This is the email address that will be used to send the email to the buyer. This name and email address will appear in the from field of the email.', 'stripe-payments' ),
			)
		);
		add_settings_field(
			'buyer_email_subject',
			__( 'Buyer Email Subject', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug . '-email',
			'AcceptStripePayments-email-section',
			array(
				'field' => 'buyer_email_subject',
				'desc'  => __( 'This is the subject of the email that will be sent to the buyer.', 'stripe-payments' ),
			)
		);

		$email_tags_descr = self::get_email_tags_descr();

		add_settings_field(
			'buyer_email_body',
			__( 'Buyer Email Body', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug . '-email',
			'AcceptStripePayments-email-section',
			array(
				'field' => 'buyer_email_body',
				'desc'  => __( 'This is the body of the email that will be sent to the buyer.', 'stripe-payments' ) . ' ' . __( 'Do not change the text within the braces {}. You can use the following email tags in this email body field:', 'stripe-payments' ) . $email_tags_descr,
			)
		);
		add_settings_field(
			'send_emails_to_seller',
			__( 'Send Emails to Seller After Purchase', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug . '-email',
			'AcceptStripePayments-email-section',
			array(
				'field' => 'send_emails_to_seller',
				'desc'  => __( 'If checked the plugin will send an email to the seller with the sale details.', 'stripe-payments' ),
			)
		);
		add_settings_field(
			'seller_notification_email',
			__( 'Notification Email Address', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug . '-email',
			'AcceptStripePayments-email-section',
			array(
				'field' => 'seller_notification_email',
				'desc'  => __( 'This is the email address where the seller will be notified of product sales. You can put multiple email addresses separated by comma (,) in the above field to send the notification to multiple email addresses.', 'stripe-payments' ),
			)
		);

		add_settings_field(
			'seller_email_type',
			__( 'Seller Email Content Type', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug . '-email',
			'AcceptStripePayments-email-section',
			array(
				'field' => 'seller_email_type',
				'desc'  => __( 'Choose which format of email to send.', 'stripe-payments' ),
			)
		);

		add_settings_field(
			'seller_email_subject',
			__( 'Seller Email Subject', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug . '-email',
			'AcceptStripePayments-email-section',
			array(
				'field' => 'seller_email_subject',
				'desc'  => __( 'This is the subject of the email that will be sent to the seller for record.', 'stripe-payments' ),
			)
		);
		add_settings_field(
			'seller_email_body',
			__( 'Seller Email Body', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug . '-email',
			'AcceptStripePayments-email-section',
			array(
				'field' => 'seller_email_body',
				'desc'  => __( 'This is the body of the email that will be sent to the seller.', 'stripe-payments' ) . ' ' . __( 'Do not change the text within the braces {}. You can use the following email tags in this email body field:', 'stripe-payments' ) . $email_tags_descr,
			)
		);

		add_settings_field(
			'send_email_on_error',
			__( 'Send Email On Payment Failure', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug . '-email',
			'AcceptStripePayments-error-email-section',
			array(
				'field' => 'send_email_on_error',
				'desc'  => __( 'If checked, plugin will send a notification email when error occurred during payment processing. The email will be sent to the email address specified below.', 'stripe-payments' ),
			)
		);
		add_settings_field(
			'send_email_on_error_to',
			__( 'Send Error Email To', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug . '-email',
			'AcceptStripePayments-error-email-section',
			array(
				'field' => 'send_email_on_error_to',
				'desc'  => __( 'Enter recipient address of error email.', 'stripe-payments' ),
			)
		);

		// Additional Email Settings
		add_settings_field(
			'enable_email_schedule',
			__( 'Send Emails in Parallel', 'stripe-payments' ) . $new_api_str,
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug . '-email',
			'AcceptStripePayments-additional-email-section',
			array(
				'field' => 'enable_email_schedule',
				'desc'  => __( 'Enabling this option should speed up checkout process for customers. Test this before enabling on production as it may not work properly on some setups.', 'stripe-payments' ),
			)
		);

		// Price Display section
		add_settings_field(
			'price_currency_pos',
			__( 'Currency Position', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug . '-advanced',
			'AcceptStripePayments-price-display',
			array(
				'field' => 'price_currency_pos',
				'desc'  => __( 'This controls the position of the currency symbol.', 'stripe-payments' ),
			)
		);
		add_settings_field(
			'price_decimal_sep',
			__( 'Decimal Separator', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug . '-advanced',
			'AcceptStripePayments-price-display',
			array(
				'field' => 'price_decimal_sep',
				'desc'  => __( 'This sets the decimal separator of the displayed price.', 'stripe-payments' ),
			)
		);
		add_settings_field(
			'price_thousand_sep',
			__( 'Thousand Separator', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug . '-advanced',
			'AcceptStripePayments-price-display',
			array(
				'field' => 'price_thousand_sep',
				'desc'  => __( 'This sets the thousand separator of the displayed price.', 'stripe-payments' ),
			)
		);
		add_settings_field(
			'price_decimals_num',
			__( 'Number of Decimals', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug . '-advanced',
			'AcceptStripePayments-price-display',
			array(
				'field' => 'price_decimals_num',
				'desc'  => __( 'This sets the number of decimal points shown in the displayed price.', 'stripe-payments' ),
			)
		);

		add_settings_field(
			'price_apply_for_input',
			__( 'Apply Separators Settings To Customer Input', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug . '-advanced',
			'AcceptStripePayments-price-display',
			array(
				'field' => 'price_apply_for_input',
				'desc'  => __( 'If enabled, separator settings will be applied to customer input as well. For example, if you have donation button where customers can enter amount and you set "," as decimal separator, customers will need to enter values correspondigly - 12,23 instead of 12.23.', 'stripe-payments' ),
			)
		);

		// Custom Field section
		add_settings_field(
			'custom_field_enabled',
			__( 'Enable For All Buttons and Products', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug . '-advanced',
			'AcceptStripePayments-custom-field',
			array(
				'field' => 'custom_field_enabled',
				'desc'  => __( 'If enabled, makes the following field enabled by default for all buttons and products.', 'stripe-payments' ) . '<br />' .
				__( 'You can control per-product or per-button behaviour by editing the product and selecting enabled or disabled option under the Custom Field section.', 'stripe-payments' ) . '<br />' .
				__( 'View the custom field <a href="https://s-plugins.com/custom-field-settings-feature-stripe-payments-plugin/" target="_blank">usage documentation</a>.', 'stripe-payments' ),
			)
		);
		add_settings_field(
			'custom_field_name',
			__( 'Field Name', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug . '-advanced',
			'AcceptStripePayments-custom-field',
			array(
				'field' => 'custom_field_name',
				'desc'  => __( 'Enter name for the field. It will be displayed in order info and emails.', 'stripe-payments' ),
			)
		);
		add_settings_field(
			'custom_field_descr',
			__( 'Field Description', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug . '-advanced',
			'AcceptStripePayments-custom-field',
			array(
				'field' => 'custom_field_descr',
				'desc'  => __( 'Enter field description. It will be displayed for users to let them know what is required from them. Leave it blank if you don\'t want to display description.', 'stripe-payments' ),
			)
		);
		add_settings_field(
			'custom_field_descr_location',
			__( 'Text Field Description Location', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug . '-advanced',
			'AcceptStripePayments-custom-field',
			array(
				'field' => 'custom_field_descr_location',
				'desc'  => __( 'Select field description location. Placeholder: description is displayed inside text input (default). Below Input: description is displayed below text input.', 'stripe-payments' ),
			)
		);
		add_settings_field(
			'custom_field_position',
			__( 'Position', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug . '-advanced',
			'AcceptStripePayments-custom-field',
			array(
				'field' => 'custom_field_position',
				'desc'  => __( 'Select custom field position.', 'stripe-payments' ) . ' ' . __( 'This option is for legacy API only.', 'stripe-payments' ),
			)
		);
		add_settings_field(
			'custom_field_type',
			__( 'Field Type', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug . '-advanced',
			'AcceptStripePayments-custom-field',
			array(
				'field' => 'custom_field_type',
				'desc'  => __( 'Select custom field type.', 'stripe-payments' ),
			)
		);
		add_settings_field(
			'custom_field_validation',
			__( 'Field Validation', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug . '-advanced',
			'AcceptStripePayments-custom-field',
			array(
				'field' => 'custom_field_validation',
				'desc'  => __( 'Select custom field validation if needed.', 'stripe-payments' ),
			)
		);
		add_settings_field(
			'custom_field_mandatory',
			__( 'Mandatory', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug . '-advanced',
			'AcceptStripePayments-custom-field',
			array(
				'field' => 'custom_field_mandatory',
				'desc'  => __( "If enabled, makes the field mandatory - user can't proceed with the payment before it's filled.", 'stripe-payments' ),
			)
		);

		//Terms and Conditions
		add_settings_field(
			'tos_enabled',
			__( 'Enable Terms and Conditions', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug . '-advanced',
			'AcceptStripePayments-tos',
			array(
				'field' => 'tos_enabled',
				'desc'  => __( 'Enable Terms and Conditions checkbox.', 'stripe-payments' ),
			)
		);
		add_settings_field(
			'tos_text',
			__( 'Checkbox Text', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug . '-advanced',
			'AcceptStripePayments-tos',
			array(
				'field' => 'tos_text',
				'desc'  => __( 'Text to be displayed on checkbox. It accepts HTML code so you can put a link to your terms and conditions page.', 'stripe-payments' ),
			)
		);
		add_settings_field(
			'tos_store_ip',
			__( 'Store Customer\'s IP Address', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug . '-advanced',
			'AcceptStripePayments-tos',
			array(
				'field' => 'tos_store_ip',
				'desc'  => __( 'If enabled, customer\'s IP address from which TOS were accepted will be stored in order info.', 'stripe-payments' ),
			)
		);
		add_settings_field(
			'tos_position',
			__( 'Position', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug . '-advanced',
			'AcceptStripePayments-tos',
			array(
				'field' => 'tos_position',
				'desc'  => __( 'Select TOS checkbox position.', 'stripe-payments' ),
			)
		);

		// Additional Settings
		add_settings_field(
			'use_old_checkout_api1',
			__( 'Enable Legacy Checkout API', 'stripe-payments' ),
			array( $this, 'settings_field_callback' ),
			$this->plugin_slug . '-advanced',
			'AcceptStripePayments-additional-settings',
			array(
				'field' => 'use_old_checkout_api1',
				'desc'  => __( "Use the legacy API to process payments. Note that the legacy API is not compatible with 3-D Secure and EU's Strong Customer Authentication (SCA) requirements. Stripe may disable this legacy API in the future. If there is a bug in the new API, then continue to use the legacy API while we fix the bug.", 'stripe-payments' ),
			)
		);
		add_settings_field(
			'new_product_edit_interface',
			__( 'Enable Compact Product Edit Interface', 'stripe-payments' ),
			array( $this, 'settings_field_callback' ),
			$this->plugin_slug . '-advanced',
			'AcceptStripePayments-additional-settings',
			array(
				'field' => 'new_product_edit_interface',
				'desc'  => __( 'Switch to the compact product edit interface.', 'stripe-payments' ),
			)
		);
		add_settings_field(
			'frontend_prefetch_scripts',
			__( 'Prefetch Payment Popup Scripts', 'stripe-payments' ) . $new_api_str,
			array( $this, 'settings_field_callback' ),
			$this->plugin_slug . '-advanced',
			'AcceptStripePayments-additional-settings',
			array(
				'field' => 'frontend_prefetch_scripts',
				'desc'  => __(
					'Enable this to speed up payment popup display after customer clicks payment button.',
					'stripe-payments'
				),
			)
		);
		add_settings_field(
			'dont_create_order',
			__( 'Don\'t Create Order', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug . '-advanced',
			'AcceptStripePayments-additional-settings',
			array(
				'field' => 'dont_create_order',
				'desc'  => __( 'If enabled, no transaction info is saved to the orders menu of the plugin. The transaction data will still be available in your Stripe dashboard. Useful if you don\'t want to store purchase and customer data in your site.', 'stripe-payments' ),
			)
		);
		add_settings_field(
			'allowed_currencies',
			__( 'Allowed Currencies', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug . '-advanced',
			'AcceptStripePayments-additional-settings',
			array(
				'field' => 'allowed_currencies',
			)
		);
		add_settings_field(
			'pp_additional_css',
			__( 'Payment Popup Additional CSS', 'stripe-payments' ),
			array( &$this, 'settings_field_callback' ),
			$this->plugin_slug . '-advanced',
			'AcceptStripePayments-additional-settings',
			array(
				'field' => 'pp_additional_css',
				'desc'  => __( 'Enter additional CSS code that would be added to payment popup page.', 'stripe-payments' ),
			)
		);

		//Experimental Settings
		add_settings_field(
			'dont_use_stripe_php_sdk',
			__( 'Do Not Use Stripe PHP SDK Library', 'stripe-payments' ),
			array( $this, 'settings_field_callback' ),
			$this->plugin_slug . '-advanced',
			'AcceptStripePayments-experimental-settings',
			array(
				'field' => 'dont_use_stripe_php_sdk',
				'desc'  => __( 'Enable this if you\'re experiencing conflicts with other plugins that use Stripe PHP SDK Library. Internal Stripe API wrapper would be used instead.', 'stripe-payments' ),
			)
		);

		add_settings_field(
			'disable_security_token_check',
			__( 'Disable Security Token Check', 'stripe-payments' ),
			array( $this, 'settings_field_callback' ),
			$this->plugin_slug . '-advanced',
			'AcceptStripePayments-experimental-settings',
			array(
				'field' => 'disable_security_token_check',
				'desc'  => __( 'Helps when you are getting "Invalid Security Token" errors due to caching being done on your server.', 'stripe-payments' ) . '<br>' .
				__( 'It is not recommended to disable security tokens. You should change your server cache settings to not cache payment popup URLs.', 'stripe-payments' ),
			)
		);

		add_settings_field(
			'show_incomplete_orders',
			__( 'Show Incomplete Orders', 'stripe-payments' ),
			array( $this, 'settings_field_callback' ),
			$this->plugin_slug . '-advanced',
			'AcceptStripePayments-experimental-settings',
			array(
				'field' => 'show_incomplete_orders',
				'desc'  => __( 'If enabled, incomplete transactions are also displayed in the Orders listing page of the plugin. If Stripe declines a card transaction because the customer entered incorrect card info, it will be considered to be an incomplete transaction.', 'stripe-payments' ),
			)
		);

		add_settings_field(
			'disable_3ds_iframe',
			__( 'Disable 3D Secure Iframe', 'stripe-payments' ),
			array( $this, 'settings_field_callback' ),
			$this->plugin_slug . '-advanced',
			'AcceptStripePayments-experimental-settings',
			array(
				'field' => 'disable_3ds_iframe',
				'desc'  => __( 'If enabled, payment popup redirects browser to 3D Secure check page instead of showing it in an iframe. This might help if your server configuration prevents displaying iframes from other websites.', 'stripe-payments' ),
			)
		);

		add_settings_field(
			'disable_buttons_before_js_loads',
			__( 'Disable Buttons Before Javascript Loads', 'stripe-payments' ),
			array( $this, 'settings_field_callback' ),
			$this->plugin_slug . '-advanced',
			'AcceptStripePayments-experimental-settings',
			array(
				'field' => 'disable_buttons_before_js_loads',
				'desc'  => __( 'If enabled, payment buttons are not clickable until Javascript libraries are loaded on page view. This prevents "Invalid Stripe Token" errors on some configurations.', 'stripe-payments' ),
			)
		);

	}

	function tos_description() {
		echo '<p>' . __( 'This section allows you to configure Terms and Conditions or Privacy Policy that customer must accept before making payment. This, for example, can be used to comply with EU GDPR.', 'stripe-payments' ) . '</p>';
	}

	public function experemintal_section_description() {
		echo '<p>' . esc_html( __( "Warning: don't change options in this section unless you know what you're doing!", 'stripe-payments' ) ) . '</p>';
	}


	static function get_currency_options( $selected_value = '', $show_default = true ) {

		$currencies = AcceptStripePayments::get_currencies();

		if ( false === $show_default ) {
			unset( $currencies[''] );
		}
		$opt_tpl = '<option value="%curr_code%"%selected%>%curr_name%</option>';
		$opts    = '';
		foreach ( $currencies as $key => $value ) {
			$selected = $selected_value === $key ? ' selected' : '';
			$opts    .= str_replace( array( '%curr_code%', '%curr_name%', '%selected%' ), array( $key, $value[0], $selected ), $opt_tpl );
		}

		return $opts;
	}

	public function get_checkout_lang_options( $selected_value = '' ) {
		$languages_arr = array(
			'ar' => __( 'Arabic', 'stripe-payments' ),
			'he' => __( 'Hebrew', 'stripe-payments' ),
			'lv' => __( 'Latvian', 'stripe-payments' ),
			'lt' => __( 'Lithuanian', 'stripe-payments' ),
			'ms' => __( 'Malay', 'stripe-payments' ),
			'nb' => __( 'Norwegian Bokmal', 'stripe-payments' ),
			'pl' => __( 'Polish', 'stripe-payments' ),
			'pt' => __( 'Portuguese', 'stripe-payments' ),
			'ru' => __( 'Russian', 'stripe-payments' ),
			'da' => __( 'Danish', 'stripe-payments' ),
			'nl' => __( 'Dutch', 'stripe-payments' ),
			'en' => __( 'English', 'stripe-payments' ),
			'fi' => __( 'Finnish', 'stripe-payments' ),
			'fr' => __( 'French', 'stripe-payments' ),
			'de' => __( 'German', 'stripe-payments' ),
			'it' => __( 'Italian', 'stripe-payments' ),
			'ja' => __( 'Japanese', 'stripe-payments' ),
			'no' => __( 'Norwegian', 'stripe-payments' ),
			'zh' => __( 'Simplified Chinese', 'stripe-payments' ),
			'es' => __( 'Spanish', 'stripe-payments' ),
			'sv' => __( 'Swedish', 'stripe-payments' ),
		);
		asort( $languages_arr );
		$data_arr = array( '' => __( 'Autodetect', 'stripe-payments' ) );
		$data_arr = $data_arr + $languages_arr;

		$opt_tpl = '<option value="%val%"%selected%>%name%</option>';
		$opts    = false === $selected_value ? '<option value="" selected>' . __( '(Default)', 'stripe-payments' ) . '</option>' : '';
		foreach ( $data_arr as $key => $value ) {
			$selected = $selected_value === $key ? ' selected' : '';
			$opts    .= str_replace( array( '%val%', '%name%', '%selected%' ), array( $key, $value, $selected ), $opt_tpl );
		}

		return $opts;
	}

	/**
	 * Settings HTML
	 */
	public function settings_field_callback( $args ) {

		$settings = (array) get_option( 'AcceptStripePayments-settings' );

		$field = isset( $args['field'] ) ? $args['field'] : '';

		$field_value = esc_attr( isset( $settings[ $field ] ) ? $settings[ $field ] : '' );

		$desc = isset( $args['desc'] ) ? $args['desc'] : '';

		$size = isset( $args['size'] ) ? $args['size'] : 40;

		$addon_field = apply_filters( 'asp-admin-settings-addon-field-display', $field, $field_value );

		if ( is_array( $addon_field ) ) {
			$field      = $addon_field['field'];
			$field_name = $addon_field['field_name'];
		}

		switch ( $field ) {
			case 'checkbox':
				echo "<input type='checkbox' name='AcceptStripePayments-settings[{$field_name}]' value='1' " . ( $field_value ? 'checked=checked' : '' ) . " /><p class=\"description\">{$desc}</p>";
				break;
			case 'custom':
				echo $addon_field['field_data'];
				echo "<p class=\"description\">{$desc}</p>";
				break;
			case 'custom_field_type':
				echo "<select name='AcceptStripePayments-settings[{$field}]'>";
				echo "<option value='text'" . ( $field_value === 'text' ? ' selected' : '' ) . '>' . __( 'Text', 'stripe-payments' ) . '</option>';
				echo "<option value='checkbox'" . ( $field_value === 'checkbox' ? ' selected' : '' ) . '>' . __( 'Checkbox', 'stripe-payments' ) . '</option>';
				echo '</select>';
				echo "<p class=\"description\">{$desc}</p>";
				break;
			case 'custom_field_descr_location':
				echo "<select name='AcceptStripePayments-settings[{$field}]'>";
				echo "<option value='placeholder'" . ( $field_value === 'placeholder' ? ' selected' : '' ) . '>' . __( 'Placeholder', 'stripe-payments' ) . '</option>';
				echo "<option value='below'" . ( $field_value === 'below' ? ' selected' : '' ) . '>' . __( 'Below Input', 'stripe-payments' ) . '</option>';
				echo '</select>';
				echo "<p class=\"description\">{$desc}</p>";
				break;
			case 'custom_field_validation':
				echo "<select name='AcceptStripePayments-settings[{$field}]'>";
				echo "<option value=''" . ( empty( $field_value ) ? ' selected' : '' ) . '>' . __( 'No Validation', 'stripe-payments' ) . '</option>';
				echo "<option value='num'" . ( $field_value === 'num' ? ' selected' : '' ) . '>' . __( 'Numbers Only', 'stripe-payments' ) . '</option>';
				echo "<option value='custom'" . ( $field_value === 'custom' ? ' selected' : '' ) . '>' . __( 'Custom Validation', 'stripe-payments' ) . '</option>';
				echo '</select>';
				echo ' <div class="wp-asp-help"><i class="dashicons dashicons-editor-help"></i>'
				. '<div class="wp-asp-help-text">'
				. '<p><strong>' . __( 'No Validation', 'stripe-payments' ) . '</strong>: ' . __( 'no validation performed', 'stripe-payments' ) . '</p>'
				. '<p><strong>' . __( 'Numbers Only', 'stripe-payments' ) . '</strong>: ' . __( 'only accepts numbers 0-9', 'stripe-payments' ) . '</p>'
				// translators: %s is a link to JavaScript RegExp page
				. '<p><strong>' . __( 'Custom Validation', 'stripe-payments' ) . '</strong>: ' . sprintf( __( 'you can enter your own validation rules using <a href="%s" target="_blank">JavaScript RegExp</a> format.', 'stripe-payments' ), 'https://developer.mozilla.org/en-US/docs/Web/JavaScript/Guide/Regular_Expressions' ) . '</p>'
				. '</div>'
				. '</div>';
				echo "<p class=\"description\">{$desc}</p>";
				$opts         = get_option( 'AcceptStripePayments-settings' );
				$custom_regex = '';
				if ( ! empty( $opts['custom_field_custom_validation_regex'] ) ) {
					$custom_regex = $opts['custom_field_custom_validation_regex'];
				}
				$custom_regex_err_msg = __( 'Please enter valid data', 'stripe-payments' );
				if ( ! empty( $opts['custom_field_custom_validation_err_msg'] ) ) {
					$custom_regex_err_msg = $opts['custom_field_custom_validation_err_msg'];
				}
				echo '<div class="wp-asp-custom-field-validation-custom-input-cont" style="display: none;">'
				. '<input type="text" size="40" name="AcceptStripePayments-settings[custom_field_custom_validation_regex]" value="' . esc_attr( $custom_regex ) . '">'
				// translators: %s is a link to JavaScript RegExp page
				. '<p class="description">' . sprintf( __( 'Enter your custom validation rule using <a href="%s" target="_blank">JavaScript RegExp</a> format. No need to enclose those using "/".', 'stripe-payments' ), 'https://developer.mozilla.org/en-US/docs/Web/JavaScript/Guide/Regular_Expressions' )
				. '<br/>' . __( 'Example RegExp to allow numbers only: ^[0-9]+$', 'stripe-payments' )
				. '</p>'
				. '<input type="text" size="40" name="AcceptStripePayments-settings[custom_field_custom_validation_err_msg]" value="' . $custom_regex_err_msg . '">'
				. '<p class="description">' . __( 'Error message to display if validation is not passed.', 'stripe-payments' ) . '</p>'
				. '</div>';
				break;
			case 'tos_position':
			case 'custom_field_position':
				echo "<select name='AcceptStripePayments-settings[{$field}]'>";
				echo "<option value='above'" . ( $field_value === 'above' || empty( $field_value ) ? ' selected' : '' ) . '>' . __( 'Above Button', 'stripe-payments' ) . '</option>';
				echo "<option value='below'" . ( $field_value === 'below' ? ' selected' : '' ) . '>' . __( 'Below Button', 'stripe-payments' ) . '</option>';
				echo '</select>';
				echo "<p class=\"description\">{$desc}</p>";
				break;
			case 'price_apply_for_input':
			case 'tos_enabled':
			case 'tos_store_ip':
			case 'debug_log_enable':
			case 'send_emails_to_seller':
			case 'send_emails_to_buyer':
			case 'stripe_receipt_email':
			case 'send_email_on_error':
			case 'use_new_button_method':
			case 'is_live':
			case 'disable_remember_me':
			case 'use_old_checkout_api1':
			case 'new_product_edit_interface':
			case 'disable_3ds_iframe':
			case 'disable_buttons_before_js_loads':
			case 'show_incomplete_orders':
			case 'disable_security_token_check':
			case 'dont_save_card':
			case 'custom_field_mandatory':
			case 'enable_zip_validation':
			case 'dont_create_order':
			case 'enable_email_schedule':
			case 'frontend_prefetch_scripts':
			case 'hide_state_field':
				echo "<input type='checkbox' name='AcceptStripePayments-settings[{$field}]' value='1' " . ( $field_value ? 'checked=checked' : '' ) . " /><p class=\"description\">{$desc}</p>";
				break;
			case 'dont_use_stripe_php_sdk':
				$desc = apply_filters( 'asp_int_dont_use_stripe_php_sdk_option_desc', $desc );
				echo "<input type='checkbox' name='AcceptStripePayments-settings[{$field}]' value='1' " . ( $field_value ? 'checked=checked' : '' ) . " /><p class=\"description\">{$desc}</p>";
				break;
			case 'prefill_wp_user_details':
				echo "<input type='checkbox' name='AcceptStripePayments-settings[{$field}]' value='1' " . ( $field_value ? 'checked=checked' : '' ) . " /><p class=\"description\">{$desc}</p>";
				$last_name_first = $this->asp_main->get_setting( 'prefill_wp_user_last_name_first' );
				echo '<label><input type="checkbox" name="AcceptStripePayments-settings[prefill_wp_user_last_name_first]"' . ( $last_name_first ? ' checked="checked"' : '' ) . '> ' . esc_html__( 'Last Name First', 'stripe-payments' ) . '</label>';
				echo '<p class="description">' . esc_html__( 'When enabled, last name is placed before first name.', 'stripe-payments' ) . '</p>';
				break;
			case 'custom_field_enabled':
				echo "<input type='checkbox' name='AcceptStripePayments-settings[{$field}]' value='1' " . ( $field_value ? 'checked=checked' : '' ) . " /><p class=\"description\">{$desc}</p>";
				//do action so ACF addon can display its message if needed
				do_action( 'asp_acf_settings_page_display_msg' );
				break;
			case 'buyer_email_type':
			case 'seller_email_type':
				$checked_text = empty( $field_value ) || ( 'text' === $field_value ) ? ' selected' : '';
				$checked_html = 'html' === $field_value ? ' selected' : '';
				echo '<select name="AcceptStripePayments-settings[' . $field . ']">';
				echo sprintf( '<option value="text"%s>' . __( 'Plain Text', 'stripe-payments' ) . '</option>', $checked_text );
				echo sprintf( '<option value="html"%s>' . __( 'HTML', 'stripe-payments' ) . '</option>', $checked_html );
				echo '</select>';
				echo "<p class=\"description\">{$desc}</p>";
				break;
			case 'buyer_email_body':
			case 'seller_email_body':
				add_filter( 'wp_default_editor', array( $this, 'set_default_editor' ) );
				wp_editor(
					html_entity_decode( $field_value ),
					$field,
					array(
						'textarea_name' => 'AcceptStripePayments-settings[' . $field . ']',
						'teeny'         => true,
					)
				);
				remove_filter( 'wp_default_editor', array( $this, 'set_default_editor' ) );
				echo "<p class=\"description\">{$desc}</p>";
				break;
			case 'products_page_id':
				//We save the products page ID internally but we show the URL of that page to the user (its user-friendly).
				$products_page_id  = $field_value;
				$products_page_url = get_permalink( $products_page_id );
				//show the URL in a text field for display purpose. This field's value can't be updated as we store the page ID internally.
				echo "<input type='text' name='asp_products_page_url_value' value='{$products_page_url}' size='{$size}' /> <p class=\"description\">{$desc}</p>";
				break;
			case 'currency_code':
				echo '<select name="AcceptStripePayments-settings[' . $field . ']" id="wp_asp_curr_code">';
				echo self::get_currency_options( $field_value, false );
				echo '</select>';
				//echo "<p class=\"description\">{$desc}</p>";
				break;
			case 'currency_symbol':
				echo '<input type="text" name="AcceptStripePayments-settings[' . $field . ']" value="" id="wp_asp_curr_symb">';
				echo "<p class=\"description\">{$desc}</p>";
				break;
			case 'allowed_currencies':
				$all_curr = ASP_Utils::get_currencies();
				unset( $all_curr[''] );
				$allowed_curr = empty( $field_value ) ? $all_curr : json_decode( html_entity_decode( $field_value ), true );
				if ( empty( array_diff_key( $all_curr, $allowed_curr ) ) ) {
					$allowed = __( 'All', 'stripe-payments' );
				} else {
					$allowed = __( 'Selected', 'stripe-payments' );
				}
				echo $allowed;
				echo '<div id="wp-asp-allowed-currencies-cont">';
				echo '<a href="#" class="wp-asp-toggle toggled-off">' . __( 'Click here to select currencies', 'stripe-payments' ) . '</a>';
				echo '<div class="wp-asp-allowed-currencies hidden">';
				echo '<div class="wp-asp-allowed-currencies-buttons-cont">';
				echo '<button type="button" class="wp-asp-curr-sel-all-btn">' . __( 'Select All', 'stripe-payments' ) . '</button>';
				echo '<button type="button" class="wp-asp-curr-sel-none-btn">' . __( 'Select None', 'stripe-payments' ) . '</button>';
				echo '<button type="button" class="wp-asp-curr-sel-invert-btn">' . __( 'Invert Selection', 'stripe-payments' ) . '</button>';
				echo '</div>';
				echo '<div class="wp-asp-allowed-currencies-sel">';
				foreach ( $all_curr as $code => $curr ) {
					$checked = '';
					if ( isset( $allowed_curr[ $code ] ) ) {
						$checked = ' checked';
					}
					echo sprintf( '<div><label><input type="checkbox" name="AcceptStripePayments-settings[allowed_currencies][%s]" value="1"%s> %s</label></div>', $code, $checked, $curr[0] );
				}
				echo '</div>';
				echo '</div>';
				echo '</div>';
				echo '<p class="description">' . __( 'You can select currencies you want to be available for your customers for variable currencies products. Useful for donation type products.', 'stripe-payments' ) . '</p>';
				break;
			case 'price_decimals_num':
				echo '<input type="number" min="0" step="1" max="5" name="AcceptStripePayments-settings[' . $field . ']" value="' . esc_attr( $field_value ) . '"';
				break;
			case 'checkout_lang':
				// list of supported languages can be found here: https://stripe.com/docs/checkout#supported-languages
				echo '<select name="AcceptStripePayments-settings[' . $field . ']">';
				echo $this->get_checkout_lang_options( $field_value );
				echo '</select>';
				echo "<p class=\"description\">{$desc}</p>";
				break;
			case 'popup_default_country':
				echo '<select name="AcceptStripePayments-settings[' . $field . ']">';
				echo ASP_Utils::get_countries_opts( $field_value );
				echo '</select>';
				echo "<p class=\"description\">{$desc}</p>";
				break;
			case 'price_currency_pos':
				?>
<select name="AcceptStripePayments-settings[<?php echo $field; ?>]">
	<option value="left" <?php echo ( 'left' === $field_value ) ? ' selected' : ''; ?>><?php _ex( 'Left', 'Currency symbol position', 'stripe-payments' ); ?></option>
	<option value="right" <?php echo ( 'right' === $field_value ) ? ' selected' : ''; ?>><?php _ex( 'Right', 'Currency symbol position', 'stripe-payments' ); ?></option>
</select>
<p class="description"><?php echo $desc; ?></p>
				<?php
				break;
			case 'pp_additional_css':
				echo sprintf( '<textarea name="AcceptStripePayments-settings[%s]" rows="8" cols="70" style="resize:both;max-width:100%%;min-height:100px;">%s</textarea>', $field, $field_value );
				echo '<p class="description">' . $desc . '</p>';
				break;
			case 'tos_text':
				echo '<textarea name="AcceptStripePayments-settings[tos_text]" rows="4" cols="70">' . $field_value . '</textarea>';
				echo '<p class="description">' . $desc . '</p>';
				break;
			case 'debug_log_link':
				//check if we have token generated
				$token = $this->asp_main->get_setting( 'debug_log_access_token' );
				if ( ! $token ) {
					//let's generate debug log access token
					$token                          = substr( md5( uniqid() ), 16 );
					$opts                           = get_option( 'AcceptStripePayments-settings' );
					$opts['debug_log_access_token'] = $token;
					unregister_setting( 'AcceptStripePayments-settings-group', 'AcceptStripePayments-settings' );
					update_option( 'AcceptStripePayments-settings', $opts );
				}
				echo '<input type="text" size="70" class="asp-debug-log-link asp-select-on-click" readonly value="' . admin_url() . '?asp_action=view_log&token=' . $token . '">';
				echo '<p class="description">' . $desc . '</p>';
				?>
				<?php
				break;
			default:
				echo "<input type='text' name='AcceptStripePayments-settings[{$field}]' value='{$field_value}' size='{$size}' /> <p class=\"description\">{$desc}</p>";
				break;
		}
	}

	public function set_default_editor( $r ) {
		$r = 'html';
		return $r;
	}

	/**
	 * Validates the admin data
	 *
	 * @since    1.0.0
	 */
	public function settings_sanitize_field_callback( $input ) {

		$output = get_option( 'AcceptStripePayments-settings' );

		$output = apply_filters( 'apm-admin-settings-sanitize-field', $output, $input );

		$output ['price_apply_for_input'] = empty( $input['price_apply_for_input'] ) ? 0 : 1;

		$output ['tos_enabled'] = empty( $input['tos_enabled'] ) ? 0 : 1;

		$output ['tos_position'] = sanitize_text_field( $input['tos_position'] );

		$output ['tos_store_ip'] = empty( $input['tos_store_ip'] ) ? 0 : 1;

		$output ['enable_email_schedule'] = empty( $input['enable_email_schedule'] ) ? 0 : 1;

		$output ['frontend_prefetch_scripts'] = empty( $input['frontend_prefetch_scripts'] ) ? 0 : 1;

		$output['pp_additional_css'] = ! empty( $input['pp_additional_css'] ) ? $input['pp_additional_css'] : '';

		$output['tos_text'] = ! empty( $input['tos_text'] ) ? $input['tos_text'] : '';

		$output['custom_field_enabled'] = empty( $input['custom_field_enabled'] ) ? 0 : 1;

		$output['custom_field_type'] = empty( $input['custom_field_type'] ) ? 'text' : sanitize_text_field( $input['custom_field_type'] );

		$output['custom_field_name'] = empty( $input['custom_field_name'] ) ? '' : sanitize_text_field( $input['custom_field_name'] );

		$output['custom_field_descr'] = empty( $input['custom_field_descr'] ) ? '' : $input['custom_field_descr'];

		$output['custom_field_descr_location'] = empty( $input['custom_field_descr_location'] ) ? 'placeholder' : $input['custom_field_descr_location'];

		$output ['custom_field_position'] = sanitize_text_field( $input['custom_field_position'] );

		$output ['custom_field_validation'] = sanitize_text_field( $input['custom_field_validation'] );

		if ( ! empty( $output['custom_field_validation'] ) && $output['custom_field_validation'] === 'custom' ) {
			$custom_regex = sanitize_text_field( $input['custom_field_custom_validation_regex'] );
			$regex_error  = false;
			try {
				if ( preg_match( '/' . $custom_regex . '/', '' ) === false ) {
					$regex_error = true;
				}
			} catch ( Exception $ex ) {
				$regex_error = true;
			}
			if ( $regex_error ) {
				//error occurred during regex test
				// translators: %s is replaced by invalid RegExp value
				add_settings_error( 'custom_field_custom_validation_regex', 'custom_field_custom_validation_regex', sprintf( __( 'Invalid custom RegExp for Custom Field validation provided: %s', 'stripe-payments' ), $custom_regex ) );
			}
			$output['custom_field_custom_validation_regex']   = $custom_regex;
			$output['custom_field_custom_validation_err_msg'] = sanitize_text_field( $input['custom_field_custom_validation_err_msg'] );
		}

		$output['custom_field_mandatory'] = empty( $input['custom_field_mandatory'] ) ? 0 : 1;

		$output['is_live'] = empty( $input['is_live'] ) ? 0 : 1;

		$output['debug_log_enable'] = empty( $input['debug_log_enable'] ) ? 0 : 1;

		$output['dont_save_card'] = empty( $input['dont_save_card'] ) ? 0 : 1;

		$output['disable_remember_me'] = empty( $input['disable_remember_me'] ) ? 0 : 1;

		$output['use_new_button_method'] = empty( $input['use_new_button_method'] ) ? 0 : 1;

		$output['prefill_wp_user_details']         = empty( $input['prefill_wp_user_details'] ) ? 0 : 1;
		$output['prefill_wp_user_last_name_first'] = empty( $input['prefill_wp_user_last_name_first'] ) ? 0 : 1;

		$output['hide_state_field'] = empty( $input['hide_state_field'] ) ? 0 : 1;

		$output['send_emails_to_buyer'] = empty( $input['send_emails_to_buyer'] ) ? 0 : 1;

		$output['stripe_receipt_email'] = empty( $input['stripe_receipt_email'] ) ? 0 : 1;

		$output['send_email_on_error'] = empty( $input['send_email_on_error'] ) ? 0 : 1;

		$output['send_emails_to_seller'] = empty( $input['send_emails_to_seller'] ) ? 0 : 1;

		$output['send_email_on_error_to'] = sanitize_text_field( $input['send_email_on_error_to'] );

		$output['disable_3ds_iframe'] = empty( $input['disable_3ds_iframe'] ) ? 0 : 1;

		$output['disable_buttons_before_js_loads'] = empty( $input['disable_buttons_before_js_loads'] ) ? 0 : 1;

		$output['show_incomplete_orders'] = empty( $input['show_incomplete_orders'] ) ? 0 : 1;

		$output['disable_security_token_check'] = empty( $input['disable_security_token_check'] ) ? 0 : 1;

		$output['dont_use_stripe_php_sdk'] = empty( $input['dont_use_stripe_php_sdk'] ) ? 0 : 1;

		$output['use_old_checkout_api1'] = empty( $input['use_old_checkout_api1'] ) ? 0 : 1;

		$output['new_product_edit_interface'] = empty( $input['new_product_edit_interface'] ) ? 0 : 1;

		$output['dont_create_order'] = empty( $input['dont_create_order'] ) ? 0 : 1;

		$output['enable_zip_validation'] = empty( $input['enable_zip_validation'] ) ? 0 : 1;

		$input['api_secret_key']           = sanitize_text_field( $input['api_secret_key'] );
		$input ['api_publishable_key']     = sanitize_text_field( $input['api_publishable_key'] );
		$input['api_secret_key_test']      = sanitize_text_field( $input['api_secret_key_test'] );
		$input['api_publishable_key_test'] = sanitize_text_field( $input['api_publishable_key_test'] );

		if ( ! empty( $output['is_live'] ) ) {
			if ( empty( $input['api_secret_key'] ) || empty( $input['api_publishable_key'] ) ) {
				add_settings_error( 'AcceptStripePayments-settings', 'invalid-credentials', __( 'You must fill Live API credentials for plugin to work correctly.', 'stripe-payments' ) );
			}
		} else {
			if ( empty( $input['api_secret_key_test'] ) || empty( $input['api_publishable_key_test'] ) ) {
				add_settings_error( 'AcceptStripePayments-settings', 'invalid-credentials', __( 'You must fill Test API credentials for plugin to work correctly.', 'stripe-payments' ) );
			}
		}

		if ( ! empty( $input['checkout_url'] ) ) {
			$output['checkout_url'] = $input['checkout_url'];
		} else {
			add_settings_error( 'AcceptStripePayments-settings', 'invalid-checkout_url', __( 'Please specify a checkout page.', 'stripe-payments' ) );
		}

		if ( ! empty( $input['button_text'] ) ) {
			$output['button_text'] = $input['button_text'];
		} else {
			add_settings_error( 'AcceptStripePayments-settings', 'invalid-button-text', __( 'Button text should not be empty.', 'stripe-payments' ) );
		}

		if ( ! empty( $input['popup_button_text'] ) ) {
			$output['popup_button_text'] = $input['popup_button_text'];
		} else {
			// translators: %s is not a placeholder
			$output['popup_button_text'] = __( 'Pay %s', 'stripe-payments' );
		}

		if ( ! empty( $input['currency_code'] ) ) {
						$input['currency_code'] = sanitize_text_field( $input['currency_code'] );
			$output['currency_code']            = sanitize_text_field( $input['currency_code'] );
			$currencies                         = AcceptStripePayments::get_currencies();
			$opts                               = get_option( 'AcceptStripePayments-settings' );
			if ( isset( $opts['custom_currency_symbols'] ) && is_array( $opts['custom_currency_symbols'] ) ) {
				$custom_curr_symb = $opts['custom_currency_symbols'];
			} else {
				$custom_curr_symb = array();
			}
			if ( $currencies[ $output['currency_code'] ][1] !== $input['currency_symbol'] ) {
				$custom_curr_symb[ $output['currency_code'] ] = array( $currencies[ $output['currency_code'] ][0], $input['currency_symbol'] );
			} else {
				if ( isset( $custom_curr_symb['currency_code'] ) ) {
					unset( $custom_curr_symb['currency_code'] );
				}
			}
			$output['custom_currency_symbols'] = $custom_curr_symb;
		} else {
			add_settings_error( 'AcceptStripePayments-settings', 'invalid-currency-code', __( 'You must specify payment currency.', 'stripe-payments' ) );
		}

		$curr_arr = ASP_Utils::get_currencies();
		if ( ! empty( $input['allowed_currencies'] ) ) {
			if ( empty( array_diff_key( $curr_arr, $input['allowed_currencies'] ) ) ) {
				$output['allowed_currencies'] = false;
			} else {
				$output['allowed_currencies'] = is_array( $input['allowed_currencies'] ) ? wp_json_encode( $input['allowed_currencies'] ) : false;
			}
		}

		$output['checkout_lang'] = $input['checkout_lang'];

		$output['popup_default_country'] = $input['popup_default_country'];

		$output['api_publishable_key'] = $input['api_publishable_key'];

		$output['api_secret_key'] = $input['api_secret_key'];

		$output['api_publishable_key_test'] = $input['api_publishable_key_test'];

		$output['api_secret_key_test'] = $input['api_secret_key_test'];

		$output['buyer_email_type'] = $input['buyer_email_type'];

		$output['seller_email_type'] = $input['seller_email_type'];

		if ( ! empty( $input['from_email_address'] ) ) {
			$output['from_email_address'] = $input['from_email_address'];
		} else {
			add_settings_error( 'AcceptStripePayments-settings', 'invalid-from-email-address', __( 'You must specify from email address.', 'stripe-payments' ) );
		}

		if ( ! empty( $input['buyer_email_subject'] ) ) {
			$output['buyer_email_subject'] = $input['buyer_email_subject'];
		} else {
			add_settings_error( 'AcceptStripePayments-settings', 'invalid-buyer-email-subject', __( 'You must specify buyer email subject.', 'stripe-payments' ) );
		}

		if ( ! empty( $input['buyer_email_body'] ) ) {
			$output['buyer_email_body'] = $input['buyer_email_body'];
		} else {
			add_settings_error( 'AcceptStripePayments-settings', 'invalid-buyer-email-body', __( 'You must fill in buyer email body.', 'stripe-payments' ) );
		}

		if ( ! empty( $input['seller_notification_email'] ) ) {
			$output['seller_notification_email'] = $input['seller_notification_email'];
		} else {
			add_settings_error( 'AcceptStripePayments-settings', 'invalid-seller-notification-email', __( 'You must specify seller notification email address.', 'stripe-payments' ) );
		}

		if ( ! empty( $input['seller_email_subject'] ) ) {
			$output['seller_email_subject'] = $input['seller_email_subject'];
		} else {
			add_settings_error( 'AcceptStripePayments-settings', 'invalid-seller-email-subject', __( 'You must specify seller email subject.', 'stripe-payments' ) );
		}

		if ( ! empty( $input['seller_email_body'] ) ) {
			$output['seller_email_body'] = $input['seller_email_body'];
		} else {
			add_settings_error( 'AcceptStripePayments-settings', 'invalid-seller-email-body', __( 'You must fill in seller email body.', 'stripe-payments' ) );
		}

		// Price display

		$output['price_currency_pos'] = $input['price_currency_pos'];

		if ( ! empty( $input['price_decimal_sep'] ) ) {
			$output['price_decimal_sep'] = esc_attr( $input['price_decimal_sep'] );
		} else {
			add_settings_error( 'AcceptStripePayments-settings', 'empty-price-decimals-sep', __( 'Price decimal separator can\'t be empty.', 'stripe-payments' ) );
		}

		if ( ! empty( $input['price_thousand_sep'] ) ) {
			$output['price_thousand_sep'] = esc_attr( $input['price_thousand_sep'] );
		} else {
			$output['price_thousand_sep'] = '';
		}

		if ( isset( $input['price_decimals_num'] ) ) {
			$price_decimals_num           = intval( $input['price_decimals_num'] );
			$price_decimals_num           = $price_decimals_num < 0 ? 0 : $price_decimals_num;
			$output['price_decimals_num'] = $price_decimals_num;
		} else {
			add_settings_error( 'AcceptStripePayments-settings', 'invalid-price-decimals-num', __( 'Price number of decimals can\'t be empty.', 'stripe-payments' ) );
		}

		$url_hash = filter_input( INPUT_POST, 'wp-asp-urlHash', FILTER_SANITIZE_STRING );

		if ( ! empty( $url_hash ) ) {
			set_transient( 'wp-asp-urlHash', $url_hash, 300 );
		}

		//regen ckey
		ASP_Utils::get_ckey( true );

		//clear caching plugins cache if this is settings save action
		$submit = filter_input( INPUT_POST, 'submit', FILTER_SANITIZE_STRING );
		if ( ! empty( $submit ) ) {
			ASP_Utils::clear_external_caches();
		}

		return $output;
	}

	/**
	 * Render the settings page for this plugin.
	 *
	 * @since    1.0.0
	 */
	public function display_plugin_admin_page() {
		include_once 'views/admin.php';
	}

	public function display_addons_menu_page() {
		include_once 'views/addons-listing.php';
	}

	/**
	 * Helper function for addons to generate custom email tags
	 *
	 * @since    1.9.21t1
	 */
	static function get_email_tags_descr_out( $email_tags = array(), $apply_filters = true ) {
		return self::get_email_tags_descr( $email_tags, $apply_filters );
	}

	static function get_email_tags_descr( $email_tags = array(), $apply_filters = true ) {
		if ( empty( $email_tags ) ) {
			$email_tags = array(
				'{item_name}'         => __( 'Name of the purchased item', 'stripe-payments' ),
				'{item_short_desc}'   => __( 'Short description of item', 'stripe-payments' ),
				'{item_quantity}'     => __( 'Number of items purchased', 'stripe-payments' ),
				'{item_price}'        => __( 'Item price. Example: 1000,00', 'stripe-payments' ),
				'{item_price_curr}'   => __( 'Item price with currency symbol. Example: $1,000.00', 'stripe-payments' ),
				'{purchase_amt}'      => __( 'The amount paid for the current transaction. Example: 1,000.00', 'stripe-payments' ),
				'{purchase_amt_curr}' => __( 'The amount paid for the current transaction with currency symbol. Example: $1,000.00', 'stripe-payments' ),
				'{tax}'               => __( 'Tax in percent. Example: 10%', 'stripe-payments' ),
				'{tax_amt}'           => __( 'Formatted tax amount for single item. Example: $0.25', 'stripe-payments' ),
				'{shipping_amt}'      => __( 'Formatted shipping amount. Example: $2.50', 'stripe-payments' ),
				'{item_url}'          => __( 'Item download URL (if it\'s set)', 'stripe-payments' ),
				'{product_details}'   => __( 'The item details of the purchased product (this will include the download link for digital items)', 'stripe-payments' ),
				'{transaction_id}'    => __( 'The unique transaction ID of the purchase', 'stripe-payments' ),
				'{shipping_address}'  => __( 'Shipping address of the buyer', 'stripe-payments' ),
				'{billing_address}'   => __( 'Billing address of the buyer', 'stripe-payments' ),
				'{customer_name}'     => __( 'Customer name. Available only if collect billing address option enabled', 'stripe-payments' ),
				'{payer_email}'       => __( 'Email Address of the buyer', 'stripe-payments' ),
				'{currency}'          => __( 'Currency symbol. Example: $', 'stripe-payments' ),
				'{currency_code}'     => __( '3-letter currency code. Example: USD', 'stripe-payments' ),
				'{purchase_date}'     => __( 'The date of the purchase', 'stripe-payments' ),
				'{custom_field}'      => __( 'Custom field name and value (if enabled)', 'stripe-payments' ),
				'{coupon_code}'       => __( 'Coupon code (if available)', 'stripe-payments' ),
				'{payment_method}'    => __( 'Paymend method used to make the payment. Example: card, alipay' ),
				'{card_brand}'        => __( 'Brand of the card used to make the payment. Example: visa, mastercard, amex' ),
				'{card_last_4}'       => __( 'Last 4 digits of the card. Example: 4242' ),
			);
		}

		//apply filters so addons can add their hints if needed
		if ( $apply_filters ) {
			$email_tags = apply_filters( 'asp_get_email_tags_descr', $email_tags );
		}

		$email_tags_descr = '';

		foreach ( $email_tags as $tag => $descr ) {
			if ( $descr === '' ) {
				//this means we need to add addon title which is in $tag var
				$email_tags_descr .= sprintf( '<tr><td colspan="2" style="text-align: center" class="wp-asp-tag-name"><b>%s</b></td></tr>', $tag );
			} else {
				$email_tags_descr .= sprintf( '<tr><td class="wp-asp-tag-name"><b>%s</b></td><td class="wp-asp-tag-descr">%s</td></tr>', $tag, $descr );
			}
		}

		$email_tags_descr = sprintf( '</p><div><a class="wp-asp-toggle toggled-off" href="#0">%s</a><div class="wp-asp-tags-table-cont hidden"><table class="wp-asp-tags-hint" cellspacing="0"><tbody>%s</tbody></table></div></div><p>', __( 'Click here to toggle tags hint', 'stripe-payments' ), $email_tags_descr );
		return $email_tags_descr;
	}

}
