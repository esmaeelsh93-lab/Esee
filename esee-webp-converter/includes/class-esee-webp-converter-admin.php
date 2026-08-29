<?php
/**
 * Admin settings for Esee Automatic WebP.
 *
 * @package Esee_Automatic_WebP
 */

defined( 'ABSPATH' ) || exit;

/**
 * Registers and renders the Media settings section.
 */
final class Esee_WebP_Converter_Admin {

	/**
	 * Registers admin hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_notices', array( $this, 'show_support_notice' ) );
	}

	/**
	 * Registers the options under Settings > Media.
	 *
	 * @return void
	 */
	public function register_settings() {
		register_setting(
			'media',
			Esee_WebP_Converter::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(
					'quality'       => 88,
					'keep_original' => false,
				),
			)
		);

		add_settings_section(
			'esee_webp_converter',
			__( 'Esee Automatic WebP', 'esee-webp-converter' ),
			array( $this, 'render_section' ),
			'media'
		);

		add_settings_field(
			'esee_webp_quality',
			__( 'WebP quality', 'esee-webp-converter' ),
			array( $this, 'render_quality_field' ),
			'media',
			'esee_webp_converter'
		);

		add_settings_field(
			'esee_webp_keep_original',
			__( 'Original image', 'esee-webp-converter' ),
			array( $this, 'render_keep_original_field' ),
			'media',
			'esee_webp_converter'
		);
	}

	/**
	 * Normalizes submitted settings.
	 *
	 * @param mixed $input Raw settings.
	 * @return array{quality:int,keep_original:bool}
	 */
	public function sanitize_settings( $input ) {
		$input = is_array( $input ) ? $input : array();

		return array(
			'quality'       => isset( $input['quality'] ) ? max( 80, min( 100, absint( $input['quality'] ) ) ) : 88,
			'keep_original' => ! empty( $input['keep_original'] ),
		);
	}

	/**
	 * Describes plugin behavior and current support.
	 *
	 * @return void
	 */
	public function render_section() {
		$webp_supported = wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) );
		$status          = $webp_supported
			? __( 'WebP output is available on this server.', 'esee-webp-converter' )
			: __( 'WebP output is not available in the active WordPress image editor.', 'esee-webp-converter' );

		echo '<p>' . esc_html__( 'JPEG, PNG, BMP, AVIF, and server-supported HEIC/HEIF uploads are converted through the standard WordPress media pipeline. Animated GIF and existing WebP files are left unchanged.', 'esee-webp-converter' ) . '</p>';
		echo '<p><strong>' . esc_html( $status ) . '</strong></p>';
		echo '<p>' . esc_html__( 'Ghostscript is not required; it is only relevant to PDF thumbnail generation.', 'esee-webp-converter' ) . '</p>';
	}

	/**
	 * Renders quality control.
	 *
	 * @return void
	 */
	public function render_quality_field() {
		$settings = Esee_WebP_Converter::get_settings();
		$name     = Esee_WebP_Converter::OPTION_NAME . '[quality]';

		printf(
			'<input name="%1$s" type="number" min="80" max="100" step="1" value="%2$d" class="small-text" />',
			esc_attr( $name ),
			(int) $settings['quality']
		);
		echo '<p class="description">' . esc_html__( '88 is a high-quality default with a useful reduction in file size.', 'esee-webp-converter' ) . '</p>';
	}

	/**
	 * Renders original-file retention control.
	 *
	 * @return void
	 */
	public function render_keep_original_field() {
		$settings = Esee_WebP_Converter::get_settings();
		$name     = Esee_WebP_Converter::OPTION_NAME . '[keep_original]';

		printf(
			'<label><input name="%1$s" type="checkbox" value="1" %2$s /> %3$s</label>',
			esc_attr( $name ),
			checked( $settings['keep_original'], true, false ),
			esc_html__( 'Keep the original file beside the converted WebP file.', 'esee-webp-converter' )
		);
	}

	/**
	 * Warns administrators when no installed editor can write WebP.
	 *
	 * @return void
	 */
	public function show_support_notice() {
		if ( ! current_user_can( 'manage_options' ) || wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) ) ) {
			return;
		}

		echo '<div class="notice notice-warning"><p>';
		echo esc_html__( 'Esee Automatic WebP needs GD or Imagick with WebP support. Uploads will remain unchanged until that support is enabled.', 'esee-webp-converter' );
		echo '</p></div>';
	}
}
