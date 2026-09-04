<?php
/**
 * General Meta — robots, title separator, OG fallback (Damavand).
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_General_Meta
 */
class Shojaei_SEO_General_Meta {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'wp_robots', array( $this, 'filter_robots' ), 20 );
		add_filter( 'document_title_separator', array( $this, 'filter_title_separator' ), 20 );
		add_action( 'wp_head', array( $this, 'output_og_fallback' ), 5 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin' ) );
	}

	/**
	 * Whether Damavand should emit general meta on the frontend.
	 */
	public static function should_output(): bool {
		if ( 'yes' !== Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_enabled', 'no' ) ) {
			return false;
		}
		// If a primary SEO plugin is active, only output when merchant forced it.
		if ( self::has_meta_competitor() && 'yes' !== Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_force_with_competitors', 'no' ) ) {
			return false;
		}
		return true;
	}

	/**
	 * Yoast / Rank Math / similar that already output robots & OG.
	 */
	public static function has_meta_competitor(): bool {
		return Shojaei_SEO_Helpers::is_rank_math_active() || Shojaei_SEO_Helpers::is_yoast_active();
	}

	/**
	 * Human-readable list of competing plugins.
	 *
	 * @return string[]
	 */
	public static function competitor_names(): array {
		$names = array();
		if ( Shojaei_SEO_Helpers::is_rank_math_active() ) {
			$names[] = 'Rank Math';
		}
		if ( Shojaei_SEO_Helpers::is_yoast_active() ) {
			$names[] = 'Yoast SEO';
		}
		return $names;
	}

	/**
	 * Allowed title separators.
	 *
	 * @return string[]
	 */
	public static function separator_choices(): array {
		return array( '-', '–', '—', '|', '•', '·', '.' );
	}

	/**
	 * Current separator (default hyphen).
	 */
	public static function get_separator(): string {
		$sep = (string) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_separator', '-' );
		$custom = (string) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_separator_custom', '' );
		if ( 'custom' === $sep && '' !== $custom ) {
			return mb_substr( $custom, 0, 3, 'UTF-8' );
		}
		if ( in_array( $sep, self::separator_choices(), true ) ) {
			return $sep;
		}
		return '-';
	}

	/**
	 * Replace %sep% / %separator% in a string.
	 *
	 * @param string $text Text.
	 */
	public static function apply_sep_tokens( string $text ): string {
		$sep = self::get_separator();
		return str_replace( array( '%sep%', '%separator%' ), $sep, $text );
	}

	/**
	 * Build default robots directives from settings.
	 *
	 * @return array<string,bool|string|int>
	 */
	public static function default_robots_directives(): array {
		$robots = array();

		$noindex = ( 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_robots_noindex', 'no' ) );
		if ( $noindex ) {
			$robots['noindex'] = true;
		} else {
			$robots['index'] = true;
		}

		if ( 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_robots_nofollow', 'no' ) ) {
			$robots['nofollow'] = true;
		} else {
			$robots['follow'] = true;
		}

		if ( 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_robots_noarchive', 'no' ) ) {
			$robots['noarchive'] = true;
		}
		if ( 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_robots_noimageindex', 'no' ) ) {
			$robots['noimageindex'] = true;
		}
		if ( 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_robots_nosnippet', 'no' ) ) {
			$robots['nosnippet'] = true;
		}

		if ( 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_adv_snippet', 'no' ) ) {
			$val = (int) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_max_snippet', -1 );
			$robots['max-snippet'] = $val;
		}
		if ( 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_adv_video', 'no' ) ) {
			$val = (int) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_max_video_preview', -1 );
			$robots['max-video-preview'] = $val;
		}
		if ( 'yes' === Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_adv_image', 'yes' ) ) {
			$img = (string) Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_max_image_preview', 'large' );
			if ( ! in_array( $img, array( 'none', 'standard', 'large' ), true ) ) {
				$img = 'large';
			}
			$robots['max-image-preview'] = $img;
		}

		return $robots;
	}

	/**
	 * Filter wp_robots.
	 *
	 * Structural noindex (cart/checkout/search/facets) runs whenever Damavand
	 * is primary — even if the "general meta" toggle is off. Site-wide defaults
	 * still require that toggle via Damavand_Robots::meta_defaults_enabled().
	 *
	 * @param array $robots Directives.
	 * @return array
	 */
	public function filter_robots( array $robots ): array {
		if ( class_exists( 'Damavand_Robots' ) ) {
			return Damavand_Robots::apply_to_robots( $robots );
		}

		if ( ! self::should_output() ) {
			return $robots;
		}

		foreach ( self::default_robots_directives() as $key => $val ) {
			$robots[ $key ] = $val;
		}

		return $robots;
	}

	/**
	 * Title separator for WP document titles.
	 *
	 * @param string $sep Current.
	 */
	public function filter_title_separator( string $sep ): string {
		if ( ! self::should_output() ) {
			return $sep;
		}
		return self::get_separator();
	}

	/**
	 * Fallback Open Graph image when no featured/OG image is set.
	 */
	public function output_og_fallback(): void {
		if ( ! self::should_output() ) {
			return;
		}
		// Let Rank Math / Yoast own OG if they are active and we are not forcing.
		if ( self::has_meta_competitor() && 'yes' !== Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_force_with_competitors', 'no' ) ) {
			return;
		}

		$id = absint( Shojaei_SEO_Helpers::get_option( 'shojaei_seo_meta_og_image_id', 0 ) );
		if ( $id < 1 ) {
			return;
		}

		// Skip if singular already has a featured image (common OG source).
		if ( is_singular() && has_post_thumbnail() ) {
			return;
		}

		$url = wp_get_attachment_image_url( $id, 'full' );
		if ( ! $url ) {
			return;
		}

		echo '<meta property="og:image" content="' . esc_url( $url ) . '" />' . "\n";
		$meta = wp_get_attachment_metadata( $id );
		if ( is_array( $meta ) ) {
			if ( ! empty( $meta['width'] ) ) {
				echo '<meta property="og:image:width" content="' . esc_attr( (string) (int) $meta['width'] ) . '" />' . "\n";
			}
			if ( ! empty( $meta['height'] ) ) {
				echo '<meta property="og:image:height" content="' . esc_attr( (string) (int) $meta['height'] ) . '" />' . "\n";
			}
		}
	}

	/**
	 * Media uploader on general-meta tab.
	 *
	 * @param string $hook Hook.
	 */
	public function enqueue_admin( string $hook ): void {
		if ( false === strpos( $hook, 'shojaei-seo' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		if ( 'general-meta' !== $tab ) {
			return;
		}
		wp_enqueue_media();
		// Nowdoc — در رشتهٔ دوبل‌کوت PHP، $( به‌عنوان سینتکس متغیر پارس می‌شود و fatal می‌دهد.
		$meta_js = <<<'JS'
(function($){$(function(){
	var frame;
	$(document).on('click','#shojaei-meta-og-upload',function(e){
		e.preventDefault();
		if(frame){frame.open();return;}
		frame=wp.media({title:'تصویر OpenGraph',button:{text:'انتخاب'},multiple:false});
		frame.on('select',function(){
			var att=frame.state().get('selection').first().toJSON();
			$('#shojaei_seo_meta_og_image_id').val(att.id);
			var url=(att.sizes&&att.sizes.medium)?att.sizes.medium.url:att.url;
			$('#shojaei-meta-og-preview').html('<img src="'+url+'" alt="" style="max-width:180px;height:auto;border-radius:6px;" />').show();
			$('#shojaei-meta-og-remove').prop('hidden',false);
		});
		frame.open();
	});
	$(document).on('click','#shojaei-meta-og-remove',function(e){
		e.preventDefault();
		$('#shojaei_seo_meta_og_image_id').val('0');
		$('#shojaei-meta-og-preview').empty().hide();
		$(this).prop('hidden',true);
	});
	$(document).on('change','input[name="shojaei_seo_meta_separator"]',function(){
		var v=$(this).val();
		$('#shojaei-meta-sep-custom-wrap').toggle(v==='custom');
		$('.shojaei-meta-sep-chip').removeClass('is-active');
		$(this).closest('label.shojaei-meta-sep-chip').addClass('is-active');
	});
});})(jQuery);
JS;
		wp_add_inline_script( 'jquery', $meta_js );
	}

	/**
	 * Persist settings from POST (called by admin save handler).
	 */
	public static function save_from_post(): void {
		$checks = array(
			'shojaei_seo_meta_enabled',
			'shojaei_seo_meta_force_with_competitors',
			'shojaei_seo_meta_robots_noindex',
			'shojaei_seo_meta_robots_nofollow',
			'shojaei_seo_meta_robots_noarchive',
			'shojaei_seo_meta_robots_noimageindex',
			'shojaei_seo_meta_robots_nosnippet',
			'shojaei_seo_meta_adv_snippet',
			'shojaei_seo_meta_adv_video',
			'shojaei_seo_meta_adv_image',
			'shojaei_seo_meta_noindex_empty_tax',
			'shojaei_seo_meta_noindex_facets',
			'shojaei_seo_meta_noindex_author_date',
			'shojaei_seo_meta_noindex_wc_system',
		);
		foreach ( $checks as $key ) {
			update_option( $key, isset( $_POST[ $key ] ) ? 'yes' : 'no' ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		}

		// Indexing vs noindex: if «نمایه‌سازی» checked and noindex not, clear noindex.
		$want_index = isset( $_POST['shojaei_seo_meta_robots_index'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( $want_index && ! isset( $_POST['shojaei_seo_meta_robots_noindex'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_option( 'shojaei_seo_meta_robots_noindex', 'no' );
		}
		if ( isset( $_POST['shojaei_seo_meta_robots_noindex'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_option( 'shojaei_seo_meta_robots_noindex', 'yes' );
			// noindex wins over index checkbox.
		} elseif ( $want_index ) {
			update_option( 'shojaei_seo_meta_robots_noindex', 'no' );
		}

		$sep = isset( $_POST['shojaei_seo_meta_separator'] ) ? sanitize_text_field( wp_unslash( $_POST['shojaei_seo_meta_separator'] ) ) : '-'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$allowed = array_merge( self::separator_choices(), array( 'custom' ) );
		if ( ! in_array( $sep, $allowed, true ) ) {
			$sep = '-';
		}
		update_option( 'shojaei_seo_meta_separator', $sep );

		if ( isset( $_POST['shojaei_seo_meta_separator_custom'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			$custom = sanitize_text_field( wp_unslash( $_POST['shojaei_seo_meta_separator_custom'] ) );
			update_option( 'shojaei_seo_meta_separator_custom', mb_substr( $custom, 0, 3, 'UTF-8' ) );
		}

		$max_snip = isset( $_POST['shojaei_seo_meta_max_snippet'] ) ? (int) wp_unslash( $_POST['shojaei_seo_meta_max_snippet'] ) : -1; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		update_option( 'shojaei_seo_meta_max_snippet', max( -1, min( 100000, $max_snip ) ) );

		$max_vid = isset( $_POST['shojaei_seo_meta_max_video_preview'] ) ? (int) wp_unslash( $_POST['shojaei_seo_meta_max_video_preview'] ) : -1; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		update_option( 'shojaei_seo_meta_max_video_preview', max( -1, min( 100000, $max_vid ) ) );

		$img = isset( $_POST['shojaei_seo_meta_max_image_preview'] ) ? sanitize_text_field( wp_unslash( $_POST['shojaei_seo_meta_max_image_preview'] ) ) : 'large'; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		if ( ! in_array( $img, array( 'none', 'standard', 'large' ), true ) ) {
			$img = 'large';
		}
		update_option( 'shojaei_seo_meta_max_image_preview', $img );

		if ( isset( $_POST['shojaei_seo_meta_og_image_id'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			update_option( 'shojaei_seo_meta_og_image_id', absint( wp_unslash( $_POST['shojaei_seo_meta_og_image_id'] ) ) );
		}

		if ( class_exists( 'Damavand_SEO_Templates' ) ) {
			Damavand_SEO_Templates::save_from_post();
		}
	}
}
