<?php
/**
 * هسته سئو — داشبورد ماژول‌ها.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

$loader  = class_exists( 'SEO_Core_Loader' ) ? SEO_Core_Loader::instance() : null;
$modules = $loader ? $loader->get_modules() : array();
$sub     = isset( $_GET['module'] ) ? sanitize_key( wp_unslash( $_GET['module'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$comps   = class_exists( 'Shojaei_SEO_Integration' ) ? Shojaei_SEO_Integration::detected_seo_plugins() : array();
$comp_lbl = class_exists( 'Shojaei_SEO_Integration' ) ? Shojaei_SEO_Integration::detected_labels() : '';
$disabled = class_exists( 'SEO_Core_Installer' ) ? SEO_Core_Installer::get_disabled_modules() : array();
$health   = class_exists( 'SEO_Core_Installer' ) ? SEO_Core_Installer::get_health_status() : array();
$last_rep = is_array( $health['last_report'] ?? null ) ? $health['last_report'] : array();
?>

<div class="shojaei-card">
	<h3 style="margin:0 0 6px;"><?php esc_html_e( 'هسته سئو', 'shojaei-seo-for-woo' ); ?></h3>
	<p class="shojaei-desc" style="margin:0;">
		<?php esc_html_e( 'چارچوب ماژولار عملکرد‌محور: هر قابلیت (نقشه سایت، تحلیلگر و …) جداگانه روشن/خاموش می‌شود. در صورت فعال بودن Rank Math / Yoast / AIOSEO، ماژول‌ها پیش‌فرض در حالت کمکی (Passive) می‌مانند.', 'shojaei-seo-for-woo' ); ?>
	</p>
</div>

<div class="shojaei-card" id="shojaei-seo-core-health">
	<h4 style="margin:0 0 8px;"><?php esc_html_e( 'وضعیت سلامت زیرساخت', 'shojaei-seo-for-woo' ); ?></h4>
	<ul class="description" style="margin:0 0 12px;line-height:1.8;">
		<li>
			<?php esc_html_e( 'کش سلامت (Transient):', 'shojaei-seo-for-woo' ); ?>
			<?php if ( ! empty( $health['cached_ok'] ) ) : ?>
				<span class="shojaei-slug-score shojaei-tone-safe"><?php esc_html_e( 'معتبر', 'shojaei-seo-for-woo' ); ?></span>
			<?php else : ?>
				<span class="shojaei-slug-score shojaei-tone-warning"><?php esc_html_e( 'منقضی / نیاز به بررسی', 'shojaei-seo-for-woo' ); ?></span>
			<?php endif; ?>
		</li>
		<li>
			<?php esc_html_e( 'Schema version:', 'shojaei-seo-for-woo' ); ?>
			<code><?php echo esc_html( (string) ( $health['schema'] ?? '—' ) ); ?></code>
		</li>
		<li>
			<?php esc_html_e( 'پرچم Rewrite flush:', 'shojaei-seo-for-woo' ); ?>
			<code><?php echo esc_html( (string) ( ( $health['rewrite_flag'] ?? '' ) !== '' ? $health['rewrite_flag'] : 'off' ) ); ?></code>
		</li>
		<li>
			<?php esc_html_e( 'ماژول‌های غیرفعال‌شده:', 'shojaei-seo-for-woo' ); ?>
			<strong><?php echo esc_html( (string) count( $disabled ) ); ?></strong>
		</li>
	</ul>
	<?php if ( ! empty( $last_rep ) ) : ?>
		<details style="margin-bottom:12px;">
			<summary><?php esc_html_e( 'آخرین گزارش خودترمیمی', 'shojaei-seo-for-woo' ); ?></summary>
			<p class="description"><?php echo esc_html( (string) ( $last_rep['message'] ?? '' ) ); ?></p>
			<?php if ( ! empty( $last_rep['repaired'] ) ) : ?>
				<p><strong><?php esc_html_e( 'ترمیم‌شده:', 'shojaei-seo-for-woo' ); ?></strong></p>
				<ul style="list-style:disc;margin:0 1.2em 8px;"><?php foreach ( (array) $last_rep['repaired'] as $line ) : ?><li><?php echo esc_html( (string) $line ); ?></li><?php endforeach; ?></ul>
			<?php endif; ?>
			<?php if ( ! empty( $last_rep['errors'] ) ) : ?>
				<p><strong><?php esc_html_e( 'خطاها:', 'shojaei-seo-for-woo' ); ?></strong></p>
				<ul style="list-style:disc;margin:0 1.2em;"><?php foreach ( (array) $last_rep['errors'] as $line ) : ?><li><?php echo esc_html( (string) $line ); ?></li><?php endforeach; ?></ul>
			<?php endif; ?>
		</details>
	<?php endif; ?>
	<p style="margin:0;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
		<button type="button" class="button button-primary" id="shojaei-seo-core-heal"><?php esc_html_e( 'اجرای خودترمیمی اکنون', 'shojaei-seo-for-woo' ); ?></button>
		<button type="button" class="button" id="shojaei-seo-core-selftest"><?php esc_html_e( 'اجرای خودآزمون', 'shojaei-seo-for-woo' ); ?></button>
		<span id="shojaei-seo-core-heal-status" class="description" aria-live="polite"></span>
	</p>
	<div id="shojaei-seo-core-heal-report" style="margin-top:10px;"></div>
	<div id="shojaei-seo-core-selftest-report" style="margin-top:10px;"></div>
</div>

<?php if ( ! empty( $disabled ) ) : ?>
	<div class="notice notice-warning inline" style="margin:0 0 12px;">
		<p><strong><?php esc_html_e( 'ماژول‌های موقتاً غیرفعال (خودترمیمی):', 'shojaei-seo-for-woo' ); ?></strong></p>
		<ul style="list-style:disc;margin:0.5em 1.5em;">
			<?php foreach ( $disabled as $mid => $reason ) : ?>
				<li><code><?php echo esc_html( (string) $mid ); ?></code> — <?php echo esc_html( (string) $reason ); ?></li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>

<?php if ( ! empty( $comps ) ) : ?>
	<div class="notice notice-warning inline" style="margin:0 0 12px;">
		<p>
			<?php
			printf(
				/* translators: %s: plugin names */
				esc_html__( 'افزونه SEO فعال تشخیص داده شد: %s — ماژول‌ها در حالت کمکی هستند مگر «حالت جایگزینی» را روشن کنید.', 'shojaei-seo-for-woo' ),
				esc_html( $comp_lbl )
			);
			?>
		</p>
	</div>
