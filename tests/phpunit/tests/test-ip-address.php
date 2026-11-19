<?php
/**
 * The visitor's IP address (ASP_Utils::get_user_ip_address). The bot mitigation checks use it: the page load signature
 * and the limit of create PaymentIntent requests per IP address (the card testing protection).
 */
class Test_IP_Address extends WP_UnitTestCase {

	private $saved_server;

	public function set_up() {
		parent::set_up();
		$this->saved_server = $_SERVER;
	}

	public function tear_down() {
		$_SERVER = $this->saved_server;
		parent::tear_down();
	}

	public function test_the_connection_address_is_used() {
		$_SERVER['REMOTE_ADDR'] = '198.51.100.1';
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );

		$this->assertSame( '198.51.100.1', ASP_Utils::get_user_ip_address() );
	}

	/**
	 * Known issue (see "Known issues" in tests/TESTS.md). The fix removes the markTestSkipped() line.
	 * A site behind a proxy (for example Cloudflare) may need the forwarded address; that can be an option or a filter,
	 * but by default a header sent by the visitor must not replace the connection address.
	 */
	public function test_an_x_forwarded_for_header_sent_by_the_visitor_is_not_trusted() {
		$this->markTestSkipped( 'Known issue, not fixed yet: get_user_ip_address() uses the X-Forwarded-For header when there is one, so a visitor can send any IP address and get around the per-IP limit of create PaymentIntent requests.' );

		$_SERVER['REMOTE_ADDR']          = '198.51.100.1';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.7';

		$this->assertSame( '198.51.100.1', ASP_Utils::get_user_ip_address() );
	}
}
