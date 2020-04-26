<?php

class ASP_Shortcode_NG {

	protected $asp_main             = null;
	protected $stripe_css_inserted  = false;
	protected $product_css_inserted = false;
	protected $button_css_inserted  = false;
	protected $compat_mode          = false;

	/**
	 * Instance of this class.
	 *
	 * @var      object
	 */
	protected static $instance        = null;
	protected static $payment_buttons = array();

	public function __construct() {
		self::$instance = $this;

		$this->asp_main = AcceptStripePayments::get_instance();

		$use_old_api = $this->asp_main->get_setting( 'use_old_checkout_api1' );

		add_shortcode( 'asp_product_ng', array( $this, 'shortcode_asp_product' ) );
		add_shortcode( 'accept_stripe_payment_ng', array( $this, 'shortcode_accept_stripe_payment' ) );
		if ( ! $use_old_api ) {
			add_shortcode( 'asp_product', array( $this, 'shortcode_asp_product' ) );
			add_shortcode( 'accept_stripe_payment', array( $this, 'shortcode_accept_stripe_payment' ) );
			add_filter( 'the_content', array( $this, 'filter_post_type_content' ) );
		}
	}

	public static function filter_post_type_content( $content ) {
		global $post;
		if ( isset( $post ) ) {
			if ( ASPMain::$products_slug === $post->post_type ) { //Handle the content for product type post
				return do_shortcode( '[asp_product id="' . $post->ID . '" is_post_tpl="1" in_the_loop="' . + in_the_loop() . '"]' );
			}
		}
		return $content;
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

	private function gen_fatal_error_box( $msg ) {
		$error_msg  = '<div class="stripe_payments_error_msg" style="color: red;">';
		$error_msg .= $msg;
		$error_msg .= '</div>';
		return $error_msg;
	}

	public function shortcode_asp_product( $atts ) {
		if ( ! isset( $atts['id'] ) || ! is_numeric( $atts['id'] ) ) {
			$msg       = __( 'Error: product ID is invalid.', 'stripe-payments' );
			$error_msg = $this->gen_fatal_error_box( $msg );
			return $error_msg;
		}

		$id   = $atts['id'];
		$item = new ASP_Product_Item( $id );

		$last_error = $item->get_last_error();

		if ( $last_error ) {
			$error_msg = $this->gen_fatal_error_box( $last_error );
			return $error_msg;
		}

		$plan_id = get_post_meta( $id, 'asp_sub_plan_id', true );

		if ( ! empty( $plan_id ) && ! class_exists( 'ASPSUB_main' ) ) {
			//Subs addon not installed or disabled. Show corresponding error message
			$error_msg = $this->gen_fatal_error_box( __( 'This product requires Stripe Payments Subscription addon.' ) );
			return $error_msg;
		}

		if ( ! empty( $plan_id ) && class_exists( 'ASPSUB_main' ) && version_compare( ASPSUB_main::ADDON_VER, '2.0.0t1' ) < 0 ) {
			$error_msg = $this->gen_fatal_error_box( 'Stripe Subscriptions addon version 2.0.0 or newer is required.' );
			return $error_msg;
		}

		$currency = $item->get_currency();

		$button_text = $item->get_button_text();

		//check if we have button_text shortcode parameter. If it's not empty, this should be our button text
		if ( isset( $atts['button_text'] ) && ! empty( $atts['button_text'] ) ) {
			$button_text = esc_attr( $atts['button_text'] );
		}

		$thumb_img = '';
		$thumb_url = get_post_meta( $id, 'asp_product_thumbnail', true );

		if ( $thumb_url ) {
			if ( is_ssl() ) {
				$thumb_url = ASP_Utils::url_to_https( $thumb_url );
			}
			$thumb_img = '<img src="' . $thumb_url . '">';
		}

		$url = get_post_meta( $id, 'asp_product_upload', true );

		if ( ! $url ) {
			$url = '';
		}

		$template_name = 'default'; //this could be made configurable
		$button_color  = 'blue'; //this could be made configurable

		$price    = $item->get_price();
		$shipping = $item->get_shipping();

		//let's apply filter so addons can change price, currency and shipping if needed
		$price_arr = array(
			'price'    => $price,
			'currency' => $currency,
			'shipping' => empty( $shipping ) ? false : $shipping,
		);
		$price_arr = apply_filters( 'asp_ng_modify_price_currency_shipping', $price_arr );
		extract( $price_arr, EXTR_OVERWRITE ); //phpcs:ignore

		$buy_btn = '';

		$button_class = $item->get_button_class();

		$class = ! empty( $button_class ) ? $button_class : 'asp_product_buy_btn ' . $button_color;

		$class = isset( $atts['class'] ) ? $atts['class'] : $class;

		$custom_field = get_post_meta( $id, 'asp_product_custom_field', true );
		$cf_enabled   = $this->asp_main->get_setting( 'custom_field_enabled' );

		if ( ( '' === $custom_field ) || '2' === $custom_field ) {
			$custom_field = $cf_enabled;
		} else {
			$custom_field = intval( $custom_field );
		}

		if ( ! $cf_enabled ) {
			$custom_field = $cf_enabled;
		}

		$thankyou_page = empty( $atts['thankyou_page_url'] ) ? get_post_meta( $id, 'asp_product_thankyou_page', true ) : $atts['thankyou_page_url'];

		if ( ! $shipping ) {
			$shipping = 0;
		}

		$tax = isset( $atts['tax'] ) ? $atts['tax'] : $item->get_tax();

		if ( ! $tax || $tax < 0 ) {
			$tax = 0;
		}

		if ( isset( $atts['tax'] ) ) {
			//save tax overidden data to session
			$uniq_id = uniqid( 'asp_button', true );
			$sess    = ASP_Session::get_instance();
			$sess->set_transient_data( 'overriden_data_' . $uniq_id, array( 'tax' => $tax ) );
		}

		$quantity = $item->get_quantity();

		$under_price_line = '';
		$tot_price        = ! empty( $quantity ) ? $price * $quantity : $price;

		if ( 0 !== $tax ) {
			$tax_str = apply_filters( 'asp_customize_text_msg', __( 'Tax', 'stripe-payments' ), 'tax_str' );
			if ( ! empty( $price ) ) {
				$tax_amount       = AcceptStripePayments::get_tax_amount( $tot_price, $tax, AcceptStripePayments::is_zero_cents( $currency ) );
				$tot_price       += $tax_amount;
				$under_price_line = '<span class="asp_price_tax_section">' . AcceptStripePayments::formatted_price( $tax_amount, $currency ) . ' (' . strtolower( $tax_str ) . ')</span>';
			} else {
				$under_price_line = '<span class="asp_price_tax_section">' . $tax . '% ' . lcfirst( $tax_str ) . '</span>';
			}
		}
		if ( 0 !== $shipping ) {
			$ship_str      = apply_filters( 'asp_customize_text_msg', __( 'Shipping', 'stripe-payments' ), 'shipping_str' );
			$tot_price    += $shipping;
			$shipping_line = AcceptStripePayments::formatted_price( $shipping, $currency ) . ' (' . strtolower( $ship_str ) . ')';
			if ( ! empty( $under_price_line ) ) {
				$under_price_line .= '<span class="asp_price_shipping_section"> + ' . $shipping_line . '</span>';
			} else {
				$under_price_line = '<span class="asp_price_shipping_section">' . $shipping_line . '</span>';
			}
		}

		if ( ! empty( $price ) && ! empty( $under_price_line ) ) {
			$under_price_line .= '<div class="asp_price_full_total">' . __( 'Total:', 'stripe-payments' ) . ' <span class="asp_tot_current_price">' . AcceptStripePayments::formatted_price( $tot_price, $currency ) . '</span> <span class="asp_tot_new_price"></span></div>';
		}

		if ( get_post_meta( $id, 'asp_product_no_popup_thumbnail', true ) !== 1 ) {
			$item_logo = ASP_Utils::get_small_product_thumb( $id );
		} else {
			$item_logo = '';
		}

		$compat_mode = isset( $atts['compat_mode'] ) ? 1 : 0;

		$this->compat_mode = ( $compat_mode ) ? true : false;

		$billing_address  = get_post_meta( $id, 'asp_product_collect_billing_addr', true );
		$shipping_address = get_post_meta( $id, 'asp_product_collect_shipping_addr', true );

		if ( ! $billing_address ) {
			$shipping_address = false;
		}

		$currency_variable = get_post_meta( $id, 'asp_product_currency_variable', true );
		$currency_variable = ! empty( $currency_variable ) ? true : false;

		//Let's only output buy button if we're in the loop. Since the_content hook could be called several times (for example, by a plugin like Yoast SEO for its purposes), we should only output the button only when it's actually needed.
		if ( ! isset( $atts['in_the_loop'] ) || '1' === $atts['in_the_loop'] ) {
			$sc_params = array(
				'product_id'        => $id,
				'name'              => $item->get_name(),
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
				'thankyou_page_url' => $thankyou_page,
				'item_logo'         => $item_logo,
				'billing_address'   => $billing_address,
				'shipping_address'  => $shipping_address,
				'custom_field'      => $custom_field,
				'compat_mode'       => $compat_mode,
				'button_only'       => isset( $atts['button_only'] ) ? intval( $atts['button_only'] ) : null,
				'btn_uniq_id'       => isset( $uniq_id ) ? $uniq_id : '',
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

		if ( ( isset( $atts['fancy'] ) && empty( $atts['fancy'] ) ) || $button_only ) {
			//Just show the stripe payment button (no fancy template)
			$tpl = '<div class="asp_product_buy_button">' . $buy_btn . '</div>';
			$tpl = "<link rel='stylesheet' href='" . WP_ASP_PLUGIN_URL . '/public/views/templates/default/style.css' . "' type='text/css' media='all' />" . $tpl; //phpcs:ignore
			if ( ! $this->compat_mode ) {
				$this->product_css_inserted = true;
			}
			return $tpl;
		}

		//Show the stripe payment button with fancy style template.
		require_once WP_ASP_PLUGIN_PATH . 'public/views/templates/' . $template_name . '/template.php';
		if ( isset( $atts['is_post_tpl'] ) ) {
			$tpl = asp_get_post_template( $this->product_css_inserted );
		} else {
			$tpl = asp_get_template( $this->product_css_inserted );
		}
		if ( ! $this->compat_mode ) {
			$this->product_css_inserted = true;
		}

		$price_line = empty( $price ) ? '' : AcceptStripePayments::formatted_price( $price, $currency );

		$qnt_str = '';
		if ( $quantity && 1 > $quantity ) {
			$qnt_str = 'x ' . $quantity;
		}

		remove_filter( 'the_content', array( $this, 'filter_post_type_content' ) );
		$post = get_post( $id );
		setup_postdata( $post );
		$GLOBALS['post'] = $post; //phpcs:ignore
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
			'quantity'         => $qnt_str,
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

	public function shortcode_accept_stripe_payment( $atts ) {
		extract( ///phpcs:ignore
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
					'customer_name'     => '',
					'currency'          => $this->asp_main->get_setting( 'currency_code' ),
					'currency_variable' => false,
					'checkout_lang'     => $this->asp_main->get_setting( 'checkout_lang' ),
					'button_text'       => $this->asp_main->get_setting( 'button_text' ),
					'compat_mode'       => 0,
				),
				$atts
			)
		);

		$this->compat_mode = ( $compat_mode ) ? true : false;

		if ( empty( $name ) ) {
			$error_msg  = '<div class="stripe_payments_error_msg" style="color: red;">';
			$error_msg .= 'There is an error in your Stripe Payments shortcode. It is missing the "name" field. ';
			$error_msg .= 'You must specify an item name value using the "name" parameter. This value should be unique so this item can be identified uniquely on the page.';
			$error_msg .= '</div>';
			return $error_msg;
		}

		if ( ! empty( $url ) ) {
			$url = base64_encode( $url ); //phpcs:ignore
		} else {
			$url = '';
		}

		if ( ! empty( $thankyou_page_url ) ) {
			$thankyou_page_url = base64_encode( $thankyou_page_url ); //phpcs:ignore
		} else {
			$thankyou_page_url = '';
		}

		if ( ! is_numeric( $quantity ) ) {
			$quantity = absint( $quantity );
		}

		if ( empty( $quantity ) && '1' !== $custom_quantity ) {
			$quantity = 1;
		}

		$price                   = floatval( $price );
		$uniq_id                 = count( self::$payment_buttons ) . uniqid();
		$button_id               = 'asp_ng_button_' . $uniq_id;
		self::$payment_buttons[] = $button_id;

		$item_price     = $price;
		$payment_amount = $custom_quantity ? $price : floatval( $price ) * $quantity;
		if ( AcceptStripePayments::is_zero_cents( $currency ) ) {
			//this is zero-cents currency, amount shouldn't be multiplied by 100
			$price_in_cents = $payment_amount;
		} else {
			$price_in_cents = $payment_amount * 100;
			$item_price     = $price * 100;
		}

		if ( ! empty( $shipping ) ) {
			$shipping_filt = round( $shipping, 2 );
			if ( ! AcceptStripePayments::is_zero_cents( $currency ) ) {
				$shipping = $shipping_filt * 100;
			} else {
				$shipping = $shipping_filt;
			}
		}

		if ( ! empty( $price ) ) {
			//let's apply tax if needed
			if ( ! empty( $tax ) ) {
				$tax_amount     = round( ( $price_in_cents * $tax / 100 ) );
				$price_in_cents = $price_in_cents + $tax_amount;
			}

			//let's apply shipping cost if needed
			if ( ! empty( $shipping ) ) {
				$price_in_cents = $price_in_cents + $shipping;
			}
		}

		if ( empty( $product_id ) ) {
			$hash = md5( wp_json_encode( $atts ) ) . '5';
			//find temp product
			$temp_post = get_posts(
				array(
					'meta_key'       => 'asp_shortcode_hash',
					'meta_value'     => $hash,
					'posts_per_page' => 1,
					'offset'         => 0,
					'post_type'      => ASPMain::$temp_prod_slug,
				)
			);
			wp_reset_postdata();
			if ( empty( $temp_post ) ) {
				// no temp post found. Let's create one
				$new_post                = array();
				$new_post['post_type']   = ASPMain::$temp_prod_slug;
				$new_post['post_title']  = $atts['name'];
				$new_post['post_status'] = 'publish';
				$new_post['meta_input']  = array(
					'asp_shortcode_hash'                   => $hash,
					'asp_product_quantity'                 => $quantity,
					'asp_product_custom_quantity'          => $custom_quantity,
					'asp_product_price'                    => $price,
					'asp_product_tax'                      => $tax,
					'asp_product_shipping'                 => $shipping,
					'asp_product_currency_variable'        => $currency_variable,
					'asp_product_currency'                 => $currency,
					'asp_product_description'              => $description,
					'asp_product_button_text'              => $button_text,
					'asp_product_collect_billing_addr'     => $billing_address,
					'asp_product_collect_shipping_addr'    => $shipping_address,
					'asp_product_button_class'             => $class,
					'asp_product_upload'                   => base64_decode( $url ), //phpcs:ignore
					'asp_product_thumbnail'                => $item_logo,
					'asp_product_thankyou_page'            => empty( $thankyou_page_url ) ? '' : base64_decode( $thankyou_page_url ), //phpcs:ignore
					'asp_product_button_only'              => 1,
					'asp_product_custom_field'             => 2,
					'asp_product_customer_email_hardcoded' => $customer_email,
					'asp_product_customer_name_hardcoded'  => $customer_name,
				);
				$post_id                 = wp_insert_post( $new_post );
				$temp_post               = get_post( $post_id );
			} else {
				$temp_post = $temp_post[0];
			}
			$atts['id'] = $temp_post->ID;
			$ret        = $this->shortcode_asp_product( $atts );
			return $ret;
		}

		$button_key = md5( htmlspecialchars_decode( $name ) . $price_in_cents );

		//Charge description
		//We only generate it if it's empty and if custom qunatity and price is not used
		//If custom quantity and\or price are used, description will be generated by javascript
		$descr_generated = false;
		if ( empty( $description ) && '1' !== $custom_quantity && ( ! empty( $price ) && 0 !== $price ) ) {
			//Create a description using quantity, payment amount and currency
			if ( ! empty( $tax ) || ! empty( $shipping ) ) {
				$formatted_amount = AcceptStripePayments::formatted_price( AcceptStripePayments::is_zero_cents( $currency ) ? $price_in_cents : $price_in_cents / 100, $currency );
			} else {
				$formatted_amount = AcceptStripePayments::formatted_price( $payment_amount, $currency );
			}
			$description     = "{$quantity} X " . $formatted_amount;
			$descr_generated = true;
		}

		// Check if "Disable Buttons Before Javascript Loads" option is set
		$is_disabled = '';
		if ( $this->asp_main->get_setting( 'disable_buttons_before_js_loads' ) ) {
			$is_disabled = ' disabled';
		}

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

		$checkout_lang = empty( $checkout_lang ) ? $this->asp_main->get_setting( 'checkout_lang' ) : $checkout_lang;

		//Currency Display settings
		$display_settings      = array();
		$display_settings['c'] = $this->asp_main->get_setting( 'price_decimals_num', 2 );
		$display_settings['d'] = $this->asp_main->get_setting( 'price_decimal_sep' );
		$display_settings['t'] = $this->asp_main->get_setting( 'price_thousand_sep' );

		$currencies = AcceptStripePayments::get_currencies();
		if ( isset( $currencies[ $currency ] ) ) {
			$curr_sym = $currencies[ $currency ][1];
		} else {
			//no currency code found, let's just use currency code instead of symbol
			$curr_sym = $currencies;
		}

		$curr_pos = $this->asp_main->get_setting( 'price_currency_pos' );

		$display_settings['s']   = $curr_sym;
		$display_settings['pos'] = $curr_pos;

		$display_str = array();
		$tax_str     = apply_filters( 'asp_customize_text_msg', __( 'Tax', 'stripe-payments' ), 'tax_str' );
		$ship_str    = apply_filters( 'asp_customize_text_msg', __( 'Shipping', 'stripe-payments' ), 'shipping_str' );

		$display_str['tax']  = '%s (' . strtolower( $tax_str ) . ')';
		$display_str['ship'] = '%s (' . strtolower( $ship_str ) . ')';

		$home_url = get_home_url( null, '/' );

		$url_params = array(
			'asp_action' => 'show_pp',
			'product_id' => $product_id,
		);

		if ( ! empty( $atts['btn_uniq_id'] ) ) {
			$url_params['btn_uniq_id'] = $atts['btn_uniq_id'];
		}

		$prefetch = $this->asp_main->get_setting( 'frontend_prefetch_scripts' );

		if ( $prefetch ) {
			$url_params['ckey'] = ASP_Utils::get_ckey();
		}

		$iframe_url = add_query_arg( $url_params, $home_url );

		$data = array(
			'is_live'                  => $this->asp_main->is_live,
			'product_id'               => $product_id,
			'iframe_url'               => $iframe_url,
			'button_key'               => $button_key,
			'item_price'               => isset( $item_price ) ? $item_price : 0,
			'quantity'                 => $quantity,
			'custom_quantity'          => $custom_quantity,
			'description'              => $description,
			'descrGenerated'           => $descr_generated,
			'shipping'                 => $shipping,
			'tax'                      => $tax,
			'image'                    => $item_logo,
			'currency'                 => $currency,
			'currency_variable'        => $currency_variable,
			'locale'                   => ( empty( $checkout_lang ) ? 'auto' : $checkout_lang ),
			'name'                     => htmlspecialchars_decode( $name ),
			'url'                      => $url,
			'amount'                   => $price_in_cents,
			'billingAddress'           => ( empty( $billing_address ) ? false : true ),
			'shippingAddress'          => ( empty( $shipping_address ) ? false : true ),
			'customer_email'           => $customer_email,
			'uniq_id'                  => $uniq_id,
			'variable'                 => ( empty( $price ) ? true : false ),
			'zeroCents'                => $this->asp_main->zeroCents,
			'addonHooks'               => array(),
			'button_text'              => esc_attr( $button_text ),
			'out_of_stock'             => $out_of_stock,
			'stock_control_enabled'    => $stock_control_enabled,
			'stock_items'              => $stock_items,
			'currencyFormat'           => $display_settings,
			'displayStr'               => $display_str,
			'thankyou_page_url'        => $thankyou_page_url,
			'show_custom_amount_input' => false,
		);

		$data = apply_filters( 'asp-button-output-data-ready', $data, $atts ); //phpcs:ignore

		$output = '';

		//Let's insert Stripe default stylesheet only when it's needed
		if ( 'stripe-button-el' === $class && ! ( ! $this->compat_mode && $this->stripe_css_inserted ) ) {
			$output                    = "<link rel = 'stylesheet' href = 'https://checkout.stripe.com/v3/checkout/button.css' type = 'text/css' media = 'all' />";
			$this->stripe_css_inserted = true;
		}

		$output .= $this->get_styles();

		$output .= "<form id = 'asp_ng_form_{$uniq_id}' class='asp-stripe-form' action = '' METHOD = 'POST'> ";

		$output .= $this->get_button_code_new_method( $data );

		$output .= '<div class="asp-child-hidden-fields" style="display: none !important;"></div>';

		$trans_name        = 'stripe-payments-' . $button_key; //Create key using the item name.
		$trans['tax']      = $tax;
		$trans['shipping'] = $shipping;
		$trans['price']    = $price;
		set_transient( $trans_name, $trans, 2 * 3600 ); //Save the price for this item for 2 hours.
		$output .= '</form>';
		//before button filter
		if ( ! $out_of_stock ) {
			$output = apply_filters( 'asp_ng_button_output_before_button', $output, $data, $class );
		}
		$output .= '<div id="asp-all-buttons-container-' . $uniq_id . '" class="asp_all_buttons_container">';
		$output .= $button;
		//after button filter
		if ( ! $out_of_stock ) {
			$output = apply_filters( 'asp_ng_button_output_after_button', $output, $data, $class );
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

		$output .= '<script>';
		$output .= 'var asp_data_' . $uniq_id . ' = ' . wp_json_encode( $data ) . ';';
		$output .= 'if(typeof jQuery!=="undefined") {jQuery(document).ready(function() {new stripeHandlerNG(asp_data_' . $uniq_id . ');});} else { if (typeof wpaspInitOnDocReady==="undefined") {var wpaspInitOnDocReady=[];} wpaspInitOnDocReady.push(asp_data_' . $uniq_id . ');}';
		$output .= '</script>';

		$prefetch = $this->asp_main->get_setting( 'frontend_prefetch_scripts' );
		if ( $prefetch ) {
			$this->asp_main->footer_scripts .= '<link rel="prefetch" as="document" href="' . $data['iframe_url'] . '" />';

			if ( empty( $this->asp_main->sc_scripts_prefetched ) ) {
				$this->asp_main->footer_scripts .= '<link rel="dns-prefetch" href="https://q.stripe.com" />';
				$this->asp_main->footer_scripts .= '<link rel="prefetch" href="https://js.stripe.com/v3/" />';
				if ( ! defined( 'WP_ASP_DEV_MODE' ) ) {
					$this->asp_main->footer_scripts .= '<link rel="prefetch" as="style" href="' . WP_ASP_PLUGIN_URL . '/public/views/templates/default/pp-combined.min.css?ver=' . WP_ASP_PLUGIN_VERSION . '" />';
					$this->asp_main->footer_scripts .= '<link rel="prefetch" as="script" href="' . WP_ASP_PLUGIN_URL . '/public/assets/js/pp-handler.min.js?ver=' . WP_ASP_PLUGIN_VERSION . '" />';
				} else {
					$this->asp_main->footer_scripts .= '<link rel="prefetch" as="style" href="' . WP_ASP_PLUGIN_URL . '/public/views/templates/default/pure.css?ver=' . WP_ASP_PLUGIN_VERSION . '" />';
					$this->asp_main->footer_scripts .= '<link rel="prefetch" as="style" href="' . WP_ASP_PLUGIN_URL . '/public/views/templates/default/pp-style.css?ver=' . WP_ASP_PLUGIN_VERSION . '" />';
					$this->asp_main->footer_scripts .= '<link rel="prefetch" as="script" href="' . WP_ASP_PLUGIN_URL . '/public/assets/js/pp-handler.js?ver=' . WP_ASP_PLUGIN_VERSION . '" />';
				}
			}
		}
		return $output;
	}

	private function get_styles() {
		$output = '';
		if ( ! $this->button_css_inserted || $this->compat_mode ) {
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

	private function get_button_code_new_method( $data ) {
		$output = '';

		if ( $data ) {
			if ( 0 !== $data['product_id'] ) {
				$output .= "<input type='hidden' name='asp_product_id' value='{$data['product_id']}' />";
			}
		}

		if ( $data['show_custom_amount_input'] ) {

			if ( $data['amount'] == 0 ) { //price not specified, let's add an input box for user to specify the amount
				$str_enter_amount = apply_filters( 'asp_customize_text_msg', __( 'Enter amount', 'stripe-payments' ), 'enter_amount' );
				$output          .= "<div class='asp_product_item_amount_input_container'>"
				. "<input type='number' min='0.01' step='0.01' size='10' class='asp_product_item_amount_input' id='stripeAmount_{$data[ 'uniq_id' ]}' value='' name='stripeAmount' placeholder='" . $str_enter_amount . "' required/>";
				if ( ! $data['currency_variable'] ) {
					$output .= "<span class='asp_product_item_amount_currency_label' style='margin-left: 5px; display: inline-block'> {$data[ 'currency' ]}</span>";
				}
				$output .= "<span style='display: block;' id='error_explanation_{$data[ 'uniq_id' ]}'></span>"
				. '</div>';
			}
			// if ( $data['currency_variable'] ) {
			// 	//let's add a box where user can select currency
			// 	$output .= '<div class="asp_product_currency_input_container">';
			// 	$output .= '<select id="stripeCurrency_' . $data['uniq_id'] . '" class="asp_product_currency_input" name="stripeCurrency">';
			// 	$currArr = AcceptStripePayments::get_currencies();
			// 	$tpl     = '<option data-asp-curr-sym="%s" value="%s"%s>%s</option>';
			// 	foreach ( $currArr as $code => $curr ) {
			// 		if ( $code !== '' ) {
			// 			$checked = $data['currency'] === $code ? ' selected' : '';
			// 			$output .= sprintf( $tpl, $curr[1], $code, $checked, $curr[0] );
			// 		}
			// 	}
			// 	$output .= '</select>';
			// 	$output .= '</div>';
			// }

		}

		return $output;
	}

}
