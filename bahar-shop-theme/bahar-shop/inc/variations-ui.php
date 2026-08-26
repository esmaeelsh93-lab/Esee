<?php
/**
 * Variation picker — horizontal pill buttons (no dropdown).
 *
 * @package Bahar_Shop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_filter( 'woocommerce_dropdown_variation_attribute_options_html', 'bahar_shop_variation_pills_html', 999, 2 );
add_filter( 'woocommerce_variation_option_name', 'bahar_shop_variation_option_label', 10, 1 );
add_filter( 'woocommerce_reset_variations_link', 'bahar_shop_reset_variations_link' );

add_action( 'wp_enqueue_scripts', 'bahar_shop_dequeue_variation_swatches', 120 );
add_action( 'woocommerce_before_variations_form', 'bahar_shop_output_variation_stock_json', 5 );
add_action( 'woocommerce_before_variations_form', 'bahar_shop_variation_box_open', 6 );
add_action( 'woocommerce_after_variations_table', 'bahar_shop_variation_clear_button', 8 );
add_action( 'woocommerce_after_variations_table', 'bahar_shop_variation_box_close', 20 );
add_action( 'wp_head', 'bahar_shop_variation_label_hide_css', 99 );

/**
 * Open bold variation selection box.
 */
function bahar_shop_variation_box_open() {
	echo '<div class="bahar-variation-box">';
}

/**
 * Close bold variation selection box.
 */
function bahar_shop_variation_box_close() {
	echo '</div>';
}

/**
 * Hide duplicate WooCommerce variation labels (name is shown in our hint box).
 */
function bahar_shop_variation_label_hide_css() {
	if ( ! is_product() ) {
		return;
	}
	?>
	<style id="bahar-variation-label-hide">
		.bahar-single-product form.variations_form table.variations .label,
		.bahar-single-product form.variations_form table.variations td.label,
		.bahar-single-product form.variations_form table.variations th.label,
		.bahar-single-product form.variations_form table.variations .label label,
		.bahar-single-product form.variations_form table.variations label {
			display: none !important;
			visibility: hidden !important;
			height: 0 !important;
			width: 0 !important;
			margin: 0 !important;
			padding: 0 !important;
			overflow: hidden !important;
			position: absolute !important;
			clip: rect(0, 0, 0, 0) !important;
		}
	</style>
	<?php
}

/**
 * Prefer theme variation UI over swatches plugin assets.
 */
function bahar_shop_dequeue_variation_swatches() {
	if ( ! is_product() ) {
		return;
	}

	wp_dequeue_style( 'woo-variation-swatches' );
	wp_dequeue_script( 'woo-variation-swatches' );
}

/**
 * Hide default WooCommerce reset link (custom clear button is used).
 *
 * @param string $link Reset link HTML.
 * @return string
 */
function bahar_shop_reset_variations_link( $link ) {
	return '<a class="reset_variations bahar-reset-variations--hidden" href="#" style="display:none !important" aria-hidden="true">' . esc_html__( 'صاف کردن', 'woocommerce' ) . '</a>';
}

/**
 * Compare WooCommerce attribute keys (handles custom Persian names + slugs).
 *
 * @param string $attr_name Attribute name on product.
 * @param string $attribute Attribute key from dropdown args.
 * @return bool
 */
function bahar_shop_attribute_keys_match( $attr_name, $attribute ) {
	if ( $attr_name === $attribute ) {
		return true;
	}

	$attr_slug = sanitize_title( $attr_name );
	$arg_slug  = sanitize_title( $attribute );

	if ( $attr_slug === $arg_slug || $attr_slug === $attribute || $attr_name === $arg_slug ) {
		return true;
	}

	$clean = preg_replace( '/^attribute_/', '', (string) $attribute );
	if ( $attr_name === $clean || $attr_slug === sanitize_title( $clean ) ) {
		return true;
	}

	return false;
}

/**
 * Label shown above each variation row — matches the attribute "نام" in product admin.
 *
 * @param WC_Product $product   Product object.
 * @param string     $attribute Attribute key/name.
 * @return string
 */
function bahar_shop_get_variation_attribute_label( $product, $attribute ) {
	if ( ! $product instanceof WC_Product ) {
		return wc_attribute_label( $attribute );
	}

	foreach ( $product->get_attributes() as $attr ) {
		if ( ! $attr->get_variation() ) {
			continue;
		}

		$attr_name = $attr->get_name();
		if ( ! bahar_shop_attribute_keys_match( $attr_name, $attribute ) ) {
			continue;
		}

		if ( $attr->is_taxonomy() ) {
			$label = wc_attribute_label( $attr_name );
		} else {
			$label = $attr_name;
		}

		/**
		 * Filter attribute label before it is shown in the variation hint box.
		 *
		 * @param string     $label     Label text.
		 * @param string     $attr_name Attribute key.
		 * @param WC_Product $product   Product object.
		 */
		return apply_filters( 'bahar_shop_variation_attribute_label', $label, $attr_name, $product );
	}

	return wc_attribute_label( $attribute, $product );
}

