<?php
class ASP_PP_Handler {

	protected $tpl_cf;
	protected $uniq_id;
	protected $asp_main;
	public function __construct() {
		$action = filter_input( INPUT_GET, 'asp_action', FILTER_SANITIZE_STRING );
		if ( 'show_pp' === $action ) {
			$process_ipn = filter_input( INPUT_POST, 'asp_process_ipn', FILTER_SANITIZE_NUMBER_INT );
			if ( $process_ipn ) {
				return;
			}
			$this->asp_main = AcceptStripePayments::get_instance();
			add_action( 'plugins_loaded', array( $this, 'showpp' ) );
		}
		if ( wp_doing_ajax() ) {
			$this->asp_main = AcceptStripePayments::get_instance();
			add_action( 'wp_ajax_asp_pp_req_token', array( $this, 'handle_request_token' ) );
			add_action( 'wp_ajax_nopriv_asp_pp_req_token', array( $this, 'handle_request_token' ) );
		}
	}

	public function showpp() {
		$product_id = filter_input( INPUT_GET, 'product_id', FILTER_SANITIZE_NUMBER_INT );
		$this->item = new ASP_Product_Item( $product_id );

		if ( $this->item->get_last_error() ) {
			echo esc_html( $this->item->get_last_error() );
			exit;
		}

		$a = array();

		$a['prod_id'] = $product_id;

		$a['is_live'] = $this->asp_main->is_live;

		$this->uniq_id = uniqid();

		$a['page_title'] = $this->item->get_name();
		$a['plugin_url'] = WP_ASP_PLUGIN_URL;
		$a['item_name']  = $this->item->get_name();
		$a['stripe_key'] = $this->asp_main->APIPubKeyTest;

		//Custom Field if needed

		$custom_field = get_post_meta( $product_id, 'asp_product_custom_field', true );
		$cf_enabled   = $this->asp_main->get_setting( 'custom_field_enabled' );
		if ( ( '' === $custom_field ) || '2' === $custom_field ) {
			$custom_field = $cf_enabled;
		} else {
			$custom_field = intval( $custom_field );
		}
		if ( ! $cf_enabled ) {
			$custom_field = $cf_enabled;
		}

		$this->custom_field = $custom_field;
		$this->prod_id      = $product_id;

		if ( $custom_field ) {
			$a['custom_fields'] = $this->tpl_get_cf();
		}

		$currency = $this->item->get_currency();

		$a['scripts'] = array();
		$a['styles']  = array();
		$a['vars']    = array();
		$a['styles']  = apply_filters( 'asp_ng_pp_output_add_styles', $a['styles'] );
		$a['scripts'] = apply_filters( 'asp_ng_pp_output_add_scripts', $a['scripts'] );
		$a['vars']    = apply_filters( 'asp_ng_pp_output_add_vars', $a['vars'] );

		$a['styles'][] = array(
			'footer' => false,
			'src'    => WP_ASP_PLUGIN_URL . '/public/views/templates/default/pure.css',
		);

		$a['styles'][] = array(
			'footer' => false,
			'src'    => WP_ASP_PLUGIN_URL . '/public/views/templates/default/pp-style.css',
		);

		$a['scripts'][] = array(
			'footer' => true,
			'src'    => WP_ASP_PLUGIN_URL . '/public/assets/js/pp-handler.js',
		);

		//vars

		//Currency Display settings
		$display_settings      = array();
		$display_settings['c'] = $this->asp_main->get_setting( 'price_decimals_num', 2 );
		$display_settings['d'] = $this->asp_main->get_setting( 'price_decimal_sep' );
		$display_settings['t'] = $this->asp_main->get_setting( 'price_thousand_sep' );
		$currencies            = AcceptStripePayments::get_currencies();
		if ( isset( $currencies[ $currency ] ) ) {
			$curr_sym = $currencies[ $currency ][1];
		} else {
			//no currency code found, let's just use currency code instead of symbol
			$curr_sym = $currencies;
		}
		$curr_pos                = $this->asp_main->get_setting( 'price_currency_pos' );
		$display_settings['s']   = $curr_sym;
		$display_settings['pos'] = $curr_pos;

		$a['amount_variable'] = false;
		if ( $this->item->get_price() === 0 ) {
			$a['amount_variable'] = true;
		}

		$a['currency_variable'] = false;
		if ( $this->item->is_currency_variable() ) {
			$a['currency_variable'] = true;
		}

		$a['currency'] = $this->item->get_currency();

		$data               = array();
		$data['product_id'] = $product_id;
		$quantity           = get_post_meta( $product_id, 'asp_product_quantity', true );
		if ( $quantity ) {
			$quantity = 1;
		}
		$data['quantity']          = $quantity;
		$data['custom_quantity']   = get_post_meta( $product_id, 'asp_product_custom_quantity', true );
		$data['amount_variable']   = $a['amount_variable'];
		$data['currency_variable'] = $a['currency_variable'];
		$data['currency']          = $a['currency'];

		$data['client_secret'] = '';
		$data['pi_id']         = '';
		$data['amount']        = $this->item->get_total( true );
		$data['item_price']    = $this->item->get_price( true );
		$data['tax']           = $this->item->get_tax();
		$data['shipping']      = $this->item->get_shipping( true );
		$data['descr']         = $this->item->get_description();

		$data['is_live'] = $this->asp_main->is_live;

		$a['data'] = $data;

		$a['vars']['vars'] = array(
			'data'           => $data,
			'stripe_key'     => $a['stripe_key'],
			'minAmounts'     => $this->asp_main->minAmounts,
			'zeroCents'      => $this->asp_main->zeroCents,
			'ajaxURL'        => admin_url( 'admin-ajax.php' ),
			'currencyFormat' => $display_settings,
			'payBtnText'     => 'Pay %s',
			'amountOpts'     => array(
				'applySepOpts' => $this->asp_main->get_setting( 'price_apply_for_input' ),
				'decimalSep'   => $this->asp_main->get_setting( 'price_decimal_sep' ),
				'thousandSep'  => $this->asp_main->get_setting( 'price_thousand_sep' ),
			),
			'str'            => array(
				'strEnterValidAmount'         => apply_filters( 'asp_customize_text_msg', __( 'Please enter a valid amount', 'stripe-payments' ), 'enter_valid_amount' ),
				'strMinAmount'                => apply_filters( 'asp_customize_text_msg', __( 'Minimum amount is', 'stripe-payments' ), 'min_amount_is' ),
				'strEnterQuantity'            => apply_filters( 'asp_customize_text_msg', __( 'Please enter quantity.', 'stripe-payments' ), 'enter_quantity' ),
				'strQuantityIsZero'           => apply_filters( 'asp_customize_text_msg', __( 'Quantity can\'t be zero.', 'stripe-payments' ), 'quantity_is_zero' ),
				'strQuantityIsFloat'          => apply_filters( 'asp_customize_text_msg', __( 'Quantity should be integer value.', 'stripe-payments' ), 'quantity_is_float' ),
				// translators: %d is number of items in stock
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
			),
		);

		try {
			ASPMain::load_stripe_lib();
			$key = $this->asp_main->is_live ? $this->asp_main->APISecKey : $this->asp_main->APISecKeyTest;
			\Stripe\Stripe::setApiKey( $key );
		} catch ( Exception $e ) {
			$a['fatal_error'] = __( 'Stripe API error occurred:', 'stripe-payments' ) . ' ' . $e->getMessage();
		}

		// if ( ! $a['amount_variable'] && ! $a['currency_variable'] ) {
		// 	try {
		// 		$intent = \Stripe\PaymentIntent::create(
		// 			array(
		// 				'amount'   => $this->item->get_total( true ),
		// 				'currency' => $this->item->get_currency(),
		// 			)
		// 		);
		// 	} catch ( Exception $e ) {
		// 		$a['fatal_error'] = __( 'Stripe API error occurred:', 'stripe-payments' ) . ' ' . $e->getMessage();
		// 	}

		// 	if ( ! isset( $a['fatal_error'] ) ) {
		// 		$a['client_secret']                         = $intent->client_secret;
		// 		$a['pi_id']                                 = $intent->id;
		// 		$a['vars']['vars']['data']['client_secret'] = $intent->client_secret;
		// 		$a['vars']['vars']['data']['pi_id']         = $intent->id;
		// 	}
		// }
		if ( isset( $a['fatal_error'] ) ) {
			$a['vars']['vars']['fatal_error'] = $a['fatal_error'];
		}
		$pay_str           = 'Pay %s';
		$a['pay_btn_text'] = sprintf( $pay_str, AcceptStripePayments::formatted_price( $this->item->get_total(), $this->item->get_currency() ) );
		ob_start();
		require_once WP_ASP_PLUGIN_PATH . 'public/views/templates/default/payment-popup.php';
		$tpl = ob_get_clean();
		echo $tpl; //phpcs:ignore
		exit;
	}

