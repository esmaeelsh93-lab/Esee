<?php
/**
 * OOS restock notification (AJAX subscribe + email blast).
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Damavand_OOS_Notifier
 */
class Damavand_OOS_Notifier {

	/**
	 * AJAX: subscribe email for restock notice on this product.
	 */
	public function ajax_restock_notify(): void {
		check_ajax_referer( 'shojaei_seo_oos_notify', 'nonce' );

		if ( 'yes' !== Shojaei_SEO_Helpers::get_option( 'shojaei_seo_oos_notify_enabled', 'no' ) ) {
			wp_send_json_error( array( 'message' => __( 'اطلاع‌رسانی موجودی خاموش است.', 'shojaei-seo-for-woo' ) ) );
		}

		$ip       = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '0';
		$rate_key = 'shojaei_oos_notify_' . md5( $ip );
		$hits     = (int) get_transient( $rate_key );
		if ( $hits >= 8 ) {
			wp_send_json_error( array( 'message' => __( 'تعداد درخواست‌ها زیاد است؛ کمی بعد دوباره تلاش کنید.', 'shojaei-seo-for-woo' ) ) );
		}
		set_transient( $rate_key, $hits + 1, HOUR_IN_SECONDS );

		$product_id = absint( $_POST['product_id'] ?? 0 );
		$email      = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );

		if ( $product_id < 1 || ! is_email( $email ) ) {
			wp_send_json_error( array( 'message' => __( 'ایمیل معتبر وارد کنید.', 'shojaei-seo-for-woo' ) ) );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product || $product->is_in_stock() ) {
			wp_send_json_error( array( 'message' => __( 'این محصول الان ناموجود نیست.', 'shojaei-seo-for-woo' ) ) );
		}

		$list = get_post_meta( $product_id, '_shojaei_seo_restock_emails', true );
		if ( ! is_array( $list ) ) {
			$list = array();
		}
		$email_l = strtolower( $email );
		foreach ( $list as $row ) {
			if ( isset( $row['email'] ) && strtolower( (string) $row['email'] ) === $email_l ) {
				wp_send_json_success( array( 'message' => __( 'قبلاً ثبت شده‌اید؛ به‌محض موجود شدن خبر می‌دهیم.', 'shojaei-seo-for-woo' ) ) );
			}
		}

		$list[] = array(
			'email' => $email,
			'time'  => time(),
			'user'  => get_current_user_id(),
		);
		// Cap list size per product.
		if ( count( $list ) > 200 ) {
			$list = array_slice( $list, -200 );
		}
		update_post_meta( $product_id, '_shojaei_seo_restock_emails', $list );

		wp_send_json_success( array( 'message' => __( 'ثبت شد. وقتی موجود شود به ایمیلتان خبر می‌دهیم.', 'shojaei-seo-for-woo' ) ) );
	}

	/**
	 * Email restock subscribers and clear the list.
	 *
	 * @param int $product_id Product ID.
	 */
	public static function notify_restock_subscribers( int $product_id ): void {
		$list = get_post_meta( $product_id, '_shojaei_seo_restock_emails', true );
		if ( ! is_array( $list ) || empty( $list ) ) {
			return;
		}

		$title = get_the_title( $product_id );
		$url   = get_permalink( $product_id );
		$subj  = sprintf(
			/* translators: %s: product title */
			__( 'موجود شد: %s', 'shojaei-seo-for-woo' ),
			$title
		);
		$body = sprintf(
			/* translators: 1: product title, 2: url */
			__( "سلام،\n\nمحصول «%1\$s» دوباره موجود شد:\n%2\$s\n", 'shojaei-seo-for-woo' ),
			$title,
			$url
		);

		foreach ( $list as $row ) {
			$email = isset( $row['email'] ) ? sanitize_email( (string) $row['email'] ) : '';
			if ( ! is_email( $email ) ) {
				continue;
			}
			wp_mail( $email, $subj, $body );
		}

		delete_post_meta( $product_id, '_shojaei_seo_restock_emails' );
	}
}
