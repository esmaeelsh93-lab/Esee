<?php
/**
 * Product UX — badges, trust signals, cart toast, loop helpers.
 *
 * @package Bahar_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_footer', 'bahar_shop_cart_toast_markup', 20 );

/**
 * Toast container for add-to-cart feedback.
 */
function bahar_shop_cart_toast_markup() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

	if ( ! is_product() && ! is_shop() && ! is_product_taxonomy() && ! is_front_page() ) {
		return;
	}
	?>
	<div class="bahar-cart-toast" id="bahar-cart-toast" role="status" aria-live="polite" hidden>
		<span class="bahar-cart-toast__icon" aria-hidden="true">✓</span>
		<span class="bahar-cart-toast__text"><?php esc_html_e( 'به سبد اضافه شد', 'bahar-shop' ); ?></span>
	</div>
	<?php
}

/**
 * Whether product is considered "new" (published within N days).
 *
 * @param WC_Product $product Product.
 * @param int        $days    Days threshold.
 * @return bool
 */
function bahar_shop_is_new_product( $product, $days = 30 ) {
	if ( ! $product instanceof WC_Product ) {
		return false;
	}

	$created = $product->get_date_created();
	if ( ! $created ) {
		return false;
	}

	$threshold = time() - ( (int) $days * DAY_IN_SECONDS );

	return $created->getTimestamp() >= $threshold;
}

/**
 * Badge HTML for product cards.
 *
 * @param WC_Product $product Product.
 * @return string
 */
function bahar_shop_product_card_badges_html( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return '';
	}

	$badges = array();

	if ( ! $product->is_in_stock() ) {
		$badges[] = array(
			'class' => 'bahar-badge--oos',
			'label' => __( 'ناموجود', 'bahar-shop' ),
		);
	} elseif ( $product->is_on_sale() ) {
		$badges[] = array(
			'class' => 'bahar-badge--sale',
			'label' => __( 'حراج', 'bahar-shop' ),
		);
	} elseif ( bahar_shop_is_new_product( $product ) ) {
		$badges[] = array(
			'class' => 'bahar-badge--new',
			'label' => __( 'جدید', 'bahar-shop' ),
		);
	}

	if ( empty( $badges ) ) {
		return '';
	}

	ob_start();
	?>
	<div class="bahar-product-card__badges">
		<?php foreach ( $badges as $badge ) : ?>
			<span class="bahar-badge <?php echo esc_attr( $badge['class'] ); ?>"><?php echo esc_html( $badge['label'] ); ?></span>
		<?php endforeach; ?>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Loop price with "از" prefix for variable products when missing.
 *
 * @param WC_Product $product Product.
 * @return string
 */
function bahar_shop_loop_price_html( $product ) {
	if ( ! $product instanceof WC_Product ) {
		return '';
	}

	$html = $product->get_price_html();

	if ( $product->is_type( 'variable' ) ) {
		$plain = wp_strip_all_tags( $html );
		if ( false === mb_strpos( $plain, 'از' ) ) {
			$min = $product->get_variation_price( 'min', true );
			if ( $min ) {
				$html = '<span class="bahar-price-from">' . esc_html__( 'از', 'bahar-shop' ) . ' ' . wp_kses_post( wc_price( $min ) ) . '</span>';
			}
		}
	}

	return wp_kses_post( $html );
}

/**
 * Mobile back link on single product.
 */
function bahar_shop_product_back_link() {
	if ( ! is_product() || ! function_exists( 'wc_get_page_permalink' ) ) {
		return;
	}

	$shop_url = wc_get_page_permalink( 'shop' );
	if ( ! $shop_url ) {
		return;
	}
	?>
	<a href="<?php echo esc_url( $shop_url ); ?>" class="bahar-product-back">
		<span class="bahar-product-back__icon" aria-hidden="true">‹</span>
		<span><?php esc_html_e( 'بازگشت به فروشگاه', 'bahar-shop' ); ?></span>
	</a>
	<?php
}

add_action( 'woocommerce_after_add_to_cart_form', 'bahar_shop_trust_badges', 12 );

/**
 * Trust signals below add-to-cart on single product.
 */
function bahar_shop_trust_badges() {
	if ( ! is_product() ) {
		return;
	}
	?>
	<div class="bahar-trust-badges" role="list">
		<span class="bahar-trust-badge" role="listitem">
			<span class="bahar-trust-badge__icon" aria-hidden="true">🚚</span>
			<span><?php esc_html_e( 'ارسال ۲ تا ۴ روزه', 'bahar-shop' ); ?></span>
		</span>
		<span class="bahar-trust-badge" role="listitem">
			<span class="bahar-trust-badge__icon" aria-hidden="true">↩</span>
			<span><?php esc_html_e( 'ضمانت تعویض', 'bahar-shop' ); ?></span>
		</span>
	</div>
	<?php
}
