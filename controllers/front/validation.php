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

class QuickPayValidationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $json = Tools::file_get_contents('php://input');
        if (!$json) {
            $json = $GLOBALS['HTTP_RAW_POST_DATA']; // Deprecated since PHP 5.6
        }
        $checksum = $_SERVER['HTTP_QUICKPAY_CHECKSUM_SHA256'];

        $quickpay = new QuickPay();
        $quickpay->validate($json, $checksum, _PS_OS_PAYMENT_, true);
        exit(0);
    }
}
