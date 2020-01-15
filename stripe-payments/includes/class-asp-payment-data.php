<?php

class ASP_Payment_Data {
	protected $obj_id;
	protected $obj;
	protected $trans_id             = false;
	protected $amount               = false;
	protected $currency             = false;
	protected $charge_created       = false;
	protected $charge_data          = false;
	protected $last_error           = '';
	protected $billing_details_obj  = false;
	protected $shipping_details_obj = false;
	protected $customer_obj         = false;
	protected $last_error_obj;

	public $is_zero_value = false;

	public function __construct( $obj_id = false, $zero_value = false ) {
		if ( false !== $obj_id ) {
			$this->obj_id = $obj_id;
		}
		if ( $zero_value ) {
			$this->construct_zero_value();
		} else {
			$this->load_from_obj();
		}
	}

	public function get_last_error() {
		return $this->last_error;
	}

	public function get_price() {
		$price = $this->get_amount();
		return $price;
	}

	public function get_amount() {
		if ( false === $this->amount ) {
			$this->amount = $this->obj->charges->data[0]->amount;
		}
		return $this->amount;
	}

	public function get_currency() {
		if ( false === $this->currency ) {
			$this->currency = $this->obj->charges->data[0]->currency;
		}
		return $this->currency;
	}

	public function get_charge_data() {
		if ( false === $this->charge_data ) {
			$this->charge_data = $this->obj->charges->data[0];
		}
		return $this->charge_data;
	}

	public function get_charge_created() {
		if ( false === $this->charge_created ) {
			$this->charge_created = $this->obj->charges->data[0]->created;
		}
		return $this->charge_created;
	}

	public function get_trans_id() {
		if ( false === $this->trans_id ) {
			$this->trans_id = $this->obj->charges->data[0]->id;
		}
		return $this->trans_id;
	}

	public function get_billing_details() {
		if ( false !== $this->billing_details_obj ) {
			return $this->billing_details_obj;
		}
		$billing_addr              = new stdClass();
		$bd                        = $this->obj->charges->data[0]->billing_details;
		$billing_addr->name        = $this->obj->charges->data[0]->billing_details->name;
		$billing_addr->email       = $this->obj->charges->data[0]->billing_details->email;
		$billing_addr->line1       = isset( $bd->address->line1 ) ? $bd->address->line1 : '';
		$billing_addr->line2       = isset( $bd->address->line2 ) ? $bd->address->line2 : '';
		$billing_addr->postal_code = isset( $bd->address->postal_code ) ? $bd->address->postal_code : '';
		$billing_addr->city        = isset( $bd->address->city ) ? $bd->address->city : '';
		$billing_addr->state       = isset( $bd->address->state ) ? $bd->address->state : '';
		$billing_addr->country     = isset( $bd->address->country ) ? $bd->address->country : '';

		$this->billing_details_obj = $billing_addr;
		return $this->billing_details_obj;
	}

	public function get_shipping_details() {
		if ( false !== $this->shipping_details_obj ) {
			return $this->shipping_details_obj;
		}
		$shipping_addr = new stdClass();
		$sd            = $this->obj->charges->data[0]->shipping;
		if ( empty( $sd ) ) {
			if ( empty( $this->customer_obj ) && ! empty( $this->obj->customer ) ) {
				try {
					$this->customer_obj = \Stripe\Customer::retrieve( $this->obj->customer );
					$sd                 = $this->customer_obj->shipping;
				} catch ( Exception $e ) {
					$this->last_error = $e->getMessage();
				}
			}
		}
		$shipping_addr->name        = isset( $sd->name ) ? $sd->name : '';
		$shipping_addr->line1       = isset( $sd->address->line1 ) ? $sd->address->line1 : '';
		$shipping_addr->line2       = isset( $sd->address->line2 ) ? $sd->address->line2 : '';
		$shipping_addr->postal_code = isset( $sd->address->postal_code ) ? $sd->address->postal_code : '';
		$shipping_addr->city        = isset( $sd->address->city ) ? $sd->address->city : '';
		$shipping_addr->state       = isset( $sd->address->state ) ? $sd->address->state : '';
		$shipping_addr->country     = isset( $sd->address->country ) ? $sd->address->country : '';

		$this->shipping_details_obj = $shipping_addr;
		return $this->shipping_details_obj;
	}

