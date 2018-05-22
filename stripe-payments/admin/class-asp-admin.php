<?php

class AcceptStripePayments_Admin {

    /**
     * Instance of this class.
     *
     * @since    1.0.0
     *
     * @var      object
     */
    protected static $instance = null;

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

	/*
	 * Call $plugin_slug from public plugin class.
	 */
	$plugin			 = AcceptStripePayments::get_instance();
	$this->plugin_slug	 = $plugin->get_plugin_slug();

	// add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	// Add the options page and menu item.
	add_action( 'admin_menu', array( $this, 'add_plugin_admin_menu' ) );

	// Add an action link pointing to the options page.
	$plugin_basename = plugin_basename( plugin_dir_path( realpath( dirname( __FILE__ ) ) ) . $this->plugin_slug . '.php' );
	add_filter( 'plugin_action_links_' . $plugin_basename, array( $this, 'add_action_links' ) );

	//Add any required inline JS code in the admin dashboard side.
	add_action( 'admin_print_scripts', array( $this, 'asp_print_admin_scripts' ) );

	add_action( 'admin_init', array( $this, 'admin_init' ) );

	//TinyMCE button related
	add_action( 'init', array( $this, 'tinymce_shortcode_button' ) );
	add_action( 'current_screen', array( $this, 'check_current_screen' ) );
	add_action( 'wp_ajax_asp_tinymce_get_settings', array( $this, 'tinymce_ajax_handler' ) ); // Add ajax action handler for tinymce
    }

    function admin_init() {
	add_action( 'wp_ajax_asp_clear_log', array( 'ASP_Debug_Logger', 'clear_log' ) );
	//view log file
	if ( isset( $_GET[ 'asp_action' ] ) ) {
	    if ( $_GET[ 'asp_action' ] === 'view_log' ) {
		ASP_Debug_Logger::view_log();
	    }
	}
    }

    public function check_current_screen() {
	if ( function_exists( 'get_current_screen' ) ) {
	    $screen = get_current_screen();
	    if ( $screen->base == 'post' ) {
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
	    var asp_admin_ajax_url = '<?php echo admin_url( 'admin-ajax.php?action=ajax' ); ?>';
	</script>
	<?php
    }

    public function tinymce_ajax_handler() {
	ob_start();
	?>
	<style>
	    div#asp-shortcode-type-container {
		text-align: center;
		margin: 0 auto;
	    }
	    #asp-default-shortcode-container, #asp-product-shortcode-container {
		display: none;
	    }
	    table.asp-shortcode-options-table sup {
		color: red;
	    }
	    div#asp-type-select-buttons button.asp-sc-type-sel-btn {
		padding: 5px 20px;
		height: auto;
		font-size: 14px;
	    }
	    div#asp-type-select-buttons div {
		margin-top: 20px;
		margin-bottom: 10px;
	    }
	    #asp-error-no-products {
		display: none;
		color: red;
	    }
	</style>
	<div id="asp-shortcode-type-container">
	    <h2>Please select shortcode type you want to insert</h2>
	    <div id="asp-type-select-buttons">
		<div>
		    <button class="button button-primary asp-sc-type-sel-btn" data-asp-display="asp-product-shortcode-container">Product Shortcode</button>
		</div>
		<div>
		    <button class="button button-primary asp-sc-type-sel-btn" data-asp-display="asp-default-shortcode-container">Custom Shortcode</button>
		</div>
	    </div>
	</div>
	<div id="asp-product-shortcode-container">
	    <table class="form-table" style="text-align: left">
		<tr>
		    <th scope="row">Product</th>
		    <td><select id ="asp_product_select" name="asp_product"></select>
			<p class="description">Select product you want to insert.</p>
			<span id="asp-error-no-products">No products found. You need to <a href="<?php echo admin_url() . 'post-new.php?post_type=' . ASPMain::$products_slug; ?>" tagert="_blank">create a product</a> first.</span>
		    </td>
		</tr>
	    </table>
	    <p class="submit">
		<input type="button" id="asp-tinymce-product-submit" class="button-primary" value="Insert Shortcode" />
	    </p>
	</div>
	<div id="asp-default-shortcode-container">
	    <h2 class="nav-tab-wrapper"><a class="nav-tab" href="javascript:void()" data-switch-to-tab="1">General Options</a><a class="nav-tab" href="javascript:void()" data-switch-to-tab="2">Additional Options</a></h2>
	    <table id="highlight-table" class="form-table asp-shortcode-options-table" style="text-align: left">
		<tr data-tabid="1">
		    <th scope="row">Item Name <sup>*</sup></th>
		    <td><input type="text" name="asp_name" id="asp_name" class="asp-input-wide">
			<p class="description">Your item name. This value should be unique so this item can be identified uniquely on the page.</p>
		    </td>
		</tr>
		<tr data-tabid="1">
		    <th scope="row">Price</th>
		    <td><input type="text" name="asp_price" id="asp_price">
			<p class="description">Item price. Numbers only, no need to put currency symbol. Example: 99.95<br />
			    Leave it blank if you want your customers to enter the amount themselves (e.g. for donation button).
			</p>
		    </td>
		</tr>
		<tr data-tabid="1">
		    <th scope="row">Currency</th>
		    <td><select name="asp_currency" id="asp_currency"></select>
			<p class="description">Leave "(Default)" option selected if you want to use currency specified on settings page.</p>
		    </td>
		</tr>
		<tr data-tabid="1">
		    <th scope="row">Quantity</th>
		    <td><input type="text" name="asp_quantity" id="asp_quantity">
			<p class="description">Specify a custom quantity for the item.<br /></p>
		    </td>
		</tr>
		<tr data-tabid="1">
		    <th scope="row">Button Text</th>
		    <td><input type="text" name="asp_button_text" id="asp_button_text" class="asp-input-wide">
			<p class="description">Specify text to be displayed on the button. Leave it blank to use button text specified on settings page.</p>
		    </td>
		</tr>
		<tr data-tabid="2">
		    <th scope="row">URL</th>
		    <td><input type="text" name="asp_url" id="asp_url" class="asp-input-wide">
			<p class="description">URL of your product (if you're selling digital products).</p>
		    </td>
		</tr>
		<tr data-tabid="2">
		    <th scope="row">Thank You Page URL</th>
		    <td><input type="text" name="asp_thankyou_page_url" id="asp_thankyou_page_url" class="asp-input-wide">
			<p class="description">Page URL where users will be redirected after the payment is processed for this item. Useful if you want to make a custom "Thank you" page for this item. Leave it blank if you want to use the default URL specified in plugin settings.</p>
		    </td>
		</tr>
		<tr data-tabid="2">
		    <th scope="row">Description</th>
		    <td><input type="text" name="asp_description" id="asp_description" class="asp-input-wide">
			<p class="description">You can optionally add a custom description for the item/product/service that will get shown in the stripe checkout/payment window of the item.</p>
		    </td>
		</tr>
		<tr data-tabid="2">
		    <th scope="row">Billing Address</th>
		    <td><input type="checkbox" name="asp_billing_address" id="asp_billing_address">
			<p class="description">Enable this option to collect customer's billing address during the transaction.</p>
		    </td>
		</tr>
		<tr data-tabid="2">
		    <th scope="row">Shipping Address</th>
		    <td><input type="checkbox" name="asp_shipping_address" id="asp_shipping_address">
			<p class="description">Enable this option to collect customer's shipping address during the transaction.</p>
		    </td>
		</tr>
		<tr data-tabid="2">
		    <th scope="row">Item Logo</th>
		    <td><input type="text" name="asp_item_logo" id="asp_item_logo" class="asp-input-wide">
			<p class="description">You can optionally show an item logo in the Stripe payment window. Specify the logo image URL.</p>
		    </td>
		</tr>
		<tr data-tabid="2">
		    <th scope="row">Button CSS Class</th>
		    <td><input type="text" name="asp_css_class" id="asp_css_class" class="asp-input-wide">
			<p class="description">CSS class to be assigned to the button. This is used for styling purposes. You can get additional information <a href="https://stripe-plugins.com/customize-stripe-payment-button-appearance-using-css/" target="_blank">in this tutorial</a>.</p>
		    </td>
		</tr>
	    </table>
	    <p id="asp_form_err">&nbsp;</p>
	    <p class="submit">
		<input type="button" id="asp-tinymce-submit" class="button-primary" value="Insert Shortcode" name="submit" style=""/>
	    </p>
	</div>
	<?php
	$content	 = ob_get_clean();
	$query		 = new WP_Query( array(
	    'post_type'	 => ASPMain::$products_slug,
	    'post_status'	 => 'publish',
	    'posts_per_page' => -1,
	) );
	$products_sel	 = '';

	while ( $query->have_posts() ) {
	    $query->the_post();
	    $products_sel .= '<option value="' . get_the_ID() . '">' . get_the_title() . '</option>';
	}
	wp_reset_query();

	$opt			 = get_option( 'AcceptStripePayments-settings' );
	$ret[ 'button_text' ]	 = $opt[ 'button_text' ];
	$ret[ 'currency_opts' ]	 = AcceptStripePayments_Admin::get_currency_options();
	$ret[ 'content' ]	 = $content;
	$ret[ 'products_sel' ]	 = $products_sel;
	echo json_encode( $ret );
	wp_die();
    }

    public function tinymce_shortcode_button() {

	add_filter( 'mce_external_plugins', array( $this, 'add_shortcode_button' ) );
	add_filter( 'mce_buttons', array( $this, 'register_shortcode_button' ) );
    }

    public function add_shortcode_button( $plugin_array ) {

	$plugin_array[ 'asp_shortcode' ] = plugins_url( 'assets/js/tinymce/asp_editor_plugin.js', __FILE__ );
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
	if ( null == self::$instance ) {
	    self::$instance = new self;
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
	wp_enqueue_style( $this->plugin_slug . '-admin-styles', plugins_url( 'assets/css/admin.css', __FILE__ ), array(), AcceptStripePayments::VERSION );
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
	if ( $this->plugin_screen_hook_suffix == $screen->id ) {
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
//	add_submenu_page( 'edit.php?post_type=stripe_order', __( 'Products', 'stripe-payments' ), __( 'Products', 'stripe-payments' ), 'manage_options', 'edit.php?post_type=stripe_order', array( $this, 'display_plugin_admin_page' ) );
	$this->plugin_screen_hook_suffix = add_submenu_page(
	'edit.php?post_type=' . ASPMain::$products_slug, __( 'Settings', 'stripe-payments' ), __( 'Settings', 'stripe-payments' ), 'manage_options', 'stripe-payments-settings', array( $this, 'display_plugin_admin_page' )
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
	register_setting( 'AcceptStripePayments-settings-group', 'AcceptStripePayments-settings', array( &$this, 'settings_sanitize_field_callback' ) );

	// Add/define the various section/groups (the fields will go under these sections).
	//add_settings_section( 'AcceptStripePayments-documentation', __( 'Plugin Documentation', 'stripe-payments' ), array( &$this, 'general_documentation_callback' ), $this->plugin_slug . '-docs' );

	add_settings_section( 'AcceptStripePayments-global-section', __( 'Global Settings', 'stripe-payments' ), null, $this->plugin_slug );
	add_settings_section( 'AcceptStripePayments-credentials-section', __( 'Credentials', 'stripe-payments' ), null, $this->plugin_slug );
	add_settings_section( 'AcceptStripePayments-debug-section', __( 'Debug', 'stripe-payments' ), null, $this->plugin_slug );
	add_settings_section( 'AcceptStripePayments-settings-footer', '', array( &$this, 'general_settings_menu_footer_callback' ), $this->plugin_slug );

	add_settings_section( 'AcceptStripePayments-email-section', __( 'Email Settings', 'stripe-payments' ), null, $this->plugin_slug . '-email' );
	add_settings_section( 'AcceptStripePayments-error-email-section', __( 'Transaction Error Email Settings', 'stripe-payments' ), null, $this->plugin_slug . '-email' );

	add_settings_section( 'AcceptStripePayments-price-display', __( 'Price Display Settings', 'stripe-payments' ), null, $this->plugin_slug . '-advanced' );
	add_settings_section( 'AcceptStripePayments-custom-field', __( 'Custom Field Settings', 'stripe-payments' ), null, $this->plugin_slug . '-advanced' );
	add_settings_section( 'AcceptStripePayments-tos', __( 'Terms and Conditions', 'stripe-payments' ), array( $this, 'tos_description' ), $this->plugin_slug . '-advanced' );
	add_settings_section( 'AcceptStripePayments-additional-settings', __( 'Additional Settings', 'stripe-payments' ), null, $this->plugin_slug . '-advanced' );

	// Global section
	add_settings_field( 'checkout_url', __( 'Checkout Result Page URL', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug, 'AcceptStripePayments-global-section', array( 'field'	 => 'checkout_url',
	    'desc'	 => __( 'This is the thank you page. This page is automatically created for you when you install the plugin. Do not delete this page as the plugin will send the customer to this page after the payment.', 'stripe-payments' ) . '<br /><b><i>' . __( 'Important Notice:', 'stripe-payments' ) . '</i></b> ' . __( 'if you are using caching plugins on your site (similar to W3 Total Cache, WP Rocket etc), you must exclude checkout results page from caching. Failing to do so will result in unpredictable checkout results output.', 'stripe-payments' ),
	    'size'	 => 100 ) );
	add_settings_field( 'products_page_id', 'Products Page URL', array( &$this, 'settings_field_callback' ), $this->plugin_slug, 'AcceptStripePayments-global-section', array( 'field'	 => 'products_page_id',
	    'desc'	 => __( 'All your products will be listed here in a grid display. When you create new products, they will show up in this page. This page is automatically created for you when you install the plugin. You can add this page to your navigation menu if you want the site visitors to find it easily.', 'stripe-payments' ),
	    'size'	 => 100 ) );

	add_settings_field( 'currency_code', __( 'Currency', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug, 'AcceptStripePayments-global-section', array( 'field' => 'currency_code', 'desc' => '', 'size' => 10 ) );
	add_settings_field( 'currency_symbol', __( 'Currency Symbol', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug, 'AcceptStripePayments-global-section', array( 'field' => 'currency_symbol', 'desc' => '', 'size' => 10 ) );
	add_settings_field( 'button_text', __( 'Button Text', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug, 'AcceptStripePayments-global-section', array( 'field'	 => 'button_text',
	    'desc'	 => __( 'Example: Buy Now, Pay Now etc.', 'stripe-payments' ) ) );
	add_settings_field( 'dont_save_card', __( 'Do Not Save Card Data on Stripe', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug, 'AcceptStripePayments-global-section', array( 'field'	 => 'dont_save_card',
	    'desc'	 => __( 'When this checkbox is checked, the transaction won\'t create the customer (no card will be saved for that).', 'stripe-payments' ) ) );
	add_settings_field( 'disable_remember_me', __( 'Turn Off "Remember me" Option', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug, 'AcceptStripePayments-global-section', array( 'field'	 => 'disable_remember_me',
	    'desc'	 => __( 'When enabled, "Remember me" checkbox will be removed from Stripe\'s checkout popup.', 'stripe-payments' ) ) );
//	add_settings_field( 'use_new_button_method', __( 'Use New Method To Display Buttons', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug, 'AcceptStripePayments-global-section', array( 'field'	 => 'use_new_button_method',
//	    'desc'	 => __( 'Use new method to display Stripe buttons. It makes connection to Stripe website only when button is clicked, which makes the page with buttons load faster. A little drawback is that Stripe pop-up is displayed with a small delay after button click. If you have more than one button on a page, enabling this option is highly recommended.', 'stripe-payments' ) . '<br /><b>' . __( 'Note:', 'stripe-payments' ) . '</b> ' . __( 'old method doesn\'t support custom price and quantity. If your shortcode or product is using one of those features, the new method will be used automatically for that entity.', 'stripe-payments' ) ) );
	add_settings_field( 'checkout_lang', __( 'Stripe Checkout Language', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug, 'AcceptStripePayments-global-section', array( 'field'	 => 'checkout_lang',
	    'desc'	 => __( 'Specify language to be used in Stripe checkout pop-up or select "Autodetect" to let Stripe handle it.', 'stripe-payments' ) ) );

	// Credentials section
	add_settings_field( 'is_live', __( 'Live Mode', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug, 'AcceptStripePayments-credentials-section', array( 'field' => 'is_live', 'desc' => __( 'Check this to run the transaction in live mode. When unchecked it will run in test mode.', 'stripe-payments' ) ) );
	add_settings_field( 'api_publishable_key', __( 'Live Stripe Publishable Key', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug, 'AcceptStripePayments-credentials-section', array( 'field' => 'api_publishable_key', 'desc' => '' ) );
	add_settings_field( 'api_secret_key', __( 'Live Stripe Secret Key', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug, 'AcceptStripePayments-credentials-section', array( 'field' => 'api_secret_key', 'desc' => '' ) );
	add_settings_field( 'api_publishable_key_test', __( 'Test Stripe Publishable Key', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug, 'AcceptStripePayments-credentials-section', array( 'field' => 'api_publishable_key_test', 'desc' => '' ) );
	add_settings_field( 'api_secret_key_test', __( 'Test Stripe Secret Key', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug, 'AcceptStripePayments-credentials-section', array( 'field' => 'api_secret_key_test', 'desc' => '' ) );

	//Debug section
	add_settings_field( 'debug_log_enable', __( 'Enable Debug Logging', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug, 'AcceptStripePayments-debug-section', array( 'field'	 => 'debug_log_enable', 'desc'	 => __( 'Check this option to enable debug logging. This is useful for troubleshooting post payment failures.', 'stripe-payments' ) .
	    '<br /><a href="' . admin_url() . '?asp_action=view_log" target="_blank">' . __( 'View Log', 'stripe-payments' ) . '</a> | <a style="color: red;" id="asp_clear_log_btn" href="#0">' . __( 'Clear Log', 'stripe-payments' ) . '</a>' ) );

	// Email section
	add_settings_field( 'stripe_receipt_email', __( 'Send Receipt Email From Stripe', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug . '-email', 'AcceptStripePayments-email-section', array( 'field'	 => 'stripe_receipt_email',
	    'desc'	 => __( 'If checked, Stripe will send email receipts to your customers whenever they make successful payment.<br /><b>Note:</b> Receipts are not sent in test mode.', 'stripe-payments' ) )
	);

	add_settings_field( 'send_emails_to_buyer', __( 'Send Emails to Buyer After Purchase', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug . '-email', 'AcceptStripePayments-email-section', array( 'field'	 => 'send_emails_to_buyer',
	    'desc'	 => __( 'If checked the plugin will send an email to the buyer with the sale details. If digital goods are purchased then the email will contain the download links for the purchased products.', 'stripe-payments' ) )
	);
	add_settings_field( 'from_email_address', __( 'From Email Address', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug . '-email', 'AcceptStripePayments-email-section', array( 'field'	 => 'from_email_address',
	    'desc'	 => __( 'Example: Your Name &lt;sales@your-domain.com&gt; This is the email address that will be used to send the email to the buyer. This name and email address will appear in the from field of the email.', 'stripe-payments' ) )
	);
	add_settings_field( 'buyer_email_subject', __( 'Buyer Email Subject', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug . '-email', 'AcceptStripePayments-email-section', array( 'field'	 => 'buyer_email_subject',
	    'desc'	 => __( 'This is the subject of the email that will be sent to the buyer.', 'stripe-payments' ) )
	);

	$email_tags = array(
	    "{shipping_address}"	 => 'Shipping address of the buyer',
	    "{billing_address}"	 => 'Billing address of the buyer',
	    "{product_details}"	 => 'The item details of the purchased product (this will include the download link for digital items)',
	    "{transaction_id}"	 => 'The unique transaction ID of the purchase',
	    "{payer_email}"		 => 'Email Address of the buyer',
	    "{purchase_amt}"	 => 'The amount paid for the current transaction. Example: 1,000.00',
	    "{tax}"			 => 'Tax in percents. Example: 10%',
	    "{tax_amt}"		 => 'Formatted tax amount for single item. Example: $0.25',
	    "{shipping_amt}"	 => 'Formatted shipping amount. Example: $2.50',
	    "{purchase_amt_curr}"	 => 'The amount paid for the current transaction with currency symbol. Example: $1,000.00',
	    "{item_price}"		 => 'Item price. Example: 1000,00',
	    "{item_price_curr}"	 => 'Item price with currency symbol. Example: $1,000.00',
	    "{currency}"		 => 'Currency symbol. Example: $',
	    "{currency_code}"	 => '3-letter currency code. Example: USD',
	    "{purchase_date}"	 => 'The date of the purchase',
	    "{custom_field}"	 => 'Custom field name and value (if enabled)',
	);

	$email_tags_descr = '';

	foreach ( $email_tags as $tag => $descr ) {
	    $email_tags_descr .= '<br>' . $tag . ' - ' . __( $descr, 'stripe-payments' );
	}

	$email_tags_descr = '<div><a class="wp-asp-toggle toggled-off" href="#0">' . __( 'Click here to toggle tags hint', 'stripe-payments' ) . '</a><div class="hidden">' . $email_tags_descr . '</div></div>';

	add_settings_field( 'buyer_email_body', __( 'Buyer Email Body', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug . '-email', 'AcceptStripePayments-email-section', array( 'field'	 => 'buyer_email_body',
	    'desc'	 => __( 'This is the body of the email that will be sent to the buyer.', 'stripe-payments' ) . ' ' . __( 'Do not change the text within the braces {}. You can use the following email tags in this email body field:', 'stripe-payments' ) . $email_tags_descr )
	);
	add_settings_field( 'send_emails_to_seller', __( 'Send Emails to Seller After Purchase', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug . '-email', 'AcceptStripePayments-email-section', array( 'field'	 => 'send_emails_to_seller',
	    'desc'	 => __( 'If checked the plugin will send an email to the seller with the sale details.', 'stripe-payments' ) )
	);
	add_settings_field( 'seller_notification_email', __( 'Notification Email Address', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug . '-email', 'AcceptStripePayments-email-section', array( 'field'	 => 'seller_notification_email',
	    'desc'	 => __( 'This is the email address where the seller will be notified of product sales. You can put multiple email addresses separated by comma (,) in the above field to send the notification to multiple email addresses.', 'stripe-payments' ) )
	);
	add_settings_field( 'seller_email_subject', __( 'Seller Email Subject', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug . '-email', 'AcceptStripePayments-email-section', array( 'field'	 => 'seller_email_subject',
	    'desc'	 => __( 'This is the subject of the email that will be sent to the seller for record.', 'stripe-payments' ) )
	);
	add_settings_field( 'seller_email_body', __( 'Seller Email Body', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug . '-email', 'AcceptStripePayments-email-section', array( 'field'	 => 'seller_email_body',
	    'desc'	 => __( 'This is the body of the email that will be sent to the seller.', 'stripe-payments' ) . ' ' . __( 'Do not change the text within the braces {}. You can use the following email tags in this email body field:', 'stripe-payments' ) . $email_tags_descr )
	);

	add_settings_field( 'send_email_on_error', __( 'Send Email On Payment Failure', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug . '-email', 'AcceptStripePayments-error-email-section', array( 'field'	 => 'send_email_on_error',
	    'desc'	 => __( 'If checked, plugin will send a notification email when error occured during payment processing. The email will be sent to the email address specified below.', 'stripe-payments' ) )
	);
	add_settings_field( 'send_email_on_error_to', __( 'Send Error Email To', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug . '-email', 'AcceptStripePayments-error-email-section', array( 'field'	 => 'send_email_on_error_to',
	    'desc'	 => __( 'Enter recipient address of error email.', 'stripe-payments' ) )
	);

	// Price Display section
	add_settings_field( 'price_currency_pos', __( 'Currency Position', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug . '-advanced', 'AcceptStripePayments-price-display', array( 'field'	 => 'price_currency_pos',
	    'desc'	 => __( 'This controls the position of the currency symbol.', 'stripe-payments' ) )
	);
	add_settings_field( 'price_decimal_sep', __( 'Decimal Separator', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug . '-advanced', 'AcceptStripePayments-price-display', array( 'field'	 => 'price_decimal_sep',
	    'desc'	 => __( 'This sets the decimal separator of the displayed price.', 'stripe-payments' ) )
	);
	add_settings_field( 'price_thousand_sep', __( 'Thousand Separator', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug . '-advanced', 'AcceptStripePayments-price-display', array( 'field'	 => 'price_thousand_sep',
	    'desc'	 => __( 'This sets the thousand separator of the displayed price.', 'stripe-payments' ) )
	);
	add_settings_field( 'price_decimals_num', 'Number of Decimals', array( &$this, 'settings_field_callback' ), $this->plugin_slug . '-advanced', 'AcceptStripePayments-price-display', array( 'field'	 => 'price_decimals_num',
	    'desc'	 => __( 'This sets the number of decimal points shown in the displayed price.', 'stripe-payments' ) )
	);

	add_settings_field( 'price_apply_for_input', __( 'Apply Separators Settings To Customer Input', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug . '-advanced', 'AcceptStripePayments-price-display', array( 'field'	 => 'price_apply_for_input',
	    'desc'	 => __( 'If enabled, separator settings will be applied to customer input as well. For example, if you have donation button where customers can enter amount and you set "," as decimal separator, customers will need to enter values correspondigly - 12,23 instead of 12.23.', 'stripe-payments' ) )
	);

	// Custom Field section
	add_settings_field( 'custom_field_enabled', __( 'Enable For All Buttons and Products', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug . '-advanced', 'AcceptStripePayments-custom-field', array( 'field'	 => 'custom_field_enabled',
	    'desc'	 => __( 'If enabled, makes the following field enabled by default for all buttons and products.', 'stripe-payments' ) . '<br />' .
	    __( 'You can control per-product or per-button behaviour by editing the product and selecting enabled or disabled option under the Custom Field section.', 'stripe-payments' ) . '<br />' .
	    __( 'View the custom field <a href="https://stripe-plugins.com/custom-field-settings-feature-stripe-payments-plugin/" target="_blank">usage documentation</a>.', 'stripe-payments' ) )
	);
	add_settings_field( 'custom_field_name', __( 'Field Name', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug . '-advanced', 'AcceptStripePayments-custom-field', array( 'field'	 => 'custom_field_name',
	    'desc'	 => __( 'Enter name for the field. It will be displayed in order info and emails.', 'stripe-payments' ) )
	);
	add_settings_field( 'custom_field_descr', __( 'Field Description', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug . '-advanced', 'AcceptStripePayments-custom-field', array( 'field'	 => 'custom_field_descr',
	    'desc'	 => __( 'Enter field description. It will be displayed for users to let them know what is required from them. Leave it blank if you don\'t want to display description.', 'stripe-payments' ) )
	);
	add_settings_field( 'custom_field_descr_location', __( 'Text Field Description Location', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug . '-advanced', 'AcceptStripePayments-custom-field', array( 'field'	 => 'custom_field_descr_location',
	    'desc'	 => __( 'Select field description location. Placeholder: description is displayed inside text input (default). Below Input: description is displayed below text input.', 'stripe-payments' ) )
	);
	add_settings_field( 'custom_field_type', __( 'Field Type', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug . '-advanced', 'AcceptStripePayments-custom-field', array( 'field'	 => 'custom_field_type',
	    'desc'	 => __( 'Select custom field type.', 'stripe-payments' ) )
	);
	add_settings_field( 'custom_field_mandatory', __( 'Mandatory', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug . '-advanced', 'AcceptStripePayments-custom-field', array( 'field'	 => 'custom_field_mandatory',
	    'desc'	 => __( "If enabled, makes the field mandatory - user can't proceed with the payment before it's filled.", 'stripe-payments' ) )
	);

	//Terms and Conditions
	add_settings_field( 'tos_enabled', __( 'Enable Terms and Conditions', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug . '-advanced', 'AcceptStripePayments-tos', array( 'field'	 => 'tos_enabled',
	    'desc'	 => __( 'Enable Terms and Conditions checkbox.', 'stripe-payments' ) )
	);
	add_settings_field( 'tos_text', __( 'Checkbox Text', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug . '-advanced', 'AcceptStripePayments-tos', array( 'field'	 => 'tos_text',
	    'desc'	 => __( 'Text to be displayed on checkbox. It accepts HTML code so you can put a link to your terms and conditions page.', 'stripe-payments' ) )
	);

	// Additional Settings
	add_settings_field( 'disable_buttons_before_js_loads', __( 'Disable Buttons Before Javascript Loads', 'stripe-payments' ), array( &$this, 'settings_field_callback' ), $this->plugin_slug . '-advanced', 'AcceptStripePayments-additional-settings', array( 'field'	 => 'disable_buttons_before_js_loads',
	    'desc'	 => __( "If enabled, payment buttons are not clickable until Javascript libraries are loaded on page view. This prevents \"Invalid Stripe Token\" errors on some configurations.", 'stripe-payments' ) )
	);
    }

    function tos_description() {
	echo '<p>' . __( 'This section allows you to configure Terms and Conditions or Privacy Policy that customer must accept before making payment. This, for example, can be used to comply with EU GDPR.', 'stripe-payments' ) . '</p>';
    }

    public function general_documentation_callback( $args ) {
	?>
	<div style="background: none repeat scroll 0 0 #FFF6D5;border: 1px solid #D1B655;color: #3F2502;margin: 10px 0;padding: 5px 5px 5px 10px;text-shadow: 1px 1px #FFFFFF;">
	    <p>Please read the
		<a target="_blank" href="https://stripe-plugins.com/stripe-payments-plugin-tutorials/">WordPress Stripe</a> plugin setup instructions to configure and use it.
	    </p>
	</div>
	<?php
    }

    public function general_settings_menu_footer_callback( $args ) {
	?>
	<div style="background: none repeat scroll 0 0 #FFF6D5;border: 1px solid #D1B655;color: #3F2502;margin: 10px 0;padding: 5px 5px 5px 10px;text-shadow: 1px 1px #FFFFFF;">
	    <p>
		If you need a feature rich plugin (with good support) for selling your products and services then check out our
		<a target="_blank" href="https://www.tipsandtricks-hq.com/wordpress-estore-plugin-complete-solution-to-sell-digital-products-from-your-wordpress-blog-securely-1059">WP eStore Plugin</a>.
	    </p>
	</div>
	<?php
    }

    static function get_currency_options( $selected_value = '', $show_default = true ) {

	$currencies = AcceptStripePayments::get_currencies();

	if ( $show_default === false ) {
	    unset( $currencies[ "" ] );
	}
	$opt_tpl = '<option value="%curr_code%"%selected%>%curr_name%</option>';
	$opts	 = '';
	foreach ( $currencies as $key => $value ) {
	    $selected	 = $selected_value == $key ? ' selected' : '';
	    $opts		 .= str_replace( array( '%curr_code%', '%curr_name%', '%selected%' ), array( $key, $value[ 0 ], $selected ), $opt_tpl );
	}

	return $opts;
    }

    public function get_checkout_lang_options( $selected_value = '' ) {
	$data_arr	 = array(
	    ""	 => "Autodetect",
	    "da"	 => "Danish",
	    "nl"	 => "Dutch",
	    "en"	 => "English",
	    "fi"	 => "Finnish",
	    "fr"	 => "French",
	    "de"	 => "German",
	    "it"	 => "Italian",
	    "ja"	 => "Japanese",
	    "no"	 => "Norwegian",
	    "zh"	 => "Simplified Chinese",
	    "es"	 => "Spanish",
	    "sv"	 => "Swedish",
	);
	$opt_tpl	 = '<option value="%val%"%selected%>%name%</option>';
	$opts		 = $selected_value === false ? '<option value="" selected>(Default)</option>' : '';
	foreach ( $data_arr as $key => $value ) {
	    $selected	 = $selected_value == $key ? ' selected' : '';
	    $opts		 .= str_replace( array( '%val%', '%name%', '%selected%' ), array( $key, $value, $selected ), $opt_tpl );
	}

	return $opts;
    }

    /**
     * Settings HTML
     */
    public function settings_field_callback( $args ) {
	$settings = (array) get_option( 'AcceptStripePayments-settings' );

	extract( $args );

	$field_value = esc_attr( isset( $settings[ $field ] ) ? $settings[ $field ] : '' );

	if ( empty( $size ) ) {
	    $size = 40;
	}

	$addon_field = apply_filters( 'asp-admin-settings-addon-field-display', $field, $field_value );

	if ( is_array( $addon_field ) ) {
	    $field		 = $addon_field[ 'field' ];
	    $field_name	 = $addon_field[ 'field_name' ];
	}

	switch ( $field ) {
	    case 'checkbox':
		echo "<input type='checkbox' name='AcceptStripePayments-settings[{$field_name}]' value='1' " . ($field_value ? 'checked=checked' : '') . " /><p class=\"description\">{$desc}</p>";
		break;
	    case 'custom':
		echo $addon_field[ 'field_data' ];
		echo "<p class=\"description\">{$desc}</p>";
		break;
	    case 'custom_field_type':
		echo "<select name='AcceptStripePayments-settings[{$field}]'>'";
		echo "<option value='text'" . ($field_value === 'text' ? ' selected' : '') . ">Text</option>";
		echo "<option value='checkbox'" . ($field_value === 'checkbox' ? ' selected' : '') . ">Checkbox</option>";
		echo "</select>";
		echo "<p class=\"description\">{$desc}</p>";
		break;
	    case 'custom_field_descr_location':
		echo "<select name='AcceptStripePayments-settings[{$field}]'>'";
		echo "<option value='placeholder'" . ($field_value === 'placeholder' ? ' selected' : '') . ">" . __( 'Placeholder', 'stripe-payments' ) . "</option>";
		echo "<option value='below'" . ($field_value === 'below' ? ' selected' : '') . ">" . __( 'Below Input', 'stripe-payments' ) . "</option>";
		echo "</select>";
		echo "<p class=\"description\">{$desc}</p>";
		break;
	    case 'price_apply_for_input':
	    case 'tos_enabled':
	    case 'debug_log_enable':
	    case 'send_emails_to_seller':
	    case 'send_emails_to_buyer':
	    case 'stripe_receipt_email':
	    case 'send_email_on_error':
	    case 'use_new_button_method':
	    case 'is_live':
	    case 'disable_remember_me':
	    case 'disable_buttons_before_js_loads':
	    case 'dont_save_card':
	    case 'custom_field_mandatory':
	    case 'custom_field_enabled':
		echo "<input type='checkbox' name='AcceptStripePayments-settings[{$field}]' value='1' " . ($field_value ? 'checked=checked' : '') . " /><p class=\"description\">{$desc}</p>";
		break;
	    case 'buyer_email_body':
	    case 'seller_email_body':
		echo "<textarea cols='70' rows='7' name='AcceptStripePayments-settings[{$field}]'>{$field_value}</textarea><p class=\"description\">{$desc}</p>";
		break;
	    case 'products_page_id':
		//We save the products page ID internally but we show the URL of that page to the user (its user-friendly).
		$products_page_id	 = $field_value;
		$products_page_url	 = get_permalink( $products_page_id );
		//show the URL in a text field for display purpose. This field's value can't be updated as we store the page ID internally.
		echo "<input type='text' name='asp_products_page_url_value' value='{$products_page_url}' size='{$size}' /> <p class=\"description\">{$desc}</p>";
		break;
	    case 'currency_code':
		echo '<select name="AcceptStripePayments-settings[' . $field . ']" id="wp_asp_curr_code">';
		echo AcceptStripePayments_Admin::get_currency_options( $field_value, false );
		echo '</select>';
		//echo "<p class=\"description\">{$desc}</p>";
		break;
	    case 'currency_symbol':
		echo '<input type="text" name="AcceptStripePayments-settings[' . $field . ']" value="" id="wp_asp_curr_symb"?>';
		break;
	    case 'checkout_lang':
		// list of supported languages can be found here: https://stripe.com/docs/checkout#supported-languages
		echo '<select name="AcceptStripePayments-settings[' . $field . ']">';
		echo $this->get_checkout_lang_options( $field_value );
		echo '</select>';
		echo "<p class=\"description\">{$desc}</p>";
		break;
	    case 'price_currency_pos':
		?>
		<select name="AcceptStripePayments-settings[<?php echo $field; ?>]">
		    <option value="left"<?php echo ($field_value === "left") ? ' selected' : ''; ?>>Left</option>
		    <option value="right"<?php echo ($field_value === "right") ? ' selected' : ''; ?>>Right</option>
		</select>
		<p class="description"><?php echo $desc; ?></p>
		<?php
		break;
	    case 'tos_text':
		echo '<textarea name="AcceptStripePayments-settings[tos_text]" rows="4" cols="70">' . $field_value . '</textarea>';
		echo '<p class="description">' . $desc . '</p>';
		break;
	    default:
		echo "<input type='text' name='AcceptStripePayments-settings[{$field}]' value='{$field_value}' size='{$size}' /> <p class=\"description\">{$desc}</p>";
		break;
	}
    }

    /**
     * Validates the admin data
     *
     * @since    1.0.0
     */
    public function settings_sanitize_field_callback( $input ) {
	$output = get_option( 'AcceptStripePayments-settings' );

	$output = apply_filters( 'apm-admin-settings-sanitize-field', $output, $input );

	$output [ 'price_apply_for_input' ] = empty( $input[ 'price_apply_for_input' ] ) ? 0 : 1;

	$output [ 'tos_enabled' ] = empty( $input[ 'tos_enabled' ] ) ? 0 : 1;

	$output[ 'tos_text' ] = ! empty( $input[ 'tos_text' ] ) ? $input[ 'tos_text' ] : '';

	$output[ 'custom_field_enabled' ] = empty( $input[ 'custom_field_enabled' ] ) ? 0 : 1;

	$output[ 'custom_field_type' ] = empty( $input[ 'custom_field_type' ] ) ? 'text' : sanitize_text_field( $input[ 'custom_field_type' ] );

	$output[ 'custom_field_name' ] = empty( $input[ 'custom_field_name' ] ) ? '' : sanitize_text_field( $input[ 'custom_field_name' ] );

	$output[ 'custom_field_descr' ] = empty( $input[ 'custom_field_descr' ] ) ? '' : $input[ 'custom_field_descr' ];

	$output[ 'custom_field_descr_location' ] = empty( $input[ 'custom_field_descr_location' ] ) ? 'placeholder' : $input[ 'custom_field_descr_location' ];

	$output[ 'custom_field_mandatory' ] = empty( $input[ 'custom_field_mandatory' ] ) ? 0 : 1;

	$output[ 'is_live' ] = empty( $input[ 'is_live' ] ) ? 0 : 1;

	$output[ 'debug_log_enable' ] = empty( $input[ 'debug_log_enable' ] ) ? 0 : 1;

	$output[ 'dont_save_card' ] = empty( $input[ 'dont_save_card' ] ) ? 0 : 1;

	$output[ 'disable_remember_me' ] = empty( $input[ 'disable_remember_me' ] ) ? 0 : 1;

	$output[ 'use_new_button_method' ] = empty( $input[ 'use_new_button_method' ] ) ? 0 : 1;

	$output[ 'send_emails_to_buyer' ] = empty( $input[ 'send_emails_to_buyer' ] ) ? 0 : 1;

	$output[ 'stripe_receipt_email' ] = empty( $input[ 'stripe_receipt_email' ] ) ? 0 : 1;

	$output[ 'send_email_on_error' ] = empty( $input[ 'send_email_on_error' ] ) ? 0 : 1;

	$output[ 'send_emails_to_seller' ] = empty( $input[ 'send_emails_to_seller' ] ) ? 0 : 1;

	$output[ 'send_email_on_error_to' ] = sanitize_text_field( $input[ 'send_email_on_error_to' ] );

	$output[ 'disable_buttons_before_js_loads' ] = empty( $input[ 'disable_buttons_before_js_loads' ] ) ? 0 : 1;

	if ( $output[ 'is_live' ] != 0 ) {
	    if ( empty( $input[ 'api_secret_key' ] ) || empty( $input[ 'api_publishable_key' ] ) ) {
		add_settings_error( 'AcceptStripePayments-settings', 'invalid-credentials', __( 'You must fill Live API credentials for plugin to work correctly.', 'stripe-payments' ) );
	    }
	} else {
	    if ( empty( $input[ 'api_secret_key_test' ] ) || empty( $input[ 'api_publishable_key_test' ] ) ) {
		add_settings_error( 'AcceptStripePayments-settings', 'invalid-credentials', __( 'You must fill Test API credentials for plugin to work correctly.', 'stripe-payments' ) );
	    }
	}

	if ( ! empty( $input[ 'checkout_url' ] ) )
	    $output[ 'checkout_url' ] = $input[ 'checkout_url' ];
	else
	    add_settings_error( 'AcceptStripePayments-settings', 'invalid-checkout_url', __( 'Please specify a checkout page.', 'stripe-payments' ) );

	if ( ! empty( $input[ 'button_text' ] ) )
	    $output[ 'button_text' ] = $input[ 'button_text' ];
	else
	    add_settings_error( 'AcceptStripePayments-settings', 'invalid-button-text', __( 'Button text should not be empty.', 'stripe-payments' ) );

	if ( ! empty( $input[ 'currency_code' ] ) ) {
	    $output[ 'currency_code' ]	 = $input[ 'currency_code' ];
	    $currencies			 = AcceptStripePayments::get_currencies();
	    $opts				 = get_option( 'AcceptStripePayments-settings' );
	    if ( isset( $opts[ 'custom_currency_symbols' ] ) && is_array( $opts[ 'custom_currency_symbols' ] ) ) {
		$custom_curr_symb = $opts[ 'custom_currency_symbols' ];
	    } else {
		$custom_curr_symb = array();
	    }
	    if ( $currencies[ $output[ 'currency_code' ] ][ 1 ] !== $input[ 'currency_symbol' ] ) {
		$custom_curr_symb[ $output[ 'currency_code' ] ] = array( $currencies[ $output[ 'currency_code' ] ][ 0 ], $input[ 'currency_symbol' ] );
	    } else {
		if ( isset( $custom_curr_symb[ 'currency_code' ] ) ) {
		    unset( $custom_curr_symb[ 'currency_code' ] );
		}
	    }
	    $output[ 'custom_currency_symbols' ] = $custom_curr_symb;
	} else
	    add_settings_error( 'AcceptStripePayments-settings', 'invalid-currency-code', __( 'You must specify payment currency.', 'stripe-payments' ) );

	$output[ 'checkout_lang' ] = $input[ 'checkout_lang' ];

	$output[ 'api_publishable_key' ] = $input[ 'api_publishable_key' ];

	$output[ 'api_secret_key' ] = $input[ 'api_secret_key' ];

	$output[ 'api_publishable_key_test' ] = $input[ 'api_publishable_key_test' ];

	$output[ 'api_secret_key_test' ] = $input[ 'api_secret_key_test' ];

	if ( ! empty( $input[ 'from_email_address' ] ) )
	    $output[ 'from_email_address' ] = $input[ 'from_email_address' ];
	else
	    add_settings_error( 'AcceptStripePayments-settings', 'invalid-from-email-address', __( 'You must specify from email address.', 'stripe-payments' ) );

	if ( ! empty( $input[ 'buyer_email_subject' ] ) )
	    $output[ 'buyer_email_subject' ] = $input[ 'buyer_email_subject' ];
	else
	    add_settings_error( 'AcceptStripePayments-settings', 'invalid-buyer-email-subject', __( 'You must specify buyer email subject.', 'stripe-payments' ) );

	if ( ! empty( $input[ 'buyer_email_body' ] ) )
	    $output[ 'buyer_email_body' ] = $input[ 'buyer_email_body' ];
	else
	    add_settings_error( 'AcceptStripePayments-settings', 'invalid-buyer-email-body', __( 'You must fill in buyer email body.', 'stripe-payments' ) );

	if ( ! empty( $input[ 'seller_notification_email' ] ) )
	    $output[ 'seller_notification_email' ] = $input[ 'seller_notification_email' ];
	else
	    add_settings_error( 'AcceptStripePayments-settings', 'invalid-seller-notification-email', __( 'You must specify seller notification email address.', 'stripe-payments' ) );

	if ( ! empty( $input[ 'seller_email_subject' ] ) )
	    $output[ 'seller_email_subject' ] = $input[ 'seller_email_subject' ];
	else
	    add_settings_error( 'AcceptStripePayments-settings', 'invalid-seller-email-subject', __( 'You must specify seller email subject.', 'stripe-payments' ) );

	if ( ! empty( $input[ 'seller_email_body' ] ) )
	    $output[ 'seller_email_body' ] = $input[ 'seller_email_body' ];
	else
	    add_settings_error( 'AcceptStripePayments-settings', 'invalid-seller-email-body', __( 'You must fill in seller email body.', 'stripe-payments' ) );

// Price display

	$output[ 'price_currency_pos' ] = $input[ 'price_currency_pos' ];

	if ( ! empty( $input[ 'price_decimal_sep' ] ) )
	    $output[ 'price_decimal_sep' ] = esc_attr( $input[ 'price_decimal_sep' ] );
	else
	    add_settings_error( 'AcceptStripePayments-settings', 'empty-price-decimals-sep', __( 'Price decimal separator can\'t be empty.', 'stripe-payments' ) );

	if ( ! empty( $input[ 'price_thousand_sep' ] ) )
	    $output[ 'price_thousand_sep' ] = esc_attr( $input[ 'price_thousand_sep' ] );
	else
	    add_settings_error( 'AcceptStripePayments-settings', 'empty-price-thousand-sep', __( 'Price thousand separator can\'t be empty.', 'stripe-payments' ) );

	if ( ! empty( $input[ 'price_decimals_num' ] ) )
	    $output[ 'price_decimals_num' ] = esc_attr( $input[ 'price_decimals_num' ] );
	else
	    add_settings_error( 'AcceptStripePayments-settings', 'invalid-price-decimals-num', __( 'Price number of decimals can\'t be empty.', 'stripe-payments' ) );



	if ( isset( $_POST[ 'wp-asp-urlHash' ] ) ) {
	    set_transient( 'wp-asp-urlHash', $_POST[ 'wp-asp-urlHash' ], 300 );
	}

	return $output;
    }

    /**
     * Render the settings page for this plugin.
     *
     * @since    1.0.0
     */
    public function display_plugin_admin_page() {
	include_once( 'views/admin.php' );
    }

    public function display_addons_menu_page() {
	include_once( 'views/addons-listing.php' );
    }

    /**
     * Add settings action link to the plugins page.
     *
     * @since    1.0.0
     */
    public function add_action_links( $links ) {

	return array_merge(
	array(
	    'settings' => '<a href="' . admin_url( 'options-general.php?page=' . $this->plugin_slug ) . '">' . __( 'Settings', 'stripe-payments' ) . '</a>'
	), $links
	);
    }

}
