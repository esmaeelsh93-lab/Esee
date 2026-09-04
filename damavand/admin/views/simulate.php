<?php
/**
 * Dry-Run trust UX + Undo / Revert Log.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

$filter_mode = sanitize_text_field( wp_unslash( $_GET['revert_mode'] ?? 'all' ) );
if ( ! in_array( $filter_mode, array( 'all', 'applied', 'dry_run' ), true ) ) {
	$filter_mode = 'all';
}

$logs = class_exists( 'Shojaei_SEO_Revert_Log' )
	? Shojaei_SEO_Revert_Log::get_recent( 80, $filter_mode )
	: array();

$oos_candidates = array();
if ( class_exists( 'Shojaei_SEO_Helpers' ) ) {
	global $wpdb;
	$statuses = "'" . implode( "','", array_map( 'esc_sql', Shojaei_SEO_Helpers::active_oos_statuses() ) ) . "'";
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
	$oos_candidates = $wpdb->get_results(
		"SELECT t.product_id, p.post_title, t.days_oos, t.status
		FROM " . Shojaei_SEO_Helpers::oos_table() . " t
		LEFT JOIN {$wpdb->posts} p ON p.ID = t.product_id
		WHERE t.status IN ({$statuses})
		ORDER BY t.days_oos DESC
		LIMIT 100"
	);
}

$preview_posts = get_posts(
	array(
		'post_type'      => array( 'post', 'product' ),
		'post_status'    => 'publish',
		'posts_per_page' => 50,
		'orderby'        => 'date',
		'order'          => 'DESC',
	)
);

$last_report = class_exists( 'Shojaei_SEO_Revert_Log' ) ? Shojaei_SEO_Revert_Log::get_dry_run_report() : null;
?>

<div class="shojaei-ops-hero shojaei-dryrun-hero">
	<h2><?php esc_html_e( 'Dry-Run — پیش‌نمایش قبل از هر تغییر انبوه', 'shojaei-seo-for-woo' ); ?></h2>
	<p><?php esc_html_e( 'هیچ چیزی روی فروشگاه اعمال نمی‌شود تا خودتان تأیید کنید. تعداد آیتم‌ها، نوع تغییر، ریسک، خروجی CSV و اجرای واقعی از روی همان پیش‌نمایش — برای اعتماد در بازار ایران.', 'shojaei-seo-for-woo' ); ?></p>
	<div class="shojaei-ops-pills">
		<span class="shojaei-ops-pill"><span class="dashicons dashicons-visibility"></span><?php esc_html_e( 'شفافیت کامل', 'shojaei-seo-for-woo' ); ?></span>
		<span class="shojaei-ops-pill"><span class="dashicons dashicons-warning"></span><?php esc_html_e( 'هشدار ریسک', 'shojaei-seo-for-woo' ); ?></span>
		<span class="shojaei-ops-pill"><span class="dashicons dashicons-media-spreadsheet"></span><?php esc_html_e( 'خروجی CSV', 'shojaei-seo-for-woo' ); ?></span>
		<span class="shojaei-ops-pill"><span class="dashicons dashicons-yes"></span><?php esc_html_e( 'اعمال از پیش‌نمایش', 'shojaei-seo-for-woo' ); ?></span>
	</div>
</div>

<div class="shojaei-card" id="shojaei-dryrun-studio">
	<h3><?php esc_html_e( 'استودیوی شبیه‌سازی', 'shojaei-seo-for-woo' ); ?></h3>
	<p class="shojaei-desc"><?php esc_html_e( 'محصولات را انتخاب کنید، شبیه‌سازی کنید، گزارش را بررسی کنید — سپس خروجی بگیرید یا اجرای واقعی را شروع کنید.', 'shojaei-seo-for-woo' ); ?></p>

	<div class="shojaei-settings-grid" style="margin-bottom:20px;">
		<div>
			<h4><?php esc_html_e( 'ریدایرکت انبوه', 'shojaei-seo-for-woo' ); ?></h4>
			<select id="shojaei-dryrun-action">
				<option value="redirect_302"><?php esc_html_e( 'ریدایرکت ۳۰۲', 'shojaei-seo-for-woo' ); ?></option>
				<option value="redirect_301"><?php esc_html_e( 'ریدایرکت ۳۰۱', 'shojaei-seo-for-woo' ); ?></option>
				<option value="redirect_410"><?php esc_html_e( '410 Gone', 'shojaei-seo-for-woo' ); ?></option>
				<option value="keep"><?php esc_html_e( 'نگهداری صفحه', 'shojaei-seo-for-woo' ); ?></option>
			</select>
			<input type="url" id="shojaei-dryrun-target" class="shojaei-target-url" placeholder="<?php esc_attr_e( 'مقصد گروهی (اختیاری)', 'shojaei-seo-for-woo' ); ?>" />
			<select id="shojaei-dryrun-products" multiple size="8" style="width:100%;margin-top:8px;">
				<?php foreach ( $oos_candidates as $row ) : ?>
					<option value="<?php echo esc_attr( $row->product_id ); ?>">
						<?php echo esc_html( $row->post_title . ' — ' . $row->days_oos . ' روز' ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<p class="description"><?php esc_html_e( 'Ctrl/Cmd برای انتخاب چندتایی.', 'shojaei-seo-for-woo' ); ?></p>
			<button type="button" class="button button-primary" id="shojaei-dryrun-redirect"><?php esc_html_e( 'شبیه‌سازی ریدایرکت', 'shojaei-seo-for-woo' ); ?></button>
		</div>
		<div>
			<h4><?php esc_html_e( 'لینک‌سازی داخلی', 'shojaei-seo-for-woo' ); ?></h4>
			<select id="shojaei-dryrun-post">
				<option value=""><?php esc_html_e( '— انتخاب نوشته/محصول —', 'shojaei-seo-for-woo' ); ?></option>
				<?php foreach ( $preview_posts as $p ) : ?>
					<option value="<?php echo esc_attr( $p->ID ); ?>"><?php echo esc_html( $p->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
			<p class="description"><?php esc_html_e( 'نشان می‌دهد چه لینک‌هایی در کش ساخته می‌شود (محتوای post دست‌نخورده).', 'shojaei-seo-for-woo' ); ?></p>
			<button type="button" class="button button-primary" id="shojaei-dryrun-links"><?php esc_html_e( 'شبیه‌سازی لینک‌سازی', 'shojaei-seo-for-woo' ); ?></button>
		</div>
	</div>

	<div id="shojaei-dryrun-result" class="shojaei-dryrun-report" <?php echo $last_report ? '' : 'style="display:none;"'; ?>
		data-batch="<?php echo esc_attr( (string) ( $last_report['batch_id'] ?? '' ) ); ?>">
		<?php if ( $last_report ) : ?>
			<p class="description"><?php esc_html_e( 'آخرین گزارش Dry-Run بارگذاری شد. می‌توانید دوباره شبیه‌سازی کنید یا از همین گزارش خروجی/اجرا بگیرید.', 'shojaei-seo-for-woo' ); ?></p>
		<?php endif; ?>
	</div>
</div>

<div id="shojaei-undo-preview" class="shojaei-card" style="display:none;">
	<h3><?php esc_html_e( 'پیش‌نمایش Undo', 'shojaei-seo-for-woo' ); ?></h3>
	<div id="shojaei-undo-preview-body"></div>
	<p style="margin-top:12px;">
		<button type="button" class="button button-primary" id="shojaei-undo-confirm"><?php esc_html_e( 'بازگردانی را اعمال کن', 'shojaei-seo-for-woo' ); ?></button>
		<button type="button" class="button" id="shojaei-undo-cancel"><?php esc_html_e( 'انصراف', 'shojaei-seo-for-woo' ); ?></button>
	</p>
</div>

<div class="shojaei-card">
	<h3><?php esc_html_e( 'تاریخچه تغییرات (Revert Log / Undo)', 'shojaei-seo-for-woo' ); ?></h3>
	<p class="shojaei-desc"><?php esc_html_e( 'پس از اجرای واقعی از Dry-Run، بازگردانی تکی یا دسته‌ای از اینجا با پیش‌نمایش Undo انجام می‌شود.', 'shojaei-seo-for-woo' ); ?></p>

	<form method="get" class="shojaei-filter-form">
		<input type="hidden" name="page" value="shojaei-seo" />
		<input type="hidden" name="tab" value="simulate" />
		<select name="revert_mode">
			<option value="all" <?php selected( $filter_mode, 'all' ); ?>><?php esc_html_e( 'همه', 'shojaei-seo-for-woo' ); ?></option>
			<option value="applied" <?php selected( $filter_mode, 'applied' ); ?>><?php esc_html_e( 'اعمال‌شده', 'shojaei-seo-for-woo' ); ?></option>
			<option value="dry_run" <?php selected( $filter_mode, 'dry_run' ); ?>><?php esc_html_e( 'فقط شبیه‌سازی', 'shojaei-seo-for-woo' ); ?></option>
		</select>
		<button type="submit" class="button"><?php esc_html_e( 'فیلتر', 'shojaei-seo-for-woo' ); ?></button>
	</form>

	<?php if ( empty( $logs ) ) : ?>
		<div class="shojaei-empty-state">
			<span class="dashicons dashicons-backup"></span>
			<p><?php esc_html_e( 'هنوز رکوردی ثبت نشده است.', 'shojaei-seo-for-woo' ); ?></p>
		</div>
	<?php else : ?>
		<table class="shojaei-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'زمان', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'حالت', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'عملیات', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'خلاصه', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'بچ', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'بازگردانی', 'shojaei-seo-for-woo' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $logs as $row ) : ?>
					<tr>
						<td><?php echo esc_html( mysql2date( 'Y/m/d H:i', $row->created_at ) ); ?></td>
						<td>
							<?php if ( 'dry_run' === $row->mode ) : ?>
								<span class="shojaei-badge shojaei-badge-type"><?php esc_html_e( 'شبیه‌سازی', 'shojaei-seo-for-woo' ); ?></span>
							<?php else : ?>
								<span class="shojaei-badge shojaei-badge-301"><?php esc_html_e( 'اعمال‌شده', 'shojaei-seo-for-woo' ); ?></span>
							<?php endif; ?>
							<?php if ( (int) $row->is_reverted ) : ?>
								<span class="shojaei-badge shojaei-badge-noindex"><?php esc_html_e( 'Rollback شد', 'shojaei-seo-for-woo' ); ?></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( Shojaei_SEO_Revert_Log::action_label( $row->action ) ); ?></td>
						<td><?php echo esc_html( $row->summary ); ?></td>
						<td><code dir="ltr" style="font-size:11px;"><?php echo esc_html( substr( $row->batch_id, 0, 8 ) ); ?>…</code></td>
						<td>
							<?php if ( 'applied' === $row->mode && ! (int) $row->is_reverted && class_exists( 'Shojaei_SEO_Revert_Log' ) && Shojaei_SEO_Revert_Log::is_undoable( (string) $row->action ) ) : ?>
								<button type="button" class="button button-primary shojaei-btn-undo-preview" data-scope="one" data-id="<?php echo esc_attr( $row->id ); ?>">
									<?php esc_html_e( 'پیش‌نمایش Undo', 'shojaei-seo-for-woo' ); ?>
								</button>
								<button type="button" class="button shojaei-btn-undo-preview" data-scope="batch" data-batch="<?php echo esc_attr( $row->batch_id ); ?>">
									<?php esc_html_e( 'پیش‌نمایش دسته', 'shojaei-seo-for-woo' ); ?>
								</button>
							<?php elseif ( 'applied' === $row->mode && ! (int) $row->is_reverted ) : ?>
								<span class="description"><?php esc_html_e( 'بدون Undo', 'shojaei-seo-for-woo' ); ?></span>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
