<?php
/**
 * Outbound internal link suggestions + smart editor injection.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Damavand_Link_Suggestions
 */
class Damavand_Link_Suggestions {

	public const SUGGEST_LIMIT = 4;

	/**
	 * Boot AJAX hooks.
	 */
	public static function register_hooks(): void {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'wp_ajax_damavand_link_suggest', array( __CLASS__, 'ajax_suggest' ) );
		add_action( 'wp_ajax_damavand_link_inject', array( __CLASS__, 'ajax_inject' ) );
		add_action( 'wp_ajax_damavand_link_search', array( __CLASS__, 'ajax_search' ) );
		add_action( 'wp_ajax_damavand_link_fix_alert', array( __CLASS__, 'ajax_fix_alert' ) );
	}

	/**
	 * Suggest outbound internal links for a post/product.
	 *
	 * @param int   $source_id Source post ID.
	 * @param array $context   Optional live context (content, excerpt, focus, title).
	 * @return array{suggestions:array<int,array>,needs_link:bool}
	 */
	public static function suggest( int $source_id, array $context = array() ): array {
		$source = get_post( $source_id );
		if ( ! $source || 'publish' !== $source->post_status ) {
			return array( 'suggestions' => array(), 'needs_link' => false );
		}

		$html     = self::resolve_html( $source_id, $context );
		$needs    = ! self::has_internal_link( $html );
		$linked   = self::linked_post_ids( $html, $source_id );
		$tokens   = self::source_tokens( $source_id, $context );
		$focus    = self::focus_keyword( $source_id, $context );
		$exclude  = array_merge(
			array( $source_id ),
			$linked,
			class_exists( 'Shojaei_SEO_Helpers' ) ? Shojaei_SEO_Helpers::get_410_excluded_ids() : array()
		);
		$exclude  = array_values( array_unique( array_filter( array_map( 'absint', $exclude ) ) ) );

		$candidates = self::fetch_candidates( $source_id, $exclude );
		$scored     = array();

		foreach ( $candidates as $target_id ) {
			$target_id = absint( $target_id );
			if ( $target_id < 1 ) {
				continue;
			}
			$row = self::score_target( $source_id, $target_id, $tokens, $focus );
			if ( null === $row || $row['score'] < 12 ) {
				continue;
			}
			$scored[] = $row;
		}

		usort(
			$scored,
			static function ( $a, $b ) {
				return (int) ( $b['score'] ?? 0 ) <=> (int) ( $a['score'] ?? 0 );
			}
		);

		$scored = array_slice( $scored, 0, self::SUGGEST_LIMIT );
		foreach ( $scored as $i => $row ) {
			$scored[ $i ]['checked'] = $i < ( $needs ? 2 : 1 );
		}

		return array(
			'suggestions' => $scored,
			'needs_link'  => $needs,
		);
	}

	/**
	 * Inject selected outbound links into source content.
	 *
	 * @param int    $source_id  Source post.
	 * @param int[]  $target_ids Target post IDs.
	 * @param string $content    Live content override.
	 * @param string $excerpt    Live excerpt override.
	 * @return array{ok:bool,message?:string,field?:string,content?:string,excerpt?:string,inserted?:int,details?:array}
	 */
	public static function inject( int $source_id, array $target_ids, string $content = '', string $excerpt = '', bool $preview_only = false ): array {
		$post = get_post( $source_id );
		if ( ! $post || ! current_user_can( 'edit_post', $source_id ) ) {
			return array( 'ok' => false, 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) );
		}

		$target_ids = array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $id ) use ( $source_id ) {
							$id = absint( $id );
							return ( $id > 0 && $id !== $source_id ) ? $id : 0;
						},
						$target_ids
					)
				)
			)
		);

		if ( empty( $target_ids ) ) {
			return array( 'ok' => false, 'message' => __( 'حداقل یک مقصد را انتخاب کنید.', 'shojaei-seo-for-woo' ) );
		}

		$field   = 'post_content';
		$html    = '' !== trim( wp_strip_all_tags( $content ) ) ? $content : (string) $post->post_content;
		$ex_html = '' !== trim( wp_strip_all_tags( $excerpt ) ) ? $excerpt : (string) $post->post_excerpt;

		if ( 'product' === $post->post_type && '' === trim( wp_strip_all_tags( $html ) ) && '' !== trim( wp_strip_all_tags( $ex_html ) ) ) {
			$field = 'post_excerpt';
			$html  = $ex_html;
		}

		if ( '' === trim( wp_strip_all_tags( $html ) ) ) {
			return array( 'ok' => false, 'message' => __( 'ابتدا توضیح محصول را بنویسید.', 'shojaei-seo-for-woo' ) );
		}

		$word_count = class_exists( 'Shojaei_SEO_Helpers' )
			? Shojaei_SEO_Helpers::count_words( wp_strip_all_tags( $html ) )
			: str_word_count( wp_strip_all_tags( $html ) );
		$max_links  = $word_count >= 180 ? min( 2, count( $target_ids ) ) : 1;
		$target_ids = array_slice( $target_ids, 0, $max_links );

		$details  = array();
		$inserted = 0;
		$before   = $html;

		foreach ( $target_ids as $target_id ) {
			$target = get_post( $target_id );
			if ( ! $target || 'publish' !== $target->post_status ) {
				continue;
			}
			if ( class_exists( 'Shojaei_SEO_Slug' ) && Shojaei_SEO_Slug::is_410_product( $target_id ) ) {
				continue;
			}

			$url    = (string) get_permalink( $target_id );
			$anchor = self::anchor_for_target( $target_id );
			$keys   = self::anchor_keywords( $target_id, $anchor );

			$result = self::insert_link_into_html( $html, $url, $keys, $anchor );
			if ( ! empty( $result['inserted'] ) ) {
				$html = (string) $result['html'];
				++$inserted;
				$details[] = array(
					'target_id' => $target_id,
					'title'     => get_the_title( $target_id ),
					'anchor'    => (string) ( $result['anchor'] ?? $anchor ),
					'mode'      => (string) ( $result['mode'] ?? 'match' ),
				);
			}
		}

		if ( $inserted < 1 ) {
			return array(
				'ok'      => false,
				'message' => __( 'لینکی درج نشد — شاید قبلاً به این مقصدها لینک داده‌اید.', 'shojaei-seo-for-woo' ),
			);
		}

		$update = array( 'ID' => $source_id );
		if ( 'post_excerpt' === $field ) {
			$update['post_excerpt'] = $html;
		} else {
			$update['post_content'] = $html;
		}

		if ( ! $preview_only ) {
			$saved = wp_update_post( $update, true );
			if ( is_wp_error( $saved ) ) {
				return array( 'ok' => false, 'message' => $saved->get_error_message() );
			}

			delete_transient( 'shojaei_seo_linked_' . $source_id );
			delete_transient( 'shojaei_seo_linked_short_' . $source_id );

			if ( class_exists( 'Shojaei_SEO_Link_Genius' ) ) {
				Shojaei_SEO_Link_Genius::index_post_links( $source_id );
			}
		}

		if ( class_exists( 'Shojaei_SEO_Revert_Log' ) && ! $preview_only ) {
			Shojaei_SEO_Revert_Log::record(
				array(
					'batch_id'    => Shojaei_SEO_Revert_Log::new_batch_id(),
					'mode'        => 'applied',
					'action'      => 'outbound_link_inject',
					'entity_type' => $post->post_type,
					'entity_id'   => $source_id,
					'summary'     => sprintf(
						/* translators: 1: title, 2: count */
						__( 'لینک داخلی در «%1$s»: %2$d لینک', 'shojaei-seo-for-woo' ),
						$post->post_title,
						$inserted
					),
					'before'      => array( $field => $before ),
					'after'       => array( $field => $html, 'details' => $details ),
				)
			);
		}

		$out = array(
			'ok'       => true,
			'message'  => $preview_only
				? sprintf(
					/* translators: %d: count */
					__( '%d لینک در ویرایشگر درج شد — برای انتشار «به‌روزرسانی» را بزنید.', 'shojaei-seo-for-woo' ),
					$inserted
				)
				: sprintf(
					/* translators: %d: count */
					__( '%d لینک داخلی درج شد. ذخیرهٔ خودکار انجام شد.', 'shojaei-seo-for-woo' ),
					$inserted
				),
			'field'    => $field,
			'inserted' => $inserted,
			'details'  => $details,
			'preview'  => $preview_only,
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
	 * Remove one broken internal link from post content (watchdog fix).
	 *
	 * @param int    $source_id Source post.
	 * @param string $dest_url  Destination URL to strip.
	 * @return array{ok:bool,message:string}
	 */
	public static function remove_internal_link( int $source_id, string $dest_url ): array {
		$post = get_post( $source_id );
		if ( ! $post || ! current_user_can( 'edit_post', $source_id ) ) {
			return array( 'ok' => false, 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) );
		}

		$dest_url = esc_url_raw( $dest_url );
		if ( '' === $dest_url ) {
			return array( 'ok' => false, 'message' => __( 'آدرس مقصد نامعتبر است.', 'shojaei-seo-for-woo' ) );
		}

		$path = (string) wp_parse_url( $dest_url, PHP_URL_PATH );
		$path = $path ? untrailingslashit( $path ) : '';

		$changed = false;
		foreach ( array( 'post_content', 'post_excerpt' ) as $field ) {
			$html = (string) $post->$field;
			if ( '' === trim( $html ) ) {
				continue;
			}
			$new = self::strip_link_to_url( $html, $dest_url, $path );
			if ( $new !== $html ) {
				wp_update_post(
					array(
						'ID'   => $source_id,
						$field => $new,
					)
				);
				$changed = true;
				$post->$field = $new;
			}
		}

		if ( ! $changed ) {
			return array( 'ok' => false, 'message' => __( 'لینکی برای حذف پیدا نشد.', 'shojaei-seo-for-woo' ) );
		}

		delete_transient( 'shojaei_seo_linked_' . $source_id );
		delete_transient( 'shojaei_seo_linked_short_' . $source_id );
		if ( class_exists( 'Shojaei_SEO_Link_Genius' ) ) {
			Shojaei_SEO_Link_Genius::index_post_links( $source_id );
		}

		return array( 'ok' => true, 'message' => __( 'لینک شکسته از محتوا حذف شد.', 'shojaei-seo-for-woo' ) );
	}

	/**
	 * Replace internal link href (e.g. after 301) in post content.
	 *
	 * @param int    $source_id Source post.
	 * @param string $old_url   Old destination URL.
	 * @param string $new_url   New destination URL.
	 * @return array{ok:bool,message:string}
	 */
	public static function update_internal_link_url( int $source_id, string $old_url, string $new_url ): array {
		$post = get_post( $source_id );
		if ( ! $post || ! current_user_can( 'edit_post', $source_id ) ) {
			return array( 'ok' => false, 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) );
		}

		$old_url = esc_url_raw( $old_url );
		$new_url = esc_url_raw( $new_url );
		if ( '' === $old_url || '' === $new_url || $old_url === $new_url ) {
			return array( 'ok' => false, 'message' => __( 'آدرس مقصد نامعتبر است.', 'shojaei-seo-for-woo' ) );
		}

		$old_path = (string) wp_parse_url( $old_url, PHP_URL_PATH );
		$old_path = $old_path ? untrailingslashit( $old_path ) : '';

		$changed = false;
		foreach ( array( 'post_content', 'post_excerpt' ) as $field ) {
			$html = (string) $post->$field;
			if ( '' === trim( $html ) ) {
				continue;
			}
			$new_html = self::replace_link_href( $html, $old_url, $old_path, $new_url );
			if ( $new_html !== $html ) {
				wp_update_post(
					array(
						'ID'   => $source_id,
						$field => $new_html,
					)
				);
				$changed      = true;
				$post->$field = $new_html;
			}
		}

		if ( ! $changed ) {
			return array( 'ok' => false, 'message' => __( 'لینکی برای به‌روزرسانی پیدا نشد.', 'shojaei-seo-for-woo' ) );
		}

		delete_transient( 'shojaei_seo_linked_' . $source_id );
		delete_transient( 'shojaei_seo_linked_short_' . $source_id );
		if ( class_exists( 'Shojaei_SEO_Link_Genius' ) ) {
			Shojaei_SEO_Link_Genius::index_post_links( $source_id );
		}

		return array( 'ok' => true, 'message' => __( 'آدرس لینک در محتوا به‌روز شد.', 'shojaei-seo-for-woo' ) );
	}

	/**
	 * @param string $html     HTML.
	 * @param string $old_url  Old URL.
	 * @param string $old_path Old path.
	 * @param string $new_url  New URL.
	 */
	private static function replace_link_href( string $html, string $old_url, string $old_path, string $new_url ): string {
		return (string) preg_replace_callback(
			'/<a\b([^>]*?)>(.*?)<\/a>/is',
			static function ( $m ) use ( $old_url, $old_path, $new_url ) {
				$attrs = (string) ( $m[1] ?? '' );
				$inner = (string) ( $m[2] ?? '' );
				if ( ! preg_match( '/\bhref\s*=\s*(["\'])(.*?)\1/i', $attrs, $hm ) ) {
					return $m[0];
				}
				$href = (string) $hm[2];
				$match = ( $href === $old_url || ( $old_path && false !== stripos( $href, $old_path ) ) );
				if ( ! $match ) {
					return $m[0];
				}
				$new_attrs = preg_replace(
					'/\bhref\s*=\s*(["\'])(.*?)\1/i',
					'href="' . esc_url( $new_url ) . '"',
					$attrs,
					1
				);
				return '<a' . (string) $new_attrs . '>' . $inner . '</a>';
			},
			$html
		);
	}

	/**
	 * Search publishable posts/products for manual link pick.
	 *
	 * @param string $query Search term.
	 * @param int    $limit Max results.
	 * @return array<int,array<string,mixed>>
	 */
	public static function search_posts( string $query, int $limit = 12 ): array {
		$query = trim( $query );
		if ( mb_strlen( $query ) < 2 ) {
			return array();
		}

		$q = new WP_Query(
			array(
				'post_type'              => array( 'product', 'post', 'page' ),
				'post_status'            => 'publish',
				's'                      => $query,
				'posts_per_page'         => max( 1, min( 20, $limit ) ),
				'orderby'                => 'relevance',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
			)
		);

		$rows = array();
		foreach ( $q->posts as $p ) {
			if ( ! $p instanceof WP_Post ) {
				continue;
			}
			$id = (int) $p->ID;
			if ( class_exists( 'Shojaei_SEO_Slug' ) && Shojaei_SEO_Slug::is_410_product( $id ) ) {
				continue;
			}
			$type_label = 'product' === $p->post_type
				? __( 'محصول', 'shojaei-seo-for-woo' )
				: ( 'page' === $p->post_type ? __( 'برگه', 'shojaei-seo-for-woo' ) : __( 'نوشته', 'shojaei-seo-for-woo' ) );
			$rows[] = array(
				'post_id' => $id,
				'title'   => get_the_title( $id ),
				'url'     => get_permalink( $id ),
				'type'    => $type_label,
			);
		}
		return $rows;
	}

	/**
	 * AJAX: search posts for manual link target.
	 */
	public static function ajax_search(): void {
		check_ajax_referer( 'damavand_seo_score_live', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( $post_id < 1 || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) ), 403 );
		}
		$q = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['q'] ) ) : '';
		wp_send_json_success(
			array(
				'results' => self::search_posts( $q, 15 ),
			)
		);
	}

	/**
	 * AJAX: suggestions.
	 */
	public static function ajax_suggest(): void {
		check_ajax_referer( 'damavand_seo_score_live', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( $post_id < 1 || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) ), 403 );
		}

		$context = self::context_from_request();
		$result  = self::suggest( $post_id, $context );

		$at_risk = class_exists( 'Damavand_Link_Watchdog' )
			? Damavand_Link_Watchdog::alerts_for_post( $post_id, 5 )
			: array();

		wp_send_json_success(
			array(
				'suggestions' => $result['suggestions'],
				'needs_link'  => $result['needs_link'],
				'at_risk'     => $at_risk,
			)
		);
	}

	/**
	 * AJAX: inject links.
	 */
	public static function ajax_inject(): void {
		check_ajax_referer( 'damavand_seo_score_live', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( $post_id < 1 || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) ), 403 );
		}

		$raw_ids = isset( $_POST['target_ids'] ) ? wp_unslash( $_POST['target_ids'] ) : array();
		if ( ! is_array( $raw_ids ) ) {
			$raw_ids = explode( ',', (string) $raw_ids );
		}
		$target_ids = array_map( 'absint', $raw_ids );

		$content = isset( $_POST['content'] ) ? wp_kses_post( (string) wp_unslash( $_POST['content'] ) ) : '';
		$excerpt = isset( $_POST['excerpt'] ) ? wp_kses_post( (string) wp_unslash( $_POST['excerpt'] ) ) : '';
		$preview = ! empty( $_POST['preview_only'] );

		$result = self::inject( $post_id, $target_ids, $content, $excerpt, $preview );
		if ( empty( $result['ok'] ) ) {
			wp_send_json_error( array( 'message' => (string) ( $result['message'] ?? __( 'خطا.', 'shojaei-seo-for-woo' ) ) ) );
		}
		wp_send_json_success( $result );
	}

	/**
	 * AJAX: fix alert (remove broken link).
	 */
	public static function ajax_fix_alert(): void {
		check_ajax_referer( 'damavand_seo_score_live', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		$dest    = isset( $_POST['dest_url'] ) ? esc_url_raw( wp_unslash( (string) $_POST['dest_url'] ) ) : '';
		$alert   = isset( $_POST['alert_id'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['alert_id'] ) ) : '';

		if ( $post_id < 1 || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) ), 403 );
		}

		$result = self::remove_internal_link( $post_id, $dest );
		if ( empty( $result['ok'] ) ) {
			wp_send_json_error( array( 'message' => (string) $result['message'] ) );
		}

		if ( $alert && class_exists( 'Damavand_Link_Watchdog' ) ) {
			Damavand_Link_Watchdog::dismiss_alert( $alert );
		}

		wp_send_json_success( $result );
	}

	/**
	 * @param int   $source_id Source.
	 * @param array $context   Context.
	 */
	private static function resolve_html( int $source_id, array $context ): string {
		$content = (string) ( $context['content'] ?? '' );
		$excerpt = (string) ( $context['excerpt'] ?? '' );
		if ( '' !== trim( wp_strip_all_tags( $content ) ) ) {
			return $content;
		}
		if ( '' !== trim( wp_strip_all_tags( $excerpt ) ) ) {
			return $excerpt;
		}
		if ( class_exists( 'Shojaei_SEO_Link_Builder' ) ) {
			return Shojaei_SEO_Link_Builder::resolve_linkable_html( $source_id, '' );
		}
		$post = get_post( $source_id );
		return $post ? (string) $post->post_content : '';
	}

	/**
	 * @param string $html HTML.
	 */
	private static function has_internal_link( string $html ): bool {
		if ( ! preg_match_all( '/<a\s[^>]{0,400}href=["\']([^"\']{1,500})["\']/i', $html, $m ) || empty( $m[1] ) ) {
			return false;
		}
		$home = wp_parse_url( home_url(), PHP_URL_HOST );
		foreach ( $m[1] as $href ) {
			$href = (string) $href;
			if ( 0 === strpos( $href, '/' ) || ( $home && false !== stripos( $href, (string) $home ) ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Post IDs already linked from HTML.
	 *
	 * @param string $html      HTML.
	 * @param int    $source_id Source (exclude self).
	 * @return int[]
	 */
	private static function linked_post_ids( string $html, int $source_id ): array {
		$out = array();
		if ( ! preg_match_all( '/<a\s[^>]{0,400}href=["\']([^"\']{1,500})["\']/i', $html, $m ) || empty( $m[1] ) ) {
			return $out;
		}
		foreach ( $m[1] as $href ) {
			$pid = url_to_postid( (string) $href );
			if ( $pid > 0 && $pid !== $source_id ) {
				$out[] = $pid;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/**
	 * @param int   $source_id Source.
	 * @param array $context   Context.
	 * @return array<string,true>
	 */
	private static function source_tokens( int $source_id, array $context ): array {
		$parts = array(
			(string) ( $context['title'] ?? '' ),
			(string) get_the_title( $source_id ),
			self::focus_keyword( $source_id, $context ),
			(string) ( $context['desc'] ?? '' ),
		);
		$post = get_post( $source_id );
		if ( $post ) {
			$parts[] = (string) $post->post_excerpt;
		}
		if ( class_exists( 'Damavand_Content_Analyzer' ) ) {
			$parts[] = Damavand_Content_Analyzer::description_blob( $source_id );
			foreach ( Damavand_Content_Analyzer::related_keywords_for_post( $source_id, $context ) as $rel ) {
				$parts[] = $rel;
			}
		}

		$tokens = array();
		foreach ( $parts as $text ) {
			if ( class_exists( 'Damavand_Content_Analyzer' ) ) {
				foreach ( Damavand_Content_Analyzer::distinctive_tokens_from_text( (string) $text, 16 ) as $tok ) {
					$tokens[ $tok ] = true;
				}
				continue;
			}
			$text = mb_strtolower( wp_strip_all_tags( $text ), 'UTF-8' );
			foreach ( preg_split( '/[\s\-\/،,]+/u', $text ) as $tok ) {
				$tok = trim( (string) $tok );
				if ( mb_strlen( $tok, 'UTF-8' ) >= 2 ) {
					$tokens[ $tok ] = true;
				}
			}
		}
		return $tokens;
	}

	/**
	 * @param int   $source_id Source.
	 * @param array $context   Context.
	 */
	private static function focus_keyword( int $source_id, array $context ): string {
		$focus = trim( (string) ( $context['focus'] ?? '' ) );
		if ( '' !== $focus ) {
			return $focus;
		}
		if ( class_exists( 'Damavand_SEO_Meta' ) ) {
			return trim( (string) get_post_meta( $source_id, Damavand_SEO_Meta::FOCUS, true ) );
		}
		return '';
	}

	/**
	 * @param int   $source_id Source.
	 * @param int[] $exclude   Exclude IDs.
	 * @return int[]
	 */
	private static function fetch_candidates( int $source_id, array $exclude ): array {
		$cat_ids = array();
		if ( 'product' === get_post_type( $source_id ) && function_exists( 'wc_get_product' ) ) {
			$p = wc_get_product( $source_id );
			if ( $p ) {
				$cat_ids = array_map( 'absint', (array) $p->get_category_ids() );
			}
		}
		if ( empty( $cat_ids ) ) {
			$terms = wp_get_object_terms( $source_id, 'category', array( 'fields' => 'ids' ) );
			if ( ! is_wp_error( $terms ) ) {
				$cat_ids = array_map( 'absint', (array) $terms );
			}
		}

		$args = array(
			'post_type'              => array( 'product', 'post', 'page' ),
			'post_status'            => 'publish',
			'posts_per_page'         => 50,
			'post__not_in'           => $exclude,
			'fields'                 => 'ids',
			'orderby'                => 'modified',
			'order'                  => 'DESC',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => true,
		);

		if ( ! empty( $cat_ids ) ) {
			$args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
				array(
					'taxonomy' => 'product' === get_post_type( $source_id ) ? 'product_cat' : 'category',
					'field'    => 'term_id',
					'terms'    => $cat_ids,
				),
			);
		}

		$ids = get_posts( $args );
		if ( count( $ids ) < self::SUGGEST_LIMIT ) {
			$extra = get_posts(
				array(
					'post_type'              => array( 'product', 'post' ),
					'post_status'            => 'publish',
					'posts_per_page'         => 30,
					'post__not_in'           => array_merge( $exclude, is_array( $ids ) ? $ids : array() ),
					'fields'                 => 'ids',
					'orderby'                => 'rand',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
				)
			);
			$ids = array_merge( is_array( $ids ) ? $ids : array(), is_array( $extra ) ? $extra : array() );
		}

		return is_array( $ids ) ? $ids : array();
	}

	/**
	 * @param int                $source_id Source.
	 * @param int                $target_id Target.
	 * @param array<string,true> $tokens    Tokens.
	 * @param string             $focus     Focus kw.
	 * @return array|null
	 */
	private static function score_target( int $source_id, int $target_id, array $tokens, string $focus ): ?array {
		$score   = 0.0;
		$reasons = array();

		if ( class_exists( 'Damavand_Link_Calculator' ) ) {
			$pair = Damavand_Link_Calculator::score_pair( $source_id, $target_id );
			if ( is_array( $pair ) ) {
				$score    += min( 45, (float) ( $pair['score'] ?? 0 ) * 0.6 );
				$reasons[] = (string) ( $pair['reason'] ?? '' );
			}
		}

		$title   = get_the_title( $target_id );
		$title_l = mb_strtolower( $title, 'UTF-8' );
		$overlap = 0;
		foreach ( array_keys( $tokens ) as $tok ) {
			if ( class_exists( 'Damavand_Content_Analyzer' ) && Damavand_Content_Analyzer::is_low_value_token( $tok ) ) {
				continue;
			}
			if ( false !== mb_strpos( $title_l, $tok, 0, 'UTF-8' ) ) {
				++$overlap;
			}
		}
		if ( $overlap > 0 ) {
			$score    += min( 25, $overlap * 5 );
			$reasons[] = __( 'هم‌پوشانی کلمات متمایز', 'shojaei-seo-for-woo' );
		}

		if ( class_exists( 'Damavand_Content_Analyzer' ) ) {
			$sim = Damavand_Content_Analyzer::description_similarity( $source_id, $target_id );
			if ( $sim >= 0.08 ) {
				$score    += min( 30, $sim * 100 );
				$reasons[] = __( 'شباهت توضیحات', 'shojaei-seo-for-woo' );
			}
			$src_rel = Damavand_Content_Analyzer::related_keywords_for_post( $source_id );
			$tgt_rel = Damavand_Content_Analyzer::related_keywords_for_post( $target_id );
			if ( ! empty( $src_rel ) && ! empty( $tgt_rel ) ) {
				$shared_rel = count(
					array_intersect(
						array_map( static fn( $s ) => mb_strtolower( (string) $s, 'UTF-8' ), $src_rel ),
						array_map( static fn( $s ) => mb_strtolower( (string) $s, 'UTF-8' ), $tgt_rel )
					)
				);
				if ( $shared_rel > 0 ) {
					$score    += min( 20, $shared_rel * 10 );
					$reasons[] = __( 'کلمات مرتبط مشترک', 'shojaei-seo-for-woo' );
				}
			}
		}

		if ( '' !== $focus && class_exists( 'Damavand_Persian_SEO_Score' ) ) {
			$desc = (string) get_post_meta( $target_id, Damavand_SEO_Meta::DESC, true );
			if ( Damavand_Persian_SEO_Score::contains_keyword( $desc, $focus ) || Damavand_Persian_SEO_Score::contains_keyword( $title, $focus ) ) {
				$score    += 12;
				$reasons[] = __( 'نزدیک به کلمه کلیدی', 'shojaei-seo-for-woo' );
			}
		}

		if ( function_exists( 'wc_get_product' ) && 'product' === get_post_type( $target_id ) ) {
			$p = wc_get_product( $target_id );
			if ( $p && $p->is_in_stock() ) {
				$score += 8;
			} elseif ( $p && ! $p->is_in_stock() ) {
				$score -= 12;
			}
		}

		$reasons = array_values( array_filter( array_unique( $reasons ) ) );
		if ( $score < 12 ) {
			return null;
		}

		return array(
			'post_id'   => $target_id,
			'title'     => $title,
			'permalink' => (string) get_permalink( $target_id ),
			'score'     => (int) round( $score ),
			'reason'    => implode( ' · ', array_slice( $reasons, 0, 2 ) ),
			'checked'   => false,
		);
	}

	/**
	 * @param int    $target_id Target.
	 */
	private static function anchor_for_target( int $target_id ): string {
		if ( class_exists( 'Damavand_Content_Analyzer' ) && 'product' === get_post_type( $target_id ) ) {
			$label = Damavand_Content_Analyzer::product_label( $target_id );
			if ( '' !== trim( $label ) ) {
				return wp_trim_words( $label, 8, '' );
			}
		}
		$title = wp_trim_words( get_the_title( $target_id ), 8, '' );
		return $title ?: ( '#' . $target_id );
	}

	/**
	 * @param int    $target_id Target.
	 * @param string $anchor    Anchor fallback.
	 * @return string[]
	 */
	private static function anchor_keywords( int $target_id, string $anchor ): array {
		$keys = array( $anchor );
		if ( class_exists( 'Shojaei_SEO_Link_Genius' ) ) {
			$keys = array_merge( $keys, Shojaei_SEO_Link_Genius::keywords_from_title( get_the_title( $target_id ) ) );
		}
		$slug = get_post_field( 'post_name', $target_id );
		if ( $slug ) {
			$keys[] = str_replace( '-', ' ', (string) $slug );
		}
		$keys = array_values(
			array_unique(
				array_filter(
					array_map(
						static function ( $k ) {
							$k = trim( (string) $k );
							return mb_strlen( $k, 'UTF-8' ) >= 2 ? $k : '';
						},
						$keys
					)
				)
			)
		);
		usort(
			$keys,
			static function ( $a, $b ) {
				return mb_strlen( (string) $b, 'UTF-8' ) <=> mb_strlen( (string) $a, 'UTF-8' );
			}
		);
		return $keys;
	}

	/**
	 * @param string   $html   HTML.
	 * @param string   $url    Target URL.
	 * @param string[] $keys   Keywords longest-first.
	 * @param string   $anchor Fallback anchor.
	 * @return array{inserted:bool,html:string,anchor?:string,mode?:string}
	 */
	private static function insert_link_into_html( string $html, string $url, array $keys, string $anchor ): array {
		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$path = $path ? untrailingslashit( $path ) : '';
		if ( $path && false !== stripos( $html, $path ) ) {
			return array( 'inserted' => false, 'html' => $html );
		}

		foreach ( $keys as $kw ) {
			$kw = trim( (string) $kw );
			if ( mb_strlen( $kw, 'UTF-8' ) < 2 ) {
				continue;
			}
			$new = self::replace_first_outside_anchors( $html, $kw, $url );
			if ( null !== $new ) {
				return array(
					'inserted' => true,
					'html'     => $new,
					'anchor'   => $kw,
					'mode'     => 'match',
				);
			}
		}

		$append = '<p>' . sprintf(
			/* translators: %s: linked anchor */
			__( 'محصول مرتبط: %s', 'shojaei-seo-for-woo' ),
			'<a href="' . esc_url( $url ) . '">' . esc_html( $anchor ) . '</a>'
		) . '</p>';

		return array(
			'inserted' => true,
			'html'     => rtrim( $html ) . "\n\n" . $append,
			'anchor'   => $anchor,
			'mode'     => 'append',
		);
	}

	/**
	 * @param string $html    HTML.
	 * @param string $keyword Keyword.
	 * @param string $url     URL.
	 */
	private static function replace_first_outside_anchors( string $html, string $keyword, string $url ): ?string {
		$parts = preg_split( '/(<a\b[^>]*>.*?<\/a>)/is', $html, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $parts ) ) {
			return null;
		}
		$quoted  = preg_quote( $keyword, '/' );
		$pattern = '/' . $quoted . '/u';
		$link    = '<a href="' . esc_url( $url ) . '">' . esc_html( $keyword ) . '</a>';
		$done    = false;

		foreach ( $parts as $i => $chunk ) {
			if ( $done || preg_match( '/^<a\b/i', $chunk ) ) {
				continue;
			}
			if ( preg_match( '/^<h[1-6]\b/i', $chunk ) ) {
				continue;
			}
			$sub = preg_split( '/(<[^>]+>)/', $chunk, -1, PREG_SPLIT_DELIM_CAPTURE );
			if ( ! is_array( $sub ) ) {
				continue;
			}
			foreach ( $sub as $j => $piece ) {
				if ( $done || '' === $piece || '<' === $piece[0] ) {
					continue;
				}
				if ( preg_match( $pattern, $piece ) ) {
					$sub[ $j ] = preg_replace( $pattern, $link, $piece, 1 );
					$done      = true;
				}
			}
			if ( $done ) {
				$parts[ $i ] = implode( '', $sub );
			}
		}

		return $done ? implode( '', $parts ) : null;
	}

	/**
	 * @param string $html     HTML.
	 * @param string $dest_url URL.
	 * @param string $path     Path fragment.
	 */
	private static function strip_link_to_url( string $html, string $dest_url, string $path ): string {
		return (string) preg_replace_callback(
			'/<a\b([^>]*?)>(.*?)<\/a>/is',
			static function ( $m ) use ( $dest_url, $path ) {
				$attrs = (string) ( $m[1] ?? '' );
				$inner = (string) ( $m[2] ?? '' );
				$href  = '';
				if ( preg_match( '/\bhref\s*=\s*(["\'])(.*?)\1/i', $attrs, $hm ) ) {
					$href = (string) $hm[2];
				}
				$match = ( $href && ( $href === $dest_url || ( $path && false !== stripos( $href, $path ) ) ) );
				return $match ? wp_strip_all_tags( $inner ) : $m[0];
			},
			$html
		);
	}

	/**
	 * @return array{content:string,excerpt:string,focus:string,title:string,desc:string}
	 */
	private static function context_from_request(): array {
		return array(
			'content' => isset( $_POST['content'] ) ? wp_kses_post( (string) wp_unslash( $_POST['content'] ) ) : '',
			'excerpt' => isset( $_POST['excerpt'] ) ? wp_kses_post( (string) wp_unslash( $_POST['excerpt'] ) ) : '',
			'focus'   => isset( $_POST['focus'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['focus'] ) ) : '',
			'title'   => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['title'] ) ) : '',
			'desc'    => isset( $_POST['desc'] ) ? sanitize_textarea_field( wp_unslash( (string) $_POST['desc'] ) ) : '',
		);
	}
}
