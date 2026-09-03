<?php
/**
 * مهاجرت از Rank Math / Yoast / AIOSEO.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Damavand_SEO_Migrator' ) ) {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'کلاس مهاجرت در دسترس نیست.', 'shojaei-seo-for-woo' ) . '</p></div>';
	return;
}

$info     = Damavand_SEO_Migrator::detect_sources();
$greeting = Damavand_SEO_Migrator::get_user_greeting();
$ready    = Damavand_SEO_Migrator::removal_readiness();

$sources = array(
	array(
		'key'    => 'rank_math',
		'label'  => 'Rank Math',
		'icon'   => 'gauge',
		'active' => ! empty( $info['rank_math_active'] ),
		'extra'  => ! empty( $info['rank_math_redirects_table'] ) ? __( 'جدول ریدایرکت موجود است', 'shojaei-seo-for-woo' ) : '',
	),
	array(
		'key'    => 'yoast',
		'label'  => 'Yoast SEO',
		'icon'   => 'file-text',
		'active' => ! empty( $info['yoast_active'] ),
		'extra'  => ( ! empty( $info['yoast_redirects_table'] ) || ! empty( $info['yoast_redirects_option'] ) ) ? __( 'داده ریدایرکت یافت شد', 'shojaei-seo-for-woo' ) : '',
	),
	array(
		'key'    => 'aioseo',
		'label'  => 'AIOSEO',
		'icon'   => 'sparkles',
		'active' => ! empty( $info['aioseo_active'] ),
		'extra'  => ! empty( $info['aioseo_posts_table'] ) ? __( 'جدول aioseo_posts', 'shojaei-seo-for-woo' ) : '',
	),
	array(
		'key'    => 'seopress',
		'label'  => 'SEOPress',
		'icon'   => 'layers',
		'active' => ! empty( $info['seopress_active'] ),
		'extra'  => ! empty( $info['seopress_redirects_table'] ) ? __( 'جدول ریدایرکت', 'shojaei-seo-for-woo' ) : '',
	),
	array(
		'key'    => 'squirrly',
		'label'  => 'Squirrly',
		'icon'   => 'search',
		'active' => ! empty( $info['squirrly_active'] ),
		'extra'  => '',
	),
	array(
		'key'    => 'tsf',
		'label'  => 'The SEO Framework',
		'icon'   => 'shield',
		'active' => ! empty( $info['tsf_active'] ),
		'extra'  => '',
	),
	array(
		'key'    => 'redirection',
		'label'  => 'Redirection',
		'icon'   => 'arrow-left-right',
		'active' => ! empty( $info['redirection_active'] ),
		'extra'  => ! empty( $info['redirection_items_table'] ) ? __( 'جدول redirection_items', 'shojaei-seo-for-woo' ) : '',
	),
	array(
		'key'    => 'slim_seo',
		'label'  => 'Slim SEO',
		'icon'   => 'minimize-2',
		'active' => ! empty( $info['slim_seo_active'] ),
		'extra'  => '',
	),
	array(
		'key'    => 'smartcrawl',
		'label'  => 'SmartCrawl',
		'icon'   => 'sparkles',
		'active' => ! empty( $info['smartcrawl_active'] ),
		'extra'  => '',
	),
	array(
		'key'    => 'wp_meta_seo',
		'label'  => 'WP Meta SEO',
		'icon'   => 'file-text',
		'active' => ! empty( $info['wp_meta_seo_active'] ),
		'extra'  => '',
	),
	array(
		'key'    => 'seo_ultimate',
		'label'  => 'SEO Ultimate',
		'icon'   => 'shield',
		'active' => ! empty( $info['seo_ultimate_active'] ),
		'extra'  => '',
	),
	array(
		'key'    => 'psp',
		'label'  => 'Premium SEO Pack',
		'icon'   => 'package',
		'active' => ! empty( $info['psp_active'] ),
		'extra'  => '',
	),
);

$dry = is_array( $info['dry_run'] ?? null ) ? $info['dry_run'] : array();
?>

<div class="dm-panel dm-panel--hero">
	<div class="dm-panel__head">
		<span class="dm-panel__ico" aria-hidden="true"><?php class_exists( 'Damavand_SEO_Icons' ) && Damavand_SEO_Icons::render( 'arrow-left-right', 20 ); ?></span>
		<div>
			<h3 class="dm-panel__title"><?php esc_html_e( 'مهاجرت از افزونه‌های سئو', 'shojaei-seo-for-woo' ); ?></h3>
			<p class="dm-panel__desc">
				<?php
				printf(
					/* translators: %s: greeting */
					esc_html__( '%s، متای عنوان/توضیح/OG/Twitter/robots و ریدایرکت‌ها را از Rank Math، Yoast، AIOSEO، SEOPress، Squirrly، TSF، Slim SEO و Redirection به Damavand منتقل کنید — دسته‌ای و امن (کپی، بدون حذف منبع).', 'shojaei-seo-for-woo' ),
					esc_html( $greeting )
				);
				?>
			</p>
		</div>
	</div>
