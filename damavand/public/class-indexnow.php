<?php
/**
 * IndexNow and Sitemap Ping module.
 *
 * ارسال خودکار روی ذخیره محصول + ارسال دستی گروهی (مثل Rank Math).
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_IndexNow
 */
class Shojaei_SEO_IndexNow {

	public const HISTORY_OPTION = 'shojaei_seo_indexnow_history';
	public const PENDING_OPTION = 'shojaei_seo_indexnow_pending';
	public const MAX_URLS       = 10000;
	public const CHUNK_SIZE     = 100;
	public const MAX_PENDING    = 100;

	/**
	 * IndexNow API endpoints.
	 *
	 * @var array
	 */
	private array $endpoints = array(
		'https://api.indexnow.org/indexnow',
		'https://www.bing.com/indexnow',
		'https://yandex.com/indexnow/indexnow',
	);

	/**
	 * Constructor — هوک‌های خودکار.
	 *
	 * @param bool $register_hooks اگر false فقط برای فراخوانی متدها ساخته می‌شود.
	 */
	public function __construct( bool $register_hooks = true ) {
		if ( ! $register_hooks ) {
			return;
		}
		if ( ! Shojaei_SEO_Helpers::is_module_enabled( 'indexnow' ) ) {
			return;
		}
		// اگر هسته سئو در حالت Passive باشد، اتوماتیک خاموش می‌ماند.
		if ( class_exists( 'SEO_Core_Loader' ) ) {
			$loader = SEO_Core_Loader::instance();
			$mod    = $loader ? $loader->get_module( 'indexnow' ) : null;
			if ( $mod && class_exists( 'SEO_Core_Installer' ) && ! SEO_Core_Installer::is_module_enabled( 'indexnow' ) ) {
				return;
			}
			if ( $mod && $mod->is_passive() ) {
				return;
			}
		}

		add_action( 'save_post_product', array( $this, 'on_product_save' ), 20, 2 );
		add_action( 'woocommerce_product_set_stock_status', array( $this, 'on_stock_change' ), 20, 3 );
		add_action( 'woocommerce_update_product', array( $this, 'on_product_update' ), 20, 1 );
	}

	/**
	 * Trigger on new/updated product publish.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function on_product_save( int $post_id, $post ): void {
		if ( class_exists( 'Shojaei_SEO_Helpers' ) && Shojaei_SEO_Helpers::should_skip_product_save_side_effects() ) {
			return;
		}
		if ( 'publish' !== $post->post_status ) {
			return;
		}
		$this->notify_url( get_permalink( $post_id ) );
	}

	/**
	 * Trigger on stock status change.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $status     Stock status.
	 * @param object $product    Product object.
	 */
	public function on_stock_change( int $product_id, string $status, $product ): void {
		if ( class_exists( 'Shojaei_SEO_Helpers' ) && Shojaei_SEO_Helpers::should_skip_product_save_side_effects() ) {
			return;
		}
		$this->notify_url( get_permalink( $product_id ) );
	}

	/**
	 * Trigger on product update.
	 *
	 * @param int $product_id Product ID.
	 */
	public function on_product_update( int $product_id ): void {
		if ( class_exists( 'Shojaei_SEO_Helpers' ) && Shojaei_SEO_Helpers::should_skip_product_save_side_effects() ) {
			return;
		}
		$this->notify_url( get_permalink( $product_id ) );
	}

	/**
	 * Send IndexNow + Google sitemap ping for a URL.
	 *
	 * @param string $url Page URL.
	 */
	public function notify_url( string $url ): void {
		if ( empty( $url ) ) {
			return;
		}
		$this->submit_urls( array( $url ), false );
		$this->ping_google_sitemap();
	}

	/**
	 * ارسال گروهی دستی/خودکار به IndexNow.
	 *
	 * @param string[] $urls           لیست URL.
	 * @param bool     $record_history ثبت در تاریخچه.
	 * @return array{ok:bool,message:string,submitted:int,skipped:int,http?:int}
	 */
	public function submit_urls( array $urls, bool $record_history = true ): array {
		$key  = class_exists( 'SEO_Core_Installer' )
			? SEO_Core_Installer::get_indexnow_key()
			: (string) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_indexnow_key', '' );
		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );

		if ( '' === $key || '' === $host ) {
			return array(
				'ok'      => false,
				'message' => __( 'کلید IndexNow تنظیم نشده است. از تنظیمات یا تب نمایه‌سازی فوری کلید را ذخیره کنید.', 'shojaei-seo-for-woo' ),
				'submitted' => 0,
				'skipped'   => 0,
			);
		}

		$clean   = $this->sanitize_url_list( $urls, $host );
		$valid   = $clean['valid'];
		$skipped = $clean['skipped'];

		if ( empty( $valid ) ) {
			return array(
				'ok'        => false,
				'message'   => __( 'هیچ URL معتبری برای این دامنه پیدا نشد.', 'shojaei-seo-for-woo' ),
				'submitted' => 0,
				'skipped'   => $skipped,
			);
		}

		$http_ok = 0;
		$chunks  = array_chunk( $valid, self::CHUNK_SIZE );
		foreach ( $chunks as $chunk ) {
			$code = $this->post_indexnow_chunk( $host, $key, $chunk );
			if ( $code >= 200 && $code < 300 ) {
				++$http_ok;
			}
		}

		$count = count( $valid );
		$this->increment_daily_stat( $count );

		if ( $record_history ) {
			self::push_history(
				array(
					'at'      => current_time( 'mysql' ),
					'count'   => $count,
					'skipped' => $skipped,
					'ok'      => $http_ok > 0,
					'sample'  => array_slice( $valid, 0, 5 ),
					'old_url' => '',
					'new_url' => $valid[0] ?? '',
					'source'  => 'manual',
				)
			);
		}

		if ( class_exists( 'SEO_Core_DB' ) ) {
			SEO_Core_DB::log(
				'indexnow',
				$http_ok > 0 ? 'info' : 'warning',
				sprintf( 'ارسال IndexNow: %d URL (ردشده: %d)', $count, $skipped ),
				array( 'chunks_ok' => $http_ok, 'chunks' => count( $chunks ) )
			);
		}

