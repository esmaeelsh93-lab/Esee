<?php
/**
 * لایه‌ی دسترسی داده برای بازدیدها، نشست‌ها و خلاصه‌های روزانه.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CVS_DB {

	/**
	 * نام کامل یکی از جدول‌های افزونه را برمی‌گرداند.
	 *
	 * @param string $type visits|sessions|daily|city
	 * @return string
	 */
	public static function table( $type = 'visits' ) {
		global $wpdb;

		$tables = array(
			'visits'   => CVS_TABLE_NAME,
			'sessions' => CVS_SESSIONS_TABLE_NAME,
			'daily'    => CVS_DAILY_SUMMARY_TABLE_NAME,
			'city'     => CVS_CITY_DAILY_TABLE_NAME,
		);

		$name = isset( $tables[ $type ] ) ? $tables[ $type ] : $tables['visits'];
		return $wpdb->prefix . $name;
	}

	/**
	 * ساخت و ارتقای جداول با dbDelta؛ جدول قدیمی cvs_visits حفظ و توسعه داده می‌شود.
	 */
	public static function create_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$visits  = self::table( 'visits' );
		$sessions = self::table( 'sessions' );
		$daily   = self::table( 'daily' );
		$city    = self::table( 'city' );

		$sql_visits = "CREATE TABLE {$visits} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			event_id VARCHAR(64) DEFAULT NULL,
			session_id VARCHAR(64) DEFAULT NULL,
			visitor_hash VARCHAR(64) DEFAULT NULL,
			visit_date DATE NOT NULL,
			visit_time DATETIME NOT NULL,
			source_key VARCHAR(60) NOT NULL DEFAULT 'direct',
			source_label VARCHAR(150) NOT NULL DEFAULT '',
			referrer_host VARCHAR(255) DEFAULT NULL,
			referrer_url TEXT DEFAULT NULL,
			page_url TEXT DEFAULT NULL,
			request_path VARCHAR(500) DEFAULT NULL,
			utm_source VARCHAR(150) DEFAULT NULL,
			utm_medium VARCHAR(150) DEFAULT NULL,
			utm_campaign VARCHAR(190) DEFAULT NULL,
			device_type VARCHAR(20) NOT NULL DEFAULT 'desktop',
			browser VARCHAR(60) DEFAULT NULL,
			os VARCHAR(60) DEFAULT NULL,
			country VARCHAR(2) DEFAULT NULL,
			city VARCHAR(120) DEFAULT NULL,
			ip_hash VARCHAR(64) DEFAULT NULL,
			is_bot TINYINT(1) NOT NULL DEFAULT 0,
			PRIMARY KEY  (id),
			UNIQUE KEY event_id (event_id),
			KEY visit_date (visit_date),
			KEY visit_time (visit_time),
			KEY session_id (session_id),
			KEY visitor_hash (visitor_hash),
			KEY source_key (source_key)
		) {$charset};";

		$sql_sessions = "CREATE TABLE {$sessions} (
			session_id VARCHAR(64) NOT NULL,
			visitor_hash VARCHAR(64) DEFAULT NULL,
			entry_page TEXT DEFAULT NULL,
			exit_page TEXT DEFAULT NULL,
			source_key VARCHAR(60) NOT NULL DEFAULT 'direct',
			page_count INT UNSIGNED NOT NULL DEFAULT 1,
			duration_seconds INT UNSIGNED NOT NULL DEFAULT 0,
			device_type VARCHAR(20) NOT NULL DEFAULT 'desktop',
			browser VARCHAR(60) DEFAULT NULL,
			os VARCHAR(60) DEFAULT NULL,
			country VARCHAR(2) DEFAULT NULL,
			city VARCHAR(120) DEFAULT NULL,
			is_converted TINYINT(1) NOT NULL DEFAULT 0,
			first_seen DATETIME NOT NULL,
			last_seen DATETIME NOT NULL,
			PRIMARY KEY  (session_id),
			KEY first_seen (first_seen),
			KEY last_seen (last_seen),
			KEY visitor_hash (visitor_hash)
		) {$charset};";

		$sql_daily = "CREATE TABLE {$daily} (
			summary_date DATE NOT NULL,
			total_pageviews BIGINT UNSIGNED NOT NULL DEFAULT 0,
			unique_visitors BIGINT UNSIGNED NOT NULL DEFAULT 0,
			sessions_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
			bounce_rate DECIMAL(5,2) NOT NULL DEFAULT 0,
			avg_duration INT UNSIGNED NOT NULL DEFAULT 0,
			total_sales DECIMAL(18,2) NOT NULL DEFAULT 0,
			orders_count BIGINT UNSIGNED NOT NULL DEFAULT 0,
			is_finalized TINYINT(1) NOT NULL DEFAULT 0,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (summary_date)
		) {$charset};";

		$sql_city = "CREATE TABLE {$city} (
			summary_date DATE NOT NULL,
			country VARCHAR(2) NOT NULL DEFAULT '',
			city VARCHAR(120) NOT NULL DEFAULT '',
			visits BIGINT UNSIGNED NOT NULL DEFAULT 0,
			unique_visitors BIGINT UNSIGNED NOT NULL DEFAULT 0,
			PRIMARY KEY  (summary_date,country,city),
			KEY summary_date (summary_date),
			KEY city (city)
		) {$charset};";

		dbDelta( $sql_visits );
		dbDelta( $sql_sessions );
		dbDelta( $sql_daily );
		dbDelta( $sql_city );
	}

	/**
	 * ثبت بازدید خام و به‌روزرسانی نشست مربوط به آن.
	 *
	 * @param array $data داده‌ی پاک‌سازی‌شده‌ی بازدید.
	 * @return int|false
	 */
	public static function insert_visit( $data ) {
		global $wpdb;

		$now  = current_time( 'mysql' );
		$date = current_time( 'Y-m-d' );

		$row = wp_parse_args(
			$data,
			array(
				'event_id'      => null,
				'session_id'    => null,
				'visitor_hash'  => null,
				'source_key'    => 'direct',
				'source_label'  => 'مستقیم',
				'referrer_host' => null,
				'referrer_url'  => null,
				'page_url'      => null,
				'request_path'  => null,
				'utm_source'    => null,
				'utm_medium'    => null,
				'utm_campaign'  => null,
				'device_type'   => 'desktop',
				'browser'       => null,
				'os'            => null,
				'country'       => null,
				'city'          => null,
				'ip_hash'       => null,
				'is_bot'        => 0,
			)
		);

		$inserted = $wpdb->insert(
			self::table( 'visits' ),
			array(
				'event_id'      => $row['event_id'],
				'session_id'    => $row['session_id'],
				'visitor_hash'  => $row['visitor_hash'],
				'visit_date'    => $date,
				'visit_time'    => $now,
				'source_key'    => $row['source_key'],
				'source_label'  => $row['source_label'],
				'referrer_host' => $row['referrer_host'],
				'referrer_url'  => $row['referrer_url'],
				'page_url'      => $row['page_url'],
				'request_path'  => $row['request_path'],
				'utm_source'    => $row['utm_source'],
				'utm_medium'    => $row['utm_medium'],
				'utm_campaign'  => $row['utm_campaign'],
				'device_type'   => $row['device_type'],
				'browser'       => $row['browser'],
				'os'            => $row['os'],
				'country'       => $row['country'],
				'city'          => $row['city'],
				'ip_hash'       => $row['ip_hash'],
				'is_bot'        => (int) $row['is_bot'],
			),
			array(
				'%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
				'%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s',
				'%s', '%s', '%d',
			)
		);

		if ( ! $inserted ) {
			return false;
		}

		if ( ! empty( $row['session_id'] ) ) {
			self::upsert_session( $row, $now );
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * نشست را به شکل اتمیک ایجاد یا به‌روزرسانی می‌کند.
	 */
	private static function upsert_session( $row, $now ) {
		global $wpdb;

		$table = self::table( 'sessions' );
		$entry = $row['page_url'] ? $row['page_url'] : $row['request_path'];

		$sql = "INSERT INTO {$table}
			(session_id, visitor_hash, entry_page, exit_page, source_key, page_count, duration_seconds, device_type, browser, os, country, city, first_seen, last_seen)
			VALUES (%s, %s, %s, %s, %s, 1, 0, %s, %s, %s, %s, %s, %s, %s)
			ON DUPLICATE KEY UPDATE
				exit_page = VALUES(exit_page),
				page_count = page_count + 1,
				last_seen = VALUES(last_seen),
				browser = VALUES(browser),
				os = VALUES(os),
				country = VALUES(country),
				city = VALUES(city)";

		$wpdb->query(
			$wpdb->prepare(
				$sql,
				$row['session_id'],
				$row['visitor_hash'],
				$entry,
				$entry,
				$row['source_key'],
				$row['device_type'],
				$row['browser'],
				$row['os'],
				$row['country'],
				$row['city'],
				$now,
				$now
			)
		);
	}

	/**
	 * به‌روزرسانی مدت حضور هنگام pagehide.
	 */
	public static function update_session_duration( $session_id, $seconds ) {
		global $wpdb;

		$seconds = max( 0, min( DAY_IN_SECONDS, (int) $seconds ) );
		$table   = self::table( 'sessions' );

		return $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET duration_seconds = GREATEST(duration_seconds, %d), last_seen = %s
				WHERE session_id = %s",
				$seconds,
				current_time( 'mysql' ),
				$session_id
			)
		);
	}

	public static function get_total( $from, $to ) {
		global $wpdb;
		$visits = self::table( 'visits' );
		$daily  = self::table( 'daily' );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(metric_value), 0) FROM (
					SELECT summary_date AS metric_date, total_pageviews AS metric_value
					FROM {$daily} WHERE summary_date BETWEEN %s AND %s AND is_finalized = 1
					UNION ALL
					SELECT visit_date AS metric_date, COUNT(*) AS metric_value
					FROM {$visits}
					WHERE visit_date BETWEEN %s AND %s AND is_bot = 0
						AND NOT EXISTS (
							SELECT 1 FROM {$daily} WHERE summary_date = {$visits}.visit_date AND is_finalized = 1
						)
					GROUP BY visit_date
				) AS cvs_total",
				$from,
				$to,
				$from,
				$to
			)
		);
	}

	public static function get_unique_visitors( $from, $to ) {
		global $wpdb;
		$visits = self::table( 'visits' );
		$daily  = self::table( 'daily' );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(metric_value), 0) FROM (
					SELECT summary_date AS metric_date, unique_visitors AS metric_value
					FROM {$daily} WHERE summary_date BETWEEN %s AND %s AND is_finalized = 1
					UNION ALL
					SELECT visit_date AS metric_date, COUNT(DISTINCT visitor_hash) AS metric_value
					FROM {$visits}
					WHERE visit_date BETWEEN %s AND %s AND visitor_hash IS NOT NULL AND is_bot = 0
						AND NOT EXISTS (
							SELECT 1 FROM {$daily} WHERE summary_date = {$visits}.visit_date AND is_finalized = 1
						)
					GROUP BY visit_date
				) AS cvs_unique",
				$from,
				$to,
				$from,
				$to
			)
		);
	}

	public static function get_sessions_count( $from, $to ) {
		global $wpdb;
		$sessions = self::table( 'sessions' );
		$daily    = self::table( 'daily' );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(metric_value), 0) FROM (
					SELECT summary_date AS metric_date, sessions_count AS metric_value
					FROM {$daily} WHERE summary_date BETWEEN %s AND %s AND is_finalized = 1
					UNION ALL
					SELECT DATE(first_seen) AS metric_date, COUNT(*) AS metric_value
					FROM {$sessions}
					WHERE first_seen >= CONCAT(%s, ' 00:00:00')
						AND first_seen < DATE_ADD(%s, INTERVAL 1 DAY)
						AND NOT EXISTS (
							SELECT 1 FROM {$daily} WHERE summary_date = DATE({$sessions}.first_seen) AND is_finalized = 1
						)
					GROUP BY DATE(first_seen)
				) AS cvs_sessions_total",
				$from,
				$to,
				$from,
				$to
			)
		);
	}

	public static function get_bounce_rate( $from, $to ) {
		global $wpdb;
		$table = self::table( 'sessions' );

		$value = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(100 * SUM(CASE WHEN page_count = 1 THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0), 0)
				FROM {$table}
				WHERE first_seen >= CONCAT(%s, ' 00:00:00')
					AND first_seen < DATE_ADD(%s, INTERVAL 1 DAY)",
				$from,
				$to
			)
		);

		return round( (float) $value, 1 );
	}

	public static function get_average_duration( $from, $to ) {
		global $wpdb;
		$table = self::table( 'sessions' );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(AVG(duration_seconds), 0) FROM {$table}
				WHERE first_seen >= CONCAT(%s, ' 00:00:00')
					AND first_seen < DATE_ADD(%s, INTERVAL 1 DAY)",
				$from,
				$to
			)
		);
	}

	public static function get_online_count() {
		global $wpdb;
		$table = self::table( 'sessions' );
		$since = wp_date( 'Y-m-d H:i:s', time() - ( 5 * MINUTE_IN_SECONDS ), wp_timezone() );

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE last_seen >= %s", $since )
		);
	}

	public static function get_recent_sessions( $from, $to, $limit = 50 ) {
		global $wpdb;
		$table = self::table( 'sessions' );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT session_id, entry_page, exit_page, source_key, page_count, duration_seconds,
					device_type, browser, os, country, city, first_seen, last_seen
				FROM {$table}
				WHERE first_seen >= CONCAT(%s, ' 00:00:00')
					AND first_seen < DATE_ADD(%s, INTERVAL 1 DAY)
				ORDER BY last_seen DESC
				LIMIT %d",
				$from,
				$to,
				max( 1, min( 200, (int) $limit ) )
			)
		);
	}

	public static function get_top_pages( $from, $to, $limit = 8 ) {
		global $wpdb;
		$table = self::table( 'visits' );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT request_path, COUNT(*) AS total, COUNT(DISTINCT visitor_hash) AS unique_visitors
				FROM {$table}
				WHERE visit_date BETWEEN %s AND %s AND is_bot = 0
				AND request_path IS NOT NULL AND request_path != ''
				GROUP BY request_path
				ORDER BY total DESC
				LIMIT %d",
				$from,
				$to,
				max( 1, min( 100, (int) $limit ) )
			)
		);
	}

	public static function get_breakdown_by_source( $from, $to ) {
		global $wpdb;
		$table = self::table( 'visits' );

		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT source_key, source_label, COUNT(*) AS total,
					COUNT(DISTINCT visitor_hash) AS unique_visitors
				FROM {$table}
				WHERE visit_date BETWEEN %s AND %s AND is_bot = 0
				GROUP BY source_key, source_label
				ORDER BY total DESC",
				$from,
				$to
			)
		);

		return $results ? $results : array();
	}

	public static function get_daily_series( $from, $to, $top_sources = array() ) {
		global $wpdb;
		$table = self::table( 'visits' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT visit_date, source_key, source_label, COUNT(*) AS total
				FROM {$table}
				WHERE visit_date BETWEEN %s AND %s AND is_bot = 0
				GROUP BY visit_date, source_key, source_label
				ORDER BY visit_date ASC",
				$from,
				$to
			)
		);

		$dates  = self::get_date_range_list( $from, $to );
		$series = array();

		foreach ( $rows as $row ) {
			if ( $top_sources && ! in_array( $row->source_key, $top_sources, true ) ) {
				continue;
			}
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

		$end->modify( '+1 day' );
		$period = new DatePeriod( $start, new DateInterval( 'P1D' ), $end );
		foreach ( $period as $date ) {
			$dates[] = $date->format( 'Y-m-d' );
		}

		return $dates;
	}

	public static function get_daily_breakdown_table( $from, $to ) {
		global $wpdb;
		$table = self::table( 'visits' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT visit_date, source_key, source_label, COUNT(*) AS total
				FROM {$table}
				WHERE visit_date BETWEEN %s AND %s AND is_bot = 0
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

	public static function get_city_breakdown( $from, $to, $limit = 100 ) {
		global $wpdb;
		$visits = self::table( 'visits' );
		$city   = self::table( 'city' );
		$daily  = self::table( 'daily' );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT country, city, SUM(visits) AS visits, SUM(unique_visitors) AS unique_visitors
				FROM (
					SELECT c.country, c.city, c.visits, c.unique_visitors
					FROM {$city} c
					INNER JOIN {$daily} d ON d.summary_date = c.summary_date AND d.is_finalized = 1
					WHERE c.summary_date BETWEEN %s AND %s
					UNION ALL
					SELECT COALESCE(country, ''), COALESCE(city, ''), COUNT(*), COUNT(DISTINCT visitor_hash)
					FROM {$visits}
					WHERE visit_date BETWEEN %s AND %s AND is_bot = 0
						AND (country IS NOT NULL OR city IS NOT NULL)
						AND NOT EXISTS (
							SELECT 1 FROM {$daily}
							WHERE summary_date = {$visits}.visit_date AND is_finalized = 1
						)
					GROUP BY country, city
				) AS cvs_geo
				GROUP BY country, city
				ORDER BY visits DESC
				LIMIT %d",
				$from,
				$to,
				$from,
				$to,
				max( 1, min( 500, (int) $limit ) )
			)
		);
	}

	public static function get_sales_totals( $from, $to ) {
		global $wpdb;
		$table = self::table( 'daily' );

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(total_sales), 0) AS total_sales,
					COALESCE(SUM(orders_count), 0) AS orders_count
				FROM {$table} WHERE summary_date BETWEEN %s AND %s",
				$from,
				$to
			)
		);

		return array(
			'total_sales' => $row ? (float) $row->total_sales : 0,
			'orders_count' => $row ? (int) $row->orders_count : 0,
		);
	}

	public static function get_daily_sales( $from, $to ) {
		global $wpdb;
		$table = self::table( 'daily' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT summary_date, total_sales, orders_count
				FROM {$table}
				WHERE summary_date BETWEEN %s AND %s
				ORDER BY summary_date DESC",
				$from,
				$to
			)
		);

		return $rows ? $rows : array();
	}

	/**
	 * تغییر خالص فروش یک روز را با حفظ خلاصه‌ی بازدید اعمال می‌کند.
	 */
	public static function update_daily_sales( $date, $amount_delta, $orders_delta ) {
		global $wpdb;
		$table = self::table( 'daily' );
		$now   = current_time( 'mysql' );

		$wpdb->query(
			$wpdb->prepare(
				"INSERT IGNORE INTO {$table} (summary_date, updated_at) VALUES (%s, %s)",
				$date,
				$now
			)
		);

		return $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table}
				SET total_sales = GREATEST(0, total_sales + %f),
					orders_count = GREATEST(0, CAST(orders_count AS SIGNED) + %d),
					updated_at = %s
				WHERE summary_date = %s",
				(float) $amount_delta,
				(int) $orders_delta,
				$now,
				$date
			)
		);
	}

	/**
	 * خلاصه‌ی روزانه و شهرها را با زمان محلی وردپرس بازسازی می‌کند.
	 */
	public static function aggregate_day( $date ) {
		global $wpdb;

		if ( ! self::is_valid_date( $date ) ) {
			return false;
		}

		$visits   = self::table( 'visits' );
		$sessions = self::table( 'sessions' );
		$daily    = self::table( 'daily' );
		$city     = self::table( 'city' );

		$metrics = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS total_pageviews, COUNT(DISTINCT visitor_hash) AS unique_visitors
				FROM {$visits} WHERE visit_date = %s AND is_bot = 0",
				$date
			)
		);

		$session_metrics = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS sessions_count,
					COALESCE(100 * SUM(CASE WHEN page_count = 1 THEN 1 ELSE 0 END) / NULLIF(COUNT(*), 0), 0) AS bounce_rate,
					COALESCE(AVG(duration_seconds), 0) AS avg_duration
				FROM {$sessions}
				WHERE first_seen >= CONCAT(%s, ' 00:00:00')
					AND first_seen < DATE_ADD(%s, INTERVAL 1 DAY)",
				$date,
				$date
			)
		);

		$existing_sales = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT total_sales, orders_count FROM {$daily} WHERE summary_date = %s",
				$date
			)
		);

		$wpdb->replace(
			$daily,
			array(
				'summary_date'    => $date,
				'total_pageviews' => $metrics ? (int) $metrics->total_pageviews : 0,
				'unique_visitors' => $metrics ? (int) $metrics->unique_visitors : 0,
				'sessions_count'  => $session_metrics ? (int) $session_metrics->sessions_count : 0,
				'bounce_rate'     => $session_metrics ? (float) $session_metrics->bounce_rate : 0,
				'avg_duration'    => $session_metrics ? (int) $session_metrics->avg_duration : 0,
				'total_sales'     => $existing_sales ? (float) $existing_sales->total_sales : 0,
				'orders_count'    => $existing_sales ? (int) $existing_sales->orders_count : 0,
				'is_finalized'    => 1,
				'updated_at'      => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%d', '%f', '%d', '%f', '%d', '%d', '%s' )
		);

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$city} WHERE summary_date = %s", $date ) );
		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$city} (summary_date, country, city, visits, unique_visitors)
				SELECT visit_date, COALESCE(country, ''), COALESCE(city, ''), COUNT(*), COUNT(DISTINCT visitor_hash)
				FROM {$visits}
				WHERE visit_date = %s AND is_bot = 0 AND (country IS NOT NULL OR city IS NOT NULL)
				GROUP BY visit_date, country, city",
				$date
			)
		);

		return true;
	}

	public static function run_scheduled_aggregation() {
		$timezone  = wp_timezone();
		$yesterday = new DateTime( 'yesterday', $timezone );
		self::aggregate_day( $yesterday->format( 'Y-m-d' ) );
	}

	public static function truncate_all() {
		global $wpdb;
		foreach ( array( 'visits', 'sessions', 'daily', 'city' ) as $type ) {
			$table = self::table( $type );
			$wpdb->query( "TRUNCATE TABLE {$table}" );
		}
	}

	public static function purge_older_than( $days ) {
		global $wpdb;
		if ( $days <= 0 ) {
			return;
		}

		$timezone  = wp_timezone();
		$threshold = new DateTime( 'now', $timezone );
		$threshold->modify( '-' . (int) $days . ' days' );
		$date = $threshold->format( 'Y-m-d' );

		$visits   = self::table( 'visits' );
		$sessions = self::table( 'sessions' );
		$daily    = self::table( 'daily' );
		$city     = self::table( 'city' );

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$visits} WHERE visit_date < %s", $date ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$sessions} WHERE last_seen < CONCAT(%s, ' 00:00:00')", $date ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$daily} WHERE summary_date < %s", $date ) );
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$city} WHERE summary_date < %s", $date ) );
	}

	public static function run_scheduled_cleanup() {
		$settings = CVS_Admin::get_settings();
		$days     = isset( $settings['retention_days'] ) ? (int) $settings['retention_days'] : 0;
		if ( $days > 0 ) {
			self::purge_older_than( $days );
		}
	}

	public static function get_previous_range( $from, $to ) {
		try {
			$start = new DateTime( $from );
			$end   = new DateTime( $to );
		} catch ( Exception $e ) {
			return array( $from, $to );
		}

		$days = (int) $start->diff( $end )->days + 1;
		$prev_end = clone $start;
		$prev_end->modify( '-1 day' );
		$prev_start = clone $prev_end;
		$prev_start->modify( '-' . ( $days - 1 ) . ' days' );

		return array( $prev_start->format( 'Y-m-d' ), $prev_end->format( 'Y-m-d' ) );
	}

	public static function get_source_total( $from, $to, $source_key ) {
		global $wpdb;
		$table = self::table( 'visits' );

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table}
				WHERE visit_date BETWEEN %s AND %s AND source_key = %s AND is_bot = 0",
				$from,
				$to,
				$source_key
			)
		);
	}

	public static function get_other_referrers( $from, $to, $limit = 10 ) {
		global $wpdb;
		$table = self::table( 'visits' );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT referrer_host, COUNT(*) AS total
				FROM {$table}
				WHERE visit_date BETWEEN %s AND %s AND source_key = 'other'
					AND referrer_host IS NOT NULL AND referrer_host != '' AND is_bot = 0
				GROUP BY referrer_host
				ORDER BY total DESC
				LIMIT %d",
				$from,
				$to,
				max( 1, min( 100, (int) $limit ) )
			)
		);

		return $rows ? $rows : array();
	}

	private static function is_valid_date( $date ) {
		$parsed = DateTime::createFromFormat( 'Y-m-d', $date );
		return $parsed && $parsed->format( 'Y-m-d' ) === $date;
	}
}
