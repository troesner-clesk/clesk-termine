/**
 * Termine: Vorschläge im Titelfeld, Stadt und Anschrift zur bekannten Location ergänzen,
 * Veröffentlichen ohne Datum abfangen, nach dem Duplizieren die zu ändernden Felder markieren,
 * am eingeklappten Abschnitt „Weitere Angaben“ nennen, was dort gefüllt ist, und den Preis prüfen.
 */
( function () {
	'use strict';

	var data = window.cttAdmin || {};
	var form = document.querySelector( '.ctt-admin' );
	if ( ! form ) {
		return;
	}

	var title = document.getElementById( 'title' );
	if ( title && document.getElementById( 'ctt-programs' ) ) {
		title.setAttribute( 'list', 'ctt-programs' );
		title.setAttribute( 'autocomplete', 'off' );
	}

	var venue = document.getElementById( 'ctt-venue' );
	var city = document.getElementById( 'ctt-city' );
	var street = document.getElementById( 'ctt-street' );
	var zip = document.getElementById( 'ctt-zip' );
	var places = data.venuePlaces || {};

	// Ein Feld gilt als „frei“, solange es leer ist oder vom Formular selbst gefüllt wurde. Die Anschrift
	// gilt in einer Kopie zusätzlich als frei, solange sie unverändert übernommen ist: Sie gehört zur alten
	// Location. Eine Stadt, die schon im Feld steht, wird dagegen nie überschrieben.
	function track( field, inheritedIsFree ) {
		if ( ! field ) {
			return null;
		}
		var state = { field: field, initial: field.value, typed: false, auto: false, inheritedIsFree: inheritedIsFree };
		field.addEventListener( 'input', function () {
			state.typed = true;
			state.auto = false;
		} );
		return state;
	}

	function isFree( state ) {
		var value = state.field.value.trim();
		return value === '' || state.auto || ( state.inheritedIsFree && !! data.isCopy && ! state.typed && state.field.value === state.initial );
	}

	function fill( state, value ) {
		if ( state && isFree( state ) && state.field.value !== value ) {
			state.field.value = value;
			state.auto = true;
		}
	}

	// Der Abschnitt „Weitere Angaben“ ist eingeklappt. Seine Zeile nennt, welche Angaben gefüllt sind,
	// auch wenn das Formular die Anschrift gerade selbst ergänzt oder geleert hat.
	var filled = document.getElementById( 'ctt-more-filled' );
	var extras = data.extras || {};
	var note = document.getElementById( 'ctt-note' );
	var info = document.getElementById( 'ctt-info' );

	function hasValue( field ) {
		return !! field && field.value.trim() !== '';
	}

	function updateFilled() {
		if ( ! filled ) {
			return;
		}
		var names = [];
		if ( hasValue( note ) ) {
			names.push( extras.note );
		}
		if ( hasValue( street ) || hasValue( zip ) ) {
			names.push( extras.address );
		}
		if ( hasValue( info ) ) {
			names.push( extras.info );
		}
		filled.textContent = names.length ? extras.prefix + ' ' + names.join( ', ' ) : '';
	}

	[ note, street, zip, info ].forEach( function ( field ) {
		if ( field ) {
			field.addEventListener( 'input', updateFilled );
		}
	} );

	var cityState = track( city, false );
	var streetState = track( street, true );
	var zipState = track( zip, true );

	function knownPlaces() {
		var name = venue.value.trim();
		return Object.prototype.hasOwnProperty.call( places, name ) && places[ name ].length ? places[ name ] : null;
	}

	// Ort zur Location: der mit der eingetragenen Stadt, sonst bei eindeutiger Location und freier Stadt der einzige.
	function findPlace( known ) {
		var wanted = city.value.trim().toLowerCase();
		for ( var i = 0; i < known.length; i++ ) {
			if ( known[ i ].city.toLowerCase() === wanted ) {
				return known[ i ];
			}
		}
		return known.length === 1 && isFree( cityState ) ? known[ 0 ] : null;
	}

	function autofill() {
		var known = knownPlaces();
		var place = known ? findPlace( known ) : null;
		if ( place ) {
			fill( cityState, place.city );
			fill( streetState, place.street );
			fill( zipState, place.zip );
		} else {
			// Keine passende bekannte Location mehr: Eine vom Formular ergänzte oder in eine Kopie
			// übernommene Anschrift gehört zur alten Location. Selbst Eingetipptes bleibt stehen.
			fill( streetState, '' );
			fill( zipState, '' );
		}
		updateFilled();
	}

	if ( venue && city ) {
		venue.addEventListener( 'change', autofill );
		city.addEventListener( 'change', autofill );
	}

	var date = document.getElementById( 'ctt-date' );
	var dateError = document.getElementById( 'ctt-date-error' );

	// Wird der Termin mit diesem Absenden öffentlich (oder bleibt er es)? Entscheidend ist der Zielstatus:
	// „Veröffentlichen“ und „Planen“ heißen publish, „Aktualisieren“ und die Enter-Taste senden save
	// und übernehmen den gewählten Status. So bleibt das Zurückstellen auf „Entwurf“ möglich.
	function goesLive( submitter ) {
		if ( ! submitter ) {
			return false;
		}
		if ( submitter.name === 'publish' ) {
			return true;
		}
		if ( submitter.name !== 'save' ) {
			return false;
		}
		var status = document.getElementById( 'post_status' );
		var isPrivate = document.getElementById( 'visibility-radio-private' );
		return ( isPrivate && isPrivate.checked ) || ( !! status && ( status.value === 'publish' || status.value === 'future' ) );
	}

	// Veröffentlichen ohne Datum abfangen. Entwürfe dürfen ohne Datum gespeichert werden.
	// Capture-Phase am Dokument: läuft vor den Submit-Handlern von WordPress, die sonst
	// den Button schon als „wird gespeichert“ markieren würden.
	if ( date ) {
		document.addEventListener(
			'submit',
			function ( event ) {
				if ( ! event.target || event.target.id !== 'post' ) {
					return;
				}
				if ( date.value || ! goesLive( event.submitter ) ) {
					return;
				}
				event.preventDefault();
				event.stopPropagation();

				// Kasten aufklappen und einblenden, falls er eingeklappt oder über „Ansicht anpassen“ ausgeblendet ist.
				var box = date.closest( '.postbox' );
				if ( box ) {
					box.classList.remove( 'closed', 'hide-if-js' );
					box.style.display = 'block';
				}
				if ( dateError ) {
					dateError.hidden = false;
				}
				date.setAttribute( 'aria-invalid', 'true' );
				date.focus();
			},
			true
		);

		date.addEventListener( 'input', function () {
			if ( date.value ) {
				date.removeAttribute( 'aria-invalid' );
				if ( dateError ) {
					dateError.hidden = true;
				}
			}
		} );
	}

	// Preis: Eingabe wie „18,50“, „ab 18 €“ oder „frei“ verstehen und nach dem Verlassen des Feldes so zeigen,
	// wie er gespeichert wird. Unlesbares meldet das Feld, sonst bliebe der Preis beim Speichern still leer.
	var price = document.getElementById( 'ctt-price' );
	var priceError = document.getElementById( 'ctt-price-error' );
	var number = data.number || {};

	// Gleiche Regeln wie Clesk_Termine_Events::sanitize_price(). Liefert '' (leer), die Zahl mit Punkt oder null (unlesbar).
	function parsePrice( raw ) {
		var value = String( raw ).trim().toLowerCase();
		var m, whole, dec;
		if ( value === '' ) {
			return '';
		}
		if ( [ 'frei', 'eintritt frei', 'kostenlos', 'gratis', 'free' ].indexOf( value ) !== -1 ) {
			return '0';
		}
		value = value
			.replace( /^ab/, '' )
			.replace( /euro|eur|€/g, '' )
			.replace( /[\s  ]+/g, '' )
			.replace( /[.,]?[-–—]+$/, '' )
			.replace( /[.,]+$/, '' );
		if ( ( m = /^(\d{1,6})(?:[.,](\d{1,2}))?$/.exec( value ) ) ) {
			whole = m[ 1 ];
		} else if ( ( m = /^(\d{1,3}(?:\.\d{3})+)(?:,(\d{1,2}))?$/.exec( value ) ) || ( m = /^(\d{1,3}(?:,\d{3})+)(?:\.(\d{1,2}))?$/.exec( value ) ) ) {
			whole = m[ 1 ].replace( /[.,]/g, '' );
		} else {
			return null;
		}
		if ( whole.length > 6 ) {
			return null;
		}
		whole = whole.replace( /^0+(?=\d)/, '' );
		dec = ( m[ 2 ] || '' ).replace( /0+$/, '' );
		return dec ? whole + '.' + dec : whole;
	}

	// Wie Clesk_Termine_Render::amount(): ganze Euro ohne, sonst mit zwei Nachkommastellen.
	function formatPrice( value ) {
		var parts = value.split( '.' );
		var whole = parts[ 0 ].replace( /\B(?=(\d{3})+(?!\d))/g, number.thousands || '' );
		return parts.length > 1 ? whole + ( number.decimal || ',' ) + ( parts[ 1 ] + '0' ).slice( 0, 2 ) : whole;
	}

	function showPriceError( show ) {
		if ( priceError ) {
			priceError.hidden = ! show;
		}
		if ( show ) {
			price.setAttribute( 'aria-invalid', 'true' );
		} else {
			price.removeAttribute( 'aria-invalid' );
		}
	}

	if ( price ) {
		price.addEventListener( 'change', function () {
			var parsed = parsePrice( price.value );
			if ( parsed === null ) {
				showPriceError( true );
				return;
			}
			price.value = parsed === '' ? '' : formatPrice( parsed );
			showPriceError( false );
		} );
		price.addEventListener( 'input', function () {
			if ( price.hasAttribute( 'aria-invalid' ) && parsePrice( price.value ) !== null ) {
				showPriceError( false );
			}
		} );
	}

	if ( data.isCopy ) {
		form.classList.add( 'is-copy' );
		if ( date ) {
			date.focus();
		}
	}
} )();
