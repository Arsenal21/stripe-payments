/**
 * @global wp, aspBlockProdStr
 */

var el = wp.element.createElement,
	registerBlockType = wp.blocks.registerBlockType,
	ServerSideRender = wp.serverSideRender,
	SelectControl = wp.components.SelectControl,
	ToggleControl = wp.components.ToggleControl,
	InspectorControls = wp.blockEditor.InspectorControls,
	PanelBody = wp.components.PanelBody,
	useBlockProps = wp.blockEditor.useBlockProps
	;

registerBlockType('stripe-payments/product-block', {
	apiVersion: 3,
	title: aspBlockProdStr.title,
	icon: 'products',
	category: 'widget',

	edit: function (props) {
		var blockProps = useBlockProps();

		return [
			el('div', blockProps,
				el(ServerSideRender, {
					block: 'stripe-payments/product-block',
					attributes: props.attributes,
				})
			),

			el(InspectorControls, {},
				el(PanelBody, { title: '', initialOpen: true },
					el(SelectControl, {
						label: aspBlockProdStr.product,
						value: props.attributes.prodId,
						options: aspProdOpts,
						__next40pxDefaultSize: true,
						__nextHasNoMarginBottom: true,
						onChange: (value) => {
							props.setAttributes({ prodId: value });
						},
					}),

					el(ToggleControl, {
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