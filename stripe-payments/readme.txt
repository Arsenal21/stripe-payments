=== Accept Stripe Payments ===
Contributors: Tips and Tricks HQ, wptipsntricks, alexanderfoxc
Donate link: https://s-plugins.com
Tags: stripe, stripe payments, stripe gateway, payment, payments, button, shortcode, digital goods, payment gateway, instant payment, commerce, digital downloads, downloads, e-commerce, e-store, ecommerce, eshop, donation
Requires at least: 5.0
Tested up to: 6.5
Requires PHP: 7.0
Stable tag: 2.0.85
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

https://www.youtube.com/watch?v=L0n_jlEhmoA

= Checkout Demo Video =

https://www.youtube.com/watch?v=b6owgRBTUwA

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
* Ability to use a link URL to create custom payment button for your products.
* Option to send receipt email to your customers from Stripe for each transaction.
* Option to collect a custom input from your customers for products (useful if you are selling products that need special instructions from the customers).
* Stock control option. You can limit the number of quantity available for a product.
* Option to enable Alipay payments. So your customers can pay using their Alipay accounts.
* Option to enable Terms and Conditions that your customers have to accept before they can make a purchase.
* Ability to configure variable products. You can charge different amount for different options of the product.
* Ability to create "Authorize Only" products. You can hold funds on a card then capture it later.
* 3D Secure payments compatible.
* Strong Customer Authentication (SCA) Compliant.

The setup is very easy. Once you have installed the plugin, all you need to do is enter your Stripe API credentials in the plugin settings and your website will be ready to accept credit card payments.

You can run it in test mode by specifying test API keys in the plugin settings.

= Shortcode Parameters/Attributes =

This plugin offers a shortcode and a block that allows you to create a 'Buy Now' or 'Pay' button for accepting payments.

First, create a product in the plugin's admin dashboard. Then, use the provided shortcode or block to embed a buy button for that specific product.

[Check this tutorial](https://s-plugins.com/creating-product-stripe-payments-plugin/) for step by step instructions.

= Detailed Documentation =

For detailed documentation and instructions please check the [WordPress Stripe Payments Plugin](https://s-plugins.com/stripe-payments-plugin-tutorials/) documentation page.

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

= 2.0.85 =
- The order item will capture and display the username of the logged-in user, provided the user is logged in at the time of the purchase.
- Added new email merge tag {logged_in_user_name} to capture logged in user's username (if available).
- Added new filter 'asp_get_logged_in_user_info' to allow customization of the logged in user info.
- Scaled down the reCAPTCHA badge size using CSS for better mobile screen compatibility.
- Updated the language translation POT file.
- Fixed a price validation bug for checkbox type variation.

= 2.0.84 =
- Added a new option to customize the payment button text (in the popup window) on a per product basis.
- Added support for 'coupon_code' query param to apply coupon directly in the product link url feature. 
- Corrected the issue causing an error when a product is created or updated with an empty variation group.
- Regional tax variation deletion issue fixed.
- Added support for Custom Fields Addon's new fields position feature.
- Added an API pre-submission amount validation function.

= 2.0.83 =
- Fixed a recent PHP8 related change that caused an issue in the download URL function.

= 2.0.82 =
- Fixed an issue with the coupon code delete function.
- Added a new utility function to retrieve the current page URL.
- New action hook in the payment popup window.

= 2.0.81 =
- The currency mismatch error message won't be displayed when the product is configured to use a variable currency.

= 2.0.80 =
- Added a currency check when the create payment intent request is made.
- Added more sanitization to the billing_details object data.

= 2.0.79 =
- New shortcode to show available quantity of a product: [asp_available_quantity id="123"]
- Better handling for zip/postal code (when it contains empty space character).
- Some php deprecated warnings has been fixed.
- Fixed a PHP 8.2 related warning.
- Updated the language translation POT file.

= 2.0.78 =
- Refactored some lines of code to remove the use of deprecated FILTER_SANITIZE_STRING filter.
- PHP 8.2 compatibility.

= 2.0.77 =
- Settings UI improvement: the individual captcha configuration fields are now displayed above the transaction rate limiting section.
- New option (Don't Use Cookie) in the advanced settings menu to disable the use of cookie.

= 2.0.76 =
- Improved the sorting by price option in the shop/products page.
- The coupon code option will be enabled by default for variable amount product as well. It can be disabled via the product specific coupon configuration.
- The custom field position for the legacy API option has been removed as it is no longer used.
- Added Bulgarian language option to the checkout language selection field.

= 2.0.75 =
- Sorting option added in the shop/products page via shortcode parameter.
- Visitors can also sort the products in the shop page by latest, sort by title, sort by price.
- Updated the integration with Simple Membership plugin.

Full changelog available [at changelog.txt](https://plugins.svn.wordpress.org/stripe-payments/trunk/changelog.txt)
