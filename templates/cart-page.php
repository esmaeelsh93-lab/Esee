<?php
/**
 * Cart page template (classic WooCommerce shortcode).
 *
 * @package RezaJordaan
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );
?>
<main id="main" class="content-page rj-section woocommerce-page rezajordaan-cart">
	<div class="rj-container content-page__inner">
		<?php
		if ( function_exists( 'woocommerce_output_all_notices' ) ) {
			woocommerce_output_all_notices();
		}
		echo do_shortcode( '[woocommerce_cart]' );
		?>
	</div>
</main>
<?php
get_footer( 'shop' );
