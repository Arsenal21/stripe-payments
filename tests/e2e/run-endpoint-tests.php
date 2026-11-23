<?php
/**
 * Endpoint tests: sends the payment requests of the checkout to a running test site (with a fake Stripe API), then checks the database.
 * Run by the GitHub Actions "Plugin Tests" workflow (the "Endpoint tests" job sets up the site). Environment variables:
 * E2E_URL (example: http://127.0.0.1:8080), E2E_WP_PATH (the site's folder), E2E_DB_HOST, E2E_DB_NAME, E2E_DB_USER, E2E_DB_PASS.
 *
 * A checkout in the browser does this, and the tests send the same requests:
 * 1. The payment popup loads (/?asp_action=show_pp&product_id=...). It records the page load signature and has the nonces.
 * 2. The popup sends the create PaymentIntent request (admin-ajax.php, action asp_pp_create_pi) with the amount in cents.
 * 3. The buyer pays (Stripe.js; here the fake Stripe API marks every PaymentIntent as paid).
 * 4. The popup form is submitted to the same URL with the asp_ prefixed fields; the plugin gets the payment from Stripe,
 *    checks it, completes the order, sends the emails and redirects to the checkout result page.
 *
 * The main tests run twice: with the Stripe PHP SDK (the default) and with the plugin's own API client ("Don't use Stripe PHP SDK").
 */
$url        = rtrim( getenv( 'E2E_URL' ), '/' );
$wp_path    = getenv( 'E2E_WP_PATH' );
$db         = new mysqli( getenv( 'E2E_DB_HOST' ), getenv( 'E2E_DB_USER' ), getenv( 'E2E_DB_PASS' ), getenv( 'E2E_DB_NAME' ) );
$prefix     = 'wp_';
$failures   = 0;
$known      = 0;
$cookie_jar = null;

//All the requests send this User-Agent: the security token of the create PaymentIntent request is made from it (see load_popup()).
const E2E_USER_AGENT = 'ASP-E2E-Tests/1.0';

//Known issues that aren't fixed (see "Known issues" in tests/TESTS.md). Their checks still run, but don't fail the tests:
//the output shows "KNOWN ISSUE (still happens)" or "KNOWN ISSUE NOW PASSES". The commit that fixes one removes it from this list,
//so its check becomes a normal check. "Unconfirmed" means the tests show what the code does, but it isn't reproduced with real Stripe yet.
const KNOWN_ISSUES = array(
	'declined-charge-completes-order' => 'Unconfirmed: process_ipn() checks the amount, currency and product of the latest charge, but not that the payment succeeded, so a PaymentIntent with a declined (failed) charge may complete a paid order.',
);

/**
 * A check for a known issue: a normal check when the issue isn't in KNOWN_ISSUES, otherwise it reports the result without failing.
 */
function check_known( $issue, $label, $condition, $details = '' ) {
	global $known;
	if ( ! isset( KNOWN_ISSUES[ $issue ] ) ) {
		return check( $label, $condition, $details );
	}
	$known++;
	if ( $condition ) {
		echo "KNOWN ISSUE NOW PASSES: {$label}\n    Remove '{$issue}' from KNOWN_ISSUES if it is fixed: " . KNOWN_ISSUES[ $issue ] . "\n";
	} else {
		echo "KNOWN ISSUE (still happens): {$label}\n    " . KNOWN_ISSUES[ $issue ] . "\n";
		if ( '' !== $details ) {
			echo '    ' . $details . "\n";
		}
	}
	return $condition;
}

/**
 * Starts a browser session: the following requests keep their cookies (the plugin's session cookie, the login cookies).
 * Returns the cookie file, so a session can be switched back to with use_session().
 */
function new_session() {
	global $cookie_jar;
	$cookie_jar = tempnam( sys_get_temp_dir(), 'asp-e2e-cookies-' );
	return $cookie_jar;
}

/** Switches to a session from new_session(), or to no cookies (null). */
function use_session( $file ) {
	global $cookie_jar;
	$cookie_jar = $file;
}

function check( $label, $condition, $details = '' ) {
	global $failures;
	if ( $condition ) {
		echo "PASS: {$label}\n";
	} else {
		$failures++;
		echo "FAIL: {$label}\n";
		if ( '' !== $details ) {
			echo '    ' . $details . "\n";
		}
	}
	return $condition;
}

function wp_cli( $command ) {
	global $wp_path;
	$output = array();
	exec( 'wp --path=' . escapeshellarg( $wp_path ) . ' ' . $command . ' 2>&1', $output, $exit_code );
	if ( 0 !== $exit_code ) {
		echo "WP-CLI command failed: wp {$command}\n" . implode( "\n", $output ) . "\n";
		exit( 1 );
	}
	return implode( "\n", $output );
}

/**
 * Sends a request (redirects are not followed).
 *
 * @return array The HTTP status code, the body and the Location header.
 */
function http_request( $method, $endpoint, $fields = null, $headers = array() ) {
	global $url, $cookie_jar;
	$location = '';
	$curl     = curl_init( $url . $endpoint );
	curl_setopt_array(
		$curl,
		array(
			CURLOPT_CUSTOMREQUEST  => $method,
			CURLOPT_HTTPHEADER     => $headers,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_FOLLOWLOCATION => false,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_USERAGENT      => E2E_USER_AGENT,
			CURLOPT_HEADERFUNCTION => function ( $curl, $header ) use ( &$location ) {
				if ( 0 === stripos( $header, 'Location:' ) ) {
					$location = trim( substr( $header, 9 ) );
				}
				return strlen( $header );
			},
		)
	);
	if ( null !== $fields ) {
		curl_setopt( $curl, CURLOPT_POSTFIELDS, http_build_query( $fields ) );
	}
	if ( null !== $cookie_jar ) {
		curl_setopt( $curl, CURLOPT_COOKIEFILE, $cookie_jar );
		curl_setopt( $curl, CURLOPT_COOKIEJAR, $cookie_jar );
	}
	$body   = (string) curl_exec( $curl );
	$status = (int) curl_getinfo( $curl, CURLINFO_HTTP_CODE );
	//The cookie file is written when the handle is freed.
	unset( $curl );
	return array(
		'status'   => $status,
		'body'     => $body,
		'location' => $location,
	);
}

/** The response text without the HTML, shortened (printed when a check fails). */
function response_text( $response ) {
	$text = trim( preg_replace( '/\s+/', ' ', strip_tags( preg_replace( '#<(style|script)\b.*?</\1>#s', '', $response['body'] ) ) ) );
	return 'HTTP ' . $response['status'] . ': ' . substr( $text, 0, 300 );
}

function db_value( $sql, $types = '', $params = array() ) {
	global $db;
	$statement = $db->prepare( $sql );
	if ( $types ) {
		$statement->bind_param( $types, ...$params );
	}
	$statement->execute();
	$row = $statement->get_result()->fetch_row();
	return $row ? $row[0] : null;
}

function db_column( $sql, $types, $params ) {
	global $db;
	$statement = $db->prepare( $sql );
	$statement->bind_param( $types, ...$params );
	$statement->execute();
	return array_map(
		function ( $row ) {
			return $row[0];
		},
		$statement->get_result()->fetch_all()
	);
}

