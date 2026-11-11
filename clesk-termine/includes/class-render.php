<?php
/**
 * HTML-Ausgabe der Terminliste für Block und Shortcode.
 *
 * @package Clesk_Termine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Rendert die Terminliste in drei Designs.
 */
final class Clesk_Termine_Render {

	const DESIGNS = array( 'karten', 'liste', 'ticket' );
	const RANGES  = array( 'upcoming', 'past', 'all' );

	/**
	 * Laufende Nummer für eindeutige IDs bei mehreren Listen auf einer Seite.
	 *
	 * @var int
	 */
	private static $instance = 0;

	/**
	 * Strukturierte Daten je Termin-ID: Array = vorgemerkt, true = bereits ausgegeben.
	 *
	 * @var array<int,array|bool>
	 */
	private static $schema_items = array();

	/**
	 * Standardwerte der Attribute (entsprechen block.json).
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'design'       => 'karten',
			'filterCity'   => true,
			'filterVenue'  => true,
			'filterMonth'  => true,
			'groupByMonth' => true,
			'showImages'   => true,
			'showMap'      => true,
			'linkDetails'  => true,
			'showShare'    => true,
			'range'        => 'upcoming',
			'program'      => '',
			'limit'        => 0,
			'exclude'      => 0,
			'ticketLabel'  => __( 'Tickets', 'clesk-termine' ),
			'emptyText'    => __( 'Aktuell sind keine Termine geplant.', 'clesk-termine' ),
		);
	}

	/**
	 * Attribute bereinigen und mit Standardwerten auffüllen.
	 *
	 * @param array $atts Attribute.
	 * @return array
	 */
	public static function normalize( array $atts ) {
		$defaults = self::defaults();
		$a        = array_merge( $defaults, array_intersect_key( $atts, $defaults ) );

		$a['design'] = in_array( $a['design'], self::DESIGNS, true ) ? $a['design'] : 'karten';
		$a['range']  = in_array( $a['range'], self::RANGES, true ) ? $a['range'] : 'upcoming';
		foreach ( array( 'filterCity', 'filterVenue', 'filterMonth', 'groupByMonth', 'showImages', 'showMap', 'linkDetails', 'showShare' ) as $flag ) {
			$a[ $flag ] = (bool) $a[ $flag ];
		}
		$a['limit']       = max( 0, min( 500, (int) $a['limit'] ) );
		$a['exclude']     = max( 0, (int) $a['exclude'] );
		// Nur zum Vergleichen: nicht bereinigen, sonst passt der Wert nicht mehr zum gespeicherten Titel.
		$a['program']     = trim( (string) $a['program'] );
		$a['ticketLabel'] = sanitize_text_field( (string) $a['ticketLabel'] );
		$a['emptyText']   = sanitize_text_field( (string) $a['emptyText'] );
		if ( '' === $a['ticketLabel'] ) {
			$a['ticketLabel'] = $defaults['ticketLabel'];
		}
		return $a;
	}

