// Helpers
// =============================================================================

/**
 * Posts to Craft
 *
 * @param {string} actionTrigger
 * @param {array} csrf
 * @param {string} action
 * @param {Object} body
 * @return {Promise}
 */
async function post ({ actionTrigger, csrf }, action, body) {
	const fd = new FormData();
	fd.append(csrf[0], csrf[1]);

	Object.keys(body).forEach(function (key) {
		const value = body[key];

		fd.append(
			key,
			typeof value === 'object' ? JSON.stringify(value) : value
		);
	});

	return fetch(`/${actionTrigger}/web-payments/stripe/${action}`, {
		method: 'POST',
		body: fd,
		headers: {
			'Accept': 'application/json',
			'X-Requested-With': 'XMLHttpRequest',
		},
	}).then(function (res) {
		return res.json();
	});
}

// Actions
// =============================================================================

/**
 * Update the items in the virtual cart
 *
 * @param items
 * @param state
 * @param paymentRequest
 * @param post
 * @return {Promise<void>}
 */
async function updateItems (items, state, paymentRequest, post) {
	try {
		const {
			total, displayItems, shippingOptions
		} = await post('update-display-items', {
			cartId: state.cartId,
			items,
		});

		state.update({ items });

		paymentRequest.update({ total, displayItems, shippingOptions });
	} catch (e) {
		throw e;
	}
}

// Events
// =============================================================================

/**
 * Handle the changing shipping address
 *
 * @param {Object} e
 * @param {Object} state
 * @param {Function} post
 * @return {Promise<void>}
 */
async function onShippingAddressChange (e, state, post) {
	try {
		const data = await post('update-address', {
			cartId: state.cartId,
			items: state.items,
			address: e.shippingAddress,
		});

		state.update({ shippingAddress: e.shippingAddress });
		e.updateWith(data);
	} catch (_) {
		e.updateWith({ status: 'fail' });
	}
}

/**
 * Handle the changing shipping option
 *
 * @param {Object} e
 * @param {array} state
 * @param {Function} post
 * @return {Promise<void>}
 */
async function onShippingOptionChange (e, state, post) {
	try {
		const data = await post('update-shipping', {
			cartId: state.cartId,
			items: state.items,
			address: state.shippingAddress,
			method: e.shippingOption,
		});

		state.update({ shippingOption: e.shippingOption });
		e.updateWith(data);
	} catch (_) {
		e.updateWith({ status: 'fail' });
	}
}

/**
 * Handle the Stripe token
 *
 * @param {Object} e
 * @param {Object} state
 * @param {Function} post
 * @return {Promise<void>}
 */
async function onToken (e, state, post) {
	try {
		const data = await post('pay', {
			cartId: state.cartId,
			items: state.items,
			token: e.token,
			payerName: e.payerName,
			payerEmail: e.payerEmail,
			payerPhone: e.payerPhone,
			shippingAddress: e.shippingAddress,
			shippingMethod: e.shippingOption,
		});

		e.complete(data.status);

		if (state.onComplete.js) {
			eval(
				'const cwp = { number: \'' + data.number + '\' };' +
				state.onComplete.js
			);
		}

		if (state.onComplete.redirect)
			window.location = state.onComplete.redirect.replace('{number}', data.number);
	} catch (_) {
		e.complete('fail');
	}
}

// Craft Web Payments
// =============================================================================

/**
 * @return {null|Object}
 */
window.CraftWebPayments = function (opts) {

	opts = Object.freeze(opts);

	// Constants
	// -------------------------------------------------------------------------

	const el          = document.getElementById(opts.id)
		, stripe      = window.Stripe(opts.stripeApiKey)
		, postOptions = { actionTrigger: opts.actionTrigger, csrf: opts.csrf };

	const state = {
		cartId: opts.cart.id,
		items: opts.cart.items,
		shippingAddress: null,
		shippingOption: null,
		onComplete: opts.onComplete,

		update: function (nextState) {
			Object.keys(nextState).forEach(key => {
				this[key] = nextState[key];
			});
		},
	};

	// Payment Request
	// -------------------------------------------------------------------------

	// Build the payment request object
	const paymentRequest = stripe.paymentRequest({
		country: opts.country.toUpperCase(),
		currency: opts.currency.toLowerCase(),
		displayItems: opts.cart.displayItems,
		total: opts.cart.total,
		requestShipping: !!opts.requestShipping,
		shippingType: typeof opts.requestShipping === 'boolean' ? 'shipping' : opts.requestShipping,
		shippingOptions: opts.shippingMethods,
		requestPayerName: opts.requestDetails.indexOf('name') > -1,
		requestPayerEmail: opts.requestDetails.indexOf('email') > -1,
		requestPayerPhone: opts.requestDetails.indexOf('phone') > -1,
	});

	// Bind the postOptions to the post function
	const postInternal = async function (action, body) {
		return post(postOptions, action, body);
	};

	// Ensure we can actually make payment
	paymentRequest.canMakePayment().then(function () {

		// Create the Stripe payment request button & mount it in place
		stripe.elements().create('paymentRequestButton', {
			paymentRequest,
			style: {
				paymentRequestButton: opts.style,
			},
		}).mount(el);

		// Helper to bind required variables to the event handler
		const bind = function (handler) {
			return function (e) {
				handler(e, state, postInternal);
			};
		};

		// Listen for the events
		paymentRequest.on('shippingaddresschange', bind(onShippingAddressChange));
		paymentRequest.on('shippingoptionchange', bind(onShippingOptionChange));
		paymentRequest.on('token', bind(onToken));

	}).catch(console.error); // eslint-disable-line no-console

	return Object.freeze({
		get items () {
			return Object.freeze(state.items.map(function (item) {
				return Object.freeze(item);
			}));
		},

		set items (items) {
			updateItems(items, state, paymentRequest, postInternal);
		},

		refresh () {
			updateItems(null, state, paymentRequest, postInternal);
		},
	});

};