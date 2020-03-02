<?php

class ASP_Session {

	protected static $instance = null;
	private $transient_id      = false;
	private $trans_name;

	public function __construct() {
		self::$instance = $this;
		$this->init();
	}

	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function get_transient_id() {
		if ( empty( $this->transient_id ) ) {
			$this->transient_id = md5( uniqid( 'asp', true ) );
		}
		return $this->transient_id;
	}

	public function set_transient_data( $name, $data ) {
		$curr_data = get_transient( $this->trans_name );
		if ( empty( $curr_data ) ) {
			$curr_data = array();
		}
		$curr_data[ $name ] = wp_json_encode( $data );
		delete_transient( $this->trans_name );
		set_transient( $this->trans_name, $curr_data, 3600 );
	}

	public function get_transient_data( $name, $default = false ) {
		$curr_data = get_transient( $this->trans_name );
		if ( empty( $curr_data ) ) {
			return $default;
		}
		if ( ! isset( $curr_data[ $name ] ) ) {
			return $default;
		}
		return json_decode( $curr_data[ $name ], true );
	}

	public function init() {
		$cookie_transient_id = filter_input( INPUT_COOKIE, 'asp_transient_id', FILTER_SANITIZE_STRING );
		if ( empty( $cookie_transient_id ) ) {
			if ( ! headers_sent() ) {
				$cookiepath    = ! defined( 'COOKIEPATH' ) ? '/' : COOKIEPATH;
				$cookie_domain = ! defined( 'COOKIE_DOMAIN' ) ? false : COOKIE_DOMAIN;
				setcookie( 'asp_transient_id', $this->get_transient_id(), 0, $cookiepath, $cookie_domain );
			}
		} else {
			$this->transient_id = $cookie_transient_id;
		}
		$this->trans_name = 'asp_session_data_' . $this->get_transient_id();
	}

}

ASP_Session::get_instance();
