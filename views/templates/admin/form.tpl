{*
* NOTICE OF LICENSE
* $Date: 2015/05/07 07:04:23 $
* Written by Kjeld Borch Egevang (based on 1.5 form.tpl)
* E-mail: helpdesk@quickpay.net
*}

{if $show_toolbar}
	{include file="toolbar.tpl" toolbar_btn=$toolbar_btn toolbar_scroll=$toolbar_scroll title=$title}
	<div class="leadin">{block name="leadin"}{/block}</div>
{/if}

{if isset($fields.title)}<h2>{$fields.title}</h2>{/if}
{block name="defaultForm"}
<form id="{$table}_form" class="defaultForm {$name_controller}" action="{$current}&{if !empty($submit_action)}{$submit_action}=1{/if}&token={$token}" method="post" enctype="multipart/form-data" {if isset($style)}style="{$style}"{/if}>
	{if $form_id}
		<input type="hidden" name="{$identifier|escape:'htmlall':'UTF-8'}" id="{$identifier|escape:'htmlall':'UTF-8'}" value="{$form_id|escape:'htmlall':'UTF-8'}" />
	{/if}
	{foreach $fields as $f => $fieldset}
		<fieldset id="fieldset_{$f|escape:'htmlall':'UTF-8'}">
			{foreach $fieldset.form as $key => $field}
				{if $key == 'legend'}
					<legend>
						{if isset($field.image)}<img src="{$field.image}" alt="{$field.title|escape:'htmlall':'UTF-8'}" />{/if}
						{$field.title|escape:'htmlall':'UTF-8'}
					</legend>
				{elseif $key == 'description' && $field}
					<p class="description">{$field|escape:'htmlall':'UTF-8'}</p>
				{elseif $key == 'input'}
					{foreach $field as $input}
						{if $input.type == 'hidden'}
							<input type="hidden" name="{$input.name}" id="{$input.name}" value="{$fields_value[$input.name]|escape:'htmlall':'UTF-8'}" />
						{else}
							{if $input.name == 'id_state'}
								<div id="contains_states" {if $contains_states}style="display:none;"{/if}>
							{/if}
							{block name="label"}
								{if isset($input.label)}<label>{$input.label} </label>{/if}
							{/block}
							{block name="field"}
								<div class="margin-form">
								{block name="input"}
								{if $input.type == 'text' || $input.type == 'tags'}
									{if isset($input.lang) AND $input.lang}
										<div class="translatable">
											{foreach $languages as $language}
												<div class="lang_{$language.id_lang}" style="display:{if $language.id_lang == $defaultFormLanguage}block{else}none{/if}; float: left;">
													{if $input.type == 'tags'}
														{literal}
														<script type="text/javascript">
															$().ready(function () {
																var input_id = '{/literal}{if isset($input.id)}{$input.id}_{$language.id_lang}{else}{$input.name}_{$language.id_lang}{/if}{literal}';
																$('#'+input_id).tagify({addTagPrompt: '{/literal}{l s='Add tag' js=1}{literal}'});
																$({/literal}'#{$table}{literal}_form').submit( function() {
																	$(this).find('#'+input_id).val($('#'+input_id).tagify('serialize'));
																});
															});
														</script>
														{/literal}
													{/if}
													{assign var='value_text' value=$fields_value[$input.name][$language.id_lang]}
													<input type="text"
															name="{$input.name|escape:'htmlall':'UTF-8'}_{$language.id_lang|escape:'htmlall':'UTF-8'}"
															id="{if isset($input.id)}{$input.id}_{$language.id_lang}{else}{$input.name}_{$language.id_lang}{/if}"
															value="{if isset($input.string_format) && $input.string_format}{$value_text|string_format:$input.string_format|escape:'htmlall':'UTF-8'}{else}{$value_text|escape:'htmlall':'UTF-8'}{/if}"
															class="{if $input.type == 'tags'}tagify {/if}{if isset($input.class)}{$input.class}{/if}"
															{if isset($input.size)}size="{$input.size}"{/if}
															{if isset($input.maxlength)}maxlength="{$input.maxlength}"{/if}
															{if isset($input.readonly) && $input.readonly}readonly="readonly"{/if}
															{if isset($input.disabled) && $input.disabled}disabled="disabled"{/if}
															{if isset($input.autocomplete) && !$input.autocomplete}autocomplete="off"{/if} />
													{if !empty($input.hint)}<span class="hint" name="help_box">{$input.hint}<span class="hint-pointer">&nbsp;</span></span>{/if}
												</div>
											{/foreach}
										</div>
									{else}
										{if $input.type == 'tags'}
											{literal}
											<script type="text/javascript">
												$().ready(function () {
													var input_id = '{/literal}{if isset($input.id)}{$input.id}{else}{$input.name}{/if}{literal}';
													$('#'+input_id).tagify();
													$('#'+input_id).tagify({addTagPrompt: '{/literal}{l s='Add tag' mod='quickpay'}{literal}'});
													$({/literal}'#{$table}{literal}_form').submit( function() {
														$(this).find('#'+input_id).val($('#'+input_id).tagify('serialize'));
													});
												});
											</script>
											{/literal}
										{/if}
										{assign var='value_text' value=$fields_value[$input.name]}
										<input type="text"
												name="{$input.name|escape:'htmlall':'UTF-8'}"
												id="{if isset($input.id)}{$input.id}{else}{$input.name}{/if}"
												value="{if isset($input.string_format) && $input.string_format}{$value_text|string_format:$input.string_format|escape:'htmlall':'UTF-8'}{else}{$value_text|escape:'htmlall':'UTF-8'}{/if}"
												class="{if $input.type == 'tags'}tagify {/if}{if isset($input.class)}{$input.class}{/if}"
												{if isset($input.size)}size="{$input.size}"{/if}
												{if isset($input.maxlength)}maxlength="{$input.maxlength}"{/if}
												{if isset($input.class)}class="{$input.class}"{/if}
												{if isset($input.readonly) && $input.readonly}readonly="readonly"{/if}
												{if isset($input.disabled) && $input.disabled}disabled="disabled"{/if}
												{if isset($input.autocomplete) && !$input.autocomplete}autocomplete="off"{/if} />
										{if isset($input.suffix)}{$input.suffix}{/if}
										{if !empty($input.hint)}<span class="hint" name="help_box">{$input.hint}<span class="hint-pointer">&nbsp;</span></span>{/if}
									{/if}
								{elseif $input.type == 'select'}
									{if isset($input.options.query) && !$input.options.query && isset($input.empty_message)}
										{$input.empty_message|escape:'htmlall':'UTF-8'}
										{$input.required = false}
										{$input.desc = null}
									{else}
										<select name="{$input.name}" class="{if isset($input.class)}{$input.class}{/if}"
												id="{if isset($input.id)}{$input.id}{else}{$input.name}{/if}"
												{if isset($input.multiple)}multiple="multiple" {/if}
												{if isset($input.size)}size="{$input.size}"{/if}
												{if isset($input.onchange)}onchange="{$input.onchange}"{/if}>
											{if isset($input.options.default)}
												<option value="{$input.options.default.value|escape:'htmlall':'UTF-8'}">{$input.options.default.label|escape:'htmlall':'UTF-8'}</option>
											{/if}
											{if isset($input.options.optiongroup)}
												{foreach $input.options.optiongroup.query AS $optiongroup}
													<optgroup label="{$optiongroup[$input.options.optiongroup.label]|escape:'htmlall':'UTF-8'}">
														{foreach $optiongroup[$input.options.options.query] as $option}
															<option value="{$option[$input.options.options.id]|escape:'htmlall':'UTF-8'}"
																{if isset($input.multiple)}
																	{foreach $fields_value[$input.name] as $field_value}
																		{if $field_value == $option[$input.options.options.id]}selected="selected"{/if}
																	{/foreach}
																{else}
																	{if $fields_value[$input.name] == $option[$input.options.options.id]}selected="selected"{/if}
																{/if}
															>{$option[$input.options.options.name]|escape:'htmlall':'UTF-8'}</option>
														{/foreach}
													</optgroup>
												{/foreach}
											{else}
												{foreach $input.options.query AS $option}
													{if is_object($option)}
														<option value="{$option->$input.options.id|escape:'htmlall':'UTF-8'}"
															{if isset($input.multiple)}
																{foreach $fields_value[$input.name] as $field_value}
																	{if $field_value == $option->$input.options.id}
																		selected="selected"
																	{/if}
																{/foreach}
															{else}
																{if $fields_value[$input.name] == $option->$input.options.id}
																	selected="selected"
																{/if}
															{/if}
														>{$option->$input.options.name|escape:'htmlall':'UTF-8'}</option>
													{else}
														<option value="{$option[$input.options.id]|escape:'htmlall':'UTF-8'}"
															{if isset($input.multiple)}
																{foreach $fields_value[$input.name] as $field_value}
																	{if $field_value == $option[$input.options.id]}
																		selected="selected"
																	{/if}
																{/foreach}
															{else}
																{if $fields_value[$input.name] == $option[$input.options.id]}
																	selected="selected"
																{/if}
															{/if}
														>{$option[$input.options.name]|escape:'htmlall':'UTF-8'}</option>

													{/if}
												{/foreach}
											{/if}
										</select>
										{if !empty($input.hint)}<span class="hint" name="help_box">{$input.hint}<span class="hint-pointer">&nbsp;</span></span>{/if}
									{/if}
								{elseif $input.type == 'radio'}
									{foreach $input.values as $value}
										<input type="radio"	name="{$input.name}"id="{$value.id}" value="{$value.value|escape:'htmlall':'UTF-8'}"
												{if $fields_value[$input.name] == $value.value}checked="checked"{/if}
												{if isset($input.disabled) && $input.disabled}disabled="disabled"{/if} />
										<label {if isset($input.class)}class="{$input.class}"{/if} for="{$value.id}">
										 {if isset($input.is_bool) && $input.is_bool == true}
											{if $value.value == 1}
												<img src="../img/admin/enabled.gif" alt="{$value.label|escape:'htmlall':'UTF-8'}" title="{$value.label|escape:'htmlall':'UTF-8'}" />
											{else}
												<img src="../img/admin/disabled.gif" alt="{$value.label|escape:'htmlall':'UTF-8'}" title="{$value.label|escape:'htmlall':'UTF-8'}" />
											{/if}
										 {else}
											{$value.label|escape:'htmlall':'UTF-8'}
										 {/if}
										</label>
										{if isset($input.br) && $input.br}<br />{/if}
										{if isset($value.p) && $value.p}<p>{$value.p}</p>{/if}
									{/foreach}
								{elseif $input.type == 'textarea'}
									{if isset($input.lang) AND $input.lang}
										<div class="translatable">
											{foreach $languages as $language}
												<div class="lang_{$language.id_lang}" id="{$input.name}_{$language.id_lang}" style="display:{if $language.id_lang == $defaultFormLanguage}block{else}none{/if}; float: left;">
													<textarea cols="{$input.cols}" rows="{$input.rows}" name="{$input.name}_{$language.id_lang}" {if isset($input.autoload_rte) && $input.autoload_rte}class="rte autoload_rte {if isset($input.class)}{$input.class}{/if}"{/if} >{$fields_value[$input.name][$language.id_lang]|escape:'htmlall':'UTF-8'}</textarea>
												</div>
											{/foreach}
										</div>
									{else}
										<textarea name="{$input.name}" id="{if isset($input.id)}{$input.id}{else}{$input.name}{/if}" cols="{$input.cols}" rows="{$input.rows}" {if isset($input.autoload_rte) && $input.autoload_rte}class="rte autoload_rte {if isset($input.class)}{$input.class}{/if}"{/if}>{$fields_value[$input.name]|escape:'htmlall':'UTF-8'}</textarea>
									{/if}
								{elseif $input.type == 'checkbox'}
									{foreach $input.values.query as $value}
										{assign var=id_checkbox value=$input.name|cat:'_'|cat:$value[$input.values.id]}
										<input type="checkbox"
											name="{$id_checkbox|escape:'htmlall':'UTF-8'}"
											id="{$id_checkbox|escape:'htmlall':'UTF-8'}"
											class="{if isset($input.class)}{$input.class}{/if}"
											{if isset($value.val)}value="{$value.val|escape:'htmlall':'UTF-8'}"{/if}
											{if isset($fields_value[$id_checkbox]) && $fields_value[$id_checkbox]}checked="checked"{/if} />
										<label for="{$id_checkbox|escape:'htmlall':'UTF-8'}" class="t"><strong>{$value[$input.values.name]|escape:'htmlall':'UTF-8'}</strong></label><br />
									{/foreach}
								{elseif $input.type == 'file'}
									{if isset($input.display_image) && $input.display_image}
										{if isset($fields_value.image) && $fields_value.image}
											<div id="image">
												{$fields_value.image|escape:'htmlall':'UTF-8'}
												<p align="center">{l s='File size' mod='quickpay'} {$fields_value.size|escape:'htmlall':'UTF-8'}kb</p>
												<a href="{$current|escape:'htmlall':'UTF-8'}&{$identifier|escape:'htmlall':'UTF-8'}={$form_id|escape:'htmlall':'UTF-8'}&token={$token}&deleteImage=1">
													<img src="../img/admin/delete.gif" alt="{l s='Delete' mod='quickpay'}" /> {l s='Delete' mod='quickpay'}
												</a>
											</div><br />
										{/if}
									{/if}
									<input type="file" name="{$input.name}" {if isset($input.id)}id="{$input.id}"{/if} />
									{if !empty($input.hint)}<span class="hint" name="help_box">{$input.hint}<span class="hint-pointer">&nbsp;</span></span>{/if}
								{elseif $input.type == 'password'}
									<input type="password"
											name="{$input.name|escape:'htmlall':'UTF-8'}"
											size="{$input.size|escape:'htmlall':'UTF-8'}"
											class="{if isset($input.class)}{$input.class}{/if}"
											value=""
											{if isset($input.autocomplete) && !$input.autocomplete}autocomplete="off"{/if} />
								{elseif $input.type == 'birthday'}
									{foreach $input.options as $key => $select}
										<select name="{$key}" class="{if isset($input.class)}{$input.class}{/if}">
											<option value="">-</option>
											{if $key == 'months'}
												{foreach $select as $k => $v}
													<option value="{$k}" {if $k == $fields_value[$key]}selected="selected"{/if}>{l s=$v mod='quickpay'}</option>
												{/foreach}
											{else}
												{foreach $select as $v}
													<option value="{$v}" {if $v == $fields_value[$key]}selected="selected"{/if}>{$v}</option>
												{/foreach}
											{/if}

										</select>
									{/foreach}
								{elseif $input.type == 'group'}
									{assign var=groups value=$input.values}
									{include file='helpers/form/form_group.tpl'}
								{elseif $input.type == 'shop'}
									{$input.html|escape:'htmlall':'UTF-8'}
								{elseif $input.type == 'categories'}
									{include file='helpers/form/form_category.tpl' categories=$input.values}
								{elseif $input.type == 'categories_select'}
									{$input.category_tree|escape:'htmlall':'UTF-8'}
								{elseif $input.type == 'asso_shop' && isset($asso_shop) && $asso_shop}
										{$asso_shop|escape:'htmlall':'UTF-8'}
								{elseif $input.type == 'color'}
									<input type="color"
										size="{$input.size|escape:'htmlall':'UTF-8'}"
										data-hex="true"
										{if isset($input.class)}class="{$input.class}"
										{else}class="color mColorPickerInput"{/if}
										name="{$input.name|escape:'htmlall':'UTF-8'}"
										class="{if isset($input.class)}{$input.class}{/if}"
										value="{$fields_value[$input.name]|escape:'htmlall':'UTF-8'}" />
								{elseif $input.type == 'date'}
									<input type="text"
										size="{$input.size|escape:'htmlall':'UTF-8'}"
										data-hex="true"
										{if isset($input.class)}class="{$input.class}"
										{else}class="datepicker"{/if}
										name="{$input.name|escape:'htmlall':'UTF-8'}"
										value="{$fields_value[$input.name]|escape:'htmlall':'UTF-8'}" />
								{elseif $input.type == 'free'}
									{$fields_value[$input.name]|escape:'htmlall':'UTF-8'}
								{/if}
								{if isset($input.required) && $input.required && $input.type != 'radio'} <sup>*</sup>{/if}
								{/block}{* end block input *}
								{block name="description"}
									{if isset($input.desc)}
										<p class="preference_description">
											{if is_array($input.desc)}
												{foreach $input.desc as $p}
													{if is_array($p)}
														<span id="{$p.id|escape:'htmlall':'UTF-8'}">{$p.text|escape:'htmlall':'UTF-8'}</span><br />
													{else}
														{$p|escape:'htmlall':'UTF-8'}<br />
													{/if}
												{/foreach}
											{else}
												{$input.desc|escape:'htmlall':'UTF-8'}
											{/if}
										</p>
									{/if}
								{/block}
								{if isset($input.lang) && isset($languages)}<div class="clear"></div>{/if}
								</div>
								<div class="clear"></div>
							{/block}{* end block field *}
							{if $input.name == 'id_state'}
								</div>
							{/if}
						{/if}
					{/foreach}
					{*
					{hook h='displayAdminForm'}
					{if isset($name_controller)}
						{capture name=hookName assign=hookName}display{$name_controller|ucfirst}Form{/capture}
						{hook h=$hookName}
					{elseif isset($smarty.get.controller)}
						{capture name=hookName assign=hookName}display{$smarty.get.controller|ucfirst|htmlentities}Form{/capture}
						{hook h=$hookName}
					{/if}
					*}
				{elseif $key == 'submit'}
					<div class="margin-form">
						<input type="submit"
							id="{if isset($field.id)}{$field.id}{else}{$table}_form_submit_btn{/if}"
							value="{$field.title|escape:'htmlall':'UTF-8'}"
							name="{if isset($field.name)}{$field.name}{else}{$submit_action}{/if}{if isset($field.stay) && $field.stay}AndStay{/if}"
							{if isset($field.class)}class="{$field.class}"{/if} />
					</div>
				{elseif $key == 'desc'}
					<p class="clear">
						{if is_array($field)}
							{foreach $field as $k => $p}
								{if is_array($p)}
									<span id="{$p.id|escape:'htmlall':'UTF-8'}">{$p.text|escape:'htmlall':'UTF-8'}</span><br />
								{else}
									{$p|escape:'htmlall':'UTF-8'}
									{if isset($field[$k+1])}<br />{/if}
								{/if}
							{/foreach}
						{else}
							{$field|escape:'htmlall':'UTF-8'}
						{/if}
					</p>
				{/if}
				{block name="other_input"}{/block}
			{/foreach}
			{if $required_fields}
				<div class="small"><sup>*</sup> {l s='Required field' mod='quickpay'}</div>
			{/if}
		</fieldset>
		{block name="other_fieldsets"}{/block}
		{if isset($fields[$f+1])}<br />{/if}
	{/foreach}
