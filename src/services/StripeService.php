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
use craft\commerce\base\GatewayInterface;
use craft\commerce\base\Purchasable;
use craft\commerce\elements\Order;
use craft\elements\Address;
use craft\commerce\models\LineItem;
use craft\commerce\models\ShippingMethod;
use craft\commerce\Plugin as Commerce;
use craft\db\Query;
use craft\errors\ElementNotFoundException;
use craft\errors\SiteNotFoundException;
use craft\helpers\Json;
use ether\webpayments\events\AddressEvent;
use ether\webpayments\WebPayments;
use Throwable;
use yii\base\Exception;
use yii\base\InvalidConfigException;

/**
 * Class CartService
 *
 * @author  Ether Creative
 * @package ether\webpayments\services
 */
class StripeService extends Component
{
    public const EVENT_MODIFY_ADDRESS = 'modifyAddress';

	/**
	 * @return GatewayInterface|null
	 * @throws InvalidConfigException
	 */
	public function getStripeGateway (): GatewayInterface|null
    {
		$uid = WebPayments::getInstance()->getSettings()->gatewayUid;

		if (!$uid)
			throw new InvalidConfigException('Gateway must be set!');

		$id = (new Query())
			->select('id')
			->where([
				'uid' => WebPayments::getInstance()->getSettings()->gatewayUid,
			])
			->from('{{%commerce_gateways}}')
			->column()[0];

		return Commerce::getInstance()->getGateways()->getGatewayById($id);
	}

	/**
	 * Build an Order from the given array of items
	 *
	 * @param int|string|null $cartId
	 * @param array $items - An array of arrays: ['id' => purchasableId, 'qty' => qty]
	 * @param bool $save
	 *
	 * @return Order
	 * @throws ElementNotFoundException
	 * @throws Exception
	 * @throws SiteNotFoundException
	 * @throws Throwable
	 */
	public function buildOrder (int|string|null $cartId, array $items, bool $save = false): Order
    {
		$cartId = Json::decodeIfJson($cartId);

		if ($cartId)
			return Commerce::getInstance()->getOrders()->getOrderById($cartId);

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

            $li = Commerce::getInstance()->getLineItems()->createLineItem($order, $purchasable->id, $item['options'] ?? []);
			$li->qty = $item['qty'];
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
	 * @param bool $includeItems
	 *
	 * @return array
	 * @throws Exception
	 */
	public function orderToPaymentRequest (Order $order, bool $includeItems = false): array
    {
		$items        = [];
		$displayItems = [];
		$total        = 0;

		$this->recalculate($order);

		foreach ($order->lineItems as $item)
		{
			$amount = round($item->salePrice * $item->qty * 100);

			$items[] = [
				'id' => $item->purchasableId,
				'qty' => $item->qty,
				'options' => $item->getOptions(),
			];

			$displayItems[] = [
				'label'  => $item->purchasable->title,
				'amount' => $amount,
			];

			$total += $amount;
		}

		foreach ($order->adjustments as $adjustment)
			if (!$adjustment->included)
			{
				$amount = round($adjustment->amount * 100);

				$displayItems[] = [
					'label'  => $adjustment->name,
					'amount' => $amount,
				];

				$total += $amount;
			}

		$ret = [
			'id'              => $order->id,
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
	 * @param Order|null $order
	 *
	 * @return array
	 */
	public function getShippingMethods (?Order $order): array
    {
		if ($order === null)
			return [];

		$shippingMethods = Commerce::getInstance()->getShippingMethods()->getMatchingShippingMethods($order);

		if (empty($shippingMethods) && Commerce::getInstance()->edition === Commerce::EDITION_LITE)
		{
			$method = Commerce::getInstance()->getShippingMethods()->getLiteShippingMethod();
			$shippingMethods = [$method->handle => $method];
		}

		$methods = [];

		/** @var ShippingMethod $method */
		foreach ($shippingMethods as $method)
		{
			$methods[] = [
				'id'     => $method->handle,
				'label'  => $method->name,
				'amount' => round($method->getPriceForOrder($order) * 100),
			];
		}

		return $methods;
	}

	/**
	 * Builds an address from the given array and sets it on the given order
	 *
	 * @param Order  $order
	 * @param array  $address
	 * @param string|null $fallbackName
	 */
	public function setShippingAddress (Order $order, array $address, ?string $fallbackName = null)
	{
		if (empty($address) || empty($address['country']))
			return;

		if (empty($fallbackName))
			$fallbackName = 'Unknown Person';

		$a = new Address();

		$name = explode(' ', $address['recipient'] ?: $fallbackName, 2);
		$a->firstName = $name[0];
		if (count($name) > 1)
			$a->lastName = $name[1];

		if (!empty($address['addressLine']))
			$a->addressLine1 = $address['addressLine'][0];
		if (count($address['addressLine']) > 1)
			$a->addressLine2 = $address['addressLine'][1];

		$a->locality = $address['city'];
		$a->administrativeArea = $address['region'];
		$a->countryCode = $address['country'];
		$a->postalCode = $address['postalCode'];
        $a->ownerId = $order->id;

        if ($this->hasEventHandlers(static::EVENT_MODIFY_ADDRESS)) {
            $event = new AddressEvent([
                'order' => $order,
                'address' => $a,
                'stripeData' => $address,
            ]);

            $this->trigger(static::EVENT_MODIFY_ADDRESS, $event);
            $a = $event->address;
        }

		$order->setShippingAddress($a);
	}

	/**
	 * Set the billing address from the given Stripe Token array on the given order
	 *
	 * @param Order $order
	 * @param array $token
	 * @param string|null  $fallbackName
	 */
	public function setBillingAddress (Order $order, array $token, ?string $fallbackName = null)
	{
		$address = $token['card'];

		if (empty($address) || empty($address['address_country']))
			return;

		if (empty($fallbackName))
			$fallbackName = 'Unknown Person';

		$a = new Address();

		$name = explode(' ', $address['name'] ?: $fallbackName, 2);

		$a->firstName = $name[0];
		if (count($name) > 1)
			$a->lastName = $name[1];

		$a->addressLine1 = $address['address_line1'];
		$a->addressLine2 = $address['address_line2'];
		$a->locality = $address['address_city'];
		$a->administrativeArea = $address['address_state'];
		$a->countryCode = $address['address_country'];
		$a->postalCode = $address['address_zip'];
        $a->ownerId = $order->id;

        if ($this->hasEventHandlers(static::EVENT_MODIFY_ADDRESS)) {
            $event = new AddressEvent([
                'order' => $order,
                'address' => $a,
                'stripeData' => $address,
            ]);

            $this->trigger(static::EVENT_MODIFY_ADDRESS, $event);
            $a = $event->address;
        }

		$order->setBillingAddress($a);
	}

}
