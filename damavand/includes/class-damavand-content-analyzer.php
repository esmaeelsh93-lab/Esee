<?php
/**
 * Modular content SEO checks (guide sections 1–2, 6) — advisory layer; score weights unchanged on upgrade.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Damavand_Content_Analyzer
 */
final class Damavand_Content_Analyzer {

	public const META_RELATED = '_damavand_seo_related_keywords';

	public const MAX_RELATED = 15;

	/**
	 * Build context array from a post for checks.
	 *
	 * @param int                  $post_id  Post ID.
	 * @param array<string,mixed>  $override Live overrides.
	 * @return array<string,mixed>|null
	 */
	public static function context_from_post( int $post_id, array $override = array() ): ?array {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return null;
		}

		$live = ! empty( $override['live'] );
		unset( $override['live'] );
		if ( ! $live ) {
			foreach ( array( 'title', 'desc', 'focus', 'slug', 'content', 'excerpt', 'related' ) as $okey ) {
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

		$related_raw = isset( $override['related'] ) ? (string) $override['related'] : (string) get_post_meta( $post_id, self::META_RELATED, true );
		$related     = self::normalize_related_input( $related_raw );

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

		$thumb = 0;
		if ( array_key_exists( 'thumbnail_id', $override ) ) {
			$thumb = absint( $override['thumbnail_id'] );
		} else {
			$thumb = (int) get_post_thumbnail_id( $post_id );
		}

		$product = null;
		if ( $is_product && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $post_id );
		}

		return array(
			'post_id'       => $post_id,
			'post_type'     => (string) $post->post_type,
			'is_product'    => $is_product,
			'title'         => $title,
			'desc'          => $desc,
			'focus'         => $focus,
			'related'       => $related,
			'slug'          => $slug,
			'body_html'     => $body_combined,
			'body_plain'    => $body_plain,
			'short_html'    => $short_html,
			'thumbnail_id'  => $thumb,
			'product'       => $product,
			'gallery_ids'   => self::gallery_ids( $post_id, $product, $override ),
		);
	}

	/**
	 * Run all advisory / extended checks (does not change cached score until save).
	 *
	 * @param array<string,mixed> $ctx Context.
	 * @return array<int,array<string,mixed>>
	 */
	public static function run_advisory( array $ctx ): array {
		$checks = array(
			self::check_keyword_in_intro( $ctx ),
			self::check_keyword_in_subheadings( $ctx ),
			self::check_external_links( $ctx ),
			self::check_transition_words( $ctx ),
			self::check_passive_voice( $ctx ),
			self::check_paragraph_length( $ctx ),
			self::check_subheading_distribution( $ctx ),
			self::check_related_keywords( $ctx ),
			self::check_number_in_title( $ctx ),
			self::check_power_words( $ctx ),
			self::check_sentiment_words( $ctx ),
		);

		if ( ! empty( $ctx['is_product'] ) ) {
			$checks = array_merge(
				$checks,
				array(
					self::check_product_price( $ctx ),
					self::check_product_stock( $ctx ),
					self::check_product_sku( $ctx ),
					self::check_product_short_description( $ctx ),
					self::check_product_category( $ctx ),
					self::check_product_reviews( $ctx ),
					self::check_product_image_size( $ctx ),
				)
			);
		}

		/**
		 * Filter advisory checks after built-in run.
		 *
		 * @param array<int,array<string,mixed>> $checks Checks.
		 * @param array<string,mixed>            $ctx    Context.
		 */
		return array_values( array_filter( (array) apply_filters( 'damavand_content_analyzer_advisory', $checks, $ctx ) ) );
	}

