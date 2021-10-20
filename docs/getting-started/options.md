---
title: Options
---

# Options
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

##### `js`
The variable the button will be set to in JS. Useful if you want to dynamically
update the items in the virtual cart.

##### `style`
Customize the appearance of the button:

```js
{
	type: 'default' | 'donate' | 'buy', // default: 'default'
	theme: 'dark' | 'light' | 'light-outline', // default: 'dark'
	height: '64px', // default: '40px', the width is always '100%'
}
```