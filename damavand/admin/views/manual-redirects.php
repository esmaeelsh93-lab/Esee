<?php
/**
 * Manual redirects — add + list (Rank Math–style).
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'Shojaei_SEO_Manual_Redirect' ) ) {
	echo '<div class="notice notice-error"><p>' . esc_html__( 'ماژول ریدایرکت دستی در دسترس نیست.', 'shojaei-seo-for-woo' ) . '</p></div>';
	return;
}

$rows     = Shojaei_SEO_Manual_Redirect::list_redirects( 200 );
$active_n = Shojaei_SEO_Manual_Redirect::count_active();
$new_mode = isset( $_GET['new'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$match_labels = array(
	'exact'    => __( 'دقیق', 'shojaei-seo-for-woo' ),
	'archive'  => __( 'آرشیو + صفحه‌بندی', 'shojaei-seo-for-woo' ),
	'contains' => __( 'شامل', 'shojaei-seo-for-woo' ),
	'start'    => __( 'شروع با', 'shojaei-seo-for-woo' ),
	'regex'    => __( 'Regex', 'shojaei-seo-for-woo' ),
);
?>

<div class="shojaei-card">
	<div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:12px;">
		<div>
			<h3 style="margin:0 0 6px;"><?php esc_html_e( 'ریدایرکت دستی', 'shojaei-seo-for-woo' ); ?></h3>
			<p class="shojaei-desc" style="margin:0;">
				<?php
				printf(
					/* translators: %d: active count */
					esc_html__( 'ریدایرکت آزاد مسیر → مقصد (مثل Rank Math). %d قاعده فعال.', 'shojaei-seo-for-woo' ),
					(int) $active_n
				);
				?>
			</p>
		</div>
		<a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=shojaei-seo&tab=manual-redirects&new=1' ) ); ?>">
			<?php esc_html_e( 'افزودن ریدایرکت', 'shojaei-seo-for-woo' ); ?>
		</a>
	</div>
</div>

<?php if ( $new_mode ) : ?>
<div class="shojaei-card shojaei-manual-redirect-form-card" id="shojaei-manual-redirect-form-card">
	<h3><?php esc_html_e( 'افزودن ریدایرکت', 'shojaei-seo-for-woo' ); ?></h3>
	<form id="shojaei-manual-redirect-form" class="shojaei-manual-redirect-form" autocomplete="off">
		<div class="shojaei-mr-field">
			<label><?php esc_html_e( 'آدرس‌های مبدأ (Source)', 'shojaei-seo-for-woo' ); ?></label>
			<div id="shojaei-mr-sources">
				<div class="shojaei-mr-source-row">
					<input type="text" class="shojaei-mr-source regular-text" name="sources[]" placeholder="/old-path یا https://…" dir="ltr" />
					<button type="button" class="button-link shojaei-mr-remove-source" hidden><?php esc_html_e( 'Remove', 'shojaei-seo-for-woo' ); ?></button>
				</div>
			</div>
			<p class="shojaei-mr-source-actions">
				<button type="button" class="button-link" id="shojaei-mr-add-source"><?php esc_html_e( 'Add another', 'shojaei-seo-for-woo' ); ?></button>
			</p>
			<div class="shojaei-mr-source-opts">
				<div id="shojaei-mr-archive-warn" class="shojaei-mr-archive-warn" hidden></div>
				<label>
					<?php esc_html_e( 'نوع تطبیق', 'shojaei-seo-for-woo' ); ?>
					<select name="match_type" id="shojaei-mr-match">
						<option value="exact" selected><?php esc_html_e( 'Exact', 'shojaei-seo-for-woo' ); ?></option>
						<option value="archive"><?php esc_html_e( 'آرشیو دسته (+ page/2…)', 'shojaei-seo-for-woo' ); ?></option>
						<option value="contains"><?php esc_html_e( 'Contains', 'shojaei-seo-for-woo' ); ?></option>
						<option value="start"><?php esc_html_e( 'Starts With', 'shojaei-seo-for-woo' ); ?></option>
						<option value="regex"><?php esc_html_e( 'Regex', 'shojaei-seo-for-woo' ); ?></option>
					</select>
				</label>
				<label class="shojaei-mr-ignore">
					<input type="checkbox" name="covers_pagination" id="shojaei-mr-covers-pagination" value="1" checked />
					<?php esc_html_e( 'شامل صفحه‌بندی دسته (page/2، page/3، …)', 'shojaei-seo-for-woo' ); ?>
				</label>
				<label class="shojaei-mr-ignore">
					<input type="checkbox" name="ignore_case" value="1" />
					<?php esc_html_e( 'Ignore Case', 'shojaei-seo-for-woo' ); ?>
				</label>
			</div>
		</div>

		<div class="shojaei-mr-field">
			<label for="shojaei-mr-destination"><?php esc_html_e( 'آدرس مقصد (Destination URL)', 'shojaei-seo-for-woo' ); ?></label>
			<input type="text" id="shojaei-mr-destination" class="regular-text" name="destination" placeholder="https://… یا /new-path" dir="ltr" />
		</div>

		<div class="shojaei-mr-field">
			<span class="shojaei-mr-label"><?php esc_html_e( 'نوع ریدایرکت', 'shojaei-seo-for-woo' ); ?></span>
			<div class="shojaei-mr-type-group" role="group">
				<label class="shojaei-mr-chip is-active"><input type="radio" name="redirect_type" value="301" checked /> <?php esc_html_e( 'Permanent Move 301', 'shojaei-seo-for-woo' ); ?></label>
				<label class="shojaei-mr-chip"><input type="radio" name="redirect_type" value="302" /> <?php esc_html_e( 'Temporary Move 302', 'shojaei-seo-for-woo' ); ?></label>
				<label class="shojaei-mr-chip"><input type="radio" name="redirect_type" value="307" /> <?php esc_html_e( 'Temporary Redirect 307', 'shojaei-seo-for-woo' ); ?></label>
				<label class="shojaei-mr-chip"><input type="radio" name="redirect_type" value="410" /> <?php esc_html_e( 'Content Deleted 410', 'shojaei-seo-for-woo' ); ?></label>
				<label class="shojaei-mr-chip"><input type="radio" name="redirect_type" value="451" /> <?php esc_html_e( 'Unavailable 451', 'shojaei-seo-for-woo' ); ?></label>
			</div>
		</div>

		<div class="shojaei-mr-field">
			<span class="shojaei-mr-label"><?php esc_html_e( 'وضعیت', 'shojaei-seo-for-woo' ); ?></span>
			<div class="shojaei-mr-type-group" role="group">
				<label class="shojaei-mr-chip is-active"><input type="radio" name="is_active" value="1" checked /> <?php esc_html_e( 'Activate', 'shojaei-seo-for-woo' ); ?></label>
				<label class="shojaei-mr-chip"><input type="radio" name="is_active" value="0" /> <?php esc_html_e( 'Deactivate', 'shojaei-seo-for-woo' ); ?></label>
			</div>
		</div>

		<div id="shojaei-mr-form-result" class="shojaei-test-result" style="display:none;margin:10px 0;" aria-live="polite"></div>

		<p class="shojaei-mr-submit-row">
			<button type="submit" class="button button-primary button-hero" id="shojaei-mr-submit">
				<?php esc_html_e( 'Add Redirection', 'shojaei-seo-for-woo' ); ?>
			</button>
			<a class="button button-hero" href="<?php echo esc_url( admin_url( 'admin.php?page=shojaei-seo&tab=manual-redirects' ) ); ?>">
				<?php esc_html_e( 'Cancel', 'shojaei-seo-for-woo' ); ?>
			</a>
		</p>
	</form>
