<?php
/**
 * Represents the view for the administration dashboard.
 *
 * This includes the header, options, and other information that should provide
 * The User Interface to the end user.
 */
if(!current_user_can('manage_options')){
    wp_die('You do not have permission to access this settings page.');
}
?>

<div class="wrap">

	<h2><?php echo esc_html( get_admin_page_title() ); ?></h2>

	<form method="post" action="options.php">

		<?php settings_fields( 'AcceptStripePayments-settings-group' ); ?>

		<?php do_settings_sections( 'accept_stripe_payment' ); ?>

		<?php submit_button(); ?>

	</form>
</div>
