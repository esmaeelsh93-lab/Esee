<?php
/**
 * Cloud AI client — OpenRouter (free models) for Alt + related keywords.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_AI_Client
 */
class Shojaei_SEO_AI_Client {

	public const OPT_ENABLED   = 'shojaei_seo_ai_enabled';
	public const OPT_PROVIDER  = 'shojaei_seo_ai_provider';
	public const OPT_KEY       = 'shojaei_seo_ai_api_key';
	public const OPT_MODEL     = 'shojaei_seo_ai_model';
	public const OPT_TIMEOUT   = 'shojaei_seo_ai_timeout';
	public const OPT_HEALTH    = 'shojaei_seo_ai_last_health';

	public const PROVIDER_GROQ       = 'groq';
	public const PROVIDER_OPENROUTER = 'openrouter';
	public const PROVIDER_GEMINI     = 'gemini';

	/** Damavand relay — prefer HTTPS when certificate is ready. */
	public const RELAY_BASE_URL     = 'https://194.60.231.229';
	public const RELAY_FALLBACK_URL = 'http://194.60.231.229';

	public const OPT_RELAY_HTTPS   = 'shojaei_seo_ai_relay_https_url';
	public const OPT_RELAY_BACKUP  = 'shojaei_seo_ai_relay_backup_urls';

	public const GROQ_DIRECT_CHAT = 'https://api.groq.com/openai/v1/chat/completions';

	public const GROQ_DEFAULT_MODEL = 'llama-3.3-70b-versatile';
	public const OR_DEFAULT_MODEL   = 'meta-llama/llama-3.3-70b-instruct:free';
	public const GEMINI_DEFAULT_MODEL = 'gemini-3.6-flash';
	public const VISION_MODEL       = 'google/gemini-2.5-flash';

	public const GEMINI_API_BASE = 'https://generativelanguage.googleapis.com/v1beta';

	/**
	 * Module switch.
	 */
	public static function is_enabled(): bool {
		return 'yes' === (string) Shojaei_SEO_Helpers::get_option( self::OPT_ENABLED, 'yes' );
	}

	/**
	 * Ready to generate (enabled + API key).
	 */
	public static function is_configured(): bool {
		return self::is_enabled() && '' !== self::api_key();
	}

	/**
	 * Active providers exposed in settings.
	 *
	 * @return array<int,string>
	 */
	public static function active_providers(): array {
		return array(
			self::PROVIDER_OPENROUTER,
		);
	}

	/**
	 * Normalize stored provider (legacy groq/gemini → OpenRouter).
	 *
	 * @param string $provider Raw provider id.
	 * @return string openrouter
	 */
	public static function normalize_provider( string $provider ): string {
		unset( $provider );
		return self::PROVIDER_OPENROUTER;
	}

	/**
	 * @return string openrouter
	 */
	public static function provider(): string {
		return self::PROVIDER_OPENROUTER;
	}

	/**
	 * Ordered relay bases (HTTPS first, HTTP fallback).
	 *
	 * @return array<int,string>
	 */
	public static function relay_bases(): array {
		$custom = trim( (string) Shojaei_SEO_Helpers::get_option( self::OPT_RELAY_HTTPS, '' ) );
		$list   = array();
		if ( '' !== $custom ) {
			$list[] = untrailingslashit( self::sanitize_url( $custom ) );
		}
		$defaults = array(
			self::RELAY_BASE_URL,
			self::RELAY_FALLBACK_URL,
		);
		$backup_raw = trim( (string) Shojaei_SEO_Helpers::get_option( self::OPT_RELAY_BACKUP, '' ) );
		if ( '' !== $backup_raw ) {
			foreach ( preg_split( '/\r\n|\r|\n|,/', $backup_raw ) as $line ) {
				$line = untrailingslashit( self::sanitize_url( trim( $line ) ) );
				if ( '' !== $line ) {
					$defaults[] = $line;
				}
			}
		}
		$list = array_merge( $list, apply_filters( 'shojaei_seo_ai_relay_urls', $defaults ) );
		$out  = array();
		foreach ( $list as $u ) {
			$u = untrailingslashit( trim( (string) $u ) );
			if ( '' !== $u && ! in_array( $u, $out, true ) ) {
				$out[] = $u;
			}
		}
		return $out;
	}

	/**
	 * Relay VPS base (no trailing slash) — first reachable base.
	 */
	public static function relay_base_url(): string {
		$bases = self::relay_bases();
		return $bases ? $bases[0] : untrailingslashit( self::RELAY_FALLBACK_URL );
	}

	/**
	 * Chat completions URL for one relay base.
	 */
	public static function relay_endpoint_from_base( string $base, string $provider = '' ): string {
		$provider = '' !== $provider ? sanitize_key( $provider ) : self::provider();
		$base     = untrailingslashit( trim( $base ) );
		if ( self::PROVIDER_OPENROUTER === $provider ) {
			return $base . '/openrouter/chat/completions';
		}
		return $base . '/groq/chat/completions';
	}

	/**
	 * Nginx relay path for chat completions.
	 *
	 * @param string $provider groq|openrouter
	 */
	public static function relay_endpoint( string $provider = '' ): string {
		return self::relay_endpoint_from_base( self::relay_base_url(), $provider );
	}

	/**
	 * User BYOK key (decrypted).
	 */
	public static function api_key(): string {
		return self::decrypt_secret( trim( (string) Shojaei_SEO_Helpers::get_option( self::OPT_KEY, '' ) ) );
	}

