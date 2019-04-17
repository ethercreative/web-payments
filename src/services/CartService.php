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
use craft\commerce\base\Gateway;
use craft\commerce\base\GatewayInterface;
use craft\commerce\base\Purchasable;
use craft\commerce\elements\Order;
use craft\commerce\models\Address;
use craft\commerce\models\LineItem;
use craft\commerce\models\ShippingMethod;
use craft\commerce\Plugin as Commerce;
use craft\errors\ElementNotFoundException;
use craft\errors\SiteNotFoundException;
use Throwable;
use yii\base\Exception;

/**
 * Class CartService
 *
 * @author  Ether Creative
 * @package ether\webpayments\services
 */
class CartService extends Component
{

	/**
	 * @return Gateway|GatewayInterface|null
	 */
	public function getStripeGateway ()
	{
		// TODO: Make configurable in settings
		return Commerce::getInstance()->getGateways()->getGatewayByHandle('stripe');
	}

	/**
	 * Build an Order from the given array of items
	 *
	 * @param array $items - An array of arrays: ['id' => purchasableId, 'qty' => qty]
	 * @param bool  $save
	 *
	 * @return Order
	 * @throws Throwable
	 * @throws ElementNotFoundException
	 * @throws SiteNotFoundException
	 * @throws Exception
	 */
	public function buildOrder (array $items, $save = false)
	{
		$craft = Craft::$app;
		$elements = $craft->getElements();
		$commerce = Commerce::getInstance();
		$carts = $commerce->getCarts();

		$order = new Order();
		$order->lastIp = $craft->getRequest()->userIP;
		$order->number = $carts->generateCartNumber();
		$order->orderLanguage = Craft::$app->getSites()->getCurrentSite()->language;
		$order->currency = $carts->getCart()->currency;
		$order->setPaymentCurrency($order->currency);

		if ($save)
			$elements->saveElement($order);

		foreach ($items as $item)
		{
			/** @var Purchasable $purchasable */
			$purchasable = $elements->getElementById($item['id']);

			$li = new LineItem();
			$li->setPurchasable($purchasable);
			$li->qty = $item['qty'];
			$li->refreshFromPurchasable();

			if ($save)
				$li->orderId = $order->id;

			$order->addLineItem($li);
		}

		return $order;
	}

	/**
	 * Force the given order to recalculate
	 *
	 * @param Order $order
	 *
	 * @throws Exception
	 */
	public function recalculate (Order $order)
	{
		if (!$order->id)
			$order->id = -1;

		$order->recalculate();

		if ($order->id === -1)
			$order->id = null;
	}

	/**
	 * Convert the given order to a Payment Request object
	 *
	 * @param Order $order
	 * @param bool  $includeItems
	 *
	 * @return array
	 * @throws Exception
	 */
	public function orderToPaymentRequest (Order $order, $includeItems = false)
	{
		$items        = [];
		$displayItems = [];
		$total        = 0;

		$this->recalculate($order);

		foreach ($order->lineItems as $item)
		{
			$amount = $item->purchasable->salePrice * $item->qty * 100;

			$items[] = [
				'id' => $item->purchasableId,
				'qty' => $item->qty,
			];

			$displayItems[] = [
				'id'     => $item->purchasableId,
				'label'  => $item->purchasable->title,
				'amount' => $amount,
			];

			$total += $amount;
		}

		foreach ($order->adjustments as $adjustment)
		{
			$amount = $adjustment->amount * 100;

			$displayItems[] = [
				'id'     => $adjustment->id,
				'type'   => $adjustment->type,
				'label'  => $adjustment->name,
				'amount' => $amount,
			];

			$total += $amount;
		}

		$ret = [
			'displayItems'    => $displayItems,
			'total'           => [
				'label'  => Craft::t('commerce', 'Total'),
				'amount' => $total,
			],
			'shippingOptions' => $this->getShippingMethods($order),
		];

		if ($includeItems)
			$ret['items'] = $items;

		return $ret;
	}

	/**
	 * Get the shipping methods (as Payment Request shipping options)
	 *
	 * @param Order $order
	 *
	 * @return array
	 */
	public function getShippingMethods (Order $order)
	{
		if ($order === null)
			return [];

		$shippingMethods =
			Commerce::getInstance()->getShippingMethods()
		                           ->getAvailableShippingMethods($order);

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

	/**
	 * Builds an address from the given array and sets it on the given order
	 *
	 * @param Order $order
	 * @param array $address
	 *
	 * @return Order
	 */
	public function setShippingAddress (Order $order, array $address)
	{
		$a = new Address();

		$name         = explode(' ', $address['recipient'], 2);
		$a->firstName = $name[0];
		if (count($name) > 1)
			$a->lastName = $name[1];

		$a->address1 = $address['addressLine'][0];
		if (count($address['addressLine']) > 1)
			$a->address2 = $address['addressLine'][1];

		$a->city = $address['city'];

		$a->setStateValue($address['region']);

		$a->countryId =
			Commerce::getInstance()->getCountries()->getCountryByIso(
				$address['country']
			)->id;

		$a->zipCode = $address['postalCode'];

		$order->setShippingAddress($a);

		return $order;
	}

}