<?php
/**
 * Similar Products — settings (WordPress Settings API + Damavand UI).
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Damavand_Similar_Products_Settings
 */
final class Damavand_Similar_Products_Settings {

	public const OPTION = 'damavand_similar_products_settings';
	public const GROUP  = 'damavand_similar_products';

	/**
	 * Register Settings API + UI hooks.
	 */
	public static function register_hooks(): void {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
	}

	/**
	 * Default option payload.
	 *
	 * @return array{enabled:bool,limit:int,match_categories:bool,match_tags:bool,match_attributes:bool}
	 */
	public static function defaults(): array {
		return array(
			'enabled'          => false, // Opt-in: fully optional until merchant enables.
			'limit'            => 5,
			'match_categories' => true,
			'match_tags'       => false,
			'match_attributes' => false,
		);
	}

	/**
	 * Sanitized settings array.
	 *
	 * @return array{enabled:bool,limit:int,match_categories:bool,match_tags:bool,match_attributes:bool}
	 */
	public static function get(): array {
		$raw = get_option( self::OPTION, null );
		if ( ! is_array( $raw ) ) {
			return self::defaults();
		}
		return self::sanitize( array_merge( self::defaults(), $raw ) );
	}

	/**
	 * Settings API registration.
	 */
	public static function register_settings(): void {
		register_setting(
			self::GROUP,
			self::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Sanitize option array (Settings API + form save).
	 *
	 * @param mixed $input Raw.
	 * @return array{enabled:bool,limit:int,match_categories:bool,match_tags:bool,match_attributes:bool}
	 */
	public static function sanitize( $input ): array {
		$defaults = self::defaults();
		if ( ! is_array( $input ) ) {
			return $defaults;
		}
		$limit = array_key_exists( 'limit', $input ) ? absint( $input['limit'] ) : $defaults['limit'];
		$limit = max( 1, min( 12, $limit ?: $defaults['limit'] ) );

		return array(
			'enabled'          => ! empty( $input['enabled'] ),
			'limit'            => $limit,
			'match_categories' => ! empty( $input['match_categories'] ),
			'match_tags'       => ! empty( $input['match_tags'] ),
			'match_attributes' => ! empty( $input['match_attributes'] ),
		);
	}

	/**
	 * Persist from Damavand settings form POST.
	 *
	 * @param array $post Sanitized-enough POST slice (unchecked boxes absent).
	 */
	public static function save_from_post( array $post ): void {
		$payload = array(
			'enabled'          => ! empty( $post['damavand_similar_enabled'] ),
			'limit'            => isset( $post['damavand_similar_limit'] ) ? absint( $post['damavand_similar_limit'] ) : 5,
			'match_categories' => ! empty( $post['damavand_similar_match_categories'] ),
			'match_tags'       => ! empty( $post['damavand_similar_match_tags'] ),
			'match_attributes' => ! empty( $post['damavand_similar_match_attributes'] ),
		);
		update_option( self::OPTION, self::sanitize( $payload ), false );
		if ( class_exists( 'Damavand_Similar_Products_Engine' ) ) {
			Damavand_Similar_Products_Engine::flush_all_caches();
		}
	}

	/**
	 * Render accordion section HTML (called from settings view).
	 */
	public static function render_section(): void {
		$s = self::get();
		?>
		<div class="shojaei-accordion-item" data-accordion="set-similar">
			<button class="shojaei-accordion-header" type="button" aria-expanded="false">
				<span class="shojaei-accordion-icon shojaei-icon-blue" aria-hidden="true">
					<?php
					if ( class_exists( 'Damavand_SEO_Icons' ) ) {
						Damavand_SEO_Icons::render( 'sparkles', 16 );
					}
					?>
				</span>
				<span class="shojaei-accordion-title"><?php esc_html_e( 'محصولات مشابه (لینک هوشمند)', 'shojaei-seo-for-woo' ); ?></span>
				<span class="shojaei-accordion-meta"><?php esc_html_e( 'سئو', 'shojaei-seo-for-woo' ); ?></span>
				<span class="shojaei-accordion-chevron dashicons dashicons-arrow-down-alt2"></span>
			</button>
			<div class="shojaei-accordion-body">
				<div class="shojaei-accordion-content">
					<p class="shojaei-module-note">
						<?php esc_html_e( 'باکس مرتبط در صفحه محصول — بدون نوشتن HTML داخل post_content. نتایج ۲۴ ساعت کش می‌شوند و با ویرایش محصول تازه می‌شوند.', 'shojaei-seo-for-woo' ); ?>
					</p>
					<div class="shojaei-settings-grid">
						<label class="shojaei-setting-item">
							<input type="checkbox" name="damavand_similar_enabled" value="1" <?php checked( $s['enabled'] ); ?> />
							<span><?php esc_html_e( 'فعال‌سازی محصولات مشابه', 'shojaei-seo-for-woo' ); ?></span>
						</label>
						<label class="shojaei-setting-item">
							<input type="checkbox" name="damavand_similar_match_categories" value="1" <?php checked( $s['match_categories'] ); ?> />
							<span><?php esc_html_e( 'تطبیق بر اساس دسته‌ها', 'shojaei-seo-for-woo' ); ?></span>
						</label>
						<label class="shojaei-setting-item">
							<input type="checkbox" name="damavand_similar_match_tags" value="1" <?php checked( $s['match_tags'] ); ?> />
							<span><?php esc_html_e( 'تطبیق بر اساس برچسب‌ها', 'shojaei-seo-for-woo' ); ?></span>
						</label>
						<label class="shojaei-setting-item">
							<input type="checkbox" name="damavand_similar_match_attributes" value="1" <?php checked( $s['match_attributes'] ); ?> />
							<span><?php esc_html_e( 'تطبیق بر اساس ویژگی‌ها (Attributes)', 'shojaei-seo-for-woo' ); ?></span>
						</label>
					</div>
					<div class="shojaei-form-grid" style="margin-top:12px;">
						<label>
							<?php esc_html_e( 'تعداد نمایش', 'shojaei-seo-for-woo' ); ?>
							<input type="number" name="damavand_similar_limit" value="<?php echo esc_attr( (string) $s['limit'] ); ?>" min="1" max="12" />
						</label>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}
