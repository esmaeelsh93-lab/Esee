<?php
/**
 * Store profile for AI prompts (Persian WooCommerce).
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_Store_Profile
 */
class Shojaei_SEO_Store_Profile {

	public const OPT_NAME     = 'shojaei_seo_store_name';
	public const OPT_CITY     = 'shojaei_seo_store_city';
	public const OPT_NICHE    = 'shojaei_seo_store_niche';
	public const OPT_TONE     = 'shojaei_seo_store_tone';
	public const OPT_SUFFIX   = 'shojaei_seo_store_meta_suffix';
	public const OPT_ABOUT    = 'shojaei_seo_store_about';
	public const OPT_VOICE    = 'shojaei_seo_store_voice';
	public const OPT_NEGATIVE = 'shojaei_seo_store_negative_rules';
	public const OPT_SAMPLES  = 'shojaei_seo_store_samples';
	public const OPT_DRAFT    = 'shojaei_seo_ai_draft_mode';

	/**
	 * @return array<string,string>
	 */
	public static function all(): array {
		return array(
			'name'     => self::name(),
			'city'     => self::city(),
			'niche'    => self::niche(),
			'tone'     => self::tone(),
			'suffix'   => self::meta_suffix(),
			'about'    => self::about(),
			'voice'    => self::voice(),
			'negative' => self::negative_rules(),
			'samples'  => self::samples(),
			'url'      => home_url( '/' ),
		);
	}

	public static function name(): string {
		$name = trim( (string) Shojaei_SEO_Helpers::get_option( self::OPT_NAME, '' ) );
		if ( '' === $name ) {
			$name = wp_strip_all_tags( get_bloginfo( 'name' ) );
		}
		return $name;
	}

	public static function city(): string {
		return trim( (string) Shojaei_SEO_Helpers::get_option( self::OPT_CITY, '' ) );
	}

	public static function niche(): string {
		return trim( (string) Shojaei_SEO_Helpers::get_option( self::OPT_NICHE, '' ) );
	}

	/**
	 * Tone key (settings).
	 */
	public static function tone_key(): string {
		$t = trim( (string) Shojaei_SEO_Helpers::get_option( self::OPT_TONE, 'friendly' ) );
		$allowed = array( 'formal', 'friendly', 'expert', 'comparison', 'guide' );
		return in_array( $t, $allowed, true ) ? $t : 'friendly';
	}

	public static function tone(): string {
		$map = array(
			'formal'     => __( 'رسمی و حرفه‌ای', 'shojaei-seo-for-woo' ),
			'friendly'   => __( 'صمیمی فروشگاهی', 'shojaei-seo-for-woo' ),
			'expert'     => __( 'کارشناسی و دقیق', 'shojaei-seo-for-woo' ),
			'comparison' => __( 'مقایسه‌ای و انتخابی', 'shojaei-seo-for-woo' ),
			'guide'      => __( 'راهنمای خرید و پرسش‌محور', 'shojaei-seo-for-woo' ),
		);
		return $map[ self::tone_key() ] ?? $map['friendly'];
	}

	/**
	 * Fixed writing style for prompts (replaces random style_hint).
	 */
	public static function writing_style_hint(): string {
		$map = array(
			'formal'     => 'لحن رسمی و حرفه‌ای — بدون slang، جملات کوتاه و دقیق.',
			'friendly'   => 'لحن صمیمی فروشگاهی — مثل فروشنده حضوری که راهنمایی می‌کند.',
			'expert'     => 'لحن کارشناسی — جزئیات فنی، مقایسه مشخصات، بدون اغراق.',
			'comparison' => 'لحن مقایسه‌ای — مزیت نسبت به جایگزین‌ها، جدول ذهنی مزایا/معایب.',
			'guide'      => 'لحن راهنمای خرید — سوال و جواب، «برای چه کسی مناسب است؟».',
		);
		return $map[ self::tone_key() ] ?? $map['friendly'];
	}

	public static function about(): string {
		return trim( (string) Shojaei_SEO_Helpers::get_option( self::OPT_ABOUT, '' ) );
	}

	public static function voice(): string {
		return trim( (string) Shojaei_SEO_Helpers::get_option( self::OPT_VOICE, '' ) );
	}

