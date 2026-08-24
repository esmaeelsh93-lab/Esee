<?php
/**
 * Uninstall cleanup.
 *
 * @package WooCommerce_Bulk_Order_Print
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'wbop_settings' );
delete_option( 'wbop_printed_order_ids' );
