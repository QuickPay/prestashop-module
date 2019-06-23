/*
* NOTICE OF LICENSE
* $Date: 2019/04/12 18:25:07 $
* Written by Kjeld Borch Egevang
* E-mail: helpdesk@quickpay.net
*/

$(document).ready(function() {
    if (typeof mobilepay === 'undefined') {
        return;
    }
    mobilepay.function = function() {
        mobilepay.dialog = $(mobilepay.div).dialog({
            title: mobilepay.title,
            resizable: false,
            height: "auto",
            width: 320,
            modal: true,
            create: function(event) { $(event.target).parent().css('z-index', '9999'); },
            buttons: [
                {
                    id: "Accept",
                    text: mobilepay.accept,
                    click: function() {
                        window.location.href = mobilepay.href;
                        $(this).dialog("close");
                    }
                },
                {
                    id: "Cancel",
                    text: mobilepay.cancel,
                    click: function () {
                        $(this).dialog('close');
                    }
                }
            ]
        });
        mobilepay.width = $(window).width();
        mobilepay.height = $(window).height();
    };

    $('a.mobilepay-checkout').click(function(event) {
        event.preventDefault();
        mobilepay.href = $(this).attr('href');
        $.get(mobilepay.url, function(data) {
            mobilepay.div = data.replace(/<!-- .* -->/g, '');
            mobilepay.function();
        });
    });

    $(window).resize(function() {
        if (mobilepay.dialog === undefined)
            return;
        if (!mobilepay.dialog.dialog('isOpen'))
            return;
        if (mobilepay.width == $(window).width())
            return;
        if (mobilepay.height == $(window).height())
            return;
        mobilepay.dialog.remove();
        mobilepay.function();
    });
});
