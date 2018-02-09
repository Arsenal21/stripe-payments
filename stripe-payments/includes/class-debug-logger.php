<?php

class ASP_Debug_Logger {

    function __construct() {
	
    }

    static function log($msg, $success = true, $addon_name = '', $overwrite = false) {
	$opts = get_option('AcceptStripePayments-settings');
	if (!$opts['debug_log_enable'] && !$overwrite) {
	    return true;
	}
	$log_file = get_option('asp_log_file_name');
	if (!$log_file) {
	    //let's generate new log file name
	    $log_file = uniqid() . '_debug_log.txt';
	    update_option('asp_log_file_name', $log_file);
	}

	$output = '';
	//Timestamp it
	$output .= '[' . date('m/d/Y g:i A') . '] - ';

	//Flag failure (if applicable)
	if (!$success) {
	    $output .= 'FAILURE: ';
	}

	//Add the addon's name (if applicable)
	if (!empty($addon_name)) {
	    $output .= '[' . $addon_name . '] ';
	}

	//Final debug output msg
	$output = $output . $msg;
	
	if (!file_put_contents(plugin_dir_path(__FILE__) . $log_file, $output . "\r\n", (!$overwrite ? FILE_APPEND : 0))) {
	    return false;
	}

	return true;
    }

    static function view_log() {
	$log_file = get_option('asp_log_file_name');
	if (!file_exists(plugin_dir_path(__FILE__) . $log_file)) {
	    if (ASP_Debug_Logger::log("Stripe Payments debug log file\r\n") === false) {
		wp_die('Can\'t write to log file. Check if plugin directory  (' . plugin_dir_path(__FILE__) . ') is writeable.');
	    };
	}
	$logfile = fopen(plugin_dir_path(__FILE__) . $log_file, 'rb');
	if (!$logfile) {
	    wp_die('Can\'t open log file.');
	}
	header('Content-Type: text/plain');
	fpassthru($logfile);
	die;
    }

    static function clear_log() {
	if (ASP_Debug_Logger::log("Stripe Payments debug log reset\r\n", true, '', true) !== false) {
	    echo '1';
	} else {
	    echo 'Can\'t clear log - log file is not writeable.';
	}
	wp_die();
    }

}