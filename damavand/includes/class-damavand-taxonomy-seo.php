<?php
/**
 * SEO metabox + score for product/category taxonomies (additive — no slug/redirect changes).
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Damavand_Taxonomy_SEO
 */
final class Damavand_Taxonomy_SEO {

	public const META_SCORE = '_damavand_term_seo_score';

	/**
	 * Boot hooks.
	 */
	public static function register_hooks(): void {
		if ( ! is_admin() ) {
			return;
		}
		foreach ( self::taxonomies() as $tax ) {
			add_action( "{$tax}_edit_form_fields", array( __CLASS__, 'render_edit_fields' ), 20, 2 );
			add_action( "{$tax}_add_form_fields", array( __CLASS__, 'render_add_fields' ), 20 );
			add_action( "edited_{$tax}", array( __CLASS__, 'save_term' ), 10, 2 );
			add_action( "created_{$tax}", array( __CLASS__, 'save_term' ), 10, 2 );
			add_filter( "manage_edit-{$tax}_columns", array( __CLASS__, 'add_list_column' ) );
			add_filter( "manage_{$tax}_custom_column", array( __CLASS__, 'render_list_column' ), 10, 3 );
			add_filter( "manage_edit-{$tax}_sortable_columns", array( __CLASS__, 'sortable_column' ) );
		}
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue' ) );
		add_action( 'wp_ajax_damavand_term_seo_live', array( __CLASS__, 'ajax_live' ) );
		add_action( 'parse_term_query', array( __CLASS__, 'orderby_score' ) );
	}

	/**
	 * @return string[]
	 */
	public static function taxonomies(): array {
		$tax = array( 'product_cat', 'product_tag', 'category', 'post_tag' );
		return array_values( array_unique( apply_filters( 'damavand_taxonomy_seo_taxonomies', $tax ) ) );
	}

