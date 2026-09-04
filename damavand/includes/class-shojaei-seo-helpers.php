<?php
/**
 * Helper functions.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_Helpers
 */
class Shojaei_SEO_Helpers {

	public const ADMIN_CAP = 'manage_shojaei_seo';

	/**
	 * Capability for admin menu and settings pages.
	 */
	public static function admin_cap(): string {
		return self::ADMIN_CAP;
	}

	/**
	 * Register capability mapping for custom / store roles.
	 */
	public static function register_hooks(): void {
		add_filter( 'user_has_cap', array( __CLASS__, 'map_admin_caps' ), 10, 4 );
	}

	/**
	 * Map store/admin caps to plugin admin cap (supports varied role setups).
	 *
	 * @param array<string,bool>    $allcaps All capabilities for the user.
	 * @param array<int,string>     $caps    Requested capabilities.
	 * @param array<int,mixed>      $args    Capability check args.
	 * @param WP_User               $user    User object.
	 * @return array<string,bool>
	 */
	public static function map_admin_caps( $allcaps, $caps, $args, $user ) {
		unset( $caps, $args, $user );
		if ( ! empty( $allcaps[ self::ADMIN_CAP ] ) ) {
			return $allcaps;
		}
		$grant = ! empty( $allcaps['manage_options'] )
			|| ! empty( $allcaps['manage_woocommerce'] )
			|| ! empty( $allcaps['edit_products'] )
			|| ! empty( $allcaps['publish_products'] );
		if ( $grant ) {
			$allcaps[ self::ADMIN_CAP ] = true;
		}
		return $allcaps;
	}

	/**
	 * Can the current user access Damavand SEO admin screens?
	 */
	public static function user_can_admin(): bool {
		return current_user_can( self::ADMIN_CAP );
	}

	/**
	 * Persist plugin admin cap on default store roles.
	 */
	public static function ensure_admin_capabilities(): void {
		if ( class_exists( 'SEO_Core_Installer' ) ) {
			SEO_Core_Installer::ensure_capabilities();
			return;
		}
		foreach ( array( 'administrator', 'shop_manager' ) as $role_slug ) {
			$role = get_role( $role_slug );
			if ( $role && ! $role->has_cap( self::ADMIN_CAP ) ) {
				$role->add_cap( self::ADMIN_CAP );
			}
		}
	}

	/**
	 * Get plugin option with default.
	 *
	 * @param string $key     Option key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public static function get_option( string $key, $default = '' ) {
		return get_option( $key, $default );
	}

	/**
	 * Check if a module is enabled.
	 *
	 * @param string $module Module key.
	 * @return bool
	 */
	public static function is_module_enabled( string $module ): bool {
		if ( 'yes' !== self::get_option( 'shojaei_seo_' . $module . '_enabled', 'yes' ) ) {
			return false;
		}
		// هم‌ترازی با رجیستری هسته سئو برای اسکیما.
		if ( 'schema' === $module && class_exists( 'SEO_Core_Installer' ) && ! SEO_Core_Installer::is_module_enabled( 'schema' ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Get OOS tracker table name.
	 */
	public static function oos_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'shojaei_seo_oos_tracker';
	}

	/** @var array<int,true>|null */
	private static $map_410 = null;

	/**
	 * Product IDs with active 410 Gone — exclude from catalog / Pulse / slug / links.
	 *
	 * @return array<int,true>
	 */
	public static function get_410_excluded_map(): array {
		if ( null !== self::$map_410 ) {
			return self::$map_410;
		}
		global $wpdb;
		$table = self::oos_table();
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$ids = $wpdb->get_col(
			"SELECT product_id FROM {$table}
			WHERE status = 'redirected' AND redirect_type = '410'"
		);
		$map = array();
		if ( is_array( $ids ) ) {
			foreach ( $ids as $id ) {
				$id = absint( $id );
				if ( $id > 0 ) {
					$map[ $id ] = true;
				}
			}
		}
		self::$map_410 = $map;
		return $map;
	}

	/**
	 * Drop in-request 410 map (after apply / undo).
	 */
	public static function flush_410_excluded_cache(): void {
		self::$map_410 = null;
	}

	/**
	 * Whether post/product should be hidden from operational SEO lists (410 Gone).
	 *
	 * @param int $post_id Post ID.
	 */
	public static function is_410_excluded( int $post_id ): bool {
		if ( $post_id < 1 ) {
			return false;
		}
		$map = self::get_410_excluded_map();
		return isset( $map[ $post_id ] );
	}

	/**
	 * IDs for WP_Query post__not_in.
	 *
	 * @return int[]
	 */
	public static function get_410_excluded_ids(): array {
		return array_map( 'absint', array_keys( self::get_410_excluded_map() ) );
	}

	/**
	 * Merge 410 IDs into a WP_Query post__not_in list.
	 *
	 * @param int[] $not_in Existing IDs.
	 * @return int[]
	 */
	public static function merge_410_not_in( array $not_in = array() ): array {
		$gone = self::get_410_excluded_ids();
		if ( empty( $gone ) ) {
			return array_values( array_unique( array_map( 'absint', $not_in ) ) );
		}
		return array_values( array_unique( array_merge( array_map( 'absint', $not_in ), $gone ) ) );
	}

	/**
	 * Drop 410 products from an ID list (related / upsells).
	 *
	 * @param int[] $ids Product IDs.
	 * @return int[]
	 */
	public static function strip_410_ids( array $ids ): array {
		$map = self::get_410_excluded_map();
		if ( empty( $map ) ) {
			return $ids;
		}
		$out = array();
		foreach ( $ids as $id ) {
			$id = absint( $id );
			if ( $id > 0 && ! isset( $map[ $id ] ) ) {
				$out[] = $id;
			}
		}
		return $out;
	}

	/**
	 * Get internal links table name.
	 */
	public static function links_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'shojaei_seo_internal_links';
	}

	/**
	 * Get redirect log table name.
	 */
	public static function redirect_log_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'shojaei_seo_redirect_log';
	}