	/**
	 * Terminliste rendern.
	 *
	 * @param array $atts     Attribute.
	 * @param bool  $is_block True im Block-Kontext (Wrapper-Attribute von WordPress).
	 * @return string
	 */
	public static function render( array $atts, $is_block = false ) {
		$a      = self::normalize( $atts );
		$events = Clesk_Termine_Events::query(
			array(
				'range'   => $a['range'],
				'program' => $a['program'],
				'limit'   => $a['limit'],
				'exclude' => $a['exclude'],
			)
		);
		$editor = self::is_editor_preview();
		$uid    = 'ctt-' . ( ++self::$instance );

		$has_media = false;
		if ( 'karten' === $a['design'] && $a['showImages'] ) {
			foreach ( $events as $event ) {
				if ( $event['image_id'] ) {
					$has_media = true;
					break;
				}
			}
		}

		// Außen die Hülle (Block-Klassen, Ausrichtung, Anker), innen die Liste als Query-Container.
		$wrapper_html = ( $is_block && function_exists( 'get_block_wrapper_attributes' ) )
			? get_block_wrapper_attributes( array( 'class' => 'ctt-wrap' ) )
			: 'class="ctt-wrap"';

		$inner = array(
			'id'            => $uid,
			'class'         => 'ctt ctt--' . $a['design'] . ( $a['groupByMonth'] ? '' : ' ctt--full-dates' ),
			'data-ctt'      => '1',
			'data-ctt-one'  => __( '1 Termin', 'clesk-termine' ),
			/* translators: %d: Anzahl der Termine. */
			'data-ctt-many' => __( '%d Termine', 'clesk-termine' ),
		);
		$inner_html = '';
		foreach ( $inner as $name => $value ) {
			$inner_html .= sprintf( ' %s="%s"', $name, esc_attr( $value ) );
		}

		$filters = self::filter_options( $events, $a );

		$html = '<div' . $inner_html . '>';
		if ( $filters ) {
			$html .= self::filters_html( $filters, $uid, $editor );
		}

		if ( ! $events ) {
			if ( '' !== $a['emptyText'] ) {
				$html .= '<p class="ctt-empty">' . esc_html( $a['emptyText'] ) . '</p>';
			} elseif ( $editor ) {
				$html .= '<p class="ctt-editor-hint">' . esc_html__( 'Keine Termine für diese Auswahl. Auf der Website bleibt der Block leer.', 'clesk-termine' ) . '</p>';
			}
			if ( $editor ) {
				$html .= '<p class="ctt-editor-hint">' . esc_html__( 'Termine werden im Menü „Termine“ angelegt. Hier erscheinen nur veröffentlichte Termine.', 'clesk-termine' ) . '</p>';
			}
		} else {
			$html .= '<div class="ctt-groups' . ( $has_media ? ' ctt-groups--media' : '' ) . '">';
			foreach ( self::group( $events, $a['groupByMonth'] ) as $key => $group ) {
				$html .= '<div class="ctt-group">';
				if ( '' !== $group['label'] ) {
					$html .= '<h3 class="ctt-group__title">' . esc_html( $group['label'] ) . '</h3>';
				}
				$html .= '<ul class="ctt-list" role="list">';
				foreach ( $group['events'] as $event ) {
					$html .= self::item_html( $event, $a, $has_media );
				}
				$html .= '</ul></div>';
			}
			$html .= '</div>';

			if ( $filters ) {
				$html .= '<div class="ctt-empty ctt-empty--filter" hidden><p>' . esc_html__( 'Keine Termine für diese Auswahl.', 'clesk-termine' ) . '</p>'
					. '<button type="button" class="ctt-reset ctt-reset--inline">' . esc_html__( 'Filter zurücksetzen', 'clesk-termine' ) . '</button></div>';
			}
		}
		$html .= '</div>';

		// Block-HTML läuft im Inhalt noch durch do_shortcode: eckige Klammern aus Terminfeldern entschärfen.
		if ( $is_block ) {
			$html = self::neutralize_brackets( $html );
		}
		if ( $events && ! $editor ) {
			$html .= self::schema_html( $events );
		}

		return '<div ' . $wrapper_html . '>' . $html . '</div>';
	}

