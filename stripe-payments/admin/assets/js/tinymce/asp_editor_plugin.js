var aspVarsData = {};
aspVarsData.dataReceived = false;

function asp_show_tab(tabid) {
    jQuery('[data-tabid]').hide();
    jQuery('[data-tabid=' + tabid + ']').show();
    jQuery('a[data-switch-to-tab]').removeClass('nav-tab-active');
    jQuery('a[data-switch-to-tab="' + tabid + '"]').addClass('nav-tab-active');
}

function asp_shortcode_type_button_handler() {
    jQuery('#asp-shortcode-type-container').hide();
    jQuery('#' + jQuery(this).attr('data-asp-display')).show();
}

function asp_get_content_and_settings() {
// Run an ajax call to the main.php to get all current CPT items
    if (aspVarsData.dataReceived === false) {
	jQuery.post(
		asp_admin_ajax_url,
		{
		    action: 'asp_tinymce_get_settings'
		},
		function (response) {
		    if (response) {  // ** If response was successful
			aspVarsData.dataReceived = true;
			var res = JSON.parse(response);
			jQuery('#asp-tinymce-container').append(res.content);
			if (res.products_sel === '') {
			    jQuery('#asp_product_select').hide();
			    jQuery('#asp_product_select').siblings('p').hide();
			    jQuery('#asp-error-no-products').show();
			    jQuery('#asp-tinymce-product-submit').attr('disabled', true);
			} else {
			    jQuery('#asp_product_select').append(res.products_sel);
			}
			jQuery('#asp_currency').append(res.currency_opts);
			//bind shortcode select button click event
			jQuery('button.asp-sc-type-sel-btn').on('click', asp_shortcode_type_button_handler);
			//bind event to tab buttons click
			jQuery('a[data-switch-to-tab]').on('click', asp_tab_click_handler);
			// handles the click event of the submit button
			jQuery('#asp-tinymce-submit').on('click', asp_form_submit_hanlder);
			// habdles the click event of the product submit button

			jQuery('#asp-tinymce-product-submit').on('click', asp_product_submit_handler);
			asp_show_tab(1);

		    } else {  // ** Else response was unsuccessful
			alert('Stripe Payments Button AJAX Error! Please deactivate the plugin to permanently dismiss this alert.');
		    }
		}
	);
    }
}

(function () {

    tinymce.create('tinymce.plugins.aspShortcode', {
	/**
	 * Initializes the plugin, this will be executed after the plugin has been created.
	 * This call is done before the editor instance has finished it's initialization so use the onInit event
	 * of the editor instance to intercept that event.
	 *
	 * @param {tinymce.Editor} ed Editor instance that the plugin is initialized in.
	 * @param {string} url Absolute URL to where the plugin is located.
	 */
	init: function (ed, url) {
	    ed.addButton('asp_shortcode', {
		icon: 'asp-shortcode-icon',
		tooltip: 'Stripe Payments Shortcode',
		cmd: 'asp_shortcode'
	    });
	    ed.addCommand('asp_shortcode', function () {

		// bind event on modal close
		jQuery(window).one('tb_unload', function () {
		    jQuery('#asp-tinymce-container').html('');
		    aspVarsData.dataReceived = false;
		});
		var width = jQuery(window).width(),
			H = jQuery(window).height(),
			W = (720 < width) ? 720 : width;
		// W = W - 80;
		H = H - 84;
		asp_get_content_and_settings();
		tb_show('Stripe Payments Insert Shortcode', '#TB_inline?width=' + W + '&height=' + H + '&inlineId=asp-highlight-form');
	    });
	},
	/**
	 * Creates control instances based in the incomming name. This method is normally not
	 * needed since the addButton method of the tinymce.Editor class is a more easy way of adding buttons
	 * but you sometimes need to create more complex controls like listboxes, split buttons etc then this
	 * method can be used to create those.
	 *
	 * @param {String} n Name of the control to create.
	 * @param {tinymce.ControlManager} cm Control manager to use inorder to create new control.
	 * @return {tinymce.ui.Control} New control instance or null if no control was created.
	 */
	createControl: function (n, cm) {
	    return null;
	},
	/**
	 * Returns information about the plugin as a name/value array.
	 * The current keys are longname, author, authorurl, infourl and version.
	 *
	 * @return {Object} Name/value array containing information about the plugin.
	 */
	getInfo: function () {
	    return {
		longname: 'Simple Stripe Payment Button',
		author: 'Tips and Tricks HQ',
		authorurl: 'http://www.tipsandtricks-hq.com/development-center',
		infourl: 'https://www.tipsandtricks-hq.com/ecommerce/wordpress-stripe-plugin-accept-payments-using-stripe',
		version: "1.0"
	    };
	}
    });
    // Register plugin
    tinymce.PluginManager.add('asp_shortcode', tinymce.plugins.aspShortcode);
})();

