<?php
/**
 * نگاشت خطای Google API به پیام فارسی قابل‌فهم.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_GSC_Error_Mapper
 */
class Shojaei_SEO_GSC_Error_Mapper {

	/**
	 * Convert raw Google error response into structured payload.
	 *
	 * @param string $context   Context slug (token|sites|indexing|property).
	 * @param int    $http_code HTTP status code.
	 * @param string $reason    Google reason.
	 * @param string $message   Human message from provider.
	 * @param string $raw_body  Raw body (possibly JSON or HTML).
	 * @return array{error_code:string,ui_message:string,debug:array<string,mixed>}
	 */
	public static function map(
		string $context,
		int $http_code,
		string $reason = '',
		string $message = '',
		string $raw_body = ''
	): array {
		$reason_l = strtolower( trim( $reason ) );
		$msg_l    = strtolower( trim( $message ) );
		$raw_l    = strtolower( substr( trim( $raw_body ), 0, 1200 ) );
		$all      = $reason_l . ' ' . $msg_l . ' ' . $raw_l;

		$api_not_enabled = (
			false !== strpos( $all, 'accessnotconfigured' ) ||
			false !== strpos( $all, 'api has not been used' ) ||
			false !== strpos( $all, 'api not enabled' ) ||
			false !== strpos( $all, 'has not been used in project' ) ||
			false !== strpos( $all, 'service has been disabled' ) ||
			( false !== strpos( $all, 'disabled' ) && false === strpos( $all, 'html' ) )
		);

		$network_blocked = (
			false !== strpos( $all, 'html_block_or_proxy' ) ||
			false !== strpos( $all, 'html_error_page' ) ||
			false !== strpos( $all, 'error 403 (forbidden)!!1' ) ||
			false !== strpos( $all, 'your client does not have permission to get url' ) ||
			( false !== strpos( $all, '<!doctype html' ) && 403 === $http_code )
		);

		$auth_error = (
			401 === $http_code ||
			false !== strpos( $all, 'invalid_grant' ) ||
			false !== strpos( $all, 'invalid credentials' ) ||
			false !== strpos( $all, 'invalid_token' ) ||
			false !== strpos( $all, 'unauthorized' )
		);

		$permission_denied = (
			403 === $http_code ||
			false !== strpos( $all, 'insufficient permission' ) ||
			false !== strpos( $all, 'you do not own this site' ) ||
			false !== strpos( $all, 'permission denied' ) ||
			false !== strpos( $all, 'forbidden' )
		);

		$invalid_request = (
			400 === $http_code ||
			422 === $http_code ||
			false !== strpos( $all, 'invalid argument' ) ||
			false !== strpos( $all, 'invalid request' ) ||
			false !== strpos( $all, 'malformed' ) ||
			false !== strpos( $all, 'missing required' )
		);

		$mapped_code = 'UNKNOWN_ERROR';
		$ui_message  = __( 'ارتباط با گوگل برقرار نشد. جزئیات فنی را باز کنید.', 'shojaei-seo-for-woo' );

		if ( $network_blocked ) {
			$mapped_code = 'NETWORK_BLOCKED';
			$ui_message  = __( 'سرور فروشگاه به سرویس ایندکس گوگل راه ندارد (پاسخ HTML به‌جای JSON). شکن روی رایانه شما کافی نیست؛ باید دسترسی خروجی سرور/هاست به googleapis باز شود.', 'shojaei-seo-for-woo' );
		} elseif ( $api_not_enabled ) {
			$mapped_code = 'API_NOT_ENABLED';
			$ui_message  = 'indexing' === $context
				? __( 'در پروژه Cloud، سرویس «Web Search Indexing API» هنوز روشن نشده. از Library آن را Enable کنید و چند دقیقه صبر کنید.', 'shojaei-seo-for-woo' )
				: __( 'در پروژه Cloud، سرویس «Google Search Console API» هنوز روشن نشده.', 'shojaei-seo-for-woo' );
		} elseif ( $auth_error ) {
			$mapped_code = 'AUTH_ERROR';
			$ui_message  = __( 'ورود با کلید Service Account ناموفق بود. فایل JSON را دوباره آپلود کنید یا کلید جدید بسازید.', 'shojaei-seo-for-woo' );
		} elseif ( $permission_denied ) {
			$mapped_code = 'PERMISSION_DENIED';
			$ownership_verify_fail = (
				false !== strpos( $all, 'failed to verify the url ownership' ) ||
				false !== strpos( $all, 'failed to verify' ) ||
				false !== strpos( $all, 'url ownership' )
			);
			if ( 'indexing' === $context ) {
				$ui_message = $ownership_verify_fail
					? __( 'گوگل مالکیت این آدرس را برای ایندکس تأیید نکرد. ایمیل داخل JSON را در Search Console با نقش Owner روی همان خاصیت سایت اضافه کنید و آدرس خاصیت را دقیقاً مثل GSC بنویسید.', 'shojaei-seo-for-woo' )
					: __( 'گوگل دسترسی ایندکس را رد کرد. اگر Owner و API درست است، معمولاً مشکل از شبکه سرور است — نه از تنظیمات افزونه.', 'shojaei-seo-for-woo' );
			} elseif ( in_array( $context, array( 'sites', 'property' ), true ) ) {
				$ui_message = __( 'بررسی خودکار خاصیت از سمت API انجام نشد (فقط اطلاع‌رسانی). اگر در سرچ‌کنسول Owner هستید، خاصیت دستی را نگه دارید و ادامه دهید.', 'shojaei-seo-for-woo' );
			} else {
				$ui_message = __( 'دسترسی رد شد. ایمیل Service Account را در همان خاصیت Search Console به‌عنوان Owner اضافه کنید.', 'shojaei-seo-for-woo' );
			}
		} elseif ( $invalid_request ) {
			$mapped_code = 'INVALID_REQUEST';
			$ui_message  = __( 'آدرس یا داده‌های ارسالی ناقص است. خاصیت را مثل https://example.com/ یا sc-domain:example.com وارد کنید — نه ایمیل اکانت.', 'shojaei-seo-for-woo' );
		}

		return array(
			'error_code' => $mapped_code,
			'ui_message' => $ui_message,
			'debug'      => array(
				'context'   => $context,
				'http_code' => $http_code,
				'reason'    => $reason,
				'message'   => $message,
				'raw_body'  => $raw_body,
			),
		);
	}

	/**
	 * برچسب فارسی لایه تست برای UI.
	 *
	 * @param string $key Layer key.
	 */
	public static function layer_label_fa( string $key ): string {
		$map = array(
			'payload'  => __( 'بررسی آدرس', 'shojaei-seo-for-woo' ),
			'auth'     => __( 'ورود (توکن)', 'shojaei-seo-for-woo' ),
			'property' => __( 'خاصیت سایت', 'shojaei-seo-for-woo' ),
			'json_key' => __( 'کلید JSON', 'shojaei-seo-for-woo' ),
			'sites_list'=> __( 'فهرست خودکار خاصیت‌ها', 'shojaei-seo-for-woo' ),
			'indexing' => __( 'ایندکس گوگل', 'shojaei-seo-for-woo' ),
		);
		return $map[ $key ] ?? $key;
	}
}
