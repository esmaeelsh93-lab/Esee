<?php
/**
 * Product slug automation: Finglish, readability, 301 on rename.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_Slug
 */
class Shojaei_SEO_Slug {
	/** @var bool|null */
	private static $slug_table_exists = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Always serve stored 301s — must not depend on the metabox/UI toggle.
		add_action( 'template_redirect', array( $this, 'maybe_redirect_old_slug' ), 0 );
		// These handlers no-op via their own options when disabled.
		add_filter( 'wp_insert_post_data', array( $this, 'maybe_transliterate_new_slug' ), 20, 2 );
		add_action( 'post_updated', array( $this, 'on_post_updated' ), 20, 3 );

		if ( 'yes' !== Shojaei_SEO_Helpers::get_option( 'shojaei_seo_slug_tools_enabled', 'yes' ) ) {
			return;
		}

		add_action( 'add_meta_boxes', array( $this, 'register_metabox' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'wp_ajax_shojaei_seo_slug_live', array( $this, 'ajax_live_preview' ) );
	}

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
	 * Slug redirects table.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'shojaei_seo_slug_redirects';
	}

	/**
	 * Slug redirects table availability (cached per-request).
	 */
	private static function has_slug_table(): bool {
		if ( null !== self::$slug_table_exists ) {
			return self::$slug_table_exists;
		}
		global $wpdb;
		$table = self::table();
		$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) );
		self::$slug_table_exists = ( $found === $table );
		return self::$slug_table_exists;
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

	/**
	 * Auto-Finglish for new products (or Persian slug).
	 *
	 * @param array $data    Post data.
	 * @param array $postarr Raw.
	 * @return array
	 */
	public function maybe_transliterate_new_slug( array $data, array $postarr ): array {
		if ( 'yes' !== Shojaei_SEO_Helpers::get_option( 'shojaei_seo_slug_auto_finglish', 'yes' ) ) {
			return $data;
		}
		$post_type = (string) ( $data['post_type'] ?? '' );
		if ( ! self::is_supported_type( $post_type ) ) {
			return $data;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return $data;
		}

		// Merchant opted out: keep Persian/current slug intentionally.
		if ( ! empty( $_POST['shojaei_seo_keep_slug'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return $data;
		}

		$status = $data['post_status'] ?? '';
		if ( in_array( $status, array( 'trash', 'auto-draft' ), true ) ) {
			return $data;
		}

		$post_id = (int) ( $postarr['ID'] ?? 0 );
		$slug    = (string) ( $data['post_name'] ?? '' );
		$title   = (string) ( $data['post_title'] ?? '' );

		if ( '' === $title ) {
			return $data;
		}

		// Respect a clean Latin/Finglish slug the merchant already chose.
		if ( self::is_clean_latin_slug( $slug ) ) {
			return $data;
		}

		// Published: only auto-fix when current slug is still Persian/invalid.
		if ( $post_id > 0 ) {
			$existing = get_post( $post_id );
			if ( $existing && 'publish' === $existing->post_status ) {
				$current = $slug !== '' ? $slug : (string) $existing->post_name;
				if ( self::is_clean_latin_slug( $current ) ) {
					return $data;
				}
			}
		}

		$latin = self::transliterate( $title );
		if ( '' === $latin || self::has_persian( $latin ) ) {
			return $data;
		}

		$data['post_name'] = self::uniquify_slug(
			$latin,
			$post_id,
			$post_type,
			(string) $status,
			(int) ( $data['post_parent'] ?? 0 )
		);

		return $data;
	}

	/**
	 * When published post/product slug changes → store 301 + activity log.
	 *
	 * @param int     $post_id     ID.
	 * @param WP_Post $post_after  After.
	 * @param WP_Post $post_before Before.
	 */
	public function on_post_updated( int $post_id, $post_after, $post_before ): void {
		if ( 'yes' !== Shojaei_SEO_Helpers::get_option( 'shojaei_seo_slug_auto_301', 'yes' ) ) {
			return;
		}
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}
		if ( ! $post_after || ! $post_before || ! self::is_supported_type( (string) $post_after->post_type ) ) {
			return;
		}
		// Only create 301 when the content was already published (real URL change).
		if ( 'publish' !== $post_before->post_status ) {
			return;
		}

		$old_slug = (string) $post_before->post_name;
		$new_slug = (string) $post_after->post_name;
		if ( '' === $old_slug || '' === $new_slug || $old_slug === $new_slug ) {
			return;
		}

		$new_url = get_permalink( $post_id );
		if ( ! $new_url ) {
			return;
		}

		$old_url = self::swap_slug_in_url( $new_url, $new_slug, $old_slug );
		if ( ! $old_url || $old_url === $new_url ) {
			return;
		}

		self::save_redirect( $post_id, $old_slug, $old_url, $new_url, '301' );

		if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
			Shojaei_SEO_Activity_Log::add(
				'slug_redirect',
				sprintf(
					/* translators: 1: old slug, 2: new slug */
					__( 'نامک عوض شد؛ ریدایرکت ۳۰۱ از «%1$s» به «%2$s»', 'shojaei-seo-for-woo' ),
					$old_slug,
					$new_slug
				),
				$post_id
			);
		}

		if ( class_exists( 'Shojaei_SEO_Redirect_Engine' ) ) {
			Shojaei_SEO_Redirect_Engine::clear_redirect_map_cache();
		}
	}

	/**
	 * Backward-compatible alias.
	 *
	 * @param int     $post_id     ID.
	 * @param WP_Post $post_after  After.
	 * @param WP_Post $post_before Before.
	 */
	public function on_product_updated( int $post_id, $post_after, $post_before ): void {
		$this->on_post_updated( $post_id, $post_after, $post_before );
	}

	/**
	 * Replace trailing slug in product URL.
	 *
	 * @param string $url      New URL.
	 * @param string $new_slug New slug.
	 * @param string $old_slug Old slug.
	 */
	public static function swap_slug_in_url( string $url, string $new_slug, string $old_slug ): string {
		$to = rawurldecode( trim( $old_slug, '/' ) );
		if ( '' === $to ) {
			$to = trim( $old_slug, '/' );
		}
		foreach ( self::slug_variants( $new_slug ) as $from ) {
			$pattern = '#/' . preg_quote( $from, '#' ) . '(/)?$#u';
			$out     = preg_replace( $pattern, '/' . $to . '$1', $url, 1 );
			if ( is_string( $out ) && $out !== $url ) {
				return $out;
			}
		}
		return '';
	}

	/**
	 * Encoded + decoded forms of a post_name (WP stores Persian as %d8%aa…).
	 *
	 * @param string $slug Slug.
	 * @return string[]
	 */
	public static function slug_variants( string $slug ): array {
		$slug = trim( str_replace( '\\', '', $slug ), '/' );
		if ( '' === $slug ) {
			return array();
		}
		$decoded = rawurldecode( $slug );
		$pieces  = preg_split( '/(-)/', $decoded, -1, PREG_SPLIT_DELIM_CAPTURE );
		$encoded = '';
		if ( is_array( $pieces ) ) {
			foreach ( $pieces as $piece ) {
				$encoded .= ( '-' === $piece ) ? '-' : rawurlencode( $piece );
			}
		}
		$out = array();
		foreach ( array( $slug, $decoded, $encoded, strtolower( $encoded ), strtolower( $slug ) ) as $v ) {
			$v = trim( (string) $v, '/' );
			if ( '' !== $v ) {
				$out[ $v ] = true;
			}
		}
		return array_keys( $out );
	}

	/**
	 * Persist slug redirect row.
	 *
	 * @param int    $product_id Product.
	 * @param string $old_slug   Old slug.
	 * @param string $old_url    Old URL.
	 * @param string $new_url    New URL.
	 * @param string $type       301/302.
	 */
	public static function save_redirect( int $product_id, string $old_slug, string $old_url, string $new_url, string $type = '301' ): int {
		global $wpdb;
		$table = self::table();

		$old_path = self::path_key( $old_url );
		if ( '' === $old_path ) {
			return 0;
		}

		$store_slug = rawurldecode( trim( $old_slug, '/' ) );
		if ( '' === $store_slug ) {
			$store_slug = trim( $old_slug, '/' );
		}
		$store_slug = substr( $store_slug, 0, 500 );

		// Deactivate duplicate active rows for same path (decoded + encoded keys).
		foreach ( array_unique( array( $old_path, self::path_key( rawurldecode( $old_url ) ) ) ) as $key ) {
			if ( '' === $key ) {
				continue;
			}
			$wpdb->update(
				$table,
				array( 'is_active' => 0 ),
				array(
					'old_path'  => $key,
					'is_active' => 1,
				),
				array( '%d' ),
				array( '%s', '%d' )
			);
		}

		$ok = $wpdb->insert(
			$table,
			array(
				'product_id'    => $product_id,
				'old_slug'      => $store_slug,
				'old_path'      => $old_path,
				'old_url'       => esc_url_raw( $old_url ),
				'new_url'       => esc_url_raw( $new_url ),
				'redirect_type' => in_array( $type, array( '301', '302' ), true ) ? $type : '301',
				'is_active'     => 1,
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s' )
		);

		return $ok ? (int) $wpdb->insert_id : 0;
	}

	/**
	 * Normalize path key for lookup (lowercase, no trailing slash).
	 *
	 * @param string $url URL.
	 */
	public static function path_key( string $url ): string {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		// Prefer decoded path so 404 lookup matches $wp->request for Persian slugs.
		$path = rawurldecode( $path );
		$path = untrailingslashit( strtolower( $path ) );
		return $path ? $path : '';
	}

	/**
	 * Build path keys to match stored redirects (encoded + decoded Persian).
	 *
	 * @param string $req_path Request path.
	 * @return string[]
	 */
	public static function path_lookup_candidates( string $req_path ): array {
		$req_path = trim( $req_path );
		if ( '' === $req_path ) {
			return array();
		}
		if ( '/' !== substr( $req_path, 0, 1 ) ) {
			$req_path = '/' . $req_path;
		}

		$decoded = rawurldecode( $req_path );
		$parts   = explode( '/', trim( $decoded, '/' ) );
		$encoded = '/' . implode(
			'/',
			array_map(
				static function ( $part ) {
					return rawurlencode( (string) $part );
				},
				$parts
			)
		);

		$home_path = (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH );
		$home_path = untrailingslashit( $home_path );

		$raw = array( $req_path, $decoded, $encoded );
		if ( $home_path && '/' !== $home_path ) {
			foreach ( array( $req_path, $decoded, $encoded ) as $p ) {
				if ( 0 === strpos( $p, $home_path . '/' ) || $p === $home_path ) {
					continue;
				}
				$raw[] = untrailingslashit( $home_path ) . $p;
			}
		}

		$out = array();
		foreach ( $raw as $p ) {
			$key = untrailingslashit( strtolower( (string) $p ) );
			if ( '' !== $key && '/' !== $key ) {
				$out[ $key ] = true;
			}
		}

		return array_keys( $out );
	}

	/**
	 * On 404, apply stored slug redirect.
	 */
	public function maybe_redirect_old_slug(): void {
		if ( ! is_404() ) {
			return;
		}
		if ( ! self::has_slug_table() ) {
			return;
		}

		global $wpdb, $wp;
		$candidates = array();

		if ( isset( $wp->request ) && is_string( $wp->request ) && '' !== $wp->request ) {
			$candidates = array_merge( $candidates, self::path_lookup_candidates( '/' . trim( $wp->request, '/' ) ) );
		}

		$uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '';
		$uri_path = (string) wp_parse_url( $uri, PHP_URL_PATH );
		if ( $uri_path ) {
			$candidates = array_merge( $candidates, self::path_lookup_candidates( $uri_path ) );
		}

		$candidates = array_values( array_unique( $candidates ) );
		if ( empty( $candidates ) ) {
			return;
		}

		$table = self::table();
		$row   = null;
		foreach ( $candidates as $key ) {
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT new_url, redirect_type FROM {$table} WHERE old_path = %s AND is_active = 1 ORDER BY id DESC LIMIT 1",
					$key
				)
			);
			if ( $row && ! empty( $row->new_url ) ) {
				break;
			}
			$row = null;
		}

		if ( ! $row || empty( $row->new_url ) ) {
			return;
		}

		if ( class_exists( 'Shojaei_SEO_Cache' ) ) {
			Shojaei_SEO_Cache::do_not_cache();
		}

		$code = ( '302' === $row->redirect_type ) ? 302 : 301;
		wp_safe_redirect( esc_url_raw( $row->new_url ), $code );
		exit;
	}

	/**
	 * Product editor metabox.
	 */
	public function register_metabox(): void {
		foreach ( self::supported_post_types() as $post_type ) {
			add_meta_box(
				'shojaei_seo_slug_box',
				__( 'نامک سئو (دماوند)', 'shojaei-seo-for-woo' ),
				array( $this, 'render_metabox' ),
				$post_type,
				'side',
				'high'
			);
		}
	}

	/**
	 * Live preview payload for product editor (title + current slug).
	 *
	 * @param string $title Title.
	 * @param string $slug  Current slug (may be empty).
	 * @return array{score:int,tone:string,tips:string[],suggest:string,slug:string,based_on:string}
	 */
	public static function live_preview( string $title, string $slug = '', int $post_id = 0 ): array {
		$title   = self::normalize_fa( $title );
		$slug    = trim( rawurldecode( $slug ), '/' );
		$base    = $title ? self::transliterate( $title ) : '';
		$suggest = $base;

		// Never surface a Persian "suggestion" — only Latin Finglish.
		if ( $suggest && self::has_persian( $suggest ) ) {
			$suggest = self::transliterate( $suggest );
		}
		if ( $suggest && ! preg_match( '/^[a-z0-9\-]+$/', $suggest ) ) {
			$suggest = preg_replace( '/[^a-z0-9]+/', '-', strtolower( remove_accents( $suggest ) ) );
			$suggest = trim( (string) $suggest, '-' );
			$suggest = self::strip_slug_stopwords( (string) $suggest );
		}

		$unique_note = false;
		if ( $suggest && $post_id > 0 ) {
			$post = get_post( $post_id );
			if ( $post && self::is_supported_type( (string) $post->post_type ) ) {
				$unique = self::uniquify_slug(
					$suggest,
					$post_id,
					(string) $post->post_type,
					(string) $post->post_status,
					(int) $post->post_parent
				);
				if ( $unique && $unique !== $suggest ) {
					$unique_note = true;
					$suggest     = $unique;
				}
			}
		}

		// Prefer real slug when set; otherwise score the Finglish suggestion so new products aren't stuck at 0.
		$based_on = '' !== $slug ? $slug : $suggest;
		$score    = self::score_slug( $based_on );
		$tone     = $score['score'] >= 75 ? 'safe' : ( $score['score'] >= 45 ? 'warning' : 'error' );

		$needs_fix = ( '' === $slug ) || self::has_persian( $slug ) || ! self::is_clean_latin_slug( $slug );

		if ( '' === $slug && $suggest ) {
			array_unshift(
				$score['tips'],
				__( 'هنوز نامک ذخیره نشده — امتیاز بر اساس پیشنهاد فینگلیش از عنوان است.', 'shojaei-seo-for-woo' )
			);
		} elseif ( $needs_fix && $suggest ) {
			array_unshift(
				$score['tips'],
				__( 'نامک فعلی فارسی/نامعتبر است؛ می‌توانید فینگلیش را اعمال کنید یا «بدون تغییر نامک» را بزنید.', 'shojaei-seo-for-woo' )
			);
		}
		if ( $unique_note ) {
			$score['tips'][] = __( 'نامک پایه تکراری بود؛ مدل/رنگ/برند/SKU به نامک اضافه شد (و در صورت نیاز پسوند عددی) تا محصول دیگری بازنویسی نشود.', 'shojaei-seo-for-woo' );
		}
		if ( $title && preg_match( '/(?:^|[\s\x{200C}])(از|به|با|در|برای|و|یا|که|این|را)(?:$|[\s\x{200C}])/u', $title ) ) {
			$score['tips'][] = __( 'کلمات ربط رایج فارسی (از، با، در، برای، …) از نامک فینگلیش حذف می‌شوند.', 'shojaei-seo-for-woo' );
		}

		return array(
			'score'      => (int) $score['score'],
			'tone'       => $tone,
			'tips'       => $score['tips'],
			'suggest'    => $suggest,
			'slug'       => $slug,
			'based_on'   => $based_on,
			'needs_fix' => $needs_fix && $suggest && $suggest !== $slug,
		);
	}

	/**
	 * AJAX: live slug score + Finglish suggestion while editing product.
	 */
	public function ajax_live_preview(): void {
		check_ajax_referer( 'shojaei_seo_slug_live', 'nonce' );

		if ( ! current_user_can( 'edit_products' ) && ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) ), 403 );
		}

		$title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$slug    = '';
		if ( isset( $_POST['slug'] ) ) {
			$slug = sanitize_text_field( rawurldecode( (string) wp_unslash( $_POST['slug'] ) ) );
			$slug = trim( $slug, '/' );
		}

		wp_send_json_success( self::live_preview( $title, $slug, $post_id ) );
	}

	/**
	 * Metabox UI.
	 *
	 * @param WP_Post $post Post.
	 */
	public function render_metabox( $post ): void {
		$slug      = (string) $post->post_name;
		$title     = (string) $post->post_title;
		$preview   = self::live_preview( $title, $slug, (int) $post->ID );
		$published = 'publish' === $post->post_status;
		$keep_label = $published
			? __( 'بروزرسانی بدون تغییر نامک', 'shojaei-seo-for-woo' )
			: __( 'انتشار بدون تغییر نامک', 'shojaei-seo-for-woo' );
		?>
		<div
			class="shojaei-slug-box"
			dir="rtl"
			data-post-id="<?php echo esc_attr( (string) (int) $post->ID ); ?>"
			data-original-slug="<?php echo esc_attr( $slug ); ?>"
			data-was-published="<?php echo $published ? '1' : '0'; ?>"
			id="shojaei-slug-box"
		>
			<p>
				<strong><?php esc_html_e( 'امتیاز خوانایی نامک:', 'shojaei-seo-for-woo' ); ?></strong>
				<span class="shojaei-slug-score shojaei-tone-<?php echo esc_attr( $preview['tone'] ); ?>" id="shojaei-slug-score">
					<?php echo esc_html( (string) $preview['score'] ); ?>/100
				</span>
			</p>
			<ul class="shojaei-slug-tips" id="shojaei-slug-tips">
				<?php foreach ( $preview['tips'] as $tip ) : ?>
					<li><?php echo esc_html( $tip ); ?></li>
				<?php endforeach; ?>
			</ul>
			<div id="shojaei-slug-suggest-wrap" class="shojaei-slug-suggest-wrap" <?php echo $preview['suggest'] ? '' : 'hidden'; ?>>
				<p class="description" style="margin-bottom:6px;">
					<?php esc_html_e( 'پیشنهاد فینگلیش (لاتین) از عنوان:', 'shojaei-seo-for-woo' ); ?>
					<code dir="ltr" id="shojaei-slug-suggest"><?php echo esc_html( $preview['suggest'] ); ?></code>
				</p>
				<button type="button" class="button button-small button-primary" id="shojaei-slug-apply-suggest">
					<?php esc_html_e( 'اعمال فینگلیش روی نامک', 'shojaei-seo-for-woo' ); ?>
				</button>
			</div>
			<p class="shojaei-slug-keep-wrap" style="margin-top:12px;padding-top:10px;border-top:1px solid #dcdcde;">
				<label for="shojaei-seo-keep-slug" style="display:flex;gap:8px;align-items:flex-start;cursor:pointer;">
					<input type="checkbox" name="shojaei_seo_keep_slug" id="shojaei-seo-keep-slug" value="1" style="margin-top:2px;" />
					<span>
						<strong><?php echo esc_html( $keep_label ); ?></strong><br />
						<span class="description"><?php esc_html_e( 'اگر عمداً می‌خواهید نامک فارسی/فعلی حفظ شود، این گزینه را بزنید.', 'shojaei-seo-for-woo' ); ?></span>
					</span>
				</label>
			</p>
			<p class="description" id="shojaei-slug-live-hint" style="margin-top:8px;">
				<?php esc_html_e( 'پیش‌فرض: پیشنهاد فینگلیش. اگر نامک لاتینِ تمیز باشد دست نمی‌زنیم. با تغییر واقعی نامکِ منتشرشده، ۳۰۱ ساخته می‌شود.', 'shojaei-seo-for-woo' ); ?>
			</p>
			<?php if ( $published ) : ?>
				<div class="notice notice-warning inline shojaei-slug-warn" style="margin:8px 0;padding:8px;">
					<p style="margin:0;">
						<?php esc_html_e( 'اگر نامک محتوای منتشرشده را عوض کنید، افزونه در صورت فعال بودن ریدایرکت ۳۰۱ خودکار می‌سازد.', 'shojaei-seo-for-woo' ); ?>
					</p>
				</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * List slug redirects for admin UI.
	 *
	 * @param int $limit Max rows.
	 * @return object[]
	 */
	public static function list_redirects( int $limit = 100 ): array {
		global $wpdb;
		$table = self::table();
		$limit = max( 1, min( 500, $limit ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY id DESC LIMIT %d",
				$limit
			)
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Count active slug redirects.
	 */
	public static function count_active_redirects(): int {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE is_active = 1" );
	}

	/**
	 * Toggle redirect active flag.
	 *
	 * @param int $id     Row ID.
	 * @param int $active 1|0.
	 */
	public static function set_redirect_active( int $id, int $active ): bool {
		global $wpdb;
		if ( $id < 1 ) {
			return false;
		}
		$ok = $wpdb->update(
			self::table(),
			array( 'is_active' => $active ? 1 : 0 ),
			array( 'id' => $id ),
			array( '%d' ),
			array( '%d' )
		);
		if ( false !== $ok && class_exists( 'Shojaei_SEO_Redirect_Engine' ) ) {
			Shojaei_SEO_Redirect_Engine::clear_redirect_map_cache();
		}
		return false !== $ok;
	}

	/**
	 * Update destination URL of a slug redirect (chain flatten).
	 *
	 * @param int    $id      Row ID.
	 * @param string $new_url Absolute target URL.
	 */
	public static function update_redirect_target( int $id, string $new_url ): bool {
		global $wpdb;
		if ( $id < 1 ) {
			return false;
		}
		$new_url = esc_url_raw( $new_url );
		if ( '' === $new_url ) {
			return false;
		}
		$ok = $wpdb->update(
			self::table(),
			array( 'new_url' => $new_url ),
			array( 'id' => $id ),
			array( '%s' ),
			array( '%d' )
		);
		if ( false !== $ok && class_exists( 'Shojaei_SEO_Redirect_Engine' ) ) {
			Shojaei_SEO_Redirect_Engine::clear_redirect_map_cache();
		}
		return false !== $ok;
	}

	/**
	 * Delete a slug redirect row.
	 *
	 * @param int $id Row ID.
	 */
	public static function delete_redirect( int $id ): bool {
		global $wpdb;
		if ( $id < 1 ) {
			return false;
		}
		$ok = $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
		if ( false !== $ok && class_exists( 'Shojaei_SEO_Redirect_Engine' ) ) {
			Shojaei_SEO_Redirect_Engine::clear_redirect_map_cache();
		}
		return false !== $ok;
	}

	/**
	 * Product IDs currently marked 410 Gone in OOS tracker.
	 *
	 * @return array<int,true> Map of product_id => true.
	 */
	public static function get_410_product_map(): array {
		if ( ! class_exists( 'Shojaei_SEO_Helpers' ) ) {
			return array();
		}
		return Shojaei_SEO_Helpers::get_410_excluded_map();
	}

	/**
	 * Whether product has an active 410 Gone decision.
	 *
	 * @param int $product_id Product ID.
	 */
	public static function is_410_product( int $product_id ): bool {
		if ( $product_id < 1 ) {
			return false;
		}
		$map = self::get_410_product_map();
		return isset( $map[ $product_id ] );
	}

	/**
	 * Option key for full-catalog slug health report.
	 */
	public static function full_report_option(): string {
		return 'shojaei_seo_slug_health_full';
	}

	/**
	 * Stored full health report (may be in-progress).
	 *
	 * @return array<string,mixed>
	 */
	public static function get_stored_full_report(): array {
		$raw = get_option( self::full_report_option(), array() );
		return is_array( $raw ) ? $raw : array();
	}

	/**
	 * Drop products from the cached health list (after apply / already finglish).
	 *
	 * @param int[] $product_ids IDs.
	 */
	public static function prune_health_report_ids( array $product_ids ): void {
		$want = array();
		foreach ( $product_ids as $id ) {
			$id = absint( $id );
			if ( $id > 0 ) {
				$want[ $id ] = true;
			}
		}
		if ( empty( $want ) ) {
			return;
		}
		$report = self::get_stored_full_report();
		$rows   = isset( $report['rows'] ) && is_array( $report['rows'] ) ? $report['rows'] : array();
		if ( empty( $rows ) ) {
			return;
		}
		$kept = array();
		foreach ( $rows as $row ) {
			$pid = (int) ( $row['product_id'] ?? 0 );
			if ( isset( $want[ $pid ] ) ) {
				continue;
			}
			$kept[] = $row;
		}
		if ( count( $kept ) === count( $rows ) ) {
			return;
		}
		$report['rows']        = $kept;
		$report['issues']      = count( $kept );
		$report['stored_rows'] = count( $kept );
		update_option( self::full_report_option(), $report, false );
	}

	/**
	 * Current slug already is the Finglish suggestion (or uniquified -2).
	 */
	public static function slug_is_applied_suggestion( string $slug, string $suggest ): bool {
		$slug    = trim( rawurldecode( $slug ), '/' );
		$suggest = trim( $suggest, '/' );
		if ( '' === $slug || '' === $suggest ) {
			return false;
		}
		if ( $slug === $suggest ) {
			return true;
		}
		return (bool) preg_match( '/^' . preg_quote( $suggest, '/' ) . '-[0-9]+$/', $slug );
	}

	/**
	 * Drop cached health rows whose live slug is already fixed.
	 *
	 * @param array<int,array<string,mixed>> $rows Stored rows.
	 * @return array<int,array<string,mixed>>
	 */
	private static function sync_stored_health_rows( array $rows ): array {
		if ( empty( $rows ) ) {
			return array();
		}
		global $wpdb;
		$ids = array();
		foreach ( $rows as $row ) {
			$pid = (int) ( $row['product_id'] ?? 0 );
			if ( $pid > 0 ) {
				$ids[] = $pid;
			}
		}
		$ids = array_values( array_unique( $ids ) );
		if ( empty( $ids ) ) {
			return array();
		}

		$live = array();
		foreach ( array_chunk( $ids, 200 ) as $chunk ) {
			$in     = implode( ',', array_map( 'absint', $chunk ) );
			$found  = $wpdb->get_results(
				"SELECT ID, post_name, post_status FROM {$wpdb->posts} WHERE ID IN ({$in})"
			);
			if ( ! is_array( $found ) ) {
				continue;
			}
			foreach ( $found as $p ) {
				$live[ (int) $p->ID ] = $p;
			}
		}

		$gone    = self::get_410_product_map();
		$out     = array();
		$changed = false;
		foreach ( $rows as $row ) {
			$pid = (int) ( $row['product_id'] ?? 0 );
			$p   = $live[ $pid ] ?? null;
			if ( ! $p || 'publish' !== (string) $p->post_status ) {
				$changed = true;
				continue;
			}
			if ( isset( $gone[ $pid ] ) ) {
				$changed = true;
				continue;
			}
			$current = (string) $p->post_name;
			if ( $current === (string) ( $row['slug'] ?? '' ) ) {
				$out[] = $row;
				continue;
			}
			$changed  = true;
			$analyzed = self::analyze_product_health( $pid, $gone );
			if ( ! empty( $analyzed['row'] ) ) {
				$fresh = $analyzed['row'];
				unset( $fresh['view_url'] );
				$out[] = $fresh;
			}
		}

		if ( $changed ) {
			$report = self::get_stored_full_report();
			$report['rows']        = $out;
			$report['issues']      = count( $out );
			$report['stored_rows'] = count( $out );
			update_option( self::full_report_option(), $report, false );
		}

		return $out;
	}

	/**
	 * All published product IDs (newest first).
	 *
	 * @return int[]
	 */
	public static function get_all_published_product_ids(): array {
		global $wpdb;
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col(
			"SELECT ID FROM {$wpdb->posts}
			WHERE post_type = 'product' AND post_status = 'publish'
			ORDER BY ID DESC"
		);
		if ( ! is_array( $ids ) ) {
			return array();
		}
		$ids  = array_values( array_filter( array_map( 'absint', $ids ) ) );
		$gone = self::get_410_product_map();
		if ( empty( $gone ) ) {
			return $ids;
		}
		return array_values(
			array_filter(
				$ids,
				static function ( $id ) use ( $gone ) {
					return ! isset( $gone[ $id ] );
				}
			)
		);
	}

	/**
	 * Analyze one product for health row (or null if OK / skipped).
	 *
	 * @param int             $product_id Product ID.
	 * @param array<int,true> $gone_410   410 map.
	 * @return array{row:?array,skipped_410:bool}
	 */
	public static function analyze_product_health( int $product_id, array $gone_410 = array() ): array {
		if ( isset( $gone_410[ $product_id ] ) ) {
			return array(
				'row'         => null,
				'skipped_410' => true,
			);
		}

		$post = get_post( $product_id );
		if ( ! $post || 'product' !== $post->post_type || 'publish' !== $post->post_status ) {
			return array(
				'row'         => null,
				'skipped_410' => false,
			);
		}

		$slug    = (string) $post->post_name;
		$slug_ui = rawurldecode( $slug );
		$title   = (string) $post->post_title;
		$suggest = self::transliterate( $title );
		if ( $suggest && self::slug_is_applied_suggestion( $slug, $suggest ) ) {
			return array(
				'row'         => null,
				'skipped_410' => false,
			);
		}
		$score   = self::score_slug( $slug );
		$reasons = array();

		if ( self::has_persian( $slug ) || self::has_persian( $slug_ui ) ) {
			$reasons[] = 'persian';
		}
		if ( strlen( $slug_ui ) > 60 || strlen( $slug ) > 80 ) {
			$reasons[] = 'long';
		}
		if ( $score['score'] < 70 ) {
			$reasons[] = 'low_score';
		}
		if ( $suggest && $suggest !== $slug && $suggest !== $slug_ui && ( self::has_persian( $slug_ui ) || $score['score'] < 75 ) ) {
			$reasons[] = 'finglish_better';
		}

		if ( empty( $reasons ) ) {
			return array(
				'row'         => null,
				'skipped_410' => false,
			);
		}

		return array(
			'row'         => array(
				'product_id'  => $product_id,
				'title'       => $title,
				'slug'        => $slug,
				'suggest'     => $suggest,
				'score'       => (int) $score['score'],
				'reasons'     => array_values( array_unique( $reasons ) ),
				'has_persian' => in_array( 'persian', $reasons, true ) ? 1 : 0,
				'has_long'    => in_array( 'long', $reasons, true ) ? 1 : 0,
				'edit_url'    => get_edit_post_link( $product_id, 'raw' ),
				'view_url'    => get_permalink( $product_id ),
			),
			'skipped_410' => false,
		);
	}

	/**
	 * Start background full-catalog slug health scan.
	 *
	 * @return array{ok:bool,message:string,job_id?:string,total?:int}
	 */
	public static function start_full_health_scan(): array {
		if ( ! class_exists( 'Shojaei_SEO_Jobs' ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'صف جاب در دسترس نیست.', 'shojaei-seo-for-woo' ),
			);
		}
		if ( Shojaei_SEO_Jobs::has_active( 'slug_health_scan' ) ) {
			return array(
				'ok'      => false,
				'message' => __( 'اسکن نامک قبلاً در حال اجراست.', 'shojaei-seo-for-woo' ),
			);
		}

		$ids = self::get_all_published_product_ids();
		update_option(
			self::full_report_option(),
			array(
				'rows'         => array(),
				'scanned'      => 0,
				'skipped_410'  => 0,
				'issues'       => 0,
				'complete'     => false,
				'total'        => count( $ids ),
				'started_at'   => current_time( 'mysql' ),
				'finished_at'  => '',
			),
			false
		);

		$job_id = Shojaei_SEO_Jobs::enqueue(
			'slug_health_scan',
			array( 'product_ids' => $ids ),
			array( 'total' => count( $ids ) )
		);

		return array(
			'ok'      => true,
			'message' => sprintf(
				/* translators: %d: product count */
				__( 'اسکن کامل نامک برای %d محصول در صف قرار گرفت.', 'shojaei-seo-for-woo' ),
				count( $ids )
			),
			'job_id'  => $job_id,
			'total'   => count( $ids ),
		);
	}

	/**
	 * Process a chunk of product IDs for full health scan.
	 *
	 * @param int[] $ids Product IDs.
	 * @return array{processed:int,issues_added:int}
	 */
	public static function process_health_scan_ids( array $ids ): array {
		$gone   = self::get_410_product_map();
		$report = self::get_stored_full_report();
		if ( empty( $report ) || ! isset( $report['rows'] ) ) {
			$report = array(
				'rows'        => array(),
				'scanned'     => 0,
				'skipped_410' => 0,
				'issues'      => 0,
				'complete'    => false,
			);
		}

		$added = 0;
		foreach ( $ids as $id ) {
			$id = absint( $id );
			if ( $id < 1 ) {
				continue;
			}
			$result = self::analyze_product_health( $id, $gone );
			++$report['scanned'];
			if ( ! empty( $result['skipped_410'] ) ) {
				++$report['skipped_410'];
			}
			if ( ! empty( $result['row'] ) ) {
				$row = $result['row'];
				unset( $row['view_url'] );
				$report['rows'][] = $row;
				++$added;
			}
		}

		$report['issues']   = count( $report['rows'] );
		$report['complete'] = false;
		update_option( self::full_report_option(), $report, false );

		return array(
			'processed'    => count( $ids ),
			'issues_added' => $added,
		);
	}

	/**
	 * Finalize full report: dup flags, sort, trim heavy fields, mark complete.
	 * Keeps at most 2000 worst-scoring issues to avoid bloating wp_options.
	 */
	public static function finalize_full_health_report(): void {
		$report = self::get_stored_full_report();
		$rows   = isset( $report['rows'] ) && is_array( $report['rows'] ) ? $report['rows'] : array();

		$by_suggest = array();
		foreach ( $rows as $row ) {
			$s = (string) ( $row['suggest'] ?? '' );
			if ( '' === $s ) {
				continue;
			}
			if ( ! isset( $by_suggest[ $s ] ) ) {
				$by_suggest[ $s ] = array();
			}
			$by_suggest[ $s ][] = (int) ( $row['product_id'] ?? 0 );
		}

		foreach ( $rows as &$row ) {
			$s = (string) ( $row['suggest'] ?? '' );
			if ( $s && isset( $by_suggest[ $s ] ) && count( $by_suggest[ $s ] ) > 1 ) {
				$row['reasons'][] = 'dup_suggest';
				$row['reasons']   = array_values( array_unique( $row['reasons'] ) );
			}
			// Drop regenerable heavy fields.
			unset( $row['view_url'] );
		}
		unset( $row );

		usort(
			$rows,
			static function ( $a, $b ) {
				return ( (int) ( $a['score'] ?? 0 ) ) <=> ( (int) ( $b['score'] ?? 0 ) );
			}
		);

		$issues_total = count( $rows );
		$max_store    = 2000;
		if ( $issues_total > $max_store ) {
			$rows = array_slice( $rows, 0, $max_store );
		}

		$report['rows']         = $rows;
		$report['issues']       = $issues_total;
		$report['stored_rows']  = count( $rows );
		$report['complete']     = true;
		$report['finished_at']  = current_time( 'mysql' );
		update_option( self::full_report_option(), $report, false );
	}

	/**
	 * Health scan for published product slugs.
	 * Prefers completed full-catalog report when available.
	 *
	 * @param int $scan_limit   How many recent products to inspect (quick mode).
	 * @param int $return_limit Per-page size.
	 * @param int $page         1-based page.
	 * @return array{rows:array<int,array>,scanned:int,issues:int,skipped_410:int,source:string,page:int,per_page:int,pages:int,finished_at?:string,total?:int,complete?:bool,stored_rows?:int}
	 */
	public static function get_health_report( int $scan_limit = 400, int $return_limit = 100, int $page = 1 ): array {
		$return_limit = max( 20, min( 200, $return_limit ) );
		$page         = max( 1, $page );
		$stored       = self::get_stored_full_report();

		if ( ! empty( $stored['complete'] ) && isset( $stored['rows'] ) && is_array( $stored['rows'] ) ) {
			$all     = self::sync_stored_health_rows( $stored['rows'] );
			$issues  = count( $all );
			$pages   = max( 1, (int) ceil( count( $all ) / $return_limit ) );
			$page    = min( $page, $pages );
			$offset  = ( $page - 1 ) * $return_limit;
			$slice   = array_slice( $all, $offset, $return_limit );

			// Refresh edit_url for current admin (stored may be stale).
			foreach ( $slice as &$row ) {
				$pid = (int) ( $row['product_id'] ?? 0 );
				if ( $pid > 0 ) {
					$row['edit_url'] = get_edit_post_link( $pid, 'raw' );
				}
			}
			unset( $row );

			return array(
				'rows'         => $slice,
				'scanned'      => (int) ( $stored['scanned'] ?? count( $all ) ),
				'issues'       => $issues,
				'skipped_410'  => (int) ( $stored['skipped_410'] ?? 0 ),
				'source'       => 'full',
				'finished_at'  => (string) ( $stored['finished_at'] ?? '' ),
				'total'        => (int) ( $stored['total'] ?? $stored['scanned'] ?? 0 ),
				'complete'     => true,
				'page'         => $page,
				'per_page'     => $return_limit,
				'pages'        => $pages,
				'stored_rows'  => count( $all ),
			);
		}

		global $wpdb;
		$scan_limit = max( 50, min( 1000, $scan_limit ) );

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_title, post_name FROM {$wpdb->posts}
				WHERE post_type = 'product' AND post_status = 'publish'
				ORDER BY ID DESC LIMIT %d",
				$scan_limit
			)
		);

		if ( ! is_array( $posts ) ) {
			$posts = array();
		}

		$gone_410    = self::get_410_product_map();
		$skipped_410 = 0;
		$by_suggest  = array();
		$rows        = array();

		foreach ( $posts as $p ) {
			$analyzed = self::analyze_product_health( (int) $p->ID, $gone_410 );
			if ( ! empty( $analyzed['skipped_410'] ) ) {
				++$skipped_410;
				continue;
			}
			if ( empty( $analyzed['row'] ) ) {
				continue;
			}
			$row = $analyzed['row'];
			unset( $row['view_url'] );
			$s = (string) $row['suggest'];
			if ( $s ) {
				if ( ! isset( $by_suggest[ $s ] ) ) {
					$by_suggest[ $s ] = array();
				}
				$by_suggest[ $s ][] = (int) $row['product_id'];
			}
			$rows[] = $row;
		}

		foreach ( $rows as &$row ) {
			$s = $row['suggest'];
			if ( $s && isset( $by_suggest[ $s ] ) && count( $by_suggest[ $s ] ) > 1 ) {
				$row['reasons'][] = 'dup_suggest';
				$row['reasons']   = array_values( array_unique( $row['reasons'] ) );
			}
		}
		unset( $row );

		usort(
			$rows,
			static function ( $a, $b ) {
				return $a['score'] <=> $b['score'];
			}
		);

		$issues = count( $rows );
		$pages  = max( 1, (int) ceil( $issues / $return_limit ) );
		$page   = min( $page, $pages );
		$offset = ( $page - 1 ) * $return_limit;

		return array(
			'rows'         => array_slice( $rows, $offset, $return_limit ),
			'scanned'      => count( $posts ),
			'issues'       => $issues,
			'skipped_410'  => $skipped_410,
			'source'       => 'quick',
			'complete'     => false,
			'total'        => count( $posts ),
			'page'         => $page,
			'per_page'     => $return_limit,
			'pages'        => $pages,
			'stored_rows'  => $issues,
		);
	}

	/**
	 * Reason labels for health UI.
	 *
	 * @param string $code Reason code.
	 */
	public static function reason_label( string $code ): string {
		$map = array(
			'persian'         => __( 'نامک فارسی', 'shojaei-seo-for-woo' ),
			'long'            => __( 'خیلی طولانی', 'shojaei-seo-for-woo' ),
			'low_score'       => __( 'امتیاز پایین', 'shojaei-seo-for-woo' ),
			'finglish_better' => __( 'پیشنهاد فینگلیش بهتر', 'shojaei-seo-for-woo' ),
			'dup_suggest'     => __( 'پیشنهاد تکراری با محصول دیگر', 'shojaei-seo-for-woo' ),
			'search'          => __( 'نتیجه جستجو', 'shojaei-seo-for-woo' ),
		);
		return $map[ $code ] ?? $code;
	}

	/**
	 * Preview or apply Finglish slug for one published product (creates 301 via post_updated).
	 *
	 * @param int  $product_id Product ID.
	 * @param bool $dry_run    If true, only preview.
	 * @return array{ok:bool,message:string,old_slug?:string,new_slug?:string,old_url?:string,new_url?:string,redirect_id?:int,indexnow?:bool,loop_blocked?:bool}
	 */
	public static function apply_suggested_slug( int $product_id, bool $dry_run = true ): array {
		$post = get_post( $product_id );
		if ( ! $post || 'product' !== $post->post_type || 'publish' !== $post->post_status ) {
			return array(
				'ok'      => false,
				'message' => __( 'محصول منتشرشده یافت نشد.', 'shojaei-seo-for-woo' ),
			);
		}

		if ( self::is_410_product( $product_id ) ) {
			self::prune_health_report_ids( array( $product_id ) );
			return array(
				'ok'          => false,
				'skipped_410' => true,
				'product_id'  => $product_id,
				'title'       => (string) $post->post_title,
				'message'     => __( 'این محصول وضعیت ۴۱۰ Gone دارد؛ از فهرست سلامت حذف شد.', 'shojaei-seo-for-woo' ),
			);
		}

		$old_slug = (string) $post->post_name;
		$latin    = self::transliterate( (string) $post->post_title );
		if ( '' === $latin ) {
			return array(
				'ok'      => false,
				'message' => __( 'از عنوان نمی‌توان نامک لاتین ساخت.', 'shojaei-seo-for-woo' ),
			);
		}

		$new_slug = self::uniquify_slug( $latin, $product_id, 'product', 'publish', (int) $post->post_parent );
		if ( $new_slug === $old_slug ) {
			self::prune_health_report_ids( array( $product_id ) );
			return array(
				'ok'           => true,
				'already_done' => true,
				'message'      => __( 'نامک از قبل فینگلیش است؛ از فهرست سلامت حذف شد.', 'shojaei-seo-for-woo' ),
				'old_slug'     => $old_slug,
				'new_slug'     => $new_slug,
				'product_id'   => $product_id,
				'title'        => (string) $post->post_title,
			);
		}

		$old_url = (string) get_permalink( $product_id );
		$new_url_preview = self::swap_slug_in_url( $old_url, $old_slug, $new_slug );

		// Loop / chain check against OOS + slug redirects.
		if ( class_exists( 'Shojaei_SEO_Redirect_Engine' ) && $old_url && $new_url_preview ) {
			$valid = Shojaei_SEO_Redirect_Engine::validate_redirect( $old_url, $new_url_preview, $product_id );
			if ( is_wp_error( $valid ) ) {
				return array(
					'ok'           => false,
					'message'      => $valid->get_error_message(),
					'old_slug'     => $old_slug,
					'new_slug'     => $new_slug,
					'old_url'      => $old_url,
					'new_url'      => $new_url_preview,
					'loop_blocked' => true,
				);
			}
		}

		if ( $dry_run ) {
			return array(
				'ok'       => true,
				'message'  => __( 'پیش‌نمایش Dry-Run: با اعمال واقعی، نامک عوض و ۳۰۱ ساخته می‌شود.', 'shojaei-seo-for-woo' ),
				'old_slug' => $old_slug,
				'new_slug' => $new_slug,
				'old_url'  => $old_url,
				'new_url'  => $new_url_preview ?: '',
				'dry_run'  => true,
				'product_id' => $product_id,
				'title'    => (string) $post->post_title,
			);
		}

		$result = wp_update_post(
			array(
				'ID'        => $product_id,
				'post_name' => $new_slug,
			),
			true
		);

		if ( is_wp_error( $result ) ) {
			return array(
				'ok'      => false,
				'message' => $result->get_error_message(),
			);
		}

		$new_url     = (string) get_permalink( $product_id );
		$redirect_id = self::latest_redirect_id_for_product( $product_id, $old_slug );

		// Health/admin apply promises a 301 even if auto-301 setting is off.
		if ( $redirect_id < 1 ) {
			$built_old = self::swap_slug_in_url( $new_url, $new_slug, $old_slug );
			if ( ! $built_old || $built_old === $new_url ) {
				$built_old = $old_url;
			}
			if ( $built_old && $new_url && $built_old !== $new_url ) {
				$redirect_id = self::save_redirect( $product_id, $old_slug, $built_old, $new_url, '301' );
				if ( class_exists( 'Shojaei_SEO_Redirect_Engine' ) ) {
					Shojaei_SEO_Redirect_Engine::clear_redirect_map_cache();
				}
			}
			if ( $redirect_id < 1 ) {
				$redirect_id = self::latest_redirect_id_for_product( $product_id, $old_slug );
			}
		}

		$indexnow_queued = false;
		if ( class_exists( 'Shojaei_SEO_IndexNow' ) && $old_url && $new_url && $old_url !== $new_url ) {
			$q = Shojaei_SEO_IndexNow::queue_suggestion(
				$old_url,
				$new_url,
				array(
					'post_id' => $product_id,
					'title'   => (string) $post->post_title,
					'reason'  => __( 'اعمال نامک فینگلیش', 'shojaei-seo-for-woo' ),
					'source'  => 'slug_apply',
				)
			);
			$indexnow_queued = ! empty( $q['ok'] );
		}

		self::prune_health_report_ids( array( $product_id ) );

		update_post_meta(
			$product_id,
			'_shojaei_seo_last_slug_change',
			array(
				'old_slug'    => $old_slug,
				'new_slug'    => $new_slug,
				'old_url'     => $old_url,
				'new_url'     => $new_url,
				'redirect_id' => $redirect_id,
				'at'          => current_time( 'mysql' ),
			)
		);

		if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
			Shojaei_SEO_Activity_Log::add(
				'slug_apply',
				sprintf(
					/* translators: 1: old slug, 2: new slug */
					__( 'اعمال فینگلیش: «%1$s» → «%2$s» — لینک قدیم ۳۰۱ شد', 'shojaei-seo-for-woo' ),
					$old_slug,
					$new_slug
				),
				$product_id,
				array(
					'redirect_id'      => $redirect_id,
					'indexnow_queued'  => $indexnow_queued,
				)
			);
		}

		$wp_covers = self::wp_old_slug_covers( $product_id, $old_slug );

		if ( $redirect_id > 0 ) {
			$msg = __( 'نامک اعمال شد. لینک قدیم با ریدایرکت ۳۰۱ به آدرس جدید می‌رود.', 'shojaei-seo-for-woo' );
			$redirect_notice = __( 'لینک قدیمی دیگر ۴۰۴ نمی‌شود — ۳۰۱ فعال است (تب ریدایرکت‌ها).', 'shojaei-seo-for-woo' );
		} elseif ( $wp_covers ) {
			$msg = __( 'نامک اعمال شد. ریدایرکت لینک قدیم توسط وردپرس (_wp_old_slug) پوشش داده می‌شود.', 'shojaei-seo-for-woo' );
			$redirect_notice = __( 'لینک قدیم ۴۰۴ نمی‌شود؛ هسته وردپرس ۳۰۱ می‌کند.', 'shojaei-seo-for-woo' );
		} else {
			$msg = __( 'نامک اعمال شد، اما ریدایرکت ۳۰۱ ثبت نشد. لینک قدیم ممکن است ۴۰۴ شود.', 'shojaei-seo-for-woo' );
			$redirect_notice = __( '۳۰۱ ساخته نشد — وضعیت را در تب ریدایرکت‌ها بررسی کنید.', 'shojaei-seo-for-woo' );
		}
		if ( $indexnow_queued ) {
			$msg .= ' ' . __( 'پیشنهاد IndexNow (آدرس قدیم/جدید) در صف تأیید قرار گرفت — از هسته سئو → نمایه‌سازی فوری تأیید کنید.', 'shojaei-seo-for-woo' );
		} else {
			$msg .= ' ' . __( 'IndexNow در صف نرفت؛ از هسته سئو → نمایه‌سازی فوری → «پیشنهاد از ریدایرکت‌های نامک» را بزنید.', 'shojaei-seo-for-woo' );
		}

		return array(
			'ok'              => true,
			'message'         => $msg,
			'old_slug'        => $old_slug,
			'new_slug'        => $new_slug,
			'old_url'         => $old_url,
			'new_url'         => $new_url,
			'redirect_id'     => $redirect_id,
			'indexnow'        => false,
			'indexnow_queued' => $indexnow_queued,
			'redirect_notice' => $redirect_notice,
			'dry_run'         => false,
			'product_id'      => $product_id,
			'title'           => (string) $post->post_title,
			'can_undo'        => $redirect_id > 0,
		);
	}

	/**
	 * Latest active slug redirect id for product + old slug.
	 */
	public static function latest_redirect_id_for_product( int $product_id, string $old_slug = '' ): int {
		global $wpdb;
		$table = self::table();
		if ( $old_slug ) {
			$variants = self::slug_variants( $old_slug );
			if ( empty( $variants ) ) {
				return 0;
			}
			$in  = implode( ',', array_fill( 0, count( $variants ), '%s' ) );
			$sql = $wpdb->prepare(
				"SELECT id FROM {$table} WHERE product_id = %d AND is_active = 1 AND old_slug IN ({$in}) ORDER BY id DESC LIMIT 1",
				array_merge( array( $product_id ), $variants )
			);
			return (int) $wpdb->get_var( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}
		$id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE product_id = %d AND is_active = 1 ORDER BY id DESC LIMIT 1",
				$product_id
			)
		);
		return (int) $id;
	}

	/**
	 * WordPress core redirect_canonical / wp_old_slug_redirect will 301 this slug.
	 */
	public static function wp_old_slug_covers( int $product_id, string $old_slug ): bool {
		$stored = get_post_meta( $product_id, '_wp_old_slug', false );
		if ( ! is_array( $stored ) || empty( $stored ) ) {
			return false;
		}
		$want = self::slug_variants( $old_slug );
		if ( empty( $want ) ) {
			return false;
		}
		$want_map = array_fill_keys( $want, true );
		foreach ( $stored as $s ) {
			foreach ( self::slug_variants( (string) $s ) as $v ) {
				if ( isset( $want_map[ $v ] ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Batch dry-run / apply (hard cap 20).
	 *
	 * @param int[] $product_ids IDs.
	 * @param bool  $dry_run     Dry-run.
	 * @return array{ok:bool,dry_run:bool,applied:int,failed:int,items:array}
	 */
	public static function batch_apply( array $product_ids, bool $dry_run = true ): array {
		$ids = array();
		foreach ( $product_ids as $id ) {
			$id = absint( $id );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		$ids = array_values( array_unique( $ids ) );
		$ids = array_slice( $ids, 0, 20 );

		$items        = array();
		$applied      = 0;
		$failed       = 0;
		$skipped_410  = 0;
		$gone_ids     = array();
		foreach ( $ids as $id ) {
			$r = self::apply_suggested_slug( $id, $dry_run );
			if ( empty( $r['product_id'] ) ) {
				$r['product_id'] = $id;
			}
			$items[] = $r;
			if ( ! empty( $r['skipped_410'] ) ) {
				++$skipped_410;
				$gone_ids[] = $id;
			} elseif ( ! empty( $r['ok'] ) ) {
				++$applied;
			} else {
				++$failed;
			}
		}
		if ( ! $dry_run && ! empty( $gone_ids ) ) {
			self::prune_health_report_ids( $gone_ids );
		}

		$msg_parts = array();
		if ( $dry_run ) {
			$msg_parts[] = sprintf(
				/* translators: 1: ready, 2: blocked */
				__( 'Dry-Run: %1$d آماده اعمال، %2$d ناموفق/مسدود.', 'shojaei-seo-for-woo' ),
				$applied,
				$failed + $skipped_410
			);
		} else {
			$msg_parts[] = sprintf(
				/* translators: %d: applied */
				__( '%d نامک اعمال شد.', 'shojaei-seo-for-woo' ),
				$applied
			);
			if ( $skipped_410 > 0 ) {
				$msg_parts[] = sprintf(
					/* translators: %d: 410 count */
					__( '%d محصول ۴۱۰ از فهرست حذف شد.', 'shojaei-seo-for-woo' ),
					$skipped_410
				);
			}
			if ( $failed > 0 ) {
				$msg_parts[] = sprintf(
					/* translators: %d: failed */
					__( '%d ناموفق.', 'shojaei-seo-for-woo' ),
					$failed
				);
			}
		}

		return array(
			'ok'          => true,
			'dry_run'     => $dry_run,
			'applied'     => $applied,
			'failed'      => $failed,
			'skipped_410' => $skipped_410,
			'total'       => count( $ids ),
			'items'       => $items,
			'message'     => implode( ' ', $msg_parts ),
		);
	}

	/**
	 * Undo a health/auto slug apply: restore old slug + deactivate 301.
	 *
	 * @param int $redirect_id Slug redirect row ID.
	 * @return array{ok:bool,message:string}
	 */
	public static function undo_slug_redirect( int $redirect_id ): array {
		global $wpdb;
		$table = self::table();
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $redirect_id ) );
		if ( ! $row ) {
			return array(
				'ok'      => false,
				'message' => __( 'ریدایرکت یافت نشد.', 'shojaei-seo-for-woo' ),
			);
		}

		$product_id = (int) $row->product_id;
		$old_slug   = (string) $row->old_slug;
		$post       = get_post( $product_id );
		if ( ! $post || 'product' !== $post->post_type ) {
			self::set_redirect_active( $redirect_id, 0 );
			return array(
				'ok'      => false,
				'message' => __( 'محصول موجود نیست؛ فقط ریدایرکت غیرفعال شد.', 'shojaei-seo-for-woo' ),
			);
		}

		if ( '' === $old_slug ) {
			return array(
				'ok'      => false,
				'message' => __( 'نامک قدیم در رکورد نیست؛ Undo ممکن نیست.', 'shojaei-seo-for-woo' ),
			);
		}

		$unique = wp_unique_post_slug( $old_slug, $product_id, $post->post_status, 'product', (int) $post->post_parent );
		// Temporarily disable auto-301 to avoid reverse redirect chain noise.
		$was_301 = Shojaei_SEO_Helpers::get_option( 'shojaei_seo_slug_auto_301', 'yes' );
		update_option( 'shojaei_seo_slug_auto_301', 'no' );

		$result = wp_update_post(
			array(
				'ID'        => $product_id,
				'post_name' => $unique,
			),
			true
		);

		update_option( 'shojaei_seo_slug_auto_301', $was_301 );
		self::set_redirect_active( $redirect_id, 0 );

		if ( is_wp_error( $result ) ) {
			return array(
				'ok'      => false,
				'message' => $result->get_error_message(),
			);
		}

		delete_post_meta( $product_id, '_shojaei_seo_last_slug_change' );

		if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
			Shojaei_SEO_Activity_Log::add(
				'slug_undo',
				sprintf(
					/* translators: %s: restored slug */
					__( 'Undo نامک — بازگشت به «%s» و غیرفعال‌سازی ۳۰۱', 'shojaei-seo-for-woo' ),
					$unique
				),
				$product_id,
				array( 'redirect_id' => $redirect_id )
			);
		}

		return array(
			'ok'       => true,
			'message'  => __( 'نامک برگردانده و ریدایرکت ۳۰۱ غیرفعال شد.', 'shojaei-seo-for-woo' ),
			'old_slug' => (string) $post->post_name,
			'new_slug' => $unique,
			'product_id' => $product_id,
		);
	}

	/**
	 * Search published products for slug tools UI.
	 *
	 * @param string $query Search term (title / ID / slug).
	 * @param int    $limit Max results.
	 * @return array<int,array<string,mixed>>
	 */
	public static function search_products_for_slug( string $query, int $limit = 20 ): array {
		$query = trim( wp_strip_all_tags( $query ) );
		$limit = max( 1, min( 50, $limit ) );
		if ( '' === $query ) {
			return array();
		}

		$ids = array();

		if ( ctype_digit( $query ) ) {
			$ids[] = absint( $query );
		} else {
			$q = new WP_Query(
				array(
					'post_type'              => 'product',
					'post_status'            => 'publish',
					'posts_per_page'         => $limit,
					's'                      => $query,
					'orderby'                => 'relevance',
					'order'                  => 'DESC',
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);
			$ids = array_map( 'absint', $q->posts );

			// Also match by Latin slug / partial post_name.
			global $wpdb;
			$like = '%' . $wpdb->esc_like( sanitize_title( $query ) ) . '%';
			if ( '%' !== $like ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$by_slug = $wpdb->get_col(
					$wpdb->prepare(
						"SELECT ID FROM {$wpdb->posts}
						WHERE post_type = 'product' AND post_status = 'publish'
						AND post_name LIKE %s
						ORDER BY ID DESC
						LIMIT %d",
						$like,
						$limit
					)
				);
				if ( is_array( $by_slug ) ) {
					$ids = array_merge( $ids, array_map( 'absint', $by_slug ) );
				}
			}

			// Raw title LIKE for Persian titles WP_Query may miss under some configs.
			$like_title = '%' . $wpdb->esc_like( $query ) . '%';
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$by_title = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT ID FROM {$wpdb->posts}
					WHERE post_type = 'product' AND post_status = 'publish'
					AND post_title LIKE %s
					ORDER BY ID DESC
					LIMIT %d",
					$like_title,
					$limit
				)
			);
			if ( is_array( $by_title ) ) {
				$ids = array_merge( $ids, array_map( 'absint', $by_title ) );
			}
		}

		$ids  = array_values( array_unique( array_filter( $ids ) ) );
		$ids  = array_slice( $ids, 0, $limit );
		$gone = self::get_410_product_map();
		$out  = array();

		foreach ( $ids as $product_id ) {
			$post = get_post( $product_id );
			if ( ! $post || 'product' !== $post->post_type || 'publish' !== $post->post_status ) {
				continue;
			}
			$analyzed = self::analyze_product_health( $product_id, $gone );
			if ( ! empty( $analyzed['skipped_410'] ) ) {
				continue;
			}
			$row      = $analyzed['row'];
			if ( ! $row ) {
				$slug    = (string) $post->post_name;
				$suggest = self::transliterate( (string) $post->post_title );
				$score   = self::score_slug( $slug );
				$row     = array(
					'product_id'  => $product_id,
					'title'       => (string) $post->post_title,
					'slug'        => $slug,
					'suggest'     => $suggest,
					'score'       => (int) $score['score'],
					'reasons'     => array( 'search' ),
					'has_persian' => self::has_persian( $slug ) || self::has_persian( rawurldecode( $slug ) ) ? 1 : 0,
					'has_long'    => strlen( rawurldecode( $slug ) ) > 60 ? 1 : 0,
					'edit_url'    => get_edit_post_link( $product_id, 'raw' ),
					'view_url'    => get_permalink( $product_id ),
					'healthy'     => empty( $analyzed['skipped_410'] ),
				);
			} else {
				$row['healthy'] = false;
			}
			$out[] = $row;
		}

		return $out;
	}

	/**
	 * Admin JS for live slug score/suggest + publish warning.
	 *
	 * @param string $hook Hook.
	 */
	public function enqueue_admin_assets( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! self::is_supported_type( (string) $screen->post_type ) ) {
			return;
		}

		wp_enqueue_script( 'jquery' );
		wp_register_script( 'shojaei-seo-slug-live', false, array( 'jquery' ), DAMAVAND_SEO_VERSION, true );
		wp_enqueue_script( 'shojaei-seo-slug-live' );
		wp_localize_script(
			'shojaei-seo-slug-live',
			'shojaeiSlugLive',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'shojaei_seo_slug_live' ),
				'i18n'    => array(
					'loading'       => __( 'در حال محاسبه…', 'shojaei-seo-for-woo' ),
					'submit_once'   => __( 'نامک به فینگلیش لاتین عوض می‌شود و برای محتوای منتشرشده ریدایرکت ۳۰۱ ساخته می‌شود. ادامه؟', 'shojaei-seo-for-woo' ),
					'submit_change' => __( 'نامک عوض می‌شود و لینک قدیم با ۳۰۱ حفظ می‌شود. ادامه؟', 'shojaei-seo-for-woo' ),
				),
			)
		);

		$slug_js = <<<'JS'
