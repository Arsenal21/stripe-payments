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

    $tab = get_transient( 'wp-asp-urlHash' );

    if ( $tab ) {
	delete_transient( 'wp-asp-urlHash' );
    }
    ?>
    <style>
        div.wp-asp-tab-container {
    	display: none;
        }

        div.wp-asp-tab-container p.description span {
    	font-style: normal;
    	background: #e3e3e3;
    	padding: 2px 5px;
        }

    </style>
    <?php
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

    	<div class="wp-asp-tab-container" data-tab-name="general">
		<?php do_settings_sections( 'accept_stripe_payment-docs' ); ?>
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
	    <?php submit_button(); ?>

        </form>
    </div>
    <script>
        var wp_asp_urlHash = window.location.hash.substr(1);
        var wp_asp_transHash = '<?php echo esc_attr( $tab ); ?>';

        if (wp_asp_urlHash === '') {
    	if (wp_asp_transHash !== '') {
    	    wp_asp_urlHash = wp_asp_transHash;
    	} else {
    	    wp_asp_urlHash = 'general';
    	}
        }
        jQuery(function ($) {
    	var wp_asp_activeTab = "";
    	$('a.nav-tab').click(function (e) {
    	    if ($(this).attr('data-tab-name') !== wp_asp_activeTab) {
    		$('div.wp-asp-tab-container[data-tab-name="' + wp_asp_activeTab + '"]').hide();
    		$('a.nav-tab[data-tab-name="' + wp_asp_activeTab + '"]').removeClass('nav-tab-active');
    		wp_asp_activeTab = $(this).attr('data-tab-name');
    		$('div.wp-asp-tab-container[data-tab-name="' + wp_asp_activeTab + '"]').show();
    		$(this).addClass('nav-tab-active');
    		$('input#wp-asp-urlHash').val(wp_asp_activeTab);
    		if (window.location.hash !== wp_asp_activeTab) {
    		    window.location.hash = wp_asp_activeTab;
    		}
    	    }
    	});

    	$('#asp_clear_log_btn').click(function (e) {
    	    e.preventDefault();
    	    if (confirm("<?php _e( 'Are you sure want to clear log?', 'stripe-payments' ); ?>")) {
    		var req = jQuery.ajax({
    		    url: ajaxurl,
    		    type: "post",
    		    data: {action: "asp_clear_log"}
    		});
    		req.done(function (data) {
    		    if (data === '1') {
    			alert("<?php _e( 'Log cleared.', 'stripe-payments' ); ?>");
    		    } else {
    			alert("Error occured: " + data);
    		    }
    		});
    	    }
    	});

    	$('a.nav-tab[data-tab-name="' + wp_asp_urlHash + '"]').trigger('click');
        });
    </script>

    <?php
}