( function ( blocks, blockEditor, components, element, i18n, serverSideRender ) {
	'use strict';
	var el = element.createElement;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var SelectControl = components.SelectControl;
	var ServerSideRender = serverSideRender;

	blocks.registerBlockType( 'contactpanda/newsletter-signup', {
		title: i18n.__( 'Panda – Newsletter signup', 'contactpanda' ),
		icon: 'email-alt',
		category: 'widgets',
		attributes: { layout: { type: 'string', default: 'one-line' } },
		edit: function ( props ) {
			return el(
				'div',
				{},
				el( InspectorControls, {}, el( PanelBody, { title: i18n.__( 'Layout', 'contactpanda' ) }, el( SelectControl, {
					label: i18n.__( 'Rows', 'contactpanda' ),
					value: props.attributes.layout,
					options: [
						{ label: i18n.__( 'One line', 'contactpanda' ), value: 'one-line' },
						{ label: i18n.__( 'Two lines', 'contactpanda' ), value: 'two-line' }
					],
					onChange: function ( value ) { props.setAttributes( { layout: value } ); }
				} ) ) ),
				el( ServerSideRender, { block: 'contactpanda/newsletter-signup', attributes: props.attributes } )
			);
		},
		save: function () { return null; }
	} );
} )( window.wp.blocks, window.wp.blockEditor, window.wp.components, window.wp.element, window.wp.i18n, window.wp.serverSideRender );
