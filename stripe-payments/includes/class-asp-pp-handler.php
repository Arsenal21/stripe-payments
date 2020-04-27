<?php
class ASP_PP_Handler {

	protected $tpl_cf;
	protected $uniq_id;
	protected $asp_main;

	public function __construct() {
		$action = filter_input( INPUT_GET, 'asp_action', FILTER_SANITIZE_STRING );
		if ( 'show_pp' === $action ) {
			//          ASP_Utils::set_custom_lang_if_needed();
			$process_ipn = filter_input( INPUT_POST, 'asp_process_ipn', FILTER_SANITIZE_NUMBER_INT );
			if ( $process_ipn ) {
				return;
			}
			$this->asp_main = AcceptStripePayments::get_instance();
			add_action( 'plugins_loaded', array( $this, 'showpp' ), 2147483647 );
		}
		if ( wp_doing_ajax() ) {
			//          ASP_Utils::set_custom_lang_if_needed();
			$this->asp_main = AcceptStripePayments::get_instance();
			add_action( 'plugins_loaded', array( $this, 'add_ajax_handlers' ), 2147483647 );
			add_action( 'wp_ajax_asp_pp_check_coupon', array( $this, 'handle_check_coupon' ) );
			add_action( 'wp_ajax_nopriv_asp_pp_check_coupon', array( $this, 'handle_check_coupon' ) );
		}
	}

	public function add_ajax_handlers() {
		add_action( 'wp_ajax_asp_pp_req_token', array( $this, 'handle_request_token' ) );
		add_action( 'wp_ajax_nopriv_asp_pp_req_token', array( $this, 'handle_request_token' ) );

		add_action( 'wp_ajax_asp_pp_save_form_data', array( $this, 'save_form_data' ) );
		add_action( 'wp_ajax_nopriv_asp_pp_save_form_data', array( $this, 'save_form_data' ) );
	}

	public function showpp() {
		$product_id = filter_input( INPUT_GET, 'product_id', FILTER_SANITIZE_NUMBER_INT );

		$this->item = new ASP_Product_Item( $product_id );

		$this->item = apply_filters( 'asp_ng_pp_product_item_override', $this->item );

		if ( $this->item->get_last_error() ) {
			echo esc_html( $this->item->get_last_error() );
			exit;
		}

		$plan_id = get_post_meta( $product_id, 'asp_sub_plan_id', true );

		if ( ! empty( $plan_id ) && ! class_exists( 'ASPSUB_main' ) ) {
			//Subs addon not installed or disabled. Show corresponding error message
			echo ( 'This product requires Stripe Payments Subscription addon.' );
			exit;
		}

		if ( ! empty( $plan_id ) && class_exists( 'ASPSUB_main' ) && version_compare( ASPSUB_main::ADDON_VER, '2.0.0t1' ) < 0 ) {
			echo ( 'Stripe Subscriptions addon version 2.0.0 or newer is required.' );
			exit;
		}

		$a = array();

		$a['prod_id'] = $product_id;

		do_action( 'asp_ng_product_mode_keys', $product_id );

		$a['is_live'] = $this->asp_main->is_live;

		$this->uniq_id = uniqid();

		$a['page_title'] = $this->item->get_name();
		$a['plugin_url'] = WP_ASP_PLUGIN_URL;
		$a['item_name']  = $this->item->get_name();
		$a['stripe_key'] = $this->asp_main->is_live ? $this->asp_main->APIPubKey : $this->asp_main->APIPubKeyTest;

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

		$cf_validation_regex   = '';
		$cf_validation_err_msg = '';

		if ( $custom_field ) {
			$a['custom_fields'] = $this->tpl_get_cf();
			//check if we have custom field validation enabled
			$custom_validation = $this->asp_main->get_setting( 'custom_field_validation' );
			if ( ! empty( $custom_validation ) ) {
				if ( 'num' === $custom_validation ) {
					$cf_validation_regex   = '^[0-9]+$';
					$cf_validation_err_msg = __( 'Only numbers are allowed: 0-9', 'stripe-payments' );
				} elseif ( 'custom' === $custom_validation ) {
					$cf_validation_regex   = $this->asp_main->get_setting( 'custom_field_custom_validation_regex' );
					$cf_validation_err_msg = $this->asp_main->get_setting( 'custom_field_custom_validation_err_msg' );
				}
			}
		}

		$currency = $this->item->get_currency();

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
			$curr_sym = $currency;
		}
		$curr_pos                = $this->asp_main->get_setting( 'price_currency_pos' );
		$display_settings['s']   = $curr_sym;
		$display_settings['pos'] = $curr_pos;

