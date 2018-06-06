<?php

class AcceptStripePayments_CouponsAdmin {

    var $POST_SLUG = 'asp_coupons';

    function __construct() {
	add_action( 'init', array( $this, 'register_post_type' ) );
	if ( is_admin() ) {
	    add_action( 'admin_menu', array( $this, 'add_menu' ) );
	}
    }

    function register_post_type() {
	$args = array(
	    'supports'		 => array( '' ),
	    'hierarchical'		 => false,
	    'public'		 => false,
	    'show_ui'		 => false,
	    'can_export'		 => false,
	    'has_archive'		 => false,
	    'exclude_from_search'	 => true,
	    'publicly_queryable'	 => false,
	    'capability_type'	 => 'post',
	);
	register_post_type( $this->POST_SLUG, $args );
    }

    function add_menu() {
	add_submenu_page( 'edit.php?post_type=' . ASPMain::$products_slug, __( 'Coupons', 'stripe-payments' ), __( 'Coupons', 'stripe-payments' ), 'manage_options', 'stripe-payments-coupons', array( $this, 'display_coupons_menu_page' ) );
    }

    function save_coupon() {
	$post			 = array();
	$post[ 'post_title' ]	 = '';
	$post[ 'post_status' ]	 = 'publish';
	$post[ 'content' ]	 = '';
	$post[ 'post_type' ]	 = $this->POST_SLUG;
	$post_id		 = wp_insert_post( $post );
	update_post_meta( $post_id, 'coupon', 'GET20OFF' );
    }

    function display_coupons_menu_page() {

	$coupons_tbl = new ASPCouponsTable( );
	$coupons_tbl->prepare_items();
	?>

	<div class="wrap">
	    <h2><?php _e( 'Coupons', 'stripe-payments' ); ?></h2>
	    <form method="post">
		<table class="form-table">
		    <tr>
			<th scope="row"><?php _e( 'Enable Coupons', 'stripe-payments' ); ?></th>
			<td>
			    <input type="checkbox" name="asp_coupons_opts[coupons_enabled]">
			    <p class="description"><?php _e( 'Enables Coupons functionality.', 'stripe-payments' ); ?></p>
			</td>
		    </tr>
		</table>
		<h2><?php _e( 'Add A Coupon', 'stripe-payments' ); ?></h2>
		<?php $coupons_tbl->display(); ?>
	    </form>
	</div>
	<?php
    }

}

class ASPCouponsTable extends WP_List_Table {

    public function prepare_items() {
	$columns	 = $this->get_columns();
//	$hidden			 = $this->get_hidden_columns();
	$sortable	 = $this->get_sortable_columns();

	$args = array(
	    'posts_per_page' => -1,
	    'offset'	 => 0,
	    'post_type'	 => 'asp_coupons',
	);

	$coupons = get_posts( $args );

	$data = array();

	foreach ( $coupons as $coupon ) {
	    $id		 = $coupon->ID;
	    $coupon_str	 = get_post_meta( $id, 'coupon', true );
	    $data[]		 = array(
		'id'	 => $id,
		'coupon' => $coupon_str,
	    );
	}
	usort( $data, array( &$this, 'sort_data' ) );
	$perPage		 = 10;
	$currentPage		 = $this->get_pagenum();
	$totalItems		 = count( $data );
	$this->set_pagination_args( array(
	    'total_items'	 => $totalItems,
	    'per_page'	 => $perPage
	) );
	$data			 = array_slice( $data, (($currentPage - 1) * $perPage ), $perPage );
	$this->_column_headers	 = array( $columns, array(), $sortable );
	$this->items		 = $data;
    }

    public function get_columns() {
	$columns = array(
	    'id'	 => 'ID',
	    'coupon' => 'Coupon',
	);
	return $columns;
    }

    public function column_default( $item, $column_name ) {
	switch ( $column_name ) {
	    case 'id':
	    case 'coupon':
		return $item[ $column_name ];
	    default:
		return print_r( $item, true );
	}
    }

    public function get_sortable_columns() {
	return array( 'coupon' => array( 'coupon', false ), 'id' => array( 'id', false ) );
    }

    private function sort_data( $a, $b ) {
	// Set defaults
	$orderby = 'title';
	$order	 = 'asc';
	// If orderby is set, use this as the sort column
	if ( ! empty( $_GET[ 'orderby' ] ) ) {
	    $orderby = $_GET[ 'orderby' ];
	}
	// If order is set use this as the order
	if ( ! empty( $_GET[ 'order' ] ) ) {
	    $order = $_GET[ 'order' ];
	}
	$result = strcmp( $a[ $orderby ], $b[ $orderby ] );
	if ( $order === 'asc' ) {
	    return $result;
	}
	return -$result;
    }

}

new AcceptStripePayments_CouponsAdmin();
