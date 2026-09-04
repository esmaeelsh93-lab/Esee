<?php
/**
 * Google Search Console — Service Account JSON key integration.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_GSC
 */
class Shojaei_SEO_GSC {

	public const OPTION_STATUS   = 'shojaei_seo_gsc_status';
	public const OPTION_SITE     = 'shojaei_seo_gsc_site_url';
	public const HOOK_PROCESS    = 'shojaei_seo_as_gsc_index';
	public const HOOK_VERIFY     = 'shojaei_seo_as_gsc_verify';
	private const TOKEN_TRANSIENT = 'shojaei_seo_gsc_access_token';
	private const QUEUE_OPTION    = 'shojaei_seo_gsc_queue';
	private const ERROR_LOG_OPTION = 'shojaei_seo_gsc_error_log';

	/**
	 * Keep the latest N failed attempts.
	 */
	private const MAX_FAILED_LOGS = 3;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( self::HOOK_PROCESS, array( $this, 'as_process_url' ), 10, 2 );
		add_action( self::HOOK_VERIFY, array( $this, 'as_verify_connection' ) );
		add_action( 'shojaei_seo_process_queue', array( $this, 'process_legacy_queue' ), 20 );
		add_action( 'shojaei_seo_gsc_submit_sitemap', array( __CLASS__, 'cron_submit_sitemap' ) );
	}

	/**
	 * Cron callback — ارسال نقشه به GSC.
	 */
	public static function cron_submit_sitemap(): void {
		$result = self::submit_sitemap();
		if ( is_wp_error( $result ) && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( '[Shojaei_SEO_GSC] sitemap submit: ' . $result->get_error_message() );
		}
	}

	/**
	 * Whether module is enabled and credentials exist.
	 */
	public static function is_ready(): bool {
		if ( 'yes' !== Shojaei_SEO_Helpers::get_option( 'shojaei_seo_gsc_enabled', 'no' ) ) {
			return false;
		}
		$creds = self::get_credentials();
		return ! empty( $creds['client_email'] ) && ! empty( $creds['private_key'] );
	}

	/**
	 * Whether connection is usable for ops (token + property).
	 * sites.list is NOT required.
	 */
	public static function is_connected(): bool {
		$status = self::get_status();
		return ! empty( $status['connected'] ) && self::is_ready();
	}

	/**
	 * Status payload for UI (includes layered diagnostics when available).
	 *
	 * @return array
	 */
	public static function get_status(): array {
		$status = get_option( self::OPTION_STATUS, array() );
		if ( ! is_array( $status ) ) {
			$status = array();
		}
		$creds   = self::get_credentials();
		$message = (string) ( $status['message'] ?? '' );
		if ( preg_match( '/<\s*(html|!doctype|body|title)/i', $message ) ) {
			$message = __( 'خطای قبلی گوگل (صفحه HTML). روی «بررسی مجدد» بزنید تا تشخیص لایه‌ای اجرا شود.', 'shojaei-seo-for-woo' );
		}
		$message = wp_strip_all_tags( $message );

		$layers = isset( $status['layers'] ) && is_array( $status['layers'] ) ? $status['layers'] : array();

		return array(
			'connected'    => ! empty( $status['connected'] ),
			'usable'       => ! empty( $status['usable'] ) || ! empty( $status['connected'] ),
			'message'      => $message,
			'site_url'     => (string) ( $status['site_url'] ?? Shojaei_SEO_Helpers::get_option( self::OPTION_SITE, '' ) ),
			'checked_at'   => (int) ( $status['checked_at'] ?? 0 ),
			'client_email' => (string) ( $creds['client_email'] ?? '' ),
			'layers'       => $layers,
		);
	}

	/**
	 * Private directory for JSON key.
	 */
	public static function credentials_dir(): string {
		$upload = wp_upload_dir();
		$dir    = trailingslashit( $upload['basedir'] ) . 'shojaei-seo-private';
		return $dir;
	}

	/**
	 * Path to stored key file.
	 */
	public static function credentials_path(): string {
		return trailingslashit( self::credentials_dir() ) . 'gsc-service-account.json';
	}

	/**
	 * Ensure private dir exists with deny rules.
	 *
	 * @return true|WP_Error
	 */
	public static function ensure_private_dir() {
		$dir = self::credentials_dir();
		if ( ! wp_mkdir_p( $dir ) ) {
			return new WP_Error( 'gsc_dir', __( 'امکان ایجاد پوشه امن کلید وجود ندارد.', 'shojaei-seo-for-woo' ) );
		}

		$htaccess = $dir . '/.htaccess';
		if ( ! file_exists( $htaccess ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $htaccess, "Deny from all\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n" );
		}
		$webconfig = $dir . '/web.config';
		if ( ! file_exists( $webconfig ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents(
				$webconfig,
				"<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<configuration><system.webServer><security><authorization><deny users=\"*\" /></authorization></security></system.webServer></configuration>\n"
			);
		}
		$index = $dir . '/index.php';
		if ( ! file_exists( $index ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			file_put_contents( $index, "<?php\n// Silence is golden.\n" );
		}

		return true;
	}

	/**
	 * Save uploaded Service Account JSON.
	 *
	 * @param string $json_raw Raw JSON string.
	 * @return true|WP_Error
	 */
	public static function save_credentials_json( string $json_raw ) {
		$json_raw = trim( $json_raw );
		if ( strlen( $json_raw ) > 200000 ) {
			return new WP_Error( 'too_large', __( 'حجم فایل کلید بیش از حد مجاز است.', 'shojaei-seo-for-woo' ) );
		}

		$data = json_decode( $json_raw, true );
		if ( ! is_array( $data ) ) {
			return new WP_Error( 'invalid_json', __( 'فایل JSON نامعتبر است.', 'shojaei-seo-for-woo' ) );
		}

		if ( empty( $data['client_email'] ) || empty( $data['private_key'] ) || empty( $data['type'] ) ) {
			return new WP_Error( 'incomplete_json', __( 'کلید باید شامل client_email و private_key باشد (Service Account).', 'shojaei-seo-for-woo' ) );
		}

		if ( 'service_account' !== $data['type'] ) {
			return new WP_Error( 'not_service_account', __( 'فقط فایل Service Account پشتیبانی می‌شود.', 'shojaei-seo-for-woo' ) );
		}

		$ensured = self::ensure_private_dir();
		if ( is_wp_error( $ensured ) ) {
			return $ensured;
		}

		$path = self::credentials_path();
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		$written = file_put_contents( $path, wp_json_encode( $data, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT ) );
		if ( false === $written ) {
			return new WP_Error( 'write_fail', __( 'ذخیره فایل کلید ناموفق بود.', 'shojaei-seo-for-woo' ) );
		}

		// Harden permissions when possible.
		if ( function_exists( 'chmod' ) ) {
			// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			@chmod( $path, 0600 );
		}

		update_option( 'shojaei_seo_gsc_client_email', sanitize_email( $data['client_email'] ), false );
		delete_transient( self::TOKEN_TRANSIENT );

		// Reset status until verification completes.
		update_option(
			self::OPTION_STATUS,
			array(
				'connected'  => false,
				'message'    => __( 'کلید ذخیره شد — در حال تایید اتصال...', 'shojaei-seo-for-woo' ),
				'site_url'   => '',
				'checked_at' => time(),
			),
			false
		);

		self::schedule_verify();

		if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
			Shojaei_SEO_Activity_Log::add(
				'gsc_key_upload',
				sprintf(
					/* translators: %s: service account email */
					__( 'کلید Service Account سرچ کنسول آپلود شد (%s).', 'shojaei-seo-for-woo' ),
					sanitize_email( $data['client_email'] )
				)
			);
		}

		return true;
	}

	/**
	 * Remove stored credentials and status.
	 */
	public static function disconnect(): void {
		$path = self::credentials_path();
		if ( file_exists( $path ) ) {
			wp_delete_file( $path );
		}
		delete_option( 'shojaei_seo_gsc_client_email' );
		delete_option( self::OPTION_STATUS );
		delete_option( self::OPTION_SITE );
		delete_transient( self::TOKEN_TRANSIENT );
		update_option( 'shojaei_seo_gsc_enabled', 'no' );

		if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
			Shojaei_SEO_Activity_Log::add( 'gsc_disconnect', __( 'اتصال گوگل سرچ کنسول قطع شد.', 'shojaei-seo-for-woo' ) );
		}
	}

	/**
	 * Load credentials from private file.
	 *
	 * @return array<string,mixed>
	 */
	public static function get_credentials(): array {
		$path = self::credentials_path();
		if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
			return array();
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$raw  = file_get_contents( $path );
		$data = json_decode( (string) $raw, true );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Schedule background connection verification.
	 */
	public static function schedule_verify(): void {
		if ( class_exists( 'Shojaei_SEO_Queue' ) && Shojaei_SEO_Queue::has_action_scheduler() ) {
			as_enqueue_async_action( self::HOOK_VERIFY, array(), 'shojaei-seo' );
			return;
		}
		wp_schedule_single_event( time() + 5, self::HOOK_VERIFY );
		if ( function_exists( 'spawn_cron' ) ) {
			spawn_cron();
		}
	}

	/**
	 * AS/Cron callback: verify.
	 */
	public function as_verify_connection(): void {
		self::verify_connection();
	}

	/**
	 * OAuth scopes required for Search Console + Indexing.
	 *
	 * @return string[]
	 */
	public static function oauth_scopes(): array {
		return array(
			'https://www.googleapis.com/auth/webmasters',
			'https://www.googleapis.com/auth/indexing',
		);
	}

	/**
	 * Commercial-grade GSC property normalizer.
	 *
	 * Accepts domain properties, URL-prefix properties, bare hosts, or empty
	 * (falls back to WordPress home_url / site_url).
	 *
	 * @param string $input_url Raw admin input.
	 * @param string $prefer    'domain' (sc-domain:) or 'url_prefix' when input is bare host/empty.
	 * @return string Normalized property or empty if unusable.
	 */
	public static function normalize_gsc_property( string $input_url = '', string $prefer = 'domain' ): string {
		$input = trim( wp_strip_all_tags( $input_url ) );
		// Copy-paste junk: zero-width, RTL marks, quotes, angle brackets.
		$input = preg_replace( '/[\x{200B}-\x{200D}\x{FEFF}\x{202A}-\x{202E}]/u', '', $input );
		$input = trim( $input, " \t\n\r\0\x0B\"'`<>" );
		$input = preg_replace( '/\s+/', '', (string) $input );

		$prefer = in_array( $prefer, array( 'domain', 'url_prefix' ), true ) ? $prefer : 'domain';

		if ( '' === $input ) {
			return self::default_property_from_wp( $prefer );
		}

		// Never treat Service Account email / Cloud identity as a GSC property.
		if ( self::looks_like_service_account_or_email( $input ) ) {
			return self::default_property_from_wp( $prefer );
		}

		// Already a domain property.
		if ( 0 === stripos( $input, 'sc-domain:' ) ) {
			$host = substr( $input, strlen( 'sc-domain:' ) );
			$host = self::normalize_hostname( $host );
			if ( ! $host || self::looks_like_service_account_or_email( $host ) ) {
				return self::default_property_from_wp( $prefer );
			}
			return 'sc-domain:' . $host;
		}

		// Bare hostname (no scheme): example.com or www.example.com
		if ( ! preg_match( '#^[a-z][a-z0-9+.-]*://#i', $input ) && false === strpos( $input, '/' ) ) {
			$host = self::normalize_hostname( $input );
			if ( ! $host || self::looks_like_service_account_or_email( $host ) ) {
				return self::default_property_from_wp( $prefer );
			}
			if ( 'url_prefix' === $prefer ) {
				$scheme = is_ssl() ? 'https' : (string) ( wp_parse_url( home_url(), PHP_URL_SCHEME ) ?: 'https' );
				return trailingslashit( $scheme . '://' . $host );
			}
			// Domain property covers www/non-www — preferred for commercial stores.
			$host = preg_replace( '/^www\./i', '', $host );
			return 'sc-domain:' . $host;
		}

		// URL-prefix: ensure scheme + trailing slash.
		if ( 0 === stripos( $input, '//' ) ) {
			$scheme = is_ssl() ? 'https' : (string) ( wp_parse_url( home_url(), PHP_URL_SCHEME ) ?: 'https' );
			$input  = $scheme . ':' . $input;
		}

		$url = esc_url_raw( $input );
		if ( ! $url ) {
			return self::default_property_from_wp( $prefer );
		}

		$parts = wp_parse_url( $url );
		if ( empty( $parts['host'] ) ) {
			return self::default_property_from_wp( $prefer );
		}

		$host = self::normalize_hostname( (string) $parts['host'] );
		if ( ! $host || self::looks_like_service_account_or_email( $host ) ) {
			return self::default_property_from_wp( $prefer );
		}

		$scheme = strtolower( (string) ( $parts['scheme'] ?? 'https' ) );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			$scheme = 'https';
		}
		$path = isset( $parts['path'] ) ? (string) $parts['path'] : '/';
		if ( '' === $path ) {
			$path = '/';
		}
		// GSC URL-prefix properties require trailing slash on the path root.
		$path = trailingslashit( $path );

		return $scheme . '://' . $host . $path;
	}

	/**
	 * Detect pasted Service Account emails / gserviceaccount hosts (invalid as GSC property).
	 *
	 * @param string $value Raw or host.
	 */
	public static function looks_like_service_account_or_email( string $value ): bool {
		$v = strtolower( trim( $value ) );
		$v = preg_replace( '#^https?://#', '', $v );
		$v = rtrim( (string) $v, '/' );
		if ( false !== strpos( $v, '@' ) ) {
			return true;
		}
		if ( false !== strpos( $v, 'gserviceaccount.com' ) ) {
			return true;
		}
		if ( false !== strpos( $v, 'iam.gserviceaccount' ) ) {
			return true;
		}
		// Corrupted form after @ was stripped: gsc-accessshojaei-seo-for-woo.iam.gserviceaccount.com
		if ( preg_match( '/\.iam\.gserviceaccount\.com$/i', $v ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Normalize a hostname (lowercase, strip path/port junk, IDN-safe ASCII).
	 *
	 * @param string $host Raw host.
	 */
	public static function normalize_hostname( string $host ): string {
		$host = strtolower( trim( $host ) );
		$host = preg_replace( '#^https?://#i', '', $host );
		$host = preg_replace( '#/.*$#', '', $host );
		$host = preg_replace( '/:\d+$/', '', $host );
		$host = trim( (string) $host, '.' );
		$host = preg_replace( '/[^a-z0-9\.\-]/', '', $host );
		return (string) $host;
	}

	/**
	 * Default property from WordPress environment.
	 *
	 * @param string $prefer domain|url_prefix.
	 */
	public static function default_property_from_wp( string $prefer = 'domain' ): string {
		$home = home_url( '/' );
		$host = self::normalize_hostname( (string) wp_parse_url( $home, PHP_URL_HOST ) );
		if ( ! $host ) {
			$host = self::normalize_hostname( (string) wp_parse_url( site_url( '/' ), PHP_URL_HOST ) );
		}
		if ( ! $host ) {
			return '';
		}

		if ( 'url_prefix' === $prefer ) {
			$scheme = (string) ( wp_parse_url( $home, PHP_URL_SCHEME ) ?: ( is_ssl() ? 'https' : 'http' ) );
			// Prefer how the site is actually rendered (home_url already reflects filters).
			return trailingslashit( set_url_scheme( $home, $scheme ) );
		}

		$host = preg_replace( '/^www\./i', '', $host );
		return 'sc-domain:' . $host;
	}

	/**
	 * Resolve authoritative stored property (normalized).
	 */
	public static function resolve_property(): string {
		$stored = (string) get_option( self::OPTION_SITE, '' );
		$prefer = (string) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_gsc_property_prefer', 'domain' );

		// Auto-heal corrupted option (e.g. pasted service-account email).
		if ( $stored && self::looks_like_service_account_or_email( $stored ) ) {
			$fixed = self::default_property_from_wp( $prefer );
			update_option( self::OPTION_SITE, $fixed, false );
			return $fixed;
		}

		$normalized = self::normalize_gsc_property( $stored, $prefer );
		if ( $stored && $normalized && $stored !== $normalized && self::looks_like_service_account_or_email( $stored ) ) {
			update_option( self::OPTION_SITE, $normalized, false );
		}
		return $normalized;
	}

	/**
	 * Candidate GSC property URLs for this WordPress site.
	 *
	 * @return string[]
	 */
	public static function site_candidates(): array {
		$host = self::normalize_hostname( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$host = preg_replace( '/^www\./', '', (string) $host );
		$home = trailingslashit( home_url( '/' ) );

		$out = array_filter(
			array(
				'sc-domain:' . $host,
				'sc-domain:www.' . $host,
				self::normalize_gsc_property( $home, 'url_prefix' ),
				self::normalize_gsc_property( set_url_scheme( $home, 'https' ), 'url_prefix' ),
				self::normalize_gsc_property( set_url_scheme( $home, 'http' ), 'url_prefix' ),
				trailingslashit( 'https://www.' . $host ),
				trailingslashit( 'https://' . $host ),
			)
		);

		return array_values( array_unique( $out ) );
	}

	/**
	 * Parse wp_remote_* response as a mapped Google error payload.
	 *
	 * @param array|WP_Error $response Response object.
	 * @param int            $code     HTTP status code.
	 * @param string         $context  token|sites|indexing|property.
	 * @return array{error_code:string,ui_message:string,debug:array<string,mixed>}
	 */
	public static function parse_google_error( $response, int $code, string $context = 'generic' ): array {
		try {
			if ( is_wp_error( $response ) ) {
				$mapped = Shojaei_SEO_GSC_Error_Mapper::map(
					$context,
					$code > 0 ? $code : 0,
					(string) $response->get_error_code(),
					(string) $response->get_error_message(),
					''
				);
				$mapped['debug']['transport_error'] = true;
				$mapped['debug']['plugin_version']  = defined( 'DAMAVAND_SEO_VERSION' ) ? DAMAVAND_SEO_VERSION : '';
				return $mapped;
			}

			$raw     = (string) wp_remote_retrieve_body( $response );
			$headers = wp_remote_retrieve_headers( $response );
			$ctype   = '';
			if ( is_object( $headers ) && method_exists( $headers, 'offsetGet' ) ) {
				$ctype = (string) $headers->offsetGet( 'content-type' );
			} elseif ( is_array( $headers ) ) {
				$ctype = (string) ( $headers['content-type'] ?? '' );
			}

			$body   = json_decode( $raw, true );
			$reason = '';
			$msg    = '';
			if ( is_array( $body ) ) {
				$msg    = (string) ( $body['error']['message'] ?? ( $body['error_description'] ?? '' ) );
				$reason = (string) ( $body['error']['errors'][0]['reason'] ?? ( $body['error']['status'] ?? '' ) );
				if ( ! $reason && ! empty( $body['error']['status'] ) ) {
					$reason = (string) $body['error']['status'];
				}
			} elseif ( '' === trim( $raw ) ) {
				$msg    = 'empty_response_body';
				$reason = 'empty_body';
			} elseif ( preg_match( '/<\s*(html|!doctype)/i', $raw ) ) {
				$msg    = 'html_error_page';
				$reason = 'html_block_or_proxy';
			}

			$mapped = Shojaei_SEO_GSC_Error_Mapper::map( $context, $code, $reason, $msg, $raw );
			$mapped['debug']['content_type']   = $ctype;
			$mapped['debug']['body_len']       = strlen( $raw );
			$mapped['debug']['raw_body']       = substr( $raw, 0, 2000 );
			$mapped['debug']['google_message'] = $msg;
			$mapped['debug']['google_reason']  = $reason;
			$mapped['debug']['plugin_version'] = defined( 'DAMAVAND_SEO_VERSION' ) ? DAMAVAND_SEO_VERSION : '';
			return $mapped;
		} catch ( Throwable $e ) {
			$mapped = Shojaei_SEO_GSC_Error_Mapper::map(
				$context,
				$code,
				'exception',
				$e->getMessage(),
				''
			);
			$mapped['debug']['plugin_version'] = defined( 'DAMAVAND_SEO_VERSION' ) ? DAMAVAND_SEO_VERSION : '';
			return $mapped;
		}
	}

	/**
	 * Backward-compatible flat error text.
	 *
	 * @param array|WP_Error $response wp_remote_* response.
	 * @param int            $code     HTTP status.
	 * @param string         $context  sites|indexing|token|generic.
	 */
	public static function extract_api_error( $response, int $code, string $context = 'generic' ): string {
		$mapped = self::parse_google_error( $response, $code, $context );
		return (string) ( $mapped['ui_message'] ?? __( 'Google API error.', 'shojaei-seo-for-woo' ) );
	}

	/**
	 * Store a failed API attempt for diagnostics (last 3 only).
	 *
	 * @param string $context Context slug.
	 * @param string $url     Tested URL/property.
	 * @param array  $mapped  Mapped error payload.
	 */
	private static function log_failed_attempt( string $context, string $url, array $mapped ): void {
		$rows = get_option( self::ERROR_LOG_OPTION, array() );
		if ( ! is_array( $rows ) ) {
			$rows = array();
		}

		$rows[] = array(
			'at'         => current_time( 'mysql' ),
			'context'    => $context,
			'url'        => $url,
			'error_code' => (string) ( $mapped['error_code'] ?? 'UNKNOWN_ERROR' ),
			'ui_message' => (string) ( $mapped['ui_message'] ?? '' ),
			'debug'      => (array) ( $mapped['debug'] ?? array() ),
		);
		if ( count( $rows ) > self::MAX_FAILED_LOGS ) {
			$rows = array_slice( $rows, -self::MAX_FAILED_LOGS );
		}

		update_option( self::ERROR_LOG_OPTION, $rows, false );
	}

	/**
	 * Get last failed attempts for UI "technical log".
	 *
	 * @return array<int,array<string,mixed>>
	 */
	public static function get_recent_failed_attempts(): array {
		$rows = get_option( self::ERROR_LOG_OPTION, array() );
		return is_array( $rows ) ? array_reverse( $rows ) : array();
	}

	/**
	 * Prefer structured mapped payload attached to WP_Error; otherwise rebuild from message.
	 *
	 * @param WP_Error $error   Error object.
	 * @param string   $context Mapper context.
	 * @return array{error_code:string,ui_message:string,debug:array<string,mixed>}
	 */
	private static function mapped_from_wp_error( WP_Error $error, string $context ): array {
		$data = $error->get_error_data();
		if ( is_array( $data ) && ! empty( $data['error_code'] ) && isset( $data['ui_message'], $data['debug'] ) ) {
			return $data;
		}

		return Shojaei_SEO_GSC_Error_Mapper::map(
			$context,
			0,
			(string) $error->get_error_code(),
			(string) $error->get_error_message(),
			is_array( $data ) ? wp_json_encode( $data ) : ''
		);
	}

	/**
	 * Layered connection diagnose (commercial / non-blocking).
	 *
	 * Layers: JSON key → Auth token → Property match → Indexing auth.
	 * sites.list failure is Warning only, never a fatal blocker.
	 *
	 * @param bool $probe_indexing Soft metadata check (+ optional publish when true and soft OK).
	 * @return array
	 */
	public static function diagnose_connection( bool $probe_indexing = false ): array {
		$prefer = (string) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_gsc_property_prefer', 'domain' );

		$layers = array(
			'json_key'  => array(
				'ok'     => false,
				'state'  => 'fail',
				'label'  => __( 'کلید JSON', 'shojaei-seo-for-woo' ),
				'detail' => '',
			),
			'auth'      => array(
				'ok'     => false,
				'state'  => 'fail',
				'label'  => __( 'ورود (توکن)', 'shojaei-seo-for-woo' ),
				'detail' => '',
			),
			'property'  => array(
				'ok'     => false,
				'state'  => 'fail',
				'label'  => __( 'خاصیت سایت', 'shojaei-seo-for-woo' ),
				'detail' => '',
			),
			'sites_list' => array(
				'ok'     => null,
				'state'  => 'pending',
				'label'  => __( 'فهرست خودکار خاصیت‌ها', 'shojaei-seo-for-woo' ),
				'detail' => '',
			),
			'indexing'  => array(
				'ok'     => null,
				'state'  => 'pending',
				'label'  => __( 'ایندکس گوگل', 'shojaei-seo-for-woo' ),
				'detail' => __( 'هنوز تست نشده. دکمه «تست ایندکس» را بزنید.', 'shojaei-seo-for-woo' ),
			),
		);

		try {
			$creds = self::get_credentials();
			if ( empty( $creds['client_email'] ) || empty( $creds['private_key'] ) || empty( $creds['type'] ) ) {
				$layers['json_key']['detail'] = __( 'Service Account JSON is missing or incomplete (needs type, client_email, private_key).', 'shojaei-seo-for-woo' );
				return self::finalize_diagnose( $layers, '', false, $probe_indexing );
			}
			if ( 'service_account' !== $creds['type'] ) {
				$layers['json_key']['detail'] = __( 'JSON type must be “service_account”. OAuth client JSON is not supported.', 'shojaei-seo-for-woo' );
				return self::finalize_diagnose( $layers, '', false, $probe_indexing );
			}

			$layers['json_key']['ok']    = true;
			$layers['json_key']['state'] = 'success';
			$layers['json_key']['detail'] = sprintf(
				/* translators: %s: service account email */
				__( 'Valid Service Account JSON — %s', 'shojaei-seo-for-woo' ),
				sanitize_email( (string) $creds['client_email'] )
			);

			$token = self::get_access_token();
			if ( is_wp_error( $token ) ) {
				$layers['auth']['detail'] = $token->get_error_message();
				return self::finalize_diagnose( $layers, '', false, $probe_indexing );
			}

			$layers['auth']['ok']    = true;
			$layers['auth']['state'] = 'success';
			$layers['auth']['detail'] = __( 'OAuth access token issued (webmasters + indexing scopes).', 'shojaei-seo-for-woo' );

			$raw_stored = (string) get_option( self::OPTION_SITE, '' );
			$property   = self::normalize_gsc_property( $raw_stored, $prefer );
			$list_matched = '';

			$sites = self::list_sites( (string) $token );
			if ( is_wp_error( $sites ) ) {
				$layers['sites_list']['ok']    = false;
				$layers['sites_list']['state'] = 'warning';
				$layers['sites_list']['detail'] = $sites->get_error_message();
			} else {
				$list_matched = self::match_site_property( $sites );
				$count        = count( $sites );
				$layers['sites_list']['ok']    = true;
				$layers['sites_list']['state'] = 'success';
				$layers['sites_list']['detail'] = $list_matched
					? sprintf(
						/* translators: 1: count, 2: matched property */
						__( 'Listed %1$d properties — auto-match: %2$s', 'shojaei-seo-for-woo' ),
						$count,
						$list_matched
					)
					: sprintf(
						/* translators: %d: count */
						__( 'Listed %d properties — no exact auto-match for this WordPress site.', 'shojaei-seo-for-woo' ),
						$count
					);

				if ( '' === $raw_stored && $list_matched ) {
					$property = self::normalize_gsc_property( $list_matched, $prefer );
				}
			}

			if ( ! $property ) {
				$hint = implode( ' · ', array_slice( self::site_candidates(), 0, 3 ) );
				$layers['property']['detail'] = sprintf(
					/* translators: %s: example properties */
					__( 'No property configured. Enter one (e.g. %s) — sites.list is not required.', 'shojaei-seo-for-woo' ),
					$hint
				);
				return self::finalize_diagnose( $layers, '', false, $probe_indexing );
			}

			// Direct sites.get — stronger than list when SA has property access but list is flaky.
			$direct = self::get_site_property( (string) $token, $property );
			if ( ! is_wp_error( $direct ) ) {
				$layers['property']['ok']    = true;
				$layers['property']['state'] = 'success';
				$layers['property']['detail'] = sprintf(
					/* translators: %s: property */
					__( 'Property verified via direct API access: %s', 'shojaei-seo-for-woo' ),
					$property
				);
			} elseif ( is_wp_error( $sites ) ) {
				// List failed + direct failed → still usable with manual property (Indexing may work).
				$layers['property']['ok']    = true;
				$layers['property']['state'] = 'warning';
				$layers['property']['detail'] = sprintf(
					/* translators: %s: normalized property */
					__( 'Auto-detection bypassed. Manual property “%s” will be used.', 'shojaei-seo-for-woo' ),
					$property
				);
			} elseif ( $list_matched && strtolower( $list_matched ) === strtolower( $property ) ) {
				$layers['property']['ok']    = true;
				$layers['property']['state'] = 'success';
				$layers['property']['detail'] = sprintf(
					/* translators: %s: property */
					__( 'Exact property match in Search Console list: %s', 'shojaei-seo-for-woo' ),
					$property
				);
			} else {
				// Property set but not confirmed — warning, still usable for Indexing tests.
				$layers['property']['ok']    = true;
				$layers['property']['state'] = 'warning';
				$layers['property']['detail'] = sprintf(
					/* translators: 1: property, 2: direct error */
					__( 'Using configured property “%1$s”. Direct verify note: %2$s', 'shojaei-seo-for-woo' ),
					$property,
					$direct->get_error_message()
				);
			}

			update_option( self::OPTION_SITE, $property, false );

			$usable = ! empty( $layers['json_key']['ok'] ) && ! empty( $layers['auth']['ok'] ) && ! empty( $layers['property']['ok'] );

			if ( $probe_indexing ) {
				if ( ! $usable ) {
					$layers['indexing']['ok']    = false;
					$layers['indexing']['state'] = 'fail';
					$layers['indexing']['detail'] = __( 'JSON key, authentication, and property must succeed before Indexing can be tested.', 'shojaei-seo-for-woo' );
				} else {
					$meta = self::get_indexing_metadata( home_url( '/' ) );
					if ( is_wp_error( $meta ) ) {
						// Soft fail on metadata — still try publish for definitive C.
						$pub = self::request_indexing( home_url( '/' ), 'URL_UPDATED', false );
						if ( is_wp_error( $pub ) ) {
							$layers['indexing']['ok']    = false;
							$layers['indexing']['state'] = 'fail';
							$layers['indexing']['detail'] = $pub->get_error_message();
						} else {
							$layers['indexing']['ok']    = true;
							$layers['indexing']['state'] = 'success';
							$layers['indexing']['detail'] = __( 'Indexing publish succeeded (homepage).', 'shojaei-seo-for-woo' );
						}
					} else {
						$layers['indexing']['ok']    = true;
						$layers['indexing']['state'] = 'success';
						$layers['indexing']['detail'] = __( 'Indexing API authorization OK (urlNotifications metadata).', 'shojaei-seo-for-woo' );
						// Also publish once when user explicitly requested full probe.
						$pub = self::request_indexing( home_url( '/' ), 'URL_UPDATED', false );
						if ( is_wp_error( $pub ) ) {
							$layers['indexing']['state']  = 'warning';
							$layers['indexing']['detail'] = sprintf(
								/* translators: %s: error */
								__( 'Metadata OK, but publish failed: %s', 'shojaei-seo-for-woo' ),
								$pub->get_error_message()
							);
						} else {
							$layers['indexing']['detail'] = __( 'Indexing API OK — metadata + homepage publish succeeded.', 'shojaei-seo-for-woo' );
						}
					}
				}
			}

			return self::finalize_diagnose( $layers, $property, $usable, $probe_indexing );
		} catch ( Throwable $e ) {
			$layers['auth']['detail'] = sprintf(
				/* translators: %s: exception */
				__( 'Unexpected connection error: %s', 'shojaei-seo-for-woo' ),
				$e->getMessage()
			);
			return self::finalize_diagnose( $layers, '', false, $probe_indexing );
		}
	}

	/**
	 * Build status option + summary message from layers.
	 *
	 * @param array  $layers Layers.
	 * @param string $property Property.
	 * @param bool   $usable Usable for ops.
	 * @param bool   $probed Whether indexing was probed.
	 * @return array
	 */
	private static function finalize_diagnose( array $layers, string $property, bool $usable, bool $probed ): array {
		// Alias for older admin JS that still reads layers.token.
		if ( isset( $layers['auth'] ) ) {
			$layers['token'] = $layers['auth'];
		}

		$parts = array();
		foreach ( array( 'json_key', 'auth', 'property', 'sites_list', 'indexing' ) as $key ) {
			if ( ! isset( $layers[ $key ] ) ) {
				continue;
			}
			$state = (string) ( $layers[ $key ]['state'] ?? '' );
			if ( 'success' === $state ) {
				$mark = '✓';
			} elseif ( 'warning' === $state ) {
				$mark = '!';
			} elseif ( 'pending' === $state || null === ( $layers[ $key ]['ok'] ?? null ) ) {
				$mark = '○';
			} else {
				$mark = '✗';
			}
			$parts[] = $mark . ' ' . $layers[ $key ]['label'] . ': ' . $layers[ $key ]['detail'];
		}

		if ( $usable ) {
			$message = __( 'Connection usable (valid key + token + property).', 'shojaei-seo-for-woo' );
			if ( 'warning' === ( $layers['property']['state'] ?? '' ) || 'warning' === ( $layers['sites_list']['state'] ?? '' ) ) {
				$message .= ' ' . __( 'Auto-list/direct verify had warnings — Indexing can still work with the configured property.', 'shojaei-seo-for-woo' );
			}
			if ( $probed && empty( $layers['indexing']['ok'] ) ) {
				$message .= ' ' . __( 'Indexing authorization failed — enable Web Search Indexing API.', 'shojaei-seo-for-woo' );
			}
		} elseif ( ! empty( $layers['json_key']['ok'] ) && ! empty( $layers['auth']['ok'] ) ) {
			$message = __( 'Authentication OK, but no Search Console property is configured.', 'shojaei-seo-for-woo' );
		} elseif ( ! empty( $layers['json_key']['ok'] ) ) {
			$message = (string) ( $layers['auth']['detail'] ?: __( 'Could not obtain access token.', 'shojaei-seo-for-woo' ) );
		} else {
			$message = (string) ( $layers['json_key']['detail'] ?: __( 'Connection failed.', 'shojaei-seo-for-woo' ) );
		}

		$status = array(
			'connected'  => $usable,
			'usable'     => $usable,
			'message'    => $message,
			'site_url'   => $property,
			'checked_at' => time(),
			'layers'     => $layers,
			'details'    => $parts,
			'scopes'     => self::oauth_scopes(),
		);

		update_option( self::OPTION_STATUS, $status, false );
		if ( $usable ) {
			update_option( 'shojaei_seo_gsc_enabled', 'yes' );
		}

		return $status;
	}

	/**
	 * Verify connection (layered). sites.list never blocks usable state.
	 *
	 * @param bool $probe_indexing Also hit Indexing API.
	 * @return array Status array.
	 */
	public static function verify_connection( bool $probe_indexing = false ): array {
		$status = self::diagnose_connection( $probe_indexing );

		if ( ! empty( $status['connected'] ) ) {
			Shojaei_SEO_Notifications::add(
				'gsc_connected',
				(string) $status['message'],
				0,
				admin_url( 'admin.php?page=shojaei-seo&tab=settings#shojaei-gsc' ),
				__( 'GSC settings', 'shojaei-seo-for-woo' )
			);
			if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
				Shojaei_SEO_Activity_Log::add( 'gsc_connected', (string) $status['message'] );
			}
		} elseif ( empty( $status['layers']['json_key']['ok'] ) || empty( $status['layers']['auth']['ok'] ) ) {
			Shojaei_SEO_Notifications::add(
				'gsc_error',
				(string) $status['message'],
				0,
				admin_url( 'admin.php?page=shojaei-seo&tab=settings#shojaei-gsc' ),
				__( 'GSC settings', 'shojaei-seo-for-woo' )
			);
		}

		return $status;
	}

	/**
	 * Pick the best matching GSC property for this WordPress site.
	 *
	 * @param array $sites Site entries from API.
	 * @return string Empty if none.
	 */
	public static function match_site_property( array $sites ): string {
		$candidates = self::site_candidates();
		$available  = array();
		foreach ( $sites as $site ) {
			$url = is_array( $site ) ? (string) ( $site['siteUrl'] ?? '' ) : (string) $site;
			if ( $url ) {
				$available[] = $url;
			}
		}

		foreach ( $candidates as $want ) {
			foreach ( $available as $have ) {
				if ( strtolower( $have ) === strtolower( $want ) ) {
					return $have;
				}
			}
		}

		$host = self::normalize_hostname( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
		$host = preg_replace( '/^www\./', '', (string) $host );

		foreach ( $available as $have ) {
			if ( 0 === stripos( $have, 'sc-domain:' ) ) {
				$dom = self::normalize_hostname( substr( $have, strlen( 'sc-domain:' ) ) );
				$dom = preg_replace( '/^www\./', '', $dom );
				if ( $dom === $host ) {
					return $have;
				}
			} elseif ( false !== stripos( $have, $host ) ) {
				return $have;
			}
		}

		return '';
	}

	/**
	 * List Search Console sites (informational — never a hard dependency).
	 *
	 * @param string $token Access token.
	 * @return array|WP_Error
	 */
	public static function list_sites( string $token ) {
		try {
			$response = wp_remote_get(
				'https://www.googleapis.com/webmasters/v3/sites',
				array(
					'timeout'     => 20,
					'redirection' => 0,
					'headers'     => array(
						'Authorization' => 'Bearer ' . $token,
						'Accept'        => 'application/json',
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

			if ( $code < 200 || $code >= 300 ) {
				return new WP_Error( 'gsc_sites', self::extract_api_error( $response, $code, 'sites' ) );
			}

			return $body['siteEntry'] ?? array();
		} catch ( Throwable $e ) {
			return new WP_Error( 'gsc_sites_exception', $e->getMessage() );
		}
	}

	/**
	 * گزارش Search Analytics رسمی گوگل (کوئری / صفحه / دستگاه / کشور).
	 *
	 * POST webmasters/v3/sites/{siteUrl}/searchAnalytics/query
	 * داده فقط از API گوگل خوانده می‌شود؛ روی سرور ثالث ذخیره نمی‌شود.
	 *
	 * @param array<string,mixed> $args Args.
	 * @return array{rows:array<int,array<string,mixed>>,totals:array<string,float|int>,meta:array<string,mixed>}|WP_Error
	 */
	public static function search_analytics_query( array $args = array() ) {
		if ( ! self::is_ready() ) {
			return new WP_Error( 'gsc_not_ready', __( 'اتصال Search Console آماده نیست.', 'shojaei-seo-for-woo' ) );
		}

		$dimension = sanitize_key( (string) ( $args['dimension'] ?? 'query' ) );
		$allowed   = array( 'query', 'page', 'country', 'device', 'date' );
		if ( ! in_array( $dimension, $allowed, true ) ) {
			$dimension = 'query';
		}

		$days = isset( $args['days'] ) ? absint( $args['days'] ) : 28;
		$days = min( 90, max( 7, $days ) );

		// GSC معمولاً ۲–۳ روز تأخیر دارد؛ endDate را ۲ روز عقب می‌گذاریم.
		$end   = gmdate( 'Y-m-d', strtotime( '-2 days' ) );
		$start = gmdate( 'Y-m-d', strtotime( $end . ' -' . ( $days - 1 ) . ' days' ) );
		if ( ! empty( $args['start_date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $args['start_date'] ) ) {
			$start = (string) $args['start_date'];
		}
		if ( ! empty( $args['end_date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', (string) $args['end_date'] ) ) {
			$end = (string) $args['end_date'];
		}

		$row_limit = isset( $args['row_limit'] ) ? absint( $args['row_limit'] ) : 25;
		$row_limit = min( 100, max( 5, $row_limit ) );

		$search_type = sanitize_key( (string) ( $args['search_type'] ?? 'web' ) );
		if ( ! in_array( $search_type, array( 'web', 'image', 'video', 'news' ), true ) ) {
			$search_type = 'web';
		}

		$force = ! empty( $args['force'] );
		$cache_key = 'shojaei_gsc_sa_' . md5( wp_json_encode( array( $dimension, $start, $end, $row_limit, $search_type, self::resolve_property() ) ) );
		if ( ! $force ) {
			$cached = get_transient( $cache_key );
			if ( is_array( $cached ) && isset( $cached['rows'] ) ) {
				$cached['meta']['cached'] = true;
				return $cached;
			}
		}

		$token = self::get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$property = self::resolve_property();
		if ( '' === $property ) {
			return new WP_Error( 'no_property', __( 'ویژگی Search Console تنظیم نشده است.', 'shojaei-seo-for-woo' ) );
		}

		$body = array(
			'startDate'             => $start,
			'endDate'               => $end,
			'dimensions'            => array( $dimension ),
			'rowLimit'              => $row_limit,
			'startRow'              => 0,
			'searchType'            => $search_type,
			'dataState'             => 'final',
			'aggregationType'       => 'auto',
			'dimensionFilterGroups' => array(),
		);

		$endpoint = 'https://www.googleapis.com/webmasters/v3/sites/' . rawurlencode( $property ) . '/searchAnalytics/query';

		$response = wp_remote_post(
			$endpoint,
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$parsed = self::parse_google_error( $response, $code, 'search_analytics' );
			$msg    = is_array( $parsed ) ? (string) ( $parsed['message'] ?? '' ) : '';
			if ( '' === $msg ) {
				$msg = is_array( $raw ) ? (string) ( $raw['error']['message'] ?? wp_json_encode( $raw ) ) : (string) wp_remote_retrieve_body( $response );
			}
			return new WP_Error(
				'search_analytics_fail',
				sprintf(
					/* translators: 1: http 2: message */
					__( 'خواندن Search Analytics ناموفق (%1$d): %2$s', 'shojaei-seo-for-woo' ),
					$code,
					$msg
				)
			);
		}

		$rows_in = ( is_array( $raw ) && isset( $raw['rows'] ) && is_array( $raw['rows'] ) ) ? $raw['rows'] : array();
		$rows    = array();
		$tot_c   = 0;
		$tot_i   = 0.0;
		$tot_pos = 0.0;
		$pos_n   = 0;

		foreach ( $rows_in as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$keys = isset( $row['keys'] ) && is_array( $row['keys'] ) ? $row['keys'] : array();
			$label = isset( $keys[0] ) ? (string) $keys[0] : '';
			$clicks = (float) ( $row['clicks'] ?? 0 );
			$impr   = (float) ( $row['impressions'] ?? 0 );
			$ctr    = (float) ( $row['ctr'] ?? 0 );
			$pos    = (float) ( $row['position'] ?? 0 );

			$rows[] = array(
				'label'       => $label,
				'clicks'      => (int) round( $clicks ),
				'impressions' => (int) round( $impr ),
				'ctr'         => round( $ctr * 100, 2 ),
				'position'    => round( $pos, 1 ),
			);

			$tot_c += $clicks;
			$tot_i += $impr;
			if ( $pos > 0 ) {
				$tot_pos += $pos;
				++$pos_n;
			}
		}

		$payload = array(
			'rows'   => $rows,
			'totals' => array(
				'clicks'      => (int) round( $tot_c ),
				'impressions' => (int) round( $tot_i ),
				'ctr'         => $tot_i > 0 ? round( ( $tot_c / $tot_i ) * 100, 2 ) : 0,
				'position'    => $pos_n > 0 ? round( $tot_pos / $pos_n, 1 ) : 0,
			),
			'meta'   => array(
				'dimension'   => $dimension,
				'start_date'  => $start,
				'end_date'    => $end,
				'days'        => $days,
				'row_limit'   => $row_limit,
				'search_type' => $search_type,
				'property'    => $property,
				'cached'      => false,
				'fetched_at'  => time(),
				'response_rows' => count( $rows ),
			),
		);

		set_transient( $cache_key, $payload, 6 * HOUR_IN_SECONDS );
		return $payload;
	}

	/**
	 * Direct property check: GET webmasters/v3/sites/{siteUrl}.
	 *
	 * @param string $token    Access token.
	 * @param string $property Normalized property.
	 * @return array|WP_Error
	 */
	public static function get_site_property( string $token, string $property ) {
		$property = self::normalize_gsc_property( $property );
		if ( ! $property ) {
			return new WP_Error( 'empty_property', __( 'Property is empty.', 'shojaei-seo-for-woo' ) );
		}

		try {
			// Google requires the siteUrl path segment to be URL-encoded (including sc-domain:).
			$encoded  = rawurlencode( $property );
			$response = wp_remote_get(
				'https://www.googleapis.com/webmasters/v3/sites/' . $encoded,
				array(
					'timeout'     => 20,
					'redirection' => 0,
					'headers'     => array(
						'Authorization' => 'Bearer ' . $token,
						'Accept'        => 'application/json',
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

			if ( $code < 200 || $code >= 300 ) {
				$mapped = self::parse_google_error( $response, $code, 'sites' );
				return new WP_Error(
					'gsc_site_get',
					(string) $mapped['ui_message'],
					$mapped
				);
			}

			return is_array( $body ) ? $body : array();
		} catch ( Throwable $e ) {
			return new WP_Error( 'gsc_site_get_exception', $e->getMessage() );
		}
	}

	/**
	 * Soft Indexing API check (does not publish).
	 *
	 * @param string $url Absolute URL.
	 * @return array|WP_Error
	 */
	public static function get_indexing_metadata( string $url ) {
		$url = esc_url_raw( $url );
		if ( ! $url ) {
			return new WP_Error( 'empty_url', __( 'URL is empty.', 'shojaei-seo-for-woo' ) );
		}

		$token = self::get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		try {
			$response = wp_remote_get(
				'https://indexing.googleapis.com/v3/urlNotifications/metadata?url=' . rawurlencode( $url ),
				array(
					'timeout'     => 20,
					'redirection' => 0,
					'headers'     => array(
						'Authorization' => 'Bearer ' . $token,
						'Accept'        => 'application/json',
					),
				)
			);

			if ( is_wp_error( $response ) ) {
				return $response;
			}

			$code = (int) wp_remote_retrieve_response_code( $response );
			// 404 = no notification history yet, but auth/scopes are fine.
			if ( 404 === $code ) {
				return array( 'ok' => true, 'note' => 'no_history' );
			}
			if ( $code < 200 || $code >= 300 ) {
				$mapped = self::parse_google_error( $response, $code, 'indexing' );
				return new WP_Error( 'gsc_index_meta', (string) $mapped['ui_message'], $mapped );
			}

			$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );
			return is_array( $body ) ? $body : array( 'ok' => true );
		} catch ( Throwable $e ) {
			return new WP_Error( 'gsc_index_meta_exception', $e->getMessage() );
		}
	}

	/**
	 * Validate indexing payload structure before any API call.
	 *
	 * @param string $url  URL.
	 * @param string $type Type.
	 */
	public static function is_valid_indexing_payload( string $url, string $type ): bool {
		$url = esc_url_raw( $url );
		if ( ! $url ) {
			return false;
		}
		$parts = wp_parse_url( $url );
		if ( empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return false;
		}
		return in_array( $type, array( 'URL_UPDATED', 'URL_DELETED' ), true );
	}

	/**
	 * Pre-flight checks before indexing test.
	 *
	 * @param string $url URL for test.
	 * @return array
	 */
	public static function preflight_indexing_check( string $url ): array {
		$url      = esc_url_raw( $url ?: home_url( '/' ) );
		$property = self::resolve_property();
		$out      = array(
			'ok'       => false,
			'url'      => $url,
			'property' => $property,
			'layers'   => array(
				'payload'  => array( 'ok' => false, 'detail' => '' ),
				'auth'     => array( 'ok' => false, 'detail' => '' ),
				'property' => array( 'ok' => false, 'detail' => '' ),
			),
		);

		if ( ! self::is_valid_indexing_payload( $url, 'URL_UPDATED' ) ) {
			$out['layers']['payload']['detail'] = __( 'Invalid URL/payload for Indexing API request.', 'shojaei-seo-for-woo' );
			return $out;
		}
		$out['layers']['payload'] = array(
			'ok'     => true,
			'detail' => __( 'آدرس ارسالی معتبر است.', 'shojaei-seo-for-woo' ),
		);

		$token = self::get_access_token();
		if ( is_wp_error( $token ) ) {
			$out['layers']['auth']['detail'] = $token->get_error_message();
			return $out;
		}
		$out['layers']['auth'] = array(
			'ok'     => true,
			'detail' => __( 'ورود به گوگل موفق بود.', 'shojaei-seo-for-woo' ),
		);

		if ( ! $property ) {
			$out['layers']['property']['detail'] = __( 'Manual property is empty and no fallback property could be normalized.', 'shojaei-seo-for-woo' );
			return $out;
		}

		$site = self::get_site_property( (string) $token, $property );
		if ( is_wp_error( $site ) ) {
			// Informational only: GSC UI Owner can be correct while sites.get fails (API off/blocked).
			$out['layers']['property'] = array(
				'ok'     => false,
				'detail' => sprintf(
					/* translators: 1: property, 2: api note */
					__( 'خاصیت دستی «%1$s» ذخیره است — تأیید API: %2$s', 'shojaei-seo-for-woo' ),
					$property,
					$site->get_error_message()
				),
			);
			$out['ok'] = true;
			return $out;
		}

		$out['layers']['property'] = array(
			'ok'     => true,
			'detail' => __( 'Property recognized by Search Console API.', 'shojaei-seo-for-woo' ),
		);
		$out['ok'] = true;
		return $out;
	}

	/**
	 * Production test workflow for Indexing API.
	 *
	 * Returns friendly admin message + technical debug log and keeps last 3 failures.
	 *
	 * @param string $url URL to test.
	 * @return array
	 */
	public static function test_indexing_connection( string $url = '' ): array {
		$url       = esc_url_raw( $url ?: home_url( '/' ) );
		$preflight = self::preflight_indexing_check( $url );
		$result    = array(
			'ok'              => false,
			'admin_message'   => __( 'Indexing test failed.', 'shojaei-seo-for-woo' ),
			'error_code'      => '',
			'preflight'       => $preflight,
			'technical_log'   => array(),
			'recent_failures' => self::get_recent_failed_attempts(),
		);

		if ( empty( $preflight['layers']['payload']['ok'] ) || empty( $preflight['layers']['auth']['ok'] ) ) {
			$mapped = Shojaei_SEO_GSC_Error_Mapper::map(
				'indexing',
				400,
				'invalid_request',
				(string) ( $preflight['layers']['payload']['detail'] ?: $preflight['layers']['auth']['detail'] ),
				wp_json_encode( $preflight )
			);
			self::log_failed_attempt( 'indexing_preflight', $url, $mapped );
			$result['error_code']      = $mapped['error_code'];
			$result['admin_message']   = $mapped['ui_message'];
			$result['technical_log']   = $mapped['debug'];
			$result['recent_failures'] = self::get_recent_failed_attempts();
			return $result;
		}

		// Layer C primary = publish. Metadata is soft and must never hide Google's raw body.
		$publish = self::request_indexing( $url, 'URL_UPDATED', false );
		if ( ! is_wp_error( $publish ) ) {
			$result['ok']            = true;
			$result['error_code']    = '';
			$result['admin_message'] = __( 'تست Indexing موفق بود (publish OK).', 'shojaei-seo-for-woo' );
			$result['technical_log'] = array(
				'context'         => 'indexing_success',
				'url'             => $url,
				'property'        => (string) ( $preflight['property'] ?? '' ),
				'plugin_version'  => defined( 'DAMAVAND_SEO_VERSION' ) ? DAMAVAND_SEO_VERSION : '',
				'client_email'    => (string) ( self::get_credentials()['client_email'] ?? '' ),
			);
			return $result;
		}

		$mapped = self::mapped_from_wp_error( $publish, 'indexing' );
		// Soft metadata probe only to enrich debug (never overwrite Google raw payload).
		$meta = self::get_indexing_metadata( $url );
		if ( is_wp_error( $meta ) ) {
			$meta_mapped = self::mapped_from_wp_error( $meta, 'indexing' );
			$mapped['debug']['metadata_probe'] = $meta_mapped['debug'] ?? array();
		} else {
			$mapped['debug']['metadata_probe'] = array( 'ok' => true );
		}
		$mapped['debug']['client_email']   = (string) ( self::get_credentials()['client_email'] ?? '' );
		$mapped['debug']['property']       = (string) ( $preflight['property'] ?? '' );
		$mapped['debug']['tested_url']     = $url;
		$mapped['debug']['plugin_version'] = defined( 'DAMAVAND_SEO_VERSION' ) ? DAMAVAND_SEO_VERSION : '';

		self::log_failed_attempt( 'indexing_publish', $url, $mapped );
		$result['error_code']      = $mapped['error_code'];
		$result['admin_message']   = $mapped['ui_message'];
		$result['technical_log']   = $mapped['debug'];
		$result['recent_failures'] = self::get_recent_failed_attempts();
		return $result;
	}

	/**
	 * CamelCase alias for integration compatibility.
	 *
	 * @param string $url URL to test.
	 * @return array
	 */
	public static function testIndexingConnection( string $url = '' ): array { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid
		return self::test_indexing_connection( $url );
	}

	/**
	 * Enqueue Request Indexing for a URL (background-safe).
	 *
	 * @param string $url  Absolute URL.
	 * @param string $type URL_UPDATED|URL_DELETED.
	 */
	public static function enqueue_indexing( string $url, string $type = 'URL_UPDATED' ): void {
		$url = esc_url_raw( $url );
		if ( ! $url || ! self::is_ready() ) {
			return;
		}
		// Need an authoritative property for usable ops (manual or previously matched).
		if ( ! self::resolve_property() && ! self::is_connected() ) {
			return;
		}
		if ( 'yes' !== Shojaei_SEO_Helpers::get_option( 'shojaei_seo_gsc_auto_index', 'yes' ) ) {
			return;
		}

		$type = ( 'URL_DELETED' === $type ) ? 'URL_DELETED' : 'URL_UPDATED';

		if ( class_exists( 'Shojaei_SEO_Queue' ) && Shojaei_SEO_Queue::has_action_scheduler() ) {
			as_enqueue_async_action( self::HOOK_PROCESS, array( $url, $type ), 'shojaei-seo' );
			return;
		}

		$queue   = get_option( self::QUEUE_OPTION, array() );
		$queue   = is_array( $queue ) ? $queue : array();
		$queue[] = array(
			'url'  => $url,
			'type' => $type,
			'at'   => time(),
		);
		update_option( self::QUEUE_OPTION, $queue, false );
	}

	/**
	 * Convenience: notify product URL after structural SEO change.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $event      redirect|oos|gone|undo.
	 */
	public static function notify_product_change( int $product_id, string $event = 'redirect' ): void {
		if ( ! $product_id || ! self::is_ready() ) {
			return;
		}

		$url = get_permalink( $product_id );
		if ( ! $url ) {
			return;
		}

		$type = ( 'gone' === $event ) ? 'URL_DELETED' : 'URL_UPDATED';
		self::enqueue_indexing( $url, $type );

		// Also ping target URL after redirect so Google re-evaluates destination.
		if ( 'redirect' === $event ) {
			global $wpdb;
			$target = $wpdb->get_var(
				$wpdb->prepare(
					'SELECT target_url FROM ' . Shojaei_SEO_Helpers::oos_table() . ' WHERE product_id = %d',
					$product_id
				)
			);
			if ( $target ) {
				self::enqueue_indexing( (string) $target, 'URL_UPDATED' );
			}
		}
	}

	/**
	 * AS callback: process one URL.
	 *
	 * @param string $url  URL.
	 * @param string $type Notification type.
	 */
	public function as_process_url( $url, $type = 'URL_UPDATED' ): void {
		self::request_indexing( (string) $url, (string) $type );
	}

	/**
	 * Drain legacy option queue (WP-Cron hourly).
	 */
	public function process_legacy_queue(): void {
		$queue = get_option( self::QUEUE_OPTION, array() );
		if ( empty( $queue ) || ! is_array( $queue ) ) {
			return;
		}

		$size  = class_exists( 'Shojaei_SEO_Batch' ) ? Shojaei_SEO_Batch::batch_size() : 50;
		$batch = array_splice( $queue, 0, min( 20, $size ) );
		foreach ( $batch as $item ) {
			self::request_indexing( (string) ( $item['url'] ?? '' ), (string) ( $item['type'] ?? 'URL_UPDATED' ) );
		}
		update_option( self::QUEUE_OPTION, $queue, false );
	}

	/**
	 * Call Google Indexing API (Request Indexing).
	 *
	 * @param string $url         URL.
	 * @param string $type        URL_UPDATED|URL_DELETED.
	 * @param bool   $do_inspect  Also call URL Inspection (best-effort).
	 * @return true|WP_Error
	 */
	public static function request_indexing( string $url, string $type = 'URL_UPDATED', bool $do_inspect = true ) {
		$url = esc_url_raw( $url );
		if ( ! $url ) {
			return new WP_Error( 'empty_url', __( 'آدرس خالی است.', 'shojaei-seo-for-woo' ) );
		}

		$token = self::get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$type = ( 'URL_DELETED' === $type ) ? 'URL_DELETED' : 'URL_UPDATED';

		$response = wp_remote_post(
			'https://indexing.googleapis.com/v3/urlNotifications:publish',
			array(
				'timeout'     => 20,
				'redirection' => 0,
				'headers'     => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
					'Accept'        => 'application/json',
				),
				'body'        => wp_json_encode(
					array(
						'url'  => $url,
						'type' => $type,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );

		if ( $code < 200 || $code >= 300 ) {
			$mapped = self::parse_google_error( $response, $code, 'indexing' );
			$msg    = (string) $mapped['ui_message'];
			if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
				Shojaei_SEO_Activity_Log::add(
					'gsc_index_error',
					sprintf(
						/* translators: 1: url, 2: message */
						__( 'خطای Request Indexing برای %1$s: %2$s', 'shojaei-seo-for-woo' ),
						$url,
						$msg
					)
				);
			}
			return new WP_Error( 'gsc_index', $msg, $mapped );
		}

		if ( $do_inspect ) {
			self::inspect_url( $url );
		}

		if ( class_exists( 'Shojaei_SEO_Activity_Log' ) ) {
			Shojaei_SEO_Activity_Log::add(
				'gsc_index',
				sprintf(
					/* translators: 1: type, 2: url */
					__( 'Request Indexing (%1$s): %2$s', 'shojaei-seo-for-woo' ),
					$type,
					$url
				)
			);
		}

		$key   = 'shojaei_seo_stats_gsc_indexed';
		$count = (int) Shojaei_SEO_Helpers::get_option( $key, 0 );
		update_option( $key, $count + 1 );

		return true;
	}

	/**
	 * URL Inspection API (status snapshot).
	 *
	 * @param string $url Inspection URL.
	 * @return array|WP_Error
	 */
	public static function inspect_url( string $url ) {
		$site = Shojaei_SEO_Helpers::get_option( self::OPTION_SITE, '' );
		if ( ! $site ) {
			$status = self::get_status();
			$site   = $status['site_url'] ?? '';
		}
		if ( ! $site ) {
			return new WP_Error( 'no_site', __( 'خاصیت Search Console تنظیم نشده است.', 'shojaei-seo-for-woo' ) );
		}

		$token = self::get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$response = wp_remote_post(
			'https://searchconsole.googleapis.com/v1/urlInspection/index:inspect',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'inspectionUrl' => $url,
						'siteUrl'       => $site,
						'languageCode'  => 'fa',
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 ) {
			$msg = is_array( $body ) ? ( $body['error']['message'] ?? '' ) : '';
			return new WP_Error( 'gsc_inspect', $msg ?: __( 'بازرسی URL ناموفق بود.', 'shojaei-seo-for-woo' ) );
		}

		return is_array( $body ) ? $body : array();
	}

	/**
	 * Obtain OAuth access token via JWT (Service Account).
	 *
	 * @return string|WP_Error
	 */
	public static function get_access_token() {
		$cached = get_transient( self::TOKEN_TRANSIENT );
		if ( is_string( $cached ) && $cached ) {
			return $cached;
		}

		$creds = self::get_credentials();
		if ( empty( $creds['client_email'] ) || empty( $creds['private_key'] ) ) {
			return new WP_Error( 'no_creds', __( 'کلید Service Account موجود نیست.', 'shojaei-seo-for-woo' ) );
		}

		if ( ! function_exists( 'openssl_sign' ) ) {
			return new WP_Error( 'no_openssl', __( 'افزونه OpenSSL روی سرور فعال نیست.', 'shojaei-seo-for-woo' ) );
		}

		$now    = time();
		$header = self::base64url( wp_json_encode( array( 'alg' => 'RS256', 'typ' => 'JWT' ) ) );
		$claims = self::base64url(
			wp_json_encode(
				array(
					'iss'   => $creds['client_email'],
					'scope' => implode( ' ', self::oauth_scopes() ),
					'aud'   => 'https://oauth2.googleapis.com/token',
					'iat'   => $now,
					'exp'   => $now + 3600,
				)
			)
		);

		$unsigned = $header . '.' . $claims;
		$key      = openssl_pkey_get_private( $creds['private_key'] );
		if ( ! $key ) {
			return new WP_Error( 'bad_key', __( 'کلید خصوصی قابل خواندن نیست.', 'shojaei-seo-for-woo' ) );
		}

		$signature = '';
		$ok        = openssl_sign( $unsigned, $signature, $key, OPENSSL_ALGO_SHA256 );
		if ( ! $ok ) {
			return new WP_Error( 'sign_fail', __( 'امضای JWT ناموفق بود.', 'shojaei-seo-for-woo' ) );
		}

		$jwt = $unsigned . '.' . self::base64url( $signature );

		$response = wp_remote_post(
			'https://oauth2.googleapis.com/token',
			array(
				'timeout' => 30,
				'body'    => array(
					'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
					'assertion'  => $jwt,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( $code < 200 || $code >= 300 || empty( $body['access_token'] ) ) {
			$msg = is_array( $body ) ? ( $body['error_description'] ?? $body['error'] ?? wp_json_encode( $body ) ) : '';
			return new WP_Error( 'token_fail', sprintf(
				/* translators: %s: error */
				__( 'دریافت توکن گوگل ناموفق: %s', 'shojaei-seo-for-woo' ),
				$msg
			) );
		}

		$ttl = max( 60, (int) ( $body['expires_in'] ?? 3600 ) - 60 );
		set_transient( self::TOKEN_TRANSIENT, $body['access_token'], $ttl );

		return (string) $body['access_token'];
	}

	/**
	 * ارسال / به‌روزرسانی نقشه سایت در Search Console API (رسمی گوگل).
	 *
	 * PUT webmasters/v3/sites/{siteUrl}/sitemaps/{feedpath}
	 * هیچ سرور ثالثی درگیر نیست.
	 *
	 * @param string $sitemap_url آدرس کامل XML نقشه سایت.
	 * @return true|WP_Error
	 */
	public static function submit_sitemap( string $sitemap_url = '' ) {
		if ( ! self::is_ready() ) {
			return new WP_Error( 'gsc_not_ready', __( 'اتصال Search Console آماده نیست.', 'shojaei-seo-for-woo' ) );
		}

		$sitemap_url = esc_url_raw( $sitemap_url );
		if ( '' === $sitemap_url ) {
			if ( class_exists( 'SEO_Core_Sitemap' ) ) {
				$sm = new SEO_Core_Sitemap();
				$sitemap_url = $sm->public_url( 'index' );
			} else {
				$sitemap_url = home_url( '/shojaei-sitemap.xml' );
			}
		}
		$sitemap_url = esc_url_raw( $sitemap_url );
		if ( '' === $sitemap_url ) {
			return new WP_Error( 'bad_sitemap', __( 'آدرس نقشه سایت نامعتبر است.', 'shojaei-seo-for-woo' ) );
		}

		$token = self::get_access_token();
		if ( is_wp_error( $token ) ) {
			return $token;
		}

		$property = self::resolve_property();
		if ( '' === $property ) {
			return new WP_Error( 'no_property', __( 'ویژگی Search Console تنظیم نشده است.', 'shojaei-seo-for-woo' ) );
		}

		$endpoint = sprintf(
			'https://www.googleapis.com/webmasters/v3/sites/%s/sitemaps/%s',
			rawurlencode( $property ),
			rawurlencode( $sitemap_url )
		);

		$response = wp_remote_request(
			$endpoint,
			array(
				'method'  => 'PUT',
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				),
				'body'    => '{}',
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			$parsed = self::parse_google_error( $response, $code, 'sitemap_submit' );
			$msg    = is_array( $parsed ) ? (string) ( $parsed['message'] ?? '' ) : '';
			if ( '' === $msg ) {
				$msg = (string) wp_remote_retrieve_body( $response );
			}
			return new WP_Error(
				'sitemap_submit_fail',
				sprintf(
					/* translators: 1: http code 2: message */
					__( 'ارسال نقشه سایت به GSC ناموفق (%1$d): %2$s', 'shojaei-seo-for-woo' ),
					$code,
					$msg
				)
			);
		}

		update_option(
			'shojaei_seo_gsc_last_sitemap_submit',
			array(
				'url'  => $sitemap_url,
				'at'   => time(),
				'code' => $code,
			),
			false
		);

		return true;
	}

	/**
	 * زمان‌بندی ارسال نقشه (debounce برای جلوگیری از اسپم API).
	 *
	 * @param int $delay ثانیه تأخیر.
	 */
	public static function schedule_sitemap_submit( int $delay = 120 ): void {
		if ( ! self::is_ready() ) {
			return;
		}
		$hook = 'shojaei_seo_gsc_submit_sitemap';
		if ( ! wp_next_scheduled( $hook ) ) {
			wp_schedule_single_event( time() + max( 30, $delay ), $hook );
		}
	}

	/**
	 * Base64 URL-safe encoding.
	 *
	 * @param string $data Raw data.
	 */
	private static function base64url( string $data ): string {
		return rtrim( strtr( base64_encode( $data ), '+/', '-_' ), '=' );
	}
}
