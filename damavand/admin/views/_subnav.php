<?php
/**
 * Secondary sub-navigation for Ops / Guide hubs.
 *
 * Expects: $shojaei_subnav_items (array), $shojaei_subnav_current (string), $shojaei_subnav_label (string)
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $shojaei_subnav_items ) || ! is_array( $shojaei_subnav_items ) ) {
	return;
}
?>
<div class="shojaei-subnav" role="navigation" aria-label="<?php echo esc_attr( $shojaei_subnav_label ?? __( 'زیرمنو', 'shojaei-seo-for-woo' ) ); ?>">
	<?php if ( ! empty( $shojaei_subnav_label ) ) : ?>
		<span class="shojaei-subnav-label"><?php echo esc_html( $shojaei_subnav_label ); ?></span>
	<?php endif; ?>
	<div class="shojaei-subnav-pills">
		<?php foreach ( $shojaei_subnav_items as $slug => $item ) : ?>
			<?php
			$is_active = ( $shojaei_subnav_current ?? '' ) === $slug;
			$url       = $item['url'] ?? admin_url( 'admin.php?page=shojaei-seo&tab=' . $slug );
			$badge     = isset( $item['badge'] ) ? (int) $item['badge'] : 0;
			$icon_key  = $item['icon'] ?? '';
			?>
			<a href="<?php echo esc_url( $url ); ?>" class="shojaei-subnav-pill<?php echo $is_active ? ' is-active' : ''; ?>"<?php echo $is_active ? ' aria-current="page"' : ''; ?>>
				<?php if ( $icon_key && class_exists( 'Damavand_SEO_Icons' ) ) : ?>
					<span class="shojaei-subnav-ico" aria-hidden="true"><?php Damavand_SEO_Icons::render( $icon_key, 15 ); ?></span>
				<?php endif; ?>
				<span><?php echo esc_html( $item['label'] ?? $slug ); ?></span>
				<?php if ( $badge > 0 ) : ?>
					<span class="shojaei-subnav-badge"><?php echo esc_html( (string) $badge ); ?></span>
				<?php endif; ?>
			</a>
		<?php endforeach; ?>
	</div>
</div>
