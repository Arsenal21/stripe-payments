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
        a.wp-asp-toggle {
    	text-decoration: none;
    	border-bottom: 1px dashed;
        }
        a.wp-asp-toggle.toggled-on::after {
    	content: " ↑";
        }
        a.wp-asp-toggle.toggled-off::after {
    	content: " ↓";
        }
        div.wp-asp-tabs {
    	width: 80%;
        }
        div.wp-asp-tabs input {
    	max-width: 100%;
        }
        div.wp-asp-tabs table {
    	width: 100%;
    	table-layout: fixed;
        }
        div.wp-asp-settings-sidebar-cont {
    	width: 19%;
    	float: right;
        }
        div.wp-asp-settings-grid {
    	display: inline-block;
        }
        div#poststuff {
    	min-width: 19%;
        }
        .wp-asp-stars-container {
    	text-align: center;
    	margin-top: 10px;
        }
        .wp-asp-stars-container span {
    	vertical-align: text-top;
    	color: #ffb900;
        }
        .wp-asp-stars-container a {
    	text-decoration: none;
        }
        @media (max-width: 782px) {
    	div.wp-asp-settings-grid {
    	    display: block;
    	    float: none;
    	    width: 100%;
    	}
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
		    <?php submit_button(); ?>

    		</form>
    	    </div>
    	    <div id="poststuff" class="wp-asp-settings-grid wp-asp-settings-sidebar-cont">
    		<div class="postbox" style="min-width: inherit;">
    		    <h3 class="hndle"><label for="title"><?php echo __( 'Plugin Documentation', 'stripe-payments' ); ?></label></h3>
    		    <div class="inside">
			    <?php echo sprintf( __( 'Please read the %s plugin setup instructions and tutorials to learn how to configure and use it.', 'stripe-payments' ), '<a target="_blank" href="https://stripe-plugins.com/stripe-payments-plugin-tutorials/">Stripe Payments</a>' ); ?>
    		    </div>
    		</div>
    		<div class="postbox" style="min-width: inherit;">
    		    <h3 class="hndle"><label for="title"><?php echo __( 'Add-ons', 'stripe-payments' ); ?></label></h3>
    		    <div class="inside">
			    <?php echo sprintf( __( 'Want additional functionality like subscriptions, Apple Pay support or MailChimp integration? Check out our %s', 'stripe-payments' ), '<a target="_blank" href="edit.php?post_type=asp-products&page=stripe-payments-addons">Add-Ons!</a>' ); ?>
    		    </div>
    		</div>
    		<div class="postbox" style="min-width: inherit;">
    		    <h3 class="hndle"><label for="title"><?php echo __( 'Rate Us', 'stripe-payments' ); ?></label></h3>
    		    <div class="inside">
			    <?php echo sprintf( _x( 'Like the plugin? Please give us a %s', '%s is replaced by "rating" link', 'stripe-payments' ), sprintf( '<a href="https://wordpress.org/support/plugin/stripe-payments/reviews/?filter=5" target="_blank">%s</a>', __( 'rating!', 'stripe-payments' ) ) ); ?>
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
    	    </div>
    	</div>
	    <?php
	    json_encode( AcceptStripePayments::get_currencies() );
	    ?>
    	<script>
    	    var wp_asp_urlHash = window.location.hash.substr(1);
    	    var wp_asp_transHash = '<?php echo esc_attr( $tab ); ?>';

    	    var wp_asp_currencies = JSON.parse('<?php echo json_encode( AcceptStripePayments::get_currencies() ); ?>');

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

    		$('a.wp-asp-toggle').click(function (e) {
    		    e.preventDefault();
    		    div = $(this).siblings('div');
    		    if (div.is(":visible")) {
    			$(this).removeClass('toggled-on');
    			$(this).addClass('toggled-off');
    		    } else {
    			$(this).removeClass('toggled-off');
    			$(this).addClass('toggled-on');
    		    }
    		    div.slideToggle('fast');
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

    		$('#wp_asp_curr_code').change(function () {
    		    $('#wp_asp_curr_symb').val(wp_asp_currencies[$('#wp_asp_curr_code').val()][1]);
    		});

    		$('#wp_asp_curr_code').change();

    		$('a.nav-tab[data-tab-name="' + wp_asp_urlHash + '"]').trigger('click');
    	    });
    	</script>

	    <?php
	}