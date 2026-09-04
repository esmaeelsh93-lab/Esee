<?php
/**
 * Admin activity log view.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

$logs = class_exists( 'Shojaei_SEO_Activity_Log' ) ? Shojaei_SEO_Activity_Log::get_recent( 80 ) : array();
?>

<div class="shojaei-card" dir="rtl">
	<h3><?php esc_html_e( 'لاگ اقدامات', 'shojaei-seo-for-woo' ); ?></h3>
	<p class="shojaei-desc"><?php esc_html_e( 'تاریخچه کارهایی که در افزونه انجام شده — فقط روی همین سایت ذخیره می‌شود.', 'shojaei-seo-for-woo' ); ?></p>

	<?php if ( empty( $logs ) ) : ?>
		<div class="shojaei-empty-state">
			<span class="dashicons dashicons-list-view"></span>
			<p><?php esc_html_e( 'هنوز چیزی ثبت نشده.', 'shojaei-seo-for-woo' ); ?></p>
		</div>
	<?php else : ?>
		<table class="shojaei-table shojaei-activity-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'زمان', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'کاربر', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'نوع کار', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'محصول', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'توضیح', 'shojaei-seo-for-woo' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $logs as $row ) :
					$user = $row->user_id ? get_userdata( (int) $row->user_id ) : null;
					$user_label = $user ? $user->display_name : __( 'سیستم', 'shojaei-seo-for-woo' );
					$product_title = $row->product_id ? get_the_title( (int) $row->product_id ) : '—';
					$msg = wp_strip_all_tags( (string) $row->message );
					// ایمیل‌ها و URL را LTR نگه می‌داریم تا خوانا بمانند.
					?>
					<tr>
						<td><?php echo esc_html( mysql2date( 'Y/m/d H:i', $row->created_at ) ); ?></td>
						<td><?php echo esc_html( $user_label ); ?></td>
						<td><span class="shojaei-badge shojaei-badge-type"><?php echo esc_html( Shojaei_SEO_Activity_Log::label( $row->action ) ); ?></span></td>
						<td>
							<?php if ( $row->product_id ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( (int) $row->product_id ) ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $product_title ); ?></a>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
						<td class="shojaei-log-message"><?php echo esc_html( $msg ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
