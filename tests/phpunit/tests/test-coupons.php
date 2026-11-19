<?php
/**
 * When a coupon can be used (ASP_Product_Item::check_coupon, used by the coupon check in the payment popup and by the payment processing).
 */
class Test_Coupons extends WP_UnitTestCase {

	private $product_id;

	public function set_up() {
		parent::set_up();
		ASP_Test_Factory::update_settings( array( 'coupons_enabled' => 1 ) );
		$this->product_id = ASP_Test_Factory::create_product( '10' );
	}

	/**
	 * @return array Whether the coupon is valid, and the error message.
	 */
	private function check( $code, $product_id = null ) {
		$item  = new ASP_Product_Item( null === $product_id ? $this->product_id : $product_id );
		$valid = $item->check_coupon( $code );
		return array( $valid, $item->get_last_error(), $item->get_coupon() );
	}

	public function test_a_valid_coupon_is_accepted() {
		$coupon_id = ASP_Test_Factory::create_coupon( 'SAVE20', '20', 'perc' );

		list( $valid, $error, $coupon ) = $this->check( 'SAVE20' );

		$this->assertTrue( $valid, $error );
		$this->assertSame( $coupon_id, $coupon['id'] );
		$this->assertEquals( 20, $coupon['discount'] );
		$this->assertSame( 'perc', $coupon['discount_type'] );
	}

	public function test_spaces_around_the_code_are_ignored() {
		ASP_Test_Factory::create_coupon( 'SAVE20', '20', 'perc' );

		list( $valid, $error ) = $this->check( '  SAVE20 ' );

		$this->assertTrue( $valid, $error );
	}

	public function test_an_unknown_code_is_rejected() {
		ASP_Test_Factory::create_coupon( 'SAVE20', '20', 'perc' );

		$this->assert_rejected( 'SAVE30', 'Coupon not found.' );
		$this->assert_rejected( '', 'No coupon code provided' );
	}

	public function test_an_inactive_coupon_is_rejected() {
		ASP_Test_Factory::create_coupon( 'OFF', '20', 'perc', array( 'active' => 0 ) );

		$this->assert_rejected( 'OFF', 'Coupon is not active.' );
	}

	public function test_a_coupon_before_its_start_date_is_rejected() {
		ASP_Test_Factory::create_coupon( 'LATER', '20', 'perc', array( 'start_date' => gmdate( 'Y-m-d', strtotime( '+2 days' ) ) ) );

		$this->assert_rejected( 'LATER', 'Coupon is not available yet.' );
	}

	public function test_an_expired_coupon_is_rejected() {
		ASP_Test_Factory::create_coupon( 'OLD', '20', 'perc', array( 'exp_date' => gmdate( 'Y-m-d', strtotime( '-2 days' ) ) ) );

		$this->assert_rejected( 'OLD', 'Coupon has expired.' );
	}

	public function test_a_coupon_with_a_future_expiry_date_is_accepted() {
		ASP_Test_Factory::create_coupon( 'NEXTWEEK', '20', 'perc', array( 'exp_date' => gmdate( 'Y-m-d', strtotime( '+7 days' ) ) ) );

		list( $valid, $error ) = $this->check( 'NEXTWEEK' );

		$this->assertTrue( $valid, $error );
	}

	public function test_the_redemption_limit() {
		$coupon_id = ASP_Test_Factory::create_coupon(
			'LIMITED',
			'20',
			'perc',
			array(
				'red_limit' => '5',
				'red_count' => '4',
			)
		);

		list( $valid, $error ) = $this->check( 'LIMITED' );
		$this->assertTrue( $valid, 'The coupon was rejected before the limit was reached. ' . $error );

		update_post_meta( $coupon_id, 'asp_coupon_red_count', '5' );
		$this->assert_rejected( 'LIMITED', 'Coupon redemption limit is reached.' );
	}

	public function test_coupons_disabled_in_the_settings() {
		ASP_Test_Factory::create_coupon( 'SAVE20', '20', 'perc' );
		ASP_Test_Factory::update_settings( array( 'coupons_enabled' => 0 ) );

		$this->assert_rejected( 'SAVE20', 'Applying coupon is not allowed for this product.' );
	}

	public function test_the_product_coupon_setting_overrides_the_general_setting() {
		ASP_Test_Factory::create_coupon( 'SAVE20', '20', 'perc' );

		//Coupons enabled in the settings, disabled for this product.
		update_post_meta( $this->product_id, 'asp_product_coupons_setting', '0' );
		$this->assert_rejected( 'SAVE20', 'Applying coupon is not allowed for this product.' );

		//Coupons disabled in the settings, enabled for this product.
		ASP_Test_Factory::update_settings( array( 'coupons_enabled' => 0 ) );
		update_post_meta( $this->product_id, 'asp_product_coupons_setting', '1' );
		list( $valid, $error ) = $this->check( 'SAVE20' );
		$this->assertTrue( $valid, $error );

		//"Use the general setting" (2).
		update_post_meta( $this->product_id, 'asp_product_coupons_setting', '2' );
		$this->assert_rejected( 'SAVE20', 'Applying coupon is not allowed for this product.' );
	}

	public function test_a_coupon_only_for_some_products() {
		$other_product_id = ASP_Test_Factory::create_product( '10' );
		ASP_Test_Factory::create_coupon(
			'ONLYONE',
			'20',
			'perc',
			array(
				'only_for_allowed_products' => 1,
				//The coupon form saves the product IDs as strings.
				'allowed_products'          => array( (string) $this->product_id ),
			)
		);

		list( $valid, $error ) = $this->check( 'ONLYONE' );
		$this->assertTrue( $valid, $error );

		list( $valid, $error, $coupon ) = $this->check( 'ONLYONE', $other_product_id );
		$this->assertFalse( $valid, 'The coupon was accepted for a product it is not allowed for.' );
		$this->assertSame( 'Coupon is not allowed for this product.', $error );
		$this->assertFalse( $coupon, 'A rejected coupon must not stay applied to the product.' );
	}

	private function assert_rejected( $code, $expected_error ) {
		list( $valid, $error, $coupon ) = $this->check( $code );
		$this->assertFalse( $valid, "The coupon '{$code}' was accepted." );
		$this->assertSame( $expected_error, $error );
		$this->assertFalse( $coupon, 'A rejected coupon must not stay applied to the product.' );
	}
}
