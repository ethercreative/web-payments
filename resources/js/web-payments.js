class WebPayments {

	// Properties
	// =========================================================================

	el = null;
	stripe = null;
	action = '';
	csrf = [];

	shippingAddress = null;
	shippingOption = null;

	// Constructor
	// =========================================================================

	constructor (opts) {
		this.el = document.getElementById(opts.id);
		this.stripe = window.Stripe(opts.stripeApiKey);
		this.action = opts.actionTrigger;
		this.csrf = opts.csrf;

		// TODO: Pass cart items to server to get actual cart (to ensure prices aren't tampered with)?

		const paymentRequestObject = {
			country: opts.country.toUpperCase(),
			currency: opts.currency.toLowerCase(),
			displayItems: opts.cart.displayItems,
			total: opts.cart.total,
			requestShipping: !!opts.requestShipping,
			shippingType: WebPayments._getShippingType(opts.requestShipping),
			shippingOptions: opts.shippingMethods,
			requestPayerName: opts.requestDetails.indexOf('name') > -1,
			requestPayerEmail: opts.requestDetails.indexOf('email') > -1,
			requestPayerPhone: opts.requestDetails.indexOf('phone') > -1,
		};

		const paymentRequest = this.stripe.paymentRequest(paymentRequestObject);

		const elements = this.stripe.elements();
		const prButton = elements.create('paymentRequestButton', {
			paymentRequest,
		});

		(async () => {
			const result = await paymentRequest.canMakePayment();
			if (result)
				prButton.mount(this.el);
		})();

		paymentRequest.on('shippingaddresschange', this.onShippingChange.bind(this, paymentRequestObject));
		paymentRequest.on('shippingoptionchange', this.onShippingChange.bind(this, paymentRequestObject));
		paymentRequest.on('token', this.onGetToken.bind(this, paymentRequestObject));
	}

	// Events
	// =========================================================================

	onShippingChange = async (paymentRequest, e) => {
		try {
			const response = await fetch(this.actionUrl('shipping/update'), {
				method: 'POST',
				body: (() => {
					const fd = new FormData();

					fd.append(this.csrf[0], this.csrf[1]);
					fd.append('paymentRequest', JSON.stringify(paymentRequest));

					if (e.hasOwnProperty('shippingOption'))
						this.shippingOption = e.shippingOption;

					fd.append('shippingOption', JSON.stringify(this.shippingOption));

					if (e.hasOwnProperty('shippingAddress'))
						this.shippingAddress = e.shippingAddress;

					fd.append('shippingAddress', JSON.stringify(this.shippingAddress));

					return fd;
				})(),
				headers: {
					'Accept': 'application/json',
					'X-Requested-With': 'XMLHttpRequest',
				},
			}).then(res => res.json());

			e.updateWith(response);
		} catch (_) {
			e.updateWith({ status: 'fail' });
		}
	};

	onGetToken = async (paymentRequest, e) => {
		try {
			const res = await fetch(this.actionUrl('order/pay'), {
				method: 'POST',
				body: (() => {
					const fd = new FormData();

					fd.append(this.csrf[0], this.csrf[1]);
					fd.append('items', JSON.stringify(paymentRequest.displayItems));
					fd.append('data', JSON.stringify(e));

					return fd;
				})(),
				headers: {
					'Accept': 'application/json',
					'X-Requested-With': 'XMLHttpRequest',
				},
			});

			/*const data = */await res.json();

			e.complete('success');
		} catch (_) {
			e.complete('fail');
		}
	};

	// Helpers
	// =========================================================================

	static _getShippingType (requestShipping) {
		if (typeof requestShipping === 'boolean')
			return 'shipping';

		return requestShipping;
	}

	actionUrl (action) {
		return `${this.action}/web-payments/${action}`;
	}

}

window.CraftWebPayments = WebPayments;