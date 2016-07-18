<?php
/**
 * NOTICE OF LICENSE
 *
 *  @author    Kjeld Borch Egevang
 *  @copyright 2015 Quickpay
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *
 *  $Date: 2016/06/25 06:44:19 $
 *  E-mail: helpdesk@quickpay.net
 */

/**
 * @since 1.5.0
 */
class QuickPayValidationModuleFrontController extends ModuleFrontController
{
	public function postProcess()
	{
		$json = Tools::file_get_contents('php://input');
		if (!$json)
			$json = $HTTP_RAW_POST_DATA;
		$checksum = $_SERVER['HTTP_QUICKPAY_CHECKSUM_SHA256'];

		$quickpay = new Quickpay();
		$quickpay->validate($json, $checksum);
	}
}
