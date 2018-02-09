<?php

class ASP_Debug_Logger {

    function __construct() {
	
    }

    static function log( $msg, $success = true, $addon_name = '', $overwrite = false ) {
	$opts = get_option( 'AcceptStripePayments-settings' );
	if ( ! $opts[ 'debug_log_enable' ] && ! $overwrite ) {
	    return true;
	}
	$log_file = get_option( 'asp_log_file_name' );
	if ( ! $log_file ) {
	    //let's generate new log file name
	    $log_file = uniqid() . '_debug_log.txt';
	    update_option( 'asp_log_file_name', $log_file );
	}

	$output	 = '';
	//Timestamp it
	$output	 .= '[' . date( 'm/d/Y g:i A' ) . '] - ';

	//Add the addon's name (if applicable)
	if ( ! empty( $addon_name ) ) {
	    $output .= '[' . $addon_name . '] ';
	}

	//Flag failure (if applicable)
	if ( ! $success ) {
	    $output .= 'FAILURE: ';
	}

	//Final debug output msg
	$output = $output . $msg;

	if ( ! file_put_contents( WP_ASP_PLUGIN_PATH . $log_file, $output . "\r\n", ( ! $overwrite ? FILE_APPEND : 0 ) ) ) {
	    return false;
	}

	return true;
    }

    static function view_log() {
	$log_file = get_option( 'asp_log_file_name' );
	if ( ! file_exists( WP_ASP_PLUGIN_PATH . $log_file ) ) {
	    if ( ASP_Debug_Logger::log( "Stripe Payments debug log file\r\n" ) === false ) {
		wp_die( 'Can\'t write to log file. Check if plugin directory (' . WP_ASP_PLUGIN_PATH . ') is writeable.' );
	    };
	}
	$logfile = fopen( WP_ASP_PLUGIN_PATH . $log_file, 'rb' );
	if ( ! $logfile ) {
	    wp_die( 'Can\'t open log file.' );
	}
	header( 'Content-Type: text/plain' );
	fpassthru( $logfile );
	die;
    }

    static function clear_log() {
	if ( ASP_Debug_Logger::log( "Stripe Payments debug log reset\r\n", true, '', true ) !== false ) {
	    echo '1';
	} else {
	    echo 'Can\'t clear log - log file is not writeable.';
	}
	wp_die();
    }

}