		$a['amount_variable'] = false;
		if ( $this->item->get_price() === 0 ) {
			$a['amount_variable'] = true;
			//let's check to see if he have item_price passed via URL parameter
			$passed_item_price = filter_input( INPUT_GET, 'price', FILTER_SANITIZE_STRING );
			$passed_item_price = abs( floatval( $passed_item_price ) );
			if ( ! empty( $passed_item_price ) ) {
				$this->item->set_price( $passed_item_price );
			}
		}

		$a['currency_variable'] = false;
		if ( $this->item->is_currency_variable() ) {
			$a['currency_variable'] = true;
		}

		$a['currency'] = $this->item->get_currency();

		$billing_address  = get_post_meta( $product_id, 'asp_product_collect_billing_addr', true );
		$shipping_address = get_post_meta( $product_id, 'asp_product_collect_shipping_addr', true );

		$billing_address  = empty( $billing_address ) ? false : true;
		$shipping_address = empty( $shipping_address ) ? false : true;

		if ( ! $billing_address ) {
			$shipping_address = false;
		}

		$tos = $this->asp_main->get_setting( 'tos_enabled' );

		if ( $tos ) {
			$a['tos']      = true;
			$a['tos_text'] = $this->asp_main->get_setting( 'tos_text' );
		}

		$coupons_enabled = get_post_meta( $product_id, 'asp_product_coupons_setting', true );
		if ( ( '' === $coupons_enabled ) || '2' === $coupons_enabled ) {
			$coupons_enabled = $this->asp_main->get_setting( 'coupons_enabled' );
		}
		if ( $a['amount_variable'] ) {
			$coupons_enabled = false;
		}

		$coupons_enabled = empty( $coupons_enabled ) ? false : true;

		$item_logo = '';

		if ( ! get_post_meta( $product_id, 'asp_product_no_popup_thumbnail', true ) ) {
			$item_logo = ASP_Utils::get_small_product_thumb( $product_id );
			if ( $a['is_live'] ) {
				$item_logo = ASP_Utils::url_to_https( $item_logo );
			}
		}

		//stock control
		$stock_control_enabled = false;
		$stock_items           = 0;
		if ( $this->item->stock_control_enabled() ) {
			$stock_items = $this->item->get_stock_items();
			if ( empty( $stock_items ) ) {
				$a['fatal_error'] = __( 'Out of stock', 'stripe-payments' );
			} else {
				$stock_control_enabled = true;
				$stock_items           = $stock_items;
			}
		}

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

		$thankyou_page      = get_post_meta( $product_id, 'asp_product_thankyou_page', true );
		$a['thankyou_page'] = $thankyou_page;

		$cust_email_hardcoded = get_post_meta( $product_id, 'asp_product_customer_email_hardcoded', true );
		$cust_name_hardcoded  = get_post_meta( $product_id, 'asp_product_customer_name_hardcoded', true );

		$user_id              = get_current_user_id();
		$prefill_user_details = $this->asp_main->get_setting( 'prefill_wp_user_details' );

