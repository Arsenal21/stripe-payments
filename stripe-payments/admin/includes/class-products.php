<?php

class ASPProducts {

    protected static $instance = null;

    function __construct() {
	
    }

    public static function get_instance() {

	// If the single instance hasn't been set, set it now.
	if ( null == self::$instance ) {
	    self::$instance = new self;
	}

	return self::$instance;
    }

    function register_post_type() {

	// Products post type
	$labels		 = array(
	    'name'			 => _x( 'Products', 'Post Type General Name', 'stripe-payments' ),
	    'singular_name'		 => _x( 'Product', 'Post Type Singular Name', 'stripe-payments' ),
	    'menu_name'		 => __( 'Stripe Payments', 'stripe-payments' ),
//	    'parent_item_colon'	 => __( 'Parent Order:', 'stripe-payments' ),
	    'all_items'		 => __( 'Products', 'stripe-payments' ),
	    'view_item'		 => __( 'View Product', 'stripe-payments' ),
	    'add_new_item'		 => __( 'Add New Product', 'stripe-payments' ),
	    'add_new'		 => __( 'Add New Product', 'stripe-payments' ),
	    'edit_item'		 => __( 'Edit Product', 'stripe-payments' ),
	    'update_item'		 => __( 'Update Products', 'stripe-payments' ),
	    'search_items'		 => __( 'Search Product', 'stripe-payments' ),
	    'not_found'		 => __( 'Not found', 'stripe-payments' ),
	    'not_found_in_trash'	 => __( 'Not found in Trash', 'stripe-payments' ),
	);
	$menu_icon	 = WP_ASP_PLUGIN_URL . '/assets/asp-dashboard-menu-icon.png';
	$asp_slug	 = untrailingslashit( ASPMain::$products_slug );
	$args		 = array(
	    'labels'		 => $labels,
	    'capability_type'	 => 'post',
	    'public'		 => true,
	    'publicly_queryable'	 => true,
	    'capability_type'	 => 'post',
	    'query_var'		 => true,
	    'has_archive'		 => true,
	    'hierarchical'		 => false,
	    'rewrite'		 => array( 'slug' => $asp_slug ),
	    'supports'		 => array( 'title' ),
	    'show_ui'		 => true,
	    'show_in_nav_menus'	 => true,
	    'show_in_admin_bar'	 => true,
	    'menu_position'		 => 80,
	    'menu_icon'		 => $menu_icon
	);

	register_post_type( ASPMain::$products_slug, $args );

	//add custom columns for list view
	add_filter( 'manage_' . ASPMain::$products_slug . '_posts_columns', array( $this, 'manage_columns' ) );
	add_action( 'manage_' . ASPMain::$products_slug . '_posts_custom_column', array( $this, 'manage_custom_columns' ), 10, 2 );
	//set custom columns sortable
	add_filter( 'manage_edit-' . ASPMain::$products_slug . '_sortable_columns', array( $this, 'manage_sortable_columns' ) );
	//enqueue css file to style list table and edit product pages
	add_action( 'admin_head', array( $this, 'enqueue_products_style' ) );
    }

    function manage_columns( $columns ) {
	unset( $columns );
	$columns = array(
	    "thumbnail"	 => __( "Thumbnail", 'stripe-payments' ),
	    "title"		 => __( 'Product Name', 'stripe-payments' ),
	    "id"		 => __( "ID", 'stripe-payments' ),
	    "price"		 => __( "Price", 'stripe-payments' ),
	    "shortcode"	 => __( "Shortcode", 'stripe-payments' ),
	    "date"		 => __( "Date", 'stripe-payments' ),
	);
	return $columns;
    }

    function manage_custom_columns( $column, $post_id ) {
	switch ( $column ) {
	    case 'id':
		echo $post_id;
		break;
	    case 'thumbnail':
		$thumb_url = get_post_meta( $post_id, 'asp_product_thumbnail', true );
		if ( ! $thumb_url ) {
		    $thumb_url = WP_ASP_PLUGIN_URL . '/assets/product-thumb-placeholder.png';
		}
		$edit_link	 = get_edit_post_link( $post_id );
		$title		 = __( "Edit Product", 'stripe-payments' );
		?>
		<span class="asp-product-thumbnail-container">
		    <a href="<?php echo esc_attr( $edit_link ); ?>">
			<img src="<?php echo esc_attr( $thumb_url ); ?>" title="<?php echo $title; ?>">
		    </a>
		</span>
		<?php
		break;
	    case 'price':
		$price		 = get_post_meta( $post_id, 'asp_product_price', true );
		if ( $price ) {
		    $currency = get_post_meta( $post_id, 'asp_product_currency', true );
		    if ( ! $currency ) {
			//we need to use default currency
			$asp		 = AcceptStripePayments::get_instance();
			$currency	 = $asp->get_setting( 'currency_code' );
		    }
		    echo AcceptStripePayments::formatted_price($price,$currency);
		} else {
		    echo "Custom";
		}
		break;
	    case 'shortcode':
		?>
		<input type="text" name="asp_product_shortcode" class="asp-select-on-click" readonly value="[asp_product id=&quot;<?php echo $post_id; ?>&quot;]">
	    <?php
	}
    }

    function manage_sortable_columns( $columns ) {
	$columns[ 'id' ]	 = 'id';
	$columns[ 'price' ]	 = 'price';
	return $columns;
    }

    function enqueue_products_style() {
	global $post_type;
	if ( ASPMain::$products_slug == $post_type ) {
	    wp_enqueue_style( 'asp-admin-products-styles', WP_ASP_PLUGIN_URL . '/admin/assets/css/admin-products.css', array(), AcceptStripePayments::VERSION );
	}
    }

}
