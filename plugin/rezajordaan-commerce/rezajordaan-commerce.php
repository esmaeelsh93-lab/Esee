<?php
/**
 * Plugin Name: Reza Jordaan Commerce
 * Description: Custom shipping methods and Iranian province/city checkout dropdowns for the Reza Jordaan store.
 * Version: 1.1.0
 * Requires at least: 6.4
 * Requires PHP: 8.0
 * Requires Plugins: woocommerce
 * WC requires at least: 8.0
 * WC tested up to: 11.0
 * Author: Esmaeil Shojaei
 * Text Domain: rezajordaan-commerce
 */

defined( 'ABSPATH' ) || exit;

define( 'REZAJORDAAN_COMMERCE_VERSION', '1.1.0' );
define( 'REZAJORDAAN_COMMERCE_OPTION', 'rezajordaan_commerce_shipping_methods' );
define( 'REZAJORDAAN_COMMERCE_PATH', plugin_dir_path( __FILE__ ) );
define( 'REZAJORDAAN_COMMERCE_URL', plugin_dir_url( __FILE__ ) );

require_once REZAJORDAAN_COMMERCE_PATH . 'includes/iran-address.php';

/**
 * Declare compatibility with WooCommerce HPOS and feature flags.
 */
function rezajordaan_commerce_declare_compatibility() {
	if ( ! class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		return;
	}

	\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
		'custom_order_tables',
		__FILE__,
		true
	);
}
add_action( 'before_woocommerce_init', 'rezajordaan_commerce_declare_compatibility' );

/**
 * Return the store's default delivery methods.
 *
 * @return array<string,array<string,mixed>>
 */
function rezajordaan_commerce_default_shipping_methods() {
	return array(
		'post'         => array(
			'enabled'     => 'yes',
			'title'       => 'پست پیشتاز (تحویل درب منزل)',
			'description' => 'هزینه ثابت ارسال به سراسر کشور: ۲۵۰,۰۰۰ تومان',
			'cost'        => '250000',
			'sort_order'  => 10,
		),
		'tipax'        => array(
			'enabled'     => 'yes',
			'title'       => 'تیپاکس (پس‌کرایه)',
			'description' => 'هزینه ارسال بر اساس وزن و مسافت توسط شرکت تیپاکس محاسبه و هنگام تحویل کالا از شما دریافت خواهد شد.',
			'cost'        => '0',
			'sort_order'  => 20,
		),
		'chapar'       => array(
			'enabled'     => 'yes',
			'title'       => 'چاپار (پس‌کرایه)',
			'description' => 'هزینه ارسال بر اساس قوانین چاپار محاسبه و در محل تحویل بار از خریدار دریافت می‌گردد.',
			'cost'        => '0',
			'sort_order'  => 30,
		),
		'courier'      => array(
			'enabled'     => 'yes',
			'title'       => 'ارسال با پیک (هماهنگی تلفنی)',
			'description' => 'هزینه و زمان ارسال با پشتیبانی هماهنگ می‌شود. تماس: ۰۹۰۳۵۲۶۳۳۴۶',
			'cost'        => '0',
			'sort_order'  => 40,
		),
		'local_pickup' => array(
			'enabled'     => 'yes',
			'title'       => 'تحویل حضوری از فروشگاه',
			'description' => 'سفارش را بدون هزینه ارسال از فروشگاه رضا جردن در کرج تحویل بگیرید.',
			'cost'        => '0',
			'sort_order'  => 50,
		),
	);
}

/**
 * Sanitize delivery method settings.
 *
 * @param mixed $submitted Submitted option value.
 * @return array<string,array<string,mixed>>
 */
function rezajordaan_commerce_sanitize_shipping_methods( $submitted ) {
	$defaults  = rezajordaan_commerce_default_shipping_methods();
	$submitted = is_array( $submitted ) ? $submitted : array();
	$clean     = array();

	foreach ( $defaults as $key => $default ) {
		$method = isset( $submitted[ $key ] ) && is_array( $submitted[ $key ] )
			? $submitted[ $key ]
			: array();
		$cost   = isset( $method['cost'] ) ? wc_format_decimal( wp_unslash( $method['cost'] ) ) : $default['cost'];

		$clean[ $key ] = array(
			'enabled'     => ! empty( $method['enabled'] ) ? 'yes' : 'no',
			'title'       => isset( $method['title'] ) ? sanitize_text_field( wp_unslash( $method['title'] ) ) : $default['title'],
			'description' => isset( $method['description'] ) ? sanitize_textarea_field( wp_unslash( $method['description'] ) ) : $default['description'],
			'cost'        => (string) max( 0, (float) $cost ),
			'sort_order'  => isset( $method['sort_order'] ) ? absint( $method['sort_order'] ) : $default['sort_order'],
		);
	}

	if ( class_exists( 'WC_Cache_Helper' ) ) {
		WC_Cache_Helper::get_transient_version( 'shipping', true );
	}

	return $clean;
}

