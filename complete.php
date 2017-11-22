<?php
/**
 * NOTICE OF LICENSE
 *
 *  @author    Kjeld Borch Egevang
 *  @copyright 2015 Quickpay
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *
 *  $Date: 2017/11/12 05:14:59 $
 *  E-mail: helpdesk@quickpay.net
 */

include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/../../init.php');
require_once(dirname(__FILE__).'/quickpay.php');

if (_PS_VERSION_ >= '1.5.0.0') {
    die('Bad version');
}

$id_cart = (int)Tools::getValue('id_cart');
$id_module = (int)Tools::getValue('id_module');
$key = Tools::getValue('key');
if (!$id_module || !$key) {
    Tools::redirect('history.php');
}
$quickpay = new Quickpay();
$cookie = $quickpay->context->cookie;
for ($i = 0; $i < 10; $i++) {
    $trans = Db::getInstance()->getRow('SELECT *
        FROM '._DB_PREFIX_.'quickpay_execution
        WHERE `id_cart` = '.$id_cart.'
        ORDER BY `id_cart` ASC');
    if ($trans) {
        $setup = $quickpay->getSetup();
        $json = $quickpay->doCurl('payments/'.$trans['trans_id']);
        $vars = $quickpay->jsonDecode($json);
        $json = Tools::jsonEncode($vars);
        if ($vars->accepted == 1) {
            $checksum = $quickpay->sign($json, $setup->private_key);
            $quickpay->validate($json, $checksum);
        }
    }
    /* Wait for validation */
    $id_order = Order::getOrderByCartId((int)$id_cart);
    if ($id_order) {
        break;
    }
    sleep(1);
}
if (!$id_order) {
    Tools::redirect('history.php');
}
$order = new Order((int)$id_order);
$id_customer = $cookie->id_customer;
if (!$id_customer) {
    $id_guest = $cookie->id_guest;
    $guest = new Guest($id_guest);
    $id_customer = $guest->id_customer;
}
if (!Validate::isLoadedObject($order) ||
        $order->id_customer != $id_customer) {
    Tools::redirect('history.php');
}

Tools::redirect(
    'order-confirmation.php?id_cart='.$id_cart.
    '&id_module='.$id_module.'&id_order='.$id_order.'&key='.$key
);
