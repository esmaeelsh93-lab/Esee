<?php
/**
 * UI مانیتور ۴۰۴.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/** @var SEO_Core_404_Monitor $monitor */
$monitor = $modules['monitor404'] ?? null;
if ( ! $monitor instanceof SEO_Core_404_Monitor ) {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'ماژول مانیتور ۴۰۴ در دسترس نیست.', 'shojaei-seo-for-woo' ) . '</p></div>';
	return;
}

$filter = isset( $_GET['404_status'] ) ? sanitize_key( wp_unslash( $_GET['404_status'] ) ) : 'open'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
if ( ! in_array( $filter, array( 'open', 'ignored', 'fixed', 'all' ), true ) ) {
	$filter = 'open';
}

$stats      = SEO_Core_404_Monitor::stats();
$rows       = SEO_Core_404_Monitor::list_rows( $filter, 80, 0 );
$retention  = absint( get_option( SEO_Core_404_Monitor::OPTION_RETENTION, 30 ) );
$ignore_bots = 'yes' === (string) get_option( SEO_Core_404_Monitor::OPTION_IGNORE_BOTS, 'yes' );
$base       = admin_url( 'admin.php?page=shojaei-seo&tab=seo-core&module=monitor404' );
$comps      = class_exists( 'Shojaei_SEO_Integration' ) ? Shojaei_SEO_Integration::detected_seo_plugins() : array();
?>

<div class="shojaei-card">
	<h3 style="margin-top:0;"><?php esc_html_e( 'مانیتور ۴۰۴', 'shojaei-seo-for-woo' ); ?></h3>
	<p class="shojaei-desc"><?php echo esc_html( $monitor->get_description() ); ?></p>
	<?php if ( ! empty( $comps ) ) : ?>
		<div class="notice notice-info inline">
			<p><?php esc_html_e( 'این ماژول مکمل است و با مانیتور ۴۰۴ Rank Math/Yoast تداخل ندارد — فقط مسیرهای ۴۰۴ واقعی را برای ساخت ریدایرکت ثبت می‌کند.', 'shojaei-seo-for-woo' ); ?></p>
		</div>
	<?php endif; ?>
</div>

<div class="shojaei-pulse-stats" style="margin-bottom:14px;">
	<div class="shojaei-card shojaei-pulse-stat" style="text-align:right;padding:14px;">
		<div class="description"><?php esc_html_e( 'مسیرهای باز', 'shojaei-seo-for-woo' ); ?></div>
		<strong style="font-size:1.4em;"><?php echo esc_html( (string) $stats['open'] ); ?></strong>
	</div>
	<div class="shojaei-card shojaei-pulse-stat" style="text-align:right;padding:14px;">
		<div class="description"><?php esc_html_e( 'کل بازدید ۴۰۴', 'shojaei-seo-for-woo' ); ?></div>
		<strong style="font-size:1.4em;"><?php echo esc_html( (string) $stats['hits'] ); ?></strong>
	</div>
	<div class="shojaei-card shojaei-pulse-stat" style="text-align:right;padding:14px;">
		<div class="description"><?php esc_html_e( 'نادیده', 'shojaei-seo-for-woo' ); ?></div>
		<strong style="font-size:1.4em;"><?php echo esc_html( (string) $stats['ignored'] ); ?></strong>
	</div>
	<div class="shojaei-card shojaei-pulse-stat" style="text-align:right;padding:14px;">
		<div class="description"><?php esc_html_e( 'کل ردیف‌ها', 'shojaei-seo-for-woo' ); ?></div>
		<strong style="font-size:1.4em;"><?php echo esc_html( (string) $stats['total'] ); ?></strong>
	</div>
</div>

<div class="shojaei-card">
	<h4 style="margin-top:0;"><?php esc_html_e( 'تنظیمات', 'shojaei-seo-for-woo' ); ?></h4>
	<p style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;margin:0 0 10px;">
		<label>
			<?php esc_html_e( 'نگهداری (روز):', 'shojaei-seo-for-woo' ); ?>
			<input type="number" id="shojaei-404-retention" min="7" max="365" value="<?php echo esc_attr( (string) $retention ); ?>" style="width:80px;" />
		</label>
		<label style="display:flex;align-items:center;gap:6px;">
			<input type="checkbox" id="shojaei-404-ignore-bots" <?php checked( $ignore_bots ); ?> />
			<?php esc_html_e( 'نادیده گرفتن ربات‌ها', 'shojaei-seo-for-woo' ); ?>
		</label>
		<button type="button" class="button button-primary" id="shojaei-404-save-settings"><?php esc_html_e( 'ذخیره', 'shojaei-seo-for-woo' ); ?></button>
		<button type="button" class="button" id="shojaei-404-purge"><?php esc_html_e( 'پاک‌سازی قدیمی‌ها اکنون', 'shojaei-seo-for-woo' ); ?></button>
		<button type="button" class="button" id="shojaei-404-clear-open"><?php esc_html_e( 'پاک کردن همهٔ بازها', 'shojaei-seo-for-woo' ); ?></button>
	</p>
	<p id="shojaei-404-status" class="description" aria-live="polite"></p>
