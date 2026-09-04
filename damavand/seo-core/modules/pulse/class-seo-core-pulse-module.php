<?php
/**
 * ماژول نبض سئو داخل هسته سئو — آداپتر روی موتور قانون‌محور موجود.
 *
 * در حالت Passive فقط داشبورد؛ اسکن پس‌زمینه با Override یا بدون رقیب.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SEO_Core_Pulse_Module
 */
class SEO_Core_Pulse_Module extends SEO_Core_Module {

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return 'pulse';
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __( 'نبض سئو (تحلیلگر)', 'shojaei-seo-for-woo' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description(): string {
		return __( 'تحلیل قانون‌محور ۰–۱۰۰ بدون API خارجی؛ نتایج در جدول seo_core_reports.', 'shojaei-seo-for-woo' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function install(): void {
		// جداول در SEO_Core_DB::install ساخته می‌شوند.
		if ( class_exists( 'Shojaei_SEO_Pulse' ) ) {
			Shojaei_SEO_Pulse::create_tables();
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		// موتور موجود (Shojaei_SEO_Pulse) خودش کرون دارد.
		// در Passive: کرون روزانه را متوقف نکن اگر Override خاموش است — فقط از UI راهنمایی نشان بده.
		// همگام‌سازی اختیاری گزارش‌ها به جدول هسته.
		add_action( 'shojaei_seo_pulse_result_saved', array( $this, 'mirror_to_core_reports' ), 10, 1 );
	}

	/**
	 * کپی سبک نتیجه به wp_seo_core_reports (بدون وابستگی اجباری).
	 *
	 * @param array<string,mixed> $row ردیف ذخیره‌شده.
	 */
	public function mirror_to_core_reports( array $row ): void {
		if ( ! class_exists( 'SEO_Core_DB' ) ) {
			return;
		}
		global $wpdb;
		$table   = SEO_Core_DB::reports_table();
		$post_id = absint( $row['post_id'] ?? 0 );
		if ( $post_id < 1 ) {
			return;
		}

		$issues = $row['issues'] ?? array();
		if ( is_array( $issues ) ) {
			$issues = wp_json_encode( $issues, JSON_UNESCAPED_UNICODE );
		}

		$data = array(
			'post_id'         => $post_id,
			'post_type'       => sanitize_key( (string) ( $row['post_type'] ?? 'post' ) ),
			'score'           => max( 0, min( 100, (int) ( $row['score'] ?? 0 ) ) ),
			'score_onpage'    => max( 0, min( 100, (int) ( $row['score_onpage'] ?? 0 ) ) ),
			'score_content'   => max( 0, min( 100, (int) ( $row['score_content'] ?? 0 ) ) ),
			'score_technical' => max( 0, min( 100, (int) ( $row['score_technical'] ?? 0 ) ) ),
			'score_links'     => max( 0, min( 100, (int) ( $row['score_links'] ?? 0 ) ) ),
			'critical_count'  => max( 0, (int) ( $row['critical_count'] ?? 0 ) ),
			'warning_count'   => max( 0, (int) ( $row['warning_count'] ?? 0 ) ),
			'is_orphan'       => ! empty( $row['is_orphan'] ) ? 1 : 0,
			'issues'          => (string) $issues,
			'content_hash'    => substr( sanitize_text_field( (string) ( $row['content_hash'] ?? '' ) ), 0, 32 ),
			'analyzed_at'     => current_time( 'mysql' ),
		);

		$exists = (int) $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$table} WHERE post_id = %d", $post_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		if ( $exists ) {
			$wpdb->update( $table, $data, array( 'post_id' => $post_id ) );
		} else {
			$wpdb->insert( $table, $data );
		}
	}
}
