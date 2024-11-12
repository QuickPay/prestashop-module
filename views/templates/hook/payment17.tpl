{*
* NOTICE OF LICENSE
* $Date: 2018/09/15 05:10:42 $
* Written by Kjeld Borch Egevang
* E-mail: support@quickpay.net
*}

{if $imgs|@count gt 2}
<p class="payment_module quickpay imgf">
{else}
<p class="payment_module quickpay">
{/if}
{foreach from=$imgs item=img}
            <img src="{$module_dir|escape:'htmlall':'UTF-8'}views/img/{$img|escape:'htmlall':'UTF-8'}.png" alt="{l s='Pay with cards ' mod='quickpay'}" />
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