	/**
	 * Persist API key encrypted at rest.
	 */
	public static function store_api_key( string $plain ): void {
		$plain = trim( $plain );
		if ( '' === $plain || 0 === strpos( $plain, '••••' ) ) {
			return;
		}
		update_option( self::OPT_KEY, self::encrypt_secret( $plain ), false );
	}

	/**
	 * @param string $plain Plain secret.
	 */
	private static function encrypt_secret( string $plain ): string {
		if ( '' === $plain || ! function_exists( 'openssl_encrypt' ) || ! defined( 'AUTH_KEY' ) ) {
			return $plain;
		}
		$key = hash( 'sha256', AUTH_KEY, true );
		$iv  = substr( hash( 'sha256', 'damavand_ai_iv' ), 0, 16 );
		$enc = openssl_encrypt( $plain, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		if ( false === $enc ) {
			return $plain;
		}
		return 'enc:' . base64_encode( $enc );
	}

	/**
	 * @param string $stored Stored value.
	 */
	private static function decrypt_secret( string $stored ): string {
		if ( '' === $stored || 0 !== strpos( $stored, 'enc:' ) ) {
			return $stored;
		}
		if ( ! function_exists( 'openssl_decrypt' ) || ! defined( 'AUTH_KEY' ) ) {
			return '';
		}
		$raw = base64_decode( substr( $stored, 4 ), true );
		if ( false === $raw ) {
			return '';
		}
		$key = hash( 'sha256', AUTH_KEY, true );
		$iv  = substr( hash( 'sha256', 'damavand_ai_iv' ), 0, 16 );
		$dec = openssl_decrypt( $raw, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
		return is_string( $dec ) ? $dec : '';
	}

	/**
	 * HTTP timeout seconds.
	 */
	public static function timeout( int $override = 0 ): int {
		if ( $override > 0 ) {
			return max( 15, min( 90, $override ) );
		}
		$t = (int) Shojaei_SEO_Helpers::get_option( self::OPT_TIMEOUT, 30 );
		return max( 15, min( 90, $t ?: 30 ) );
	}

	/**
	 * Preset models for settings dropdown.
	 *
	 * @return array<string,array<int,array{id:string,label:string}>>
	 */
	public static function model_presets(): array {
		return array(
			self::PROVIDER_GROQ       => array(
				array( 'id' => 'llama-3.3-70b-versatile', 'label' => 'Llama 3.3 70B (پیشنهادی)' ),
				array( 'id' => 'llama-3.1-8b-instant', 'label' => 'Llama 3.1 8B Instant' ),
				array( 'id' => 'qwen-2.5-32b', 'label' => 'Qwen 2.5 32B' ),
			),
			self::PROVIDER_OPENROUTER => array(
				array( 'id' => 'meta-llama/llama-3.3-70b-instruct:free', 'label' => 'Llama 3.3 70B — رایگان (OpenRouter)' ),
				array( 'id' => 'meta-llama/llama-3.2-3b-instruct:free', 'label' => 'Llama 3.2 3B — رایگان' ),
				array( 'id' => 'qwen/qwen-2.5-7b-instruct:free', 'label' => 'Qwen 2.5 7B — رایگان' ),
				array( 'id' => 'google/gemma-2-9b-it:free', 'label' => 'Gemma 2 9B — رایگان' ),
			),
			self::PROVIDER_GEMINI     => array(),
		);
	}

	/**
	 * Known model ids for a provider.
	 *
	 * @return array<int,string>
	 */
	public static function model_ids_for_provider( string $provider ): array {
		$provider = self::normalize_provider( $provider );
		if ( self::PROVIDER_GROQ === sanitize_key( $provider ) ) {
			$provider = self::PROVIDER_OPENROUTER;
		}
		$presets = self::model_presets();
		if ( ! isset( $presets[ $provider ] ) ) {
			return array();
		}
		$ids = array();
		foreach ( $presets[ $provider ] as $row ) {
			$ids[] = $row['id'];
		}
		return $ids;
	}

	/**
	 * Retired Gemini model ids → current replacements (Google API, 2026).
	 *
	 * @return array<string,string>
	 */
	public static function retired_gemini_models(): array {
		return array(
			'gemini-2.0-flash'      => 'gemini-3.6-flash',
			'gemini-2.0-flash-lite' => 'gemini-3.5-flash-lite',
			'gemini-1.5-flash'      => 'gemini-3.6-flash',
			'gemini-1.5-pro'        => 'gemini-3.6-flash',
			'gemini-1.5-flash-8b'   => 'gemini-3.5-flash-lite',
		);
	}

	/**
	 * Map legacy / cross-provider model id to the active provider.
	 */
	public static function map_model_to_provider( string $model, string $provider ): string {
		$model    = trim( $model );
		$provider = self::normalize_provider( $provider );

		if ( '' === $model || '__custom__' === $model ) {
			return self::OR_DEFAULT_MODEL;
		}

		$legacy = array(
			'llama-3.3-70b-versatile'           => self::OR_DEFAULT_MODEL,
			'llama-3.1-8b-instant'              => 'meta-llama/llama-3.2-3b-instruct:free',
			'qwen-2.5-32b'                      => 'qwen/qwen-2.5-7b-instruct:free',
			'meta-llama/llama-3.3-70b-instruct' => self::OR_DEFAULT_MODEL,
			'meta-llama/llama-3.1-8b-instruct'  => 'meta-llama/llama-3.2-3b-instruct:free',
			'qwen/qwen-2.5-32b-instruct'        => 'qwen/qwen-2.5-7b-instruct:free',
			'qwen/qwen3-32b'                    => 'qwen/qwen-2.5-7b-instruct:free',
			'qwen/qwen-2.5-7b-instruct'         => 'qwen/qwen-2.5-7b-instruct:free',
			'qwen/qwen-2.5-72b-instruct'        => self::OR_DEFAULT_MODEL,
			'groq/llama-3.3-70b-versatile'      => self::OR_DEFAULT_MODEL,
		);

		if ( isset( $legacy[ $model ] ) ) {
			return $legacy[ $model ];
		}

		if ( preg_match( '#^gemini-#i', $model ) || preg_match( '#^models/gemini-#i', $model ) ) {
			return self::OR_DEFAULT_MODEL;
		}

		if ( str_contains( $model, '/' ) ) {
			if ( str_ends_with( $model, ':free' ) ) {
				return $model;
			}
			$with_free = $model . ':free';
			if ( in_array( $with_free, self::model_ids_for_provider( self::PROVIDER_OPENROUTER ), true ) ) {
				return $with_free;
			}
			return self::OR_DEFAULT_MODEL;
		}

		return self::OR_DEFAULT_MODEL;
	}

	/**
	 * Active model id (always valid for current provider).
	 */
	public static function model(): string {
		$saved    = trim( (string) Shojaei_SEO_Helpers::get_option( self::OPT_MODEL, '' ) );
		$provider = self::provider();
		$resolved = self::map_model_to_provider( $saved, $provider );
		if ( $resolved !== $saved ) {
			update_option( self::OPT_MODEL, $resolved, false );
		}
		return $resolved;
	}

	/**
	 * Persist provider + model from settings form values.
	 *
	 * @param string $provider openrouter|gemini
	 * @param string $model    Model id.
	 */
	public static function save_connection_settings( string $provider, string $model ): void {
		$provider = self::normalize_provider( $provider );
		$model    = self::map_model_to_provider( trim( $model ), $provider );
		update_option( self::OPT_PROVIDER, $provider, false );
		update_option( self::OPT_MODEL, $model, false );
	}

	/**
	 * Route by configured provider and optional API key prefix (sk-or- / gsk_).
	 */
	public static function route_from_api_key( string $api_key = '' ): string {
		unset( $api_key );
		return self::PROVIDER_OPENROUTER;
	}

	/**
	 * Min tokens for connection test (thinking models need headroom).
	 */
	public static function test_max_tokens( string $model = '' ): int {
		$model = '' !== $model ? $model : self::model();
		if ( preg_match( '/qwen3|thinking|reason/i', $model ) ) {
			return 256;
		}
		return 128;
	}

	/**
	 * @param string $url Raw URL.
	 */
	public static function sanitize_url( string $url ): string {
		$url = trim( wp_strip_all_tags( $url ) );
		$url = str_replace( array( "\r", "\n", "\t" ), '', $url );
		if ( ! preg_match( '#^https?://#i', $url ) ) {
			return '';
		}
		return esc_url_raw( $url );
	}

	/**
	 * Chat completion (returns assistant text).
	 *
	 * @param string               $prompt  User prompt.
	 * @param array<string,mixed>  $opts    max_tokens, timeout, temperature, system.
	 * @return string|WP_Error
	 */
	public static function chat( string $prompt, array $opts = array() ) {
		if ( ! self::is_enabled() ) {
			return new WP_Error( 'ai_off', __( 'موتور تولید محتوا خاموش است.', 'shojaei-seo-for-woo' ) );
		}
		$key = self::api_key();
		if ( '' === $key ) {
			return new WP_Error( 'ai_no_key', __( 'کلید API وارد نشده. از تنظیمات سئو دماوند کلید OpenRouter را ذخیره کنید.', 'shojaei-seo-for-woo' ) );
		}

		$system = isset( $opts['system'] ) ? (string) $opts['system'] : self::default_system_prompt();
		$model  = isset( $opts['model'] ) && '' !== trim( (string) $opts['model'] ) ? trim( (string) $opts['model'] ) : self::model();
		$opts   = self::adjust_opts_for_provider( $opts, $model );
		$body   = array(
			'model'       => $model,
			'messages'    => array(
				array(
					'role'    => 'system',
					'content' => $system,
				),
				array(
					'role'    => 'user',
					'content' => $prompt,
				),
			),
			'temperature' => isset( $opts['temperature'] ) ? max( 0.0, min( 1.2, (float) $opts['temperature'] ) ) : 0.3,
			'stream'      => false,
		);
		if ( ! empty( $opts['max_tokens'] ) ) {
			$body['max_tokens'] = max( 32, min( 8192, (int) $opts['max_tokens'] ) );
		}
		if ( ! empty( $opts['response_mime'] ) ) {
			$body['response_mime'] = sanitize_text_field( (string) $opts['response_mime'] );
		}

		$timeout  = self::timeout( (int) ( $opts['timeout'] ?? 0 ) );
		$response = self::post_chat( $body, $key, $timeout );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$text = self::extract_message_text( $response );
		if ( '' === $text ) {
			return new WP_Error( 'ai_empty', __( 'پاسخ سرور خالی بود. مدل یا کلید را بررسی کنید.', 'shojaei-seo-for-woo' ) );
		}
		return $text;
	}

	/**
	 * Gemini 3.x uses internal thinking that shares maxOutputTokens — tune for SEO tasks.
	 *
	 * @param array<string,mixed> $opts  Request opts.
	 * @param string              $model Model id.
	 * @return array<string,mixed>
	 */
	public static function adjust_opts_for_provider( array $opts, string $model = '' ): array {
		if ( self::PROVIDER_GEMINI !== self::provider() ) {
			return $opts;
		}
		$model = '' !== $model ? $model : self::model();
		if ( ! empty( $opts['timeout'] ) ) {
			$opts['timeout'] = min( 180, (int) $opts['timeout'] + 25 );
		}
		if ( self::is_gemini_thinking_model( $model ) && ! empty( $opts['max_tokens'] ) ) {
			$floor = 768;
			if ( (int) $opts['max_tokens'] >= 2000 ) {
				$floor = 4096;
			} elseif ( (int) $opts['max_tokens'] >= 800 ) {
				$floor = 1536;
			}
			$opts['max_tokens'] = max( (int) $opts['max_tokens'], $floor );
		}
		return $opts;
	}

	/**
	 * Whether model belongs to Gemini 3.x family (thinking enabled by default).
	 */
	public static function is_gemini_thinking_model( string $model ): bool {
		$model = preg_replace( '#^models/#', '', trim( $model ) );
		return (bool) preg_match( '#^gemini-3(?:\.|\-|/|$)#i', $model );
	}

	/**
	 * Local image path for vision (prefer medium if original is large).
	 */
	public static function attachment_path_for_vision( int $attachment_id ): string {
		$path = get_attached_file( $attachment_id );
		if ( ! $path || ! is_readable( $path ) ) {
			return '';
		}
		if ( filesize( $path ) <= 900000 ) {
			return $path;
		}
		foreach ( array( 'woocommerce_single', 'medium_large', 'medium' ) as $size ) {
			$inter = image_get_intermediate_size( $attachment_id, $size );
			if ( ! $inter || empty( $inter['path'] ) ) {
				continue;
			}
			$upload    = wp_upload_dir();
			$candidate = path_join( $upload['basedir'], $inter['path'] );
			if ( is_readable( $candidate ) ) {
				return $candidate;
			}
		}
		return $path;
	}

	/**
	 * Data URL for vision APIs.
	 */
	public static function attachment_image_data_url( int $attachment_id ): string {
		$path = self::attachment_path_for_vision( $attachment_id );
		if ( '' === $path ) {
			return '';
		}
		$mime = wp_check_filetype( $path );
		$type = ! empty( $mime['type'] ) ? (string) $mime['type'] : 'image/jpeg';
		if ( 0 !== strpos( $type, 'image/' ) ) {
			return '';
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$bytes = file_get_contents( $path );
		if ( false === $bytes || '' === $bytes ) {
			return '';
		}
		return 'data:' . $type . ';base64,' . base64_encode( $bytes );
	}

	/**
	 * Vision chat — describe attachment image (OpenRouter multimodal).
	 *
	 * @param string               $prompt        User prompt.
	 * @param int                  $attachment_id Attachment ID.
	 * @param array<string,mixed>  $opts          max_tokens, timeout, temperature, vision_model.
	 * @return string|WP_Error
	 */
	public static function chat_with_image( string $prompt, int $attachment_id, array $opts = array() ) {
		if ( ! self::is_enabled() ) {
			return new WP_Error( 'ai_off', __( 'موتور تولید محتوا خاموش است.', 'shojaei-seo-for-woo' ) );
		}
		$key = self::api_key();
		if ( '' === $key ) {
			return new WP_Error( 'ai_no_key', __( 'کلید API وارد نشده.', 'shojaei-seo-for-woo' ) );
		}
		if ( self::PROVIDER_OPENROUTER !== self::route_from_api_key( $key ) ) {
			return new WP_Error( 'vision_key', __( 'تحلیل تصویر نیاز به کلید OpenRouter (sk-or-) دارد.', 'shojaei-seo-for-woo' ) );
		}

		$data_url = self::attachment_image_data_url( $attachment_id );
		if ( '' === $data_url ) {
			return new WP_Error( 'no_image', __( 'فایل تصویر خوانده نشد.', 'shojaei-seo-for-woo' ) );
		}

		$model = isset( $opts['vision_model'] ) ? trim( (string) $opts['vision_model'] ) : self::VISION_MODEL;
		$body  = array(
			'model'       => $model,
			'messages'    => array(
				array(
					'role'    => 'user',
					'content' => array(
						array(
							'type' => 'text',
							'text' => $prompt,
						),
						array(
							'type'      => 'image_url',
							'image_url' => array( 'url' => $data_url ),
						),
					),
				),
			),
			'temperature' => isset( $opts['temperature'] ) ? max( 0.0, min( 1.0, (float) $opts['temperature'] ) ) : 0.2,
			'stream'      => false,
		);
		if ( ! empty( $opts['max_tokens'] ) ) {
			$body['max_tokens'] = max( 32, min( 512, (int) $opts['max_tokens'] ) );
		}

		$timeout  = self::timeout( (int) ( $opts['timeout'] ?? 0 ) );
		$response = self::post_chat( $body, $key, $timeout );
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$text = self::extract_message_text( $response );
		if ( '' === $text ) {
			return new WP_Error( 'ai_empty', __( 'پاسخ تحلیل تصویر خالی بود.', 'shojaei-seo-for-woo' ) );
		}
		return $text;
	}

	/**
	 * Connection test with latency.
	 *
	 * @param array<string,mixed> $overrides Unsaved form values.
	 * @return array<string,mixed>|WP_Error
	 */
	public static function test_connection( array $overrides = array() ) {
		$prev = self::apply_overrides( $overrides );
		$t0   = microtime( true );
		$out  = self::chat(
			'Reply with exactly one word: ok',
			array(
				'max_tokens'  => self::test_max_tokens(),
				'timeout'     => 45,
				'temperature' => 0,
			)
		);
		$ms = (int) round( ( microtime( true ) - $t0 ) * 1000 );
		self::restore_overrides( $prev );

		if ( is_wp_error( $out ) ) {
			return $out;
		}

		return array(
			'ok'       => true,
			'message'  => __( 'اتصال برقرار شد.', 'shojaei-seo-for-woo' ),
			'latency'  => $ms,
			'provider' => self::provider(),
			'model'    => self::model(),
			'endpoint' => self::connection_endpoint(),
			'sample'   => mb_substr( trim( $out ), 0, 80 ),
		);
	}

	/**
	 * Human-readable endpoint label for health checks (no secrets).
	 */
	public static function connection_endpoint(): string {
		$route = self::provider();
		if ( self::PROVIDER_GEMINI === $route ) {
			return self::gemini_endpoint( self::model() );
		}
		return self::relay_endpoint( $route );
	}

	/**
	 * Gemini generateContent URL for a model id.
	 */
	public static function gemini_endpoint( string $model ): string {
		$model = preg_replace( '#^models/#', '', trim( $model ) );
		if ( '' === $model ) {
			$model = self::GEMINI_DEFAULT_MODEL;
		}
		return self::GEMINI_API_BASE . '/models/' . rawurlencode( $model ) . ':generateContent';
	}

	/**
	 * POST OpenAI-compatible chat.
	 *
	 * @param array<string,mixed> $body    Chat body.
	 * @param string              $api_key Key.
	 * @param int                 $timeout Seconds.
	 * @return array<string,mixed>|WP_Error Decoded JSON.
	 */
	public static function post_chat( array $body, string $api_key, int $timeout ) {
		$route = self::route_from_api_key( $api_key );
		$model = isset( $body['model'] ) ? (string) $body['model'] : self::model();
		$body['model'] = self::map_model_to_provider( $model, $route );

		if ( self::PROVIDER_GEMINI === $route ) {
			return self::post_gemini_chat( $body, $api_key, $timeout );
		}

		$json = wp_json_encode( $body );
		if ( false === $json ) {
			return new WP_Error( 'ai_json', __( 'ساخت JSON درخواست ناموفق بود.', 'shojaei-seo-for-woo' ) );
		}

		$headers    = self::chat_headers( $route, $api_key );
		$urls       = self::chat_urls( $route );
		$last_error = null;

		foreach ( $urls as $url ) {
			$raw = self::http_post( $url, $json, $headers, $timeout );
			if ( is_wp_error( $raw ) ) {
				$last_error = $raw;
				continue;
			}

			$code = (int) $raw['code'];
			$data = json_decode( (string) $raw['body'], true );
			if ( $code >= 200 && $code < 300 && is_array( $data ) ) {
				return $data;
			}

			$msg        = self::http_error_message( $code, is_array( $data ) ? $data : array(), (string) $raw['body'], $route );
			$last_error = new WP_Error( 'ai_http', $msg );

			if ( self::is_relay_url( $url ) ) {
				continue;
			}
			if ( self::PROVIDER_GROQ === $route && 403 === $code ) {
				continue;
			}
			break;
		}

		return $last_error instanceof WP_Error
			? $last_error
			: new WP_Error( 'ai_http', __( 'اتصال برقرار نشد.', 'shojaei-seo-for-woo' ) );
	}

	/**
	 * POST Gemini generateContent (direct — no relay).
	 *
	 * @param array<string,mixed> $body    OpenAI-style chat body.
	 * @param string              $api_key Auth key from Google AI Studio.
	 * @param int                 $timeout Seconds.
	 * @return array<string,mixed>|WP_Error Normalized OpenAI-style JSON.
	 */
	private static function post_gemini_chat( array $body, string $api_key, int $timeout ) {
		$model   = isset( $body['model'] ) ? (string) $body['model'] : self::GEMINI_DEFAULT_MODEL;
		$payload = self::gemini_body_from_chat( $body );
		$json    = wp_json_encode( $payload );
		if ( false === $json ) {
			return new WP_Error( 'ai_json', __( 'ساخت JSON درخواست ناموفق بود.', 'shojaei-seo-for-woo' ) );
		}

		$url  = self::gemini_endpoint( $model );
		$raw  = self::http_post( $url, $json, self::gemini_headers( $api_key ), $timeout );
		if ( is_wp_error( $raw ) ) {
			return $raw;
		}

		$code = (int) $raw['code'];
		$data = json_decode( (string) $raw['body'], true );
		if ( $code >= 200 && $code < 300 && is_array( $data ) ) {
			return self::normalize_gemini_response( $data );
		}

		return new WP_Error(
			'ai_http',
			self::http_error_message( $code, is_array( $data ) ? $data : array(), (string) $raw['body'], self::PROVIDER_GEMINI )
		);
	}

	/**
	 * @return array<string,string>
	 */
	private static function gemini_headers( string $api_key ): array {
		return array(
			'Content-Type'  => 'application/json; charset=utf-8',
			'Accept'        => 'application/json',
			'x-goog-api-key' => $api_key,
		);
	}

	/**
	 * Convert OpenAI chat body to Gemini generateContent payload.
	 *
	 * @param array<string,mixed> $body Chat body.
	 * @return array<string,mixed>
	 */
	private static function gemini_body_from_chat( array $body ): array {
		$system = '';
		$user   = '';
		foreach ( $body['messages'] ?? array() as $msg ) {
			if ( ! is_array( $msg ) ) {
				continue;
			}
			$role    = isset( $msg['role'] ) ? (string) $msg['role'] : '';
			$content = $msg['content'] ?? '';
			$text    = is_string( $content ) ? $content : '';
			if ( 'system' === $role ) {
				$system = $text;
			} elseif ( 'user' === $role && '' !== $text ) {
				$user = $text;
			} elseif ( 'assistant' === $role && '' === $user && '' !== $text ) {
				$user = $text;
			}
		}

		$out = array(
			'contents' => array(
				array(
					'role'  => 'user',
					'parts' => array(
						array( 'text' => $user ),
					),
				),
			),
		);
		if ( '' !== $system ) {
			$out['systemInstruction'] = array(
				'parts' => array(
					array( 'text' => $system ),
				),
			);
		}

		$gen = array();
		$model = isset( $body['model'] ) ? (string) $body['model'] : self::GEMINI_DEFAULT_MODEL;
		if ( self::is_gemini_thinking_model( $model ) ) {
			$gen['thinkingConfig'] = array(
				'thinkingLevel' => 'MINIMAL',
			);
		}
		if ( isset( $body['temperature'] ) && ! self::is_gemini_thinking_model( $model ) ) {
			$gen['temperature'] = max( 0.0, min( 2.0, (float) $body['temperature'] ) );
		}
		if ( ! empty( $body['max_tokens'] ) ) {
			$gen['maxOutputTokens'] = max( 64, min( 8192, (int) $body['max_tokens'] ) );
		}
		if ( ! empty( $body['response_mime'] ) ) {
			$gen['responseMimeType'] = (string) $body['response_mime'];
		}
		if ( $gen ) {
			$out['generationConfig'] = $gen;
		}
		return $out;
	}

	/**
	 * Map Gemini JSON to OpenAI-style response for extract_message_text().
	 *
	 * @param array<string,mixed> $data Gemini API response.
	 * @return array<string,mixed>
	 */
	private static function normalize_gemini_response( array $data ): array {
		return array(
			'choices' => array(
				array(
					'message' => array(
						'content' => self::extract_gemini_text( $data ),
					),
				),
			),
		);
	}

	/**
	 * @param array<string,mixed> $data Gemini API response.
	 */
	private static function extract_gemini_text( array $data ): string {
		if ( empty( $data['candidates'] ) || ! is_array( $data['candidates'] ) ) {
			return '';
		}
		foreach ( $data['candidates'] as $candidate ) {
			if ( ! is_array( $candidate ) || empty( $candidate['content']['parts'] ) || ! is_array( $candidate['content']['parts'] ) ) {
				continue;
			}
			$parts = array();
			foreach ( $candidate['content']['parts'] as $part ) {
				if ( ! is_array( $part ) ) {
					continue;
				}
				if ( ! empty( $part['thought'] ) ) {
					continue;
				}
				if ( isset( $part['text'] ) && is_string( $part['text'] ) && '' !== trim( $part['text'] ) ) {
					$parts[] = trim( $part['text'] );
				}
			}
			if ( $parts ) {
				return trim( implode( "\n", $parts ) );
			}
		}
		return '';
	}

	/**
	 * @return array<string,string>
	 */
	private static function chat_headers( string $route, string $api_key ): array {
		$headers = array(
			'Content-Type'    => 'application/json; charset=utf-8',
			'Accept'          => 'application/json',
			'Authorization'   => 'Bearer ' . $api_key,
			'X-Site-Domain'   => home_url( '/' ),
		);
		if ( self::PROVIDER_OPENROUTER === $route ) {
			$headers['HTTP-Referer'] = home_url( '/' );
			$headers['X-Title']      = wp_strip_all_tags( get_bloginfo( 'name' ) ) . ' — Damavand SEO';
		}
		return $headers;
	}

	/**
	 * @return array<int,string>
	 */
	private static function chat_urls( string $route ): array {
		$urls = array();
		foreach ( self::relay_bases() as $base ) {
			$urls[] = self::relay_endpoint_from_base( $base, $route );
		}
		if ( self::PROVIDER_GROQ === $route ) {
			$urls[] = self::GROQ_DIRECT_CHAT;
		}
		return $urls;
	}

	/**
	 * Whether URL is an allowed Damavand relay endpoint.
	 */
	private static function is_relay_url( string $url ): bool {
		$url = self::sanitize_url( $url );
		if ( '' === $url ) {
			return false;
		}
		foreach ( self::relay_bases() as $base ) {
			if ( 0 === strpos( $url, $base ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * @param array<string,mixed> $data API JSON.
	 */
	public static function extract_message_text( array $data ): string {
		$choice = isset( $data['choices'][0] ) && is_array( $data['choices'][0] ) ? $data['choices'][0] : array();
		$msg    = isset( $choice['message'] ) && is_array( $choice['message'] ) ? $choice['message'] : array();

		if ( isset( $msg['content'] ) ) {
			$content = $msg['content'];
			if ( is_string( $content ) && '' !== trim( $content ) ) {
				return trim( $content );
			}
			if ( is_array( $content ) ) {
				$parts = array();
				foreach ( $content as $part ) {
					if ( is_array( $part ) && isset( $part['text'] ) ) {
						$parts[] = (string) $part['text'];
					} elseif ( is_string( $part ) ) {
						$parts[] = $part;
					}
				}
				if ( $parts ) {
					return trim( implode( "\n", $parts ) );
				}
			}
		}

		if ( ! empty( $msg['reasoning'] ) && is_string( $msg['reasoning'] ) ) {
			return trim( $msg['reasoning'] );
		}
		if ( isset( $choice['text'] ) && is_string( $choice['text'] ) ) {
			return trim( $choice['text'] );
		}
		if ( isset( $data['response'] ) ) {
			return trim( (string) $data['response'] );
		}
		if ( isset( $data['text'] ) ) {
			return trim( (string) $data['text'] );
		}
		return '';
	}

	/**
	 * @param string $text Raw LLM output.
	 */
	public static function clean_html( string $text ): string {
		$text = trim( $text );
		$text = preg_replace( '/^```(?:html)?\s*/i', '', $text );
		$text = preg_replace( '/\s*```\s*$/', '', $text );
		return trim( wp_kses_post( $text ) );
	}

	/**
	 * @param string $text Raw.
	 * @return array<string,mixed>
	 */
	public static function extract_json( string $text ): array {
		$text = trim( $text );
		if ( preg_match( '/```(?:json)?\s*([\s\S]*?)```/', $text, $m ) ) {
			$text = trim( $m[1] );
		}
		$start = strpos( $text, '{' );
		$end   = strrpos( $text, '}' );
		if ( false !== $start && false !== $end && $end > $start ) {
			$decoded = json_decode( substr( $text, $start, $end - $start + 1 ), true );
			if ( is_array( $decoded ) ) {
				return $decoded;
			}
		}
		return array();
	}

	/**
	 * @param int $seed Post ID.
	 */
	public static function style_hint( int $seed = 0 ): string {
		unset( $seed );
		return '';
	}

	/**
	 * System prompt for Persian SEO.
	 */
	public static function default_system_prompt(): string {
		return 'تو دستیار سئو فروشگاه ایرانی هستی. فقط Alt فارسی تصویر یا JSON کلمات مرتبط تولید کن. '
			. 'خروجی دقیق، کوتاه و فارسی. بدون توضیح اضافه و بدون markdown.';
	}

	/**
	 * @param string               $url     URL.
	 * @param string               $json    Body.
	 * @param array<string,string> $headers Headers.
	 * @param int                  $timeout Seconds.
	 * @return array{code:int,body:string}|WP_Error
	 */
	private static function http_post( string $url, string $json, array $headers, int $timeout ) {
		$url = self::sanitize_url( $url );
		if ( '' === $url ) {
			return new WP_Error( 'ai_url', __( 'آدرس اتصال نامعتبر است.', 'shojaei-seo-for-woo' ) );
		}

		$header_lines = array();
		foreach ( $headers as $k => $v ) {
			$header_lines[] = $k . ': ' . $v;
		}

		if ( function_exists( 'curl_init' ) ) {
			$ch = curl_init();
			if ( false !== $ch ) {
				$curl_opts = array(
					CURLOPT_URL            => $url,
					CURLOPT_POST           => true,
					CURLOPT_POSTFIELDS     => $json,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_TIMEOUT        => $timeout,
					CURLOPT_CONNECTTIMEOUT => 12,
					CURLOPT_HTTPHEADER     => $header_lines,
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_MAXREDIRS      => 3,
				);
				if ( 0 === strpos( $url, 'https://' ) && self::is_relay_url( $url ) ) {
					$sslverify = (bool) apply_filters( 'shojaei_seo_ai_relay_sslverify', false, $url );
					$curl_opts[ CURLOPT_SSL_VERIFYPEER ] = $sslverify;
					$curl_opts[ CURLOPT_SSL_VERIFYHOST ] = $sslverify ? 2 : 0;
				}
				curl_setopt_array( $ch, $curl_opts );
				$body     = curl_exec( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_exec
				$curl_err = curl_error( $ch );
				$code     = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
				curl_close( $ch ); // phpcs:ignore WordPress.WP.AlternativeFunctions.curl_curl_close
				if ( false !== $body ) {
					return array(
						'code' => $code,
						'body' => (string) $body,
					);
				}
				if ( $curl_err ) {
					return new WP_Error( 'ai_curl', self::friendly_transport_error( $curl_err ) );
				}
			}
		}

		$wp_headers = array();
		foreach ( $headers as $k => $v ) {
			$wp_headers[ $k ] = $v;
		}
		$args = array(
			'timeout'  => $timeout,
			'headers'  => $wp_headers,
			'body'     => $json,
		);
		if ( ! self::is_relay_url( $url ) ) {
			$args['reject_unsafe_urls'] = true;
		}
		if ( 0 === strpos( $url, 'https://' ) && self::is_relay_url( $url ) ) {
			$args['sslverify'] = (bool) apply_filters( 'shojaei_seo_ai_relay_sslverify', false, $url );
		}
		$response = wp_remote_post( $url, $args );
		if ( is_wp_error( $response ) ) {
			return new WP_Error( 'ai_http', self::friendly_transport_error( $response->get_error_message() ) );
		}
		return array(
			'code' => (int) wp_remote_retrieve_response_code( $response ),
			'body' => (string) wp_remote_retrieve_body( $response ),
		);
	}

	/**
	 * @param string $raw Transport error.
	 */
	private static function friendly_transport_error( string $raw ): string {
		if ( preg_match( '/timed out|timeout/i', $raw ) ) {
			return __( 'پاسخ در زمان مجاز نرسید. چند لحظه بعد دوباره تلاش کنید.', 'shojaei-seo-for-woo' );
		}
		if ( preg_match( '/Could not resolve|Failed to connect|Connection refused/i', $raw ) ) {
			return __( 'اتصال به سرور برقرار نشد. دسترسی هاست به اینترنت را بررسی کنید.', 'shojaei-seo-for-woo' );
		}
		return sprintf(
			/* translators: %s: error */
			__( 'خطای شبکه: %s', 'shojaei-seo-for-woo' ),
			$raw
		);
	}

	/**
	 * @param int                  $code HTTP.
	 * @param array<string,mixed>  $data JSON.
	 * @param string               $raw  Body.
	 */
	private static function http_error_message( int $code, array $data, string $raw, string $route = '' ): string {
		$route = '' !== $route ? sanitize_key( $route ) : self::provider();
		$api   = '';
		if ( isset( $data['error']['message'] ) ) {
			$api = (string) $data['error']['message'];
		} elseif ( isset( $data['message'] ) ) {
			$api = (string) $data['message'];
		}
		$status = isset( $data['error']['status'] ) ? (string) $data['error']['status'] : '';
		if ( 401 === $code || ( self::PROVIDER_GEMINI === $route && 'UNAUTHENTICATED' === $status ) ) {
			if ( self::PROVIDER_GEMINI === $route ) {
				return __( 'کلید Gemini نامعتبر است. کلید را از Google AI Studio بگیرید و دوباره ذخیره کنید.', 'shojaei-seo-for-woo' );
			}
			return __( 'کلید API نامعتبر است. Provider و کلید را در تنظیمات بررسی کنید.', 'shojaei-seo-for-woo' );
		}
		if ( 403 === $code || ( self::PROVIDER_GEMINI === $route && 'PERMISSION_DENIED' === $status ) ) {
			if ( self::PROVIDER_GEMINI === $route ) {
				return __( 'دسترسی Gemini رد شد. کلید API یا محدودیت Free Tier را در Google AI Studio بررسی کنید.', 'shojaei-seo-for-woo' );
			}
			if ( self::PROVIDER_GROQ === self::provider() ) {
				return __( 'Groq از IP سرور Relay دسترسی را مسدود کرده (403). OpenRouter را انتخاب کنید یا کلید OpenRouter بگیرید — همان مدل Llama روی OpenRouter موجود است.', 'shojaei-seo-for-woo' );
			}
			return __( 'سرور واسط درخواست را رد کرد (403). پیکربندی Relay را بررسی کنید.', 'shojaei-seo-for-woo' );
		}
		if ( 429 === $code || 'RESOURCE_EXHAUSTED' === $status ) {
			if ( self::PROVIDER_GEMINI === $route ) {
				return __( 'سقف Free Tier Gemini پر شده. چند دقیقه صبر کنید یا مدل سبک‌تر (Flash Lite) انتخاب کنید.', 'shojaei-seo-for-woo' );
			}
			return __( 'سقف رایگان درخواست پر شده. چند دقیقه صبر کنید یا مدل سبک‌تر انتخاب کنید.', 'shojaei-seo-for-woo' );
		}
		if ( 404 === $code && self::PROVIDER_GEMINI === $route ) {
			if ( preg_match( '/no longer available|deprecated/i', $api ) ) {
				return sprintf(
					/* translators: %s: current default model id */
					__( 'مدل Gemini انتخاب‌شده منسوخ شده. افزونه را به‌روز کنید یا مدل «%s» را انتخاب کنید.', 'shojaei-seo-for-woo' ),
					self::GEMINI_DEFAULT_MODEL
				);
			}
			return __( 'مدل Gemini پیدا نشد. از لیست مدل‌های افزونه یک مدل جدید انتخاب کنید.', 'shojaei-seo-for-woo' );
		}
		if ( self::PROVIDER_GEMINI === $route && 'INVALID_ARGUMENT' === $status ) {
			return __( 'درخواست Gemini نامعتبر است. مدل انتخاب‌شده یا محتوای درخواست را بررسی کنید.', 'shojaei-seo-for-woo' );
		}
		if ( $api ) {
			return sprintf(
				/* translators: 1: status, 2: api message */
				__( 'خطای سرور (%1$d): %2$s', 'shojaei-seo-for-woo' ),
				$code,
				mb_substr( $api, 0, 220 )
			);
		}
		return sprintf(
			/* translators: 1: status, 2: snippet */
			__( 'خطای سرور (%1$d): %2$s', 'shojaei-seo-for-woo' ),
			$code,
			mb_substr( wp_strip_all_tags( $raw ), 0, 160 )
		);
	}

	/**
	 * Temporarily apply unsaved form overrides.
	 *
	 * @param array<string,mixed> $overrides Overrides.
	 * @return array<string,string> Previous option snapshots.
	 */
	private static function apply_overrides( array $overrides ): array {
		$map = array(
			'provider' => self::OPT_PROVIDER,
			'api_key'  => self::OPT_KEY,
			'model'    => self::OPT_MODEL,
		);
		$prev = array();
		foreach ( $map as $k => $opt ) {
			if ( ! array_key_exists( $k, $overrides ) ) {
				continue;
			}
			$prev[ $opt ] = (string) get_option( $opt, '' );
			$val          = is_string( $overrides[ $k ] ) ? $overrides[ $k ] : '';
			if ( 'api_key' === $k && '' === trim( $val ) ) {
				continue;
			}
			if ( 'model' === $k ) {
				$prov = isset( $overrides['provider'] )
					? sanitize_key( (string) $overrides['provider'] )
					: self::provider();
				$val = self::map_model_to_provider( $val, $prov );
			}
			if ( 'provider' === $k ) {
				$val = self::normalize_provider( $val );
			}
			update_option( $opt, sanitize_text_field( $val ), false );
		}
		return $prev;
	}

	/**
	 * @param array<string,string> $prev Snapshot.
	 */
	private static function restore_overrides( array $prev ): void {
		foreach ( $prev as $opt => $val ) {
			update_option( $opt, $val, false );
		}
	}
}
