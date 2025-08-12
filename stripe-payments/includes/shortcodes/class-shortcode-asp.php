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

		//register the scripts.
		add_action( 'wp_enqueue_scripts', array( $this, 'register_stripe_script' ) );

		if ( ! is_admin() ) {
			//Frontend only code.

			//NOTE: more shortcodes are defined in the class 'ASP_Shortcode_NG'.
            add_shortcode( 'asp_show_all_products', array( &$this, 'shortcode_show_all_products' ) );
            add_shortcode( 'accept_stripe_payment_checkout', array( &$this, 'shortcode_accept_stripe_payment_checkout' ) );
            add_shortcode( 'accept_stripe_payment_checkout_error', array( &$this, 'shortcode_accept_stripe_payment_checkout_error' ) );
            add_shortcode( 'asp_show_my_transactions', array( $this, 'show_user_transactions' ) );
		    add_shortcode( 'asp_available_quantity', array( $this, 'show_available_quantity' ) );

			add_filter( 'widget_text', 'do_shortcode' );
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
			'strSurcharge'                => apply_filters( 'asp_customize_text_msg', __( 'Surcharge', 'stripe-payments' ), 'surcharge_str' ),
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

		wp_register_style( 'asp-all-products-css', WP_ASP_PLUGIN_URL . '/public/views/all-products/default/style.css', array(), WP_ASP_PLUGIN_VERSION );

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

                //This custom field position option is used only for the legacy API which has been deprecated. It's here for backwards compatibility.
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

        $atts = array_map('sanitize_text_field', $atts);

		require_once WP_ASP_PLUGIN_PATH . 'includes/shortcodes/show-user-transactions.php';
		$scClass = new AcceptStripePayments_scUserTransactions();
		return $scClass->process_shortcode( $atts );
	}

	/**
	 * Displays available stock quantity of a product.
	 * If stock control is turned of, It shows 'Unlimited'.
	 *
	 * @param Array $atts Shortcode attributes.
	 * @return String HTML
	 */
	function show_available_quantity( $atts ) {
		if ( ! isset( $atts['id'] ) || ! is_numeric( $atts['id'] ) ) {
			$error_msg  = '<div class="asp_error_msg" style="color: red;">';
			$error_msg .= 'Error: product ID is invalid.';
			$error_msg .= '</div>';
			return $error_msg;
		}
		$id   = $atts['id'];
		$post = get_post( $id );
		if ( ! $post || get_post_type( $id ) != ASPMain::$products_slug ) {
			$error_msg  = '<div class="asp_error_msg" style="color: red;">';
			$error_msg .= "Error: invalid product ID " . $id;
			$error_msg .= '</div>';
			return $error_msg;
		}

		$available_quantity = esc_attr(get_post_meta( $id, 'asp_product_stock_items', true ));
		$stock_enable = esc_attr(get_post_meta( $id, 'asp_product_enable_stock', true ));

		$output  = '<span class="asp_available_quantity">';
		$output  .= $stock_enable ? $available_quantity : 'Unlimited';
		$output  .= '</span>';
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
		<script type='text/javascript'>var stripehandler<?php echo esc_attr( $data['uniq_id'] ); ?> = <?php echo json_encode( array( 'data' => $data ) ); ?>;</script>
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

        // Check if $content is empty or not. If $content is empty, it means that it is using the standard thank you page shortcode (no customization using the shortcode opening and ending tags).
		if ( empty( $content ) ) {
			// Let's display the default/standard thank you page output.
			// This scenario is not using the shortcode opening and ending tags for customization.

			if ( isset( $aspData['error_msg'] ) && ! empty( $aspData['error_msg'] ) ) {
				//some error occurred, let's display it
				return __( 'System was not able to complete the payment.', 'stripe-payments' ) . ' ' . $aspData['error_msg'];
			}

			//Create the default thank you page output template. Later we will replace the placeholders with actual data.
			ob_start();
			?>
            <div class="asp-order-details-wrap">
                <h4><?php _e( 'Thank you for your payment.', 'stripe-payments' ); ?></h4>
                <div class="asp-order-data-box">
                    <div class="asp-order-data-box-col asp-order-data-box-col-date">
                        <div class="asp-order-data-box-col-label"><?php _e( "Date", "stripe-payments" ); ?></div>
                        <div class="asp-order-data-box-col-value">{purchase_date_only}</div>
                    </div>
                    <div class="asp-order-data-box-col asp-order-data-box-col-email">
                        <div class="asp-order-data-box-col-label"><?php _e( "Email", "stripe-payments" ); ?></div>
                        <div class="asp-order-data-box-col-value">{payer_email}</div>
                    </div>
                    <div class="asp-order-data-box-col asp-order-data-box-col-txn-id">
                        <div class="asp-order-data-box-col-label"><?php _e( "Transaction ID", "stripe-payments" ); ?></div>
                        <div class="asp-order-data-box-col-value">{transaction_id}</div>
                    </div>
                </div>

                <h4 class="asp-order-details-heading"><?php _e( "Order Details", "stripe-payments" ); ?></h4>

                <table class="asp-order-details-table">
                    <thead>
                        <tr class="asp-order-details-table-header">
                            <th style="text-align: start"><?php _e( "Item", "stripe-payments" ); ?></th>
                            <th style="text-align: end"><?php _e( "Total", "stripe-payments" ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="asp-order-details-product-row">
                            <th style="text-align: start" class="asp-order-product-name-label">{item_name}</th>
                            <td style="text-align: end">{item_price_curr}</td>
                        </tr>
                        <tr class="asp-order-details-quantity-row">
                            <th style="text-align: start">
                                <?php _e('Quantity', 'stripe-payments') ?>
                            </th>
                            <td style="text-align: end">x {item_quantity}</td>
                        </tr>

                        <?php if ( ! empty( $aspData['additional_items'] ) ) {
                            foreach ( $aspData['additional_items'] as $item => $price ) {
                                if ( $price < 0 ) {
                                    $amnt_str = '-' . AcceptStripePayments::formatted_price( abs( $price ), $aspData['currency_code'] );
                                } else {
                                    $amnt_str = AcceptStripePayments::formatted_price( $price, $aspData['currency_code'] );
                                }
                                echo '<tr class="asp-order-details-additional-items-row"><th style="text-align: start">' . $item . '</th><td style="text-align: end">' . $amnt_str. '</td></tr>';
                            }
                        }
                        ?>

                        <?php if ( isset( $aspData['paid_amount'] )) { ?>
                        <tr class="asp-order-details-total-amount-row">
                            <th style="text-align: start"><?php _e( "Total Amount: ", "stripe-payments" ); ?></th>
                            <td style="text-align: end">{paid_amount_curr}</td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>

				<?php if ( ! empty( $aspData['item_url'] ) ) { ?>
                    <h4><?php _e( "Downloads", "stripe-payments" ); ?></h4>
                    <table class="asp-order-downloads-table">
                        <thead>
                        <tr class="asp-order-downloads-table-header">
                            <th style="text-align: start"><?php _e( "Item", "stripe-payments" ); ?></th>
                            <th style="text-align: start"><?php _e( "Download Link", "stripe-payments" ); ?></th>
                        </tr>
                        </thead>
                        <tbody>
						<?php if ( isset($aspData['item_url']) && ! empty( $aspData['item_url'] ) ) { ?>
                            <tr class="asp-order-downloads-downloadable-item-row">
                                <td>{item_name}</td>
                                <td><a class="asp-order-downloadable-item-link" href="{item_url}"
                                       target="_blank"><?php _e( "Download", "stripe-payments" ) ?></a>
                                </td>
                            </tr>
						<?php } ?>

						<?php
						// Variation download link(s) if needed
						if ( isset($aspData['var_applied']) && ! empty( $aspData['var_applied'] ) ) {
							foreach ( $aspData['var_applied'] as $var ) {
								if ( ! empty( $var['url'] ) ) {
									$dl_item_str = implode(':', array_filter(array( $var['group_name'],$var['name'] )))
									?>
                                    <tr class="asp-order-downloads-variations-download-row">
                                        <td><?php echo esc_attr( $dl_item_str ) ?></td>
                                        <td><a class="asp-order-downloadable-item-link" href="<?php echo esc_url( $var['url'] ) ?>"
                                               target="_blank"><?php _e( "Download", "stripe-payments" ) ?></a>
                                        </td>
                                    </tr>
									<?php
								}
							}
						}
						?>
                        </tbody>
                    </table>
				<?php } ?>

				<?php if ( isset($aspData['shipping_address']) && ! empty( $aspData['shipping_address'] ) ) { ?>
                    <div class="asp-order-additional-data-box asp-order-additional-data-box-shipping-address">
                        <h4><?php _e( "Shipping Address", "stripe-payments" ); ?></h4>
                        <div class="asp-order-shipping-address">{shipping_address}</div>
                    </div>
				<?php } ?>

	            <?php if (
                        isset($aspData['charge']['billing_details']['address'])
                        && is_array($aspData['charge']['billing_details']['address'])
                        && !empty(array_filter($aspData['charge']['billing_details']['address'])) // Check if all address fields are empty.
                ) { ?>
                    <div class="asp-order-additional-data-box asp-order-additional-data-box-billing-address">
                        <h4><?php _e( "Billing Address", "stripe-payments" ); ?></h4>
                        <div class="asp-order-billing-address">{billing_address}</div>
                    </div>
				<?php } ?>
            </div>
			<?php
			$output = ob_get_clean();

			$output = apply_filters( 'asp_stripe_payments_checkout_page_result', $output, $aspData ); //Filter that allows you to modify the output data on the checkout result page

			return $this->apply_content_tags( $output, $aspData );
		}

		// Fallback to the customized thank you page output (where the shortcode opening and ending tags are used for customization).
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
			//some error occurred. Let's display error message
			$content = $this->apply_content_tags( do_shortcode( $content ), $aspData );
			return $content;
		}
		// no error occurred - we don't display anything
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

		//Handle the 'sort by' and 'sort order' options.
		//Check if the user has changed/selected a 'sort by' option from the UI.
		$sort_by = isset( $_GET['asp-sortby'] ) ? sanitize_text_field( stripslashes ( $_GET['asp-sortby'] ) ) : '';
		//Value of 'sort_by' from the shortcode parameters.
		$sc_sort_by = isset( $params['sort_by'] ) ? $params['sort_by'] : 'id';
		//Value of 'sort_order' from the shortcode parameters.
		$sc_sort_direction = isset( $params['sort_order'] ) ? strtoupper( $params['sort_order'] ) : 'desc';

		if( !empty($sort_by) ) {
			//User has selected a 'sort by' option from the UI.
			$order_by = explode("-",$sort_by)[0];
			$sort_direction = isset(explode("-",$sort_by)[1]) ? explode("-",$sort_by)[1] : "asc";
		}
		else{
			//Set default sorting option on page load.
			//If shortcode has sorting parameters specified then it will use those otherwise, it will use the default value of 'id'.
			$order_by = $sc_sort_by;
			$sort_direction = $sc_sort_direction;
		}

		//Include the template file for displaying all products.
        $tpl = array();
		include_once WP_ASP_PLUGIN_PATH . 'public/views/all-products/default/template.php';

		//Pagination related variables.
		$page = filter_input( INPUT_GET, 'asp_page', FILTER_SANITIZE_NUMBER_INT );
		$page = empty( $page ) ? 1 : $page;

		//Query arguments for fetching products.
		$q = array(
			'post_type'      => ASPMain::$products_slug,
			'post_status'    => 'publish',
			'posts_per_page' => isset( $params['items_per_page'] ) ? $params['items_per_page'] : 30,
			'paged'          => $page,
			'orderby'        => $order_by,
			'order'          => $sort_direction,
		);

		//Handle search
		$search = isset( $_GET['asp_search'] ) ? sanitize_text_field( stripslashes ( $_GET['asp_search'] ) ) : '';
		$search = empty( $search ) ? false : $search;
		if ( $search !== false ) {
			$q['s'] = $search;
		}

		if( $q["orderby"] == "price" ) {
			add_filter( 'posts_orderby', array(__CLASS__,'asp_orderby_price_callback' ));
		}

		$products = new WP_Query( $q );

		if( $q["orderby"] == "price" ) {
			remove_filter( 'posts_orderby',array(__CLASS__,'asp_orderby_price_callback')  );
		}

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

        if (!isset($tpl['products_list'])){
	        $tpl['products_list'] = '';
        }
		$tpl['products_list'] .= isset($tpl['products_row_start']) ? $tpl['products_row_start'] : '';
		$i                     = isset($tpl['products_per_row']) ? $tpl['products_per_row'] : 3; //items per row

		while ( $products->have_posts() ) {
			$products->the_post();
			$i --;
			if ( $i < 0 ) { //new row
				$tpl['products_list'] .= isset($tpl['products_row_end']) ? $tpl['products_row_end'] : '';
				$tpl['products_list'] .= isset($tpl['products_row_start']) ? $tpl['products_row_start'] : '';
				$i                     = (isset($tpl['products_per_row']) ? $tpl['products_per_row'] : 3) - 1;
			}

			$id = get_the_ID();

			$thumb_url = get_post_meta( $id, 'asp_product_thumbnail', true );
			if ( ! $thumb_url ) {
				$thumb_url = WP_ASP_PLUGIN_URL . '/assets/product-thumb-placeholder.png';
			}

			$view_btn = str_replace( '%[product_url]%', get_permalink(), (isset($tpl['view_product_btn']) ? $tpl['view_product_btn'] : '') );

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

			$price_orig = $price;

			$price = AcceptStripePayments::formatted_price( $price, $curr );
			if ( empty( $price ) ) {
				$price = '&nbsp';
			}

			$constr_price_var = get_post_meta( $id, 'asp_product_hide_amount_input', true );

			if ( empty( $price_orig ) && ! empty( $constr_price_var ) ) {
				$price = __( 'Variable', 'stripe-payments' );
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
				( isset($tpl['products_item']) ? $tpl['products_item'] : '')
			);

			$tpl['products_list'] .= $item;
		}

		$tpl['products_list'] .= isset($tpl['products_row_end']) ? $tpl['products_row_end'] : '';

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
			$tpl['page'] = str_replace( '_%' . $key . '%_', $value, ( isset( $tpl['page'] ) ? $tpl['page'] : '') );
		}

		$output = '<div class="wpec_shop_products">'.$tpl['page'].'</div>';
		return $output;
	}


	public static  function asp_orderby_price_callback( $orderby ) {
		global $wpdb;
		$order = "";
		if(stripos( $orderby, "desc" ) !== false) {
			$order="desc";
		}
		else{
			$order="asc";
		}

		$orderby = "
		CASE 
			WHEN  (select wp.meta_value from ".$wpdb->prefix."postmeta wp where wp.meta_key='asp_product_type' and wp.post_id=wp_posts.ID limit 1) ='one_time' THEN cast((select wp.meta_value from ".$wpdb->prefix."postmeta wp where wp.meta_key='asp_product_price' and wp.post_id=wp_posts.ID limit 1) as decimal) 
			WHEN  ((select wp.meta_value from ".$wpdb->prefix."postmeta wp where wp.meta_key='asp_product_price' and wp.post_id=wp_posts.ID limit 1) is not null and (select wp.meta_value from ".$wpdb->prefix."postmeta wp where wp.meta_key='asp_product_price' and wp.post_id=wp_posts.ID limit 1)>0) THEN cast((select wp.meta_value from ".$wpdb->prefix."postmeta wp where wp.meta_key='asp_product_price' and wp.post_id=wp_posts.ID limit 1) as decimal) 
			WHEN  (select wp.meta_value from ".$wpdb->prefix."postmeta wp where wp.meta_key='asp_product_type' and wp.post_id=wp_posts.ID limit 1) ='donation' THEN cast(0 as decimal) 									
			WHEN  (select wp.meta_value from ".$wpdb->prefix."postmeta wp where wp.meta_key='asp_product_min_amount' and wp.post_id=wp_posts.ID limit 1) is not null THEN cast(0 as decimal) 									
			WHEN  (select wp.meta_value from ".$wpdb->prefix."postmeta wp where wp.meta_key='asp_sub_plan_id' and wp.post_id=wp_posts.ID limit 1) is not null THEN cast((select (select plan.meta_value from ".$wpdb->prefix."postmeta plan where plan.post_id=wp.meta_value and plan.meta_key='asp_sub_plan_price' limit 1) from ".$wpdb->prefix."postmeta wp where wp.meta_key='asp_sub_plan_id' and wp.post_id=wp_posts.ID limit 1) as decimal) 						
			else 0 
		END ".$order."
			";

		return $orderby;
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

        if (isset($data['charge']['created'])){
		    $data['purchase_date_only'] = get_date_from_gmt( date( 'Y-m-d H:i:s', $data['charge']['created'] ), get_option( 'date_format' ));
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
