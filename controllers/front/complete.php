<?php
/**
* NOTICE OF LICENSE
*
*  @author    Kjeld Borch Egevang
*  @copyright 2020 QuickPay
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*
*  $Date: 2021/01/05 08:05:42 $
*  E-mail: support@quickpay.net
*/

class QuickPayCompleteModuleFrontController extends ModuleFrontController
{
    public function init()
    {
        $id_cart = (int)Tools::getValue('id_cart');
        $id_module = (int)Tools::getValue('id_module');
        $key = Tools::getValue('key');
        $key2 = Tools::getValue('key2');
        if (!$id_module || !$key) {
            Tools::redirect('history.php');
        }
        for ($i = 0; $i < 10; $i++) {
            /* Wait for validation */
            $trans = Db::getInstance()->getRow('SELECT *
                FROM '._DB_PREFIX_.'quickpay_execution
                WHERE `id_cart` = '.$id_cart.'
                ORDER BY `id_cart` ASC');
            if ($trans && $trans['accepted']) {
                break;
            }
            sleep(1);
        }
        if ($trans && !$trans['accepted']) {
            $quickpay = new QuickPay();
            $setup = $quickpay->getSetup();
            $json = $quickpay->doCurl('payments/'.$trans['trans_id']);
            $vars = $quickpay->jsonDecode($json);
            $json = Tools::jsonEncode($vars);
            if ($vars->accepted == 1) {
                $checksum = $quickpay->sign($json, $setup->private_key);
                $header = array('Quickpay-checksum-sha256: '.$checksum);
                if (Configuration::get('PS_SHOP_ENABLE')) {
                    $this->doCurl($vars->link->callback_url, $json, $header);
                } else {
                    die('Shop is in maintenance');
                }
            }
        }
        unset($this->context->cookie->id_cart);
        parent::init();
        $id_order = Order::getOrderByCartId((int)$id_cart);
        if (!$id_order) {
            Tools::redirect('history.php');
        }
        $order = new Order((int)$id_order);
        $customer = new Customer($order->id_customer);
        if ($key2) {
            $quickpay = new QuickPay();
            $trans = Db::getInstance()->getRow('SELECT *
                FROM '._DB_PREFIX_.'quickpay_execution
                WHERE `id_cart` = '.$id_cart.'
                ORDER BY `id_cart` ASC');
            $json = $trans['json'];
            $vars = $quickpay->jsonDecode($json);
            $query = parse_url($vars->link->continue_url, PHP_URL_QUERY);
            parse_str($query, $args);
            if ($args['key'] == $key) {
                $key = $customer->secure_key;
            }
            $this->context->cookie->id_customer = $customer->id;
        }
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


    public function doCurl($url, $json, $header)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_exec($ch);
    }
}