	/**
	 * Eckige Klammern als Entities ausgeben, damit WordPress darin keine Shortcodes ausführt.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function neutralize_brackets( $html ) {
		return str_replace( array( '[', ']' ), array( '&#91;', '&#93;' ), $html );
	}

	/**
	 * Vorschau im Block-Editor (ServerSideRender über die REST-API)?
	 *
	 * @return bool
	 */
	private static function is_editor_preview() {
		if ( ! defined( 'REST_REQUEST' ) || ! REST_REQUEST ) {
			return false;
		}
		$uri = isset( $_SERVER['REQUEST_URI'] ) ? (string) wp_unslash( $_SERVER['REQUEST_URI'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		return false !== strpos( $uri, 'block-renderer' );
	}

	/**
	 * Filterwerte sammeln. Ein Filter erscheint nur bei mindestens zwei Werten.
	 *
	 * @param array[] $events Termine.
	 * @param array   $a      Attribute.
	 * @return array<string,array<string,string>>
	 */
	private static function filter_options( array $events, array $a ) {
		$sets = array(
			'city'  => array(),
			'venue' => array(),
			'month' => array(),
		);
		foreach ( $events as $event ) {
			if ( '' !== $event['city'] ) {
				$sets['city'][ $event['city_key'] ] = $event['city'];
			}
			if ( '' !== $event['venue'] ) {
				$sets['venue'][ $event['venue_key'] ] = '' !== $event['city'] ? $event['venue'] . ', ' . $event['city'] : $event['venue'];
			}
			$sets['month'][ $event['month_key'] ] = $event['month_label'];
		}
		uasort( $sets['city'], array( __CLASS__, 'compare_labels' ) );
		uasort( $sets['venue'], array( __CLASS__, 'compare_labels' ) );

		$enabled = array(
			'city'  => $a['filterCity'],
			'venue' => $a['filterVenue'],
			'month' => $a['filterMonth'],
		);
		$out     = array();
		foreach ( $sets as $key => $options ) {
			if ( $enabled[ $key ] && count( $options ) > 1 ) {
				$out[ $key ] = $options;
			}
		}
		return $out;
	}

	/**
	 * Alphabetisch ohne Rücksicht auf Umlaute und Groß-/Kleinschreibung.
	 *
	 * @param string $a Erster Wert.
	 * @param string $b Zweiter Wert.
	 * @return int
	 */
	public static function compare_labels( $a, $b ) {
		return strcasecmp( remove_accents( $a ), remove_accents( $b ) );
	}

	/**
	 * Filterleiste. Ohne JavaScript bleibt sie verborgen und alle Termine sichtbar.
	 *
	 * @param array  $filters Filterwerte.
	 * @param string $uid     Eindeutiges Präfix.
	 * @param bool   $editor  Editor-Vorschau (Leiste sichtbar, ohne Funktion).
	 * @return string
	 */
	private static function filters_html( array $filters, $uid, $editor ) {
		$meta = array(
			'city'  => array( __( 'Stadt', 'clesk-termine' ), __( 'Alle Städte', 'clesk-termine' ), 'pin' ),
			'venue' => array( __( 'Location', 'clesk-termine' ), __( 'Alle Locations', 'clesk-termine' ), 'venue' ),
			'month' => array( __( 'Monat', 'clesk-termine' ), __( 'Alle Monate', 'clesk-termine' ), 'calendar' ),
		);

		$html = '<div class="ctt-filters" role="group" aria-label="' . esc_attr__( 'Termine filtern', 'clesk-termine' ) . '"' . ( $editor ? '' : ' hidden' ) . '>';
		foreach ( $filters as $key => $options ) {
			$id    = $uid . '-' . $key;
			$html .= '<div class="ctt-filter ctt-filter--' . esc_attr( $key ) . '">';
			$html .= '<label class="ctt-sr" for="' . esc_attr( $id ) . '">' . esc_html( $meta[ $key ][0] ) . '</label>';
			$html .= self::icon( $meta[ $key ][2], 'ctt-filter__icon' );
			$html .= '<select id="' . esc_attr( $id ) . '" data-ctt-filter="' . esc_attr( $key ) . '">';
			$html .= '<option value="">' . esc_html( $meta[ $key ][1] ) . '</option>';
			foreach ( $options as $value => $label ) {
				$html .= '<option value="' . esc_attr( $value ) . '">' . esc_html( $label ) . '</option>';
			}
			$html .= '</select></div>';
		}
		$html .= '<button type="button" class="ctt-reset" hidden>' . self::icon( 'close' ) . '<span>' . esc_html__( 'Zurücksetzen', 'clesk-termine' ) . '</span></button>';
		$html .= '<p class="ctt-count" aria-live="polite"></p>';
		$html .= '</div>';
		return $html;
	}

	/**
	 * Termine nach Monat gruppieren.
	 *
	 * @param array[] $events   Termine (bereits sortiert).
	 * @param bool    $by_month Gruppieren?
	 * @return array<string,array{label:string,events:array}>
	 */
	private static function group( array $events, $by_month ) {
		if ( ! $by_month ) {
			return array(
				'' => array(
					'label'  => '',
					'events' => $events,
				),
			);
		}
		$groups = array();
		foreach ( $events as $event ) {
			$key = $event['month_key'];
			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'label'  => $event['month_label'],
					'events' => array(),
				);
			}
			$groups[ $key ]['events'][] = $event;
		}
		return $groups;
	}

	/**
	 * Ein Termin als Listeneintrag.
	 *
	 * @param array $e         Termin.
	 * @param array $a         Attribute.
	 * @param bool  $has_media Bildspalte anzeigen.
	 * @return string
	 */
	private static function item_html( array $e, array $a, $has_media ) {
		$classes = array( 'ctt-event' );
		if ( '' !== $e['status'] ) {
			$classes[] = 'ctt-event--' . $e['status'];
		}
		if ( $e['is_past'] ) {
			$classes[] = 'ctt-event--past';
		}

		$html  = '<li class="ctt-item" data-city="' . esc_attr( $e['city_key'] ) . '" data-venue="' . esc_attr( $e['venue_key'] ) . '" data-month="' . esc_attr( $e['month_key'] ) . '">';
		$html .= '<div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		$html .= '<div class="ctt-event__date" aria-hidden="true">' . self::date_html( $e, $a ) . '</div>';
		if ( $has_media ) {
			$html .= self::media_html( $e, $a );
		}
		$html .= '<div class="ctt-event__main">' . self::main_html( $e, $a['design'], $a['linkDetails'] ) . '</div>';

		$actions = self::actions_html( $e, $a );
		if ( '' !== $actions ) {
			$html .= '<div class="ctt-event__actions">' . $actions . '</div>';
		}
		$html .= '</div></li>';
		return $html;
	}

