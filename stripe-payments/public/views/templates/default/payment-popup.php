<!DOCTYPE html>
<html>

<head>
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta charset="utf-8">
	<title><?php echo $a['page_title']; ?></title>
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<?php
	foreach ( $a['styles'] as $style ) {
		if ( ! $style['footer'] ) {
			printf( '<link rel="stylesheet" href="%s">' . "\r\n", $style['src'] );
		}
	}
	foreach ( $a['vars'] as $var => $data ) {
		printf(
			"<script type='text/javascript'>
            /* <![CDATA[ */
            var %s = %s;
            /* ]]> */
            </script>\r\n",
			$var,
			wp_json_encode( $data )
		);
	}

	foreach ( $a['scripts'] as $script ) {
		if ( ! $script['footer'] ) {
			printf( '<script src="%s"></script>' . "\r\n", $script['src'] );
		}
	}

	$icon = get_site_icon_url();
	if ( $icon ) {
		printf( '<link rel="icon" href="%s" />' . "\r\n", esc_attr( $icon ) );
	}
	?>
<!--[if lt IE 9]>
<script src="//cdnjs.cloudflare.com/ajax/libs/html5shiv/3.7.2/html5shiv.min.js"></script>
<script src="//cdnjs.cloudflare.com/ajax/libs/respond.js/1.4.2/respond.min.js"></script>
<![endif]-->
</head>

<body>
	<div id="Aligner" class="Aligner">
	<a href="https://stripe.com/docs/testing#cards" target="_blank" id="test-mode">TEST MODE</a>
		<div class="Aligner-item">
			<div id="modal-header">
				<span id="modal-close-btn" title="<?php _e( 'Close', 'stripe-payments' ); ?>"><img src="<?php echo $a['plugin_url']; ?>/public/views/templates/default/close-btn.png"></span>
				<div id="item-name"><?php echo $a['item_name']; ?></div>
			</div>
			<div id="modal-body">
				<div class="pure-g">
					<div class="pure-u-1">
						<div id="global-error" 
						<?php
						if ( isset( $a['fatal_error'] ) ) {
							echo 'style="display: block"';
						}
						?>
												>
							<?php
							if ( isset( $a['fatal_error'] ) ) {
								echo $a['fatal_error'];
							}
							?>
						</div>
					</div>
					<div id="form-container" class="pure-u-1" 
					<?php
					if ( isset( $a['fatal_error'] ) ) {
						echo ' style="display: none;"';
					}
					?>
																>
						<form method="post" id="payment-form" class="pure-form pure-form-stacked">
							<?php if ( $a['amount_variable'] ) { ?>
								<label for="amount">Enter Amount</label>
								<input class="pure-input-1" id="amount" name="amount" required>
								<div id="amount-error" class="form-err" role="alert"></div>
							<?php } ?>
							<?php
							if ( $a['currency_variable'] ) {
							}
							?>
							<?php if ( $a['data']['custom_quantity'] ) { ?>
								<label for="quantity">Enter Quantity</label>
								<input type="number" min="1" class="pure-input-1" id="quantity" name="quantity" value="<?php echo esc_attr( $a['data']['quantity'] ); ?>" required>
								<div id="quantity-error" class="form-err" role="alert"></div>
							<?php } ?>
							<?php if ( isset( $a['custom_fields'] ) ) { ?>
								<div class="pure-u-1">
									<?php echo $a['custom_fields']; ?>
								</div>
							<?php } ?>
							<div class="pure-u-1">
								<fieldset>
									<div class="pure-g">
										<div class="pure-u-1 pure-u-md-11-24" style="position: relative;">
											<label for="billing_name">Name</label>
											<svg id="i-user" class="icon input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="32" height="32" fill="none" stroke="currentcolor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2">
												<path d="M22 11 C22 16 19 20 16 20 13 20 10 16 10 11 10 6 12 3 16 3 20 3 22 6 22 11 Z M4 30 L28 30 C28 21 22 20 16 20 10 20 4 21 4 30 Z" />
											</svg>
											<input class="pure-input-1 has-icon" type="text" id="billing-name" name="billing_name" required>
										</div>
										<div class="pure-u-md-2-24"></div>
										<div class="pure-u-1 pure-u-md-11-24" style="position: relative;">
											<label for="email">Email</label>
											<svg id="i-mail" class="icon input-icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32" width="32" height="32" fill="none" stroke="currentcolor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2">
												<path d="M2 26 L30 26 30 6 2 6 Z M2 6 L16 16 30 6" />
											</svg>
											<input class="pure-input-1 has-icon" type="email" id="email" name="email" required>
										</div>
									</div>
									<label for="card-element">Credit or debit card</label>
									<div id="card-element">
									</div>
									<div id="card-errors" class="form-err" role="alert"></div>
								</fieldset>
							</div>
							<div class="pure-u-5-5 centered">
								<div id="submit-btn-cont">
									<button type="submit" id="submit-btn" class="pure-button pure-button-primary" disabled><?php echo $a['pay_btn_text']; ?></button>
									<span id="btn-spinner" class="small-spinner"></span>
								</div>
							</div>
							<input type="hidden" id="payment-intent" name="payment_intent" value="">
							<input type="hidden" id="product-id" name="product_id" value="<?php echo $a['prod_id']; ?>">
							<input type="hidden" name="process_ipn" value="1">
							<input type="hidden" name="is_live" value="<?php echo $a['is_live'] ? 'true' : 'false'; ?>">
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
		printf( '<script src="%s"></script>' . "\r\n", $script['src'] );
	}
}

foreach ( $a['styles'] as $style ) {
	if ( $style['footer'] ) {
		printf( '<link rel="stylesheet" href="%s">' . "\r\n", $style['src'] );
	}
}
?>

</html>
