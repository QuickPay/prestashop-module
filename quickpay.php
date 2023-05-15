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

class QuickPay extends PaymentModule
{
    private $post_errors = array();
    private $v15 = _PS_VERSION_ >= '1.5.0.0';
    private $v16 = _PS_VERSION_ >= '1.6.0.0';
    private $v17 = _PS_VERSION_ >= '1.7.0.0';
    private $v177 = _PS_VERSION_ >= '1.7.7.0';
    private $hooks;
    private $secure_key;
    private $submitName;
    private $setup_vars;
    private $setup;
    private $qpPreview;

    public function __construct()
    {
        $this->name = 'quickpay';
        $this->tab = 'payments_gateways';
        $this->version = '4.1.14';
        $this->author = 'Kjeld Borch Egevang';
        $this->module_key = 'b99f59b30267e81da96b12a8d1aa5bac';
        $this->need_instance = 0;
        $this->secure_key = Tools::encrypt($this->name);
        $this->bootstrap = true;
        $this->hooks = array(
            'displayPayment' => true,
            'displayHeader' => true,
            'displayLeftColumn' => true,
            'displayFooter' => true,
            'displayAdminOrder' => !$this->v177,
            'displayAdminOrderSide' => $this->v177,
            'displayPaymentReturn' => true,
            'displayPDFInvoice' => true,
            'actionOrderStatusPostUpdate' => true,
            'paymentOptions' => $this->v17,
            'displayExpressCheckout' => $this->v17,
            'displayShoppingCart' => !$this->v17
        );

        parent::__construct();

        $this->displayName = 'QuickPay';
        $this->submitName = sprintf('submit%sModule', $this->name);
        $this->description = sprintf($this->l('Payment via %s'), 'QuickPay');
        $this->confirmUninstall =
            $this->l('Are you sure you want to delete your settings?');

        if (!$this->v15) {
            $this->warning = $this->l('This module only works for PrestaShop 1.5, 1.6 and 1.7');
        }
    }

    public function varsObj($setup_var)
    {
        $vars = new StdClass();
        $keys = array(
                'glob_name',
                'var_name',
                'card_text',
                'def_val',
                'card_type_lock');
        $i = 0;
        foreach ($keys as $key) {
            $vars->$key = $setup_var[$i++];
        }
        return $vars;
    }

    public function install()
    {
        include(dirname(__FILE__).'/sql/install.php');

        if (!parent::install()) {
            return false;
        }
        $this->getSetup();
        foreach ($this->setup_vars as $setup_var) {
            $vars = $this->varsObj($setup_var);
            if (!Configuration::updateValue($vars->glob_name, $vars->def_val)) {
                return false;
            }
        }
        return $this->registerHooks();
    }

    public function uninstall()
    {
        include(dirname(__FILE__).'/sql/uninstall.php');

        if (!parent::uninstall()) {
            return false;
        }
        $this->getSetup();
        foreach ($this->setup_vars as $setup_var) {
            $vars = $this->varsObj($setup_var);
            if (!Configuration::deleteByName($vars->glob_name)) {
                return false;
            }
        }
        return Configuration::deleteByName('_QUICKPAY_OVERLAY_CODE') &&
            Configuration::deleteByName('_QUICKPAY_ORDERING') &&
            Configuration::deleteByName('_QUICKPAY_HIDE_IMAGES');
    }

    public function registerHooks()
    {
        foreach ($this->hooks as $name => $condition) {
            if ($condition && !$this->isRegisteredInHook($name)) {
                if (!$this->registerHook($name)) {
                    return false;
                }
            }
        }
        return true;
    }

    public function getConf($name)
    {
        if (isset($this->orderShopId)) {
            $conf = Configuration::get($name, null, null, $this->orderShopId);
        } else {
            $conf = Configuration::get($name);
        }
        return $conf;
    }

    public function getSetup()
    {
        $this->setup_vars = array(
            array('_QUICKPAY_MERCHANT_ID', 'merchant_id',
                sprintf($this->l('%s merchant ID'), 'QuickPay'), '', ''),
            array('_QUICKPAY_PRIVATE_KEY', 'private_key',
                sprintf($this->l('%s private key'), 'QuickPay'), '', ''),
            array('_QUICKPAY_USER_KEY', 'user_key',
                sprintf($this->l('%s user key'), 'QuickPay'), '', ''),
            array('_QUICKPAY_ORDER_PREFIX', 'orderprefix',
                $this->l('Order prefix'), '000', ''),
            array('_QUICKPAY_GA_CLIENT_ID', 'ga_client_id',
                $this->l('Google analytics client ID'), '', ''),
            array('_QUICKPAY_GA_TRACKING_ID', 'ga_tracking_id',
                $this->l('Google analytics tracking ID'), '', ''),
            array('_QUICKPAY_STATEMENT_TEXT', 'statementtext',
                $this->l('Text on statement'), '', ''),
            array('_QUICKPAY_TESTMODE', 'testmode',
                $this->l('Accept test payments'), 0, ''),
            array('_QUICKPAY_COMBINE', 'combine',
                $this->l('Creditcards combined window'), 0, ''),
            array('_QUICKPAY_AUTOGET', 'autoget',
                sprintf($this->l('Cards in payment window controlled by %s'), 'QuickPay'), 0, ''),
            array('_QUICKPAY_MOBILEPAY_CHECKOUT', 'mobilepaycheckout',
                $this->l('Add button for MobilePay Checkout'), 0, ''),
            array('_QUICKPAY_AUTOFEE', 'autofee',
                $this->l('Customer pays the card fee'), 0, ''),
            array('_QUICKPAY_API', 'api',
                $this->l('Activate API'), 1, ''),
            array('_QUICKPAY_SHOWCARDS', 'showcards',
                $this->l('Show card logos in left column'), 1, ''),
            array('_QUICKPAY_SHOWCARDSFOOTER', 'showcardsfooter',
                $this->l('Show card logos in footer'), 1, ''),
            array('_QUICKPAY_AUTOCAPTURE', 'autocapture',
                $this->l('Auto-capture payments'), 0, ''),
            array('_QUICKPAY_STATECAPTURE', 'statecapture',
                $this->l('Capture payments in state'), 0, ''),
            array('_QUICKPAY_BRANDING', 'branding',
                $this->l('Branding in payment window'), 0, ''),
            array('_QUICKPAY_FEE_TAX', 'feetax',
                $this->l('Tax for card fee'), 0, ''),
            array('_QUICKPAY_CMS_ID', 'cmsid',
                $this->l('Info page for MobilePay Checkout'), 0, ''),
            array('_QUICKPAY_VIABILL', 'viabill',
                $this->l('ViaBill - buy now, pay whenever you want'), 0, 'viabill'),
            array('_QUICKPAY_DK', 'dk',
                $this->l('Dankort'), 0, 'dankort'),
            array('_QUICKPAY_VISA', 'visa',
                $this->l('Visa card'), 0, 'visa,visa-dk'),
            array('_QUICKPAY_VELECTRON', 'visaelectron',
                $this->l('Visa Electron'), 0, 'visa-electron,visa-electron-dk'),
            array('_QUICKPAY_MASTERCARD', 'mastercard',
                $this->l('MasterCard'), 0, 'mastercard,mastercard-dk'),
            array('_QUICKPAY_MASTERCARDDEBET', 'mastercarddebet',
                $this->l('MasterCard Debet'), 0, 'mastercard-debet,mastercard-debet-dk'),
            array('_QUICKPAY_A_EXPRESS', 'express',
                $this->l('American Express'), 0, 'american-express,american-express-dk'),
            array('_QUICKPAY_MOBILEPAY', 'mobilepay',
                $this->l('MobilePay'), 0, 'mobilepay'),
            array('_QUICKPAY_SWISH', 'swish',
                $this->l('Swish'), 0, 'swish'),
            array('_QUICKPAY_KLARNA', 'klarna',
                $this->l('Klarna'), 0, 'klarna-payments'),
            array('_QUICKPAY_FORBRUGS_1886', 'f1886',
                $this->l('Forbrugsforeningen af 1886'), 0, 'fbg1886'),
            array('_QUICKPAY_DINERS', 'diners',
                $this->l('Diners Club'), 0, 'diners,diners-dk'),
            array('_QUICKPAY_JCB', 'jcb',
                $this->l('JCB'), 0, 'jcb'),
            array('_QUICKPAY_DK_3D', 'dk_3d',
                $this->l('Dankort (3D)'), 0, '3d-dankort'),
            array('_QUICKPAY_VISA_3D', 'visa_3d',
                $this->l('Visa card (3D)'), 0, '3d-visa,3d-visa-dk'),
            array('_QUICKPAY_VELECTRON_3D', 'visaelectron_3d',
                $this->l('Visa Electron (3D)'), 0, '3d-visa-electron,3d-visa-electron-dk'),
            array('_QUICKPAY_MASTERCARD_3D', 'mastercard_3d',
                $this->l('MasterCard (3D)'), 0, '3d-mastercard,3d-mastercard-dk'),
            array('_QUICKPAY_MASTERCARDDEBET_3D', 'mastercarddebet_3d',
                $this->l('MasterCard Debet (3D)'), 0, '3d-mastercard-debet,3d-mastercard-debet-dk'),
            array('_QUICKPAY_MAESTRO_3D', 'maestro_3d',
                $this->l('Maestro (3D)'), 0, '3d-maestro,3d-maestro-dk'),
            array('_QUICKPAY_JCB_3D', 'jcb_3d',
                $this->l('JCB (3D)'), 0, '3d-jcb'),
            array('_QUICKPAY_PAYPAL', 'paypal',
                $this->l('PayPal'), 0, 'paypal'),
            array('_QUICKPAY_SOFORT', 'sofort',
                $this->l('Sofort'), 0, 'sofort'),
            array('_QUICKPAY_APPLEPAY', 'applepay',
                $this->l('Apple Pay'), 0, 'apple-pay'),
            array('_QUICKPAY_GOOGLE_PAY', 'googlepay',
                $this->l('Google Pay'), 0, 'google-pay'),
            array('_QUICKPAY_BITCOIN', 'bitcoin',
                $this->l('Bitcoin'), 0, 'bitcoin'),
            array('_QUICKPAY_VIPPS', 'vipps',
                $this->l('Vipps'), 0, 'vipps,vippspsp'),
            array('_QUICKPAY_RESURS', 'resurs',
                $this->l('Resurs Bank'), 0, 'resurs'),
            array('_QUICKPAY_ANYDAYSPLIT', 'anydaysplit',
                $this->l('ANYDAY Split'), 0, 'anyday-split')
        );
        $this->setup = new StdClass();
        $this->setup->lock_names = array();
        $this->setup->card_type_locks = array();
        $this->setup->card_texts = array();
        $this->setup->credit_cards = array();
        $credit_cards = array(
            'dk',
            'visa',
            'visaelectron',
            'express',
            'f1886',
            'mastercard',
            'mastercarddebet',
            'maestro',
            'diners',
            'jcb'
        );
        $credit_cards2d = array(
            'visa_3d' => 'visa',
            'visaelectron_3d' => 'visaelectron',
            'mastercard_3d' => 'mastercard',
            'mastercarddebet_3d' => 'mastercarddebet',
            'maestro_3d' => 'maestro',
            'jcb_3d' => 'jcb'
        );
        $this->setup->secure_list = array(
            'visa' => 'visa_secure',
            'visaelectron' => 'visa_secure',
            'visa_3d' => 'visa_secure',
            'visaelectron_3d' => 'visa_secure',
            'mastercard' => 'mastercard_secure',
            'maestro' => 'mastercard_secure',
            'mastercarddebet' => 'mastercard_secure',
            'mastercard_3d' => 'mastercard_secure',
            'maestro_3d' => 'mastercard_secure',
            'mastercarddebet_3d' => 'mastercard_secure'
        );
        $all_cards = array_merge($credit_cards, array_keys($credit_cards2d));
        $this->setup->auto_ignore = array_keys($credit_cards2d);
        array_pop($this->setup->auto_ignore);
        $setup_vars = $this->sortSetup();
        $autoget = $this->getConf('_QUICKPAY_AUTOGET');
        $combine = $this->getConf('_QUICKPAY_COMBINE');
        foreach ($setup_vars as $setup_var) {
            $vars = $this->varsObj($setup_var);
            $field = $vars->var_name;
            $this->setup->$field = $this->getConf($vars->glob_name);
            $this->setup->card_texts[$vars->var_name] = $vars->card_text;
            if ($field == 'maestro_3d') {
                $this->setup->card_texts['maestro'] = $this->l('Maestro');
            }
            elseif ($field == 'statecapture') {
                // Backwards compatibility
                $capture_state2 = Configuration::get('_QUICKPAY_STATECAPTURE2');
                $capture_state3 = Configuration::get('_QUICKPAY_STATECAPTURE3');
                if ($capture_state2 || $capture_state3) {
                    $ids = array();
                    if ($this->setup->$field) {
                        $ids[] = $this->setup->$field;
                    }
                    if ($capture_state2) {
                        $ids[] = $capture_state2;
                    }
                    if ($capture_state3) {
                        $ids[] = $capture_state3;
                    }
                    $this->setup->$field = implode(',', $ids);
                    Configuration::updateValue($vars->glob_name, $this->setup->$field);
                    Configuration::updateValue('_QUICKPAY_STATECAPTURE2', 0);
                    Configuration::updateValue('_QUICKPAY_STATECAPTURE3', 0);
                }
            }
            if (in_array($vars->var_name, $all_cards)) {
                if ($autoget && $combine) {
                    if (in_array($vars->var_name, $this->setup->auto_ignore)) {
                        $this->setup->$field = 0;
                    } else {
                        $this->setup->$field = 2;
                    }
                }
                if ($this->setup->$field) {
                    $this->setup->credit_cards[$vars->var_name] = $vars->card_text;
                    $card_type_locks = explode(',', $vars->card_type_lock);
                    foreach ($card_type_locks as $name) {
                        $this->setup->card_type_locks[] = $name;
                        $this->setup->lock_names[$name] = $vars->var_name;
                    }
                }
            }
        }
        if ($this->setup->mobilepaycheckout) {
            $this->setup->mobilepay = 1;
        }
        // $this->dump($this->setup->card_type_locks);
        return $this->setup;
    }