(function($){$(function(){
	var $box=$('#shojaei-slug-box');
	if(!$box.length){return;}
	var orig=String($box.data('original-slug')||'');
	var published=String($box.data('was-published'))==='1';
	var timer=null;
	var lastKey='';
	var applying=false;
	var confirmedOnce=false;

	function keepSlug(){ return $('#shojaei-seo-keep-slug').is(':checked'); }
	function isPersian(s){
		s=String(s||'');
		if(/[\u0600-\u06FF\u0750-\u077F]/.test(s)){return true;}
		if(s.indexOf('%')!==-1){
			try{
				var d=decodeURIComponent(s.replace(/\+/g,' '));
				if(d!==s && /[\u0600-\u06FF\u0750-\u077F]/.test(d)){return true;}
			}catch(e){}
		}
		return false;
	}
	function isCleanLatin(s){
		s=String(s||'').replace(/^\/+|\/+$/g,'');
		try{ s=decodeURIComponent(s); }catch(e){}
		return !!s && !isPersian(s) && /^[a-z0-9\-]+$/.test(s);
	}
	function readTitle(){
		var t=($('#title').val()||'').toString();
		if(!t && window.wp && wp.data && wp.data.select){
			try{
				var ed=wp.data.select('core/editor');
				if(ed && ed.getEditedPostAttribute){ t=ed.getEditedPostAttribute('title')||''; }
			}catch(e){}
		}
		return t;
	}
	function readSlug(){
		var s=($('#post_name').val()||'').toString();
		if(!s){ s=($('#editable-post-name-full').text()||$('#editable-post-name').text()||'').toString(); }
		if(!s && window.wp && wp.data && wp.data.select){
			try{
				var ed=wp.data.select('core/editor');
				if(ed && ed.getEditedPostAttribute){ s=ed.getEditedPostAttribute('slug')||''; }
			}catch(e){}
		}
		return String(s||'').replace(/^\/+|\/+$/g,'');
	}
	function setEditorSlug(slug){
		if(!slug || !/^[a-z0-9\-]+$/.test(slug)){return;}
		applying=true;
		var $pn=$('#post_name');
		if($pn.length){ $pn.val(slug); }
		if(window.wp && wp.data && wp.data.dispatch){
			try{ wp.data.dispatch('core/editor').editPost({slug:slug}); }catch(e){}
		}
		var $full=$('#editable-post-name-full');
		var $short=$('#editable-post-name');
		if($full.length){ $full.text(slug); }
		if($short.length){ $short.text(slug); }
		var $field=$('#new-post-slug');
		if($field.length){
			$field.val(slug);
		} else {
			var $edit=$('#edit-slug-buttons .edit-slug, #edit-slug-box .edit-slug').first();
			if($edit.length){
				$edit.trigger('click');
				window.setTimeout(function(){
					$('#new-post-slug').val(slug);
					var $ok=$('#edit-slug-buttons .save, #edit-slug-box .save').first();
					if($ok.length){ $ok.trigger('click'); }
					applying=false;
				}, 60);
				return;
			}
		}
		var $ok=$('#edit-slug-buttons .save, #edit-slug-box .save').first();
		if($ok.length && $field.length){ $ok.trigger('click'); }
		window.setTimeout(function(){ applying=false; }, 120);
	}
	function render(data){
		if(!data){return;}
		var $score=$('#shojaei-slug-score');
		$score.removeClass('shojaei-tone-safe shojaei-tone-warning shojaei-tone-error')
			.addClass('shojaei-tone-'+(data.tone||'error'))
			.text((data.score!=null?data.score:0)+'/100');
		var tips=data.tips||[];
		var $tips=$('#shojaei-slug-tips').empty();
		tips.forEach(function(t){ $tips.append($('<li/>').text(t)); });
		var suggest=String(data.suggest||'');
		if(suggest && !/^[a-z0-9\-]+$/.test(suggest)){ suggest=''; }
		var $wrap=$('#shojaei-slug-suggest-wrap');
		$('#shojaei-slug-suggest').text(suggest);
		if(suggest){ $wrap.prop('hidden',false); } else { $wrap.prop('hidden',true); }
	}
	function refresh(force){
		var title=readTitle();
		var slug=readSlug();
		var key=title+'|'+slug;
		if(!force && key===lastKey){return;}
		lastKey=key;
		$.post(shojaeiSlugLive.ajaxUrl,{
			action:'shojaei_seo_slug_live',
			nonce:shojaeiSlugLive.nonce,
			title:title,
			slug:slug,
			post_id: parseInt($box.data('post-id'),10)||0
		}).done(function(res){
			if(res && res.success && res.data){ render(res.data); }
		});
	}
	function schedule(){
		if(timer){ clearTimeout(timer); }
		timer=setTimeout(function(){ refresh(false); }, 350);
	}

	$(document).on('input keyup change', '#title, #post_name, #new-post-slug', schedule);
	$(document).on('change', '#shojaei-seo-keep-slug', function(){
		$('#shojaei-slug-apply-suggest').prop('disabled', keepSlug());
	});
	$(document).on('ajaxComplete', function(ev, xhr, settings){
		if(applying){return;}
		var u=(settings && settings.url)||'';
		var d=(settings && settings.data)||'';
		if((typeof d==='string' && d.indexOf('sample-permalink')!==-1) || (typeof u==='string' && u.indexOf('sample-permalink')!==-1)){
			schedule();
		}
	});
	var permalink=document.getElementById('edit-slug-box');
	if(permalink && window.MutationObserver){
		var mo=new MutationObserver(function(){ if(!applying){ schedule(); } });
		mo.observe(permalink,{childList:true,subtree:true,characterData:true});
	}

	$('#shojaei-slug-apply-suggest').on('click', function(e){
		e.preventDefault();
		if(keepSlug()){return;}
		var suggest=($('#shojaei-slug-suggest').text()||'').trim();
		if(!suggest || !/^[a-z0-9\-]+$/.test(suggest)){return;}
		setEditorSlug(suggest);
		refresh(true);
	});

	$('#post').on('submit', function(e){
		if(confirmedOnce || keepSlug() || !published){return;}
		var slug=readSlug();
		if(orig && slug && slug!==orig){
			var msg=isPersian(orig)
				? (shojaeiSlugLive.i18n.submit_once||'')
				: (shojaeiSlugLive.i18n.submit_change||'');
			if(msg && !window.confirm(msg)){
				e.preventDefault();
				return false;
			}
			confirmedOnce=true;
		}
	});

	refresh(true);
	setTimeout(function(){ refresh(true); }, 600);
});})(jQuery);
JS;
		wp_add_inline_script( 'shojaei-seo-slug-live', $slug_js );

		wp_register_style( 'shojaei-seo-slug-admin', false, array(), DAMAVAND_SEO_VERSION );
		wp_enqueue_style( 'shojaei-seo-slug-admin' );
		wp_add_inline_style(
			'shojaei-seo-slug-admin',
			'.shojaei-slug-score{font-weight:700;padding:2px 8px;border-radius:4px;display:inline-block}'
			. '.shojaei-slug-score.shojaei-tone-safe{background:#e8f5e9;color:#2e7d32}'
			. '.shojaei-slug-score.shojaei-tone-warning{background:#fff8e1;color:#ef6c00}'
			. '.shojaei-slug-score.shojaei-tone-error{background:#ffebee;color:#c62828}'
			. '.shojaei-slug-tips{margin:8px 0 0;padding-right:18px}'
			. '.shojaei-slug-tips li{margin:0 0 4px}'
			. '.shojaei-slug-suggest-wrap{margin-top:8px}'
			. '.shojaei-slug-suggest-wrap code{display:inline-block;margin-top:2px;word-break:break-all}'
		);
	}
}
