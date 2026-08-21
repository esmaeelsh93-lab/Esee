<?php
/**
 * Bootstrap.
 *
 * @package Esee_Order_Messenger
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Esee_OM_Plugin {

	public function init() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'need_woocommerce' ) );
			return;
		}

		$settings = Esee_OM_Settings::get();
		if ( empty( $settings['webhook_secret'] ) ) {
			$settings['webhook_secret'] = wp_generate_password( 20, false, false );
			update_option( Esee_OM_Settings::OPTION, $settings );
		}

		( new Esee_OM_Settings() )->hooks();
		( new Esee_OM_Checkout() )->hooks();
		( new Esee_OM_Sender() )->hooks();
		( new Esee_OM_Webhooks() )->hooks();
	}

	public function need_woocommerce() {
		echo '<div class="notice notice-error"><p>افزونه پیام‌رسان سفارش به ووکامرس نیاز دارد.</p></div>';
	}
}