    public function sortSetup()
    {
        $ordering = Configuration::get('_QUICKPAY_ORDERING');
        if ($ordering) {
            $ordering_list = explode(',', $ordering);
        } else {
            $ordering_list = array();
        }
        $setup_dict = array();
        foreach ($this->setup_vars as $setup_var) {
            $vars = $this->varsObj($setup_var);
            $setup_dict[$vars->var_name] = $setup_var;
        }
        $setup_vars = array();
        foreach ($ordering_list as $vars->var_name) {
            if (isset($setup_dict[$vars->var_name])) {
                $setup_vars[$vars->var_name] = $setup_dict[$vars->var_name];
            }
        }
        foreach ($setup_dict as $vars->var_name => $setup_var) {
            if (empty($setup_vars[$vars->var_name])) {
                $setup_vars[$vars->var_name] = $setup_var;
            }
        }
        return $setup_vars;
    }

    public function imagesSetup()
    {
        $hide_images = Configuration::get('_QUICKPAY_HIDE_IMAGES');
        if ($hide_images !== false) {
            $hide_images_list = explode(',', $hide_images);
        } else {
            $hide_images_list = array(
                'visa_secure',
                'visaelectron_secure',
                'mastercard_secure',
                'maestro_secure',
                'mastercarddebet_secure'
            );
        }
        $key_list = array();
        foreach ($hide_images_list as $hide_image) {
            $key_list[$hide_image] = $hide_image;
        }
        return $key_list;
    }

    public function getPageLink($name, $parm)
    {
        $url = $this->context->link->getPageLink($name, true, null, $parm);
        return $url;
    }

    public function getModuleLink($name, $parms = array())
    {
        $url = $this->context->link->getModuleLink(
            $this->name,
            $name,
            $parms,
            true
        );
        return $url;
    }

    public function addLog($message, $severity = 1, $error_code = null, $object_type = null, $object_id = null)
    {
        if ($this->v16) {
            PrestaShopLogger::addLog($message, $severity, $error_code, $object_type, $object_id);
        } else {
            Logger::addLog($message, $severity, $error_code, $object_type, $object_id);
        }
    }

    public function dump($var, $name = null)
    {
        print '<pre>';
        if ($name) {
            print "$name:\n";
        }
        print_r($var);
        print '</pre>';
    }

    public function changeState()
    {
        $target = Tools::getValue('target');
        $this->getSetup();
        $hide_images_list = $this->imagesSetup();
        foreach ($this->setup_vars as $setup_var) {
            $vars = $this->varsObj($setup_var);
            // Toggle value
            if ($target == $vars->var_name.'_on') {
                Configuration::updateValue($vars->glob_name, 0);
            }
            if ($target == $vars->var_name.'_off') {
                Configuration::updateValue($vars->glob_name, 1);
            }
            if ($target == $vars->var_name.'_hide') {
                unset($hide_images_list[$vars->var_name]);
            }
            if ($target == $vars->var_name.'_disp') {
                $hide_images_list[$vars->var_name] = $vars->var_name;
            }
            if ($target == $vars->var_name.'_hide_sec') {
                unset($hide_images_list[$vars->var_name.'_secure']);
            }
            if ($target == $vars->var_name.'_disp_sec') {
                $hide_images_list[$vars->var_name.'_secure'] = $vars->var_name.'_secure';
            }
        }
        Configuration::updateValue('_QUICKPAY_HIDE_IMAGES', implode(',', $hide_images_list));
    }

    public function updateCardsPosition()
    {
        $cards = Tools::getValue('cards');
        if ($cards) {
            Configuration::updateValue('_QUICKPAY_ORDERING', implode(',', $cards));
        }
    }

    /**
     * Load the configuration form
     */
    public function getContent()
    {
        if (isset($this->warning)) {
            return $this->displayError($this->warning);
        }

        $this->context->controller->addCSS($this->_path.'/views/css/front.css');

        $output = '';
        $row = Db::getInstance()->ExecuteS(
            'SHOW TABLES LIKE "'._DB_PREFIX_.'quickpay_execution"'
        );
        if (!$row) {
            // Not installed properly
            $this->install();
        }
        $this->getSetup();
        $this->postProcess();
        $this->registerHooks();
        $this->context->controller->addJqueryUI('ui.sortable');
        $output .= $this->displayErrors();
        if (Tools::getValue($this->submitName) && !$this->post_errors) {
            $output .= '
                <div class="alert alert-success conf confirm">
                '.$this->l('Settings updated').'
                </div>';
        }

        $this->context->smarty->assign('module_dir', $this->_path);
        $this->context->smarty->clearCompiledTemplate();

        $output .= $this->context->smarty->fetch(
            $this->local_path.'views/templates/admin/configure.tpl'
        );

        $output .= $this->renderForm();
        $output .= $this->previewPayment();
        $output .= $this->renderList();
        return $output;
    }

    protected function renderForm()
    {
        $helper = new HelperForm();

        if (empty($helper->context)) {
            $helper->context = $this->context;
            $helper->local_path = $this->local_path;
        }
        $helper->show_toolbar = false;
        $helper->table = $this->table;
        $helper->module = $this;
        $helper->default_form_language = $this->context->language->id;
        $helper->allow_employee_form_lang =
            Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
        if (!$helper->allow_employee_form_lang) {
            // For old 1.5 helper
            $helper->allow_employee_form_lang = 0;
        }

        $helper->identifier = $this->identifier;
        $helper->submit_action = $this->submitName;
        $helper->currentIndex =
            $this->context->link->getAdminLink('AdminModules', false).
            '&configure='.$this->name.
            '&tab_module='.$this->tab.
            '&module_name='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->tpl_vars = array(
            'fields_value' => $this->getConfigFormValues(),
            'languages' => $this->context->controller->getLanguages(),
            'id_language' => $this->context->language->id,
        );

        $out = $helper->generateForm(array($this->getConfigSettings()));
        if (!$this->v16) {
            $out .= '<br />';
        }
        return $out;
    }

    protected function renderList()
    {
        $setup = $this->setup;
        $setup_vars = $this->sortSetup();
        $hide_images_list = $this->imagesSetup();
        $cards = array();
        foreach ($setup_vars as $setup_var) {
            $vars = $this->varsObj($setup_var);
            $field = $vars->var_name;
            if ($setup->autoget && in_array($vars->var_name, $setup->auto_ignore)) {
                continue;
            }
            if (!$vars->card_type_lock) {
                continue;
            }
            $card = array();
            $card['name'] = $vars->var_name;
            $card['image'] = $vars->var_name.'.png';
            if (isset($setup->secure_list[$vars->var_name])) {
                $card['image_secure'] = $setup->secure_list[$vars->var_name].'.png';
            } else {
                $card['image_secure'] = '';
            }
            $card['display'] = empty($hide_images_list[$vars->var_name]);
            $card['display_secure'] = empty($hide_images_list[$vars->var_name.'_secure']);
            $card['title'] = $vars->card_text;
            $card['status'] = $setup->$field;
            $cards[] = $card;
        }
        $change_url = $_SERVER['REQUEST_URI'];
        $this->context->smarty->assign(
            array(
                'link' => $this->context->link,
                'cards' => $cards,
                'change_url' => $change_url,
                'secure_key' => $this->secure_key,
                'image_baseurl' => $this->_path.'views/img/'
            )
        );

        if ($this->v16) {
            return $this->display(__FILE__, 'list.tpl');
        } else {
            return $this->display(__FILE__, 'list15.tpl');
        }
    }

    protected function useCheckBox($vars)
    {
        return $vars->def_val !== '' &&
            $vars->var_name != 'orderprefix' &&
            $vars->var_name != 'statecapture' &&
            $vars->var_name != 'branding' &&
            $vars->var_name != 'feetax' &&
            $vars->var_name != 'cmsid';
    }

    protected function getConfigInput15($vars)
    {
        if ($this->useCheckBox($vars)) {
            $input = array(
                'type' => 'select',
                'name' => $vars->glob_name,
                'label' => $vars->card_text,
                'options' => array(
                    'query' =>  array(
                        array(
                            'id' => '0',
                            'name' => $this->l('No')
                        ),
                        array(
                            'id' => '1',
                            'name' => $this->l('Yes')
                        )
                    ),
                    'id' => 'id',
                    'name' => 'name'
                )
            );
        } else {
            $input = array(
                'size' => strpos($vars->var_name, '_key') === false ? 10 : 60,
                'type' => 'text',
                'name' => $vars->glob_name,
                'label' => $vars->card_text
            );
        }
        return $input;
    }

    protected function getConfigInput($vars)
    {
        if (!$this->v16) {
            return $this->getConfigInput15($vars);
        }
        if ($this->useCheckBox($vars)) {
            $input = array(
                'type' => 'switch',
                'name' => $vars->glob_name,
                'label' => $vars->card_text,
                'values' => array(
                    array(
                        'id' => 'on',
                        'value' => '1',
                        'label' => $this->l('Yes'),
                    ),
                    array(
                        'id' => 'off',
                        'value' => '0',
                        'label' => $this->l('No'),
                    )
                )
            );
        } else {
            $input = array(
                'col' => strpos($vars->var_name, '_key') === false ? 3 : 6,
                'type' => 'text',
                'name' => $vars->glob_name,
                'label' => $vars->card_text
            );
        }
        if ($vars->var_name == 'statementtext') {
            $input['desc'] = $this->l('Max. 22 ASCII characters. Only for Clearhaus.');
        }
        return $input;
    }

    protected function getStatesInput($vars)
    {
        $order_states = OrderState::getOrderStates($this->context->language->id);
        $query = array();
        foreach ($order_states as $order_state) {
            $query[] = array(
                'id' => $order_state['id_order_state'],
                'name' => $order_state['name']
            );
        }
        $input = array(
            'type' => 'checkbox',
            'name' => $vars->glob_name,
            'label' => $vars->card_text,
            'values' => array(
                'query' => $query,
                'id' => 'id',
                'name' => 'name'
            ),
            'expand' => array(
                'print_total' => count($query),
                'default' => 'show',
                'show' => array('text' => $this->l('show'), 'icon' => 'plus-sign-alt'),
                'hide' => array('text' => $this->l('hide'), 'icon' => 'minus-sign-alt')
            ),
        );
        return $input;
    }

    protected function getBrandingInput($vars)
    {
        $brandings = $this->getBrandings();
        if ($brandings === false) {
            $brandings = array();
        }
        $query = array();
        $query[] = array('id' => 0,  'name' => $this->l('Standard'));
        foreach ($brandings as $id => $name) {
            $query[] = array(
                'id' => $id,
                'name' => $name
            );
        }
        $input = array(
            'type' => 'select',
            'name' => $vars->glob_name,
            'label' => $vars->card_text,
            'options' => array(
                'query' =>  $query,
                'id' => 'id',
                'name' => 'name'
            )
        );
        return $input;
    }

