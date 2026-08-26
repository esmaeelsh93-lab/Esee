<?php
/**
 * Smoke tests for helpers (no WordPress bootstrap).
 */

define( 'ABSPATH', __DIR__ );

require_once dirname( __DIR__ ) . '/includes/class-esee-om-utils.php';

$failed = 0;

function esee_om_assert( $ok, $label ) {
	global $failed;
	if ( $ok ) {
		echo "OK  {$label}\n";
		return;
	}
	$failed++;
	echo "FAIL {$label}\n";
}

esee_om_assert( '989121234567' === Esee_OM_Utils::normalize_phone( '09121234567' ), 'local 09 number' );
esee_om_assert( '989121234567' === Esee_OM_Utils::normalize_phone( '+98 912 123 4567' ), 'plus 98 spaced' );
esee_om_assert( '989121234567' === Esee_OM_Utils::normalize_phone( '00989121234567' ), '0098 prefix' );
esee_om_assert( '989121234567' === Esee_OM_Utils::normalize_phone( '9121234567' ), 'without leading zero' );
esee_om_assert( '' === Esee_OM_Utils::normalize_phone( 'abc' ), 'garbage phone' );

$text = Esee_OM_Utils::render_template( 'سلام {first_name} سفارش {order_id}', array( 'first_name' => 'علی', 'order_id' => '12' ) );
esee_om_assert( 'سلام علی سفارش 12' === $text, 'template tokens' );

$channels = Esee_OM_Utils::sanitize_channels( array( 'whatsapp', 'telegram', 'BALE', 'rubika' ) );
esee_om_assert( array( 'whatsapp', 'bale', 'rubika' ) === $channels, 'channel whitelist' );

if ( $failed ) {
	fwrite( STDERR, "Failed: {$failed}\n" );
	exit( 1 );
}

echo "All smoke tests passed.\n";
exit( 0 );
