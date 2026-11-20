# Plugin Tests: what is covered

This file lists what the **Plugin Tests** GitHub Actions workflow checks, and what isn't covered yet.
Update it in the same commit when you add or change a test.

## How to run

GitHub > Actions > **Plugin Tests (ASP Main)** > Run workflow. Choose the branch and tick the test groups to run (all are ticked by default).
The workflow file is [.github/workflows/plugin-tests-main.yml](../.github/workflows/plugin-tests-main.yml).
Run it before a release and after bigger changes.

A separate, light workflow, [.github/workflows/php-compatibility-test.yml](../.github/workflows/php-compatibility-test.yml), runs on every push and pull request to master
(it activates the plugin and runs two shortcodes). A pull request can't be merged until it passes, so keep it fast; full tests go in the Plugin Tests workflow.

| Group | What it is | PHP versions | Files |
|---|---|---|---|
| Lint | PHP syntax check of the plugin and the test files (the Stripe PHP library in `includes/stripe` is skipped) | 7.4, 8.5 | (in the workflow) |
| PHPUnit | Tests with the WordPress test library and a real database | 7.4, 8.4 | [phpunit/](phpunit/) |
| Endpoint tests | A real WordPress site with the plugin and a fake Stripe API: sends the checkout requests over HTTP and checks the database | 7.4, 8.4 | [e2e/](e2e/) |

Which group a new test belongs in:
- Plugin functions and classes that need WordPress and the database: **PHPUnit**.
- The code runs from an incoming request (the payment AJAX calls, the payment processing after checkout) and ends with `exit` or `wp_die()`: **Endpoint tests**.

PHPUnit converts PHP notices and warnings into test failures, so a notice in the plugin code fails the test that runs it.
The test files must stay PHP 7.4-compatible (the oldest PHP version in the workflow).

---

## Endpoint tests ([e2e/run-endpoint-tests.php](e2e/run-endpoint-tests.php))

The site is set up with WP-CLI; [e2e/setup-site.php](e2e/setup-site.php) saves the settings and creates the products, the coupons
and two pages with the `[accept_stripe_payment]` shortcode.
[e2e/mu-plugin-fake-stripe-api.php](e2e/mu-plugin-fake-stripe-api.php) is a fake Stripe API (customers, PaymentIntents with confirm/capture/cancel, charges)
that answers both the Stripe PHP SDK and the plugin's own API client. It also answers the reCAPTCHA and hCaptcha verification
(the token `e2e-captcha-pass` is solved) and keeps the emails instead of sending them.
There is no browser, so every PaymentIntent is paid as soon as it is created (or authorized, for authorize-only products);
the runner adds PaymentIntents in other states directly (needs 3D Secure, not paid, declined card).
The tests send the same requests as a checkout in the browser: load the payment popup (page load signature and nonces),
create the PaymentIntent (`asp_pp_create_pi`), then submit the payment form (`asp_process_ipn`).
Some tests use a browser session (cookies): the checkout result page, the captcha, the 3D Secure return and the admin's orders page.

The main tests run twice: with the Stripe PHP SDK (the default) and with the plugin's own API client ("Don't use Stripe PHP SDK").

**A purchase**
- The create PaymentIntent request with the correct amount creates the PaymentIntent (amount in cents, currency, product ID) and an incomplete order.
- The payment completes the same order (paid, title with the quantity and total, the charge ID), counts it in the daily transaction counter,
  sends the buyer and seller emails and redirects to the checkout result page.
- Submitting the same payment again doesn't create another order or send the emails again.
- The checkout result page shows the order (thank you, product, transaction ID); after a rejected payment it shows the error.
- 3 items (quantity chosen by the buyer); stock control (more items than in stock are rejected, the stock goes down after the payment);
  a coupon (the coupon check request, the discounted total, the redemption count, the coupon code in the PaymentIntent metadata); JPY (no cents).
