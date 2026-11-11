/**
 * Terminseite mit GeneratePress Premium: Der Kopf liegt über dem Inhalt („Merge“), aber kein Seitenkopf folgt.
 * Ist das Menü auf dem Seitenhintergrund nicht lesbar, bekommt der Kopf eine Fläche (Klasse ctt-headband).
 * Steht direkt hinter dem Kopf in der Seite, damit nichts nachträglich umspringt.
 */
( function () {
	var wrap = document.querySelector( '.header-wrap' );
	var page = wrap && wrap.nextElementSibling;
	if ( ! page || ! page.classList.contains( 'site' ) ) {
		return;
	}
	var link = wrap.querySelector( '.main-nav a' ) || wrap.querySelector( '.menu-toggle' ) || wrap.querySelector( '.main-title a' );
	if ( ! link ) {
		return;
	}

	// Helligkeit einer berechneten Farbe (0 bis 1); null bei durchsichtig oder unbekannter Schreibweise.
	function luminance( color ) {
		var match = /^rgba?\(([^)]+)\)/.exec( color );
		if ( ! match ) {
			return null;
		}
		var parts = match[ 1 ].split( /[\s,\/]+/ ).map( parseFloat );
		if ( parts.length < 3 || parts[ 3 ] === 0 ) {
			return null;
		}
		var rgb = parts.slice( 0, 3 ).map( function ( value ) {
			value /= 255;
			return value <= 0.03928 ? value / 12.92 : Math.pow( ( value + 0.055 ) / 1.055, 2.4 );
		} );
		return 0.2126 * rgb[ 0 ] + 0.7152 * rgb[ 1 ] + 0.0722 * rgb[ 2 ];
	}

	var text = luminance( getComputedStyle( link ).color );
	var back = luminance( getComputedStyle( document.body ).backgroundColor );
	if ( back === null ) {
		back = luminance( getComputedStyle( document.documentElement ).backgroundColor );
	}
	if ( back === null ) {
		back = 1;
	}
	if ( text !== null && ( Math.max( text, back ) + 0.05 ) / ( Math.min( text, back ) + 0.05 ) < 3 ) {
		wrap.classList.add( 'ctt-headband' );
	}
}() );
