/* global ApplePaySession */

class WebPayments {

	// Properties
	// =========================================================================

	static METHOD_APPLE    = 'apple-pay';
	static METHOD_COMMERCE = 'commerce';
	static METHOD_GOOGLE   = 'google-pay';

	placeholderEl   = null;
	paymentMethods  = [];
	shippingMethods = null;
	cart            = null;
	requestDetails  = [];
	requestShipping = false;

	// Getters
	// =========================================================================

	static get supportsApplePay () {
		return window.ApplePaySession && ApplePaySession.canMakePayments();
	}

	get shippingType () {
		if (typeof this.requestShipping === 'boolean')
			return 'shipping';

		return this.requestShipping;
	}

	// Constructor
	// =========================================================================

	constructor (opts) {
		this.placeholderEl   = document.getElementById(opts.id);
		this.cart            = opts.cart;
		this.shippingMethods = opts.shippingMethods;
		this.requestDetails  = opts.requestDetails;
		this.requestShipping = opts.requestShipping;

		const methodKeys = [];
		this.paymentMethods = opts.paymentMethods.map(({ type, data }) => {
			methodKeys.push(type);

			return {
				supportedMethods: WebPayments.paymentMethodType(type),
				data,
			};
		});

		if (window.PaymentRequest)
			this.initPaymentRequest();
		else if (WebPayments.supportsApplePay && methodKeys.indexOf(WebPayments.METHOD_APPLE) > -1)
			this.initApplePay();
	}

	// Initializers
	// =========================================================================

	initPaymentRequest () {
		if (WebPayments.supportsApplePay)
			this.showApplyPayButton();
		else
			this.showGenericButton();
	}

	initApplePay () {
		this.showApplyPayButton();
	}

	// Actions
	// =========================================================================

	// Payment Buttons
	// -------------------------------------------------------------------------

	showApplyPayButton () {
		const btn = document.createElement('button');
		btn.style.setProperty('-webkit-appearance', '-apple-pay-button');
		btn.addEventListener('click', this.onApplePayClick);

		this.injectButton(btn);
	}

	showGenericButton () {
		const btn = document.createElement('button');
		btn.textContent = 'Pay';
		btn.addEventListener('click', this.onGenericPayClick);

		this.injectButton(btn);
	}

	// Cart
	// -------------------------------------------------------------------------

	async updateCart (paymentRequest) {
		// TODO: If commerce cart do it server-side

		let activeMethod = null;

		const shippingOptions = [...this.shippingMethods].map(method => {
			const isActive = method.id === paymentRequest.shippingOption;

			if (isActive) activeMethod = method;
			method.selected = isActive;

			return method;
		});

		let total = 0;

		const displayItems = [...this.cart.displayItems].map(item => {
			if (item.type === 'shipping')
				return null;

			total += +item.amount.value;
			return item;
		}).filter(Boolean);

		if (activeMethod) {
			total += +activeMethod.amount.value;

			displayItems.push({
				type: 'shipping',
				label: activeMethod.label,
				amount: activeMethod.amount,
			});
		}

		return {
			...this.cart,
			displayItems,
			total: {
				...this.cart.total,
				amount: {
					...this.cart.total.amount,
					value: total,
				},
			},
			shippingOptions,
		};
	}

	// Validation
	// -------------------------------------------------------------------------

	async validateCart () {
		// TODO: Validate that the cart hasn't been tampered with?

		return true;
	}
	
	// Payment Requests
	// -------------------------------------------------------------------------

	async requestApplePayment () {
		if (window.PaymentRequest) {
			await this.requestGenericPayment();
			return;
		}

		if (!await this.validateCart())
			return;

		// TODO: Use old Apple Pay API
	}

	async requestGenericPayment () {
		if (!await this.validateCart())
			return;

		const request = new PaymentRequest(
			this.paymentMethods,
			{
				...this.cart,
				shippingOptions: this.shippingMethods,
			},
			{
				requestPayerName: this.requestDetails.indexOf('name') > -1,
				requestPayerEmail: this.requestDetails.indexOf('email') > -1,
				requestPayerPhone: this.requestDetails.indexOf('phone') > -1,
				requestShipping: !!this.requestShipping,
				shippingType: this.shippingType,
			}
		);

		request.addEventListener(
			'paymentmethodchange',
			this.onPaymentMethodChange
		);

		request.addEventListener(
			'payerdetailchange',
			this.onPayerDetailChange
		);

		request.addEventListener(
			'merchantvalidation',
			this.onMerchantValidation
		);

		request.addEventListener(
			'shippingaddresschange',
			this.onShippingAddressChange
		);

		request.addEventListener(
			'shippingoptionchange',
			this.onShippingMethodChange
		);

		let response;

		try {
			response = await request.show();
		} catch (e) {
			// TODO: Show error message if not cancelled (ignore otherwise)
			console.log(e.message); // Likely to be "Request cancelled"
			return;
		}

		console.log(response);

		await response.complete('success');
	}

	// Events
	// =========================================================================

	// Button Click Events
	// -------------------------------------------------------------------------

	onApplePayClick = async e => {
		e.preventDefault();

		await this.requestApplePayment();
	};

	onGenericPayClick = async e => {
		e.preventDefault();

		await this.requestGenericPayment();
	};

	// Payment Request Events
	// -------------------------------------------------------------------------

	onPaymentMethodChange = e => {
		console.log(e.target);
	};

	onPayerDetailChange = e => {
		console.log(e.target);
	};

	onMerchantValidation = (/*e*/) => {
		// TODO: this
	};

	onShippingAddressChange = e => {
		e.updateWith(this.updateCart(e.target));
	};

	onShippingMethodChange = e => {
		e.updateWith(this.updateCart(e.target));
	};

	// Helpers
	// =========================================================================

	static paymentMethodType (type) {
		switch (type) {
			case WebPayments.METHOD_APPLE:
				return 'https://apple.com/apple-pay';
			case WebPayments.METHOD_COMMERCE:
				return 'basic-card';
			case WebPayments.METHOD_GOOGLE:
				return 'https://google.com/pay';
		}

		return null;
	}

	injectButton (btn) {
		this.placeholderEl.parentNode.insertBefore(btn, this.placeholderEl);
		this.placeholderEl.parentNode.removeChild(this.placeholderEl);
	}

}

window.CraftWebPayments = WebPayments;