	/**
	 * Calculate title similarity percentage between two strings.
	 *
	 * @param string $title1 First title.
	 * @param string $title2 Second title.
	 * @return int Percentage 0-100.
	 */
	public static function title_similarity( string $title1, string $title2 ): int {
		$words1 = self::extract_keywords( $title1 );
		$words2 = self::extract_keywords( $title2 );

		if ( empty( $words1 ) || empty( $words2 ) ) {
			return 0;
		}

		$common = array_intersect( $words1, $words2 );
		if ( empty( $common ) ) {
			return 0;
		}

		// Dice coefficient — fairer for long Persian fashion titles than max-length Jaccard.
		$dice = ( 2 * count( $common ) ) / ( count( $words1 ) + count( $words2 ) );
		return (int) round( min( 100, $dice * 100 ) );
	}

	/**
	 * Extract meaningful keywords from a title.
	 *
	 * @param string $title Product title.
	 * @return array
	 */
	public static function extract_keywords( string $title ): array {
		$stop_words = array(
			'و', 'در', 'به', 'از', 'با', 'که', 'این', 'آن', 'برای', 'تا', 'یا', 'هم', 'را',
			'طرح', 'مدل', 'جدید', 'اصل', 'اورجینال',
			'the', 'a', 'an', 'and', 'or', 'of', 'in', 'for', 'to', 'with',
		);

		if ( class_exists( 'Shojaei_SEO_Persian' ) ) {
			$title = Shojaei_SEO_Persian::normalize( $title );
		} else {
			$title = mb_strtolower( $title, 'UTF-8' );
		}

		$title = preg_replace( '/[^\p{L}\p{N}\s]/u', ' ', $title );
		$words = preg_split( '/\s+/u', (string) $title, -1, PREG_SPLIT_NO_EMPTY );
		$words = array_filter(
			(array) $words,
			static function ( $word ) use ( $stop_words ) {
				$word = (string) $word;
				return mb_strlen( $word, 'UTF-8' ) > 2 && ! in_array( $word, $stop_words, true );
			}
		);

		return array_values( array_unique( $words ) );
	}

	/**
	 * Count words in Persian/English text.
	 *
	 * @param string $text Content text.
	 * @return int
	 */
	public static function count_words( string $text ): int {
		$text = wp_strip_all_tags( $text );
		$text = preg_replace( '/\s+/', ' ', trim( $text ) );
		if ( empty( $text ) ) {
			return 0;
		}
		return count( preg_split( '/\s+/', $text, -1, PREG_SPLIT_NO_EMPTY ) );
	}

	/**
	 * Format price with configured currency label.
	 *
	 * @param float $price Price value.
	 * @return string
	 */
	public static function format_price( float $price ): string {
		return number_format( $price, 0, '.', ',' ) . ' ' . self::get_currency_label();
	}

	/**
	 * ISO-ish currency code for schema / exports (IRT|IRR|USD|EUR|AED).
	 */
	public static function get_currency_code(): string {
		$code = strtoupper( (string) self::get_option( 'shojaei_seo_currency', DAMAVAND_SEO_CURRENCY ) );
		$ok   = array( 'IRT', 'IRR', 'USD', 'EUR', 'AED' );
		return in_array( $code, $ok, true ) ? $code : 'IRT';
	}

