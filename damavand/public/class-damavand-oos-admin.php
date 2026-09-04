<?php
/**
 * OOS admin: metabox, diagnose, manual/bulk redirects, keep-page, redirect plans.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Damavand_OOS_Admin
 */
class Damavand_OOS_Admin {

	/**
	 * متاباکس ادمین: وضعیت سئوی ناموجودی روی ویرایش محصول.
	 */
	public function register_admin_metabox(): void {
		add_meta_box(
			'damavand_oos_status',
			__( 'موجودی و سئو (Damavand)', 'shojaei-seo-for-woo' ),
			array( $this, 'render_admin_metabox' ),
			'product',
			'side',
			'default'
		);
	}

	/**
	 * @param WP_Post $post Post.
	 */
	public function render_admin_metabox( $post ): void {
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		$product = function_exists( 'wc_get_product' ) ? wc_get_product( $post->ID ) : null;
		if ( ! $product ) {
			echo '<p>' . esc_html__( 'محصول ووکامرس یافت نشد.', 'shojaei-seo-for-woo' ) . '</p>';
			return;
		}

		$in_stock = $product->is_in_stock();
		$emails   = get_post_meta( $post->ID, '_shojaei_seo_restock_emails', true );
		$waiters  = is_array( $emails ) ? count( $emails ) : 0;
		$notify   = ( 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_notify_enabled', 'no' ) );
		$oos_url  = admin_url( 'admin.php?page=shojaei-seo&tab=oos' );

		if ( $in_stock ) {
			echo '<p><strong class="shojaei-tone-safe">' . esc_html__( 'موجود', 'shojaei-seo-for-woo' ) . '</strong></p>';
			if ( $waiters > 0 ) {
				echo '<p>' . esc_html(
					sprintf(
						/* translators: %d: count */
						__( '%d نفر برای خبر موجود شدن ثبت‌نام کرده‌اند (با برگشت موجودی ایمیل می‌گیرند).', 'shojaei-seo-for-woo' ),
						$waiters
					)
				) . '</p>';
			}
			echo '<p><a href="' . esc_url( $oos_url ) . '">' . esc_html__( 'مرکز ناموجودی', 'shojaei-seo-for-woo' ) . '</a></p>';
			return;
		}

		global $wpdb;
		$table  = Shojaei_SEO_Helpers::oos_table();
		$record = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE product_id = %d", $post->ID ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$days   = $record ? (int) $record->days_oos : 0;
		$state  = Shojaei_SEO_Helpers::get_oos_state( $days );
		$phase  = (int) ( $state['phase'] ?? 0 );
		$labels = array(
			1 => __( 'فاز ۱ — موقت', 'shojaei-seo-for-woo' ),
			2 => __( 'فاز ۲ — کم‌احتمال', 'shojaei-seo-for-woo' ),
			3 => __( 'فاز ۳ — نهایی / کاندید ریدایرکت', 'shojaei-seo-for-woo' ),
		);
		$phase_label = $labels[ $phase ] ?? __( 'ناموجود (بدون رکورد چرخه)', 'shojaei-seo-for-woo' );

		$manager = new Shojaei_SEO_OOS_Manager( false );
		$related = $manager->get_related_in_stock_products( (int) $post->ID, 3 );
		$plan    = self::build_quick_redirect_plan_static( $product );

		echo '<p><strong class="shojaei-tone-warning">' . esc_html__( 'ناموجود', 'shojaei-seo-for-woo' ) . '</strong></p>';
		echo '<ul style="margin:0 0 8px 1.1em;padding:0;">';
		echo '<li>' . esc_html( $phase_label ) . '</li>';
		echo '<li>' . esc_html(
			sprintf(
				/* translators: %d: days */
				__( 'روز ناموجودی: %d', 'shojaei-seo-for-woo' ),
				$days
			)
		) . '</li>';
		echo '<li>' . esc_html(
			sprintf(
				/* translators: %d: count */
				__( 'لیست خبرم‌کن: %d نفر', 'shojaei-seo-for-woo' ),
				$waiters
			)
		) . ( $notify ? '' : ' — ' . esc_html__( 'اطلاع‌رسانی خاموش است', 'shojaei-seo-for-woo' ) ) . '</li>';
		echo '<li>' . esc_html(
			sprintf(
				/* translators: %d: count */
				__( 'پیشنهاد جایگزین موجود: %d', 'shojaei-seo-for-woo' ),
				count( $related )
			)
		) . '</li>';
		if ( ! empty( $plan['target_url'] ) ) {
			echo '<li>' . esc_html__( 'مقصد پیشنهادی ریدایرکت:', 'shojaei-seo-for-woo' ) . ' <code dir="ltr">' . esc_html( (string) $plan['target_url'] ) . '</code></li>';
		}
		echo '</ul>';
		echo '<p><a class="button button-small" href="' . esc_url( $oos_url ) . '">' . esc_html__( 'اقدام در مرکز ناموجودی', 'shojaei-seo-for-woo' ) . '</a></p>';
	}

	/**
	 * Build suggested redirect plan without applying (static — safe for Rule Engine).
	 *
	 * @param WC_Product $product Product.
	 * @return array{redirect_type:string,target_url:string,reason:string,match_id:int,match_score:int,score_parts:array,loop_safe:bool}
	 */
	public static function build_redirect_plan_static( $product ): array {
		$timeline  = Shojaei_SEO_Helpers::get_oos_timeline();
		$auto_type = $timeline['auto_type'];
		$threshold = (int) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_match_threshold', 70 );
		$match     = class_exists( 'Shojaei_SEO_Redirect_Engine' )
			? Shojaei_SEO_Redirect_Engine::find_best_replacement( $product, $threshold )
			: array( 'id' => 0, 'score' => 0 );

		if ( ! empty( $match['id'] ) ) {
			$target = get_permalink( (int) $match['id'] );
			$valid  = Shojaei_SEO_Redirect_Engine::validate_redirect(
				(string) get_permalink( $product->get_id() ),
				(string) $target,
				(int) $product->get_id()
			);

			if ( ! is_wp_error( $valid ) ) {
				return array(
					'redirect_type' => $auto_type,
					'target_url'    => $target,
					'reason'        => 'relevance_match',
					'match_id'      => (int) $match['id'],
					'match_score'   => (int) ( $match['score'] ?? 0 ),
					'score_parts'   => $match['parts'] ?? array(),
					'loop_safe'     => true,
				);
			}
		}

		$category_url = Shojaei_SEO_Helpers::get_primary_category_url( $product->get_id() );
		$cat_valid    = Shojaei_SEO_Redirect_Engine::validate_redirect(
			(string) get_permalink( $product->get_id() ),
			(string) $category_url,
			(int) $product->get_id()
		);

		return array(
			'redirect_type' => $auto_type,
			'target_url'    => ! is_wp_error( $cat_valid ) ? $category_url : home_url( '/shop/' ),
			'reason'        => 'auto_category',
			'match_id'      => 0,
			'match_score'   => 0,
			'score_parts'   => array(),
			'loop_safe'     => ! is_wp_error( $cat_valid ),
		);
	}

	/**
	 * Build suggested redirect plan without applying.
	 *
	 * @param WC_Product $product Product.
	 * @return array{redirect_type:string,target_url:string,reason:string,match_id:int,match_score:int,score_parts:array,loop_safe:bool}
	 */
	public function build_redirect_plan( $product ): array {
		return self::build_redirect_plan_static( $product );
	}

	/**
	 * Fast plan for admin diagnose — category/home only (no catalog similarity scan).
	 *
	 * @param WC_Product $product Product.
	 * @return array{redirect_type:string,target_url:string,reason:string,match_id:int,match_score:int,score_parts:array,loop_safe:bool}
	 */
	public static function build_quick_redirect_plan_static( $product ): array {
		$timeline  = Shojaei_SEO_Helpers::get_oos_timeline();
		$auto_type = $timeline['auto_type'] ?? '302';
		$category  = Shojaei_SEO_Helpers::get_primary_category_url( $product->get_id() );
		$source    = (string) get_permalink( $product->get_id() );
		$valid     = class_exists( 'Shojaei_SEO_Redirect_Engine' )
			? Shojaei_SEO_Redirect_Engine::validate_redirect( $source, (string) $category, (int) $product->get_id() )
			: true;

		return array(
			'redirect_type' => $auto_type,
			'target_url'    => ! is_wp_error( $valid ) ? $category : home_url( '/shop/' ),
			'reason'        => 'quick_category',
			'match_id'      => 0,
			'match_score'   => 0,
			'score_parts'   => array(),
			'loop_safe'     => ! is_wp_error( $valid ),
		);
	}

	/**
	 * Diagnose a product for the test panel.
	 *
	 * @param int $product_id Product ID.
	 * @return array
	 */
	public function diagnose_product( int $product_id ): array {
		global $wpdb;

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return array( 'error' => __( 'محصول یافت نشد.', 'shojaei-seo-for-woo' ) );
		}

		$table  = Shojaei_SEO_Helpers::oos_table();
		$record = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE product_id = %d", $product_id )
		);