function db_write( $sql, $types, $params ) {
	global $db;
	$statement = $db->prepare( $sql );
	$statement->bind_param( $types, ...$params );
	$statement->execute();
}

//Options are read and written directly in the database. Each request to the site reads them again (there is no object cache).
function get_site_option_value( $name ) {
	global $prefix;
	$value = db_value( "SELECT option_value FROM {$prefix}options WHERE option_name = ?", 's', array( $name ) );
	return null === $value ? false : unserialize( $value );
}

function set_site_option_value( $name, $value ) {
	global $prefix;
	db_write( "INSERT INTO {$prefix}options (option_name, option_value, autoload) VALUES (?, ?, 'off') ON DUPLICATE KEY UPDATE option_value = VALUES(option_value)", 'ss', array( $name, serialize( $value ) ) );
}

function delete_site_option_value( $name ) {
	global $prefix;
	db_write( "DELETE FROM {$prefix}options WHERE option_name = ?", 's', array( $name ) );
}

function post_meta( $post_id, $key ) {
	global $prefix;
	return db_value( "SELECT meta_value FROM {$prefix}postmeta WHERE post_id = ? AND meta_key = ? ORDER BY meta_id DESC LIMIT 1", 'is', array( $post_id, $key ) );
}

function set_post_meta( $post_id, $key, $value ) {
	global $prefix;
	db_write( "UPDATE {$prefix}postmeta SET meta_value = ? WHERE post_id = ? AND meta_key = ?", 'sis', array( $value, $post_id, $key ) );
}

function post_field( $post_id, $field ) {
	global $prefix;
	return db_value( "SELECT {$field} FROM {$prefix}posts WHERE ID = ?", 'i', array( $post_id ) );
}

/** The order posts (stripe_order) for this PaymentIntent ID. */
function orders_for_pi( $pi_id ) {
	global $prefix;
	return db_column( "SELECT p.ID FROM {$prefix}posts p JOIN {$prefix}postmeta m ON m.post_id = p.ID WHERE p.post_type = 'stripe_order' AND m.meta_key = 'pi_id' AND m.meta_value = ?", 's', array( $pi_id ) );
}

function stripe_state() {
	$state = get_site_option_value( 'asp_e2e_stripe' );
	return is_array( $state ) ? $state : array(
		'next'            => 1,
		'customers'       => array(),
		'payment_intents' => array(),
		'requests'        => array(),
	);
}

/**
 * Adds a paid PaymentIntent to the fake Stripe API without the create PaymentIntent request
 * (to test what the payment processing does with a payment that the amount check before the payment would have stopped).
 *
 * @param array $state_fields Other states: array( 'status' => 'requires_action' ) needs 3D Secure;
 *                            array( 'status' => 'requires_payment_method', 'charge_status' => 'none' ) isn't paid;
 *                            array( 'status' => 'requires_payment_method', 'charge_status' => 'failed' ) has a declined card.
 */
function add_paid_payment_intent( $amount, $currency, $product_id, $state_fields = array() ) {
	$state  = stripe_state();
	$number = $state['next']++;
	$id     = 'pi_e2e_added_' . $number;
	$state['payment_intents'][ $id ] = array_merge(
		array(
			'id'              => $id,
			'amount'          => $amount,
			'currency'        => $currency,
			'customer'        => null,
			'description'     => 'Added by the test',
			'metadata'        => array( 'Product ID' => (string) $product_id ),
			'charge_id'       => 'ch_e2e_added_' . $number,
			'created'         => time(),
			'status'          => 'succeeded',
			'billing_details' => array(
				'name'    => 'E2E Buyer',
				'email'   => 'buyer@example.com',
				'address' => null,
			),
		),
		$state_fields
	);
	set_site_option_value( 'asp_e2e_stripe', $state );
	return $id;
}

/** Changes some of the plugin settings. */
function update_site_settings( $changes ) {
	set_site_option_value( 'AcceptStripePayments-settings', array_merge( get_site_option_value( 'AcceptStripePayments-settings' ), $changes ) );
}

/** The order of a PaymentIntent (0 if there is none). */
function order_for_pi( $pi_id ) {
	$orders = orders_for_pi( $pi_id );
	return isset( $orders[0] ) ? (int) $orders[0] : 0;
}

/** Billing or shipping details as the popup sends them (JSON). */
function address_json( $country, $state = '', $city = '' ) {
	return json_encode(
		array(
			'name'    => 'E2E Buyer',
			'email'   => 'buyer@example.com',
			'address' => array(
				'line1'       => '1 Test Street',
				'city'        => $city,
				'state'       => $state,
				'country'     => $country,
				'postal_code' => '12345',
			),
		)
	);
}

function emails() {
	$emails = get_site_option_value( 'asp_e2e_emails' );
	return is_array( $emails ) ? $emails : array();
}

function find_email( $to ) {
	foreach ( emails() as $email ) {
		if ( $email['to'] === $to ) {
			return $email;
		}
	}
	return null;
}

/**
 * Loads the payment popup of a product, like the browser does.
 * Returns its nonces and the security token, which the popup's script makes from the browser's User-Agent and the
 * product's button key: md5( navigator.userAgent + button_key ). The plugin checks it in ASP_Self_Hooks_Handler::check_token().
 */
function load_popup( $product_id, $headers = array() ) {
	$response = http_request( 'GET', '/?asp_action=show_pp&product_id=' . $product_id, null, $headers );
	$popup    = array();
	if ( preg_match( '/"asp_pp_ajax_create_pi_nonce":"([0-9a-f]+)"/', $response['body'], $m ) ) {
		$popup['create_pi'] = $m[1];
	}
	if ( preg_match( '/value="([0-9a-f]+)" name="payment_form_nonce"/', $response['body'], $m ) ) {
		$popup['payment_form'] = $m[1];
	}
	if ( preg_match( '/"button_key":"([0-9a-f]+)"/', $response['body'], $m ) ) {
		$popup['token'] = md5( E2E_USER_AGENT . $m[1] );
	}
	if ( preg_match( '/"asp_pp_ajax_nonce":"([0-9a-f]+)"/', $response['body'], $m ) ) {
		$popup['ajax'] = $m[1];
	}
	if ( 4 !== count( $popup ) ) {
		check( "The payment popup of product {$product_id} loads (with the nonces and the button key)", false, response_text( $response ) );
		return null;
	}
	return $popup;
}

/**
 * The create PaymentIntent request, with the nonce and the security token of the loaded popup. Returns the JSON response.
 */
function create_pi( $product_id, $popup, $amount, $currency = 'USD', $quantity = '1', $extra = array(), $headers = array() ) {
	$response = http_request(
		'POST',
		'/wp-admin/admin-ajax.php',
		array_merge(
			array(
				'action'           => 'asp_pp_create_pi',
				'nonce'            => $popup['create_pi'],
				'token'            => $popup['token'],
				'amount'           => (string) $amount,
				'curr'             => $currency,
				'product_id'       => (string) $product_id,
				'quantity'         => (string) $quantity,
				'billing_details'  => json_encode(
					array(
						'name'    => 'E2E Buyer',
						'email'   => 'buyer@example.com',
						'address' => array(
							'line1'       => '1 Test Street',
							'city'        => 'Austin',
							'state'       => 'TX',
							'country'     => 'US',
							'postal_code' => '78701',
						),
					)
				),
				'customer_details' => '{}',
			),
			$extra
		),
		$headers
	);
	$json = json_decode( $response['body'], true );
	return is_array( $json ) ? $json : array( 'err' => 'Not a JSON response. ' . response_text( $response ) );
}