</form>
{/block}
{block name="after"}{/block}

{if isset($tinymce) && $tinymce}
	<script type="text/javascript">

	var iso = '{$iso|escape:'htmlall':'UTF-8'}';
	var pathCSS = '{$smarty.const._THEME_CSS_DIR_|escape:'htmlall':'UTF-8'}';
	var ad = '{$ad|escape:'htmlall':'UTF-8'}';

	$(document).ready(function(){
		{block name="autoload_tinyMCE"}
			tinySetup({
				editor_selector :"autoload_rte",
				theme_advanced_buttons1 : "bold,italic,underline,strikethrough,|,justifyleft,justifycenter,justifyright,justifyfull|cut,copy,paste,pastetext,pasteword,|,search,replace,|,bullist,numlist,|,undo,redo",
				theme_advanced_buttons2 : "link,unlink,anchor,image,cleanup,code,|,forecolor,backcolor,|,hr,removeformat,visualaid,|,charmap,media,|,ltr,rtl,|,fullscreen",
				theme_advanced_buttons3 : "",
				theme_advanced_buttons4 : ""
			});
		{/block}
	});
	</script>
{/if}
{if $firstCall}
	<script type="text/javascript">
		var module_dir = '{$smarty.const._MODULE_DIR_|escape:'htmlall':'UTF-8'}';
		var id_language = {$defaultFormLanguage|escape:'htmlall':'UTF-8'};
		var languages = new Array();
		var vat_number = {if $vat_number}1{else}0{/if};
		// Multilang field setup must happen before document is ready so that calls to displayFlags() to avoid
		// precedence conflicts with other document.ready() blocks
		{foreach $languages as $k => $language}
			languages[{$k|escape:'htmlall':'UTF-8'}] = {
				id_lang: {$language.id_lang|escape:'htmlall':'UTF-8'},
				iso_code: '{$language.iso_code|escape:'htmlall':'UTF-8'}',
				name: '{$language.name|escape:'htmlall':'UTF-8'}',
				{*
				is_default: '{$language.is_default|escape:'htmlall':'UTF-8'}'
				*}
			};
		{/foreach}
		// we need allowEmployeeFormLang var in ajax request
		allowEmployeeFormLang = {$allowEmployeeFormLang|escape:'htmlall':'UTF-8'};
		displayFlags(languages, id_language, allowEmployeeFormLang);

		$(document).ready(function() {
			{if isset($fields_value.id_state)}
				if ($('#id_country') && $('#id_state'))
				{
					ajaxStates({$fields_value.id_state|escape:'htmlall':'UTF-8'});
					$('#id_country').change(function() {
						ajaxStates();
					});
				}
			{/if}

			if ($(".datepicker").length > 0)
				$(".datepicker").datepicker({
					prevText: '',
					nextText: '',
					dateFormat: 'yy-mm-dd'
				});

		});
	{block name="script"}{/block}
	</script>
{/if}
