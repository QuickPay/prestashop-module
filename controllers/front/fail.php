<?php
/**
 * NOTICE OF LICENSE
 *
 *  @author    Kjeld Borch Egevang
 *  @copyright 2015 QuickPay
 *  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 *
 *  $Date: 2019/01/07 06:37:33 $
 *  E-mail: helpdesk@quickpay.net
 */

/**
 * @since 1.5.0
 */
class QuickPayFailModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();
        Context::getContext()->smarty->assign(
            array(
                'status' => Tools::getValue('status'),
                'shop_name' => Configuration::get('PS_SHOP_NAME'),
            )
        );
        if (_PS_VERSION_ >= '1.7.0.0') {
            $this->setTemplate('module:quickpay/views/templates/front/fail17.tpl');
        } else {
            $this->setTemplate('fail.tpl');
        }
    }
}
