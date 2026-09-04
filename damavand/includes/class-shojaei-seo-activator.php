<?php
/**
 * Plugin activation handler.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_Activator
 */
class Shojaei_SEO_Activator {

	/**
	 * Run on plugin activation.
	 */
	public static function activate(): void {
		try {
			self::create_tables();
			self::set_default_options();
			self::migrate_ollama_to_ai();
			self::schedule_cron_events();
			update_option( 'shojaei_seo_db_version', DAMAVAND_SEO_DB_VERSION );

			// خودترمیمی هسته سئو — اجبار به چک کامل روی فعال‌سازی.
			if ( ! class_exists( 'SEO_Core_Loader' ) && defined( 'DAMAVAND_SEO_DIR' ) ) {
				$loader = DAMAVAND_SEO_DIR . 'seo-core/class-seo-core-loader.php';
				if ( is_readable( $loader ) ) {
					require_once $loader;
				}
			}
			if ( class_exists( 'SEO_Core_Loader' ) ) {
				try {
					SEO_Core_Loader::install();
				} catch ( Throwable $e ) {
					if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
						// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
						error_log( '[Damavand SEO] activate install: ' . $e->getMessage() );
					}
				}
			}

			if ( class_exists( 'Shojaei_SEO_Helpers' ) ) {
				Shojaei_SEO_Helpers::ensure_admin_capabilities();
			}

			flush_rewrite_rules();

			if ( class_exists( 'Shojaei_SEO_Impact' ) ) {
				Shojaei_SEO_Impact::maybe_capture_baseline();
			} else {
				// Class may not be loaded during activate bootstrap — defer.
				update_option( 'shojaei_seo_need_health_baseline', 'yes', false );
			}

			// Queue initial OOS inventory scan (Action Scheduler or fallback).
			delete_option( 'shojaei_seo_initial_scan_done' );
			update_option( 'shojaei_seo_initial_scan_pending', 0, false );

			// Defer until WooCommerce/Action Scheduler is loaded.
			add_action( 'woocommerce_loaded', array( __CLASS__, 'schedule_scan_after_wc' ), 20 );
			if ( did_action( 'woocommerce_loaded' ) || class_exists( 'WooCommerce' ) ) {
				self::schedule_scan_after_wc();
			} else {
				// Fallback marker for first admin request.
				update_option( 'shojaei_seo_need_initial_scan', 'yes', false );
			}
		} catch ( Throwable $e ) {
			update_option(
				'damavand_seo_activate_error',
				$e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine(),
				false
			);
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( '[Damavand SEO] activate: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Schedule initial scan once WooCommerce is ready.
	 */
	public static function schedule_scan_after_wc(): void {
		if ( ! class_exists( 'Shojaei_SEO_Queue' ) ) {
			require_once DAMAVAND_SEO_DIR . 'includes/class-shojaei-seo-queue.php';
		}
		Shojaei_SEO_Queue::schedule_initial_scan();
		delete_option( 'shojaei_seo_need_initial_scan' );
	}

	/**
	 * Create custom database tables.
	 */
	private static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'shojaei_seo_oos_tracker';

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT(20) UNSIGNED NOT NULL,
			oos_date DATETIME NOT NULL,
			days_oos INT NOT NULL DEFAULT 0,
			status VARCHAR(50) NOT NULL DEFAULT 'temp_oos',
			redirect_type VARCHAR(10) NOT NULL DEFAULT 'none',
			target_url TEXT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY product_id (product_id),
			KEY status (status),
			KEY days_oos (days_oos)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		$links_table = $wpdb->prefix . 'shojaei_seo_internal_links';

		$sql_links = "CREATE TABLE {$links_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			keyword VARCHAR(255) NOT NULL,
			target_url TEXT NOT NULL,
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY keyword (keyword),
			KEY is_active (is_active)
		) {$charset_collate};";

		dbDelta( $sql_links );

		$log_table = $wpdb->prefix . 'shojaei_seo_redirect_log';

		$sql_log = "CREATE TABLE {$log_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT(20) UNSIGNED NOT NULL,
			redirect_type VARCHAR(10) NOT NULL,
			target_url TEXT NOT NULL,
			reason VARCHAR(100) NOT NULL DEFAULT 'auto',
			is_undone TINYINT(1) NOT NULL DEFAULT 0,
			user_id BIGINT(20) UNSIGNED NULL DEFAULT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY product_id (product_id),
			KEY is_undone (is_undone)
		) {$charset_collate};";

