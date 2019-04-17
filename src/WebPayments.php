<?php
/**
 * Web Payments for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2019 Ether Creative
 */

namespace ether\webpayments;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\fields\PlainText;
use craft\web\twig\variables\CraftVariable;
use craft\commerce\Plugin as Commerce;
use ether\webpayments\models\Settings;
use ether\webpayments\services\StripeService;
use ether\webpayments\web\Variable;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use yii\base\Event;
use yii\base\InvalidConfigException;

/**
 * Class WebPayments
 *
 * @author  Ether Creative
 * @package ether\webpayments
 * @property StripeService $stripe
 */
class WebPayments extends Plugin
{

	// Properties
	// =========================================================================

	public $hasCpSettings = true;

	// Craft
	// =========================================================================

	public function init ()
	{
		parent::init();

		$this->setComponents([
			'stripe' => StripeService::class,
		]);

		// Events
		// ---------------------------------------------------------------------

		Event::on(
			CraftVariable::class,
			CraftVariable::EVENT_INIT,
			[$this, 'onRegisterVariable']
		);

	}

	// Settings
	// =========================================================================

	protected function createSettingsModel ()
	{
		return new Settings();
	}

	/**
	 * @return string|null
	 * @throws LoaderError
	 * @throws RuntimeError
	 * @throws SyntaxError
	 */
	protected function settingsHtml ()
	{
		$gateways = [];

		foreach (Commerce::getInstance()->getGateways()->getAllCustomerEnabledGateways() as $gateway)
		{
			if (strpos(get_class($gateway), 'stripe') === false)
				continue;

			$gateways[$gateway->uid] = $gateway->name;
		}

		$fields = [
			null => '---',
		];

		foreach (Craft::$app->getFields()->getAllFields() as $field)
		{
			if (!($field instanceof PlainText))
				continue;

			$fields[$field->uid] = $field->name;
		}

		return Craft::$app->getView()->renderTemplate('web-payments/_settings', [
			'settings' => $this->getSettings(),
			'gateways' => $gateways,
			'fields' => $fields,
		]);
	}

	/**
	 * @return Settings|bool|Model|null
	 */
	public function getSettings ()
	{
		return parent::getSettings();
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