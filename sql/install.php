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

$sql = array();

$sql[] = 'CREATE TABLE IF NOT EXISTS '._DB_PREFIX_.'quickpay_execution (
    `exec_id` INT(6) NOT NULL AUTO_INCREMENT,
    `id_cart` INT(10),
    `trans_id` INT(10),
    `order_id` VARCHAR(25),
    `accepted` INT(1),
    `test_mode` INT(1),
    `json` TEXT,
    PRIMARY KEY(`exec_id`)
) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';


foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}
