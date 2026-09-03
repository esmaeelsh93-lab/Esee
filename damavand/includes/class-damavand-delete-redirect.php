<?php
/**
 * Smart redirect guide when trashing/deleting posts and products.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Damavand_Delete_Redirect
 */
class Damavand_Delete_Redirect {

	/**
	 * Boot admin hooks.
	 */
	public static function register_hooks(): void {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'admin_footer', array( __CLASS__, 'render_modal' ) );
		add_action( 'wp_ajax_damavand_delete_redirect_suggest', array( __CLASS__, 'ajax_suggest' ) );
		add_action( 'wp_ajax_damavand_delete_redirect_apply', array( __CLASS__, 'ajax_apply' ) );
		add_action( 'wp_ajax_damavand_delete_redirect_search', array( __CLASS__, 'ajax_search' ) );
	}

	/**
	 * Supported post types for intercept.
	 *
	 * @return string[]
	 */
	public static function post_types(): array {
		$types = array( 'product', 'post', 'page' );
		return array_values( array_unique( apply_filters( 'damavand_delete_redirect_post_types', $types ) ) );
	}

	/**
	 * @param string $hook Admin hook.
	 */
	public static function enqueue( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php', 'edit.php' ), true ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->post_type, self::post_types(), true ) ) {
			return;
		}

		wp_enqueue_style(
			'shojaei-seo-admin',
			DAMAVAND_SEO_URL . 'admin/css/admin-style.css',
			array(),
			DAMAVAND_SEO_VERSION
		);

		wp_enqueue_script(
			'damavand-delete-redirect',
			DAMAVAND_SEO_URL . 'admin/js/damavand-delete-redirect.js',
			array( 'jquery' ),
			DAMAVAND_SEO_VERSION,
			true
		);

		wp_localize_script(
			'damavand-delete-redirect',
			'damavandDeleteRedirect',
			array(
				'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'damavand_delete_redirect' ),
				'postType' => (string) $screen->post_type,
				'postId'   => 'post.php' === $hook ? (int) ( $_GET['post'] ?? 0 ) : 0, // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'i18n'     => array(
					'title'       => __( 'ریدایرکت قبل از حذف', 'shojaei-seo-for-woo' ),
					'loading'     => __( 'در حال یافتن مسیر جایگزین…', 'shojaei-seo-for-woo' ),
					'noSuggest'   => __( 'جایگزین خودکار پیدا نشد. مقصد را انتخاب یا جستجو کنید.', 'shojaei-seo-for-woo' ),
					'destLabel'   => __( 'مقصد ریدایرکت', 'shojaei-seo-for-woo' ),
					'searchPh'    => __( 'جستجوی محصول یا نوشته…', 'shojaei-seo-for-woo' ),
					'type301'     => __( 'ریدایرکت 301', 'shojaei-seo-for-woo' ),
					'type410'     => __( 'حذف دائمی (410)', 'shojaei-seo-for-woo' ),
					'skip'        => __( 'بدون ریدایرکت — فقط حذف', 'shojaei-seo-for-woo' ),
					'confirm'     => __( 'ثبت ریدایرکت و حذف', 'shojaei-seo-for-woo' ),
					'cancel'      => __( 'انصراف', 'shojaei-seo-for-woo' ),
					'error'       => __( 'خطا در ثبت ریدایرکت.', 'shojaei-seo-for-woo' ),
					'sourceLabel' => __( 'آدرس حذف‌شونده', 'shojaei-seo-for-woo' ),
				),
			)
		);
	}

	/**
	 * Modal shell in admin footer.
	 */
	public static function render_modal(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->post_type, self::post_types(), true ) ) {
			return;
		}
		?>
		<div id="damavand-delete-redirect-modal" class="damavand-delete-modal" hidden aria-hidden="true">
			<div class="damavand-delete-modal__backdrop"></div>
			<div class="damavand-delete-modal__panel" role="dialog" aria-modal="true" aria-labelledby="damavand-delete-modal-title">
				<h2 id="damavand-delete-modal-title"><?php esc_html_e( 'ریدایرکت قبل از حذف', 'shojaei-seo-for-woo' ); ?></h2>
				<p class="damavand-delete-modal__source"><strong><?php esc_html_e( 'آدرس حذف‌شونده:', 'shojaei-seo-for-woo' ); ?></strong> <span id="damavand-delete-source" dir="ltr"></span></p>
				<div id="damavand-delete-suggest-wrap">
					<p class="description"><?php esc_html_e( 'مسیرهای جایگزین پیشنهادی:', 'shojaei-seo-for-woo' ); ?></p>
					<ul id="damavand-delete-suggest-list" class="damavand-delete-suggest-list"></ul>
				</div>
				<div class="damavand-delete-modal__field">
					<label for="damavand-delete-search"><?php esc_html_e( 'جستجوی مقصد', 'shojaei-seo-for-woo' ); ?></label>
					<input type="search" id="damavand-delete-search" class="regular-text" placeholder="<?php esc_attr_e( 'نام محصول، نوشته یا شناسه…', 'shojaei-seo-for-woo' ); ?>" autocomplete="off" />
					<ul id="damavand-delete-search-results" class="damavand-delete-search-results"></ul>
				</div>
				<div class="damavand-delete-modal__field">
					<label for="damavand-delete-dest"><?php esc_html_e( 'آدرس مقصد (دستی)', 'shojaei-seo-for-woo' ); ?></label>
					<input type="text" id="damavand-delete-dest" class="regular-text" dir="ltr" placeholder="https://… یا /path" />
				</div>
				<div class="damavand-delete-modal__types">
					<label><input type="radio" name="damavand_delete_type" value="301" checked /> <?php esc_html_e( '301 دائم', 'shojaei-seo-for-woo' ); ?></label>
					<label><input type="radio" name="damavand_delete_type" value="410" /> <?php esc_html_e( '410 حذف‌شده', 'shojaei-seo-for-woo' ); ?></label>
				</div>
				<p id="damavand-delete-status" class="description" aria-live="polite"></p>
				<div class="damavand-delete-modal__actions">
					<button type="button" class="button button-primary" id="damavand-delete-confirm"><?php esc_html_e( 'ثبت ریدایرکت و حذف', 'shojaei-seo-for-woo' ); ?></button>
					<button type="button" class="button" id="damavand-delete-skip"><?php esc_html_e( 'بدون ریدایرکت — فقط حذف', 'shojaei-seo-for-woo' ); ?></button>
					<button type="button" class="button-link" id="damavand-delete-cancel"><?php esc_html_e( 'انصراف', 'shojaei-seo-for-woo' ); ?></button>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * AJAX: suggestions for a post being deleted.
	 */
	public static function ajax_suggest(): void {
		check_ajax_referer( 'damavand_delete_redirect', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( $post_id < 1 || ! current_user_can( 'delete_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) ), 403 );
		}
		wp_send_json_success( self::suggest_for_post( $post_id ) );
	}

	/**
	 * AJAX: create redirect then client completes delete.
	 */
	public static function ajax_apply(): void {
		check_ajax_referer( 'damavand_delete_redirect', 'nonce' );
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		if ( $post_id < 1 || ! current_user_can( 'delete_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) ), 403 );
		}
		if ( ! class_exists( 'Shojaei_SEO_Manual_Redirect' ) ) {
			wp_send_json_error( array( 'message' => __( 'ماژول ریدایرکت در دسترس نیست.', 'shojaei-seo-for-woo' ) ) );
		}

		$type      = isset( $_POST['redirect_type'] ) ? strtoupper( sanitize_text_field( wp_unslash( (string) $_POST['redirect_type'] ) ) ) : '301';
		$target_id = isset( $_POST['target_id'] ) ? absint( $_POST['target_id'] ) : 0;
		$target_type = isset( $_POST['target_type'] ) ? sanitize_key( wp_unslash( (string) $_POST['target_type'] ) ) : '';
		$dest_raw  = isset( $_POST['destination'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['destination'] ) ) : '';
		$skip      = ! empty( $_POST['skip_redirect'] );

		if ( $skip ) {
			wp_send_json_success( array( 'skipped' => true ) );
		}

		$source = (string) get_permalink( $post_id );
		if ( '' === $source ) {
			wp_send_json_error( array( 'message' => __( 'آدرس مبدأ یافت نشد.', 'shojaei-seo-for-woo' ) ) );
		}

		$destination = '';
		if ( '410' !== $type && '451' !== $type ) {
			if ( $target_id > 0 ) {
				if ( 'term' === $target_type ) {
					$link = get_term_link( $target_id );
					$destination = is_wp_error( $link ) ? '' : (string) $link;
				} else {
					$destination = (string) get_permalink( $target_id );
				}
			} elseif ( '' !== $dest_raw ) {
				$destination = preg_match( '#^https?://#i', $dest_raw )
					? esc_url_raw( $dest_raw )
					: esc_url_raw( home_url( '/' . ltrim( $dest_raw, '/' ) ) );
			}
			if ( '' === $destination ) {
				wp_send_json_error( array( 'message' => __( 'مقصد ریدایرکت را انتخاب یا وارد کنید.', 'shojaei-seo-for-woo' ) ) );
			}
		}

		$result = Shojaei_SEO_Manual_Redirect::add_redirect(
			array(
				'sources'       => array( $source ),
				'destination'   => $destination,
				'redirect_type' => $type,
				'match_type'    => 'exact',
			)
		);

		if ( empty( $result['ok'] ) ) {
			wp_send_json_error( array( 'message' => (string) ( $result['message'] ?? __( 'خطا.', 'shojaei-seo-for-woo' ) ) ) );
		}

		wp_send_json_success(
			array(
				'message' => (string) ( $result['message'] ?? __( 'ریدایرکت ثبت شد.', 'shojaei-seo-for-woo' ) ),
			)
		);
	}

	/**
	 * AJAX: search redirect targets.
	 */
	public static function ajax_search(): void {
		check_ajax_referer( 'damavand_delete_redirect', 'nonce' );
		if ( ! current_user_can( 'edit_posts' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) ), 403 );
		}
		$q         = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['q'] ) ) : '';
		$exclude   = isset( $_POST['exclude_id'] ) ? absint( $_POST['exclude_id'] ) : 0;
		$post_type = isset( $_POST['post_type'] ) ? sanitize_key( wp_unslash( (string) $_POST['post_type'] ) ) : 'product';
		wp_send_json_success(
			array(
				'results' => self::search_targets( $q, $post_type, $exclude ),
			)
		);
	}

	/**
	 * Build suggestion payload.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string,mixed>
	 */
	public static function suggest_for_post( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array(
				'suggestions' => array(),
				'source_url'  => '',
			);
		}

		$suggestions = array();
		$seen        = array();

		if ( 'product' === $post->post_type && function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $post_id );
			if ( $product && class_exists( 'Shojaei_SEO_Redirect_Engine' ) ) {
				$rows = Shojaei_SEO_Redirect_Engine::find_top_replacements( $product, 5, 45 );
				foreach ( $rows as $row ) {
					$tid = (int) ( $row['id'] ?? 0 );
					if ( $tid < 1 || isset( $seen[ $tid ] ) ) {
						continue;
					}
					$seen[ $tid ] = true;
					$suggestions[] = array(
						'id'     => $tid,
						'title'  => get_the_title( $tid ),
						'url'    => (string) get_permalink( $tid ),
						'reason' => sprintf(
							/* translators: %d: score */
							__( 'محصول مشابه — شباهت %d%%', 'shojaei-seo-for-woo' ),
							(int) ( $row['score'] ?? 0 )
						),
						'type'   => 'product',
					);
				}
			}

			$cats = wp_get_post_terms( $post_id, 'product_cat' );
			if ( is_array( $cats ) && ! empty( $cats ) && ! is_wp_error( $cats ) ) {
				$cat  = $cats[0];
				$link = get_term_link( $cat );
				if ( ! is_wp_error( $link ) ) {
					$key = 'term_' . (int) $cat->term_id;
					if ( ! isset( $seen[ $key ] ) ) {
						$suggestions[] = array(
							'id'     => (int) $cat->term_id,
							'title'  => (string) $cat->name,
							'url'    => (string) $link,
							'reason' => __( 'دسته اصلی محصول', 'shojaei-seo-for-woo' ),
							'type'   => 'term',
						);
					}
				}
			}
		} elseif ( in_array( $post->post_type, array( 'post', 'page' ), true ) ) {
			if ( 'post' === $post->post_type ) {
				$cats = get_the_category( $post_id );
				if ( ! empty( $cats ) ) {
					$related = get_posts(
						array(
							'post_type'              => 'post',
							'post_status'            => 'publish',
							'posts_per_page'         => 5,
							'post__not_in'           => array( $post_id ),
							'category__in'           => array( (int) $cats[0]->term_id ),
							'no_found_rows'          => true,
							'update_post_meta_cache' => false,
						)
					);
					foreach ( $related as $rel ) {
						$rid = (int) $rel->ID;
						if ( isset( $seen[ $rid ] ) ) {
							continue;
						}
						$seen[ $rid ]  = true;
						$suggestions[] = array(
							'id'     => $rid,
							'title'  => get_the_title( $rid ),
							'url'    => (string) get_permalink( $rid ),
							'reason' => __( 'نوشته هم‌دسته', 'shojaei-seo-for-woo' ),
							'type'   => 'post',
						);
					}
					$link = get_category_link( (int) $cats[0]->term_id );
					if ( $link && ! is_wp_error( $link ) ) {
						$suggestions[] = array(
							'id'     => (int) $cats[0]->term_id,
							'title'  => (string) $cats[0]->name,
							'url'    => (string) $link,
							'reason' => __( 'دسته نوشته', 'shojaei-seo-for-woo' ),
							'type'   => 'term',
						);
					}
				}
			}
			if ( 'page' === $post->post_type && $post->post_parent > 0 ) {
				$parent_id = (int) $post->post_parent;
				$suggestions[] = array(
					'id'     => $parent_id,
					'title'  => get_the_title( $parent_id ),
					'url'    => (string) get_permalink( $parent_id ),
					'reason' => __( 'برگه والد', 'shojaei-seo-for-woo' ),
					'type'   => 'page',
				);
			}
		}

		$shop = function_exists( 'wc_get_page_id' ) ? (int) wc_get_page_id( 'shop' ) : 0;
		if ( $shop > 0 && $shop !== $post_id ) {
			$suggestions[] = array(
				'id'     => $shop,
				'title'  => get_the_title( $shop ),
				'url'    => (string) get_permalink( $shop ),
				'reason' => __( 'فروشگاه', 'shojaei-seo-for-woo' ),
				'type'   => 'page',
			);
		}

		return array(
			'post_id'     => $post_id,
			'title'       => get_the_title( $post_id ),
			'post_type'   => $post->post_type,
			'source_url'  => (string) get_permalink( $post_id ),
			'suggestions' => array_slice( $suggestions, 0, 8 ),
		);
	}

	/**
	 * Search products/posts for picker.
	 *
	 * @param string $query     Query.
	 * @param string $post_type Context post type.
	 * @param int    $exclude   Exclude ID.
	 * @return array<int,array<string,mixed>>
	 */
	public static function search_targets( string $query, string $post_type = 'product', int $exclude = 0 ): array {
		$query = trim( $query );
		if ( '' === $query ) {
			return array();
		}

		$types = array( 'product' === $post_type ? 'product' : 'post' );
		if ( 'page' === $post_type ) {
			$types = array( 'page', 'post' );
		}

		if ( 'product' === $types[0] && class_exists( 'Shojaei_SEO_Slug' ) ) {
			$rows = Shojaei_SEO_Slug::search_products_for_slug( $query, 12 );
			$out  = array();
			foreach ( $rows as $row ) {
				$pid = (int) ( $row['product_id'] ?? $row['id'] ?? 0 );
				if ( $pid < 1 || $pid === $exclude ) {
					continue;
				}
				$out[] = array(
					'id'    => $pid,
					'title' => (string) ( $row['title'] ?? get_the_title( $pid ) ),
					'url'   => (string) ( $row['permalink'] ?? get_permalink( $pid ) ),
					'type'  => 'product',
				);
			}
			return $out;
		}

		$q = new WP_Query(
			array(
				'post_type'              => $types,
				'post_status'            => 'publish',
				's'                      => $query,
				'posts_per_page'         => 12,
				'post__not_in'           => $exclude > 0 ? array( $exclude ) : array(),
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
			)
		);
		$out = array();
		foreach ( $q->posts as $p ) {
			if ( ! $p instanceof WP_Post ) {
				continue;
			}
			$out[] = array(
				'id'    => (int) $p->ID,
				'title' => get_the_title( $p ),
				'url'   => (string) get_permalink( $p ),
				'type'  => (string) $p->post_type,
			);
		}
		return $out;
	}
}
