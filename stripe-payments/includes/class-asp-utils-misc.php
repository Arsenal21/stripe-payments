<?php

class ASP_Utils_Misc {

	public static function get_current_page_url() {
		$pageURL = 'http';

		if ( isset( $_SERVER['SCRIPT_URI'] ) && ! empty( $_SERVER['SCRIPT_URI'] ) ) {
			$pageURL = $_SERVER['SCRIPT_URI'];
			$pageURL = str_replace( ':443', '', $pageURL ); //remove any port number from the URL value (some hosts include the port number with this).
			$pageURL = apply_filters( 'asp_get_current_page_url_filter', $pageURL );
			return $pageURL;
		}

		//Check if 'SERVER_NAME' is set. If not, try get the URL from WP.
		if ( ! isset( $_SERVER['SERVER_NAME'] ) ) {
			global $wp;
			if ( is_object( $wp ) && isset( $wp->request ) ) {
				//Try to get the URL from WP
				$pageURL = home_url( add_query_arg( array(), $wp->request ) );
				$pageURL = apply_filters( 'asp_get_current_page_url_filter', $pageURL );
				return $pageURL;
			}
		}

		//Construct the URL value from the $_SERVER array values.
		if ( isset( $_SERVER['HTTPS'] ) && ( $_SERVER['HTTPS'] == 'on' ) ) {
			$pageURL .= 's';
		}
		$pageURL .= '://';
		if ( isset( $_SERVER['SERVER_PORT'] ) && ( $_SERVER['SERVER_PORT'] != '80' ) && ( $_SERVER['SERVER_PORT'] != '443' ) ) {
			$pageURL .= ltrim( $_SERVER['SERVER_NAME'], '.*' ) . ':' . $_SERVER['SERVER_PORT'] . $_SERVER['REQUEST_URI'];
		} else {
			$pageURL .= ltrim( $_SERVER['SERVER_NAME'], '.*' ) . $_SERVER['REQUEST_URI'];
		}

		//Clean any known port numbers from the URL (some hosts may include these port numbers).
		$pageURL = str_replace( ':8080', '', $pageURL );

		//Trigger filter 
		$pageURL = apply_filters( 'asp_get_current_page_url_filter', $pageURL );

		return $pageURL;
	}

	public static function secure_badge_allowed_tags() {
		return array(
			'img' => array(
				'src'	=> array(),
				'alt'	=> array(),
				'class' => array(),
				'id' => array(),
			),
			'ul' => array(
				'class' => array(),
				'id' => array(),
			),
			'li' => array(
				'class' => array(),
				'id' => array(),
			),
			'p' => array(
				'class' => array(),
				'id' => array(),
			),
			'br' => array()
		);
	}

	public static function secure_badge_default_content() {
		$output = '<ul>'.PHP_EOL;
		$output .= ' <li>100% Secure Checkout</li>'.PHP_EOL;
		$output .= ' <li>All transactions are encrypted using SSL/TLS technology.</li>'.PHP_EOL;
		$output .= '</ul>';

		return $output;
	}
}
