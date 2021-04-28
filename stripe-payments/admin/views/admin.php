<?php
/**
 * Represents the view for the administration dashboard.
 *
 * This includes the header, options, and other information that should provide
 * The User Interface to the end user.
 */
if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( 'You do not have permission to access this settings page.' );
}

if ( $_GET['page'] == 'stripe-payments-settings' ) {

	$tab = get_transient( 'wp-asp-urlHash' );

	if ( $tab ) {
		delete_transient( 'wp-asp-urlHash' );
	}
	do_action( 'asp-settings-page-after-styles' );
	?>
	<div class="wrap">

		<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>

	<?php settings_errors(); ?>

		<form method="post" action="options.php">
		<input type="hidden" id="wp-asp-urlHash" name="wp-asp-urlHash" value="">

		<?php settings_fields( 'AcceptStripePayments-settings-group' ); ?>

		<h2 class="nav-tab-wrapper">
			<a href="#general" data-tab-name="general" class="nav-tab"><?php echo __( 'General Settings', 'stripe-payments' ); ?></a>
			<a href="#email" data-tab-name="email" class="nav-tab"><?php echo __( 'Email Settings', 'stripe-payments' ); ?></a>
			<a href="#advanced" data-tab-name="advanced" class="nav-tab"><?php echo __( 'Advanced Settings', 'stripe-payments' ); ?></a>
		<?php
		do_action( 'asp-settings-page-after-tabs-menu' );
		?>
		</h2>
		<div class="asp-settings-spinner-container">
			<div class="asp-settings-spinner">Loading...</div>
		</div>
		<div class="wp-asp-settings-cont">
			<div class="wp-asp-settings-grid wp-asp-tabs">
			<div class="wp-asp-tab-container" data-tab-name="general">
			<?php //do_settings_sections( 'accept_stripe_payment-docs' ); ?>
			<?php do_settings_sections( 'accept_stripe_payment' ); ?>
			</div>
			<div class="wp-asp-tab-container" data-tab-name="email">
			<?php do_settings_sections( 'accept_stripe_payment-email' ); ?>
			</div>
			<div class="wp-asp-tab-container" data-tab-name="advanced">
			<?php do_settings_sections( 'accept_stripe_payment-advanced' ); ?>
			</div>
			<?php
			do_action( 'asp-settings-page-after-tabs' );
			?>
			</div>
			<div id="poststuff" class="wp-asp-settings-grid wp-asp-settings-sidebar-cont">
			<div class="postbox" style="min-width: inherit;">
				<h3 class="hndle"><label for="title"><?php echo __( 'Plugin Documentation', 'stripe-payments' ); ?></label></h3>
				<div class="inside">
				<?php
				// translators: %s is link to documentation page
				echo sprintf( __( 'Please read the <a target="_blank" href="%s">Stripe Payments</a> plugin setup instructions and tutorials to learn how to configure and use it.', 'stripe-payments' ), 'https://s-plugins.com/stripe-payments-plugin-tutorials/' );
				?>
				</div>
			</div>
			<div class="postbox yellowish" style="min-width: inherit;">
				<h3 class="hndle"><label for="title"><?php echo __( 'Add-ons', 'stripe-payments' ); ?></label></h3>
				<div class="inside">
				<?php
				// translators: %s is link to addons page
				echo sprintf( __( 'Want additional functionality like Subscriptions, Apple Pay support or MailChimp integration? Check out our <a target="_blank" href="%s">Add-Ons!</a>', 'stripe-payments' ), 'edit.php?post_type=' . ASPMain::$products_slug . '&page=stripe-payments-addons' );
				?>
				</div>
			</div>
			<div class="postbox" style="min-width: inherit;">
				<h3 class="hndle"><label for="title"><?php echo __( 'Need Something for PayPal?', 'stripe-payments' ); ?></label></h3>
				<div class="inside">
				<?php _ex( 'If you need a lightweight plugin to sell your products and services using PayPal then check out our', 'Followed by a link to Express Checkout and eStore plugins', 'stripe-payments' ); ?>
                                    <a target="_blank" href="https://wordpress.org/plugins/wp-express-checkout/">Express Checkout Plugin</a> or
                                    <a target="_blank" href="https://www.tipsandtricks-hq.com/wordpress-estore-plugin-complete-solution-to-sell-digital-products-from-your-wordpress-blog-securely-1059">WP eStore Plugin</a>.
				</div>
			</div>
			<div class="postbox" style="min-width: inherit;">
				<h3 class="hndle"><label for="title"><?php echo __( 'Rate Us', 'stripe-payments' ); ?></label></h3>
				<div class="inside">
				<?php
				// translators: %s is replaced by "rating" link
				echo sprintf( _x( 'Like the plugin? Please give us a good %s', '%s is replaced by "rating" link', 'stripe-payments' ), sprintf( '<a href="https://wordpress.org/support/plugin/stripe-payments/reviews/?filter=5" target="_blank">%s</a>', __( 'rating!', 'stripe-payments' ) ) );
				?>
				<div class="wp-asp-stars-container">
					<a href="https://wordpress.org/support/plugin/stripe-payments/reviews/?filter=5" target="_blank">
					<span class="dashicons dashicons-star-filled"></span>
					<span class="dashicons dashicons-star-filled"></span>
					<span class="dashicons dashicons-star-filled"></span>
					<span class="dashicons dashicons-star-filled"></span>
					<span class="dashicons dashicons-star-filled"></span>
					</a>
				</div>
				</div>
			</div>
			<div class="postbox" style="min-width: inherit;">
				<h3 class="hndle"><label for="title"><?php echo __( 'Testing Version', 'stripe-payments' ); ?></label></h3>
				<div class="inside">
				<?php
				// translators: %s is replaced by "Testing Version" link for testing version
				echo sprintf( _x( "Want to see or test upcoming features, bugfixes or changes before they're released? Install %s of the plugin.", '%s is replaced by "Testing Version" link for testing version', 'stripe-payments' ), sprintf( '<a href="https://s-plugins.com/testing-version/" target="_blank">%s</a>', _x( 'Testing Version', 'Link for testing version of the plugin', 'stripe-payments' ) ) );
				?>
				</div>
			</div>
			</div>
		<?php submit_button(); ?>
		</div>

		</form>
	<?php
	do_action( 'asp-settings-page-after-form' );
	?>

	<?php
	wp_localize_script(
		'asp-admin-settings-js',
		'aspSettingsData',
		array(
			'transHash'  => $tab,
			'currencies' => AcceptStripePayments::get_currencies(),
			'str'        => array(
				'logClearConfirm' => __( 'Are you sure you want to clear log?', 'stripe-payments' ),
				'logCleared'      => __( 'Log cleared.', 'stripe-payments' ),
				'errorOccurred'   => __( 'Error occurred:', 'stripe-payments' ),
			),
		)
	);
	wp_enqueue_script( 'asp-admin-settings-js' );
}
?>
</div>
