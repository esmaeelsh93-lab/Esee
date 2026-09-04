<?php
/**
 * In-dashboard notification center.
 *
 * @package Shojaei_SEO_For_Woo
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Shojaei_SEO_Notifications
 */
class Shojaei_SEO_Notifications {

	private const OPTION_KEY = 'shojaei_seo_notifications';
	private const MAX_ITEMS  = 50;

	/**
	 * Types that should not spam — new entry replaces older ones of same type.
	 *
	 * @var string[]
	 */
	private const DEDUPE_TYPES = array( 'initial_scan', 'weekly_summary', 'gsc_connected', 'batch_done', 'link_watchdog' );

	/**
	 * Add a notification.
	 *
	 * @param string $type       Notification type slug.
	 * @param string $message    Human-readable message.
	 * @param int    $product_id Related product ID.
	 * @param string $link       Optional admin link.
	 * @param string $link_label Optional CTA label (default: مشاهده / contextual).
	 */
	public static function add( string $type, string $message, int $product_id = 0, string $link = '', string $link_label = '' ): void {
		$items = self::get_all_raw();

		// Collapse spammy types (e.g. repeated «اسکن کامل شد»).
		if ( in_array( $type, self::DEDUPE_TYPES, true ) ) {
			$items = array_values(
				array_filter(
					$items,
					static function ( $item ) use ( $type ) {
						return ( $item['type'] ?? '' ) !== $type;
					}
				)
			);
		}

		if ( '' === $link ) {
			if ( $product_id > 0 ) {
				$link       = admin_url( 'admin.php?page=shojaei-seo&tab=test&product_id=' . $product_id );
				$link_label = $link_label ?: __( 'تست این محصول', 'shojaei-seo-for-woo' );
			} else {
				// Informational only — no fake «مشاهده» to the same empty place.
				$link       = '';
				$link_label = '';
			}
		} elseif ( '' === $link_label ) {
			$link_label = self::default_link_label( $type, $product_id, $link );
		}

		$items[] = array(
			'id'         => uniqid( 'shojaei_', true ),
			'type'       => $type,
			'message'    => $message,
			'product_id' => $product_id,
			'link'       => $link,
			'link_label' => $link_label,
			'created_at' => current_time( 'mysql' ),
			'read'       => false,
		);

		if ( count( $items ) > self::MAX_ITEMS ) {
			$items = array_slice( $items, -self::MAX_ITEMS );
		}

		update_option( self::OPTION_KEY, $items, false );
	}

	/**
	 * Default CTA label for a notification link.
	 *
	 * @param string $type Type.
	 * @param int    $product_id Product.
	 * @param string $link Link URL.
	 */
	public static function default_link_label( string $type, int $product_id, string $link ): string {
		if ( $product_id > 0 ) {
			return __( 'تست این محصول', 'shojaei-seo-for-woo' );
		}
		if ( 'initial_scan' === $type || false !== strpos( $link, 'tab=oos' ) ) {
			return __( 'لیست ناموجودها', 'shojaei-seo-for-woo' );
		}
		if ( false !== strpos( $link, 'tab=dashboard' ) ) {
			return __( 'رفتن به داشبورد', 'shojaei-seo-for-woo' );
		}
		if ( false !== strpos( $link, 'tab=simulate' ) ) {
			return __( 'Dry-Run / Undo', 'shojaei-seo-for-woo' );
		}
		return __( 'باز کردن', 'shojaei-seo-for-woo' );
	}

	/**
	 * Whether this notice should show an action link.
	 *
	 * @param array $notice Notice row.
	 */
	public static function has_action_link( array $notice ): bool {
		$link = trim( (string) ( $notice['link'] ?? '' ) );
		if ( '' === $link ) {
			return false;
		}
		// Never point «مشاهده» at the notifications tab itself.
		if ( false !== strpos( $link, 'tab=notifications' ) ) {
			return false;
		}
		$pid = absint( $notice['product_id'] ?? 0 );
		if ( $pid > 0 ) {
			return true;
		}
		// System notices need an explicit useful destination (oos / dashboard / …).
		return (bool) preg_match( '/[?&]tab=(oos|dashboard|impact|simulate|wizard|test|settings)/', $link );
	}

	/**
	 * Raw list (oldest first) for writes.
	 *
	 * @return array
	 */
	private static function get_all_raw(): array {
		$items = get_option( self::OPTION_KEY, array() );
		return is_array( $items ) ? $items : array();
	}