</div>
<?php endif; ?>

<div class="shojaei-card">
	<h3><?php esc_html_e( 'لیست ریدایرکت‌های دستی', 'shojaei-seo-for-woo' ); ?></h3>
	<p>
		<label class="screen-reader-text" for="shojaei-mr-search"><?php esc_html_e( 'جستجو', 'shojaei-seo-for-woo' ); ?></label>
		<input type="search" id="shojaei-mr-search" class="shojaei-slug-product-search" placeholder="<?php esc_attr_e( 'جستجو در مبدأ یا مقصد…', 'shojaei-seo-for-woo' ); ?>" style="max-width:360px;width:100%;" />
	</p>
	<div id="shojaei-mr-list-result" class="shojaei-test-result" style="display:none;margin-bottom:10px;" aria-live="polite"></div>
	<?php if ( empty( $rows ) ) : ?>
		<div class="shojaei-empty-state">
			<span class="dashicons dashicons-randomize"></span>
			<p><?php esc_html_e( 'هنوز ریدایرکت دستی ثبت نشده.', 'shojaei-seo-for-woo' ); ?></p>
		</div>
	<?php else : ?>
		<table class="widefat striped shojaei-table dm-responsive-table" id="shojaei-mr-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'مبدأ', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'مقصد', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'نوع', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'تطبیق', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'Hits', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'فعال', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'عملیات', 'shojaei-seo-for-woo' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr data-id="<?php echo esc_attr( (string) $row->id ); ?>">
						<td dir="ltr"><code><?php echo esc_html( (string) $row->source_path ); ?></code></td>
						<td dir="ltr">
							<?php if ( ! empty( $row->destination ) ) : ?>
								<a href="<?php echo esc_url( (string) $row->destination ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( (string) $row->destination ); ?></a>
							<?php else : ?>
								—
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( (string) $row->redirect_type ); ?></td>
						<td><?php echo esc_html( $match_labels[ (string) $row->match_type ] ?? (string) $row->match_type ); ?><?php echo ! empty( $row->ignore_case ) ? ' · i' : ''; ?></td>
						<td><?php echo esc_html( (string) (int) $row->hits ); ?></td>
						<td>
							<label class="shojaei-switch">
								<input type="checkbox" class="shojaei-mr-toggle" data-id="<?php echo esc_attr( (string) $row->id ); ?>" <?php checked( (int) $row->is_active, 1 ); ?> />
							</label>
						</td>
						<td>
							<button type="button" class="button button-small shojaei-mr-delete" data-id="<?php echo esc_attr( (string) $row->id ); ?>">
								<?php esc_html_e( 'حذف', 'shojaei-seo-for-woo' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
