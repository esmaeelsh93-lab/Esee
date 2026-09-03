<?php
/**
 * Internal link builder — conservative rule-based injection.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_Link_Builder
 */
class Shojaei_SEO_Link_Builder {

	/**
	 * Tags whose entire inner content must not receive auto-links.
	 *
	 * @var array
	 */
	private array $skip_tags = array(
		'a', 'script', 'style', 'code', 'pre', 'textarea', 'noscript',
		'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
		'button', 'nav', 'header', 'footer', 'form', 'svg', 'iframe', 'canvas',
		'select', 'option', 'label', 'aside',
	);

	/**
	 * Class/id tokens that mark menus, buttons, CTAs.
	 *
	 * @var array
	 */
	private array $skip_class_tokens = array(
		'btn', 'button', 'menu', 'nav', 'navbar', 'navigation',
		'wp-block-button', 'wp-block-navigation', 'elementor-button',
		'elementor-nav', 'menu-item', 'cta',
	);

	/** @var bool */
	private static $hooks_registered = false;

	/**
	 * Constructor.
	 *
	 * @param bool $register_hooks False when only calling methods (preview/AJAX).
	 */
	public function __construct( bool $register_hooks = true ) {
		if ( ! $register_hooks || self::$hooks_registered ) {
			return;
		}
		if ( ! Shojaei_SEO_Helpers::is_module_enabled( 'link_builder' ) ) {
			return;
		}
		if ( class_exists( 'SEO_Core_Installer' ) && ! SEO_Core_Installer::is_module_enabled( 'links' ) ) {
			return;
		}
		self::$hooks_registered = true;

		add_action( 'save_post', array( $this, 'process_content_on_save' ), 20, 2 );
		add_filter( 'the_content', array( $this, 'filter_content' ), 99 );
		add_filter( 'woocommerce_short_description', array( $this, 'filter_short_description' ), 99 );
	}

	/**
	 * Resolve HTML used for linking: content, or short description for products.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $fallback Fallback HTML.
	 */
	public static function resolve_linkable_html( int $post_id, string $fallback = '' ): string {
		if ( $post_id < 1 ) {
			return $fallback;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return $fallback;
		}

		$content = (string) $post->post_content;
		$excerpt = (string) $post->post_excerpt;

		// Woo products often keep body empty and put text in short description.
		if ( '' === trim( wp_strip_all_tags( $content ) ) && '' !== trim( wp_strip_all_tags( $excerpt ) ) ) {
			return $excerpt;
		}
		if ( '' !== trim( wp_strip_all_tags( $content ) ) ) {
			return $content;
		}
		if ( '' !== trim( wp_strip_all_tags( $fallback ) ) ) {
			return $fallback;
		}
		// Last resort: allow matching/related-block against title as plain text.
		return $post->post_title ? '<p>' . esc_html( $post->post_title ) . '</p>' : '';
	}