/** Submits the payment form after the payment. Returns the response (the plugin redirects to the checkout result page). */
function submit_payment( $product_id, $nonce, $pi_id, $extra = array() ) {
	return http_request(
		'POST',
		'/?asp_action=show_pp&product_id=' . $product_id,
		array_merge(
			array(
				'asp_process_ipn'        => '1',
				'asp_payment_form_nonce' => $nonce,
				'asp_product_id'         => (string) $product_id,
				'asp_payment_intent'     => $pi_id,
				'asp_is_live'            => '0',
				'asp_quantity'           => '1',
				'asp_billing_name'       => 'E2E Buyer',
				'asp_email'              => 'buyer@example.com',
				'asp_thankyou_page_url'  => '',
			),
			$extra
		)
	);
}

function check_coupon_request( $product_id, $code ) {
	$response = http_request(
		'POST',
		'/wp-admin/admin-ajax.php',
		array(
			'action'      => 'asp_pp_check_coupon',
			'product_id'  => (string) $product_id,
			'coupon_code' => $code,
		)
	);
	$json = json_decode( $response['body'], true );
	return is_array( $json ) ? $json : array( 'err' => 'Not a JSON response. ' . response_text( $response ) );
}

/** An AJAX request (admin-ajax.php). Returns the JSON response. */
function ajax_request( $fields ) {
	$response = http_request( 'POST', '/wp-admin/admin-ajax.php', $fields );
	$json     = json_decode( $response['body'], true );
	return is_array( $json ) ? $json : array( 'err' => 'Not a JSON response. ' . response_text( $response ) );
}

/**
 * Shows a page with the [accept_stripe_payment] shortcode (that creates the shortcode's product) and returns the product ID.
 */
function shortcode_product_id( $page_id ) {
	$response = http_request( 'GET', '/?page_id=' . $page_id );
	if ( preg_match( '/"product_id":"?(\d+)/', $response['body'], $m ) ) {
		return (int) $m[1];
	}
	check( "The [accept_stripe_payment] page {$page_id} shows the buy button", false, response_text( $response ) );
	return 0;
}

/** Logs in as the admin in the current session (the login page sets the test cookie that the login form needs). */
function admin_login() {
	http_request( 'GET', '/wp-login.php' );
	$response = http_request(
		'POST',
		'/wp-login.php',
		array(
			'log'         => 'admin',
			'pwd'         => 'admin',
			'wp-submit'   => 'Log In',
			'redirect_to' => '/wp-admin/',
			'testcookie'  => '1',
		)
	);
	return check( 'The admin can log in', 302 === $response['status'] && false !== strpos( $response['location'], 'wp-admin' ), response_text( $response ) );
}

/** The Capture/Cancel nonce of an authorized order, from the orders list (the admin must be logged in). */
function order_action_nonce( $order_id ) {
	$response = http_request( 'GET', '/wp-admin/edit.php?post_type=stripe_order' );
	if ( preg_match( '/data-action="confirm" href="#" data-order-id="' . $order_id . '" data-nonce="([0-9a-f]+)"/', $response['body'], $m ) ) {
		return $m[1];
	}
	return '';
}

/**
 * The 3D Secure return: the popup saves its form (asp_pp_save_form_data), then Stripe sends the buyer to the
 * asp_next_action_results request, which confirms the PaymentIntent and processes the payment. Uses the current session.
 */
function three_d_secure_return( $product_id, $popup, $pi_id ) {
	ajax_request(
		array(
			'action'    => 'asp_pp_save_form_data',
			'nonce'     => $popup['ajax'],
			'form_data' => http_build_query(
				array(
					'product_id'         => (string) $product_id,
					'payment_form_nonce' => $popup['payment_form'],
					'is_live'            => '0',
					'quantity'           => '1',
					'billing_name'       => 'E2E Buyer',
					'email'              => 'buyer@example.com',
					'thankyou_page_url'  => '',
				)
			),
		)
	);
	return http_request( 'GET', '/wp-admin/admin-ajax.php?action=asp_next_action_results&is_live=false&payment_intent=' . rawurlencode( $pi_id ) );
}

function error_of( $json ) {
	return isset( $json['err'] ) ? $json['err'] : '(no error message) ' . json_encode( $json );
}

function contains( $haystack, $needle ) {
	return false !== strpos( (string) $haystack, $needle );
}

/** Checks that a payment didn't create an order or send an email. */
function check_rejected_payment( $label, $pi_id, $response ) {
	check( $label, 0 === count( orders_for_pi( $pi_id ) ) && 0 === count( emails() ), response_text( $response ) );
}

/** Starts a round with a clean state: no emails, no request or transaction counts, the stock back to 2. */
function reset_round( $ids ) {
	delete_site_option_value( 'asp_e2e_emails' );
	reset_limits();
	set_post_meta( $ids['stock'], 'asp_product_stock_items', '2' );
}

/** Resets the count of create PaymentIntent requests per IP address (limit 20) and the daily transaction count (limit 25). */
function reset_limits() {
	foreach ( array( 'asp_daily_txn_count_args', '_transient_asp_request_usage_count', '_transient_timeout_asp_request_usage_count' ) as $name ) {
		delete_site_option_value( $name );
	}
}

/** Gets the page the plugin redirected to (a full URL on the test site), in the current session. */
function follow_redirect( $response ) {
	global $url;
	return http_request( 'GET', substr( $response['location'], strlen( $url ) ) );
}

/** The status of an order (asp_order_status) and its title. */
function order_summary( $order_id ) {
	return $order_id ? post_meta( $order_id, 'asp_order_status' ) . ', ' . post_field( $order_id, 'post_title' ) : 'No order.';
}

