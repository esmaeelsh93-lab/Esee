<?php
/**
 * Out-of-stock manager view — fast filters + bulk redirect selection.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

global $wpdb;

// Repair absurd day counts in small chunks so OOS tab never blocks for tens of seconds.
if ( class_exists( 'Shojaei_SEO_Helpers' ) ) {
	$repaired_ver = (string) get_option( 'shojaei_seo_oos_dates_repaired', '' );
	if ( version_compare( $repaired_ver, '1.10.5', '<' ) ) {
		$fixed = Shojaei_SEO_Helpers::repair_invalid_oos_dates( 50 );
		if ( $fixed < 50 ) {
			update_option( 'shojaei_seo_oos_dates_repaired', '1.10.5', false );
		}
	}
}

$filter_status   = sanitize_text_field( wp_unslash( $_REQUEST['oos_status'] ?? '' ) );
$filter_search   = sanitize_text_field( wp_unslash( $_REQUEST['oos_search'] ?? '' ) );
$filter_min_days = absint( $_REQUEST['oos_min_days'] ?? 0 );
$filter_cat      = absint( $_REQUEST['oos_cat'] ?? 0 );
$filter_type     = sanitize_text_field( wp_unslash( $_REQUEST['oos_type'] ?? '' ) );
$paged           = max( 1, absint( $_REQUEST['oos_paged'] ?? 1 ) );
$per_page        = 40;

if ( ! in_array( $filter_type, array( '', 'simple', 'variable' ), true ) ) {
	$filter_type = '';
}

$where  = array( "t.status IN ('candidate_redirect', 'soft_oos', 'temp_oos', 'permanent_oos', 'needs_manual')" );
$params = array();
$join   = "LEFT JOIN {$wpdb->posts} p ON t.product_id = p.ID";

$allowed_statuses = array( 'soft_oos', 'temp_oos', 'permanent_oos', 'candidate_redirect', 'needs_manual' );
if ( $filter_status && in_array( $filter_status, $allowed_statuses, true ) ) {
	$where[]  = 't.status = %s';
	$params[] = $filter_status;
}

$now_mysql = current_time( 'mysql' );
if ( $filter_min_days > 0 ) {
	$where[]  = 'TIMESTAMPDIFF(DAY, t.oos_date, %s) >= %d';
	$params[] = $now_mysql;
	$params[] = $filter_min_days;
}

if ( $filter_search ) {
	$where[]  = 'p.post_title LIKE %s';
	$params[] = '%' . $wpdb->esc_like( $filter_search ) . '%';
}

if ( $filter_cat > 0 ) {
	$join    .= " INNER JOIN {$wpdb->term_relationships} tr_cat ON p.ID = tr_cat.object_id
		INNER JOIN {$wpdb->term_taxonomy} tt_cat ON tr_cat.term_taxonomy_id = tt_cat.term_taxonomy_id
			AND tt_cat.taxonomy = 'product_cat'";
	$where[]  = 'tt_cat.term_id = %d';
	$params[] = $filter_cat;
}

if ( $filter_type ) {
	$join    .= " INNER JOIN {$wpdb->term_relationships} tr_type ON p.ID = tr_type.object_id
		INNER JOIN {$wpdb->term_taxonomy} tt_type ON tr_type.term_taxonomy_id = tt_type.term_taxonomy_id
			AND tt_type.taxonomy = 'product_type'
		INNER JOIN {$wpdb->terms} term_type ON tt_type.term_id = term_type.term_id";
	$where[]  = 'term_type.slug = %s';
	$params[] = $filter_type;
}

$where_sql = implode( ' AND ', $where );
$table     = Shojaei_SEO_Helpers::oos_table();

$count_sql = "SELECT COUNT(DISTINCT t.id) FROM {$table} t {$join} WHERE {$where_sql}";
if ( ! empty( $params ) ) {
	$count_sql = $wpdb->prepare( $count_sql, $params );
}
$total_items = (int) $wpdb->get_var( $count_sql );
$total_pages = max( 1, (int) ceil( $total_items / $per_page ) );
$paged       = min( $paged, $total_pages );
$offset      = ( $paged - 1 ) * $per_page;

$list_sql = "SELECT DISTINCT t.*, p.post_title FROM {$table} t
	{$join}
	WHERE {$where_sql}
	ORDER BY TIMESTAMPDIFF(DAY, t.oos_date, %s) DESC
	LIMIT %d OFFSET %d";

$list_params = array_merge( $params, array( $now_mysql, $per_page, $offset ) );
$candidates  = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_params ) ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

$categories = get_terms( array(
	'taxonomy'   => 'product_cat',
	'hide_empty' => true,
	'number'     => 200,
) );
if ( is_wp_error( $categories ) ) {
	$categories = array();
}

$redirected = $wpdb->get_results(
	"SELECT t.product_id, t.redirect_type, t.target_url, p.post_title FROM {$table} t
	LEFT JOIN {$wpdb->posts} p ON t.product_id = p.ID
	WHERE t.status = 'redirected'
	ORDER BY t.id DESC
	LIMIT 30"
);

$base_url = admin_url( 'admin.php?page=shojaei-seo&tab=oos' );
$query_args = array_filter(
	array(
		'oos_status'   => $filter_status,
		'oos_search'   => $filter_search,
		'oos_min_days' => $filter_min_days ?: null,
		'oos_cat'      => $filter_cat ?: null,
		'oos_type'     => $filter_type,
	)
);
?>

<div class="shojaei-ops-hero">
	<h2><?php esc_html_e( 'عملیات موجودی', 'shojaei-seo-for-woo' ); ?></h2>
	<p><?php esc_html_e( 'صف تصمیم سئو بر اساس ناموجودی: نگه‌داشتن صفحه، ریدایرکت ۳۰۲/۳۰۱ به مشابه یا دسته، یا ۴۱۰.', 'shojaei-seo-for-woo' ); ?></p>
</div>
<div class="shojaei-card" style="margin-bottom:16px;">
	<p style="margin:0 0 8px;">
		<button type="button" class="button button-primary" id="shojaei-oos-days-scan"><?php esc_html_e( 'اسکن روز ناموجودی', 'shojaei-seo-for-woo' ); ?></button>
		<span class="description"><?php esc_html_e( 'از وقتی موجودی صفر شده (فروش، کم‌کردن دستی، فاکتور/API ووکامرس). گذشته: آخرین فروش. هر مرحله ۱۰۰ محصول — سایت سنگین نمی‌شود.', 'shojaei-seo-for-woo' ); ?></span>
	</p>
	<div id="shojaei-oos-days-progress"></div>
</div>

<div class="shojaei-card">
	<h3><?php esc_html_e( 'فیلتر و جستجو', 'shojaei-seo-for-woo' ); ?></h3>
	<form method="get" class="shojaei-filter-form" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" id="shojaei-oos-filter-form">
		<input type="hidden" name="page" value="shojaei-seo" />
		<input type="hidden" name="tab" value="oos" />
		<input type="text" name="oos_search" value="<?php echo esc_attr( $filter_search ); ?>" placeholder="<?php esc_attr_e( 'جستجوی عنوان…', 'shojaei-seo-for-woo' ); ?>" />
		<select name="oos_status">
			<option value=""><?php esc_html_e( 'همه وضعیت‌ها', 'shojaei-seo-for-woo' ); ?></option>
			<option value="temp_oos" <?php selected( $filter_status, 'temp_oos' ); ?>><?php esc_html_e( 'ناموجود موقت', 'shojaei-seo-for-woo' ); ?></option>
			<option value="permanent_oos" <?php selected( $filter_status, 'permanent_oos' ); ?>><?php esc_html_e( 'ناموجود دائم', 'shojaei-seo-for-woo' ); ?></option>
			<option value="candidate_redirect" <?php selected( $filter_status, 'candidate_redirect' ); ?>><?php esc_html_e( 'کاندید ریدایرکت', 'shojaei-seo-for-woo' ); ?></option>
			<option value="needs_manual" <?php selected( $filter_status, 'needs_manual' ); ?>><?php esc_html_e( 'نیاز به تایید دستی', 'shojaei-seo-for-woo' ); ?></option>
		</select>
		<select name="oos_type">
			<option value=""><?php esc_html_e( 'همه انواع', 'shojaei-seo-for-woo' ); ?></option>
			<option value="simple" <?php selected( $filter_type, 'simple' ); ?>><?php esc_html_e( 'ساده', 'shojaei-seo-for-woo' ); ?></option>
			<option value="variable" <?php selected( $filter_type, 'variable' ); ?>><?php esc_html_e( 'متغیر', 'shojaei-seo-for-woo' ); ?></option>
		</select>
		<select name="oos_cat">
			<option value="0"><?php esc_html_e( 'همه دسته‌ها', 'shojaei-seo-for-woo' ); ?></option>
			<?php foreach ( $categories as $cat ) : ?>
				<option value="<?php echo esc_attr( $cat->term_id ); ?>" <?php selected( $filter_cat, (int) $cat->term_id ); ?>>
					<?php echo esc_html( $cat->name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<input type="number" name="oos_min_days" value="<?php echo esc_attr( $filter_min_days ?: '' ); ?>" min="0" placeholder="<?php esc_attr_e( 'حداقل روز', 'shojaei-seo-for-woo' ); ?>" />
		<div class="shojaei-filter-actions">
			<button type="submit" class="button button-primary"><?php esc_html_e( 'اعمال فیلتر', 'shojaei-seo-for-woo' ); ?></button>
			<a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php esc_html_e( 'پاک کردن', 'shojaei-seo-for-woo' ); ?></a>
		</div>
	</form>
</div>

<div class="shojaei-card">
	<h3>
		<?php esc_html_e( 'محصولات ناموجود — انتخاب و ریدایرکت گروهی', 'shojaei-seo-for-woo' ); ?>
		<small class="description" style="font-weight:400;margin-right:8px;">
			<?php
			printf(
				/* translators: 1: shown 2: total */
				esc_html__( '%1$d از %2$d مورد', 'shojaei-seo-for-woo' ),
				count( $candidates ),
				$total_items
			);
			?>
		</small>
	</h3>
	<p class="shojaei-desc"><?php esc_html_e( 'چند محصول را تیک بزنید و یکجا ریدایرکت ۳۰۲ (یا ۳۰۱ / ۴۱۰) اعمال کنید. مقصد خالی = پیشنهاد دسته.', 'shojaei-seo-for-woo' ); ?></p>

	<?php if ( empty( $candidates ) ) : ?>
		<div class="shojaei-empty-state">
			<span class="dashicons dashicons-yes-alt"></span>
			<p><?php esc_html_e( 'با این فیلتر محصولی پیدا نشد.', 'shojaei-seo-for-woo' ); ?></p>
		</div>
	<?php else : ?>
		<div class="shojaei-bulk-toolbar">
			<label><input type="checkbox" id="shojaei-select-all" /> <?php esc_html_e( 'انتخاب همه در این صفحه', 'shojaei-seo-for-woo' ); ?></label>
			<input type="url" id="shojaei-bulk-target-url" class="shojaei-target-url" placeholder="<?php esc_attr_e( 'آدرس مقصد گروهی (اختیاری)', 'shojaei-seo-for-woo' ); ?>" />
			<button type="button" class="button button-primary shojaei-bulk-action" data-action="redirect_302"><?php esc_html_e( 'ریدایرکت ۳۰۲ گروهی', 'shojaei-seo-for-woo' ); ?></button>
			<button type="button" class="button shojaei-bulk-action" data-action="redirect_301"><?php esc_html_e( 'ریدایرکت ۳۰۱ گروهی', 'shojaei-seo-for-woo' ); ?></button>
			<button type="button" class="button shojaei-bulk-action shojaei-bulk-410" data-action="redirect_410"><?php esc_html_e( '410 گروهی', 'shojaei-seo-for-woo' ); ?></button>
			<button type="button" class="button shojaei-bulk-action" data-action="keep"><?php esc_html_e( 'نگهداری گروهی', 'shojaei-seo-for-woo' ); ?></button>
			<span class="shojaei-bulk-count"></span>
		</div>

		<table class="shojaei-table" id="shojaei-candidates-table">
			<thead>
				<tr>
					<th class="check-column"><span class="screen-reader-text"><?php esc_html_e( 'انتخاب', 'shojaei-seo-for-woo' ); ?></span></th>
					<th><?php esc_html_e( 'محصول', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'روز ناموجود', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'وضعیت', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'چرخه', 'shojaei-seo-for-woo' ); ?></th>
					<th><?php esc_html_e( 'عملیات', 'shojaei-seo-for-woo' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $candidates as $row ) :
					$days = Shojaei_SEO_Helpers::days_since_oos( (string) $row->oos_date );
					$phase = Shojaei_SEO_Helpers::get_oos_phase( $days );
					$state = Shojaei_SEO_Helpers::get_oos_state( $days );
					$cat_url = Shojaei_SEO_Helpers::get_primary_category_url( (int) $row->product_id );
					?>
					<tr data-product-id="<?php echo esc_attr( $row->product_id ); ?>">
						<td class="check-column">
							<input type="checkbox" class="shojaei-product-check" value="<?php echo esc_attr( $row->product_id ); ?>" />
						</td>
						<td>
							<strong><?php echo esc_html( $row->post_title ?: ( '#' . $row->product_id ) ); ?></strong>
							<div class="shojaei-row-meta">
								<a href="<?php echo esc_url( get_edit_post_link( (int) $row->product_id ) ); ?>" target="_blank"><?php esc_html_e( 'ویرایش', 'shojaei-seo-for-woo' ); ?></a>
								·
								<a href="<?php echo esc_url( admin_url( 'admin.php?page=shojaei-seo&tab=test&product_id=' . (int) $row->product_id ) ); ?>"><?php esc_html_e( 'تست', 'shojaei-seo-for-woo' ); ?></a>
							</div>
						</td>
						<td><span class="shojaei-days-badge"><?php echo esc_html( (string) $days ); ?> <?php esc_html_e( 'روز', 'shojaei-seo-for-woo' ); ?></span></td>
						<td><?php echo esc_html( Shojaei_SEO_Helpers::oos_status_label( (string) $row->status ) ); ?></td>
						<td>
							<span class="shojaei-phase-badge phase-<?php echo esc_attr( (string) $phase ); ?>"><?php echo esc_html( $state['label'] ); ?></span>
						</td>
						<td class="shojaei-actions">
							<input type="url" class="shojaei-target-url" value="<?php echo esc_url( $cat_url ); ?>" placeholder="<?php esc_attr_e( 'آدرس مقصد', 'shojaei-seo-for-woo' ); ?>" />
							<button type="button" class="button button-primary shojaei-btn-redirect" data-action="redirect_302" data-id="<?php echo esc_attr( $row->product_id ); ?>">
								<?php esc_html_e( '۳۰۲', 'shojaei-seo-for-woo' ); ?>
							</button>
							<button type="button" class="button shojaei-btn-redirect" data-action="redirect_301" data-id="<?php echo esc_attr( $row->product_id ); ?>">
								<?php esc_html_e( '۳۰۱', 'shojaei-seo-for-woo' ); ?>
							</button>
							<button type="button" class="button shojaei-btn-410" data-action="redirect_410" data-id="<?php echo esc_attr( $row->product_id ); ?>">
								<?php esc_html_e( '۴۱۰', 'shojaei-seo-for-woo' ); ?>
							</button>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
			<div class="shojaei-pagination">
				<?php
				for ( $i = 1; $i <= $total_pages; $i++ ) {
					$url = add_query_arg( array_merge( $query_args, array( 'oos_paged' => $i ) ), $base_url );
					printf(
						'<a class="button%s" href="%s">%d</a> ',
						$i === $paged ? ' button-primary' : '',
						esc_url( $url ),
						$i
					);
					if ( $i >= 12 && $i < $total_pages - 1 ) {
						echo '<span>…</span> ';
						$i = $total_pages - 1;
					}
				}
				?>
			</div>
		<?php endif; ?>
	<?php endif; ?>