		return array(
			'ok'        => $http_ok > 0,
			'message'   => $http_ok > 0
				? sprintf(
					/* translators: 1: submitted 2: skipped */
					__( '%1$d پیوند به IndexNow ارسال شد (%2$d رد شد).', 'shojaei-seo-for-woo' ),
					$count,
					$skipped
				)
				: __( 'ارسال به IndexNow ناموفق بود. اتصال سرور یا کلید را بررسی کنید.', 'shojaei-seo-for-woo' ),
			'submitted' => $count,
			'skipped'   => $skipped,
		);
	}

	/**
	 * نرمال‌سازی و اعتبارسنجی لیست URL (همان هاست، سقف امن).
	 *
	 * @param string[] $urls Raw.
	 * @param string   $host Host.
	 * @return array{valid:string[],skipped:int}
	 */
	public function sanitize_url_list( array $urls, string $host = '' ): array {
		if ( '' === $host ) {
			$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		}
		$valid   = array();
		$skipped = 0;
		$seen    = array();

		foreach ( $urls as $raw ) {
			$raw = trim( (string) $raw );
			if ( '' === $raw ) {
				continue;
			}
			$url = esc_url_raw( $raw );
			if ( '' === $url || ! wp_http_validate_url( $url ) ) {
				++$skipped;
				continue;
			}
			$uhost = (string) wp_parse_url( $url, PHP_URL_HOST );
			if ( '' === $uhost || 0 !== strcasecmp( $uhost, $host ) ) {
				++$skipped;
				continue;
			}
			if ( isset( $seen[ $url ] ) ) {
				continue;
			}
			$seen[ $url ] = true;
			$valid[]      = $url;
			if ( count( $valid ) >= self::MAX_URLS ) {
				break;
			}
		}

		return array(
			'valid'   => $valid,
			'skipped' => $skipped,
		);
	}

	/**
	 * POST یک دسته به endpointهای IndexNow.
	 *
	 * @param string   $host Host.
	 * @param string   $key  Key.
	 * @param string[] $urls URLs.
	 * @return int Best HTTP code.
	 */
	private function post_indexnow_chunk( string $host, string $key, array $urls ): int {
		$body = wp_json_encode(
			array(
				'host'        => $host,
				'key'         => $key,
				'keyLocation' => home_url( '/' . $key . '.txt' ),
				'urlList'     => array_values( $urls ),
			)
		);

		$best = 0;
		foreach ( $this->endpoints as $endpoint ) {
			$response = wp_remote_post(
				$endpoint,
				array(
					'timeout' => 15,
					'headers' => array( 'Content-Type' => 'application/json; charset=utf-8' ),
					'body'    => $body,
				)
			);
			if ( is_wp_error( $response ) ) {
				continue;
			}
			$code = (int) wp_remote_retrieve_response_code( $response );
			// 200 OK / 202 Accepted.
			if ( $code > $best ) {
				$best = $code;
			}
			if ( $code >= 200 && $code < 300 ) {
				return $code;
			}
		}
		return $best;
	}

	/**
	 * Ping Google with sitemap URL.
	 */
	private function ping_google_sitemap(): void {
		$sitemap_url = home_url( '/sitemap_index.xml' );
		if ( class_exists( 'SEO_Core_Sitemap' ) ) {
			$sm = new SEO_Core_Sitemap();
			if ( $sm->can_emit() ) {
				$sitemap_url = $sm->public_url( 'index' );
			}
		}

		wp_remote_get(
			'https://www.google.com/ping?sitemap=' . rawurlencode( $sitemap_url ),
			array( 'timeout' => 10 )
		);

		// ارسال رسمی به Search Console API در صورت آمادگی GSC (بدون سرور ثالث).
		if (
			class_exists( 'Shojaei_SEO_GSC' )
			&& Shojaei_SEO_GSC::is_ready()
			&& 'yes' === get_option( 'shojaei_seo_gsc_auto_sitemap_submit', 'yes' )
		) {
			Shojaei_SEO_GSC::schedule_sitemap_submit( 60 );
		}
	}

	/**
	 * Increment daily indexing stat.
	 *
	 * @param int $by Count.
	 */
	private function increment_daily_stat( int $by = 1 ): void {
		$today = gmdate( 'Y-m-d' );
		$saved = Shojaei_SEO_Helpers::get_option( 'shojaei_seo_stats_indexed_date', '' );
		$by    = max( 1, $by );

		if ( $saved !== $today ) {
			update_option( 'shojaei_seo_stats_indexed_date', $today );
			update_option( 'shojaei_seo_stats_indexed_today', $by );
		} else {
			$cur = (int) get_option( 'shojaei_seo_stats_indexed_today', 0 );
			update_option( 'shojaei_seo_stats_indexed_today', $cur + $by );
		}
	}

	/**
	 * افزودن به تاریخچه (حداکثر ۵۰ مورد).
	 *
	 * @param array<string,mixed> $row Row.
	 */
	public static function push_history( array $row ): void {
		$hist = get_option( self::HISTORY_OPTION, array() );
		if ( ! is_array( $hist ) ) {
			$hist = array();
		}
		array_unshift( $hist, $row );
		$hist = array_slice( $hist, 0, 50 );
		update_option( self::HISTORY_OPTION, $hist, false );
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_history(): array {
		$hist = get_option( self::HISTORY_OPTION, array() );
		return is_array( $hist ) ? $hist : array();
	}

	/**
	 * پاک کردن تاریخچه.
	 */
	public static function clear_history(): void {
		delete_option( self::HISTORY_OPTION );
	}

	/**
	 * صف پیشنهاد IndexNow برای تغییر URL (قدیم → جدید) — بدون ارسال خودکار.
	 *
	 * @param string               $old_url Old URL.
	 * @param string               $new_url New URL.
	 * @param array<string,mixed>  $meta    Extra (post_id, reason, title).
	 * @return array{ok:bool,id?:string,message:string,duplicate?:bool}
	 */
	public static function queue_suggestion( string $old_url, string $new_url, array $meta = array() ): array {
		$old_url = esc_url_raw( $old_url );
		$new_url = esc_url_raw( $new_url );
		if ( '' === $new_url && '' === $old_url ) {
			return array( 'ok' => false, 'message' => __( 'آدرس معتبری برای پیشنهاد نیست.', 'shojaei-seo-for-woo' ) );
		}

		$pending = self::get_pending();
		foreach ( $pending as $row ) {
			if ( (string) ( $row['old_url'] ?? '' ) === $old_url && (string) ( $row['new_url'] ?? '' ) === $new_url ) {
				return array(
					'ok'        => true,
					'duplicate' => true,
					'id'        => (string) ( $row['id'] ?? '' ),
					'message'   => __( 'این پیشنهاد از قبل در صف است.', 'shojaei-seo-for-woo' ),
				);
			}
		}

		$id  = uniqid( 'in_', true );
		$row = array(
			'id'      => $id,
			'at'      => current_time( 'mysql' ),
			'old_url' => $old_url,
			'new_url' => $new_url,
			'post_id' => absint( $meta['post_id'] ?? 0 ),
			'title'   => sanitize_text_field( (string) ( $meta['title'] ?? '' ) ),
			'reason'  => sanitize_text_field( (string) ( $meta['reason'] ?? __( 'تغییر آدرس', 'shojaei-seo-for-woo' ) ) ),
			'source'  => sanitize_key( (string) ( $meta['source'] ?? 'manual' ) ),
		);
		array_unshift( $pending, $row );
		$pending = array_slice( $pending, 0, self::MAX_PENDING );
		update_option( self::PENDING_OPTION, $pending, false );

		return array(
			'ok'      => true,
			'id'      => $id,
			'message' => __( 'پیشنهاد IndexNow در صف تأیید قرار گرفت.', 'shojaei-seo-for-woo' ),
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_pending(): array {
		$rows = get_option( self::PENDING_OPTION, array() );
		return is_array( $rows ) ? array_values( $rows ) : array();
	}

	/**
	 * ساخت پیشنهاد از ریدایرکت‌های نامک اخیر که هنوز در صف/تاریخچه نیستند.
	 *
	 * @param int $limit Max.
	 * @return array{ok:bool,message:string,added:int,pending:array}
	 */
	public static function suggest_from_slug_redirects( int $limit = 40 ): array {
		$added = 0;
		if ( ! class_exists( 'Shojaei_SEO_Slug' ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'ماژول نامک در دسترس نیست.', 'shojaei-seo-for-woo' ),
				'added'   => 0,
				'pending' => self::get_pending(),
			);
		}

		$redirects = Shojaei_SEO_Slug::list_redirects( max( 10, min( 100, $limit ) ) );
		foreach ( $redirects as $r ) {
			if ( empty( $r->is_active ) ) {
				continue;
			}
			$old = (string) ( $r->old_url ?? '' );
			$new = (string) ( $r->new_url ?? '' );
			if ( '' === $old || '' === $new || $old === $new ) {
				continue;
			}
			$pid = (int) ( $r->product_id ?? 0 );
			$res = self::queue_suggestion(
				$old,
				$new,
				array(
					'post_id' => $pid,
					'title'   => $pid ? get_the_title( $pid ) : '',
					'reason'  => __( 'ریدایرکت نامک (قدیم → جدید)', 'shojaei-seo-for-woo' ),
					'source'  => 'slug_redirect',
				)
			);
			if ( ! empty( $res['ok'] ) && empty( $res['duplicate'] ) ) {
				++$added;
			}
		}

		return array(
			'ok'      => true,
			'message' => sprintf(
				/* translators: %d: added */
				__( '%d پیشنهاد جدید از ریدایرکت‌های نامک به صف اضافه شد.', 'shojaei-seo-for-woo' ),
				$added
			),
			'added'   => $added,
			'pending' => self::get_pending(),
		);
	}

	/**
	 * حذف پیشنهادهای انتخاب‌شده.
	 *
	 * @param string[] $ids IDs.
	 */
	public static function dismiss_pending( array $ids ): array {
		$ids = array_filter( array_map( 'strval', $ids ) );
		if ( empty( $ids ) ) {
			return array( 'ok' => false, 'message' => __( 'موردی انتخاب نشده.', 'shojaei-seo-for-woo' ) );
		}
		$want    = array_fill_keys( $ids, true );
		$pending = self::get_pending();
		$kept    = array();
		$removed = 0;
		foreach ( $pending as $row ) {
			$id = (string) ( $row['id'] ?? '' );
			if ( isset( $want[ $id ] ) ) {
				++$removed;
				continue;
			}
			$kept[] = $row;
		}
		update_option( self::PENDING_OPTION, $kept, false );
		return array(
			'ok'      => true,
			'message' => sprintf(
				/* translators: %d: removed */
				__( '%d پیشنهاد حذف شد.', 'shojaei-seo-for-woo' ),
				$removed
			),
			'removed' => $removed,
		);
	}

	/**
	 * تأیید و ارسال پیشنهادها به IndexNow (قدیم + جدید به‌صورت جدا).
	 *
	 * @param string[] $ids IDs.
	 * @return array{ok:bool,message:string,submitted?:int}
	 */
	public function confirm_pending( array $ids ): array {
		$ids = array_filter( array_map( 'strval', $ids ) );
		if ( empty( $ids ) ) {
			return array( 'ok' => false, 'message' => __( 'موردی انتخاب نشده.', 'shojaei-seo-for-woo' ) );
		}
		$want    = array_fill_keys( $ids, true );
		$pending = self::get_pending();
		$picked  = array();
		$kept    = array();
		foreach ( $pending as $row ) {
			$id = (string) ( $row['id'] ?? '' );
			if ( isset( $want[ $id ] ) ) {
				$picked[] = $row;
			} else {
				$kept[] = $row;
			}
		}
		if ( empty( $picked ) ) {
			return array( 'ok' => false, 'message' => __( 'پیشنهاد انتخاب‌شده پیدا نشد.', 'shojaei-seo-for-woo' ) );
		}

		$urls   = array();
		$pairs  = array();
		foreach ( $picked as $row ) {
			$old = (string) ( $row['old_url'] ?? '' );
			$new = (string) ( $row['new_url'] ?? '' );
			if ( '' !== $old ) {
				$urls[] = $old;
			}
			if ( '' !== $new ) {
				$urls[] = $new;
			}
			$pairs[] = array(
				'old_url' => $old,
				'new_url' => $new,
				'title'   => (string) ( $row['title'] ?? '' ),
			);
		}

		$result = $this->submit_urls( $urls, false );
		if ( ! empty( $result['ok'] ) ) {
			update_option( self::PENDING_OPTION, $kept, false );
			self::push_history(
				array(
					'at'      => current_time( 'mysql' ),
					'count'   => (int) ( $result['submitted'] ?? count( $urls ) ),
					'skipped' => (int) ( $result['skipped'] ?? 0 ),
					'ok'      => true,
					'sample'  => array_slice( $urls, 0, 5 ),
					'old_url' => (string) ( $pairs[0]['old_url'] ?? '' ),
					'new_url' => (string) ( $pairs[0]['new_url'] ?? '' ),
					'pairs'   => array_slice( $pairs, 0, 10 ),
					'source'  => 'confirm',
				)
			);
			$result['message'] = sprintf(
				/* translators: 1: submitted, 2: pairs */
				__( '%1$d پیوند (از %2$d تغییر آدرس) به IndexNow ارسال شد.', 'shojaei-seo-for-woo' ),
				(int) ( $result['submitted'] ?? 0 ),
				count( $pairs )
			);
		}
		return $result;
	}
}