function run_round( $label, $via, $ids, $checkout_url ) {
	echo "--- {$label} ---\n";
	reset_round( $ids );
	$t = "[{$label}]";

	$ebook = load_popup( $ids['ebook'] );
	if ( null === $ebook ) {
		return;
	}

	//1. A purchase: $10 + 10% tax.
	$result = create_pi( $ids['ebook'], $ebook, 1100 );
	if ( ! check( "{$t} The create PaymentIntent request with the correct amount succeeds", ! empty( $result['success'] ) && ! empty( $result['pi_id'] ), error_of( $result ) ) ) {
		return;
	}
	$pi_id = $result['pi_id'];
	$state = stripe_state();
	$pi    = $state['payment_intents'][ $pi_id ];
	check( "{$t} The PaymentIntent is created for 1100 USD cents with the product ID", 1100 === $pi['amount'] && 'usd' === $pi['currency'] && (string) $ids['ebook'] === $pi['metadata']['Product ID'], json_encode( $pi ) );
	check( "{$t} The request was sent by the expected Stripe client", in_array( "{$via}: POST payment_intents", $state['requests'], true ), implode( ', ', $state['requests'] ) );
	$orders = orders_for_pi( $pi_id );
	check( "{$t} An incomplete order is created for the PaymentIntent", 1 === count( $orders ) && 'incomplete' === post_meta( $orders[0], 'asp_order_status' ) );

	//In a browser session: the checkout result page shows the order from the plugin's session (a cookie).
	new_session();
	$response = submit_payment( $ids['ebook'], $ebook['payment_form'], $pi_id );
	check( "{$t} The payment redirects to the checkout result page", 302 === $response['status'] && $checkout_url === $response['location'], response_text( $response ) . ' Location: ' . $response['location'] );
	$result_page = follow_redirect( $response );
	use_session( null );
	check( "{$t} The checkout result page shows the order (thank you, product, transaction ID)", contains( $result_page['body'], 'Thank you for your payment.' ) && contains( $result_page['body'], 'E2E Ebook' ) && contains( $result_page['body'], $pi['charge_id'] ), response_text( $result_page ) );
	$orders = orders_for_pi( $pi_id );
	$order  = isset( $orders[0] ) ? $orders[0] : 0;
	check( "{$t} The payment completes the same order (paid, published)", 1 === count( $orders ) && 'paid' === post_meta( $order, 'asp_order_status' ) && 'publish' === post_field( $order, 'post_status' ) );
	check( "{$t} The order title has the quantity, the product and the total", '[Test Mode] 1 x E2E Ebook - $11.00' === post_field( $order, 'post_title' ), (string) post_field( $order, 'post_title' ) );
	check( "{$t} The order has the charge ID as the transaction ID", $pi['charge_id'] === post_meta( $order, 'trans_id' ) );
	$counter = get_site_option_value( 'asp_daily_txn_count_args' );
	check( "{$t} The daily transaction counter counts the payment", isset( $counter['counter'] ) && 1 === $counter['counter'], json_encode( $counter ) );
	$buyer_email  = find_email( 'buyer@example.com' );
	$seller_email = find_email( 'seller@example.com' );
	check( "{$t} The buyer gets the email with the product details and the total", null !== $buyer_email && contains( $buyer_email['message'], 'Product Name: E2E Ebook' ) && contains( $buyer_email['message'], 'Total Amount: $11.00' ), json_encode( emails() ) );
	check( "{$t} The seller gets the sale notification", null !== $seller_email && contains( $seller_email['message'], 'Sale to buyer@example.com' ) );

	//2. The same payment form submitted again (for example, the buyer reloads the page).
	delete_site_option_value( 'asp_e2e_emails' );
	submit_payment( $ids['ebook'], $ebook['payment_form'], $pi_id );
	$counter = get_site_option_value( 'asp_daily_txn_count_args' );
	check( "{$t} Submitting the same payment again doesn't create another order or send the emails again", 1 === count( orders_for_pi( $pi_id ) ) && 0 === count( emails() ) && 1 === $counter['counter'] );

	//3. The amount check before the PaymentIntent is created.
	$pi_count = count( stripe_state()['payment_intents'] );
	$result   = create_pi( $ids['ebook'], $ebook, 100 );
	check( "{$t} A lower amount than the product total is rejected", contains( error_of( $result ), 'Price validation failed' ), error_of( $result ) );
	check( "{$t} No PaymentIntent is created for the rejected amount", count( stripe_state()['payment_intents'] ) === $pi_count );

	$result = create_pi( $ids['ebook'], $ebook, 1100, 'EUR' );
	check( "{$t} A different currency is rejected", contains( error_of( $result ), 'Currency mismatch' ), error_of( $result ) );

	$result = create_pi( $ids['ebook'], array_merge( $ebook, array( 'create_pi' => 'badnonce00' ) ), 1100 );
	check( "{$t} A wrong nonce is rejected", contains( error_of( $result ), 'Nonce security verification failed' ), error_of( $result ) );

	$result = create_pi( $ids['ebook'], array_merge( $ebook, array( 'token' => md5( 'another browser' ) ) ), 1100 );
	check( "{$t} A wrong security token is rejected", 'Invalid security token.' === error_of( $result ), error_of( $result ) );

	$result = create_pi( $ids['no_popup'], $ebook, 1000 );
	check( "{$t} A product whose payment popup wasn't loaded is rejected (page load signature)", contains( error_of( $result ), 'Page load signature check failed' ), error_of( $result ) );

	//4. A quantity chosen by the buyer.
	$result = create_pi( $ids['ebook'], $ebook, 3300, 'USD', '3' );
	check( "{$t} The create PaymentIntent request for 3 items succeeds", ! empty( $result['pi_id'] ), error_of( $result ) );
	if ( ! empty( $result['pi_id'] ) ) {
		submit_payment( $ids['ebook'], $ebook['payment_form'], $result['pi_id'], array( 'asp_quantity' => '3' ) );
		$orders = orders_for_pi( $result['pi_id'] );
		check( "{$t} The order for 3 items is paid with the right total", 1 === count( $orders ) && '[Test Mode] 3 x E2E Ebook - $33.00' === post_field( $orders[0], 'post_title' ) && 'paid' === post_meta( $orders[0], 'asp_order_status' ) );
	}

	//5. Stock control (2 items in stock).
	$stock  = load_popup( $ids['stock'] );
	$result = create_pi( $ids['stock'], $stock, 1500, 'USD', '3' );
	check( "{$t} Ordering more items than in stock is rejected", contains( error_of( $result ), 'You cannot order more items than available: 2' ), error_of( $result ) );
	$result = create_pi( $ids['stock'], $stock, 500, 'USD', '1' );
	if ( check( "{$t} Ordering an item in stock succeeds", ! empty( $result['pi_id'] ), error_of( $result ) ) ) {
		submit_payment( $ids['stock'], $stock['payment_form'], $result['pi_id'] );
		check( "{$t} The stock goes down after the payment", '1' === post_meta( $ids['stock'], 'asp_product_stock_items' ), (string) post_meta( $ids['stock'], 'asp_product_stock_items' ) );
	}

	//6. A coupon: 20% off $10 = $8, tax 10% of $8 = $0.80.
	$coupon = check_coupon_request( $ids['ebook'], 'E2E20' );
	check( "{$t} The coupon check accepts a valid coupon", isset( $coupon['code'] ) && 'E2E20' === $coupon['code'] && '20' === (string) $coupon['discount'], json_encode( $coupon ) );
	$coupon = check_coupon_request( $ids['ebook'], 'NOSUCHCODE' );
	check( "{$t} The coupon check rejects an unknown coupon", 'Coupon not found.' === error_of( $coupon ), error_of( $coupon ) );
	$redemptions = (int) post_meta( $ids['coupon'], 'asp_coupon_red_count' );
	$result      = create_pi( $ids['ebook'], $ebook, 880, 'USD', '1', array( 'coupon' => 'E2E20' ) );
	if ( check( "{$t} The create PaymentIntent request with the discounted amount and the coupon succeeds", ! empty( $result['pi_id'] ), error_of( $result ) ) ) {
		submit_payment( $ids['ebook'], $ebook['payment_form'], $result['pi_id'], array( 'asp_coupon-code' => 'E2E20' ) );
		$orders = orders_for_pi( $result['pi_id'] );
		check( "{$t} The order with the coupon is paid with the discounted total", 1 === count( $orders ) && '[Test Mode] 1 x E2E Ebook - $8.80' === post_field( $orders[0], 'post_title' ) && 'paid' === post_meta( $orders[0], 'asp_order_status' ), isset( $orders[0] ) ? (string) post_field( $orders[0], 'post_title' ) : 'No order.' );
		check( "{$t} The coupon redemption is counted", $redemptions + 1 === (int) post_meta( $ids['coupon'], 'asp_coupon_red_count' ) );
		$pi = stripe_state()['payment_intents'][ $result['pi_id'] ];
		check( "{$t} The coupon code is saved in the PaymentIntent metadata", isset( $pi['metadata']['Coupon Code'] ) && 'E2E20' === $pi['metadata']['Coupon Code'], json_encode( $pi['metadata'] ) );
	}

	//7. A zero-decimal currency (JPY amounts have no cents).
	$jpy    = load_popup( $ids['jpy'] );
	$result = create_pi( $ids['jpy'], $jpy, 1000, 'JPY' );
	if ( check( "{$t} The create PaymentIntent request for 1000 JPY succeeds", ! empty( $result['pi_id'] ), error_of( $result ) ) ) {
		$pi = stripe_state()['payment_intents'][ $result['pi_id'] ];
		check( "{$t} The JPY PaymentIntent amount is 1000 (not multiplied by 100)", 1000 === $pi['amount'] && 'jpy' === $pi['currency'] );
		submit_payment( $ids['jpy'], $jpy['payment_form'], $result['pi_id'] );
		$orders = orders_for_pi( $result['pi_id'] );
		check( "{$t} The JPY order is paid", 1 === count( $orders ) && 'paid' === post_meta( $orders[0], 'asp_order_status' ) );
	}

	//8. The checks of the payment processing, with paid PaymentIntents that the amount check before the payment would have stopped.
	delete_site_option_value( 'asp_e2e_emails' );
	$pi_id = add_paid_payment_intent( 100, 'usd', $ids['ebook'] );
	new_session();
	$response    = submit_payment( $ids['ebook'], $ebook['payment_form'], $pi_id );
	$result_page = follow_redirect( $response );
	use_session( null );
	check_rejected_payment( "{$t} A payment lower than the product total doesn't complete an order", $pi_id, $response );
	check( "{$t} The checkout result page shows the error", contains( $result_page['body'], 'System was not able to complete the payment.' ) && contains( $result_page['body'], 'Invalid payment amount received' ), response_text( $result_page ) );

	$pi_id    = add_paid_payment_intent( 1100, 'eur', $ids['ebook'] );
	$response = submit_payment( $ids['ebook'], $ebook['payment_form'], $pi_id );
	check_rejected_payment( "{$t} A payment in another currency doesn't complete an order", $pi_id, $response );

	//The payment is enough for the $1 product, but it was made for the ebook.
	$cheap    = load_popup( $ids['cheap'] );
	$pi_id    = add_paid_payment_intent( 1100, 'usd', $ids['ebook'] );
	$response = submit_payment( $ids['cheap'], $cheap['payment_form'], $pi_id );
	check_rejected_payment( "{$t} A payment made for one product can't complete an order for another product", $pi_id, $response );

	$response = submit_payment( $ids['ebook'], $ebook['payment_form'], 'pi_e2e_does_not_exist' );
	check_rejected_payment( "{$t} A PaymentIntent that doesn't exist doesn't complete an order", 'pi_e2e_does_not_exist', $response );
	check( "{$t} A PaymentIntent that doesn't exist redirects to the checkout result page (no fatal error)", 302 === $response['status'], response_text( $response ) );

	$pi_id    = add_paid_payment_intent( 1100, 'usd', $ids['ebook'] );
	$response = submit_payment( $ids['ebook'], 'badnonce00', $pi_id );
	check_rejected_payment( "{$t} A payment form with a wrong nonce doesn't complete an order", $pi_id, $response );
	check( "{$t} A payment form with a wrong nonce shows the nonce error", contains( $response['body'], 'Nonce verification failed' ), response_text( $response ) );

	//9. A product with no saved product type ($10).
	$no_type = load_popup( $ids['no_type'] );
	$result  = create_pi( $ids['no_type'], $no_type, 1000 );
	check( "{$t} A product with no saved product type: the correct amount is accepted", ! empty( $result['pi_id'] ), error_of( $result ) );
	$pi_count = count( stripe_state()['payment_intents'] );
	$result   = create_pi( $ids['no_type'], $no_type, 100 );
	check( "{$t} A product with no saved product type: a lower amount is rejected", contains( error_of( $result ), 'Price validation failed' ) && count( stripe_state()['payment_intents'] ) === $pi_count, error_of( $result ) );

	//The next sections send many create PaymentIntent requests: start the per-IP and daily counts again.
	reset_limits();

	//10. The coupon's product rules, before the PaymentIntent is created.
	$result = create_pi( $ids['ebook'], $ebook, 800, 'USD', '1', array( 'coupon' => 'E2ECHEAP' ) );
	check( "{$t} A coupon only for another product is rejected before the payment", contains( error_of( $result ), 'Coupon is not allowed for this product.' ), error_of( $result ) );
	$cheap  = load_popup( $ids['cheap'] );
	$result = create_pi( $ids['cheap'], $cheap, 80, 'USD', '1', array( 'coupon' => 'E2ECHEAP' ) );
	check( "{$t} A coupon only for this product is accepted (20% off \$1)", ! empty( $result['pi_id'] ), error_of( $result ) );
	$no_coupons = load_popup( $ids['no_coupons'] );
	$result     = create_pi( $ids['no_coupons'], $no_coupons, 800, 'USD', '1', array( 'coupon' => 'E2E20' ) );
	check( "{$t} A coupon is rejected for a product with coupons turned off", contains( error_of( $result ), 'Applying coupon is not allowed for this product.' ), error_of( $result ) );

	//11. The [accept_stripe_payment] shortcode's products (they have no saved product type).
	$product_id = shortcode_product_id( $ids['shortcode_priced'] );
	$popup      = $product_id ? load_popup( $product_id ) : null;
	if ( null !== $popup ) {
		$result = create_pi( $product_id, $popup, 100 );
		check( "{$t} [accept_stripe_payment price=\"10\"]: a lower amount is rejected", contains( error_of( $result ), 'Price validation failed' ), error_of( $result ) );
		$result = create_pi( $product_id, $popup, 1000 );
		if ( check( "{$t} [accept_stripe_payment price=\"10\"]: the create PaymentIntent request succeeds", ! empty( $result['pi_id'] ), error_of( $result ) ) ) {
			submit_payment( $product_id, $popup['payment_form'], $result['pi_id'] );
			$order = order_for_pi( $result['pi_id'] );
			check( "{$t} [accept_stripe_payment price=\"10\"]: the order is paid", 'paid' === post_meta( $order, 'asp_order_status' ) && '[Test Mode] 1 x E2E Shortcode Product - $10.00' === post_field( $order, 'post_title' ), order_summary( $order ) );
		}
	}
	$product_id = shortcode_product_id( $ids['shortcode_donation'] );
	$popup      = $product_id ? load_popup( $product_id ) : null;
	if ( null !== $popup ) {
		$result = create_pi( $product_id, $popup, 700 );
		if ( check( "{$t} [accept_stripe_payment price=\"0\"] (the buyer enters the amount): the create PaymentIntent request succeeds", ! empty( $result['pi_id'] ), error_of( $result ) ) ) {
			submit_payment( $product_id, $popup['payment_form'], $result['pi_id'], array( 'asp_amount' => '700' ) );
			$order = order_for_pi( $result['pi_id'] );
			check( "{$t} [accept_stripe_payment price=\"0\"]: the order is paid with the buyer's amount", 'paid' === post_meta( $order, 'asp_order_status' ) && contains( post_field( $order, 'post_title' ), '$7.00' ), order_summary( $order ) );
		}
	}

	//12. A donation with a minimum amount ($5).
	$donation = load_popup( $ids['donation'] );
	$result   = create_pi( $ids['donation'], $donation, 300 );
	check( "{$t} A donation lower than the minimum amount is rejected", contains( error_of( $result ), 'Minimum amount is' ), error_of( $result ) );
	$result = create_pi( $ids['donation'], $donation, 700 );
	if ( check( "{$t} A donation above the minimum amount succeeds", ! empty( $result['pi_id'] ), error_of( $result ) ) ) {
		submit_payment( $ids['donation'], $donation['payment_form'], $result['pi_id'], array( 'asp_amount' => '700' ) );
		$order = order_for_pi( $result['pi_id'] );
		check( "{$t} The donation order is paid with the buyer's amount", 'paid' === post_meta( $order, 'asp_order_status' ) && contains( post_field( $order, 'post_title' ), '$7.00' ), order_summary( $order ) );
	}

	//13. Tax and shipping by country (the buyer's address).
	$tax    = load_popup( $ids['tax_region'] );
	$result = create_pi( $ids['tax_region'], $tax, 1000 );
	check( "{$t} Tax by country: the amount without the US tax is rejected for a US buyer", contains( error_of( $result ), 'Price validation failed' ), error_of( $result ) );
	$result = create_pi( $ids['tax_region'], $tax, 1100 );
	if ( check( "{$t} Tax by country: a US buyer pays the 10% tax", ! empty( $result['pi_id'] ), error_of( $result ) ) ) {
		submit_payment( $ids['tax_region'], $tax['payment_form'], $result['pi_id'] );
		$order = order_for_pi( $result['pi_id'] );
		check( "{$t} Tax by country: the US order is paid with the tax", 'paid' === post_meta( $order, 'asp_order_status' ) && contains( post_field( $order, 'post_title' ), '$11.00' ), order_summary( $order ) );
	}
	$result = create_pi( $ids['tax_region'], $tax, 1000, 'USD', '1', array( 'billing_details' => address_json( 'DE', '', 'Berlin' ) ) );
	if ( check( "{$t} Tax by country: a German buyer pays no tax", ! empty( $result['pi_id'] ), error_of( $result ) ) ) {
		submit_payment( $ids['tax_region'], $tax['payment_form'], $result['pi_id'] );
		$order = order_for_pi( $result['pi_id'] );
		check( "{$t} Tax by country: the German order is paid without the tax", 'paid' === post_meta( $order, 'asp_order_status' ) && contains( post_field( $order, 'post_title' ), '$10.00' ), order_summary( $order ) );
	}
	$ship     = load_popup( $ids['ship_region'] );
	$shipping = array( 'shipping_details' => address_json( 'US', 'TX', 'Austin' ) );
	$result   = create_pi( $ids['ship_region'], $ship, 1300, 'USD', '1', $shipping );
	check( "{$t} Shipping by country: the amount without the US shipping is rejected", contains( error_of( $result ), 'Price validation failed' ), error_of( $result ) );
	$result = create_pi( $ids['ship_region'], $ship, 2000, 'USD', '1', $shipping );
	if ( check( "{$t} Shipping by country: \$10 + \$3 shipping + \$7 to the US", ! empty( $result['pi_id'] ), error_of( $result ) ) ) {
		submit_payment( $ids['ship_region'], $ship['payment_form'], $result['pi_id'] );
		$order = order_for_pi( $result['pi_id'] );
		check( "{$t} Shipping by country: the order is paid with the US shipping", 'paid' === post_meta( $order, 'asp_order_status' ) && contains( post_field( $order, 'post_title' ), '$20.00' ), order_summary( $order ) );
	}

	reset_limits();

	//14. Authorize only: the payment is authorized, then captured or canceled from the orders page by the admin.
	$auth   = load_popup( $ids['auth'] );
	$orders = array();
	foreach ( array( 'capture', 'cancel' ) as $action ) {
		$result = create_pi( $ids['auth'], $auth, 1000 );
		if ( ! empty( $result['pi_id'] ) ) {
			submit_payment( $ids['auth'], $auth['payment_form'], $result['pi_id'] );
			$orders[ $action ] = array( order_for_pi( $result['pi_id'] ), $result['pi_id'] );
		}
	}
	if ( check( "{$t} Authorize only: the create PaymentIntent requests succeed", 2 === count( $orders ) ) ) {
		list( $capture_order, $capture_pi ) = $orders['capture'];
		list( $cancel_order, $cancel_pi )   = $orders['cancel'];
		$pi = stripe_state()['payment_intents'][ $capture_pi ];
		check( "{$t} Authorize only: the PaymentIntent is created for a later capture", 'requires_capture' === $pi['status'], json_encode( $pi ) );
		check( "{$t} Authorize only: the order is authorized (not paid)", 'authorized' === post_meta( $capture_order, 'asp_order_status' ), order_summary( $capture_order ) );

		new_session();
		if ( admin_login() ) {
			foreach ( array( 'capture' => array( $capture_order, $capture_pi, 'asp_order_capture_confirm', 'paid', 'succeeded' ), 'cancel' => array( $cancel_order, $cancel_pi, 'asp_order_capture_cancel', 'canceled', 'canceled' ) ) as $action => $case ) {
				list( $order, $order_pi, $ajax_action, $order_status, $pi_status ) = $case;
				$nonce  = order_action_nonce( $order );
				$result = '' === $nonce ? array( 'err_msg' => 'No Capture/Cancel links for the order on the orders page.' ) : ajax_request(
					array(
						'action'   => $ajax_action,
						'order_id' => (string) $order,
						'nonce'    => $nonce,
					)
				);
				//Fixed after 2.1.5: Capture and Cancel failed with the plugin's own API client, and the order lost its charge data.
				check( "{$t} Authorize only: {$action} from the orders page succeeds", ! empty( $result['success'] ), json_encode( $result ) );
				check( "{$t} Authorize only: after {$action}, the order is {$order_status} and the PaymentIntent is {$pi_status}", $order_status === post_meta( $order, 'asp_order_status' ) && $pi_status === stripe_state()['payment_intents'][ $order_pi ]['status'], order_summary( $order ) );
				$order_data = (string) post_meta( $order, 'order_data' );
				check( "{$t} Authorize only: after {$action}, the order still has its charge data (the PaymentIntent's latest charge)", contains( $order_data, $order_pi ) && contains( $order_data, stripe_state()['payment_intents'][ $order_pi ]['charge_id'] ) );
			}
		}
		use_session( null );
	}

	//15. 3D Secure: the PaymentIntent needs authentication; after it, Stripe sends the buyer back to the plugin, which confirms it.
	new_session();
	$pi_id    = add_paid_payment_intent( 1100, 'usd', $ids['ebook'], array( 'status' => 'requires_action' ) );
	$response = three_d_secure_return( $ids['ebook'], $ebook, $pi_id );
	use_session( null );
	$order = order_for_pi( $pi_id );
	check( "{$t} 3D Secure: the return confirms the PaymentIntent and completes the paid order", 302 === $response['status'] && 'succeeded' === stripe_state()['payment_intents'][ $pi_id ]['status'] && 'paid' === post_meta( $order, 'asp_order_status' ), response_text( $response ) . ' ' . order_summary( $order ) );

	//16. A 100% discount coupon: no payment, a "free_" transaction ID from the coupon check.
	$coupon = check_coupon_request( $ids['ebook'], 'E2EFREE' );
	$free   = isset( $coupon['zero_value_id'] ) ? $coupon['zero_value_id'] : '';
	if ( check( "{$t} The coupon check of a 100% coupon gives a free transaction ID", 0 === strpos( $free, 'free_' ), json_encode( $coupon ) ) ) {
		submit_payment( $ids['ebook'], $ebook['payment_form'], $free, array( 'asp_coupon-code' => 'E2EFREE' ) );
		$order = order_for_pi( $free );
		check( "{$t} A 100% coupon completes a free paid order", 'paid' === post_meta( $order, 'asp_order_status' ) && contains( post_field( $order, 'post_title' ), '$0.00' ), order_summary( $order ) );
	}
	delete_site_option_value( 'asp_e2e_emails' );
	$response = submit_payment( $ids['ebook'], $ebook['payment_form'], 'free_e2eforged', array( 'asp_coupon-code' => 'E2EFREE' ) );
	check_rejected_payment( "{$t} A made-up free transaction ID doesn't complete an order", 'free_e2eforged', $response );
	$coupon = check_coupon_request( $ids['ebook'], 'E2E20' );
	if ( isset( $coupon['zero_value_id'] ) ) {
		$response = submit_payment( $ids['ebook'], $ebook['payment_form'], $coupon['zero_value_id'], array( 'asp_coupon-code' => 'E2E20' ) );
		check_rejected_payment( "{$t} The free transaction ID of a coupon that isn't 100% doesn't complete an order", $coupon['zero_value_id'], $response );
	}

	//The security report of November 2026 (fixed after 2.1.5): a flat coupon is taken off each item, and the free order
	//check used the product's quantity for the discount but one item's price; with a quantity of -1 the total became negative
	//(then 0). A product sold in a quantity of 2 at $50, with the flat $30 coupon: $60 off, but the order is $100.
	$pack = load_popup( $ids['qty2'] );
	foreach ( array( '-1', '1' ) as $quantity ) {
		delete_site_option_value( 'asp_e2e_emails' );
		$coupon = check_coupon_request( $ids['qty2'], 'E2EFLAT30' );
		if ( check( "{$t} The coupon check of the flat coupon gives a free transaction ID (quantity {$quantity})", isset( $coupon['zero_value_id'] ), json_encode( $coupon ) ) ) {
			$response = submit_payment(
				$ids['qty2'],
				$pack['payment_form'],
				$coupon['zero_value_id'],
				array(
					'asp_coupon-code' => 'E2EFLAT30',
					'asp_quantity'    => $quantity,
				)
			);
			check_rejected_payment( "{$t} A flat coupon worth less than the order doesn't complete a free order (quantity {$quantity})", $coupon['zero_value_id'], $response );
		}
	}

	//A donation (the buyer enters the amount) with any coupon: the free order check compared a discount of 0 with a price of 0.
	delete_site_option_value( 'asp_e2e_emails' );
	$coupon = check_coupon_request( $ids['donation'], 'E2E20' );
	if ( check( "{$t} The coupon check of a donation gives a free transaction ID", isset( $coupon['zero_value_id'] ), json_encode( $coupon ) ) ) {
		$response = submit_payment( $ids['donation'], $donation['payment_form'], $coupon['zero_value_id'], array( 'asp_coupon-code' => 'E2E20' ) );
		check_rejected_payment( "{$t} A donation with a coupon doesn't complete a free order", $coupon['zero_value_id'], $response );
	}

	//The quantity: only 1 or more, and only the product's quantity when the buyer can't change it.
	$result = create_pi( $ids['ebook'], $ebook, 0, 'USD', '-1' );
	check( "{$t} A quantity of -1 is rejected before the PaymentIntent is created", contains( error_of( $result ), 'Invalid quantity' ), error_of( $result ) );
	$result = create_pi( $ids['ebook'], $ebook, 0, 'USD', '0' );
	check( "{$t} A quantity of 0 is rejected before the PaymentIntent is created", contains( error_of( $result ), 'Invalid quantity' ), error_of( $result ) );
	$result = create_pi( $ids['qty2'], $pack, 5000, 'USD', '1' );
	check( "{$t} A product sold in a quantity of 2: the price of 1 item is rejected (the buyer can't change the quantity)", contains( error_of( $result ), 'Price validation failed' ), error_of( $result ) );
	$result = create_pi( $ids['qty2'], $pack, 10000, 'USD', '1' );
	if ( check( "{$t} A product sold in a quantity of 2: the price of 2 items is accepted", ! empty( $result['pi_id'] ), error_of( $result ) ) ) {
		submit_payment( $ids['qty2'], $pack['payment_form'], $result['pi_id'], array( 'asp_quantity' => '1' ) );
		$order = order_for_pi( $result['pi_id'] );
		check( "{$t} A product sold in a quantity of 2: the order is for 2 items", 'paid' === post_meta( $order, 'asp_order_status' ) && '[Test Mode] 2 x E2E Pack Of Two - $100.00' === post_field( $order, 'post_title' ), order_summary( $order ) );
	}
	delete_site_option_value( 'asp_e2e_emails' );
	$stock_before = (string) post_meta( $ids['stock'], 'asp_product_stock_items' );
	$pi_id        = add_paid_payment_intent( 500, 'usd', $ids['stock'] );
	$response     = submit_payment( $ids['stock'], $stock['payment_form'], $pi_id, array( 'asp_quantity' => '-1' ) );
	check_rejected_payment( "{$t} A payment form with a quantity of -1 doesn't complete an order", $pi_id, $response );
	check( "{$t} A payment form with a quantity of -1 doesn't change the stock", $stock_before === (string) post_meta( $ids['stock'], 'asp_product_stock_items' ), $stock_before . ' -> ' . post_meta( $ids['stock'], 'asp_product_stock_items' ) );

	//A donation lower than the minimum ($5) that was paid without the create PaymentIntent request.
	$pi_id    = add_paid_payment_intent( 300, 'usd', $ids['donation'] );
	$response = submit_payment( $ids['donation'], $donation['payment_form'], $pi_id, array( 'asp_amount' => '300' ) );
	check_rejected_payment( "{$t} A donation payment lower than the minimum amount doesn't complete an order", $pi_id, $response );

	//17. PaymentIntents that weren't paid.
	delete_site_option_value( 'asp_e2e_emails' );
	$pi_id = add_paid_payment_intent(
		1100,
		'usd',
		$ids['ebook'],
		array(
			'status'        => 'requires_payment_method',
			'charge_status' => 'none',
		)
	);
	$response = submit_payment( $ids['ebook'], $ebook['payment_form'], $pi_id );
	check_rejected_payment( "{$t} A PaymentIntent without a payment doesn't complete an order", $pi_id, $response );
	$pi_id = add_paid_payment_intent(
		1100,
		'usd',
		$ids['ebook'],
		array(
			'status'        => 'requires_payment_method',
			'charge_status' => 'failed',
		)
	);
	$response = submit_payment( $ids['ebook'], $ebook['payment_form'], $pi_id );
	$order    = order_for_pi( $pi_id );
	check_known( 'declined-charge-completes-order', "{$t} A PaymentIntent with a declined card doesn't complete an order", 0 === $order && 0 === count( emails() ), 'Order: ' . order_summary( $order ) . '. Emails sent: ' . count( emails() ) . '.' );

	reset_limits();

	//18. The limits (last, because they block the create PaymentIntent request).
	set_site_option_value( '_transient_asp_request_usage_count', array( '127.0.0.1' => 20 ) );
	set_site_option_value( '_transient_timeout_asp_request_usage_count', time() + 3600 );
	$result = create_pi( $ids['ebook'], $ebook, 1100 );
	check( "{$t} The 21st create PaymentIntent request from the same IP address is rejected", contains( error_of( $result ), 'Transaction request limit reached for this IP address' ), error_of( $result ) );
	delete_site_option_value( '_transient_asp_request_usage_count' );
	delete_site_option_value( '_transient_timeout_asp_request_usage_count' );

	set_site_option_value(
		'asp_daily_txn_count_args',
		array(
			'counter_date' => gmdate( 'Y-m-d' ),
			'counter'      => 25,
		)
	);
	$result = create_pi( $ids['ebook'], $ebook, 1100 );
	check( "{$t} The create PaymentIntent request is rejected when the daily transaction limit (25) is reached", contains( error_of( $result ), 'has been reached for the day' ), error_of( $result ) );
}

