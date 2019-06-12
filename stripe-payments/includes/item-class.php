<?php

class ASPItem {

    var $post_id		 = false;
    protected $post;
    private $last_error	 = '';
    protected $tax;
    protected $zero_cent	 = false;
    protected $price;
    protected $ASPMain;

    function __construct( $post_id = false ) {
	$this->ASPMain = AcceptStripePayments::get_instance();
	if ( $post_id !== false ) {
	    //let's try to load item from product
	    $this->post_id = $post_id;
	    $this->load_from_product();
	}
    }

    private function in_cents( $amount ) {
	if ( ! $this->zero_cent ) {
	    $amount = round( $amount * 100, 2 );
	} else {
	    $amount = round( $amount, 0 );
	}
	return $amount;
    }

    public function get_last_error() {
	return $this->last_error;
    }

    public function get_button_text() {
	$button_text = get_post_meta( $this->post_id, 'asp_product_button_text', true );
	if ( empty( $button_text ) ) {
	    $button_text = $this->ASPMain->get_setting( 'button_text' );
	}
	return $button_text;
    }

    public function get_button_class() {
	$button_class = get_post_meta( $this->post_id, 'asp_product_button_class', true );
	return $button_class;
    }

    public function get_tax() {
	$this->tax = get_post_meta( $this->post_id, 'asp_product_tax', true );
	return $this->tax;
    }

    public function get_shipping( $in_cents = false ) {
	$this->shipping = get_post_meta( $this->post_id, 'asp_product_shipping', true );
	if ( empty( $this->shipping ) ) {
	    $this->shipping = 0;
	}
	if ( $in_cents ) {
	    return $this->in_cents( $this->shipping );
	}
	return $this->shipping;
    }

    public function get_thumb() {
	$this->thumb = get_post_meta( $this->post_id, 'asp_product_thumbnail', true );
	return $this->thumb;
    }

    public function get_name() {
	$this->name = $this->post->post_title;
	return $this->name;
    }

    public function get_description() {
	$this->description = get_post_meta( $this->post_id, 'asp_product_description', true );
	return $this->description;
    }

    public function get_quantity() {
	$this->quantity = get_post_meta( $this->post_id, 'asp_product_quantity', true );
	if ( empty( $this->quantity ) ) {
	    $this->quantity = 1;
	}
	return $this->quantity;
    }

    public function get_price( $in_cents = false ) {
	if ( is_null( $this->price ) ) {
	    $this->price	 = get_post_meta( $this->post_id, 'asp_product_price', true );
	    $this->price	 = empty( $this->price ) ? 0 : $this->price;
	}
	if ( $in_cents ) {
	    return $this->in_cents( $this->price );
	}
	return $this->price;
    }

    public function set_price( $price ) {
	$this->price = $price;
    }

    public function get_tax_amount( $in_cents = false ) {
	$this->tax_amount = AcceptStripePayments::get_tax_amount( $this->get_price(), $this->get_tax(), $this->zero_cent );
	if ( $in_cents ) {
	    return $this->in_cents( $this->tax_amount );
	}
	return $this->tax_amount;
    }

    public function get_currency() {
	$this->currency = get_post_meta( $this->post_id, 'asp_product_currency', true );
	if ( ! $this->currency ) {
	    $this->currency = $this->ASPMain->get_setting( 'currency_code' );
	}
	return $this->currency;
    }

    public function get_redir_url() {
	$url = get_post_meta( $this->post_id, 'asp_product_thankyou_page', true );
	if ( empty( $url ) ) {
	    $url = $this->ASPMain->get_setting( 'checkout_url' );
	}
	return $url;
    }

    public function collect_billing_addr() {
	return get_post_meta( $this->post_id, 'asp_product_collect_billing_addr', true );
    }

    public function gen_item_data() {
	$ret		 = array();
	$item_info	 = array(
	    'name'		 => $this->get_name(),
	    'amount'	 => $this->get_price( true ),
	    'currency'	 => $this->get_currency(),
	    'quantity'	 => $this->get_quantity(),
	);
	if ( ! empty( $this->get_thumb() ) ) {
	    $item_info[ 'images' ] = array( $this->get_thumb() );
	}
	if ( ! empty( $this->get_description() ) ) {
	    $item_info[ 'description' ] = $this->get_description();
	}
	$ret[] = $item_info;
	if ( ! empty( $this->get_tax() ) ) {
	    $taxStr		 = apply_filters( 'asp_customize_text_msg', __( 'Tax', 'stripe-payments' ), 'tax_str' );
	    $tax_info	 = array(
		'name'		 => sprintf( '%s (%s%%)', $taxStr, $this->get_tax() ),
		'amount'	 => $this->get_tax_amount( true ),
		'currency'	 => $this->get_currency(),
		'quantity'	 => $this->get_quantity(),
	    );
	    $ret[]		 = $tax_info;
	}
	if ( ! empty( $this->get_shipping() ) ) {
	    $shipStr	 = apply_filters( 'asp_customize_text_msg', __( 'Shipping', 'stripe-payments' ), 'shipping_str' );
	    $tax_info	 = array(
		'name'		 => sprintf( '%s', $shipStr ),
		'amount'	 => $this->get_shipping( true ),
		'currency'	 => $this->get_currency(),
		'quantity'	 => $this->get_quantity(),
	    );
	    $ret[]		 = $tax_info;
	}
	return $ret;
    }

    public function load_from_product( $post_id = false ) {
	if ( $post_id === false ) {
	    $post_id = $this->post_id;
	}
	if ( $post_id === false ) {
	    $this->last_error = 'No product ID provided.';
	    return false;
	}
	$this->post = get_post( $post_id );
	if ( ! $this->post || get_post_type( $post_id ) != ASPMain::$products_slug ) {
	    $this->last_error = sprintf( "Can't find product with ID %d", $post_id );
	    return false;
	}
	$this->zero_cent = AcceptStripePayments::is_zero_cents( $this->get_currency() );
	return true;
    }

}
