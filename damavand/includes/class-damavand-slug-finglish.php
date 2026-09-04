<?php
/**
 * Finglish slug maps, dictionary, transliteration, scoring, uniquify.
 *
 * Extracted from Shojaei_SEO_Slug (Task 5). Facade wrappers remain on Shojaei_SEO_Slug.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Damavand_Slug_Finglish
 */
class Damavand_Slug_Finglish {
	/**
	 * Post types that get Finglish slug tools + 301 on rename.
	 *
	 * @return string[]
	 */
	public static function supported_post_types(): array {
		$types = array( 'product', 'post' );
		/**
		 * Filter post types for Damavand slug tools.
		 *
		 * @param string[] $types Post types.
		 */
		return array_values( array_unique( apply_filters( 'shojaei_seo_slug_post_types', $types ) ) );
	}

	/**
	 * Whether a post type is supported.
	 *
	 * @param string $post_type Type.
	 */
	public static function is_supported_type( string $post_type ): bool {
		return in_array( $post_type, self::supported_post_types(), true );
	}

	/**
	 * Persian/Arabic → Finglish map (stable, offline).
	 *
	 * @return array<string,string>
	 */
	public static function char_map(): array {
		return array(
			'ا' => 'a', 'آ' => 'a', 'أ' => 'a', 'إ' => 'e', 'ب' => 'b', 'پ' => 'p', 'ت' => 't', 'ث' => 's',
			'ج' => 'j', 'چ' => 'ch', 'ح' => 'h', 'خ' => 'kh', 'د' => 'd', 'ذ' => 'z', 'ر' => 'r', 'ز' => 'z',
			'ژ' => 'zh', 'س' => 's', 'ش' => 'sh', 'ص' => 's', 'ض' => 'z', 'ط' => 't', 'ظ' => 'z', 'ع' => 'a',
			'غ' => 'gh', 'ف' => 'f', 'ق' => 'gh', 'ک' => 'k', 'ك' => 'k', 'گ' => 'g', 'ل' => 'l', 'م' => 'm',
			'ن' => 'n', 'و' => 'v', 'ه' => 'h', 'ی' => 'y', 'ي' => 'y', 'ئ' => 'y', 'ء' => '', 'ة' => 'h',
			'ۀ' => 'h', 'ؤ' => 'o', '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5',
			'٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9', '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3',
			'۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
		);
	}

	/**
	 * Built-in Persian → Latin word dictionary (fashion / shop).
	 *
	 * @return array<string,string>
	 */
	public static function builtin_word_map(): array {
		static $map = null;
		if ( is_array( $map ) ) {
			return $map;
		}
		$file = DAMAVAND_SEO_DIR . 'includes/data/finglish-builtin-words.php';
		$map  = is_readable( $file ) ? include $file : array();
		if ( ! is_array( $map ) ) {
			$map = array();
		}
		return $map;
	}

	/**
	 * Option key for custom Finglish dictionary.
	 */
	public static function dictionary_option_key(): string {
		return 'shojaei_seo_finglish_dictionary';
	}

