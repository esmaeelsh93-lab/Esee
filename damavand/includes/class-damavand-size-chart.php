<?php
/**
 * Persistent product size chart — shown before short description.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Damavand_Size_Chart
 */
final class Damavand_Size_Chart {

	public const META_RAW  = '_damavand_size_chart_raw';
	public const META_HTML = '_damavand_size_chart_html';

	/**
	 * Boot admin + front hooks.
	 */
	public static function register_hooks(): void {
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'render_product_field' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_product_field' ), 20 );
		add_action( 'woocommerce_single_product_summary', array( __CLASS__, 'render_before_short_desc' ), 19 );
		add_filter( 'woocommerce_short_description', array( __CLASS__, 'prepend_to_short_desc' ), 5 );
	}

	/**
	 * Raw textarea value for a product.
	 */
	public static function get_raw( int $product_id ): string {
		return trim( (string) get_post_meta( $product_id, self::META_RAW, true ) );
	}

	/**
	 * HTML table for front-end (built from raw or stored HTML).
	 */
	public static function get_html( int $product_id ): string {
		$html = trim( (string) get_post_meta( $product_id, self::META_HTML, true ) );
		if ( '' !== $html ) {
			return $html;
		}
		$raw = self::get_raw( $product_id );
		if ( '' === $raw || ! class_exists( 'Shojaei_SEO_AI_Client' ) ) {
			return '';
		}
		$table = Shojaei_SEO_AI_Client::build_size_table_html( $raw );
		return '' === $table ? '' : '<div class="damavand-size-chart"><h3 class="damavand-size-chart__title">' . esc_html__( 'جدول سایزبندی', 'shojaei-seo-for-woo' ) . '</h3>' . $table . '</div>';
	}

	/**
	 * Save raw sizes and derived HTML.
	 */
	public static function save( int $product_id, string $raw ): void {
		$raw = trim( $raw );
		if ( '' === $raw ) {
			delete_post_meta( $product_id, self::META_RAW );
			delete_post_meta( $product_id, self::META_HTML );
			return;
		}
		update_post_meta( $product_id, self::META_RAW, $raw );
		$html = '';
		if ( class_exists( 'Shojaei_SEO_AI_Client' ) ) {
			$table = Shojaei_SEO_AI_Client::build_size_table_html( $raw );
			if ( '' !== $table ) {
				$html = '<div class="damavand-size-chart"><h3 class="damavand-size-chart__title">' . esc_html__( 'جدول سایزبندی', 'shojaei-seo-for-woo' ) . '</h3>' . $table . '</div>';
			}
		}
		if ( '' === $html ) {
			delete_post_meta( $product_id, self::META_HTML );
		} else {
			update_post_meta( $product_id, self::META_HTML, $html );
		}
	}

	/**
	 * WooCommerce product data panel field.
	 */
	public static function render_product_field(): void {
		global $post;
		if ( ! $post instanceof WP_Post ) {
			return;
		}
		$raw = self::get_raw( (int) $post->ID );
		echo '<div class="options_group damavand-size-chart-field">';
		woocommerce_wp_textarea_input(
			array(
				'id'          => 'damavand_size_chart_raw',
				'label'       => __( 'جدول سایزبندی (Damavand)', 'shojaei-seo-for-woo' ),
				'description' => __( 'هر خط: سایز|قد|دور سینه یا با Tab. قبل از توضیح کوتاه در صفحه محصول نمایش داده می‌شود. روی SEO و محتوای اصلی تأثیر نمی‌گذارد.', 'shojaei-seo-for-woo' ),
				'desc_tip'    => true,
				'value'       => $raw,
				'rows'        => 5,
				'placeholder' => "سایز\tقد\tدور سینه\nM\t170\t96\nL\t175\t100",
			)
		);
		echo '</div>';
	}

	/**
	 * Persist from product save.
	 *
	 * @param int $product_id Product ID.
	 */
	public static function save_product_field( int $product_id ): void {
		if ( ! isset( $_POST['damavand_size_chart_raw'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return;
		}
		$raw = sanitize_textarea_field( wp_unslash( $_POST['damavand_size_chart_raw'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		self::save( $product_id, $raw );
	}

	/**
	 * Print chart just before short description in product summary.
	 */
	public static function render_before_short_desc(): void {
		if ( ! is_product() ) {
			return;
		}
		// Avoid double output when short_description filter also prepends.
		if ( did_action( 'damavand_size_chart_printed' ) ) {
			return;
		}
		$html = self::get_html( (int) get_the_ID() );
		if ( '' === $html ) {
			return;
		}
		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- stored HTML built via esc_html cells.
		echo $html;
		do_action( 'damavand_size_chart_printed' );
	}

	/**
	 * Fallback prepend when themes only print excerpt filter (skip if already printed).
	 *
	 * @param string $desc Short description HTML.
	 */
	public static function prepend_to_short_desc( $desc ): string {
		if ( ! is_product() || did_action( 'damavand_size_chart_printed' ) ) {
			return (string) $desc;
		}
		$html = self::get_html( (int) get_the_ID() );
		if ( '' === $html ) {
			return (string) $desc;
		}
		do_action( 'damavand_size_chart_printed' );
		return $html . (string) $desc;
	}
}
