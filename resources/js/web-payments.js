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

	return fetch(`${actionTrigger}/web-payments/stripe/${action}`, {
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
			items: state.items,
			token: e.token,
			payerName: e.payerName,
			payerEmail: e.payerEmail,
			payerPhone: e.payerPhone,
			shippingAddress: e.shippingAddress,
			shippingMethod: e.shippingOption,
		});

		// TODO: Handle on completion events (i.e. clear active cart & redirect)
		e.complete(data.status);
	} catch (_) {
		e.complete('fail');
	}
}

// Craft Web Payments
// =============================================================================

window.CraftWebPayments = async function (opts) {

	opts = Object.freeze(opts);

	// Constants
	// -------------------------------------------------------------------------

	const el          = document.getElementById(opts.id)
		, stripe      = window.Stripe(opts.stripeApiKey)
		, postOptions = { actionTrigger: opts.actionTrigger, csrf: opts.csrf };

	const state = {
		items: opts.cart.items,
		shippingAddress: null,
		shippingOption: null,

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

	// Don't bother continuing if we can't make the payment
	if (!await paymentRequest.canMakePayment())
		return;

	// Create the Stripe payment request button & mount it in place
	stripe.elements().create('paymentRequestButton', {
		paymentRequest,
	}).mount(el);

	// Bind the postOptions to the post function
	const postInternal = async function (action, body) {
		return post(postOptions, action, body);
	};

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

};