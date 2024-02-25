jQuery(document).ready(function ($) {
	var aspVariationsGroups = aspEditProdData.varGroups;
	var aspVariationsNames = aspEditProdData.varNames;
	var aspVariationsPrices = aspEditProdData.varPrices;
	var aspVariationsUrls = aspEditProdData.varUrls;
	var aspVariationsOpts = aspEditProdData.varOpts;
	var aspVariationsGroupsId = 0;
	var aspVariationsVarId = 0;

	function asp_variations_get_type(groupId) {
		var varType = 0;
		if (typeof aspVariationsOpts[groupId] !== 'undefined') {
			if (typeof aspVariationsOpts[groupId] === 'object') {
				varType = aspVariationsOpts[groupId].type;
			} else {
				varType = aspVariationsOpts[groupId];
			}
		}
		return varType;
	}

	function asp_create_variations_group(aspGroupId, groupName, focus) {
		$('span.asp-variations-no-variations-msg').hide();
		var tpl_html = $('div.asp-html-tpl-variations-group').html();
		tpl_html = $.parseHTML(tpl_html);
		$(tpl_html).find('input.asp-variations-group-name').attr('name', 'asp-variations-group-names[' + aspGroupId + ']');
		$(tpl_html).find('input.asp-variations-group-name').val(groupName);
		var displayType = asp_variations_get_type(aspGroupId);

		$(tpl_html).find('select.asp-variations-display-type').attr('name', 'asp-variations-opts[' + aspGroupId + '][type]');
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

		var var_opt_tpl = $('div.asp-html-tpl-variation-options-2').html();
		var_opt_tpl = var_opt_tpl.replace(/%_group_id_%/g, aspGroupId);
		var_opt_tpl = $.parseHTML(var_opt_tpl);
		$(var_opt_tpl).find('input').prop('disabled', false);
		$(tpl_html).find('input.asp-variation-name').after(var_opt_tpl);

		$('div.asp-variations-group-cont[data-asp-group-id="' + aspGroupId + '"]').find('table.asp-variations-tbl').append(tpl_html);

		var varType = $(tpl_html).closest('.asp-variations-group-cont').find('select.asp-variations-display-type').val();
		$(tpl_html).closest('.asp-variations-group-cont').find('div[data-asp-var-type="' + varType + '"]').show();

		if (focus) {
			$(tpl_html).find('input.asp-variation-name').focus();
		} else {
			if (varType === '2') {
				if (aspVariationsOpts[aspGroupId][aspVariationsVarId]['checked'] === '1') {
					$(var_opt_tpl).find('input[type="checkbox"]').prop('checked', true);
					$(var_opt_tpl).find('input[type="checkbox"]').trigger('change');
				}
			}
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
		
		const variationTable = $(this).closest('table');

		$(this).closest('tr').remove();
		
		// Check if it was the last variation item. If so, remove the variation group as well.
		if (variationTable.children('tr').length < 1) {
			variationTable.closest('.asp-variations-group-cont').remove();
		}
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
	$(document).on('change', 'input.asp-variations-opts-checked', function (e) {
		$(this).siblings('input.asp-variations-opts-checked-hidden').attr('disabled', $(this).prop('checked'));
	});
	$(document).on('change', 'select.asp-variations-display-type', function (e) {
		$(this).closest('.asp-variations-group-cont').find('div[data-asp-var-type]').hide();
		$(this).closest('.asp-variations-group-cont').find('div[data-asp-var-type="' + $(this).val() + '"]').show();
	});

	if (aspVariationsGroups.length !== 0) {
		$.each(aspVariationsGroups, function (index, item) {
			aspVariationsGroupsId = index;
			asp_create_variations_group(index, item, false);
			if (aspVariationsNames !== null) {
				aspVariationsVarId = 0;
				$.each(aspVariationsNames[index], function (index, item) {
					asp_add_variation(aspVariationsGroupsId, item, aspVariationsPrices[aspVariationsGroupsId][index], aspVariationsUrls[aspVariationsGroupsId][index], false);
					aspVariationsVarId++;
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

	var aspPriceInputChange = function () {
		$('input[name="asp_product_hide_amount_input"]').prop('disabled', !($(this).val() == 0));
	};

	$('input[name="asp_product_price"]').on('change', aspPriceInputChange);
	$('input[name="asp_product_price"]').on('keyup', aspPriceInputChange);

	$('input[name="asp_product_price"]').trigger('change');

	$('input[name="asp_product_type_radio"]').on('change', function (e) {
		aspProductTypeChange(this.value);
	});
	aspProductTypeChange($('input[name="asp_product_type_radio"]:checked').val());

	$('input[name="asp_use_other_stripe_acc"]').on('change', function (e) {
		$('input[data-asp-other-acc]').prop('disabled', !this.checked);
	});
	$('input[name="asp_use_other_stripe_acc"]').trigger('change');

	$('input[name="asp_product_enable_stock"]').on('change', function (e) {
		$('input[name="asp_product_show_remaining_items"]').prop('disabled', !this.checked);
	});
	$('input[name="asp_product_enable_stock"]').trigger('change');

	function aspProductTypeChange(val) {
		if (val === 'subscription') {
			$('.asp-other-stripe-acc').hide();
			if (aspTaxVarData.disabledForSub) {
				jQuery('#wp-asp-tax-variations-cont').hide();
				jQuery('#wp-asp-tax-variations-disabled-msg').show();
			}
		} else {
			$('.asp-other-stripe-acc').show();
			if (aspTaxVarData.disabledForSub) {
				jQuery('#wp-asp-tax-variations-cont').show();
				jQuery('#wp-asp-tax-variations-disabled-msg').hide();
			}
		}
	}

	jQuery('#wp-asp-tax-variations-add-btn').click(function (e) {
		e.preventDefault();
		var tplLine = aspTaxVarData.tplLine;
		tplLine = tplLine.replaceAll('%1$s', aspTaxVarData.cOpts);
		tplLine = tplLine.replaceAll('%2$s', 0);
		tplLine = tplLine.replaceAll('%4$s', 'display:none;');
		tplLine = tplLine.replaceAll('%5$s', 'display:none;');
		tplLine = tplLine.replaceAll('%7$s', 'disabled');
		tplLine = tplLine.replaceAll('%8$s', 'disabled');
		tplLine = tplLine.replaceAll(/%[0-9]*\$s/g, '');
		var tplLineHide = jQuery(tplLine).css('display', 'none');
		jQuery('#wp-asp-tax-variations-tbl').find('tbody').append(tplLineHide);
		jQuery('#wp-asp-tax-variations-tbl').show();
		tplLineHide.fadeIn(200);
	});

	jQuery('#wp-asp-tax-variations-tbl').on('click', 'button.wp-asp-tax-variations-del-btn', function (e) {
		e.preventDefault();
		if (confirm(aspTaxVarData.str.delConfirm)) {
			jQuery(this).closest('tr').fadeOut(300, function () { jQuery(this).remove(); });
			
			// Check if the variation table gets empty. If so, hide the table.
			const tableBody = jQuery('#wp-asp-tax-variations-tbl tbody tr');
			if(tableBody.length < 2){
				jQuery('#wp-asp-tax-variations-tbl').fadeOut(300);
			}
		}
	});

	jQuery('#wp-asp-tax-variations-tbl').on('change', 'select.wp-asp-tax-variation-base', function (e) {
		var selBase = jQuery(this).val();
		jQuery(this).closest('tr').find('div').hide();
		jQuery(this).closest('tr').find('div').find('input,select').prop('disabled', true);
		jQuery(this).closest('tr').find('.wp-asp-tax-variation-cont-type-' + selBase).show();
		jQuery(this).closest('tr').find('.wp-asp-tax-variation-cont-type-' + selBase).find('input,select').prop('disabled', false);
	});

});