<?php
/**
 * SEO Pulse analysis engine — modular rule runner.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_Pulse_Engine
 */
class Shojaei_SEO_Pulse_Engine {

	/**
	 * Registered rule callbacks.
	 *
	 * @var array<int,callable>
	 */
	private array $rules = array();

	/**
	 * Constructor — register built-in rules.
	 */
	public function __construct() {
		$this->register_builtin_rules();
		/**
		 * Allow adding/removing pulse rules.
		 *
		 * @param Shojaei_SEO_Pulse_Engine $engine Engine.
		 */
		do_action( 'shojaei_seo_pulse_register_rules', $this );
	}

	/**
	 * Register a rule callable.
	 *
	 * Callable signature: function( WP_Post $post, array $ctx ): ?array
	 * Return null (pass) or issue array:
	 * [code, layer, severity, title, why, action, points, priority]
	 *
	 * @param callable $rule Rule.
	 */
	public function add_rule( callable $rule ): void {
		$this->rules[] = $rule;
	}

	/**
	 * Built-in modular rules (4 layers).
	 */
	private function register_builtin_rules(): void {
		// --- On-page ---
		$this->add_rule( array( $this, 'rule_title' ) );
		$this->add_rule( array( $this, 'rule_title_focus_keyword' ) );
		$this->add_rule( array( $this, 'rule_meta_description' ) );
		$this->add_rule( array( $this, 'rule_h1' ) );
		$this->add_rule( array( $this, 'rule_heading_structure' ) );
		$this->add_rule( array( $this, 'rule_slug' ) );

		// --- Content ---
		$this->add_rule( array( $this, 'rule_content_length' ) );
		$this->add_rule( array( $this, 'rule_featured_image' ) );
		$this->add_rule( array( $this, 'rule_image_alt' ) );
		$this->add_rule( array( $this, 'rule_media' ) );
		$this->add_rule( array( $this, 'rule_paragraphs' ) );
		$this->add_rule( array( $this, 'rule_keyword_density' ) );

		// --- Technical ---
		$this->add_rule( array( $this, 'rule_noindex_flag' ) );
		$this->add_rule( array( $this, 'rule_canonical' ) );
		$this->add_rule( array( $this, 'rule_schema_hint' ) );

		// --- Links ---
		$this->add_rule( array( $this, 'rule_internal_links' ) );
		$this->add_rule( array( $this, 'rule_external_links' ) );
		$this->add_rule( array( $this, 'rule_orphan' ) );
		$this->add_rule( array( $this, 'rule_outbound_broken' ) );
	}

	/**
	 * Analyze one post and optionally persist.
	 *
	 * @param int  $post_id Post ID.
	 * @param bool $force   Ignore hash cache.
	 * @return array{saved:bool,score?:int,skipped?:bool}
	 */
	public function analyze_post( int $post_id, bool $force = false ): array {
		if ( ! class_exists( 'Shojaei_SEO_Pulse' ) || ! Shojaei_SEO_Pulse::is_ready() ) {
			return array( 'saved' => false, 'error' => 'not_ready' );
		}

		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return array( 'saved' => false );
		}
		if ( ! in_array( $post->post_type, Shojaei_SEO_Pulse::post_types(), true ) ) {
			return array( 'saved' => false );
		}
		// محصولات 410 Gone در نبض سئو تحلیل / نمایش نمی‌شوند.
		if ( class_exists( 'Shojaei_SEO_Helpers' ) && Shojaei_SEO_Helpers::is_410_excluded( $post_id ) ) {
			if ( class_exists( 'Shojaei_SEO_Pulse' ) ) {
				Shojaei_SEO_Pulse::forget_post( $post_id );
			}
			return array(
				'saved'       => false,
				'skipped'     => true,
				'skipped_410' => true,
			);
		}

		$hash = md5( $post->post_title . '|' . $post->post_content . '|' . $post->post_name . '|' . $post->post_excerpt );
		if ( ! $force ) {
			global $wpdb;
			$table = Shojaei_SEO_Pulse::table();
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$prev = $wpdb->get_var( $wpdb->prepare( "SELECT content_hash FROM {$table} WHERE post_id = %d", $post_id ) );
			if ( $prev && $prev === $hash ) {
				return array( 'saved' => false, 'skipped' => true );
			}
		}

		$ctx      = $this->build_context( $post );
		$issues   = array();
		$successes = array();
		foreach ( $this->rules as $rule ) {
			$issue = call_user_func( $rule, $post, $ctx );
			if ( is_array( $issue ) && ! empty( $issue['code'] ) ) {
				$norm = $this->normalize_issue( $issue );
				if ( 'success' === $norm['severity'] ) {
					$successes[] = $norm;
				} else {
					$issues[] = $norm;
				}
			}
		}

		$scores = $this->score_from_issues( $issues );
		$crit   = 0;
		$warn   = 0;
		foreach ( $issues as $i ) {
			if ( 'error' === $i['severity'] ) {
				++$crit;
			} elseif ( 'warning' === $i['severity'] ) {
				++$warn;
			}
		}

		$status = Shojaei_SEO_Pulse::status_from_score( $scores['total'] );
		$all    = array_merge( $issues, $successes );

		$row = array(
			'post_id'         => $post_id,
			'post_type'       => $post->post_type,
			'score'           => $scores['total'],
			'score_onpage'    => $scores['onpage'],
			'score_content'   => $scores['content'],
			'score_technical' => $scores['technical'],
			'score_links'     => $scores['links'],
			'critical_count'  => $crit,
			'warning_count'   => $warn,
			'is_orphan'       => ! empty( $ctx['is_orphan'] ) ? 1 : 0,
			'issues'          => $all,
			'content_hash'    => $hash,
		);