	/**
	 * Simplified score for taxonomy term description (0–100).
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy.
	 * @param array<string,mixed> $override Overrides.
	 * @return array<string,mixed>
	 */
	public static function score_term( int $term_id, string $taxonomy, array $override = array() ): array {
		$term = get_term( $term_id, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			return array( 'score' => 0, 'tone' => 'bad', 'checks' => array(), 'next_tip' => '' );
		}

		$title = isset( $override['title'] ) ? (string) $override['title'] : (string) get_term_meta( $term_id, Damavand_SEO_Meta::TITLE, true );
		if ( '' === trim( $title ) ) {
			$title = (string) $term->name;
		}
		$desc  = isset( $override['desc'] ) ? (string) $override['desc'] : (string) get_term_meta( $term_id, Damavand_SEO_Meta::DESC, true );
		if ( '' === trim( $desc ) ) {
			$desc = wp_strip_all_tags( (string) $term->description );
		}
		$focus = isset( $override['focus'] ) ? (string) $override['focus'] : (string) get_term_meta( $term_id, Damavand_SEO_Meta::FOCUS, true );

		$plain = wp_strip_all_tags( $desc );
		$score = 0;
		$checks = array();

		$t_pts = 0;
		$t_w   = Damavand_Persian_Text::weighted_length( $title );
		if ( $t_w >= 20 && $t_w <= 55 ) {
			$t_pts = 25;
		} elseif ( $t_w > 0 ) {
			$t_pts = 12;
		}
		if ( $focus && Damavand_Persian_Text::contains_keyword( $title, $focus ) ) {
			$t_pts += 10;
		}
		$t_pts = min( 35, $t_pts );
		$checks[] = self::pack_group( 'term_title', __( 'عنوان دسته', 'shojaei-seo-for-woo' ), $t_pts, 35, $t_pts >= 25 );
		$score += $t_pts;

		$d_pts = 0;
		$words = Damavand_Persian_Text::word_count( $plain );
		if ( $words >= 150 ) {
			$d_pts = 30;
		} elseif ( $words >= 80 ) {
			$d_pts = 18;
		} elseif ( $words >= 40 ) {
			$d_pts = 10;
		}
		if ( $focus && Damavand_Persian_Text::contains_keyword( $plain, $focus ) ) {
			$d_pts += 8;
		}
		if ( preg_match( '/<h2[\s>]/i', $desc ) || preg_match( '/<h2[\s>]/i', (string) $term->description ) ) {
			$d_pts += 5;
		}
		$d_pts = min( 45, $d_pts );
		$checks[] = self::pack_group(
			'term_desc',
			__( 'توضیح دسته', 'shojaei-seo-for-woo' ),
			$d_pts,
			45,
			$d_pts >= 30,
			$words < 150
				? sprintf(
					/* translators: %d: word count */
					__( 'توضیح حدود %d کلمه است؛ هدف ~۱۵۰ کلمه.', 'shojaei-seo-for-woo' ),
					$words
				)
				: __( 'طول و ساختار توضیح مناسب است.', 'shojaei-seo-for-woo' )
		);
		$score += $d_pts;

		$k_pts = 0;
		if ( '' === trim( $focus ) ) {
			$k_tip = __( 'کلمه کلیدی تمرکزی تنظیم نشده.', 'shojaei-seo-for-woo' );
		} else {
			$k_pts = Damavand_Persian_Text::contains_keyword( $title . ' ' . $plain, $focus ) ? 20 : 8;
			$k_tip = $k_pts >= 20
				? __( 'کلمه کلیدی در عنوان یا توضیح دیده می‌شود.', 'shojaei-seo-for-woo' )
				: __( 'کلمه کلیدی را در توضیح دسته بیاورید.', 'shojaei-seo-for-woo' );
		}
		$checks[] = self::pack_group( 'term_focus', __( 'کلمه کلیدی', 'shojaei-seo-for-woo' ), $k_pts, 20, $k_pts >= 15, $k_tip ?? '' );
		$score += $k_pts;

		$score = max( 0, min( 100, (int) $score ) );
		$tone  = class_exists( 'Damavand_Persian_SEO_Score' )
			? Damavand_Persian_SEO_Score::score_tone( $score )
			: ( $score >= 81 ? 'good' : ( $score >= 51 ? 'ok' : 'bad' ) );

		$next = '';
		foreach ( $checks as $c ) {
			if ( empty( $c['ok'] ) && ! empty( $c['tip'] ) ) {
				$next = (string) $c['label'] . ': ' . (string) $c['tip'];
				break;
			}
		}

		return array(
			'score'    => $score,
			'tone'     => $tone,
			'checks'   => $checks,
			'next_tip' => $next,
			'words'    => $words,
		);
	}

	/**
	 * @param string $id     ID.
	 * @param string $label  Label.
	 * @param string $status pass|warning|fail.
	 * @param string $message Message.
	 */
	public static function item( string $id, string $label, string $status, string $message ): array {
		return array(
			'id'      => $id,
			'label'   => $label,
			'status'  => $status,
			'message' => $message,
		);
	}

	/**
	 * @param array<string,mixed> $ctx Context.
	 */
	public static function check_keyword_in_intro( array $ctx ): array {
		$focus = trim( (string) ( $ctx['focus'] ?? '' ) );
		$plain = (string) ( $ctx['body_plain'] ?? '' );
		if ( '' === $focus ) {
			return self::item( 'kw_intro', __( 'کلمه کلیدی در ابتدای متن', 'shojaei-seo-for-woo' ), 'warning', __( 'کلمه کلیدی تنظیم نشده.', 'shojaei-seo-for-woo' ) );
		}
		$intro = mb_substr( $plain, 0, (int) max( 120, mb_strlen( $plain, 'UTF-8' ) * 0.1 ), 'UTF-8' );
		if ( Damavand_Persian_Text::contains_keyword( $intro, $focus ) ) {
			return self::item( 'kw_intro', __( 'کلمه کلیدی در ابتدای متن', 'shojaei-seo-for-woo' ), 'pass', __( 'در ~۱۰٪ اول محتوا آمده.', 'shojaei-seo-for-woo' ) );
		}
		return self::item( 'kw_intro', __( 'کلمه کلیدی در ابتدای متن', 'shojaei-seo-for-woo' ), 'fail', __( 'کلمه کلیدی را در پاراگراف اول بیاورید.', 'shojaei-seo-for-woo' ) );
	}

