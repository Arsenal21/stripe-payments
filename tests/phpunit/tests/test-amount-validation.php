<?php
/**
 * The amount check before the PaymentIntent is created (ASP_Product_Item::validate_total_amount).
 *
 * The create PaymentIntent request (asp_pp_create_pi) sends the amount in cents from the browser, and the PaymentIntent
 * is created with that amount. This check recalculates the total on the server from the product settings and the
 * buyer's choices (quantity, coupon, variations, address), and rejects the request when the amounts don't match.
 * The arguments are passed the same way handle_create_pi() passes them (the amount and quantity are strings from $_POST).
 */
class Test_Amount_Validation extends WP_UnitTestCase {

	public function set_up() {
		parent::set_up();
		ASP_Test_Factory::update_settings( array( 'coupons_enabled' => 1 ) );
	}

	private function validate( $product_id, $amount, $quantity = '', $custom_inputs = array() ) {
		$item   = new ASP_Product_Item( $product_id );
		$result = $item->validate_total_amount( $amount, $quantity, ASP_Test_Factory::custom_inputs( $custom_inputs ) );
		return array( $result, $item->get_last_error() );
	}

	public function test_the_correct_amount_is_accepted() {
		$product_id = ASP_Test_Factory::create_product(
			'10',
			array(
				'asp_product_tax'      => '10',
				'asp_product_shipping' => '5',
			)
		);

		list( $valid, $error ) = $this->validate( $product_id, '1600' );

		$this->assertTrue( $valid, $error );
	}

	public function test_a_lower_amount_is_rejected() {
		$product_id = ASP_Test_Factory::create_product(
			'10',
			array(
				'asp_product_tax'      => '10',
				'asp_product_shipping' => '5',
			)
		);

		list( $valid, $error ) = $this->validate( $product_id, '100' );

		$this->assertFalse( $valid, 'A tampered (lower) amount was accepted.' );
		$this->assertStringContainsString( 'Price validation failed', $error );
		$this->assertStringContainsString( 'Expected: 16, Submitted: 1', $error );
	}

	public function test_a_higher_amount_is_rejected() {
		list( $valid ) = $this->validate( ASP_Test_Factory::create_product( '10' ), '1001' );

		$this->assertFalse( $valid );
	}

	public function test_the_product_quantity_is_used_when_the_buyer_sends_none() {
		$product_id = ASP_Test_Factory::create_product( '10', array( 'asp_product_quantity' => '3' ) );

		list( $valid, $error ) = $this->validate( $product_id, '3000', '' );
		$this->assertTrue( $valid, $error );

		list( $valid ) = $this->validate( $product_id, '1000', '' );
		$this->assertFalse( $valid, 'The price of one item was accepted for a product sold in a quantity of 3.' );
	}

	public function test_the_amount_must_match_the_buyer_quantity() {
		$product_id = ASP_Test_Factory::create_product( '10', array( 'asp_product_custom_quantity' => '1' ) );

		list( $valid, $error ) = $this->validate( $product_id, '4000', '4' );
		$this->assertTrue( $valid, $error );

		list( $valid ) = $this->validate( $product_id, '1000', '4' );
		$this->assertFalse( $valid, 'The price of one item was accepted for 4 items.' );
	}

	public function test_a_valid_coupon_lowers_the_expected_amount() {
		$product_id = ASP_Test_Factory::create_product( '10' );
		ASP_Test_Factory::create_coupon( 'SAVE20', '20', 'perc' );

		list( $valid, $error ) = $this->validate( $product_id, '800', '', array( 'coupon_code' => 'SAVE20' ) );
		$this->assertTrue( $valid, $error );

		list( $valid ) = $this->validate( $product_id, '800' );
		$this->assertFalse( $valid, 'The discounted amount was accepted without a coupon.' );
	}

	public function test_an_invalid_coupon_is_rejected() {
		$product_id = ASP_Test_Factory::create_product( '10' );
		ASP_Test_Factory::create_coupon( 'OLD20', '20', 'perc', array( 'exp_date' => gmdate( 'Y-m-d', strtotime( '-2 days' ) ) ) );

		list( $valid, $error ) = $this->validate( $product_id, '800', '', array( 'coupon_code' => 'NOSUCHCODE' ) );
		$this->assertFalse( $valid );
		$this->assertSame( 'Coupon not found.', $error );

		list( $valid, $error ) = $this->validate( $product_id, '800', '', array( 'coupon_code' => 'OLD20' ) );
		$this->assertFalse( $valid );
		$this->assertSame( 'Coupon has expired.', $error );
	}