</div>

<div class="dm-cat-grid">
	<section class="dm-panel dm-panel--fit">
		<div class="dm-panel__head">
			<span class="dm-panel__ico" aria-hidden="true"><?php class_exists( 'Damavand_SEO_Icons' ) && Damavand_SEO_Icons::render( 'plug', 18 ); ?></span>
			<h4 class="dm-panel__title"><?php esc_html_e( 'منابع تشخیص‌داده‌شده', 'shojaei-seo-for-woo' ); ?></h4>
		</div>
		<div class="dm-source-grid">
			<?php foreach ( $sources as $src ) : ?>
				<div class="dm-source-card <?php echo $src['active'] ? 'is-on' : 'is-off'; ?>">
					<span class="dm-source-card__ico" aria-hidden="true"><?php class_exists( 'Damavand_SEO_Icons' ) && Damavand_SEO_Icons::render( $src['icon'], 18 ); ?></span>
					<div class="dm-source-card__body">
						<strong><?php echo esc_html( $src['label'] ); ?></strong>
						<span class="dm-source-card__state">
							<?php
							if ( $src['active'] ) {
								class_exists( 'Damavand_SEO_Icons' ) && Damavand_SEO_Icons::render( 'circle-check', 14 );
								esc_html_e( 'فعال', 'shojaei-seo-for-woo' );
							} else {
								class_exists( 'Damavand_SEO_Icons' ) && Damavand_SEO_Icons::render( 'circle-x', 14 );
								esc_html_e( 'غیرفعال', 'shojaei-seo-for-woo' );
							}
							?>
						</span>
						<?php if ( $src['extra'] ) : ?>
							<em class="dm-source-card__extra"><?php echo esc_html( $src['extra'] ); ?></em>
						<?php endif; ?>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
		<p class="dm-panel__meta">
			<span class="dm-panel__ico-inline" aria-hidden="true"><?php class_exists( 'Damavand_SEO_Icons' ) && Damavand_SEO_Icons::render( 'database', 14 ); ?></span>
			<?php esc_html_e( 'جدول مقصد ریدایرکت:', 'shojaei-seo-for-woo' ); ?>
			<code dir="ltr"><?php echo esc_html( (string) ( $info['dest_redirects_table'] ?? '' ) ); ?></code>
		</p>
	</section>

	<section class="dm-panel dm-panel--fit">
		<div class="dm-panel__head">
			<span class="dm-panel__ico" aria-hidden="true"><?php class_exists( 'Damavand_SEO_Icons' ) && Damavand_SEO_Icons::render( 'refresh-cw', 18 ); ?></span>
			<h4 class="dm-panel__title"><?php esc_html_e( 'اجرای مهاجرت', 'shojaei-seo-for-woo' ); ?></h4>
		</div>
		<p class="dm-panel__desc" style="margin-top:0;">
			<label class="dm-check">
				<input type="checkbox" id="damavand-migrate-overwrite" />
				<?php esc_html_e( 'بازنویسی متای Damavand اگر از قبل پر باشد', 'shojaei-seo-for-woo' ); ?>
			</label>
		</p>
		<div class="dm-actions">
			<button type="button" class="button" id="damavand-migrate-dry-run"><?php esc_html_e( 'پیش‌نمایش (Dry-run)', 'shojaei-seo-for-woo' ); ?></button>
			<button type="button" class="button button-primary" id="damavand-migrate-start"><?php esc_html_e( 'شروع مهاجرت کامل', 'shojaei-seo-for-woo' ); ?></button>
			<button type="button" class="button" id="damavand-migrate-meta-only"><?php esc_html_e( 'فقط متای پست‌ها', 'shojaei-seo-for-woo' ); ?></button>
			<button type="button" class="button" id="damavand-migrate-redirects-only"><?php esc_html_e( 'فقط ریدایرکت‌ها', 'shojaei-seo-for-woo' ); ?></button>
		</div>
		<?php if ( ! empty( $dry['eligible_posts'] ) ) : ?>
			<p class="description">
				<?php
				printf(
					/* translators: %d: post count */
					esc_html__( 'پیش‌نمایش: حدود %d پست/محصول با متا قابل مهاجرت.', 'shojaei-seo-for-woo' ),
					(int) $dry['eligible_posts']
				);
				?>
			</p>
		<?php endif; ?>
		<div class="damavand-migrate-progress" id="damavand-migrate-progress" hidden>
			<div class="damavand-migrate-progress__bar"><span id="damavand-migrate-bar"></span></div>
			<p id="damavand-migrate-status" class="description" aria-live="polite"></p>
		</div>
	</section>
