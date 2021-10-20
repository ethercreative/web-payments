---
title: Config
---

# Config
After installing the plugin:

1. Create a [Stripe payment gateway](https://plugins.craftcms.com/commerce-stripe).
2. Select the gateway in the plugin options.
3. Register your domain with Stripe and Apple Pay (Simply add your domain [here](https://dashboard.stripe.com/account/apple_pay)).
4. Output the button where ever you want using the template tag below.
5. ???
6. Profit.

### `{{ craft.webPayments.button(options) }}`
Place this twig where you want the payments button to appear.