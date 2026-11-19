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
	'coupon' => ASP_Test_Factory::create_coupon( 'E2E20', '20', 'perc' ),
);

echo "\n" . wp_json_encode( $ids ) . "\n";