	/**
	 * @param array<string,mixed> $ctx Context.
	 */
	public static function check_keyword_in_subheadings( array $ctx ): array {
		$focus = trim( (string) ( $ctx['focus'] ?? '' ) );
		$html  = (string) ( $ctx['body_html'] ?? '' );
		if ( '' === $focus ) {
			return self::item( 'kw_h2', __( 'کلمه کلیدی در H2/H3', 'shojaei-seo-for-woo' ), 'warning', __( 'کلمه کلیدی تنظیم نشده.', 'shojaei-seo-for-woo' ) );
		}
		if ( ! preg_match_all( '/<h[23][^>]*>(.*?)<\/h[23]>/isu', $html, $m ) || empty( $m[1] ) ) {
			return self::item( 'kw_h2', __( 'کلمه کلیدی در H2/H3', 'shojaei-seo-for-woo' ), 'warning', __( 'زیرعنوان H2/H3 یافت نشد.', 'shojaei-seo-for-woo' ) );
		}
		foreach ( $m[1] as $heading ) {
			if ( Damavand_Persian_Text::contains_keyword( wp_strip_all_tags( (string) $heading ), $focus ) ) {
				return self::item( 'kw_h2', __( 'کلمه کلیدی در H2/H3', 'shojaei-seo-for-woo' ), 'pass', __( 'در حداقل یک زیرعنوان آمده.', 'shojaei-seo-for-woo' ) );
			}
		}
		return self::item( 'kw_h2', __( 'کلمه کلیدی در H2/H3', 'shojaei-seo-for-woo' ), 'fail', __( 'کلمه کلیدی را در یک H2/H3 بگنجانید.', 'shojaei-seo-for-woo' ) );
	}

	/**
	 * @param array<string,mixed> $ctx Context.
	 */
	public static function check_external_links( array $ctx ): array {
		$html = (string) ( $ctx['body_html'] ?? '' );
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		if ( ! preg_match_all( '/<a\s[^>]{0,400}href=["\']([^"\']+)["\']/iu', $html, $m ) || empty( $m[1] ) ) {
			return self::item( 'ext_link', __( 'لینک خارجی', 'shojaei-seo-for-woo' ), 'warning', __( 'لینک خارجی اختیاری است؛ برای مقالات مرجع معتبر مفید است.', 'shojaei-seo-for-woo' ) );
		}
		foreach ( $m[1] as $href ) {
			$href = (string) $href;
			if ( preg_match( '#^https?://#i', $href ) && $home && false === stripos( $href, (string) $home ) ) {
				return self::item( 'ext_link', __( 'لینک خارجی', 'shojaei-seo-for-woo' ), 'pass', __( 'حداقل یک لینک خارجی دیده شد.', 'shojaei-seo-for-woo' ) );
			}
		}
		return self::item( 'ext_link', __( 'لینک خارجی', 'shojaei-seo-for-woo' ), 'warning', __( 'لینک خارجی اختیاری — برای نوشته‌ها پیشنهاد می‌شود.', 'shojaei-seo-for-woo' ) );
	}

	/**
	 * @param array<string,mixed> $ctx Context.
	 */
	public static function check_transition_words( array $ctx ): array {
		$plain = (string) ( $ctx['body_plain'] ?? '' );
		$words = array( 'بنابراین', 'در نتیجه', 'همچنین', 'از طرف دیگر', 'به علاوه', 'در عین حال', 'به طور خلاصه', 'اول', 'دوم', 'سوم', 'در نهایت', 'مثلا', 'برای مثال' );
		$hits  = 0;
		foreach ( $words as $w ) {
			if ( false !== mb_strpos( Damavand_Persian_Text::normalize( $plain ), Damavand_Persian_Text::normalize( $w ), 0, 'UTF-8' ) ) {
				++$hits;
			}
		}
		if ( $hits >= 2 ) {
			return self::item( 'transition', __( 'کلمات انتقالی', 'shojaei-seo-for-woo' ), 'pass', __( 'چند کلمه انتقالی فارسی در متن دیده شد.', 'shojaei-seo-for-woo' ) );
		}
		if ( $hits === 1 ) {
			return self::item( 'transition', __( 'کلمات انتقالی', 'shojaei-seo-for-woo' ), 'warning', __( 'یک کلمه انتقالی کافی است؛ چند مورد دیگر خوانایی را بهتر می‌کند.', 'shojaei-seo-for-woo' ) );
		}
		return self::item( 'transition', __( 'کلمات انتقالی', 'shojaei-seo-for-woo' ), 'warning', __( 'از «همچنین، بنابراین، در نتیجه» برای روان‌تر شدن متن استفاده کنید.', 'shojaei-seo-for-woo' ) );
	}

	/**
	 * @param array<string,mixed> $ctx Context.
	 */
	public static function check_passive_voice( array $ctx ): array {
		$plain = (string) ( $ctx['body_plain'] ?? '' );
		if ( ! preg_match_all( '/[^\n.!?؟۔]{10,120}?(?:شد|می‌شود|گردید|می‌گردد|بود|می‌باشد)[.!?؟۔\n]/u', $plain, $m ) ) {
			return self::item( 'passive', __( 'جملات مجهول', 'shojaei-seo-for-woo' ), 'pass', __( 'الگوی مجهول پررنگ دیده نشد.', 'shojaei-seo-for-woo' ) );
		}
		$count = count( $m[0] );
		if ( $count >= 6 ) {
			return self::item( 'passive', __( 'جملات مجهول', 'shojaei-seo-for-woo' ), 'warning', __( 'چند جمله با «شد/می‌شود» دیده شد — در صورت امکان معلوم بنویسید (تشخیص ۱۰۰٪ نیست).', 'shojaei-seo-for-woo' ) );
		}
		return self::item( 'passive', __( 'جملات مجهول', 'shojaei-seo-for-woo' ), 'pass', __( 'ترکیب جملات مجهول/معلوم متعادل به نظر می‌رسد.', 'shojaei-seo-for-woo' ) );
	}