	/**
	 * Human currency label (تومان / ریال / …).
	 */
	public static function get_currency_label(): string {
		$map = array(
			'IRT' => __( 'تومان', 'shojaei-seo-for-woo' ),
			'IRR' => __( 'ریال', 'shojaei-seo-for-woo' ),
			'USD' => 'USD',
			'EUR' => 'EUR',
			'AED' => __( 'درهم', 'shojaei-seo-for-woo' ),
		);
		$code  = self::get_currency_code();
		$custom = trim( (string) self::get_option( 'shojaei_seo_currency_label', '' ) );
		if ( '' !== $custom ) {
			return $custom;
		}
		return $map[ $code ] ?? $map['IRT'];
	}

	/**
	 * Neutralize CSV formula injection (= + - @) for Excel/Sheets.
	 *
	 * @param mixed $value Cell value.
	 * @return string|int|float
	 */
	public static function csv_safe_cell( $value ) {
		if ( is_int( $value ) || is_float( $value ) ) {
			return $value;
		}
		$s = (string) $value;
		if ( '' === $s ) {
			return $s;
		}
		$first = $s[0];
		if ( in_array( $first, array( '=', '+', '-', '@', "\t", "\r", "\n" ), true ) ) {
			return "'" . $s;
		}
		return $s;
	}

	/**
	 * @param array<int,mixed> $row CSV row.
	 * @return array<int,mixed>
	 */
	public static function csv_safe_row( array $row ): array {
		$out = array();
		foreach ( $row as $cell ) {
			$out[] = self::csv_safe_cell( $cell );
		}
		return $out;
	}

	/**
	 * Get product primary category URL.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public static function get_primary_category_url( int $product_id ): string {
		$terms = get_the_terms( $product_id, 'product_cat' );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return home_url( '/shop/' );
		}
		$term = $terms[0];
		return get_term_link( $term );
	}

	/**
	 * Get product primary category name.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public static function get_primary_category_name( int $product_id ): string {
		$terms = get_the_terms( $product_id, 'product_cat' );
		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return '';
		}
		return $terms[0]->name;
	}

	/**
	 * Increment a stat counter.
	 *
	 * @param string $stat Stat key suffix.
	 */
	public static function increment_stat( string $stat ): void {
		$key   = 'shojaei_seo_stats_' . $stat;
		$count = (int) self::get_option( $key, 0 );
		update_option( $key, $count + 1 );

		// Local daily analytics (no external APIs).
		if ( class_exists( 'Shojaei_SEO_Analytics' ) ) {
			if ( 'links_built' === $stat ) {
				Shojaei_SEO_Analytics::bump( 'links_built' );
			} elseif ( 'redirects' === $stat ) {
				Shojaei_SEO_Analytics::bump( 'redirects' );
			}
		}
	}

	/**
	 * Decrement a stat counter (minimum zero).
	 *
	 * @param string $stat Stat key suffix.
	 */
	public static function decrement_stat( string $stat ): void {
		$key   = 'shojaei_seo_stats_' . $stat;
		$count = max( 0, (int) self::get_option( $key, 0 ) - 1 );
		update_option( $key, $count );
	}

	/**
	 * Get minimum phase for applying noindex on OOS products.
	 */
	public static function get_noindex_from_phase(): int {
		return max( 2, (int) self::get_option( 'shojaei_seo_oos_noindex_from_phase', 2 ) );
	}

	/**
	 * OOS timeline thresholds (multi-level, not binary).
	 *
	 * @return array{message_day:int,temp_days:int,auto_day:int,auto_type:string}
	 */
	public static function get_oos_timeline(): array {
		$message = max( 1, (int) self::get_option( 'shojaei_seo_oos_message_day', 15 ) );
		$temp    = max( $message, (int) self::get_option( 'shojaei_seo_oos_temp_days', 30 ) );
		$auto    = max( $temp, (int) self::get_option( 'shojaei_seo_oos_auto_day', 45 ) );
		$type    = self::get_option( 'shojaei_seo_oos_auto_redirect_type', '302' );
		$type    = in_array( $type, array( '301', '302' ), true ) ? $type : '302';

		return array(
			'message_day' => $message,
			'temp_days'   => $temp,
			'auto_day'    => $auto,
			'auto_type'   => $type,
		);
	}

