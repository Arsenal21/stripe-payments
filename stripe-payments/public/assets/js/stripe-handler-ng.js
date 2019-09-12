var stripeHandlerNG = function (data) {
	var parent = this;
	parent.data = data;
	parent.form = jQuery('form#asp_ng_form_' + data.uniq_id);
	parent.documentElementOrigOverflow = jQuery('html').css('overflow');
	jQuery('#asp_ng_button_' + data.uniq_id).prop('disabled', false);
	jQuery('#asp_ng_button_' + data.uniq_id).click(function (e) {
		jQuery('html').css('overflow', 'hidden');
		e.preventDefault();
		if (!parent.modal) {
			jQuery('body').append('<div id="asp-payment-popup-' + parent.data.uniq_id + '" class="asp-popup-iframe-cont"><div class="asp-popup-spinner-cont"></div><iframe frameborder="0" allowtransparency="true" class="asp-popup-iframe" src="' + parent.data.iframe_url + '"></iframe></div>');
			parent.modal = jQuery('#asp-payment-popup-' + parent.data.uniq_id);
			parent.modal.css("display", "flex");
			parent.modal.find('.asp-popup-spinner-cont').append(jQuery('div#asp-btn-spinner-container-' + parent.data.uniq_id).html());
			var iframe = parent.modal.find('iframe');
			iframe.on('load', function (e) {
				parent.modal.find('.asp-popup-spinner-cont').hide();
				var aligner = iframe.contents().find('#Aligner');
				closebtn = iframe.contents().find('#modal-close-btn');
				closebtn.fadeIn();
				// aligner.on('click', function (e) {
				//     if (e.target !== e.currentTarget) {
				//         return;
				//     }
				//     parent.modal.fadeOut();
				// })
				closebtn.on('click', function (e) {
					jQuery('html').css('overflow', parent.documentElementOrigOverflow);
					parent.modal.fadeOut();
				})
				parent.iForm = iframe.contents().find('form#payment-form');
				parent.iForm.on('submit', function (e) {
					e.preventDefault();
					var token = parent.iForm.find('input#payment-intent').val();
					if (token !== '') {
						jQuery('div#asp-all-buttons-container-' + parent.data.uniq_id).hide();
						jQuery('div#asp-btn-spinner-container-' + parent.data.uniq_id).show();
						parent.modal.fadeOut();
						var hiddenInputsDiv = parent.form.find('div.asp-child-hidden-fields');
						parent.iForm.find('[name!=""]').each(function (e) {
							if (jQuery(this).attr('name')) {
								jQuery(this).attr('name', 'asp_' + jQuery(this).attr('name'));
								hiddenInputsDiv.append(jQuery(this));
							}
						});
						console.log('Parent form submit');
						jQuery('form#asp_ng_form_' + parent.data.uniq_id).submit();
					}
					return false;
				});
			});
		} else {
			parent.modal.css("display", "flex").hide().fadeIn();
		}
	});
}