<?php
/**
 * تشخیص منبع ارجاع بازدیدکننده بر اساس پارامتر UTM یا آدرس ریفرر.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CVS_Source_Detector {

	/**
	 * نگاشت بخشی از دامنه‌ی ریفرر به کلید و برچسب فارسی منبع.
	 * ترتیب اهمیت دارد؛ اولین تطابق در نظر گرفته می‌شود.
	 */
	private static function get_domain_map() {
		return array(
			'instagram.'  => array( 'instagram', 'اینستاگرام' ),
			'l.instagram' => array( 'instagram', 'اینستاگرام' ),
			'twitter.'    => array( 'x', 'ایکس (توییتر)' ),
			'x.com'       => array( 'x', 'ایکس (توییتر)' ),
			't.co'        => array( 'x', 'ایکس (توییتر)' ),
			'facebook.'   => array( 'facebook', 'فیسبوک' ),
			'fb.watch'    => array( 'facebook', 'فیسبوک' ),
			'm.facebook'  => array( 'facebook', 'فیسبوک' ),
			'l.facebook'  => array( 'facebook', 'فیسبوک' ),
			't.me'        => array( 'telegram', 'تلگرام' ),
			'telegram.'   => array( 'telegram', 'تلگرام' ),
			'wa.me'       => array( 'whatsapp', 'واتساپ' ),
			'whatsapp.'   => array( 'whatsapp', 'واتساپ' ),
			'api.whatsapp'=> array( 'whatsapp', 'واتساپ' ),
			'eitaa.com'   => array( 'eitaa', 'ایتا' ),
			'bale.ai'     => array( 'bale', 'بله' ),
			'rubika.ir'   => array( 'rubika', 'روبیکا' ),
			'aparat.com'  => array( 'aparat', 'آپارات' ),
			'linkedin.'   => array( 'linkedin', 'لینکدین' ),
			'pinterest.'  => array( 'pinterest', 'پینترست' ),
			'youtube.'    => array( 'youtube', 'یوتیوب' ),
			'youtu.be'    => array( 'youtube', 'یوتیوب' ),
			'bing.'       => array( 'bing', 'بینگ' ),
			'yahoo.'      => array( 'yahoo', 'یاهو' ),
			'yandex.'     => array( 'yandex', 'یاندکس' ),
			'duckduckgo.' => array( 'duckduckgo', 'داک‌داک‌گو' ),
			'google.'     => array( 'google', 'گوگل' ),
		);
	}

	/**
	 * نگاشت مقادیر رایج utm_source به کلید و برچسب فارسی.
	 */
	private static function get_utm_map() {
		return array(
			'google'    => array( 'google', 'گوگل' ),
			'instagram' => array( 'instagram', 'اینستاگرام' ),
			'ig'        => array( 'instagram', 'اینستاگرام' ),
			'facebook'  => array( 'facebook', 'فیسبوک' ),
			'fb'        => array( 'facebook', 'فیسبوک' ),
			'twitter'   => array( 'x', 'ایکس (توییتر)' ),
			'x'         => array( 'x', 'ایکس (توییتر)' ),
			'telegram'  => array( 'telegram', 'تلگرام' ),
			'whatsapp'  => array( 'whatsapp', 'واتساپ' ),
			'eitaa'     => array( 'eitaa', 'ایتا' ),
			'bale'      => array( 'bale', 'بله' ),
			'rubika'    => array( 'rubika', 'روبیکا' ),
			'aparat'    => array( 'aparat', 'آپارات' ),
			'linkedin'  => array( 'linkedin', 'لینکدین' ),
			'youtube'   => array( 'youtube', 'یوتیوب' ),
			'sms'       => array( 'sms', 'پیامک' ),
			'email'     => array( 'email', 'ایمیل' ),
			'newsletter'=> array( 'email', 'ایمیل' ),
			'ads'       => array( 'ads', 'تبلیغات' ),
			'cpc'       => array( 'ads', 'تبلیغات' ),
		);
	}

	/**
	 * تشخیص منبع بازدید.
	 *
	 * @param string $referrer آدرس کامل ریفرر (می‌تواند خالی باشد).
	 * @param array  $query_params پارامترهای GET درخواست فعلی.
	 * @param string $home_host دامنه‌ی سایت جاری (برای شناسایی ارجاع داخلی).
	 * @return array { source_key, source_label, referrer_host }
	 */
	public static function detect( $referrer, $query_params, $home_host ) {
		if ( ! empty( $query_params['utm_source'] ) ) {
			$utm_raw = strtolower( trim( sanitize_text_field( $query_params['utm_source'] ) ) );
			$utm_map = self::get_utm_map();

			if ( isset( $utm_map[ $utm_raw ] ) ) {
				return array(
					'source_key'    => $utm_map[ $utm_raw ][0],
					'source_label'  => $utm_map[ $utm_raw ][1],
					'referrer_host' => self::extract_host( $referrer ),
				);
			}

			$custom_key = preg_replace( '/[^a-z0-9_\-]/', '_', $utm_raw );
			return array(
				'source_key'    => 'utm_' . $custom_key,
				'source_label'  => sanitize_text_field( $query_params['utm_source'] ),
				'referrer_host' => self::extract_host( $referrer ),
			);
		}

		$host = self::extract_host( $referrer );

		if ( empty( $host ) ) {
			return array(
				'source_key'    => 'direct',
				'source_label'  => 'مستقیم',
				'referrer_host' => null,
			);
		}

		if ( $home_host && self::hosts_match( $host, $home_host ) ) {
			return array(
				'source_key'    => 'direct',
				'source_label'  => 'مستقیم',
				'referrer_host' => $host,
			);
		}

		foreach ( self::get_domain_map() as $needle => $info ) {
			if ( false !== strpos( $host, $needle ) ) {
				return array(
					'source_key'    => $info[0],
					'source_label'  => $info[1],
					'referrer_host' => $host,
				);
			}
		}

		return array(
			'source_key'    => 'other',
			'source_label'  => 'سایر (' . $host . ')',
			'referrer_host' => $host,
		);
	}

	/**
	 * استخراج دامنه از یک آدرس کامل.
	 */
	private static function extract_host( $url ) {
		if ( empty( $url ) ) {
			return '';
		}
		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) ) {
			return '';
		}
		return strtolower( preg_replace( '/^www\./', '', $parts['host'] ) );
	}

	/**
	 * مقایسه دو دامنه با نادیده گرفتن پیشوند www.
	 */
	private static function hosts_match( $host_a, $host_b ) {
		$normalize = function ( $h ) {
			return strtolower( preg_replace( '/^www\./', '', $h ) );
		};
		return $normalize( $host_a ) === $normalize( $host_b );
	}
}
