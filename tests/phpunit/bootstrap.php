<?php
/**
 * PHPUnit bootstrap for the Accept Stripe Payments plugin tests.
 *
 * It needs the WordPress test library (installed by tests/bin/install-wp-tests.sh) and a test database.
 * The GitHub Actions "Plugin Tests" workflow sets this up. WP_TESTS_DIR is the folder of the WordPress test library.
 */

require __DIR__ . '/vendor/autoload.php';

$_tests_dir = getenv( 'WP_TESTS_DIR' );
if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! defined( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH' ) ) {
	define( 'WP_TESTS_PHPUNIT_POLYFILLS_PATH', __DIR__ . '/vendor/yoast/phpunit-polyfills' );
}

if ( ! file_exists( "{$_tests_dir}/includes/functions.php" ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php. Run tests/bin/install-wp-tests.sh first." . PHP_EOL;
	exit( 1 );
}

//Gives access to tests_add_filter().
require_once "{$_tests_dir}/includes/functions.php";

//Load the plugin.
tests_add_filter(
	'muplugins_loaded',
	function () {
		require dirname( __DIR__, 2 ) . '/stripe-payments/accept-stripe-payments.php';
	}
);

//Then run what the plugin's activation does (save the default settings, create the checkout and products pages).
//It runs on 'init' priority 1, like a real activation that happens after WordPress is loaded: the plugin's post types
//are registered on 'init' priority 0, and its shortcodes on priority 10.
//Note: the test library deletes all posts after WordPress loads, so the pages created here are gone when the tests run
//(the settings stay). The smoke test runs the activation again to check the pages.
tests_add_filter(
	'init',
	function () {
		AcceptStripePayments::activate( false );
	},
	1
);

//Start the WordPress test environment.
require "{$_tests_dir}/includes/bootstrap.php";
