<?php
/**
 * Plugin Name: پرینت هوشمند شجاعی
 * Plugin URI:  https://github.com/esmaeelsh93-lab/esee
 * Description: چاپ گروهی و هوشمند سفارش‌های ووکامرس با پشتیبانی از HPOS، اندازه‌های مختلف کاغذ و ابعاد سفارشی.
 * Version:     1.3.1
 * Author:      Shojaei / to3edev
 * Text Domain: woocommerce-bulk-order-print
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 * WC tested up to: 9.0
 */

defined( 'ABSPATH' ) || exit;

define( 'WBOP_VERSION', '1.3.1' );
define( 'WBOP_FILE', __FILE__ );
define( 'WBOP_DIR', plugin_dir_path( __FILE__ ) );
define( 'WBOP_URL', plugin_dir_url( __FILE__ ) );
define( 'WBOP_BASENAME', plugin_basename( __FILE__ ) );

require_once WBOP_DIR . 'includes/class-wbop-settings.php';
require_once WBOP_DIR . 'includes/class-wbop-printer.php';
require_once WBOP_DIR . 'includes/class-wbop-plugin.php';

/**
 * Declare WooCommerce HPOS compatibility safely.
 */
add_action(
	'before_woocommerce_init',
	static function () {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
				'custom_order_tables',
				WBOP_FILE,
				true
			);
		}
	}
);

/**
 * Bootstrap after plugins are loaded.
 */
add_action(
	'plugins_loaded',
	static function () {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action(
				'admin_notices',
				static function () {
					if ( ! current_user_can( 'activate_plugins' ) ) {
						return;
					}
					echo '<div class="notice notice-error"><p>';
					echo esc_html( 'افزونه «پرینت هوشمند شجاعی» به ووکامرس نیاز دارد.' );
					echo '</p></div>';
				}
			);
			return;
		}

		( new WBOP_Plugin() )->init();
	}
);
