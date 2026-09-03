<?php
/**
 * Admin panel handler.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_Admin
 */
class Shojaei_SEO_Admin {

	/**
	 * Current tab slug.
	 *
	 * @var string
	 */
	private string $current_tab = 'dashboard';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_menu', array( $this, 'add_menu_badge' ), 999 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_plugins_screen_assets' ) );
		add_filter( 'plugin_action_links_' . DAMAVAND_SEO_BASENAME, array( $this, 'plugin_action_links' ) );
		add_action( 'admin_init', array( $this, 'handle_form_submissions' ) );
		add_action( 'admin_init', array( $this, 'handle_csv_export' ) );
		add_action( 'admin_init', array( $this, 'handle_setup_wizard' ) );
		add_action( 'admin_init', array( $this, 'handle_store_profile' ) );
		add_action( 'admin_init', array( $this, 'handle_wizard_defaults' ) );
		add_action( 'wp_ajax_shojaei_seo_redirect_action', array( $this, 'ajax_redirect_action' ) );
		add_action( 'wp_ajax_shojaei_seo_link_action', array( $this, 'ajax_link_action' ) );
		add_action( 'wp_ajax_shojaei_seo_bulk_redirect', array( $this, 'ajax_bulk_redirect' ) );
		add_action( 'wp_ajax_shojaei_seo_undo_redirect', array( $this, 'ajax_undo_redirect' ) );
		add_action( 'wp_ajax_shojaei_seo_link_preview', array( $this, 'ajax_link_preview' ) );
		add_action( 'wp_ajax_shojaei_seo_notification_action', array( $this, 'ajax_notification_action' ) );
		add_action( 'wp_ajax_shojaei_seo_force_rescan', array( $this, 'ajax_force_rescan' ) );
		add_action( 'wp_ajax_shojaei_seo_oos_days_scan', array( $this, 'ajax_oos_days_scan' ) );
		add_action( 'wp_ajax_shojaei_seo_scan_progress', array( $this, 'ajax_scan_progress' ) );
		add_action( 'wp_ajax_shojaei_seo_product_test', array( $this, 'ajax_product_test' ) );
		add_action( 'wp_ajax_shojaei_seo_dry_run', array( $this, 'ajax_dry_run' ) );
		add_action( 'wp_ajax_shojaei_seo_dry_run_apply', array( $this, 'ajax_dry_run_apply' ) );
		add_action( 'wp_ajax_shojaei_seo_rollback', array( $this, 'ajax_rollback' ) );
		add_action( 'wp_ajax_shojaei_seo_undo_preview', array( $this, 'ajax_undo_preview' ) );
		add_action( 'wp_ajax_shojaei_seo_schema_scan', array( $this, 'ajax_schema_scan' ) );
		add_action( 'wp_ajax_shojaei_seo_disable_wc_schema', array( $this, 'ajax_disable_wc_schema' ) );
		add_action( 'wp_ajax_shojaei_seo_batch_status', array( $this, 'ajax_batch_status' ) );
		add_action( 'wp_ajax_shojaei_seo_gsc_upload', array( $this, 'ajax_gsc_upload' ) );
		add_action( 'wp_ajax_shojaei_seo_gsc_verify', array( $this, 'ajax_gsc_verify' ) );
		add_action( 'wp_ajax_shojaei_seo_gsc_disconnect', array( $this, 'ajax_gsc_disconnect' ) );
		add_action( 'wp_ajax_shojaei_seo_gsc_test_url', array( $this, 'ajax_gsc_test_url' ) );
		add_action( 'wp_ajax_shojaei_seo_slug_action', array( $this, 'ajax_slug_action' ) );
		add_action( 'wp_ajax_shojaei_seo_manual_redirect', array( $this, 'ajax_manual_redirect' ) );
		add_action( 'wp_ajax_shojaei_seo_link_genius', array( $this, 'ajax_link_genius' ) );
		add_action( 'wp_ajax_shojaei_seo_pulse', array( $this, 'ajax_seo_pulse' ) );
		add_action( 'wp_ajax_shojaei_seo_core', array( $this, 'ajax_seo_core' ) );
		add_action( 'wp_ajax_shojaei_seo_redirect_audit', array( $this, 'ajax_redirect_audit' ) );
	}

	/**
	 * Add unread badge to admin menu item.
	 */
	public function add_menu_badge(): void {
		global $menu;

		$count = Shojaei_SEO_Notifications::unread_count();
		if ( $count < 1 ) {
			return;
		}

		foreach ( $menu as $key => $item ) {
			if ( isset( $item[2] ) && 'shojaei-seo' === $item[2] ) {
				$menu[ $key ][0] .= sprintf(
					' <span class="awaiting-mod count-%1$d"><span class="pending-count">%1$d</span></span>',
					$count
				);
				break;
			}
		}
	}

	/**
	 * Register admin menu.
	 */
	public function add_menu_page(): void {
		$menu_icon = class_exists( 'Damavand_SEO_Icons' )
			? Damavand_SEO_Icons::menu_icon_uri()
			: 'dashicons-chart-line';

		add_menu_page(
			__( 'سئو دماوند', 'shojaei-seo-for-woo' ),
			__( 'سئو دماوند', 'shojaei-seo-for-woo' ),
			Shojaei_SEO_Helpers::admin_cap(),
			'shojaei-seo',
			array( $this, 'render_page' ),
			$menu_icon,
			56
		);

		// زیرمنوهای وردپرس — با هاور/کلیک روی «سئو دماوند» دیده می‌شوند.
		$subs = array(
			'shojaei-seo'          => array( __( 'وضعیت', 'shojaei-seo-for-woo' ), 'dashboard' ),
			'shojaei-seo-ops'      => array( __( 'عملیات', 'shojaei-seo-for-woo' ), 'oos' ),
			'shojaei-seo-impact'   => array( __( 'آمار', 'shojaei-seo-for-woo' ), 'impact' ),
			'shojaei-seo-meta'     => array( __( 'متای عمومی', 'shojaei-seo-for-woo' ), 'general-meta' ),
			'shojaei-seo-pulse'    => array( __( 'نبض سئو', 'shojaei-seo-for-woo' ), 'seo-pulse' ),
			'shojaei-seo-core'     => array( __( 'هسته سئو', 'shojaei-seo-for-woo' ), 'seo-core' ),
			'shojaei-seo-migrate'  => array( __( 'مهاجرت', 'shojaei-seo-for-woo' ), 'migrate' ),
			'shojaei-seo-links'    => array( __( 'نابغه لینک', 'shojaei-seo-for-woo' ), 'keyword-maps' ),
			'shojaei-seo-settings' => array( __( 'تنظیمات', 'shojaei-seo-for-woo' ), 'settings' ),
			'shojaei-seo-guide'    => array( __( 'راهنما', 'shojaei-seo-for-woo' ), 'education' ),
		);

		foreach ( $subs as $slug => $pair ) {
			add_submenu_page(
				'shojaei-seo',
				$pair[0],
				$pair[0],
				Shojaei_SEO_Helpers::admin_cap(),
				$slug,
				array( $this, 'render_page' )
			);
		}
	}

	/**
	 * Enqueue admin CSS/JS.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( string $hook ): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		if ( 0 !== strpos( $page, 'shojaei-seo' ) && false === strpos( $hook, 'shojaei-seo' ) ) {
			return;
		}

		wp_enqueue_style(
			'shojaei-seo-admin',
			DAMAVAND_SEO_URL . 'admin/css/admin-style.css',
			array(),
			DAMAVAND_SEO_VERSION
		);

		wp_enqueue_style(
			'damavand-saas',
			DAMAVAND_SEO_URL . 'admin/css/damavand-saas.css',
			array( 'shojaei-seo-admin' ),
			DAMAVAND_SEO_VERSION
		);

		wp_enqueue_style(
			'damavand-rtl-responsive',
			DAMAVAND_SEO_URL . 'admin/css/damavand-rtl-responsive.css',
			array( 'damavand-saas' ),
			DAMAVAND_SEO_VERSION
		);

		wp_enqueue_script(
			'shojaei-seo-admin',
			DAMAVAND_SEO_URL . 'admin/js/admin-script.js',
			array( 'jquery' ),
			DAMAVAND_SEO_VERSION,
			true
		);

		$last_dry = class_exists( 'Shojaei_SEO_Revert_Log' ) ? Shojaei_SEO_Revert_Log::get_dry_run_report() : null;

		wp_localize_script( 'shojaei-seo-admin', 'shojaeiSeoAdmin', array(
			'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
			'nonce'      => wp_create_nonce( 'shojaei_seo_admin_nonce' ),
			'exportBase' => wp_nonce_url( admin_url( 'admin.php?page=shojaei-seo&tab=simulate' ), 'shojaei_seo_export' ),
			'lastDryRun' => is_array( $last_dry ) ? $last_dry : null,
			'i18n'       => array(
				'confirm_redirect' => __( 'آیا از اعمال این ریدایرکت اطمینان دارید؟', 'shojaei-seo-for-woo' ),
				'confirm_undo'     => __( 'آیا از لغو این ریدایرکت اطمینان دارید؟', 'shojaei-seo-for-woo' ),
				'confirm_bulk'     => __( 'آیا از اعمال عملیات گروهی اطمینان دارید؟', 'shojaei-seo-for-woo' ),
				'confirm_410'      => __( '410 Gone یعنی محصول برای همیشه حذف شده. ادامه می‌دهید؟', 'shojaei-seo-for-woo' ),
				'select_products'  => __( 'لطفاً حداقل یک محصول انتخاب کنید.', 'shojaei-seo-for-woo' ),
				'success'          => __( 'عملیات با موفقیت انجام شد.', 'shojaei-seo-for-woo' ),
				'error'            => __( 'خطا در انجام عملیات.', 'shojaei-seo-for-woo' ),
				'preview_loading'  => __( 'در حال پیش‌نمایش...', 'shojaei-seo-for-woo' ),
				'oos_days_scan'      => __( 'اسکن روز ناموجودی شروع شود؟ از آخرین فروش / صفر شدن موجودی.', 'shojaei-seo-for-woo' ),
				'confirm_rescan'     => __( 'اسکن مجدد موجودی شروع شود؟', 'shojaei-seo-for-woo' ),
				'rescan_started'     => __( 'اسکن مجدد در صف قرار گرفت.', 'shojaei-seo-for-woo' ),
				'rescan_busy'        => __( 'اسکن قبلی هنوز در حال اجراست.', 'shojaei-seo-for-woo' ),
				'select_test_product'=> __( 'لطفاً یک محصول انتخاب کنید یا ID وارد کنید.', 'shojaei-seo-for-woo' ),
				'test_loading'       => __( 'در حال اجرای تست...', 'shojaei-seo-for-woo' ),
				'test_timeout'       => __( 'زمان پاسخ تمام شد. صفحه را تازه کنید و دوباره امتحان کنید.', 'shojaei-seo-for-woo' ),
				'suggested_plan'     => __( 'پیشنهاد ریدایرکت', 'shojaei-seo-for-woo' ),
				'redirect_type'      => __( 'نوع', 'shojaei-seo-for-woo' ),
				'target_url'         => __( 'مقصد', 'shojaei-seo-for-woo' ),
				'reason'             => __( 'دلیل', 'shojaei-seo-for-woo' ),
				'match_score'        => __( 'محصول مشابه', 'shojaei-seo-for-woo' ),
				'no_plan'            => __( 'پیشنهاد ریدایرکت در دسترس نیست.', 'shojaei-seo-for-woo' ),
				'view_product'       => __( 'مشاهده صفحه محصول', 'shojaei-seo-for-woo' ),
				'high_value_confirm' => __( 'این صفحه ارزش بالایی دارد و بدون تایید دستی ریدایرکت/حذف نمی‌شود. آیا مطمئن هستید؟', 'shojaei-seo-for-woo' ),
				'confirm_rollback'   => __( 'این تغییر بازگردانی شود؟', 'shojaei-seo-for-woo' ),
				'confirm_rollback_batch' => __( 'کل دسته تغییرات بازگردانی شود؟', 'shojaei-seo-for-woo' ),
				'undo_preview_title'     => __( 'پیش‌نمایش Undo', 'shojaei-seo-for-woo' ),
				'undo_confirm_apply'     => __( 'بازگردانی را اعمال کن', 'shojaei-seo-for-woo' ),
				'undo_cancel'            => __( 'انصراف', 'shojaei-seo-for-woo' ),
				'dryrun_apply_confirm'   => __( 'اجرای واقعی از روی همین پیش‌نمایش؟ تغییرات با Undo ثبت می‌شوند.', 'shojaei-seo-for-woo' ),
				'dryrun_export'          => __( 'خروجی CSV', 'shojaei-seo-for-woo' ),
				'dryrun_apply'           => __( 'اجرای واقعی از پیش‌نمایش', 'shojaei-seo-for-woo' ),
				'dryrun_no_batch'        => __( 'ابتدا یک شبیه‌سازی اجرا کنید.', 'shojaei-seo-for-woo' ),
				'batch_queued'           => __( 'در صف پس‌زمینه قرار گرفت. پیشرفت را اینجا دنبال کنید.', 'shojaei-seo-for-woo' ),
				'batch_running'          => __( 'در حال پردازش دسته‌ای...', 'shojaei-seo-for-woo' ),
				'batch_done'             => __( 'پردازش دسته‌ای تمام شد.', 'shojaei-seo-for-woo' ),
				'slug_delete_confirm'    => __( 'این ریدایرکت نامک حذف شود؟', 'shojaei-seo-for-woo' ),
				'slug_apply_confirm'     => __( 'نامک این محصول به فینگلیش عوض شود و ریدایرکت ۳۰۱ ساخته شود؟', 'shojaei-seo-for-woo' ),
				'slug_preview_title'     => __( 'پیش‌نمایش تغییر نامک', 'shojaei-seo-for-woo' ),
				'slug_apply_ok'          => __( 'اعمال شد.', 'shojaei-seo-for-woo' ),
				'slug_undo_confirm'      => __( 'نامک قدیم برگردد و ریدایرکت ۳۰۱ خاموش شود؟', 'shojaei-seo-for-woo' ),
				'slug_batch_confirm'     => __( 'نامک محصولات انتخاب‌شده عوض شود و برای هرکدام ۳۰۱ ساخته شود؟ (حداکثر ۲۰)', 'shojaei-seo-for-woo' ),
				'slug_batch_max'         => __( 'حداکثر ۲۰ محصول در هر بچ قابل انتخاب است.', 'shojaei-seo-for-woo' ),
				'slug_search_empty'      => __( 'محصولی یافت نشد.', 'shojaei-seo-for-woo' ),
				'slug_search_loading'    => __( 'در حال جستجو…', 'shojaei-seo-for-woo' ),
				'slug_search_found'      => __( 'نتیجه جستجو: %d محصول', 'shojaei-seo-for-woo' ),
				'mr_delete_confirm'      => __( 'این ریدایرکت دستی حذف شود؟', 'shojaei-seo-for-woo' ),
				'mr_saved'               => __( 'ریدایرکت ذخیره شد.', 'shojaei-seo-for-woo' ),
				'lg_map_delete'          => __( 'این نقشه حذف شود؟', 'shojaei-seo-for-woo' ),
				'lg_crawl_confirm'       => __( 'اسکن لینک‌های همه نوشته‌ها شروع شود؟', 'shojaei-seo-for-woo' ),
				'uninstall_wipe_confirm' => __( 'هشدار: با ذخیره این گزینه، حذف کامل افزونه از وردپرس همه ریدایرکت‌ها و جداول را پاک می‌کند و قابل برگشت نیست. مطمئنید؟', 'shojaei-seo-for-woo' ),
				'broken_scan_ok'         => __( 'اسکن تمام شد.', 'shojaei-seo-for-woo' ),
				'broken_disable_confirm' => __( 'این ریدایرکت غیرفعال/لغو شود؟', 'shojaei-seo-for-woo' ),
				'broken_disable_ok'      => __( 'انجام شد.', 'shojaei-seo-for-woo' ),
				'chain_flatten_confirm'  => __( 'مبدأ مستقیم به مقصد نهایی وصل شود و زنجیره میانی حذف شود؟', 'shojaei-seo-for-woo' ),
				'loop_break_confirm'     => __( 'این یال حلقه غیرفعال/لغو شود تا چرخه بشکند؟', 'shojaei-seo-for-woo' ),
			),
		) );
	}

	/**
	 * Extra links on Plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public function plugin_action_links( array $links ): array {
		$extra = array(
			'<a href="' . esc_url( admin_url( 'admin.php?page=shojaei-seo&tab=settings#shojaei-uninstall-policy' ) ) . '">' . esc_html__( 'حذف امن / داده', 'shojaei-seo-for-woo' ) . '</a>',
		);
		return array_merge( $extra, $links );
	}

	/**
	 * Warn before deactivate/delete on Plugins screen.
	 *
	 * @param string $hook Hook.
	 */
	public function enqueue_plugins_screen_assets( string $hook ): void {
		if ( 'plugins.php' !== $hook ) {
			return;
		}

		$slug = plugin_basename( DAMAVAND_SEO_FILE );
		wp_register_script( 'shojaei-seo-plugins-guard', false, array( 'jquery' ), DAMAVAND_SEO_VERSION, true );
		wp_enqueue_script( 'shojaei-seo-plugins-guard' );
		wp_localize_script(
			'shojaei-seo-plugins-guard',
			'shojaeiPluginsGuard',
			array(
				'slug' => $slug,
				'i18n' => array(
					'deactivate' => __( "غیرفعال‌سازی Shojaei SEO:\n\n• داده‌ها و ریدایرکت‌ها در دیتابیس می‌مانند\n• اما تا وقتی افزونه خاموش است، ریدایرکت ۳۰۱/۴۱۰ اجرا نمی‌شود و لینک قدیم ممکن است ۴۰۴ شود\n\nادامه می‌دهید؟", 'shojaei-seo-for-woo' ),
					'delete'     => __( "حذف Shojaei SEO:\n\nپیش‌فرض امن: جداول ریدایرکت نگه داشته می‌شوند (ولی بدون افزونه اجرا نمی‌شوند).\nاگر در تنظیمات گزینه «پاک‌سازی کامل» را روشن کرده باشید، همه ریدایرکت‌ها برای همیشه پاک می‌شوند.\n\nبرای تغییر سیاست حذف: تنظیمات افزونه → پیشرفته.\n\nادامه حذف؟", 'shojaei-seo-for-woo' ),
				),
			)
		);
		$js = <<<'JS'
(function($){
	$(function(){
		var slug = (window.shojaeiPluginsGuard && shojaeiPluginsGuard.slug) || '';
		if(!slug){return;}
		var sel = 'tr[data-plugin="'+slug.replace(/"/g,'\\"')+'"]';
		$(document).on('click', sel + ' .deactivate a', function(e){
			var msg = (shojaeiPluginsGuard.i18n && shojaeiPluginsGuard.i18n.deactivate) || '';
			if(msg && !window.confirm(msg)){ e.preventDefault(); }
		});
		$(document).on('click', sel + ' .delete a', function(e){
			var msg = (shojaeiPluginsGuard.i18n && shojaeiPluginsGuard.i18n.delete) || '';
			if(msg && !window.confirm(msg)){ e.preventDefault(); }
		});
	});
})(jQuery);
JS;
		wp_add_inline_script( 'shojaei-seo-plugins-guard', $js );
	}

	/**
	 * AJAX: broken redirect audit (scan / disable).
	 */
	public function ajax_redirect_audit(): void {
		check_ajax_referer( 'shojaei_seo_admin_nonce', 'nonce' );

		if ( ! Shojaei_SEO_Helpers::user_can_admin() ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) ) );
		}

		if ( ! class_exists( 'Shojaei_SEO_Redirect_Audit' ) ) {
			wp_send_json_error( array( 'message' => __( 'ماژول سلامت ریدایرکت در دسترس نیست.', 'shojaei-seo-for-woo' ) ) );
		}

		$action = sanitize_text_field( wp_unslash( $_POST['audit_action'] ?? 'scan' ) );

		if ( 'scan' === $action ) {
			$report = Shojaei_SEO_Redirect_Audit::scan_broken();
			if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
				Shojaei_SEO_Activity_Log::add(
					'redirect_audit',
					sprintf(
						/* translators: %d: broken count */
						__( 'اسکن ریدایرکت شکسته: %d مورد', 'shojaei-seo-for-woo' ),
						(int) $report['broken']
					),
					0,
					array(
						'broken'  => (int) $report['broken'],
						'checked' => (int) $report['total_checked'],
					)
				);
			}
			wp_send_json_success(
				array(
					'message' => sprintf(
						/* translators: 1: broken, 2: checked */
						__( '%1$d ریدایرکت شکسته از %2$d فعال پیدا شد.', 'shojaei-seo-for-woo' ),
						(int) $report['broken'],
						(int) $report['total_checked']
					),
					'report'  => $report,
				)
			);
		}

		if ( 'scan_chains' === $action ) {
			$report = Shojaei_SEO_Redirect_Audit::scan_chains();
			if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
				Shojaei_SEO_Activity_Log::add(
					'redirect_audit',
					sprintf(
						/* translators: %d: chain count */
						__( 'اسکن زنجیره ریدایرکت: %d مورد', 'shojaei-seo-for-woo' ),
						(int) $report['chains']
					),
					0,
					array(
						'chains'  => (int) $report['chains'],
						'checked' => (int) $report['total_checked'],
					)
				);
			}
			wp_send_json_success(
				array(
					'message' => sprintf(
						/* translators: 1: chains, 2: checked */
						__( '%1$d زنجیره از %2$d ریدایرکت فعال پیدا شد.', 'shojaei-seo-for-woo' ),
						(int) $report['chains'],
						(int) $report['total_checked']
					),
					'report'  => $report,
				)
			);
		}

		if ( 'disable' === $action ) {
			$kind       = sanitize_key( wp_unslash( $_POST['kind'] ?? '' ) );
			$id         = absint( $_POST['id'] ?? 0 );
			$product_id = absint( $_POST['product_id'] ?? 0 );
			$result     = Shojaei_SEO_Redirect_Audit::disable_redirect( $kind, $id, $product_id );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}
			wp_send_json_success( array( 'message' => __( 'انجام شد.', 'shojaei-seo-for-woo' ) ) );
		}

		if ( 'flatten' === $action ) {
			$kind       = sanitize_key( wp_unslash( $_POST['kind'] ?? '' ) );
			$id         = absint( $_POST['id'] ?? 0 );
			$product_id = absint( $_POST['product_id'] ?? 0 );
			$result     = Shojaei_SEO_Redirect_Audit::flatten_chain( $kind, $id, $product_id );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}
			wp_send_json_success( array( 'message' => __( 'زنجیره صاف شد — مبدأ مستقیم به مقصد نهایی می‌رود.', 'shojaei-seo-for-woo' ) ) );
		}

		if ( 'scan_loops' === $action ) {
			$report = Shojaei_SEO_Redirect_Audit::scan_loops();
			if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
				Shojaei_SEO_Activity_Log::add(
					'redirect_audit',
					sprintf(
						/* translators: %d: loop count */
						__( 'اسکن حلقه ریدایرکت: %d مورد', 'shojaei-seo-for-woo' ),
						(int) $report['loops']
					),
					0,
					array(
						'loops'   => (int) $report['loops'],
						'checked' => (int) $report['total_checked'],
					)
				);
			}
			wp_send_json_success(
				array(
					'message' => sprintf(
						/* translators: 1: loops, 2: checked */
						__( '%1$d حلقه از %2$d ریدایرکت فعال پیدا شد.', 'shojaei-seo-for-woo' ),
						(int) $report['loops'],
						(int) $report['total_checked']
					),
					'report'  => $report,
				)
			);
		}

		if ( 'break_loop' === $action ) {
			$kind       = sanitize_key( wp_unslash( $_POST['kind'] ?? '' ) );
			$id         = absint( $_POST['id'] ?? 0 );
			$product_id = absint( $_POST['product_id'] ?? 0 );
			$result     = Shojaei_SEO_Redirect_Audit::break_loop( $kind, $id, $product_id );
			if ( is_wp_error( $result ) ) {
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}
			wp_send_json_success( array( 'message' => __( 'حلقه شکسته شد (ریدایرکت غیرفعال/لغو شد).', 'shojaei-seo-for-woo' ) ) );
		}

		wp_send_json_error( array( 'message' => __( 'عملیات نامعتبر است.', 'shojaei-seo-for-woo' ) ) );
	}

	/**
	 * Finish setup wizard / keep beginners on status board.
	 */
	public function handle_setup_wizard(): void {
		if ( ! Shojaei_SEO_Helpers::user_can_admin() ) {
			return;
		}

		if ( isset( $_POST['shojaei_seo_finish_setup'] ) ) {
			check_admin_referer( 'shojaei_seo_finish_setup', 'shojaei_seo_wizard_nonce' );
			if ( class_exists( 'Shojaei_SEO_Status' ) ) {
				Shojaei_SEO_Status::mark_setup_done();
			} else {
				update_option( 'shojaei_seo_setup_done', 'yes', false );
			}
			wp_safe_redirect( admin_url( 'admin.php?page=shojaei-seo&tab=dashboard' ) );
			exit;
		}
	}

	/**
	 * Save editable wizard defaults (with skip option).
	 */
	public function handle_wizard_defaults(): void {
		if ( ! isset( $_POST['shojaei_seo_save_wizard_defaults'], $_POST['shojaei_seo_wizard_defaults_nonce'] ) ) {
			return;
		}
		if ( ! Shojaei_SEO_Helpers::user_can_admin() ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['shojaei_seo_wizard_defaults_nonce'] ) ), 'shojaei_seo_wizard_defaults' ) ) {
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST['shojaei_seo_wizard_defaults_action'] ?? 'save_continue' ) );
		if ( 'skip' !== $action ) {
			$ints = array(
				'shojaei_seo_oos_message_day',
				'shojaei_seo_oos_temp_days',
				'shojaei_seo_oos_auto_day',
				'shojaei_seo_batch_size',
			);
			foreach ( $ints as $key ) {
				if ( ! isset( $_POST[ $key ] ) ) {
					continue;
				}
				$val = absint( $_POST[ $key ] );
				if ( 'shojaei_seo_batch_size' === $key ) {
					$val = max( 10, min( 200, $val ?: 50 ) );
				} else {
					$val = max( 1, min( 365, $val ?: 1 ) );
				}
				update_option( $key, (string) $val );
			}
			update_option( 'shojaei_seo_oos_phase1_days', (string) absint( $_POST['shojaei_seo_oos_message_day'] ?? 15 ) );
			update_option( 'shojaei_seo_oos_phase2_days', (string) absint( $_POST['shojaei_seo_oos_temp_days'] ?? 30 ) );
			update_option( 'shojaei_seo_oos_phase3_days', (string) absint( $_POST['shojaei_seo_oos_auto_day'] ?? 45 ) );

			$type = sanitize_text_field( wp_unslash( $_POST['shojaei_seo_oos_auto_redirect_type'] ?? '302' ) );
			update_option( 'shojaei_seo_oos_auto_redirect_type', in_array( $type, array( '301', '302' ), true ) ? $type : '302' );
			update_option( 'shojaei_seo_oos_dry_run', isset( $_POST['shojaei_seo_oos_dry_run'] ) ? 'yes' : 'no' );
			update_option( 'shojaei_seo_event_driven', isset( $_POST['shojaei_seo_event_driven'] ) ? 'yes' : 'no' );
		}

		wp_safe_redirect( admin_url( 'admin.php?page=shojaei-seo&tab=wizard&wizard_step=4' ) );
		exit;
	}

	/**
	 * Apply store profile from Impact tab or wizard.
	 */
	public function handle_store_profile(): void {
		if ( ! isset( $_POST['shojaei_seo_store_profile'], $_POST['shojaei_seo_profile_nonce'] ) ) {
			return;
		}
		if ( ! Shojaei_SEO_Helpers::user_can_admin() ) {
			return;
		}
		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['shojaei_seo_profile_nonce'] ) ), 'shojaei_seo_apply_profile' ) ) {
			return;
		}
		$profile = sanitize_key( wp_unslash( $_POST['shojaei_seo_store_profile'] ) );
		$ok      = class_exists( 'Shojaei_SEO_Impact' ) && Shojaei_SEO_Impact::apply_profile( $profile );
		if ( $ok ) {
			add_settings_error( 'shojaei_seo', 'profile_ok', __( 'پروفایل فروشگاهی اعمال شد.', 'shojaei-seo-for-woo' ), 'success' );
			if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
				Shojaei_SEO_Activity_Log::add( 'store_profile', sprintf( __( 'پروفایل فروشگاهی: %s', 'shojaei-seo-for-woo' ), $profile ) );
			}
		}
		$redirect = admin_url( 'admin.php?page=shojaei-seo&tab=impact' );
		if ( ! empty( $_POST['shojaei_seo_profile_from_wizard'] ) ) {
			$redirect = admin_url( 'admin.php?page=shojaei-seo&tab=wizard&wizard_step=3' );
		}
		wp_safe_redirect( $redirect );
		exit;
	}

	/**
	 * Handle settings form submissions.
	 */
	public function handle_form_submissions(): void {
		if ( ! isset( $_POST['shojaei_seo_settings_nonce'] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['shojaei_seo_settings_nonce'] ) ), 'shojaei_seo_save_settings' ) ) {
			return;
		}

		if ( ! Shojaei_SEO_Helpers::user_can_admin() ) {
			return;
		}

		// Dedicated save for متای عمومی — must not wipe other settings checkboxes.
		if ( ! empty( $_POST['shojaei_seo_save_general_meta'] ) && class_exists( 'Shojaei_SEO_General_Meta' ) ) {
			Shojaei_SEO_General_Meta::save_from_post();
			add_settings_error( 'shojaei_seo', 'meta_saved', __( 'متای عمومی ذخیره شد.', 'shojaei-seo-for-woo' ), 'success' );
			set_transient( 'settings_errors', get_settings_errors(), 30 );
			wp_safe_redirect( admin_url( 'admin.php?page=shojaei-seo&tab=general-meta&settings-updated=1' ) );
			exit;
		}

		$checkboxes = array(
			'shojaei_seo_oos_enabled',
			'shojaei_seo_event_driven',
			'shojaei_seo_link_builder_enabled',
			'shojaei_seo_checkout_box_enabled',
			'shojaei_seo_schema_enabled',
			'shojaei_seo_indexnow_enabled',
			'shojaei_seo_oos_auto_redirect',
			'shojaei_seo_oos_notify_enabled',
			'shojaei_seo_oos_noindex_enabled',
			'shojaei_seo_oos_dry_run',
			'shojaei_seo_oos_page_value_enabled',
			'shojaei_seo_schema_detect_enabled',
			'shojaei_seo_disable_wc_schema',
			'shojaei_seo_schema_product_enabled',
			'shojaei_seo_schema_breadcrumb_enabled',
			'shojaei_seo_schema_faq_enabled',
			'shojaei_seo_gsc_enabled',
			'shojaei_seo_gsc_auto_index',
			'shojaei_seo_link_whitelist_only',
			'shojaei_seo_schema_respect_seo_plugins',
			'shojaei_seo_variation_canonical',
			'shojaei_seo_slug_tools_enabled',
			'shojaei_seo_slug_auto_finglish',
			'shojaei_seo_slug_auto_301',
			'shojaei_seo_complementary_enabled',
			'shojaei_seo_ai_enabled',
			'shojaei_seo_schema_itemlist_enabled',
		);

		foreach ( $checkboxes as $key ) {
			update_option( $key, isset( $_POST[ $key ] ) ? 'yes' : 'no' );
		}

		// Uninstall policy: radio keep|wipe (default keep).
		if ( isset( $_POST['shojaei_seo_remove_data_on_uninstall'] ) ) {
			$wipe = sanitize_text_field( wp_unslash( $_POST['shojaei_seo_remove_data_on_uninstall'] ) );
			update_option( 'shojaei_seo_remove_data_on_uninstall', ( 'yes' === $wipe ) ? 'yes' : 'no' );
		}

		$fields = array(
			'shojaei_seo_oos_message_day',
			'shojaei_seo_oos_temp_days',
			'shojaei_seo_oos_auto_day',
			'shojaei_seo_oos_auto_redirect_type',
			'shojaei_seo_oos_page_value_threshold',
			'shojaei_seo_oos_phase1_days',
			'shojaei_seo_oos_phase2_days',
			'shojaei_seo_oos_phase3_days',
			'shojaei_seo_oos_match_threshold',
			'shojaei_seo_oos_msg_temp_title',
			'shojaei_seo_oos_msg_temp_cta',
			'shojaei_seo_oos_msg_unlikely_title',
			'shojaei_seo_oos_msg_unlikely_cta',
			'shojaei_seo_oos_msg_final_title',
			'shojaei_seo_oos_msg_final_cta',
			'shojaei_seo_link_max_per_1000',
			'shojaei_seo_link_max_per_page',
			'shojaei_seo_link_min_word_gap',
			'shojaei_seo_checkout_max_products',
			'shojaei_seo_currency',
			'shojaei_seo_currency_label',
			'shojaei_seo_indexnow_key',
			'shojaei_seo_oos_noindex_from_phase',
			'shojaei_seo_batch_size',
			'shojaei_seo_job_max_attempts',
			'shojaei_seo_gsc_site_url',
			'shojaei_seo_gsc_property_prefer',
			'shojaei_seo_complementary_mode',
			'shojaei_seo_complementary_limit',
			'shojaei_seo_oos_related_limit',
			'shojaei_seo_faq_returns_page_id',
			'shojaei_seo_ai_provider',
			'shojaei_seo_ai_timeout',
		);

		foreach ( $fields as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
				if ( 'shojaei_seo_batch_size' === $key ) {
					$value = (string) max( 10, min( 200, absint( $value ) ?: 50 ) );
				}
				if ( 'shojaei_seo_job_max_attempts' === $key ) {
					$value = (string) max( 1, min( 10, absint( $value ) ?: 3 ) );
				}
				if ( 'shojaei_seo_link_max_per_page' === $key ) {
					$value = (string) max( 1, min( 20, absint( $value ) ?: 5 ) );
				}
				if ( 'shojaei_seo_link_max_per_1000' === $key ) {
					$value = (string) max( 1, min( 10, absint( $value ) ?: 3 ) );
				}
				if ( 'shojaei_seo_complementary_limit' === $key ) {
					$value = (string) max( 2, min( 8, absint( $value ) ?: 4 ) );
				}
				if ( 'shojaei_seo_oos_related_limit' === $key ) {
					$value = (string) max( 2, min( 8, absint( $value ) ?: 4 ) );
				}
				if ( 'shojaei_seo_complementary_mode' === $key ) {
					$value = in_array( $value, array( 'always', 'oos_only' ), true ) ? $value : 'always';
				}
				if ( 'shojaei_seo_currency' === $key ) {
					$value = strtoupper( $value );
					$value = in_array( $value, array( 'IRT', 'IRR', 'USD', 'EUR', 'AED' ), true ) ? $value : 'IRT';
				}
				if ( 'shojaei_seo_gsc_site_url' === $key ) {
					$value = self::sanitize_gsc_site_url( $value );
				}
				if ( 'shojaei_seo_gsc_property_prefer' === $key ) {
					$value = in_array( $value, array( 'domain', 'url_prefix' ), true ) ? $value : 'domain';
				}
				if ( 'shojaei_seo_faq_returns_page_id' === $key ) {
					$value = (string) max( 0, absint( $value ) );
				}
				if ( 'shojaei_seo_ai_provider' === $key ) {
					$value = sanitize_key( $value );
					$value = in_array( $value, array( 'groq', 'openrouter' ), true ) ? $value : 'openrouter';
					update_option( $key, $value, false );
					continue;
				}
				if ( 'shojaei_seo_ai_timeout' === $key ) {
					$value = (string) max( 15, min( 90, absint( $value ) ?: 30 ) );
				}
				if ( 'shojaei_seo_indexnow_key' === $key && class_exists( 'SEO_Core_Installer' ) ) {
					SEO_Core_Installer::set_indexnow_key( $value );
				} else {
					update_option( $key, $value );
				}
			}
		}

		if ( isset( $_POST['shojaei_seo_ai_model'] ) ) { // phpcs:ignore
			$model = sanitize_text_field( wp_unslash( $_POST['shojaei_seo_ai_model'] ) ); // phpcs:ignore
			if ( '__custom__' === $model && isset( $_POST['shojaei_seo_ai_model_custom'] ) ) { // phpcs:ignore
				$model = sanitize_text_field( wp_unslash( $_POST['shojaei_seo_ai_model_custom'] ) ); // phpcs:ignore
			}
			if ( '' !== trim( $model ) && '__custom__' !== $model ) {
				$provider = isset( $_POST['shojaei_seo_ai_provider'] ) // phpcs:ignore
					? sanitize_key( wp_unslash( $_POST['shojaei_seo_ai_provider'] ) ) // phpcs:ignore
					: Shojaei_SEO_AI_Client::provider();
				$model = Shojaei_SEO_AI_Client::map_model_to_provider( trim( $model ), $provider );
				update_option( 'shojaei_seo_ai_model', $model, false );
			}
		}

		if ( isset( $_POST['shojaei_seo_ai_api_key'] ) ) { // phpcs:ignore
			$api_key = trim( (string) wp_unslash( $_POST['shojaei_seo_ai_api_key'] ) ); // phpcs:ignore
			if ( '' !== $api_key && 0 !== strpos( $api_key, '••••' ) ) {
				Shojaei_SEO_AI_Client::store_api_key( $api_key );
			}
		}

		if ( isset( $_POST['shojaei_seo_ai_relay_https_url'] ) ) { // phpcs:ignore
			$relay = Shojaei_SEO_AI_Client::sanitize_url( (string) wp_unslash( $_POST['shojaei_seo_ai_relay_https_url'] ) ); // phpcs:ignore
			update_option( Shojaei_SEO_AI_Client::OPT_RELAY_HTTPS, $relay, false );
		}

		if ( isset( $_POST['shojaei_seo_ai_relay_backup_urls'] ) ) { // phpcs:ignore
			$lines = sanitize_textarea_field( (string) wp_unslash( $_POST['shojaei_seo_ai_relay_backup_urls'] ) ); // phpcs:ignore
			update_option( Shojaei_SEO_AI_Client::OPT_RELAY_BACKUP, $lines, false );
		}

		if ( class_exists( 'Shojaei_SEO_Store_Profile' ) ) {
			Shojaei_SEO_Store_Profile::save_from_post( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized inside.
		}

		$textareas = array(
			'shojaei_seo_link_keyword_blacklist',
			'shojaei_seo_link_keyword_whitelist',
			'shojaei_seo_link_url_blacklist',
			'shojaei_seo_link_url_whitelist',
			'shojaei_seo_oos_msg_temp_body',
			'shojaei_seo_oos_msg_unlikely_body',
			'shojaei_seo_oos_msg_final_body',
			'shojaei_seo_oos_custom_css',
		);
		foreach ( $textareas as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				update_option( $key, sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) );
			}
		}

		if ( isset( $_POST['shojaei_seo_faq_returns_url'] ) ) {
			update_option(
				'shojaei_seo_faq_returns_url',
				esc_url_raw( wp_unslash( (string) $_POST['shojaei_seo_faq_returns_url'] ) )
			);
		}
		if ( class_exists( 'Damavand_FAQ_Box' ) ) {
			Damavand_FAQ_Box::flush_returns_detect_cache();
		}

		if ( isset( $_POST['shojaei_seo_finglish_dictionary'] ) && class_exists( 'Shojaei_SEO_Slug' ) ) {
			Shojaei_SEO_Slug::save_custom_dictionary_from_text(
				(string) wp_unslash( $_POST['shojaei_seo_finglish_dictionary'] ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in parser.
			);
		}

		if ( class_exists( 'Damavand_Similar_Products_Settings' ) ) {
			Damavand_Similar_Products_Settings::save_from_post( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized inside save_from_post.
		}

		// Keep legacy phase keys aligned with the multi-level timeline.
		if ( isset( $_POST['shojaei_seo_oos_message_day'], $_POST['shojaei_seo_oos_temp_days'], $_POST['shojaei_seo_oos_auto_day'] ) ) {
			update_option( 'shojaei_seo_oos_phase1_days', absint( $_POST['shojaei_seo_oos_message_day'] ) );
			update_option( 'shojaei_seo_oos_phase2_days', absint( $_POST['shojaei_seo_oos_temp_days'] ) );
			update_option( 'shojaei_seo_oos_phase3_days', absint( $_POST['shojaei_seo_oos_auto_day'] ) );
		}

		add_settings_error( 'shojaei_seo', 'settings_saved', __( 'تنظیمات با موفقیت ذخیره شد.', 'shojaei-seo-for-woo' ), 'success' );

		if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
			Shojaei_SEO_Activity_Log::add( 'settings_save', __( 'تنظیمات افزونه ذخیره شد.', 'shojaei-seo-for-woo' ) );
		}
	}

	/**
	 * Handle CSV export downloads (local only, no external APIs).
	 */
	public function handle_csv_export(): void {
		if ( ! isset( $_GET['shojaei_export'] ) || ! isset( $_GET['_wpnonce'] ) ) {
			return;
		}

		if ( ! Shojaei_SEO_Helpers::user_can_admin() ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'shojaei_seo_export' ) ) {
			return;
		}

		$type = sanitize_text_field( wp_unslash( $_GET['shojaei_export'] ) );
		if ( ! in_array( $type, array( 'redirects', 'oos', 'daily', 'dry_run' ), true ) ) {
			return;
		}

		global $wpdb;
		$rows   = array();
		$header = array();
		$filename = 'shojaei-seo-' . $type . '-' . gmdate( 'Y-m-d' ) . '.csv';

		if ( 'dry_run' === $type ) {
			$batch  = sanitize_text_field( wp_unslash( $_GET['batch_id'] ?? '' ) );
			$report = Shojaei_SEO_Revert_Log::get_dry_run_report( $batch );
			if ( ! $report ) {
				wp_die( esc_html__( 'گزارش Dry-Run یافت نشد. ابتدا شبیه‌سازی کنید.', 'shojaei-seo-for-woo' ) );
			}
			$export   = Shojaei_SEO_Revert_Log::export_dry_run_rows( $report );
			$header   = $export['header'];
			$rows     = $export['rows'];
			$filename = 'shojaei-seo-dry-run-' . substr( (string) ( $report['batch_id'] ?? 'report' ), 0, 8 ) . '-' . gmdate( 'Y-m-d' ) . '.csv';
		} elseif ( 'redirects' === $type ) {
			$header = array( 'ID', 'Product ID', 'Title', 'Redirect Type', 'Target URL', 'Reason', 'User ID', 'Created At', 'Is Undone' );
			$results = $wpdb->get_results(
				"SELECT l.*, p.post_title FROM " . Shojaei_SEO_Helpers::redirect_log_table() . " l
				LEFT JOIN {$wpdb->posts} p ON l.product_id = p.ID
				ORDER BY l.created_at DESC"
			);
			foreach ( $results as $row ) {
				$rows[] = array(
					$row->id,
					$row->product_id,
					$row->post_title,
					$row->redirect_type,
					$row->target_url,
					$row->reason,
					$row->user_id,
					$row->created_at,
					$row->is_undone,
				);
			}
		} elseif ( 'oos' === $type ) {
			$header = array( 'ID', 'Product ID', 'Title', 'OOS Date', 'Days OOS', 'Status', 'Redirect Type', 'Target URL' );
			$results = $wpdb->get_results(
				"SELECT t.*, p.post_title FROM " . Shojaei_SEO_Helpers::oos_table() . " t
				LEFT JOIN {$wpdb->posts} p ON t.product_id = p.ID
				ORDER BY t.days_oos DESC"
			);
			foreach ( $results as $row ) {
				$rows[] = array(
					$row->id,
					$row->product_id,
					$row->post_title,
					$row->oos_date,
					$row->days_oos,
					$row->status,
					$row->redirect_type,
					$row->target_url,
				);
			}
		} else {
			$header = array( 'Date', 'OOS Count', 'Candidates', 'Redirects', '410 Gone', 'Links Built' );
			$stats  = get_option( 'shojaei_seo_daily_stats', array() );
			if ( is_array( $stats ) ) {
				ksort( $stats );
				foreach ( $stats as $date => $day ) {
					$rows[] = array(
						$date,
						(int) ( $day['oos_count'] ?? 0 ),
						(int) ( $day['candidates'] ?? 0 ),
						(int) ( $day['redirects'] ?? 0 ),
						(int) ( $day['gone_410'] ?? 0 ),
						(int) ( $day['links_built'] ?? 0 ),
					);
				}
			}
		}

		nocache_headers();
		header( 'Content-Type: text/csv; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename=' . $filename );

		$out = fopen( 'php://output', 'w' );
		// UTF-8 BOM for Excel compatibility.
		fwrite( $out, "\xEF\xBB\xBF" );
		fputcsv( $out, Shojaei_SEO_Helpers::csv_safe_row( $header ) );
		foreach ( $rows as $row ) {
			fputcsv( $out, Shojaei_SEO_Helpers::csv_safe_row( (array) $row ) );
		}
		fclose( $out );
		exit;
	}

	/**
	 * AJAX: backfill OOS day counts from last paid sale (batches of 100).
	 */
	public function ajax_oos_days_scan(): void {
		check_ajax_referer( 'shojaei_seo_admin_nonce', 'nonce' );
		if ( ! Shojaei_SEO_Helpers::user_can_admin() ) {
			wp_send_json_error();
		}
		if ( ! class_exists( 'Shojaei_SEO_Batch' ) || ! class_exists( 'Shojaei_SEO_Helpers' ) ) {
			wp_send_json_error( array( 'message' => __( 'صف جاب در دسترس نیست.', 'shojaei-seo-for-woo' ) ) );
		}
		if ( Shojaei_SEO_Batch::has_active_job( 'oos_days_backfill' ) ) {
			wp_send_json_error( array( 'message' => __( 'اسکن روز ناموجودی همین حالا در حال اجراست.', 'shojaei-seo-for-woo' ) ) );
		}
		$total = Shojaei_SEO_Helpers::count_oos_date_backfill();
		if ( $total < 1 ) {
			wp_send_json_success(
				array(
					'message' => __( 'ردیفی برای تخمین تاریخ نمانده است.', 'shojaei-seo-for-woo' ),
					'queued'  => false,
				)
			);
		}
		$job_id = Shojaei_SEO_Jobs::enqueue(
			'oos_days_backfill',
			array(),
			array( 'total' => $total )
		);
		wp_send_json_success(
			array(
				'queued'  => true,
				'job_id'  => $job_id,
				'message' => sprintf(
					/* translators: %d: rows */
					__( 'اسکن روز ناموجودی برای %d محصول در صف است (هر مرحله ۱۰۰ ردیف).', 'shojaei-seo-for-woo' ),
					$total
				),
			)
		);
	}

	/**
	 * AJAX: force inventory rescan.
	 */
	public function ajax_force_rescan(): void {
		check_ajax_referer( 'shojaei_seo_admin_nonce', 'nonce' );

		if ( ! Shojaei_SEO_Helpers::user_can_admin() ) {
			wp_send_json_error();
		}

		$started = Shojaei_SEO_Queue::force_rescan();
		if ( ! $started ) {
			wp_send_json_error( array(
				'message' => __( 'اسکن قبلی هنوز در حال اجراست.', 'shojaei-seo-for-woo' ),
			) );
		}

		if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
			Shojaei_SEO_Activity_Log::add( 'force_rescan', __( 'اسکن مجدد موجودی از پنل شروع شد.', 'shojaei-seo-for-woo' ) );
		}

		wp_send_json_success( array(
			'message'  => __( 'اسکن مجدد در صف قرار گرفت.', 'shojaei-seo-for-woo' ),
			'progress' => class_exists( 'Shojaei_SEO_Queue' ) ? Shojaei_SEO_Queue::get_scan_progress() : array(),
		) );
	}

	/**
	 * AJAX: inventory scan progress (+ light job tick).
	 */
	public function ajax_scan_progress(): void {
		check_ajax_referer( 'shojaei_seo_admin_nonce', 'nonce' );

		if ( ! Shojaei_SEO_Helpers::user_can_admin() ) {
			wp_send_json_error();
		}

		// Advance queue while the progress UI is open (helps when AS/cron is slow).
		if ( class_exists( 'Shojaei_SEO_Jobs' ) ) {
			Shojaei_SEO_Jobs::run_next();
		}
		if ( class_exists( 'Shojaei_SEO_Queue' ) ) {
			$q = new Shojaei_SEO_Queue();
			$q->process_queue();
		}
		// Nudge Action Scheduler so delayed scan jobs actually start during polling.
		if ( class_exists( 'ActionScheduler_QueueRunner' ) ) {
			try {
				$runner = ActionScheduler_QueueRunner::instance();
				if ( $runner && method_exists( $runner, 'run' ) ) {
					$runner->run( 'Shojaei SEO scan progress' );
				}
			} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Non-fatal: progress UI still reports option counters.
			}
		}

		wp_send_json_success(
			class_exists( 'Shojaei_SEO_Queue' )
				? Shojaei_SEO_Queue::get_scan_progress()
				: array( 'done' => true, 'percent' => 100, 'label' => '' )
		);
	}

	/**
	 * AJAX: diagnose a product.
	 */
	public function ajax_product_test(): void {
		check_ajax_referer( 'shojaei_seo_admin_nonce', 'nonce' );

		if ( ! Shojaei_SEO_Helpers::user_can_admin() ) {
			wp_send_json_error();
		}

		$product_id = absint( $_POST['product_id'] ?? 0 );
		if ( ! $product_id ) {
			wp_send_json_error( array( 'message' => __( 'شناسه محصول نامعتبر است.', 'shojaei-seo-for-woo' ) ) );
		}

		$manager = new Shojaei_SEO_OOS_Manager( false );
		$result  = $manager->diagnose_product( $product_id );

		if ( ! empty( $result['error'] ) ) {
			wp_send_json_error( array( 'message' => $result['error'] ) );
		}

		if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
			Shojaei_SEO_Activity_Log::add(
				'product_test',
				sprintf(
					/* translators: %s: product title */
					__( 'تست محصول «%s»', 'shojaei-seo-for-woo' ),
					$result['title'] ?? $product_id
				),
				$product_id
			);
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Dry-Run simulation (redirect bulk or link build).
	 */
	public function ajax_dry_run(): void {
		check_ajax_referer( 'shojaei_seo_admin_nonce', 'nonce' );

		if ( ! Shojaei_SEO_Helpers::user_can_admin() ) {
			wp_send_json_error();
		}

		$type = sanitize_key( wp_unslash( $_POST['dry_run_type'] ?? '' ) );

		if ( 'redirect' === $type ) {
			$action = sanitize_text_field( wp_unslash( $_POST['bulk_action'] ?? '' ) );
			$ids    = isset( $_POST['product_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['product_ids'] ) ) : array();
			$target = esc_url_raw( wp_unslash( $_POST['target_url'] ?? '' ) );

			if ( empty( $ids ) ) {
				wp_send_json_error( array( 'message' => __( 'محصولی انتخاب نشده است.', 'shojaei-seo-for-woo' ) ) );
			}

			$threshold = class_exists( 'Shojaei_SEO_Batch' ) ? Shojaei_SEO_Batch::batch_size() : 50;
			if ( count( $ids ) > $threshold && class_exists( 'Shojaei_SEO_Batch' ) ) {
				$job_id = Shojaei_SEO_Batch::enqueue(
					'dry_run_redirect',
					array(
						'product_ids' => $ids,
						'action'      => $action,
						'target_url'  => $target ?: null,
					)
				);
				wp_send_json_success( array(
					'queued'  => true,
					'job_id'  => $job_id,
					'message' => sprintf(
						/* translators: 1: count, 2: batch size */
						__( 'شبیه‌سازی %1$d محصول در صف پس‌زمینه قرار گرفت (هر اجرا حدود %2$d محصول).', 'shojaei-seo-for-woo' ),
						count( $ids ),
						$threshold
					),
				) );
			}

			$result = Shojaei_SEO_Revert_Log::dry_run_bulk_redirect( $ids, $action, $target ?: null );
			wp_send_json_success( $result );
		}

		if ( 'links' === $type ) {
			$post_id = absint( $_POST['post_id'] ?? 0 );
			if ( ! $post_id ) {
				wp_send_json_error( array( 'message' => __( 'نوشته انتخاب نشده است.', 'shojaei-seo-for-woo' ) ) );
			}
			$result = Shojaei_SEO_Revert_Log::dry_run_link_build( $post_id );
			wp_send_json_success( $result );
		}

		wp_send_json_error( array( 'message' => __( 'نوع شبیه‌سازی نامعتبر است.', 'shojaei-seo-for-woo' ) ) );
	}

	/**
	 * AJAX: Apply real changes from a Dry-Run preview batch.
	 */
	public function ajax_dry_run_apply(): void {
		check_ajax_referer( 'shojaei_seo_admin_nonce', 'nonce' );

		if ( ! Shojaei_SEO_Helpers::user_can_admin() ) {
			wp_send_json_error();
		}

		$batch = sanitize_text_field( wp_unslash( $_POST['batch_id'] ?? '' ) );
		if ( ! $batch ) {
			wp_send_json_error( array( 'message' => __( 'batch_id نامعتبر است.', 'shojaei-seo-for-woo' ) ) );
		}

		$force  = ! empty( $_POST['force_confirm'] );
		$result = Shojaei_SEO_Revert_Log::apply_from_dry_run( $batch, $force );
		if ( (int) ( $result['ok'] ?? 0 ) < 1 && (int) ( $result['fail'] ?? 0 ) > 0 ) {
			wp_send_json_error( $result );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: Preview Undo effects before applying rollback.
	 */
	public function ajax_undo_preview(): void {
		check_ajax_referer( 'shojaei_seo_admin_nonce', 'nonce' );

		if ( ! Shojaei_SEO_Helpers::user_can_admin() ) {
			wp_send_json_error();
		}

		$scope = sanitize_key( wp_unslash( $_POST['scope'] ?? 'one' ) );

		if ( 'batch' === $scope ) {
			$batch = sanitize_text_field( wp_unslash( $_POST['batch_id'] ?? '' ) );
			if ( ! $batch ) {
				wp_send_json_error( array( 'message' => __( 'batch_id نامعتبر است.', 'shojaei-seo-for-woo' ) ) );
			}
			wp_send_json_success( Shojaei_SEO_Revert_Log::preview_rollback_batch( $batch ) );
		}

		$log_id  = absint( $_POST['log_id'] ?? 0 );
		$preview = Shojaei_SEO_Revert_Log::preview_rollback_one( $log_id );
		if ( is_wp_error( $preview ) ) {
			wp_send_json_error( array( 'message' => $preview->get_error_message() ) );
		}

		wp_send_json_success( $preview );
	}

	/**
	 * AJAX: Rollback one entry or a batch.
	 */
	public function ajax_rollback(): void {
		check_ajax_referer( 'shojaei_seo_admin_nonce', 'nonce' );

		if ( ! Shojaei_SEO_Helpers::user_can_admin() ) {
			wp_send_json_error();
		}

		$scope = sanitize_key( wp_unslash( $_POST['scope'] ?? 'one' ) );

		if ( 'batch' === $scope ) {
			$batch = sanitize_text_field( wp_unslash( $_POST['batch_id'] ?? '' ) );
			if ( ! $batch ) {
				wp_send_json_error();
			}
			$stats = Shojaei_SEO_Revert_Log::rollback_batch( $batch );
			wp_send_json_success( array(
				'message' => sprintf(
					/* translators: 1: ok, 2: fail */
					__( 'بازگردانی دسته: %1$d موفق، %2$d ناموفق.', 'shojaei-seo-for-woo' ),
					$stats['ok'],
					$stats['fail']
				),
			) );
		}

		$log_id = absint( $_POST['log_id'] ?? 0 );
		$result = Shojaei_SEO_Revert_Log::rollback_one( $log_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'بازگردانی انجام شد.', 'shojaei-seo-for-woo' ) ) );
	}

	/**
	 * AJAX: scan a URL for schema conflicts.
	 */
	public function ajax_schema_scan(): void {
		check_ajax_referer( 'shojaei_seo_admin_nonce', 'nonce' );

		if ( ! Shojaei_SEO_Helpers::user_can_admin() ) {
			wp_send_json_error();
		}

		$url = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );
		if ( ! $url ) {
			// Default: first published product.
			$products = get_posts( array(
				'post_type'      => 'product',
				'post_status'    => 'publish',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			) );
			$url = $products ? get_permalink( (int) $products[0] ) : home_url( '/' );
		}

		$result = Shojaei_SEO_Schema_Detector::scan_url( (string) $url );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( $result );
	}

	/**
	 * AJAX: one-click disable WooCommerce default schema (conflict fix).
	 */
	public function ajax_disable_wc_schema(): void {
		check_ajax_referer( 'shojaei_seo_admin_nonce', 'nonce' );

		if ( ! Shojaei_SEO_Helpers::user_can_admin() ) {
			wp_send_json_error();
		}

		update_option( 'shojaei_seo_disable_wc_schema', 'yes' );
		if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
			Shojaei_SEO_Activity_Log::add( 'schema_fix', __( 'اسکیمای پیش‌فرض ووکامرس از بنر تداخل خاموش شد.', 'shojaei-seo-for-woo' ) );
		}

		wp_send_json_success(
			array(
				'message' => __( 'اسکیمای ووکامرس غیرفعال شد. صفحه را رفرش کنید.', 'shojaei-seo-for-woo' ),
			)
		);
	}

	/**
	 * AJAX handler for redirect actions.
	 */
	public function ajax_redirect_action(): void {
		check_ajax_referer( 'shojaei_seo_admin_nonce', 'nonce' );

		if ( ! Shojaei_SEO_Helpers::user_can_admin() ) {
			wp_send_json_error();
		}

		$action     = sanitize_text_field( wp_unslash( $_POST['redirect_action'] ?? '' ) );
		$product_id = absint( $_POST['product_id'] ?? 0 );
		$force      = ! empty( $_POST['force_confirm'] );
		$manager    = new Shojaei_SEO_OOS_Manager( false );

		switch ( $action ) {
			case 'redirect_301':
				$target = esc_url_raw( wp_unslash( $_POST['target_url'] ?? '' ) );
				$result = $manager->apply_manual_redirect( $product_id, '301', $target, $force );
				break;
			case 'redirect_302':
				$target = esc_url_raw( wp_unslash( $_POST['target_url'] ?? '' ) );
				$result = $manager->apply_manual_redirect( $product_id, '302', $target, $force );
				break;
			case 'redirect_410':
				$result = $manager->apply_manual_redirect( $product_id, '410', '', $force );
				break;
			case 'keep':
				$manager->keep_page( $product_id );
				$result = true;
				break;
			default:
				wp_send_json_error();
		}

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array(
				'message'         => $result->get_error_message(),
				'code'            => $result->get_error_code(),
				'requires_manual' => 'high_page_value' === $result->get_error_code(),
				'page_value'      => $result->get_error_data(),
			) );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX handler for bulk redirect actions.
	 */
	public function ajax_bulk_redirect(): void {
		check_ajax_referer( 'shojaei_seo_admin_nonce', 'nonce' );

		if ( ! Shojaei_SEO_Helpers::user_can_admin() ) {
			wp_send_json_error();
		}

		$action      = sanitize_text_field( wp_unslash( $_POST['bulk_action'] ?? '' ) );
		$product_ids = isset( $_POST['product_ids'] ) ? array_map( 'absint', (array) wp_unslash( $_POST['product_ids'] ) ) : array();
		$target_url  = esc_url_raw( wp_unslash( $_POST['target_url'] ?? '' ) );
		$force       = ! empty( $_POST['force_confirm'] );

		if ( empty( $product_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'محصولی انتخاب نشده است.', 'shojaei-seo-for-woo' ) ) );
		}

		$allowed = array( 'redirect_301', 'redirect_302', 'redirect_410', 'keep' );
		if ( ! in_array( $action, $allowed, true ) ) {
			wp_send_json_error();
		}

		if ( in_array( $action, array( 'redirect_301', 'redirect_302' ), true ) && empty( $target_url ) ) {
			// Optional: each product will use its own suggested target URL.
		}

		$threshold = class_exists( 'Shojaei_SEO_Batch' ) ? Shojaei_SEO_Batch::batch_size() : 50;

		// Large selections → WP-Cron / Action Scheduler batches.
		if ( count( $product_ids ) > $threshold && class_exists( 'Shojaei_SEO_Batch' ) ) {
			$job_id = Shojaei_SEO_Batch::enqueue(
				'bulk_redirect',
				array(
					'product_ids'    => $product_ids,
					'action'         => $action,
					'target_url'     => $target_url ?: null,
					'force_confirm'  => $force,
				)
			);

			if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
				Shojaei_SEO_Activity_Log::add(
					'bulk_queued',
					sprintf(
						/* translators: 1: action, 2: count */
						__( 'عملیات گروهی «%1$s» برای %2$d محصول در صف پس‌زمینه', 'shojaei-seo-for-woo' ),
						$action,
						count( $product_ids )
					),
					0,
					array( 'action' => $action, 'count' => count( $product_ids ), 'job_id' => $job_id )
				);
			}

			wp_send_json_success( array(
				'queued'    => true,
				'job_id'    => $job_id,
				'processed' => 0,
				'total'     => count( $product_ids ),
				'message'   => sprintf(
					/* translators: 1: count, 2: batch */
					__( '%1$d محصول در صف پس‌زمینه قرار گرفت (هر اجرا حدود %2$d محصول).', 'shojaei-seo-for-woo' ),
					count( $product_ids ),
					$threshold
				),
			) );
		}

		$manager   = new Shojaei_SEO_OOS_Manager( false );
		$processed = $manager->bulk_action( $product_ids, $action, $target_url ?: null, $force );

		if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
			Shojaei_SEO_Activity_Log::add(
				'bulk_action',
				sprintf(
					/* translators: 1: action, 2: count */
					__( 'عملیات گروهی «%1$s» روی %2$d محصول', 'shojaei-seo-for-woo' ),
					$action,
					$processed
				),
				0,
				array( 'action' => $action, 'count' => $processed, 'ids' => $product_ids )
			);
		}

		wp_send_json_success( array(
			'processed' => $processed,
			'message'   => sprintf(
				/* translators: %d: number of products */
				__( '%d محصول پردازش شد.', 'shojaei-seo-for-woo' ),
				$processed
			),
		) );
	}

	/**
	 * AJAX: poll batch job status.
	 */
	public function ajax_batch_status(): void {
		check_ajax_referer( 'shojaei_seo_admin_nonce', 'nonce' );

		if ( ! Shojaei_SEO_Helpers::user_can_admin() ) {
			wp_send_json_error();
		}

		$job_id = sanitize_text_field( wp_unslash( $_POST['job_id'] ?? '' ) );
		if ( $job_id ) {
			$job = Shojaei_SEO_Batch::get_job( $job_id );
			if ( ! $job ) {
				wp_send_json_error( array( 'message' => __( 'جاب یافت نشد.', 'shojaei-seo-for-woo' ) ) );
			}
			wp_send_json_success( $job );
		}

		wp_send_json_success( array(
			'jobs'       => Shojaei_SEO_Batch::list_jobs( 8 ),
			'batch_size' => Shojaei_SEO_Batch::batch_size(),
		) );
	}

	/**
	 * AJAX: upload GSC Service Account JSON.
	 */
	public function ajax_gsc_upload(): void {
		check_ajax_referer( 'shojaei_seo_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'فقط مدیرکل می‌تواند کلید Search Console را آپلود کند.', 'shojaei-seo-for-woo' ) ) );
		}

		$raw = '';
		if ( ! empty( $_FILES['gsc_key']['tmp_name'] ) && is_uploaded_file( $_FILES['gsc_key']['tmp_name'] ) ) {
			$size = isset( $_FILES['gsc_key']['size'] ) ? (int) $_FILES['gsc_key']['size'] : 0;
			$name = isset( $_FILES['gsc_key']['name'] ) ? (string) $_FILES['gsc_key']['name'] : '';
			if ( $size > 200000 ) {
				wp_send_json_error( array( 'message' => __( 'حجم فایل کلید بیش از حد مجاز است.', 'shojaei-seo-for-woo' ) ) );
			}
			if ( $name && ! preg_match( '/\.json$/i', $name ) ) {
				wp_send_json_error( array( 'message' => __( 'فقط فایل با پسوند .json پذیرفته می‌شود.', 'shojaei-seo-for-woo' ) ) );
			}
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			$raw = (string) file_get_contents( $_FILES['gsc_key']['tmp_name'] );
		} elseif ( ! empty( $_POST['gsc_json'] ) ) {
			$raw = wp_unslash( $_POST['gsc_json'] );
		}

		if ( '' === trim( $raw ) ) {
			wp_send_json_error( array( 'message' => __( 'فایل کلید انتخاب نشده است.', 'shojaei-seo-for-woo' ) ) );
		}

		$result = Shojaei_SEO_GSC::save_credentials_json( $raw );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		if ( isset( $_POST['site_url'] ) || isset( $_POST['gsc_key'] ) ) {
			if ( isset( $_POST['shojaei_seo_gsc_property_prefer'] ) ) {
				$prefer = sanitize_text_field( wp_unslash( $_POST['shojaei_seo_gsc_property_prefer'] ) );
				$prefer = in_array( $prefer, array( 'domain', 'url_prefix' ), true ) ? $prefer : 'domain';
				update_option( 'shojaei_seo_gsc_property_prefer', $prefer, false );
			}
			if ( isset( $_POST['site_url'] ) ) {
				$site = self::sanitize_gsc_site_url( sanitize_text_field( wp_unslash( $_POST['site_url'] ) ) );
				update_option( 'shojaei_seo_gsc_site_url', $site, false );
			}
		}

		$status = Shojaei_SEO_GSC::verify_connection( false );

		wp_send_json_success( array(
			'message' => __( 'کلید ذخیره شد — تشخیص لایه‌ای اجرا شد.', 'shojaei-seo-for-woo' ),
			'status'  => $status,
		) );
	}

	/**
	 * AJAX: re-verify GSC connection (layered).
	 */
	public function ajax_gsc_verify(): void {
		check_ajax_referer( 'shojaei_seo_admin_nonce', 'nonce' );

		if ( ! Shojaei_SEO_Helpers::user_can_admin() ) {
			wp_send_json_error();
		}

		if ( isset( $_POST['site_url'] ) ) {
			if ( isset( $_POST['shojaei_seo_gsc_property_prefer'] ) ) {
				$prefer = sanitize_text_field( wp_unslash( $_POST['shojaei_seo_gsc_property_prefer'] ) );
				$prefer = in_array( $prefer, array( 'domain', 'url_prefix' ), true ) ? $prefer : 'domain';
				update_option( 'shojaei_seo_gsc_property_prefer', $prefer, false );
			}
			$site = self::sanitize_gsc_site_url( sanitize_text_field( wp_unslash( $_POST['site_url'] ) ) );
			update_option( 'shojaei_seo_gsc_site_url', $site, false );
		}

		$probe  = ! empty( $_POST['probe_indexing'] );
		$status = Shojaei_SEO_GSC::verify_connection( $probe );
		wp_send_json_success( array( 'status' => $status ) );
	}

	/**
	 * Sanitize GSC property string (sc-domain:… or URL-prefix).
	 *
	 * @param string $value Raw.
	 */
	private static function sanitize_gsc_site_url( string $value ): string {
		$prefer = (string) ( $_POST['shojaei_seo_gsc_property_prefer'] ?? get_option( 'shojaei_seo_gsc_property_prefer', 'domain' ) );
		$prefer = sanitize_text_field( wp_unslash( (string) $prefer ) );
		return Shojaei_SEO_GSC::normalize_gsc_property( $value, $prefer );
	}

	/**
	 * AJAX: disconnect GSC.
	 */
	public function ajax_gsc_disconnect(): void {
		check_ajax_referer( 'shojaei_seo_admin_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'فقط مدیرکل می‌تواند اتصال را قطع کند.', 'shojaei-seo-for-woo' ) ) );
		}

		Shojaei_SEO_GSC::disconnect();
		wp_send_json_success( array(
			'message' => __( 'اتصال قطع شد.', 'shojaei-seo-for-woo' ),
		) );
	}

	/**
	 * AJAX: manual Request Indexing / Inspection test.
	 */
	public function ajax_gsc_test_url(): void {
		check_ajax_referer( 'shojaei_seo_admin_nonce', 'nonce' );

		if ( ! Shojaei_SEO_Helpers::user_can_admin() ) {
			wp_send_json_error();
		}

		if ( isset( $_POST['shojaei_seo_gsc_property_prefer'] ) ) {
			$prefer = sanitize_text_field( wp_unslash( $_POST['shojaei_seo_gsc_property_prefer'] ) );
			$prefer = in_array( $prefer, array( 'domain', 'url_prefix' ), true ) ? $prefer : 'domain';
			update_option( 'shojaei_seo_gsc_property_prefer', $prefer, false );
		}
		if ( isset( $_POST['site_url'] ) ) {
			$site = self::sanitize_gsc_site_url( sanitize_text_field( wp_unslash( $_POST['site_url'] ) ) );
			update_option( 'shojaei_seo_gsc_site_url', $site, false );
		}

		$url = esc_url_raw( wp_unslash( $_POST['url'] ?? '' ) );
		if ( ! $url ) {
			$url = home_url( '/' );
		}

		$report = Shojaei_SEO_GSC::testIndexingConnection( $url );
		if ( empty( $report['ok'] ) ) {
			wp_send_json_error(
				array(
					'message'     => (string) ( $report['admin_message'] ?? __( 'Indexing test failed.', 'shojaei-seo-for-woo' ) ),
					'error_code'  => (string) ( $report['error_code'] ?? '' ),
					'technical'   => (array) ( $report['technical_log'] ?? array() ),
					'preflight'   => (array) ( $report['preflight'] ?? array() ),
					'recent_logs' => (array) ( $report['recent_failures'] ?? array() ),
				)
			);
		}

		wp_send_json_success(
			array(
				'message'     => (string) ( $report['admin_message'] ?? __( 'Indexing test passed.', 'shojaei-seo-for-woo' ) ),
				'technical'   => (array) ( $report['technical_log'] ?? array() ),
				'preflight'   => (array) ( $report['preflight'] ?? array() ),
				'recent_logs' => (array) ( $report['recent_failures'] ?? array() ),
			)
		);
	}

	/**
	 * AJAX handler for undo redirect.
	 */
	public function ajax_undo_redirect(): void {
		check_ajax_referer( 'shojaei_seo_admin_nonce', 'nonce' );

		if ( ! Shojaei_SEO_Helpers::user_can_admin() ) {
			wp_send_json_error();
		}

		$product_id = absint( $_POST['product_id'] ?? 0 );
		$log_id     = absint( $_POST['log_id'] ?? 0 );
		$manager    = new Shojaei_SEO_OOS_Manager( false );

		if ( ! $manager->undo_redirect( $product_id, $log_id ) ) {
			wp_send_json_error( array( 'message' => __( 'لغو ریدایرکت ممکن نیست.', 'shojaei-seo-for-woo' ) ) );
		}

		wp_send_json_success();
	}

	/**
	 * AJAX handler for link preview.
	 */
	public function ajax_link_preview(): void {
		check_ajax_referer( 'shojaei_seo_admin_nonce', 'nonce' );

		if ( ! Shojaei_SEO_Helpers::user_can_admin() ) {
			wp_send_json_error();
		}

		$post_id = absint( $_POST['post_id'] ?? 0 );
		$content = isset( $_POST['content'] ) ? wp_kses_post( wp_unslash( $_POST['content'] ) ) : '';

		if ( $post_id ) {
			$post = get_post( $post_id );
			if ( $post ) {
				$content = $post->post_content;
			}
		}

		if ( empty( trim( $content ) ) ) {
			wp_send_json_error( array( 'message' => __( 'متنی برای پیش‌نمایش وجود ندارد.', 'shojaei-seo-for-woo' ) ) );
		}

		$builder = new Shojaei_SEO_Link_Builder( false );
		$result  = $builder->preview_links( $content, $post_id );

		$skipped_labels = array();
		if ( ! empty( $result['skipped'] ) && class_exists( 'Shojaei_SEO_Link_Rules' ) ) {
			foreach ( array_slice( $result['skipped'], 0, 12 ) as $skip ) {
				$reason = (string) ( $skip['reason'] ?? '' );
				$skipped_labels[] = array(
					'keyword' => (string) ( $skip['keyword'] ?? '' ),
					'reason'  => Shojaei_SEO_Link_Rules::reason_label( $reason ),
				);
			}
		}

		wp_send_json_success( array(
			'content'         => wp_kses_post( $result['content'] ),
			'links_added'     => (int) ( $result['links_added'] ?? 0 ),
			'details'         => $result['details'] ?? array(),
			'max_allowed'     => (int) ( $result['max_allowed'] ?? 0 ),
			'word_count'      => (int) ( $result['word_count'] ?? 0 ),
			'skipped'         => $skipped_labels,
			'existing_links'  => (int) ( $result['existing_links'] ?? 0 ),
			'existing_list'   => array_slice( $result['existing_list'] ?? array(), 0, 20 ),
		) );
	}

	/**
	 * AJAX handler for in-dashboard notifications.
	 */
	public function ajax_notification_action(): void {
		check_ajax_referer( 'shojaei_seo_admin_nonce', 'nonce' );

		if ( ! Shojaei_SEO_Helpers::user_can_admin() ) {
			wp_send_json_error();
		}

		$action = sanitize_text_field( wp_unslash( $_POST['notification_action'] ?? '' ) );
		$id     = sanitize_text_field( wp_unslash( $_POST['notification_id'] ?? '' ) );

		switch ( $action ) {
			case 'read':
				Shojaei_SEO_Notifications::mark_read( $id );
				break;
			case 'read_all':
				Shojaei_SEO_Notifications::mark_all_read();
				break;
			case 'dismiss':
				Shojaei_SEO_Notifications::dismiss( $id );
				break;
			default:
				wp_send_json_error();
		}

		wp_send_json_success( array(
			'unread' => Shojaei_SEO_Notifications::unread_count(),
		) );
	}

	/**
	 * AJAX handler for internal link CRUD.
	 */
	public function ajax_link_action(): void {
		check_ajax_referer( 'shojaei_seo_admin_nonce', 'nonce' );

		if ( ! Shojaei_SEO_Helpers::user_can_admin() ) {
			wp_send_json_error();
		}

		global $wpdb;
		$table  = Shojaei_SEO_Helpers::links_table();
		$action = sanitize_text_field( wp_unslash( $_POST['link_action'] ?? '' ) );

		switch ( $action ) {
			case 'add':
				$keyword = sanitize_text_field( wp_unslash( $_POST['keyword'] ?? '' ) );
				$url     = esc_url_raw( wp_unslash( $_POST['target_url'] ?? '' ) );
				if ( $keyword && $url ) {
					$wpdb->insert( $table, array( 'keyword' => $keyword, 'target_url' => $url ), array( '%s', '%s' ) );
					if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
						Shojaei_SEO_Activity_Log::add(
							'link_add',
							sprintf(
								/* translators: 1: keyword, 2: url */
								__( 'افزودن لینک «%1$s» → %2$s', 'shojaei-seo-for-woo' ),
								$keyword,
								$url
							),
							0,
							array( 'keyword' => $keyword, 'url' => $url )
						);
					}
				}
				break;
			case 'delete':
				$id = absint( $_POST['link_id'] ?? 0 );
				$wpdb->delete( $table, array( 'id' => $id ), array( '%d' ) );
				if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
					Shojaei_SEO_Activity_Log::add(
						'link_delete',
						sprintf(
							/* translators: %d: link id */
							__( 'حذف لینک داخلی #%d', 'shojaei-seo-for-woo' ),
							$id
						),
						0,
						array( 'link_id' => $id )
					);
				}
				break;
			case 'toggle':
				$id     = absint( $_POST['link_id'] ?? 0 );
				$active = absint( $_POST['is_active'] ?? 0 );
				$wpdb->update( $table, array( 'is_active' => $active ), array( 'id' => $id ), array( '%d' ), array( '%d' ) );
				break;
			default:
				wp_send_json_error();
		}

		wp_send_json_success();
	}

	/**
	 * AJAX: slug redirect CRUD + health apply/preview.
	 */
	public function ajax_slug_action(): void {
		check_ajax_referer( 'shojaei_seo_admin_nonce', 'nonce' );

		if ( ! Shojaei_SEO_Helpers::user_can_admin() ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) ) );
		}

		if ( ! class_exists( 'Shojaei_SEO_Slug' ) ) {
			wp_send_json_error( array( 'message' => __( 'ماژول نامک در دسترس نیست.', 'shojaei-seo-for-woo' ) ) );
		}

		$action = sanitize_text_field( wp_unslash( $_POST['slug_action'] ?? '' ) );

		switch ( $action ) {
			case 'toggle':
				$id     = absint( $_POST['redirect_id'] ?? 0 );
				$active = absint( $_POST['is_active'] ?? 0 );
				$ok     = Shojaei_SEO_Slug::set_redirect_active( $id, $active );
				if ( ! $ok ) {
					wp_send_json_error( array( 'message' => __( 'به‌روزرسانی ناموفق بود.', 'shojaei-seo-for-woo' ) ) );
				}
				if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
					Shojaei_SEO_Activity_Log::add(
						'slug_redirect',
						$active
							? sprintf( __( 'فعال‌سازی ریدایرکت نامک #%d', 'shojaei-seo-for-woo' ), $id )
							: sprintf( __( 'غیرفعال‌سازی ریدایرکت نامک #%d', 'shojaei-seo-for-woo' ), $id ),
						0,
						array( 'redirect_id' => $id, 'is_active' => $active )
					);
				}
				wp_send_json_success();
				break;

			case 'delete':
				$id = absint( $_POST['redirect_id'] ?? 0 );
				$ok = Shojaei_SEO_Slug::delete_redirect( $id );
				if ( ! $ok ) {
					wp_send_json_error( array( 'message' => __( 'حذف ناموفق بود.', 'shojaei-seo-for-woo' ) ) );
				}
				if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
					Shojaei_SEO_Activity_Log::add(
						'slug_redirect',
						sprintf( __( 'حذف ریدایرکت نامک #%d', 'shojaei-seo-for-woo' ), $id ),
						0,
						array( 'redirect_id' => $id )
					);
				}
				wp_send_json_success();
				break;

			case 'preview':
			case 'apply':
				$product_id = absint( $_POST['product_id'] ?? 0 );
				$dry        = ( 'preview' === $action );
				$result     = Shojaei_SEO_Slug::apply_suggested_slug( $product_id, $dry );
				if ( empty( $result['ok'] ) ) {
					wp_send_json_error( $result );
				}
				wp_send_json_success( $result );
				break;

			case 'batch_preview':
			case 'batch_apply':
				$raw = isset( $_POST['product_ids'] ) ? wp_unslash( $_POST['product_ids'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				if ( is_string( $raw ) ) {
					$decoded = json_decode( $raw, true );
					$raw     = is_array( $decoded ) ? $decoded : preg_split( '/\s*,\s*/', $raw );
				}
				if ( ! is_array( $raw ) ) {
					$raw = array();
				}
				$ids = array_map( 'absint', $raw );
				$dry = ( 'batch_preview' === $action );
				wp_send_json_success( Shojaei_SEO_Slug::batch_apply( $ids, $dry ) );
				break;

			case 'undo':
				$redirect_id = absint( $_POST['redirect_id'] ?? 0 );
				$result      = Shojaei_SEO_Slug::undo_slug_redirect( $redirect_id );
				if ( empty( $result['ok'] ) ) {
					wp_send_json_error( $result );
				}
				wp_send_json_success( $result );
				break;

			case 'start_full_scan':
				$result = Shojaei_SEO_Slug::start_full_health_scan();
				if ( empty( $result['ok'] ) ) {
					wp_send_json_error( $result );
				}
				wp_send_json_success( $result );
				break;

			case 'search_products':
				$q = isset( $_POST['q'] ) ? sanitize_text_field( wp_unslash( $_POST['q'] ) ) : '';
				if ( strlen( $q ) < 1 ) {
					wp_send_json_error( array( 'message' => __( 'عبارت جستجو را وارد کنید.', 'shojaei-seo-for-woo' ) ) );
				}
				wp_send_json_success(
					array(
						'rows' => Shojaei_SEO_Slug::search_products_for_slug( $q, 25 ),
						'q'    => $q,
					)
				);
				break;

			case 'dict_preview':
				$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
				$fa    = isset( $_POST['fa'] ) ? sanitize_text_field( wp_unslash( $_POST['fa'] ) ) : '';
				$en    = isset( $_POST['en'] ) ? sanitize_text_field( wp_unslash( $_POST['en'] ) ) : '';
				if ( '' !== $fa && '' !== $en ) {
					Shojaei_SEO_Slug::set_preview_overlay(
						array(
							Shojaei_SEO_Slug::normalize_dict_key( $fa ) => Shojaei_SEO_Slug::normalize_dict_value( $en ),
						)
					);
				}
				$sample = $title ? $title : ( $fa ? $fa : 'کتونی نیوبالانس ۵۳۰ مردانه' );
				wp_send_json_success(
					array(
						'slug' => Shojaei_SEO_Slug::transliterate( $sample ),
					)
				);
				break;

			case 'dict_add':
				$fa     = isset( $_POST['fa'] ) ? sanitize_text_field( wp_unslash( $_POST['fa'] ) ) : '';
				$en     = isset( $_POST['en'] ) ? sanitize_text_field( wp_unslash( $_POST['en'] ) ) : '';
				$result = Shojaei_SEO_Slug::upsert_dictionary_entry( $fa, $en );
				if ( empty( $result['ok'] ) ) {
					wp_send_json_error( $result );
				}
				wp_send_json_success( $result );
				break;

			case 'dict_delete':
				$fa = isset( $_POST['fa'] ) ? sanitize_text_field( wp_unslash( $_POST['fa'] ) ) : '';
				if ( ! Shojaei_SEO_Slug::delete_dictionary_entry( $fa ) ) {
					wp_send_json_error( array( 'message' => __( 'واژه پیدا نشد.', 'shojaei-seo-for-woo' ) ) );
				}
				wp_send_json_success( array( 'message' => __( 'حذف شد.', 'shojaei-seo-for-woo' ) ) );
				break;

			default:
				wp_send_json_error( array( 'message' => __( 'عملیات نامعتبر.', 'shojaei-seo-for-woo' ) ) );
		}
	}

	/**
	 * AJAX: manual redirect CRUD.
	 */
	public function ajax_manual_redirect(): void {
		check_ajax_referer( 'shojaei_seo_admin_nonce', 'nonce' );

		if ( ! Shojaei_SEO_Helpers::user_can_admin() ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) ) );
		}

		if ( ! class_exists( 'Shojaei_SEO_Manual_Redirect' ) ) {
			wp_send_json_error( array( 'message' => __( 'ماژول ریدایرکت دستی در دسترس نیست.', 'shojaei-seo-for-woo' ) ) );
		}

		$action = sanitize_text_field( wp_unslash( $_POST['mr_action'] ?? '' ) );

		switch ( $action ) {
			case 'add':
				$sources = isset( $_POST['sources'] ) ? wp_unslash( $_POST['sources'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				if ( is_string( $sources ) ) {
					$decoded = json_decode( $sources, true );
					$sources = is_array( $decoded ) ? $decoded : array( $sources );
				}
				if ( ! is_array( $sources ) ) {
					$sources = array();
				}
				$sources = array_map( 'sanitize_text_field', $sources );
				$result  = Shojaei_SEO_Manual_Redirect::add_redirect(
					array(
						'sources'           => $sources,
						'destination'       => isset( $_POST['destination'] ) ? sanitize_text_field( wp_unslash( $_POST['destination'] ) ) : '',
						'redirect_type'     => isset( $_POST['redirect_type'] ) ? sanitize_text_field( wp_unslash( $_POST['redirect_type'] ) ) : '301',
						'match_type'        => isset( $_POST['match_type'] ) ? sanitize_text_field( wp_unslash( $_POST['match_type'] ) ) : 'exact',
						'ignore_case'       => ! empty( $_POST['ignore_case'] ),
						'is_active'         => ! isset( $_POST['is_active'] ) || '0' !== (string) wp_unslash( $_POST['is_active'] ),
						'covers_pagination' => ! isset( $_POST['covers_pagination'] ) || ! empty( $_POST['covers_pagination'] ),
					)
				);
				if ( empty( $result['ok'] ) ) {
					wp_send_json_error( $result );
				}
				wp_send_json_success( $result );
				break;

			case 'archive_preview':
				$raw = isset( $_POST['source'] ) ? sanitize_text_field( wp_unslash( (string) $_POST['source'] ) ) : '';
				wp_send_json_success( Shojaei_SEO_Manual_Redirect::preview_archive_source( $raw ) );
				break;

			case 'toggle':
				$id     = absint( $_POST['redirect_id'] ?? 0 );
				$active = absint( $_POST['is_active'] ?? 0 );
				if ( ! Shojaei_SEO_Manual_Redirect::set_active( $id, $active ) ) {
					wp_send_json_error( array( 'message' => __( 'به‌روزرسانی ناموفق بود.', 'shojaei-seo-for-woo' ) ) );
				}
				wp_send_json_success();
				break;

			case 'delete':
				$id = absint( $_POST['redirect_id'] ?? 0 );
				if ( ! Shojaei_SEO_Manual_Redirect::delete( $id ) ) {
					wp_send_json_error( array( 'message' => __( 'حذف ناموفق بود.', 'shojaei-seo-for-woo' ) ) );
				}
				wp_send_json_success();
				break;

			default:
				wp_send_json_error( array( 'message' => __( 'عملیات نامعتبر.', 'shojaei-seo-for-woo' ) ) );
		}
	}

	/**
	 * AJAX: Link Genius (keyword maps + inventory).
	 */
	public function ajax_link_genius(): void {
		check_ajax_referer( 'shojaei_seo_admin_nonce', 'nonce' );

		if ( ! Shojaei_SEO_Helpers::user_can_admin() ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) ) );
		}
		if ( ! class_exists( 'Shojaei_SEO_Link_Genius' ) ) {
			wp_send_json_error( array( 'message' => __( 'ماژول نابغه لینک در دسترس نیست.', 'shojaei-seo-for-woo' ) ) );
		}

		$action = sanitize_text_field( wp_unslash( $_POST['lg_action'] ?? '' ) );

		switch ( $action ) {
			case 'save_map':
				$result = Shojaei_SEO_Link_Genius::save_map(
					array(
						'id'             => absint( $_POST['map_id'] ?? 0 ),
						'name'           => isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '',
						'target_url'     => isset( $_POST['target_url'] ) ? esc_url_raw( wp_unslash( $_POST['target_url'] ) ) : '',
						'keywords'       => isset( $_POST['keywords'] ) ? sanitize_textarea_field( wp_unslash( $_POST['keywords'] ) ) : '',
						'max_per_post'   => absint( $_POST['max_per_post'] ?? 3 ),
						'case_sensitive' => ! empty( $_POST['case_sensitive'] ),
						'is_active'      => ! isset( $_POST['is_active'] ) || '0' !== (string) wp_unslash( $_POST['is_active'] ),
					)
				);
				if ( empty( $result['ok'] ) ) {
					wp_send_json_error( $result );
				}
				wp_send_json_success( $result );
				break;

			case 'toggle_map':
				$id     = absint( $_POST['map_id'] ?? 0 );
				$active = absint( $_POST['is_active'] ?? 0 );
				if ( ! Shojaei_SEO_Link_Genius::set_map_active( $id, $active ) ) {
					wp_send_json_error( array( 'message' => __( 'به‌روزرسانی ناموفق بود.', 'shojaei-seo-for-woo' ) ) );
				}
				wp_send_json_success();
				break;

			case 'delete_map':
				$id = absint( $_POST['map_id'] ?? 0 );
				if ( ! Shojaei_SEO_Link_Genius::delete_map( $id ) ) {
					wp_send_json_error( array( 'message' => __( 'حذف ناموفق بود.', 'shojaei-seo-for-woo' ) ) );
				}
				wp_send_json_success();
				break;

			case 'start_crawl':
				$result = Shojaei_SEO_Link_Genius::start_inventory_crawl();
				if ( empty( $result['ok'] ) ) {
					wp_send_json_error( $result );
				}
				wp_send_json_success( $result );
				break;

			case 'http_check':
				$result = Shojaei_SEO_Link_Genius::check_http_statuses( 50 );
				wp_send_json_success(
					array(
						'message' => sprintf(
							/* translators: %d: checked */
							__( '%d لینک از نظر HTTP بررسی شد.', 'shojaei-seo-for-woo' ),
							(int) ( $result['checked'] ?? 0 )
						),
						'checked' => (int) ( $result['checked'] ?? 0 ),
					)
				);
				break;

			case 'suggest_orphan':
				$target_id = absint( $_POST['post_id'] ?? 0 );
				$result    = Shojaei_SEO_Link_Genius::suggest_orphan_sources( $target_id, 5 );
				if ( empty( $result['ok'] ) ) {
					wp_send_json_error( $result );
				}
				wp_send_json_success( $result );
				break;

			case 'apply_orphan_fix':
				$target_id  = absint( $_POST['post_id'] ?? 0 );
				$source_raw = isset( $_POST['source_ids'] ) ? wp_unslash( $_POST['source_ids'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
				if ( is_string( $source_raw ) ) {
					$source_raw = array_filter( array_map( 'absint', explode( ',', $source_raw ) ) );
				} elseif ( is_array( $source_raw ) ) {
					$source_raw = array_map( 'absint', $source_raw );
				} else {
					$source_raw = array();
				}
				$keywords = isset( $_POST['keywords'] ) ? sanitize_textarea_field( wp_unslash( $_POST['keywords'] ) ) : '';
				$result   = Shojaei_SEO_Link_Genius::apply_orphan_fix( $target_id, $source_raw, $keywords );
				if ( empty( $result['ok'] ) ) {
					wp_send_json_error( $result );
				}
				wp_send_json_success( $result );
				break;

			case 'fix_remove_link':
				if ( ! class_exists( 'Damavand_Link_Suggestions' ) ) {
					wp_send_json_error( array( 'message' => __( 'ماژول نگهبان لینک در دسترس نیست.', 'shojaei-seo-for-woo' ) ) );
				}
				$source_id = absint( $_POST['source_post_id'] ?? 0 );
				$dest_url  = isset( $_POST['dest_url'] ) ? esc_url_raw( wp_unslash( (string) $_POST['dest_url'] ) ) : '';
				$result    = Damavand_Link_Suggestions::remove_internal_link( $source_id, $dest_url );
				if ( empty( $result['ok'] ) ) {
					wp_send_json_error( $result );
				}
				if ( class_exists( 'Damavand_Link_Watchdog' ) && ! empty( $_POST['alert_id'] ) ) {
					Damavand_Link_Watchdog::dismiss_alert( sanitize_text_field( wp_unslash( (string) $_POST['alert_id'] ) ) );
				}
				wp_send_json_success( $result );
				break;

			case 'fix_update_link':
				if ( ! class_exists( 'Damavand_Link_Suggestions' ) ) {
					wp_send_json_error( array( 'message' => __( 'ماژول نگهبان لینک در دسترس نیست.', 'shojaei-seo-for-woo' ) ) );
				}
				$source_id = absint( $_POST['source_post_id'] ?? 0 );
				$old_url   = isset( $_POST['dest_url'] ) ? esc_url_raw( wp_unslash( (string) $_POST['dest_url'] ) ) : '';
				$new_url   = isset( $_POST['new_url'] ) ? esc_url_raw( wp_unslash( (string) $_POST['new_url'] ) ) : '';
				if ( '' === $new_url && class_exists( 'Shojaei_SEO_Link_Genius' ) && $old_url ) {
					$probe   = Shojaei_SEO_Link_Genius::probe_url( $old_url );
					$new_url = (string) ( $probe['redirect_url'] ?? '' );
				}
				$result = Damavand_Link_Suggestions::update_internal_link_url( $source_id, $old_url, $new_url );
				if ( empty( $result['ok'] ) ) {
					wp_send_json_error( $result );
				}
				wp_send_json_success( $result );
				break;

			default:
				wp_send_json_error( array( 'message' => __( 'عملیات نامعتبر.', 'shojaei-seo-for-woo' ) ) );
		}
	}

	/**
	 * AJAX: SEO Pulse (نبض سئو) — queue background scan.
	 */
	public function ajax_seo_pulse(): void {
		check_ajax_referer( 'shojaei_seo_admin_nonce', 'nonce' );

		if ( ! Shojaei_SEO_Helpers::user_can_admin() ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) ) );
		}
		if ( ! class_exists( 'Shojaei_SEO_Pulse' ) ) {
			wp_send_json_error( array( 'message' => __( 'ماژول نبض سئو در دسترس نیست.', 'shojaei-seo-for-woo' ) ) );
		}

		$action = sanitize_text_field( wp_unslash( $_POST['pulse_action'] ?? '' ) );

		switch ( $action ) {
			case 'start_scan':
				$result = Shojaei_SEO_Pulse::start_scan( true );
				if ( empty( $result['ok'] ) ) {
					wp_send_json_error( $result );
				}
				wp_send_json_success( $result );
				break;

			case 'analyze_one':
				$post_id = absint( $_POST['post_id'] ?? 0 );
				$result  = Shojaei_SEO_Pulse::analyze_one( $post_id, true );
				if ( empty( $result['ok'] ) ) {
					wp_send_json_error( $result );
				}
				wp_send_json_success( $result );
				break;

			case 'stats':
				wp_send_json_success( Shojaei_SEO_Pulse::dashboard_stats() );
				break;

			default:
				wp_send_json_error( array( 'message' => __( 'عملیات نامعتبر.', 'shojaei-seo-for-woo' ) ) );
		}
	}

	/**
	 * AJAX: هسته سئو — فعال/غیرفعال ماژول و Override.
	 */
	public function ajax_seo_core(): void {
		check_ajax_referer( 'shojaei_seo_admin_nonce', 'nonce' );

		if ( ! Shojaei_SEO_Helpers::user_can_admin() ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) ) );
		}

		$action = sanitize_key( wp_unslash( $_POST['core_action'] ?? '' ) );
		$module = sanitize_key( wp_unslash( $_POST['module'] ?? '' ) );

		if ( 'toggle_module' === $action ) {
			if ( '' === $module ) {
				wp_send_json_error( array( 'message' => __( 'ماژول نامعتبر.', 'shojaei-seo-for-woo' ) ) );
			}
			$opt_key = class_exists( 'SEO_Core_Installer' ) ? SEO_Core_Installer::MODULES_OPTION : 'shojaei_seo_core_modules';
			$opts    = get_option( $opt_key, array() );
			if ( ! is_array( $opts ) ) {
				$opts = array();
			}
			$opts[ $module ] = ! empty( $_POST['enabled'] );
			update_option( $opt_key, $opts, false );
			if ( class_exists( 'SEO_Core_Installer' ) ) {
				SEO_Core_Installer::invalidate_health_cache();
				SEO_Core_Installer::request_rewrite_flush();
			}
			wp_send_json_success(
				array(
					'message' => __( 'وضعیت ماژول ذخیره شد. برای اعمال rewrite یک بار صفحه را رفرش کنید.', 'shojaei-seo-for-woo' ),
				)
			);
		}

		if ( 'toggle_override' === $action ) {
			if ( '' === $module ) {
				wp_send_json_error( array( 'message' => __( 'ماژول نامعتبر.', 'shojaei-seo-for-woo' ) ) );
			}
			$key = 'shojaei_seo_core_' . $module . '_override';
			$on  = ! empty( $_POST['enabled'] );
			if ( class_exists( 'SEO_Core_Installer' ) ) {
				SEO_Core_Installer::set_override( $module, $on );
				SEO_Core_Installer::request_rewrite_flush();
			} else {
				update_option( $key, $on ? 'yes' : 'no', false );
			}
			wp_send_json_success(
				array(
					'message' => $on
						? __( 'حالت جایگزینی روشن شد. خروجی ماژول حتی با Rank Math/Yoast فعال می‌شود.', 'shojaei-seo-for-woo' )
						: __( 'حالت جایگزینی خاموش شد — برگشت به حالت کمکی.', 'shojaei-seo-for-woo' ),
				)
			);
		}

		if ( 'heal' === $action ) {
			if ( ! class_exists( 'SEO_Core_Installer' ) ) {
				wp_send_json_error( array( 'message' => __( 'نصب‌کننده هسته سئو در دسترس نیست.', 'shojaei-seo-for-woo' ) ) );
			}
			SEO_Core_Installer::invalidate_health_cache();
			$result = SEO_Core_Installer::ensure_infrastructure( true );
			wp_send_json_success(
				array(
					'message'  => (string) ( $result['message'] ?? '' ),
					'ok'       => ! empty( $result['ok'] ),
					'repaired' => $result['repaired'] ?? array(),
					'healthy'  => $result['healthy'] ?? array(),
					'errors'   => $result['errors'] ?? array(),
					'disabled' => $result['disabled'] ?? array(),
				)
			);
		}

		if ( 'self_test' === $action ) {
			if ( ! class_exists( 'SEO_Core_Self_Test' ) ) {
				$file = DAMAVAND_SEO_DIR . 'seo-core/class-seo-core-self-test.php';
				if ( is_readable( $file ) ) {
					require_once $file;
				}
			}
			if ( ! class_exists( 'SEO_Core_Self_Test' ) ) {
				wp_send_json_error( array( 'message' => __( 'کلاس خودآزمون در دسترس نیست.', 'shojaei-seo-for-woo' ) ) );
			}
			$result = SEO_Core_Self_Test::run();
			wp_send_json_success( $result );
		}

		wp_send_json_error( array( 'message' => __( 'عملیات نامعتبر.', 'shojaei-seo-for-woo' ) ) );
	}

	/**
	 * Map raw tab to primary hub + view file slug.
	 *
	 * @param string $tab Raw tab.
	 * @return array{primary:string,view:string,ops:bool,guide:bool}
	 */
	private function resolve_nav_context( string $tab ): array {
		$ops_tabs   = array( 'oos', 'links', 'slugs', 'slug-train', 'redirects', 'manual-redirects', 'simulate', 'test' );
		$guide_tabs = array( 'education', 'activity', 'notifications' );

		if ( 'ops' === $tab ) {
			$tab = 'oos';
		}
		if ( 'guide' === $tab ) {
			$tab = 'education';
		}

		$primary = 'dashboard';
		if ( in_array( $tab, $ops_tabs, true ) ) {
			$primary = 'ops';
		} elseif ( in_array( $tab, $guide_tabs, true ) ) {
			$primary = 'guide';
		} elseif ( 'impact' === $tab ) {
			$primary = 'impact';
		} elseif ( 'settings' === $tab ) {
			$primary = 'settings';
		} elseif ( 'general-meta' === $tab ) {
			$primary = 'general-meta';
		} elseif ( 'seo-pulse' === $tab ) {
			$primary = 'seo-pulse';
		} elseif ( 'seo-core' === $tab ) {
			$primary = 'seo-core';
		} elseif ( in_array( $tab, array( 'keyword-maps', 'link-bulk', 'link-inventory', 'link-posts', 'link-genius' ), true ) ) {
			$primary = 'link-genius';
			if ( 'link-genius' === $tab ) {
				$tab = 'keyword-maps';
			}
		} elseif ( 'migrate' === $tab ) {
			$primary = 'migrate';
		} elseif ( 'wizard' === $tab ) {
			$primary = 'wizard';
		} elseif ( 'dashboard' === $tab ) {
			$primary = 'dashboard';
		}

		return array(
			'primary'     => $primary,
			'view'        => $tab,
			'ops'         => 'ops' === $primary,
			'guide'       => 'guide' === $primary,
			'link_genius' => 'link-genius' === $primary,
		);
	}

	/**
	 * Render main admin page.
	 */
	public function render_page(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$raw_tab = sanitize_text_field( wp_unslash( $_GET['tab'] ?? '' ) );
		if ( '' === $raw_tab ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$page_slug = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : 'shojaei-seo';
			$page_map  = array(
				'shojaei-seo'          => 'dashboard',
				'shojaei-seo-ops'      => 'oos',
				'shojaei-seo-impact'   => 'impact',
				'shojaei-seo-meta'     => 'general-meta',
				'shojaei-seo-pulse'    => 'seo-pulse',
				'shojaei-seo-core'     => 'seo-core',
				'shojaei-seo-migrate'  => 'migrate',
				'shojaei-seo-links'    => 'keyword-maps',
				'shojaei-seo-settings' => 'settings',
				'shojaei-seo-guide'    => 'education',
			);
			$raw_tab = $page_map[ $page_slug ] ?? 'dashboard';
		}
		$allowed = array(
			'dashboard',
			'wizard',
			'impact',
			'oos',
			'links',
			'slugs',
			'slug-train',
			'redirects',
			'manual-redirects',
			'test',
			'simulate',
			'activity',
			'notifications',
			'settings',
			'general-meta',
			'seo-pulse',
			'seo-core',
			'migrate',
			'link-genius',
			'keyword-maps',
			'link-bulk',
			'link-inventory',
			'link-posts',
			'education',
			'ops',
			'guide',
		);

		if ( ! in_array( $raw_tab, $allowed, true ) ) {
			$raw_tab = 'dashboard';
		}

		$nav               = $this->resolve_nav_context( $raw_tab );
		$this->current_tab = $nav['view'];

		// First-run: only force wizard on dashboard landing — never steal explicit tabs.
		if (
			class_exists( 'Shojaei_SEO_Status' )
			&& ! Shojaei_SEO_Status::is_setup_done()
			&& 'dashboard' === $this->current_tab
			&& empty( $_POST )
		) {
			$this->current_tab = 'wizard';
			$nav               = $this->resolve_nav_context( 'wizard' );
		}

		settings_errors( 'shojaei_seo' );
		$unread = class_exists( 'Shojaei_SEO_Notifications' ) ? Shojaei_SEO_Notifications::unread_count() : 0;
		$icon   = static function ( string $name, int $size = 16 ): string {
			return class_exists( 'Damavand_SEO_Icons' ) ? Damavand_SEO_Icons::svg( $name, $size ) : '';
		};
		?>
		<div class="shojaei-seo-wrap shojaei-ia-v2 damavand-saas" dir="rtl">
			<header class="damavand-app-header" role="banner">
				<div class="damavand-app-header__brand">
					<span class="damavand-app-header__mark" aria-hidden="true"><?php echo class_exists( 'Damavand_SEO_Icons' ) ? Damavand_SEO_Icons::brand_mark( 20 ) : $icon( 'line-chart', 20 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
					<div class="damavand-app-header__titles">
						<h1 class="damavand-app-header__title"><?php esc_html_e( 'افزونه سئو حرفه‌ای دماوند', 'shojaei-seo-for-woo' ); ?></h1>
						<p class="damavand-app-header__sub"><?php esc_html_e( 'عملیات سئو فروشگاه — ببینید چه باید بکنید', 'shojaei-seo-for-woo' ); ?></p>
					</div>
					<span class="damavand-app-header__ver">v<?php echo esc_html( DAMAVAND_SEO_VERSION ); ?></span>
				</div>
				<p class="damavand-app-header__credit"><?php esc_html_e( 'توسعه: اسماعیل شجاعی', 'shojaei-seo-for-woo' ); ?></p>
			</header>

			<nav class="shojaei-seo-tabs shojaei-seo-tabs--primary damavand-app-nav" aria-label="<?php esc_attr_e( 'منوی اصلی', 'shojaei-seo-for-woo' ); ?>">
				<?php
				$tabs = array(
					'dashboard'    => array(
						'icon'  => 'layout-dashboard',
						'label' => __( 'وضعیت', 'shojaei-seo-for-woo' ),
						'url'   => admin_url( 'admin.php?page=shojaei-seo&tab=dashboard' ),
					),
					'ops'          => array(
						'icon'  => 'wrench',
						'label' => __( 'عملیات', 'shojaei-seo-for-woo' ),
						'url'   => admin_url( 'admin.php?page=shojaei-seo&tab=oos' ),
					),
					'impact'       => array(
						'icon'  => 'chart-pie',
						'label' => __( 'آمار', 'shojaei-seo-for-woo' ),
						'url'   => admin_url( 'admin.php?page=shojaei-seo&tab=impact' ),
					),
					'general-meta' => array(
						'icon'  => 'tags',
						'label' => __( 'متای عمومی', 'shojaei-seo-for-woo' ),
						'url'   => admin_url( 'admin.php?page=shojaei-seo&tab=general-meta' ),
					),
					'seo-pulse'    => array(
						'icon'  => 'activity',
						'label' => __( 'نبض سئو', 'shojaei-seo-for-woo' ),
						'url'   => admin_url( 'admin.php?page=shojaei-seo&tab=seo-pulse' ),
					),
					'seo-core'     => array(
						'icon'  => 'network',
						'label' => __( 'هسته سئو', 'shojaei-seo-for-woo' ),
						'url'   => admin_url( 'admin.php?page=shojaei-seo&tab=seo-core' ),
					),
					'migrate'      => array(
						'icon'  => 'arrow-left-right',
						'label' => __( 'مهاجرت', 'shojaei-seo-for-woo' ),
						'url'   => admin_url( 'admin.php?page=shojaei-seo&tab=migrate' ),
					),
					'link-genius'  => array(
						'icon'  => 'link-2',
						'label' => __( 'نابغه لینک', 'shojaei-seo-for-woo' ),
						'url'   => admin_url( 'admin.php?page=shojaei-seo&tab=keyword-maps' ),
					),
					'settings'     => array(
						'icon'  => 'settings',
						'label' => __( 'تنظیمات', 'shojaei-seo-for-woo' ),
						'url'   => admin_url( 'admin.php?page=shojaei-seo&tab=settings' ),
					),
					'guide'        => array(
						'icon'  => 'book-open',
						'label' => __( 'راهنما', 'shojaei-seo-for-woo' ),
						'url'   => admin_url( 'admin.php?page=shojaei-seo&tab=education' ),
						'badge' => $unread,
					),
				);
				if ( class_exists( 'Shojaei_SEO_Status' ) && ! Shojaei_SEO_Status::is_setup_done() ) {
					$tabs = array(
						'wizard' => array(
							'icon'  => 'flag',
							'label' => __( 'راه‌اندازی', 'shojaei-seo-for-woo' ),
							'url'   => admin_url( 'admin.php?page=shojaei-seo&tab=wizard' ),
						),
					) + $tabs;
				}
				foreach ( $tabs as $slug => $tab ) :
					$active = ( $nav['primary'] === $slug ) ? ' is-active' : '';
					?>
					<a href="<?php echo esc_url( $tab['url'] ); ?>" class="shojaei-tab<?php echo esc_attr( $active ); ?>"<?php echo $active ? ' aria-current="page"' : ''; ?> title="<?php echo esc_attr( $tab['label'] ); ?>">
						<span class="shojaei-tab-icon"><?php echo $icon( $tab['icon'], 16 ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
						<span class="shojaei-tab-label"><?php echo esc_html( $tab['label'] ); ?></span>
						<?php if ( ! empty( $tab['badge'] ) ) : ?>
							<span class="shojaei-tab-badge"><?php echo esc_html( (string) (int) $tab['badge'] ); ?></span>
						<?php endif; ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="shojaei-seo-content damavand-app-main">
				<?php
				if ( ! empty( $nav['ops'] ) ) {
					$shojaei_subnav_label   = __( 'ابزار عملیات', 'shojaei-seo-for-woo' );
					$shojaei_subnav_current = $this->current_tab;
					$shojaei_subnav_items   = array(
						'oos'              => array(
							'label' => __( 'موجودی', 'shojaei-seo-for-woo' ),
							'icon'  => 'package',
							'url'   => admin_url( 'admin.php?page=shojaei-seo&tab=oos' ),
						),
						'slugs'            => array(
							'label' => __( 'نامک', 'shojaei-seo-for-woo' ),
							'icon'  => 'code-2',
							'url'   => admin_url( 'admin.php?page=shojaei-seo&tab=slugs' ),
						),
						'slug-train'       => array(
							'label' => __( 'آموزش نامک', 'shojaei-seo-for-woo' ),
							'icon'  => 'graduation-cap',
							'url'   => admin_url( 'admin.php?page=shojaei-seo&tab=slug-train' ),
						),
						'redirects'        => array(
							'label' => __( 'سلامت ریدایرکت', 'shojaei-seo-for-woo' ),
							'icon'  => 'route',
							'url'   => admin_url( 'admin.php?page=shojaei-seo&tab=redirects' ),
						),
						'manual-redirects' => array(
							'label' => __( 'ریدایرکت دستی', 'shojaei-seo-for-woo' ),
							'icon'  => 'arrow-left-right',
							'url'   => admin_url( 'admin.php?page=shojaei-seo&tab=manual-redirects' ),
						),
						'links'            => array(
							'label' => __( 'لینک‌ها (قدیمی)', 'shojaei-seo-for-woo' ),
							'icon'  => 'link-2',
							'url'   => admin_url( 'admin.php?page=shojaei-seo&tab=links' ),
						),
						'simulate'         => array(
							'label' => __( 'Dry-Run', 'shojaei-seo-for-woo' ),
							'icon'  => 'eye',
							'url'   => admin_url( 'admin.php?page=shojaei-seo&tab=simulate' ),
						),
						'test'             => array(
							'label' => __( 'تست محصول', 'shojaei-seo-for-woo' ),
							'icon'  => 'search',
							'url'   => admin_url( 'admin.php?page=shojaei-seo&tab=test' ),
						),
					);
					include DAMAVAND_SEO_DIR . 'admin/views/_subnav.php';
				}

				if ( ! empty( $nav['link_genius'] ) ) {
					$shojaei_subnav_label   = __( 'نابغه لینک', 'shojaei-seo-for-woo' );
					$shojaei_subnav_current = $this->current_tab;
					$shojaei_subnav_items   = array(
						'keyword-maps'   => array(
							'label' => __( 'نقشه کلمات کلیدی', 'shojaei-seo-for-woo' ),
							'icon'  => 'map',
							'url'   => admin_url( 'admin.php?page=shojaei-seo&tab=keyword-maps' ),
						),
						'link-bulk'      => array(
							'label' => __( 'به‌روزرسانی گروهی', 'shojaei-seo-for-woo' ),
							'icon'  => 'refresh-cw',
							'url'   => admin_url( 'admin.php?page=shojaei-seo&tab=link-bulk' ),
						),
						'link-inventory' => array(
							'label' => __( 'نگهبان لینک', 'shojaei-seo-for-woo' ),
							'icon'  => 'search',
							'url'   => admin_url( 'admin.php?page=shojaei-seo&tab=link-inventory' ),
						),
						'link-posts'     => array(
							'label' => __( 'بررسی نوشته‌ها', 'shojaei-seo-for-woo' ),
							'icon'  => 'file-text',
							'url'   => admin_url( 'admin.php?page=shojaei-seo&tab=link-posts' ),
						),
					);
					include DAMAVAND_SEO_DIR . 'admin/views/_subnav.php';
				}

				if ( ! empty( $nav['guide'] ) ) {
					$shojaei_subnav_label   = __( 'راهنما و سوابق', 'shojaei-seo-for-woo' );
					$shojaei_subnav_current = $this->current_tab;
					$shojaei_subnav_items   = array(
						'education'     => array(
							'label' => __( 'آموزش', 'shojaei-seo-for-woo' ),
							'icon'  => 'graduation-cap',
							'url'   => admin_url( 'admin.php?page=shojaei-seo&tab=education' ),
						),
						'notifications' => array(
							'label' => __( 'اعلان‌ها', 'shojaei-seo-for-woo' ),
							'icon'  => 'bell',
							'url'   => admin_url( 'admin.php?page=shojaei-seo&tab=notifications' ),
							'badge' => $unread,
						),
						'activity'      => array(
							'label' => __( 'لاگ', 'shojaei-seo-for-woo' ),
							'icon'  => 'list',
							'url'   => admin_url( 'admin.php?page=shojaei-seo&tab=activity' ),
						),
					);
					include DAMAVAND_SEO_DIR . 'admin/views/_subnav.php';
				}

				$view_file = DAMAVAND_SEO_DIR . 'admin/views/' . $this->current_tab . '.php';
				if ( file_exists( $view_file ) ) {
					include $view_file;
				}
				?>
			</div>
		</div>
		<?php
	}
}