	/**
	 * Lifecycle classification for a day count.
	 *
	 * Front copy uses three keys only: temporary | unlikely | final
	 * (bound to message_day / temp_days / auto_day — not one string per day count).
	 *
	 * @param int $days Days OOS.
	 * @return array{type:string,stage:string,phase:int,status:string,message_key:string,label:string}
	 */
	public static function get_oos_state( int $days ): array {
		$t = self::get_oos_timeline();

		$type = $days < $t['temp_days'] ? 'temporary' : 'permanent';

		if ( $days < $t['message_day'] ) {
			$stage       = 'soft_message';
			$phase       = 1;
			$status      = 'temp_oos';
			$message_key = 'temporary';
		} elseif ( $days < $t['temp_days'] ) {
			$stage       = 'hard_message';
			$phase       = 2;
			$status      = 'temp_oos';
			$message_key = 'unlikely';
		} elseif ( $days < $t['auto_day'] ) {
			$stage       = 'permanent_watch';
			$phase       = 3;
			$status      = 'permanent_oos';
			$message_key = 'unlikely';
		} else {
			$stage       = 'auto_ready';
			$phase       = 4;
			$status      = 'candidate_redirect';
			$message_key = 'final';
		}

		$labels = array(
			'temporary' => __( 'ناموجود موقت', 'shojaei-seo-for-woo' ),
			'permanent' => __( 'ناموجود دائم', 'shojaei-seo-for-woo' ),
		);

		return array(
			'type'        => $type,
			'stage'       => $stage,
			'phase'       => $phase,
			'status'      => $status,
			'message_key' => $message_key,
			'label'       => $labels[ $type ],
		);
	}

	/**
	 * Front-facing OOS copy for the three cycle phases.
	 * Empty custom fields fall back to defaults.
	 *
	 * @param string $message_key temporary|unlikely|final (legacy: optimistic|long_term).
	 * @return array{title:string,body:string,cta:string,css:string}
	 */
	public static function get_oos_front_copy( string $message_key ): array {
		// Legacy keys from older installs / cached state.
		if ( 'optimistic' === $message_key ) {
			$message_key = 'temporary';
		}
		if ( 'long_term' === $message_key ) {
			$message_key = 'unlikely';
		}

		$defaults = array(
			'temporary' => array(
				'title' => __( 'فعلاً ناموجود — در حال تأمین مجدد', 'shojaei-seo-for-woo' ),
				'body'  => __( 'این کالا موقتاً موجود نیست. به‌زودی دوباره تأمین می‌شود؛ تا آن موقع می‌توانید گزینه‌های مشابه را ببینید.', 'shojaei-seo-for-woo' ),
				'cta'   => __( 'مشاهده محصولات مشابه', 'shojaei-seo-for-woo' ),
				'css'   => 'shojaei-oos-notice shojaei-oos-phase-temp',
			),
			'unlikely'  => array(
				'title' => __( 'احتمال موجود شدن کمتر است', 'shojaei-seo-for-woo' ),
				'body'  => __( 'مدت بیشتری از ناموجودی گذشته. پیشنهاد می‌کنیم همین حالا گزینه‌های نزدیک را بررسی کنید.', 'shojaei-seo-for-woo' ),
				'cta'   => __( 'پیشنهادهای جایگزین', 'shojaei-seo-for-woo' ),
				'css'   => 'shojaei-oos-notice shojaei-oos-phase-unlikely',
			),
			'final'     => array(
				'title' => __( 'این کالا فعلاً در دسترس نیست', 'shojaei-seo-for-woo' ),
				'body'  => __( 'انتظار موجود شدن دوباره نداریم. از پیشنهادهای زیر انتخاب کنید یا در صورت فعال بودن مسیر جایگزین هدایت می‌شوید.', 'shojaei-seo-for-woo' ),
				'cta'   => __( 'مشاهده جایگزین‌ها', 'shojaei-seo-for-woo' ),
				'css'   => 'shojaei-oos-notice shojaei-oos-phase-final',
			),
		);

		$base = $defaults[ $message_key ] ?? $defaults['temporary'];
		$map  = array(
			'temporary' => 'temp',
			'unlikely'  => 'unlikely',
			'final'     => 'final',
		);
		$suffix = $map[ $message_key ] ?? 'temp';

		$title = trim( (string) self::get_option( 'shojaei_seo_oos_msg_' . $suffix . '_title', '' ) );
		$body  = trim( (string) self::get_option( 'shojaei_seo_oos_msg_' . $suffix . '_body', '' ) );
		$cta   = trim( (string) self::get_option( 'shojaei_seo_oos_msg_' . $suffix . '_cta', '' ) );

		if ( '' !== $title ) {
			$base['title'] = $title;
		}
		if ( '' !== $body ) {
			$base['body'] = $body;
		}
		if ( '' !== $cta ) {
			$base['cta'] = $cta;
		}

		return $base;
	}

	/**
	 * Store-owner custom CSS for OOS notice (sanitized for style output).
	 */
	public static function get_oos_custom_css(): string {
		$raw = (string) self::get_option( 'shojaei_seo_oos_custom_css', '' );
		if ( '' === trim( $raw ) ) {
			return '';
		}
		// Strip tags / closing style to avoid breakout; keep CSS text.
		$css = wp_strip_all_tags( $raw );
		$css = str_ireplace( array( '</style', '<script', 'expression(' ), '', $css );
		/**
		 * Filter custom OOS CSS before print.
		 *
		 * @param string $css CSS.
		 */
		return (string) apply_filters( 'shojaei_seo_oos_custom_css', $css );
	}