/**
 * Render attribute label box above variation pill buttons.
 *
 * @param WC_Product $product   Product object.
 * @param string     $attribute Attribute key/name.
 * @return string
 */
function bahar_shop_render_variation_hint_box( $product, $attribute ) {
	$label = trim( wp_strip_all_tags( bahar_shop_get_variation_attribute_label( $product, $attribute ) ) );

	if ( '' === $label ) {
		$label = trim( wp_strip_all_tags( wc_attribute_label( $attribute, $product ) ) );
	}

	if ( '' === $label ) {
		return '';
	}

	ob_start();
	?>
	<div
		class="bahar-variation-hint"
		role="note"
		style="display:flex;align-items:flex-start;gap:.55rem;margin-bottom:.65rem;padding:.7rem .9rem;border-radius:14px;background:linear-gradient(135deg,rgba(255,240,247,.95),rgba(255,255,255,.88));border:1px solid rgba(255,142,199,.45);box-shadow:0 6px 18px rgba(232,74,154,.1);color:#e84a9a;font-size:.88rem;font-weight:700;line-height:1.55;"
	>
		<span class="bahar-variation-hint__icon" aria-hidden="true">💕</span>
		<span class="bahar-variation-hint__text"><?php echo esc_html( $label ); ?></span>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Custom clear-selection button below variation table.
 */
function bahar_shop_variation_clear_button() {
	global $product;

	if ( ! $product instanceof WC_Product_Variable ) {
		return;
	}
	?>
	<div class="bahar-variation-clear-wrap" id="bahar-variation-clear-wrap" hidden>
		<button type="button" class="bahar-variation-clear-btn" id="bahar-variation-clear-btn" aria-label="<?php esc_attr_e( 'پاک کردن انتخاب متغیرها', 'bahar-shop' ); ?>">
			<span class="bahar-variation-clear-btn__icon" aria-hidden="true">✕</span>
			<span><?php esc_html_e( 'پاک کردن انتخاب', 'bahar-shop' ); ?></span>
		</button>
	</div>
	<?php
}

/**
 * Friendly labels for legacy attribute spellings.
 *
 * @param string $option Option label.
 * @return string
 */
function bahar_shop_variation_option_label( $option ) {
	$map = array(
		'شرت'   => 'شورت',
		'شورتک' => 'شورت',
	);

	return isset( $map[ $option ] ) ? $map[ $option ] : $option;
}

/**
 * Replace variation dropdown with horizontal pill buttons.
 *
 * @param string $html Default dropdown HTML.
 * @param array  $args Dropdown args.
 * @return string
 */
function bahar_shop_variation_pills_html( $html, $args ) {
	$product = isset( $args['product'] ) ? $args['product'] : null;

	if ( ! $product instanceof WC_Product || ! $product->is_type( 'variable' ) || empty( $args['attribute'] ) ) {
		return $html;
	}

	$attribute = $args['attribute'];
	$options   = bahar_shop_collect_attribute_options( $product, $attribute, $args['options'] ?? false );

	if ( empty( $options ) ) {
		return $html;
	}

	$name     = ! empty( $args['name'] ) ? $args['name'] : wc_variation_attribute_name( $attribute );
	$id       = ! empty( $args['id'] ) ? $args['id'] : sanitize_title( $attribute );
	$selected = $args['selected'] ?? false;
	$class    = isset( $args['class'] ) ? $args['class'] : '';

	if ( false === $selected ) {
		$selected_key = wc_variation_attribute_name( $attribute );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$selected = isset( $_REQUEST[ $selected_key ] ) ? wc_clean( wp_unslash( $_REQUEST[ $selected_key ] ) ) : $product->get_variation_default_attribute( $attribute );
	}

	$attribute_label = bahar_shop_get_variation_attribute_label( $product, $attribute );

	ob_start();
	?>
	<div class="bahar-variation-picker" data-attribute="<?php echo esc_attr( sanitize_title( $attribute ) ); ?>">
		<?php
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo bahar_shop_render_variation_hint_box( $product, $attribute );
		?>
		<select
			id="<?php echo esc_attr( $id ); ?>"
			class="bahar-variation-select <?php echo esc_attr( $class ); ?>"
			name="<?php echo esc_attr( $name ); ?>"
			data-attribute_name="<?php echo esc_attr( wc_variation_attribute_name( $attribute ) ); ?>"
			data-show_option_none="yes"
		>
			<option value=""><?php esc_html_e( 'یک گزینه را انتخاب کنید', 'bahar-shop' ); ?></option>
			<?php foreach ( $options as $option ) : ?>
				<?php
				$label     = apply_filters( 'woocommerce_variation_option_name', $option, null, $attribute, $product );
				$is_active = ( (string) $selected === (string) $option || sanitize_title( (string) $selected ) === sanitize_title( (string) $option ) );
				?>
				<option value="<?php echo esc_attr( $option ); ?>" <?php selected( $is_active, true ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<div class="bahar-variation-buttons" role="group" aria-label="<?php echo esc_attr( $attribute_label ); ?>">
			<?php
			$stock_map = bahar_shop_get_option_stock_map( $product, $attribute );
			foreach ( $options as $option ) :
				$label     = apply_filters( 'woocommerce_variation_option_name', $option, null, $attribute, $product );
				$is_active = ( (string) $selected === (string) $option || sanitize_title( (string) $selected ) === sanitize_title( (string) $option ) );
				$in_stock  = ! empty( $stock_map[ $option ] );
				$classes   = 'bahar-variation-btn';
				if ( $is_active && $in_stock ) {
					$classes .= ' is-active';
				}
				if ( ! $in_stock ) {
					$classes .= ' is-out-of-stock';
				}
				?>
				<button
					type="button"
					class="<?php echo esc_attr( $classes ); ?>"
					data-value="<?php echo esc_attr( $option ); ?>"
					aria-pressed="<?php echo ( $is_active && $in_stock ) ? 'true' : 'false'; ?>"
					aria-disabled="<?php echo $in_stock ? 'false' : 'true'; ?>"
					<?php disabled( ! $in_stock ); ?>
					title="<?php echo $in_stock ? '' : esc_attr__( 'ناموجود', 'bahar-shop' ); ?>"
				>
					<span class="bahar-variation-btn__label"><?php echo esc_html( $label ); ?></span>
					<?php if ( ! $in_stock ) : ?>
						<span class="bahar-variation-btn__x" aria-hidden="true">✕</span>
					<?php endif; ?>
				</button>
			<?php endforeach; ?>
		</div>
	</div>
	<?php
	return ob_get_clean();
}

/**
 * Collect all attribute options from parent meta and child variations.
 *
 * @param WC_Product $product   Variable product.
 * @param string     $attribute Attribute name.
 * @param mixed      $fallback  Options from WooCommerce.
 * @return array<int, string>
 */
function bahar_shop_collect_attribute_options( $product, $attribute, $fallback ) {
	$options = array();

	if ( ! empty( $fallback ) && is_array( $fallback ) ) {
		$options = array_merge( $options, $fallback );
	}

	foreach ( $product->get_attributes() as $attr ) {
		if ( ! $attr->get_variation() ) {
			continue;
		}

		$attr_name = $attr->get_name();
		if ( $attr_name !== $attribute && sanitize_title( $attr_name ) !== sanitize_title( $attribute ) ) {
			continue;
		}

		if ( $attr->is_taxonomy() ) {
			$terms = wc_get_product_terms( $product->get_id(), $attr_name, array( 'fields' => 'names' ) );
			if ( ! is_wp_error( $terms ) ) {
				$options = array_merge( $options, $terms );
			}
		} else {
			$raw = $attr->get_options();
			if ( is_array( $raw ) ) {
				$options = array_merge( $options, $raw );
			}
		}
	}

	foreach ( $product->get_children() as $child_id ) {
		$variation = wc_get_product( $child_id );
		if ( ! $variation ) {
			continue;
		}

		$var_attrs = $variation->get_attributes();
		foreach ( $var_attrs as $key => $value ) {
			if ( $key === $attribute || sanitize_title( $key ) === sanitize_title( $attribute ) ) {
				if ( '' !== (string) $value ) {
					$options[] = (string) $value;
				}
			}
		}
	}

	$options = array_map( 'trim', $options );
	$options = array_filter( $options );
	$options = array_unique( $options );

	return array_values( $options );
}

/**
 * Build stock map for a variation attribute.
 *
 * @param WC_Product_Variable $product   Variable product.
 * @param string              $attribute Attribute name.
 * @return array<string, bool>
 */
function bahar_shop_get_option_stock_map( $product, $attribute ) {
	$map = array();

	if ( ! $product instanceof WC_Product_Variable ) {
		return $map;
	}

	foreach ( $product->get_children() as $child_id ) {
		$variation = wc_get_product( $child_id );
		if ( ! $variation ) {
			continue;
		}

		$var_attrs = $variation->get_attributes();
		$value     = '';

		foreach ( $var_attrs as $key => $attr_value ) {
			if ( $key === $attribute || sanitize_title( $key ) === sanitize_title( $attribute ) ) {
				$value = (string) $attr_value;
				break;
			}
		}

		if ( '' === $value ) {
			continue;
		}

		$available = $variation->is_purchasable() && $variation->is_in_stock();

		if ( ! isset( $map[ $value ] ) ) {
			$map[ $value ] = $available;
		} elseif ( $available ) {
			$map[ $value ] = true;
		}
	}

	return $map;
}

/**
 * Output variation stock JSON for JS.
 */
function bahar_shop_output_variation_stock_json() {
	global $product;

	if ( ! $product instanceof WC_Product_Variable ) {
		return;
	}

	$data = array();

	foreach ( $product->get_variation_attributes() as $attribute => $options ) {
		$key          = sanitize_title( $attribute );
		$data[ $key ] = bahar_shop_get_option_stock_map( $product, $attribute );
	}

	printf(
		'<script type="application/json" id="bahar-variation-stock">%s</script>',
		wp_json_encode( $data )
	);
}
