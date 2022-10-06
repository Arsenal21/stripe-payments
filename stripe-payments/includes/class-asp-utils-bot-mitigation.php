<?php

if (isset($_REQUEST['test'])){
    ASP_Utils_Bot_Mitigation::record_captcha_solve_ip_time_data();
}

class ASP_Utils_Bot_Mitigation {

    public static function get_captcha_solve_ip_time_data() {
        $solve_ip_time_arr = get_transient('asp_captcha_solve_ip_time');
        if (!isset($solve_ip_time_arr) || empty($solve_ip_time_arr)) {
            $solve_ip_time_arr = array();
        }
        return $solve_ip_time_arr;
    }

    public static function record_captcha_solve_ip_time_data() {
        $captcha_solve_ip = ASP_Utils::get_user_ip_address();
        $current_wp_time = current_time('mysql');

        if (!isset($captcha_solve_ip) || empty($captcha_solve_ip)) {
            ASP_Debug_Logger::log('Captcha solved but IP address is missing. Cannot record this captcha solve event. The extra security check later may fail.', false);
            return;
        }
        ASP_Debug_Logger::log('Captcha solved from IP: ' . $captcha_solve_ip . ', Time: ' . $current_wp_time, true);

        //Save the IP -> Timestamp array
        $solve_ip_time_arr = ASP_Utils_Bot_Mitigation::get_captcha_solve_ip_time_data();
        ASP_Debug_Logger::log_array_data($solve_ip_time_arr);
        
        $solve_ip_time_arr[$captcha_solve_ip] = $current_wp_time;
        ASP_Debug_Logger::log_array_data($solve_ip_time_arr);
        
        set_transient('asp_captcha_solve_ip_time', $solve_ip_time_arr, 300);
    }

}
