<?php
/**
 * Link Watchdog — نگهبان لینک (inventory + fixes).
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Shojaei_SEO_Link_Genius' ) ) {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'ماژول نابغه لینک در دسترس نیست.', 'shojaei-seo-for-woo' ) . '</p></div>';
	return;
}

$type   = isset( $_GET['type'] ) ? sanitize_key( wp_unslash( $_GET['type'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$status = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : 'all'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$q      = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$page   = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$result = Shojaei_SEO_Link_Genius::query_inventory(
	array(
		'type'   => $type,
		'status' => $status,
		'q'      => $q,
		'page'   => $page,
	)
);
$counts      = Shojaei_SEO_Link_Genius::inventory_counts();
$base        = admin_url( 'admin.php?page=shojaei-seo&tab=link-inventory' );
$busy        = class_exists( 'Shojaei_SEO_Jobs' ) && Shojaei_SEO_Jobs::has_active( 'link_inventory_crawl' );
$watch       = class_exists( 'Damavand_Link_Watchdog' ) ? Damavand_Link_Watchdog::get_alerts() : array();
$watch_open  = class_exists( 'Damavand_Link_Watchdog' ) ? Damavand_Link_Watchdog::open_count() : 0;
$open_rows   = array_values(
	array_filter(
		$watch,
		static function ( $row ) {
			return empty( $row['resolved'] );
		}
	)
);
$can_fix     = class_exists( 'Damavand_Link_Suggestions' );
?>

<div class="shojaei-card">
	<h3><?php esc_html_e( 'نگهبان لینک', 'shojaei-seo-for-woo' ); ?></h3>
	<p class="shojaei-desc">
		<?php esc_html_e( 'لینک‌های داخل محتوا را پیدا می‌کند؛ برای شکسته و ریدایرکت می‌توانید مستقیم از همینجا اصلاح کنید.', 'shojaei-seo-for-woo' ); ?>
	</p>
	<p class="shojaei-desc">
		<?php
		printf(
			/* translators: 1: total 2: broken 3: redirect 4: watchdog alerts */
			esc_html__( 'موجودی: %1$d · شکسته %2$d · ریدایرکت %3$d · هشدار نگهبان %4$d', 'shojaei-seo-for-woo' ),
			(int) $counts['total'],
			(int) $counts['broken'],
			(int) $counts['redirect'],
			(int) $watch_open
		);
		?>
	</p>
	<p style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
		<button type="button" class="button button-primary" id="shojaei-lg-crawl" <?php disabled( $busy ); ?>>
			<?php echo $busy ? esc_html__( 'اسکن در حال اجرا…', 'shojaei-seo-for-woo' ) : esc_html__( 'اسکن مجدد محتوا', 'shojaei-seo-for-woo' ); ?>
		</button>
		<button type="button" class="button" id="shojaei-lg-http-check"><?php esc_html_e( 'بررسی وضعیت HTTP', 'shojaei-seo-for-woo' ); ?></button>
		<?php if ( (int) $counts['broken'] > 0 ) : ?>
			<a class="button" href="<?php echo esc_url( add_query_arg( array( 'status' => 'broken' ), $base ) ); ?>"><?php esc_html_e( 'فقط شکسته‌ها', 'shojaei-seo-for-woo' ); ?></a>
		<?php endif; ?>
		<?php if ( (int) $counts['redirect'] > 0 ) : ?>
			<a class="button" href="<?php echo esc_url( add_query_arg( array( 'status' => 'redirect' ), $base ) ); ?>"><?php esc_html_e( 'فقط ریدایرکت‌ها', 'shojaei-seo-for-woo' ); ?></a>
		<?php endif; ?>
		<span id="shojaei-lg-inv-status" class="description" aria-live="polite"></span>
	</p>
</div>

