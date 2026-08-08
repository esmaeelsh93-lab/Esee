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
$tables = array(
	$wpdb->prefix . 'cvs_visits',
	$wpdb->prefix . 'cvs_sessions',
	$wpdb->prefix . 'cvs_daily_summary',
	$wpdb->prefix . 'cvs_city_daily',
);
foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

delete_option( 'cvs_settings' );
delete_option( 'cvs_salt' );
delete_option( 'cvs_db_version' );
