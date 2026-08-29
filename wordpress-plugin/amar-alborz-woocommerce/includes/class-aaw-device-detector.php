<?php
/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

/**
 * تشخیص نوع دستگاه، مرورگر و سیستم‌عامل بازدیدکننده بر اساس رشته‌ی User-Agent واقعی.
 * این تشخیص کاملاً بر اساس الگوهای شناخته‌شده‌ی رشته‌ی UA است (نه حدس بر اساس رفتار کاربر)
 * و همان اطلاعاتی است که مرورگر خودِ کاربر برای هر درخواست ارسال می‌کند.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAW_Device_Detector {

	/**
	 * تشخیص نوع دستگاه: mobile, tablet یا desktop.
	 */
	public static function detect_device_type( $user_agent ) {
		$ua = strtolower( (string) $user_agent );

		if ( '' === $ua ) {
			return 'desktop';
		}

		if ( preg_match( '/ipad|tablet|kindle|playbook|silk|nexus 7|nexus 9|nexus 10/', $ua ) ) {
			return 'tablet';
		}

		if ( preg_match( '/mobile|iphone|ipod|android(?!.*tablet)|blackberry|windows phone|opera mini|iemobile/', $ua ) ) {
			return 'mobile';
		}

		return 'desktop';
	}

	/**
	 * تشخیص نام مرورگر از روی رشته‌ی UA؛ ترتیب بررسی برای دقت بیشتر اهمیت دارد
	 * (مثلاً کروم‌بیس‌ها قبل از سافاری، و اج قبل از کروم بررسی می‌شوند).
	 */
	public static function detect_browser( $user_agent ) {
		$ua = strtolower( (string) $user_agent );

		if ( '' === $ua ) {
			return 'نامشخص';
		}

		$map = array(
			'edg/'          => 'مایکروسافت اج',
			'edga/'         => 'مایکروسافت اج',
			'edgios/'       => 'مایکروسافت اج',
			'opr/'          => 'اپرا',
			'opera'         => 'اپرا',
			'ucbrowser'     => 'یوسی مرورگر',
			'samsungbrowser'=> 'سامسونگ اینترنت',
			'miuibrowser'   => 'مرورگر MIUI',
			'yabrowser'     => 'یاندکس مرورگر',
			'firefox'       => 'فایرفاکس',
			'fxios'         => 'فایرفاکس',
			'crios'         => 'کروم',
			'chrome'        => 'کروم',
			'safari'        => 'سافاری',
			'msie'          => 'اینترنت‌اکسپلورر',
			'trident'       => 'اینترنت‌اکسپلورر',
		);

		foreach ( $map as $needle => $label ) {
			if ( false !== strpos( $ua, $needle ) ) {
				return $label;
			}
		}

		return 'سایر مرورگرها';
	}

	/**
	 * تشخیص سیستم‌عامل از روی رشته‌ی UA.
	 */
	public static function detect_os( $user_agent ) {
		$ua = strtolower( (string) $user_agent );

		if ( '' === $ua ) {
			return 'نامشخص';
		}

		$map = array(
			'windows phone' => 'ویندوز فون',
			'windows'       => 'ویندوز',
			'android'       => 'اندروید',
			'iphone'        => 'iOS',
			'ipad'          => 'iPadOS',
			'ipod'          => 'iOS',
			'mac os'        => 'مک‌اواس',
			'macintosh'     => 'مک‌اواس',
			'linux'         => 'لینوکس',
			'cros'          => 'ChromeOS',
			'ubuntu'        => 'لینوکس',
		);

		foreach ( $map as $needle => $label ) {
			if ( false !== strpos( $ua, $needle ) ) {
				return $label;
			}
		}

		return 'سایر';
	}

	/**
	 * برچسب فارسی نوع دستگاه.
	 */
	public static function device_label( $device_type ) {
		$labels = array(
			'mobile'  => 'موبایل',
			'tablet'  => 'تبلت',
			'desktop' => 'دسکتاپ',
		);

		return isset( $labels[ $device_type ] ) ? $labels[ $device_type ] : 'نامشخص';
	}

	/**
	 * آیکون نمایشی هر نوع دستگاه (اموجی ساده، بدون نیاز به فونت آیکون اضافه).
	 */
	public static function device_icon( $device_type ) {
		$icons = array(
			'mobile'  => '📱',
			'tablet'  => '📟',
			'desktop' => '🖥',
		);

		return isset( $icons[ $device_type ] ) ? $icons[ $device_type ] : '❔';
	}
}
