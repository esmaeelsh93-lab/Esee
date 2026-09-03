<?php
/**
 * Redirect health — Broken + Chain + Loop.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

$report  = class_exists( 'Shojaei_SEO_Redirect_Audit' ) ? Shojaei_SEO_Redirect_Audit::get_broken_report() : array();
$issues  = is_array( $report['issues'] ?? null ) ? $report['issues'] : array();
$broken  = (int) ( $report['broken'] ?? 0 );
$checked = (int) ( $report['total_checked'] ?? 0 );
$when    = (string) ( $report['scanned_at'] ?? '' );

$chain_report  = class_exists( 'Shojaei_SEO_Redirect_Audit' ) ? Shojaei_SEO_Redirect_Audit::get_chain_report() : array();
$chain_issues  = is_array( $chain_report['issues'] ?? null ) ? $chain_report['issues'] : array();
$chains        = (int) ( $chain_report['chains'] ?? 0 );
$chain_checked = (int) ( $chain_report['total_checked'] ?? 0 );
$chain_when    = (string) ( $chain_report['scanned_at'] ?? '' );

$loop_report  = class_exists( 'Shojaei_SEO_Redirect_Audit' ) ? Shojaei_SEO_Redirect_Audit::get_loop_report() : array();
$loop_issues  = is_array( $loop_report['issues'] ?? null ) ? $loop_report['issues'] : array();
$loops        = (int) ( $loop_report['loops'] ?? 0 );
$loop_checked = (int) ( $loop_report['total_checked'] ?? 0 );
$loop_when    = (string) ( $loop_report['scanned_at'] ?? '' );
?>

<div class="shojaei-card">
	<h3><?php esc_html_e( 'سلامت ریدایرکت', 'shojaei-seo-for-woo' ); ?></h3>
	<p class="shojaei-desc">
		<?php esc_html_e( 'ریدایرکت‌های فعال موجودی و نامک: مقصد شکسته، زنجیره، و حلقه.', 'shojaei-seo-for-woo' ); ?>
	</p>
	<ul class="shojaei-wizard-list" style="margin:10px 0 0;">
		<li><?php esc_html_e( 'Broken: مقصد خراب/ناموجود', 'shojaei-seo-for-woo' ); ?></li>
		<li><?php esc_html_e( 'Chain: A → B → C', 'shojaei-seo-for-woo' ); ?></li>
		<li><?php esc_html_e( 'Loop: A → B → A (یا A → A)', 'shojaei-seo-for-woo' ); ?></li>
	</ul>
</div>

<div class="shojaei-card">
	<h3><?php esc_html_e( '۱) ریدایرکت شکسته (Broken)', 'shojaei-seo-for-woo' ); ?></h3>
	<p>
		<button type="button" class="button button-primary" id="shojaei-broken-scan">
			<?php esc_html_e( 'اسکن ریدایرکت‌های شکسته', 'shojaei-seo-for-woo' ); ?>
		</button>
		<?php if ( $when ) : ?>
			<span class="description" style="margin-right:10px;">
				<?php
				printf(
					/* translators: 1: datetime, 2: broken count, 3: checked count */
					esc_html__( 'آخرین اسکن: %1$s — %2$d شکسته از %3$d فعال', 'shojaei-seo-for-woo' ),
					esc_html( $when ),
					$broken,
					$checked
				);
				?>
			</span>
		<?php endif; ?>
	</p>
	<div id="shojaei-broken-scan-status" class="shojaei-test-result" style="display:none;margin-top:10px;" aria-live="polite"></div>

	<?php if ( ! $when ) : ?>
		<div class="shojaei-empty-state">
			<span class="dashicons dashicons-search"></span>
			<p><?php esc_html_e( 'هنوز اسکن نشده. دکمه بالا را بزنید.', 'shojaei-seo-for-woo' ); ?></p>
		</div>
	<?php elseif ( empty( $issues ) ) : ?>
		<div class="shojaei-empty-state">
			<span class="dashicons dashicons-yes-alt"></span>
			<p><?php esc_html_e( 'مورد شکسته‌ای پیدا نشد.', 'shojaei-seo-for-woo' ); ?></p>
		</div>
	<?php else : ?>
		<table class="widefat striped shojaei-table dm-responsive-table" id="shojaei-broken-redirects-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'نوع', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'مبدأ', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'مقصد', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'مشکل', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'شدت', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'عملیات', 'shojaei-seo-for-woo' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $issues as $issue ) : ?>
					<?php
					if ( ! is_array( $issue ) ) {
						continue;
					}
					$kind     = (string) ( $issue['kind'] ?? '' );
					$sev      = (string) ( $issue['severity'] ?? 'error' );
					$pid      = (int) ( $issue['product_id'] ?? 0 );
					$src      = (string) ( $issue['source_url'] ?? '' );
					$tgt      = (string) ( $issue['target_url'] ?? '' );
					$path     = (string) ( $issue['old_path'] ?? '' );
					$kind_l   = ( 'slug' === $kind ) ? __( 'نامک', 'shojaei-seo-for-woo' ) : __( 'موجودی', 'shojaei-seo-for-woo' );
					$sev_l    = ( 'warning' === $sev ) ? __( 'هشدار', 'shojaei-seo-for-woo' ) : __( 'خطا', 'shojaei-seo-for-woo' );
					$src_show = $path ? $path : ( wp_parse_url( $src, PHP_URL_PATH ) ?: $src );
					$tgt_show = wp_parse_url( $tgt, PHP_URL_PATH ) ?: $tgt;
					?>
					<tr
						data-kind="<?php echo esc_attr( $kind ); ?>"
						data-id="<?php echo esc_attr( (string) (int) ( $issue['id'] ?? 0 ) ); ?>"
						data-product-id="<?php echo esc_attr( (string) $pid ); ?>"
					>
						<td><?php echo esc_html( $kind_l ); ?> · <?php echo esc_html( (string) ( $issue['redirect_type'] ?? '' ) ); ?></td>
						<td dir="ltr">
							<?php if ( $src ) : ?>
								<a href="<?php echo esc_url( $src ); ?>" target="_blank" rel="noopener noreferrer"><code><?php echo esc_html( (string) $src_show ); ?></code></a>
							<?php elseif ( $path ) : ?>
								<code><?php echo esc_html( $path ); ?></code>
							<?php else : ?>
								—
							<?php endif; ?>
							<?php if ( $pid && get_post( $pid ) ) : ?>
								<br><a href="<?php echo esc_url( get_edit_post_link( $pid, 'raw' ) ); ?>"><?php echo esc_html( get_the_title( $pid ) ?: ( '#' . $pid ) ); ?></a>
							<?php endif; ?>
						</td>
						<td dir="ltr">
							<?php if ( $tgt ) : ?>
								<a href="<?php echo esc_url( $tgt ); ?>" target="_blank" rel="noopener noreferrer"><code><?php echo esc_html( (string) $tgt_show ); ?></code></a>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( (string) ( $issue['label'] ?? $issue['code'] ?? '' ) ); ?></td>
						<td>
							<span class="<?php echo 'warning' === $sev ? 'shojaei-tone-warning' : 'shojaei-tone-error'; ?>">
								<?php echo esc_html( $sev_l ); ?>
							</span>
						</td>
						<td>
							<button type="button" class="button button-small shojaei-broken-disable">
								<?php echo 'slug' === $kind
									? esc_html__( 'غیرفعال کردن', 'shojaei-seo-for-woo' )
									: esc_html__( 'لغو ریدایرکت', 'shojaei-seo-for-woo' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<div class="shojaei-card">
	<h3><?php esc_html_e( '۲) زنجیره ریدایرکت (Chain)', 'shojaei-seo-for-woo' ); ?></h3>
	<p class="shojaei-desc">
		<?php esc_html_e( 'اگر A به B برود و B خودش به C برود، خزنده چند بار جابه‌جا می‌شود. «صاف کردن» مبدأ را مستقیم به مقصد نهایی وصل می‌کند.', 'shojaei-seo-for-woo' ); ?>
	</p>
	<p>
		<button type="button" class="button button-primary" id="shojaei-chain-scan">
			<?php esc_html_e( 'اسکن زنجیره‌ها', 'shojaei-seo-for-woo' ); ?>
		</button>
		<?php if ( $chain_when ) : ?>
			<span class="description" style="margin-right:10px;">
				<?php
				printf(
					/* translators: 1: datetime, 2: chains, 3: checked */
					esc_html__( 'آخرین اسکن: %1$s — %2$d زنجیره از %3$d فعال', 'shojaei-seo-for-woo' ),
					esc_html( $chain_when ),
					$chains,
					$chain_checked
				);
				?>
			</span>
		<?php endif; ?>
	</p>
	<div id="shojaei-chain-scan-status" class="shojaei-test-result" style="display:none;margin-top:10px;" aria-live="polite"></div>

	<?php if ( ! $chain_when ) : ?>
		<div class="shojaei-empty-state">
			<span class="dashicons dashicons-search"></span>
			<p><?php esc_html_e( 'هنوز اسکن زنجیره نشده.', 'shojaei-seo-for-woo' ); ?></p>
		</div>
	<?php elseif ( empty( $chain_issues ) ) : ?>
		<div class="shojaei-empty-state">
			<span class="dashicons dashicons-yes-alt"></span>
			<p><?php esc_html_e( 'زنجیره‌ای پیدا نشد — ریدایرکت‌ها تک‌پرش‌اند.', 'shojaei-seo-for-woo' ); ?></p>
		</div>
	<?php else : ?>
		<table class="widefat striped shojaei-table dm-responsive-table" id="shojaei-chain-redirects-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'نوع', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'مسیر زنجیره', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'مقصد نهایی', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'پرش', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'عملیات', 'shojaei-seo-for-woo' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $chain_issues as $issue ) : ?>
					<?php
					if ( ! is_array( $issue ) ) {
						continue;
					}
					$kind   = (string) ( $issue['kind'] ?? '' );
					$pid    = (int) ( $issue['product_id'] ?? 0 );
					$final  = (string) ( $issue['final_url'] ?? '' );
					$path_a = is_array( $issue['path'] ?? null ) ? $issue['path'] : array();
					$kind_l = ( 'slug' === $kind ) ? __( 'نامک', 'shojaei-seo-for-woo' ) : __( 'موجودی', 'shojaei-seo-for-woo' );
					$final_show = wp_parse_url( $final, PHP_URL_PATH ) ?: $final;
					?>
					<tr
						data-kind="<?php echo esc_attr( $kind ); ?>"
						data-id="<?php echo esc_attr( (string) (int) ( $issue['id'] ?? 0 ) ); ?>"
						data-product-id="<?php echo esc_attr( (string) $pid ); ?>"
					>
						<td><?php echo esc_html( $kind_l ); ?></td>
						<td dir="ltr"><code><?php echo esc_html( implode( ' → ', array_map( 'strval', $path_a ) ) ); ?></code></td>
						<td dir="ltr">
							<?php if ( $final ) : ?>
								<a href="<?php echo esc_url( $final ); ?>" target="_blank" rel="noopener noreferrer"><code><?php echo esc_html( (string) $final_show ); ?></code></a>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( (string) (int) ( $issue['hops'] ?? 0 ) ); ?></td>
						<td>
							<button type="button" class="button button-small shojaei-chain-flatten">
								<?php esc_html_e( 'صاف کردن به مقصد نهایی', 'shojaei-seo-for-woo' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>

<div class="shojaei-card">
	<h3><?php esc_html_e( '۳) حلقه ریدایرکت (Loop)', 'shojaei-seo-for-woo' ); ?></h3>
	<p class="shojaei-desc">
		<?php esc_html_e( 'اگر ریدایرکت‌ها چرخه بسازند (A → B → A) مرورگر/بات گیر می‌کند. «شکستن حلقه» همان یال را لغو/غیرفعال می‌کند.', 'shojaei-seo-for-woo' ); ?>
	</p>
	<p>
		<button type="button" class="button button-primary" id="shojaei-loop-scan">
			<?php esc_html_e( 'اسکن حلقه‌ها', 'shojaei-seo-for-woo' ); ?>
		</button>
		<?php if ( $loop_when ) : ?>
			<span class="description" style="margin-right:10px;">
				<?php
				printf(
					/* translators: 1: datetime, 2: loops, 3: checked */
					esc_html__( 'آخرین اسکن: %1$s — %2$d حلقه از %3$d فعال', 'shojaei-seo-for-woo' ),
					esc_html( $loop_when ),
					$loops,
					$loop_checked
				);
				?>
			</span>
		<?php endif; ?>
	</p>
	<div id="shojaei-loop-scan-status" class="shojaei-test-result" style="display:none;margin-top:10px;" aria-live="polite"></div>

	<?php if ( ! $loop_when ) : ?>
		<div class="shojaei-empty-state">
			<span class="dashicons dashicons-search"></span>
			<p><?php esc_html_e( 'هنوز اسکن حلقه نشده.', 'shojaei-seo-for-woo' ); ?></p>
		</div>
	<?php elseif ( empty( $loop_issues ) ) : ?>
		<div class="shojaei-empty-state">
			<span class="dashicons dashicons-yes-alt"></span>
			<p><?php esc_html_e( 'حلقه‌ای پیدا نشد.', 'shojaei-seo-for-woo' ); ?></p>
		</div>
	<?php else : ?>
		<table class="widefat striped shojaei-table dm-responsive-table" id="shojaei-loop-redirects-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'نوع', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'مسیر حلقه', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'مشکل', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'عملیات', 'shojaei-seo-for-woo' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $loop_issues as $issue ) : ?>
					<?php
					if ( ! is_array( $issue ) ) {
						continue;
					}
					$kind   = (string) ( $issue['kind'] ?? '' );
					$pid    = (int) ( $issue['product_id'] ?? 0 );
					$path_a = is_array( $issue['path'] ?? null ) ? $issue['path'] : array();
					$kind_l = ( 'slug' === $kind ) ? __( 'نامک', 'shojaei-seo-for-woo' ) : __( 'موجودی', 'shojaei-seo-for-woo' );
					?>
					<tr
						data-kind="<?php echo esc_attr( $kind ); ?>"
						data-id="<?php echo esc_attr( (string) (int) ( $issue['id'] ?? 0 ) ); ?>"
						data-product-id="<?php echo esc_attr( (string) $pid ); ?>"
					>
						<td><?php echo esc_html( $kind_l ); ?></td>
						<td dir="ltr"><code><?php echo esc_html( implode( ' → ', array_map( 'strval', $path_a ) ) ); ?></code></td>
						<td>
							<span class="shojaei-tone-error"><?php echo esc_html( (string) ( $issue['label'] ?? '' ) ); ?></span>
						</td>
						<td>
							<button type="button" class="button button-small shojaei-loop-break">
								<?php esc_html_e( 'شکستن حلقه', 'shojaei-seo-for-woo' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="description" style="margin-top:10px;">
			<?php esc_html_e( 'بعد از شکستن یک یال، دوباره اسکن کنید؛ اگر حلقه چند یال دارد ممکن است یال دیگری هم نیاز به اصلاح داشته باشد.', 'shojaei-seo-for-woo' ); ?>
		</p>
	<?php endif; ?>
</div>
