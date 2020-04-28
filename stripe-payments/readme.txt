=== Stripe Payments ===
Contributors: Tips and Tricks HQ, wptipsntricks, alexanderfoxc
Donate link: https://s-plugins.com
Tags: stripe, stripe payments, stripe gateway, payment, payments, button, shortcode, digital goods, payment gateway, instant payment, commerce, digital downloads, downloads, e-commerce, e-store, ecommerce, eshop, donation
Requires at least: 4.7
Tested up to: 5.4
Requires PHP: 5.6
Stable tag: 2.0.28
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

= 2.0.28 =
- Coupon code is now saved in Stripe metadata.
- Restored `Don't Create Order` settings option (was hidden by mistake few versions back).
- Attempt to fix `mixed content` browser error on payment popup when product thumbnail is using `http` instead of `https`.
- Resolved issue with APM addon when shipping address collection is enabled.

= 2.0.27 =
- Added workaround for servers with aggressive caching when "Prefetch Payment Popup Scripts" option is enabled.
- Removed dev debug lines from the code.
- Fixed typos in text descriptions.

= 2.0.26.1 =
- Fixed a minor checkout issue with the Subscription addon when the `State` field is disabled.

= 2.0.26 =
- Added option to construct product price using variations only.
- Added support for Lebanese Pound (LBP) and Saudi Riyal (SAR) currencies.
- Added 'tax' parameter support for [asp_product] shortcode.
- Added {coupon_code} email merge tag.
- 'thankyou_page_url' parameter is no longer ignored by [asp_product] shortcode.
- State value is now properly handled.
- Shipping address is now properly saved if payment is made via payment addons.
- Made prefetch work for products attached via CSS and link URL.
- Upgraded Stripe PHP SDK to version 7.28.1.
- PHP 7.4 support.
- Minor improvements, bugfixes and optimizations.
- Added built-in integration with [WP PDF Stamper plugin](https://www.tipsandtricks-hq.com/wp-pdf-stamper-plugin-2332)

= 2.0.25 =
- Added "State" field for billing and shipping address.
- Added "Hide State Field" option to the settings menu. It's enabled by default for existing installations. This new option can be used to hide the field.
- Bundled translation files are properly loaded now.
- Minor improvements and optimizations.
- Added Italian language translation file.

= 2.0.24 =
- Empty lines in billing and shipping address are no longer displayed.
- Error is displayed when viewing subscription product with Subscriptions add-on disabled.
- Added Japanese language translation file.

= 2.0.23 =
- Added "Last Name First" option for user name prefill. Useful for languages where last name comes first (Japanese).
- Coupon discount amount was improperly displayed on checkout results page for products with variations.
- Additional functionality added for addons.
- Various minor improvements and bugfixes.
- Minimum PHP version requirement is set to PHP 5.6

= 2.0.22 =
- New Stripe API is now enabled by default on fresh new plugin installation.
- Added check if card details are properly filled in before making any backend requests (prevents excess Stripe API calls).
- Added "Force Test Mode" product option (located in Advanced Settings metabox on product edit page).
- Speed up payment popup display by removing unneeded Stripe library init.
- Added built-in integration with [Simple Membership plugin](https://wordpress.org/plugins/simple-membership/)
- WP eMember integration now works with new Stripe API and it handles customer's name and address properly.
- Some visual payment popup form tweaks.

= 2.0.21 =
- Added a new feature to insert custom CSS to payment popup window (it can be found in the advanced settings tab).
- Updated the code to remove a conflict with other plugins when "Enable Compact Product Edit Interface" option is enabled.
- Minor improvements and optimizations.

= 2.0.20 =
- Added validation for values on product edit page (tax, price, quantity etc).
- Discount coupons will now work with subscription products (requires Subscriptions addon 2.0.9+).

= 2.0.19 =
- Added 100% discount coupons support.
- Made amount rounding more consistent between frontend and backend.
- Added payment popup animation to indicate payment is accepted.
- Added quantity support for subscription products (requires Subscriptions addon 2.0.8+).
- Improved payment popup appearance on mobile devices.
- Made payment popup compatible with some older browsers (like IE11).
- Minor backend improvements and fixes.

= 2.0.18 =
- The invalid price display for subscription items on the "All products" page is fixed.
- Updated the code to remove a conflict with other plugins when "Enable Compact Product Edit Interface" option is enabled.
- Shipping value miscalculation during payment processing is fixed.
- Fixed an issue where the customer was getting redirected to login page instead of "checkout results" page on some configurations.
- Improved some text messages on the product edit interface.

= 2.0.17 =
- Improved prefetch payment scripts functionality.
- Optimization improvement to speed up the payment popup display by adding the essential CSS code directly into HTML page.
- Fixed coupon discount was improperly calculated for fixed amount coupons.
- Fixed Stripe receipt is not sent when "Send Receipt Email From Stripe" option enabled (new API only).
- On some servers, the update checker was causing an error. This has been fixed.
- Subscription product: removed the excess "Incomplete" payment entry that was being created in the Stripe Dashboard for the initial subscription charge.

= 2.0.16 =
- Replaced deprecated stripe.js functions to prevent potential issues with payments.
- Forced payment token regeneration if payment amount or currency changed.
- Made more strings available for translation.
- Fixed "Incomplete" subscription status when 3D Secure card is used for payments.
- Fixed an issue with email duplication (caused by multiple execution of some code parts).

= 2.0.15 =
- Payment popup now considers "Stripe Checkout Language" settings option.
- Made most admin interface pages responsive.
- Added MailerLite addon to addons listing menu.
- Fixed potential addon update checking issues on some servers.
- Some minor bugfixes and optimizations.
- Fixed issues with zero-cents currency amounts display and payment processing.

= 2.0.14 =
- Added "Embed Product" metabox to product edit page with available options to embed/attach payment buttons to any page or HTML element.
- Added a new feature that allows you to use a URL to make the payment button. Tutorial https://s-plugins.com/using-a-text-link-instead-of-the-buy-now-button-shortcode/
- Fixed payment popup issue when variable currency was set for subscription product.
- Addons update checker library is now bundled with core plugin.

= 2.0.13 =
- Fixed malformed download URL when [accept_stripe_payment] shortcode is used with new API.
- Added custom field validation support on payment popup.
- Added "Prefetch Payment Popup Scripts" option to speed up payment popup display when customer clicks payment button.
- Proper error message is now displayed if error occurs during frontend Stripe scripts init on payment popup.
- Removed excess output when payment button is displayed.

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

Older versions changelog available in changelog.txt file.
