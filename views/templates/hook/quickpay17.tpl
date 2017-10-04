{*
* NOTICE OF LICENSE
* $Date: 2016/11/11 04:32:14 $
* Written by Kjeld Borch Egevang
* E-mail: helpdesk@quickpay.net
*}

<p class="payment_module quickpay">
{foreach from=$imgs item=img}
{if $imgs|@count gt 2}
		<img src="{$module_dir|escape:'htmlall':'UTF-8'}views/imgf/{$img|escape:'htmlall':'UTF-8'}.gif" alt="{l s='Pay with credit cards ' mod='quickpay'}" />
{else}
		<img src="{$module_dir|escape:'htmlall':'UTF-8'}views/img/{$img|escape:'htmlall':'UTF-8'}.png" alt="{l s='Pay with credit cards ' mod='quickpay'}" />
{/if}
{/foreach}
{if $fees|@count gt 0}
<span style="display:table">
{foreach from=$fees item=fee}
	<span style="display:table-row">
		<span style="display:table-cell">
			<i>
				{$fee.name|escape:'htmlall':'UTF-8'}
			</i>
		</span>
		<span style="display:table-cell">
				{$fee.amount|escape:'htmlall':'UTF-8'}
		</span>
	</span>
{/foreach}
</span>
{/if}
</p>
