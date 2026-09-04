<?php
/**
 * FAQ box — پیشنهاد + درج برای FAQPage schema (گوگل).
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Damavand_FAQ_Box
 */
class Damavand_FAQ_Box {

	public const META = '_shojaei_seo_faq';

	public const BLOCK_CLASS = 'damavand-faq';

	public const MAX_ITEMS = 4;

	public const OPTION_RETURNS_URL  = 'shojaei_seo_faq_returns_url';
	public const OPTION_RETURNS_PAGE = 'shojaei_seo_faq_returns_page_id';

	public const DETECT_TRANSIENT = 'damavand_faq_returns_detect';

	/**
	 * URL for returns / exchange policy page.
	 */
	public static function get_returns_url(): string {
		$manual = trim( (string) Shojaei_SEO_Helpers::get_option( self::OPTION_RETURNS_URL, '' ) );
		if ( '' !== $manual ) {
			return esc_url( $manual );
		}

		$page_id = absint( Shojaei_SEO_Helpers::get_option( self::OPTION_RETURNS_PAGE, 0 ) );
		if ( $page_id < 1 ) {
			$page_id = self::detect_returns_page_id();
		}
		if ( $page_id > 0 ) {
			$link = get_permalink( $page_id );
			if ( is_string( $link ) && '' !== $link ) {
				return esc_url( $link );
			}
		}

		return '';
	}

	/**
	 * Auto-detect a published page/post about returns policy.
	 */
	public static function detect_returns_page_id(): int {
		$cached = get_transient( self::DETECT_TRANSIENT );
		if ( false !== $cached ) {
			return max( 0, (int) $cached );
		}

		$slugs = array(
			'return-policy',
			'returns',
			'refund-policy',
			'refunds',
			'exchange-policy',
			'marjoee',
			'taaviz',
			'sharayet-marjoee',
			'sharayet-taaviz',
			'شرایط-مرجوعی',
			'شرایط-تعویض',
			'مرجوعی',
			'تعویض-و-مرجوعی',
		);
		foreach ( $slugs as $slug ) {
			$page = get_page_by_path( $slug, OBJECT, array( 'page', 'post' ) );
			if ( $page instanceof WP_Post && 'publish' === $page->post_status ) {
				set_transient( self::DETECT_TRANSIENT, (int) $page->ID, DAY_IN_SECONDS );
				return (int) $page->ID;
			}
		}

		$terms = array( 'مرجوع', 'تعویض', 'بازگشت کالا', 'return policy', 'refund' );
		foreach ( $terms as $term ) {
			$q = new WP_Query(
				array(
					'post_type'              => array( 'page', 'post' ),
					'post_status'            => 'publish',
					's'                      => $term,
					'posts_per_page'         => 5,
					'orderby'                => 'menu_order title',
					'order'                  => 'ASC',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'fields'                 => 'ids',
				)
			);
			foreach ( array_map( 'absint', (array) $q->posts ) as $pid ) {
				if ( $pid < 1 ) {
					continue;
				}
				$title = mb_strtolower( get_the_title( $pid ), 'UTF-8' );
				if ( preg_match( '/مرجوع|تعویض|return|refund|exchange/u', $title ) ) {
					set_transient( self::DETECT_TRANSIENT, $pid, DAY_IN_SECONDS );
					return $pid;
				}
			}
		}

		set_transient( self::DETECT_TRANSIENT, 0, HOUR_IN_SECONDS );
		return 0;
	}

	/**
	 * Clear cached auto-detect after settings change.
	 */
	public static function flush_returns_detect_cache(): void {
		delete_transient( self::DETECT_TRANSIENT );
	}

	/**
	 * @param string $question FAQ question.
	 */
	private static function is_returns_question( string $question ): bool {
		return (bool) preg_match( '/مرجوع|تعویض|return|refund|exchange/u', $question );
	}

