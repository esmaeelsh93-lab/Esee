<?php
/**
 * Checkout opt-in checkboxes for messenger channels.
 *
 * @package Esee_Order_Messenger
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Esee_OM_Checkout {

	public function hooks() {
		add_action( 'woocommerce_after_order_notes', array( $this, 'fields' ) );
		add_action( 'woocommerce_checkout_create_order', array( $this, 'save_to_order' ), 20, 2 );
		add_action( 'woocommerce_thankyou', array( $this, 'thankyou_bot_links' ), 12 );
	}

	public function fields( $checkout ) {
		$settings = Esee_OM_Settings::get();
		if ( '1' !== $settings['enabled'] ) {
			return;
		}

		echo '<div id="esee-om-channels"><h3>اطلاع‌رسانی سفارش</h3>';
		echo '<p>اگر می‌خواهید جزئیات سفارش برایتان پیامک‌رسان شود، کانال را تیک بزنید. این افزونه به‌صورت پنهان حساب واتساپ/روبیکا/بله شما را اسکن نمی‌کند.</p>';

		if ( '1' === $settings['whatsapp_enabled'] ) {
			woocommerce_form_field(
				'esee_om_whatsapp',
				array(
					'type'  => 'checkbox',
					'class' => array( 'form-row-wide' ),
					'label' => 'واتساپ دارم؛ اطلاعات سفارش را در واتساپ بفرستید',
				),
				$checkout->get_value( 'esee_om_whatsapp' )
			);
		}
		if ( '1' === $settings['rubika_enabled'] ) {
			woocommerce_form_field(
				'esee_om_rubika',
				array(
					'type'  => 'checkbox',
					'class' => array( 'form-row-wide' ),
					'label' => 'روبیکا دارم؛ اطلاعات سفارش را در روبیکا بفرستید',
				),
				$checkout->get_value( 'esee_om_rubika' )
			);
		}
		if ( '1' === $settings['bale_enabled'] ) {
			woocommerce_form_field(
				'esee_om_bale',
				array(
					'type'  => 'checkbox',
					'class' => array( 'form-row-wide' ),
					'label' => 'بله دارم؛ اطلاعات سفارش را در بله بفرستید',
				),
				$checkout->get_value( 'esee_om_bale' )
			);
		}

		echo '</div>';
	}

	/**
	 * @param WC_Order $order
	 * @param array    $data
	 */
	public function save_to_order( $order, $data ) {
		$channels = array();

		if ( ! empty( $_POST['esee_om_whatsapp'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$channels[] = 'whatsapp';
		}
		if ( ! empty( $_POST['esee_om_rubika'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$channels[] = 'rubika';
		}
		if ( ! empty( $_POST['esee_om_bale'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$channels[] = 'bale';
		}

		$channels = Esee_OM_Utils::sanitize_channels( $channels );
		$phone    = Esee_OM_Utils::normalize_phone( $order->get_billing_phone() );

		$order->update_meta_data( '_esee_om_channels', $channels );
		$order->update_meta_data( '_esee_om_phone', $phone );
	}

	public function thankyou_bot_links( $order_id ) {
		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		$channels = Esee_OM_Utils::sanitize_channels( $order->get_meta( '_esee_om_channels' ) );
		$settings = Esee_OM_Settings::get();
		$links    = array();

		if ( in_array( 'bale', $channels, true ) && ! $order->get_meta( '_esee_om_bale_chat_id' ) && $settings['bale_username'] ) {
			$links[] = array(
				'label' => 'برای دریافت پیام در بله، بازو را استارت کنید',
				'url'   => 'https://ble.ir/' . rawurlencode( ltrim( $settings['bale_username'], '@' ) ) . '?start=' . rawurlencode( (string) $order_id ),
			);
		}

		if ( in_array( 'rubika', $channels, true ) && ! $order->get_meta( '_esee_om_rubika_chat_id' ) && $settings['rubika_username'] ) {
			$links[] = array(
				'label' => 'برای دریافت پیام در روبیکا، بات را استارت کنید',
				'url'   => 'https://rubika.ir/' . rawurlencode( ltrim( $settings['rubika_username'], '@' ) ),
			);
		}

		if ( empty( $links ) ) {
			return;
		}

		echo '<section class="esee-om-thankyou"><h2>فعال‌سازی پیام‌رسان</h2><ul>';
		foreach ( $links as $link ) {
			echo '<li><a class="button" href="' . esc_url( $link['url'] ) . '" target="_blank" rel="noopener">' . esc_html( $link['label'] ) . '</a></li>';
		}
		echo '</ul></section>';
	}
}
