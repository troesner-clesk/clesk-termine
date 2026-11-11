<?php
/**
 * Abhängigkeiten des Editor-Skripts (ohne Build-Schritt gepflegt).
 *
 * @package Clesk_Termine
 */

return array(
	'dependencies' => array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-components', 'wp-server-side-render' ),
	'version'      => defined( 'CLESK_TERMINE_VERSION' ) ? CLESK_TERMINE_VERSION : '1.0.0',
);
