=== Stripe Payments ===
Contributors: Tips and Tricks HQ, wptipsntricks, alexanderfoxc
Donate link: https://stripe-plugins.com
Tags: stripe, stripe payments, stripe gateway, payment, payments, button, shortcode, digital goods, payment gateway, instant payment, commerce, digital downloads, downloads, e-commerce, e-store, ecommerce, eshop, donation
Requires at least: 4.7
Tested up to: 4.9
Stable tag: 1.8.9
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Easily accept payments on your WordPress site via Stripe payment gateway.

== Description ==

The Stripe Payments plugin allows you to accept credit card payments via Stripe payment gateway on your WordPress site easily.

It has a simple shortcode that lets you put Stripe "Buy Now" buttons anywhere on your site for a product or service. You can accept donation via Stripe also.

One click payment via Stripe with a remember me feature. Responsive design so it is compatible with all devices and browsers.

Your customers will be redirected to the "Checkout Result" page after the credit card payment. This page shows them the details of the transaction (the item that they just paid for).

The transaction info is also captured in the orders menu of the plugin. You can view all the payments you received from your WordPress admin dashboard.

= Setup and Usage Video =

https://www.youtube.com/watch?v=yQB6IKz73g4

= Checkout Demo Video =

https://www.youtube.com/watch?v=upWqk069Khg

= Features =

* Quick installation and setup.
* Easily take payment for a service from your site via Stripe.
* Sell files, digital goods or downloads using your Stripe merchant account.
* Sell music, video, ebook, PDF or any other digital media files.
* The ultimate plugin to create simple Stripe payment buttons.
* Create buy buttons for your products or services on the fly and embed it anywhere on your site using a user-friendly shortcode.
* Ability to add multiple "Buy Now" buttons to a post/page.
* Allow users to automatically download the digital file after the purchase is complete.
* View purchase orders from your WordPress admin dashboard.
* Accept donation on your WordPress site for a cause.
* Create a stripe payment button widget and add it to your sidebar.
* Ability to collect billing and shipping address of the customer.
* Ability to specify a logo or thumbnail image for the item that will get shown in the stripe payment window.
* Ability to customize the Stripe buy now button text from the shortcode.
* Ability to customize the Stripe buy now button appearance using custom CSS code.
* Ability to specify a custom description for the item/product (this info is captured with the order).
* Option to configure a notification email to be sent to the buyer and seller after the purchase.
* There is an option to allow the customer to enter a custom price amount for your product or service (customer pays what they want).
* Option to accept custom donation amount via Stripe payment gateway.
* Option to collect tax for your products (if applicable).
* Option to collect shipping for your tangible products.
* Option to save the card data on Stripe.
* Ability to have custom thank you page on a per product basis.
* Ability to customize the message on the thank you page using tags.
* Ability to customize the price display with currency symbol.
* Option to send receipt email to your customers from Stripe for each transaction.
* Option to collect a custom input from your customers for products (useful if you are selling products that need special instructions from the customers).
* Stock control option. You can limit the number of quantity available for a product.
* Option to enable Alipay payments. So your customers can pay using their Alipay accounts.
* Option to enable Terms and Conditions that your customers have to accept before they can make a purchase.

The setup is very easy. Once you have installed the plugin, all you need to do is enter your Stripe API credentials in the plugin settings and your website will be ready to accept credit card payments.

You can run it in test mode by specifying test API keys in the plugin settings.

= Shortcode Parameters/Attributes =

There are two ways you can use this plugin to create a "Buy Now" or "Pay" button to accept payment.

Option 1) Create a product in the admin dashboard of this plugin then use a shortcode to put a buy button for that product.

[Check this tutorial](https://stripe-plugins.com/creating-product-stripe-payments-plugin/) for step by step instructions.

Option 2) You can specify the item details in a shortcode to dynamically create a Stripe payment button.

