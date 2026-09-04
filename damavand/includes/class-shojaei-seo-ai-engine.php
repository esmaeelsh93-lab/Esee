<?php
/**
 * Cloud AI AJAX engine — product content generation.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_AI_Engine
 */
class Shojaei_SEO_AI_Engine {

	public const JOB_ALT = 'ai_alt_batch';

	/** @var array<int,string> */
	private static $kinds = array(
		'keywords',
		'meta_titles',
		'meta_desc',
		'short_desc',
		'long_desc',
		'faq',
		'alt_texts',
		'full_pack',
		'llms_txt',
		'slug',
	);

	/**
	 * Init hooks.
	 */
	public static function init(): void {
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin' ), 25 );

		$actions = array(
			'shojaei_ai_generate'        => 'ajax_generate',
			'shojaei_ai_test_connection' => 'ajax_test_connection',
			'shojaei_ai_itemlist'        => 'ajax_itemlist',
			'shojaei_ai_write_llms'      => 'ajax_write_llms',
			'shojaei_ai_bulk_alt_start'  => 'ajax_bulk_alt_start',
			'shojaei_ai_bulk_alt_status' => 'ajax_bulk_alt_status',
			'shojaei_ai_bulk_alt_run'    => 'ajax_bulk_alt_run',
			'shojaei_ai_validate_seo'    => 'ajax_validate_seo',
		);
		foreach ( $actions as $hook => $method ) {
			add_action( 'wp_ajax_' . $hook, array( __CLASS__, $method ) );
		}
	}