	/**
	 * The coupon's product rules are checked before the payment too, not only by the payment processing after the card is charged.
	 */
	public function test_a_coupon_only_for_other_products_is_rejected() {
		$product_id       = ASP_Test_Factory::create_product( '10' );
		$other_product_id = ASP_Test_Factory::create_product( '10' );
		ASP_Test_Factory::create_coupon(
			'ONLYOTHER',
			'20',
			'perc',
			array(
				'only_for_allowed_products' => 1,
				'allowed_products'          => array( (string) $other_product_id ),
			)
		);

		list( $valid, $error ) = $this->validate( $product_id, '800', '', array( 'coupon_code' => 'ONLYOTHER' ) );

		$this->assertFalse( $valid, 'The discounted amount was accepted with a coupon that is only for another product.' );
		$this->assertSame( 'Coupon is not allowed for this product.', $error );
	}

	public function test_a_coupon_is_rejected_when_coupons_are_turned_off_for_the_product() {
		$product_id = ASP_Test_Factory::create_product( '10', array( 'asp_product_coupons_setting' => '0' ) );
		ASP_Test_Factory::create_coupon( 'SAVE20', '20', 'perc' );

		list( $valid, $error ) = $this->validate( $product_id, '800', '', array( 'coupon_code' => 'SAVE20' ) );

		$this->assertFalse( $valid, 'The discounted amount was accepted for a product with coupons turned off.' );
		$this->assertSame( 'Applying coupon is not allowed for this product.', $error );
	}

	/**
	 * The type used for the amount check (the create PaymentIntent request only checks the amount of 'one_time' products).
	 */
	public function test_the_type_for_the_amount_check() {
		$product_id = ASP_Test_Factory::create_product( '10' );
		$this->assertSame( 'one_time', ( new ASP_Product_Item( $product_id ) )->get_type_for_amount_check() );

		$product_id = ASP_Test_Factory::create_product( '0', array( 'asp_product_type' => 'donation' ) );
		$this->assertSame( 'donation', ( new ASP_Product_Item( $product_id ) )->get_type_for_amount_check(), 'A saved type must be kept.' );

		//No saved type, like products saved before the product type option and the [accept_stripe_payment] products.
		$product_id = ASP_Test_Factory::create_product( '10' );
		delete_post_meta( $product_id, 'asp_product_type' );
		$this->assertSame( 'one_time', ( new ASP_Product_Item( $product_id ) )->get_type_for_amount_check(), 'A product with a price and no saved type must be checked as one_time.' );

		$product_id = ASP_Test_Factory::create_product( '0' );
		delete_post_meta( $product_id, 'asp_product_type' );
		$this->assertSame( 'donation', ( new ASP_Product_Item( $product_id ) )->get_type_for_amount_check(), 'A product without a price (the buyer enters the amount) must not be checked as one_time.' );

		$product_id = ASP_Test_Factory::create_product( '0', array( 'asp_product_hide_amount_input' => '1' ) );
		delete_post_meta( $product_id, 'asp_product_type' );
		$this->assertSame( 'one_time', ( new ASP_Product_Item( $product_id ) )->get_type_for_amount_check(), 'Variations that make up the price must be checked as one_time.' );

		$product_id = ASP_Test_Factory::create_product( '10', array( 'asp_sub_plan_id' => 'plan_test' ) );
		delete_post_meta( $product_id, 'asp_product_type' );
		$this->assertSame( '', ( new ASP_Product_Item( $product_id ) )->get_type_for_amount_check(), 'A subscription product keeps its saved type (the Subscriptions add-on checks it).' );
	}

	public function test_price_variations_are_added_to_the_price() {
		$product_id = ASP_Test_Factory::create_product(
			'10',
			array(
				'asp_variations_groups' => array( 'Size', 'Gift_Wrap' ),
				'asp_variations_names'  => array( array( 'Small', 'Large' ), array( 'No', 'Yes' ) ),
				'asp_variations_prices' => array( array( '0', '5' ), array( '0', '2.5' ) ),
			)
		);

		//The popup sends "<group name>_<option index>"; a group name can contain "_".
		list( $valid, $error ) = $this->validate( $product_id, '1750', '', array( 'price_variation' => 'Size_1,Gift_Wrap_1' ) );
		$this->assertTrue( $valid, $error );

		list( $valid, $error ) = $this->validate( $product_id, '1000', '', array( 'price_variation' => 'Size_0,Gift_Wrap_0' ) );
		$this->assertTrue( $valid, $error );

		list( $valid ) = $this->validate( $product_id, '1000', '', array( 'price_variation' => 'Size_1,Gift_Wrap_1' ) );
		$this->assertFalse( $valid, 'The base price was accepted for the more expensive options.' );
	}

