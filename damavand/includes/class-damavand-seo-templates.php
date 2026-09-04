<?php
/**
 * قالب‌های عنوان و توضیح SERP — Damavand.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Damavand_SEO_Templates
 */
final class Damavand_SEO_Templates {

	public const OPT_PRODUCT_TITLE = 'damavand_seo_tpl_product_title';
	public const OPT_PRODUCT_DESC  = 'damavand_seo_tpl_product_desc';
	public const OPT_POST_TITLE    = 'damavand_seo_tpl_post_title';
	public const OPT_POST_DESC     = 'damavand_seo_tpl_post_desc';
	public const OPT_PAGE_TITLE    = 'damavand_seo_tpl_page_title';
	public const OPT_PAGE_DESC     = 'damavand_seo_tpl_page_desc';

	/**
	 * پیش‌فرض‌های مناسب فروشگاه فارسی.
	 *
	 * @return array<string,string>
	 */
	public static function defaults(): array {
		return array(
			self::OPT_PRODUCT_TITLE => '%title% %sep% %sitename%',
			self::OPT_PRODUCT_DESC  => 'خرید %title% با بهترین قیمت از %sitename%. %excerpt%',
			self::OPT_POST_TITLE    => '%title% %sep% %sitename%',
			self::OPT_POST_DESC     => '%excerpt%',
			self::OPT_PAGE_TITLE    => '%title% %sep% %sitename%',
			self::OPT_PAGE_DESC     => '%excerpt%',
		);
	}

	/**
	 * ثبت پیش‌فرض‌ها در activator / self-heal.
	 */
	public static function ensure_defaults(): void {
		foreach ( self::defaults() as $key => $val ) {
			if ( false === get_option( $key, false ) ) {
				add_option( $key, $val, '', false );
			}
		}
	}

	/**
	 * توکن‌های قابل استفاده (برای راهنما).
	 *
	 * @return array<string,string> token => label
	 */
	public static function token_help(): array {
		return array(
			'%title%'     => __( 'عنوان نوشته/محصول', 'shojaei-seo-for-woo' ),
			'%sitename%'  => __( 'نام سایت', 'shojaei-seo-for-woo' ),
			'%sep%'       => __( 'جداکننده عنوان', 'shojaei-seo-for-woo' ),
			'%excerpt%'   => __( 'خلاصه یا ابتدای محتوا', 'shojaei-seo-for-woo' ),
			'%focus%'     => __( 'کلمه کلیدی اصلی', 'shojaei-seo-for-woo' ),
			'%category%'  => __( 'دسته اصلی', 'shojaei-seo-for-woo' ),
			'%sku%'       => __( 'کد محصول (SKU)', 'shojaei-seo-for-woo' ),
			'%price%'     => __( 'قیمت نمایشی محصول', 'shojaei-seo-for-woo' ),
			'%brand%'     => __( 'برند (در صورت وجود)', 'shojaei-seo-for-woo' ),
		);
	}

	/**
	 * کلیدهای قالب برای یک post type.
	 *
	 * @param string $post_type Type.
	 * @return array{title:string,desc:string}|null
	 */
	public static function option_keys_for_type( string $post_type ): ?array {
		$map = array(
			'product' => array(
				'title' => self::OPT_PRODUCT_TITLE,
				'desc'  => self::OPT_PRODUCT_DESC,
			),
			'post'    => array(
				'title' => self::OPT_POST_TITLE,
				'desc'  => self::OPT_POST_DESC,
			),
			'page'    => array(
				'title' => self::OPT_PAGE_TITLE,
				'desc'  => self::OPT_PAGE_DESC,
			),
		);
		return $map[ $post_type ] ?? null;
	}

	/**
	 * خواندن قالب ذخیره‌شده (یا پیش‌فرض).
	 *
	 * @param string $option_key Option.
	 */
	public static function get_template( string $option_key ): string {
		$defaults = self::defaults();
		$fallback = $defaults[ $option_key ] ?? '';
		$val      = get_option( $option_key, $fallback );
		$val      = is_string( $val ) ? trim( $val ) : '';
		return '' !== $val ? $val : $fallback;
	}

