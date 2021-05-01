{*
* NOTICE OF LICENSE
* $Date: 2017/10/04 19:59:35 $
* Written by Kjeld Borch Egevang
* E-mail: support@quickpay.net
*}

{function name=showhide}
    {if $style != 1}
        style='display:none'
    {/if}
{/function}
<div class="panel">
    <h3><i class="icon-list-ul"></i> {l s='Card list' mod='quickpay'}</h3>
    <div id="cardsContent">
        <div id="cards">
            {foreach from=$cards item=card}
                <div id="cards={$card.name|escape:'htmlall':'UTF-8'}" class="panel" style="padding-bottom:6px">
                    <div class="row">
                        <div class="col-lg-1">
                            <span><i class="icon-arrows "></i></span>
                        </div>
                        <div class="col-md-1">
                            <img src="{$image_baseurl|escape:'htmlall':'UTF-8'}{$card.image|escape:'htmlall':'UTF-8'}" alt="{$card.title|escape:'htmlall':'UTF-8'}" class="img-thumbnail" style="display:block" />
                            <a class="btn" {showhide style=$card.display} id="{$card.name|escape:'htmlall':'UTF-8'}_disp">
                                <i class="icon-plus-sign"></i>
                                {l s='Visible' mod='quickpay'}
                            </a>
                            <a class="btn" {showhide style=!$card.display} id="{$card.name|escape:'htmlall':'UTF-8'}_hide">
                                <i class="icon-minus-sign"></i>
                                {l s='Hidden' mod='quickpay'}
                            </a>
                        </div>
                        {if $card.image_secure}
                            <div class="col-md-2">
                                <img src="{$image_baseurl|escape:'htmlall':'UTF-8'}{$card.image_secure|escape:'htmlall':'UTF-8'}" alt="{$card.title|escape:'htmlall':'UTF-8'}" class="img-thumbnail" style="display:block" />
                                <a class="btn" {showhide style=$card.display_secure} id="{$card.name|escape:'htmlall':'UTF-8'}_disp_sec">
                                    <i class="icon-plus-sign"></i>
                                    {l s='Visible' mod='quickpay'}
                                </a>
                                <a class="btn" {showhide style=!$card.display_secure} id="{$card.name|escape:'htmlall':'UTF-8'}_hide_sec">
                                    <i class="icon-minus-sign"></i>
                                    {l s='Hidden' mod='quickpay'}
                                </a>
                            </div>
                        {else}
                            <div class="col-md-2">
                            </div>
                        {/if}
                        <div class="col-md-8">
                            <h4 class="pull-left">
                                {$card.title|escape:'htmlall':'UTF-8'}
                            </h4>
                            <div class="btn-group-action pull-right">
                                <a class="btn btn-success" {showhide style=$card.status} id="{$card.name|escape:'htmlall':'UTF-8'}_on">
                                    <i class="icon-check"></i>
                                    {l s='Enabled' mod='quickpay'}
                                </a>
                                <a class="btn btn-danger" {showhide style=$card.status + 1} id="{$card.name|escape:'htmlall':'UTF-8'}_off">
                                    <i class="icon-remove"></i>
                                    {l s='Disabled' mod='quickpay'}
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            {/foreach}
        </div>
    </div>
</div>

<script type="text/javascript">
$(document).ready(function() {
    $('a.btn').click(function (event) {
        $(event.target).parent().find('a').toggle();
        var url = "{$change_url|escape:'javascript':'UTF-8'}" +
            "&secure_key={$secure_key|escape:'urlpathinfo':'UTF-8'}" +
            "&action=changeState" +
            "&target=" + event.target.id;
        $.get(url).done(function() {
            var url = "{$change_url|escape:'javascript':'UTF-8'}" +
                "&secure_key={$secure_key|escape:'urlpathinfo':'UTF-8'}" +
                "&action=previewPayment";
            $.get(url, function(data) {
                $('.previewPayment').first().remove();
                $('.previewPayment').replaceWith(data);
            });
        });
    });

    /* Style & js for fieldset 'cards configuration' */
    var $myCards = $("#cards");
    $myCards.sortable({
        opacity: 0.6,
        cursor: "move",
        update: function() {
            var order = $(this).sortable("serialize", { expression: /(.+)=(.+)/ });
            var url = "{$change_url|escape:'javascript':'UTF-8'}" +
                "&action=updateCardsPosition";
            $.post(url, order).done(function() {
                var url = "{$change_url|escape:'javascript':'UTF-8'}" +
                    "&secure_key={$secure_key|escape:'urlpathinfo':'UTF-8'}" +
                    "&action=previewPayment";
                $.get(url, function(data) {
                    $('.previewPayment').first().remove();
                    $('.previewPayment').replaceWith(data);
                });
            });
        }
    });
    $myCards.hover(function() {
        $(this).css("cursor","move");
    },
    function() {
        $(this).css("cursor","auto");
    });
});
</script>
