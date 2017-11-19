<?php

class asp_products_metaboxes {

    public function __construct() {
	remove_post_type_support( 'asp_products', 'editor' );
	add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ) );
    }

    function add_meta_boxes() {
	add_meta_box( 'asp_description_meta_box', __( 'Description', 'stripe_payments' ), array( $this, 'display_description_meta_box' ), 'asp_products', 'normal', 'default' );
	add_meta_box( 'asp_price_meta_box', __( 'Price', 'stripe_payments' ), array( $this, 'display_price_meta_box' ), 'asp_products', 'normal', 'default' );
	add_meta_box( 'asp_currency_meta_box', __( 'Currency', 'stripe_payments' ), array( $this, 'display_currency_meta_box' ), 'asp_products', 'normal', 'default' );
	add_meta_box( 'asp_quantity_meta_box', __( 'Quantity', 'stripe_payments' ), array( $this, 'display_quantity_meta_box' ), 'asp_products', 'normal', 'default' );
	add_meta_box( 'asp_button_text_meta_box', __( 'Button Text', 'stripe_payments' ), array( $this, 'display_button_text_meta_box' ), 'asp_products', 'normal', 'default' );
	add_meta_box( 'asp_shortcode_meta_box', __( 'Shortcode', 'stripe_payments' ), array( $this, 'display_shortcode_meta_box' ), 'asp_products', 'normal', 'default' );
    }

    function display_description_meta_box( $post ) {
	$current_val = get_post_meta( $post->ID, 'asp_product_description', true );
	?>
	<input type="text" name="asp_product_description" size="50" value="<?php echo $current_val; ?>">
	<p class="description"><?php echo __( 'You can optionally add a custom description for the item/product/service that will get shown in the stripe checkout/payment window of the item.', 'stripe_payments' ); ?></p>
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
	<select name="asp_product_currency" id="asp_currency"><?php echo AcceptStripePayments_Admin::get_currency_options( $current_val ); ?>></select>
	<p class = "description">Leave "(Default)" option selected if you want to use currency specified on settings page.</p>
	<?php
    }

    function display_quantity_meta_box( $post ) {
	$current_val = get_post_meta( $post->ID, 'asp_product_quantity', true );
	?>
	<input type="text" name="asp_product_quantity" value="<?php echo $current_val; ?>">
	<p class="description">Specify a custom quantity for the item.</p>
	<?php
    }

    function display_button_text_meta_box( $post ) {
	$current_val = get_post_meta( $post->ID, 'asp_product_button_text', true );
	?>
	<input type="text" name="asp_product_button_text" size="50" value="<?php echo $current_val; ?>">
	<p class="description">Specify text to be displayed on the button. Leave it blank to use button text specified on settings page.</p>
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