/**
 * Return configured delivery methods in display order.
 *
 * @param bool $enabled_only Return enabled methods only.
 * @return array<string,array<string,mixed>>
 */
function rezajordaan_commerce_get_shipping_methods( $enabled_only = false ) {
	$defaults = rezajordaan_commerce_default_shipping_methods();
	$saved    = get_option( REZAJORDAAN_COMMERCE_OPTION, array() );
	$methods  = array();

	foreach ( $defaults as $key => $default ) {
		$saved_method    = isset( $saved[ $key ] ) && is_array( $saved[ $key ] ) ? $saved[ $key ] : array();
		$methods[ $key ] = wp_parse_args( $saved_method, $default );
	}

	if ( $enabled_only ) {
		$methods = array_filter(
			$methods,
			static function ( $method ) {
				return 'yes' === $method['enabled'];
			}
		);
	}

	uasort(
		$methods,
		static function ( $first, $second ) {
			return (int) $first['sort_order'] <=> (int) $second['sort_order'];
		}
	);

	return $methods;
}

/**
 * Install default settings without overwriting existing configuration.
 */
function rezajordaan_commerce_activate() {
	if ( false === get_option( REZAJORDAAN_COMMERCE_OPTION, false ) ) {
		add_option( REZAJORDAAN_COMMERCE_OPTION, rezajordaan_commerce_default_shipping_methods() );
	}
}
register_activation_hook( __FILE__, 'rezajordaan_commerce_activate' );

/**
 * Display a dependency notice when WooCommerce is unavailable.
 */
