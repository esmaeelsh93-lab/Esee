<?php
/**
 * Small, focused settings panel for the Reza Jordaan theme.
 *
 * @package RezaJordaan
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Return defaults for every editable theme option.
 *
 * @return array<string, mixed>
 */
function rezajordaan_default_settings() {
	$shop_url = function_exists( 'rezajordaan_shop_url' ) ? rezajordaan_shop_url() : home_url( '/shop/' );

	return array(
		'instagram_url'              => 'https://www.instagram.com/rezajordaan/',
		'whatsapp_url'               => 'https://wa.me/989035263346',
		'rubika_url'                 => 'https://rubika.ir/rezajordaan',
		'telegram_url'               => '',
		'phone_display'              => '09035263346',
		'phone_url'                  => 'tel:+989035263346',
		'email'                      => '',
		'address'                    => 'کرج، گلشهر، خیابان پونه شرقی، جنب اسباب‌بازی البرز',
		'enamad_url'                 => 'https://trustseal.enamad.ir/?id=541613&Code=ZHfcBR7k0vVn3dPW7YgfoAkkTmF6j0Uc',
		'enamad_logo_url'            => 'https://trustseal.enamad.ir/logo.aspx?id=541613&Code=ZHfcBR7k0vVn3dPW7YgfoAkkTmF6j0Uc',
		'show_instagram_header'      => 1,
		'show_whatsapp_header'       => 1,
		'show_telegram_header'       => 0,
		'show_phone_footer'          => 1,
		'show_social_footer'         => 1,
		'show_enamad_footer'         => 1,
		'show_product_search'        => 1,
		'show_categories'            => 1,
		'show_about'                 => 1,
		'show_category_count'        => 1,
		'show_subcategories'         => 0,
		'show_sale_badge'            => 1,
		'featured_category_ids'      => array(),
		'category_section_kicker'    => 'برای هر سلیقه',
		'category_section_title'     => 'دسته‌بندی‌ها',
		'category_columns_desktop'   => 3,
		'category_columns_mobile'    => 2,
		'category_border_width'      => 1,
		'category_border_color'      => '#408a71',
		'category_name_color'        => '#ffffff',
		'category_name_size_desktop' => 26,
		'category_name_size_mobile'  => 18,
		'product_border_width'       => 1,
		'product_border_color'       => '#b0e4cc',
		'sale_badge_text'            => '٪{percent} تخفیف',
		'sale_badge_background'      => '#285a48',
		'sale_badge_color'           => '#ffffff',
		'footer_tagline'             => 'انتخاب خاص برای استایل تو',
		'footer_description'         => 'فروشگاه کفش رضا جردن؛ خرید آنلاین و اقساطی آسان با امکان بررسی و خرید حضوری.',
		'about_title'                => 'کیفیت را از نزدیک انتخاب کن',
		'about_description'          => 'در رضا جردن تلاش می‌کنیم انتخاب کفش باکیفیت، خوش‌استایل و مناسب را برایت ساده‌تر کنیم؛ چه آنلاین خرید کنی و چه حضوری به فروشگاه بیایی.',
		'about_url'                  => 'https://rezajordaan.ir/about-us-3/',
		'store_visit_text'           => 'برای بررسی کیفیت از نزدیک و تست سایز، خرید حضوری در فروشگاه فیزیکی فراهم است: کرج، گلشهر، خیابان پونه شرقی، جنب اسباب‌بازی البرز',
		'blog_page_id'               => 0,
		'header_links'               => array(
			array(
				'label'   => 'فروشگاه',
				'page_id' => 0,
				'url'     => $shop_url,
			),
			array(
				'label'   => 'درباره ما',
				'page_id' => 0,
				'url'     => 'https://rezajordaan.ir/about-us-3/',
			),
			array(
				'label'   => 'تماس با ما',
				'page_id' => 0,
				'url'     => 'https://rezajordaan.ir/contact-us/',
			),
		),
		'footer_links'               => array(
			array(
				'label'   => 'صفحه اصلی',
				'page_id' => 0,
				'url'     => home_url( '/' ),
			),
			array(
				'label'   => 'فروشگاه ما',
				'page_id' => 0,
				'url'     => $shop_url,
			),
			array(
				'label'   => 'درباره ما',
				'page_id' => 0,
				'url'     => 'https://rezajordaan.ir/about-us-3/',
			),
			array(
				'label'   => 'تماس با ما',
				'page_id' => 0,
				'url'     => 'https://rezajordaan.ir/contact-us/',
			),
			array(
				'label'   => 'سیاست تعویض و مرجوعی',
				'page_id' => 0,
				'url'     => 'https://rezajordaan.ir/%D8%B3%DB%8C%D8%A7%D8%B3%D8%AA-%D8%AA%D8%B9%D9%88%DB%8C%D8%B6-%D9%88-%D9%85%D8%B1%D8%AC%D9%88%D8%B9%DB%8C/',
			),
		),
		'latest_products_count'      => 10,
		'marquee_speed'              => 42,
		'shop_columns_desktop'       => 4,
		'shop_columns_mobile'        => 1,
		'archive_full_width'         => 1,
		'shop_image_height_desktop'  => 360,
		'shop_image_height_mobile'   => 220,
		'latest_card_width_desktop'  => 300,
		'latest_card_width_mobile'   => 210,
		'latest_image_height_desktop'=> 366,
		'latest_image_height_mobile' => 256,
		'product_card_gap'           => 20,
	);
}

