<?php
/**
 * NOTICE OF LICENSE
 *
 *  @author    Kjeld Borch Egevang
 *  @copyright 2015 Quickpay
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *
 *  $Date: 2015/06/03 19:37:07 $
 *  E-mail: helpdesk@quickpay.net
 */

include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/../../init.php');

$id_cart = Tools::getValue('id_cart');
$id_module = Tools::getValue('id_module');
$key = Tools::getValue('key');
for ($i = 0; $i < 10; $i++)
{
	/* Wait for validation */
	$id_order = Order::getOrderByCartId((int)$id_cart);
	if ($id_order)
		break;
	sleep(1);
}
if (!$id_order || !$id_module || !$key)
	Tools::redirect('history.php');
$order = new Order((int)$id_order);
$cookie_var = $GLOBALS['cookie'];
$id_customer = $cookie_var->id_customer;
if (!Validate::isLoadedObject($order) ||
		$order->id_customer != $id_customer)
	Tools::redirect('history.php');

Tools::redirect('order-confirmation.php?id_cart='.$id_cart.
		'&id_module='.$id_module.'&id_order='.$id_order.'&key='.$key);

?>
