<!DOCTYPE html>
<html>

<head>
<?php
//include inline css file
$css = file_get_contents( WP_ASP_PLUGIN_PATH . 'public/views/templates/default/pp-inline-head.css' );
$css = str_replace( array( "\t", "\r\n" ), array( '', '' ), $css );
echo '<style>' . $css . '</style>' . "\r\n";
?>
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta charset="utf-8">
	<title><?php echo esc_html( $a['page_title'] ); ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php

	foreach ( $a['vars'] as $var => $data ) {
		printf(
			"<script type='text/javascript'>
            /* <![CDATA[ */
            var %s = %s;
            /* ]]> */
            </script>\r\n",
			esc_js( $var ),
			wp_json_encode( $data )
		);
	}

	$icon = get_site_icon_url();
	if ( $icon ) {
		printf( '<link rel="icon" href="%s" />' . "\r\n", esc_url( $icon ) );
	}

	$a['data']['customer_default_country'] = apply_filters( 'asp_ng_pp_default_country_override', $a['data']['customer_default_country'] );

	?>
</head>

<body<?php echo isset( $a['prod_id'] ) ? sprintf( ' id="product-%d"', esc_attr( $a['prod_id'] ) ) : ''; ?>>
	<div id="Aligner" class="Aligner">
		<?php if ( ! $a['data']['is_live'] ) { ?>
		<a href="https://stripe.com/docs/testing#cards" target="_blank" id="test-mode"><?php esc_html_e( 'TEST MODE', 'stripe-payments' ); ?></a>
		<?php } ?>
		<div id="global-spinner" class="small-spinner"></div>
		<div id="Aligner-item">
			<div id="smoke-screen">
				<span id="btn-spinner" class="small-spinner"></span>
				<div id="checkmark-cont">
					<svg class="checkmark" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 52 52">
						<circle class="checkmark__circle" cx="26" cy="26" r="25" fill="none" />
						<path class="checkmark__check" fill="none" d="M14.1 27.2l7.1 7.2 16.7-16.8" /></svg>
				</div>
				<div id="redirect-spinner" style="display:none;">
					<span class="rs-arrow rs-arrow-first bounceAlpha"></span>
					<span class="rs-arrow rs-arrow-second bounceAlpha"></span>
				</div>
			</div>
			<div id="modal-header">
				<?php if ( $a['data']['item_logo'] ) { ?>
				<div id="item-logo-cont">
					<img id="item-logo" width="70" height="70" src="<?php echo esc_url( $a['data']['item_logo'] ); ?>">
				</div>
				<?php } ?>
				<span id="modal-close-btn" title="<?php esc_html_e( 'Close', 'stripe-payments' ); ?>" tabindex="0">
					<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
						<path d="M14.59 8L12 10.59 9.41 8 8 9.41 10.59 12 8 14.59 9.41 16 12 13.41 14.59 16 16 14.59 13.41 12 16 9.41 14.59 8zM12 2C6.47 2 2 6.47 2 12s4.47 10 10 10 10-4.47 10-10S17.53 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z" />
					</svg>
				</span>
				<div id="item-name"><?php echo esc_html( $a['item_name'] ); ?></div>
				<div id="item-descr"><?php echo esc_html( $a['data']['descr'] ); ?></div>
			</div>
			<div id="modal-body" class="pure-g">
				<div class="pure-u-1">
					<div id="global-error" <?php echo isset( $a['fatal_error'] ) ? 'style="display: block"' : ''; ?>>
						<?php echo isset( $a['fatal_error'] ) ? esc_html( $a['fatal_error'] ) : ''; ?>
					</div>
					<form method="post" id="payment-form" class="pure-form pure-form-stacked" <?php echo isset( $a['fatal_error'] ) ? 'style="display: none;"' : ''; ?>>
						<?php if ( $a['data']['stock_control_enabled'] && $a['data']['show_remaining'] ) { ?>
							<div id="available-quantity-cont" class="pure-u-1">
								<span><?php esc_html_e( 'Available quantity', 'stripe-payments' ); ?>: </span><span><?php echo $a['data']['stock_items']; ?></span>
							</div>
						<?php } ?>
						<?php if ( $a['data']['amount_variable'] && ! $this->item->get_meta( 'asp_product_hide_amount_input' ) ) { ?>
						<div id="amount-cont" class="pure-u-1">
							<label for="amount"><?php echo esc_html( apply_filters( 'asp_customize_text_msg', __( 'Enter amount', 'stripe-payments' ), 'enter_amount' ) ); ?></label>
							<input class="pure-input-1" id="amount" name="amount" inputmode="decimal" value="<?php echo esc_attr( ! empty( $a['data']['item_price'] ) ? ASP_Utils::formatted_price( $a['data']['item_price'], false, true ) : '' ); ?>" required>
							<div id="amount-error" class="form-err" role="alert"></div>
						</div>
						<?php } ?>
						<?php if ( $a['data']['currency_variable'] ) { ?>
						<div id="currency-cont" class="pure-u-1">
							<label for="currency"><?php esc_html_e( 'Select currency', 'stripe-payments' ); ?></label>
							<select class="pure-input-1" id="currency" name="currency">
								<?php
								//let's add a box where user can select currency
								$output   = '';
								$curr_arr = ASP_Utils::get_currencies();
								$curr_arr = apply_filters( 'asp_ng_available_currencies', $curr_arr );
								$tpl      = '<option data-asp-curr-sym="%s" value="%s"%s>%s</option>';
								foreach ( $curr_arr as $code => $curr ) {
									if ( '' !== $code ) {
										$checked = $a['data']['currency'] === $code ? ' selected' : '';
										$output .= sprintf( $tpl, $curr[1], $code, $checked, $curr[0] );
									}
								}
							echo $output; //phpcs:ignore
								?>
							</select>
						</div>
						<?php } ?>
						<?php if ( $a['data']['custom_quantity'] ) { ?>
						<div id="quantity-cont" class="pure-u-1">
							<label for="quantity"><?php esc_html_e( 'Enter quantity', 'stripe-payments' ); ?></label>
							<input type="number" min="1" class="pure-input-1" id="quantity" name="quantity" inputmode="numeric" value="<?php echo esc_attr( $a['data']['quantity'] ); ?>" required>
							<div id="quantity-error" class="form-err" role="alert"></div>
						</div>
						<?php } ?>
						<?php if ( isset( $a['custom_fields'] ) ) { ?>
						<div id="custom-fields-cont" class="pure-u-1">
                            <?php echo $a['custom_fields']; //phpcs:ignore ?>
						</div>
						<?php } ?>
						<?php
						if ( ! empty( $a['data']['variations'] ) ) {
							$variations_str = '';
							$var_count      = count( $a['data']['variations']['groups'] );
							$curr_var       = 1;
							foreach ( $a['data']['variations']['groups'] as $grp_id => $group ) {
								if ( ! empty( $a['data']['variations']['names'] ) ) {
									if ( $var_count % 2 && $curr_var === $var_count ) {
										$variations_str .= '<div class="pure-u-1">';
									} else {
										$variations_str .= '<div class="pure-u-1 pure-u-md-12-24 variation">';
									}
									$variations_str .= '<fieldset>';
									$variations_str .= '<legend>' . $group . '</legend>';
									if ( isset( $a['data']['variations']['opts'][ $grp_id ] ) && '1' === $a['data']['variations']['opts'][ $grp_id ] ) {
										//radio buttons output
									} else {
										$variations_str .= sprintf( '<select class="pure-input-1 variations-input" data-asp-variations-group-id="%1$d" name="stripeVariations[%1$d][]">', $grp_id );
									}
									foreach ( $a['data']['variations']['names'][ $grp_id ] as $var_id => $name ) {
										if ( isset( $a['data']['variations']['opts'][ $grp_id ] ) && '1' === $a['data']['variations']['opts'][ $grp_id ] ) {
											$tpl = '<label class="pure-radio"><input class="variations-input" data-asp-variations-group-id="' . $grp_id . '" name="stripeVariations[' . $grp_id . '][]" type="radio" name="123" value="%d"' . ( 0 === $var_id ? 'checked' : '' ) . '> %s %s</label>';
										} else {
											$tpl = '<option value="%d">%s %s</option>';
										}
										$price_mod = $a['data']['variations']['prices'][ $grp_id ][ $var_id ];
										if ( ! empty( $price_mod ) ) {
											$fmt_price = AcceptStripePayments::formatted_price( abs( $price_mod ), $a['data']['currency'] );
											$price_mod = $price_mod < 0 ? ' - ' . $fmt_price : ' + ' . $fmt_price;
											$price_mod = '(' . $price_mod . ')';
										} else {
											$price_mod = '';
										}
										$variations_str .= sprintf( $tpl, $var_id, $name, $price_mod );
									}
									if ( isset( $a['data']['variations']['opts'][ $grp_id ] ) && '1' === $a['data']['variations']['opts'][ $grp_id ] ) {
										//radio buttons output
									} else {
										$variations_str .= '</select>';
									}
									$variations_str .= '</fieldset></div>';
									//                                      $variations_str .= '<div class="pure-u-md-1-24 variation"></div>';
								}
								$curr_var++;
							}
							echo '<div id="variations-cont" class="pure-u-1">';
							echo '<div class="pure-g">';
							echo $variations_str; //phpcs:ignore
							echo '</div>';
							echo '</div>';
						}
						?>
						<?php if ( $a['data']['coupons_enabled'] ) { ?>
						<div id="coupon-cont" class='pure-u-1'>
							<label for="coupon"><?php echo esc_html( apply_filters( 'asp_customize_text_msg', __( 'Coupon Code', 'stripe-payments' ), 'pp_coupon_code' ) ); ?></label>
							<div id="coupon-input-cont">
								<div style="position: relative;">
									<input class="pure-input-1" type="text" id="coupon-code" name="coupon-code">
									<button id="apply-coupon-btn" class="pure-button" type="button"><?php echo esc_html( __( 'Apply', 'stripe-payments' ) ); ?></button>
								</div>
								<div id="coupon-err" class="form-err" role="alert"></div>
							</div>
							<div id="coupon-res-cont" style="display: none;">
								<span id="coupon-info"></span>
								<button id="remove-coupon-btn" class="pure-button" type="button"><?php echo esc_html( __( 'Remove', 'stripe-payments' ) ); ?></button>
							</div>
						</div>
						<?php } ?>
						<?php if ( $a['data']['show_your_order'] ) { ?>
						<div id="your-order" class="pure-u-1">
							<fieldset>
								<legend><?php esc_html_e( 'Your order', 'stripe-payments' ); ?></legend>
								<table class="pure-table pure-table-horizontal" style="width: 100%;">
									<thead>
										<tr>
											<th style="width: 50%;"><?php esc_html_e( 'Item', 'stripe-payments' ); ?></th>
											<th><?php esc_html_e( 'Total', 'stripe-payments' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<tr id="order-item-line">
											<td><?php echo wp_kses( sprintf( '%s × <span id="order-quantity">%d</span>', $a['item_name'], $a['item']->get_quantity() ), array( 'span' => array( 'id' => array() ) ) ); ?></td>
											<td><span id="order-item-price"><?php echo esc_html( AcceptStripePayments::formatted_price( $this->item->get_price(), $this->item->get_currency() ) ); ?></span></td>
										</tr>
										<?php
											$items = $this->item->get_items();
										if ( ! empty( $items ) ) {
											foreach ( $items as $item ) {
												?>
										<tr>
											<td><?php echo esc_html( sprintf( '%s', $item['name'] ) ); ?></td>
											<td><span id="order-item-price"><?php echo esc_html( AcceptStripePayments::formatted_price( $item['price'], $this->item->get_currency() ) ); ?></span></td>
										</tr>
												<?php
											}
										}
										if ( $a['data']['tax'] ) {
											$tax_str        = apply_filters( 'asp_customize_text_msg', __( 'Tax', 'stripe-payments' ), 'tax_str' );
											$tax_amount_str = AcceptStripePayments::formatted_price( $a['item']->get_tax_amount(), $this->item->get_currency() );
											$out            = sprintf( '<tr><td>%s (%s%%)</td><td><span id="order-tax">%s</span></td></tr>', $tax_str, $a['item']->get_tax(), $tax_amount_str );
											echo $out; //phpcs:ignore
										}
										if ( $a['data']['shipping'] ) {
											$ship_str        = apply_filters( 'asp_customize_text_msg', __( 'Shipping', 'stripe-payments' ), 'shipping_str' );
											$ship_amount_str = AcceptStripePayments::formatted_price( $a['item']->get_shipping(), $this->item->get_currency() );
											$out             = sprintf( '<tr><td>%s</td><td><span id="shipping">%s</span></td></tr>', $ship_str, $ship_amount_str );
											echo $out; //phpcs:ignore
										}
										?>
										<tr>
											<td><strong><?php esc_html_e( 'Total', 'stripe-payments' ); ?>:</strong></td>
											<td><span id="order-total"><?php echo esc_html( AcceptStripePayments::formatted_price( $this->item->get_total(), $this->item->get_currency() ) ); ?></span></td>
										</tr>
									</tbody>
								</table>
							</fieldset>
						</div>
						<?php } ?>
						<?php if ( count( $a['data']['payment_methods'] ) > 1 ) { ?>
						<div id="pm-select-cont" class="pure-u-1">
							<fieldset>
								<legend><?php esc_html_e( 'Select payment method', 'stripe-payments' ); ?></legend>
								<?php
										$out = '';
								foreach ( $a['data']['payment_methods'] as $pm ) {
									$img = '';
									if ( isset( $pm['img'] ) ) {
										$img = sprintf(
											' <img title="%1$s" alt="%1$s" height="%2$d" width="%3$d" src="%4$s">',
											$pm['title'],
											isset( $pm['img_height'] ) ? $pm['img_height'] : 32,
											isset( $pm['img_width'] ) ? $pm['img_width'] : 32,
											$pm['img']
										);
									}
									$out .= sprintf( '<div class="pure-u-1 pure-u-md-1-3"><label class="pure-radio"><input name="pm" class="pm-select-btn" type="radio"%s value="%s" data-pm-id="%s">%s%s %s</label></div>', empty( $out ) ? ' checked' : '', $pm['id'], $pm['id'], isset( $pm['before_title'] ) ? $pm['before_title'] : '', ! empty( $img ) ? $img : '', isset( $pm['hide_title'] ) ? '' : $pm['title'] );
								}
									echo $out; //phpcs:ignore
								?>
							</fieldset>
						</div>
							<?php
						}
							do_action( 'asp_ng_pp_output_before_address', $a['data'] );
						?>
						<div id="name-email-outer-cont" class="pure-u-1">
							<div class="pure-g">
								<fieldset id="name-email-cont" style="width: 100%;">
									<div class="pure-u-1 pure-u-md-11-24">
										<label for="billing_name"><?php echo esc_html( apply_filters( 'asp_customize_text_msg', _x( 'Name', 'Customer name', 'stripe-payments' ), 'pp_billing_name' ) ); ?></label>
										<div style="position: relative;">
											<svg id="i-user" class="icon input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="32" height="32" fill="none" stroke="currentcolor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2">
												<path d="M22 11 C22 16 19 20 16 20 13 20 10 16 10 11 10 6 12 3 16 3 20 3 22 6 22 11 Z M4 30 L28 30 C28 21 22 20 16 20 10 20 4 21 4 30 Z" />
											</svg>
											<input class="pure-input-1 has-icon" type="text" id="billing-name" name="billing_name" value="<?php echo esc_attr( $a['data']['customer_name'] ); ?>" required>
										</div>
									</div>
									<div class="pure-u-md-1-24"></div>
									<div class="pure-u-1 pure-u-md-12-24">
										<label for="email"><?php echo esc_html( apply_filters( 'asp_customize_text_msg', __( 'Email', 'stripe-payments' ), 'pp_email' ) ); ?></label>
										<div style="position: relative;">
											<svg id="i-mail" class="icon input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="32" height="32" fill="none" stroke="currentcolor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2">
												<path d="M2 26 L30 26 30 6 2 6 Z M2 6 L16 16 30 6" />
											</svg>
											<input class="pure-input-1 has-icon" type="email" id="email" name="email" value="<?php echo esc_attr( $a['data']['customer_email'] ); ?>" required>
										</div>
									</div>
								</fieldset>
							</div>
						</div>
						<?php if ( $a['data']['billing_address'] || $a['data']['shipping_address'] ) { ?>
						<div id="addr-cont" class="pure-g">
							<?php } ?>
							<?php if ( $a['data']['billing_address'] && $a['data']['shipping_address'] ) { ?>
							<div class="pure-u-1">
								<label class="pure-checkbox">
									<input type="checkbox" id="same-bill-ship-addr" name="same-bill-ship-addr" checked>
									<?php echo esc_html( __( 'Same billing and shipping info', 'stripe-payments' ) ); ?>
								</label>
							</div>
							<?php } ?>
							<?php if ( $a['data']['billing_address'] ) { ?>
							<div id="billing-addr-cont">
								<div class="half-inner-left">
									<fieldset>
										<div class="pure-u-1">
											<legend><?php esc_html_e( 'Billing info', 'stripe-payments' ); ?></legend>
										</div>
										<div class="pure-u-1 pure-u-md-14-24 baddr-toggle" style="position: relative;">
											<label for="address"><?php esc_html_e( 'Address', 'stripe-payments' ); ?></label>
											<div style="position: relative;">
												<svg id="i-location" class="icon input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="32" height="32" fill="none" stroke="currentcolor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2">
													<circle cx="16" cy="11" r="4" />
													<path d="M24 15 C21 22 16 30 16 30 16 30 11 22 8 15 5 8 10 2 16 2 22 2 27 8 24 15 Z" />
												</svg>
											</div>
											<input class="pure-input-1 has-icon" type="text" id="address" name="address" required>
										</div>
										<div class="pure-u-md-1-24 baddr-hide"></div>
										<div class="pure-u-1 pure-u-md-9-24 baddr-toggle" style="position: relative;">
											<label for="city"><?php esc_html_e( 'City', 'stripe-payments' ); ?></label>
											<input class="pure-input-1" type="text" id="city" name="city" required>
										</div>
										<div class="pure-u-1 pure-u-md-<?php echo $a['hide_state_field'] ? '14' : '10'; ?>-24 baddr-toggle" style="position: relative;">
											<label for="country"><?php esc_html_e( 'Country', 'stripe-payments' ); ?></label>
											<select class="pure-input-1" name="country" id="country" required>
                                                <?php echo ASP_Utils::get_countries_opts($a['data']['customer_default_country']); //phpcs:ignore ?>
											</select>
										</div>
										<?php if ( ! $a['hide_state_field'] ) { ?>
										<div class="pure-u-md-1-24 baddr-hide"></div>
										<div class="pure-u-1 pure-u-md-6-24 baddr-toggle" style="position: relative;">
											<label for="state"><?php esc_html_e( 'State', 'stripe-payments' ); ?></label>
											<input class="pure-input-1" type="text" id="state" name="state">
										</div>
										<?php } ?>
										<div class="pure-u-md-1-24 baddr-hide"></div>
										<div class="pure-u-1 pure-u-md-<?php echo $a['hide_state_field'] ? '9' : '6'; ?>-24 baddr-toggle">
											<label for="postcode"><?php esc_html_e( 'Postcode', 'stripe-payments' ); ?></label>
											<input class="pure-u-1" type="text" name="postcode" id="postcode">
										</div>
									</fieldset>
								</div>
							</div>
							<?php } ?>
							<?php if ( $a['data']['shipping_address'] ) { ?>
							<div id="shipping-addr-cont" class="half-width" style="display: none;">
								<div class="half-inner-right">
									<fieldset>
										<div class="pure-u-1">
											<legend>
												<?php esc_html_e( 'Shipping info', 'stripe-payments' ); ?>
											</legend>
										</div>
										<div class="pure-u-1" style="position: relative;">
											<label for="shipping_address"><?php esc_html_e( 'Address', 'stripe-payments' ); ?></label>
											<div style="position: relative;">
												<svg id="i-location" class="icon input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="32" height="32" fill="none" stroke="currentcolor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2">
													<circle cx="16" cy="11" r="4" />
													<path d="M24 15 C21 22 16 30 16 30 16 30 11 22 8 15 5 8 10 2 16 2 22 2 27 8 24 15 Z" />
												</svg>
												<input class="pure-input-1 has-icon saddr-required" type="text" id="shipping_address" name="shipping_address">
											</div>
										</div>
										<div class="pure-u-1" style="position: relative;">
											<label for="shipping_city"><?php esc_html_e( 'City', 'stripe-payments' ); ?></label>
											<input class="pure-input-1 saddr-required" type="text" id="shipping_city" name="shipping_city">
										</div>
										<div class="pure-u-1" style="position: relative;">
											<label for="shipping_country"><?php esc_html_e( 'Country', 'stripe-payments' ); ?></label>
											<select class="pure-input-1 saddr-required" name="shipping_country" id="shipping_country">
                                                <?php echo ASP_Utils::get_countries_opts($a['data']['customer_default_country']); //phpcs:ignore ?>
											</select>
										</div>
										<?php if ( ! $a['hide_state_field'] ) { ?>
										<div class="pure-u-1">
											<label for="shipping_state"><?php esc_html_e( 'State', 'stripe-payments' ); ?></label>
											<input class="pure-u-1" type="text" name="shipping_state" id="shipping_state">
										</div>
										<?php } ?>
										<div class="pure-u-1">
											<label for="shipping_postcode"><?php esc_html_e( 'Postcode', 'stripe-payments' ); ?></label>
											<input class="pure-u-1" type="text" name="shipping_postcode" id="shipping_postcode">
										</div>
									</fieldset>
								</div>
							</div>
							<?php } ?>
							<?php if ( $a['data']['billing_address'] || $a['data']['shipping_address'] ) { ?>
						</div>
						<?php } ?>
						<div id="card-cont" data-pm-name="def" class="pure-u-1">
							<label for="card-element"><?php echo esc_html( apply_filters( 'asp_customize_text_msg', __( 'Credit or debit card', 'stripe-payments' ), 'pp_credit_or_debit_card' ) ); ?></label>
							<div id="card-element">
							</div>
							<div id="card-errors" class="form-err" role="alert"></div>
						</div>
						<?php if ( isset( $a['tos'] ) && $a['tos'] ) { ?>
						<div id="tos-cont" class="pure-u-1">
							<label for="tos" class="pure-checkbox">
								<input id="tos" type="checkbox" value="1">
                                <?php echo html_entity_decode( $a['tos_text'] ); //phpcs:ignore?>
							</label>
							<div id="tos-error" class="form-err" role="alert"></div>
						</div>
						<?php } ?>
						<?php
							$out = apply_filters( 'asp_ng_pp_output_before_buttons', '', $a['data'] );
							echo $out; //phpcs:ignore
						?>
						<div id="buttons-container">
							<div class="pure-u-5-5" style="position: relative;">
								<div id="submit-btn-cont" data-pm-name="def" class="pure-u-5-5 centered">
									<button type="submit" id="submit-btn" class="pure-button pure-button-primary" disabled><?php echo esc_html( $a['pay_btn_text'] ); ?></button>
								</div>
								<?php
									$out = apply_filters( 'asp_ng_pp_after_button', '', $a['data'], '' );
									echo $out; //phpcs:ignore
								?>
							</div>
						</div>
						<input type="hidden" id="payment-intent" name="payment_intent" value="">
						<input type="hidden" id="btn-uniq-id" name="btn_uniq_id" value="<?php echo ! empty( $a['btn_uniq_id'] ) ? esc_attr( $a['btn_uniq_id'] ) : ''; ?>">
						<input type="hidden" id="product-id" name="product_id" value="<?php echo esc_attr( $a['prod_id'] ); ?>">
						<input type="hidden" name="process_ipn" value="1">
						<input type="hidden" name="is_live" value="<?php echo $a['is_live'] ? '1' : '0'; ?>">
						<?php if ( $a['data']['url'] ) { ?>
						<input type="hidden" name="item_url" value="<?php echo esc_attr( $a['data']['url'] ); ?>">
						<?php } ?>
						<input type="hidden" value="<?php echo ! empty( $a['thankyou_page'] ) ? esc_attr( base64_encode( $a['thankyou_page'] ) ) : ''; ?>" name="thankyou_page_url" id="thankyou_page_url">
						<?php if ( ! empty( $a['data']['create_token'] ) ) { ?>
						<input type="hidden" value="1" name="create_token">
						<input type="hidden" value="" id="sub_id" name="sub_id">
						<?php } ?>
					</form>
				</div>
			</div>
		</div>
	</div>
	<span id="threeds-iframe-close-btn" title="<?php esc_html_e( 'Close', 'stripe-payments' ); ?>" tabindex="0">
		<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24">
			<path d="M14.59 8L12 10.59 9.41 8 8 9.41 10.59 12 8 14.59 9.41 16 12 13.41 14.59 16 16 14.59 13.41 12 16 9.41 14.59 8zM12 2C6.47 2 2 6.47 2 12s4.47 10 10 10 10-4.47 10-10S17.53 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z" />
		</svg>
	</span>
	<?php

	foreach ( $a['styles'] as $style ) {
		if ( ! $style['footer'] ) {
			printf( '<link rel="stylesheet" href="%s">' . "\r\n", $style['src'] ); //phpcs:ignore
		}
	}
	foreach ( $a['scripts'] as $script ) {
		if ( ! $script['footer'] ) {
			printf( '<script src="%s"></script>' . "\r\n",  $script['src'] ); //phpcs:ignore
		}
	}

	foreach ( $a['scripts'] as $script ) {
		if ( $script['footer'] ) {
			printf( '<script src="%s"></script>' . "\r\n",  $script['src'] ); //phpcs:ignore
		}
	}

	foreach ( $a['styles'] as $style ) {
		if ( $style['footer'] ) {
			printf( '<link rel="stylesheet" href="%s">' . "\r\n", $style['src'] ); //phpcs:ignore
		}
	}

	//fire action to output additional data to payment popup before closing <body> tag
	do_action( 'asp_ng_pp_output_before_closing_body', $a );
	?>
	</body>

</html>
