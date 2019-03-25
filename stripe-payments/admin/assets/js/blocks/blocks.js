var el = wp.element.createElement,
	registerBlockType = wp.blocks.registerBlockType,
	ServerSideRender = wp.components.ServerSideRender,
	TextControl = wp.components.TextControl,
	SelectControl = wp.components.SelectControl,
	InspectorControls = wp.editor.InspectorControls;

registerBlockType('stripe-payments/block', {
    title: 'Stripe Payments',
    icon: 'book-alt',
    category: 'common',

    edit: function (props) {
	return [
	    el(ServerSideRender, {
		block: 'stripe-payments/block',
		attributes: props.attributes,
	    }),
	    el(InspectorControls, {},
		    el(SelectControl, {
			label: 'Product',
			value: props.attributes.prodId,
			options: aspProdOpts,
			onChange: (value) => {
			    props.setAttributes({prodId: value});
			},
		    })
		    ),
	];
    },

    save: function () {
	return null;
    },
});