	/**
	 * Sichtbarer Datumsblock je Design (für Screenreader verborgen).
	 *
	 * @param array $e Termin.
	 * @param array $a Attribute.
	 * @return string
	 */
	private static function date_html( array $e, array $a ) {
		$ts      = $e['timestamp'];
		$weekday = rtrim( wp_date( 'D', $ts ), '.' );
		$time    = $e['time'];
		// Ohne Monatsüberschriften fehlt sonst das Jahr.
		$year = $a['groupByMonth'] ? '' : '<span class="ctt-date__year">' . esc_html( wp_date( 'Y', $ts ) ) . '</span>';

		switch ( $a['design'] ) {
			case 'liste':
				$main = $weekday . ', ' . wp_date( $a['groupByMonth'] ? 'd.m.' : 'd.m.Y', $ts );
				return '<span class="ctt-date__main">' . esc_html( $main ) . '</span>'
					/* translators: %s: Uhrzeit. */
					. ( '' !== $time ? '<span class="ctt-date__meta">' . esc_html( sprintf( __( '%s Uhr', 'clesk-termine' ), $time ) ) . '</span>' : '' );

			case 'ticket':
				return '<span class="ctt-date__weekday">' . esc_html( $weekday ) . '</span>'
					. '<span class="ctt-date__day">' . esc_html( wp_date( 'j', $ts ) ) . '</span>'
					. '<span class="ctt-date__monthname">' . esc_html( rtrim( wp_date( 'M', $ts ), '.' ) ) . '</span>'
					. $year
					. ( '' !== $time ? '<span class="ctt-date__meta">' . esc_html( $time ) . '</span>' : '' );

			default:
				// Kalenderblatt: Monat (ohne Monatsüberschriften mit Jahr), Tag, Wochentag. Die Uhrzeit steht beim Ort.
				$month = rtrim( wp_date( 'M', $ts ), '.' ) . ( $a['groupByMonth'] ? '' : ' ' . wp_date( 'Y', $ts ) );
				return '<span class="ctt-date__monthname">' . esc_html( $month ) . '</span>'
					. '<span class="ctt-date__day">' . esc_html( wp_date( 'j', $ts ) ) . '</span>'
					. '<span class="ctt-date__weekday">' . esc_html( $weekday ) . '</span>';
		}
	}

	/**
	 * Bildfläche (Design „Karten“). Ohne Bild eine ruhige Fläche, auf der das Kalenderblatt sitzt.
	 * Mit Terminseiten ist die Fläche ein Link dorthin (gleiches Ziel wie der Titel, deshalb für
	 * Tastatur und Screenreader ausgeblendet).
	 *
	 * @param array $e Termin.
	 * @param array $a Attribute.
	 * @return string
	 */
	private static function media_html( array $e, array $a ) {
		$img = '';
		if ( $e['image_id'] ) {
			$img = (string) wp_get_attachment_image(
				$e['image_id'],
				'large',
				false,
				array(
					'class'    => 'ctt-event__img',
					'alt'      => '',
					'loading'  => 'lazy',
					'decoding' => 'async',
					// Schmal steht das Bild in voller Breite über dem Text, sonst in der 208 px breiten Spalte.
					'sizes'    => '(max-width: 700px) 100vw, 208px',
				)
			);
		}

		$inner = $img;
		if ( $a['linkDetails'] && '' !== $e['url'] && '' !== $e['title'] ) {
			$inner = '<a class="ctt-event__media-link" href="' . esc_url( $e['url'] ) . '" tabindex="-1" aria-hidden="true">' . $img . '</a>';
		}
		return '<div class="ctt-event__media' . ( '' === $img ? ' ctt-event__media--empty' : '' ) . '">' . $inner . '</div>';
	}

	/**
	 * Texte des Termins je Design.
	 *
	 * @param array  $e            Termin.
	 * @param string $design       Design.
	 * @param bool   $link_details Programm mit der Terminseite verlinken.
	 * @return string
	 */
	private static function main_html( array $e, $design, $link_details ) {
		$sr    = '<p class="ctt-sr"><time datetime="' . esc_attr( self::iso( $e ) ) . '">' . esc_html( self::long_date( $e ) ) . '</time></p>';
		$title = '';
		if ( '' !== $e['title'] ) {
			$title = $link_details && '' !== $e['url']
				? '<p class="ctt-event__title"><a class="ctt-event__link" href="' . esc_url( $e['url'] ) . '">' . esc_html( $e['title'] ) . '</a></p>'
				: '<p class="ctt-event__title">' . esc_html( $e['title'] ) . '</p>';
		}
		$note  = '' !== $e['note'] ? '<p class="ctt-event__note">' . esc_html( $e['note'] ) . '</p>' : '';

		if ( 'karten' === $design ) {
			$city = '' !== $e['city'] ? '<p class="ctt-event__city">' . esc_html( $e['city'] ) . '</p>' : '';
			/* translators: %s: Uhrzeit. */
			$time  = '' !== $e['time'] ? sprintf( __( '%s Uhr', 'clesk-termine' ), $e['time'] ) : '';
			$where = implode( ', ', array_filter( array( $e['venue'], $time ), 'strlen' ) );
			$venue = '' !== $where ? '<p class="ctt-event__venue">' . esc_html( $where ) . '</p>' : '';
			return $sr . $city . $title . $venue . $note;
		}

		$parts = array();
		if ( '' !== $e['city'] ) {
			$parts[] = '<span class="ctt-event__city">' . esc_html( $e['city'] ) . '</span>';
		}
		if ( '' !== $e['venue'] ) {
			$parts[] = '<span class="ctt-event__venue">' . esc_html( $e['venue'] ) . '</span>';
		}
		$place = $parts ? '<p class="ctt-event__place">' . implode( '<span class="ctt-sep" aria-hidden="true"> · </span>', $parts ) . '</p>' : '';

		if ( 'liste' === $design ) {
			return $sr . $place . $title . $note;
		}

		// Tickets: Status unter den Texten, damit die Aktionszeile ruhig bleibt.
		$badge = self::badge_html( $e );
		return $sr . $title . $place . $note . ( '' !== $badge ? '<p class="ctt-event__status">' . $badge . '</p>' : '' );
	}

