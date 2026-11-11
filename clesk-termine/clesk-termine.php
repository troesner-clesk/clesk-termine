<?php
/**
 * Plugin Name:       Termine & Tickets
 * Plugin URI:        https://github.com/troesner-clesk/clesk-termine
 * Description:       Veranstaltungen mit Datum, Uhrzeit, Stadt, Location und Ticket-Link pflegen, mit dem Block „Termine“ samt Filtern (Stadt, Location, Monat) anzeigen und jedem Termin eine eigene Seite zum Teilen geben.
 * Version:           1.0.1
 * Requires at least: 6.3
 * Requires PHP:      7.4
 * Author:            Clesk Digital GmbH
 * Author URI:        https://clesk.de
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       clesk-termine
 * Update URI:        https://github.com/troesner-clesk/clesk-termine
 *
 * @package Clesk_Termine
 */

defined( 'ABSPATH' ) || exit;

define( 'CLESK_TERMINE_VERSION', '1.0.1' );
define( 'CLESK_TERMINE_DIR', plugin_dir_path( __FILE__ ) );
define( 'CLESK_TERMINE_URL', plugin_dir_url( __FILE__ ) );

require_once CLESK_TERMINE_DIR . 'includes/class-events.php';
require_once CLESK_TERMINE_DIR . 'includes/class-render.php';
require_once CLESK_TERMINE_DIR . 'includes/class-block.php';
require_once CLESK_TERMINE_DIR . 'includes/class-single.php';

Clesk_Termine_Events::init();
Clesk_Termine_Block::init();
Clesk_Termine_Single::init();

register_deactivation_hook( __FILE__, array( 'Clesk_Termine_Events', 'deactivate' ) );

if ( is_admin() ) {
	require_once CLESK_TERMINE_DIR . 'includes/class-admin.php';
	Clesk_Termine_Admin::init();
}
