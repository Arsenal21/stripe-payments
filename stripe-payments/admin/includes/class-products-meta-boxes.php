<?php

class asp_products_metaboxes {

    public function __construct() {
	remove_post_type_support( ASPMain::$products_slug, 'editor' );
	add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
	//products post save action
	add_action( 'save_post_' . ASPMain::$products_slug, array( $this, 'save_product_handler' ), 10, 3 );
    }

    function add_meta_boxes() {
	add_meta_box( 'wsp_content', __( 'Description', 'stripe-payments' ), array( $this, 'display_description_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
	add_meta_box( 'asp_short_description_meta_box', __( 'Short Description', 'stripe-payments' ), array( $this, 'display_short_description_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
	add_meta_box( 'asp_price_meta_box', __( 'Price & Currency', 'stripe-payments' ), array( $this, 'display_price_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
	add_meta_box( 'asp_quantity_meta_box', __( 'Quantity & Stock', 'stripe-payments' ), array( $this, 'display_quantity_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
	add_meta_box( 'asp_upload_meta_box', __( 'Download URL', 'stripe-payments' ), array( $this, 'display_upload_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
	add_meta_box( 'asp_thumbnail_meta_box', __( 'Product Thumbnail (optional)', 'stripe-payments' ), array( $this, 'display_thumbnail_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
	add_meta_box( 'asp_address_meta_box', __( 'Collect Address', 'stripe-payments' ), array( $this, 'display_address_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
	add_meta_box( 'asp_shipping_tax_meta_box', __( 'Shipping & Tax', 'stripe-payments' ), array( $this, 'display_shipping_tax_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
	add_meta_box( 'asp_thankyou_page_meta_box', __( 'Thank You Page URL', 'stripe-payments' ), array( $this, 'display_thankyou_page_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
	add_meta_box( 'asp_appearance_meta_box', __( 'Appearance', 'stripe-payments' ), array( $this, 'display_appearance_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
	add_meta_box( 'asp_custom_field_meta_box', __( 'Custom Field', 'stripe-payments' ), array( $this, 'display_custom_field_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
	add_meta_box( 'asp_shortcode_meta_box', __( 'Shortcode', 'stripe-payments' ), array( $this, 'display_shortcode_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );

	do_action( 'asp_edit_product_metabox' );
    }

    function display_description_meta_box( $post ) {
	_e( 'Add a description for your product.', 'stripe-payments' );
	echo '<br /><br />';
	wp_editor( $post->post_content, "content", array( 'textarea_name' => 'content' ) );
    }

    function display_short_description_meta_box( $post ) {
	$current_val = get_post_meta( $post->ID, 'asp_product_description', true );
	?>
	<input type="text" name="asp_product_description" size="50" value="<?php echo $current_val; ?>">
	<p class="description"><?php echo __( 'You can optionally add a custom short description for the item/product/service that will get shown in the stripe checkout/payment window of the item.', 'stripe-payments' ); ?></p>
	<?php
    }

    function display_price_meta_box( $post ) {
	$current_price	 = get_post_meta( $post->ID, 'asp_product_price', true );
	$current_curr	 = get_post_meta( $post->ID, 'asp_product_currency', true );
	do_action( 'asp_product_price_metabox_before_content', $post );
	?>
	<label><?php _e( 'Price', 'stripe-payments' ); ?></label>
	<br/>
	<input type="text" name="asp_product_price" value="<?php echo $current_price; ?>">
	<p class="description"><?php
	    echo __( 'Item price. Numbers only, no need to put currency symbol. Example: 99.95', 'stripe-payments' ) .
	    '<br>' . __( 'Leave it blank if you want your customers to enter the amount themselves (e.g. for donation button).', 'stripe-payments' );
	    ?></p>
	<label><?php _e( 'Currency', 'stripe-payments' ); ?></label>
	<br/>
	<select name="asp_product_currency" id="asp_currency_select"><?php echo AcceptStripePayments_Admin::get_currency_options( $current_curr ); ?>></select>
	<p class = "description"><?php echo __( 'Leave "(Default)" option selected if you want to use currency specified on settings page.', 'stripe-payments' ); ?></p>
	<?php
	do_action( 'asp_product_price_metabox_after_content', $post );
    }

    function display_shipping_tax_meta_box( $post ) {
	$current_shipping	 = get_post_meta( $post->ID, 'asp_product_shipping', true );
	$current_tax		 = get_post_meta( $post->ID, 'asp_product_tax', true );
	?>
	<div id="asp_shipping_cost_container">
	    <label><?php _e( 'Shipping Cost', 'stripe-payments' ); ?></label>
	    <br/>
	    <input type="text" name="asp_product_shipping" value="<?php echo $current_shipping; ?>">
	    <p class="description">
		<?php
		echo __( 'Numbers only, no need to put currency symbol. Example: 5.90', 'stripe-payments' ) .
		'<br>' . __( 'Leave it blank if you are not shipping your product or not charging additional shipping costs.', 'stripe-payments' );
		?>
	    </p>
	</div>
	<label><?php _e( 'Tax (%)', 'stripe-payments' ); ?></label>
	<br/>
	<input type="text" name="asp_product_tax" value="<?php echo $current_tax; ?>">
	<p class = "description">
	    <?php
	    echo __( 'Enter tax (in percents) which should be added to product price during purchase.', 'stripe-payments' ) .
	    '<br>' . __( 'Leave it blank if you don\'t want to apply tax.', 'stripe-payments' );
	    ?>
	</p>
	<?php
    }

    function display_quantity_meta_box( $post ) {
	$current_val		 = get_post_meta( $post->ID, 'asp_product_quantity', true );
	$allow_custom_quantity	 = get_post_meta( $post->ID, 'asp_product_custom_quantity', true );
	$enable_stock		 = get_post_meta( $post->ID, 'asp_product_enable_stock', true );
	$stock_items		 = get_post_meta( $post->ID, 'asp_product_stock_items', true );
	?>
	<p><?php echo __( 'By default, if you leave this field empty, the product quantity will be set to 1. You can change this behavior by using the following options.', 'stripe-payments' ); ?></p>

	<label>
	    <input type="checkbox" name="asp_product_custom_quantity" value="1"<?php echo ($allow_custom_quantity === "1") ? ' checked' : ''; ?>>
	    <?php echo __( 'Allow users to specify quantity', 'stripe-payments' ); ?>
	</label>
	<p class="description"><?php echo __( "When checked, users can enter quantity they want to buy.", 'stripe-payments' ); ?></p>

	<div style="margin-top: 20px;"><label><?php _e( 'Set Quantity:', 'stripe-payments' ); ?>
		<input type="text" name="asp_product_quantity" value="<?php echo $current_val; ?>">
	    </label>
	    <p class="description"><?php _e( 'If you want to use a set quanity for this item then enter the value in this field.', 'stripe-payments' ); ?></p>
	</div>

	<hr />

	<label>
	    <input type="checkbox" name="asp_product_enable_stock" value="1"<?php echo ($enable_stock === "1") ? ' checked' : ''; ?>>
	    <?php echo __( 'Enable stock control', 'stripe-payments' ); ?>
	</label>
	<p class="description"><?php echo __( "When enabled, you can specify the quantity available for this product. It will be decreased each time the item is purchased. When stock reaches zero, an \"Out of stock\" message will be displayed instead of the buy button.", 'stripe-payments' ); ?></p>

	<div style="margin-top: 20px;"><label><?php _e( 'Quantity Available:', 'stripe-payments' ); ?>
		<input type="number" name="asp_product_stock_items" value="<?php echo ! $stock_items ? 0 : $stock_items; ?>">
	    </label>
	    <p class="description"><?php _e( 'Specify the quantity available for this product.', 'stripe-payments' ); ?></p>
	</div>

	<?php
    }

    public function display_upload_meta_box( $post ) {
	$current_val = get_post_meta( $post->ID, 'asp_product_upload', true );
	?>
	<p><?php echo __( 'URL of your product (if you\'re selling digital products).', 'stripe-payments' ); ?></p>

	<div>
	    <input id="asp_product_upload" type="text" style="width: 100%" name="asp_product_upload" value="<?php echo esc_attr( $current_val ); ?>" placeholder="http://..." />

	    <p class="description">
		<?php _e( 'Manually enter a valid URL of the file in the text box below, or click "Select File" button to upload (or choose) the downloadable file.', 'stripe-payments' ); ?>
	    </p>
	</div>
	<p>
	    <input id="asp_select_upload_btn" type="button" class="button" value="<?php echo __( 'Select File', 'stripe-payments' ); ?>" />
	</p>
	<div>
	    <?php _e( 'Steps to upload a file or choose one from your media library:', 'stripe-payments' ); ?>
	    <ol>
		<li><?php _e( 'Hit the "Select File" button.', 'stripe-payments' ); ?></li>
		<li><?php _e( 'Upload a new file or choose an existing one from your media library.', 'stripe-payments' ) ?></li>
		<li><?php _e( 'Click the "Insert" button, this will populate the uploaded file\'s URL in the above text field.', 'stripe-payments' ) ?></li>
	    </ol>
	</div>
	<script>
	    jQuery(document).ready(function ($) {
		var asp_selectFileFrame;
		// Run media uploader for file upload
		$('#asp_select_upload_btn').click(function (e) {
		    e.preventDefault();
		    asp_selectFileFrame = wp.media({
			title: "<?php echo __( 'Select File', 'stripe-payments' ); ?>",
			button: {
			    text: "<?php echo __( 'Insert', 'stripe-payments' ); ?>"
			},
			multiple: false
		    });
		    asp_selectFileFrame.open();
		    asp_selectFileFrame.on('select', function () {
			var attachment = asp_selectFileFrame.state().get('selection').first().toJSON();

			$('#asp_product_upload').val(attachment.url);
		    });
		    return false;
		});
	    });
	</script>
	<?php
    }

    public function display_thumbnail_meta_box( $post ) {
	$current_val		 = get_post_meta( $post->ID, 'asp_product_thumbnail', true );
	$current_no_popup_thumb	 = get_post_meta( $post->ID, 'asp_product_no_popup_thumbnail', true );
	?>
	<div>
	    <input id="asp_product_thumbnail" type="text" style="width: 100%" name="asp_product_thumbnail" value="<?php echo esc_attr( $current_val ); ?>" placeholder="http://..." />

	    <p class="description">
		<?php echo __( 'Manually enter a valid URL, or click "Select Image" to upload (or choose) the file thumbnail image.', 'stripe-payments' ); ?>
	    </p>
	</div>
	<p>
	    <input id="asp_select_thumbnail_btn" type="button" class="button" value="<?php echo __( 'Select Image', 'stripe-payments' ); ?>" />
	    <input id="asp_remove_thumbnail_button" class="button" value="<?php echo __( 'Remove Image', 'stripe-payments' ); ?>" type="button">
	</p>
	<div>
	    <span id="asp_admin_thumb_preview">
		<?php if ( $current_val ) { ?>
	    	<img id="asp_thumbnail_image" src="<?php echo $current_val; ?>" style="max-width:200px;" />
		<?php } ?>
	    </span>
	</div>
	<label><input type="checkbox" name="asp_product_no_popup_thumbnail" value="1"<?php echo ($current_no_popup_thumb === "1") ? ' checked' : ''; ?>/> <?php _e( "Don't use product thumbnail in Stripe pop-up", 'stripe-payments' ); ?></label>
	<p class="description">
	    <?php _e( "Use this checkbox if you do not want to show the product thumbnail in Stripe checkout popup.", 'stripe-payments' ); ?>
	</p>
	<script>
	    jQuery(document).ready(function ($) {
		var asp_selectFileFrame;
		$('#asp_select_thumbnail_btn').click(function (e) {
		    e.preventDefault();
		    asp_selectFileFrame = wp.media({
			title: "<?php echo __( 'Select Image', 'stripe-payments' ); ?>",
			button: {
			    text: "<?php echo __( 'Insert', 'stripe-payments' ); ?>",
			},
			multiple: false,
			library: {type: 'image'},
		    });
		    asp_selectFileFrame.open();
		    asp_selectFileFrame.on('select', function () {
			var attachment = asp_selectFileFrame.state().get('selection').first().toJSON();
			$('#asp_thumbnail_image').remove();
			$('#asp_admin_thumb_preview').html('<img id="asp_thumbnail_image" src="' + attachment.url + '" style="max-width:200px;" />');
			$('#asp_product_thumbnail').val(attachment.url);
		    });
		    return false;
		});
		$('#asp_remove_thumbnail_button').click(function (e) {
		    e.preventDefault();
		    $('#asp_thumbnail_image').remove();
		    $('#asp_product_thumbnail').val('');
		});
	    });
	</script>
	<?php
    }

    function display_address_meta_box( $post ) {
	$collect_billing_addr	 = get_post_meta( $post->ID, 'asp_product_collect_billing_addr', true );
	$collect_shipping_addr	 = get_post_meta( $post->ID, 'asp_product_collect_shipping_addr', true );
	?>
	<label><input type="checkbox" name="asp_product_collect_billing_addr" value="1"<?php echo ($collect_billing_addr === "1") ? ' checked' : ''; ?>><?php echo __( 'Collect Address on Checkout', 'stripe-payments' ); ?> </label>
	<p class="description"><?php echo __( "Enable this to collect customer address on checkout.", 'stripe-payments' ); ?></p>
	<div style="margin-left:30px;">
	    <label><input type="radio" name="asp_product_collect_shipping_addr" value="1"<?php echo ($collect_shipping_addr === "1" || $collect_shipping_addr === "") ? ' checked' : ''; ?>><?php echo __( 'Collect Both Billing And Shipping Addresses', 'stripe-payments' ); ?> </label>
	    <p></p>
	    <label><input type="radio" name="asp_product_collect_shipping_addr" value="0"<?php echo ($collect_shipping_addr === "0") ? ' checked' : ''; ?>><?php echo __( 'Collect Billing Address Only', 'stripe-payments' ); ?> </label>
	</div>
	<?php
    }

    function display_thankyou_page_meta_box( $post ) {
	$current_val = get_post_meta( $post->ID, 'asp_product_thankyou_page', true );
	?>
	<input type="text" name="asp_product_thankyou_page" style="width: 100%;" value="<?php echo ! empty( $current_val ) ? $current_val : ''; ?>">
	<p class="description"><?php _e( 'Enter Thank You page URL. Leave it blank if you want ot use default Thank You page.', 'stripe-payments' ); ?>
	    <br />
	    <?php _e( 'You can read how to customize messages on Thank You page <a href="https://stripe-plugins.com/customize-the-thank-page-message-of-stripe-payments-plugin/" target="_blank">in the documentation</a>.', 'stripe-payments' ); ?>
	</p>
	<?php
    }

    function display_appearance_meta_box( $post ) {
	$button_txt	 = get_post_meta( $post->ID, 'asp_product_button_text', true );
	$button_class	 = get_post_meta( $post->ID, 'asp_product_button_class', true );
	$button_only	 = get_post_meta( $post->ID, 'asp_product_button_only', true );
	?>
	<label><?php _e( 'Button Text', 'stripe-payments' ); ?></label>
	<br/>
	<input type="text" name="asp_product_button_text" size="50" value="<?php echo $button_txt; ?>">
	<p class="description"><?php _e( 'Specify text to be displayed on the button. Leave it blank to use button text specified on settings page.', 'stripe-payments' ); ?></p>
	<label><?php _e( 'Button CSS Class', 'stripe-payments' ); ?></label>
	<br/>
	<input type="text" name="asp_product_button_class" size="50" value="<?php echo $button_class; ?>">
	<p class="description"><?php _e( 'CSS class to be assigned to the button. This is used for styling purposes. You can get additional information <a href="https://stripe-plugins.com/customize-stripe-payment-button-appearance-using-css/" target="_blank">in this tutorial</a>.', 'stripe-payments' ); ?></p>
	<label><input type="checkbox" name="asp_product_button_only" value="1"<?php echo ($button_only == 1) ? " checked" : ""; ?>> <?php _e( 'Show Button Only', 'stripe-payments' ); ?></label>
	<p class="description"><?php _e( 'Check this box if you just want to show the button only without any additional product info.', 'stripe-payments' ); ?></p>
	<?php
    }

    function display_custom_field_meta_box( $post ) {
	$current_val = get_post_meta( $post->ID, 'asp_product_custom_field', true );

	$show_custom_field_settings	 = '';
	$asp_settings			 = AcceptStripePayments::get_instance();
	$field_name			 = $asp_settings->get_setting( 'custom_field_name' );
	if ( ! empty( $field_name ) ) {//Custom field configured so show product specific settings
	    $show_custom_field_settings = '1';
	}
	$show_custom_field_settings = apply_filters( 'asp_show_product_custom_field_settings', $show_custom_field_settings ); //Filter to allow addon to override this
	if ( empty( $show_custom_field_settings ) ) {
	    //Custom field isn't configured. Don't show the seettings
	    _e( 'Custom field is disabled. Configure custom field in the settings menu of this plugin to enable it.', 'stripe-payments' );
	    return;
	}
	?>
	<p><?php _e( 'Select how Custom Field display should be handled for this product.', 'stripe-payments' ); ?></p>
	<label><input type="radio" name="asp_product_custom_field" value="2"<?php echo ($current_val === "2" || $current_val === "") ? ' checked' : ''; ?>><?php echo __( 'Use Global Setting', 'stripe-payments' ); ?> </label>
	<label><input type="radio" name="asp_product_custom_field" value="1"<?php echo ($current_val === "1") ? ' checked' : ''; ?>><?php echo __( 'Enabled', 'stripe-payments' ); ?> </label>
	<label><input type="radio" name="asp_product_custom_field" value="0"<?php echo ($current_val === "0") ? ' checked' : ''; ?>><?php echo __( 'Disabled', 'stripe-payments' ); ?> </label>
	<?php
    }

    function display_shortcode_meta_box( $post ) {
	$current_val = get_post_meta( $post->ID, 'asp_product_button_text', true );
	?>
	<input type="text" name="asp_product_shortcode" size="50" readonly value="[asp_product id=&quot;<?php echo $post->ID; ?>&quot;]">
	<p class="description"><?php _e( 'Use this shortcode to display button for your product.', 'stripe-payments' ); ?></p>
	<?php
    }

    function save_product_handler( $post_id, $post, $update ) {
	if ( ! isset( $_POST[ 'action' ] ) ) {
	    //this is probably not edit or new post creation event
	    return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
	    return;
	}
	if ( isset( $post_id ) ) {
	    update_post_meta( $post_id, 'asp_product_price', sanitize_text_field( $_POST[ 'asp_product_price' ] ) );
	    update_post_meta( $post_id, 'asp_product_currency', sanitize_text_field( $_POST[ 'asp_product_currency' ] ) );
	    update_post_meta( $post_id, 'asp_product_shipping', sanitize_text_field( $_POST[ 'asp_product_shipping' ] ) );
	    update_post_meta( $post_id, 'asp_product_tax', sanitize_text_field( $_POST[ 'asp_product_tax' ] ) );
	    update_post_meta( $post_id, 'asp_product_quantity', sanitize_text_field( $_POST[ 'asp_product_quantity' ] ) );
	    update_post_meta( $post_id, 'asp_product_custom_quantity', isset( $_POST[ 'asp_product_custom_quantity' ] ) ? "1" : false  );
	    update_post_meta( $post_id, 'asp_product_enable_stock', isset( $_POST[ 'asp_product_enable_stock' ] ) ? "1" : false  );
	    update_post_meta( $post_id, 'asp_product_stock_items', sanitize_text_field( absint( $_POST[ 'asp_product_stock_items' ] ) ) );

	    update_post_meta( $post_id, 'asp_product_custom_field', isset( $_POST[ 'asp_product_custom_field' ] ) ? sanitize_text_field( $_POST[ 'asp_product_custom_field' ] ) : "0"  );
	    update_post_meta( $post_id, 'asp_product_button_text', sanitize_text_field( $_POST[ 'asp_product_button_text' ] ) );
	    update_post_meta( $post_id, 'asp_product_button_class', sanitize_text_field( $_POST[ 'asp_product_button_class' ] ) );
	    update_post_meta( $post_id, 'asp_product_button_only', isset( $_POST[ 'asp_product_button_only' ] ) ? 1 : 0  );
	    update_post_meta( $post_id, 'asp_product_description', sanitize_text_field( $_POST[ 'asp_product_description' ] ) );
	    update_post_meta( $post_id, 'asp_product_upload', esc_url( $_POST[ 'asp_product_upload' ] ) );
	    update_post_meta( $post_id, 'asp_product_thumbnail', esc_url( $_POST[ 'asp_product_thumbnail' ] ) );
	    update_post_meta( $post_id, 'asp_product_no_popup_thumbnail', isset( $_POST[ 'asp_product_no_popup_thumbnail' ] ) ? "1" : false  );
	    update_post_meta( $post_id, 'asp_product_thankyou_page', isset( $_POST[ 'asp_product_thankyou_page' ] ) && ! empty( $_POST[ 'asp_product_thankyou_page' ] ) ? esc_url( $_POST[ 'asp_product_thankyou_page' ] ) : ''  );
	    $shipping_addr = false;
	    if ( isset( $_POST[ 'asp_product_collect_shipping_addr' ] ) ) {
		$shipping_addr = $_POST[ 'asp_product_collect_shipping_addr' ];
	    }
	    update_post_meta( $post_id, 'asp_product_collect_shipping_addr', $shipping_addr );
	    update_post_meta( $post_id, 'asp_product_collect_billing_addr', isset( $_POST[ 'asp_product_collect_billing_addr' ] ) ? "1" : false  );

	    do_action( 'asp_save_product_handler', $post_id, $post, $update );
	}
    }

}

$asp_products_metaboxes = new asp_products_metaboxes();
