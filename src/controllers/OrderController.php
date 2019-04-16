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
 * Class OrderController
 *
 * @author  Ether Creative
 * @package ether\webpayments\controllers
 */
class OrderController extends Controller
{

	protected $allowAnonymous = true;

	public function actionPay ()
	{
		$this->requireAcceptsJson();
		$request = Craft::$app->getRequest();
		$wp      = WebPayments::getInstance();
		$commerce = Commerce::getInstance();

		$items = json_decode(
			$request->getRequiredBodyParam('items'),
			true
		);

		$data = json_decode(
			$request->getRequiredBodyParam('data'),
			true
		);

		/** @var Purchasable $purchasable */
		$purchasable =$commerce->purchasables->getPurchasableById(
			$items[0]['id']
		);

		$cart = $wp->cart->orderFromPurchasable($purchasable);
		$cart = $wp->cart->setShippingAddress($cart, $data['shippingAddress']);
		$cart->shippingMethodHandle = $data['shippingOption']['id'];
		$cart->email = $data['payerEmail'];

		$cart->validate();
		die(var_dump($cart->getErrors()));
	}

}