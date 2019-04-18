<?php
/**
 * Web Payments for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2019 Ether Creative
 */

namespace ether\webpayments\migrations;

use craft\db\Migration;

/**
 * Class Install
 *
 * @author  Ether Creative
 * @package ether\webpayments\migrations
 */
class Install extends Migration
{

	public function safeUp ()
	{
		$this->createTable('{{%web_payments}}', [
			'orderId'     => $this->integer()->notNull(),
			'dateCreated' => $this->dateTime()->notNull(),
			'dateUpdated' => $this->dateTime()->notNull(),
			'uid'         => $this->uid()->notNull(),
		]);

		$this->addPrimaryKey(
			null,
			'{{%web_payments}}',
			'orderId'
		);

		$this->addForeignKey(
			null,
			'{{%web_payments}}',
			'orderId',
			'{{%commerce_orders}}',
			'id',
			'CASCADE',
			null
		);

		return true;
	}

	public function safeDown ()
	{
		$this->dropTableIfExists('{{%web_payments}}');

		return true;
	}

}