	/**
	 * Human label for tracker status.
	 *
	 * @param string $status Status slug.
	 */
	public static function oos_status_label( string $status ): string {
		$map = array(
			'soft_oos'           => __( 'ناموجود موقت', 'shojaei-seo-for-woo' ),
			'temp_oos'           => __( 'ناموجود موقت', 'shojaei-seo-for-woo' ),
			'permanent_oos'      => __( 'ناموجود دائم', 'shojaei-seo-for-woo' ),
			'candidate_redirect' => __( 'کاندید ریدایرکت', 'shojaei-seo-for-woo' ),
			'needs_manual'       => __( 'نیاز به تایید دستی (ارزش بالا)', 'shojaei-seo-for-woo' ),
			'redirected'         => __( 'ریدایرکت شده', 'shojaei-seo-for-woo' ),
		);

		return $map[ $status ] ?? $status;
	}

	/**
	 * Active (non-redirected) OOS statuses for queries.
	 *
	 * @return string[]
	 */
	public static function active_oos_statuses(): array {
		return array( 'soft_oos', 'temp_oos', 'permanent_oos', 'candidate_redirect', 'needs_manual' );
	}

	/**
	 * Sync OOS date + lifecycle into postmeta (state machine mirror).
	 *
	 * @param int    $product_id Product ID.
	 * @param string $oos_date   MySQL datetime.
	 * @param int    $days       Days OOS.
	 */
	public static function sync_oos_postmeta( int $product_id, string $oos_date, int $days ): void {
		$state = self::get_oos_state( $days );
		update_post_meta( $product_id, '_shojaei_seo_oos_date', $oos_date );
		update_post_meta( $product_id, '_shojaei_seo_oos_lifecycle', $state['type'] );
		update_post_meta( $product_id, '_shojaei_seo_oos_days', $days );
	}

	/**
	 * Clear OOS postmeta when product is back in stock.
	 *
	 * @param int $product_id Product ID.
	 */
	public static function clear_oos_postmeta( int $product_id ): void {
		delete_post_meta( $product_id, '_shojaei_seo_oos_date' );
		delete_post_meta( $product_id, '_shojaei_seo_oos_lifecycle' );
		delete_post_meta( $product_id, '_shojaei_seo_oos_days' );
		delete_post_meta( $product_id, '_shojaei_seo_oos_observed' );
		delete_post_meta( $product_id, '_shojaei_seo_oos_probed' );
	}

	/**
	 * Check if Rank Math is active.
	 */
	public static function is_rank_math_active(): bool {
		if ( class_exists( 'Shojaei_SEO_Integration' ) ) {
			return Shojaei_SEO_Integration::is_rank_math_active();
		}
		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		// File basename (standard) OR class already loaded (mu-plugin / renamed folder).
		return is_plugin_active( 'seo-by-rank-math/rank-math.php' )
			|| class_exists( 'RankMath' )
			|| defined( 'RANK_MATH_VERSION' );
	}

	/**
	 * Check if Yoast SEO is active.
	 */
	public static function is_yoast_active(): bool {
		if ( class_exists( 'Shojaei_SEO_Integration' ) ) {
			return Shojaei_SEO_Integration::is_yoast_active();
		}
		return defined( 'WPSEO_VERSION' ) || class_exists( 'WPSEO_Options' );
	}

	/**
	 * Whether any primary SEO plugin (Yoast / Rank Math / …) is active.
	 */
	public static function has_primary_seo_plugin(): bool {
		if ( class_exists( 'Shojaei_SEO_Integration' ) ) {
			return Shojaei_SEO_Integration::has_primary_seo_plugin();
		}
		return self::is_rank_math_active() || self::is_yoast_active();
	}

	/**
	 * Sanitize redirect type.
	 *
	 * @param string $type Redirect type.
	 * @return string
	 */
	public static function sanitize_redirect_type( string $type ): string {
		return in_array( $type, array( '301', '302', '410', 'none' ), true ) ? $type : 'none';
	}

	/**
	 * Resolve parent product ID for stock tracking (handles variations).
	 *
	 * @param WC_Product $product Product object.
	 * @return int Parent or self ID for simple products; 0 if not trackable.
	 */
	public static function get_trackable_product_id( $product ): int {
		if ( ! $product ) {
			return 0;
		}

		if ( $product->is_type( 'variation' ) ) {
			return (int) $product->get_parent_id();
		}

		if ( $product->is_type( 'variable' ) || $product->is_type( 'simple' ) ) {
			return (int) $product->get_id();
		}

		return 0;
	}

