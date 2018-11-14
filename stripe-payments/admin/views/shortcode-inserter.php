<style>
    div#asp-shortcode-type-container {
	text-align: center;
	margin: 0 auto;
    }
    #asp-default-shortcode-container, #asp-product-shortcode-container {
	display: none;
    }
    table.asp-shortcode-options-table sup {
	color: red;
    }
    div#asp-type-select-buttons button.asp-sc-type-sel-btn {
	padding: 5px 20px;
	height: auto;
	font-size: 14px;
    }
    div#asp-type-select-buttons div {
	margin-top: 20px;
	margin-bottom: 10px;
    }
    #asp-error-no-products {
	display: none;
	color: red;
    }
</style>
<div id="asp-shortcode-type-container">
    <h2>Please select shortcode type you want to insert</h2>
    <div id="asp-type-select-buttons">
	<div>
	    <button class="button button-primary asp-sc-type-sel-btn" data-asp-display="asp-product-shortcode-container">Product Shortcode</button>
	</div>
	<div>
	    <button class="button button-primary asp-sc-type-sel-btn" data-asp-display="asp-default-shortcode-container">Custom Shortcode</button>
	</div>
    </div>
</div>
<div id="asp-product-shortcode-container">
    <table class="form-table" style="text-align: left">
	<tr>
	    <th scope="row">Product</th>
	    <td><select id ="asp_product_select" name="asp_product"></select>
		<p class="description">Select product you want to insert.</p>
		<span id="asp-error-no-products">No products found. You need to <a href="<?php echo admin_url() . 'post-new.php?post_type=' . ASPMain::$products_slug; ?>" tagert="_blank">create a product</a> first.</span>
	    </td>
	</tr>
    </table>
    <p class="submit">
	<input type="button" id="asp-tinymce-product-submit" class="button-primary" value="Insert Shortcode" />
    </p>
</div>
<div id="asp-default-shortcode-container">
    <h2 class="nav-tab-wrapper"><a class="nav-tab" href="javascript:void()" data-switch-to-tab="1">General Options</a><a class="nav-tab" href="javascript:void()" data-switch-to-tab="2">Additional Options</a></h2>
    <table id="highlight-table" class="form-table asp-shortcode-options-table" style="text-align: left">
	<tr data-tabid="1">
	    <th scope="row">Item Name <sup>*</sup></th>
	    <td><input type="text" name="asp_name" id="asp_name" class="asp-input-wide">
		<p class="description">Your item name. This value should be unique so this item can be identified uniquely on the page.</p>
	    </td>
	</tr>
	<tr data-tabid="1">
	    <th scope="row">Price</th>
	    <td><input type="text" name="asp_price" id="asp_price">
		<p class="description">Item price. Numbers only, no need to put currency symbol. Example: 99.95<br />
		    Leave it blank if you want your customers to enter the amount themselves (e.g. for donation button).
		</p>
	    </td>
	</tr>
	<tr data-tabid="1">
	    <th scope="row">Currency</th>
	    <td><select name="asp_currency" id="asp_currency"></select>
		<p class="description">Leave "(Default)" option selected if you want to use currency specified on settings page.</p>
	    </td>
	</tr>
	<tr data-tabid="1">
	    <th scope="row">Quantity</th>
	    <td><input type="text" name="asp_quantity" id="asp_quantity">
		<p class="description">Specify a custom quantity for the item.<br /></p>
	    </td>
	</tr>
	<tr data-tabid="1">
	    <th scope="row">Button Text</th>
	    <td><input type="text" name="asp_button_text" id="asp_button_text" class="asp-input-wide">
		<p class="description">Specify text to be displayed on the button. Leave it blank to use button text specified on settings page.</p>
	    </td>
	</tr>
	<tr data-tabid="2">
	    <th scope="row">URL</th>
	    <td><input type="text" name="asp_url" id="asp_url" class="asp-input-wide">
		<p class="description">URL of your product (if you're selling digital products).</p>
	    </td>
	</tr>
	<tr data-tabid="2">
	    <th scope="row">Thank You Page URL</th>
	    <td><input type="text" name="asp_thankyou_page_url" id="asp_thankyou_page_url" class="asp-input-wide">
		<p class="description">Page URL where users will be redirected after the payment is processed for this item. Useful if you want to make a custom "Thank you" page for this item. Leave it blank if you want to use the default URL specified in plugin settings.</p>
	    </td>
	</tr>
	<tr data-tabid="2">
	    <th scope="row">Description</th>
	    <td><input type="text" name="asp_description" id="asp_description" class="asp-input-wide">
		<p class="description">You can optionally add a custom description for the item/product/service that will get shown in the stripe checkout/payment window of the item.</p>
	    </td>
	</tr>
	<tr data-tabid="2">
	    <th scope="row">Billing Address</th>
	    <td><input type="checkbox" name="asp_billing_address" id="asp_billing_address">
		<p class="description">Enable this option to collect customer's billing address during the transaction.</p>
	    </td>
	</tr>
	<tr data-tabid="2">
	    <th scope="row">Shipping Address</th>
	    <td><input type="checkbox" name="asp_shipping_address" id="asp_shipping_address">
		<p class="description">Enable this option to collect customer's shipping address during the transaction.</p>
	    </td>
	</tr>
	<tr data-tabid="2">
	    <th scope="row">Item Logo</th>
	    <td><input type="text" name="asp_item_logo" id="asp_item_logo" class="asp-input-wide">
		<p class="description">You can optionally show an item logo in the Stripe payment window. Specify the logo image URL.</p>
	    </td>
	</tr>
	<tr data-tabid="2">
	    <th scope="row">Button CSS Class</th>
	    <td><input type="text" name="asp_css_class" id="asp_css_class" class="asp-input-wide">
		<p class="description">CSS class to be assigned to the button. This is used for styling purposes. You can get additional information <a href="https://s-plugins.com/customize-stripe-payment-button-appearance-using-css/" target="_blank">in this tutorial</a>.</p>
	    </td>
	</tr>
    </table>
    <p id="asp_form_err">&nbsp;</p>
    <p class="submit">
	<input type="button" id="asp-tinymce-submit" class="button-primary" value="Insert Shortcode" name="submit" style=""/>
    </p>
</div>