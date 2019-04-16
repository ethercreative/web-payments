<?php
/**
 * Web Payments for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2019 Ether Creative
 */

namespace ether\webpayments\web;

use Craft;
use craft\commerce\base\Purchasable;
use craft\commerce\elements\Order;
use craft\commerce\models\ShippingMethod;
use craft\commerce\Plugin as Commerce;
use craft\web\View;
use ether\webpayments\web\assets\WebPaymentsAsset;
use ether\webpayments\WebPayments;
use Twig\Markup;
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
	 */
	public function button (array $options = [])
	{
		$view = Craft::$app->getView();
		$request = Craft::$app->getRequest();
		$general = Craft::$app->getConfig()->getGeneral();
		$wp = WebPayments::getInstance();

		$id = uniqid('cwp_');

		list($cart, $order) = $this->_cartFromOpts($options);

		// TODO: Make default options configurable in plugin settings
		$options = array_merge([
			'id' => $id,
			'csrf' => [$general->csrfTokenName, $request->getCsrfToken()],
			'actionTrigger' => $general->actionTrigger,

			'cart' => $cart,

			'paymentMethods' => [],
			'shippingMethods' => [], // $wp->cart->getShippingMethods($order),
			'requestDetails' => [],
			'requestShipping' => false,

			'stripeApiKey' => 'pk_test_M9f7RxQ2I3SWGsQhrUlTMpUT00C4DptGeg',
		], $options);

		// TODO: if $options['cart'] instanceof Order convert to WebPayments cart
		// TODO: if paymentMethod is commerce auto-populate cards opt if not set

		$view->registerAssetBundle(WebPaymentsAsset::class);
		$view->registerJsFile('https://js.stripe.com/v3/');
		$view->registerJs(
			'new CraftWebPayments(' . json_encode($options) . ');',
			View::POS_END
		);

		return new Markup('<span id="' . $id . '"></span>', 'utf8');
	}

	// Helpers
	// =========================================================================

	private function _cartFromOpts (array $opts)
	{
		$order = null;

		$cart = [
			'displayItems' => [],
			'total' => [
				'label' => Craft::t('commerce', 'Total'),
				'amount' => 0,
			],
		];

		// From Purchasable
		// ---------------------------------------------------------------------

		if (array_key_exists('purchasable', $opts))
		{
			/** @var Purchasable $purchasable */
			$purchasable = $opts['purchasable'];
			$amount = $purchasable->salePrice * 100;

			$cart['displayItems'][] = [
				'id' => $purchasable->id,
				'label' => $purchasable->title,
				'amount' => $amount,
			];

			$cart['total']['amount'] += $amount;

			$order = WebPayments::getInstance()->cart->orderFromPurchasable(
				$purchasable
			);
		}

		// From Cart
		// ---------------------------------------------------------------------

		else if (array_key_exists('cart', $opts))
		{
			// TODO: from given cart
		}

		else
		{
			// TODO: Use default cart
		}

		// Shipping
		// ---------------------------------------------------------------------

		if (
			array_key_exists('shippingMethods', $opts) &&
			array_key_exists('requestShipping', $opts) &&
			!empty($opts['shippingMethods']) &&
			$opts['requestShipping'] !== false
		) {
			$defaultMethod = $opts['shippingMethods'][0];
			$amount = $defaultMethod['amount'];

			$cart['displayItems'][] = [
				'label' => $defaultMethod['label'],
				'amount' => $amount,
			];

			$cart['total']['amount'] += $amount;
		}

		return [$cart, $order];
	}

}