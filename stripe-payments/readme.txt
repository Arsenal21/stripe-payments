=== Stripe Payments ===
Contributors: Tips and Tricks HQ, wptipsntricks
Donate link: http://www.tipsandtricks-hq.com/
Tags: stripe, stripe payments, stripe gateway, payment, payments, button, shortcode, digital goods, payment gateway, instant payment, commerce, digital downloads, downloads, e-commerce, e-store, ecommerce, eshop, donation
Requires at least: 4.7
Tested up to: 4.8
Stable tag: 1.6.0
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

https://www.youtube.com/watch?v=HYarbgMywNM

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
* Ability to specify a custom description for the item/product (this info is captured with the order).
* Option to configure a notification email to be sent to the buyer and seller after the purchase.
* There is an option to allow the customer to enter a custom price amount for your product or service (customer pays what they want).
* Option to accept custom donation amount via Stripe payment gateway.
* Option to save the card data on Stripe.
* Ability to have custom thank you page on a per product basis.
* Ability to customize the message on the thank you page using tags.

The setup is very easy. Once you have installed the plugin, all you need to do is enter your Stripe API credentials in the plugin settings (Settings -> Accept Stripe Payments) and your website will be ready to accept credit card payments.

You can run it in test mode by specifying test API keys in the plugin settings.

= Shortcode Parameters/Attributes =

In order to create a "Buy Now" or "Pay" button, insert the following shortcode into a post/page.

`[accept_stripe_payment]`

It supports the following parameters in the shortcode -

    name:
    (string) (required) Name of the product
    Possible Values: 'Awesome Script', 'My Ebook', 'Wooden Table' etc.

    price:
    (number) (required) Price of the product or item
    Possible Values: '9.90', '29.95', '50' etc.

    quantity:
    (number) (optional) Number of products to be charged.
    Possible Values: '1', '5' etc.
    Default: 1

    currency:
    (string) (optional) Currency of the price specified.
    Possible Values: 'USD', 'GBP', 'CAD' etc.
    Default: The one set up in Settings area.
    
    url:
    (URL) (optional) URL of the downloadable file.
    Possible Values: http://example.com/my-downloads/product.zip

    button_text:
    (string) (optional) Label of the payment button
    Possible Values: 'Buy Now', 'Pay Now' etc

    billing_address:
    (string) (optional) Use it to collect billing address for the transaction
    Possible Value: '1'

    shipping_address:
    (string) (optional) Use it to collect shipping address for the transaction
    Possible Value: '1'

= Shortcode Usage Example =

`[accept_stripe_payment name="Cool Script" price="49.90" url="http://example.com/downloads/my-script.zip" button_text="Buy Now"]`

= Specifying a Logo or Thumbnail for the Item Checkout =

You can specify a logo or thumbnail image URL in the shortcode for the item. This image will be shown in the stripe checkout window. 

Use the "item_logo" parameter in the shortcode and enter the image URL to use this feature. See example below:

`[accept_stripe_payment name="Test Product" price="39.00" button_text="Buy Now" item_logo="http://example.com/my-item-logo.png"]`

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

Yes, please use "quantity" attribute.

= Can I change the button label? =

Yes, please use "button_text" attribute

= Can It be tested before going live? =

Yes, please visit Settings > Accept Stripe Payments screen for options.


== Screenshots ==

1. Stripe Plugin Settings
2. Stripe Plugin Payment Page
3. Stripe Plugin Orders Menu

== Upgrade Notice ==
None

== Changelog ==

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
