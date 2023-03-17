<?php
/**
 * Web Payments for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2019 Ether Creative
 */

namespace ether\webpayments\events;

use craft\commerce\elements\Order;
use craft\elements\Address;
use yii\base\Event;

class AddressEvent extends Event
{
    /**
     * @var Order
     */
    public $order;

    /**
     * @var Address
     */
    public $address;

    /**
     * @var array
     */
    public $stripeData;
}
