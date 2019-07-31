<?php
class AcceptStripePaymentsPP
{
    var $tplCF;
    var $uniq_id;
    protected $AcceptStripePayments;
    function __construct()
    {
        $action = filter_input(INPUT_GET, 'asp_action', FILTER_SANITIZE_STRING);
        if ($action === 'show_pp') {
            $process_ipn = filter_input(INPUT_POST, 'asp_process_ipn', FILTER_SANITIZE_NUMBER_INT);
            if ($process_ipn) {
                return;
            }
            $this->AcceptStripePayments = AcceptStripePayments::get_instance();
            add_action('plugins_loaded', array($this, 'showpp'));
        }
        if (wp_doing_ajax()) {
            $this->AcceptStripePayments = AcceptStripePayments::get_instance();
            add_action('wp_ajax_asp_pp_req_token', array($this, 'handle_request_token'));
            add_action('wp_ajax_nopriv_asp_pp_req_token', array($this, 'handle_request_token'));
        }
    }

    function tpl_get_cf($output = '')
    {
        if (empty($this->tplCF)) {
            $replaceCF = apply_filters('asp_ng_button_output_replace_custom_field', '', array('product_id' => $this->prod_id, 'custom_field' => $this->custom_field));
            if (!empty($replaceCF)) {
                //we got custom field replaced
                $this->tplCF     = $replaceCF;
                $output         .= $this->tplCF;
                $this->tplCF     = '';
                return $output;
            }
            $field_type     = $this->AcceptStripePayments->get_setting('custom_field_type');
            $field_name     = $this->AcceptStripePayments->get_setting('custom_field_name');
            $field_name     = empty($field_name) ? __('Custom Field', 'stripe-payments') : $field_name;
            $field_descr     = $this->AcceptStripePayments->get_setting('custom_field_descr');
            $descr_loc     = $this->AcceptStripePayments->get_setting('custom_field_descr_location');
            $mandatory     = $this->AcceptStripePayments->get_setting('custom_field_mandatory');
            $tplCF         = '';
            $tplCF         .= "<div class='asp_product_custom_field_input_container'>";
            $tplCF         .= "<fieldset>";
            $tplCF         .= '<input type="hidden" name="stripeCustomFieldName" value="' . esc_attr($field_name) . '">';
            switch ($field_type) {
                case 'text':
                    if ($descr_loc !== 'below') {
                        $tplCF .= '<label class="asp_product_custom_field_label">' . $field_name . ' ' . '</label><input id="asp-custom-field" class="pure-input-1 asp_product_custom_field_input" type="text"' . ($mandatory ? ' data-asp-custom-mandatory' : '') . ' name="stripeCustomField" placeholder="' . $field_descr . '"' . ($mandatory ? ' required' : '') . '>';
                    } else {
                        $tplCF     .= '<label class="asp_product_custom_field_label">' . $field_name . ' ' . '</label><input id="asp-custom-field" class="pure-input-1 asp_product_custom_field_input" type="text"' . ($mandatory ? ' data-asp-custom-mandatory' : '') . ' name="stripeCustomField"' . ($mandatory ? ' required' : '') . '>';
                        $tplCF     .= '<div class="asp_product_custom_field_descr">' . $field_descr . '</div>';
                    }
                    break;
                case 'checkbox':
                    $tplCF .= '<label class="pure-checkbox asp_product_custom_field_label"><input id="asp-custom-field" class="pure-input-1 asp_product_custom_field_input" type="checkbox"' . ($mandatory ? ' data-asp-custom-mandatory' : '') . ' name="stripeCustomField"' . ($mandatory ? ' required' : '') . '>' . $field_descr . '</label>';
                    break;
            }
            $tplCF         .= "<span id='custom_field_error_explanation' class='pure-form-message asp_product_custom_field_error'></span>" .
                "</fieldset>" .
                "</div>";
            $this->tplCF     = $tplCF;
        }
        $cfPos = $this->AcceptStripePayments->get_setting('custom_field_position');
        if ($cfPos !== 'below') {
            $output         .= $this->tplCF;
            $this->tplCF     = '';
        } else {
            add_filter('asp_button_output_after_button', array($this, 'after_button_add_сf_filter'), 990, 3);
        }
        return $output;
    }