	/**
	 * Process content on save and cache linked version.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public function process_content_on_save( int $post_id, $post ): void {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( ! in_array( $post->post_type, array( 'post', 'product' ), true ) ) {
			return;
		}

		if ( 'publish' !== $post->post_status ) {
			return;
		}

		$cache_key = 'shojaei_seo_linked_' . $post_id;
		$prev      = get_transient( $cache_key );
		$html      = self::resolve_linkable_html( $post_id, (string) $post->post_content );
		$result    = $this->build_links( $html, true, $post_id );
		set_transient( $cache_key, $result['content'], DAY_IN_SECONDS );

		if ( class_exists( 'Shojaei_SEO_Revert_Log' ) && $result['links_added'] > 0 ) {
			Shojaei_SEO_Revert_Log::record(
				array(
					'batch_id'    => Shojaei_SEO_Revert_Log::new_batch_id(),
					'mode'        => 'applied',
					'action'      => 'link_build',
					'entity_type' => $post->post_type,
					'entity_id'   => $post_id,
					'summary'     => sprintf(
						/* translators: 1: title, 2: count */
						__( 'لینک‌سازی «%1$s»: %2$d لینک در کش', 'shojaei-seo-for-woo' ),
						$post->post_title,
						(int) $result['links_added']
					),
					'before'      => array(
						'transient'     => $cache_key,
						'has_cache'     => false !== $prev,
						'cache_content' => is_string( $prev ) ? $prev : '',
					),
					'after'       => array(
						'links_added' => (int) $result['links_added'],
						'details'     => $result['details'],
						'has_cache'   => true,
					),
				)
			);
		}
	}

	/**
	 * Filter content to inject cached links.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public function filter_content( string $content ): string {
		if ( ! is_singular( array( 'post', 'product' ) ) ) {
			return $content;
		}

		// Products: prefer short-description filter when body is empty.
		if ( is_singular( 'product' ) && '' === trim( wp_strip_all_tags( $content ) ) ) {
			return $content;
		}

		$post_id = (int) get_the_ID();
		$cached  = get_transient( 'shojaei_seo_linked_' . $post_id );

		if ( false !== $cached ) {
			return $cached;
		}

		$html   = self::resolve_linkable_html( $post_id, $content );
		$result = $this->build_links( $html, false, $post_id );
		set_transient( 'shojaei_seo_linked_' . $post_id, $result['content'], DAY_IN_SECONDS );

		return $result['content'];
	}

	/**
	 * Inject links into WooCommerce short description when that is the main text.
	 *
	 * @param string $content Short description HTML.
	 */
	public function filter_short_description( string $content ): string {
		if ( ! is_singular( 'product' ) ) {
			return $content;
		}

		$post_id = (int) get_the_ID();
		$post    = get_post( $post_id );
		if ( $post && '' !== trim( wp_strip_all_tags( (string) $post->post_content ) ) ) {
			return $content; // Body content is used instead.
		}

		$cache_key = 'shojaei_seo_linked_short_' . $post_id;
		$cached    = get_transient( $cache_key );
		if ( false !== $cached ) {
			return $cached;
		}

		$html   = '' !== trim( wp_strip_all_tags( $content ) ) ? $content : self::resolve_linkable_html( $post_id, $content );
		$result = $this->build_links( $html, false, $post_id );
		set_transient( $cache_key, $result['content'], DAY_IN_SECONDS );

		return $result['content'];
	}

	/**
	 * Preview link building without saving.
	 *
	 * @param string $content        Content to preview.
	 * @param int    $source_post_id Optional source for priority rules.
	 * @return array
	 */
	public function preview_links( string $content, int $source_post_id = 0 ): array {
		if ( $source_post_id > 0 ) {
			$content = self::resolve_linkable_html( $source_post_id, $content );
		}
		return $this->build_links( $content, false, $source_post_id );
	}

	/**
	 * Build internal links with conservative rule engine.
	 *
	 * @param string $content         Raw content.
	 * @param bool   $count_stat      Whether to increment stat counter.
	 * @param int    $source_post_id  Source post/product for priority.
	 * @return array{content:string,links_added:int,details:array,skipped:array,max_allowed:int,word_count:int}
	 */
	public function build_links( string $content, bool $count_stat = false, int $source_post_id = 0 ): array {
		global $wpdb;

		$empty = array(
			'content'         => $content,
			'links_added'     => 0,
			'details'         => array(),
			'skipped'         => array(),
			'max_allowed'     => 0,
			'word_count'      => 0,
			'existing_links'  => 0,
			'existing_list'   => array(),
		);

		if ( empty( trim( $content ) ) ) {
			return $empty;
		}

		// Inventory of links already in the source HTML (not inserted by this engine).
		$existing_list = array();
		if ( preg_match_all( '/<a\b([^>]*)>(.*?)<\/a>/is', $content, $am, PREG_SET_ORDER ) ) {
			foreach ( $am as $m ) {
				$attrs = (string) ( $m[1] ?? '' );
				$inner = trim( wp_strip_all_tags( (string) ( $m[2] ?? '' ) ) );
				$href  = '';
				if ( preg_match( '/\bhref\s*=\s*(["\'])(.*?)\1/i', $attrs, $hm ) ) {
					$href = (string) $hm[2];
				}
				if ( '' !== $inner || '' !== $href ) {
					$existing_list[] = array(
						'anchor' => $inner,
						'url'    => $href,
					);
				}
			}
		}
		$existing_count = count( $existing_list );

		$keywords = $wpdb->get_results(
			'SELECT keyword, target_url FROM ' . Shojaei_SEO_Helpers::links_table() . ' WHERE is_active = 1 ORDER BY LENGTH(keyword) DESC'
		);

		if ( empty( $keywords ) ) {
			$empty['word_count']     = Shojaei_SEO_Helpers::count_words( wp_strip_all_tags( $content ) );
			$empty['max_allowed']    = class_exists( 'Shojaei_SEO_Link_Rules' )
				? Shojaei_SEO_Link_Rules::max_allowed_for_content( (int) $empty['word_count'] )
				: 0;
			$empty['existing_links'] = $existing_count;
			$empty['existing_list']  = $existing_list;
			return $empty;
		}

		if ( class_exists( 'Shojaei_SEO_Link_Rules' ) ) {
			$keywords          = Shojaei_SEO_Link_Rules::prepare_candidates( $keywords, $source_post_id );
			$prefilter_skipped = Shojaei_SEO_Link_Rules::last_skipped();
		} elseif ( class_exists( 'Shojaei_SEO_Rule_Engine' ) ) {
			$prefilter_skipped = array();
			// Legacy fallback if rules class missing.
			$keywords = array_values(
				array_filter(
					$keywords,
					static function ( $row ) {
						$url = (string) ( $row->target_url ?? '' );
						if ( ! $url ) {
							return true;
						}
						$post_id = url_to_postid( $url );
						if ( $post_id && 'product' === get_post_type( $post_id ) ) {
							if ( 'yes' === get_post_meta( $post_id, '_shojaei_seo_link_deprioritized', true ) ) {
								return false;
							}
						}
						return true;
					}
				)
			);
		} else {
			$prefilter_skipped = array();
		}

		$plain_text   = wp_strip_all_tags( $content );
		$word_count   = Shojaei_SEO_Helpers::count_words( $plain_text );
		$max_allowed  = class_exists( 'Shojaei_SEO_Link_Rules' )
			? Shojaei_SEO_Link_Rules::max_allowed_for_content( $word_count )
			: 3;

		if ( empty( $keywords ) ) {
			$empty['word_count']     = $word_count;
			$empty['skipped']        = $prefilter_skipped ?? array();
			$empty['max_allowed']    = $max_allowed;
			$empty['existing_links'] = $existing_count;
			$empty['existing_list']  = $existing_list;
			$empty['content']        = $content;
			return $empty;
		}
		$min_word_gap = class_exists( 'Shojaei_SEO_Link_Rules' )
			? Shojaei_SEO_Link_Rules::min_word_gap()
			: 200;
		// Short product texts cannot satisfy a 200-word gap — scale down.
		if ( $word_count > 0 && $word_count < ( $min_word_gap * 2 ) ) {
			$min_word_gap = max( 40, (int) floor( $word_count / 3 ) );
		}

		// Anchors already present in source HTML (manual links) — do not repeat same anchor text.
		$existing_anchors = array();
		if ( preg_match_all( '/<a\b[^>]*>(.*?)<\/a>/is', $content, $am ) ) {
			foreach ( $am[1] as $inner ) {
				$txt = trim( wp_strip_all_tags( $inner ) );
				if ( '' !== $txt ) {
					$existing_anchors[] = class_exists( 'Shojaei_SEO_Link_Rules' )
						? Shojaei_SEO_Link_Rules::normalize_token( $txt )
						: mb_strtolower( $txt, 'UTF-8' );
				}
			}
		}

		$state = array(
			'keywords'          => $keywords,
			'links_added'       => 0,
			'max_allowed'       => $max_allowed,
			'min_word_gap'      => $min_word_gap,
			'used_urls'         => array(),
			'used_keywords'     => array(),
			'used_anchors'      => $existing_anchors,
			'linked_positions'  => array(),
			'global_word_pos'   => 0,
			'details'           => array(),
			'skipped'           => $prefilter_skipped ?? array(),
			'source_post_id'    => $source_post_id,
		);

		$output = $this->process_segments( $content, $state );

		// Fallback: if nothing matched in-body, append a related-products recovery block.
		if ( 0 === (int) $state['links_added'] && $source_post_id > 0 && $max_allowed > 0 ) {
			$block = $this->build_related_links_block( $source_post_id, $max_allowed, $state );
			if ( '' !== $block ) {
				$output .= $block;
			}
		}

		if ( $count_stat && $state['links_added'] > 0 ) {
			Shojaei_SEO_Helpers::increment_stat( 'links_built' );
		}

		return array(
			'content'        => $output,
			'links_added'    => $state['links_added'],
			'details'        => $state['details'],
			'skipped'        => $state['skipped'],
			'max_allowed'    => $max_allowed,
			'word_count'     => $word_count,
			'max_page'       => class_exists( 'Shojaei_SEO_Link_Rules' ) ? Shojaei_SEO_Link_Rules::max_per_page() : 5,
			'existing_links' => $existing_count,
			'existing_list'  => $existing_list,
		);
	}

	/**
	 * Append a small related-links paragraph when in-body keyword match fails.
	 *
	 * @param int   $source_post_id Source.
	 * @param int   $max_allowed    Cap.
	 * @param array $state          Mutable state.
	 */
	private function build_related_links_block( int $source_post_id, int $max_allowed, array &$state ): string {
		if ( ! class_exists( 'Shojaei_SEO_Link_Rules' ) || 'product' !== get_post_type( $source_post_id ) ) {
			return '';
		}

		$auto = Shojaei_SEO_Link_Rules::auto_related_candidates( $source_post_id );
		if ( empty( $auto ) ) {
			return '';
		}

		$links = array();
		$seen  = array();
		foreach ( $auto as $row ) {
			if ( count( $links ) >= min( 2, $max_allowed ) ) {
				break;
			}
			$url = (string) ( $row->target_url ?? '' );
			if ( ! $url || isset( $seen[ $url ] ) ) {
				continue;
			}
			$eval = Shojaei_SEO_Link_Rules::evaluate_target( $url );
			if ( ! $eval['ok'] || (int) $eval['post_id'] === $source_post_id ) {
				continue;
			}
			$seen[ $url ] = true;
			$title        = get_the_title( (int) $eval['post_id'] );
			if ( '' === $title ) {
				$title = (string) ( $row->keyword ?? '' );
			}
			$links[] = array(
				'keyword'    => $title,
				'matched'    => $title,
				'target_url' => $url,
				'priority'   => (int) ( $row->_priority_boost ?? 0 ),
			);
		}

		if ( empty( $links ) ) {
			return '';
		}

		$html = '<p class="shojaei-seo-related-links">' . esc_html__( 'محصولات مرتبط:', 'shojaei-seo-for-woo' ) . ' ';
		$parts = array();
		foreach ( $links as $item ) {
			$parts[] = '<a href="' . esc_url( $item['target_url'] ) . '" class="shojaei-internal-link shojaei-seo-inserted">' . esc_html( $item['keyword'] ) . '</a>';
			$state['links_added']++;
			$state['details'][]   = $item;
			$state['used_urls'][] = $item['target_url'];
		}
		$html .= implode( ' · ', $parts ) . '</p>';

		return $html;
	}

	/**
	 * Process content segments while skipping protected HTML regions.
	 *
	 * @param string $content Content chunk.
	 * @param array  $state   Mutable link-building state.
	 * @return string
	 */
	private function process_segments( string $content, array &$state ): string {
		$parts      = preg_split( '/(<[^>]+>)/', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
		$skip_tag   = null;
		$skip_depth = 0;
		$output     = '';

		if ( ! is_array( $parts ) ) {
			return $this->link_text_segment( $content, $state );
		}

		foreach ( $parts as $part ) {
			if ( '' === $part ) {
				continue;
			}

			if ( preg_match( '/^<\/?([a-zA-Z0-9]+)/', $part, $matches ) ) {
				$tag = strtolower( $matches[1] );

				if ( preg_match( '/^<\//', $part ) ) {
					if ( $skip_depth > 0 && $tag === $skip_tag ) {
						--$skip_depth;
						if ( 0 === $skip_depth ) {
							$skip_tag = null;
						}
					}
					$output .= $part;
					continue;
				}

				$self_closing = (bool) preg_match( '/\/\s*>$/', $part ) || in_array( $tag, array( 'br', 'hr', 'img', 'input', 'meta', 'link', 'source', 'wbr' ), true );

				if ( $skip_depth > 0 ) {
					if ( ! $self_closing && $tag === $skip_tag ) {
						++$skip_depth;
					}
					$output .= $part;
					continue;
				}

				if ( ! $self_closing && $this->should_skip_tag( $tag, $part ) ) {
					$skip_tag   = $tag;
					$skip_depth = 1;
				}

				$output .= $part;
				continue;
			}

			if ( $skip_depth > 0 ) {
				$output .= $part;
				continue;
			}

			$output .= $this->link_text_segment( $part, $state );
		}

		return $output;
	}

	/**
	 * Whether an opening tag starts a no-link zone.
	 *
	 * @param string $tag      Tag name.
	 * @param string $tag_html Full opening tag HTML.
	 */
	private function should_skip_tag( string $tag, string $tag_html ): bool {
		if ( in_array( $tag, $this->skip_tags, true ) ) {
			return true;
		}

		if ( preg_match( '/\b(?:class|id)=(["\'])(.*?)\1/i', $tag_html, $m ) ) {
			$tokens = preg_split( '/[\s_-]+/', strtolower( $m[2] ) );
			if ( is_array( $tokens ) ) {
				foreach ( $tokens as $token ) {
					if ( in_array( $token, $this->skip_class_tokens, true ) ) {
						return true;
					}
				}
			}
			$hay = strtolower( $m[2] );
			foreach ( $this->skip_class_tokens as $needle ) {
				if ( false !== strpos( $hay, $needle ) ) {
					return true;
				}
			}
		}

		if ( preg_match( '/\brole=(["\'])(button|navigation|menuitem|menu)\1/i', $tag_html ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Apply internal links to a plain-text HTML segment.
	 *
	 * @param string $segment Text segment.
	 * @param array  $state   Mutable state.
	 * @return string
	 */
	private function link_text_segment( string $segment, array &$state ): string {
		if ( '' === trim( wp_strip_all_tags( $segment ) ) ) {
			return $segment;
		}

		$segment_word_count = Shojaei_SEO_Helpers::count_words( wp_strip_all_tags( $segment ) );
		$segment_start_pos  = $state['global_word_pos'];

		foreach ( $state['keywords'] as $row ) {
			if ( $state['links_added'] >= $state['max_allowed'] ) {
				break;
			}

			$keyword = trim( (string) $row->keyword );
			if ( '' === $keyword ) {
				continue;
			}

			$norm_key = class_exists( 'Shojaei_SEO_Link_Rules' )
				? Shojaei_SEO_Link_Rules::normalize_token( $keyword )
				: mb_strtolower( $keyword, 'UTF-8' );

			if ( in_array( $row->target_url, $state['used_urls'], true ) ) {
				$state['skipped'][] = array(
					'keyword' => $keyword,
					'reason'  => 'dup_url',
				);
				continue;
			}

			if ( in_array( $norm_key, $state['used_keywords'], true ) || in_array( $norm_key, $state['used_anchors'], true ) ) {
				$state['skipped'][] = array(
					'keyword' => $keyword,
					'reason'  => 'dup_anchor',
				);
				continue;
			}

			$pattern = class_exists( 'Shojaei_SEO_Persian' )
				? Shojaei_SEO_Persian::keyword_pattern( $keyword )
				: '/(?<![\w\x{0600}-\x{06FF}])' . preg_quote( $keyword, '/' ) . '(?![\w\x{0600}-\x{06FF}])/iu';

			if ( ! preg_match( $pattern, $segment, $match, PREG_OFFSET_CAPTURE ) ) {
				continue;
			}

			$matched_text = $match[0][0];
			$byte_offset  = $match[0][1];
			$norm_anchor  = class_exists( 'Shojaei_SEO_Link_Rules' )
				? Shojaei_SEO_Link_Rules::normalize_token( $matched_text )
				: mb_strtolower( $matched_text, 'UTF-8' );

			// Same visible anchor text already linked on this page.
			if ( in_array( $norm_anchor, $state['used_anchors'], true ) ) {
				$state['skipped'][] = array(
					'keyword' => $keyword,
					'reason'  => 'dup_anchor',
				);
				continue;
			}

			$before_match = wp_strip_all_tags( substr( $segment, 0, $byte_offset ) );
			$word_offset  = Shojaei_SEO_Helpers::count_words( $before_match );
			$absolute_pos = $segment_start_pos + $word_offset;
			$too_close    = false;

			foreach ( $state['linked_positions'] as $pos ) {
				if ( abs( $absolute_pos - $pos ) < $state['min_word_gap'] ) {
					$too_close = true;
					break;
				}
			}

			if ( $too_close ) {
				continue;
			}

			$replacement = '<a href="' . esc_url( $row->target_url ) . '" class="shojaei-internal-link shojaei-seo-inserted">' . esc_html( $matched_text ) . '</a>';
			$segment     = substr_replace( $segment, $replacement, $byte_offset, strlen( $matched_text ) );

			$state['links_added']++;
			$state['used_urls'][]        = $row->target_url;
			$state['used_keywords'][]    = $norm_key;
			$state['used_anchors'][]     = $norm_anchor;
			$state['linked_positions'][] = $absolute_pos;
			$state['details'][]          = array(
				'keyword'    => $keyword,
				'matched'    => $matched_text,
				'target_url' => $row->target_url,
				'priority'   => (int) ( $row->_priority ?? 0 ),
			);
		}

		$state['global_word_pos'] += $segment_word_count;

		return $segment;
	}
}
