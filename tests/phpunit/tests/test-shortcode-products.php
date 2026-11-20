<?php
/**
 * The products that the [accept_stripe_payment] shortcode creates (post type asp-products-temp) when the shortcode is shown.
 * The payment popup, the amount check and the payment processing use these products like normal products.
 */
class Test_Shortcode_Products extends WP_UnitTestCase {

	/**
	 * Shows the shortcode and returns the product it created.
	 */
	private function shortcode_product( $shortcode ) {
		do_shortcode( $shortcode );
		$posts = get_posts(
			array(
				'post_type'      => 'asp-products-temp',
				'posts_per_page' => 1,
				'orderby'        => 'ID',
				'order'          => 'DESC',
			)
		);
		$this->assertNotEmpty( $posts, 'The shortcode did not create its product.' );
		return new ASP_Product_Item( $posts[0]->ID );
	}

	public function test_the_shortcode_saves_its_product() {
		$item = $this->shortcode_product( '[accept_stripe_payment name="Shortcode Ebook" price="10" tax="10"]' );

		$this->assertSame( 'Shortcode Ebook', $item->get_name() );
		$this->assertEquals( 10, $item->get_price() );
		$this->assertEquals( 10, $item->get_tax() );
		$this->assertEquals( 1100, $item->get_total( true ) );
		$this->assertSame( 'one_time', $item->get_type_for_amount_check(), 'A shortcode product with a price must get the amount check.' );
	}

	public function test_a_shortcode_without_a_price_is_checked_as_a_donation() {
		$item = $this->shortcode_product( '[accept_stripe_payment name="Shortcode Donation" price="0"]' );

		$this->assertSame( 'donation', $item->get_type_for_amount_check(), 'The buyer enters the amount, so it must not be checked against a price of 0.' );
	}

	/**
	 * Fixed after 2.1.5: the shortcode saved the shipping in cents (shipping="5" became $500.00).
	 */
	public function test_the_shortcode_shipping_is_saved_as_entered() {
		$item = $this->shortcode_product( '[accept_stripe_payment name="Shortcode Shipping" price="10" shipping="5"]' );

		$this->assertEquals( 5, $item->get_shipping() );
		$this->assertEquals( 1500, $item->get_total( true ) );
	}

	/**
	 * A product saved by an older version (shipping in cents) is corrected the next time the shortcode is shown.
	 */
	public function test_the_shipping_of_a_product_saved_by_an_older_version_is_corrected() {
		$shortcode = '[accept_stripe_payment name="Shortcode Old Shipping" price="10" shipping="5"]';
		$item      = $this->shortcode_product( $shortcode );
		update_post_meta( $item->get_product_id(), 'asp_product_shipping', 500 );

		$item = $this->shortcode_product( $shortcode );

		$this->assertEquals( 5, $item->get_shipping() );
	}
}