    protected function getFeeTaxInput($vars)
    {
        $taxes = array(0 => $this->l('None'));
        $rows = TaxRulesGroup::getTaxRulesGroups();
        foreach ($rows as $row) {
            $taxes[$row['id_tax_rules_group']] = $row['name'];
        }
        $query = array();
        foreach ($taxes as $id => $name) {
            $query[] = array(
                'id' => $id,
                'name' => $name
            );
        }
        $input = array(
            'type' => 'select',
            'name' => $vars->glob_name,
            'label' => $vars->card_text,
            'options' => array(
                'query' => $query,
                'id' => 'id',
                'name' => 'name'
            )
        );
        return $input;
    }

    protected function getCmsIdInput($vars)
    {
        $query = array();
        $query[] = array('id' => 0,  'name' => $this->l('None'));
        $cmsPages = CMS::getCMSPages($this->context->language->id);
        foreach ($cmsPages as $cmsPage) {
            $query[] = array(
                'id' => $cmsPage['id_cms'],
                'name' => $cmsPage['meta_title']
            );
        }
        $input = array(
            'type' => 'select',
            'name' => $vars->glob_name,
            'label' => $vars->card_text,
            'options' => array(
                'query' =>  $query,
                'id' => 'id',
                'name' => 'name'
            )
        );
        return $input;
    }

    protected function getConfigSettings()
    {
        $inputs = array();
        foreach ($this->setup_vars as $setup_var) {
            $vars = $this->varsObj($setup_var);
            if ($vars->card_type_lock) {
                continue;
            }
            if ($vars->var_name == 'statecapture') {
                $inputs[] = $this->getStatesInput($vars);
                continue;
            }
            if ($vars->var_name == 'branding') {
                $inputs[] = $this->getBrandingInput($vars);
                continue;
            }
            if ($vars->var_name == 'feetax') {
                $inputs[] = $this->getFeeTaxInput($vars);
                continue;
            }
            if ($vars->var_name == 'cmsid') {
                $inputs[] = $this->getCmsIdInput($vars);
                continue;
            }
            $inputs[] = $this->getConfigInput($vars);
        }

        $submit = array(
            'title' => $this->l('Save'),
        );
        $form = array(
            'legend' => array(
                'title' => $this->l('Settings'),
                'icon' => 'icon-cogs',
            ),
            'input' => $inputs,
            'submit' => $submit,
        );
        return array('form' => $form);
    }

    protected function getConfigFormValues()
    {
        $setup = $this->setup;
        $values = array();
        foreach ($this->setup_vars as $setup_var) {
            $vars = $this->varsObj($setup_var);
            $field = $vars->var_name;
            if ($this->useCheckBox($vars)) {
                $values[$vars->glob_name] = $setup->$field ? 1 : 0;
            } elseif ($vars->var_name == 'statecapture') {
                $ids = explode(',', $setup->$field);
                foreach ($ids as $id) {
                    $values[$vars->glob_name.'_'.$id] = true;
                }
            } else {
                $values[$vars->glob_name] = $setup->$field;
            }
        }
        return $values;
    }

    public function displayErrors()
    {
        $out = '';
        foreach ($this->post_errors as $err) {
            $out .= $this->displayError($err);
        }
        return $out;
    }

    protected function postProcess()
    {
        if (Tools::getValue('action') == 'changeState') {
            $this->changeState();
            exit;
        }
        if (Tools::getValue('action') == 'previewPayment') {
            print $this->previewPayment();
            exit;
        }
        if (Tools::getValue('action') == 'updateCardsPosition') {
            $this->updateCardsPosition();
            exit;
        }
        if (Tools::getValue($this->submitName)) {
            foreach ($this->setup_vars as $setup_var) {
                $vars = $this->varsObj($setup_var);
                if ($vars->card_type_lock) {
                    continue;
                }
                if ($vars->var_name == 'statecapture') {
                    $values = array();
                    $order_states = OrderState::getOrderStates($this->context->language->id);
                    foreach ($order_states as $order_state) {
                        $id = $order_state['id_order_state'];
                        if (Tools::getValue($vars->glob_name.'_'.$id)) {
                            $values[] = $id;
                        }
                    }
                    Configuration::updateValue($vars->glob_name, implode(',', $values));
                    continue;
                }
                if (Tools::getValue($vars->glob_name, null) !== null) {
                    Configuration::updateValue(
                        $vars->glob_name,
                        Tools::getValue($vars->glob_name)
                    );
                }
            }
            // Read the new setup
            $setup = $this->getSetup();
            if (!$setup->merchant_id) {
                $this->post_errors[] = $this->l('Merchant ID is required.');
            }
            if (Tools::strlen($setup->orderprefix) != 3) {
                $this->post_errors[] =
                    $this->l('Order prefix must be exactly 3 characters long.');
            }
            $txt = Tools::iconv('utf-8', 'ASCII', $setup->statementtext);
            if ($txt != $setup->statementtext) {
                $this->post_errors[] =
                    $this->l('Statement text must be 7-bit ASCII.');
            }
            if (Tools::strlen($txt) > 22) {
                $this->post_errors[] =
                    $this->l('Statement text must not be longer than 22 characters.');
            }
            $data = $this->doCurl('payments', array(), 'POST');
            $vars = $this->jsonDecode($data);
            if ($vars->message == 'Invalid API key') {
                $this->post_errors[] = sprintf(
                    $this->l('Invalid %1$s user key. Check the key at %2$s.'),
                    'QuickPay',
                    '<a href="https://manage.quickpay.net">https://manage.quickpay.net</a>'
                );
            } elseif ($setup->autofee) {
                $fees = $this->getFees(100);
                if (!$fees) {
                    $this->post_errors[] = $this->l('Could not access fees via user key. Check access rights in').
                        ' <a href="https://manage.quickpay.net">https://manage.quickpay.net</a>.';
                }
            }
            $data = $this->doCurl('payments', array('order_id=0'), 'GET');
            $vars = $this->jsonDecode($data);
            if ($vars && Tools::substr($vars->message, 0, 14) == 'Not authorized') {
                $this->post_errors[] = $this->l('Could not access payments via user key. Check access rights in').
                    ' <a href="https://manage.quickpay.net">https://manage.quickpay.net</a>.';
            }
        }
    }

    public function jsonDecode($data)
    {
        return json_decode($data);
    }

    public function getCurlHandle($resource, $fields = null, $method = null, $callback_url = null)
    {
        $ch = curl_init();
        $header = array();
        $header[] = 'Authorization: Basic '.
            call_user_func('base64_encode', ':'.$this->setup->user_key);
        $header[] = 'Accept-Version: v10';
        if ($callback_url) {
            $header[] = 'QuickPay-Callback-Url: '.$callback_url;
        }
        $url = 'https://api.quickpay.net/'.$resource;
        if ($method == null) {
            if ($fields) {
                $method = 'POST';
            } else {
                $method = 'GET';
            }
        }
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        if ($fields) {
            curl_setopt($ch, CURLOPT_POST, count($fields));
            curl_setopt($ch, CURLOPT_POSTFIELDS, implode('&', $fields));
        }
        return $ch;
    }

    public function doCurl($resource, $fields = null, $method = null, $callback_url = null)
    {
        $ch = $this->getCurlHandle($resource, $fields, $method, $callback_url);
        $data = curl_exec($ch);
        if (!$data) {
            $this->qp_error = curl_error($ch);
        }
        curl_close($ch);
        return $data;
    }

    public function getFees($amount)
    {
        $setup = $this->setup;
        $data = $this->doCurl('fees/formulas');
        $vars = $this->jsonDecode($data);
        if (!is_array($vars)) {
            return false;
        }
        $fields = array('amount='.$amount);
        $chs = array();
        $mh = curl_multi_init();
        // curl_multi_setopt($mh, CURLMOPT_PIPELINING, 1);
        foreach ($vars as $var) {
            $url = 'fees/'.$var->acquirer.'/'.$var->payment_method;
            $ch = $this->getCurlHandle($url, $fields);
            curl_multi_add_handle($mh, $ch);
            $chs[$var->acquirer][$var->payment_method] = $ch;
        }
        while (true) {
            curl_multi_exec($mh, $running);
            curl_multi_select($mh);
            if ($running == 0) {
                break;
            }
        }
        $fees = array();
        foreach ($vars as $var) {
            $ch = $chs[$var->acquirer][$var->payment_method];
            curl_multi_remove_handle($mh, $ch);
            $data = curl_multi_getcontent($ch);
            $row = $this->jsonDecode($data);
            if (empty($row->payment_method)) {
                continue;
            }
            if (isset($setup->lock_names[$row->payment_method])) {
                $lock_name = $setup->lock_names[$row->payment_method];
            } elseif (isset($setup->lock_names['3d-'.$row->payment_method])) {
                $lock_name = $row->payment_method; // Maestro
            } else {
                continue;
            }
            if (isset($fees[$lock_name])) {
                if ($row->fee < $fees[$lock_name]) {
                    $fees[$lock_name.'_f'] = $fees[$lock_name];
                    $fees[$lock_name.'_3d_f'] = $fees[$lock_name.'_3d'];
                    $fees[$lock_name] = $row->fee;
                    $fees[$lock_name.'_3d'] = $row->fee;
                }
                if ($row->fee > $fees[$lock_name]) {
                    $fees[$lock_name.'_f'] = $row->fee;
                    $fees[$lock_name.'_3d_f'] = $row->fee;
                }
            } else {
                $fees[$lock_name] = $row->fee;
                $fees[$lock_name.'_3d'] = $row->fee;
            }
        }
        curl_multi_close($mh);
        return $fees;
    }

    public function getBrandings()
    {
        $brandings = array();
        $data = $this->doCurl('brandings');
        if (!$data) {
            return false;
        }
        $vars = $this->jsonDecode($data);
        if (!$vars || isset($vars->errors) || isset($vars->message)) {
            return false;
        }
        foreach ($vars as $var) {
            $brandings[$var->id] = $var->name;
        }
        return $brandings;
    }

    public function getOrderingList($setup, $setup_vars)
    {
        $ordering_list = array();
        $hide_images_list = $this->imagesSetup();
        if ($this->v16) {
            $browser = Tools::getUserBrowser();
        } else {
            $browser = '';
        }
        foreach ($setup_vars as $setup_var) {
            $vars = $this->varsObj($setup_var);
            $var_name = $vars->var_name;
            $field = $var_name;
            if (!$vars->card_type_lock || !$setup->$field) {
                continue;
            }
            if ($setup->autoget && in_array($var_name, $setup->auto_ignore)) {
                continue;
            }
            if ($field == 'googlepay' &&
                empty($this->qpPreview) &&
                $browser != 'Google Chrome') {
                continue;
            }
            if ($field == 'applepay' &&
                empty($this->qpPreview) &&
                $browser != 'Apple Safari') {
                continue;
            }
            if (!in_array($var_name, $ordering_list) && empty($hide_images_list[$var_name])) {
                $ordering_list[] = $var_name;
            }
            if (isset($setup->secure_list[$var_name]) && empty($hide_images_list[$var_name.'_secure'])) {
                $ordering_list[] = $setup->secure_list[$var_name];
            }
        }
        return $ordering_list;
    }

