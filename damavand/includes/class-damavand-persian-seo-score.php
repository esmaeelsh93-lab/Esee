<?php
/**
 * Damavand Persian SEO Score — offline 0–100 scorer for FA shops (not a Rank Math clone).
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Damavand_Persian_SEO_Score
 */
final class Damavand_Persian_SEO_Score {

	public const META_SCORE     = '_damavand_seo_score';
	public const META_BREAKDOWN = '_damavand_score_breakdown';
	public const META_SEED      = '_damavand_seo_score_seed';

	/**
	 * Boot metabox + AJAX + save.
	 */
	public static function register_hooks(): void {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_metabox' ) );
		add_action( 'save_post', array( __CLASS__, 'save_metabox' ), 20, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_ajax_damavand_seo_score_live', array( __CLASS__, 'ajax_live' ) );
		add_action( 'wp_ajax_damavand_seo_score_finglish', array( __CLASS__, 'ajax_finglish' ) );
		add_action( 'wp_ajax_damavand_seo_score_apply_tpl', array( __CLASS__, 'ajax_apply_tpl' ) );
		add_action( 'wp_ajax_damavand_schema_preview', array( __CLASS__, 'ajax_schema_preview' ) );
		add_filter( 'manage_edit-product_columns', array( __CLASS__, 'add_product_list_column' ), 20 );
		add_action( 'manage_product_posts_custom_column', array( __CLASS__, 'render_product_list_column' ), 10, 2 );
		add_filter( 'manage_edit-product_sortable_columns', array( __CLASS__, 'product_list_sortable_column' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'product_list_orderby_score' ) );
		add_action( 'pre_get_posts', array( __CLASS__, 'product_list_filter_by_score' ) );
		add_action( 'restrict_manage_posts', array( __CLASS__, 'product_list_score_dropdown' ) );
	}

	/**
	 * Supported types.
	 *
	 * @return string[]
	 */
	public static function post_types(): array {
		$types = array( 'product', 'post', 'page' );
		return array_values( array_unique( apply_filters( 'damavand_seo_score_post_types', $types ) ) );
	}

	/**
	 * Normalize FA orthography for keyword matching.
	 *
	 * @param string $text Text.
	 */
	public static function normalize_fa( string $text ): string {
		return class_exists( 'Damavand_Persian_Text' )
			? Damavand_Persian_Text::normalize( $text )
			: trim( mb_strtolower( wp_strip_all_tags( $text ), 'UTF-8' ) );
	}

	/**
	 * Very light Persian stem (suffix strip) for density / presence.
	 *
	 * @param string $word Word.
	 */
	public static function stem_fa( string $word ): string {
		return class_exists( 'Damavand_Persian_Text' )
			? Damavand_Persian_Text::stem( $word )
			: self::normalize_fa( $word );
	}

	/**
	 * Weighted length for SERP (Persian glyphs ≈ wider than Latin).
	 *
	 * @param string $text Text.
	 */
	public static function weighted_length( string $text ): float {
		return class_exists( 'Damavand_Persian_Text' )
			? Damavand_Persian_Text::weighted_length( $text )
			: (float) mb_strlen( wp_strip_all_tags( $text ), 'UTF-8' );
	}

	/**
	 * Whether needle appears in haystack (normalized + stemmed tokens).
	 *
	 * @param string $haystack Haystack.
	 * @param string $needle   Focus keyword.
	 */
	public static function contains_keyword( string $haystack, string $needle ): bool {
		return class_exists( 'Damavand_Persian_Text' )
			? Damavand_Persian_Text::contains_keyword( $haystack, $needle )
			: ( '' !== self::normalize_fa( $needle ) && false !== mb_strpos( self::normalize_fa( $haystack ), self::normalize_fa( $needle ), 0, 'UTF-8' ) );
	}

	/**
	 * @param string $text Text.
	 */
	public static function has_arabic_ye_ke( string $text ): bool {
		return class_exists( 'Damavand_Persian_Text' )
			? Damavand_Persian_Text::has_arabic_ye_ke( $text )
			: (bool) preg_match( '/[يك]/u', $text );
	}

	/**
	 * @param string $title Title.
	 * @param string $focus Focus.
	 */
	public static function focus_near_start( string $title, string $focus ): bool {
		return class_exists( 'Damavand_Persian_Text' )
			? Damavand_Persian_Text::focus_near_start( $title, $focus )
			: self::contains_keyword( mb_substr( $title, 0, 20, 'UTF-8' ), $focus );
	}

	/**
	 * FA/Latin function words skipped in density (no Finglish scan per token).
	 *
	 * @return array<string,true>
	 */
	private static function density_stopwords(): array {
		static $map = null;
		if ( is_array( $map ) ) {
			return $map;
		}
		$words = array(
			'از', 'با', 'و', 'در', 'برای', 'به', 'که', 'این', 'آن', 'را', 'یک', 'یه', 'تا', 'روی', 'هم', 'نیز',
			'یا', 'اگر', 'ولی', 'اما', 'پس', 'چون', 'هر', 'همه', 'شده', 'است', 'بود', 'می',
			'az', 'ba', 'va', 'dar', 'be', 'ke', 'in', 'an', 'ra', 'yek', 'ye', 'ta', 'the', 'a', 'and', 'or', 'of', 'to', 'on', 'for', 'with',
		);
		$map = array();
		foreach ( $words as $w ) {
			$map[ self::normalize_fa( (string) $w ) ] = true;
		}
		return $map;
	}

	/**
	 * Keyword density % with stemming (stopwords ignored in word count).
	 *
	 * @param string $content Content.
	 * @param string $focus   Focus.
	 */
	public static function keyword_density( string $content, string $focus ): float {
		$focus = self::normalize_fa( $focus );
		if ( '' === $focus ) {
			return 0.0;
		}
		$content = self::normalize_fa( $content );
		if ( mb_strlen( $content, 'UTF-8' ) > 12000 ) {
			$content = mb_substr( $content, 0, 12000, 'UTF-8' );
		}
		$stop   = self::density_stopwords();
		$tokens = preg_split( '/\s+/u', $content );
		if ( ! is_array( $tokens ) || empty( $tokens ) ) {
			return 0.0;
		}
		$stem_f = self::stem_fa( $focus );
		$words  = 0;
		$hits   = 0;
		foreach ( $tokens as $t ) {
			$t = self::normalize_fa( (string) $t );
			if ( '' === $t || isset( $stop[ $t ] ) ) {
				continue;
			}
			++$words;
			if ( $t === $focus || self::stem_fa( $t ) === $stem_f || false !== mb_strpos( $t, $stem_f, 0, 'UTF-8' ) ) {
				++$hits;
			}
			if ( $words >= 800 ) {
				break;
			}
		}
		if ( $words < 1 ) {
			return 0.0;
		}
		return round( ( $hits / $words ) * 100, 2 );
	}

	/**
	 * آیا نامک فینگلیش، کلمه کلیدی فارسی را پوشش می‌دهد؟
	 *
	 * @param string $slug  Latin slug.
	 * @param string $focus Focus keyword (FA/EN).
	 */
	public static function slug_covers_focus( string $slug, string $focus ): bool {
		$focus = trim( $focus );
		$slug  = strtolower( trim( $slug ) );
		if ( '' === $focus || '' === $slug ) {
			return false;
		}
		$slug_words = str_replace( '-', ' ', $slug );
		if ( self::contains_keyword( $slug_words, $focus ) ) {
			return true;
		}
		if ( class_exists( 'Shojaei_SEO_Slug' ) ) {
			$focus_latin = Shojaei_SEO_Slug::transliterate( $focus );
			$focus_latin = strtolower( str_replace( '-', ' ', (string) $focus_latin ) );
			if ( '' !== $focus_latin && false !== mb_strpos( $slug_words, $focus_latin, 0, 'UTF-8' ) ) {
				return true;
			}
			// تطبیق توکن‌به‌توکن (کفش ورزشی → kafsh + varzeshi).
			$need = array_filter( explode( ' ', $focus_latin ) );
			$have = array_filter( explode( ' ', $slug_words ) );
			if ( $need && count( array_intersect( $need, $have ) ) >= max( 1, (int) ceil( count( $need ) * 0.6 ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Full analysis for a post (or draft field overrides).
	 *
	 * @param int                  $post_id Post ID.
	 * @param array<string,string> $override title|desc|focus|slug|content.
	 * @return array{score:int,tone:string,checks:array<int,array{id:string,label:string,ok:bool,points:int,max:int,tip:string}>}
	 */
	public static function analyze( int $post_id, array $override = array() ): array {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array(
				'score'          => 0,
				'tone'           => 'bad',
				'checks'         => array(),
				'title_weighted' => 0,
				'desc_weighted'  => 0,
				'title'          => '',
				'desc'           => '',
				'permalink'      => '',
				'site_name'      => wp_strip_all_tags( get_bloginfo( 'name' ) ),
				'next_tip'       => '',
			);
		}

		$live = ! empty( $override['live'] );
		unset( $override['live'] );
		if ( ! $live ) {
			foreach ( array( 'title', 'desc', 'focus', 'slug', 'content', 'excerpt' ) as $okey ) {
				if ( isset( $override[ $okey ] ) && '' === trim( (string) $override[ $okey ] ) ) {
					unset( $override[ $okey ] );
				}
			}
		}

		$title = isset( $override['title'] ) ? (string) $override['title'] : ( class_exists( 'Damavand_SEO_Meta' ) ? Damavand_SEO_Meta::get_title( $post_id, true ) : (string) $post->post_title );
		$desc  = isset( $override['desc'] ) ? (string) $override['desc'] : ( class_exists( 'Damavand_SEO_Meta' ) ? Damavand_SEO_Meta::get_description( $post_id, true ) : '' );
		$focus = isset( $override['focus'] ) ? (string) $override['focus'] : ( class_exists( 'Damavand_SEO_Meta' ) ? Damavand_SEO_Meta::get_focus_keyword( $post_id ) : '' );
		$slug  = isset( $override['slug'] ) ? (string) $override['slug'] : (string) $post->post_name;
		$body  = isset( $override['content'] ) ? (string) $override['content'] : (string) $post->post_content;

		if ( isset( $override['title'] ) && class_exists( 'Damavand_SEO_Templates' ) && false !== strpos( $title, '%' ) ) {
			$title = Damavand_SEO_Templates::expand( $title, $post_id, array( 'title' => (string) $post->post_title, 'focus' => $focus ) );
		}
		if ( isset( $override['desc'] ) && class_exists( 'Damavand_SEO_Templates' ) && false !== strpos( $desc, '%' ) ) {
			$desc = Damavand_SEO_Templates::expand( $desc, $post_id, array( 'title' => (string) $post->post_title, 'focus' => $focus ) );
		}

		$is_product = ( 'product' === $post->post_type );
		$short_html = '';
		if ( isset( $override['excerpt'] ) ) {
			$short_html = (string) $override['excerpt'];
		} elseif ( $is_product && ! isset( $override['content'] ) ) {
			$short_html = (string) $post->post_excerpt;
			if ( '' === $short_html && function_exists( 'wc_get_product' ) ) {
				$pobj = wc_get_product( $post_id );
				if ( $pobj ) {
					$short_html = (string) $pobj->get_short_description();
				}
			}
		}
		$body_combined = $short_html ? ( $short_html . "\n" . $body ) : $body;
		if ( strlen( $body_combined ) > 80000 ) {
			$body_combined = substr( $body_combined, 0, 80000 );
		}
		$body_plain = wp_strip_all_tags( $body_combined );
		if ( mb_strlen( $body_plain, 'UTF-8' ) > 12000 ) {
			$body_plain = mb_substr( $body_plain, 0, 12000, 'UTF-8' );
		}

		$checks = array();
		$score  = 0;

		// 1) Slug — 18
		$slug_pts   = 0;
		$slug_tip   = '';
		$slug_dec   = rawurldecode( $slug );
		$has_fa_slug = class_exists( 'Shojaei_SEO_Slug' ) ? Shojaei_SEO_Slug::has_persian( $slug_dec ) : (bool) preg_match( '/[\x{0600}-\x{06FF}]/u', $slug_dec );
		$finglish    = class_exists( 'Shojaei_SEO_Slug' ) ? Shojaei_SEO_Slug::transliterate( (string) $post->post_title ) : sanitize_title( $post->post_title );
		$slug_len    = strlen( $has_fa_slug ? $finglish : preg_replace( '/[^a-z0-9\-]/', '', strtolower( $slug_dec ) ) );
		if ( $has_fa_slug ) {
			$slug_tip = __( 'نامک فارسی در اشتراک‌گذاری طولانی و زشت می‌شود؛ به فینگلیش لاتین تبدیل کنید (از/با/در حذف می‌شود).', 'shojaei-seo-for-woo' );
			$slug_pts = 3;
		} elseif ( class_exists( 'Shojaei_SEO_Slug' ) && Shojaei_SEO_Slug::is_clean_latin_slug( $slug_dec ) ) {
			$slug_pts = 10;
			$slug_tip = __( 'نامک فینگلیش تمیز است.', 'shojaei-seo-for-woo' );
			$stripped = Shojaei_SEO_Slug::strip_slug_stopwords( $slug_dec );
			if ( $stripped !== $slug_dec && strlen( $stripped ) + 3 < strlen( $slug_dec ) ) {
				$slug_pts -= 2;
				$slug_tip  = __( 'حروف اضافه انگلیسی/فینگلیش اضافی در نامک هست؛ کوتاه‌تر کنید.', 'shojaei-seo-for-woo' );
			}
			if ( $slug_len > 0 && $slug_len <= 55 ) {
				$slug_pts += 3;
			} elseif ( $slug_len > 70 ) {
				$slug_pts -= 1;
				$slug_tip .= ' ' . __( 'نامک خیلی بلند است.', 'shojaei-seo-for-woo' );
			}
			if ( $focus && self::slug_covers_focus( $slug_dec, $focus ) ) {
				$slug_pts += 5;
				$slug_tip  = __( 'نامک فینگلیش تمیز است و کلمه کلیدی را پوشش می‌دهد.', 'shojaei-seo-for-woo' );
			} elseif ( $focus ) {
				$slug_tip .= ' ' . __( 'کلمه کلیدی را در نامک فینگلیش بگنجانید.', 'shojaei-seo-for-woo' );
			}
		} else {
			$slug_pts = 5;
			$slug_tip = __( 'نامک باید فقط a-z، عدد و خط تیره باشد.', 'shojaei-seo-for-woo' );
		}
		$slug_pts = max( 0, min( 18, $slug_pts ) );
		$checks[] = array(
			'id'     => 'slug',
			'label'  => __( 'نامک / فینگلیش', 'shojaei-seo-for-woo' ),
			'ok'     => $slug_pts >= 13,
			'points' => $slug_pts,
			'max'    => 18,
			'tip'    => trim( $slug_tip ),
		);
		$score += $slug_pts;

		// 2) Title — 22
		$title_w = self::weighted_length( $title );
		$t_pts   = 0;
		$t_tip   = '';
		if ( '' === trim( $title ) ) {
			$t_tip = __( 'عنوان سئو خالی است.', 'shojaei-seo-for-woo' );
		} else {
			$t_pts = 8;
			// محدوده وزنی فارسی برای اسنیپت موبایل گوگل.
			if ( $title_w >= 38 && $title_w <= 68 ) {
				$t_pts += 8;
				$t_tip  = __( 'طول عنوان با نمایش فارسی گوگل هم‌خوان است.', 'shojaei-seo-for-woo' );
			} elseif ( $title_w >= 30 && $title_w < 38 ) {
				$t_pts += 5;
				$t_tip  = __( 'عنوان کمی کوتاه است؛ یک صفت یا برند اضافه کنید.', 'shojaei-seo-for-woo' );
			} elseif ( $title_w > 68 && $title_w <= 80 ) {
				$t_pts += 4;
				$t_tip  = __( 'عنوان کمی بلند است؛ ممکن است در موبایل بریده شود.', 'shojaei-seo-for-woo' );
			} elseif ( $title_w < 30 ) {
				$t_pts += 2;
				$t_tip  = __( 'عنوان خیلی کوتاه است.', 'shojaei-seo-for-woo' );
			} else {
				$t_pts += 1;
				$t_tip  = __( 'عنوان برای اسنیپت فارسی خیلی بلند است.', 'shojaei-seo-for-woo' );
			}
			if ( $focus && self::contains_keyword( $title, $focus ) ) {
				$t_pts += 3;
				if ( self::focus_near_start( $title, $focus ) ) {
					$t_pts += 3;
					$t_tip .= ' ' . __( 'کلمه کلیدی نزدیک ابتدای عنوان است.', 'shojaei-seo-for-woo' );
				} else {
					$t_tip .= ' ' . __( 'کلمه کلیدی را نزدیک‌تر به ابتدای عنوان بیاورید.', 'shojaei-seo-for-woo' );
				}
			} elseif ( $focus ) {
				$t_tip .= ' ' . __( 'کلمه کلیدی را در عنوان بیاورید.', 'shojaei-seo-for-woo' );
			}
			if ( self::has_arabic_ye_ke( $title ) ) {
				$t_pts = max( 0, $t_pts - 1 );
				$t_tip .= ' ' . __( 'ی/ک عربی را به ی/ک فارسی یکسان کنید.', 'shojaei-seo-for-woo' );
			}
			$site = wp_strip_all_tags( get_bloginfo( 'name' ) );
			if ( $site && self::normalize_fa( $title ) === self::normalize_fa( $site ) ) {
				$t_pts = max( 0, $t_pts - 4 );
				$t_tip  = __( 'عنوان فقط نام فروشگاه است؛ نام کالا را اضافه کنید.', 'shojaei-seo-for-woo' );
			}
		}
		$t_pts    = max( 0, min( 22, $t_pts ) );
		$checks[] = array(
			'id'     => 'title',
			'label'  => __( 'عنوان سئو (فارسی)', 'shojaei-seo-for-woo' ),
			'ok'     => $t_pts >= 15,
			'points' => $t_pts,
			'max'    => 22,
			'tip'    => trim( $t_tip ),
		);
		$score += $t_pts;

		// 3) Meta description — 15
		$desc_w = self::weighted_length( $desc );
		$d_pts  = 0;
		$d_tip  = '';
		if ( '' === trim( $desc ) ) {
			$d_tip = __( 'متا توضیح خالی است.', 'shojaei-seo-for-woo' );
		} else {
			$d_pts = 5;
			if ( $desc_w >= 95 && $desc_w <= 165 ) {
				$d_pts += 6;
				$d_tip  = __( 'طول توضیح برای اسنیپت فارسی مناسب است.', 'shojaei-seo-for-woo' );
			} elseif ( $desc_w >= 70 && $desc_w < 95 ) {
				$d_pts += 3;
				$d_tip  = __( 'توضیح کمی کوتاه است؛ مزیت خرید را بنویسید.', 'shojaei-seo-for-woo' );
			} else {
				$d_pts += 1;
				$d_tip  = __( 'طول توضیح را نزدیک ۹۵–۱۶۵ واحد وزنی فارسی تنظیم کنید.', 'shojaei-seo-for-woo' );
			}
			if ( $focus && self::contains_keyword( $desc, $focus ) ) {
				$d_pts += 2;
			}
			// دعوت به اقدام رایج فروشگاه ایرانی.
			if ( preg_match( '/خرید|قیمت|ارسال|فروش|تخفیف|موجود/u', $desc ) ) {
				$d_pts += 2;
				$d_tip .= ' ' . __( 'دعوت به اقدام در توضیح دیده می‌شود.', 'shojaei-seo-for-woo' );
			} elseif ( $is_product ) {
				$d_tip .= ' ' . __( 'کلماتی مثل خرید/قیمت/ارسال کلیک را بهتر می‌کند.', 'shojaei-seo-for-woo' );
			}
		}
		$d_pts    = max( 0, min( 15, $d_pts ) );
		$checks[] = array(
			'id'     => 'desc',
			'label'  => __( 'توضیح متا', 'shojaei-seo-for-woo' ),
			'ok'     => $d_pts >= 10,
			'points' => $d_pts,
			'max'    => 15,
			'tip'    => trim( $d_tip ),
		);
		$score += $d_pts;

		// 4) Density / focus — 15
		$density = self::keyword_density( $body_plain . ' ' . $title . ' ' . $desc, $focus );
		$k_pts   = 0;
		$k_tip   = '';
		if ( '' === $focus ) {
			$k_tip = __( 'کلمه کلیدی تمرکزی تنظیم نشده.', 'shojaei-seo-for-woo' );
		} else {
			$k_pts = 4;
			if ( $density >= 0.4 && $density <= 2.8 ) {
				$k_pts += 9;
				$k_tip  = sprintf(
					/* translators: %s: density */
					__( 'تراکم کلمه کلیدی مناسب است (%s٪).', 'shojaei-seo-for-woo' ),
					(string) $density
				);
			} elseif ( $density > 0 && $density < 0.4 ) {
				$k_pts += 4;
				$k_tip  = __( 'کلمه کلیدی کم دیده می‌شود؛ طبیعی در توضیح کوتاه و متن تکرار کنید.', 'shojaei-seo-for-woo' );
			} elseif ( $density > 2.8 ) {
				$k_pts += 2;
				$k_tip  = __( 'تراکم کلمه کلیدی بالاست؛ تکرار مصنوعی را کم کنید.', 'shojaei-seo-for-woo' );
			} else {
				$k_tip = __( 'کلمه کلیدی در متن پیدا نشد (ی/ک و جمع‌ها هم بررسی شد).', 'shojaei-seo-for-woo' );
			}
			if ( $is_product && $short_html && self::contains_keyword( wp_strip_all_tags( $short_html ), $focus ) ) {
				$k_pts += 2;
				$k_tip .= ' ' . __( 'در توضیح کوتاه محصول هم آمده.', 'shojaei-seo-for-woo' );
			}
		}
		$k_pts    = max( 0, min( 15, $k_pts ) );
		$checks[] = array(
			'id'     => 'density',
			'label'  => __( 'کلمه کلیدی و تراکم', 'shojaei-seo-for-woo' ),
			'ok'     => $k_pts >= 10,
			'points' => $k_pts,
			'max'    => 15,
			'tip'    => trim( $k_tip ),
		);
		$score += $k_pts;

		// 5) Content structure — 15 (محصول: آستانه کلمات نرم‌تر)
		$c_pts  = 0;
		$c_tips = array();
		$words  = preg_split( '/\s+/u', trim( $body_plain ) );
		$wcount = is_array( $words ) ? count( array_filter( $words ) ) : 0;
		$min_ok = $is_product ? 80 : 120;
		$min_good = $is_product ? 180 : 300;
		if ( $wcount >= $min_good ) {
			$c_pts += 5;
		} elseif ( $wcount >= $min_ok ) {
			$c_pts += 3;
			$c_tips[] = $is_product
				? __( 'توضیح محصول را کمی کامل‌تر کنید (ویژگی، کاربرد، ارسال).', 'shojaei-seo-for-woo' )
				: __( 'محتوا کوتاه است؛ برای نوشته توضیح کامل‌تر بهتر است.', 'shojaei-seo-for-woo' );
		} else {
			$c_tips[] = __( 'متن خیلی کوتاه است.', 'shojaei-seo-for-woo' );
		}
		if ( preg_match( '/<h2[\s>]/i', $body_combined ) || preg_match( '/<h3[\s>]/i', $body_combined ) ) {
			$c_pts += 3;
		} else {
			$c_tips[] = __( 'از زیرعنوان H2/H3 استفاده کنید.', 'shojaei-seo-for-woo' );
		}
		if ( preg_match_all( '/<a\s[^>]{0,300}href=["\']([^"\']{1,500})["\']/i', $body_combined, $am ) && ! empty( $am[1] ) ) {
			$c_pts += 2;
			$home = wp_parse_url( home_url(), PHP_URL_HOST );
			$has_internal = false;
			foreach ( $am[1] as $href ) {
				if ( $home && false !== stripos( (string) $href, (string) $home ) ) {
					$has_internal = true;
					break;
				}
				if ( 0 === strpos( (string) $href, '/' ) ) {
					$has_internal = true;
					break;
				}
			}
			if ( $has_internal ) {
				$c_pts += 2;
			} else {
				$c_tips[] = __( 'لینک داخلی به دسته یا محصول مرتبط بگذارید.', 'shojaei-seo-for-woo' );
			}
		} else {
			$c_tips[] = __( 'حداقل یک لینک داخلی در محتوا بگذارید.', 'shojaei-seo-for-woo' );
		}
		$sentences = preg_split( '/[.!?؟۔\n]+/u', $body_plain );
		$s_count   = is_array( $sentences ) ? count( array_filter( array_map( 'trim', $sentences ) ) ) : 0;
		if ( $s_count > 0 && $wcount > 0 ) {
			$avg = $wcount / $s_count;
			if ( $avg >= 6 && $avg <= 30 ) {
				$c_pts += 3;
			} else {
				$c_tips[] = __( 'جمله‌ها را برای خوانایی فارسی کوتاه‌تر/متعادل کنید.', 'shojaei-seo-for-woo' );
				$c_pts   += 1;
			}
		}
		if ( $body_combined && false === stripos( $body_combined, 'dir=' ) && preg_match( '/[\x{0600}-\x{06FF}]/u', $body_plain ) && preg_match( '/style=["\'][^"\']*text-align\s*:\s*left/i', $body_combined ) ) {
			$c_pts    = max( 0, $c_pts - 2 );
			$c_tips[] = __( 'محتوا چپ‌چین ذخیره شده؛ برای فارسی RTL درست کنید.', 'shojaei-seo-for-woo' );
		}
		$c_pts    = max( 0, min( 15, $c_pts ) );
		$checks[] = array(
			'id'     => 'content',
			'label'  => __( 'ساختار و خوانایی محتوا', 'shojaei-seo-for-woo' ),
			'ok'     => $c_pts >= 10,
			'points' => $c_pts,
			'max'    => 15,
			'tip'    => $c_tips ? implode( ' ', $c_tips ) : __( 'ساختار محتوا مناسب است.', 'shojaei-seo-for-woo' ),
		);
		$score += $c_pts;

		// 6) Image + shop extras — 15
		$i_pts = 0;
		$i_tip = '';
		$thumb = 0;
		if ( array_key_exists( 'thumbnail_id', $override ) ) {
			$thumb = absint( $override['thumbnail_id'] );
		} else {
			$thumb = (int) get_post_thumbnail_id( $post_id );
			if ( ! $thumb && $is_product && function_exists( 'wc_get_product' ) ) {
				$pobj = wc_get_product( $post_id );
				if ( $pobj ) {
					$thumb = (int) $pobj->get_image_id();
				}
			}
		}
		if ( ! $thumb ) {
			$i_tip = __( 'تصویر شاخص تنظیم نشده.', 'shojaei-seo-for-woo' );
		} else {
			$i_pts = 7;
			$alt   = (string) get_post_meta( $thumb, '_wp_attachment_image_alt', true );
			$alt_n = self::normalize_fa( $alt );
			if ( '' === $alt_n ) {
				$i_tip = __( 'alt تصویر خالی است؛ توضیح فارسی معنادار بنویسید.', 'shojaei-seo-for-woo' );
			} elseif ( ! preg_match( '/[\x{0600}-\x{06FF}]/u', $alt ) && preg_match( '/^[a-z0-9_\-\.\s]+$/i', $alt ) ) {
				$i_pts += 2;
				$i_tip  = __( 'alt شبیه نام فایل انگلیسی است؛ فارسی معنادار بهتر است.', 'shojaei-seo-for-woo' );
			} else {
				$i_pts += 4;
				$i_tip  = __( 'تصویر شاخص با alt مناسب.', 'shojaei-seo-for-woo' );
				if ( $focus && self::contains_keyword( $alt, $focus ) ) {
					$i_pts += 2;
				}
			}
			if ( $is_product ) {
				$gallery = array();
				if ( array_key_exists( 'gallery_ids', $override ) && is_array( $override['gallery_ids'] ) ) {
					$gallery = array_values( array_filter( array_map( 'absint', $override['gallery_ids'] ) ) );
				} elseif ( function_exists( 'wc_get_product' ) ) {
					$pobj = wc_get_product( $post_id );
					if ( $pobj ) {
						$gallery = (array) $pobj->get_gallery_image_ids();
					}
				}
				if ( count( $gallery ) >= 1 ) {
					$i_pts += 2;
					$i_tip .= ' ' . __( 'گالری تصویر دارد.', 'shojaei-seo-for-woo' );
				} else {
					$i_tip .= ' ' . __( 'یک تصویر گالری اضافه کنید.', 'shojaei-seo-for-woo' );
				}
				if ( function_exists( 'wc_get_product' ) ) {
					$pobj = isset( $pobj ) && $pobj ? $pobj : wc_get_product( $post_id );
					if ( $pobj ) {
						$cats = $pobj->get_category_ids();
						if ( empty( $cats ) ) {
							$i_pts = max( 0, $i_pts - 1 );
							$i_tip .= ' ' . __( 'دسته محصول خالی است.', 'shojaei-seo-for-woo' );
						}
					}
				}
			}
		}
		$i_pts    = max( 0, min( 15, $i_pts ) );
		$checks[] = array(
			'id'     => 'image',
			'label'  => $is_product ? __( 'تصویر، گالری و دسته', 'shojaei-seo-for-woo' ) : __( 'تصویر شاخص و alt', 'shojaei-seo-for-woo' ),
			'ok'     => $i_pts >= 10,
			'points' => $i_pts,
			'max'    => 15,
			'tip'    => trim( $i_tip ),
		);
		$score += $i_pts;

		$score = max( 0, min( 100, (int) $score ) );
		$tone  = self::score_tone( $score );

		// اولویت: اول موارد قرمز با بیشترین فاصله از سقف.
		usort(
			$checks,
			static function ( $a, $b ) {
				$da = (int) ( $a['max'] ?? 0 ) - (int) ( $a['points'] ?? 0 );
				$db = (int) ( $b['max'] ?? 0 ) - (int) ( $b['points'] ?? 0 );
				$oa = empty( $a['ok'] ) ? 1 : 0;
				$ob = empty( $b['ok'] ) ? 1 : 0;
				if ( $oa !== $ob ) {
					return $ob <=> $oa;
				}
				return $db <=> $da;
			}
		);

		$next_tip = '';
		foreach ( $checks as $c ) {
			if ( empty( $c['ok'] ) && ! empty( $c['tip'] ) ) {
				$next_tip = (string) $c['label'] . ': ' . (string) $c['tip'];
				break;
			}
		}

		$detailed = class_exists( 'Damavand_Content_Analyzer' )
			? Damavand_Content_Analyzer::run_advisory(
				Damavand_Content_Analyzer::context_from_post( $post_id, $override ) ?: array()
			)
			: array();

		$readability = class_exists( 'Damavand_Persian_Text' )
			? Damavand_Persian_Text::readability_metrics( $body_plain, $body_combined )
			: array();

		return array(
			'score'            => $score,
			'tone'             => $tone,
			'checks'           => $checks,
			'detailed_checks'  => $detailed,
			'readability'      => $readability,
			'advisory_hint'    => self::advisory_potential_gain( $detailed ),
			'title_weighted'   => $title_w,
			'desc_weighted'    => $desc_w,
			'density'          => $density,
			'has_fa_slug'      => $has_fa_slug,
			'finglish'         => $finglish,
			'focus'            => $focus,
			'title'            => $title,
			'desc'             => $desc,
			'permalink'        => (string) get_permalink( $post_id ),
			'site_name'        => wp_strip_all_tags( get_bloginfo( 'name' ) ),
			'next_tip'         => $next_tip,
		);
	}

	public static function score_tone( int $score ): string {
		if ( $score >= 81 ) {
			return 'good';
		}
		if ( $score >= 51 ) {
			return 'ok';
		}
		return 'bad';
	}

	/**
	 * Display-only hint from advisory checklist (does not change persisted score).
	 *
	 * @param array<int,array<string,mixed>> $advisory Advisory rows.
	 */
	public static function advisory_potential_gain( array $advisory ): int {
		$gain = 0;
		foreach ( $advisory as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$st = (string) ( $row['status'] ?? '' );
			if ( 'fail' === $st ) {
				$gain += 2;
			} elseif ( 'warning' === $st ) {
				$gain += 1;
			}
		}
		return min( 15, $gain );
	}

	/**
	 * Score bucket weights — filterable without changing defaults.
	 *
	 * @return array<string,int>
	 */
	public static function score_weights(): array {
		$defaults = array(
			'slug'    => 18,
			'title'   => 22,
			'desc'    => 15,
			'focus'   => 15,
			'content' => 15,
			'image'   => 15,
		);
		$weights = apply_filters( 'damavand_persian_score_weights', $defaults );
		if ( ! is_array( $weights ) ) {
			return $defaults;
		}
		foreach ( $defaults as $key => $max ) {
			if ( ! isset( $weights[ $key ] ) ) {
				$weights[ $key ] = $max;
			} else {
				$weights[ $key ] = max( 1, min( 40, (int) $weights[ $key ] ) );
			}
		}
		return $weights;
	}

	/**
	 * Cache top failed checks for list tooltips.
	 *
	 * @param int                  $post_id  Post ID.
	 * @param array<string,mixed>  $analysis Analysis payload.
	 */
	public static function persist_breakdown( int $post_id, array $analysis ): void {
		$tips = array();
		foreach ( (array) ( $analysis['checks'] ?? array() ) as $check ) {
			if ( ! is_array( $check ) || ! empty( $check['ok'] ) || empty( $check['tip'] ) ) {
				continue;
			}
			$tips[] = array(
				'label' => (string) ( $check['label'] ?? '' ),
				'tip'   => (string) ( $check['tip'] ?? '' ),
			);
			if ( count( $tips ) >= 3 ) {
				break;
			}
		}
		update_post_meta( $post_id, self::META_BREAKDOWN, $tips );
	}

	/**
	 * Persist score meta (no overwrite of seed unless computing fresh).
	 *
	 * @param int $post_id Post.
	 */
	public static function persist_score( int $post_id ): int {
		$analysis = self::analyze( $post_id );
		update_post_meta( $post_id, self::META_SCORE, (int) $analysis['score'] );
		self::persist_breakdown( $post_id, $analysis );
		return (int) $analysis['score'];
	}

	/**
	 * Seed from Rank Math score once (migration) — never overwrites Damavand score if set.
	 *
	 * @param int $post_id Post.
	 */
	public static function seed_from_rank_math( int $post_id ): bool {
		$existing = get_post_meta( $post_id, self::META_SCORE, true );
		if ( '' !== (string) $existing && false !== $existing ) {
			return false;
		}
		$rm = get_post_meta( $post_id, 'rank_math_seo_score', true );
		if ( '' === (string) $rm || ! is_numeric( $rm ) ) {
			return false;
		}
		$seed = max( 0, min( 100, (int) $rm ) );
		update_post_meta( $post_id, self::META_SEED, $seed );
		update_post_meta( $post_id, self::META_SCORE, $seed );
		return true;
	}

	/**
	 * Metabox.
	 */
	public static function register_metabox(): void {
		foreach ( self::post_types() as $type ) {
			$context = ( 'product' === $type ) ? 'normal' : 'side';
			add_meta_box(
				'damavand_persian_seo_score',
				__( 'سئوی Damavand (فارسی)', 'shojaei-seo-for-woo' ),
				array( __CLASS__, 'render_metabox' ),
				$type,
				$context,
				'high'
			);
		}
	}

	/**
	 * @param WP_Post $post Post.
	 */
	public static function render_metabox( $post ): void {
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		wp_nonce_field( 'damavand_seo_score_save', 'damavand_seo_score_nonce' );
		$analysis = self::analyze( (int) $post->ID );
		$title    = class_exists( 'Damavand_SEO_Meta' ) ? Damavand_SEO_Meta::get_damavand_only( (int) $post->ID, 'title' ) : '';
		$desc     = class_exists( 'Damavand_SEO_Meta' ) ? Damavand_SEO_Meta::get_damavand_only( (int) $post->ID, 'desc' ) : '';
		$focus    = class_exists( 'Damavand_SEO_Meta' ) ? Damavand_SEO_Meta::get_damavand_only( (int) $post->ID, 'focus' ) : '';
		if ( '' === $title ) {
			$title = class_exists( 'Damavand_SEO_Meta' ) ? Damavand_SEO_Meta::get_title( (int) $post->ID, false ) : '';
		}
		if ( '' === $desc ) {
			$desc = class_exists( 'Damavand_SEO_Meta' ) ? Damavand_SEO_Meta::get_description( (int) $post->ID, false ) : '';
		}
		if ( '' === $focus ) {
			$focus = class_exists( 'Damavand_SEO_Meta' ) ? Damavand_SEO_Meta::get_focus_keyword( (int) $post->ID ) : '';
		}
		$serp_url  = (string) get_permalink( $post );
		$site_name = wp_strip_all_tags( get_bloginfo( 'name' ) );
		$faq_count = class_exists( 'Damavand_FAQ_Box' ) ? count( Damavand_FAQ_Box::get_stored( (int) $post->ID ) ) : 0;
		$related   = class_exists( 'Damavand_Content_Analyzer' )
			? (string) get_post_meta( (int) $post->ID, Damavand_Content_Analyzer::META_RELATED, true )
			: '';
		$robots_flags = class_exists( 'Damavand_SEO_Meta' ) ? Damavand_SEO_Meta::get_robots( (int) $post->ID ) : array();
		$robots_noindex  = in_array( 'noindex', $robots_flags, true );
		$robots_nofollow = in_array( 'nofollow', $robots_flags, true );
		include DAMAVAND_SEO_DIR . 'admin/views/metabox-persian-seo-score.php';
	}

	/**
	 * @param int     $post_id ID.
	 * @param WP_Post $post    Post.
	 */
	public static function save_metabox( $post_id, $post ): void {
		if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, self::post_types(), true ) ) {
			return;
		}
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}
		if ( ! isset( $_POST['damavand_seo_score_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['damavand_seo_score_nonce'] ) ), 'damavand_seo_score_save' ) ) {
			return;
		}
		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$title = isset( $_POST['damavand_seo_score_title'] ) ? sanitize_text_field( wp_unslash( $_POST['damavand_seo_score_title'] ) ) : null;
		$desc  = isset( $_POST['damavand_seo_score_desc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['damavand_seo_score_desc'] ) ) : null;
		$focus = isset( $_POST['damavand_seo_score_focus'] ) ? sanitize_text_field( wp_unslash( $_POST['damavand_seo_score_focus'] ) ) : null;
		$related = isset( $_POST['damavand_seo_score_related'] ) ? sanitize_text_field( wp_unslash( $_POST['damavand_seo_score_related'] ) ) : null;

		if ( ! class_exists( 'Damavand_SEO_Meta' ) ) {
			return;
		}
		if ( null !== $title ) {
			if ( '' === $title ) {
				delete_post_meta( $post_id, Damavand_SEO_Meta::TITLE );
			} else {
				update_post_meta( $post_id, Damavand_SEO_Meta::TITLE, $title );
			}
		}
		if ( null !== $desc ) {
			if ( '' === $desc ) {
				delete_post_meta( $post_id, Damavand_SEO_Meta::DESC );
			} else {
				update_post_meta( $post_id, Damavand_SEO_Meta::DESC, $desc );
			}
		}
		if ( null !== $focus ) {
			$focus = trim( $focus );
			if ( '' === $focus && 'product' === $post->post_type ) {
				$focus = trim( wp_strip_all_tags( (string) $post->post_title ) );
			}
			if ( '' === $focus ) {
				delete_post_meta( $post_id, Damavand_SEO_Meta::FOCUS );
			} else {
				update_post_meta( $post_id, Damavand_SEO_Meta::FOCUS, $focus );
			}
		}
		if ( null !== $related && class_exists( 'Damavand_Content_Analyzer' ) && 'product' === $post->post_type ) {
			if ( '' === trim( $related ) ) {
				delete_post_meta( $post_id, Damavand_Content_Analyzer::META_RELATED );
			} else {
				update_post_meta( $post_id, Damavand_Content_Analyzer::META_RELATED, Damavand_Content_Analyzer::normalize_related_input( $related ) );
			}
		}

		// Persist size chart from SEO metabox (synced with WooCommerce field).
		if ( 'product' === $post->post_type && isset( $_POST['damavand_size_chart_raw'] ) && class_exists( 'Damavand_Size_Chart' ) ) {
			Damavand_Size_Chart::save(
				(int) $post_id,
				sanitize_textarea_field( wp_unslash( $_POST['damavand_size_chart_raw'] ) )
			);
		}

		// Per-post robots (Rank Math parity — explicit merchant control).
		if ( isset( $_POST['damavand_seo_robots_present'] ) ) {
			$flags = array();
			if ( ! empty( $_POST['damavand_seo_robots_noindex'] ) ) {
				$flags[] = 'noindex';
			}
			if ( ! empty( $_POST['damavand_seo_robots_nofollow'] ) ) {
				$flags[] = 'nofollow';
			}
			if ( empty( $flags ) ) {
				delete_post_meta( $post_id, Damavand_SEO_Meta::ROBOTS );
			} else {
				update_post_meta( $post_id, Damavand_SEO_Meta::ROBOTS, $flags );
			}
		}

		self::persist_score( (int) $post_id );
	}

	/**
	 * Assets on post editor.
	 *
	 * @param string $hook Hook.
	 */
	public static function enqueue( string $hook ): void {
		if ( 'edit.php' === $hook ) {
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( $screen && 'product' === $screen->post_type ) {
				wp_enqueue_style(
					'damavand-seo-score',
					DAMAVAND_SEO_URL . 'admin/css/damavand-seo-score.css',
					array(),
					DAMAVAND_SEO_VERSION
				);
			}
			return;
		}
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->post_type, self::post_types(), true ) ) {
			return;
		}
		wp_enqueue_style(
			'damavand-seo-score',
			DAMAVAND_SEO_URL . 'admin/css/damavand-seo-score.css',
			array(),
			DAMAVAND_SEO_VERSION
		);
		wp_enqueue_script(
			'damavand-seo-score',
			DAMAVAND_SEO_URL . 'admin/js/damavand-seo-score.js',
			array( 'jquery' ),
			DAMAVAND_SEO_VERSION,
			true
		);
		wp_localize_script(
			'damavand-seo-score',
			'damavandSeoScore',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'damavand_seo_score_live' ),
				'i18n'    => array(
					'error'       => __( 'خطا در محاسبه امتیاز.', 'shojaei-seo-for-woo' ),
					'applied'     => __( 'فینگلیش اعمال شد. ذخیره کنید تا ۳۰۱ ساخته شود.', 'shojaei-seo-for-woo' ),
					'tplOk'       => __( 'قالب در فیلدهای خالی قرار گرفت. ذخیره کنید.', 'shojaei-seo-for-woo' ),
					'tplFill'     => __( 'فیلدها پر بودند؛ برای جایگزینی دوباره کلیک کنید.', 'shojaei-seo-for-woo' ),
					'linkLoading' => __( 'در حال یافتن پیشنهاد…', 'shojaei-seo-for-woo' ),
					'linkInject'  => __( 'در حال درج لینک…', 'shojaei-seo-for-woo' ),
					'linkNone'    => __( 'پیشنهاد مرتبطی پیدا نشد.', 'shojaei-seo-for-woo' ),
					'linkPick'    => __( 'حداقل یک مقصد را انتخاب کنید.', 'shojaei-seo-for-woo' ),
					'linkFix'     => __( 'حذف لینک', 'shojaei-seo-for-woo' ),
					'faqLoading'  => __( 'در حال ساخت FAQ…', 'shojaei-seo-for-woo' ),
					'faqInject'   => __( 'در حال درج FAQ…', 'shojaei-seo-for-woo' ),
					'faqPick'     => __( 'حداقل یک سؤال را انتخاب کنید.', 'shojaei-seo-for-woo' ),
					'faqHas'      => __( 'FAQ در محتوا هست — درج دوباره بلوک قبلی را جایگزین می‌کند.', 'shojaei-seo-for-woo' ),
				),
			)
		);
	}

	/**
	 * Product list: SEO score column header.
	 *
	 * @param array<string,string> $columns Columns.
	 * @return array<string,string>
	 */
	public static function add_product_list_column( array $columns ): array {
		$out   = array();
		$added = false;
		foreach ( $columns as $key => $label ) {
			$out[ $key ] = $label;
			if ( ! $added && 'name' === $key ) {
				$out['damavand_seo_score'] = __( 'سئو', 'shojaei-seo-for-woo' );
				$added                     = true;
			}
		}
		if ( ! $added ) {
			$out['damavand_seo_score'] = __( 'سئو', 'shojaei-seo-for-woo' );
		}
		return $out;
	}

	/**
	 * Product list: render cached score badge.
	 *
	 * @param string $column  Column key.
	 * @param int    $post_id Post ID.
	 */
	public static function render_product_list_column( string $column, int $post_id ): void {
		if ( 'damavand_seo_score' !== $column ) {
			return;
		}
		$raw   = get_post_meta( $post_id, self::META_SCORE, true );
		$score = ( '' === (string) $raw || false === $raw ) ? null : max( 0, min( 100, (int) $raw ) );
		if ( null === $score ) {
			echo '<span class="dm-list-score dm-list-score--na" title="' . esc_attr__( 'هنوز امتیاز ذخیره نشده — یک بار ویرایش/ذخیره کنید.', 'shojaei-seo-for-woo' ) . '">—</span>';
			return;
		}
		$tone  = self::score_tone( $score );
		$tips  = get_post_meta( $post_id, self::META_BREAKDOWN, true );
		$title = '';
		if ( is_array( $tips ) && ! empty( $tips ) ) {
			$parts = array();
			foreach ( $tips as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}
				$label = trim( (string) ( $row['label'] ?? '' ) );
				$tip   = trim( (string) ( $row['tip'] ?? '' ) );
				if ( '' === $tip ) {
					continue;
				}
				$parts[] = ( $label ? $label . ': ' : '' ) . $tip;
			}
			if ( ! empty( $parts ) ) {
				$title = implode( ' | ', $parts );
			}
		}
		$edit = get_edit_post_link( $post_id, 'raw' );
		$href = $edit ? add_query_arg( 'damavand_seo_focus', '1', $edit ) : '';
		printf(
			'<a class="dm-list-score dm-list-score--%1$s" href="%2$s" title="%3$s" aria-label="%4$s"><span>%5$d</span></a>',
			esc_attr( $tone ),
			esc_url( $href ? $href : '#' ),
			esc_attr( $title ),
			esc_attr(
				sprintf(
					/* translators: %d: score */
					__( 'امتیاز سئو: %d', 'shojaei-seo-for-woo' ),
					$score
				)
			),
			(int) $score
		);
	}

	/**
	 * @param array<string,string> $columns Sortable columns.
	 * @return array<string,string>
	 */
	public static function product_list_sortable_column( array $columns ): array {
		$columns['damavand_seo_score'] = 'damavand_seo_score';
		return $columns;
	}

	/**
	 * Order product list by cached score meta.
	 *
	 * @param WP_Query $query Query.
	 */
	public static function product_list_orderby_score( $query ): void {
		if ( ! is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() ) {
			return;
		}
		if ( 'damavand_seo_score' !== $query->get( 'orderby' ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-product' !== $screen->id ) {
			return;
		}
		$query->set( 'meta_key', self::META_SCORE );
		$query->set( 'orderby', 'meta_value_num' );
	}

	/**
	 * Dropdown filter on All Products: under 70 / under 80 / unscored / good.
	 *
	 * @param string $post_type Post type.
	 */
	public static function product_list_score_dropdown( string $post_type ): void {
		if ( 'product' !== $post_type ) {
			return;
		}
		$current = isset( $_GET['damavand_seo_score_filter'] ) ? sanitize_key( wp_unslash( $_GET['damavand_seo_score_filter'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$opts    = array(
			''           => __( 'همه امتیازهای سئو', 'shojaei-seo-for-woo' ),
			'under_70'   => __( 'امتیاز زیر ۷۰', 'shojaei-seo-for-woo' ),
			'under_80'   => __( 'امتیاز زیر ۸۰', 'shojaei-seo-for-woo' ),
			'70_79'      => __( 'امتیاز ۷۰ تا ۷۹', 'shojaei-seo-for-woo' ),
			'80_plus'    => __( 'امتیاز ۸۰ و بالاتر', 'shojaei-seo-for-woo' ),
			'unscored'   => __( 'بدون امتیاز ذخیره‌شده', 'shojaei-seo-for-woo' ),
		);
		echo '<select name="damavand_seo_score_filter" id="damavand-seo-score-filter">';
		foreach ( $opts as $value => $label ) {
			printf(
				'<option value="%1$s" %2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	/**
	 * Apply SEO score meta_query on product list.
	 *
	 * @param WP_Query $query Query.
	 */
	public static function product_list_filter_by_score( $query ): void {
		if ( ! is_admin() || ! $query instanceof WP_Query || ! $query->is_main_query() ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || 'edit-product' !== $screen->id ) {
			return;
		}
		$filter = isset( $_GET['damavand_seo_score_filter'] ) ? sanitize_key( wp_unslash( $_GET['damavand_seo_score_filter'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( '' === $filter ) {
			return;
		}

		$meta_query = (array) $query->get( 'meta_query' );
		if ( ! is_array( $meta_query ) ) {
			$meta_query = array();
		}

		switch ( $filter ) {
			case 'under_70':
				$meta_query[] = array(
					'key'     => self::META_SCORE,
					'value'   => 70,
					'compare' => '<',
					'type'    => 'NUMERIC',
				);
				break;
			case 'under_80':
				$meta_query[] = array(
					'key'     => self::META_SCORE,
					'value'   => 80,
					'compare' => '<',
					'type'    => 'NUMERIC',
				);
				break;
			case '70_79':
				$meta_query[] = array(
					'key'     => self::META_SCORE,
					'value'   => array( 70, 79 ),
					'compare' => 'BETWEEN',
					'type'    => 'NUMERIC',
				);
				break;
			case '80_plus':
				$meta_query[] = array(
					'key'     => self::META_SCORE,
					'value'   => 80,
					'compare' => '>=',
					'type'    => 'NUMERIC',
				);
				break;
			case 'unscored':
				$meta_query[] = array(
					'relation' => 'OR',
					array(
						'key'     => self::META_SCORE,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'     => self::META_SCORE,
						'value'   => '',
						'compare' => '=',
					),
				);
				break;
			default:
				return;
		}

		$query->set( 'meta_query', $meta_query );
	}

	/**
	 * Live score AJAX.
	 */
	public static function ajax_live(): void {
		check_ajax_referer( 'damavand_seo_score_live', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( $post_id < 1 || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) ), 403 );
		}
		$gallery_raw = isset( $_POST['gallery'] ) ? sanitize_text_field( wp_unslash( $_POST['gallery'] ) ) : '';
		$gallery_ids = array_filter( array_map( 'absint', preg_split( '/\s*,\s*/', $gallery_raw ) ) );
		$content_raw = isset( $_POST['content'] ) ? (string) wp_unslash( $_POST['content'] ) : '';
		$excerpt_raw = isset( $_POST['excerpt'] ) ? (string) wp_unslash( $_POST['excerpt'] ) : '';
		if ( strlen( $content_raw ) > 80000 ) {
			$content_raw = substr( $content_raw, 0, 80000 );
		}
		if ( strlen( $excerpt_raw ) > 20000 ) {
			$excerpt_raw = substr( $excerpt_raw, 0, 20000 );
		}
		$override    = array(
			'live'         => ! empty( $_POST['live'] ),
			'title'        => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'desc'         => isset( $_POST['desc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['desc'] ) ) : '',
			'focus'        => isset( $_POST['focus'] ) ? sanitize_text_field( wp_unslash( $_POST['focus'] ) ) : '',
			'related'      => isset( $_POST['related'] ) ? sanitize_text_field( wp_unslash( $_POST['related'] ) ) : '',
			'slug'         => isset( $_POST['slug'] ) ? sanitize_title( wp_unslash( $_POST['slug'] ) ) : '',
			'content'      => wp_kses_post( $content_raw ),
			'excerpt'      => wp_kses_post( $excerpt_raw ),
			'gallery_ids'  => $gallery_ids,
			'thumbnail_id' => isset( $_POST['thumbnail_id'] ) ? absint( $_POST['thumbnail_id'] ) : 0,
		);
		wp_send_json_success( self::analyze( $post_id, $override ) );
	}

	/**
	 * Suggest / apply finglish slug (field only; save_post creates 301 via Slug module).
	 */
	public static function ajax_finglish(): void {
		check_ajax_referer( 'damavand_seo_score_live', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( $post_id < 1 || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) ), 403 );
		}
		$post = get_post( $post_id );
		if ( ! $post || ! class_exists( 'Shojaei_SEO_Slug' ) ) {
			wp_send_json_error( array( 'message' => __( 'ماژول نامک در دسترس نیست.', 'shojaei-seo-for-woo' ) ) );
		}
		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : (string) $post->post_title;
		if ( '' === trim( $title ) ) {
			$title = (string) $post->post_title;
		}

		$ctx = array(
			'post_id'    => $post_id,
			'title'      => $title,
			'keyword'    => (string) get_post_meta( $post_id, '_damavand_seo_focus_keyword', true ),
			'categories' => '',
			'attributes' => '',
		);
		if ( class_exists( 'Shojaei_SEO_AI_Engine' ) ) {
			$enriched = Shojaei_SEO_AI_Engine::product_seo_context( $post_id );
			$ctx['categories'] = $enriched['categories'] ?? '';
			$ctx['attributes'] = $enriched['attributes'] ?? '';
		}

		$latin    = '';
		$via_ai   = false;
		$fallback = false;

		if ( class_exists( 'Shojaei_SEO_AI_Client' ) && Shojaei_SEO_AI_Client::is_configured() && class_exists( 'Shojaei_SEO_AI_Engine' ) ) {
			$ai_slug = Shojaei_SEO_AI_Engine::generate_slug( $ctx );
			if ( ! is_wp_error( $ai_slug ) && '' !== $ai_slug ) {
				$latin  = $ai_slug;
				$via_ai = true;
			}
		}

		if ( '' === $latin ) {
			$latin    = Shojaei_SEO_Slug::transliterate( $title );
			$fallback = true;
		}

		$latin = Shojaei_SEO_Slug::uniquify_slug( $latin, $post_id, (string) $post->post_type, (string) $post->post_status, (int) $post->post_parent );
		if ( '' === $latin ) {
			wp_send_json_error( array( 'message' => __( 'فینگلیش ساخته نشد.', 'shojaei-seo-for-woo' ) ) );
		}

		$message = $via_ai
			? __( 'نامک فینگلیش تولید شد — در ویرایشگر اعمال شد.', 'shojaei-seo-for-woo' )
			: __( 'پیشنهاد فینگلیش آفلاین — در ویرایشگر اعمال شد.', 'shojaei-seo-for-woo' );

		wp_send_json_success(
			array(
				'slug'    => $latin,
				'message' => $message,
				'source'  => $via_ai ? 'ai' : 'offline',
			)
		);
	}

	/**
	 * پر کردن فیلدهای خالی از قالب نوع محتوا (خام با توکن، برای ذخیره).
	 */
	public static function ajax_apply_tpl(): void {
		check_ajax_referer( 'damavand_seo_score_live', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( $post_id < 1 || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) ), 403 );
		}
		if ( ! class_exists( 'Damavand_SEO_Templates' ) ) {
			wp_send_json_error( array( 'message' => __( 'قالب‌ها در دسترس نیست.', 'shojaei-seo-for-woo' ) ) );
		}

		$force = ! empty( $_POST['force'] );
		$title_raw = Damavand_SEO_Templates::get_raw_template_for_post( $post_id, 'title' );
		$desc_raw  = Damavand_SEO_Templates::get_raw_template_for_post( $post_id, 'desc' );
		$preview   = array(
			'title' => Damavand_SEO_Templates::expand( $title_raw, $post_id ),
			'desc'  => Damavand_SEO_Templates::expand( $desc_raw, $post_id ),
		);

		wp_send_json_success(
			array(
				'title_raw' => $title_raw,
				'desc_raw'  => $desc_raw,
				'preview'   => $preview,
				'force'     => $force,
				'message'   => __( 'قالب آماده است.', 'shojaei-seo-for-woo' ),
			)
		);
	}

	/**
	 * JSON-LD preview for editor (Damavand output only).
	 */
	public static function ajax_schema_preview(): void {
		check_ajax_referer( 'damavand_seo_score_live', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( $post_id < 1 || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) ), 403 );
		}
		if ( ! class_exists( 'Shojaei_SEO_Schema_Generator' ) ) {
			wp_send_json_error( array( 'message' => __( 'ماژول اسکیما در دسترس نیست.', 'shojaei-seo-for-woo' ) ) );
		}

		$blocks  = Shojaei_SEO_Schema_Generator::preview_for_post( $post_id );
		$flags   = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT;
		$payload = array();
		foreach ( $blocks as $block ) {
			$json = wp_json_encode( $block['schema'] ?? array(), $flags );
			if ( false === $json ) {
				continue;
			}
			$payload[] = array(
				'kind' => (string) ( $block['kind'] ?? 'schema' ),
				'json' => $json,
			);
		}

		wp_send_json_success(
			array(
				'blocks'    => $payload,
				'permalink' => (string) get_permalink( $post_id ),
				'message'   => empty( $payload )
					? __( 'اسکیمای فعالی برای این محتوا تعریف نشده (تنظیمات یا نوع محتوا).', 'shojaei-seo-for-woo' )
					: __( 'پیش‌نمایش JSON-LD آماده است.', 'shojaei-seo-for-woo' ),
			)
		);
	}
}
