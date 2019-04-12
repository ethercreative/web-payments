<?php
/**
 * Web Payments for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2019 Ether Creative
 */

namespace ether\webpayments\web;

use Craft;
use craft\web\View;
use ether\webpayments\web\assets\WebPaymentsAsset;
use Twig\Markup;
use yii\base\InvalidConfigException;

/**
 * Class Variable
 *
 * @author  Ether Creative
 * @package ether\webpayments\web
 */
class Variable
{

	/**
	 * Render the payment button
	 *
	 * @param array $options
	 *
	 * @return string
	 * @throws InvalidConfigException
	 */
	public function button (array $options = [])
	{
		$view = Craft::$app->getView();

		$id = uniqid('cwp_');

		// TODO: Make default options configurable in plugin settings
		$options = array_merge([
			'id' => $id,
			'cart' => null, // TODO: If commerce is installed default to active cart
			'paymentMethods' => [],
			'shippingMethods' => null, // TODO: if commerce installed and cart is Order, default to available shipping methods
			'requestDetails' => [],
			'requestShipping' => false,
		], $options);

		// TODO: if $options['cart'] instanceof Order convert to WebPayments cart
		// TODO: if paymentMethod is commerce auto-populate cards opt if not set

		$view->registerAssetBundle(WebPaymentsAsset::class);
		$view->registerJs(
			'new CraftWebPayments(' . json_encode($options) . ');',
			View::POS_END
		);

		return new Markup('<span id="' . $id . '"></span>', 'utf8');
	}

}