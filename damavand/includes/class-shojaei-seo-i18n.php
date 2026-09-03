<?php
/**
 * Internationalization handler.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_i18n
 */
class Shojaei_SEO_i18n {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		// This class is instantiated on plugins_loaded; load now to avoid JIT notices
		// when other boot-time code calls translation functions before init.
		if ( did_action( 'plugins_loaded' ) && ! did_action( 'init' ) ) {
			$this->load_textdomain();
		}
	}

	/**
	 * Load plugin text domain.
	 */
	public function load_textdomain(): void {
		load_plugin_textdomain(
			'shojaei-seo-for-woo',
			false,
			dirname( DAMAVAND_SEO_BASENAME ) . '/languages'
		);
	}
}
