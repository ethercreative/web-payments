<?php
/**
 * Web Payments for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2019 Ether Creative
 */

namespace ether\webpayments\controllers;

use Craft;
use craft\commerce\base\Purchasable;
use craft\commerce\Plugin as Commerce;
use craft\web\Controller;
use ether\webpayments\WebPayments;

/**
 * Class ShippingController
 *
 * @author  Ether Creative
 * @package ether\webpayments\controllers
 */
class ShippingController extends Controller
{

	protected $allowAnonymous = true;

	public function actionUpdate ()
	{
		$this->requireAcceptsJson();
		$request = Craft::$app->getRequest();
		$wp = WebPayments::getInstance();

		$paymentRequest = json_decode(
			$request->getRequiredBodyParam('paymentRequest'),
			true
		);

		$shippingOption = $request->getBodyParam('shippingOption');
		$shippingAddress = $request->getBodyParam('shippingAddress');

		if ($shippingOption)
			$shippingOption = json_decode($shippingOption, true);

		if ($shippingAddress)
			$shippingAddress = json_decode($shippingAddress, true);

		/** @var Purchasable $purchasable */
		$purchasable = Commerce::getInstance()->getPurchasables()->getPurchasableById(
			$paymentRequest['displayItems'][0]['id']
		);

		$cart = $wp->cart->orderFromPurchasable($purchasable);

		if ($shippingAddress)
			$cart = $wp->cart->setShippingAddress($cart, $shippingAddress);

		if ($shippingOption)
			$cart->shippingMethodHandle = $shippingOption['id'];

		return $this->asJson(array_merge(
			$wp->cart->cartToPaymentRequest($cart),
			[ 'status' => 'success' ]
		));
	}

}