<?php
/**
 * Admin activity log — local only.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_Activity_Log
 */
class Shojaei_SEO_Activity_Log {

	/**
	 * Table name.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'shojaei_seo_activity_log';
	}

	/**
	 * Add a log entry.
	 *
	 * @param string $action     Action slug.
	 * @param string $message    Human message.
	 * @param int    $product_id Related product.
	 * @param array  $meta       Optional meta.
	 */
	public static function add( string $action, string $message, int $product_id = 0, array $meta = array() ): void {
		global $wpdb;

		$wpdb->insert(
			self::table(),
			array(
				'user_id'    => get_current_user_id(),
				'action'     => sanitize_key( $action ),
				'product_id' => $product_id,
				'message'    => wp_strip_all_tags( $message ),
				'meta'       => $meta ? wp_json_encode( $meta, JSON_UNESCAPED_UNICODE ) : '',
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s' )
		);
	}

	/**
	 * Get recent log entries.
	 *
	 * @param int $limit Max rows.
	 * @return array
	 */
	public static function get_recent( int $limit = 50 ): array {
		global $wpdb;
		$table = self::table();
		$limit = max( 1, min( 200, $limit ) );

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY id DESC LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * Action label (Persian).
	 *
	 * @param string $action Action slug.
	 * @return string
	 */
	public static function label( string $action ): string {
		$map = array(
			'redirect_301'   => __( 'ریدایرکت دائمی (۳۰۱)', 'shojaei-seo-for-woo' ),
			'redirect_302'   => __( 'ریدایرکت موقت (۳۰۲)', 'shojaei-seo-for-woo' ),
			'redirect_410'   => __( 'حذف از ایندکس (۴۱۰)', 'shojaei-seo-for-woo' ),
			'keep_page'      => __( 'نگه‌داشتن صفحه', 'shojaei-seo-for-woo' ),
			'undo_redirect'  => __( 'لغو ریدایرکت', 'shojaei-seo-for-woo' ),
			'bulk_action'    => __( 'عملیات گروهی', 'shojaei-seo-for-woo' ),
			'bulk_queued'    => __( 'صف عملیات گروهی', 'shojaei-seo-for-woo' ),
			'auto_redirect'  => __( 'ریدایرکت خودکار', 'shojaei-seo-for-woo' ),
			'dry_run'        => __( 'شبیه‌سازی (بدون اعمال)', 'shojaei-seo-for-woo' ),
			'dry_run_apply'  => __( 'اجرای واقعی بعد از شبیه‌سازی', 'shojaei-seo-for-woo' ),
			'force_rescan'   => __( 'اسکن مجدد موجودی', 'shojaei-seo-for-woo' ),
			'settings_save'  => __( 'ذخیره تنظیمات', 'shojaei-seo-for-woo' ),
			'link_add'       => __( 'افزودن لینک داخلی', 'shojaei-seo-for-woo' ),
			'link_delete'    => __( 'حذف لینک داخلی', 'shojaei-seo-for-woo' ),
			'rollback'       => __( 'بازگردانی تغییر', 'shojaei-seo-for-woo' ),
			'redirect_loop'  => __( 'هشدار حلقه ریدایرکت', 'shojaei-seo-for-woo' ),
			'needs_manual'   => __( 'نیاز به تأیید دستی', 'shojaei-seo-for-woo' ),
			'product_test'   => __( 'تست محصول', 'shojaei-seo-for-woo' ),
			'gsc_connected'  => __( 'اتصال سرچ‌کنسول', 'shojaei-seo-for-woo' ),
			'gsc_error'      => __( 'خطای سرچ‌کنسول', 'shojaei-seo-for-woo' ),
			'gsc_key_upload' => __( 'آپلود کلید گوگل', 'shojaei-seo-for-woo' ),
			'gsc_disconnect' => __( 'قطع اتصال گوگل', 'shojaei-seo-for-woo' ),
			'gsc_index'      => __( 'درخواست ایندکس', 'shojaei-seo-for-woo' ),
			'gsc_index_error'=> __( 'خطای ایندکس گوگل', 'shojaei-seo-for-woo' ),
			'store_profile'  => __( 'پروفایل فروشگاهی', 'shojaei-seo-for-woo' ),
			'schema_fix'    => __( 'تنظیم اسکیما', 'shojaei-seo-for-woo' ),
			'slug_redirect'  => __( 'ریدایرکت نامک محصول', 'shojaei-seo-for-woo' ),
			'slug_apply'     => __( 'اعمال نامک فینگلیش', 'shojaei-seo-for-woo' ),
			'slug_undo'      => __( 'Undo نامک', 'shojaei-seo-for-woo' ),
		);

		return $map[ $action ] ?? $action;
	}
}
