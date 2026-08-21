<?php
/**
 * WooCommerce wrapper.
 *
 * @package Bahar_Shop
 */

get_header();
?>

<div class="container woocommerce-wrap">
	<?php woocommerce_content(); ?>
</div>

<?php
get_footer();
