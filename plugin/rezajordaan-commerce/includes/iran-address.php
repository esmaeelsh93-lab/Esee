<?php
/**
 * Iranian province/city helpers for classic WooCommerce checkout.
 *
 * State codes match WooCommerce core (`i18n/states.php` for IR). Persian
 * WooCommerce aliases (TE, AL, …) are normalized back to those codes so
 * order validation succeeds.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Return WooCommerce IR state codes mapped to Persian province names.
 *
 * @return array<string,string>
 */
function rezajordaan_commerce_persian_states() {
	return array(
		'ABZ' => 'البرز',
		'ADL' => 'اردبیل',
		'EAZ' => 'آذربایجان شرقی',
		'WAZ' => 'آذربایجان غربی',
		'BHR' => 'بوشهر',
		'CHB' => 'چهارمحال و بختیاری',
		'SKH' => 'خراسان جنوبی',
		'RKH' => 'خراسان رضوی',
		'NKH' => 'خراسان شمالی',
		'KHZ' => 'خوزستان',
		'ZJN' => 'زنجان',
		'SMN' => 'سمنان',
		'SBN' => 'سیستان و بلوچستان',
		'FRS' => 'فارس',
		'QHM' => 'قم',
		'GZN' => 'قزوین',
		'KRD' => 'کردستان',
		'KRN' => 'کرمان',
		'KRH' => 'کرمانشاه',
		'KBD' => 'کهگیلویه و بویراحمد',
		'GLS' => 'گلستان',
		'GIL' => 'گیلان',
		'LRS' => 'لرستان',
		'MZN' => 'مازندران',
		'MKZ' => 'مرکزی',
		'HRZ' => 'هرمزگان',
		'HDN' => 'همدان',
		'YZD' => 'یزد',
		'THR' => 'تهران',
		'ILM' => 'ایلام',
		'ESF' => 'اصفهان',
	);
}

/**
 * Map Persian WooCommerce / legacy codes onto WooCommerce core IR codes.
 *
 * @return array<string,string>
 */
function rezajordaan_commerce_state_aliases() {
	return array(
		'AL'  => 'ABZ',
		'AR'  => 'ADL',
		'AE'  => 'EAZ',
		'AW'  => 'WAZ',
		'BU'  => 'BHR',
		'CM'  => 'CHB',
		'KJ'  => 'SKH',
		'KV'  => 'RKH',
		'KS'  => 'NKH',
		'KZ'  => 'KHZ',
		'ZA'  => 'ZJN',
		'SM'  => 'SMN',
		'SB'  => 'SBN',
		'FA'  => 'FRS',
		'QM'  => 'QHM',
		'QZ'  => 'GZN',
		'KD'  => 'KRD',
		'KE'  => 'KRN',
		'BK'  => 'KRH',
		'KB'  => 'KBD',
		'GO'  => 'GLS',
		'GI'  => 'GIL',
		'LO'  => 'LRS',
		'MN'  => 'MZN',
		'MK'  => 'MKZ',
		'HG'  => 'HRZ',
		'HD'  => 'HDN',
		'YA'  => 'YZD',
		'TE'  => 'THR',
		'IL'  => 'ILM',
		'IS'  => 'ESF',
	);
}

/**
 * Normalize Arabic Yeh/Keheh so typed or stored names match the city list.
 *
 * @param string $text Raw Persian text.
 * @return string
 */
function rezajordaan_commerce_normalize_persian( $text ) {
	$text = str_replace(
		array( 'ي', 'ك', 'ة', '‌' ),
		array( 'ی', 'ک', 'ه', ' ' ),
		(string) $text
	);

	return trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
}

/**
 * Absolute path to the bundled cities JSON.
 *
 * @return string
 */
function rezajordaan_commerce_cities_data_file() {
	if ( defined( 'REZAJORDAAN_COMMERCE_PATH' ) ) {
		return REZAJORDAAN_COMMERCE_PATH . 'data/iran-cities.json';
	}

	return dirname( __DIR__ ) . '/data/iran-cities.json';
}

/**
 * Return cities grouped by WooCommerce IR state code.
 *
 * @return array<string,string[]>
 */
function rezajordaan_commerce_cities_map() {
	static $cities = null;

	if ( null !== $cities ) {
		return $cities;
	}

	$file = rezajordaan_commerce_cities_data_file();
	if ( ! is_readable( $file ) ) {
		$cities = array();
		return $cities;
	}

	$decoded = json_decode( (string) file_get_contents( $file ), true );
	$cities  = is_array( $decoded ) ? $decoded : array();

	return $cities;
}

/**
 * Convert a posted or stored province value into a WooCommerce IR state code.
 *
 * @param string $value State code, Persian WooCommerce alias, or Persian name.
 * @return string
 */
function rezajordaan_commerce_normalize_state_code( $value ) {
	$value = trim( (string) $value );
	if ( '' === $value ) {
		return '';
	}

	$states  = rezajordaan_commerce_persian_states();
	$aliases = rezajordaan_commerce_state_aliases();
	$upper   = strtoupper( $value );

	if ( isset( $states[ $upper ] ) ) {
		return $upper;
	}

	if ( isset( $aliases[ $upper ] ) ) {
		return $aliases[ $upper ];
	}

	$normalized = rezajordaan_commerce_normalize_persian( $value );
	foreach ( $states as $code => $label ) {
		if ( rezajordaan_commerce_normalize_persian( $label ) === $normalized ) {
			return $code;
		}
	}

	return $upper;
}

