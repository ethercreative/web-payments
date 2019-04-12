<?php
/**
 * Web Payments for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2019 Ether Creative
 */

namespace ether\webpayments\web\assets;

use craft\web\AssetBundle;

/**
 * Class WebPaymentsAsset
 *
 * @author  Ether Creative
 * @package ether\webpayments\web\assets
 */
class WebPaymentsAsset extends AssetBundle
{

	public function init ()
	{
		$this->sourcePath = __DIR__;

		$this->js = [
			'js/web-payments.min.js',
		];

		parent::init();
	}

}