/**
 * Block „Termine“ und Terminseiten: Suche, Filter (Stadt, Location, Monat), Teilen und Link kopieren.
 * Ohne JavaScript bleibt die Filterleiste samt Suchfeld verborgen und alle Termine sichtbar,
 * Teilen-Links führen dann zum Abschnitt „Termin teilen“ der Terminseite.
 */
( function () {
	'use strict';

	function toArray( list ) {
		return Array.prototype.slice.call( list );
	}

	/**
	 * Text für den Vergleich: klein, ohne Akzente, ß als ss („Düsseldorf“ → „dusseldorf“).
	 */
	function fold( text ) {
		text = String( text || '' ).toLowerCase().replace( /ß/g, 'ss' );
		if ( text.normalize ) {
			text = text.normalize( 'NFD' ).replace( /[\u0300-\u036f]/g, '' );
		}
		return text.replace( /\s+/g, ' ' ).trim();
	}

	/**
	 * Suchtext eines Termins. Enthält Umlaute zusätzlich in der Schreibweise mit e,
	 * damit auch „duesseldorf“ oder „muenchen“ passen.
	 */
	function haystack( text ) {
		var spelled = String( text || '' )
			.replace( /ä/g, 'ae' ).replace( /ö/g, 'oe' ).replace( /ü/g, 'ue' )
			.replace( /Ä/g, 'Ae' ).replace( /Ö/g, 'Oe' ).replace( /Ü/g, 'Ue' );
		var plain = fold( text );
		var alt = fold( spelled );
		return alt === plain ? plain : plain + ' ' + alt;
	}

	function init( root ) {
		if ( root.getAttribute( 'data-ctt-ready' ) ) {
			return;
		}
		var bar = root.querySelector( '.ctt-filters' );
		if ( ! bar ) {
			return;
		}
		var selects = toArray( bar.querySelectorAll( 'select[data-ctt-filter]' ) );
		var search = bar.querySelector( 'input[data-ctt-search]' );
		if ( ! selects.length && ! search ) {
			return;
		}
		root.setAttribute( 'data-ctt-ready', '1' );

		var items = toArray( root.querySelectorAll( '.ctt-item' ) );
		var groups = toArray( root.querySelectorAll( '.ctt-group' ) );
		var resets = toArray( root.querySelectorAll( '.ctt-reset' ) );
		var barReset = bar.querySelector( '.ctt-reset' );
		var count = bar.querySelector( '.ctt-count' );
		var empty = root.querySelector( '.ctt-empty--filter' );
		var one = root.getAttribute( 'data-ctt-one' ) || '1 Termin';
		var many = root.getAttribute( 'data-ctt-many' ) || '%d Termine';
		var texts = items.map( function ( item ) {
			return haystack( item.getAttribute( 'data-search' ) );
		} );
		var typing = 0;
		var speaking = 0;

		function state() {
			var s = {};
			selects.forEach( function ( select ) {
				if ( select.value ) {
					s[ select.getAttribute( 'data-ctt-filter' ) ] = select.value;
				}
			} );
			return s;
		}

		// Suchwörter; jedes muss im Termin vorkommen.
		function terms() {
			var value = search ? fold( search.value ) : '';
			return value ? value.split( ' ' ) : [];
		}

		function found( index, words ) {
			for ( var i = 0; i < words.length; i++ ) {
				if ( texts[ index ].indexOf( words[ i ] ) === -1 ) {
					return false;
				}
			}
			return true;
		}

		function matches( item, s, skip ) {
			for ( var key in s ) {
				if ( key !== skip && item.getAttribute( 'data-' + key ) !== s[ key ] ) {
					return false;
				}
			}
			return true;
		}

		function announce( text ) {
			if ( count ) {
				count.textContent = text;
			}
		}

		// quiet: beim Tippen die Trefferzahl erst nach einer Pause ansagen, damit Screenreader nicht jede Taste vorlesen.
		function apply( quiet ) {
			var s = state();
			var words = terms();
			var active = Object.keys( s ).length > 0 || words.length > 0;
			var shown = 0;
			var hits = items.map( function ( item, index ) {
				return found( index, words );
			} );

			items.forEach( function ( item, index ) {
				var ok = hits[ index ] && matches( item, s );
				item.hidden = ! ok;
				if ( ok ) {
					shown++;
				}
			} );

			groups.forEach( function ( group ) {
				group.hidden = ! group.querySelector( '.ctt-item:not([hidden])' );
			} );

			// Nur Werte anbieten, die zusammen mit den anderen Filtern und der Suche Treffer haben.
			selects.forEach( function ( select ) {
				var key = select.getAttribute( 'data-ctt-filter' );
				var available = {};
				items.forEach( function ( item, index ) {
					if ( hits[ index ] && matches( item, s, key ) ) {
						available[ item.getAttribute( 'data-' + key ) ] = true;
					}
				} );
				toArray( select.options ).forEach( function ( option ) {
					if ( option.value ) {
						option.disabled = ! available[ option.value ] && option.value !== select.value;
					}
				} );
				var wrap = select.closest( '.ctt-filter' );
				if ( wrap ) {
					wrap.classList.toggle( 'is-active', !! select.value );
				}
			} );

			if ( barReset ) {
				barReset.hidden = ! active;
			}
			if ( empty ) {
				empty.hidden = shown > 0;
			}

			var text = active ? ( shown === 1 ? one : many.replace( '%d', shown ) ) : '';
			window.clearTimeout( speaking );
			if ( quiet && text ) {
				speaking = window.setTimeout( function () {
					announce( text );
				}, 500 );
			} else {
				announce( text );
			}
		}

		function reset() {
			window.clearTimeout( typing );
			selects.forEach( function ( select ) {
				select.value = '';
			} );
			if ( search ) {
				search.value = '';
			}
			apply();
		}

		selects.forEach( function ( select ) {
			select.addEventListener( 'change', function () {
				apply();
			} );
		} );

		if ( search ) {
			search.addEventListener( 'input', function () {
				window.clearTimeout( typing );
				typing = window.setTimeout( function () {
					apply( true );
				}, 150 );
			} );
			search.addEventListener( 'keydown', function ( event ) {
				if ( event.key === 'Enter' ) {
					event.preventDefault();
					window.clearTimeout( typing );
					apply();
				} else if ( ( event.key === 'Escape' || event.key === 'Esc' ) && search.value ) {
					event.preventDefault();
					window.clearTimeout( typing );
					search.value = '';
					apply();
				}
			} );
		}

		resets.forEach( function ( button ) {
			button.addEventListener( 'click', function () {
				reset();
				( search || selects[ 0 ] ).focus();
			} );
		} );

		// Beim Zurückblättern stellt der Browser die Auswahl wieder her, ohne ein Ereignis zu senden.
		window.addEventListener( 'pageshow', function () {
			apply();
		} );

		bar.hidden = false;
		apply();
	}

	// Teilen: Dialog des Geräts, wenn es einen gibt. Sonst folgt der Link zum Abschnitt „Termin teilen“.
	document.addEventListener( 'click', function ( event ) {
		var target = event.target;
		if ( ! target || ! target.closest ) {
			return;
		}

		var share = target.closest( '[data-ctt-share]' );
		if ( share ) {
			if ( navigator.share ) {
				event.preventDefault();
				navigator
					.share( {
						title: share.getAttribute( 'data-ctt-title' ) || document.title,
						text: share.getAttribute( 'data-ctt-text' ) || '',
						url: share.getAttribute( 'data-ctt-url' ) || window.location.href,
					} )
					.catch( function () {} );
			}
			return;
		}

		var copy = target.closest( '[data-ctt-copy]' );
		if ( copy && navigator.clipboard ) {
			navigator.clipboard
				.writeText( copy.getAttribute( 'data-ctt-copy' ) )
				.then( function () {
					copied( copy );
				} )
				.catch( function () {} );
		}
	} );

	function copied( button ) {
		var label = button.querySelector( 'span' );
		var section = button.closest( '.ctt-share' );
		var status = section ? section.querySelector( '.ctt-share__status' ) : null;
		var done = button.getAttribute( 'data-ctt-copied' ) || 'Link kopiert';

		if ( status ) {
			status.textContent = done;
		}
		if ( ! label || button.hasAttribute( 'data-ctt-label' ) ) {
			return;
		}
		button.setAttribute( 'data-ctt-label', label.textContent );
		label.textContent = done;
		button.classList.add( 'is-done' );
		window.setTimeout( function () {
			label.textContent = button.getAttribute( 'data-ctt-label' );
			button.removeAttribute( 'data-ctt-label' );
			button.classList.remove( 'is-done' );
			if ( status ) {
				status.textContent = '';
			}
		}, 2500 );
	}

	function boot() {
		toArray( document.querySelectorAll( '.ctt[data-ctt]' ) ).forEach( init );

		// „Link kopieren“ nur anbieten, wenn der Browser es kann.
		if ( navigator.clipboard && window.isSecureContext ) {
			toArray( document.querySelectorAll( '[data-ctt-copy]' ) ).forEach( function ( button ) {
				button.hidden = false;
			} );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
