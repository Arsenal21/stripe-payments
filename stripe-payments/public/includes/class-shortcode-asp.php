<?php

class AcceptStripePaymentsShortcode {

    var $AcceptStripePayments	 = null;
    var $StripeCSSInserted	 = false;
    var $ProductCSSInserted	 = false;
    var $ButtonCSSInserted	 = false;
    var $CompatMode		 = false;

    /**
     * Instance of this class.
     *
     * @var      object
     */
    protected static $instance		 = null;
    protected static $payment_buttons	 = array();

    function __construct() {
	$this->AcceptStripePayments = AcceptStripePayments::get_instance();

	add_action( 'wp_enqueue_scripts', array( $this, 'register_stripe_script' ) );

	add_shortcode( 'asp_show_all_products', array( &$this, 'shortcode_show_all_products' ) );
	add_shortcode( 'asp_product', array( &$this, 'shortcode_asp_product' ) );
	add_shortcode( 'accept_stripe_payment', array( &$this, 'shortcode_accept_stripe_payment' ) );
	add_shortcode( 'accept_stripe_payment_checkout', array( &$this, 'shortcode_accept_stripe_payment_checkout' ) );
	add_shortcode( 'accept_stripe_payment_checkout_error', array( &$this, 'shortcode_accept_stripe_payment_checkout_error' ) );
	if ( ! is_admin() ) {
	    add_filter( 'widget_text', 'do_shortcode' );
	}
    }

