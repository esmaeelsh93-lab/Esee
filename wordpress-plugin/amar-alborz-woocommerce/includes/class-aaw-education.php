<?php
/*
 * تهیه شده توسط اسماعیل شجاعی - to3edev.ir
 */

/**
 * منوی مستقل «آموزش»: راهنمای فارسی کامل استفاده از افزونه، اصطلاحات، رفع خطا، سوالات متداول
 * و راهنمای تفسیر گزارش‌ها؛ کاملاً مستقل از منوی اصلی «آمار البرز».
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class AAW_Education {

	const CAPABILITY  = 'manage_options';
	const PAGE_SLUG    = 'aaw-education';

	private static $page_hooks = array();

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_menu' ) );
	}

	public static function add_menu() {
		self::$page_hooks['education'] = add_menu_page(
			'آموزش آمار البرز',
			'آموزش',
			self::CAPABILITY,
			self::PAGE_SLUG,
			array( __CLASS__, 'render_page' ),
			'dashicons-welcome-learn-more',
			27
		);
	}

	public static function get_page_hooks() {
		return self::$page_hooks;
	}

	public static function get_tabs() {
		return array(
			'quickstart'   => array( 'label' => 'شروع سریع', 'icon' => '🚀' ),
			'glossary'     => array( 'label' => 'راهنمای اصطلاحات', 'icon' => '📖' ),
			'page-guides'  => array( 'label' => 'راهنمای هر صفحه', 'icon' => '🗺' ),
			'troubleshoot' => array( 'label' => 'رفع خطاهای متداول', 'icon' => '🛠' ),
			'faq'          => array( 'label' => 'سوالات متداول', 'icon' => '❓' ),
			'speed'        => array( 'label' => 'بهینه‌سازی سرعت', 'icon' => '⚡' ),
			'interpret'    => array( 'label' => 'تفسیر گزارش‌ها', 'icon' => '🧠' ),
			'videos'       => array( 'label' => 'ویدئوهای آموزشی', 'icon' => '🎥' ),
		);
	}

	public static function render_page() {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			wp_die( 'شما دسترسی لازم برای مشاهده‌ی این صفحه را ندارید.' );
		}

		$tabs = self::get_tabs();
		$tab  = AAW_Admin::current_tab( $tabs, 'quickstart' );

		include AAW_PLUGIN_DIR . 'templates/education-page.php';
	}
}
