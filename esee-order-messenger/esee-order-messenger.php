<?php
/**
 * Plugin Name: Esee Order Messenger
 * Plugin URI:  https://github.com/esmaeelsh93-lab/esee
 * Description: اطلاع‌رسانی سفارش ووکامرس از طریق واتساپ، روبیکا و بله با API رسمی — بدون VPS و بدون اسکن غیررسمی شماره.
 * Version:     1.0.0
 * Author:      Esee
 * Text Domain: esee-order-messenger
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ESEE_OM_VERSION', '1.0.0' );
define( 'ESEE_OM_FILE', __FILE__ );
define( 'ESEE_OM_DIR', plugin_dir_path( __FILE__ ) );

require_once ESEE_OM_DIR . 'includes/class-esee-om-utils.php';
require_once ESEE_OM_DIR . 'includes/class-esee-om-settings.php';
require_once ESEE_OM_DIR . 'includes/class-esee-om-checkout.php';
require_once ESEE_OM_DIR . 'includes/class-esee-om-sender.php';
require_once ESEE_OM_DIR . 'includes/class-esee-om-webhooks.php';
require_once ESEE_OM_DIR . 'includes/class-esee-om-plugin.php';

add_action(
	'plugins_loaded',
	static function () {
		( new Esee_OM_Plugin() )->init();
	}
);
