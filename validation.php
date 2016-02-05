<?php
/**
* NOTICE OF LICENSE
*
*  @author    Kjeld Borch Egevang
*  @copyright 2015 Quickpay
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*
*  $Date: 2016/01/30 07:45:06 $
*  E-mail: helpdesk@quickpay.net
*/

include(dirname(__FILE__).'/../../config/config.inc.php');
include(dirname(__FILE__).'/../../init.php');
require_once(dirname(__FILE__).'/quickpay.php');

if (_PS_VERSION_ >= '1.5.0.0')
	die('Bad version');

$json = Tools::file_get_contents('php://input');
$checksum = $_SERVER['HTTP_QUICKPAY_CHECKSUM_SHA256'];

$quickpay = new Quickpay();
$quickpay->validate($json, $checksum);

?>