	/**
	 * Boot AJAX.
	 */
	public static function register_hooks(): void {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'wp_ajax_damavand_faq_suggest', array( __CLASS__, 'ajax_suggest' ) );
		add_action( 'wp_ajax_damavand_faq_inject', array( __CLASS__, 'ajax_inject' ) );
	}

	/**
	 * Stored FAQ rows for schema.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int,array{question:string,answer:string}>
	 */
	public static function get_stored( int $post_id ): array {
		$raw = get_post_meta( $post_id, self::META, true );
		if ( ! is_array( $raw ) ) {
			return array();
		}
		$out = array();
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$q = trim( (string) ( $row['question'] ?? $row['q'] ?? '' ) );
			$a = trim( (string) ( $row['answer'] ?? $row['a'] ?? '' ) );
			if ( '' === $q || '' === $a ) {
				continue;
			}
			$out[] = array(
				'question' => $q,
				'answer'   => $a,
			);
		}
		return $out;
	}

	/**
	 * Suggest FAQ items for a product/post.
	 *
	 * @param int   $post_id Post.
	 * @param array $context Live context.
	 * @return array{suggestions:array,has_faq:bool,count:int}
	 */
	public static function suggest( int $post_id, array $context = array() ): array {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array(
				'suggestions' => array(),
				'has_faq'     => false,
				'count'       => 0,
			);
		}

		$stored   = self::get_stored( $post_id );
		$html     = self::resolve_html( $post_id, $context );
		$has_faq  = ! empty( $stored ) || self::content_has_faq_block( $html );
		$title    = trim( (string) ( $context['title'] ?? $post->post_title ) );
		$focus    = trim( (string) ( $context['focus'] ?? '' ) );
		if ( '' === $focus && class_exists( 'Damavand_SEO_Meta' ) ) {
			$focus = trim( (string) Damavand_SEO_Meta::get_focus_keyword( $post_id ) );
		}

		$short    = wp_trim_words( $title, 6, '' );
		$kind     = self::detect_kind( $post_id, $title );
		$in_stock = self::is_in_stock( $post_id );

		$items = self::build_templates( $short, $focus, $kind, $in_stock, $post_id, $context );
		foreach ( $items as $i => $row ) {
			$items[ $i ]['checked'] = $i < 3;
		}

		return array(
			'suggestions' => array_slice( $items, 0, self::MAX_ITEMS ),
			'has_faq'     => $has_faq,
			'count'       => count( $stored ),
			'returns_url' => self::get_returns_url(),
		);
	}

	/**
	 * Inject FAQ HTML + save schema meta.
	 *
	 * @param int                       $post_id Post.
	 * @param array<int,array<string>>  $items   Selected Q&A.
	 * @param string                    $content Content override.
	 * @param string                    $excerpt Excerpt override.
	 * @return array{ok:bool,message?:string,field?:string,content?:string,excerpt?:string,count?:int}
	 */
	public static function inject( int $post_id, array $items, string $content = '', string $excerpt = '' ): array {
		$post = get_post( $post_id );
		if ( ! $post || ! current_user_can( 'edit_post', $post_id ) ) {
			return array( 'ok' => false, 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) );
		}

		$clean = array();
		foreach ( $items as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$q = trim( (string) ( $row['question'] ?? '' ) );
			$a = trim( (string) ( $row['answer'] ?? '' ) );
			if ( '' === $q || '' === $a ) {
				continue;
			}
			$clean[] = array(
				'question' => $q,
				'answer'   => $a,
			);
		}

		if ( empty( $clean ) ) {
			return array( 'ok' => false, 'message' => __( 'حداقل یک سؤال و پاسخ کامل انتخاب کنید.', 'shojaei-seo-for-woo' ) );
		}

		$clean = array_slice( $clean, 0, self::MAX_ITEMS );

		$field = 'post_content';
		$html  = '' !== trim( wp_strip_all_tags( $content ) ) ? $content : (string) $post->post_content;
		$ex    = '' !== trim( wp_strip_all_tags( $excerpt ) ) ? $excerpt : (string) $post->post_excerpt;

		if ( 'product' === $post->post_type && '' === trim( wp_strip_all_tags( $html ) ) && '' !== trim( wp_strip_all_tags( $ex ) ) ) {
			$field = 'post_excerpt';
			$html  = $ex;
		}

		$block = self::build_html( $clean );
		$html  = self::upsert_faq_block( $html, $block );

		$update = array(
			'ID'   => $post_id,
			$field => $html,
		);
		$saved = wp_update_post( $update, true );
		if ( is_wp_error( $saved ) ) {
			return array( 'ok' => false, 'message' => $saved->get_error_message() );
		}

		update_post_meta( $post_id, self::META, $clean );

		delete_transient( 'shojaei_seo_linked_' . $post_id );
		delete_transient( 'shojaei_seo_linked_short_' . $post_id );

		if ( class_exists( 'Shojaei_SEO_Link_Genius' ) ) {
			Shojaei_SEO_Link_Genius::index_post_links( $post_id );
		}

		$out = array(
			'ok'      => true,
			'message' => sprintf(
				/* translators: %d: count */
				__( '%d سؤال FAQ در محتوا درج و Schema ذخیره شد.', 'shojaei-seo-for-woo' ),
				count( $clean )
			),
			'field'   => $field,
			'count'   => count( $clean ),
		);

		if ( 'post_excerpt' === $field ) {
			$out['excerpt'] = $html;
			$out['content'] = '' !== trim( wp_strip_all_tags( $content ) ) ? $content : (string) $post->post_content;
		} else {
			$out['content'] = $html;
			$out['excerpt'] = '' !== trim( wp_strip_all_tags( $excerpt ) ) ? $excerpt : (string) $post->post_excerpt;
		}

		return $out;
	}

	/**
	 * AJAX suggest.
	 */
	public static function ajax_suggest(): void {
		check_ajax_referer( 'damavand_seo_score_live', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( $post_id < 1 || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) ), 403 );
		}
		wp_send_json_success( self::suggest( $post_id, self::context_from_request() ) );
	}

	/**
	 * AJAX inject.
	 */
	public static function ajax_inject(): void {
		check_ajax_referer( 'damavand_seo_score_live', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( $post_id < 1 || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) ), 403 );
		}

		$items_raw = isset( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : '';
		$items     = array();
		if ( is_string( $items_raw ) && '' !== $items_raw ) {
			$decoded = json_decode( $items_raw, true );
			if ( is_array( $decoded ) ) {
				$items = $decoded;
			}
		} elseif ( is_array( $items_raw ) ) {
			$items = $items_raw;
		}

		$content = isset( $_POST['content'] ) ? wp_kses_post( (string) wp_unslash( $_POST['content'] ) ) : '';
		$excerpt = isset( $_POST['excerpt'] ) ? wp_kses_post( (string) wp_unslash( $_POST['excerpt'] ) ) : '';

		$result = self::inject( $post_id, $items, $content, $excerpt );
		if ( empty( $result['ok'] ) ) {
			wp_send_json_error( array( 'message' => (string) ( $result['message'] ?? __( 'خطا.', 'shojaei-seo-for-woo' ) ) ) );
		}
		wp_send_json_success( $result );
	}

	/**
	 * @param int    $post_id Post.
	 * @param array  $context Context.
	 */
	private static function resolve_html( int $post_id, array $context ): string {
		$content = (string) ( $context['content'] ?? '' );
		$excerpt = (string) ( $context['excerpt'] ?? '' );
		if ( '' !== trim( wp_strip_all_tags( $content ) ) ) {
			return $content;
		}
		if ( '' !== trim( wp_strip_all_tags( $excerpt ) ) ) {
			return $excerpt;
		}
		if ( class_exists( 'Shojaei_SEO_Link_Builder' ) ) {
			return Shojaei_SEO_Link_Builder::resolve_linkable_html( $post_id, '' );
		}
		$post = get_post( $post_id );
		return $post ? (string) $post->post_content : '';
	}

	/**
	 * @param string $html HTML.
	 */
	private static function content_has_faq_block( string $html ): bool {
		if ( false !== stripos( $html, self::BLOCK_CLASS ) ) {
			return true;
		}
		return (bool) preg_match( '/سؤالات\s*متداول|سوالات\s*متداول/u', wp_strip_all_tags( $html ) );
	}

	/**
	 * @param int    $post_id Post.
	 * @param string $title   Title.
	 */
	private static function detect_kind( int $post_id, string $title ): string {
		$blob = mb_strtolower( $title, 'UTF-8' );
		$terms = wp_get_post_terms( $post_id, 'product_cat', array( 'fields' => 'names' ) );
		if ( is_array( $terms ) ) {
			$blob .= ' ' . mb_strtolower( implode( ' ', $terms ), 'UTF-8' );
		}

		if ( preg_match( '/کتون|کفش|نیم\s*بوت|صندل|دمپ|اسنیپ/u', $blob ) ) {
			return 'footwear';
		}
		if ( preg_match( '/هودی|سویشرت|تی\s*شرت|پیراهن|شلوار|لباس|کاپشن|ست\s/u', $blob ) ) {
			return 'apparel';
		}
		if ( preg_match( '/ساعت|کیف|کمربند|عینک/u', $blob ) ) {
			return 'accessory';
		}
		return 'generic';
	}

	/**
	 * @param int $post_id Post.
	 */
	private static function is_in_stock( int $post_id ): bool {
		if ( ! function_exists( 'wc_get_product' ) || 'product' !== get_post_type( $post_id ) ) {
			return true;
		}
		$p = wc_get_product( $post_id );
		return $p ? $p->is_in_stock() : true;
	}

	/**
	 * @param string $short    Short product name.
	 * @param string $focus    Focus kw.
	 * @param string $kind     Product kind.
	 * @param bool   $in_stock In stock.
	 * @param int    $post_id  Post.
	 * @return array<int,array{question:string,answer:string}>
	 */
	private static function build_templates( string $short, string $focus, string $kind, bool $in_stock, int $post_id, array $context = array() ): array {
		$label = class_exists( 'Damavand_Content_Analyzer' )
			? Damavand_Content_Analyzer::product_label(
				$post_id,
				array_merge(
					$context,
					array(
						'short' => $short,
						'focus' => $focus,
					)
				)
			)
			: ( $short ?: wp_trim_words( get_the_title( $post_id ), 5, '' ) );
		$ship  = __( 'ارسال معمولاً ۲ تا ۵ روز کاری بسته به شهر مقصد است؛ پس از ثبت سفارش کد رهگیری برای شما پیامک می‌شود.', 'shojaei-seo-for-woo' );
		$ret   = __( 'طبق قوانین فروشگاه، در صورت سالم بودن کالا امکان مرجوعی طبق مهلت اعلام‌شده در سایت وجود دارد.', 'shojaei-seo-for-woo' );

		$items = array();

		if ( 'footwear' === $kind ) {
			$items[] = array(
				'question' => sprintf(
					/* translators: %s: product short name */
					__( 'سایز %s را چطور انتخاب کنم؟', 'shojaei-seo-for-woo' ),
					$label
				),
				'answer'   => __( 'جدول سایز برند را با طول پا مقایسه کنید؛ اگر بین دو سایز هستید برای کتونی اسپرت معمولاً نیم‌سایز بزرگ‌تر راحت‌تر است.', 'shojaei-seo-for-woo' ),
			);
			$items[] = array(
				'question' => sprintf(
					/* translators: %s: product short name */
					__( 'ارسال %s چند روز طول می‌کشد؟', 'shojaei-seo-for-woo' ),
					$label
				),
				'answer'   => $ship,
			);
			$items[] = array(
				'question' => sprintf(
					/* translators: %s: product short name */
					__( '%s برای استفاده روزمره مناسب است؟', 'shojaei-seo-for-woo' ),
					$label
				),
				'answer'   => __( 'بله — برای پیاده‌روی و استفاده روزانه طراحی شده؛ برای دویدن حرفه‌ای مدل تخصصی‌تر پیشنهاد می‌شود.', 'shojaei-seo-for-woo' ),
			);
		} elseif ( 'apparel' === $kind ) {
			$items[] = array(
				'question' => sprintf(
					/* translators: %s: product short name */
					__( 'جنس و دوخت %s چگونه است؟', 'shojaei-seo-for-woo' ),
					$label
				),
				'answer'   => __( 'جنس در توضیحات محصول ذکر شده؛ برای ماندگاری بهتر شستشو با آب سرد و پشت‌و‌رو توصیه می‌شود.', 'shojaei-seo-for-woo' ),
			);
			$items[] = array(
				'question' => sprintf(
					/* translators: %s: product short name */
					__( 'سایز %s را چطور بگیرم؟', 'shojaei-seo-for-woo' ),
					$label
				),
				'answer'   => __( 'ابعاد جدول سایز را با لباس مشابه که راحت می‌پوشید مقایسه کنید؛ در صورت شک بین دو سایز، سایز بزرگ‌تر را انتخاب کنید.', 'shojaei-seo-for-woo' ),
			);
			$items[] = array(
				'question' => sprintf(
					/* translators: %s: product short name */
					__( 'ارسال %s به سراسر کشور دارید؟', 'shojaei-seo-for-woo' ),
					$label
				),
				'answer'   => $ship,
			);
		} else {
			$name = $focus ?: $label;
			$items[] = array(
				'question' => sprintf(
					/* translators: %s: product name */
					__( '%s برای چه کاربردی مناسب است؟', 'shojaei-seo-for-woo' ),
					$name
				),
				'answer'   => __( 'کاربرد اصلی در توضیحات بالا آمده؛ برای جزئیات بیشتر با پشتیبانی تماس بگیرید.', 'shojaei-seo-for-woo' ),
			);
			$items[] = array(
				'question' => sprintf(
					/* translators: %s: product short name */
					__( 'ارسال %s چگونه است؟', 'shojaei-seo-for-woo' ),
					$label
				),
				'answer'   => $ship,
			);
		}

		if ( ! $in_stock ) {
			$items[] = array(
				'question' => sprintf(
					/* translators: %s: product short name */
					__( 'آیا %s دوباره موجود می‌شود؟', 'shojaei-seo-for-woo' ),
					$label
				),
				'answer'   => __( 'در صورت بازگشت به انبار در همین صفحه اطلاع‌رسانی می‌شود؛ می‌توانید محصولات مشابه همان دسته را ببینید.', 'shojaei-seo-for-woo' ),
			);
		} else {
			$items[] = array(
				'question' => sprintf(
					/* translators: %s: product short name */
					__( 'شرایط تعویض و مرجوعی %s چیست؟', 'shojaei-seo-for-woo' ),
					$label
				),
				'answer'   => $ret,
				'kind'     => 'returns',
			);
		}

		if ( '' !== $focus && count( $items ) < self::MAX_ITEMS ) {
			$items[] = array(
				'question' => sprintf(
					/* translators: %s: focus keyword */
					__( 'مزیت %s در این فروشگاه چیست؟', 'shojaei-seo-for-woo' ),
					$focus
				),
				'answer'   => __( 'اصالت کالا، بسته‌بندی مناسب و پشتیبانی پس از فروش از اولویت‌های ماست.', 'shojaei-seo-for-woo' ),
			);
		}

		return array_slice( $items, 0, self::MAX_ITEMS );
	}

	/**
	 * @param array<int,array{question:string,answer:string,kind?:string}> $items FAQ rows.
	 */
	public static function build_html( array $items ): string {
		$returns_url = self::get_returns_url();
		$parts       = array(
			'<section class="' . esc_attr( self::BLOCK_CLASS ) . '">',
			'<h2>' . esc_html__( 'سؤالات متداول', 'shojaei-seo-for-woo' ) . '</h2>',
		);
		foreach ( $items as $row ) {
			$question = (string) ( $row['question'] ?? '' );
			$answer   = (string) ( $row['answer'] ?? '' );
			$is_ret   = 'returns' === ( $row['kind'] ?? '' ) || self::is_returns_question( $question );

			$parts[] = '<div class="damavand-faq__item">';
			$parts[] = '<h3>' . esc_html( $question ) . '</h3>';
			$parts[] = '<p>' . esc_html( $answer ) . '</p>';
			if ( $is_ret && '' !== $returns_url ) {
				$parts[] = '<p><a class="damavand-faq__btn" href="' . esc_url( $returns_url ) . '">'
					. esc_html__( 'مشاهده شرایط تعویض و مرجوعی', 'shojaei-seo-for-woo' ) . '</a></p>';
			}
			$parts[] = '</div>';
		}
		$parts[] = '</section>';
		return implode( "\n", $parts );
	}

	/**
	 * Replace existing FAQ block or append.
	 *
	 * @param string $html  Content.
	 * @param string $block FAQ HTML.
	 */
	private static function upsert_faq_block( string $html, string $block ): string {
		$pattern = '/<section[^>]*class=["\'][^"\']*\bdamavand-faq\b[^"\']*["\'][^>]*>.*?<\/section>/is';
		if ( preg_match( $pattern, $html ) ) {
			return (string) preg_replace( $pattern, $block, $html, 1 );
		}
		return rtrim( $html ) . "\n\n" . $block;
	}

	/**
	 * @return array{content:string,excerpt:string,focus:string,title:string}
	 */
	private static function context_from_request(): array {
		return array(
			'content' => isset( $_POST['content'] ) ? wp_kses_post( (string) wp_unslash( $_POST['content'] ) ) : '',
			'excerpt' => isset( $_POST['excerpt'] ) ? wp_kses_post( (string) wp_unslash( $_POST['excerpt'] ) ) : '',
			'focus'   => isset( $_POST['focus'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['focus'] ) ) : '',
			'title'   => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['title'] ) ) : '',
		);
	}
}
