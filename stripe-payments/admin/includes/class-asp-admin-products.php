<?php

class ASP_Admin_Products {

	protected static $instance = null;

	public function __construct() {
		self::$instance = $this;

		if ( is_admin() ) {
			//products meta boxes handler
			require_once WP_ASP_PLUGIN_PATH . 'admin/includes/class-asp-admin-product-meta-boxes.php';
		}
	}

	public static function get_instance() {

		// If the single instance hasn't been set, set it now.
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function register_post_type() {

		// Products post type
		$labels    = array(
			'name'               => _x( 'Products', 'Post Type General Name', 'stripe-payments' ),
			'singular_name'      => _x( 'Product', 'Post Type Singular Name', 'stripe-payments' ),
			'menu_name'          => __( 'Stripe Payments', 'stripe-payments' ),
			'all_items'          => __( 'Products', 'stripe-payments' ),
			'view_item'          => __( 'View Product', 'stripe-payments' ),
			'add_new_item'       => __( 'Add New Product', 'stripe-payments' ),
			'add_new'            => __( 'Add New Product', 'stripe-payments' ),
			'edit_item'          => __( 'Edit Product', 'stripe-payments' ),
			'update_item'        => __( 'Update Products', 'stripe-payments' ),
			'search_items'       => __( 'Search Product', 'stripe-payments' ),
			'not_found'          => __( 'Not found', 'stripe-payments' ),
			'not_found_in_trash' => __( 'Not found in Trash', 'stripe-payments' ),
		);
		$menu_icon = WP_ASP_PLUGIN_URL . '/assets/asp-dashboard-menu-icon.png';
		$asp_slug  = untrailingslashit( ASPMain::$products_slug );
		$args      = array(
			'labels'             => $labels,
			'capability_type'    => 'post',
			'public'             => true,
			'publicly_queryable' => true,
			'capability_type'    => 'post',
			'query_var'          => true,
			'has_archive'        => false,
			'hierarchical'       => false,
			'rewrite'            => array( 'slug' => $asp_slug ),
			'supports'           => array( 'title' ),
			'show_ui'            => true,
			'show_in_nav_menus'  => true,
			'show_in_admin_bar'  => true,
			'menu_position'      => 80,
			'menu_icon'          => $menu_icon,
		);

		$args = apply_filters( 'asp_products_post_type_before_register', $args );

		register_post_type( ASPMain::$products_slug, $args );

		//add custom columns for list view
		add_filter( 'manage_' . ASPMain::$products_slug . '_posts_columns', array( $this, 'manage_columns' ) );
		add_action( 'manage_' . ASPMain::$products_slug . '_posts_custom_column', array( $this, 'manage_custom_columns' ), 10, 2 );
		//set custom columns sortable
		add_filter( 'manage_edit-' . ASPMain::$products_slug . '_sortable_columns', array( $this, 'manage_sortable_columns' ) );
		//set custom messages on post save\update etc.
		add_filter( 'post_updated_messages', array( $this, 'post_updated_messages' ) );

		// Temp products post type
		$labels   = array(
			'name'               => _x( 'Products', 'Post Type General Name', 'stripe-payments' ),
			'singular_name'      => _x( 'Product', 'Post Type Singular Name', 'stripe-payments' ),
			'all_items'          => __( 'Products', 'stripe-payments' ),
			'view_item'          => __( 'View Product', 'stripe-payments' ),
			'add_new_item'       => __( 'Add New Product', 'stripe-payments' ),
			'add_new'            => __( 'Add New Product', 'stripe-payments' ),
			'edit_item'          => __( 'Edit Product', 'stripe-payments' ),
			'update_item'        => __( 'Update Products', 'stripe-payments' ),
			'search_items'       => __( 'Search Product', 'stripe-payments' ),
			'not_found'          => __( 'Not found', 'stripe-payments' ),
			'not_found_in_trash' => __( 'Not found in Trash', 'stripe-payments' ),
		);
		$asp_slug = untrailingslashit( ASPMain::$temp_prod_slug );
		$args     = array(
			'labels'             => $labels,
			'capability_type'    => 'post',
			'public'             => false,
			'publicly_queryable' => false,
			'capability_type'    => 'post',
			'query_var'          => true,
			'has_archive'        => false,
			'hierarchical'       => false,
			'rewrite'            => array( 'slug' => $asp_slug ),
			'supports'           => array( 'title' ),
			'show_ui'            => false,
			'show_in_nav_menus'  => false,
			'show_in_admin_bar'  => false,
			'menu_position'      => 80,
			'menu_icon'          => $menu_icon,
		);
	}

	public function post_updated_messages( $messages ) {
		$post      = get_post();
		$post_type = get_post_type( $post );
		$slug      = ASPMain::$products_slug;
		if ( ASPMain::$products_slug === $post_type ) {
			$permalink             = get_permalink( $post->ID );
			$view_link             = sprintf( ' <a href="%s">%s</a>', esc_url( $permalink ), __( 'View product', 'stripe-payments' ) );
			$preview_permalink     = add_query_arg( 'preview', 'true', $permalink );
			$preview_link          = sprintf( ' <a target="_blank" href="%s">%s</a>', esc_url( $preview_permalink ), __( 'Preview product', 'stripe-payments' ) );
			$messages[ $slug ]     = $messages['post'];
			$messages[ $slug ][1]  = __( 'Product updated.', 'stripe-payments' ) . $view_link;
			$messages[ $slug ][4]  = __( 'Product updated.', 'stripe-payments' );
			$messages[ $slug ][6]  = __( 'Product published.', 'stripe-payments' ) . $view_link;
			$messages[ $slug ][7]  = __( 'Product saved.', 'stripe-payments' );
			$messages[ $slug ][8]  = __( 'Product submitted.', 'stripe-payments' ) . $preview_link;
			$messages[ $slug ][10] = __( 'Product draft updated.', 'stripe-payments' ) . $preview_link;
		}
		return $messages;
	}

	public function manage_columns( $columns ) {
		unset( $columns );
		$columns = array(
			'thumbnail' => __( 'Thumbnail', 'stripe-payments' ),
			'title'     => __( 'Product Name', 'stripe-payments' ),
			'id'        => __( 'ID', 'stripe-payments' ),
			'price'     => __( 'Price', 'stripe-payments' ),
			'stock'     => __( 'Stock', 'stripe-payments' ),
			'shortcode' => __( 'Shortcode', 'stripe-payments' ),
			'date'      => __( 'Date', 'stripe-payments' ),
		);
		return $columns;
	}

	public function manage_custom_columns( $column, $post_id ) {
		switch ( $column ) {
			case 'id':
				echo esc_html( $post_id );
				break;
			case 'stock':
				if ( get_post_meta( $post_id, 'asp_product_enable_stock', true ) ) {
					$stock_items = get_post_meta( $post_id, 'asp_product_stock_items', true );
					echo ! $stock_items ? __( 'Out of stock', 'stripe-payments' ) : $stock_items;
				} else {
					echo '—';
				}
				break;
			case 'thumbnail':
				$thumb_url = ASP_Utils::get_small_product_thumb( $post_id );
				if ( ! $thumb_url ) {
					$thumb_url = WP_ASP_PLUGIN_URL . '/assets/product-thumb-placeholder.png';
				}
				$edit_link = get_edit_post_link( $post_id );
				$title     = __( 'Edit Product', 'stripe-payments' );
				?>
		<span class="asp-product-thumbnail-container">
			<a href="<?php echo esc_attr( $edit_link ); ?>">
			<img src="<?php echo esc_attr( $thumb_url ); ?>" title="<?php echo $title; ?>">
			</a>
		</span>
				<?php
				break;
			case 'price':
				$price    = get_post_meta( $post_id, 'asp_product_price', true );
				$currency = get_post_meta( $post_id, 'asp_product_currency', true );
				if ( ! $currency ) {
					//we need to use default currency
					$asp      = AcceptStripePayments::get_instance();
					$currency = $asp->get_setting( 'currency_code' );
				}
				if ( $price ) {
					$output = AcceptStripePayments::formatted_price( $price, $currency );
				} else {
					$output = __( 'Custom', 'stripe-payments' );
				}
				//let's apply filter to let addons change price column output if needed
				$output = apply_filters( 'asp_products_table_price_column', $output, $price, $currency, $post_id );
				echo $output;
				break;
			case 'shortcode':
				?>
		<input type="text" name="asp_product_shortcode" class="asp-select-on-click" readonly value="[asp_product id=&quot;<?php echo esc_attr( $post_id ); ?>&quot;]">
				<?php
				break;
		}
	}

	public function manage_sortable_columns( $columns ) {
		$columns['id']    = 'id';
		$columns['price'] = 'price';
		$columns['stock'] = 'stock';
		return $columns;
	}

}
