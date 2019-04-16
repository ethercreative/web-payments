<?php
/**
 * Web Payments for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2019 Ether Creative
 */

namespace ether\webpayments\services;

use Craft;
use craft\base\Component;
use craft\commerce\base\Purchasable;
use craft\commerce\elements\Order;
use craft\commerce\models\Address;
use craft\commerce\models\LineItem;
use craft\commerce\models\ShippingMethod;
use craft\commerce\Plugin as Commerce;

/**
 * Class CartService
 *
 * @author  Ether Creative
 * @package ether\webpayments\services
 */
class CartService extends Component
{

	public function orderFromPurchasable (Purchasable $purchasable)
	{
		$li = new LineItem();
		$li->setPurchasable($purchasable);
		$li->qty = 1;

		$order = new Order();
		$order->addLineItem($li);

		return $order;
	}

	public function cartToPaymentRequest (Order $order)
	{
		$displayItems = [];
		$total = 0;

		foreach ($order->lineItems as $item)
		{
			$amount = $item->purchasable->salePrice * 100;

			$displayItems[] = [
				'id' => $item->purchasableId,
				'label' => $item->purchasable->title,
				'amount' => $amount,
			];

			$total += $amount;
		}

		foreach ($order->adjustments as $adjustment)
		{
			$amount = $adjustment->amount * 100;

			$displayItems[] = [
				'id' => $adjustment->id,
				'type' => $adjustment->type,
				'label' => $adjustment->name,
				'amount' => $amount,
			];

			$total += $amount;
		}

		$shippingOptions = $this->getShippingMethods($order);
		$shippingMethod = $order->shippingMethod;
		$shippingOption = null;

		if ($shippingMethod === null && !empty($shippingOptions))
			$shippingMethod = Commerce::getInstance()->getShippingMethods()->getShippingMethodByHandle($shippingOptions['id']);

		if ($shippingMethod)
		{
			$shippingOption = [
				'id'     => $shippingMethod->getHandle(),
				'label'  => $shippingMethod->getName(),
				'amount' => $shippingMethod->getPriceForOrder($order) * 100,
			];

			$displayItems[] = [
				'id'     => $shippingOption['id'],
				'type'   => 'shipping',
				'label'  => $shippingOption['label'],
				'amount' => $shippingOption['amount'],
			];

			$total += $shippingOption['amount'];
		}

		return [
			'displayItems' => $displayItems,
			'total' => [
				'label' => Craft::t('commerce', 'Total'),
				'amount' => $total,
			],
			'shippingOptions' => $shippingOptions,
		];
	}

	public function getShippingMethods (Order $order)
	{
		if ($order === null)
			return [];

		$shippingMethods = Commerce::getInstance()->getShippingMethods()->getAvailableShippingMethods($order);

		$methods = [];

		/** @var ShippingMethod $method */
		foreach ($shippingMethods as $method)
		{
			$methods[] = [
				'id'     => $method->handle,
				'label'  => $method->name,
				'amount' => $method->getPriceForOrder($order) * 100,
			];
		}

		return $methods;
	}

	public function setShippingAddress (Order $order, array $address)
	{
		$a = new Address();

		$name = explode(' ', $address['recipient'], 2);
		$a->firstName = $name[0];
		if (count($name) > 1)
			$a->lastName = $name[1];

		$a->address1 = $address['addressLine'][0];
		if (count($address['addressLine']) > 1)
			$a->address2 = $address['addressLine'][1];

		$a->city = $address['city'];

		$a->setStateValue($address['region']);

		$a->countryId = Commerce::getInstance()->getCountries()->getCountryByIso(
			$address['country']
		)->id;

		$a->zipCode = $address['postalCode'];

		$order->setShippingAddress($a);

		return $order;
	}

}