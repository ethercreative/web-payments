<?php
/**
 * Web Payments for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2019 Ether Creative
 */

namespace ether\webpayments\web;

use Craft;
use craft\commerce\elements\Order;
use craft\commerce\Plugin as Commerce;
use craft\commerce\stripe\Plugin as StripePlugin;
use craft\errors\ElementNotFoundException;
use craft\errors\SiteNotFoundException;
use craft\helpers\App;
use craft\web\View;
use ether\webpayments\web\assets\WebPaymentsAsset;
use ether\webpayments\WebPayments;
use Stripe\PaymentIntent;
use Throwable;
use Twig\Markup;
use yii\base\Exception;
use yii\base\InvalidConfigException;

/**
 * Class Variable
 *
 * @author  Ether Creative
 * @package ether\webpayments\web
 */
class Variable
{

	/**
	 * Render the payment button
	 *
	 * @param array $options
	 *
	 * @return string|Markup|null
	 * @throws InvalidConfigException
	 * @throws Throwable
	 * @throws ElementNotFoundException
	 * @throws Exception
	 */
	public function button (array $options = []): string|Markup|null
    {
		$view = Craft::$app->getView();
		$request = Craft::$app->getRequest();
		$general = Craft::$app->getConfig()->getGeneral();
		$wp = WebPayments::getInstance()->stripe;
		$commerce = Commerce::getInstance();
		$settings = WebPayments::getInstance()->getSettings();

		$id = uniqid('cwp_');

		$cart = $this->_getCart($options);

		$oldPrecision = ini_get('serialize_precision');
		if (version_compare(phpversion(), '7.1', '>='))
			ini_set('serialize_precision', 14);

		if ($cart === null || empty($cart['items']))
			return null;

		$jsVariable = '';
		if (!empty($options['js']))
			$jsVariable = $options['js'] . '=';

		$gateway = $wp->getStripeGateway();
		$gatewayType = explode('\\', get_class($gateway));
		$gatewayType = end($gatewayType);

		$options = array_merge([
			'id' => $id,
			'csrf' => [$general->csrfTokenName, $request->getCsrfToken()],
			'actionTrigger' => $general->actionTrigger,

			'country' => $commerce->getStore()->getStore()->getLocationAddress()->getCountryCode(),
			'currency' => $commerce->getCarts()->getCart()->currency,
			'shippingMethods' => [],

			'requestDetails' => $settings->requestDetails,
			'requestShipping' => $settings->requestShipping === 'no' ? false : $settings->requestShipping,

			'onComplete' => [],

			'stripeGatewayType' => $gatewayType,
			'stripeApiKey' => App::parseEnv($gateway->settings['publishableKey']),
			'style' => [],
		], $options);

		$options['cart'] = $cart;

		if (is_string($options['requestDetails']))
			$options['requestDetails'] = [$options['requestDetails']];

		if (!in_array('email', $options['requestDetails']))
			$options['requestDetails'][] = 'email';

		$view->registerAssetBundle(WebPaymentsAsset::class);
		$view->registerJsFile('https://js.stripe.com/v3/');
		$view->registerJs(
			$jsVariable . 'CraftWebPayments(' . json_encode($options) . ');',
			View::POS_END
		);

		if (version_compare(phpversion(), '7.1', '>='))
			ini_set('serialize_precision', $oldPrecision);

		return new Markup('<div id="' . $id . '"></div>', 'utf8');
	}

	// Helpers
	// =========================================================================

	/**
	 * @param array $options
	 *
	 * @return array|null
	 * @throws ElementNotFoundException
	 * @throws Exception
	 * @throws Throwable
	 * @throws SiteNotFoundException
	 */
	private function _getCart (array $options = []): ?array
    {
		$wp = WebPayments::getInstance()->stripe;

		if (array_key_exists('cart', $options) && $options['cart'] instanceof Order)
			return $wp->orderToPaymentRequest($options['cart'], true);

		if (array_key_exists('items', $options))
			return $wp->orderToPaymentRequest($wp->buildOrder(null, $options['items']), true);

		return null;
	}

}