function rezajordaan_commerce_woocommerce_notice() {
	if ( class_exists( 'WooCommerce' ) || ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	?>
	<div class="notice notice-error"><p><?php esc_html_e( 'افزونه تجارت رضا جردن برای اجرا به ووکامرس نیاز دارد.', 'rezajordaan-commerce' ); ?></p></div>
	<?php
}
add_action( 'admin_notices', 'rezajordaan_commerce_woocommerce_notice' );

/**
 * Register the settings page and option.
 */
function rezajordaan_commerce_admin_init() {
	register_setting(
		'rezajordaan_commerce_shipping',
		REZAJORDAAN_COMMERCE_OPTION,
		array(
			'type'              => 'array',
			'sanitize_callback' => 'rezajordaan_commerce_sanitize_shipping_methods',
			'default'           => rezajordaan_commerce_default_shipping_methods(),
		)
	);
}
add_action( 'admin_init', 'rezajordaan_commerce_admin_init' );

/**
 * Allow shop managers to save the plugin settings.
 *
 * @return string
 */
function rezajordaan_commerce_settings_capability() {
	return 'manage_woocommerce';
}
add_filter( 'option_page_capability_rezajordaan_commerce_shipping', 'rezajordaan_commerce_settings_capability' );

/**
 * Add the settings page under WooCommerce.
 */
function rezajordaan_commerce_admin_menu() {
	add_submenu_page(
		'woocommerce',
		__( 'ارسال رضا جردن', 'rezajordaan-commerce' ),
		__( 'ارسال رضا جردن', 'rezajordaan-commerce' ),
		'manage_woocommerce',
		'rezajordaan-commerce-shipping',
		'rezajordaan_commerce_render_settings_page'
	);
}
add_action( 'admin_menu', 'rezajordaan_commerce_admin_menu', 30 );

/**
 * Render the shipping settings page.
 */
function rezajordaan_commerce_render_settings_page() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}

	$methods = rezajordaan_commerce_get_shipping_methods();
	?>
	<div class="wrap" dir="rtl">
		<h1><?php esc_html_e( 'روش‌های ارسال رضا جردن', 'rezajordaan-commerce' ); ?></h1>
		<p><?php esc_html_e( 'این روش‌ها جایگزین نرخ‌های ناحیه‌های حمل‌ونقل ووکامرس می‌شوند و برای همه شهرها در تسویه‌حساب نمایش داده خواهند شد.', 'rezajordaan-commerce' ); ?></p>
		<form method="post" action="options.php">
			<?php settings_fields( 'rezajordaan_commerce_shipping' ); ?>
			<table class="widefat striped" style="max-width:1100px">
				<thead>
					<tr>
						<th><?php esc_html_e( 'فعال', 'rezajordaan-commerce' ); ?></th>
						<th><?php esc_html_e( 'عنوان', 'rezajordaan-commerce' ); ?></th>
						<th><?php esc_html_e( 'توضیحات', 'rezajordaan-commerce' ); ?></th>
						<th><?php esc_html_e( 'هزینه (تومان)', 'rezajordaan-commerce' ); ?></th>
						<th><?php esc_html_e( 'ترتیب', 'rezajordaan-commerce' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $methods as $key => $method ) : ?>
						<tr>
							<td>
								<input type="checkbox" name="<?php echo esc_attr( REZAJORDAAN_COMMERCE_OPTION . '[' . $key . '][enabled]' ); ?>" value="1" <?php checked( 'yes', $method['enabled'] ); ?>>
							</td>
							<td>
								<input class="regular-text" type="text" name="<?php echo esc_attr( REZAJORDAAN_COMMERCE_OPTION . '[' . $key . '][title]' ); ?>" value="<?php echo esc_attr( $method['title'] ); ?>" required>
							</td>
							<td>
								<textarea class="large-text" rows="3" name="<?php echo esc_attr( REZAJORDAAN_COMMERCE_OPTION . '[' . $key . '][description]' ); ?>"><?php echo esc_textarea( $method['description'] ); ?></textarea>
							</td>
							<td>
								<input type="number" min="0" step="1" name="<?php echo esc_attr( REZAJORDAAN_COMMERCE_OPTION . '[' . $key . '][cost]' ); ?>" value="<?php echo esc_attr( $method['cost'] ); ?>">
							</td>
							<td>
								<input type="number" min="0" step="1" name="<?php echo esc_attr( REZAJORDAAN_COMMERCE_OPTION . '[' . $key . '][sort_order]' ); ?>" value="<?php echo esc_attr( (string) $method['sort_order'] ); ?>">
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<?php submit_button( __( 'ذخیره روش‌های ارسال', 'rezajordaan-commerce' ) ); ?>
		</form>
	</div>
	<?php
}

/**
 * Guarantee an Iranian destination so rates are available before address entry.
 *
 * @param array<int,array<string,mixed>> $packages Shipping packages.
 * @return array<int,array<string,mixed>>
 */
function rezajordaan_commerce_prepare_shipping_packages( $packages ) {
	foreach ( $packages as &$package ) {
		if ( empty( $package['destination']['country'] ) ) {
			$package['destination']['country'] = 'IR';
		}
	}
	unset( $package );

	return $packages;
}
add_filter( 'woocommerce_cart_shipping_packages', 'rezajordaan_commerce_prepare_shipping_packages', 999 );

/**
 * Define the always-available WooCommerce shipping method.
 */
function rezajordaan_commerce_shipping_method_init() {
	if ( ! class_exists( 'WC_Shipping_Method' ) || class_exists( 'WC_RezaJordaan_Commerce_Shipping' ) ) {
		return;
	}

	/**
	 * A legacy/global method is intentional: rates must not depend on zones.
	 */
	class WC_RezaJordaan_Commerce_Shipping extends WC_Shipping_Method {
		/**
		 * Set method identity without exposing duplicate WooCommerce settings.
		 */
		public function __construct() {
			$this->id                 = 'rezajordaan_commerce';
			$this->method_title       = 'ارسال رضا جردن';
			$this->method_description = 'روش‌های ارسال از منوی ووکامرس ← ارسال رضا جردن مدیریت می‌شوند.';
			$this->title              = $this->method_title;
			$this->enabled            = 'yes';
			$this->supports           = array();
		}

		/**
		 * Add every enabled method for every destination.
		 *
		 * @param array<string,mixed> $package Shipping package.
		 */
		public function calculate_shipping( $package = array() ) {
			unset( $package );

			foreach ( rezajordaan_commerce_get_shipping_methods( true ) as $key => $method ) {
				$this->add_rate(
					array(
						'id'        => $this->id . '_' . sanitize_key( $key ),
						'label'     => $method['title'],
						'cost'      => (float) $method['cost'],
						'taxes'     => false,
						'calc_tax'  => 'per_order',
						'meta_data' => array(
							'rezajordaan_method_key' => $key,
						),
					)
				);
			}
		}
	}
}
add_action( 'woocommerce_shipping_init', 'rezajordaan_commerce_shipping_method_init' );

