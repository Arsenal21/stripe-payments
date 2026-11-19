<?php
/**
 * The order records (ASP_Order_Item): created when the payment starts, completed with the order details after the payment.
 */
class Test_Orders extends WP_UnitTestCase {

	private $product_id;

	public function set_up() {
		parent::set_up();
		$this->product_id = ASP_Test_Factory::create_product( '10', array( 'asp_product_tax' => '10' ), 'Test Ebook' );
	}

	public function tear_down() {
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	public function test_a_new_order_is_an_incomplete_pending_order() {
		$order    = new ASP_Order_Item();
		$order_id = $order->create( $this->product_id, 'pi_test_123' );

		$post = get_post( $order_id );
		$this->assertSame( 'stripe_order', $post->post_type );
		$this->assertSame( 'pending', $post->post_status );
		$this->assertSame( '[Test Mode] [Incomplete]Test Ebook', $post->post_title );
		$this->assertSame( 'pi_test_123', get_post_meta( $order_id, 'pi_id', true ) );
		$this->assertEquals( $this->product_id, get_post_meta( $order_id, 'asp_product_id', true ) );
		$this->assertSame( 'incomplete', $order->get_status() );
		$this->assertCount( 1, get_post_meta( $order_id, 'asp_order_events', true ) );
	}

	public function test_the_order_of_a_logged_in_buyer_is_linked_to_the_user() {
		$user_id = self::factory()->user->create();
		wp_set_current_user( $user_id );

		$order_id = ( new ASP_Order_Item() )->create( $this->product_id, 'pi_test_123' );

		$this->assertEquals( $user_id, get_post_meta( $order_id, 'asp_user_id', true ) );
	}

	public function test_an_order_is_found_by_its_payment_intent() {
		$order_id = ( new ASP_Order_Item() )->create( $this->product_id, 'pi_test_123' );

		$this->assertSame( $order_id, ( new ASP_Order_Item() )->find( 'pi_id', 'pi_test_123' ) );
		$this->assertFalse( ( new ASP_Order_Item() )->find( 'pi_id', 'pi_test_other' ) );
	}

	public function test_status_changes_are_saved_as_order_events_without_markup() {
		$order = new ASP_Order_Item();
		$order->create( $this->product_id, 'pi_test_123' );

		$order->change_status( 'paid', '<b>Paid</b> by card<script>alert(1)</script>' );

		$this->assertSame( 'paid', $order->get_status() );
		$events = get_post_meta( $order->get_id(), 'asp_order_events', true );
		$this->assertCount( 2, $events );
		$this->assertSame( 'paid', $events[1]['status'] );
		$this->assertSame( 'Paid by card', $events[1]['comment'] );
	}

	public function test_a_paid_order_gets_the_order_details() {
		$order = new ASP_Order_Item();
		$order->create( $this->product_id, 'pi_test_123' );
		$data = ASP_Test_Factory::order_data( $this->product_id );

		$order_id = $order->update_legacy( $data, $data['charge'] );

		$post = get_post( $order_id );
		$this->assertSame( 'publish', $post->post_status );
		$this->assertSame( '[Test Mode] 2 x Test Ebook - $22.00', $post->post_title );
		$expected = array(
			'Transaction ID: ch_test_123',
			'Product Name: Test Ebook',
			'Quantity: 2',
			'Item Price: $10.00',
			'Tax: $2.00',
			'Total Amount: $22.00',
			'E-Mail Address: buyer@example.com',
			"Customer's Name: Jane Buyer",
			'Payment Source: card',
		);
		foreach ( $expected as $text ) {
			$this->assertStringContainsString( $text, $post->post_content );
		}
	}

	public function test_a_failed_payment_stays_pending_with_the_failure_details() {
		$order = new ASP_Order_Item();
		$order->create( $this->product_id, 'pi_test_123' );
		$data                            = ASP_Test_Factory::order_data( $this->product_id );
		$data['charge']->failure_code    = 'card_declined';
		$data['charge']->failure_message = 'Your card was declined.';

		$order_id = $order->update_legacy( $data, $data['charge'] );

		$post = get_post( $order_id );
		$this->assertSame( 'pending', $post->post_status );
		$this->assertStringContainsString( 'Payment Failure Details', $post->post_content );
		$this->assertStringContainsString( 'card_declined: Your card was declined.', $post->post_content );
	}

	public function test_orders_can_be_turned_off_in_the_settings() {
		$this->assertTrue( ( new ASP_Order_Item() )->can_create() );

		ASP_Test_Factory::update_settings( array( 'dont_create_order' => 1 ) );

		$this->assertFalse( ( new ASP_Order_Item() )->can_create() );
	}
}
