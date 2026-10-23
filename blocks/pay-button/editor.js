/**
 * The pay button block in the editor: a picker for which button, and the server's own render as the preview.
 * Plain wp.* globals, so there's no build step.
 */
( function ( wp, data ) {
	'use strict';

	const el = wp.element.createElement;
	const __ = wp.i18n.__;
	const { InspectorControls, useBlockProps } = wp.blockEditor;
	const { PanelBody, SelectControl, TextControl, Placeholder, ExternalLink } = wp.components;
	const serverSideRender = wp.serverSideRender;
	const buttons = ( data && data.buttons ) || [];

	const options = [ { value: 0, label: __( 'Choose a button…', 'ifthenpay-pay-button' ) } ].concat(
		buttons.map( function ( button ) {
			return {
				value: button.value,
				label: button.active ? button.label : button.label + ' ' + __( '(off)', 'ifthenpay-pay-button' ),
			};
		} )
	);

	function picker( props ) {
		return el( SelectControl, {
			label: __( 'Pay button', 'ifthenpay-pay-button' ),
			value: props.attributes.buttonId,
			options: options,
			onChange: function ( value ) {
				props.setAttributes( { buttonId: parseInt( value, 10 ) || 0 } );
			},
			__nextHasNoMarginBottom: true,
		} );
	}

	function newLink() {
		return data && data.newUrl
			? el( ExternalLink, { href: data.newUrl }, __( 'Create a pay button', 'ifthenpay-pay-button' ) )
			: null;
	}

	wp.blocks.registerBlockType( 'ifthenpay-pay-button/button', {
		edit: function ( props ) {
			const blockProps = useBlockProps();

			const inspector = el(
				InspectorControls,
				null,
				el(
					PanelBody,
					{ title: __( 'Button', 'ifthenpay-pay-button' ) },
					picker( props ),
					el( TextControl, {
						label: __( 'Text', 'ifthenpay-pay-button' ),
						help: __( 'Leave empty to use the text set on the button.', 'ifthenpay-pay-button' ),
						value: props.attributes.label,
						onChange: function ( value ) {
							props.setAttributes( { label: value } );
						},
						__nextHasNoMarginBottom: true,
					} ),
					newLink()
				)
			);

			if ( ! props.attributes.buttonId ) {
				return el(
					'div',
					blockProps,
					inspector,
					el(
						Placeholder,
						{
							icon: 'money-alt',
							label: __( 'ifthenpay Pay Button', 'ifthenpay-pay-button' ),
							instructions: buttons.length
								? __( 'Pick the pay button to show here.', 'ifthenpay-pay-button' )
								: __( 'You have no pay buttons yet.', 'ifthenpay-pay-button' ),
						},
						buttons.length ? picker( props ) : null,
						newLink()
					)
				);
			}

			return el(
				'div',
				blockProps,
				inspector,
				el( serverSideRender, {
					block: 'ifthenpay-pay-button/button',
					attributes: props.attributes,
					skipBlockSupportAttributes: false,
				} )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp, window.iftpBtnBlock );
