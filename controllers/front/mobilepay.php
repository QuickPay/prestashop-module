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

class QuickPayMobilepayModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        $quickpay = new QuickPay();
        $quickpay->mobilePay();
    }
}
