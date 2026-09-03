<?php
/**
 * ماژول Advanced Analytics & Google Hub — هسته سئو.
 *
 * گسترش لایه موجود GSC + افزودن GA4 و پیشنهاد کلمه کلیدی.
 * داده فقط به APIهای رسمی گوگل ارسال می‌شود.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SEO_Core_Advanced_Analytics_Module
 */
class SEO_Core_Advanced_Analytics_Module extends SEO_Core_Module {

	public const ID = 'advanced-analytics';

	public const OPTION_AUTO_SITEMAP = 'shojaei_seo_gsc_auto_sitemap_submit';
	public const OPTION_DEGRADED     = 'seo_core_advanced_analytics_degraded';

	/**
	 * {@inheritdoc}
	 */
	public function get_id(): string {
		return self::ID;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_label(): string {
		return __( 'تحلیل و هاب گوگل', 'shojaei-seo-for-woo' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_description(): string {
		return __( 'GA4، Search Console (کلید JSON موجود)، ارسال نقشه سایت به API گوگل، و پیشنهاد رایگان کلمات کلیدی فارسی.', 'shojaei-seo-for-woo' );
	}

	/**
	 * مکمل عملیاتی است — با Rank Math/Yoast تداخل خروجی ندارد.
	 * Passive فقط وقتی پیش‌نیازها خراب باشند (حالت degraded).
	 */
	public function is_passive(): bool {
		return $this->is_degraded();
	}

	/**
	 * آیا ماژول به‌خاطر پیش‌نیاز در حالت کمکی/هشدار است؟
	 */
	public function is_degraded(): bool {
		$flag = get_option( self::OPTION_DEGRADED, '' );
		return is_string( $flag ) && '' !== $flag;
	}

	/**
	 * پیام هشدار degraded.
	 */
	public function degraded_message(): string {
		$msg = get_option( self::OPTION_DEGRADED, '' );
		return is_string( $msg ) ? $msg : '';
	}

	/**
	 * {@inheritdoc}
	 */
	public function install(): void {
		if ( false === get_option( Shojaei_SEO_GA4::OPTION_ID, false ) ) {
			add_option( Shojaei_SEO_GA4::OPTION_ID, '', '', false );
		}
		if ( false === get_option( Shojaei_SEO_GA4::OPTION_ENABLED, false ) ) {
			add_option( Shojaei_SEO_GA4::OPTION_ENABLED, 'yes', '', false );
		}
		if ( false === get_option( self::OPTION_AUTO_SITEMAP, false ) ) {
			add_option( self::OPTION_AUTO_SITEMAP, 'yes', '', false );
		}
		$this->heal_prerequisites();
	}

	/**
	 * بررسی پیش‌نیازها و تنظیم Passive/degraded.
	 *
	 * @return array{ok:bool,message:string,issues:string[]}
	 */
	public function heal_prerequisites(): array {
		$issues = array();

		if ( ! class_exists( 'Shojaei_SEO_GSC' ) ) {
			$issues[] = __( 'کلاس Shojaei_SEO_GSC در دسترس نیست.', 'shojaei-seo-for-woo' );
		} else {
			$dir = Shojaei_SEO_GSC::ensure_private_dir();
			if ( is_wp_error( $dir ) ) {
				$issues[] = $dir->get_error_message();
			}
		}

		if ( ! function_exists( 'openssl_sign' ) ) {
			$issues[] = __( 'افزونه OpenSSL برای JWT سرویس‌اکانت گوگل لازم است.', 'shojaei-seo-for-woo' );
		}

		if ( class_exists( 'SEO_Core_DB' ) ) {
			SEO_Core_DB::install();
			global $wpdb;
			$reports = SEO_Core_DB::reports_table();
			$found   = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $reports ) );
			if ( $found !== $reports ) {
				$issues[] = __( 'جدول گزارش‌های هسته سئو در دسترس نیست.', 'shojaei-seo-for-woo' );
			}
		}

		if ( $issues ) {
			$msg = implode( ' ', $issues );
			update_option( self::OPTION_DEGRADED, $msg, false );
			return array(
				'ok'      => false,
				'message' => $msg,
				'issues'  => $issues,
			);
		}

		delete_option( self::OPTION_DEGRADED );
		return array(
			'ok'      => true,
			'message' => __( 'پیش‌نیازهای هاب گوگل سالم است.', 'shojaei-seo-for-woo' ),
			'issues'  => array(),
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function boot(): void {
		// بررسی سبک — ترمیم سنگین فقط در install / دکمه heal.
		if ( ! function_exists( 'openssl_sign' ) ) {
			update_option(
				self::OPTION_DEGRADED,
				__( 'افزونه OpenSSL برای JWT سرویس‌اکانت گوگل لازم است.', 'shojaei-seo-for-woo' ),
				false
			);
		} elseif ( $this->is_degraded() && false !== strpos( $this->degraded_message(), 'OpenSSL' ) ) {
			delete_option( self::OPTION_DEGRADED );
		}

		if ( class_exists( 'Shojaei_SEO_GA4' ) && $this->is_enabled() && ! $this->is_degraded() ) {
			Shojaei_SEO_GA4::register_hooks();
		}

		add_action( 'seo_core_sitemap_invalidated', array( $this, 'on_sitemap_invalidated' ) );
		add_action( 'shojaei_seo_gsc_submit_sitemap', array( $this, 'cron_submit_sitemap' ) );

		if ( is_admin() ) {
			add_action( 'wp_ajax_shojaei_seo_advanced_analytics', array( $this, 'ajax' ) );
			add_action( 'wp_ajax_damavand_keyword_suggest', array( $this, 'ajax_keyword_suggest' ) );
		}
	}

	/**
	 * پس از باطل شدن کش نقشه — ارسال زمان‌بندی‌شده به GSC.
	 */
	public function on_sitemap_invalidated(): void {
		if ( $this->is_degraded() || ! $this->is_enabled() ) {
			return;
		}
		if ( 'yes' !== get_option( self::OPTION_AUTO_SITEMAP, 'yes' ) ) {
			return;
		}
		if ( ! class_exists( 'Shojaei_SEO_GSC' ) || ! Shojaei_SEO_GSC::is_ready() ) {
			return;
		}
		Shojaei_SEO_GSC::schedule_sitemap_submit( 180 );
	}

	/**
	 * Cron: ارسال نقشه.
	 */
	public function cron_submit_sitemap(): void {
		if ( ! class_exists( 'Shojaei_SEO_GSC' ) ) {
			return;
		}
		$result = Shojaei_SEO_GSC::submit_sitemap();
		if ( is_wp_error( $result ) ) {
			$this->log( 'warning', $result->get_error_message() );
		} else {
			$this->log( 'info', __( 'نقشه سایت به Search Console ارسال شد.', 'shojaei-seo-for-woo' ) );
		}
	}

	/**
	 * پیشنهاد کلمات کلیدی از Google Suggest (فقط سرور → گوگل).
	 *
	 * @param string $keyword کلمه.
	 * @return array{ok:bool,keyword:string,suggestions:string[],message?:string}
	 */
	public static function fetch_keyword_suggestions( string $keyword ): array {
		$keyword = sanitize_text_field( $keyword );
		$keyword = trim( $keyword );
		if ( '' === $keyword || mb_strlen( $keyword ) < 2 ) {
			return array(
				'ok'          => false,
				'keyword'     => $keyword,
				'suggestions' => array(),
				'message'     => __( 'کلمه کلیدی حداقل ۲ نویسه باشد.', 'shojaei-seo-for-woo' ),
			);
		}

		$cache_key = 'damavand_kw_' . md5( mb_strtolower( $keyword, 'UTF-8' ) );
		$cached    = get_transient( $cache_key );
		if ( is_array( $cached ) && isset( $cached['suggestions'] ) ) {
			return array(
				'ok'          => true,
				'keyword'     => $keyword,
				'suggestions' => (array) $cached['suggestions'],
				'cached'      => true,
			);
		}

		$url = add_query_arg(
			array(
				'client' => 'chrome',
				'hl'     => 'fa',
				'q'      => $keyword,
			),
			'https://suggestqueries.google.com/complete/search'
		);

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 12,
				'headers' => array(
					'Accept'     => 'application/json',
					'User-Agent' => 'DamavandSEO/' . ( defined( 'DAMAVAND_SEO_VERSION' ) ? DAMAVAND_SEO_VERSION : '1.0' ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'          => false,
				'keyword'     => $keyword,
				'suggestions' => array(),
				'message'     => $response->get_error_message(),
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = (string) wp_remote_retrieve_body( $response );
		if ( $code < 200 || $code >= 300 || '' === $body ) {
			return array(
				'ok'          => false,
				'keyword'     => $keyword,
				'suggestions' => array(),
				'message'     => __( 'پاسخ پیشنهاد گوگل نامعتبر بود.', 'shojaei-seo-for-woo' ),
			);
		}

		$data = json_decode( $body, true );
		$raw  = ( is_array( $data ) && isset( $data[1] ) && is_array( $data[1] ) ) ? $data[1] : array();
		$out  = array();
		foreach ( $raw as $item ) {
			if ( is_array( $item ) ) {
				$item = $item[0] ?? '';
			}
			$s = sanitize_text_field( (string) $item );
			if ( '' !== $s && ! in_array( $s, $out, true ) ) {
				$out[] = $s;
			}
			if ( count( $out ) >= 12 ) {
				break;
			}
		}

		set_transient( $cache_key, array( 'suggestions' => $out ), 6 * HOUR_IN_SECONDS );

		return array(
			'ok'          => true,
			'keyword'     => $keyword,
			'suggestions' => $out,
			'cached'      => false,
		);
	}

	/**
	 * AJAX عمومی ماژول.
	 */
	public function ajax(): void {
		check_ajax_referer( 'shojaei_seo_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_options' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) ) );
		}

		$action = sanitize_key( wp_unslash( $_POST['aa_action'] ?? '' ) );

		switch ( $action ) {
			case 'save_ga4':
				$id      = isset( $_POST['measurement_id'] ) ? sanitize_text_field( wp_unslash( $_POST['measurement_id'] ) ) : '';
				$enabled = ! empty( $_POST['enabled'] );
				$force   = ! empty( $_POST['force'] );
				$clean   = Shojaei_SEO_GA4::save( $id, $enabled, $force );
				if ( '' === $clean && '' !== trim( $id ) ) {
					wp_send_json_error( array( 'message' => __( 'فرمت Measurement ID نامعتبر است (مثال: G-XXXXXXX).', 'shojaei-seo-for-woo' ) ) );
				}
				wp_send_json_success(
					array(
						'message'        => __( 'تنظیمات GA4 ذخیره شد.', 'shojaei-seo-for-woo' ),
						'measurement_id' => $clean,
					)
				);

			case 'save_sitemap_auto':
				$on = ! empty( $_POST['auto_sitemap'] );
				update_option( self::OPTION_AUTO_SITEMAP, $on ? 'yes' : 'no', false );
				wp_send_json_success( array( 'message' => __( 'تنظیم ارسال خودکار نقشه ذخیره شد.', 'shojaei-seo-for-woo' ) ) );

			case 'submit_sitemap':
				if ( ! class_exists( 'Shojaei_SEO_GSC' ) ) {
					wp_send_json_error( array( 'message' => __( 'GSC در دسترس نیست.', 'shojaei-seo-for-woo' ) ) );
				}
				$result = Shojaei_SEO_GSC::submit_sitemap();
				if ( is_wp_error( $result ) ) {
					wp_send_json_error( array( 'message' => $result->get_error_message() ) );
				}
				$last = get_option( 'shojaei_seo_gsc_last_sitemap_submit', array() );
				wp_send_json_success(
					array(
						'message' => __( 'نقشه سایت با موفقیت به Search Console ارسال شد.', 'shojaei-seo-for-woo' ),
						'last'    => $last,
					)
				);

			case 'heal':
				$report = $this->heal_prerequisites();
				if ( empty( $report['ok'] ) ) {
					wp_send_json_error( $report );
				}
				wp_send_json_success( $report );

			case 'search_analytics':
				if ( ! class_exists( 'Shojaei_SEO_GSC' ) ) {
					wp_send_json_error( array( 'message' => __( 'GSC در دسترس نیست.', 'shojaei-seo-for-woo' ) ) );
				}
				$result = Shojaei_SEO_GSC::search_analytics_query(
					array(
						'dimension'  => sanitize_key( wp_unslash( $_POST['dimension'] ?? 'query' ) ),
						'days'       => absint( $_POST['days'] ?? 28 ),
						'row_limit'  => absint( $_POST['row_limit'] ?? 25 ),
						'search_type'=> sanitize_key( wp_unslash( $_POST['search_type'] ?? 'web' ) ),
						'force'      => ! empty( $_POST['force'] ),
					)
				);
				if ( is_wp_error( $result ) ) {
					wp_send_json_error( array( 'message' => $result->get_error_message() ) );
				}
				wp_send_json_success( $result );

			case 'status':
				wp_send_json_success( $this->status_payload() );

			default:
				wp_send_json_error( array( 'message' => __( 'عملیات نامعتبر.', 'shojaei-seo-for-woo' ) ) );
		}
	}

	/**
	 * AJAX پیشنهاد کلمه کلیدی.
	 */
	public function ajax_keyword_suggest(): void {
		check_ajax_referer( 'shojaei_seo_admin_nonce', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) ) );
		}

