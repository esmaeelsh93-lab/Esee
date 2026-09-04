<?php
/**
 * SEO Core — کلاس پایه همه ماژول‌ها.
 *
 * هر ماژول باید این کلاس را extend کند تا Loader بتواند
 * فعال/غیرفعال‌سازی، حالت Passive و ضدتداخل را یکسان مدیریت کند.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class SEO_Core_Module
 */
abstract class SEO_Core_Module {

	/**
	 * شناسه یکتای ماژول (مثلاً sitemap).
	 */
	abstract public function get_id(): string;

	/**
	 * برچسب فارسی برای UI.
	 */
	abstract public function get_label(): string;

	/**
	 * توضیح کوتاه آموزشی.
	 */
	abstract public function get_description(): string;

	/**
	 * آیا این ماژول به‌طور کلی روشن است؟
	 */
	public function is_enabled(): bool {
		if ( class_exists( 'SEO_Core_Installer' ) ) {
			return SEO_Core_Installer::is_module_enabled( $this->get_id() );
		}
		$opts = get_option( 'shojaei_seo_core_modules', array() );
		if ( ! is_array( $opts ) ) {
			$opts = array();
		}
		$id = $this->get_id();
		if ( array_key_exists( $id, $opts ) ) {
			return (bool) $opts[ $id ];
		}
		return true;
	}

	/**
	 * کلید گزینه Override برای این ماژول.
	 */
	protected function override_option_key(): string {
		return 'shojaei_seo_core_' . $this->get_id() . '_override';
	}

	/**
	 * آیا کاربر «حالت جایگزینی» را روشن کرده؟
	 */
	public function is_override_mode(): bool {
		if ( class_exists( 'SEO_Core_Installer' ) ) {
			return SEO_Core_Installer::is_override_enabled( $this->get_id() );
		}
		$key = $this->override_option_key();
		if ( class_exists( 'Shojaei_SEO_Helpers' ) ) {
			return 'yes' === Shojaei_SEO_Helpers::get_option( $key, 'no' );
		}
		return 'yes' === get_option( $key, 'no' );
	}

	/**
	 * افزونه‌های رقیب فعال (Rank Math / Yoast / AIOSEO و …).
	 *
	 * @return array<int,array{file:string,name:string}>
	 */
	public function detect_competitors(): array {
		if ( class_exists( 'Shojaei_SEO_Integration' ) ) {
			return Shojaei_SEO_Integration::detected_seo_plugins();
		}
		return array();
	}

	/**
	 * حالت Passive/Helper: داشبورد فقط، بدون خروجی رقابتی.
	 * تا وقتی Override روشن نباشد و رقیب فعال باشد.
	 */
	public function is_passive(): bool {
		if ( $this->is_override_mode() ) {
			return false;
		}
		return ! empty( $this->detect_competitors() );
	}

	/**
	 * آیا ماژول می‌تواند خروجی فعال (endpoint / cron / rewrite) داشته باشد؟
	 */
	public function can_emit(): bool {
		return $this->is_enabled() && ! $this->is_passive();
	}

	/**
	 * ثبت هوک‌ها — فقط وقتی ماژول enabled است فراخوانی می‌شود.
	 */
	abstract public function boot(): void;

	/**
	 * نصب جدول/گزینه مخصوص ماژول (اختیاری).
	 */
	public function install(): void {}

	/**
	 * پاکسازی کامل ماژول (اختیاری — فقط wipe).
	 */
	public function uninstall(): void {}

	/**
	 * ثبت لاگ سبک در جدول مشترک هسته سئو.
	 *
	 * @param string               $level   info|warning|error.
	 * @param string               $message پیام فارسی.
	 * @param array<string,mixed>  $context زمینه.
	 */
	protected function log( string $level, string $message, array $context = array() ): void {
		if ( class_exists( 'SEO_Core_DB' ) ) {
			SEO_Core_DB::log( $this->get_id(), $level, $message, $context );
		}
	}

	/**
	 * خواندن/نوشتن Transient با پیشوند ثابت (کش خروجی‌ها).
	 *
	 * @param string $key کلید نسبی.
	 * @return mixed
	 */
	protected function cache_get( string $key ) {
		return get_transient( $this->cache_key( $key ) );
	}

	/**
	 * @param string $key        کلید.
	 * @param mixed  $value      مقدار.
	 * @param int    $expiration ثانیه.
	 */
	protected function cache_set( string $key, $value, int $expiration = HOUR_IN_SECONDS ): void {
		set_transient( $this->cache_key( $key ), $value, max( 60, $expiration ) );
	}

	/**
	 * @param string $key کلید.
	 */
	protected function cache_delete( string $key ): void {
		delete_transient( $this->cache_key( $key ) );
	}

	/**
	 * @param string $key کلید نسبی.
	 */
	protected function cache_key( string $key ): string {
		return 'shojaei_seo_core_' . $this->get_id() . '_' . sanitize_key( $key );
	}
}
