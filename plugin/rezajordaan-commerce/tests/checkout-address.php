<?php
/**
 * Smoke tests for Iranian province/city checkout helpers.
 *
 * Run with: php plugin/rezajordaan-commerce/tests/checkout-address.php
 */

define( 'ABSPATH', __DIR__ . '/' );

require_once dirname( __DIR__ ) . '/includes/iran-address.php';

$failures = 0;

function rj_assert( $condition, $message ) {
	global $failures;

	if ( $condition ) {
		echo "PASS  {$message}\n";
		return;
	}

	$failures++;
	echo "FAIL  {$message}\n";
}

$states = rezajordaan_commerce_persian_states();
$cities = rezajordaan_commerce_cities_map();
$aliases = rezajordaan_commerce_state_aliases();

rj_assert( 31 === count( $states ), 'There are 31 Iranian provinces' );
rj_assert( 31 === count( $cities ), 'City list covers all 31 WooCommerce IR codes' );
rj_assert( count( $aliases ) === 31, 'Persian WooCommerce aliases cover every province' );

foreach ( $states as $code => $label ) {
	rj_assert( isset( $cities[ $code ] ) && count( $cities[ $code ] ) > 0, "Province {$code} ({$label}) has cities" );
}

rj_assert( in_array( 'کرج', rezajordaan_commerce_get_cities( 'ABZ' ), true ), 'Karaj is a city of Alborz' );
rj_assert( in_array( 'تهران', rezajordaan_commerce_get_cities( 'THR' ), true ), 'Tehran is a city of Tehran province' );
rj_assert( in_array( 'اصفهان', rezajordaan_commerce_get_cities( 'ESF' ), true ), 'Isfahan is a city of Isfahan province' );

rj_assert( 'ABZ' === rezajordaan_commerce_normalize_state_code( 'al' ), 'Persian WooCommerce AL alias becomes ABZ' );
rj_assert( 'THR' === rezajordaan_commerce_normalize_state_code( 'TE' ), 'Persian WooCommerce TE alias becomes THR' );
rj_assert( 'ABZ' === rezajordaan_commerce_normalize_state_code( 'البرز' ), 'Typed province name البرز becomes ABZ' );
rj_assert( 'THR' === rezajordaan_commerce_normalize_state_code( 'تهران' ), 'Typed province name تهران becomes THR' );

$wc_states = $states;
$typed     = 'البرز';
rj_assert( ! array_key_exists( $typed, $wc_states ), 'Free-text province names are not valid WooCommerce state keys' );
rj_assert( array_key_exists( rezajordaan_commerce_normalize_state_code( $typed ), $wc_states ), 'Normalized province name is a valid WooCommerce state key' );

rj_assert( rezajordaan_commerce_is_valid_state( 'ABZ' ), 'ABZ is a valid state' );
rj_assert( rezajordaan_commerce_is_valid_city( 'ABZ', 'کرج' ), 'Karaj is valid for Alborz' );
rj_assert( ! rezajordaan_commerce_is_valid_city( 'ABZ', 'تهران' ), 'Tehran city is rejected for Alborz' );
rj_assert( ! rezajordaan_commerce_is_valid_city( 'THR', 'کرج' ), 'Karaj is rejected for Tehran province' );
rj_assert( rezajordaan_commerce_is_valid_city( 'TE', 'تهران' ), 'City validation accepts Persian WooCommerce TE alias' );

$state_field = rezajordaan_commerce_state_field_args( 'البرز' );
$city_field  = rezajordaan_commerce_city_field_args( 'ABZ', 'کرج' );

rj_assert( 'state' === $state_field['type'], 'Province field type is WooCommerce state select' );
rj_assert( 'select' === $city_field['type'], 'City field type is select' );
rj_assert( 'ABZ' === $state_field['default'], 'Saved text province preselects ABZ' );
rj_assert( isset( $city_field['options']['کرج'] ), 'Karaj is offered after Alborz is selected' );
rj_assert( ! isset( $city_field['options']['تهران'] ), 'Tehran city is not offered for Alborz' );
rj_assert( 'IR' === $state_field['country'], 'State field is bound to Iran' );

function wc_like_accepts_state( $posted_state, $valid_states ) {
	return is_array( $valid_states ) && array_key_exists( $posted_state, $valid_states );
}

rj_assert( ! wc_like_accepts_state( 'البرز', $states ), 'WooCommerce rejects typed province name البرز' );
rj_assert( ! wc_like_accepts_state( 'تهران', $states ), 'WooCommerce rejects typed province name تهران' );
rj_assert( wc_like_accepts_state( 'ABZ', $states ), 'WooCommerce accepts selected Alborz code ABZ' );
rj_assert( wc_like_accepts_state( 'THR', $states ), 'WooCommerce accepts selected Tehran code THR' );

$empty_city = rezajordaan_commerce_city_field_args( '', '' );
rj_assert( 'ابتدا استان را انتخاب کنید' === $empty_city['options'][''], 'City placeholder asks for province first' );

$total = 0;
foreach ( $cities as $list ) {
	$total += count( $list );
}
rj_assert( $total >= 1000, "Bundled city count is comprehensive ({$total})" );

if ( $failures ) {
	echo "\n{$failures} assertion(s) failed.\n";
	exit( 1 );
}

echo "\nAll checkout address assertions passed ({$total} cities).\n";
exit( 0 );
