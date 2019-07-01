<?php

class AcceptStripePaymentsShortcodeNG
{

	var $ASPClass			 = null;
	var $StripeCSSInserted		 = false;
	var $ProductCSSInserted		 = false;
	var $ButtonCSSInserted		 = false;
	var $CompatMode			 = false;
	var $variations			 = array();
	protected static $instance		 = null;
	protected static $payment_buttons	 = array();
	protected $tplTOS			 = '';
	protected $tplCF			 = '';
	protected $locDataPrinted		 = false;

	function __construct()
	{
		$this->ASPClass = AcceptStripePayments::get_instance();
		add_action('wp_enqueue_scripts', array($this, 'register_scripts'));
		add_shortcode('asp_product_ng', array($this, 'shortcode_asp_product'));
	}

	public static function get_instance()
	{
		if (null == self::$instance) {
			self::$instance = new self;
		}
		return self::$instance;
	}

	public function register_scripts()
	{
		wp_register_script('asp-stripe-script-ng', 'https://js.stripe.com/v3/', array(), null, true);
		wp_register_script('asp-stripe-handler-ng', WP_ASP_PLUGIN_URL . '/public/assets/js/stripe-handler-ng.js', array('jquery'), WP_ASP_PLUGIN_VERSION, true);
		wp_register_style('asp-pure-css', 'https://unpkg.com/purecss@1.0.0/build/pure-min.css', array(), null);

		wp_enqueue_style('asp-pure-css');
		wp_enqueue_script('asp-stripe-script-ng');
		wp_enqueue_script('asp-stripe-handler-ng');
	}