function asp_tab_click_handler(e) {
    e.preventDefault();
    asp_show_tab(jQuery(this).data('switch-to-tab'));
}

function asp_product_submit_handler() {
    var product_id = jQuery('#asp_product_select').val();
    var shortcode = '[asp_product id="' + product_id + '"]';
    // inserts the shortcode into the active editor
    tinyMCE.activeEditor.execCommand('mceInsertContent', 0, shortcode);  // Send processed shortcode to editor
// close WP thickbox window
    tb_remove();
}

function asp_form_submit_hanlder() {

    var name = jQuery('#asp_name').val();
    var price = jQuery('#asp_price').val();
    var currency = jQuery('#asp_currency').val();
    var quantity = jQuery('#asp_quantity').val();
    var url = jQuery('#asp_url').val();
    var thankyou_page_url = jQuery('#asp_thankyou_page_url').val();
    var description = jQuery('#asp_description').val();
    var button_text = jQuery('#asp_button_text').val();
    var item_logo = jQuery('#asp_item_logo').val();
    var billing_address = jQuery('#asp_billing_address').is(':checked');
    var shipping_address = jQuery('#asp_shipping_address').is(':checked');
    var css_class = jQuery('#asp_css_class').val();
    //Build the shortcode with parameters according to the options
    var shortcode = '[accept_stripe_payment';
    //Add the fancy parameter to the shortcode (if needed
    if (name != '') {
	shortcode += ' name="' + name + '"';
    } else {
	jQuery('#asp_form_err').html('* You must specify item name.');
	return false;
    }

    if (price != '') {
	shortcode += ' price="' + price + '"';
    }

    if (currency != '') {
	shortcode += ' currency="' + currency + '"';
    }
    if (quantity != '') {
	shortcode += ' quantity="' + quantity + '"';
    }

    if (url != '') {
	shortcode += ' url="' + url + '"';
    }

    if (thankyou_page_url != '') {
	shortcode += ' thankyou_page_url="' + thankyou_page_url + '"';
    }

    if (description != '') {
	shortcode += ' description="' + description + '"';
    }

    if (button_text != '') {
	shortcode += ' button_text="' + button_text + '"';
    }
    if (item_logo != '') {
	shortcode += ' item_logo="' + item_logo + '"';
    }
    if (billing_address) {
	shortcode += ' billing_address="1"';
    }
    if (shipping_address) {
	shortcode += ' shipping_address="1"';
    }
    if (css_class != '') {
	shortcode += ' class="' + css_class + '"';
    }
    shortcode = shortcode + ']';//End the shortcode

// inserts the shortcode into the active editor
    tinyMCE.activeEditor.execCommand('mceInsertContent', 0, shortcode);  // Send processed shortcode to editor
// close WP thickbox window
    tb_remove();
}

jQuery(function () {

    // Instantiate a form in the wp thickbox window (hidden at start)
    var form = jQuery('<div id="asp-highlight-form"><div id="asp-tinymce-container"></div></div>');

    form.appendTo('body').hide();  // Hide form

});