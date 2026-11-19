<?php
/**
 * Endpoint tests only (installed as a must-use plugin on the GitHub Actions test site, never on a real site).
 *
 * A fake Stripe API, so the payment requests can run without Stripe. It answers both ways the plugin calls Stripe:
 * - the Stripe PHP SDK (the default): a custom SDK HTTP client;
 * - the plugin's own client (the "Don't use Stripe PHP SDK" option, ASP_Stripe_API): the 'pre_http_request' filter.
 * Both go to asp_e2e_stripe_handle(). The fake keeps its data (customers, PaymentIntents and a log of the requests)
 * in the 'asp_e2e_stripe' option, so the test runner can read it and add PaymentIntents to it.
 *
 * There is no browser, so the buyer never confirms the PaymentIntent with Stripe.js: a PaymentIntent is "succeeded"
 * as soon as it is created, and has one charge for its full amount.
 *
 * It also keeps the emails the site sends in the 'asp_e2e_emails' option instead of sending them.
 */

function asp_e2e_stripe_state() {
	$state = get_option( 'asp_e2e_stripe' );
	if ( ! is_array( $state ) ) {
		$state = array(
			'next'            => 1,
			'customers'       => array(),
			'payment_intents' => array(),
			'requests'        => array(),
		);
	}
	return $state;
}

function asp_e2e_stripe_error( $status, $message, $code = 'resource_missing' ) {
	return array(
		$status,
		array(
			'error' => array(
				'type'    => 'invalid_request_error',
				'code'    => $code,
				'message' => $message,
			),
		),
	);
}

/**
 * Stripe returns {} for empty objects (like metadata), not [].
 */
function asp_e2e_stripe_object( $value ) {
	return empty( $value ) ? new stdClass() : $value;
}

/**
 * Metadata as Stripe saves it: string values, and a null or empty value removes the key.
 */
function asp_e2e_stripe_metadata( $metadata ) {
	$result = array();
	foreach ( (array) $metadata as $key => $value ) {
		if ( null !== $value && '' !== $value ) {
			$result[ $key ] = (string) $value;
		}
	}
	return $result;
}

function asp_e2e_stripe_customer_response( $customer ) {
	return array(
		'id'       => $customer['id'],
		'object'   => 'customer',
		'name'     => isset( $customer['name'] ) ? $customer['name'] : null,
		'email'    => isset( $customer['email'] ) ? $customer['email'] : null,
		'address'  => isset( $customer['address'] ) ? $customer['address'] : null,
		'shipping' => isset( $customer['shipping'] ) ? $customer['shipping'] : null,
		'metadata' => asp_e2e_stripe_object( isset( $customer['metadata'] ) ? $customer['metadata'] : array() ),
	);
}

/**
 * A PaymentIntent as Stripe API version 2024-12-18 returns it (no 'charges' list: the plugin gets the charge separately).
 */
function asp_e2e_stripe_pi_response( $pi, $state, $expand_customer ) {
	$customer = $pi['customer'];
	if ( $expand_customer && $customer ) {
		$customer = isset( $state['customers'][ $customer ] ) ? asp_e2e_stripe_customer_response( $state['customers'][ $customer ] ) : null;
	}
	return array(
		'id'                  => $pi['id'],
		'object'              => 'payment_intent',
		'amount'              => $pi['amount'],
		'amount_received'     => $pi['amount'],
		'currency'            => $pi['currency'],
		'customer'            => $customer,
		'description'         => $pi['description'],
		'metadata'            => asp_e2e_stripe_object( $pi['metadata'] ),
		'status'              => 'succeeded',
		'client_secret'       => $pi['id'] . '_secret_e2e',
		'confirmation_method' => 'manual',
		'latest_charge'       => $pi['charge_id'],
		'livemode'            => false,
	);
}

