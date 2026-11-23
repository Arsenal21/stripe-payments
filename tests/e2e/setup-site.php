<?php
/**
 * Endpoint tests: creates the test data on the test site. Run with: wp eval-file tests/e2e/setup-site.php
 * Prints a JSON line with the IDs the tests need (the last line of the output).
 */
require_once dirname( __DIR__ ) . '/phpunit/includes/class-asp-test-factory.php';

//Plugin settings: test mode with test API keys (the fake Stripe API doesn't check them, but the SDK needs a key),
//debug logging (the log helps when a test fails), the emails and coupons.
ASP_Test_Factory::update_settings(
	array(
		'is_live'                   => 0,
		'api_publishable_key_test'  => 'pk_test_e2e',
		'api_secret_key_test'       => 'sk_test_e2e',
		'debug_log_enable'          => 1,
		'currency_code'             => 'USD',
		'send_emails_to_buyer'      => 1,
		'send_emails_to_seller'     => 1,
		'from_email_address'        => 'E2E Shop <shop@example.com>',
		'seller_notification_email' => 'seller@example.com',
		'buyer_email_subject'       => 'Thank you for the purchase',
		'buyer_email_body'          => "Thank you for your purchase.\n\n{product_details}",
		'seller_email_subject'      => 'Notification of product sale',
		'seller_email_body'         => "Sale to {payer_email}\n\n{product_details}",
		'coupons_enabled'           => 1,
	)
);

$ids = array(
	//$10 + 10% tax, the buyer can change the quantity.
	'ebook'  => ASP_Test_Factory::create_product(
		'10',
		array(
			'asp_product_tax'             => '10',
			'asp_product_custom_quantity' => '1',
		),
		'E2E Ebook'
	),
	'cheap'  => ASP_Test_Factory::create_product( '1', array(), 'E2E Cheap Product' ),
	'jpy'    => ASP_Test_Factory::create_product( '1000', array( 'asp_product_currency' => 'JPY' ), 'E2E JPY Product' ),
	//Stock control with 2 items (the runner sets the stock back to 2 before each round of tests).
	'stock'  => ASP_Test_Factory::create_product(
		'5',
		array(
			'asp_product_enable_stock'    => '1',
			'asp_product_stock_items'     => '2',
			'asp_product_custom_quantity' => '1',
		),
		'E2E Stock Product'
	),
	//The payment popup of this product is never loaded (no page load signature).
	'no_popup' => ASP_Test_Factory::create_product( '10', array(), 'E2E Product Without Popup' ),
	//A product with no saved product type (like products saved before the product type option, and the products the
	//[accept_stripe_payment] shortcode creates).
	'no_type'  => ASP_Test_Factory::create_product( '10', array(), 'E2E Product Without Type' ),
	//"Authorize only": the payment is captured or canceled later from the orders page.
	'auth'     => ASP_Test_Factory::create_product( '10', array( 'asp_product_authorize_only' => '1' ), 'E2E Authorize Only Product' ),
	//A donation: the buyer enters the amount, at least $5.
	'donation' => ASP_Test_Factory::create_product(
		'0',
		array(
			'asp_product_type'       => 'donation',
			'asp_product_min_amount' => '5',
		),
		'E2E Donation'
	),
	//10% tax for US billing addresses.
	'tax_region'  => ASP_Test_Factory::create_product(
		'10',
		array(
			'asp_product_collect_billing_addr' => '1',
			'asp_product_tax_variations_type'  => 'b',
			'asp_product_tax_variations'       => array(
				array( 'type' => '0', 'loc' => 'US', 'amount' => '10' ),
			),
		),
		'E2E Tax By Country'
	),
	//$3 shipping, plus $7 to the US.
	'ship_region' => ASP_Test_Factory::create_product(
		'10',
		array(
			'asp_product_shipping'              => '3',
			'asp_product_collect_billing_addr'  => '1',
			'asp_product_collect_shipping_addr' => '1',
			'asp_product_shipping_variations'   => array(
				array( 'type' => '0', 'loc' => 'US', 'amount' => '7' ),
			),
		),
		'E2E Shipping By Country'
	),
	//Coupons turned off for this product (the product setting; coupons are on in the settings).
	'no_coupons'  => ASP_Test_Factory::create_product( '10', array( 'asp_product_coupons_setting' => '0' ), 'E2E Product Without Coupons' ),
	//Sold in a quantity of 2 that the buyer can't change ($50 each), and a flat $30 coupon (taken off each item).
	'qty2'        => ASP_Test_Factory::create_product( '50', array( 'asp_product_quantity' => '2' ), 'E2E Pack Of Two' ),
	'flat_coupon' => ASP_Test_Factory::create_coupon( 'E2EFLAT30', '30', 'fixed' ),
	'coupon'      => ASP_Test_Factory::create_coupon( 'E2E20', '20', 'perc' ),
	'free_coupon' => ASP_Test_Factory::create_coupon( 'E2EFREE', '100', 'perc' ),
);
delete_post_meta( $ids['no_type'], 'asp_product_type' );

//A coupon only for the $1 product.
$ids['cheap_coupon'] = ASP_Test_Factory::create_coupon(
	'E2ECHEAP',
	'20',
	'perc',
	array(
		'only_for_allowed_products' => 1,
		'allowed_products'          => array( (string) $ids['cheap'] ),
	)
);

//Pages with the [accept_stripe_payment] shortcode. The shortcode creates its product (a "temp" product) when the page is shown.
foreach (
	array(
		'shortcode_priced'   => '[accept_stripe_payment name="E2E Shortcode Product" price="10"]',
		'shortcode_donation' => '[accept_stripe_payment name="E2E Shortcode Donation" price="0"]',
	) as $key => $shortcode
) {
	$ids[ $key ] = wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_title'   => $key,
			'post_content' => $shortcode,
		)
	);
}

echo "\n" . wp_json_encode( $ids ) . "\n";
