<?php

class ASP_Payment_Data {
	protected $obj_id;
	protected $obj;
	protected $trans_id             = false;
	protected $amount               = false;
	protected $surcharge_data       = array();
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

    /**
     * Get the surcharge data from payment intent metadata.
     *
     * @param $key string The key to get the value of.
     *
     * @return string Get the value as string if found. Otherwise, empty string.
     */
    public function get_surcharge_data( string $key )
    {
        if ( empty($this->surcharge_data) ) {
            $metadata = isset($this->obj->charges->data[0]->metadata) ? $this->obj->charges->data[0]->metadata : array();
            if (isset($metadata['Surcharge Amount'])){
                $this->surcharge_data['amount'] = $metadata['Surcharge Amount'];
            }
            if (isset($metadata['Surcharge Label'])){
                $this->surcharge_data['label'] = $metadata['Surcharge Label'];
            }
        }
        return isset($this->surcharge_data[$key]) ? (string) $this->surcharge_data[$key] : '';
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
		if ( ! empty( $this->billing_details_obj ) ) {
			return $this->billing_details_obj;
		}
		$billing_addr = new stdClass();
		$bd           = $this->obj->charges->data[0]->billing_details;

		$billing_addr->name        = ! empty( $bd->name ) ? sanitize_text_field($bd->name) : ( isset( $this->obj->customer->name ) ? sanitize_text_field(  $this->obj->customer->name  ): '' );
		$billing_addr->email       = ! empty( $bd->email ) ? sanitize_text_field($bd->email) : ( isset( $this->obj->customer->email ) ? sanitize_text_field( $this->obj->customer->email) : '' );
		$billing_addr->line1       = isset( $bd->address->line1 ) ? sanitize_text_field($bd->address->line1) : ( isset( $this->obj->customer->address->line1 ) ? sanitize_text_field($this->obj->customer->address->line1) : '' );
		$billing_addr->line2       = isset( $bd->address->line2 ) ? sanitize_text_field($bd->address->line2) : ( isset( $this->obj->customer->address->line2 ) ? sanitize_text_field($this->obj->customer->address->line2) : '' );
		$billing_addr->postal_code = isset( $bd->address->postal_code ) ? sanitize_text_field($bd->address->postal_code) : '';
		$billing_addr->city        = isset( $bd->address->city ) ? sanitize_text_field($bd->address->city) : ( isset( $this->obj->customer->address->city ) ? sanitize_text_field( $this->obj->customer->address->city ): '' );
		$billing_addr->state       = isset( $bd->address->state ) ? sanitize_text_field($bd->address->state) : ( isset( $this->obj->customer->address->state ) ? sanitize_text_field($this->obj->customer->address->state) : '' );
		$billing_addr->country     = isset( $bd->address->country ) ? sanitize_text_field( $bd->address->country) : ( isset( $this->obj->customer->address->country ) ? sanitize_text_field($this->obj->customer->address->country) : '' );

		$this->billing_details_obj = $billing_addr;
		return $this->billing_details_obj;
	}

	public function get_shipping_details() {
		if ( ! empty( $this->shipping_details_obj ) ) {
			return $this->shipping_details_obj;
		}
		$shipping_addr = new stdClass();

		$sd = $this->obj->charges->data[0]->shipping;
		if ( empty( $sd ) ) {
			if ( ! empty( $this->obj->customer ) ) {
					$sd = $this->obj->customer->shipping;
			}
		}

		$shipping_addr->name        = isset( $sd->name ) ? sanitize_text_field($sd->name) : '';
		$shipping_addr->line1       = isset( $sd->address->line1 ) ? sanitize_text_field($sd->address->line1) : '';
		$shipping_addr->line2       = isset( $sd->address->line2 ) ? sanitize_text_field($sd->address->line2) : '';
		$shipping_addr->postal_code = isset( $sd->address->postal_code ) ? sanitize_text_field($sd->address->postal_code) : '';
		$shipping_addr->city        = isset( $sd->address->city ) ? sanitize_text_field($sd->address->city) : '';
		$shipping_addr->state       = isset( $sd->address->state ) ? sanitize_text_field($sd->address->state) : '';
		$shipping_addr->country     = isset( $sd->address->country ) ? sanitize_text_field($sd->address->country) : '';

		$this->shipping_details_obj = $shipping_addr;
		return $this->shipping_details_obj;
	}

	public function get_billing_addr_str() {
		$this->get_billing_details();
		$billing_address  = '';
		$bd               = $this->billing_details_obj;
		$billing_address .= ! empty( $bd->name ) ? $bd->name . "\n" : '';
		$billing_address .= ! empty( $bd->line1 ) ? $bd->line1 . "\n" : '';
		$billing_address .= ! empty( $bd->line2 ) ? $bd->line2 . "\n" : '';
		$billing_address .= ! empty( $bd->postal_code ) ? $bd->postal_code . "\n" : '';
		$billing_address .= ! empty( $bd->city ) ? $bd->city . "\n" : '';
		$billing_address .= ! empty( $bd->state ) ? $bd->state . "\n" : '';
		$billing_address .= ! empty( $bd->country ) ? $bd->country . "\n" : '';
		return $billing_address;
	}