function asp_e2e_stripe_charge_response( $pi, $state ) {
	$billing = isset( $pi['billing_details'] ) ? $pi['billing_details'] : null;
	if ( null === $billing ) {
		$customer = ( $pi['customer'] && isset( $state['customers'][ $pi['customer'] ] ) ) ? $state['customers'][ $pi['customer'] ] : array();
		$billing  = array(
			'name'    => isset( $customer['name'] ) ? $customer['name'] : null,
			'email'   => isset( $customer['email'] ) ? $customer['email'] : null,
			'address' => isset( $customer['address'] ) ? $customer['address'] : null,
		);
	}
	return array(
		'id'                     => $pi['charge_id'],
		'object'                 => 'charge',
		'amount'                 => $pi['amount'],
		'amount_captured'        => $pi['amount'],
		'currency'               => $pi['currency'],
		'created'                => $pi['created'],
		'customer'               => $pi['customer'],
		'payment_intent'         => $pi['id'],
		'paid'                   => true,
		'captured'               => true,
		'status'                 => 'succeeded',
		'billing_details'        => $billing,
		'shipping'               => null,
		'metadata'               => asp_e2e_stripe_object( $pi['metadata'] ),
		'payment_method_details' => array(
			'type' => 'card',
			'card' => array(
				'brand' => 'visa',
				'last4' => '4242',
			),
		),
		'livemode'               => false,
	);
}

/**
 * Handles one Stripe API request.
 *
 * @param string $via    'sdk' or 'wp' (which client sent it; saved in the request log).
 * @param string $method HTTP method.
 * @param string $url    Full URL (it can have a query string).
 * @param array  $params The request parameters.
 *
 * @return array The HTTP status code and the response body (an array, sent as JSON).
 */
function asp_e2e_stripe_handle( $via, $method, $url, $params ) {
	$parts = wp_parse_url( $url );
	$path  = preg_replace( '#^/v1/#', '', $parts['path'] );
	$query = array();
	if ( ! empty( $parts['query'] ) ) {
		parse_str( $parts['query'], $query );
	}
	$params = array_merge( $query, is_array( $params ) ? $params : array() );
	$method = strtoupper( $method );

	$state               = asp_e2e_stripe_state();
	$state['requests'][] = $via . ': ' . $method . ' ' . $path;
	$expand              = isset( $params['expand'] ) ? (array) $params['expand'] : array();

	$segments = explode( '/', trim( $path, '/' ) );
	$resource = $segments[0];
	$id       = isset( $segments[1] ) ? $segments[1] : '';
	$response = asp_e2e_stripe_error( 404, 'Unrecognized request URL (' . $method . ': /v1/' . $path . ').', 'url_invalid' );

	if ( 'customers' === $resource && 'POST' === $method ) {
		if ( '' === $id ) {
			$id = 'cus_e2e_' . $state['next']++;
			$state['customers'][ $id ] = array( 'id' => $id );
		}
		if ( ! isset( $state['customers'][ $id ] ) ) {
			$response = asp_e2e_stripe_error( 404, "No such customer: '{$id}'" );
		} else {
			foreach ( array( 'name', 'email', 'address', 'shipping', 'metadata' ) as $field ) {
				if ( isset( $params[ $field ] ) ) {
					$state['customers'][ $id ][ $field ] = $params[ $field ];
				}
			}
			$response = array( 200, asp_e2e_stripe_customer_response( $state['customers'][ $id ] ) );
		}
	} elseif ( 'payment_intents' === $resource && 'POST' === $method && '' === $id ) {
		$number = $state['next']++;
		$id     = 'pi_e2e_' . $number;
		$state['payment_intents'][ $id ] = array(
			'id'          => $id,
			'amount'      => (int) $params['amount'],
			'currency'    => strtolower( $params['currency'] ),
			'customer'    => isset( $params['customer'] ) ? $params['customer'] : null,
			'description' => isset( $params['description'] ) ? $params['description'] : null,
			'metadata'    => isset( $params['metadata'] ) ? asp_e2e_stripe_metadata( $params['metadata'] ) : array(),
			'charge_id'   => 'ch_e2e_' . $number,
			'created'     => time(),
		);
		$response = array( 200, asp_e2e_stripe_pi_response( $state['payment_intents'][ $id ], $state, false ) );
	} elseif ( 'payment_intents' === $resource && '' !== $id ) {
		if ( ! isset( $state['payment_intents'][ $id ] ) ) {
			$response = asp_e2e_stripe_error( 404, "No such payment_intent: '{$id}'" );
		} else {
			if ( 'POST' === $method ) {
				if ( isset( $params['metadata'] ) ) {
					$state['payment_intents'][ $id ]['metadata'] = array_merge( $state['payment_intents'][ $id ]['metadata'], asp_e2e_stripe_metadata( $params['metadata'] ) );
				}
				if ( isset( $params['description'] ) ) {
					$state['payment_intents'][ $id ]['description'] = $params['description'];
				}
			}
			$response = array( 200, asp_e2e_stripe_pi_response( $state['payment_intents'][ $id ], $state, in_array( 'customer', $expand, true ) ) );
		}
	} elseif ( 'charges' === $resource && 'GET' === $method && '' === $id ) {
		$pi_id = isset( $params['payment_intent'] ) ? $params['payment_intent'] : '';
		$data  = isset( $state['payment_intents'][ $pi_id ] ) ? array( asp_e2e_stripe_charge_response( $state['payment_intents'][ $pi_id ], $state ) ) : array();
		$response = array(
			200,
			array(
				'object'   => 'list',
				'data'     => $data,
				'has_more' => false,
				'url'      => '/v1/charges',
			),
		);
	}

	update_option( 'asp_e2e_stripe', $state, false );
	return $response;
}

