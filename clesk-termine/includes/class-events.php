<?php
/**
 * Datenmodell: Beitragstyp „Termin“, Metafelder, Bereinigung und Abfragen.
 *
 * @package Clesk_Termine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Beitragstyp und Datenzugriff.
 */
final class Clesk_Termine_Events {

	const POST_TYPE = 'clesk_termin';

	/**
	 * Sortierschlüssel „Y-m-d H:i“, wird beim Speichern aus Datum und Uhrzeit gebildet.
	 */
	const META_START = '_ctt_start';

	/**
	 * Zuletzt automatisch vergebene Adresse (Slug) der Terminseite.
	 */
	const META_AUTO_SLUG = '_ctt_auto_slug';

	/**
	 * Option: Stand, für den die Permalink-Regeln zuletzt neu geschrieben wurden.
	 */
	const OPTION_REWRITE = 'clesk_termine_rewrite';

	/**
	 * Zwischenspeicher: Seite mit der Terminübersicht.
	 */
	const TRANSIENT_OVERVIEW = 'clesk_termine_overview';

	/**
	 * Feldname => Metaschlüssel.
	 */
	const FIELDS = array(
		'date'       => '_ctt_date',
		'time'       => '_ctt_time',
		'city'       => '_ctt_city',
		'venue'      => '_ctt_venue',
		'street'     => '_ctt_street',
		'zip'        => '_ctt_zip',
		'ticket_url' => '_ctt_ticket_url',
		'price'      => '_ctt_price',
		'status'     => '_ctt_status',
		'note'       => '_ctt_note',
		'info_url'   => '_ctt_info_url',
	);

	/**
	 * Status, bei denen kein Ticket-Link angezeigt wird.
	 */
	const NO_TICKET_STATUSES = array( 'soldout', 'cancelled', 'private' );

	/**
	 * In dieser Anfrage automatisch vergebene Slugs.
	 *
	 * @var string[]
	 */
	private static $auto_slugs = array();

	/**
	 * Hooks registrieren.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'init', array( __CLASS__, 'maybe_flush_rewrite' ), 99 );
		add_filter( 'wp_insert_post_data', array( __CLASS__, 'filter_post_data' ), 10, 2 );
		add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'store_auto_slug' ), 99 );

		foreach ( array( 'save_post', 'deleted_post', 'trashed_post', 'untrashed_post' ) as $hook ) {
			add_action( $hook, array( __CLASS__, 'forget_overview' ) );
		}
	}

	/**
	 * Hat jeder Termin eine eigene Seite? Abschaltbar über den Filter clesk_termine_single_pages.
	 *
	 * @return bool
	 */
	public static function has_single_pages() {
		return (bool) apply_filters( 'clesk_termine_single_pages', true );
	}

	/**
	 * Erster Teil der Adresse der Terminseiten, z. B. „termine“ in /termine/programm-stadt-datum/.
	 *
	 * @return string
	 */
	public static function rewrite_slug() {
		$slug = sanitize_title( (string) apply_filters( 'clesk_termine_slug', 'termine' ) );
		// Prozentkodierte (nicht lateinische) Werte ergäben unbrauchbare Permalink-Regeln.
		return ( '' !== $slug && false === strpos( $slug, '%' ) ) ? $slug : 'termine';
	}

	/**
	 * Permalink-Regeln neu schreiben, wenn sich Version oder Adressaufbau geändert haben.
	 * Läuft auch nach einem Update per ZIP, bei dem kein Aktivierungs-Hook feuert.
	 */
	public static function maybe_flush_rewrite() {
		$state = CLESK_TERMINE_VERSION . '|' . ( self::has_single_pages() ? self::rewrite_slug() : '-' );
		if ( get_option( self::OPTION_REWRITE ) !== $state ) {
			flush_rewrite_rules( false );
			update_option( self::OPTION_REWRITE, $state, true );
		}
	}

	/**
	 * Beim Deaktivieren: Regeln der Terminseiten entfernen.
	 */
	public static function deactivate() {
		unregister_post_type( self::POST_TYPE );
		flush_rewrite_rules( false );
		delete_option( self::OPTION_REWRITE );
		delete_transient( self::TRANSIENT_OVERVIEW );
		wp_clear_scheduled_hook( Clesk_Termine_Single::DAILY_HOOK );
	}