	public function get_shipping_addr_str() {
		$this->get_shipping_details();
		$shipping_address  = '';
		$bd                = $this->shipping_details_obj;
		$shipping_address .= ! empty( $bd->name ) ? $bd->name . "\n" : '';
		$shipping_address .= ! empty( $bd->line1 ) ? $bd->line1 . "\n" : '';
		$shipping_address .= ! empty( $bd->line2 ) ? $bd->line2 . "\n" : '';
		$shipping_address .= ! empty( $bd->postal_code ) ? $bd->postal_code . "\n" : '';
		$shipping_address .= ! empty( $bd->city ) ? $bd->city . "\n" : '';
		$shipping_address .= ! empty( $bd->state ) ? $bd->state . "\n" : '';
		$shipping_address .= ! empty( $bd->country ) ? $bd->country . "\n" : '';
		return $shipping_address;
	}

	public function get_customer_details() {
		if ( false === $this->customer_obj ) {
			$this->customer_obj = $this->obj->customer;
		}
		return $this->customer_obj;
	}

	protected function load_from_obj() {
		try {
			if ( ASP_Utils::use_internal_api() ) {
				$api = ASP_Stripe_API::get_instance();
				$obj = $api->get( 'payment_intents/' . $this->obj_id . '?expand[]=customer' );

				if ( false === $obj ) {
					$error            = $api->get_last_error();
					$this->last_error = $error['message'];
					return false;
				}
			} else {
				$obj = \Stripe\PaymentIntent::retrieve(
					array(
						'id'     => $this->obj_id,
						'expand' => array( 'customer' ),
					)
				);
			}
		} catch ( Exception $e ) {
			$this->last_error     = $e->getMessage();
			$this->last_error_obj = $e;
			return false;
		}

		// Check if associated charges aren't set for this payment intent object.
		if( !isset ( $obj->charges )  ){
			//Using the new Stripe API version 2022-11-15 or later
			ASP_Debug_Logger::log( 'Using the Stripe API version 2022-11-15 or later for Payment Intents object. Need to retrieve the charge object separately.' );
			//For Stripe API version 2022-11-15 or later, the charge object is not included in the payment intents object. It needs to be retrieved using the charge ID.
			try {
				//Retrieve the charges related to this payment intent as Stripe\Collection object.
				$charges = \Stripe\Charge::all([
					'payment_intent' => $this->obj_id,
				]);;

				// Add the charges to the payment intent object.
				$obj->charges = $charges;
			} catch (\Stripe\Exception\ApiErrorException $e) {
				// Handle the error
				ASP_Debug_Logger::log( 'Stripe error occurred trying to retrieve the associated charges for the payment intent. ' . $e->getMessage(), false );
			}
		}

		$this->obj = $obj;
	}

	public function get_obj() {
		return $this->obj;
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
		$b_name   = $ipn_ng_class->get_post_var( 'asp_billing_name' );
		$bd->name = empty( $b_name ) ? '' : sanitize_text_field( stripslashes ( $b_name ));

		$b_email   = $ipn_ng_class->get_post_var( 'asp_email' );
		$bd->email = empty( $b_email ) ? '' : sanitize_text_field( stripslashes($b_email));

		$b_addr    = $ipn_ng_class->get_post_var( 'asp_address' );
		$bd->line1 = empty( $b_addr ) ? '' : sanitize_text_field( stripslashes($b_addr));
		$bd->line2 = '';

		$b_postal_code   = $ipn_ng_class->get_post_var( 'asp_postcode' );
		$bd->postal_code = empty( $b_postal_code ) ? '' : sanitize_text_field( stripslashes($b_postal_code));

		$b_city   = $ipn_ng_class->get_post_var( 'asp_city' );
		$bd->city = empty( $b_city ) ? '' : sanitize_text_field( stripslashes($b_city));
		
		$b_state   = $ipn_ng_class->get_post_var( 'asp_state' );
		$bd->state = empty( $b_state ) ? '' : sanitize_text_field( stripslashes($b_state));

		$b_country   = $ipn_ng_class->get_post_var( 'asp_country' );
		$bd->country = empty( $b_country ) ? '' : sanitize_text_field( stripslashes($b_country));

		$this->billing_details_obj = $bd;

		//Shipping details
		$same_addr = $ipn_ng_class->get_post_var( 'asp_same-bill-ship-addr' );

		if ( ! empty( $same_addr ) ) {
			$this->shipping_details_obj = $this->billing_details_obj;
		} else {
			$sd = new stdClass();

			$s_addr    = $ipn_ng_class->get_post_var( 'asp_shipping_address' );
			$sd->line1 = empty( $s_addr ) ? '' : sanitize_text_field( stripslashes($s_addr));
			$sd->line2 = '';

			$s_postal_code   = $ipn_ng_class->get_post_var( 'asp_shipping_postcode' );
			$sd->postal_code = empty( $s_postal_code ) ? '' : sanitize_text_field( stripslashes($s_postal_code));

			$s_city   = $ipn_ng_class->get_post_var( 'asp_shipping_city' );
			$sd->city = empty( $s_city ) ? '' : sanitize_text_field( stripslashes($s_city));
			
			$s_state   = $ipn_ng_class->get_post_var( 'asp_shipping_state' );
			$sd->state = empty( $s_state ) ? '' : sanitize_text_field( stripslashes($s_state));

			$s_country   = $ipn_ng_class->get_post_var( 'asp_shipping_country' );
			$sd->country = empty( $s_country ) ? '' : sanitize_text_field( stripslashes($s_country));

			$this->shipping_details_obj = $sd;
		}

		$this->amount = 0;

		$this->currency = $ipn_ng_class->item->get_currency();

		$ipn_ng_class->item->set_shipping( 0 );

		$this->is_zero_value = true;

	}
}