	public function shortcode_asp_product($atts)
	{

		if (!isset($atts['id']) || !is_numeric($atts['id'])) {
			$error_msg	 = '<div class="stripe_payments_error_msg" style="color: red;">';
			$error_msg	 .= __("Error: product ID is invalid.", 'stripe-payments');
			$error_msg	 .= '</div>';
			return $error_msg;
		}

		$id	 = $atts['id'];
		$item	 = new ASPItem($id);

		if (!empty($item->get_last_error())) {
			$error_msg	 = '<div class="stripe_payments_error_msg" style="color: red;">';
			$error_msg	 .= $item->get_last_error();
			$error_msg	 .= '</div>';
			return $error_msg;
		}

		$button_text = $item->get_button_text();

		if (!empty($atts['button_text'])) {
			$button_text = esc_attr($atts['button_text']);
		}

		$uniq_id = uniqid();

		//button class
		$class = !empty($atts['class']) ? $atts['class'] : $item->get_button_class();

		if (empty($class)) {
			$class = "asp_product_buy_btn blue";
		}

		$currency = $item->get_currency();

		//price
		$price = $item->get_price(true);

		$custom_field	 = get_post_meta($id, 'asp_product_custom_field', true);
		$cf_enabled	 = $this->ASPClass->get_setting('custom_field_enabled');

		if (($custom_field === "") || $custom_field === "2") {
			$custom_field = $cf_enabled;
		} else {
			$custom_field = intval($custom_field);
		}

		if (!$cf_enabled) {
			$custom_field = $cf_enabled;
		}

		$coupons_enabled = get_post_meta($id, 'asp_product_coupons_setting', true);

		if (($coupons_enabled === "") || $coupons_enabled === "2") {
			$coupons_enabled = $this->AcceptStripePayments->get_setting('coupons_enabled');
		}

		$itemData = array(
			'productId'	 => $id,
			'product_id'	 => $id, //for addons compatability
			'is_live'	 => $this->ASPClass->is_live,
			'uniq_id'	 => $uniq_id,
			'currency'	 => $item->get_currency(),
			'variable'	 => empty($price) ? true : false,
			'price'		 => $price,
			'tax'		 => $item->get_tax(true),
			'shipping'	 => $item->get_shipping(true),
			'quantity'	 => $item->get_quantity(),
			'custom_field'	 => $custom_field,
			'coupons_enabled' => $coupons_enabled
		);

		$output = '';

		$template_name	 = "default-ng";
		require_once(WP_ASP_PLUGIN_PATH . 'public/views/templates/' . $template_name . '/template-ng.php');
		$tplClass	 = new ASPTemplateNG();
		$tpl		 = $tplClass->get_template(false);

		$post = get_post($id);

		$thumb_img	 = '';
		$thumb_url	 = $item->get_thumb();

		if ($thumb_url) {
			$thumb_img = '<img src="' . $thumb_url . '">';
		}

		$descr = $post->post_content;
		global $wp_embed;
		if (isset($wp_embed) && is_object($wp_embed)) {
			if (method_exists($wp_embed, 'autoembed')) {
				$descr = $wp_embed->autoembed($descr);
			}
			if (method_exists($wp_embed, 'run_shortcode')) {
				$descr = $wp_embed->run_shortcode($descr);
			}
		}

		$descr = wpautop(do_shortcode($descr));

		$buy_btn = $this->get_button_code($itemData, $class, $button_text);

		$quantity	 = $item->get_quantity();
		$qntStr		 = '';
		if ($quantity && $quantity != 1) {
			$qntStr = 'x ' . $quantity;
		}

		$price_line = empty($item->get_price()) ? '' : AcceptStripePayments::formatted_price($item->get_price(), $currency);

		$under_price_line	 = '';
		$tot_price		 = $item->get_price() * $item->get_quantity();

		$tax = $item->get_tax();

		if ($tax !== 0) {
			$taxStr = apply_filters('asp_customize_text_msg', __('Tax', 'stripe-payments'), 'tax_str');
			if (!empty($price)) {
				$tax_amount	 = $item->get_tax_amount();
				$tot_price	 += $tax_amount;
				$tax_amt	 = AcceptStripePayments::formatted_price($tax_amount, $currency);
			} else {
				$tax_amt = $item->get_tax() . '%';
			}
			$under_price_line = sprintf('<span class="asp_price_tax_section asp_price_tax_section-ng">%s: <span>%s</span></span>', $taxStr, $tax_amt);
		}

		$shipping = $item->get_shipping();

		if ($shipping !== 0) {
			$shipStr	 = apply_filters('asp_customize_text_msg', __('Shipping', 'stripe-payments'), 'shipping_str');
			$tot_price	 += $shipping;
			if (!empty($under_price_line)) {
				$under_price_line .= sprintf('<span class="asp_price_shipping_section asp_price_shipping_section-ng">%s: <span>%s</span></span>', $shipStr, AcceptStripePayments::formatted_price($shipping, $currency));
			}
		}

		if (!empty($under_price_line)) {
			$under_price_line .= sprintf('<div class="asp_price_full_total"%s>%s <span class="asp_tot_current_price">%s</span> <span class="asp_tot_new_price"></span></div>', empty($price) ? ' style="display: none;"' : '', __('Total:', 'stripe-payments'), AcceptStripePayments::formatted_price($tot_price, $currency));
		}

		$product_tags = array(
			'thumb_img'		 => $thumb_img,
			'quantity'		 => $qntStr,
			'name'			 => $post->post_title,
			'description'		 => $descr,
			'price'			 => $price_line,
			'under_price_line'	 => $under_price_line,
			'buy_btn'		 => $buy_btn,
		);

		$product_tags = apply_filters('asp_product_tpl_tags_arr', $product_tags, $id);

		foreach ($product_tags as $tag => $repl) {
			$tpl = str_replace('%_' . $tag . '_%', $repl, $tpl);
		}

		$output .= $tpl;
		return '<div data-asp-ng-cont-id="' . $uniq_id . '">' . $output . '</div>';
	}

