var stripeHandlerNG = function (data) {

	this.handleModal = function (show) {
		if (!parent.modal) {
			parent.modal = jQuery('div[data-asp-iframe-prod-id="' + parent.data.product_id + '"]');
			if (parent.modal.length === 0) {
				jQuery('body').append('<div id="asp-payment-popup-' + parent.data.uniq_id + '" style="display: none;" data-asp-iframe-prod-id="' + parent.data.product_id + '" class="asp-popup-iframe-cont"><iframe frameborder="0" allowtransparency="true" class="asp-popup-iframe" allow="payment" allowpaymentrequest="true" src="' + parent.data.iframe_url + '"></iframe></div>');
				parent.modal = jQuery('#asp-payment-popup-' + parent.data.uniq_id);
			}
			if (show) {
				parent.modal.css('display', 'flex').hide().fadeIn();
			}
			var iframe = parent.modal.find('iframe');
			iframe.on('load', function () {
				if (parent.redirectToResult) {
					window.location.href = iframe[0].contentWindow.location.href;
					return false;
				}
				iframe[0].contentWindow['doSelfSubmit'] = data.doSelfSubmit;
				var closebtn = iframe.contents().find('#modal-close-btn');
				if (show) {
					closebtn.fadeIn();
				} else {
					closebtn.css('display', 'inline');
				}
				closebtn.on('click', function () {
					jQuery('html').css('overflow', parent.documentElementOrigOverflow);
					parent.modal.fadeOut();
				});
				parent.iForm = iframe.contents().find('form#payment-form');
				parent.iForm.on('submit', function (e) {
					e.preventDefault();
					var token = parent.iForm.find('input#payment-intent').val();
					if (token !== '') {
						if (parent.form.length === 0) {
							console.log('Waiting for iframe to complete loading');
							parent.redirectToResult = true;
							return true;
						}
						var hiddenInputsDiv = parent.form.find('div.asp-child-hidden-fields');
						parent.iForm.find('[name!=""]').each(function () {
							if (jQuery(this).attr('name')) {
								jQuery(this).attr('name', 'asp_' + jQuery(this).attr('name'));
								var clonedItem = jQuery(this).clone();
								if (jQuery(this).is('select')) {
									clonedItem.prop('selectedIndex', jQuery(this).prop('selectedIndex'));
								}
								hiddenInputsDiv.append(clonedItem);
							}
						});
						console.log('Parent form submit');
						parent.form.submit();
					}
					return false;
				});
			});
		} else {
			parent.modal.css('display', 'flex').hide().fadeIn();
		}

	};

	var parent = this;
	parent.preload = false;
	parent.data = data;
	parent.form = jQuery('form#asp_ng_form_' + parent.data.uniq_id);
	parent.documentElementOrigOverflow = jQuery('html').css('overflow');
	jQuery('#asp_ng_button_' + parent.data.uniq_id).prop('disabled', false);
	if (parent.preload) {
		parent.handleModal(false);
	}
	jQuery('#asp_ng_button_' + parent.data.uniq_id).click(function (e) {
		jQuery('html').css('overflow', 'hidden');
		e.preventDefault();
		parent.handleModal(true);
	});
};

function WPASPDocReady(callbackFunc) {
	if (document.readyState !== 'loading') {
		callbackFunc();
	} else if (document.addEventListener) {
		document.addEventListener('DOMContentLoaded', callbackFunc);
	} else {
		document.attachEvent('onreadystatechange', function () {
			if (document.readyState === 'complete') {
				callbackFunc();
			}
		});
	}
}

WPASPDocReady(function () {
	if (typeof wpaspInitOnDocReady !== 'undefined') {
		console.log('ASP: Creating buttons on page load');
		wpaspInitOnDocReady.forEach(function (data) {
			new stripeHandlerNG(data);
		});
	}
	jQuery('[class*="asp-attach-product-"]').each(function (id, el) {
		var classStr = jQuery(el).attr('class');
		var meinClass = classStr.match(/asp-attach-product-[0-9]*/);
		if (meinClass[0]) {
			var productId = meinClass[0].match(/([0-9].*)/);
			if (productId[0]) {
				WPASPAttach(el, productId[0]);
			}
		}
	});

	function WPASPAttach(el, prodId) {
		var uniqId = Math.random().toString(36).substr(2, 9);
		var sg = new stripeHandlerNG({ 'uniq_id': uniqId, 'product_id': prodId, 'doSelfSubmit': true, 'iframe_url': wpASPNG.iframeUrl + '&product_id=' + prodId });
		jQuery(el).on('click', function (e) {
			e.preventDefault();
			sg.handleModal(true);
		});
	}

});