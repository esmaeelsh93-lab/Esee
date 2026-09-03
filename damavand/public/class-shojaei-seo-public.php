<?php
/**
 * Public-facing functionality.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_Public
 */
class Shojaei_SEO_Public {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'register_indexnow_key_file' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	/**
	 * Front assets (conflict banner styles for managers).
	 */
	public function enqueue_assets(): void {
		if ( ! Shojaei_SEO_Helpers::user_can_admin() ) {
			return;
		}
		wp_enqueue_style(
			'shojaei-seo-public',
			DAMAVAND_SEO_URL . 'public/css/public-style.css',
			array(),
			DAMAVAND_SEO_VERSION
		);
	}

	/**
	 * Serve IndexNow key verification file.
	 */
	public function register_indexnow_key_file(): void {
		$key = class_exists( 'SEO_Core_Installer' )
			? SEO_Core_Installer::get_indexnow_key()
			: (string) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_indexnow_key', '' );
		if ( empty( $key ) ) {
			return;
		}

		add_rewrite_rule( '^' . preg_quote( $key, '/' ) . '\.txt$', 'index.php?shojaei_indexnow_key=1', 'top' );
		add_filter( 'query_vars', function ( $vars ) {
			$vars[] = 'shojaei_indexnow_key';
			return $vars;
		} );

		add_action( 'template_redirect', function () use ( $key ) {
			if ( get_query_var( 'shojaei_indexnow_key' ) ) {
				header( 'Content-Type: text/plain' );
				echo esc_html( $key );
				exit;
			}
		} );
	}
}
