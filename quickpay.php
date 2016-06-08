<?php
/**
* NOTICE OF LICENSE
*
*  @author    Kjeld Borch Egevang
*  @copyright 2015 Quickpay
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*
*  $Date: 2016/06/08 17:59:24 $
*  E-mail: helpdesk@quickpay.net
*/

if (!defined('_PS_VERSION_'))
	exit;

if (function_exists('stream_resolve_include_path') &&
		stream_resolve_include_path(_PS_MODULE_DIR_.'quickpay/quickpay.inc.php'))
	include(_PS_MODULE_DIR_.'quickpay/quickpay.inc.php');


class QuickPay extends PaymentModule
{
	protected $config_form = false;
	private $post_errors = array();

	public function __construct()
	{
		$this->name = 'quickpay';
		$this->tab = 'payments_gateways';
		$this->version = '4.0.24b';
		$this->v14 = _PS_VERSION_ >= '1.4.1.0';
		$this->v15 = _PS_VERSION_ >= '1.5.0.0';
		$this->v16 = _PS_VERSION_ >= '1.6.0.0';
		$this->author = 'Kjeld Borch Egevang';
		$this->module_key = 'b99f59b30267e81da96b12a8d1aa5bac';
		$this->need_instance = 0;
		$this->secure_key = Tools::encrypt($this->name);
		$this->bootstrap = true;

		parent::__construct();

		$this->displayName = $this->l('Quickpay');
		$this->description = $this->l('Accept payments by Quickpay');
		$this->description = $this->l('Payment via Quickpay');
		$this->confirmUninstall =
			$this->l('Are you sure you want to delete your settings?');

		/* Backward compatibility */
		if (!$this->v15)
		{
			$this->local_path = _PS_MODULE_DIR_.$this->name.'/';
			$this->back_file =
				$this->local_path.'backward_compatibility/backward.php';
			if (file_exists($this->back_file))
				require($this->back_file);
		}

		if (!$this->v14)
			$this->warning = $this->l('This module only works for PrestaShop 1.4, 1.5 and 1.6');
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
		foreach ($keys as $key)
			$vars->$key = $setup_var[$i++];
		return $vars;
	}

	public function install()
	{
		include(dirname(__FILE__).'/sql/install.php');
		if (isset($this->back_file))
		{
			$data = Tools::file_get_contents($this->local_path.'translations/da.php');
			file_put_contents($this->local_path.'da.php', $data);
		}

		if (!parent::install())
			return false;
		$this->getSetup();
		foreach ($this->setup_vars as $setup_var)
		{
			$vars = $this->varsObj($setup_var);
			if (!Configuration::updateValue($vars->glob_name, $vars->def_val))
				return false;
		}
		return $this->registerHook('payment') &&
			$this->registerHook('paymentTop') &&
			$this->registerHook('leftColumn') &&
			$this->registerHook('footer') &&
			$this->registerHook('adminOrder') &&
			$this->registerHook('paymentReturn') &&
			$this->registerHook('PDFInvoice') &&
			$this->registerHook('postUpdateOrderStatus');
	}

	public function uninstall()
	{
		include(dirname(__FILE__).'/sql/uninstall.php');

		if (!parent::uninstall())
			return false;
		$this->getSetup();
		foreach ($this->setup_vars as $setup_var)
		{
			$vars = $this->varsObj($setup_var);
			if (!Configuration::deleteByName($vars->glob_name))
				return false;
		}
		return Configuration::deleteByName('_QUICKPAY_ORDERING') &&
			Configuration::deleteByName('_QUICKPAY_OVERLAY_CODE');
	}

	public function getSetup()
	{
		$this->setup_vars = array(
				array('_QUICKPAY_MERCHANT_ID', 'merchant_id', $this->l('Quickpay merchant ID'), '', ''),
				array('_QUICKPAY_PRIVATE_KEY', 'private_key', $this->l('Quickpay private key'), '', ''),
				array('_QUICKPAY_USER_KEY', 'user_key', $this->l('Quickpay user key'), '', ''),
				array('_QUICKPAY_ORDER_PREFIX', 'orderprefix', $this->l('Order prefix'), '000', ''),
				array('_QUICKPAY_TESTMODE', 'testmode', $this->l('Accept test payments'), 0, ''),
				array('_QUICKPAY_COMBINE', 'combine', $this->l('Creditcards combined window'), 0, ''),
				array('_QUICKPAY_AUTOFEE', 'autofee', $this->l('Customer pays the card fee'), 0, ''),
				array('_QUICKPAY_API', 'api', $this->l('Activate API'), 1, ''),
				array('_QUICKPAY_SHOWCARDS', 'showcards', $this->l('Show card logos in left column'), 1, ''),
				array('_QUICKPAY_SHOWCARDSFOOTER', 'showcardsfooter', $this->l('Show card logos in footer'), 1, ''),
				array('_QUICKPAY_AUTOCAPTURE', 'autocapture', $this->l('Auto-capture payments'), 0, ''),
				array('_QUICKPAY_STATECAPTURE', 'statecapture', $this->l('Capture payments in state'), 0, ''),
				array('_QUICKPAY_BRANDING', 'branding', $this->l('Branding in payment window'), 0, ''),
				array('_QUICKPAY_VIABILL', 'viabill', $this->l('ViaBill - buy now, pay whenever you want'), 0, 'viabill'),
				array('_QUICKPAY_DK', 'dk', $this->l('Dankort'), 0, 'dankort'),
				array('_QUICKPAY_EDK', 'edk', $this->l('eDankort'), 0, 'edankort'),
				array('_QUICKPAY_VISA', 'visa', $this->l('Visa card'), 0, 'visa,visa-dk'),
				array('_QUICKPAY_VELECTRON', 'visaelectron', $this->l('Visa Electron'), 0, 'visa-electron,visa-electron-dk'),
				array('_QUICKPAY_MASTERCARD', 'mastercard', $this->l('MasterCard'), 0, 'mastercard,mastercard-dk'),
				array('_QUICKPAY_MASTERCARDDEBET', 'mastercarddebet', $this->l('MasterCard Debet'), 0, 'mastercard-debet-dk'),
				array('_QUICKPAY_A_EXPRESS', 'express', $this->l('American Express'), 0, 'american-express,american-express-dk'),
				array('_QUICKPAY_MOBILEPAY', 'mobilepay', $this->l('MobilePay'), 0, 'mobilepay'),
				array('_QUICKPAY_FORBRUGS_1886', 'f1886', $this->l('Forbrugsforeningen af 1886'), 0, 'fbg1886'),
				array('_QUICKPAY_DINERS', 'diners', $this->l('Diners Club'), 0, 'diners,diners-dk'),
				array('_QUICKPAY_JCB', 'jcb', $this->l('JCB'), 0, 'jcb'),
				array('_QUICKPAY_VISA_3D', 'visa_3d', $this->l('Visa card (3D)'), 0, '3d-visa,3d-visa-dk'),
				array('_QUICKPAY_VELECTRON_3D', 'visaelectron_3d', $this->l('Visa Electron (3D)'), 0, '3d-visa-electron,3d-visa-electron-dk'),
				array('_QUICKPAY_MASTERCARD_3D', 'mastercard_3d', $this->l('MasterCard (3D)'), 0, '3d-mastercard,3d-mastercard-dk'),
				array('_QUICKPAY_MASTERCARDDEBET_3D', 'mastercarddebet_3d', $this->l('MasterCard Debet (3D)'), 0, '3d-mastercard-debet-dk'),
				array('_QUICKPAY_MAESTRO_3D', 'maestro_3d', $this->l('Maestro (3D)'), 0, '3d-maestro,3d-maestro-dk'),
				array('_QUICKPAY_JCB_3D', 'jcb_3d', $this->l('JCB (3D)'), 0, '3d-jcb'),
				array('_QUICKPAY_PAYEX', 'payex', $this->l('PayEx'), 0, 'creditcard'),
				array('_QUICKPAY_DANSKE', 'danske', $this->l('Danske'), 0, 'danske-dk'),
				array('_QUICKPAY_NORDEA', 'nordea', $this->l('Nordea'), 0, 'nordea-dk'),
				array('_QUICKPAY_PAYPAL', 'paypal', $this->l('PayPal'), 0, 'paypal'),
				array('_QUICKPAY_PAII', 'paii', $this->l('Paii'), 0, 'paii'),
				array('_QUICKPAY_SOFORT', 'sofort', $this->l('Sofort'), 0, 'sofort'));
		$this->setup = new StdClass();
		$this->setup->lock_names = array();
		$this->setup->card_type_locks = array('creditcard');
		$this->setup->card_type_locks3d = array('3d-creditcard');
		$this->setup->card_texts = array();
		$this->setup->credit_cards = array();
		$this->setup->credit_cards3d = array();
		$this->setup->credit_cards2di = array();
		$this->setup->credit_cards3di = array();
		$credit_cards = array(
				'dk',
				'edk',
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
				'maestro_3d' => 'maestro'
				);
		$credit_cards3d = array(
				'visa_3d' => 'visa_3d',
				'visaelectron_3d' => 'visa_3d',
				'mastercard_3d' => 'mastercard_3d',
				'mastercarddebet_3d' => 'mastercard_3d',
				'maestro_3d' => 'mastercard_3d',
				'jcb_3d' => 'jcb_3d'
				);
		$setup_vars = $this->sortSetup();
		$creditcard_type_locks = array();
		foreach ($setup_vars as $setup_var)
		{
			$vars = $this->varsObj($setup_var);
			$field = $vars->var_name;
			$this->setup->$field = Configuration::get($vars->glob_name);
			$this->setup->card_texts[$vars->var_name] = $vars->card_text;
			if ($vars->var_name == 'maestro_3d')
				$this->setup->card_texts['maestro'] = $this->l('Maestro');
			if (in_array($vars->var_name, $credit_cards) && $this->setup->$field)
				$this->setup->credit_cards[$vars->var_name] = $vars->card_text;
			if (isset($credit_cards3d[$vars->var_name]) && $this->setup->$field)
			{
				$this->setup->credit_cards3d[$vars->var_name] = $vars->card_text;
				if (isset($credit_cards2d[$vars->var_name]))
					$this->setup->credit_cards2di[$credit_cards2d[$vars->var_name]] = true;
				$this->setup->credit_cards3di[$credit_cards3d[$vars->var_name]] = true;
			}
			$card_type_locks = explode(',', $vars->card_type_lock);
			foreach ($card_type_locks as $name)
			{
				$this->setup->lock_names[$name] = $vars->var_name;
				$creditcard_type_locks[] = $name;
			}
		}
		foreach ($this->setup_vars as $setup_var)
		{
			$vars = $this->varsObj($setup_var);
			$field = $vars->var_name;
			$card_type_locks = explode(',', $vars->card_type_lock);
			if (!$this->setup->$field)
			{
				if (in_array($vars->var_name, $credit_cards))
				{
					foreach ($card_type_locks as $name)
					{
						if (!in_array('!'.$name, $this->setup->card_type_locks))
							$this->setup->card_type_locks[] = '!'.$name;
					}
				}
				if (isset($credit_cards3d[$vars->var_name]))
				{
					foreach ($card_type_locks as $name)
						$this->setup->card_type_locks3d[] = '!'.$name;
				}
			}
		}
		// $this->dump($this->setup->card_type_locks);
		return $this->setup;
	}