    function showpp()
    {
        $product_id = filter_input(INPUT_GET, 'product_id', FILTER_SANITIZE_NUMBER_INT);
        $this->item = new AcceptStripePayments_Item($product_id);

        if ($this->item->get_last_error()) {
            echo $this->item->get_last_error();
            exit;
        }

        $a = array();

        $a['prod_id'] = $product_id;

        $a['is_live'] = $this->AcceptStripePayments->is_live;

        $this->uniq_id = uniqid();

        $a['page_title'] = $this->item->get_name();
        $a['plugin_url'] = WP_ASP_PLUGIN_URL;
        $a['item_name'] = $this->item->get_name();
        $a['stripe_key'] = $this->AcceptStripePayments->APIPubKeyTest;

        //Custom Field if needed

        $custom_field     = get_post_meta($product_id, 'asp_product_custom_field', true);
        $cf_enabled     = $this->AcceptStripePayments->get_setting('custom_field_enabled');
        if (($custom_field === "") || $custom_field === "2") {
            $custom_field = $cf_enabled;
        } else {
            $custom_field = intval($custom_field);
        }
        if (!$cf_enabled) {
            $custom_field = $cf_enabled;
        }

        $this->custom_field = $custom_field;
        $this->prod_id = $product_id;

        if ($custom_field) {
            $a['custom_fields'] = $this->tpl_get_cf();
        }

        $currency=$this->item->get_currency();

        $a['scripts'] = array();
        $a['styles'] = array();
        $a['vars'] = array();
        $a['styles'] = apply_filters('asp_ng_pp_output_add_styles', $a['styles']);
        $a['scripts'] = apply_filters('asp_ng_pp_output_add_scripts', $a['scripts']);
        $a['vars'] = apply_filters('asp_ng_pp_output_add_vars', $a['vars']);

        //vars

        //Currency Display settings
        $display_settings     = array();
        $display_settings['c'] = $this->AcceptStripePayments->get_setting('price_decimals_num', 2);
        $display_settings['d'] = $this->AcceptStripePayments->get_setting('price_decimal_sep');
        $display_settings['t'] = $this->AcceptStripePayments->get_setting('price_thousand_sep');
        $currencies = $this->AcceptStripePayments::get_currencies();
        if (isset($currencies[$currency])) {
            $curr_sym = $currencies[$currency][1];
        } else {
            //no currency code found, let's just use currency code instead of symbol
            $curr_sym = $currencies;
        }
        $curr_pos = $this->AcceptStripePayments->get_setting('price_currency_pos');
        $display_settings['s']     = $curr_sym;
        $display_settings['pos']     = $curr_pos;

        $a['vars']['vars'] = array(
            'minAmounts' => $this->AcceptStripePayments->minAmounts,
            'zeroCents' => $this->AcceptStripePayments->zeroCents,
            'ajaxURL' => admin_url('admin-ajax.php'),
            'currencyFormat' => $display_settings,
            'payBtnText' => "Pay %s",
            'amountOpts' => array(
                'applySepOpts'     => $this->AcceptStripePayments->get_setting('price_apply_for_input'),
                'decimalSep'     => $this->AcceptStripePayments->get_setting('price_decimal_sep'),
                'thousandSep'     => $this->AcceptStripePayments->get_setting('price_thousand_sep'),
            ),
            'str' => array(
                'strEnterValidAmount' => apply_filters('asp_customize_text_msg', __('Please enter a valid amount', 'stripe-payments'), 'enter_valid_amount'),
                'strMinAmount' => apply_filters('asp_customize_text_msg', __('Minimum amount is', 'stripe-payments'), 'min_amount_is'),
                'strEnterQuantity'         => apply_filters('asp_customize_text_msg', __('Please enter quantity.', 'stripe-payments'), 'enter_quantity'),
                'strQuantityIsZero'         => apply_filters('asp_customize_text_msg', __('Quantity can\'t be zero.', 'stripe-payments'), 'quantity_is_zero'),
                'strQuantityIsFloat'         => apply_filters('asp_customize_text_msg', __('Quantity should be integer value.', 'stripe-payments'), 'quantity_is_float'),
                'strStockNotAvailable'         => apply_filters('asp_customize_text_msg', __('You cannot order more items than available: %d', 'stripe-payments'), 'stock_not_available'),
                'strTax'             => apply_filters('asp_customize_text_msg', __('Tax', 'stripe-payments'), 'tax_str'),
                'strShipping'             => apply_filters('asp_customize_text_msg', __('Shipping', 'stripe-payments'), 'shipping_str'),
                'strTotal'             => __('Total:', 'stripe-payments'),
                'strPleaseFillIn'         => apply_filters('asp_customize_text_msg', __('Please fill in this field.', 'stripe-payments'), 'fill_in_field'),
                'strPleaseCheckCheckbox'     => __('Please check this checkbox.', 'stripe-payments'),
                'strMustAcceptTos'         => apply_filters('asp_customize_text_msg', __('You must accept the terms before you can proceed.', 'stripe-payments'), 'accept_terms'),
                'strRemoveCoupon'         => apply_filters('asp_customize_text_msg', __('Remove coupon', 'stripe-payments'), 'remove_coupon'),
                'strRemove'             => apply_filters('asp_customize_text_msg', __('Remove', 'stripe-payments'), 'remove'),
                'strStartFreeTrial'         => apply_filters('asp_customize_text_msg', __('Start Free Trial', 'stripe-payments'), 'start_free_trial'),
                'strInvalidCFValidationRegex'     => __('Invalid validation RegEx: ', 'stripe-payments'),
            )
        );

        try {
            ASPMain::load_stripe_lib();
            $key = $this->AcceptStripePayments->is_live ? $this->AcceptStripePayments->APISecKey : $this->AcceptStripePayments->APISecKeyTest;
            \Stripe\Stripe::setApiKey($key);
        } catch (Exception $e) {
            $a['fatal_error'] = 'Stripe API error occurred:' . ' ' . $e->getMessage();
        }

        $a['amount_variable'] = false;
        if ($this->item->get_price() === 0) {
            $a['amount_variable'] = true;
        }

        $a['currency_variable'] = false;
        if ($this->item->is_currency_variable()) {
            $a['currency_variable'] = true;
        }

        if (!$a['amount_variable'] && !$a['currency_variable']) {
            try {
                $intent = \Stripe\PaymentIntent::create([
                    'amount' => $this->item->get_total(true),
                    'currency' => $this->item->get_currency(),
                ]);
            } catch (Exception $e) {
                $a['fatal_error'] = 'Stripe API error occurred:' . ' ' . $e->getMessage();
            }

            if (!isset($a['fatal_error'])) {

                $a['client_secret'] = $intent->client_secret;
            }
        }
        $a['currency'] = $this->item->get_currency();
        $pay_str = "Pay %s";
        $a['pay_btn_text'] = sprintf($pay_str, AcceptStripePayments::formatted_price($this->item->get_total(), $this->item->get_currency()));
        ob_start();
        require_once(WP_ASP_PLUGIN_PATH . 'public/views/templates/default/payment-popup.php');
        $tpl = ob_get_clean();
        echo $tpl;
        //        var_dump($a);
        exit;
    }

    function handle_request_token()
    {
        $out = array();
        $out['success'] = false;
        $amount = filter_input(INPUT_POST, 'amount', FILTER_SANITIZE_NUMBER_INT);
        $curr = filter_input(INPUT_POST, 'curr', FILTER_SANITIZE_STRING);

        try {
            ASPMain::load_stripe_lib();
            $key = $this->AcceptStripePayments->is_live ? $this->AcceptStripePayments->APISecKey : $this->AcceptStripePayments->APISecKeyTest;
            \Stripe\Stripe::setApiKey($key);
        } catch (Exception $e) {
            $out['err'] = 'Stripe API error occurred:' . ' ' . $e->getMessage();
            wp_send_json($out);
        }
        try {
            $intent = \Stripe\PaymentIntent::create([
                'amount' => $amount,
                'currency' => $curr,
            ]);
        } catch (Exception $e) {
            $out['err'] = 'Stripe API error occurred:' . ' ' . $e->getMessage();
            wp_send_json($out);
        }

        $out['success'] = true;
        $out['clientSecret'] = $intent->client_secret;
        wp_send_json($out);
        exit;
    }
}

new AcceptStripePaymentsPP();
