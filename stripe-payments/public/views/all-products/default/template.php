<?php
wp_enqueue_style( 'dashicons' );

$params = shortcode_atts( array(
    'items_per_page' => '30',
    'sort_by'	 => 'none',
    'sort_order'	 => 'desc',
    'template'	 => '',
    'search_box' => '1',
), $params, 'asp_show_all_products' );

ob_start();
//Page
?>
<link rel="stylesheet" href="<?php echo WP_ASP_PLUGIN_URL; ?>/public/views/all-products/default/style.css" type="text/css" media="all" />
<div class="wp-asp-post-grid wp-asp-grid">
    _%search_box%_
    <div id="wp-asp-members-list">
        _%products_list%_
    </div>
    _%pagination%_
</div>
<?php
$tpl[ 'page' ]			 = ob_get_clean();
ob_start();
//Search box
?>
<form id="wp-asp-search-form" method="GET">
    <div class="wp-asp-listing-search-field">
        <input type="text" class="wp-asp-search-input" name="asp_search" value="_%search_term%_" placeholder="Search ...">
        <button type="submit" class="wp-asp-search-button" value="Search" title="Search"><span class="dashicons dashicons-search"></button>
    </div>
</form>
<div class="wp-asp-search-res-text">
    _%search_result_text%__%clear_search_button%_
</div>
<?php
$tpl[ 'search_box' ]		 = ob_get_clean();
$tpl[ 'clear_search_button' ]	 = ' <a href="_%clear_search_url%_">Clear search</a>';
ob_start();
//Member item
?>
<div class="wp-asp-grid-item wp-asp-product-id-%[product_id]%">
    <div class="wp-asp-product-thumb"><img src="%[product_thumb]%"></div>
    <div class="wp-asp-product-price">%[product_price]%</div>
    <div class="wp-asp-product-name">%[product_name]%</div>
    %[view_product_btn]%
</div>
<?php
$tpl[ 'products_item' ] = ob_get_clean();
$tpl[ 'products_list' ] = '';
$tpl[ 'products_per_row' ] = 3;
$tpl[ 'products_row_start' ] = '<div class="wp-asp-grid-row">';
$tpl[ 'products_row_end' ] = '</div>';
ob_start();
//Pagination
?>
<div class="wp-asp-pagination">
    <ul>
        _%pagination_items%_
    </ul>
</div>
<?php
$tpl[ 'pagination' ]		 = ob_get_clean();
//Pagination item
$tpl[ 'pagination_item' ]		 = '<li><a href="%[url]%">%[page_num]%</a></li>';
//Pagination item - current page
$tpl[ 'pagination_item_current' ]	 = '<li><span>%[page_num]%</span></li>';

//Profile button
$tpl[ 'view_product_btn' ] = '<div class="wp-asp-view-product-btn"><a href="%[product_url]%" class="wp-asp-view-product-lnk"><button>View Item</button></a></div>';


