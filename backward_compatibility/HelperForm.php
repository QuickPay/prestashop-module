<?php
/*
* 2007-2012 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Open Software License (OSL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/osl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author PrestaShop SA <contact@prestashop.com>
*  @copyright  2007-2012 PrestaShop SA
*  @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/

/**
 * @since 1.5.0
 */
class HelperForm
{
	public $id;
	public $first_call = true;

	/** @var array of forms fields */
	protected $fields_form = array();

	/** @var array values ​​of form fields */
	public $fields_value = array();

	public $table;
	public $name_controller = '';

	/** @var string if not null, a title will be added on that list */
	public $title = null;

	/** @var string Used to override default 'submitAdd' parameter in form action attribute */
	public $submit_action;

	public $token;
	public $languages = null;
	public $default_form_language = null;
	public $allow_employee_form_lang = null;

	public function __construct()
	{
		$this->base_tpl = 'form.tpl';
	}

	public function generateForm($fields_form)
	{
		$this->fields_form = $fields_form;
		return $this->generate();
	}

	public function generate()
	{
		$this->tpl = $this->createTemplate($this->base_tpl);
		if (is_null($this->submit_action))
			$this->submit_action = 'submitAdd'.$this->table;

		$this->tpl->assign(array(
			'title' => $this->title,
			'toolbar_btn' => '',
			'show_toolbar' => '',
			'toolbar_scroll' => '',
			'submit_action' => $this->submit_action,
			'firstCall' => $this->first_call,
			'current' => $this->currentIndex,
			'token' => $this->token,
			'table' => $this->table,
			'identifier' => $this->identifier,
			'name_controller' => $this->name_controller,
			'languages' => $this->languages,
			'defaultFormLanguage' => $this->default_form_language,
			'allowEmployeeFormLang' => $this->allow_employee_form_lang,
			'form_id' => $this->id,
			'fields' => $this->fields_form,
			'fields_value' => $this->fields_value,
			'required_fields' => $this->getFieldsRequired(),
			'vat_number' => file_exists(_PS_MODULE_DIR_.'vatnumber/ajax.php'),
			'module_dir' => _MODULE_DIR_,
			'contains_states' => (isset($this->fields_value['id_country']) && isset($this->fields_value['id_state'])) ? Country::containsStates($this->fields_value['id_country']) : null,
		));
		$this->tpl->assign($this->tpl_vars);
		return $this->tpl->fetch();
	}

	/**
	 * Return true if there are required fields
	 */
	public function getFieldsRequired()
	{
		foreach ($this->fields_form as $fieldset)
			if (isset($fieldset['form']['input']))
				foreach ($fieldset['form']['input'] as $input)
					if (array_key_exists('required', $input) && $input['required'] && $input['type'] != 'radio')
						return true;

		return false;
	}

	public function createTemplate($tpl_name)
	{
		$this->base_folder =
			$this->local_path.'views/templates/admin/';
		return $this->context->smarty->createTemplate($this->base_folder.$tpl_name, $this->context->smarty);
	}
}
