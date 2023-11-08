<?php

class AcceptStripePayments_CouponsAdmin {

	public static $post_slug = 'asp_coupons';

	public function __construct() {
		add_action( 'init', array( $this, 'init_handler' ) );
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_menu' ) );
			if ( wp_doing_ajax() ) {
				add_action( 'wp_ajax_asp_check_coupon', array( $this, 'frontend_check_coupon' ) );
				add_action( 'wp_ajax_nopriv_asp_check_coupon', array( $this, 'frontend_check_coupon' ) );
			}
		}
	}

	public static function get_coupon( $coupon_code ) {
		$out = array(
			'code'  => $coupon_code,
			'valid' => true,
		);
		//let's find coupon
		$coupon = get_posts(
			array(
				'meta_key'       => 'asp_coupon_code',
				'meta_value'     => $coupon_code,
				'posts_per_page' => 1,
				'offset'         => 0,
				'post_type'      => self::$post_slug,
			)
		);
		wp_reset_postdata();
		if ( empty( $coupon ) ) {
			//coupon not found
			$out['valid']   = false;
			$out['err_msg'] = __( 'Coupon not found.', 'stripe-payments' );
			return $out;
		}
		$coupon = $coupon[0];
		//check if coupon is active
		if ( ! get_post_meta( $coupon->ID, 'asp_coupon_active', true ) ) {
			$out['valid']   = false;
			$out['err_msg'] = __( 'Coupon is not active.', 'stripe-payments' );
			return $out;
		}
		//check if coupon start date has come
		$start_date = get_post_meta( $coupon->ID, 'asp_coupon_start_date', true );
		if ( empty( $start_date ) || strtotime( $start_date ) > time() ) {
			$out['valid']   = false;
			$out['err_msg'] = __( 'Coupon is not available yet.', 'stripe-payments' );
			return $out;
		}
		//check if coupon has expired
		$exp_date = get_post_meta( $coupon->ID, 'asp_coupon_exp_date', true );
		if ( ! empty( $exp_date ) && strtotime( $exp_date ) < time() ) {
			$out['valid']   = false;
			$out['err_msg'] = __( 'Coupon has expired.', 'stripe-payments' );
			return $out;
		}
		//check if redemption limit is reached
		$red_limit = get_post_meta( $coupon->ID, 'asp_coupon_red_limit', true );
		$red_count = get_post_meta( $coupon->ID, 'asp_coupon_red_count', true );
		if ( ! empty( $red_limit ) && intval( $red_count ) >= intval( $red_limit ) ) {
			$out['valid']   = false;
			$out['err_msg'] = __( 'Coupon redemption limit is reached.', 'stripe-payments' );
			return $out;
		}
		$out['id']           = $coupon->ID;
		$out['discount']     = get_post_meta( $coupon->ID, 'asp_coupon_discount', true );
		$out['discountType'] = get_post_meta( $coupon->ID, 'asp_coupon_discount_type', true );
		return $out;
	}

	public static function is_coupon_allowed_for_product( $coupon_id, $prod_id ) {
		//check if coupon is only available for specific products
		$only_for_allowed_products = get_post_meta( $coupon_id, 'asp_coupon_only_for_allowed_products', true );
		if ( $only_for_allowed_products ) {
			$allowed_products = get_post_meta( $coupon_id, 'asp_coupon_allowed_products', true );
			if ( is_array( $allowed_products ) && ! in_array( $prod_id, $allowed_products, true ) ) {
				return false;
			}
		}
		return true;
	}

	public function frontend_check_coupon() {
		$out = array();

		$coupon_code = isset( $_POST['coupon_code'] ) ? sanitize_text_field( stripslashes ( $_POST['coupon_code'] ) ) : '';

		if ( empty( $coupon_code ) ) {
			$out['success'] = false;
			$out['msg']     = __( 'Empty coupon code', 'stripe-payments' );
			wp_send_json( $out );
		}

		$coupon_code = strtoupper( $coupon_code );

		$tax = filter_input( INPUT_POST, 'tax', FILTER_SANITIZE_NUMBER_INT );
		$tax = empty( $tax ) ? 0 : absint( $tax );

		$shipping = filter_input( INPUT_POST, 'shipping', FILTER_SANITIZE_NUMBER_INT );
		$shipping = empty( $shipping ) ? 0 : absint( $shipping );

		$coupon = self::get_coupon( $coupon_code );

		if ( ! $coupon['valid'] ) {
			$out['success'] = false;
			$out['msg']     = $coupon['err_msg'];
			wp_send_json( $out );
		}

		$prod_id = filter_input( INPUT_POST, 'product_id', FILTER_SANITIZE_NUMBER_INT );
		if ( empty( $prod_id ) ) {
			$out['success'] = false;
			$out['msg']     = __( 'No product ID specified.', 'stripe-payments' );
			wp_send_json( $out );
		}
		if ( ! self::is_coupon_allowed_for_product( $coupon['id'], $prod_id ) ) {
			$out['success'] = false;
			$out['msg']     = __( 'Coupon is not allowed for this product.', 'stripe-payments' );
			wp_send_json( $out );
		}

		$curr = isset( $_POST['curr'] ) ? sanitize_text_field( stripslashes ( $_POST['curr'] ) ) : '';
		$curr = isset( $curr ) ? $curr : '';

		$discount      = $coupon['discount'];
		$discount_type = $coupon['discountType'];

		$amount = filter_input( INPUT_POST, 'amount', FILTER_SANITIZE_NUMBER_INT );

		$perc = AcceptStripePayments::is_zero_cents( $curr ) ? 0 : 2;

		if ( $coupon['discountType'] === 'perc' ) {
			$discount_amount = round( $amount * ( $coupon['discount'] / 100 ), 0 );
		} else {
			$discount_amount = $coupon['discount'] * ( $perc === 0 ? 1 : 100 );
		}
		$out['discountAmount'] = $discount_amount;
		$amount                = round( ( $amount - $discount_amount ) / ( $perc === 0 ? 1 : 100 ), $perc );

		$amount = AcceptStripePayments::apply_tax( $amount, $tax, AcceptStripePayments::is_zero_cents( $curr ) );

		$amount = round( $amount + $shipping / 100, 2 );

		$out['tax']          = $tax;
		$out['shipping']     = $shipping;
		$out['amount']       = $amount;
		$out['success']      = true;
		$out['code']         = $coupon_code;
		$out['discount']     = $discount;
		$out['discountType'] = $discount_type;
		$out['discountStr']  = $coupon_code . ': - ' . ( $discount_type === 'perc' ? $discount . '%' : AcceptStripePayments::formatted_price( $discount, $curr ) );
		$out['newAmountFmt'] = AcceptStripePayments::formatted_price( $amount, $curr );
		wp_send_json( $out );
	}

	public function init_handler() {
		$args = array(
			'supports'            => array( '' ),
			'hierarchical'        => false,
			'public'              => false,
			'show_ui'             => false,
			'can_export'          => false,
			'has_archive'         => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'capability_type'     => 'post',
		);
		register_post_type( self::$post_slug, $args );

		if ( ! is_admin() ) {
			return;
		}

		$post_action = isset( $_POST['asp_coupon_action'] ) ? sanitize_text_field( stripslashes ( $_POST['asp_coupon_action'] ) ) : '';
		$post_action = empty( $post_action ) ? '0' : $post_action;

		switch ( $post_action ) {
			case 'save_coupon':
				$this->save_coupon();
				break;
			case 'save_settings':
				$this->save_settings();
				break;
			default:
				break;
		}

		$action = isset( $_GET['action'] ) ? sanitize_text_field( stripslashes ( $_GET['action'] ) ) : '';
		$action = empty( $action ) ? '' : $action;

		if ( $action === 'asp_delete_coupon' ) {
			$this->delete_coupon();
		}
	}

	public function add_menu() {
                //Trigger filter hook to allow overriding of the add-ons menu capability.
                $asp_coupons_menu_capability = apply_filters( 'asp_coupons_menu_capability', ASP_MANAGEMENT_PERMISSION );            
		add_submenu_page( 'edit.php?post_type=' . ASPMain::$products_slug, __( 'Coupons', 'stripe-payments' ), __( 'Coupons', 'stripe-payments' ), $asp_coupons_menu_capability, 'stripe-payments-coupons', array( $this, 'display_coupons_menu_page' ) );
	}

	public function save_settings() {
		check_admin_referer( 'asp-coupons-settings' );
		$settings                    = get_option( 'AcceptStripePayments-settings' );
		$opts                        = filter_input( INPUT_POST, 'asp_coupons_opts', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY );
		$settings['coupons_enabled'] = isset( $opts['coupons_enabled'] ) ? 1 : 0;
		update_option( 'AcceptStripePayments-settings', $settings );

		AcceptStripePayments_Admin::add_admin_notice(
			'success',
			__( 'Settings updated.', 'stripe-payments' ),
			false
		);
	}

	public function display_coupons_menu_page() {

		$action = isset( $_GET['action'] ) ? sanitize_text_field( stripslashes ( $_GET['action'] ) ) : '';

		if ( ! empty( $action ) ) {
			if ( $action === 'asp_add_edit_coupon' ) {
				//coupon add or edit content
				$this->display_coupon_add_edit_page();
				return;
			}
		}

		$asp_main        = AcceptStripePayments::get_instance();
		$coupons_enabled = $asp_main->get_setting( 'coupons_enabled' );

		if ( ! class_exists( 'WP_List_Table' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
		}

		require_once WP_ASP_PLUGIN_PATH . 'admin/includes/class-coupons-list-table.php';

		$coupons_tbl = new ASP_Coupons_Table();
		$coupons_tbl->prepare_items();
		?>
<style>
th#id {
	width: 10%;
}
</style>
<div class="wrap">
	<h2><?php esc_html_e( 'Coupons', 'stripe-payments' ); ?></h2>
	<div id="poststuff">
		<div id="post-body">
			<div class="postbox">
				<h3 class="hndle"><label for="title"><?php esc_html_e( 'Coupon Settings', 'stripe-payments' ); ?></label></h3>
				<div class="inside">
					<form method="post">
						<input type="hidden" name="asp_coupon_action" value="save_settings">
						<table class="form-table">
							<tr>
								<th scope="row"><?php esc_html_e( 'Enable Coupons', 'stripe-payments' ); ?></th>
								<td>
									<input type="checkbox" name="asp_coupons_opts[coupons_enabled]" <?php echo $coupons_enabled ? ' checked' : ''; ?>>
									<p class="description"><?php esc_html_e( 'Enables the discount coupon functionality.', 'stripe-payments' ); ?></p>
								</td>
							</tr>
						</table>
						<?php
						wp_nonce_field( 'asp-coupons-settings' );
						submit_button( __( 'Save Settings', 'stripe-payments' ) );
						?>
					</form>
				</div>
			</div>
		</div>
	</div>
	<h2><?php esc_html_e( 'Coupons', 'stripe-payments' ); ?> <a class="page-title-action" href="?post_type=<?php echo esc_attr( ASPMain::$products_slug ); ?>&page=stripe-payments-coupons&action=asp_add_edit_coupon"><?php esc_html_e( 'Add a Coupon', 'stripe-payments' ); ?></a></h2>
		<?php $coupons_tbl->display(); ?>
</div>
		<?php
	}

	public function display_coupon_add_edit_page() {

		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_register_style( 'jquery-ui', WP_ASP_PLUGIN_URL . '/admin/assets/css/jquery-ui-theme/jquery-ui.min.css' );
		wp_enqueue_style( 'jquery-ui' );

		$coupon_id = filter_input( INPUT_GET, 'asp_coupon_id', FILTER_SANITIZE_NUMBER_INT );
		$coupon_id = empty( $coupon_id ) ? false : absint( $coupon_id );

		$is_edit = $coupon_id ? true : false;
		if ( $is_edit ) {
			if ( is_null( get_post( $coupon_id ) ) ) {
				echo 'error';
				return false;
			}
			$coupon = array(
				'id'                        => $coupon_id,
				'code'                      => get_post_meta( $coupon_id, 'asp_coupon_code', true ),
				'active'                    => get_post_meta( $coupon_id, 'asp_coupon_active', true ),
				'discount'                  => get_post_meta( $coupon_id, 'asp_coupon_discount', true ),
				'discount_type'             => get_post_meta( $coupon_id, 'asp_coupon_discount_type', true ),
				'red_limit'                 => get_post_meta( $coupon_id, 'asp_coupon_red_limit', true ),
				'red_count'                 => get_post_meta( $coupon_id, 'asp_coupon_red_count', true ),
				'start_date'                => get_post_meta( $coupon_id, 'asp_coupon_start_date', true ),
				'exp_date'                  => get_post_meta( $coupon_id, 'asp_coupon_exp_date', true ),
				'only_for_allowed_products' => get_post_meta( $coupon_id, 'asp_coupon_only_for_allowed_products', true ),
				'allowed_products'          => get_post_meta( $coupon_id, 'asp_coupon_allowed_products', true ),
				'per_order'                 => get_post_meta( $coupon_id, 'asp_coupon_per_order', true ),
			);
		}
		//generate array with all products
		$posts = get_posts(
			array(
				'post_type'   => untrailingslashit( ASPMain::$products_slug ),
				'post_status' => 'publish',
				'numberposts' => -1,
			)
		);

		$prod_inputs = '';
		$input_tpl   = '<label><input type="checkbox" name="asp_coupon[allowed_products][]" value="%s"%s> %s</label>';
		if ( $posts ) {
			foreach ( $posts as $the_post ) {
				$checked = '';
				if ( ! empty( $coupon ) && is_array( $coupon['allowed_products'] ) ) {
					if ( in_array( strval( $the_post->ID ), $coupon['allowed_products'], true ) ) {
						$checked = ' checked';
					}
				}
				$prod_inputs .= sprintf( $input_tpl, $the_post->ID, $checked, $the_post->post_title );
				$prod_inputs .= '<br>';
			}
		} else {
			$prod_inputs = __( 'No products created yet.', 'stripe-payments' );
		}
		wp_reset_postdata();
		?>
<div class="wrap">
	<h2><?php empty( $coupon_id ) ? esc_html_e( 'Add Coupon', 'stripe-payments' ) : esc_html_e( 'Edit Coupon', 'stripe-payments' ); ?></h2>
	<form method="post">
		<input type="hidden" name="asp_coupon_action" value="save_coupon">
		<table class="form-table">
			<tr>
				<th scope="row"><?php esc_html_e( 'Active', 'stripe-payments' ); ?></th>
				<td>
					<input type="checkbox" name="asp_coupon[active]" <?php echo ( ! $is_edit ) || ( $is_edit && $coupon['active'] ) ? 'checked' : ''; ?>>
					<p class="description"><?php esc_html_e( 'Use this to enable/disable this coupon.', 'stripe-payments' ); ?></p>
				</td>
			</tr>
			<?php if ( $is_edit ) { ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Coupon ID', 'stripe-payments' ); ?></th>
				<td>
					<input type="hidden" name="asp_coupon_id" value="<?php echo esc_attr( $coupon_id ); ?>">
					<?php echo esc_html( $coupon_id ); ?>
					<p class="description"><?php esc_html_e( 'Coupon ID. This value cannot be changed.', 'stripe-payments' ); ?></p>
				</td>
			</tr>
			<?php } ?>
			<tr>
				<th scope="row"><?php esc_html_e( 'Coupon Code', 'stripe-payments' ); ?></th>
				<td>
					<input type="text" name="asp_coupon[code]" value="<?php echo $is_edit ? esc_attr( $coupon['code'] ) : ''; ?>">
					<p class="description"><?php esc_html_e( 'Coupon code that you can share with your customers. Example: GET10OFF', 'stripe-payments' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Discount', 'stripe-payments' ); ?></th>
				<td>
					<input style="vertical-align: middle;" type="number" name="asp_coupon[discount]" min="0" step="0.01" <?php echo $is_edit && $coupon['discount_type'] === 'perc' ? 'max="100"' : ''; ?>value="<?php echo $is_edit ? esc_attr( $coupon['discount'] ) : ''; ?>">
					<select name="asp_coupon[discount_type]">
						<option value="perc" <?php echo $is_edit && $coupon['discount_type'] === 'perc' ? ' selected' : ''; ?>><?php esc_html_e( 'Percent (%)', 'stripe-payments' ); ?></option>
						<option value="fixed" <?php echo $is_edit && $coupon['discount_type'] === 'fixed' ? ' selected' : ''; ?>><?php esc_html_e( 'Fixed amount', 'stripe-payments' ); ?></option>
					</select>
					<p class="description"><?php esc_html_e( 'Select discount amount and type. Enter a numeric value only. Example: 25', 'stripe-payments' ); ?></p>
					<div id="asp-coupon-per-order-cont" <?php echo $is_edit && $coupon['discount_type'] === 'fixed' ? '' : ' style="display:none;"'; ?>>
						<br>
						<label><input type="checkbox" name="asp_coupon[per_order]" value="1"<?php echo $is_edit && ! empty( $coupon['per_order'] ) ? ' checked' : ''; ?>> <?php esc_html_e( 'Apply Per-Order', 'stripe-payments' ); ?></label>
						<p class="description">
							<?php esc_html_e( 'If enabled, discount is applied on per-order basis rather than per-item.', 'stripe-payments' ); ?>
							<br />
							<em><?php esc_html_e( 'This option is only available for "fixed amount" type coupons.', 'stripe-payments' ); ?></em>
						</p>
					</div>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Redemption Limit', 'stripe-payments' ); ?></th>
				<td>
					<input type="number" name="asp_coupon[red_limit]" value="<?php echo $is_edit ? esc_attr( $coupon['red_limit'] ) : 0; ?>">
					<p class="description"><?php esc_html_e( 'Set max number of coupons available for redemption. Put 0 to make it unlimited.', 'stripe-payments' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Redemption Count', 'stripe-payments' ); ?></th>
				<td>
					<input type="number" name="asp_coupon[red_count]" value="<?php echo $is_edit ? esc_attr( $coupon['red_count'] ) : 0; ?>">
					<p class="description"><?php esc_html_e( 'Number of already redeemed coupons.', 'stripe-payments' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Start Date', 'stripe-payments' ); ?></th>
				<td>
					<input class="datepicker-input" type="text" name="asp_coupon[start_date]" value="<?php echo $is_edit ? esc_attr( $coupon['start_date'] ) : esc_attr( date( 'Y-m-d' ) ); ?>">
					<p class="description"><?php esc_html_e( 'Start date when this coupon can be used.', 'stripe-payments' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Expiry Date', 'stripe-payments' ); ?></th>
				<td>
					<input class="datepicker-input" type="text" name="asp_coupon[exp_date]" value="<?php echo $is_edit ? esc_attr( $coupon['exp_date'] ) : 0; ?>">
					<p class="description"><?php esc_html_e( 'Date when this coupon will expire. Put 0 to disable expiry check.', 'stripe-payments' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Coupon Available For:', 'stripe-payments' ); ?></th>
				<td>
					<label><input type="radio" name="asp_coupon[only_for_allowed_products]" value="0" <?php echo ! $is_edit || ( $is_edit && ! $coupon['only_for_allowed_products'] ) ? ' checked' : ''; ?>> <?php esc_html_e( 'All products', 'stripe-payments' ); ?></label>
					<br>
					<label><input type="radio" name="asp_coupon[only_for_allowed_products]" value="1" <?php echo $is_edit && $coupon['only_for_allowed_products'] ? ' checked' : ''; ?>> <?php esc_html_e( 'Specific Products Only', 'stripe-payments' ); ?></label>
					<p class="asp-coupons-available-products" <?php echo ( $is_edit && ! $coupon['only_for_allowed_products'] ) || ( ! $is_edit ) ? ' style="display: none;"' : ''; ?>>
						<?php echo wp_kses( $prod_inputs, ASP_Utils::asp_allowed_tags() ); ?>
					</p>
					<p class="description"><?php esc_html_e( 'Choose availability of the coupon. You can specify which products the coupon is available to when the "Specific Products Only" option is selected.', 'stripe-payments' ); ?></p>
				</td>
			</tr>
			<?php
			do_action( 'asp_admin_add_edit_coupon', $coupon_id );
			?>
		</table>
		<?php
		wp_nonce_field( 'asp-add-edit-coupon' );
		submit_button( $is_edit ? __( 'Update Coupon', 'stripe-payments' ) : __( 'Create Coupon', 'stripe-payments' ) );
		?>
	</form>
</div>
<script>
jQuery(document).ready(function($) {
	$('.datepicker-input').datepicker({
		dateFormat: 'yy-mm-dd'
	});
	$('input[name="asp_coupon[only_for_allowed_products]"]').change(function() {
		if ($(this).val() === "1") {
			$('.asp-coupons-available-products').show();
		} else {
			$('.asp-coupons-available-products').hide();
		}
	});
	$('select[name="asp_coupon[discount_type]"]').change(function() {
		if (this.value === 'fixed') {
			jQuery('input[name="asp_coupon[discount]"]').prop('max', '');

			$('#asp-coupon-per-order-cont').slideDown('fast');
		} else {
			jQuery('input[name="asp_coupon[discount]"]').prop('max', 100);

			$('#asp-coupon-per-order-cont').slideUp('fast');
		}
	});
});
</script>
		<?php
	}

	public function delete_coupon() {
		$coupon_id = isset( $_GET['asp_coupon_id'] ) ? absint( $_GET['asp_coupon_id'] ) : false;

		if ( ! $coupon_id ) {
			AcceptStripePayments_Admin::add_admin_notice(
				'error',
				__( 'Can\'t delete coupon: coupon ID is not provided.', 'stripe-payments' ),
				false
			);
			return false;
		}
		$the_post = get_post( $coupon_id );
		if ( is_null( $the_post ) ) {
			AcceptStripePayments_Admin::add_admin_notice(
				'error',
				// translators: %d is coupon ID
				sprintf( __( 'Can\'t delete coupon: coupon #%d not found.', 'stripe-payments' ), $coupon_id ),
				false
			);
			return false;
		}
		if ( $the_post->post_type !== self::$post_slug ) {
			AcceptStripePayments_Admin::add_admin_notice(
				'error',
				// translators: %d is coupon ID
				sprintf( __( 'Can\'t delete coupon: post #%d is not a coupon.', 'stripe-payments' ), $coupon_id ),
				false
			);
			return false;
		}
		check_admin_referer( 'delete-coupon_' . $coupon_id );
		wp_delete_post( $coupon_id, true );

		AcceptStripePayments_Admin::add_admin_notice(
			'success',
			// translators: %d is coupon ID
			sprintf( __( 'Coupon #%d has been deleted.', 'stripe-payments' ), $coupon_id ),
			false
		);

		wp_safe_redirect( remove_query_arg( array( 'action', 'asp_coupon_id', '_wpnonce' ) ) );
		exit();
	}

	public function save_coupon() {
		check_admin_referer( 'asp-add-edit-coupon' );

		$coupon = filter_input( INPUT_POST, 'asp_coupon', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY );

		$coupon_id = isset( $_POST['asp_coupon_id'] ) ? absint( $_POST['asp_coupon_id'] ) : false;

		$is_edit = $coupon_id ? true : false;

		$err_msg = array();

		$coupon['active'] = isset( $coupon['active'] ) ? 1 : 0;

		$coupon['per_order'] = isset( $coupon['per_order'] ) ? 1 : 0;

		$coupon['code'] = ! empty( $coupon['code'] ) ? sanitize_text_field( $coupon['code'] ) : '';

		if ( empty( $coupon['code'] ) ) {
			$err_msg[] = __( 'Please enter coupon code.', 'stripe-payments' );
		}

		if ( empty( $coupon['discount'] ) ) {
			$err_msg[] = __( 'Please enter discount.', 'stripe-payments' );
		}

		if ( $coupon['discount_type'] === 'perc' && $coupon['discount'] > 100 ) {
			$err_msg[] = __( "Discount can't be more than 100%.", 'stripe-payments' );
		}

		if ( ! empty( $err_msg ) ) {
			foreach ( $err_msg as $msg ) {
				AcceptStripePayments_Admin::add_admin_notice( 'error', $msg, false );
			}
			return false;
		}

		if ( ! $is_edit ) {
			$post                = array();
			$post['post_title']  = '';
			$post['post_status'] = 'publish';
			$post['content']     = '';
			$post['post_type']   = self::$post_slug;
			$coupon_id           = wp_insert_post( $post );
		}

		if ( empty( $coupon['allowed_products'] ) ) {
			$coupon['allowed_products'] = array();
		}

		foreach ( $coupon as $key => $value ) {
			update_post_meta( $coupon_id, 'asp_coupon_' . $key, $value );
		}
		do_action( 'asp_admin_save_coupon', $coupon_id, $coupon );

		AcceptStripePayments_Admin::add_admin_notice(
			'success',
			// translators: %s is coupon code
			sprintf( $is_edit ? __( 'Coupon "%s" has been updated.', 'stripe-payments' ) : __( 'Coupon "%s" has been created.', 'stripe-payments' ), $coupon['code'] ),
			false
		);

		wp_safe_redirect( 'edit.php?post_type=' . ASPMain::$products_slug . '&page=stripe-payments-coupons' );
		exit;
	}

}

new AcceptStripePayments_CouponsAdmin();
