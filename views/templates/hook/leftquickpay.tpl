{*
* NOTICE OF LICENSE
* $Date: 2015/04/22 19:18:18 $
* Written by Kjeld Borch Egevang
* E-mail: helpdesk@quickpay.net
*}

<center>
{foreach from=$ordering_list item=var_name}
  <img src="{$module_dir|escape:'htmlall':'UTF-8'}views/imgf/{$var_name|escape:'htmlall':'UTF-8'}.gif" alt="{l s='Pay with credit cards ' mod='quickpay'}" />
{/foreach}
</center><br />
