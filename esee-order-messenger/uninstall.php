<?php
/**
 * Remove plugin options on uninstall.
 *
 * @package Esee_Order_Messenger
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'esee_om_settings' );
