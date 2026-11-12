/**
 * Terminliste: Schnelleinstellungen direkt in der Zeile. Bild wählen, tauschen oder entfernen,
 * Status umstellen, Ticket-Link und Preis eintragen. Jede Änderung wird sofort gespeichert.
 */
( function () {
	'use strict';

	var config = window.cttList || {};
	var text = config.text || {};
	var list = document.getElementById( 'the-list' );
	if ( ! list || ! config.action || ! window.ajaxurl ) {
		return;
	}

	function speak( message ) {
		if ( window.wp && wp.a11y && wp.a11y.speak ) {
			wp.a11y.speak( message );
		}
	}

	// Kurze Rückmeldung in der Zelle: Haken bei Erfolg, Meldung bei Fehler.
	function flash( cell, ok, message ) {
		var old = cell.querySelector( '.ctt-quick-flash' );
		if ( old ) {
			old.remove();
		}
		var note = document.createElement( 'span' );
		note.className = 'ctt-quick-flash' + ( ok ? '' : ' ctt-quick-flash--error' );
		note.textContent = ok ? text.saved : message || text.failed;
		cell.appendChild( note );
		speak( note.textContent );
		window.setTimeout(
			function () {
				note.remove();
			},
			ok ? 1600 : 5000
		);
	}

	// Speichert ein Feld und liefert den neuen Zelleninhalt.
	function save( postId, field, value ) {
		var body = new FormData();
		body.append( 'action', config.action );
		body.append( 'nonce', config.nonce );
		body.append( 'post', postId );
		body.append( 'field', field );
		body.append( 'value', value );

		return window
			.fetch( window.ajaxurl, { method: 'POST', credentials: 'same-origin', body: body } )
			.then( function ( response ) {
				return response.json().catch( function () {
					return { success: false };
				} );
			} )
			.then( function ( result ) {
				if ( ! result || ! result.success ) {
					throw new Error( ( result && result.data && result.data.message ) || text.failed );
				}
				return result.data.html;
			} );
	}

	// Speichert und ersetzt danach den Zelleninhalt. Währenddessen ist die Zelle gesperrt.
	function update( cell, postId, field, value, focusSelector ) {
		cell.setAttribute( 'aria-busy', 'true' );
		cell.classList.add( 'is-saving' );
		return save( postId, field, value )
			.then( function ( html ) {
				cell.innerHTML = html;
				flash( cell, true );
				var target = focusSelector ? cell.querySelector( focusSelector ) : null;
				if ( target ) {
					target.focus();
				}
				return true;
			} )
			.catch( function ( error ) {
				flash( cell, false, error.message );
				return false;
			} )
			.then( function ( ok ) {
				cell.removeAttribute( 'aria-busy' );
				cell.classList.remove( 'is-saving' );
				return ok;
			} );
	}

	/* Bild ------------------------------------------------------------------ */

	function pickImage( button ) {
		if ( ! window.wp || ! wp.media ) {
			return;
		}
		var cell = button.closest( 'td' );
		var postId = button.getAttribute( 'data-post' );
		var current = parseInt( button.getAttribute( 'data-image' ), 10 ) || 0;
		var frame = wp.media( {
			title: text.imageTitle,
			button: { text: text.imageButton },
			library: { type: 'image' },
			multiple: false,
		} );

		frame.on( 'open', function () {
			// Mit der Mediathek beginnen: Meist wird ein vorhandenes Plakat für weitere Termine gewählt.
			// Neue Bilder lassen sich dort hineinziehen oder über „Dateien hochladen“ ergänzen.
			if ( frame.content && frame.content.mode ) {
				frame.content.mode( 'browse' );
			}
			if ( current ) {
				var attachment = wp.media.attachment( current );
				attachment.fetch();
				frame.state().get( 'selection' ).add( attachment );
			}
		} );
		frame.on( 'select', function () {
			var chosen = frame.state().get( 'selection' ).first();
			if ( chosen && chosen.id !== current ) {
				update( cell, postId, 'image', chosen.id, '.ctt-quick-image__pick' );
			}
		} );
		frame.open();
	}

	/* Ticket-Link und Preis ------------------------------------------------- */

	// Felder, die sich über ein kleines Eingabefeld unter dem Button ändern lassen.
	var fields = {
		ticket: {
			field: 'ticket_url',
			label: text.ticketLabel,
			help: text.ticketHelp,
			placeholder: 'https://',
			inputMode: 'url',
			focus: '.ctt-quick-ticket__edit',
		},
		price: {
			field: 'price',
			label: text.priceLabel,
			help: text.priceHelp,
			placeholder: text.pricePlaceholder || '',
			inputMode: 'decimal',
			focus: '.ctt-quick-price__edit',
		},
	};

	var popover = null;

	function closePopover( restoreFocus ) {
		if ( ! popover ) {
			return;
		}
		var button = popover.button;
		popover.element.remove();
		popover = null;
		button.setAttribute( 'aria-expanded', 'false' );
		if ( restoreFocus && document.body.contains( button ) ) {
			button.focus();
		}
	}

	function openField( button, options ) {
		if ( popover && popover.button === button ) {
			closePopover( true );
			return;
		}
		closePopover( false );

		var cell = button.closest( 'td' );
		var postId = button.getAttribute( 'data-post' );
		var id = 'ctt-quick-' + options.field + '-' + postId;

		var box = document.createElement( 'div' );
		box.className = 'ctt-quick-pop';
		box.setAttribute( 'role', 'group' );
		box.setAttribute( 'aria-label', options.label );

		var label = document.createElement( 'label' );
		label.setAttribute( 'for', id );
		label.textContent = options.label;

		// Ohne name-Attribut: Das Feld gehört nicht zum Formular der Liste.
		var input = document.createElement( 'input' );
		input.type = 'text';
		input.id = id;
		input.inputMode = options.inputMode;
		input.autocomplete = 'off';
		input.spellcheck = false;
		input.placeholder = options.placeholder;
		input.value = button.getAttribute( 'data-value' ) || '';

		var help = document.createElement( 'p' );
		help.className = 'ctt-quick-pop__help';
		help.textContent = options.help;

		var actions = document.createElement( 'div' );
		actions.className = 'ctt-quick-pop__actions';
		var ok = document.createElement( 'button' );
		ok.type = 'button';
		ok.className = 'button button-primary';
		ok.textContent = text.save;
		var cancel = document.createElement( 'button' );
		cancel.type = 'button';
		cancel.className = 'button';
		cancel.textContent = text.cancel;
		actions.appendChild( ok );
		actions.appendChild( cancel );

		box.appendChild( label );
		box.appendChild( input );
		box.appendChild( help );
		box.appendChild( actions );
		button.parentNode.appendChild( box );
		button.setAttribute( 'aria-expanded', 'true' );
		popover = { element: box, button: button };

		function submit() {
			var value = input.value.trim();
			if ( value === ( button.getAttribute( 'data-value' ) || '' ) ) {
				closePopover( true );
				return;
			}
			ok.disabled = true;
			save( postId, options.field, value )
				.then( function ( html ) {
					popover = null;
					cell.innerHTML = html;
					flash( cell, true );
					var next = cell.querySelector( options.focus );
					if ( next ) {
						next.focus();
					}
				} )
				.catch( function ( error ) {
					ok.disabled = false;
					help.textContent = error.message;
					help.classList.add( 'ctt-quick-pop__help--error' );
					speak( error.message );
					input.focus();
				} );
		}

		ok.addEventListener( 'click', submit );
		cancel.addEventListener( 'click', function () {
			closePopover( true );
		} );
		box.addEventListener( 'keydown', function ( event ) {
			if ( event.key === 'Enter' && event.target === input ) {
				// Sonst schickt die Eingabetaste das Formular der Liste ab.
				event.preventDefault();
				submit();
			} else if ( event.key === 'Escape' ) {
				event.preventDefault();
				event.stopPropagation();
				closePopover( true );
			}
		} );

		input.focus();
		input.select();
	}

	document.addEventListener( 'click', function ( event ) {
		if ( popover && ! popover.element.contains( event.target ) && ! popover.button.contains( event.target ) ) {
			closePopover( false );
		}
	} );

	/* Ereignisse der Liste --------------------------------------------------- */

	list.addEventListener( 'click', function ( event ) {
		var control = event.target.closest ? event.target.closest( '[data-ctt-quick]' ) : null;
		if ( ! control || control.tagName === 'SELECT' ) {
			return;
		}
		var kind = control.getAttribute( 'data-ctt-quick' );
		if ( kind === 'image' ) {
			event.preventDefault();
			pickImage( control );
		} else if ( kind === 'image-remove' ) {
			event.preventDefault();
			update( control.closest( 'td' ), control.getAttribute( 'data-post' ), 'image', 0, '.ctt-quick-image__pick' );
		} else if ( fields[ kind ] ) {
			event.preventDefault();
			openField( control, fields[ kind ] );
		}
	} );

	list.addEventListener( 'change', function ( event ) {
		var select = event.target;
		if ( ! select.matches || ! select.matches( 'select[data-ctt-quick="status"]' ) ) {
			return;
		}
		var cell = select.closest( 'td' );
		var before = select.getAttribute( 'data-status' ) || '';
		var value = select.value;
		select.disabled = true;
		save( select.getAttribute( 'data-post' ), 'status', value )
			.then( function () {
				select.setAttribute( 'data-status', value );
				flash( cell, true );
			} )
			.catch( function ( error ) {
				select.value = before;
				flash( cell, false, error.message );
			} )
			.then( function () {
				select.disabled = false;
				select.focus();
			} );
	} );
}() );
