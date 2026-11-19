# Plugin Tests: what is covered

This file lists what the **Plugin Tests** GitHub Actions workflow checks, and what isn't covered yet.
Update it in the same commit when you add or change a test.

## How to run

GitHub > Actions > **Plugin Tests** > Run workflow. Choose the branch and tick the test groups to run (all are ticked by default).
The workflow file is [.github/workflows/tests.yml](../.github/workflows/tests.yml).
Run it before a release and after bigger changes.

A separate, light workflow, [.github/workflows/php-compatibility-test.yml](../.github/workflows/php-compatibility-test.yml), runs on every push and pull request to master
(it activates the plugin and runs two shortcodes). A pull request can't be merged until it passes, so keep it fast; full tests go in the Plugin Tests workflow.

| Group | What it is | PHP versions | Files |
|---|---|---|---|
| Lint | PHP syntax check of the plugin and the test files (the Stripe PHP library in `includes/stripe` is skipped) | 7.4, 8.5 | (in the workflow) |
| PHPUnit | Tests with the WordPress test library and a real database | 7.4, 8.4 | [phpunit/](phpunit/) |

Which group a new test belongs in:
- Plugin functions and classes that need WordPress and the database: **PHPUnit**.
- The code runs from an incoming request (the payment AJAX calls, the payment processing after checkout) and ends with `exit` or `wp_die()`: **Endpoint tests** (planned, see below).

PHPUnit converts PHP notices and warnings into test failures, so a notice in the plugin code fails the test that runs it.
The test files must stay PHP 7.4-compatible (the oldest PHP version in the workflow).

---

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

---

## Not covered yet (ideas for new tests)

Phase C: endpoint tests (a real test site with a faked Stripe API)
- Creating the PaymentIntent (`asp_pp_create_pi`): the amount is calculated on the server; an amount sent by the browser is ignored.
- Processing the payment after checkout: the order is created and the emails are sent.
- Rejected: a paid amount lower than the price, a different currency.
- The same PaymentIntent doesn't create a second order.
- The coupon check AJAX request.
- The daily transaction limit and the bot mitigation checks.

Later
- The add-ons (for example the Subscriptions add-on webhooks), tested with the core plugin installed.
