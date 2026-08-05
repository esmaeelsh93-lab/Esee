<?php
/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

/**
 * تب «هشدار هوشمند» (نسخه تجاری): فهرست هشدارهای ثبت‌شده بر اساس مقایسه‌ی واقعی داده‌ها.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$alerts        = AAW_DB::get_recent_alerts( 30 );
$mark_read_url = wp_nonce_url(
	add_query_arg( array( 'action' => 'aaw_mark_alerts_read' ), admin_url( 'admin-post.php' ) ),
	'aaw_mark_alerts_read'
);
?>
<div class="aaw-pro-intro">
	<div class="aaw-pro-badge">نسخه تجاری</div>
	<h2>هشدار هوشمند</h2>
	<p>هر روز به‌صورت خودکار، وضعیت ۷ روز اخیر با ۷ روز قبل از آن مقایسه می‌شود و در صورت افت نرخ تبدیل، افزایش نرخ خروج بدون تعامل، کاهش فروش یا افزایش سبدهای رها شده، هشدار ثبت می‌شود.</p>
	<a class="aaw-btn" href="<?php echo esc_url( $mark_read_url ); ?>">✓ علامت‌گذاری همه به‌عنوان خوانده‌شده</a>
</div>

<div class="aaw-table-panel">
	<?php if ( empty( $alerts ) ) : ?>
		<div class="aaw-empty-state">تا امروز هشداری ثبت نشده است؛ یعنی وضعیت فروشگاه شما پایدار بوده.</div>
	<?php else : ?>
		<div class="aaw-alert-list">
			<?php foreach ( $alerts as $alert ) : ?>
				<div class="aaw-alert-item is-<?php echo esc_attr( $alert->severity ); ?> <?php echo $alert->is_read ? 'is-read' : ''; ?>">
					<div class="aaw-alert-item-icon" aria-hidden="true"><?php echo 'critical' === $alert->severity ? '🔴' : '🟡'; ?></div>
					<div class="aaw-alert-item-body">
						<strong><?php echo esc_html( $alert->title ); ?></strong>
						<p><?php echo esc_html( $alert->message ); ?></p>
						<span class="aaw-alert-item-time"><?php echo esc_html( AAW_Jalali::format_datetime( $alert->created_at ) ); ?></span>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