/**
 * Register the global method with WooCommerce.
 *
 * @param array<string,string> $methods Shipping method classes.
 * @return array<string,string>
 */
function rezajordaan_commerce_register_shipping_method( $methods ) {
	$methods['rezajordaan_commerce'] = 'WC_RezaJordaan_Commerce_Shipping';
	return $methods;
}
add_filter( 'woocommerce_shipping_methods', 'rezajordaan_commerce_register_shipping_method', 999 );

/**
 * Replace zone/plugin rates with the store's configured delivery methods.
 *
 * Rates are injected here so they do not depend on WooCommerce shipping zones.
 *
 * @param array<string,WC_Shipping_Rate> $rates   Existing rates.
 * @param array<string,mixed>            $package Shipping package.
 * @return array<string,WC_Shipping_Rate>
 */
function rezajordaan_commerce_package_shipping_rates( $rates, $package ) {
	unset( $rates, $package );

	$custom_rates = array();

	foreach ( rezajordaan_commerce_get_shipping_methods( true ) as $key => $method ) {
		$rate_id = 'rezajordaan_commerce_' . sanitize_key( $key );

		$custom_rates[ $rate_id ] = new WC_Shipping_Rate(
			$rate_id,
			$method['title'],
			(float) $method['cost'],
			array(),
			'rezajordaan_commerce',
			0,
			array(
				'rezajordaan_method_key' => $key,
			)
		);
	}

	return $custom_rates;
}
add_filter( 'woocommerce_package_rates', 'rezajordaan_commerce_package_shipping_rates', 9999, 2 );

/**
 * Calculate shipping on checkout even before the customer finishes the address.
 *
 * @param bool $ready Whether shipping can be calculated.
 * @return bool
 */
function rezajordaan_commerce_ready_to_calc_shipping( $ready ) {
	if ( function_exists( 'is_cart' ) && is_cart() && ! is_checkout() ) {
		return false;
	}

	if ( function_exists( 'is_checkout' ) && is_checkout() && ! is_wc_endpoint_url( 'order-received' ) ) {
		return true;
	}

	return $ready;
}
add_filter( 'woocommerce_cart_ready_to_calc_shipping', 'rezajordaan_commerce_ready_to_calc_shipping', 999 );

/**
 * Keep shipping rates stable when address fields trigger update_checkout.
 *
 * @param string[]           $fields  Ignored hash fields.
 * @param array<string,mixed> $package Shipping package.
 * @return string[]
 */
function rezajordaan_commerce_ignore_destination_in_package_hash( $fields, $package ) {
	unset( $package );
	$fields[] = 'destination';
	return array_values( array_unique( $fields ) );
}
add_filter( 'woocommerce_shipping_package_hash_ignored_fields', 'rezajordaan_commerce_ignore_destination_in_package_hash', 10, 2 );

/**
 * Default to the first enabled delivery method when nothing is selected.
 *
 * @param string              $default Default method ID.
 * @param array<string,mixed> $package Shipping package.
 * @param string              $chosen  Currently chosen method ID.
 * @return string
 */
function rezajordaan_commerce_default_chosen_shipping_method( $default, $package, $chosen ) {
	unset( $package );

	if ( '' !== $chosen ) {
		return $chosen;
	}

	foreach ( rezajordaan_commerce_get_shipping_methods( true ) as $key => $method ) {
		unset( $method );
		return 'rezajordaan_commerce_' . sanitize_key( $key );
	}

	return $default;
}
add_filter( 'woocommerce_shipping_chosen_method', 'rezajordaan_commerce_default_chosen_shipping_method', 10, 3 );

/**
 * Show each delivery method's explanation below its title.
 *
 * @param WC_Shipping_Rate $method Shipping rate.
 */