</div>

<div id="damavand-migrate-result" class="damavand-migrate-glass" hidden></div>

<section class="dm-panel" id="damavand-rm-ready" dir="rtl">
	<div class="dm-panel__head">
		<span class="dm-panel__ico" aria-hidden="true"><?php class_exists( 'Damavand_SEO_Icons' ) && Damavand_SEO_Icons::render( 'shield', 18 ); ?></span>
		<h4 class="dm-panel__title"><?php esc_html_e( 'چک‌لیست: آماده حذف Rank Math؟', 'shojaei-seo-for-woo' ); ?></h4>
	</div>
	<ul class="dm-ready-list" id="damavand-ready-list">
		<?php foreach ( (array) ( $ready['items'] ?? array() ) as $item ) : ?>
			<li class="<?php echo ! empty( $item['ok'] ) ? 'is-ok' : 'is-bad'; ?>" data-id="<?php echo esc_attr( (string) ( $item['id'] ?? '' ) ); ?>">
				<strong>
					<?php
					if ( ! empty( $item['ok'] ) ) {
						class_exists( 'Damavand_SEO_Icons' ) && Damavand_SEO_Icons::render( 'circle-check', 14 );
					} else {
						class_exists( 'Damavand_SEO_Icons' ) && Damavand_SEO_Icons::render( 'circle-x', 14 );
					}
					echo ' ' . esc_html( (string) ( $item['label'] ?? '' ) );
					?>
				</strong>
				<em><?php echo esc_html( (string) ( $item['detail'] ?? '' ) ); ?></em>
			</li>
		<?php endforeach; ?>
	</ul>
	<p class="dm-ready-cta" id="damavand-ready-cta" <?php echo ! empty( $ready['ready'] ) ? '' : 'hidden'; ?>>
		<button type="button" class="button button-primary" disabled>
			<?php esc_html_e( 'حالا می‌توانید Rank Math را غیرفعال کنید', 'shojaei-seo-for-woo' ); ?>
		</button>
		<span class="description"><?php esc_html_e( 'از پیشخوان → افزونه‌ها فقط غیرفعال کنید؛ چند روز تست کنید بعد حذف.', 'shojaei-seo-for-woo' ); ?></span>
	</p>
	<p class="description" id="damavand-ready-wait" <?php echo empty( $ready['ready'] ) ? '' : 'hidden'; ?>>
		<?php esc_html_e( 'تا وقتی همهٔ موارد سبز نشده‌اند، Rank Math را حذف نکنید.', 'shojaei-seo-for-woo' ); ?>
	</p>
</section>

<div class="dm-tip">
	<span aria-hidden="true"><?php class_exists( 'Damavand_SEO_Icons' ) && Damavand_SEO_Icons::render( 'lightbulb', 16 ); ?></span>
	<?php esc_html_e( 'کلیدهای مقصد: _damavand_seo_title / _damavand_seo_metadesc / _damavand_seo_canonical / _damavand_seo_focus_keyword / OG / Twitter / robots / pillar — مهاجرت فقط کپی است و متا منبع پاک نمی‌شود.', 'shojaei-seo-for-woo' ); ?>
</div>
