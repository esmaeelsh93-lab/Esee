<?php
/**
 * خواندن متای سئو Damavand با اولویت روی کلیدهای مهاجرت‌شده.
 *
 * ترتیب: Damavand → Rank Math → Yoast → AIOSEO → (اختیاری) fallback نوشته.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Damavand_SEO_Meta
 */
class Damavand_SEO_Meta {

	public const TITLE = '_damavand_seo_title';
	public const DESC  = '_damavand_seo_metadesc';
	public const CANON = '_damavand_seo_canonical';
	public const FOCUS = '_damavand_seo_focus_keyword';
	public const OG_TITLE = '_damavand_seo_og_title';
	public const OG_DESC  = '_damavand_seo_og_description';
	public const OG_IMAGE = '_damavand_seo_og_image';
	public const TW_TITLE = '_damavand_seo_twitter_title';
	public const TW_DESC  = '_damavand_seo_twitter_description';
	public const TW_IMAGE = '_damavand_seo_twitter_image';
	public const ROBOTS   = '_damavand_seo_robots';
	public const PILLAR   = '_damavand_seo_pillar';

	/**
	 * نقشه فیلد → کلیدهای منبع (بدون کلید Damavand؛ آن جدا اول خوانده می‌شود).
	 *
	 * @return array<string,string[]>
	 */
	public static function fallback_keys(): array {
		return array(
			'title'    => array( 'rank_math_title', '_yoast_wpseo_title', '_aioseo_title' ),
			'desc'     => array( 'rank_math_description', '_yoast_wpseo_metadesc', '_aioseo_description' ),
			'canonical'=> array( 'rank_math_canonical_url', 'rank_math_canonical', '_yoast_wpseo_canonical', '_aioseo_canonical_url' ),
			'focus'    => array(
				'rank_math_focus_keyword',
				'rank_math_focuskeyword',
				'_yoast_wpseo_focuskw',
				'_aioseo_keywords',
				'_shojaei_seo_focus_keyword',
			),
		);
	}

	/**
	 * آیا Damavand باید تگ‌های سئوی پست را در فرانت چاپ کند؟
	 * وقتی رقیب فعال است، مگر با سوئیچ اجبار، سکوت می‌کند.
	 */
	public static function should_emit_frontend(): bool {
		$has_competitor = class_exists( 'Shojaei_SEO_Helpers' )
			? ( Shojaei_SEO_Helpers::is_rank_math_active() || Shojaei_SEO_Helpers::is_yoast_active() )
			: ( defined( 'RANK_MATH_VERSION' ) || defined( 'WPSEO_VERSION' ) );

		if ( $has_competitor ) {
			$force = class_exists( 'Shojaei_SEO_Helpers' )
				? Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_force_with_competitors', 'no' )
				: get_option( 'shojaei_seo_meta_force_with_competitors', 'no' );
			return 'yes' === $force;
		}

		return true;
	}

	/**
	 * اولین مقدار غیرخالی از لیست کلیدها.
	 *
	 * @param int      $post_id Post ID.
	 * @param string[] $keys    Meta keys.
	 */
	public static function first_meta( int $post_id, array $keys ): string {
		if ( $post_id < 1 ) {
			return '';
		}
		foreach ( $keys as $key ) {
			$raw = get_post_meta( $post_id, $key, true );
			if ( is_array( $raw ) ) {
				$raw = (string) reset( $raw );
			}
			$val = trim( wp_strip_all_tags( (string) $raw ) );
			if ( '' !== $val ) {
				return $val;
			}
		}
		return '';
	}

	/**
	 * پاکسازی placeholderهای Rank Math / Yoast مثل %title%.
	 */
	public static function strip_placeholders( string $text ): string {
		$text = preg_replace( '/%[a-z0-9_]+%/i', '', $text );
		return trim( (string) $text );
	}

	/**
	 * عنوان سئو.
	 *
	 * @param int  $post_id     Post ID.
	 * @param bool $with_post   اگر متا خالی بود از قالب / post_title استفاده شود.
	 */
	public static function get_title( int $post_id, bool $with_post = true ): string {
		$own = self::get_damavand_only( $post_id, 'title' );
		if ( '' !== $own ) {
			return self::expand_text( $own, $post_id );
		}

		$val = self::first_meta( $post_id, self::fallback_keys()['title'] );
		$val = self::strip_placeholders( $val );
		if ( '' !== $val ) {
			return $val;
		}

		if ( $with_post && class_exists( 'Damavand_SEO_Templates' ) ) {
			$from_tpl = Damavand_SEO_Templates::resolve_title( $post_id );
			if ( '' !== $from_tpl ) {
				return $from_tpl;
			}
		}

		if ( $with_post ) {
			$post = get_post( $post_id );
			if ( $post instanceof WP_Post ) {
				return trim( wp_strip_all_tags( (string) $post->post_title ) );
			}
		}
		return '';
	}