	/**
	 * WooCommerce product editor AJAX (variations/attributes bulk) — defer heavy hooks.
	 *
	 * Must include link_all_variations / add_attributes_and_variations or bulk
	 * variation creation triggers O(n²) OOS sync + IndexNow HTTP per variation.
	 */
	public static function is_wc_product_editor_ajax(): bool {
		if ( ! wp_doing_ajax() ) {
			return false;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WC action name only.
		$action = isset( $_REQUEST['action'] ) ? sanitize_key( wp_unslash( $_REQUEST['action'] ) ) : '';
		if ( '' === $action ) {
			return false;
		}
		return in_array( $action, self::wc_product_editor_ajax_actions(), true );
	}

	/**
	 * Known WooCommerce admin product-data AJAX actions (variations + attributes).
	 *
	 * @return string[]
	 */
	public static function wc_product_editor_ajax_actions(): array {
		return array(
			'woocommerce_save_variations',
			'woocommerce_load_variations',
			'woocommerce_add_variation',
			'woocommerce_remove_variation',
			'woocommerce_remove_variations',
			'woocommerce_link_all_variations',
			'woocommerce_save_attributes',
			'woocommerce_add_attribute',
			'woocommerce_add_new_attribute',
			'woocommerce_add_attribute_and_term',
			'woocommerce_add_attributes_and_variations',
			'woocommerce_bulk_edit_variations',
			'woocommerce_json_search_products_and_variations',
			'woocommerce_get_variation',
			'woocommerce_get_formatted_variation',
		);
	}

	/**
	 * Skip heavy SEO side effects during WC variation/attribute bulk editor AJAX.
	 */
	public static function should_skip_product_save_side_effects(): bool {
		return self::is_wc_product_editor_ajax();
	}

	/**
	 * Check if a variable product has at least one in-stock variation.
	 *
	 * @param int $parent_id Parent product ID.
	 * @return bool
	 */
	public static function variable_product_has_stock( int $parent_id ): bool {
		$parent = wc_get_product( $parent_id );
		if ( ! $parent || ! $parent->is_type( 'variable' ) ) {
			return false;
		}

		foreach ( $parent->get_children() as $variation_id ) {
			$variation = wc_get_product( $variation_id );
			if ( $variation && $variation->is_in_stock() && 'publish' === $variation->get_status() ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check if product is fully out of stock (simple or all variations OOS).
	 *
	 * @param int $product_id Product ID.
	 * @return bool
	 */
	public static function is_product_fully_out_of_stock( int $product_id ): bool {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return false;
		}

		if ( $product->is_type( 'variable' ) ) {
			return ! self::variable_product_has_stock( $product_id );
		}

		return ! $product->is_in_stock();
	}

	/**
	 * Get OOS phase for a given day count (1–4).
	 * Driven by multi-level timeline: message / temporary / auto.
	 *
	 * @param int $days Days out of stock.
	 * @return int Phase number 1-4.
	 */
	public static function get_oos_phase( int $days ): int {
		return self::get_oos_state( $days )['phase'];
	}

	/**
	 * Format a local timestamp as Gregorian MySQL DATETIME.
	 * Never use wp_date() here — Persian calendar plugins turn years into 140x.
	 *
	 * @param int|null $timestamp Local timestamp (current_time style). Null = now.
	 */
	public static function mysql_datetime( ?int $timestamp = null ): string {
		if ( null === $timestamp ) {
			return current_time( 'mysql' );
		}
		// phpcs:ignore WordPress.DateTime.RestrictedFunctions.date_date -- must stay Gregorian for DB storage.
		return date( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Whether an OOS datetime is a plausible Gregorian catalog date.
	 *
	 * @param string $datetime MySQL datetime string.
	 */
	public static function is_plausible_oos_datetime( string $datetime ): bool {
		$datetime = trim( $datetime );
		if ( '' === $datetime || false === strtotime( $datetime ) ) {
			return false;
		}

		// Jalali years stored as "1405-…" parse as year 1405 AD.
		if ( preg_match( '/^(\d{4})-/', $datetime, $m ) ) {
			$year = (int) $m[1];
			if ( $year < 2000 || $year > 2100 ) {
				return false;
			}
		}

		$ts  = (int) strtotime( $datetime );
		$now = (int) current_time( 'timestamp' );
		if ( $ts > ( $now + DAY_IN_SECONDS ) ) {
			return false;
		}
		// Cap: site catalogs older than ~5 years still OK; not 600+.
		if ( ( $now - $ts ) > ( 5 * YEAR_IN_SECONDS ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Days since OOS start (safe; returns 0 for junk / Jalali-as-Gregorian dates).
	 *
	 * @param string $oos_date MySQL datetime.
	 */
	public static function days_since_oos( string $oos_date ): int {
		if ( ! self::is_plausible_oos_datetime( $oos_date ) ) {
			return 0;
		}
		$ts = (int) strtotime( $oos_date );
		return max( 0, (int) floor( ( (int) current_time( 'timestamp' ) - $ts ) / DAY_IN_SECONDS ) );
	}

	/**
	 * Recompute days_oos from oos_date; re-estimate stub "install-day" starts.
	 *
	 * @param int $limit Rows per call.
	 * @return int Updated rows.
	 */
	/**
	 * Tracker rows that still need a historical OOS-start estimate.
	 */
	public static function count_oos_date_backfill(): int {
		global $wpdb;
		$table = self::oos_table();
		$pm    = $wpdb->postmeta;
		$now   = current_time( 'mysql' );
		$n     = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$table} t
				WHERE t.status != 'redirected'
				AND NOT EXISTS (
					SELECT 1 FROM {$pm} obs
					WHERE obs.post_id = t.product_id AND obs.meta_key = %s AND obs.meta_value = %s
				)
				AND (
					t.days_oos > 2000
					OR t.oos_date < %s
					OR TIMESTAMPDIFF(DAY, t.oos_date, %s) < 2
				)",
				'_shojaei_seo_oos_observed',
				'1',
				'2000-01-01 00:00:00',
				$now
			)
		);
		return (int) $n;
	}

	public static function refresh_oos_day_counts( int $limit = 40 ): int {
		global $wpdb;
		$table     = self::oos_table();
		$now_mysql = current_time( 'mysql' );
		$limit     = max( 1, min( 80, $limit ) );
		$pm   = $wpdb->postmeta;
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT t.product_id, t.oos_date, t.days_oos, t.status FROM {$table} t
				LEFT JOIN {$pm} pm ON pm.post_id = t.product_id AND pm.meta_key = %s
				WHERE t.status != 'redirected'
				AND pm.meta_id IS NULL
				AND NOT EXISTS (
					SELECT 1 FROM {$pm} obs
					WHERE obs.post_id = t.product_id AND obs.meta_key = %s AND obs.meta_value = %s
				)
				AND (
					t.days_oos > 2000
					OR t.oos_date < %s
					OR TIMESTAMPDIFF(DAY, t.oos_date, %s) < 2
				)
				ORDER BY t.id ASC LIMIT %d",
				'_shojaei_seo_oos_probed',
				'_shojaei_seo_oos_observed',
				'1',
				'2000-01-01 00:00:00',
				$now_mysql,
				$limit
			)
		);
		if ( empty( $rows ) ) {
			return 0;
		}

		$n = 0;
		foreach ( $rows as $row ) {
			$pid = (int) $row->product_id;
			$oos = (string) $row->oos_date;
			if ( class_exists( 'Shojaei_SEO_OOS_Manager' ) ) {
				$guess = Shojaei_SEO_OOS_Manager::estimate_oos_started_at( $pid, true );
				$gts   = (int) strtotime( $guess );
				$ots   = (int) strtotime( $oos );
				if ( $gts && ( ! $ots || $gts < $ots || ! self::is_plausible_oos_datetime( $oos ) ) ) {
					$oos = $guess;
				}
			}
			$days   = self::days_since_oos( $oos );
			$status = (string) $row->status;
			if ( ! in_array( $status, array( 'needs_manual', 'redirected' ), true ) ) {
				$status = self::get_oos_state( $days )['status'];
			}
			$changed = ( $days !== (int) $row->days_oos || $oos !== (string) $row->oos_date || $status !== (string) $row->status );
			if ( $changed ) {
				$wpdb->update(
					$table,
					array(
						'oos_date' => $oos,
						'days_oos' => $days,
						'status'   => $status,
					),
					array( 'product_id' => $pid ),
					array( '%s', '%d', '%s' ),
					array( '%d' )
				);
				self::sync_oos_postmeta( $pid, $oos, $days );
			}
			if ( $days < 2 ) {
				update_post_meta( $pid, '_shojaei_seo_oos_probed', '1' );
			} else {
				delete_post_meta( $pid, '_shojaei_seo_oos_probed' );
			}
			++$n;
		}
		return $n;
	}

	/**
	 * Repair rows where oos_date was saved with Jalali year via wp_date (e.g. 226898 days).
	 *
	 * @param int $limit Max rows per call.
	 * @return int Number of rows fixed.
	 */
	public static function repair_invalid_oos_dates( int $limit = 500 ): int {
		global $wpdb;
		$table = self::oos_table();
		$rows  = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT product_id, oos_date, days_oos, status FROM {$table}
				WHERE status != 'redirected'
				AND ( days_oos > %d OR oos_date < %s )
				ORDER BY id ASC LIMIT %d",
				2000,
				'2000-01-01 00:00:00',
				max( 1, $limit )
			)
		);

		if ( empty( $rows ) ) {
			return 0;
		}

		$fixed = 0;
		foreach ( $rows as $row ) {
			$product_id = (int) $row->product_id;
			$fresh      = class_exists( 'Shojaei_SEO_OOS_Manager' )
				? Shojaei_SEO_OOS_Manager::estimate_oos_started_at( $product_id, false )
				: self::mysql_datetime();

			if ( ! self::is_plausible_oos_datetime( $fresh ) ) {
				$fresh = self::mysql_datetime();
			}

			$days  = self::days_since_oos( $fresh );
			$state = self::get_oos_state( $days );
			$status = (string) $row->status;
			if ( ! in_array( $status, array( 'needs_manual', 'redirected' ), true ) ) {
				$status = $state['status'];
			}

			$wpdb->update(
				$table,
				array(
					'oos_date' => $fresh,
					'days_oos' => $days,
					'status'   => $status,
				),
				array( 'product_id' => $product_id ),
				array( '%s', '%d', '%s' ),
				array( '%d' )
			);
			self::sync_oos_postmeta( $product_id, $fresh, $days );

			// Resync SEO flags that were set from the bogus day count (phase 4 / noindex).
			$phase      = (int) ( $state['phase'] ?? 1 );
			$noindex_on = $phase >= self::get_noindex_from_phase()
				&& 'yes' === self::get_option( 'shojaei_seo_oos_noindex_enabled', 'yes' );
			update_post_meta( $product_id, '_shojaei_seo_noindex', $noindex_on ? 'yes' : 'no' );
			update_post_meta( $product_id, '_shojaei_seo_link_deprioritized', $phase >= 3 ? 'yes' : 'no' );
			update_post_meta( $product_id, '_shojaei_seo_sitemap_exclude', $phase >= 3 ? 'yes' : 'no' );

			$fixed++;
		}

		return $fixed;
	}

	/**
	 * آیا URL برای درخواست خروجی از سرور امن است؟ (ضد SSRF)
	 *
	 * فقط http/https، بدون IP خصوصی/رزروشده، ترجیحاً همان هاست سایت مگر $allow_external.
	 *
	 * @param string $url            URL.
	 * @param bool   $allow_external اجازه دامنه خارجی (با فیلتر DNS).
	 */
	public static function is_safe_remote_url( string $url, bool $allow_external = false ): bool {
		$url = esc_url_raw( $url );
		if ( '' === $url ) {
			return false;
		}

		$parts = wp_parse_url( $url );
		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return false;
		}

		$scheme = strtolower( (string) $parts['scheme'] );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			return false;
		}

