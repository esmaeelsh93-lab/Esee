<?php
/**
 * Local analytics — daily snapshots, trends, weekly summary.
 * Fully self-contained (no external APIs or CDN chart libraries).
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_Analytics
 */
class Shojaei_SEO_Analytics {

	private const OPTION_DAILY   = 'shojaei_seo_daily_stats';
	private const OPTION_WEEKLY  = 'shojaei_seo_weekly_summary';
	private const MAX_DAYS       = 90;

	/**
	 * Constructor — register cron hooks.
	 */
	public function __construct() {
		add_action( 'shojaei_seo_daily_oos_check', array( $this, 'snapshot_today' ), 5 );
		add_action( 'shojaei_seo_weekly_summary', array( $this, 'generate_weekly_summary' ) );
	}

	/**
	 * Snapshot current counts into local daily history.
	 * Skips re-query if today's snapshot already exists (dashboard should stay fast).
	 */
	public function snapshot_today(): void {
		global $wpdb;

		$today = current_time( 'Y-m-d' );
		$stats = get_option( self::OPTION_DAILY, array() );
		if ( ! is_array( $stats ) ) {
			$stats = array();
		}

		// Already snapshotted today — keep dashboard render cheap.
		if ( isset( $stats[ $today ] ) && isset( $stats[ $today ]['oos_count'] ) ) {
			return;
		}

		$oos_table = Shojaei_SEO_Helpers::oos_table();
		$log_table = Shojaei_SEO_Helpers::redirect_log_table();

		$oos_count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$oos_table} WHERE status != 'redirected'"
		);
		$candidates = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$oos_table} WHERE status = 'candidate_redirect'"
		);

		$day_start = $today . ' 00:00:00';
		$day_end   = $today . ' 23:59:59';

		$redirects_today = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$log_table}
				WHERE is_undone = 0 AND reason != 'undo'
				AND redirect_type IN ('301','302')
				AND created_at BETWEEN %s AND %s",
				$day_start,
				$day_end
			)
		);

		$gone_today = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$log_table}
				WHERE is_undone = 0 AND reason != 'undo'
				AND redirect_type = '410'
				AND created_at BETWEEN %s AND %s",
				$day_start,
				$day_end
			)
		);

		$existing = $stats[ $today ] ?? array();

		$stats[ $today ] = array(
			'oos_count'   => $oos_count,
			'candidates'  => $candidates,
			'redirects'   => max( (int) ( $existing['redirects'] ?? 0 ), $redirects_today ),
			'gone_410'    => max( (int) ( $existing['gone_410'] ?? 0 ), $gone_today ),
			'links_built' => (int) ( $existing['links_built'] ?? 0 ),
		);

		$stats = self::trim_history( $stats );
		update_option( self::OPTION_DAILY, $stats, false );
	}

	/**
	 * Increment a daily counter (redirects, links, 410).
	 *
	 * @param string $metric Metric key.
	 * @param int    $by     Amount.
	 */
	public static function bump( string $metric, int $by = 1 ): void {
		$allowed = array( 'redirects', 'gone_410', 'links_built' );
		if ( ! in_array( $metric, $allowed, true ) ) {
			return;
		}

		$today = current_time( 'Y-m-d' );
		$stats = get_option( self::OPTION_DAILY, array() );
		if ( ! is_array( $stats ) ) {
			$stats = array();
		}

		if ( ! isset( $stats[ $today ] ) ) {
			$stats[ $today ] = array(
				'oos_count'   => 0,
				'candidates'  => 0,
				'redirects'   => 0,
				'gone_410'    => 0,
				'links_built' => 0,
			);
		}

		$stats[ $today ][ $metric ] = (int) ( $stats[ $today ][ $metric ] ?? 0 ) + $by;
		$stats = self::trim_history( $stats );
		update_option( self::OPTION_DAILY, $stats, false );
	}

	/**
	 * Get trend series for last N days.
	 *
	 * @param int $days Number of days.
	 * @return array{labels:string[],oos:int[],redirects:int[],gone_410:int[],links:int[],candidates:int[]}
	 */
	public static function get_trend( int $days = 7 ): array {
		$days  = max( 1, min( 90, $days ) );
		$stats = get_option( self::OPTION_DAILY, array() );
		if ( ! is_array( $stats ) ) {
			$stats = array();
		}

		$labels     = array();
		$oos        = array();
		$redirects  = array();
		$gone       = array();
		$links      = array();
		$candidates = array();

		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$date     = gmdate( 'Y-m-d', strtotime( "-{$i} days", current_time( 'timestamp' ) ) );
			$day_data = $stats[ $date ] ?? array();

			$labels[]     = mysql2date( 'm/d', $date . ' 00:00:00' );
			$oos[]        = (int) ( $day_data['oos_count'] ?? 0 );
			$redirects[]  = (int) ( $day_data['redirects'] ?? 0 );
			$gone[]       = (int) ( $day_data['gone_410'] ?? 0 );
			$links[]      = (int) ( $day_data['links_built'] ?? 0 );
			$candidates[] = (int) ( $day_data['candidates'] ?? 0 );
		}

		return array(
			'labels'     => $labels,
			'oos'        => $oos,
			'redirects'  => $redirects,
			'gone_410'   => $gone,
			'links'      => $links,
			'candidates' => $candidates,
		);
	}

	/**
	 * Build period totals for a day range ending N days ago.
	 *
	 * @param int $days        Length of window.
	 * @param int $offset_days How many days ago the window ends (0 = today).
	 * @return array
	 */
	public static function get_period_totals( int $days = 7, int $offset_days = 0 ): array {
		$days        = max( 1, min( 90, $days ) );
		$offset_days = max( 0, $offset_days );
		$stats       = get_option( self::OPTION_DAILY, array() );
		if ( ! is_array( $stats ) ) {
			$stats = array();
		}

		$oos_sum = 0;
		$oos_n   = 0;
		$redirects = 0;
		$gone      = 0;
		$links     = 0;
		$candidates_max = 0;
		$first_oos = null;
		$last_oos  = null;

		for ( $i = $days - 1; $i >= 0; $i-- ) {
			$ago  = $offset_days + $i;
			$date = gmdate( 'Y-m-d', strtotime( "-{$ago} days", current_time( 'timestamp' ) ) );
			$day  = $stats[ $date ] ?? array();

			$oos = (int) ( $day['oos_count'] ?? 0 );
			$oos_sum += $oos;
			$oos_n++;
			if ( null === $first_oos ) {
				$first_oos = $oos;
			}
			$last_oos = $oos;

			$redirects     += (int) ( $day['redirects'] ?? 0 );
			$gone          += (int) ( $day['gone_410'] ?? 0 );
			$links         += (int) ( $day['links_built'] ?? 0 );
			$candidates_max = max( $candidates_max, (int) ( $day['candidates'] ?? 0 ) );
		}

		return array(
			'avg_oos'        => $oos_n ? (int) round( $oos_sum / $oos_n ) : 0,
			'oos_delta'      => (int) $last_oos - (int) $first_oos,
			'redirects'      => $redirects,
			'gone_410'       => $gone,
			'links_built'    => $links,
			'max_candidates' => $candidates_max,
			'latest_oos'     => (int) $last_oos,
		);
	}

	/**
	 * Compare this week vs previous week (local data only).
	 *
	 * @return array{this_week:array,prev_week:array,diff:array}
	 */
	public static function get_week_comparison(): array {
		$this_week = self::get_period_totals( 7, 0 );
		$prev_week = self::get_period_totals( 7, 7 );

		$keys = array( 'avg_oos', 'redirects', 'gone_410', 'links_built', 'max_candidates', 'latest_oos' );
		$diff = array();
		foreach ( $keys as $key ) {
			$diff[ $key ] = (int) ( $this_week[ $key ] ?? 0 ) - (int) ( $prev_week[ $key ] ?? 0 );
		}

		return array(
			'this_week' => $this_week,
			'prev_week' => $prev_week,
			'diff'      => $diff,
		);
	}

	/**
	 * Generate weekly summary from local data and store + notify.
	 */
	public function generate_weekly_summary(): void {
		$trend = self::get_trend( 7 );

		$sum_redirects = array_sum( $trend['redirects'] );
		$sum_410       = array_sum( $trend['gone_410'] );
		$sum_links     = array_sum( $trend['links'] );
		$avg_oos       = count( $trend['oos'] ) ? (int) round( array_sum( $trend['oos'] ) / count( $trend['oos'] ) ) : 0;
		$latest_oos    = end( $trend['oos'] );
		$first_oos     = reset( $trend['oos'] );
		$oos_delta     = (int) $latest_oos - (int) $first_oos;
		$max_candidates = max( $trend['candidates'] ?: array( 0 ) );

		$summary = array(
			'generated_at'   => current_time( 'mysql' ),
			'period_days'    => 7,
			'avg_oos'        => $avg_oos,
			'oos_delta'      => $oos_delta,
			'redirects'      => $sum_redirects,
			'gone_410'       => $sum_410,
			'links_built'    => $sum_links,
			'max_candidates' => (int) $max_candidates,
			'latest_oos'     => (int) $latest_oos,
		);

		update_option( self::OPTION_WEEKLY, $summary, false );

		$message = sprintf(
			/* translators: 1: oos count, 2: redirects, 3: 410 count, 4: links */
			__( 'خلاصه هفتگی: میانگین %1$d محصول ناموجود | %2$d ریدایرکت | %3$d وضعیت 410 | %4$d لینک داخلی.', 'shojaei-seo-for-woo' ),
			$avg_oos,
			$sum_redirects,
			$sum_410,
			$sum_links
		);

		Shojaei_SEO_Notifications::add(
			'weekly_summary',
			$message,
			0,
			admin_url( 'admin.php?page=shojaei-seo&tab=impact' )
		);
	}

	/**
	 * Get stored weekly summary.
	 *
	 * @return array
	 */
	public static function get_weekly_summary(): array {
		$summary = get_option( self::OPTION_WEEKLY, array() );
		return is_array( $summary ) ? $summary : array();
	}

	/**
	 * Build a local weekly summary on the fly if cron hasn't run yet.
	 *
	 * @return array
	 */
	public static function get_or_build_weekly_summary(): array {
		$stored = self::get_weekly_summary();
		if ( ! empty( $stored ) ) {
			return $stored;
		}

		$trend = self::get_trend( 7 );
		return array(
			'generated_at'   => current_time( 'mysql' ),
			'period_days'    => 7,
			'avg_oos'        => count( $trend['oos'] ) ? (int) round( array_sum( $trend['oos'] ) / count( $trend['oos'] ) ) : 0,
			'oos_delta'      => (int) end( $trend['oos'] ) - (int) reset( $trend['oos'] ),
			'redirects'      => array_sum( $trend['redirects'] ),
			'gone_410'       => array_sum( $trend['gone_410'] ),
			'links_built'    => array_sum( $trend['links'] ),
			'max_candidates' => (int) max( $trend['candidates'] ?: array( 0 ) ),
			'latest_oos'     => (int) end( $trend['oos'] ),
		);
	}

	/**
	 * Render a simple SVG line chart (no external library).
	 *
	 * @param array  $values Numeric series.
	 * @param array  $labels X labels.
	 * @param string $color  Stroke color.
	 * @param int    $width  SVG width.
	 * @param int    $height SVG height.
	 * @return string
	 */
	public static function render_svg_line( array $values, array $labels, string $color = '#1e88e5', int $width = 560, int $height = 160 ): string {
		$count = count( $values );
		if ( $count < 1 ) {
			return '';
		}

		$max = max( 1, max( $values ) );
		$pad = 28;
		$inner_w = $width - ( $pad * 2 );
		$inner_h = $height - ( $pad * 2 );

		$points = array();
		for ( $i = 0; $i < $count; $i++ ) {
			$x = $pad + ( $count === 1 ? $inner_w / 2 : ( $i / ( $count - 1 ) ) * $inner_w );
			$y = $pad + $inner_h - ( ( (int) $values[ $i ] / $max ) * $inner_h );
			$points[] = round( $x, 1 ) . ',' . round( $y, 1 );
		}

		$polyline = implode( ' ', $points );
		$circles  = '';
		foreach ( $points as $i => $pt ) {
			list( $cx, $cy ) = explode( ',', $pt );
			$circles .= sprintf(
				'<circle cx="%s" cy="%s" r="3.5" fill="%s"><title>%s: %s</title></circle>',
				esc_attr( $cx ),
				esc_attr( $cy ),
				esc_attr( $color ),
				esc_attr( $labels[ $i ] ?? '' ),
				esc_attr( (string) $values[ $i ] )
			);
		}

		$label_svg = '';
		$step = max( 1, (int) floor( $count / 6 ) );
		for ( $i = 0; $i < $count; $i += $step ) {
			list( $cx ) = explode( ',', $points[ $i ] );
			$label_svg .= sprintf(
				'<text x="%s" y="%d" text-anchor="middle" font-size="10" fill="#888">%s</text>',
				esc_attr( $cx ),
				$height - 6,
				esc_html( $labels[ $i ] ?? '' )
			);
		}

		return sprintf(
			'<svg class="shojaei-trend-svg" viewBox="0 0 %1$d %2$d" width="100%%" height="%2$d" role="img" aria-label="%3$s">
				<line x1="%4$d" y1="%5$d" x2="%6$d" y2="%5$d" stroke="#e8f0fe" stroke-width="1"/>
				<line x1="%4$d" y1="%7$d" x2="%6$d" y2="%7$d" stroke="#e8f0fe" stroke-width="1"/>
				<polyline fill="none" stroke="%8$s" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round" points="%9$s"/>
				%10$s
				%11$s
			</svg>',
			$width,
			$height,
			esc_attr__( 'نمودار روند', 'shojaei-seo-for-woo' ),
			$pad,
			$pad,
			$width - $pad,
			$height - $pad,
			esc_attr( $color ),
			esc_attr( $polyline ),
			$circles,
			$label_svg
		);
	}

	/**
	 * Keep only last MAX_DAYS entries.
	 *
	 * @param array $stats Stats array.
	 * @return array
	 */
	private static function trim_history( array $stats ): array {
		ksort( $stats );
		if ( count( $stats ) > self::MAX_DAYS ) {
			$stats = array_slice( $stats, -self::MAX_DAYS, null, true );
		}
		return $stats;
	}
}
