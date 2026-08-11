<?php
/**
 * Plugin Name: افزونه وردپرس مدیریت هوشمند انبار سبلان
 * Plugin URI: https://to3edev.ir
 * Description: مدیریت سریع موجودی، قیمت اصلی و قیمت ویژه محصولات ووکامرس با ویرایش گروهی قیمت، رابط فارسی، کنترل دسترسی و لاگ تغییرات.
 * Version: 2.11.0
 * Author: Esmaeel Shojaei (TO3E)
 * Author URI: https://to3edev.ir
 * Text Domain: smart-stock-manager
 * Domain Path: /languages
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 *
 * @package   Smart_Stock_Manager
 * @author    Esmaeel Shojaei (TO3E)
 * @copyright Copyright (c) 2026, TO3E — https://to3edev.ir
 * @license   Commercial
 */

if (!defined('ABSPATH')) {
    exit;
}

// اعلام سازگاری با جداول سفارش ووکامرس (HPOS) تا اخطار تداخل ندهد
add_action('before_woocommerce_init', function () {
    if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
    }
});

if (!class_exists('Smart_Stock_Manager')) {

    class Smart_Stock_Manager {

        const VERSION          = '2.11.0';
        const MENU_SLUG        = 'smart-stock-manager';
        const LOGS_SLUG        = 'ssm-change-logs';
        const ACCESS_SLUG      = 'ssm-access-settings';
        const HELP_SLUG        = 'ssm-help';
        const BULK_PRICE_SLUG  = 'ssm-bulk-price';
        const CSV_SLUG         = 'ssm-csv-tools';
        const NONCE_ACTION     = 'ssm_ajax_security_nonce';
        const ACCESS_NONCE     = 'ssm_save_access_settings';
        const SETTINGS_NONCE   = 'ssm_save_stock_settings';
        const CSV_NONCE        = 'ssm_csv_tools';
        const LOGS_NONCE       = 'ssm_logs_tools';
        const OPT_LOW_STOCK    = 'ssm_low_stock_threshold';
        const ZHAKET_URL       = 'https://www.zhaket.com/web/sabalan-stock-manager-plugin';
        const SEARCH_LIMIT     = 10;
        const BULK_BATCH       = 80;
        const BULK_PREVIEW_BATCH = 120;
        const BULK_RATE_LIMIT  = 180;
        const BULK_DB_VERSION  = '1.1.0';
        const BULK_DB_OPTION   = 'ssm_bulk_db_version';
        const BULK_RETENTION_DAYS = 30;
        const RATE_LIMIT_READ  = 90;
        const RATE_LIMIT_WRITE = 40;
        const RATE_WINDOW      = 60;
        const AUDIT_LOG_OPTION = 'ssm_audit_log';
        const AUDIT_LOG_MAX    = 800;
        const LOGS_PER_PAGE    = 40;
        const META_VIEW        = 'ssm_can_view_inventory';
        const META_STOCK       = 'ssm_can_edit_stock';
        const META_PRICE       = 'ssm_can_edit_price';

        public function __construct() {
            add_action('plugins_loaded', array($this, 'init_plugin'));
        }

        public function init_plugin() {
            $this->maybe_install_bulk_tables();

            if (!class_exists('WooCommerce')) {
                add_action('admin_notices', array($this, 'woocommerce_missing_notice'));
                return;
            }

            add_action('admin_menu', array($this, 'register_admin_menu'));
            add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
            add_action('admin_init', array($this, 'handle_access_settings_save'));
            add_action('admin_init', array($this, 'handle_stock_settings_save'));
            add_action('admin_post_ssm_export_csv', array($this, 'handle_export_csv'));
            add_action('admin_post_ssm_import_csv', array($this, 'handle_import_csv'));
            add_action('admin_post_ssm_export_logs', array($this, 'handle_export_logs'));
            add_action('admin_post_ssm_clear_logs', array($this, 'handle_clear_logs'));

            add_action('wp_ajax_ssm_search_products', array($this, 'ajax_search_products'));
            add_action('wp_ajax_ssm_update_product_data', array($this, 'ajax_update_product_data'));
            add_action('wp_ajax_ssm_get_product', array($this, 'ajax_get_product'));
            add_action('wp_ajax_ssm_lookup_by_code', array($this, 'ajax_lookup_by_code'));
            add_action('wp_ajax_ssm_decrease_stock', array($this, 'ajax_decrease_stock'));
            // نام جایگزین — بعضی فایروال‌ها اکشن decrease را بلاک می‌کنند
            add_action('wp_ajax_ssm_stock_bump', array($this, 'ajax_decrease_stock'));
            add_action('wp_ajax_ssm_scan_bump', array($this, 'ajax_scan_and_decrease'));
            add_action('wp_ajax_ssm_bulk_price_preview', array($this, 'ajax_bulk_price_preview'));
            add_action('wp_ajax_ssm_bulk_price_apply', array($this, 'ajax_bulk_price_apply'));
            add_action('wp_ajax_ssm_bulk_price_status', array($this, 'ajax_bulk_price_status'));
            add_action('wp_ajax_ssm_bulk_price_cancel', array($this, 'ajax_bulk_price_cancel'));
            add_action('wp_ajax_ssm_bulk_price_rollback', array($this, 'ajax_bulk_price_rollback'));
            add_action('wp_ajax_ssm_legacy_bulk_recovery', array($this, 'ajax_legacy_bulk_recovery'));
            add_action('ssm_cleanup_bulk_jobs', array($this, 'cleanup_bulk_jobs'));
        }

        public static function activate() {
            self::install_bulk_tables();
            if (!wp_next_scheduled('ssm_cleanup_bulk_jobs')) {
                wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'ssm_cleanup_bulk_jobs');
            }
        }

        public static function deactivate() {
            $timestamp = wp_next_scheduled('ssm_cleanup_bulk_jobs');
            if ($timestamp) {
                wp_unschedule_event($timestamp, 'ssm_cleanup_bulk_jobs');
            }
        }

        private function maybe_install_bulk_tables() {
            if (get_option(self::BULK_DB_OPTION) !== self::BULK_DB_VERSION) {
                self::install_bulk_tables();
            }
            if (!wp_next_scheduled('ssm_cleanup_bulk_jobs')) {
                wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'ssm_cleanup_bulk_jobs');
            }
        }

        private static function get_bulk_table_names() {
            global $wpdb;
            return array(
                'jobs'  => $wpdb->prefix . 'ssm_bulk_jobs',
                'items' => $wpdb->prefix . 'ssm_bulk_job_items',
            );
        }

        public static function install_bulk_tables() {
            global $wpdb;

            if (!function_exists('dbDelta')) {
                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
            }

            $tables = self::get_bulk_table_names();
            $charset_collate = $wpdb->get_charset_collate();

            $jobs_sql = "CREATE TABLE {$tables['jobs']} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                job_key varchar(64) NOT NULL,
                user_id bigint(20) unsigned NOT NULL,
                params longtext NOT NULL,
                params_hash char(32) NOT NULL,
                status varchar(24) NOT NULL DEFAULT 'preparing',
                matched int(10) unsigned NOT NULL DEFAULT 0,
                prepared int(10) unsigned NOT NULL DEFAULT 0,
                actionable int(10) unsigned NOT NULL DEFAULT 0,
                skipped int(10) unsigned NOT NULL DEFAULT 0,
                applied int(10) unsigned NOT NULL DEFAULT 0,
                conflicts int(10) unsigned NOT NULL DEFAULT 0,
                failed int(10) unsigned NOT NULL DEFAULT 0,
                rolled_back int(10) unsigned NOT NULL DEFAULT 0,
                created_at datetime NOT NULL,
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY job_key (job_key),
                KEY user_status (user_id,status),
                KEY status_updated (status,updated_at)
            ) ENGINE=InnoDB {$charset_collate};";

            $items_sql = "CREATE TABLE {$tables['items']} (
                id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
                job_id bigint(20) unsigned NOT NULL,
                product_id bigint(20) unsigned NOT NULL,
                field varchar(16) NOT NULL DEFAULT 'regular',
                old_regular varchar(64) NOT NULL DEFAULT '',
                new_regular varchar(64) NOT NULL DEFAULT '',
                old_sale varchar(64) NOT NULL DEFAULT '',
                new_sale varchar(64) NOT NULL DEFAULT '',
                state varchar(24) NOT NULL DEFAULT 'pending',
                error_message varchar(255) NOT NULL DEFAULT '',
                updated_at datetime NOT NULL,
                PRIMARY KEY  (id),
                UNIQUE KEY job_product (job_id,product_id),
                KEY job_state_id (job_id,state,id),
                KEY product_state (product_id,state)
            ) ENGINE=InnoDB {$charset_collate};";

            dbDelta($jobs_sql);
            dbDelta($items_sql);
            $wpdb->query("ALTER TABLE {$tables['jobs']} ENGINE=InnoDB");
            $wpdb->query("ALTER TABLE {$tables['items']} ENGINE=InnoDB");
            $jobs_exists = $wpdb->get_var(
                $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($tables['jobs']))
            );
            $items_exists = $wpdb->get_var(
                $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($tables['items']))
            );
            $jobs_status = $wpdb->get_row(
                $wpdb->prepare('SHOW TABLE STATUS LIKE %s', $wpdb->esc_like($tables['jobs'])),
                ARRAY_A
            );
            $items_status = $wpdb->get_row(
                $wpdb->prepare('SHOW TABLE STATUS LIKE %s', $wpdb->esc_like($tables['items'])),
                ARRAY_A
            );
            $job_columns = $wpdb->get_col("SHOW COLUMNS FROM {$tables['jobs']}", 0);
            $item_columns = $wpdb->get_col("SHOW COLUMNS FROM {$tables['items']}", 0);
            $required_job_columns = array('id', 'job_key', 'user_id', 'params', 'params_hash', 'status', 'matched', 'prepared', 'actionable', 'skipped', 'applied', 'conflicts', 'failed', 'rolled_back', 'created_at', 'updated_at');
            $required_item_columns = array('id', 'job_id', 'product_id', 'field', 'old_regular', 'new_regular', 'old_sale', 'new_sale', 'state', 'error_message', 'updated_at');
            $schema_ok = empty(array_diff($required_job_columns, (array) $job_columns))
                && empty(array_diff($required_item_columns, (array) $item_columns));
            $engines_ok = !empty($jobs_status['Engine']) && !empty($items_status['Engine'])
                && strtolower($jobs_status['Engine']) === 'innodb'
                && strtolower($items_status['Engine']) === 'innodb';
            delete_transient('ssm_bulk_storage_ok');
            if ($jobs_exists === $tables['jobs'] && $items_exists === $tables['items'] && $schema_ok && $engines_ok) {
                update_option(self::BULK_DB_OPTION, self::BULK_DB_VERSION, false);
                return true;
            }
            return false;
        }

        public function get_low_stock_threshold() {
            $n = (int) get_option(self::OPT_LOW_STOCK, 8);
            if ($n < 1) {
                $n = 1;
            }
            if ($n > 9999) {
                $n = 9999;
            }
            return $n;
        }

        private function is_plugin_admin($user_id = 0) {
            $user_id = $user_id ? absint($user_id) : get_current_user_id();
            if (!$user_id) {
                return false;
            }
            // مدیر کل وردپرس
            if (user_can($user_id, 'manage_options')) {
                return true;
            }
            // مدیر فروشگاه ووکامرس
            if (user_can($user_id, 'manage_woocommerce')) {
                return true;
            }
            // نقش administrator (حتی اگر capability موقتاً فیلتر شده باشد)
            $user = get_userdata($user_id);
            if ($user && in_array('administrator', (array) $user->roles, true)) {
                return true;
            }
            return false;
        }

        private function user_meta_flag($user_id, $meta_key) {
            return (bool) get_user_meta(absint($user_id), $meta_key, true);
        }

        public function user_can_view_inventory($user_id = 0) {
            $user_id = $user_id ? absint($user_id) : get_current_user_id();
            if (!$user_id) {
                return false;
            }
            if ($this->is_plugin_admin($user_id)) {
                return true;
            }
            // بعد از گرفتن دسترسی، اگر نقش به مشتری برگشت دیگر راه نده
            if (!$this->is_backend_staff_user($user_id)) {
                return false;
            }
            return $this->user_meta_flag($user_id, self::META_VIEW)
                || $this->user_meta_flag($user_id, self::META_STOCK)
                || $this->user_meta_flag($user_id, self::META_PRICE);
        }

        public function user_can_edit_stock($user_id = 0) {
            $user_id = $user_id ? absint($user_id) : get_current_user_id();
            if (!$user_id || $this->is_plugin_admin($user_id)) {
                return (bool) $user_id && $this->is_plugin_admin($user_id);
            }
            return $this->is_backend_staff_user($user_id) && $this->user_meta_flag($user_id, self::META_STOCK);
        }

        public function user_can_edit_price($user_id = 0) {
            $user_id = $user_id ? absint($user_id) : get_current_user_id();
            if (!$user_id || $this->is_plugin_admin($user_id)) {
                return (bool) $user_id && $this->is_plugin_admin($user_id);
            }
            return $this->is_backend_staff_user($user_id) && $this->user_meta_flag($user_id, self::META_PRICE);
        }

        private function get_current_perms() {
            return array(
                'canView'      => $this->user_can_view_inventory(),
                'canEditStock' => $this->user_can_edit_stock(),
                'canEditPrice' => $this->user_can_edit_price(),
                'isAdmin'      => $this->is_plugin_admin(),
            );
        }

        public function woocommerce_missing_notice() {
            if (current_user_can('activate_plugins')) {
                echo '<div class="error notice is-dismissible"><p><strong>افزونه مدیریت هوشمند انبار سبلان:</strong> ابتدا ووکامرس را نصب و فعال کنید.</p></div>';
            }
        }

        public function enqueue_admin_assets($hook) {
            $page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
            if (!in_array($page, array(self::MENU_SLUG, self::LOGS_SLUG, self::ACCESS_SLUG, self::HELP_SLUG, self::BULK_PRICE_SLUG, self::CSV_SLUG), true)) {
                return;
            }
            if (!$this->user_can_view_inventory() && !$this->is_plugin_admin()) {
                return;
            }

            wp_enqueue_style('ssm-admin-style', plugin_dir_url(__FILE__) . 'assets/css/admin-style.css', array(), self::VERSION);

            if ($page === self::MENU_SLUG) {
                wp_enqueue_script('ssm-admin-script', plugin_dir_url(__FILE__) . 'assets/js/admin-script.js', array('jquery'), self::VERSION, true);
                wp_localize_script('ssm-admin-script', 'ssmAdmin', array(
                    'ajaxUrl' => admin_url('admin-ajax.php'),
                    'nonce' => wp_create_nonce(self::NONCE_ACTION),
                    'searchMinChars' => 3,
                    'searchLimit' => self::SEARCH_LIMIT,
                    'lowStockThreshold' => $this->get_low_stock_threshold(),
                    'perms' => $this->get_current_perms(),
                    'i18n' => array(
                        'rateLimited'   => 'تعداد درخواست‌ها زیاد است. لطفاً چند لحظه صبر کنید و دوباره تلاش کنید.',
                        'forbidden'     => 'شما دسترسی لازم برای این عملیات را ندارید.',
                        'generic'       => 'خطایی رخ داد. لطفاً دوباره تلاش کنید.',
                        'noStockPerm'   => 'شما دسترسی تغییر موجودی ندارید.',
                        'noPricePerm'   => 'شما دسترسی تغییر قیمت ندارید.',
                        'noEditPerm'    => 'شما دسترسی تغییر موجودی یا قیمت ندارید.',
                        'saved'         => 'با موفقیت ذخیره شد',
                        'save'          => 'ذخیره تغییرات',
                        'decreasing'    => 'در حال کاهش...',
                        'decrease'      => 'کم کن (−۱)',
                        'saveFailed'    => 'ذخیره انجام نشد. دوباره تلاش کنید.',
                        'decreaseFailed'=> 'کاهش موجودی انجام نشد. دوباره تلاش کنید.',
                        'autoBumpOn'    => 'کاهش خودکار پس از اسکن فعال است',
                        'lowStockWarn'  => 'در نتایج، موجودی کم یا ناموجود وجود دارد.',
                    ),
                ));
            }

            if ($page === self::BULK_PRICE_SLUG && ($this->is_plugin_admin() || $this->user_can_edit_price())) {
                $store_unit = $this->get_store_money_unit();
                wp_enqueue_script('ssm-bulk-price', plugin_dir_url(__FILE__) . 'assets/js/bulk-price.js', array('jquery'), self::VERSION, true);
                wp_localize_script('ssm-bulk-price', 'ssmBulkPrice', array(
                    'ajaxUrl'   => admin_url('admin-ajax.php'),
                    'nonce'     => wp_create_nonce(self::NONCE_ACTION),
                    'batchSize' => self::BULK_BATCH,
                    'storeUnit' => $store_unit,
                    'currency'  => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '',
                    'i18n'      => array(
                        'needPreview' => 'اول پیش‌نمایش بگیرید، بعد اجرا کنید.',
                        'confirm'     => 'آیا از اعمال تغییر گروهی قیمت مطمئن هستید؟ در صورت قطع ارتباط، عملیات از همان نقطه ادامه پیدا می‌کند.',
                        'running'     => 'در حال اعمال...',
                        'done'        => 'اتمام عملیات',
                        'previewing'  => 'در حال ساخت پیش‌نمایش امن...',
                        'resuming'    => 'عملیات نیمه‌کاره پیدا شد؛ ادامه از آخرین وضعیت ذخیره‌شده.',
                        'cancelConfirm' => 'عملیات نیمه‌کاره لغو شود؟ محصولاتی که قبلاً تغییر کرده‌اند دست‌نخورده می‌مانند و امکان بازگردانی دارند.',
                        'rollbackConfirm' => 'قیمت‌های این عملیات به مقادیر قبل بازگردند؟ محصولاتی که بعداً دستی تغییر کرده‌اند بازنویسی نمی‌شوند.',
                        'noChange'    => 'با این تنظیمات هیچ تغییری اعمال نمی‌شود.',
                        'generic'     => 'خطایی رخ داد. دوباره تلاش کنید.',
                    ),
                ));
            }
        }

        public function register_admin_menu() {
            if (!$this->user_can_view_inventory() && !$this->is_plugin_admin()) {
                return;
            }

            add_menu_page('انبار', 'انبار', 'read', self::MENU_SLUG, array($this, 'render_inventory_page'), 'dashicons-database-view', 56);
            add_submenu_page(self::MENU_SLUG, 'انبار', 'انبار', 'read', self::MENU_SLUG, array($this, 'render_inventory_page'));

            if ($this->is_plugin_admin() || $this->user_can_edit_price()) {
                add_submenu_page(self::MENU_SLUG, 'ویرایش گروهی قیمت', 'ویرایش گروهی قیمت', 'read', self::BULK_PRICE_SLUG, array($this, 'render_bulk_price_page'));
            }

            if ($this->user_can_view_inventory() || $this->is_plugin_admin()) {
                add_submenu_page(self::MENU_SLUG, 'خروجی و ورود CSV', 'خروجی CSV', 'read', self::CSV_SLUG, array($this, 'render_csv_page'));
                add_submenu_page(self::MENU_SLUG, 'آموزش کار با افزونه', 'آموزش', 'read', self::HELP_SLUG, array($this, 'render_help_page'));
                add_submenu_page(self::MENU_SLUG, 'لاگ تغییرات', 'لاگ تغییرات', 'read', self::LOGS_SLUG, array($this, 'render_logs_page'));
            }
            if ($this->is_plugin_admin()) {
                add_submenu_page(self::MENU_SLUG, 'تنظیمات دسترسی', 'تنظیمات دسترسی', 'manage_options', self::ACCESS_SLUG, array($this, 'render_access_page'));
            }
        }

        private function render_plugin_tabs($active) {
            $tabs = array(
                self::MENU_SLUG => 'انبار',
            );
            if ($this->is_plugin_admin() || $this->user_can_edit_price()) {
                $tabs[self::BULK_PRICE_SLUG] = 'ویرایش گروهی قیمت';
            }
            if ($this->user_can_view_inventory() || $this->is_plugin_admin()) {
                $tabs[self::CSV_SLUG] = 'خروجی CSV';
            }
            $tabs[self::HELP_SLUG] = 'آموزش';
            $tabs[self::LOGS_SLUG] = 'لاگ تغییرات';
            if ($this->is_plugin_admin()) {
                $tabs[self::ACCESS_SLUG] = 'تنظیمات دسترسی';
            }
            ?>
            <nav class="ssm-plugin-tabs" aria-label="بخش‌های افزونه">
                <?php foreach ($tabs as $slug => $label) :
                    $url = admin_url('admin.php?page=' . $slug);
                    $is_active = ($active === $slug);
                    ?>
                    <a class="ssm-plugin-tab<?php echo $is_active ? ' is-active' : ''; ?>" href="<?php echo esc_url($url); ?>"<?php echo $is_active ? ' aria-current="page"' : ''; ?>><?php echo esc_html($label); ?></a>
                <?php endforeach; ?>
            </nav>
            <?php
        }

        private function render_page_top($active_tab, $help_text = '') {
            echo '<h1 class="ssm-screen-title">' . esc_html('افزونه وردپرس مدیریت هوشمند انبار سبلان') . '</h1>';
            echo '<hr class="wp-header-end">';
            $this->render_brand_header($help_text);
            $this->render_plugin_tabs($active_tab);
        }

        // راهنمای جمع‌شونده (آکاردئون) — پیش‌فرض بسته
        private function render_guide_box($title, $items, $variant = 'guide') {
            $class = 'ssm-guide-box ssm-guide-accordion';
            if ($variant === 'warning') {
                $class .= ' ssm-guide-warning';
            } elseif ($variant === 'success') {
                $class .= ' ssm-guide-success';
            }
            ?>
            <details class="<?php echo esc_attr($class); ?>">
                <summary class="ssm-guide-title"><?php echo esc_html($title); ?> <span class="ssm-guide-toggle-hint">کلیک برای باز/بسته</span></summary>
                <?php if (!empty($items)) : ?>
                    <ul class="ssm-guide-list">
                        <?php foreach ((array) $items as $item) : ?>
                            <li><?php echo esc_html($item); ?></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </details>
            <?php
        }

        private function render_brand_header($help_text = '') {
            ?>
            <header class="ssm-brand-header">
                <div class="ssm-brand-mark" aria-hidden="true">
                    <svg viewBox="0 0 48 48" width="32" height="32" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <rect width="48" height="48" rx="12" fill="url(#ssmGrad)"/>
                        <path d="M14 18h20v3H14v-3zm0 7h20v3H14v-3zm0 7h12v3H14v-3z" fill="#fff" opacity=".95"/>
                        <circle cx="34" cy="34" r="7" fill="#22c55e"/>
                        <path d="M31 34l2 2 4-4" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <defs><linearGradient id="ssmGrad" x1="0" y1="0" x2="48" y2="48"><stop stop-color="#4f46e5"/><stop offset="1" stop-color="#7c3aed"/></linearGradient></defs>
                    </svg>
                </div>
                <div class="ssm-brand-text">
                    <p class="ssm-brand-title" role="heading" aria-level="1">مدیریت هوشمند انبار سبلان <span class="ssm-ver-badge">v<?php echo esc_html(self::VERSION); ?></span></p>
                    <a class="ssm-zhaket-link" href="<?php echo esc_url(self::ZHAKET_URL); ?>" target="_blank" rel="noopener noreferrer">مارکت ژاکت</a>
                </div>
                <?php if ($help_text !== '') : ?>
                <span class="ssm-help" tabindex="0" role="button" aria-label="راهنما"><span class="ssm-help-icon">؟</span><span class="ssm-help-tip"><?php echo esc_html($help_text); ?></span></span>
                <?php endif; ?>
            </header>
            <?php
        }

        public function render_inventory_page() {
            if (!$this->user_can_view_inventory()) {
                wp_die(esc_html('شما دسترسی مشاهده انبار ندارید.'), esc_html('دسترسی غیرمجاز'), array('response' => 403));
            }
            $perms = $this->get_current_perms();
            $threshold = $this->get_low_stock_threshold();
            ?>
            <div class="wrap ssm-wrap" dir="rtl">
                <?php wp_nonce_field(self::NONCE_ACTION, 'ssm_page_nonce', false); ?>
                <div class="ssm-container">
                    <?php $this->render_page_top(self::MENU_SLUG); ?>
                    <p class="ssm-page-subtitle">محصولات ساده و متغیر را بدون ورود به صفحه هر محصول مدیریت کنید.</p>

                    <div class="ssm-scanner-bar" role="group" aria-label="هدف بارکدخوان">
                        <span class="ssm-scanner-label">هدف بارکدخوان:</span>
                        <label class="ssm-scanner-option">
                            <input type="radio" name="ssm_scan_target" value="top" checked>
                            جستجوی بالا (ویرایش موجودی/قیمت)
                        </label>
                        <label class="ssm-scanner-option">
                            <input type="radio" name="ssm_scan_target" value="bottom">
                            کاهش سریع پایین (−۱)
                        </label>
                        <?php if ($perms['canEditStock']) : ?>
                        <label class="ssm-scanner-option ssm-auto-bump-option" for="ssm-auto-bump">
                            <input type="checkbox" id="ssm-auto-bump" value="1">
                            پس از اسکن پایین، خودکار −۱ کن
                        </label>
                        <?php endif; ?>
                        <span class="ssm-scanner-hint">بعد از انتخاب، دستگاه را بزنید؛ نیازی به تایپ دستی نیست.</span>
                    </div>

                    <?php if ($this->is_plugin_admin()) : ?>
                    <section class="ssm-panel ssm-panel-threshold">
                        <div class="ssm-panel-head"><span class="ssm-panel-badge">!</span><div><h2>آستانه موجودی (رنگ‌بندی)</h2><p>عدد «فول» را تنظیم کنید؛ رنگ‌ها هر ۲۵٪ نسبت به همین آستانه عوض می‌شوند.</p></div></div>
                        <form method="post" action="" class="ssm-stock-settings-form">
                            <?php wp_nonce_field(self::SETTINGS_NONCE); ?>
                            <?php wp_referer_field(); ?>
                            <div class="ssm-stock-settings-row">
                                <label for="ssm-low-stock-threshold-inv">آستانه فول (عدد موجودی مرجع):</label>
                                <input type="number" min="1" max="9999" id="ssm-low-stock-threshold-inv" name="ssm_low_stock_threshold" value="<?php echo esc_attr((string) $threshold); ?>">
                                <button type="submit" name="ssm_stock_settings_save" value="1" class="button button-primary">ذخیره آستانه</button>
                            </div>
                        </form>
                        <div class="ssm-stock-legend" aria-label="راهنمای رنگ موجودی">
                            <span class="ssm-legend-item stock-ok">سبز ≥ آستانه</span>
                            <span class="ssm-legend-item stock-warn">زرد ≥ ۷۵٪</span>
                            <span class="ssm-legend-item stock-mid">نارنجی ≥ ۵۰٪</span>
                            <span class="ssm-legend-item stock-danger">قرمز &lt; ۵۰٪</span>
                        </div>
                    </section>
                    <?php else : ?>
                    <div class="ssm-stock-legend ssm-stock-legend-inline" aria-label="راهنمای رنگ موجودی">
                        <span class="ssm-muted">آستانه فول: <?php echo esc_html((string) $threshold); ?></span>
                        <span class="ssm-legend-item stock-ok">سبز</span>
                        <span class="ssm-legend-item stock-warn">زرد</span>
                        <span class="ssm-legend-item stock-mid">نارنجی</span>
                        <span class="ssm-legend-item stock-danger">قرمز</span>
                    </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['stock_updated']) && sanitize_key(wp_unslash($_GET['stock_updated'])) === '1') : ?>
                        <div class="ssm-notice ssm-notice-info">آستانه موجودی ذخیره شد. رنگ‌بندی بر اساس آستانه جدید اعمال می‌شود.</div>
                    <?php endif; ?>

                    <?php if (!$perms['canEditStock'] && !$perms['canEditPrice']) : ?>
                        <div class="ssm-notice ssm-notice-warning">شما فقط دسترسی مشاهده انبار دارید. برای تغییر موجودی یا قیمت با مدیر سیستم هماهنگ کنید.</div>
                    <?php elseif ($perms['canEditStock'] && !$perms['canEditPrice']) : ?>
                        <div class="ssm-notice ssm-notice-info">شما دسترسی تغییر موجودی دارید، اما دسترسی تغییر قیمت ندارید.</div>
                    <?php elseif (!$perms['canEditStock'] && $perms['canEditPrice']) : ?>
                        <div class="ssm-notice ssm-notice-info">شما دسترسی تغییر قیمت دارید، اما دسترسی تغییر موجودی ندارید.</div>
                    <?php endif; ?>

                    <section class="ssm-panel ssm-panel-edit">
                        <div class="ssm-panel-head"><span class="ssm-panel-badge">۱</span><div><h2>ویرایش موجودی و قیمت</h2><p>نام، SKU یا بارکد را جستجو کنید — قیمت اصلی و قیمت ویژه قابل ویرایش‌اند.</p></div></div>
                        <div class="ssm-search-box">
                            <div class="ssm-search-input-wrapper">
                                <input type="text" id="ssm-search-input" placeholder="تیتر محصول، SKU یا بارکد..." autocomplete="off">
                                <ul id="ssm-live-results-list" style="display:none;"></ul>
                            </div>
                            <button type="button" id="ssm-search-button" class="button button-primary">جستجو</button>
                        </div>
                        <div id="ssm-low-stock-banner" class="ssm-notice ssm-notice-warning" hidden></div>
                        <div class="ssm-result-toolbar" id="ssm-result-toolbar" hidden>
                            <div class="ssm-quick-filters" role="group" aria-label="فیلتر نتایج">
                                <button type="button" class="ssm-filter-chip is-active" data-ssm-filter="all">همه</button>
                                <button type="button" class="ssm-filter-chip" data-ssm-filter="out">ناموجود</button>
                                <button type="button" class="ssm-filter-chip" data-ssm-filter="low">کم‌موجود</button>
                                <button type="button" class="ssm-filter-chip" data-ssm-filter="sale">دارای تخفیف</button>
                                <button type="button" class="ssm-filter-chip" data-ssm-filter="variable">فقط متغیر</button>
                                <button type="button" class="ssm-filter-chip" data-ssm-filter="dirty">تغییرکرده</button>
                            </div>
                        </div>
                        <div id="ssm-results" class="ssm-results"></div>
                    </section>
                    <div id="ssm-bulk-bar" class="ssm-bulk-bar" hidden>
                        <span class="ssm-bulk-count" id="ssm-bulk-count">۰ تغییر ذخیره‌نشده</span>
                        <button type="button" class="button button-primary" id="ssm-save-all">ذخیره همه</button>
                    </div>
                    <section class="ssm-panel ssm-panel-quick">
                        <div class="ssm-panel-head"><span class="ssm-panel-badge ssm-panel-badge-alt">۲</span><div><h2>کاهش سریع موجودی با کد</h2><p>با اسکنر یا کد، پیش‌نمایش بگیرید<?php echo $perms['canEditStock'] ? ' و یک واحد کم کنید' : ' (کاهش فقط با دسترسی تغییر موجودی)'; ?>.</p></div></div>
                        <div class="ssm-code-search">
                            <input type="text" id="ssm-code-input" placeholder="SKU یا بارکد را اسکن کنید..." autocomplete="off">
                            <button type="button" id="ssm-code-preview-btn" class="button button-primary">پیش‌نمایش</button>
                        </div>
                        <?php if (!$perms['canEditStock']) : ?>
                            <div class="ssm-notice ssm-notice-warning" style="margin-top:12px;">شما دسترسی تغییر موجودی ندارید؛ فقط پیش‌نمایش برای شما نمایش داده می‌شود.</div>
                        <?php endif; ?>
                        <div id="ssm-code-preview" class="ssm-code-preview" aria-live="polite"></div>
                    </section>
                </div>
            </div>
            <?php
        }

        public function render_csv_page() {
            if (!$this->user_can_view_inventory() && !$this->is_plugin_admin()) {
                wp_die(esc_html('شما دسترسی مشاهده این بخش را ندارید.'), esc_html('دسترسی غیرمجاز'), array('response' => 403));
            }
            $perms = $this->get_current_perms();
            $can_import = $this->is_plugin_admin() || $perms['canEditStock'] || $perms['canEditPrice'];
            ?>
            <div class="wrap ssm-wrap" dir="rtl">
                <div class="ssm-container">
                    <?php $this->render_page_top(self::CSV_SLUG); ?>
                    <h2 class="ssm-section-title">خروجی و ورود CSV</h2>
                    <p class="ssm-page-subtitle">دانلود یا وارد کردن موجودی و قیمت — سازگار با Excel (UTF-8).</p>

                    <?php if (isset($_GET['ssm_csv_ok'])) : ?>
                        <div class="ssm-notice ssm-notice-info">
                            وارد کردن CSV انجام شد —
                            بروزرسانی: <?php echo esc_html((string) absint($_GET['ssm_csv_ok'])); ?>،
                            بدون تغییر: <?php echo esc_html((string) absint(isset($_GET['ssm_csv_skip']) ? $_GET['ssm_csv_skip'] : 0)); ?>،
                            ناموفق: <?php echo esc_html((string) absint(isset($_GET['ssm_csv_fail']) ? $_GET['ssm_csv_fail'] : 0)); ?>.
                        </div>
                    <?php elseif (isset($_GET['ssm_csv_err'])) :
                        $cerr = sanitize_key(wp_unslash($_GET['ssm_csv_err']));
                        $cmsg = ($cerr === 'size') ? 'حجم فایل بیش از حد مجاز است (حداکثر ۵ مگابایت).' : 'فایل CSV انتخاب نشده است.';
                        ?>
                        <div class="ssm-notice ssm-notice-warning"><?php echo esc_html($cmsg); ?></div>
                    <?php endif; ?>

                    <?php
                    $this->render_guide_box(
                        'نکات CSV',
                        array(
                            'ستون‌ها: product_id, sku, name, type, parent_id, stock_qty, regular_price, sale_price',
                            'برای بروزرسانی، product_id یا SKU کافی است.',
                            'فیلد خالی قیمت ویژه = برداشتن تخفیف.',
                            'قبل از ورود گروهی، یک خروجی پشتیبان بگیرید.',
                        )
                    );
                    ?>

                    <section class="ssm-panel ssm-panel-csv">
                        <div class="ssm-panel-head"><span class="ssm-panel-badge">CSV</span><div><h2>ابزار فایل</h2><p>خروجی برای اکسل و ورود برای بروزرسانی دسته‌ای.</p></div></div>
                        <div class="ssm-csv-actions">
                            <a class="button button-secondary button-hero" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=ssm_export_csv'), self::CSV_NONCE)); ?>">دانلود CSV موجودی و قیمت</a>
                            <?php if ($can_import) : ?>
                            <form class="ssm-csv-import" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="ssm_import_csv">
                                <?php wp_nonce_field(self::CSV_NONCE); ?>
                                <input type="file" name="ssm_csv_file" accept=".csv,text/csv" required>
                                <button type="submit" class="button button-primary button-hero">وارد کردن CSV</button>
                            </form>
                            <?php else : ?>
                            <span class="ssm-muted">برای وارد کردن فایل به دسترسی تغییر موجودی یا قیمت نیاز دارید.</span>
                            <?php endif; ?>
                        </div>
                    </section>
                </div>
            </div>
            <?php
        }

        public function render_bulk_price_page() {
            if (!$this->is_plugin_admin() && !$this->user_can_edit_price()) {
                wp_die(esc_html('شما دسترسی تغییر قیمت ندارید.'), esc_html('دسترسی غیرمجاز'), array('response' => 403));
            }

            $store_unit = $this->get_store_money_unit();
            $currency = function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '';
            $store_label = ($store_unit === 'toman') ? 'تومان' : 'ریال';
            $categories = get_terms(array(
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
                'number'     => 300,
            ));
            if (is_wp_error($categories)) {
                $categories = array();
            }
            ?>
            <div class="wrap ssm-wrap" dir="rtl">
                <div class="ssm-container">
                    <?php $this->render_page_top(self::BULK_PRICE_SLUG); ?>
                    <h2 class="ssm-section-title">ویرایش گروهی قیمت</h2>
                    <p class="ssm-page-subtitle">افزایش یا کاهش قیمت اصلی / ویژه روی چند محصول — با پیش‌نمایش قبل از اجرا.</p>

                    <?php
                    $this->render_guide_box(
                        'نکات مهم قبل از اجرا',
                        array(
                            'حتماً اول «پیش‌نمایش» بگیرید و نمونه‌ها را چک کنید.',
                            'واحد ذخیره‌شده فروشگاه تشخیص داده شده: ' . $store_label . ($currency ? ' (ارز ووکامرس: ' . $currency . ')' : '') . '.',
                            'برای مبلغ ثابت، واحد ورود خودتان (تومان یا ریال) را مشخص کنید تا تبدیل خودکار انجام شود.',
                            'پیشرفت عملیات روی سرور ذخیره می‌شود؛ قطع اینترنت یا بستن صفحه باعث شروع دوباره از ابتدا نمی‌شود.',
                            'قیمت مقصد هر محصول فقط یک‌بار ساخته می‌شود؛ تکرار درخواست درصد را دوباره روی همان محصول اعمال نمی‌کند.',
                            'آخرین عملیات از همین صفحه قابل بازگردانی است؛ با این حال قبل از تغییر بزرگ، خروجی CSV بگیرید.',
                        ),
                        'warning'
                    );
                    ?>

                    <div class="ssm-notice ssm-notice-info">
                        واحد قیمت ذخیره‌شده در فروشگاه: <strong><?php echo esc_html($store_label); ?></strong>
                        <?php if ($currency) : ?>
                            <span class="ssm-muted">— ارز ووکامرس: <?php echo esc_html($currency); ?></span>
                        <?php endif; ?>
                    </div>

                    <section class="ssm-panel ssm-bulk-price-panel">
                        <div class="ssm-inner-tabs" role="tablist">
                            <button type="button" class="ssm-inner-tab is-active" data-ssm-price-field="regular" role="tab" aria-selected="true">قیمت اصلی</button>
                            <button type="button" class="ssm-inner-tab" data-ssm-price-field="sale" role="tab" aria-selected="false">قیمت ویژه</button>
                        </div>
                        <input type="hidden" id="ssm-bp-field" value="regular">

                        <div class="ssm-bp-grid">
                            <div class="ssm-bp-field">
                                <label for="ssm-bp-direction">نوع تغییر</label>
                                <select id="ssm-bp-direction">
                                    <option value="increase">افزایش</option>
                                    <option value="decrease">کاهش</option>
                                </select>
                            </div>
                            <div class="ssm-bp-field">
                                <label for="ssm-bp-mode">روش محاسبه</label>
                                <select id="ssm-bp-mode">
                                    <option value="percent">درصدی (%)</option>
                                    <option value="fixed">مبلغ ثابت</option>
                                </select>
                            </div>
                            <div class="ssm-bp-field">
                                <label for="ssm-bp-amount">مقدار</label>
                                <input type="number" id="ssm-bp-amount" min="0" step="any" placeholder="مثلاً ۱۰ یا ۱۰۰۰۰۰">
                            </div>
                            <div class="ssm-bp-field" id="ssm-bp-unit-wrap">
                                <label for="ssm-bp-unit">واحد مبلغ ورودی</label>
                                <select id="ssm-bp-unit">
                                    <option value="toman" <?php selected($store_unit, 'toman'); ?>>تومان</option>
                                    <option value="rial" <?php selected($store_unit, 'rial'); ?>>ریال</option>
                                </select>
                                <p class="ssm-muted ssm-bp-hint">فقط برای مبلغ ثابت؛ درصد نیازی به واحد ندارد.</p>
                            </div>
                            <div class="ssm-bp-field">
                                <label for="ssm-bp-scope">محدوده محصولات</label>
                                <select id="ssm-bp-scope">
                                    <option value="all">همه محصولات (ساده + متغیرها)</option>
                                    <option value="simple">فقط ساده</option>
                                    <option value="variation">فقط متغیرها</option>
                                    <option value="on_sale">فقط دارای قیمت ویژه</option>
                                </select>
                            </div>
                            <div class="ssm-bp-field">
                                <label for="ssm-bp-category">دسته‌بندی (اختیاری)</label>
                                <select id="ssm-bp-category">
                                    <option value="0">همه محصولات</option>
                                    <?php foreach ($categories as $cat) : ?>
                                        <option value="<?php echo esc_attr((string) $cat->term_id); ?>"><?php echo esc_html($cat->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="ssm-bp-field ssm-bp-sale-options" id="ssm-bp-sale-options" hidden>
                                <label for="ssm-bp-empty-sale">اگر قیمت ویژه خالی باشد</label>
                                <select id="ssm-bp-empty-sale">
                                    <option value="skip">رد شود (دست نزن)</option>
                                    <option value="from_regular">از روی قیمت اصلی ساخته شود</option>
                                </select>
                            </div>
                        </div>

                        <div class="ssm-bp-actions">
                            <button type="button" class="button button-secondary button-hero" id="ssm-bp-preview">پیش‌نمایش تغییرات</button>
                            <button type="button" class="button button-primary button-hero" id="ssm-bp-apply" disabled>اعمال روی محصولات</button>
                            <button type="button" class="button button-secondary" id="ssm-bp-cancel" hidden>لغو عملیات نیمه‌کاره</button>
                            <button type="button" class="button" id="ssm-bp-rollback" hidden>بازگردانی آخرین عملیات</button>
                        </div>
                        <div id="ssm-bp-status" class="ssm-bp-status" aria-live="polite"></div>
                        <div id="ssm-bp-preview-box" class="ssm-bp-preview-box" hidden></div>
                    </section>

                    <section class="ssm-panel ssm-legacy-recovery-panel">
                        <div class="ssm-panel-head">
                            <span class="ssm-panel-badge">↶</span>
                            <div>
                                <h2>بازیابی اضطراری تغییرات نسخه قبلی</h2>
                                <p>تغییرات گروهی ثبت‌شده در لاگ داخلی یا لاگر ووکامرس را پیدا می‌کند و یک عملیات بازگردانی امن می‌سازد.</p>
                            </div>
                        </div>
                        <div class="ssm-notice ssm-notice-warning">
                            قبل از بازیابی از دیتابیس بکاپ بگیرید. فقط محصولی بازگردانده می‌شود که قیمت فعلی آن هنوز با آخرین مقدار ثبت‌شده در لاگ یکسان باشد؛ تغییرات دستی جدید بازنویسی نمی‌شوند.
                        </div>
                        <div class="ssm-bp-grid">
                            <div class="ssm-bp-field">
                                <label for="ssm-recovery-date">تاریخ عملیات</label>
                                <input type="date" id="ssm-recovery-date" value="<?php echo esc_attr(current_time('Y-m-d')); ?>">
                            </div>
                            <div class="ssm-bp-field">
                                <label for="ssm-recovery-from">از ساعت</label>
                                <input type="time" id="ssm-recovery-from" value="00:00">
                            </div>
                            <div class="ssm-bp-field">
                                <label for="ssm-recovery-to">تا ساعت</label>
                                <input type="time" id="ssm-recovery-to" value="23:59">
                            </div>
                            <div class="ssm-bp-field">
                                <label for="ssm-recovery-field">نوع قیمت تغییرکرده</label>
                                <select id="ssm-recovery-field">
                                    <option value="regular">قیمت اصلی</option>
                                    <option value="sale">قیمت ویژه</option>
                                </select>
                            </div>
                        </div>
                        <div class="ssm-bp-actions">
                            <button type="button" class="button button-secondary button-hero" id="ssm-recovery-scan">پیدا کردن تغییرات و ساخت پیش‌نمایش بازیابی</button>
                        </div>
                        <div id="ssm-recovery-status" class="ssm-bp-status" aria-live="polite"></div>
                    </section>
                </div>
            </div>
            <?php
        }

        public function render_help_page() {
            if (!$this->user_can_view_inventory() && !$this->is_plugin_admin()) {
                wp_die(esc_html('شما دسترسی مشاهده آموزش ندارید.'), esc_html('دسترسی غیرمجاز'), array('response' => 403));
            }
            ?>
            <div class="wrap ssm-wrap" dir="rtl"><div class="ssm-container">
                <?php $this->render_page_top(self::HELP_SLUG); ?>
                <h2 class="ssm-section-title">آموزش کار با افزونه</h2>
                <p class="ssm-page-subtitle">راهنمای کلی مدیریت هوشمند انبار سبلان — در همه بخش‌های افزونه قابل استفاده است.</p>

                <div class="ssm-help-page">
                    <section class="ssm-help-block">
                        <h3>شروع سریع از فروشگاه تا انبار</h3>
                        <ol class="ssm-help-steps">
                            <li>به <strong>فروشگاه</strong> (سایت یا پیشخوان محصولات ووکامرس) بروید و <strong>تیتر محصول</strong> یا <strong>کد SKU</strong> (و در صورت وجود بارکد) را ببینید و یادداشت کنید.</li>
                            <li>از منوی <strong>انبار</strong> وارد صفحه انبارداری افزونه شوید.</li>
                            <li>همان تیتر یا SKU را در کادر جستجو وارد کنید (یا با بارکدخوان اسکن کنید).</li>
                            <li>موجودی، قیمت اصلی یا قیمت ویژه را ویرایش کنید. ردیف‌های تغییرکرده زرد می‌شوند؛ می‌توانید تکی یا با «ذخیره همه» ذخیره کنید.</li>
                        </ol>
                    </section>

                    <section class="ssm-help-block">
                        <h3>صفحه انبار — دو باکس اصلی</h3>
                        <ul class="ssm-help-list">
                            <li><strong>باکس ۱ (بالا):</strong> جستجو با نام، SKU یا بارکد؛ ویرایش موجودی، قیمت اصلی و قیمت ویژه.</li>
                            <li><strong>باکس ۲ (پایین):</strong> پیش‌نمایش سریع با اسکن کد و کم کردن یک واحد (−۱) بدون ورود به صفحه محصول.</li>
                            <li><strong>هدف بارکدخوان:</strong> اگر «جستجوی بالا» را انتخاب کنید اسکن در باکس ویرایش می‌افتد؛ اگر «کاهش سریع پایین» را انتخاب کنید اسکن در باکس −۱ می‌افتد.</li>
                            <li><strong>رنگ موجودی:</strong> نسبت به آستانه فول (قابل تنظیم در صفحه انبار یا تنظیمات دسترسی) — سبز ≥ آستانه، زرد ≥۷۵٪، نارنجی ≥۵۰٪، قرمز کمتر از ۵۰٪.</li>
                            <li><strong>فایل CSV:</strong> از تب «خروجی CSV» — خروجی و ورود موجودی/قیمت سازگار با Excel.</li>
                            <li><strong>کاهش خودکار:</strong> با انتخاب «کاهش سریع پایین» و تیک «خودکار −۱»، هر اسکن بلافاصله یک واحد کم می‌کند.</li>
                        </ul>
                    </section>

                    <section class="ssm-help-block">
                        <h3>ویرایش گروهی قیمت</h3>
                        <ul class="ssm-help-list">
                            <li>از تب <strong>ویرایش گروهی قیمت</strong> می‌توانید قیمت اصلی یا ویژه را درصدی یا با مبلغ ثابت، افزایش/کاهش دهید.</li>
                            <li>محدوده را با دسته‌بندی یا نوع محصول محدود کنید؛ همیشه اول پیش‌نمایش ببینید.</li>
                            <li>واحد فروشگاه (تومان/ریال) تشخیص داده می‌شود؛ واحد ورود مبلغ ثابت را خودتان انتخاب کنید تا تبدیل خودکار شود.</li>
                        </ul>
                    </section>

                    <section class="ssm-help-block">
                        <h3>لاگ تغییرات</h3>
                        <ul class="ssm-help-list">
                            <li>هر تغییر واقعی موجودی یا قیمت با نام کاربر، منبع و زمان ثبت می‌شود.</li>
                            <li>فیلتر حرفه‌ای: نوع عملیات، کاربر، منبع (اسکن/CSV/گروهی)، تاریخ و جستجوی محصول.</li>
                            <li>خروجی CSV از همین فیلتر، صفحه‌بندی و پاک‌سازی لاگ (فقط مدیر) در دسترس است.</li>
                        </ul>
                    </section>

                    <section class="ssm-help-block">
                        <h3>تنظیمات دسترسی</h3>
                        <ul class="ssm-help-list">
                            <li>مدیر سیستم برای هر کاربر بک‌اند تیک <em>مشاهده انبار</em>، <em>تغییر موجودی</em> و <em>تغییر قیمت</em> را جداگانه تنظیم می‌کند.</li>
                            <li>مشتریان فروشگاه در این لیست نیستند؛ ابتدا نقش پیشخوان وردپرس بدهید، بعد اینجا دسترسی بزنید.</li>
                            <li>آستانه فول موجودی هم در صفحه انبار و هم اینجا قابل تنظیم است.</li>
                        </ul>
                    </section>
                </div>
            </div></div>
            <?php
        }

        public function render_logs_page() {
            if (!$this->user_can_view_inventory() && !$this->is_plugin_admin()) {
                wp_die(esc_html('شما دسترسی مشاهده لاگ تغییرات ندارید.'), esc_html('دسترسی غیرمجاز'), array('response' => 403));
            }

            $logs = get_option(self::AUDIT_LOG_OPTION, array());
            if (!is_array($logs)) {
                $logs = array();
            }

            $filters = $this->get_logs_filters_from_request();
            $filtered = $this->filter_audit_logs($logs, $filters);
            $stats = $this->get_logs_stats($logs, $filtered);

            $total = count($filtered);
            $per_page = self::LOGS_PER_PAGE;
            $total_pages = max(1, (int) ceil($total / $per_page));
            $page = isset($_GET['ssm_log_page']) ? max(1, absint($_GET['ssm_log_page'])) : 1;
            if ($page > $total_pages) {
                $page = $total_pages;
            }
            $offset = ($page - 1) * $per_page;
            $page_rows = array_slice($filtered, $offset, $per_page);

            $users_in_logs = $this->get_log_user_options($logs);
            $base_url = admin_url('admin.php?page=' . self::LOGS_SLUG);
            $export_args = array_merge(array('action' => 'ssm_export_logs'), $this->logs_filters_to_query_args($filters));
            $export_url = wp_nonce_url(add_query_arg($export_args, admin_url('admin-post.php')), self::LOGS_NONCE);

            $labels = $this->get_log_action_labels();
            $context_labels = $this->get_log_context_labels();
            ?>
            <div class="wrap ssm-wrap" dir="rtl"><div class="ssm-container">
                <?php $this->render_page_top(self::LOGS_SLUG); ?>
                <h2 class="ssm-section-title">لاگ تغییرات</h2>
                <p class="ssm-page-subtitle">ردیابی حرفه‌ای موجودی و قیمت — فیلتر، جستجو و خروجی Excel.</p>

                <?php if (isset($_GET['ssm_logs_cleared']) && sanitize_key(wp_unslash($_GET['ssm_logs_cleared'])) === '1') : ?>
                    <div class="ssm-notice ssm-notice-info">همه لاگ‌ها پاک شدند.</div>
                <?php endif; ?>

                <?php
                $this->render_guide_box(
                    'راهنمای لاگ تغییرات',
                    array(
                        'هر تغییر واقعی موجودی یا قیمت با کاربر، منبع عملیات و زمان ثبت می‌شود.',
                        'با فیلتر نوع، کاربر، تاریخ، محصول و منبع (اسکن، CSV، گروهی و …) دقیق پیدا کنید.',
                        'خروجی CSV از همان فیلتر فعلی ساخته می‌شود.',
                    )
                );
                ?>

                <div class="ssm-log-stats" aria-label="آمار لاگ">
                    <div class="ssm-log-stat"><span class="ssm-log-stat-num"><?php echo esc_html((string) $stats['total_all']); ?></span><span class="ssm-log-stat-label">کل ذخیره‌شده</span></div>
                    <div class="ssm-log-stat"><span class="ssm-log-stat-num"><?php echo esc_html((string) $stats['filtered']); ?></span><span class="ssm-log-stat-label">نتیجه فیلتر</span></div>
                    <div class="ssm-log-stat"><span class="ssm-log-stat-num"><?php echo esc_html((string) $stats['today']); ?></span><span class="ssm-log-stat-label">امروز</span></div>
                    <div class="ssm-log-stat"><span class="ssm-log-stat-num"><?php echo esc_html((string) $stats['stock']); ?></span><span class="ssm-log-stat-label">موجودی (فیلتر)</span></div>
                    <div class="ssm-log-stat"><span class="ssm-log-stat-num"><?php echo esc_html((string) $stats['price']); ?></span><span class="ssm-log-stat-label">قیمت (فیلتر)</span></div>
                </div>

                <form class="ssm-log-filter-form" method="get" action="">
                    <input type="hidden" name="page" value="<?php echo esc_attr(self::LOGS_SLUG); ?>">
                    <div class="ssm-log-filter-grid">
                        <div class="ssm-bp-field">
                            <label for="ssm_log_filter">نوع عملیات</label>
                            <select id="ssm_log_filter" name="ssm_log_filter">
                                <option value="all" <?php selected($filters['type'], 'all'); ?>>همه</option>
                                <option value="stock" <?php selected($filters['type'], 'stock'); ?>>همه موجودی</option>
                                <option value="stock_increase" <?php selected($filters['type'], 'stock_increase'); ?>>افزایش موجودی</option>
                                <option value="stock_decrease" <?php selected($filters['type'], 'stock_decrease'); ?>>کاهش موجودی</option>
                                <option value="price" <?php selected($filters['type'], 'price'); ?>>همه قیمت</option>
                                <option value="regular_price" <?php selected($filters['type'], 'regular_price'); ?>>قیمت اصلی</option>
                                <option value="sale_price" <?php selected($filters['type'], 'sale_price'); ?>>قیمت ویژه</option>
                            </select>
                        </div>
                        <div class="ssm-bp-field">
                            <label for="ssm_log_user">کاربر</label>
                            <select id="ssm_log_user" name="ssm_log_user">
                                <option value="0">همه کاربران</option>
                                <?php foreach ($users_in_logs as $uid => $uname) : ?>
                                    <option value="<?php echo esc_attr((string) $uid); ?>" <?php selected($filters['user'], $uid); ?>><?php echo esc_html($uname); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="ssm-bp-field">
                            <label for="ssm_log_context">منبع</label>
                            <select id="ssm_log_context" name="ssm_log_context">
                                <option value="all" <?php selected($filters['context'], 'all'); ?>>همه منابع</option>
                                <?php foreach ($context_labels as $ckey => $clabel) : ?>
                                    <option value="<?php echo esc_attr($ckey); ?>" <?php selected($filters['context'], $ckey); ?>><?php echo esc_html($clabel); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="ssm-bp-field">
                            <label for="ssm_log_q">جستجوی محصول</label>
                            <input type="search" id="ssm_log_q" name="ssm_log_q" value="<?php echo esc_attr($filters['q']); ?>" placeholder="نام، SKU یا شناسه...">
                        </div>
                        <div class="ssm-bp-field">
                            <label for="ssm_log_from">از تاریخ</label>
                            <input type="date" id="ssm_log_from" name="ssm_log_from" value="<?php echo esc_attr($filters['from']); ?>">
                        </div>
                        <div class="ssm-bp-field">
                            <label for="ssm_log_to">تا تاریخ</label>
                            <input type="date" id="ssm_log_to" name="ssm_log_to" value="<?php echo esc_attr($filters['to']); ?>">
                        </div>
                    </div>
                    <div class="ssm-log-filter-actions">
                        <button type="submit" class="button button-primary">اعمال فیلتر</button>
                        <a class="button" href="<?php echo esc_url($base_url); ?>">پاک کردن فیلتر</a>
                        <a class="button button-secondary" href="<?php echo esc_url($export_url); ?>">خروجی CSV همین فیلتر</a>
                    </div>
                </form>
                <?php if ($this->is_plugin_admin()) : ?>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="ssm-log-clear-form" onsubmit="return confirm('همه لاگ‌ها پاک شوند؟ این کار برگشت‌پذیر نیست.');">
                    <input type="hidden" name="action" value="ssm_clear_logs">
                    <?php wp_nonce_field(self::LOGS_NONCE); ?>
                    <button type="submit" class="button">پاک‌سازی همه لاگ‌ها</button>
                </form>
                <?php endif; ?>

                <p class="ssm-muted ssm-log-meta">نمایش <?php echo esc_html((string) ($total ? ($offset + 1) : 0)); ?>–<?php echo esc_html((string) min($offset + $per_page, $total)); ?> از <?php echo esc_html((string) $total); ?> مورد</p>

                <div class="ssm-table-wrap"><table class="ssm-table widefat striped">
                    <thead>
                        <tr>
                            <th>کاربر</th>
                            <th>محصول</th>
                            <th>نوع</th>
                            <th>منبع</th>
                            <th>قبل</th>
                            <th>بعد</th>
                            <th>تاریخ</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($page_rows)) : ?>
                        <tr><td colspan="7" class="ssm-empty-cell">برای این فیلتر لاگی پیدا نشد.</td></tr>
                    <?php else : foreach ($page_rows as $row) :
                        $uid = isset($row['user_id']) ? absint($row['user_id']) : 0;
                        $user = $uid ? get_userdata($uid) : false;
                        $uname = $user ? $user->display_name : ('#' . $uid);
                        $pid = isset($row['product_id']) ? absint($row['product_id']) : 0;
                        $prod = $pid ? wc_get_product($pid) : false;
                        $pname = $prod ? $prod->get_name() : ('محصول #' . $pid);
                        $sku = $prod ? (string) $prod->get_sku() : '';
                        $atype = isset($row['_atype']) ? $row['_atype'] : 'stock_update';
                        $alabel = $this->format_log_action_label($row, $labels);
                        $ctx = isset($row['context']) ? sanitize_key($row['context']) : '';
                        $clabel = ($ctx && isset($context_labels[$ctx])) ? $context_labels[$ctx] : ($ctx ? $ctx : '—');
                        $old = isset($row['old_value']) ? $row['old_value'] : (isset($row['old']) ? $row['old'] : '');
                        $newv = isset($row['new_value']) ? $row['new_value'] : (isset($row['new']) ? $row['new'] : '');
                        $when = isset($row['created_at']) ? $row['created_at'] : (isset($row['time']) ? $row['time'] : '');
                        $edit_link = $pid ? get_edit_post_link($pid) : '';
                        if (!$edit_link && $pid && $prod && $prod->is_type('variation')) {
                            $parent = $prod->get_parent_id();
                            $edit_link = $parent ? get_edit_post_link($parent) : '';
                        }
                    ?>
                        <tr>
                            <td><?php echo esc_html($uname); ?></td>
                            <td>
                                <?php if ($pid && $edit_link) : ?>
                                    <a href="<?php echo esc_url($edit_link); ?>"><?php echo esc_html($pname); ?></a>
                                <?php else : ?>
                                    <?php echo esc_html($pname); ?>
                                <?php endif; ?>
                                <div class="ssm-muted">#<?php echo esc_html((string) $pid); ?><?php echo $sku !== '' ? ' — ' . esc_html($sku) : ''; ?></div>
                            </td>
                            <td><span class="ssm-log-badge ssm-log-<?php echo esc_attr(sanitize_html_class($atype)); ?>"><?php echo esc_html($alabel); ?></span></td>
                            <td><span class="ssm-log-context"><?php echo esc_html($clabel); ?></span></td>
                            <td><?php echo esc_html((string) $old); ?></td>
                            <td><strong><?php echo esc_html((string) $newv); ?></strong></td>
                            <td><?php echo esc_html($when); ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table></div>

                <?php if ($total_pages > 1) : ?>
                    <nav class="ssm-log-pagination" aria-label="صفحه‌بندی لاگ">
                        <?php
                        for ($i = 1; $i <= $total_pages; $i++) {
                            $url = add_query_arg(array_merge($this->logs_filters_to_query_args($filters), array('ssm_log_page' => $i)), $base_url);
                            $cls = 'ssm-log-page' . ($i === $page ? ' is-active' : '');
                            echo '<a class="' . esc_attr($cls) . '" href="' . esc_url($url) . '">' . esc_html((string) $i) . '</a>';
                        }
                        ?>
                    </nav>
                <?php endif; ?>
            </div></div>
            <?php
        }

        private function get_log_action_labels() {
            return array(
                'stock_increase' => 'افزایش موجودی',
                'stock_decrease' => 'کاهش موجودی',
                'stock_update'   => 'بروزرسانی موجودی',
                'price_update'   => 'بروزرسانی قیمت',
            );
        }

        private function get_log_context_labels() {
            return array(
                'ajax_update'     => 'ویرایش دستی',
                'ajax_decrease'   => 'کاهش سریع (−۱)',
                'ajax_scan_bump'  => 'اسکن خودکار',
                'csv_import'      => 'ورود CSV',
                'bulk_price'      => 'ویرایش گروهی قیمت',
                'bulk_rollback'   => 'بازگردانی ویرایش گروهی',
            );
        }

        private function format_log_action_label($row, $labels) {
            $atype = isset($row['_atype']) ? $row['_atype'] : (isset($row['action_type']) ? $row['action_type'] : 'stock_update');
            $alabel = isset($labels[$atype]) ? $labels[$atype] : $atype;
            $field = isset($row['field']) ? $row['field'] : '';
            if ($atype === 'price_update') {
                if ($field === 'sale_price') {
                    return 'قیمت ویژه';
                }
                if ($field === 'regular_price') {
                    return 'قیمت اصلی';
                }
            }
            return $alabel;
        }

        private function normalize_log_row($row) {
            if (!is_array($row)) {
                return null;
            }
            $atype = isset($row['action_type']) ? $row['action_type'] : '';
            if ($atype === '' && isset($row['field'])) {
                $atype = in_array($row['field'], array('regular_price', 'sale_price', 'price'), true) ? 'price_update' : 'stock_update';
            }
            if (!in_array($atype, array('stock_increase', 'stock_decrease', 'stock_update', 'price_update'), true)) {
                $atype = 'stock_update';
            }
            $row['_atype'] = $atype;
            if (empty($row['field'])) {
                if ($atype === 'price_update') {
                    $row['field'] = 'regular_price';
                } else {
                    $row['field'] = 'stock';
                }
            }
            if (!isset($row['context'])) {
                $row['context'] = '';
            }
            return $row;
        }

        private function get_logs_filters_from_request() {
            $type = isset($_REQUEST['ssm_log_filter']) ? sanitize_key(wp_unslash($_REQUEST['ssm_log_filter'])) : 'all';
            $allowed_types = array('all', 'stock', 'price', 'stock_increase', 'stock_decrease', 'regular_price', 'sale_price');
            if (!in_array($type, $allowed_types, true)) {
                $type = 'all';
            }
            $user = isset($_REQUEST['ssm_log_user']) ? absint($_REQUEST['ssm_log_user']) : 0;
            $context = isset($_REQUEST['ssm_log_context']) ? sanitize_key(wp_unslash($_REQUEST['ssm_log_context'])) : 'all';
            $ctx_ok = array_merge(array('all'), array_keys($this->get_log_context_labels()));
            if (!in_array($context, $ctx_ok, true)) {
                $context = 'all';
            }
            $q = isset($_REQUEST['ssm_log_q']) ? sanitize_text_field(wp_unslash($_REQUEST['ssm_log_q'])) : '';
            if (function_exists('mb_substr')) {
                $q = mb_substr($q, 0, 80);
            } else {
                $q = substr($q, 0, 80);
            }
            $from = isset($_REQUEST['ssm_log_from']) ? sanitize_text_field(wp_unslash($_REQUEST['ssm_log_from'])) : '';
            $to = isset($_REQUEST['ssm_log_to']) ? sanitize_text_field(wp_unslash($_REQUEST['ssm_log_to'])) : '';
            if ($from !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
                $from = '';
            }
            if ($to !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
                $to = '';
            }
            return array(
                'type'    => $type,
                'user'    => $user,
                'context' => $context,
                'q'       => $q,
                'from'    => $from,
                'to'      => $to,
            );
        }

        private function logs_filters_to_query_args($filters) {
            $args = array();
            if (!empty($filters['type']) && $filters['type'] !== 'all') {
                $args['ssm_log_filter'] = $filters['type'];
            }
            if (!empty($filters['user'])) {
                $args['ssm_log_user'] = (int) $filters['user'];
            }
            if (!empty($filters['context']) && $filters['context'] !== 'all') {
                $args['ssm_log_context'] = $filters['context'];
            }
            if (!empty($filters['q'])) {
                $args['ssm_log_q'] = $filters['q'];
            }
            if (!empty($filters['from'])) {
                $args['ssm_log_from'] = $filters['from'];
            }
            if (!empty($filters['to'])) {
                $args['ssm_log_to'] = $filters['to'];
            }
            return $args;
        }

        private function filter_audit_logs($logs, $filters) {
            $out = array();
            $q = isset($filters['q']) ? trim((string) $filters['q']) : '';
            $q_lower = function_exists('mb_strtolower') ? mb_strtolower($q) : strtolower($q);

            foreach ((array) $logs as $row) {
                $row = $this->normalize_log_row($row);
                if (!$row) {
                    continue;
                }
                $atype = $row['_atype'];
                $field = isset($row['field']) ? $row['field'] : '';
                $is_price = ($atype === 'price_update');

                $type = $filters['type'];
                if ($type === 'stock' && $is_price) {
                    continue;
                }
                if ($type === 'price' && !$is_price) {
                    continue;
                }
                if ($type === 'stock_increase' && $atype !== 'stock_increase') {
                    continue;
                }
                if ($type === 'stock_decrease' && $atype !== 'stock_decrease') {
                    continue;
                }
                if ($type === 'regular_price' && !($is_price && $field === 'regular_price')) {
                    continue;
                }
                if ($type === 'sale_price' && !($is_price && $field === 'sale_price')) {
                    continue;
                }

                if (!empty($filters['user']) && (int) $row['user_id'] !== (int) $filters['user']) {
                    continue;
                }

                if (!empty($filters['context']) && $filters['context'] !== 'all') {
                    $ctx = isset($row['context']) ? $row['context'] : '';
                    if ($ctx !== $filters['context']) {
                        continue;
                    }
                }

                $when = isset($row['created_at']) ? $row['created_at'] : (isset($row['time']) ? $row['time'] : '');
                $day = $when ? substr((string) $when, 0, 10) : '';
                if (!empty($filters['from']) && ($day === '' || $day < $filters['from'])) {
                    continue;
                }
                if (!empty($filters['to']) && ($day === '' || $day > $filters['to'])) {
                    continue;
                }

                if ($q !== '') {
                    $pid = isset($row['product_id']) ? absint($row['product_id']) : 0;
                    $prod = $pid ? wc_get_product($pid) : false;
                    $pname = $prod ? $prod->get_name() : ('#' . $pid);
                    $sku = $prod ? (string) $prod->get_sku() : '';
                    $hay = $pname . ' ' . $sku . ' ' . $pid;
                    $hay_l = function_exists('mb_strtolower') ? mb_strtolower($hay) : strtolower($hay);
                    if (function_exists('mb_strpos')) {
                        if (mb_strpos($hay_l, $q_lower) === false && (string) $pid !== $q) {
                            continue;
                        }
                    } elseif (strpos($hay_l, $q_lower) === false && (string) $pid !== $q) {
                        continue;
                    }
                }

                $out[] = $row;
            }
            return $out;
        }

        private function get_logs_stats($all_logs, $filtered) {
            $today = current_time('Y-m-d');
            $today_count = 0;
            foreach ((array) $all_logs as $row) {
                $when = isset($row['created_at']) ? $row['created_at'] : '';
                if ($when && substr((string) $when, 0, 10) === $today) {
                    $today_count++;
                }
            }
            $stock = 0;
            $price = 0;
            foreach ($filtered as $row) {
                if (isset($row['_atype']) && $row['_atype'] === 'price_update') {
                    $price++;
                } else {
                    $stock++;
                }
            }
            return array(
                'total_all' => count((array) $all_logs),
                'filtered'  => count($filtered),
                'today'     => $today_count,
                'stock'     => $stock,
                'price'     => $price,
            );
        }

        private function get_log_user_options($logs) {
            $map = array();
            foreach ((array) $logs as $row) {
                $uid = isset($row['user_id']) ? absint($row['user_id']) : 0;
                if (!$uid || isset($map[$uid])) {
                    continue;
                }
                $user = get_userdata($uid);
                $map[$uid] = $user ? $user->display_name : ('#' . $uid);
            }
            asort($map, SORT_STRING);
            return $map;
        }

        public function handle_export_logs() {
            if (!$this->user_can_view_inventory() && !$this->is_plugin_admin()) {
                wp_die(esc_html('دسترسی غیرمجاز.'), esc_html('دسترسی غیرمجاز'), array('response' => 403));
            }
            $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
            if ($nonce === '' || !wp_verify_nonce($nonce, self::LOGS_NONCE)) {
                wp_die(esc_html('توکن امنیتی نامعتبر است.'));
            }

            $logs = get_option(self::AUDIT_LOG_OPTION, array());
            if (!is_array($logs)) {
                $logs = array();
            }
            $filters = $this->get_logs_filters_from_request();
            $filtered = $this->filter_audit_logs($logs, $filters);
            $labels = $this->get_log_action_labels();
            $context_labels = $this->get_log_context_labels();

            $filename = 'sabalan-logs-' . gmdate('Y-m-d-His') . '.csv';
            nocache_headers();
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');

            $out = fopen('php://output', 'w');
            if ($out === false) {
                wp_die(esc_html('خروجی CSV ساخته نشد.'));
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array('user', 'product_id', 'product', 'sku', 'action', 'context', 'old_value', 'new_value', 'created_at', 'ip'));
            foreach ($filtered as $row) {
                $uid = isset($row['user_id']) ? absint($row['user_id']) : 0;
                $user = $uid ? get_userdata($uid) : false;
                $uname = $user ? $user->display_name : ('#' . $uid);
                $pid = isset($row['product_id']) ? absint($row['product_id']) : 0;
                $prod = $pid ? wc_get_product($pid) : false;
                $pname = $prod ? $prod->get_name() : '';
                $sku = $prod ? (string) $prod->get_sku() : '';
                $ctx = isset($row['context']) ? $row['context'] : '';
                $clabel = isset($context_labels[$ctx]) ? $context_labels[$ctx] : $ctx;
                fputcsv($out, array(
                    $uname,
                    $pid,
                    $pname,
                    $sku,
                    $this->format_log_action_label($row, $labels),
                    $clabel,
                    isset($row['old_value']) ? $row['old_value'] : '',
                    isset($row['new_value']) ? $row['new_value'] : '',
                    isset($row['created_at']) ? $row['created_at'] : '',
                    isset($row['ip']) ? $row['ip'] : '',
                ));
            }
            fclose($out);
            exit;
        }

        public function handle_clear_logs() {
            if (!$this->is_plugin_admin()) {
                wp_die(esc_html('فقط مدیر می‌تواند لاگ را پاک کند.'), esc_html('دسترسی غیرمجاز'), array('response' => 403));
            }
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), self::LOGS_NONCE)) {
                wp_die(esc_html('توکن امنیتی نامعتبر است.'));
            }
            update_option(self::AUDIT_LOG_OPTION, array(), false);
            wp_safe_redirect(add_query_arg(array(
                'page' => self::LOGS_SLUG,
                'ssm_logs_cleared' => '1',
            ), admin_url('admin.php')));
            exit;
        }

        public function handle_access_settings_save() {
            if (!isset($_POST['ssm_access_save'])) { return; }
            if (!$this->is_plugin_admin()) { return; }
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), self::ACCESS_NONCE)) {
                wp_die(esc_html('توکن امنیتی نامعتبر است. صفحه را بروزرسانی کنید و دوباره تلاش کنید.'));
            }
            $posted = isset($_POST['ssm_users']) && is_array($_POST['ssm_users']) ? wp_unslash($_POST['ssm_users']) : array();
            // شناسه‌هایی که در فرم بودند (حتی بدون تیک) تا لغو دسترسی ممکن باشد
            $form_ids = isset($_POST['ssm_user_ids']) && is_array($_POST['ssm_user_ids'])
                ? array_map('absint', wp_unslash($_POST['ssm_user_ids']))
                : array_map('absint', array_keys($posted));
            $form_ids = array_values(array_unique(array_filter($form_ids)));

            foreach ($form_ids as $user_id) {
                if (!$user_id || !$this->is_backend_staff_user($user_id) || $this->is_plugin_admin($user_id)) {
                    continue;
                }
                $flags = isset($posted[$user_id]) && is_array($posted[$user_id]) ? $posted[$user_id] : array();
                $view = !empty($flags['can_view_inventory']) ? 1 : 0;
                $stock = !empty($flags['can_edit_stock']) ? 1 : 0;
                $price = !empty($flags['can_edit_price']) ? 1 : 0;
                if (($stock || $price) && !$view) { $view = 1; }
                update_user_meta($user_id, self::META_VIEW, $view);
                update_user_meta($user_id, self::META_STOCK, $stock);
                update_user_meta($user_id, self::META_PRICE, $price);
            }
            wp_safe_redirect(add_query_arg(array(
                'page'       => self::ACCESS_SLUG,
                'updated'    => '1',
                'ssm_user_q' => isset($_POST['ssm_user_q']) ? sanitize_text_field(wp_unslash($_POST['ssm_user_q'])) : '',
            ), admin_url('admin.php')));
            exit;
        }

        public function handle_stock_settings_save() {
            if (!isset($_POST['ssm_stock_settings_save'])) {
                return;
            }
            if (!$this->is_plugin_admin()) {
                return;
            }
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), self::SETTINGS_NONCE)) {
                wp_die(esc_html('توکن امنیتی نامعتبر است. صفحه را بروزرسانی کنید و دوباره تلاش کنید.'));
            }
            $threshold = isset($_POST['ssm_low_stock_threshold']) ? absint($_POST['ssm_low_stock_threshold']) : 8;
            if ($threshold < 1) {
                $threshold = 1;
            }
            if ($threshold > 9999) {
                $threshold = 9999;
            }
            update_option(self::OPT_LOW_STOCK, $threshold, false);
            $redirect_page = self::MENU_SLUG;
            if (!empty($_POST['_wp_http_referer'])) {
                $ref = wp_unslash($_POST['_wp_http_referer']);
                $ref_query = wp_parse_url($ref, PHP_URL_QUERY);
                if (is_string($ref_query)) {
                    parse_str($ref_query, $ref_args);
                    if (!empty($ref_args['page']) && in_array($ref_args['page'], array(self::MENU_SLUG, self::ACCESS_SLUG), true)) {
                        $redirect_page = sanitize_key($ref_args['page']);
                    }
                }
            }
            wp_safe_redirect(add_query_arg(array(
                'page'          => $redirect_page,
                'stock_updated' => '1',
            ), admin_url('admin.php')));
            exit;
        }

        public function handle_export_csv() {
            if (!$this->user_can_view_inventory() && !$this->is_plugin_admin()) {
                wp_die(esc_html('دسترسی غیرمجاز.'), esc_html('دسترسی غیرمجاز'), array('response' => 403));
            }
            $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field(wp_unslash($_GET['_wpnonce'])) : '';
            if ($nonce === '' || !wp_verify_nonce($nonce, self::CSV_NONCE)) {
                wp_die(esc_html('توکن امنیتی نامعتبر است.'));
            }

            $rows = $this->build_csv_export_rows();
            $filename = 'sabalan-stock-' . gmdate('Y-m-d-His') . '.csv';

            nocache_headers();
            header('Content-Type: text/csv; charset=UTF-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Expires: 0');

            $out = fopen('php://output', 'w');
            if ($out === false) {
                wp_die(esc_html('خروجی CSV ساخته نشد.'));
            }
            // BOM برای باز شدن صحیح در Excel
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, array('product_id', 'sku', 'name', 'type', 'parent_id', 'stock_qty', 'regular_price', 'sale_price'));
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
            fclose($out);
            exit;
        }

        public function handle_import_csv() {
            if (!$this->user_can_edit_stock() && !$this->user_can_edit_price() && !$this->is_plugin_admin()) {
                wp_die(esc_html('برای وارد کردن CSV به دسترسی تغییر موجودی یا قیمت نیاز دارید.'), esc_html('دسترسی غیرمجاز'), array('response' => 403));
            }
            if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), self::CSV_NONCE)) {
                wp_die(esc_html('توکن امنیتی نامعتبر است.'));
            }
            if (empty($_FILES['ssm_csv_file']['tmp_name']) || !is_uploaded_file($_FILES['ssm_csv_file']['tmp_name'])) {
                wp_safe_redirect(add_query_arg(array(
                    'page'        => self::CSV_SLUG,
                    'ssm_csv_err' => 'nofile',
                ), admin_url('admin.php')));
                exit;
            }

            $tmp = $_FILES['ssm_csv_file']['tmp_name'];
            $size = isset($_FILES['ssm_csv_file']['size']) ? (int) $_FILES['ssm_csv_file']['size'] : 0;
            if ($size <= 0 || $size > 5 * 1024 * 1024) {
                wp_safe_redirect(add_query_arg(array(
                    'page'        => self::CSV_SLUG,
                    'ssm_csv_err' => 'size',
                ), admin_url('admin.php')));
                exit;
            }

            $result = $this->process_csv_import($tmp);
            wp_safe_redirect(add_query_arg(array(
                'page'         => self::CSV_SLUG,
                'ssm_csv_ok'   => (int) $result['updated'],
                'ssm_csv_skip' => (int) $result['skipped'],
                'ssm_csv_fail' => (int) $result['failed'],
            ), admin_url('admin.php')));
            exit;
        }

        private function build_csv_export_rows() {
            $rows = array();
            $ids = wc_get_products(array(
                'limit'  => -1,
                'status' => array('publish', 'private'),
                'type'   => array('simple', 'variable'),
                'return' => 'ids',
                'orderby'=> 'ID',
                'order'  => 'ASC',
            ));
            if (empty($ids) || !is_array($ids)) {
                return $rows;
            }

            foreach ($ids as $pid) {
                $product = wc_get_product($pid);
                if (!$product) {
                    continue;
                }
                if ($product->is_type('variable')) {
                    $children = $product->get_children();
                    foreach ($children as $vid) {
                        $variation = wc_get_product($vid);
                        if (!$variation) {
                            continue;
                        }
                        $rows[] = $this->csv_row_from_product($variation, $pid);
                    }
                } else {
                    $rows[] = $this->csv_row_from_product($product, 0);
                }
            }
            return $rows;
        }

        private function csv_row_from_product($product, $parent_id) {
            $stock = $product->get_manage_stock() ? $product->get_stock_quantity() : '';
            if ($stock === null) {
                $stock = '';
            }
            $regular = $product->get_regular_price();
            $sale = $product->get_sale_price();
            return array(
                (int) $product->get_id(),
                (string) $product->get_sku(),
                (string) $product->get_name(),
                $product->is_type('variation') ? 'variation' : 'simple',
                (int) $parent_id,
                $stock === '' ? '' : (string) (int) $stock,
                $regular === null || $regular === '' ? '' : (string) $regular,
                $sale === null || $sale === '' ? '' : (string) $sale,
            );
        }

        private function process_csv_import($filepath) {
            $updated = 0;
            $skipped = 0;
            $failed = 0;
            $can_stock = $this->is_plugin_admin() || $this->user_can_edit_stock();
            $can_price = $this->is_plugin_admin() || $this->user_can_edit_price();

            $fh = fopen($filepath, 'r');
            if ($fh === false) {
                return array('updated' => 0, 'skipped' => 0, 'failed' => 1);
            }

            $header = fgetcsv($fh);
            if ($header === false) {
                fclose($fh);
                return array('updated' => 0, 'skipped' => 0, 'failed' => 1);
            }
            // حذف BOM از اولین سلول
            if (isset($header[0])) {
                $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', $header[0]);
            }
            $map = array();
            foreach ($header as $i => $col) {
                $key = strtolower(trim((string) $col));
                $map[$key] = $i;
            }
            if (!isset($map['product_id']) && !isset($map['sku'])) {
                fclose($fh);
                return array('updated' => 0, 'skipped' => 0, 'failed' => 1);
            }

            $max_rows = 5000;
            $count = 0;
            while (($cols = fgetcsv($fh)) !== false) {
                $count++;
                if ($count > $max_rows) {
                    break;
                }
                if (!is_array($cols) || count(array_filter($cols, 'strlen')) === 0) {
                    $skipped++;
                    continue;
                }

                $product_id = 0;
                if (isset($map['product_id']) && isset($cols[$map['product_id']])) {
                    $product_id = absint($cols[$map['product_id']]);
                }
                $sku = '';
                if (isset($map['sku']) && isset($cols[$map['sku']])) {
                    $sku = sanitize_text_field($cols[$map['sku']]);
                }

                $product = null;
                if ($product_id) {
                    $product = $this->get_editable_product($product_id);
                }
                if (!$product && $sku !== '') {
                    $found_id = wc_get_product_id_by_sku($sku);
                    if ($found_id) {
                        $product = $this->get_editable_product($found_id);
                    }
                }
                if (!$product) {
                    $failed++;
                    continue;
                }

                $changed = false;
                $pid = $product->get_id();

                if ($can_stock && isset($map['stock_qty']) && array_key_exists($map['stock_qty'], $cols)) {
                    $raw = trim((string) $cols[$map['stock_qty']]);
                    if ($raw !== '') {
                        if (!is_numeric($raw) || (float) $raw < 0) {
                            $failed++;
                            continue;
                        }
                        $new_qty = (int) $raw;
                        $old = $product->get_manage_stock() ? (int) $product->get_stock_quantity() : null;
                        if ($old === null || $old !== $new_qty) {
                            $product->set_manage_stock(true);
                            $product->set_stock_quantity($new_qty);
                            $product->set_stock_status($new_qty > 0 ? 'instock' : 'outofstock');
                            $this->log_inventory_change($pid, 'stock', $old === null ? '' : (string) $old, (string) $new_qty, 'csv_import');
                            $changed = true;
                        }
                    }
                }

                if ($can_price) {
                    if (isset($map['regular_price']) && array_key_exists($map['regular_price'], $cols)) {
                        $raw = trim((string) $cols[$map['regular_price']]);
                        if ($raw !== '') {
                            if (!is_numeric($raw) || (float) $raw < 0) {
                                $failed++;
                                continue;
                            }
                            $old = (string) $product->get_regular_price();
                            $newp = wc_format_decimal($raw);
                            if ((string) $newp !== $old) {
                                $product->set_regular_price($newp);
                                $this->log_inventory_change($pid, 'regular_price', $old, (string) $newp, 'csv_import');
                                $changed = true;
                            }
                        }
                    }
                    if (isset($map['sale_price']) && array_key_exists($map['sale_price'], $cols)) {
                        $raw = trim((string) $cols[$map['sale_price']]);
                        $old = (string) $product->get_sale_price();
                        if ($raw === '') {
                            if ($old !== '') {
                                $product->set_sale_price('');
                                $this->log_inventory_change($pid, 'sale_price', $old, '', 'csv_import');
                                $changed = true;
                            }
                        } else {
                            if (!is_numeric($raw) || (float) $raw < 0) {
                                $failed++;
                                continue;
                            }
                            $news = wc_format_decimal($raw);
                            $regular = $product->get_regular_price();
                            if ($regular !== '' && (float) $news >= (float) $regular) {
                                $failed++;
                                continue;
                            }
                            if ((string) $news !== $old) {
                                $product->set_sale_price($news);
                                $this->log_inventory_change($pid, 'sale_price', $old, (string) $news, 'csv_import');
                                $changed = true;
                            }
                        }
                    }
                }

                if ($changed) {
                    $product->save();
                    $updated++;
                } else {
                    $skipped++;
                }
            }
            fclose($fh);
            return array('updated' => $updated, 'skipped' => $skipped, 'failed' => $failed);
        }

        // نقش‌هایی که مشتری فروشگاه‌اند و در تنظیمات دسترسی نمی‌آیند
        private function get_access_excluded_roles() {
            return apply_filters('ssm_access_exclude_roles', array('customer', 'subscriber'));
        }

        private function is_backend_staff_user($user_id) {
            $user = get_userdata(absint($user_id));
            if (!$user || empty($user->roles)) {
                return false;
            }
            $exclude = $this->get_access_excluded_roles();
            foreach ((array) $user->roles as $role) {
                if (!in_array($role, $exclude, true)) {
                    return true;
                }
            }
            return false;
        }

        private function get_user_roles_label($user_id) {
            $user = get_userdata(absint($user_id));
            if (!$user || empty($user->roles)) {
                return '';
            }
            $wp_roles = wp_roles();
            $labels = array();
            foreach ((array) $user->roles as $role) {
                if (isset($wp_roles->roles[$role]['name'])) {
                    $labels[] = translate_user_role($wp_roles->roles[$role]['name']);
                } else {
                    $labels[] = $role;
                }
            }
            return implode('، ', $labels);
        }

        private function get_backend_users_for_access($search = '') {
            $exclude = $this->get_access_excluded_roles();
            $all_roles = function_exists('wp_roles') ? array_keys(wp_roles()->roles) : array();
            $role_in = array_values(array_diff($all_roles, $exclude));

            if (empty($role_in)) {
                $role_in = array('administrator', 'shop_manager', 'editor', 'author');
            }

            $role_in = apply_filters('ssm_access_include_roles', $role_in);
            $search  = trim((string) $search);

            $args = array(
                'role__in' => array_map('sanitize_key', (array) $role_in),
                'orderby'  => 'display_name',
                'order'    => 'ASC',
                'number'   => 200,
            );

            if ($search !== '') {
                $args['search']         = '*' . $search . '*';
                $args['search_columns'] = array('user_login', 'user_email', 'display_name', 'user_nicename');
            }

            $users = get_users($args);

            // اگر با search چیزی نیامد، روی لیست بک‌اند فیلتر دستی هم بزن (ایمیل ناقص و فارسی)
            if ($search !== '' && empty($users)) {
                $all = get_users(array(
                    'role__in' => array_map('sanitize_key', (array) $role_in),
                    'orderby'  => 'display_name',
                    'order'    => 'ASC',
                    'number'   => 300,
                ));
                $needle = function_exists('mb_strtolower') ? mb_strtolower($search) : strtolower($search);
                $users = array();
                foreach ($all as $u) {
                    $hay = $u->user_login . ' ' . $u->user_email . ' ' . $u->display_name;
                    $hay = function_exists('mb_strtolower') ? mb_strtolower($hay) : strtolower($hay);
                    if (function_exists('mb_strpos') ? (mb_strpos($hay, $needle) !== false) : (strpos($hay, $needle) !== false)) {
                        $users[] = $u;
                    }
                }
            }

            return $users;
        }

        public function render_access_page() {
            if (!$this->is_plugin_admin()) {
                wp_die(esc_html('فقط مدیر سیستم می‌تواند تنظیمات دسترسی را مدیریت کند.'), esc_html('دسترسی غیرمجاز'), array('response' => 403));
            }

            $search = isset($_GET['ssm_user_q']) ? sanitize_text_field(wp_unslash($_GET['ssm_user_q'])) : '';
            if (function_exists('mb_substr')) {
                $search = mb_substr($search, 0, 80);
            } else {
                $search = substr($search, 0, 80);
            }
            $users = $this->get_backend_users_for_access($search);
            $total_backend = count($this->get_backend_users_for_access(''));
            ?>
            <div class="wrap ssm-wrap" dir="rtl"><div class="ssm-container">
                <?php $this->render_page_top(self::ACCESS_SLUG); ?>
                <h2 class="ssm-section-title">تنظیمات دسترسی</h2>
                <p class="ssm-page-subtitle">کاربران بک‌اند وردپرس (ادمین، مدیر فروشگاه، ویرایشگر و نقش‌های مشابه). مشتریان فروشگاه در این لیست نیستند.</p>

                <?php
                $this->render_guide_box(
                    'آموزش کوتاه تنظیمات دسترسی',
                    array(
                        'لیست زیر همان کاربران اضافه‌شده وردپرس با نقش پیشخوان است.',
                        'با جعبه جستجو، نام کاربری یا ایمیل انباردار را پیدا کنید.',
                        'برای انباردار: اول در وردپرس نقش غیرمشتری بدهید، بعد اینجا تیک بزنید.',
                        'مشاهده انبار / تغییر موجودی / تغییر قیمت را جدا تنظیم کنید و ذخیره کنید.',
                        'مدیران سیستم همیشه دسترسی کامل دارند.',
                        'آستانه فول موجودی را تنظیم کنید تا رنگ‌بندی سبز/زرد/نارنجی/قرمز و فیلتر «کم‌موجود» هماهنگ شوند.',
                    )
                );
                ?>

                <?php if (isset($_GET['updated']) && sanitize_key(wp_unslash($_GET['updated'])) === '1') : ?>
                    <div class="ssm-notice ssm-notice-info">تنظیمات دسترسی با موفقیت ذخیره شد.</div>
                <?php endif; ?>
                <?php if (isset($_GET['stock_updated']) && sanitize_key(wp_unslash($_GET['stock_updated'])) === '1') : ?>
                    <div class="ssm-notice ssm-notice-info">آستانه کم‌موجودی ذخیره شد.</div>
                <?php endif; ?>

                <section class="ssm-panel ssm-panel-stock-settings">
                    <div class="ssm-panel-head"><span class="ssm-panel-badge">!</span><div><h2>آستانه فول موجودی</h2><p>این عدد مرجع رنگ‌بندی است (سبز ≥ آستانه، بعد زرد/نارنجی/قرمز هر ۲۵٪). فیلتر «کم‌موجود» هم زیر آستانه است.</p></div></div>
                    <form method="post" action="" class="ssm-stock-settings-form">
                        <?php wp_nonce_field(self::SETTINGS_NONCE); ?>
                        <?php wp_referer_field(); ?>
                        <label for="ssm-low-stock-threshold">آستانه فول (عدد مرجع):</label>
                        <div class="ssm-stock-settings-row">
                            <input type="number" min="1" max="9999" id="ssm-low-stock-threshold" name="ssm_low_stock_threshold" value="<?php echo esc_attr((string) $this->get_low_stock_threshold()); ?>">
                            <button type="submit" name="ssm_stock_settings_save" value="1" class="button button-primary">ذخیره آستانه</button>
                        </div>
                    </form>
                    <div class="ssm-stock-legend" aria-label="راهنمای رنگ موجودی">
                        <span class="ssm-legend-item stock-ok">سبز ≥ ۱۰۰٪</span>
                        <span class="ssm-legend-item stock-warn">زرد ≥ ۷۵٪</span>
                        <span class="ssm-legend-item stock-mid">نارنجی ≥ ۵۰٪</span>
                        <span class="ssm-legend-item stock-danger">قرمز &lt; ۵۰٪</span>
                    </div>
                </section>

                <form class="ssm-user-search" method="get" action="">
                    <input type="hidden" name="page" value="<?php echo esc_attr(self::ACCESS_SLUG); ?>">
                    <label class="ssm-user-search-label" for="ssm-user-q">جستجوی کاربر وردپرس</label>
                    <div class="ssm-user-search-row">
                        <input type="search" id="ssm-user-q" name="ssm_user_q" value="<?php echo esc_attr($search); ?>" placeholder="نام کاربری، نام نمایشی یا ایمیل..." autocomplete="off">
                        <button type="submit" class="button button-primary">جستجو</button>
                        <?php if ($search !== '') : ?>
                            <a class="button" href="<?php echo esc_url(admin_url('admin.php?page=' . self::ACCESS_SLUG)); ?>">پاک کردن</a>
                        <?php endif; ?>
                    </div>
                    <p class="ssm-muted ssm-user-search-meta">
                        <?php
                        if ($search !== '') {
                            echo esc_html(sprintf('نتیجه جستجو برای «%s»: %d کاربر', $search, count($users)));
                        } else {
                            echo esc_html(sprintf('تعداد کاربران بک‌اند: %d', $total_backend));
                        }
                        ?>
                    </p>
                </form>

                <?php if (empty($users)) : ?>
                    <div class="ssm-notice ssm-notice-warning">
                        <?php if ($search !== '') : ?>
                            کاربری با این مشخصات در نقش‌های بک‌اند پیدا نشد. نقش کاربر را در وردپرس چک کنید (نباید فقط Customer باشد).
                        <?php else : ?>
                            کاربر بک‌اندی پیدا نشد. حداقل یک مدیر یا نقش پیشخوان در وردپرس باید وجود داشته باشد.
                        <?php endif; ?>
                    </div>
                <?php else : ?>
                <form method="post" action=""><?php wp_nonce_field(self::ACCESS_NONCE); ?>
                <?php if ($search !== '') : ?>
                    <input type="hidden" name="ssm_user_q" value="<?php echo esc_attr($search); ?>">
                <?php endif; ?>
                <div class="ssm-table-wrap"><table class="ssm-table widefat striped">
                    <thead><tr><th>کاربر</th><th>نقش</th><th>ایمیل</th><th>مشاهده انبار</th><th>تغییر موجودی</th><th>تغییر قیمت</th></tr></thead>
                    <tbody>
                    <?php foreach ($users as $u) :
                        $is_admin = $this->is_plugin_admin($u->ID);
                        $view = $is_admin || $this->user_meta_flag($u->ID, self::META_VIEW);
                        $stock = $is_admin || $this->user_meta_flag($u->ID, self::META_STOCK);
                        $price = $is_admin || $this->user_meta_flag($u->ID, self::META_PRICE);
                        $roles_label = $this->get_user_roles_label($u->ID);
                    ?>
                        <tr>
                            <td>
                                <?php if (!$is_admin) : ?>
                                    <input type="hidden" name="ssm_user_ids[]" value="<?php echo esc_attr($u->ID); ?>">
                                <?php endif; ?>
                                <strong><?php echo esc_html($u->display_name); ?></strong><div class="ssm-muted"><?php echo esc_html($u->user_login); ?><?php echo $is_admin ? ' — مدیر' : ''; ?></div>
                            </td>
                            <td><span class="ssm-muted"><?php echo esc_html($roles_label ? $roles_label : '—'); ?></span></td>
                            <td><?php echo esc_html($u->user_email); ?></td>
                            <?php if ($is_admin) : ?>
                                <td colspan="3"><em class="ssm-muted">دسترسی کامل (مدیر سیستم)</em></td>
                            <?php else : ?>
                                <td class="ssm-check-cell"><label><input type="checkbox" name="ssm_users[<?php echo esc_attr($u->ID); ?>][can_view_inventory]" value="1" <?php checked($view); ?>> مشاهده انبار</label></td>
                                <td class="ssm-check-cell"><label><input type="checkbox" name="ssm_users[<?php echo esc_attr($u->ID); ?>][can_edit_stock]" value="1" <?php checked($stock); ?>> تغییر موجودی</label></td>
                                <td class="ssm-check-cell"><label><input type="checkbox" name="ssm_users[<?php echo esc_attr($u->ID); ?>][can_edit_price]" value="1" <?php checked($price); ?>> تغییر قیمت</label></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table></div>
                <p class="ssm-form-actions"><button type="submit" name="ssm_access_save" value="1" class="button button-primary button-hero">ذخیره تنظیمات دسترسی</button></p>
                </form>
                <?php endif; ?>
            </div></div>
            <?php
        }

        // چک مشترک AJAX: لاگین، دسترسی مشاهده، nonce، rate limit
        private function verify_request($is_write = false, $bucket = '') {
            if (!is_user_logged_in()) {
                wp_send_json_error(array('message' => 'ابتدا وارد حساب کاربری شوید.'));
            }

            // مدیر همیشه مجاز است — قبل از بقیه چک‌ها
            if ($this->is_plugin_admin()) {
                $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
                if ($nonce === '' || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
                    wp_send_json_error(array('message' => 'توکن امنیتی نامعتبر است. صفحه را یک‌بار رفرش (F5) کنید و دوباره تلاش کنید.'));
                }
                $bucket = $bucket !== '' ? sanitize_key($bucket) : ($is_write ? 'write' : 'read');
                $max    = $is_write ? self::RATE_LIMIT_WRITE : self::RATE_LIMIT_READ;
                $this->enforce_rate_limit($bucket, $max, self::RATE_WINDOW);
                return;
            }

            if (!$this->user_can_view_inventory()) {
                wp_send_json_error(array('message' => 'شما دسترسی مشاهده انبار ندارید.'));
            }

            // عملیات نوشتن بدون هیچ دسترسی ویرایش را همین‌جا قطع می‌کنیم
            if ($is_write && !$this->user_can_edit_stock() && !$this->user_can_edit_price()) {
                wp_send_json_error(array('message' => 'شما دسترسی تغییر موجودی یا قیمت ندارید.'));
            }

            $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
            if ($nonce === '' || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
                wp_send_json_error(array('message' => 'توکن امنیتی نامعتبر است. صفحه را یک‌بار رفرش (F5) کنید و دوباره تلاش کنید.'));
            }

            $bucket = $bucket !== '' ? sanitize_key($bucket) : ($is_write ? 'write' : 'read');
            $max    = $is_write ? self::RATE_LIMIT_WRITE : self::RATE_LIMIT_READ;
            $this->enforce_rate_limit($bucket, $max, self::RATE_WINDOW);
        }

        private function enforce_rate_limit($bucket, $max, $window_seconds) {
            $user_id = get_current_user_id();
            $key     = 'ssm_rl_' . $bucket . '_' . $user_id;
            $now     = time();
            $data    = get_transient($key);

            if (!is_array($data) || !isset($data['count'], $data['start'])) {
                set_transient($key, array('count' => 1, 'start' => $now), (int) $window_seconds);
                return;
            }

            $elapsed = $now - (int) $data['start'];
            if ($elapsed >= (int) $window_seconds) {
                set_transient($key, array('count' => 1, 'start' => $now), (int) $window_seconds);
                return;
            }

            if ((int) $data['count'] >= (int) $max) {
                wp_send_json_error(array(
                    'message' => 'تعداد درخواست‌ها زیاد است. چند لحظه صبر کنید و دوباره تلاش کنید.',
                ), 429);
            }

            $data['count'] = (int) $data['count'] + 1;
            $remaining     = max(1, (int) $window_seconds - $elapsed);
            set_transient($key, $data, $remaining);
        }

        // قفل کوتاه برای دوبار کلیک روی ذخیره / کم کردن
        private function acquire_action_lock($action, $product_id, $ttl = 2) {
            $key = 'ssm_lock_' . sanitize_key($action) . '_' . get_current_user_id() . '_' . absint($product_id);
            if (get_transient($key)) {
                wp_send_json_error(array(
                    'message' => 'درخواست قبلی هنوز در حال پردازش است. کمی صبر کنید.',
                ), 429);
            }
            set_transient($key, 1, max(1, (int) $ttl));
        }

        private function get_editable_product($product_id) {
            $product_id = absint($product_id);
            if (!$product_id) {
                return false;
            }

            $product = wc_get_product($product_id);
            if (!$product) {
                return false;
            }

            // فقط ساده و variation — خود محصول variable از اینجا ویرایش نمی‌شود
            $allowed_types = apply_filters('ssm_editable_product_types', array('simple', 'variation'));
            if (!in_array($product->get_type(), (array) $allowed_types, true)) {
                return false;
            }

            // trash و auto-draft را نمی‌گیریم
            $status = $product->get_status();
            $ok_status = apply_filters('ssm_editable_post_statuses', array('publish', 'private', 'draft', 'pending'));
            $ok_status = array_values(array_intersect((array) $ok_status, array('publish', 'private', 'draft', 'pending')));
            if (empty($ok_status)) {
                $ok_status = array('publish', 'private');
            }
            if (!in_array($status, $ok_status, true)) {
                return false;
            }

            return $product;
        }

        private function sanitize_stock_input($raw) {
            if ($raw === null || $raw === '') {
                return null;
            }
            $qty = wc_stock_amount(wp_unslash($raw));
            if ($qty === '' || !is_numeric($qty)) {
                return null;
            }
            $qty = (int) floor((float) $qty);
            if ($qty < 0) {
                $qty = 0;
            }
            if ($qty > 9999999) {
                $qty = 9999999;
            }
            return $qty;
        }

        private function sanitize_price_input($raw) {
            if ($raw === null || $raw === '') {
                return null;
            }
            $price = wc_format_decimal(wp_unslash($raw));
            if ($price === '' || !is_numeric($price)) {
                wp_send_json_error(array('message' => 'مقدار قیمت نامعتبر است.'));
            }
            $price = (float) $price;
            if ($price < 0) {
                wp_send_json_error(array('message' => 'قیمت نمی‌تواند منفی باشد.'));
            }
            // سقف برای جلوگیری از عددهای عجیب/خرابکارانه
            if ($price > 999999999) {
                wp_send_json_error(array('message' => 'مقدار قیمت بیش از حد مجاز است.'));
            }
            return wc_format_decimal($price);
        }

        private function get_request_ip() {
            // فقط REMOTE_ADDR — هدرهای X-Forwarded-For قابل جعل‌اند
            if (empty($_SERVER['REMOTE_ADDR'])) {
                return '';
            }
            $ip = sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR']));
            return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
        }

        private function resolve_stock_action($old, $new) {
            $old = (float) $old;
            $new = (float) $new;
            if ($new > $old) {
                return 'stock_increase';
            }
            if ($new < $old) {
                return 'stock_decrease';
            }
            return 'stock_update';
        }

        private function log_inventory_change($product_id, $field, $old_value, $new_value, $context = '', $operation_id = '') {
            if ($field === 'regular_price' || $field === 'sale_price' || $field === 'price') {
                $action = 'price_update';
            } elseif ($context === 'ajax_decrease' || $context === 'ajax_scan_bump') {
                $action = 'stock_decrease';
            } else {
                $action = $this->resolve_stock_action($old_value, $new_value);
            }

            $allowed_actions = array('stock_increase', 'stock_decrease', 'stock_update', 'price_update');
            if (!in_array($action, $allowed_actions, true)) {
                $action = 'stock_update';
            }

            $entry = array(
                'user_id'     => get_current_user_id(),
                'product_id'  => absint($product_id),
                'action_type' => $action,
                'field'       => sanitize_key((string) $field),
                'context'     => sanitize_key((string) $context),
                'old_value'   => sanitize_text_field((string) $old_value),
                'new_value'   => sanitize_text_field((string) $new_value),
                'created_at'  => current_time('mysql'),
                'ip'          => $this->get_request_ip(),
                'operation_id'=> sanitize_text_field((string) $operation_id),
            );

            if (function_exists('wc_get_logger')) {
                wc_get_logger()->info(
                    sprintf(
                        'Product #%d %s: %s → %s by user #%d (%s)',
                        $entry['product_id'],
                        $entry['action_type'],
                        $entry['old_value'],
                        $entry['new_value'],
                        $entry['user_id'],
                        sanitize_text_field($context)
                    ),
                    array(
                        'source'       => 'smart-stock-manager',
                        'operation_id' => $entry['operation_id'],
                        'field'        => $entry['field'],
                        'context'      => $entry['context'],
                        'product_id'   => $entry['product_id'],
                    )
                );
            }

            $log = get_option(self::AUDIT_LOG_OPTION, array());
            if (!is_array($log)) {
                $log = array();
            }
            array_unshift($log, $entry);
            $log = array_slice($log, 0, self::AUDIT_LOG_MAX);
            update_option(self::AUDIT_LOG_OPTION, $log, false);
        }

        public function ajax_search_products() {
            $this->verify_request(false, 'search');

            $keyword = isset($_POST['keyword']) ? sanitize_text_field(wp_unslash($_POST['keyword'])) : '';
            $keyword = trim($keyword);
            if (function_exists('mb_substr')) {
                $keyword = mb_substr($keyword, 0, 100);
            } else {
                $keyword = substr($keyword, 0, 100);
            }

            $page    = isset($_POST['page']) ? max(1, absint($_POST['page'])) : 1;
            $is_live = !empty($_POST['is_live']) ? 1 : 0;

            if ($keyword === '') {
                wp_send_json_error(array('message' => 'عبارت جستجو نمی‌تواند خالی باشد.'));
            }

            $min_chars = 3;
            $kw_len = function_exists('mb_strlen') ? mb_strlen($keyword) : strlen($keyword);
            if ($kw_len < $min_chars && !$this->keyword_looks_like_code($keyword)) {
                wp_send_json_error(array('message' => 'حداقل ۳ کاراکتر وارد کنید (یا یک SKU/کد کامل).'));
            }
            if ($is_live && $kw_len < $min_chars) {
                wp_send_json_error(array('message' => 'برای جستجوی زنده حداقل ۳ کاراکتر وارد کنید.'));
            }

            $search_data = $this->search_products_by_keyword($keyword, $page, self::SEARCH_LIMIT);
            $products    = $search_data['products'];
            $has_more    = $search_data['has_more'];
            $focus_vid   = isset($search_data['focus_variation_id']) ? absint($search_data['focus_variation_id']) : 0;

            if ($is_live) {
                if (empty($products) && $page === 1) {
                    wp_send_json_success(array(
                        'html' => '<li class="no-result">' . esc_html('موردی یافت نشد.') . '</li>',
                    ));
                }
                wp_send_json_success(array(
                    'html' => $this->generate_live_results_html($products, $has_more, $page),
                ));
            }

            if (empty($products) && $page === 1) {
                wp_send_json_success(array(
                    'html'     => '<div class="ssm-notice ssm-notice-warning">' . esc_html('هیچ محصولی با این مشخصات یافت نشد.') . '</div>',
                    'has_more' => false,
                    'page'     => $page,
                    'count'    => 0,
                ));
            }

            wp_send_json_success(array(
                'html'     => $this->generate_results_html($products, $has_more, $page, $focus_vid),
                'has_more' => (bool) $has_more,
                'page'     => $page,
                'count'    => count($products),
            ));
        }

        public function ajax_update_product_data() {
            $this->verify_request(true, 'update');

            $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
            $stock_qty  = $this->sanitize_stock_input(isset($_POST['stock_qty']) ? $_POST['stock_qty'] : null);
            $regular_price = null;
            if (isset($_POST['regular_price']) && $_POST['regular_price'] !== '') {
                $regular_price = $this->sanitize_price_input($_POST['regular_price']);
            }
            // کلید sale_price اگر ارسال شود حتی خالی = پاک کردن قیمت ویژه
            $sale_price_provided = array_key_exists('sale_price', $_POST);
            $sale_price = null;
            if ($sale_price_provided) {
                $raw_sale = isset($_POST['sale_price']) ? wp_unslash($_POST['sale_price']) : '';
                if ($raw_sale === '' || $raw_sale === null) {
                    $sale_price = '';
                } else {
                    $sale_price = $this->sanitize_price_input($raw_sale);
                }
            }

            if (!$product_id) {
                wp_send_json_error(array('message' => 'شناسه محصول نامعتبر است.'));
            }

            // چک دسترسی روی سرور
            if ($stock_qty !== null && !$this->user_can_edit_stock()) {
                wp_send_json_error(array('message' => 'شما دسترسی تغییر موجودی ندارید.'));
            }
            if (($regular_price !== null || $sale_price_provided) && !$this->user_can_edit_price()) {
                wp_send_json_error(array('message' => 'شما دسترسی تغییر قیمت ندارید.'));
            }
            if ($stock_qty === null && $regular_price === null && !$sale_price_provided) {
                wp_send_json_error(array('message' => 'هیچ مقداری برای ذخیره ارسال نشده است.'));
            }

            $this->acquire_action_lock('update', $product_id, 2);

            $product = $this->get_editable_product($product_id);
            if (!$product) {
                wp_send_json_error(array('message' => 'محصول قابل ویرایش یافت نشد.'));
            }

            $changed = false;
            $messages = array();

            if ($stock_qty !== null) {
                $old_stock_num = $product->get_manage_stock() ? (int) $product->get_stock_quantity() : 0;
                $new_stock_num = (int) $stock_qty;
                $stock_really_changed = (!$product->get_manage_stock()) || ($old_stock_num !== $new_stock_num);

                if ($stock_really_changed) {
                    $product->set_manage_stock(true);
                    $product->set_stock_quantity($new_stock_num);
                    $product->set_stock_status($new_stock_num > 0 ? 'instock' : 'outofstock');
                    $this->log_inventory_change($product_id, 'stock', (string) $old_stock_num, (string) $new_stock_num, 'ajax_update');
                    $changed = true;
                    $messages[] = 'موجودی';
                }
            }

            if ($regular_price !== null) {
                $old_price = wc_format_decimal((string) $product->get_regular_price());
                $new_price = wc_format_decimal((string) $regular_price);
                if ($old_price !== $new_price) {
                    $product->set_regular_price($new_price);
                    $this->log_inventory_change($product_id, 'regular_price', $old_price, $new_price, 'ajax_update');
                    $changed = true;
                    $messages[] = 'قیمت اصلی';
                }
            }

            if ($sale_price_provided) {
                $old_sale_raw = $product->get_sale_price();
                $old_sale = ($old_sale_raw === '' || $old_sale_raw === null) ? '' : wc_format_decimal((string) $old_sale_raw);
                $new_sale = ($sale_price === '') ? '' : wc_format_decimal((string) $sale_price);

                // قیمت ویژه نباید از قیمت اصلی بیشتر باشد
                $current_regular = $product->get_regular_price();
                if ($new_sale !== '' && $current_regular !== '' && $current_regular !== null) {
                    if ((float) $new_sale >= (float) wc_format_decimal((string) $current_regular)) {
                        wp_send_json_error(array('message' => 'قیمت ویژه باید کمتر از قیمت اصلی باشد.'));
                    }
                }

                if ($old_sale !== $new_sale) {
                    $product->set_sale_price($new_sale);
                    $this->log_inventory_change(
                        $product_id,
                        'sale_price',
                        $old_sale === '' ? '—' : $old_sale,
                        $new_sale === '' ? '—' : $new_sale,
                        'ajax_update'
                    );
                    $changed = true;
                    $messages[] = 'قیمت ویژه';
                }
            }

            // همگام‌سازی قیمت فعال فروشگاه (مثل رفتار ووکامرس)
            if ($changed && ($regular_price !== null || $sale_price_provided)) {
                $active_sale = $product->get_sale_price();
                if ($active_sale !== '' && $active_sale !== null) {
                    $reg = $product->get_regular_price();
                    if ($reg !== '' && $reg !== null && (float) $active_sale >= (float) wc_format_decimal((string) $reg)) {
                        $product->set_sale_price('');
                        $active_sale = '';
                    }
                }
                if ($active_sale !== '' && $active_sale !== null) {
                    $product->set_price($active_sale);
                } else {
                    $product->set_price($product->get_regular_price());
                }
            }

            if ($changed) {
                $product->save();
            }

            if (!$changed) {
                wp_send_json_success(array(
                    'message' => 'تغییری برای ذخیره وجود نداشت.',
                ));
            }

            wp_send_json_success(array(
                'message' => 'ذخیره شد: ' . implode(' و ', $messages) . '.',
            ));
        }

        // کارت محصول با کلیک روی نتیجه زنده
        public function ajax_get_product() {
            $this->verify_request(false, 'get_product');

            $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;

            if (!$product_id) {
                wp_send_json_error(array('message' => 'شناسه محصول نامعتبر است.'));
            }

            $product = wc_get_product($product_id);
            if (!$product || in_array($product->get_status(), array('trash', 'auto-draft'), true)) {
                wp_send_json_error(array('message' => 'محصول یافت نشد.'));
            }

            // برای variation خود والد را نشان بده
            if ($product->is_type('variation')) {
                $parent = wc_get_product($product->get_parent_id());
                if ($parent && !in_array($parent->get_status(), array('trash', 'auto-draft'), true)) {
                    $product = $parent;
                }
            }

            if (!in_array($product->get_type(), array('simple', 'variable'), true)) {
                wp_send_json_error(array('message' => 'نوع محصول پشتیبانی نمی‌شود.'));
            }

            wp_send_json_success(array(
                'html' => $this->generate_results_html(array($product), false, 1),
            ));
        }

        // پیش‌نمایش با SKU / بارکد / شناسه
        public function ajax_lookup_by_code() {
            $this->verify_request(false, 'lookup');

            $code = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';
            $code = trim($code);
            if (function_exists('mb_substr')) {
                $code = mb_substr($code, 0, 64);
            } else {
                $code = substr($code, 0, 64);
            }

            if ($code === '') {
                wp_send_json_error(array('message' => 'کد را وارد کنید.'));
            }

            $product = $this->find_product_by_code($code);

            if (!$product) {
                wp_send_json_error(array('message' => 'محصولی با این کد یافت نشد.'));
            }

            wp_send_json_success(array(
                'html' => $this->generate_code_preview_html($product),
            ));
        }

        /**
         * کاهش یک واحد موجودی
         */
        public function ajax_decrease_stock() {
            if (!is_user_logged_in()) {
                wp_send_json_error(array('message' => 'ابتدا وارد حساب کاربری شوید.'));
            }

            // مدیر / مدیر فروشگاه: بدون وابستگی به متای دسترسی
            $can = $this->is_plugin_admin() || $this->user_can_edit_stock();
            if (!$can) {
                wp_send_json_error(array('message' => 'شما دسترسی تغییر موجودی ندارید. از تنظیمات دسترسی افزونه، دسترسی موجودی را برای کاربر فعال کنید.'));
            }

            $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
            if ($nonce === '') {
                $nonce = isset($_POST['_ajax_nonce']) ? sanitize_text_field(wp_unslash($_POST['_ajax_nonce'])) : '';
            }
            if ($nonce === '' || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
                wp_send_json_error(array('message' => 'توکن امنیتی نامعتبر است. صفحه انبار را با Ctrl+F5 رفرش کنید و دوباره امتحان کنید.'));
            }

            $this->enforce_rate_limit('decrease', self::RATE_LIMIT_WRITE, self::RATE_WINDOW);

            $product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
            if (!$product_id) {
                wp_send_json_error(array('message' => 'شناسه محصول نامعتبر است.'));
            }

            $this->acquire_action_lock('decrease', $product_id, 2);

            $product = $this->get_editable_product($product_id);
            if (!$product) {
                wp_send_json_error(array('message' => 'محصول قابل ویرایش یافت نشد (فقط محصول ساده یا متغیر زیرمجموعه).'));
            }

            $current = $product->get_manage_stock() ? (int) $product->get_stock_quantity() : null;
            if ($current === null) {
                wp_send_json_error(array('message' => 'مدیریت موجودی این محصول فعال نیست. اول از باکس بالا موجودی را تنظیم و ذخیره کنید.'));
            }
            $new_qty = max(0, $current - 1);

            $product->set_manage_stock(true);
            $product->set_stock_quantity($new_qty);
            $product->set_stock_status($new_qty > 0 ? 'instock' : 'outofstock');
            $product->save();

            $this->log_inventory_change($product_id, 'stock', (string) $current, (string) $new_qty, 'ajax_decrease');

            wp_send_json_success(array(
                'message'   => 'یک واحد از موجودی کم شد.',
                'stock_qty' => $new_qty,
                'html'      => $this->generate_code_preview_html($product),
            ));
        }

        /**
         * اسکن + کاهش یک‌مرحله‌ای (پیدا کردن کد و −۱)
         */
        public function ajax_scan_and_decrease() {
            if (!is_user_logged_in()) {
                wp_send_json_error(array('message' => 'ابتدا وارد حساب کاربری شوید.'));
            }

            $can = $this->is_plugin_admin() || $this->user_can_edit_stock();
            if (!$can) {
                wp_send_json_error(array('message' => 'شما دسترسی تغییر موجودی ندارید.'));
            }

            $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
            if ($nonce === '') {
                $nonce = isset($_POST['_ajax_nonce']) ? sanitize_text_field(wp_unslash($_POST['_ajax_nonce'])) : '';
            }
            if ($nonce === '' || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
                wp_send_json_error(array('message' => 'توکن امنیتی نامعتبر است. صفحه انبار را با Ctrl+F5 رفرش کنید.'));
            }

            $this->enforce_rate_limit('scan_bump', self::RATE_LIMIT_WRITE, self::RATE_WINDOW);

            $code = isset($_POST['code']) ? sanitize_text_field(wp_unslash($_POST['code'])) : '';
            $code = trim($code);
            if (function_exists('mb_substr')) {
                $code = mb_substr($code, 0, 64);
            } else {
                $code = substr($code, 0, 64);
            }
            if ($code === '') {
                wp_send_json_error(array('message' => 'کد را وارد کنید.'));
            }

            $found = $this->find_product_by_code($code);
            if (!$found) {
                wp_send_json_error(array('message' => 'محصولی با این کد یافت نشد.'));
            }

            $product_id = (int) $found->get_id();
            $this->acquire_action_lock('decrease', $product_id, 2);

            $product = $this->get_editable_product($product_id);
            if (!$product) {
                wp_send_json_error(array('message' => 'محصول قابل ویرایش یافت نشد (فقط محصول ساده یا متغیر زیرمجموعه).'));
            }

            $current = $product->get_manage_stock() ? (int) $product->get_stock_quantity() : null;
            if ($current === null) {
                wp_send_json_error(array('message' => 'مدیریت موجودی این محصول فعال نیست. اول از باکس بالا موجودی را تنظیم و ذخیره کنید.'));
            }
            $new_qty = max(0, $current - 1);

            $product->set_manage_stock(true);
            $product->set_stock_quantity($new_qty);
            $product->set_stock_status($new_qty > 0 ? 'instock' : 'outofstock');
            $product->save();

            $this->log_inventory_change($product_id, 'stock', (string) $current, (string) $new_qty, 'ajax_scan_bump');

            wp_send_json_success(array(
                'message'   => 'یک واحد از موجودی کم شد.',
                'stock_qty' => $new_qty,
                'html'      => $this->generate_code_preview_html($product),
            ));
        }

        // کلیدهای متای بارکد که معمولاً تو فروشگاه‌ها هست
        private function get_barcode_meta_keys() {
            $keys = array(
                '_global_unique_id', // GTIN ووکامرس
                '_barcode',
                'barcode',
                '_ean',
                'ean',
                '_gtin',
                'gtin',
                '_upc',
                'hwp_product_gtin',
                '_alg_ean',
                'yith_barcode',
            );
            $keys = apply_filters('ssm_barcode_meta_keys', $keys);
            $keys = array_values(array_unique(array_filter(array_map('sanitize_key', (array) $keys))));
            return $keys;
        }

        // رنگ‌بندی چهارسطحی نسبت به آستانه فول: سبز / زرد / نارنجی / قرمز
        private function get_stock_level_class($qty) {
            if ($qty === null || $qty === '') {
                return 'stock-unknown';
            }
            $qty = (int) $qty;
            $threshold = $this->get_low_stock_threshold();
            if ($threshold < 1) {
                $threshold = 1;
            }
            $p75 = (int) ceil($threshold * 0.75);
            $p50 = (int) ceil($threshold * 0.5);
            if ($qty >= $threshold) {
                return 'stock-ok';
            }
            if ($qty >= $p75) {
                return 'stock-warn';
            }
            if ($qty >= $p50) {
                return 'stock-mid';
            }
            return 'stock-danger';
        }

        private function get_stock_level_label($qty) {
            $class = $this->get_stock_level_class($qty);
            if ($class === 'stock-ok') {
                return 'موجودی فول';
            }
            if ($class === 'stock-warn') {
                return 'موجودی نسبتاً کم';
            }
            if ($class === 'stock-mid') {
                return 'موجودی متوسط رو به پایین';
            }
            if ($class === 'stock-danger') {
                return 'موجودی بحرانی';
            }
            return '';
        }

        // اول exact روی SKU/بارکد، بعد اختیاری LIKE
        private function find_product_by_code($code, $allow_like = true) {
            global $wpdb;

            $code = trim((string) $code);
            if ($code === '') {
                return false;
            }

            $product_id = 0;

            // SKU از جدول lookup ووکامرس (قابل اعتمادتر از postmeta خام)
            $product_id = (int) wc_get_product_id_by_sku($code);

            if (!$product_id && function_exists('wc_get_product_id_by_global_unique_id')) {
                $product_id = (int) wc_get_product_id_by_global_unique_id($code);
            }

            if (!$product_id) {
                $keys = $this->get_barcode_meta_keys();
                if (!empty($keys)) {
                    $ph   = implode(',', array_fill(0, count($keys), '%s'));
                    $args = array_merge($keys, array($code));
                    $sql  = "SELECT post_id FROM {$wpdb->postmeta}
                             WHERE meta_key IN ({$ph}) AND meta_value = %s
                             ORDER BY post_id DESC LIMIT 1";
                    $product_id = (int) $wpdb->get_var($wpdb->prepare($sql, ...$args));
                }
            }

            if (!$product_id && ctype_digit($code)) {
                $maybe = absint($code);
                $post_type = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT post_type FROM {$wpdb->posts} WHERE ID = %d AND post_type IN ('product','product_variation') AND post_status NOT IN ('trash','auto-draft') LIMIT 1",
                        $maybe
                    )
                );
                if ($post_type) {
                    $product_id = $maybe;
                }
            }

            if (!$product_id && $allow_like) {
                $like = '%' . $wpdb->esc_like($code) . '%';
                $lookup = $wpdb->prefix . 'wc_product_meta_lookup';
                $lookup_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($lookup))) === $lookup);
                if ($lookup_exists) {
                    $product_id = (int) $wpdb->get_var(
                        $wpdb->prepare(
                            "SELECT product_id FROM {$lookup} WHERE sku LIKE %s ORDER BY (sku = %s) DESC, product_id DESC LIMIT 1",
                            $like,
                            $code
                        )
                    );
                }
                if (!$product_id) {
                    $keys = array_merge(array('_sku'), $this->get_barcode_meta_keys());
                    $keys = array_values(array_unique($keys));
                    $ph   = implode(',', array_fill(0, count($keys), '%s'));
                    $args = array_merge($keys, array($like, $code));
                    $sql  = "SELECT post_id FROM {$wpdb->postmeta}
                             WHERE meta_key IN ({$ph}) AND meta_value LIKE %s
                             ORDER BY (meta_value = %s) DESC, post_id DESC
                             LIMIT 1";
                    $product_id = (int) $wpdb->get_var($wpdb->prepare($sql, ...$args));
                }
            }

            if (!$product_id) {
                return false;
            }

            $product = wc_get_product($product_id);
            if (!$product || in_array($product->get_status(), array('trash', 'auto-draft'), true)) {
                return false;
            }
            return $product;
        }

        // محصول نمایشی برای نتایج بالا: variation → والد
        private function resolve_search_display_product($product) {
            $focus_variation_id = 0;
            if ($product && $product->is_type('variation')) {
                $focus_variation_id = $product->get_id();
                $parent = wc_get_product($product->get_parent_id());
                if ($parent && !in_array($parent->get_status(), array('trash', 'auto-draft'), true)) {
                    return array($parent, $focus_variation_id);
                }
            }
            return array($product, $focus_variation_id);
        }

        // اگر عبارت شبیه کد/SKU باشد (بدون فاصله) exact را اول می‌زنیم؛ عنوان فارسی با فاصله می‌رود سراغ جستجوی عنوان
        private function keyword_looks_like_code($keyword) {
            $keyword = trim((string) $keyword);
            if ($keyword === '') {
                return false;
            }
            if (preg_match('/\s/u', $keyword)) {
                return false;
            }
            $len = function_exists('mb_strlen') ? mb_strlen($keyword) : strlen($keyword);
            return $len <= 64;
        }

        // جستجو: کد/SKU/بارکد + عنوان محصول (ساده و متغیر)
        private function search_products_by_keyword($keyword, $page = 1, $limit = 10) {
            global $wpdb;

            $page   = max(1, (int) $page);
            $limit  = max(1, (int) $limit);
            $offset = ($page - 1) * $limit;
            $keyword = trim($keyword);

            // فقط وقتی شبیه کد است؛ عنوان محصول را اشتباهی با SKU قاطی نکن
            if ($page === 1 && $this->keyword_looks_like_code($keyword)) {
                $exact = $this->find_product_by_code($keyword, false);
                if ($exact) {
                    list($display, $focus_vid) = $this->resolve_search_display_product($exact);
                    if ($display) {
                        return array(
                            'products'            => array($display),
                            'has_more'            => false,
                            'focus_variation_id'  => $focus_vid,
                        );
                    }
                }
            }

            $like   = '%' . $wpdb->esc_like($keyword) . '%';
            $id_val = ctype_digit($keyword) ? (int) $keyword : 0;

            $allowed_statuses = array('publish', 'private', 'draft', 'pending');
            $statuses         = apply_filters('ssm_search_post_statuses', $allowed_statuses);
            $statuses         = array_values(array_intersect((array) $statuses, $allowed_statuses));
            if (empty($statuses)) {
                $statuses = array('publish', 'private');
            }

            $max_rows = (int) apply_filters('ssm_search_max_rows', 300);
            $max_rows = max(10, min(500, $max_rows));

            $barcode_keys = $this->get_barcode_meta_keys();
            $status_placeholders = implode(',', array_fill(0, count($statuses), '%s'));

            $lookup = $wpdb->prefix . 'wc_product_meta_lookup';
            $lookup_exists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($lookup))) === $lookup);

            $lookup_join  = '';
            $lookup_where = '';
            $lookup_args  = array();
            if ($lookup_exists) {
                $lookup_join  = "LEFT JOIN {$lookup} AS luk ON ( p.ID = luk.product_id )";
                $lookup_where = ' OR luk.sku LIKE %s OR luk.sku = %s ';
                $lookup_args  = array($like, $keyword);
            }

            $barcode_join = '';
            $barcode_where = '';
            $barcode_join_args = array();
            $barcode_where_args = array();
            if (!empty($barcode_keys)) {
                $bph = implode(',', array_fill(0, count($barcode_keys), '%s'));
                // placeholderهای JOIN باید قبل از WHERE در prepare بیایند
                $barcode_join  = "LEFT JOIN {$wpdb->postmeta} AS bc ON ( p.ID = bc.post_id AND bc.meta_key IN ({$bph}) )";
                $barcode_where = ' OR bc.meta_value LIKE %s OR bc.meta_value = %s ';
                $barcode_join_args  = $barcode_keys;
                $barcode_where_args = array($like, $keyword);
            }

            // عنوان خود محصول + عنوان والد برای variation
            $sql = "
                SELECT p.ID, p.post_type, p.post_parent
                FROM {$wpdb->posts} AS p
                LEFT JOIN {$wpdb->posts} AS parent
                    ON ( p.post_type = 'product_variation' AND parent.ID = p.post_parent )
                LEFT JOIN {$wpdb->postmeta} AS sku
                    ON ( p.ID = sku.post_id AND sku.meta_key = '_sku' )
                {$lookup_join}
                {$barcode_join}
                WHERE p.post_type IN ( 'product', 'product_variation' )
                    AND p.post_status IN ( {$status_placeholders} )
                    AND (
                        p.post_title LIKE %s
                        OR parent.post_title LIKE %s
                        OR sku.meta_value LIKE %s
                        OR sku.meta_value = %s
                        OR p.ID = %d
                        {$lookup_where}
                        {$barcode_where}
                    )
                GROUP BY p.ID
                ORDER BY
                    (p.post_title LIKE %s OR parent.post_title LIKE %s) DESC,
                    (sku.meta_value = %s OR p.ID = %d) DESC,
                    (p.post_type = 'product') DESC,
                    p.ID DESC
                LIMIT %d
            ";

            // ترتیب آرگومان = ترتیب ظاهر شدن %s در کوئری
            $prepare_args = array_merge(
                $barcode_join_args,
                $statuses,
                array($like, $like, $like, $keyword, $id_val),
                $lookup_args,
                $barcode_where_args,
                array($like, $like, $keyword, $id_val, $max_rows)
            );

            $rows = $wpdb->get_results($wpdb->prepare($sql, ...$prepare_args));

            $ordered_ids = array();
            if (!empty($rows)) {
                foreach ($rows as $row) {
                    $target_id = ($row->post_type === 'product_variation' && $row->post_parent)
                        ? (int) $row->post_parent
                        : (int) $row->ID;

                    if ($target_id && !in_array($target_id, $ordered_ids, true)) {
                        $ordered_ids[] = $target_id;
                    }
                }
            }

            // اگر SQL چیزی نداد، با جستجوی عنوان وردپرس دوباره امتحان کن (از ۳ حرف)
            if (empty($ordered_ids)) {
                $title_ids = $this->search_products_by_title_fallback($keyword, $statuses, $max_rows);
                foreach ($title_ids as $tid) {
                    if ($tid && !in_array($tid, $ordered_ids, true)) {
                        $ordered_ids[] = $tid;
                    }
                }
            }

            $total    = count($ordered_ids);
            $page_ids = array_slice($ordered_ids, $offset, $limit);
            $has_more = $total > ($offset + $limit);

            $products = array();
            foreach ($page_ids as $product_id) {
                $product = wc_get_product($product_id);
                if ($product) {
                    $products[] = $product;
                }
            }

            return array(
                'products'           => $products,
                'has_more'           => $has_more,
                'focus_variation_id' => 0,
            );
        }

        // جستجوی عنوان با WP_Query — پشتیبان برای وقتی کوئری مستقیم نتیجه ندهد
        private function search_products_by_title_fallback($keyword, $statuses, $limit = 50) {
            $keyword = trim((string) $keyword);
            if ($keyword === '') {
                return array();
            }

            $q = new WP_Query(array(
                'post_type'              => 'product',
                'post_status'            => $statuses,
                's'                      => $keyword,
                'posts_per_page'         => max(1, min(100, (int) $limit)),
                'fields'                 => 'ids',
                'orderby'                => 'relevance',
                'no_found_rows'          => true,
                'update_post_meta_cache' => false,
                'update_post_term_cache' => false,
                'suppress_filters'       => false,
            ));

            $ids = !empty($q->posts) ? array_map('absint', $q->posts) : array();

            // جستجوی مستقیم عنوان با LIKE هم — بعضی سایت‌ها س جستجوی WP را محدود می‌کنند
            if (empty($ids)) {
                global $wpdb;
                $like = '%' . $wpdb->esc_like($keyword) . '%';
                $status_placeholders = implode(',', array_fill(0, count($statuses), '%s'));
                $sql = "SELECT ID FROM {$wpdb->posts}
                        WHERE post_type = 'product'
                          AND post_status IN ({$status_placeholders})
                          AND post_title LIKE %s
                        ORDER BY ID DESC
                        LIMIT %d";
                $args = array_merge($statuses, array($like, max(1, min(100, (int) $limit))));
                $found = $wpdb->get_col($wpdb->prepare($sql, ...$args));
                if (!empty($found)) {
                    $ids = array_map('absint', $found);
                }
            }

            return $ids;
        }

        private function generate_code_preview_html($product) {
            $is_variation = $product->is_type('variation');
            $parent_id    = $is_variation ? $product->get_parent_id() : 0;
            $parent       = $parent_id ? wc_get_product($parent_id) : null;

            $image_id  = $product->get_image_id();
            if (!$image_id && $parent) {
                $image_id = $parent->get_image_id();
            }
            $image_url = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : wc_placeholder_img_src();

            $title = $is_variation
                ? $this->get_friendly_variation_name($product)
                : $product->get_name();

            if ($is_variation && $parent) {
                $title = $parent->get_name() . ' — ' . $title;
            }

            $sku   = $product->get_sku();
            $stock = $product->get_manage_stock() ? (int) $product->get_stock_quantity() : null;
            $price = $product->get_regular_price();
            $stock_label = ($stock === null)
                ? ($product->is_in_stock() ? 'موجود' : 'ناموجود')
                : (string) $stock;
            $stock_class = ($stock === null)
                ? ($product->is_in_stock() ? 'stock-ok' : 'stock-danger')
                : $this->get_stock_level_class($stock);
            $stock_hint = ($stock === null) ? '' : $this->get_stock_level_label($stock);

            // بارکد اگر ذخیره شده باشد
            $barcode = '';
            if (method_exists($product, 'get_global_unique_id')) {
                $barcode = (string) $product->get_global_unique_id();
            }
            if ($barcode === '') {
                foreach ($this->get_barcode_meta_keys() as $bkey) {
                    if ($bkey === '_global_unique_id') {
                        continue;
                    }
                    $val = $product->get_meta($bkey, true);
                    if ($val !== '' && $val !== null) {
                        $barcode = (string) $val;
                        break;
                    }
                }
            }

            ob_start();
            ?>
            <div class="ssm-quick-card" data-product-id="<?php echo esc_attr($product->get_id()); ?>">
                <img class="ssm-quick-thumb" src="<?php echo esc_url($image_url); ?>" alt="">
                <div class="ssm-quick-info">
                    <strong class="ssm-quick-title"><?php echo esc_html($title); ?></strong>
                    <div class="ssm-quick-meta">
                        <span>شناسه: <?php echo esc_html($product->get_id()); ?></span>
                        <span>SKU: <?php echo esc_html($sku ? $sku : '—'); ?></span>
                        <?php if ($barcode !== '') : ?>
                            <span>بارکد: <?php echo esc_html($barcode); ?></span>
                        <?php endif; ?>
                        <?php if ($price !== '' && $price !== null) : ?>
                            <span>قیمت: <?php echo esc_html($price); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="ssm-quick-stock <?php echo esc_attr($stock_class); ?>" title="<?php echo esc_attr($stock_hint); ?>">
                    <span class="ssm-quick-stock-label">موجودی<?php echo $stock_hint ? ' — ' . esc_html($stock_hint) : ''; ?></span>
                    <span class="ssm-quick-stock-value"><?php echo esc_html($stock_label); ?></span>
                </div>
                <?php if ($this->user_can_edit_stock()) : ?>
                <button type="button" class="button ssm-decrease-btn" data-product-id="<?php echo esc_attr($product->get_id()); ?>">
                    کم کن (−۱)
                </button>
                <?php else : ?>
                <span class="ssm-perm-hint ssm-perm-hint-inline">شما دسترسی تغییر موجودی ندارید</span>
                <?php endif; ?>
            </div>
            <?php
            return ob_get_clean();
        }

        // اسم خوانای variation (مخصوصاً وقتی اسلاگ فارسی URL-encoded باشه)
        private function get_friendly_variation_name($variation) {
            if (function_exists('wc_get_formatted_variation')) {
                $formatted = wc_get_formatted_variation($variation, true, true, false);
                $formatted = trim(wp_strip_all_tags(html_entity_decode($formatted)));

                if ($formatted !== '') {
                    $formatted = str_replace(array('، ', ', '), ' | ', $formatted);
                    return $formatted;
                }
            }

            $parent_id  = $variation->get_parent_id();
            $parent     = $parent_id ? wc_get_product($parent_id) : $variation;
            $attributes = $variation->get_variation_attributes();
            $name_parts = array();

            foreach ($attributes as $attribute_key => $value) {
                if ($value === '' || $value === null) {
                    continue;
                }

                $taxonomy_or_name = urldecode(str_replace('attribute_', '', $attribute_key));
                $attribute_label  = wc_attribute_label($taxonomy_or_name, $parent);

                if (taxonomy_exists($taxonomy_or_name)) {
                    $term       = get_term_by('slug', $value, $taxonomy_or_name);
                    $value_name = $term ? $term->name : urldecode($value);
                } else {
                    $value_name = urldecode($value);
                }

                $name_parts[] = $attribute_label . ': ' . $value_name;
            }

            if (!empty($name_parts)) {
                return implode(' | ', $name_parts);
            }

            return urldecode($variation->get_name());
        }

        private function get_stock_badge_html($product) {
            if ($product->get_manage_stock()) {
                $qty   = (int) $product->get_stock_quantity();
                $class = $this->get_stock_level_class($qty);
                $hint  = $this->get_stock_level_label($qty);
                if ($qty <= 0) {
                    return '<span class="ssm-stock-badge stock-danger" title="' . esc_attr($hint) . '">ناموجود (۰)</span>';
                }
                return '<span class="ssm-stock-badge ' . esc_attr($class) . '" title="' . esc_attr($hint) . '">موجودی: ' . esc_html($qty) . '</span>';
            }

            if ($product->is_in_stock()) {
                return '<span class="ssm-stock-badge stock-ok">موجود</span>';
            }
            return '<span class="ssm-stock-badge stock-danger">ناموجود</span>';
        }

        private function generate_live_results_html($products, $has_more = false, $page = 1) {
            ob_start();

            foreach ($products as $product) {
                $product_id  = $product->get_id();
                $sku         = $product->get_sku();
                $image_id    = $product->get_image_id();
                $image_url   = $image_id ? wp_get_attachment_image_url($image_id, 'thumbnail') : wc_placeholder_img_src();
                $is_variable = $product->is_type('variable');
                ?>
                <li class="ssm-live-group">
                    <a href="#" class="ssm-live-item" data-load-id="<?php echo esc_attr($product_id); ?>">
                        <img class="ssm-live-thumb" src="<?php echo esc_url($image_url); ?>" alt="" loading="lazy">
                        <span class="ssm-live-info">
                            <span class="ssm-live-name"><?php echo esc_html($product->get_name()); ?></span>
                            <span class="ssm-live-meta">
                                <?php if ($sku) : ?>
                                    <span class="ssm-live-sku">SKU: <?php echo esc_html($sku); ?></span>
                                <?php endif; ?>
                                <span class="ssm-live-price"><?php echo wp_kses_post($product->get_price_html()); ?></span>
                            </span>
                        </span>
                        <span class="ssm-live-side">
                            <?php if ($is_variable) : ?>
                                <span class="ssm-type-badge">متغیر</span>
                            <?php else : ?>
                                <?php echo $this->get_stock_badge_html($product); ?>
                            <?php endif; ?>
                        </span>
                    </a>

                    <?php if ($is_variable) : ?>
                        <ul class="ssm-live-variations">
                            <?php
                            foreach ($product->get_children() as $variation_id) :
                                $variation = wc_get_product($variation_id);
                                if (!$variation) {
                                    continue;
                                }
                                $var_image_id  = $variation->get_image_id();
                                $var_image_url = $var_image_id ? wp_get_attachment_image_url($var_image_id, 'thumbnail') : $image_url;
                                ?>
                                <li>
                                    <a href="#" class="ssm-live-item ssm-live-subitem" data-load-id="<?php echo esc_attr($product_id); ?>">
                                        <img class="ssm-live-thumb ssm-live-thumb-sm" src="<?php echo esc_url($var_image_url); ?>" alt="" loading="lazy">
                                        <span class="ssm-live-info">
                                            <span class="ssm-live-name"><?php echo esc_html($this->get_friendly_variation_name($variation)); ?></span>
                                            <span class="ssm-live-meta">
                                                <span class="ssm-live-price"><?php echo wp_kses_post($variation->get_price_html()); ?></span>
                                            </span>
                                        </span>
                                        <span class="ssm-live-side">
                                            <?php echo $this->get_stock_badge_html($variation); ?>
                                        </span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                </li>
                <?php
            }

            if ($has_more) {
                ?>
                <li class="ssm-load-more-trigger" data-next-page="<?php echo esc_attr($page + 1); ?>">
                    نمایش موارد بیشتر...
                </li>
                <?php
            }

            return ob_get_clean();
        }

        private function generate_results_html($products, $has_more = false, $page = 1, $focus_variation_id = 0) {
            ob_start();
            $focus_variation_id = absint($focus_variation_id);

            foreach ($products as $product) {
                $product_id = $product->get_id();
                $parent_image_id = $product->get_image_id();
                $parent_image_url = $parent_image_id ? wp_get_attachment_image_url($parent_image_id, 'thumbnail') : wc_placeholder_img_src();
                $sku = $product->get_sku();
                ?>
                <div class="ssm-product-card" data-product-id="<?php echo esc_attr($product_id); ?>" data-ssm-type="<?php echo $product->is_type('variable') ? 'variable' : 'simple'; ?>">
                    <div class="ssm-product-header">
                        <img class="ssm-product-image" src="<?php echo esc_url($parent_image_url); ?>" alt="<?php echo esc_attr($product->get_name()); ?>">
                        <div class="ssm-product-meta">
                            <h3><?php echo esc_html($product->get_name()); ?></h3>
                            <div class="ssm-badge-container">
                                <span class="ssm-badge">شناسه: <?php echo esc_html($product_id); ?></span>
                                <span class="ssm-badge">کد محصول (SKU): <?php echo esc_html($sku ? $sku : 'تعریف نشده'); ?></span>
                            </div>
                        </div>
                    </div>

                    <?php if ($product->is_type('variable')) : ?>
                        <div class="ssm-variations">
                            <h4>لیست متغیرها</h4>
                            <?php
                            $children_ids = $product->get_children();

                            foreach ($children_ids as $variation_id) :
                                $variation = wc_get_product($variation_id);
                                if (!$variation) {
                                    continue;
                                }

                                // تصویر خود variation، وگرنه تصویر والد
                                $var_image_id = $variation->get_image_id();
                                $var_image_url = $var_image_id ? wp_get_attachment_image_url($var_image_id, 'thumbnail') : $parent_image_url;
                                $row_class = 'ssm-variation-row';
                                if ($focus_variation_id && (int) $variation_id === $focus_variation_id) {
                                    $row_class .= ' ssm-variation-focus';
                                }
                                ?>
                                <div class="<?php echo esc_attr($row_class); ?>"
                                     data-product-id="<?php echo esc_attr($variation_id); ?>"
                                     id="ssm-var-<?php echo esc_attr($variation_id); ?>"
                                     data-ssm-stock="<?php echo esc_attr((int) $variation->get_stock_quantity()); ?>"
                                     data-ssm-sale="<?php echo ($variation->get_sale_price() !== '' && $variation->get_sale_price() !== null) ? '1' : '0'; ?>">
                                    <div class="ssm-variation-info">
                                        <img class="ssm-variation-image" src="<?php echo esc_url($var_image_url); ?>" alt="">
                                        <div class="ssm-variation-text">
                                            <strong><?php echo esc_html($this->get_friendly_variation_name($variation)); ?></strong>
                                            <span>SKU: <?php echo esc_html($variation->get_sku() ? $variation->get_sku() : '—'); ?></span>
                                        </div>
                                    </div>
                                    <div class="ssm-fields-group">
                                        <?php
                                        $can_stock = $this->user_can_edit_stock();
                                        $can_price = $this->user_can_edit_price();
                                        ?>
                                        <div class="ssm-input-wrapper">
                                            <label>موجودی انبار</label>
                                            <?php
                                            $v_qty = (int) $variation->get_stock_quantity();
                                            $v_lvl = $this->get_stock_level_class($v_qty);
                                            ?>
                                            <?php if ($can_stock) : ?>
                                            <div class="ssm-stepper ssm-stock-level <?php echo esc_attr($v_lvl); ?>" title="<?php echo esc_attr($this->get_stock_level_label($v_qty)); ?>">
                                                <button type="button" class="ssm-step ssm-step-down" tabindex="-1" aria-label="کاهش موجودی">&minus;</button>
                                                <input type="number" class="ssm-stock-qty" value="<?php echo esc_attr($v_qty); ?>" min="0" max="9999999" step="1" data-original="<?php echo esc_attr($v_qty); ?>">
                                                <button type="button" class="ssm-step ssm-step-up" tabindex="-1" aria-label="افزایش موجودی">+</button>
                                            </div>
                                            <?php else : ?>
                                            <input type="number" class="ssm-stock-qty ssm-stock-level <?php echo esc_attr($v_lvl); ?>" value="<?php echo esc_attr($v_qty); ?>" disabled readonly>
                                            <span class="ssm-perm-hint">شما دسترسی تغییر موجودی ندارید</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="ssm-input-wrapper">
                                            <label>قیمت اصلی</label>
                                            <input type="number" class="ssm-regular-price" value="<?php echo esc_attr($variation->get_regular_price()); ?>" min="0" step="any" data-original="<?php echo esc_attr($variation->get_regular_price()); ?>" <?php disabled(!$can_price); ?> <?php echo !$can_price ? 'readonly' : ''; ?>>
                                            <?php if (!$can_price) : ?>
                                            <span class="ssm-perm-hint">شما دسترسی تغییر قیمت ندارید</span>
                                            <?php endif; ?>
                                        </div>
                                        <div class="ssm-input-wrapper">
                                            <label>قیمت ویژه</label>
                                            <?php
                                            $v_sale = $variation->get_sale_price();
                                            $v_sale_val = ($v_sale === '' || $v_sale === null) ? '' : $v_sale;
                                            ?>
                                            <input type="number" class="ssm-sale-price" value="<?php echo esc_attr($v_sale_val); ?>" min="0" step="any" placeholder="خالی = بدون تخفیف" data-original="<?php echo esc_attr($v_sale_val); ?>" <?php disabled(!$can_price); ?> <?php echo !$can_price ? 'readonly' : ''; ?>>
                                        </div>
                                        <?php if ($can_stock || $can_price) : ?>
                                        <button type="button" class="button button-primary ssm-save-button">ذخیره تغییرات</button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else : ?>
                        <?php
                        $p_qty = (int) $product->get_stock_quantity();
                        $p_sale = $product->get_sale_price();
                        $p_on_sale = ($p_sale !== '' && $p_sale !== null) ? '1' : '0';
                        ?>
                        <div class="ssm-fields-group" data-ssm-stock="<?php echo esc_attr($p_qty); ?>" data-ssm-sale="<?php echo esc_attr($p_on_sale); ?>">
                            <?php
                            $can_stock = $this->user_can_edit_stock();
                            $can_price = $this->user_can_edit_price();
                            ?>
                            <div class="ssm-input-wrapper">
                                <label>موجودی انبار</label>
                                <?php
                                $p_lvl = $this->get_stock_level_class($p_qty);
                                ?>
                                <?php if ($can_stock) : ?>
                                <div class="ssm-stepper ssm-stock-level <?php echo esc_attr($p_lvl); ?>" title="<?php echo esc_attr($this->get_stock_level_label($p_qty)); ?>">
                                    <button type="button" class="ssm-step ssm-step-down" tabindex="-1" aria-label="کاهش موجودی">&minus;</button>
                                    <input type="number" class="ssm-stock-qty" value="<?php echo esc_attr($p_qty); ?>" min="0" max="9999999" step="1" data-original="<?php echo esc_attr($p_qty); ?>">
                                    <button type="button" class="ssm-step ssm-step-up" tabindex="-1" aria-label="افزایش موجودی">+</button>
                                </div>
                                <?php else : ?>
                                <input type="number" class="ssm-stock-qty ssm-stock-level <?php echo esc_attr($p_lvl); ?>" value="<?php echo esc_attr($p_qty); ?>" disabled readonly>
                                <span class="ssm-perm-hint">شما دسترسی تغییر موجودی ندارید</span>
                                <?php endif; ?>
                            </div>
                            <div class="ssm-input-wrapper">
                                <label>قیمت اصلی</label>
                                <input type="number" class="ssm-regular-price" value="<?php echo esc_attr($product->get_regular_price()); ?>" min="0" step="any" data-original="<?php echo esc_attr($product->get_regular_price()); ?>" <?php disabled(!$can_price); ?> <?php echo !$can_price ? 'readonly' : ''; ?>>
                                <?php if (!$can_price) : ?>
                                <span class="ssm-perm-hint">شما دسترسی تغییر قیمت ندارید</span>
                                <?php endif; ?>
                            </div>
                            <div class="ssm-input-wrapper">
                                <label>قیمت ویژه</label>
                                <?php
                                $p_sale = $product->get_sale_price();
                                $p_sale_val = ($p_sale === '' || $p_sale === null) ? '' : $p_sale;
                                ?>
                                <input type="number" class="ssm-sale-price" value="<?php echo esc_attr($p_sale_val); ?>" min="0" step="any" placeholder="خالی = بدون تخفیف" data-original="<?php echo esc_attr($p_sale_val); ?>" <?php disabled(!$can_price); ?> <?php echo !$can_price ? 'readonly' : ''; ?>>
                            </div>
                            <?php if ($can_stock || $can_price) : ?>
                            <button type="button" class="button button-primary ssm-save-button">ذخیره تغییرات</button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <?php
            }

            if ($has_more) {
                ?>
                <div class="ssm-load-more-wrap" data-next-page="<?php echo esc_attr($page + 1); ?>">
                    <button type="button" class="button ssm-load-more-button">نمایش ۱۰ مورد بعدی</button>
                </div>
                <?php
            }

            return ob_get_clean();
        }

        // ——— ویرایش گروهی قیمت ———

        private function get_store_money_unit() {
            $forced = apply_filters('ssm_store_money_unit', null);
            if ($forced === 'toman' || $forced === 'rial') {
                return $forced;
            }

            $currency = strtoupper((string) (function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : ''));
            $symbol = '';
            if (function_exists('get_woocommerce_currency_symbol')) {
                $symbol = html_entity_decode((string) get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8');
            }

            if ($symbol !== '') {
                if (function_exists('mb_stripos')) {
                    if (mb_stripos($symbol, 'تومان') !== false || mb_stripos($symbol, 'toman') !== false) {
                        return 'toman';
                    }
                    if (mb_stripos($symbol, 'ریال') !== false || mb_stripos($symbol, 'rial') !== false) {
                        return 'rial';
                    }
                } else {
                    if (stripos($symbol, 'toman') !== false) {
                        return 'toman';
                    }
                    if (stripos($symbol, 'rial') !== false) {
                        return 'rial';
                    }
                }
            }

            if (in_array($currency, array('IRT', 'TMN', 'TOMAN'), true)) {
                return 'toman';
            }
            if ($currency === 'IRR') {
                return 'rial';
            }

            // پیش‌فرض رایج فروشگاه‌های فارسی: تومان
            return apply_filters('ssm_store_money_unit_default', 'toman');
        }

        private function convert_fixed_amount_to_store($amount, $input_unit) {
            $amount = (float) $amount;
            $input_unit = ($input_unit === 'rial') ? 'rial' : 'toman';
            $store = $this->get_store_money_unit();
            if ($store === $input_unit) {
                return $amount;
            }
            if ($input_unit === 'toman' && $store === 'rial') {
                return $amount * 10;
            }
            return $amount / 10;
        }

        private function parse_bulk_price_request() {
            $field = isset($_POST['price_field']) ? sanitize_key(wp_unslash($_POST['price_field'])) : '';
            if (!in_array($field, array('regular', 'sale'), true)) {
                return new WP_Error('bad_field', 'نوع قیمت نامعتبر است.');
            }
            $direction = isset($_POST['direction']) ? sanitize_key(wp_unslash($_POST['direction'])) : '';
            if (!in_array($direction, array('increase', 'decrease'), true)) {
                return new WP_Error('bad_direction', 'نوع تغییر نامعتبر است.');
            }
            $mode = isset($_POST['mode']) ? sanitize_key(wp_unslash($_POST['mode'])) : '';
            if (!in_array($mode, array('percent', 'fixed'), true)) {
                return new WP_Error('bad_mode', 'روش محاسبه نامعتبر است.');
            }
            $amount_raw = isset($_POST['amount']) ? wp_unslash($_POST['amount']) : '';
            $amount_raw = is_string($amount_raw) ? str_replace(array(',', '،', ' '), '', $amount_raw) : $amount_raw;
            $amount = is_numeric($amount_raw) ? (float) $amount_raw : -1;
            $unit = isset($_POST['unit']) ? sanitize_key(wp_unslash($_POST['unit'])) : '';
            if (!in_array($unit, array('toman', 'rial'), true)) {
                return new WP_Error('bad_unit', 'واحد مبلغ نامعتبر است.');
            }
            $scope = isset($_POST['scope']) ? sanitize_key(wp_unslash($_POST['scope'])) : '';
            if (!in_array($scope, array('all', 'simple', 'variation', 'on_sale'), true)) {
                return new WP_Error('bad_scope', 'محدوده محصولات نامعتبر است.');
            }
            $category_raw = isset($_POST['category']) ? wp_unslash($_POST['category']) : '';
            if (!is_scalar($category_raw) || !ctype_digit((string) $category_raw)) {
                return new WP_Error('bad_category', 'دسته‌بندی نامعتبر است.');
            }
            $category = absint($category_raw);
            if ($category > 0) {
                $term = get_term($category, 'product_cat');
                if (!$term || is_wp_error($term) || empty($term->slug)) {
                    return new WP_Error('bad_category', 'دسته‌بندی انتخاب‌شده وجود ندارد.');
                }
            }
            $empty_sale = isset($_POST['empty_sale']) ? sanitize_key(wp_unslash($_POST['empty_sale'])) : '';
            if (!in_array($empty_sale, array('skip', 'from_regular'), true)) {
                return new WP_Error('bad_empty_sale', 'رفتار قیمت ویژه خالی نامعتبر است.');
            }

            if ($amount < 0 || !is_finite($amount) || ($mode === 'percent' && $amount > 1000)) {
                return new WP_Error('bad_amount', 'مقدار نامعتبر است.');
            }
            if ($mode === 'percent' && $amount == 0.0) {
                return new WP_Error('bad_amount', 'درصد نمی‌تواند صفر باشد.');
            }
            if ($mode === 'fixed' && $amount == 0.0) {
                return new WP_Error('bad_amount', 'مبلغ ثابت نمی‌تواند صفر باشد.');
            }

            $store_amount = ($mode === 'fixed') ? $this->convert_fixed_amount_to_store($amount, $unit) : $amount;
            if (!is_finite($store_amount) || ($mode === 'fixed' && $store_amount > 999999999)) {
                return new WP_Error('bad_amount', 'مبلغ ثابت بیش از حد مجاز است.');
            }

            return array(
                'field'        => $field,
                'direction'    => $direction,
                'mode'         => $mode,
                'amount'       => $amount,
                'unit'         => $unit,
                'store_amount' => $store_amount,
                'scope'        => $scope,
                'category'     => $category,
                'empty_sale'   => $empty_sale,
                'store_unit'   => $this->get_store_money_unit(),
            );
        }

        private function collect_bulk_price_target_ids($scope, $category) {
            $ids = array();
            $parent_args = array(
                'status' => array('publish', 'private'),
                'limit'  => -1,
                'return' => 'ids',
                'orderby'=> 'ID',
                'order'  => 'ASC',
            );
            if ($category > 0) {
                $term = get_term($category, 'product_cat');
                if (!$term || is_wp_error($term) || empty($term->slug)) {
                    return array();
                }
                $parent_args['category'] = array($term->slug);
            }

            if ($scope === 'simple') {
                $parent_args['type'] = array('simple');
                $ids = wc_get_products($parent_args);
                return array_map('absint', (array) $ids);
            }

            if ($scope === 'variation' || $scope === 'all' || $scope === 'on_sale') {
                if ($scope !== 'variation') {
                    $simple_args = $parent_args;
                    $simple_args['type'] = array('simple');
                    $simple_ids = wc_get_products($simple_args);
                    foreach ((array) $simple_ids as $sid) {
                        $ids[] = absint($sid);
                    }
                }

                $var_args = $parent_args;
                $var_args['type'] = array('variable');
                $variable_ids = wc_get_products($var_args);
                foreach ((array) $variable_ids as $vid) {
                    $product = wc_get_product($vid);
                    if (!$product) {
                        continue;
                    }
                    foreach ($product->get_children() as $child) {
                        $ids[] = absint($child);
                    }
                }
            }

            $ids = array_values(array_unique(array_filter($ids)));

            if ($scope === 'on_sale') {
                $filtered = array();
                foreach ($ids as $pid) {
                    $p = wc_get_product($pid);
                    if ($p && $p->is_on_sale()) {
                        $filtered[] = $pid;
                    }
                }
                return $filtered;
            }

            return $ids;
        }

        private function calc_adjusted_price($old_price, $params) {
            $old = (float) $old_price;
            if ($params['mode'] === 'percent') {
                $delta = $old * ((float) $params['store_amount'] / 100);
            } else {
                $delta = (float) $params['store_amount'];
            }
            if ($params['direction'] === 'decrease') {
                $new = $old - $delta;
            } else {
                $new = $old + $delta;
            }
            if ($new < 0) {
                $new = 0;
            }
            if (!is_finite($new) || $new > 999999999) {
                return '';
            }
            return (string) wc_format_decimal($new);
        }

        private function simulate_bulk_price_change($product, $params) {
            $result = array(
                'ok'       => false,
                'skip'     => false,
                'reason'   => '',
                'old'      => '',
                'new'      => '',
                'field'    => $params['field'],
                'id'       => (int) $product->get_id(),
                'name'     => $product->get_name(),
                'sku'      => (string) $product->get_sku(),
            );

            if ($params['field'] === 'regular') {
                $old = $product->get_regular_price('edit');
                if ($old === '' || $old === null || !is_numeric($old)) {
                    $result['skip'] = true;
                    $result['reason'] = 'قیمت اصلی خالی است';
                    return $result;
                }
                $new = $this->calc_adjusted_price($old, $params);
                if ($new === '') {
                    $result['skip'] = true;
                    $result['reason'] = 'قیمت مقصد بیش از حد مجاز است';
                    return $result;
                }
                $result['old'] = (string) $old;
                $result['new'] = $new;
                if ((string) wc_format_decimal($old) === (string) $new) {
                    $result['skip'] = true;
                    $result['reason'] = 'بدون تغییر';
                    return $result;
                }
                $result['ok'] = true;
                return $result;
            }

            // sale
            $old_sale = $product->get_sale_price('edit');
            $regular = $product->get_regular_price('edit');
            if ($regular === '' || $regular === null || !is_numeric($regular)) {
                $result['skip'] = true;
                $result['reason'] = 'قیمت اصلی برای محاسبه ویژه موجود نیست';
                return $result;
            }

            $base = $old_sale;
            if ($old_sale === '' || $old_sale === null || !is_numeric($old_sale)) {
                if ($params['empty_sale'] === 'from_regular') {
                    $base = $regular;
                } else {
                    $result['skip'] = true;
                    $result['reason'] = 'قیمت ویژه خالی است';
                    return $result;
                }
            }

            $new = $this->calc_adjusted_price($base, $params);
            if ($new === '') {
                $result['skip'] = true;
                $result['reason'] = 'قیمت مقصد بیش از حد مجاز است';
                return $result;
            }
            if ((float) $new >= (float) $regular) {
                $result['skip'] = true;
                $result['reason'] = 'قیمت ویژه باید کمتر از قیمت اصلی باشد';
                $result['old'] = (string) (($old_sale === '' || $old_sale === null) ? '' : $old_sale);
                $result['new'] = $new;
                return $result;
            }

            $result['old'] = (string) (($old_sale === '' || $old_sale === null) ? '' : $old_sale);
            $result['new'] = $new;
            if ($result['old'] !== '' && (string) wc_format_decimal($result['old']) === (string) $new) {
                $result['skip'] = true;
                $result['reason'] = 'بدون تغییر';
                return $result;
            }
            $result['ok'] = true;
            return $result;
        }

        private function verify_bulk_request($is_write = false) {
            if (!is_user_logged_in()) {
                wp_send_json_error(array('message' => 'ابتدا وارد حساب کاربری شوید.'), 401);
            }
            if (!$this->is_plugin_admin() && !$this->user_can_edit_price()) {
                wp_send_json_error(array('message' => 'شما دسترسی تغییر قیمت ندارید.'), 403);
            }
            $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
            if ($nonce === '' || !wp_verify_nonce($nonce, self::NONCE_ACTION)) {
                wp_send_json_error(array('message' => 'توکن امنیتی نامعتبر است؛ صفحه را تازه‌سازی کنید. عملیات ذخیره‌شده از بین نمی‌رود.'), 403);
            }
            if (!$this->bulk_storage_is_transactional()) {
                wp_send_json_error(array(
                    'message' => 'جدول‌های عملیات یا wp_postmeta از تراکنش InnoDB پشتیبانی نمی‌کنند؛ برای جلوگیری از خراب‌شدن قیمت‌ها عملیات متوقف شد.'
                ), 500);
            }
            $this->enforce_rate_limit(
                $is_write ? 'bulk_write' : 'bulk_read',
                $is_write ? self::BULK_RATE_LIMIT : self::RATE_LIMIT_READ,
                self::RATE_WINDOW
            );
        }

        private function bulk_storage_is_transactional() {
            global $wpdb;
            $cached = get_transient('ssm_bulk_storage_ok');
            if ($cached === 'yes') {
                return true;
            }
            if ($cached === 'no') {
                return false;
            }
            $tables = self::get_bulk_table_names();
            $required = array($tables['jobs'], $tables['items'], $wpdb->postmeta);
            foreach ($required as $table) {
                $status = $wpdb->get_row(
                    $wpdb->prepare('SHOW TABLE STATUS LIKE %s', $wpdb->esc_like($table)),
                    ARRAY_A
                );
                if (empty($status['Engine']) || strtolower($status['Engine']) !== 'innodb') {
                    set_transient('ssm_bulk_storage_ok', 'no', 10 * MINUTE_IN_SECONDS);
                    return false;
                }
            }
            set_transient('ssm_bulk_storage_ok', 'yes', 10 * MINUTE_IN_SECONDS);
            return true;
        }

        private function get_bulk_lock_name() {
            return 'ssm_bulk_' . (function_exists('get_current_blog_id') ? get_current_blog_id() : 1);
        }

        private function acquire_bulk_lock() {
            global $wpdb;
            $locked = (int) $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, 1)', $this->get_bulk_lock_name()));
            if ($locked === 1) {
                register_shutdown_function(array($this, 'release_bulk_lock'));
                return true;
            }
            return false;
        }

        public function release_bulk_lock() {
            global $wpdb;
            $wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $this->get_bulk_lock_name()));
        }

        private function bulk_job_token($job_key) {
            return wp_create_nonce('ssm_bulk_job_' . sanitize_key($job_key));
        }

        private function verify_bulk_job_token($job) {
            $token = isset($_POST['confirm_token']) ? sanitize_text_field(wp_unslash($_POST['confirm_token'])) : '';
            return $token !== '' && wp_verify_nonce($token, 'ssm_bulk_job_' . $job['job_key']);
        }

        private function get_bulk_job_by_key($job_key, $check_owner = true) {
            global $wpdb;
            $tables = self::get_bulk_table_names();
            $job_key = sanitize_text_field((string) $job_key);
            if ($job_key === '') {
                return false;
            }
            $job = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$tables['jobs']} WHERE job_key = %s LIMIT 1", $job_key),
                ARRAY_A
            );
            if (!$job) {
                return false;
            }
            if ($check_owner && (int) $job['user_id'] !== get_current_user_id() && !$this->is_plugin_admin()) {
                return false;
            }
            return $job;
        }

        private function get_latest_bulk_job($user_id, $active_only = false) {
            global $wpdb;
            $tables = self::get_bulk_table_names();
            $where = $active_only
                ? "AND status IN ('preparing','ready','running','rolling_back')"
                : '';
            return $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT * FROM {$tables['jobs']} WHERE user_id = %d {$where} ORDER BY id DESC LIMIT 1",
                    absint($user_id)
                ),
                ARRAY_A
            );
        }

        private function get_any_active_bulk_job() {
            global $wpdb;
            $tables = self::get_bulk_table_names();
            return $wpdb->get_row(
                "SELECT * FROM {$tables['jobs']}
                 WHERE status IN ('preparing','ready','running','rolling_back')
                 ORDER BY id ASC LIMIT 1",
                ARRAY_A
            );
        }

        private function insert_bulk_job_items($job_id, $ids) {
            global $wpdb;
            $tables = self::get_bulk_table_names();
            $now = current_time('mysql');
            $inserted_total = 0;
            foreach (array_chunk(array_values(array_unique(array_map('absint', (array) $ids))), 250) as $chunk) {
                $placeholders = array();
                $args = array();
                foreach ($chunk as $product_id) {
                    if (!$product_id) {
                        continue;
                    }
                    $placeholders[] = '(%d,%d,%s)';
                    $args[] = absint($job_id);
                    $args[] = $product_id;
                    $args[] = $now;
                }
                if (!empty($placeholders)) {
                    $sql = "INSERT IGNORE INTO {$tables['items']} (job_id,product_id,updated_at) VALUES " . implode(',', $placeholders);
                    $result = $wpdb->query($wpdb->prepare($sql, ...$args));
                    if ($result === false) {
                        return false;
                    }
                    $inserted_total += (int) $result;
                }
            }
            return $inserted_total;
        }

        private function create_or_resume_bulk_job($params) {
            global $wpdb;
            $tables = self::get_bulk_table_names();
            $params_hash = md5(wp_json_encode($params));
            $active = $this->get_any_active_bulk_job();
            if ($active) {
                if ((int) $active['user_id'] === get_current_user_id() && hash_equals($active['params_hash'], $params_hash)) {
                    return $active;
                }
                return new WP_Error(
                    'active_job',
                    (int) $active['user_id'] === get_current_user_id()
                        ? 'یک عملیات نیمه‌کاره با تنظیمات دیگری وجود دارد. ابتدا آن را ادامه دهید یا لغو کنید.'
                        : 'یک عملیات گروهی دیگر در فروشگاه در حال اجراست. پس از پایان آن دوباره تلاش کنید.'
                );
            }

            $ids = $this->collect_bulk_price_target_ids($params['scope'], $params['category']);
            $job_key = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('ssm-', true);
            $now = current_time('mysql');
            if ($wpdb->query('START TRANSACTION') === false) {
                return new WP_Error('job_transaction_failed', 'شروع تراکنش عملیات گروهی ناموفق بود.');
            }
            $inserted = $wpdb->insert(
                $tables['jobs'],
                array(
                    'job_key'    => $job_key,
                    'user_id'    => get_current_user_id(),
                    'params'     => wp_json_encode($params),
                    'params_hash'=> $params_hash,
                    'status'     => 'preparing',
                    'matched'    => count($ids),
                    'created_at' => $now,
                    'updated_at' => $now,
                ),
                array('%s', '%d', '%s', '%s', '%s', '%d', '%s', '%s')
            );
            if (!$inserted) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('job_create_failed', 'ساخت عملیات گروهی در پایگاه داده ناموفق بود.');
            }

            $job_id = (int) $wpdb->insert_id;
            $inserted_items = $this->insert_bulk_job_items($job_id, $ids);
            $stored_items = (int) $wpdb->get_var(
                $wpdb->prepare("SELECT COUNT(*) FROM {$tables['items']} WHERE job_id = %d", $job_id)
            );
            if ($inserted_items === false || $stored_items !== count($ids)) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('job_items_failed', 'فهرست کامل محصولات ذخیره نشد؛ هیچ عملیاتی ساخته نشد.');
            }
            if ($wpdb->query('COMMIT') === false) {
                $wpdb->query('ROLLBACK');
                return new WP_Error('job_commit_failed', 'ثبت عملیات گروهی کامل نشد.');
            }
            return $this->get_bulk_job_by_key($job_key);
        }

        private function prices_equal($left, $right) {
            $left = ($left === null) ? '' : (string) $left;
            $right = ($right === null) ? '' : (string) $right;
            if ($left === '' || $right === '') {
                return $left === $right;
            }
            return (string) wc_format_decimal($left) === (string) wc_format_decimal($right);
        }

        private function snapshot_bulk_item($item, $params) {
            $product = $this->get_editable_product($item['product_id']);
            if (!$product) {
                return array('state' => 'skipped', 'error_message' => 'محصول قابل ویرایش نیست یا حذف شده است.');
            }
            $sim = $this->simulate_bulk_price_change($product, $params);
            if (empty($sim['ok'])) {
                return array(
                    'state'         => 'skipped',
                    'error_message' => isset($sim['reason']) ? $sim['reason'] : 'بدون تغییر',
                );
            }

            $old_regular = $product->get_regular_price('edit');
            $old_regular = ($old_regular === null) ? '' : (string) $old_regular;
            $old_sale = $product->get_sale_price('edit');
            $old_sale = ($old_sale === null) ? '' : (string) $old_sale;
            $new_regular = $old_regular;
            $new_sale = $old_sale;

            if ($params['field'] === 'regular') {
                $new_regular = (string) $sim['new'];
                if ($old_sale !== '' && is_numeric($old_sale) && (float) $old_sale >= (float) $new_regular) {
                    $new_sale = '';
                }
            } else {
                $new_sale = (string) $sim['new'];
            }

            return array(
                'field'       => $params['field'],
                'old_regular' => $old_regular,
                'new_regular' => $new_regular,
                'old_sale'    => $old_sale,
                'new_sale'    => $new_sale,
                'state'       => 'ready',
                'error_message' => '',
            );
        }

        private function refresh_bulk_job_counts($job_id) {
            global $wpdb;
            $tables = self::get_bulk_table_names();
            $counts = $wpdb->get_row(
                $wpdb->prepare(
                    "SELECT
                        COUNT(*) AS matched,
                        SUM(state <> 'pending') AS prepared,
                        SUM(state IN ('ready','applied','conflict','error','rolled_back','rollback_conflict')) AS actionable,
                        SUM(state IN ('skipped','cancelled')) AS skipped,
                        SUM(state = 'applied') AS applied,
                        SUM(state IN ('conflict','rollback_conflict')) AS conflicts,
                        SUM(state = 'error') AS failed,
                        SUM(state = 'rolled_back') AS rolled_back,
                        SUM(state = 'pending') AS pending,
                        SUM(state = 'ready') AS ready
                     FROM {$tables['items']} WHERE job_id = %d",
                    absint($job_id)
                ),
                ARRAY_A
            );
            if (!$counts) {
                return false;
            }
            $job = $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$tables['jobs']} WHERE id = %d", absint($job_id)),
                ARRAY_A
            );
            if (!$job) {
                return false;
            }

            $status = $job['status'];
            if ($status === 'preparing' && (int) $counts['pending'] === 0) {
                $status = (int) $counts['actionable'] > 0 ? 'ready' : 'completed';
            } elseif ($status === 'running' && (int) $counts['pending'] === 0 && (int) $counts['ready'] === 0) {
                $status = 'completed';
            } elseif ($status === 'rolling_back' && (int) $counts['applied'] === 0) {
                $status = 'rolled_back';
            }

            $wpdb->update(
                $tables['jobs'],
                array(
                    'status'      => $status,
                    'matched'     => (int) $counts['matched'],
                    'prepared'    => (int) $counts['prepared'],
                    'actionable'  => (int) $counts['actionable'],
                    'skipped'     => (int) $counts['skipped'],
                    'applied'     => (int) $counts['applied'],
                    'conflicts'   => (int) $counts['conflicts'],
                    'failed'      => (int) $counts['failed'],
                    'rolled_back' => (int) $counts['rolled_back'],
                    'updated_at'  => current_time('mysql'),
                ),
                array('id' => absint($job_id)),
                array('%s', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%d', '%s'),
                array('%d')
            );
            return $wpdb->get_row(
                $wpdb->prepare("SELECT * FROM {$tables['jobs']} WHERE id = %d", absint($job_id)),
                ARRAY_A
            );
        }

        private function prepare_bulk_job_batch($job) {
            global $wpdb;
            $tables = self::get_bulk_table_names();
            $params = json_decode($job['params'], true);
            if (!is_array($params)) {
                return new WP_Error('bad_job', 'اطلاعات عملیات ذخیره‌شده معتبر نیست.');
            }
            $items = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$tables['items']} WHERE job_id = %d AND state = 'pending' ORDER BY id ASC LIMIT %d",
                    (int) $job['id'],
                    self::BULK_PREVIEW_BATCH
                ),
                ARRAY_A
            );
            foreach ((array) $items as $item) {
                $snapshot = $this->snapshot_bulk_item($item, $params);
                $snapshot['updated_at'] = current_time('mysql');
                $wpdb->update(
                    $tables['items'],
                    $snapshot,
                    array('id' => (int) $item['id'], 'state' => 'pending')
                );
            }
            return $this->refresh_bulk_job_counts($job['id']);
        }

        private function get_bulk_job_samples($job) {
            global $wpdb;
            $tables = self::get_bulk_table_names();
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT product_id,field,old_regular,new_regular,old_sale,new_sale
                     FROM {$tables['items']}
                     WHERE job_id = %d AND state NOT IN ('pending','skipped','cancelled')
                     ORDER BY id ASC LIMIT 10",
                    (int) $job['id']
                ),
                ARRAY_A
            );
            $samples = array();
            foreach ((array) $rows as $row) {
                $product = wc_get_product($row['product_id']);
                $samples[] = array(
                    'id'   => (int) $row['product_id'],
                    'name' => $product ? $product->get_name() : ('محصول #' . $row['product_id']),
                    'sku'  => $product ? (string) $product->get_sku() : '',
                    'old'  => $row['field'] === 'sale' ? $row['old_sale'] : $row['old_regular'],
                    'new'  => $row['field'] === 'sale' ? $row['new_sale'] : $row['new_regular'],
                );
            }
            return $samples;
        }

        private function get_bulk_unit_note($params) {
            if (!is_array($params) || $params['mode'] !== 'fixed') {
                return '';
            }
            $store_label = ($params['store_unit'] === 'toman') ? 'تومان' : 'ریال';
            $input_label = ($params['unit'] === 'toman') ? 'تومان' : 'ریال';
            return sprintf(
                'مبلغ ورودی: %s %s → معادل ذخیره‌شده: %s %s',
                rtrim(rtrim(number_format((float) $params['amount'], 2, '.', ','), '0'), '.'),
                $input_label,
                rtrim(rtrim(number_format((float) $params['store_amount'], 2, '.', ','), '0'), '.'),
                $store_label
            );
        }

        private function bulk_job_response($job, $resumed = false) {
            $params = json_decode($job['params'], true);
            if (!is_array($params)) {
                $params = array();
            }
            $processed = (int) $job['applied'] + (int) $job['conflicts'] + (int) $job['failed'] + (int) $job['rolled_back'];
            return array(
                'job_id'       => $job['job_key'],
                'status'       => $job['status'],
                'resumed'      => (bool) $resumed,
                'preparing'    => $job['status'] === 'preparing',
                'matched'      => (int) $job['matched'],
                'prepared'     => (int) $job['prepared'],
                'would_change' => (int) $job['actionable'],
                'skipped'      => (int) $job['skipped'],
                'updated'      => (int) $job['applied'],
                'failed'       => (int) $job['failed'],
                'conflicts'    => (int) $job['conflicts'],
                'rolled_back'  => (int) $job['rolled_back'],
                'processed'    => $processed,
                'total'        => (int) $job['actionable'],
                'done'         => in_array($job['status'], array('completed', 'cancelled', 'rolled_back'), true),
                'can_apply'    => in_array($job['status'], array('ready', 'running'), true),
                'can_cancel'   => in_array($job['status'], array('preparing', 'ready', 'running'), true),
                'can_rollback' => (int) $job['applied'] > 0,
                'samples'      => $this->get_bulk_job_samples($job),
                'unit_note'    => $this->get_bulk_unit_note($params),
                'store_unit'   => isset($params['store_unit']) ? $params['store_unit'] : $this->get_store_money_unit(),
                'params'       => $params,
                'token'        => $this->bulk_job_token($job['job_key']),
            );
        }

        private function get_canonical_product_prices($product_id, $for_update = false) {
            global $wpdb;
            $lock = $for_update ? ' FOR UPDATE' : '';
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT meta_key,meta_value FROM {$wpdb->postmeta}
                     WHERE post_id = %d AND meta_key IN ('_regular_price','_sale_price')
                     ORDER BY meta_id ASC{$lock}",
                    absint($product_id)
                ),
                ARRAY_A
            );
            if ($rows === null) {
                return false;
            }
            $prices = array('regular' => '', 'sale' => '');
            foreach ((array) $rows as $row) {
                if ($row['meta_key'] === '_regular_price') {
                    $prices['regular'] = (string) $row['meta_value'];
                } elseif ($row['meta_key'] === '_sale_price') {
                    $prices['sale'] = (string) $row['meta_value'];
                }
            }
            return $prices;
        }

        private function apply_bulk_target($item, $job_key, $rollback = false) {
            global $wpdb;
            $tables = self::get_bulk_table_names();
            $product = $this->get_editable_product($item['product_id']);
            if (!$product) {
                return array('state' => 'error', 'message' => 'محصول قابل ویرایش نیست یا حذف شده است.');
            }

            $from_regular = $rollback ? $item['new_regular'] : $item['old_regular'];
            $to_regular = $rollback ? $item['old_regular'] : $item['new_regular'];
            $from_sale = $rollback ? $item['new_sale'] : $item['old_sale'];
            $to_sale = $rollback ? $item['old_sale'] : $item['new_sale'];

            if ($wpdb->query('START TRANSACTION') === false) {
                return array('state' => 'error', 'message' => 'قفل تراکنشی قیمت محصول ایجاد نشد.');
            }
            try {
                $source_state = $rollback ? 'applied' : 'ready';
                $target_state = $rollback ? 'rolled_back' : 'applied';
                $item_state = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT state FROM {$tables['items']} WHERE id = %d AND job_id = %d FOR UPDATE",
                        absint($item['id']),
                        absint($item['job_id'])
                    )
                );
                if ($item_state === null) {
                    $wpdb->query('ROLLBACK');
                    return array('state' => 'error', 'message' => 'ردیف عملیات قیمت پیدا نشد.');
                }
                if ($item_state !== $source_state) {
                    $wpdb->query('COMMIT');
                    return array('state' => $item_state, 'message' => '', 'persisted' => true);
                }

                $canonical = $this->get_canonical_product_prices($item['product_id'], true);
                if ($canonical === false) {
                    $wpdb->query('ROLLBACK');
                    return array('state' => 'error', 'message' => 'خواندن قیمت اصلی محصول از پایگاه داده ناموفق بود.');
                }
                if ($this->prices_equal($canonical['regular'], $to_regular)
                    && $this->prices_equal($canonical['sale'], $to_sale)) {
                    $conflict_state = $rollback ? 'rollback_conflict' : 'conflict';
                    $message = 'قیمت مقصد از قبل توسط عملیات دیگری ثبت شده و مالکیت آن برای بازگردانی قابل اثبات نیست.';
                    $updated = $wpdb->update(
                        $tables['items'],
                        array('state' => $conflict_state, 'error_message' => $message, 'updated_at' => current_time('mysql')),
                        array('id' => (int) $item['id'], 'state' => $source_state),
                        array('%s', '%s', '%s'),
                        array('%d', '%s')
                    );
                    if ($updated !== 1 || $wpdb->query('COMMIT') === false) {
                        $wpdb->query('ROLLBACK');
                        return array('state' => $source_state, 'message' => 'نتیجه تراکنش نامشخص است؛ در درخواست بعدی دوباره بررسی می‌شود.', 'persisted' => true);
                    }
                    return array('state' => $conflict_state, 'message' => $message, 'persisted' => true);
                }
                if (!$this->prices_equal($canonical['regular'], $from_regular)
                    || !$this->prices_equal($canonical['sale'], $from_sale)) {
                    $conflict_state = $rollback ? 'rollback_conflict' : 'conflict';
                    $message = 'قیمت پس از ساخت پیش‌نمایش توسط بخش دیگری تغییر کرده است؛ برای جلوگیری از بازنویسی رد شد.';
                    $updated = $wpdb->update(
                        $tables['items'],
                        array('state' => $conflict_state, 'error_message' => $message, 'updated_at' => current_time('mysql')),
                        array('id' => (int) $item['id'], 'state' => $source_state),
                        array('%s', '%s', '%s'),
                        array('%d', '%s')
                    );
                    if ($updated !== 1 || $wpdb->query('COMMIT') === false) {
                        $wpdb->query('ROLLBACK');
                        return array('state' => $source_state, 'message' => 'نتیجه تراکنش نامشخص است؛ در درخواست بعدی دوباره بررسی می‌شود.', 'persisted' => true);
                    }
                    return array('state' => $conflict_state, 'message' => $message, 'persisted' => true);
                }

                $product->set_regular_price($to_regular);
                $product->set_sale_price($to_sale);
                $sale_is_active = $to_sale !== ''
                    && method_exists($product, 'is_on_sale')
                    && $product->is_on_sale('edit');
                $product->set_price($sale_is_active ? $to_sale : $to_regular);
                $product->save();

                $saved = $this->get_canonical_product_prices($item['product_id'], false);
                if ($saved === false
                    || !$this->prices_equal($saved['regular'], $to_regular)
                    || !$this->prices_equal($saved['sale'], $to_sale)) {
                    $wpdb->query('ROLLBACK');
                    return array('state' => 'error', 'message' => 'قیمت ذخیره‌شده با مقدار مقصد یکسان نیست؛ تغییر برگشت خورد.');
                }
                $state_saved = $wpdb->update(
                    $tables['items'],
                    array('state' => $target_state, 'error_message' => '', 'updated_at' => current_time('mysql')),
                    array('id' => (int) $item['id'], 'state' => $source_state),
                    array('%s', '%s', '%s'),
                    array('%d', '%s')
                );
                if ($state_saved !== 1) {
                    $wpdb->query('ROLLBACK');
                    return array('state' => 'error', 'message' => 'ثبت وضعیت محصول ناموفق بود؛ تغییر قیمت برگشت خورد.');
                }
                if ($wpdb->query('COMMIT') === false) {
                    return array(
                        'state'     => $source_state,
                        'message'   => 'نتیجه commit نامشخص است؛ وضعیت در درخواست بعدی دوباره بررسی می‌شود.',
                        'persisted' => true,
                    );
                }
            } catch (\Throwable $e) {
                $wpdb->query('ROLLBACK');
                return array('state' => 'error', 'message' => $e->getMessage());
            }

            try {
                $context = $rollback ? 'bulk_rollback' : 'bulk_price';
                if (!$this->prices_equal($from_regular, $to_regular)) {
                    $this->log_inventory_change($item['product_id'], 'regular_price', $from_regular, $to_regular, $context, $job_key);
                }
                if (!$this->prices_equal($from_sale, $to_sale)) {
                    $this->log_inventory_change($item['product_id'], 'sale_price', $from_sale, $to_sale, $context, $job_key);
                }
            } catch (\Throwable $e) {
                // ثبت لاگ نباید یک تغییر قیمت موفق و قابل‌ادامه را به خطا تبدیل کند.
            }
            return array('state' => $target_state, 'message' => '', 'persisted' => true);
        }

        private function process_bulk_apply_batch($job, $rollback = false) {
            global $wpdb;
            $tables = self::get_bulk_table_names();
            $source_state = $rollback ? 'applied' : 'ready';
            $items = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT * FROM {$tables['items']} WHERE job_id = %d AND state = %s ORDER BY id ASC LIMIT %d",
                    (int) $job['id'],
                    $source_state,
                    self::BULK_BATCH
                ),
                ARRAY_A
            );
            $parent_ids = array();
            foreach ((array) $items as $item) {
                try {
                    $result = $this->apply_bulk_target($item, $job['job_key'], $rollback);
                } catch (\Throwable $e) {
                    $result = array('state' => 'error', 'message' => $e->getMessage());
                }
                if (empty($result['persisted'])) {
                    $wpdb->update(
                        $tables['items'],
                        array(
                            'state'         => $result['state'],
                            'error_message' => substr(sanitize_text_field($result['message']), 0, 255),
                            'updated_at'    => current_time('mysql'),
                        ),
                        array('id' => (int) $item['id'], 'state' => $source_state),
                        array('%s', '%s', '%s'),
                        array('%d', '%s')
                    );
                }
                $product = wc_get_product($item['product_id']);
                if ($product && $product->is_type('variation') && $product->get_parent_id()) {
                    $parent_ids[] = (int) $product->get_parent_id();
                }
            }
            foreach (array_unique($parent_ids) as $parent_id) {
                if (class_exists('WC_Product_Variable')) {
                    WC_Product_Variable::sync($parent_id);
                }
                wc_delete_product_transients($parent_id);
            }
            return $this->refresh_bulk_job_counts($job['id']);
        }

        public function ajax_bulk_price_status() {
            $this->verify_bulk_request(false);
            $job = $this->get_latest_bulk_job(get_current_user_id(), false);
            if (!$job) {
                wp_send_json_success(array('has_job' => false));
            }
            wp_send_json_success(array_merge(array('has_job' => true), $this->bulk_job_response($job, true)));
        }

        public function ajax_bulk_price_preview() {
            $this->verify_bulk_request(false);
            if (!$this->acquire_bulk_lock()) {
                wp_send_json_error(array('message' => 'عملیات دیگری در حال ثبت است؛ چند ثانیه بعد دوباره تلاش کنید.'), 409);
            }
            try {
                $job_key = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';
                $resumed = false;
                if ($job_key !== '') {
                    $job = $this->get_bulk_job_by_key($job_key);
                    $resumed = true;
                } else {
                    $params = $this->parse_bulk_price_request();
                    if (is_wp_error($params)) {
                        wp_send_json_error(array('message' => $params->get_error_message()));
                    }
                    $before = $this->get_latest_bulk_job(get_current_user_id(), true);
                    $job = $this->create_or_resume_bulk_job($params);
                    $resumed = (bool) $before;
                }
                if (is_wp_error($job)) {
                    wp_send_json_error(array('message' => $job->get_error_message()), 409);
                }
                if (!$job) {
                    wp_send_json_error(array('message' => 'عملیات گروهی پیدا نشد.'), 404);
                }
                if ($job['status'] === 'preparing') {
                    $job = $this->prepare_bulk_job_batch($job);
                    if (is_wp_error($job)) {
                        wp_send_json_error(array('message' => $job->get_error_message()));
                    }
                }
                wp_send_json_success($this->bulk_job_response($job, $resumed));
            } finally {
                $this->release_bulk_lock();
            }
        }

        public function ajax_bulk_price_apply() {
            $this->verify_bulk_request(true);
            $job_key = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';
            $job = $this->get_bulk_job_by_key($job_key);
            if (!$job || !$this->verify_bulk_job_token($job)) {
                wp_send_json_error(array('message' => 'شناسه عملیات نامعتبر است؛ صفحه را تازه‌سازی کنید.'), 403);
            }
            if (!in_array($job['status'], array('ready', 'running'), true)) {
                wp_send_json_error(array('message' => 'این عملیات آماده اجرا یا ادامه نیست.'), 409);
            }
            if (!$this->acquire_bulk_lock()) {
                wp_send_json_error(array('message' => 'یک batch دیگر در حال اجراست؛ درخواست بعدی را چند ثانیه دیگر بفرستید.'), 409);
            }
            try {
                global $wpdb;
                $tables = self::get_bulk_table_names();
                if ($job['status'] === 'ready') {
                    $wpdb->update(
                        $tables['jobs'],
                        array('status' => 'running', 'updated_at' => current_time('mysql')),
                        array('id' => (int) $job['id']),
                        array('%s', '%s'),
                        array('%d')
                    );
                    $job['status'] = 'running';
                }
                $job = $this->process_bulk_apply_batch($job, false);
                wp_send_json_success($this->bulk_job_response($job, true));
            } finally {
                $this->release_bulk_lock();
            }
        }

        public function ajax_bulk_price_cancel() {
            $this->verify_bulk_request(true);
            $job_key = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';
            $job = $this->get_bulk_job_by_key($job_key);
            if (!$job || !$this->verify_bulk_job_token($job)) {
                wp_send_json_error(array('message' => 'شناسه عملیات نامعتبر است.'), 403);
            }
            if (!in_array($job['status'], array('preparing', 'ready', 'running'), true)) {
                wp_send_json_error(array('message' => 'این عملیات در وضعیت قابل لغو نیست.'), 409);
            }
            if (!$this->acquire_bulk_lock()) {
                wp_send_json_error(array('message' => 'batch دیگری در حال اجراست؛ دوباره تلاش کنید.'), 409);
            }
            try {
                global $wpdb;
                $tables = self::get_bulk_table_names();
                $wpdb->query(
                    $wpdb->prepare(
                        "UPDATE {$tables['items']} SET state = 'cancelled', updated_at = %s
                         WHERE job_id = %d AND state IN ('pending','ready')",
                        current_time('mysql'),
                        (int) $job['id']
                    )
                );
                $wpdb->update(
                    $tables['jobs'],
                    array('status' => 'cancelled', 'updated_at' => current_time('mysql')),
                    array('id' => (int) $job['id']),
                    array('%s', '%s'),
                    array('%d')
                );
                $job = $this->refresh_bulk_job_counts($job['id']);
                wp_send_json_success($this->bulk_job_response($job, true));
            } finally {
                $this->release_bulk_lock();
            }
        }

        public function ajax_bulk_price_rollback() {
            $this->verify_bulk_request(true);
            $job_key = isset($_POST['job_id']) ? sanitize_text_field(wp_unslash($_POST['job_id'])) : '';
            $job = $this->get_bulk_job_by_key($job_key);
            if (!$job || !$this->verify_bulk_job_token($job)) {
                wp_send_json_error(array('message' => 'شناسه عملیات نامعتبر است.'), 403);
            }
            if (!in_array($job['status'], array('completed', 'cancelled', 'rolling_back'), true)) {
                wp_send_json_error(array('message' => 'ابتدا عملیات را تمام یا لغو کنید، سپس بازگردانی را اجرا کنید.'), 409);
            }
            if (!$this->acquire_bulk_lock()) {
                wp_send_json_error(array('message' => 'batch دیگری در حال اجراست؛ دوباره تلاش کنید.'), 409);
            }
            try {
                global $wpdb;
                $tables = self::get_bulk_table_names();
                if ($job['status'] !== 'rolling_back') {
                    $wpdb->update(
                        $tables['jobs'],
                        array('status' => 'rolling_back', 'updated_at' => current_time('mysql')),
                        array('id' => (int) $job['id']),
                        array('%s', '%s'),
                        array('%d')
                    );
                    $job['status'] = 'rolling_back';
                }
                $job = $this->process_bulk_apply_batch($job, true);
                wp_send_json_success($this->bulk_job_response($job, true));
            } finally {
                $this->release_bulk_lock();
            }
        }

        private function normalize_legacy_price_value($value, $allow_empty) {
            $value = trim((string) $value);
            if ($value === '—') {
                $value = '';
            }
            if ($value === '') {
                return $allow_empty ? '' : new WP_Error('bad_legacy_price', 'یک مقدار قیمت خالی و غیرمجاز در لاگ بازیابی پیدا شد.');
            }
            if (!is_numeric($value)) {
                return new WP_Error('bad_legacy_price', 'یک مقدار غیرعددی در لاگ بازیابی پیدا شد.');
            }
            $number = (float) $value;
            if (!is_finite($number) || $number < 0 || $number > 999999999) {
                return new WP_Error('bad_legacy_price', 'یک مقدار قیمت خارج از محدوده امن در لاگ بازیابی پیدا شد.');
            }
            return (string) wc_format_decimal($value);
        }

        private function parse_legacy_bulk_log_message($message, $timestamp, $default_field, $explicit_field = '') {
            $pattern = '/Product\s+#(\d+)\s+price_update:\s*(.*?)\s*→\s*(.*?)\s+by user\s+#\d+\s+\(bulk_price\)/u';
            if (!preg_match($pattern, (string) $message, $matches)) {
                return false;
            }
            $old = trim((string) $matches[2]);
            $new = trim((string) $matches[3]);
            $field = in_array($explicit_field, array('regular_price', 'sale_price'), true)
                ? $explicit_field
                : ($new === '' ? 'sale_price' : ($default_field === 'sale' ? 'sale_price' : 'regular_price'));
            $old = $this->normalize_legacy_price_value($old, $field === 'sale_price');
            $new = $this->normalize_legacy_price_value($new, $field === 'sale_price');
            if (is_wp_error($old)) {
                return $old;
            }
            if (is_wp_error($new)) {
                return $new;
            }
            return array(
                'product_id' => absint($matches[1]),
                'field'      => $field,
                'old'        => $old,
                'new'        => $new,
                'timestamp'  => (string) $timestamp,
            );
        }

        private function legacy_log_timestamp_to_epoch($timestamp, $assume_utc = false) {
            if (is_numeric($timestamp)) {
                return (int) $timestamp;
            }
            $raw = trim((string) $timestamp);
            if ($raw === '') {
                return false;
            }
            if (preg_match('/(?:Z|[+-]\d{2}:\d{2})$/', $raw)) {
                return strtotime($raw);
            }
            try {
                $timezone = $assume_utc
                    ? new \DateTimeZone('UTC')
                    : (function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC'));
                return (new \DateTimeImmutable($raw, $timezone))->getTimestamp();
            } catch (\Exception $e) {
                return false;
            }
        }

        private function legacy_log_time_is_in_range($timestamp, $start_ts, $end_ts, $assume_utc = false) {
            $ts = $this->legacy_log_timestamp_to_epoch($timestamp, $assume_utc);
            return $ts && $ts >= $start_ts && $ts <= $end_ts;
        }

        private function parse_recovery_time_range($date, $from, $to) {
            $timezone = function_exists('wp_timezone') ? wp_timezone() : new \DateTimeZone('UTC');
            $start = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $from, $timezone);
            $start_errors = \DateTimeImmutable::getLastErrors();
            $end = \DateTimeImmutable::createFromFormat('!Y-m-d H:i', $date . ' ' . $to, $timezone);
            $end_errors = \DateTimeImmutable::getLastErrors();
            $has_start_errors = is_array($start_errors) && ($start_errors['warning_count'] || $start_errors['error_count']);
            $has_end_errors = is_array($end_errors) && ($end_errors['warning_count'] || $end_errors['error_count']);
            if (!$start || !$end || $has_start_errors || $has_end_errors
                || $start->format('Y-m-d H:i') !== $date . ' ' . $from
                || $end->format('Y-m-d H:i') !== $date . ' ' . $to
                || $start->getTimestamp() > $end->getTimestamp()) {
                return new WP_Error('bad_recovery_range', 'تاریخ یا بازه ساعت نامعتبر است.');
            }
            return array($start->getTimestamp(), $end->setTime(
                (int) $end->format('H'),
                (int) $end->format('i'),
                59
            )->getTimestamp());
        }

        private function collect_legacy_bulk_events($date, $from, $to, $default_field) {
            global $wpdb;
            $events = array();
            $transition_fields = array();
            $range = $this->parse_recovery_time_range($date, $from, $to);
            if (is_wp_error($range)) {
                return $range;
            }
            list($start_ts, $end_ts) = $range;

            $audit = get_option(self::AUDIT_LOG_OPTION, array());
            foreach ((array) $audit as $audit_index => $row) {
                if (!is_array($row) || (isset($row['context']) ? $row['context'] : '') !== 'bulk_price') {
                    continue;
                }
                $when = isset($row['created_at']) ? $row['created_at'] : '';
                if (!$this->legacy_log_time_is_in_range($when, $start_ts, $end_ts)) {
                    continue;
                }
                $event_field = isset($row['field'])
                    ? sanitize_key($row['field'])
                    : ($default_field === 'sale' ? 'sale_price' : 'regular_price');
                if (!in_array($event_field, array('regular_price', 'sale_price'), true)) {
                    return new WP_Error('bad_legacy_field', 'نوع قیمت یکی از لاگ‌های بازیابی نامعتبر است.');
                }
                $event_old = $this->normalize_legacy_price_value(
                    isset($row['old_value']) ? $row['old_value'] : '',
                    $event_field === 'sale_price'
                );
                $event_new = $this->normalize_legacy_price_value(
                    isset($row['new_value']) ? $row['new_value'] : '',
                    $event_field === 'sale_price'
                );
                if (is_wp_error($event_old)) {
                    return $event_old;
                }
                if (is_wp_error($event_new)) {
                    return $event_new;
                }
                $event = array(
                    'product_id' => isset($row['product_id']) ? absint($row['product_id']) : 0,
                    'field'      => $event_field,
                    'old'        => $event_old,
                    'new'        => $event_new,
                    'timestamp'  => $when,
                    'epoch'      => $this->legacy_log_timestamp_to_epoch($when, false),
                    'sequence'   => 'audit-' . (int) $audit_index,
                    'source_rank'=> 2,
                    'order'      => 0 - (int) $audit_index,
                );
                if (!$event['product_id']) {
                    continue;
                }
                $transition_key = $event['product_id'] . '|' . $event['old'] . '|' . $event['new'];
                $transition_fields[$transition_key] = $event['field'];
                $events[] = $event;
            }

            $log_table = $wpdb->prefix . 'woocommerce_log';
            $table_exists = $wpdb->get_var(
                $wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($log_table))
            );
            if ($table_exists === $log_table) {
                $last_log_id = 0;
                do {
                    $rows = $wpdb->get_results(
                        $wpdb->prepare(
                            "SELECT log_id,timestamp,message,context FROM {$log_table}
                             WHERE source = %s AND timestamp >= %s AND timestamp <= %s
                               AND message LIKE %s AND log_id > %d
                             ORDER BY log_id ASC LIMIT 2000",
                            'smart-stock-manager',
                            gmdate('Y-m-d H:i:s', $start_ts),
                            gmdate('Y-m-d H:i:s', $end_ts),
                            '%(bulk_price)%',
                            $last_log_id
                        ),
                        ARRAY_A
                    );
                    if ($rows === null) {
                        return new WP_Error('recovery_db_log_failed', 'خواندن کامل لاگ دیتابیس ووکامرس ناموفق بود.');
                    }
                    foreach ((array) $rows as $row) {
                        $last_log_id = max($last_log_id, absint($row['log_id']));
                        if (!$this->legacy_log_time_is_in_range($row['timestamp'], $start_ts, $end_ts, true)) {
                            continue;
                        }
                        $explicit_field = '';
                        $context = json_decode((string) $row['context'], true);
                        if (is_array($context) && isset($context['field'])) {
                            $explicit_field = sanitize_key($context['field']);
                        }
                        $probe = $this->parse_legacy_bulk_log_message(
                            $row['message'],
                            $row['timestamp'] . ' UTC',
                            $default_field,
                            $explicit_field
                        );
                        if (is_wp_error($probe)) {
                            return $probe;
                        }
                        if (!$probe) {
                            continue;
                        }
                        $transition_key = $probe['product_id'] . '|' . $probe['old'] . '|' . $probe['new'];
                        if (isset($transition_fields[$transition_key])) {
                            $probe['field'] = $transition_fields[$transition_key];
                        }
                        $probe['epoch'] = $this->legacy_log_timestamp_to_epoch($row['timestamp'], true);
                        $probe['sequence'] = 'db-' . absint($row['log_id']);
                        $probe['source_rank'] = 0;
                        $probe['order'] = absint($row['log_id']);
                        $events[] = $probe;
                    }
                    if (count($events) > 100000) {
                        return new WP_Error('recovery_too_many_logs', 'تعداد لاگ‌ها بیش از حد امن است؛ بازه ساعت را کوتاه‌تر کنید.');
                    }
                } while (count($rows) === 2000);
            }

            $uploads = wp_upload_dir();
            if (empty($uploads['error']) && !empty($uploads['basedir'])) {
                $default_log_dir = trailingslashit($uploads['basedir']) . 'wc-logs';
                $log_dir = apply_filters('woocommerce_log_directory', $default_log_dir);
                $pattern = trailingslashit($log_dir) . '*smart-stock-manager*';
                $files = glob($pattern);
                foreach ((array) $files as $file_index => $file) {
                    if (!is_readable($file)) {
                        return new WP_Error('recovery_file_unreadable', 'یکی از فایل‌های لاگ ووکامرس قابل خواندن نیست؛ بازیابی برای ایمنی متوقف شد.');
                    }
                    $handle = fopen($file, 'r');
                    if (!$handle) {
                        return new WP_Error('recovery_file_open_failed', 'باز کردن یکی از فایل‌های لاگ ووکامرس ناموفق بود.');
                    }
                    $line_count = 0;
                    while (($line = fgets($handle)) !== false) {
                        $line_count++;
                        if (strpos($line, '(bulk_price)') === false) {
                            continue;
                        }
                        $line_time = '';
                        if (preg_match('/^(\d{4}-\d{2}-\d{2}[T ][0-9:]+(?:Z|[+-]\d{2}:\d{2})?)/', $line, $tm)) {
                            $line_time = $tm[1];
                        }
                        if ($line_time === '' || !$this->legacy_log_time_is_in_range($line_time, $start_ts, $end_ts)) {
                            continue;
                        }
                        $explicit_field = '';
                        if (preg_match('/CONTEXT:\s*(\{.*\})\s*$/u', $line, $context_match)) {
                            $file_context = json_decode($context_match[1], true);
                            if (is_array($file_context) && isset($file_context['field'])) {
                                $explicit_field = sanitize_key($file_context['field']);
                            }
                        }
                        $probe = $this->parse_legacy_bulk_log_message(
                            $line,
                            $line_time,
                            $default_field,
                            $explicit_field
                        );
                        if (is_wp_error($probe)) {
                            fclose($handle);
                            return $probe;
                        }
                        if (!$probe) {
                            continue;
                        }
                        $transition_key = $probe['product_id'] . '|' . $probe['old'] . '|' . $probe['new'];
                        if (isset($transition_fields[$transition_key])) {
                            $probe['field'] = $transition_fields[$transition_key];
                        }
                        $probe['epoch'] = $this->legacy_log_timestamp_to_epoch($line_time, false);
                        $probe['sequence'] = 'file-' . (int) $file_index . '-' . $line_count;
                        $probe['source_rank'] = 1;
                        $probe['order'] = ((int) $file_index * 1000000) + $line_count;
                        $events[] = $probe;
                        if (count($events) > 100000) {
                            fclose($handle);
                            return new WP_Error('recovery_too_many_logs', 'تعداد لاگ‌ها بیش از حد امن است؛ بازه ساعت را کوتاه‌تر کنید.');
                        }
                    }
                    fclose($handle);
                }
            }

            usort($events, function ($a, $b) {
                $epoch_compare = (int) $a['epoch'] <=> (int) $b['epoch'];
                if ($epoch_compare !== 0) {
                    return $epoch_compare;
                }
                $source_compare = (int) $a['source_rank'] <=> (int) $b['source_rank'];
                if ($source_compare !== 0) {
                    return $source_compare;
                }
                return (int) $a['order'] <=> (int) $b['order'];
            });

            $unique = array();
            $out = array();
            foreach ($events as $event) {
                $key = implode('|', array(
                    $event['product_id'],
                    $event['field'],
                    $event['old'],
                    $event['new'],
                    (int) $event['epoch'],
                ));
                if (isset($unique[$key])) {
                    continue;
                }
                $unique[$key] = true;
                $out[] = $event;
            }
            return $out;
        }

        private function group_legacy_bulk_events($events) {
            $groups = array();
            $ambiguous = array();
            foreach ((array) $events as $event) {
                $pid = absint($event['product_id']);
                if (!$pid || !in_array($event['field'], array('regular_price', 'sale_price'), true)) {
                    continue;
                }
                if (!isset($groups[$pid])) {
                    $groups[$pid] = array(
                        'regular_price' => array('old' => null, 'new' => null, 'last_old' => null),
                        'sale_price'    => array('old' => null, 'new' => null, 'last_old' => null),
                    );
                }
                $field = $event['field'];
                if ($groups[$pid][$field]['old'] === null) {
                    $groups[$pid][$field]['old'] = $event['old'];
                    $groups[$pid][$field]['last_old'] = $event['old'];
                    $groups[$pid][$field]['new'] = $event['new'];
                    continue;
                }
                if ($this->prices_equal($groups[$pid][$field]['new'], $event['old'])) {
                    $groups[$pid][$field]['last_old'] = $event['old'];
                    $groups[$pid][$field]['new'] = $event['new'];
                    continue;
                }
                if ($this->prices_equal($groups[$pid][$field]['last_old'], $event['old'])
                    && $this->prices_equal($groups[$pid][$field]['new'], $event['new'])) {
                    continue;
                }
                $ambiguous[$pid] = true;
            }
            if (!empty($ambiguous)) {
                return new WP_Error(
                    'ambiguous_recovery_chain',
                    sprintf(
                        'زنجیره تغییرات %d محصول پیوسته نیست. بازه ساعت را کوتاه‌تر کنید تا فقط همان عملیات خراب انتخاب شود.',
                        count($ambiguous)
                    )
                );
            }
            foreach ($groups as &$fields) {
                unset($fields['regular_price']['last_old'], $fields['sale_price']['last_old']);
            }
            unset($fields);
            return $groups;
        }

        private function insert_legacy_recovery_items($job_id, $groups, $default_field) {
            global $wpdb;
            $tables = self::get_bulk_table_names();
            $rows = array();
            foreach ($groups as $product_id => $fields) {
                $product = $this->get_editable_product($product_id);
                if (!$product) {
                    continue;
                }
                $current_regular = (string) $product->get_regular_price('edit');
                $current_sale = $product->get_sale_price('edit');
                $current_sale = ($current_sale === null) ? '' : (string) $current_sale;
                $regular = $fields['regular_price'];
                $sale = $fields['sale_price'];
                $rows[] = array(
                    'product_id'  => absint($product_id),
                    'field'       => $default_field,
                    'old_regular' => $regular['old'] === null ? $current_regular : (string) $regular['old'],
                    'new_regular' => $regular['new'] === null ? $current_regular : (string) $regular['new'],
                    'old_sale'    => $sale['old'] === null ? $current_sale : (string) $sale['old'],
                    'new_sale'    => $sale['new'] === null ? $current_sale : (string) $sale['new'],
                );
            }

            $now = current_time('mysql');
            $inserted_total = 0;
            foreach (array_chunk($rows, 150) as $chunk) {
                $placeholders = array();
                $args = array();
                foreach ($chunk as $row) {
                    $placeholders[] = '(%d,%d,%s,%s,%s,%s,%s,%s,%s)';
                    array_push(
                        $args,
                        absint($job_id),
                        $row['product_id'],
                        $row['field'],
                        $row['old_regular'],
                        $row['new_regular'],
                        $row['old_sale'],
                        $row['new_sale'],
                        'applied',
                        $now
                    );
                }
                $sql = "INSERT IGNORE INTO {$tables['items']}
                    (job_id,product_id,field,old_regular,new_regular,old_sale,new_sale,state,updated_at)
                    VALUES " . implode(',', $placeholders);
                $result = $wpdb->query($wpdb->prepare($sql, ...$args));
                if ($result === false) {
                    return false;
                }
                $inserted_total += (int) $result;
            }
            return $inserted_total;
        }

        public function ajax_legacy_bulk_recovery() {
            $this->verify_bulk_request(true);
            if (!$this->is_plugin_admin()) {
                wp_send_json_error(array('message' => 'بازیابی اضطراری فقط برای مدیر فروشگاه مجاز است.'), 403);
            }
            $date = isset($_POST['recovery_date']) ? sanitize_text_field(wp_unslash($_POST['recovery_date'])) : '';
            $from = isset($_POST['recovery_from']) ? sanitize_text_field(wp_unslash($_POST['recovery_from'])) : '00:00';
            $to = isset($_POST['recovery_to']) ? sanitize_text_field(wp_unslash($_POST['recovery_to'])) : '23:59';
            $field = isset($_POST['recovery_field']) ? sanitize_key(wp_unslash($_POST['recovery_field'])) : 'regular';
            $range = $this->parse_recovery_time_range($date, $from, $to);
            if (is_wp_error($range) || !in_array($field, array('regular', 'sale'), true)) {
                wp_send_json_error(array('message' => 'تاریخ، ساعت یا نوع قیمت نامعتبر است.'));
            }
            if (!$this->acquire_bulk_lock()) {
                wp_send_json_error(array('message' => 'عملیات دیگری در حال اجراست؛ چند ثانیه بعد دوباره تلاش کنید.'), 409);
            }
            try {
                if ($this->get_any_active_bulk_job()) {
                    wp_send_json_error(array('message' => 'ابتدا عملیات گروهی نیمه‌کاره را تمام یا لغو کنید.'), 409);
                }
                $events = $this->collect_legacy_bulk_events($date, $from, $to, $field);
                if (is_wp_error($events)) {
                    wp_send_json_error(array('message' => $events->get_error_message()));
                }
                $groups = $this->group_legacy_bulk_events($events);
                if (is_wp_error($groups)) {
                    wp_send_json_error(array('message' => $groups->get_error_message()), 409);
                }
                if (empty($groups)) {
                    wp_send_json_error(array(
                        'message' => 'لاگ قابل بازیابی در این بازه پیدا نشد. در ووکامرس ← وضعیت ← لاگ‌ها، منبع smart-stock-manager را هم بررسی کنید.'
                    ), 404);
                }

                global $wpdb;
                $tables = self::get_bulk_table_names();
                $params = array(
                    'field'           => $field,
                    'direction'       => 'recovery',
                    'mode'            => 'legacy_recovery',
                    'amount'          => 0,
                    'unit'            => $this->get_store_money_unit(),
                    'store_amount'    => 0,
                    'scope'           => 'all',
                    'category'        => 0,
                    'empty_sale'      => 'skip',
                    'store_unit'      => $this->get_store_money_unit(),
                    'recovery_date'   => $date,
                    'recovery_from'   => $from,
                    'recovery_to'     => $to,
                );
                $job_key = function_exists('wp_generate_uuid4') ? wp_generate_uuid4() : uniqid('ssm-recovery-', true);
                $now = current_time('mysql');
                if ($wpdb->query('START TRANSACTION') === false) {
                    wp_send_json_error(array('message' => 'شروع تراکنش بازیابی ناموفق بود.'));
                }
                $job_inserted = $wpdb->insert(
                    $tables['jobs'],
                    array(
                        'job_key'     => $job_key,
                        'user_id'     => get_current_user_id(),
                        'params'      => wp_json_encode($params),
                        'params_hash' => md5(wp_json_encode($params)),
                        'status'      => 'completed',
                        'matched'     => count($groups),
                        'created_at'  => $now,
                        'updated_at'  => $now,
                    )
                );
                if (!$job_inserted || !$wpdb->insert_id) {
                    $wpdb->query('ROLLBACK');
                    wp_send_json_error(array('message' => 'ساخت عملیات بازیابی در پایگاه داده ناموفق بود.'));
                }
                $job_id = (int) $wpdb->insert_id;
                $inserted_items = $this->insert_legacy_recovery_items($job_id, $groups, $field);
                if ($inserted_items === false || $inserted_items < 1) {
                    $wpdb->query('ROLLBACK');
                    wp_send_json_error(array('message' => 'هیچ محصول قابل بازیابی به‌صورت کامل ذخیره نشد.'));
                }
                if ($wpdb->query('COMMIT') === false) {
                    $wpdb->query('ROLLBACK');
                    wp_send_json_error(array('message' => 'ثبت نهایی پیش‌نمایش بازیابی ناموفق بود.'));
                }
                $job = $this->refresh_bulk_job_counts($job_id);
                wp_send_json_success(array_merge(
                    $this->bulk_job_response($job, false),
                    array('events_found' => count($events), 'recovery_preview' => true)
                ));
            } finally {
                $this->release_bulk_lock();
            }
        }

        public function cleanup_bulk_jobs() {
            global $wpdb;
            $tables = self::get_bulk_table_names();
            $cutoff = gmdate('Y-m-d H:i:s', time() - (self::BULK_RETENTION_DAYS * DAY_IN_SECONDS));
            $ids = $wpdb->get_col(
                $wpdb->prepare(
                    "SELECT id FROM {$tables['jobs']}
                     WHERE status IN ('completed','cancelled','rolled_back') AND updated_at < %s
                     ORDER BY id ASC LIMIT 20",
                    $cutoff
                )
            );
            foreach ((array) $ids as $job_id) {
                $wpdb->delete($tables['items'], array('job_id' => absint($job_id)), array('%d'));
                $wpdb->delete($tables['jobs'], array('id' => absint($job_id)), array('%d'));
            }
        }
    }

    register_activation_hook(__FILE__, array('Smart_Stock_Manager', 'activate'));
    register_deactivation_hook(__FILE__, array('Smart_Stock_Manager', 'deactivate'));
    new Smart_Stock_Manager();
}