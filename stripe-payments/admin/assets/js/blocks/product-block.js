/**
 * @global wp, aspBlockProdStr
 */

var asp_element = wp.element.createElement,
	asp_registerBlockType = wp.blocks.registerBlockType,
	asp_serverSideRender = wp.serverSideRender,
	asp_selectControl = wp.components.SelectControl,
	asp_toggleControl = wp.components.ToggleControl,
	asp_inspectorControls = wp.blockEditor.InspectorControls,
	asp_panelBody = wp.components.PanelBody,
	asp_useBlockProps = wp.blockEditor.useBlockProps
	;

asp_registerBlockType('stripe-payments/product-block', {
	apiVersion: 3,
	title: aspBlockProdStr.title,
	icon: 'products',
	category: 'widget',

	edit: function (props) {
		var blockProps = asp_useBlockProps();

		return [
			asp_element('div', blockProps,
				asp_element(asp_serverSideRender, {
					block: 'stripe-payments/product-block',
					attributes: props.attributes,
				})
			),

			asp_element(asp_inspectorControls, {},
				asp_element(asp_panelBody, { title: '', initialOpen: true },
					asp_element(asp_selectControl, {
						label: aspBlockProdStr.product,
						value: props.attributes.prodId,
						options: aspProdOpts,
						__next40pxDefaultSize: true,
						__nextHasNoMarginBottom: true,
						onChange: (value) => {
							props.setAttributes({ prodId: value });
						},
					}),

					asp_element(asp_toggleControl, {
						label: aspBlockProdStr.button_only,
						help: aspBlockProdStr.button_only_help,
						checked: props.attributes.btnOnly,
						__nextHasNoMarginBottom: true,
						onChange: (state) => {
							props.setAttributes({ btnOnly: state });
						},
					})
				)
			),
		];
	},

	save: function () {
		return null;
	},
});