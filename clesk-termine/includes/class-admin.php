<?php
/**
 * Verwaltung: Eingabeformular, Terminliste, Duplizieren.
 *
 * @package Clesk_Termine
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin-Oberfläche für Termine.
 */
final class Clesk_Termine_Admin {

	const DUPLICATE_ACTION = 'clesk_termin_duplicate';

	/**
	 * Eigener Bereich für die Termindetails: direkt unter dem Titel, über der Beschreibung.
	 */
	const BOX_CONTEXT = 'ctt_after_title';

	/**
	 * Hooks registrieren.
	 */
	public static function init() {
		$pt = Clesk_Termine_Events::POST_TYPE;

		add_action( 'add_meta_boxes_' . $pt, array( __CLASS__, 'add_meta_box' ) );
		add_action( 'edit_form_after_title', array( __CLASS__, 'render_after_title' ) );
		add_action( 'save_post_' . $pt, array( __CLASS__, 'save' ), 10, 2 );
		add_filter( 'enter_title_here', array( __CLASS__, 'title_placeholder' ), 10, 2 );
		add_filter( 'post_updated_messages', array( __CLASS__, 'updated_messages' ) );

		add_filter( 'manage_' . $pt . '_posts_columns', array( __CLASS__, 'columns' ) );
		add_action( 'manage_' . $pt . '_posts_custom_column', array( __CLASS__, 'column_content' ), 10, 2 );
		add_filter( 'manage_edit-' . $pt . '_sortable_columns', array( __CLASS__, 'sortable_columns' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'list_query' ) );
		add_filter( 'views_edit-' . $pt, array( __CLASS__, 'views' ) );
		add_filter( 'bulk_post_updated_messages', array( __CLASS__, 'bulk_messages' ), 10, 2 );
		add_filter( 'disable_months_dropdown', array( __CLASS__, 'disable_months_dropdown' ), 10, 2 );
		add_action( 'manage_posts_extra_tablenav', array( __CLASS__, 'range_field' ) );

		add_filter( 'post_row_actions', array( __CLASS__, 'row_actions' ), 10, 2 );
		add_action( 'admin_action_' . self::DUPLICATE_ACTION, array( __CLASS__, 'duplicate' ) );
		add_action( 'post_submitbox_misc_actions', array( __CLASS__, 'submitbox_duplicate' ) );
		add_action( 'admin_notices', array( __CLASS__, 'notices' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
	}

	/**
	 * Formular „Termindetails“ registrieren.
	 */
	public static function add_meta_box() {
		add_meta_box(
			'ctt-details',
			__( 'Termindetails', 'clesk-termine' ),
			array( __CLASS__, 'render_meta_box' ),
			Clesk_Termine_Events::POST_TYPE,
			self::BOX_CONTEXT,
			'high'
		);
	}

	/**
	 * Termindetails direkt unter dem Titel ausgeben, darunter die Überschrift für die Beschreibung.
	 *
	 * @param WP_Post $post Beitrag.
	 */
	public static function render_after_title( $post ) {
		if ( Clesk_Termine_Events::POST_TYPE !== $post->post_type ) {
			return;
		}
		do_meta_boxes( get_current_screen(), self::BOX_CONTEXT, $post );

		if ( post_type_supports( $post->post_type, 'editor' ) ) {
			echo '<div class="ctt-admin-description"><h2>' . esc_html__( 'Beschreibung (optional)', 'clesk-termine' ) . '</h2><p class="description">'
				. esc_html__( 'Erscheint auf der eigenen Seite des Termins unter den Eckdaten, zum Beispiel ein kurzer Text zum Programm.', 'clesk-termine' ) . '</p></div>';
		}
	}

	/**
	 * Formular ausgeben.
	 *
	 * @param WP_Post $post Termin.
	 */
	public static function render_meta_box( $post ) {
		$f    = Clesk_Termine_Events::get_fields( $post->ID );
		$past = '' !== $f['date'] && $f['date'] < Clesk_Termine_Events::today();
		// Die optionalen Angaben bleiben eingeklappt, solange keine davon gefüllt ist.
		$more_open = '' !== $f['note'] || '' !== $f['street'] || '' !== $f['zip'] || '' !== $f['info_url'];

		wp_nonce_field( 'ctt_save_' . $post->ID, 'ctt_nonce' );
		?>
		<div class="ctt-admin">
			<div class="ctt-admin__grid">
				<p class="ctt-admin__field">
					<label for="ctt-date"><?php esc_html_e( 'Datum', 'clesk-termine' ); ?> <span class="ctt-admin__required" aria-hidden="true">*</span></label>
					<input type="date" id="ctt-date" name="ctt[date]" value="<?php echo esc_attr( $f['date'] ); ?>" min="2000-01-01" max="2099-12-31" aria-required="true" aria-describedby="ctt-date-error">
					<span id="ctt-date-error" class="ctt-admin__help ctt-admin__help--warn" role="alert" hidden><?php esc_html_e( 'Bitte ein Datum eintragen. Ohne Datum erscheint der Termin nicht auf der Website.', 'clesk-termine' ); ?></span>
					<?php if ( $past ) : ?>
						<span class="ctt-admin__help ctt-admin__help--warn"><?php esc_html_e( 'Liegt in der Vergangenheit: erscheint nur in Listen mit dem Zeitraum „Vergangene“ oder „Alle“.', 'clesk-termine' ); ?></span>
					<?php endif; ?>
				</p>
				<p class="ctt-admin__field">
					<label for="ctt-time"><?php esc_html_e( 'Uhrzeit (Beginn)', 'clesk-termine' ); ?></label>
					<input type="time" id="ctt-time" name="ctt[time]" value="<?php echo esc_attr( $f['time'] ); ?>">
				</p>
				<p class="ctt-admin__field">
					<label for="ctt-city"><?php esc_html_e( 'Stadt', 'clesk-termine' ); ?></label>
					<input type="text" id="ctt-city" name="ctt[city]" value="<?php echo esc_attr( $f['city'] ); ?>" list="ctt-cities" autocomplete="off" placeholder="<?php esc_attr_e( 'z. B. Köln', 'clesk-termine' ); ?>">
				</p>
				<p class="ctt-admin__field">
					<label for="ctt-venue"><?php esc_html_e( 'Location (Veranstaltungsort)', 'clesk-termine' ); ?></label>
					<input type="text" id="ctt-venue" name="ctt[venue]" value="<?php echo esc_attr( $f['venue'] ); ?>" list="ctt-venues" autocomplete="off" placeholder="<?php esc_attr_e( 'z. B. Stadthalle', 'clesk-termine' ); ?>">
				</p>
				<p class="ctt-admin__field ctt-admin__field--wide">
					<label for="ctt-ticket"><?php esc_html_e( 'Ticket-Link', 'clesk-termine' ); ?></label>
					<input type="text" inputmode="url" id="ctt-ticket" name="ctt[ticket_url]" value="<?php echo esc_attr( $f['ticket_url'] ); ?>" placeholder="https://" autocomplete="off" spellcheck="false">
					<span class="ctt-admin__help"><?php esc_html_e( 'Adresse der Ticketseite, z. B. bei Eventbrite oder Eventim. Leer lassen, wenn es keinen Vorverkauf gibt. Bei „Ausverkauft“, „Abgesagt“ und „Geschlossene Veranstaltung“ wird kein Ticket-Button angezeigt.', 'clesk-termine' ); ?></span>
				</p>
				<p class="ctt-admin__field">
					<label for="ctt-status"><?php esc_html_e( 'Status', 'clesk-termine' ); ?></label>
					<select id="ctt-status" name="ctt[status]">
						<?php foreach ( Clesk_Termine_Events::statuses() as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $f['status'], $value ); ?>><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
			</div>

			<details class="ctt-admin__more"<?php echo $more_open ? ' open' : ''; ?>>
				<summary><?php esc_html_e( 'Weitere Angaben (optional): Hinweis, Anschrift, Link „Mehr Infos“', 'clesk-termine' ); ?></summary>
				<div class="ctt-admin__grid">
					<p class="ctt-admin__field ctt-admin__field--wide">
						<label for="ctt-note"><?php esc_html_e( 'Hinweis', 'clesk-termine' ); ?></label>
						<input type="text" id="ctt-note" name="ctt[note]" value="<?php echo esc_attr( $f['note'] ); ?>" maxlength="120" placeholder="<?php esc_attr_e( 'z. B. Verlegt vom 07.11.2026', 'clesk-termine' ); ?>">
					</p>
					<p class="ctt-admin__field">
						<label for="ctt-street"><?php esc_html_e( 'Straße und Hausnummer der Location', 'clesk-termine' ); ?></label>
						<input type="text" id="ctt-street" name="ctt[street]" value="<?php echo esc_attr( $f['street'] ); ?>" autocomplete="off" placeholder="<?php esc_attr_e( 'z. B. Musterstraße 1', 'clesk-termine' ); ?>">
						<span class="ctt-admin__help"><?php esc_html_e( 'Nur wenn gewünscht: macht Kartenlink und Terminseite genauer. Bei bekannten Locations wird die Anschrift ergänzt.', 'clesk-termine' ); ?></span>
					</p>
					<p class="ctt-admin__field">
						<label for="ctt-zip"><?php esc_html_e( 'PLZ', 'clesk-termine' ); ?></label>
						<input type="text" id="ctt-zip" name="ctt[zip]" value="<?php echo esc_attr( $f['zip'] ); ?>" autocomplete="off" maxlength="12" inputmode="numeric" placeholder="<?php esc_attr_e( 'z. B. 12345', 'clesk-termine' ); ?>">
					</p>
					<p class="ctt-admin__field ctt-admin__field--wide">
						<label for="ctt-info"><?php esc_html_e( 'Link „Mehr Infos“', 'clesk-termine' ); ?></label>
						<input type="text" inputmode="url" id="ctt-info" name="ctt[info_url]" value="<?php echo esc_attr( $f['info_url'] ); ?>" placeholder="<?php esc_attr_e( 'https://… oder /seite/', 'clesk-termine' ); ?>" autocomplete="off" spellcheck="false">
						<span class="ctt-admin__help"><?php esc_html_e( 'Zum Beispiel zur Programmseite oder zur Website der Location. Erscheint als Button „Mehr Infos“ auf der Terminseite.', 'clesk-termine' ); ?></span>
					</p>
				</div>
			</details>

			<p class="ctt-admin__tip"><?php esc_html_e( 'Tipp: Im Titelfeld steht das Programm oder die Veranstaltung. Für weitere Termine desselben Programms in der Terminliste auf „Duplizieren“ klicken und nur Datum, Stadt und Location ändern. Die Adresse der Terminseite entsteht automatisch aus Programm, Stadt und Datum.', 'clesk-termine' ); ?></p>

			<datalist id="ctt-cities"><?php self::datalist_options( Clesk_Termine_Events::distinct_values( 'city' ) ); ?></datalist>
			<datalist id="ctt-venues"><?php self::datalist_options( Clesk_Termine_Events::distinct_values( 'venue' ) ); ?></datalist>
			<datalist id="ctt-programs"><?php self::datalist_options( Clesk_Termine_Events::distinct_titles() ); ?></datalist>
		</div>
		<?php
	}

	/**
	 * Optionen einer Datalist ausgeben.
	 *
	 * @param string[] $values Werte.
	 */
	private static function datalist_options( array $values ) {
		foreach ( $values as $value ) {
			echo '<option value="' . esc_attr( $value ) . '"></option>';
		}
	}

	/**
	 * Formular speichern.
	 *
	 * @param int     $post_id Beitrags-ID.
	 * @param WP_Post $post    Termin.
	 */
	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST['ctt_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['ctt_nonce'] ) ), 'ctt_save_' . $post_id ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$input = ( isset( $_POST['ctt'] ) && is_array( $_POST['ctt'] ) ) ? wp_unslash( $_POST['ctt'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- einzeln bereinigt.

		foreach ( Clesk_Termine_Events::FIELDS as $field => $meta_key ) {
			$value = isset( $input[ $field ] ) && is_scalar( $input[ $field ] ) ? (string) $input[ $field ] : '';
			// Bereinigt wird über den sanitize_callback aus register_post_meta().
			update_post_meta( $post_id, $meta_key, wp_slash( $value ) );
		}
		Clesk_Termine_Events::sync_start( $post_id );
	}

	/**
	 * Platzhalter im Titelfeld.
	 *
	 * @param string  $text Standardtext.
	 * @param WP_Post $post Beitrag.
	 * @return string
	 */
	public static function title_placeholder( $text, $post ) {
		if ( $post && Clesk_Termine_Events::POST_TYPE === $post->post_type ) {
			return __( 'Programm oder Veranstaltung, z. B. Poetry Slam', 'clesk-termine' );
		}
		return $text;
	}

	/**
	 * Meldungen nach dem Speichern, mit Link zur Terminseite, wenn es eine gibt.
	 *
	 * @param array $messages Meldungen.
	 * @return array
	 */
	public static function updated_messages( $messages ) {
		$post  = get_post();
		$saved = __( 'Termin gespeichert.', 'clesk-termine' );
		$view  = '';
		$peek  = '';
		if ( $post && Clesk_Termine_Events::POST_TYPE === $post->post_type && is_post_type_viewable( $post->post_type ) ) {
			$view = ' <a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html__( 'Termin ansehen', 'clesk-termine' ) . '</a>';
			$peek = ' <a href="' . esc_url( get_preview_post_link( $post ) ) . '" target="_blank">' . esc_html__( 'Vorschau ansehen', 'clesk-termine' ) . '</a>';
		}

		$messages[ Clesk_Termine_Events::POST_TYPE ] = array(
			0  => '',
			1  => __( 'Termin aktualisiert.', 'clesk-termine' ) . $view,
			2  => $saved,
			3  => $saved,
			4  => __( 'Termin aktualisiert.', 'clesk-termine' ),
			5  => '',
			6  => __( 'Termin veröffentlicht.', 'clesk-termine' ) . $view,
			7  => $saved,
			8  => __( 'Termin zur Prüfung eingereicht.', 'clesk-termine' ) . $peek,
			9  => __( 'Termin geplant.', 'clesk-termine' ) . $peek,
			10 => __( 'Entwurf gespeichert.', 'clesk-termine' ) . $peek,
		);
		return $messages;
	}

	/**
	 * Spalten der Terminliste.
	 *
	 * @param array $columns Spalten.
	 * @return array
	 */
	public static function columns( $columns ) {
		return array(
			'cb'         => isset( $columns['cb'] ) ? $columns['cb'] : '<input type="checkbox" />',
			'ctt_date'   => __( 'Datum', 'clesk-termine' ),
			'title'      => __( 'Programm', 'clesk-termine' ),
			'ctt_city'   => __( 'Stadt', 'clesk-termine' ),
			'ctt_venue'  => __( 'Location', 'clesk-termine' ),
			'ctt_status' => __( 'Status', 'clesk-termine' ),
			'ctt_ticket' => __( 'Tickets', 'clesk-termine' ),
		);
	}

	/**
	 * Spalteninhalt.
	 *
	 * @param string $column  Spalte.
	 * @param int    $post_id Beitrags-ID.
	 */
	public static function column_content( $column, $post_id ) {
		$f = Clesk_Termine_Events::get_fields( $post_id );

		switch ( $column ) {
			case 'ctt_date':
				$start = Clesk_Termine_Events::start( $f['date'], $f['time'] );
				if ( ! $start ) {
					echo '<span class="ctt-admin-missing">' . esc_html__( 'Kein Datum', 'clesk-termine' ) . '</span>';
					break;
				}
				$past = $f['date'] < Clesk_Termine_Events::today();
				$ts = $start->getTimestamp();
				echo '<span class="' . ( $past ? 'ctt-admin-past' : 'ctt-admin-date' ) . '">' . esc_html( rtrim( wp_date( 'D', $ts ), '.' ) . ', ' . wp_date( 'd.m.Y', $ts ) );
				if ( '' !== $f['time'] ) {
					echo '<br><span class="ctt-admin-time">' . esc_html( $f['time'] ) . ' ' . esc_html__( 'Uhr', 'clesk-termine' ) . '</span>';
				}
				echo '</span>';
				break;

			case 'ctt_city':
				echo '' !== $f['city'] ? esc_html( $f['city'] ) : '<span aria-hidden="true">–</span>';
				break;

			case 'ctt_venue':
				echo '' !== $f['venue'] ? esc_html( $f['venue'] ) : '<span aria-hidden="true">–</span>';
				break;

			case 'ctt_status':
				$label = Clesk_Termine_Events::status_label( $f['status'] );
				echo '' !== $label
					? '<span class="ctt-admin-badge ctt-admin-badge--' . esc_attr( $f['status'] ) . '">' . esc_html( $label ) . '</span>'
					: '<span aria-hidden="true">–</span>';
				break;

			case 'ctt_ticket':
				if ( '' !== $f['ticket_url'] ) {
					echo '<a href="' . esc_url( $f['ticket_url'] ) . '" target="_blank" rel="noopener">' . esc_html__( 'Link', 'clesk-termine' ) . '<span class="screen-reader-text"> ' . esc_html__( '(öffnet neues Fenster)', 'clesk-termine' ) . '</span></a>';
				} else {
					echo '<span aria-hidden="true">–</span>';
				}
				break;
		}
	}

	/**
	 * Sortierbare Spalten.
	 *
	 * @param array $columns Spalten.
	 * @return array
	 */
	public static function sortable_columns( $columns ) {
		$columns['ctt_date'] = 'ctt_date';
		$columns['ctt_city'] = 'ctt_city';
		return $columns;
	}

	/**
	 * Gewählter Zeitraum der Terminliste.
	 * Standard sind die kommenden Termine. Die Suche und Ansichten wie „Entwürfe“, „Papierkorb“ oder „Meine“ zeigen alles.
	 *
	 * @return string kommende|vergangene|alle
	 */
	private static function current_range() {
		// Die Suche durchsucht immer alle Zeiträume, auch wenn das Listenformular einen Zeitraum mitschickt.
		if ( self::is_searching() ) {
			return 'alle';
		}
		$range = self::requested_range();
		if ( '' !== $range ) {
			return $range;
		}
		return ( self::is_status_view() || self::is_author_view() ) ? 'alle' : 'kommende';
	}

	/**
	 * Ausdrücklich über die Adresse gewählter Zeitraum, sonst leer.
	 *
	 * @return string
	 */
	private static function requested_range() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- reine Anzeigefilter.
		$range = isset( $_GET['ctt_zeitraum'] ) && is_string( $_GET['ctt_zeitraum'] ) ? sanitize_key( wp_unslash( $_GET['ctt_zeitraum'] ) ) : '';
		// phpcs:enable
		return in_array( $range, array( 'kommende', 'vergangene', 'alle' ), true ) ? $range : '';
	}

	/**
	 * Ist die Liste auf einen Autor beschränkt? Das ist die Ansicht „Meine“. WordPress wählt sie von selbst
	 * für Benutzer, die fremde Termine nicht bearbeiten dürfen.
	 *
	 * @return bool
	 */
	private static function is_author_view() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reine Anzeigefilter.
		return ! empty( $_GET['author'] );
	}

