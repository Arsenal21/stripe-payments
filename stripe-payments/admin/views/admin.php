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

if ( $_GET[ 'page' ] == 'stripe-payments-settings' ) {
    ?>

    <div class="wrap">

        <h2><?php echo esc_html( get_admin_page_title() ); ?></h2>

        <form method="post" action="options.php">

	    <?php settings_fields( 'AcceptStripePayments-settings-group' ); ?>

	    <?php do_settings_sections( 'accept_stripe_payment' ); ?>

	    <?php submit_button(); ?>

        </form>
    </div>

    <?php
} else if ( $_GET[ 'page' ] == 'asp-products' ) {
    ?>
    <div class="wrap">
        <h1 id="asp-products" class="wp-heading-inline"><?php echo 'Products'; ?></h1>
        <a href="" class="page-title-action">Add New</a>
        <hr class="wp-header-end">
	<?php
	require_once(WP_ASP_PLUGIN_PATH . 'admin/includes/class-products-table.php');

	$products_table = new asp_list_table();
	$products_table->prepare_items();
	$products_table->views();
	$products_table->display();
	?>
    </div>
    <?php
}