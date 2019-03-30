var el = wp.element.createElement,
	registerBlockType = wp.blocks.registerBlockType,
	ServerSideRender = wp.components.ServerSideRender,
	SelectControl = wp.components.SelectControl,
	ToggleControl = wp.components.ToggleControl,
	InspectorControls = wp.editor.InspectorControls;

registerBlockType('stripe-payments/product-block', {
    title: aspBlockProdStr.title,
    icon: 'products',
    category: 'common',

    edit: function (props) {
	return [
	    el(ServerSideRender, {
		block: 'stripe-payments/product-block',
		attributes: props.attributes,
	    }),
	    el(InspectorControls, {},
		    el(SelectControl, {
			label: aspBlockProdStr.product,
			value: props.attributes.prodId,
			options: aspProdOpts,
			onChange: (value) => {
			    props.setAttributes({prodId: value});
			},
		    })
		    ),
	    el(InspectorControls, {},
		    el(ToggleControl, {
			label: aspBlockProdStr.button_only,
			help: aspBlockProdStr.button_only_help,
			checked: props.attributes.btnOnly,
			onChange: (state) => {
			    props.setAttributes({btnOnly: state});
			},
		    })
		    ),
	];
    },

    save: function () {
	return null;
    },
});