		if ( $user_id && $prefill_user_details ) {
			$user_info = get_userdata( $user_id );
			if ( false !== $user_info ) {
				if ( empty( $cust_email_hardcoded ) ) {
					$cust_email_hardcoded = $user_info->user_email;
				}
				if ( empty( $cust_name_hardcoded ) ) {
					$last_name_first     = $this->asp_main->get_setting( 'prefill_wp_user_last_name_first' );
					$cust_name_hardcoded = $last_name_first ? $user_info->last_name . ' ' . $user_info->first_name : $user_info->first_name . ' ' . $user_info->last_name;
				}
			}
		}

		$default_country = $this->asp_main->get_setting( 'popup_default_country' );

		$dont_save_card = $this->asp_main->get_setting( 'dont_save_card' );

		$verify_zip = $this->asp_main->get_setting( 'enable_zip_validation' );

		$checkout_lang = $this->asp_main->get_setting( 'checkout_lang' );

		$checkout_lang = empty( $checkout_lang ) ? 'auto' : $checkout_lang;

		$data               = array();
		$data['product_id'] = $product_id;
		$data['item_name']  = $this->item->get_name();

		$quantity = get_post_meta( $product_id, 'asp_product_quantity', true );
		if ( ! $quantity ) {
			$quantity = 1;
		}
		$data['quantity']          = $quantity;
		$data['custom_quantity']   = get_post_meta( $product_id, 'asp_product_custom_quantity', true );
		$data['amount_variable']   = $a['amount_variable'];
		$data['currency_variable'] = $a['currency_variable'];
		$data['currency']          = $a['currency'];

		$data['stock_control_enabled'] = $stock_control_enabled;
		$data['stock_items']           = $stock_items;

		$data['billing_address']  = $billing_address;
		$data['shipping_address'] = $shipping_address;

		$data['coupons_enabled'] = $coupons_enabled;
		$data['tos']             = $tos;
		$data['item_logo']       = $item_logo;

		$data['url'] = base64_encode( $this->item->get_download_url() );

		$data['client_secret'] = '';
		$data['pi_id']         = '';

		$data['amount'] = $this->item->get_total( true );

		$data['item_price'] = $this->item->get_price( true );

		$data['tax']      = $this->item->get_tax();
		$data['shipping'] = $this->item->get_shipping( true );
		$data['descr']    = $this->item->get_description();

		$data['custom_field']                    = $custom_field;
		$data['custom_field_validation_regex']   = $cf_validation_regex;
		$data['custom_field_validation_err_msg'] = $cf_validation_err_msg;

		$data['stripe_receipt_email'] = $this->asp_main->get_setting( 'stripe_receipt_email' );

		$data['variations'] = $this->variations;

		$data['is_live'] = $this->asp_main->is_live;

		$data['button_key'] = $this->item->get_button_key();

		$data['create_token'] = false;

		$data['customer_email'] = $cust_email_hardcoded;
		$data['customer_name']  = $cust_name_hardcoded;

		$data['dont_save_card'] = ! $dont_save_card ? false : true;

		$data['verify_zip'] = ! $verify_zip ? false : true;

		$data['checkout_lang'] = $checkout_lang;

		$data['customer_default_country'] = $default_country;

		$data['hide_amount_input'] = $this->item->get_meta( 'asp_product_hide_amount_input' );

		$data['show_your_order'] = get_post_meta( $product_id, 'asp_product_show_your_order', true );
		$data['show_your_order'] = $data['show_your_order'] ? 1 : 0;

		$data['addons'] = array();

		$data['payment_methods'][] = array(
			'id'           => 'def',
			'title'        => __( 'Credit or debit card', 'stripe-payments' ),
			'before_title' => ' <svg id="i-creditcard" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="32" height="32" fill="none" stroke="currentcolor" stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5px">
				<path d="M2 7 L2 25 30 25 30 7 Z M5 18 L9 18 M5 21 L11 21" />
				<path d="M2 11 L2 13 30 13 30 11 Z" fill="currentColor" />
			</svg>',
		);

		$data['addonHooks'] = array();