	/**
	 * Status-Badge.
	 *
	 * @param array $e Termin.
	 * @return string
	 */
	public static function badge_html( array $e ) {
		$label = Clesk_Termine_Events::status_label( $e['status'] );
		return '' !== $label ? '<span class="ctt-badge ctt-badge--' . esc_attr( $e['status'] ) . '">' . esc_html( $label ) . '</span>' : '';
	}

	/**
	 * Status, Details, Teilen, Karte und Ticket-Link.
	 *
	 * @param array $e Termin.
	 * @param array $a Attribute.
	 * @return string
	 */
	private static function actions_html( array $e, array $a ) {
		$html   = 'ticket' === $a['design'] ? '' : self::badge_html( $e );
		$detail = $a['linkDetails'] && '' !== $e['url'];

		if ( $detail && '' !== $e['title'] ) {
			// Gleiches Ziel wie der verlinkte Titel, deshalb für Tastatur und Screenreader ausgeblendet.
			$html .= '<a class="ctt-icon ctt-icon--info" href="' . esc_url( $e['url'] ) . '" tabindex="-1" aria-hidden="true" title="' . esc_attr__( 'Details', 'clesk-termine' ) . '">' . self::icon( 'info' ) . '</a>';
		} elseif ( $detail ) {
			$html .= '<a class="ctt-icon ctt-icon--info" href="' . esc_url( $e['url'] ) . '" aria-label="' . esc_attr__( 'Details zu diesem Termin', 'clesk-termine' ) . '" title="' . esc_attr__( 'Details', 'clesk-termine' ) . '">' . self::icon( 'info' ) . '</a>';
		} elseif ( '' !== $e['info_url'] ) {
			$html .= self::info_link_html( $e, 'ctt-icon ctt-icon--info', false );
		}

		if ( $a['showShare'] && '' !== $e['url'] ) {
			$html .= self::share_link_html( $e, 'ctt-icon ctt-icon--share', false, $e['url'] . '#teilen' );
		}

		if ( $a['showMap'] ) {
			$html .= self::map_link_html( $e, 'ctt-icon ctt-icon--map', false );
		}

		return $html . self::ticket_html( $e, $a['ticketLabel'] );
	}

