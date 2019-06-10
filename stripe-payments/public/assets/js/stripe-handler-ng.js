jQuery(document).ready(function ($) {

    stripeHandlerNG.toggle_spinner = function (btnId, show) {
	if (show) {
	    $('div#asp-all-buttons-container-' + btnId).hide();
	    $('div#asp-btn-spinner-container-' + btnId).show();
	} else {
	    $('div#asp-btn-spinner-container-' + btnId).hide();
	    $('div#asp-all-buttons-container-' + btnId).show();
	}
    };

    stripeHandlerNG.doCheckout = function (itemData) {
	var payloadData = {
	    'action': 'asp_ng_get_token',
	    'product_id': itemData.productId,
	    'current_url': stripeHandlerNG.current_url,
	    'is_live': itemData.is_live
	};

	var stripe = Stripe(stripeHandlerNG.pubKey);

	$.post(stripeHandlerNG.ajaxURL, payloadData, function (response) {
	    console.log(response);
	    if (response.success) {
		stripe.redirectToCheckout({
		    sessionId: response.checkoutSessionId
		}).then(function (result) {
		    alert(stripeHandlerNG.strErrorOccurred + ': ' + result.error.message);
		    stripeHandlerNG.toggle_spinner(itemData.btnId, false);
		});
	    } else {
		alert(stripeHandlerNG.strErrorOccurred + ': ' + response.errMsg);
		stripeHandlerNG.toggle_spinner(itemData.btnId, false);
	    }
	}).fail(function (res) {
	    alert(stripeHandlerNG.strErrorOccurred + ': ' + res.responseText);
	    stripeHandlerNG.toggle_spinner(itemData.btnId, false);
	});
    };

    $('[data-asp-ng-button-id]').click(function (e) {
	e.preventDefault();
	var btnId = $(this).data('asp-ng-button-id');
	stripeHandlerNG.toggle_spinner(btnId, true);
	var itemData = window['aspItemDataNG' + btnId];
	stripeHandlerNG.doCheckout(itemData);
    });

});