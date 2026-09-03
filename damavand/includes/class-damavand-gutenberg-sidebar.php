<?php
/**
 * Block editor sidebar for Damavand SEO (post/page).
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Damavand_Gutenberg_Sidebar
 */
final class Damavand_Gutenberg_Sidebar {

	/**
	 * Register hooks.
	 */
	public static function register_hooks(): void {
		if ( ! is_admin() ) {
			return;
		}
		add_action( 'init', array( __CLASS__, 'register_meta_rest' ) );
		add_action( 'enqueue_block_editor_assets', array( __CLASS__, 'enqueue' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'maybe_hide_metabox' ), 100 );
	}

	/**
	 * Expose Damavand meta to block editor REST saves.
	 */
	public static function register_meta_rest(): void {
		if ( ! class_exists( 'Damavand_SEO_Meta' ) || ! class_exists( 'Damavand_Persian_SEO_Score' ) ) {
			return;
		}

		$map = array(
			Damavand_SEO_Meta::TITLE => 'string',
			Damavand_SEO_Meta::DESC  => 'string',
			Damavand_SEO_Meta::FOCUS => 'string',
		);

		foreach ( Damavand_Persian_SEO_Score::post_types() as $post_type ) {
			if ( 'product' === $post_type ) {
				continue;
			}
			foreach ( $map as $meta_key => $type ) {
				register_post_meta(
					$post_type,
					$meta_key,
					array(
						'show_in_rest'  => true,
						'single'        => true,
						'type'          => $type,
						'auth_callback' => static function () {
							return current_user_can( 'edit_posts' );
						},
					)
				);
			}
		}
	}

	/**
	 * Hide classic side metabox when block editor owns the screen.
	 */
	public static function maybe_hide_metabox(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->post_type, array( 'post', 'page' ), true ) ) {
			return;
		}
		if ( ! function_exists( 'use_block_editor_for_post_type' ) || ! use_block_editor_for_post_type( $screen->post_type ) ) {
			return;
		}
		remove_meta_box( 'damavand_persian_seo_score', $screen->post_type, 'side' );
	}

	/**
	 * Enqueue sidebar script on block editor for post/page.
	 */
	public static function enqueue(): void {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->post_type, array( 'post', 'page' ), true ) ) {
			return;
		}
		if ( ! function_exists( 'use_block_editor_for_post_type' ) || ! use_block_editor_for_post_type( $screen->post_type ) ) {
			return;
		}

		wp_enqueue_style(
			'damavand-seo-score',
			DAMAVAND_SEO_URL . 'admin/css/damavand-seo-score.css',
			array(),
			DAMAVAND_SEO_VERSION
		);

		wp_enqueue_script(
			'damavand-gutenberg-sidebar',
			DAMAVAND_SEO_URL . 'admin/js/damavand-gutenberg-sidebar.js',
			array( 'wp-plugins', 'wp-edit-post', 'wp-element', 'wp-components', 'wp-data', 'wp-i18n' ),
			DAMAVAND_SEO_VERSION,
			true
		);

		wp_localize_script(
			'damavand-gutenberg-sidebar',
			'damavandGbSeo',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'damavand_seo_score_live' ),
				'meta'    => array(
					'title'   => class_exists( 'Damavand_SEO_Meta' ) ? Damavand_SEO_Meta::TITLE : '_damavand_seo_title',
					'desc'    => class_exists( 'Damavand_SEO_Meta' ) ? Damavand_SEO_Meta::DESC : '_damavand_seo_metadesc',
					'focus'   => class_exists( 'Damavand_SEO_Meta' ) ? Damavand_SEO_Meta::FOCUS : '_damavand_seo_focus_keyword',
					'related' => class_exists( 'Damavand_Content_Analyzer' ) ? Damavand_Content_Analyzer::META_RELATED : '_damavand_seo_related_keywords',
				),
				'i18n'    => array(
					'title'       => __( 'سئوی Damavand', 'shojaei-seo-for-woo' ),
					'tabBasic'    => __( 'سئو پایه', 'shojaei-seo-for-woo' ),
					'tabAnalysis' => __( 'تحلیل', 'shojaei-seo-for-woo' ),
					'seoTitle'    => __( 'عنوان سئو', 'shojaei-seo-for-woo' ),
					'seoDesc'     => __( 'توضیح متا', 'shojaei-seo-for-woo' ),
					'focus'       => __( 'کلمه کلیدی', 'shojaei-seo-for-woo' ),
					'score'       => __( 'امتیاز فارسی', 'shojaei-seo-for-woo' ),
					'readability' => __( 'خوانایی محتوا', 'shojaei-seo-for-woo' ),
					'schemaLoad'  => __( 'پیش‌نمایش JSON-LD', 'shojaei-seo-for-woo' ),
					'error'       => __( 'خطا در محاسبه.', 'shojaei-seo-for-woo' ),
				),
			)
		);
	}
}