	/**
	 * توضیحات متا.
	 *
	 * @param int  $post_id   Post ID.
	 * @param bool $with_excerpt Fallback به قالب / excerpt.
	 */
	public static function get_description( int $post_id, bool $with_excerpt = true ): string {
		$own = self::get_damavand_only( $post_id, 'desc' );
		if ( '' !== $own ) {
			return self::expand_text( $own, $post_id );
		}

		$val = self::first_meta( $post_id, self::fallback_keys()['desc'] );
		$val = self::strip_placeholders( $val );
		if ( '' !== $val ) {
			return $val;
		}

		if ( $with_excerpt && class_exists( 'Damavand_SEO_Templates' ) ) {
			$from_tpl = Damavand_SEO_Templates::resolve_description( $post_id );
			if ( '' !== $from_tpl ) {
				return $from_tpl;
			}
		}

		if ( $with_excerpt ) {
			$post = get_post( $post_id );
			if ( $post instanceof WP_Post && '' !== trim( (string) $post->post_excerpt ) ) {
				return trim( wp_strip_all_tags( (string) $post->post_excerpt ) );
			}
		}
		return '';
	}

	/**
	 * گسترش توکن‌های قالب در متن Damavand.
	 *
	 * @param string $text    Text.
	 * @param int    $post_id Post.
	 */
	public static function expand_text( string $text, int $post_id ): string {
		if ( class_exists( 'Damavand_SEO_Templates' ) ) {
			return Damavand_SEO_Templates::expand( $text, $post_id );
		}
		if ( class_exists( 'Shojaei_SEO_General_Meta' ) ) {
			$text = Shojaei_SEO_General_Meta::apply_sep_tokens( $text );
		}
		return trim( $text );
	}

	/**
	 * آدرس کنونیکال سفارشی (ممکن است خالی باشد).
	 */
	public static function get_canonical( int $post_id ): string {
		$val = self::first_meta( $post_id, array_merge( array( self::CANON ), self::fallback_keys()['canonical'] ) );
		$val = esc_url_raw( $val );
		return is_string( $val ) ? $val : '';
	}

	/**
	 * کلمه کلیدی تمرکزی (اولین مورد اگر چندتایی با کاما باشد).
	 */
	public static function get_focus_keyword( int $post_id ): string {
		$raw = self::first_meta( $post_id, array_merge( array( self::FOCUS ), self::fallback_keys()['focus'] ) );
		if ( '' === $raw ) {
			return '';
		}
		$parts = preg_split( '/\s*,\s*/', $raw );
		$first = is_array( $parts ) ? trim( (string) ( $parts[0] ?? '' ) ) : '';
		return $first;
	}

	/**
	 * فقط مقدار ذخیره‌شده Damavand (بدون fallback رقیب) — برای تشخیص مهاجرت.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $field   title|desc|canonical|focus.
	 */
	public static function get_damavand_only( int $post_id, string $field ): string {
		$map = array(
			'title'     => self::TITLE,
			'desc'      => self::DESC,
			'canonical' => self::CANON,
			'focus'     => self::FOCUS,
		);
		$key = $map[ $field ] ?? '';
		if ( '' === $key ) {
			return '';
		}
		return self::first_meta( $post_id, array( $key ) );
	}

	/**
	 * هوک‌های فرانت برای چاپ title / description / canonical مهاجرت‌شده.
	 */
	public static function register_frontend_hooks(): void {
		add_filter( 'pre_get_document_title', array( __CLASS__, 'filter_document_title' ), 20 );
		add_action( 'wp_head', array( __CLASS__, 'print_meta_description' ), 1 );
		add_action( 'wp_head', array( __CLASS__, 'print_open_graph' ), 2 );
	}

	/**
	 * عنوان سند از متای Damavand (وقتی رقیب مالک نیست).
	 *
	 * @param string $title Current title.
	 */
	public static function filter_document_title( $title ) {
		if ( ! self::should_emit_frontend() || ! is_singular() ) {
			return $title;
		}
		$post_id = (int) get_queried_object_id();
		$custom  = self::get_title( $post_id, true );
		if ( '' === $custom ) {
			return $title;
		}
		return $custom;
	}