</div>

<nav class="shojaei-subnav" style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:12px;" aria-label="<?php esc_attr_e( 'فیلتر ۴۰۴', 'shojaei-seo-for-woo' ); ?>">
	<?php
	$filters = array(
		'open'    => __( 'باز', 'shojaei-seo-for-woo' ),
		'ignored' => __( 'نادیده', 'shojaei-seo-for-woo' ),
		'fixed'   => __( 'رفع‌شده', 'shojaei-seo-for-woo' ),
		'all'     => __( 'همه', 'shojaei-seo-for-woo' ),
	);
	foreach ( $filters as $key => $label ) :
		?>
		<a class="button <?php echo $filter === $key ? 'button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( '404_status', $key, $base ) ); ?>">
			<?php echo esc_html( $label ); ?>
		</a>
	<?php endforeach; ?>
</nav>

<div class="shojaei-card">
	<?php if ( empty( $rows ) ) : ?>
		<p class="description"><?php esc_html_e( 'هنوز مسیری ثبت نشده است. پس از بازدید واقعی از یک URL ناموجود، اینجا ظاهر می‌شود.', 'shojaei-seo-for-woo' ); ?></p>
	<?php else : ?>
		<table class="widefat striped shojaei-table" id="shojaei-404-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'مسیر', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'بازدید', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'ارجاع‌دهنده', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'آخرین', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'وضعیت', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'اقدام', 'shojaei-seo-for-woo' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<?php
					$status_labels = array(
						'open'    => __( 'باز', 'shojaei-seo-for-woo' ),
						'ignored' => __( 'نادیده', 'shojaei-seo-for-woo' ),
						'fixed'   => __( 'رفع‌شده', 'shojaei-seo-for-woo' ),
					);
					$st = (string) $row->status;
					?>
					<tr data-id="<?php echo esc_attr( (string) $row->id ); ?>">
						<td><code dir="ltr"><?php echo esc_html( (string) $row->url_path ); ?></code></td>
						<td><?php echo esc_html( (string) (int) $row->hits ); ?></td>
						<td style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;" title="<?php echo esc_attr( (string) $row->referer ); ?>">
							<?php echo $row->referer ? esc_html( (string) $row->referer ) : '—'; ?>
						</td>
						<td><?php echo esc_html( (string) $row->last_seen ); ?></td>
						<td><?php echo esc_html( $status_labels[ $st ] ?? $st ); ?></td>
						<td style="white-space:nowrap;">
							<?php if ( 'open' === $st ) : ?>
								<button type="button" class="button button-small shojaei-404-ignore"><?php esc_html_e( 'نادیده', 'shojaei-seo-for-woo' ); ?></button>
								<button type="button" class="button button-small shojaei-404-redirect"><?php esc_html_e( 'ریدایرکت ۳۰۱', 'shojaei-seo-for-woo' ); ?></button>
							<?php elseif ( 'ignored' === $st ) : ?>
								<button type="button" class="button button-small shojaei-404-reopen"><?php esc_html_e( 'بازگشایی', 'shojaei-seo-for-woo' ); ?></button>
							<?php endif; ?>
							<button type="button" class="button button-small shojaei-404-delete"><?php esc_html_e( 'حذف', 'shojaei-seo-for-woo' ); ?></button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<div class="shojaei-edu-tip">
	<span class="dashicons dashicons-lightbulb"></span>
	<?php esc_html_e( 'نکته: ثبت فقط بعد از شکست ریدایرکت‌های موجود انجام می‌شود. برای مسیرهای پربازدید، ریدایرکت ۳۰۱ بسازید تا Soft 404 و هدر رفتن بودجه خزش کم شود.', 'shojaei-seo-for-woo' ); ?>
	<a href="<?php echo esc_url( admin_url( 'admin.php?page=shojaei-seo&tab=manual-redirects' ) ); ?>"><?php esc_html_e( 'ریدایرکت‌های دستی ←', 'shojaei-seo-for-woo' ); ?></a>
</div>
