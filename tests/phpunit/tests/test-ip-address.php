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
	 * The X-Forwarded-For header is used when there is one (so sites behind a proxy get the buyer's address).
	 * This is a decision, not a missing fix: see "Decisions" in tests/TESTS.md.
	 */
	public function test_the_first_x_forwarded_for_address_is_used() {
		$_SERVER['REMOTE_ADDR']          = '10.0.0.5';
		$_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1, 10.0.0.5';

		$this->assertSame( '198.51.100.1', ASP_Utils::get_user_ip_address() );
	}

	public function test_the_address_can_be_changed_with_the_filter() {
		$_SERVER['REMOTE_ADDR'] = '10.0.0.5';
		unset( $_SERVER['HTTP_X_FORWARDED_FOR'] );
		add_filter(
			'asp_get_user_ip_address',
			function () {
				return '198.51.100.2';
			}
		);

		$this->assertSame( '198.51.100.2', ASP_Utils::get_user_ip_address() );
	}
}
