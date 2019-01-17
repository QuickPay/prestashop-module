{*
* NOTICE OF LICENSE
* $Date: 2018/09/15 06:03:59 $
* Written by Kjeld Borch Egevang
* E-mail: helpdesk@quickpay.net
*}

<section class="footer-block col-xs-12 col-sm-2 clearfix">
<h4>
	{l s='Payment methods' mod='quickpay'}
</h4>
<div class="block_content toggle-footer quickpay imgf">
{foreach from=$ordering_list item=var_name}
    <img src="{$link->getMediaLink("`$module_dir|escape:'htmlall':'UTF-8'`views/img/`$var_name|escape:'htmlall':'UTF-8'`.png")}" alt="{l s='Credit card' mod='quickpay'}" />
{/foreach}
</div>
</section>