    public function getIso3($iso_code)
    {
        $iso3 = array(
            'AF' => 'AFG', 'AX' => 'ALA', 'AL' => 'ALB', 'DZ' => 'DZA',
            'AS' => 'ASM', 'AD' => 'AND', 'AO' => 'AGO', 'AI' => 'AIA',
            'AQ' => 'ATA', 'AG' => 'ATG', 'AR' => 'ARG', 'AM' => 'ARM',
            'AW' => 'ABW', 'AU' => 'AUS', 'AT' => 'AUT', 'AZ' => 'AZE',
            'BS' => 'BHS', 'BH' => 'BHR', 'BD' => 'BGD', 'BB' => 'BRB',
            'BY' => 'BLR', 'BE' => 'BEL', 'BZ' => 'BLZ', 'BJ' => 'BEN',
            'BM' => 'BMU', 'BT' => 'BTN', 'BO' => 'BOL', 'BQ' => 'BES',
            'BA' => 'BIH', 'BW' => 'BWA', 'BV' => 'BVT', 'BR' => 'BRA',
            'IO' => 'IOT', 'BN' => 'BRN', 'BG' => 'BGR', 'BF' => 'BFA',
            'BI' => 'BDI', 'CV' => 'CPV', 'KH' => 'KHM', 'CM' => 'CMR',
            'CA' => 'CAN', 'KY' => 'CYM', 'CF' => 'CAF', 'TD' => 'TCD',
            'CL' => 'CHL', 'CN' => 'CHN', 'CX' => 'CXR', 'CC' => 'CCK',
            'CO' => 'COL', 'KM' => 'COM', 'CG' => 'COG', 'CD' => 'COD',
            'CK' => 'COK', 'CR' => 'CRI', 'CI' => 'CIV', 'HR' => 'HRV',
            'CU' => 'CUB', 'CW' => 'CUW', 'CY' => 'CYP', 'CZ' => 'CZE',
            'DK' => 'DNK', 'DJ' => 'DJI', 'DM' => 'DMA', 'DO' => 'DOM',
            'EC' => 'ECU', 'EG' => 'EGY', 'SV' => 'SLV', 'GQ' => 'GNQ',
            'ER' => 'ERI', 'EE' => 'EST', 'ET' => 'ETH', 'FK' => 'FLK',
            'FO' => 'FRO', 'FJ' => 'FJI', 'FI' => 'FIN', 'FR' => 'FRA',
            'GF' => 'GUF', 'PF' => 'PYF', 'TF' => 'ATF', 'GA' => 'GAB',
            'GM' => 'GMB', 'GE' => 'GEO', 'DE' => 'DEU', 'GH' => 'GHA',
            'GI' => 'GIB', 'GR' => 'GRC', 'GL' => 'GRL', 'GD' => 'GRD',
            'GP' => 'GLP', 'GU' => 'GUM', 'GT' => 'GTM', 'GG' => 'GGY',
            'GN' => 'GIN', 'GW' => 'GNB', 'GY' => 'GUY', 'HT' => 'HTI',
            'HM' => 'HMD', 'VA' => 'VAT', 'HN' => 'HND', 'HK' => 'HKG',
            'HU' => 'HUN', 'IS' => 'ISL', 'IN' => 'IND', 'ID' => 'IDN',
            'IR' => 'IRN', 'IQ' => 'IRQ', 'IE' => 'IRL', 'IM' => 'IMN',
            'IL' => 'ISR', 'IT' => 'ITA', 'JM' => 'JAM', 'JP' => 'JPN',
            'JE' => 'JEY', 'JO' => 'JOR', 'KZ' => 'KAZ', 'KE' => 'KEN',
            'KI' => 'KIR', 'KP' => 'PRK', 'KR' => 'KOR', 'KW' => 'KWT',
            'KG' => 'KGZ', 'LA' => 'LAO', 'LV' => 'LVA', 'LB' => 'LBN',
            'LS' => 'LSO', 'LR' => 'LBR', 'LY' => 'LBY', 'LI' => 'LIE',
            'LT' => 'LTU', 'LU' => 'LUX', 'MO' => 'MAC', 'MK' => 'MKD',
            'MG' => 'MDG', 'MW' => 'MWI', 'MY' => 'MYS', 'MV' => 'MDV',
            'ML' => 'MLI', 'MT' => 'MLT', 'MH' => 'MHL', 'MQ' => 'MTQ',
            'MR' => 'MRT', 'MU' => 'MUS', 'YT' => 'MYT', 'MX' => 'MEX',
            'FM' => 'FSM', 'MD' => 'MDA', 'MC' => 'MCO', 'MN' => 'MNG',
            'ME' => 'MNE', 'MS' => 'MSR', 'MA' => 'MAR', 'MZ' => 'MOZ',
            'MM' => 'MMR', 'NA' => 'NAM', 'NR' => 'NRU', 'NP' => 'NPL',
            'NL' => 'NLD', 'NC' => 'NCL', 'NZ' => 'NZL', 'NI' => 'NIC',
            'NE' => 'NER', 'NG' => 'NGA', 'NU' => 'NIU', 'NF' => 'NFK',
            'MP' => 'MNP', 'NO' => 'NOR', 'OM' => 'OMN', 'PK' => 'PAK',
            'PW' => 'PLW', 'PS' => 'PSE', 'PA' => 'PAN', 'PG' => 'PNG',
            'PY' => 'PRY', 'PE' => 'PER', 'PH' => 'PHL', 'PN' => 'PCN',
            'PL' => 'POL', 'PT' => 'PRT', 'PR' => 'PRI', 'QA' => 'QAT',
            'RE' => 'REU', 'RO' => 'ROU', 'RU' => 'RUS', 'RW' => 'RWA',
            'BL' => 'BLM', 'SH' => 'SHN', 'KN' => 'KNA', 'LC' => 'LCA',
            'MF' => 'MAF', 'PM' => 'SPM', 'VC' => 'VCT', 'WS' => 'WSM',
            'SM' => 'SMR', 'ST' => 'STP', 'SA' => 'SAU', 'SN' => 'SEN',
            'RS' => 'SRB', 'SC' => 'SYC', 'SL' => 'SLE', 'SG' => 'SGP',
            'SX' => 'SXM', 'SK' => 'SVK', 'SI' => 'SVN', 'SB' => 'SLB',
            'SO' => 'SOM', 'ZA' => 'ZAF', 'GS' => 'SGS', 'SS' => 'SSD',
            'ES' => 'ESP', 'LK' => 'LKA', 'SD' => 'SDN', 'SR' => 'SUR',
            'SJ' => 'SJM', 'SZ' => 'SWZ', 'SE' => 'SWE', 'CH' => 'CHE',
            'SY' => 'SYR', 'TW' => 'TWN', 'TJ' => 'TJK', 'TZ' => 'TZA',
            'TH' => 'THA', 'TL' => 'TLS', 'TG' => 'TGO', 'TK' => 'TKL',
            'TO' => 'TON', 'TT' => 'TTO', 'TN' => 'TUN', 'TR' => 'TUR',
            'TM' => 'TKM', 'TC' => 'TCA', 'TV' => 'TUV', 'UG' => 'UGA',
            'UA' => 'UKR', 'AE' => 'ARE', 'GB' => 'GBR', 'UM' => 'UMI',
            'US' => 'USA', 'UY' => 'URY', 'UZ' => 'UZB', 'VU' => 'VUT',
            'VE' => 'VEN', 'VN' => 'VNM', 'VG' => 'VGB', 'VI' => 'VIR',
            'WF' => 'WLF', 'EH' => 'ESH', 'YE' => 'YEM', 'ZM' => 'ZMB',
            'ZW' => 'ZWE',
        );
        if (isset($iso3[$iso_code])) {
            return $iso3[$iso_code];
        } else {
            return $iso_code;
        }
    }

    public function getIso2($iso_code)
    {
        $iso2 = array(
            'DNK' => 'DK', 'FIN' => 'FI', 'GRL' => 'GL'
        );
        if (isset($iso2[$iso_code])) {
            return $iso2[$iso_code];
        } else {
            return $iso_code;
        }
    }

    public function getDecimals($iso_code)
    {
        $iso_code = $this->getIso3($iso_code);
        $decimals = array(
            'BHD' => 3,
            'BIF' => 0,
            'BYR' => 0,
            'CLF' => 4,
            'CLP' => 0,
            'CVE' => 0,
            'DJF' => 0,
            'GNF' => 0,
            'IQD' => 3,
            'ISK' => 0,
            'JOD' => 3,
            'JPY' => 0,
            'KMF' => 0,
            'KRW' => 0,
            'KWD' => 3,
            'LYD' => 3,
            'MGA' => 1,
            'MRO' => 1,
            'OMR' => 3,
            'PYG' => 0,
            'RWF' => 0,
            'TND' => 3,
            'UGX' => 0,
            'UYI' => 0,
            'VND' => 0,
            'VUV' => 0,
            'XAF' => 0,
            'XOF' => 0,
            'XPF' => 0,
        );
        if (isset($decimals[$iso_code])) {
            return $decimals[$iso_code];
        }
        return 2;
    }

    public function fromQpAmount($amount, $currency)
    {
        $decimals = $this->getDecimals($currency->iso_code);
        return $amount / pow(10, $decimals);
    }

    public function toQpAmount($amount, $currency)
    {
        $decimals = $this->getDecimals($currency->iso_code);
        return Tools::ps_round($amount * pow(10, $decimals));
    }

    public function displayQpAmount($amount, $currency)
    {
        $amount = $this->fromQpAmount($amount, $currency);
        return Tools::displayPrice($amount, $currency);
    }

    public function fromUserAmount($amount, $currency)
    {
        $use_comma = strpos(Tools::displayPrice(1.23, $currency), ',') !== false;
        if ($use_comma) {
            $amount = str_replace('.', '', $amount);
            $amount = str_replace(',', '.', $amount);
        } else {
            $amount = str_replace(',', '', $amount);
        }
        return $amount;
    }

    public function toUserAmount($amount, $currency)
    {
        $use_comma = strpos(Tools::displayPrice(1.23, $currency), ',') !== false;
        if ($use_comma) {
            $amount = str_replace('.', ',', $amount);
        }
        return $amount;
    }

    public function hookDisplayHeader()
    {
        if ($this->v16) {
            $this->context->controller->addCSS($this->_path.'/views/css/front.css');
        } else {
            $this->context->controller->addCSS($this->_path.'/views/css/front15.css');
        }
        if ($this->getConf('_QUICKPAY_MOBILEPAY_CHECKOUT')) {
            $this->context->controller->addJqueryUI('ui.dialog');
            $this->context->controller->addJS($this->_path.'views/js/mobilepay.js');
        }
    }

    public function hookPaymentTop()
    {
        $this->hookDisplayHeader();
    }