	public function test_price_variations_can_make_up_the_whole_price() {
		$product_id = ASP_Test_Factory::create_product(
			'0',
			array(
				//"Use variations only to construct the final price" option.
				'asp_product_hide_amount_input' => '1',
				'asp_variations_groups'         => array( 'Plan' ),
				'asp_variations_names'          => array( array( 'Basic', 'Pro' ) ),
				'asp_variations_prices'         => array( array( '8', '12' ) ),
			)
		);

		list( $valid, $error ) = $this->validate( $product_id, '1200', '', array( 'price_variation' => 'Plan_1' ) );
		$this->assertTrue( $valid, $error );

		list( $valid ) = $this->validate( $product_id, '800', '', array( 'price_variation' => 'Plan_1' ) );
		$this->assertFalse( $valid );
	}

	public function test_tax_by_billing_country() {
		$product_id = ASP_Test_Factory::create_product(
			'10',
			array(
				'asp_product_collect_billing_addr' => '1',
				'asp_product_tax_variations_type'  => 'b',
				'asp_product_tax_variations'       => array(
					array( 'type' => '0', 'loc' => 'US', 'amount' => '10' ),
				),
			)
		);
		$us_buyer = array( 'billing_details' => ASP_Test_Factory::address_details( 'US', 'TX', 'Austin' ) );
		$de_buyer = array( 'billing_details' => ASP_Test_Factory::address_details( 'DE', '', 'Berlin' ) );

		list( $valid, $error ) = $this->validate( $product_id, '1100', '', $us_buyer );
		$this->assertTrue( $valid, $error );

		list( $valid ) = $this->validate( $product_id, '1000', '', $us_buyer );
		$this->assertFalse( $valid, 'The amount without the US tax was accepted for a US buyer.' );

		list( $valid, $error ) = $this->validate( $product_id, '1000', '', $de_buyer );
		$this->assertTrue( $valid, $error );
	}

	public function test_shipping_by_country() {
		$product_id = ASP_Test_Factory::create_product(
			'10',
			array(
				'asp_product_shipping'              => '3',
				'asp_product_collect_shipping_addr' => '1',
				'asp_product_shipping_variations'   => array(
					array( 'type' => '0', 'loc' => 'US', 'amount' => '7' ),
				),
			)
		);

		//$10 + $3 base shipping + $7 for the US.
		list( $valid, $error ) = $this->validate( $product_id, '2000', '', array( 'shipping_details' => ASP_Test_Factory::address_details( 'US' ) ) );
		$this->assertTrue( $valid, $error );

		list( $valid, $error ) = $this->validate( $product_id, '1300', '', array( 'shipping_details' => ASP_Test_Factory::address_details( 'DE' ) ) );
		$this->assertTrue( $valid, $error );

		list( $valid ) = $this->validate( $product_id, '1300', '', array( 'shipping_details' => ASP_Test_Factory::address_details( 'US' ) ) );
		$this->assertFalse( $valid, 'The amount without the US shipping was accepted.' );
	}

	public function test_the_surcharge_is_added() {
		$product_id = ASP_Test_Factory::create_product(
			'10',
			array(
				'asp_surcharge_amount' => '2.5',
				'asp_surcharge_type'   => 'flat',
			)
		);

		list( $valid, $error ) = $this->validate( $product_id, '1250' );
		$this->assertTrue( $valid, $error );

		list( $valid ) = $this->validate( $product_id, '1000' );
		$this->assertFalse( $valid, 'The amount without the surcharge was accepted.' );
	}

	public function test_zero_decimal_currency_amount() {
		$product_id = ASP_Test_Factory::create_product( '1000', array( 'asp_product_currency' => 'JPY' ) );

		list( $valid, $error ) = $this->validate( $product_id, '1000' );
		$this->assertTrue( $valid, $error );

		list( $valid ) = $this->validate( $product_id, '100000' );
		$this->assertFalse( $valid );
	}
}