	public static function negative_rules(): string {
		return trim( (string) Shojaei_SEO_Helpers::get_option( self::OPT_NEGATIVE, '' ) );
	}

	public static function samples(): string {
		return trim( (string) Shojaei_SEO_Helpers::get_option( self::OPT_SAMPLES, '' ) );
	}

	public static function draft_mode(): bool {
		return 'yes' === (string) Shojaei_SEO_Helpers::get_option( self::OPT_DRAFT, 'yes' );
	}

	public static function meta_suffix(): string {
		$tpl = trim( (string) Shojaei_SEO_Helpers::get_option( self::OPT_SUFFIX, '' ) );
		if ( '' === $tpl ) {
			$tpl = 'خرید {product} از {store}';
		}
		return $tpl;
	}

	/**
	 * Expand template tokens for meta titles.
	 */
	public static function expand_meta_suffix( string $product_title = '' ): string {
		$repl = array(
			'{store}'   => self::name(),
			'{city}'    => self::city(),
			'{niche}'   => self::niche(),
			'{product}' => $product_title,
			'{site}'    => wp_strip_all_tags( get_bloginfo( 'name' ) ),
		);
		$out = strtr( self::meta_suffix(), $repl );
		$out = preg_replace( '/\s+/', ' ', trim( $out ) );
		return is_string( $out ) ? $out : '';
	}

	/**
	 * Block injected into user prompts.
	 */
	public static function prompt_block(): string {
		$lines = array(
			'فروشگاه: ' . self::name(),
		);
		if ( self::city() ) {
			$lines[] = 'شهر: ' . self::city();
		}
		if ( self::niche() ) {
			$lines[] = 'حوزه کاری: ' . self::niche();
		}
		$lines[] = 'لحن: ' . self::tone();
		$lines[] = 'سبک نوشتار: ' . self::writing_style_hint();
		if ( self::about() ) {
			$lines[] = 'درباره فروشگاه: ' . self::about();
		}
		$lines[] = 'الگوی انتهای عنوان متا: «' . self::expand_meta_suffix( 'نام محصول' ) . '»';
		$lines[] = 'در عنوان متا حتماً کلمه «خرید» را طبیعی بیاور.';
		return implode( "\n", $lines );
	}

	/**
	 * Extra rules for system prompt (voice, negative, samples).
	 */
	public static function system_extras(): string {
		$parts = array();
		if ( self::voice() ) {
			$parts[] = "قوانین برند و لحن:\n" . self::voice();
		}
		if ( self::negative_rules() ) {
			$parts[] = "ممنوعیت‌ها (هرگز ننویس):\n" . self::negative_rules();
		}
		if ( self::samples() ) {
			$parts[] = "نمونه سبک نوشتاری فروشگاه (تقلید لحن، نه کپی جمله):\n" . self::samples();
		}
		return $parts ? implode( "\n\n", $parts ) : '';
	}

	/**
	 * Save from settings POST.
	 *
	 * @param array<string,mixed> $post POST data.
	 */
	public static function save_from_post( array $post ): void {
		$text_fields = array(
			self::OPT_NAME   => 'sanitize_text_field',
			self::OPT_CITY   => 'sanitize_text_field',
			self::OPT_NICHE  => 'sanitize_text_field',
			self::OPT_SUFFIX => 'sanitize_text_field',
			self::OPT_TONE   => 'sanitize_key',
		);
		foreach ( $text_fields as $opt => $fn ) {
			if ( ! array_key_exists( $opt, $post ) ) {
				continue;
			}
			$val = call_user_func( $fn, (string) $post[ $opt ] );
			update_option( $opt, $val, false );
		}
		$areas = array(
			self::OPT_ABOUT    => 'sanitize_textarea_field',
			self::OPT_VOICE    => 'sanitize_textarea_field',
			self::OPT_NEGATIVE => 'sanitize_textarea_field',
			self::OPT_SAMPLES  => 'sanitize_textarea_field',
		);
		foreach ( $areas as $opt => $fn ) {
			if ( isset( $post[ $opt ] ) ) {
				update_option( $opt, call_user_func( $fn, (string) $post[ $opt ] ), false );
			}
		}
		update_option( self::OPT_DRAFT, ! empty( $post[ self::OPT_DRAFT ] ) ? 'yes' : 'no', false );
	}
}