		$data = apply_filters( 'asp-button-output-data-ready', $data, array( 'product_id' => $product_id ) ); //phpcs:ignore

		$data = apply_filters( 'asp_ng_pp_data_ready', $data, array( 'product_id' => $product_id ) ); //phpcs:ignore

		if ( empty( $plan_id ) ) {
			$this->item->set_currency( $data['currency'] );
			$this->item->set_price( $data['item_price'], true );
			$data['item_price'] = $this->item->get_price( true );
			$this->item->set_shipping( $data['shipping'], true );
			$data['shipping'] = $this->item->get_shipping( true );

			$tmp_curr = strtoupper( $data['currency'] );
			if ( isset( $currencies[ $tmp_curr ] ) ) {
				$curr_sym = $currencies[ $tmp_curr ][1];
			} else {
				//no currency code found, let's just use currency code instead of symbol
				$curr_sym = $tmp_curr;
			}
			$display_settings['s'] = $curr_sym;
		}

		$data['items'] = $this->item->get_items();

		$a['data'] = $data;

		if ( isset( $data['fatal_error'] ) ) {
			$a['fatal_error'] = $data['fatal_error'];
		}

		$a['scripts'] = array();
		$a['styles']  = array();
		$a['vars']    = array();

		// Stripe script should come first
		$a['scripts'][] = array(
			'footer' => true,
			'src'    => 'https://js.stripe.com/v3/',
		);

		$site_url       = $data['is_live'] ? get_site_url( null, '', 'https' ) : get_site_url();
		$a['scripts'][] = array(
			'src'    => $site_url . '/wp-includes/js/jquery/jquery.js?ver=1.12.4-wp',
			'footer' => true,
		);

		//filters for addons to add styles, scripts and vars
		$a['styles']  = apply_filters( 'asp_ng_pp_output_add_styles', $a['styles'] );
		$a['scripts'] = apply_filters( 'asp_ng_pp_output_add_scripts', $a['scripts'] );
		$a['vars']    = apply_filters( 'asp_ng_pp_output_add_vars', $a['vars'] );

		if ( ! defined( 'WP_ASP_DEV_MODE' ) ) {
			$a['styles'][]  = array(
				'footer' => false,
				'src'    => WP_ASP_PLUGIN_URL . '/public/views/templates/default/pp-combined.min.css?ver=' . WP_ASP_PLUGIN_VERSION,
			);
			$a['scripts'][] = array(
				'footer' => true,
				'src'    => WP_ASP_PLUGIN_URL . '/public/assets/js/pp-handler.min.js?ver=' . WP_ASP_PLUGIN_VERSION,
			);
		} else {
			$a['styles'][]  = array(
				'footer' => false,
				'src'    => WP_ASP_PLUGIN_URL . '/public/views/templates/default/pure.css?ver=' . WP_ASP_PLUGIN_VERSION,
			);
			$a['styles'][]  = array(
				'footer' => false,
				'src'    => WP_ASP_PLUGIN_URL . '/public/views/templates/default/pp-style.css?ver=' . WP_ASP_PLUGIN_VERSION,
			);
			$a['scripts'][] = array(
				'footer' => true,
				'src'    => WP_ASP_PLUGIN_URL . '/public/assets/js/pp-handler.js?ver=' . WP_ASP_PLUGIN_VERSION,
			);
		}

		$a['hide_state_field'] = $this->asp_main->get_setting( 'hide_state_field' );

		$pay_btn_text = $this->asp_main->get_setting( 'popup_button_text' );

		if ( empty( $pay_btn_text ) ) {
			$pay_btn_text = __( 'Pay %s', 'stripe-payments' );
		} else {
			$pay_btn_text = __( $pay_btn_text, 'stripe-payments' ); //phpcs:ignore
		}

		if ( isset( $data['is_trial'] ) && $data['is_trial'] ) {
			if ( $this->item->get_price() === 0 ) {
				$data['amount_variable'] = false;
			}
			$pay_btn_text = apply_filters( 'asp_customize_text_msg', __( 'Start Free Trial', 'stripe-payments' ), 'start_free_trial' );
		}

