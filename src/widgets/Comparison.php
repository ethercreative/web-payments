<?php
/**
 * Web Payments for Craft CMS
 *
 * @link      https://ethercreative.co.uk
 * @copyright Copyright (c) 2019 Ether Creative
 */

namespace ether\webpayments\widgets;

use Craft;
use craft\base\Widget;
use craft\db\Query;
use craft\helpers\FileHelper;
use ether\webpayments\WebPayments;
use Twig\Markup;
use yii\base\ErrorException;
use yii\base\Exception;
use yii\helpers\Markdown;

/**
 * Class Comparison
 *
 * @author  Ether Creative
 * @package ether\webpayments\widgets
 */
class Comparison extends Widget
{

	// Properties
	// =========================================================================

	private static $_total;

	// Methods
	// =========================================================================

	public static function displayName (): string
	{
		return WebPayments::t('Web Payment Comparison');
	}

	/**
	 * @return mixed|string|null
	 * @throws Exception
	 * @throws ErrorException
	 */
	public static function iconPath ()
	{
		$path = Craft::$app->path->getStoragePath() . "/web-payments/comparison.svg";

		FileHelper::writeToFile(
			$path,
			str_replace(
				'#b9bfc6\'',
				'#b9bfc6\' style=\'fill:#b9bfc6!important\'',
				self::_pie('#8f98a3', '#b9bfc6')
			)
		);

		return $path;
	}

	public function getBodyHtml ()
	{
		$wpFill = '#0d78f2';
		$rcFill = '#94bce9';

		$mk = self::_pie($wpFill, $rcFill);

		$wpTotal = self::$_total * self::_getPercentage();
		$rcTotal = self::$_total - $wpTotal;

		$mk .= '<p>';
		$mk .= WebPayments::t(
			Markdown::processParagraph(
				'Number of **{wp} Web Payments ({wpCount})** vs **{rc} Regular Checkouts ({rcCount})**'
			),
			[
				'wp' => '<span style="display:inline-block;background-color:'.$wpFill.';width:10px;height:10px;border-radius:100%"></span>',
				'rc' => '<span style="display:inline-block;background-color:'.$rcFill.';width:10px;height:10px;border-radius:100%"></span>',
				'wpCount' => $wpTotal,
				'rcCount' => $rcTotal,
			]
		);
		$mk .= '</p>';

		return new Markup($mk, 'utf8');
	}

	// Helpers
	// =========================================================================

	private static function _getPercentage ()
	{
		static $p;

		if ($p)
			return $p;

		$total = self::$_total = (new Query())
			->from('{{%commerce_orders}} orders')
			->leftJoin(
				'{{%elements}} elements',
				'[[orders.id]] = [[elements.id]]'
			)
			->where([
				'orders.isCompleted'   => '1',
				'elements.dateDeleted' => null,
			])
			->count();

		$wp = (new Query())
			->from('{{%web_payments}} wp')
			->leftJoin(
				'{{%elements}} elements',
				'[[wp.orderId]] = [[elements.id]]'
			)
			->where(['elements.dateDeleted' => null])
			->count();

		return $p = $wp / $total;
	}

	private static function _pie ($wpFill, $rcFill)
	{
		$p = self::_getPercentage();

		/** @noinspection HtmlUnknownAttribute */
		$mk = '<svg viewBox=\'-1 -1 2 2\' style=\'transform:rotate(-90deg)\'>';
		$mk .= self::_slice(0, $p, $wpFill);
		$mk .= self::_slice($p, 1, $rcFill);
		$mk .= '</svg>';

		return $mk;
	}

	private static function _slice ($s, $e, $fill)
	{
		list($sx, $sy) = self::_percentToCoords($s);
		list($ex, $ey) = self::_percentToCoords($e);
		$l = $e > 0.5 ? 1 : 0;

		return "<path d='M $sx $sy A 1 1 0 $l 1 $ex $ey L 0 0' fill='$fill' />";
	}

	private static function _percentToCoords ($percent)
	{
		return [
			cos(2 * pi() * $percent),
			sin(2 * pi() * $percent),
		];
	}

}