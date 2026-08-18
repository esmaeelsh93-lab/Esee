<?php
/**
 * Editable page content and safe per-page presentation controls.
 *
 * @package RezaJordaan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Add a compact HTML/CSS helper box to pages.
 */
function rezajordaan_add_page_options_box() {
	add_meta_box(
		'rezajordaan-page-options',
		__( 'تنظیمات نمایش رضا جردن', 'rezajordaan' ),
		'rezajordaan_render_page_options_box',
		'page',
		'normal',
		'default'
	);
}
add_action( 'add_meta_boxes_page', 'rezajordaan_add_page_options_box' );

/**
 * Render custom page options.
 *
 * @param WP_Post $post Current page.
 */
function rezajordaan_render_page_options_box( $post ) {
	$custom_css = get_post_meta( $post->ID, '_rezajordaan_custom_css', true );
	$hide_title = '1' === get_post_meta( $post->ID, '_rezajordaan_hide_title', true );
	$full_width = '1' === get_post_meta( $post->ID, '_rezajordaan_full_width', true );

	wp_nonce_field( 'rezajordaan_save_page_options', 'rezajordaan_page_options_nonce' );
	?>
	<p>
		<?php esc_html_e( 'برای ساخت برگه کدنویسی‌شده، از بخش «قالب» گزینه «HTML اختصاصی» را انتخاب و کد HTML را داخل بلوک HTML سفارشی قرار دهید.', 'rezajordaan' ); ?>
	</p>
	<p>
		<label>
			<input type="checkbox" name="rezajordaan_hide_title" value="1" <?php checked( $hide_title ); ?>>
			<?php esc_html_e( 'عنوان برگه در سایت نمایش داده نشود', 'rezajordaan' ); ?>
		</label>
		&nbsp;&nbsp;
		<label>
			<input type="checkbox" name="rezajordaan_full_width" value="1" <?php checked( $full_width ); ?>>
			<?php esc_html_e( 'محتوا تمام‌عرض باشد', 'rezajordaan' ); ?>
		</label>
	</p>
	<p><label for="rezajordaan-custom-css"><strong><?php esc_html_e( 'CSS اختصاصی این برگه', 'rezajordaan' ); ?></strong></label></p>
	<textarea id="rezajordaan-custom-css" name="rezajordaan_custom_css" class="widefat code" rows="12" dir="ltr"><?php echo esc_textarea( $custom_css ); ?></textarea>
	<p class="description"><?php esc_html_e( 'کد CSS فقط در همین برگه بارگذاری می‌شود. این بخش تنها برای مدیرانی که دسترسی HTML بدون فیلتر دارند ذخیره می‌شود.', 'rezajordaan' ); ?></p>
	<?php
}

/**
 * Save page display options.
 *
 * @param int $post_id Page ID.
 */
function rezajordaan_save_page_options( $post_id ) {
	if (
		! isset( $_POST['rezajordaan_page_options_nonce'] )
		|| ! wp_verify_nonce(
			sanitize_text_field( wp_unslash( $_POST['rezajordaan_page_options_nonce'] ) ),
			'rezajordaan_save_page_options'
		)
		|| ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
		|| ! current_user_can( 'edit_post', $post_id )
	) {
		return;
	}

	update_post_meta( $post_id, '_rezajordaan_hide_title', isset( $_POST['rezajordaan_hide_title'] ) ? '1' : '0' );
	update_post_meta( $post_id, '_rezajordaan_full_width', isset( $_POST['rezajordaan_full_width'] ) ? '1' : '0' );

	if ( current_user_can( 'unfiltered_html' ) && isset( $_POST['rezajordaan_custom_css'] ) ) {
		$css = wp_strip_all_tags( wp_unslash( $_POST['rezajordaan_custom_css'] ) );
		update_post_meta( $post_id, '_rezajordaan_custom_css', $css );
	}
}
add_action( 'save_post_page', 'rezajordaan_save_page_options' );

/**
 * Enhance the CSS field with WordPress' built-in code editor.
 *
 * @param string $hook Current admin page.
 */
function rezajordaan_page_code_editor( $hook ) {
	if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
		return;
	}

	$screen = get_current_screen();
	if ( ! $screen || 'page' !== $screen->post_type || ! current_user_can( 'unfiltered_html' ) ) {
		return;
	}

	$settings = wp_enqueue_code_editor( array( 'type' => 'text/css' ) );
	if ( ! $settings ) {
		return;
	}

	wp_add_inline_script(
		'code-editor',
		'jQuery(function(){if(document.getElementById("rezajordaan-custom-css")){wp.codeEditor.initialize("rezajordaan-custom-css",' . wp_json_encode( $settings ) . ');}});'
	);
}
add_action( 'admin_enqueue_scripts', 'rezajordaan_page_code_editor' );

/**
 * Load per-page CSS after the theme stylesheet.
 */
function rezajordaan_enqueue_page_css() {
	if ( ! is_page() ) {
		return;
	}

	$css = get_post_meta( get_queried_object_id(), '_rezajordaan_custom_css', true );
	if ( ! $css ) {
		return;
	}

	$css = preg_replace( '#</?style[^>]*>#i', '', $css );
	wp_add_inline_style( 'rezajordaan-main', $css );
}
add_action( 'wp_enqueue_scripts', 'rezajordaan_enqueue_page_css', 20 );