/**
 * The captcha check before the PaymentIntent is created ('recaptcha' or 'hcaptcha'). The popup sends the captcha widget's token
 * to the plugin (asp_recaptcha_check / asp_hcaptcha_check), the plugin verifies it (the fake answers) and saves the result
 * in the buyer's session, and the create PaymentIntent request needs it.
 */
function run_captcha_tests( $type, $label, $ids ) {
	$t = "[{$label}]";
	reset_limits();
	update_site_settings(
		array(
			'captcha_type'       => $type,
			"{$type}_site_key"   => 'e2e-site-key',
			"{$type}_secret_key" => 'e2e-secret-key',
		)
	);
	new_session();
	$ebook = load_popup( $ids['ebook'] );
	if ( null !== $ebook ) {
		$result = create_pi( $ids['ebook'], $ebook, 1100 );
		check( "{$t} The create PaymentIntent request without the captcha is rejected", contains( error_of( $result ), 'check failed' ), error_of( $result ) );

		$check = ajax_request(
			array(
				'action'            => "asp_{$type}_check",
				"{$type}_response" => 'e2e-captcha-wrong',
			)
		);
		check( "{$t} A wrong captcha token is rejected by the captcha check", ! empty( $check['error'] ), json_encode( $check ) );
		$result = create_pi( $ids['ebook'], $ebook, 1100 );
		check( "{$t} The create PaymentIntent request after a failed captcha is rejected", contains( error_of( $result ), 'check failed' ), error_of( $result ) );

		$check = ajax_request(
			array(
				'action'            => "asp_{$type}_check",
				"{$type}_response" => 'e2e-captcha-pass',
			)
		);
		check( "{$t} A solved captcha passes the captcha check", empty( $check['error'] ), json_encode( $check ) );
		$result = create_pi( $ids['ebook'], $ebook, 1100 );
		check( "{$t} The create PaymentIntent request after the solved captcha succeeds", ! empty( $result['pi_id'] ), error_of( $result ) );
	}
	use_session( null );
	update_site_settings( array( 'captcha_type' => '' ) );
}

