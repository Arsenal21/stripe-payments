<?php
/**
 * The product total calculation (ASP_Product_Item): price, quantity, tax, shipping, surcharge, coupons and zero-decimal currencies.
 *
 * The total in cents is what the buyer is charged: the create PaymentIntent request is checked against it
 * (see test-amount-validation.php) and the payment processing compares the paid amount with it.
 * The expected values are exact: the plugin compares amounts in cents with "!=", so a rounding difference fails a real checkout.
 */
class Test_Price_Calculation extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		ASP_Test_Factory::update_settings( array( 'coupons_enabled' => 1 ) );
	}

	public function test_price_only() {
		$item = new ASP_Product_Item( ASP_Test_Factory::create_product( '10' ) );

		$this->assertEquals( 10, $item->get_total() );
		$this->assertEquals( 1000, $item->get_total( true ) );
		$this->assertEquals( 1000, $item->get_price( true ) );
	}

	public function test_quantity_tax_and_shipping() {
		$product_id = ASP_Test_Factory::create_product(
			'10',
			array(
				'asp_product_quantity' => '3',
				'asp_product_tax'      => '10',
				'asp_product_shipping' => '5',
			)
		);
		$item = new ASP_Product_Item( $product_id );

		//3 x $10 = $30, tax 10% of $30 = $3, shipping $5 once per order (not per item).
		$this->assertEquals( 3, $item->get_quantity() );
		$this->assertEquals( 3, $item->get_tax_amount() );
		$this->assertEquals( 500, $item->get_shipping( true ) );
		$this->assertEquals( 3800, $item->get_total( true ) );
	}

	public function test_the_buyer_quantity_replaces_the_product_quantity() {
		$item = new ASP_Product_Item( ASP_Test_Factory::create_product( '10', array( 'asp_product_quantity' => '3' ) ) );
		$item->set_quantity( 4 );

		$this->assertEquals( 4000, $item->get_total( true ) );
	}

	public function test_tax_is_rounded_to_the_cent() {
		$item = new ASP_Product_Item( ASP_Test_Factory::create_product( '19.99', array( 'asp_product_tax' => '7.25' ) ) );
		$item->set_quantity( 3 );

		//3 x $19.99 = $59.97, tax 7.25% = $4.347825, rounded to $4.35.
		$this->assertEquals( 4.35, $item->get_tax_amount() );
		$this->assertEquals( 435, $item->get_tax_amount( true ) );
		$this->assertEquals( 6432, $item->get_total( true ) );
	}

	public function test_prices_that_are_not_exact_in_floating_point_are_converted_to_whole_cents() {
		$item = new ASP_Product_Item( ASP_Test_Factory::create_product( '0.29' ) );
		$this->assertEquals( 29, $item->get_total( true ) );

		$item = new ASP_Product_Item( ASP_Test_Factory::create_product( '1.15', array( 'asp_product_quantity' => '3' ) ) );
		$this->assertEquals( 345, $item->get_total( true ) );
	}

	public function test_zero_decimal_currency_is_not_multiplied_by_100() {
		$product_id = ASP_Test_Factory::create_product(
			'1000',
			array(
				'asp_product_currency' => 'JPY',
				'asp_product_quantity' => '2',
				'asp_product_tax'      => '10',
				'asp_product_shipping' => '500',
			)
		);
		$item = new ASP_Product_Item( $product_id );

		//2 x 1000 JPY = 2000, tax 10% = 200, shipping 500. Stripe takes JPY amounts without cents.
		$this->assertEquals( 1000, $item->get_price( true ) );
		$this->assertEquals( 500, $item->get_shipping( true ) );
		$this->assertEquals( 2700, $item->get_total( true ) );
	}

	public function test_zero_decimal_currencies() {
		foreach ( array( 'JPY', 'KRW', 'VND', 'MGA', 'jpy' ) as $currency ) {
			$this->assertTrue( AcceptStripePayments::is_zero_cents( $currency ), "{$currency} is a zero-decimal currency." );
		}
		foreach ( array( 'USD', 'EUR', 'GBP' ) as $currency ) {
			$this->assertFalse( AcceptStripePayments::is_zero_cents( $currency ), "{$currency} is not a zero-decimal currency." );
		}
	}

	public function test_the_default_currency_is_used_when_the_product_has_none() {
		ASP_Test_Factory::update_settings( array( 'currency_code' => 'EUR' ) );
		$item = new ASP_Product_Item( ASP_Test_Factory::create_product( '10' ) );

		$this->assertSame( 'EUR', $item->get_currency() );
	}

	public function test_flat_surcharge() {
		$product_id = ASP_Test_Factory::create_product(
			'10',
			array(
				'asp_surcharge_amount' => '2.5',
				'asp_surcharge_type'   => 'flat',
				'asp_surcharge_label'  => 'Card fee',
			)
		);
		$item = new ASP_Product_Item( $product_id );

		$this->assertEquals( 250, $item->calculate_total_surcharge( true ) );
		$this->assertSame( 'Card fee', $item->get_surcharge_label() );
	}

	public function test_percent_surcharge_is_calculated_from_the_total() {
		$product_id = ASP_Test_Factory::create_product(
			'100',
			array(
				'asp_product_tax'      => '10',
				'asp_surcharge_amount' => '3',
				'asp_surcharge_type'   => 'perc',
			)
		);
		$item = new ASP_Product_Item( $product_id );

		//3% of the total with tax ($110).
		$this->assertEquals( 330, $item->calculate_total_surcharge( true ) );
		$this->assertSame( 'Surcharge', $item->get_surcharge_label(), 'The default surcharge label is wrong.' );
	}

	public function test_regional_shipping_matches_country_state_and_city() {
		$product_id = ASP_Test_Factory::create_product(
			'10',
			array(
				'asp_product_shipping_variations' => array(
					array( 'type' => '0', 'loc' => 'US', 'amount' => '5' ),
					array( 'type' => '1', 'loc' => 'CA', 'amount' => '2' ),
					array( 'type' => '2', 'loc' => 'Los Angeles', 'amount' => '1.5' ),
					array( 'type' => '0', 'loc' => 'DE', 'amount' => '20' ),
				),
			)
		);
		$item = new ASP_Product_Item( $product_id );

		//The location is matched without case: 'us' matches 'US'.
		$this->assertEquals(
			850,
			$item->calculate_regional_shipping_amount( array( 'country' => 'us', 'state' => 'CA', 'city' => 'Los Angeles' ), true )
		);
		$this->assertEquals( 0, $item->calculate_regional_shipping_amount( array( 'country' => 'FR', 'state' => '', 'city' => '' ), true ) );
	}

	public function test_tax_by_region() {
		$tax_variations = array(
			array( 'type' => '0', 'loc' => 'US', 'amount' => '5' ),
			array( 'type' => '1', 'loc' => 'CA', 'amount' => '2.5' ),
			array( 'type' => '2', 'loc' => 'Paris', 'amount' => '20' ),
		);

		$this->assertEquals( 7.5, ASP_Utils::get_tax_variations_tax( $tax_variations, 'US', 'CA', 'Los Angeles' ) );
		$this->assertEquals( 5, ASP_Utils::get_tax_variations_tax( $tax_variations, 'US', 'TX', 'Austin' ) );
		$this->assertFalse( ASP_Utils::get_tax_variations_tax( $tax_variations, 'DE', '', 'Berlin' ), 'No region matches, so the product tax must be used.' );
		//A state called "Paris" must not match the city rule.
		$this->assertFalse( ASP_Utils::get_tax_variations_tax( $tax_variations, 'FR', 'Paris', '' ) );
	}

	public function test_percent_coupon_with_quantity_and_tax() {
		$product_id = ASP_Test_Factory::create_product( '10', array( 'asp_product_tax' => '10' ) );
		ASP_Test_Factory::create_coupon( 'SAVE20', '20', 'perc' );
		$item = new ASP_Product_Item( $product_id );
		$item->set_quantity( 2 );

		$this->assertTrue( $item->check_coupon( 'SAVE20' ), $item->get_last_error() );
		//2 x $10 = $20, 20% off = $16, tax 10% of the discounted $16 = $1.60.
		$this->assertEquals( 1.6, $item->get_tax_amount() );
		$this->assertEquals( 1760, $item->get_total( true ) );
	}

	public function test_fixed_coupon_is_taken_off_each_item() {
		$product_id = ASP_Test_Factory::create_product( '10' );
		ASP_Test_Factory::create_coupon( 'THREEOFF', '3', 'fixed' );
		$item = new ASP_Product_Item( $product_id );
		$item->set_quantity( 2 );

		$this->assertTrue( $item->check_coupon( 'THREEOFF' ), $item->get_last_error() );
		$this->assertEquals( 1400, $item->get_total( true ) );
	}

	public function test_fixed_coupon_per_order_is_taken_off_once() {
		$product_id = ASP_Test_Factory::create_product( '10' );
		ASP_Test_Factory::create_coupon( 'THREEOFFORDER', '3', 'fixed', array( 'per_order' => 1 ) );
		$item = new ASP_Product_Item( $product_id );
		$item->set_quantity( 2 );

		$this->assertTrue( $item->check_coupon( 'THREEOFFORDER' ), $item->get_last_error() );
		$this->assertEquals( 1700, $item->get_total( true ) );
	}

	public function test_a_coupon_larger_than_the_price_never_makes_the_total_negative() {
		$product_id = ASP_Test_Factory::create_product( '10', array( 'asp_product_tax' => '10' ) );
		ASP_Test_Factory::create_coupon( 'FIFTEENOFF', '15', 'fixed' );
		$item = new ASP_Product_Item( $product_id );

		$this->assertTrue( $item->check_coupon( 'FIFTEENOFF' ), $item->get_last_error() );
		$this->assertEquals( 0, $item->get_total( true ) );
	}

	public function test_coupons_with_a_zero_decimal_currency() {
		$product_id = ASP_Test_Factory::create_product( '999', array( 'asp_product_currency' => 'JPY' ) );
		ASP_Test_Factory::create_coupon( 'JPYPERC', '15', 'perc' );
		ASP_Test_Factory::create_coupon( 'JPYFIXED', '100', 'fixed' );

		$item = new ASP_Product_Item( $product_id );
		$this->assertTrue( $item->check_coupon( 'JPYPERC' ), $item->get_last_error() );
		//15% of 999 = 149.85, rounded to 150 (no decimals in JPY).
		$this->assertEquals( 849, $item->get_total( true ) );

		$item = new ASP_Product_Item( $product_id );
		$this->assertTrue( $item->check_coupon( 'JPYFIXED' ), $item->get_last_error() );
		$this->assertEquals( 899, $item->get_total( true ), 'A fixed JPY discount must not be multiplied by 100.' );
	}

	public function test_formatted_price_uses_the_currency_settings() {
		$this->assertSame( '$1,234.50', AcceptStripePayments::formatted_price( 1234.5, 'USD' ) );
		$this->assertSame( '$12.34', AcceptStripePayments::formatted_price( 1234, 'USD', true ), 'A price in cents must be divided by 100.' );
		$this->assertSame( '1,234.50', AcceptStripePayments::formatted_price( 1234.5, false ), 'No currency symbol when the currency is false.' );

		ASP_Test_Factory::update_settings(
			array(
				'price_currency_pos' => 'right-with-space',
				'price_decimal_sep'  => ',',
				'price_thousand_sep' => '.',
			)
		);
		$this->assertSame( '1.234,50 €', AcceptStripePayments::formatted_price( 1234.5, 'EUR' ) );
	}
}