	public function handle_request_token() {
		$out            = array();
		$out['success'] = false;
		$amount         = filter_input( INPUT_POST, 'amount', FILTER_SANITIZE_NUMBER_FLOAT );
		$curr           = filter_input( INPUT_POST, 'curr', FILTER_SANITIZE_STRING );
		$pi_id          = filter_input( INPUT_POST, 'pi', FILTER_SANITIZE_STRING );

		try {
			ASPMain::load_stripe_lib();
			$key = $this->asp_main->is_live ? $this->asp_main->APISecKey : $this->asp_main->APISecKeyTest;
			\Stripe\Stripe::setApiKey( $key );
		} catch ( Exception $e ) {
			$out['err'] = __( 'Stripe API error occurred:', 'stripe-payments' ) . ' ' . $e->getMessage();
			wp_send_json( $out );
		}

		$metadata = array();

		try {
			$pi_params = array(
				'amount'   => $amount,
				'currency' => $curr,
			);
			if ( isset( $metadata ) && ! empty( $metadata ) ) {
				$pi_params['metadata'] = $metadata;
			}
			if ( $pi_id ) {
				$intent = \Stripe\PaymentIntent::update( $pi_id, $pi_params );
			} else {
				$intent = \Stripe\PaymentIntent::create( $pi_params );
			}
		} catch ( Exception $e ) {
			$out['err'] = $e->getMessage();
			wp_send_json( $out );
		}

		$out['success']      = true;
		$out['clientSecret'] = $intent->client_secret;
		$out['pi_id']        = $intent->id;
		wp_send_json( $out );
		exit;
	}

