# Web Payments for Craft Commerce

Use the Web Payments API and Apply Pay to vastly improve your checkout process!

## Usage
### `{{ craft.webPayments.button(options) }}`
Place this twig where you want the payments button to appear.

#### Options
Pass a twig object as the only parameter to configure the button.

##### `cart`
Either a Commerce Order object or a valid Web Payments `details` object. Will 
default to the active cart if commerce is installed. 
[See Payment Request Details Option](https://developer.mozilla.org/en-US/docs/Web/API/PaymentRequest/PaymentRequest).

##### `paymentMethods`
An array of twig objects containing details about the available payment methods.

##### `paymentMethods[i].type`
The payment method to use. One of: `commerce` (default), `apple-pay`, `google-pay`.

##### `paymentMethods[i].data`
Additional data for the selected `paymentMethods[i].type`.
[For `commerce`](https://developer.mozilla.org/en-US/docs/Web/API/BasicCardRequest).
[For `apple-pay`](https://developer.apple.com/documentation/apple_pay_on_the_web/applepayrequest).
[For `google-pay`](https://developers.google.com/pay/api/web/reference/object#PaymentDataRequest).

##### `shippingMethods`
The available shipping methods. If commerce is installed this will default to 
the shipping methods available for the given cart (if a Commerce Order).

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
    cart: {
        id: '1',
        displayItems: [
            {
                type: 'lineItem',
                label: 'Example Item',
                amount: { currency: 'GBP', value: '1.00' },
            },
            {
                type: 'shipping',
                label: 'Standard shipping',
                amount: { currency: 'GBP', value: '0.50' },
            },
        ],
        total: {
            label: 'Total',
            amount: { currency: 'GBP', value: '1.50' },
        },
    },
    paymentMethods: [
        {
            type: 'google-pay',
            data: {
                merchantName: 'Google Pay Demo',
                environment: 'TEST',
                allowedCardNetworks: ['AMEX', 'DISCOVER', 'MASTERCARD', 'VISA'],
                paymentMethodTokenizationParameters: {
                    tokenizationType: 'GATEWAY_TOKEN',
                    parameters: {
                        'gateway': 'stripe',
                        'stripe:publishableKey': 'pk_live_lNk21zqKM2BENZENh3rzCUgo',
                        'stripe:version': '2016-07-06',
                    },
                },
            },
        },
        {
            type: 'apple-pay',
            data: {
                version: 3,
                merchantIdentifier: 'merchant.com.example',
                merchantCapabilities: ['supports3DS', 'supportsCredit', 'supportsDebit'],
                supportedNetworks: ['amex', 'discover', 'masterCard', 'visa'],
                countryCode: 'GB',
            },
        },
        {
            type: 'commerce',
            data: {
                supportedNetworks: ['visa', 'mastercard'],
            },
        },
    ],
    shippingMethods: [
        {
            id: 'standard',
            label: 'Standard shipping',
            amount: { currency: 'GBP', value: '0.50' },
            selected: true,
        },
        {
            id: 'free',
            label: 'Free shipping',
            amount: { currency: 'GBP', value: '0.00' },
        },
    ],
    requestShipping: 'delivery',
    requestDetails: ['name', 'email'],
}) }}
```