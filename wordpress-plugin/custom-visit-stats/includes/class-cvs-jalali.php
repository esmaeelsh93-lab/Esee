<?php
/**
 * تبدیل تاریخ میلادی به شمسی و اعداد لاتین به فارسی، صرفاً برای نمایش در رابط کاربری.
 * تاریخ‌های ذخیره‌شده در دیتابیس همچنان میلادی باقی می‌مانند تا مرتب‌سازی/کوئری درست کار کند.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CVS_Jalali {

	private static $month_names = array(
		1  => 'فروردین',
		2  => 'اردیبهشت',
		3  => 'خرداد',
		4  => 'تیر',
		5  => 'مرداد',
		6  => 'شهریور',
		7  => 'مهر',
		8  => 'آبان',
		9  => 'آذر',
		10 => 'دی',
		11 => 'بهمن',
		12 => 'اسفند',
	);

	/**
	 * تبدیل تاریخ میلادی به شمسی.
	 *
	 * @return array [ jy, jm, jd ]
	 */
	public static function to_jalali( $gy, $gm, $gd ) {
		$g_days_in_month = array( 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 );
		$j_days_in_month = array( 31, 31, 31, 31, 31, 31, 30, 30, 30, 30, 30, 29 );

		$gy2 = $gy - 1600;
		$gm2 = $gm - 1;
		$gd2 = $gd - 1;

		$g_day_no = 365 * $gy2 + intdiv( $gy2 + 3, 4 ) - intdiv( $gy2 + 99, 100 ) + intdiv( $gy2 + 399, 400 );

		for ( $i = 0; $i < $gm2; ++$i ) {
			$g_day_no += $g_days_in_month[ $i ];
		}
		if ( $gm2 > 1 && ( ( $gy % 4 === 0 && $gy % 100 !== 0 ) || $gy % 400 === 0 ) ) {
			$g_day_no++;
		}
		$g_day_no += $gd2;

		$j_day_no = $g_day_no - 79;

		$j_np     = intdiv( $j_day_no, 12053 );
		$j_day_no = $j_day_no % 12053;

		$jy = 979 + 33 * $j_np + 4 * intdiv( $j_day_no, 1461 );
		$j_day_no = $j_day_no % 1461;

		if ( $j_day_no >= 366 ) {
			$jy      += intdiv( $j_day_no - 1, 365 );
			$j_day_no = ( $j_day_no - 1 ) % 365;
		}

		$i = 0;
		for ( ; $i < 11 && $j_day_no >= $j_days_in_month[ $i ]; ++$i ) {
			$j_day_no -= $j_days_in_month[ $i ];
		}

		$jm = $i + 1;
		$jd = $j_day_no + 1;

		return array( $jy, $jm, $jd );
	}

	/**
	 * فرمت کردن رشته‌ی تاریخ میلادی (Y-m-d) به رشته‌ی شمسی خوانا.
	 *
	 * @param string $date_string تاریخ به فرمت Y-m-d.
	 * @param string $format 'full' برای «۱۶ مرداد ۱۴۰۴» یا 'short' برای «۱۴۰۴/۰۵/۱۶».
	 */
	public static function format( $date_string, $format = 'full' ) {
		$parts = explode( '-', $date_string );
		if ( count( $parts ) !== 3 ) {
			return $date_string;
		}

		list( $gy, $gm, $gd ) = array_map( 'intval', $parts );
		list( $jy, $jm, $jd ) = self::to_jalali( $gy, $gm, $gd );

		if ( 'short' === $format ) {
			$result = sprintf( '%04d/%02d/%02d', $jy, $jm, $jd );
		} else {
			$result = sprintf( '%d %s %d', $jd, self::$month_names[ $jm ], $jy );
		}

		return self::to_persian_digits( $result );
	}

	/**
	 * تبدیل ارقام لاتین یک رشته به ارقام فارسی.
	 */
	public static function to_persian_digits( $string ) {
		$latin   = array( '0', '1', '2', '3', '4', '5', '6', '7', '8', '9' );
		$persian = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );

		return str_replace( $latin, $persian, (string) $string );
	}

	/**
	 * فرمت‌بندی عدد با جداکننده‌ی هزارگان و ارقام فارسی.
	 */
	public static function format_number( $number ) {
		return self::to_persian_digits( number_format_i18n( $number ) );
	}
}
