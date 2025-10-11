<?php

class ASP_SWPM_Integrations {

    public function __construct() {
        add_action('plugins_loaded', array($this, 'handle_plugins_loaded'));
        add_action('init', array($this, 'handle_init'));
    }

    public function handle_plugins_loaded() {
        if (defined('SIMPLE_WP_MEMBERSHIP_VER')) {
            add_action('asp_stripe_payment_completed', array($this, 'handle_swpm_signup'), 10, 2);
            add_action('asp_stripe_payment_completed', array($this, 'handle_swpm_account_connection'), 10, 2);
        }
    }

    public function handle_init() {
        add_shortcode('asp_swpm_purchase_history', array($this, 'show_asp_swpm_purchase_history'));
    }

    public function handle_swpm_signup($data, $charge) {
        if (empty($data['product_id'])) {
            return;
        }

        //let's check if Membership Level is set for this product
        $level_id = get_post_meta($data['product_id'], 'asp_product_swpm_level', true);
        if (empty($level_id)) {
            return;
        }

        //let's form data required for eMember_handle_subsc_signup_stand_alone function and call it
        $first_name = '';
        $last_name  = '';
        if (! empty($data['customer_name'])) {
            // let's try to create first name and last name from full name
            $parts      = explode(' ', $data['customer_name']);
            $last_name  = array_pop($parts);
            $first_name = implode(' ', $parts);
        }
        $addr_street  = isset($_POST['stripeBillingAddressLine1']) ? sanitize_text_field($_POST['stripeBillingAddressLine1']) : '';
        $addr_zip     = isset($_POST['stripeBillingAddressZip']) ? sanitize_text_field($_POST['stripeBillingAddressZip']) : '';
        $addr_city    = isset($_POST['stripeBillingAddressCity']) ? sanitize_text_field($_POST['stripeBillingAddressCity']) : '';
        $addr_state   = isset($_POST['stripeBillingAddressState']) ? sanitize_text_field($_POST['stripeBillingAddressState']) : '';
        $addr_country = isset($_POST['stripeBillingAddressCountry']) ? sanitize_text_field($_POST['stripeBillingAddressCountry']) : '';

        if (empty($addr_street) && ! empty($charge->source->address_line1)) {
            $addr_street = $charge->source->address_line1;
        }

        if (empty($addr_zip) && ! empty($charge->source->address_zip)) {
            $addr_zip = $charge->source->address_zip;
        }

        if (empty($addr_city) && ! empty($charge->source->address_city)) {
            $addr_city = $charge->source->address_city;
        }

        if (empty($addr_state) && ! empty($charge->source->address_state)) {
            $addr_state = $charge->source->address_state;
        }

        if (empty($addr_country) && ! empty($charge->source->address_country)) {
            $addr_country = $charge->source->address_country;
        }

        //get address from new API payment data
        $ipn = ASP_Process_IPN_NG::get_instance();

        if (isset($ipn->p_data)) {
            $addr = $ipn->p_data->get_billing_details();
            if ($addr) {
                if (empty($addr_street) && ! empty($addr->line1)) {
                    $addr_street = $addr->line1;
                }
                if (empty($addr_zip) && ! empty($addr->postal_code)) {
                    $addr_zip = $addr->postal_code;
                }

                if (empty($addr_city) && ! empty($addr->city)) {
                    $addr_city = $addr->city;
                }

                if (empty($addr_state) && ! empty($addr->state)) {
                    $addr_state = $addr->state;
                }

                if (empty($addr_country) && ! empty($addr->country)) {
                    $addr_country = $addr->country;
                }
            }
        }

        if (! empty($addr_country)) {
            //convert country code to country name
            $countries = ASP_Utils::get_countries_untranslated();
            if (isset($countries[$addr_country])) {
                $addr_country = $countries[$addr_country];
            }
        }

        $ipn_data = array(
            'payer_email'     => $data['stripeEmail'],
            'first_name'      => $first_name,
            'last_name'       => $last_name,
            'txn_id'          => $data['txn_id'],
            'address_street'  => $addr_street,
            'address_city'    => $addr_city,
            'address_state'   => $addr_state,
            'address_zip'     => $addr_zip,
            'address_country' => $addr_country,
        );

        ASP_Debug_Logger::log('Calling swpm_handle_subsc_signup_stand_alone');

        $swpm_id = '';
        if (SwpmMemberUtils::is_member_logged_in()) {
            $swpm_id = SwpmMemberUtils::get_logged_in_members_id();
        }

        if (defined('SIMPLE_WP_MEMBERSHIP_PATH')) {
            if (!function_exists('swpm_handle_subsc_signup_stand_alone')) {
                require_once(SIMPLE_WP_MEMBERSHIP_PATH . 'ipn/swpm_handle_subsc_ipn.php');
            }

            swpm_handle_subsc_signup_stand_alone($ipn_data, $level_id, $data['txn_id'], $swpm_id);
        }
    }

    public function handle_swpm_account_connection($data, $charge) {
        if (empty($data['product_id'])) {
            return;
        }

        $order_post_id = $data['order_post_id'];

        // Check if user is logged in
        if (!SwpmMemberUtils::is_member_logged_in()) {
            return;
        }

        $member_id = SwpmMemberUtils::get_logged_in_members_id();
        if (!is_numeric($member_id)) {
            return;
        }

        // Valid swpm member ID found, let's save it to order meta;
        update_post_meta($order_post_id, 'asp_product_swpm_member_id', $member_id);

        ASP_Debug_Logger::log('Connected SWPM member ID ' . $member_id . ' to order ID ' . $order_post_id);
    }