	/**
	 * @param string $hook Hook.
	 */
	public static function enqueue_admin( string $hook ): void {
		$load = in_array( $hook, array( 'post.php', 'post-new.php' ), true );
		if ( ! $load && false !== strpos( $hook, 'shojaei-seo' ) ) {
			$load = true;
		}
		if ( ! $load ) {
			return;
		}

		if ( false !== strpos( $hook, 'shojaei-seo' ) && 'post.php' !== $hook && 'post-new.php' !== $hook ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
			$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore
			if ( 'settings' !== $tab && 'shojaei-seo-settings' !== $page ) {
				return;
			}
		}

		wp_enqueue_script(
			'damavand-ai',
			SHOJAEI_SEO_PLUGIN_URL . 'admin/js/damavand-ai.js',
			array( 'jquery' ),
			SHOJAEI_SEO_VERSION,
			true
		);
		wp_localize_script(
			'damavand-ai',
			'damavandAI',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'shojaei_ai' ),
				'enabled'   => Shojaei_SEO_AI_Client::is_configured(),
				'draftMode' => class_exists( 'Shojaei_SEO_Store_Profile' ) && Shojaei_SEO_Store_Profile::draft_mode(),
				'modelPresets' => Shojaei_SEO_AI_Client::model_presets(),
				'i18n'      => array(
					'working'   => __( 'در حال تولید…', 'shojaei-seo-for-woo' ),
					'error'     => __( 'خطا در ارتباط با سرور.', 'shojaei-seo-for-woo' ),
					'done'      => __( 'انجام شد.', 'shojaei-seo-for-woo' ),
					'pickTitle' => __( 'یک عنوان را انتخاب کنید.', 'shojaei-seo-for-woo' ),
					'testing'   => __( 'در حال تست اتصال…', 'shojaei-seo-for-woo' ),
					'timeout'   => __( 'زمان انتظار تمام شد. دوباره تلاش کنید.', 'shojaei-seo-for-woo' ),
					'autoSeo'   => __( 'سئو خودکار', 'shojaei-seo-for-woo' ),
					'rateLimit' => __( 'تعداد درخواست زیاد است. یک دقیقه صبر کنید.', 'shojaei-seo-for-woo' ),
					'draftReady' => __( 'پیش‌نویس آماده — بررسی و اعمال کنید.', 'shojaei-seo-for-woo' ),
					'draftApply' => __( 'اعمال پیش‌نویس', 'shojaei-seo-for-woo' ),
				),
			)
		);
	}

	/**
	 * Generate one kind.
	 */
	public static function ajax_generate(): void {
		self::verify();
		self::check_rate_limit();
		if ( ! Shojaei_SEO_AI_Client::is_configured() ) {
			wp_send_json_error( array( 'message' => __( 'موتور تولید خاموش است یا کلید API ذخیره نشده.', 'shojaei-seo-for-woo' ) ) );
		}
		$kind = isset( $_POST['job_kind'] ) ? sanitize_key( wp_unslash( $_POST['job_kind'] ) ) : ''; // phpcs:ignore
		if ( ! in_array( $kind, self::$kinds, true ) ) {
			wp_send_json_error( array( 'message' => __( 'نوع درخواست نامعتبر است.', 'shojaei-seo-for-woo' ) ) );
		}
		self::relax_time_limits();
		$result = self::run_kind( $kind, self::context_from_post(), self::extra_from_post( $kind ) );
		self::send_result( $result );
	}

	/**
	 * Test connection.
	 */
	public static function ajax_test_connection(): void {
		self::verify();
		if ( ! Shojaei_SEO_Helpers::user_can_admin() ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) ), 403 );
		}
		$overrides = self::connection_from_post();
		if ( ! empty( $overrides['api_key'] ) ) {
			Shojaei_SEO_AI_Client::store_api_key( (string) $overrides['api_key'] );
		}
		if ( ! empty( $overrides['provider'] ) || ! empty( $overrides['model'] ) ) {
			Shojaei_SEO_AI_Client::save_connection_settings(
				(string) ( $overrides['provider'] ?? Shojaei_SEO_AI_Client::provider() ),
				(string) ( $overrides['model'] ?? Shojaei_SEO_AI_Client::model() )
			);
		}
		$check = Shojaei_SEO_AI_Client::test_connection( $overrides );
		if ( is_wp_error( $check ) ) {
			update_option(
				Shojaei_SEO_AI_Client::OPT_HEALTH,
				array(
					'ok'      => false,
					'time'    => time(),
					'message' => $check->get_error_message(),
				),
				false
			);
			wp_send_json_error( array( 'message' => $check->get_error_message() ) );
		}
		update_option(
			Shojaei_SEO_AI_Client::OPT_HEALTH,
			array_merge(
				$check,
				array( 'time' => time() )
			),
			false
		);
		wp_send_json_success( $check );
	}

	/**
	 * ItemList schema (no LLM).
	 */
	public static function ajax_itemlist(): void {
		self::verify();
		$schema = self::build_itemlist( self::context_from_post() );
		wp_send_json_success( array( 'data' => $schema ) );
	}

	/**
	 * Write llms.txt.
	 */
	public static function ajax_write_llms(): void {
		self::verify();
		if ( ! current_user_can( 'manage_options' ) && ! Shojaei_SEO_Helpers::user_can_admin() ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) ), 403 );
		}
		$content = (string) get_option( 'shojaei_seo_llms_txt', '' );
		if ( '' === trim( $content ) ) {
			wp_send_json_error( array( 'message' => __( 'ابتدا llms.txt را تولید کنید.', 'shojaei-seo-for-woo' ) ) );
		}
		$path = trailingslashit( ABSPATH ) . 'llms.txt';
		$ok   = file_put_contents( $path, $content ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === $ok ) {
			wp_send_json_error( array( 'message' => __( 'نوشتن فایل ممکن نشد.', 'shojaei-seo-for-woo' ) ) );
		}
		wp_send_json_success( array( 'path' => home_url( '/llms.txt' ) ) );
	}

	/**
	 * Start bulk alt job.
	 */
	public static function ajax_bulk_alt_start(): void {
		self::verify();
		if ( ! Shojaei_SEO_Helpers::user_can_admin() ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) ), 403 );
		}
		if ( ! Shojaei_SEO_AI_Client::is_configured() ) {
			wp_send_json_error( array( 'message' => __( 'کلید API ذخیره نشده.', 'shojaei-seo-for-woo' ) ) );
		}
		if ( ! class_exists( 'Shojaei_SEO_Jobs' ) ) {
			wp_send_json_error( array( 'message' => __( 'صف پس‌زمینه در دسترس نیست.', 'shojaei-seo-for-woo' ) ) );
		}

		$ids = self::find_products_missing_alt( 400 );
		if ( empty( $ids ) ) {
			wp_send_json_success(
				array(
					'job_id'  => '',
					'message' => __( 'محصولی بدون Alt پیدا نشد.', 'shojaei-seo-for-woo' ),
					'total'   => 0,
				)
			);
		}

		$job_key = Shojaei_SEO_Jobs::enqueue(
			self::JOB_ALT,
			array(
				'product_ids' => $ids,
				'user_id'     => get_current_user_id(),
			),
			array(
				'total'        => count( $ids ),
				'max_attempts' => 2,
			)
		);

		self::spawn_alt_worker( $job_key );

		wp_send_json_success(
			array(
				'job_id'       => $job_key,
				'total'        => count( $ids ),
				'worker_nonce' => wp_create_nonce( 'shojaei_ai_alt_run_' . $job_key ),
			)
		);
	}

	/**
	 * Poll bulk alt.
	 */
	public static function ajax_bulk_alt_status(): void {
		self::verify();
		$job_key = isset( $_POST['job_id'] ) ? sanitize_text_field( wp_unslash( $_POST['job_id'] ) ) : ''; // phpcs:ignore
		if ( '' === $job_key || ! class_exists( 'Shojaei_SEO_Jobs' ) ) {
			wp_send_json_error( array( 'message' => __( 'شناسه job نامعتبر است.', 'shojaei-seo-for-woo' ) ) );
		}
		$job = Shojaei_SEO_Jobs::get( $job_key );
		if ( ! $job || self::JOB_ALT !== ( $job['type'] ?? '' ) ) {
			wp_send_json_error( array( 'message' => __( 'job یافت نشد.', 'shojaei-seo-for-woo' ) ) );
		}
		Shojaei_SEO_Jobs::schedule_next( $job_key, 0 );
		wp_send_json_success(
			array(
				'status'    => (string) ( $job['status'] ?? '' ),
				'processed' => (int) ( $job['processed'] ?? 0 ),
				'total'     => (int) ( $job['total'] ?? 0 ),
				'failed'    => (int) ( $job['failed'] ?? 0 ),
				'message'   => (string) ( $job['message'] ?? '' ),
			)
		);
	}

	/**
	 * Background chunk runner.
	 */
	public static function ajax_bulk_alt_run(): void {
		$job_key = isset( $_POST['job_key'] ) ? sanitize_text_field( wp_unslash( $_POST['job_key'] ) ) : ''; // phpcs:ignore
		if ( '' === $job_key ) {
			wp_die();
		}
		check_ajax_referer( 'shojaei_ai_alt_run_' . $job_key, 'nonce' );
		if ( ! is_user_logged_in() ) {
			wp_die();
		}
		if ( ! current_user_can( 'edit_products' ) && ! Shojaei_SEO_Helpers::user_can_admin() ) {
			wp_die();
		}
		if ( class_exists( 'Shojaei_SEO_Jobs' ) ) {
			$job = Shojaei_SEO_Jobs::get( $job_key );
			if ( $job && self::JOB_ALT === ( $job['type'] ?? '' ) ) {
				$owner = (int) ( $job['payload']['user_id'] ?? 0 );
				if ( $owner && $owner !== get_current_user_id() && ! Shojaei_SEO_Helpers::user_can_admin() ) {
					wp_die();
				}
			}
		}
		self::relax_time_limits();
		if ( class_exists( 'Shojaei_SEO_Jobs' ) ) {
			Shojaei_SEO_Jobs::run_next( $job_key );
		}
		wp_die();
	}

	/**
	 * SEO checklist after auto wizard.
	 */
	public static function ajax_validate_seo(): void {
		self::verify();
		$ctx     = self::context_from_post();
		$post_id = (int) ( $ctx['post_id'] ?? 0 );
		wp_send_json_success(
			array(
				'checklist' => self::seo_checklist( $post_id, $ctx ),
			)
		);
	}

	/**
	 * Batch chunk: generate missing alts.
	 *
	 * @param array<string,mixed> $job Job.
	 * @param int                 $size Batch size.
	 * @return array<string,mixed>
	 */
	public static function execute_alt_chunk( array $job, int $size = 3 ): array {
		self::relax_time_limits();
		$payload = is_array( $job['payload'] ?? null ) ? $job['payload'] : array();
		$ids     = isset( $payload['product_ids'] ) ? array_map( 'absint', (array) $payload['product_ids'] ) : array();
		$offset  = (int) ( $job['offset'] ?? 0 );
		$slice   = array_slice( $ids, $offset, max( 1, min( 5, $size ) ) );

		$processed = 0;
		$failed    = 0;
		foreach ( $slice as $pid ) {
			$ctx = self::product_seo_context( $pid );
			$r   = self::run_alt_texts( $ctx, array( 'image_ids' => $ctx['image_ids'] ) );
			if ( is_wp_error( $r ) ) {
				++$failed;
			} else {
				++$processed;
			}
		}

		$new_offset = $offset + count( $slice );
		$done       = $new_offset >= count( $ids );

		return array(
			'processed' => $processed,
			'failed'    => $failed,
			'offset'    => $new_offset,
			'done'      => $done,
			'message'   => $done ? __( 'تولید Alt تمام شد.', 'shojaei-seo-for-woo' ) : '',
		);
	}

	/**
	 * Core generation.
	 *
	 * @param string              $kind  Kind.
	 * @param array<string,mixed> $ctx   Context.
	 * @param array<string,mixed> $extra Extra.
	 * @return array<string,mixed>|string|WP_Error
	 */
	public static function run_kind( string $kind, array $ctx, array $extra = array() ) {
		if ( 'alt_texts' === $kind ) {
			return self::run_alt_texts( $ctx, $extra );
		}
		if ( 'itemlist' === $kind ) {
			return self::build_itemlist( $ctx );
		}
		if ( 'long_desc' === $kind ) {
			return self::run_long_desc( $ctx, $extra );
		}
		if ( 'full_pack' === $kind ) {
			return self::run_full_pack( $ctx, $extra );
		}

		$prompt = self::build_prompt( $kind, $ctx, $extra );
		if ( is_wp_error( $prompt ) ) {
			return $prompt;
		}

		$opts = self::opts_for_kind( $kind );
		$raw  = Shojaei_SEO_AI_Client::chat( $prompt, $opts );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		if ( 'llms_txt' === $kind ) {
			$content = trim( $raw );
			update_option( 'shojaei_seo_llms_txt', $content, false );
			return $content;
		}

		$result = self::shape_result( $kind, $raw, $ctx );

		return $result;
	}

	/**
	 * AI slug (Finglish Latin) with local cleanup + uniquify.
	 *
	 * @param array<string,mixed> $ctx Context (title, keyword, attributes, post_id, …).
	 * @return string|WP_Error
	 */
	public static function generate_slug( array $ctx ) {
		$prompt = self::build_prompt( 'slug', $ctx, array() );
		if ( is_wp_error( $prompt ) ) {
			return $prompt;
		}
		$raw = Shojaei_SEO_AI_Client::chat( $prompt, self::opts_for_kind( 'slug' ) );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}
		$slug = self::sanitize_ai_slug( (string) self::shape_result( 'slug', $raw, $ctx ), $ctx );
		if ( '' === $slug ) {
			return new WP_Error( 'ai_slug', __( 'نامک AI نامعتبر بود.', 'shojaei-seo-for-woo' ) );
		}
		return $slug;
	}

	/**
	 * Two-phase long description: outline → full HTML article.
	 *
	 * @param array<string,mixed> $ctx   Context.
	 * @param array<string,mixed> $extra Extra.
	 * @return string|WP_Error
	 */
	private static function run_long_desc( array $ctx, array $extra = array() ) {
		$outline_prompt = self::build_prompt( 'long_desc_outline', $ctx, $extra );
		if ( is_wp_error( $outline_prompt ) ) {
			return $outline_prompt;
		}

		$outline_raw = Shojaei_SEO_AI_Client::chat(
			$outline_prompt,
			array_merge(
				self::opts_for_kind( 'long_desc_outline' ),
				array( 'temperature' => 0.25 )
			)
		);
		if ( is_wp_error( $outline_raw ) ) {
			return $outline_raw;
		}

		$outline = Shojaei_SEO_AI_Client::extract_json( $outline_raw );
		if ( empty( $outline['sections'] ) || ! is_array( $outline['sections'] ) ) {
			$outline = array(
				'sections' => array(
					array(
						'h2'      => 'معرفی محصول',
						'bullets' => array( (string) ( $ctx['title'] ?? '' ) ),
					),
				),
			);
		}

		$expand_prompt = self::build_prompt(
			'long_desc_expand',
			$ctx,
			array_merge( $extra, array( 'outline' => $outline ) )
		);
		if ( is_wp_error( $expand_prompt ) ) {
			return $expand_prompt;
		}

		$raw = Shojaei_SEO_AI_Client::chat( $expand_prompt, self::opts_for_kind( 'long_desc' ) );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		return self::shape_result( 'long_desc', $raw, $ctx );
	}

	/**
	 * Full SEO pack: meta layer + two-phase long desc + optional alts.
	 *
	 * @param array<string,mixed> $ctx   Context.
	 * @param array<string,mixed> $extra Extra.
	 * @return array<string,mixed>|WP_Error
	 */
	private static function run_full_pack( array $ctx, array $extra = array() ) {
		$prompt = self::build_prompt( 'full_pack_meta', $ctx, $extra );
		if ( is_wp_error( $prompt ) ) {
			return $prompt;
		}

		$raw = Shojaei_SEO_AI_Client::chat( $prompt, self::opts_for_kind( 'full_pack_meta' ) );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		$json = Shojaei_SEO_AI_Client::extract_json( $raw );
		$pack = array(
			'meta_title' => self::polish_meta_title( isset( $json['meta_title'] ) ? (string) $json['meta_title'] : '', $ctx ),
			'meta_desc'  => isset( $json['meta_desc'] ) ? trim( (string) $json['meta_desc'] ) : '',
			'short_desc' => isset( $json['short_desc'] ) ? Shojaei_SEO_AI_Client::clean_html( (string) $json['short_desc'] ) : '',
			'long_desc'  => '',
		);

		$long = self::run_long_desc( $ctx, $extra );
		if ( is_wp_error( $long ) ) {
			return $long;
		}
		$pack['long_desc'] = is_string( $long ) ? $long : '';

		$faq = self::run_faq( $ctx, $pack['long_desc'] );
		if ( ! is_wp_error( $faq ) && is_array( $faq ) ) {
			$pack['faqs']   = $faq['faqs'] ?? array();
			$pack['faq_schema'] = $faq['schema'] ?? array();
		}

		if ( ! empty( $ctx['image_ids'] ) ) {
			$alts = self::run_alt_texts( $ctx, array( 'image_ids' => $ctx['image_ids'] ) );
			if ( ! is_wp_error( $alts ) ) {
				$pack['alt_texts'] = $alts;
				$pack['alt_saved'] = count(
					array_filter(
						$alts,
						static function ( $row ) {
							return is_array( $row ) && ! empty( $row['alt'] );
						}
					)
				);
			}
		}

		return self::finalize_pack( $pack, $ctx );
	}

	/**
	 * FAQ generation for pack flow.
	 *
	 * @param array<string,mixed> $ctx     Context.
	 * @param string              $article Long HTML/text.
	 * @return array<string,mixed>|WP_Error
	 */
	private static function run_faq( array $ctx, string $article ) {
		$prompt = self::build_prompt( 'faq', $ctx, array( 'article' => $article ) );
		if ( is_wp_error( $prompt ) ) {
			return $prompt;
		}
		$raw = Shojaei_SEO_AI_Client::chat( $prompt, self::opts_for_kind( 'faq' ) );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}
		$result = self::shape_result( 'faq', $raw, $ctx );
		return is_array( $result ) ? $result : new WP_Error( 'ai_faq', __( 'FAQ ساخته نشد.', 'shojaei-seo-for-woo' ) );
	}

	/**
	 * Draft mode vs direct meta persist.
	 *
	 * @param array<string,mixed> $pack Pack.
	 * @param array<string,mixed> $ctx  Context.
	 * @return array<string,mixed>
	 */
	private static function finalize_pack( array $pack, array $ctx ): array {
		$post_id = (int) ( $ctx['post_id'] ?? 0 );
		$draft   = class_exists( 'Shojaei_SEO_Store_Profile' ) && Shojaei_SEO_Store_Profile::draft_mode();

		if ( $draft ) {
			if ( $post_id > 0 ) {
				update_post_meta( $post_id, '_damavand_seo_ai_draft', wp_json_encode( $pack, JSON_UNESCAPED_UNICODE ), false );
			}
			$pack['draft']         = true;
			$pack['draft_message'] = __( 'پیش‌نویس آماده است — دکمه «اعمال پیش‌نویس» را بزنید یا فیلدها را دستی بررسی کنید.', 'shojaei-seo-for-woo' );
			return $pack;
		}

		if ( $post_id > 0 && ! empty( $pack['meta_title'] ) ) {
			update_post_meta( $post_id, '_damavand_seo_title', (string) $pack['meta_title'] );
		}
		if ( $post_id > 0 && ! empty( $pack['meta_desc'] ) ) {
			update_post_meta( $post_id, '_damavand_seo_metadesc', (string) $pack['meta_desc'] );
		}
		delete_post_meta( $post_id, '_damavand_seo_ai_draft' );
		$pack['draft'] = false;
		return $pack;
	}

	/**
	 * Suggested internal links block for long-desc prompts.
	 *
	 * @param int                 $post_id Product ID.
	 * @param array<string,mixed> $ctx     Context.
	 */
	private static function link_prompt_block( int $post_id, array $ctx ): string {
		if ( $post_id < 1 || ! class_exists( 'Damavand_Link_Suggestions' ) ) {
			return '';
		}
		$result = Damavand_Link_Suggestions::suggest(
			$post_id,
			array(
				'title'   => (string) ( $ctx['title'] ?? '' ),
				'focus'   => (string) ( $ctx['keyword'] ?? '' ),
				'content' => '',
				'excerpt' => '',
			)
		);
		$lines = array();
		foreach ( array_slice( (array) ( $result['suggestions'] ?? array() ), 0, 4 ) as $row ) {
			if ( empty( $row['post_id'] ) ) {
				continue;
			}
			$pid = (int) $row['post_id'];
			$lines[] = '- ' . get_the_title( $pid ) . ' → ' . get_permalink( $pid );
		}
		if ( ! $lines ) {
			return '';
		}
		return "صفحات مرتبط برای لینک داخلی (حداکثر ۱–۲ لینک، anchor فارسی طبیعی):\n" . implode( "\n", $lines );
	}

	/**
	 * Normalize AI slug output to safe Latin slug.
	 *
	 * @param string              $raw Raw slug text.
	 * @param array<string,mixed> $ctx Context.
	 */
	public static function sanitize_ai_slug( string $raw, array $ctx = array() ): string {
		$raw = trim( wp_strip_all_tags( $raw ) );
		$raw = preg_replace( '#^https?://[^/]+/#i', '', $raw );
		$raw = str_replace( array( '/', ' ', '_' ), '-', $raw );
		$raw = strtolower( $raw );
		$raw = preg_replace( '/[^a-z0-9-]+/', '-', $raw );
		$raw = trim( (string) preg_replace( '/-+/', '-', $raw ), '-' );

		if ( class_exists( 'Shojaei_SEO_Slug' ) ) {
			$raw = Shojaei_SEO_Slug::strip_slug_stopwords( $raw );
			$post_id = (int) ( $ctx['post_id'] ?? 0 );
			if ( $post_id > 0 ) {
				$post = get_post( $post_id );
				if ( $post ) {
					$raw = Shojaei_SEO_Slug::uniquify_slug(
						$raw,
						$post_id,
						(string) $post->post_type,
						(string) $post->post_status,
						(int) $post->post_parent
					);
				}
			}
		}

		if ( '' !== $raw && class_exists( 'Shojaei_SEO_Slug' ) && Shojaei_SEO_Slug::has_persian( $raw ) ) {
			return '';
		}

		return $raw;
	}

	/**
	 * Ensure meta title includes «خرید» and fits length.
	 *
	 * @param string              $title Meta title.
	 * @param array<string,mixed> $ctx   Context.
	 */
	private static function polish_meta_title( string $title, array $ctx ): string {
		$title = trim( preg_replace( '/\s+/u', ' ', $title ) );
		if ( '' === $title ) {
			return $title;
		}
		if ( ! preg_match( '/خرید/u', $title ) ) {
			$kw      = trim( (string) ( $ctx['keyword'] ?? '' ) );
			$product = trim( (string) ( $ctx['title'] ?? '' ) );
			$core    = $kw ?: $product;
			$try     = 'خرید ' . $core;
			$store   = class_exists( 'Shojaei_SEO_Store_Profile' ) ? Shojaei_SEO_Store_Profile::name() : '';
			if ( $store && mb_strlen( $try . ' | ' . $store ) <= 60 ) {
				$title = $try . ' | ' . $store;
			} elseif ( mb_strlen( $try ) <= 60 ) {
				$title = $try;
			} else {
				$title = mb_substr( $try, 0, 60 );
			}
		}
		if ( mb_strlen( $title ) > 60 ) {
			$title = mb_substr( $title, 0, 60 );
		}
		return trim( $title );
	}

	/**
	 * Anti-repetition hints for prompts (per product seed).
	 *
	 * @param array<string,mixed> $ctx Context.
	 */
	private static function anti_repetition_block( array $ctx ): string {
		$seed = (int) ( $ctx['post_id'] ?? 0 );
		$bans = array(
			'بهترین انتخاب برای شما',
			'کیفیت عالی و بی‌نظیر',
			'تجربه‌ای متفاوت',
			'با افتخار ارائه می‌دهیم',
			'محصولی ایده‌آل برای همه',
			'همین حالا سفارش دهید و لذت ببرید',
		);
		// Rotate emphasis so consecutive products get different writing angles.
		$angles = array(
			'روی کاربرد روزمره و سناریوی واقعی استفاده تمرکز کن.',
			'روی جنس، دوخت یا جزئیات فنی ملموس تمرکز کن.',
			'روی مخاطب هدف و اینکه برای چه کسی مناسب است تمرکز کن.',
			'روی تفاوت با گزینه‌های رایج بازار تمرکز کن (بدون توهین به برند دیگر).',
			'روی راهنمای انتخاب و نکات قبل از خرید تمرکز کن.',
		);
		$angle = $angles[ abs( $seed ) % count( $angles ) ];
		return "قوانین ضدتکرار:\n- این عبارات را به‌کار نبر: " . implode( '؛ ', $bans )
			. "\n- زاویه این محصول: " . $angle
			. "\n- از کپی اسکلت مقاله محصولات دیگر خودداری کن.";
	}

	/**
	 * Shared meta-title rules for prompts.
	 *
	 * @param array<string,mixed> $ctx Context.
	 */
	private static function meta_title_rules( array $ctx ): string {
		$title  = (string) ( $ctx['title'] ?? '' );
		$kw     = (string) ( $ctx['keyword'] ?? '' );
		$meta_s = class_exists( 'Shojaei_SEO_Store_Profile' ) ? Shojaei_SEO_Store_Profile::expand_meta_suffix( $title ) : '';
		return sprintf(
			"قوانین عنوان متا:\n- حداکثر ۶۰ کاراکتر\n- حتماً کلمه «خرید» در عنوان باشد\n- کلمه کلیدی «%s» را طبیعی بگنجان\n- الگوی پیشنهادی پایان: «%s»\n- کلیک‌خور و مخصوص فروشگاه ایرانی",
			$kw,
			$meta_s
		);
	}

	/**
	 * @param string $kind Kind.
	 * @return array<string,mixed>
	 */
	public static function opts_for_kind( string $kind ): array {
		$map = array(
			'keywords'           => array( 'max_tokens' => 512, 'timeout' => 45, 'temperature' => 0.2, 'response_mime' => 'application/json' ),
			'meta_titles'        => array( 'max_tokens' => 512, 'timeout' => 45, 'temperature' => 0.3, 'response_mime' => 'application/json' ),
			'meta_desc'          => array( 'max_tokens' => 320, 'timeout' => 45, 'temperature' => 0.3 ),
			'slug'               => array( 'max_tokens' => 120, 'timeout' => 35, 'temperature' => 0.1 ),
			'short_desc'         => array( 'max_tokens' => 768, 'timeout' => 55, 'temperature' => 0.4 ),
			'long_desc_outline'  => array( 'max_tokens' => 1200, 'timeout' => 60, 'temperature' => 0.45, 'response_mime' => 'application/json' ),
			'long_desc'          => array( 'max_tokens' => 4096, 'timeout' => 150, 'temperature' => 0.55 ),
			'faq'                => array( 'max_tokens' => 1200, 'timeout' => 60, 'temperature' => 0.4, 'response_mime' => 'application/json' ),
			'full_pack_meta'     => array( 'max_tokens' => 1536, 'timeout' => 75, 'temperature' => 0.4, 'response_mime' => 'application/json' ),
			'llms_txt'           => array( 'max_tokens' => 1200, 'timeout' => 60, 'temperature' => 0.3 ),
			'alt_texts'          => array( 'max_tokens' => 120, 'timeout'  => 45, 'temperature' => 0.2, 'vision_model' => Shojaei_SEO_AI_Client::VISION_MODEL ),
		);
		$opts = $map[ $kind ] ?? array( 'max_tokens' => 768, 'timeout' => 45 );
		return Shojaei_SEO_AI_Client::adjust_opts_for_provider( $opts );
	}

	/**
	 * @param array<int,array<string,string>> $faqs FAQs.
	 */
	public static function faq_schema( array $faqs ): array {
		$entities = array();
		foreach ( $faqs as $row ) {
			$q = isset( $row['question'] ) ? wp_strip_all_tags( $row['question'] ) : '';
			$a = isset( $row['answer'] ) ? wp_strip_all_tags( $row['answer'] ) : '';
			if ( '' === $q || '' === $a ) {
				continue;
			}
			$entities[] = array(
				'@type'          => 'Question',
				'name'           => $q,
				'acceptedAnswer' => array(
					'@type' => 'Answer',
					'text'  => $a,
				),
			);
		}
		return array(
			'@context'   => 'https://schema.org',
			'@type'      => 'FAQPage',
			'mainEntity' => $entities,
		);
	}

	/**
	 * Verify AJAX.
	 */
	private static function verify(): void {
		check_ajax_referer( 'shojaei_ai', 'nonce' );
		if ( ! current_user_can( 'edit_products' ) && ! current_user_can( 'edit_posts' ) && ! Shojaei_SEO_Helpers::user_can_admin() ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) ) );
		}
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0; // phpcs:ignore
		if ( $post_id > 0 && ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ویرایش این محصول را ندارید.', 'shojaei-seo-for-woo' ) ), 403 );
		}
	}

	/**
	 * Per-user minute + daily site caps for AI calls.
	 */
	private static function check_rate_limit(): void {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return;
		}
		$minute_key = 'shojaei_ai_rl_m' . $user_id;
		$minute_max = (int) apply_filters( 'shojaei_seo_ai_rate_limit_minute', 20 );
		$minute     = get_transient( $minute_key );
		if ( ! is_array( $minute ) ) {
			$minute = array(
				'count' => 0,
				'start' => time(),
			);
		}
		if ( time() - (int) $minute['start'] > 60 ) {
			$minute = array(
				'count' => 0,
				'start' => time(),
			);
		}
		if ( (int) $minute['count'] >= max( 5, $minute_max ) ) {
			wp_send_json_error( array( 'message' => __( 'تعداد درخواست زیاد است. یک دقیقه صبر کنید.', 'shojaei-seo-for-woo' ) ), 429 );
		}
		++$minute['count'];
		set_transient( $minute_key, $minute, 60 );

		$day_key = 'shojaei_ai_rl_d';
		$day_max = (int) apply_filters( 'shojaei_seo_ai_rate_limit_day', 200 );
		$day     = get_transient( $day_key );
		if ( ! is_array( $day ) ) {
			$day = array(
				'count' => 0,
				'start' => time(),
			);
		}
		if ( time() - (int) $day['start'] > DAY_IN_SECONDS ) {
			$day = array(
				'count' => 0,
				'start' => time(),
			);
		}
		if ( (int) $day['count'] >= max( 20, $day_max ) ) {
			wp_send_json_error( array( 'message' => __( 'سقف روزانه تولید محتوا پر شده. فردا دوباره امتحان کنید.', 'shojaei-seo-for-woo' ) ), 429 );
		}
		++$day['count'];
		set_transient( $day_key, $day, DAY_IN_SECONDS );
	}

	private static function relax_time_limits(): void {
		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 120 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		}
		@ignore_user_abort( true ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
	}

	/**
	 * @param string              $kind  Kind.
	 * @param array<string,mixed> $ctx   Context.
	 * @param array<string,mixed> $extra Extra.
	 * @return string|WP_Error
	 */
	private static function build_prompt( string $kind, array $ctx, array $extra = array() ) {
		$title   = (string) ( $ctx['title'] ?? '' );
		$keyword = (string) ( $ctx['keyword'] ?? '' );
		$extra_t = (string) ( $ctx['extra'] ?? '' );
		$cats    = (string) ( $ctx['categories'] ?? '' );
		$attrs   = (string) ( $ctx['attributes'] ?? '' );
		$style   = Shojaei_SEO_AI_Client::style_hint( (int) ( $ctx['post_id'] ?? 0 ) ?: 1 );
		$store   = class_exists( 'Shojaei_SEO_Store_Profile' ) ? Shojaei_SEO_Store_Profile::prompt_block() . "\n\n" : '';
		$meta_s  = class_exists( 'Shojaei_SEO_Store_Profile' ) ? Shojaei_SEO_Store_Profile::expand_meta_suffix( $title ) : '';

		switch ( $kind ) {
			case 'keywords':
				return $store . sprintf(
					"عنوان محصول: %s\nدسته‌بندی: %s\nویژگی‌ها: %s\nاطلاعات: %s\n۵ کلمه کلیدی فارسی فروشگاهی پیشنهاد بده (یک اصلی و ۴ فرعی). فقط JSON:\n{\"primary\":\"...\",\"secondary\":[\"...\",\"...\",\"...\",\"...\"]}",
					$title,
					$cats,
					$attrs,
					$extra_t
				);
			case 'meta_titles':
				return $store . sprintf(
					"۳ عنوان سئو فارسی کلیک‌خور برای گوگل.\nمحصول: %s\nکلمه کلیدی: %s\nدسته: %s\n%s\nهر عنوان حداکثر ۶۰ کاراکتر.\nحداقل ۲ عنوان با «خرید» شروع شوند.\nفقط JSON: {\"titles\":[\"...\",\"...\",\"...\"]}",
					$title,
					$keyword,
					$cats,
					self::meta_title_rules( $ctx )
				);
			case 'meta_desc':
				return $store . sprintf(
					"یک Meta Description فارسی ترغیب‌کننده برای خرید بنویس.\nمحصول: %s\nکلمه کلیدی: %s\nدسته: %s\nحداکثر ۱۵۵ کاراکتر.\nیک CTA کوتاه مثل «همین حالا سفارش دهید» یا «خرید آنلاین».\nفقط متن بدون کوتیشن.",
					$title,
					$keyword,
					$cats
				);
			case 'slug':
				return $store . sprintf(
					"نامک URL لاتین (Finglish) برای محصول فارسی بساز.\nعنوان: %s\nکلمه کلیدی: %s\nویژگی‌ها: %s\n\nقوانین:\n- فقط a-z، 0-9 و خط تیره\n- ۳ تا ۷ کلمه، کوتاه و خوانا\n- حروف اضافه فارسی (از، با، برای…) حذف شوند\n- برند، مدل، رنگ یا SKU در نامک بماند\n- تلفظ رایج فینگlish (مثلاً کفش ورزشی → kafsh-varzeshi)\n- بدون دامنه، بدون slash — فقط یک slug",
					$title,
					$keyword,
					$attrs
				);
			case 'short_desc':
				return $store . sprintf(
					"خلاصه بازاریابی فارسی برای بالای دکمه خرید (۲–۳ جمله خاص این محصول).\nمحصول: %s\nکلمه کلیدی: %s\nویژگی‌ها: %s\nاطلاعات: %s\n%s\nفقط HTML ساده با p و ul/li. بدون markdown. از جملات کلیشه‌ای تکراری پرهیز کن.",
					$title,
					$keyword,
					$attrs,
					$extra_t,
					self::anti_repetition_block( $ctx )
				);
			case 'long_desc':
				return $store . sprintf(
					"مقاله سئو محصول فروشگاهی فارسی — منحصر به این محصول.\nعنوان: %s\nکلمه کلیدی: %s\nدسته: %s\nویژگی‌ها: %s\nاطلاعات تکمیلی: %s\nسبک: %s\n%s\n\nقوانین:\n- حداقل ۴۰۰ کلمه فارسی\n- HTML تمیز: h2, h3, p, ul, li (بدون markdown)\n- ساختار را بر اساس ویژگی‌های واقعی محصول انتخاب کن (نه اسکلت ثابت همیشگی)\n- کلمه کلیدی در ۱۲۰ کلمه اول، طبیعی نه اسپم\n- لحن فروشگاهی واقعی؛ بدون ادعاهای ساختگی",
					$title,
					$keyword,
					$cats,
					$attrs,
					$extra_t,
					$style,
					self::anti_repetition_block( $ctx )
				);
			case 'long_desc_outline':
				return $store . sprintf(
					"طرح مقاله سئو محصول را بساز (فاز ۱ از ۲) — برای همین محصول منحصر به فرد باشد.\nعنوان: %s\nکلمه کلیدی: %s\nدسته: %s\nویژگی‌ها: %s\nاطلاعات: %s\n%s\n\n۴ تا ۶ بخش h2 با ۳–۵ bullet.\nاز بین این الگوها یکی را که به محصول می‌خورد انتخاب کن (همه محصولات یکسان نباشند):\nالف) معرفی کاربردی → مشخصات → مقایسه با گزینه‌های مشابه → راهنمای خرید → جمع‌بندی\nب) مشکل مشتری → راه‌حل محصول → جزئیات جنس/ساخت → نگهداری → دعوت به خرید\nج) معرفی کوتاه → ویژگی‌های متمایز → مخاطب هدف → سوالات رایج کوتاه → جمع‌بندی\nفقط JSON:\n{\"angle\":\"الف|ب|ج\",\"sections\":[{\"h2\":\"...\",\"bullets\":[\"...\",\"...\"]}]}",
					$title,
					$keyword,
					$cats,
					$attrs,
					$extra_t,
					self::anti_repetition_block( $ctx )
				);
			case 'long_desc_expand':
				$outline = $extra['outline'] ?? array();
				$outline_json = wp_json_encode( $outline, JSON_UNESCAPED_UNICODE );
				$link_block   = self::link_prompt_block( (int) ( $ctx['post_id'] ?? 0 ), $ctx );
				$link_section = '' !== $link_block ? "\n\n" . $link_block : '';
				return $store . sprintf(
					"بر اساس طرح زیر، مقاله HTML کامل فاز ۲ را بنویس — متن انسانی و غیرتکراری.\nعنوان: %s\nکلمه کلیدی: %s\nدسته: %s\nویژگی‌ها: %s\nاطلاعات: %s\nسبک: %s\n%s\n\nطرح:\n%s%s\n\nقوانین:\n- حداقل ۴۰۰ کلمه\n- HTML: h2, h3, p, ul, li — بدون markdown\n- هر h2 را با جزئیات واقعی محصول گسترش بده؛ پاراگراف‌های کپی‌شده از محصولات دیگر ممنوع\n- کلمه کلیدی در پاراگراف اول طبیعی باشد\n- فقط HTML",
					$title,
					$keyword,
					$cats,
					$attrs,
					$extra_t,
					$style,
					self::anti_repetition_block( $ctx ),
					$outline_json ?: '{}',
					$link_section
				);
			case 'faq':
				$article = (string) ( $extra['article'] ?? '' );
				return $store . sprintf(
					"۴ یا ۵ سوال متداول فارسی خاص همین محصول (نه سوالات کلیشه‌ای عمومی).\nمحصول: %s\nکلمه کلیدی: %s\n%s\nمتن:\n%s\n\nفقط JSON:\n{\"faqs\":[{\"question\":\"...\",\"answer\":\"...\"}]}",
					$title,
					$keyword,
					self::anti_repetition_block( $ctx ),
					wp_strip_all_tags( $article )
				);
			case 'full_pack_meta':
				return $store . sprintf(
					"بسته سئو فروشگاهی (لایه متا و خلاصه).\nعنوان: %s\nکلمه کلیدی: %s\nدسته: %s\nویژگی‌ها: %s\nاطلاعات: %s\nسبک: %s\n\n%s\n\nتوضیح متا: ۱۳۰–۱۵۵ کاراکتر، CTA خرید.\nshort_desc: HTML با p و ul (۲–۳ جمله + ۳ bullet).\n\nفقط JSON:\n{\"meta_title\":\"...\",\"meta_desc\":\"...\",\"short_desc\":\"<p>...</p>\"}",
					$title,
					$keyword,
					$cats,
					$attrs,
					$extra_t,
					$style,
					self::meta_title_rules( $ctx )
				);
			case 'full_pack':
				return $store . sprintf(
					"برای محصول فروشگاهی فارسی یک بسته سئو کامل بساز.\nعنوان: %s\nکلمه کلیدی: %s\nدسته: %s\nویژگی‌ها: %s\nاطلاعات تکمیلی: %s\nسبک: %s\n\n%s\n\nفقط JSON:\n{\"meta_title\":\"...\",\"meta_desc\":\"...\",\"short_desc\":\"<p>...</p>\",\"long_desc\":\"<h2>...</h2><p>...</p>\"}",
					$title,
					$keyword,
					$cats,
					$attrs,
					$extra_t,
					$style,
					self::meta_title_rules( $ctx )
				);
			case 'llms_txt':
				return $store . sprintf(
					"فایل llms.txt استاندارد برای سایت فروشگاهی فارسی «%s» با آدرس %s بنویس. معرفی کوتاه و لینک‌های مهم. متن ساده.",
					get_bloginfo( 'name' ),
					home_url( '/' )
				);
			default:
				return new WP_Error( 'ai_kind', __( 'نوع درخواست پشتیبانی نمی‌شود.', 'shojaei-seo-for-woo' ) );
		}
	}

	/**
	 * @param string              $kind Kind.
	 * @param string              $raw  Text.
	 * @param array<string,mixed> $ctx  Context.
	 * @return array<string,mixed>|string
	 */
	private static function shape_result( string $kind, string $raw, array $ctx ) {
		switch ( $kind ) {
			case 'keywords':
				$json = Shojaei_SEO_AI_Client::extract_json( $raw );
				return ! empty( $json['primary'] ) ? $json : array( 'raw' => trim( $raw ) );
			case 'meta_titles':
				return Shojaei_SEO_AI_Client::extract_json( $raw ) ?: array( 'raw' => trim( $raw ) );
			case 'meta_desc':
				return trim( wp_strip_all_tags( $raw ) );
			case 'slug':
				$clean = trim( wp_strip_all_tags( $raw ) );
				$slug  = self::sanitize_ai_slug( $clean, $ctx );
				return '' !== $slug ? $slug : $clean;
			case 'short_desc':
				return Shojaei_SEO_AI_Client::clean_html( $raw );
			case 'long_desc':
				return Shojaei_SEO_AI_Client::clean_html( $raw );
			case 'faq':
				$json = Shojaei_SEO_AI_Client::extract_json( $raw );
				if ( empty( $json['faqs'] ) || ! is_array( $json['faqs'] ) ) {
					return array( 'raw' => trim( $raw ) );
				}
				return array(
					'faqs'   => $json['faqs'],
					'schema' => self::faq_schema( $json['faqs'] ),
				);
			case 'full_pack':
				$json = Shojaei_SEO_AI_Client::extract_json( $raw );
				$pack = array(
					'meta_title' => self::polish_meta_title( isset( $json['meta_title'] ) ? (string) $json['meta_title'] : '', $ctx ),
					'meta_desc'  => isset( $json['meta_desc'] ) ? trim( (string) $json['meta_desc'] ) : '',
					'short_desc' => isset( $json['short_desc'] ) ? Shojaei_SEO_AI_Client::clean_html( (string) $json['short_desc'] ) : '',
					'long_desc'  => isset( $json['long_desc'] ) ? Shojaei_SEO_AI_Client::clean_html( (string) $json['long_desc'] ) : '',
				);
				if ( ! empty( $ctx['post_id'] ) && $pack['meta_title'] && ! ( class_exists( 'Shojaei_SEO_Store_Profile' ) && Shojaei_SEO_Store_Profile::draft_mode() ) ) {
					update_post_meta( (int) $ctx['post_id'], '_damavand_seo_title', $pack['meta_title'] );
				}
				if ( ! empty( $ctx['post_id'] ) && $pack['meta_desc'] && ! ( class_exists( 'Shojaei_SEO_Store_Profile' ) && Shojaei_SEO_Store_Profile::draft_mode() ) ) {
					update_post_meta( (int) $ctx['post_id'], '_damavand_seo_metadesc', $pack['meta_desc'] );
				}
				return $pack;
			default:
				return trim( $raw );
		}
	}

	/**
	 * @param array<string,mixed> $ctx   Context.
	 * @param array<string,mixed> $extra Extra.
	 * @return array<int,array<string,string>>|WP_Error
	 */
	private static function run_alt_texts( array $ctx, array $extra ) {
		$image_ids = isset( $extra['image_ids'] ) ? array_map( 'absint', (array) $extra['image_ids'] ) : array();
		if ( empty( $image_ids ) && ! empty( $ctx['image_ids'] ) ) {
			$image_ids = array_map( 'absint', (array) $ctx['image_ids'] );
		}
		if ( empty( $image_ids ) ) {
			return new WP_Error( 'no_image', __( 'تصویر شاخص یا گالری انتخاب نشده.', 'shojaei-seo-for-woo' ) );
		}

		$results  = array();
		$opts     = self::opts_for_kind( 'alt_texts' );
		$thumb_id = ! empty( $ctx['post_id'] ) ? (int) get_post_thumbnail_id( (int) $ctx['post_id'] ) : 0;
		$total    = count( $image_ids );
		$used     = array();

		foreach ( array_values( $image_ids ) as $index => $id ) {
			if ( ! $id ) {
				continue;
			}
			$role = self::image_role_label( $index, $total, $id === $thumb_id );
			$hint = self::attachment_hint( $id );

			$prompt = sprintf(
				"تصویر محصول فروشگاه ایرانی را ببین.\nیک alt فارسی منحصربه‌فرد بنویس (۶–۱۴ کلمه، سئو، بدون گیومه).\nعنوان محصول: %s\nکلمه کلیدی: %s\nنقش این تصویر: %s\n%s\n\nفقط متن alt — برای هر تصویر متفاوت باشد.",
				(string) ( $ctx['title'] ?? '' ),
				(string) ( $ctx['keyword'] ?? '' ),
				$role,
				$hint
			);

			$alt = Shojaei_SEO_AI_Client::chat_with_image( $prompt, $id, $opts );
			if ( is_wp_error( $alt ) ) {
				$fallback = sprintf(
					"Alt فارسی سئو برای تصویر %d از %d محصول.\nعنوان: %s\nکلمه کلیدی: %s\nنقش: %s\n%s\nحداکثر ۱۲۵ کاراکتر. فقط alt منحصربه‌فرد.",
					$index + 1,
					$total,
					(string) ( $ctx['title'] ?? '' ),
					(string) ( $ctx['keyword'] ?? '' ),
					$role,
					$hint
				);
				$alt = Shojaei_SEO_AI_Client::chat( $fallback, $opts );
			}

			if ( is_wp_error( $alt ) ) {
				$results[ $id ] = array( 'error' => $alt->get_error_message() );
				continue;
			}

			$clean = mb_substr( trim( wp_strip_all_tags( (string) $alt ) ), 0, 125 );
			$clean = self::unique_alt_text( $clean, $used, $index, $role );
			update_post_meta( $id, '_wp_attachment_image_alt', $clean );
			$results[ $id ] = array( 'alt' => $clean, 'saved' => true );
		}

		return $results;
	}

	/**
	 * Human label for image position in gallery.
	 */
	private static function image_role_label( int $index, int $total, bool $is_thumb ): string {
		if ( $is_thumb || 0 === $index ) {
			return __( 'تصویر شاخص — نمای اصلی محصول', 'shojaei-seo-for-woo' );
		}
		if ( 1 === $index && $total > 2 ) {
			return __( 'نمای جانبی یا زاویه دوم', 'shojaei-seo-for-woo' );
		}
		if ( $index === $total - 1 ) {
			return __( 'جزئیات، کف، برچسب یا بسته‌بندی', 'shojaei-seo-for-woo' );
		}
		return sprintf(
			/* translators: 1: image number, 2: total images */
			__( 'تصویر گالری %1$d از %2$d', 'shojaei-seo-for-woo' ),
			$index + 1,
			$total
		);
	}

	/**
	 * Filename / caption hints for alt uniqueness.
	 */
	private static function attachment_hint( int $attachment_id ): string {
		$parts = array();
		$file  = get_attached_file( $attachment_id );
		if ( $file ) {
			$parts[] = 'فایل: ' . sanitize_file_name( basename( $file ) );
		}
		$title = get_the_title( $attachment_id );
		if ( $title && ! preg_match( '/^\d+$/', $title ) ) {
			$parts[] = 'عنوان رسانه: ' . $title;
		}
		$caption = (string) wp_get_attachment_caption( $attachment_id );
		if ( '' !== trim( $caption ) ) {
			$parts[] = 'کپشن: ' . mb_substr( trim( $caption ), 0, 80 );
		}
		return $parts ? implode( ' | ', $parts ) : '';
	}

	/**
	 * ItemList preview for the product's primary category (archive schema — NOT stored on product).
	 *
	 * @param array<string,mixed> $ctx Context.
	 * @return array<string,mixed>
	 */
	private static function build_itemlist( array $ctx ): array {
		$post_id = (int) ( $ctx['post_id'] ?? 0 );
		$term    = null;
		if ( $post_id ) {
			$terms = wp_get_post_terms( $post_id, 'product_cat' );
			if ( is_array( $terms ) && ! empty( $terms ) && ! is_wp_error( $terms ) ) {
				$term = $terms[0];
			}
		}

		if ( ! $term instanceof WP_Term ) {
			return array(
				'@context' => 'https://schema.org',
				'@type'    => 'ItemList',
				'name'     => __( 'ItemList فقط برای صفحه دسته/آرشیو است', 'shojaei-seo-for-woo' ),
				'itemListElement' => array(),
				'_damavand_note'  => __( 'روی صفحه محصول چاپ نمی‌شود. در فرانتِ دسته/فروشگاه به‌صورت زنده ساخته می‌شود.', 'shojaei-seo-for-woo' ),
			);
		}

		$link = get_term_link( $term );
		$url  = is_wp_error( $link ) ? '' : (string) $link;
		$q    = new WP_Query(
			array(
				'post_type'      => 'product',
				'posts_per_page' => 12,
				'tax_query'      => array(
					array(
						'taxonomy' => 'product_cat',
						'field'    => 'term_id',
						'terms'    => (int) $term->term_id,
					),
				),
			)
		);
		$items = array();
		$pos   = 1;
		while ( $q->have_posts() ) {
			$q->the_post();
			$pid     = get_the_ID();
			$product = function_exists( 'wc_get_product' ) ? wc_get_product( $pid ) : null;
			$items[] = array(
				'@type'    => 'ListItem',
				'position' => $pos++,
				'url'      => get_permalink( $pid ),
				'name'     => $product ? $product->get_name() : get_the_title( $pid ),
			);
		}
		wp_reset_postdata();

		// Do NOT save ItemList on the product — that belonged on archives only.
		if ( $post_id ) {
			delete_post_meta( $post_id, '_damavand_seo_itemlist_schema' );
		}

		return array(
			'@context'        => 'https://schema.org',
			'@type'           => 'ItemList',
			'name'            => sprintf(
				/* translators: %s: category name */
				__( 'محصولات %s', 'shojaei-seo-for-woo' ),
				$term->name
			),
			'url'             => $url,
			'numberOfItems'   => count( $items ),
			'itemListElement' => $items,
			'_damavand_note'  => __( 'پیش‌نمایش ItemList دسته — در صفحه محصول چاپ نمی‌شود؛ روی آرشیو دسته چاپ می‌شود.', 'shojaei-seo-for-woo' ),
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function context_from_post(): array {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0; // phpcs:ignore
		$title   = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : ''; // phpcs:ignore
		$keyword = isset( $_POST['keyword'] ) ? sanitize_text_field( wp_unslash( $_POST['keyword'] ) ) : ''; // phpcs:ignore
		$extra   = isset( $_POST['extra'] ) ? sanitize_textarea_field( wp_unslash( $_POST['extra'] ) ) : ''; // phpcs:ignore

		$ctx = array(
			'post_id'    => $post_id,
			'title'      => $title,
			'keyword'    => $keyword,
			'extra'      => $extra,
			'categories' => '',
			'attributes' => '',
			'image_ids'  => array(),
		);

		if ( $post_id ) {
			$enriched = self::product_seo_context( $post_id );
			if ( '' === $ctx['title'] ) {
				$ctx['title'] = $enriched['title'];
			}
			if ( '' === $ctx['keyword'] ) {
				$ctx['keyword'] = $enriched['keyword'];
			}
			$ctx['categories'] = $enriched['categories'];
			$ctx['attributes'] = $enriched['attributes'];
			$ctx['image_ids']  = $enriched['image_ids'];
		}

		return $ctx;
	}

	/**
	 * Product fields for prompts and alt.
	 *
	 * @param int $post_id Product ID.
	 * @return array<string,mixed>
	 */
	public static function product_seo_context( int $post_id ): array {
		$title   = get_the_title( $post_id );
		$keyword = class_exists( 'Damavand_SEO_Meta' )
			? Damavand_SEO_Meta::get_focus_keyword( $post_id, true )
			: (string) get_post_meta( $post_id, '_damavand_seo_focus_keyword', true );
		if ( '' === trim( $keyword ) ) {
			$keyword = trim( wp_strip_all_tags( (string) $title ) );
		}
		$cats    = array();
		$terms   = get_the_terms( $post_id, 'product_cat' );
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$cats[] = $term->name;
			}
		}

		$attrs = array();
		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $post_id );
			if ( $product ) {
				foreach ( $product->get_attributes() as $attribute ) {
					if ( is_object( $attribute ) && method_exists( $attribute, 'get_options' ) ) {
						$name    = $attribute->get_name();
						$options = $attribute->get_options();
						if ( $attribute->is_taxonomy() ) {
							$labels = array();
							foreach ( (array) $options as $term_id ) {
								$t = get_term( (int) $term_id );
								if ( $t && ! is_wp_error( $t ) ) {
									$labels[] = $t->name;
								}
							}
							$attrs[] = wc_attribute_label( $name ) . ': ' . implode( '، ', $labels );
						} else {
							$attrs[] = wc_attribute_label( $name ) . ': ' . implode( '، ', array_map( 'strval', (array) $options ) );
						}
					}
				}
			}
		}

		$image_ids = array();
		$thumb     = (int) get_post_thumbnail_id( $post_id );
		if ( $thumb > 0 ) {
			$image_ids[] = $thumb;
		}
		$gallery = (string) get_post_meta( $post_id, '_product_image_gallery', true );
		foreach ( explode( ',', $gallery ) as $part ) {
			$n = absint( $part );
			if ( $n > 0 && ! in_array( $n, $image_ids, true ) ) {
				$image_ids[] = $n;
			}
		}

		return array(
			'post_id'    => $post_id,
			'title'      => $title,
			'keyword'    => $keyword,
			'categories' => implode( '، ', $cats ),
			'attributes' => implode( '؛ ', $attrs ),
			'image_ids'  => $image_ids,
			'extra'      => '',
		);
	}

	/**
	 * @param string $kind Kind.
	 * @return array<string,mixed>
	 */
	private static function extra_from_post( string $kind ): array {
		$extra = array();
		if ( 'faq' === $kind && isset( $_POST['article'] ) ) { // phpcs:ignore
			$extra['article'] = wp_kses_post( wp_unslash( $_POST['article'] ) ); // phpcs:ignore
		}
		if ( 'alt_texts' === $kind && isset( $_POST['image_ids'] ) ) { // phpcs:ignore
			$extra['image_ids'] = array_map( 'absint', (array) wp_unslash( $_POST['image_ids'] ) ); // phpcs:ignore
		}
		return $extra;
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function connection_from_post(): array {
		$out = array();
		$map = array(
			'provider' => 'provider',
			'api_key'  => 'api_key',
			'model'    => 'model',
		);
		foreach ( $map as $post_key => $opt_key ) {
			if ( isset( $_POST[ $post_key ] ) ) { // phpcs:ignore
				$out[ $opt_key ] = sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) ); // phpcs:ignore
			}
		}
		return $out;
	}

	/**
	 * @param mixed $result Result.
	 */
	private static function send_result( $result ): void {
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}
		wp_send_json_success( array( 'data' => $result ) );
	}

	/**
	 * @param int $limit Max products.
	 * @return array<int,int>
	 */
	private static function find_products_missing_alt( int $limit ): array {
		$q = new WP_Query(
			array(
				'post_type'      => 'product',
				'post_status'    => array( 'publish', 'draft' ),
				'posts_per_page' => $limit,
				'fields'         => 'ids',
				'no_found_rows'  => true,
			)
		);
		$out = array();
		foreach ( $q->posts as $pid ) {
			$pid   = (int) $pid;
			$ctx   = self::product_seo_context( $pid );
			$need  = false;
			foreach ( $ctx['image_ids'] as $img ) {
				$alt = (string) get_post_meta( (int) $img, '_wp_attachment_image_alt', true );
				if ( '' === trim( $alt ) ) {
					$need = true;
					break;
				}
			}
			if ( $need ) {
				$out[] = $pid;
			}
		}
		return $out;
	}

	/**
	 * @param string $job_key Job.
	 */
	private static function spawn_alt_worker( string $job_key ): void {
		if ( class_exists( 'Shojaei_SEO_Jobs' ) ) {
			Shojaei_SEO_Jobs::schedule_next( $job_key, 0 );
		}
		wp_remote_post(
			admin_url( 'admin-ajax.php' ),
			array(
				'timeout'   => 0.01,
				'blocking'  => false,
				'sslverify' => apply_filters( 'https_local_ssl_verify', false ),
				'body'      => array(
					'action'  => 'shojaei_ai_bulk_alt_run',
					'job_key' => $job_key,
					'nonce'   => wp_create_nonce( 'shojaei_ai_alt_run_' . $job_key ),
				),
			)
		);
	}

	/**
	 * Avoid duplicate alt strings within one product batch.
	 *
	 * @param string              $alt   Candidate alt.
	 * @param array<int,string>   $used  Normalized alts already saved.
	 * @param int                 $index Image index.
	 * @param string              $role  Role label.
	 */
	private static function unique_alt_text( string $alt, array &$used, int $index, string $role ): string {
		$norm = mb_strtolower( preg_replace( '/\s+/u', ' ', trim( $alt ) ) );
		if ( '' === $norm ) {
			$alt  = mb_substr( trim( $role ), 0, 125 );
			$norm = mb_strtolower( preg_replace( '/\s+/u', ' ', $alt ) );
		}
		$tries = 0;
		while ( in_array( $norm, $used, true ) && $tries < 6 ) {
			++$tries;
			$suffix = ' — ' . ( $index + 1 );
			$alt    = mb_substr( trim( $alt ) . $suffix, 0, 125 );
			$norm   = mb_strtolower( preg_replace( '/\s+/u', ' ', $alt ) );
		}
		$used[] = $norm;
		return $alt;
	}

	/**
	 * Post-auto SEO checklist.
	 *
	 * @param int                 $post_id Product ID.
	 * @param array<string,mixed> $ctx     Context.
	 * @return array<int,array<string,mixed>>
	 */
	public static function seo_checklist( int $post_id, array $ctx = array() ): array {
		$title   = (string) ( $ctx['title'] ?? ( $post_id ? get_the_title( $post_id ) : '' ) );
		$keyword = (string) ( $ctx['keyword'] ?? ( $post_id ? get_post_meta( $post_id, '_damavand_seo_focus_keyword', true ) : '' ) );
		$meta_t  = (string) ( $post_id ? get_post_meta( $post_id, '_damavand_seo_title', true ) : '' );
		if ( '' === $meta_t && isset( $_POST['meta_title'] ) ) { // phpcs:ignore
			$meta_t = sanitize_text_field( wp_unslash( $_POST['meta_title'] ) ); // phpcs:ignore
		}
		$meta_d = (string) ( $post_id ? get_post_meta( $post_id, '_damavand_seo_metadesc', true ) : '' );
		$slug   = $post_id ? (string) get_post_field( 'post_name', $post_id ) : '';
		$excerpt = $post_id ? (string) get_post_field( 'post_excerpt', $post_id ) : '';
		$content = $post_id ? (string) get_post_field( 'post_content', $post_id ) : '';

		$missing_alt = 0;
		if ( $post_id ) {
			$images = self::product_seo_context( $post_id )['image_ids'] ?? array();
			foreach ( (array) $images as $img_id ) {
				$alt = (string) get_post_meta( (int) $img_id, '_wp_attachment_image_alt', true );
				if ( '' === trim( $alt ) ) {
					++$missing_alt;
				}
			}
		}

		$items = array(
			array(
				'id'      => 'title',
				'label'   => __( 'عنوان محصول', 'shojaei-seo-for-woo' ),
				'ok'      => mb_strlen( trim( $title ) ) >= 3,
				'detail'  => trim( $title ),
			),
			array(
				'id'      => 'keyword',
				'label'   => __( 'کلمه کلیدی', 'shojaei-seo-for-woo' ),
				'ok'      => mb_strlen( trim( $keyword ) ) >= 2,
				'detail'  => trim( $keyword ),
			),
			array(
				'id'      => 'meta_title',
				'label'   => __( 'عنوان متا', 'shojaei-seo-for-woo' ),
				'ok'      => mb_strlen( trim( $meta_t ) ) >= 10 && mb_strlen( trim( $meta_t ) ) <= 65 && preg_match( '/خرید/u', $meta_t ),
				'detail'  => trim( $meta_t ),
			),
			array(
				'id'      => 'meta_desc',
				'label'   => __( 'توضیح متا', 'shojaei-seo-for-woo' ),
				'ok'      => mb_strlen( trim( $meta_d ) ) >= 50 && mb_strlen( trim( $meta_d ) ) <= 165,
				'detail'  => trim( $meta_d ),
			),
			array(
				'id'      => 'short_desc',
				'label'   => __( 'توضیح کوتاه', 'shojaei-seo-for-woo' ),
				'ok'      => mb_strlen( trim( wp_strip_all_tags( $excerpt ) ) ) >= 40,
				'detail'  => '',
			),
			array(
				'id'      => 'long_desc',
				'label'   => __( 'توضیح کامل', 'shojaei-seo-for-woo' ),
				'ok'      => mb_strlen( trim( wp_strip_all_tags( $content ) ) ) >= 200,
				'detail'  => '',
			),
			array(
				'id'      => 'slug',
				'label'   => __( 'نامک لاتین', 'shojaei-seo-for-woo' ),
				'ok'      => '' !== $slug && ! preg_match( '/[\x{0600}-\x{06FF}]/u', $slug ),
				'detail'  => $slug,
			),
			array(
				'id'      => 'alt',
				'label'   => __( 'Alt تصاویر', 'shojaei-seo-for-woo' ),
				'ok'      => 0 === $missing_alt,
				'detail'  => $missing_alt ? sprintf(
					/* translators: %d: missing alt count */
					__( '%d تصویر بدون Alt', 'shojaei-seo-for-woo' ),
					$missing_alt
				) : __( 'همه تصاویر Alt دارند', 'shojaei-seo-for-woo' ),
			),
		);

		return $items;
	}
}