<?php if ( ! empty( $open_rows ) ) : ?>
<div class="shojaei-card">
	<h3><?php esc_html_e( 'هشدارهای نگهبان', 'shojaei-seo-for-woo' ); ?></h3>
	<p class="shojaei-desc"><?php esc_html_e( 'بررسی دوره‌ای ~۴۸ ساعت (دسته‌ای) + واکنش لحظه‌ای به ۴۱۰/ریدایرکت.', 'shojaei-seo-for-woo' ); ?></p>
	<table class="widefat striped shojaei-table dm-responsive-table" id="shojaei-watchdog-alerts">
		<thead>
			<tr>
				<th><?php esc_html_e( 'مبدأ', 'shojaei-seo-for-woo' ); ?></th>
				<th><?php esc_html_e( 'مقصد', 'shojaei-seo-for-woo' ); ?></th>
				<th><?php esc_html_e( 'مشکل', 'shojaei-seo-for-woo' ); ?></th>
				<th><?php esc_html_e( 'عملیات', 'shojaei-seo-for-woo' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( array_slice( $open_rows, 0, 30 ) as $row ) : ?>
				<?php
				$sid = (int) ( $row['source_post_id'] ?? 0 );
				$sev = (string) ( $row['severity'] ?? 'warning' );
				?>
				<tr data-alert-id="<?php echo esc_attr( (string) ( $row['id'] ?? '' ) ); ?>">
					<td>
						<?php if ( $sid && get_post( $sid ) ) : ?>
							<a href="<?php echo esc_url( get_edit_post_link( $sid, 'raw' ) ); ?>"><?php echo esc_html( get_the_title( $sid ) ?: ( '#' . $sid ) ); ?></a>
						<?php elseif ( 'redirect_chain' === ( $row['code'] ?? '' ) ) : ?>
							<?php esc_html_e( 'سیستم ریدایرکت', 'shojaei-seo-for-woo' ); ?>
						<?php else : ?>
							—
						<?php endif; ?>
					</td>
					<td dir="ltr"><code><?php echo esc_html( (string) ( $row['dest_url'] ?? '' ) ); ?></code></td>
					<td>
						<span class="<?php echo 'error' === $sev ? 'shojaei-tone-error' : 'shojaei-tone-warning'; ?>">
							<?php echo esc_html( (string) ( $row['label'] ?? '' ) ); ?>
						</span>
					</td>
					<td>
						<?php if ( 'remove_link' === ( $row['fix'] ?? '' ) && $sid && $can_fix ) : ?>
							<button type="button" class="button button-small shojaei-lg-fix-remove"
								data-source-id="<?php echo esc_attr( (string) $sid ); ?>"
								data-dest-url="<?php echo esc_attr( (string) ( $row['dest_url'] ?? '' ) ); ?>"
								data-alert-id="<?php echo esc_attr( (string) ( $row['id'] ?? '' ) ); ?>">
								<?php esc_html_e( 'حذف از متن', 'shojaei-seo-for-woo' ); ?>
							</button>
						<?php elseif ( 'flatten_redirect' === ( $row['fix'] ?? '' ) ) : ?>
							<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=shojaei-seo&tab=redirects' ) ); ?>"><?php esc_html_e( 'سلامت ریدایرکت', 'shojaei-seo-for-woo' ); ?></a>
						<?php elseif ( $sid ) : ?>
							<a class="button button-small" href="<?php echo esc_url( get_edit_post_link( $sid, 'raw' ) ); ?>"><?php esc_html_e( 'ویرایش', 'shojaei-seo-for-woo' ); ?></a>
						<?php else : ?>
							—
						<?php endif; ?>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php endif; ?>

<div class="shojaei-card">
	<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="shojaei-lg-filters" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;">
		<input type="hidden" name="page" value="shojaei-seo" />
		<input type="hidden" name="tab" value="link-inventory" />
		<select name="type">
			<option value="all" <?php selected( $type, 'all' ); ?>><?php esc_html_e( 'همه انواع', 'shojaei-seo-for-woo' ); ?></option>
			<option value="internal" <?php selected( $type, 'internal' ); ?>><?php esc_html_e( 'داخلی', 'shojaei-seo-for-woo' ); ?></option>
			<option value="external" <?php selected( $type, 'external' ); ?>><?php esc_html_e( 'خارجی', 'shojaei-seo-for-woo' ); ?></option>
		</select>
		<select name="status">
			<option value="all" <?php selected( $status, 'all' ); ?>><?php esc_html_e( 'همه وضعیت‌ها', 'shojaei-seo-for-woo' ); ?></option>
			<option value="ok" <?php selected( $status, 'ok' ); ?>><?php esc_html_e( 'سالم (۲xx)', 'shojaei-seo-for-woo' ); ?></option>
			<option value="broken" <?php selected( $status, 'broken' ); ?>><?php esc_html_e( 'شکسته (۴xx+)', 'shojaei-seo-for-woo' ); ?></option>
			<option value="redirect" <?php selected( $status, 'redirect' ); ?>><?php esc_html_e( 'ریدایرکت', 'shojaei-seo-for-woo' ); ?></option>
			<option value="unchecked" <?php selected( $status, 'unchecked' ); ?>><?php esc_html_e( 'بررسی‌نشده', 'shojaei-seo-for-woo' ); ?></option>
		</select>
		<input type="search" name="q" value="<?php echo esc_attr( $q ); ?>" placeholder="<?php esc_attr_e( 'جستجو در آدرس یا انکر…', 'shojaei-seo-for-woo' ); ?>" style="min-width:220px;" />
		<button type="submit" class="button"><?php esc_html_e( 'فیلتر', 'shojaei-seo-for-woo' ); ?></button>
	</form>

	<?php if ( empty( $result['rows'] ) ) : ?>
		<div class="shojaei-empty-state" style="margin-top:16px;">
			<span class="dashicons dashicons-admin-links"></span>
			<p><?php esc_html_e( 'لینکی نیست. «اسکن مجدد محتوا» را بزنید.', 'shojaei-seo-for-woo' ); ?></p>
		</div>
	<?php else : ?>
		<table class="widefat striped shojaei-table dm-responsive-table" style="margin-top:14px;" id="shojaei-lg-inventory-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'نوع', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'مقصد', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'نوشته مبدأ', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'انکر', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'HTTP', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'آخرین بررسی', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'عملیات', 'shojaei-seo-for-woo' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $result['rows'] as $row ) : ?>
					<?php
					$pid         = (int) $row->source_post_id;
					$title       = $pid ? get_the_title( $pid ) : '';
					$code        = (int) $row->http_status;
					$is_internal = 'internal' === $row->link_type;
					$tone        = ( $code >= 400 ) ? 'error' : ( ( $code >= 300 || ! empty( $row->is_redirect ) ) ? 'warning' : ( $code >= 200 ? 'safe' : '' ) );
					$dest        = (string) $row->dest_url;
					$new_url     = ! empty( $row->redirect_url ) ? (string) $row->redirect_url : '';
					?>
					<tr data-row-id="<?php echo esc_attr( (string) (int) $row->id ); ?>">
						<td><?php echo $is_internal ? esc_html__( 'داخلی', 'shojaei-seo-for-woo' ) : esc_html__( 'خارجی', 'shojaei-seo-for-woo' ); ?></td>
						<td dir="ltr"><a href="<?php echo esc_url( $dest ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $dest ); ?></a></td>
						<td>
							<?php if ( $pid ) : ?>
								<a href="<?php echo esc_url( get_edit_post_link( $pid, 'raw' ) ); ?>"><?php echo esc_html( $title ?: ( '#' . $pid ) ); ?></a>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( (string) $row->anchor_text ); ?></td>
						<td>
							<?php if ( $code ) : ?>
								<span class="shojaei-slug-score<?php echo $tone ? ' shojaei-tone-' . esc_attr( $tone ) : ''; ?>"><?php echo esc_html( (string) $code ); ?></span>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( (string) ( $row->last_checked ?: '—' ) ); ?></td>
						<td>
							<?php if ( $can_fix && $is_internal && $pid && $code >= 400 ) : ?>
								<button type="button" class="button button-small shojaei-lg-fix-remove"
									data-source-id="<?php echo esc_attr( (string) $pid ); ?>"
									data-dest-url="<?php echo esc_attr( $dest ); ?>">
									<?php esc_html_e( 'حذف از متن', 'shojaei-seo-for-woo' ); ?>
								</button>
							<?php elseif ( $can_fix && $is_internal && $pid && ( $code >= 300 && $code < 400 || ! empty( $row->is_redirect ) ) ) : ?>
								<button type="button" class="button button-small shojaei-lg-fix-update"
									data-source-id="<?php echo esc_attr( (string) $pid ); ?>"
									data-dest-url="<?php echo esc_attr( $dest ); ?>"
									data-new-url="<?php echo esc_attr( $new_url ); ?>">
									<?php esc_html_e( 'به‌روز URL', 'shojaei-seo-for-woo' ); ?>
								</button>
								<?php if ( $new_url ) : ?>
									<span class="description" dir="ltr" style="display:block;font-size:11px;margin-top:4px;">→ <?php echo esc_html( $new_url ); ?></span>
								<?php endif; ?>
							<?php elseif ( $pid ) : ?>
								<a class="button button-small" href="<?php echo esc_url( get_edit_post_link( $pid, 'raw' ) ); ?>"><?php esc_html_e( 'ویرایش', 'shojaei-seo-for-woo' ); ?></a>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<?php
		$pages = max( 1, (int) ceil( $result['total'] / 50 ) );
		if ( $pages > 1 ) :
			?>
			<p class="description" style="margin-top:10px;">
				<?php
				printf(
					/* translators: 1: page 2: pages 3: total */
					esc_html__( 'صفحه %1$d از %2$d — %3$d لینک', 'shojaei-seo-for-woo' ),
					$page,
					$pages,
					(int) $result['total']
				);
				?>
				<?php if ( $page > 1 ) : ?>
					· <a href="<?php echo esc_url( add_query_arg( array( 'paged' => $page - 1, 'type' => $type, 'status' => $status, 'q' => $q ), $base ) ); ?>">&laquo;</a>
				<?php endif; ?>
				<?php if ( $page < $pages ) : ?>
					· <a href="<?php echo esc_url( add_query_arg( array( 'paged' => $page + 1, 'type' => $type, 'status' => $status, 'q' => $q ), $base ) ); ?>">&raquo;</a>
				<?php endif; ?>
			</p>
		<?php endif; ?>
	<?php endif; ?>
</div>
