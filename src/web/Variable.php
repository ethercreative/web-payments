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
use craft\errors\ElementNotFoundException;
use craft\web\View;
use ether\webpayments\web\assets\WebPaymentsAsset;
use ether\webpayments\WebPayments;
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
	 * @return string
	 * @throws InvalidConfigException
	 * @throws Throwable
	 * @throws ElementNotFoundException
	 * @throws Exception
	 */
	public function button (array $options = [])
	{
		$view = Craft::$app->getView();
		$request = Craft::$app->getRequest();
		$general = Craft::$app->getConfig()->getGeneral();
		$wp = WebPayments::getInstance();

		$id = uniqid('cwp_');

		if (array_key_exists('cart', $options) && $options['cart'] instanceof Order)
			$cart = $wp->cart->orderToPaymentRequest(
				$options['cart'],
				true
			);
		else
			$cart = $wp->cart->orderToPaymentRequest(
				$wp->cart->buildOrder($options['items']),
				true
			);

		if (empty($cart['items']))
			return null;

		// TODO: Make default options configurable in plugin settings
		$options = array_merge([
			'id' => $id,
			'csrf' => [$general->csrfTokenName, $request->getCsrfToken()],
			'actionTrigger' => $general->actionTrigger,

			'currency' => Commerce::getInstance()->getCarts()->getCart()->currency,

			'shippingMethods' => [],
			'requestDetails' => ['email'],
			'requestShipping' => false,

			'stripeApiKey' => $wp->cart->getStripeGateway()->settings['publishableKey'],
		], $options);

		$options['cart'] = $cart;

		$view->registerAssetBundle(WebPaymentsAsset::class);
		$view->registerJsFile('https://js.stripe.com/v3/');
		$view->registerJs(
			'CraftWebPayments(' . json_encode($options) . ');',
			View::POS_END
		);

		return new Markup('<span id="' . $id . '"></span>', 'utf8');
	}

}