		$ok = Shojaei_SEO_Pulse::save_result( $row );
		return array(
			'saved'            => $ok,
			'score'            => $scores['total'],
			'scores'           => $scores,
			'weights'          => $this->get_layer_weights(),
			'status'           => $status['key'],
			'status_label'     => $status['label'],
			'errors'           => array_values(
				array_filter(
					$issues,
					static function ( $i ) {
						return 'error' === $i['severity'];
					}
				)
			),
			'warnings'         => array_values(
				array_filter(
					$issues,
					static function ( $i ) {
						return 'warning' === $i['severity'];
					}
				)
			),
			'suggestions'      => array_map(
				static function ( $i ) {
					return array(
						'code'   => $i['code'],
						'action' => $i['action'],
						'title'  => $i['title'],
					);
				},
				$issues
			),
			'successes'        => $successes,
			'focus_keyword'    => (string) ( $ctx['focus_keyword'] ?? '' ),
		);
	}

	/**
	 * Shared context for rules (one fetch).
	 *
	 * @param WP_Post $post Post.
	 * @return array<string,mixed>
	 */
	private function build_context( WP_Post $post ): array {
		$html   = (string) $post->post_content;
		$text   = wp_strip_all_tags( $html );
		$words  = preg_split( '/\s+/u', trim( $text ), -1, PREG_SPLIT_NO_EMPTY );
		$word_n = is_array( $words ) ? count( $words ) : 0;
		$h1_n   = preg_match_all( '/<h1\b[^>]*>/i', $html );
		$h2_n   = preg_match_all( '/<h2\b[^>]*>/i', $html );
		$h3_n   = preg_match_all( '/<h3\b[^>]*>/i', $html );
		$img_n  = preg_match_all( '/<img\b[^>]*>/i', $html, $img_tags );
		$img_tags = $img_tags[0] ?? array();
		$missing_alt = 0;
		foreach ( $img_tags as $tag ) {
			if ( ! preg_match( '/\balt\s*=\s*([\'"])(.*?)\1/i', $tag, $am ) || '' === trim( $am[2] ?? '' ) ) {
				++$missing_alt;
			}
		}

		$paras  = preg_split( '/<\/p>/i', $html );
		$long_p = 0;
		if ( is_array( $paras ) ) {
			foreach ( $paras as $p ) {
				$plen = mb_strlen( trim( wp_strip_all_tags( $p ) ), 'UTF-8' );
				if ( $plen > 400 ) {
					++$long_p;
				}
			}
		}

		$host = (string) wp_parse_url( home_url(), PHP_URL_HOST );
		$internal = 0;
		$external = 0;
		if ( preg_match_all( '/<a\s[^>]*href\s*=\s*[\'"]([^\'"]+)[\'"]/i', $html, $hrefs ) ) {
			foreach ( $hrefs[1] as $href ) {
				$href = trim( $href );
				if ( '' === $href || 0 === strpos( $href, '#' ) || 0 === stripos( $href, 'mailto:' ) || 0 === stripos( $href, 'tel:' ) ) {
					continue;
				}
				$h = (string) wp_parse_url( $href, PHP_URL_HOST );
				if ( '' === $h || 0 === strcasecmp( $h, $host ) ) {
					++$internal;
				} else {
					++$external;
				}
			}
		}

		$focus = $this->resolve_focus_keyword( $post );
		$density = 0.0;
		if ( $focus && $word_n > 0 ) {
			$needle = preg_quote( mb_strtolower( $focus, 'UTF-8' ), '/' );
			$count  = preg_match_all( '/' . $needle . '/u', mb_strtolower( $text, 'UTF-8' ) );
			$density = round( ( (int) $count / $word_n ) * 100, 2 );
		}

		$meta = $this->resolve_meta_description( $post );
		$incoming = 0;
		$broken_out = 0;
		if ( class_exists( 'Shojaei_SEO_Link_Genius' ) ) {
			global $wpdb;
			$inv = Shojaei_SEO_Link_Genius::inventory_table();
			$permalink = (string) get_permalink( $post->ID );
			$path = (string) wp_parse_url( $permalink, PHP_URL_PATH );
			if ( $path ) {
				$like = '%' . $wpdb->esc_like( untrailingslashit( $path ) ) . '%';
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$incoming = (int) $wpdb->get_var(
					$wpdb->prepare(
						"SELECT COUNT(*) FROM {$inv} WHERE link_type = 'internal' AND dest_url LIKE %s AND source_post_id != %d",
						$like,
						$post->ID
					)
				);
			}
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$broken_out = (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$inv} WHERE source_post_id = %d AND http_status >= 400",
					$post->ID
				)
			);
		}

		$noindex = ( 'yes' === get_post_meta( $post->ID, '_shojaei_seo_noindex', true ) );
		$rm_robots = get_post_meta( $post->ID, 'rank_math_robots', true );
		if ( is_array( $rm_robots ) && in_array( 'noindex', $rm_robots, true ) ) {
			$noindex = true;
		}
		if ( '1' === (string) get_post_meta( $post->ID, '_yoast_wpseo_meta-robots-noindex', true ) ) {
			$noindex = true;
		}

		$canonical = '';
		if ( function_exists( 'damavand_get_canonical_url' ) ) {
			$canonical = damavand_get_canonical_url( (int) $post->ID );
		} elseif ( class_exists( 'Damavand_SEO_Meta' ) ) {
			$canonical = Damavand_SEO_Meta::get_canonical( (int) $post->ID );
		} else {
			$canonical = (string) get_post_meta( $post->ID, 'rank_math_canonical_url', true );
			if ( '' === $canonical ) {
				$canonical = (string) get_post_meta( $post->ID, '_yoast_wpseo_canonical', true );
			}
		}

		return array(
			'html'             => $html,
			'text'             => $text,
			'word_count'       => $word_n,
			'h1_count'         => (int) $h1_n,
			'h2_count'         => (int) $h2_n,
			'h3_count'         => (int) $h3_n,
			'img_count'        => (int) $img_n,
			'missing_alt'     => $missing_alt,
			'long_paragraphs'  => $long_p,
			'meta_description' => $meta,
			'focus_keyword'    => $focus,
			'keyword_density'  => $density,
			'internal_links'   => $internal,
			'external_links'   => $external,
			'incoming'         => $incoming,
			'is_orphan'        => ( $incoming < 1 && 'page' !== $post->post_type ),
			'broken_out'       => $broken_out,
			'noindex'          => $noindex,
			'canonical'        => $canonical,
			'has_thumbnail'    => has_post_thumbnail( $post->ID ),
			'seo_title'        => $this->resolve_seo_title( $post ),
		);
	}

	/**
	 * عنوان سئو — اولویت Damavand سپس Rank Math / Yoast / عنوان نوشته.
	 *
	 * @param WP_Post $post Post.
	 */
	private function resolve_seo_title( WP_Post $post ): string {
		if ( class_exists( 'Damavand_SEO_Meta' ) ) {
			return Damavand_SEO_Meta::get_title( (int) $post->ID, true );
		}
		$candidates = array(
			(string) get_post_meta( $post->ID, 'rank_math_title', true ),
			(string) get_post_meta( $post->ID, '_yoast_wpseo_title', true ),
			(string) $post->post_title,
		);
		foreach ( $candidates as $c ) {
			$c = trim( wp_strip_all_tags( $c ) );
			$c = preg_replace( '/%[a-z0-9_]+%/i', '', $c );
			$c = trim( (string) $c );
			if ( '' !== $c ) {
				return $c;
			}
		}
		return '';
	}

	/**
	 * کلمه کلیدی هدف — اولویت Damavand.
	 *
	 * @param WP_Post $post Post.
	 */
	private function resolve_focus_keyword( WP_Post $post ): string {
		if ( class_exists( 'Damavand_SEO_Meta' ) ) {
			return Damavand_SEO_Meta::get_focus_keyword( (int) $post->ID );
		}
		$candidates = array(
			(string) get_post_meta( $post->ID, 'rank_math_focus_keyword', true ),
			(string) get_post_meta( $post->ID, '_yoast_wpseo_focuskw', true ),
			(string) get_post_meta( $post->ID, '_shojaei_seo_focus_keyword', true ),
		);
		foreach ( $candidates as $c ) {
			$parts = preg_split( '/\s*,\s*/', trim( $c ) );
			$first = is_array( $parts ) ? trim( (string) ( $parts[0] ?? '' ) ) : '';
			if ( '' !== $first ) {
				return $first;
			}
		}
		return '';
	}

	/**
	 * Meta description — اولویت Damavand سپس Rank Math / Yoast / excerpt.
	 *
	 * @param WP_Post $post Post.
	 */
	private function resolve_meta_description( WP_Post $post ): string {
		if ( class_exists( 'Damavand_SEO_Meta' ) ) {
			return Damavand_SEO_Meta::get_description( (int) $post->ID, true );
		}
		$candidates = array(
			(string) get_post_meta( $post->ID, 'rank_math_description', true ),
			(string) get_post_meta( $post->ID, '_yoast_wpseo_metadesc', true ),
			(string) $post->post_excerpt,
		);
		foreach ( $candidates as $c ) {
			$c = trim( wp_strip_all_tags( $c ) );
			if ( '' !== $c ) {
				return $c;
			}
		}
		return '';
	}

	/**
	 * Normalize issue shape.
	 *
	 * @param array $issue Raw.
	 * @return array<string,mixed>
	 */
	private function normalize_issue( array $issue ): array {
		$sev = (string) ( $issue['severity'] ?? 'warning' );
		// سازگاری با نسخه قبلی critical/info.
		if ( 'critical' === $sev ) {
			$sev = 'error';
		} elseif ( 'info' === $sev ) {
			$sev = 'warning';
		}
		if ( ! in_array( $sev, array( 'error', 'warning', 'success' ), true ) ) {
			$sev = 'warning';
		}
		return array(
			'code'     => (string) ( $issue['code'] ?? '' ),
			'layer'    => (string) ( $issue['layer'] ?? 'onpage' ),
			'severity'=> $sev,
			'title'    => (string) ( $issue['title'] ?? '' ),
			'why'      => (string) ( $issue['why'] ?? '' ),
			'action'   => (string) ( $issue['action'] ?? '' ),
			'points'   => (int) ( $issue['points'] ?? 5 ),
			'priority' => (string) ( $issue['priority'] ?? 'متوسط' ),
			'weight'   => (int) ( $issue['points'] ?? 5 ),
		);
	}

	/**
	 * وزن لایه‌ها — قابل فیلتر برای تنظیم شفاف امتیاز.
	 *
	 * @return array{onpage:float,content:float,technical:float,links:float}
	 */
	public function get_layer_weights(): array {
		$weights = array(
			'onpage'    => 0.30,
			'content'   => 0.30,
			'technical' => 0.20,
			'links'     => 0.20,
		);
		/**
		 * فیلتر وزن لایه‌های امتیاز نبض سئو (جمع باید ≈ ۱ باشد).
		 *
		 * @param array $weights Weights.
		 */
		$weights = apply_filters( 'shojaei_seo_pulse_layer_weights', $weights );
		return is_array( $weights ) ? $weights : array(
			'onpage'    => 0.30,
			'content'   => 0.30,
			'technical' => 0.20,
			'links'     => 0.20,
		);
	}

	/**
	 * Score layers from issues (start 100, subtract points).
	 *
	 * @param array $issues Issues.
	 * @return array{total:int,onpage:int,content:int,technical:int,links:int,formula:string}
	 */
	private function score_from_issues( array $issues ): array {
		$layers = array(
			'onpage'    => 100,
			'content'   => 100,
			'technical' => 100,
			'links'     => 100,
		);
		foreach ( $issues as $i ) {
			if ( 'success' === ( $i['severity'] ?? '' ) ) {
				continue;
			}
			$layer = $i['layer'] ?? 'onpage';
			if ( ! isset( $layers[ $layer ] ) ) {
				$layer = 'onpage';
			}
			$layers[ $layer ] = max( 0, $layers[ $layer ] - (int) $i['points'] );
		}
		$w = $this->get_layer_weights();
		$total = (int) round(
			$layers['onpage'] * (float) ( $w['onpage'] ?? 0.3 )
			+ $layers['content'] * (float) ( $w['content'] ?? 0.3 )
			+ $layers['technical'] * (float) ( $w['technical'] ?? 0.2 )
			+ $layers['links'] * (float) ( $w['links'] ?? 0.2 )
		);
		return array(
			'total'     => max( 0, min( 100, $total ) ),
			'onpage'    => $layers['onpage'],
			'content'   => $layers['content'],
			'technical' => $layers['technical'],
			'links'     => $layers['links'],
			'formula'   => sprintf(
				'onpage×%.2f + content×%.2f + technical×%.2f + links×%.2f',
				(float) ( $w['onpage'] ?? 0.3 ),
				(float) ( $w['content'] ?? 0.3 ),
				(float) ( $w['technical'] ?? 0.2 ),
				(float) ( $w['links'] ?? 0.2 )
			),
		);
	}

	/* ===================== Rules ===================== */

	/**
	 * Title length rule.
	 *
	 * @param WP_Post $post Post.
	 * @param array   $ctx  Context.
	 */
	public function rule_title( WP_Post $post, array $ctx ): ?array {
		$title = (string) ( $ctx['seo_title'] ?? $post->post_title );
		$len   = mb_strlen( trim( $title ), 'UTF-8' );
		if ( $len < 1 ) {
			return array(
				'code'     => 'title_missing',
				'layer'    => 'onpage',
				'severity'=> 'error',
				'title'    => __( 'عنوان صفحه خالی است', 'shojaei-seo-for-woo' ),
				'why'      => __( 'بدون عنوان، گوگل و کاربر نمی‌فهمند صفحه درباره چیست.', 'shojaei-seo-for-woo' ),
				'action'   => __( 'یک عنوان واضح و جذاب بین ۳۰ تا ۶۰ کاراکتر بنویسید.', 'shojaei-seo-for-woo' ),
				'points'   => 20,
				'priority' => __( 'بحرانی', 'shojaei-seo-for-woo' ),
			);
		}
		if ( $len < 20 ) {
			return array(
				'code'     => 'title_short',
				'layer'    => 'onpage',
				'severity'=> 'warning',
				'title'    => __( 'عنوان خیلی کوتاه است', 'shojaei-seo-for-woo' ),
				'why'      => __( 'عنوان کوتاه معمولاً اطلاعات کافی برای کلیک و رتبه ندارد.', 'shojaei-seo-for-woo' ),
				'action'   => __( 'عنوان را به حدود ۳۰–۶۰ کاراکتر برسانید و کلمه کلیدی اصلی را بگنجانید.', 'shojaei-seo-for-woo' ),
				'points'   => 8,
				'priority' => __( 'متوسط', 'shojaei-seo-for-woo' ),
			);
		}
		if ( $len > 70 ) {
			return array(
				'code'     => 'title_long',
				'layer'    => 'onpage',
				'severity'=> 'warning',
				'title'    => __( 'عنوان خیلی طولانی است', 'shojaei-seo-for-woo' ),
				'why'      => __( 'در نتایج گوگل بریده می‌شود و پیام ناقص می‌ماند.', 'shojaei-seo-for-woo' ),
				'action'   => __( 'عنوان را کوتاه‌تر کنید (ترجیحاً زیر ۶۰–۶۵ کاراکتر).', 'shojaei-seo-for-woo' ),
				'points'   => 6,
				'priority' => __( 'کم', 'shojaei-seo-for-woo' ),
			);
		}
		return array(
			'code'     => 'title_ok',
			'layer'    => 'onpage',
			'severity'=> 'success',
			'title'    => __( 'طول عنوان مناسب است', 'shojaei-seo-for-woo' ),
			'why'      => '',
			'action'   => '',
			'points'   => 0,
			'priority' => '',
		);
	}

	/**
	 * وجود کلمه کلیدی هدف در عنوان سئو.
	 *
	 * @param WP_Post $post Post.
	 * @param array   $ctx  Context.
	 */
	public function rule_title_focus_keyword( WP_Post $post, array $ctx ): ?array {
		$focus = (string) ( $ctx['focus_keyword'] ?? '' );
		if ( '' === $focus ) {
			return array(
				'code'     => 'focus_keyword_missing',
				'layer'    => 'onpage',
				'severity'=> 'warning',
				'title'    => __( 'کلمه کلیدی هدف تعریف نشده', 'shojaei-seo-for-woo' ),
				'why'      => __( 'بدون کلمه کلیدی هدف، ارزیابی عنوان و چگالی دقیق نیست.', 'shojaei-seo-for-woo' ),
				'action'   => __( 'در Rank Math/Yoast یا متای نوشته یک Focus Keyword تنظیم کنید.', 'shojaei-seo-for-woo' ),
				'points'   => 5,
				'priority' => __( 'متوسط', 'shojaei-seo-for-woo' ),
			);
		}
		$title = mb_strtolower( (string) ( $ctx['seo_title'] ?? $post->post_title ), 'UTF-8' );
		$fk    = mb_strtolower( $focus, 'UTF-8' );
		if ( false === mb_strpos( $title, $fk, 0, 'UTF-8' ) ) {
			return array(
				'code'     => 'title_missing_focus',
				'layer'    => 'onpage',
				'severity'=> 'warning',
				'title'    => __( 'کلمه کلیدی هدف در عنوان نیست', 'shojaei-seo-for-woo' ),
				'why'      => __( 'وجود کلمه کلیدی در عنوان به ارتباط موضوعی صفحه کمک می‌کند.', 'shojaei-seo-for-woo' ),
				'action'   => sprintf(
					/* translators: %s: keyword */
					__( 'کلمه «%s» را به‌صورت طبیعی در ابتدای عنوان بگنجانید.', 'shojaei-seo-for-woo' ),
					$focus
				),
				'points'   => 8,
				'priority' => __( 'بالا', 'shojaei-seo-for-woo' ),
			);
		}
		return array(
			'code'     => 'title_has_focus',
			'layer'    => 'onpage',
			'severity'=> 'success',
			'title'    => __( 'کلمه کلیدی در عنوان وجود دارد', 'shojaei-seo-for-woo' ),
			'why'      => '',
			'action'   => '',
			'points'   => 0,
			'priority' => '',
		);
	}

	/**
	 * Meta description rule.
	 *
	 * @param WP_Post $post Post.
	 * @param array   $ctx  Context.
	 */
	public function rule_meta_description( WP_Post $post, array $ctx ): ?array {
		$meta = (string) ( $ctx['meta_description'] ?? '' );
		$len  = mb_strlen( $meta, 'UTF-8' );
		if ( $len < 1 ) {
			return array(
				'code'     => 'meta_missing',
				'layer'    => 'onpage',
				'severity'=> 'error',
				'title'    => __( 'توضیح متا (Meta Description) وجود ندارد', 'shojaei-seo-for-woo' ),
				'why'      => __( 'اسنیپت نتایج جستجو ضعیف می‌شود و نرخ کلیک پایین می‌آید.', 'shojaei-seo-for-woo' ),
				'action'   => __( 'یک توضیح ۱۴۰ تا ۱۵۵ کاراکتری بنویسید که ارزش صفحه را بگوید و دعوت به کلیک داشته باشد.', 'shojaei-seo-for-woo' ),
				'points'   => 10,
				'priority' => __( 'بالا', 'shojaei-seo-for-woo' ),
			);
		}
		if ( $len < 70 ) {
			return array(
				'code'     => 'meta_short',
				'layer'    => 'onpage',
				'severity'=> 'warning',
				'title'    => __( 'توضیح متا کوتاه است', 'shojaei-seo-for-woo' ),
				'why'      => __( 'فضای اسنیپت کامل استفاده نمی‌شود.', 'shojaei-seo-for-woo' ),
				'action'   => __( 'توضیح را به حدود ۱۴۰–۱۵۵ کاراکتر برسانید.', 'shojaei-seo-for-woo' ),
				'points'   => 5,
				'priority' => __( 'متوسط', 'shojaei-seo-for-woo' ),
			);
		}
		if ( $len > 170 ) {
			return array(
				'code'     => 'meta_long',
				'layer'    => 'onpage',
				'severity'=> 'info',
				'title'    => __( 'توضیح متا طولانی است', 'shojaei-seo-for-woo' ),
				'why'      => __( 'ممکن است در نتایج بریده شود.', 'shojaei-seo-for-woo' ),
				'action'   => __( 'توضیح را کمی کوتاه‌تر کنید (حدود ۱۵۵ کاراکتر).', 'shojaei-seo-for-woo' ),
				'points'   => 3,
				'priority' => __( 'کم', 'shojaei-seo-for-woo' ),
			);
		}
		return null;
	}

	/**
	 * Sample / core H1 rule.
	 *
	 * @param WP_Post $post Post.
	 * @param array   $ctx  Context.
	 */
	public function rule_h1( WP_Post $post, array $ctx ): ?array {
		$count = (int) ( $ctx['h1_count'] ?? 0 );
		// Many themes print title as H1 outside content — if content has 0, soft as info not critical.
		if ( 0 === $count ) {
			return array(
				'code'     => 'h1_missing_in_content',
				'layer'    => 'onpage',
				'severity'=> 'warning',
				'title'    => __( 'در محتوای نوشته تگ H1 دیده نشد', 'shojaei-seo-for-woo' ),
				'why'      => __( 'H1 ساختار صفحه را برای کاربر و موتور جستجو روشن می‌کند. گاهی قالب عنوان را به‌عنوان H1 چاپ می‌کند؛ اگر در پیش‌نمایش صفحه H1 دارید، این مورد را نادیده بگیرید.', 'shojaei-seo-for-woo' ),
				'action'   => __( 'مطمئن شوید صفحه دقیقاً یک H1 واضح دارد (ترجیحاً نزدیک به عنوان و کلمه کلیدی).', 'shojaei-seo-for-woo' ),
				'points'   => 8,
				'priority' => __( 'متوسط', 'shojaei-seo-for-woo' ),
			);
		}
		if ( $count > 1 ) {
			return array(
				'code'     => 'h1_multiple',
				'layer'    => 'onpage',
				'severity'=> 'warning',
				'title'    => __( 'بیش از یک H1 در محتوا', 'shojaei-seo-for-woo' ),
				'why'      => __( 'چند H1 سلسله‌مراتب را مبهم می‌کند.', 'shojaei-seo-for-woo' ),
				'action'   => __( 'فقط یک H1 نگه دارید و بقیه را به H2/H3 تبدیل کنید.', 'shojaei-seo-for-woo' ),
				'points'   => 7,
				'priority' => __( 'متوسط', 'shojaei-seo-for-woo' ),
			);
		}
		return null;
	}

	/**
	 * Slug quality (reuse Damavand helpers when available).
	 *
	 * @param WP_Post $post Post.
	 * @param array   $ctx  Context.
	 */
	public function rule_slug( WP_Post $post, array $ctx ): ?array {
		$slug = (string) $post->post_name;
		$persian = class_exists( 'Shojaei_SEO_Slug' )
			? Shojaei_SEO_Slug::has_persian( $slug ) || Shojaei_SEO_Slug::has_persian( rawurldecode( $slug ) )
			: (bool) preg_match( '/[\x{0600}-\x{06FF}]/u', rawurldecode( $slug ) );
		if ( $persian ) {
			return array(
				'code'     => 'slug_persian',
				'layer'    => 'onpage',
				'severity'=> 'warning',
				'title'    => __( 'نامک (URL) فارسی است', 'shojaei-seo-for-woo' ),
				'why'      => __( 'نامک لاتین/فینگلیش پایدارتر، کوتاه‌تر و برای اشتراک‌گذاری بهتر است.', 'shojaei-seo-for-woo' ),
				'action'   => __( 'از تب نامک دماوند پیشنهاد فینگلیش را اعمال کنید و ۳۰۱ بسازید.', 'shojaei-seo-for-woo' ),
				'points'   => 8,
				'priority' => __( 'متوسط', 'shojaei-seo-for-woo' ),
			);
		}
		if ( strlen( rawurldecode( $slug ) ) > 60 ) {
			return array(
				'code'     => 'slug_long',
				'layer'    => 'onpage',
				'severity'=> 'info',
				'title'    => __( 'نامک خیلی طولانی است', 'shojaei-seo-for-woo' ),
				'why'      => __( 'URLهای بلند کمتر خوانا و کمتر قابل اشتراک هستند.', 'shojaei-seo-for-woo' ),
				'action'   => __( 'نامک را کوتاه و توصیفی کنید (ترجیحاً زیر ۶۰ کاراکتر).', 'shojaei-seo-for-woo' ),
				'points'   => 4,
				'priority' => __( 'کم', 'shojaei-seo-for-woo' ),
			);
		}
		return null;
	}

	/**
	 * Content length.
	 *
	 * @param WP_Post $post Post.
	 * @param array   $ctx  Context.
	 */
	public function rule_content_length( WP_Post $post, array $ctx ): ?array {
		$n = (int) ( $ctx['word_count'] ?? 0 );
		if ( $n < 100 ) {
			return array(
				'code'     => 'content_thin',
				'layer'    => 'content',
				'severity'=> 'error',
				'title'    => __( 'محتوا خیلی کم‌حجم است', 'shojaei-seo-for-woo' ),
				'why'      => __( 'صفحات کم‌محتوا معمولاً ارزش جستجوی پایینی دارند و رقابت را نمی‌برند.', 'shojaei-seo-for-woo' ),
				'action'   => __( 'حداقل ۳۰۰ کلمه محتوای مفید، با زیرعنوان و مثال اضافه کنید.', 'shojaei-seo-for-woo' ),
				'points'   => 15,
				'priority' => __( 'بالا', 'shojaei-seo-for-woo' ),
			);
		}
		if ( $n < 250 ) {
			return array(
				'code'     => 'content_short',
				'layer'    => 'content',
				'severity'=> 'warning',
				'title'    => __( 'محتوا کوتاه است', 'shojaei-seo-for-woo' ),
				'why'      => __( 'برای پوشش موضوع معمولاً به عمق بیشتری نیاز است.', 'shojaei-seo-for-woo' ),
				'action'   => __( 'متن را غنی‌تر کنید: پاسخ به سوالات کاربر، مقایسه، نکات کاربردی.', 'shojaei-seo-for-woo' ),
				'points'   => 8,
				'priority' => __( 'متوسط', 'shojaei-seo-for-woo' ),
			);
		}
		return null;
	}

	/**
	 * Media usage.
	 *
	 * @param WP_Post $post Post.
	 * @param array   $ctx  Context.
	 */
	public function rule_media( WP_Post $post, array $ctx ): ?array {
		$imgs = (int) ( $ctx['img_count'] ?? 0 );
		$thumb = ! empty( $ctx['has_thumbnail'] );
		if ( $imgs < 1 && ! $thumb ) {
			return array(
				'code'     => 'no_media',
				'layer'    => 'content',
				'severity'=> 'warning',
				'title'    => __( 'تصویر در محتوا یا تصویر شاخص نیست', 'shojaei-seo-for-woo' ),
				'why'      => __( 'رسانه ماندگاری کاربر و اشتراک‌گذاری را بهتر می‌کند و برای OpenGraph مهم است.', 'shojaei-seo-for-woo' ),
				'action'   => __( 'حداقل یک تصویر مرتبط + تصویر شاخص تنظیم کنید؛ alt توصیفی بنویسید.', 'shojaei-seo-for-woo' ),
				'points'   => 7,
				'priority' => __( 'متوسط', 'shojaei-seo-for-woo' ),
			);
		}
		return null;
	}

	/**
	 * Long paragraphs readability.
	 *
	 * @param WP_Post $post Post.
	 * @param array   $ctx  Context.
	 */
	public function rule_paragraphs( WP_Post $post, array $ctx ): ?array {
		$long = (int) ( $ctx['long_paragraphs'] ?? 0 );
		if ( $long >= 3 ) {
			return array(
				'code'     => 'paragraphs_long',
				'layer'    => 'content',
				'severity'=> 'info',
				'title'    => __( 'چند پاراگراف خیلی بلند دارید', 'shojaei-seo-for-woo' ),
				'why'      => __( 'خوانایی موبایل و اسکن سریع متن ضعیف می‌شود.', 'shojaei-seo-for-woo' ),
				'action'   => __( 'پاراگراف‌های بلند را بشکنید و از لیست/زیرعنوان استفاده کنید.', 'shojaei-seo-for-woo' ),
				'points'   => 4,
				'priority' => __( 'کم', 'shojaei-seo-for-woo' ),
			);
		}
		return null;
	}

	/**
	 * Product/post noindex flag from Damavand OOS.
	 *
	 * @param WP_Post $post Post.
	 * @param array   $ctx  Context.
	 */
	public function rule_noindex_flag( WP_Post $post, array $ctx ): ?array {
		if ( empty( $ctx['noindex'] ) ) {
			return null;
		}
		return array(
			'code'     => 'noindex_set',
			'layer'    => 'technical',
			'severity'=> 'info',
			'title'    => __( 'این صفحه noindex شده است', 'shojaei-seo-for-woo' ),
			'why'      => __( 'صفحه از نتایج جستجو کنار گذاشته می‌شود — گاهی عمدی (مثل ناموجود طولانی) است.', 'shojaei-seo-for-woo' ),
			'action'   => __( 'اگر باید ایندکس شود، فلگ noindex را در تنظیمات موجودی/متای عمومی بررسی کنید.', 'shojaei-seo-for-woo' ),
			'points'   => 5,
			'priority' => __( 'وابسته به هدف', 'shojaei-seo-for-woo' ),
		);
	}

	/**
	 * Schema presence hint (lightweight).
	 *
	 * @param WP_Post $post Post.
	 * @param array   $ctx  Context.
	 */
	public function rule_schema_hint( WP_Post $post, array $ctx ): ?array {
		$html = (string) ( $ctx['html'] ?? '' );
		if ( false !== stripos( $html, 'application/ld+json' ) ) {
			return null;
		}
		// Not critical — theme/plugins may inject in footer.
		if ( 'product' === $post->post_type ) {
			return array(
				'code'     => 'schema_not_in_content',
				'layer'    => 'technical',
				'severity'=> 'info',
				'title'    => __( 'داده ساختاریافته در خود محتوا دیده نشد', 'shojaei-seo-for-woo' ),
				'why'      => __( 'اسکیما می‌تواند توسط قالب یا Rank Math در فوتر تزریق شود؛ این فقط یک هشدار سبک است.', 'shojaei-seo-for-woo' ),
				'action'   => __( 'در تنظیمات اسکیما/Rank Math وجود Product schema را تأیید کنید.', 'shojaei-seo-for-woo' ),
				'points'   => 3,
				'priority' => __( 'کم', 'shojaei-seo-for-woo' ),
			);
		}
		return null;
	}

	/**
	 * Orphan detection.
	 *
	 * @param WP_Post $post Post.
	 * @param array   $ctx  Context.
	 */
	public function rule_orphan( WP_Post $post, array $ctx ): ?array {
		if ( empty( $ctx['is_orphan'] ) ) {
			return null;
		}
		// Need inventory data; if table empty, skip false positives.
		if ( class_exists( 'Shojaei_SEO_Link_Genius' ) ) {
			$counts = Shojaei_SEO_Link_Genius::inventory_counts();
			if ( (int) ( $counts['total'] ?? 0 ) < 1 ) {
				return null;
			}
		} else {
			return null;
		}
		return array(
			'code'     => 'orphan_post',
			'layer'    => 'links',
			'severity'=> 'error',
			'title'    => __( 'نوشته یتیم — لینک ورودی داخلی ندارد', 'shojaei-seo-for-woo' ),
			'why'      => __( 'بدون لینک داخلی، خزنده و کاربر سخت‌تر به صفحه می‌رسند و اعتبار لینک منتقل نمی‌شود.', 'shojaei-seo-for-woo' ),
			'action'   => __( 'دکمه «پیشنهاد لینک ورودی» را بزنید، مبدأها را تأیید کنید تا نقشه کلمات ساخته شود.', 'shojaei-seo-for-woo' ),
			'points'   => 15,
			'priority' => __( 'بالا', 'shojaei-seo-for-woo' ),
		);
	}

	/**
	 * Broken outbound links from this post.
	 *
	 * @param WP_Post $post Post.
	 * @param array   $ctx  Context.
	 */
	public function rule_outbound_broken( WP_Post $post, array $ctx ): ?array {
		$n = (int) ( $ctx['broken_out'] ?? 0 );
		if ( $n < 1 ) {
			return null;
		}
		return array(
			'code'     => 'broken_outbound',
			'layer'    => 'links',
			'severity'=> 'error',
			'title'    => sprintf(
				/* translators: %d: count */
				__( '%d لینک خروجی شکسته در این صفحه', 'shojaei-seo-for-woo' ),
				$n
			),
			'why'      => __( 'لینک شکسته تجربه کاربر و اعتماد موتور جستجو را خراب می‌کند.', 'shojaei-seo-for-woo' ),
			'action'   => __( 'در «نابغه لینک → نگهبان لینک» فیلتر شکسته را ببینید و «حذف از متن» یا «به‌روز URL» بزنید.', 'shojaei-seo-for-woo' ),
			'points'   => min( 20, 5 * $n ),
			'priority' => __( 'بالا', 'shojaei-seo-for-woo' ),
		);
	}

	/**
	 * ساختار H2/H3.
	 *
	 * @param WP_Post $post Post.
	 * @param array   $ctx  Context.
	 */
	public function rule_heading_structure( WP_Post $post, array $ctx ): ?array {
		$words = (int) ( $ctx['word_count'] ?? 0 );
		$h2    = (int) ( $ctx['h2_count'] ?? 0 );
		$h3    = (int) ( $ctx['h3_count'] ?? 0 );
		if ( $words < 300 ) {
			return null;
		}
		if ( $h2 < 1 ) {
			return array(
				'code'     => 'missing_h2',
				'layer'    => 'onpage',
				'severity'=> 'warning',
				'title'    => __( 'زیرعنوان H2 در محتوا نیست', 'shojaei-seo-for-woo' ),
				'why'      => __( 'H2 ساختار موضوع را روشن می‌کند و خوانایی را بالا می‌برد.', 'shojaei-seo-for-woo' ),
				'action'   => __( 'حداقل ۲–۳ زیرعنوان H2 برای بخش‌های اصلی اضافه کنید؛ در صورت نیاز از H3 استفاده کنید.', 'shojaei-seo-for-woo' ),
				'points'   => 7,
				'priority' => __( 'متوسط', 'shojaei-seo-for-woo' ),
			);
		}
		if ( $words > 800 && $h2 + $h3 < 3 ) {
			return array(
				'code'     => 'thin_heading_structure',
				'layer'    => 'onpage',
				'severity'=> 'warning',
				'title'    => __( 'ساختار تیترها برای محتوای بلند کافی نیست', 'shojaei-seo-for-woo' ),
				'why'      => __( 'محتوای بلند بدون تیتر میانی سخت‌خوان می‌شود.', 'shojaei-seo-for-woo' ),
				'action'   => __( 'با H2/H3 بخش‌بندی کنید تا اسکن سریع ممکن شود.', 'shojaei-seo-for-woo' ),
				'points'   => 5,
				'priority' => __( 'کم', 'shojaei-seo-for-woo' ),
			);
		}
		return array(
			'code'     => 'heading_structure_ok',
			'layer'    => 'onpage',
			'severity'=> 'success',
			'title'    => __( 'ساختار H2/H3 قابل قبول است', 'shojaei-seo-for-woo' ),
			'why'      => '',
			'action'   => '',
			'points'   => 0,
			'priority' => '',
		);
	}

	/**
	 * تصویر شاخص.
	 *
	 * @param WP_Post $post Post.
	 * @param array   $ctx  Context.
	 */
	public function rule_featured_image( WP_Post $post, array $ctx ): ?array {
		if ( ! empty( $ctx['has_thumbnail'] ) ) {
			return array(
				'code'     => 'featured_image_ok',
				'layer'    => 'content',
				'severity'=> 'success',
				'title'    => __( 'تصویر شاخص تنظیم شده', 'shojaei-seo-for-woo' ),
				'why'      => '',
				'action'   => '',
				'points'   => 0,
				'priority' => '',
			);
		}
		return array(
			'code'     => 'featured_image_missing',
			'layer'    => 'content',
			'severity'=> 'warning',
			'title'    => __( 'تصویر شاخص وجود ندارد', 'shojaei-seo-for-woo' ),
			'why'      => __( 'تصویر شاخص برای شبکه‌های اجتماعی، لیست نوشته‌ها و OpenGraph مهم است.', 'shojaei-seo-for-woo' ),
			'action'   => __( 'یک تصویر شاخص مرتبط با موضوع تنظیم کنید.', 'shojaei-seo-for-woo' ),
			'points'   => 6,
			'priority' => __( 'متوسط', 'shojaei-seo-for-woo' ),
		);
	}

	/**
	 * alt تصاویر داخل محتوا.
	 *
	 * @param WP_Post $post Post.
	 * @param array   $ctx  Context.
	 */
	public function rule_image_alt( WP_Post $post, array $ctx ): ?array {
		$imgs = (int) ( $ctx['img_count'] ?? 0 );
		$miss = (int) ( $ctx['missing_alt'] ?? 0 );
		if ( $imgs < 1 ) {
			return null;
		}
		if ( $miss < 1 ) {
			return array(
				'code'     => 'image_alt_ok',
				'layer'    => 'content',
				'severity'=> 'success',
				'title'    => __( 'همه تصاویر محتوا alt دارند', 'shojaei-seo-for-woo' ),
				'why'      => '',
				'action'   => '',
				'points'   => 0,
				'priority' => '',
			);
		}
		return array(
			'code'     => 'image_alt_missing',
			'layer'    => 'content',
			'severity'=> 'warning',
			'title'    => sprintf(
				/* translators: %d: count */
				__( '%d تصویر بدون متن جایگزین (alt)', 'shojaei-seo-for-woo' ),
				$miss
			),
			'why'      => __( 'alt برای دسترس‌پذیری و درک تصویر توسط موتور جستجو مهم است.', 'shojaei-seo-for-woo' ),
			'action'   => __( 'برای هر تصویر یک alt کوتاه و توصیفی بنویسید (بدون پر کردن کلمه کلیدی).', 'shojaei-seo-for-woo' ),
			'points'   => min( 12, 3 * $miss ),
			'priority' => __( 'متوسط', 'shojaei-seo-for-woo' ),
		);
	}

	/**
	 * چگالی / تکرار کلمه کلیدی.
	 *
	 * @param WP_Post $post Post.
	 * @param array   $ctx  Context.
	 */
	public function rule_keyword_density( WP_Post $post, array $ctx ): ?array {
		$focus = (string) ( $ctx['focus_keyword'] ?? '' );
		if ( '' === $focus || (int) ( $ctx['word_count'] ?? 0 ) < 50 ) {
			return null;
		}
		$d = (float) ( $ctx['keyword_density'] ?? 0 );
		if ( $d <= 0 ) {
			return array(
				'code'     => 'keyword_absent_in_body',
				'layer'    => 'content',
				'severity'=> 'warning',
				'title'    => __( 'کلمه کلیدی در متن اصلی دیده نشد', 'shojaei-seo-for-woo' ),
				'why'      => __( 'بدون اشاره طبیعی به موضوع، ارتباط محتوا با کلمه هدف ضعیف می‌شود.', 'shojaei-seo-for-woo' ),
				'action'   => __( 'کلمه کلیدی را ۲–۳ بار به‌صورت طبیعی در مقدمه و تیترها بیاورید.', 'shojaei-seo-for-woo' ),
				'points'   => 7,
				'priority' => __( 'متوسط', 'shojaei-seo-for-woo' ),
			);
		}
		if ( $d > 3.5 ) {
			return array(
				'code'     => 'keyword_stuffing',
				'layer'    => 'content',
				'severity'=> 'error',
				'title'    => __( 'تکرار بیش از حد کلمه کلیدی (Keyword Stuffing)', 'shojaei-seo-for-woo' ),
				'why'      => __( 'چگالی خیلی بالا نشانه بهینه‌سازی غیرطبیعی است و می‌تواند به خوانایی آسیب بزند.', 'shojaei-seo-for-woo' ),
				'action'   => sprintf(
					/* translators: %s: density */
					__( 'چگالی فعلی حدود %s٪ است؛ به حدود ۰٫۵–۲٫۵٪ برسانید و مترادف استفاده کنید.', 'shojaei-seo-for-woo' ),
					(string) $d
				),
				'points'   => 12,
				'priority' => __( 'بالا', 'shojaei-seo-for-woo' ),
			);
		}
		return array(
			'code'     => 'keyword_density_ok',
			'layer'    => 'content',
			'severity'=> 'success',
			'title'    => __( 'چگالی کلمه کلیدی متعادل است', 'shojaei-seo-for-woo' ),
			'why'      => '',
			'action'   => '',
			'points'   => 0,
			'priority' => '',
		);
	}

	/**
	 * Canonical.
	 *
	 * @param WP_Post $post Post.
	 * @param array   $ctx  Context.
	 */
	public function rule_canonical( WP_Post $post, array $ctx ): ?array {
		$canon = trim( (string) ( $ctx['canonical'] ?? '' ) );
		$self  = (string) get_permalink( $post->ID );
		if ( '' === $canon ) {
			// وردپرس معمولاً canonical پیش‌فرض دارد — موفقیت نرم.
			return array(
				'code'     => 'canonical_default',
				'layer'    => 'technical',
				'severity'=> 'success',
				'title'    => __( 'Canonical سفارشی تنظیم نشده (پیش‌فرض وردپرس)', 'shojaei-seo-for-woo' ),
				'why'      => '',
				'action'   => '',
				'points'   => 0,
				'priority' => '',
			);
		}
		$canon_n = untrailingslashit( esc_url_raw( $canon ) );
		$self_n  = untrailingslashit( esc_url_raw( $self ) );
		if ( $canon_n && $self_n && 0 !== strcasecmp( $canon_n, $self_n ) ) {
			return array(
				'code'     => 'canonical_points_elsewhere',
				'layer'    => 'technical',
				'severity'=> 'warning',
				'title'    => __( 'Canonical به آدرس دیگری اشاره می‌کند', 'shojaei-seo-for-woo' ),
				'why'      => __( 'اگر عمدی نباشد، سیگنال ایندکس به URL دیگر منتقل می‌شود.', 'shojaei-seo-for-woo' ),
				'action'   => __( 'در Rank Math/Yoast مطمئن شوید Canonical عمدی و صحیح است.', 'shojaei-seo-for-woo' ),
				'points'   => 6,
				'priority' => __( 'متوسط', 'shojaei-seo-for-woo' ),
			);
		}
		return array(
			'code'     => 'canonical_ok',
			'layer'    => 'technical',
			'severity'=> 'success',
			'title'    => __( 'Canonical با آدرس صفحه هم‌خوان است', 'shojaei-seo-for-woo' ),
			'why'      => '',
			'action'   => '',
			'points'   => 0,
			'priority' => '',
		);
	}

	/**
	 * لینک‌های داخلی در محتوا.
	 *
	 * @param WP_Post $post Post.
	 * @param array   $ctx  Context.
	 */
	public function rule_internal_links( WP_Post $post, array $ctx ): ?array {
		$n     = (int) ( $ctx['internal_links'] ?? 0 );
		$words = (int) ( $ctx['word_count'] ?? 0 );
		if ( $words < 150 ) {
			return null;
		}
		if ( $n < 1 ) {
			return array(
				'code'     => 'no_internal_links',
				'layer'    => 'links',
				'severity'=> 'warning',
				'title'    => __( 'لینک داخلی در محتوا نیست', 'shojaei-seo-for-woo' ),
				'why'      => __( 'لینک داخلی به کشف صفحات و انتقال اعتبار کمک می‌کند.', 'shojaei-seo-for-woo' ),
				'action'   => __( 'حداقل ۲ لینک داخلی مرتبط به نوشته‌ها/محصولات دیگر اضافه کنید.', 'shojaei-seo-for-woo' ),
				'points'   => 8,
				'priority' => __( 'متوسط', 'shojaei-seo-for-woo' ),
			);
		}
		return array(
			'code'     => 'internal_links_ok',
			'layer'    => 'links',
			'severity'=> 'success',
			'title'    => sprintf(
				/* translators: %d: count */
				__( '%d لینک داخلی در محتوا', 'shojaei-seo-for-woo' ),
				$n
			),
			'why'      => '',
			'action'   => '',
			'points'   => 0,
			'priority' => '',
		);
	}

	/**
	 * لینک‌های خارجی (اختیاری — فقط هشدار اگر زیاد باشد).
	 *
	 * @param WP_Post $post Post.
	 * @param array   $ctx  Context.
	 */
	public function rule_external_links( WP_Post $post, array $ctx ): ?array {
		$n = (int) ( $ctx['external_links'] ?? 0 );
		if ( $n > 15 ) {
			return array(
				'code'     => 'too_many_external_links',
				'layer'    => 'links',
				'severity'=> 'warning',
				'title'    => __( 'تعداد لینک خارجی زیاد است', 'shojaei-seo-for-woo' ),
				'why'      => __( 'لینک خارجی زیاد می‌تواند حواس کاربر را پرت کند و سیگنال را پخش کند.', 'shojaei-seo-for-woo' ),
				'action'   => __( 'لینک‌های خارجی را به منابع معتبر محدود کنید؛ در صورت نیاز rel="nofollow" بگذارید.', 'shojaei-seo-for-woo' ),
				'points'   => 4,
				'priority' => __( 'کم', 'shojaei-seo-for-woo' ),
			);
		}
		if ( $n >= 1 ) {
			return array(
				'code'     => 'external_links_present',
				'layer'    => 'links',
				'severity'=> 'success',
				'title'    => sprintf(
					/* translators: %d: count */
					__( '%d لینک خارجی در محتوا', 'shojaei-seo-for-woo' ),
					$n
				),
				'why'      => '',
				'action'   => '',
				'points'   => 0,
				'priority' => '',
			);
		}
		return null;
	}
}
