<?php
/**
 * Dry-Run simulation + Revert Log (rollback) engine.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_Revert_Log
 *
 * First-class Undo: every SEO-affecting apply must be batch_id + before/after + previewable rollback.
 */
class Shojaei_SEO_Revert_Log {

	/** Actions that support real undo. */
	public const UNDOABLE = array(
		'redirect_301',
		'redirect_302',
		'redirect_410',
		'keep_page',
		'auto_redirect',
		'undo_redirect',
		'link_build',
		'set_noindex',
		'clear_noindex',
		'sitemap_exclude',
		'sitemap_include',
		'link_deprioritize',
		'link_reprioritize',
	);

	/**
	 * Table name.
	 */
	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'shojaei_seo_revert_log';
	}

	/**
	 * Whether an action can be undone from before_state.
	 *
	 * @param string $action Action slug.
	 */
	public static function is_undoable( string $action ): bool {
		return in_array( $action, self::UNDOABLE, true );
	}

	/**
	 * Principle: bulk/auto apply only when Undo is available (or Dry-Run is on).
	 *
	 * @param string $action Action that would be applied.
	 */
	public static function can_auto_apply( string $action ): bool {
		if ( 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_dry_run', 'yes' ) ) {
			return false; // Dry-Run means do not auto-apply.
		}
		return self::is_undoable( $action );
	}

	/**
	 * Create a new batch id.
	 */
	public static function new_batch_id(): string {
		if ( function_exists( 'wp_generate_uuid4' ) ) {
			return wp_generate_uuid4();
		}
		return uniqid( 'batch_', true );
	}

	/**
	 * Record a change (applied or dry-run).
	 *
	 * @param array $args Arguments.
	 * @return int Insert ID.
	 */
	public static function record( array $args ): int {
		global $wpdb;

		$wpdb->insert(
			self::table(),
			array(
				'batch_id'     => sanitize_text_field( $args['batch_id'] ?? self::new_batch_id() ),
				'mode'         => in_array( ( $args['mode'] ?? 'applied' ), array( 'applied', 'dry_run' ), true ) ? $args['mode'] : 'applied',
				'action'       => sanitize_key( $args['action'] ?? 'change' ),
				'entity_type'  => sanitize_key( $args['entity_type'] ?? 'product' ),
				'entity_id'    => absint( $args['entity_id'] ?? 0 ),
				'summary'      => wp_strip_all_tags( (string) ( $args['summary'] ?? '' ) ),
				'before_state' => wp_json_encode( $args['before'] ?? array(), JSON_UNESCAPED_UNICODE ),
				'after_state'  => wp_json_encode( $args['after'] ?? array(), JSON_UNESCAPED_UNICODE ),
				'is_reverted'  => 0,
				'user_id'      => get_current_user_id(),
				'created_at'   => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%d', '%d', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Snapshot OOS tracker row for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return array
	 */
	public static function snapshot_oos( int $product_id ): array {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT product_id, oos_date, days_oos, status, redirect_type, target_url FROM ' . Shojaei_SEO_Helpers::oos_table() . ' WHERE product_id = %d',
				$product_id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return array( 'exists' => false, 'product_id' => $product_id );
		}

		$row['exists']    = true;
		$row['protected'] = class_exists( 'Shojaei_SEO_Page_Value' )
			? ( Shojaei_SEO_Page_Value::is_protected( $product_id ) ? 'yes' : 'no' )
			: 'no';

		return $row;
	}

	/**
	 * Human label for action.
	 *
	 * @param string $action Action slug.
	 */
	public static function action_label( string $action ): string {
		$map = array(
			'redirect_301'      => __( 'ریدایرکت ۳۰۱', 'shojaei-seo-for-woo' ),
			'redirect_302'      => __( 'ریدایرکت ۳۰۲', 'shojaei-seo-for-woo' ),
			'redirect_410'      => __( '410 Gone', 'shojaei-seo-for-woo' ),
			'keep_page'         => __( 'نگهداری صفحه', 'shojaei-seo-for-woo' ),
			'undo_redirect'     => __( 'لغو ریدایرکت', 'shojaei-seo-for-woo' ),
			'link_build'        => __( 'لینک‌سازی داخلی', 'shojaei-seo-for-woo' ),
			'auto_redirect'     => __( 'ریدایرکت خودکار', 'shojaei-seo-for-woo' ),
			'set_noindex'       => __( 'noindex', 'shojaei-seo-for-woo' ),
			'clear_noindex'     => __( 'بازگشت به index', 'shojaei-seo-for-woo' ),
			'sitemap_exclude'   => __( 'حذف از sitemap', 'shojaei-seo-for-woo' ),
			'sitemap_include'   => __( 'بازگشت به sitemap', 'shojaei-seo-for-woo' ),
			'link_deprioritize' => __( 'کاهش اولویت لینک', 'shojaei-seo-for-woo' ),
			'link_reprioritize' => __( 'بازگشت اولویت لینک', 'shojaei-seo-for-woo' ),
		);
		return $map[ $action ] ?? $action;
	}

	/**
	 * Snapshot SEO ops flags (noindex / sitemap / link priority).
	 *
	 * @param int $product_id Product ID.
	 * @return array
	 */
	public static function snapshot_seo_flags( int $product_id ): array {
		$noindex = get_post_meta( $product_id, '_shojaei_seo_noindex', true );
		$sitemap = get_post_meta( $product_id, '_shojaei_seo_sitemap_exclude', true );
		$link    = get_post_meta( $product_id, '_shojaei_seo_link_deprioritized', true );

		return array(
			'product_id'         => $product_id,
			'noindex'            => ( 'yes' === $noindex ) ? 'yes' : ( ( 'no' === $noindex ) ? 'no' : '' ),
			'sitemap_exclude'    => ( 'yes' === $sitemap ) ? 'yes' : 'no',
			'link_deprioritized' => ( 'yes' === $link ) ? 'yes' : 'no',
		);
	}

	/**
	 * Combined OOS + SEO flags snapshot for redirect-class ops.
	 *
	 * @param int $product_id Product ID.
	 */
	public static function snapshot_full( int $product_id ): array {
		return array_merge(
			self::snapshot_oos( $product_id ),
			array( 'seo_flags' => self::snapshot_seo_flags( $product_id ) )
		);
	}

	/**
	 * Persist flag diffs as separate undoable rows in one batch.
	 *
	 * @param int         $product_id Product.
	 * @param array       $before     Flags before.
	 * @param array       $after      Flags after.
	 * @param string|null $batch_id   Batch.
	 * @return int Rows written.
	 */
	public static function record_flag_diffs( int $product_id, array $before, array $after, ?string $batch_id = null ): int {
		$batch_id = $batch_id ?: self::new_batch_id();
		$title    = get_the_title( $product_id );
		$written  = 0;

		$pairs = array(
			array(
				'key'    => 'noindex',
				'on'     => 'set_noindex',
				'off'    => 'clear_noindex',
				'yes_lbl'=> __( 'noindex', 'shojaei-seo-for-woo' ),
				'no_lbl' => __( 'index', 'shojaei-seo-for-woo' ),
			),
			array(
				'key'    => 'sitemap_exclude',
				'on'     => 'sitemap_exclude',
				'off'    => 'sitemap_include',
				'yes_lbl'=> __( 'خارج از sitemap', 'shojaei-seo-for-woo' ),
				'no_lbl' => __( 'در sitemap', 'shojaei-seo-for-woo' ),
			),
			array(
				'key'    => 'link_deprioritized',
				'on'     => 'link_deprioritize',
				'off'    => 'link_reprioritize',
				'yes_lbl'=> __( 'اولویت لینک کم', 'shojaei-seo-for-woo' ),
				'no_lbl' => __( 'اولویت لینک عادی', 'shojaei-seo-for-woo' ),
			),
		);

		foreach ( $pairs as $pair ) {
			$b = (string) ( $before[ $pair['key'] ] ?? '' );
			$a = (string) ( $after[ $pair['key'] ] ?? '' );
			if ( $b === $a ) {
				continue;
			}

			$turning_on = ( 'yes' === $a );
			$action     = $turning_on ? $pair['on'] : $pair['off'];
			$from_lbl   = ( 'yes' === $b ) ? $pair['yes_lbl'] : $pair['no_lbl'];
			$to_lbl     = ( 'yes' === $a ) ? $pair['yes_lbl'] : $pair['no_lbl'];

			self::record(
				array(
					'batch_id'    => $batch_id,
					'mode'        => 'applied',
					'action'      => $action,
					'entity_type' => 'product',
					'entity_id'   => $product_id,
					'summary'     => sprintf(
						/* translators: 1: title, 2: from, 3: to */
						__( '«%1$s»: %2$s → %3$s', 'shojaei-seo-for-woo' ),
						$title,
						$from_lbl,
						$to_lbl
					),
					'before'      => $before,
					'after'       => $after,
				)
			);
			$written++;
		}

		return $written;
	}

	/**
	 * Apply SEO flag snapshot (used by undo).
	 *
	 * @param int   $product_id Product.
	 * @param array $flags      Flags snapshot.
	 */
	public static function restore_seo_flags( int $product_id, array $flags ): void {
		$noindex = (string) ( $flags['noindex'] ?? '' );
		if ( '' === $noindex ) {
			delete_post_meta( $product_id, '_shojaei_seo_noindex' );
		} else {
			update_post_meta( $product_id, '_shojaei_seo_noindex', ( 'yes' === $noindex ) ? 'yes' : 'no' );
		}

		update_post_meta(
			$product_id,
			'_shojaei_seo_sitemap_exclude',
			( 'yes' === ( $flags['sitemap_exclude'] ?? '' ) ) ? 'yes' : 'no'
		);
		update_post_meta(
			$product_id,
			'_shojaei_seo_link_deprioritized',
			( 'yes' === ( $flags['link_deprioritized'] ?? '' ) ) ? 'yes' : 'no'
		);

		if ( class_exists( 'Shojaei_SEO_Cache' ) ) {
			Shojaei_SEO_Cache::on_seo_state_change( $product_id );
		}
	}

	/**
	 * Recent log rows.
	 *
	 * @param int    $limit Limit.
	 * @param string $mode  all|applied|dry_run.
	 * @return array
	 */
	public static function get_recent( int $limit = 100, string $mode = 'all' ): array {
		global $wpdb;
		$table = self::table();
		$limit = max( 1, min( 300, $limit ) );

		if ( in_array( $mode, array( 'applied', 'dry_run' ), true ) ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE mode = %s ORDER BY id DESC LIMIT %d",
					$mode,
					$limit
				)
			);
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY id DESC LIMIT %d",
				$limit
			)
		);
	}

	/**
	 * Get rows by batch.
	 *
	 * @param string $batch_id Batch UUID.
	 * @return array
	 */
	public static function get_batch( string $batch_id ): array {
		global $wpdb;
		return $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . self::table() . ' WHERE batch_id = %s ORDER BY id ASC',
				$batch_id
			)
		);
	}

	/**
	 * Simulate bulk redirect without writing OOS changes (records dry_run log).
	 *
	 * @param array       $product_ids IDs.
	 * @param string      $action      redirect_301|302|410|keep.
	 * @param string|null $target_url  Shared target.
	 * @return array{batch_id:string,changes:array,blocked:array}
	 */
	public static function dry_run_bulk_redirect( array $product_ids, string $action, ?string $target_url = null, ?string $batch_id = null ): array {
		$batch_id = $batch_id ?: self::new_batch_id();
		$changes  = array();
		$blocked  = array();
		$manager  = new Shojaei_SEO_OOS_Manager( false );

		foreach ( $product_ids as $product_id ) {
			$product_id = absint( $product_id );
			if ( ! $product_id ) {
				continue;
			}

			$before = self::snapshot_oos( $product_id );
			$title  = get_the_title( $product_id );
			$after  = $before;
			$summary = '';

			if ( 'keep' === $action ) {
				$days   = (int) ( $before['days_oos'] ?? 0 );
				$state  = Shojaei_SEO_Helpers::get_oos_state( $days );
				$status = ( 'candidate_redirect' === ( $state['status'] ?? '' ) ) ? 'permanent_oos' : $state['status'];
				$after  = array_merge( $before, array(
					'status'        => $status,
					'redirect_type' => 'none',
					'target_url'    => '',
					'protected'     => 'yes',
				) );
				$summary = sprintf(
					/* translators: %s: title */
					__( 'نگهداری صفحه برای «%s» (+ محافظت)', 'shojaei-seo-for-woo' ),
					$title
				);
				$action_key = 'keep_page';
			} else {
				$type = 'redirect_410' === $action ? '410' : ( 'redirect_302' === $action ? '302' : '301' );
				$url  = '410' === $type ? '' : ( $target_url ?: $manager->get_suggested_target_url( $product_id ) );

				if ( '410' !== $type ) {
					$loop = Shojaei_SEO_Redirect_Engine::validate_redirect(
						(string) get_permalink( $product_id ),
						(string) $url,
						$product_id
					);
					if ( is_wp_error( $loop ) ) {
						$blocked[] = array(
							'product_id' => $product_id,
							'title'      => $title,
							'reason'     => $loop->get_error_message(),
						);
						continue;
					}
				}

				if ( class_exists( 'Shojaei_SEO_Page_Value' ) && Shojaei_SEO_Page_Value::requires_manual( $product_id ) ) {
					$blocked[] = array(
						'product_id' => $product_id,
						'title'      => $title,
						'reason'     => __( 'Page Value بالا — نیاز به تایید دستی', 'shojaei-seo-for-woo' ),
					);
					continue;
				}

				$after = array_merge( $before, array(
					'status'        => 'redirected',
					'redirect_type' => $type,
					'target_url'    => $url,
				) );
				$summary = sprintf(
					/* translators: 1: type, 2: title, 3: url */
					__( 'ریدایرکت %1$s برای «%2$s» → %3$s', 'shojaei-seo-for-woo' ),
					$type,
					$title,
					$url ?: '—'
				);
				$action_key = 'redirect_' . $type;
			}

			$log_id = self::record( array(
				'batch_id'    => $batch_id,
				'mode'        => 'dry_run',
				'action'      => $action_key,
				'entity_type' => 'product',
				'entity_id'   => $product_id,
				'summary'     => $summary,
				'before'      => $before,
				'after'       => $after,
			) );

			$risk = self::assess_change_risk( $action_key, $before, $after, $product_id );

			$changes[] = array(
				'log_id'       => $log_id,
				'product_id'   => $product_id,
				'title'        => $title,
				'action'       => $action_key,
				'action_label' => self::action_label( $action_key ),
				'change_type'  => self::change_type_label( $action_key ),
				'summary'      => $summary,
				'before'       => $before,
				'after'        => $after,
				'risk'         => $risk['level'],
				'risk_label'   => $risk['label'],
				'warnings'     => $risk['warnings'],
				'table'        => Shojaei_SEO_Helpers::oos_table(),
			);
		}

		return self::build_trust_report(
			'redirect',
			$action,
			array(
				'batch_id' => $batch_id,
				'changes'  => $changes,
				'blocked'  => $blocked,
				'message'  => sprintf(
					/* translators: 1: planned, 2: blocked */
					__( 'شبیه‌سازی: %1$d تغییر پیشنهادی، %2$d مورد مسدود.', 'shojaei-seo-for-woo' ),
					count( $changes ),
					count( $blocked )
				),
			)
		);
	}

	/**
	 * Simulate link building for a post (no transient write); records dry_run rows.
	 *
	 * @param int $post_id Post/product ID.
	 * @return array
	 */
	public static function dry_run_link_build( int $post_id ): array {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return array(
				'batch_id' => '',
				'changes'  => array(),
				'blocked'  => array(),
				'message'  => __( 'نوشته یافت نشد.', 'shojaei-seo-for-woo' ),
			);
		}

		$builder = new Shojaei_SEO_Link_Builder( false );
		$result  = $builder->preview_links( $post->post_content, $post_id );
		$batch   = self::new_batch_id();
		$changes = array();

		$summary = sprintf(
			/* translators: 1: title, 2: count, 3: max */
			__( 'لینک‌سازی «%1$s»: %2$d لینک (سقف مجاز %3$d، کلمات %4$d)', 'shojaei-seo-for-woo' ),
			$post->post_title,
			(int) $result['links_added'],
			(int) ( $result['max_allowed'] ?? 0 ),
			(int) ( $result['word_count'] ?? 0 )
		);

		$before = array(
			'post_id'   => $post_id,
			'transient' => 'shojaei_seo_linked_' . $post_id,
			'has_cache' => false !== get_transient( 'shojaei_seo_linked_' . $post_id ),
		);
		$after  = array(
			'post_id'     => $post_id,
			'links_added' => (int) $result['links_added'],
			'details'     => $result['details'],
			'max_allowed' => (int) ( $result['max_allowed'] ?? 0 ),
			'note'        => __( 'فقط کش transient تغییر می‌کند؛ محتوای دیتابیس نوشته دست‌نخورده می‌ماند.', 'shojaei-seo-for-woo' ),
		);

		$log_id = self::record( array(
			'batch_id'    => $batch,
			'mode'        => 'dry_run',
			'action'      => 'link_build',
			'entity_type' => $post->post_type,
			'entity_id'   => $post_id,
			'summary'     => $summary,
			'before'      => $before,
			'after'       => $after,
		) );

		$risk = self::assess_change_risk( 'link_build', $before, $after, $post_id );

		$changes[] = array(
			'log_id'       => $log_id,
			'product_id'   => $post_id,
			'title'        => $post->post_title,
			'action'       => 'link_build',
			'action_label' => self::action_label( 'link_build' ),
			'change_type'  => self::change_type_label( 'link_build' ),
			'summary'      => $summary,
			'details'      => $result['details'],
			'table'        => 'transients / options',
			'before'       => $before,
			'after'        => $after,
			'risk'         => $risk['level'],
			'risk_label'   => $risk['label'],
			'warnings'     => $risk['warnings'],
		);

		return self::build_trust_report(
			'links',
			'link_build',
			array(
				'batch_id' => $batch,
				'changes'  => $changes,
				'blocked'  => array(),
				'preview'  => $result['content'],
				'message'  => $summary,
			)
		);
	}

	/**
	 * Human label for change category.
	 *
	 * @param string $action Action slug.
	 */
	public static function change_type_label( string $action ): string {
		if ( in_array( $action, array( 'redirect_301', 'redirect_302', 'redirect_410', 'auto_redirect' ), true ) ) {
			return __( 'ریدایرکت / URL', 'shojaei-seo-for-woo' );
		}
		if ( 'keep_page' === $action ) {
			return __( 'نگهداری صفحه', 'shojaei-seo-for-woo' );
		}
		if ( 'link_build' === $action ) {
			return __( 'لینک‌سازی داخلی', 'shojaei-seo-for-woo' );
		}
		if ( in_array( $action, array( 'set_noindex', 'clear_noindex' ), true ) ) {
			return __( 'indexability', 'shojaei-seo-for-woo' );
		}
		if ( in_array( $action, array( 'sitemap_exclude', 'sitemap_include' ), true ) ) {
			return __( 'sitemap', 'shojaei-seo-for-woo' );
		}
		return self::action_label( $action );
	}

	/**
	 * Risk assessment for a planned change.
	 *
	 * @param string $action     Action.
	 * @param array  $before     Before.
	 * @param array  $after      After.
	 * @param int    $product_id Entity.
	 * @return array{level:string,label:string,warnings:array}
	 */
	public static function assess_change_risk( string $action, array $before, array $after, int $product_id ): array {
		$warnings = array();
		$level    = 'low';

		if ( 'redirect_410' === $action ) {
			$level      = 'high';
			$warnings[] = __( '۴۱۰ دائمی است — URL از نتایج حذف می‌شود.', 'shojaei-seo-for-woo' );
		} elseif ( 'redirect_301' === $action ) {
			$level      = 'high';
			$warnings[] = __( '۳۰۱ دائمی سیگنال رتبه را منتقل می‌کند.', 'shojaei-seo-for-woo' );
		} elseif ( 'redirect_302' === $action || 'auto_redirect' === $action ) {
			$level      = 'medium';
			$warnings[] = __( '۳۰۲ موقتی است؛ برای ناموجودی کوتاه مناسب‌تر است.', 'shojaei-seo-for-woo' );
		} elseif ( 'link_build' === $action ) {
			$added = (int) ( $after['links_added'] ?? 0 );
			$level = $added >= 5 ? 'medium' : 'low';
			if ( $added < 1 ) {
				$warnings[] = __( 'لینک جدیدی پیشنهاد نشد.', 'shojaei-seo-for-woo' );
				$warnings[] = __( 'علت‌های رایج: محتوای محصول خالی است، کلمه کلیدی در متن نیست، یا مقصدها noindex شده‌اند. از نسخه جدید: توضیح کوتاه + پیشنهاد محصولات مشابه دسته هم بررسی می‌شود.', 'shojaei-seo-for-woo' );
			}
		} elseif ( 'keep_page' === $action ) {
			$level = 'low';
		}

		if ( class_exists( 'Shojaei_SEO_Page_Value' ) && $product_id ) {
			$score = (int) Shojaei_SEO_Page_Value::get_score( $product_id );
			if ( $score >= 50 && in_array( $action, array( 'redirect_301', 'redirect_302', 'redirect_410' ), true ) ) {
				$level      = 'high';
				$warnings[] = sprintf(
					/* translators: %d: score */
					__( 'Page Value نسبتاً بالا (امتیاز %d) — با احتیاط اعمال کنید.', 'shojaei-seo-for-woo' ),
					$score
				);
			}
		}

		if ( in_array( $action, array( 'redirect_301', 'redirect_302' ), true ) && empty( $after['target_url'] ) ) {
			$level      = 'high';
			$warnings[] = __( 'مقصد ریدایرکت خالی است.', 'shojaei-seo-for-woo' );
		}

		$labels = array(
			'high'   => __( 'ریسک بالا', 'shojaei-seo-for-woo' ),
			'medium' => __( 'ریسک متوسط', 'shojaei-seo-for-woo' ),
			'low'    => __( 'ریسک پایین', 'shojaei-seo-for-woo' ),
		);

		return array(
			'level'    => $level,
			'label'    => $labels[ $level ] ?? $level,
			'warnings' => $warnings,
		);
	}

	/**
	 * Build trust-oriented Dry-Run report + persist for export/apply.
	 *
	 * @param string $type    redirect|links.
	 * @param string $action  Requested action.
	 * @param array  $result  Raw result.
	 * @return array
	 */
	public static function build_trust_report( string $type, string $action, array $result ): array {
		$changes = $result['changes'] ?? array();
		$blocked = $result['blocked'] ?? array();
		$batch   = (string) ( $result['batch_id'] ?? '' );

		// Merge chunked dry-runs that share the same batch_id.
		if ( $batch ) {
			$prev = get_transient( 'shojaei_seo_dryrun_' . $batch );
			if ( is_array( $prev ) ) {
				$changes = array_merge( (array) ( $prev['changes'] ?? array() ), $changes );
				$blocked = array_merge( (array) ( $prev['blocked'] ?? array() ), $blocked );
			}
		}

		foreach ( $blocked as &$b ) {
			$b['risk']       = 'high';
			$b['risk_label'] = __( 'مسدود / هشدار', 'shojaei-seo-for-woo' );
		}
		unset( $b );

		$by_type = array();
		$by_risk = array( 'high' => 0, 'medium' => 0, 'low' => 0 );
		foreach ( $changes as $c ) {
			$key = (string) ( $c['action'] ?? 'change' );
			if ( ! isset( $by_type[ $key ] ) ) {
				$by_type[ $key ] = array(
					'action' => $key,
					'label'  => self::action_label( $key ),
					'count'  => 0,
				);
			}
			$by_type[ $key ]['count']++;
			$risk = (string) ( $c['risk'] ?? 'low' );
			if ( isset( $by_risk[ $risk ] ) ) {
				$by_risk[ $risk ]++;
			}
		}

		$report_warnings = array();
		if ( $by_risk['high'] > 0 ) {
			$report_warnings[] = sprintf(
				/* translators: %d: count */
				__( '%d مورد با ریسک بالا در این پیش‌نمایش وجود دارد.', 'shojaei-seo-for-woo' ),
				$by_risk['high']
			);
		}
		if ( count( $blocked ) > 0 ) {
			$report_warnings[] = sprintf(
				/* translators: %d: count */
				__( '%d مورد مسدود شد و در اعمال واقعی نادیده گرفته می‌شود.', 'shojaei-seo-for-woo' ),
				count( $blocked )
			);
		}
		if ( 'redirect_301' === $action || 'redirect_410' === $action ) {
			$report_warnings[] = __( 'تغییرات دائمی روی URL — قبل از اجرا خروجی بگیرید و با تیم محتوا هماهنگ کنید.', 'shojaei-seo-for-woo' );
		}

		$report = array_merge(
			$result,
			array(
				'type'             => $type,
				'requested_action' => $action,
				'changes'          => $changes,
				'blocked'          => $blocked,
				'message'          => sprintf(
					/* translators: 1: planned, 2: blocked */
					__( 'شبیه‌سازی: %1$d تغییر پیشنهادی، %2$d مورد مسدود.', 'shojaei-seo-for-woo' ),
					count( $changes ),
					count( $blocked )
				),
				'counts'           => array(
					'affected' => count( $changes ),
					'blocked'  => count( $blocked ),
					'total'    => count( $changes ) + count( $blocked ),
					'by_type'  => array_values( $by_type ),
					'by_risk'  => $by_risk,
				),
				'warnings'         => $report_warnings,
				'can_apply'        => count( $changes ) > 0,
				'can_export'       => ( count( $changes ) + count( $blocked ) ) > 0,
				'created_at'       => time(),
				'trust_note'       => __( 'هنوز چیزی روی فروشگاه اعمال نشده است. پس از بررسی می‌توانید خروجی بگیرید یا اجرای واقعی را شروع کنید.', 'shojaei-seo-for-woo' ),
			)
		);

		self::store_dry_run_report( $report );
		return $report;
	}

	/**
	 * Persist report for export / apply-from-preview.
	 *
	 * @param array $report Report.
	 */
	public static function store_dry_run_report( array $report ): void {
		$batch = (string) ( $report['batch_id'] ?? '' );
		if ( $batch ) {
			set_transient( 'shojaei_seo_dryrun_' . $batch, $report, DAY_IN_SECONDS );
		}
		$user_key = 'shojaei_seo_last_dry_run_' . get_current_user_id();
		set_transient( $user_key, $report, DAY_IN_SECONDS );
		update_option( 'shojaei_seo_last_dry_run', $report, false );
	}

	/**
	 * Load stored dry-run report.
	 *
	 * @param string $batch_id Optional batch.
	 * @return array|null
	 */
	public static function get_dry_run_report( string $batch_id = '' ): ?array {
		if ( $batch_id ) {
			$cached = get_transient( 'shojaei_seo_dryrun_' . $batch_id );
			if ( is_array( $cached ) ) {
				return $cached;
			}
		}
		$user = get_transient( 'shojaei_seo_last_dry_run_' . get_current_user_id() );
		if ( is_array( $user ) ) {
			return $user;
		}
		$opt = get_option( 'shojaei_seo_last_dry_run', null );
		return is_array( $opt ) ? $opt : null;
	}

	/**
	 * Apply a previous Dry-Run batch for real (with Undo records).
	 *
	 * @param string $batch_id      Dry-run batch id.
	 * @param bool   $force_confirm Force high page-value.
	 * @return array{ok:int,fail:int,apply_batch:string,errors:array,message:string}
	 */
	public static function apply_from_dry_run( string $batch_id, bool $force_confirm = false ): array {
		$rows = self::get_batch( $batch_id );
		$ok   = 0;
		$fail = 0;
		$errors = array();
		$apply_batch = self::new_batch_id();
		$manager = class_exists( 'Shojaei_SEO_OOS_Manager' ) ? new Shojaei_SEO_OOS_Manager( false ) : null;

		foreach ( $rows as $row ) {
			if ( 'dry_run' !== $row->mode ) {
				continue;
			}

			$action    = (string) $row->action;
			$entity_id = (int) $row->entity_id;
			$after     = json_decode( (string) $row->after_state, true );
			if ( ! is_array( $after ) ) {
				$after = array();
			}

			try {
				if ( 'link_build' === $action ) {
					$post = get_post( $entity_id );
					if ( ! $post ) {
						throw new RuntimeException( __( 'نوشته یافت نشد.', 'shojaei-seo-for-woo' ) );
					}
					$builder   = new Shojaei_SEO_Link_Builder( false );
					$cache_key = 'shojaei_seo_linked_' . $entity_id;
					$prev      = get_transient( $cache_key );
					$result    = $builder->build_links( (string) $post->post_content, true, (int) $entity_id );
					set_transient( $cache_key, $result['content'], DAY_IN_SECONDS );
					self::record(
						array(
							'batch_id'    => $apply_batch,
							'mode'        => 'applied',
							'action'      => 'link_build',
							'entity_type' => $post->post_type,
							'entity_id'   => $entity_id,
							'summary'     => sprintf(
								/* translators: 1: title, 2: count */
								__( 'اعمال از Dry-Run — لینک‌سازی «%1$s»: %2$d لینک', 'shojaei-seo-for-woo' ),
								$post->post_title,
								(int) $result['links_added']
							),
							'before'      => array(
								'has_cache'     => false !== $prev,
								'cache_content' => is_string( $prev ) ? $prev : '',
							),
							'after'       => array(
								'links_added' => (int) $result['links_added'],
								'details'     => $result['details'],
							),
						)
					);
					$ok++;
					continue;
				}

				if ( ! $manager ) {
					throw new RuntimeException( 'OOS manager missing' );
				}

				if ( 'keep_page' === $action ) {
					$manager->keep_page( $entity_id, $apply_batch );
					$ok++;
					continue;
				}

				if ( in_array( $action, array( 'redirect_301', 'redirect_302', 'redirect_410' ), true ) ) {
					$type = 'redirect_410' === $action ? '410' : ( 'redirect_302' === $action ? '302' : '301' );
					$url  = (string) ( $after['target_url'] ?? '' );
					$res  = $manager->apply_manual_redirect( $entity_id, $type, $url, $force_confirm, $apply_batch );
					if ( is_wp_error( $res ) ) {
						throw new RuntimeException( $res->get_error_message() );
					}
					$ok++;
					continue;
				}

				$fail++;
				$errors[] = array(
					'entity_id' => $entity_id,
					'reason'    => __( 'نوع عملیات برای اعمال از Dry-Run پشتیبانی نمی‌شود.', 'shojaei-seo-for-woo' ),
				);
			} catch ( Throwable $e ) {
				$fail++;
				$errors[] = array(
					'entity_id' => $entity_id,
					'title'     => get_the_title( $entity_id ),
					'reason'    => $e->getMessage(),
				);
			}
		}

		if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
			Shojaei_SEO_Activity_Log::add(
				'dry_run_apply',
				sprintf(
					/* translators: 1: ok, 2: fail, 3: batch */
					__( 'اجرای واقعی از Dry-Run: %1$d موفق، %2$d ناموفق (batch %3$s)', 'shojaei-seo-for-woo' ),
					$ok,
					$fail,
					substr( $batch_id, 0, 8 )
				),
				0,
				array(
					'dry_run_batch' => $batch_id,
					'apply_batch'   => $apply_batch,
				)
			);
		}

		return array(
			'ok'          => $ok,
			'fail'        => $fail,
			'apply_batch' => $apply_batch,
			'errors'      => $errors,
			'message'     => sprintf(
				/* translators: 1: ok, 2: fail */
				__( 'اجرای واقعی از پیش‌نمایش: %1$d موفق، %2$d ناموفق. Undo از همان batch اعمال‌شده در دسترس است.', 'shojaei-seo-for-woo' ),
				$ok,
				$fail
			),
		);
	}

	/**
	 * Flat rows for CSV export of a dry-run report.
	 *
	 * @param array $report Report.
	 * @return array{header:array,rows:array}
	 */
	public static function export_dry_run_rows( array $report ): array {
		$header = array(
			'Batch ID',
			'Status',
			'Product/Post ID',
			'Title',
			'Change Type',
			'Action',
			'Risk',
			'Summary',
			'Warnings',
			'Before',
			'After',
		);
		$rows   = array();

		foreach ( (array) ( $report['changes'] ?? array() ) as $c ) {
			$rows[] = array(
				$report['batch_id'] ?? '',
				'planned',
				$c['product_id'] ?? '',
				$c['title'] ?? '',
				$c['change_type'] ?? '',
				$c['action_label'] ?? ( $c['action'] ?? '' ),
				$c['risk_label'] ?? ( $c['risk'] ?? '' ),
				$c['summary'] ?? '',
				implode( ' | ', (array) ( $c['warnings'] ?? array() ) ),
				wp_json_encode( $c['before'] ?? array(), JSON_UNESCAPED_UNICODE ),
				wp_json_encode( $c['after'] ?? array(), JSON_UNESCAPED_UNICODE ),
			);
		}
		foreach ( (array) ( $report['blocked'] ?? array() ) as $b ) {
			$rows[] = array(
				$report['batch_id'] ?? '',
				'blocked',
				$b['product_id'] ?? '',
				$b['title'] ?? '',
				'',
				'',
				$b['risk_label'] ?? __( 'مسدود', 'shojaei-seo-for-woo' ),
				$b['reason'] ?? '',
				$b['reason'] ?? '',
				'',
				'',
			);
		}

		return array(
			'header' => $header,
			'rows'   => $rows,
		);
	}

	/**
	 * Rollback one applied log entry.
	 *
	 * @param int $log_id Log ID.
	 * @return true|WP_Error
	 */
	public static function rollback_one( int $log_id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $log_id )
		);

		if ( ! $row ) {
			return new WP_Error( 'not_found', __( 'رکورد یافت نشد.', 'shojaei-seo-for-woo' ) );
		}

		if ( 'dry_run' === $row->mode ) {
			return new WP_Error( 'dry_run', __( 'شبیه‌سازی قابل بازگردانی نیست (اعمال نشده).', 'shojaei-seo-for-woo' ) );
		}

		if ( (int) $row->is_reverted ) {
			return new WP_Error( 'already', __( 'قبلاً بازگردانی شده است.', 'shojaei-seo-for-woo' ) );
		}

		$before = json_decode( (string) $row->before_state, true );
		if ( ! is_array( $before ) ) {
			$before = array();
		}

		$result = self::apply_before_state( $row->action, (int) $row->entity_id, $before );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$wpdb->update(
			self::table(),
			array( 'is_reverted' => 1 ),
			array( 'id' => $log_id ),
			array( '%d' ),
			array( '%d' )
		);

		if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
			Shojaei_SEO_Activity_Log::add(
				'rollback',
				sprintf(
					/* translators: %s: summary */
					__( 'بازگردانی: %s', 'shojaei-seo-for-woo' ),
					$row->summary
				),
				(int) $row->entity_id,
				array( 'log_id' => $log_id )
			);
		}

		return true;
	}

	/**
	 * Rollback entire applied batch.
	 *
	 * @param string $batch_id Batch UUID.
	 * @return array{ok:int,fail:int}
	 */
	public static function rollback_batch( string $batch_id ): array {
		$rows = self::get_batch( $batch_id );
		$ok   = 0;
		$fail = 0;

		// Reverse order for safer undo.
		$rows = array_reverse( $rows );

		foreach ( $rows as $row ) {
			if ( 'applied' !== $row->mode || (int) $row->is_reverted ) {
				continue;
			}
			$result = self::rollback_one( (int) $row->id );
			if ( is_wp_error( $result ) ) {
				$fail++;
			} else {
				$ok++;
			}
		}

		return array( 'ok' => $ok, 'fail' => $fail );
	}

	/**
	 * Restore before_state for a recorded action.
	 *
	 * @param string $action    Action.
	 * @param int    $entity_id Entity.
	 * @param array  $before    Before snapshot.
	 * @return true|WP_Error
	 */
	private static function apply_before_state( string $action, int $entity_id, array $before ) {
		global $wpdb;

		if ( ! self::is_undoable( $action ) ) {
			return new WP_Error( 'unsupported', __( 'این نوع تغییر قابل بازگردانی خودکار نیست.', 'shojaei-seo-for-woo' ) );
		}

		if ( 'link_build' === $action ) {
			$key = 'shojaei_seo_linked_' . $entity_id;
			if ( ! empty( $before['has_cache'] ) && isset( $before['cache_content'] ) ) {
				set_transient( $key, (string) $before['cache_content'], DAY_IN_SECONDS );
			} else {
				delete_transient( $key );
			}
			return true;
		}

		if ( in_array( $action, array( 'set_noindex', 'clear_noindex', 'sitemap_exclude', 'sitemap_include', 'link_deprioritize', 'link_reprioritize' ), true ) ) {
			self::restore_seo_flags( $entity_id, $before );
			return true;
		}

		if ( in_array( $action, array( 'redirect_301', 'redirect_302', 'redirect_410', 'keep_page', 'auto_redirect', 'undo_redirect' ), true ) ) {
			$table = Shojaei_SEO_Helpers::oos_table();

			if ( empty( $before['exists'] ) ) {
				$wpdb->delete( $table, array( 'product_id' => $entity_id ), array( '%d' ) );
				Shojaei_SEO_Helpers::clear_oos_postmeta( $entity_id );
			} else {
				$exists = $wpdb->get_var(
					$wpdb->prepare( "SELECT id FROM {$table} WHERE product_id = %d", $entity_id )
				);

				$data = array(
					'status'        => sanitize_text_field( $before['status'] ?? 'temp_oos' ),
					'redirect_type' => sanitize_text_field( $before['redirect_type'] ?? 'none' ),
					'target_url'    => esc_url_raw( $before['target_url'] ?? '' ),
					'days_oos'      => absint( $before['days_oos'] ?? 0 ),
				);

				if ( ! empty( $before['oos_date'] ) ) {
					$data['oos_date'] = sanitize_text_field( $before['oos_date'] );
				}

				if ( $exists ) {
					$wpdb->update( $table, $data, array( 'product_id' => $entity_id ) );
				} else {
					$data['product_id'] = $entity_id;
					if ( empty( $data['oos_date'] ) ) {
						$data['oos_date'] = current_time( 'mysql' );
					}
					$wpdb->insert( $table, $data );
				}

				if ( class_exists( 'Shojaei_SEO_Page_Value' ) && isset( $before['protected'] ) ) {
					Shojaei_SEO_Page_Value::set_protected( $entity_id, 'yes' === $before['protected'] );
				}

				if ( ! empty( $before['oos_date'] ) ) {
					Shojaei_SEO_Helpers::sync_oos_postmeta( $entity_id, (string) $before['oos_date'], (int) ( $before['days_oos'] ?? 0 ) );
				}
			}

			if ( ! empty( $before['seo_flags'] ) && is_array( $before['seo_flags'] ) ) {
				self::restore_seo_flags( $entity_id, $before['seo_flags'] );
			}

			if ( class_exists( 'Shojaei_SEO_Cache' ) ) {
				Shojaei_SEO_Cache::on_seo_state_change( $entity_id );
			}

			return true;
		}

		return new WP_Error( 'unsupported', __( 'این نوع تغییر قابل بازگردانی خودکار نیست.', 'shojaei-seo-for-woo' ) );
	}

	/**
	 * Preview what one rollback will restore (no writes).
	 *
	 * @param int $log_id Log ID.
	 * @return array|WP_Error
	 */
	public static function preview_rollback_one( int $log_id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $log_id )
		);
		if ( ! $row ) {
			return new WP_Error( 'not_found', __( 'رکورد یافت نشد.', 'shojaei-seo-for-woo' ) );
		}
		if ( 'dry_run' === $row->mode ) {
			return new WP_Error( 'dry_run', __( 'شبیه‌سازی اعمال نشده؛ Undo لازم نیست.', 'shojaei-seo-for-woo' ) );
		}
		if ( (int) $row->is_reverted ) {
			return new WP_Error( 'already', __( 'قبلاً بازگردانی شده است.', 'shojaei-seo-for-woo' ) );
		}
		if ( ! self::is_undoable( (string) $row->action ) ) {
			return new WP_Error( 'unsupported', __( 'این عملیات Undo واقعی ندارد.', 'shojaei-seo-for-woo' ) );
		}

		$before = json_decode( (string) $row->before_state, true );
		$after  = json_decode( (string) $row->after_state, true );
		if ( ! is_array( $before ) ) {
			$before = array();
		}
		if ( ! is_array( $after ) ) {
			$after = array();
		}

		return array(
			'log_id'     => (int) $row->id,
			'batch_id'   => (string) $row->batch_id,
			'action'     => (string) $row->action,
			'action_label' => self::action_label( (string) $row->action ),
			'entity_id'  => (int) $row->entity_id,
			'title'      => get_the_title( (int) $row->entity_id ),
			'summary'    => (string) $row->summary,
			'effects'    => self::describe_effects( (string) $row->action, $before, $after ),
			'undoable'   => true,
			'message'    => __( 'پیش‌نمایش Undo — هنوز چیزی بازگردانی نشده است.', 'shojaei-seo-for-woo' ),
		);
	}

	/**
	 * Preview batch rollback.
	 *
	 * @param string $batch_id Batch UUID.
	 * @return array
	 */
	public static function preview_rollback_batch( string $batch_id ): array {
		$rows    = self::get_batch( $batch_id );
		$items   = array();
		$skipped = 0;

		foreach ( array_reverse( $rows ) as $row ) {
			if ( 'applied' !== $row->mode || (int) $row->is_reverted ) {
				$skipped++;
				continue;
			}
			$preview = self::preview_rollback_one( (int) $row->id );
			if ( is_wp_error( $preview ) ) {
				$skipped++;
				continue;
			}
			$items[] = $preview;
		}

		return array(
			'batch_id' => $batch_id,
			'count'    => count( $items ),
			'skipped'  => $skipped,
			'items'    => $items,
			'message'  => sprintf(
				/* translators: 1: count, 2: skipped */
				__( 'پیش‌نمایش Undo دسته: %1$d مورد قابل بازگردانی، %2$d رد شده.', 'shojaei-seo-for-woo' ),
				count( $items ),
				$skipped
			),
		);
	}

	/**
	 * Human-readable effect lines for preview UI.
	 *
	 * @param string $action Action.
	 * @param array  $before Before.
	 * @param array  $after  After.
	 * @return array<int,array{field:string,from:string,to:string}>
	 */
	public static function describe_effects( string $action, array $before, array $after ): array {
		$effects = array();

		if ( 'link_build' === $action ) {
			$effects[] = array(
				'field' => __( 'لینک‌سازی (کش)', 'shojaei-seo-for-woo' ),
				'from'  => ! empty( $after['links_added'] )
					? sprintf(
						/* translators: %d: links */
						__( '%d لینک تزریق‌شده', 'shojaei-seo-for-woo' ),
						(int) $after['links_added']
					)
					: __( 'لینک جدید', 'shojaei-seo-for-woo' ),
				'to'    => ! empty( $before['has_cache'] )
					? __( 'کش قبلی', 'shojaei-seo-for-woo' )
					: __( 'بدون لینک تزریق‌شده', 'shojaei-seo-for-woo' ),
			);
			return $effects;
		}

		if ( in_array( $action, array( 'set_noindex', 'clear_noindex' ), true ) ) {
			$effects[] = array(
				'field' => 'robots',
				'from'  => ( 'yes' === ( $after['noindex'] ?? '' ) ) ? 'noindex' : 'index',
				'to'    => ( 'yes' === ( $before['noindex'] ?? '' ) ) ? 'noindex' : 'index',
			);
		}

		if ( in_array( $action, array( 'sitemap_exclude', 'sitemap_include' ), true ) ) {
			$effects[] = array(
				'field' => 'sitemap',
				'from'  => ( 'yes' === ( $after['sitemap_exclude'] ?? '' ) )
					? __( 'خارج‌شده', 'shojaei-seo-for-woo' )
					: __( 'در sitemap', 'shojaei-seo-for-woo' ),
				'to'    => ( 'yes' === ( $before['sitemap_exclude'] ?? '' ) )
					? __( 'خارج‌شده', 'shojaei-seo-for-woo' )
					: __( 'در sitemap', 'shojaei-seo-for-woo' ),
			);
		}

		if ( in_array( $action, array( 'link_deprioritize', 'link_reprioritize' ), true ) ) {
			$effects[] = array(
				'field' => __( 'اولویت لینک', 'shojaei-seo-for-woo' ),
				'from'  => ( 'yes' === ( $after['link_deprioritized'] ?? '' ) ) ? 'low' : 'normal',
				'to'    => ( 'yes' === ( $before['link_deprioritized'] ?? '' ) ) ? 'low' : 'normal',
			);
		}

		if ( in_array( $action, array( 'redirect_301', 'redirect_302', 'redirect_410', 'keep_page', 'auto_redirect', 'undo_redirect' ), true ) ) {
			$from_type = (string) ( $after['redirect_type'] ?? 'none' );
			$to_type   = (string) ( $before['redirect_type'] ?? 'none' );
			$from_url  = (string) ( $after['target_url'] ?? '' );
			$to_url    = (string) ( $before['target_url'] ?? '' );

			$effects[] = array(
				'field' => __( 'ریدایرکت', 'shojaei-seo-for-woo' ),
				'from'  => ( 'none' === $from_type || '' === $from_type )
					? __( 'بدون ریدایرکت', 'shojaei-seo-for-woo' )
					: trim( $from_type . ' ' . $from_url ),
				'to'    => ( 'none' === $to_type || '' === $to_type )
					? __( 'بدون ریدایرکت', 'shojaei-seo-for-woo' )
					: trim( $to_type . ' ' . $to_url ),
			);

			if ( isset( $after['status'] ) || isset( $before['status'] ) ) {
				$effects[] = array(
					'field' => __( 'وضعیت OOS', 'shojaei-seo-for-woo' ),
					'from'  => (string) ( $after['status'] ?? '—' ),
					'to'    => (string) ( $before['status'] ?? '—' ),
				);
			}
		}

		if ( empty( $effects ) ) {
			$effects[] = array(
				'field' => __( 'وضعیت', 'shojaei-seo-for-woo' ),
				'from'  => __( 'بعد از تغییر', 'shojaei-seo-for-woo' ),
				'to'    => __( 'قبل از تغییر', 'shojaei-seo-for-woo' ),
			);
		}

		return $effects;
	}

	/**
	 * Record an applied OOS mutation with before/after.
	 *
	 * @param string $batch_id   Batch.
	 * @param string $action     Action.
	 * @param int    $product_id Product.
	 * @param array  $before     Before.
	 * @param array  $after      After.
	 * @param string $summary    Summary.
	 */
	public static function record_applied_oos( string $batch_id, string $action, int $product_id, array $before, array $after, string $summary ): int {
		return self::record( array(
			'batch_id'    => $batch_id,
			'mode'        => 'applied',
			'action'      => $action,
			'entity_type' => 'product',
			'entity_id'   => $product_id,
			'summary'     => $summary,
			'before'      => $before,
			'after'       => $after,
		) );
	}
}
