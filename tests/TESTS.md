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

---

## Not covered yet (ideas for new tests)

Phase B: the price calculation (PHPUnit, no network)
- `ASP_Product_Item` totals: price, quantity, variations, tax (and tax by region), shipping (and shipping by region), surcharge.
- Coupons: percent and fixed, expiry, start date, redemption limit, allowed products.
- Zero-decimal currencies (JPY and others).
- Creating the order record; the buyer and seller email merge tags.

Phase C: endpoint tests (a real test site with a faked Stripe API)
- Creating the PaymentIntent (`asp_pp_create_pi`): the amount is calculated on the server; an amount sent by the browser is ignored.
- Processing the payment after checkout: the order is created and the emails are sent.
- Rejected: a paid amount lower than the price, a different currency.
- The same PaymentIntent doesn't create a second order.
- The coupon check AJAX request.
- The daily transaction limit and the bot mitigation checks.

Later
- The add-ons (for example the Subscriptions add-on webhooks), tested with the core plugin installed.
