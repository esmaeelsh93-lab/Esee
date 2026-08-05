<?php
/**
 * مدیریت جدول دیتابیس و کوئری‌های آماری افزونه.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CVS_DB {

	/**
	 * نام کامل جدول (با پیشوند وردپرس) را برمی‌گرداند.
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . CVS_TABLE_NAME;
	}

	/**
	 * ساخت یا به‌روزرسانی جدول دیتابیس با استفاده از dbDelta.
	 */
	public static function create_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = self::table();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			visit_date DATE NOT NULL,
			visit_time DATETIME NOT NULL,
			source_key VARCHAR(60) NOT NULL DEFAULT 'direct',
			source_label VARCHAR(150) NOT NULL DEFAULT '',
			referrer_host VARCHAR(255) DEFAULT NULL,
			referrer_url TEXT DEFAULT NULL,
			request_path VARCHAR(500) DEFAULT NULL,
			ip_hash VARCHAR(64) DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY visit_date (visit_date),
			KEY source_key (source_key)
		) {$charset_collate};";

		dbDelta( $sql );
	}

	/**
	 * ثبت یک ورودی جدید در دیتابیس.
	 *
	 * @param array $data داده‌های بازدید.
	 * @return int|false شناسه ردیف درج‌شده یا false در صورت خطا.
	 */
	public static function insert_visit( $data ) {
		global $wpdb;

		$now = current_time( 'mysql' );

		$inserted = $wpdb->insert(
			self::table(),
			array(
				'visit_date'    => current_time( 'Y-m-d' ),
				'visit_time'    => $now,
				'source_key'    => $data['source_key'],
				'source_label'  => $data['source_label'],
				'referrer_host' => isset( $data['referrer_host'] ) ? $data['referrer_host'] : null,
				'referrer_url'  => isset( $data['referrer_url'] ) ? $data['referrer_url'] : null,
				'request_path'  => isset( $data['request_path'] ) ? $data['request_path'] : null,
				'ip_hash'       => isset( $data['ip_hash'] ) ? $data['ip_hash'] : null,
			),
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return $inserted ? $wpdb->insert_id : false;
	}

	/**
	 * مجموع کل ورودی‌ها در یک بازه تاریخی.
	 */
	public static function get_total( $from, $to ) {
		global $wpdb;
		$table = self::table();

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE visit_date BETWEEN %s AND %s",
				$from,
				$to
			)
		);
	}

	/**
	 * تفکیک تعداد بازدید بر اساس منبع ارجاع در یک بازه تاریخی.
	 *
	 * @return array لیستی از آبجکت‌ها با source_key, source_label, total
	 */
	public static function get_breakdown_by_source( $from, $to ) {
		global $wpdb;
		$table = self::table();

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT source_key, source_label, COUNT(*) AS total
				FROM {$table}
				WHERE visit_date BETWEEN %s AND %s
				GROUP BY source_key, source_label
				ORDER BY total DESC",
				$from,
				$to
			)
		);

		return $results ? $results : array();
	}

	/**
	 * سری روزانه‌ی تعداد بازدید به تفکیک منبع، برای رسم نمودار.
	 *
	 * @return array [ 'dates' => [...], 'sources' => [ key => [ 'label' => ..., 'data' => [date=>count] ] ] ]
	 */
	public static function get_daily_series( $from, $to, $top_sources = array() ) {
		global $wpdb;
		$table = self::table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT visit_date, source_key, source_label, COUNT(*) AS total
				FROM {$table}
				WHERE visit_date BETWEEN %s AND %s
				GROUP BY visit_date, source_key, source_label
				ORDER BY visit_date ASC",
				$from,
				$to
			)
		);

		$dates = self::get_date_range_list( $from, $to );
		$series = array();

		foreach ( $rows as $row ) {
			if ( ! isset( $series[ $row->source_key ] ) ) {
				$series[ $row->source_key ] = array(
					'label' => $row->source_label,
					'data'  => array_fill_keys( $dates, 0 ),
				);
			}
			$series[ $row->source_key ]['data'][ $row->visit_date ] = (int) $row->total;
		}

		return array(
			'dates'   => $dates,
			'sources' => $series,
		);
	}

	/**
	 * فهرست تاریخ‌های موجود بین دو بازه (شامل ابتدا و انتها) را برمی‌گرداند.
	 */
	public static function get_date_range_list( $from, $to ) {
		$dates = array();
		try {
			$start = new DateTime( $from );
			$end   = new DateTime( $to );
		} catch ( Exception $e ) {
			return $dates;
		}

		if ( $start > $end ) {
			return $dates;
		}

		$interval = new DateInterval( 'P1D' );
		$end->modify( '+1 day' );
		$period = new DatePeriod( $start, $interval, $end );

		foreach ( $period as $date ) {
			$dates[] = $date->format( 'Y-m-d' );
		}

		return $dates;
	}

	/**
	 * جدول تفکیکی روز به روز به همراه تعداد کل و منابع اصلی برای جدول گزارش.
	 *
	 * @return array لیستی مرتب‌شده بر اساس تاریخ نزولی؛ هر آیتم شامل تاریخ، کل، و شمارش هر منبع است.
	 */
	public static function get_daily_breakdown_table( $from, $to ) {
		global $wpdb;
		$table = self::table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT visit_date, source_key, source_label, COUNT(*) AS total
				FROM {$table}
				WHERE visit_date BETWEEN %s AND %s
				GROUP BY visit_date, source_key, source_label",
				$from,
				$to
			)
		);

		$by_date = array();
		foreach ( $rows as $row ) {
			if ( ! isset( $by_date[ $row->visit_date ] ) ) {
				$by_date[ $row->visit_date ] = array(
					'date'    => $row->visit_date,
					'total'   => 0,
					'sources' => array(),
				);
			}
			$by_date[ $row->visit_date ]['total'] += (int) $row->total;
			$by_date[ $row->visit_date ]['sources'][ $row->source_key ] = array(
				'label' => $row->source_label,
				'total' => (int) $row->total,
			);
		}

		krsort( $by_date );

		return array_values( $by_date );
	}

	/**
	 * حذف کامل تمام رکوردهای آماری (بازنشانی آمار).
	 */
	public static function truncate_all() {
		global $wpdb;
		$table = self::table();
		$wpdb->query( "TRUNCATE TABLE {$table}" );
	}

	/**
	 * حذف رکوردهای قدیمی‌تر از تعداد روز مشخص‌شده.
	 */
	public static function purge_older_than( $days ) {
		global $wpdb;
		$table = self::table();

		if ( $days <= 0 ) {
			return;
		}

		$threshold = gmdate( 'Y-m-d', strtotime( '-' . (int) $days . ' days' ) );

		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} WHERE visit_date < %s",
				$threshold
			)
		);
	}

	/**
	 * اجرای پاک‌سازی زمان‌بندی‌شده روزانه بر اساس تنظیمات نگهداری داده.
	 */
	public static function run_scheduled_cleanup() {
		$settings = CVS_Admin::get_settings();
		$days     = isset( $settings['retention_days'] ) ? (int) $settings['retention_days'] : 0;
		if ( $days > 0 ) {
			self::purge_older_than( $days );
		}
	}

	/**
	 * محاسبه‌ی بازه‌ی زمانی متناظر قبلی (با طول برابر) برای مقایسه‌ی درصد رشد.
	 *
	 * @return array [ prev_from, prev_to ]
	 */
	public static function get_previous_range( $from, $to ) {
		try {
			$start = new DateTime( $from );
			$end   = new DateTime( $to );
		} catch ( Exception $e ) {
			return array( $from, $to );
		}

		$days = (int) $start->diff( $end )->days + 1;

		$prev_end   = clone $start;
		$prev_end->modify( '-1 day' );
		$prev_start = clone $prev_end;
		$prev_start->modify( '-' . ( $days - 1 ) . ' days' );

		return array( $prev_start->format( 'Y-m-d' ), $prev_end->format( 'Y-m-d' ) );
	}

	/**
	 * تعداد بازدید یک منبع خاص در یک بازه‌ی زمانی.
	 */
	public static function get_source_total( $from, $to, $source_key ) {
		global $wpdb;
		$table = self::table();

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE visit_date BETWEEN %s AND %s AND source_key = %s",
				$from,
				$to,
				$source_key
			)
		);
	}

	/**
	 * فهرست ارجاع‌دهنده‌های ناشناخته (سایر) برای نمایش در گزارش پیشرفته.
	 */
	public static function get_other_referrers( $from, $to, $limit = 10 ) {
		global $wpdb;
		$table = self::table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT referrer_host, COUNT(*) AS total
				FROM {$table}
				WHERE visit_date BETWEEN %s AND %s AND source_key = 'other' AND referrer_host IS NOT NULL AND referrer_host != ''
				GROUP BY referrer_host
				ORDER BY total DESC
				LIMIT %d",
				$from,
				$to,
				$limit
			)
		);

		return $rows ? $rows : array();
	}
}