		$host = strtolower( (string) $parts['host'] );
		if ( '' === $host || 'localhost' === $host || preg_match( '/\.local$/', $host ) ) {
			return false;
		}

		// IP تحت‌اللفظی.
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return self::is_public_ip( $host );
		}

		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$home_host = is_string( $home_host ) ? strtolower( $home_host ) : '';
		$site_host = wp_parse_url( site_url(), PHP_URL_HOST );
		$site_host = is_string( $site_host ) ? strtolower( $site_host ) : '';

		$same_site = ( $home_host && $host === $home_host ) || ( $site_host && $host === $site_host );
		if ( ! $same_site && ! $allow_external ) {
			return false;
		}

		// Resolve DNS — بلاک IP خصوصی پشت دامنه.
		$ips = array();
		if ( function_exists( 'dns_get_record' ) ) {
			if ( defined( 'DNS_A' ) ) {
				$a = @dns_get_record( $host, DNS_A ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				if ( is_array( $a ) ) {
					foreach ( $a as $row ) {
						if ( ! empty( $row['ip'] ) ) {
							$ips[] = $row['ip'];
						}
					}
				}
			}
			if ( defined( 'DNS_AAAA' ) ) {
				$aaaa = @dns_get_record( $host, DNS_AAAA ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
				if ( is_array( $aaaa ) ) {
					foreach ( $aaaa as $row ) {
						if ( ! empty( $row['ipv6'] ) ) {
							$ips[] = $row['ipv6'];
						}
					}
				}
			}
		}
		if ( empty( $ips ) ) {
			$resolved = gethostbynamel( $host );
			if ( is_array( $resolved ) ) {
				$ips = $resolved;
			}
		}
		if ( empty( $ips ) ) {
			return false;
		}
		foreach ( $ips as $ip ) {
			if ( ! self::is_public_ip( (string) $ip ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @param string $ip IPv4/IPv6.
	 */
	public static function is_public_ip( string $ip ): bool {
		if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			return false;
		}
		return (bool) filter_var(
			$ip,
			FILTER_VALIDATE_IP,
			FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
		);
	}
}