		dbDelta( $sql_log );

		$activity_table = $wpdb->prefix . 'shojaei_seo_activity_log';

		$sql_activity = "CREATE TABLE {$activity_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			action VARCHAR(50) NOT NULL,
			product_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			message TEXT NOT NULL,
			meta LONGTEXT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY action_key (action),
			KEY product_id (product_id),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql_activity );

		$revert_table = $wpdb->prefix . 'shojaei_seo_revert_log';

		$sql_revert = "CREATE TABLE {$revert_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			batch_id VARCHAR(36) NOT NULL,
			mode VARCHAR(20) NOT NULL DEFAULT 'applied',
			action VARCHAR(50) NOT NULL,
			entity_type VARCHAR(20) NOT NULL DEFAULT 'product',
			entity_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			summary TEXT NOT NULL,
			before_state LONGTEXT NULL,
			after_state LONGTEXT NULL,
			is_reverted TINYINT(1) NOT NULL DEFAULT 0,
			user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY batch_id (batch_id),
			KEY mode_key (mode),
			KEY is_reverted (is_reverted),
			KEY created_at (created_at)
		) {$charset_collate};";

		dbDelta( $sql_revert );

		if ( ! class_exists( 'Shojaei_SEO_Jobs' ) ) {
			require_once DAMAVAND_SEO_DIR . 'includes/class-shojaei-seo-jobs.php';
		}
		Shojaei_SEO_Jobs::create_table();

		$slug_table = $wpdb->prefix . 'shojaei_seo_slug_redirects';
		$sql_slug   = "CREATE TABLE {$slug_table} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
			old_slug VARCHAR(500) NOT NULL DEFAULT '',
			old_path VARCHAR(255) NOT NULL,
			old_url TEXT NOT NULL,
			new_url TEXT NOT NULL,
			redirect_type VARCHAR(10) NOT NULL DEFAULT '301',
			is_active TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY old_path_active (old_path, is_active),
			KEY product_id (product_id)
		) {$charset_collate};";
		dbDelta( $sql_slug );

		if ( class_exists( 'Shojaei_SEO_Manual_Redirect' ) ) {
			Shojaei_SEO_Manual_Redirect::install();
		} else {
			$manual_table = $wpdb->prefix . 'seo_core_manual_redirects';
			$sql_manual   = "CREATE TABLE {$manual_table} (
				id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
				group_id VARCHAR(32) NOT NULL DEFAULT '',
				source_raw VARCHAR(500) NOT NULL DEFAULT '',
				source_path VARCHAR(500) NOT NULL DEFAULT '',
				match_type VARCHAR(20) NOT NULL DEFAULT 'exact',
				ignore_case TINYINT(1) NOT NULL DEFAULT 0,
				destination TEXT NULL,
				redirect_type VARCHAR(10) NOT NULL DEFAULT '301',
				is_active TINYINT(1) NOT NULL DEFAULT 1,
				hits BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
				created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
				updated_at DATETIME NULL,
				PRIMARY KEY (id),
				KEY source_active (source_path(191), is_active),
				KEY group_id (group_id),
				KEY is_active (is_active)
			) {$charset_collate};";
			dbDelta( $sql_manual );
		}

		if ( class_exists( 'Shojaei_SEO_Link_Genius' ) ) {
			Shojaei_SEO_Link_Genius::install();
		}
		if ( class_exists( 'Shojaei_SEO_Pulse' ) ) {
			Shojaei_SEO_Pulse::install();
		}
		if ( class_exists( 'Damavand_Link_Manager' ) ) {
			Damavand_Link_Manager::install();
		} elseif ( defined( 'DAMAVAND_SEO_DIR' ) && is_readable( DAMAVAND_SEO_DIR . 'includes/class-damavand-link-manager.php' ) ) {
			require_once DAMAVAND_SEO_DIR . 'includes/class-damavand-link-manager.php';
			Damavand_Link_Manager::install();
		}
		// First install / upgrade: queue one background affinity calc (non-blocking).
		if ( class_exists( 'Damavand_Link_Calculator' ) && class_exists( 'Shojaei_SEO_Jobs' ) ) {
			if ( ! Shojaei_SEO_Jobs::has_active( Damavand_Link_Calculator::JOB_TYPE ) ) {
				Damavand_Link_Calculator::start_scan();
			}
		} elseif ( defined( 'DAMAVAND_SEO_DIR' ) && is_readable( DAMAVAND_SEO_DIR . 'includes/class-damavand-link-calculator.php' ) ) {
			require_once DAMAVAND_SEO_DIR . 'includes/class-damavand-link-calculator.php';
			if ( class_exists( 'Shojaei_SEO_Jobs' ) && ! Shojaei_SEO_Jobs::has_active( Damavand_Link_Calculator::JOB_TYPE ) ) {
				Damavand_Link_Calculator::start_scan();
			}
		}
		if ( class_exists( 'SEO_Core_Loader' ) ) {
			SEO_Core_Loader::install();
		}
	}

	/**
	 * Run database upgrades when plugin version changes.
	 */
	public static function maybe_upgrade(): void {
		$installed = get_option( 'shojaei_seo_db_version', '0' );

		if ( version_compare( $installed, DAMAVAND_SEO_DB_VERSION, '>=' ) ) {
			return;
		}

		self::create_tables();
		self::set_default_options();
		self::migrate_to_1_7( $installed );

		// خودترمیمی هسته پس از ارتقا نسخه.
		if ( class_exists( 'SEO_Core_Installer' ) ) {
			SEO_Core_Installer::invalidate_health_cache();
			SEO_Core_Installer::ensure_infrastructure( true );
		} elseif ( class_exists( 'SEO_Core_Loader' ) ) {
			SEO_Core_Loader::install();
		}

		// 1.11: variation canonical on by default for upgrades.
		if ( false === get_option( 'shojaei_seo_variation_canonical', false ) ) {
			add_option( 'shojaei_seo_variation_canonical', 'yes', '', false );
		}

		// 1.12: slug tools defaults (complementary removed in 1.32 / task 2).
		foreach ( array(
			'shojaei_seo_slug_tools_enabled'    => 'yes',
			'shojaei_seo_slug_auto_finglish'    => 'yes',
			'shojaei_seo_slug_auto_301'         => 'yes',
			'shojaei_seo_oos_related_limit'     => 4,
		) as $opt => $val ) {
			if ( false === get_option( $opt, false ) ) {
				add_option( $opt, $val, '', false );
			}
		}

		// 1.16: custom Finglish dictionary (empty array by default).
		if ( false === get_option( 'shojaei_seo_finglish_dictionary', false ) ) {
			add_option( 'shojaei_seo_finglish_dictionary', array(), '', false );
		}

		// 1.17.x: uninstall safety — keep redirect data unless merchant opts in.
		if ( false === get_option( 'shojaei_seo_remove_data_on_uninstall', false ) ) {
			add_option( 'shojaei_seo_remove_data_on_uninstall', 'no', '', false );
		}

		// 1.58 / DB 1.31: Rank Math–parity crawl budget + single Product schema owner.
		if ( version_compare( $installed, '1.31.0', '<' ) ) {
			foreach ( array(
				'shojaei_seo_meta_noindex_facets'      => 'yes',
				'shojaei_seo_meta_noindex_author_date' => 'yes',
				'shojaei_seo_meta_noindex_wc_system'   => 'yes',
			) as $opt => $val ) {
				if ( false === get_option( $opt, false ) ) {
					add_option( $opt, $val, '', false );
				}
			}
			// If Damavand schema is on and WC schema not explicitly managed, prefer disable.
			if ( 'yes' === (string) get_option( 'shojaei_seo_schema_product_enabled', 'yes' )
				&& false === get_option( 'shojaei_seo_disable_wc_schema', false ) ) {
				add_option( 'shojaei_seo_disable_wc_schema', 'yes', '', false );
			}
			// Enable general meta output when no competitor was intended (fresh Rank Math alternative).
			if ( false === get_option( 'shojaei_seo_meta_enabled', false ) ) {
				add_option( 'shojaei_seo_meta_enabled', 'yes', '', false );
			}
		}

		// 1.32: remove checkout cross-sell + complementary product options (features deleted).
		if ( version_compare( $installed, '1.32.0', '<' ) ) {
			self::cleanup_removed_merchandising_options();
		}

		// 1.33: remove Damavand size-chart postmeta (feature deleted).
		if ( version_compare( $installed, '1.33.0', '<' ) ) {
			self::cleanup_removed_size_chart_meta();
		}

		if ( class_exists( 'Shojaei_SEO_Jobs' ) ) {
			Shojaei_SEO_Jobs::migrate_from_options();
		}

		// Fix Jalali-as-Gregorian oos_date rows (e.g. 226898 days).
		if ( class_exists( 'Shojaei_SEO_Helpers' ) ) {
			Shojaei_SEO_Helpers::repair_invalid_oos_dates( 1000 );
			update_option( 'shojaei_seo_oos_dates_repaired', DAMAVAND_SEO_DB_VERSION, false );
		}

		self::schedule_cron_events();
		update_option( 'shojaei_seo_db_version', DAMAVAND_SEO_DB_VERSION );

		if ( class_exists( 'Shojaei_SEO_Impact' ) ) {
			Shojaei_SEO_Impact::maybe_capture_baseline();
		}

		if ( 'yes' !== get_option( 'shojaei_seo_initial_scan_done', '' ) ) {
			update_option( 'shojaei_seo_need_initial_scan', 'yes', false );
		}

		// Rewrite flush is handled by maybe_sync_plugin_version() on every plugin version bump.
	}

	/**
	 * حذف آپشن‌های یتیم فیچرهای حذف‌شدهٔ Cross-Sell چک‌اوت و محصولات مکمل.
	 * دادهٔ سئو/OOS/ریدایرکت دست‌نخورده می‌ماند.
	 */
	public static function cleanup_removed_merchandising_options(): void {
		$orphans = array(
			'shojaei_seo_checkout_box_enabled',
			'shojaei_seo_checkout_max_products',
			'shojaei_seo_complementary_enabled',
			'shojaei_seo_complementary_mode',
			'shojaei_seo_complementary_limit',
		);
		foreach ( $orphans as $opt ) {
			delete_option( $opt );
		}
	}

	/**
	 * حذف postmeta جدول سایزبندی Damavand (فیچر حذف‌شده).
	 * کلیدها: `_damavand_size_chart_raw` و `_damavand_size_chart_html`.
	 */
	public static function cleanup_removed_size_chart_meta(): void {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			"DELETE FROM {$wpdb->postmeta}
			WHERE meta_key IN ('_damavand_size_chart_raw', '_damavand_size_chart_html')"
		);
	}

	/**
	 * On plugin file version change: request rewrite flush (never flush on plugins_loaded).
	 */
	public static function maybe_sync_plugin_version(): void {
		if ( ! defined( 'DAMAVAND_SEO_VERSION' ) ) {
			return;
		}
		$stored = (string) get_option( 'damavand_seo_plugin_version', '' );
		if ( $stored === DAMAVAND_SEO_VERSION ) {
			return;
		}

		// Safety net: if any table was dropped manually, recreate with dbDelta on version bump.
		self::create_tables();

		// Only flag + defer — $wp_rewrite is often null on plugins_loaded.
		if ( class_exists( 'SEO_Core_Installer' ) ) {
			SEO_Core_Installer::request_rewrite_flush();
		} else {
			update_option( 'seo_core_rewrite_needs_flush', '1', false );
			add_action(
				'init',
				static function () {
					global $wp_rewrite;
					if ( $wp_rewrite instanceof WP_Rewrite && '1' === (string) get_option( 'seo_core_rewrite_needs_flush', '' ) ) {
						flush_rewrite_rules( false );
						delete_option( 'seo_core_rewrite_needs_flush' );
					}
				},
				99
			);
		}

		update_option( 'damavand_seo_plugin_version', DAMAVAND_SEO_VERSION, false );
		self::migrate_ollama_to_ai();
		if ( class_exists( 'Shojaei_SEO_AI_Client' ) ) {
			Shojaei_SEO_AI_Client::model();
		}
		if ( class_exists( 'Shojaei_SEO_Helpers' ) ) {
			Shojaei_SEO_Helpers::ensure_admin_capabilities();
		}
		delete_option( 'shojaei_seo_oos_days_refreshed' );

		if ( class_exists( 'Damavand_SEO_Templates' ) ) {
			Damavand_SEO_Templates::ensure_defaults();
		}
		if ( false === get_option( 'shojaei_seo_oos_notify_enabled', false ) ) {
			add_option( 'shojaei_seo_oos_notify_enabled', 'yes', '', false );
		}
		if ( false === get_option( 'shojaei_seo_oos_related_limit', false ) ) {
			add_option( 'shojaei_seo_oos_related_limit', 4, '', false );
		}
		foreach ( array(
			'seo_core_sitemap_include_posts'        => 'yes',
			'seo_core_sitemap_include_pages'        => 'yes',
			'seo_core_sitemap_include_products'     => 'yes',
			'seo_core_sitemap_include_categories'   => 'yes',
			'seo_core_sitemap_include_product_cats' => 'yes',
			'seo_core_sitemap_include_product_tags' => 'yes',
			'seo_core_sitemap_product_gallery'      => 'yes',
			'seo_core_sitemap_post_images'          => 'yes',
			'seo_core_sitemap_alias_xml'            => 'yes',
			'seo_core_sitemap_claim_robots'         => 'yes',
		) as $opt => $val ) {
			if ( false === get_option( $opt, false ) ) {
				add_option( $opt, $val, '', false );
			}
		}
	}

	/**
	 * One-time: copy Ollama toggle to new AI options (1.53+).
	 */
	public static function migrate_ollama_to_ai(): void {
		if ( 'yes' === (string) get_option( 'shojaei_seo_ai_migrated', '' ) ) {
			return;
		}

		$ollama_on = (string) get_option( 'shojaei_seo_ollama_enabled', '' );
		if ( '' !== $ollama_on && false === get_option( 'shojaei_seo_ai_enabled', false ) ) {
			update_option( 'shojaei_seo_ai_enabled', $ollama_on, false );
		}

		foreach ( array(
			'shojaei_seo_ai_provider' => 'openrouter',
			'shojaei_seo_ai_model'    => 'meta-llama/llama-3.3-70b-instruct',
			'shojaei_seo_ai_timeout'  => 30,
		) as $opt => $default ) {
			if ( false === get_option( $opt, false ) ) {
				add_option( $opt, $default, '', false );
			}
		}

		update_option( 'shojaei_seo_ai_migrated', 'yes', false );
	}

	/**
	 * Migrate lifecycle defaults and backfill postmeta for 1.7.
	 *
	 * @param string $installed Previous DB version.
	 */
	private static function migrate_to_1_7( string $installed ): void {
		if ( version_compare( $installed, '1.7.0', '>=' ) ) {
			return;
		}

		update_option( 'shojaei_seo_oos_message_day', 15 );
		update_option( 'shojaei_seo_oos_temp_days', 30 );
		update_option( 'shojaei_seo_oos_auto_day', 45 );
		update_option( 'shojaei_seo_oos_auto_redirect_type', '302' );
		update_option( 'shojaei_seo_oos_page_value_enabled', 'yes' );
		update_option( 'shojaei_seo_oos_page_value_threshold', 60 );
		update_option( 'shojaei_seo_oos_phase1_days', 15 );
		update_option( 'shojaei_seo_oos_phase2_days', 30 );
		update_option( 'shojaei_seo_oos_phase3_days', 45 );

		global $wpdb;
		$table = $wpdb->prefix . 'shojaei_seo_oos_tracker';
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "UPDATE {$table} SET status = 'temp_oos' WHERE status = 'soft_oos'" );

		$rows = $wpdb->get_results( "SELECT product_id, oos_date, days_oos FROM {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $rows ) {
			foreach ( $rows as $row ) {
				Shojaei_SEO_Helpers::sync_oos_postmeta( (int) $row->product_id, (string) $row->oos_date, (int) $row->days_oos );
			}
		}
	}

	/**
	 * Set default plugin options.
	 */
	private static function set_default_options(): void {
		$defaults = array(
			'shojaei_seo_oos_enabled'           => 'yes',
			'shojaei_seo_link_builder_enabled'  => 'yes',
			'shojaei_seo_schema_enabled'        => 'yes',
			'shojaei_seo_schema_detect_enabled' => 'yes',
			'shojaei_seo_disable_wc_schema'     => 'yes',
			'shojaei_seo_schema_product_enabled'=> 'yes',
			'shojaei_seo_schema_breadcrumb_enabled' => 'yes',
			'shojaei_seo_schema_faq_enabled'    => 'yes',
			'shojaei_seo_faq_returns_url'       => '',
			'shojaei_seo_faq_returns_page_id'   => '0',
			'shojaei_seo_schema_article_enabled' => 'yes',
			'shojaei_seo_schema_site_enabled'   => 'yes',
			'shojaei_seo_schema_collection_enabled' => 'yes',
			'shojaei_seo_schema_respect_seo_plugins' => 'yes',
			'shojaei_seo_meta_enabled'          => 'yes',
			'shojaei_seo_meta_force_with_competitors' => 'no',
			'shojaei_seo_meta_robots_noindex'   => 'no',
			'shojaei_seo_meta_robots_nofollow'  => 'no',
			'shojaei_seo_meta_robots_noarchive' => 'no',
			'shojaei_seo_meta_robots_noimageindex' => 'no',
			'shojaei_seo_meta_robots_nosnippet' => 'no',
			'shojaei_seo_meta_adv_snippet'      => 'no',
			'shojaei_seo_meta_adv_video'        => 'no',
			'shojaei_seo_meta_adv_image'        => 'yes',
			'shojaei_seo_meta_max_snippet'      => -1,
			'shojaei_seo_meta_max_video_preview'=> -1,
			'shojaei_seo_meta_max_image_preview'=> 'large',
			'shojaei_seo_meta_noindex_empty_tax'=> 'yes',
			'shojaei_seo_meta_noindex_facets'   => 'yes',
			'shojaei_seo_meta_noindex_author_date' => 'yes',
			'shojaei_seo_meta_noindex_wc_system'=> 'yes',
			'shojaei_seo_meta_separator'        => '-',
			'shojaei_seo_meta_separator_custom' => '',
			'shojaei_seo_meta_og_image_id'      => 0,
			'damavand_seo_tpl_product_title'    => '%title% %sep% %sitename%',
			'damavand_seo_tpl_product_desc'     => 'خرید %title% با بهترین قیمت از %sitename%. %excerpt%',
			'damavand_seo_tpl_post_title'       => '%title% %sep% %sitename%',
			'damavand_seo_tpl_post_desc'        => '%excerpt%',
			'damavand_seo_tpl_page_title'       => '%title% %sep% %sitename%',
			'damavand_seo_tpl_page_desc'        => '%excerpt%',
			'shojaei_seo_variation_canonical'   => 'yes',
			'shojaei_seo_slug_tools_enabled'    => 'yes',
			'shojaei_seo_slug_auto_finglish'    => 'yes',
			'shojaei_seo_slug_auto_301'         => 'yes',
			'shojaei_seo_oos_related_limit'     => 4,
			'shojaei_seo_finglish_dictionary'   => array(),
			'shojaei_seo_remove_data_on_uninstall' => 'no',
			'shojaei_seo_store_profile'         => 'general',
			'shojaei_seo_batch_size'            => 50,
			'shojaei_seo_job_max_attempts'      => 3,
			'shojaei_seo_event_driven'          => 'yes',
			'shojaei_seo_gsc_enabled'           => 'no',
			'shojaei_seo_gsc_auto_index'        => 'yes',
			'shojaei_seo_indexnow_enabled'      => 'yes',
			'shojaei_seo_ai_enabled'            => 'yes',
			'shojaei_seo_ai_provider'           => 'openrouter',
			'shojaei_seo_ai_api_key'            => '',
			'shojaei_seo_ai_model'              => 'meta-llama/llama-3.3-70b-instruct',
			'shojaei_seo_ai_timeout'            => 30,
			'shojaei_seo_schema_itemlist_enabled' => 'yes',
			'shojaei_seo_oos_notify_enabled'    => 'yes',
			'shojaei_seo_oos_phase1_days'       => 15,
			'shojaei_seo_oos_phase2_days'       => 30,
			'shojaei_seo_oos_phase3_days'       => 45,
			'shojaei_seo_oos_message_day'       => 15,
			'shojaei_seo_oos_temp_days'         => 30,
			'shojaei_seo_oos_auto_day'          => 45,
			'shojaei_seo_oos_auto_redirect_type'=> '302',
			'shojaei_seo_oos_auto_redirect'     => 'yes',
			'shojaei_seo_oos_match_threshold'   => 70,
			'shojaei_seo_link_max_per_1000'     => 3,
			'shojaei_seo_link_max_per_page'     => 5,
			'shojaei_seo_link_min_word_gap'     => 200,
			'shojaei_seo_link_whitelist_only'   => 'no',
			'shojaei_seo_link_keyword_blacklist'=> '',
			'shojaei_seo_link_keyword_whitelist'=> '',
			'shojaei_seo_link_url_blacklist'    => '',
			'shojaei_seo_link_url_whitelist'    => '',
			'shojaei_seo_oos_noindex_from_phase' => 2,
			'shojaei_seo_oos_noindex_enabled'    => 'yes',
			'shojaei_seo_oos_dry_run'            => 'yes',
			'shojaei_seo_oos_page_value_enabled' => 'yes',
			'shojaei_seo_oos_page_value_threshold' => 60,
			'shojaei_seo_stats_links_built'     => 0,
			'shojaei_seo_stats_redirects'       => 0,
			'shojaei_seo_stats_indexed_today'   => 0,
			'shojaei_seo_stats_indexed_date'    => gmdate( 'Y-m-d' ),
		);

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				add_option( $key, $value );
			}
		}

		// کلید IndexNow مشترک (canonical + legacy).
		$legacy = (string) get_option( 'shojaei_seo_indexnow_key', '' );
		$canon  = (string) get_option( 'seo_core_indexnow_key', '' );
		if ( '' === $legacy && '' === $canon ) {
			$key = wp_generate_password( 32, false );
			add_option( 'shojaei_seo_indexnow_key', $key );
			add_option( 'seo_core_indexnow_key', $key );
		} elseif ( '' === $canon && '' !== $legacy ) {
			add_option( 'seo_core_indexnow_key', $legacy );
		} elseif ( '' !== $canon && '' === $legacy ) {
			add_option( 'shojaei_seo_indexnow_key', $canon );
		}
	}

	/**
	 * Schedule cron events.
	 */
	private static function schedule_cron_events(): void {
		if ( ! wp_next_scheduled( 'shojaei_seo_daily_oos_check' ) ) {
			wp_schedule_event( time(), 'daily', 'shojaei_seo_daily_oos_check' );
		}
		if ( ! wp_next_scheduled( 'shojaei_seo_process_queue' ) ) {
			wp_schedule_event( time(), 'hourly', 'shojaei_seo_process_queue' );
		}
		if ( ! wp_next_scheduled( 'shojaei_seo_weekly_summary' ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', 'shojaei_seo_weekly_summary' );
		}
		if ( class_exists( 'Damavand_Link_Watchdog' ) ) {
			Damavand_Link_Watchdog::ensure_scheduled();
		}
		if ( class_exists( 'Shojaei_SEO_Jobs' ) ) {
			Shojaei_SEO_Jobs::ensure_tick_scheduled();
		} elseif ( class_exists( 'Shojaei_SEO_Batch' ) ) {
			Shojaei_SEO_Batch::ensure_tick_scheduled();
		}
	}
}
