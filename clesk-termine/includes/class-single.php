<?php
/**
 * Eigene Seite je Termin: Inhalt, Seitentitel, Vorschau-Daten zum Teilen und Angaben für Suchmaschinen.
 *
 * Die Seite nutzt die Einzelansicht des Themes (Kopf, Fuß, Inhaltsbreite) und ersetzt nur den Inhalt.
 *
 * @package Clesk_Termine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Einzelansicht eines Termins.
 */
final class Clesk_Termine_Single {

	/**
	 * Gibt die Terminseite die Überschrift selbst aus (statt des Themes)? null = noch nicht entschieden.
	 *
	 * @var bool|null
	 */
	private static $print_title = null;

	/**
	 * Zeigt das Theme auf dieser Seite seine eigene Überschrift (GeneratePress: nicht abgeschaltet)?
	 *
	 * @var bool
	 */
	private static $theme_title = true;

	/**
	 * Wird gerade nur abgefragt, was das Theme zur Überschrift entschieden hat?
	 *
	 * @var bool
	 */
	private static $probing = false;

	/**
	 * Läuft gerade die Hauptschleife des Themes?
	 *
	 * @var bool
	 */
	private static $in_loop = false;

	/**
	 * Schutz gegen verschachteltes Rendern.
	 *
	 * @var bool
	 */
	private static $rendering = false;

	/**
	 * Aufbereiteter Termin der aufgerufenen Seite (false = noch nicht ermittelt).
	 *
	 * @var array|null|false
	 */
	private static $event = false;