	public function sortSetup()
	{
		$ordering = Configuration::get('_QUICKPAY_ORDERING');
		if ($ordering)
			$ordering_list = explode(',', $ordering);
		else
			$ordering_list = array();
		$setup_dict = array();
		foreach ($this->setup_vars as $setup_var)
		{
			$vars = $this->varsObj($setup_var);
			$setup_dict[$vars->var_name] = $setup_var;
		}
		$setup_vars = array();
		foreach ($ordering_list as $vars->var_name)
			if (isset($setup_dict[$vars->var_name]))
				$setup_vars[$vars->var_name] = $setup_dict[$vars->var_name];
		foreach ($setup_dict as $vars->var_name => $setup_var)
			if (empty($setup_vars[$vars->var_name]))
				$setup_vars[$vars->var_name] = $setup_var;
		return $setup_vars;
	}

	public function getPageLink($name, $parm)
	{
		if ($this->v15)
			$url = $this->context->link->getPageLink(
					$name, true, null, 'step=3'
					);
		else
		{
			$url = Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://';
			$url .= $_SERVER['HTTP_HOST'].__PS_BASE_URI__.$name.'.php?'.$parm;
		}
		return $url;
	}

	public function getModuleLink($name, $parms = array())
	{
		if ($this->v15)
		{
			$url = $this->context->link->getModuleLink(
					$this->name, $name, $parms, true
				);
		}
		else
		{
			$url = Configuration::get('PS_SSL_ENABLED') ? 'https://' : 'http://';
			$url .= $_SERVER['HTTP_HOST'].$this->_path.$name.'.php';
			if ($parms)
			{
				$key_values = array();
				foreach ($parms as $k => $v)
					$key_values[] = $k.'='.$v;
				$url .= '?'.implode('&', $key_values);
			}
		}
		return $url;
	}

	public function dump($var, $name = null)
	{
		print '<pre>';
		if ($name)
			print "$name:\n";
		print_r($var);
		print '</pre>';
	}

	public function changeState()
	{
		$target = Tools::getValue('target');
		$this->getSetup();
		foreach ($this->setup_vars as $setup_var)
		{
			$vars = $this->varsObj($setup_var);
			// Toggle value
			if ($target == $vars->var_name.'_on')
				Configuration::updateValue($vars->glob_name, 0);
			if ($target == $vars->var_name.'_off')
				Configuration::updateValue($vars->glob_name, 1);
		}
	}

	public function updateCardsPosition()
	{
		$cards = Tools::getValue('cards');
		if ($cards)
			Configuration::updateValue('_QUICKPAY_ORDERING', implode(',', $cards));
	}

