<?php

class asp_products_metaboxes {

    public function __construct() {
	remove_post_type_support( ASPMain::$products_slug, 'editor' );
	add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
    }

    function add_meta_boxes() {
	add_meta_box( 'wsp_content', __( 'Description', 'stripe-payments' ), array( $this, 'display_description_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
	add_meta_box( 'asp_short_description_meta_box', __( 'Short Description', 'stripe-payments' ), array( $this, 'display_short_description_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
	add_meta_box( 'asp_price_meta_box', __( 'Price', 'stripe-payments' ), array( $this, 'display_price_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
	add_meta_box( 'asp_currency_meta_box', __( 'Currency', 'stripe-payments' ), array( $this, 'display_currency_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
	add_meta_box( 'asp_quantity_meta_box', __( 'Quantity', 'stripe-payments' ), array( $this, 'display_quantity_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
	add_meta_box( 'asp_upload_meta_box', __( 'Download URL', 'stripe-payments' ), array( $this, 'display_upload_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
	add_meta_box( 'asp_thumbnail_meta_box', __( 'Product Thumbnail (optional)', 'stripe-payments' ), array( $this, 'display_thumbnail_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
	add_meta_box( 'asp_address_meta_box', __( 'Collect Address', 'stripe-payments' ), array( $this, 'display_address_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
	add_meta_box( 'asp_button_text_meta_box', __( 'Button Text', 'stripe-payments' ), array( $this, 'display_button_text_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
	add_meta_box( 'asp_custom_field_meta_box', __( 'Custom Field', 'stripe-payments' ), array( $this, 'display_custom_field_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
	add_meta_box( 'asp_shortcode_meta_box', __( 'Shortcode', 'stripe-payments' ), array( $this, 'display_shortcode_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
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
	$current_val = get_post_meta( $post->ID, 'asp_product_price', true );
	?>
	<input type="text" name="asp_product_price" value="<?php echo $current_val; ?>">
	<p class="description">Item price. Numbers only, no need to put currency symbol. Example: 99.95<br>Leave it blank if you want your customers to enter the amount themselves (e.g. for donation button). </p>
	<?php
    }

    function display_currency_meta_box( $post ) {
	$current_val = get_post_meta( $post->ID, 'asp_product_currency', true );
	?>
	<select name="asp_product_currency" id="asp_currency_select"><?php echo AcceptStripePayments_Admin::get_currency_options( $current_val ); ?>></select>
	<p class = "description">Leave "(Default)" option selected if you want to use currency specified on settings page.</p>
	<?php
    }

    function display_quantity_meta_box( $post ) {
	$current_val		 = get_post_meta( $post->ID, 'asp_product_quantity', true );
	$allow_custom_quantity	 = get_post_meta( $post->ID, 'asp_product_custom_quantity', true );
	?>
	<p>By default, if you leave this field empty, the product quantity will be set to 1. You can change this behavior by using the following options.</p>

	<label>
	    <input type="checkbox" name="asp_product_custom_quantity" value="1"<?php echo ($allow_custom_quantity === "1") ? ' checked' : ''; ?>>
	    <?php echo __( 'Allow users to specify quantity', 'stripe-payments' ); ?>
	</label>
	<p class="description"><?php echo __( "When checked, users can enter qunatity they want to buy.", 'stripe-payments' ); ?></p>


	<div style="margin-top: 20px;">Set Quantity <input type="text" name="asp_product_quantity" value="<?php echo $current_val; ?>"></div>
	<p class="description">If you want to use a set quanity for this item then enter the value in this field.</p>
	<?php
    }

    public function display_upload_meta_box( $post ) {
	$current_val = get_post_meta( $post->ID, 'asp_product_upload', true );
	?>
	<p><?php echo __( 'URL of your product (if you\'re selling digital products).', 'stripe-payments' ); ?></p>

	<div>
	    <input id="asp_product_upload" type="text" size="100" name="asp_product_upload" value="<?php echo esc_attr( $current_val ); ?>" placeholder="http://..." />

	    <p class="description">
		Manually enter a valid URL of the file in the text box below, or click "Select File" button to upload (or choose) the downloadable file.
	    </p>
	</div>
	<p>
	    <input id="asp_select_upload_btn" type="button" class="button" value="<?php echo __( 'Select File', 'stripe-payments' ); ?>" />
	</p>
	<div>
	    Steps to upload a file or choose one from your media library:
	    <ol>
		<li>Hit the "Select File" button.</li>
		<li>Upload a new file or choose an existing one from your media library.</li>
		<li>Click the "Insert" button, this will populate the uploaded file's URL in the above text field.</li>
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
	$current_val = get_post_meta( $post->ID, 'asp_product_thumbnail', true );
	?>
	<div>
	    <input id="asp_product_thumbnail" type="text" size="100" name="asp_product_thumbnail" value="<?php echo esc_attr( $current_val ); ?>" placeholder="http://..." />

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

    function display_button_text_meta_box( $post ) {
	$current_val = get_post_meta( $post->ID, 'asp_product_button_text', true );
	?>
	<input type="text" name="asp_product_button_text" size="50" value="<?php echo $current_val; ?>">
	<p class="description">Specify text to be displayed on the button. Leave it blank to use button text specified on settings page.</p>
	<?php
    }

    function display_custom_field_meta_box( $post ) {
	$current_val = get_post_meta( $post->ID, 'asp_product_custom_field', true );
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
	<p class="description">Use this shortcode to display button for your product.</p>
	<?php
    }

}

$asp_products_metaboxes = new asp_products_metaboxes();
