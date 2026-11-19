<?php
/**
 * Helpers that create the plugin's products, coupons and order data for the tests.
 */
class ASP_Test_Factory {

	const SETTINGS_OPTION = 'AcceptStripePayments-settings';

	/**
	 * Creates a published one-time payment product.
	 *
	 * @param string|float $price Product price (as entered on the edit product page, for example '10' or '19.99').
	 * @param array        $meta  Other product meta, for example array( 'asp_product_tax' => '10' ).
	 * @param string       $title Product name.
	 *
	 * @return int Product ID.
	 */
	public static function create_product( $price, $meta = array(), $title = 'Test Product' ) {
		$product_id = wp_insert_post(
			array(
				'post_type'   => 'asp-products',
				'post_title'  => $title,
				'post_status' => 'publish',
			)
		);
		$meta = array_merge(
			array(
				'asp_product_type'  => 'one_time',
				'asp_product_price' => (string) $price,
			),
			$meta
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $product_id, $key, $value );
		}
		return $product_id;
	}

	/**
	 * Creates an active coupon that started yesterday (the meta is saved the same way as the admin coupon form saves it).
	 *
	 * @param string       $code          Coupon code.
	 * @param string|float $discount      Discount (percent or fixed amount).
	 * @param string       $discount_type 'perc' or 'fixed'.
	 * @param array        $meta          Other coupon meta without the 'asp_coupon_' prefix, for example array( 'exp_date' => '2020-01-01' ).
	 *
	 * @return int Coupon ID.
	 */
	public static function create_coupon( $code, $discount, $discount_type = 'perc', $meta = array() ) {
		$coupon_id = wp_insert_post(
			array(
				'post_type'   => 'asp_coupons',
				'post_title'  => '',
				'post_status' => 'publish',
			)
		);
		$meta = array_merge(
			array(
				'code'             => $code,
				'active'           => 1,
				'discount'         => (string) $discount,
				'discount_type'    => $discount_type,
				'start_date'       => gmdate( 'Y-m-d', strtotime( '-1 day' ) ),
				'exp_date'         => '',
				'red_limit'        => '',
				'red_count'        => '',
				'per_order'        => 0,
				'allowed_products' => array(),
			),
			$meta
		);
		foreach ( $meta as $key => $value ) {
			update_post_meta( $coupon_id, 'asp_coupon_' . $key, $value );
		}
		return $coupon_id;
	}

	/**
	 * Changes some of the plugin settings.
	 */
	public static function update_settings( $changes ) {
		$settings = get_option( self::SETTINGS_OPTION );
		update_option( self::SETTINGS_OPTION, array_merge( $settings, $changes ) );
	}

	/**
	 * The custom inputs that the create PaymentIntent request passes to ASP_Product_Item::validate_total_amount().
	 */
	public static function custom_inputs( $overrides = array() ) {
		return array_merge(
			array(
				'coupon_code'      => '',
				'price_variation'  => '',
				'billing_details'  => null,
				'shipping_details' => null,
			),
			$overrides
		);
	}

	/**
	 * Billing or shipping details as the payment popup sends them (after json_decode).
	 */
	public static function address_details( $country, $state = '', $city = '' ) {
		return array(
			'name'    => 'Test Buyer',
			'email'   => 'buyer@example.com',
			'address' => array(
				'line1'       => '1 Test Street',
				'city'        => $city,
				'state'       => $state,
				'country'     => $country,
				'postal_code' => '12345',
			),
		);
	}

	/**
	 * The order data that the payment processing (ASP_Process_IPN_NG::process_ipn) builds for a paid order:
	 * 2 x $10.00 with 10% tax, paid $22.00 by card.
	 */
	public static function order_data( $product_id, $overrides = array() ) {
		return array_merge(
			array(
				'product_id'         => $product_id,
				'paid_amount'        => 22,
				'currency_code'      => 'USD',
				'item_quantity'      => 2,
				'charge'             => (object) array(
					'object'                 => 'charge',
					'id'                     => 'ch_test_123',
					'created'                => strtotime( '2026-01-15 10:30:00' ),
					'failure_code'           => null,
					'failure_message'        => null,
					'payment_method_details' => (object) array(
						'type' => 'card',
						'card' => (object) array(
							'brand' => 'visa',
							'last4' => '4242',
						),
					),
				),
				'stripeTokenType'    => 'card',
				'is_live'            => false,
				'charge_description' => '',
				'item_name'          => get_the_title( $product_id ),
				'item_price'         => 10,
				'stripeEmail'        => 'buyer@example.com',
				'customer_name'      => 'Jane Buyer',
				'purchase_date'      => 'January 15, 2026, 10:30 am',
				'txn_id'             => 'ch_test_123',
				'button_key'         => 'test-button-key',
				'item_url'           => '',
				'billing_address'    => '',
				'shipping_address'   => '',
				'additional_items'   => array( 'Tax' => 2 ),
				'tax_perc'           => 10,
				'tax'                => 2,
			),
			$overrides
		);
	}
}