	/**
	 * Custom dictionary from settings (overrides built-in on same key).
	 *
	 * @return array<string,string>
	 */
	public static function custom_word_map(): array {
		$raw = get_option( self::dictionary_option_key(), array() );
		if ( is_string( $raw ) ) {
			return self::parse_dictionary_text( $raw );
		}
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $fa => $en ) {
			$fa = self::normalize_dict_key( (string) $fa );
			$en = self::normalize_dict_value( (string) $en );
			if ( '' !== $fa && '' !== $en ) {
				$out[ $fa ] = $en;
			}
		}
		return $out;
	}

	/**
	 * Temporary overlay for live preview (not persisted).
	 *
	 * @var array<string,string>
	 */
	private static $preview_overlay = array();

	/**
	 * Merged map: built-in + custom (custom wins).
	 *
	 * @return array<string,string>
	 */
	public static function word_map(): array {
		return array_merge( self::builtin_word_map(), self::custom_word_map(), self::$preview_overlay );
	}

	/**
	 * Overlay pairs for a single preview request.
	 *
	 * @param array<string,string> $map Overlay.
	 */
	public static function set_preview_overlay( array $map ): void {
		self::$preview_overlay = $map;
		self::clear_word_map_cache();
	}

	/**
	 * @var array<string,string>|null Longest-first normalized dictionary cache.
	 */
	private static $sorted_word_map_cache = null;

	/**
	 * Drop dictionary cache (after custom dict save).
	 */
	public static function clear_word_map_cache(): void {
		self::$sorted_word_map_cache = null;
	}

	/**
	 * Normalize Persian text for matching (ی/ک، حذف نیم‌فاصله).
	 *
	 * @param string $text Raw.
	 */
	public static function normalize_fa( string $text ): string {
		$text = wp_strip_all_tags( $text );
		$text = str_replace(
			array( 'ي', 'ك', 'ة', 'ؤ', 'أ', 'إ', 'ۀ', 'ـ' ),
			array( 'ی', 'ک', 'ه', 'و', 'ا', 'ا', 'ه', '' ),
			$text
		);
		// ZWNJ / zero-width.
		$text = preg_replace( '/[\x{200B}-\x{200D}\x{FEFF}]/u', '', $text );
		$text = preg_replace( '/\s+/u', ' ', (string) $text );
		return trim( (string) $text );
	}

	/**
	 * Dictionary keys normalized, longest-match-first (O(n·k) with k small).
	 *
	 * @return array<string,string>
	 */
	public static function sorted_word_map(): array {
		if ( null !== self::$sorted_word_map_cache ) {
			return self::$sorted_word_map_cache;
		}
		$norm = array();
		foreach ( self::word_map() as $fa => $en ) {
			$k = self::normalize_fa( (string) $fa );
			$v = self::normalize_dict_value( (string) $en );
			if ( '' === $k || '' === $v ) {
				continue;
			}
			// Later keys win (custom dict merged last in word_map()).
			$norm[ $k ] = $v;
		}
		$keys = array_keys( $norm );
		usort(
			$keys,
			static function ( $a, $b ) {
				return mb_strlen( (string) $b, 'UTF-8' ) <=> mb_strlen( (string) $a, 'UTF-8' );
			}
		);
		$sorted = array();
		foreach ( $keys as $k ) {
			$sorted[ $k ] = $norm[ $k ];
		}
		self::$sorted_word_map_cache = $sorted;
		return $sorted;
	}

	/**
	 * Persian + Latin stopwords removed from final slug tokens.
	 *
	 * @return array<string,true>
	 */
	public static function slug_stopwords(): array {
		$words = array(
			// FA after Finglish: از با در برای و یا که این آن را یک یه تا روی …
			'az', 'be', 'ba', 'dar', 'baraye', 'bara', 'va', 'ya', 'ke', 'in', 'an', 'ra', 'yek', 'ye', 'ta', 'roye', 'roy',
			'bar', 'ham', 'niz', 'shode', 'shavad', 'ast', 'bod', 'o', 'hamrah', 'bedoon', 'bedun', 'bedone',
			'of', 'the', 'a', 'and', 'or', 'for', 'with', 'from', 'to', 'on', 'in', 'at',
		);
		/**
		 * Filter Latin stopwords stripped from Finglish slugs.
		 *
		 * @param string[] $words Stopwords.
		 */
		$words = apply_filters( 'shojaei_seo_slug_stopwords', $words );
		$out   = array();
		foreach ( (array) $words as $w ) {
			$w = strtolower( trim( (string) $w ) );
			if ( '' !== $w ) {
				$out[ $w ] = true;
			}
		}
		return $out;
	}

	/**
	 * Remove stopword tokens; never empty the slug completely.
	 *
	 * @param string $slug Latin slug.
	 */
	public static function strip_slug_stopwords( string $slug ): string {
		$slug = trim( $slug, '-' );
		if ( '' === $slug ) {
			return '';
		}
		$stop  = self::slug_stopwords();
		$parts = array_filter( explode( '-', $slug ) );
		$kept  = array();
		foreach ( $parts as $p ) {
			$p = strtolower( (string) $p );
			if ( '' === $p || isset( $stop[ $p ] ) ) {
				continue;
			}
			$kept[] = $p;
		}
		if ( empty( $kept ) ) {
			return $slug;
		}
		return implode( '-', $kept );
	}

	/**
	 * Ensure slug is unique. Prefer distinctive product tokens (color/brand/sku/model)
	 * before falling back to WordPress numeric suffix (-2, -3…).
	 *
	 * @param string $slug      Desired slug.
	 * @param int    $post_id   Current post.
	 * @param string $post_type Type.
	 * @param string $status    Status.
	 * @param int    $parent    Parent.
	 */
	public static function uniquify_slug( string $slug, int $post_id = 0, string $post_type = 'product', string $status = 'publish', int $parent = 0 ): string {
		$slug = sanitize_title( $slug );
		if ( '' === $slug ) {
			return '';
		}

		$unique = wp_unique_post_slug( $slug, $post_id, $status, $post_type, $parent );
		if ( $unique === $slug ) {
			return $unique;
		}

		// Collision: try model/color/brand/sku before plain -2.
		if ( 'product' === $post_type && $post_id > 0 && function_exists( 'wc_get_product' ) ) {
			$enriched = self::enrich_slug_with_discriminators( $slug, $post_id );
			if ( '' !== $enriched && $enriched !== $slug ) {
				$try = wp_unique_post_slug( $enriched, $post_id, $status, $post_type, $parent );
				if ( '' !== $try ) {
					return $try;
				}
			}
		}

		return $unique;
	}

	/**
	 * Append short distinctive tokens from product data when base slug already exists.
	 *
	 * @param string $base      Base Latin slug.
	 * @param int    $product_id Product ID.
	 */
	public static function enrich_slug_with_discriminators( string $base, int $product_id ): string {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return $base;
		}

		$candidates = array();

		$sku = self::normalize_dict_value( (string) $product->get_sku() );
		if ( '' !== $sku && strlen( $sku ) <= 24 ) {
			$candidates[] = $sku;
		}

		$prefer_keys = array(
			'pa_color',
			'pa_colour',
			'pa_rang',
			'color',
			'colour',
			'رنگ',
			'pa_brand',
			'brand',
			'برند',
			'pa_model',
			'model',
			'مدل',
			'pa_size',
			'size',
			'سایز',
		);

		$attrs = $product->get_attributes();
		if ( is_array( $attrs ) ) {
			foreach ( $prefer_keys as $key ) {
				if ( empty( $attrs[ $key ] ) ) {
					continue;
				}
				$attr = $attrs[ $key ];
				$raw  = '';
				if ( is_object( $attr ) && method_exists( $attr, 'is_taxonomy' ) && $attr->is_taxonomy() ) {
					$terms = wc_get_product_terms( $product_id, $key, array( 'fields' => 'names' ) );
					if ( ! is_wp_error( $terms ) && ! empty( $terms[0] ) ) {
						$raw = (string) $terms[0];
					}
				} elseif ( is_object( $attr ) && method_exists( $attr, 'get_options' ) ) {
					$opts = $attr->get_options();
					if ( ! empty( $opts[0] ) ) {
						$raw = (string) $opts[0];
					}
				}
				$token = self::normalize_dict_value( self::has_persian( $raw ) ? self::transliterate( $raw ) : $raw );
				if ( '' !== $token && strlen( $token ) <= 20 ) {
					$candidates[] = $token;
				}
			}
		}

		$candidates = array_values( array_unique( array_filter( $candidates ) ) );
		if ( empty( $candidates ) ) {
			return $base;
		}

		$out = $base;
		$added = 0;
		foreach ( $candidates as $token ) {
			if ( $added >= 2 ) {
				break;
			}
			if ( false !== strpos( $out, $token ) ) {
				continue;
			}
			$next = sanitize_title( $out . '-' . $token );
			if ( strlen( $next ) > 90 ) {
				continue;
			}
			$out = $next;
			++$added;
		}

		return $out !== $base ? $out : $base;
	}

	/**
	 * Normalize Persian/key side of a dictionary entry.
	 *
	 * @param string $key Raw key.
	 */
	public static function normalize_dict_key( string $key ): string {
		$key = self::normalize_fa( $key );
		$key = preg_replace( '/\s+/u', ' ', $key );
		return is_string( $key ) ? trim( $key ) : '';
	}

	/**
	 * Normalize Latin/value side → slug-safe token.
	 *
	 * @param string $value Raw value.
	 */
	public static function normalize_dict_value( string $value ): string {
		$value = trim( wp_strip_all_tags( $value ) );
		$value = remove_accents( $value );
		$value = strtolower( $value );
		$value = preg_replace( '/[^a-z0-9]+/', '-', $value );
		$value = trim( (string) $value, '-' );
		return $value;
	}

	/**
	 * Parse textarea lines: «فارسی = latin» or «فارسی => latin» or «فارسی: latin».
	 * Lines starting with # are comments. Max 500 entries.
	 *
	 * @param string $text Raw textarea.
	 * @return array<string,string>
	 */
	public static function parse_dictionary_text( string $text ): array {
		$map  = array();
		$lines = preg_split( '/\r\n|\r|\n/', $text );
		if ( ! is_array( $lines ) ) {
			return array();
		}
		foreach ( $lines as $line ) {
			$line = trim( (string) $line );
			if ( '' === $line || 0 === strpos( $line, '#' ) ) {
				continue;
			}
			if ( ! preg_match( '/^(.+?)\s*(?:=>|=|:)\s*(.+)$/u', $line, $m ) ) {
				continue;
			}
			$fa = self::normalize_dict_key( $m[1] );
			$en = self::normalize_dict_value( $m[2] );
			if ( '' === $fa || '' === $en ) {
				continue;
			}
			$map[ $fa ] = $en;
			if ( count( $map ) >= 500 ) {
				break;
			}
		}
		return $map;
	}

	/**
	 * Format stored map for textarea editor.
	 *
	 * @param array<string,string>|null $map Map or null to load custom.
	 */
	public static function format_dictionary_text( ?array $map = null ): string {
		if ( null === $map ) {
			$map = self::custom_word_map();
		}
		if ( empty( $map ) ) {
			return '';
		}
		$lines = array();
		foreach ( $map as $fa => $en ) {
			$lines[] = $fa . ' = ' . $en;
		}
		return implode( "\n", $lines );
	}

	/**
	 * Sanitize and persist custom dictionary from textarea POST.
	 *
	 * @param string $text Raw textarea.
	 * @return array<string,string> Saved map.
	 */
	public static function save_custom_dictionary_from_text( string $text ): array {
		$map = self::parse_dictionary_text( $text );
		update_option( self::dictionary_option_key(), $map, false );
		self::clear_word_map_cache();
		return $map;
	}

	/**
	 * Add / update one custom dictionary pair.
	 *
	 * @param string $fa Persian word/phrase.
	 * @param string $en Latin slug token.
	 * @return array{ok:bool,message:string,map?:array<string,string>,preview?:string}
	 */
	public static function upsert_dictionary_entry( string $fa, string $en ): array {
		$fa = self::normalize_dict_key( $fa );
		$en = self::normalize_dict_value( $en );
		if ( '' === $fa || '' === $en ) {
			return array( 'ok' => false, 'message' => __( 'هر دو طرف واژه لازم است (مثلاً نیوبالانس = new-balance).', 'shojaei-seo-for-woo' ) );
		}
		$map      = self::custom_word_map();
		$map[ $fa ] = $en;
		if ( count( $map ) > 500 ) {
			return array( 'ok' => false, 'message' => __( 'سقف ۵۰۰ واژهٔ سفارشی پر است.', 'shojaei-seo-for-woo' ) );
		}
		update_option( self::dictionary_option_key(), $map, false );
		self::clear_word_map_cache();
		return array(
			'ok'      => true,
			'message' => __( 'واژه ذخیره شد؛ از این به بعد در پیشنهاد نامک استفاده می‌شود.', 'shojaei-seo-for-woo' ),
			'map'     => $map,
			'preview' => self::transliterate( $fa ),
		);
	}

	/**
	 * Remove one custom dictionary key.
	 *
	 * @param string $fa Key.
	 */
	public static function delete_dictionary_entry( string $fa ): bool {
		$fa  = self::normalize_dict_key( $fa );
		$map = self::custom_word_map();
		if ( ! isset( $map[ $fa ] ) ) {
			return false;
		}
		unset( $map[ $fa ] );
		update_option( self::dictionary_option_key(), $map, false );
		self::clear_word_map_cache();
		return true;
	}

	/**
	 * Transliterate title/text to Latin slug (Finglish) — offline, longest-match-first.
	 *
	 * @param string $text Raw title (FA/AR/Latin/digits mix OK).
	 */
	public static function transliterate( string $text ): string {
		$text = self::normalize_fa( $text );
		if ( '' === $text ) {
			return '';
		}

		$dict = self::sorted_word_map();
		$map  = self::char_map();
		$out  = '';
		$len  = mb_strlen( $text, 'UTF-8' );
		$i    = 0;

		while ( $i < $len ) {
			$matched = false;
			foreach ( $dict as $fa => $en ) {
				$flen = mb_strlen( $fa, 'UTF-8' );
				if ( $flen < 1 || ( $i + $flen ) > $len ) {
					continue;
				}
				if ( mb_substr( $text, $i, $flen, 'UTF-8' ) !== $fa ) {
					continue;
				}
				// Short keys (≤2): only on token boundaries so «کت» نمی‌شکند «کتان».
				if ( $flen <= 2 ) {
					$before = $i > 0 ? mb_substr( $text, $i - 1, 1, 'UTF-8' ) : '';
					$after  = ( $i + $flen ) < $len ? mb_substr( $text, $i + $flen, 1, 'UTF-8' ) : '';
					$bound  = static function ( string $c ): bool {
						return '' !== $c && (bool) preg_match( '/[\p{L}\p{N}]/u', $c );
					};
					if ( $bound( $before ) || $bound( $after ) ) {
						continue;
					}
				}
				$out    .= ' ' . $en . ' ';
				$i      += $flen;
				$matched = true;
				break;
			}
			if ( $matched ) {
				continue;
			}

			$ch = mb_substr( $text, $i, 1, 'UTF-8' );
			if ( isset( $map[ $ch ] ) ) {
				$out .= $map[ $ch ];
			} elseif ( preg_match( '/[a-zA-Z0-9]/', $ch ) ) {
				$out .= $ch;
			} else {
				$out .= ' ';
			}
			++$i;
		}

		$out = remove_accents( $out );
		$out = strtolower( $out );
		$out = preg_replace( '/[^a-z0-9]+/', '-', $out );
		$out = trim( (string) $out, '-' );
		$out = preg_replace( '/-{2,}/', '-', (string) $out );
		$out = self::strip_slug_stopwords( (string) $out );

		// Respect WP sanitize_title filters while staying Latin-only.
		$filtered = sanitize_title( $out );
		if ( is_string( $filtered ) && '' !== $filtered && ! self::has_persian( $filtered ) ) {
			$out = $filtered;
		}

		return (string) $out;
	}

	/**
	 * Whether string contains Persian/Arabic letters (raw or percent-encoded).
	 *
	 * @param string $text Text.
	 */
	public static function has_persian( string $text ): bool {
		if ( '' === $text ) {
			return false;
		}
		if ( preg_match( '/[\x{0600}-\x{06FF}\x{0750}-\x{077F}]/u', $text ) ) {
			return true;
		}
		// WordPress often stores Persian slugs percent-encoded (%D8%…).
		if ( false !== strpos( $text, '%' ) ) {
			$decoded = rawurldecode( $text );
			if ( $decoded !== $text && preg_match( '/[\x{0600}-\x{06FF}\x{0750}-\x{077F}]/u', $decoded ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * True when slug is already clean Latin (a-z0-9- only, no Persian).
	 *
	 * @param string $slug Slug.
	 */
	public static function is_clean_latin_slug( string $slug ): bool {
		$slug = trim( rawurldecode( $slug ), '/' );
		return '' !== $slug
			&& ! self::has_persian( $slug )
			&& (bool) preg_match( '/^[a-z0-9\-]+$/', $slug );
	}

	/**
	 * Readability score 0–100 for a slug.
	 *
	 * @param string $slug Slug.
	 * @return array{score:int,tips:string[]}
	 */
	public static function score_slug( string $slug ): array {
		$slug = trim( rawurldecode( $slug ), '/' );
		$tips = array();
		$score = 100;

		if ( '' === $slug ) {
			return array(
				'score' => 0,
				'tips'  => array( __( 'نامک خالی است.', 'shojaei-seo-for-woo' ) ),
			);
		}

		if ( self::has_persian( $slug ) ) {
			$score -= 40;
			$tips[] = __( 'نامک فارسی است؛ بهتر است فینگلیش لاتین باشد.', 'shojaei-seo-for-woo' );
		}

		$len = strlen( $slug );
		if ( $len > 60 ) {
			$score -= 25;
			$tips[] = __( 'نامک خیلی طولانی است (ترجیحاً زیر ۶۰ کاراکتر).', 'shojaei-seo-for-woo' );
		} elseif ( $len > 45 ) {
			$score -= 10;
			$tips[] = __( 'نامک کمی طولانی است.', 'shojaei-seo-for-woo' );
		} elseif ( $len < 3 ) {
			$score -= 20;
			$tips[] = __( 'نامک خیلی کوتاه است.', 'shojaei-seo-for-woo' );
		}

		if ( preg_match( '/[^a-z0-9\-]/', $slug ) ) {
			$score -= 20;
			$tips[] = __( 'فقط حروف لاتین کوچک، عدد و خط تیره مجاز است.', 'shojaei-seo-for-woo' );
		}

		if ( preg_match( '/--/', $slug ) ) {
			$score -= 10;
			$tips[] = __( 'خط تیره‌های پشت‌سرهم را کم کنید.', 'shojaei-seo-for-woo' );
		}

		$words = array_filter( explode( '-', $slug ) );
		if ( count( $words ) > 8 ) {
			$score -= 10;
			$tips[] = __( 'تعداد بخش‌های نامک زیاد است.', 'shojaei-seo-for-woo' );
		}

		if ( empty( $tips ) ) {
			$tips[] = __( 'نامک خوانا و مناسب به‌نظر می‌رسد.', 'shojaei-seo-for-woo' );
		}

		return array(
			'score' => max( 0, min( 100, $score ) ),
			'tips'  => $tips,
		);
	}

}
