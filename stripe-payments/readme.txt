=== Accept Stripe Payments ===
Contributors: Tips and Tricks HQ, wptipsntricks, alexanderfoxc
Donate link: https://s-plugins.com
Tags: stripe, stripe payments, stripe gateway, payment, payments, button, shortcode, digital goods, payment gateway, instant payment, commerce, digital downloads, downloads, e-commerce, e-store, ecommerce, eshop, donation
Requires at least: 5.0
Tested up to: 6.1
Requires PHP: 5.6
Stable tag: 2.0.75
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

= 2.0.75 =
- Sorting option added in the shop/products page via shortcode parameter.
- Visitors can also sort the products in the shop page by latest, sort by title, sort by price.
- Updated the integration with Simple Membership plugin.

= 2.0.74 =
- iDEAL addon fix (this payment method option was getting selected by default when enabled).
- Better thumbnail positioning within the product template display.
- The shop page is wrapped using a div element for better display in block themes.

= 2.0.73 =
- Added filter hooks to allow customization of the admin menu item capability.
- Added a new email merge tag {product_variations} that will include the selected variations of the order.
- Fixed an issue with the "authorize" and "capture" later feature (when the Stripe PHP SDK option is used).

= 2.0.72 =
- Removed the Legacy/Old API checkbox option from the settings. It will now always fallback to use the new API. 

= 2.0.71 =
- Added a transaction request limit check on a per IP address basis. This will provide additional protection against card testing bot.

= 2.0.70 =
- Removed the "Invisible captcha" option as it doesn't provide the maximum protection against card testing bot.
- If you were using the Invisible captcha option, it will fallback to the standard reCAPTCHA option.

= 2.0.69 =
- Added a page load signature check on payment form submission.

= 2.0.68 =
- Japanese language translation file updated.
- Added Hungarian language translation file.
- Fixed - Special characters in the Customer Name field causing a Stripe API error.
- Added an additional captcha response check against saved data (when captcha is enabled) for bot mitigation.

= 2.0.67 =
- Added a settings option to specify a limit with captcha. You can disable it by entering a value of -1 in the settings field.

= 2.0.66 =
- Custom Fields - fixed an issue with the "required" custom field configuration.
- Added a new feature to apply a daily transaction limit to provide protection against card testing attack. This is applied when the captcha option is disabled on a site.
- Explanation of the new feature is available in [this documentation page](https://s-plugins.com/protect-yourself-from-card-testing/).

= 2.0.65 =
- Added CSS classes for "Item Price" and "Total Amount" on the thank you page. It can be used to target those items for CSS customization.
- Added additional currency position display options in the settings (left with space, right with space).
- Fixed a formatting issue with the shipping and tax string on the individual product post output.
- Added a new option in the settings for Enterprise reCAPTCHA of Google.

= 2.0.64 =
- Added escaping to the currency symbol output.

= 2.0.63 =
- Added output escaping in the settings interface.

= 2.0.62 =
- Added more filters so the "state" and "postcode" strings can be customized via the custom messages addon.
- Fixed an issue with the quick edit option causing the product configuration to be lost.
- Added a notice to enable captcha feature (if it is not already enabled).

= 2.0.61 =
- Tested on WP 6.0.
- Added a new utility function for escaping SVG output.
- Added escaping to the admin notice message.
- Removed the phpcs related comments from the code.

= 2.0.60 =
- PHP Debug warning fix for view debug request parameter.

= 2.0.59 =
- Added escaping to the extension/addon settings output.

= 2.0.58 =
- Fixed a typo in the "Experimental Settings" heading in the settings menu.
- Added escaping to "Additional CSS" description field in the settings menu.
- Added a utility function to help with output escaping.
- Removed two unused PHP files.

= 2.0.57 =
- Updated the captcha settings menu interface to mention that the "I am not a robot" checkbox captcha option should be used for better protection against bot. 
- Added escaping to $data variable in the [Accept Stripe Payments] shortcode.

= 2.0.56 =
- Fixed the additional payments settings menu (it correctly renders the description field)
- Changed parse_str() call to using wp_parse_str()
- Removed the Advanced Custom Field addon's input processing code from the core plugin. This task is now done inside the ACF addon
- Minor code improvements (replaced the PHP buffer output with a direct require_once call)

= 2.0.55 =
- Added sanitization to inputs that were missing sanitization.
- Commented out the post data writing to the log file.
- Added escaping to description output of settings fields.
- Added nonce verification check to the payment intent handling code.
- Added nonce verification check to the 3D secure handling code.

= 2.0.54 = 
- Added a capabilities check and a nonce check before the log file is cleared.
- Regenerated the translation POT file.
- Spanish Language translation file updated.

= 2.0.53 =
- Updated Stripe PHP SDK library to 7.110.0. This should resolve most issues related to PHP 8.0.X.
- Added hCaptcha support.
- Addressed improper validation of `Specific Products Only` option on coupon edit page.
- Better error handling for internal Stripe API.

= 2.0.52 =
- Added a checkbox to enable or disable the `Debug Log Shareable Link` option (disabled by default).
- The word `Variable` is displayed (in the store page) instead of zero as price for products that are constructed via variations.
- Fixed PHP warning on Widgets admin page.

= 2.0.51 =
- Improved the `Use variations only to construct final product price` option's reaction to product price change.
- Improvements to coupons edit page.
- Internal API improvements.

= 2.0.50 =
- Invisible reCaptcha option no longer causes an issue on some Apple devices.
- Minor improvements and optimizations to the debug logging feature.

= 2.0.49 =
- Addressed the `Class not found` error related issue on some configurations.
- Minor CSS update to improve the admin-side layout.

= 2.0.48 =
- Added a new feature to configure per-region tax variations option.
- Added checkbox variation type in the product variations feature.
- Only users with admin capabilities (administrator role) can add/edit products now.
- Improved layout and functionality of some admin-side pages.
- Added United Arab Emirates Dirham (AED) currency support.
- Minor improvements and bugfixes.
- Updated the Italian language translation file.

= 2.0.47 =
- Fixed all-off coupons issue in Firefox when payment popup is integrated via link URL.
- Added `{first_name}` and `{last_name}` email merge tags.
- Addressed rare PHP warnings issue when debug mode is enabled.
- More text messages can now be customized via Custom Messages add-on.

Full changelog available [at changelog.txt](https://plugins.svn.wordpress.org/stripe-payments/trunk/changelog.txt)