	/**
	 * Hooks registrieren.
	 */
	public static function init() {
		add_action( 'wp', array( __CLASS__, 'setup' ) );
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 20 );
		add_filter( 'get_the_excerpt', array( __CLASS__, 'excerpt' ), 9, 2 );
		add_filter( 'wp_robots', array( __CLASS__, 'robots' ) );
		add_filter( 'wp_sitemaps_posts_query_args', array( __CLASS__, 'sitemap_args' ), 10, 2 );
		add_filter( 'wp_sitemaps_users_query_args', array( __CLASS__, 'sitemap_users_args' ) );
		add_filter( 'oembed_response_data', array( __CLASS__, 'oembed_data' ), 10, 2 );
		add_action( 'template_redirect', array( __CLASS__, 'redirect_collections' ) );
	}

	/**
	 * Ist die aufgerufene Seite eine Terminseite?
	 *
	 * @return bool
	 */
	private static function is_event_page() {
		return Clesk_Termine_Events::has_single_pages() && is_singular( Clesk_Termine_Events::POST_TYPE );
	}

	/**
	 * Auf Terminseiten: Styles laden, Theme-Ausgabe anpassen, Kopfdaten vorbereiten.
	 */
	public static function setup() {
		// Einbettungen (oEmbed) zeigen Titel, Auszug und Bild wie bei jedem Beitrag.
		if ( ! self::is_event_page() || is_embed() ) {
			return;
		}
		$post = get_queried_object();
		if ( ! $post instanceof WP_Post ) {
			return;
		}

		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );

		// Passwortgeschützt: WordPress zeigt sein Passwortformular. Titel, Kopfdaten und Theme bleiben unverändert,
		// damit weder Stadt noch Datum nach außen dringen und die Überschrift des Themes erhalten bleibt.
		if ( post_password_required( $post ) ) {
			return;
		}

		// Das Beitragsbild steht im Terminkopf. Die automatische Ausgabe des Themes in der Schleife entfällt.
		add_action( 'loop_start', array( __CLASS__, 'loop_start' ) );
		add_action( 'loop_end', array( __CLASS__, 'loop_end' ) );
		add_filter( 'has_post_thumbnail', array( __CLASS__, 'hide_theme_thumbnail' ), 10, 2 );
		add_filter( 'post_thumbnail_html', array( __CLASS__, 'hide_theme_thumbnail_html' ), 10, 2 );
		// GP Premium (Blog-Modul) gäbe den Rahmen des Beitragsbilds sonst leer aus, mit Abstand über dem Termin.
		add_filter( 'generate_single_featured_image_output', '__return_empty_string' );

		// GeneratePress: keine Seitenleiste, die Überschrift kommt aus dem Terminkopf (siehe print_title()).
		if ( function_exists( 'generate_get_layout' ) ) {
			add_filter( 'generate_sidebar_layout', array( __CLASS__, 'no_sidebar' ) );
			add_filter( 'generate_show_title', array( __CLASS__, 'theme_title' ), 99 );
			add_filter( 'generate_show_entry_header', array( __CLASS__, 'theme_entry_header' ), 99 );
			if ( defined( 'GP_PREMIUM_VERSION' ) ) {
				add_action( 'generate_inside_site_container', array( __CLASS__, 'header_band_script' ), 1 );
			}
		}

		if ( self::outputs_meta() ) {
			add_filter( 'document_title_parts', array( __CLASS__, 'title_parts' ) );
			add_action( 'wp_head', array( __CLASS__, 'head_meta' ), 1 );
		}
	}

	/**
	 * Gibt die Terminseite die Überschrift (h1) selbst aus?
	 *
	 * Bei GeneratePress ja, andere Themes zeigen ihren Titel selbst. Hat GeneratePress die Überschrift
	 * für diese Seite abgeschaltet (etwa weil ein Seitenkopf aus GP Premium den Titel zeigt), steht sie
	 * auch im Terminkopf nicht noch einmal. Entschieden wird erst beim Rendern: Dann haben Theme und
	 * Erweiterungen ihre Einstellungen sicher angemeldet.
	 *
	 * @return bool
	 */
	private static function print_title() {
		if ( null === self::$print_title ) {
			$post = get_queried_object();
			$own  = function_exists( 'generate_get_layout' ) && $post instanceof WP_Post && '' !== trim( (string) $post->post_title );
			if ( function_exists( 'generate_show_title' ) ) {
				self::$probing     = true;
				self::$theme_title = (bool) generate_show_title();
				self::$probing     = false;
			}

			/**
			 * Soll die Terminseite die Überschrift (h1) selbst ausgeben?
			 * Standard: bei GeneratePress, solange das Theme seine Überschrift dort nicht abgeschaltet hat.
			 */
			self::$print_title = (bool) apply_filters( 'clesk_termine_single_print_title', $own && self::$theme_title );
		}
		return self::$print_title;
	}

	/**
	 * GeneratePress: Steht die Überschrift im Terminkopf, gibt das Theme sie nicht noch einmal aus.
	 *
	 * @param bool $show Bisherige Entscheidung des Themes.
	 * @return bool
	 */
	public static function theme_title( $show ) {
		if ( self::$probing ) {
			return $show;
		}
		return self::print_title() ? false : $show;
	}

	/**
	 * GeneratePress: Kopfbereich des Beitrags nur zeigen, wenn das Theme dort die Überschrift ausgibt.
	 * Sonst bliebe ein leerer Bereich mit Abstand über dem Termin.
	 *
	 * @param bool $show Bisherige Entscheidung des Themes.
	 * @return bool
	 */
	public static function theme_entry_header( $show ) {
		return ( self::print_title() || ! self::$theme_title ) ? false : $show;
	}

	/**
	 * GeneratePress Premium kann den Kopf über den Inhalt legen („Merge“), gedacht für einen Seitenkopf
	 * mit Bild oder Farbfläche. Fehlt der auf der Terminseite, holt das CSS den Kopf zurück in den
	 * Seitenfluss. Das Skript assets/headband.js prüft zusätzlich, ob das Menü auf dem Seitenhintergrund
	 * lesbar ist, und gibt dem Kopf sonst eine Fläche in der Textfarbe der Seite (Klasse ctt-headband).
	 * Es steht direkt hinter dem Kopf in der Seite, damit nichts nachträglich umspringt.
	 */
	public static function header_band_script() {
		$file = CLESK_TERMINE_DIR . 'assets/headband.js';
		$js   = is_readable( $file ) ? (string) file_get_contents( $file ) : ''; // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- lokale Plugin-Datei.
		if ( '' !== $js ) {
			wp_print_inline_script_tag( $js, array( 'id' => 'clesk-termine-headband' ) );
		}
	}

	/**
	 * Termine haben kein Archiv und keinen Feed. Sammelansichten wie /?post_type=clesk_termin
	 * führen deshalb zur Terminübersicht (oder zur Startseite).
	 */
	public static function redirect_collections() {
		if ( is_admin() || is_singular() || is_404() ) {
			return;
		}
		$types = (array) get_query_var( 'post_type' );
		if ( ! in_array( Clesk_Termine_Events::POST_TYPE, $types, true ) ) {
			return;
		}
		$target = strtok( Clesk_Termine_Events::overview_url(), '#' );
		wp_safe_redirect( $target ? $target : home_url( '/' ), 302 );
		exit;
	}

	/**
	 * Styles und Skript des Blocks auch auf der Terminseite laden.
	 */
	public static function enqueue() {
		wp_enqueue_style( generate_block_asset_handle( Clesk_Termine_Block::NAME, 'style' ) );
		wp_enqueue_script( generate_block_asset_handle( Clesk_Termine_Block::NAME, 'viewScript' ) );
	}

	/**
	 * Body-Klasse für eigene Anpassungen im Theme.
	 *
	 * @param string[] $classes Klassen.
	 * @return string[]
	 */
	public static function body_class( $classes ) {
		$classes[] = 'ctt-single-page';
		return $classes;
	}

	/**
	 * GeneratePress-Layout ohne Seitenleiste.
	 *
	 * @return string
	 */
	public static function no_sidebar() {
		return 'no-sidebar';
	}

	/**
	 * Beginn der Hauptschleife merken.
	 *
	 * @param WP_Query $query Abfrage.
	 */
	public static function loop_start( $query ) {
		if ( $query instanceof WP_Query && $query->is_main_query() ) {
			self::$in_loop = true;
		}
	}

	/**
	 * Ende der Hauptschleife merken.
	 *
	 * @param WP_Query $query Abfrage.
	 */
	public static function loop_end( $query ) {
		if ( $query instanceof WP_Query && $query->is_main_query() ) {
			self::$in_loop = false;
		}
	}

	/**
	 * Ist der Beitrag der Termin der aufgerufenen Seite und läuft die Hauptschleife?
	 *
	 * @param WP_Post|int|null $post Beitrag.
	 * @return bool
	 */
	private static function is_queried_event_in_loop( $post ) {
		if ( ! self::$in_loop ) {
			return false;
		}
		$post = get_post( $post );
		return $post && Clesk_Termine_Events::POST_TYPE === $post->post_type && (int) $post->ID === (int) get_queried_object_id();
	}

	/**
	 * In der Schleife des Themes so tun, als gäbe es kein Beitragsbild.
	 *
	 * @param bool             $has  Bisheriger Wert.
	 * @param WP_Post|int|null $post Beitrag.
	 * @return bool
	 */
	public static function hide_theme_thumbnail( $has, $post = null ) {
		return self::is_queried_event_in_loop( $post ) ? false : $has;
	}

	/**
	 * Beitragsbild, das ein Theme ohne Prüfung ausgibt, in der Schleife unterdrücken.
	 *
	 * @param string $html    Bild-HTML.
	 * @param int    $post_id Beitrags-ID.
	 * @return string
	 */
	public static function hide_theme_thumbnail_html( $html, $post_id = 0 ) {
		return self::is_queried_event_in_loop( $post_id ) ? '' : $html;
	}

	/**
	 * Kopfdaten nur ausgeben, wenn kein SEO-Plugin das übernimmt.
	 *
	 * @return bool
	 */
	private static function outputs_meta() {
		$seo_plugin = defined( 'WPSEO_VERSION' ) || defined( 'RANK_MATH_VERSION' ) || defined( 'SEOPRESS_VERSION' )
			|| defined( 'AIOSEO_VERSION' ) || defined( 'THE_SEO_FRAMEWORK_VERSION' ) || defined( 'SLIM_SEO_VER' );

		/**
		 * Seitentitel, Beschreibung und Vorschau-Daten (Open Graph) der Terminseiten ausgeben?
		 * Standard: ja, solange kein bekanntes SEO-Plugin aktiv ist.
		 */
		return (bool) apply_filters( 'clesk_termine_output_meta', ! $seo_plugin );
	}

	/**
	 * Termin der aufgerufenen Seite.
	 *
	 * @return array|null
	 */
	private static function current_event() {
		if ( false === self::$event ) {
			$post        = get_queried_object();
			self::$event = ( $post instanceof WP_Post && Clesk_Termine_Events::POST_TYPE === $post->post_type )
				? Clesk_Termine_Events::prepare( $post, true )
				: null;
		}
		return self::$event;
	}

	/**
	 * Seitentitel, z. B. „Poetry Slam in Köln am 14.11.2026“.
	 *
	 * @param array $e Termin.
	 * @return string
	 */
	private static function seo_title( array $e ) {
		$title = '' !== $e['title'] ? $e['title'] : __( 'Termin', 'clesk-termine' );
		if ( ! $e['has_date'] ) {
			/* translators: 1: Programm, 2: Stadt. */
			return '' !== $e['city'] ? sprintf( __( '%1$s in %2$s', 'clesk-termine' ), $title, $e['city'] ) : $title;
		}
		$date = wp_date( 'd.m.Y', $e['timestamp'] );
		return '' !== $e['city']
			/* translators: 1: Programm, 2: Stadt, 3: Datum. */
			? sprintf( __( '%1$s in %2$s am %3$s', 'clesk-termine' ), $title, $e['city'], $date )
			/* translators: 1: Programm, 2: Datum. */
			: sprintf( __( '%1$s am %2$s', 'clesk-termine' ), $title, $date );
	}

	/**
	 * Titel im Browser-Tab und in Suchergebnissen.
	 *
	 * @param array $parts Titelbestandteile.
	 * @return array
	 */
	public static function title_parts( $parts ) {
		$e = self::current_event();
		if ( $e ) {
			$parts['title'] = self::seo_title( $e );
		}
		return $parts;
	}

	/**
	 * Kurzbeschreibung aus Eckdaten, Status, Hinweis und Beschreibungstext.
	 *
	 * @param array   $e      Termin.
	 * @param WP_Post $post   Beitrag.
	 * @param int     $length Höchstlänge in Zeichen.
	 * @return string
	 */
	private static function description( array $e, $post, $length ) {
		$plain = array( 'Clesk_Termine_Render', 'plain' );
		$title = '' !== $e['title'] ? call_user_func( $plain, $e['title'] ) : __( 'Termin', 'clesk-termine' );
		$text  = $title;
		if ( $e['has_date'] ) {
			/* translators: 1: Programm, 2: ausgeschriebenes Datum mit Uhrzeit. */
			$text = sprintf( __( '%1$s am %2$s', 'clesk-termine' ), $title, Clesk_Termine_Render::long_date( $e ) );
		}
		$where = implode( ', ', array_filter( array( call_user_func( $plain, $e['city'] ), call_user_func( $plain, $e['venue'] ) ), 'strlen' ) );
		if ( '' !== $where ) {
			/* translators: 1: bisheriger Text, 2: Stadt und Location. */
			$text = sprintf( __( '%1$s in %2$s', 'clesk-termine' ), $text, $where );
		}
		$text = self::sentence( $text );

		$note  = trim( call_user_func( $plain, $e['note'] ) );
		$label = in_array( $e['status'], array( 'soldout', 'cancelled', 'moved', 'extra' ), true ) ? Clesk_Termine_Events::status_label( $e['status'] ) : '';
		// Steht der Status schon im Hinweis („Verlegt vom …“), nicht doppelt nennen.
		if ( '' !== $label && ( '' === $note || false === stripos( $note, $label ) ) ) {
			$text .= ' ' . self::sentence( $label );
		}
		if ( '' !== $note ) {
			$text .= ' ' . self::sentence( $note );
		}

		$body = wp_strip_all_tags( strip_shortcodes( (string) $post->post_content ) );
		// Nackte Adressen (z. B. eingebettete Videos) gehören nicht in die Beschreibung.
		$body = (string) preg_replace( '#https?://\S+#u', ' ', $body );
		$body = trim( (string) preg_replace( '/\s+/u', ' ', call_user_func( $plain, $body ) ) );
		if ( '' !== $body ) {
			$text .= ' ' . $body;
		}
		return self::truncate( $text, $length );
	}

	/**
	 * Text mit genau einem Satzzeichen am Ende.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function sentence( $text ) {
		$text = rtrim( (string) $text, " .,;:\t\n" );
		return preg_match( '/[!?…]$/u', $text ) ? $text : $text . '.';
	}

	/**
	 * Text an einer Wortgrenze kürzen.
	 *
	 * @param string $text   Text.
	 * @param int    $length Höchstlänge in Zeichen.
	 * @return string
	 */
	private static function truncate( $text, $length ) {
		$strlen = function_exists( 'mb_strlen' ) ? 'mb_strlen' : 'strlen';
		$substr = function_exists( 'mb_substr' ) ? 'mb_substr' : 'substr';
		if ( $strlen( $text ) <= $length ) {
			return $text;
		}
		$cut   = $substr( $text, 0, $length - 1 );
		$space = function_exists( 'mb_strrpos' ) ? mb_strrpos( $cut, ' ' ) : strrpos( $cut, ' ' );
		if ( false !== $space && $space > $length * 0.6 ) {
			$cut = $substr( $cut, 0, $space );
		}
		return rtrim( $cut, " ,;:.\t" ) . '…';
	}

	/**
	 * Bild für Vorschauen beim Teilen: Beitragsbild, sonst das Website-Icon.
	 *
	 * @param array $e Termin.
	 * @return array{url:string,width:int,height:int}|null
	 */
	private static function share_image( array $e ) {
		if ( $e['image_id'] ) {
			$src = wp_get_attachment_image_src( $e['image_id'], 'large' );
			if ( $src ) {
				return array(
					'url'    => (string) $src[0],
					'width'  => (int) $src[1],
					'height' => (int) $src[2],
				);
			}
		}
		$icon = get_site_icon_url( 512 );
		if ( $icon ) {
			return array(
				'url'    => (string) $icon,
				'width'  => 0,
				'height' => 0,
			);
		}
		return null;
	}

	/**
	 * Beschreibung und Vorschau-Daten (Open Graph, Twitter Card) im Seitenkopf.
	 */
	public static function head_meta() {
		$e    = self::current_event();
		$post = get_queried_object();
		if ( ! $e || ! $post instanceof WP_Post || post_password_required( $post ) ) {
			return;
		}

		$title       = self::seo_title( $e );
		$description = self::description( $e, $post, 200 );
		$url         = '' !== $e['url'] ? $e['url'] : (string) get_permalink( $post );
		$image       = self::share_image( $e );
		$large       = $image && $image['width'] >= 600;

		$tags = array(
			array( 'name', 'description', $description ),
			array( 'property', 'og:type', 'website' ),
			array( 'property', 'og:title', $title ),
			array( 'property', 'og:description', $description ),
			array( 'property', 'og:url', $url ),
			array( 'property', 'og:site_name', html_entity_decode( (string) get_bloginfo( 'name' ), ENT_QUOTES, 'UTF-8' ) ),
			array( 'property', 'og:locale', get_locale() ),
		);
		if ( $image ) {
			$tags[] = array( 'property', 'og:image', $image['url'] );
			if ( $image['width'] && $image['height'] ) {
				$tags[] = array( 'property', 'og:image:width', (string) $image['width'] );
				$tags[] = array( 'property', 'og:image:height', (string) $image['height'] );
			}
		}
		$tags[] = array( 'name', 'twitter:card', $large ? 'summary_large_image' : 'summary' );

		echo "\n";
		foreach ( $tags as $tag ) {
			printf( '<meta %1$s="%2$s" content="%3$s">' . "\n", esc_attr( $tag[0] ), esc_attr( $tag[1] ), esc_attr( $tag[2] ) );
		}
	}

	/**
	 * Vergangene und geschlossene Termine nicht in Suchmaschinen aufnehmen.
	 *
	 * @param array $robots Robots-Angaben.
	 * @return array
	 */
	public static function robots( $robots ) {
		if ( ! self::is_event_page() ) {
			return $robots;
		}
		$e    = self::current_event();
		$post = get_queried_object();
		if ( ! $e || ! $e['has_date'] || $e['is_past'] || 'private' === $e['status'] || ( $post instanceof WP_Post && '' !== $post->post_password ) ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
			unset( $robots['max-image-preview'] );
		}
		return $robots;
	}

	/**
	 * In die Sitemap von WordPress kommen nur kommende, öffentliche Termine.
	 *
	 * @param array  $args      Argumente der Abfrage.
	 * @param string $post_type Beitragstyp.
	 * @return array
	 */
	public static function sitemap_args( $args, $post_type ) {
		if ( Clesk_Termine_Events::POST_TYPE !== $post_type ) {
			return $args;
		}
		$meta_query   = isset( $args['meta_query'] ) && is_array( $args['meta_query'] ) ? $args['meta_query'] : array();
		$meta_query[] = array(
			'key'     => Clesk_Termine_Events::FIELDS['date'],
			'value'   => Clesk_Termine_Events::today(),
			'compare' => '>=',
			'type'    => 'DATE',
		);
		$meta_query[] = array(
			'relation' => 'OR',
			array(
				'key'     => Clesk_Termine_Events::FIELDS['status'],
				'value'   => 'private',
				'compare' => '!=',
			),
			array(
				'key'     => Clesk_Termine_Events::FIELDS['status'],
				'compare' => 'NOT EXISTS',
			),
		);
		$args['meta_query']   = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
		$args['has_password'] = false;
		return $args;
	}

	/**
	 * Benutzer-Sitemap: Termine zählen nicht als Beiträge eines Autors. Das Konto, das Termine pflegt,
	 * soll nicht allein deshalb in der Sitemap stehen.
	 *
	 * @param array $args Argumente für WP_User_Query.
	 * @return array
	 */
	public static function sitemap_users_args( $args ) {
		if ( ! isset( $args['has_published_posts'] ) || ! is_array( $args['has_published_posts'] ) ) {
			return $args;
		}
		$types = array_values( array_diff( $args['has_published_posts'], array( Clesk_Termine_Events::POST_TYPE ) ) );
		if ( $types ) {
			$args['has_published_posts'] = $types;
		} else {
			// Eine leere Liste hieße für WP_User_Query „keine Einschränkung“, dann stünden alle Konten in der Sitemap.
			$args['include'] = array( 0 );
		}
		return $args;
	}

	/**
	 * Einbettungen (oEmbed): als Urheber die Website nennen, nicht das Konto, das den Termin angelegt hat.
	 *
	 * @param array   $data Antwortdaten.
	 * @param WP_Post $post Beitrag.
	 * @return array
	 */
	public static function oembed_data( $data, $post ) {
		if ( is_array( $data ) && $post instanceof WP_Post && Clesk_Termine_Events::POST_TYPE === $post->post_type ) {
			$data['author_name'] = get_bloginfo( 'name' );
			$data['author_url']  = home_url( '/' );
		}
		return $data;
	}

	/**
	 * Auszug eines Termins (Einbettungen, Suchergebnisse, SEO-Plugins): Eckdaten in einem Satz, dann die Beschreibung.
	 *
	 * @param string       $excerpt Bisheriger Auszug.
	 * @param WP_Post|null $post    Beitrag.
	 * @return string
	 */
	public static function excerpt( $excerpt, $post = null ) {
		$post = get_post( $post );
		if ( '' !== trim( (string) $excerpt ) || ! $post || Clesk_Termine_Events::POST_TYPE !== $post->post_type || post_password_required( $post ) ) {
			return $excerpt;
		}
		return self::description( Clesk_Termine_Events::prepare( $post, true ), $post, 300 );
	}

	/**
	 * Inhalt der Terminseite: Eckdaten, Beschreibung, Teilen, weitere Termine.
	 *
	 * @param string $content Beschreibung des Termins (bereits formatiert).
	 * @return string
	 */
	public static function filter_content( $content ) {
		// Nicht in Einbettungen und nicht, wenn WordPress gerade einen Auszug aus dem Inhalt bildet.
		if ( self::$rendering || ! self::is_event_page() || ! in_the_loop() || ! is_main_query() || is_embed() || doing_filter( 'get_the_excerpt' ) ) {
			return $content;
		}
		$post = get_post();
		if ( ! $post || Clesk_Termine_Events::POST_TYPE !== $post->post_type || (int) $post->ID !== (int) get_queried_object_id() || post_password_required( $post ) ) {
			return $content;
		}

		self::$rendering = true;
		$html            = self::render( $post, (string) $content );
		self::$rendering = false;
		return $html;
	}

	/**
	 * HTML der Terminseite.
	 *
	 * @param WP_Post $post    Termin.
	 * @param string  $content Beschreibung (bereits formatiert).
	 * @return string
	 */
	private static function render( $post, $content ) {
		$e = Clesk_Termine_Events::prepare( $post, true );

		$classes = array( 'ctt', 'ctt-single' );
		if ( '' !== $e['status'] ) {
			$classes[] = 'ctt-single--' . $e['status'];
		}
		if ( $e['is_past'] ) {
			$classes[] = 'ctt-single--past';
		}
		if ( ! $e['has_date'] ) {
			$classes[] = 'ctt-single--undated';
		}

		// Strukturierte Daten zuerst vormerken: Der Termin dieser Seite steht dann vor den weiteren Terminen.
		$schema = ( '' !== $e['url'] && $e['has_date'] )
			? Clesk_Termine_Render::schema_html( array( $e ), self::description( $e, $post, 400 ) )
			: '';

		$html  = '<div class="ctt-wrap ctt-wrap--single"><div class="' . esc_attr( implode( ' ', $classes ) ) . '">';
		$html .= self::head_html( $e );
		$html .= self::body_html( $e, $content );

		if ( '' !== $e['url'] && $e['has_date'] ) {
			$html .= self::share_html( $e );
		}
		$html .= self::more_html( $e );

		$overview = Clesk_Termine_Events::overview_url();
		if ( '' !== $overview ) {
			$html .= '<p class="ctt-single__back"><a class="ctt-btn" href="' . esc_url( $overview ) . '">' . Clesk_Termine_Render::icon( 'back' )
				. '<span>' . esc_html__( 'Alle Termine', 'clesk-termine' ) . '</span></a></p>';
		}

		return $html . '</div>' . $schema . '</div>';
	}

	/**
	 * Terminkopf: Datum, Überschrift, Ort, Status und Aktionen.
	 *
	 * @param array $e Termin.
	 * @return string
	 */
	private static function head_html( array $e ) {
		$html = '<div class="ctt-single__head">';

		if ( $e['has_date'] ) {
			$ts    = $e['timestamp'];
			$html .= '<div class="ctt-single__date" aria-hidden="true">'
				. '<span class="ctt-date__weekday">' . esc_html( rtrim( wp_date( 'D', $ts ), '.' ) ) . '</span>'
				. '<span class="ctt-date__day">' . esc_html( wp_date( 'j', $ts ) ) . '</span>'
				. '<span class="ctt-date__monthname">' . esc_html( rtrim( wp_date( 'M', $ts ), '.' ) . ' ' . wp_date( 'Y', $ts ) ) . '</span>'
				. ( '' !== $e['time'] ? '<span class="ctt-date__meta">' . esc_html( $e['time'] ) . '</span>' : '' )
				. '</div>';
		}

		$html .= '<div class="ctt-single__main">';
		if ( self::print_title() && '' !== $e['title'] ) {
			$html .= '<h1 class="ctt-single__title">' . esc_html( $e['title'] ) . '</h1>';
		}

		$html .= $e['has_date']
			? '<p class="ctt-single__when"><time datetime="' . esc_attr( Clesk_Termine_Render::iso( $e ) ) . '">' . esc_html( Clesk_Termine_Render::long_date( $e ) ) . '</time></p>'
			: '<p class="ctt-single__when">' . esc_html__( 'Das Datum wird noch bekannt gegeben.', 'clesk-termine' ) . '</p>';

		$address = implode( ', ', array_filter( array( $e['street'], trim( $e['zip'] . ' ' . $e['city'] ) ), 'strlen' ) );
		if ( '' !== $e['venue'] || '' !== $address ) {
			$html .= '<p class="ctt-single__where">'
				. ( '' !== $e['venue'] ? '<span class="ctt-single__venue">' . esc_html( $e['venue'] ) . '</span>' : '' )
				. ( '' !== $address ? '<span class="ctt-single__address">' . esc_html( $address ) . '</span>' : '' )
				. '</p>';
		}

		$badge = Clesk_Termine_Render::badge_html( $e );
		if ( '' !== $badge || '' !== $e['note'] ) {
			$html .= '<p class="ctt-single__status">' . $badge
				. ( '' !== $e['note'] ? '<span class="ctt-single__note">' . esc_html( $e['note'] ) . '</span>' : '' ) . '</p>';
		}
		if ( $e['is_past'] ) {
			$html .= '<p class="ctt-single__hint">' . esc_html__( 'Dieser Termin hat bereits stattgefunden.', 'clesk-termine' ) . '</p>';
		}

		$actions = '';
		if ( $e['has_date'] ) {
			$actions .= Clesk_Termine_Render::ticket_html( $e, __( 'Tickets', 'clesk-termine' ), 'ctt-ticket ctt-ticket--large' );
		}
		$actions .= Clesk_Termine_Render::map_link_html( $e, 'ctt-btn', true );
		$actions .= Clesk_Termine_Render::info_link_html( $e, 'ctt-btn', true );
		if ( $e['has_date'] ) {
			$actions .= Clesk_Termine_Render::share_link_html( $e, 'ctt-btn', true, '#teilen' );
		}
		if ( '' !== $actions ) {
			$html .= '<div class="ctt-single__actions">' . $actions . '</div>';
		}

		return $html . '</div></div>';
	}

	/**
	 * Bild und Beschreibung.
	 *
	 * @param array  $e       Termin.
	 * @param string $content Beschreibung (bereits formatiert).
	 * @return string
	 */
	private static function body_html( array $e, $content ) {
		$image = '';
		if ( $e['image_id'] ) {
			$alt   = trim( (string) get_post_meta( $e['image_id'], '_wp_attachment_image_alt', true ) );
			$image = wp_get_attachment_image(
				$e['image_id'],
				'large',
				false,
				array(
					'class' => 'ctt-single__img',
					'alt'   => '' !== $alt ? $alt : $e['title'],
					'sizes' => '(max-width: 720px) 100vw, 460px',
				)
			);
		}
		$text = trim( $content );
		if ( '' === $image && '' === $text ) {
			return '';
		}

		$html = '<div class="ctt-single__body' . ( '' !== $image && '' !== $text ? ' ctt-single__body--split' : '' ) . '">';
		if ( '' !== $image ) {
			$html .= '<figure class="ctt-single__media">' . $image . '</figure>';
		}
		if ( '' !== $text ) {
			$html .= '<div class="ctt-single__text">' . $text . '</div>';
		}
		return $html . '</div>';
	}

	/**
	 * Abschnitt „Termin teilen“: reine Links, keine eingebundenen Dienste.
	 *
	 * @param array $e Termin.
	 * @return string
	 */
	private static function share_html( array $e ) {
		$share    = Clesk_Termine_Render::share_data( $e );
		$url      = $e['url'];
		$new_tab  = '<span class="ctt-sr"> ' . esc_html__( '(öffnet neues Fenster)', 'clesk-termine' ) . '</span>';
		$whatsapp = 'https://wa.me/?text=' . rawurlencode( $share['text'] . ' ' . $url );
		$facebook = 'https://www.facebook.com/sharer/sharer.php?u=' . rawurlencode( $url );
		$mail     = 'mailto:?subject=' . rawurlencode( $share['title'] ) . '&body=' . rawurlencode( $share['text'] . "\n\n" . $url );

		return '<section class="ctt-share" id="teilen" aria-labelledby="ctt-share-title">'
			. '<h2 class="ctt-share__title" id="ctt-share-title">' . esc_html__( 'Termin teilen', 'clesk-termine' ) . '</h2>'
			. '<div class="ctt-share__links">'
			. '<a class="ctt-btn" href="' . esc_url( $whatsapp ) . '" target="_blank" rel="noopener">WhatsApp' . $new_tab . '</a>'
			. '<a class="ctt-btn" href="' . esc_url( $facebook ) . '" target="_blank" rel="noopener">Facebook' . $new_tab . '</a>'
			. '<a class="ctt-btn" href="' . esc_url( $mail ) . '">' . Clesk_Termine_Render::icon( 'mail' ) . '<span>' . esc_html__( 'E-Mail', 'clesk-termine' ) . '</span></a>'
			. '<button type="button" class="ctt-btn ctt-share__copy" data-ctt-copy="' . esc_url( $url ) . '" data-ctt-copied="' . esc_attr__( 'Link kopiert', 'clesk-termine' ) . '" hidden>'
			. Clesk_Termine_Render::icon( 'copy' ) . '<span>' . esc_html__( 'Link kopieren', 'clesk-termine' ) . '</span></button>'
			. '</div>'
			. '<p class="ctt-share__status ctt-sr" role="status"></p>'
			. '</section>';
	}

	/**
	 * Weitere kommende Termine desselben Programms.
	 *
	 * @param array $e Termin.
	 * @return string
	 */
	private static function more_html( array $e ) {
		if ( '' === $e['title'] ) {
			return '';
		}
		$atts = array(
			'design'       => 'liste',
			'filterCity'   => false,
			'filterVenue'  => false,
			'filterMonth'  => false,
			'groupByMonth' => false,
			'range'        => 'upcoming',
			'program'      => $e['title'],
			'exclude'      => $e['id'],
			'limit'        => 6,
		);
		$more = Clesk_Termine_Events::query(
			array(
				'range'   => 'upcoming',
				'program' => $e['title'],
				'exclude' => $e['id'],
				'limit'   => 1,
			)
		);
		if ( ! $more ) {
			return '';
		}

		/* translators: %s: Programm. */
		$heading = sprintf( __( 'Weitere Termine: %s', 'clesk-termine' ), $e['title'] );
		return '<section class="ctt-single__more"><h2 class="ctt-single__more-title">' . esc_html( $heading ) . '</h2>'
			. Clesk_Termine_Render::render( $atts, false ) . '</section>';
	}
}