/**
 * Return all saved settings merged with defaults.
 *
 * @return array<string, mixed>
 */
function rezajordaan_get_settings() {
	$saved = get_option( 'rezajordaan_settings', array() );
	return wp_parse_args( is_array( $saved ) ? $saved : array(), rezajordaan_default_settings() );
}

/**
 * Return one setting.
 *
 * @param string $key Setting key.
 * @return mixed
 */
function rezajordaan_get_setting( $key ) {
	$settings = rezajordaan_get_settings();
	return array_key_exists( $key, $settings ) ? $settings[ $key ] : null;
}

/**
 * Resolve page-based and custom links into frontend URLs.
 *
 * @param string $key Setting key containing link rows.
 * @return array<int, array{label:string,url:string}>
 */
function rezajordaan_get_theme_links( $key ) {
	$rows  = rezajordaan_get_setting( $key );
	$links = array();

	if ( ! is_array( $rows ) ) {
		return $links;
	}

	foreach ( $rows as $row ) {
		$page_id = isset( $row['page_id'] ) ? absint( $row['page_id'] ) : 0;
		$url     = $page_id ? get_permalink( $page_id ) : ( isset( $row['url'] ) ? $row['url'] : '' );
		$label   = isset( $row['label'] ) ? $row['label'] : '';

		if ( $page_id && ! $label ) {
			$label = get_the_title( $page_id );
		}

		if ( $label && $url ) {
			$links[] = array(
				'label' => $label,
				'url'   => $url,
			);
		}
	}

	return $links;
}

/**
 * Sanitize a group of page/custom URL rows.
 *
 * @param mixed $rows Raw rows.
 * @param int   $limit Maximum row count.
 * @return array<int, array{label:string,page_id:int,url:string}>
 */
function rezajordaan_sanitize_link_rows( $rows, $limit ) {
	$clean = array();

	if ( ! is_array( $rows ) ) {
		return $clean;
	}

	foreach ( array_slice( $rows, 0, $limit ) as $row ) {
		if ( ! is_array( $row ) ) {
			continue;
		}

		$item = array(
			'label'   => isset( $row['label'] ) ? sanitize_text_field( $row['label'] ) : '',
			'page_id' => isset( $row['page_id'] ) ? absint( $row['page_id'] ) : 0,
			'url'     => isset( $row['url'] ) ? esc_url_raw( $row['url'] ) : '',
		);

		if ( $item['label'] || $item['page_id'] || $item['url'] ) {
			$clean[] = $item;
		}
	}

	return $clean;
}

/**
 * Sanitize the complete settings payload.
 *
 * @param mixed $input Raw settings.
 * @return array<string, mixed>
 */
