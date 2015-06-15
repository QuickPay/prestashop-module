{*
* NOTICE OF LICENSE
* $Date: 2015/05/12 20:33:12 $
* Written by Kjeld Borch Egevang
* E-mail: helpdesk@quickpay.net
*}

<div class="panel"><h3><i class="icon-list-ul"></i> {l s='Card list' mod='quickpay'}
	</h3>
	<div id="cardsContent">
		<div id="cards">
			{foreach from=$cards item=card}
				<div id="cards={$card.name|escape:'htmlall':'UTF-8'}" class="panel">
					<div class="row">
						<div class="col-lg-1">
							<span><i class="icon-arrows "></i></span>
						</div>
						<div class="col-md-3">
							<img src="{$image_baseurl|escape:'htmlall':'UTF-8'}{$card.image|escape:'htmlall':'UTF-8'}" alt="{$card.title|escape:'htmlall':'UTF-8'}" class="img-thumbnail" />
						</div>
						<div class="col-md-8">
							<h4 class="pull-left">
								{$card.title|escape:'htmlall':'UTF-8'}
							</h4>
							<div class="btn-group-action pull-right">
								{if $card.status}
									<a class="btn btn-success" id="{$card.name|escape:'htmlall':'UTF-8'}_on">
										<i class="icon-check"></i>
										{l s='Enabled' mod='quickpay'}
									</a>
									<a class="btn btn-danger" style="display:none" id="{$card.name|escape:'htmlall':'UTF-8'}_off">
										<i class="icon-remove"></i>
										{l s='Disabled' mod='quickpay'}
									</a>
								{else}
									<a class="btn btn-success" style="display:none" id="{$card.name|escape:'htmlall':'UTF-8'}_on">
										<i class="icon-check"></i>
										{l s='Enabled' mod='quickpay'}
									</a>
									<a class="btn btn-danger" id="{$card.name|escape:'htmlall':'UTF-8'}_off">
										<i class="icon-remove"></i>
										{l s='Disabled' mod='quickpay'}
									</a>
								{/if}
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
			$.get(url);
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
				$.post(url, order);
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
