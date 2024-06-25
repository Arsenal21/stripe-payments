<?php

class ASP_Admin_Product_Meta_Boxes {

	protected $asp_main;
	protected $metaboxes;

	public function __construct() {
		$this->asp_main = AcceptStripePayments::get_instance();
		remove_post_type_support( ASPMain::$products_slug, 'editor' );
		add_action( 'add_meta_boxes_' . ASPMain::$products_slug, array( $this, 'add_meta_boxes' ), 0 );
		//products post save action
		add_action( 'save_post_' . ASPMain::$products_slug, array( $this, 'save_product_handler' ), 10, 3 );

		add_action( 'admin_footer', array( $this, 'admin_footer' ) );
	}

	public function admin_footer() {
		wp_enqueue_script( 'asp-admin-edit-product-js' );
	}

	public function add_meta_boxes() {
		add_meta_box( 'wsp_content', __( 'Description', 'stripe-payments' ), array( $this, 'display_description_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
		add_meta_box( 'asp_short_description_meta_box', __( 'Short Description', 'stripe-payments' ), array( $this, 'display_short_description_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
		if ( class_exists( 'ASPSUB_main' ) && version_compare( ASPSUB_main::ADDON_VER, '2.0.16' ) <= 0 ) {
			add_meta_box( 'asp_price_meta_box', esc_html( __( 'Price & Currency', 'stripe-payments' ) ), array( $this, 'display_price_meta_box_deprecated' ), ASPMain::$products_slug, 'normal', 'default' );
		} else {
			add_meta_box( 'asp_price_meta_box', esc_html( __( 'Price & Currency', 'stripe-payments' ) ), array( $this, 'display_price_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
		}
		add_meta_box( 'asp_variations_meta_box', esc_html( __( 'Variations', 'stripe-payments' ) ), array( $this, 'display_variations_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
		add_meta_box( 'asp_quantity_meta_box', esc_html( __( 'Quantity & Stock', 'stripe-payments' ) ), array( $this, 'display_quantity_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
		add_meta_box( 'asp_shipping_tax_meta_box', esc_html( __( 'Shipping & Tax', 'stripe-payments' ) ), array( $this, 'display_shipping_tax_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
		add_meta_box( 'asp_surcharge_meta_box', esc_html( __( 'Transaction Surcharge (Beta)', 'stripe-payments' ) ), array( $this, 'display_surcharge_meta_box' ), ASPMain::$products_slug, 'normal', 'default' );
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
		$min_amount       = get_post_meta( $post->ID, 'asp_product_min_amount', true );

		$min_amount        = empty( $min_amount ) ? 0 : floatval( $min_amount );
		$post_status       = get_post_status( $post );
		$hide_amount_input = get_post_meta( $post->ID, 'asp_product_hide_amount_input', true );

		$product_types             = array();
		$product_types['one_time'] = __( 'One-time payment' );
		$product_types['donation'] = __( 'Donation' );

		$product_types = apply_filters( 'asp_product_edit_product_types', $product_types, $post );

		$product_type = get_post_meta( $post->ID, 'asp_product_type', true );

		$product_type = apply_filters( 'asp_product_edit_product_type_selected', $product_type, $post );

		if ( ! isset( $product_types[ $product_type ] ) || empty( $product_type ) ) {
			$product_type = 'one_time';
			if ( 'auto-draft' !== $post_status && empty( $current_price ) && ! $hide_amount_input ) {
				$product_type = 'donation';
			}
		}

		$cont = '';

		echo '<p class="asp_product_type_select_cont">';

		foreach ( $product_types as $type => $name ) {
			?>
		<label>
		<input type="radio" class="asp_product_type_radio" name="asp_product_type_radio" value="<?php echo $type; ?>"<?php echo $type === $product_type ? ' checked' : ''; ?>><?php echo $name; ?>
		</label>
			<?php
			$cont .= sprintf( '<div class="asp_product_type_cont%s" data-asp-product-type="%s">', $type === $product_type ? ' asp_product_type_active' : '', $type );
			ob_start();
			switch ( $type ) {
				case 'one_time':
					?>
		<label><?php esc_html_e( 'Price', 'stripe-payments' ); ?></label>
		<br />
		<input type="number" step="any" min="0" name="asp_product_price" value="<?php echo esc_attr( $current_price ); ?>">
		<p class="description">
					<?php
					echo esc_html( __( 'Item price. Numbers only, no need to put currency symbol. Example: 99.95', 'stripe-payments' ) );
					?>
		</p>
		<hr />
		<div class="asp_product_currency_sel_location">
			<div class="asp_product_currency_sel">
		<label><?php esc_html_e( 'Currency', 'stripe-payments' ); ?></label>
		<br />
		<select name="asp_product_currency" id="asp_currency_select"><?php echo ( AcceptStripePayments_Admin::get_currency_options( $current_curr ) ); ?></select>
		<p class="description"><?php esc_html_e( 'Leave "(Default)" option selected if you want to use currency specified on settings page.', 'stripe-payments' ); ?></p>
				</div>
				</div>
					<?php
					break;
				case 'donation':
					?>
		<label><?php esc_html_e( 'Minimum Donation Amount', 'stripe-payments' ); ?></label>
		<br />
		<input type="number" step="0.01" min="0" name="asp_product_min_amount" value="<?php echo esc_attr( $min_amount ); ?>">
		<p class="description">
					<?php
					echo esc_html( __( 'Specify a minimum donation amount.', 'stripe-payments' ) ) .
					// translators: %1$s and %2$s are replaced by <a></a> tags
					' ' . sprintf( __( 'If set to 0 then %1$s Stripe\'s minimum amount limit%2$s will be applied.', 'stripe-payments' ), '<a href="https://stripe.com/docs/currencies#minimum-and-maximum-charge-amounts" target="_blank">', '</a>' );
					?>
		</p>
		<hr />
		<div class="asp_product_currency_sel_location"></div>
		<label>
			<input type="checkbox" name="asp_product_currency_variable" value="1" <?php echo esc_attr( ! empty( $current_curr_var ) ? ' checked' : '' ); ?>> <?php esc_html_e( 'Allow customers to specify currency', 'stripe-payments' ); ?>
		</label>
		<p class="description"><?php esc_attr_e( 'When enabled, it allows the customers to select the currency which is used to make the payment. It does not dynamically change the price. No dynamic currency conversion takes place.', 'stripe-payments' ); ?></p>
					<?php
					break;
				default:
					do_action( 'asp_product_edit_output_product_type_' . $type, $post );
					break;
			}
			$cont .= ob_get_clean();
			$cont .= '</div>';
		}
		echo '</p>';
                $allowed_tags = ASP_Utils::asp_allowed_tags();
		echo wp_kses( $cont, $allowed_tags );
		?>
		<script>
			jQuery('.asp_product_type_radio').change(function(e) {
				jQuery('.asp_product_type_cont').removeClass('asp_product_type_active');
				if (jQuery('.asp_product_type_cont[data-asp-product-type="'+jQuery(this).val()+'"]').find('.asp_product_currency_sel_location').length !== 0) {
					jQuery('.asp_product_currency_sel').appendTo(jQuery('.asp_product_type_cont[data-asp-product-type="'+jQuery(this).val()+'"]').find('.asp_product_currency_sel_location'));
				}
				jQuery('.asp_product_type_cont[data-asp-product-type="'+jQuery(this).val()+'"]').addClass('asp_product_type_active');
			});
			jQuery('.asp_product_type_radio:checked').trigger('change');
			</script>
		<?php
	}

	public function display_price_meta_box_deprecated( $post ) {
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
<p>
		<?php
		// translators: %s is a link to documentation page
		echo sprintf( __( 'You can find documentation on variations <a href="%s" target="_blank">here</a>.', 'stripe-payments' ), 'https://s-plugins.com/creating-variable-products-using-the-stripe-payments-plugin' );
		?>
</p>
		<?php
		if ( class_exists( 'ASPSUB_main' ) ) {
			echo '<p>' . esc_html_e( 'Note: variations for subscription products are currently not supported.', 'stripe-payments' ) . '</p>';
		}
		$current_hide_amount_input = get_post_meta( $post->ID, 'asp_product_hide_amount_input', true );
		?>
<style>
input[type=checkbox][disabled] + label {
	color: rgba(44,51,56,.5);
}
</style>
	<input id="asp-product-hide-amount-input" type="checkbox" name="asp_product_hide_amount_input" value="1" <?php echo esc_attr( ! empty( $current_hide_amount_input ) ? ' checked' : '' ); ?>>
	<label for="asp-product-hide-amount-input"> <?php esc_html_e( 'Use variations only to construct final product price', 'stripe-payments' ); ?></label>
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
			<label><?php esc_html_e( 'Group Name:', 'stripe-payments' ); ?> </label>
			<input type="text" value="" class="asp-variations-group-name">
			<button type="button" class="button asp-variations-delete-group-btn asp-btn-small">
				<span class="dashicons dashicons-trash" title="<?php esc_html_e( 'Delete group', 'stripe-payments' ); ?>"></span>
			</button>
			<div class="asp-variations-display-type-cont">
				<label><?php esc_html_e( 'Display As:', 'stripe-payments' ); ?> </label>
				<select class="asp-variations-display-type">
					<option value="0"><?php esc_html_e( 'Dropdown', 'stripe-payments' ); ?></option>
					<option value="1"><?php esc_html_e( 'Radio Buttons', 'stripe-payments' ); ?></option>
					<option value="2"><?php esc_html_e( 'Checkboxes', 'stripe-payments' ); ?></option>
				</select>
			</div>
		</div>
		<table class="widefat fixed asp-variations-tbl">
			<tr>
				<th width="40%"><?php echo esc_html( _x( 'Name', 'Variation name', 'stripe-payments' ) ); ?></th>
				<th width="20%"><?php esc_html_e( 'Price Mod', 'stripe-payments' ); ?> <?php echo ASP_Utils::gen_help_popup( $price_mod_help ); ?></th>
				<th width="30%"><?php esc_html_e( 'Product URL', 'stripe-payments' ); ?></th>
				<th width="30px"></th>
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
<div class="asp-html-tpl asp-html-tpl-variation-options-2">
	<div data-asp-var-type="2" class="asp-variations-options-cont" style="display:none;">
	<label>
		<input type="hidden" class="asp-variations-opts-checked-hidden" name="asp-variations-opts[%_group_id_%][][checked]" value="0" disabled>
		<input type="checkbox" class="asp-variations-opts-checked" name="asp-variations-opts[%_group_id_%][][checked]" value="1" disabled>
		<?php esc_html_e( 'Checked by default', 'stripe-payments' ); ?>
	</label>
	</div>
</div>
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
	}

	public function display_shipping_tax_meta_box( $post ) {
		$current_shipping    = get_post_meta( $post->ID, 'asp_product_shipping', true );
		$current_tax         = get_post_meta( $post->ID, 'asp_product_tax', true );
		$tax_variations_type = get_post_meta( $post->ID, 'asp_product_tax_variations_type', true );
		$tax_variations_arr  = get_post_meta( $post->ID, 'asp_product_tax_variations', true );

		$t_var_line_tpl = '<tr>
		<td>
		<select class="wp-asp-tax-variation-base wp-asp-tax-variations-input" name="asp_product_tax_variations_base[]">
			<option value="0" %11$s>' . esc_html__( 'Country', 'stripe-payments' ) . '</option>
			<option value="1" %12$s>' . esc_html__( 'State', 'stripe-payments' ) . '</option>
			<option value="2" %13$s>' . esc_html__( 'City', 'stripe-payments' ) . '</option>
		</select>
		</td>
		<td>
		<div class="wp-asp-tax-variation-cont-type-0" style="%3$s"><select class="wp-asp-tax-variations-input" name="asp_product_tax_variations_l[]" %6$s>%1$s</select></div>
		<div class="wp-asp-tax-variation-cont-type-1" style="%4$s">
		<input class="wp-asp-tax-variations-input" name="asp_product_tax_variations_l[]" type="text" %7$s value="%9$s">
		</div>
		<div class="wp-asp-tax-variation-cont-type-2" style="%5$s">
		<input class="wp-asp-tax-variations-input" name="asp_product_tax_variations_l[]" type="text" %8$s value="%10$s">
		</div>
		</td>
		<td><input type="number" class="wp-asp-tax-variations-input" step="any" min="0" name="asp_product_tax_variations_a[]" value="%2$s"></td>
		<td><button type="button" class="button wp-asp-tax-variations-del-btn asp-btn-small"><span class="dashicons dashicons-trash" title="' . __( 'Delete variation', 'stripe-payments' ) . '"></span></button></td>
		</tr>';

		$out = '';
		if ( ! empty( $tax_variations_arr ) ) {
			foreach ( $tax_variations_arr as $v ) {
				$c_code = '0' === $v['type'] ? $c_code = $v['loc'] : $c_code = '';
				$out   .= sprintf(
					$t_var_line_tpl,
					ASP_Utils::get_countries_opts( $c_code ),
					$v['amount'],
					'0' === $v['type'] ? '' : 'display:none',
					'1' === $v['type'] ? '' : 'display:none',
					'2' === $v['type'] ? '' : 'display:none',
					'0' === $v['type'] ? '' : 'disabled',
					'1' === $v['type'] ? '' : 'disabled',
					'2' === $v['type'] ? '' : 'disabled',
					'1' === $v['type'] ? $v['loc'] : '',
					'2' === $v['type'] ? $v['loc'] : '',
					'0' === $v['type'] ? 'selected' : '',
					'1' === $v['type'] ? 'selected' : '',
					'2' === $v['type'] ? 'selected' : ''
				);
			}
		}

		$tax_variations_disabled = false;

		$prod_type = get_post_meta( $post->ID, 'asp_product_type', true );
		$prod_type = apply_filters( 'asp_product_edit_product_type_selected', $prod_type, $post );
		if ( 'subscription' === $prod_type
		&& class_exists( 'ASPSUB_main' )
		&& version_compare( ASPSUB_main::ADDON_VER, '2.0.35', '<' ) ) {
			$tax_variations_disabled = true;
		}

		wp_localize_script(
			'asp-admin-edit-product-js',
			'aspTaxVarData',
			array(
				'tplLine'        => $t_var_line_tpl,
				'cOpts'          => ASP_Utils::get_countries_opts(),
				'disabledForSub' => $tax_variations_disabled,
				'str'            => array(
					'delConfirm' => __( 'Are you sure you want to delete this variation?', 'stripe-payments' ),
				),
			)
		);

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
<fieldset>
	<legend><?php esc_html_e( 'Tax Variations', 'stripe-payments' ); ?></legend>
		<div id="wp-asp-tax-variations-disabled-msg" style="color:red;<?php echo ! empty( $tax_variations_disabled ) ? '' : 'display:none;'; ?>">
			<?php
			// translators: %s is add-on version number
				echo sprintf( __( 'Update Stripe Payments Subscriptions add-on to version %s to enable this functionailty.', 'stripe-payments' ), '2.0.35+' );
			?>
</div>
<div id="wp-asp-tax-variations-cont"<?php echo ! empty( $tax_variations_disabled ) ? ' style="display:none;"' : ''; ?>>
<p class="description">
			<?php
			esc_html_e( 'Use this to configure tax variations on a per-region basis.', 'stripe-payments' );
			?>
</p>
<table class="fixed" id="wp-asp-tax-variations-tbl"<?php echo empty( $out ) ? 'style="display:none;"' : ''; ?>>
<thead>
	<tr>
		<th style="width: 20%;"><?php esc_html_e( 'Type', 'stripe-payments' ); ?></th>
		<th style="width: 50%;"><?php esc_html_e( 'Location', 'stripe-payments' ); ?></th>
		<th style="width: 20%;"><?php esc_html_e( 'Tax', 'stripe-payments' ); ?></th>
		<th style="width: 10%;"></th>
	</tr>
</thead>
<tbody>
		<?php echo $out; ?>
</tbody>
</table>
<p><button type="button" id="wp-asp-tax-variations-add-btn" class="button"><span class="dashicons dashicons-plus"></span> <?php _e( 'Add Tax Variation', 'stripe-payments' ); ?></button></p>
<label><?php esc_html_e( 'Apply the tax variation based on:', 'stripe-payments' ); ?></label>
<br>
<label><input type="radio" name="asp_product_tax_variations_type" value="b"<?php echo 'b' === $tax_variations_type || empty( $tax_variations_type ) ? ' checked' : ''; ?>><?php _e( 'Billing address', 'stripe-payments' ); ?></label>
<label><input type="radio" name="asp_product_tax_variations_type" value="s"<?php echo 's' === $tax_variations_type ? ' checked' : ''; ?>><?php _e( 'Shipping address', 'stripe-payments' ); ?></label>
</div>
</fieldset>
		<?php
	}

    public function display_surcharge_meta_box( $post )
    {
        $surcharge_type = get_post_meta( $post->ID, 'asp_surcharge_type', true );;
        $surcharge_amount = get_post_meta( $post->ID, 'asp_surcharge_amount', true );;
        $surcharge_label = get_post_meta( $post->ID, 'asp_surcharge_label', true );;
    	?>
        <div id="wp-asp-surcharge-cont">
			<p>
				<?php _e('If you want to charge your customers an additional amount for transaction or processing fees, you can use this transaction surcharge feature.', 'stripe-payments'); ?>
				<?php
				echo sprintf( __( ' You can get additional information <a href="%s" target="_blank">in this tutorial</a>.', 'stripe-payments' ), 'https://s-plugins.com/transaction-surcharge-feature-collect-processing-fees-for-products/' );
				?>
			</p>
            <div>
                <label><?php _e( 'Transaction Surcharge Amount Type: ', 'stripe-payments' ); ?></label>
                <br>
                <label>
                    <input type="radio" name="asp_surcharge_type" value="flat"<?php echo $surcharge_type == 'flat' || empty( $surcharge_type ) ? ' checked' : ''; ?>>
                    <?php _e( 'Flat Rate', 'stripe-payments' ); ?>
                </label>
                <label>
                    <input type="radio" name="asp_surcharge_type" value="perc"<?php echo $surcharge_type == 'perc' ? ' checked' : ''; ?>>
                    <?php _e( 'Percentage', 'stripe-payments' ); ?>
                </label>
            </div>

            <div>
                <div>
                    <label><?php _e( 'Surcharge Amount', 'stripe-payments' ); ?></label>
                    <br />
                    <input type="number" step="any" min="0" name="asp_surcharge_amount" value="<?php echo esc_attr( $surcharge_amount ); ?>">
                    <p class="description">
                        <?php _e( 'Numbers only, do not enter any currency symbol. Example: 5', 'stripe-payments' ); ?>
                    </p>
                </div>
                <div>
                    <label><?php _e( 'Surcharge Label', 'stripe-payments' ); ?></label>
                    <br />
                    <input type="text" name="asp_surcharge_label" value="<?php echo esc_attr( $surcharge_label ); ?>">
                    <p class="description">
                        <?php _e( 'Specify the label to use for the surcharge. Example: Processing Fee.', 'stripe-payments' ); ?>
                    </p>
                </div>
            </div>
        </div>
    	<?php
    }

	public function display_quantity_meta_box( $post ) {
		$current_val           = get_post_meta( $post->ID, 'asp_product_quantity', true );
		$allow_custom_quantity = get_post_meta( $post->ID, 'asp_product_custom_quantity', true );
		$enable_stock          = get_post_meta( $post->ID, 'asp_product_enable_stock', true );
		$show_remaining        = get_post_meta( $post->ID, 'asp_product_show_remaining_items', true );
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
	<label>
	<input type="checkbox" name="asp_product_show_remaining_items" value="1" <?php echo esc_attr( ( '1' === $show_remaining ) ? ' checked' : '' ); ?>>
		<?php esc_html_e( 'Show the available quantity in the payment popup', 'stripe-payments' ); ?>
	</label>
	<p class="description"><?php esc_html_e( 'When this is checked, the number of remaining items is displayed on payment popup.', 'stripe-payments' ); ?></p>
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
<p class="description"><?php _e( 'Enter the Thank You page URL for this product. Leave it blank to use the default Thank You page specified in the settings.', 'stripe-payments' ); ?>
	<br />
		<?php _e( 'You can read on how to customize some of the messages on the Thank You page <a href="https://s-plugins.com/customize-the-thank-page-message-of-stripe-payments-plugin/" target="_blank">in this documentation</a>.', 'stripe-payments' ); ?>
</p>
		<?php
	}

	public function display_appearance_meta_box( $post ) {
		$button_txt   = get_post_meta( $post->ID, 'asp_product_button_text', true );
		$button_class = get_post_meta( $post->ID, 'asp_product_button_class', true );
		$button_only  = get_post_meta( $post->ID, 'asp_product_button_only', true );

		$popup_button_txt  = get_post_meta( $post->ID, 'asp_product_popup_button_text', true );
		$show_your_order = get_post_meta( $post->ID, 'asp_product_show_your_order', true );
		?>
<fieldset>
	<legend><?php esc_html_e( 'Button Options', 'stripe-payments' ); ?></legend>
	<label><?php _e( 'Button Text', 'stripe-payments' ); ?></label>
	<br />
	<input type="text" name="asp_product_button_text" size="50" value="<?php echo esc_attr( $button_txt ); ?>">
	<p class="description"><?php _e( 'Specify text to be displayed on the button. Leave it blank to use the button text specified on the settings page.', 'stripe-payments' ); ?></p>
	<label><?php _e( 'Button CSS Class', 'stripe-payments' ); ?></label>
	<br />
	<input type="text" name="asp_product_button_class" size="50" value="<?php echo esc_attr( $button_class ); ?>">
	<p class="description"><?php _e( 'CSS class to be assigned to the button. This is used for styling purposes. You can get additional information <a href="https://s-plugins.com/customize-stripe-payment-button-appearance-using-css/" target="_blank">in this tutorial</a>.', 'stripe-payments' ); ?></p>
	<label><input type="checkbox" name="asp_product_button_only" value="1" <?php echo ( $button_only == 1 ) ? ' checked' : ''; ?>> <?php _e( 'Show Button Only', 'stripe-payments' ); ?></label>
	<p class="description"><?php _e( 'Check this box if you just want to show the button only without any additional product info.', 'stripe-payments' ); ?></p>
</fieldset>
<fieldset>
	<legend><?php esc_html_e( 'Payment Popup Options', 'stripe-payments' ); ?></legend>
	<label for="asp_product_popup_button_text"><?php _e( 'Payment Popup Button Text', 'stripe-payments' ); ?></label>
	<br />
	<input type="text" name="asp_product_popup_button_text" id="asp_product_popup_button_text" size="50" value="<?php echo esc_attr( $popup_button_txt ); ?>">
	<p class="description"><?php _e( 'Specify the button text for the payment popup window. Leave blank for global settings.', 'stripe-payments' ); ?></p>
	<label><input type="checkbox" name="asp_product_show_your_order" value="1" <?php echo $show_your_order ? ' checked' : ''; ?>> <?php esc_html_e( 'Display Order Total in Payment Popup', 'stripe-payments' ); ?></label>
	<p class="description"><?php _e( 'If enabled, an additional "Your order" section with itemized product info (shipping, tax amount, variations etc) will be displayed in the payment popup window.', 'stripe-payments' ); ?></p>
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
		$plan_id     = get_post_meta( $post->ID, 'asp_sub_plan_id', true );
		$auth_only   = get_post_meta( $post->ID, 'asp_product_authorize_only', true );

		$use_other_stripe_acc = get_post_meta( $post->ID, 'asp_use_other_stripe_acc', true );

		$live_pub_key = get_post_meta( $post->ID, 'asp_stripe_live_pub_key', true );
		$live_sec_key = get_post_meta( $post->ID, 'asp_stripe_live_sec_key', true );
		$test_pub_key = get_post_meta( $post->ID, 'asp_stripe_test_pub_key', true );
		$test_sec_key = get_post_meta( $post->ID, 'asp_stripe_test_sec_key', true );
		?>
		<fieldset>
		<label><input type="checkbox" name="asp_product_authorize_only" value="1"
		<?php
		echo $auth_only ? ' checked' : '';
		echo ! empty( $plan_id ) ? ' disabled' : '';
		?>
		> <?php echo esc_html_e( 'Authorize Only', 'stripe-payments' ); ?></label>
		<p class="description">
		<?php echo esc_html_e( 'Place a hold on a card to reserve the funds now and capture it later manually.', 'stripe-payments' ); ?>
		<br>
		<?php echo esc_html_e( 'Note: this option is not supported by Subscription products, Alipay, SOFORT, iDEAL and FPX payment methods. If enabled, those won\'t be offered as payment option for this product.', 'stripe-payments' ); ?>
		</p>
		<label><input type="checkbox" name="asp_product_force_test_mode" value="1"<?php echo $current_val ? ' checked' : ''; ?>> <?php echo esc_html_e( 'Force Test Mode', 'stripe-payments' ); ?></label>
		<p class="description"><?php echo esc_html_e( 'When enabled, this product will stay in test mode regardless of the value set in the global "Live Mode" settings option. Can be useful to create a test product.', 'stripe-payments' ); ?></p>
		</fieldset>
		<fieldset class="asp-other-stripe-acc">
			<legend><?php esc_html_e( 'Other Stripe Account', 'stripe-payments' ); ?></legend>
			<p class="description"><i><?php echo __( 'Note: this functionality is currently being tested and is not supported by Subscription products, APM, Alipay, SOFORT and iDEAL add-ons. Please do not enable it if you are using one of these add-ons.', 'stripe-payments' ); ?></i></p>
			<label><input type="checkbox" name="asp_use_other_stripe_acc" value="1"<?php echo $use_other_stripe_acc ? ' checked' : ''; ?>> <?php echo esc_html_e( 'Use Another Stripe Account', 'stripe-payments' ); ?></label>
			<p class="description"><?php _e( 'Enable this option if you want to use another Stripe account for this product only. The payment for this product will go to the Stripe account specified below. Enter API keys of the Stripe account below.' ); ?></p>
			<label>Live Stripe Publishable Key</label>
			<br>
			<input type="text" size="45" data-asp-other-acc name="asp_stripe_live_pub_key" value="<?php echo esc_attr( $live_pub_key ); ?>">
			<br>
			<label>Live Stripe Secret Key</label>
			<br>
			<input type="text" size="45" data-asp-other-acc name="asp_stripe_live_sec_key" value="<?php echo esc_attr( $live_sec_key ); ?>">
			<br>
			<label>Test Stripe Publishable Key</label>
			<br>
			<input type="text" size="45" data-asp-other-acc name="asp_stripe_test_pub_key" value="<?php echo esc_attr( $test_pub_key ); ?>">
			<br>
			<label>Test Stripe Secret Key</label>
			<br>
			<input type="text" size="45" data-asp-other-acc name="asp_stripe_test_sec_key" value="<?php echo esc_attr( $test_sec_key ); ?>">
		</fieldset>
		<?php
	}

	public function display_embed_meta_box( $post ) {
		$embed_url = add_query_arg(
			array(
				'product_id' => $post->ID,
			),
			ASP_Utils::get_base_pp_url()
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
                $action = isset( $_POST['action'] ) ? sanitize_text_field( $_POST['action'] ) : '';

		if ( empty( $action ) ) {
			//this is probably not edit or new post creation event
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
                if ( $action == 'inline-save' ){
                    //This is a quick edit action. Don't try to save other product details for this action.
                    //The default wordpress post_save action will handle the standard post data update (for example: the title, slug, date etc).
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
			$currency = isset( $_POST['asp_product_currency'] ) ? sanitize_text_field( stripslashes ( $_POST['asp_product_currency'] ) ) : '';
			update_post_meta( $post_id, 'asp_product_currency', sanitize_text_field( $currency ) );

			$shipping = isset( $_POST['asp_product_shipping'] ) ? sanitize_text_field( stripslashes ( $_POST['asp_product_shipping'] ) ) : '';
			$shipping = ! empty( $shipping ) ? AcceptStripePayments::tofloat( $shipping ) : $shipping;
			update_post_meta( $post_id, 'asp_product_shipping', $shipping );

			$tax = isset( $_POST['asp_product_tax'] ) ? sanitize_text_field( stripslashes ( $_POST['asp_product_tax'] ) ) : '';
			$tax = floatval( $tax );
			$tax = empty( $tax ) ? '' : $tax;
			update_post_meta( $post_id, 'asp_product_tax', $tax );

			$tax_variations_base = filter_input( INPUT_POST, 'asp_product_tax_variations_base', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
			$tax_variations_l    = filter_input( INPUT_POST, 'asp_product_tax_variations_l', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
			$tax_variations_a    = filter_input( INPUT_POST, 'asp_product_tax_variations_a', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );

			$tax_variations_arr = array();

			if ( ! empty( $tax_variations_base ) && ! empty( $tax_variations_l ) && ! empty( $tax_variations_a ) ) {
				foreach ( $tax_variations_base as $i => $type ) {
					$l                    = filter_var( $tax_variations_l[ $i ], FILTER_DEFAULT );
					$tax                  = floatval( filter_var( $tax_variations_a[ $i ], FILTER_DEFAULT ) );
					$tax_variations_arr[] = array(
						'type'   => $type,
						'loc'    => $l,
						'amount' => $tax,
					);
				}
			}

			update_post_meta( $post_id, 'asp_product_tax_variations', $tax_variations_arr );

			$tax_variations_type = isset( $_POST['asp_product_tax_variations_type'] ) ? sanitize_text_field( stripslashes ( $_POST['asp_product_tax_variations_type'] ) ) : '';
			update_post_meta( $post_id, 'asp_product_tax_variations_type', $tax_variations_type );

			$quantity = filter_input( INPUT_POST, 'asp_product_quantity', FILTER_SANITIZE_NUMBER_INT );
			$quantity = empty( $quantity ) ? '' : $quantity;
			update_post_meta( $post_id, 'asp_product_quantity', $quantity );

			update_post_meta( $post_id, 'asp_product_custom_quantity', isset( $_POST['asp_product_custom_quantity'] ) ? '1' : false );
			update_post_meta( $post_id, 'asp_product_enable_stock', isset( $_POST['asp_product_enable_stock'] ) ? '1' : false );
			update_post_meta( $post_id, 'asp_product_stock_items', sanitize_text_field( absint( $_POST['asp_product_stock_items'] ) ) );

            $surcharge_type = isset($_POST['asp_surcharge_type']) ? sanitize_text_field($_POST['asp_surcharge_type']) : '';
            update_post_meta( $post_id, 'asp_surcharge_type', $surcharge_type );
            $surcharge_amount = isset($_POST['asp_surcharge_amount']) ? sanitize_text_field($_POST['asp_surcharge_amount']) : '';
            update_post_meta( $post_id, 'asp_surcharge_amount', $surcharge_amount );
            $surcharge_label = isset($_POST['asp_surcharge_label']) && !empty($_POST['asp_surcharge_label']) ? sanitize_text_field($_POST['asp_surcharge_label']) : '';
            update_post_meta( $post_id, 'asp_surcharge_label', $surcharge_label );

			$show_remaining = filter_input( INPUT_POST, 'asp_product_show_remaining_items', FILTER_SANITIZE_NUMBER_INT );
			$show_remaining = ! empty( $show_remaining ) ? true : false;
			update_post_meta( $post_id, 'asp_product_show_remaining_items', $show_remaining );

			$force_test_mode = isset( $_POST['asp_product_force_test_mode'] ) ? sanitize_text_field( stripslashes ( $_POST['asp_product_force_test_mode'] ) ) : '';
			$force_test_mode = ! empty( $force_test_mode ) ? true : false;
			update_post_meta( $post_id, 'asp_product_force_test_mode', $force_test_mode );

			$auth_only = isset( $_POST['asp_product_authorize_only'] ) ? sanitize_text_field( stripslashes ( $_POST['asp_product_authorize_only'] ) ) : '';
			$auth_only = ! empty( $auth_only ) ? true : false;
			update_post_meta( $post_id, 'asp_product_authorize_only', $auth_only );

			$use_other_stripe_acc = isset( $_POST['asp_use_other_stripe_acc'] ) ? sanitize_text_field( stripslashes ( $_POST['asp_use_other_stripe_acc'] ) ) : '';
			$use_other_stripe_acc = ! empty( $use_other_stripe_acc ) ? true : false;
			update_post_meta( $post_id, 'asp_use_other_stripe_acc', $use_other_stripe_acc );

			$live_pub_key = isset( $_POST['asp_stripe_live_pub_key'] ) ? sanitize_text_field( stripslashes ( $_POST['asp_stripe_live_pub_key'] ) ) : '';
			update_post_meta( $post_id, 'asp_stripe_live_pub_key', $live_pub_key );
			$live_sec_key = isset( $_POST['asp_stripe_live_sec_key'] ) ? sanitize_text_field( stripslashes ( $_POST['asp_stripe_live_sec_key'] ) ) : '';
			update_post_meta( $post_id, 'asp_stripe_live_sec_key', $live_sec_key );
			$test_pub_key = isset( $_POST['asp_stripe_test_pub_key'] ) ? sanitize_text_field( stripslashes ( $_POST['asp_stripe_test_pub_key'] ) ) : '';
			update_post_meta( $post_id, 'asp_stripe_test_pub_key', $test_pub_key );
			$test_sec_key = isset( $_POST['asp_stripe_test_sec_key'] ) ? sanitize_text_field( stripslashes ( $_POST['asp_stripe_test_sec_key'] ) ) : '';
			update_post_meta( $post_id, 'asp_stripe_test_sec_key', $test_sec_key );

			if ( $use_other_stripe_acc && ( empty( $live_pub_key ) || empty( $live_sec_key ) || empty( $test_pub_key ) || empty( $test_sec_key ) ) ) {
				$text = __( 'Please enter all API keys for other Stripe account.', 'stripe-payments' );
				AcceptStripePayments_Admin::add_admin_notice( 'error', $text, false );
			}

			$pdf_stamper_enabled = isset( $_POST['asp_product_pdf_stamper_enabled'] ) ? sanitize_text_field( stripslashes ( $_POST['asp_product_pdf_stamper_enabled'] ) ) : '';
			$pdf_stamper_enabled = ! empty( $pdf_stamper_enabled ) ? true : false;
			update_post_meta( $post_id, 'asp_product_pdf_stamper_enabled', $pdf_stamper_enabled );

			update_post_meta( $post_id, 'asp_product_coupons_setting', isset( $_POST['asp_product_coupons_setting'] ) ? sanitize_text_field( $_POST['asp_product_coupons_setting'] ) : '0' );
			update_post_meta( $post_id, 'asp_product_custom_field', isset( $_POST['asp_product_custom_field'] ) ? sanitize_text_field( $_POST['asp_product_custom_field'] ) : '0' );
			update_post_meta( $post_id, 'asp_product_button_text', sanitize_text_field( $_POST['asp_product_button_text'] ) );
			update_post_meta( $post_id, 'asp_product_button_class', sanitize_text_field( $_POST['asp_product_button_class'] ) );
			update_post_meta( $post_id, 'asp_product_button_only', isset( $_POST['asp_product_button_only'] ) ? 1 : 0 );
			update_post_meta( $post_id, 'asp_product_popup_button_text', sanitize_text_field( $_POST['asp_product_popup_button_text'] ) );
			update_post_meta( $post_id, 'asp_product_show_your_order', isset( $_POST['asp_product_show_your_order'] ) ? 1 : 0 );
			update_post_meta( $post_id, 'asp_product_description', sanitize_text_field( $_POST['asp_product_description'] ) );
			update_post_meta( $post_id, 'asp_product_upload', sanitize_url( $_POST['asp_product_upload'], array( 'http', 'https', 'dropbox' ) ) );

			$thumb_url_raw = filter_input( INPUT_POST, 'asp_product_thumbnail', FILTER_DEFAULT );
			$thumb_url = esc_url_raw( $thumb_url_raw, array( 'http', 'https' ) );

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
			update_post_meta( $post_id, 'asp_product_thankyou_page', isset( $_POST['asp_product_thankyou_page'] ) && ! empty( $_POST['asp_product_thankyou_page'] ) ? esc_url_raw( $_POST['asp_product_thankyou_page'] ) : '' );
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

			$product_type = isset( $_POST['asp_product_type_radio'] ) ? sanitize_text_field( stripslashes ( $_POST['asp_product_type_radio'] ) ) : '';

			if ( empty( $asp_plan_id ) || ( ! empty( $product_type ) && 'subscription' !== $product_type ) ) {
				update_post_meta( $post_id, 'asp_sub_plan_id', 0 );

				//handle variations
				$variations_groups = filter_input( INPUT_POST, 'asp-variations-group-names', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY );
				if ( is_array( $variations_groups ) && ! empty( $variations_groups ) ) {
					//we got variations groups. Let's process them
					update_post_meta( $post_id, 'asp_variations_groups', array_values( $variations_groups ) );
					$variations_names = filter_input( INPUT_POST, 'asp-variation-names', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY );
					update_post_meta( $post_id, 'asp_variations_names', array_values( $variations_names ) );
					$variations_prices = filter_input( INPUT_POST, 'asp-variation-prices', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY );
					update_post_meta( $post_id, 'asp_variations_prices', array_values( $variations_prices ) );
					$variations_urls = filter_input( INPUT_POST, 'asp-variation-urls', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY );
					update_post_meta( $post_id, 'asp_variations_urls', array_values( $variations_urls ) );
					$variations_opts = filter_input( INPUT_POST, 'asp-variations-opts', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY );
					update_post_meta( $post_id, 'asp_variations_opts', array_values( $variations_opts ) );
				} else {
					//we got no variations groups. Let's clear meta values
					update_post_meta( $post_id, 'asp_variations_groups', false );
					update_post_meta( $post_id, 'asp_variations_names', false );
					update_post_meta( $post_id, 'asp_variations_prices', false );
					update_post_meta( $post_id, 'asp_variations_urls', false );
					update_post_meta( $post_id, 'asp_variations_opts', false );
				}

				$hide_amount_input = isset( $_POST['asp_product_hide_amount_input'] ) ? sanitize_text_field( stripslashes ( $_POST['asp_product_hide_amount_input'] ) ) : '';
				$hide_amount_input = ! empty( $hide_amount_input ) ? true : false;
				update_post_meta( $post_id, 'asp_product_hide_amount_input', $hide_amount_input );

				//check if price is in min-max range for the currency set by Stripe: https://stripe.com/docs/currencies#minimum-and-maximum-charge-amounts
				$price    = sanitize_text_field( $_POST['asp_product_price'] );
				$price    = AcceptStripePayments::tofloat( $price );
				$currency = sanitize_text_field( $_POST['asp_product_currency'] );

				if ( ! empty( $product_type ) ) {
					update_post_meta( $post_id, 'asp_product_type', $product_type );
					if ( 'donation' === $product_type ) {
						$min_amount = isset( $_POST['asp_product_min_amount'] ) ? sanitize_text_field( stripslashes ( $_POST['asp_product_min_amount'] ) ) : '';
						$min_amount = abs( floatval( $min_amount ) );
						update_post_meta( $post_id, 'asp_product_min_amount', $min_amount );
						update_post_meta( $post_id, 'asp_product_price', 0 );
						$currency_variable = isset( $_POST['asp_product_currency_variable'] ) ? sanitize_text_field( stripslashes ( $_POST['asp_product_currency_variable'] ) ) : '';
						$currency_variable = ! empty( $currency_variable ) ? true : false;
						update_post_meta( $post_id, 'asp_product_currency_variable', $currency_variable );
						return true;
					}
				} else {
					$currency_variable = isset( $_POST['asp_product_currency_variable'] ) ? sanitize_text_field( stripslashes ( $_POST['asp_product_currency_variable'] ) ) : '';
					$currency_variable = ! empty( $currency_variable ) ? true : false;
					update_post_meta( $post_id, 'asp_product_currency_variable', $currency_variable );
				}

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
							// translators: %1$s - currency code, %2$s - minimum amount, %3$s - current amount
							$text = sprintf( __( '<b>Invalid product price</b>: minimum price in %1$s should be %2$s, you specified %3$s. This price limitation comes from Stripe.', 'stripe-payments' ), $currency, AcceptStripePayments::formatted_price( $this->asp_main->minAmounts[ $currency ], $currency, true ), AcceptStripePayments::formatted_price( $price_cents, $currency, true ) );
							AcceptStripePayments_Admin::add_admin_notice( 'error', $text, false );
							// we don't save invalid price
							return false;
						}
					}
					//check if value is not above maximum allowed by Stripe (8 digits; e.g. 99999999 in cents)
					if ( $price_cents > 99999999 ) {
						// it is. Let's add error message
						// translators: %1$s - maximum allowed amount, %2$s - current amount
						$text = sprintf( __( '<b>Invalid product price</b>: maximum allowed product price is %1$s, you specified %2$s', 'stripe-payments' ), $this->asp_main->formatted_price( 99999999, $currency, true ), AcceptStripePayments::formatted_price( $price_cents, $currency, true ) );
						AcceptStripePayments_Admin::add_admin_notice( 'error', $text, false );
						// we don't save invalid price
						return false;
					}
				} elseif ( ! empty( $product_type ) && 'one_time' === $product_type && ! $hide_amount_input ) {
					//price cannot be 0
					$text = __( '<b>Invalid product price</b>: one-time payment product price cannot be zero.', 'stripe-payments' );
					AcceptStripePayments_Admin::add_admin_notice( 'error', $text, false );
					return false;
				}
				//price seems to be valid, let's save it
				update_post_meta( $post_id, 'asp_product_price', $price );
			}
		}
	}

}

new ASP_Admin_Product_Meta_Boxes();
