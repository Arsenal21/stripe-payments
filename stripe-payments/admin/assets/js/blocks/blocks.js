var el = wp.element.createElement,
	registerBlockType = wp.blocks.registerBlockType,
	ServerSideRender = wp.components.ServerSideRender,
	SelectControl = wp.components.SelectControl,
	InspectorControls = wp.editor.InspectorControls;

registerBlockType('stripe-payments/block', {
    title: aspBlockProdStr.title,
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
			label: aspBlockProdStr.product,
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