<?php
/**
 * Block „Termine“ und Shortcode [termine].
 *
 * @package Clesk_Termine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registriert Block und Shortcode.
 */
final class Clesk_Termine_Block {

	const NAME = 'clesk/termine';

	/**
	 * Hooks registrieren.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'editor_data' ) );
	}

	/**
	 * Block aus block.json und Shortcodes registrieren.
	 */
	public static function register() {
		$args = array(
			'render_callback' => array( __CLASS__, 'render_block' ),
		);
		// Der HTML-Anker dynamischer Blöcke funktioniert erst ab WordPress 7.0. Davor ausblenden, statt ein Feld ohne Wirkung zu zeigen.
		if ( ! function_exists( 'wp_register_anchor_support' ) ) {
			$args['supports'] = array(
				'html'   => false,
				'anchor' => false,
				'align'  => array( 'wide', 'full' ),
			);
		}
		register_block_type( CLESK_TERMINE_DIR . 'blocks/termine', $args );

		add_shortcode( 'clesk_termine', array( __CLASS__, 'shortcode' ) );
		if ( ! shortcode_exists( 'termine' ) ) {
			add_shortcode( 'termine', array( __CLASS__, 'shortcode' ) );
		}
	}

	/**
	 * Render-Callback des Blocks.
	 *
	 * @param array $attributes Block-Attribute.
	 * @return string
	 */
	public static function render_block( $attributes ) {
		return Clesk_Termine_Render::render( (array) $attributes, true );
	}

	/**
	 * Programmliste und Links für die Block-Seitenleiste.
	 */
	public static function editor_data() {
		$data = array(
			'programs'  => Clesk_Termine_Events::distinct_titles( true ),
			'singlePages' => Clesk_Termine_Events::has_single_pages(),
			'manageUrl' => admin_url( 'edit.php?post_type=' . Clesk_Termine_Events::POST_TYPE ),
			'newUrl'    => admin_url( 'post-new.php?post_type=' . Clesk_Termine_Events::POST_TYPE ),
		);
		wp_add_inline_script(
			generate_block_asset_handle( self::NAME, 'editorScript' ),
			'window.cleskTermine = ' . wp_json_encode( $data ) . ';',
			'before'
		);
	}

	/**
	 * Shortcode, z. B. [termine design="liste" filter="stadt,monat" zeitraum="kommende"].
	 *
	 * @param array|string $atts Shortcode-Attribute.
	 * @return string
	 */
	public static function shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'design'      => 'karten',
				'filter'      => 'stadt,location,monat',
				'monate'      => 'ja',
				'bilder'      => 'ja',
				'karte'       => 'ja',
				'details'     => 'ja',
				'teilen'      => 'ja',
				'zeitraum'    => 'kommende',
				'programm'    => '',
				'anzahl'      => 0,
				'ticket_text' => '',
				'leer_text'   => '',
			),
			$atts,
			'termine'
		);

		$filters = array_map( 'trim', explode( ',', strtolower( (string) $atts['filter'] ) ) );
		$ranges  = array(
			'kommende'   => 'upcoming',
			'vergangene' => 'past',
			'alle'       => 'all',
		);
		$range   = strtolower( (string) $atts['zeitraum'] );

		$block_atts = array(
			'design'       => strtolower( (string) $atts['design'] ),
			'filterCity'   => in_array( 'stadt', $filters, true ),
			'filterVenue'  => in_array( 'location', $filters, true ),
			'filterMonth'  => in_array( 'monat', $filters, true ),
			'groupByMonth' => self::truthy( $atts['monate'] ),
			'showImages'   => self::truthy( $atts['bilder'] ),
			'showMap'      => self::truthy( $atts['karte'] ),
			'linkDetails'  => self::truthy( $atts['details'] ),
			'showShare'    => self::truthy( $atts['teilen'] ),
			'range'        => isset( $ranges[ $range ] ) ? $ranges[ $range ] : 'upcoming',
			'program'      => (string) $atts['programm'],
			'limit'        => (int) $atts['anzahl'],
		);
		if ( '' !== (string) $atts['ticket_text'] ) {
			$block_atts['ticketLabel'] = (string) $atts['ticket_text'];
		}
		if ( '' !== (string) $atts['leer_text'] ) {
			$block_atts['emptyText'] = (string) $atts['leer_text'];
		}

		wp_enqueue_style( generate_block_asset_handle( self::NAME, 'style' ) );
		wp_enqueue_script( generate_block_asset_handle( self::NAME, 'viewScript' ) );

		return Clesk_Termine_Render::render( $block_atts, false );
	}

	/**
	 * Ja/Nein-Werte aus Shortcodes lesen.
	 *
	 * @param mixed $value Wert.
	 * @return bool
	 */
	private static function truthy( $value ) {
		return in_array( strtolower( trim( (string) $value ) ), array( '1', 'ja', 'true', 'yes', 'an', 'on' ), true );
	}
}
