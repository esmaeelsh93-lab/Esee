<?php
/**
 * Compact intelligent filters for product archives.
 *
 * @package RezaJordaan
 */

defined( 'ABSPATH' ) || exit;

$filter_data    = rezajordaan_get_archive_filter_data();
$selected_sizes = isset( $_GET['rz_size'] )
	? array_values( array_filter( array_map( 'absint', (array) wp_unslash( $_GET['rz_size'] ) ) ) )
	: array();
$requested_min  = isset( $_GET['min_price'] ) ? (float) wc_clean( wp_unslash( $_GET['min_price'] ) ) : $filter_data['min_price'];
$requested_max  = isset( $_GET['max_price'] ) ? (float) wc_clean( wp_unslash( $_GET['max_price'] ) ) : $filter_data['max_price'];
$active_min     = max( $filter_data['min_price'], min( $requested_min, $filter_data['max_price'] ) );
$active_max     = max( $active_min, min( $requested_max, $filter_data['max_price'] ) );
$has_price      = $filter_data['max_price'] > $filter_data['min_price'];
$has_filters    = ! empty( $filter_data['sizes'] ) || $has_price;
$has_selection  = ! empty( $selected_sizes ) || isset( $_GET['min_price'] ) || isset( $_GET['max_price'] );
$queried_object = get_queried_object();
$archive_url    = $queried_object instanceof WP_Term ? get_term_link( $queried_object ) : rezajordaan_shop_url();
$archive_url    = is_wp_error( $archive_url ) ? rezajordaan_shop_url() : $archive_url;

if ( ! $has_filters ) {
	return;
}
?>
<details class="archive-filters" data-archive-filters <?php echo $has_selection ? 'open' : ''; ?>>
	<summary>
		<span>
			<svg aria-hidden="true" viewBox="0 0 24 24"><path d="M4 6h16M7 12h10m-7 6h4"/><circle cx="8" cy="6" r="1.5"/><circle cx="15" cy="12" r="1.5"/><circle cx="12" cy="18" r="1.5"/></svg>
			<?php esc_html_e( 'فیلتر محصولات', 'rezajordaan' ); ?>
		</span>
		<small><?php echo $has_selection ? esc_html__( 'فیلتر فعال', 'rezajordaan' ) : esc_html__( 'سایز و قیمت', 'rezajordaan' ); ?></small>
		<i aria-hidden="true"></i>
	</summary>

	<form class="archive-filters__form" method="get" action="<?php echo esc_url( $archive_url ); ?>">
		<?php if ( $filter_data['sizes'] ) : ?>
			<fieldset class="archive-filters__sizes">
				<legend><?php esc_html_e( 'سایزهای موجود', 'rezajordaan' ); ?></legend>
				<div>
					<?php foreach ( $filter_data['sizes'] as $size_group ) : ?>
						<?php foreach ( $size_group['terms'] as $size_term ) : ?>
							<label>
								<input type="checkbox" name="rz_size[]" value="<?php echo esc_attr( $size_term->term_id ); ?>" <?php checked( in_array( (int) $size_term->term_id, $selected_sizes, true ) ); ?>>
								<span><?php echo esc_html( $size_term->name ); ?></span>
							</label>
						<?php endforeach; ?>
					<?php endforeach; ?>
				</div>
			</fieldset>
		<?php endif; ?>

		<?php if ( $has_price ) : ?>
			<fieldset class="archive-filters__price" data-price-range>
				<legend><?php esc_html_e( 'محدوده قیمت', 'rezajordaan' ); ?></legend>
				<div class="archive-filters__price-inputs">
					<label>
						<span><?php esc_html_e( 'از', 'rezajordaan' ); ?></span>
						<input type="number" name="min_price" min="<?php echo esc_attr( $filter_data['min_price'] ); ?>" max="<?php echo esc_attr( $filter_data['max_price'] ); ?>" step="<?php echo esc_attr( $filter_data['step'] ); ?>" value="<?php echo esc_attr( $active_min ); ?>" data-price-min-input>
					</label>
					<label>
						<span><?php esc_html_e( 'تا', 'rezajordaan' ); ?></span>
						<input type="number" name="max_price" min="<?php echo esc_attr( $filter_data['min_price'] ); ?>" max="<?php echo esc_attr( $filter_data['max_price'] ); ?>" step="<?php echo esc_attr( $filter_data['step'] ); ?>" value="<?php echo esc_attr( $active_max ); ?>" data-price-max-input>
					</label>
				</div>
				<div
					class="archive-filters__slider"
					style="--price-start: 0%; --price-end: 100%;"
				>
					<input type="range" min="<?php echo esc_attr( $filter_data['min_price'] ); ?>" max="<?php echo esc_attr( $filter_data['max_price'] ); ?>" step="<?php echo esc_attr( $filter_data['step'] ); ?>" value="<?php echo esc_attr( $active_min ); ?>" aria-label="<?php esc_attr_e( 'کمترین قیمت', 'rezajordaan' ); ?>" data-price-min-range>
					<input type="range" min="<?php echo esc_attr( $filter_data['min_price'] ); ?>" max="<?php echo esc_attr( $filter_data['max_price'] ); ?>" step="<?php echo esc_attr( $filter_data['step'] ); ?>" value="<?php echo esc_attr( $active_max ); ?>" aria-label="<?php esc_attr_e( 'بیشترین قیمت', 'rezajordaan' ); ?>" data-price-max-range>
				</div>
				<p>
					<span><?php echo wp_kses_post( wc_price( $filter_data['min_price'] ) ); ?></span>
					<span><?php echo wp_kses_post( wc_price( $filter_data['max_price'] ) ); ?></span>
				</p>
			</fieldset>
		<?php endif; ?>

		<?php if ( isset( $_GET['orderby'] ) ) : ?>
			<input type="hidden" name="orderby" value="<?php echo esc_attr( wc_clean( wp_unslash( $_GET['orderby'] ) ) ); ?>">
		<?php endif; ?>

		<div class="archive-filters__actions">
			<button type="submit"><?php esc_html_e( 'اعمال فیلتر', 'rezajordaan' ); ?></button>
			<?php if ( $has_selection ) : ?>
				<a href="<?php echo esc_url( $archive_url ); ?>"><?php esc_html_e( 'پاک‌کردن', 'rezajordaan' ); ?></a>
			<?php endif; ?>
		</div>
	</form>
</details>