    public function show_asp_swpm_purchase_history($atts) {
        $is_logged_in = SwpmMemberUtils::is_member_logged_in();
        $member_id = SwpmMemberUtils::get_logged_in_members_id();
        
        if (empty($is_logged_in) || !is_numeric($member_id)) {
            return '<p class="asp-swpm-purchase-history-not-logged-in">' . esc_html__('You need to be logged in to view your purchase history.', 'stripe-payments') . '</p>';
        }

        $atts = shortcode_atts(array(
            'show_downloads' => 0,
        ), $atts);

        $show_downloads = boolval($atts['show_downloads']);

        // Get all order cpt (stripe_order) for this SWPM member by member ID by the 'asp_product_swpm_member_id' post meta.
        $orders = get_posts(array(
            'post_type'      => 'stripe_order',
            'posts_per_page' => -1,
            'field'          => 'ids',
            'meta_query'     => array(
                array(
                    'key'   => 'asp_product_swpm_member_id',
                    'value' => $member_id,
                ),
            ),
        ));

        if (empty($orders)) {
            return '<p class="asp-swpm-purchase-history-not-found">' . esc_html__('No purchase history found.', 'stripe-payments') . '</p>';
        }

        $transactions_data = array();

        foreach ($orders as $order) {
            $product_id = get_post_meta($order->ID, 'asp_product_id', true);
            
            if (empty($product_id)) {
                continue;
            }

            // $product_name = get_the_title($product_id);
            $order_data = get_post_meta($order->ID, 'order_data', true);

            // Format the date using the saved wp date format string;
            // $date_format = get_option('date_format') . ' ' . get_option('time_format');
            $date_format = get_option('date_format');
            $formatted_date = date($date_format, strtotime($order->post_date));

            $product_name = isset($order_data['item_name']) ? sanitize_text_field($order_data['item_name']) : '-';
            $txn_id = isset($order_data['txn_id']) ? sanitize_text_field($order_data['txn_id']) : '-';
            $item_url = isset($order_data['item_url']) ? sanitize_url($order_data['item_url']) : '';
            $quantity = isset($order_data['item_quantity']) ? intval($order_data['item_quantity']) : 0;
            $paid_amount = isset($order_data['paid_amount']) ? $order_data['paid_amount'] : 0;
            $currency_code = isset($order_data['currency_code']) ? strtoupper($order_data['currency_code']) : 'USD';
            // Get the formatted amount with currency symbol
            $paid_amount = ASP_Utils::formatted_price($paid_amount, $currency_code);

            $transactions_data[] = array(
                'product_name' => $product_name,
                'quantity' => $quantity,
                'txn_id' => $txn_id,
                'item_url' => $item_url,
                'purchase_date' => $formatted_date,
                'paid_amount'   => $paid_amount,
            );
        }

        $output = '';
        ob_start();
        ?>
        <style>
        .asp-swpm-purchase-history-wrapper {
            overflow-x: auto;
        }
        .asp-swpm-purchase-history-table {
            width: 100%;
        }
        .asp-swpm-purchase-history-table th, 
        .asp-swpm-purchase-history-table td {
            text-align: left;
            vertical-align: top;
            padding: 6px 4px;
        }
        /* .asp-swpm-purchase-history-table tr :first-child{
            padding-left: 0;
        } */
        /* .asp-swpm-purchase-history-table tr :last-child{
            padding-right: 0;
        } */
        .asp-swpm-purchase-history-table td:first-child{
            min-width: 200px;
        }
        </style>
        <div class="asp-swpm-purchase-history-wrapper">
            <table class="widefat asp-swpm-purchase-history-table">
                <thead>
                    <tr>
                        <th><?php echo esc_html__('Product', 'stripe-payments'); ?></th>
                        <th><?php echo esc_html__('Amount', 'stripe-payments'); ?></th>
                        <th><?php echo esc_html__('Date', 'stripe-payments'); ?></th>
                        <?php if ($show_downloads) : ?>
                            <th><?php echo esc_html__('Downloads', 'stripe-payments'); ?></th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    foreach ($transactions_data as $txn) { 
                    ?>
                    <tr>
                        <td>
                            <?php 
                            echo esc_html($txn['product_name']); 
                            if (!empty($txn['quantity']) && $txn['quantity'] > 1) {
                                echo ' x' . esc_html($txn['quantity']);
                            }
                            ?>
                        </td>
                        <td>
                            <?php echo esc_html($txn['paid_amount']) ?>
                        </td>
                        <td>
                            <?php echo esc_html($txn['purchase_date']) ?>
                        </td>
                        <?php if ($show_downloads) { ?>
                        <td>
                            <?php if (!empty($txn['item_url'])) { ?>
                                <a href="<?php echo esc_url($txn['item_url']) ?>"><?php esc_html_e('Download', 'stripe-payments') ?></a>
                            <?php } else { ?>
                                <?php esc_html_e('-', 'stripe-payments') ?>
                            <?php } ?>
                        </td>
                        <?php } ?>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
        <?php
        $output .= ob_get_clean();
        return $output;
    }
}

new ASP_SWPM_Integrations();
