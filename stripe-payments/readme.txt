=== Stripe Payments ===
Contributors: Tips and Tricks HQ, wptipsntricks, alexanderfoxc
Donate link: https://s-plugins.com
Tags: stripe, stripe payments, stripe gateway, payment, payments, button, shortcode, digital goods, payment gateway, instant payment, commerce, digital downloads, downloads, e-commerce, e-store, ecommerce, eshop, donation
Requires at least: 4.7
Tested up to: 5.3
Requires PHP: 5.4
Stable tag: 2.0.12
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
* Option to send receipt email to your customers from Stripe for each transaction.
* Option to collect a custom input from your customers for products (useful if you are selling products that need special instructions from the customers).
* Stock control option. You can limit the number of quantity available for a product.
* Option to enable Alipay payments. So your customers can pay using their Alipay accounts.
* Option to enable Terms and Conditions that your customers have to accept before they can make a purchase.
* Ability to configure variable products. You can charge different amount for different options of the product.
* 3D Secure payments compatible.
* Strong Customer Authentication (SCA) Compliant.

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

= 2.0.12 =
- Fixed subscription payment with tax validity check.
- Fixed subscription payment invalid tax amount displayed on checkout results page.
- Added support for [iDEAL](https://s-plugins.com/stripe-ideal-payment-addon/) and [Country Autodetect](https://s-plugins.com/stripe-country-autodetect-addon/) addons.

= 2.0.11 =
- Fixed an issue with duplicate buy emails being sent on some browsers.
- Fixed "Processing" text was showing in the product insert block.
- Added the Spanish language translation file.
- Fixed subscription payment with tax not processing correctly.

= 2.0.10 =
- [New API]: Fixed selected variations weren't properly passed upon payment form submission.
- [New API]: Fixed "Invalid email address" error when APM addon is installed and "Send Receipt Email From Stripe" option enabled.
- [New API]: Added Alipay addon support. Requires Alipay addon version 2.0.0+.
- Legacy API is disabled by default for fresh plugin installations.
- Added admin area notice regarding SCA compatibility.
- Added Bosnia and Herzegovina Convertible Mark (BAM) currency.

= 2.0.9 =
- [New API]: Fixed zero cents currencies were divided by 100 on payments result page and order info.
- [New API]: Fixed Stripe receipt wasn't sent if "Do Not Save Card Data on Stripe" option enabled.
- [New API]: ZIP\postal code is now requested on credit card input when "Validate ZIP Code" option enabled and address collection disabled for a product.
- [New API]: Product name and ID are now saved in payment metadata on Stripe Dashboard.
- [New API]: If product short description is empty, product name is used for payment details "Description" on Stripe Dashboard.
- [New API]: Added spinner after payment popup form submission.
- [New API]: Single variation in a row is now full-width (instead of half-width before).
- [New API]: Adjusted payment popup display on mobile devices.

= 2.0.8 =
- [New API]: Added compatibility with optimization plugins that do minify\combine JavaScript.
- [New API]: Fixed currency wasn't properly updated for variable currency payments in some situations. 
- [New API]: Fixed product with stock control enabled could produce fatal error during payment process under some circumstances. 
- [New API]: Fixed checkbox custom field display issue.
- [New API]: Added mandatory inputs validation for browsers that don't provide it.
- [New API]: Fixed rare issue that could break payment process if payment button was clicked multiple times.
- [New API]: Added per-product "Show Order Total On Payment Popup" option. When enabled, it displays detailed financial info (tax, variations, coupon etc) on payment popup.
- [New API]: Added support for reCaptcha addon. reCatpcha addon version 2.0.0+ is required to work with new payment popup.
- [New API]: Added support for Additional Payment Methods addon. APM addon version 2.0.0+ is required to work with new payment popup.
- [New API]: Some visual tweaks and fixes for payment popup.
- Fixed custom input wasn't properly validating and honoring "Mandatory" option if position was "Below Button" (legacy API only).
- Added new product edit interface. To see it in action, check the "Enable Compact Product Edit Interface" checkbox in the Advanced Settings tab.

= 2.0.7.1 =
- [New API]: Fixed visual bug on payment popup when coupons are enabled.

= 2.0.7 =
- [New API]: Moved process_ipn action to wp_loaded hook. Should fix issues with "The site is experiencing technical problems" error during payment processing on some configurations.
- [New API]: Restyled payment popup for better responsiveness on mobile devices.
- [New API]: Updated Stripe PHP SDK library to 6.43.1.
- [New API]: Added debug log warning when another Stripe PHP SDK is loaded. Warning is logged once per 6 hours in order to not flood the log.
- [New API]: Payment popup server interaction errors are now more informative.
- [New Api]: Removed excess "Coupon is invalid for the product" debug log message when no coupon code provided.

= 2.0.6 =
- [New API]: Customer info and card data is now saved on Stripe unless "Do Not Save Card Data on Stripe" option is enabled.
- [New API]: Added "Prefill Logged In User Name and Email" option to prefill corresponding payment popup fields with logged in user's name and email.
- Added 'asp_stripe_order_register_post_type_args' filter to override 'stripe-order' post type args.

= 2.0.5 =
- [New API]: Fixed payment popup was not scrollable on Apple devices.
- [New API]: Added "Send Emails In Parallel" option that should speed up checkout process.
- [New API]: Removed excess output for buttons when new API is used.

= 2.0.4 =
- [New API]: Added "Popup Default Country" option that sets default country on payment popup for billing and shipping address.
- Minor bugfixes for the new API.

= 2.0.3 =
- [New API]: Fixed new API was enabled by default. Now you need to disable "Enable Legacy Checkout API" option on Advanced Settings tab to use new API. 
- [New API]: Fixed popup button text was empty on plugin update. 
- [New API]: Fixed popup form was not scrollable on some mobile devices.
- [New API]: Added white background for popup item logo.
- [New API]: Fixed product description wasn't passed to Stripe.
- [New API]: Fixed customer_email shortcode parameter was ignored.
- [New API]: Added customer_name shortcode parameter to prefill customer name in payment popup.

= 2.0.2 =
- [New API]: Fixed checkout error when both billing and shipping address collection enabled.
- [New API]: Fixed popup JavaScript caching issue.

= 2.0.1 =
- Important: This is a major upgrade. We advise that you backup your site before upgrading the plugin.
- Added new SCA compliant API for checkout. There is a new payment popup that utilizes SCA-complaint payment process.
- You can enable the new SCA compliant checkout by going to the "Advanced Settings Menu" of the plugin then unchecking the "Enable Legacy Checkout API" checkbox.
- By default it uses the legacy API to ensure that it is a smooth upgrade. We don't want your checkout process to be broken after the upgrade.

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
- Tweaks for better compatibility with various page builders.
- Other minor bugfixes.

Previous versions changelog available in changelog.txt file.
