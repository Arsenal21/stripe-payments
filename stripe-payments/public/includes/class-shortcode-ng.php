<?php

class AcceptStripePaymentsShortcodeNG {

    var $ASPClass		 = null;
    var $StripeCSSInserted	 = false;
    var $ProductCSSInserted	 = false;
    var $ButtonCSSInserted	 = false;
    var $CompatMode		 = false;
    var $variations		 = array();

    /**
     * Instance of this class.
     *
     * @var      object
     */
    protected static $instance		 = null;
    protected static $payment_buttons	 = array();
    protected $tplTOS			 = '';
    protected $tplCF			 = '';
    protected $locDataPrinted		 = false;

    function __construct() {
	$this->ASPClass = AcceptStripePayments::get_instance();

	add_action( 'wp_enqueue_scripts', array( $this, 'register_scripts' ) );
	add_shortcode( 'asp_product_ng', array( $this, 'shortcode_asp_product' ) );
    }

    /**
     * Return an instance of this class.
     *
     * @since     1.0.0
     *
     * @return    object    A single instance of this class.
     */
    public static function get_instance() {

	// If the single instance hasn't been set, set it now.
	if ( null == self::$instance ) {
	    self::$instance = new self;
	}

	return self::$instance;
    }

    public function register_scripts() {
	wp_register_script( 'asp-stripe-script-ng', 'https://js.stripe.com/v3/', array(), null, true );
	wp_register_script( 'asp-stripe-handler-ng', WP_ASP_PLUGIN_URL . '/public/assets/js/stripe-handler-ng.js', array( 'jquery' ), WP_ASP_PLUGIN_VERSION, true );
    }

    public function shortcode_asp_product( $atts ) {
	if ( ! isset( $atts[ 'id' ] ) || ! is_numeric( $atts[ 'id' ] ) ) {
	    $error_msg	 = '<div class="stripe_payments_error_msg" style="color: red;">';
	    $error_msg	 .= "Error: product ID is invalid.";
	    $error_msg	 .= '</div>';
	    return $error_msg;
	}

	$id	 = $atts[ 'id' ];
	$item	 = new ASPItem( $id );

	if ( ! empty( $item->get_last_error() ) ) {
	    $error_msg	 = '<div class="stripe_payments_error_msg" style="color: red;">';
	    $error_msg	 .= $item->get_last_error();
	    $error_msg	 .= '</div>';
	    return $error_msg;
	}

	$button_text = $item->get_button_text();

	if ( ! empty( $atts[ 'button_text' ] ) ) {
	    $button_text = esc_attr( $atts[ 'button_text' ] );
	}

	$itemData = array(
	    'productId' => $id,
	);

	$this->print_loc_data();
	wp_localize_script( 'asp-stripe-handler-ng', 'aspItemDataNG' . '0', $itemData );
	wp_enqueue_script( 'asp-stripe-script-ng' );
	wp_enqueue_script( 'asp-stripe-handler-ng' );

	$output	 = '';
	$output	 = sprintf( '<a href="#0" data-asp-ng-button-id="%d">%s</a>', 0, $button_text );
	return $output;
    }

    private function print_loc_data() {

	if ( $this->locDataPrinted ) {
	    return;
	}

	$loc_data		 = array(
	    'strEnterValidAmount'		 => apply_filters( 'asp_customize_text_msg', __( 'Please enter a valid amount', 'stripe-payments' ), 'enter_valid_amount' ),
	    'strMinAmount'			 => apply_filters( 'asp_customize_text_msg', __( 'Minimum amount is', 'stripe-payments' ), 'min_amount_is' ),
	    'strEnterQuantity'		 => apply_filters( 'asp_customize_text_msg', __( 'Please enter quantity.', 'stripe-payments' ), 'enter_quantity' ),
	    'strQuantityIsZero'		 => apply_filters( 'asp_customize_text_msg', __( 'Quantity can\'t be zero.', 'stripe-payments' ), 'quantity_is_zero' ),
	    'strQuantityIsFloat'		 => apply_filters( 'asp_customize_text_msg', __( 'Quantity should be integer value.', 'stripe-payments' ), 'quantity_is_float' ),
	    'strStockNotAvailable'		 => apply_filters( 'asp_customize_text_msg', __( 'You cannot order more items than available: %d', 'stripe-payments' ), 'stock_not_available' ),
	    'strTax'			 => apply_filters( 'asp_customize_text_msg', __( 'Tax', 'stripe-payments' ), 'tax_str' ),
	    'strShipping'			 => apply_filters( 'asp_customize_text_msg', __( 'Shipping', 'stripe-payments' ), 'shipping_str' ),
	    'strTotal'			 => __( 'Total:', 'stripe-payments' ),
	    'strPleaseFillIn'		 => apply_filters( 'asp_customize_text_msg', __( 'Please fill in this field.', 'stripe-payments' ), 'fill_in_field' ),
	    'strPleaseCheckCheckbox'	 => __( 'Please check this checkbox.', 'stripe-payments' ),
	    'strMustAcceptTos'		 => apply_filters( 'asp_customize_text_msg', __( 'You must accept the terms before you can proceed.', 'stripe-payments' ), 'accept_terms' ),
	    'strRemoveCoupon'		 => apply_filters( 'asp_customize_text_msg', __( 'Remove coupon', 'stripe-payments' ), 'remove_coupon' ),
	    'strRemove'			 => apply_filters( 'asp_customize_text_msg', __( 'Remove', 'stripe-payments' ), 'remove' ),
	    'strStartFreeTrial'		 => apply_filters( 'asp_customize_text_msg', __( 'Start Free Trial', 'stripe-payments' ), 'start_free_trial' ),
	    'strInvalidCFValidationRegex'	 => __( 'Invalid validation RegEx: ', 'stripe-payments' ),
	    'ajaxURL'			 => admin_url( 'admin-ajax.php' ),
	    'pubKey'			 => $this->ASPClass->APIPubKeyTest,
	);
	wp_localize_script( 'asp-stripe-handler-ng', 'stripeHandlerNG', $loc_data );
	$this->locDataPrinted	 = true;
    }

}
