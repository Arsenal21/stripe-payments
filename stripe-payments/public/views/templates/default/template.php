<?php

function asp_get_template() {
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
        <div class="asp_product_buy_button">
    	%_buy_btn_%
        </div>
    </div>
    <?php
    $tpl = ob_get_clean();
    return $tpl;
}

wp_enqueue_style( 'asp-products-template-styles' );
