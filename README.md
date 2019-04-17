# Web Payments for Craft Commerce

Use the Web Payments API and Apply Pay to vastly improve your checkout process!

## Usage
### `{{ craft.webPayments.button(options) }}`
Place this twig where you want the payments button to appear.

#### Options
Pass a twig object as the only parameter to configure the button.

##### `country`
ISO 2 country code (Stripe needs this for whatever reason).

##### `items`
An array of items to buy.

##### `items[x].id`
The ID of the purchasable.

##### `items[x].qty`
The Qty being purchased.

##### `cart`
A Craft Commerce Order (i.e. the current cart).

##### `requestShipping`
A boolean or string. If true a shipping address will be required. Can also be 
set to one of: `shipping` (default), `delivery`, `pickup`. This will change how 
the UI refers to the shipping of the products. For example, in English speaking 
countries you would say "pizza delivery" not "pizza shipping". You must have 
`shippingMethods` available for this to have any effect.

##### `requestDetails`
An array of additional details to request. Any of: `name`, `email`, `phone`.

## Example
```twig
{{ craft.webPayments.button({
    country: 'GB',
    items: [
        { id: product.defaultVariant.id, qty: 1 },
    ],
    requestShipping: 'delivery',
    requestDetails: ['name', 'email'],
}) }}
```

```twig
{{ craft.webPayments.button({
    country: 'GB',
    cart: craft.commerce.carts.cart,
    requestShipping: true,
    requestDetails: ['name', 'email'],
}) }}
```