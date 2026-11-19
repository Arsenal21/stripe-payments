<?php
/**
 * The buyer and seller emails after a payment (ASP_Process_IPN_NG::send_buyer_notification_email() and send_seller_notification_email())
 * and their merge tags. The WordPress test library replaces PHPMailer with a mock that keeps the sent emails.
 */
class Test_Emails extends WP_UnitTestCase {

	private $product_id;

	public function set_up() {
		parent::set_up();
		reset_phpmailer_instance();
		$this->product_id = ASP_Test_Factory::create_product( '10', array( 'asp_product_tax' => '10' ), 'Test Ebook' );
		ASP_Test_Factory::update_settings(
			array(
				'from_email_address'        => 'Test Shop <shop@example.com>',
				'buyer_email_subject'       => 'Thank you for the purchase',
				'seller_notification_email' => 'seller@example.com',
				'seller_email_subject'      => 'Notification of product sale',
			)
		);
	}

	public function tear_down() {
		reset_phpmailer_instance();
		parent::tear_down();
	}

	public function test_no_emails_are_sent_when_they_are_turned_off() {
		$data = ASP_Test_Factory::order_data( $this->product_id );

		ASP_Process_IPN_NG::send_buyer_notification_email( $data );
		ASP_Process_IPN_NG::send_seller_notification_email( $data );

		$this->assertCount( 0, tests_retrieve_phpmailer_instance()->mock_sent );
	}

	public function test_the_buyer_email_goes_to_the_buyer_with_the_product_details() {
		ASP_Test_Factory::update_settings(
			array(
				'send_emails_to_buyer' => 1,
				'buyer_email_body'     => "Hello\n\n{product_details}",
			)
		);

		ASP_Process_IPN_NG::send_buyer_notification_email( ASP_Test_Factory::order_data( $this->product_id ) );

		$email = $this->get_only_sent_email();
		$this->assertSame( 'buyer@example.com', $email->to[0][0] );
		$this->assertSame( 'Thank you for the purchase', $email->subject );
		$this->assertStringContainsString( 'From: Test Shop <shop@example.com>', $email->header );
		$expected = array(
			'Product Name: Test Ebook',
			'Quantity: 2',
			'Item Price: $10.00',
			'Tax: $2.00',
			'Total Amount: $22.00',
		);
		foreach ( $expected as $text ) {
			$this->assertStringContainsString( $text, $email->body );
		}
		$this->assertStringNotContainsString( 'Order Info:', $email->body, 'The buyer email must not have the link to the admin order page.' );
	}

	public function test_the_merge_tags_are_replaced() {
		ASP_Test_Factory::update_settings(
			array(
				'send_emails_to_buyer' => 1,
				'buyer_email_body'     => 'Hi {first_name} {last_name} ({payer_email}). Transaction: {transaction_id}. Item: {item_name} x {item_quantity} at {item_price_curr}. '
					. 'Tax: {tax} = {tax_amt}. Total: {purchase_amt_curr} ({purchase_amt} {currency_code}). Card: {card_brand} {card_last_4} ({payment_method}). '
					. 'Coupon: [{coupon_code}]. Download: {download_url}',
			)
		);
		$data = ASP_Test_Factory::order_data( $this->product_id, array( 'item_url' => 'https://example.com/files/ebook.pdf' ) );

		ASP_Process_IPN_NG::send_buyer_notification_email( $data );

		$this->assertStringContainsString(
			'Hi Jane Buyer (buyer@example.com). Transaction: ch_test_123. Item: Test Ebook x 2 at $10.00. '
			. 'Tax: 10% = $2.00. Total: $22.00 (22.00 USD). Card: visa 4242 (card). '
			. 'Coupon: []. Download: https://example.com/files/ebook.pdf',
			$this->get_only_sent_email()->body
		);
	}

	public function test_the_download_link_is_added_to_the_product_details() {
		ASP_Test_Factory::update_settings(
			array(
				'send_emails_to_buyer' => 1,
				'buyer_email_body'     => '{product_details}',
			)
		);
		$data = ASP_Test_Factory::order_data( $this->product_id, array( 'item_url' => 'https://example.com/files/ebook.pdf' ) );

		ASP_Process_IPN_NG::send_buyer_notification_email( $data );

		$this->assertStringContainsString( 'Download link: https://example.com/files/ebook.pdf', $this->get_only_sent_email()->body );
	}

	public function test_html_buyer_email() {
		ASP_Test_Factory::update_settings(
			array(
				'send_emails_to_buyer' => 1,
				'buyer_email_type'     => 'html',
				'buyer_email_body'     => "Hello\n{product_details}",
			)
		);

		ASP_Process_IPN_NG::send_buyer_notification_email( ASP_Test_Factory::order_data( $this->product_id ) );

		$email = $this->get_only_sent_email();
		$this->assertStringContainsString( 'Content-Type: text/html', $email->header );
		$this->assertStringContainsString( 'Hello<br />', $email->body );
	}

	public function test_the_seller_email_goes_to_the_seller_with_a_link_to_the_order() {
		ASP_Test_Factory::update_settings(
			array(
				'send_emails_to_seller' => 1,
				'seller_email_body'     => "Sale to {payer_email}\n{product_details}",
			)
		);
		$order_id = ( new ASP_Order_Item() )->create( $this->product_id, 'pi_test_123' );
		$data     = ASP_Test_Factory::order_data( $this->product_id, array( 'order_post_id' => $order_id ) );

		ASP_Process_IPN_NG::send_seller_notification_email( $data );

		$email = $this->get_only_sent_email();
		$this->assertSame( 'seller@example.com', $email->to[0][0] );
		$this->assertSame( 'Notification of product sale', $email->subject );
		$this->assertStringContainsString( 'Reply-To: buyer@example.com', $email->header, 'Replying to the seller email must go to the buyer.' );
		$this->assertStringContainsString( 'Sale to buyer@example.com', $email->body );
		$this->assertStringContainsString( 'Total Amount: $22.00', $email->body );
		$this->assertStringContainsString( 'Order Info: ' . admin_url( 'post.php?post=' . $order_id . '&action=edit' ), $email->body );
	}

	private function get_only_sent_email() {
		$mailer = tests_retrieve_phpmailer_instance();
		$this->assertCount( 1, $mailer->mock_sent, 'Exactly one email should have been sent.' );
		return $mailer->get_sent( 0 );
	}
}
