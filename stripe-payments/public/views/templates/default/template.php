<?php

function asp_get_template( $css_inserted = false ) {
    ob_start();
    ?>
    <div class="asp_product_item">
        <div class="asp_product_item_top">
    	<div class="asp_product_item_thumbnail">
    	    %_thumb_img_%
    	</div>
    	<div class="asp_product_name">
    	    %_name_%
    	</div>
        </div>
        <div style="clear:both;"></div>
        <div class="asp_product_description">
    	%_description_%
        </div>
        <div class="asp_price_container">
    	<span class="asp_price_amount">%_price_%</span> <span class="asp_new_price_amount"></span> <span class="asp_quantity">%_quantity_%</span>
    	<div class="asp_under_price_line">%_under_price_line_%</div>
        </div>
        <div class="asp_product_buy_button">
    	%_buy_btn_%
        </div>
    </div>
    <?php
    $tpl = ob_get_clean();
    if ( ! $css_inserted ) {
	$tpl = "<link rel='stylesheet' href='" . WP_ASP_PLUGIN_URL . '/public/views/templates/default/style.css' . "' type='text/css' media='all' />" . $tpl;
    }
    return $tpl;
}

function asp_get_post_template( $css_inserted = false ) {
    ob_start();
    ?>
    <div class = "asp_post_item">
        <div class = "asp_post_item_top">
    	<div class = "asp_post_thumbnail">
    	    %_thumb_img_%
    	</div>
    	<div class = "asp_post_title">
    	    %_name_%
    	</div>
    	<div class = "asp_post_description">
    	    %_description_%
    	</div>
    	<div class="asp_price_container">
    	    <span class="asp_price_amount">%_price_%</span> <span class="asp_new_price_amount"></span> <span class="asp_quantity">%_quantity_%</span>
    	    <div class="asp_under_price_line">%_under_price_line_%</div>
    	</div>
    	<div class="asp_product_buy_button">
    	    %_buy_btn_%
    	</div>
        </div>
    </div>
    <?php
    $tpl = ob_get_clean();
    if ( ! $css_inserted ) {
	$tpl = "<link rel='stylesheet' href='" . WP_ASP_PLUGIN_URL . '/public/views/templates/default/style.css' . "' type='text/css' media='all' />" . $tpl;
    }
    return $tpl;
}