[Check this tutorial](https://stripe-plugins.com/creating-payment-button-dynamically-adding-details-shortcode/) for step by step instructions.

= Detailed Documentation =

For detailed documentation and instructions please check the [WordPress Stripe Payments Plugin](https://www.tipsandtricks-hq.com/ecommerce/wordpress-stripe-plugin-accept-payments-using-stripe) documentation page.

== Installation ==

= Using The WordPress Dashboard =

1. Navigate to "Plugins->Add New" from your dashboard
2. Search for 'stripe payments'
3. Click 'Install Now'
4. Activate the plugin

= Uploading via WordPress Dashboard =

1. Navigate to the "Add New" in the plugins dashboard
2. Navigate to the "Upload" area
3. Select `stripe-payments.zip` from your computer
4. Click "Install Now"
5. Activate the plugin in the Plugin dashboard

= Using FTP =

1. Download `stripe-payments.zip`
2. Extract the `stripe-payments` directory on your computer
3. Upload the `stripe-payments` directory to the `/wp-content/plugins/` directory
4. Activate it from the Plugins dashboard

== Frequently Asked Questions ==

= Can I have multiple payment buttons on a single page? =

Yes, you can have any number of buttons on a single page.

= Can I use it in a WordPress Widgets? =

Yes, you can.

= Can I specify quantity of the item? =

Yes, you can configure it in the product configuration interface.

= Can I change the button label? =

Yes, you can specify the "button_text" attribute in the shortcode to customize it.

= Will the customers receive an email after purchase? =

Yes, you can configure the email settings options in the settings menu of the plugin.

= Is it possible to get notified if payment failed for some reason?

Yes. Go to Stripe Payments > Settings, Email Settings tab and check "Send Email On Payment Failure" option.

= Can It be tested before going live? =

Yes, please visit Stripe Payments > Settings screen for options.


== Screenshots ==

1. Stripe Plugin Settings
2. Stripe Plugin Payment Page
3. Stripe Plugin Orders Menu

== Upgrade Notice ==
None.

== Changelog ==

= 1.8.9 =
- Fixed "You passed an empty string for referrer" error which was caused by recent changes in Stripe API.
- Added option to apply decimal separator settings to customer input as well.

= 1.8.8 =
- Added option to add mandatory "I accept the Terms and Conditions" checkbox. Should help to comply with the EU GDPR. Go to Settings -> Advanced Settings tab to configure it as per your needs.
- Fixed zero-decimal currencies tax value was calculated improperly.
- More text available for translation. You can help in translating the plugin to your language [here](https://translate.wordpress.org/projects/wp-plugins/stripe-payments)
- Inline CSS is now minified.
- Some addons-related changes.

= 1.8.7 =
- Added some debug logging message after the notification email is sent by the plugin. This will be useful for troubleshooting email related issue.
- Added Alipay payment option as a free addon - https://stripe-plugins.com/alipay-addon-stripe-payments-plugin/

= 1.8.6 =
- Added basic products stock control functionality.
- Added option to select custom text field description location (placeholder or below input).
- Added links to documentation and add-ons on Settings page.

= 1.8.5 =
- Fixed "Invalid Stripe token" error in Safari on iPad (thanks to temparcweb for reporting and helping to debug).
- Fixed IE11 incompatibility (thanks to temparcweb).
- Additional information is put into debug log and error email if error occurs.
- Customer no longer redirected to download URL if error occurs during payment process.

= 1.8.4 =
- Fixed "Invalid positive integer" error when price is set to 0 in [accept_stripe_payment name] shortcode.

= 1.8.3 =
- Added some more hooks for better addons support.

= 1.8.2 =
- Tax and shipping info is now displayed in standard checkout result page, emails and order info (if applicable).
- New email tags added: {tax}, {tax_amt}, {shipping_amt}.
- Added proper check for minimum amount for following currencies: DKK (2.50-kr.), GPB (£0.30), HKD ($4.00), JPY (¥50), MXN ($10), NOK (3.00-kr.), SEK (3.00-kr.).
- Fixed products page was displaying incorrect number of products per row.
- Fixed product template was improperly displaying price if quantity was greater than 1.
- Fixed tax and shipping being improperly calculated when custom quantity was enabled.
- Various small bugfixes and improvements.

= 1.8.1 =
- New shortcode parameter added: compat_mode="1". Useful if you get "Invalid Stripe Token" error if using visual page builders.
- Added additional email tags: {item_price}, {item_price_curr}, {currency}, {currency_code}.
- Added {purchase_amt_curr} email tag to display formatted amount with currency symbol.
- Fixed {purchase_amt} email tag wasn't showing total purchase amount (was showing item price instead). Also made {purchase_amt} to be formatted according to Price Display Settings.

= 1.8.0 =
- Fixed variable price was improperly handled for products in some cases.
- Added option to make payment buttons not clickable until Javascript libraries are loaded on page view. This prevents "Invalid Stripe Token" errors on some configurations.
- You can customize currency symbol on settings page now.
- Prioritized button_text parameter for product shortcode (useful if you want to have several buttons with different text for same product).
- {custom_field} tag is now supported on custom Thank You page.
- Custom field name and value are now added to Stripe metadata.

= 1.7.9.1 =
- Fixed minor bug related to product quantity.

= 1.7.9 =
- "Send Error Email To" field now accepts coma-separated emails (thanks to pitfallindimate3746 for reporting).
- Added customer_email shortcode parameter which allows to specify customer email in Stripe pop-up (useful if you're dynamically generating payment buttons via do_shortcode() function).
- Minor bug fixes (mostly related to addons).

= 1.7.8 =
- Added Shipping and Tax support for products.
- Merged Price and Currency sections on product edit page.
- Product thumbnail is now displayed in Stripe pop-up. This can be disabled using corresponding option on product edit page.

= 1.7.7 =
- Added "Button CSS Class" and "Show Button Only" parameters to product edit page.
- Added "Thank You Page URL" field to product edit page.
- Added debug logging option to the settings.

= 1.7.6 =
- Fixed PHP warning displayed upon saving product when custom field is not configured (thanks to falcon13 for reporting).
- Plugin text domain is now properly set.

= 1.7.5 =
- Changed some currencies symbols to be more distinctive (e.g. Australian Dollar was using '$' symbol before, now is using 'AU$').
- Updated the language translation POT file.
- Made additional strings available for translation via translate.wordpress.org.
[Click here](https://translate.wordpress.org/projects/wp-plugins/stripe-payments) if you want to help in translating plugin to your language.

= 1.7.4 =
- Added "Send Email On Payment Failure" option to notify admin if payment failed.
- Fixed plugin conflict with WordPress Themes Editor (thanks to natecarlson1 for reporting).

= 1.7.3 =
- Stripe PHP Library updated to v5.8.0.
- Minimum PHP version required is PHP5.3.

= 1.7.2 =
- Added Custom Field to the advanced settings. Custom Field can be used to add an additional text field or checkbox to your buttons to collect an input from your customer.
- Fixed scripts were called too early, which rarely resulted in conflicts with other plugins and themes (thanks to mmeida for reporting and helping out).

= 1.7.1 =
- Fixed "Button key mismatch" error when special characters (like '&') are used in button name (thanks to damhnait for reporting and helping out).
- Removed "Use New Method To Display Buttons" setting. Now all buttons are displayed using the new method.

= 1.7.0 =
- Fixed a PHP warning in the settings menu of the plugin.

= 1.6.9 =
- Added "Send Receipt Email From Stripe" option. You can find this option under the "Email Settings" menu of the plugin.
- [asp_product] shortcode now supports "class" parameter that allows to assign CSS class to the payment button.

= 1.6.8 =
- Added language text-domain to the plugin file header.
- Hopefully fixed plugin conflict with Yoast SEO (thanks to rogbiz for reporting and helping out).
- Added sanitization to the button output. Thanks to Mikko.

= 1.6.7 =
- Amount in order title is formatted corresponding to Price Display Settings.
- Added [Test Mode] to the order title if payment was made in Test mode.
- Notice added to the settings regarding caching plugins.
- A small bug introduced in previous version has been fixed.

= 1.6.6 =
- Separate fields for Stripe Test keys added to the settings page.
- Plugin will now properly handle buttons with same name but different price (thanks to nourrirsafoi for reporting).
- Fixed "Warning: A non-numeric value encountered" when custom amount is used (thanks to rogbiz for reporting).

= 1.6.5 =
- Fixed improper handling of custom amount feature (thanks to triode33 for reporting).
- Added "Processing.." text to payment button to let user know the payment is being processed.

= 1.6.4 =
- The email related settings options have been moved to a separate tab in the settings menu.
- Added a new configuration option to allow customization of the price display settings in the advanced settings tab.
- The price of the item now gets displayed in the product description.
- The plugin automatically creates a "products" page where all your Stripe items/products are listed in a grid display.

= 1.6.3 =
- Improved the description that gets shown in the stripe checkout window when a product has no "short description" specified for it.

= 1.6.2 =
- Updated the Quantity field box in the product edit interface to add more explanation as to how that field works.

= 1.6.1 =
- Stripe plugin's admin menu interface has been reworked to facilitate the addition of new features.
- Added a new interface to add/edit products from the wp admin dashboard. Usage instructions at the following URL:
  https://stripe-plugins.com/creating-product-stripe-payments-plugin/

- There is a new shortcode to embed a Stripe payment button for the products you create in the admin dashboard.
- The existing shortcodes will continue to work as is (no change there).
- The shortcode inserter (in the post/page editor) has been updated. It will allow you to insert both the shortcodes.

= 1.6.0 =
- Stripe button CSS is now inserted before the form to prevent payment buttons from having default theme style for a second before the CSS file actually loaded.
- Updated the settings menu link slug to make it unique.

= 1.5.9 =
- Added "Turn Off "Remember me" Option" setting. When enabled, "Remember me" checkbox will be removed from Stripe's checkout popup.
- Moved "Settings" menu item from WP Settings to a new independent menu called "Stripe Payments".
- Added "thankyou_page_url" shortcode parameter to specify a custom thank you page URL for an item. This can be used to override the default thank you page URL on a per item basis.
- Extended checkout results page customization using [accept_stripe_payment_checkout] and [accept_stripe_payment_checkout_error] shortcodes.
- Instructions on how to customize the thank you page using tags can be found at the following URL:
https://stripe-plugins.com/customize-the-thank-page-message-of-stripe-payments-plugin/

= 1.5.8 =
- Zero-decimal currencies (like JPY) are no longer multiplied by 100.
- Added Italian language translation file to the plugin. Translation was submitted by Daniele Oneta.

= 1.5.7 =
- Added "Stripe Checkout Language" option to the settings.
- The 'asp_stripe_payment_completed' hook now passes the order post ID in the $post_data array.

= 1.5.6 =
- The shipping and billing address email merge tags are usable again (if you use the address parameters in the shortcode).
- The address will be stored correctly in the Stripe Orders menu (if you are collecting address).

= 1.5.5 =
- Reworked the TinyMCE shortcode inserter code a little to fix an issue with saving a post in WP v4.8.

= 1.5.4 =
- Added filter hooks for the notification email subject and body.
- Currency Code on settings page changed from input to select.
- Added "Do Not Save Card Data on Stripe" setting to tell Stripe to not save card information.
- Added a shortcode inserter button to the TinyMCE editor ("Visual" tab on Edit Post\Page screen).
- Updated the Stripe Orders dashboard menu icon.

= 1.5.3 =
- Updated the French language translation file.
- Updated the translation POT file.
- The plugin will show an error if the shortcode doesn't have the "name" field present. This is a required field for the plugin to process the checkout.

= 1.5.2 =
- Added a new option to display the Stripe buttons. It makes connection to Stripe website only when button is clicked, this makes the page with Stripe buttons load a little faster.
- Added French language translation file to the plugin. Translation file was submitted by Claudy GALAIS.

= 1.5.1 =
- There is now an option to send a notification email to the buyer and seller after the purchase. You can configure it in the settings menu of this plugin.
- A custom css class can be specified for the Stripe button to customize the button style.
- The "price" parameter can be omitted in the shortcode to allow the visitors to specify a custom price or donation amount for an item.

= 1.4 =
- Added an improvement so the description also gets captured with the stripe charge (so you can see it in your Stripe account).
- It will also save the description in the stripe orders menu of the plugin.

= 1.3 =
- The transaction ID will now get shown on the thank you page also (after the payment).
- Added more CSS classes on the thank you page message.
- Added a new parameter in the shortcode so you can specify a custom description for the item checkout (if you want to).

= 1.2 =
* Added a new option to show the item logo or thumbnail image in the stripe checkout window.
* Added a new filter so you can add extra Stripe checkout data parameters (if you want to customize it).
* Added a new action hook that is triggered after the payment is complete (asp_stripe_payment_completed).

= 1.1 =
* Added new option to show shipping and billing address in the stipe payment popup window. You can specify a parameter in the shortcode to collect the address.

= 1.0.5 =
* Added a new filter so the checkout result page's output can be customized.
* Added extra details to the thank you page that shows the details of the item that was purchased.

= 1.0.4 =
* Added more instructions to the checkout result page explaining what that page is for.
* Added settings link in the plugins listing page.

= 1.0.3 =
* Added some enhanced security in the form submission.

= 1.0.2 =
* Updated the payment shortcode parameter.

= 1.0.1 =
* First Release