	/**
	 * @param array<string,mixed> $ctx Context.
	 */
	public static function check_paragraph_length( array $ctx ): array {
		$html = (string) ( $ctx['body_html'] ?? '' );
		if ( ! preg_match_all( '/<p[^>]*>(.*?)<\/p>/isu', $html, $m ) || empty( $m[1] ) ) {
			$chunks = preg_split( '/\n{2,}/u', (string) ( $ctx['body_plain'] ?? '' ) );
			$long   = 0;
			if ( is_array( $chunks ) ) {
				foreach ( $chunks as $ch ) {
					if ( Damavand_Persian_Text::word_count( (string) $ch ) > 150 ) {
						++$long;
					}
				}
			}
		} else {
			$long = 0;
			foreach ( $m[1] as $p ) {
				if ( Damavand_Persian_Text::word_count( wp_strip_all_tags( (string) $p ) ) > 150 ) {
					++$long;
				}
			}
		}
		if ( $long > 0 ) {
			return self::item( 'para_len', __( 'طول پاراگراف', 'shojaei-seo-for-woo' ), 'warning', sprintf(
				/* translators: %d: count */
				__( '%d پاراگراف بیش از ~۱۵۰ کلمه دارد — بشکنید.', 'shojaei-seo-for-woo' ),
				$long
			) );
		}
		return self::item( 'para_len', __( 'طول پاراگراف', 'shojaei-seo-for-woo' ), 'pass', __( 'پاراگراف‌ها کوتاه/متعادل هستند.', 'shojaei-seo-for-woo' ) );
	}

	/**
	 * @param array<string,mixed> $ctx Context.
	 */
	public static function check_subheading_distribution( array $ctx ): array {
		$html  = (string) ( $ctx['body_html'] ?? '' );
		$words = Damavand_Persian_Text::word_count( (string) ( $ctx['body_plain'] ?? '' ) );
		if ( $words < 200 ) {
			return self::item( 'sub_dist', __( 'توزیع زیرعنوان', 'shojaei-seo-for-woo' ), 'pass', __( 'متن کوتاه است — زیرعنوان اجباری نیست.', 'shojaei-seo-for-woo' ) );
		}
		$hcount = preg_match_all( '/<h[23][\s>]/i', $html );
		$need   = max( 1, (int) floor( $words / 300 ) );
		if ( $hcount >= $need ) {
			return self::item( 'sub_dist', __( 'توزیع زیرعنوان', 'shojaei-seo-for-woo' ), 'pass', __( 'زیرعنوان‌ها با طول متن هم‌خوان است.', 'shojaei-seo-for-woo' ) );
		}
		return self::item( 'sub_dist', __( 'توزیع زیرعنوان', 'shojaei-seo-for-woo' ), 'warning', sprintf(
			/* translators: 1: have 2: need */
			__( '%1$d زیرعنوان برای ~%2$d کلمه کم است.', 'shojaei-seo-for-woo' ),
			(int) $hcount,
			$words
		) );
	}

	/**
	 * @param array<string,mixed> $ctx Context.
	 */
	public static function check_related_keywords( array $ctx ): array {
		$related = trim( (string) ( $ctx['related'] ?? '' ) );
		$plain   = (string) ( $ctx['body_plain'] ?? '' ) . ' ' . (string) ( $ctx['title'] ?? '' );
		if ( '' === $related ) {
			return self::item( 'related_kw', __( 'کلمات مرتبط', 'shojaei-seo-for-woo' ), 'warning', __( 'فیلد «کلمات مرتبط» خالی است (اختیاری — فقط در مدیریت محصول).', 'shojaei-seo-for-woo' ) );
		}
		$parts = self::parse_related_list( $related );
		$found = 0;
		$total = count( $parts );
		foreach ( $parts as $p ) {
			if ( Damavand_Persian_Text::contains_keyword( $plain, $p ) ) {
				++$found;
			}
		}
		if ( $total > 0 && $found >= max( 1, (int) ceil( $total * 0.5 ) ) ) {
			return self::item( 'related_kw', __( 'کلمات مرتبط', 'shojaei-seo-for-woo' ), 'pass', sprintf(
				/* translators: %1$d %2$d %3$s */
				__( '%1$d از %2$d کلمه مرتبط در متن دیده شد (%3$s).', 'shojaei-seo-for-woo' ),
				$found,
				$total,
				$related
			) );
		}
		return self::item( 'related_kw', __( 'کلمات مرتبط', 'shojaei-seo-for-woo' ), 'fail', sprintf(
			/* translators: %s: related phrase list */
			__( 'عبارات «%s» را در توضیح/متن بیاورید.', 'shojaei-seo-for-woo' ),
			$related
		) );
	}

