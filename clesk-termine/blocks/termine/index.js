/**
 * Block „Termine“ im Editor. Ohne Build-Schritt: nutzt die globalen wp.*-Pakete.
 */
( function ( wp ) {
	'use strict';

	var el = wp.element.createElement;
	var Fragment = wp.element.Fragment;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var c = wp.components;
	var ServerSideRender = wp.serverSideRender;
	var data = window.cleskTermine || {};

	var designOptions = [
		{ label: 'Karten', value: 'karten' },
		{ label: 'Liste', value: 'liste' },
		{ label: 'Tickets', value: 'ticket' },
	];

	var rangeOptions = [
		{ label: 'Kommende Termine', value: 'upcoming' },
		{ label: 'Vergangene Termine', value: 'past' },
		{ label: 'Alle Termine', value: 'all' },
	];

	function programOptions( current ) {
		var list = ( data.programs || [] ).slice();
		if ( current && list.indexOf( current ) === -1 ) {
			list.unshift( current );
		}
		return [ { label: 'Alle Programme', value: '' } ].concat(
			list.map( function ( title ) {
				return { label: title, value: title };
			} )
		);
	}

	function toggle( props, key, label, help ) {
		return el( c.ToggleControl, {
			label: label,
			help: help,
			checked: !! props.attributes[ key ],
			onChange: function ( value ) {
				var next = {};
				next[ key ] = value;
				props.setAttributes( next );
			},
			__nextHasNoMarginBottom: true,
		} );
	}

	function links() {
		if ( ! data.manageUrl ) {
			return null;
		}
		return el(
			'p',
			{ className: 'ctt-editor-links' },
			el( 'a', { href: data.manageUrl, target: '_blank', rel: 'noopener' }, 'Termine verwalten' ),
			' · ',
			el( 'a', { href: data.newUrl, target: '_blank', rel: 'noopener' }, 'Neuer Termin' )
		);
	}

	wp.blocks.registerBlockType( 'clesk/termine', {
		edit: function ( props ) {
			var a = props.attributes;
			var set = props.setAttributes;
			var blockProps = useBlockProps();

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						c.PanelBody,
						{ title: 'Darstellung', initialOpen: true },
						el( c.SelectControl, {
							label: 'Design',
							value: a.design,
							options: designOptions,
							onChange: function ( value ) {
								set( { design: value } );
							},
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
						} ),
						toggle( props, 'groupByMonth', 'Nach Monaten gruppieren' ),
						a.design === 'karten'
							? toggle( props, 'showImages', 'Bilder anzeigen', 'Nutzt das Bild des Termins. Ohne Bild erscheint ein Platzhalter.' )
							: null,
						toggle( props, 'showMap', 'Kartenlink anzeigen', 'Öffnet die Location in Google Maps.' ),
						data.singlePages
							? toggle( props, 'linkDetails', 'Terminseiten verlinken', 'Programm und Info-Symbol führen zur eigenen Seite des Termins.' )
							: null,
						data.singlePages ? toggle( props, 'showShare', 'Teilen-Symbol anzeigen' ) : null,
						el( c.TextControl, {
							label: 'Beschriftung Ticket-Button',
							value: a.ticketLabel,
							onChange: function ( value ) {
								set( { ticketLabel: value } );
							},
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
						} ),
						links()
					),
					el(
						c.PanelBody,
						{ title: 'Filter', initialOpen: true },
						toggle( props, 'filterCity', 'Stadt' ),
						toggle( props, 'filterVenue', 'Location' ),
						toggle( props, 'filterMonth', 'Monat' ),
						el(
							'p',
							{ className: 'components-base-control__help' },
							'Ein Filter erscheint nur, wenn es dafür mindestens zwei verschiedene Werte gibt.'
						)
					),
					el(
						c.PanelBody,
						{ title: 'Auswahl', initialOpen: false },
						el( c.SelectControl, {
							label: 'Zeitraum',
							value: a.range,
							options: rangeOptions,
							onChange: function ( value ) {
								set( { range: value } );
							},
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
						} ),
						el( c.SelectControl, {
							label: 'Programm',
							help: 'Zeigt nur Termine mit genau diesem Titel, z. B. für eine Programmseite.',
							value: a.program,
							options: programOptions( a.program ),
							onChange: function ( value ) {
								set( { program: value } );
							},
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
						} ),
						el( c.RangeControl, {
							label: 'Höchstens anzeigen',
							help: '0 zeigt alle Termine.',
							min: 0,
							max: 50,
							value: a.limit,
							onChange: function ( value ) {
								set( { limit: value || 0 } );
							},
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
						} ),
						el( c.TextControl, {
							label: 'Text, wenn keine Termine anstehen',
							value: a.emptyText,
							onChange: function ( value ) {
								set( { emptyText: value } );
							},
							__nextHasNoMarginBottom: true,
							__next40pxDefaultSize: true,
						} )
					)
				),
				el(
					'div',
					blockProps,
					el(
						c.Disabled,
						null,
						el( ServerSideRender, {
							block: 'clesk/termine',
							attributes: a,
							skipBlockSupportAttributes: true,
						} )
					)
				)
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );
