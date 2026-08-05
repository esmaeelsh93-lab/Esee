<?php
/**
 * اجرا می‌شود فقط زمانی که کاربر افزونه را به‌طور کامل از وردپرس حذف کند.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'cvs_settings', array() );

if ( empty( $settings['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;
$table = $wpdb->prefix . 'cvs_visits';
$wpdb->query( "DROP TABLE IF EXISTS {$table}" );

$funnel_table = $wpdb->prefix . 'cvs_funnel_events';
$wpdb->query( "DROP TABLE IF EXISTS {$funnel_table}" );

delete_option( 'cvs_settings' );
delete_option( 'cvs_salt' );
delete_option( 'cvs_db_version' );