<?php endif; ?>

<div class="shojaei-edu-tip" style="margin-bottom:14px;">
	<span class="dashicons dashicons-lightbulb"></span>
	<?php esc_html_e( 'نکته آموزشی: حالت Passive یعنی داشبورد و راهنما فعال است، اما خروجی رقابتی (مثل XML موازی) صادر نمی‌شود تا با Rank Math تداخل نکند.', 'shojaei-seo-for-woo' ); ?>
</div>

<?php if ( empty( $modules ) ) : ?>
	<div class="notice notice-error"><p><?php esc_html_e( 'هسته سئو بارگذاری نشده است.', 'shojaei-seo-for-woo' ); ?></p></div>
	<?php
	return;
endif;
?>

<nav class="shojaei-subnav" aria-label="<?php esc_attr_e( 'ماژول‌های هسته سئو', 'shojaei-seo-for-woo' ); ?>" style="margin-bottom:14px;display:flex;flex-wrap:wrap;gap:8px;">
	<a class="button <?php echo '' === $sub ? 'button-primary' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=shojaei-seo&tab=seo-core' ) ); ?>">
		<?php esc_html_e( 'نمای کلی', 'shojaei-seo-for-woo' ); ?>
	</a>
	<?php foreach ( $modules as $mod ) : ?>
		<?php if ( 'pulse' === $mod->get_id() ) { continue; } ?>
		<a class="button <?php echo $sub === $mod->get_id() ? 'button-primary' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=shojaei-seo&tab=seo-core&module=' . rawurlencode( $mod->get_id() ) ) ); ?>">
			<?php echo esc_html( $mod->get_label() ); ?>
		</a>
	<?php endforeach; ?>
</nav>