	/**
	 * قالب خام عنوان/توضیح برای پست.
	 *
	 * @param int    $post_id Post.
	 * @param string $which   title|desc.
	 */
	public static function get_raw_template_for_post( int $post_id, string $which ): string {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}
		$keys = self::option_keys_for_type( (string) $post->post_type );
		if ( ! $keys || ! isset( $keys[ $which ] ) ) {
			return '';
		}
		return self::get_template( $keys[ $which ] );
	}

	/**
	 * گسترش توکن‌ها.
	 *
	 * @param string               $template Template string.
	 * @param int                  $post_id  Post ID.
	 * @param array<string,string> $override Optional title|excerpt|focus overrides (editor live).
	 */
	public static function expand( string $template, int $post_id, array $override = array() ): string {
		$template = trim( $template );
		if ( '' === $template ) {
			return '';
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return self::cleanup( $template );
		}

		$title = isset( $override['title'] ) ? (string) $override['title'] : (string) $post->post_title;
		$title = trim( wp_strip_all_tags( $title ) );

		$site = wp_strip_all_tags( get_bloginfo( 'name' ) );
		$sep  = class_exists( 'Shojaei_SEO_General_Meta' ) ? Shojaei_SEO_General_Meta::get_separator() : '-';

		$excerpt = isset( $override['excerpt'] ) ? (string) $override['excerpt'] : '';
		if ( '' === $excerpt ) {
			$excerpt = trim( wp_strip_all_tags( (string) $post->post_excerpt ) );
		}
		if ( '' === $excerpt ) {
			$excerpt = wp_trim_words( wp_strip_all_tags( (string) $post->post_content ), 28, '…' );
		}

		$focus = isset( $override['focus'] ) ? (string) $override['focus'] : '';
		if ( '' === $focus && class_exists( 'Damavand_SEO_Meta' ) ) {
			$focus = Damavand_SEO_Meta::get_focus_keyword( $post_id );
		}

		$category = self::primary_category_name( $post );
		$sku      = '';
		$price    = '';
		$brand    = '';

		if ( 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $post_id );
			if ( $product ) {
				$sku = (string) $product->get_sku();
				if ( '' === $category ) {
					$terms = get_the_terms( $post_id, 'product_cat' );
					if ( is_array( $terms ) && ! empty( $terms ) && ! is_wp_error( $terms ) ) {
						$category = (string) $terms[0]->name;
					}
				}
				$price = self::product_price_text( $product );
				$brand = self::product_brand_name( $post_id );
			}
		}

		$map = array(
			'%title%'       => $title,
			'%post_title%'  => $title,
			'%sitename%'    => $site,
			'%site_name%'   => $site,
			'%sep%'         => $sep,
			'%separator%'   => $sep,
			'%excerpt%'     => $excerpt,
			'%focus%'       => $focus,
			'%focuskw%'     => $focus,
			'%category%'    => $category,
			'%sku%'         => $sku,
			'%price%'       => $price,
			'%brand%'       => $brand,
		);

		$out = str_ireplace( array_keys( $map ), array_values( $map ), $template );
		return self::cleanup( $out );
	}

	/**
	 * عنوان از قالب وقتی متای اختصاصی خالی است.
	 *
	 * @param int                  $post_id  Post.
	 * @param array<string,string> $override Overrides.
	 */
	public static function resolve_title( int $post_id, array $override = array() ): string {
		$tpl = self::get_raw_template_for_post( $post_id, 'title' );
		if ( '' === $tpl ) {
			return '';
		}
		return self::expand( $tpl, $post_id, $override );
	}

	/**
	 * توضیح از قالب.
	 *
	 * @param int                  $post_id  Post.
	 * @param array<string,string> $override Overrides.
	 */
	public static function resolve_description( int $post_id, array $override = array() ): string {
		$tpl = self::get_raw_template_for_post( $post_id, 'desc' );
		if ( '' === $tpl ) {
			return '';
		}
		$out = self::expand( $tpl, $post_id, $override );
		// گوگل حدود ۱۵۵–۱۶۰ کاراکتر لاتین؛ برای فارسی کمی کوتاه‌تر نگه می‌داریم.
		if ( mb_strlen( $out, 'UTF-8' ) > 320 ) {
			$out = mb_substr( $out, 0, 317, 'UTF-8' ) . '…';
		}
		return $out;
	}

	/**
	 * پاکسازی فاصله‌های تکراری و جداکننده‌های یتیم.
	 *
	 * @param string $text Text.
	 */
	public static function cleanup( string $text ): string {
		$text = preg_replace( '/%[a-z0-9_]+%/i', '', $text );
		$text = preg_replace( '/\s+/u', ' ', (string) $text );
		$text = preg_replace( '/\s*([\|\-–—·•])(?:\s*\1)+\s*/u', ' $1 ', (string) $text );
		$text = preg_replace( '/^\s*[|\-–—·•]\s*|\s*[|\-–—·•]\s*$/u', '', (string) $text );
		return trim( (string) $text );
	}

	/**
	 * @param WP_Post $post Post.
	 */
	private static function primary_category_name( WP_Post $post ): string {
		$tax = ( 'product' === $post->post_type ) ? 'product_cat' : 'category';
		$terms = get_the_terms( $post->ID, $tax );
		if ( ! is_array( $terms ) || empty( $terms ) || is_wp_error( $terms ) ) {
			return '';
		}
		return (string) $terms[0]->name;
	}

	/**
	 * @param WC_Product $product Product.
	 */
	private static function product_price_text( $product ): string {
		if ( ! is_object( $product ) || ! method_exists( $product, 'get_price' ) ) {
			return '';
		}
		$raw = $product->get_price();
		if ( '' === $raw || null === $raw ) {
			return '';
		}
		if ( function_exists( 'wc_price' ) ) {
			return trim( wp_strip_all_tags( (string) wc_price( $raw ) ) );
		}
		return (string) $raw;
	}

	/**
	 * @param int $post_id Product ID.
	 */
	private static function product_brand_name( int $post_id ): string {
		$taxonomies = array( 'product_brand', 'pwb-brand', 'brand', 'pa_brand', 'pa_brands' );
		foreach ( $taxonomies as $tax ) {
			if ( ! taxonomy_exists( $tax ) ) {
				continue;
			}
			$terms = get_the_terms( $post_id, $tax );
			if ( is_array( $terms ) && ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				return (string) $terms[0]->name;
			}
		}
		return '';
	}

	/**
	 * ذخیره از فرم متای عمومی.
	 */
	public static function save_from_post(): void {
		$title_keys = array(
			self::OPT_PRODUCT_TITLE => 120,
			self::OPT_POST_TITLE    => 120,
			self::OPT_PAGE_TITLE    => 120,
		);
		$desc_keys  = array(
			self::OPT_PRODUCT_DESC => 320,
			self::OPT_POST_DESC    => 320,
			self::OPT_PAGE_DESC    => 320,
		);
		foreach ( $title_keys as $key => $max ) {
			if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				continue;
			}
			$raw = sanitize_text_field( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$raw = mb_substr( $raw, 0, $max, 'UTF-8' );
			update_option( $key, $raw, false );
		}
		foreach ( $desc_keys as $key => $max ) {
			if ( ! isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
				continue;
			}
			$raw = sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$raw = mb_substr( $raw, 0, $max, 'UTF-8' );
			update_option( $key, $raw, false );
		}
	}
}
