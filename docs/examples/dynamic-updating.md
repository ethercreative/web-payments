---
title: Dynamic Updating
---

# Dynamic Updating
You can dynamically update the button via JS. 

Use the `js` option to define the variable you want the button to be bound to:

```twig
{{ craft.webPayments.button({
	items: [{ id: 1, qty: 1 }],
	js: 'window.payButton',
}) }}
```

You can then get the current items and update them:

```js
const items = [ ...window.payButton.items ];
items[0].options = { giftWrapped: 'yes' };
window.payButton.items = items;
```

You **can't** update the items directly (i.e. `window.payButton.items[0].id = 2`). 
The `items` are immutable, and therefore must be set to a new array if you want 
to update them. Above we are cloning the existing items into a new array before 
modifying them.

If you passed in a cart you can simply call `refresh()` to update the button 
with the latest contents of the cart.

```js
window.payButton.refresh();
```

Note that you can't update the button while the payment dialog is active.

If you need to reload the button (i.e. if the DOM has changed) you can use the 
`reload()` function.

```js
window.payButton = window.payButton.reload();
```

`reload()` will return a new instance of the button, so you'll want to replace 
your existing variable with that new instance.
