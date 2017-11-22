<?php
/**
 * NOTICE OF LICENSE
 *
 *  @author    Kjeld Borch Egevang
 *  @copyright 2015 Quickpay
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *
 *  $Date: 2017/11/22 04:40:06 $
 *  E-mail: helpdesk@quickpay.net
 */

/**
 * @since 1.5.0
 */
class QuickPayCompleteModuleFrontController extends ModuleFrontController
{
    public function __construct()
    {
        parent::__construct();
    }

    public function initContent()
    {
        parent::initContent();

        $id_cart = (int)Tools::getValue('id_cart');
        $id_module = (int)Tools::getValue('id_module');
        $key = Tools::getValue('key');
        $quickpay = new Quickpay();
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
        if (!$id_order || !$id_module || !$key) {
            Tools::redirect('history.php');
        }
        $order = new Order((int)$id_order);
        $customer = new Customer($order->id_customer);
        if (!Validate::isLoadedObject($order) ||
                $customer->secure_key != $key) {
            Tools::redirect('history.php');
        }
        Tools::redirect(
            'index.php?controller=order-confirmation&id_cart='.$id_cart.
            '&id_module='.$id_module.
            '&id_order='.$id_order.
            '&key='.$key
        );
    }
}