function rezajordaan_sanitize_settings( $input ) {
	$input    = is_array( $input ) ? $input : array();
	$defaults = rezajordaan_default_settings();
	$clean    = array();

	foreach ( array( 'instagram_url', 'whatsapp_url', 'rubika_url', 'telegram_url', 'phone_url', 'enamad_url', 'enamad_logo_url', 'about_url' ) as $key ) {
		$clean[ $key ] = isset( $input[ $key ] ) ? esc_url_raw( $input[ $key ] ) : '';
	}

	$clean['phone_display']      = isset( $input['phone_display'] ) ? sanitize_text_field( $input['phone_display'] ) : '';
	$clean['email']              = isset( $input['email'] ) ? sanitize_email( $input['email'] ) : '';
	$clean['address']            = isset( $input['address'] ) ? sanitize_textarea_field( $input['address'] ) : '';
	$clean['footer_tagline']     = isset( $input['footer_tagline'] ) ? sanitize_text_field( $input['footer_tagline'] ) : '';
	$clean['footer_description'] = isset( $input['footer_description'] ) ? sanitize_textarea_field( $input['footer_description'] ) : '';
	$clean['category_section_kicker'] = isset( $input['category_section_kicker'] ) ? sanitize_text_field( $input['category_section_kicker'] ) : '';
	$clean['category_section_title']  = isset( $input['category_section_title'] ) ? sanitize_text_field( $input['category_section_title'] ) : '';
	$clean['sale_badge_text']         = isset( $input['sale_badge_text'] ) ? sanitize_text_field( $input['sale_badge_text'] ) : '';
	$clean['about_title']        = isset( $input['about_title'] ) ? sanitize_text_field( $input['about_title'] ) : '';
	$clean['about_description']  = isset( $input['about_description'] ) ? sanitize_textarea_field( $input['about_description'] ) : '';
	$clean['store_visit_text']   = isset( $input['store_visit_text'] ) ? sanitize_textarea_field( $input['store_visit_text'] ) : '';
	$clean['blog_page_id']       = isset( $input['blog_page_id'] ) ? absint( $input['blog_page_id'] ) : 0;

	foreach ( array( 'show_instagram_header', 'show_whatsapp_header', 'show_telegram_header', 'show_phone_footer', 'show_social_footer', 'show_enamad_footer', 'show_product_search', 'show_categories', 'show_about', 'show_category_count', 'show_subcategories', 'show_sale_badge', 'archive_full_width' ) as $key ) {
		$clean[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
	}

	foreach ( array( 'category_border_color', 'category_name_color', 'product_border_color', 'sale_badge_background', 'sale_badge_color' ) as $key ) {
		$clean[ $key ] = isset( $input[ $key ] ) ? sanitize_hex_color( $input[ $key ] ) : $defaults[ $key ];
		$clean[ $key ] = $clean[ $key ] ?: $defaults[ $key ];
	}

	$category_ids = isset( $input['featured_category_ids'] ) && is_array( $input['featured_category_ids'] )
		? array_map( 'absint', $input['featured_category_ids'] )
		: array();
	$clean['featured_category_ids'] = array_slice( array_values( array_unique( array_filter( $category_ids ) ) ), 0, 24 );

	$clean['header_links'] = rezajordaan_sanitize_link_rows( $input['header_links'] ?? array(), 4 );
	$clean['footer_links'] = rezajordaan_sanitize_link_rows( $input['footer_links'] ?? array(), 10 );

	$ranges = array(
		'latest_products_count'       => array( 4, 20 ),
		'marquee_speed'               => array( 15, 100 ),
		'shop_columns_desktop'        => array( 2, 6 ),
		'shop_columns_mobile'         => array( 1, 2 ),
		'shop_image_height_desktop'   => array( 180, 720 ),
		'shop_image_height_mobile'    => array( 140, 480 ),
		'latest_card_width_desktop'   => array( 200, 420 ),
		'latest_card_width_mobile'    => array( 150, 300 ),
		'latest_image_height_desktop' => array( 220, 560 ),
		'latest_image_height_mobile'  => array( 180, 420 ),
		'product_card_gap'            => array( 6, 48 ),
		'category_columns_desktop'    => array( 1, 4 ),
		'category_columns_mobile'     => array( 1, 2 ),
		'category_border_width'       => array( 0, 6 ),
		'category_name_size_desktop'  => array( 14, 40 ),
		'category_name_size_mobile'   => array( 12, 30 ),
		'product_border_width'        => array( 0, 6 ),
	);

	foreach ( $ranges as $key => $limits ) {
		$value         = isset( $input[ $key ] ) ? absint( $input[ $key ] ) : $defaults[ $key ];
		$clean[ $key ] = min( $limits[1], max( $limits[0], $value ) );
	}

	return $clean;
}

/**
 * Register the option and Appearance submenu.
 */
function rezajordaan_register_settings() {
	register_setting(
		'rezajordaan_settings_group',
		'rezajordaan_settings',
		array(
			'type'              => 'array',
			'sanitize_callback' => 'rezajordaan_sanitize_settings',
			'default'           => rezajordaan_default_settings(),
		)
	);
}
add_action( 'admin_init', 'rezajordaan_register_settings' );

/**
 * Assign the article-list template to the selected page.
 *
 * @param array $old_value Previous settings.
 * @param array $value New settings.
 */
function rezajordaan_sync_blog_page( $old_value, $value ) {
	$old_page_id = isset( $old_value['blog_page_id'] ) ? absint( $old_value['blog_page_id'] ) : 0;
	$page_id     = isset( $value['blog_page_id'] ) ? absint( $value['blog_page_id'] ) : 0;

	if ( $old_page_id && $old_page_id !== $page_id && 'page-blog.php' === get_post_meta( $old_page_id, '_wp_page_template', true ) ) {
		delete_post_meta( $old_page_id, '_wp_page_template' );
	}

	if ( $page_id && 'page' === get_post_type( $page_id ) ) {
		update_post_meta( $page_id, '_wp_page_template', 'page-blog.php' );
	}
}
add_action( 'update_option_rezajordaan_settings', 'rezajordaan_sync_blog_page', 10, 2 );

/**
 * Assign the article template when settings are saved for the first time.
 *
 * @param string $option Option name.
 * @param array  $value Saved settings.
 */
function rezajordaan_sync_blog_page_on_add( $option, $value ) {
	rezajordaan_sync_blog_page( array(), $value );
}
add_action( 'add_option_rezajordaan_settings', 'rezajordaan_sync_blog_page_on_add', 10, 2 );

/**
 * Add the settings page.
 */
function rezajordaan_add_settings_page() {
	add_theme_page(
		__( 'تنظیمات رضا جردن', 'rezajordaan' ),
		__( 'تنظیمات رضا جردن', 'rezajordaan' ),
		'manage_options',
		'rezajordaan-settings',
		'rezajordaan_render_settings_page'
	);
}
add_action( 'admin_menu', 'rezajordaan_add_settings_page' );

/**
 * Enqueue settings-page assets.
 *
 * @param string $hook Current admin hook.
 */
function rezajordaan_settings_assets( $hook ) {
	if ( 'appearance_page_rezajordaan-settings' !== $hook ) {
		return;
	}

	wp_enqueue_style(
		'rezajordaan-admin',
		get_template_directory_uri() . '/assets/css/admin-settings.css',
		array(),
		REZAJORDAAN_VERSION
	);
	wp_enqueue_script(
		'rezajordaan-admin',
		get_template_directory_uri() . '/assets/js/admin-settings.js',
		array(),
		REZAJORDAAN_VERSION,
		true
	);
}
add_action( 'admin_enqueue_scripts', 'rezajordaan_settings_assets' );

/**
 * Render a switch field.
 *
 * @param string $key Settings key.
 * @param string $label Label.
 */
function rezajordaan_setting_switch( $key, $label ) {
	?>
	<label class="rj-admin-switch">
		<input type="checkbox" name="rezajordaan_settings[<?php echo esc_attr( $key ); ?>]" value="1" <?php checked( rezajordaan_get_setting( $key ) ); ?>>
		<span aria-hidden="true"></span>
		<strong><?php echo esc_html( $label ); ?></strong>
	</label>
	<?php
}

/**
 * Render one adjustable range.
 *
 * @param string $key Settings key.
 * @param string $label Label.
 * @param int    $min Minimum.
 * @param int    $max Maximum.
 * @param int    $step Step.
 * @param string $unit Unit label.
 */
function rezajordaan_setting_range( $key, $label, $min, $max, $step = 1, $unit = '' ) {
	$value = absint( rezajordaan_get_setting( $key ) );
	?>
	<label class="rj-admin-range">
		<span><?php echo esc_html( $label ); ?></span>
		<div>
			<input type="range" name="rezajordaan_settings[<?php echo esc_attr( $key ); ?>]" min="<?php echo esc_attr( $min ); ?>" max="<?php echo esc_attr( $max ); ?>" step="<?php echo esc_attr( $step ); ?>" value="<?php echo esc_attr( $value ); ?>">
			<output><?php echo esc_html( $value . $unit ); ?></output>
		</div>
	</label>
	<?php
}

/**
 * Render page/custom-link rows.
 *
 * @param string $key Settings key.
 * @param string $title Section title.
 * @param int    $limit Maximum links.
 */
function rezajordaan_setting_links( $key, $title, $limit ) {
	$rows  = rezajordaan_get_setting( $key );
	$rows  = is_array( $rows ) ? $rows : array();
	$pages = get_pages( array( 'sort_column' => 'post_title' ) );
	?>
	<div class="rj-link-editor" data-link-editor data-key="<?php echo esc_attr( $key ); ?>" data-limit="<?php echo esc_attr( $limit ); ?>">
		<div class="rj-link-editor__heading">
			<div>
				<h3><?php echo esc_html( $title ); ?></h3>
				<p><?php esc_html_e( 'یک برگه انتخاب کنید یا عنوان و نشانی دلخواه وارد کنید.', 'rezajordaan' ); ?></p>
			</div>
			<button type="button" class="button button-secondary" data-add-link><?php esc_html_e( 'افزودن پیوند', 'rezajordaan' ); ?></button>
		</div>
		<div class="rj-link-editor__rows" data-link-rows>
			<?php foreach ( $rows as $index => $row ) : ?>
				<?php rezajordaan_render_link_row( $key, $index, $row, $pages ); ?>
			<?php endforeach; ?>
		</div>
		<template data-link-template>
			<?php rezajordaan_render_link_row( $key, '__INDEX__', array(), $pages ); ?>
		</template>
	</div>
	<?php
}

/**
 * Render an individual link row.
 *
 * @param string $key Option key.
 * @param int|string $index Row index.
 * @param array  $row Link data.
 * @param array  $pages Published pages.
 */
function rezajordaan_render_link_row( $key, $index, $row, $pages ) {
	$label   = isset( $row['label'] ) ? $row['label'] : '';
	$page_id = isset( $row['page_id'] ) ? absint( $row['page_id'] ) : 0;
	$url     = isset( $row['url'] ) ? $row['url'] : '';
	$name    = 'rezajordaan_settings[' . $key . '][' . $index . ']';
	?>
	<div class="rj-link-row">
		<label>
			<span><?php esc_html_e( 'عنوان', 'rezajordaan' ); ?></span>
			<input type="text" name="<?php echo esc_attr( $name . '[label]' ); ?>" value="<?php echo esc_attr( $label ); ?>" placeholder="<?php esc_attr_e( 'مثلاً درباره ما', 'rezajordaan' ); ?>">
		</label>
		<label>
			<span><?php esc_html_e( 'انتخاب برگه', 'rezajordaan' ); ?></span>
			<select name="<?php echo esc_attr( $name . '[page_id]' ); ?>">
				<option value="0"><?php esc_html_e( '— بدون برگه —', 'rezajordaan' ); ?></option>
				<?php foreach ( $pages as $page ) : ?>
					<option value="<?php echo esc_attr( $page->ID ); ?>" <?php selected( $page_id, $page->ID ); ?>><?php echo esc_html( $page->post_title ); ?></option>
				<?php endforeach; ?>
			</select>
		</label>
		<label>
			<span><?php esc_html_e( 'نشانی دلخواه', 'rezajordaan' ); ?></span>
			<input type="url" dir="ltr" name="<?php echo esc_attr( $name . '[url]' ); ?>" value="<?php echo esc_attr( $url ); ?>" placeholder="https://">
		</label>
		<button type="button" class="button-link-delete" data-remove-link><?php esc_html_e( 'حذف', 'rezajordaan' ); ?></button>
	</div>
	<?php
}

/**
 * Render WooCommerce category visibility controls.
 */
function rezajordaan_setting_categories() {
	$selected_ids = array_map( 'absint', (array) rezajordaan_get_setting( 'featured_category_ids' ) );
	$categories   = taxonomy_exists( 'product_cat' )
		? get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
				'order'      => 'ASC',
			)
		)
		: array();

	if ( is_wp_error( $categories ) ) {
		$categories = array();
	}
	?>
	<div class="rj-category-picker">
		<?php if ( $categories ) : ?>
			<p><?php esc_html_e( 'هر دسته‌ای که باید در لندینگ دیده شود تیک بزنید. برداشتن تیک، همان دسته را از لندینگ حذف می‌کند.', 'rezajordaan' ); ?></p>
			<div class="rj-category-picker__list">
				<?php foreach ( $categories as $category ) : ?>
					<label>
						<input
							type="checkbox"
							name="rezajordaan_settings[featured_category_ids][]"
							value="<?php echo esc_attr( $category->term_id ); ?>"
							<?php checked( in_array( (int) $category->term_id, $selected_ids, true ) ); ?>
						>
						<span><?php echo esc_html( $category->name ); ?></span>
						<small><?php echo esc_html( number_format_i18n( $category->count ) ); ?></small>
					</label>
				<?php endforeach; ?>
			</div>
		<?php else : ?>
			<p><?php esc_html_e( 'پس از ساخت دسته‌بندی‌های ووکامرس، انتخاب آن‌ها در همین قسمت فعال می‌شود.', 'rezajordaan' ); ?></p>
		<?php endif; ?>
	</div>
	<?php
}

