<?php
/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

/**
 * اجرا می‌شود فقط زمانی که کاربر افزونه را به‌طور کامل از وردپرس حذف کند.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$settings = get_option( 'aaw_settings', array() );

if ( empty( $settings['delete_on_uninstall'] ) ) {
	return;
}

global $wpdb;

$tables = array(
	$wpdb->prefix . 'aaw_visits',
	$wpdb->prefix . 'aaw_funnel_events',
	$wpdb->prefix . 'aaw_cart_snapshots',
	$wpdb->prefix . 'aaw_heatmap_events',
	$wpdb->prefix . 'aaw_replay_sessions',
	$wpdb->prefix . 'aaw_replay_events',
	$wpdb->prefix . 'aaw_alerts',
);

foreach ( $tables as $table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

delete_option( 'aaw_settings' );
delete_option( 'aaw_salt' );
delete_option( 'aaw_db_version' );
