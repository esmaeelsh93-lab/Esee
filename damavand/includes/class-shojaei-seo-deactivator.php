<?php
/**
 * Plugin deactivation handler.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_Deactivator
 */
class Shojaei_SEO_Deactivator {

	/**
	 * Run on plugin deactivation.
	 */
	public static function deactivate(): void {
		wp_clear_scheduled_hook( 'shojaei_seo_daily_oos_check' );
		wp_clear_scheduled_hook( 'shojaei_seo_process_queue' );
		wp_clear_scheduled_hook( 'shojaei_seo_weekly_summary' );
		wp_clear_scheduled_hook( 'shojaei_seo_batch_tick' );
		wp_clear_scheduled_hook( 'shojaei_seo_jobs_tick' );
		wp_clear_scheduled_hook( 'shojaei_seo_as_react_event' );
		wp_clear_scheduled_hook( 'shojaei_seo_pulse_daily' );
		wp_clear_scheduled_hook( 'damavand_link_calc_daily' );
		wp_clear_scheduled_hook( 'shojaei_seo_link_watchdog' );
		if ( class_exists( 'Damavand_Link_Watchdog' ) ) {
			Damavand_Link_Watchdog::clear_scheduled();
		}
		wp_clear_scheduled_hook( 'seo_core_404_purge' );

		if ( class_exists( 'SEO_Core_Installer' ) ) {
			SEO_Core_Installer::clear_cron_jobs();
		} elseif ( is_readable( dirname( __DIR__ ) . '/seo-core/class-seo-core-installer.php' ) ) {
			require_once dirname( __DIR__ ) . '/seo-core/class-seo-core-installer.php';
			SEO_Core_Installer::clear_cron_jobs();
		}

		if ( class_exists( 'Shojaei_SEO_Jobs' ) ) {
			Shojaei_SEO_Jobs::clear_scheduled();
		}
		if ( class_exists( 'Shojaei_SEO_Batch' ) ) {
			Shojaei_SEO_Batch::clear_scheduled();
		}

		if ( function_exists( 'as_unschedule_all_actions' ) ) {
			as_unschedule_all_actions( null, null, 'shojaei-seo' );
		}

		flush_rewrite_rules();
	}
}
