<?php
/**
 * Lightweight SEO Operations Rule Engine — central decisions for inventory-aware SEO.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Input context for one product evaluation.
 */
class Shojaei_SEO_Rule_Context {

	/** @var int */
	public int $product_id = 0;

	/** @var WC_Product|null */
	public $product = null;

	/** @var string in_stock|outofstock|onbackorder */
	public string $stock_status = 'instock';

	/** @var bool Fully OOS (incl. variable). */
	public bool $is_fully_oos = false;

	/** @var int */
	public int $outofstock_days = 0;

	/** @var string Tracker status or empty. */
	public string $tracker_status = '';

	/** @var string|null */
	public ?string $oos_date = null;

	/** @var bool */
	public bool $has_previous_redirect = false;

	/** @var string Previous redirect type if any. */
	public string $previous_redirect_type = 'none';

	/** @var string */
	public string $previous_target_url = '';

	/** @var int */
	public int $primary_category_id = 0;

	/** @var string */
	public string $primary_category_name = '';

	/** @var string */
	public string $brand = '';

	/** @var bool Lazy-filled by engine. */
	public bool $has_replacement = false;

	/** @var array|null Redirect plan from manager/engine. */
	public ?array $redirect_plan = null;

	/** @var array|null Page value evaluate(). */
	public ?array $page_value = null;

	/** @var bool */
	public bool $requires_manual = false;

	/** @var string index|noindex (hint from current robots / phase). */
	public string $index_status = 'index';

	/** @var array Timeline options snapshot. */
	public array $timeline = array();

	/** @var bool */
	public bool $auto_redirect_enabled = true;

	/** @var bool */
	public bool $dry_run = true;

	/** @var bool */
	public bool $noindex_enabled = true;

	/** @var int */
	public int $noindex_from_phase = 2;

	/** @var int */
	public int $match_threshold = 70;

	/**
	 * Build context from product ID.
	 *
	 * @param int  $product_id Product ID.
	 * @param bool $light      Skip heavy replacement matching (admin diagnose).
	 * @return self|null
	 */
	public static function from_product_id( int $product_id, bool $light = false ): ?self {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return null;
		}

		global $wpdb;
		$ctx = new self();
		$ctx->product_id   = $product_id;
		$ctx->product      = $product;
		$ctx->stock_status = (string) $product->get_stock_status();

		$fully_oos = ! $product->is_in_stock();
		if ( $product->is_type( 'variable' ) ) {
			$fully_oos = ! Shojaei_SEO_Helpers::variable_product_has_stock( $product_id );
		}
		$ctx->is_fully_oos = $fully_oos;