	/**
	 * کنونیکال — delegated to damavand_get_canonical_url().
	 *
	 * @param string  $canonical Current.
	 * @param WP_Post $post      Post.
	 * @deprecated Use damavand_get_canonical_url().
	 */
	public static function filter_canonical_url( $canonical, $post = null ) {
		if ( function_exists( 'damavand_get_canonical_url' ) ) {
			$url = damavand_get_canonical_url( $post, is_string( $canonical ) ? $canonical : '' );
			return '' !== $url ? $url : $canonical;
		}
		if ( ! self::should_emit_frontend() ) {
			return $canonical;
		}
		$post_id = 0;
		if ( $post instanceof WP_Post ) {
			$post_id = (int) $post->ID;
		} elseif ( is_singular() ) {
			$post_id = (int) get_queried_object_id();
		}
		if ( $post_id < 1 ) {
			return $canonical;
		}
		$custom = self::get_canonical( $post_id );
		return '' !== $custom ? $custom : $canonical;
	}

	/**
	 * چاپ meta description.
	 */
	public static function print_meta_description(): void {
		if ( ! self::should_emit_frontend() || ! is_singular() ) {
			return;
		}
		$post_id = (int) get_queried_object_id();
		$desc    = self::get_description( $post_id, true );
		if ( '' === $desc ) {
			return;
		}
		echo '<meta name="description" content="' . esc_attr( $desc ) . '" data-damavand-seo="1" />' . "\n";
	}

	/**
	 * Open Graph + Twitter برای وقتی Damavand مالک متا است (مسیر جایگزینی Rank Math).
	 */
	public static function print_open_graph(): void {
		if ( ! self::should_emit_frontend() || ! is_singular() ) {
			return;
		}

		$post_id = (int) get_queried_object_id();
		if ( $post_id < 1 ) {
			return;
		}

		$title = self::get_title( $post_id, true );
		$desc  = self::get_description( $post_id, true );

		$url = function_exists( 'damavand_get_canonical_url' )
			? damavand_get_canonical_url( $post_id )
			: self::get_canonical( $post_id );
		if ( '' === $url ) {
			$url = (string) get_permalink( $post_id );
		}

		$type = 'product' === get_post_type( $post_id ) ? 'product' : 'article';

		$image = '';
		if ( 'product' === get_post_type( $post_id ) && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $post_id );
			if ( $product ) {
				$img_id = (int) $product->get_image_id();
				if ( $img_id > 0 ) {
					$image = (string) wp_get_attachment_image_url( $img_id, 'full' );
				}
			}
		}
		if ( '' === $image && has_post_thumbnail( $post_id ) ) {
			$image = (string) get_the_post_thumbnail_url( $post_id, 'full' );
		}
		if ( '' === $image && class_exists( 'Shojaei_SEO_Helpers' ) ) {
			$fallback_id = absint( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_og_image_id', 0 ) );
			if ( $fallback_id > 0 ) {
				$image = (string) wp_get_attachment_image_url( $fallback_id, 'full' );
			}
		}

		echo '<meta property="og:locale" content="fa_IR" data-damavand-seo="1" />' . "\n";
		echo '<meta property="og:type" content="' . esc_attr( $type ) . '" data-damavand-seo="1" />' . "\n";
		echo '<meta property="og:site_name" content="' . esc_attr( wp_strip_all_tags( get_bloginfo( 'name' ) ) ) . '" data-damavand-seo="1" />' . "\n";
		if ( '' !== $url ) {
			echo '<meta property="og:url" content="' . esc_url( $url ) . '" data-damavand-seo="1" />' . "\n";
		}
		if ( '' !== $title ) {
			echo '<meta property="og:title" content="' . esc_attr( $title ) . '" data-damavand-seo="1" />' . "\n";
		}
		if ( '' !== $desc ) {
			echo '<meta property="og:description" content="' . esc_attr( $desc ) . '" data-damavand-seo="1" />' . "\n";
		}
		if ( '' !== $image ) {
			echo '<meta property="og:image" content="' . esc_url( $image ) . '" data-damavand-seo="1" />' . "\n";
		}

		echo '<meta name="twitter:card" content="summary_large_image" data-damavand-seo="1" />' . "\n";
		if ( '' !== $title ) {
			echo '<meta name="twitter:title" content="' . esc_attr( $title ) . '" data-damavand-seo="1" />' . "\n";
		}
		if ( '' !== $desc ) {
			echo '<meta name="twitter:description" content="' . esc_attr( $desc ) . '" data-damavand-seo="1" />' . "\n";
		}
		if ( '' !== $image ) {
			echo '<meta name="twitter:image" content="' . esc_url( $image ) . '" data-damavand-seo="1" />' . "\n";
		}
	}
}
