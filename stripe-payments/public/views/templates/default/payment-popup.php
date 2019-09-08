<!DOCTYPE html>
<html>

<head>
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta charset="utf-8">
	<title><?php echo esc_html( $a['page_title'] ); ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php
	foreach ( $a['styles'] as $style ) {
		if ( ! $style['footer'] ) {
			printf( '<link rel="stylesheet" href="%s">' . "\r\n", esc_url( $style['src'] ) ); //phpcs:ignore
		}
	}
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

	foreach ( $a['scripts'] as $script ) {
		if ( ! $script['footer'] ) {
			printf( '<script src="%s"></script>' . "\r\n", esc_url( $script['src'] ) ); //phpcs:ignore
		}
	}

	$icon = get_site_icon_url();
	if ( $icon ) {
		printf( '<link rel="icon" href="%s" />' . "\r\n", esc_url( $icon ) );
	}
	?>
<!--[if lt IE 9]>
<script src="//cdnjs.cloudflare.com/ajax/libs/html5shiv/3.7.2/html5shiv.min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/respond.js/1.4.2/respond.min.js"></script>
<![endif]-->
</head>

<body>
	<div id="Aligner" class="Aligner">
		<?php if ( ! $a['data']['is_live'] ) { ?>
			<a href="https://stripe.com/docs/testing#cards" target="_blank" id="test-mode"><?php esc_html_e( 'TEST MODE', 'stripe-payments' ); ?></a>
		<?php } ?>
		<div class="Aligner-item">
		<div id="smoke-screen"></div>
			<div id="modal-header">
				<?php if ( $a['data']['item_logo'] ) { ?>
					<div id="item-logo-cont">
						<img id="item-logo" src="<?php echo esc_url( $a['data']['item_logo'] ); ?>">
					</div>
				<?php } ?>
				<span id="modal-close-btn" title="<?php esc_html_e( 'Close', 'stripe-payments' ); ?>"><img src="<?php echo esc_url( $a['plugin_url'] ); ?>/public/views/templates/default/close-btn.png"></span>
				<div id="item-name"><?php echo esc_html( $a['item_name'] ); ?></div>
				<div id="item-descr"><?php echo esc_html( $a['data']['descr'] ); ?></div>
			</div>
			<div id="modal-body">
				<div class="pure-g">
					<div class="pure-u-1">
						<div id="global-error" <?php echo isset( $a['fatal_error'] ) ? 'style="display: block"' : ''; ?>>
							<?php echo isset( $a['fatal_error'] ) ? esc_html( $a['fatal_error'] ) : ''; ?> 
						</div>
					</div>
					<div id="form-container" class="pure-u-1" <?php	echo isset( $a['fatal_error'] ) ? 'style="display: none;"' : ''; ?>>
						<form method="post" id="payment-form" class="pure-form pure-form-stacked">
							<?php if ( $a['data']['amount_variable'] ) { ?>
								<label for="amount"><?php esc_html_e( 'Enter amount', 'stripe-payments' ); ?></label>
								<input class="pure-input-1" id="amount" name="amount" inputmode="decimal" required>
								<div id="amount-error" class="form-err" role="alert"></div>
							<?php } ?>
							<?php
							if ( $a['data']['currency_variable'] ) {
								?>
								<label for="quantity"><?php esc_html_e( 'Select currency', 'stripe-payments' ); ?></label>
								<select class="pure-input-1" id="currency" name="currency">
								<?php
								//let's add a box where user can select currency
								$output   = '';
								$curr_arr = ASP_Utils::get_currencies();
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
							<?php } ?>
							<?php if ( $a['data']['custom_quantity'] ) { ?>
								<label for="quantity"><?php esc_html_e( 'Enter quantity', 'stripe-payments' ); ?></label>
								<input type="number" min="1" class="pure-input-1" id="quantity" name="quantity" inputmode="numeric" value="<?php echo esc_attr( $a['data']['quantity'] ); ?>" required>
								<div id="quantity-error" class="form-err" role="alert"></div>
							<?php } ?>
							<?php if ( isset( $a['custom_fields'] ) ) { ?>
								<div class="pure-u-1">
									<?php echo $a['custom_fields']; //phpcs:ignore ?>
								</div>
							<?php } ?>
							<div class="pure-u-1">
		<?php
		if ( ! empty( $a['data']['variations'] ) ) {
			$variations_str = '';
			foreach ( $a['data']['variations']['groups'] as $grp_id => $group ) {
				if ( ! empty( $a['data']['variations']['names'] ) ) {
					$variations_str .= '<div class="pure-u-1 pure-u-md-11-24">';
					$variations_str .= '<label>' . $group . '</label>';
					if ( isset( $a['data']['variations']['opts'][ $grp_id ] ) && $a['data']['variations']['opts'][ $grp_id ] === '1' ) {
						//radio buttons output
					} else {
						$variations_str .= sprintf( '<select class="pure-input-1 variations-input" data-asp-variations-group-id="%1$d" name="stripeVariations[%1$d][]">', $grp_id );
					}
					foreach ( $a['data']['variations']['names'][ $grp_id ] as $var_id => $name ) {
						if ( isset( $a['data']['variations']['opts'][ $grp_id ] ) && $a['data']['variations']['opts'][ $grp_id ] === '1' ) {
							$tpl = '<label class="pure-radio"><input class="variations-input" data-asp-variations-group-id="' . $grp_id . '" name="stripeVariations[' . $grp_id . '][]" type="radio" name="123" value="%d"' . ( $var_id === 0 ? 'checked' : '' ) . '> %s %s</label>';
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
					if ( isset( $a['data']['variations']['opts'][ $grp_id ] ) && $a['data']['variations']['opts'][ $grp_id ] === '1' ) {
						//radio buttons output
					} else {
						$variations_str .= '</select>';
					}
					$variations_str .= '</div>';
					$variations_str .= '<div class="pure-u-1-24"></div>';
				}
			}
			echo $variations_str; //phpcs:ignore
		}
		?>
								<?php if ( $a['data']['coupons_enabled'] ) { ?>
								<div class='pure-u-1'>
									<label for="coupon"><?php echo esc_html( __( 'Coupon Code', 'stripe-payments' ) ); ?></label>
									<div id="coupon-input-cont">
										<div style="position: relative;">
										<input class="pure-input-1" type="text" id="coupon-code" name="coupon-code">
										<span id="coupon-spinner" class="small-spinner coupon-spinner"></span>
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
									<div class="pure-g">
										<div class="pure-u-1 pure-u-md-11-24" style="position: relative;">
											<label for="billing_name"><?php esc_html_e( 'Name', 'stripe-payments' ); ?></label>
											<svg id="i-user" class="icon input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="32" height="32" fill="none" stroke="currentcolor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2">
												<path d="M22 11 C22 16 19 20 16 20 13 20 10 16 10 11 10 6 12 3 16 3 20 3 22 6 22 11 Z M4 30 L28 30 C28 21 22 20 16 20 10 20 4 21 4 30 Z" />
											</svg>
											<input class="pure-input-1 has-icon" type="text" id="billing-name" name="billing_name" value="<?php echo esc_attr( $a['data']['customer_name'] ); ?>" required>
										</div>
										<div class="pure-u-md-1-24"></div>
										<div class="pure-u-1 pure-u-md-12-24" style="position: relative;">
											<label for="email"><?php esc_html_e( 'Email', 'stripe-payments' ); ?></label>
											<svg id="i-mail" class="icon input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="32" height="32" fill="none" stroke="currentcolor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2">
												<path d="M2 26 L30 26 30 6 2 6 Z M2 6 L16 16 30 6" />
											</svg>
											<input class="pure-input-1 has-icon" type="email" id="email" name="email" value="<?php echo esc_attr( $a['data']['customer_email'] ); ?>" required>
										</div>
										<?php if ( $a['data']['billing_address'] && $a['data']['shipping_address'] ) { ?>
											<div class="pure-u-1">
												<label class="pure-checkbox"><input type="checkbox" id="same-bill-ship-addr" name="same-bill-ship-addr" checked> Same billing and shipping info</label>
											</div>
										<?php } ?>
										<?php if ( $a['data']['billing_address'] ) { ?>
											<div id="billing-addr-cont">
												<div class="half-inner-left">
												<fieldset>
											<div class="pure-u-1"><legend><?php esc_html_e( 'Billing info', 'stripe-payments' ); ?></legend></div>
										<div class="pure-u-1 pure-u-md-14-24 baddr-toggle" style="position: relative;">
											<label for="address"><?php esc_html_e( 'Address', 'stripe-payments' ); ?></label>
											<svg id="i-location" class="icon input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="32" height="32" fill="none" stroke="currentcolor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2">
												<circle cx="16" cy="11" r="4" />
												<path d="M24 15 C21 22 16 30 16 30 16 30 11 22 8 15 5 8 10 2 16 2 22 2 27 8 24 15 Z" />
											</svg>
											<input class="pure-input-1 has-icon" type="text" id="address" name="address" required>
										</div>
										<div class="pure-u-md-1-24 baddr-hide"></div>
										<div class="pure-u-1 pure-u-md-9-24 baddr-toggle" style="position: relative;">
											<label for="city"><?php esc_html_e( 'City', 'stripe-payments' ); ?></label>
											<input class="pure-input-1" type="text" id="city" name="city" required>
										</div>
										<div class="pure-u-1 pure-u-md-14-24 baddr-toggle" style="position: relative;">
											<label for="country"><?php esc_html_e( 'Country', 'stripe-payments' ); ?></label>
											<select class="pure-input-1" name="country" id="country" required>
												<?php echo ASP_Utils::get_countries_opts($a['data']['customer_default_country']); //phpcs:ignore ?>
											</select>
										</div>
										<div class="pure-u-md-1-24 baddr-hide"></div>
										<div class="pure-u-1 pure-u-md-9-24 baddr-toggle">
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
											<div class="pure-u-1"><legend><?php esc_html_e( 'Shipping info', 'stripe-payments' ); ?></legend></div>
										<div class="pure-u-1" style="position: relative;">
											<label for="shipping_address"><?php esc_html_e( 'Address', 'stripe-payments' ); ?></label>
											<svg id="i-location" class="icon input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="32" height="32" fill="none" stroke="currentcolor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2">
												<circle cx="16" cy="11" r="4" />
												<path d="M24 15 C21 22 16 30 16 30 16 30 11 22 8 15 5 8 10 2 16 2 22 2 27 8 24 15 Z" />
											</svg>
											<input class="pure-input-1 has-icon saddr-required" type="text" id="shipping_address" name="shipping_address">
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
										<div class="pure-u-1">
											<label for="shipping_postcode"><?php esc_html_e( 'Postcode', 'stripe-payments' ); ?></label>
											<input class="pure-u-1" type="text" name="shipping_postcode" id="shipping_postcode">
										</div>
										</fieldset>

										</div>
										</div>
										<?php } ?>
										<div class="pure-u-1">
									<label for="card-element"><?php esc_html_e( 'Credit or debit card', 'stripe-payments' ); ?></label>
									<div id="card-element">
									</div>
									<div id="card-errors" class="form-err" role="alert"></div>
										</div>

								</div>
								<?php if ( isset( $a['tos'] ) && $a['tos'] ) { ?>
								<div class="pure-u-1">
									<label for="tos" class="pure-checkbox">
										<input id="tos" type="checkbox" value="1"> <?php echo html_entity_decode( $a['tos_text'] ); //phpcs:ignore?>
									</label>
									<div id="tos-error" class="form-err" role="alert"></div>
								</div>
								<?php } ?>
							</div>
							<div class="pure-u-5-5 centered">
								<div id="submit-btn-cont">
									<button type="submit" id="submit-btn" class="pure-button pure-button-primary" disabled><?php echo esc_html( $a['pay_btn_text'] ); ?></button>
									<span id="btn-spinner" class="small-spinner"></span>
								</div>
							</div>
							<div class="pure-u-1"<?php echo ( ! $a['data']['tax'] && ! $a['data']['shipping'] ) ? ' style="display: none;"' : ''; ?>>
								<div id="tax-shipping-cont" class="pure-u-5-5 centered">
									<?php
									$out = array();
									if ( $a['data']['tax'] ) {
										$tax_str = apply_filters( 'asp_customize_text_msg', __( 'Tax', 'stripe-payments' ), 'tax_str' );
										$out[]   = sprintf( '<span id="tax-cont">%s %s%%</span>', lcfirst( $tax_str ), $a['data']['tax'] );
									}
									if ( $a['data']['shipping'] ) {
										$ship_str = apply_filters( 'asp_customize_text_msg', __( 'Shipping', 'stripe-payments' ), 'shipping_str' );
										$out[]    = sprintf( '<span id="shipping-cont">%s %s</span>', lcfirst( $ship_str ), AcceptStripePayments::formatted_price( $a['data']['shipping'], $a['data']['currency'], true ) );
									}
									if ( $out ) {
										$out_str = '';
										foreach ( $out as $text ) {
											$out_str .= $text . ', ';
										}
										$out_str = rtrim( $out_str, ', ' );
										$out_str = __( 'Includes', 'stripe-payments' ) . ' ' . $out_str;
										echo $out_str; //phpcs:ignore
									}
									?>
								</div>
							</div>
							<input type="hidden" id="payment-intent" name="payment_intent" value="">
							<input type="hidden" id="product-id" name="product_id" value="<?php echo esc_attr( $a['prod_id'] ); ?>">
							<input type="hidden" name="process_ipn" value="1">
							<input type="hidden" name="is_live" value="<?php echo $a['is_live'] ? '1' : '0'; ?>">
							<?php if ( $a['data']['url'] ) { ?>
							<input type="hidden" name="item_url" value="<?php echo esc_attr( $a['data']['url'] ); ?>">
							<?php } ?>
							<?php if ( ! empty( $a['thankyou_page'] ) ) { ?>
							<input type="hidden" value="<?php echo esc_attr( base64_encode( $a['thankyou_page'] ) ); ?>" name="thankyou_page_url">
							<?php } ?>
							<?php if ( ! empty( $a['data']['create_token'] ) ) { ?>
							<input type="hidden" value="1" name="create_token">
							<input type="hidden" value="" id="sub_id" name="sub_id">
							<?php } ?>
						</form>
					</div>
				</div>
			</div>
		</div>
	</div>
</body>
<script src="https://js.stripe.com/v3/"></script>
<?php
foreach ( $a['scripts'] as $script ) {
	if ( $script['footer'] ) {
		printf( '<script src="%s"></script>' . "\r\n", esc_url( $script['src'] ) ); //phpcs:ignore
	}
}

foreach ( $a['styles'] as $style ) {
	if ( $style['footer'] ) {
		printf( '<link rel="stylesheet" href="%s">' . "\r\n", esc_url( $style['src'] ) ); //phpcs:ignore
	}
}
?>

</html>
