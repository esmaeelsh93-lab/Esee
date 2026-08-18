<?php
/**
 * WooCommerce wrapper for product and archive views.
 *
 * @package RezaJordaan
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );
?>
<main id="main" class="content-page rj-section woocommerce-page">
	<div class="rj-container content-page__inner">
		<?php woocommerce_content(); ?>
	</div>
</main>
<?php
get_footer( 'shop' );
