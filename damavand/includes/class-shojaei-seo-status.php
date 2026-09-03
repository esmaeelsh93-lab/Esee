<?php
/**
 * Status-centric ops board — what needs attention now.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_Status
 */
class Shojaei_SEO_Status {

	public const SETUP_OPTION = 'shojaei_seo_setup_done';

	/**
	 * Whether setup wizard is finished.
	 */
	public static function is_setup_done(): bool {
		$flag = get_option( self::SETUP_OPTION, null );
		if ( 'yes' === $flag ) {
			return true;
		}
		if ( 'no' === $flag ) {
			return false;
		}

		// Legacy installs (before wizard): skip if already operational.
		if ( 'yes' === get_option( 'shojaei_seo_initial_scan_done', '' ) ) {
			update_option( self::SETUP_OPTION, 'yes', false );
			return true;
		}

		return false;
	}

	/**
	 * Mark setup complete.
	 */
	public static function mark_setup_done(): void {
		update_option( self::SETUP_OPTION, 'yes', false );
	}

	/**
	 * Full dashboard snapshot (cached briefly per request).
	 *
	 * @return array
	 */
	public static function snapshot(): array {
		static $cache = null;
		if ( null !== $cache ) {
			return $cache;
		}

		$timeline = Shojaei_SEO_Helpers::get_oos_timeline();
		$temp_day = (int) $timeline['temp_days'];

		$action_needed   = self::count_action_needed( $temp_day );
		$suggested       = self::count_suggested_redirects( 50 );
		$errors          = self::recent_errors( 5 );
		$undo_batches    = self::recent_undo_batches( 5 );
		$overall         = self::overall_status( $action_needed, $suggested, $errors );
		$dry_run         = 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_dry_run', 'yes' );
		$jobs_active     = class_exists( 'Shojaei_SEO_Jobs' ) ? Shojaei_SEO_Jobs::count_active() : 0;
		$scan_pending    = (int) get_option( 'shojaei_seo_initial_scan_pending', 0 );

		$cache = array(
			'overall'           => $overall,
			'action_needed'     => $action_needed,
			'suggested'         => $suggested,
			'errors'            => $errors,
			'undo_batches'      => $undo_batches,
			'dry_run'           => $dry_run,
			'jobs_active'       => $jobs_active,
			'scan_pending'      => $scan_pending,
			'scan_done'         => 'yes' === get_option( 'shojaei_seo_initial_scan_done', '' ),
			'event_driven'      => class_exists( 'Shojaei_SEO_Events' ) && Shojaei_SEO_Events::is_enabled(),
			'temp_days'         => $temp_day,
			'cards'             => self::build_action_cards( $action_needed, $suggested, $errors, $undo_batches, $dry_run, $scan_pending ),
			'next_steps'        => self::next_steps( $action_needed, $suggested, $errors, $dry_run, $scan_pending ),
		);

		return $cache;
	}

