jQuery(document).ready(function ($) {
	var aspVariationsGroups = aspEditProdData.varGroups;
	var aspVariationsNames = aspEditProdData.varNames;
	var aspVariationsPrices = aspEditProdData.varPrices;
	var aspVariationsUrls = aspEditProdData.varUrls;
	var aspVariationsOpts = aspEditProdData.varOpts;
	var aspVariationsGroupsId = 0;
	function asp_create_variations_group(aspGroupId, groupName, focus) {
		$('span.asp-variations-no-variations-msg').hide();
		var tpl_html = $('div.asp-html-tpl-variations-group').html();
		tpl_html = $.parseHTML(tpl_html);
		$(tpl_html).find('input.asp-variations-group-name').attr('name', 'asp-variations-group-names[' + aspGroupId + ']');
		$(tpl_html).find('input.asp-variations-group-name').val(groupName);
		var displayType = 0;
		if (typeof aspVariationsOpts[aspGroupId] !== 'undefined') {
			displayType = aspVariationsOpts[aspGroupId];
		}
		$(tpl_html).find('select.asp-variations-display-type').attr('name', 'asp-variations-opts[' + aspGroupId + ']');
		$(tpl_html).find('select.asp-variations-display-type').val(displayType);
		$(tpl_html).closest('div.asp-variations-group-cont').attr('data-asp-group-id', aspGroupId);
		$('div#asp-variations-cont').append(tpl_html);
		if (focus) {
			asp_add_variation(aspGroupId, '', 0, '', false);
			$(tpl_html).find('input.asp-variations-group-name').focus();
		}
	}
	function asp_add_variation(aspGroupId, variationName, variationPrice, variationUrl, focus) {
		var tpl_html = $('table.asp-html-tpl-variation-row tbody').html();
		tpl_html = $.parseHTML(tpl_html);
		$(tpl_html).find('input.asp-variation-name').attr('name', 'asp-variation-names[' + aspGroupId + '][]');
		$(tpl_html).find('input.asp-variation-name').val(variationName);
		$(tpl_html).find('input.asp-variation-price').attr('name', 'asp-variation-prices[' + aspGroupId + '][]');
		$(tpl_html).find('input.asp-variation-price').val(variationPrice);
		$(tpl_html).find('input.asp-variation-url').attr('name', 'asp-variation-urls[' + aspGroupId + '][]');
		$(tpl_html).find('input.asp-variation-url').val(variationUrl);
		$('div.asp-variations-group-cont[data-asp-group-id="' + aspGroupId + '"]').find('table.asp-variations-tbl').append(tpl_html);
		if (focus) {
			$(tpl_html).find('input.asp-variation-name').focus();
		}
	}
	$('button#asp-create-variations-group-btn').click(function (e) {
		e.preventDefault();
		asp_create_variations_group(aspVariationsGroupsId, '', true);
		aspVariationsGroupsId++;
	});
	$(document).on('click', 'button.asp-variations-delete-group-btn', function (e) {
		e.preventDefault();
		if (!confirm(aspEditProdData.str.groupDeleteConfirm)) {
			return false;
		}
		$(this).closest('div.asp-variations-group-cont').remove();
		if ($('div.asp-variations-group-cont').length <= 1) {
			$('span.asp-variations-no-variations-msg').show();
		}
	});
	$(document).on('click', 'button.asp-variations-delete-variation-btn', function (e) {
		e.preventDefault();
		if (!confirm(aspEditProdData.str.varDeleteConfirm)) {
			return false;
		}
		$(this).closest('tr').remove();
	});
	$(document).on('click', 'button.asp-variations-add-variation-btn', function (e) {
		e.preventDefault();
		var aspGroupId = $(this).closest('div.asp-variations-group-cont').data('asp-group-id');
		asp_add_variation(aspGroupId, '', 0, '', true);
	});
	$(document).on('click', 'button.asp-variations-select-from-ml-btn', function (e) {
		e.preventDefault();
		var asp_selectVarFile = wp.media({
			title: 'Select File',
			button: {
				text: 'Insert'
			},
			multiple: false
		});
		var buttonEl = $(this);
		asp_selectVarFile.open();
		asp_selectVarFile.on('select', function () {
			var attachment_var = asp_selectVarFile.state().get('selection').first().toJSON();
			$(buttonEl).closest('tr').children().find('input.asp-variation-url').val(attachment_var.url);
		});
		return false;
	});
	if (aspVariationsGroups.length !== 0) {
		$.each(aspVariationsGroups, function (index, item) {
			aspVariationsGroupsId = index;
			asp_create_variations_group(index, item, false);
			if (aspVariationsNames !== null) {
				$.each(aspVariationsNames[index], function (index, item) {
					asp_add_variation(aspVariationsGroupsId, item, aspVariationsPrices[aspVariationsGroupsId][index], aspVariationsUrls[aspVariationsGroupsId][index], false);
				});
			}
		});
		aspVariationsGroupsId++;
	}
	$('input[name="asp_product_collect_billing_addr"]').change(function () {
		var checked = $(this).is(':checked');
		$('input[data-addr-radio="1"]').prop('disabled', !checked);
	});

	$('a.wp-asp-product-menu-nav-item').on('click', function (e) {
		e.preventDefault();
		if ($(this).hasClass('nav-tab-active')) {
			if (!$('#wp-asp-product-settings-menu-icon').is(':visible')) {
				return false;
			}
			$('#wp-asp-product-settings-menu-icon').click();
			return;
		}
		if ($('#wp-asp-product-settings-menu-icon').hasClass('menu-visible')) {
			$('#wp-asp-product-settings-menu-icon').click();
		}
		var itemId = $(this).data('asp-nav-item');
		$('.wp-asp-product-tab-item').removeClass('wp-asp-product-tab-item-visible');
		$('#' + itemId).addClass('wp-asp-product-tab-item-visible');
		$('a.wp-asp-product-menu-nav-item').removeClass('nav-tab-active');
		$(this).addClass('nav-tab-active');

	});

	$('#wp-asp-product-settings-menu-icon').on('click', function (e) {
		if ($(this).hasClass('menu-visible')) {
			$('a.wp-asp-product-menu-nav-item').css('display', 'none');
			$(this).find('span').removeClass('dashicons-menu-alt').addClass('dashicons-menu');
			$(this).removeClass('menu-visible');
		} else {
			$(this).addClass('menu-visible');
			$(this).find('span').removeClass('dashicons-menu').addClass('dashicons-menu-alt');
			$('a.wp-asp-product-menu-nav-item').css('display', 'block');
		}
	});

	$('input[name="asp_product_price"]').on('change', function (e) {
		$('input[name="asp_product_hide_amount_input"]').prop('disabled', !($(this).val() == 0));
	});
	$('input[name="asp_product_price"]').trigger('change');
});