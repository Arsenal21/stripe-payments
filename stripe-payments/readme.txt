=== Stripe Payments ===
Contributors: Tips and Tricks HQ, wptipsntricks, alexanderfoxc
Donate link: https://s-plugins.com
Tags: stripe, stripe payments, stripe gateway, payment, payments, button, shortcode, digital goods, payment gateway, instant payment, commerce, digital downloads, downloads, e-commerce, e-store, ecommerce, eshop, donation
Requires at least: 4.7
Tested up to: 5.5
Requires PHP: 5.6
Stable tag: 2.0.35
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

= 2.0.35 =
- Donation type products using zero-decimal currencies no longer cause payment error. 
- Products with applied coupons that reduce amount to 0 are properly handled now.
- Symbols with accents in product thumbnail URLs are no longer removed.
- Added Romanian Leu (RON) currency support.
- Fixed minor incompatibility issue with WP 5.5 on Settings page.

= 2.0.34 =
- `Currency not supported for this payment method` error message is now removed when another payment method is selected.
- Billing address postcode is no longer missing in the orders menu.
- Fixed an issue with the Order Events interface showing the "Paid" label twice.
- Payment popup blocks can now be rearranged via CSS tweaks.
- Minor payment popup appearance improvements.

= 2.0.33 =
- Fixed payment error when Legacy API option is still enabled.
- Changed the way security tokens are generated. Should help prevent `Invalid security token` error on servers with aggressive caching.
- Added `Disable Security Token Check` settings option (if the above fails).
- Fixed rare issue affecting `Authorize Only` products and payment methods that do not support it.

= 2.0.32 =
- reCAPTCHA add-on functionality is now built into the core plugin. You can enable reCAPTCHA from the settings menu of the plugin.
- Added security token check for subscriptions payments.

= 2.0.31 =
- Added `Authorize Only` option in the product configuration interface. You can use it to hold funds on a card and then capture it later from the Orders page.
- Added `Donation` product type and `Minimum Donation Amount` product option.
- Re-styled the Orders menu a little.
- Added `Order Events` meta-box to the Order menu.
- Added `Allowed Currencies` global settings option.
- Added `{payment_method}`, `{card_brand}` and `{card_last_4}` email merge tags.
- Payment popup `Close` button is now displayed instantly.
- Browser's `Back` button closes popup if it's open.
- Custom translations are properly loaded on payment popup window now.
- Currency format settings are no longer ignored on the payment popup.
- Empty value is now allowed for "Thousand Separator" option.
- Popup init errors are properly displayed now.
- Added Bulgarian Lev (BGN) currency.
- Minor improvements, bugfixes and optimizations.

= 2.0.30 =
- Added Argentine Peso (ARS), Chilean Peso (CLP), Colombian Peso (COP), Peruvian Nuevo Sol (PEN) and Uruguayan Peso (UYU) currencies.
- Fixed PHP 7.4 deprecation notices.
- Added `asp_ng_pp_countries_list` filter to programmatically modify countries list on payment popup.
- Minor fixes and improvements.

= 2.0.29 =
- Added `Do Not Use Stripe PHP SDK Library` experimental option.
- Plugin now shows warning if required PHP modules are not installed on the server.
- Minor code optimizations and improvements.
- Added subscriptions support for Additional Payment Methods addon.

= 2.0.28 =
- Coupon code is now saved in Stripe metadata.
- Restored `Don't Create Order` settings option (was hidden by mistake few versions back).
- Attempt to fix `mixed content` browser error on payment popup when product thumbnail is using `http` instead of `https`.
- Resolved issue with APM addon when shipping address collection is enabled.

= 2.0.27 =
- Added workaround for servers with aggressive caching when "Prefetch Payment Popup Scripts" option is enabled.
- Removed dev debug lines from the code.
- Fixed typos in text descriptions.

Full changelog available [ at changelog.txt](https://plugins.svn.wordpress.org/stripe-payments/trunk/changelog.txt)