    public function makePayment($params, &$paymentOptions, $select_option = 0)
    {
        if (function_exists('stream_resolve_include_path') &&
            stream_resolve_include_path(
                _PS_MODULE_DIR_.'quickpay/quickpay.inc.php'
            )
        ) {
            include(_PS_MODULE_DIR_.'quickpay/quickpay.inc.php');
        }
        $setup = $this->getSetup();
        $hide_images_list = $this->imagesSetup();
        $smarty = $this->context->smarty;
        $cart = $params['cart'];
        $invoice_address = new Address((int)$cart->id_address_invoice);
        $invoice_street = $invoice_address->address1;
        if ($invoice_address->address2) {
            $invoice_street .= ' '.$invoice_address->address2;
        }
        $country = new Country($invoice_address->id_country);
        if ($invoice_address->id) {
            $invoice_country_code = $this->getIso3($country->iso_code);
        } else {
            $invoice_country_code = 'DNK';
        }
        $delivery_address = new Address((int)$cart->id_address_delivery);
        $delivery_street = $delivery_address->address1;
        if ($delivery_address->address2) {
            $delivery_street .= ' '.$delivery_address->address2;
        }
        $customer = new Customer((int)$cart->id_customer);
        if ($customer->secure_key) {
            $key2 = 0;
        } else {
            $key2 = 1;
            $customer->secure_key = md5(uniqid(rand(), true));
        }
        $id_currency = (int)$cart->id_currency;
        $currency = new Currency((int)$id_currency);

        $language = new Language($this->context->language->id);
        $cart_total = $this->toQpAmount($cart->getOrderTotal(), $currency);
        if (isset($this->qpPreview)) {
            $cart_total = 10000;
        }
        $continueurl = $this->getModuleLink(
            'complete',
            array(
                'key' => $customer->secure_key,
                'key2' => $key2,
                'id_cart' => (int)$cart->id,
                'id_module' => (int)$this->id,
                'utm_nooverride' => 1
            )
        );
        $cancelurl = $this->getPageLink('order', 'step=3');
        $callbackurl = $this->getModuleLink('validation');
        $payment_url = $this->getModuleLink('payment');
        $html = '';

        if ($setup->autofee) {
            $fees = $this->getFees($cart_total);
        } else {
            $fees = false;
        }

        $order_id = $setup->orderprefix.(int)$cart->id;
        $done = false;
        if ($this->v16) {
            $browser = Tools::getUserBrowser();
        } else {
            $browser = '';
        }
        $setup_vars = $this->sortSetup();
        foreach ($setup_vars as $setup_var) {
            $id_option = $setup_var[1];
            if ($select_option && $id_option != $select_option) {
                continue;
            }
            $vars = $this->varsObj($setup_var);
            $card_list = array($vars->var_name);
            $card_text = $vars->card_text;
            $field = $vars->var_name;
            if (!$vars->card_type_lock || !$setup->$field) {
                continue;
            }
            $card_type_lock = $vars->card_type_lock;
            if ($setup->combine &&
                    isset($setup->credit_cards[$vars->var_name])) {
                // Group these cards
                if ($done) {
                    continue;
                }
                $card_text = $this->l('credit card');
                $card_list = array_keys($setup->credit_cards);
                $card_type_lock = implode(',', $setup->card_type_locks);
                $done = true;
            }
            if ($setup->autoget && isset($setup->credit_cards[$vars->var_name])) {
                    $card_type_lock = '';
            }
            if ($vars->var_name == 'mobilepay' &&
                empty($this->qpPreview) &&
                $invoice_country_code != 'DNK' &&
                $invoice_country_code != 'GRL' &&
                $invoice_country_code != 'FIN') {
                continue;
            }
            if ($vars->var_name == 'swish' &&
                empty($this->qpPreview) &&
                $invoice_country_code != 'SWE') {
                continue;
            }
            if ($vars->var_name == 'vipps' &&
                empty($this->qpPreview) &&
                $invoice_country_code != 'NOR') {
                continue;
            }
            if ($vars->var_name == 'anydaysplit' &&
                empty($this->qpPreview) &&
                $invoice_country_code != 'DNK') {
                continue;
            }
            if ($vars->var_name == 'googlepay' &&
                empty($this->qpPreview) &&
                $browser != 'Google Chrome') {
                continue;
            }
            if ($vars->var_name == 'applepay' &&
                empty($this->qpPreview) &&
                $browser != 'Apple Safari') {
                continue;
            }
            if ($vars->var_name == 'viabill') {
                if (empty($this->qpPreview) &&
                    $invoice_country_code != 'DNK') {
                    continue;
                }
                // Autofee does not work
                $amount = $cart_total;
                $autofee = 0;
            } elseif ($vars->var_name == 'klarna') {
                // Autofee does not work
                $amount = $cart_total;
                $autofee = 0;
            } else {
                $amount = $cart_total;
                $autofee = $setup->autofee;
            }
            $fee_texts = array();
            if ($card_list) {
                foreach ($card_list as $card_name) {
                    if ($autofee && ($card_name == 'mobilepay' || $card_name == 'swish')) {
                        $fee_text = array();
                        $fee_text['name'] = $this->l('Fee will be included in the payment window').
                            $fee_text['amount'] = '';
                        $fee_texts[] = $fee_text;
                        continue;
                    }
                    if (!empty($fees[$card_name])) {
                        $fee_text = array();
                        if ($card_name == 'viabill') {
                            $fee_text['name'] = $this->l('Fee for').
                                ' '.$this->l('ViaBill').":\xC2\xA0";
                        } else {
                            $fee_text['name'] = $this->l('Fee for').
                                ' '.$setup->card_texts[$card_name].":\xC2\xA0";
                        }
                        $fee_text['amount'] =
                            $this->displayQpAmount($fees[$card_name], $currency);
                        if (!empty($fees[$card_name.'_f'])) {
                            $fee_text['amount'] .= sprintf(
                                ' (%s: %s)',
                                $this->l('foreign'),
                                $this->displayQpAmount($fees[$card_name.'_f'], $currency)
                            );
                        }
                        $fee_texts[] = $fee_text;
                        if ($card_name == 'dk') {
                            $fee_texts = array();
                            $fee_text['name'] = $this->l('Fee for').
                                ' '.$this->l('Visa/Dankort').":\xC2\xA0";
                            if ($currency->iso_code != 'DKK' && isset($fees['visa'])) {
                                $fee_text['amount'] = $this->displayQpAmount($fees['visa'], $currency);
                            } else {
                                $fee_text['amount'] = $this->displayQpAmount($fees[$card_name], $currency);
                            }
                            $fee_texts[] = $fee_text;
                        }
                    }
                }
            }
            $images_list = array();
            foreach ($card_list as $card) {
                if (empty($hide_images_list[$card])) {
                    $images_list[] = $card;
                }
                if (isset($setup->secure_list[$card]) && empty($hide_images_list[$card.'_secure'])) {
                    $images_list[] = $setup->secure_list[$card];
                }
            }
            $smarty->assign('fees', $fee_texts);
            $branding = $setup->branding ? $setup->branding : '';
            $csum_fields = array(
                'amount'            => $amount,
                'autocapture'       => $setup->autocapture,
                'autofee'           => $autofee,
                'branding_id'       => $branding,
                'callback_url'      => $callbackurl,
                'cancel_url'        => $cancelurl,
                'continue_url'      => $continueurl,
                'currency'          => $currency->iso_code,
                'language'          => $language->iso_code,
                'merchant_id'       => $setup->merchant_id,
                'order_id'          => $order_id,
                'payment_methods'   => $card_type_lock,
                'text_on_statement' => $setup->statementtext,
            );
            $smarty_fields = array();
            foreach ($csum_fields as $key => $value) {
                $smarty_fields[] = array(
                    'name' => $key,
                    'value' => $value
                );
            }
            if ($select_option) {
                return $smarty_fields;
            }
            $fields = array(
                'fields'      => $smarty_fields,
                'payment_url' => $payment_url,
                'imgs'        => $images_list,
                'text'        => $this->l('Pay with').' '.$card_text,
                'type'        => $vars->var_name,
                'module_dir'  => $this->_path
            );
            $smarty->assign($fields);
            if ($this->v17 && empty($this->qpPreview)) {
                $parms = array('option' => $id_option, 'order_id' => $order_id);
                $tpl = 'module:quickpay/views/templates/hook/payment17.tpl';
                $newOption = new PrestaShop\PrestaShop\Core\Payment\PaymentOption();
                $newOption->setCallToActionText($fields['text'])
                    ->setAction($this->getModuleLink('payment', $parms))
                    ->setAdditionalInformation($this->fetch($tpl));
                $paymentOptions[] = $newOption;
            } else {
                $html .= $this->display(__FILE__, 'views/templates/hook/payment.tpl');
            }
        }

        return $html;
    }

    public function previewPayment()
    {
        $params = array();
        $paymentOptions = array();
        $cart = new Cart();
        $cart->id_currency = Configuration::get('PS_CURRENCY_DEFAULT');
        $params['cart'] = $cart;
        $this->qpPreview = true;
        $innerHtml = $this->makePayment($params, $paymentOptions);
        $innerHtml = str_replace('</a>', '', $innerHtml);
        $innerHtml = preg_replace('/<a [^>]*>/', '', $innerHtml);
        // $innerHtml .= print_r($this->imagesSetup(), true);
        $this->context->smarty->assign('title', $this->l('Payment preview'));
        $outerHtml = $this->display(__FILE__, 'views/templates/hook/preview.tpl');
        $html = str_replace('_HTML_', $innerHtml, $outerHtml);
        $innerHtml = $this->hookDisplayLeftColumn();
        $innerHtml = str_replace('<center>', '', $innerHtml);
        $innerHtml = str_replace('</center>', '', $innerHtml);
        $this->context->smarty->assign('title', $this->l('Card logo preview'));
        $outerHtml = $this->display(__FILE__, 'views/templates/hook/preview.tpl');
        $html .= str_replace('_HTML_', $innerHtml, $outerHtml);
        return $html;
    }


    public function hookDisplayPayment($params)
    {
        $paymentOptions = array();
        return $this->makePayment($params, $paymentOptions);
    }


    public function hookPaymentOptions($params)
    {
        $paymentOptions = array();
        $this->makePayment($params, $paymentOptions);
        return $paymentOptions;
    }


    public function hookDisplayLeftColumn()
    {
        $smarty = $this->context->smarty;

        $setup = $this->getSetup();
        $setup_vars = $this->sortSetup();
        if ($setup->showcards) {
            $ordering_list = $this->getOrderingList($setup, $setup_vars);
        } else {
            $ordering_list = array();
        }

        if ($ordering_list) {
            $smarty->assign(
                array(
                    'ordering_list' => $ordering_list,
                    'link' => $this->context->link
                )
            );
            return $this->display(__FILE__, 'views/templates/hook/leftlogo.tpl');
        }
        return '';
    }

    public function hookRightColumn()
    {
        return $this->hookDisplayLeftColumn();
    }

    public function hookDisplayFooter()
    {
        $smarty = $this->context->smarty;

        $setup = $this->getSetup();
        if (!$setup->showcardsfooter) {
            return;
        }

        $setup_vars = $this->sortSetup();
        $ordering_list = $this->getOrderingList($setup, $setup_vars);

        if ($ordering_list) {
            $smarty->assign(
                array(
                    'ordering_list' => $ordering_list,
                    'link' => $this->context->link
                )
            );
            return $this->display(__FILE__, 'views/templates/hook/footerlogo.tpl');
        }
        return '';
    }

    public function getBrand($vars)
    {
        if (empty($this->setup_vars)) {
            $this->getSetup();
        }
        $brand = $vars->metadata->brand;
        if (!$brand) {
            $brand = $vars->acquirer;
        }
        foreach ($this->setup_vars as $setup_var) {
            $vars = $this->varsObj($setup_var);
            $card_type_locks = explode(',', $vars->card_type_lock);
            if (in_array($brand, $card_type_locks)) {
                $text = explode(' ', $vars->card_text);
                $brand = $text[0];
                break;
            }
        }
        return $brand;
    }

    public function hookDisplayPaymentReturn($params)
    {
        if (!$this->active) {
            return;
        }

        if ($this->v17) {
            $order = $params['order'];
        } else {
            $order = $params['objOrder'];
        }
        $state = $order->getCurrentState();
        if ($state == _PS_OS_ERROR_) {
            $status = 'callback';
            $msg = 'QuickPay: Confirmation failed';
            $this->addLog($msg, 2, 0, 'Order', $order->id);
        } else {
            $status = 'ok';
        }
        $this->smarty->assign('status', $status);
        if ($this->v17) {
            $url = $this->getPageLink('contact', 'file');
            $this->smarty->assign('shop_name', Configuration::get('PS_SHOP_NAME'));
            $this->smarty->assign('base_dir_ssl', $url);
        }
        $trans = Db::getInstance()->getRow(
            'SELECT *
            FROM '._DB_PREFIX_.'quickpay_execution
            WHERE `id_cart` = '.$order->id_cart.'
            ORDER BY `id_cart` ASC'
        );
        $json = $trans['json'];
        $vars = $this->jsonDecode($json);
        $query = parse_url($vars->link->continue_url, PHP_URL_QUERY);
        parse_str($query, $args);
        if ($args['key2']) {
            $this->context->customer->mylogout();
        }
        return $this->display(__FILE__, 'views/templates/hook/confirmation.tpl');
    }

