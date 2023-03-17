<?php
/**
 * Web Payments for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2019 Ether Creative
 */

namespace ether\webpayments\models;

use craft\base\Model;

/**
 * Class Settings
 *
 * @author  Ether Creative
 * @package ether\webpayments\models
 */
class Settings extends Model
{

	// Properties
	// =========================================================================

	/** @var string The gateway to use */
	public $gatewayUid;

	/** @var string Should we request shipping details? */
	public $requestShipping = 'no';

	/** @var string[] Additional details to request */
	public $requestDetails = [];

	/** @var array Request details to Order field IDs */
	public $requestDetailFields = [];

	// Methods
	// =========================================================================

	public function rules (): array
	{
		return [
			[['gatewayUid'], 'required'],
		];
	}

}
