jQuery(document).ready(function ($) {

    stripeHandlerNG.doCheckout = function (itemData) {
	var payloadData = {
	    'action': 'asp_ng_get_token',
	    'product_id': itemData.productId
	};

	var stripe = Stripe(stripeHandlerNG.pubKey);

	$.post(stripeHandlerNG.ajaxURL, payloadData, function (response) {
	    console.log(response);
	    if (response.success) {
		stripe.redirectToCheckout({
		    sessionId: response.checkoutSessionId
		}).then(function (result) {
		    alert('ERROR OCCURRED! ' + result.error.message);
		    return false;
		});
	    } else {
		alert('ERROR OCCURRED! ' + response.errMsg);
		return false;
	    }
	}).fail(function (res) {
	    alert('ERROR OCCURRED! ' + res.responseText);
	});
    };

    $('[data-asp-ng-button-id]').click(function (e) {
	e.preventDefault();
	var btnId = $(this).data('asp-ng-button-id');
	var itemData = window['aspItemDataNG' + btnId];
	stripeHandlerNG.doCheckout(itemData);
    });

});