</div>

<?php if ( ! empty( $redirected ) ) : ?>
<div class="shojaei-card">
	<h3><?php esc_html_e( 'آخرین ریدایرکت‌ها (قابل لغو)', 'shojaei-seo-for-woo' ); ?></h3>
	<table class="shojaei-table">
		<thead>
			<tr>
				<th><?php esc_html_e( 'محصول', 'shojaei-seo-for-woo' ); ?></th>
				<th><?php esc_html_e( 'نوع', 'shojaei-seo-for-woo' ); ?></th>
				<th><?php esc_html_e( 'مقصد', 'shojaei-seo-for-woo' ); ?></th>
				<th><?php esc_html_e( 'عملیات', 'shojaei-seo-for-woo' ); ?></th>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $redirected as $row ) : ?>
				<tr data-product-id="<?php echo esc_attr( $row->product_id ); ?>">
					<td><?php echo esc_html( $row->post_title ); ?></td>
					<td><span class="shojaei-badge shojaei-badge-<?php echo esc_attr( $row->redirect_type ); ?>"><?php echo esc_html( $row->redirect_type ); ?></span></td>
					<td>
						<?php if ( '410' === $row->redirect_type ) : ?>
							—
						<?php else : ?>
							<a href="<?php echo esc_url( $row->target_url ); ?>" target="_blank"><?php echo esc_html( (string) wp_parse_url( $row->target_url, PHP_URL_PATH ) ); ?></a>
						<?php endif; ?>
					</td>
					<td>
						<button type="button" class="button shojaei-btn-undo" data-id="<?php echo esc_attr( $row->product_id ); ?>">
							<?php esc_html_e( 'لغو ریدایرکت', 'shojaei-seo-for-woo' ); ?>
						</button>
					</td>
				</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
</div>
<?php endif; ?>