		$table  = Shojaei_SEO_Helpers::oos_table();
		$record = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE product_id = %d", $product_id )
		);

		if ( $record ) {
			$ctx->outofstock_days        = (int) $record->days_oos;
			$ctx->tracker_status         = (string) $record->status;
			$ctx->oos_date               = (string) $record->oos_date;
			$ctx->has_previous_redirect  = ( 'redirected' === $record->status );
			$ctx->previous_redirect_type = (string) ( $record->redirect_type ?? 'none' );
			$ctx->previous_target_url    = (string) ( $record->target_url ?? '' );
		} elseif ( $fully_oos ) {
			$meta_date = (string) get_post_meta( $product_id, '_shojaei_seo_oos_date', true );
			if ( $meta_date ) {
				$ctx->oos_date        = $meta_date;
				$ctx->outofstock_days = (int) floor( ( time() - strtotime( $meta_date ) ) / DAY_IN_SECONDS );
			}
		}

		$terms = wp_get_post_terms( $product_id, 'product_cat' );
		if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
			$ctx->primary_category_id   = (int) $terms[0]->term_id;
			$ctx->primary_category_name = (string) $terms[0]->name;
		}

		$brand_tax = taxonomy_exists( 'product_brand' ) ? 'product_brand' : ( taxonomy_exists( 'pwb-brand' ) ? 'pwb-brand' : '' );
		if ( $brand_tax ) {
			$brands = wp_get_post_terms( $product_id, $brand_tax );
			if ( ! is_wp_error( $brands ) && ! empty( $brands ) ) {
				$ctx->brand = (string) $brands[0]->name;
			}
		}

		$ctx->timeline              = Shojaei_SEO_Helpers::get_oos_timeline();
		$ctx->auto_redirect_enabled = ( 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_auto_redirect', 'yes' ) );
		$ctx->dry_run               = ( 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_dry_run', 'yes' ) );
		$ctx->noindex_enabled       = ( 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_noindex_enabled', 'yes' ) );
		$ctx->noindex_from_phase    = Shojaei_SEO_Helpers::get_noindex_from_phase();
		$ctx->match_threshold       = max( 1, (int) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_match_threshold', 70 ) );

		if ( class_exists( 'Shojaei_SEO_Page_Value' ) ) {
			$ctx->requires_manual = Shojaei_SEO_Page_Value::requires_manual( $product_id );
			if ( $light ) {
				$ctx->page_value = array(
					'score'           => (int) Shojaei_SEO_Page_Value::get_score( $product_id, false ),
					'requires_manual' => $ctx->requires_manual,
				);
			} else {
				$ctx->page_value = Shojaei_SEO_Page_Value::evaluate( $product_id );
			}
		}

		if ( $fully_oos && ! $ctx->has_previous_redirect && class_exists( 'Shojaei_SEO_OOS_Manager' ) ) {
			$ctx->redirect_plan   = $light
				? Shojaei_SEO_OOS_Manager::build_quick_redirect_plan_static( $product )
				: Shojaei_SEO_OOS_Manager::build_redirect_plan_static( $product );
			$ctx->has_replacement = ! empty( $ctx->redirect_plan['match_id'] );
			if ( ! $ctx->has_replacement && ! empty( $ctx->redirect_plan['target_url'] ) ) {
				$ctx->has_replacement = in_array( (string) ( $ctx->redirect_plan['reason'] ?? '' ), array( 'auto_category', 'quick_category' ), true );
			}
		}

		$phase = Shojaei_SEO_Helpers::get_oos_phase( $ctx->outofstock_days );
		if ( $ctx->noindex_enabled && $fully_oos && $phase >= $ctx->noindex_from_phase ) {
			$ctx->index_status = 'noindex';
		}

		return $ctx;
	}
}

/**
 * Decision bag — outputs of the rule engine.
 */
class Shojaei_SEO_Rule_Decision {

	/** @var array Lifecycle state from get_oos_state. */
	public array $lifecycle = array();

	/** @var string Primary action slug. */
	public string $primary_action = 'keep_page';

	/** @var bool */
	public bool $keep_indexed = true;

	/** @var bool */
	public bool $apply_noindex = false;

	/** @var bool */
	public bool $show_replacements = false;

	/** @var bool */
	public bool $reduce_link_priority = false;

	/** @var bool */
	public bool $create_redirect_candidate = false;

	/** @var bool */
	public bool $enqueue_auto_redirect = false;

	/** @var string none|dry_run|apply|needs_manual|blocked */
	public string $redirect_apply_mode = 'none';

	/** @var string 301|302|410|none */
	public string $redirect_type = 'none';

	/** @var string */
	public string $target_url = '';

	/** @var array|null */
	public ?array $redirect_plan = null;

	/** @var bool */
	public bool $remove_from_sitemap = false;

	/** @var string Suggested tracker status update (empty = no change). */
	public string $suggested_status = '';

	/** @var string */
	public string $block_reason = '';

	/** @var string[] Human-readable rule traces. */
	public array $traces = array();

	/** @var string[] Action flags for logging/undo hooks. */
	public array $log_actions = array();

	/**
	 * Add a trace line.
	 *
	 * @param string $rule Rule id.
	 * @param string $message Message.
	 */
	public function trace( string $rule, string $message ): void {
		$this->traces[] = sprintf( '[%s] %s', $rule, $message );
	}

	/**
	 * Export for diagnose / AJAX.
	 *
	 * @return array
	 */
	public function to_array(): array {
		return array(
			'primary_action'            => $this->primary_action,
			'lifecycle'                 => $this->lifecycle,
			'keep_indexed'              => $this->keep_indexed,
			'apply_noindex'             => $this->apply_noindex,
			'show_replacements'         => $this->show_replacements,
			'reduce_link_priority'      => $this->reduce_link_priority,
			'create_redirect_candidate' => $this->create_redirect_candidate,
			'enqueue_auto_redirect'     => $this->enqueue_auto_redirect,
			'redirect_apply_mode'       => $this->redirect_apply_mode,
			'redirect_type'             => $this->redirect_type,
			'target_url'                => $this->target_url,
			'redirect_plan'             => $this->redirect_plan,
			'remove_from_sitemap'       => $this->remove_from_sitemap,
			'suggested_status'          => $this->suggested_status,
			'block_reason'              => $this->block_reason,
			'traces'                    => $this->traces,
			'log_actions'               => $this->log_actions,
		);
	}
}

/**
 * Central Rule Engine for Inventory-aware SEO decisions.
 */
class Shojaei_SEO_Rule_Engine {

	/**
	 * Evaluate all rules for a context.
	 *
	 * @param Shojaei_SEO_Rule_Context $ctx Context.
	 * @return Shojaei_SEO_Rule_Decision
	 */
	public function evaluate( Shojaei_SEO_Rule_Context $ctx ): Shojaei_SEO_Rule_Decision {
		$bag = new Shojaei_SEO_Rule_Decision();

		$this->rule_lifecycle( $ctx, $bag );
		$this->rule_early_oos_keep( $ctx, $bag );
		$this->rule_replacements( $ctx, $bag );
		$this->rule_noindex( $ctx, $bag );
		$this->rule_link_priority( $ctx, $bag );
		$this->rule_redirect_candidate( $ctx, $bag );
		$this->rule_page_value_gate( $ctx, $bag );
		$this->rule_auto_redirect( $ctx, $bag );
		$this->rule_discontinued_policy( $ctx, $bag );
		$this->rule_finalize_primary( $ctx, $bag );

		/**
		 * Filter the decision bag after evaluation.
		 *
		 * @param Shojaei_SEO_Rule_Decision $bag Decision.
		 * @param Shojaei_SEO_Rule_Context  $ctx Context.
		 */
		return apply_filters( 'shojaei_seo_rule_decision', $bag, $ctx );
	}

	/**
	 * Convenience: evaluate by product ID.
	 *
	 * @param int  $product_id Product ID.
	 * @param bool $light      Fast path for admin UI (no heavy match scan).
	 * @return Shojaei_SEO_Rule_Decision|null
	 */
	public static function evaluate_product( int $product_id, bool $light = false ): ?Shojaei_SEO_Rule_Decision {
		$ctx = Shojaei_SEO_Rule_Context::from_product_id( $product_id, $light );
		if ( ! $ctx ) {
			return null;
		}
		$engine = new self();
		return $engine->evaluate( $ctx );
	}

	/**
	 * Whether internal links should avoid pointing at this product URL.
	 *
	 * @param string $url Absolute URL.
	 */
	public static function should_deprioritize_url( string $url ): bool {
		$post_id = url_to_postid( $url );
		if ( ! $post_id || 'product' !== get_post_type( $post_id ) ) {
			return false;
		}
		if ( 'yes' === get_post_meta( $post_id, '_shojaei_seo_link_deprioritized', true ) ) {
			return true;
		}
		$decision = self::evaluate_product( $post_id );
		return $decision ? (bool) $decision->reduce_link_priority : false;
	}

	/**
	 * Persist lightweight decision flags to postmeta (for link builder / sitemap / noindex).
	 * Diffs are recorded into Revert Log so Undo is always available.
	 *
	 * @param int                       $product_id Product ID.
	 * @param Shojaei_SEO_Rule_Decision $bag        Decision.
	 * @param string|null               $batch_id   Optional shared batch.
	 */
	public static function sync_decision_meta( int $product_id, Shojaei_SEO_Rule_Decision $bag, ?string $batch_id = null ): void {
		$before = class_exists( 'Shojaei_SEO_Revert_Log' )
			? Shojaei_SEO_Revert_Log::snapshot_seo_flags( $product_id )
			: array();

		update_post_meta( $product_id, '_shojaei_seo_rule_action', $bag->primary_action );
		update_post_meta( $product_id, '_shojaei_seo_noindex', $bag->apply_noindex ? 'yes' : 'no' );
		update_post_meta( $product_id, '_shojaei_seo_link_deprioritized', $bag->reduce_link_priority ? 'yes' : 'no' );
		update_post_meta( $product_id, '_shojaei_seo_sitemap_exclude', $bag->remove_from_sitemap ? 'yes' : 'no' );

		if ( class_exists( 'Shojaei_SEO_Revert_Log' ) ) {
			$after = Shojaei_SEO_Revert_Log::snapshot_seo_flags( $product_id );
			Shojaei_SEO_Revert_Log::record_flag_diffs( $product_id, $before, $after, $batch_id );
		}
	}

	/**
	 * Rule: lifecycle from days OOS.
	 *
	 * @param Shojaei_SEO_Rule_Context  $ctx Context.
	 * @param Shojaei_SEO_Rule_Decision $bag Bag.
	 */
	private function rule_lifecycle( Shojaei_SEO_Rule_Context $ctx, Shojaei_SEO_Rule_Decision $bag ): void {
		if ( ! $ctx->is_fully_oos ) {
			$bag->lifecycle = array(
				'type'   => 'in_stock',
				'phase'  => 0,
				'status' => 'in_stock',
				'label'  => __( 'موجود', 'shojaei-seo-for-woo' ),
			);
			$bag->primary_action = 'keep_page';
			$bag->trace( 'lifecycle', __( 'محصول موجود است — بدون عملیات OOS.', 'shojaei-seo-for-woo' ) );
			return;
		}

		$bag->lifecycle = Shojaei_SEO_Helpers::get_oos_state( $ctx->outofstock_days );
		if ( 'needs_manual' !== $ctx->tracker_status && 'redirected' !== $ctx->tracker_status ) {
			$bag->suggested_status = (string) $bag->lifecycle['status'];
		}
		$bag->trace(
			'lifecycle',
			sprintf(
				/* translators: 1: days, 2: phase, 3: status */
				__( 'ناموجود %1$d روز → فاز %2$d / وضعیت پیشنهادی %3$s', 'shojaei-seo-for-woo' ),
				$ctx->outofstock_days,
				(int) $bag->lifecycle['phase'],
				(string) $bag->lifecycle['status']
			)
		);
	}

	/**
	 * Rule: early OOS — keep indexed + suggest replacements.
	 *
	 * @param Shojaei_SEO_Rule_Context  $ctx Context.
	 * @param Shojaei_SEO_Rule_Decision $bag Bag.
	 */
	private function rule_early_oos_keep( Shojaei_SEO_Rule_Context $ctx, Shojaei_SEO_Rule_Decision $bag ): void {
		if ( ! $ctx->is_fully_oos || $ctx->has_previous_redirect ) {
			return;
		}

		$message_day = (int) ( $ctx->timeline['message_day'] ?? 15 );
		if ( $ctx->outofstock_days < $message_day ) {
			$bag->keep_indexed       = true;
			$bag->apply_noindex      = false;
			$bag->show_replacements  = true;
			$bag->primary_action     = 'keep_page';
			$bag->log_actions[]      = 'keep_indexed_early_oos';
			$bag->trace(
				'early_oos',
				sprintf(
					/* translators: %d: days threshold */
					__( 'کمتر از %d روز ناموجودی → حفظ ایندکس + پیشنهاد جایگزین.', 'shojaei-seo-for-woo' ),
					$message_day
				)
			);
		}
	}

	/**
	 * Rule: show replacements when OOS.
	 *
	 * @param Shojaei_SEO_Rule_Context  $ctx Context.
	 * @param Shojaei_SEO_Rule_Decision $bag Bag.
	 */
	private function rule_replacements( Shojaei_SEO_Rule_Context $ctx, Shojaei_SEO_Rule_Decision $bag ): void {
		if ( ! $ctx->is_fully_oos || $ctx->has_previous_redirect ) {
			return;
		}

		$bag->show_replacements = true;
		$bag->redirect_plan     = $ctx->redirect_plan;
		if ( $ctx->has_replacement ) {
			$bag->trace( 'replacement', __( 'مسیر جایگزین/دسته برای پیشنهاد یا ریدایرکت موجود است.', 'shojaei-seo-for-woo' ) );
		} else {
			$bag->trace( 'replacement', __( 'جایگزین قوی یافت نشد — سیاست دسته/۴۱۰ ممکن است اعمال شود.', 'shojaei-seo-for-woo' ) );
		}
	}

	/**
	 * Rule: noindex by phase policy.
	 *
	 * @param Shojaei_SEO_Rule_Context  $ctx Context.
	 * @param Shojaei_SEO_Rule_Decision $bag Bag.
	 */
	private function rule_noindex( Shojaei_SEO_Rule_Context $ctx, Shojaei_SEO_Rule_Decision $bag ): void {
		if ( ! $ctx->is_fully_oos || ! $ctx->noindex_enabled || $ctx->has_previous_redirect ) {
			return;
		}

		$phase = (int) ( $bag->lifecycle['phase'] ?? 0 );
		if ( $phase >= $ctx->noindex_from_phase ) {
			$bag->apply_noindex  = true;
			$bag->keep_indexed   = false;
			$bag->remove_from_sitemap = ( $phase >= 3 );
			$bag->log_actions[]  = 'apply_noindex';
			$bag->trace(
				'noindex',
				sprintf(
					/* translators: 1: phase, 2: from */
					__( 'فاز %1$d ≥ آستانه %2$d → noindex,follow.', 'shojaei-seo-for-woo' ),
					$phase,
					$ctx->noindex_from_phase
				)
			);
		}
	}

	/**
	 * Rule: reduce internal link priority for long OOS.
	 *
	 * @param Shojaei_SEO_Rule_Context  $ctx Context.
	 * @param Shojaei_SEO_Rule_Decision $bag Bag.
	 */
	private function rule_link_priority( Shojaei_SEO_Rule_Context $ctx, Shojaei_SEO_Rule_Decision $bag ): void {
		if ( ! $ctx->is_fully_oos || $ctx->has_previous_redirect ) {
			return;
		}

		$temp_days = (int) ( $ctx->timeline['temp_days'] ?? 30 );
		if ( $ctx->outofstock_days >= $temp_days ) {
			$bag->reduce_link_priority = true;
			$bag->log_actions[]        = 'reduce_link_priority';
			$bag->trace( 'link_priority', __( 'ناموجود طولانی → کاهش اولویت لینک‌سازی داخلی به این URL.', 'shojaei-seo-for-woo' ) );
		}
	}

	/**
	 * Rule: create redirect candidate when durable OOS + replacement.
	 *
	 * @param Shojaei_SEO_Rule_Context  $ctx Context.
	 * @param Shojaei_SEO_Rule_Decision $bag Bag.
	 */
	private function rule_redirect_candidate( Shojaei_SEO_Rule_Context $ctx, Shojaei_SEO_Rule_Decision $bag ): void {
		if ( ! $ctx->is_fully_oos || $ctx->has_previous_redirect ) {
			return;
		}

		$temp_days = (int) ( $ctx->timeline['temp_days'] ?? 30 );
		$phase     = (int) ( $bag->lifecycle['phase'] ?? 0 );

		if ( $ctx->outofstock_days >= $temp_days && $ctx->has_replacement ) {
			$bag->create_redirect_candidate = true;
			$bag->suggested_status          = 'candidate_redirect';
			$bag->primary_action            = 'create_redirect_candidate';
			$bag->log_actions[]             = 'create_redirect_candidate';
			$bag->trace(
				'redirect_candidate',
				sprintf(
					/* translators: 1: days, 2: phase */
					__( '≥%1$d روز + جایگزین → کاندید ریدایرکت (فاز %2$d).', 'shojaei-seo-for-woo' ),
					$temp_days,
					$phase
				)
			);
		} elseif ( $phase >= 4 ) {
			$bag->create_redirect_candidate = true;
			$bag->suggested_status          = 'candidate_redirect';
			$bag->log_actions[]             = 'create_redirect_candidate';
			$bag->trace( 'redirect_candidate', __( 'فاز اتوماسیون → کاندید ریدایرکت.', 'shojaei-seo-for-woo' ) );
		}
	}

	/**
	 * Rule: Page Value gate.
	 *
	 * @param Shojaei_SEO_Rule_Context  $ctx Context.
	 * @param Shojaei_SEO_Rule_Decision $bag Bag.
	 */
	private function rule_page_value_gate( Shojaei_SEO_Rule_Context $ctx, Shojaei_SEO_Rule_Decision $bag ): void {
		if ( ! $ctx->is_fully_oos || $ctx->has_previous_redirect ) {
			return;
		}

		if ( ! $ctx->requires_manual ) {
			return;
		}

		$score = (int) ( $ctx->page_value['score'] ?? 0 );
		$bag->redirect_apply_mode = 'needs_manual';
		$bag->enqueue_auto_redirect = false;
		$bag->suggested_status    = 'needs_manual';
		$bag->primary_action      = 'needs_manual';
		$bag->block_reason        = 'high_page_value';
		$bag->log_actions[]       = 'needs_manual';
		$bag->trace(
			'page_value',
			sprintf(
				/* translators: %d: score */
				__( 'Page Value بالا (امتیاز %d) → ریدایرکت خودکار ممنوع؛ تایید دستی.', 'shojaei-seo-for-woo' ),
				$score
			)
		);
	}

	/**
	 * Rule: auto redirect eligibility + dry-run.
	 *
	 * @param Shojaei_SEO_Rule_Context  $ctx Context.
	 * @param Shojaei_SEO_Rule_Decision $bag Bag.
	 */
	private function rule_auto_redirect( Shojaei_SEO_Rule_Context $ctx, Shojaei_SEO_Rule_Decision $bag ): void {
		if ( ! $ctx->is_fully_oos || $ctx->has_previous_redirect ) {
			return;
		}
		if ( 'needs_manual' === $bag->redirect_apply_mode || 'needs_manual' === $ctx->tracker_status ) {
			$bag->enqueue_auto_redirect = false;
			return;
		}

		$phase    = (int) ( $bag->lifecycle['phase'] ?? 0 );
		$auto_day = (int) ( $ctx->timeline['auto_day'] ?? 45 );

		if ( $phase < 4 || $ctx->outofstock_days < $auto_day || ! $ctx->auto_redirect_enabled ) {
			return;
		}

		$bag->enqueue_auto_redirect = true;
		$bag->create_redirect_candidate = true;

		$plan = $ctx->redirect_plan;
		if ( is_array( $plan ) ) {
			$bag->redirect_plan = $plan;
			$bag->redirect_type = (string) ( $plan['redirect_type'] ?? ( $ctx->timeline['auto_type'] ?? '302' ) );
			$bag->target_url    = (string) ( $plan['target_url'] ?? '' );
		}

		// Loop safety already in plan; if invalid target, block.
		if ( $bag->target_url && class_exists( 'Shojaei_SEO_Redirect_Engine' ) ) {
			$loop = Shojaei_SEO_Redirect_Engine::validate_redirect(
				(string) get_permalink( $ctx->product_id ),
				$bag->target_url,
				$ctx->product_id
			);
			if ( is_wp_error( $loop ) ) {
				$bag->enqueue_auto_redirect = false;
				$bag->redirect_apply_mode   = 'blocked';
				$bag->block_reason          = 'redirect_loop';
				$bag->trace( 'auto_redirect', $loop->get_error_message() );
				return;
			}
		}

		if ( $ctx->dry_run ) {
			$bag->redirect_apply_mode = 'dry_run';
			$bag->primary_action      = 'dry_run_suggest';
			$bag->log_actions[]       = 'dry_run';
			$bag->trace( 'auto_redirect', __( 'واجد شرایط اتوماسیون — Dry-Run: فقط پیشنهاد.', 'shojaei-seo-for-woo' ) );
			return;
		}

		$bag->redirect_apply_mode = 'apply';
		$bag->primary_action      = 'apply_redirect';
		$bag->log_actions[]       = 'apply_redirect';
		$bag->log_actions[]       = 'record_undo';
		$bag->trace( 'auto_redirect', __( 'واجد شرایط اتوماسیون — اعمال ریدایرکت.', 'shojaei-seo-for-woo' ) );
	}

	/**
	 * Rule: discontinued / no replacement → noindex or 410 policy.
	 *
	 * @param Shojaei_SEO_Rule_Context  $ctx Context.
	 * @param Shojaei_SEO_Rule_Decision $bag Bag.
	 */
	private function rule_discontinued_policy( Shojaei_SEO_Rule_Context $ctx, Shojaei_SEO_Rule_Decision $bag ): void {
		if ( ! $ctx->is_fully_oos || $ctx->has_previous_redirect ) {
			return;
		}
		if ( 'needs_manual' === $bag->redirect_apply_mode ) {
			return;
		}

		$auto_day = (int) ( $ctx->timeline['auto_day'] ?? 45 );
		$phase    = (int) ( $bag->lifecycle['phase'] ?? 0 );

		// Long-term OOS without product-level replacement (only category/shop).
		$strong_replacement = ! empty( $ctx->redirect_plan['match_id'] );
		if ( $phase >= 4 && $ctx->outofstock_days >= $auto_day && ! $strong_replacement ) {
			$bag->apply_noindex       = true;
			$bag->keep_indexed        = false;
			$bag->remove_from_sitemap = true;
			// Policy: prefer noindex over automatic 410 (safer). Manual 410 remains available.
			if ( 'none' === $bag->redirect_apply_mode || 'dry_run' === $bag->redirect_apply_mode ) {
				$bag->trace( 'discontinued', __( 'بدون جایگزین قوی در فاز نهایی → noindex + حذف از sitemap (۴۱۰ فقط دستی).', 'shojaei-seo-for-woo' ) );
				$bag->log_actions[] = 'remove_from_sitemap';
				if ( 'apply_redirect' !== $bag->primary_action && 'dry_run_suggest' !== $bag->primary_action ) {
					$bag->primary_action = 'apply_noindex';
				}
			}
		}
	}

	/**
	 * Finalize primary action label consistency.
	 *
	 * @param Shojaei_SEO_Rule_Context  $ctx Context.
	 * @param Shojaei_SEO_Rule_Decision $bag Bag.
	 */
	private function rule_finalize_primary( Shojaei_SEO_Rule_Context $ctx, Shojaei_SEO_Rule_Decision $bag ): void {
		if ( ! $ctx->is_fully_oos ) {
			$bag->primary_action = 'keep_page';
			return;
		}
		if ( $ctx->has_previous_redirect ) {
			$bag->primary_action = 'already_redirected';
			$bag->trace( 'finalize', __( 'ریدایرکت قبلی ثبت شده است.', 'shojaei-seo-for-woo' ) );
			return;
		}

		$priority = array(
			'needs_manual',
			'apply_redirect',
			'dry_run_suggest',
			'create_redirect_candidate',
			'apply_noindex',
			'keep_page',
		);
		foreach ( $priority as $action ) {
			if ( $action === $bag->primary_action ) {
				return;
			}
		}
		if ( $bag->create_redirect_candidate ) {
			$bag->primary_action = 'create_redirect_candidate';
		} elseif ( $bag->apply_noindex ) {
			$bag->primary_action = 'apply_noindex';
		} else {
			$bag->primary_action = 'keep_page';
		}
	}

	/**
	 * Human labels for actions (admin/test).
	 *
	 * @param string $action Action slug.
	 */
	public static function action_label( string $action ): string {
		$map = array(
			'keep_page'                 => __( 'حفظ صفحه بدون تغییر اجباری', 'shojaei-seo-for-woo' ),
			'show_replacements'         => __( 'نمایش پیشنهاد جایگزین', 'shojaei-seo-for-woo' ),
			'apply_noindex'             => __( 'فعال‌سازی noindex', 'shojaei-seo-for-woo' ),
			'create_redirect_candidate' => __( 'ثبت کاندید ریدایرکت', 'shojaei-seo-for-woo' ),
			'apply_redirect'            => __( 'اعمال ریدایرکت', 'shojaei-seo-for-woo' ),
			'dry_run_suggest'           => __( 'پیشنهاد Dry-Run (بدون اعمال)', 'shojaei-seo-for-woo' ),
			'needs_manual'              => __( 'توقف — تایید دستی (Page Value)', 'shojaei-seo-for-woo' ),
			'already_redirected'        => __( 'قبلاً ریدایرکت شده', 'shojaei-seo-for-woo' ),
			'blocked'                   => __( 'مسدود (حلقه/سیاست)', 'shojaei-seo-for-woo' ),
		);
		return $map[ $action ] ?? $action;
	}
}
