<?php

class ASPProductsMetaboxes {

	protected $asp_main;
	protected $metaboxes;

	public function __construct() {
		$this->asp_main = AcceptStripePayments::get_instance();
		remove_post_type_support( ASPMain::$products_slug, 'editor' );
		add_action( 'add_meta_boxes_' . ASPMain::$products_slug, array( $this, 'add_meta_boxes' ), 0 );
		//products post save action
		add_action( 'save_post_' . ASPMain::$products_slug, array( $this, 'save_product_handler' ), 10, 3 );
	}

	public function add_meta_boxes() {
		add_meta_box( 'wsp_content', __( 'Description', 'stripe-payments' ), array( $this, 'display_description_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
		add_meta_box( 'asp_short_description_meta_box', __( 'Short Description', 'stripe-payments' ), array( $this, 'display_short_description_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
		add_meta_box( 'asp_price_meta_box', esc_html( __( 'Price & Currency', 'stripe-payments' ) ), array( $this, 'display_price_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
		add_meta_box( 'asp_variations_meta_box', esc_html( __( 'Variations', 'stripe-payments' ) ), array( $this, 'display_variations_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
		add_meta_box( 'asp_quantity_meta_box', esc_html( __( 'Quantity & Stock', 'stripe-payments' ) ), array( $this, 'display_quantity_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
		add_meta_box( 'asp_shipping_tax_meta_box', esc_html( __( 'Shipping & Tax', 'stripe-payments' ) ), array( $this, 'display_shipping_tax_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
		add_meta_box( 'asp_address_meta_box', __( 'Collect Address', 'stripe-payments' ), array( $this, 'display_address_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
		add_meta_box( 'asp_upload_meta_box', __( 'Download URL', 'stripe-payments' ), array( $this, 'display_upload_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
		add_meta_box( 'asp_thumbnail_meta_box', __( 'Product Thumbnail', 'stripe-payments' ), array( $this, 'display_thumbnail_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
		add_meta_box( 'asp_thankyou_page_meta_box', __( 'Thank You Page URL', 'stripe-payments' ), array( $this, 'display_thankyou_page_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
		add_meta_box( 'asp_appearance_meta_box', __( 'Appearance', 'stripe-payments' ), array( $this, 'display_appearance_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
		add_meta_box( 'asp_coupons_meta_box', __( 'Coupons Settings', 'stripe-payments' ), array( $this, 'display_coupons_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
		add_meta_box( 'asp_custom_field_meta_box', __( 'Custom Field', 'stripe-payments' ), array( $this, 'display_custom_field_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
		add_meta_box( 'asp_advanced_settings', __( 'Advanced Settings', 'stripe-payments' ), array( $this, 'display_advanced_settings_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
		add_meta_box( 'asp_embed_meta_box', __( 'Embed Product', 'stripe-payments' ), array( $this, 'display_embed_meta_box' ), ASPMain::$products_slug, 'side', 'default' );

		//check if eMember installed
		if ( function_exists( 'wp_eMember_install' ) ) {
			//if it is, let's add metabox where admin can select membership level
			add_meta_box( 'asp_emember_meta_box', __( 'WP eMember Membership Level', 'stripe-payments' ), array( $this, 'display_emember_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
		}

		//check if Simple Membership installed
		if ( defined( 'SIMPLE_WP_MEMBERSHIP_VER' ) ) {
			//if it is, let's add metabox where admin can select membership level
			add_meta_box( 'asp_swpm_meta_box', __( 'Simple Membership Level', 'stripe-payments' ), array( $this, 'display_swpm_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
		}

		//check if WP PDF Stamper is installed
		if ( defined( 'WP_PDF_STAMP_VERSION' ) ) {
			//if it is, let's add metabox where admin can select additional options
			add_meta_box( 'asp_pdf_stamper_meta_box', __( 'PDF Stamper Integration', 'stripe-payments' ), array( $this, 'display_pdf_stamper_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
		}

		do_action( 'asp_edit_product_metabox' );
		$new_product_edit_interface = $this->asp_main->get_setting( 'new_product_edit_interface' );
		if ( $new_product_edit_interface ) {
			global $wp_meta_boxes;
			global $post;
			$skip_metaboxes = array( 'wsp_content', 'asp_short_description_meta_box' );
			foreach ( $wp_meta_boxes[ ASPMain::$products_slug ]['normal']['default'] as $box ) {
				if ( in_array( $box['id'], $skip_metaboxes, true ) ) {
					continue;
				}
				if ( 'asp_' === substr( $box['id'], 0, 4 ) ) {
					$this->metaboxes[] = $box;
					unset( $wp_meta_boxes[ ASPMain::$products_slug ]['normal']['default'][ $box['id'] ] );
				}
			}
			add_meta_box( 'asp_product_metaboxes_meta_box', __( 'Product Options', 'stripe-payments' ), array( $this, 'display_metaboxes_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
		}
	}

	public function display_metaboxes_meta_box( $post ) {
		echo '<div id="wp-asp-product-settings-cont">';
		echo '<div class="wp-asp-product-settings-menu">';
		echo '<div id="wp-asp-product-settings-menu-icon"><span class="dashicons dashicons-menu"></span></div>';
		$first = true;
		foreach ( $this->metaboxes as $box ) {
			if ( ! is_callable( array( $box['callback'][0], $box['callback'][1] ) ) ) {
				continue;
			}
			echo wp_kses(
				sprintf( '<a class="nav-tab wp-asp-product-menu-nav-item%s" data-asp-nav-item="%s" href="#"><span>%s</span></a>', $first ? ' nav-tab-active' : '', $box['id'], $box['title'] ),
				array(
					'a'    => array(
						'class'             => array(),
						'data-asp-nav-item' => array(),
						'href'              => array(),
					),
					'span' => array(),
				)
			);
			$first = false;
		}
		echo '</div>';
		$first = true;
		foreach ( $this->metaboxes as $box ) {
			if ( ! is_callable( array( $box['callback'][0], $box['callback'][1] ) ) ) {
				continue;
			}
			echo wp_kses(
				sprintf( '<div id="%s" class="wp-asp-product-tab-item%s">', $box['id'], $first ? ' wp-asp-product-tab-item-visible' : '' ),
				array(
					'div' => array(
						'id'    => array(),
						'class' => array(),
					),
				)
			);
			echo '<span class="wp-asp-product-meta-box-title"><strong>' . esc_html( $box['title'] ) . '</strong></span>';
			echo '<hr>';
			call_user_func( array( $box['callback'][0], $box['callback'][1] ), $post );
			echo '</div>';
			$first = false;
		}
		echo '</div>';
	}

	public function display_swpm_meta_box( $post ) {
		$current_val = get_post_meta( $post->ID, 'asp_product_swpm_level', true );
		?>
<p><?php esc_html_e( 'If you want this product to be connected to a membership level then select the membership Level here.', 'stripe-payments' ); ?></p>
<select name="asp_product_swpm_level">
<option value=""><?php esc_html_e( 'None', 'stripe-payments' ); ?></option>
		<?php
		echo SwpmUtils::membership_level_dropdown( $current_val );
		?>
</select>
		<?php
	}

	public function display_emember_meta_box( $post ) {
		$current_val = get_post_meta( $post->ID, 'asp_product_emember_level', true );

		$all_levels = dbAccess::findAll( WP_EMEMBER_MEMBERSHIP_LEVEL_TABLE, ' id != 1 ', ' id DESC ' );
		$levels_str = '<option value="">(' . __( 'None', 'stripe-payments' ) . ')</option>' . "\r\n";

		foreach ( $all_levels as $level ) {
			$levels_str .= '<option value="' . $level->id . '"' . ( $level->id === $current_val ? ' selected' : '' ) . '>' . stripslashes( $level->alias ) . '</option>' . "\r\n";
		}
		?>
<p><?php esc_html_e( 'If you want this product to be connected to a membership level then select the membership Level here.', 'stripe-payments' ); ?></p>
<select name="asp_product_emember_level">
		<?php
		echo wp_kses(
			$levels_str,
			array(
				'option' => array(
					'value'    => array(),
					'selected' => array(),
				),
			)
		);
		?>
</select>
		<?php
	}

	public function display_pdf_stamper_meta_box( $post ) {
		$current_val = get_post_meta( $post->ID, 'asp_product_pdf_stamper_enabled', true );
		?>
		<label><input type="checkbox" name="asp_product_pdf_stamper_enabled" value="1"<?php echo $current_val ? ' checked' : ''; ?>> <?php echo esc_html_e( 'Stamp the PDF File', 'stripe-payments' ); ?></label>
		<p class="description">
					<?php echo esc_html_e( 'If this product is an eBook and you want to stamp this PDF file with customer details upon purchase then use this option. ', 'stripe-payments' ); ?>
					<?php echo _e( 'It requires the <a href="https://www.tipsandtricks-hq.com/wp-pdf-stamper-plugin-2332" target="_blank">WP PDF Stamper plugin</a> to be installed on this site.', 'stripe-payments' ); ?>
				</p>
		<?php
	}

	public function display_description_meta_box( $post ) {
		esc_html_e( 'Add a description for your product.', 'stripe-payments' );
		echo '<br /><br />';
		wp_editor( $post->post_content, 'content', array( 'textarea_name' => 'content' ) );
	}

	public function display_short_description_meta_box( $post ) {
		$current_val = get_post_meta( $post->ID, 'asp_product_description', true );
		?>
<input type="text" name="asp_product_description" size="50" value="<?php echo esc_attr( $current_val ); ?>">
<p class="description"><?php echo esc_html( __( 'You can optionally add a custom short description for the item/product/service that will get shown in the stripe checkout/payment window of the item.', 'stripe-payments' ) ); ?></p>
		<?php
	}

	public function display_price_meta_box( $post ) {
		$current_price    = get_post_meta( $post->ID, 'asp_product_price', true );
		$current_curr     = get_post_meta( $post->ID, 'asp_product_currency', true );
		$current_curr_var = get_post_meta( $post->ID, 'asp_product_currency_variable', true );
		do_action( 'asp_product_price_metabox_before_content', $post );
		?>
<label><?php esc_html_e( 'Price', 'stripe-payments' ); ?></label>
<br />
<input type="number" step="any" min="0" name="asp_product_price" value="<?php echo esc_attr( $current_price ); ?>">
<p class="description">
		<?php
		echo esc_html( __( 'Item price. Numbers only, no need to put currency symbol. Example: 99.95', 'stripe-payments' ) ) .
		'<br>' . esc_html( __( 'Leave it blank if you want your customers to enter the amount themselves (e.g. for donation button).', 'stripe-payments' ) );
		?>
</p>
<hr />
<label><?php esc_html_e( 'Currency', 'stripe-payments' ); ?></label>
<br />
<select name="asp_product_currency" id="asp_currency_select"><?php echo ( AcceptStripePayments_Admin::get_currency_options( $current_curr ) ); ?></select>
<p class="description"><?php esc_html_e( 'Leave "(Default)" option selected if you want to use currency specified on settings page.', 'stripe-payments' ); ?></p>
<label>
	<input type="checkbox" name="asp_product_currency_variable" value="1" <?php echo esc_attr( ! empty( $current_curr_var ) ? ' checked' : '' ); ?>> <?php esc_html_e( 'Allow customers to specify currency', 'stripe-payments' ); ?>
</label>
<p class="description"><?php esc_attr_e( 'When enabled, it allows the customers to select the currency which is used to make the payment. It does not dynamically change the price. No dynamic currency conversion takes place. So this is mainly useful for a donation type product.', 'stripe-payments' ); ?></p>
		<?php
		do_action( 'asp_product_price_metabox_after_content', $post );
	}

	public function display_variations_meta_box( $post ) {
		$price_mod_help  = __( 'Enter price modification - amount that will be added to product price if particular variation is selected.', 'stripe-payments' );
		$price_mod_help .= '<br><br>';
		$price_mod_help .= __( 'Put negative value if you want to substract the amount instead.', 'stripe-payments' );
		?>
<p><?php echo sprintf( __( 'You can find documentation on variations %s', 'stripe-payments' ), '<a href="https://s-plugins.com/creating-variable-products-using-the-stripe-payments-plugin/" target="_blank">here</a>' ); ?></p>
		<?php
		if ( class_exists( 'ASPSUB_main' ) ) {
			echo '<p>' . esc_html_e( 'Note: variations for subscription products are currently not supported.', 'stripe-payments' ) . '</p>';
		}
		$current_hide_amount_input = get_post_meta( $post->ID, 'asp_product_hide_amount_input', true );
		?>
<label>
	<input type="checkbox" name="asp_product_hide_amount_input" value="1" <?php echo esc_attr( ! empty( $current_hide_amount_input ) ? ' checked' : '' ); ?>> <?php esc_html_e( 'Use only variations to construct final product price', 'stripe-payments' ); ?>
</label>
<p class="description">
		<?php esc_html_e( 'When enabled, the total product price will be calculated by using the variation prices only. Useful if you do not want to have a base price for this product.', 'stripe-payments' ); ?>
	<br />
		<?php esc_html_e( 'Note: To enable this option, you will need to set the product price to 0.', 'stripe-payments' ); ?>
</p>
<br />
		<?php
			$variations_str    = '';
			$variations_groups = get_post_meta( $post->ID, 'asp_variations_groups', true );
			$variations_names  = get_post_meta( $post->ID, 'asp_variations_names', true );
			$variations_prices = get_post_meta( $post->ID, 'asp_variations_prices', true );
			$variations_urls   = get_post_meta( $post->ID, 'asp_variations_urls', true );
			$variations_opts   = get_post_meta( $post->ID, 'asp_variations_opts', true );
		if ( empty( $variations_groups ) ) {
			$variations_str = __( 'No variations configured for this product.', 'stripe-payments' );
		}
		?>
<div id="asp-variations-cont-main">
	<div id="asp-variations-cont">
		<span class="asp-variations-no-variations-msg"><?php echo $variations_str; ?></span>
	</div>
	<button type="button" class="button" id="asp-create-variations-group-btn"><span class="dashicons dashicons-welcome-add-page"></span> <?php esc_html_e( 'Create Group', 'stripe-payments' ); ?></button>
</div>
<div class="asp-html-tpl asp-html-tpl-variations-group">
	<div class="asp-variations-group-cont">
		<div class="asp-variations-group-title">
			<span><?php esc_html_e( 'Group Name:', 'stripe-payments' ); ?> </span>
			<input type="text" value="" class="asp-variations-group-name">
			<button type="button" class="button asp-variations-delete-group-btn asp-btn-small">
				<span class="dashicons dashicons-trash" title="<?php esc_html_e( 'Delete group', 'stripe-payments' ); ?>"></span>
			</button>
			<div class="asp-variations-display-type-cont">
				<label><?php esc_html_e( 'Display As:', 'stripe-payments' ); ?> </label>
				<select class="asp-variations-display-type">
					<option value="0"><?php esc_html_e( 'Dropdown', 'stripe-payments' ); ?></option>
					<option value="1"><?php esc_html_e( 'Radio Buttons', 'stripe-payments' ); ?></option>
				</select>
			</div>
		</div>
		<table class="widefat fixed asp-variations-tbl">
			<tr>
				<th width="40%"><?php echo esc_html( _x( 'Name', 'Variation name', 'stripe-payments' ) ); ?></th>
				<th width="20%"><?php esc_html_e( 'Price Mod', 'stripe-payments' ); ?> <?php echo ASP_Utils::gen_help_popup( $price_mod_help ); ?></th>
				<th width="30%"><?php esc_html_e( 'Product URL', 'stripe-payments' ); ?></th>
			</tr>
		</table>
		<div class="asp-variations-buttons-cont">
			<button type="button" class="button asp-variations-add-variation-btn"><span class="dashicons dashicons-plus"></span> <?php esc_html_e( 'Add Variation', 'stripe-payments' ); ?></button>
		</div>
	</div>
</div>
<table class="asp-html-tpl asp-html-tpl-variation-row">
	<tbody>
		<tr>
			<td><input type="text" value="" class="asp-variation-name"></td>
			<td><input type="text" value="" class="asp-variation-price"></td>
			<td style="position: relative;">
				<input type="text" value="" class="asp-variation-url">
				<button type="button" class="button asp-variations-select-from-ml-btn asp-btn-small"><span class="dashicons  dashicons-admin-media" title="<?php echo esc_attr( __( 'Select from Media Library', 'stripe-payments' ) ); ?>"></span></button>
			</td>
			<td>
				<button type="button" class="button asp-variations-delete-variation-btn asp-btn-small"><span class="dashicons dashicons-trash" title="<?php echo esc_attr( __( 'Delete variation', 'stripe-payments' ) ); ?>"></span></button>
			</td>
		</tr>
	</tbody>
</table>
		<?php
			wp_localize_script(
				'asp-admin-edit-product-js',
				'aspEditProdData',
				array(
					'varGroups' => ! empty( $variations_groups ) ? $variations_groups : '',
					'varNames'  => $variations_names,
					'varPrices' => $variations_prices,
					'varUrls'   => $variations_urls,
					'varOpts'   => $variations_opts,
					'str'       => array(
						'groupDeleteConfirm' => __( 'Are you sure you want to delete this group?', 'stripe-payments' ),
						'varDeleteConfirm'   => __( 'Are you sure you want to delete this variation?', 'stripe-payments' ),
					),
				)
			);
			wp_enqueue_script( 'asp-admin-edit-product-js' );
	}

	public function display_shipping_tax_meta_box( $post ) {
		$current_shipping = get_post_meta( $post->ID, 'asp_product_shipping', true );
		$current_tax      = get_post_meta( $post->ID, 'asp_product_tax', true );
		?>
<div id="asp_shipping_cost_container">
	<label><?php esc_html_e( 'Shipping Cost', 'stripe-payments' ); ?></label>
	<br />
	<input type="number" step="any" min="0" name="asp_product_shipping" value="<?php echo esc_attr( $current_shipping ); ?>">
	<p class="description">
		<?php
		esc_html_e( 'Numbers only, no need to put currency symbol. Example: 5.90', 'stripe-payments' );
		echo '<br>';
		esc_html_e( 'Leave it blank if you are not shipping your product or not charging additional shipping costs.', 'stripe-payments' );
		?>
	</p>
<hr />
</div>
<label><?php esc_html_e( 'Tax (%)', 'stripe-payments' ); ?></label>
<br />
<input type="number" step="any" min="0" name="asp_product_tax" value="<?php echo esc_attr( $current_tax ); ?>"><span>%</span>
<p class="description">
		<?php
		esc_html_e( 'Enter tax (in percent) which should be added to product price during purchase.', 'stripe-payments' );
		echo '<br>';
		esc_html_e( 'Leave it blank if you don\'t want to apply tax.', 'stripe-payments' );
		?>
</p>
		<?php
	}

	public function display_quantity_meta_box( $post ) {
		$current_val           = get_post_meta( $post->ID, 'asp_product_quantity', true );
		$allow_custom_quantity = get_post_meta( $post->ID, 'asp_product_custom_quantity', true );
		$enable_stock          = get_post_meta( $post->ID, 'asp_product_enable_stock', true );
		$stock_items           = get_post_meta( $post->ID, 'asp_product_stock_items', true );
		?>
<p><?php esc_html_e( 'By default, if you leave this field empty, the product quantity will be set to 1. You can change this behavior by using the following options.', 'stripe-payments' ); ?></p>

<label>
	<input type="checkbox" name="asp_product_custom_quantity" value="1" <?php echo esc_attr( '1' === $allow_custom_quantity ? ' checked' : '' ); ?>>
		<?php echo esc_html( __( 'Allow users to specify quantity', 'stripe-payments' ) ); ?>
</label>
<p class="description"><?php echo esc_html( __( 'When checked, users can enter the quantity they want to buy.', 'stripe-payments' ) ); ?></p>

<label><?php esc_html_e( 'Set Quantity:', 'stripe-payments' ); ?></label>
	<br />
	<input type="number" min="1" step="1" name="asp_product_quantity" value="<?php echo esc_attr( $current_val ); ?>">
	<p class="description"><?php esc_html_e( 'If you want to use a set quantity for this item then enter the value in this field.', 'stripe-payments' ); ?></p>

<hr />

<label>
	<input type="checkbox" name="asp_product_enable_stock" value="1" <?php echo esc_attr( ( '1' === $enable_stock ) ? ' checked' : '' ); ?>>
		<?php esc_html_e( 'Enable stock control', 'stripe-payments' ); ?>
</label>
<p class="description"><?php esc_html_e( 'When enabled, you can specify the quantity available for this product. It will be decreased each time the item is purchased. When stock reaches zero, an "Out of stock" message will be displayed instead of the buy button.', 'stripe-payments' ); ?></p>

<label><?php esc_html_e( 'Quantity Available:', 'stripe-payments' ); ?>	</label>
	<br />
	<input type="number" min="0" step="1" name="asp_product_stock_items" value="<?php echo esc_attr( ! $stock_items ? 0 : $stock_items ); ?>">
	<p class="description"><?php esc_html_e( 'Specify the quantity available for this product.', 'stripe-payments' ); ?></p>

		<?php
	}

	public function display_upload_meta_box( $post ) {
		$current_val = get_post_meta( $post->ID, 'asp_product_upload', true );
		?>
<p><?php esc_html_e( 'URL of your product (if you\'re selling digital products).', 'stripe-payments' ); ?></p>

<div>
	<input id="asp_product_upload" type="text" style="width: 100%" name="asp_product_upload" value="<?php echo esc_attr( $current_val ); ?>" placeholder="https://..." />

	<p class="description">
		<?php esc_html_e( 'Manually enter a valid URL of the file in the text box below, or click "Select File" button to upload (or choose) the downloadable file.', 'stripe-payments' ); ?>
	</p>
</div>
<p>
	<input id="asp_select_upload_btn" type="button" class="button" value="<?php esc_attr_e( 'Select File', 'stripe-payments' ); ?>" />
		<?php do_action( 'asp_product_upload_metabox_after_button', $post ); ?>
</p>
<div>
		<?php esc_html_e( 'Steps to upload a file or choose one from your media library:', 'stripe-payments' ); ?>
	<ol>
		<li><?php esc_html_e( 'Hit the "Select File" button.', 'stripe-payments' ); ?></li>
		<li><?php esc_html_e( 'Upload a new file or choose an existing one from your media library.', 'stripe-payments' ); ?></li>
		<li><?php esc_html_e( 'Click the "Insert" button, this will populate the uploaded file\'s URL in the above text field.', 'stripe-payments' ); ?></li>
	</ol>
</div>
<script>
jQuery(document).ready(function($) {
	var asp_selectFileFrame;
	// Run media uploader for file upload
	$('#asp_select_upload_btn').click(function(e) {
		e.preventDefault();
		asp_selectFileFrame = wp.media({
			title: "<?php esc_html_e( 'Select File', 'stripe-payments' ); ?>",
			button: {
				text: "<?php esc_html_e( 'Insert', 'stripe-payments' ); ?>"
			},
			multiple: false
		});
		asp_selectFileFrame.open();
		asp_selectFileFrame.on('select', function() {
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
		$current_val            = get_post_meta( $post->ID, 'asp_product_thumbnail', true );
		$current_no_popup_thumb = get_post_meta( $post->ID, 'asp_product_no_popup_thumbnail', true );
		?>
<div>
	<input id="asp_product_thumbnail" type="text" style="width: 100%" name="asp_product_thumbnail" value="<?php echo esc_attr( $current_val ); ?>" placeholder="https://..." />

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
<label><input type="checkbox" name="asp_product_no_popup_thumbnail" value="1" <?php echo ( $current_no_popup_thumb === '1' ) ? ' checked' : ''; ?> /> <?php _e( "Don't use product thumbnail in Stripe pop-up", 'stripe-payments' ); ?></label>
<p class="description">
		<?php _e( 'Use this checkbox if you do not want to show the product thumbnail in Stripe checkout popup.', 'stripe-payments' ); ?>
</p>
<script>
jQuery(document).ready(function($) {
	var asp_selectFileFrame;
	$('#asp_select_thumbnail_btn').click(function(e) {
		e.preventDefault();
		asp_selectFileFrame = wp.media({
			title: "<?php echo __( 'Select Image', 'stripe-payments' ); ?>",
			button: {
				text: "<?php echo __( 'Insert', 'stripe-payments' ); ?>",
			},
			multiple: false,
			library: {
				type: 'image'
			},
		});
		asp_selectFileFrame.open();
		asp_selectFileFrame.on('select', function() {
			var attachment = asp_selectFileFrame.state().get('selection').first().toJSON();
			$('#asp_thumbnail_image').remove();
			$('#asp_admin_thumb_preview').html('<img id="asp_thumbnail_image" src="' + attachment.url + '" style="max-width:200px;" />');
			$('#asp_product_thumbnail').val(attachment.url);
		});
		return false;
	});
	$('#asp_remove_thumbnail_button').click(function(e) {
		e.preventDefault();
		$('#asp_thumbnail_image').remove();
		$('#asp_product_thumbnail').val('');
	});
});
</script>
		<?php
	}

	public function display_address_meta_box( $post ) {
		$collect_billing_addr  = get_post_meta( $post->ID, 'asp_product_collect_billing_addr', true );
		$collect_shipping_addr = get_post_meta( $post->ID, 'asp_product_collect_shipping_addr', true );
		?>
<label><input type="checkbox" name="asp_product_collect_billing_addr" value="1" <?php echo ( $collect_billing_addr === '1' ) ? ' checked' : ''; ?>><?php echo __( 'Collect Address on Checkout', 'stripe-payments' ); ?> </label>
<p class="description"><?php echo __( 'Enable this to collect customer address on checkout.', 'stripe-payments' ); ?></p>
<div style="margin-left:30px;">
	<label><input type="radio" name="asp_product_collect_shipping_addr" data-addr-radio="1" value="1"
		<?php
		echo ( $collect_shipping_addr === '1' || $collect_shipping_addr === '' ) ? ' checked' : '';
		echo ! $collect_billing_addr ? ' disabled' : '';
		?>
		><?php echo __( 'Collect Both Billing And Shipping Addresses', 'stripe-payments' ); ?> </label>
	<p></p>
	<label><input type="radio" name="asp_product_collect_shipping_addr" data-addr-radio="1" value="0"
		<?php
		echo ( $collect_shipping_addr === '0' ) ? ' checked' : '';
		echo ! $collect_billing_addr ? ' disabled' : '';
		?>
		><?php echo __( 'Collect Billing Address Only', 'stripe-payments' ); ?> </label>
</div>
		<?php
	}

	public function display_thankyou_page_meta_box( $post ) {
		$current_val = get_post_meta( $post->ID, 'asp_product_thankyou_page', true );
		?>
<input type="text" name="asp_product_thankyou_page" style="width: 100%;" value="<?php echo ! empty( $current_val ) ? $current_val : ''; ?>">
<p class="description"><?php _e( 'Enter Thank You page URL. Leave it blank if you want ot use default Thank You page.', 'stripe-payments' ); ?>
	<br />
		<?php _e( 'You can read how to customize messages on Thank You page <a href="https://s-plugins.com/customize-the-thank-page-message-of-stripe-payments-plugin/" target="_blank">in the documentation</a>.', 'stripe-payments' ); ?>
</p>
		<?php
	}

	public function display_appearance_meta_box( $post ) {
		$button_txt   = get_post_meta( $post->ID, 'asp_product_button_text', true );
		$button_class = get_post_meta( $post->ID, 'asp_product_button_class', true );
		$button_only  = get_post_meta( $post->ID, 'asp_product_button_only', true );

		$show_your_order = get_post_meta( $post->ID, 'asp_product_show_your_order', true );
		?>
<fieldset>
	<legend><?php esc_html_e( 'Button Options', 'stripe-payments' ); ?></legend>
	<label><?php _e( 'Button Text', 'stripe-payments' ); ?></label>
	<br />
	<input type="text" name="asp_product_button_text" size="50" value="<?php echo esc_attr( $button_txt ); ?>">
	<p class="description"><?php _e( 'Specify text to be displayed on the button. Leave it blank to use button text specified on settings page.', 'stripe-payments' ); ?></p>
	<label><?php _e( 'Button CSS Class', 'stripe-payments' ); ?></label>
	<br />
	<input type="text" name="asp_product_button_class" size="50" value="<?php echo esc_attr( $button_class ); ?>">
	<p class="description"><?php _e( 'CSS class to be assigned to the button. This is used for styling purposes. You can get additional information <a href="https://s-plugins.com/customize-stripe-payment-button-appearance-using-css/" target="_blank">in this tutorial</a>.', 'stripe-payments' ); ?></p>
	<label><input type="checkbox" name="asp_product_button_only" value="1" <?php echo ( $button_only == 1 ) ? ' checked' : ''; ?>> <?php _e( 'Show Button Only', 'stripe-payments' ); ?></label>
	<p class="description"><?php _e( 'Check this box if you just want to show the button only without any additional product info.', 'stripe-payments' ); ?></p>
</fieldset>
<fieldset>
	<legend><?php esc_html_e( 'Payment Popup Options', 'stripe-payments' ); ?></legend>
	<label><input type="checkbox" name="asp_product_show_your_order" value="1" <?php echo $show_your_order ? ' checked' : ''; ?>> <?php esc_html_e( 'Show Order Total On Payment Popup', 'stripe-payments' ); ?></label>
	<p class="description"><?php _e( 'If enabled, an additional "Your order" section with itemized product info (shipping, tax amount, variations etc) will be displayed on payment popup.', 'stripe-payments' ); ?></p>
</fieldset>

		<?php
	}

	public function display_custom_field_meta_box( $post ) {
		$current_val = get_post_meta( $post->ID, 'asp_product_custom_field', true );

		$show_custom_field_settings = '';
		$field_name                 = $this->asp_main->get_setting( 'custom_field_enabled' );
		if ( ! empty( $field_name ) ) {//Custom field configured so show product specific settings
			$show_custom_field_settings = '1';
		}
		$show_custom_field_settings = apply_filters( 'asp_show_product_custom_field_settings', $show_custom_field_settings ); //Filter to allow addon to override this
		if ( empty( $show_custom_field_settings ) ) {
			//Custom field isn't configured. Don't show the settings
			_e( 'Custom field is disabled. Configure custom field in the settings menu of this plugin to enable it.', 'stripe-payments' );
			return;
		}
		?>
<p><?php _e( 'Select how Custom Field display should be handled for this product.', 'stripe-payments' ); ?></p>
<label><input type="radio" name="asp_product_custom_field" value="2" <?php echo ( $current_val === '2' || $current_val === '' ) ? ' checked' : ''; ?>><?php echo __( 'Use Global Setting', 'stripe-payments' ); ?> </label>
<label><input type="radio" name="asp_product_custom_field" value="1" <?php echo ( $current_val === '1' ) ? ' checked' : ''; ?>><?php echo __( 'Enabled', 'stripe-payments' ); ?> </label>
<label><input type="radio" name="asp_product_custom_field" value="0" <?php echo ( $current_val === '0' ) ? ' checked' : ''; ?>><?php echo __( 'Disabled', 'stripe-payments' ); ?> </label>
		<?php
		do_action( 'asp_product_custom_field_metabox_after', $post->ID );
	}

	public function display_coupons_meta_box( $post ) {
		$current_val = get_post_meta( $post->ID, 'asp_product_coupons_setting', true );
		?>
<p><?php _e( 'Select how Coupons should be handled for this product.', 'stripe-payments' ); ?></p>
<label><input type="radio" name="asp_product_coupons_setting" value="2" <?php echo ( $current_val === '2' || $current_val === '' ) ? ' checked' : ''; ?>><?php echo __( 'Use Global Setting', 'stripe-payments' ); ?> </label>
<label><input type="radio" name="asp_product_coupons_setting" value="1" <?php echo ( $current_val === '1' ) ? ' checked' : ''; ?>><?php echo __( 'Enabled', 'stripe-payments' ); ?> </label>
<label><input type="radio" name="asp_product_coupons_setting" value="0" <?php echo ( $current_val === '0' ) ? ' checked' : ''; ?>><?php echo __( 'Disabled', 'stripe-payments' ); ?> </label>
		<?php
	}

	public function display_advanced_settings_meta_box( $post ) {
		$current_val = get_post_meta( $post->ID, 'asp_product_force_test_mode', true );
		?>
		<label><input type="checkbox" name="asp_product_force_test_mode" value="1"<?php echo $current_val ? ' checked' : ''; ?>> <?php echo esc_html_e( 'Force Test Mode', 'stripe-payments' ); ?></label>
		<p class="description"><?php echo esc_html_e( 'When checked, product stays in test mode regardless of the global "Live Mode" switch.', 'stripe-payments' ); ?></p>
		<?php
	}

	public function display_embed_meta_box( $post ) {
		$home_url = get_home_url( null, '/' );

		$embed_url = add_query_arg(
			array(
				'asp_action' => 'show_pp',
				'product_id' => $post->ID,
			),
			$home_url
		);
		$css_class = sprintf( 'asp-attach-product-%d', $post->ID );
		?>
<fieldset>
	<legend><?php echo esc_html( __( 'Shortcode', 'stripe-payments' ) ); ?></legend>
	<input type="text" name="asp_product_shortcode" style="width: 100%;" class="asp-select-on-click" readonly value="[asp_product id=&quot;<?php echo esc_attr( $post->ID ); ?>&quot;]">
	<p class="description"><?php echo esc_html( __( 'Use this shortcode to display this product.', 'stripe-payments' ) ); ?> Usage instructions <a href="https://s-plugins.com/embedding-products-post-page/" target="_blank">here</a>.</p>
</fieldset>
<fieldset>
	<legend><?php echo esc_html( __( 'CSS Class', 'stripe-payments' ) ); ?></legend>
	<input type="text" style="width: 100%;" class="asp-select-on-click" readonly value="<?php echo esc_attr( $css_class ); ?>">
	<p class="description"><?php echo esc_html( __( 'Attach this product to any html element by adding this CSS class to it.', 'stripe-payments' ) ); ?></p>
</fieldset>
<fieldset>
	<legend><?php echo esc_html( __( 'Link URL', 'stripe-payments' ) ); ?></legend>
	<textarea class="asp-select-on-click" style="width: 100%;word-break: break-all;" rows="3" readonly><?php echo esc_html( $embed_url ); ?></textarea>
	<p class="description"><?php echo esc_html( __( 'Use this URL to create a custom payment button using a text or image link.', 'stripe-payments' ) ); ?></p>
</fieldset>
		<?php
	}

	public function save_product_handler( $post_id, $post, $update ) {
		$action = filter_input( INPUT_POST, 'action', FILTER_SANITIZE_STRING );

		if ( empty( $action ) ) {
			//this is probably not edit or new post creation event
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( isset( $post_id ) ) {
			//regen ckey
			ASP_Utils::get_ckey( true );

			$title = get_the_title( $post_id );
			if ( empty( $title ) ) {
				//Display error message of product name is empty
				$text = __( 'Please specify product name.', 'stripe-payments' );
				AcceptStripePayments_Admin::add_admin_notice( 'error', $text, false );
			}
			$currency = filter_input( INPUT_POST, 'asp_product_currency', FILTER_SANITIZE_STRING );
			update_post_meta( $post_id, 'asp_product_currency', sanitize_text_field( $currency ) );

			$shipping = filter_input( INPUT_POST, 'asp_product_shipping', FILTER_SANITIZE_STRING );
			$shipping = ! empty( $shipping ) ? AcceptStripePayments::tofloat( $shipping ) : $shipping;
			update_post_meta( $post_id, 'asp_product_shipping', $shipping );

			$tax = filter_input( INPUT_POST, 'asp_product_tax', FILTER_SANITIZE_STRING );
			$tax = floatval( $tax );
			$tax = empty( $tax ) ? '' : $tax;
			update_post_meta( $post_id, 'asp_product_tax', $tax );

			$quantity = filter_input( INPUT_POST, 'asp_product_quantity', FILTER_SANITIZE_NUMBER_INT );
			$quantity = empty( $quantity ) ? '' : $quantity;
			update_post_meta( $post_id, 'asp_product_quantity', $quantity );

			update_post_meta( $post_id, 'asp_product_custom_quantity', isset( $_POST['asp_product_custom_quantity'] ) ? '1' : false );
			update_post_meta( $post_id, 'asp_product_enable_stock', isset( $_POST['asp_product_enable_stock'] ) ? '1' : false );
			update_post_meta( $post_id, 'asp_product_stock_items', sanitize_text_field( absint( $_POST['asp_product_stock_items'] ) ) );

			$force_test_mode = filter_input( INPUT_POST, 'asp_product_force_test_mode', FILTER_SANITIZE_STRING );
			$force_test_mode = ! empty( $force_test_mode ) ? true : false;
			update_post_meta( $post_id, 'asp_product_force_test_mode', $force_test_mode );

			$pdf_stamper_enabled = filter_input( INPUT_POST, 'asp_product_pdf_stamper_enabled', FILTER_SANITIZE_STRING );
			$pdf_stamper_enabled = ! empty( $pdf_stamper_enabled ) ? true : false;
			update_post_meta( $post_id, 'asp_product_pdf_stamper_enabled', $pdf_stamper_enabled );

			update_post_meta( $post_id, 'asp_product_coupons_setting', isset( $_POST['asp_product_coupons_setting'] ) ? sanitize_text_field( $_POST['asp_product_coupons_setting'] ) : '0' );
			update_post_meta( $post_id, 'asp_product_custom_field', isset( $_POST['asp_product_custom_field'] ) ? sanitize_text_field( $_POST['asp_product_custom_field'] ) : '0' );
			update_post_meta( $post_id, 'asp_product_button_text', sanitize_text_field( $_POST['asp_product_button_text'] ) );
			update_post_meta( $post_id, 'asp_product_button_class', sanitize_text_field( $_POST['asp_product_button_class'] ) );
			update_post_meta( $post_id, 'asp_product_button_only', isset( $_POST['asp_product_button_only'] ) ? 1 : 0 );
			update_post_meta( $post_id, 'asp_product_show_your_order', isset( $_POST['asp_product_show_your_order'] ) ? 1 : 0 );
			update_post_meta( $post_id, 'asp_product_description', sanitize_text_field( $_POST['asp_product_description'] ) );
			update_post_meta( $post_id, 'asp_product_upload', esc_url( $_POST['asp_product_upload'], array( 'http', 'https', 'dropbox' ) ) );

			$thumb_url_raw = filter_input( INPUT_POST, 'asp_product_thumbnail', FILTER_SANITIZE_URL );
			$thumb_url     = esc_url( $thumb_url_raw, array( 'http', 'https' ) );

			if ( ! empty( $thumb_url ) ) {
				$curr_thumb  = get_post_meta( $post_id, 'asp_product_thumbnail', true );
				$force_regen = $thumb_url === $curr_thumb ? false : true;
				update_post_meta( $post_id, 'asp_product_thumbnail', $thumb_url );
				//generate small 100x100 thumbnail
				ASP_Utils::get_small_product_thumb( $post_id, $force_regen );
			} else {
				//thumbnail is removed
				update_post_meta( $post_id, 'asp_product_thumbnail', '' );
				update_post_meta( $post_id, 'asp_product_thumbnail_thumb', '' );
			}

			update_post_meta( $post_id, 'asp_product_no_popup_thumbnail', isset( $_POST['asp_product_no_popup_thumbnail'] ) ? '1' : false );
			update_post_meta( $post_id, 'asp_product_thankyou_page', isset( $_POST['asp_product_thankyou_page'] ) && ! empty( $_POST['asp_product_thankyou_page'] ) ? esc_url( $_POST['asp_product_thankyou_page'] ) : '' );
			$shipping_addr = false;

			if ( isset( $_POST['asp_product_collect_shipping_addr'] ) ) {
				$shipping_addr = $_POST['asp_product_collect_shipping_addr'];
			}

			update_post_meta( $post_id, 'asp_product_collect_shipping_addr', $shipping_addr );
			update_post_meta( $post_id, 'asp_product_collect_billing_addr', isset( $_POST['asp_product_collect_billing_addr'] ) ? '1' : false );
			update_post_meta( $post_id, 'asp_product_emember_level', ! empty( $_POST['asp_product_emember_level'] ) ? intval( $_POST['asp_product_emember_level'] ) : '' );
			update_post_meta( $post_id, 'asp_product_swpm_level', ! empty( $_POST['asp_product_swpm_level'] ) ? intval( $_POST['asp_product_swpm_level'] ) : '' );

			do_action( 'asp_save_product_handler', $post_id, $post, $update );

			//check if this is not subscription product
			$asp_plan_id = get_post_meta( $post_id, 'asp_sub_plan_id', true );

			if ( empty( $asp_plan_id ) ) {

				//handle variations
				$variations_groups = filter_input( INPUT_POST, 'asp-variations-group-names', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY );
				if ( ! empty( $variations_groups ) && is_array( $variations_groups ) ) {
					//we got variations groups. Let's process them
					update_post_meta( $post_id, 'asp_variations_groups', $variations_groups );
					$variations_names = filter_input( INPUT_POST, 'asp-variation-names', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY );
					update_post_meta( $post_id, 'asp_variations_names', $variations_names );
					$variations_prices = filter_input( INPUT_POST, 'asp-variation-prices', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY );
					update_post_meta( $post_id, 'asp_variations_prices', $variations_prices );
					$variations_urls = filter_input( INPUT_POST, 'asp-variation-urls', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY );
					update_post_meta( $post_id, 'asp_variations_urls', $variations_urls );
					$variations_opts = filter_input( INPUT_POST, 'asp-variations-opts', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY );
					update_post_meta( $post_id, 'asp_variations_opts', $variations_opts );
				} else {
					//we got no variations groups. Let's clear meta values
					update_post_meta( $post_id, 'asp_variations_groups', false );
					update_post_meta( $post_id, 'asp_variations_names', false );
					update_post_meta( $post_id, 'asp_variations_prices', false );
					update_post_meta( $post_id, 'asp_variations_urls', false );
					update_post_meta( $post_id, 'asp_variations_opts', false );
				}

				$currency_variable = filter_input( INPUT_POST, 'asp_product_currency_variable', FILTER_SANITIZE_STRING );
				$currency_variable = ! empty( $currency_variable ) ? true : false;
				update_post_meta( $post_id, 'asp_product_currency_variable', $currency_variable );

				$hide_amount_input = filter_input( INPUT_POST, 'asp_product_hide_amount_input', FILTER_SANITIZE_STRING );
				$hide_amount_input = ! empty( $hide_amount_input ) ? true : false;
				update_post_meta( $post_id, 'asp_product_hide_amount_input', $hide_amount_input );

				//check if price is in min-max range for the currency set by Stripe: https://stripe.com/docs/currencies#minimum-and-maximum-charge-amounts
				$price    = sanitize_text_field( $_POST['asp_product_price'] );
				$price    = AcceptStripePayments::tofloat( $price );
				$currency = sanitize_text_field( $_POST['asp_product_currency'] );
				if ( ! empty( $price ) ) {
					$price_cents = AcceptStripePayments::is_zero_cents( $currency ) ? round( $price ) : round( $price * 100 );
					//check if we have currency set
					if ( empty( $currency ) ) {
						//we have not. This means default currency should be used
						$currency = $this->asp_main->get_setting( 'currency_code' );
					}
					$currency = strtoupper( $currency );
					//let's see if currency has specific minimum set
					if ( isset( $this->asp_main->minAmounts[ $currency ] ) ) {
						//check if price < minAmount
						if ( $price_cents < $this->asp_main->minAmounts[ $currency ] ) {
							// it is. Let's add error message
							$text = sprintf( __( '<b>Invalid product price</b>: minimum price in %1$s should be %2$s, you specified %3$s. This price limitation comes from Stripe.', 'stripe-payments' ), $currency, AcceptStripePayments::formatted_price( $this->asp_main->minAmounts[ $currency ], $currency, true ), AcceptStripePayments::formatted_price( $price_cents, $currency, true ) );
							AcceptStripePayments_Admin::add_admin_notice( 'error', $text, false );
							// we don't save invalid price
							return false;
						}
					}
					//check if value is not above maximum allowed by Stripe (8 digits; e.g. 99999999 in cents)
					if ( $price_cents > 99999999 ) {
						// it is. Let's add error message
						$text = sprintf( __( '<b>Invalid product price</b>: maximum allowed product price is %1$s, you specified %2$s', 'stripe-payments' ), $this->asp_main->formatted_price( 99999999, $currency, true ), AcceptStripePayments::formatted_price( $price_cents, $currency, true ) );
						AcceptStripePayments_Admin::add_admin_notice( 'error', $text, false );
						// we don't save invalid price
						return false;
					}
				}
				//price seems to be valid, let's save it
				update_post_meta( $post_id, 'asp_product_price', $price );
			}
		}
	}

}

new ASPProductsMetaboxes();
