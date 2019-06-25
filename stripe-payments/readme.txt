=== Stripe Payments ===
Contributors: Tips and Tricks HQ, wptipsntricks, alexanderfoxc
Donate link: https://s-plugins.com
Tags: stripe, stripe payments, stripe gateway, payment, payments, button, shortcode, digital goods, payment gateway, instant payment, commerce, digital downloads, downloads, e-commerce, e-store, ecommerce, eshop, donation
Requires at least: 4.7
Tested up to: 5.2
Requires PHP: 5.4
Stable tag: 1.9.25
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
* Ability to configure variable products. You can charge different amount for different options of the product.

The setup is very easy. Once you have installed the plugin, all you need to do is enter your Stripe API credentials in the plugin settings and your website will be ready to accept credit card payments.

You can run it in test mode by specifying test API keys in the plugin settings.

= Shortcode Parameters/Attributes =

There are two ways you can use this plugin to create a "Buy Now" or "Pay" button to accept payment.

Option 1) Create a product in the admin dashboard of this plugin then use a shortcode to put a buy button for that product.

[Check this tutorial](https://s-plugins.com/creating-product-stripe-payments-plugin/) for step by step instructions.

Option 2) You can specify the item details in a shortcode to dynamically create a Stripe payment button.

[Check this tutorial](https://s-plugins.com/creating-payment-button-dynamically-adding-details-shortcode/) for step by step instructions.

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

= 1.9.25 =
- Fixed improper frontend total amount display in some circumstances.
- Fixed total amount was displayed instead of item price in some circumstances.
- Fixed issues that could lead to "button key mismatch" error when [accept_stripe_payment] shortcode is used.
- Paragraphs are automatically added to product description when needed.

= 1.9.24 =
- Added {item_url} email tag support.
- Fixed issue with the_content filter usage in product shortcode output that could cause some content duplication.
- Added checkout_lang shortcode parameter which sets checkout popup language for a product.

= 1.9.23 =
- PHP sessions are no longer used for security and better caching purposes.
- Disabled nonce checking for buttons.
- Fixed thankyou_page_url parameter was ignored for [asp_product] shortcode and by some addons.
- Fixed item URL wasn't processed by Secure Downloads addon when some other addons are enabled.
- Fixed frontend total amount calculation display for products with variations and custom amount enabled.
- Custom amount validation errors no longer displayed on page load for products with variations and custom amount enabled.
- Checkout results page no longer displays "Download links" message if there are no downloads set for variations.
- Checkout error message is now displayed even if no [accept_stripe_payment_checkout_error] shortcode inserted on custom checkout results page.
- Frontend amount and quantity inputs are disabled on payment form submit to prevent "Token can't be used more than once" error.
- Fixed zero-cent currencies displaying and handling issues.
- Product description now supports WP embeds.
- Tweaks for better compatability with various page builders.
- Other minor bugfixes.

= 1.9.22 =
- Fixed archive pages list could be messed up when plugin is enabled.
- Fixed the "asp_stripe_payments_checkout_page_result" filter hook not triggering correctly.

= 1.9.21 =
- Fixed frontend discount amount display when custom quantity is enabled and initial quantity is set to 0 or empty.
- Fixed adding groups to existing product with variations caused improper variations placement.
- Fixed tax amount was displayed rounded down on frontend under some circumstances (wasn't affecting actual payment amount).
- Product variations are now added to payment metadata in Stripe account.
- Added admin side notice if required PHP modules are not installed on the server.

= 1.9.20 =
- Added Stripe Payments Product Gutenberg block.
- Added button_only parameter to [asp_product] shortcode. When set to "1", no product title and info is displayed.
- Fixed PHP notices when viewing some products with variations.

= 1.9.19 =
- Fixed issues on Settings page that prevented it from being properly displayed in some versions of Safari browser.
- Stripe Payments menu icon color changed to white to have better contrast with dark menu background.
- Fixed minor HTML-related admin interface issues.

= 1.9.18 =
- Trial subscriptions are now displaying 0 as payment amount on checkout results and email receipts.
Payment button in Stripe pop-up for those now shows "Start Free Trial" instead of payment amount.
Requires Subscriptions addon 1.4.5+
- Added validation for custom filed. You can use your own validation rules via custom JavaScript RegExp.
- Fixed invalid amount was displayed on Stripe pop-up when variable price and quantity is used.
- {product_details} merge tag is available for custom checkout results page.
- Purchase date is now displayed using WP date\time format settings and considers timezone.
- Added option to display product variations as radio buttons (can be set per product on product edit page).

Previous versions changelog available in changelog.txt file.