- `[accept_stripe_payment price="10"]` (the amount is checked, the order is paid) and `[accept_stripe_payment price="0"]` (the buyer's amount).
- A donation with a minimum amount (below it is rejected; the order has the buyer's amount).
- Tax by billing country (a US buyer pays the tax, a German buyer doesn't) and shipping by shipping country, through to the paid order.
- Authorize only: the order is authorized, then the admin captures (paid) or cancels (canceled) it from the orders page, and the order keeps its charge data.
- 3D Secure: the return request (`asp_next_action_results`, after the form is saved with `asp_pp_save_form_data`) confirms the PaymentIntent and completes the order.
- A 100% coupon: the coupon check gives a "free_" transaction ID and the order is paid with $0.00.

**Rejected before the PaymentIntent is created**
- An amount lower than the product total (and no PaymentIntent is created), a different currency, a wrong nonce, a wrong security token
  (the popup makes it from the browser's User-Agent and the product's button key; the tests send a fixed User-Agent),
  a product whose payment popup wasn't loaded (page load signature), an unknown coupon (coupon check request).
- A coupon only for another product, a coupon for a product with coupons turned off (a coupon only for this product is accepted).
- The 21st request from the same IP address; the daily transaction limit.
- A product with no saved product type: the correct amount is accepted, a lower amount is rejected.

**Rejected by the payment processing** (with paid PaymentIntents added to the fake Stripe API directly)
- A payment lower than the product total, a payment in another currency, a payment made for another product,
  a PaymentIntent that doesn't exist (no fatal error), a payment form with a wrong nonce, a PaymentIntent without a payment,
  a made-up "free_" transaction ID, the "free_" transaction ID of a coupon that isn't 100%. None of them creates an order or sends an email.

**Captcha** (once, after the two rounds; reCAPTCHA, then hCaptcha)
- The create PaymentIntent request is rejected without the captcha and after a wrong captcha token; it succeeds after the solved captcha.

**PHP errors**: a PHP fatal error on the test site fails the tests; the plugin's PHP warnings and notices are listed in the output.

## PHPUnit ([phpunit/tests/](phpunit/tests/))

**Plugin smoke test** (test-plugin-smoke.php)
- The plugin loads.
- Activation saves the default settings (test mode, USD) and the hashing key, and creates the checkout result and products pages.
- Activating again (as a plugin update does) doesn't create the pages twice, keeps the saved settings and adds missing settings with their defaults.
- The products and orders post types and the main shortcodes are registered.
- A product shows the payment form, the buy button, the price, the tax amount and the total with tax.
- A wrong product ID (not a number, or a post that isn't a product) shows an error and no button.
- `[accept_stripe_payment]` needs the `name` parameter, and shows a buy button when it has one.
- `[asp_show_all_products]` lists a product.

**Price calculation** (test-price-calculation.php). The expected amounts are exact, because the plugin compares amounts in cents with `!=`.
- Price, quantity (the product quantity, or the buyer's quantity), tax (rounded to the cent), shipping (once per order, not per item).
- Prices that aren't exact in floating point (0.29, 1.15) become whole cents.
- Zero-decimal currencies (JPY, KRW, VND, MGA) aren't multiplied by 100; the default currency is used when the product has none.
- Flat and percent surcharge (percent of the total with tax).
- Shipping by region (country, state, city, without case) and tax by region.
- Coupons: percent (tax on the discounted amount), fixed per item, fixed per order, a discount larger than the price (total 0, never negative), JPY coupons.
- Price formatting: symbol position, separators, prices in cents.

**Amount check before the PaymentIntent is created** (test-amount-validation.php, `validate_total_amount()`)
- The correct amount is accepted; a lower or higher amount is rejected with the expected and submitted amounts.
- The product quantity is used when the buyer sends none; the amount must match the buyer's quantity.
- A valid coupon lowers the expected amount; the discounted amount without the coupon, an unknown coupon and an expired coupon are rejected.
- The coupon's product rules are checked too (fixed after 2.1.5): a coupon only for other products, and coupons turned off for the product, are rejected.
- Price variations (also a group name with `_`, and variations that make up the whole price).
- Tax by billing country, shipping by country, the surcharge, a JPY amount.
- The type used for the amount check (fixed after 2.1.5): a product with no saved type is checked as `one_time` when it has a price
  (or the variations make up the price) and as `donation` when the buyer enters the amount; a saved type and subscription products are kept.

**Coupons** (test-coupons.php, `check_coupon()`)
- A valid coupon is accepted; spaces around the code are ignored.
- Rejected with the right message: unknown code, empty code, inactive, before the start date, expired, redemption limit reached,
  coupons turned off (in the settings or for the product), a coupon only for other products. A rejected coupon doesn't stay applied.
- The product's coupon setting overrides the general setting.

**Orders** (test-orders.php, `ASP_Order_Item`)
- A new order is a pending "[Test Mode] [Incomplete]" order with the PaymentIntent ID and the product ID; a logged-in buyer's order is linked to the user.
- An order is found by its PaymentIntent ID.
- Status changes are saved as order events, without HTML.
- A paid order gets the title and the order details (transaction, product, quantity, price, tax, total, buyer); a failed payment stays pending with the failure details.
- Orders can be turned off in the settings.

**Emails** (test-emails.php)
- No emails when they are turned off.
- The buyer email goes to the buyer with the product details (and the download link), and without the admin order link.
- The merge tags are replaced (names, email, transaction, item, price, tax, total, card, coupon, download link).
- The HTML email type.
- The seller email goes to the seller, with Reply-To set to the buyer and a link to the order.

**`[accept_stripe_payment]` products** (test-shortcode-products.php)
- The shortcode saves its product (name, price, tax, total) and it gets the amount check; without a price it's checked as a donation.
- The shipping is saved as entered; a product saved by an older version (shipping in cents) is corrected.

**IP address** (test-ip-address.php)
- The connection address (REMOTE_ADDR) is used when there is no X-Forwarded-For header; the first X-Forwarded-For address is used when there is one (see Decisions).
- The `asp_get_user_ip_address` filter can change the address.

---

## Known issues (tests that report without failing)

A test for a known or suspected bug runs, but doesn't fail the workflow, so the output shows on every run whether the problem still happens:
- Endpoint tests: the issue is in `KNOWN_ISSUES` in [e2e/run-endpoint-tests.php](e2e/run-endpoint-tests.php); its checks print
  `KNOWN ISSUE (still happens)` or `KNOWN ISSUE NOW PASSES`.
- PHPUnit: the test calls `markTestIncomplete()` when the bug happens (shown as incomplete, with the reason); any other wrong result fails.

The commit that fixes an issue removes it from `KNOWN_ISSUES` (or the `markTestIncomplete()` part), so its test becomes a normal test.
"Unconfirmed" means the tests show what the code does with the fake Stripe API, but it isn't reproduced with real Stripe in a browser yet.

| Issue | Status | Tests |
|---|---|---|
| The payment processing (`process_ipn()`) checks the amount, currency and product of the latest charge, but not that the payment succeeded, so a PaymentIntent with a declined (failed) charge may complete a paid order, send the emails and run `asp_stripe_payment_completed`. | Unconfirmed: to reproduce with the decline card 4000 0000 0000 0002 in test mode | Endpoint: `declined-charge-completes-order` |

Fixed after 2.1.5 (their tests are normal tests now):
- The coupon's product rules in the amount check, and the amount check for products with no saved type.
- Capture and Cancel on the orders page saved `$intent->charges->data[0]` as the order's charge, which is empty with the Stripe API version the plugin uses;
  they now save the PaymentIntent's latest charge (`latest_charge`).
- With "Don't use Stripe PHP SDK", Capture and Cancel failed ("Funds already captured or refunded"), because the saved charge (a Stripe SDK object)
  was read before the SDK classes were loaded.
- `[accept_stripe_payment shipping="5"]` saved the shipping in cents ($500.00); it is saved as entered now, and a product saved by an older version
  is corrected the next time the shortcode is shown.

## Decisions (behavior that is kept on purpose)

- **The visitor's IP address comes from the X-Forwarded-For header when there is one** (`ASP_Utils::get_user_ip_address()`).
  A visitor can send any value in that header, so the limit of 20 create PaymentIntent requests per IP address (and the page load signature, which is per IP address)
  can be bypassed by changing it on each request. It is kept because sites behind a proxy (a load balancer, Varnish, Cloudflare without the real IP restored)
  only see the proxy's address otherwise, and all their buyers would share one limit. The daily transaction limit and the captcha options still apply.
  The `asp_get_user_ip_address` filter lets a site use another address. Decided in November 2026.

---

## Not covered yet (ideas for new tests)

Checkout
- Price variations and the surcharge over the endpoints (the calculation is covered by PHPUnit).
- Enterprise reCAPTCHA; the daily limit with captcha.
- Saving the settings page (the sanitize callback needs the whole settings form).

Needs a real browser (a possible later phase with Playwright and Stripe test mode)
- The payment popup's JavaScript: the Stripe card field, the 3D Secure popup, error messages, the total updating with variations and the address.

Manual
- Apple Pay / Google Pay; real email delivery; how the admin pages look.

Later
- The add-ons (for example the Subscriptions add-on and its webhooks), tested with the core plugin installed.
