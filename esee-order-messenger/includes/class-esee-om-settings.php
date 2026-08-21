<?php
/**
 * Plugin settings (API keys and message templates).
 *
 * @package Esee_Order_Messenger
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Esee_OM_Settings {

	const OPTION = 'esee_om_settings';

	public static function defaults() {
		return array(
			'enabled'             => '1',
			'require_opt_in'      => '1',
			'whatsapp_enabled'    => '1',
			'whatsapp_token'      => '',
			'whatsapp_phone_id'   => '',
			'whatsapp_template_new' => '',
			'whatsapp_template_done' => '',
			'whatsapp_lang'       => 'fa',
			'bale_enabled'        => '1',
			'bale_token'          => '',
			'bale_username'       => '',
			'bale_safir_token'    => '',
			'bale_safir_bot_id'   => '',
			'rubika_enabled'      => '1',
			'rubika_token'        => '',
			'rubika_username'     => '',
			'webhook_secret'      => '',
			'template_new'        => "سلام {first_name} عزیز\nسفارش {order_id} ثبت شد.\nمبلغ: {total}\nاقلام:\n{items}",
			'template_done'       => "سلام {first_name} عزیز\nسفارش {order_id} تکمیل شد.\n{custom_note}",
			'custom_note'         => 'از خرید شما سپاسگزاریم.',
		);
	}

	public static function get() {
		$saved = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		return wp_parse_args( $saved, self::defaults() );
	}

	public static function get_field( $key, $default = '' ) {
		$settings = self::get();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	public function hooks() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_init', array( $this, 'register' ) );
	}

	public function menu() {
		add_submenu_page(
			'woocommerce',
			'پیام‌رسان سفارش',
			'پیام‌رسان سفارش',
			'manage_woocommerce',
			'esee-order-messenger',
			array( $this, 'render' )
		);
	}

	public function register() {
		register_setting(
			'esee_om_group',
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize' ),
			)
		);
	}

	public function sanitize( $input ) {
		$current = self::get();
		$input   = is_array( $input ) ? $input : array();
		$out     = $current;

		$checkboxes = array(
			'enabled',
			'require_opt_in',
			'whatsapp_enabled',
			'bale_enabled',
			'rubika_enabled',
		);
		foreach ( $checkboxes as $key ) {
			$out[ $key ] = empty( $input[ $key ] ) ? '0' : '1';
		}

		$text_fields = array(
			'whatsapp_token',
			'whatsapp_phone_id',
			'whatsapp_template_new',
			'whatsapp_template_done',
			'whatsapp_lang',
			'bale_token',
			'bale_username',
			'bale_safir_token',
			'bale_safir_bot_id',
			'rubika_token',
			'rubika_username',
			'webhook_secret',
			'template_new',
			'template_done',
			'custom_note',
		);
		foreach ( $text_fields as $key ) {
			if ( ! array_key_exists( $key, $input ) ) {
				continue;
			}
			$value = (string) $input[ $key ];
			if ( in_array( $key, array( 'template_new', 'template_done', 'custom_note' ), true ) ) {
				$out[ $key ] = sanitize_textarea_field( $value );
			} else {
				$out[ $key ] = sanitize_text_field( $value );
			}
		}

		if ( '' === $out['webhook_secret'] ) {
			$out['webhook_secret'] = wp_generate_password( 20, false, false );
		}

		return $out;
	}

	public function render() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		$s      = self::get();
		$secret = rawurlencode( $s['webhook_secret'] );
		$bale   = rest_url( 'esee-om/v1/bale?secret=' . $secret );
		$rubika = rest_url( 'esee-om/v1/rubika?secret=' . $secret );
		?>
		<div class="wrap">
			<h1>پیام‌رسان سفارش (واتساپ / روبیکا / بله)</h1>
			<p>این افزونه شماره مشتری را <strong>اسکن نمی‌کند</strong> تا ببیند واتساپ یا روبیکا دارد یا نه؛ آن کار فقط با روش‌های غیررسمی و سرور همیشه روشن ممکن است. به‌جایش مشتری در تسویه‌حساب تیک می‌زند، و ارسال با یک درخواست HTTP کوتاه به API رسمی انجام می‌شود (بدون VPS و بدون فشار روی هاست).</p>
			<form method="post" action="options.php">
				<?php settings_fields( 'esee_om_group' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th>فعال‌سازی</th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[enabled]" value="1" <?php checked( $s['enabled'], '1' ); ?>> افزونه فعال باشد</label><br>
							<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[require_opt_in]" value="1" <?php checked( $s['require_opt_in'], '1' ); ?>> فقط اگر مشتری تیک زده باشد پیام برود</label>
						</td>
					</tr>
					<tr>
						<th>واتساپ Cloud API</th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[whatsapp_enabled]" value="1" <?php checked( $s['whatsapp_enabled'], '1' ); ?>> ارسال واتساپ</label><br>
							<input class="regular-text" type="password" name="<?php echo esc_attr( self::OPTION ); ?>[whatsapp_token]" value="<?php echo esc_attr( $s['whatsapp_token'] ); ?>" placeholder="Access Token"><br>
							<input class="regular-text" type="text" name="<?php echo esc_attr( self::OPTION ); ?>[whatsapp_phone_id]" value="<?php echo esc_attr( $s['whatsapp_phone_id'] ); ?>" placeholder="Phone Number ID"><br>
							<input class="regular-text" type="text" name="<?php echo esc_attr( self::OPTION ); ?>[whatsapp_template_new]" value="<?php echo esc_attr( $s['whatsapp_template_new'] ); ?>" placeholder="نام قالب ثبت سفارش"><br>
							<input class="regular-text" type="text" name="<?php echo esc_attr( self::OPTION ); ?>[whatsapp_template_done]" value="<?php echo esc_attr( $s['whatsapp_template_done'] ); ?>" placeholder="نام قالب تکمیل"><br>
							<input class="small-text" type="text" name="<?php echo esc_attr( self::OPTION ); ?>[whatsapp_lang]" value="<?php echo esc_attr( $s['whatsapp_lang'] ); ?>" placeholder="fa">
							<p class="description">بدون قالب تأییدشده متا، پیام اول معمولاً ارسال نمی‌شود. اگر شماره روی واتساپ نباشد، API خطا می‌دهد و تیک واتساپ روی سفارش برداشته می‌شود.</p>
						</td>
					</tr>
					<tr>
						<th>بله</th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[bale_enabled]" value="1" <?php checked( $s['bale_enabled'], '1' ); ?>> ارسال بله</label><br>
							<input class="regular-text" type="password" name="<?php echo esc_attr( self::OPTION ); ?>[bale_token]" value="<?php echo esc_attr( $s['bale_token'] ); ?>" placeholder="توکن بازو"><br>
							<input class="regular-text" type="text" name="<?php echo esc_attr( self::OPTION ); ?>[bale_username]" value="<?php echo esc_attr( $s['bale_username'] ); ?>" placeholder="نام کاربری بازو بدون @"><br>
							<input class="regular-text" type="password" name="<?php echo esc_attr( self::OPTION ); ?>[bale_safir_token]" value="<?php echo esc_attr( $s['bale_safir_token'] ); ?>" placeholder="توکن سفیر (اختیاری، ارسال با شماره)"><br>
							<input class="regular-text" type="text" name="<?php echo esc_attr( self::OPTION ); ?>[bale_safir_bot_id]" value="<?php echo esc_attr( $s['bale_safir_bot_id'] ); ?>" placeholder="bot_id سفیر">
							<p class="description">وب‌هوک بله: <code><?php echo esc_html( $bale ); ?></code></p>
						</td>
					</tr>
					<tr>
						<th>روبیکا</th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION ); ?>[rubika_enabled]" value="1" <?php checked( $s['rubika_enabled'], '1' ); ?>> ارسال روبیکا</label><br>
							<input class="regular-text" type="password" name="<?php echo esc_attr( self::OPTION ); ?>[rubika_token]" value="<?php echo esc_attr( $s['rubika_token'] ); ?>" placeholder="توکن بات"><br>
							<input class="regular-text" type="text" name="<?php echo esc_attr( self::OPTION ); ?>[rubika_username]" value="<?php echo esc_attr( $s['rubika_username'] ); ?>" placeholder="آیدی بات">
							<p class="description">روبیکا با شماره موبایل پیام نمی‌فرستد؛ مشتری باید بات را استارت کند. وب‌هوک: <code><?php echo esc_html( $rubika ); ?></code></p>
						</td>
					</tr>
					<tr>
						<th>متن پیام‌ها</th>
						<td>
							<p>متغیرها: <code>{order_id}</code> <code>{first_name}</code> <code>{last_name}</code> <code>{phone}</code> <code>{total}</code> <code>{status}</code> <code>{items}</code> <code>{custom_note}</code></p>
							<p>ثبت سفارش</p>
							<textarea class="large-text" rows="5" name="<?php echo esc_attr( self::OPTION ); ?>[template_new]"><?php echo esc_textarea( $s['template_new'] ); ?></textarea>
							<p>تکمیل سفارش</p>
							<textarea class="large-text" rows="5" name="<?php echo esc_attr( self::OPTION ); ?>[template_done]"><?php echo esc_textarea( $s['template_done'] ); ?></textarea>
							<p>یادداشت شخصی‌سازی</p>
							<input class="large-text" type="text" name="<?php echo esc_attr( self::OPTION ); ?>[custom_note]" value="<?php echo esc_attr( $s['custom_note'] ); ?>">
							<p>رمز وب‌هوک</p>
							<input class="regular-text" type="text" name="<?php echo esc_attr( self::OPTION ); ?>[webhook_secret]" value="<?php echo esc_attr( $s['webhook_secret'] ); ?>">
						</td>
					</tr>
				</table>
				<?php submit_button( 'ذخیره تنظیمات' ); ?>
			</form>
		</div>
		<?php
	}
}