		$days  = $record ? (int) $record->days_oos : 0;
		// Repair absurd Jalali-as-Gregorian day counts on the fly.
		if ( $record && ( $days > 2000 || ! Shojaei_SEO_Helpers::is_plausible_oos_datetime( (string) $record->oos_date ) ) ) {
			( new Damavand_OOS_Detector() )->refresh_oos_days_public( $product_id );
			$record = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$table} WHERE product_id = %d", $product_id )
			);
			$days = $record ? (int) $record->days_oos : 0;
		}

		$phase = $record ? Shojaei_SEO_Helpers::get_oos_phase( $days ) : 0;
		$state = $record ? Shojaei_SEO_Helpers::get_oos_state( $days ) : null;
		$plan  = null;

		$robots_note = '';
		if ( $record && $phase >= Shojaei_SEO_Helpers::get_noindex_from_phase() && 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_noindex_enabled', 'yes' ) ) {
			$robots_note = 'noindex, follow';
		} else {
			$robots_note = 'index, follow (پیش‌فرض)';
		}

		$type = $product->get_type();
		$stock_detail = '';
		if ( $product->is_type( 'variable' ) ) {
			$children = $product->get_children();
			$in_stock = 0;
			foreach ( $children as $vid ) {
				$v = wc_get_product( $vid );
				if ( $v && $v->is_in_stock() ) {
					$in_stock++;
				}
			}
			$stock_detail = sprintf(
				/* translators: 1: in stock vars, 2: total */
				__( '%1$d از %2$d variation موجود', 'shojaei-seo-for-woo' ),
				$in_stock,
				count( $children )
			);
		}

		// Cached page value first — avoid slow recalculation on every View click.
		$page_value = null;
		if ( class_exists( 'Shojaei_SEO_Page_Value' ) ) {
			$cached = (int) Shojaei_SEO_Page_Value::get_score( $product_id, false );
			$page_value = array(
				'score'           => $cached,
				'requires_manual' => Shojaei_SEO_Page_Value::requires_manual( $product_id ),
			);
		}

		// Light rule eval for flags; full redirect plan for real similarity scores.
		$rule = class_exists( 'Shojaei_SEO_Rule_Engine' )
			? Shojaei_SEO_Rule_Engine::evaluate_product( $product_id, true )
			: null;

		$is_oos = ! $product->is_in_stock()
			|| ( $product->is_type( 'variable' ) && ! Shojaei_SEO_Helpers::variable_product_has_stock( $product_id ) );

		if ( $is_oos ) {
			$plan = self::build_redirect_plan_static( $product );
		} elseif ( $rule && ! empty( $rule->redirect_plan ) ) {
			$plan = $rule->redirect_plan;
		}

		if ( $rule ) {
			$robots_note = $rule->apply_noindex ? 'noindex, follow' : 'index, follow (پیش‌فرض)';
		}

		return array(
			'product_id'   => $product_id,
			'title'        => $product->get_name(),
			'type'         => $type,
			'permalink'    => get_permalink( $product_id ),
			'in_stock'     => $product->is_in_stock(),
			'stock_detail' => $stock_detail,
			'tracked'      => (bool) $record,
			'oos_status'   => $record->status ?? '',
			'oos_date'     => $record->oos_date ?? get_post_meta( $product_id, '_shojaei_seo_oos_date', true ),
			'days_oos'     => $days,
			'phase'        => $phase,
			'lifecycle'    => $state['type'] ?? '',
			'lifecycle_label' => $state['label'] ?? '',
			'redirect_type'=> $record->redirect_type ?? 'none',
			'target_url'   => $record->target_url ?? '',
			'robots'       => $robots_note,
			'dry_run'      => 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_dry_run', 'yes' ),
			'plan'         => $plan,
			'page_value'   => $page_value,
			'rank_math'    => Shojaei_SEO_Helpers::is_rank_math_active(),
			'yoast'        => Shojaei_SEO_Helpers::is_yoast_active(),
			'seo_plugins'  => class_exists( 'Shojaei_SEO_Integration' ) ? Shojaei_SEO_Integration::detected_labels() : '',
			'schema_mode'  => class_exists( 'Shojaei_SEO_Integration' )
				? Shojaei_SEO_Integration::schema_mode_label()
				: ( Shojaei_SEO_Helpers::is_rank_math_active()
					? __( 'فقط FAQ تکمیلی (Rank Math فعال)', 'shojaei-seo-for-woo' )
					: __( 'Product + Breadcrumb فعال', 'shojaei-seo-for-woo' ) ),
			'rule_engine'  => $rule ? array(
				'primary_action' => $rule->primary_action,
				'primary_label'  => Shojaei_SEO_Rule_Engine::action_label( $rule->primary_action ),
				'apply_mode'     => $rule->redirect_apply_mode,
				'show_replacements' => $rule->show_replacements,
				'reduce_link_priority' => $rule->reduce_link_priority,
				'remove_from_sitemap'  => $rule->remove_from_sitemap,
				'traces'         => $rule->traces,
			) : null,
		);
	}

	/**
	 * Find best match with score details (legacy wrapper → Redirect Engine).
	 *
	 * @param WC_Product $product   Source product.
	 * @param int        $threshold Threshold.
	 * @return array{id:int,score:int,parts?:array,skipped_loops?:int}
	 */
	public function find_best_match_detailed( $product, int $threshold ): array {
		if ( class_exists( 'Shojaei_SEO_Redirect_Engine' ) ) {
			return Shojaei_SEO_Redirect_Engine::find_best_replacement( $product, $threshold );
		}

		return array( 'id' => 0, 'score' => 0 );
	}

	/**
	 * Find best matching in-stock product by title similarity.
	 *
	 * @param WC_Product $product   Source product.
	 * @param int        $threshold Minimum similarity percentage.
	 * @return int|null Product ID or null.
	 */
	public function find_best_match( $product, int $threshold ): ?int {
		$data = $this->find_best_match_detailed( $product, $threshold );
		return $data['id'] ? $data['id'] : null;
	}

	/**
	 * Apply manual redirect from admin.
	 *
	 * @param int         $product_id    Product ID.
	 * @param string      $redirect_type 301, 302 or 410.
	 * @param string      $target_url    Target URL.
	 * @param bool        $force_confirm Allow high page-value override.
	 * @param string|null $batch_id      Optional revert-log batch.
	 * @return true|WP_Error
	 */
	public function apply_manual_redirect( int $product_id, string $redirect_type, string $target_url = '', bool $force_confirm = false, ?string $batch_id = null ) {
		global $wpdb;

		$redirect_type = Shojaei_SEO_Helpers::sanitize_redirect_type( $redirect_type );
		$before        = class_exists( 'Shojaei_SEO_Revert_Log' ) ? Shojaei_SEO_Revert_Log::snapshot_full( $product_id ) : array();

		if ( '410' !== $redirect_type ) {
			$target_url = esc_url_raw( $target_url );
			if ( empty( $target_url ) ) {
				return new WP_Error( 'missing_target', __( 'آدرس مقصد الزامی است.', 'shojaei-seo-for-woo' ) );
			}

			$source_url = get_permalink( $product_id );
			$loop_check = Shojaei_SEO_Redirect_Engine::validate_redirect(
				(string) $source_url,
				$target_url,
				$product_id
			);
			if ( is_wp_error( $loop_check ) ) {
				return $loop_check;
			}
		} else {
			$target_url = '';
		}

		if ( class_exists( 'Shojaei_SEO_Page_Value' ) && Shojaei_SEO_Page_Value::requires_manual( $product_id ) && ! $force_confirm ) {
			$eval = Shojaei_SEO_Page_Value::evaluate( $product_id );
			return new WP_Error(
				'high_page_value',
				sprintf(
					/* translators: %d: score */
					__( 'این صفحه ارزش بالایی دارد (امتیاز %d). برای ریدایرکت/حذف باید صریحاً تایید کنید.', 'shojaei-seo-for-woo' ),
					$eval['score']
				),
				$eval
			);
		}

		$table  = Shojaei_SEO_Helpers::oos_table();
		$exists = (int) $wpdb->get_var(
			$wpdb->prepare( "SELECT id FROM {$table} WHERE product_id = %d", $product_id )
		);
		$row    = array(
			'status'        => 'redirected',
			'redirect_type' => $redirect_type,
			'target_url'    => $target_url,
		);
		if ( $exists > 0 ) {
			$wpdb->update( $table, $row, array( 'product_id' => $product_id ), array( '%s', '%s', '%s' ), array( '%d' ) );
		} else {
			$wpdb->insert(
				$table,
				array_merge(
					array(
						'product_id' => $product_id,
						'oos_date'   => Shojaei_SEO_Helpers::mysql_datetime(),
						'days_oos'   => 0,
					),
					$row
				),
				array( '%d', '%s', '%d', '%s', '%s', '%s' )
			);
		}
		Shojaei_SEO_Helpers::flush_410_excluded_cache();

		if ( class_exists( 'Shojaei_SEO_Redirect_Engine' ) ) {
			Shojaei_SEO_Redirect_Engine::clear_redirect_map_cache();
		}

		$wpdb->insert(
			Shojaei_SEO_Helpers::redirect_log_table(),
			array(
				'product_id'    => $product_id,
				'redirect_type' => $redirect_type,
				'target_url'    => $target_url,
				'reason'        => $force_confirm ? 'manual_forced' : 'manual',
				'user_id'       => get_current_user_id(),
			),
			array( '%d', '%s', '%s', '%s', '%d' )
		);

		if ( class_exists( 'Shojaei_SEO_Page_Value' ) && $force_confirm ) {
			Shojaei_SEO_Page_Value::set_protected( $product_id, false );
		}

		try {
			if ( '410' === $redirect_type ) {
				Damavand_OOS_Detector::hide_410_from_catalog( $product_id );
				$key   = 'shojaei_seo_stats_redirects';
				$count = (int) Shojaei_SEO_Helpers::get_option( $key, 0 );
				update_option( $key, $count + 1 );
				if ( class_exists( 'Shojaei_SEO_Analytics' ) ) {
					Shojaei_SEO_Analytics::bump( 'gone_410' );
				}

				if ( class_exists( 'Shojaei_SEO_Pulse' ) ) {
					Shojaei_SEO_Pulse::forget_post( $product_id );
				}
				if ( class_exists( 'Damavand_Link_Manager' ) ) {
					Damavand_Link_Manager::purge_post( $product_id );
				}

				$title = get_the_title( $product_id );
				Shojaei_SEO_Notifications::add(
					'gone_410',
					sprintf(
						/* translators: %s: product title */
						__( 'وضعیت 410 Gone برای «%s» اعمال شد.', 'shojaei-seo-for-woo' ),
						$title
					),
					$product_id
				);
			} else {
				Shojaei_SEO_Helpers::increment_stat( 'redirects' );
			}

			$title  = get_the_title( $product_id );
			$action = '410' === $redirect_type ? 'redirect_410' : ( '302' === $redirect_type ? 'redirect_302' : 'redirect_301' );

			if ( class_exists( 'Shojaei_SEO_Revert_Log' ) ) {
				$after = Shojaei_SEO_Revert_Log::snapshot_full( $product_id );
				Shojaei_SEO_Revert_Log::record_applied_oos(
					$batch_id ?: Shojaei_SEO_Revert_Log::new_batch_id(),
					$action,
					$product_id,
					$before,
					$after,
					sprintf(
						/* translators: 1: type, 2: title, 3: url */
						__( 'اعمال %1$s برای «%2$s» → %3$s', 'shojaei-seo-for-woo' ),
						$redirect_type,
						$title,
						$target_url ?: '—'
					)
				);
			}

			if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
				Shojaei_SEO_Activity_Log::add(
					$action,
					sprintf(
						/* translators: 1: type, 2: title, 3: url */
						__( 'اعمال دستی %1$s برای «%2$s» → %3$s', 'shojaei-seo-for-woo' ),
						$redirect_type,
						$title,
						$target_url ?: '—'
					),
					$product_id,
					array( 'redirect_type' => $redirect_type, 'target_url' => $target_url, 'forced' => $force_confirm )
				);
			}

			if ( class_exists( 'Shojaei_SEO_Cache' ) ) {
				Shojaei_SEO_Cache::on_seo_state_change( $product_id );
			}

			if ( class_exists( 'Shojaei_SEO_GSC' ) ) {
				$event = ( '410' === $redirect_type ) ? 'gone' : 'redirect';
				Shojaei_SEO_GSC::notify_product_change( $product_id, $event );
			}

			/**
			 * Fires after OOS manual redirect/410 is saved.
			 *
			 * @param int    $product_id     Product ID.
			 * @param string $redirect_type  301|302|410.
			 * @param string $target_url     Target URL (empty for 410).
			 */
			do_action( 'damavand_seo_redirect_applied', $product_id, $redirect_type, $target_url );
		} catch ( Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
				error_log( 'Damavand OOS redirect side-effect: ' . $e->getMessage() );
			}
		}

		return true;
	}

	/**
	 * Undo a redirect and restore product to OOS tracking.
	 *
	 * @param int $product_id Product ID.
	 * @param int $log_id     Optional log entry ID.
	 * @return bool
	 */
	public function undo_redirect( int $product_id, int $log_id = 0 ): bool {
		global $wpdb;

		$table  = Shojaei_SEO_Helpers::oos_table();
		$record = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE product_id = %d", $product_id )
		);

		if ( ! $record || 'redirected' !== $record->status ) {
			return false;
		}

		$was_410 = ( '410' === (string) $record->redirect_type );

		$days   = (int) $record->days_oos;
		$state  = Shojaei_SEO_Helpers::get_oos_state( $days );
		$status = $state['status'];

		$wpdb->update(
			$table,
			array(
				'status'        => $status,
				'redirect_type' => 'none',
				'target_url'    => '',
			),
			array( 'product_id' => $product_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		Shojaei_SEO_Helpers::flush_410_excluded_cache();
		if ( $was_410 ) {
			Damavand_OOS_Detector::restore_410_catalog( $product_id );
		}

		$log_table = Shojaei_SEO_Helpers::redirect_log_table();

		if ( $log_id > 0 ) {
			$wpdb->update(
				$log_table,
				array( 'is_undone' => 1 ),
				array( 'id' => $log_id ),
				array( '%d' ),
				array( '%d' )
			);
		} else {
			$wpdb->query(
				$wpdb->prepare(
					"UPDATE {$log_table} SET is_undone = 1 WHERE product_id = %d AND is_undone = 0 ORDER BY id DESC LIMIT 1",
					$product_id
				)
			);
		}

		$wpdb->insert(
			$log_table,
			array(
				'product_id'    => $product_id,
				'redirect_type' => 'none',
				'target_url'    => '',
				'reason'        => 'undo',
				'user_id'       => get_current_user_id(),
			),
			array( '%d', '%s', '%s', '%s', '%d' )
		);

		Shojaei_SEO_Helpers::decrement_stat( 'redirects' );

		if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
			Shojaei_SEO_Activity_Log::add(
				'undo_redirect',
				sprintf(
					/* translators: %s: product title */
					__( 'لغو ریدایرکت برای «%s»', 'shojaei-seo-for-woo' ),
					get_the_title( $product_id )
				),
				$product_id
			);
		}

		if ( class_exists( 'Shojaei_SEO_Cache' ) ) {
			Shojaei_SEO_Cache::on_seo_state_change( $product_id );
		}

		if ( class_exists( 'Shojaei_SEO_GSC' ) ) {
			Shojaei_SEO_GSC::notify_product_change( $product_id, 'undo' );
		}

		return true;
	}

	/**
	 * Apply bulk redirect or keep action.
	 *
	 * @param array       $product_ids Product IDs.
	 * @param string      $action      Action slug.
	 * @param string|null $target_url  Optional shared target URL.
	 * @return int Number of processed items.
	 */
	public function bulk_action( array $product_ids, string $action, ?string $target_url = null, bool $force_confirm = false, ?string $batch_id = null ): int {
		$processed = 0;
		if ( null === $batch_id && class_exists( 'Shojaei_SEO_Revert_Log' ) ) {
			$batch_id = Shojaei_SEO_Revert_Log::new_batch_id();
		}

		foreach ( $product_ids as $product_id ) {
			$product_id = absint( $product_id );
			if ( ! $product_id ) {
				continue;
			}

			switch ( $action ) {
				case 'redirect_301':
				case 'redirect_302':
					$url = $target_url ?: $this->get_suggested_target_url( $product_id );
					if ( $url ) {
						$type   = 'redirect_301' === $action ? '301' : '302';
						$result = $this->apply_manual_redirect( $product_id, $type, $url, $force_confirm, $batch_id );
						if ( ! is_wp_error( $result ) ) {
							$processed++;
						}
					}
					break;
				case 'redirect_410':
					$result = $this->apply_manual_redirect( $product_id, '410', '', $force_confirm, $batch_id );
					if ( ! is_wp_error( $result ) ) {
						$processed++;
					}
					break;
				case 'keep':
					$this->keep_page( $product_id, $batch_id );
					$processed++;
					break;
			}
		}

		return $processed;
	}

	/**
	 * Get suggested redirect target for a product.
	 *
	 * @param int $product_id Product ID.
	 * @return string
	 */
	public function get_suggested_target_url( int $product_id ): string {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return Shojaei_SEO_Helpers::get_primary_category_url( $product_id );
		}

		$plan = $this->build_redirect_plan( $product );
		return ! empty( $plan['target_url'] ) ? (string) $plan['target_url'] : Shojaei_SEO_Helpers::get_primary_category_url( $product_id );
	}

	/**
	 * Keep page without redirect.
	 *
	 * @param int         $product_id Product ID.
	 * @param string|null $batch_id   Optional batch.
	 */
	public function keep_page( int $product_id, ?string $batch_id = null ): void {
		global $wpdb;

		$before = class_exists( 'Shojaei_SEO_Revert_Log' ) ? Shojaei_SEO_Revert_Log::snapshot_full( $product_id ) : array();

		$record = $wpdb->get_row(
			$wpdb->prepare(
				'SELECT days_oos, oos_date FROM ' . Shojaei_SEO_Helpers::oos_table() . ' WHERE product_id = %d',
				$product_id
			)
		);

		$days  = $record ? (int) $record->days_oos : 0;
		$state = Shojaei_SEO_Helpers::get_oos_state( $days );

		$wpdb->update(
			Shojaei_SEO_Helpers::oos_table(),
			array( 'status' => $state['status'] === 'candidate_redirect' ? 'permanent_oos' : $state['status'], 'redirect_type' => 'none', 'target_url' => '' ),
			array( 'product_id' => $product_id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		if ( class_exists( 'Shojaei_SEO_Page_Value' ) ) {
			Shojaei_SEO_Page_Value::set_protected( $product_id, true );
		}

		if ( $record ) {
			Shojaei_SEO_Helpers::sync_oos_postmeta( $product_id, (string) $record->oos_date, $days );
		}

		if ( class_exists( 'Shojaei_SEO_Revert_Log' ) ) {
			$after = Shojaei_SEO_Revert_Log::snapshot_full( $product_id );
			Shojaei_SEO_Revert_Log::record_applied_oos(
				$batch_id ?: Shojaei_SEO_Revert_Log::new_batch_id(),
				'keep_page',
				$product_id,
				$before,
				$after,
				sprintf(
					/* translators: %s: product title */
					__( 'نگهداری صفحه (محافظت دستی) برای «%s»', 'shojaei-seo-for-woo' ),
					get_the_title( $product_id )
				)
			);
		}

		if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
			Shojaei_SEO_Activity_Log::add(
				'keep_page',
				sprintf(
					/* translators: %s: product title */
					__( 'نگهداری صفحه (محافظت دستی) برای «%s»', 'shojaei-seo-for-woo' ),
					get_the_title( $product_id )
				),
				$product_id
			);
		}
	}
}
