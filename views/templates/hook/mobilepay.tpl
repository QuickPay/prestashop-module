{*
* NOTICE OF LICENSE
* $Date: 2019/06/23 05:15:20 $
* Written by Kjeld Borch Egevang
* E-mail: helpdesk@quickpay.net
*}

{if isset($mobilepay_link)}
<div class="mobilepay-confirm">
    <span class="ui-icon ui-icon-alert"></span>
    <p>
    {l s='I agree to the terms of service and will adhere to them unconditionally.' mod='quickpay'}
    </p>
    {if $mobilepay_link}
    <p>
    {l s='Read more about our' mod='quickpay'}
    <a href="{$mobilepay_link|escape:'javascript':'UTF-8'}">{l s='policy' mod='quickpay'}</a>.
    </p>
    {/if}
    {if $carrier_name}
    <p>
    {l s='Delivery method:' mod='quickpay'}
    <br>
    <strong>{$carrier_name|escape:'javascript':'UTF-8'}</strong>
    <br>
    {l s='Delivery time:' mod='quickpay'}
    {$carrier_delay|escape:'javascript':'UTF-8'}
    </p>
    {/if}
</div>
{/if}

{if isset($mobilepay_url)}
{if $smarty.const._PS_VERSION_ >= 1.7}
<br>
<br>
<a href="{$payment_url|escape:'javascript':'UTF-8'}" class="btn btn-primary mobilepay-checkout">
    <img src="{$module_dir|escape:'htmlall':'UTF-8'}views/img/mobilepay_white.png" alt="{l s='MobilePay Checkout' mod='quickpay'}" />
    MobilePay Checkout
</a>
{elseif $smarty.const._PS_VERSION_ >= 1.6}
<a href="{$payment_url|escape:'javascript':'UTF-8'}" class="btn btn-default button button-medium mobilepay-checkout">
    <span>
        <img src="{$module_dir|escape:'htmlall':'UTF-8'}views/img/mobilepay_white.png" alt="{l s='MobilePay Checkout' mod='quickpay'}" />
        MobilePay Checkout
    </span>
</a>
<br>
<br>
{else}
<a href="{$payment_url|escape:'javascript':'UTF-8'}" class="btn btn-default button button-medium mobilepay-checkout">
    <span>
        MobilePay Checkout
    </span>
</a>
<br>
<br>
{/if}

<script type="text/javascript">
    var mobilepay = {};
    mobilepay.url = "{$mobilepay_url|escape:'javascript':'UTF-8'}";
    mobilepay.id_cms = {$id_cms|escape:'javascript':'UTF-8'};
    mobilepay.accept = "{l s='Accept' mod='quickpay'}";
    mobilepay.cancel = "{l s='Cancel' mod='quickpay'}";
    mobilepay.title = "{l s='Terms of service' mod='quickpay'}";
</script>
{/if}
