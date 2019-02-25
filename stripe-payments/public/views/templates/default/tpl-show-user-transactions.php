<?php

class AcceptStripePayments_tplUserTransactions {

    private $tpl		 = false;
    private $item_tpl	 = false;
    private $add_data_tpl	 = false;
    private $pagination_tpl	 = false;
    private $atts		 = array();

    public function __construct( $atts ) {
	wp_register_style( 'asp-show-user-transactions-css', WP_ASP_PLUGIN_URL . '/public/views/templates/default/show-user-transactions.css', array(), WP_ASP_PLUGIN_VERSION );
	wp_enqueue_style( 'asp-show-user-transactions-css' );
	$this->_get_tpl();
	$this->_get_item_tpl();
	$this->_get_pagination_tpl();
	$this->_get_additional_data_tpl();
	$this->atts = $atts;
    }

    public function build_tpl( $items ) {
	$items_tpl = '';
	foreach ( $items as $item_arr ) {
	    $item_tpl	 = $this->item_tpl;
	    $additional_data = '';
	    if ( ! empty( $item_arr[ 'additional_data' ] ) ) {
		foreach ( $item_arr[ 'additional_data' ] as $add_data ) {
		    foreach ( $add_data as $key => $value )
			$add_data_tpl	 = $this->add_data_tpl;
		    $additional_data .= str_replace( array( '%_key_%', '%_value_%' ), array( $key, $value ), $add_data_tpl );
		}
	    }
	    $item_arr[ 'additional_data' ] = $additional_data;
	    foreach ( $item_arr as $key => $value ) {
		$item_tpl = str_replace( '%_' . $key . '_%', $value, $item_tpl );
	    }
	    $items_tpl .= $item_tpl;
	}
	$out = str_replace( '%_items_%', $items_tpl, $this->tpl );

	$pagination = '';
	if ( $this->atts[ 'total_pages' ] > 1 ) {
	    //pagination required
	    for ( $i = 1; $i <= $this->atts[ 'total_pages' ]; $i ++ ) {
		if ( $this->atts[ 'curr_page' ] == $i ) {
		    $p_tpl = $this->pagination_tpl[ 'current' ];
		} else {
		    $p_tpl = $this->pagination_tpl[ 'default' ];
		}
		$pagination .= str_replace( array( '%_url_%', '%_page_num_%' ), array( add_query_arg( 'asp_page', $i, get_permalink() ), $i ), $p_tpl );
	    }
	}
	$out = str_replace( '%_pagination_%', $pagination, $out );
	return $out;
    }

    private function _get_tpl() {
	if ( $this->tpl ) {
	    return true;
	}
	ob_start();
	?>
	<div class="asp-user-transactions-pagination-cont">
	    <ul>
		%_pagination_%
	    </ul>
	</div>
	<div class="asp-user-transactions-cont">
	    %_items_%
	</div>
	<div class="asp-user-transactions-pagination-cont">
	    <ul>
		%_pagination_%
	    </ul>
	</div>
	<?php
	$this->tpl = ob_get_clean();
	return false;
    }

    private function _get_item_tpl() {
	if ( $this->item_tpl ) {
	    return true;
	}
	ob_start();
	?>
	<div class="asp-user-transactions-item">
	    <div class="asp-user-transaction-item-line asp-user-transaction-product-name"><span><?php _e( 'Name:', 'stripe-payments' ); ?></span> %_product_name_%</div>
	    <div class="asp-user-transaction-item-line asp-user-transaction-product-id"><span><?php _e( 'Item ID:', 'stripe-payments' ); ?></span> %_product_id_%</div>
	    <div class="asp-user-transaction-item-line asp-user-transaction-product-amount"><span><?php _e( 'Amount:', 'stripe-payments' ); ?></span> %_amount_%</div>
	    <div class="asp-user-transaction-item-line asp-user-transaction-product-date"><span><?php _e( 'Date:', 'stripe-payments' ); ?></span> %_date_%</div>
	    <div class="asp-user-transaction-item-line asp-user-transaction-product-additional-data">%_additional_data_%</div>
	</div>
	<?php
	$this->item_tpl = ob_get_clean();
	return false;
    }

    private function _get_additional_data_tpl() {
	if ( $this->add_data_tpl ) {
	    return true;
	}
	ob_start();
	?>
	<div class="asp-user-transaction-product-additional-data-line"><span>%_key_%</span> %_value_%</div>
	<?php
	$this->add_data_tpl = ob_get_clean();
	return false;
    }

    private function _get_pagination_tpl() {
	if ( $this->pagination_tpl ) {
	    return true;
	}
	ob_start();
	?>
	<li>
	    <a href="%_url_%">%_page_num_%</a>
	</li>
	<?php
	$this->pagination_tpl[ 'default' ]	 = ob_get_clean();
	//current page tpl
	ob_start();
	?>
	<li>
	    <span>%_page_num_%</span>
	</li>
	<?php
	$this->pagination_tpl[ 'current' ]	 = ob_get_clean();
	return false;
    }

}
