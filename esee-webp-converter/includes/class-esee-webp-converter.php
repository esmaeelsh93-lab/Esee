<?php
/**
 * Core image conversion logic.
 *
 * @package Esee_Automatic_WebP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Converts images handled by WordPress' upload API to WebP.
 */
final class Esee_WebP_Converter {

	/**
	 * Database option name.
	 *
	 * @var string
	 */
	const OPTION_NAME = 'esee_webp_converter_settings';

	/**
	 * Source MIME types that can safely be converted when the active editor supports them.
	 *
	 * GIF is intentionally excluded because converting it could remove animation.
	 *
	 * @var string[]
	 */
	private $convertible_mime_types = array(
		'image/jpeg',
		'image/png',
		'image/bmp',
		'image/x-ms-bmp',
		'image/avif',
		'image/heic',
		'image/heif',
		'image/heic-sequence',
		'image/heif-sequence',
	);

	/**
	 * Registers the plugin hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'wp_handle_upload', array( $this, 'convert_upload' ), 20, 2 );
		add_filter( 'image_editor_output_format', array( $this, 'configure_heic_output' ) );
		add_filter( 'upload_mimes', array( $this, 'allow_heic_uploads' ) );
	}

	/**
	 * Returns normalized settings with defaults.
	 *
	 * @return array{quality:int,keep_original:bool}
	 */
	public static function get_settings() {
		$settings = get_option( self::OPTION_NAME, array() );
		$settings = is_array( $settings ) ? $settings : array();

		return array(
			'quality'       => isset( $settings['quality'] ) ? max( 80, min( 100, absint( $settings['quality'] ) ) ) : 88,
			'keep_original' => ! empty( $settings['keep_original'] ),
		);
	}

	/**
	 * Keeps WordPress' expected JPEG fallback for HEIC/HEIF image sub-sizes.
	 *
	 * This is useful when an HEIC-capable editor is installed. It does not claim
	 * HEIC support on servers where GD or Imagick cannot read the source file.
	 *
	 * @param array<string,string> $formats Current output format mappings.
	 * @return array<string,string>
	 */
	public function configure_heic_output( $formats ) {
		$formats = is_array( $formats ) ? $formats : array();

		$formats['image/heic']          = 'image/jpeg';
		$formats['image/heif']          = 'image/jpeg';
		$formats['image/heic-sequence'] = 'image/jpeg';
		$formats['image/heif-sequence'] = 'image/jpeg';

		return $formats;
	}

	/**
	 * Allows HEIC and HEIF files through WordPress' standard upload validation.
	 *
	 * Actual conversion still depends on the server image editor supporting the
	 * source format.
	 *
	 * @param array<string,string> $mime_types Allowed extension-to-MIME mappings.
	 * @return array<string,string>
	 */
	public function allow_heic_uploads( $mime_types ) {
		$mime_types['heic'] = 'image/heic';
		$mime_types['heif'] = 'image/heif';

		return $mime_types;
	}

	/**
	 * Converts one successfully uploaded image to WebP.
	 *
	 * If conversion is unsupported or fails, the original upload response is
	 * returned unchanged so uploading never breaks.
	 *
	 * @param array<string,mixed> $upload  WordPress upload response.
	 * @param string              $context Upload context.
	 * @return array<string,mixed>
	 */
	public function convert_upload( $upload, $context = 'upload' ) {
		unset( $context );

		if ( ! empty( $upload['error'] ) || empty( $upload['file'] ) || ! is_file( $upload['file'] ) ) {
			return $upload;
		}

		$source_path = $upload['file'];
		$source_mime = wp_get_image_mime( $source_path );

		if ( ! $source_mime && ! empty( $upload['type'] ) ) {
			$source_mime = strtolower( (string) $upload['type'] );
		}

		if ( ! in_array( $source_mime, $this->convertible_mime_types, true ) ) {
			return $upload;
		}

		$editor = wp_get_image_editor( $source_path );

		if ( is_wp_error( $editor ) ) {
			$this->log_error( 'Unable to load image editor', $editor );
			return $upload;
		}

		$settings = self::get_settings();
		$quality  = $editor->set_quality( $settings['quality'] );

		if ( is_wp_error( $quality ) ) {
			$this->log_error( 'Unable to set WebP quality', $quality );
		}

		if ( method_exists( $editor, 'maybe_exif_rotate' ) ) {
			$rotation = $editor->maybe_exif_rotate();

			if ( is_wp_error( $rotation ) ) {
				$this->log_error( 'Unable to apply EXIF orientation', $rotation );
			}
		}

		$directory        = dirname( $source_path );
		$source_filename  = wp_basename( $source_path );
		$filename_stem    = pathinfo( $source_filename, PATHINFO_FILENAME );
		$webp_filename    = sanitize_file_name( $filename_stem . '.webp' );
		$unique_filename  = wp_unique_filename( $directory, $webp_filename );
		$destination_path = trailingslashit( $directory ) . $unique_filename;
		$saved            = $editor->save( $destination_path, 'image/webp' );

		if ( is_wp_error( $saved ) ) {
			$this->log_error( 'Unable to save WebP image', $saved );
			return $upload;
		}

		$saved_path = ! empty( $saved['path'] ) ? $saved['path'] : $destination_path;

		if ( ! is_file( $saved_path ) ) {
			$this->log_error( 'Image editor did not create the expected WebP file' );
			return $upload;
		}

		if ( ! $settings['keep_original'] && $source_path !== $saved_path ) {
			wp_delete_file( $source_path );
		}

		$upload['file'] = $saved_path;
		$upload['type'] = 'image/webp';

		if ( ! empty( $upload['url'] ) ) {
			$url_directory = preg_replace( '#/[^/]*$#', '', $upload['url'] );
			$upload['url'] = trailingslashit( $url_directory ) . rawurlencode( wp_basename( $saved_path ) );
		}

		return $upload;
	}

	/**
	 * Writes diagnostics only when WordPress debugging is enabled.
	 *
	 * @param string          $message Human-readable context.
	 * @param WP_Error|null   $error   Optional WordPress error.
	 * @return void
	 */
	private function log_error( $message, $error = null ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		if ( is_wp_error( $error ) ) {
			$message .= ': ' . $error->get_error_message();
		}

		error_log( 'Esee Automatic WebP: ' . $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}
}