function rezajordaan_commerce_shipping_rate_description( $method ) {
	if ( ! $method instanceof WC_Shipping_Rate || 'rezajordaan_commerce' !== $method->get_method_id() ) {
		return;
	}

	$meta    = $method->get_meta_data();
	$key     = isset( $meta['rezajordaan_method_key'] ) ? (string) $meta['rezajordaan_method_key'] : '';
	$methods = rezajordaan_commerce_get_shipping_methods();

	if ( empty( $methods[ $key ]['description'] ) ) {
		return;
	}

	echo '<span class="rj-shipping-method__description">' . esc_html( $methods[ $key ]['description'] ) . '</span>';
}
add_action( 'woocommerce_after_shipping_rate', 'rezajordaan_commerce_shipping_rate_description' );

/**
 * Hide shipping calculations on the cart; checkout owns method selection.
 *
 * @param bool $needs_shipping Whether the cart needs shipping.
 * @return bool
 */
function rezajordaan_commerce_hide_cart_shipping( $needs_shipping ) {
	if ( function_exists( 'is_cart' ) && is_cart() && ! is_checkout() ) {
		return false;
	}

	return $needs_shipping;
}
add_filter( 'woocommerce_cart_needs_shipping', 'rezajordaan_commerce_hide_cart_shipping', 999 );
add_filter( 'woocommerce_shipping_calculator_enable', '__return_false' );

/**
 * Customize classic checkout fields.
 *
 * @param array<string,array<string,array<string,mixed>>> $fields Checkout fields.
 * @return array<string,array<string,array<string,mixed>>>
 */
function rezajordaan_commerce_checkout_fields( $fields ) {
	$billing = isset( $fields['billing'] ) ? $fields['billing'] : array();

	unset( $billing['billing_company'], $billing['billing_address_2'] );

	$billing['billing_first_name'] = array(
		'type'         => 'text',
		'label'        => 'نام',
		'required'     => true,
		'class'        => array( 'form-row-first' ),
		'autocomplete' => 'given-name',
		'priority'     => 10,
	);
	$billing['billing_last_name']  = array(
		'type'         => 'text',
		'label'        => 'نام خانوادگی',
		'required'     => true,
		'class'        => array( 'form-row-last' ),
		'autocomplete' => 'family-name',
		'priority'     => 20,
	);
	$billing['billing_phone']      = array(
		'type'         => 'tel',
		'label'        => 'شماره موبایل',
		'placeholder'  => 'مثلاً ۰۹۱۲۱۲۳۴۵۶۷',
		'required'     => true,
		'class'        => array( 'form-row-wide' ),
		'autocomplete' => 'tel',
		'priority'     => 30,
	);
	$current_state                 = rezajordaan_commerce_checkout_field_value( 'billing_state' );
	$current_city                  = rezajordaan_commerce_checkout_field_value( 'billing_city' );
	$billing['billing_state']      = rezajordaan_commerce_state_field_args( $current_state );
	$billing['billing_city']       = rezajordaan_commerce_city_field_args( $current_state, $current_city );
	$billing['billing_address_1']  = array(
		'type'              => 'textarea',
		'label'             => 'نشانی کامل',
		'placeholder'       => 'خیابان، کوچه، پلاک، واحد و توضیحات لازم برای تحویل',
		'required'          => true,
		'class'             => array( 'form-row-wide', 'rj-checkout-address' ),
		'autocomplete'      => 'street-address',
		'priority'          => 60,
		'custom_attributes' => array(
			'rows' => '5',
		),
	);
	$billing['billing_postcode']   = array(
		'type'         => 'text',
		'label'        => 'کدپستی (اختیاری)',
		'required'     => false,
		'class'        => array( 'form-row-first' ),
		'autocomplete' => 'postal-code',
		'priority'     => 70,
	);
	$billing['billing_email']      = array(
		'type'         => 'email',
		'label'        => 'ایمیل (اختیاری)',
		'required'     => false,
		'class'        => array( 'form-row-last' ),
		'autocomplete' => 'email',
		'priority'     => 80,
	);
	$billing['billing_country']    = array(
		'type'     => 'hidden',
		'required' => false,
		'default'  => 'IR',
		'priority' => 5,
	);

	$fields['billing'] = $billing;
	$fields['shipping'] = array();
	$fields['order']['order_comments'] = array(
		'type'        => 'textarea',
		'label'       => 'یادداشت سفارش (اختیاری)',
		'placeholder' => 'اگر درباره تحویل سفارش توضیحی دارید، اینجا بنویسید.',
		'required'    => false,
		'class'       => array( 'form-row-wide' ),
		'priority'    => 10,
	);

	return $fields;
}
add_filter( 'woocommerce_checkout_fields', 'rezajordaan_commerce_checkout_fields', 9999 );