		// محدودیت نرخ ساده برای هر کاربر.
		$uid  = get_current_user_id();
		$key  = 'damavand_kw_rl_' . $uid;
		$hits = (int) get_transient( $key );
		if ( $hits >= 30 ) {
			wp_send_json_error( array( 'message' => __( 'تعداد درخواست پیشنهاد بیش از حد است. کمی بعد دوباره تلاش کنید.', 'shojaei-seo-for-woo' ) ) );
		}
		set_transient( $key, $hits + 1, MINUTE_IN_SECONDS );

		if ( ! $this->is_enabled() || $this->is_degraded() ) {
			wp_send_json_error( array( 'message' => __( 'ماژول تحلیل در حالت Passive/خاموش است.', 'shojaei-seo-for-woo' ) ) );
		}
		$kw     = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : '';
		$result = self::fetch_keyword_suggestions( $kw );
		if ( empty( $result['ok'] ) ) {
			wp_send_json_error( $result );
		}
		wp_send_json_success( $result );
	}

	/**
	 * @return array<string,mixed>
	 */
	public function status_payload(): array {
		$gsc_ok = class_exists( 'Shojaei_SEO_GSC' ) && Shojaei_SEO_GSC::is_ready();
		$last   = get_option( 'shojaei_seo_gsc_last_sitemap_submit', array() );
		return array(
			'degraded'         => $this->is_degraded(),
			'degraded_message' => $this->degraded_message(),
			'ga4_id'           => class_exists( 'Shojaei_SEO_GA4' ) ? Shojaei_SEO_GA4::get_measurement_id() : '',
			'ga4_competitor'   => class_exists( 'Shojaei_SEO_GA4' ) && Shojaei_SEO_GA4::has_analytics_competitor(),
			'gsc_ready'        => $gsc_ok,
			'auto_sitemap'     => 'yes' === get_option( self::OPTION_AUTO_SITEMAP, 'yes' ),
			'last_sitemap'     => is_array( $last ) ? $last : array(),
			'settings_gsc_url' => admin_url( 'admin.php?page=shojaei-seo&tab=settings#shojaei-gsc' ),
		);
	}
}