	/**
	 * @param array<string,mixed> $ctx Context.
	 */
	public static function check_number_in_title( array $ctx ): array {
		$title = (string) ( $ctx['title'] ?? '' );
		if ( preg_match( '/[0-9۰-۹]/u', Damavand_Persian_Text::normalize_digits( $title ) ) ) {
			return self::item( 'num_title', __( 'عدد در عنوان', 'shojaei-seo-for-woo' ), 'pass', __( 'عنوان شامل رقم است — معمولاً CTR بهتر می‌شود.', 'shojaei-seo-for-woo' ) );
		}
		return self::item( 'num_title', __( 'عدد در عنوان', 'shojaei-seo-for-woo' ), 'warning', __( 'افزودن عدد (مثل «۵ مدل») گاهی کلیک را بیشتر می‌کند.', 'shojaei-seo-for-woo' ) );
	}

	/**
	 * @param array<string,mixed> $ctx Context.
	 */
	public static function check_power_words( array $ctx ): array {
		$title = Damavand_Persian_Text::normalize( (string) ( $ctx['title'] ?? '' ) );
		$list  = array( 'کامل', 'رایگان', 'تضمینی', 'اختصاصی', 'نهایی', 'حرفه‌ای', 'اصل', 'ویژه', 'پرفروش', 'جدید', 'ارزان', 'لوکس' );
		foreach ( $list as $w ) {
			if ( false !== mb_strpos( $title, Damavand_Persian_Text::normalize( $w ), 0, 'UTF-8' ) ) {
				return self::item( 'power_word', __( 'کلمه قدرت در عنوان', 'shojaei-seo-for-woo' ), 'pass', __( 'کلمه قدرت در عنوان دیده شد.', 'shojaei-seo-for-woo' ) );
			}
		}
		return self::item( 'power_word', __( 'کلمه قدرت در عنوان', 'shojaei-seo-for-woo' ), 'warning', __( 'کلماتی مثل «اصل، ویژه، حرفه‌ای» CTR را بهتر می‌کند.', 'shojaei-seo-for-woo' ) );
	}

	/**
	 * @param array<string,mixed> $ctx Context.
	 */
	public static function check_sentiment_words( array $ctx ): array {
		$title = Damavand_Persian_Text::normalize( (string) ( $ctx['title'] ?? '' ) );
		$list  = array( 'عالی', 'فوق‌العاده', 'محبوب', 'زیبا', 'شیک', 'لوکس', 'باکیفیت', 'ارزان', 'تخفیف', 'پیشنهاد' );
		foreach ( $list as $w ) {
			if ( false !== mb_strpos( $title, Damavand_Persian_Text::normalize( $w ), 0, 'UTF-8' ) ) {
				return self::item( 'sentiment', __( 'کلمه احساسی در عنوان', 'shojaei-seo-for-woo' ), 'pass', __( 'کلمه احساسی در عنوان دیده شد.', 'shojaei-seo-for-woo' ) );
			}
		}
		return self::item( 'sentiment', __( 'کلمه احساسی در عنوان', 'shojaei-seo-for-woo' ), 'warning', __( 'صفات احساسی مثل «شیک، باکیفیت» عنوان را جذاب‌تر می‌کند.', 'shojaei-seo-for-woo' ) );
	}

	/**
	 * @param array<string,mixed> $ctx Context.
	 */
	public static function check_product_price( array $ctx ): array {
		/** @var WC_Product|null $product */
		$product = $ctx['product'] ?? null;
		if ( ! $product ) {
			return self::item( 'price', __( 'قیمت محصول', 'shojaei-seo-for-woo' ), 'warning', __( 'محصول ووکامرس در دسترس نیست.', 'shojaei-seo-for-woo' ) );
		}
		$price = $product->get_price();
		if ( '' !== $price && (float) $price > 0 ) {
			return self::item( 'price', __( 'قیمت محصول', 'shojaei-seo-for-woo' ), 'pass', __( 'قیمت معتبر تنظیم شده.', 'shojaei-seo-for-woo' ) );
		}
		return self::item( 'price', __( 'قیمت محصول', 'shojaei-seo-for-woo' ), 'fail', __( 'قیمت خالی یا صفر است.', 'shojaei-seo-for-woo' ) );
	}

	/**
	 * @param array<string,mixed> $ctx Context.
	 */
	public static function check_product_stock( array $ctx ): array {
		/** @var WC_Product|null $product */
		$product = $ctx['product'] ?? null;
		if ( ! $product ) {
			return self::item( 'stock', __( 'موجودی', 'shojaei-seo-for-woo' ), 'warning', __( '—', 'shojaei-seo-for-woo' ) );
		}
		if ( $product->managing_stock() || $product->get_stock_status() ) {
			return self::item( 'stock', __( 'موجودی', 'shojaei-seo-for-woo' ), 'pass', __( 'وضعیت موجودی مشخص است.', 'shojaei-seo-for-woo' ) );
		}
		return self::item( 'stock', __( 'موجودی', 'shojaei-seo-for-woo' ), 'warning', __( 'وضعیت موجودی را در ووکامرس تنظیم کنید.', 'shojaei-seo-for-woo' ) );
	}

