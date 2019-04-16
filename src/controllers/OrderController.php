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
		$purchasable = $commerce->purchasables->getPurchasableById(
			$items[0]['id']
		);

		// TODO: Support multiple items
		// TODO: Billing address
		// TODO: Don't require shipping address
		$order = $wp->cart->orderFromPurchasable($purchasable, true);
		$order = $wp->cart->setShippingAddress($order, $data['shippingAddress']);
		$order->shippingMethodHandle = $data['shippingOption']['id'];
		$order->email = $data['payerEmail'];

		$order->setGatewayId(
			// TODO: don't hard-code
			$commerce->getGateways()->getGatewayByHandle('stripe')->id
		);

		$gateway = $order->getGateway();

		$paymentForm = $gateway->getPaymentFormModel();
		$token = $data['token'];

		// TODO: Account for 3D Secure
		$paymentForm->setAttributes([
			'token' => $token['id'],
		], false);
		$paymentSource = $order->getPaymentSource();

		if ($paymentSource)
			$paymentForm->populateFromPaymentSource($paymentSource);

		$order->recalculate();

		$paymentForm->validate();

		Craft::$app->elements->saveElement($order);

		$redirect = '';
		$transaction = null;

		$commerce->getPayments()->processPayment(
			$order,
			$paymentForm,
			$redirect,
			$transaction
		);

		// TODO: Error handling

		return $this->asJson(['status' => 'success']);
	}

}