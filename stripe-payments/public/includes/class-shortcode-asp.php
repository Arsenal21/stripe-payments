<?php

class AcceptStripePaymentsShortcode {

    var $AcceptStripePayments = null;
    var $zeroCents = array('JPY', 'MGA', 'VND', 'KRW');

    /**
     * Instance of this class.
     *
     * @var      object
     */
    protected static $instance = null;
    protected static $payment_buttons = array();

    function __construct() {
        $this->AcceptStripePayments = AcceptStripePayments::get_instance();

        add_action('wp_enqueue_scripts', array($this, 'register_stripe_stylesheet'));
        add_action('wp_enqueue_scripts', array($this, 'register_stripe_script'));

        add_shortcode('accept_stripe_payment', array(&$this, 'shortcode_accept_stripe_payment'));
        add_shortcode('accept_stripe_payment_checkout', array(&$this, 'shortcode_accept_stripe_payment_checkout'));
        if (!is_admin()) {
            add_filter('widget_text', 'do_shortcode');
        }
    }

    public function interfer_for_redirect() {
        global $post;
        if (!is_admin()) {
            if (has_shortcode($post->post_content, 'accept_stripe_payment_checkout')) {
                $this->shortcode_accept_stripe_payment_checkout();
                exit;
            }
        }
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

    function register_stripe_stylesheet() {
        wp_register_style('stripe-stylesheet', 'https://checkout.stripe.com/v3/checkout/button.css', array(), null);
        //wp_register_style('stripe-button-public', WP_ASP_PLUGIN_URL . 'public/assets/css/public.css');
    }

    function register_stripe_script() {
        wp_register_script('stripe-script', 'https://checkout.stripe.com/checkout.js', array(), null);
        wp_register_script('stripe-handler', WP_ASP_PLUGIN_URL . '/public/assets/js/stripe-handler.js', array('jquery'), WP_ASP_PLUGIN_VERSION);
        //localization data and Stripe API key
        $loc_data = array(
            'strEnterValidAmount' => __('Please enter a valid amount', 'stripe-payments'),
            'strMinAmount' => __('Minimum amount is 0.5', 'stripe-payments'),
            'key' => $this->AcceptStripePayments->get_setting('api_publishable_key'),
        );
        wp_localize_script('stripe-handler', 'stripehandler', $loc_data);
    }

    function shortcode_accept_stripe_payment($atts) {

        extract(shortcode_atts(array(
            'name' => '',
            'class' => 'stripe-button-el', //default Stripe button class
            'price' => '0',
            'quantity' => '1',
            'description' => '',
            'url' => '',
            'item_logo' => '',
            'billing_address' => '',
            'shipping_address' => '',
            'currency' => $this->AcceptStripePayments->get_setting('currency_code'),
            'button_text' => $this->AcceptStripePayments->get_setting('button_text'),
                        ), $atts));

        if (empty($name)) {
            $error_msg = '<div class="stripe_payments_error_msg" style="color: red;">';
            $error_msg .= 'There is an error in your Stripe Payments shortcode. It is missing the "name" field. ';
            $error_msg .= 'You must specify an item name value using the "name" parameter. This value should be unique so this item can be identified uniquely on the page.';
            $error_msg .= '</div>';
            return $error_msg;
        }

        if (!empty($url)) {
            $url = base64_encode($url);
        } else {
            $url = '';
        }
        if (!is_numeric($quantity)) {
            $quantity = strtoupper($quantity);
        }
        if ($quantity == "N/A") {
            $quantity = "NA";
        }
        $uniq_id = count(self::$payment_buttons);
        $button_id = 'stripe_button_' . $uniq_id;
        self::$payment_buttons[] = $button_id;
        $paymentAmount = ("$quantity" === "NA" ? $price : ($price * $quantity));
        if (in_array($currency, $this->zeroCents)) {
            //this is zero-cents currency, amount shouldn't be multiplied by 100
            $priceInCents = $paymentAmount;
        } else {
            $priceInCents = $paymentAmount * 100;
        }
        if ((!isset($description) || empty($description)) && $price != 0) {
            //Create a description using quantity and payment amount
            $description = "{$quantity} piece" . ($quantity <> 1 ? "s" : "") . " for {$paymentAmount} {$currency}";
        }
        //Let's enqueue Stripe default stylesheet only when it's needed
        if ($class == 'stripe-button-el') {
            wp_enqueue_style('stripe-stylesheet');
        }
        //This is public.css stylesheet
        //wp_enqueue_style('stripe-button-public');

        $button = "<button id='{$button_id}' type='submit' class='{$class}'><span>{$button_text}</span></button>";

        $checkout_lang = $this->AcceptStripePayments->get_setting('checkout_lang');

        $data = array(
            'description' => $description,
            'image' => $item_logo,
            'currency' => $currency,
            'locale' => (empty($checkout_lang) ? 'auto' : $checkout_lang),
            'name' => $name,
            'url' => $url,
            'amount' => $priceInCents,
            'billingAddress' => (empty($billing_address) ? false : true),
            'shippingAddress' => (empty($shipping_address) ? false : true),
            'uniq_id' => $uniq_id,
            'variable' => ($price == 0 ? true : false),
            'zeroCents' => $this->zeroCents,
        );

        $output = "<form id='stripe_form_{$uniq_id}' action='" . $this->AcceptStripePayments->get_setting('checkout_url') . "' METHOD='POST'> ";

        if ($price == 0 || $this->AcceptStripePayments->get_setting('use_new_button_method')) {
            // variable amount or new method option is set in settings
            $output .= $this->get_button_code_new_method($data);
        } else {
            // use old method instead
            $output .= $this->get_button_code_old_method($data, $price, $button_text);
        }
        $output .= "<input type='hidden' value='{$name}' name='item_name' />";
        $output .= "<input type = 'hidden' value = '{$quantity}' name = 'item_quantity' />";
        $output .= "<input type = 'hidden' value = '{$currency}' name = 'currency_code' />";
        $output .= "<input type = 'hidden' value = '{$url}' name = 'item_url' />";
        $output .= "<input type = 'hidden' value = '{$description}' name = 'charge_description' />"; //

        $trans_name = 'stripe-payments-' . sanitize_title_with_dashes($name); //Create key using the item name.
        set_transient($trans_name, $price, 2 * 3600); //Save the price for this item for 2 hours.
        $output .= wp_nonce_field('stripe_payments', '_wpnonce', true, false);
        $output .= $button;
        $output .= "</form>";
        return $output;
    }

    function get_button_code_old_method($data, $price, $button_text) {
        $output = "<input type='hidden' value='{$price}' name='item_price' />";
        //Lets hide default Stripe button. We'll be using our own instead for styling purposes
        $output .= "<div style='display: none !important'>";
        $output .= "<script src='https://checkout.stripe.com/checkout.js' class='stripe-button'
          data-key='" . $this->AcceptStripePayments->get_setting('api_publishable_key') . "'
          data-panel-label='Pay'
          data-amount='{$data['amount']}' 
          data-name='{$data['name']}'";
        $output .= "data-description='{$data['description']}'";
        $output .= "data-label='{$button_text}'";
        $output .= "data-currency='{$data['currency']}'";
        $output .= "data-locale='{$data['locale']}'";
        if (!empty($data['image'])) {//Show item logo/thumbnail in the stripe payment window
            $output .= "data-image='{$data['image']}'";
        }

        if ($data['billingAddress']) {
            $output .= "data-billing-address='{$data['billingAddress']}'";
        }
        if ($data['shippingAddress']) {
            $output .= "data-shipping-address='{$data['shippingAddress']}'";
        }
        $output .= apply_filters('asp_additional_stripe_checkout_data_parameters', ''); //Filter to allow the addition of extra data parameters for stripe checkout.
        $output .= "></script>";
        $output .= '</div>';
        return $output;
    }

    function get_button_code_new_method($data) {
        $output = '';
        if ($data['amount'] == 0) { //price not specified, let's add an input box for user to specify the amount
            $output .= "<p>"
                    . "<input style='max-width: 10em;' type='text' id='stripeAmount_{$data['uniq_id']}' value='' name='stripeAmount' placeholder='" . __('Enter amount', 'stripe-payments') . "' required/>"
                    . "<span> {$data['currency']}</span>"
                    . "<span style='display: block;' id='error_explanation_{$data['uniq_id']}'></span>"
                    . "</p>";
        }
        $output .= "<input type='hidden' id='stripeToken_{$data['uniq_id']}' name='stripeToken' />"
                . "<input type='hidden' id='stripeTokenType_{$data['uniq_id']}' name='stripeTokenType' />"
                . "<input type='hidden' id='stripeEmail_{$data['uniq_id']}' name='stripeEmail' />"
                . "<input type='hidden' data-stripe-button-uid='{$data['uniq_id']}' />";
        //Let's enqueue Stripe js
        wp_enqueue_script('stripe-script');
        //using nested array in order to ensure boolean values are not converted to strings by wp_localize_script function
        wp_localize_script('stripe-handler', 'stripehandler' . $data['uniq_id'], array('data' => $data));
        //enqueue our script that handles the stuff
        wp_enqueue_script('stripe-handler');
        return $output;
    }

    /*
     * This shortcode processes the payment data after the payment.
     */

    public function shortcode_accept_stripe_payment_checkout($atts = array()) {

        extract(shortcode_atts(array(
            'currency' => $this->AcceptStripePayments->get_setting('currency_code'),
                        ), $atts)
        );
        //Check nonce
        $nonce = $_REQUEST['_wpnonce'];
        if (!wp_verify_nonce($nonce, 'stripe_payments')) {
            //The user is likely directly viewing this page.
            echo '<div style="background: #FFF6D5; border: 1px solid #D1B655; color: #3F2502; margin: 10px 0px; padding: 10px;">';
            echo '<p>The message in this box is ONLY visible to you because you are viewing this page directly. Your customers won\'t see this message.</p>';
            echo '<p>Your customers will get sent to this page after the transaction. This page will work correctly when customers get redirected here AFTER the payment.</p>';
            echo '<p>You can edit this page from your admin dashboard and add extra message that your customers will see after the payment.</p>';
            echo '<p>Nonce Security Check Failed!</p>';
            echo '</div>';
            return;
        }
        if (!isset($_POST['item_name']) || empty($_POST['item_name'])) {
            echo ('Invalid Item name');
            return;
        }
        if (!isset($_POST['stripeToken']) || empty($_POST['stripeToken'])) {
            echo ('Invalid Stripe Token');
            return;
        }
        if (!isset($_POST['stripeTokenType']) || empty($_POST['stripeTokenType'])) {
            echo ('Invalid Stripe Token Type');
            return;
        }
        if (!isset($_POST['stripeEmail']) || empty($_POST['stripeEmail'])) {
            echo ('Invalid Request');
            return;
        }
        if (!isset($_POST['currency_code']) || empty($_POST['currency_code'])) {
            echo ('Invalid Currency Code');
            return;
        }

        $item_name = sanitize_text_field($_POST['item_name']);
        $stripeToken = sanitize_text_field($_POST['stripeToken']);
        $stripeTokenType = sanitize_text_field($_POST['stripeTokenType']);
        $stripeEmail = sanitize_email($_POST['stripeEmail']);
        $item_quantity = sanitize_text_field($_POST['item_quantity']);
        $item_url = sanitize_text_field($_POST['item_url']);
        $charge_description = sanitize_text_field($_POST['charge_description']);

        //$item_price = sanitize_text_field($_POST['item_price']);
        $trans_name = 'stripe-payments-' . sanitize_title_with_dashes($item_name);
        $item_price = get_transient($trans_name); //Read the price for this item from the system.
        if (!is_numeric($item_price)) {
            echo ('Invalid item price');
            return;
        }
        if ($item_price == 0) { //Custom amount
            $item_price = floatval($_POST['stripeAmount']);
            if (!is_numeric($item_price)) {
                echo ('Invalid item price');
                return;
            }
        }
        $currency_code = sanitize_text_field($_POST['currency_code']);
        $paymentAmount = ($item_quantity !== "NA" ? ($item_price * $item_quantity) : $item_price);

        $currencyCodeType = strtolower($currency_code);


        Stripe::setApiKey($this->AcceptStripePayments->get_setting('api_secret_key'));


        $GLOBALS['asp_payment_success'] = false;

        $opt = get_option('AcceptStripePayments-settings');

        if (in_array($currency_code, $this->zeroCents)) {
            $amount = $paymentAmount;
        } else {
            $amount = $paymentAmount * 100;
        }

        ob_start();
        try {

            if ($opt['dont_save_card'] == 1) {
                $charge = Stripe_Charge::create(array(
                            'amount' => $amount,
                            'currency' => $currencyCodeType,
                            'description' => $charge_description,
                            'source' => $stripeToken,
                ));
            } else {

                $customer = Stripe_Customer::create(array(
                            'email' => $stripeEmail,
                            'card' => $stripeToken
                ));

                $charge = Stripe_Charge::create(array(
                            'customer' => $customer->id,
                            'amount' => $amount,
                            'currency' => $currencyCodeType,
                            'description' => $charge_description,
                ));
            }
            //Grab the charge ID and set it as the transaction ID.
            $txn_id = $charge->id; //$charge->balance_transaction;
            //Core transaction data
            $data = array();
            $data['item_name'] = $item_name;
            $data['stripeToken'] = $stripeToken;
            $data['stripeTokenType'] = $stripeTokenType;
            $data['stripeEmail'] = $stripeEmail;
            $data['item_quantity'] = $item_quantity;
            $data['item_price'] = $item_price;
            $data['currency_code'] = $currency_code;
            $data['txn_id'] = $txn_id; //The Stripe charge ID
            $data['charge_description'] = $charge_description;

            $post_data = array_map('sanitize_text_field', $data);

            //Billing address data (if any)
            $billing_address = "";
            $billing_address .= sanitize_text_field((isset($_POST['stripeBillingName']) ? $_POST['stripeBillingName'] : '')) . "\n";
            $billing_address .= sanitize_text_field((isset($_POST['stripeBillingAddressLine1']) ? $_POST['stripeBillingAddressLine1'] : '')) . sanitize_text_field(isset($_POST['stripeBillingAddressApt']) ? ' ' . $_POST['stripeBillingAddressApt'] : '') . "\n";
            $billing_address .= sanitize_text_field((isset($_POST['stripeBillingAddressZip']) ? $_POST['stripeBillingAddressZip'] : '')) . "\n";
            $billing_address .= sanitize_text_field((isset($_POST['stripeBillingAddressCity']) ? $_POST['stripeBillingAddressCity'] : '')) . "\n";
            $billing_address .= sanitize_text_field((isset($_POST['stripeBillingAddressState']) ? $_POST['stripeBillingAddressState'] : '')) . "\n";
            $billing_address .= sanitize_text_field((isset($_POST['stripeBillingAddressCountry']) ? $_POST['stripeBillingAddressCountry'] : '')) . "\n";
            $post_data['billing_address'] = $billing_address;

            //Shipping address data (if any)
            $shipping_address = "";
            $shipping_address .= sanitize_text_field((isset($_POST['stripeShippingName']) ? $_POST['stripeShippingName'] : '')) . "\n";
            $shipping_address .= sanitize_text_field(isset($_POST['stripeShippingAddressLine1']) ? $_POST['stripeShippingAddressLine1'] : '') . sanitize_text_field(isset($_POST['stripeShippingAddressApt']) ? ' ' . $_POST['stripeShippingAddressApt'] : '') . "\n";
            $shipping_address .= sanitize_text_field((isset($_POST['stripeShippingAddressZip']) ? $_POST['stripeShippingAddressZip'] : '')) . "\n";
            $shipping_address .= sanitize_text_field((isset($_POST['stripeShippingAddressCity']) ? $_POST['stripeShippingAddressCity'] : '')) . "\n";
            $shipping_address .= sanitize_text_field((isset($_POST['stripeShippingAddressState']) ? $_POST['stripeShippingAddressState'] : '')) . "\n";
            $shipping_address .= sanitize_text_field((isset($_POST['stripeShippingAddressCountry']) ? $_POST['stripeShippingAddressCountry'] : '')) . "\n";
            $post_data['shipping_address'] = $shipping_address;

            //Insert the order data to the custom post
            $order = ASPOrder::get_instance();
            $order_post_id = $order->insert($post_data, $charge);

            $post_data['order_post_id'] = $order_post_id;

            //Action hook with the checkout post data parameters.
            do_action('asp_stripe_payment_completed', $post_data, $charge);

            //Action hook with the order object.
            do_action('AcceptStripePayments_payment_completed', $order, $charge);

            $GLOBALS['asp_payment_success'] = true;
            $item_url = base64_decode($item_url);
            $post_data['item_url'] = $item_url;

            //Let's handle email sending stuff

            if (isset($opt['send_emails_to_buyer'])) {
                if ($opt['send_emails_to_buyer']) {
                    $from = $opt['from_email_address'];
                    $to = $post_data['stripeEmail'];
                    $subj = $opt['buyer_email_subject'];
                    $body = $this->apply_dynamic_tags_on_email_body($opt['buyer_email_body'], $post_data);
                    $headers = 'From: ' . $from . "\r\n";

                    $subj = apply_filters('asp_buyer_email_subject', $subj, $post_data);
                    $body = apply_filters('asp_buyer_email_body', $body, $post_data);
                    wp_mail($to, $subj, $body, $headers);
                }
            }
            if (isset($opt['send_emails_to_seller'])) {
                if ($opt['send_emails_to_seller']) {
                    $from = $opt['from_email_address'];
                    $to = $opt['seller_notification_email'];
                    $subj = $opt['seller_email_subject'];
                    $body = $this->apply_dynamic_tags_on_email_body($opt['seller_email_body'], $post_data);
                    $headers = 'From: ' . $from . "\r\n";

                    $subj = apply_filters('asp_seller_email_subject', $subj, $post_data);
                    $body = apply_filters('asp_seller_email_body', $body, $post_data);
                    wp_mail($to, $subj, $body, $headers);
                }
            }
        } catch (Exception $e) {
            //If the charge fails (payment unsuccessful), this code will get triggered.
            if (!empty($charge->failure_code))
                $GLOBALS['asp_error'] = $charge->failure_code . ": " . $charge->failure_message;
            else {
                $GLOBALS['asp_error'] = $e->getMessage();
            }
        }

        //Show the "payment success" or "payment failure" info on the checkout complete page.
        include dirname(dirname(__FILE__)) . '/views/checkout.php';

        return ob_get_clean();
    }

    function apply_dynamic_tags_on_email_body($body, $post) {
        $product_details = __("Product Name: ", "stripe-payments") . $post['item_name'] . "\n";
        $product_details .= __("Quantity: ", "stripe-payments") . $post['item_quantity'] . "\n";
        $product_details .= __("Amount: ", "stripe-payments") . $post['item_price'] . ' ' . $post['currency_code'] . "\n";
        $product_details .= "--------------------------------" . "\n";
        $product_details .= __("Total Amount: ", "stripe-payments") . ($post['item_price'] * $post['item_quantity']) . ' ' . $post['currency_code'] . "\n";
        if (!empty($post['item_url']))
            $product_details .= "\n\n" . __("Download link: ", "stripe-payments") . $post['item_url'];

        $tags = array("{product_details}", "{payer_email}", "{transaction_id}", "{purchase_amt}", "{purchase_date}", "{shipping_address}", "{billing_address}");
        $vals = array($product_details, $post['stripeEmail'], $post['txn_id'], $post['item_price'], date("F j, Y, g:i a", strtotime('now')), $post['shipping_address'], $post['billing_address']);

        $body = stripslashes(str_replace($tags, $vals, $body));

        return $body;
    }

}