/**
 * Whether the value is a known Iranian province.
 *
 * @param string $value Raw state value.
 * @return bool
 */
function rezajordaan_commerce_is_valid_state( $value ) {
	$code = rezajordaan_commerce_normalize_state_code( $value );
	return isset( rezajordaan_commerce_persian_states()[ $code ] );
}

/**
 * Cities belonging to a province.
 *
 * @param string $state Raw state value.
 * @return string[]
 */
function rezajordaan_commerce_get_cities( $state ) {
	$code = rezajordaan_commerce_normalize_state_code( $state );
	$map  = rezajordaan_commerce_cities_map();

	return isset( $map[ $code ] ) && is_array( $map[ $code ] ) ? $map[ $code ] : array();
}

/**
 * Whether a city belongs to the selected province.
 *
 * @param string $state Raw state value.
 * @param string $city  City name.
 * @return bool
 */
function rezajordaan_commerce_is_valid_city( $state, $city ) {
	$city = rezajordaan_commerce_normalize_persian( $city );
	if ( '' === $city ) {
		return false;
	}

	foreach ( rezajordaan_commerce_get_cities( $state ) as $candidate ) {
		if ( rezajordaan_commerce_normalize_persian( $candidate ) === $city ) {
			return true;
		}
	}

	return false;
}

/**
 * Select options for the city field.
 *
 * @param string $state Raw state value.
 * @return array<string,string>
 */
function rezajordaan_commerce_city_options( $state ) {
	$cities = rezajordaan_commerce_get_cities( $state );
	$options = array(
		'' => $cities ? 'شهر را انتخاب کنید' : 'ابتدا استان را انتخاب کنید',
	);

	foreach ( $cities as $city ) {
		$options[ $city ] = $city;
	}

	return $options;
}

/**
 * State field arguments for billing/shipping.
 *
 * @param string $current_state Current value.
 * @return array<string,mixed>
 */
function rezajordaan_commerce_state_field_args( $current_state = '' ) {
	$code = rezajordaan_commerce_normalize_state_code( $current_state );

	return array(
		'type'         => 'state',
		'label'        => 'استان',
		'placeholder'  => 'استان را انتخاب کنید',
		'required'     => true,
		'class'        => array( 'form-row-first', 'address-field', 'update_totals_on_change' ),
		'autocomplete' => 'address-level1',
		'priority'     => 40,
		'country'      => 'IR',
		'default'      => $code,
		'input_class'  => array( 'state_select', 'rj-state-select' ),
	);
}

/**
 * City field arguments for billing/shipping.
 *
 * @param string $current_state Current province value.
 * @param string $current_city  Current city value.
 * @return array<string,mixed>
 */
function rezajordaan_commerce_city_field_args( $current_state = '', $current_city = '' ) {
	$state   = rezajordaan_commerce_normalize_state_code( $current_state );
	$options = rezajordaan_commerce_city_options( $state );
	$city    = rezajordaan_commerce_normalize_persian( $current_city );

	if ( '' !== $city && ! isset( $options[ $current_city ] ) ) {
		foreach ( $options as $value => $label ) {
			if ( rezajordaan_commerce_normalize_persian( (string) $value ) === $city ) {
				$current_city = $value;
				break;
			}
		}
	}

	return array(
		'type'              => 'rj_city',
		'label'             => 'شهر',
		'required'          => true,
		'class'             => array( 'form-row-last', 'address-field', 'update_totals_on_change' ),
		'autocomplete'      => 'address-level2',
		'priority'          => 50,
		'options'           => $options,
		'default'           => $current_city,
		'input_class'       => array( 'rj-city-select' ),
		'rj_groups'         => rezajordaan_commerce_city_groups( $state ),
		'custom_attributes' => array(
			'data-placeholder' => 'شهر را انتخاب کنید',
			'data-selected'    => $current_city,
		),
	);
}

/**
 * City optgroups for checkout. Unknown province → all provinces, so the
 * city list is searchable even if JavaScript does not run.
 *
 * @param string $state Raw state value.
 * @return array<string,array{label:string,cities:string[]}>
 */
function rezajordaan_commerce_city_groups( $state = '' ) {
	$states = rezajordaan_commerce_persian_states();
	$map    = rezajordaan_commerce_cities_map();
	$code   = rezajordaan_commerce_normalize_state_code( $state );
	$groups = array();

	$codes = ( $code && isset( $map[ $code ] ) ) ? array( $code ) : array_keys( $states );

	foreach ( $codes as $state_code ) {
		if ( ! isset( $states[ $state_code ] ) ) {
			continue;
		}

		$groups[ $state_code ] = array(
			'label'  => $states[ $state_code ],
			'cities' => isset( $map[ $state_code ] ) && is_array( $map[ $state_code ] ) ? $map[ $state_code ] : array(),
		);
	}

	return $groups;
}

/**
 * Frontend payload for the city dropdown script.
 *
 * @return array<string,mixed>
 */
function rezajordaan_commerce_address_script_data() {
	$states = rezajordaan_commerce_persian_states();
	$labels = array();

	foreach ( $states as $code => $label ) {
		$labels[ $label ] = $code;
		$labels[ rezajordaan_commerce_normalize_persian( $label ) ] = $code;
	}

	return array(
		'cities'  => rezajordaan_commerce_cities_map(),
		'aliases' => rezajordaan_commerce_state_aliases(),
		'labels'  => $labels,
		'states'  => $states,
		'i18n'    => array(
			'chooseCity' => 'شهر را انتخاب کنید',
		),
	);
}
