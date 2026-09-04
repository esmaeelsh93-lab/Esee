<?php
/**
 * Fired when the plugin is uninstalled (deleted from WordPress).
 *
 * Default: KEEP operational data (slug 301s, OOS redirects, settings)
 * so old URLs do not break after deletion. Full wipe only when the
 * merchant explicitly enabled «حذف داده با حذف افزونه».
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

// Always stop schedules — without the plugin they cannot run.
wp_clear_scheduled_hook( 'shojaei_seo_daily_oos_check' );
wp_clear_scheduled_hook( 'shojaei_seo_process_queue' );
wp_clear_scheduled_hook( 'shojaei_seo_weekly_summary' );
wp_clear_scheduled_hook( 'shojaei_seo_batch_tick' );
wp_clear_scheduled_hook( 'shojaei_seo_jobs_tick' );
wp_clear_scheduled_hook( 'shojaei_seo_as_gsc_verify' );
wp_clear_scheduled_hook( 'shojaei_seo_as_react_event' );
wp_clear_scheduled_hook( 'shojaei_seo_pulse_daily' );
wp_clear_scheduled_hook( 'damavand_link_calc_daily' );
wp_clear_scheduled_hook( 'seo_core_404_purge' );

if ( function_exists( 'as_unschedule_all_actions' ) ) {
	as_unschedule_all_actions( null, null, 'shojaei-seo' );
}

$remove_data = get_option( 'shojaei_seo_remove_data_on_uninstall', 'no' );

// Safe default: keep redirects / tracker / settings for reinstall or migration.
if ( 'yes' !== $remove_data ) {
	return;
}

// --- Full wipe (only if merchant opted in) ---
// Prefer class::uninstall() when files are loadable; fallback to DROP TABLE list.

$plugin_dir = dirname( __FILE__ );
$class_map  = array(
	$plugin_dir . '/includes/class-shojaei-seo-pulse.php'            => 'Shojaei_SEO_Pulse',
	$plugin_dir . '/includes/class-damavand-link-manager.php'        => 'Damavand_Link_Manager',
	$plugin_dir . '/includes/class-shojaei-seo-link-genius.php'       => 'Shojaei_SEO_Link_Genius',
	$plugin_dir . '/includes/class-shojaei-seo-manual-redirect.php'   => 'Shojaei_SEO_Manual_Redirect',
	$plugin_dir . '/seo-core/class-seo-core-loader.php'               => 'SEO_Core_Loader',
);

foreach ( $class_map as $file => $class ) {
	if ( ! is_readable( $file ) ) {
		continue;
	}
	require_once $file;
	if ( class_exists( $class ) && method_exists( $class, 'uninstall' ) ) {
		$class::uninstall();
	}
}

$tables = array(
	$wpdb->prefix . 'shojaei_seo_oos_tracker',
	$wpdb->prefix . 'shojaei_seo_internal_links',
	$wpdb->prefix . 'shojaei_seo_redirect_log',
	$wpdb->prefix . 'shojaei_seo_activity_log',
	$wpdb->prefix . 'shojaei_seo_revert_log',
	$wpdb->prefix . 'shojaei_seo_jobs',
	$wpdb->prefix . 'shojaei_seo_slug_redirects',
	$wpdb->prefix . 'shojaei_seo_manual_redirects',
	$wpdb->prefix . 'seo_core_manual_redirects',
	$wpdb->prefix . 'shojaei_seo_keyword_maps',
	$wpdb->prefix . 'shojaei_seo_link_inventory',
	$wpdb->prefix . 'seo_core_link_genius',
	$wpdb->prefix . 'shojaei_seo_pulse_results',
	$wpdb->prefix . 'damavand_link_graph',
	$wpdb->prefix . 'seo_core_logs',
	$wpdb->prefix . 'seo_core_reports',
	$wpdb->prefix . 'seo_core_404_monitor',
);

foreach ( $tables as $table ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

$options = $wpdb->get_col(
	"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE 'shojaei_seo_%'"
);

if ( is_array( $options ) ) {
	foreach ( $options as $option_name ) {
		delete_option( $option_name );
	}
}

$wpdb->query(
	"DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_shojaei_seo_%' OR option_name LIKE '_transient_timeout_shojaei_seo_%' OR option_name LIKE '_transient_seo_core_%' OR option_name LIKE '_transient_timeout_seo_core_%' OR option_name LIKE '_transient_damavand_lg_%' OR option_name LIKE '_transient_timeout_damavand_lg_%' OR option_name LIKE '_transient_damavand_sim_prod_%' OR option_name LIKE '_transient_timeout_damavand_sim_prod_%'"
);

delete_option( 'damavand_similar_products_settings' );

foreach ( array(
	'seo_core_schema_version',
	'seo_core_rewrite_needs_flush',
	'seo_core_overrides',
	'seo_core_indexnow_key',
	'seo_core_disabled_modules',
	'seo_core_last_heal_report',
	'seo_core_404_retention_days',
	'seo_core_404_ignore_bots',
	'seo_core_robots_mode',
	'seo_core_robots_extra',
	'seo_core_robots_add_sitemap',
	'seo_core_canonical_force_https',
	'seo_core_canonical_strip_args',
) as $seo_core_opt ) {
	delete_option( $seo_core_opt );
}

// Product meta written by the plugin (flags / snapshots) — only on full wipe.
$wpdb->query(
	"DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '\\_shojaei\\_seo\\_%'"
);
$wpdb->query(
	"DELETE FROM {$wpdb->postmeta} WHERE meta_key IN ('_damavand_size_chart_raw', '_damavand_size_chart_html')"
);

$upload = wp_upload_dir();
if ( ! empty( $upload['basedir'] ) ) {
	$gsc_dir = trailingslashit( $upload['basedir'] ) . 'shojaei-seo-private';
	$gsc_key = $gsc_dir . '/gsc-service-account.json';
	if ( file_exists( $gsc_key ) ) {
		wp_delete_file( $gsc_key );
	}
	foreach ( array( '.htaccess', 'index.php' ) as $guard ) {
		$guard_path = $gsc_dir . '/' . $guard;
		if ( file_exists( $guard_path ) ) {
			wp_delete_file( $guard_path );
		}
	}
	if ( is_dir( $gsc_dir ) ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
		rmdir( $gsc_dir );
	}
}
