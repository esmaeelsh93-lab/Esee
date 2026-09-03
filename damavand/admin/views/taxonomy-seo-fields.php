<?php
/**
 * Taxonomy SEO fields partial.
 *
 * @var WP_Term|null $term
 * @var array        $analysis
 * @var string       $title
 * @var string       $desc
 * @var string       $focus
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

$term_id  = ( $term instanceof WP_Term ) ? (int) $term->term_id : 0;
$taxonomy = ( $term instanceof WP_Term ) ? (string) $term->taxonomy : ( isset( $_GET['taxonomy'] ) ? sanitize_key( wp_unslash( $_GET['taxonomy'] ) ) : '' ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$tone     = (string) ( $analysis['tone'] ?? 'bad' );
$is_edit  = $term instanceof WP_Term;

ob_start();
?>
<?php wp_nonce_field( 'damavand_term_seo_save', 'damavand_term_seo_nonce' ); ?>
<div class="dm-score dm-term-seo" id="damavand-term-seo-box" data-term-id="<?php echo esc_attr( (string) $term_id ); ?>" data-taxonomy="<?php echo esc_attr( $taxonomy ); ?>" dir="rtl">
	<div class="dm-term-seo__row">
		<div class="dm-score__gauge dm-score__gauge--<?php echo esc_attr( $tone ); ?>">
			<span class="dm-score__num" id="dm-term-score-num"><?php echo esc_html( (string) (int) ( $analysis['score'] ?? 0 ) ); ?></span>
			<span class="dm-score__label"><?php esc_html_e( 'امتیاز دسته', 'shojaei-seo-for-woo' ); ?></span>
		</div>
		<div class="dm-term-seo__fields">
			<label class="dm-score__field">
				<span><?php esc_html_e( 'عنوان سئو', 'shojaei-seo-for-woo' ); ?></span>
				<input type="text" name="damavand_term_seo_title" id="dm-term-seo-title" class="regular-text" value="<?php echo esc_attr( $title ); ?>" />
			</label>
			<label class="dm-score__field">
				<span><?php esc_html_e( 'توضیح متا', 'shojaei-seo-for-woo' ); ?></span>
				<textarea name="damavand_term_seo_desc" id="dm-term-seo-desc" rows="3" class="large-text"><?php echo esc_textarea( $desc ); ?></textarea>
			</label>
			<label class="dm-score__field">
				<span><?php esc_html_e( 'کلمه کلیدی', 'shojaei-seo-for-woo' ); ?></span>
				<input type="text" name="damavand_term_seo_focus" id="dm-term-seo-focus" class="regular-text" value="<?php echo esc_attr( $focus ); ?>" />
			</label>
			<p class="description"><?php esc_html_e( 'توضیح دسته را در ویرایشگر بالا (~۱۵۰ کلمه) کامل کنید. نامک و ریدایرکت دست‌نخورده می‌ماند.', 'shojaei-seo-for-woo' ); ?></p>
		</div>
	</div>
	<ul class="dm-score__checks" id="dm-term-score-checks">
		<?php foreach ( (array) ( $analysis['checks'] ?? array() ) as $check ) : ?>
			<li class="<?php echo ! empty( $check['ok'] ) ? 'is-ok' : 'is-bad'; ?>">
				<strong><?php echo esc_html( (string) ( $check['label'] ?? '' ) ); ?></strong>
				<span dir="ltr"><?php echo esc_html( (int) ( $check['points'] ?? 0 ) . '/' . (int) ( $check['max'] ?? 0 ) ); ?></span>
				<em><?php echo esc_html( (string) ( $check['tip'] ?? '' ) ); ?></em>
			</li>
		<?php endforeach; ?>
	</ul>
</div>
<?php
$inner = ob_get_clean();

if ( $is_edit ) {
	echo '<tr class="form-field damavand-term-seo"><th scope="row">' . esc_html__( 'سئو دماوند', 'shojaei-seo-for-woo' ) . '</th><td>' . $inner . '</td></tr>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
} else {
	echo '<div class="form-field damavand-term-seo"><label>' . esc_html__( 'سئو دماوند', 'shojaei-seo-for-woo' ) . '</label>' . $inner . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
