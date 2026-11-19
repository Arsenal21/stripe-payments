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

The site is set up with WP-CLI; [e2e/setup-site.php](e2e/setup-site.php) saves the settings and creates the products and the coupon.
[e2e/mu-plugin-fake-stripe-api.php](e2e/mu-plugin-fake-stripe-api.php) is a fake Stripe API (customers, PaymentIntents, charges) that answers
both the Stripe PHP SDK and the plugin's own API client, and keeps the emails instead of sending them.
There is no browser, so every PaymentIntent is paid as soon as it is created.
The tests send the same requests as a checkout in the browser: load the payment popup (page load signature and nonces),
create the PaymentIntent (`asp_pp_create_pi`), then submit the payment form (`asp_process_ipn`).

All the tests run twice: with the Stripe PHP SDK (the default) and with the plugin's own API client ("Don't use Stripe PHP SDK").

**A purchase**
- The create PaymentIntent request with the correct amount creates the PaymentIntent (amount in cents, currency, product ID) and an incomplete order.
- The payment completes the same order (paid, title with the quantity and total, the charge ID), counts it in the daily transaction counter,
  sends the buyer and seller emails and redirects to the checkout result page.
- Submitting the same payment again doesn't create another order or send the emails again.
- 3 items (quantity chosen by the buyer); stock control (more items than in stock are rejected, the stock goes down after the payment);
  a coupon (the coupon check request, the discounted total, the redemption count, the coupon code in the PaymentIntent metadata); JPY (no cents).

**Rejected before the PaymentIntent is created**
- An amount lower than the product total (and no PaymentIntent is created), a different currency, a wrong nonce, a wrong security token
  (the popup makes it from the browser's User-Agent and the product's button key; the tests send a fixed User-Agent),
  a product whose payment popup wasn't loaded (page load signature), an unknown coupon (coupon check request).
- The 21st request from the same IP address; the daily transaction limit.
- A product with no saved product type: the correct amount is accepted (the lower amount check is a known issue, see below).

**Rejected by the payment processing** (with paid PaymentIntents added to the fake Stripe API directly)
- A payment lower than the product total, a payment in another currency, a payment made for another product,
  a PaymentIntent that doesn't exist (no fatal error), a payment form with a wrong nonce. None of them creates an order or sends an email.

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
- Price variations (also a group name with `_`, and variations that make up the whole price).
- Tax by billing country, shipping by country, the surcharge, a JPY amount.

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

**IP address** (test-ip-address.php)
- The connection address (REMOTE_ADDR) is used.

---

## Known issues (tests skipped until fixed)

These tests are written but skipped, so the workflow stays green. The commit that fixes an issue turns its tests on:
remove the `markTestSkipped()` line (PHPUnit), or remove the issue from `KNOWN_ISSUES` in [e2e/run-endpoint-tests.php](e2e/run-endpoint-tests.php) (endpoint tests).
Skipped tests show as skipped in the PHPUnit output and as `SKIP:` lines in the endpoint tests output.

| Issue | Tests | Effect |
|---|---|---|
| The amount check before the PaymentIntent is created (`validate_total_amount()`) loads the coupon with `load_coupon()`, which doesn't check the coupon's allowed products or whether coupons are enabled for the product. Fix: use `check_coupon()`. | test-amount-validation.php: "a coupon only for other products is rejected", "a coupon is rejected when coupons are turned off for the product" | A tampered request gets a PaymentIntent for the discounted amount; the payment processing then rejects the payment, but only after the card is charged (no order). |
| `handle_create_pi()` only runs the amount check when the product type is `one_time`. Products with no saved type (saved before the product type option, and the products of `[accept_stripe_payment]`) skip it. | Endpoint tests: `amount-check-skipped-without-product-type` | Same as above: a PaymentIntent for any amount; the payment processing rejects a lower payment after the card is charged. |
| `ASP_Utils::get_user_ip_address()` uses the X-Forwarded-For header when there is one. | test-ip-address.php: "an X-Forwarded-For header sent by the visitor is not trusted"; endpoint tests: `ip-limit-trusts-x-forwarded-for` | A different header value on each request gets around the limit of 20 create PaymentIntent requests per IP address (the card testing protection). Sites behind a proxy may need the forwarded address (an option or a filter). |

---

## Not covered yet (ideas for new tests)

Checkout
- Donation products (the minimum amount), price variations and tax/shipping by region over the endpoints, the surcharge.
- 100% discount coupons (the zero-value "free_" transaction and its signature check).
- 3D Secure (`asp_next_action_results`) and authorize-only payments (capture and cancel from the order page).
- Captcha (reCAPTCHA, hCaptcha) and the daily limit with captcha.

Later
- The add-ons (for example the Subscriptions add-on webhooks), tested with the core plugin installed.