	/**
	 * Link „Mehr Infos“ (z. B. zur Programmseite).
	 *
	 * @param array  $e          Termin.
	 * @param string $class      CSS-Klassen.
	 * @param bool   $with_label Sichtbare Beschriftung statt reinem Symbol.
	 * @return string
	 */
	public static function info_link_html( array $e, $class, $with_label ) {
		if ( '' === $e['info_url'] ) {
			return '';
		}
		$external = self::is_external( $e['info_url'] );
		$label    = __( 'Mehr Infos', 'clesk-termine' );
		/* translators: %s: Programm. */
		$aria  = sprintf( __( 'Mehr Infos zu %s', 'clesk-termine' ), '' !== $e['title'] ? $e['title'] : __( 'diesem Termin', 'clesk-termine' ) );
		$aria .= $external ? ' ' . __( '(öffnet neues Fenster)', 'clesk-termine' ) : '';

		return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $e['info_url'] ) . '"' . ( $external ? ' target="_blank" rel="noopener"' : '' )
			. ' aria-label="' . esc_attr( $aria ) . '"' . ( $with_label ? '' : ' title="' . esc_attr( $label ) . '"' ) . '>'
			. self::icon( 'info' ) . ( $with_label ? '<span>' . esc_html( $label ) . '</span>' : '' ) . '</a>';
	}

	/**
	 * Link zur Karte (Google Maps, reiner Link).
	 *
	 * @param array  $e          Termin.
	 * @param string $class      CSS-Klassen.
	 * @param bool   $with_label Sichtbare Beschriftung statt reinem Symbol.
	 * @return string
	 */
	public static function map_link_html( array $e, $class, $with_label ) {
		$place = Clesk_Termine_Events::place_line( $e );
		if ( '' === $place ) {
			return '';
		}
		$label = __( 'Karte', 'clesk-termine' );
		/* translators: %s: Location und Anschrift. */
		$aria = sprintf( __( 'Karte: %s (öffnet neues Fenster)', 'clesk-termine' ), $place );

		return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode( $place ) ) . '" target="_blank" rel="noopener"'
			. ' aria-label="' . esc_attr( $aria ) . '"' . ( $with_label ? '' : ' title="' . esc_attr( $label ) . '"' ) . '>'
			. self::icon( 'pin' ) . ( $with_label ? '<span>' . esc_html( $label ) . '</span>' : '' ) . '</a>';
	}

	/**
	 * Teilen-Link. Mit JavaScript öffnet er den Teilen-Dialog des Geräts, sonst führt er zum Abschnitt „Teilen“ der Terminseite.
	 *
	 * @param array  $e          Termin.
	 * @param string $class      CSS-Klassen.
	 * @param bool   $with_label Sichtbare Beschriftung statt reinem Symbol.
	 * @param string $href       Ziel ohne JavaScript.
	 * @return string
	 */
	public static function share_link_html( array $e, $class, $with_label, $href ) {
		if ( '' === $e['url'] ) {
			return '';
		}
		$share = self::share_data( $e );
		$label = __( 'Teilen', 'clesk-termine' );
		/* translators: %s: Programm, Datum und Ort. */
		$aria = sprintf( __( 'Teilen: %s', 'clesk-termine' ), $share['text'] );

		return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $href ) . '" data-ctt-share data-ctt-title="' . esc_attr( $share['title'] ) . '" data-ctt-text="' . esc_attr( $share['text'] ) . '" data-ctt-url="' . esc_url( $e['url'] ) . '"'
			. ' aria-label="' . esc_attr( $aria ) . '"' . ( $with_label ? '' : ' title="' . esc_attr( $label ) . '"' ) . '>'
			. self::icon( 'share' ) . ( $with_label ? '<span>' . esc_html( $label ) . '</span>' : '' ) . '</a>';
	}

	/**
	 * Titel und Text zum Teilen, z. B. „Poetry Slam am Sa, 14.11.2026, 20:00 Uhr in Köln, Stadthalle“.
	 *
	 * @param array $e Termin.
	 * @return array{title:string,text:string}
	 */
	public static function share_data( array $e ) {
		// Ohne HTML-Entities: Der Text landet auch in Links zu WhatsApp und E-Mail.
		$title = '' !== $e['title'] ? self::plain( $e['title'] ) : __( 'Termin', 'clesk-termine' );
		$when  = rtrim( wp_date( 'D', $e['timestamp'] ), '.' ) . ', ' . wp_date( 'd.m.Y', $e['timestamp'] );
		if ( '' !== $e['time'] ) {
			/* translators: %s: Uhrzeit. */
			$when .= ', ' . sprintf( __( '%s Uhr', 'clesk-termine' ), $e['time'] );
		}
		$where = implode( ', ', array_filter( array( self::plain( $e['city'] ), self::plain( $e['venue'] ) ), 'strlen' ) );
		$text  = '' !== $where
			/* translators: 1: Programm, 2: Datum und Uhrzeit, 3: Stadt und Location. */
			? sprintf( __( '%1$s am %2$s in %3$s', 'clesk-termine' ), $title, $when, $where )
			/* translators: 1: Programm, 2: Datum und Uhrzeit. */
			: sprintf( __( '%1$s am %2$s', 'clesk-termine' ), $title, $when );

		return array(
			'title' => $title,
			'text'  => $text,
		);
	}

	/**
	 * Ticket-Link, leer ohne nutzbaren Vorverkauf.
	 *
	 * @param array  $e     Termin.
	 * @param string $label Beschriftung.
	 * @param string $class CSS-Klassen.
	 * @return string
	 */
	public static function ticket_html( array $e, $label, $class = 'ctt-ticket' ) {
		if ( ! self::has_tickets( $e ) ) {
			return '';
		}
		$external = self::is_external( $e['ticket_url'] );
		$what     = '' !== $e['title'] ? $e['title'] : __( 'diesen Termin', 'clesk-termine' );
		$when     = wp_date( 'j. F Y', $e['timestamp'] );
		$sr       = '' !== $e['city']
			/* translators: 1: Programm, 2: Datum, 3: Stadt. */
			? sprintf( __( 'für %1$s am %2$s in %3$s', 'clesk-termine' ), $what, $when, $e['city'] )
			/* translators: 1: Programm, 2: Datum. */
			: sprintf( __( 'für %1$s am %2$s', 'clesk-termine' ), $what, $when );
		$sr .= $external ? ' ' . __( '(öffnet neues Fenster)', 'clesk-termine' ) : '';

		return '<a class="' . esc_attr( $class ) . '" href="' . esc_url( $e['ticket_url'] ) . '"' . ( $external ? ' target="_blank" rel="noopener"' : '' ) . '>'
			. self::icon( 'ticket' ) . '<span>' . esc_html( $label ) . '</span><span class="ctt-sr"> ' . esc_html( $sr ) . '</span></a>';
	}

	/**
	 * Gibt es einen nutzbaren Ticket-Link?
	 *
	 * @param array $e Termin.
	 * @return bool
	 */
	public static function has_tickets( array $e ) {
		return '' !== $e['ticket_url'] && ! $e['is_past'] && ! in_array( $e['status'], Clesk_Termine_Events::NO_TICKET_STATUSES, true );
	}

	/**
	 * Zeigt ein Link auf eine andere Website?
	 *
	 * @param string $url Link.
	 * @return bool
	 */
	public static function is_external( $url ) {
		$host = wp_parse_url( $url, PHP_URL_HOST );
		return $host && strtolower( $host ) !== strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	}

	/**
	 * Beginn im ISO-Format (ohne Uhrzeit nur das Datum).
	 *
	 * @param array $e Termin.
	 * @return string
	 */
	public static function iso( array $e ) {
		return '' !== $e['time'] ? $e['start']->format( 'Y-m-d\TH:i:sP' ) : $e['date'];
	}

	/**
	 * Ausgeschriebenes Datum, z. B. „Mittwoch, 11. November 2026, 20:00 Uhr“.
	 *
	 * @param array $e Termin.
	 * @return string
	 */
	public static function long_date( array $e ) {
		$text = wp_date( 'l, j. F Y', $e['timestamp'] );
		if ( '' !== $e['time'] ) {
			/* translators: %s: Uhrzeit. */
			$text .= ', ' . sprintf( __( '%s Uhr', 'clesk-termine' ), $e['time'] );
		}
		return $text;
	}

	/**
	 * Strukturierte Daten (schema.org/Event) eines Termins.
	 *
	 * @param array  $e           Termin.
	 * @param string $description Beschreibung, sonst der Hinweis des Termins.
	 * @return array|null Null für vergangene und geschlossene Termine sowie Termine ohne Programm.
	 */
	public static function schema_item( array $e, $description = '' ) {
		static $performer = null;

		if ( $e['is_past'] || 'private' === $e['status'] || '' === $e['title'] ) {
			return null;
		}
		if ( null === $performer ) {
			$performer = (string) apply_filters( 'clesk_termine_performer', self::default_performer() );
		}
		$status = array(
			'cancelled' => 'https://schema.org/EventCancelled',
			'moved'     => 'https://schema.org/EventRescheduled',
		);

		$item = array(
			'@context'            => 'https://schema.org',
			'@type'               => 'Event',
			'name'                => self::plain( $e['title'] ),
			'startDate'           => self::iso( $e ),
			'eventStatus'         => isset( $status[ $e['status'] ] ) ? $status[ $e['status'] ] : 'https://schema.org/EventScheduled',
			'eventAttendanceMode' => 'https://schema.org/OfflineEventAttendanceMode',
		);
		if ( '' !== $e['url'] ) {
			$item['url'] = $e['url'];
		}
		if ( '' !== $e['venue'] || '' !== $e['city'] ) {
			$address = array( '@type' => 'PostalAddress' );
			if ( '' !== $e['street'] ) {
				$address['streetAddress'] = self::plain( $e['street'] );
			}
			if ( '' !== $e['zip'] ) {
				$address['postalCode'] = self::plain( $e['zip'] );
			}
			if ( '' !== $e['city'] ) {
				$address['addressLocality'] = self::plain( $e['city'] );
			}
			$item['location'] = array(
				'@type'   => 'Place',
				'name'    => self::plain( '' !== $e['venue'] ? $e['venue'] : $e['city'] ),
				'address' => $address,
			);
		}
		if ( '' !== $performer ) {
			$item['performer'] = array(
				'@type' => 'Person',
				'name'  => $performer,
			);
		}
		$description = '' !== $description ? $description : $e['note'];
		if ( '' !== $description ) {
			$item['description'] = self::plain( $description );
		}
		if ( $e['image_id'] ) {
			$image = wp_get_attachment_image_url( $e['image_id'], 'large' );
			if ( $image ) {
				$item['image'] = array( $image );
			}
		}
		$ticket_url = Clesk_Termine_Events::absolute_url( $e['ticket_url'] );
		if ( '' !== $ticket_url && 'cancelled' !== $e['status'] ) {
			$item['offers'] = array(
				'@type'        => 'Offer',
				'url'          => $ticket_url,
				'availability' => 'soldout' === $e['status'] ? 'https://schema.org/SoldOut' : 'https://schema.org/InStock',
			);
		}
		return $item;
	}

	/**
	 * Text ohne HTML-Entities (Titel können in der Datenbank kodiert stehen, etwa „&amp;“).
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function plain( $text ) {
		return html_entity_decode( (string) $text, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Strukturierte Daten vormerken.
	 *
	 * Ausgegeben wird einmal im Footer. So bleibt es bei mehreren Listen auf einer Seite bei
	 * einem Eintrag je Termin, auch wenn ein anderes Plugin den Inhalt vorab schon einmal rendert.
	 *
	 * @param array[] $events      Termine.
	 * @param string  $description Beschreibung (nur sinnvoll bei genau einem Termin).
	 * @return string Leer, außer der Footer läuft bereits (dann direkt das Skript).
	 */
	public static function schema_html( array $events, $description = '' ) {
		foreach ( $events as $e ) {
			if ( isset( self::$schema_items[ $e['id'] ] ) ) {
				continue;
			}
			$item = self::schema_item( $e, $description );
			if ( $item ) {
				self::$schema_items[ $e['id'] ] = $item;
			}
		}

		if ( doing_action( 'wp_footer' ) || did_action( 'wp_footer' ) ) {
			return self::schema_script();
		}
		if ( ! has_action( 'wp_footer', array( __CLASS__, 'print_schema' ) ) ) {
			add_action( 'wp_footer', array( __CLASS__, 'print_schema' ), 20 );
		}
		return '';
	}

	/**
	 * Vorgemerkte strukturierte Daten im Footer ausgeben.
	 */
	public static function print_schema() {
		echo self::schema_script(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON mit JSON_HEX_TAG.
	}

	/**
	 * Skript-Element mit allen noch nicht ausgegebenen Terminen.
	 *
	 * @return string
	 */
	private static function schema_script() {
		$items = array();
		foreach ( self::$schema_items as $id => $item ) {
			if ( is_array( $item ) ) {
				$items[]                   = $item;
				self::$schema_items[ $id ] = true;
			}
		}
		if ( ! $items ) {
			return '';
		}
		$json = wp_json_encode( 1 === count( $items ) ? $items[0] : $items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS );
		return $json ? '<script type="application/ld+json">' . $json . '</script>' . "\n" : '';
	}

	/**
	 * Künstlername aus dem Website-Titel: Teil vor einem Trenner wie „ - “, „ – “ oder „ | “,
	 * damit ein Slogan im Titel nicht als Name erscheint.
	 *
	 * @return string
	 */
	private static function default_performer() {
		$name  = html_entity_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES, 'UTF-8' );
		$parts = preg_split( '/\s+[-–—|·:]\s+/u', $name );
		return trim( is_array( $parts ) && '' !== trim( $parts[0] ) ? $parts[0] : $name );
	}

	/**
	 * Inline-SVG-Symbol. Pfade aus Lucide (ISC-Lizenz), siehe ICONS-LICENSE.txt.
	 *
	 * @param string $name  Symbolname.
	 * @param string $class Zusätzliche Klasse.
	 * @return string
	 */
	public static function icon( $name, $class = '' ) {
		$paths = array(
			'ticket'   => '<path d="M2 9a3 3 0 0 1 0 6v2a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-2a3 3 0 0 1 0-6V7a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2Z"/><path d="M13 5v2"/><path d="M13 17v2"/><path d="M13 11v2"/>',
			'pin'      => '<path d="M20 10c0 4.993-5.539 10.193-7.399 11.799a1 1 0 0 1-1.202 0C9.539 20.193 4 14.993 4 10a8 8 0 0 1 16 0"/><circle cx="12" cy="10" r="3"/>',
			'info'     => '<circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/>',
			'calendar' => '<path d="M8 2v3"/><path d="M16 2v3"/><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18"/>',
			'venue'    => '<path d="M10 18v-7"/><path d="M11.119 2.205a2 2 0 0 1 1.762 0l7.84 3.846A.5.5 0 0 1 20.5 7h-17a.5.5 0 0 1-.22-.949z"/><path d="M14 18v-7"/><path d="M18 18v-7"/><path d="M3 22h18"/><path d="M6 18v-7"/>',
			'close'    => '<path d="M18 6 6 18"/><path d="m6 6 12 12"/>',
			'share'    => '<circle cx="18" cy="5" r="3"/><circle cx="6" cy="12" r="3"/><circle cx="18" cy="19" r="3"/><line x1="8.59" x2="15.42" y1="13.51" y2="17.49"/><line x1="15.41" x2="8.59" y1="6.51" y2="10.49"/>',
			'copy'     => '<rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/>',
			'mail'     => '<path d="m22 7-8.991 5.727a2 2 0 0 1-2.009 0L2 7"/><rect x="2" y="4" width="20" height="16" rx="2"/>',
			'back'     => '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
		);
		if ( ! isset( $paths[ $name ] ) ) {
			return '';
		}
		return '<svg class="ctt-svg' . ( '' !== $class ? ' ' . esc_attr( $class ) : '' ) . '" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
			. $paths[ $name ] . '</svg>';
	}
}
