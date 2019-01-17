<?php
/**
 * NOTICE OF LICENSE
 *
 *  @author    Kjeld Borch Egevang
 *  @copyright 2016 QuickPay
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *
 *  $Date: 2019/01/07 06:37:29 $
 *  E-mail: helpdesk@quickpay.net
 */

include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/../../header.php');
require_once(dirname(__FILE__).'/quickpay.php');

if (_PS_VERSION_ >= '1.5.0.0') {
    die('Bad version');
}

$quickpay = new QuickPay();
$smarty = $quickpay->context->smarty;
$smarty->assign('status', Tools::getValue('status'));
$smarty->display(dirname(__FILE__).'/views/templates/front/fail.tpl');

include(dirname(__FILE__).'/../../footer.php');