	/**
	 * Load the configuration form
	 */
	public function getContent()
	{
		if (isset($this->warning))
			return $this->displayError($this->warning);

		if (isset($this->back_file) && !file_exists($this->back_file))
		{
			$err = $this->l('This module requires the backward compatibility module.');
			if (!Module::isInstalled('backwardcompatibility'))
				$err .= ' '.$this->l('You can get the compatibility module for free from').
					' <a href="http://addons.prestashop.com">http://addons.prestashop.com</a>';
			$err .= '<br /><br />'.$this->l('You must configure the compatibilty module.');
			return $this->displayError($err);
		}

		if (Configuration::get('PS_SHOP_ENABLE'))
			$output = '';
		else
			$output = $this->displayError($this->l('The callback function does not work when the shop is in maintenance mode'));
		$row = Db::getInstance()->ExecuteS('SHOW TABLES LIKE
				"'._DB_PREFIX_.'quickpay_execution"');
		if (!$row) // Not installed properly
			$this->install();
		if ($this->v15)
		{
			if (!$this->isRegisteredInHook(Hook::getIdByName('paymentTop')))
				$this->registerHook('paymentTop');
			$this->context->controller->addJqueryUI('ui.sortable');
		}
		else
		{
			// Old PrestaShop
			require($this->local_path.'backward_compatibility/HelperForm.php');
			$output .= '<script type="text/javascript" src="'.
				$this->_path.'views/js/jquery-ui-1.9.0.custom.min.js"></script>';
		}
		$this->getSetup();
		$this->postProcess();
		$output .= $this->displayErrors();
		if (!$this->post_errors)
			$output .= '
				<div class="conf confirm">
				<img src="../img/admin/ok.gif" alt="'.$this->l('Confirmation').'" />
				'.$this->l('Settings updated').'
				</div>';

		$this->context->smarty->assign('module_dir', $this->_path);
		$this->context->smarty->clearCompiledTemplate(
				$this->local_path.'views/templates/hook/quickpay.tpl');

		$output .= $this->context->smarty->fetch(
				$this->local_path.'views/templates/admin/configure.tpl');

		$output .= $this->renderForm();
		$output .= $this->renderList();
		return $output;
	}

	protected function renderForm()
	{
		$helper = new HelperForm();

		if (empty($helper->context))
		{
			$helper->context = $this->context;
			$helper->local_path = $this->local_path;
		}
		$helper->show_toolbar = false;
		$helper->table = $this->table;
		$helper->module = $this;
		$helper->default_form_language = $this->context->language->id;
		$helper->allow_employee_form_lang =
			Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG');
		if (!$helper->allow_employee_form_lang) // For old 1.5 helper
			$helper->allow_employee_form_lang = 0;

		$helper->identifier = $this->identifier;
		$helper->submit_action = 'submitQuickPayModule';
		if ($this->v15)
		{
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
		}
		else
		{
			$helper->currentIndex = 'index.php?tab='.Tools::getValue('tab').
				'&configure='.Tools::getValue('configure').
				'&tab_module='.Tools::getValue('tab_module').
				'&module_name='.Tools::getValue('module_name');
			$helper->token = Tools::getValue('token');
			$helper->tpl_vars = array(
				'fields_value' => $this->getConfigFormValues(),
				'languages' => Language::getLanguages(),
				'id_language' => $this->context->language->id,
			);
		}

		$out = $helper->generateForm(array($this->getConfigSettings()));
		if (!$this->v16)
			$out .= '<br />';
		return $out;
	}

	protected function renderList()
	{
		$setup = $this->setup;
		$setup_vars = $this->sortSetup();
		$cards = array();
		foreach ($setup_vars as $setup_var)
		{
			$vars = $this->varsObj($setup_var);
			$field = $vars->var_name;
			if (!$vars->card_type_lock)
				continue;
			$card = array();
			$card['name'] = $vars->var_name;
			$card['image'] = $vars->var_name.'.png';
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

		if ($this->v16)
			return $this->display(__FILE__, 'list.tpl');
		elseif ($this->v15)
			return $this->display(__FILE__, 'list15.tpl');
		else
			return $this->display(__FILE__, 'views/templates/hook/list15.tpl');
	}

	protected function useCheckBox($vars)
	{
		return $vars->def_val !== '' &&
			$vars->var_name != 'orderprefix' &&
			$vars->var_name != 'statecapture' &&
			$vars->var_name != 'branding';
	}

	protected function getConfigInput15($vars)
	{
		if ($this->useCheckBox($vars))
		{
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
		}
		else
		{
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
		if (!$this->v16)
			return $this->getConfigInput15($vars);
		if ($this->useCheckBox($vars))
		{
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
		}
		else
		{
			$input = array(
					'col' => strpos($vars->var_name, '_key') === false ? 3 : 6,
					'type' => 'text',
					'name' => $vars->glob_name,
					'label' => $vars->card_text
					);
		}
		return $input;
	}

	protected function getStatesInput($vars)
	{
		$order_states = OrderState::getOrderStates($this->context->language->id);
		$query = array();
		$query[] = array('id' => 0,  'name' => $this->l('Never'));
		foreach ($order_states as $order_state)
			$query[] = array(
					'id' => $order_state['id_order_state'],
					'name' => $order_state['name']
					);
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

	protected function getBrandingInput($vars)
	{
		$brandings = $this->getBrandings();
		if ($brandings === false)
			$brandings = array();
		$query = array();
		$query[] = array('id' => 0,  'name' => $this->l('Standard'));
		foreach ($brandings as $id => $name)
			$query[] = array(
					'id' => $id,
					'name' => $name
					);
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
		foreach ($this->setup_vars as $setup_var)
		{
			$vars = $this->varsObj($setup_var);
			if ($vars->card_type_lock)
				continue;
			if ($vars->var_name == 'statecapture')
			{
				$inputs[] = $this->getStatesInput($vars);
				continue;
			}
			if ($vars->var_name == 'branding')
			{
				$inputs[] = $this->getBrandingInput($vars);
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
		foreach ($this->setup_vars as $setup_var)
		{
			$vars = $this->varsObj($setup_var);
			$field = $vars->var_name;
			if ($this->useCheckBox($vars))
				$values[$vars->glob_name] = $setup->$field ? 1 : 0;
			else
				$values[$vars->glob_name] = $setup->$field;
		}
		return $values;
	}

	public function displayErrors()
	{
		$out = '';
		foreach ($this->post_errors as $err)
			$out .= $this->displayError($err);
		return $out;
	}

	protected function postProcess()
	{
		if (Tools::getValue('action') == 'changeState')
		{
			$this->changeState();
			exit;
		}
		if (Tools::getValue('action') == 'updateCardsPosition')
		{
			$this->updateCardsPosition();
			exit;
		}
		if (Tools::getValue('submitQuickPayModule'))
		{
			foreach ($this->setup_vars as $setup_var)
			{
				$vars = $this->varsObj($setup_var);
				if ($vars->card_type_lock)
					continue;
				if (Tools::getValue($vars->glob_name, null) !== null)
					Configuration::updateValue($vars->glob_name,
							Tools::getValue($vars->glob_name));
			}
			// Read the new setup
			$setup = $this->getSetup();
			if (!$setup->merchant_id)
				$this->post_errors[] = $this->l('Merchant ID is required.');
			if (Tools::strlen($setup->orderprefix) != 3)
				$this->post_errors[] =
					$this->l('Order prefix must be exactly 3 characters long.');
			$data = $this->doCurl('payments', array(), 'POST');
			$vars = $this->jsonDecode($data);
			if ($vars->message == 'Invalid API key')
			{
					$this->post_errors[] = $this->l('Invalid Quickpay user key. Check the key at').
						' <a href="https://manage.quickpay.net">https://manage.quickpay.net</a>.';
			}
			elseif ($setup->autofee)
			{
				$fees = $this->getFees(100);
				if (!$fees)
				{
					$this->post_errors[] = $this->l('Could not access fees via user key. Check access rights in').
						' <a href="https://manage.quickpay.net">https://manage.quickpay.net</a>.';
				}
			}
			$data = $this->doCurl('payments', array('order_id=0'), 'GET');
			$vars = $this->jsonDecode($data);
			if ($vars && Tools::substr($vars->message, 0, 14) == 'Not authorized')
			{
					$this->post_errors[] = $this->l('Could not access payments via user key. Check access rights in').
						' <a href="https://manage.quickpay.net">https://manage.quickpay.net</a>.';
			}
		}
	}

	public function jsonDecode($data)
	{
		if ($this->v15)
			return Tools::jsonDecode($data);
		else
			return call_user_func('json_decode', $data);
	}

	public function getCurlHandle($resource, $fields = null, $method = null)
	{
		$ch = curl_init();
		$header = array();
		$header[] = 'Authorization: Basic '.
			call_user_func('base64_encode', ':'.$this->setup->user_key);
		$header[] = 'Accept-Version: v10';
		$url = 'https://api.quickpay.net/'.$resource;
		if ($method == null)
		{
			if ($fields)
				$method = 'POST';
			else
				$method = 'GET';
		}
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
		if ($fields)
		{
			curl_setopt($ch, CURLOPT_POST, count($fields));
			curl_setopt($ch, CURLOPT_POSTFIELDS, implode('&', $fields));
		}
		return $ch;
	}

	public function doCurl($resource, $fields = null, $method = null)
	{
		$ch = $this->getCurlHandle($resource, $fields, $method);
		$data = curl_exec($ch);
		if (!$data)
			$this->qpError = curl_error($ch);
		curl_close($ch);
		return $data;
	}

	public function getFees($amount)
	{
		$setup = $this->setup;
		$data = $this->doCurl('fees/formulas');
		$vars = $this->jsonDecode($data);
		if (!is_array($vars))
			return false;
		$fields = array('amount='.$amount);
		$chs = array();
		$mh = curl_multi_init();
		// curl_multi_setopt($mh, CURLMOPT_PIPELINING, 1);
		foreach ($vars as $var)
		{
			$url = 'fees/'.$var->acquirer.'/'.$var->payment_method;
			$ch = $this->getCurlHandle($url, $fields);
			curl_multi_add_handle($mh, $ch);
			$chs[$var->acquirer][$var->payment_method] = $ch;
		}
		while (true)
		{
			curl_multi_exec($mh, $running);
			curl_multi_select($mh);
			if ($running == 0)
				break;
		}
		$fees = array();
		foreach ($vars as $var)
		{
			$ch = $chs[$var->acquirer][$var->payment_method];
			curl_multi_remove_handle($mh, $ch);
			$data = curl_multi_getcontent($ch);
			$row = $this->jsonDecode($data);
			if (empty($row->payment_method))
				continue;
			if (isset($setup->lock_names[$row->payment_method]))
				$lock_name = $setup->lock_names[$row->payment_method];
			elseif (isset($setup->lock_names['3d-'.$row->payment_method]))
				$lock_name = $row->payment_method; // Maestro
			else
				continue;
			if (isset($fees[$lock_name]))
			{
				if ($row->fee < $fees[$lock_name])
				{
					$fees[$lock_name.'_f'] = $fees[$lock_name];
					$fees[$lock_name.'_3d_f'] = $fees[$lock_name.'_3d'];
					$fees[$lock_name] = $row->fee;
					$fees[$lock_name.'_3d'] = $row->fee;
				}
				if ($row->fee > $fees[$lock_name])
				{
					$fees[$lock_name.'_f'] = $row->fee;
					$fees[$lock_name.'_3d_f'] = $row->fee;
				}
			}
			else
			{
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
		if (!$data)
			return false;
		$vars = $this->jsonDecode($data);
		if (!$vars || isset($vars->errors) || isset($vars->message))
			return false;
		foreach ($vars as $var)
			$brandings[$var->id] = $var->name;
		return $brandings;
	}

	public function getOrderingList($setup, $setup_vars)
	{
		$ordering_list = array();
		$secure_list = array();
		foreach ($setup_vars as $setup_var)
		{
			$vars = $this->varsObj($setup_var);
			$field = $vars->var_name;
			if (!$vars->card_type_lock || !$setup->$field)
				continue;
			$var_name = $vars->var_name;
			switch ($vars->var_name)
			{
				case 'mastercarddebet':
					$var_name = 'mastercard';
					break;
				case 'visa_3d':
				case 'visaelectron_3d':
					if (!in_array('visa_3d', $secure_list))
						$secure_list[] = 'visa_3d';
					$var_name = Tools::substr($var_name, 0, -3);
					break;
				case 'mastercard_3d':
				case 'maestro_3d':
					if (!in_array('mastercard_3d', $secure_list))
						$secure_list[] = 'mastercard_3d';
					$var_name = Tools::substr($var_name, 0, -3);
					break;
				case 'mastercarddebet_3d':
					if (!in_array('mastercard_3d', $secure_list))
						$secure_list[] = 'mastercard_3d';
					$var_name = 'mastercard';
					break;
				default:
					if ($secure_list)
					{
						foreach ($secure_list as $sec_name)
							if (!in_array($sec_name, $ordering_list))
								$ordering_list[] = $sec_name;
					}
					break;
			}
			if (!in_array($var_name, $ordering_list))
				$ordering_list[] = $var_name;
		}
		if ($secure_list)
		{
			foreach ($secure_list as $sec_name)
				if (!in_array($sec_name, $ordering_list))
					$ordering_list[] = $sec_name;
		}
		return $ordering_list;
	}

	public function getDecimals($iso_code)
	{
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
		if (isset($decimals[$iso_code]))
			return $decimals[$iso_code];
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
		if ($use_comma)
		{
			$amount = str_replace('.', '', $amount);
			$amount = str_replace(',', '.', $amount);
		}
		else
			$amount = str_replace(',', '', $amount);
		return $amount;
	}

	public function toUserAmount($amount, $currency)
	{
		$use_comma = strpos(Tools::displayPrice(1.23, $currency), ',') !== false;
		if ($use_comma)
			$amount = str_replace('.', ',', $amount);
		return $amount;
	}

	public function hookPaymentTop()
	{
		if ($this->v16)
			$this->context->controller->addCSS($this->_path.'/views/css/front.css');
		else
			$this->context->controller->addCSS($this->_path.'/views/css/front15.css');
	}

	public function hookPayment($params)
	{
		$setup = $this->getSetup();
		$smarty = $this->context->smarty;
		$cart = $params['cart'];
		$invoice_address = new Address((int)$cart->id_address_invoice);
		$country = new Country($invoice_address->id_country);
		$delivery_address = new Address((int)$cart->id_address_delivery);
		$customer = new Customer((int)$cart->id_customer);
		$id_currency = (int)$cart->id_currency;
		$currency = new Currency((int)$id_currency);

		$language = new Language($this->context->language->id);
		$decimals = $this->getDecimals($currency->iso_code);
		$cart_total = number_format($cart->getOrderTotal(), $decimals, '', '');
		$cart_total_no_vat = number_format($cart->getOrderTotal(false), $decimals, '', '');
		$tax_total = $cart_total - $cart_total_no_vat;
		if (!defined('QUICKPAY_COMPLETE'))
			define('QUICKPAY_COMPLETE', 'complete');
		$continueurl = $this->getModuleLink(
				QUICKPAY_COMPLETE,
				array(
					'key' => $customer->secure_key,
					'id_cart' => (int)$cart->id,
					'id_module' => (int)$this->id
				)
			);
		$cancelurl = $this->getPageLink('order', 'step=3');
		$callbackurl = $this->getModuleLink('validation');
		$payment_url = $this->getModuleLink('payment');
		$msgtype = 'authorize';
		$protocol = '7';
		$splitpayment = '1';
		$description = '';
		$html = '';

		if ($setup->autofee)
			$fees = $this->getFees($cart_total);
		else
			$fees = false;

		$order_id = $setup->orderprefix.(int)$cart->id;
		$done = false;
		$done3d = false;
		$setup_vars = $this->sortSetup();
		foreach ($setup_vars as $setup_var)
		{
			$vars = $this->varsObj($setup_var);
			$card_list = array($vars->var_name);
			$card_text = $vars->card_text;
			$field = $vars->var_name;
			if (!$vars->card_type_lock || !$setup->$field)
				continue;
			$card_type_lock = $vars->card_type_lock;
			if ($setup->combine && isset($setup->credit_cards[$vars->var_name]))
			{
				// Group these cards
				if ($done)
					continue;
				// $card_text = implode(' / ', $setup->credit_cards);
				$card_text = $this->l('credit card');
				$card_list = array_keys($setup->credit_cards);
				$card_type_lock = implode(',', $setup->card_type_locks);
				$done = true;
			}
			if ($setup->combine && isset($setup->credit_cards3d[$vars->var_name]))
			{
				if ($done3d)
					continue;
				// $card_text = implode(' / ', $setup->credit_cards3d);
				$card_text = $this->l('credit card');
				$card_list = array_merge(array_keys($setup->credit_cards2di),
						array_keys($setup->credit_cards3di));
				$card_type_lock = implode(',', $setup->card_type_locks3d);
				$done3d = true;
			}
			if (!$setup->combine)
			{
				switch ($vars->var_name)
				{
					case 'visa_3d':
					case 'visaelectron_3d':
						$card_list[] = 'visa_secure';
						break;
					case 'mastercard_3d':
					case 'maestro_3d':
					case 'mastercarddebet_3d':
						$card_list[] = 'mastercard_secure';
						break;
				}
			}
			if ($vars->var_name == 'mobilepay' && $country->iso_code != 'DK')
					continue;
			if ($vars->var_name == 'viabill')
			{
				// Autofee does not work
				$amount = $cart_total;
				$autofee = 0;
				if ($country->iso_code != 'DK')
					continue;
			}
			else
			{
				$amount = $cart_total;
				$autofee = $setup->autofee;
			}
			$smarty->assign(array(
						'amount' => $amount,
						'autofee' => $autofee,
						'vat_amount' => $tax_total,
						'shopName' => Configuration::get('PS_SHOP_NAME')
						));
			$fee_texts = array();
			if ($card_list)
			{
				foreach ($card_list as $card_name)
				{
					if ($setup->combine &&
							($card_name == 'visa_3d' || $card_name == 'mastercard_3d'))
						continue;
					if (!empty($fees[$card_name]))
					{
						$fee_text = array();
						if ($card_name == 'viabill')
							$fee_text['name'] = $this->l('Fee for').
								' '.$this->l('ViaBill').":\xC2\xA0";
						else
							$fee_text['name'] = $this->l('Fee for').
								' '.$setup->card_texts[$card_name].":\xC2\xA0";
						$fee_text['amount'] =
							$this->displayQpAmount($fees[$card_name], $currency);
						if (!empty($fees[$card_name.'_f']))
							$fee_text['amount'] .= sprintf(' (%s: %s)',
									$this->l('foreign'),
									$this->displayQpAmount($fees[$card_name.'_f'], $currency));
						$fee_texts[] = $fee_text;
						if ($card_name == 'dk')
						{
							$fee_texts = array();
							$fee_text['name'] = $this->l('Fee for').
								' '.$this->l('Visadankort').":\xC2\xA0";
							$fee_text['amount'] =
								$this->displayQpAmount($fees[$card_name], $currency);
							$fee_texts[] = $fee_text;
						}
					}
				}
			}
			$smarty->assign('fees', $fee_texts);
			$branding = $setup->branding ? $setup->branding : '';
			$csum_fields = array(
					'amount'                       => $amount,
					'autocapture'                  => $setup->autocapture,
					'autofee'                      => $setup->autofee,
					'branding_id'                  => $branding,
					'callbackurl'                  => $callbackurl,
					'cancelurl'                    => $cancelurl,
					'category'                     => 'SC21',
					'continueurl'                  => $continueurl,
					'currency'                     => $currency->iso_code,
					'description'                  => $description,
					'google_analytics_client_id'   => '',
					'google_analytics_tracking_id' => '',
					'language'                     => $language->iso_code,
					'merchant_id'                  => $setup->merchant_id,
					'order_id'                     => $order_id,
					'payment_methods'              => $card_type_lock,
					'product_id'                   => 'P03',
					'reference_title'              => Configuration::get('PS_SHOP_NAME'),
					'subscription'                 => '',
					'vat_amount'                   => $tax_total,
					'version'                      => 'v10'
						);
			$smarty_fields = array();
			foreach ($csum_fields as $key => $value)
			{
				$smarty_fields[] = array(
						'name' => $key,
						'value' => $value
						);
			}
			$fields = array(
					'fields'           => $smarty_fields,
					'payment_url'      => $payment_url,
					'invoice_address'  => $invoice_address,
					'delivery_address' => $delivery_address,
					'customer'         => $customer,
					'protocol'         => $protocol,
					'msgtype'          => $msgtype,
					'splitpayment'     => $splitpayment,
					'uri'              => $_SERVER['REQUEST_URI'],
					'imgs'             => $card_list,
					'text'             => $this->l('Pay with').' '.$card_text,
					'type'             => $vars->var_name
					);
			$smarty->assign($fields);
			$html .= $this->display(__FILE__, 'views/templates/hook/quickpay.tpl');
		}

		return $html;
	}

	public function hookLeftColumn()
	{
		$smarty = $this->context->smarty;

		$setup = $this->getSetup();
		$setup_vars = $this->sortSetup();
		if ($setup->showcards)
			$ordering_list = $this->getOrderingList($setup, $setup_vars);
		else
			$ordering_list = array();

		if ($ordering_list)
		{
			$smarty->assign(
					array(
						'uri' => $_SERVER['REQUEST_URI'],
						'ordering_list' => $ordering_list,
						'showcards' => $setup->showcards
						)
					);
			return $this->display(__FILE__, 'views/templates/hook/leftquickpay.tpl');
		}
		return '';
	}

	public function hookRightColumn()
	{
		return $this->hookLeftColumn();
	}

	public function hookFooter()
	{
		$smarty = $this->context->smarty;

		$setup = $this->getSetup();
		if (!$setup->showcardsfooter)
			return;

		$setup_vars = $this->sortSetup();
		$ordering_list = $this->getOrderingList($setup, $setup_vars);

		if ($ordering_list)
		{
			$smarty->assign(
					array(
						'uri' => $_SERVER['REQUEST_URI'],
						'ordering_list' => $ordering_list,
						'showcardsfooter' => $setup->showcardsfooter
						)
					);
			return $this->display(__FILE__, 'views/templates/hook/footerquickpay.tpl');
		}
		return '';
	}

	public function getBrand($vars)
	{
		if (empty($this->setup_vars))
			$this->getSetup();
		$brand = $vars->metadata->brand;
		if (!$brand)
			$brand = $vars->acquirer;
		foreach ($this->setup_vars as $setup_var)
		{
			$vars = $this->varsObj($setup_var);
			$card_type_locks = explode(',', $vars->card_type_lock);
			if (in_array($brand, $card_type_locks))
			{
				$text = explode(' ', $vars->card_text);
				$brand = $text[0];
				break;
			}
		}
		return $brand;
	}

	public function hookPaymentReturn($params)
	{
		if (!$this->active)
			return;

		$order = $params['objOrder'];
		$state = $order->getCurrentState();
		if ($state == _PS_OS_ERROR_)
		{
			$status = 'callback';
			$msg = 'QuickPay: Confirmation failed';
			Logger::addLog($msg, 2, 0, 'Order', $order->id);
		}
		else
			$status = 'ok';
		$this->smarty->assign('status', $status);
		return $this->display(__FILE__, 'views/templates/hook/confirmation.tpl');
	}

	public function hookAdminOrder($params)
	{
		$setup = $this->getSetup();
		if (!$setup->api)
			return '';

		$order = new Order((int)$params['id_order']);
		$currency = new Currency((int)$order->id_currency);
		$amountex = explode('.', $order->total_paid);
		$amount = $amountex[0].$amountex[1];
		$trans = Db::getInstance()->getRow('SELECT *
				FROM '._DB_PREFIX_.'quickpay_execution
				WHERE `id_cart` = '.$order->id_cart.'
				ORDER BY `exec_id` ASC');
		if (!$trans)
			return '';
		$order_id = $trans['order_id'];
		$module = Db::getInstance()->getRow('SELECT `module`
				FROM '._DB_PREFIX_.'orders
				WHERE `id_order` = '.(int)$params['id_order']);
		if ($module['module'] != 'quickpay')
			return '';
		if ($this->v16)
			$html = '<div class="col-lg-5"><div class="panel">
				<h3><img src="'.$this->_path.'logo.gif" />
				'.$this->l('Quickpay API').'</h3>';
		else
			$html = '<br />
				<fieldset>
				<legend>'.$this->l('Quickpay API').'</legend>';

		if (!Configuration::get('PS_SHOP_ENABLE'))
			$html .= '<p class="error alert-danger">'.$this->l('The callback function does not work when the shop is in maintenance mode').'</p>';

		$double_post = false;
		$status_data = $this->doCurl('payments/'.$trans['trans_id']);
		if (!empty($status_data))
		{
			$vars = $this->jsonDecode($status_data);
			if (!empty($vars->id) &&
					Tools::getValue('qp_count') < count($vars->operations))
				$double_post = true;
		}

		if (!$double_post && Tools::isSubmit('qpcapture'))
		{
			$amount = Tools::getValue('acramount');
			$amount = $this->fromUserAmount($amount, $currency);
			$amount = $this->toQpAmount($amount, $currency);
			$fields = array('amount='.$amount);
			$action_data = $this->doCurl('payments/'.$trans['trans_id'].'/capture', $fields);
			// $html .= '<pre>'.print_r(json_decode($action_data), true).'</pre>';
		}

		if (!$double_post && Tools::isSubmit('qprefund'))
		{
			$amount = Tools::getValue('acramountref');
			$amount = $this->fromUserAmount($amount, $currency);
			$amount = $this->toQpAmount($amount, $currency);
			$fields = array('amount='.$amount);
			$action_data = $this->doCurl('payments/'.$trans['trans_id'].'/refund', $fields);
			// $html .= '<pre>'.print_r(json_decode($action_data), true).'</pre>';
		}

		if (!$double_post && Tools::isSubmit('qpcancel'))
		{
			$action_data = $this->doCurl('payments/'.$trans['trans_id'].'/cancel', null, 'POST');
			// $html .= '<pre>'.print_r($action_data, true).'</pre>';
			// $html .= '<pre>'.print_r(json_decode($action_data), true).'</pre>';
		}

		if (isset($action_data))
		{
			$vars = $this->jsonDecode($action_data);
			if (isset($vars) && isset($vars->errors))
			{
				if ($vars->errors->amount[0] == 'is too large')
					$html .= '<p class="error alert-danger">'.$this->l('Amount is too large').'</p>';
				else
					$html .= '<pre>'.print_r($this->jsonDecode($action_data), true).'</pre>';
			}
			elseif (isset($vars) && isset($vars->message))
				$html .= '<p class="error alert-danger">'.$vars->message.'</p>';
		}

		// Get status reply from quickpay
		$status_data = $this->doCurl('payments/'.$trans['trans_id']);
		if (empty($status_data))
		{
			$html .= '<pre>'.$this->curl_error.'</pre>';
			if ($this->v16)
				$html .= '</div></div>';
			else
				$html .= '</fieldset>';
			return $html;
		}
		$vars = $this->jsonDecode($status_data);
		if (empty($vars->id))
		{
			$html .= '<pre>'.$vars->message.'</pre>';
			if ($this->v16)
				$html .= '</div></div>';
			else
				$html .= '</fieldset>';
			return $html;
		}

		$html .= '<table>';
		$html .= '<tbody>';
		$html .= '<tr><th style="padding-right:10px">';
		$html .= $this->l('Quickpay order ID:');
		$html .= '</th><td>';
		$html .= $order_id;
		if ($vars->test_mode)
			$html .= ' ['.$this->l('test mode').']';
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
		if ($vars->metadata->is_3d_secure)
			$html .= ' '.$this->l('[3D secure]');
		$html .= '</td></tr>';

		$html .= '<tr><th>';
		$html .= $this->l('Country:');
		$html .= '</th><td>';
		$html .= $vars->metadata->country;
		$html .= '</td></tr>';

		$html .= '<tr><th>';
		$html .= $this->l('Created:');
		$html .= '</th><td>';
		$html .= Tools::displayDate(date('Y-m-d H:i:s',
					strtotime($vars->created_at)), null, true);
		$html .= '</td></tr>';

		if ($vars->metadata->fraud_suspected)
		{
			$html .= '<tr><th>';
			$html .= $this->l('Fraud:');
			$html .= '</th><td>';
			$html .= implode('</td></tr><tr><td></td><td>',
					$vars->metadata->fraud_remarks);
			$html .= '</td></tr>';
		}

		$html .= '</tbody>';
		$html .= '</table><br />';

		if (Tools::getValue('qpDebug'))
			$html .= '<pre>'.print_r($this->jsonDecode($status_data), true).'</pre>';
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
		foreach ($vars->operations as $operation)
		{
			$html .= '<tr><td>';
			$html .= Tools::displayDate(date('Y-m-d H:i:s',
						strtotime($operation->created_at)), null, true);
			$html .= '</td><td>';
			switch ($operation->type)
			{
				case 'capture':
					$resttoref += $operation->amount;
					$allowcancel = false;
					$html .= $this->l('Captured');
					break;
				case 'authorize':
					if ($operation->aq_status_code == 202)
					{
						$resttocap = 0;
						$html .= $this->l('Waiting for approval');
					}
					else
					{
						$resttocap += $operation->amount;
						$html .= $this->l('Authorized');
					}
					break;
				case 'refund':
					$resttoref -= $operation->amount;
					$resttocap -= $operation->amount;
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
			if ($operation->qp_status_code != '20000')
				$html .= ' ['.$this->l('Not approved!').']';
			$html .= '</td><td style="text-align:right">';
			$html .= ' '.$this->displayQpAmount($operation->amount, $currency);
			$html .= '</td></tr>';
		}
		if ($resttocap < 0)
			$resttocap = 0;
		$resttocap = $this->fromQpAmount($resttocap, $currency);
		if ($resttocap > $order->total_paid)
			$resttocap = $order->total_paid;
		if ($resttoref < 0)
			$resttoref = 0;
		$resttoref = $this->fromQpAmount($resttoref, $currency);
		$html .= '</tbody>';
		$html .= '</table>';

		if ($this->v15)
		{
			$url = 'index.php?controller='.Tools::getValue('controller');
			$url .= '&id_order='.Tools::getValue('id_order');
			$url .= '&vieworder&token='.Tools::getValue('token');
		}
		else
		{
			$url = 'index.php?tab='.Tools::getValue('tab');
			$url .= '&id_order='.Tools::getValue('id_order');
			$url .= '&vieworder&token='.Tools::getValue('token');
		}
		$html .= '<br /><br />';
		if ($resttocap > 0)
		{
			$resttocap = $this->toUserAmount($resttocap, $currency);
			$html .= '<form action="'.$url.'" method="post" name="capture-cancel">';
			$html .= '<input type="hidden" name="qp_count" value="'.$qp_count.'" />';
			$html .= '<b>'.$this->l('Amount to capture:').'</b>';
			$html .= '<div><input style="width:auto;display:inline" type="text" name="acramount" value="'.$resttocap.'"/>
				<input type="submit" class="button" name="qpcapture" value="'.
				$this->l('Capture').'" onclick="return confirm(\''.$this->l('Are you sure you want to capture the amount?').'\')"/></div><br />';
			$html .= '</form>';
		}
		if ($resttoref > 0)
		{
			$resttoref = $this->toUserAmount($resttoref, $currency);
			$html .= '<form action="'.$url.'" method="post" name="capture-cancel">';
			$html .= '<input type="hidden" name="qp_count" value="'.$qp_count.'" />';
			$html .= '<b>'.$this->l('Amount to refund').' ('.$resttoref.'):</b>';
			$html .= '<div><input style="width:auto;display:inline" type="text" name="acramountref" id="acramountref" value="" />
				<input type="submit" class="button" name="qprefund" value="'.
				$this->l('Refund').'" onclick="return confirm(\''.$this->l('Are you sure you want to refund the amount?').'\');"/></div><br />';
			$html .= '</form>';
		}
		if ($allowcancel)
		{
			$html .= '<form action="'.$url.'" method="post" name="capture-cancel">';
			$html .= '<input type="hidden" name="qp_count" value="'.$qp_count.'" />';
			$html .= '<input type="submit" name="qpcancel" value="';
			$html .= $this->l('Cancel the transaction!');
			$html .= '" class="button" onclick="return confirm(\'';
			$html .= $this->l('Are you sure you want cancel the transaction?').'\')"/></center>';
			$html .= '</form><br />';
		}
		$html .= '<a href="https://manage.quickpay.net" target="_blank" style="color: blue;">'.$this->l('Quickpay manager').'</a>';
		if ($this->v16)
			$html .= '</div></div>';
		else
			$html .= '</fieldset>';
		return $html;
	}


	public function hookPDFInvoice($params)
	{
		if ($this->v15)
		{
			$object = $params['object'];
			$order = new Order((int)$object->id_order);
		}
		else
		{
			$pdf = $params['pdf'];
			$order = new Order($params['id_order']);
		}
		$trans = Db::getInstance()->getRow('SELECT *
				FROM '._DB_PREFIX_.'quickpay_execution
				WHERE `id_cart` = '.$order->id_cart.'
				ORDER BY `exec_id` ASC');
		if (isset($trans['trans_id']))
		{
			// $brand = $this->metadata->brand;
			$vars = $this->jsonDecode($trans['json']);
			if ($this->v15)
			{
				$html = '<table><tr>';
				$html .= '<td style="width:100%; text-align:right">Quickpay transaction ID: '.$trans['trans_id'].'</td>';
				$html .= '</tr></table>';
				if ($vars->acquirer == 'viabill')
				{
					$html .= '<br/>';
					$html .= 'Det skyldige beløb kan alene betales med frigørende virkning til ViaBill, som fremsender særskilt opkrævning.';
					$html .= '<br/>';
					$html .= 'Betaling kan ikke ske ved modregning af krav, der udspringer af andre retsforhold.';
				}
				return $html;
			}
			else
			{
				if ($this->v14)
					$encoding = $pdf->encoding();
				else
					$encoding = 'iso-8859-1';
				$old_str = Tools::iconv('utf-8', $encoding, $order->payment);
				$new_str = Tools::iconv('utf-8', $encoding,
						$order->payment.' TransID: '.$trans['trans_id']);
				$pdf->pages[1] = str_replace($old_str, $new_str, $pdf->pages[1]);
				if ($vars->acquirer == 'viabill')
				{
					$pdf->Ln(14);
					$width = 165;
					$txt = Tools::iconv('utf-8', $encoding,
							'Det skyldige beløb kan alene betales med frigørende virkning til ViaBill, som fremsender særskilt opkrævning.');
					$pdf->Cell($width, 3, $txt, 0, 2, 'L');
					$txt = Tools::iconv('utf-8', $encoding,
							'Betaling kan ikke ske ved modregning af krav, der udspringer af andre retsforhold.');
					$pdf->Cell($width, 3, $txt, 0, 2, 'L');
				}
			}
		}
	}

	public function hookPostUpdateOrderStatus($params)
	{
		$this->getSetup();
		$new_state = $params['newOrderStatus'];
		$order = new Order($params['id_order']);
		$capture_statue = Configuration::get('_QUICKPAY_STATECAPTURE');
		if ($capture_statue == $new_state->id)
		{
			$trans = Db::getInstance()->getRow('SELECT *
					FROM '._DB_PREFIX_.'quickpay_execution
					WHERE `id_cart` = '.$order->id_cart.'
					ORDER BY `exec_id` ASC');
			if ($trans)
			{
				$vars = $this->jsonDecode($trans['json']);
				if (isset($vars->operations))
				{
					$amountex = explode('.', $order->total_paid);
					$amount_order = $amountex[0].$amountex[1];
					$amount = $vars->operations[0]->amount;
					if ($amount > $amount_order)
						$amount = $amount_order;
					$fields = array('amount='.$amount);
					$this->doCurl('payments/'.$trans['trans_id'].'/capture', $fields);
				}
			}
		}
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
		$this->getSetup();
		$fields = array();
		foreach ($_POST as $k => $v)
			if ($v != '')
				$fields[] = $k.'='.urlencode($v);
		$order_id = Tools::getValue('order_id');
		$id_cart = (int)Tools::substr($order_id, 3);
		$cart = new Cart($id_cart);
		if (!Validate::isLoadedObject($cart))
		{
			$msg = 'QuickPay: Payment error. Not a valid cart';
			Logger::addLog($msg, 2, 0, 'Cart', $id_cart);
			die('Not a valid cart');
		}
		$customer = new Customer((int)$cart->id_customer);
		$currency = new Currency((int)$cart->id_currency);
		Db::getInstance()->Execute('DELETE
				FROM '._DB_PREFIX_.'quickpay_execution
				WHERE `id_cart` = '.$id_cart);
		$json = $this->doCurl('payments', $fields);
		$vars = $saved_vars = $this->jsonDecode($json);
		if (empty($vars->id))
		{
			// Payment already exists
			$json = $this->doCurl('payments', array('order_id='.$order_id), 'GET');
			$vars = $this->jsonDecode($json);
			if (isset($vars->message))
			{
				if (isset($saved_vars->message))
				{
					$msg = 'QuickPay: Payment error: '.$saved_vars->message;
					Logger::addLog($msg, 2, 0, 'Cart', $id_cart);
					die($saved_vars->message);
				}
				else
				{
					$msg = 'QuickPay: Payment error: '.$vars->message;
					Logger::addLog($msg, 2, 0, 'Cart', $id_cart);
					die($vars->message);
				}
			}
			$vars = $vars[0];
			if (empty($vars->id))
			{
				if (empty($this->qpError))
					$msg = 'QuickPay: Payment error: '.$saved_vars->message;
				else
					$msg = 'QuickPay: cURL error: '.$this->qpError;
				Logger::addLog($msg, 2, 0, 'Cart', $id_cart);
				die($msg);
			}
		}
		$values = array($id_cart, $vars->id, $vars->order_id, 0, 0, pSql($json));
		Db::getInstance()->Execute(
				'INSERT INTO '._DB_PREFIX_.'quickpay_execution
				(`id_cart`, `trans_id`, `order_id`, `accepted`, `test_mode`, `json`)
				VALUES '.$this->group($values));
		if ($vars->accepted)
		{
			// Already paid
			$msg = 'QuickPay: Payment notice: Already paid';
			Logger::addLog($msg, 2, 0, 'Cart', $id_cart);
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
		if ($currency->iso_code != $vars->currency)
		{
			/*
			$fields = array('currency' => $currency->iso_code);
			$res = $this->doCurl('payments/'.$vars->id, $fields, 'PATCH');
			*/
			$msg = sprintf(
					'QuickPay: Payment error: Currency was changed from %s to %s',
					$vars->currency,
					$currency->iso_code
				);
			Logger::addLog($msg, 2, 0, 'Cart', $id_cart);
			$cart->delete();
			$fail_url = $this->getModuleLink('fail', array('status' => 'currency'));
			Tools::redirect($fail_url, '');
			return;
		}
		$json = $this->doCurl('payments/'.$vars->id.'/link', $fields, 'PUT');
		$vars = $this->jsonDecode($json);
		if (isset($vars->message))
		{
			$msg = 'QuickPay: Payment error: '.$vars->message;
			Logger::addLog($msg, 2, 0, 'Cart', $id_cart);
			die($vars->message);
		}
		Tools::redirect($vars->url, '');
	}

	public function validate($json, $checksum, $id_order_state = _PS_OS_PAYMENT_)
	{
		$this->getSetup();
		if ($checksum != $this->sign($json, $this->setup->private_key))
		{
			$msg = 'QuickPay: Validate error. Checksum failed. Check private key in configuration';
			Logger::addLog($msg, 2);
			die('Checksum failed');
		}

		$vars = $this->jsonDecode($json);
		if ($this->v16)
			$brand = $this->getBrand($vars);
		else
			$brand = $this->displayName;
		$accepted = $vars->accepted ? 1 : 0;
		$test_mode = $vars->test_mode ? 1 : 0;
		$id_cart = (int)Tools::substr($vars->order_id, 3);
		$cart = new Cart($id_cart);
		if ($test_mode && !$this->setup->testmode)
		{
			$cart->delete();
			$msg = 'QuickPay: Validate error. Will not accept test payment!';
			Logger::addLog($msg, 2, 0, 'Cart', $id_cart);
			if ($id_order_state == _PS_OS_ERROR_)
			{
				$fail_url = $this->getModuleLink('fail', array('status' => 'test'));
				Tools::redirect($fail_url, '');
			}
			die('Will not accept test payment!');
		}
		if (!Validate::isLoadedObject($cart))
		{
			$msg = 'QuickPay: Validate error. Not a valid cart';
			Logger::addLog($msg, 2, 0, 'Cart', $id_cart);
			die('Not a valid cart');
		}
		if ($cart->OrderExists() != 0)
		{
			$msg = 'QuickPay: Validate error. Order already exists';
			Logger::addLog($msg, 2, 0, 'Cart', $id_cart);
			die('Order already exists');
		}
		if ($this->v15)
		{
			Shop::setContext(Shop::CONTEXT_SHOP, $cart->id_shop);
			$customer = new Customer((int)$cart->id_customer);
			Context::getContext()->customer = $customer;
			$currency = new Currency((int)$cart->id_currency);
			Context::getContext()->currency = $currency;
		}
		$trans = Db::getInstance()->getRow('SELECT *
				FROM '._DB_PREFIX_.'quickpay_execution
				WHERE `id_cart` = '.$id_cart.'
				ORDER BY `exec_id` ASC');
		if ($trans['accepted'])
		{
			$msg = 'QuickPay: Validate error. Order already accepted';
			Logger::addLog($msg, 2, 0, 'Cart', $id_cart);
			die('Order already accepted');
		}
		Db::getInstance()->Execute('DELETE
				FROM '._DB_PREFIX_.'quickpay_execution
				WHERE `id_cart` = '.$cart->id);
		$values = array(
				$cart->id, $vars->id, $vars->order_id, $accepted,
				$test_mode, pSql($json));
		Db::getInstance()->Execute(
				'INSERT INTO '._DB_PREFIX_.'quickpay_execution
				(`id_cart`, `trans_id`, `order_id`, `accepted`, `test_mode`, `json`)
				VALUES '.$this->group($values));
		if ($accepted && isset($vars->operations[0]))
		{
			$decimals = $this->getDecimals($vars->currency);
			$amount = number_format(
					$vars->operations[0]->amount / pow(10, $decimals),
					$decimals,
					'.',
					''
				);
			$extra_vars = array('transaction_id' => $vars->id,
					'cardtype' => $vars->metadata->brand);
			if ($this->setup->autofee && isset($vars->operations))
				$this->addFee($cart, $amount);
			if (!$this->validateOrder($cart->id, $id_order_state, $amount,
						$brand, null, $extra_vars, null, false,
						$cart->secure_key))
			{
				$msg = 'QuickPay: Validate error. Unable to process order';
				Logger::addLog($msg, 2, 0, 'Cart', $id_cart);
				die('Prestashop error - unable to process order..');
			}
		}
	}

	public function addFee(&$cart, $amount)
	{
		$def_lang = (int)Configuration::get('PS_LANG_DEFAULT');
		$txt = $this->l('Credit card fee', $this->name, $def_lang);
		$row = Db::getInstance()->getRow('SELECT `id_product`
				FROM '._DB_PREFIX_.'product
				LEFT JOIN '._DB_PREFIX_.'product_lang
				USING (`id_product`)
				WHERE `name` = "'.$txt.'"');
		if ($row)
		{
			$product = new Product($row['id_product']);
			$cart->deleteProduct($row['id_product']);
		}
		else
			$product = new Product();
		$fee = $amount - $cart->getOrderTotal(true);
		if ($this->v15)
			$cacheEntries = Cache::retrieveAll();
		if ($fee <= 0)
			return;
		$product->name = array($def_lang => $txt);
		$product->active = 0;
		$product->price = $fee;
		$product->quantity = 100;
		$product->link_rewrite = array($def_lang => 'fee');
		$product->reference = $this->l('cardfee');
		$id_currency = Configuration::get('PS_CURRENCY_DEFAULT');
		$currency = new Currency((int)$cart->id_currency);
		if ($currency->id != $id_currency && $currency->conversion_rate)
			$product->price /= $currency->conversion_rate;
		$product->price = Tools::ps_round($product->price, 6);
		if ($this->v15)
			$product->is_virtual = 1;
		if ($this->v14)
			$product->id_tax_rules_group = 0;
		else
			$product->id_tax = 0;
		if ($row)
			$product->update();
		else
			$product->add();
		if ($this->v15)
		{
			$rows = Group::getGroups($cart->id_lang);
			foreach ($rows as $row)
			{
				Db::getInstance()->execute('
						INSERT IGNORE INTO `'._DB_PREFIX_.'product_group_reduction_cache`
						(`id_product`, `id_group`, `reduction`)
						VALUES ('.(int)$product->id.', '.$row['id_group'].', 0)');
			}
			StockAvailable::setQuantity($product->id, 0, 100);
		}
		$cart->updateQty(1, $product->id);
		if ($this->v15) {
			foreach ($cacheEntries as $cache_id => $value) {
				$entry = explode('_', $cache_id);
				if ($entry[0] == 'getContextualValue') {
					$cache_id .= '_'.$product->id.'_0';
					Cache::store($cache_id, $value);
				}
			}
			$cart->getPackageList(true); // Flush cache
		}
	}
}

?>
