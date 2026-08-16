<?php
/**
 * Plugin Name:       Esee Automatic WebP
 * Plugin URI:        https://github.com/esmaeelsh93-lab/esee
 * Description:       Converts uploaded WordPress images to high-quality WebP using the available WordPress image editor.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Esee
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       esee-webp-converter
 */

defined( 'ABSPATH' ) || exit;

define( 'ESEE_WEBP_VERSION', '1.0.0' );
define( 'ESEE_WEBP_FILE', __FILE__ );
define( 'ESEE_WEBP_PATH', plugin_dir_path( __FILE__ ) );

require_once ESEE_WEBP_PATH . 'includes/class-esee-webp-converter.php';

if ( is_admin() ) {
	require_once ESEE_WEBP_PATH . 'includes/class-esee-webp-converter-admin.php';
}

/**
 * Starts the plugin after WordPress has loaded all active plugins.
 *
 * @return void
 */
function esee_webp_converter_boot() {
	$converter = new Esee_WebP_Converter();
	$converter->init();

	if ( is_admin() ) {
		$admin = new Esee_WebP_Converter_Admin();
		$admin->init();
	}
}
add_action( 'plugins_loaded', 'esee_webp_converter_boot' );
