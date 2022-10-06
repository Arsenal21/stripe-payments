<?php

class ASP_Utils_Bot_Mitigation {

    public static function record_captcha_solve_ip_time() {
        $captcha_solve_ip = ASP_Utils::get_user_ip_address();
        $current_wp_time = current_time('mysql');
        ASP_Debug_Logger::log('Captcha solved from from IP: ' . $captcha_solve_ip . ', Time: ' . $current_wp_time, true);
        //Save the IP -> Timestamp array using set_transient() 
    }

}
