<?php
/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

/**
 * مدیریت جدول‌های دیتابیس و تمام کوئری‌های آماری افزونه.
 * تمام داده‌ها فقط در دیتابیس خودِ سایت ذخیره می‌شوند و به هیچ سرویس بیرونی ارسال نمی‌شوند.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAW_DB {

	public static function visits_table() {
		global $wpdb;
		return $wpdb->prefix . 'aaw_visits';
	}

	public static function funnel_table() {
		global $wpdb;
		return $wpdb->prefix . 'aaw_funnel_events';
	}

	public static function cart_table() {
		global $wpdb;
		return $wpdb->prefix . 'aaw_cart_snapshots';
	}

	public static function heatmap_table() {
		global $wpdb;
		return $wpdb->prefix . 'aaw_heatmap_events';
	}

	public static function replay_sessions_table() {
		global $wpdb;
		return $wpdb->prefix . 'aaw_replay_sessions';
	}

	public static function replay_events_table() {
		global $wpdb;
		return $wpdb->prefix . 'aaw_replay_events';
	}

	public static function alerts_table() {
		global $wpdb;
		return $wpdb->prefix . 'aaw_alerts';
	}

	/**
	 * ساخت یا به‌روزرسانی تمام جدول‌های دیتابیس افزونه با استفاده از dbDelta.
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();

		$visits = self::visits_table();
		$sql    = "CREATE TABLE {$visits} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id VARCHAR(40) NOT NULL,
			visit_date DATE NOT NULL,
			first_visit_time DATETIME NOT NULL,
			last_visit_time DATETIME NOT NULL,
			pageview_count INT UNSIGNED NOT NULL DEFAULT 1,
			had_interaction TINYINT(1) NOT NULL DEFAULT 0,
			source_key VARCHAR(60) NOT NULL DEFAULT 'direct',
			source_label VARCHAR(150) NOT NULL DEFAULT '',
			referrer_host VARCHAR(255) DEFAULT NULL,
			referrer_url TEXT DEFAULT NULL,
			entry_path VARCHAR(500) DEFAULT NULL,
			utm_source VARCHAR(150) DEFAULT NULL,
			utm_medium VARCHAR(150) DEFAULT NULL,
			utm_campaign VARCHAR(150) DEFAULT NULL,
			utm_term VARCHAR(150) DEFAULT NULL,
			utm_content VARCHAR(150) DEFAULT NULL,
			device_type VARCHAR(20) NOT NULL DEFAULT 'desktop',
			browser VARCHAR(60) DEFAULT NULL,
			os_name VARCHAR(60) DEFAULT NULL,
			ip_hash VARCHAR(64) DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY session_id (session_id),
			KEY visit_date (visit_date),
			KEY source_key (source_key),
			KEY device_type (device_type),
			KEY utm_campaign (utm_campaign)
		) {$charset_collate};";
		dbDelta( $sql );

		$funnel = self::funnel_table();
		$sql    = "CREATE TABLE {$funnel} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			event_date DATE NOT NULL,
			event_time DATETIME NOT NULL,
			stage_key VARCHAR(30) NOT NULL,
			session_id VARCHAR(40) DEFAULT NULL,
			object_id BIGINT UNSIGNED DEFAULT NULL,
			revenue DECIMAL(18,2) DEFAULT NULL,
			PRIMARY KEY  (id),
			KEY event_date (event_date),
			KEY stage_key (stage_key),
			KEY session_stage (session_id, stage_key)
		) {$charset_collate};";
		dbDelta( $sql );

		$cart = self::cart_table();
		$sql  = "CREATE TABLE {$cart} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id VARCHAR(40) NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			cart_contents LONGTEXT DEFAULT NULL,
			items_count INT UNSIGNED NOT NULL DEFAULT 0,
			cart_total DECIMAL(18,2) NOT NULL DEFAULT 0,
			order_id BIGINT UNSIGNED DEFAULT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY session_id (session_id),
			KEY status (status),
			KEY updated_at (updated_at)
		) {$charset_collate};";
		dbDelta( $sql );

		$heatmap = self::heatmap_table();
		$sql     = "CREATE TABLE {$heatmap} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			page_url_hash CHAR(32) NOT NULL,
			page_url VARCHAR(500) NOT NULL,
			event_type VARCHAR(10) NOT NULL,
			x_percent DECIMAL(6,2) DEFAULT NULL,
			y_percent DECIMAL(6,2) DEFAULT NULL,
			scroll_percent TINYINT UNSIGNED DEFAULT NULL,
			viewport_w SMALLINT UNSIGNED DEFAULT NULL,
			viewport_h SMALLINT UNSIGNED DEFAULT NULL,
			device_type VARCHAR(20) DEFAULT NULL,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY page_url_hash (page_url_hash),
			KEY event_type (event_type),
			KEY created_at (created_at)
		) {$charset_collate};";
		dbDelta( $sql );

		$replay_sessions = self::replay_sessions_table();
		$sql             = "CREATE TABLE {$replay_sessions} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id VARCHAR(40) NOT NULL,
			started_at DATETIME NOT NULL,
			ended_at DATETIME NOT NULL,
			page_count SMALLINT UNSIGNED NOT NULL DEFAULT 1,
			device_type VARCHAR(20) DEFAULT NULL,
			browser VARCHAR(60) DEFAULT NULL,
			entry_url VARCHAR(500) DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY session_id (session_id),
			KEY started_at (started_at)
		) {$charset_collate};";
		dbDelta( $sql );

		$replay_events = self::replay_events_table();
		$sql           = "CREATE TABLE {$replay_events} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			session_id VARCHAR(40) NOT NULL,
			page_url VARCHAR(500) DEFAULT NULL,
			event_type VARCHAR(10) NOT NULL,
			x_percent DECIMAL(6,2) DEFAULT NULL,
			y_percent DECIMAL(6,2) DEFAULT NULL,
			scroll_percent TINYINT UNSIGNED DEFAULT NULL,
			t_offset_ms INT UNSIGNED NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY session_id (session_id),
			KEY event_type (event_type)
		) {$charset_collate};";
		dbDelta( $sql );

		$alerts = self::alerts_table();
		$sql    = "CREATE TABLE {$alerts} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			alert_key VARCHAR(40) NOT NULL,
			severity VARCHAR(10) NOT NULL DEFAULT 'warning',
			title VARCHAR(190) NOT NULL,
			message TEXT DEFAULT NULL,
			metric_before DECIMAL(18,2) DEFAULT NULL,
			metric_after DECIMAL(18,2) DEFAULT NULL,
			is_read TINYINT(1) NOT NULL DEFAULT 0,
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY created_at (created_at),
			KEY is_read (is_read),
			KEY alert_key (alert_key)
		) {$charset_collate};";
		dbDelta( $sql );
	}

	/* ===================== بازدید‌ها (Visits) ===================== */

	/**
	 * ثبت بازدید جدید یا به‌روزرسانی نشست فعلی (افزایش شمار صفحات مشاهده‌شده).
	 *
	 * @return string 'new'|'updated'
	 */
	public static function upsert_visit( $session_id, $data ) {
		global $wpdb;
		$table = self::visits_table();

		$existing_id = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE session_id = %s", $session_id )
		);

		$now = current_time( 'mysql' );

		if ( $existing_id ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET last_visit_time = %s, pageview_count = pageview_count + 1 WHERE id = %d",
					$now,
					$existing_id
				)
			);
			return 'updated';
		}

		$wpdb->insert(
			$table,
			array(
				'session_id'       => $session_id,
				'visit_date'       => current_time( 'Y-m-d' ),
				'first_visit_time' => $now,
				'last_visit_time'  => $now,
				'pageview_count'   => 1,
				'had_interaction'  => 0,
				'source_key'       => $data['source_key'],
				'source_label'     => $data['source_label'],
				'referrer_host'    => isset( $data['referrer_host'] ) ? $data['referrer_host'] : null,
				'referrer_url'     => isset( $data['referrer_url'] ) ? $data['referrer_url'] : null,
				'entry_path'       => isset( $data['entry_path'] ) ? $data['entry_path'] : null,
				'utm_source'       => isset( $data['utm_source'] ) ? $data['utm_source'] : null,
				'utm_medium'       => isset( $data['utm_medium'] ) ? $data['utm_medium'] : null,
				'utm_campaign'     => isset( $data['utm_campaign'] ) ? $data['utm_campaign'] : null,
				'utm_term'         => isset( $data['utm_term'] ) ? $data['utm_term'] : null,
				'utm_content'      => isset( $data['utm_content'] ) ? $data['utm_content'] : null,
				'device_type'      => isset( $data['device_type'] ) ? $data['device_type'] : 'desktop',
				'browser'          => isset( $data['browser'] ) ? $data['browser'] : null,
				'os_name'          => isset( $data['os_name'] ) ? $data['os_name'] : null,
				'ip_hash'          => isset( $data['ip_hash'] ) ? $data['ip_hash'] : null,
			)
		);

		return 'new';
	}

	/**
	 * علامت‌گذاری نشست به‌عنوان «تعامل‌داشته» (غیر بانس) وقتی رویداد واقعی‌ای مثل افزودن به سبد رخ می‌دهد.
	 */
	public static function mark_session_interaction( $session_id ) {
		if ( empty( $session_id ) ) {
			return;
		}
		global $wpdb;
		$table = self::visits_table();
		$wpdb->query(
			$wpdb->prepare( "UPDATE {$table} SET had_interaction = 1 WHERE session_id = %s", $session_id )
		);
	}

	public static function get_total( $from, $to ) {
		global $wpdb;
		$table = self::visits_table();

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE visit_date BETWEEN %s AND %s", $from, $to )
		);
	}

	public static function get_total_pageviews( $from, $to ) {
		global $wpdb;
		$table = self::visits_table();

		return (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT COALESCE(SUM(pageview_count),0) FROM {$table} WHERE visit_date BETWEEN %s AND %s", $from, $to )
		);
	}

	/**
	 * نرخ خروج بدون تعامل (Bounce Rate) بر اساس رویدادهای واقعی: نشستی که فقط یک صفحه دیده
	 * و هیچ تعامل واقعی (افزودن به سبد، تسویه‌حساب، خرید) نداشته است.
	 */
	public static function get_bounce_rate( $from, $to ) {
		global $wpdb;
		$table = self::visits_table();

		$total = self::get_total( $from, $to );
		if ( 0 === $total ) {
			return 0.0;
		}

		$bounced = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE visit_date BETWEEN %s AND %s AND pageview_count <= 1 AND had_interaction = 0",
				$from,
				$to
			)
		);

		return round( ( $bounced / $total ) * 100, 1 );
	}

	public static function get_breakdown_by_source( $from, $to ) {
		global $wpdb;
		$table = self::visits_table();

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

	public static function get_daily_series( $from, $to ) {
		global $wpdb;
		$table = self::visits_table();

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

		$dates  = self::get_date_range_list( $from, $to );
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

	public static function get_daily_breakdown_table( $from, $to ) {
		global $wpdb;
		$table = self::visits_table();

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
			$by_date[ $row->visit_date ]['total']                        += (int) $row->total;
			$by_date[ $row->visit_date ]['sources'][ $row->source_key ] = array(
				'label' => $row->source_label,
				'total' => (int) $row->total,
			);
		}

		krsort( $by_date );

		return array_values( $by_date );
	}

	public static function get_other_referrers( $from, $to, $limit = 10 ) {
		global $wpdb;
		$table = self::visits_table();

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

	public static function get_device_breakdown( $from, $to ) {
		global $wpdb;
		$table = self::visits_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT device_type, COUNT(*) AS total
				FROM {$table}
				WHERE visit_date BETWEEN %s AND %s
				GROUP BY device_type
				ORDER BY total DESC",
				$from,
				$to
			)
		);

		return $rows ? $rows : array();
	}

	public static function get_browser_breakdown( $from, $to, $limit = 8 ) {
		global $wpdb;
		$table = self::visits_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT browser, COUNT(*) AS total
				FROM {$table}
				WHERE visit_date BETWEEN %s AND %s AND browser IS NOT NULL
				GROUP BY browser
				ORDER BY total DESC
				LIMIT %d",
				$from,
				$to,
				$limit
			)
		);

		return $rows ? $rows : array();
	}

	public static function get_source_total( $from, $to, $source_key ) {
		global $wpdb;
		$table = self::visits_table();

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE visit_date BETWEEN %s AND %s AND source_key = %s",
				$from,
				$to,
				$source_key
			)
		);
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

	public static function purge_older_than( $days ) {
		global $wpdb;

		if ( $days <= 0 ) {
			return;
		}

		$threshold = gmdate( 'Y-m-d', strtotime( '-' . (int) $days . ' days' ) );

		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::visits_table() . ' WHERE visit_date < %s', $threshold ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::funnel_table() . ' WHERE event_date < %s', $threshold ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::heatmap_table() . ' WHERE created_at < %s', $threshold . ' 00:00:00' ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::replay_events_table() . ' WHERE created_at < %s', $threshold . ' 00:00:00' ) );
		$wpdb->query( $wpdb->prepare( 'DELETE FROM ' . self::replay_sessions_table() . ' WHERE started_at < %s', $threshold . ' 00:00:00' ) );
	}

	public static function run_scheduled_cleanup() {
		$settings = AAW_Admin::get_settings();
		$days     = isset( $settings['retention_days'] ) ? (int) $settings['retention_days'] : 0;
		if ( $days > 0 ) {
			self::purge_older_than( $days );
		}

		AAW_Cart_Tracker::mark_stale_carts_abandoned();
	}

	public static function truncate_all() {
		global $wpdb;
		foreach ( array( self::visits_table(), self::funnel_table(), self::cart_table(), self::heatmap_table(), self::replay_sessions_table(), self::replay_events_table(), self::alerts_table() ) as $table ) {
			$wpdb->query( "TRUNCATE TABLE {$table}" );
		}
	}

	/* ===================== قیف فروش (Funnel) ===================== */

	/**
	 * ثبت یک رویداد قیف فقط در صورتی که برای این نشست و این مرحله قبلاً ثبت نشده باشد
	 * (جلوگیری از شمارش تکراری با رفرش صفحه، بدون نیاز به کوکی مجزا برای هر مرحله).
	 *
	 * @return bool آیا رویداد جدید ثبت شد؟
	 */
	public static function insert_funnel_event_once( $stage_key, $session_id, $object_id = null, $revenue = null ) {
		global $wpdb;
		$table = self::funnel_table();

		if ( ! empty( $session_id ) ) {
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE session_id = %s AND stage_key = %s LIMIT 1",
					$session_id,
					$stage_key
				)
			);
			if ( $exists ) {
				return false;
			}
		}

		$wpdb->insert(
			$table,
			array(
				'event_date' => current_time( 'Y-m-d' ),
				'event_time' => current_time( 'mysql' ),
				'stage_key'  => sanitize_key( $stage_key ),
				'session_id' => $session_id ? $session_id : null,
				'object_id'  => $object_id ? (int) $object_id : null,
				'revenue'    => null !== $revenue ? (float) $revenue : null,
			)
		);

		return true;
	}

	/**
	 * ثبت رویداد «خرید نهایی» برای یک سفارش؛ محافظت اصلی از شمارش تکراری در سطح متای سفارش
	 * (در کلاس AAW_WooCommerce) انجام می‌شود، این متد صرفاً درج رکورد رویداد است.
	 */
	public static function insert_purchase_event( $order_id, $revenue, $session_id = null ) {
		global $wpdb;
		$wpdb->insert(
			self::funnel_table(),
			array(
				'event_date' => current_time( 'Y-m-d' ),
				'event_time' => current_time( 'mysql' ),
				'stage_key'  => 'purchase',
				'session_id' => $session_id,
				'object_id'  => (int) $order_id,
				'revenue'    => (float) $revenue,
			)
		);
	}

	public static function get_funnel_counts( $from, $to ) {
		global $wpdb;
		$table = self::funnel_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT stage_key, COUNT(*) AS total
				FROM {$table}
				WHERE event_date BETWEEN %s AND %s
				GROUP BY stage_key",
				$from,
				$to
			)
		);

		$counts = array();
		foreach ( (array) $rows as $row ) {
			$counts[ $row->stage_key ] = (int) $row->total;
		}

		return $counts;
	}

	/* ===================== سبد خرید و سبدهای رها شده ===================== */

	public static function upsert_cart_snapshot( $session_id, $items, $total ) {
		global $wpdb;
		$table = self::cart_table();
		$now   = current_time( 'mysql' );

		$existing_id = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE session_id = %s", $session_id )
		);

		$payload = array(
			'status'        => 'active',
			'cart_contents' => wp_json_encode( $items ),
			'items_count'   => count( $items ),
			'cart_total'    => (float) $total,
			'updated_at'    => $now,
		);

		if ( $existing_id ) {
			$wpdb->update( $table, $payload, array( 'id' => $existing_id ) );
		} else {
			$payload['session_id'] = $session_id;
			$payload['created_at'] = $now;
			$wpdb->insert( $table, $payload );
		}
	}

	public static function mark_cart_converted( $session_id, $order_id ) {
		global $wpdb;
		$wpdb->update(
			self::cart_table(),
			array(
				'status'     => 'converted',
				'order_id'   => (int) $order_id,
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'session_id' => $session_id )
		);
	}

	public static function mark_carts_abandoned_before( $threshold_mysql ) {
		global $wpdb;
		$table = self::cart_table();
		$wpdb->query(
			$wpdb->prepare(
				"UPDATE {$table} SET status = 'abandoned' WHERE status = 'active' AND updated_at < %s",
				$threshold_mysql
			)
		);
	}

	public static function get_abandoned_summary( $from, $to ) {
		global $wpdb;
		$table = self::cart_table();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COUNT(*) AS total, COALESCE(SUM(cart_total),0) AS total_value
				FROM {$table}
				WHERE status = 'abandoned' AND updated_at BETWEEN %s AND %s",
				$from . ' 00:00:00',
				$to . ' 23:59:59'
			)
		);

		return array(
			'total'       => $row ? (int) $row->total : 0,
			'total_value' => $row ? (float) $row->total_value : 0,
		);
	}

	public static function get_abandoned_carts( $from, $to, $limit = 20 ) {
		global $wpdb;
		$table = self::cart_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE status = 'abandoned' AND updated_at BETWEEN %s AND %s
				ORDER BY updated_at DESC
				LIMIT %d",
				$from . ' 00:00:00',
				$to . ' 23:59:59',
				$limit
			)
		);

		return $rows ? $rows : array();
	}

	/**
	 * محصولات پرتکرار در سبدهای رها شده (برای شناسایی محصولاتی که بیشترین ترک سبد را دارند).
	 */
	public static function get_abandoned_top_products( $from, $to, $limit = 8 ) {
		$carts    = self::get_abandoned_carts( $from, $to, 500 );
		$products = array();

		foreach ( $carts as $cart ) {
			$items = json_decode( $cart->cart_contents, true );
			if ( ! is_array( $items ) ) {
				continue;
			}
			foreach ( $items as $item ) {
				$pid = isset( $item['product_id'] ) ? (int) $item['product_id'] : 0;
				if ( ! $pid ) {
					continue;
				}
				if ( ! isset( $products[ $pid ] ) ) {
					$products[ $pid ] = array(
						'product_id' => $pid,
						'name'       => isset( $item['name'] ) ? $item['name'] : '',
						'count'      => 0,
						'quantity'   => 0,
					);
				}
				$products[ $pid ]['count']++;
				$products[ $pid ]['quantity'] += isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;
			}
		}

		usort(
			$products,
			function ( $a, $b ) {
				return $b['count'] - $a['count'];
			}
		);

		return array_slice( array_values( $products ), 0, $limit );
	}

	/* ===================== Heatmap ===================== */

	public static function insert_heatmap_events( $page_url, $events, $device_type = 'desktop' ) {
		global $wpdb;
		$table = self::heatmap_table();
		$hash  = md5( self::normalize_url( $page_url ) );
		$now   = current_time( 'mysql' );

		foreach ( $events as $event ) {
			$wpdb->insert(
				$table,
				array(
					'page_url_hash'  => $hash,
					'page_url'       => mb_substr( $page_url, 0, 500 ),
					'event_type'     => sanitize_key( $event['type'] ),
					'x_percent'      => isset( $event['x'] ) ? (float) $event['x'] : null,
					'y_percent'      => isset( $event['y'] ) ? (float) $event['y'] : null,
					'scroll_percent' => isset( $event['scroll'] ) ? (int) $event['scroll'] : null,
					'viewport_w'     => isset( $event['vw'] ) ? (int) $event['vw'] : null,
					'viewport_h'     => isset( $event['vh'] ) ? (int) $event['vh'] : null,
					'device_type'    => $device_type,
					'created_at'     => $now,
				)
			);
		}
	}

	public static function normalize_url( $url ) {
		$parts = wp_parse_url( $url );
		return isset( $parts['path'] ) ? untrailingslashit( $parts['path'] ) : $url;
	}

	public static function get_tracked_pages( $from, $to, $limit = 20 ) {
		global $wpdb;
		$table = self::heatmap_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT page_url_hash, MAX(page_url) AS page_url, COUNT(*) AS total,
					SUM(CASE WHEN event_type = 'click' THEN 1 ELSE 0 END) AS clicks
				FROM {$table}
				WHERE created_at BETWEEN %s AND %s
				GROUP BY page_url_hash
				ORDER BY total DESC
				LIMIT %d",
				$from . ' 00:00:00',
				$to . ' 23:59:59',
				$limit
			)
		);

		return $rows ? $rows : array();
	}

	public static function get_click_points( $page_url_hash, $from, $to, $limit = 2000 ) {
		global $wpdb;
		$table = self::heatmap_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT x_percent, y_percent FROM {$table}
				WHERE page_url_hash = %s AND event_type = 'click' AND created_at BETWEEN %s AND %s
				ORDER BY id DESC
				LIMIT %d",
				$page_url_hash,
				$from . ' 00:00:00',
				$to . ' 23:59:59',
				$limit
			)
		);

		return $rows ? $rows : array();
	}

	public static function get_scroll_depth_distribution( $page_url_hash, $from, $to ) {
		global $wpdb;
		$table = self::heatmap_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT scroll_percent FROM {$table}
				WHERE page_url_hash = %s AND event_type = 'scroll' AND created_at BETWEEN %s AND %s",
				$page_url_hash,
				$from . ' 00:00:00',
				$to . ' 23:59:59'
			)
		);

		$buckets = array(
			25  => 0,
			50  => 0,
			75  => 0,
			100 => 0,
		);
		$total   = count( $rows );

		foreach ( $rows as $row ) {
			$depth = (int) $row->scroll_percent;
			foreach ( array( 25, 50, 75, 100 ) as $bucket ) {
				if ( $depth >= $bucket ) {
					$buckets[ $bucket ]++;
				}
			}
		}

		$result = array();
		foreach ( $buckets as $bucket => $count ) {
			$result[ $bucket ] = $total > 0 ? round( ( $count / $total ) * 100 ) : 0;
		}

		return $result;
	}

	/* ===================== Session Replay ===================== */

	public static function upsert_replay_session( $session_id, $meta ) {
		global $wpdb;
		$table = self::replay_sessions_table();
		$now   = current_time( 'mysql' );

		$existing_id = $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE session_id = %s", $session_id )
		);

		if ( $existing_id ) {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$table} SET ended_at = %s, page_count = page_count + %d WHERE id = %d",
					$now,
					isset( $meta['new_page'] ) && $meta['new_page'] ? 1 : 0,
					$existing_id
				)
			);
			return;
		}

		$wpdb->insert(
			$table,
			array(
				'session_id'  => $session_id,
				'started_at'  => $now,
				'ended_at'    => $now,
				'page_count'  => 1,
				'device_type' => isset( $meta['device_type'] ) ? $meta['device_type'] : null,
				'browser'     => isset( $meta['browser'] ) ? $meta['browser'] : null,
				'entry_url'   => isset( $meta['entry_url'] ) ? mb_substr( $meta['entry_url'], 0, 500 ) : null,
			)
		);
	}

	public static function insert_replay_events( $session_id, $events ) {
		global $wpdb;
		$table = self::replay_events_table();
		$now   = current_time( 'mysql' );

		foreach ( $events as $event ) {
			$wpdb->insert(
				$table,
				array(
					'session_id'     => $session_id,
					'page_url'       => isset( $event['url'] ) ? mb_substr( $event['url'], 0, 500 ) : null,
					'event_type'     => sanitize_key( $event['type'] ),
					'x_percent'      => isset( $event['x'] ) ? (float) $event['x'] : null,
					'y_percent'      => isset( $event['y'] ) ? (float) $event['y'] : null,
					'scroll_percent' => isset( $event['scroll'] ) ? (int) $event['scroll'] : null,
					't_offset_ms'    => isset( $event['t'] ) ? (int) $event['t'] : 0,
					'created_at'     => $now,
				)
			);
		}
	}

	public static function get_replay_sessions( $from, $to, $limit = 20, $offset = 0 ) {
		global $wpdb;
		$table = self::replay_sessions_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table}
				WHERE started_at BETWEEN %s AND %s
				ORDER BY started_at DESC
				LIMIT %d OFFSET %d",
				$from . ' 00:00:00',
				$to . ' 23:59:59',
				$limit,
				$offset
			)
		);

		return $rows ? $rows : array();
	}

	public static function count_replay_sessions( $from, $to ) {
		global $wpdb;
		$table = self::replay_sessions_table();

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE started_at BETWEEN %s AND %s",
				$from . ' 00:00:00',
				$to . ' 23:59:59'
			)
		);
	}

	public static function get_replay_events( $session_id ) {
		global $wpdb;
		$table = self::replay_events_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE session_id = %s ORDER BY t_offset_ms ASC",
				$session_id
			)
		);

		return $rows ? $rows : array();
	}

	/* ===================== هشدار هوشمند (Alerts) ===================== */

	public static function insert_alert( $key, $severity, $title, $message, $before, $after ) {
		global $wpdb;
		$wpdb->insert(
			self::alerts_table(),
			array(
				'alert_key'     => sanitize_key( $key ),
				'severity'      => $severity,
				'title'         => $title,
				'message'       => $message,
				'metric_before' => $before,
				'metric_after'  => $after,
				'is_read'       => 0,
				'created_at'    => current_time( 'mysql' ),
			)
		);
	}

	public static function had_alert_today( $key ) {
		global $wpdb;
		$table = self::alerts_table();
		$today = current_time( 'Y-m-d' );

		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} WHERE alert_key = %s AND created_at >= %s",
				$key,
				$today . ' 00:00:00'
			)
		);

		return $count > 0;
	}

	public static function get_recent_alerts( $limit = 20 ) {
		global $wpdb;
		$table = self::alerts_table();

		$rows = $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", $limit )
		);

		return $rows ? $rows : array();
	}

	public static function get_unread_alerts_count() {
		global $wpdb;
		$table = self::alerts_table();

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE is_read = 0" );
	}

	public static function mark_all_alerts_read() {
		global $wpdb;
		$wpdb->query( 'UPDATE ' . self::alerts_table() . ' SET is_read = 1 WHERE is_read = 0' );
	}
}
