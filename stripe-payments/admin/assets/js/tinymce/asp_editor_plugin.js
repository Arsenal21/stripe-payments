function asp_show_tab(tabid) {
    jQuery('[data-tabid]').hide();
    jQuery('[data-tabid=' + tabid + ']').show();
    jQuery('a[data-switch-to-tab]').removeClass('nav-tab-active');
    jQuery('a[data-switch-to-tab="' + tabid + '"]').addClass('nav-tab-active');
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
                    jQuery('#asp_form_err').html('&nbsp;');
                });
                asp_show_tab(1);
                var width = jQuery(window).width(),
                        H = jQuery(window).height(),
                        W = (720 < width) ? 720 : width;
                // W = W - 80;
                H = H - 84;
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

jQuery(function () {

    // Run an ajax call to the main.php to get all current CPT items			
    jQuery.post(
            asp_admin_ajax_url,
            {
                action: 'asp_tinymce_get_settings'
            },
            function (response) {
                if (response) {  // ** If response was successful

                    var res = JSON.parse(response);

                    jQuery('#asp_currency').append(res.currency_opts);

                } else {  // ** Else response was unsuccessful
                    alert('Stripe Payments Button AJAX Error! Please deactivate the plugin to permanently dismiss this alert.');
                }
            }
    );

    // Instantiate a form in the wp thickbox window (hidden at start)		
    var form = jQuery('\
<div id="asp-highlight-form">\
<h2 class="nav-tab-wrapper"><a class="nav-tab" href="javascript:void()" data-switch-to-tab="1">General Options</a><a class="nav-tab" href="javascript:void()" data-switch-to-tab="2">Additional Options</a></h2>\
        <table id="highlight-table" class="form-table asp-shortcode-options-table" style="text-align: left">\
	<tr data-tabid="1">\
            <th scope="row">Item Name *</label></th>\
            <td><input type="text" name="asp_name" id="asp_name" class="asp-input-wide">\
            <p class="description">Your item name. This value should be unique so this item can be identified uniquely on the page.</p>\
            </td>\
	</tr>\
	<tr data-tabid="1">\
            <th scope="row">Price</label></th>\
            <td><input type="text" name="asp_price" id="asp_price">\
            <p class="description">Item price. Numbers only, no need to put currency sumbol. Example: 99.95<br />\
            Leave it blank if you want your customers to enter the amount themselves (e.g. for donation button).\
            </p>\
            </td>\
	</tr>\
	<tr data-tabid="1">\
            <th scope="row">Currency</th>\
            <td><select name="asp_currency" id="asp_currency"></select>\
            <p class="description">Leave "(Default)" option selected if you want to use currency specified on settings page.</p>\
            </td>\
	</tr>\
	<tr data-tabid="1">\
            <th scope="row">Quantity</th>\
            <td><input type="text" name="asp_quantity" id="asp_quantity">\
            <p class="description">Specify a custom quantity for the item.<br /></p>\
            </td>\
	</tr>\
	<tr data-tabid="1">\
            <th scope="row">Button Text</th>\
            <td><input type="text" name="asp_button_text" id="asp_button_text">\
            <p class="description">Specify text to be displayed on the button. Leave it blank to use button text specified on settings page.</p>\
            </td>\
	</tr>\
	<tr data-tabid="2">\
            <th scope="row">URL</th>\
            <td><input type="text" name="asp_url" id="asp_url" class="asp-input-wide">\
            <p class="description">URL of your product (if you\'re selling digital products).</p>\
            </td>\
	</tr>\
	<tr data-tabid="2">\
            <th scope="row">Description</th>\
            <td><input type="text" name="asp_description" id="asp_description" class="asp-input-wide">\
            <p class="description">You can optionally add a custom description for the item/product/service that will get shown in the stripe checkout/payment window of the item.</p>\
            </td>\
	</tr>\
	<tr data-tabid="2">\
            <th scope="row">Billing Address</th>\
            <td><input type="checkbox" name="asp_billing_address" id="asp_billing_address">\
            <p class="description">Enable this option to collect customer\'s billing address during the transaction.</p>\
            </td>\
	</tr>\
	<tr data-tabid="2">\
            <th scope="row">Shipping Address</th>\
            <td><input type="checkbox" name="asp_shipping_address" id="asp_shipping_address">\
            <p class="description">Enable this option to collect customer\'s shipping address during the transaction.</p>\
            </td>\
	</tr>\
	<tr data-tabid="2">\
            <th scope="row">Item Logo</th>\
            <td><input type="text" name="asp_item_logo" id="asp_item_logo">\
            <p class="description">You can optionally show an item logo in the Stripe payment window. Specify the logo image URL.</p>\
            </td>\
	</tr>\
	<tr data-tabid="2">\
            <th scope="row">Button CSS Class</th>\
            <td><input type="text" name="asp_css_class" id="asp_css_class">\
            <p class="description">CSS class to be assigned to the button. This is used for styling purposes. You can get additional information <a href="https://www.tipsandtricks-hq.com/customizing-the-payment-button-styles-of-the-stripe-payments-plugin-9071" target="_blank">in this tutorial</a>.</p>\
            </td>\
	</tr>\
        </table>\
        <p id="asp_form_err">\&nbsp;</p>\
	<p class="submit">\
            <input type="button" id="asp-tinymce-submit" class="button-primary" value="Insert Shortcode" name="submit" style=""/>\
	</p>\
    </div>'
            );

    var table = form.find('table');
    form.appendTo('body').hide();  // Hide form

    //bind event to tab buttons click
    jQuery('a[data-switch-to-tab]').click(function (e) {
        e.preventDefault();
        asp_show_tab(jQuery(this).data('switch-to-tab'));
    });

    // handles the click event of the submit button
    form.find('#asp-tinymce-submit').click(function () {

        var name = jQuery('#asp_name').val();
        var price = jQuery('#asp_price').val();
        var currency = jQuery('#asp_currency').val();
        var quantity = jQuery('#asp_quantity').val();
        var url = jQuery('#asp_url').val();
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
    });

});