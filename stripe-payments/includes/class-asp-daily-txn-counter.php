<?php

class ASP_Daily_Txn_Counter {

    private $asp_main = null;
    private $txn_counter_option_name;
    //private $txn_counter_limit;
    private $today;

    public function __construct() {
        $this->txn_counter_option_name = 'asp_daily_txn_count_args';
        $this->asp_main = AcceptStripePayments::get_instance();
        $this->today = date("Y-m-d");
    }

    //Resets or get the current counter
    public function asp_get_daily_txn_counter_args() {

        $txn_counter_args = get_option($this->txn_counter_option_name);

        if (!$txn_counter_args) {
            //If txn_counter don't exists , create and return new as zero
            return $this->asp_reset_daily_txn_counter();
        } else {

            if (isset($txn_counter_args["counter_date"]) == false || $this->today != $txn_counter_args["counter_date"] ) {
                return $this->asp_reset_daily_txn_counter();
            }
        }

        return $txn_counter_args;
    }

    public function asp_increment_daily_txn_counter() {
        $txn_counter_args = $this->asp_get_daily_txn_counter_args();

        $txn_counter_args["counter"]++;
        update_option($this->txn_counter_option_name, $txn_counter_args);

        return $txn_counter_args;
    }

    public function asp_set_txn_counter_value( $count ) {
        $txn_counter_args = $this->asp_get_daily_txn_counter_args();
        $txn_counter_args["counter"] = $count;
        update_option($this->txn_counter_option_name, $txn_counter_args);
    }
    
    public function asp_is_daily_txn_limit_reached($is_captcha_enabled=false) {
        $txn_counter_args = $this->asp_get_daily_txn_counter_args();
        $txn_counter_limit = 0;
        
        if( $is_captcha_enabled == true ) {
            $txn_counter_limit = $this->asp_main->get_setting('daily_txn_limit_with_captcha');
        } else {
            $txn_counter_limit = $this->asp_main->get_setting('daily_txn_limit_without_captcha');
        }

        if ( !$txn_counter_limit ) {
            $txn_counter_limit = 25;
        }

        if ( $txn_counter_args["counter"] >= $txn_counter_limit ) {
            return true;
        }

        return false;
    }

    public function asp_is_daily_tnx_limit_with_captcha_enabled()
    {
        $daily_txn_limit_with_captcha = $this->asp_main->get_setting('daily_txn_limit_with_captcha');
        
        if(empty($daily_txn_limit_with_captcha)){
            return true;
        }
        if($daily_txn_limit_with_captcha && $daily_txn_limit_with_captcha >= 0) {
            return true;
        }
        return false;
    }

    private function asp_reset_daily_txn_counter() {
        $txn_counter_args = array('counter_date' => $this->today, 'counter' => 0);
        update_option($this->txn_counter_option_name, $txn_counter_args);

        return $txn_counter_args;
    }

}