/**
 * Normalize the hidden country and shipping address before validation.
 *
 * @param array<string,mixed> $data Posted checkout data.
 * @return array<string,mixed>
 */
function rezajordaan_commerce_checkout_posted_data( $data ) {
	$data['billing_country']  = 'IR';
	$data['shipping_country'] = 'IR';

	if ( isset( $data['billing_state'] ) ) {
		$data['billing_state'] = rezajordaan_commerce_normalize_state_code( (string) $data['billing_state'] );
	}

	foreach ( array( 'first_name', 'last_name', 'state', 'city', 'address_1', 'postcode' ) as $field ) {
		$billing_key  = 'billing_' . $field;
		$shipping_key = 'shipping_' . $field;

		if ( isset( $data[ $billing_key ] ) ) {
			$data[ $shipping_key ] = $data[ $billing_key ];
		}
	}

	return $data;
}
add_filter( 'woocommerce_checkout_posted_data', 'rezajordaan_commerce_checkout_posted_data', 999 );

/**
 * Keep Iranian provinces as a WooCommerce state select with Persian labels.
 *
 * @param array<string,array<string,string>> $states Country states.
 * @return array<string,array<string,string>>
 */
function rezajordaan_commerce_woocommerce_states( $states ) {
	$states['IR'] = rezajordaan_commerce_persian_states();
	return $states;
}
add_filter( 'woocommerce_states', 'rezajordaan_commerce_woocommerce_states', 99999 );

/**
 * Force Iran to use required province/city fields in WooCommerce locale data.
 *
 * @param array<string,array<string,array<string,mixed>>> $locale Country locale fields.
 * @return array<string,array<string,array<string,mixed>>>
 */
function rezajordaan_commerce_country_locale( $locale ) {
	if ( ! isset( $locale['IR'] ) || ! is_array( $locale['IR'] ) ) {
		$locale['IR'] = array();
	}

	$locale['IR']['state']             = isset( $locale['IR']['state'] ) && is_array( $locale['IR']['state'] ) ? $locale['IR']['state'] : array();
	$locale['IR']['city']              = isset( $locale['IR']['city'] ) && is_array( $locale['IR']['city'] ) ? $locale['IR']['city'] : array();
	$locale['IR']['state']['required'] = true;
	$locale['IR']['state']['hidden']   = false;
	$locale['IR']['city']['required']  = true;
	$locale['IR']['city']['hidden']    = false;

	return $locale;
}
add_filter( 'woocommerce_get_country_locale', 'rezajordaan_commerce_country_locale', 9999 );

/**
 * Apply the same select fields on My Account address forms.
 *
 * @param array<string,array<string,mixed>> $fields Address fields.
 * @return array<string,array<string,mixed>>
 */
function rezajordaan_commerce_billing_fields( $fields ) {
	$current_state           = rezajordaan_commerce_checkout_field_value( 'billing_state' );
	$current_city            = rezajordaan_commerce_checkout_field_value( 'billing_city' );
	$fields['billing_state'] = rezajordaan_commerce_state_field_args( $current_state );
	$fields['billing_city']  = rezajordaan_commerce_city_field_args( $current_state, $current_city );
	return $fields;
}
add_filter( 'woocommerce_billing_fields', 'rezajordaan_commerce_billing_fields', 9999 );

/**
 * Read a checkout/address value from the request, then WooCommerce, then the user.
 *
 * @param string $key Field key.
 * @return string
 */
