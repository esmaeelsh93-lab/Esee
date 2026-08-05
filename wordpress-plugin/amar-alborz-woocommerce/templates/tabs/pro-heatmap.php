<?php
/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

/**
 * تب «Heatmap» (نسخه تجاری): نمایش نقاط کلیک و عمق اسکرول واقعی کاربران روی هر صفحه.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings        = AAW_Admin::get_settings();
$heatmap_enabled = ! empty( $settings['heatmap_enabled'] );
$view_hash       = isset( $_GET['view'] ) ? sanitize_text_field( wp_unslash( $_GET['view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$pages           = AAW_DB::get_tracked_pages( $from, $to, 30 );
?>
<div class="aaw-pro-intro">
	<div class="aaw-pro-badge">نسخه تجاری</div>
	<h2>Heatmap — نقشه‌ی حرارتی کلیک و اسکرول</h2>
	<p>محل واقعی کلیک‌ها و میزان واقعی اسکرول کاربران روی هر صفحه از فروشگاه را ببینید تا بفهمید کدام بخش‌ها بیشترین توجه را جذب می‌کنند.</p>
	<?php if ( ! $heatmap_enabled ) : ?>
		<div class="aaw-notice aaw-notice-warning">
			ردیابی Heatmap در حال حاضر غیرفعال است. برای فعال‌سازی به
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AAW_Admin::PAGE_SETTINGS . '&tab=general' ) ); ?>">تنظیمات ← عمومی</a> بروید.
		</div>
	<?php endif; ?>
</div>

<?php if ( $view_hash ) : ?>
	<?php
	$click_points  = AAW_DB::get_click_points( $view_hash, $from, $to );
	$scroll_depths = AAW_DB::get_scroll_depth_distribution( $view_hash, $from, $to );
	$page_url      = '';
	foreach ( $pages as $p ) {
		if ( $p->page_url_hash === $view_hash ) {
			$page_url = $p->page_url;
			break;
		}
	}
	?>
	<div class="aaw-panel">
		<div class="aaw-panel-header">
			<h2>نقشه‌ی حرارتی: <?php echo esc_html( wp_parse_url( $page_url, PHP_URL_PATH ) ); ?></h2>
			<a class="aaw-panel-link" href="<?php echo esc_url( remove_query_arg( 'view' ) ); ?>">← بازگشت به فهرست صفحات</a>
		</div>
		<div class="aaw-heatmap-frame-wrap" id="aawHeatmapFrameWrap" data-points="<?php echo esc_attr( wp_json_encode( $click_points ) ); ?>">
			<iframe id="aawHeatmapFrame" src="<?php echo esc_url( $page_url ); ?>" loading="lazy"></iframe>
			<canvas id="aawHeatmapCanvas"></canvas>
		</div>
	</div>

	<div class="aaw-panel">
		<div class="aaw-panel-header"><h2>عمق اسکرول</h2></div>
		<div class="aaw-bar-list">
			<?php foreach ( $scroll_depths as $bucket => $percent ) : ?>
				<div class="aaw-bar-row">
					<div class="aaw-bar-row-label">تا <?php echo esc_html( AAW_Jalali::to_persian_digits( $bucket ) ); ?>٪ صفحه</div>
					<div class="aaw-bar-track">
						<div class="aaw-bar-fill" style="width: <?php echo esc_attr( $percent ); ?>%;"></div>
					</div>
					<div class="aaw-bar-row-value"><?php echo esc_html( AAW_Jalali::to_persian_digits( $percent ) ); ?>٪ بازدیدکنندگان</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
<?php else : ?>
	<div class="aaw-table-panel">
		<div class="aaw-panel-header"><h2>صفحات ردیابی‌شده</h2></div>
		<?php if ( empty( $pages ) ) : ?>
			<div class="aaw-empty-state">هنوز داده‌ای ثبت نشده است. پس از فعال‌سازی Heatmap از تنظیمات، داده‌ها به‌مرور نمایش داده می‌شوند.</div>
		<?php else : ?>
			<div class="aaw-table-scroll">
				<table class="aaw-table">
					<thead>
						<tr>
							<th>صفحه</th>
							<th>تعداد کلیک</th>
							<th>تعداد رویداد کل</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $pages as $p ) : ?>
							<tr>
								<td><?php echo esc_html( wp_parse_url( $p->page_url, PHP_URL_PATH ) ); ?></td>
								<td><?php echo esc_html( AAW_Jalali::format_number( $p->clicks ) ); ?></td>
								<td><?php echo esc_html( AAW_Jalali::format_number( $p->total ) ); ?></td>
								<td><a class="aaw-btn aaw-btn-primary" href="<?php echo esc_url( add_query_arg( 'view', $p->page_url_hash ) ); ?>">مشاهده Heatmap</a></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>
<?php endif; ?>