<?php if ( '' === $sub ) : ?>
	<div class="shojaei-pulse-stats">
		<?php foreach ( $modules as $mod ) : ?>
			<?php
			if ( 'pulse' === $mod->get_id() ) {
				continue;
			}
			$enabled = $mod->is_enabled();
			$passive = $mod->is_passive();
			$ov      = $mod->is_override_mode();
			?>
			<div class="shojaei-card shojaei-pulse-stat" style="text-align:right;padding:16px;">
				<div style="font-weight:600;margin-bottom:6px;"><?php echo esc_html( $mod->get_label() ); ?></div>
				<p class="description" style="margin:0 0 10px;"><?php echo esc_html( $mod->get_description() ); ?></p>
				<p style="margin:0 0 8px;">
					<span class="shojaei-slug-score <?php echo $enabled ? 'shojaei-tone-safe' : 'shojaei-tone-error'; ?>">
						<?php echo $enabled ? esc_html__( 'فعال', 'shojaei-seo-for-woo' ) : esc_html__( 'خاموش', 'shojaei-seo-for-woo' ); ?>
					</span>
					<?php if ( $enabled && $passive ) : ?>
						<span class="shojaei-slug-score shojaei-tone-warning"><?php esc_html_e( 'حالت کمکی', 'shojaei-seo-for-woo' ); ?></span>
					<?php elseif ( $enabled && $ov ) : ?>
						<span class="shojaei-slug-score shojaei-tone-warning"><?php esc_html_e( 'جایگزینی روشن', 'shojaei-seo-for-woo' ); ?></span>
					<?php endif; ?>
				</p>
				<label style="display:flex;align-items:center;gap:8px;margin:8px 0;">
					<input type="checkbox" class="shojaei-seo-core-toggle" data-module="<?php echo esc_attr( $mod->get_id() ); ?>" <?php checked( $enabled ); ?> />
					<?php esc_html_e( 'فعال‌سازی ماژول', 'shojaei-seo-for-woo' ); ?>
				</label>
				<?php if ( ! in_array( $mod->get_id(), array( 'monitor404', 'links', 'canonical', 'advanced-analytics' ), true ) ) : ?>
				<label style="display:flex;align-items:center;gap:8px;">
					<input type="checkbox" class="shojaei-seo-core-override" data-module="<?php echo esc_attr( $mod->get_id() ); ?>" <?php checked( $ov ); ?> <?php disabled( empty( $comps ) ); ?> />
					<?php esc_html_e( 'حالت جایگزینی (Override)', 'shojaei-seo-for-woo' ); ?>
				</label>
				<?php else : ?>
				<p class="description" style="margin:8px 0 0;"><?php esc_html_e( 'مکمل است — Override لازم نیست.', 'shojaei-seo-for-woo' ); ?></p>
				<?php endif; ?>
				<p class="description" style="margin-top:8px;">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=shojaei-seo&tab=seo-core&module=' . rawurlencode( $mod->get_id() ) ) ); ?>">
						<?php esc_html_e( 'ورود به ماژول ←', 'shojaei-seo-for-woo' ); ?>
					</a>
				</p>
			</div>
		<?php endforeach; ?>
	</div>
	<p id="shojaei-seo-core-status" class="description" aria-live="polite"></p>
<?php elseif ( 'sitemap' === $sub && isset( $modules['sitemap'] ) ) : ?>
	<?php include DAMAVAND_SEO_DIR . 'seo-core/modules/sitemap/admin-view.php'; ?>
<?php elseif ( 'indexnow' === $sub && isset( $modules['indexnow'] ) ) : ?>
	<?php include DAMAVAND_SEO_DIR . 'seo-core/modules/indexnow/admin-view.php'; ?>
<?php elseif ( 'monitor404' === $sub && isset( $modules['monitor404'] ) ) : ?>
	<?php include DAMAVAND_SEO_DIR . 'seo-core/modules/monitor-404/admin-view.php'; ?>
<?php elseif ( 'redirects' === $sub && isset( $modules['redirects'] ) ) : ?>
	<?php include DAMAVAND_SEO_DIR . 'seo-core/modules/redirects/admin-view.php'; ?>
<?php elseif ( 'links' === $sub && isset( $modules['links'] ) ) : ?>
	<?php include DAMAVAND_SEO_DIR . 'seo-core/modules/links/admin-view.php'; ?>
<?php elseif ( 'robots' === $sub && isset( $modules['robots'] ) ) : ?>
	<?php include DAMAVAND_SEO_DIR . 'seo-core/modules/robots/admin-view.php'; ?>
<?php elseif ( 'canonical' === $sub && isset( $modules['canonical'] ) ) : ?>
	<?php include DAMAVAND_SEO_DIR . 'seo-core/modules/canonical/admin-view.php'; ?>
<?php elseif ( 'schema' === $sub && isset( $modules['schema'] ) ) : ?>
	<?php include DAMAVAND_SEO_DIR . 'seo-core/modules/schema/admin-view.php'; ?>
<?php elseif ( 'advanced-analytics' === $sub && isset( $modules['advanced-analytics'] ) ) : ?>
	<?php include DAMAVAND_SEO_DIR . 'seo-core/modules/advanced-analytics/admin-view.php'; ?>
<?php elseif ( 'pulse' === $sub ) : ?>
	<div class="shojaei-card">
		<p><?php esc_html_e( 'نبض سئو اینجا تکرار نمی‌شود — از منوی «نبض سئو» استفاده کنید.', 'shojaei-seo-for-woo' ); ?></p>
		<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=shojaei-seo&tab=seo-pulse' ) ); ?>"><?php esc_html_e( 'رفتن به نبض سئو', 'shojaei-seo-for-woo' ); ?></a></p>
	</div>
<?php else : ?>
	<div class="notice notice-error"><p><?php esc_html_e( 'ماژول یافت نشد.', 'shojaei-seo-for-woo' ); ?></p></div>
<?php endif; ?>