	public function get_billing_addr_str() {
		$this->get_billing_details();
		$billing_address  = '';
		$bd               = $this->billing_details_obj;
		$billing_address .= $bd->name ? $bd->name . "\n" : '';
		$billing_address .= isset( $bd->line1 ) ? $bd->line1 . "\n" : '';
		$billing_address .= isset( $bd->line2 ) ? $bd->line2 . "\n" : '';
		$billing_address .= isset( $bd->postal_code ) ? $bd->postal_code . "\n" : '';
		$billing_address .= isset( $bd->city ) ? $bd->city . "\n" : '';
		$billing_address .= isset( $bd->state ) ? $bd->state . "\n" : '';
		$billing_address .= isset( $bd->country ) ? $bd->country . "\n" : '';
		return $billing_address;
	}

	public function get_shipping_addr_str() {
		$this->get_shipping_details();
		$shipping_address  = '';
		$bd                = $this->shipping_details_obj;
		$shipping_address .= isset( $bd->name ) ? $bd->name . "\n" : '';
		$shipping_address .= isset( $bd->line1 ) ? $bd->line1 . "\n" : '';
		$shipping_address .= isset( $bd->line2 ) ? $bd->line2 . "\n" : '';
		$shipping_address .= isset( $bd->postal_code ) ? $bd->postal_code . "\n" : '';
		$shipping_address .= isset( $bd->city ) ? $bd->city . "\n" : '';
		$shipping_address .= isset( $bd->state ) ? $bd->state . "\n" : '';
		$shipping_address .= isset( $bd->country ) ? $bd->country . "\n" : '';
		return $shipping_address;
	}

	protected function load_from_obj() {
		try {
			$obj = \Stripe\PaymentIntent::retrieve( $this->obj_id );
		} catch ( Exception $e ) {
			$this->last_error     = $e->getMessage();
			$this->last_error_obj = $e;
			return false;
		}
		$this->obj = $obj;
	}

	public function construct_zero_value() {
		$this->charge_data          = new stdClass();
		$this->charge_data->id      = $this->obj_id;
		$this->trans_id             = $this->charge_data->id;
		$this->charge_data->created = time();
		$this->charge_created       = $this->charge_data->created;

		$ipn_ng_class = ASP_Process_IPN_NG::get_instance();

		$bd = new stdClass();

		//Billing details
		$b_name   = $ipn_ng_class->get_post_var( 'asp_billing_name', FILTER_SANITIZE_STRING );
		$bd->name = empty( $b_name ) ? '' : $b_name;

		$b_email   = $ipn_ng_class->get_post_var( 'asp_email', FILTER_SANITIZE_STRING );
		$bd->email = empty( $b_email ) ? '' : $b_email;

		$b_addr    = $ipn_ng_class->get_post_var( 'asp_address', FILTER_SANITIZE_STRING );
		$bd->line1 = empty( $b_addr ) ? '' : $b_addr;
		$bd->line2 = '';

		$b_postal_code   = $ipn_ng_class->get_post_var( 'asp_postcode', FILTER_SANITIZE_STRING );
		$bd->postal_code = empty( $b_postal_code ) ? '' : $b_postal_code;

		$b_city   = $ipn_ng_class->get_post_var( 'asp_city', FILTER_SANITIZE_STRING );
		$bd->city = empty( $b_city ) ? '' : $b_city;

		$b_country   = $ipn_ng_class->get_post_var( 'asp_country', FILTER_SANITIZE_STRING );
		$bd->country = empty( $b_country ) ? '' : $b_country;

		$bd->state = '';

		$this->billing_details_obj = $bd;

		//Shipping details
		$same_addr = $ipn_ng_class->get_post_var( 'asp_same-bill-ship-addr', FILTER_SANITIZE_STRING );

		if ( ! empty( $same_addr ) ) {
			$this->shipping_details_obj = $this->billing_details_obj;
		} else {
			$sd = new stdClass();

			$s_addr    = $ipn_ng_class->get_post_var( 'asp_shipping_address', FILTER_SANITIZE_STRING );
			$sd->line1 = empty( $s_addr ) ? '' : $s_addr;
			$sd->line2 = '';

			$s_postal_code   = $ipn_ng_class->get_post_var( 'asp_shipping_postcode', FILTER_SANITIZE_STRING );
			$sd->postal_code = empty( $s_postal_code ) ? '' : $s_postal_code;

			$s_city   = $ipn_ng_class->get_post_var( 'asp_shipping_city', FILTER_SANITIZE_STRING );
			$sd->city = empty( $s_city ) ? '' : $s_city;

			$s_country   = $ipn_ng_class->get_post_var( 'asp_shipping_country', FILTER_SANITIZE_STRING );
			$sd->country = empty( $s_country ) ? '' : $s_country;

			$sd->state = '';

			$this->shipping_details_obj = $sd;
		}

		$this->amount = 0;

		$this->currency = $ipn_ng_class->item->get_currency();

		$ipn_ng_class->item->set_shipping( 0 );

		$this->is_zero_value = true;

	}
}
