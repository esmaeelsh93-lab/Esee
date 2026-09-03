<?php
/**
 * Damavand Link Resolver — dynamic related-box injection (no post_content writes).
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Damavand_Link_Resolver
 */
final class Damavand_Link_Resolver {

	/**
	 * Register front filters / shortcode.
	 */
	public static function register_hooks(): void {
		add_filter( 'the_content', array( __CLASS__, 'filter_content' ), 28 );
		add_shortcode( 'damavand_related', array( __CLASS__, 'shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
	}

	/**
	 * Front CSS for related box.
	 */
	public static function enqueue_assets(): void {
		if ( is_admin() && ! is_preview() ) {
			return;
		}
		if ( ! defined( 'DAMAVAND_SEO_URL' ) ) {
			return;
		}
		wp_enqueue_style(
			'damavand-link-resolver',
			DAMAVAND_SEO_URL . 'public/css/damavand-related.css',
			array(),
			defined( 'DAMAVAND_SEO_VERSION' ) ? DAMAVAND_SEO_VERSION : '1.0'
		);
	}

	/**
	 * Shortcode [damavand_related].
	 *
	 * @param array<string,string> $atts Atts.
	 */
	public static function shortcode( $atts = array() ): string {
		$atts = shortcode_atts(
			array(
				'id'    => 0,
				'limit' => 5,
			),
			$atts,
			'damavand_related'
		);
		$id = absint( $atts['id'] );
		if ( $id < 1 ) {
			$id = get_the_ID();
		}
		return self::render_box( absint( $id ), absint( $atts['limit'] ) );
	}

	/**
	 * Inject related box into content.
	 *
	 * @param string $content Content.
	 */
	public static function filter_content( string $content ): string {
		if ( ! self::should_inject() ) {
			return $content;
		}

		// Avoid double related-box on product singles when Similar Products is ON.
		if (
			function_exists( 'is_product' )
			&& is_product()
			&& class_exists( 'Damavand_Similar_Products_Engine' )
			&& Damavand_Similar_Products_Engine::is_enabled()
		) {
			return $content;
		}

		$post_id = get_the_ID();
		if ( $post_id < 1 ) {
			return $content;
		}

		// Avoid double inject when shortcode already present.
		if ( has_shortcode( $content, 'damavand_related' ) ) {
			return $content;
		}

		$box = self::render_box( $post_id );
		if ( '' === $box ) {
			return $content;
		}

		/**
		 * Paragraph index after which to inject (1-based). 0 = append.
		 *
		 * @param int $after After Nth </p>.
		 * @param int $post_id Post.
		 */
		$after = (int) apply_filters( 'damavand_link_inject_after_paragraph', 2, $post_id );
		if ( $after < 1 ) {
			return $content . $box;
		}

		$parts = preg_split( '/(<\/p>)/i', $content, -1, PREG_SPLIT_DELIM_CAPTURE );
		if ( ! is_array( $parts ) || count( $parts ) < 2 ) {
			return $content . $box;
		}

		$out      = '';
		$p_count  = 0;
		$injected = false;
		$i        = 0;
		$len      = count( $parts );
		while ( $i < $len ) {
			$out .= $parts[ $i ];
			if ( isset( $parts[ $i + 1 ] ) && preg_match( '/<\/p>/i', $parts[ $i + 1 ] ) ) {
				$out .= $parts[ $i + 1 ];
				++$p_count;
				$i += 2;
				if ( ! $injected && $p_count >= $after ) {
					$out     .= $box;
					$injected = true;
				}
				continue;
			}
			++$i;
		}
		if ( ! $injected ) {
			$out .= $box;
		}
		return $out;
	}

	/**
	 * Render related box HTML from approved graph edges.
	 *
	 * @param int $source_id Source.
	 * @param int $limit     Limit.
	 */
	public static function render_box( int $source_id, int $limit = 0 ): string {
		if ( ! class_exists( 'Damavand_Link_Manager' ) ) {
			return '';
		}

		// noindex pages: skip auto box unless filtered.
		if ( self::is_noindex_post( $source_id ) ) {
			/**
			 * Allow related box on noindex pages.
			 *
			 * @param bool $allow Allow.
			 * @param int  $source_id Source.
			 */
			if ( ! (bool) apply_filters( 'damavand_link_allow_on_noindex', false, $source_id ) ) {
				return '';
			}
		}

		$edges = Damavand_Link_Manager::get_approved_for_source( $source_id, $limit );
		// Prefer related_box type; fall back to any approved.
		$edges = array_values(
			array_filter(
				$edges,
				static function ( $e ) {
					$type = (string) ( $e['type'] ?? '' );
					return Damavand_Link_Manager::TYPE_RELATED_BOX === $type
						|| Damavand_Link_Manager::TYPE_AUTO === $type
						|| Damavand_Link_Manager::TYPE_MANUAL === $type;
				}
			)
		);
		if ( empty( $edges ) ) {
			return '';
		}

		ob_start();
		?>
		<aside class="damavand-related" dir="rtl" data-source="<?php echo esc_attr( (string) $source_id ); ?>">
			<div class="damavand-related__inner">
				<h3 class="damavand-related__title"><?php esc_html_e( 'محصولات مرتبط', 'shojaei-seo-for-woo' ); ?></h3>
				<ul class="damavand-related__list">
					<?php foreach ( $edges as $edge ) : ?>
						<?php
						$url   = (string) ( $edge['target_url'] ?? '' );
						$title = (string) ( $edge['anchor_text'] ?? $edge['target_title'] ?? '' );
						$reason = (string) ( $edge['reason'] ?? '' );
						if ( '' === $url || '' === $title ) {
							continue;
						}
						?>
						<li class="damavand-related__item">
							<a class="damavand-related__link" href="<?php echo esc_url( $url ); ?>">
								<span class="damavand-related__anchor"><?php echo esc_html( $title ); ?></span>
								<?php if ( $reason ) : ?>
									<span class="damavand-related__reason"><?php echo esc_html( $reason ); ?></span>
								<?php endif; ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</aside>
		<?php
		$html = (string) ob_get_clean();

		/**
		 * Filter rendered related box HTML.
		 *
		 * @param string $html HTML.
		 * @param int    $source_id Source.
		 * @param array  $edges Edges.
		 */
		return (string) apply_filters( 'damavand_link_related_box_html', $html, $source_id, $edges );
	}

	/**
	 * Whether injection is safe in this request.
	 */
	private static function should_inject(): bool {
		// Editors / builders (Elementor, Woodmart admin, block editor).
		if ( is_admin() && ! is_preview() ) {
			return false;
		}
		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			return false;
		}
		if ( is_feed() || is_search() || is_embed() ) {
			return false;
		}
		if ( ! is_singular() ) {
			return false;
		}

		// Elementor edit / preview canvas.
		if ( class_exists( '\Elementor\Plugin' ) ) {
			$el = \Elementor\Plugin::$instance;
			if ( isset( $el->editor ) && method_exists( $el->editor, 'is_edit_mode' ) && $el->editor->is_edit_mode() ) {
				return false;
			}
			if ( isset( $el->preview ) && method_exists( $el->preview, 'is_preview_mode' ) && $el->preview->is_preview_mode() && is_admin() ) {
				return false;
			}
		}

		$post = get_post();
		if ( ! $post || ! Damavand_Link_Manager::is_graph_post_type( (string) $post->post_type ) ) {
			return false;
		}

		/**
		 * Gate dynamic related-box injection.
		 *
		 * @param bool $allow Allow.
		 * @param int  $post_id Post.
		 */
		return (bool) apply_filters( 'damavand_link_should_inject', true, (int) $post->ID );
	}

	/**
	 * Soft noindex detection (Damavand / Yoast / Rank Math).
	 *
	 * @param int $post_id Post.
	 */
	private static function is_noindex_post( int $post_id ): bool {
		if ( 'yes' === get_post_meta( $post_id, '_shojaei_seo_noindex', true ) ) {
			return true;
		}
		if ( '1' === (string) get_post_meta( $post_id, '_yoast_wpseo_meta-robots-noindex', true ) ) {
			return true;
		}
		$rm = get_post_meta( $post_id, 'rank_math_robots', true );
		if ( is_array( $rm ) && in_array( 'noindex', $rm, true ) ) {
			return true;
		}
		return false;
	}
}
