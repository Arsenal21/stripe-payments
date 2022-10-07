<?php

class ASP_Utils_Bot_Mitigation {

    public static function get_asp_hash_private_key_one() {
    	$hp_key = get_option( 'asp_hash_private_key_one' );
	if ( empty( $hp_key ) ) {
	    $hp_key = uniqid( '', true );
	    update_option( 'asp_hash_private_key_one', $hp_key );
	}
        return $hp_key;
    }
        
    public static function get_captcha_solve_ip_time_data() {
        $solved_ip_time_arr = get_transient('asp_captcha_solve_ip_time');
        if (!isset($solved_ip_time_arr) || empty($solved_ip_time_arr)) {
            $solved_ip_time_arr = array();
        }
        return $solved_ip_time_arr;
    }

    public static function record_captcha_solve_ip_time_data() {
        $captcha_solve_ip = ASP_Utils::get_user_ip_address();
        $current_wp_time = current_time('mysql');

        if (!isset($captcha_solve_ip) || empty($captcha_solve_ip)) {
            ASP_Debug_Logger::log('Captcha solved but IP address is missing. Cannot record this captcha solve event. The extra security check later may fail.', false);
            return;
        }
        //ASP_Debug_Logger::log('Captcha solved from IP: ' . $captcha_solve_ip . ', Time: ' . $current_wp_time, true);

        $solved_ip_time_arr = ASP_Utils_Bot_Mitigation::get_captcha_solve_ip_time_data();
        //ASP_Debug_Logger::log_array_data($solved_ip_time_arr);
        
        $solved_ip_time_arr[$captcha_solve_ip] = $current_wp_time;
        //ASP_Debug_Logger::log_array_data($solved_ip_time_arr);
        
        set_transient('asp_captcha_solve_ip_time', $solved_ip_time_arr, 300);
    }
    
    public static function is_captcha_solve_ip_data_time_valid() {
        $solved_ip_time_arr = get_transient('asp_captcha_solve_ip_time');
        if (!isset($solved_ip_time_arr) || empty($solved_ip_time_arr)) {
            ASP_Debug_Logger::log( 'Captcha response check - currently there are no entries in the saved data that solved a captcha.', false );
            return false;
        }
        
        $ip_address_to_check = ASP_Utils::get_user_ip_address();
        if (!isset($ip_address_to_check) || empty($ip_address_to_check)) {
            ASP_Debug_Logger::log( 'Captcha response check - IP address value for this request is missing.', false );
            return false;
        }
        
        if (!isset($solved_ip_time_arr[$ip_address_to_check]) || empty ($solved_ip_time_arr[$ip_address_to_check])){
            ASP_Debug_Logger::log( 'Captcha response check - cannot find this IP address ('.$ip_address_to_check.') in the saved data that solved a captcha.', false );
            return false;
        }
        
        $captcha_solved_timestamp = $solved_ip_time_arr[$ip_address_to_check];
        $current_wp_time = current_time('mysql');

        $time_diff = strtotime($current_wp_time) - strtotime($captcha_solved_timestamp);
        if ( $time_diff > 300 ){
            //Time expired
            ASP_Debug_Logger::log( 'Captcha response check - The captcha solve data for this IP address ('.$ip_address_to_check.') expired. Time difference (seconds): ' . $time_diff, false );
            return false;
        }
        
        //Entry for the given IP address exists and time is valid.
        return true;
    }
    
    public static function get_page_load_signature_data() {
        $page_load_signature_arr = get_transient('asp_page_load_signature');
        if (!isset($page_load_signature_arr) || empty($page_load_signature_arr)) {
            $page_load_signature_arr = array();
        }
        return $page_load_signature_arr;
    }
    
    public static function record_page_load_signature_data($product_id) {
        $ip_address = ASP_Utils::get_user_ip_address();
        //$current_wp_time = current_time('mysql');

        if (!isset($ip_address) || empty($ip_address)) {
            ASP_Debug_Logger::log('IP address value missing (could not read the IP address of the user). Cannot record this page load signature. The extra security check later may fail.', false);
            return;
        }

        $page_load_signature_arr = ASP_Utils_Bot_Mitigation::get_page_load_signature_data();
        //ASP_Debug_Logger::log_array_data($page_load_signature_arr);

	$hp_key = ASP_Utils_Bot_Mitigation::get_asp_hash_private_key_one();
        
        $index = $product_id. '_'.$ip_address;
        $signature = sha1($hp_key.$product_id.$ip_address);
        $page_load_signature_arr[$index] = $signature;
        //ASP_Debug_Logger::log('Index: ' . $index . ', Signature: ' . $signature . ', IP Address: ' . $ip_address, true);
        //ASP_Debug_Logger::log_array_data($page_load_signature_arr);
        
        //Save the page load signature data with an expiry of 1 hour.
        set_transient('asp_page_load_signature', $page_load_signature_arr, 3600);
    }
    
}