	/**
	 * Gewählten Zeitraum im Listenformular mitführen, damit er beim Absenden erhalten bleibt,
	 * etwa wenn jemand eine Seitenzahl eintippt und Enter drückt.
	 *
	 * @param string $which Position der Leiste: top oder bottom.
	 */
	public static function range_field( $which ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( 'top' !== $which || ! $screen || 'edit' !== $screen->base || Clesk_Termine_Events::POST_TYPE !== $screen->post_type ) {
			return;
		}
		$range = self::requested_range();
		if ( '' !== $range ) {
			echo '<input type="hidden" name="ctt_zeitraum" value="' . esc_attr( $range ) . '" />';
		}
	}

	/**
	 * Läuft eine Suche in der Terminliste?
	 *
	 * @return bool
	 */
	private static function is_searching() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reine Anzeigefilter.
		return isset( $_GET['s'] ) && is_string( $_GET['s'] ) && '' !== trim( wp_unslash( $_GET['s'] ) );
	}

	/**
	 * Ist eine Status-Ansicht wie „Entwürfe“ oder „Papierkorb“ gewählt?
	 *
	 * @return bool
	 */
	private static function is_status_view() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reine Anzeigefilter.
		$status = isset( $_GET['post_status'] ) && is_string( $_GET['post_status'] ) ? sanitize_key( wp_unslash( $_GET['post_status'] ) ) : '';
		return '' !== $status && 'all' !== $status;
	}

	/**
	 * Meta-Abfrage für „kommende“ Termine: ab heute, dazu Termine ohne Datum (z. B. neue Entwürfe),
	 * die sonst nirgends zu sehen wären.
	 *
	 * @return array
	 */
	private static function upcoming_meta_query() {
		return array(
			'relation' => 'OR',
			array(
				'key'     => Clesk_Termine_Events::FIELDS['date'],
				'value'   => Clesk_Termine_Events::today(),
				'compare' => '>=',
				'type'    => 'DATE',
			),
			array(
				'key'     => Clesk_Termine_Events::FIELDS['date'],
				'value'   => '',
				'compare' => '=',
			),
			array(
				'key'     => Clesk_Termine_Events::FIELDS['date'],
				'compare' => 'NOT EXISTS',
			),
		);
	}

	/**
	 * Meta-Abfrage für vergangene Termine.
	 *
	 * @return array
	 */
	private static function past_meta_query() {
		return array(
			'key'     => Clesk_Termine_Events::FIELDS['date'],
			'value'   => Clesk_Termine_Events::today(),
			'compare' => '<',
			'type'    => 'DATE',
		);
	}

	/**
	 * Anzahl der Termine je Zeitraum (ohne Papierkorb), für die Ansichten über der Liste.
	 *
	 * @return array{kommende:int,vergangene:int,alle:int}
	 */
	private static function range_counts() {
		$counts = array();
		$ranges = array(
			'kommende'   => array( self::upcoming_meta_query() ),
			'vergangene' => array( self::past_meta_query() ),
			'alle'       => array(),
		);
		foreach ( $ranges as $range => $meta_query ) {
			$args = array(
				'post_type'      => Clesk_Termine_Events::POST_TYPE,
				'post_status'    => array( 'publish', 'future', 'draft', 'pending', 'private' ),
				'perm'           => 'readable',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			);
			if ( $meta_query ) {
				$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			}
			$query            = new WP_Query( $args );
			$counts[ $range ] = (int) $query->found_posts;
		}
		return $counts;
	}

	/**
	 * Ansichten über der Liste: „Kommende | Vergangene | Alle“ vor den Status-Ansichten von WordPress.
	 * Als Links bleiben sie auch auf schmalen Bildschirmen erreichbar.
	 *
	 * @param string[] $views Ansichten.
	 * @return string[]
	 */
	public static function views( $views ) {
		$base = admin_url( 'edit.php?post_type=' . Clesk_Termine_Events::POST_TYPE );
		$type = get_post_type_object( Clesk_Termine_Events::POST_TYPE );
		if ( $type && ! current_user_can( $type->cap->edit_others_posts ) ) {
			// Ohne diesen Parameter schaltet WordPress solche Benutzer bei jedem Klick auf „Meine“ zurück.
			$base = add_query_arg( 'all_posts', '1', $base );
		}
		$range  = self::current_range();
		$counts = self::range_counts();
		$plain  = ! self::is_searching() && ! self::is_status_view() && ! self::is_author_view();
		$labels = array(
			'kommende'   => __( 'Kommende', 'clesk-termine' ),
			'vergangene' => __( 'Vergangene', 'clesk-termine' ),
			'alle'       => __( 'Alle', 'clesk-termine' ),
		);

		$range_views = array();
		foreach ( $labels as $key => $label ) {
			$url     = 'kommende' === $key ? $base : add_query_arg( 'ctt_zeitraum', $key, $base );
			$current = $plain && $key === $range;

			$range_views[ 'alle' === $key ? 'all' : 'ctt_' . $key ] = sprintf(
				'<a href="%1$s"%2$s>%3$s <span class="count">(%4$s)</span></a>',
				esc_url( $url ),
				$current ? ' class="current" aria-current="page"' : '',
				esc_html( $label ),
				esc_html( number_format_i18n( $counts[ $key ] ) )
			);
		}

		unset( $views['all'] );
		return array_merge( $range_views, $views );
	}

	/**
	 * Sammelmeldungen (Papierkorb, Wiederherstellen, Mehrfachbearbeitung) mit „Termin“ statt „Beitrag“.
	 *
	 * @param array[] $messages Meldungen je Beitragstyp.
	 * @param int[]   $counts   Anzahl je Meldung.
	 * @return array[]
	 */
	public static function bulk_messages( $messages, $counts ) {
		$one = static function ( $key ) use ( $counts ) {
			return isset( $counts[ $key ] ) && 1 === (int) $counts[ $key ];
		};

		$messages[ Clesk_Termine_Events::POST_TYPE ] = array(
			/* translators: %s: Anzahl. */
			'updated'   => $one( 'updated' ) ? __( '%s Termin aktualisiert.', 'clesk-termine' ) : __( '%s Termine aktualisiert.', 'clesk-termine' ),
			/* translators: %s: Anzahl. */
			'locked'    => $one( 'locked' ) ? __( '%s Termin nicht aktualisiert, er wird gerade von jemand anderem bearbeitet.', 'clesk-termine' ) : __( '%s Termine nicht aktualisiert, sie werden gerade von jemand anderem bearbeitet.', 'clesk-termine' ),
			/* translators: %s: Anzahl. */
			'deleted'   => $one( 'deleted' ) ? __( '%s Termin endgültig gelöscht.', 'clesk-termine' ) : __( '%s Termine endgültig gelöscht.', 'clesk-termine' ),
			/* translators: %s: Anzahl. */
			'trashed'   => $one( 'trashed' ) ? __( '%s Termin in den Papierkorb verschoben.', 'clesk-termine' ) : __( '%s Termine in den Papierkorb verschoben.', 'clesk-termine' ),
			/* translators: %s: Anzahl. */
			'untrashed' => $one( 'untrashed' ) ? __( '%s Termin aus dem Papierkorb wiederhergestellt.', 'clesk-termine' ) : __( '%s Termine aus dem Papierkorb wiederhergestellt.', 'clesk-termine' ),
		);
		return $messages;
	}

	/**
	 * Terminliste: Zeitraum filtern, nach Termindatum sortieren.
	 *
	 * @param WP_Query $query Abfrage.
	 */
	public static function list_query( $query ) {
		global $pagenow;
		if ( ! is_admin() || ! $query->is_main_query() || 'edit.php' !== $pagenow || Clesk_Termine_Events::POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		$range      = self::current_range();
		$meta_query = array( 'relation' => 'AND' );
		if ( 'kommende' === $range ) {
			$meta_query[] = self::upcoming_meta_query();
		} elseif ( 'vergangene' === $range ) {
			$meta_query[] = self::past_meta_query();
		}

		$orderby = (string) $query->get( 'orderby' );
		$order   = strtoupper( (string) $query->get( 'order' ) );
		if ( ! in_array( $order, array( 'ASC', 'DESC' ), true ) ) {
			$order = 'kommende' === $range ? 'ASC' : 'DESC';
		}

		if ( '' === $orderby || 'ctt_date' === $orderby || 'ctt_city' === $orderby ) {
			$key          = 'ctt_city' === $orderby ? Clesk_Termine_Events::FIELDS['city'] : Clesk_Termine_Events::META_START;
			$meta_query[] = array(
				'relation'     => 'OR',
				'ctt_sort'     => array(
					'key'     => $key,
					'compare' => 'EXISTS',
				),
				'ctt_sort_not' => array(
					'key'     => $key,
					'compare' => 'NOT EXISTS',
				),
			);
			$query->set(
				'orderby',
				array(
					'ctt_sort' => $order,
					'ID'       => $order,
				)
			);
		}

		if ( count( $meta_query ) > 1 ) {
			$query->set( 'meta_query', $meta_query );
		}
	}

	/**
	 * Der Monatsfilter nach Veröffentlichungsdatum wäre bei Terminen irreführend.
	 *
	 * @param bool   $disable   Bisheriger Wert.
	 * @param string $post_type Beitragstyp.
	 * @return bool
	 */
	public static function disable_months_dropdown( $disable, $post_type ) {
		return Clesk_Termine_Events::POST_TYPE === $post_type ? true : $disable;
	}

	/**
	 * Link zum Duplizieren eines Termins.
	 *
	 * @param int $post_id Beitrags-ID.
	 * @return string
	 */
	private static function duplicate_url( $post_id ) {
		return wp_nonce_url(
			admin_url( 'admin.php?action=' . self::DUPLICATE_ACTION . '&post=' . (int) $post_id ),
			self::DUPLICATE_ACTION . '_' . (int) $post_id
		);
	}

	/**
	 * Zeilenaktion „Duplizieren“.
	 *
	 * @param array   $actions Aktionen.
	 * @param WP_Post $post    Beitrag.
	 * @return array
	 */
	public static function row_actions( $actions, $post ) {
		if ( Clesk_Termine_Events::POST_TYPE !== $post->post_type ) {
			return $actions;
		}
		// Die Schnellbearbeitung kennt nur Veröffentlichungsdatum und Beitragsstatus, nicht die Termindetails.
		// Ein dort eingetragenes Datum würde den Termin als „Geplant“ von der Website nehmen, statt ihn zu verschieben.
		unset( $actions['inline hide-if-no-js'] );

		if ( 'trash' === $post->post_status || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}
		$actions['ctt_duplicate'] = '<a href="' . esc_url( self::duplicate_url( $post->ID ) ) . '" aria-label="'
			/* translators: %s: Programm. */
			. esc_attr( sprintf( __( '„%s“ duplizieren', 'clesk-termine' ), $post->post_title ) ) . '">'
			. esc_html__( 'Duplizieren', 'clesk-termine' ) . '</a>';
		return $actions;
	}

	/**
	 * Link „Duplizieren“ im Veröffentlichen-Kasten.
	 *
	 * @param WP_Post $post Beitrag.
	 */
	public static function submitbox_duplicate( $post ) {
		if ( Clesk_Termine_Events::POST_TYPE !== $post->post_type || 'auto-draft' === $post->post_status || ! current_user_can( 'edit_post', $post->ID ) ) {
			return;
		}
		echo '<div class="misc-pub-section ctt-admin-duplicate"><span class="dashicons dashicons-admin-page" aria-hidden="true"></span> <a href="' . esc_url( self::duplicate_url( $post->ID ) ) . '">'
			. esc_html__( 'Als neuen Termin duplizieren', 'clesk-termine' ) . '</a><br><span class="ctt-admin__help">'
			. esc_html__( 'Kopiert den gespeicherten Stand als Entwurf, ohne Status und Hinweis.', 'clesk-termine' ) . '</span></div>';
	}

	/**
	 * Termin als Entwurf kopieren und die Kopie zum Bearbeiten öffnen.
	 */
	public static function duplicate() {
		$post_id = isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Prüfung folgt.
		check_admin_referer( self::DUPLICATE_ACTION . '_' . $post_id );

		$post = get_post( $post_id );
		if ( ! $post || Clesk_Termine_Events::POST_TYPE !== $post->post_type ) {
			wp_die( esc_html__( 'Termin nicht gefunden.', 'clesk-termine' ), 404 );
		}
		$type = get_post_type_object( $post->post_type );
		if ( ! current_user_can( 'edit_post', $post_id ) || ! $type || ! current_user_can( $type->cap->create_posts ) ) {
			wp_die( esc_html__( 'Keine Berechtigung zum Duplizieren.', 'clesk-termine' ), 403 );
		}

		$new_id = wp_insert_post(
			wp_slash(
				array(
					'post_type'   => $post->post_type,
					'post_status' => 'draft',
					'post_title'   => $post->post_title,
					'post_content' => $post->post_content,
					'post_author'  => get_current_user_id(),
				)
			),
			true
		);
		if ( is_wp_error( $new_id ) ) {
			wp_die( esc_html( $new_id->get_error_message() ) );
		}

		foreach ( Clesk_Termine_Events::FIELDS as $field => $meta_key ) {
			// Status und Hinweis („Ausverkauft“, „Verlegt vom …“) gehören zum Ursprungstermin.
			if ( 'status' === $field || 'note' === $field ) {
				continue;
			}
			$value = (string) get_post_meta( $post_id, $meta_key, true );
			if ( '' !== $value ) {
				update_post_meta( $new_id, $meta_key, wp_slash( $value ) );
			}
		}
		Clesk_Termine_Events::sync_start( $new_id );

		$thumbnail = get_post_thumbnail_id( $post_id );
		if ( $thumbnail ) {
			set_post_thumbnail( $new_id, $thumbnail );
		}

		wp_safe_redirect( add_query_arg( 'ctt_kopie', '1', get_edit_post_link( $new_id, 'raw' ) ) );
		exit;
	}

	/**
	 * Hinweis nach dem Duplizieren.
	 */
	public static function notices() {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || Clesk_Termine_Events::POST_TYPE !== $screen->post_type || 'post' !== $screen->base ) {
			return;
		}
		if ( ! empty( $_GET['ctt_kopie'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nur Anzeige.
			echo '<div class="notice notice-success is-dismissible"><p><strong>' . esc_html__( 'Kopie angelegt (Entwurf).', 'clesk-termine' ) . '</strong> '
				. esc_html__( 'Datum, Stadt und Location anpassen, bei Bedarf auch Ticket-Link und Anschrift, dann auf „Veröffentlichen“ klicken. Status und Hinweis wurden nicht übernommen.', 'clesk-termine' ) . '</p></div>';
		}

		$post = get_post();
		if ( ! $post ) {
			return;
		}
		if ( in_array( $post->post_status, array( 'publish', 'future', 'private' ), true )
			&& '' === (string) get_post_meta( $post->ID, Clesk_Termine_Events::FIELDS['date'], true ) ) {
			echo '<div class="notice notice-error"><p><strong>' . esc_html__( 'Dieser Termin hat kein Datum.', 'clesk-termine' ) . '</strong> '
				. esc_html__( 'Er erscheint deshalb nicht in den Terminlisten. Bitte unter „Termindetails“ ein Datum eintragen und speichern.', 'clesk-termine' ) . '</p></div>';
		}
		if ( 'future' === $post->post_status ) {
			/* translators: %s: Datum und Uhrzeit der geplanten Veröffentlichung. */
			$text = sprintf( __( 'Er erscheint erst ab dem %s auf der Website. Das Datum der Veranstaltung steht unter „Termindetails“, das Feld „Veröffentlichen“ rechts steuert nur, ab wann der Termin sichtbar ist.', 'clesk-termine' ), get_the_date( 'd.m.Y, H:i', $post ) . ' ' . __( 'Uhr', 'clesk-termine' ) );
			echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Dieser Termin ist für später geplant.', 'clesk-termine' ) . '</strong> ' . esc_html( $text ) . '</p></div>';
		}
		if ( 'publish' === $post->post_status && '' !== $post->post_password ) {
			echo '<div class="notice notice-warning"><p><strong>' . esc_html__( 'Dieser Termin ist passwortgeschützt.', 'clesk-termine' ) . '</strong> '
				. esc_html__( 'Er erscheint deshalb nicht in den Terminlisten, seine eigene Seite verlangt das Passwort.', 'clesk-termine' ) . '</p></div>';
		}
	}

	/**
	 * CSS und JavaScript nur auf den Termin-Seiten laden.
	 *
	 * @param string $hook Admin-Seite.
	 */
	public static function assets( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || Clesk_Termine_Events::POST_TYPE !== $screen->post_type ) {
			return;
		}
		wp_enqueue_style( 'clesk-termine-admin', CLESK_TERMINE_URL . 'assets/admin.css', array(), CLESK_TERMINE_VERSION );

		if ( 'post.php' === $hook || 'post-new.php' === $hook ) {
			wp_enqueue_script( 'clesk-termine-admin', CLESK_TERMINE_URL . 'assets/admin.js', array(), CLESK_TERMINE_VERSION, true );
			wp_add_inline_script(
				'clesk-termine-admin',
				'window.cttAdmin = ' . wp_json_encode(
					array(
						'venuePlaces' => (object) Clesk_Termine_Events::venue_places(),
						'isCopy'      => ! empty( $_GET['ctt_kopie'] ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- nur Anzeige.
					)
				) . ';',
				'before'
			);
		}
	}
}
