<?php
/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

/**
 * تب «Session Replay» (نسخه تجاری): فهرست نشست‌های ضبط‌شده و پخش مجدد مسیر حرکت کاربر.
 * هرگز مقدار فیلدهای فرم ذخیره نمی‌شود؛ فقط مسیر حرکت/کلیک/اسکرول.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings       = AAW_Admin::get_settings();
$replay_enabled = ! empty( $settings['replay_enabled'] );
$view_session   = isset( $_GET['view'] ) ? sanitize_text_field( wp_unslash( $_GET['view'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
?>
<div class="aaw-pro-intro">
	<div class="aaw-pro-badge">نسخه تجاری</div>
	<h2>Session Replay — پخش مجدد مسیر کاربر</h2>
	<p>مسیر حرکت، کلیک و اسکرول واقعی کاربران را به‌صورت انیمیشن پخش کنید؛ بدون ذخیره‌ی هیچ اطلاعات حساس فرم (رمز عبور، شماره کارت، اطلاعات تماس و ...).</p>
	<?php if ( ! $replay_enabled ) : ?>
		<div class="aaw-notice aaw-notice-warning">
			ضبط Session Replay در حال حاضر غیرفعال است. برای فعال‌سازی به
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . AAW_Admin::PAGE_SETTINGS . '&tab=general' ) ); ?>">تنظیمات ← عمومی</a> بروید.
		</div>
	<?php endif; ?>
</div>

<?php if ( $view_session ) : ?>
	<?php
	$events = AAW_DB::get_replay_events( $view_session );
	$first_url = '';
	foreach ( $events as $event ) {
		if ( ! empty( $event->page_url ) ) {
			$first_url = $event->page_url;
			break;
		}
	}
	?>
	<div class="aaw-panel">
		<div class="aaw-panel-header">
			<h2>پخش نشست</h2>
			<a class="aaw-panel-link" href="<?php echo esc_url( remove_query_arg( 'view' ) ); ?>">← بازگشت به فهرست نشست‌ها</a>
		</div>
		<?php if ( empty( $events ) ) : ?>
			<div class="aaw-empty-state">رویدادی برای این نشست ثبت نشده است.</div>
		<?php else : ?>
			<div class="aaw-replay-frame-wrap" id="aawReplayFrameWrap" data-events="<?php echo esc_attr( wp_json_encode( $events ) ); ?>">
				<iframe id="aawReplayFrame" src="<?php echo esc_url( $first_url ); ?>" loading="lazy"></iframe>
				<canvas id="aawReplayCanvas"></canvas>
			</div>
			<div class="aaw-replay-controls">
				<button type="button" class="aaw-btn aaw-btn-primary" id="aawReplayPlay">▶ پخش</button>
				<button type="button" class="aaw-btn" id="aawReplayPause">⏸ توقف</button>
				<input type="range" id="aawReplaySeek" min="0" max="1000" value="0" />
				<select id="aawReplaySpeed">
					<option value="1">۱x</option>
					<option value="2">۲x</option>
					<option value="4">۴x</option>
				</select>
			</div>
		<?php endif; ?>
	</div>
<?php else : ?>
	<?php $sessions = AAW_DB::get_replay_sessions( $from, $to, 30 ); ?>
	<div class="aaw-table-panel">
		<div class="aaw-panel-header"><h2>نشست‌های ضبط‌شده</h2></div>
		<?php if ( empty( $sessions ) ) : ?>
			<div class="aaw-empty-state">هنوز نشستی ضبط نشده است.</div>
		<?php else : ?>
			<div class="aaw-table-scroll">
				<table class="aaw-table">
					<thead>
						<tr>
							<th>شروع</th>
							<th>دستگاه</th>
							<th>مرورگر</th>
							<th>تعداد صفحه</th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $sessions as $s ) : ?>
							<tr>
								<td><?php echo esc_html( AAW_Jalali::format_datetime( $s->started_at ) ); ?></td>
								<td><?php echo esc_html( AAW_Device_Detector::device_label( $s->device_type ) ); ?></td>
								<td><?php echo esc_html( $s->browser ); ?></td>
								<td><?php echo esc_html( AAW_Jalali::format_number( $s->page_count ) ); ?></td>
								<td><a class="aaw-btn aaw-btn-primary" href="<?php echo esc_url( add_query_arg( 'view', $s->session_id ) ); ?>">پخش</a></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		<?php endif; ?>
	</div>
<?php endif; ?>