    public function hookDisplayAdminOrderSide($params)
    {
        $order = new Order((int)$params['id_order']);
        $this->orderShopId = $order->id_shop;
        $setup = $this->getSetup();
        if (!$setup->api) {
            return '';
        }

        $currency = new Currency((int)$order->id_currency);
        $module = Db::getInstance()->getRow(
            'SELECT `module`
            FROM '._DB_PREFIX_.'orders
            WHERE `id_order` = '.(int)$params['id_order']
        );
        if ($module['module'] != 'quickpay') {
            return '';
        }
        $trans = Db::getInstance()->getRow(
            'SELECT *
            FROM '._DB_PREFIX_.'quickpay_execution
            WHERE `id_cart` = '.$order->id_cart.'
            ORDER BY `exec_id` ASC'
        );
        if ($trans) {
            $trans_id = $trans['trans_id'];
            $order_id = $trans['order_id'];
        } else {
            // Look for payment via API
            $order_id = $setup->orderprefix.(int)$order->id_cart;
            $json = $this->doCurl('payments', array('order_id='.$order_id), 'GET');
            $vars = $this->jsonDecode($json);
            $vars = reset($vars);
            if (empty($vars->id)) {
                return '';
            }
            $trans_id = $vars->id;
            $order_id = $vars->order_id;
        }
        if ($this->v177) {
            $html = '<div class="card"><div class="card-header">
                <h3><img src="'.$this->_path.'logo.png" />
                QuickPay</h3></div>
                <div class="card-body">';
        } elseif ($this->v16) {
            $html = '<div class="row"><div class="col-lg-5 panel">
                <div class="panel-heading"><img src="'.$this->_path.'logo.png" />
                QuickPay '.$this->l('API').'</div>';
        } else {
            $html = '<br />
                <fieldset>
                <legend>QuickPay '.$this->l('API').'</legend>';
        }

        $double_post = false;
        $status_data = $this->doCurl('payments/'.$trans_id);
        if (!empty($status_data)) {
            $vars = $this->jsonDecode($status_data);
            if (!empty($vars->id) &&
                    Tools::getValue('qp_count') < count($vars->operations)) {
                $double_post = true;
            }
        }

        if (!$double_post && Tools::isSubmit('qpcapture')) {
            $amount = Tools::getValue('acramount');
            $amount = $this->fromUserAmount($amount, $currency);
            $amount = $this->toQpAmount($amount, $currency);
            $callbackurl = $this->getModuleLink('validation');
            $fields = array('amount='.$amount);
            $action_data = $this->doCurl('payments/'.$trans_id.'/capture', $fields, 'POST', $callbackurl);
            // $html .= '<pre>'.print_r(json_decode($action_data), true).'</pre>';
        }

        if (!$double_post && Tools::isSubmit('qprefund')) {
            $amount = Tools::getValue('acramountref');
            $amount = $this->fromUserAmount($amount, $currency);
            $amount = $this->toQpAmount($amount, $currency);
            $fields = array('amount='.$amount);
            $action_data = $this->doCurl('payments/'.$trans_id.'/refund', $fields);
            // $html .= '<pre>'.print_r(json_decode($action_data), true).'</pre>';
        }

        if (!$double_post && Tools::isSubmit('qpcancel')) {
            $action_data = $this->doCurl('payments/'.$trans_id.'/cancel', null, 'POST');
            // $html .= '<pre>'.print_r($action_data, true).'</pre>';
            // $html .= '<pre>'.print_r(json_decode($action_data), true).'</pre>';
        }

        if (isset($action_data)) {
            $vars = $this->jsonDecode($action_data);
            if (isset($vars) && isset($vars->errors) && get_object_vars($vars->errors)) {
                if (isset($vars->errors->amount) && $vars->errors->amount[0] == 'is too large') {
                    $html .= '<p class="error alert-danger">'.$this->l('Amount is too large').'</p>';
                } else {
                    $html .= '<pre>'.print_r($this->jsonDecode($action_data), true).'</pre>';
                }
            } elseif (isset($vars) && isset($vars->message)) {
                $html .= '<p class="error alert-danger">'.$vars->message.'</p>';
            }
        }

        // Get status reply from quickpay
        $status_data = $this->doCurl('payments/'.$trans_id);
        if (empty($status_data)) {
            $html .= '<pre>'.$this->curl_error.'</pre>';
            if ($this->v16) {
                $html .= '</div></div>';
            } else {
                $html .= '</fieldset>';
            }
            return $html;
        }
        $vars = $this->jsonDecode($status_data);
        if (empty($vars->id)) {
            $html .= '<pre>'.$vars->message.'</pre>';
            if ($this->v16) {
                $html .= '</div></div>';
            } else {
                $html .= '</fieldset>';
            }
            return $html;
        }

        $html .= '<table>';
        $html .= '<tbody>';
        $html .= '<tr><th style="padding-right:10px">';
        $html .= 'QuickPay '.$this->l('order ID:');
        $html .= '</th><td>';
        $html .= $order_id;
        if ($vars->test_mode) {
            $html .= ' ['.$this->l('test mode').']';
        }
        $html .= '</td></tr>';

        $html .= '<tr><th>';
        $html .= $this->l('Transaction ID:');
        $html .= '</th><td>';
        $html .= $vars->id;
        $html .= '</td></tr>';

        $html .= '<tr><th>';
        $html .= $this->l('Acquirer:');
        $html .= '</th><td>';
        $html .= Tools::ucfirst($vars->acquirer);
        $html .= ' '.Tools::ucfirst($vars->facilitator);
        $html .= '</td></tr>';

        $html .= '<tr><th>';
        $html .= $this->l('Card type:');
        $html .= '</th><td>';
        $html .= Tools::ucfirst($vars->metadata->brand);
        if ($vars->metadata->is_3d_secure) {
            $html .= ' '.$this->l('[3D secure]');
        }
        $html .= '</td></tr>';

        $html .= '<tr><th>';
        $html .= $this->l('Country:');
        $html .= '</th><td>';
        $html .= $vars->metadata->country;
        $html .= '</td></tr>';

        $html .= '<tr><th>';
        $html .= $this->l('Created:');
        $html .= '</th><td>';
        $html .= Tools::displayDate(
            date(
                'Y-m-d H:i:s',
                strtotime($vars->created_at)
            ),
            null,
            true
        );
        $html .= '</td></tr>';

        if ($vars->metadata->fraud_suspected) {
            if (empty($vars->metadata->fraud_remarks)) {
                $vars->metadata->fraud_remarks[] = $this->l('Suspicious payment');
            }
            $html .= '<tr><th>';
            $html .= $this->l('Fraud:');
            $html .= '</th><td class="alert-danger">';
            $html .= implode(
                '</td></tr><tr><td></td><td class="alert-danger">',
                $vars->metadata->fraud_remarks
            );
            $html .= '</td></tr>';
        }

        $html .= '</tbody>';
        $html .= '</table><br />';

        if (Tools::getValue('qpDebug')) {
            $html .= '<pre>'.print_r($this->jsonDecode($status_data), true).'</pre>';
        }
        // $html .= '<pre>'.print_r($_POST, true).'</pre>';
        $html .= '<table class="table">';
        $html .= '<thead>';
        $html .= '<tr><th>';
        $html .= $this->l('Date');
        $html .= '</th><th>';
        $html .= $this->l('Operation');
        $html .= '</th><th>';
        $html .= $this->l('Amount');
        $html .= '</th></tr>';
        $html .= '</thead>';
        $html .= '<tbody>';
        $resttocap = - $vars->balance;
        $resttoref = 0;
        $allowcancel = true;
        $qp_count = count($vars->operations);
        $qp_total = $this->toQpAmount($order->total_paid, $currency);
        foreach ($vars->operations as $operation) {
            $html .= '<tr><td>';
            $html .= Tools::displayDate(
                date(
                    'Y-m-d H:i:s',
                    strtotime($operation->created_at)
                ),
                null,
                true
            );
            $html .= '</td><td>';
            switch ($operation->type) {
                case 'capture':
                    $resttoref += $operation->amount;
                    $resttocap -= $operation->amount;
                    $allowcancel = false;
                    $html .= $this->l('Captured');
                    break;
                case 'authorize':
                    if ($operation->aq_status_code == 202) {
                        $resttocap = 0;
                        $html .= $this->l('Waiting for approval');
                    } else {
                        if ($operation->amount > $qp_total) {
                            $resttocap = $qp_total;
                        } else {
                            $resttocap = $operation->amount;
                        }
                        $html .= $this->l('Authorized');
                    }
                    break;
                case 'refund':
                    $resttoref -= $operation->amount;
                    $resttocap += $operation->amount;
                    $html .= $this->l('Refunded');
                    break;
                case 'cancel':
                    $resttocap = 0;
                    $allowcancel = false;
                    $html .= $this->l('Cancelled');
                    break;
                case 'session':
                    $resttocap += $operation->amount;
                    $html .= $this->l('Pending');
                    break;
                default:
                    $html .= $operation->type;
                    break;
            }
            if ($operation->qp_status_code != '20000') {
                $html .= ' ['.$this->l('Not approved!').']';
            }
            $html .= '</td><td style="text-align:right">';
            $html .= ' '.$this->displayQpAmount($operation->amount, $currency);
            $html .= '</td></tr>';
        }
        if ($resttocap < 0) {
            $resttocap = 0;
        }
        $resttocap = $this->fromQpAmount($resttocap, $currency);
        if ($resttoref < 0) {
            $resttoref = 0;
        }
        $resttoref = $this->fromQpAmount($resttoref, $currency);
        $html .= '</tbody>';
        $html .= '</table>';

        $url = 'index.php?controller='.Tools::getValue('controller');
        $url .= '&id_order='.Tools::getValue('id_order');
        $url .= '&vieworder&token='.Tools::getValue('token');
        $html .= '<br /><br />';
        if ($resttocap > 0) {
            $resttocap = $this->toUserAmount($resttocap, $currency);
            $html .= '<form action="'.$url.'" method="post" name="capture-cancel">';
            $html .= '<input type="hidden" name="qp_count" value="'.$qp_count.'" />';
            $html .= '<b>'.$this->l('Amount to capture:').'</b>';
            $html .= '<div>
                <input style="width:auto;display:inline" type="text" name="acramount" value="'.$resttocap.'"/>
                <input type="submit" class="button" name="qpcapture" value="'.
                $this->l('Capture').'" onclick="return confirm(\''.
                $this->l('Are you sure you want to capture the amount?').'\')"/></div><br />';
            $html .= '</form>';
        }
        if ($resttoref > 0) {
            $resttoref = $this->toUserAmount($resttoref, $currency);
            $html .= '<form action="'.$url.'" method="post" name="capture-cancel">';
            $html .= '<input type="hidden" name="qp_count" value="'.$qp_count.'" />';
            $html .= '<b>'.$this->l('Amount to refund').' ('.$resttoref.'):</b>';
            $html .= '<div>
                <input style="width:auto;display:inline" type="text" name="acramountref" id="acramountref" value="" />
                <input type="submit" class="button" name="qprefund" value="'.
                $this->l('Refund').'" onclick="return confirm(\''.
                $this->l('Are you sure you want to refund the amount?').'\');"/></div><br />';
            $html .= '</form>';
        }
        if ($allowcancel) {
            $html .= '<form action="'.$url.'" method="post" name="capture-cancel">';
            $html .= '<input type="hidden" name="qp_count" value="'.$qp_count.'" />';
            $html .= '<input type="submit" name="qpcancel" value="';
            $html .= $this->l('Cancel the transaction!');
            $html .= '" class="button" onclick="return confirm(\'';
                    $html .= $this->l('Are you sure you want cancel the transaction?').'\')"/></center>';
            $html .= '</form><br />';
        }
        $html .= '<a href="https://manage.quickpay.net" target="_blank" style="color: blue;">'.
            sprintf($this->l('%s manager'), 'QuickPay').'</a>';
        if ($this->v16) {
            $html .= '</div></div>';
        } else {
            $html .= '</fieldset>';
        }
        return $html;
    }

    public function hookDisplayAdminOrder($params)
    {
        if ($this->v177) {
            return '';
        } else {
            return $this->hookDisplayAdminOrderSide($params);
        }
    }

    public function hookDisplayPDFInvoice($params)
    {
        $object = $params['object'];
        $order = new Order((int)$object->id_order);
        $trans = Db::getInstance()->getRow(
            'SELECT *
            FROM '._DB_PREFIX_.'quickpay_execution
            WHERE `id_cart` = '.$order->id_cart.'
            ORDER BY `exec_id` ASC'
        );
        if (isset($trans['trans_id'])) {
            // $brand = $this->metadata->brand;
            $vars = $this->jsonDecode($trans['json']);
            $html = '<table><tr>';
            $html .= '<td style="width:100%; text-align:right">'.
                sprintf(
                    $this->l('%1$s transaction ID: %2$s'),
                    'QuickPay',
                    $trans['trans_id']
                ).
                '</td>';
            $html .= '</tr></table>';
            if (isset($vars->acquirer) && $vars->acquirer == 'viabill') {
                $html .= '<br/>';
                $html .='Det skyldige beløb kan alene betales med frigørende virkning til ViaBill, ';
                $html .= 'som fremsender særskilt opkrævning.';
                $html .= '<br/>';
                $html .= 'Betaling kan ikke ske ved modregning af krav, der udspringer af andre retsforhold.';
            }
            return $html;
        }
    }

    public function hookActionOrderStatusPostUpdate($params)
    {
        $this->getSetup();
        $new_state = $params['newOrderStatus'];
        $order = new Order($params['id_order']);
        $capture_states = explode(',', Configuration::get('_QUICKPAY_STATECAPTURE'));
        $cancelled = $new_state->id == _PS_OS_CANCELED_;
        if (in_array($new_state->id, $capture_states) || $cancelled) {
            $trans = Db::getInstance()->getRow(
                'SELECT *
                FROM '._DB_PREFIX_.'quickpay_execution
                WHERE `id_cart` = '.$order->id_cart.'
                ORDER BY `exec_id` ASC'
            );
            if ($trans) {
                $vars = $this->jsonDecode($trans['json']);
                $amount = $this->getAmount($vars);
                $callbackurl = $this->getModuleLink('validation');
                if ($amount) {
                    $currency = new Currency((int)$order->id_currency);
                    $amount_order = $this->toQpAmount($order->total_paid, $currency);
                    if ($amount > $amount_order) {
                        $amount = $amount_order;
                    }
                    $fields = array('amount='.$amount);
                    if ($cancelled) {
                        $this->doCurl('payments/'.$trans['trans_id'].'/cancel', null, 'POST');
                    } else {
                        $this->doCurl('payments/'.$trans['trans_id'].'/capture', $fields, 'POST', $callbackurl);
                    }
                }
            }
        }
    }

    public function hookDisplayExpressCheckout($params)
    {
        if (!$this->getConf('_QUICKPAY_MOBILEPAY_CHECKOUT')) {
            return '';
        }
        $cart = $params['cart'];
        $invoice_address = new Address((int)$cart->id_address_invoice);
        $country = new Country($invoice_address->id_country);
        if ($country->iso_code && !in_array($country->iso_code, array('DK', 'FI', 'GL'))) {
            return '';
        }
        $prefix = $this->getConf('_QUICKPAY_ORDER_PREFIX');
        $order_id = $prefix.(int)$cart->id;
        $parms = array(
            'option' => 'mobilepay',
            'order_id' => $order_id,
            'mobilepay_checkout' => 1
        );
        $payment_url = $this->getModuleLink('payment', $parms);
        $mobilepay_url = $this->getModuleLink('mobilepay');
        $id_cms = (int)$this->getConf('_QUICKPAY_CMS_ID');
        $this->context->smarty->assign(
            array(
                'payment_url' => $payment_url,
                'mobilepay_url' => $mobilepay_url,
                'id_cms' => $id_cms
            )
        );
        return $this->display(__FILE__, 'mobilepay.tpl');
    }

