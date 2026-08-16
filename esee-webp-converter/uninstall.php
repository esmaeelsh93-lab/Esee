<?php
/**
 * Removes plugin settings when the plugin is deleted.
 *
 * @package Esee_Automatic_WebP
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'esee_webp_converter_settings' );
