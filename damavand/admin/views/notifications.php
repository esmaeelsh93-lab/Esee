<?php
/**
 * Notifications tab (standalone — not injected on every screen).
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

$notifications = Shojaei_SEO_Notifications::get_all();
$unread_count  = Shojaei_SEO_Notifications::unread_count();
?>

<div class="shojaei-card">
	<div class="shojaei-notifications-header" style="margin-bottom:12px;">
		<h3 style="margin:0;">
			<span class="dashicons dashicons-bell"></span>
			<?php esc_html_e( 'اعلان‌های Shojaei SEO', 'shojaei-seo-for-woo' ); ?>
			<?php if ( $unread_count > 0 ) : ?>
				<span class="shojaei-notif-badge"><?php echo esc_html( (string) $unread_count ); ?></span>
			<?php endif; ?>
		</h3>
		<?php if ( $unread_count > 0 ) : ?>
			<button type="button" class="button button-small" id="shojaei-mark-all-read">
				<?php esc_html_e( 'علامت‌گذاری همه به‌عنوان خوانده‌شده', 'shojaei-seo-for-woo' ); ?>
			</button>
		<?php endif; ?>
	</div>
	<p class="shojaei-desc"><?php esc_html_e( 'فقط وقتی مقصد مشخصی باشد دکمه عملیات نشان داده می‌شود. برای محصول: تست همان محصول؛ برای اسکن: لیست ناموجودها.', 'shojaei-seo-for-woo' ); ?></p>
</div>

<?php if ( empty( $notifications ) ) : ?>
	<div class="shojaei-card">
		<div class="shojaei-empty-state">
			<span class="dashicons dashicons-yes-alt"></span>
			<p><?php esc_html_e( 'اعلانی نیست.', 'shojaei-seo-for-woo' ); ?></p>
		</div>
	</div>
<?php else : ?>
	<div class="shojaei-notifications-panel is-tab">
		<ul class="shojaei-notifications-list">
			<?php foreach ( $notifications as $notice ) :
				$is_unread   = empty( $notice['read'] );
				$icon        = Shojaei_SEO_Notifications::icon_for( $notice['type'] ?? '' );
				$show_link   = Shojaei_SEO_Notifications::has_action_link( $notice );
				$link_label  = (string) ( $notice['link_label'] ?? '' );
				if ( '' === $link_label ) {
					$link_label = Shojaei_SEO_Notifications::default_link_label(
						(string) ( $notice['type'] ?? '' ),
						absint( $notice['product_id'] ?? 0 ),
						(string) ( $notice['link'] ?? '' )
					);
				}
				?>
				<li class="shojaei-notification-item <?php echo $is_unread ? 'is-unread' : 'is-read'; ?>" data-id="<?php echo esc_attr( $notice['id'] ); ?>">
					<span class="shojaei-notif-icon"><span class="dashicons <?php echo esc_attr( $icon ); ?>"></span></span>
					<div class="shojaei-notif-body">
						<p><?php echo esc_html( $notice['message'] ?? '' ); ?></p>
						<small><?php echo esc_html( mysql2date( 'Y/m/d H:i', $notice['created_at'] ?? '' ) ); ?></small>
						<?php if ( $show_link ) : ?>
							<a href="<?php echo esc_url( $notice['link'] ); ?>" class="shojaei-notif-link button button-small">
								<?php echo esc_html( $link_label ); ?>
							</a>
						<?php endif; ?>
					</div>
					<div class="shojaei-notif-actions">
						<?php if ( $is_unread ) : ?>
							<button type="button" class="button-link shojaei-notif-read" title="<?php esc_attr_e( 'خوانده شد', 'shojaei-seo-for-woo' ); ?>">
								<span class="dashicons dashicons-yes"></span>
							</button>
						<?php endif; ?>
						<button type="button" class="button-link shojaei-notif-dismiss" title="<?php esc_attr_e( 'حذف', 'shojaei-seo-for-woo' ); ?>">
							<span class="dashicons dashicons-no-alt"></span>
						</button>
					</div>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endif; ?>