    public function hookDisplayShoppingCart($params)
    {
        if ($this->v17) {
            return '';
        }
        return $this->hookDisplayExpressCheckout($params);
    }

    public function sign($data, $key)
    {
        return call_user_func('hash_hmac', 'sha256', $data, $key);
    }

    public function group($entries)
    {
        return "('".implode("','", $entries)."')";
    }

    public function payment()
    {
        $setup = $this->getSetup();
        $fields = array();
        $id_option = Tools::getValue('option');
        $order_id = Tools::getValue('order_id');
        $mobilepay_checkout = Tools::getValue('mobilepay_checkout');
        $id_cart = (int)Tools::substr($order_id, 3);
        $cart = new Cart($id_cart);
        if ($id_option) {
            $params = array('cart' => $cart);
            $paymentOptions = array();
            $sfields = $this->makePayment($params, $paymentOptions, $id_option);
            foreach ($sfields as $sfield) {
                $fields[] = $sfield['name'].'='.urlencode($sfield['value']);
            }
        } else {
            foreach ($_POST as $k => $v) {
                if ($v != '') {
                    $fields[] = $k.'='.urlencode($v);
                }
            }
        }
        $delivery_address = new Address($cart->id_address_delivery);
        $carrier = new Carrier($cart->id_carrier);
        $invoice_address = new Address((int)$cart->id_address_invoice);
        $invoice_street = $invoice_address->address1;
        if ($invoice_address->address2) {
            $invoice_street .= ' '.$invoice_address->address2;
        }
        $country = new Country($invoice_address->id_country);
        $invoice_country_code = $this->getIso3($country->iso_code);
        $delivery_address = new Address((int)$cart->id_address_delivery);
        $delivery_street = $delivery_address->address1;
        if ($delivery_address->address2) {
            $delivery_street .= ' '.$delivery_address->address2;
        }
        $country = new Country($delivery_address->id_country);
        $delivery_country = $this->getIso3($country->iso_code);
        $customer = new Customer((int)$cart->id_customer);
        $currency = new Currency((int)$cart->id_currency);
        $info = array(
            'variables[module_version]' => $this->version,
            'shopsystem[name]' => 'PrestaShop',
            'shopsystem[version]' => $this->version,
            'customer_email' => $customer->email,
            'google_analytics_client_id' => $setup->ga_client_id,
            'google_analytics_tracking_id' => $setup->ga_tracking_id
        );
        if ($mobilepay_checkout) {
            $info += array(
                'invoice_address_selection' => true,
                'shipping_address_selection' => true
            );
        }
        if ($invoice_address->id) {
            $info += array(
                'invoice_address[name]' => $invoice_address->firstname.' '.$invoice_address->lastname,
                'invoice_address[street]' => $invoice_street,
                'invoice_address[city]' => $invoice_address->city,
                'invoice_address[zip_code]' => $invoice_address->postcode,
                'invoice_address[country_code]' => $invoice_country_code,
                'invoice_address[phone_number]' => $invoice_address->phone,
                'invoice_address[mobile_number]' => $invoice_address->phone_mobile,
                'invoice_address[vat_no]' => $invoice_address->vat_number,
                'invoice_address[email]' => $customer->email,
                'shipping_address[name]' => $delivery_address->firstname.' '.$delivery_address->lastname,
                'shipping_address[street]' => $delivery_street,
                'shipping_address[city]' => $delivery_address->city,
                'shipping_address[zip_code]' => $delivery_address->postcode,
                'shipping_address[country_code]' => $delivery_country,
                'shipping_address[phone_number]' => $delivery_address->phone,
                'shipping_address[mobile_number]' => $delivery_address->phone_mobile,
                'shipping_address[vat_no]' => $delivery_address->vat_number,
                'shipping_address[email]' => $customer->email
            );
        }
        foreach ($info as $k => $v) {
            $fields[] = $k.'='.urlencode($v);
        }
        if (!in_array('payment_methods=paypal', $fields)) {
            $info = array(
                'shipping[amount]' => $this->toQpAmount($cart->getTotalShippingCost(), $currency),
                'shipping[vat_rate]' => $carrier->getTaxesRate($delivery_address) / 100,
            );
            foreach ($info as $k => $v) {
                $fields[] = $k.'='.urlencode($v);
            }
            foreach ($cart->getProducts() as $product) {
                $info = array(
                    'basket[][qty]' => $product['cart_quantity'],
                    'basket[][item_no]' => $product['id_product'],
                    'basket[][item_name]' => $product['name'],
                    'basket[][item_price]' => $this->toQpAmount($product['price_wt'], $currency),
                    'basket[][vat_rate]' => $product['rate'] / 100
                );
                foreach ($info as $k => $v) {
                    $fields[] = $k.'='.urlencode($v);
                }
            }
            $total_discounts = $cart->getOrderTotal(true, Cart::ONLY_DISCOUNTS);
            if ($total_discounts > 0) {
                $info = array(
                    'basket[][qty]' => 1,
                    'basket[][item_no]' => 0,
                    'basket[][item_name]' => $this->l('Discount', $this->name),
                    'basket[][item_price]' => -$this->toQpAmount($total_discounts, $currency),
                    'basket[][vat_rate]' => $product['rate'] / 100
                );
                foreach ($info as $k => $v) {
                    $fields[] = $k.'='.urlencode($v);
                }
            }
            $total_wrapping = $cart->getOrderTotal(true, Cart::ONLY_WRAPPING);
            if ($total_wrapping > 0) {
                $info = array(
                    'basket[][qty]' => 1,
                    'basket[][item_no]' => 0,
                    'basket[][item_name]' => $this->l('Wrapping', $this->name),
                    'basket[][item_price]' => $this->toQpAmount($total_wrapping, $currency),
                    'basket[][vat_rate]' => $product['rate'] / 100
                );
                foreach ($info as $k => $v) {
                    $fields[] = $k.'='.urlencode($v);
                }
            }
        }
        if (!Validate::isLoadedObject($cart)) {
            $msg = 'QuickPay: Payment error. Not a valid cart';
            $this->addLog($msg, 2, 0, 'Cart', $id_cart);
            die('Not a valid cart');
        }
        Db::getInstance()->Execute(
            'DELETE
            FROM '._DB_PREFIX_.'quickpay_execution
            WHERE `id_cart` = '.$id_cart
        );
        $json = $this->doCurl('payments', $fields);
        $vars = $saved_vars = $this->jsonDecode($json);
        if (empty($vars->id)) {
            // Payment already exists
            $json = $this->doCurl('payments', array('order_id='.$order_id), 'GET');
            $vars = $this->jsonDecode($json);
            if (isset($vars->message)) {
                if (isset($saved_vars->message)) {
                    $msg = 'QuickPay: Payment error: '.$saved_vars->message;
                    $this->addLog($msg, 2, 0, 'Cart', $id_cart);
                    die($saved_vars->message);
                } else {
                    $msg = 'QuickPay: Payment error: '.$vars->message;
                    $this->addLog($msg, 2, 0, 'Cart', $id_cart);
                    die($vars->message);
                }
            }
            $vars = reset($vars);
            if (empty($vars->id)) {
                if (empty($this->qp_error)) {
                    $msg = 'QuickPay: Payment error: '.$saved_vars->message;
                } else {
                    $msg = 'QuickPay: cURL error: '.$this->qp_error;
                }
                $this->addLog($msg, 2, 0, 'Cart', $id_cart);
                die($msg);
            }
            $this->doCurl('payments/'.$vars->id, $fields, 'PATCH');
        }
        $values = array($id_cart, $vars->id, $vars->order_id, 0, 0, pSql($json));
        Db::getInstance()->Execute(
            'INSERT INTO '._DB_PREFIX_.'quickpay_execution
            (`id_cart`, `trans_id`, `order_id`, `accepted`, `test_mode`, `json`)
            VALUES '.$this->group($values)
        );
        if ($vars->accepted) {
            // Already paid
            $msg = 'QuickPay: Payment notice: Already paid';
            $this->addLog($msg, 2, 0, 'Cart', $id_cart);
            $paid_url = $this->getModuleLink(
                'complete',
                array(
                    'key' => $customer->secure_key,
                    'id_cart' => (int)$cart->id,
                    'id_module' => (int)$this->id
                )
            );
            Tools::redirect($paid_url, '');
            return;
        }
        if ($currency->iso_code != $vars->currency) {
            /*
               $fields = array('currency' => $currency->iso_code);
               $res = $this->doCurl('payments/'.$vars->id, $fields, 'PATCH');
             */
            $msg = sprintf(
                'QuickPay: Payment error: Currency was changed from %s to %s',
                $vars->currency,
                $currency->iso_code
            );
            $this->addLog($msg, 2, 0, 'Cart', $id_cart);
            $cart->delete();
            $fail_url = $this->getModuleLink('fail', array('status' => 'currency'));
            Tools::redirect($fail_url, '');
            return;
        }
        $json = $this->doCurl('payments/'.$vars->id.'/link', $fields, 'PUT');
        $vars = $this->jsonDecode($json);
        if (isset($vars->message)) {
            $msg = 'QuickPay: Payment error: '.$vars->message;
            $this->addLog($msg, 2, 0, 'Cart', $id_cart);
            die($vars->message);
        }
        Tools::redirect($vars->url, '');
    }

    public function mobilePay()
    {
        $cart = $this->context->cart;
        $delivery_option = $cart->getDeliveryOption();
        $carrier = new Carrier((int)reset($delivery_option));
        $id_lang = (int)$cart->id_lang;
        $prefix = $this->getConf('_QUICKPAY_ORDER_PREFIX');
        $order_id = $prefix.(int)$cart->id;
        $id_cms = (int)$this->getConf('_QUICKPAY_CMS_ID');
        $links = CMS::getLinks(
            Context::getContext()->language->id,
            array($id_cms)
        );
        $link = reset($links);
        if (isset($link['link'])) {
            $mobilepay_link = $link['link'];
        } else {
            $mobilepay_link = '';
        }
        $parms = array(
            'option' => 'mobilepay',
            'order_id' => $order_id,
            'mobilepay_checkout' => 1
        );
        $payment_url = $this->getModuleLink('payment', $parms);
        $this->context->smarty->assign(
            array(
                'payment_url' => $payment_url,
                'mobilepay_link' => $mobilepay_link,
                'carrier_name' => $carrier->name,
                'carrier_delay' => $carrier->delay[$id_lang]
            )
        );
        print $this->display(__FILE__, 'mobilepay.tpl');
        exit;
    }