	/**
	 * @param array<string,mixed> $ctx Context.
	 */
	public static function check_product_sku( array $ctx ): array {
		/** @var WC_Product|null $product */
		$product = $ctx['product'] ?? null;
		if ( ! $product ) {
			return self::item( 'sku', __( 'SKU', 'shojaei-seo-for-woo' ), 'warning', __( '—', 'shojaei-seo-for-woo' ) );
		}
		if ( '' !== trim( (string) $product->get_sku() ) ) {
			return self::item( 'sku', __( 'SKU', 'shojaei-seo-for-woo' ), 'pass', __( 'SKU ثبت شده.', 'shojaei-seo-for-woo' ) );
		}
		return self::item( 'sku', __( 'SKU', 'shojaei-seo-for-woo' ), 'warning', __( 'SKU خالی است — برای فروشگاه بزرگ پیشنهاد می‌شود.', 'shojaei-seo-for-woo' ) );
	}

	/**
	 * @param array<string,mixed> $ctx Context.
	 */
	public static function check_product_short_description( array $ctx ): array {
		$short = wp_strip_all_tags( (string) ( $ctx['short_html'] ?? '' ) );
		$words = Damavand_Persian_Text::word_count( $short );
		if ( $words >= 20 ) {
			return self::item( 'short_desc', __( 'توضیح کوتاه', 'shojaei-seo-for-woo' ), 'pass', __( 'توضیح کوتاه پر شده.', 'shojaei-seo-for-woo' ) );
		}
		return self::item( 'short_desc', __( 'توضیح کوتاه', 'shojaei-seo-for-woo' ), 'fail', __( 'توضیح کوتاه محصول خالی یا خیلی کوتاه است.', 'shojaei-seo-for-woo' ) );
	}

	/**
	 * @param array<string,mixed> $ctx Context.
	 */
	public static function check_product_category( array $ctx ): array {
		/** @var WC_Product|null $product */
		$product = $ctx['product'] ?? null;
		if ( ! $product ) {
			return self::item( 'product_cat', __( 'دسته محصول', 'shojaei-seo-for-woo' ), 'warning', __( '—', 'shojaei-seo-for-woo' ) );
		}
		$cats = $product->get_category_ids();
		if ( ! empty( $cats ) ) {
			return self::item( 'product_cat', __( 'دسته محصول', 'shojaei-seo-for-woo' ), 'pass', __( 'دسته انتخاب شده.', 'shojaei-seo-for-woo' ) );
		}
		return self::item( 'product_cat', __( 'دسته محصول', 'shojaei-seo-for-woo' ), 'fail', __( 'دسته محصول خالی است.', 'shojaei-seo-for-woo' ) );
	}

	/**
	 * @param array<string,mixed> $ctx Context.
	 */
	public static function check_product_reviews( array $ctx ): array {
		$post_id = (int) ( $ctx['post_id'] ?? 0 );
		if ( $post_id < 1 || ! function_exists( 'wc_review_ratings_enabled' ) || ! wc_review_ratings_enabled() ) {
			return self::item( 'reviews', __( 'نقد و امتیاز', 'shojaei-seo-for-woo' ), 'pass', __( 'ریویو غیرفعال یا نیاز نیست.', 'shojaei-seo-for-woo' ) );
		}
		$count = (int) get_comments(
			array(
				'post_id' => $post_id,
				'status'  => 'approve',
				'type'    => 'review',
				'count'   => true,
			)
		);
		if ( $count > 0 ) {
			return self::item( 'reviews', __( 'نقد و امتیاز', 'shojaei-seo-for-woo' ), 'pass', sprintf(
				/* translators: %d: count */
				__( '%d نظر ثبت شده — AggregateRating ممکن است.', 'shojaei-seo-for-woo' ),
				$count
			) );
		}
		return self::item( 'reviews', __( 'نقد و امتیاز', 'shojaei-seo-for-woo' ), 'warning', __( 'هنوز نظری ثبت نشده — برای Rich Results مفید است.', 'shojaei-seo-for-woo' ) );
	}

	/**
	 * @param array<string,mixed> $ctx Context.
	 */
	public static function check_product_image_size( array $ctx ): array {
		$thumb = (int) ( $ctx['thumbnail_id'] ?? 0 );
		if ( $thumb < 1 ) {
			return self::item( 'img_size', __( 'ابعاد تصویر', 'shojaei-seo-for-woo' ), 'fail', __( 'تصویر شاخص نیست.', 'shojaei-seo-for-woo' ) );
		}
		$meta = wp_get_attachment_metadata( $thumb );
		$w    = (int) ( $meta['width'] ?? 0 );
		$h    = (int) ( $meta['height'] ?? 0 );
		if ( $w >= 800 && $h >= 800 ) {
			return self::item( 'img_size', __( 'ابعاد تصویر', 'shojaei-seo-for-woo' ), 'pass', sprintf(
				/* translators: 1: w 2: h */
				__( 'تصویر %1$d×%2$d — مناسب گوگل.', 'shojaei-seo-for-woo' ),
				$w,
				$h
			) );
		}
		return self::item( 'img_size', __( 'ابعاد تصویر', 'shojaei-seo-for-woo' ), 'warning', sprintf(
			/* translators: 1: w 2: h */
			__( 'تصویر %1$d×%2$d — هدف حداقل ۸۰۰×۸۰۰.', 'shojaei-seo-for-woo' ),
			$w,
			$h
		) );
	}

