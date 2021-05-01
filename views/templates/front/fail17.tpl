{*
* NOTICE OF LICENSE
* $Date: 2018/06/09 10:40:37 $
* Written by Kjeld Borch Egevang
* E-mail: support@quickpay.net
*}

{extends file='customer/page.tpl'}

{block name='page_title'}
    {l s='Payment problem' mod='quickpay'}
{/block}

{block name='page_content'}

{if $status == 'currency'}
<p class="alert alert-warning warning">{l s='Your order on' mod='quickpay'} <strong>{$shop_name|escape:'htmlall':'UTF-8'}</strong> {l s='failed because the currency was changed.' mod='quickpay'}
</p>
<div class="box">
	{l s='Please fill the cart again.' mod='quickpay'}
	<br /><br />{l s='For any questions or for further information, please contact our' mod='quickpay'} <a href="{$urls.pages.contact|escape:'javascript':'UTF-8'}">{l s='customer support' mod='quickpay'}</a>.
</div>
{/if}

{if $status == 'test'}
<p class="alert alert-warning warning">{l s='Your order on' mod='quickpay'} <strong>{$shop_name|escape:'htmlall':'UTF-8'}</strong> {l s='failed because a test card was used for payment.' mod='quickpay'}
</p>
<div class="box">
	{l s='Please fill the cart again.' mod='quickpay'}
	<br /><br />{l s='For any questions or for further information, please contact our' mod='quickpay'} <a href="{$urls.pages.contact|escape:'javascript':'UTF-8'}">{l s='customer support' mod='quickpay'}</a>.
</div>
{/if}

{/block}