function rezajordaan_commerce_checkout_field_value( $key ) {
	if ( isset( $_POST[ $key ] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
	}

	if ( function_exists( 'WC' ) && WC() && WC()->checkout() ) {
		$value = WC()->checkout()->get_value( $key );
		if ( null !== $value && '' !== $value ) {
			return (string) $value;
		}
	}

	return '';
}

/**
 * Load province/city dropdown assets on checkout and account address pages.
 */
function rezajordaan_commerce_enqueue_checkout_address() {
	$load = ( function_exists( 'is_checkout' ) && is_checkout() )
		|| ( function_exists( 'is_account_page' ) && is_account_page() && function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( 'edit-address' ) );

	if ( ! $load ) {
		return;
	}

	wp_enqueue_style(
		'rezajordaan-commerce-checkout-address',
		REZAJORDAAN_COMMERCE_URL . 'assets/css/checkout-address.css',
		array(),
		REZAJORDAAN_COMMERCE_VERSION
	);

	wp_enqueue_script(
		'rezajordaan-commerce-checkout-address',
		REZAJORDAAN_COMMERCE_URL . 'assets/js/checkout-address.js',
		array( 'jquery' ),
		REZAJORDAAN_COMMERCE_VERSION,
		true
	);

	wp_localize_script(
		'rezajordaan-commerce-checkout-address',
		'rjCommerceAddress',
		array(
			'cities'  => rezajordaan_commerce_cities_map(),
			'aliases' => rezajordaan_commerce_state_aliases(),
			'i18n'    => array(
				'chooseProvince' => 'ابتدا استان را انتخاب کنید',
				'chooseCity'     => 'شهر را انتخاب کنید',
			),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'rezajordaan_commerce_enqueue_checkout_address', 30 );

/**
 * Keep checkout guest-friendly and use one delivery address.
 */
add_filter( 'woocommerce_checkout_registration_required', '__return_false' );
add_filter( 'woocommerce_checkout_registration_enabled', '__return_false' );
add_filter( 'woocommerce_cart_needs_shipping_address', '__return_false' );
add_filter( 'default_checkout_billing_country', static fn() => 'IR' );
add_filter( 'default_checkout_shipping_country', static fn() => 'IR' );
add_filter( 'woocommerce_checkout_billing_heading', '__return_empty_string' );
add_filter( 'woocommerce_checkout_shipping_heading', '__return_empty_string' );
add_filter( 'woocommerce_checkout_additional_fields_heading', '__return_empty_string' );

/**
 * Prevent Persian WooCommerce Shipping from replacing checkout fields.
 */
function rezajordaan_commerce_disable_pws_checkout_fields() {
	if ( ! function_exists( 'is_checkout' ) || ! is_checkout() ) {
		return;
	}

	if ( class_exists( 'PWS_Checkout' ) && is_callable( array( 'PWS_Checkout', 'instance' ) ) ) {
		$checkout = PWS_Checkout::instance();

		remove_action( 'woocommerce_checkout_fields', array( $checkout, 'checkout_fields' ), 20 );
		remove_action( 'woocommerce_checkout_fields', array( $checkout, 'checkout_fields' ), 10 );
	}
}
add_action( 'wp', 'rezajordaan_commerce_disable_pws_checkout_fields', 5 );

/**
 * Validate Iranian mobile numbers and optional email at checkout.
 */
function rezajordaan_commerce_validate_checkout_fields() {
	$phone = isset( $_POST['billing_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_phone'] ) ) : '';
	$phone = preg_replace( '/\D+/', '', $phone );

	if ( '' === $phone || ! preg_match( '/^09[0-9]{9}$/', $phone ) ) {
		wc_add_notice( 'لطفاً شماره موبایل معتبر ایرانی وارد کنید (مثلاً ۰۹۱۲۱۲۳۴۵۶۷).', 'error' );
	}

	$email = isset( $_POST['billing_email'] ) ? sanitize_email( wp_unslash( $_POST['billing_email'] ) ) : '';

	if ( '' !== $email && ! is_email( $email ) ) {
		wc_add_notice( 'ایمیل وارد شده معتبر نیست.', 'error' );
	}

	$state = isset( $_POST['billing_state'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_state'] ) ) : '';
	$city  = isset( $_POST['billing_city'] ) ? sanitize_text_field( wp_unslash( $_POST['billing_city'] ) ) : '';

	if ( ! rezajordaan_commerce_is_valid_state( $state ) ) {
		wc_add_notice( 'لطفاً استان را از فهرست انتخاب کنید.', 'error' );
	} elseif ( ! rezajordaan_commerce_is_valid_city( $state, $city ) ) {
		wc_add_notice( 'لطفاً شهر را از فهرست استان انتخاب‌شده برگزینید.', 'error' );
	}
}
add_action( 'woocommerce_checkout_process', 'rezajordaan_commerce_validate_checkout_fields' );