	/**
	 * Get all notifications (newest first).
	 * Normalizes legacy links so «مشاهده» is never a dead end.
	 *
	 * @return array
	 */
	public static function get_all(): array {
		$items = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $items ) ) {
			return array();
		}

		$changed = false;
		foreach ( $items as &$item ) {
			$pid  = absint( $item['product_id'] ?? 0 );
			$type = (string) ( $item['type'] ?? '' );
			$link = (string) ( $item['link'] ?? '' );

			if ( $pid > 0 ) {
				$fixed = admin_url( 'admin.php?page=shojaei-seo&tab=test&product_id=' . $pid );
				if ( $link !== $fixed ) {
					$item['link'] = $fixed;
					$changed      = true;
				}
				if ( empty( $item['link_label'] ) ) {
					$item['link_label'] = __( 'تست این محصول', 'shojaei-seo-for-woo' );
					$changed            = true;
				}
			} elseif ( 'initial_scan' === $type ) {
				// Scan notices: one useful CTA — OOS list — or no link if message is enough.
				$fixed = admin_url( 'admin.php?page=shojaei-seo&tab=oos' );
				if ( $link !== $fixed ) {
					$item['link'] = $fixed;
					$changed      = true;
				}
				if ( empty( $item['link_label'] ) || __( 'مشاهده', 'shojaei-seo-for-woo' ) === ( $item['link_label'] ?? '' ) ) {
					$item['link_label'] = __( 'لیست ناموجودها', 'shojaei-seo-for-woo' );
					$changed            = true;
				}
			} elseif ( '' === $link || false !== strpos( $link, 'tab=notifications' ) ) {
				$item['link']       = '';
				$item['link_label'] = '';
				$changed            = true;
			} elseif ( empty( $item['link_label'] ) ) {
				$item['link_label'] = self::default_link_label( $type, $pid, $link );
				$changed            = true;
			}
		}
		unset( $item );

		// Collapse duplicate initial_scan rows already stored.
		$seen_scan = false;
		$cleaned   = array();
		foreach ( array_reverse( $items ) as $item ) { // newest first while building
			if ( 'initial_scan' === ( $item['type'] ?? '' ) ) {
				if ( $seen_scan ) {
					$changed = true;
					continue;
				}
				$seen_scan = true;
			}
			$cleaned[] = $item;
		}
		$items = array_reverse( $cleaned ); // back to oldest-first for storage

		if ( $changed ) {
			update_option( self::OPTION_KEY, $items, false );
		}

		return array_reverse( $items );
	}

	/**
	 * Get unread notifications.
	 *
	 * @return array
	 */
	public static function get_unread(): array {
		return array_values( array_filter( self::get_all(), function ( $item ) {
			return empty( $item['read'] );
		} ) );
	}

	/**
	 * Count unread notifications (fast — no normalize / write-on-read).
	 */
	public static function unread_count(): int {
		$items = self::get_all_raw();
		$n     = 0;
		foreach ( $items as $item ) {
			if ( empty( $item['read'] ) ) {
				$n++;
			}
		}
		return $n;
	}

	/**
	 * Mark one notification as read.
	 *
	 * @param string $id Notification ID.
	 */
	public static function mark_read( string $id ): void {
		$items = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $items ) ) {
			return;
		}

		foreach ( $items as &$item ) {
			if ( ( $item['id'] ?? '' ) === $id ) {
				$item['read'] = true;
			}
		}

		update_option( self::OPTION_KEY, $items, false );
	}

	/**
	 * Mark all as read.
	 */
	public static function mark_all_read(): void {
		$items = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $items ) ) {
			return;
		}

		foreach ( $items as &$item ) {
			$item['read'] = true;
		}

		update_option( self::OPTION_KEY, $items, false );
	}

	/**
	 * Remove a notification.
	 *
	 * @param string $id Notification ID.
	 */
	public static function dismiss( string $id ): void {
		$items = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $items ) ) {
			return;
		}

		$items = array_values( array_filter( $items, function ( $item ) use ( $id ) {
			return ( $item['id'] ?? '' ) !== $id;
		} ) );

		update_option( self::OPTION_KEY, $items, false );
	}

	/**
	 * Notification icon by type.
	 *
	 * @param string $type Type slug.
	 * @return string Dashicon class.
	 */
	public static function icon_for( string $type ): string {
		$map = array(
			'candidate_redirect' => 'dashicons-warning',
			'auto_redirect'      => 'dashicons-migrate',
			'schema_conflict'    => 'dashicons-code-standards',
			'redirect_loop'      => 'dashicons-image-rotate',
			'needs_manual'       => 'dashicons-lock',
			'dry_run'             => 'dashicons-visibility',
			'back_in_stock'      => 'dashicons-yes-alt',
			'gone_410'           => 'dashicons-dismiss',
			'variable_oos'       => 'dashicons-archive',
			'weekly_summary'     => 'dashicons-chart-area',
			'initial_scan'       => 'dashicons-search',
			'batch_done'         => 'dashicons-performance',
			'gsc_connected'      => 'dashicons-yes-alt',
			'gsc_error'          => 'dashicons-warning',
			'link_at_risk'       => 'dashicons-admin-links',
			'link_watchdog'      => 'dashicons-shield',
		);

		return $map[ $type ] ?? 'dashicons-info';
	}
}
