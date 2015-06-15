{*
* NOTICE OF LICENSE
* $Date: 2015/04/22 19:18:18 $
* Written by Kjeld Borch Egevang
* E-mail: helpdesk@quickpay.net
*}

<section class="footer-block col-xs-12 col-sm-2 clearfix">
<h4>
	{l s='Payment methods' mod='quickpay'}
</h4>
<div class="block_content toggle-footer" style="display: block;">
{foreach from=$ordering_list item=var_name}
<div class="col-xs-3 col-sm-6" style="float: left; padding-left: 0px;">
  	<img style="margin-bottom: 10px;" src="{$module_dir|escape:'htmlall':'UTF-8'}views/imgf/{$var_name|escape:'htmlall':'UTF-8'}.gif" alt="{l s='Credit card' mod='quickpay'}" />
</div>
{/foreach}
</div>
</section>
