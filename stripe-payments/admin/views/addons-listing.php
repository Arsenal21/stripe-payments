<?php
/**
 * Represents the view for the addons listing page.
 */
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'You do not have permission to access this menu page.' );
}

$output = '';
echo '<link type="text/css" rel="stylesheet" href="' . WP_ASP_PLUGIN_URL . '/admin/assets/css/stripe-addons-listing.css" />' . "\n";
?>

<div class="wrap">
    <h1>Add-ons</h1>

    <div id="poststuff"><div id="post-body">

            <?php
            $addons_data = array();
            
            $addon_1 = array(
                "name" => "Subscription Payments",
                "thumbnail" => WP_ASP_PLUGIN_URL . "/admin/assets/images/subscription-payments.png",
                "description" => "This addon allows you to configure and sell subscription and recurring payments to your customers.",
                "page_url" => "https://s-plugins.com/stripe-subscription-payments-addon/",
            );
            array_push($addons_data, $addon_1);

            $addon_2 = array(
                "name" => "Apple and Google Pay",
                "thumbnail" => WP_ASP_PLUGIN_URL . "/admin/assets/images/apple-android-pay.png",
                "description" => "This addon allows you to accept payments from your customers using Apple and Google Pay.",
                "page_url" => "https://s-plugins.com/stripe-additional-payment-methods-addon/",
            );
            array_push($addons_data, $addon_2);

            $addon_3 = array(
                "name" => "Secure Downloads",
                "thumbnail" => WP_ASP_PLUGIN_URL . "/admin/assets/images/secure-downloads.png",
                "description" => "Digital products sold to your customers are secured by an encrypted download link that expires automatically.",
                "page_url" => "https://s-plugins.com/protecting-your-digital-downloads-using-the-secure-downloads-addon/",
            );
            array_push($addons_data, $addon_3);           
                        
            $addon_4 = array(
                "name" => "MailChimp Integration",
                "thumbnail" => WP_ASP_PLUGIN_URL . "/admin/assets/images/maichimp-integration.png",
                "description" => "This extension allows you to add customers to your Mailchimp list after they purchase a product.",
                "page_url" => "https://s-plugins.com/mailchimp-integration-addon-stripe-payments/",
            );
            array_push($addons_data, $addon_4);   
            
            $addon_5 = array(
                "name" => "reCAPTCHA Addon",
                "thumbnail" => WP_ASP_PLUGIN_URL . "/admin/assets/images/google-recaptcha.png",
                "description" => "When you enable this addon, the reCAPTCHA popup will appear when customers click a Stripe payment button.",
                "page_url" => "https://s-plugins.com/stripe-payments-recaptcha-addon/",
            );
            array_push($addons_data, $addon_5);
            
            $addon_6 = array(
                "name" => "Alipay Addon",
                "thumbnail" => WP_ASP_PLUGIN_URL . "/admin/assets/images/alipay-stripe-payments-addon.png",
                "description" => "When you enable this addon, it gives you the ability to accept payments via Alipay on your website",
                "page_url" => "https://s-plugins.com/alipay-addon-stripe-payments-plugin/",
            );
            array_push($addons_data, $addon_6);
	    
            $addon_7 = array(
                "name" => "WP Affiliate Integration",
                "thumbnail" => WP_ASP_PLUGIN_URL . "/admin/assets/images/wp-affiliate-integration.png",
                "description" => "The affiliate plugin will track customers that purchase items and award the affiliate that referred the customer.",
                "page_url" => "https://s-plugins.com/stripe-payments-wp-affiliate-plugin-integration/",
            );
            array_push($addons_data, $addon_7);
            
            $addon_8 = array(
                "name" => "Multi-Currency Addon",
                "thumbnail" => WP_ASP_PLUGIN_URL . "/admin/assets/images/stripe-multi-currency-addon.png",
                "description" => "The multi-currency addon allows your customers to pick a currency and pay for the item in that currency.",
                "page_url" => "https://s-plugins.com/stripe-payments-multi-currency-addon/",
            );
            array_push($addons_data, $addon_8);
            
            $addon_9 = array(
                "name" => "Custom Messages Addon",
                "thumbnail" => WP_ASP_PLUGIN_URL . "/admin/assets/images/stripe-custom-messages-addon.png",
                "description" => "This addon allows you to customize a number of common messages displayed by the Stripe Payments Plugin",
                "page_url" => "https://s-plugins.com/stripe-payments-custom-messages-addon/",
            );
            array_push($addons_data, $addon_9);
            
	    
            /*** Show the addons list ***/
            foreach ($addons_data as $addon) {
                $output .= '<div class="stripe_addon_item_canvas">';

                $output .= '<div class="stripe_addon_item_thumb">';
                $img_src = $addon['thumbnail'];
                $output .= '<img src="' . $img_src . '" alt="' . $addon['name'] . '">';
                $output .= '</div>'; //end thumbnail

                $output .='<div class="stripe_addon_item_body">';
                $output .='<div class="stripe_addon_item_name">';
                $output .= '<a href="' . $addon['page_url'] . '" target="_blank">' . $addon['name'] . '</a>';
                $output .='</div>'; //end name

                $output .='<div class="stripe_addon_item_description">';
                $output .= $addon['description'];
                $output .='</div>'; //end description

                $output .='<div class="stripe_addon_item_details_link">';
                $output .='<a href="' . $addon['page_url'] . '" class="stripe_addon_view_details" target="_blank">View Details</a>';
                $output .='</div>'; //end detils link      
                $output .='</div>'; //end body

                $output .= '</div>'; //end canvas
            }

            echo $output;
            ?>

        </div></div><!-- end of poststuff and post-body -->        
</div><!-- end of .wrap -->