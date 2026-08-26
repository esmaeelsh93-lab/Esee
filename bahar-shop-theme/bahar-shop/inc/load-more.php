<?php
/**
 * AJAX "بارگذاری بیشتر" for shop and category archives.
 *
 * @package Bahar_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_enqueue_scripts', 'bahar_shop_load_more_assets' );
add_action( 'woocommerce_after_shop_loop', 'bahar_shop_render_load_more_button', 5 );
add_action( 'wp_ajax_bahar_load_more_products', 'bahar_shop_ajax_load_more_products' );
add_action( 'wp_ajax_nopriv_bahar_load_more_products', 'bahar_shop_ajax_load_more_products' );

/**
 * Enqueue load-more script on product archives.
 */
function bahar_shop_load_more_assets() {
	$settings = bahar_shop_load_more_settings();
	if ( empty( $settings['enabled'] ) ) {
		return;
	}
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}
	if ( ! ( is_shop() || is_product_taxonomy() ) ) {
		return;
	}

	wp_enqueue_script(
		'bahar-shop-load-more',
		BAHAR_SHOP_URI . '/assets/js/load-more.js',
		array(),
		BAHAR_SHOP_VERSION,
		true
	);

	global $wp_query;
	wp_localize_script(
		'bahar-shop-load-more',
		'baharLoadMore',
		array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'nonce'    => wp_create_nonce( 'bahar_load_more' ),
			'page'     => max( 1, (int) get_query_var( 'paged' ) ),
			'maxPages' => max( 1, (int) $wp_query->max_num_pages ),
			'label'    => $settings['label'],
			'loading'  => __( 'در حال بارگذاری...', 'bahar-shop' ),
			'query'    => array(
				'is_shop'     => is_shop() ? 1 : 0,
				'taxonomy'    => is_product_taxonomy() ? get_queried_object()->taxonomy : '',
				'term_id'     => is_product_taxonomy() ? (int) get_queried_object_id() : 0,
				'orderby'     => isset( $_GET['orderby'] ) ? sanitize_text_field( wp_unslash( $_GET['orderby'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'min_price'   => isset( $_GET['min_price'] ) ? sanitize_text_field( wp_unslash( $_GET['min_price'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				'max_price'   => isset( $_GET['max_price'] ) ? sanitize_text_field( wp_unslash( $_GET['max_price'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				's'           => get_search_query(),
			),
		)
	);
}

/**
 * Hide default pagination when load-more is on; render button.
 */
function bahar_shop_render_load_more_button() {
	$settings = bahar_shop_load_more_settings();
	if ( empty( $settings['enabled'] ) ) {
		return;
	}
	global $wp_query;
	if ( (int) $wp_query->max_num_pages <= 1 ) {
		return;
	}
	?>
	<div class="bahar-load-more" data-bahar-load-more>
		<button type="button" class="bahar-load-more__btn">
			<?php echo esc_html( $settings['label'] ); ?>
		</button>
	</div>
	<?php
}

add_action( 'wp', 'bahar_shop_maybe_disable_pagination' );

/**
 * Remove Woo pagination when load-more is enabled.
 */
function bahar_shop_maybe_disable_pagination() {
	$settings = bahar_shop_load_more_settings();
	if ( empty( $settings['enabled'] ) ) {
		return;
	}
	if ( is_shop() || is_product_taxonomy() ) {
		remove_action( 'woocommerce_after_shop_loop', 'woocommerce_pagination', 10 );
	}
}

/**
 * AJAX handler — returns next page of product cards HTML.
 */
function bahar_shop_ajax_load_more_products() {
	check_ajax_referer( 'bahar_load_more', 'nonce' );

	$page = max( 1, (int) ( $_POST['page'] ?? 1 ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$query_args = array(
		'post_type'      => 'product',
		'post_status'    => 'publish',
		'posts_per_page' => (int) apply_filters( 'loop_shop_per_page', 12 ),
		'paged'          => $page,
	);

	$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$term_id  = isset( $_POST['term_id'] ) ? (int) $_POST['term_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( $taxonomy && $term_id > 0 && taxonomy_exists( $taxonomy ) ) {
		$query_args['tax_query'] = array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
			array(
				'taxonomy' => $taxonomy,
				'field'    => 'term_id',
				'terms'    => array( $term_id ),
			),
		);
	}

	$orderby = isset( $_POST['orderby'] ) ? sanitize_text_field( wp_unslash( $_POST['orderby'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( $orderby && function_exists( 'WC' ) ) {
		$ordering = WC()->query->get_catalog_ordering_args( $orderby );
		$query_args = array_merge( $query_args, $ordering );
	}

	$min_price = isset( $_POST['min_price'] ) ? wc_clean( wp_unslash( $_POST['min_price'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$max_price = isset( $_POST['max_price'] ) ? wc_clean( wp_unslash( $_POST['max_price'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	$meta_query = array();
	if ( '' !== $min_price || '' !== $max_price ) {
		$meta_query[] = array(
			'key'     => '_price',
			'value'   => array( $min_price !== '' ? (float) $min_price : 0, $max_price !== '' ? (float) $max_price : PHP_FLOAT_MAX ),
			'compare' => 'BETWEEN',
			'type'    => 'NUMERIC',
		);
	}
	if ( $meta_query ) {
		$query_args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
	}

	$search = isset( $_POST['s'] ) ? sanitize_text_field( wp_unslash( $_POST['s'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing
	if ( $search ) {
		$query_args['s'] = $search;
	}

	$q = new WP_Query( $query_args );
	ob_start();
	if ( $q->have_posts() ) {
		while ( $q->have_posts() ) {
			$q->the_post();
			wc_get_template_part( 'content', 'product' );
		}
		wp_reset_postdata();
	}
	$html = ob_get_clean();

	wp_send_json_success(
		array(
			'html'     => $html,
			'page'     => $page,
			'maxPages' => (int) $q->max_num_pages,
			'hasMore'  => $page < (int) $q->max_num_pages,
		)
	);
}
