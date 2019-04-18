# Web Payments for Craft Commerce

Use the Web Payments API and Apply Pay to vastly improve your checkout process!

## Usage
### `{{ craft.webPayments.button(options) }}`
Place this twig where you want the payments button to appear.

#### Options
Pass a twig object as the only parameter to configure the button.

##### `items`
An array of items to buy. Use this **OR** `cart`.

##### `items[x].id`
The ID of the purchasable.

##### `items[x].qty`
The Qty being purchased.

##### `cart`
A Craft Commerce Order (i.e. the current cart). Use this **OR** `items`.

##### `requestShipping`
A boolean or string. If true a shipping address will be required. Can also be 
set to one of: `'shipping'`, `'delivery'`, `'pickup'`, `true`, `false` (default).
This will also change how the UI refers to the shipping of the products. For 
example, in English speaking countries you would say "pizza delivery" not 
"pizza shipping". Setting this to true will default the working to "shipping".

##### `requestDetails`
An array of additional details to request. Any of: `name`, `email` (email is 
always collected, so you don't need to add it), `phone`.

##### `onComplete`
An object of events to trigger once the payment is complete.

##### `onComplete.redirect`
A URL to redirect to once the payment is completed. Can include `{number}`, 
which will output the order number.

##### `onComplete.js`
JavaScript that will be executed once the payment is complete. Has access to the 
`cwp` object. Currently this only has `cwp.number` (the order number).

## Example
```twig
{{ craft.webPayments.button({
    items: [
        { 
            id: product.defaultVariant.id, 
            qty: 1,
            options: {
                giftWrapped: 'yes',
            },
         },
    ],
    requestShipping: 'delivery',
}) }}
```

```twig
{{ craft.webPayments.button({
    cart: craft.commerce.carts.cart,
    requestDetails: ['name', 'phone'],
    onComplete: {
        redirect: '/thanks?number={number}',
        js: 'window.paymentCompleted(cwp.number);',
    },
}) }}
```

## TODO
- [x] When using a cart, actually use the cart to keep fields / options persistent
  - [x] Remove cart option (if items isn't set, use active cart)
  - [x] Remove clear cart option
- [x] Support line item options when using items
- [x] On payment complete event (i.e. clear active cart, redirect to thanks)
- [ ] JS hooks to update items (if not using commerce cart)
- [ ] JS hook to refresh cart data (if using commerce cart)
- [ ] Option to use default Apple / Google Pay buttons (rather that Stripe's button)
- [x] Use billing address from Stripe (don't set billing address to shipping)
- [x] Make shipping address optional
- [x] Settings:
  - [x] Select Stripe gateway
  - [x] Button config defaults
  - [x] Map request details (name, phone) to order fields
- [ ] Write setup instructions
- [ ] Browser test JS
- [ ] Test with Commerce Lite