		//filter to change pay button text
		$pay_btn_text = apply_filters( 'asp_ng_pp_pay_button_text', $pay_btn_text );

		$a['item'] = $this->item;

		$btn_uniq_id = filter_input( INPUT_GET, 'btn_uniq_id', FILTER_SANITIZE_STRING );
		if ( ! empty( $btn_uniq_id ) ) {
			$a['btn_uniq_id'] = $btn_uniq_id;
		}

		$a['vars']['vars'] = array(
			'data'           => $data,
			'stripe_key'     => ! empty( $data['stripe_key'] ) ? $data['stripe_key'] : $a['stripe_key'],
			'minAmounts'     => $this->asp_main->minAmounts,
			'zeroCents'      => $this->asp_main->zeroCents,
			'ajaxURL'        => admin_url( 'admin-ajax.php' ),
			'currencyFormat' => $display_settings,
			'payBtnText'     => $pay_btn_text,
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
				'strGetForFree'               => __( 'Purchase for Free', 'stripe-payments' ),
				'strCurrencyNotSupported'     => __( 'Currency not supported for this payment method.', 'stripe-payments' ),
			),
		);

		if ( isset( $a['fatal_error'] ) ) {
			$a['vars']['vars']['fatal_error'] = $a['fatal_error'];
		}

		$a['pay_btn_text'] = sprintf( $pay_btn_text, AcceptStripePayments::formatted_price( $this->item->get_total(), $this->item->get_currency() ) );

		//output custom PP CSS if needed
		add_action( 'asp_ng_pp_output_before_closing_body', array( $this, 'output_custom_css' ), 1000 );

		ob_start();
		require_once WP_ASP_PLUGIN_PATH . 'public/views/templates/default/payment-popup.php';
		$tpl = ob_get_clean();
		echo $tpl; //phpcs:ignore
		exit;
	}

	public function output_custom_css( $a ) {
		$pp_additional_css = $this->asp_main->get_setting( 'pp_additional_css' );
		if ( empty( $pp_additional_css ) ) {
			return;
		}
		echo sprintf( '<style>%s</style>', wp_kses( $pp_additional_css, array() ) );
	}

	public function handle_request_token() {
		$out            = array();
		$out['success'] = false;
		$product_id     = filter_input( INPUT_POST, 'product_id', FILTER_SANITIZE_NUMBER_INT );
		$amount         = filter_input( INPUT_POST, 'amount', FILTER_SANITIZE_NUMBER_FLOAT );
		$curr           = filter_input( INPUT_POST, 'curr', FILTER_SANITIZE_STRING );
		$pi_id          = filter_input( INPUT_POST, 'pi', FILTER_SANITIZE_STRING );
		$cust_id        = filter_input( INPUT_POST, 'cust_id', FILTER_SANITIZE_STRING );
		$quantity       = filter_input( INPUT_POST, 'quantity', FILTER_SANITIZE_NUMBER_INT );

		$item = new ASP_Product_Item( $product_id );

		if ( $item->get_last_error() ) {
			$out['err'] = __( 'Error occurred:', 'stripe-payments' ) . ' ' . $item->get_last_error();
			wp_send_json( $out );
		}

		if ( $item->stock_control_enabled() ) {
			$stock_items        = $item->get_stock_items();
			$out['stock_items'] = $stock_items;
			if ( $quantity > $stock_items ) {
				// translators: %d is number of items in stock
				$msg        = apply_filters( 'asp_customize_text_msg', __( 'You cannot order more items than available: %d', 'stripe-payments' ), 'stock_not_available' );
				$out['err'] = sprintf( $msg, $stock_items );
				wp_send_json( $out );
			}
		}

		do_action( 'asp_ng_product_mode_keys', $product_id );

		try {
			ASPMain::load_stripe_lib();
			$key = $this->asp_main->is_live ? $this->asp_main->APISecKey : $this->asp_main->APISecKeyTest;
			\Stripe\Stripe::setApiKey( $key );
		} catch ( \Exception $e ) {
			$out['err'] = __( 'Stripe API error occurred:', 'stripe-payments' ) . ' ' . $e->getMessage();
			wp_send_json( $out );
		} catch ( \Throwable $e ) {
			$out['err'] = __( 'Stripe API error occurred:', 'stripe-payments' ) . ' ' . $e->getMessage();
			wp_send_json( $out );
		}

		$metadata = array();

		try {

			$pi_params = array(
				'amount'   => $amount,
				'currency' => $curr,
			);

			$post_billing_details = filter_input( INPUT_POST, 'billing_details', FILTER_SANITIZE_STRING );

			$post_shipping_details = filter_input( INPUT_POST, 'shipping_details', FILTER_SANITIZE_STRING );

			if ( isset( $post_billing_details ) ) {
				$post_billing_details = html_entity_decode( $post_billing_details );

				$billing_details = json_decode( $post_billing_details );
			}

			$dont_save_card = $this->asp_main->get_setting( 'dont_save_card' );

			if ( ! $dont_save_card ) {
				$customer_opts = array();

				if ( isset( $billing_details ) ) {

					if ( ! empty( $billing_details->name ) ) {
						$customer_opts['name'] = $billing_details->name;
					}

					if ( ! empty( $billing_details->email ) ) {
						$customer_opts['email'] = $billing_details->email;
					}

					if ( isset( $billing_details->address ) && isset( $billing_details->address->line1 ) ) {
						//we have address
						$addr = array(
							'line1'   => $billing_details->address->line1,
							'state'   => isset( $billing_details->address->state ) ? $billing_details->address->state : null,
							'city'    => isset( $billing_details->address->city ) ? $billing_details->address->city : null,
							'country' => isset( $billing_details->address->country ) ? $billing_details->address->country : null,
						);

						if ( isset( $billing_details->address->postal_code ) ) {
							$addr['postal_code'] = $billing_details->address->postal_code;
						}

						$customer_opts['address'] = $addr;
					}
				}

				if ( isset( $post_shipping_details ) ) {
					$post_shipping_details = html_entity_decode( $post_shipping_details );

					$shipping_details = json_decode( $post_shipping_details );

					$shipping = array();

					if ( $shipping_details->name ) {
						$shipping['name'] = $shipping_details->name;
					} elseif ( ! empty( $customer_opts['name'] ) ) {
						$shipping['name'] = $customer_opts['name'];
					}

					if ( isset( $shipping_details->address ) && isset( $shipping_details->address->line1 ) ) {
						//we have address
						$addr = array(
							'line1'   => $shipping_details->address->line1,
							'state'   => isset( $shipping_details->address->state ) ? $shipping_details->address->state : null,
							'city'    => isset( $shipping_details->address->city ) ? $shipping_details->address->city : null,
							'country' => isset( $shipping_details->address->country ) ? $shipping_details->address->country : null,
						);

						if ( isset( $shipping_details->address->postal_code ) ) {
							$addr['postal_code'] = $shipping_details->address->postal_code;
						}

						$shipping['address'] = $addr;

						if ( ! empty( $shipping['name'] ) ) {
							$customer_opts['shipping'] = $shipping;
						}
					}
				}

				$customer_opts = apply_filters( 'asp_ng_before_customer_create_update', $customer_opts, empty( $cust_id ) ? false : $cust_id );

				if ( empty( $cust_id ) ) {
					$customer = \Stripe\Customer::create( $customer_opts );
					$cust_id  = $customer->id;
				} else {
					$customer = \Stripe\Customer::update( $cust_id, $customer_opts );
				}
				$pi_params['customer'] = $cust_id;
			}

			$metadata['Product Name'] = $item->get_name();
			$metadata['Product ID']   = $product_id;

			if ( isset( $metadata ) && ! empty( $metadata ) ) {
				$pi_params['metadata'] = $metadata;
			}
			$description = $item->get_description();
			if ( ! empty( $description ) ) {
				$pi_params['description'] = $description;
			} else {
				$pi_params['description'] = $item->get_name();
			}

			$stripe_receipt_email = $this->asp_main->get_setting( 'stripe_receipt_email' );

			if ( $stripe_receipt_email ) {
				if ( isset( $billing_details ) && isset( $billing_details->email ) && ! empty( $billing_details->email ) ) {
					$pi_params['receipt_email'] = $billing_details->email;
				}
			}

			$pi_params = apply_filters( 'asp_ng_before_pi_create_update', $pi_params );
			if ( $pi_id ) {
				$intent = \Stripe\PaymentIntent::update( $pi_id, $pi_params );
			} else {
				$intent = \Stripe\PaymentIntent::create( $pi_params );
			}
		} catch ( \Exception $e ) {
			$out['shipping'] = wp_json_encode( $shipping );
			$out['err']      = __( 'Error occurred:', 'stripe-payments' ) . ' ' . $e->getMessage();
			wp_send_json( $out );
		} catch ( \Throwable $e ) {
			$out['shipping'] = wp_json_encode( $shipping );
			$out['err']      = __( 'Error occurred:', 'stripe-payments' ) . ' ' . $e->getMessage();
			wp_send_json( $out );
		}

		$out['success']      = true;
		$out['clientSecret'] = $intent->client_secret;
		$out['pi_id']        = $intent->id;
		$out['cust_id']      = $cust_id;
		$out                 = apply_filters( 'asp_ng_before_pi_result_send', $out, $intent );
		wp_send_json( $out );
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
					$tpl_cf .= '<label class="pure-checkbox asp_product_custom_field_label"><input id="asp-custom-field" class="asp_product_custom_field_input" type="checkbox"' . ( $mandatory ? ' data-asp-custom-mandatory' : '' ) . ' name="stripeCustomField"' . ( $mandatory ? ' required' : '' ) . '> ' . $field_descr . '</label>';
					break;
			}
			$tpl_cf      .= '<div id="custom-field-error" class="form-err" role="alert"></div>' .
				'</fieldset>' .
				'</div>';
			$this->tpl_cf = $tpl_cf;
		}
			$output      .= $this->tpl_cf;
			$this->tpl_cf = '';
		return $output;
	}

	public function save_form_data() {
		$out['success'] = true;
		$sess           = ASP_Session::get_instance();
		parse_str( $_POST['form_data'], $form_data );
		$sess->set_transient_data( 'asp_pp_form_data', $form_data );
		wp_send_json( $out );
	}

	public function handle_check_coupon() {
		$product_id = filter_input( INPUT_POST, 'product_id', FILTER_SANITIZE_NUMBER_INT );
		$item       = new ASP_Product_Item( $product_id );

		if ( $item->get_last_error() ) {
			$out['err'] = __( 'Error occurred:', 'stripe-payments' ) . ' ' . $item->get_last_error();
			wp_send_json( $out );
		}

		$coupon_code = filter_input( INPUT_POST, 'coupon_code', FILTER_SANITIZE_STRING );

		$coupon_valid = $item->check_coupon( $coupon_code );
		if ( ! $coupon_valid ) {
			$out['err'] = $item->get_last_error();
			wp_send_json( $out );
		}

		$coupon = $item->get_coupon();

		if ( 'perc' === $coupon['discount_type'] && 100 === intval( $coupon['discount'] ) ) {
			//this is 100% discount coupon. Let's also generate zero-value payment id
			$zero_value_id           = str_replace( '.', '', uniqid( 'free_', true ) );
			$coupon['zero_value_id'] = $zero_value_id;
		}

		wp_send_json( $coupon );

	}

}

new ASP_PP_Handler();
