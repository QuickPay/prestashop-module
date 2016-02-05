<?php
/**
 * NOTICE OF LICENSE
 *
 *  @author    Kjeld Borch Egevang
 *  @copyright 2015 Quickpay
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *
 *  $Date: 2016/01/27 07:19:42 $
 *  E-mail: helpdesk@quickpay.net
 */

/**
 * @since 1.5.0
 */
class QuickPayCompleteModuleFrontController extends ModuleFrontController
{
	public function initContent()
	{
		parent::initContent();

		$id_cart = Tools::getValue('id_cart');
		$id_module = Tools::getValue('id_module');
		$key = Tools::getValue('key');
		if (!$id_module || !$key)
			Tools::redirect('history.php');
		for ($i = 0; $i < 10; $i++)
		{
			/* Wait for validation */
			$id_order = Order::getOrderByCartId((int)$id_cart);
			if ($id_order)
				break;
			sleep(1);
		}
		if (!$id_order)
		{
			$trans = Db::getInstance()->getRow('SELECT *
					FROM '._DB_PREFIX_.'quickpay_execution
					WHERE `id_cart` = '.$id_cart.'
					ORDER BY `id_cart` ASC');
			if ($trans)
			{
				$quickpay = new Quickpay();
				$setup = $quickpay->getSetup();
				$json = $quickpay->doCurl('payments/'.$trans['trans_id']);
				$vars = $quickpay->jsonDecode($json);
				$json = Tools::jsonEncode($vars);
				if ($vars->accepted == 1)
				{
					$checksum = $quickpay->sign($json, $setup->private_key);
					$quickpay->validate($json, $checksum, _PS_OS_ERROR_);
				}
			}
			$id_order = Order::getOrderByCartId((int)$id_cart);
		}
		if (!$id_order)
			Tools::redirect('history.php');
		$order = new Order((int)$id_order);
		$context = Context::getContext();
		$id_customer = $context->cookie->id_customer;
		if (!Validate::isLoadedObject($order) ||
				$order->id_customer != $id_customer)
			Tools::redirect('history.php');
		Tools::redirect('index.php?controller=order-confirmation&id_cart='.$id_cart.
				'&id_module='.$id_module.
				'&id_order='.$id_order.
				'&key='.$key);
	}
}