/**
 * Render the complete settings screen.
 */
function rezajordaan_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap rj-settings">
		<header class="rj-settings__hero">
			<div>
				<p>REZA JORDAAN</p>
				<h1><?php esc_html_e( 'تنظیمات ساده قالب', 'rezajordaan' ); ?></h1>
				<span><?php esc_html_e( 'فقط گزینه‌های کاربردی؛ بدون صفحه‌ساز و تنظیمات پیچیده.', 'rezajordaan' ); ?></span>
			</div>
			<a class="button" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'مشاهده سایت', 'rezajordaan' ); ?></a>
		</header>

		<?php settings_errors(); ?>
		<form method="post" action="options.php">
			<?php settings_fields( 'rezajordaan_settings_group' ); ?>

			<nav class="rj-settings__tabs" aria-label="<?php esc_attr_e( 'بخش‌های تنظیمات', 'rezajordaan' ); ?>">
				<button type="button" class="is-active" data-settings-tab="contact"><?php esc_html_e( 'هدر و فوتر', 'rezajordaan' ); ?></button>
				<button type="button" data-settings-tab="links"><?php esc_html_e( 'پیوندها و برگه‌ها', 'rezajordaan' ); ?></button>
				<button type="button" data-settings-tab="landing"><?php esc_html_e( 'لندینگ و دسته‌بندی‌ها', 'rezajordaan' ); ?></button>
				<button type="button" data-settings-tab="products"><?php esc_html_e( 'کارت محصولات', 'rezajordaan' ); ?></button>
			</nav>

			<section class="rj-settings__panel is-active" data-settings-panel="contact">
				<div class="rj-settings__grid">
					<div class="rj-settings__card">
						<h2><?php esc_html_e( 'راه‌های ارتباطی', 'rezajordaan' ); ?></h2>
						<label><span><?php esc_html_e( 'اینستاگرام', 'rezajordaan' ); ?></span><input type="url" dir="ltr" name="rezajordaan_settings[instagram_url]" value="<?php echo esc_attr( rezajordaan_get_setting( 'instagram_url' ) ); ?>"></label>
						<label><span><?php esc_html_e( 'واتساپ', 'rezajordaan' ); ?></span><input type="url" dir="ltr" name="rezajordaan_settings[whatsapp_url]" value="<?php echo esc_attr( rezajordaan_get_setting( 'whatsapp_url' ) ); ?>"></label>
						<label><span><?php esc_html_e( 'روبیکا', 'rezajordaan' ); ?></span><input type="url" dir="ltr" name="rezajordaan_settings[rubika_url]" value="<?php echo esc_attr( rezajordaan_get_setting( 'rubika_url' ) ); ?>"></label>
						<label><span><?php esc_html_e( 'تلگرام', 'rezajordaan' ); ?></span><input type="url" dir="ltr" name="rezajordaan_settings[telegram_url]" value="<?php echo esc_attr( rezajordaan_get_setting( 'telegram_url' ) ); ?>"></label>
						<label><span><?php esc_html_e( 'شماره نمایشی', 'rezajordaan' ); ?></span><input type="text" dir="ltr" name="rezajordaan_settings[phone_display]" value="<?php echo esc_attr( rezajordaan_get_setting( 'phone_display' ) ); ?>"></label>
						<label><span><?php esc_html_e( 'لینک تماس', 'rezajordaan' ); ?></span><input type="url" dir="ltr" name="rezajordaan_settings[phone_url]" value="<?php echo esc_attr( rezajordaan_get_setting( 'phone_url' ) ); ?>" placeholder="tel:+98..."></label>
						<label><span><?php esc_html_e( 'ایمیل', 'rezajordaan' ); ?></span><input type="email" dir="ltr" name="rezajordaan_settings[email]" value="<?php echo esc_attr( rezajordaan_get_setting( 'email' ) ); ?>"></label>
						<label><span><?php esc_html_e( 'آدرس', 'rezajordaan' ); ?></span><textarea name="rezajordaan_settings[address]" rows="3"><?php echo esc_textarea( rezajordaan_get_setting( 'address' ) ); ?></textarea></label>
					</div>
					<div class="rj-settings__card">
						<h2><?php esc_html_e( 'نمایش بخش‌ها', 'rezajordaan' ); ?></h2>
						<?php rezajordaan_setting_switch( 'show_instagram_header', __( 'آیکون اینستاگرام در هدر', 'rezajordaan' ) ); ?>
						<?php rezajordaan_setting_switch( 'show_whatsapp_header', __( 'آیکون واتساپ در هدر', 'rezajordaan' ) ); ?>
						<?php rezajordaan_setting_switch( 'show_telegram_header', __( 'آیکون تلگرام در هدر', 'rezajordaan' ) ); ?>
						<?php rezajordaan_setting_switch( 'show_phone_footer', __( 'شماره تماس در فوتر', 'rezajordaan' ) ); ?>
						<?php rezajordaan_setting_switch( 'show_social_footer', __( 'شبکه‌های اجتماعی در فوتر', 'rezajordaan' ) ); ?>
						<?php rezajordaan_setting_switch( 'show_enamad_footer', __( 'نمایش اینماد در فوتر', 'rezajordaan' ) ); ?>
						<?php rezajordaan_setting_switch( 'show_product_search', __( 'جستجوی محصولات زیر هیرو', 'rezajordaan' ) ); ?>
						<hr>
						<label><span><?php esc_html_e( 'شعار فوتر', 'rezajordaan' ); ?></span><input type="text" name="rezajordaan_settings[footer_tagline]" value="<?php echo esc_attr( rezajordaan_get_setting( 'footer_tagline' ) ); ?>"></label>
						<label><span><?php esc_html_e( 'توضیح کوتاه فوتر', 'rezajordaan' ); ?></span><textarea name="rezajordaan_settings[footer_description]" rows="4"><?php echo esc_textarea( rezajordaan_get_setting( 'footer_description' ) ); ?></textarea></label>
						<label><span><?php esc_html_e( 'لینک اعتبارسنجی اینماد', 'rezajordaan' ); ?></span><input type="url" dir="ltr" name="rezajordaan_settings[enamad_url]" value="<?php echo esc_attr( rezajordaan_get_setting( 'enamad_url' ) ); ?>"></label>
						<label><span><?php esc_html_e( 'نشانی تصویر اینماد', 'rezajordaan' ); ?></span><input type="url" dir="ltr" name="rezajordaan_settings[enamad_logo_url]" value="<?php echo esc_attr( rezajordaan_get_setting( 'enamad_logo_url' ) ); ?>"></label>
					</div>
				</div>
			</section>

			<section class="rj-settings__panel" data-settings-panel="links">
				<div class="rj-settings__card">
					<h2><?php esc_html_e( 'صفحه مقالات', 'rezajordaan' ); ?></h2>
					<p><?php esc_html_e( 'یک برگه خالی مثل «مجله» بسازید و اینجا انتخاب کنید تا همه نوشته‌ها در آن نمایش داده شوند.', 'rezajordaan' ); ?></p>
					<label>
						<span><?php esc_html_e( 'برگه نمایش مقالات', 'rezajordaan' ); ?></span>
						<?php
						wp_dropdown_pages(
							array(
								'name'              => 'rezajordaan_settings[blog_page_id]',
								'selected'          => absint( rezajordaan_get_setting( 'blog_page_id' ) ),
								'show_option_none'  => __( '— انتخاب نشده —', 'rezajordaan' ),
								'option_none_value' => 0,
							)
						);
						?>
					</label>
				</div>
				<div class="rj-settings__card">
					<?php rezajordaan_setting_links( 'header_links', __( 'پیوندهای اضافه هدر', 'rezajordaan' ), 4 ); ?>
				</div>
				<div class="rj-settings__card">
					<?php rezajordaan_setting_links( 'footer_links', __( 'پیوندهای فوتر', 'rezajordaan' ), 10 ); ?>
				</div>
				<div class="rj-settings__notice">
					<strong><?php esc_html_e( 'برگه‌ها و مقاله‌ها اکنون قالب اختصاصی دارند.', 'rezajordaan' ); ?></strong>
					<p><?php esc_html_e( 'پس از انتشار برگه، همین‌جا آن را انتخاب کنید تا در هدر یا فوتر دیده شود. برای CSS اختصاصی، قالب «HTML اختصاصی» را برای برگه انتخاب کنید.', 'rezajordaan' ); ?></p>
				</div>
			</section>

			<section class="rj-settings__panel" data-settings-panel="landing">
				<div class="rj-settings__grid">
					<div class="rj-settings__card">
						<h2><?php esc_html_e( 'نمایش بخش‌های لندینگ', 'rezajordaan' ); ?></h2>
						<?php rezajordaan_setting_switch( 'show_categories', __( 'نمایش بخش دسته‌بندی‌ها', 'rezajordaan' ) ); ?>
						<?php rezajordaan_setting_switch( 'show_category_count', __( 'نمایش تعداد محصول زیر نام دسته', 'rezajordaan' ) ); ?>
						<?php rezajordaan_setting_switch( 'show_subcategories', __( 'نمایش زیردسته‌ها در آرشیو فروشگاه', 'rezajordaan' ) ); ?>
						<?php rezajordaan_setting_switch( 'show_about', __( 'نمایش بخش درباره ما و خرید حضوری', 'rezajordaan' ) ); ?>
					</div>
					<div class="rj-settings__card">
						<h2><?php esc_html_e( 'متن درباره ما', 'rezajordaan' ); ?></h2>
						<label><span><?php esc_html_e( 'عنوان', 'rezajordaan' ); ?></span><input type="text" name="rezajordaan_settings[about_title]" value="<?php echo esc_attr( rezajordaan_get_setting( 'about_title' ) ); ?>"></label>
						<label><span><?php esc_html_e( 'توضیح', 'rezajordaan' ); ?></span><textarea name="rezajordaan_settings[about_description]" rows="4"><?php echo esc_textarea( rezajordaan_get_setting( 'about_description' ) ); ?></textarea></label>
						<label><span><?php esc_html_e( 'لینک درباره ما', 'rezajordaan' ); ?></span><input type="url" dir="ltr" name="rezajordaan_settings[about_url]" value="<?php echo esc_attr( rezajordaan_get_setting( 'about_url' ) ); ?>"></label>
						<label><span><?php esc_html_e( 'متن خرید حضوری و آدرس', 'rezajordaan' ); ?></span><textarea name="rezajordaan_settings[store_visit_text]" rows="5"><?php echo esc_textarea( rezajordaan_get_setting( 'store_visit_text' ) ); ?></textarea></label>
					</div>
				</div>
				<div class="rj-settings__card">
					<h2><?php esc_html_e( 'دسته‌بندی‌های قابل نمایش در لندینگ', 'rezajordaan' ); ?></h2>
					<?php rezajordaan_setting_categories(); ?>
				</div>
				<div class="rj-settings__card">
					<h2><?php esc_html_e( 'چیدمان و ظاهر دسته‌بندی‌ها', 'rezajordaan' ); ?></h2>
					<div class="rj-settings__grid">
						<div>
							<label><span><?php esc_html_e( 'نوشته بالای عنوان', 'rezajordaan' ); ?></span><input type="text" name="rezajordaan_settings[category_section_kicker]" value="<?php echo esc_attr( rezajordaan_get_setting( 'category_section_kicker' ) ); ?>"></label>
							<label><span><?php esc_html_e( 'عنوان بخش دسته‌بندی‌ها', 'rezajordaan' ); ?></span><input type="text" name="rezajordaan_settings[category_section_title]" value="<?php echo esc_attr( rezajordaan_get_setting( 'category_section_title' ) ); ?>"></label>
							<label class="rj-color-field"><span><?php esc_html_e( 'رنگ قاب دسته‌ها', 'rezajordaan' ); ?></span><input type="color" name="rezajordaan_settings[category_border_color]" value="<?php echo esc_attr( rezajordaan_get_setting( 'category_border_color' ) ); ?>"></label>
							<label class="rj-color-field"><span><?php esc_html_e( 'رنگ نام دسته‌ها', 'rezajordaan' ); ?></span><input type="color" name="rezajordaan_settings[category_name_color]" value="<?php echo esc_attr( rezajordaan_get_setting( 'category_name_color' ) ); ?>"></label>
						</div>
						<div>
							<?php rezajordaan_setting_range( 'category_columns_desktop', __( 'تعداد ستون دسکتاپ', 'rezajordaan' ), 1, 4 ); ?>
							<?php rezajordaan_setting_range( 'category_columns_mobile', __( 'تعداد ستون موبایل', 'rezajordaan' ), 1, 2 ); ?>
							<?php rezajordaan_setting_range( 'category_border_width', __( 'ضخامت قاب دسته‌ها', 'rezajordaan' ), 0, 6, 1, 'px' ); ?>
							<?php rezajordaan_setting_range( 'category_name_size_desktop', __( 'اندازه نام دسته در دسکتاپ', 'rezajordaan' ), 14, 40, 1, 'px' ); ?>
							<?php rezajordaan_setting_range( 'category_name_size_mobile', __( 'اندازه نام دسته در موبایل', 'rezajordaan' ), 12, 30, 1, 'px' ); ?>
						</div>
					</div>
				</div>
			</section>

			<section class="rj-settings__panel" data-settings-panel="products">
				<div class="rj-settings__grid">
					<div class="rj-settings__card">
						<h2><?php esc_html_e( 'فروشگاه و آرشیو محصولات', 'rezajordaan' ); ?></h2>
						<?php rezajordaan_setting_switch( 'archive_full_width', __( 'نمایش فول‌عرض محصولات در دسته‌بندی (دسکتاپ)', 'rezajordaan' ) ); ?>
						<?php rezajordaan_setting_range( 'shop_columns_desktop', __( 'تعداد ستون دسکتاپ', 'rezajordaan' ), 2, 6 ); ?>
						<?php rezajordaan_setting_range( 'shop_columns_mobile', __( 'تعداد ستون موبایل', 'rezajordaan' ), 1, 2 ); ?>
						<?php rezajordaan_setting_range( 'shop_image_height_desktop', __( 'ارتفاع تصویر دسکتاپ', 'rezajordaan' ), 180, 720, 10, 'px' ); ?>
						<?php rezajordaan_setting_range( 'shop_image_height_mobile', __( 'ارتفاع تصویر موبایل', 'rezajordaan' ), 140, 480, 10, 'px' ); ?>
						<?php rezajordaan_setting_range( 'product_border_width', __( 'ضخامت قاب محصول', 'rezajordaan' ), 0, 6, 1, 'px' ); ?>
						<label class="rj-color-field"><span><?php esc_html_e( 'رنگ قاب محصول', 'rezajordaan' ); ?></span><input type="color" name="rezajordaan_settings[product_border_color]" value="<?php echo esc_attr( rezajordaan_get_setting( 'product_border_color' ) ); ?>"></label>
					</div>
					<div class="rj-settings__card">
						<h2><?php esc_html_e( 'اسلایدر جدیدترین‌ها', 'rezajordaan' ); ?></h2>
						<?php rezajordaan_setting_range( 'latest_products_count', __( 'تعداد محصولات', 'rezajordaan' ), 4, 20 ); ?>
						<?php rezajordaan_setting_range( 'marquee_speed', __( 'سرعت حرکت', 'rezajordaan' ), 15, 100, 1, 'px/s' ); ?>
						<?php rezajordaan_setting_range( 'latest_card_width_desktop', __( 'عرض کارت دسکتاپ', 'rezajordaan' ), 200, 420, 10, 'px' ); ?>
						<?php rezajordaan_setting_range( 'latest_card_width_mobile', __( 'عرض کارت موبایل', 'rezajordaan' ), 150, 300, 10, 'px' ); ?>
						<?php rezajordaan_setting_range( 'latest_image_height_desktop', __( 'ارتفاع تصویر دسکتاپ', 'rezajordaan' ), 220, 560, 10, 'px' ); ?>
						<?php rezajordaan_setting_range( 'latest_image_height_mobile', __( 'ارتفاع تصویر موبایل', 'rezajordaan' ), 180, 420, 10, 'px' ); ?>
						<?php rezajordaan_setting_range( 'product_card_gap', __( 'فاصله کارت‌ها', 'rezajordaan' ), 6, 48, 2, 'px' ); ?>
					</div>
					<div class="rj-settings__card">
						<h2><?php esc_html_e( 'برچسب تخفیف', 'rezajordaan' ); ?></h2>
						<?php rezajordaan_setting_switch( 'show_sale_badge', __( 'نمایش برچسب تخفیف روی محصول', 'rezajordaan' ) ); ?>
						<label><span><?php esc_html_e( 'متن برچسب', 'rezajordaan' ); ?></span><input type="text" name="rezajordaan_settings[sale_badge_text]" value="<?php echo esc_attr( rezajordaan_get_setting( 'sale_badge_text' ) ); ?>" placeholder="٪{percent} تخفیف"></label>
						<p class="description"><?php esc_html_e( 'برای نمایش درصد واقعی از {percent} داخل متن استفاده کنید.', 'rezajordaan' ); ?></p>
						<div class="rj-color-row">
							<label class="rj-color-field"><span><?php esc_html_e( 'رنگ زمینه', 'rezajordaan' ); ?></span><input type="color" name="rezajordaan_settings[sale_badge_background]" value="<?php echo esc_attr( rezajordaan_get_setting( 'sale_badge_background' ) ); ?>"></label>
							<label class="rj-color-field"><span><?php esc_html_e( 'رنگ نوشته', 'rezajordaan' ); ?></span><input type="color" name="rezajordaan_settings[sale_badge_color]" value="<?php echo esc_attr( rezajordaan_get_setting( 'sale_badge_color' ) ); ?>"></label>
						</div>
					</div>
				</div>
			</section>

			<div class="rj-settings__save">
				<?php submit_button( __( 'ذخیره تنظیمات', 'rezajordaan' ), 'primary', 'submit', false ); ?>
			</div>
		</form>
	</div>
	<?php
}
