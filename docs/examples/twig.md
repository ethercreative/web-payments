---
title: Twig Examples
---

# Twig Examples
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
	js: 'window.pay_item_' ~ product.id,
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