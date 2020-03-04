<?php

class AcceptStripePaymentsShortcode {

	var $AcceptStripePayments = null;
	var $StripeCSSInserted    = false;
	var $ProductCSSInserted   = false;
	var $ButtonCSSInserted    = false;
	var $CompatMode           = false;
	var $variations           = array();

	/**
	 * Instance of this class.
	 *
	 * @var      object
	 */
	protected static $instance        = null;
	protected static $payment_buttons = array();
	protected $tplTOS                 = '';
	protected $tplCF                  = '';

	function __construct() {
		self::$instance = $this;

		$this->AcceptStripePayments = AcceptStripePayments::get_instance();

		$use_old_api = $this->AcceptStripePayments->get_setting( 'use_old_checkout_api1' );

		if ( $use_old_api ) {
			add_shortcode( 'asp_product', array( &$this, 'shortcode_asp_product' ) );
			add_shortcode( 'accept_stripe_payment', array( &$this, 'shortcode_accept_stripe_payment' ) );
			add_filter( 'the_content', array( $this, 'filter_post_type_content' ) );
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'register_stripe_script' ) );

		add_shortcode( 'asp_show_all_products', array( &$this, 'shortcode_show_all_products' ) );
		add_shortcode( 'accept_stripe_payment_checkout', array( &$this, 'shortcode_accept_stripe_payment_checkout' ) );
		add_shortcode( 'accept_stripe_payment_checkout_error', array( &$this, 'shortcode_accept_stripe_payment_checkout_error' ) );
		add_shortcode( 'asp_show_my_transactions', array( $this, 'show_user_transactions' ) );
		if ( ! is_admin() ) {
			add_filter( 'widget_text', 'do_shortcode' );
		}
	}

	public static function filter_post_type_content( $content ) {
		global $post;
		if ( isset( $post ) ) {
			if ( $post->post_type === ASPMain::$products_slug ) {//Handle the content for product type post
				return do_shortcode( '[asp_product id="' . $post->ID . '" is_post_tpl="1" in_the_loop="' . + in_the_loop() . '"]' );
			}
		}
		return $content;
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
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	function get_loc_data() {
		//localization data, some settings and Stripe API key
		$key        = $this->AcceptStripePayments->APIPubKey;
		$key_test   = $this->AcceptStripePayments->APIPubKeyTest;
		$minAmounts = $this->AcceptStripePayments->minAmounts;
		$zeroCents  = $this->AcceptStripePayments->zeroCents;

		$amountOpts = array(
			'applySepOpts' => $this->AcceptStripePayments->get_setting( 'price_apply_for_input' ),
			'decimalSep'   => $this->AcceptStripePayments->get_setting( 'price_decimal_sep' ),
			'thousandSep'  => $this->AcceptStripePayments->get_setting( 'price_thousand_sep' ),
		);

		$loc_data = array(
			'strEnterValidAmount'         => apply_filters( 'asp_customize_text_msg', __( 'Please enter a valid amount', 'stripe-payments' ), 'enter_valid_amount' ),
			'strMinAmount'                => apply_filters( 'asp_customize_text_msg', __( 'Minimum amount is', 'stripe-payments' ), 'min_amount_is' ),
			'strEnterQuantity'            => apply_filters( 'asp_customize_text_msg', __( 'Please enter quantity.', 'stripe-payments' ), 'enter_quantity' ),
			'strQuantityIsZero'           => apply_filters( 'asp_customize_text_msg', __( 'Quantity can\'t be zero.', 'stripe-payments' ), 'quantity_is_zero' ),
			'strQuantityIsFloat'          => apply_filters( 'asp_customize_text_msg', __( 'Quantity should be integer value.', 'stripe-payments' ), 'quantity_is_float' ),
			'strStockNotAvailable'        => apply_filters( 'asp_customize_text_msg', __( 'You cannot order more items than available: %d', 'stripe-payments' ), 'stock_not_available' ),
			'strTax'                      => apply_filters( 'asp_customize_text_msg', __( 'Tax', 'stripe-payments' ), 'tax_str' ),
			'strShipping'                 => apply_filters( 'asp_customize_text_msg', __( 'Shipping', 'stripe-payments' ), 'shipping_str' ),
			'strTotal'                    => __( 'Total:', 'stripe-payments' ),
			'strPleaseFillIn'             => apply_filters( 'asp_customize_text_msg', __( 'Please fill in this field.', 'stripe-payments' ), 'fill_in_field' ),
			'strPleaseCheckCheckbox'      => __( 'Please check this checkbox.', 'stripe-payments' ),
			'strMustAcceptTos'            => apply_filters( 'asp_customize_text_msg', __( 'You must accept the terms before you can proceed.', 'stripe-payments' ), 'accept_terms' ),
			'strRemoveCoupon'             => apply_filters( 'asp_customize_text_msg', __( 'Remove coupon', 'stripe-payments' ), 'remove_coupon' ),
			'strRemove'                   => apply_filters( 'asp_customize_text_msg', __( 'Remove', 'stripe-payments' ), 'remove' ),
			'strStartFreeTrial'           => apply_filters( 'asp_customize_text_msg', __( 'Start Free Trial', 'stripe-payments' ), 'start_free_trial' ),
			'strInvalidCFValidationRegex' => __( 'Invalid validation RegEx: ', 'stripe-payments' ),
			'key'                         => $key,
			'key_test'                    => $key_test,
			'ajax_url'                    => admin_url( 'admin-ajax.php' ),
			'minAmounts'                  => $minAmounts,
			'zeroCents'                   => $zeroCents,
			'amountOpts'                  => $amountOpts,
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

	function after_button_add_tos_filter( $output, $data, $class ) {
		$output       = apply_filters( 'asp_button_output_before_tos', $output, $data );
		$output      .= $this->tplTOS;
		$this->tplTOS = '';
		return $output;
	}

	function after_button_add_сf_filter( $output, $data, $class ) {
		$output     .= $this->tplCF;
		$this->tplCF = '';
		return $output;
	}

	function tpl_get_tos( $output, $data ) {
		if ( $data['tos'] == 1 && empty( $this->tplTOS ) ) {
			$tos_text     = $this->AcceptStripePayments->get_setting( 'tos_text' );
			$tplTOS       = '';
			$tplTOS      .= '<div class="asp_product_tos_input_container">';
			$tplTOS      .= '<label class="asp_product_tos_label"><input id="asp-tos-' . $data['uniq_id'] . '" class="asp_product_tos_input" type="checkbox" required>' . html_entity_decode( $tos_text ) . '</label>';
			$tplTOS      .= "<span style='display: block;' id='tos_error_explanation_{$data[ 'uniq_id' ]}'></span>";
			$tplTOS      .= '</div>';
			$this->tplTOS = $tplTOS;
		}
		$tosPos = $this->AcceptStripePayments->get_setting( 'tos_position' );
		if ( $tosPos !== 'below' ) {
			$output       = apply_filters( 'asp_button_output_before_tos', $output, $data );
			$output      .= $this->tplTOS;
			$this->tplTOS = '';
		} else {
			add_filter( 'asp_button_output_after_button', array( $this, 'after_button_add_tos_filter' ), 1000, 3 );
		}
		return $output;
	}

	function tpl_get_cf( $output, $data ) {
		if ( $data['custom_field'] == 1 && empty( $this->tplCF ) ) {
			$replaceCF = apply_filters( 'asp_button_output_replace_custom_field', '', $data );
			if ( ! empty( $replaceCF ) ) {
				//we got custom field replaced
				$this->tplCF = $replaceCF;
				$output     .= $this->tplCF;
				$this->tplCF = '';
				return $output;
			}
			$field_type  = $this->AcceptStripePayments->get_setting( 'custom_field_type' );
			$field_name  = $this->AcceptStripePayments->get_setting( 'custom_field_name' );
			$field_name  = empty( $field_name ) ? __( 'Custom Field', 'stripe-payments' ) : $field_name;
			$field_descr = $this->AcceptStripePayments->get_setting( 'custom_field_descr' );
			$descr_loc   = $this->AcceptStripePayments->get_setting( 'custom_field_descr_location' );
			$mandatory   = $this->AcceptStripePayments->get_setting( 'custom_field_mandatory' );
			$tplCF       = '';
			$tplCF      .= "<div class='asp_product_custom_field_input_container'>";
			$tplCF      .= '<input type="hidden" name="stripeCustomFieldName" value="' . esc_attr( $field_name ) . '">';
			switch ( $field_type ) {
				case 'text':
					if ( $descr_loc !== 'below' ) {
						$tplCF .= '<label class="asp_product_custom_field_label">' . $field_name . ' ' . '</label><input id="asp-custom-field-' . $data['uniq_id'] . '" class="asp_product_custom_field_input" type="text"' . ( $mandatory ? ' data-asp-custom-mandatory' : '' ) . ' name="stripeCustomField" placeholder="' . $field_descr . '"' . ( $mandatory ? ' required' : '' ) . '>';
					} else {
						$tplCF .= '<label class="asp_product_custom_field_label">' . $field_name . ' ' . '</label><input id="asp-custom-field-' . $data['uniq_id'] . '" class="asp_product_custom_field_input" type="text"' . ( $mandatory ? ' data-asp-custom-mandatory' : '' ) . ' name="stripeCustomField"' . ( $mandatory ? ' required' : '' ) . '>';
						$tplCF .= '<div class="asp_product_custom_field_descr">' . $field_descr . '</div>';
					}
					break;
				case 'checkbox':
					$tplCF .= '<label class="asp_product_custom_field_label"><input id="asp-custom-field-' . $data['uniq_id'] . '" class="asp_product_custom_field_input" type="checkbox"' . ( $mandatory ? ' data-asp-custom-mandatory' : '' ) . ' name="stripeCustomField"' . ( $mandatory ? ' required' : '' ) . '>' . $field_descr . '</label>';
					break;
			}
			$tplCF      .= "<span id='custom_field_error_explanation_{$data[ 'uniq_id' ]}' class='asp_product_custom_field_error'></span>" .
			'</div>';
			$this->tplCF = $tplCF;
		}
		$cfPos = $this->AcceptStripePayments->get_setting( 'custom_field_position' );
		if ( $cfPos !== 'below' ) {
			$output     .= $this->tplCF;
			$this->tplCF = '';
		} else {
			add_filter( 'asp_button_output_after_button', array( $this, 'after_button_add_сf_filter' ), 990, 3 );
		}
		return $output;
	}

	function show_user_transactions( $atts ) {
		$atts = shortcode_atts(
			array(
				'items_per_page'           => '20',
				'show_subscription_cancel' => 0,
				'show_download_link'       => 1,
			),
			$atts,
			'asp_show_my_transactions'
		);
		require_once WP_ASP_PLUGIN_PATH . 'public/includes/shortcodes/show-user-transactions.php';
		$scClass = new AcceptStripePayments_scUserTransactions();
		return $scClass->process_shortcode( $atts );
	}

	function shortcode_asp_product( $atts ) {
		if ( ! isset( $atts['id'] ) || ! is_numeric( $atts['id'] ) ) {
			$error_msg  = '<div class="stripe_payments_error_msg" style="color: red;">';
			$error_msg .= 'Error: product ID is invalid.';
			$error_msg .= '</div>';
			return $error_msg;
		}
		$id   = $atts['id'];
		$post = get_post( $id );
		if ( ! $post || get_post_type( $id ) != ASPMain::$products_slug ) {
			$error_msg  = '<div class="stripe_payments_error_msg" style="color: red;">';
			$error_msg .= "Can't find product with ID " . $id;
			$error_msg .= '</div>';
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

		if ( isset( $atts['button_text'] ) && ! empty( $atts['button_text'] ) ) {
			$button_text = esc_attr( $atts['button_text'] );
		}

		$thumb_img = '';
		$thumb_url = get_post_meta( $id, 'asp_product_thumbnail', true );

		if ( $thumb_url ) {
			$thumb_img = '<img src="' . $thumb_url . '">';
		}

		$url = get_post_meta( $id, 'asp_product_upload', true );

		if ( ! $url ) {
			$url = '';
		}

		$template_name = 'default'; //this could be made configurable
		$button_color  = 'blue'; //this could be made configurable

		$price    = get_post_meta( $id, 'asp_product_price', true );
		$price    = empty( $price ) ? 0 : $price;
		$shipping = get_post_meta( $id, 'asp_product_shipping', true );

		$plan_id = get_post_meta( $id, 'asp_sub_plan_id', true );

		if ( empty( $plan_id ) ) {
			//let's apply filter so addons can change price, currency and shipping if needed
			$price_arr = array(
				'price'    => $price,
				'currency' => $currency,
				'shipping' => empty( $shipping ) ? false : $shipping,
			);
			$price_arr = apply_filters( 'asp_modify_price_currency_shipping', $price_arr );
			extract( $price_arr, EXTR_OVERWRITE );
		}

		$buy_btn = '';

		$button_class = get_post_meta( $id, 'asp_product_button_class', true );

		$class = ! empty( $button_class ) ? $button_class : 'asp_product_buy_btn ' . $button_color;

		$class = isset( $atts['class'] ) ? $atts['class'] : $class;

		$custom_field = get_post_meta( $id, 'asp_product_custom_field', true );
		$cf_enabled   = $this->AcceptStripePayments->get_setting( 'custom_field_enabled' );

		if ( ( $custom_field === '' ) || $custom_field === '2' ) {
			$custom_field = $cf_enabled;
		} else {
			$custom_field = intval( $custom_field );
		}

		if ( ! $cf_enabled ) {
			$custom_field = $cf_enabled;
		}

		$coupons_enabled = get_post_meta( $id, 'asp_product_coupons_setting', true );

		if ( ( $coupons_enabled === '' ) || $coupons_enabled === '2' ) {
			$coupons_enabled = $this->AcceptStripePayments->get_setting( 'coupons_enabled' );
		}

		$thankyou_page = empty( $atts['thankyou_page_url'] ) ? get_post_meta( $id, 'asp_product_thankyou_page', true ) : $atts['thankyou_page_url'];

		if ( ! $shipping ) {
			$shipping = 0;
		}

		$tax = get_post_meta( $id, 'asp_product_tax', true );

		if ( ! $tax ) {
			$tax = 0;
		}

		$quantity = get_post_meta( $id, 'asp_product_quantity', true );

		$under_price_line = '';
		$tot_price        = ! empty( $quantity ) ? $price * $quantity : $price;

		if ( $tax !== 0 ) {
			$taxStr = apply_filters( 'asp_customize_text_msg', __( 'Tax', 'stripe-payments' ), 'tax_str' );
			if ( ! empty( $price ) ) {
				$tax_amount       = AcceptStripePayments::get_tax_amount( $tot_price, $tax, AcceptStripePayments::is_zero_cents( $currency ) );
				$tot_price       += $tax_amount;
				$under_price_line = '<span class="asp_price_tax_section">' . AcceptStripePayments::formatted_price( $tax_amount, $currency ) . ' (' . strtolower( $taxStr ) . ')' . '</span>';
			} else {
				$under_price_line = '<span class="asp_price_tax_section">' . $tax . '% ' . lcfirst( $taxStr ) . '</span>';
			}
		}
		if ( $shipping !== 0 ) {
			$shipStr       = apply_filters( 'asp_customize_text_msg', __( 'Shipping', 'stripe-payments' ), 'shipping_str' );
			$tot_price    += $shipping;
			$shipping_line = AcceptStripePayments::formatted_price( $shipping, $currency ) . ' (' . strtolower( $shipStr ) . ')';
			if ( ! empty( $under_price_line ) ) {
				$under_price_line .= '<span class="asp_price_shipping_section">' . ' + ' . $shipping_line . '</span>';
			} else {
				$under_price_line = '<span class="asp_price_shipping_section">' . $shipping_line . '</span>';
			}
		}

		if ( ! empty( $price ) && ! empty( $under_price_line ) ) {
			$under_price_line .= '<div class="asp_price_full_total">' . __( 'Total:', 'stripe-payments' ) . ' <span class="asp_tot_current_price">' . AcceptStripePayments::formatted_price( $tot_price, $currency ) . '</span> <span class="asp_tot_new_price"></span></div>';
		}

		if ( get_post_meta( $id, 'asp_product_no_popup_thumbnail', true ) != 1 ) {
			$item_logo = ASP_Utils::get_small_product_thumb( $id );
		} else {
			$item_logo = '';
		}

		$compat_mode = isset( $atts['compat_mode'] ) ? 1 : 0;

		$this->CompatMode = ( $compat_mode ) ? true : false;

		$billing_address  = get_post_meta( $id, 'asp_product_collect_billing_addr', true );
		$shipping_address = get_post_meta( $id, 'asp_product_collect_shipping_addr', true );

		if ( ! $billing_address ) {
			$shipping_address = false;
		}

		$currency_variable = get_post_meta( $id, 'asp_product_currency_variable', true );
		$currency_variable = ! empty( $currency_variable ) ? true : false;

		//Let's only output buy button if we're in the loop. Since the_content hook could be called several times (for example, by a plugin like Yoast SEO for its purposes), we should only output the button only when it's actually needed.
		if ( ! isset( $atts['in_the_loop'] ) || $atts['in_the_loop'] === '1' ) {
			$sc_params = array(
				'product_id'        => $id,
				'name'              => $post->post_title,
				'price'             => $price,
				'currency'          => $currency,
				'currency_variable' => $currency_variable,
				'shipping'          => $shipping,
				'tax'               => $tax,
				'class'             => $class,
				'quantity'          => get_post_meta( $id, 'asp_product_quantity', true ),
				'custom_quantity'   => get_post_meta( $id, 'asp_product_custom_quantity', true ),
				'button_text'       => $button_text,
				'description'       => get_post_meta( $id, 'asp_product_description', true ),
				'url'               => $url,
				'thankyou_page_url' => $thankyou_page,
				'item_logo'         => $item_logo,
				'billing_address'   => $billing_address,
				'shipping_address'  => $shipping_address,
				'custom_field'      => $custom_field,
				'coupons_enabled'   => $coupons_enabled,
				'compat_mode'       => $compat_mode,
				'button_only'       => isset( $atts['button_only'] ) ? intval( $atts['button_only'] ) : null,
			);
			//this would pass additional shortcode parameters from asp_product shortcode
			$sc_params = array_merge( $atts, $sc_params );
			$buy_btn   = $this->shortcode_accept_stripe_payment( $sc_params );
		}

		if ( ! isset( $sc_params['button_only'] ) ) {
			$button_only = get_post_meta( $id, 'asp_product_button_only', true );
		} else {
			$button_only = $sc_params['button_only'];
		}

		if ( ( isset( $atts['fancy'] ) && $atts['fancy'] == '0' ) || $button_only == 1 ) {
			//Just show the stripe payment button (no fancy template)
			$tpl = '<div class="asp_product_buy_button">' . $buy_btn . '</div>';
			$tpl = "<link rel='stylesheet' href='" . WP_ASP_PLUGIN_URL . '/public/views/templates/default/style.css' . "' type='text/css' media='all' />" . $tpl;
			if ( ! $this->CompatMode ) {
				$this->productCSSInserted = true;
			}
			return $tpl;
		}

		//Show the stripe payment button with fancy style template.
		require_once WP_ASP_PLUGIN_PATH . 'public/views/templates/' . $template_name . '/template.php';
		if ( isset( $atts['is_post_tpl'] ) ) {
			$tpl = asp_get_post_template( $this->ProductCSSInserted );
		} else {
			$tpl = asp_get_template( $this->ProductCSSInserted );
		}
		if ( ! $this->CompatMode ) {
			$this->productCSSInserted = true;
		}

		$price_line = empty( $price ) ? '' : AcceptStripePayments::formatted_price( $price, $currency );

		$qntStr = '';
		if ( $quantity && $quantity != 1 ) {
			$qntStr = 'x ' . $quantity;
		}

		remove_filter( 'the_content', array( $this, 'filter_post_type_content' ) );
		setup_postdata( $post );
		$GLOBALS['post'] = $post;
		$descr           = $post->post_content;
		global $wp_embed;
		if ( isset( $wp_embed ) && is_object( $wp_embed ) ) {
			if ( method_exists( $wp_embed, 'autoembed' ) ) {
				$descr = $wp_embed->autoembed( $descr );
			}
			if ( method_exists( $wp_embed, 'run_shortcode' ) ) {
				$descr = $wp_embed->run_shortcode( $descr );
			}
		}
		$descr = wpautop( do_shortcode( $descr ) );
		wp_reset_postdata();
		add_filter( 'the_content', array( $this, 'filter_post_type_content' ) );

		$product_tags = array(
			'thumb_img'        => $thumb_img,
			'quantity'         => $qntStr,
			'name'             => $post->post_title,
			'description'      => $descr,
			'price'            => $price_line,
			'under_price_line' => $under_price_line,
			'buy_btn'          => $buy_btn,
		);

		$product_tags = apply_filters( 'asp_product_tpl_tags_arr', $product_tags, $id );

		foreach ( $product_tags as $tag => $repl ) {
			$tpl = str_replace( '%_' . $tag . '_%', $repl, $tpl );
		}

		return $tpl;
	}

	function shortcode_accept_stripe_payment( $atts ) {

		extract(
			shortcode_atts(
				array(
					'product_id'        => 0,
					'name'              => '',
					'class'             => 'stripe-button-el', //default Stripe button class
					'price'             => '0',
					'shipping'          => 0,
					'tax'               => 0,
					'quantity'          => '',
					'custom_quantity'   => false,
					'description'       => '',
					'url'               => '',
					'thankyou_page_url' => '',
					'item_logo'         => '',
					'billing_address'   => '',
					'shipping_address'  => '',
					'customer_email'    => '',
					'currency'          => $this->AcceptStripePayments->get_setting( 'currency_code' ),
					'currency_variable' => false,
					'checkout_lang'     => $this->AcceptStripePayments->get_setting( 'checkout_lang' ),
					'button_text'       => $this->AcceptStripePayments->get_setting( 'button_text' ),
					'compat_mode'       => 0,
				),
				$atts
			)
		);

		$this->CompatMode = ( $compat_mode ) ? true : false;

		if ( empty( $name ) ) {
			$error_msg  = '<div class="stripe_payments_error_msg" style="color: red;">';
			$error_msg .= 'There is an error in your Stripe Payments shortcode. It is missing the "name" field. ';
			$error_msg .= 'You must specify an item name value using the "name" parameter. This value should be unique so this item can be identified uniquely on the page.';
			$error_msg .= '</div>';
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

		if ( empty( $quantity ) && $custom_quantity !== '1' ) {
			$quantity = 1;
		}

		if ( ! is_numeric( $quantity ) ) {
			$quantity = strtoupper( $quantity );
		}
		if ( $quantity == 'N/A' ) {
			$quantity = 'NA';
		}
		$price                   = floatval( $price );
		$uniq_id                 = count( self::$payment_buttons ) . uniqid();
		$button_id               = 'stripe_button_' . $uniq_id;
		self::$payment_buttons[] = $button_id;

		$item_price    = $price;
		$paymentAmount = ( $custom_quantity == '1' ? $price : ( floatval( $price ) * $quantity ) );
		if ( AcceptStripePayments::is_zero_cents( $currency ) ) {
			//this is zero-cents currency, amount shouldn't be multiplied by 100
			$priceInCents = $paymentAmount;
		} else {
			$priceInCents = $paymentAmount * 100;
			$item_price   = $price * 100;
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
				$tax_amount   = round( ( $priceInCents * $tax / 100 ) );
				$priceInCents = $priceInCents + $tax_amount;
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
		$descr_generated = false;
		if ( empty( $description ) && $custom_quantity !== '1' && ( ! empty( $price ) && $price !== 0 ) ) {
			//Create a description using quantity, payment amount and currency
			if ( ! empty( $tax ) || ! empty( $shipping ) ) {
				$formatted_amount = AcceptStripePayments::formatted_price( AcceptStripePayments::is_zero_cents( $currency ) ? $priceInCents : $priceInCents / 100, $currency );
			} else {
				$formatted_amount = AcceptStripePayments::formatted_price( $paymentAmount, $currency );
			}
			$description     = "{$quantity} X " . $formatted_amount;
			$descr_generated = true;
		}

		// Check if "Disable Buttons Before Javascript Loads" option is set
		$is_disabled = '';
		if ( $this->AcceptStripePayments->get_setting( 'disable_buttons_before_js_loads' ) ) {
			$is_disabled = ' disabled';
		}

		//This is public.css stylesheet
		//wp_enqueue_style('stripe-button-public');
		//$button = "<button id = '{$button_id}' type = 'submit' class = '{$class}'><span>{$button_text}</span></button>";
		$button = sprintf( '<div class="asp_product_buy_btn_container"><button id="%s" type="submit" class="%s"%s><span>%s</span></button></div>', esc_attr( $button_id ), esc_attr( $class ), $is_disabled, sanitize_text_field( $button_text ) );

		$out_of_stock          = false;
		$stock_control_enabled = false;
		$stock_items           = 0;
		//check if stock enabled
		if ( isset( $product_id ) && get_post_meta( $product_id, 'asp_product_enable_stock', true ) ) {
			//check if product is not out of stock
			$stock_items = get_post_meta( $product_id, 'asp_product_stock_items', true );
			if ( empty( $stock_items ) ) {
				$button       = '<div class="asp_out_of_stock">' . __( 'Out of stock', 'stripe-payments' ) . '</div>';
				$out_of_stock = true;
			} else {
				$stock_control_enabled = true;
				$stock_items           = $stock_items;
			}
		}

		//add message if no javascript is enabled
		$button .= '<noscript>' . __( 'Stripe Payments requires Javascript to be supported by the browser in order to operate.', 'stripe-payments' ) . '</noscript>';

		$checkout_lang = empty( $checkout_lang ) ? $this->AcceptStripePayments->get_setting( 'checkout_lang' ) : $checkout_lang;

		$allowRememberMe = $this->AcceptStripePayments->get_setting( 'disable_remember_me' );

		$allowRememberMe = ( $allowRememberMe === 1 ) ? false : true;

		$custom_field = $this->AcceptStripePayments->get_setting( 'custom_field_enabled' );
		if ( isset( $atts['custom_field'] ) ) {
			$custom_field = $atts['custom_field'];
		}

		$cf_validation_regex   = '';
		$cf_validation_err_msg = '';

		if ( $custom_field ) {
			//check if we have custom field validation enabled
			$custom_validation = $this->AcceptStripePayments->get_setting( 'custom_field_validation' );
			if ( ! empty( $custom_validation ) ) {
				if ( $custom_validation === 'num' ) {
					$cf_validation_regex   = '^[0-9]+$';
					$cf_validation_err_msg = __( 'Only numbers are allowed: 0-9', 'stripe-payments' );
				} elseif ( $custom_validation === 'custom' ) {
					$cf_validation_regex   = $this->AcceptStripePayments->get_setting( 'custom_field_custom_validation_regex' );
					$cf_validation_err_msg = $this->AcceptStripePayments->get_setting( 'custom_field_custom_validation_err_msg' );
				}
			}
		}

		$coupons_enabled = $this->AcceptStripePayments->get_setting( 'coupons_enabled' );
		if ( isset( $atts['coupons_enabled'] ) ) {
			$coupons_enabled = $atts['coupons_enabled'];
		}

		$tos = $this->AcceptStripePayments->get_setting( 'tos_enabled' );

		$verifyZip = $this->AcceptStripePayments->get_setting( 'enable_zip_validation' );

		//Currency Display settings
		$display_settings      = array();
		$display_settings['c'] = $this->AcceptStripePayments->get_setting( 'price_decimals_num', 2 );
		$display_settings['d'] = $this->AcceptStripePayments->get_setting( 'price_decimal_sep' );
		$display_settings['t'] = $this->AcceptStripePayments->get_setting( 'price_thousand_sep' );

		$currencies = AcceptStripePayments::get_currencies();
		if ( isset( $currencies[ $currency ] ) ) {
			$curr_sym = $currencies[ $currency ][1];
		} else {
			//no currency code found, let's just use currency code instead of symbol
			$curr_sym = $currencies;
		}

		$curr_pos = $this->AcceptStripePayments->get_setting( 'price_currency_pos' );

		$display_settings['s']   = $curr_sym;
		$display_settings['pos'] = $curr_pos;

		$displayStr = array();
		$taxStr     = apply_filters( 'asp_customize_text_msg', __( 'Tax', 'stripe-payments' ), 'tax_str' );
		$shipStr    = apply_filters( 'asp_customize_text_msg', __( 'Shipping', 'stripe-payments' ), 'shipping_str' );

		$displayStr['tax']  = '%s (' . strtolower( $taxStr ) . ')';
		$displayStr['ship'] = '%s (' . strtolower( $shipStr ) . ')';

		$plan_id = get_post_meta( $product_id, 'asp_sub_plan_id', true );

		//variations
		$this->variations = array();
		$v                = new ASPVariations( $product_id );
		if ( ! empty( $v->groups ) && empty( $plan_id ) ) {
			$this->variations['groups'] = $v->groups;
			$variations_names           = get_post_meta( $product_id, 'asp_variations_names', true );
			$variations_prices_orig     = get_post_meta( $product_id, 'asp_variations_prices', true );
			$variations_prices          = apply_filters( 'asp_variations_prices_filter', $variations_prices_orig, $product_id );
			$variations_urls            = get_post_meta( $product_id, 'asp_variations_urls', true );
			$variations_opts            = get_post_meta( $product_id, 'asp_variations_opts', true );
			$this->variations['names']  = $variations_names;
			$this->variations['prices'] = $variations_prices;
			$this->variations['urls']   = $variations_urls;
			$this->variations['opts']   = $variations_opts;
		}

		$data = array(
			'is_live'                         => $this->AcceptStripePayments->is_live,
			'product_id'                      => $product_id,
			'button_key'                      => $button_key,
			'item_price'                      => isset( $item_price ) ? $item_price : 0,
			'allowRememberMe'                 => $allowRememberMe,
			'quantity'                        => $quantity,
			'custom_quantity'                 => $custom_quantity,
			'description'                     => $description,
			'descrGenerated'                  => $descr_generated,
			'shipping'                        => $shipping,
			'tax'                             => $tax,
			'image'                           => $item_logo,
			'currency'                        => $currency,
			'currency_variable'               => $currency_variable,
			'locale'                          => ( empty( $checkout_lang ) ? 'auto' : $checkout_lang ),
			'name'                            => htmlspecialchars_decode( $name ),
			'url'                             => $url,
			'amount'                          => $priceInCents,
			'billingAddress'                  => ( empty( $billing_address ) ? false : true ),
			'shippingAddress'                 => ( empty( $shipping_address ) ? false : true ),
			'customer_email'                  => $customer_email,
			'uniq_id'                         => $uniq_id,
			'variable'                        => ( $price == 0 ? true : false ),
			'zeroCents'                       => $this->AcceptStripePayments->zeroCents,
			'addonHooks'                      => array(),
			'custom_field'                    => $custom_field,
			'custom_field_validation_regex'   => $cf_validation_regex,
			'custom_field_validation_err_msg' => $cf_validation_err_msg,
			'coupons_enabled'                 => $coupons_enabled,
			'tos'                             => $tos,
			'button_text'                     => esc_attr( $button_text ),
			'out_of_stock'                    => $out_of_stock,
			'stock_control_enabled'           => $stock_control_enabled,
			'stock_items'                     => $stock_items,
			'verifyZip'                       => ( ! $verifyZip ) ? 0 : 1,
			'currencyFormat'                  => $display_settings,
			'displayStr'                      => $displayStr,
			'variations'                      => $this->variations,
		);

		if ( ! empty( $data['variations'] ) ) {
			//do not hook to this filter, it will be REMOVED in upcoming versions. Use asp-button-output-data-ready instead
			$data['variations'] = apply_filters( 'asp_button_output_variations_ready', $data['variations'], $data );
		}

		$data = apply_filters( 'asp-button-output-data-ready', $data, $atts );

		$output = '';

		//Let's insert Stripe default stylesheet only when it's needed
		if ( $class == 'stripe-button-el' && ! ( ! $this->CompatMode && $this->StripeCSSInserted ) ) {
			$output                  = "<link rel = 'stylesheet' href = 'https://checkout.stripe.com/v3/checkout/button.css' type = 'text/css' media = 'all' />";
			$this->StripeCSSInserted = true;
		}

		$output .= $this->get_styles();

		$output .= "<form id = 'stripe_form_{$uniq_id}' class='asp-stripe-form' action = '' METHOD = 'POST'> ";

		$output .= $this->get_button_code_new_method( $data );

		$output .= '<input type="hidden" name="asp_action" value="process_ipn" />';
		$output .= "<input type = 'hidden' value = '" . esc_attr( $data['name'] ) . "' name = 'item_name' />";
		$output .= "<input type = 'hidden' value = '{$data[ 'quantity' ]}' name = 'item_quantity' />";
		$output .= "<input type = 'hidden' value = '{$data[ 'currency' ]}' name = 'currency_code' />";
		$output .= "<input type = 'hidden' value = '{$data[ 'url' ]}' name = 'item_url' />";
		$output .= "<input type = 'hidden' value = '{$thankyou_page_url}' name = 'thankyou_page_url' />";
		$output .= "<input type = 'hidden' value = '{$data[ 'description' ]}' name = 'charge_description' />"; //

		$trans_name        = 'stripe-payments-' . $button_key; //Create key using the item name.
		$trans['tax']      = $tax;
		$trans['shipping'] = $shipping;
		$trans['price']    = $price;
		set_transient( $trans_name, $trans, 2 * 3600 ); //Save the price for this item for 2 hours.
		$output .= '</form>';
		//before button filter
		if ( ! $out_of_stock ) {
			$output = apply_filters( 'asp_button_output_before_button', $output, $data, $class );
		}
		$output .= '<div id="asp-all-buttons-container-' . $uniq_id . '" class="asp_all_buttons_container">';
		$output .= $button;
		//after button filter
		if ( ! $out_of_stock ) {
			$output = apply_filters( 'asp-button-output-after-button', $output, $data, $class );
			$output = apply_filters( 'asp_button_output_after_button', $output, $data, $class );
		}
		$output .= '</div>';
		$output .= '<div id="asp-btn-spinner-container-' . $uniq_id . '" class="asp-btn-spinner-container" style="display: none !important">'
		. '<div class="asp-btn-spinner">'
		. '<div></div>'
		. '<div></div>'
		. '<div></div>'
		. '<div></div>'
		. '</div>'
		. '</div>';
		$output .= $this->get_scripts( $data );

		return $output;
	}

	function get_styles() {
		$output = '';
		if ( ! $this->ButtonCSSInserted || $this->CompatMode ) {
			//      $this->ButtonCSSInserted = true;
			// we need to style custom inputs
			$style   = file_get_contents( WP_ASP_PLUGIN_PATH . 'public/assets/css/public.css' );
			$output .= '<style type="text/css">' . $style . '</style>';
			//addons can output their styles if needed
			$output = apply_filters( 'asp-button-output-additional-styles', $output );
			ob_start();
			?>
		<div class="asp-processing-cont" style="display:none;"><span class="asp-processing">Processing <i>.</i><i>.</i><i>.</i></span></div>
			<?php
			$output .= ob_get_clean();
			//remove newline symbols for compatability with some page builders
			$output = str_replace( array( "\r\n", "\n", "\t" ), '', $output );
		}
		return $output;
	}

	function get_scripts( $data ) {
		$output = '';
		if ( $this->CompatMode ) {
			ob_start();
			?>
		<script type='text/javascript'>var stripehandler = <?php echo json_encode( $this->get_loc_data() ); ?>;</script>
		<script type='text/javascript'>var stripehandler<?php echo $data['uniq_id']; ?> = <?php echo json_encode( array( 'data' => $data ) ); ?>;</script>
		<script type='text/javascript' src='https://checkout.stripe.com/checkout.js'></script>
		<script type='text/javascript' src='<?php echo WP_ASP_PLUGIN_URL; ?>/public/assets/js/stripe-handler.js?ver=<?php echo WP_ASP_PLUGIN_VERSION; ?>'></script>
			<?php
			$output .= ob_get_clean();
			//remove newline symbols for compatability with some page builders
			$output = str_replace( array( "\r\n", "\n", "\t" ), '', $output );
		} else {
			//Let's enqueue Stripe js
			wp_enqueue_script( 'stripe-script' );
			//using nested array in order to ensure boolean values are not converted to strings by wp_localize_script function
			wp_localize_script( 'stripe-handler', 'stripehandler' . $data['uniq_id'], array( 'data' => $data ) );
			//enqueue our script that handles the stuff
			wp_enqueue_script( 'stripe-handler' );
		}
		//addons can enqueue their scripts if needed
		do_action( 'asp-button-output-enqueue-script' );
		return $output;
	}

	function get_button_code_new_method( $data ) {
		$output = '';

		if ( ! $data['out_of_stock'] ) {

			if ( $data['amount'] == 0 ) { //price not specified, let's add an input box for user to specify the amount
				$str_enter_amount = apply_filters( 'asp_customize_text_msg', __( 'Enter amount', 'stripe-payments' ), 'enter_amount' );
				$output          .= "<div class='asp_product_item_amount_input_container'>"
				. "<input type='text' size='10' class='asp_product_item_amount_input' id='stripeAmount_{$data[ 'uniq_id' ]}' value='' name='stripeAmount' placeholder='" . $str_enter_amount . "' required/>";
				if ( ! $data['currency_variable'] ) {
					$output .= "<span class='asp_product_item_amount_currency_label' style='margin-left: 5px; display: inline-block'> {$data[ 'currency' ]}</span>";
				}
				$output .= "<span style='display: block;' id='error_explanation_{$data[ 'uniq_id' ]}'></span>"
				. '</div>';
			}
			if ( $data['currency_variable'] ) {
				//let's add a box where user can select currency
				$output .= '<div class="asp_product_currency_input_container">';
				$output .= '<select id="stripeCurrency_' . $data['uniq_id'] . '" class="asp_product_currency_input" name="stripeCurrency">';
				$currArr = AcceptStripePayments::get_currencies();
				$tpl     = '<option data-asp-curr-sym="%s" value="%s"%s>%s</option>';
				foreach ( $currArr as $code => $curr ) {
					if ( $code !== '' ) {
						$checked = $data['currency'] === $code ? ' selected' : '';
						$output .= sprintf( $tpl, $curr[1], $code, $checked, $curr[0] );
					}
				}
				$output .= '</select>';
				$output .= '</div>';
			}
			if ( $data['custom_quantity'] === '1' ) { //we should output input for customer to input custom quantity
				if ( empty( $data['quantity'] ) ) {
					//If quantity option is enabled and the value is empty then set default quantity to 1 so the number field type can handle it better.
					$data['quantity'] = 1;
				}
				$output .= "<div class='asp_product_item_qty_input_container'>"
				. "<input type='number' min='1' size='6' class='asp_product_item_qty_input' id='stripeCustomQuantity_{$data[ 'uniq_id' ]}' value='{$data[ 'quantity' ]}' name='stripeCustomQuantity' placeholder='" . __( 'Enter quantity', 'stripe-payments' ) . "' value='{$data[ 'quantity' ]}' required/>"
				. "<span class='asp_product_item_qty_label' style='margin-left: 5px; display: inline-block'> " . __( 'X item(s)', 'stripe-payments' ) . '</span>'
				. "<span style='display: block;' id='error_explanation_quantity_{$data[ 'uniq_id' ]}'></span>"
				. '</div>';
			}

			$output = apply_filters( 'asp_button_output_before_custom_field', $output, $data );

			//Output Custom Field if needed
			$output = $this->tpl_get_cf( $output, $data );

			//Get subscription plan ID for the product (if any)
			$plan_id = get_post_meta( $data['product_id'], 'asp_sub_plan_id', true );

			//Variations
			if ( ! empty( $data['variations'] ) ) {
				//we got variations for this product
				$variations_str = '';
				foreach ( $data['variations']['groups'] as $grp_id => $group ) {
					if ( ! empty( $data['variations']['names'] ) ) {
						$variations_str .= '<div class="asp-product-variations-cont">';
						$variations_str .= '<label class="asp-product-variations-label">' . $group . '</label>';
						if ( isset( $data['variations']['opts'][ $grp_id ] ) && $data['variations']['opts'][ $grp_id ] === '1' ) {
							//radio buttons output
						} else {
							$variations_str .= sprintf( '<select class="asp-product-variations-select" data-asp-variations-group-id="%1$d" name="stripeVariations[%1$d][]">', $grp_id );
						}
						foreach ( $data['variations']['names'][ $grp_id ] as $var_id => $name ) {
							if ( isset( $data['variations']['opts'][ $grp_id ] ) && $data['variations']['opts'][ $grp_id ] === '1' ) {
								$tpl = '<label class="asp-product-variations-select-radio-label"><input class="asp-product-variations-select-radio" data-asp-variations-group-id="' . $grp_id . '" name="stripeVariations[' . $grp_id . '][]" type="radio" name="123" value="%d"' . ( $var_id === 0 ? 'checked' : '' ) . '>%s %s</label>';
							} else {
								$tpl = '<option value="%d">%s %s</option>';
							}
							$price_mod = $data['variations']['prices'][ $grp_id ][ $var_id ];
							if ( ! empty( $price_mod ) ) {
								$fmt_price = AcceptStripePayments::formatted_price( abs( $price_mod ), $data['currency'] );
								$price_mod = $price_mod < 0 ? ' - ' . $fmt_price : ' + ' . $fmt_price;
								$price_mod = '(' . $price_mod . ')';
							} else {
								$price_mod = '';
							}
							$variations_str .= sprintf( $tpl, $var_id, $name, $price_mod );
						}
						if ( isset( $data['variations']['opts'][ $grp_id ] ) && $data['variations']['opts'][ $grp_id ] === '1' ) {
							//radio buttons output
						} else {
							$variations_str .= '</select>';
						}
						$variations_str .= '</div>';
					}
				}
				$output .= $variations_str;
			}

			//add TOS box if needed
			$output = $this->tpl_get_tos( $output, $data );

			//Coupons
			if ( isset( $data['coupons_enabled'] ) && $data['coupons_enabled'] == '1' && ! $data['variable'] ) {
				if ( isset( $data['product_id'] ) ) {
					//check if this is subscription product. If it is, we will only display coupon field if subs addon version is >=1.3.3t1
					if ( ! $plan_id || ( $plan_id && class_exists( 'ASPSUB_main' ) && version_compare( ASPSUB_main::ADDON_VER, '1.3.3t1' ) >= 0 ) ) {
						$str_coupon_label = __( 'Coupon Code', 'stripe-payments' );
						$output          .= '<div class="asp_product_coupon_input_container"><label class="asp_product_coupon_field_label">' . $str_coupon_label . ' ' . '</label><input id="asp-coupon-field-' . $data['uniq_id'] . '" class="asp_product_coupon_field_input" type="text" name="stripeCoupon">'
						. '<input type="button" id="asp-redeem-coupon-btn-' . $data['uniq_id'] . '" type="button" class="asp_btn_normalize asp_coupon_apply_btn" value="' . __( 'Apply', 'stripe-payments' ) . '">'
						. '<div id="asp-coupon-info-' . $data['uniq_id'] . '" class="asp_product_coupon_info"></div>'
						. '</div>';
					}
				}
			}
		}
		if ( $data ) {
			if ( $data['product_id'] !== 0 ) {
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
		$sess    = ASP_Session::get_instance();
		$aspData = $sess->get_transient_data( 'asp_data' );
		if ( empty( $aspData ) ) {
			// no session data, let's display nothing for now
			return;
		}
		if ( empty( $content ) ) {
			//this is old shortcode. Let's display the default output for backward compatability
			if ( isset( $aspData['error_msg'] ) && ! empty( $aspData['error_msg'] ) ) {
				//some error occurred, let's display it
				return __( 'System was not able to complete the payment.', 'stripe-payments' ) . ' ' . $aspData['error_msg'];
			}
			$output  = '';
			$output .= '<p class="asp-thank-you-page-msg1">' . __( 'Thank you for your payment.', 'stripe-payments' ) . '</p>';
			$output .= '<p class="asp-thank-you-page-msg2">' . __( "Here's what you purchased: ", 'stripe-payments' ) . '</p>';
			$output .= '<div class="asp-thank-you-page-product-name">' . __( 'Product Name', 'stripe-payments' ) . ': {item_name}' . '</div>';
			$output .= '<div class="asp-thank-you-page-qty">' . __( 'Quantity', 'stripe-payments' ) . ': {item_quantity}' . '</div>';
			$output .= '<div class="asp-thank-you-page-qty">' . __( 'Item Price', 'stripe-payments' ) . ': {item_price_curr}' . '</div>';
			//check if there are any additional items available like tax and shipping cost
			$output .= AcceptStripePayments::gen_additional_items( $aspData, '<br />' );
			$output .= '<hr />';
			$output .= '<div class="asp-thank-you-page-qty">' . __( 'Total Amount', 'stripe-payments' ) . ': {paid_amount_curr}' . '</div>';
			$output .= '<br />';
			$output .= '<div class="asp-thank-you-page-txn-id">' . __( 'Transaction ID', 'stripe-payments' ) . ': {transaction_id}' . '</div>';

			$download_str = '';
			if ( ! empty( $aspData['item_url'] ) ) {
				$download_str .= "<br /><div class='asp-thank-you-page-download-link'>";
				$download_str .= _x( 'Please ', "Is a part of 'Please click here to download'", 'stripe-payments' ) . "<a href='{item_url}'>" . _x( 'click here', "Is a part of 'Please click here to download'", 'stripe-payments' ) . '</a>' . _x( ' to download.', "Is a part of 'Please click here to download'", 'stripe-payments' );
				$download_str .= '</div>';
			}

			//variations download links if needed
			if ( ! empty( $aspData['var_applied'] ) ) {
				$download_var_str  = '';
				$has_download_link = false;
				$download_var_str .= "<br /><div class='asp-thank-you-page-download-link'>";
				$download_var_str .= '<span>' . __( 'Download links', 'stripe-payments' ) . ':</span><br/>';
				$download_txt      = __( 'Click here to download', 'stripe-payments' );
				$link_tpl          = '<a href="%s">%s</a><br/>';
				foreach ( $aspData['var_applied'] as $var ) {
					if ( ! empty( $var['url'] ) ) {
						$has_download_link = true;
						$download_var_str .= sprintf( $link_tpl, $var['url'], $download_txt );
					}
				}
				$download_var_str .= '</div>';
				if ( $has_download_link ) {
					$download_str .= $download_var_str;
				}
			}
			$output .= $download_str;

			$output = apply_filters( 'asp_stripe_payments_checkout_page_result', $output, $aspData ); //Filter that allows you to modify the output data on the checkout result page

			$output = $this->apply_content_tags( $output, $aspData );

			$output .= '<style>.asp-thank-you-page-msg-wrap {background: #dff0d8; border: 1px solid #C9DEC1; margin: 10px 0px; padding: 15px;}</style>';
			$wrap    = "<div class='asp-thank-you-page-wrap'>";
			$wrap   .= "<div class='asp-thank-you-page-msg-wrap'>";
			$output  = $wrap . $output;
			$output .= '</div>'; //end of .asp-thank-you-page-msg-wrap
			$output .= '</div>'; //end of .asp-thank-you-page-wrap

			return $output;
		}
		if ( isset( $aspData['error_msg'] ) && ! empty( $aspData['error_msg'] ) ) {
			//some error occurred. Let's check if we have [accept_stripe_payment_checkout_error] shortcode on page
			$page_content = get_the_content();
			if ( ! empty( $page_content ) && ! has_shortcode( $page_content, 'accept_stripe_payment_checkout_error' ) ) {
				//no error output shortcode found. Let's output default error message
				return __( 'System was not able to complete the payment.', 'stripe-payments' ) . ' ' . $aspData['error_msg'];
			}
			return;
		}

		$content = apply_filters( 'asp_stripe_payments_checkout_page_result', $content, $aspData );

		$content = $this->apply_content_tags( do_shortcode( $content ), $aspData );
		return $content;
	}

	function shortcode_accept_stripe_payment_checkout_error( $atts, $content = '' ) {
		$aspData = array();
		$sess    = ASP_Session::get_instance();
		$aspData = $sess->get_transient_data( 'asp_data' );
		if ( empty( $aspData ) ) {
			// no session data, let's display nothing for now
			return;
		}
		if ( isset( $aspData['error_msg'] ) && ! empty( $aspData['error_msg'] ) ) {
			//some error occured. Let's display error message
			$content = $this->apply_content_tags( do_shortcode( $content ), $aspData );
			return $content;
		}
		// no error occured - we don't display anything
		return;
	}

	function shortcode_show_all_products( $params ) {

		$params = shortcode_atts(
			array(
				'items_per_page' => '30',
				'sort_by'        => 'none',
				'sort_order'     => 'desc',
				'template'       => '',
				'search_box'     => '1',
			),
			$params,
			'asp_show_all_products'
		);

		include_once WP_ASP_PLUGIN_PATH . 'public/views/all-products/default/template.php';

		$page = filter_input( INPUT_GET, 'asp_page', FILTER_SANITIZE_NUMBER_INT );

		$page = empty( $page ) ? 1 : $page;

		$q = array(
			'post_type'      => ASPMain::$products_slug,
			'post_status'    => 'publish',
			'posts_per_page' => isset( $params['items_per_page'] ) ? $params['items_per_page'] : 30,
			'paged'          => $page,
			'orderby'        => isset( $params['sort_by'] ) ? ( $params['sort_by'] ) : 'none',
			'order'          => isset( $params['sort_order'] ) ? strtoupper( $params['sort_order'] ) : 'DESC',
		);

		//handle search

		$search = filter_input( INPUT_GET, 'asp_search', FILTER_SANITIZE_STRING );

		$search = empty( $search ) ? false : $search;

		if ( $search !== false ) {
			$q['s'] = $search;
		}

		$products = new WP_Query( $q );

		if ( ! $products->have_posts() ) {
			//query returned no results. Let's see if that was a search query
			if ( $search === false ) {
				//that wasn't search query. That means there is no products configured
				wp_reset_postdata();
				return __( 'No products have been configured yet', 'stripe-payments' );
			}
		}

		$search_box = ! empty( $params['search_box'] ) ? $params['search_box'] : false;

		if ( $search_box ) {
			if ( $search !== false ) {
				$tpl['clear_search_url']   = esc_url( remove_query_arg( array( 'asp_search', 'asp_page' ) ) );
				$tpl['search_result_text'] = $products->found_posts === 0 ? __( 'Nothing found for', 'stripe-payments' ) . ' "%s".' : __( 'Search results for', 'stripe-payments' ) . ' "%s".';
				$tpl['search_result_text'] = sprintf( $tpl['search_result_text'], htmlentities( $search ) );
				$tpl['search_term']        = htmlentities( $search );
			} else {
				$tpl['search_result_text']  = '';
				$tpl['clear_search_button'] = '';
				$tpl['search_term']         = '';
			}
		} else {
			$tpl['search_box'] = '';
		}

		$tpl['products_list'] .= $tpl['products_row_start'];
		$i                     = $tpl['products_per_row']; //items per row

		while ( $products->have_posts() ) {
			$products->the_post();
			$i --;
			if ( $i < 0 ) { //new row
				$tpl['products_list'] .= $tpl['products_row_end'];
				$tpl['products_list'] .= $tpl['products_row_start'];
				$i                     = $tpl['products_per_row'] - 1;
			}

			$id = get_the_ID();

			$thumb_url = get_post_meta( $id, 'asp_product_thumbnail', true );
			if ( ! $thumb_url ) {
				$thumb_url = WP_ASP_PLUGIN_URL . '/assets/product-thumb-placeholder.png';
			}

			$view_btn = str_replace( '%[product_url]%', get_permalink(), $tpl['view_product_btn'] );

			$price = get_post_meta( $id, 'asp_product_price', true );
			$curr  = get_post_meta( $id, 'asp_product_currency', true );

			if ( empty( $plan_id ) ) {
				//let's apply filter so addons can change price, currency and shipping if needed
				$price_arr = array(
					'price'    => $price,
					'currency' => $curr,
					'shipping' => empty( $shipping ) ? false : $shipping,
				);
				$price_arr = apply_filters( 'asp_modify_price_currency_shipping', $price_arr );
				extract( $price_arr, EXTR_OVERWRITE );
				$curr = $currency;
			}

			$price = AcceptStripePayments::formatted_price( $price, $curr );
			if ( empty( $price ) ) {
				$price = '&nbsp';
			}

			$item_tags = array( 'price' => $price );

			$item_tags = apply_filters( 'asp_product_tpl_tags_arr', $item_tags, $id );

			$price = $item_tags['price'];

			$item = str_replace(
				array(
					'%[product_id]%',
					'%[product_name]%',
					'%[product_thumb]%',
					'%[view_product_btn]%',
					'%[product_price]%',
				),
				array(
					$id,
					get_the_title(),
					$thumb_url,
					$view_btn,
					$price,
				),
				$tpl['products_item']
			);

			$tpl['products_list'] .= $item;
		}

		$tpl['products_list'] .= $tpl['products_row_end'];

		//pagination

		$tpl['pagination_items'] = '';

		$pages = $products->max_num_pages;

		if ( $pages > 1 ) {
			$i = 1;

			while ( $i <= $pages ) {
				if ( $i != $page ) {
					$url = esc_url( add_query_arg( 'asp_page', $i ) );
					$str = str_replace( array( '%[url]%', '%[page_num]%' ), array( $url, $i ), $tpl['pagination_item'] );
				} else {
					$str = str_replace( '%[page_num]%', $i, $tpl['pagination_item_current'] );
				}
				$tpl['pagination_items'] .= $str;
				$i ++;
			}
		}

		if ( empty( $tpl['pagination_items'] ) ) {
			$tpl['pagination'] = '';
		}

		wp_reset_postdata();

		//Build template
		foreach ( $tpl as $key => $value ) {
			$tpl['page'] = str_replace( '_%' . $key . '%_', $value, $tpl['page'] );
		}

		return $tpl['page'];
	}

	function apply_content_tags( $content, $data ) {

		$tags = array();
		$vals = array();

		if ( isset( $data['custom_fields'] ) && ! empty( $data['custom_fields'] ) ) {
			$data['custom_field'] = '';
			foreach ( $data['custom_fields'] as $field ) {
				$data['custom_field'] .= $field['name'] . ': ' . $field['value'] . "\r\n";
			}
			$data['custom_field']       = rtrim( $data['custom_field'], "\r\n" );
			$data['custom_field']       = nl2br( $data['custom_field'] );
			$first_item                 = reset( $data['custom_fields'] );
			$data['custom_field_name']  = $first_item;
			$data['custom_field_value'] = $first_item;
		} else {
			$data['custom_field']       = null;
			$data['custom_field_name']  = null;
			$data['custom_field_value'] = null;
		}

		if ( isset( $data['paid_amount'] ) ) {
			$data['paid_amount_curr'] = AcceptStripePayments::formatted_price( $data['paid_amount'], $data['currency_code'] );
		} else {
			$data['paid_amount']      = 0;
			$data['paid_amount_curr'] = 0;
		}
		$data['purchase_amt']      = $data['paid_amount'];
		$data['purchase_amt_curr'] = $data['paid_amount_curr'];

		$curr       = isset( $data['currency_code'] ) ? $data['currency_code'] : '';
		$currencies = AcceptStripePayments::get_currencies();
		if ( isset( $currencies[ $curr ] ) ) {
			$curr_sym = $currencies[ $curr ][1];
		} else {
			$curr_sym = '';
		}
		$data['currency'] = $curr_sym;

		if ( isset( $data['item_price'] ) ) {
			$data['item_price_curr'] = AcceptStripePayments::formatted_price( $data['item_price'], $data['currency_code'] );
		} else {
			$data['item_price'] = 0;
		}

		if ( isset( $data['tax_perc'] ) && ! empty( $data['tax_perc'] ) ) {
			$data['tax_perc_fmt'] = $data['tax_perc'] . '%';
		}
		if ( isset( $data['tax'] ) && ! empty( $data['tax'] ) ) {
			$data['tax_amt'] = AcceptStripePayments::formatted_price( $data['tax'], $data['currency_code'] );
		}
		if ( isset( $data['shipping'] ) && ! empty( $data['shipping'] ) ) {
			$data['shipping_amt'] = AcceptStripePayments::formatted_price( $data['shipping'], $data['currency_code'] );
		}

		// we should unset as it's not a string and it would produce following fatal error if not unset:
		// Object of class __PHP_Incomplete_Class could not be converted to string
		unset( $data['charge'] );

		foreach ( $data as $key => $value ) {
			if ( $key == 'stripeEmail' ) {
				$key = 'payer_email';
			}
			if ( $key == 'txn_id' ) {
				$key = 'transaction_id';
			}
			if ( $key == 'tax' ) { //we don't set 'tax' key so it won't replace our new 'tax' key which we set below
				continue;
			}
			if ( $key == 'tax_perc_fmt' ) {
				$key = 'tax';
			}
			$tags[] = '{' . $key . '}';
			$vals[] = is_array( $value ) ? '' : $value;
		}

		//add email merge tags to the available merge tags
		$sess      = ASP_Session::get_instance();
		$sess_tags = $sess->get_transient_data( 'asp_checkout_data_tags', array() );
		$sess_vals = $sess->get_transient_data( 'asp_checkout_data_vals', array() );

		foreach ( $sess_tags as $key => $value ) {
			if ( empty( $tags[ $value ] ) ) {
				if ( $value === '{product_details}' ) {
					//replace new lines to <br> to display product details properly
					$sess_vals[ $key ] = nl2br( $sess_vals[ $key ] );
				}
				$tags[] = $value;
				$vals[] = $sess_vals[ $key ];
			}
		}

		$content = str_replace( $tags, $vals, $content );
		return $content;
	}

}