	/**
	 * @param string $hook Hook.
	 */
	public static function enqueue( string $hook ): void {
		if ( ! in_array( $hook, array( 'term.php', 'edit-tags.php' ), true ) ) {
			return;
		}
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || ! in_array( $screen->taxonomy, self::taxonomies(), true ) ) {
			return;
		}
		wp_enqueue_style(
			'damavand-fonts',
			DAMAVAND_SEO_URL . 'admin/css/damavand-fonts.css',
			array(),
			DAMAVAND_SEO_VERSION
		);
		wp_enqueue_style(
			'damavand-seo-score',
			DAMAVAND_SEO_URL . 'admin/css/damavand-seo-score.css',
			array( 'damavand-fonts' ),
			DAMAVAND_SEO_VERSION
		);
		wp_enqueue_script(
			'damavand-term-seo',
			DAMAVAND_SEO_URL . 'admin/js/damavand-term-seo.js',
			array( 'jquery' ),
			DAMAVAND_SEO_VERSION,
			true
		);
		wp_localize_script(
			'damavand-term-seo',
			'damavandTermSeo',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'damavand_term_seo' ),
			)
		);
	}

	/**
	 * @param WP_Term $term Term.
	 */
	public static function render_edit_fields( $term ): void {
		if ( ! $term instanceof WP_Term ) {
			return;
		}
		$analysis = class_exists( 'Damavand_Content_Analyzer' )
			? Damavand_Content_Analyzer::score_term( (int) $term->term_id, (string) $term->taxonomy )
			: array( 'score' => 0, 'tone' => 'bad', 'checks' => array() );
		$title = (string) get_term_meta( (int) $term->term_id, Damavand_SEO_Meta::TITLE, true );
		$desc  = (string) get_term_meta( (int) $term->term_id, Damavand_SEO_Meta::DESC, true );
		$focus = (string) get_term_meta( (int) $term->term_id, Damavand_SEO_Meta::FOCUS, true );
		include DAMAVAND_SEO_DIR . 'admin/views/taxonomy-seo-fields.php';
	}

	/**
	 * @param string $taxonomy Taxonomy.
	 */
	public static function render_add_fields( string $taxonomy ): void {
		$term     = null;
		$analysis = array( 'score' => 0, 'tone' => 'bad', 'checks' => array() );
		$title    = '';
		$desc     = '';
		$focus    = '';
		include DAMAVAND_SEO_DIR . 'admin/views/taxonomy-seo-fields.php';
	}

	/**
	 * @param int $term_id Term ID.
	 */
	public static function save_term( int $term_id ): void {
		if ( $term_id < 1 || ! current_user_can( 'manage_categories' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		if ( ! isset( $_POST['damavand_term_seo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['damavand_term_seo_nonce'] ) ), 'damavand_term_seo_save' ) ) {
			return;
		}
		$tax = isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : '';
		if ( ! in_array( $tax, self::taxonomies(), true ) ) {
			return;
		}
		$fields = array(
			Damavand_SEO_Meta::TITLE => isset( $_POST['damavand_term_seo_title'] ) ? sanitize_text_field( wp_unslash( $_POST['damavand_term_seo_title'] ) ) : '',
			Damavand_SEO_Meta::DESC  => isset( $_POST['damavand_term_seo_desc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['damavand_term_seo_desc'] ) ) : '',
			Damavand_SEO_Meta::FOCUS => isset( $_POST['damavand_term_seo_focus'] ) ? sanitize_text_field( wp_unslash( $_POST['damavand_term_seo_focus'] ) ) : '',
		);
		foreach ( $fields as $key => $val ) {
			if ( '' === $val ) {
				delete_term_meta( $term_id, $key );
			} else {
				update_term_meta( $term_id, $key, $val );
			}
		}
		if ( class_exists( 'Damavand_Content_Analyzer' ) ) {
			$score = Damavand_Content_Analyzer::score_term( $term_id, $tax );
			update_term_meta( $term_id, self::META_SCORE, (int) ( $score['score'] ?? 0 ) );
		}
	}

	/**
	 * Live term score preview.
	 */
	public static function ajax_live(): void {
		check_ajax_referer( 'damavand_term_seo', 'nonce' );
		$term_id  = isset( $_POST['term_id'] ) ? absint( $_POST['term_id'] ) : 0;
		$taxonomy = isset( $_POST['taxonomy'] ) ? sanitize_key( wp_unslash( $_POST['taxonomy'] ) ) : '';
		if ( $term_id < 1 || ! in_array( $taxonomy, self::taxonomies(), true ) ) {
			wp_send_json_error( array( 'message' => __( 'نامعتبر.', 'shojaei-seo-for-woo' ) ), 400 );
		}
		if ( ! current_user_can( 'manage_categories' ) && ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'دسترسی ندارید.', 'shojaei-seo-for-woo' ) ), 403 );
		}
		$override = array(
			'title' => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
			'desc'  => isset( $_POST['desc'] ) ? sanitize_textarea_field( wp_unslash( $_POST['desc'] ) ) : '',
			'focus' => isset( $_POST['focus'] ) ? sanitize_text_field( wp_unslash( $_POST['focus'] ) ) : '',
		);
		wp_send_json_success(
			class_exists( 'Damavand_Content_Analyzer' )
				? Damavand_Content_Analyzer::score_term( $term_id, $taxonomy, $override )
				: array( 'score' => 0 )
		);
	}

	/**
	 * @param array<string,string> $columns Columns.
	 * @return array<string,string>
	 */
	public static function add_list_column( array $columns ): array {
		$columns['damavand_term_seo'] = __( 'سئو', 'shojaei-seo-for-woo' );
		return $columns;
	}

	/**
	 * @param string $content     Cell.
	 * @param string $column_name Column.
	 * @param int    $term_id     Term ID.
	 */
	public static function render_list_column( $content, string $column_name, $term_id ) {
		if ( 'damavand_term_seo' !== $column_name ) {
			return $content;
		}
		$raw   = get_term_meta( (int) $term_id, self::META_SCORE, true );
		$score = ( '' === (string) $raw || false === $raw ) ? null : max( 0, min( 100, (int) $raw ) );
		if ( null === $score ) {
			return '<span class="dm-list-score dm-list-score--na">—</span>';
		}
		$tone = 'bad';
		if ( $score >= 80 ) {
			$tone = 'good';
		} elseif ( $score >= 60 ) {
			$tone = 'ok';
		}
		return sprintf(
			'<span class="dm-list-score dm-list-score--%1$s"><span>%2$d</span></span>',
			esc_attr( $tone ),
			(int) $score
		);
	}

	/**
	 * @param array<string,string> $columns Columns.
	 * @return array<string,string>
	 */
	public static function sortable_column( array $columns ): array {
		$columns['damavand_term_seo'] = 'damavand_term_seo';
		return $columns;
	}

	/**
	 * Sort terms by Damavand SEO score when requested.
	 *
	 * @param WP_Term_Query $query Query.
	 */
	public static function orderby_score( $query ): void {
		if ( ! is_admin() || ! $query instanceof WP_Term_Query ) {
			return;
		}
		$orderby = $query->query_vars['orderby'] ?? '';
		if ( 'damavand_term_seo' !== $orderby ) {
			return;
		}
		$query->query_vars['meta_key'] = self::META_SCORE;
		$query->query_vars['orderby']  = 'meta_value_num';
	}
}