    public function addCustomer($vars, &$cart)
    {
        $query = parse_url($vars->link->continue_url, PHP_URL_QUERY);
        parse_str($query, $args);
        if ($args['key2']) {
            // New customer
            $customer = new Customer();
            $email = $vars->invoice_address->email;
            if (!$customer->getByEmail($email)) {
                // New customer
                $customer->email = $email;
                $name = explode(' ', trim($vars->invoice_address->name));
                $customer->lastname = array_pop($name);
                $customer->firstname = implode(' ', $name);
                $customer->passwd = Tools::encrypt(Tools::passwdGen(MIN_PASSWD_LENGTH, 'RANDOM'));
                $customer->is_guest = true;
                $customer->add();
            }
        } else {
            // Old customer
            $customer = new Customer((int)$cart->id_customer);
        }
        $cart->secure_key = $customer->secure_key;
        $cart->id_customer = $customer->id;
        $cart->id_currency = Currency::getIdByIsoCode($vars->currency);
        $phone_number = '';
        if ($vars->invoice_address) {
            $id_country = Country::getByIso($this->getIso2($vars->invoice_address->country_code));
            $address1 = $vars->invoice_address->street;
            $address1 .= ' '.$vars->invoice_address->house_number;
            $alias = 'mobilepay';
            if ($vars->invoice_address->house_extension) {
                $address1 .= ' '.$vars->invoice_address->house_extension;
            }
            $row = Db::getInstance()->getRow(
                'SELECT `id_address`
                FROM '._DB_PREFIX_.'address
                WHERE `alias` = "'.$alias.'"
                AND `id_customer` = "'.$customer->id.'"'
            );
            if ($row) {
                $address = new Address($row['id_address']);
            } else {
                $address = new Address();
            }
            $address->id_customer = $customer->id;
            $address->lastname = $customer->lastname;
            $address->firstname = $customer->firstname;
            $address->alias = $alias;
            $address->id_country = $id_country;
            $address->company = $vars->invoice_address->company_name;
            $address->vat_number = $vars->invoice_address->vat_no;
            $address->address1 = $address1;
            $address->postcode = $vars->invoice_address->zip_code;
            $address->city = $vars->invoice_address->city;
            $phone_number = $vars->invoice_address->phone_number;
            $address->phone = $phone_number;
            $address->phone_mobile = $phone_number;
            if ($row) {
                $address->update();
            } else {
                $address->add();
            }
            $cart->id_address_invoice = $address->id;
            $cart->id_address_delivery = $address->id;
        }
        if ($vars->shipping_address) {
            $id_country = Country::getByIso($this->getIso2($vars->shipping_address->country_code));
            $address1 = $vars->shipping_address->street;
            $address1 .= ' '.$vars->shipping_address->house_number;
            $alias = 'mobilepay '.$this->l('delivery');
            if ($vars->shipping_address->house_extension) {
                $address1 .= ' '.$vars->shipping_address->house_extension;
            }
            $row = Db::getInstance()->getRow(
                'SELECT `id_address`
                FROM '._DB_PREFIX_.'address
                WHERE `alias` = "'.$alias.'"
                AND `id_customer` = "'.$customer->id.'"'
            );
            if ($row) {
                $address = new Address($row['id_address']);
            } else {
                $address = new Address();
            }
            $address->id_customer = $customer->id;
            $address->lastname = $customer->lastname;
            $address->firstname = $customer->firstname;
            $address->alias = $alias;
            $address->id_country = $id_country;
            $address->company = $vars->shipping_address->company_name;
            $address->vat_number = $vars->shipping_address->vat_no;
            $address->address1 = $address1;
            $address->postcode = $vars->shipping_address->zip_code;
            $address->city = $vars->shipping_address->city;
            if ($vars->shipping_address->phone_number) {
                $phone_number = $vars->shipping_address->phone_number;
            }
            $address->phone = $phone_number;
            $address->phone_mobile = $phone_number;
            if ($row) {
                $address->update();
            } else {
                $address->add();
            }
            $cart->id_address_delivery = $address->id;
        }
        Context::getContext()->cart = $cart;
        Context::getContext()->customer = $customer;
        // Update delivery cache
        $products = $cart->getProducts();
        foreach ($products as $product) {
            $cart->setProductAddressDelivery(
                $product['id_product'],
                $product['id_product_attribute'],
                $product['id_address_delivery'],
                $cart->id_address_delivery
            );
        }
        $cart->update();
        $this->context->cart = $cart;
    }

    public function validate($json, $checksum, $id_order_state = _PS_OS_PAYMENT_, $callback = false)
    {
        $this->getSetup();
        if ($checksum != $this->sign($json, $this->setup->private_key)) {
            $msg = 'QuickPay: Validate error. Checksum failed. Check private key in configuration';
            $this->addLog($msg, 2);
            die('Checksum failed');
        }

        $vars = $this->jsonDecode($json);
        if ($this->v16) {
            $brand = $this->getBrand($vars);
        } else {
            $brand = $this->displayName;
        }
        $accepted = $vars->accepted ? 1 : 0;
        $test_mode = $vars->test_mode ? 1 : 0;
        $id_cart = (int)Tools::substr($vars->order_id, 3);
        $cart = new Cart($id_cart);
        $this->context->cart = $cart;
        if (!empty($vars->link->invoice_address_selection)) {
            // MobilePay Checkout
            $this->addCustomer($vars, $cart);
        }
        if ($test_mode && !$this->setup->testmode) {
            $cart->delete();
            $msg = 'QuickPay: Validate error. Will not accept test payment!';
            $this->addLog($msg, 2, 0, 'Cart', $id_cart);
            if ($id_order_state == _PS_OS_ERROR_) {
                $fail_url = $this->getModuleLink('fail', array('status' => 'test'));
                Tools::redirect($fail_url, '');
            }
            die('Will not accept test payment!');
        }
        if (!Validate::isLoadedObject($cart)) {
            $msg = 'QuickPay: Validate error. Not a valid cart';
            $this->addLog($msg, 2, 0, 'Cart', $id_cart);
            die('Not a valid cart');
        }
        if ($callback && isset($vars->operations)) {
            $operation = end($vars->operations);
            if ($operation->type == 'capture') {
                if ($operation->qp_status_code != '20000') {
                    $msg = 'QuickPay: Capture error: '.$operation->qp_status_msg;
                    $this->addLog($msg, 3, 0, 'Cart', $id_cart);
                }
                die('Callback type is capture');
            }
            if ($operation->type != 'authorize') {
                die('Callback type is '.$operation->type);
            }
            if ($operation->qp_status_code != '20000') {
                die('Callback status is '.$operation->qp_status_code);
            }
        }
        if ($cart->OrderExists() != 0) {
            die('Order already exists');
        }
        $currency = new Currency((int)$cart->id_currency);
        if ($currency->iso_code != $vars->currency) {
            $msg = sprintf(
                'QuickPay: Validation error: Currency was changed from %s to %s',
                $vars->currency,
                $currency->iso_code
            );
            $this->addLog($msg, 2, 0, 'Cart', $id_cart);
        }
        Shop::setContext(Shop::CONTEXT_SHOP, $cart->id_shop);
        $customer = new Customer((int)$cart->id_customer);
        Context::getContext()->customer = $customer;
        Context::getContext()->currency = $currency;
        if ($this->v17 && !$this->v177) {
            global $kernel;
            if (!$kernel) {
                require_once _PS_ROOT_DIR_.'/app/AppKernel.php';
                $kernel = new \AppKernel('prod', false);
                $kernel->boot();
            }
        }
        $trans = Db::getInstance()->getRow(
            'SELECT *
            FROM '._DB_PREFIX_.'quickpay_execution
            WHERE `id_cart` = '.$id_cart.'
            ORDER BY `exec_id` ASC'
        );
        if ($trans['accepted']) {
            die('Order already accepted');
        }
        Db::getInstance()->Execute(
            'DELETE
            FROM '._DB_PREFIX_.'quickpay_execution
            WHERE `id_cart` = '.$cart->id
        );
        $values = array(
                $cart->id, $vars->id, $vars->order_id, $accepted,
                $test_mode, pSql($json));
        Db::getInstance()->Execute(
            'INSERT INTO '._DB_PREFIX_.'quickpay_execution
            (`id_cart`, `trans_id`, `order_id`, `accepted`, `test_mode`, `json`)
            VALUES '.$this->group($values)
        );
        $amount = $this->getAmount($vars);
        $fee = $this->getFee($vars);
        $fee = $this->fromQpAmount($fee, $currency);
        if ($accepted && $amount) {
            try {
                $amount_paid = $this->fromQpAmount($amount, $currency);
                $extra_vars = array(
                    'transaction_id' => $vars->id,
                    'cardtype' => $vars->metadata->brand
                );
                if ($this->setup->autofee && isset($vars->operations)) {
                    $this->addFee($cart, $fee);
                }
                if ($this->v17) {
                    smartyRegisterFunction(
                        $this->context->smarty,
                        'function',
                        'displayPrice',
                        array('Tools', 'displayPriceSmarty')
                    );
                }
                $total = $cart->getOrderTotal();
                $cart_total = $this->toQpAmount($total, $currency);
                if ($amount_paid == $cart_total) {
                    // Handle rounding problem e.g. with JPY
                    $amount_paid = $total;
                }
                if (!$this->validateOrder(
                    $cart->id,
                    $id_order_state,
                    $amount_paid,
                    $brand,
                    null,
                    $extra_vars,
                    null,
                    false,
                    $cart->secure_key
                )) {
                    $msg = 'QuickPay: Validate error. Unable to process order';
                    $this->addLog($msg, 2, 0, 'Cart', $id_cart);
                    die('Prestashop error - unable to process order..');
                }
            } catch (Exception $exception) {
                Db::getInstance()->Execute(
                    'UPDATE '._DB_PREFIX_.'quickpay_execution
                    SET `accepted` = 0
                    WHERE `id_cart` = '.$id_cart
                );
                $msg = 'QuickPay: Validate error. Exception ';
                $msg .= $exception->getMessage();
                $this->addLog($msg, 2, 0, 'Cart', $id_cart);
                die('Prestashop error - got exception.');
            }
            $id_order = Order::getOrderByCartId($cart->id);
            if ($id_order) {
                $order = new Order($id_order);
                $fields = array(
                    'variables[id_order]='.$order->id,
                    'variables[reference]='.$order->reference,
                );
                $this->doCurl('payments/'.$vars->id, $fields, 'PATCH');
            }
        }
    }

    public function getAmount($vars)
    {
        return (int)$vars->link->amount + (int)$vars->fee;
    }

    public function getFee($vars)
    {
        return (int)$vars->fee;
    }

    public function addFee(&$cart, $fee)
    {
        $id_lang = (int)$cart->id_lang;
        if ($this->v16) {
            $txt = $this->l('Credit card fee', $this->name);
        } else {
            $txt = $this->l('Credit card fee', $this->name, $id_lang);
        }
        $row = Db::getInstance()->getRow(
            'SELECT `id_product`
            FROM '._DB_PREFIX_.'product
            LEFT JOIN '._DB_PREFIX_.'product_lang
            USING (`id_product`)
            WHERE `name` = "'.$txt.'"'
        );
        if ($row) {
            SpecificPrice::deleteByProductId($row['id_product']);
            $product = new Product($row['id_product']);
            try {
                $cart->deleteProduct($row['id_product']);
            } catch (Exception $exception) {
                // Do nothing
            }
        } else {
            $product = new Product();
        }
        $cache_entries = Cache::retrieveAll();
        if ($fee <= 0) {
            return;
        }
        $product->name = array();
        $product->link_rewrite = array();
        foreach (Language::getLanguages(false) as $lang) {
            $id_lang = $lang['id_lang'];
            if ($this->v16) {
                $txt = $this->l('Credit card fee', $this->name);
            } else {
                $txt = $this->l('Credit card fee', $this->name, $id_lang);
            }
            $product->name[$id_lang] = $txt;
            $product->link_rewrite[$id_lang] = 'fee';
        }
        $product->active = 0;
        $product->price = $fee;
        $product->quantity = 100;
        $product->reference = $this->l('cardfee');
        $id_currency = Configuration::get('PS_CURRENCY_DEFAULT');
        $currency = new Currency((int)$cart->id_currency);
        if ($currency->id != $id_currency && $currency->conversion_rate) {
            $product->price /= $currency->conversion_rate;
        }
        $product->is_virtual = 1;
        $product->id_tax_rules_group = $this->setup->feetax;
        if ($product->id_tax_rules_group) {
            $address = new Address($cart->id_address_delivery);
            $tax_manager = TaxManagerFactory::getManager($address, $product->id_tax_rules_group);
            $tax_calculator = $tax_manager->getTaxCalculator();
            $tax_rate = $tax_calculator->getTotalRate();
            if ($tax_rate) {
                $product->price /= (1 + $tax_rate / 100);
            }
        }
        $product->price = Tools::ps_round($product->price, 6);
        if ($row) {
            $product->update();
        } else {
            Shop::setContext(Shop::CONTEXT_ALL, $cart->id_shop);
            $product->add();
            Shop::setContext(Shop::CONTEXT_SHOP, $cart->id_shop);
        }
        $rows = Group::getGroups($cart->id_lang);
        foreach ($rows as $row) {
            Db::getInstance()->Execute(
                'INSERT IGNORE INTO `'._DB_PREFIX_.'product_group_reduction_cache`
                (`id_product`, `id_group`, `reduction`)
                VALUES ('.(int)$product->id.', '.$row['id_group'].', 0)'
            );
        }
        StockAvailable::setQuantity($product->id, 0, 100);
        $cart->updateQty(1, $product->id);
        foreach ($cache_entries as $cache_id => $value) {
            $entry = explode('_', $cache_id);
            if ($entry[0] == 'getContextualValue') {
                $entry[] = $product->id;
                $entry[] = 0;
                $cache_id = implode('_', $entry);
                Cache::store($cache_id, $value);
                $cache_id = implode('_', $entry).'_1';
                Cache::store($cache_id, $value);
                $entry[4] = CartRule::FILTER_ACTION_ALL_NOCAP;
                $cache_id = implode('_', $entry);
                Cache::store($cache_id, $value);
                $cache_id = implode('_', $entry).'_1';
                Cache::store($cache_id, $value);
            }
        }
        $cart->getPackageList(true); // Flush cache
    }
}
