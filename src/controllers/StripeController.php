<?php
/**
 * Web Payments for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2019 Ether Creative
 */

namespace ether\webpayments\controllers;

use Craft;
use craft\base\Field;
use craft\errors\ElementNotFoundException;
use craft\errors\SiteNotFoundException;
use craft\helpers\Json;
use craft\web\Controller;
use ether\webpayments\WebPayments;
use Throwable;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;
use yii\web\BadRequestHttpException;
use yii\web\Response;
use craft\commerce\Plugin as Commerce;

/**
 * Class DefaultController
 *
 * @author  Ether Creative
 * @package ether\webpayments\controllers
 */
class StripeController extends Controller
{

	protected $allowAnonymous = true;

	/**
	 * @return Response
	 * @throws BadRequestHttpException
	 * @throws ElementNotFoundException
	 * @throws Exception
	 * @throws SiteNotFoundException
	 * @throws Throwable
	 */
	public function actionUpdateDisplayItems ()
	{
		$this->requireAcceptsJson();
		$this->requirePostRequest();

		$request = Craft::$app->getRequest();
		$wp = WebPayments::getInstance()->stripe;

		$cartId = $request->getRequiredBodyParam('cartId');

		$items = Json::decodeIfJson(
			$request->getRequiredBodyParam('items'),
			true
		);

		return $this->asJson(
			$wp->orderToPaymentRequest(
				$wp->buildOrder($cartId, $items ?: [])
			)
		);
	}

	/**
	 * @return Response
	 * @throws Throwable
	 * @throws ElementNotFoundException
	 * @throws SiteNotFoundException
	 * @throws Exception
	 * @throws BadRequestHttpException
	 */
	public function actionUpdateAddress ()
	{
		$this->requireAcceptsJson();
		$this->requirePostRequest();

		$request = Craft::$app->getRequest();
		$wp      = WebPayments::getInstance()->stripe;

		$cartId = $request->getRequiredBodyParam('cartId');

		$items = Json::decodeIfJson(
			$request->getRequiredBodyParam('items'),
			true
		);

		$address = Json::decodeIfJson(
			$request->getRequiredBodyParam('address'),
			true
		);

		$order = $wp->buildOrder($cartId, $items);
		$wp->setShippingAddress($order, $address);

		if (!$order->shippingAddress->validate())
			return $this->asJson(['status' => 'invalid_shipping_address']);

		$shippingMethods = $order->getAvailableShippingMethods();
		if (!empty($shippingMethods))
			$order->shippingMethodHandle = $shippingMethods[key($shippingMethods)]->handle;

		return $this->asJson(array_merge(
			$wp->orderToPaymentRequest($order),
			['status' => 'success']
		));
	}

	/**
	 * @return Response
	 * @throws BadRequestHttpException
	 * @throws ElementNotFoundException
	 * @throws Exception
	 * @throws SiteNotFoundException
	 * @throws Throwable
	 */
	public function actionUpdateShipping ()
	{
		$this->requireAcceptsJson();
		$this->requirePostRequest();

		$request = Craft::$app->getRequest();
		$wp      = WebPayments::getInstance()->stripe;

		$cartId = $request->getRequiredBodyParam('cartId');

		$items = Json::decodeIfJson(
			$request->getRequiredBodyParam('items'),
			true
		);

		$address = Json::decodeIfJson(
			$request->getRequiredBodyParam('address'),
			true
		);

		$method = Json::decodeIfJson(
			$request->getRequiredBodyParam('method'),
			true
		);

		$order = $wp->buildOrder($cartId, $items);
		$wp->setShippingAddress($order, $address);
		$order->shippingMethodHandle = $method['id'];

		return $this->asJson(array_merge(
			$wp->orderToPaymentRequest($order),
			['status' => 'success']
		));
	}

	/**
	 * @return Response
	 * @throws BadRequestHttpException
	 * @throws ElementNotFoundException
	 * @throws Exception
	 * @throws SiteNotFoundException
	 * @throws Throwable
	 * @throws InvalidConfigException
	 * @throws NotSupportedException
	 */
	public function actionPay ()
	{
		$this->requireAcceptsJson();
		$this->requirePostRequest();

		$request = Craft::$app->getRequest();
		$wp      = WebPayments::getInstance()->stripe;
		$settings= WebPayments::getInstance()->getSettings();

		$cartId = $request->getRequiredBodyParam('cartId');
		$email = $request->getRequiredBodyParam('payerEmail');

		$requestDetails = [
			'name' => $request->getBodyParam('payerName'),
			'phone' => $request->getBodyParam('payerPhone'),
		];

		$name = $requestDetails['name'];

		$token = Json::decodeIfJson(
			$request->getRequiredBodyParam('token'),
			true
		);

		$items = Json::decodeIfJson(
			$request->getRequiredBodyParam('items'),
			true
		);

		$address = Json::decodeIfJson(
			$request->getRequiredBodyParam('shippingAddress'),
			true
		);

		$method = Json::decodeIfJson(
			$request->getRequiredBodyParam('shippingMethod'),
			true
		);

		$order = $wp->buildOrder($cartId, $items, true);
		$wp->setBillingAddress($order, $token, $name);

		if (!empty($address))
			$wp->setShippingAddress($order, $address, $name);

		if (!empty($method))
			$order->shippingMethodHandle = $method['id'];

		$order->setEmail($email);

		foreach ($settings->requestDetailFields as $key => $uid)
		{
			if (empty($uid) || !$requestDetails[$key])
				continue;

			/** @var Field $field */
			$field = Craft::$app->getFields()->getFieldByUid($uid);
			/** @noinspection PhpParamsInspection */
			$order->setFieldValue($field->handle, $requestDetails[$key]);
		}

		if (!$order->validate())
		{
			$status = 'fail';

			foreach ($order->getErrorSummary(true) as $error)
			{
				if (strpos($error, 'email') !== false)
				{
					$status = 'invalid_payer_email';
					break;
				}
				else if (strpos($error, 'address') !== false)
				{
					$status = 'invalid_shipping_address';
					break;
				}
			}

			return $this->asJson([ 'status' => $status ]);
		}

		$order->setGatewayId($wp->getStripeGateway()->id);
		$gateway = $order->getGateway();

		$paymentSource = $order->getPaymentSource();
		$paymentForm = $gateway->getPaymentFormModel();
		$paymentForm->setAttributes([
			'token' => $token['id'],
		], false);

		if ($paymentSource)
			$paymentForm->populateFromPaymentSource($paymentSource);

		$order->recalculate();
		Craft::$app->elements->saveElement($order);

		$redirect    = '';
		$transaction = null;

		try {
			Commerce::getInstance()->getPayments()->processPayment(
				$order,
				$paymentForm,
				$redirect,
				$transaction
			);
		} catch (Throwable $e) {
			return $this->asJson(['status' => 'fail']);
		}

		return $this->asJson([
			'status' => 'success',
			'number' => $order->number,
		]);
	}

}