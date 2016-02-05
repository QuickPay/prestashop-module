{*
* NOTICE OF LICENSE
* $Date: 2016/02/02 11:50:13 $
* Written by Kjeld Borch Egevang
* E-mail: helpdesk@quickpay.net
*}

{if $status == 'currency'}
<p class="alert alert-warning warning">{l s='Your order on' mod='quickpay'} <strong>{$shop_name|escape:'htmlall':'UTF-8'}</strong> {l s='failed because the currency was changed.' mod='quickpay'}
</p>
<div class="box">
	{l s='Please fill the cart again.' mod='quickpay'}
	<br /><br />{l s='For any questions or for further information, please contact our' mod='quickpay'} <a href="{$base_dir_ssl|escape:'htmlall':'UTF-8'}contact-form.php">{l s='customer support' mod='quickpay'}</a>.
</div>
{/if}

{if $status == 'test'}
<p class="alert alert-warning warning">{l s='Your order on' mod='quickpay'} <strong>{$shop_name|escape:'htmlall':'UTF-8'}</strong> {l s='failed because a test card was used for payment.' mod='quickpay'}
</p>
<div class="box">
	{l s='Please fill the cart again.' mod='quickpay'}
	<br /><br />{l s='For any questions or for further information, please contact our' mod='quickpay'} <a href="{$base_dir_ssl|escape:'htmlall':'UTF-8'}contact-form.php">{l s='customer support' mod='quickpay'}</a>.
</div>
{/if}
