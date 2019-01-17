<?php
/**
* NOTICE OF LICENSE
*
*  @author    Kjeld Borch Egevang
*  @copyright 2015 QuickPay
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*
*  $Date: 2019/01/07 06:37:29 $
*  E-mail: helpdesk@quickpay.net
*/

include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/../../init.php');
require_once(dirname(__FILE__).'/quickpay.php');

if (_PS_VERSION_ >= '1.5.0.0') {
    die('Bad version');
}

$json = Tools::file_get_contents('php://input');
/* HTTP_RAW_POST_DATA deprecated since PHP 5.6 */
if (!$json) {
    $json = $GLOBALS['HTTP_RAW_POST_DATA'];
}
$checksum = $_SERVER['HTTP_QUICKPAY_CHECKSUM_SHA256'];

$quickpay = new QuickPay();
$quickpay->validate($json, $checksum);
