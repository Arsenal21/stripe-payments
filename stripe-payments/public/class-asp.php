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

    var $zeroCents = array('JPY', 'MGA', 'VND', 'KRW');

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
    private $settings = null;

    /**
     * Initialize the plugin by setting localization and loading public scripts
     * and styles.
     *
     * @since     1.0.0
     */
    private function __construct() {
        $this->settings = (array) get_option('AcceptStripePayments-settings');

        // Load plugin text domain
        add_action('init', array($this, 'load_plugin_textdomain'));

        //Check if IPN submitted
        add_action('init', array($this, 'asp_check_ipn'));

        // Activate plugin when new blog is added
        add_action('wpmu_new_blog', array($this, 'activate_new_site'));

        // Load public-facing style sheet and JavaScript.
        // add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
        // add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
        add_action('after_switch_theme', array($this, 'rewrite_flush'));
    }

    public function asp_check_ipn() {
        if (isset($_POST['asp_action'])) {
            if ($_POST['asp_action'] == 'process_ipn') {
                require_once(WP_ASP_PLUGIN_PATH . 'includes/process_ipn.php');
            }
        }
    }

    public function get_setting($field) {
        if (isset($this->settings[$field]))
            return $this->settings[$field];
        return false;
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
        if (null == self::$instance) {
            self::$instance = new self;
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
    public static function activate($network_wide) {

        if (function_exists('is_multisite') && is_multisite()) {

            if ($network_wide) {

                // Get all blog ids
                $blog_ids = self::get_blog_ids();

                foreach ($blog_ids as $blog_id) {
                    switch_to_blog($blog_id);
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
    public static function deactivate($network_wide) {

        if (function_exists('is_multisite') && is_multisite()) {

            if ($network_wide) {

                // Get all blog ids
                $blog_ids = self::get_blog_ids();

                foreach ($blog_ids as $blog_id) {

                    switch_to_blog($blog_id);
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
    public function activate_new_site($blog_id) {

        if (1 !== did_action('wpmu_new_blog')) {
            return;
        }

        switch_to_blog($blog_id);
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

        return $wpdb->get_col($sql);
    }

    /**
     * Fired for each blog when the plugin is activated.
     *
     * @since    1.0.0
     */
    private static function single_activate() {
        // Check if its a first install
        $default = array(
            'is_live' => 0,
            'dont_save_card' => 0,
            'currency_code' => 'USD',
            'button_text' => 'Buy Now',
            'use_new_button_method' => 0,
            'api_username' => 'xyz.biz_api1.abc.com',
            'api_password' => '1234567891',
            'api_signature' => 'xxxxxxx.xxxxxxxxxxxxxxx.xxxxxxxxxxxxxx-xxxxxxx',
            'checkout_url' => site_url('checkout'),
            'from_email_address' => get_bloginfo('name') . ' <sales@your-domain.com>',
            'buyer_email_subject' => 'Thank you for the purchase',
            'buyer_email_body' => "Hello\r\n\r\n"
            . "Thank you for your purchase! You ordered the following item(s):\r\n\r\n"
            . "{product_details}",
            'seller_notification_email' => get_bloginfo('admin_email'),
            'seller_email_subject' => 'Notification of product sale',
            'seller_email_body' => "Dear Seller\r\n\r\n"
            . "This mail is to notify you of a product sale.\r\n\r\n"
            . "{product_details}\r\n\r\n"
            . "The sale was made to {payer_email}\r\n\r\n"
            . "Thanks",
        );
        $opt = get_option('AcceptStripePayments-settings');
        if (empty($opt)) {
            add_option('AcceptStripePayments-settings', $default);
        } else { //lets add default values for some settings that were added after plugin update
            $opt_diff = array_diff_key($default, $opt);
            if (!empty($opt_diff)) {
                foreach ($opt_diff as $key => $value) {
                    $opt[$key] = $default[$key];
                }
                update_option('AcceptStripePayments-settings', $opt);
            }
        }
        //create checkout page           
        $args = array(
            'post_type' => 'page'
        );
        $pages = get_pages($args);
        $checkout_page_id = '';
        foreach ($pages as $page) {
            if (strpos($page->post_content, 'accept_stripe_payment_checkout') !== false) {
                $checkout_page_id = $page->ID;
            }
        }
        if ($checkout_page_id == '') {
            $checkout_page_id = AcceptStripePayments::create_post('page', 'Checkout-Result', 'Stripe-Checkout-Result', '[accept_stripe_payment_checkout]');
            $checkout_page = get_post($checkout_page_id);
            $checkout_page_url = $checkout_page->guid;
            $AcceptStripePayments_settings = get_option('AcceptStripePayments-settings');
            if (!empty($AcceptStripePayments_settings)) {
                $AcceptStripePayments_settings['checkout_url'] = $checkout_page_url;
                update_option('AcceptStripePayments-settings', $AcceptStripePayments_settings);
            }
        }
    }

    public static function create_post($postType, $title, $name, $content, $parentId = NULL) {
        $post = array(
            'post_title' => $title,
            'post_name' => $name,
            'comment_status' => 'closed',
            'ping_status' => 'closed',
            'post_content' => $content,
            'post_status' => 'publish',
            'post_type' => $postType
        );

        if ($parentId !== NULL) {
            $post['post_parent'] = $parentId;
        }
        $postId = wp_insert_post($post);
        return $postId;
    }

    /**
     * Fired for each blog when the plugin is deactivated.
     *
     * @since    1.0.0
     */
    private static function single_deactivate() {
        // @TODO: Define deactivation functionality here
    }

    /**
     * Load the plugin text domain for translation.
     */
    public function load_plugin_textdomain() {

        $domain = 'stripe-payments';
        $locale = apply_filters('plugin_locale', get_locale(), $domain);

        load_textdomain($domain, trailingslashit(WP_LANG_DIR) . $domain . '/' . $domain . '-' . $locale . '.mo');
        load_plugin_textdomain($domain, FALSE, basename(plugin_dir_path(dirname(__FILE__))) . '/languages/');
    }

    /**
     * @since    1.0.0
     */
    public function rewrite_flush() {
        flush_rewrite_rules();
    }

}
