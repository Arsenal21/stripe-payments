<?php

class ASP_Stripe_API {

	protected static $instance;
	protected $api_key;
	protected $api_url    = 'https://api.stripe.com/v1/';
	protected $last_error = array();

	protected $app_info = array(
		'name'       => 'Stripe Payments',
		'partner_id' => 'pp_partner_Fvas9OJ0jQ2oNQ',
		'url'        => 'https://wordpress.org/plugins/stripe-payments/',
		'version'    => WP_ASP_PLUGIN_VERSION,
	);

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function set_api_key( $key ) {
		$this->api_key = $key;
	}

	private function before_request() {
		$this->last_error = array();
	}

	private function get_headers() {
		$ua_string = 'Stripe/v1 PhpBindings/0.0.0';

		$ua_string .= ' ' . $this->format_app_info( $this->app_info );

		$lang_version   = PHP_VERSION;
		$uname_disabled = in_array( 'php_uname', explode( ',', ini_get( 'disable_functions' ) ), true );
		$uname          = $uname_disabled ? '(disabled)' : php_uname();

		$ua = array(
			'bindings_version' => '0.0.0',
			'lang'             => 'php',
			'lang_version'     => $lang_version,
			'publisher'        => 'stripe',
			'uname'            => $uname,
			'application'      => $this->app_info,
		);

		$headers = array(
			'X-Stripe-Client-User-Agent' => json_encode( $ua ),
			'User-Agent'                 => $ua_string,
			'Authorization'              => 'Basic ' . base64_encode( $this->api_key . ':' ),
			'Stripe-Version'             => '2020-03-02',
		);
		return $headers;
	}

	private function process_result( $res ) {
		if ( is_wp_error( $res ) ) {
			$this->last_error['message']    = $res->get_error_message();
			$this->last_error['error_code'] = $res->get_error_code();
			return false;
		}

		if ( 200 !== $res['response']['code'] ) {
			if ( ! empty( $res['body'] ) ) {
				$body = json_decode( $res['body'], true );
				if ( isset( $body['error'] ) ) {
					$this->last_error              = $body['error'];
					$this->last_error['http_code'] = $res['response']['code'];
				}
			}
			return false;
		}

		$return = json_decode( $res['body'] );

		return $return;
	}

	public function get( $endpoint, $params = array() ) {

		$this->before_request();

		$headers = $this->get_headers();

		$res = wp_remote_get(
			$this->api_url . $endpoint,
			array( 'headers' => $headers )
		);

		$return = $this->process_result( $res );

		return $return;

	}

	public function post( $endpoint, $params = array() ) {

		$this->before_request();

		$headers = $this->get_headers();

		$res = wp_remote_get(
			$this->api_url . $endpoint,
			array(
				'method'  => 'POST',
				'headers' => $headers,
				'body'    => $params,
			)
		);

		$return = $this->process_result( $res );
		return $return;

	}

	public function get_last_error() {
		return $this->last_error;
	}

	private function format_app_info( $app_info ) {
		if ( null !== $app_info ) {
			$string = $app_info['name'];
			if ( null !== $app_info['version'] ) {
				$string .= '/' . $app_info['version'];
			}
			if ( null !== $app_info['url'] ) {
				$string .= ' (' . $app_info['url'] . ')';
			}

			return $string;
		}

		return null;
	}

}