//The plugin's own client (ASP_Stripe_API) uses wp_remote_get().
add_filter(
	'pre_http_request',
	function ( $pre, $args, $url ) {
		if ( 0 !== strpos( $url, 'https://api.stripe.com/' ) ) {
			return $pre;
		}
		$params = isset( $args['body'] ) ? $args['body'] : array();
		if ( is_string( $params ) ) {
			parse_str( $params, $params );
		}
		list( $status, $body ) = asp_e2e_stripe_handle( 'wp', isset( $args['method'] ) ? $args['method'] : 'GET', $url, $params );
		return array(
			'headers'  => array(),
			'body'     => wp_json_encode( $body ),
			'response' => array(
				'code'    => $status,
				'message' => '',
			),
			'cookies'  => array(),
			'filename' => null,
		);
	},
	10,
	3
);

/**
 * The Stripe PHP SDK: loads it (the same way the plugin loads it) and gives it the fake HTTP client.
 * The class is declared here, after the SDK is loaded, because it implements the SDK's interface.
 */
function asp_e2e_stripe_install_sdk_client() {
	if ( ! class_exists( 'ASP_Utils' ) ) {
		return;
	}
	ASP_Utils::load_stripe_lib();
	if ( ! class_exists( 'ASP_E2E_Fake_Stripe_Http_Client', false ) ) {
		class ASP_E2E_Fake_Stripe_Http_Client implements \Stripe\HttpClient\ClientInterface {
			public function request( $method, $absUrl, $headers, $params, $hasFile ) {
				list( $status, $body ) = asp_e2e_stripe_handle( 'sdk', $method, $absUrl, $params );
				return array( wp_json_encode( $body ), $status, new \Stripe\Util\CaseInsensitiveArray( array( 'request-id' => 'req_e2e' ) ) );
			}
		}
	}
	\Stripe\ApiRequestor::setHttpClient( new ASP_E2E_Fake_Stripe_Http_Client() );
}
add_action( 'init', 'asp_e2e_stripe_install_sdk_client', 1 );

//Keep the emails instead of sending them.
add_filter(
	'pre_wp_mail',
	function ( $return, $atts ) {
		$emails   = get_option( 'asp_e2e_emails' );
		$emails   = is_array( $emails ) ? $emails : array();
		$emails[] = array(
			'to'      => is_array( $atts['to'] ) ? implode( ', ', $atts['to'] ) : $atts['to'],
			'subject' => $atts['subject'],
			'message' => $atts['message'],
		);
		update_option( 'asp_e2e_emails', $emails, false );
		return true;
	},
	10,
	2
);