	/**
	 * Beitragstyp und Metafelder registrieren.
	 */
	public static function register() {
		$single = self::has_single_pages();

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'              => array(
					'name'                  => __( 'Termine', 'clesk-termine' ),
					'singular_name'         => __( 'Termin', 'clesk-termine' ),
					'menu_name'             => __( 'Termine', 'clesk-termine' ),
					'name_admin_bar'        => __( 'Termin', 'clesk-termine' ),
					'add_new'               => __( 'Neuer Termin', 'clesk-termine' ),
					'add_new_item'          => __( 'Neuen Termin anlegen', 'clesk-termine' ),
					'edit_item'             => __( 'Termin bearbeiten', 'clesk-termine' ),
					'new_item'              => __( 'Neuer Termin', 'clesk-termine' ),
					'all_items'             => __( 'Alle Termine', 'clesk-termine' ),
					'search_items'          => __( 'Termine durchsuchen', 'clesk-termine' ),
					'not_found'             => __( 'Keine Termine gefunden.', 'clesk-termine' ),
					'not_found_in_trash'    => __( 'Keine Termine im Papierkorb.', 'clesk-termine' ),
					'featured_image'        => __( 'Bild (optional)', 'clesk-termine' ),
					'set_featured_image'    => __( 'Bild festlegen', 'clesk-termine' ),
					'remove_featured_image' => __( 'Bild entfernen', 'clesk-termine' ),
					'use_featured_image'    => __( 'Als Bild verwenden', 'clesk-termine' ),
					'item_published'        => __( 'Termin veröffentlicht.', 'clesk-termine' ),
					'item_updated'          => __( 'Termin aktualisiert.', 'clesk-termine' ),
					'view_item'             => __( 'Termin ansehen', 'clesk-termine' ),
				),
				'description'         => __( 'Veranstaltungen für den Block „Termine“.', 'clesk-termine' ),
				// Mit eigener Seite je Termin öffentlich, sonst reine Datenquelle für den Block.
				'public'              => $single,
				'publicly_queryable'  => $single,
				'exclude_from_search' => true,
				'show_ui'             => true,
				'show_in_menu'        => true,
				'show_in_nav_menus'   => false,
				'show_in_admin_bar'   => true,
				'show_in_rest'        => false,
				'has_archive'         => false,
				'rewrite'             => $single
					? array(
						'slug'       => self::rewrite_slug(),
						'with_front' => false,
						'feeds'      => false,
						'pages'      => false,
					)
					: false,
				'query_var'           => $single,
				'menu_position'       => 21,
				'menu_icon'           => 'dashicons-tickets-alt',
				'supports'            => $single ? array( 'title', 'editor', 'thumbnail' ) : array( 'title', 'thumbnail' ),
				'capability_type'     => 'post',
				'map_meta_cap'        => true,
			)
		);

		$sanitizers = array(
			'date'       => array( __CLASS__, 'sanitize_date' ),
			'time'       => array( __CLASS__, 'sanitize_time' ),
			'city'       => array( __CLASS__, 'sanitize_text' ),
			'venue'      => array( __CLASS__, 'sanitize_text' ),
			'street'     => array( __CLASS__, 'sanitize_text' ),
			'zip'        => array( __CLASS__, 'sanitize_zip' ),
			'ticket_url' => array( __CLASS__, 'sanitize_url' ),
			'price'      => array( __CLASS__, 'sanitize_price' ),
			'status'     => array( __CLASS__, 'sanitize_status' ),
			'note'       => array( __CLASS__, 'sanitize_text' ),
			'info_url'   => array( __CLASS__, 'sanitize_url' ),
		);

		foreach ( self::FIELDS as $field => $meta_key ) {
			register_post_meta(
				self::POST_TYPE,
				$meta_key,
				array(
					'type'              => 'string',
					'single'            => true,
					'default'           => '',
					'show_in_rest'      => false,
					'sanitize_callback' => $sanitizers[ $field ],
					'auth_callback'     => array( __CLASS__, 'can_edit_meta' ),
				)
			);
		}
	}

	/**
	 * Berechtigung für die geschützten Metafelder.
	 *
	 * @param bool   $allowed  Bisheriges Ergebnis.
	 * @param string $meta_key Metaschlüssel.
	 * @param int    $post_id  Beitrags-ID.
	 * @return bool
	 */
	public static function can_edit_meta( $allowed, $meta_key, $post_id ) {
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Auswahl für das Statusfeld.
	 *
	 * @return array<string,string>
	 */
	public static function statuses() {
		return array(
			''          => __( 'Normal', 'clesk-termine' ),
			'few'       => __( 'Wenige Tickets', 'clesk-termine' ),
			'soldout'   => __( 'Ausverkauft', 'clesk-termine' ),
			'extra'     => __( 'Zusatztermin', 'clesk-termine' ),
			'moved'     => __( 'Verlegt', 'clesk-termine' ),
			'cancelled' => __( 'Abgesagt', 'clesk-termine' ),
			'free'      => __( 'Eintritt frei', 'clesk-termine' ),
			'private'   => __( 'Geschlossene Veranstaltung', 'clesk-termine' ),
		);
	}

	/**
	 * Lesbare Bezeichnung eines Status.
	 *
	 * @param string $status Statusschlüssel.
	 * @return string
	 */
	public static function status_label( $status ) {
		$all = self::statuses();
		return ( '' !== $status && isset( $all[ $status ] ) ) ? $all[ $status ] : '';
	}

	/**
	 * Datum als Y-m-d. Akzeptiert auch TT.MM.JJJJ.
	 *
	 * @param mixed $value Eingabe.
	 * @return string
	 */
	public static function sanitize_date( $value ) {
		$value = trim( (string) $value );
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m ) ) {
			list( , $year, $month, $day ) = $m;
		} elseif ( preg_match( '/^(\d{1,2})\.(\d{1,2})\.(\d{2}|\d{4})$/', $value, $m ) ) {
			list( , $day, $month, $year ) = $m;
		} else {
			return '';
		}
		$year = (int) $year;
		// Zweistellig getippte Jahre („26“, vom Datumsfeld als 0026 geliefert) meinen dieses Jahrhundert.
		if ( $year < 100 ) {
			$year += 2000;
		}
		if ( $year < 1970 || $year > 2199 || ! checkdate( (int) $month, (int) $day, $year ) ) {
			return '';
		}
		return sprintf( '%04d-%02d-%02d', $year, $month, $day );
	}

	/**
	 * Uhrzeit als H:i. Akzeptiert auch 20.00 und 20:00:00.
	 *
	 * @param mixed $value Eingabe.
	 * @return string
	 */
	public static function sanitize_time( $value ) {
		$value = trim( (string) $value );
		if ( preg_match( '/^(\d{1,2})[:.](\d{2})(?::\d{2})?$/', $value, $m ) && (int) $m[1] < 24 && (int) $m[2] < 60 ) {
			return sprintf( '%02d:%02d', $m[1], $m[2] );
		}
		return '';
	}

	/**
	 * Einzeiliger Text, höchstens 200 Zeichen.
	 *
	 * @param mixed $value Eingabe.
	 * @return string
	 */
	public static function sanitize_text( $value ) {
		$value = sanitize_text_field( (string) $value );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 200 ) : substr( $value, 0, 200 );
	}

	/**
	 * Postleitzahl: kurzer einzeiliger Text (auch ausländische Formate).
	 *
	 * @param mixed $value Eingabe.
	 * @return string
	 */
	public static function sanitize_zip( $value ) {
		$value = sanitize_text_field( (string) $value );
		return function_exists( 'mb_substr' ) ? mb_substr( $value, 0, 12 ) : substr( $value, 0, 12 );
	}

	/**
	 * Link: http(s) oder relativ (/seite/, #anker). „www.…“ bekommt https://.
	 *
	 * @param mixed $value Eingabe.
	 * @return string
	 */
	public static function sanitize_url( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		// Ein Schema wie „mailto:“ bleibt erkennbar (und wird unten verworfen), „shop.de:8080“ gilt als Host mit Port.
		if ( ! preg_match( '#^([a-z][a-z0-9+.-]*:(?!\d)|//|/|\#|\?)#i', $value ) ) {
			$value = 'https://' . $value;
		}
		return esc_url_raw( $value, array( 'http', 'https' ) );
	}

	/**
	 * Preis ab (Euro) als Dezimalzahl mit Punkt, z. B. „18“ oder „18.5“; „0“ heißt Eintritt frei.
	 *
	 * Tolerant für Eingaben wie „18,50“, „ab 18 €“, „18,-“, „1.200“ oder „frei“. Ein Trenner vor genau
	 * drei Ziffern gilt als Tausendertrenner. Negative und unlesbare Werte ergeben leer, ebenso mehr als
	 * zwei Nachkommastellen. admin.js prüft Eingaben im Formular nach denselben Regeln (parsePrice).
	 *
	 * @param mixed $value Eingabe.
	 * @return string
	 */
	public static function sanitize_price( $value ) {
		$value = trim( (string) $value );
		$value = function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
		if ( '' === $value ) {
			return '';
		}
		if ( in_array( $value, array( 'frei', 'eintritt frei', 'kostenlos', 'gratis', 'free' ), true ) ) {
			return '0';
		}
		$value = (string) preg_replace( '/^ab/u', '', $value );
		$value = str_replace( array( 'euro', 'eur', '€' ), '', $value );
		$value = (string) preg_replace( '/[\s\x{00A0}\x{202F}]+/u', '', $value );
		// „18,-“ und „18.–“ sind volle Euro.
		$value = (string) preg_replace( '/[.,]?[-\x{2013}\x{2014}]+$/u', '', $value );
		$value = rtrim( $value, '.,' );

		if ( preg_match( '/^(\d{1,6})(?:[.,](\d{1,2}))?$/', $value, $m ) ) {
			$int = $m[1];
		} elseif ( preg_match( '/^(\d{1,3}(?:\.\d{3})+)(?:,(\d{1,2}))?$/', $value, $m ) || preg_match( '/^(\d{1,3}(?:,\d{3})+)(?:\.(\d{1,2}))?$/', $value, $m ) ) {
			$int = str_replace( array( '.', ',' ), '', $m[1] );
		} else {
			return '';
		}
		if ( strlen( $int ) > 6 ) {
			return '';
		}
		$int = ltrim( $int, '0' );
		$int = '' !== $int ? $int : '0';
		$dec = isset( $m[2] ) ? rtrim( $m[2], '0' ) : '';
		return '' !== $dec ? $int . '.' . $dec : $int;
	}

	/**
	 * Absolute Adresse zu einem gespeicherten Link, leer bei reinen Ankern.
	 *
	 * @param string $url Gespeicherter Link.
	 * @return string
	 */
	public static function absolute_url( $url ) {
		$url = (string) $url;
		if ( '' === $url || '#' === $url[0] || '?' === $url[0] ) {
			return '';
		}
		if ( 0 === strpos( $url, '//' ) ) {
			return 'https:' . $url;
		}
		if ( '/' === $url[0] ) {
			return home_url( $url );
		}
		return $url;
	}

	/**
	 * Status gegen die erlaubten Werte prüfen.
	 *
	 * @param mixed $value Eingabe.
	 * @return string
	 */
	public static function sanitize_status( $value ) {
		$value = (string) $value;
		return array_key_exists( $value, self::statuses() ) ? $value : '';
	}

	/**
	 * Sortierschlüssel aus Datum und Uhrzeit neu setzen.
	 *
	 * @param int $post_id Beitrags-ID.
	 */
	public static function sync_start( $post_id ) {
		$date = (string) get_post_meta( $post_id, self::FIELDS['date'], true );
		$time = (string) get_post_meta( $post_id, self::FIELDS['time'], true );
		if ( '' === $date ) {
			delete_post_meta( $post_id, self::META_START );
			return;
		}
		update_post_meta( $post_id, self::META_START, $date . ' ' . ( '' !== $time ? $time : '00:00' ) );
	}

	/**
	 * Rohwerte aller Felder eines Termins.
	 *
	 * @param int $post_id Beitrags-ID.
	 * @return array<string,string>
	 */
	public static function get_fields( $post_id ) {
		$out = array();
		foreach ( self::FIELDS as $field => $meta_key ) {
			$out[ $field ] = (string) get_post_meta( $post_id, $meta_key, true );
		}
		return $out;
	}

	/**
	 * Beginn als DateTimeImmutable in der Zeitzone der Website.
	 *
	 * @param string $date Y-m-d.
	 * @param string $time H:i oder leer.
	 * @return DateTimeImmutable|null
	 */
	public static function start( $date, $time ) {
		if ( '' === $date ) {
			return null;
		}
		$dt = DateTimeImmutable::createFromFormat( '!Y-m-d H:i', $date . ' ' . ( '' !== $time ? $time : '00:00' ), wp_timezone() );
		return $dt ? $dt : null;
	}

	/**
	 * Heutiges Datum in der Zeitzone der Website.
	 *
	 * @return string
	 */
	public static function today() {
		return wp_date( 'Y-m-d' );
	}

	/**
	 * Stabiler Filterschlüssel für Stadt und Location.
	 *
	 * @param string $value Anzeigewert.
	 * @return string
	 */
	public static function key( $value ) {
		$key = sanitize_title( remove_accents( $value ) );
		return '' !== $key ? $key : 'k' . substr( md5( strtolower( $value ) ), 0, 10 );
	}

	/**
	 * Termin für die Ausgabe aufbereiten.
	 *
	 * @param WP_Post $post          Termin.
	 * @param bool    $allow_undated Auch Termine ohne gültiges Datum liefern (für die Terminseite).
	 * @return array|null Null, wenn kein gültiges Datum gesetzt ist und $allow_undated false ist.
	 */
	public static function prepare( $post, $allow_undated = false ) {
		$fields = self::get_fields( $post->ID );
		$start  = self::start( $fields['date'], $fields['time'] );
		if ( ! $start && ! $allow_undated ) {
			return null;
		}
		$ts = $start ? $start->getTimestamp() : 0;

		return array_merge(
			$fields,
			array(
				'id'          => (int) $post->ID,
				'title'       => (string) $post->post_title,
				'has_date'    => (bool) $start,
				'start'       => $start,
				'timestamp'   => $ts,
				'is_past'     => $start && $fields['date'] < self::today(),
				'month_key'   => $start ? substr( $fields['date'], 0, 7 ) : '',
				'month_label' => $start ? wp_date( 'F Y', $ts ) : '',
				'city_key'    => '' !== $fields['city'] ? self::key( $fields['city'] ) : '',
				'venue_key'   => '' !== $fields['venue'] ? self::key( $fields['venue'] . ' ' . $fields['city'] ) : '',
				'image_id'    => (int) get_post_thumbnail_id( $post ),
				'url'         => 'publish' === $post->post_status ? self::permalink( $post ) : '',
			)
		);
	}

	/**
	 * Adresse der eigenen Seite eines Termins, leer ohne Terminseiten.
	 *
	 * @param WP_Post|int $post Termin.
	 * @return string
	 */
	public static function permalink( $post ) {
		if ( ! self::has_single_pages() ) {
			return '';
		}
		$url = get_permalink( $post );
		return $url ? (string) $url : '';
	}

	/**
	 * Location mit Anschrift in einer Zeile, z. B. „Stadthalle, Musterstraße 1, 12345 Köln“.
	 *
	 * @param array $fields Felder eines Termins.
	 * @return string
	 */
	public static function place_line( array $fields ) {
		$town  = trim( $fields['zip'] . ' ' . $fields['city'] );
		$parts = array_filter( array( $fields['venue'], $fields['street'], $town ), 'strlen' );
		return implode( ', ', $parts );
	}

	/**
	 * Veröffentlichte Termine abfragen.
	 *
	 * @param array $args range (upcoming|past|all), program (exakter Titel), limit (0 = alle), exclude (ID).
	 * @return array[] Aufbereitete Termine.
	 */
	public static function query( array $args = array() ) {
		// „Alle“ mit Begrenzung: zuerst die nächsten Termine, dann mit den jüngsten vergangenen auffüllen.
		if ( isset( $args['range'], $args['limit'] ) && 'all' === $args['range'] && (int) $args['limit'] > 0 ) {
			$limit  = (int) $args['limit'];
			$events = self::query( array_merge( $args, array( 'range' => 'upcoming' ) ) );
			if ( count( $events ) < $limit ) {
				$past = self::query(
					array_merge(
						$args,
						array(
							'range' => 'past',
							'limit' => $limit - count( $events ),
						)
					)
				);
				$events = array_merge( array_reverse( $past ), $events );
			}
			return $events;
		}

		$args  = wp_parse_args(
			$args,
			array(
				'range'   => 'upcoming',
				'program' => '',
				'limit'   => 0,
				'exclude' => 0,
			)
		);
		$today = self::today();

		$meta_query = array();
		if ( 'upcoming' === $args['range'] ) {
			$meta_query[] = array(
				'key'     => self::FIELDS['date'],
				'value'   => $today,
				'compare' => '>=',
				'type'    => 'DATE',
			);
		} elseif ( 'past' === $args['range'] ) {
			$meta_query[] = array(
				'key'     => self::FIELDS['date'],
				'value'   => $today,
				'compare' => '<',
				'type'    => 'DATE',
			);
		}

		$limit   = max( 0, (int) $args['limit'] );
		$program = self::program_key( (string) $args['program'] );

		$query_args = array(
			'post_type'           => self::POST_TYPE,
			'post_status'         => 'publish',
			// Passwortgeschützte Termine bleiben verborgen, wie es die Sichtbarkeit im Backend verspricht.
			'has_password'        => false,
			// Mit Programmfilter erst alle holen: verglichen und begrenzt wird danach in PHP.
			'posts_per_page'      => ( $limit > 0 && '' === $program ) ? $limit : 500,
			'no_found_rows'       => true,
			'ignore_sticky_posts' => true,
			'meta_key'            => self::META_START, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			// Neueste zuerst holen, wenn die Obergrenze sonst die kommenden Termine abschneiden würde.
			'orderby'             => array(
				'meta_value' => 'upcoming' === $args['range'] ? 'ASC' : 'DESC',
				'ID'         => 'upcoming' === $args['range'] ? 'ASC' : 'DESC',
			),
		);
		if ( $meta_query ) {
			$query_args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		}
		if ( $args['exclude'] > 0 ) {
			$query_args['post__not_in'] = array( (int) $args['exclude'] );
		}

		$query = new WP_Query( $query_args );
		if ( $query->posts ) {
			update_post_thumbnail_cache( $query );
		}

		$events = array();
		foreach ( $query->posts as $post ) {
			if ( '' !== $program && self::program_key( $post->post_title ) !== $program ) {
				continue;
			}
			$event = self::prepare( $post );
			if ( $event ) {
				$events[] = $event;
			}
		}
		if ( $limit > 0 && '' !== $program ) {
			$events = array_slice( $events, 0, $limit );
		}
		// „Alle“ wird absteigend geholt und chronologisch angezeigt.
		return 'all' === $args['range'] ? array_reverse( $events ) : $events;
	}

	/**
	 * Vergleichsschlüssel für Programmnamen: ohne HTML-Entities, mit einfachem Leerraum, klein geschrieben.
	 * So passt „Rock &amp; Roll“ aus einem Editor auch zum gespeicherten Titel „Rock & Roll“.
	 *
	 * @param string $title Programm.
	 * @return string
	 */
	public static function program_key( $title ) {
		$title = html_entity_decode( (string) $title, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$title = trim( (string) preg_replace( '/\s+/u', ' ', $title ) );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $title, 'UTF-8' ) : strtolower( $title );
	}

	/**
	 * Bisher verwendete Werte eines Feldes (für Vorschläge im Formular).
	 *
	 * @param string $field Feldname aus FIELDS.
	 * @return string[]
	 */
	public static function distinct_values( $field ) {
		global $wpdb;
		if ( ! isset( self::FIELDS[ $field ] ) ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$values = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT pm.meta_value FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
				WHERE pm.meta_key = %s AND p.post_type = %s
				AND " . self::visible_sql() . "
				AND pm.meta_value <> ''
				ORDER BY pm.meta_value ASC LIMIT 500",
				self::FIELDS[ $field ],
				self::POST_TYPE
			)
		);
		return array_map( 'strval', (array) $values );
	}

	/**
	 * Bisher verwendete Programmnamen (Titel).
	 *
	 * @param bool $published_only Nur veröffentlichte, nicht passwortgeschützte Termine (für die Block-Einstellungen).
	 * @return string[]
	 */
	public static function distinct_titles( $published_only = false ) {
		global $wpdb;
		$where = $published_only ? "p.post_status = 'publish' AND p.post_password = ''" : self::visible_sql();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$values = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT p.post_title FROM {$wpdb->posts} p
				WHERE p.post_type = %s AND " . $where . " AND p.post_title <> ''
				ORDER BY p.post_title ASC LIMIT 300",
				self::POST_TYPE
			)
		);
		return array_map( 'strval', (array) $values );
	}

	/**
	 * SQL-Bedingung (Alias p): Termine, deren Angaben der angemeldete Nutzer als Vorschlag sehen darf.
	 * Veröffentlichte immer, fremde Entwürfe und private Termine nur mit den passenden Rechten, eigene immer.
	 *
	 * @return string
	 */
	private static function visible_sql() {
		global $wpdb;
		$type     = get_post_type_object( self::POST_TYPE );
		$statuses = array( 'publish' );
		if ( $type && current_user_can( $type->cap->edit_others_posts ) ) {
			$statuses = array_merge( $statuses, array( 'draft', 'pending', 'future' ) );
		}
		if ( $type && current_user_can( $type->cap->read_private_posts ) ) {
			$statuses[] = 'private';
		}
		$in = "'" . implode( "','", $statuses ) . "'";

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return "p.post_status IN ($in)";
		}

		return $wpdb->prepare(
			"( p.post_status IN ($in) OR ( p.post_author = %d AND p.post_status IN ('draft','pending','future','private') ) )", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- feste Werte.
			$user_id
		);
	}

	/**
	 * Location => bekannte Orte mit Anschrift (für die automatische Ergänzung im Formular).
	 * Je Location und Stadt zählt die jüngste Angabe, bei der Straße oder PLZ gefüllt sind.
	 *
	 * @return array<string,array<int,array{city:string,street:string,zip:string}>>
	 */
	public static function venue_places() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT v.meta_value AS venue, c.meta_value AS city, s.meta_value AS street, z.meta_value AS zip
				FROM {$wpdb->postmeta} v
				INNER JOIN {$wpdb->posts} p ON p.ID = v.post_id
				INNER JOIN {$wpdb->postmeta} c ON c.post_id = v.post_id AND c.meta_key = %s
				LEFT JOIN {$wpdb->postmeta} s ON s.post_id = v.post_id AND s.meta_key = %s
				LEFT JOIN {$wpdb->postmeta} z ON z.post_id = v.post_id AND z.meta_key = %s
				WHERE v.meta_key = %s AND p.post_type = %s
				AND " . self::visible_sql() . "
				AND v.meta_value <> '' AND c.meta_value <> ''
				ORDER BY p.ID DESC LIMIT 2000",
				self::FIELDS['city'],
				self::FIELDS['street'],
				self::FIELDS['zip'],
				self::FIELDS['venue'],
				self::POST_TYPE
			)
		);

		$map = array();
		foreach ( (array) $rows as $row ) {
			$venue  = (string) $row->venue;
			$city   = (string) $row->city;
			$street = (string) $row->street;
			$zip    = (string) $row->zip;
			if ( ! isset( $map[ $venue ][ $city ] ) ) {
				$map[ $venue ][ $city ] = array(
					'city'   => $city,
					'street' => $street,
					'zip'    => $zip,
				);
			} elseif ( '' === $map[ $venue ][ $city ]['street'] && '' === $map[ $venue ][ $city ]['zip'] && ( '' !== $street || '' !== $zip ) ) {
				$map[ $venue ][ $city ]['street'] = $street;
				$map[ $venue ][ $city ]['zip']    = $zip;
			}
		}

		$out = array();
		foreach ( $map as $venue => $places ) {
			$out[ $venue ] = array_values( $places );
		}
		return $out;
	}

	/**
	 * Adresse (Slug) einer Terminseite aus Programm, Stadt und Datum, z. B. „poetry-slam-koeln-19-11-2026“.
	 *
	 * @param string $title Programm.
	 * @param string $city  Stadt.
	 * @param string $date  Y-m-d oder leer.
	 * @return string
	 */
	public static function build_slug( $title, $city, $date ) {
		$parts = array( trim( (string) $title ), trim( (string) $city ) );
		if ( preg_match( '/^(\d{4})-(\d{2})-(\d{2})$/', (string) $date, $m ) ) {
			$parts[] = $m[3] . '-' . $m[2] . '-' . $m[1];
		}
		return sanitize_title( implode( ' ', array_filter( $parts, 'strlen' ) ) );
	}

	/**
	 * Slug beim Veröffentlichen automatisch setzen, solange ihn niemand von Hand geändert hat.
	 * Ändern sich Programm, Stadt oder Datum eines veröffentlichten Termins, leitet WordPress
	 * die alte Adresse über _wp_old_slug auf die neue um.
	 *
	 * Entwürfe bekommen noch keine Adresse: WordPress prüft sie dort nicht auf Eindeutigkeit. Eine Kopie
	 * trüge sonst die Adresse des Originals, dessen Seite für Besucher dann nicht mehr erreichbar wäre.
	 *
	 * @param array $data    Bereinigte Beitragsdaten (mit Slashes).
	 * @param array $postarr Übergebene Beitragsdaten.
	 * @return array
	 */
	public static function filter_post_data( $data, $postarr ) {
		if ( ! isset( $data['post_type'] ) || self::POST_TYPE !== $data['post_type'] || ! self::has_single_pages() ) {
			return $data;
		}
		$status = isset( $data['post_status'] ) ? (string) $data['post_status'] : '';
		if ( in_array( $status, array( 'auto-draft', 'draft', 'pending', 'trash', 'inherit' ), true ) ) {
			return $data;
		}

		$post_id = isset( $postarr['ID'] ) ? (int) $postarr['ID'] : 0;
		$title   = wp_unslash( (string) $data['post_title'] );
		$current = isset( $data['post_name'] ) ? (string) $data['post_name'] : '';
		$default = sanitize_title( $title );
		$last    = $post_id ? (string) get_post_meta( $post_id, self::META_AUTO_SLUG, true ) : '';

		// Automatisch verwaltet: leer, zuletzt von hier gesetzt oder der WordPress-Standard aus dem Titel,
		// jeweils auch mit dem Zähler („-2“), den WordPress vor diesem Filter an eine schon vergebene Adresse hängt.
		$is_auto = '' === $current;
		foreach ( array( $last, $default ) as $base ) {
			if ( '' !== $base && ( $current === $base || preg_match( '/^' . preg_quote( $base, '/' ) . '-\d+$/', $current ) ) ) {
				$is_auto = true;
			}
		}
		if ( ! $is_auto ) {
			return $data;
		}

		$incoming = self::incoming_slug_fields( $post_id );
		$slug     = self::build_slug( $title, $incoming['city'], $incoming['date'] );
		if ( '' === $slug ) {
			return $data;
		}

		$slug               = wp_unique_post_slug( $slug, $post_id, $status, self::POST_TYPE, 0 );
		$data['post_name']  = $slug;
		self::$auto_slugs[] = $slug;
		return $data;
	}

	/**
	 * Stadt und Datum für den Slug: aus dem gerade abgeschickten Formular, sonst aus den gespeicherten Feldern.
	 *
	 * @param int $post_id Beitrags-ID.
	 * @return array{city:string,date:string}
	 */
	private static function incoming_slug_fields( $post_id ) {
		$fields = array(
			'city' => '',
			'date' => '',
		);
		if ( ! $post_id ) {
			return $fields;
		}
		$fields['city'] = (string) get_post_meta( $post_id, self::FIELDS['city'], true );
		$fields['date'] = (string) get_post_meta( $post_id, self::FIELDS['date'], true );

		if ( isset( $_POST['ctt_nonce'], $_POST['ctt'] ) && is_array( $_POST['ctt'] )
			&& wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ctt_nonce'] ) ), 'ctt_save_' . $post_id ) ) {
			$input = wp_unslash( $_POST['ctt'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- einzeln bereinigt.
			if ( isset( $input['city'] ) && is_scalar( $input['city'] ) ) {
				$fields['city'] = self::sanitize_text( $input['city'] );
			}
			if ( isset( $input['date'] ) && is_scalar( $input['date'] ) ) {
				$fields['date'] = self::sanitize_date( $input['date'] );
			}
		}
		return $fields;
	}

	/**
	 * Merken, welchen Slug das Plugin vergeben hat (Grundlage für „von Hand geändert?“).
	 *
	 * @param int $post_id Beitrags-ID.
	 */
	public static function store_auto_slug( $post_id ) {
		if ( ! self::$auto_slugs || wp_is_post_revision( $post_id ) ) {
			return;
		}
		$post = get_post( $post_id );
		if ( $post && in_array( $post->post_name, self::$auto_slugs, true ) ) {
			update_post_meta( $post_id, self::META_AUTO_SLUG, wp_slash( $post->post_name ) );
		}
	}

	/**
	 * Adresse der Seite mit der Terminübersicht (für „Alle Termine“ auf den Terminseiten), mit Sprungziel
	 * auf die Liste. Standard: die veröffentlichte Seite mit dem Block „Termine“, bevorzugt die vollständige Liste.
	 *
	 * @return string Leer, wenn keine Übersicht gefunden wurde.
	 */
	public static function overview_url() {
		$filtered = apply_filters( 'clesk_termine_overview_url', null );
		if ( null !== $filtered ) {
			return (string) $filtered;
		}

		$found = get_transient( self::TRANSIENT_OVERVIEW );
		if ( ! is_array( $found ) ) {
			$found = self::find_overview_page();
			set_transient( self::TRANSIENT_OVERVIEW, $found, DAY_IN_SECONDS );
		}
		$page_id = isset( $found['id'] ) ? (int) $found['id'] : 0;
		if ( $page_id < 1 || 'publish' !== get_post_status( $page_id ) ) {
			return '';
		}
		$url = get_permalink( $page_id );
		if ( ! $url ) {
			return '';
		}
		$target = isset( $found['target'] ) ? (string) $found['target'] : '';
		return (string) $url . ( '' !== $target ? '#' . rawurlencode( $target ) : '' );
	}

	/**
	 * Zwischengespeicherte Übersichtsseite verwerfen, sobald Inhalte gespeichert oder gelöscht werden.
	 */
	public static function forget_overview() {
		delete_transient( self::TRANSIENT_OVERVIEW );
	}

	/**
	 * Veröffentlichte Seite (oder Beitrag) mit dem Block oder Shortcode suchen.
	 *
	 * @return array{id:int,target:string} Beitrags-ID (0 = keine) und Sprungziel auf der Seite.
	 */
	private static function find_overview_page() {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery
		$rows = $wpdb->get_results(
			"SELECT ID, post_content FROM {$wpdb->posts}
			WHERE post_status = 'publish' AND post_type IN ('page','post')
			AND ( post_content LIKE '%<!-- wp:clesk/termine%' OR post_content LIKE '%[termine%' OR post_content LIKE '%[clesk\\_termine%' )
			ORDER BY post_type = 'page' DESC, menu_order ASC, ID ASC LIMIT 20"
		);

		$best = array(
			'id'     => 0,
			'target' => '',
			'score'  => 0,
		);
		foreach ( (array) $rows as $row ) {
			$lists = array();
			self::collect_lists( parse_blocks( (string) $row->post_content ), $lists );

			$candidate = array(
				'id'     => (int) $row->ID,
				'target' => '',
				'score'  => 1,
			);
			foreach ( $lists as $index => $atts ) {
				$full  = empty( $atts['program'] ) && empty( $atts['limit'] ) && ( ! isset( $atts['range'] ) || 'past' !== $atts['range'] );
				$score = $full ? 3 : 2;
				if ( $score > $candidate['score'] ) {
					$candidate['score'] = $score;
					// Eigener HTML-Anker des Blocks, sonst die laufende Nummer der Liste auf der Seite.
					$candidate['target'] = ! empty( $atts['anchor'] ) && is_string( $atts['anchor'] ) ? $atts['anchor'] : 'ctt-' . ( $index + 1 );
				}
			}
			if ( $candidate['score'] > $best['score'] ) {
				$best = $candidate;
			}
		}
		return array(
			'id'     => $best['id'],
			'target' => $best['target'],
		);
	}

	/**
	 * Attribute aller Blöcke „Termine“ eines Inhalts in Reihenfolge sammeln.
	 *
	 * @param array $blocks Geparste Blöcke.
	 * @param array $lists  Sammelliste (wird ergänzt).
	 */
	private static function collect_lists( array $blocks, array &$lists ) {
		foreach ( $blocks as $block ) {
			if ( isset( $block['blockName'] ) && 'clesk/termine' === $block['blockName'] ) {
				$lists[] = isset( $block['attrs'] ) && is_array( $block['attrs'] ) ? $block['attrs'] : array();
			}
			if ( ! empty( $block['innerBlocks'] ) ) {
				self::collect_lists( $block['innerBlocks'], $lists );
			}
		}
	}
}
