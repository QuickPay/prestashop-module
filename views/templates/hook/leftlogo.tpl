{*
* NOTICE OF LICENSE
* $Date: 2018/09/15 05:10:42 $
* Written by Kjeld Borch Egevang
* E-mail: support@quickpay.net
*}

<center class="quickpay imgf">
{foreach from=$ordering_list item=var_name}
  <img src="{$link->getMediaLink("`$module_dir|escape:'htmlall':'UTF-8'`views/img/`$var_name|escape:'htmlall':'UTF-8'`.png")}" alt="{l s='Pay with credit cards ' mod='quickpay'}" />
{/foreach}
</center><br />