	/**
	 * Products needing attention (long OOS / candidates / manual).
	 *
	 * @param int $temp_days Threshold days.
	 * @return array{count:int,over_threshold:int,candidates:int,manual:int,label:string,status:string}
	 */
	public static function count_action_needed( int $temp_days = 30 ): array {
		global $wpdb;
		$table = Shojaei_SEO_Helpers::oos_table();

		$now_mysql = current_time( 'mysql' );
		$tracked   = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE status NOT IN ('redirected')" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		$over      = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
				WHERE status NOT IN ('redirected')
				AND TIMESTAMPDIFF(DAY, oos_date, %s) >= %d",
				$now_mysql,
				max( 1, $temp_days )
			)
		);

		$candidates = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE status = 'candidate_redirect'" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		$manual = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE status = 'needs_manual'" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		// oos_date is often "plugin install day"; still show the real OOS queue.
		$count  = max( $tracked, $over, $candidates + $manual );
		$status = 'safe';
		if ( $count >= 20 || $manual > 0 ) {
			$status = 'action';
		} elseif ( $count > 0 ) {
			$status = 'warning';
		}

		if ( $over > 0 ) {
			$label = sprintf(
				/* translators: 1: tracked, 2: over-threshold, 3: days */
				__( '%1$d در صف ناموجودی · %2$d بالای %3$d روز', 'shojaei-seo-for-woo' ),
				$tracked,
				$over,
				$temp_days
			);
		} else {
			$label = sprintf(
				/* translators: %d: tracked */
				__( '%d محصول در صف ناموجودی (مرکز ناموجودی را باز کنید)', 'shojaei-seo-for-woo' ),
				$tracked
			);
		}

		return array(
			'count'          => $count,
			'tracked'        => $tracked,
			'over_threshold' => $over,
			'candidates'     => $candidates,
			'manual'         => $manual,
			'threshold'      => $temp_days,
			'label'          => $label,
			'status'         => $status,
			'url'            => admin_url( 'admin.php?page=shojaei-seo&tab=oos' ),
		);
	}

	/**
	 * Candidates / permanent OOS pool for redirect decisions (SQL only — no similarity probe).
	 *
	 * Probing replacements on every dashboard load was O(n×m) and could take tens of seconds.
	 *
	 * @param int $scan_limit Unused (kept for BC).
	 * @return array
	 */
	public static function count_suggested_redirects( int $scan_limit = 50 ): array {
		global $wpdb;
		$table = Shojaei_SEO_Helpers::oos_table();
		unset( $scan_limit );

		$candidates = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE status = 'candidate_redirect'" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);
		$pool = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table} WHERE status IN ('candidate_redirect','needs_manual','permanent_oos')" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		);

		$status = 'safe';
		if ( $candidates >= 10 ) {
			$status = 'action';
		} elseif ( $candidates > 0 ) {
			$status = 'warning';
		}

		return array(
			'count'      => $candidates,
			'scanned'    => 0,
			'total_pool' => $pool,
			'label'      => sprintf(
				/* translators: 1: candidates, 2: pool */
				__( '%1$d کاندید ریدایرکت در صف (از %2$d محصول دائم/کاندید)', 'shojaei-seo-for-woo' ),
				$candidates,
				$pool
			),
			'status'     => $status,
			'url'        => admin_url( 'admin.php?page=shojaei-seo&tab=simulate' ),
		);
	}

	/**
	 * Recent failed jobs / dry-run apply errors.
	 *
	 * @param int $limit Limit.
	 * @return array{count:int,items:array,status:string,label:string}
	 */
	public static function recent_errors( int $limit = 5 ): array {
		$items = array();

		if ( class_exists( 'Shojaei_SEO_Jobs' ) ) {
			global $wpdb;
			$table = Shojaei_SEO_Jobs::table();
			$since = gmdate( 'Y-m-d H:i:s', time() - WEEK_IN_SECONDS );
			$rows  = $wpdb->get_results(
				$wpdb->prepare(
					"SELECT job_key, type, message, last_error, updated_at, failed
					FROM {$table}
					WHERE status = %s AND updated_at >= %s
					ORDER BY updated_at DESC
					LIMIT %d",
					Shojaei_SEO_Jobs::STATUS_FAILED,
					$since,
					$limit
				)
			);
			foreach ( $rows ?: array() as $row ) {
				$items[] = array(
					'type'    => 'job',
					'title'   => (string) $row->type,
					'message' => (string) ( $row->last_error ?: $row->message ),
					'when'    => (string) $row->updated_at,
					'url'     => admin_url( 'admin.php?page=shojaei-seo&tab=settings#shojaei-performance' ),
				);
			}
		}

		$count  = count( $items );
		$status = $count > 0 ? ( $count >= 3 ? 'error' : 'warning' ) : 'safe';

		return array(
			'count'  => $count,
			'items'  => $items,
			'status' => $status,
			'label'  => sprintf(
				/* translators: %d: errors */
				__( '%d خطا در پردازش اخیر', 'shojaei-seo-for-woo' ),
				$count
			),
			'url'    => admin_url( 'admin.php?page=shojaei-seo&tab=settings#shojaei-performance' ),
		);
	}

	/**
	 * Latest applied batches that can still be undone.
	 *
	 * @param int $limit Limit.
	 * @return array{count:int,items:array,status:string,label:string}
	 */
	public static function recent_undo_batches( int $limit = 5 ): array {
		if ( ! class_exists( 'Shojaei_SEO_Revert_Log' ) ) {
			return array(
				'count'  => 0,
				'items'  => array(),
				'status' => 'safe',
				'label'  => __( 'هنوز batch قابل Undo وجود ندارد', 'shojaei-seo-for-woo' ),
				'url'    => admin_url( 'admin.php?page=shojaei-seo&tab=simulate' ),
			);
		}

		global $wpdb;
		$table = Shojaei_SEO_Revert_Log::table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT batch_id, COUNT(*) AS cnt, MAX(created_at) AS last_at, MAX(action) AS sample_action
				FROM {$table}
				WHERE mode = 'applied' AND is_reverted = 0
				GROUP BY batch_id
				ORDER BY last_at DESC
				LIMIT %d",
				$limit
			)
		);

		$items = array();
		foreach ( $rows ?: array() as $row ) {
			$items[] = array(
				'batch_id' => (string) $row->batch_id,
				'count'    => (int) $row->cnt,
				'action'   => Shojaei_SEO_Revert_Log::action_label( (string) $row->sample_action ),
				'when'     => (string) $row->last_at,
				'url'      => admin_url( 'admin.php?page=shojaei-seo&tab=simulate' ),
			);
		}

		return array(
			'count'  => count( $items ),
			'items'  => $items,
			'status' => count( $items ) > 0 ? 'safe' : 'safe',
			'label'  => sprintf(
				/* translators: %d: batches */
				__( '%d دسته قابل بازگشت (Undo)', 'shojaei-seo-for-woo' ),
				count( $items )
			),
			'url'    => admin_url( 'admin.php?page=shojaei-seo&tab=simulate' ),
		);
	}

	/**
	 * Overall board status.
	 *
	 * @param array $action Action needed.
	 * @param array $suggested Suggested.
	 * @param array $errors Errors.
	 * @return array{code:string,label:string,tone:string}
	 */
	public static function overall_status( array $action, array $suggested, array $errors ): array {
		if ( ( $errors['status'] ?? '' ) === 'error' || (int) ( $errors['count'] ?? 0 ) >= 3 ) {
			return array(
				'code'  => 'error',
				'label' => __( 'خطا — پردازش پس‌زمینه نیاز به بررسی دارد', 'shojaei-seo-for-woo' ),
				'tone'  => 'error',
			);
		}
		if ( ( $action['status'] ?? '' ) === 'action' || (int) ( $action['manual'] ?? 0 ) > 0 ) {
			return array(
				'code'  => 'action',
				'label' => __( 'نیازمند اقدام — صف تصمیم را باز کنید', 'shojaei-seo-for-woo' ),
				'tone'  => 'action',
			);
		}
		if ( ( $action['status'] ?? '' ) === 'warning' || ( $suggested['status'] ?? '' ) === 'warning' || ( $errors['status'] ?? '' ) === 'warning' ) {
			return array(
				'code'  => 'warning',
				'label' => __( 'هشدار — چند مورد برای بررسی وجود دارد', 'shojaei-seo-for-woo' ),
				'tone'  => 'warning',
			);
		}
		return array(
			'code'  => 'safe',
			'label' => __( 'امن — صف عملیات سبک است', 'shojaei-seo-for-woo' ),
			'tone'  => 'safe',
		);
	}

	/**
	 * Action cards for the dashboard.
	 *
	 * @param array $action Action.
	 * @param array $suggested Suggested.
	 * @param array $errors Errors.
	 * @param array $undo Undo.
	 * @param bool  $dry_run Dry-run on.
	 * @param int   $scan_pending Pending scan.
	 * @return array
	 */
	public static function build_action_cards( array $action, array $suggested, array $errors, array $undo, bool $dry_run, int $scan_pending ): array {
		$cards = array();

		$cards[] = array(
			'id'          => 'action_needed',
			'tone'        => $action['status'],
			'icon'        => 'dashicons-warning',
			'title'       => __( 'محصولات نیازمند اقدام', 'shojaei-seo-for-woo' ),
			'count'       => (int) ( $action['tracked'] ?? $action['count'] ?? 0 ),
			'description' => $action['label'],
			'meta'        => sprintf(
				/* translators: 1: over, 2: days, 3: candidates, 4: manual */
				__( 'بالای %2$d روز: %1$d · کاندید: %3$d · دستی: %4$d', 'shojaei-seo-for-woo' ),
				(int) $action['over_threshold'],
				(int) ( $action['threshold'] ?? 30 ),
				(int) $action['candidates'],
				(int) $action['manual']
			),
			'cta_label'   => __( 'باز کردن صف تصمیم', 'shojaei-seo-for-woo' ),
			'cta_url'     => $action['url'],
		);

		$cards[] = array(
			'id'          => 'suggested',
			'tone'        => $suggested['status'],
			'icon'        => 'dashicons-migrate',
			'title'       => __( 'کاندیدهای ریدایرکت', 'shojaei-seo-for-woo' ),
			'count'       => (int) $suggested['count'],
			'description' => $suggested['label'],
			'meta'        => __( 'مقصد مشابه در Dry-Run / صف موجودی محاسبه می‌شود — نه هنگام باز شدن داشبورد', 'shojaei-seo-for-woo' ),
			'cta_label'   => __( 'شروع Dry-Run', 'shojaei-seo-for-woo' ),
			'cta_url'     => $suggested['url'],
		);

		$cards[] = array(
			'id'          => 'errors',
			'tone'        => $errors['status'],
			'icon'        => 'dashicons-dismiss',
			'title'       => __( 'خطاهای اخیر', 'shojaei-seo-for-woo' ),
			'count'       => (int) $errors['count'],
			'description' => $errors['label'],
			'meta'        => ! empty( $errors['items'][0]['message'] )
				? (string) $errors['items'][0]['message']
				: __( 'خطای فعالی در هفته اخیر نیست', 'shojaei-seo-for-woo' ),
			'cta_label'   => __( 'مشاهده صف Job', 'shojaei-seo-for-woo' ),
			'cta_url'     => $errors['url'],
		);

		$cards[] = array(
			'id'          => 'undo',
			'tone'        => 'safe',
			'icon'        => 'dashicons-undo',
			'title'       => __( 'قابلیت Undo', 'shojaei-seo-for-woo' ),
			'count'       => (int) $undo['count'],
			'description' => $undo['label'],
			'meta'        => ! empty( $undo['items'][0] )
				? sprintf(
					/* translators: 1: action, 2: count */
					__( 'آخرین: %1$s (%2$d مورد)', 'shojaei-seo-for-woo' ),
					$undo['items'][0]['action'],
					$undo['items'][0]['count']
				)
				: __( 'پس از اعمال واقعی، دسته‌ها اینجا می‌آیند', 'shojaei-seo-for-woo' ),
			'cta_label'   => __( 'پیش‌نمایش Undo', 'shojaei-seo-for-woo' ),
			'cta_url'     => $undo['url'],
		);

		if ( $scan_pending > 0 ) {
			array_unshift(
				$cards,
				array(
					'id'          => 'scan',
					'tone'        => 'warning',
					'icon'        => 'dashicons-update',
					'title'       => __( 'اسکن موجودی در حال اجرا', 'shojaei-seo-for-woo' ),
					'count'       => $scan_pending,
					'description' => __( 'محصولات باقی‌مانده در صف اسکن اولیه', 'shojaei-seo-for-woo' ),
					'meta'        => __( 'صفحه را باز نگه دارید یا منتظر cron بمانید', 'shojaei-seo-for-woo' ),
					'cta_label'   => __( 'تازه‌سازی', 'shojaei-seo-for-woo' ),
					'cta_url'     => admin_url( 'admin.php?page=shojaei-seo&tab=dashboard' ),
				)
			);
		}

		if ( $dry_run ) {
			$cards[] = array(
				'id'          => 'dry_run',
				'tone'        => 'safe',
				'icon'        => 'dashicons-visibility',
				'title'       => __( 'Dry-Run فعال است', 'shojaei-seo-for-woo' ),
				'count'       => '✓',
				'description' => __( 'اتوماسیون فقط پیشنهاد می‌دهد — مناسب شروع امن', 'shojaei-seo-for-woo' ),
				'meta'        => __( 'برای اعمال انبوه از تب Dry-Run استفاده کنید', 'shojaei-seo-for-woo' ),
				'cta_label'   => __( 'استودیوی شبیه‌سازی', 'shojaei-seo-for-woo' ),
				'cta_url'     => admin_url( 'admin.php?page=shojaei-seo&tab=simulate' ),
			);
		}

		if ( class_exists( 'Shojaei_SEO_Integration' ) && Shojaei_SEO_Integration::has_primary_seo_plugin() ) {
			$cards[] = array(
				'id'          => 'integration',
				'tone'        => 'safe',
				'icon'        => 'dashicons-admin-plugins',
				'title'       => __( 'همزیستی با افزونه SEO', 'shojaei-seo-for-woo' ),
				'count'       => '✓',
				'description' => sprintf(
					/* translators: %s: plugin names */
					__( 'تشخیص: %s — Meta و Product به آن‌ها واگذار شده', 'shojaei-seo-for-woo' ),
					Shojaei_SEO_Integration::detected_labels()
				),
				'meta'        => Shojaei_SEO_Integration::schema_mode_label(),
				'cta_label'   => __( 'سیاست یکپارچگی', 'shojaei-seo-for-woo' ),
				'cta_url'     => admin_url( 'admin.php?page=shojaei-seo&tab=settings#shojaei-integration' ),
			);
		}

		if ( class_exists( 'Shojaei_SEO_Canonical' ) && Shojaei_SEO_Canonical::is_enabled() ) {
			$cards[] = array(
				'id'          => 'variation_canonical',
				'tone'        => 'safe',
				'icon'        => 'dashicons-admin-links',
				'title'       => __( 'Canonical متغیرها', 'shojaei-seo-for-woo' ),
				'count'       => '✓',
				'description' => __( 'حالت‌های رنگ/سایز به آدرس محصول والد جمع می‌شوند', 'shojaei-seo-for-woo' ),
				'meta'        => __( 'با Rank Math / Yoast تداخل ندارد — canonical آن‌ها فیلتر می‌شود', 'shojaei-seo-for-woo' ),
				'cta_label'   => __( 'تنظیمات لایه', 'shojaei-seo-for-woo' ),
				'cta_url'     => admin_url( 'admin.php?page=shojaei-seo&tab=settings' ),
			);
		}

		if ( 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_slug_tools_enabled', 'yes' ) ) {
			$cards[] = array(
				'id'          => 'slug_tools',
				'tone'        => 'safe',
				'icon'        => 'dashicons-editor-code',
				'title'       => __( 'نامک و ریدایرکت اسلاگ', 'shojaei-seo-for-woo' ),
				'count'       => '✓',
				'description' => __( 'فینگلیش برای محصولات جدید + ۳۰۱ خودکار هنگام تغییر نامک', 'shojaei-seo-for-woo' ),
				'meta'        => __( 'محصولات قدیمی انبوه‌بازنویسی نمی‌شوند', 'shojaei-seo-for-woo' ),
				'cta_label'   => __( 'عملیات → نامک', 'shojaei-seo-for-woo' ),
				'cta_url'     => admin_url( 'admin.php?page=shojaei-seo&tab=slugs' ),
			);
		}

		if ( class_exists( 'Shojaei_SEO_Impact' ) ) {
			$health = Shojaei_SEO_Impact::compute_health_cached();
			$cards[] = array(
				'id'          => 'impact',
				'tone'        => (string) ( $health['tone'] ?? 'safe' ),
				'icon'        => 'dashicons-chart-pie',
				'title'       => __( 'سلامت عملیات', 'shojaei-seo-for-woo' ),
				'count'       => (int) ( $health['score'] ?? 0 ) . '%',
				'description' => (string) ( $health['summary'] ?? '' ),
				'meta'        => __( 'آمار ۳۰۱/۳۰۲/۴۱۰ و روند ۳۰ روزه', 'shojaei-seo-for-woo' ),
				'cta_label'   => __( 'اثر و آمار', 'shojaei-seo-for-woo' ),
				'cta_url'     => admin_url( 'admin.php?page=shojaei-seo&tab=impact' ),
			);
		}

		return $cards;
	}

	/**
	 * Ordered next steps for the operator.
	 *
	 * @param array $action Action.
	 * @param array $suggested Suggested.
	 * @param array $errors Errors.
	 * @param bool  $dry_run Dry-run.
	 * @param int   $scan_pending Scan.
	 * @return array<int,array{text:string,url:string}>
	 */
	public static function next_steps( array $action, array $suggested, array $errors, bool $dry_run, int $scan_pending ): array {
		$steps = array();

		if ( $scan_pending > 0 ) {
			$steps[] = array(
				'text' => __( 'منتظر بمانید تا اسکن موجودی تمام شود', 'shojaei-seo-for-woo' ),
				'url'  => admin_url( 'admin.php?page=shojaei-seo&tab=dashboard' ),
			);
		}
		if ( (int) ( $errors['count'] ?? 0 ) > 0 ) {
			$steps[] = array(
				'text' => __( 'خطاهای Job را در تنظیمات عملکرد بررسی کنید', 'shojaei-seo-for-woo' ),
				'url'  => admin_url( 'admin.php?page=shojaei-seo&tab=settings#shojaei-performance' ),
			);
		}
		if ( (int) ( $action['manual'] ?? 0 ) > 0 ) {
			$steps[] = array(
				'text' => __( 'محصولات قفل‌شده (Page Value) را دستی تصمیم بگیرید', 'shojaei-seo-for-woo' ),
				'url'  => admin_url( 'admin.php?page=shojaei-seo&tab=oos&oos_status=needs_manual' ),
			);
		}
		if ( (int) ( $suggested['count'] ?? 0 ) > 0 ) {
			$steps[] = array(
				'text' => $dry_run
					? __( 'ریدایرکت‌های پیشنهادی را Dry-Run کنید، بعد از پیش‌نمایش اجرا کنید', 'shojaei-seo-for-woo' )
					: __( 'قبل از اعمال انبوه یک Dry-Run بگیرید', 'shojaei-seo-for-woo' ),
				'url'  => admin_url( 'admin.php?page=shojaei-seo&tab=simulate' ),
			);
		} elseif ( (int) ( $action['tracked'] ?? $action['over_threshold'] ?? 0 ) > 0 ) {
			$steps[] = array(
				'text' => __( 'صف ناموجودی را باز کنید و برای محصولات طولانی‌مدت تصمیم بگیرید', 'shojaei-seo-for-woo' ),
				'url'  => admin_url( 'admin.php?page=shojaei-seo&tab=oos' ),
			);
		}
		if ( empty( $steps ) ) {
			$steps[] = array(
				'text' => __( 'وضعیت خوب است — در صورت نیاز یک محصول را در تب تست بررسی کنید', 'shojaei-seo-for-woo' ),
				'url'  => admin_url( 'admin.php?page=shojaei-seo&tab=test' ),
			);
			if ( 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_indexnow_enabled', 'yes' ) ) {
				$steps[] = array(
					'text' => __( 'IndexNow روشن است؛ برای GSC اگر سرور بلاک است از هاست کمک بگیرید', 'shojaei-seo-for-woo' ),
					'url'  => admin_url( 'admin.php?page=shojaei-seo&tab=education' ),
				);
			}
		}

		return $steps;
	}

	/**
	 * Human status badge labels.
	 *
	 * @param string $tone Tone code.
	 */
	public static function tone_label( string $tone ): string {
		$map = array(
			'safe'    => __( 'امن', 'shojaei-seo-for-woo' ),
			'warning' => __( 'هشدار', 'shojaei-seo-for-woo' ),
			'action'  => __( 'نیازمند اقدام', 'shojaei-seo-for-woo' ),
			'error'   => __( 'خطا', 'shojaei-seo-for-woo' ),
		);
		return $map[ $tone ] ?? $tone;
	}
}