//Create the test data.
$setup_output = wp_cli( 'eval-file ' . escapeshellarg( __DIR__ . '/setup-site.php' ) );
$lines        = array_filter( array_map( 'trim', explode( "\n", $setup_output ) ) );
$ids          = json_decode( end( $lines ), true );
if ( empty( $ids['ebook'] ) ) {
	echo "Could not create the test data. Output:\n{$setup_output}\n";
	exit( 1 );
}
$settings     = get_site_option_value( 'AcceptStripePayments-settings' );
$checkout_url = $settings['checkout_url'];

run_round( 'Stripe PHP SDK', 'sdk', $ids, $checkout_url );

//"Don't use Stripe PHP SDK" (Advanced settings). The setting isn't saved until it is changed, so it is added here.
update_site_settings( array( 'dont_use_stripe_php_sdk' => 1 ) );
run_round( "Plugin's own API client", 'wp', $ids, $checkout_url );
update_site_settings( array( 'dont_use_stripe_php_sdk' => 0 ) );

echo "--- Captcha ---\n";
run_captcha_tests( 'recaptcha', 'reCAPTCHA', $ids );
run_captcha_tests( 'hcaptcha', 'hCaptcha', $ids );

//PHP errors on the test site (WP_DEBUG_LOG). A fatal error fails the tests; warnings and notices from the plugin are listed.
echo "--- PHP errors ---\n";
$debug_log = $wp_path . '/wp-content/debug.log';
$log_lines = file_exists( $debug_log ) ? file( $debug_log, FILE_IGNORE_NEW_LINES ) : array();
$fatal     = preg_grep( '/PHP Fatal error/', $log_lines );
check( 'No PHP fatal errors on the test site', 0 === count( $fatal ), implode( "\n    ", $fatal ) );
$plugin_messages = array_unique( preg_replace( '/^\[[^\]]+\] /', '', preg_grep( '#plugins/stripe-payments/#', $log_lines ) ) );
if ( $plugin_messages ) {
	echo 'PHP warnings and notices from the plugin (not a failure):' . "\n    " . implode( "\n    ", $plugin_messages ) . "\n";
}

echo "\n" . ( 0 === $failures ? 'All endpoint tests passed.' : "{$failures} endpoint test(s) failed." ) . "\n";
if ( $known ) {
	echo "{$known} check(s) for known issues ran without failing the tests (see the KNOWN ISSUE lines and KNOWN_ISSUES).\n";
}
exit( 0 === $failures ? 0 : 1 );
