<?php
/**
 * Basic checks that the plugin loads, activates and shows a product in WordPress.
 */
class Test_Plugin_Smoke extends WP_UnitTestCase {

	const SETTINGS_OPTION = 'AcceptStripePayments-settings';

	public function test_plugin_is_loaded() {
		$this->assertTrue( class_exists( 'ASPMain' ), 'The ASPMain class is not loaded.' );
		$this->assertTrue( class_exists( 'AcceptStripePayments' ), 'The AcceptStripePayments class is not loaded.' );
		$this->assertTrue( defined( 'WP_ASP_PLUGIN_VERSION' ), 'The plugin version constant is not defined.' );
		$this->assertInstanceOf( 'AcceptStripePayments', AcceptStripePayments::get_instance() );
	}

	public function test_activation_saves_the_default_settings() {
		$settings = get_option( self::SETTINGS_OPTION );
		$this->assertIsArray( $settings, 'The settings were not saved on activation.' );
		$this->assertSame( 'USD', $settings['currency_code'] );
		$this->assertSame( 0, $settings['is_live'], 'A new install must start in test mode.' );
		$this->assertSame( 25, $settings['daily_txn_limit_without_captcha'] );
		$this->assertNotEmpty( get_option( 'asp_hash_private_key_one' ), 'The private hashing key was not created.' );
	}

	public function test_activation_creates_the_checkout_and_products_pages() {
		AcceptStripePayments::activate( false );

		$settings = get_option( self::SETTINGS_OPTION );

		$checkout_page = get_post( $settings['checkout_page_id'] );
		$this->assertInstanceOf( 'WP_Post', $checkout_page, 'The checkout result page was not created.' );
		$this->assertStringContainsString( '[accept_stripe_payment_checkout]', $checkout_page->post_content );
		$this->assertSame( 'publish', $checkout_page->post_status );

		$products_page = get_post( $settings['products_page_id'] );
		$this->assertInstanceOf( 'WP_Post', $products_page, 'The products page was not created.' );
		$this->assertStringContainsString( '[asp_show_all_products]', $products_page->post_content );
	}

	public function test_activating_again_does_not_create_the_pages_twice() {
		AcceptStripePayments::activate( false );
		AcceptStripePayments::activate( false );

		$this->assertCount( 1, $this->get_pages_containing( '[accept_stripe_payment_checkout]' ), 'The checkout result page was created more than once.' );
		$this->assertCount( 1, $this->get_pages_containing( '[asp_show_all_products]' ), 'The products page was created more than once.' );
	}

	/**
	 * A plugin update runs the activation again: the saved settings must stay, and settings added in the new version get their defaults.
	 */
	public function test_activating_again_keeps_the_saved_settings() {
		$settings                  = get_option( self::SETTINGS_OPTION );
		$settings['currency_code'] = 'EUR';
		$settings['button_text']   = 'Order now';
		unset( $settings['daily_txn_limit_with_captcha'] );
		update_option( self::SETTINGS_OPTION, $settings );

		AcceptStripePayments::activate( false );

		$settings = get_option( self::SETTINGS_OPTION );
		$this->assertSame( 'EUR', $settings['currency_code'] );
		$this->assertSame( 'Order now', $settings['button_text'] );
		$this->assertSame( 100, $settings['daily_txn_limit_with_captcha'], 'A missing setting did not get its default value.' );
	}

	public function test_post_types_are_registered() {
		$this->assertTrue( post_type_exists( 'asp-products' ), 'The products post type is not registered.' );
		$this->assertTrue( post_type_exists( 'stripe_order' ), 'The orders post type is not registered.' );
	}

	public function test_main_shortcodes_are_registered() {
		$shortcodes = array(
			'asp_product',
			'asp_product_ng',
			'accept_stripe_payment',
			'accept_stripe_payment_ng',
			'asp_show_all_products',
			'accept_stripe_payment_checkout',
			'accept_stripe_payment_checkout_error',
			'asp_show_my_transactions',
			'asp_available_quantity',
		);
		foreach ( $shortcodes as $shortcode ) {
			$this->assertTrue( shortcode_exists( $shortcode ), "The [{$shortcode}] shortcode is not registered." );
		}
	}

	public function test_product_shortcode_shows_the_buy_button_and_the_price_with_tax() {
		$product_id = $this->create_product( 'Smoke Test Ebook', '10', array( 'asp_product_tax' => '10' ) );

		$output = do_shortcode( '[asp_product id="' . $product_id . '"]' );

		$this->assertStringNotContainsString( 'stripe_payments_error_msg', $output, 'The product shortcode shows an error.' );
		$this->assertStringContainsString( 'Smoke Test Ebook', $output );
		$this->assertStringContainsString( 'id="asp_ng_form_', $output, 'The payment form is missing.' );
		$this->assertStringContainsString( 'id="asp_ng_button_', $output, 'The buy button is missing.' );
		$this->assertStringContainsString( 'Buy Now', $output, 'The default button text is missing.' );
		$this->assertStringContainsString( '$10.00', $output, 'The price is missing.' );
		$this->assertStringContainsString( '$1.00 (tax)', $output, 'The tax amount (10% of $10) is wrong.' );
		$this->assertStringContainsString( '$11.00', $output, 'The total with tax is wrong.' );
	}

	public function test_product_shortcode_shows_an_error_for_a_wrong_product_id() {
		$this->assertStringContainsString( 'Error: product ID is invalid.', do_shortcode( '[asp_product id="abc"]' ) );

		$post_id = self::factory()->post->create();
		$output  = do_shortcode( '[asp_product id="' . $post_id . '"]' );
		$this->assertStringContainsString( "Can't find product with ID {$post_id}", $output, 'A post that is not a product was shown as a product.' );
		$this->assertStringNotContainsString( 'asp_ng_button_', $output );
	}

	public function test_accept_stripe_payment_shortcode_needs_a_name() {
		$this->assertStringContainsString( 'It is missing the "name" field', do_shortcode( '[accept_stripe_payment price="5"]' ) );

		$output = do_shortcode( '[accept_stripe_payment name="Smoke Test Donation" price="5"]' );
		$this->assertStringNotContainsString( 'stripe_payments_error_msg', $output );
		$this->assertStringContainsString( 'id="asp_ng_button_', $output, 'The buy button is missing.' );
	}

	public function test_all_products_shortcode_lists_a_product() {
		$this->create_product( 'Smoke Test Listed Product', '20' );

		$output = do_shortcode( '[asp_show_all_products]' );

		$this->assertStringContainsString( 'Smoke Test Listed Product', $output );
	}

	/**
	 * Creates a published product with a price (and other product meta).
	 */
	private function create_product( $title, $price, $meta = array() ) {
		$product_id = self::factory()->post->create(
			array(
				'post_type'   => 'asp-products',
				'post_title'  => $title,
				'post_status' => 'publish',
			)
		);
		update_post_meta( $product_id, 'asp_product_price', $price );
		foreach ( $meta as $key => $value ) {
			update_post_meta( $product_id, $key, $value );
		}
		return $product_id;
	}

	private function get_pages_containing( $text ) {
		$pages = get_pages( array( 'post_status' => 'publish' ) );
		return array_filter(
			$pages,
			function ( $page ) use ( $text ) {
				return false !== strpos( $page->post_content, $text );
			}
		);
	}
}
