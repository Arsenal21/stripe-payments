jQuery(function ($) {
	$('a.asp-order-action').click(function (e) {
		e.preventDefault();
		var order_id = $(this).data('order-id');
		var nonce = $(this).data('nonce');
		var action = $(this).data('action');
		var confirm_msg = action === 'confirm' ? aspOrdersVars.str.confirmCapture : aspOrdersVars.str.confirmCancel;
		if (confirm(confirm_msg.replace('%s', order_id))) {
			var status_td = $(this).closest('td');
			var status_html = status_td.html();
			status_td.html('<span class="spinner is-active" style="float: left;"></span>');
			var ajax_action = 'asp_order_capture_' + action;
			var req = jQuery.ajax({
				url: ajaxurl,
				type: 'post',
				data: { action: ajax_action, 'order_id': order_id, 'nonce': nonce }
			});
			req.done(function (data) {
				console.log(data);
				if (data.success) {
				} else {
					alert(data.err_msg);
				}
				status_td.html(status_html);
				if (data.order_status) {
					console.log(data.order_status);
					status_td.html(data.order_status);
				}
			});
			req.fail(function (data) {
				alert(aspOrdersVars.str.errorOccurred);
				status_td.html(status_html);
			});
		}
	});
});