<?php
/**
 * Represents the view for the addons listing page.
 */
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'You do not have permission to access this menu page.' );
}

$output = '';
?>

<div class="wrap">
	<h1><?php _e( 'Add-ons', 'stripe-payments' ); ?></h1>

	<div id="poststuff">
	<div id="post-body">

		<?php
		$addons_data = array();

		$addon_1 = array(
			'name'         => __( 'Subscription Payments', 'stripe-payments' ),
			'thumbnail'    => WP_ASP_PLUGIN_URL . '/admin/assets/images/subscription-payments.png',
			'description'  => __( 'This addon allows you to configure and sell subscription and recurring payments to your customers.', 'stripe-payments' ),
			'page_url'     => 'https://s-plugins.com/stripe-subscription-payments-addon/',
			'settings_url' => sprintf( 'edit.php?post_type=%s&page=stripe-payments-settings#sub', ASPMain::$products_slug ),
			'installed'    => class_exists( 'ASPSUB_main' ) ? true : false,
		);
		array_push( $addons_data, $addon_1 );

		$addon_2 = array(
			'name'         => __( 'Apple and Google Pay', 'stripe-payments' ),
			'thumbnail'    => WP_ASP_PLUGIN_URL . '/admin/assets/images/apple-android-pay.png',
			'description'  => __( 'This addon allows you to accept payments from your customers using Apple and Google Pay.', 'stripe-payments' ),
			'page_url'     => 'https://s-plugins.com/stripe-additional-payment-methods-addon/',
			'settings_url' => sprintf( 'edit.php?post_type=%s&page=stripe-payments-settings#apm', ASPMain::$products_slug ),
			'installed'    => class_exists( 'ASPAPM_main' ) ? true : false,
		);
		array_push( $addons_data, $addon_2 );

		$addon_3 = array(
			'name'         => __( 'Secure Downloads', 'stripe-payments' ),
			'thumbnail'    => WP_ASP_PLUGIN_URL . '/admin/assets/images/secure-downloads.png',
			'description'  => __( 'Digital products sold to your customers are secured by an encrypted download link that expires automatically.', 'stripe-payments' ),
			'page_url'     => 'https://s-plugins.com/protecting-your-digital-downloads-using-the-secure-downloads-addon/',
			'settings_url' => sprintf( 'edit.php?post_type=%s&page=stripe-payments-settings#securedownloads', ASPMain::$products_slug ),
			'installed'    => class_exists( 'ASPSD_main' ) ? true : false,
		);
		array_push( $addons_data, $addon_3 );

		$addon_4 = array(
			'name'         => __( 'MailChimp Integration', 'stripe-payments' ),
			'thumbnail'    => WP_ASP_PLUGIN_URL . '/admin/assets/images/maichimp-integration.png',
			'description'  => __( 'This extension allows you to add customers to your Mailchimp list after they purchase a product.', 'stripe-payments' ),
			'page_url'     => 'https://s-plugins.com/mailchimp-integration-addon-stripe-payments/',
			'settings_url' => 'edit.php?post_type=' . ASPMain::$products_slug . '&page=stripe-payments-settings#mailchimp',
			'installed'    => class_exists( 'ASPMCI_main' ) ? true : false,
		);
		array_push( $addons_data, $addon_4 );

		$addon_6 = array(
			'name'         => __( 'Alipay Addon', 'stripe-payments' ),
			'thumbnail'    => WP_ASP_PLUGIN_URL . '/admin/assets/images/alipay-stripe-payments-addon.png',
			'description'  => __( 'When you enable this addon, it gives you the ability to accept payments via Alipay on your website', 'stripe-payments' ),
			'page_url'     => 'https://s-plugins.com/alipay-addon-stripe-payments-plugin/',
			'settings_url' => 'edit.php?post_type=' . ASPMain::$products_slug . '&page=stripe-payments-settings#alipay',
			'installed'    => class_exists( 'ASPALI_main' ) ? true : false,
		);
		array_push( $addons_data, $addon_6 );

		$addon_7 = array(
			'name'         => __( 'WP Affiliate Integration', 'stripe-payments' ),
			'thumbnail'    => WP_ASP_PLUGIN_URL . '/admin/assets/images/wp-affiliate-integration.png',
			'description'  => __( 'The affiliate plugin will track customers that purchase items and award the affiliate that referred the customer.', 'stripe-payments' ),
			'page_url'     => 'https://s-plugins.com/stripe-payments-wp-affiliate-plugin-integration/',
			'settings_url' => '',
			'installed'    => false,
		);
		array_push( $addons_data, $addon_7 );

		$addon_8 = array(
			'name'         => __( 'Multi-Currency Addon', 'stripe-payments' ),
			'thumbnail'    => WP_ASP_PLUGIN_URL . '/admin/assets/images/stripe-multi-currency-addon.png',
			'description'  => __( 'The multi-currency addon allows your customers to pick a currency and pay for the item in that currency.', 'stripe-payments' ),
			'page_url'     => 'https://s-plugins.com/stripe-payments-multi-currency-addon/',
			'settings_url' => 'edit.php?post_type=' . ASPMain::$products_slug . '&page=stripe-payments-settings#multicurr',
			'installed'    => class_exists( 'ASPMULTICURR_main' ) ? true : false,
		);
		array_push( $addons_data, $addon_8 );

		$addon_9 = array(
			'name'         => __( 'Custom Messages Addon', 'stripe-payments' ),
			'thumbnail'    => WP_ASP_PLUGIN_URL . '/admin/assets/images/stripe-custom-messages-addon.png',
			'description'  => __( 'This addon allows you to customize a number of common messages displayed by the Stripe Payments Plugin.', 'stripe-payments' ),
			'page_url'     => 'https://s-plugins.com/stripe-payments-custom-messages-addon/',
			'settings_url' => 'edit.php?post_type=' . ASPMain::$products_slug . '&page=stripe-payments-settings#custmsg',
			'installed'    => class_exists( 'ASPCUSTMSG_main' ) ? true : false,
		);
		array_push( $addons_data, $addon_9 );

		$addon_10 = array(
			'name'         => __( 'AWeber Integration Addon', 'stripe-payments' ),
			'thumbnail'    => WP_ASP_PLUGIN_URL . '/admin/assets/images/aweber-integration-addon.png',
			'description'  => __( 'This addon allows you to add customers to your AWeber list after they purchase a product.', 'stripe-payments' ),
			'page_url'     => 'https://s-plugins.com/aweber-integration-addon-for-stripe-payments/',
			'settings_url' => 'edit.php?post_type=' . ASPMain::$products_slug . '&page=stripe-payments-settings#aweber',
			'installed'    => class_exists( 'ASPAWEBER_main' ) ? true : false,
		);
		array_push( $addons_data, $addon_10 );

		$addon_11 = array(
			'name'         => __( 'ConvertKit Integration Addon', 'stripe-payments' ),
			'thumbnail'    => WP_ASP_PLUGIN_URL . '/admin/assets/images/convertkit-integration.png',
			'description'  => __( 'This addon allows you to add customers to your ConvertKit list after they purchase a product.', 'stripe-payments' ),
			'page_url'     => 'https://s-plugins.com/stripe-convertkit-integration-addon/',
			'settings_url' => 'edit.php?post_type=' . ASPMain::$products_slug . '&page=stripe-payments-settings#convertkit',
			'installed'    => class_exists( 'ASPCK_main' ) ? true : false,
		);
		array_push( $addons_data, $addon_11 );

		$addon_12 = array(
			'name'         => __( 'Post Payment Actions Addon', 'stripe-payments' ),
			'thumbnail'    => WP_ASP_PLUGIN_URL . '/admin/assets/images/post-payment-actions.png',
			'description'  => __( 'Post Payment Actions are triggered after a successful Stripe Payments transaction has been completed.', 'stripe-payments' ),
			'page_url'     => 'https://s-plugins.com/post-payment-actions-addon-for-stripe-payments/',
			'settings_url' => 'edit.php?post_type=' . ASPMain::$products_slug . '&page=stripe-payments-settings#ppa',
			'installed'    => class_exists( 'ASPPPA_main' ) ? true : false,
		);
		array_push( $addons_data, $addon_12 );

		$addon_13 = array(
			'name'         => __( 'SOFORT Payments Addon', 'stripe-payments' ),
			'thumbnail'    => WP_ASP_PLUGIN_URL . '/admin/assets/images/sofort-payments.png',
			'description'  => __( 'The SOFORT Addon can be used along side the Stripe Payments Plugin to allow your customers to pay using SOFORT.', 'stripe-payments' ),
			'page_url'     => 'https://s-plugins.com/stripe-sofort-payment-addon/',
			'settings_url' => 'edit.php?post_type=' . ASPMain::$products_slug . '&page=stripe-payments-settings#sofort',
			'installed'    => class_exists( 'ASPSOFORT_main' ) ? true : false,
		);
		array_push( $addons_data, $addon_13 );

		$addon_14 = array(
			'name'         => __( 'iDEAL Payments Addon', 'stripe-payments' ),
			'thumbnail'    => WP_ASP_PLUGIN_URL . '/admin/assets/images/iDEAL-payment-gateway-addon.png',
			'description'  => __( 'The iDEAL Addon can be used along side the Stripe Payments Plugin to allow your customers to pay using iDEAL gateway.', 'stripe-payments' ),
			'page_url'     => 'https://s-plugins.com/stripe-ideal-payment-addon/',
			'settings_url' => sprintf( 'edit.php?post_type=%s&page=stripe-payments-settings#ideal', ASPMain::$products_slug ),
			'installed'    => class_exists( 'ASPIDEAL_main' ) ? true : false,
		);
		array_push( $addons_data, $addon_14 );

		$addon_15 = array(
			'name'         => __( 'Additional Custom Fields', 'stripe-payments' ),
			'thumbnail'    => WP_ASP_PLUGIN_URL . '/admin/assets/images/additional-custom-fields.png',
			'description'  => __( 'The Additional Custom Fields Addon allows you to collect information from your customers before they proceed to checkout.', 'stripe-payments' ),
			'page_url'     => 'https://s-plugins.com/stripe-payments-additional-custom-fields-addon/',
			'settings_url' => 'edit.php?post_type=' . ASPMain::$products_slug . '&page=stripe-payments-settings#acf',
			'installed'    => class_exists( 'ASPACF_main' ) ? true : false,
		);
		array_push( $addons_data, $addon_15 );

		$addon_16 = array(
			'name'         => __( 'MailerLite Addon', 'stripe-payments' ),
			'thumbnail'    => WP_ASP_PLUGIN_URL . '/admin/assets/images/mailer-lite-integration.png',
			'description'  => __( 'This addon allows you to add customers to your MailerLite group after they purchase a product.', 'stripe-payments' ),
			'page_url'     => 'https://s-plugins.com/stripe-payments-mailerlite-integration-addon/',
			'settings_url' => 'edit.php?post_type=' . ASPMain::$products_slug . '&page=stripe-payments-settings#mailerlite',
			'installed'    => class_exists( 'ASP_Mailerlite_Main' ) ? true : false,
		);
		array_push( $addons_data, $addon_16 );

		$addon_17 = array(
			'name'         => __( 'Google Analytics Tracking', 'stripe-payments' ),
			'thumbnail'    => WP_ASP_PLUGIN_URL . '/admin/assets/images/google-analytics-ecommerce-tracking.png',
			'description'  => __( 'This addon allows you to do Google analytics eCommerce tracking for our Stripe plugin transactions.', 'stripe-payments' ),
			'page_url'     => 'https://s-plugins.com/stripe-payments-google-analytics-ecommerce-tracking-addon/',
			'settings_url' => 'edit.php?post_type=' . ASPMain::$products_slug . '&page=stripe-payments-settings#gaet',
			'installed'    => class_exists( 'ASPGAET_main' ) ? true : false,
		);
		array_push( $addons_data, $addon_17 );

		/* Show the addons list */
		foreach ( $addons_data as $addon ) {
			$output .= '<div class="stripe_addon_item_canvas">';

			$output .= '<div class="stripe_addon_item_thumb">';
			if ( $addon['installed'] ) {
				$output .= '<div class="stripe-addon-item-installed-mark"><span class="dashicons dashicons-yes" title="' . __( 'Add-on installed', 'stripe-payments' ) . '"></span></div>';
			}

			$img_src = $addon['thumbnail'];
			$output .= '<img width="256" height="190" src="' . $img_src . '" alt="' . $addon['name'] . '">';
			$output .= '</div>'; //end thumbnail

			$output .= '<div class="stripe_addon_item_body">';
			$output .= '<div class="stripe_addon_item_name">';
			$output .= '<a href="' . $addon['page_url'] . '" target="_blank">' . $addon['name'] . '</a>';
			$output .= '</div>'; //end name

			$output .= '<div class="stripe_addon_item_description">';
			$output .= $addon['description'];
			$output .= '</div>'; //end description

			$output .= '<div class="stripe_addon_item_details_link">';
			$output .= '<a href="' . $addon['page_url'] . '" class="stripe_addon_view_details" target="_blank">' . __( 'View Details', 'stripe-payments' ) . '</a>';
			if ( $addon['installed'] ) {
				$output .= ' <a href="' . $addon['settings_url'] . '" class="stripe_addon_view_details" target="_blank">' . __( 'Settings', 'stripe-payments' ) . '</a>';
			}
			$output .= '</div>'; //end detils link
			$output .= '</div>'; //end body

			$output .= '</div>'; //end canvas
		}

		echo $output;
		?>

		</div>
	</div><!-- end of poststuff and post-body -->
</div><!-- end of .wrap -->