	public function tpl_get_cf( $output = '' ) {
		if ( empty( $this->tpl_cf ) ) {
			$replace_cf = apply_filters(
				'asp_ng_button_output_replace_custom_field',
				'',
				array(
					'product_id'   => $this->prod_id,
					'custom_field' => $this->custom_field,
				)
			);
			if ( ! empty( $replace_cf ) ) {
				//we got custom field replaced
				$this->tpl_cf = $replace_cf;
				$output      .= $this->tpl_cf;
				$this->tpl_cf = '';
				return $output;
			}
			$field_type  = $this->asp_main->get_setting( 'custom_field_type' );
			$field_name  = $this->asp_main->get_setting( 'custom_field_name' );
			$field_name  = empty( $field_name ) ? __( 'Custom Field', 'stripe-payments' ) : $field_name;
			$field_descr = $this->asp_main->get_setting( 'custom_field_descr' );
			$descr_loc   = $this->asp_main->get_setting( 'custom_field_descr_location' );
			$mandatory   = $this->asp_main->get_setting( 'custom_field_mandatory' );
			$tpl_cf      = '';
			$tpl_cf     .= "<div class='asp_product_custom_field_input_container'>";
			$tpl_cf     .= '<fieldset>';
			$tpl_cf     .= '<input type="hidden" name="stripeCustomFieldName" value="' . esc_attr( $field_name ) . '">';
			switch ( $field_type ) {
				case 'text':
					if ( 'below' !== $descr_loc ) {
						$tpl_cf .= sprintf(
							'<label class="asp_product_custom_field_label">%s</label><input id="asp-custom-field" name="stripeCustomField" class="pure-input-1 asp_product_custom_field_input" type="text"%s placeholder="%s"%s>',
							esc_html( $field_name ),
							( $mandatory ? ' data-asp-custom-mandatory' : '' ),
							esc_attr( $field_descr ),
							( $mandatory ? ' required' : '' )
						);
					} else {
						$tpl_cf .= sprintf(
							'<label class="asp_product_custom_field_label">%s</label><input id="asp-custom-field" name="stripeCustomField" class="pure-input-1 asp_product_custom_field_input" type="text"%s%s>',
							esc_html( $field_name ),
							( $mandatory ? ' data-asp-custom-mandatory' : '' ),
							( $mandatory ? ' required' : '' )
						);
						$tpl_cf .= sprintf( '<div class="asp_product_custom_field_descr">%s</div>', $field_descr );
					}
					break;
				case 'checkbox':
					$tpl_cf .= '<label class="pure-checkbox asp_product_custom_field_label"><input id="asp-custom-field" class="pure-input-1 asp_product_custom_field_input" type="checkbox"' . ( $mandatory ? ' data-asp-custom-mandatory' : '' ) . ' name="stripeCustomField"' . ( $mandatory ? ' required' : '' ) . '>' . $field_descr . '</label>';
					break;
			}
			$tpl_cf      .= "<span id='custom_field_error_explanation' class='pure-form-message asp_product_custom_field_error'></span>" .
				'</fieldset>' .
				'</div>';
			$this->tpl_cf = $tpl_cf;
		}
		$cf_pos = $this->asp_main->get_setting( 'custom_field_position' );
		if ( 'below' !== $cf_pos ) {
			$output      .= $this->tpl_cf;
			$this->tpl_cf = '';
		} else {
			add_filter( 'asp_button_output_after_button', array( $this, 'after_button_add_сf_filter' ), 990, 3 );
		}
		return $output;
	}

}

new ASP_PP_Handler();
