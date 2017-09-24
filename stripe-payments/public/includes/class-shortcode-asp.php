<?php

class AcceptStripePaymentsShortcode {

    var $AcceptStripePayments = null;
    var $StripeCSSInserted = false;

    /**
     * Instance of this class.
     *
     * @var      object
     */
    protected static $instance = null;
    protected static $payment_buttons = array();

    function __construct() {
        $this->AcceptStripePayments = AcceptStripePayments::get_instance();

        add_action('wp_enqueue_scripts', array($this, 'register_stripe_script'));

        add_shortcode('accept_stripe_payment', array(&$this, 'shortcode_accept_stripe_payment'));
        add_shortcode('accept_stripe_payment_checkout', array(&$this, 'shortcode_accept_stripe_payment_checkout'));
        add_shortcode('accept_stripe_payment_checkout_error', array(&$this, 'shortcode_accept_stripe_payment_checkout_error'));
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
            'thankyou_page_url' => '',
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

        if (!empty($thankyou_page_url)) {
            $thankyou_page_url = base64_encode($thankyou_page_url);
        } else {
            $thankyou_page_url = '';
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
        if (in_array($currency, $this->AcceptStripePayments->zeroCents)) {
            //this is zero-cents currency, amount shouldn't be multiplied by 100
            $priceInCents = $paymentAmount;
        } else {
            $priceInCents = $paymentAmount * 100;
        }
        if ((!isset($description) || empty($description)) && $price != 0) {
            //Create a description using quantity and payment amount
            $description = "{$quantity} piece" . ($quantity <> 1 ? "s" : "") . " for {$paymentAmount} {$currency}";
        }
        //This is public.css stylesheet
        //wp_enqueue_style('stripe-button-public');

        $button = "<button id='{$button_id}' type='submit' class='{$class}'><span>{$button_text}</span></button>";

        $checkout_lang = $this->AcceptStripePayments->get_setting('checkout_lang');

        $allowRememberMe = $this->AcceptStripePayments->get_setting('disable_remember_me');

        $allowRememberMe = ($allowRememberMe === 1) ? false : true;

        $data = array(
            'allowRememberMe' => $allowRememberMe,
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
            'zeroCents' => $this->AcceptStripePayments->zeroCents,
        );

        $output = '';

        //Let's insert Stripe default stylesheet only when it's needed
        if ($class == 'stripe-button-el' && !($this->StripeCSSInserted)) {
            $output = "<link rel='stylesheet' href='https://checkout.stripe.com/v3/checkout/button.css' type='text/css' media='all' />";
            $this->StripeCSSInserted = true;
        }

        $output .= "<form id='stripe_form_{$uniq_id}' action='' METHOD='POST'> ";

        if ($price == 0 || $this->AcceptStripePayments->get_setting('use_new_button_method')) {
            // variable amount or new method option is set in settings
            $output .= $this->get_button_code_new_method($data);
        } else {
            // use old method instead
            $output .= $this->get_button_code_old_method($data, $price, $button_text);
        }
        $output .= '<input type="hidden" name="asp_action" value="process_ipn" />';
        $output .= "<input type='hidden' value='{$name}' name='item_name' />";
        $output .= "<input type = 'hidden' value = '{$quantity}' name = 'item_quantity' />";
        $output .= "<input type = 'hidden' value = '{$currency}' name = 'currency_code' />";
        $output .= "<input type = 'hidden' value = '{$url}' name = 'item_url' />";
        $output .= "<input type = 'hidden' value = '{$thankyou_page_url}' name = 'thankyou_page_url' />";
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
          data-name='{$data['name']}'
          data-allow-remember-me='{$data['allowRememberMe']}'";
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

    function shortcode_accept_stripe_payment_checkout($atts, $content = '') {
        $aspData = array();
        if (isset($_SESSION['asp_data'])) {
            $aspData = $_SESSION['asp_data'];
        } else {
            // no session data, let's display nothing for now
            return;
        }
        if (empty($content)) {
            //this is old shortcode. Let's display the default output for backward compatability
            if (isset($aspData['error_msg']) && !empty($aspData['error_msg'])) {
                //some error occured, let's display it
                return __("System was not able to complete the payment.", "stripe-payments") . ' ' . $aspData['error_msg'];
            }
            $output = '';
            $output .= '<p class="asp-thank-you-page-msg1">' . __("Thank you for your payment.", "stripe-payments") . '</p>';
            $output .= '<p class="asp-thank-you-page-msg2">' . __("Here's what you purchased: ", "stripe-payments") . '</p>';
            $output .= '<div class="asp-thank-you-page-product-name">' . __("Product Name: ", "stripe-payments") . $aspData['item_name'] . '</div>';
            $output .= '<div class="asp-thank-you-page-qty">' . __("Quantity: ", "stripe-payments") . $aspData['item_quantity'] . '</div>';
            $output .= '<div class="asp-thank-you-page-qty">' . __("Amount: ", "stripe-payments") . $aspData['item_price'] . ' ' . $aspData['currency_code'] . '</div>';
            $output .= '<div class="asp-thank-you-page-txn-id">' . __("Transaction ID: ", "stripe-payments") . $aspData['txn_id'] . '</div>';

            if (!empty($aspData['item_url'])) {
                $output .= "<div class='asp-thank-you-page-download-link'>";
                $output .= __("Please ", "stripe-payments") . "<a href='" . $aspData['item_url'] . "'>" . __("click here", "stripe-payments") . "</a>" . __(" to download.", "stripe-payments");
                $output .= "</div>";
            }

            $output = apply_filters('asp_stripe_payments_checkout_page_result', $output, $aspData); //Filter that allows you to modify the output data on the checkout result page

            $wrap = "<div class='asp-thank-you-page-wrap'>";
            $wrap .= "<div class='asp-thank-you-page-msg-wrap' style='background: #dff0d8; border: 1px solid #C9DEC1; margin: 10px 0px; padding: 15px;'>";
            $output = $wrap . $output;
            $output .= "</div>"; //end of .asp-thank-you-page-msg-wrap
            $output .= "</div>"; //end of .asp-thank-you-page-wrap

            return $output;
        }
        if (isset($aspData['error_msg']) && !empty($aspData['error_msg'])) {
            //some error occured. We don't display any content to let the error shortcode handle it
            return;
        }
        $content = $this->apply_content_tags(do_shortcode($content), $aspData);
        return $content;
    }

    function shortcode_accept_stripe_payment_checkout_error($atts, $content = '') {
        $aspData = array();
        if (isset($_SESSION['asp_data'])) {
            $aspData = $_SESSION['asp_data'];
        } else {
            // no session data, let's display nothing for now
            return;
        }
        if (isset($aspData['error_msg']) && !empty($aspData['error_msg'])) {
            //some error occured. Let's display error message
            $content = $this->apply_content_tags(do_shortcode($content), $aspData);
            return $content;
        }
        // no error occured - we don't display anything
        return;
    }

    function apply_content_tags($content, $data) {
        $tags = array();
        $vals = array();

        foreach ($data as $key => $value) {
            if ($key == 'stripeEmail') {
                $key = 'payer_email';
            }
            if ($key == 'txn_id') {
                $key = 'transaction_id';
            }
            $tags[] = '{' . $key . '}';
            $vals[] = $value;
        }

        $content = str_replace($tags, $vals, $content);
        return $content;
    }

}