	function get_button_code($itemData, $class, $button_text)
	{
		$output = '';
		if (!$this->locDataPrinted) {
			$loc_data		 = $this->get_loc_data($itemData['currency']);
			$output			 .= '<script>var aspFrontVars =' . json_encode($loc_data) . ';</script>';
			$this->locDataPrinted	 = true;
		}

		$output .= '<script>jQuery(document).ready(function() {new stripeHandlerNG(' . json_encode($itemData) . ')});</script>';

		$styles	 = AcceptStripePaymentsShortcode::get_instance()->get_styles();
		$output	 .= $styles;

		$output .= "<form id = 'stripe_form_{$itemData['uniq_id']}' data-asp-ng-form-id='{$itemData['uniq_id']}' class='pure-form pure-form-stacked asp-stripe-form' action = '' METHOD = 'POST'> ";


		if (empty($itemData['price'])) { //price not specified, let's add an input box for user to specify the amount
			$str_enter_amount	 = apply_filters('asp_customize_text_msg', __('Enter amount', 'stripe-payments'), 'enter_amount');
			$output			 .= "<div class='asp_product_item_amount_input_container'>"
				. " <fieldset>"
				. "<input type='text' size='10' class='pure-input-1 asp_product_item_amount_input' id='stripeAmount_{$itemData['uniq_id']}' value='' name='stripeAmount' placeholder='" . $str_enter_amount . " ({$itemData['currency']})' required/>";
			$output			 .= "<span class='pure-form-message asp-product-error-msg' id='error_explanation_{$itemData['uniq_id']}'></span>"
				. "</fieldset>"
				. "</div>";
		}

		$output = apply_filters('asp_button_output_before_custom_field', $output, $itemData);

		//Output Custom Field if needed
		$output = $this->tpl_get_cf($output, $itemData);

		//Get subscription plan ID for the product (if any)
		$plan_id = get_post_meta($itemData['product_id'], 'asp_sub_plan_id', true);

		//Coupons
		if (isset($itemData['coupons_enabled']) && $itemData['coupons_enabled'] == "1" && !$itemData['variable']) {
			if (isset($itemData['product_id'])) {
				//check if this is subscription product. If it is, we will only display coupon field if subs addon version is >=1.3.3t1
				if (!$plan_id || ($plan_id && class_exists('ASPSUB_main') && version_compare(ASPSUB_main::ADDON_VER, '1.3.3t1') >= 0)) {
					$str_coupon_label = __('Coupon Code', 'stripe-payments');
					ob_start();
					?>
				<div class="asp_product_coupon_input_container">
					<label class="asp_product_coupon_field_label"><?php echo $str_coupon_label; ?></label>
					<div class="pure-g">
						<div class="pure-u-1 pure-u-md-3-5">
							<div class="pure-u-1 pure-u-md-23-24">
								<input id="asp-coupon-field-<?php echo $itemData['uniq_id']; ?>" class="pure-input-1 asp_product_coupon_field_input-ng" type="text" name="stripeCoupon">
							</div>
						</div>
						<div class="pure-u-1 pure-u-md-2-5">
							<button type="button" id="asp-redeem-coupon-btn-<?php echo $itemData['uniq_id']; ?>" class="pure-button asp_coupon_apply_btn-ng"><?php _e('Apply', 'stripe-payments'); ?></button>
						</div>
					</div>
					<div id="asp-coupon-info-<?php echo $itemData['uniq_id']; ?>" class="asp_product_coupon_info"></div>
				</div>
				<?php
				$output .= ob_get_clean();
			}
		}
	}

	$output .= '</form>';

	$output	 .= '<div id="asp-all-buttons-container-' . $itemData['uniq_id'] . '" class="asp_all_buttons_container">';
	$output	 .= '<div class="asp_product_buy_btn_container">';
	$output	 .= sprintf('<button class="%s" type="submit" data-asp-ng-button-id="%s"><span>%s</span></button>', $class, $itemData['uniq_id'], $button_text);
	$output	 .= '</div>';
	$output	 .= '</div>';
	$output	 .= '<div id="asp-btn-spinner-container-' . $itemData['uniq_id'] . '" class="asp-btn-spinner-container" style="display: none !important">'
		. '<div class="asp-btn-spinner">'
		. '<div></div>'
		. '<div></div>'
		. '<div></div>'
		. '<div></div>'
		. '</div>'
		. '</div>';
	return $output;
}

function tpl_get_cf($output, $data)
{
	if ($data['custom_field'] == 1 && empty($this->tplCF)) {
		$replaceCF = apply_filters('asp_button_output_replace_custom_field', '', $data);
		if (!empty($replaceCF)) {
			//we got custom field replaced
			$this->tplCF	 = $replaceCF;
			$output		 .= $this->tplCF;
			$this->tplCF	 = '';
			return $output;
		}
		$field_type	 = $this->ASPClass->get_setting('custom_field_type');
		$field_name	 = $this->ASPClass->get_setting('custom_field_name');
		$field_name	 = empty($field_name) ? __('Custom Field', 'stripe-payments') : $field_name;
		$field_descr	 = $this->ASPClass->get_setting('custom_field_descr');
		$descr_loc	 = $this->ASPClass->get_setting('custom_field_descr_location');
		$mandatory	 = $this->ASPClass->get_setting('custom_field_mandatory');
		$tplCF		 = '';
		$tplCF		 .= "<div class='asp_product_custom_field_input_container'>";
		$tplCF		 .= "<fieldset>";
		$tplCF		 .= '<input type="hidden" name="stripeCustomFieldName" value="' . esc_attr($field_name) . '">';
		switch ($field_type) {
			case 'text':
				if ($descr_loc !== 'below') {
					$tplCF .= '<label class="asp_product_custom_field_label">' . $field_name . ' ' . '</label><input id="asp-custom-field-' . $data['uniq_id'] . '" class="asp_product_custom_field_input" type="text"' . ($mandatory ? ' data-asp-custom-mandatory' : '') . ' name="stripeCustomField" placeholder="' . $field_descr . '"' . ($mandatory ? ' required' : '') . '>';
				} else {
					$tplCF	 .= '<label class="asp_product_custom_field_label">' . $field_name . ' ' . '</label><input id="asp-custom-field-' . $data['uniq_id'] . '" class="asp_product_custom_field_input" type="text"' . ($mandatory ? ' data-asp-custom-mandatory' : '') . ' name="stripeCustomField"' . ($mandatory ? ' required' : '') . '>';
					$tplCF	 .= '<div class="asp_product_custom_field_descr">' . $field_descr . '</div>';
				}
				break;
			case 'checkbox':
				$tplCF .= '<label class="pure-checkbox asp_product_custom_field_label"><input id="asp-custom-field-' . $data['uniq_id'] . '" class="asp_product_custom_field_input" type="checkbox"' . ($mandatory ? ' data-asp-custom-mandatory' : '') . ' name="stripeCustomField"' . ($mandatory ? ' required' : '') . '>' . $field_descr . '</label>';
				break;
		}
		$tplCF		 .= "<span id='custom_field_error_explanation_{$data['uniq_id']}' class='pure-form-message asp_product_custom_field_error'></span>" .
			"</fieldset>" .
			"</div>";
		$this->tplCF	 = $tplCF;
	}
	$cfPos = $this->ASPClass->get_setting('custom_field_position');
	if ($cfPos !== 'below') {
		$output		 .= $this->tplCF;
		$this->tplCF	 = '';
	} else {
		add_filter('asp_button_output_after_button', array($this, 'after_button_add_сf_filter'), 990, 3);
	}
	return $output;
}

function after_button_add_сf_filter($output, $data, $class)
{
	$output		 .= $this->tplCF;
	$this->tplCF	 = '';
	return $output;
}

private function get_loc_data($currency)
{

	$key = $this->ASPClass->is_live ? $this->ASPClass->APIPubKey : $this->ASPClass->APIPubKeyTest;

	$minAmounts	 = $this->ASPClass->minAmounts;
	$zeroCents	 = $this->ASPClass->zeroCents;

	$amountOpts = array(
		'applySepOpts'	 => $this->ASPClass->get_setting('price_apply_for_input'),
		'decimalSep'	 => $this->ASPClass->get_setting('price_decimal_sep'),
		'thousandSep'	 => $this->ASPClass->get_setting('price_thousand_sep'),
	);

	global $wp;
	$current_url = home_url(add_query_arg(array($_GET), $wp->request));

	//Currency Display settings
	$display_settings	 = array();
	$display_settings['c'] = $this->ASPClass->get_setting('price_decimals_num', 2);
	$display_settings['d'] = $this->ASPClass->get_setting('price_decimal_sep');
	$display_settings['t'] = $this->ASPClass->get_setting('price_thousand_sep');

	$currencies = AcceptStripePayments::get_currencies();
	if (isset($currencies[$currency])) {
		$curr_sym = $currencies[$currency][1];
	} else {
		//no currency code found, let's just use currency code instead of symbol
		$curr_sym = $currencies;
	}

	$curr_pos = $this->ASPClass->get_setting('price_currency_pos');

	$display_settings['s']	 = $curr_sym;
	$display_settings['pos']	 = $curr_pos;

	$loc_data = array(
		'strEnterValidAmount' => apply_filters('asp_customize_text_msg', __('Please enter a valid amount', 'stripe-payments'), 'enter_valid_amount'),
		'strMinAmount' => apply_filters('asp_customize_text_msg', __('Minimum amount is', 'stripe-payments'), 'min_amount_is'),
		'strEnterQuantity' => apply_filters('asp_customize_text_msg', __('Please enter quantity.', 'stripe-payments'), 'enter_quantity'),
		'strQuantityIsZero' => apply_filters('asp_customize_text_msg', __('Quantity can\'t be zero.', 'stripe-payments'), 'quantity_is_zero'),
		'strQuantityIsFloat' => apply_filters('asp_customize_text_msg', __('Quantity should be integer value.', 'stripe-payments'), 'quantity_is_float'),
		'strStockNotAvailable' => apply_filters('asp_customize_text_msg', __('You cannot order more items than available: %d', 'stripe-payments'), 'stock_not_available'),
		'strTax' => apply_filters('asp_customize_text_msg', __('Tax', 'stripe-payments'), 'tax_str'),
		'strShipping' => apply_filters('asp_customize_text_msg', __('Shipping', 'stripe-payments'), 'shipping_str'),
		'strTotal' => __('Total:', 'stripe-payments'),
		'strPleaseFillIn' => apply_filters('asp_customize_text_msg', __('Please fill in this field.', 'stripe-payments'), 'fill_in_field'),
		'strPleaseCheckCheckbox' => __('Please check this checkbox.', 'stripe-payments'),
		'strMustAcceptTos' => apply_filters('asp_customize_text_msg', __('You must accept the terms before you can proceed.', 'stripe-payments'), 'accept_terms'),
		'strRemoveCoupon' => apply_filters('asp_customize_text_msg', __('Remove coupon', 'stripe-payments'), 'remove_coupon'),
		'strRemove'	=> apply_filters('asp_customize_text_msg', __('Remove', 'stripe-payments'), 'remove'),
		'strStartFreeTrial' => apply_filters('asp_customize_text_msg', __('Start Free Trial', 'stripe-payments'), 'start_free_trial'),
		'strInvalidCFValidationRegex' => __('Invalid validation RegEx: ', 'stripe-payments'),
		'strErrorOccurred' => __('Error occurred', 'stripe-payments'),
		'ajaxURL' => admin_url('admin-ajax.php'),
		'pubKey' => $key,
		'current_url' => $current_url,
		'minAmounts' => $minAmounts,
		'zeroCents'	=> $zeroCents,
		'amountOpts' => $amountOpts,
		'currencyFormat' => $display_settings,
	);
	return $loc_data;
}
}
