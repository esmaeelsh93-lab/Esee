<?php
/**
 * Similar products template (front).
 *
 * Expects: $damavand_similar_products (list), $damavand_similar_source (int)
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $damavand_similar_products ) || ! is_array( $damavand_similar_products ) ) {
	return;
}
?>
<aside class="damavand-related damavand-similar-products" dir="rtl" data-source="<?php echo esc_attr( (string) ( $damavand_similar_source ?? 0 ) ); ?>">
	<div class="damavand-related__inner">
		<h3 class="damavand-related__title"><?php esc_html_e( 'محصولات مشابه', 'shojaei-seo-for-woo' ); ?></h3>
		<ul class="damavand-related__list">
			<?php foreach ( $damavand_similar_products as $item ) : ?>
				<?php
				$url    = isset( $item['url'] ) ? (string) $item['url'] : '';
				$title  = isset( $item['title'] ) ? (string) $item['title'] : '';
				$reason = isset( $item['reason'] ) ? (string) $item['reason'] : '';
				if ( '' === $url || '' === $title ) {
					continue;
				}
				?>
				<li class="damavand-related__item">
					<a class="damavand-related__link" href="<?php echo esc_url( $url ); ?>">
						<span class="damavand-related__anchor"><?php echo esc_html( $title ); ?></span>
						<?php if ( $reason ) : ?>
							<span class="damavand-related__reason"><?php echo esc_html( $reason ); ?></span>
						<?php endif; ?>
					</a>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
</aside>
