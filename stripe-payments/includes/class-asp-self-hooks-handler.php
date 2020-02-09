<?php

class ASP_Self_Hooks_Handler {

	private $main;

	public function __construct() {
		$this->main = AcceptStripePayments::get_instance();
		add_action( 'asp_ng_product_mode_keys', array( $this, 'ng_product_mode_keys_handler' ) );
	}

	public function ng_product_mode_keys_handler( $product_id ) {
		if ( empty( $product_id ) ) {
			return;
		}

		$product = get_post( $product_id );

		if ( ! $product ) {
			return;
		}

		$plan_id = get_post_meta( $product_id, 'asp_sub_plan_id', true );

		if ( ! empty( $plan_id ) ) {
			//check if Subs addon enabled
			if ( class_exists( 'ASPSUB_main' ) ) {
				$asp_sub = ASPSUB_main::get_instance();
				$plan    = $asp_sub->get_plan_data( $plan_id );
				if ( $plan->livemode ) {
					return;
				}
			}
		} else {
			//check if force test mode option set for the product
			$force_test = get_post_meta( $product_id, 'asp_product_force_test_mode', true );
			if ( empty( $force_test ) ) {
				return;
			}
		}

		$this->main->is_live = false;
		$this->APIPubKey     = $this->main->get_setting( 'api_publishable_key_test' );
		$this->APISecKey     = $this->main->get_setting( 'api_secret_key_test' );
	}

}

new ASP_Self_Hooks_Handler();
