<?php
/**
 * Web Payments for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2019 Ether Creative
 */

namespace ether\webpayments;

use craft\base\Plugin;
use craft\web\twig\variables\CraftVariable;
use ether\webpayments\services\CartService;
use ether\webpayments\web\Variable;
use yii\base\Event;
use yii\base\InvalidConfigException;

/**
 * Class WebPayments
 *
 * @author  Ether Creative
 * @package ether\webpayments
 * @property CartService $cart
 */
class WebPayments extends Plugin
{

	// Craft
	// =========================================================================

	public function init ()
	{
		parent::init();

		$this->setComponents([
			'cart' => CartService::class,
		]);

		// Events
		// ---------------------------------------------------------------------

		Event::on(
			CraftVariable::class,
			CraftVariable::EVENT_INIT,
			[$this, 'onRegisterVariable']
		);

	}

	// Events
	// =========================================================================

	/**
	 * @param Event $event
	 *
	 * @throws InvalidConfigException
	 */
	public function onRegisterVariable (Event $event)
	{
		/** @var CraftVariable $variable */
		$variable = $event->sender;
		$variable->set('webPayments', Variable::class);
	}

}