	/**
	 * @param int                  $post_id Post.
	 * @param WC_Product|null      $product Product.
	 * @param array<string,mixed>  $override Override.
	 * @return int[]
	 */
	private static function gallery_ids( int $post_id, $product, array $override ): array {
		if ( array_key_exists( 'gallery_ids', $override ) && is_array( $override['gallery_ids'] ) ) {
			return array_values( array_filter( array_map( 'absint', $override['gallery_ids'] ) ) );
		}
		if ( $product && is_a( $product, 'WC_Product' ) ) {
			return array_map( 'intval', (array) $product->get_gallery_image_ids() );
		}
		return array();
	}

	/**
	 * @param string $id    ID.
	 * @param string $label Label.
	 * @param int    $pts   Points.
	 * @param int    $max   Max.
	 * @param bool   $ok    OK.
	 * @param string $tip   Tip.
	 */
	private static function pack_group( string $id, string $label, int $pts, int $max, bool $ok, string $tip = '' ): array {
		return array(
			'id'     => $id,
			'label'  => $label,
			'ok'     => $ok,
			'points' => $pts,
			'max'    => $max,
			'tip'    => $tip,
		);
	}

	/**
	 * Parse comma-separated related phrases (Persian/English comma), dedupe, trim, cap count.
	 *
	 * @param string $raw Raw input.
	 * @param int    $max Max phrases.
	 * @return string[]
	 */
	public static function parse_related_list( string $raw, int $max = self::MAX_RELATED ): array {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			return array();
		}
		$parts = preg_split( '/[,،؛|]+/u', $raw );
		if ( ! is_array( $parts ) ) {
			return array();
		}
		$seen = array();
		$out  = array();
		foreach ( $parts as $p ) {
			$p = preg_replace( '/\s+/u', ' ', trim( (string) $p ) );
			if ( '' === $p ) {
				continue;
			}
			$key = mb_strtolower( $p, 'UTF-8' );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = $p;
			if ( count( $out ) >= $max ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Normalize stored related input for save/display.
	 */
	public static function normalize_related_input( string $raw ): string {
		return implode( '، ', self::parse_related_list( $raw ) );
	}

	/**
	 * Commerce / filler words to strip from labels and low-value matching.
	 *
	 * @return string[]
	 */
	public static function commerce_stopwords(): array {
		return array(
			'خرید',
			'فروش',
			'قیمت',
			'سفارش',
			'ارزان',
			'تخفیف',
			'ارسال',
			'رایگان',
			'buy',
			'shop',
			'sale',
			'price',
			'order',
			'online',
			'store',
		);
	}

	/**
	 * Generic product-type tokens that match too many items in a catalog.
	 *
	 * @return string[]
	 */
	public static function generic_product_tokens(): array {
		return array(
			'کتونی',
			'کفش',
			'کفشک',
			'sneaker',
			'sneakers',
			'shoe',
			'shoes',
			'محصول',
			'کالا',
			'مدل',
			'اصل',
			'اورجینال',
			'original',
		);
	}

	/**
	 * Token too common for link/FAQ matching.
	 */
	public static function is_low_value_token( string $token ): bool {
		$t = mb_strtolower( trim( $token ), 'UTF-8' );
		if ( mb_strlen( $t, 'UTF-8' ) < 2 ) {
			return true;
		}
		static $stop = null;
		if ( null === $stop ) {
			$stop = array();
			foreach ( array_merge(
				self::commerce_stopwords(),
				self::generic_product_tokens(),
				array( 'از', 'با', 'و', 'در', 'برای', 'به', 'که', 'این', 'آن', 'را', 'یک', 'یه', 'the', 'a', 'an', 'and', 'or', 'of', 'for', 'with' )
			) as $w ) {
				$stop[ mb_strtolower( $w, 'UTF-8' ) ] = true;
			}
		}
		return isset( $stop[ $t ] );
	}

	/**
	 * Distinctive tokens from text (brand/model/color etc.).
	 *
	 * @return string[]
	 */
	public static function distinctive_tokens_from_text( string $text, int $limit = 12 ): array {
		$text  = mb_strtolower( wp_strip_all_tags( $text ), 'UTF-8' );
		$parts = preg_split( '/[\s\x{200C}\x{200B}\-\_\,\.\;\:\!\?\(\)\[\]「»«\"\'\/\\\\]+/u', $text );
		if ( ! is_array( $parts ) ) {
			return array();
		}
		$out = array();
		foreach ( $parts as $p ) {
			$p = trim( (string) $p );
			if ( self::is_low_value_token( $p ) ) {
				continue;
			}
			$out[] = $p;
			if ( count( $out ) >= $limit ) {
				break;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * Related phrases for a post (admin-only meta).
	 *
	 * @param array<string,mixed> $context Optional live context.
	 * @return string[]
	 */
	public static function related_keywords_for_post( int $post_id, array $context = array() ): array {
		$raw = trim( (string) ( $context['related'] ?? '' ) );
		if ( '' === $raw ) {
			$raw = (string) get_post_meta( $post_id, self::META_RELATED, true );
		}
		return self::parse_related_list( $raw );
	}

	/**
	 * Human label for FAQ/templates — strips «خرید» and generic «کتونی» when possible.
	 *
	 * @param array<string,mixed> $context Optional title/focus/short.
	 */
	public static function product_label( int $post_id, array $context = array() ): string {
		$short = trim( (string) ( $context['short'] ?? '' ) );
		if ( '' !== $short ) {
			$tokens = self::distinctive_tokens_from_text( $short, 6 );
			if ( ! empty( $tokens ) ) {
				return implode( ' ', array_slice( $tokens, 0, 4 ) );
			}
		}

		$focus = trim( (string) ( $context['focus'] ?? '' ) );
		if ( '' === $focus && class_exists( 'Damavand_SEO_Meta' ) ) {
			$focus = trim( (string) Damavand_SEO_Meta::get_focus_keyword( $post_id ) );
		}
		if ( '' !== $focus ) {
			$tokens = self::distinctive_tokens_from_text( $focus, 5 );
			if ( ! empty( $tokens ) ) {
				return implode( ' ', array_slice( $tokens, 0, 4 ) );
			}
			return wp_trim_words( $focus, 5, '' );
		}

		$related = self::related_keywords_for_post( $post_id, $context );
		if ( ! empty( $related ) ) {
			return implode( ' ', array_slice( $related, 0, 2 ) );
		}

		$title  = trim( (string) ( $context['title'] ?? get_the_title( $post_id ) ) );
		$tokens = self::distinctive_tokens_from_text( $title, 6 );
		if ( ! empty( $tokens ) ) {
			return implode( ' ', array_slice( $tokens, 0, 4 ) );
		}

		if ( function_exists( 'wc_get_product' ) && 'product' === get_post_type( $post_id ) ) {
			$p = wc_get_product( $post_id );
			if ( $p ) {
				$sku = trim( (string) $p->get_sku() );
				if ( '' !== $sku && mb_strlen( $sku, 'UTF-8' ) <= 24 ) {
					return $sku;
				}
			}
		}

		return wp_trim_words( $title, 4, '' );
	}

	/**
	 * Plain description blob for similarity (excerpt, short desc, meta desc).
	 */
	public static function description_blob( int $post_id ): string {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}
		$parts = array( (string) $post->post_excerpt, (string) $post->post_content );
		if ( function_exists( 'wc_get_product' ) && 'product' === $post->post_type ) {
			$p = wc_get_product( $post_id );
			if ( $p ) {
				array_unshift( $parts, (string) $p->get_short_description() );
			}
		}
		if ( class_exists( 'Damavand_SEO_Meta' ) ) {
			$meta_desc = trim( (string) get_post_meta( $post_id, Damavand_SEO_Meta::DESC, true ) );
			if ( '' !== $meta_desc ) {
				$parts[] = $meta_desc;
			}
		}
		foreach ( self::related_keywords_for_post( $post_id ) as $rel ) {
			$parts[] = $rel;
		}
		return mb_strtolower( wp_strip_all_tags( implode( ' ', array_filter( $parts ) ) ), 'UTF-8' );
	}

	/**
	 * Jaccard similarity on distinctive description tokens (0..1).
	 */
	public static function description_similarity( int $a, int $b ): float {
		$ta = self::distinctive_tokens_from_text( self::description_blob( $a ), 24 );
		$tb = self::distinctive_tokens_from_text( self::description_blob( $b ), 24 );
		if ( empty( $ta ) || empty( $tb ) ) {
			return 0.0;
		}
		$set   = array_flip( $ta );
		$inter = 0;
		foreach ( $tb as $t ) {
			if ( isset( $set[ $t ] ) ) {
				++$inter;
			}
		}
		$union = count( array_unique( array_merge( $ta, $tb ) ) );
		return $union > 0 ? $inter / $union : 0.0;
	}

	/**
	 * Keywords for orphan/link matching: focus, related, distinctive title tokens.
	 *
	 * @return string[]
	 */
	public static function link_keywords_for_post( int $post_id ): array {
		$out = array();
		if ( class_exists( 'Damavand_SEO_Meta' ) ) {
			$focus = trim( (string) Damavand_SEO_Meta::get_focus_keyword( $post_id ) );
			if ( '' !== $focus ) {
				$out[] = $focus;
			}
		}
		foreach ( self::related_keywords_for_post( $post_id ) as $rel ) {
			$out[] = $rel;
		}
		$title  = (string) get_the_title( $post_id );
		$tokens = self::distinctive_tokens_from_text( $title, 8 );
		if ( count( $tokens ) >= 2 ) {
			$out[] = implode( ' ', array_slice( $tokens, 0, 3 ) );
		}
		foreach ( $tokens as $t ) {
			$out[] = $t;
		}
		if ( class_exists( 'Shojaei_SEO_Link_Genius' ) ) {
			foreach ( Shojaei_SEO_Link_Genius::keywords_from_title( $title ) as $kw ) {
				$out[] = $kw;
			}
		}
		$seen  = array();
		$final = array();
		foreach ( $out as $k ) {
			$k = trim( (string) $k );
			if ( mb_strlen( $k, 'UTF-8' ) < 2 ) {
				continue;
			}
			$key = mb_strtolower( $k, 'UTF-8' );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$final[]      = $k;
		}
		return $final;
	}
}