    public function interfer_for_redirect() {
	global $post;
	if ( ! is_admin() ) {
	    if ( has_shortcode( $post->post_content, 'accept_stripe_payment_checkout' ) ) {
		$this->shortcode_accept_stripe_payment_checkout();
		exit;
	    }
	}
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

    function get_loc_data() {
	//localization data, some settings and Stripe API key
	$key		 = $this->AcceptStripePayments->APIPubKey;
	$minAmounts	 = $this->AcceptStripePayments->minAmounts;
	$zeroCents	 = $this->AcceptStripePayments->zeroCents;

	$amountOpts = array(
	    'applySepOpts'	 => $this->AcceptStripePayments->get_setting( 'price_apply_for_input' ),
	    'decimalSep'	 => $this->AcceptStripePayments->get_setting( 'price_decimal_sep' ),
	    'thousandSep'	 => $this->AcceptStripePayments->get_setting( 'price_thousand_sep' ),
	);

	$loc_data = array(
	    'strEnterValidAmount'	 => __( 'Please enter a valid amount', 'stripe-payments' ),
	    'strMinAmount'		 => __( 'Minimum amount is', 'stripe-payments' ),
	    'key'			 => $key,
	    'strEnterQuantity'	 => __( 'Please enter quantity.', 'stripe-payments' ),
	    'strQuantityIsZero'	 => __( 'Quantity can\'t be zero.', 'stripe-payments' ),
	    'strQuantityIsFloat'	 => __( 'Quantity should be integer value.', 'stripe-payments' ),
	    'strTax'		 => __( 'Tax', 'stripe-payments' ),
	    'strShipping'		 => __( 'Shipping', 'stripe-payments' ),
	    'strTotal'		 => __( 'Total:', 'stripe-payments' ),
	    'strPleaseFillIn'	 => __( 'Please fill in this field.', 'stripe-payments' ),
	    'strPleaseCheckCheckbox' => __( 'Please check this checkbox.', 'stripe-payments' ),
	    'strMustAcceptTos'	 => __( 'You must accept the terms before you can proceed.', 'stripe-payments' ),
	    'minAmounts'		 => $minAmounts,
	    'zeroCents'		 => $zeroCents,
	    'amountOpts'		 => $amountOpts,
	);
	return $loc_data;
    }

    function register_stripe_script() {
	wp_register_script( 'stripe-script', 'https://checkout.stripe.com/checkout.js', array(), null, true );
	wp_register_script( 'stripe-handler', WP_ASP_PLUGIN_URL . '/public/assets/js/stripe-handler.js', array( 'jquery' ), WP_ASP_PLUGIN_VERSION, true );

	wp_localize_script( 'stripe-handler', 'stripehandler', $this->get_loc_data() );
	// addons can register their scripts if needed
	do_action( 'asp-button-output-register-script' );
    }

    function shortcode_asp_product( $atts ) {
	if ( ! isset( $atts[ 'id' ] ) || ! is_numeric( $atts[ 'id' ] ) ) {
	    $error_msg	 = '<div class="stripe_payments_error_msg" style="color: red;">';
	    $error_msg	 .= "Error: product ID is invalid.";
	    $error_msg	 .= '</div>';
	    return $error_msg;
	}
	$id	 = $atts[ 'id' ];
	$post	 = get_post( $id );
	if ( ! $post || get_post_type( $id ) != ASPMain::$products_slug ) {
	    $error_msg	 = '<div class="stripe_payments_error_msg" style="color: red;">';
	    $error_msg	 .= "Can't find product with ID " . $id;
	    $error_msg	 .= '</div>';
	    return $error_msg;
	}

	$currency = get_post_meta( $id, 'asp_product_currency', true );

	if ( ! $currency ) {
	    $currency = $this->AcceptStripePayments->get_setting( 'currency_code' );
	}

	$button_text = get_post_meta( $id, 'asp_product_button_text', true );
	if ( ! $button_text ) {
	    $button_text = $this->AcceptStripePayments->get_setting( 'button_text' );
	}

	//check if we have button_text shortcode parameter. If it's not empty, this should be our button text

	if ( isset( $atts[ 'button_text' ] ) && ! empty( $atts[ 'button_text' ] ) ) {
	    $button_text = esc_attr( $atts[ 'button_text' ] );
	}

	$thumb_img	 = '';
	$thumb_url	 = get_post_meta( $id, 'asp_product_thumbnail', true );

	if ( $thumb_url ) {
	    $thumb_img = '<img src="' . $thumb_url . '">';
	}

	$url = get_post_meta( $id, 'asp_product_upload', true );

	if ( ! $url ) {
	    $url = '';
	}

	$template_name	 = 'default'; //this could be made configurable
	$button_color	 = 'blue'; //this could be made configurable

	$price		 = get_post_meta( $id, 'asp_product_price', true );
	$shipping	 = get_post_meta( $id, 'asp_product_shipping', true );

	//let's apply filter so addons can change price, currency and shipping if needed
	$price_arr	 = array( 'price' => $price, 'currency' => $currency, 'shipping' => empty( $shipping ) ? false : $shipping );
	$price_arr	 = apply_filters( 'asp_modify_price_currency_shipping', $price_arr );
	extract( $price_arr, EXTR_OVERWRITE );

	$buy_btn = '';

	$button_class = get_post_meta( $id, 'asp_product_button_class', true );

	$class = ! empty( $button_class ) ? $button_class : 'asp_product_buy_btn ' . $button_color;

	$class = isset( $atts[ 'class' ] ) ? $atts[ 'class' ] : $class;

	$custom_field = get_post_meta( $id, 'asp_product_custom_field', true );

	if ( ( $custom_field === "" ) || $custom_field === "2" ) {
	    $custom_field = $this->AcceptStripePayments->get_setting( 'custom_field_enabled' );
	} else {
	    $custom_field = intval( $custom_field );
	}

	$thankyou_page = get_post_meta( $id, 'asp_product_thankyou_page', true );

	if ( ! $shipping ) {
	    $shipping = 0;
	}

	$tax = get_post_meta( $id, 'asp_product_tax', true );

	if ( ! $tax ) {
	    $tax = 0;
	}

	$quantity = get_post_meta( $id, 'asp_product_quantity', true );

	$under_price_line	 = '';
	$tot_price		 = $quantity ? $price * $quantity : $price;

	if ( $tax !== 0 ) {
	    if ( ! empty( $price ) ) {
		$tax_amount		 = AcceptStripePayments::get_tax_amount( $tot_price, $tax, AcceptStripePayments::is_zero_cents( $currency ) );
		$tot_price		 += $tax_amount;
		$under_price_line	 = '<span class="asp_price_tax_section">' . AcceptStripePayments::formatted_price( $tax_amount, $currency ) . __( ' (tax)', 'stripe-payments' ) . '</span>';
	    } else {
		$under_price_line = '<span class="asp_price_tax_section">' . $tax . '% tax' . '</span>';
	    }
	}
	if ( $shipping !== 0 ) {
	    $tot_price	 += $shipping;
	    $shipping_line	 = AcceptStripePayments::formatted_price( $shipping, $currency ) . __( ' (shipping)', 'stripe-payments' );
	    if ( ! empty( $under_price_line ) ) {
		$under_price_line .= '<span class="asp_price_shipping_section">' . ' + ' . $shipping_line . '</span>';
	    } else {
		$under_price_line = '<span class="asp_price_shipping_section">' . $shipping_line . '</span>';
	    }
	}

	if ( ! empty( $price ) && ! empty( $under_price_line ) ) {
	    $under_price_line .= '<div class="asp_price_full_total">' . __( 'Total:', 'stripe-payments' ) . ' ' . AcceptStripePayments::formatted_price( $tot_price, $currency ) . '</div>';
	}

	if ( get_post_meta( $id, 'asp_product_no_popup_thumbnail', true ) != 1 ) {
	    $item_logo = get_post_meta( $id, 'asp_product_thumbnail', true );
	} else {
	    $item_logo = '';
	}

	$compat_mode = isset( $atts[ 'compat_mode' ] ) ? 1 : 0;

	$this->CompatMode = ($compat_mode) ? true : false;

	//Let's only output buy button if we're in the loop. Since the_content hook could be called several times (for example, by a plugin like Yoast SEO for its purposes), we should only output the button only when it's actually needed.
	if ( ! isset( $atts[ 'in_the_loop' ] ) || $atts[ 'in_the_loop' ] === "1" ) {
	    $sc_params	 = array(
		'product_id'		 => $id,
		'name'			 => $post->post_title,
		'price'			 => $price,
		'currency'		 => $currency,
		'shipping'		 => $shipping,
		'tax'			 => $tax,
		'class'			 => $class,
		'quantity'		 => get_post_meta( $id, 'asp_product_quantity', true ),
		'custom_quantity'	 => get_post_meta( $id, 'asp_product_custom_quantity', true ),
		'button_text'		 => $button_text,
		'description'		 => get_post_meta( $id, 'asp_product_description', true ),
		'url'			 => $url,
		'thankyou_page_url'	 => $thankyou_page,
		'item_logo'		 => $item_logo,
		'billing_address'	 => get_post_meta( $id, 'asp_product_collect_billing_addr', true ),
		'shipping_address'	 => get_post_meta( $id, 'asp_product_collect_shipping_addr', true ),
		'custom_field'		 => $custom_field,
		'compat_mode'		 => $compat_mode,
	    );
	    //this would pass additional shortcode parameters from asp_product shortcode
	    $sc_params	 = array_merge( $atts, $sc_params );
	    $buy_btn	 = $this->shortcode_accept_stripe_payment( $sc_params );
	}

	$button_only = get_post_meta( $id, 'asp_product_button_only', true );

	if ( (isset( $atts[ "fancy" ] ) && $atts[ "fancy" ] == '0') || $button_only == 1 ) {
	    //Just show the stripe payment button (no fancy template)
	    $tpl	 = '<div class="asp_product_buy_button">' . $buy_btn . '</div>';
	    $tpl	 = "<link rel='stylesheet' href='" . WP_ASP_PLUGIN_URL . '/public/views/templates/default/style.css' . "' type='text/css' media='all' />" . $tpl;
	    if ( ! $this->CompatMode ) {
		$this->productCSSInserted = true;
	    }
	    return $tpl;
	}

	//Show the stripe payment button with fancy style template.
	require_once(WP_ASP_PLUGIN_PATH . 'public/views/templates/' . $template_name . '/template.php');
	if ( isset( $atts[ "is_post_tpl" ] ) ) {
	    $tpl = asp_get_post_template( $this->ProductCSSInserted );
	} else {
	    $tpl = asp_get_template( $this->ProductCSSInserted );
	}
	if ( ! $this->CompatMode ) {
	    $this->productCSSInserted = true;
	}

	$price_line = AcceptStripePayments::formatted_price( $price, $currency );

	if ( $quantity && $quantity != 1 ) {
	    $price_line = AcceptStripePayments::formatted_price( $price, $currency ) . ' x ' . $quantity;
	}

	$product_tags = array(
	    'thumb_img'		 => $thumb_img,
	    'name'			 => $post->post_title,
	    'description'		 => do_shortcode( wpautop( $post->post_content ) ),
	    'price'			 => $price_line,
	    'under_price_line'	 => $under_price_line,
	    'buy_btn'		 => $buy_btn,
	);

	$product_tags = apply_filters( 'asp_product_tpl_tags_arr', $product_tags, $id );

	foreach ( $product_tags as $tag => $repl ) {
	    $tpl = str_replace( '%_' . $tag . '_%', $repl, $tpl );
	}

	return $tpl;
    }

    function shortcode_accept_stripe_payment( $atts ) {

	extract( shortcode_atts( array(
	    'product_id'		 => 0,
	    'name'			 => '',
	    'class'			 => 'stripe-button-el', //default Stripe button class
	    'price'			 => '0',
	    'shipping'		 => 0,
	    'tax'			 => 0,
	    'quantity'		 => '',
	    'custom_quantity'	 => false,
	    'description'		 => '',
	    'url'			 => '',
	    'thankyou_page_url'	 => '',
	    'item_logo'		 => '',
	    'billing_address'	 => '',
	    'shipping_address'	 => '',
	    'customer_email'	 => '',
	    'currency'		 => $this->AcceptStripePayments->get_setting( 'currency_code' ),
	    'button_text'		 => $this->AcceptStripePayments->get_setting( 'button_text' ),
	    'compat_mode'		 => 0,
	), $atts ) );

	$this->CompatMode = ($compat_mode) ? true : false;

	if ( empty( $name ) ) {
	    $error_msg	 = '<div class="stripe_payments_error_msg" style="color: red;">';
	    $error_msg	 .= 'There is an error in your Stripe Payments shortcode. It is missing the "name" field. ';
	    $error_msg	 .= 'You must specify an item name value using the "name" parameter. This value should be unique so this item can be identified uniquely on the page.';
	    $error_msg	 .= '</div>';
	    return $error_msg;
	}

	if ( ! empty( $url ) ) {
	    $url = base64_encode( $url );
	} else {
	    $url = '';
	}

	if ( ! empty( $thankyou_page_url ) ) {
	    $thankyou_page_url = base64_encode( $thankyou_page_url );
	} else {
	    $thankyou_page_url = '';
	}

	if ( empty( $quantity ) && $custom_quantity !== "1" ) {
	    $quantity = 1;
	}

	if ( ! is_numeric( $quantity ) ) {
	    $quantity = strtoupper( $quantity );
	}
	if ( $quantity == "N/A" ) {
	    $quantity = "NA";
	}
	$price			 = floatval( $price );
	$uniq_id		 = count( self::$payment_buttons );
	$button_id		 = 'stripe_button_' . $uniq_id;
	self::$payment_buttons[] = $button_id;

	$item_price	 = $price;
	$paymentAmount	 = ($custom_quantity == "1" ? $price : (floatval( $price ) * $quantity));
	if ( AcceptStripePayments::is_zero_cents( $currency ) ) {
	    //this is zero-cents currency, amount shouldn't be multiplied by 100
	    $priceInCents = $paymentAmount;
	} else {
	    $priceInCents	 = $paymentAmount * 100;
	    $item_price	 = $price * 100;
	}

	if ( ! empty( $shipping ) ) {
	    $shipping = round( $shipping, 2 );
	    if ( ! AcceptStripePayments::is_zero_cents( $currency ) ) {
		$shipping = $shipping * 100;
	    }
	}

	if ( ! empty( $price ) ) {
	    //let's apply tax if needed
	    if ( ! empty( $tax ) ) {
		$tax_amount	 = round( ($priceInCents * $tax / 100 ) );
		$priceInCents	 = $priceInCents + $tax_amount;
	    }

	    //let's apply shipping cost if needed
	    if ( ! empty( $shipping ) ) {
		$priceInCents = $priceInCents + $shipping;
	    }
	}

	$button_key = md5( htmlspecialchars_decode( $name ) . $priceInCents );

	//Charge description
	//We only generate it if it's empty and if custom qunatity and price is not used
	//If custom quantity and\or price are used, description will be generated by javascript
	if ( empty( $description ) && $custom_quantity !== '1' && ( ! empty( $price ) && $price !== 0) ) {
	    //Create a description using quantity, payment amount and currency
	    if ( ! empty( $tax ) || ! empty( $shipping ) ) {
		$formatted_amount = AcceptStripePayments::formatted_price( AcceptStripePayments::is_zero_cents( $currency ) ? $priceInCents : $priceInCents / 100, $currency );
	    } else {
		$formatted_amount = AcceptStripePayments::formatted_price( $paymentAmount, $currency );
	    }
	    $description = "{$quantity} X " . $formatted_amount;
	}

	// Check if "Disable Buttons Before Javascript Loads" option is set
	$is_disabled = '';
	if ( $this->AcceptStripePayments->get_setting( 'disable_buttons_before_js_loads' ) ) {
	    $is_disabled = " disabled";
	}

	//This is public.css stylesheet
	//wp_enqueue_style('stripe-button-public');
	//$button = "<button id = '{$button_id}' type = 'submit' class = '{$class}'><span>{$button_text}</span></button>";
	$button = sprintf( '<button id="%s" type="submit" class="%s"%s><span>%s</span></button>', esc_attr( $button_id ), esc_attr( $class ), $is_disabled, sanitize_text_field( $button_text ) );

	$out_of_stock = false;
	//check if stock enabled
	if ( isset( $product_id ) && get_post_meta( $product_id, 'asp_product_enable_stock', true ) ) {
	    //check if product is not out of stock
	    $stock_items = get_post_meta( $product_id, 'asp_product_stock_items', true );
	    if ( empty( $stock_items ) ) {
		$button		 = '<div class="asp_out_of_stock">' . __( "Out of stock", 'stripe-payments' ) . '</div>';
		$out_of_stock	 = true;
	    }
	}

	//add message if no javascript is enabled
	$button .= '<noscript>' . __( 'Stripe Payments requires Javascript to be supported by the browser in order to operate.', 'stripe-payments' ) . '</noscript>';

	$checkout_lang = $this->AcceptStripePayments->get_setting( 'checkout_lang' );

	$allowRememberMe = $this->AcceptStripePayments->get_setting( 'disable_remember_me' );

	$allowRememberMe = ($allowRememberMe === 1) ? false : true;

	$custom_field = $this->AcceptStripePayments->get_setting( 'custom_field_enabled' );
	if ( isset( $atts[ 'custom_field' ] ) ) {
	    $custom_field = $atts[ 'custom_field' ];
	}

	$tos = $this->AcceptStripePayments->get_setting( 'tos_enabled' );

	$data = array(
	    'product_id'		 => $product_id,
	    'button_key'		 => $button_key,
	    'item_price'		 => isset( $item_price ) ? $item_price : 0,
	    'allowRememberMe'	 => $allowRememberMe,
	    'quantity'		 => $quantity,
	    'custom_quantity'	 => $custom_quantity,
	    'description'		 => $description,
	    'shipping'		 => $shipping,
	    'tax'			 => $tax,
	    'image'			 => $item_logo,
	    'currency'		 => $currency,
	    'locale'		 => (empty( $checkout_lang ) ? 'auto' : $checkout_lang),
	    'name'			 => htmlspecialchars_decode( $name ),
	    'url'			 => $url,
	    'amount'		 => $priceInCents,
	    'billingAddress'	 => (empty( $billing_address ) ? false : true),
	    'shippingAddress'	 => (empty( $shipping_address ) ? false : true),
	    'customer_email'	 => $customer_email,
	    'uniq_id'		 => $uniq_id,
	    'variable'		 => ($price == 0 ? true : false),
	    'zeroCents'		 => $this->AcceptStripePayments->zeroCents,
	    'addonHooks'		 => array(),
	    'custom_field'		 => $custom_field,
	    'tos'			 => $tos,
	    'button_text'		 => esc_attr( $button_text ),
	    'out_of_stock'		 => $out_of_stock,
	);

	$data = apply_filters( 'asp-button-output-data-ready', $data, $atts );

	$output = '';

	//Let's insert Stripe default stylesheet only when it's needed
	if ( $class == 'stripe-button-el' && ! ( ! $this->CompatMode && $this->StripeCSSInserted) ) {
	    $output			 = "<link rel = 'stylesheet' href = 'https://checkout.stripe.com/v3/checkout/button.css' type = 'text/css' media = 'all' />";
	    $this->StripeCSSInserted = true;
	}

	$output .= $this->get_styles();

	$output .= "<form id = 'stripe_form_{$uniq_id}' class='asp-stripe-form' action = '' METHOD = 'POST'> ";

	$output .= $this->get_button_code_new_method( $data );

	$output	 .= '<input type="hidden" name="asp_action" value="process_ipn" />';
	$output	 .= "<input type = 'hidden' value = '{$data[ 'name' ]}' name = 'item_name' />";
	$output	 .= "<input type = 'hidden' value = '{$data[ 'quantity' ]}' name = 'item_quantity' />";
	$output	 .= "<input type = 'hidden' value = '{$data[ 'currency' ]}' name = 'currency_code' />";
	$output	 .= "<input type = 'hidden' value = '{$data[ 'url' ]}' name = 'item_url' />";
	$output	 .= "<input type = 'hidden' value = '{$thankyou_page_url}' name = 'thankyou_page_url' />";
	$output	 .= "<input type = 'hidden' value = '{$data[ 'description' ]}' name = 'charge_description' />"; //

	$trans_name		 = 'stripe-payments-' . $button_key; //Create key using the item name.
	$trans[ 'tax' ]		 = $tax;
	$trans[ 'shipping' ]	 = $shipping;
	$trans[ 'price' ]	 = $price;
	set_transient( $trans_name, $trans, 2 * 3600 ); //Save the price for this item for 2 hours.
	$output			 .= wp_nonce_field( 'stripe_payments', '_wpnonce', true, false );
	$output			 .= "</form>";
	//before button filter
	if ( ! $out_of_stock ) {
	    $output = apply_filters( 'asp_button_output_before_button', $output, $data, $class );
	}
	$output .= $button;
	//after button filter
	if ( ! $out_of_stock ) {
	    $output = apply_filters( 'asp-button-output-after-button', $output, $data, $class );
	}
	$output .= $this->get_scripts( $data );

	return $output;
    }

    function get_styles() {
	$output = '';
	if ( ! $this->ButtonCSSInserted || $this->CompatMode ) {
//	    $this->ButtonCSSInserted = true;
	    // we need to style custom inputs
	    $style	 = file_get_contents( WP_ASP_PLUGIN_PATH . 'public/assets/css/public.min.css' );
	    $output	 .= '<style type="text/css">' . $style . '</style>';
	    //addons can output their styles if needed
	    $output	 = apply_filters( 'asp-button-output-additional-styles', $output );
	    ob_start();
	    ?>
	    <div class="asp-processing-cont"><span class="asp-processing">Processing <i>.</i><i>.</i><i>.</i></span></div>
	    <?php
	    $output	 .= ob_get_clean();
	}
	return $output;
    }

    function get_scripts( $data ) {
	$output = '';
	if ( $this->CompatMode ) {
	    ob_start();
	    ?>

	    <script type='text/javascript'>
	        var stripehandler = <?php echo json_encode( $this->get_loc_data() ); ?>;
	    </script>
	    <script type='text/javascript'>
	        var stripehandler<?php echo $data[ 'uniq_id' ]; ?> = <?php echo json_encode( array( 'data' => $data ) ); ?>;
	    </script>
	    <script type='text/javascript' src='https://checkout.stripe.com/checkout.js'></script>
	    <script type='text/javascript' src='<?php echo WP_ASP_PLUGIN_URL; ?>/public/assets/js/stripe-handler.js?ver=<?php echo WP_ASP_PLUGIN_VERSION; ?>'></script>
	    <?php
	    $output .= ob_get_clean();
	} else {
	    //Let's enqueue Stripe js
	    wp_enqueue_script( 'stripe-script' );
	    //using nested array in order to ensure boolean values are not converted to strings by wp_localize_script function
	    wp_localize_script( 'stripe-handler', 'stripehandler' . $data[ 'uniq_id' ], array( 'data' => $data ) );
	    //enqueue our script that handles the stuff
	    wp_enqueue_script( 'stripe-handler' );
	}
	//addons can enqueue their scripts if needed
	do_action( 'asp-button-output-enqueue-script' );
	return $output;
    }

    function get_button_code_new_method( $data ) {
	$output = '';

	if ( ! $data[ 'out_of_stock' ] ) {

	    if ( $data[ 'amount' ] == 0 ) { //price not specified, let's add an input box for user to specify the amount
		$output .= "<div class='asp_product_item_amount_input_container'>"
		. "<input type='text' size='10' class='asp_product_item_amount_input' id='stripeAmount_{$data[ 'uniq_id' ]}' value='' name='stripeAmount' placeholder='" . __( 'Enter amount', 'stripe-payments' ) . "' required/>"
		. "<span class='asp_product_item_amount_currency_label' style='margin-left: 5px; display: inline-block'> {$data[ 'currency' ]}</span>"
		. "<span style='display: block;' id='error_explanation_{$data[ 'uniq_id' ]}'></span>"
		. "</div>";
	    }
	    if ( $data[ 'custom_quantity' ] === "1" ) { //we should output input for customer to input custom quantity
		if ( empty( $data[ 'quantity' ] ) ) {
		    //If quantity option is enabled and the value is empty then set default quantity to 1 so the number field type can handle it better.
		    $data[ 'quantity' ] = 1;
		}
		$output .= "<div class='asp_product_item_qty_input_container'>"
		. "<input type='number' min='1' size='6' class='asp_product_item_qty_input' id='stripeCustomQuantity_{$data[ 'uniq_id' ]}' value='{$data[ 'quantity' ]}' name='stripeCustomQuantity' placeholder='" . __( 'Enter quantity', 'stripe-payments' ) . "' value='{$data[ 'quantity' ]}' required/>"
		. "<span class='asp_product_item_qty_label' style='margin-left: 5px; display: inline-block'> " . __( 'X item(s)', 'stripe-payments' ) . "</span>"
		. "<span style='display: block;' id='error_explanation_quantity_{$data[ 'uniq_id' ]}'></span>"
		. "</div>";
	    }
	    if ( $data[ 'custom_field' ] == 1 ) {
		$field_type	 = $this->AcceptStripePayments->get_setting( 'custom_field_type' );
		$field_name	 = $this->AcceptStripePayments->get_setting( 'custom_field_name' );
		$field_descr	 = $this->AcceptStripePayments->get_setting( 'custom_field_descr' );
		$descr_loc	 = $this->AcceptStripePayments->get_setting( 'custom_field_descr_location' );
		$mandatory	 = $this->AcceptStripePayments->get_setting( 'custom_field_mandatory' );
		$output		 .= "<div class='asp_product_custom_field_input_container'>";
		$output		 .= '<input type="hidden" name="stripeCustomFieldName" value="' . esc_attr( $field_name ) . '">';
		switch ( $field_type ) {
		    case 'text':
			if ( $descr_loc !== 'below' ) {
			    $output .= '<label class="asp_product_custom_field_label">' . $field_name . ' ' . '</label><input id="asp-custom-field-' . $data[ 'uniq_id' ] . '" class="asp_product_custom_field_input" type="text"' . ($mandatory ? ' data-asp-custom-mandatory' : '') . ' name="stripeCustomField" placeholder="' . $field_descr . '"' . ($mandatory ? ' required' : '' ) . '>';
			} else {
			    $output	 .= '<label class="asp_product_custom_field_label">' . $field_name . ' ' . '</label><input id="asp-custom-field-' . $data[ 'uniq_id' ] . '" class="asp_product_custom_field_input" type="text"' . ($mandatory ? ' data-asp-custom-mandatory' : '') . ' name="stripeCustomField"' . ($mandatory ? ' required' : '' ) . '>';
			    $output	 .= '<div class="asp_product_custom_field_descr">' . $field_descr . '</div>';
			}
			break;
		    case 'checkbox':
			$output .= '<label class="asp_product_custom_field_label"><input id="asp-custom-field-' . $data[ 'uniq_id' ] . '" class="asp_product_custom_field_input" type="checkbox"' . ($mandatory ? ' data-asp-custom-mandatory' : '') . ' name="stripeCustomField"' . ($mandatory ? ' required' : '' ) . '>' . $field_descr . '</label>';
			break;
		}
		$output .= "<span style='display: block;' id='custom_field_error_explanation_{$data[ 'uniq_id' ]}'></span>" .
		"</div>";
	    }
	    //Terms and Conditions
	    if ( $data[ 'tos' ] == 1 ) {
		$tos_text	 = $this->AcceptStripePayments->get_setting( 'tos_text' );
		$output		 .= '<div class="asp_product_tos_input_container">';
		$output		 .= '<label class="asp_product_tos_label"><input id="asp-tos-' . $data[ 'uniq_id' ] . '" class="asp_product_tos_input" type="checkbox" required>' . html_entity_decode( $tos_text ) . '</label>';
		$output		 .= "<span style='display: block;' id='tos_error_explanation_{$data[ 'uniq_id' ]}'></span>";
		$output		 .= '</div>';
	    }
	}
	if ( $data ) {
	    if ( $data[ 'product_id' ] !== 0 ) {
		$output .= "<input type='hidden' id='stripeProductId_{$data[ 'uniq_id' ]}' name='stripeProductId' value='{$data[ 'product_id' ]}' />";
	    }
	    $output .= "<input type='hidden' id='stripeToken_{$data[ 'uniq_id' ]}' name='stripeToken' />"
	    . "<input type='hidden' id='stripeTokenType_{$data[ 'uniq_id' ]}' name='stripeTokenType' />"
	    . "<input type='hidden' id='stripeEmail_{$data[ 'uniq_id' ]}' name='stripeEmail' />"
	    . "<input type='hidden' name='stripeButtonKey' value='{$data[ 'button_key' ]}' />"
	    . "<input type='hidden' name='stripeItemPrice' value='{$data[ 'amount' ]}' />"
	    . "<input type='hidden' data-stripe-button-uid='{$data[ 'uniq_id' ]}' />"
	    . "<input type='hidden' name='stripeTax' value='{$data[ 'tax' ]}' />"
	    . "<input type='hidden' name='stripeShipping' value='{$data[ 'shipping' ]}' />"
	    . "<input type='hidden' name='stripeItemCost' value='{$data[ 'item_price' ]}' />";
	}

	return $output;
    }

    function shortcode_accept_stripe_payment_checkout( $atts, $content = '' ) {
	if ( ! defined( 'DONOTCACHEPAGE' ) ) {
	    define( 'DONOTCACHEPAGE', true );
	}
	$aspData = array();
	if ( isset( $_SESSION[ 'asp_data' ] ) ) {
	    $aspData = $_SESSION[ 'asp_data' ];
	} else {
	    // no session data, let's display nothing for now
	    return;
	}
	if ( empty( $content ) ) {
	    //this is old shortcode. Let's display the default output for backward compatability
	    if ( isset( $aspData[ 'error_msg' ] ) && ! empty( $aspData[ 'error_msg' ] ) ) {
		//some error occured, let's display it
		return __( "System was not able to complete the payment.", "stripe-payments" ) . ' ' . $aspData[ 'error_msg' ];
	    }
	    $output	 = '';
	    $output	 .= '<p class="asp-thank-you-page-msg1">' . __( "Thank you for your payment.", "stripe-payments" ) . '</p>';
	    $output	 .= '<p class="asp-thank-you-page-msg2">' . __( "Here's what you purchased: ", "stripe-payments" ) . '</p>';
	    $output	 .= '<div class="asp-thank-you-page-product-name">' . __( "Product Name: ", "stripe-payments" ) . $aspData[ 'item_name' ] . '</div>';
	    $output	 .= '<div class="asp-thank-you-page-qty">' . __( "Quantity: ", "stripe-payments" ) . $aspData[ 'item_quantity' ] . '</div>';
	    $output	 .= '<div class="asp-thank-you-page-qty">' . __( "Item Price: ", "stripe-payments" ) . AcceptStripePayments::formatted_price( $aspData[ 'item_price' ], $aspData[ 'currency_code' ] ) . '</div>';
	    //check if there are any additional items available like tax and shipping cost
	    if ( ! empty( $aspData[ 'additional_items' ] ) ) {
		foreach ( $aspData[ 'additional_items' ] as $item => $price ) {
		    $output .= $item . ": " . AcceptStripePayments::formatted_price( $price, $aspData[ 'currency_code' ] ) . "<br />";
		}
	    }
	    $output	 .= '<hr />';
	    $output	 .= '<div class="asp-thank-you-page-qty">' . __( "Total Amount: ", "stripe-payments" ) . AcceptStripePayments::formatted_price( $aspData[ 'paid_amount' ], $aspData[ 'currency_code' ] ) . '</div>';
	    $output	 .= '<br />';
	    $output	 .= '<div class="asp-thank-you-page-txn-id">' . __( "Transaction ID: ", "stripe-payments" ) . $aspData[ 'txn_id' ] . '</div>';

	    if ( ! empty( $aspData[ 'item_url' ] ) ) {
		$output	 .= "<div class='asp-thank-you-page-download-link'>";
		$output	 .= __( "Please ", "stripe-payments" ) . "<a href='" . $aspData[ 'item_url' ] . "'>" . __( "click here", "stripe-payments" ) . "</a>" . __( " to download.", "stripe-payments" );
		$output	 .= "</div>";
	    }

	    $output = apply_filters( 'asp_stripe_payments_checkout_page_result', $output, $aspData ); //Filter that allows you to modify the output data on the checkout result page

	    $wrap	 = "<div class='asp-thank-you-page-wrap'>";
	    $wrap	 .= "<div class='asp-thank-you-page-msg-wrap' style='background: #dff0d8; border: 1px solid #C9DEC1; margin: 10px 0px; padding: 15px;'>";
	    $output	 = $wrap . $output;
	    $output	 .= "</div>"; //end of .asp-thank-you-page-msg-wrap
	    $output	 .= "</div>"; //end of .asp-thank-you-page-wrap

	    return $output;
	}
	if ( isset( $aspData[ 'error_msg' ] ) && ! empty( $aspData[ 'error_msg' ] ) ) {
	    //some error occured. We don't display any content to let the error shortcode handle it
	    return;
	}
	$content = $this->apply_content_tags( do_shortcode( $content ), $aspData );
	return $content;
    }

    function shortcode_accept_stripe_payment_checkout_error( $atts, $content = '' ) {
	$aspData = array();
	if ( isset( $_SESSION[ 'asp_data' ] ) ) {
	    $aspData = $_SESSION[ 'asp_data' ];
	} else {
	    // no session data, let's display nothing for now
	    return;
	}
	if ( isset( $aspData[ 'error_msg' ] ) && ! empty( $aspData[ 'error_msg' ] ) ) {
	    //some error occured. Let's display error message
	    $content = $this->apply_content_tags( do_shortcode( $content ), $aspData );
	    return $content;
	}
	// no error occured - we don't display anything
	return;
    }

    function shortcode_show_all_products( $params ) {

	include_once(WP_ASP_PLUGIN_PATH . 'public/views/all-products/default/template.php');

	$page = isset( $_GET[ 'asp_page' ] ) && ! empty( $_GET[ 'asp_page' ] ) ? intval( $_GET[ 'asp_page' ] ) : 1;

	$q = array(
	    'post_type'	 => ASPMain::$products_slug,
	    'post_status'	 => 'publish',
	    'posts_per_page' => $params[ 'items_per_page' ],
	    'paged'		 => $page,
	    'orderby'	 => $params[ 'sort_by' ],
	    'order'		 => strtoupper( $params[ 'sort_order' ] ),
	);

	//handle search

	$search = isset( $_GET[ 'asp_search' ] ) && ! empty( $_GET[ 'asp_search' ] ) ? sanitize_text_field( $_GET[ 'asp_search' ] ) : false;

	if ( $search !== false ) {
	    $q[ 's' ] = $search;
	}

	$products = new WP_Query( $q );

	if ( ! $products->have_posts() ) {
	    //query returned no results. Let's see if that was a search query
	    if ( $search === false ) {
		//that wasn't search query. That means there is no products configured
		return __( 'No products have been configured yet', 'stripe-payments' );
	    }
	}

	if ( $params[ 'search_box' ] !== '1' ) {
	    $tpl[ 'search_box' ] = '';
	} else {
	    if ( $search !== false ) {
		$tpl[ 'clear_search_url' ]	 = esc_url( remove_query_arg( array( 'asp_search', 'asp_page' ) ) );
		$tpl[ 'search_result_text' ]	 = $products->found_posts === 0 ? __( 'Nothing found for', 'stripe-payments' ) . ' "%s".' : __( 'Search results for', 'stripe-payments' ) . ' "%s".';
		$tpl[ 'search_result_text' ]	 = sprintf( $tpl[ 'search_result_text' ], htmlentities( $search ) );
		$tpl[ 'search_term' ]		 = htmlentities( $search );
	    } else {
		$tpl[ 'search_result_text' ]	 = '';
		$tpl[ 'clear_search_button' ]	 = '';
		$tpl[ 'search_term' ]		 = '';
	    }
	}

	$tpl[ 'products_list' ]	 .= $tpl[ 'products_row_start' ];
	$i			 = $tpl[ 'products_per_row' ]; //items per row

	while ( $products->have_posts() ) {
	    $products->the_post();
	    $i --;
	    if ( $i < 0 ) { //new row
		$tpl[ 'products_list' ]	 .= $tpl[ 'products_row_end' ];
		$tpl[ 'products_list' ]	 .= $tpl[ 'products_row_start' ];
		$i			 = $tpl[ 'products_per_row' ] - 1;
	    }

	    $id = get_the_ID();

	    $thumb_url = get_post_meta( $id, 'asp_product_thumbnail', true );
	    if ( ! $thumb_url ) {
		$thumb_url = WP_ASP_PLUGIN_URL . '/assets/product-thumb-placeholder.png';
	    }

	    $view_btn = str_replace( '%[product_url]%', get_permalink(), $tpl[ 'view_product_btn' ] );

	    $price	 = get_post_meta( $id, 'asp_product_price', true );
	    $curr	 = get_post_meta( $id, 'asp_product_currency', true );
	    $price	 = AcceptStripePayments::formatted_price( $price, $curr );
	    if ( empty( $price ) ) {
		$price = '&nbsp';
	    }

	    $item			 = str_replace(
	    array(
		'%[product_id]%', '%[product_name]%', '%[product_thumb]%', '%[view_product_btn]%', '%[product_price]%'
	    ), array(
		$id, get_the_title(), $thumb_url, $view_btn, $price
	    ), $tpl[ 'products_item' ] );
	    $tpl[ 'products_list' ]	 .= $item;
	}

	$tpl[ 'products_list' ] .= $tpl[ 'products_row_end' ];

	//pagination

	$tpl[ 'pagination_items' ] = '';

	$pages = $products->max_num_pages;

	if ( $pages > 1 ) {
	    $i = 1;

	    while ( $i <= $pages ) {
		if ( $i != $page ) {
		    $url	 = esc_url( add_query_arg( 'asp_page', $i ) );
		    $str	 = str_replace( array( '%[url]%', '%[page_num]%' ), array( $url, $i ), $tpl[ 'pagination_item' ] );
		} else
		    $str				 = str_replace( '%[page_num]%', $i, $tpl[ 'pagination_item_current' ] );
		$tpl[ 'pagination_items' ]	 .= $str;
		$i ++;
	    }
	}

	if ( empty( $tpl[ 'pagination_items' ] ) ) {
	    $tpl[ 'pagination' ] = '';
	}

	wp_reset_postdata();

	//Build template
	foreach ( $tpl as $key => $value ) {
	    $tpl[ 'page' ] = str_replace( '_%' . $key . '%_', $value, $tpl[ 'page' ] );
	}

	return $tpl[ 'page' ];
    }

    function apply_content_tags( $content, $data ) {
	$tags	 = array();
	$vals	 = array();

	if ( isset( $data[ 'custom_field_value' ] ) ) {
	    $data[ 'custom_field' ] = $data[ 'custom_field_name' ] . ': ' . $data[ 'custom_field_value' ];
	} else {
	    $data[ 'custom_field' ] = '';
	}

	foreach ( $data as $key => $value ) {
	    if ( $key == 'stripeEmail' ) {
		$key = 'payer_email';
	    }
	    if ( $key == 'txn_id' ) {
		$key = 'transaction_id';
	    }
	    $tags[]	 = '{' . $key . '}';
	    $vals[]	 = is_array( $value ) ? '' : $value;
	}

	$content = str_replace( $tags, $vals, $content );
	return $content;
    }

}
