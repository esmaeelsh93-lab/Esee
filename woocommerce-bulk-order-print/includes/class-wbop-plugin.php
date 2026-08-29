<?php
/**
 * Main plugin bootstrap and admin UI.
 *
 * @package WooCommerce_Bulk_Order_Print
 */

defined( 'ABSPATH' ) || exit;

/**
 * Core plugin class.
 */
class WBOP_Plugin {

	/**
	 * Allowed admin tabs.
	 *
	 * @var array
	 */
	private $tabs = array( 'operations', 'settings' );

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'admin_post_wbop_print_orders', array( $this, 'handle_print' ) );
		add_action( 'admin_post_wbop_save_settings', array( $this, 'handle_save_settings' ) );
		add_filter( 'plugin_action_links_' . WBOP_BASENAME, array( $this, 'action_links' ) );
	}

	/**
	 * Plugin list action links.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public function action_links( $links ) {
		$url = admin_url( 'admin.php?page=woocommerce-bulk-order-print&tab=settings' );
		array_unshift(
			$links,
			'<a href="' . esc_url( $url ) . '">' . esc_html( 'تنظیمات' ) . '</a>'
		);
		return $links;
	}

	/**
	 * Register WooCommerce submenu.
	 *
	 * @return void
	 */
	public function register_menu() {
		add_submenu_page(
			'woocommerce',
			'پرینت هوشمند شجاعی',
			'پرینت هوشمند شجاعی',
			'manage_woocommerce',
			'woocommerce-bulk-order-print',
			array( $this, 'render_page' )
		);
	}

	/**
	 * Load assets only on plugin page.
	 *
	 * @param string $hook Hook suffix.
	 * @return void
	 */
	public function enqueue_assets( $hook ) {
		if ( 'woocommerce_page_woocommerce-bulk-order-print' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'wbop-admin',
			WBOP_URL . 'assets/admin.css',
			array(),
			WBOP_VERSION
		);

		wp_enqueue_script(
			'wbop-admin',
			WBOP_URL . 'assets/admin.js',
			array(),
			WBOP_VERSION,
			true
		);

		$tab = $this->current_tab();
		if ( 'settings' === $tab ) {
			wp_enqueue_media();
			wp_enqueue_script(
				'wbop-admin-settings',
				WBOP_URL . 'assets/admin-settings.js',
				array( 'jquery' ),
				WBOP_VERSION,
				true
			);
			wp_localize_script(
				'wbop-admin-settings',
				'wbopSettings',
				array(
					'title'  => 'انتخاب تصویر سربرگ',
					'button' => 'استفاده از این تصویر',
				)
			);
		}
	}

	/**
	 * Current validated tab.
	 *
	 * @return string
	 */
	private function current_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'operations'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! in_array( $tab, $this->tabs, true ) ) {
			$tab = 'operations';
		}
		return $tab;
	}

	/**
	 * Render admin page shell.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html( 'دسترسی غیرمجاز.' ) );
		}

		$tab = $this->current_tab();
		?>
		<div class="wrap wbop-wrap" dir="rtl">
			<h1><?php echo esc_html( 'پرینت هوشمند شجاعی' ); ?></h1>

			<?php if ( isset( $_GET['settings-updated'] ) && '1' === $_GET['settings-updated'] ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
				<div class="notice notice-success is-dismissible"><p><?php echo esc_html( 'تنظیمات با موفقیت ذخیره شد.' ); ?></p></div>
			<?php endif; ?>

			<nav class="nav-tab-wrapper wbop-tabs">
				<a class="nav-tab <?php echo 'operations' === $tab ? 'nav-tab-active' : ''; ?>"
					href="<?php echo esc_url( admin_url( 'admin.php?page=woocommerce-bulk-order-print&tab=operations' ) ); ?>">
					<?php echo esc_html( 'عملیات چاپ' ); ?>
				</a>
				<a class="nav-tab <?php echo 'settings' === $tab ? 'nav-tab-active' : ''; ?>"
					href="<?php echo esc_url( admin_url( 'admin.php?page=woocommerce-bulk-order-print&tab=settings' ) ); ?>">
					<?php echo esc_html( 'تنظیمات' ); ?>
				</a>
			</nav>

			<div class="wbop-tab-panel">
				<?php
				if ( 'settings' === $tab ) {
					$this->render_settings_tab();
				} else {
					$this->render_operations_tab();
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Operations tab.
	 *
	 * @return void
	 */
	private function render_operations_tab() {
		$status  = isset( $_GET['order_status'] ) ? sanitize_key( wp_unslash( $_GET['order_status'] ) ) : 'processing'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$limit   = isset( $_GET['order_limit'] ) ? absint( $_GET['order_limit'] ) : 50; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$limit   = max( 1, min( 200, $limit ) );
		$allowed = array_keys( wc_get_order_statuses() );
		if ( ! in_array( 'wc-' . $status, $allowed, true ) && ! in_array( $status, $allowed, true ) ) {
			// Accept both "processing" and "wc-processing".
			if ( 0 === strpos( $status, 'wc-' ) && in_array( $status, $allowed, true ) ) {
				$status = substr( $status, 3 );
			} elseif ( ! in_array( 'wc-' . $status, $allowed, true ) ) {
				$status = 'processing';
			}
		}

		$orders = wc_get_orders(
			array(
				'status'  => $status,
				'limit'   => $limit,
				'orderby' => 'date',
				'order'   => 'DESC',
				'return'  => 'objects',
			)
		);
		?>
		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" class="wbop-filter-form">
			<input type="hidden" name="page" value="woocommerce-bulk-order-print">
			<input type="hidden" name="tab" value="operations">
			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="wbop-order-status"><?php echo esc_html( 'وضعیت سفارشات' ); ?></label></th>
					<td>
						<select name="order_status" id="wbop-order-status">
							<?php foreach ( wc_get_order_statuses() as $slug => $label ) : ?>
								<?php
								$key = ( 0 === strpos( $slug, 'wc-' ) ) ? substr( $slug, 3 ) : $slug;
								?>
								<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wbop-order-limit"><?php echo esc_html( 'تعداد سفارش‌های قابل نمایش' ); ?></label></th>
					<td>
						<input type="number" name="order_limit" id="wbop-order-limit" min="1" max="200" value="<?php echo esc_attr( (string) $limit ); ?>">
					</td>
				</tr>
			</table>
			<p>
				<button type="submit" class="button button-secondary"><?php echo esc_html( 'فیلتر' ); ?></button>
			</p>
		</form>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" id="wbop-print-form" class="wbop-print-form">
			<input type="hidden" name="action" value="wbop_print_orders">
			<?php wp_nonce_field( 'wbop_print_orders', 'wbop_print_nonce' ); ?>

			<table class="widefat striped wbop-orders-table">
				<thead>
					<tr>
						<td class="manage-column column-cb check-column">
							<input type="checkbox" id="wbop-select-all" title="<?php echo esc_attr( 'انتخاب همه' ); ?>">
						</td>
						<th><?php echo esc_html( 'شماره سفارش' ); ?></th>
						<th><?php echo esc_html( 'نام مشتری' ); ?></th>
						<th><?php echo esc_html( 'تلفن' ); ?></th>
						<th><?php echo esc_html( 'تاریخ' ); ?></th>
						<th><?php echo esc_html( 'مبلغ' ); ?></th>
						<th><?php echo esc_html( 'نحوه ارسال' ); ?></th>
						<th><?php echo esc_html( 'وضعیت چاپ' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $orders ) ) : ?>
					<tr><td colspan="8"><?php echo esc_html( 'سفارشی یافت نشد.' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $orders as $order ) : ?>
						<?php
						if ( ! $order instanceof WC_Order ) {
							continue;
						}
						$order_id = $order->get_id();
						$name     = trim( $order->get_formatted_billing_full_name() );
						$printed  = WBOP_Settings::is_printed( $order_id );
						$ship     = $order->get_shipping_method();
						?>
						<tr class="<?php echo $printed ? 'wbop-printed-row' : ''; ?>">
							<th scope="row" class="check-column">
								<input type="checkbox" class="wbop-order-cb" name="order_ids[]" value="<?php echo esc_attr( (string) $order_id ); ?>">
							</th>
							<td>
								<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">
									#<?php echo esc_html( $order->get_order_number() ); ?>
								</a>
							</td>
							<td><?php echo esc_html( $name ); ?></td>
							<td><?php echo esc_html( $order->get_billing_phone() ); ?></td>
							<td><?php echo esc_html( wc_format_datetime( $order->get_date_created(), 'Y/m/d H:i' ) ); ?></td>
							<td><?php echo wp_kses_post( $order->get_formatted_order_total() ); ?></td>
							<td><?php echo esc_html( $ship ? $ship : '—' ); ?></td>
							<td>
								<?php if ( $printed ) : ?>
									<span class="wbop-printed-badge" title="<?php echo esc_attr( 'قبلاً چاپ شده' ); ?>">✓ <?php echo esc_html( 'چاپ‌شده' ); ?></span>
								<?php else : ?>
									<span class="wbop-not-printed"><?php echo esc_html( '—' ); ?></span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>

			<p class="wbop-actions">
				<button type="submit" class="button button-primary button-hero" id="wbop-print-submit">
					<?php echo esc_html( 'چاپ سفارش‌های انتخاب‌شده' ); ?>
				</button>
			</p>
			<p class="description wbop-print-warning" id="wbop-print-warning" hidden>
				<?php echo esc_html( 'لطفاً حداقل یک سفارش را انتخاب کنید.' ); ?>
			</p>
		</form>
		<?php
	}

	/**
	 * Settings tab.
	 *
	 * @return void
	 */
	private function render_settings_tab() {
		$settings = WBOP_Settings::get();
		$header   = absint( $settings['header_image'] );
		$img_url  = $header ? wp_get_attachment_image_url( $header, 'medium' ) : '';
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wbop-settings-form">
			<input type="hidden" name="action" value="wbop_save_settings">
			<?php wp_nonce_field( 'wbop_save_settings', 'wbop_settings_nonce' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="wbop-sender-name"><?php echo esc_html( 'نام فرستنده' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="wbop-sender-name" name="wbop_settings[sender_name]" value="<?php echo esc_attr( $settings['sender_name'] ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wbop-sender-address"><?php echo esc_html( 'آدرس فرستنده' ); ?></label></th>
					<td>
						<textarea class="large-text" rows="3" id="wbop-sender-address" name="wbop_settings[sender_address]"><?php echo esc_textarea( $settings['sender_address'] ); ?></textarea>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wbop-sender-phone"><?php echo esc_html( 'تلفن فرستنده' ); ?></label></th>
					<td>
						<input type="text" class="regular-text" id="wbop-sender-phone" name="wbop_settings[sender_phone]" value="<?php echo esc_attr( $settings['sender_phone'] ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><?php echo esc_html( 'تصویر سربرگ' ); ?></th>
					<td>
						<div class="wbop-media-field">
							<input type="hidden" id="wbop-header-image" name="wbop_settings[header_image]" value="<?php echo esc_attr( (string) $header ); ?>">
							<div id="wbop-header-preview" class="wbop-header-preview">
								<?php if ( $img_url ) : ?>
									<img src="<?php echo esc_url( $img_url ); ?>" alt="">
								<?php endif; ?>
							</div>
							<p>
								<button type="button" class="button" id="wbop-upload-header"><?php echo esc_html( 'انتخاب / آپلود تصویر' ); ?></button>
								<button type="button" class="button" id="wbop-remove-header" <?php disabled( ! $header ); ?>><?php echo esc_html( 'حذف تصویر' ); ?></button>
							</p>
							<p class="description"><?php echo esc_html( 'تصویر از کتابخانه رسانه وردپرس انتخاب می‌شود و در چاپ به‌صورت سیاه‌وسفید نمایش داده می‌شود.' ); ?></p>
						</div>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wbop-paper-type"><?php echo esc_html( 'نوع کاغذ' ); ?></label></th>
					<td>
						<select name="wbop_settings[paper_type]" id="wbop-paper-type">
							<?php foreach ( WBOP_Settings::paper_types() as $value => $label ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['paper_type'], $value ); ?>>
									<?php echo esc_html( $label ); ?>
								</option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr class="wbop-custom-paper-row">
					<th scope="row"><label for="wbop-paper-width"><?php echo esc_html( 'عرض کاغذ سفارشی' ); ?></label></th>
					<td>
						<input type="number" step="0.1" min="5" max="100" id="wbop-paper-width" name="wbop_settings[paper_width]" value="<?php echo esc_attr( (string) $settings['paper_width'] ); ?>">
						<span><?php echo esc_html( 'سانتی‌متر' ); ?></span>
					</td>
				</tr>
				<tr class="wbop-custom-paper-row">
					<th scope="row"><label for="wbop-paper-height"><?php echo esc_html( 'طول کاغذ سفارشی' ); ?></label></th>
					<td>
						<input type="number" step="0.1" min="5" max="100" id="wbop-paper-height" name="wbop_settings[paper_height]" value="<?php echo esc_attr( (string) $settings['paper_height'] ); ?>">
						<span><?php echo esc_html( 'سانتی‌متر' ); ?></span>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wbop-print-margin"><?php echo esc_html( 'حاشیه چاپ (میلی‌متر)' ); ?></label></th>
					<td>
						<input type="number" step="0.1" min="3" max="30" id="wbop-print-margin" name="wbop_settings[print_margin]" value="<?php echo esc_attr( (string) $settings['print_margin'] ); ?>">
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="wbop-font-size"><?php echo esc_html( 'سایز فونت چاپ' ); ?></label></th>
					<td>
						<select name="wbop_settings[font_size]" id="wbop-font-size">
							<?php foreach ( WBOP_Settings::font_sizes() as $value => $meta ) : ?>
								<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $settings['font_size'], $value ); ?>>
									<?php
									echo esc_html(
										$meta['label'] . ' — متن ' . $meta['body'] . 'px'
									);
									?>
								</option>
							<?php endforeach; ?>
						</select>
						<p class="description"><?php echo esc_html( 'پیش‌فرض «کمی بزرگ» یک پله از سایز قبلی خواناتر است. برای برگه‌های شلوغ می‌توانید کوچک‌تر انتخاب کنید.' ); ?></p>
					</td>
				</tr>
			</table>

			<?php submit_button( 'ذخیره تنظیمات' ); ?>
		</form>
		<?php
	}

	/**
	 * Handle print POST.
	 *
	 * @return void
	 */
	public function handle_print() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html( 'دسترسی غیرمجاز.' ), 403 );
		}

		check_admin_referer( 'wbop_print_orders', 'wbop_print_nonce' );

		$raw_ids = isset( $_POST['order_ids'] ) ? (array) wp_unslash( $_POST['order_ids'] ) : array();
		$ids     = array();
		foreach ( $raw_ids as $id ) {
			$id = absint( $id );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		$ids = array_values( array_unique( $ids ) );

		if ( empty( $ids ) ) {
			wp_die(
				esc_html( 'لطفاً حداقل یک سفارش را انتخاب کنید.' ),
				esc_html( 'پرینت هوشمند شجاعی' ),
				array( 'response' => 400 )
			);
		}

		$printer = new WBOP_Printer();
		$printer->render( $ids );
	}

	/**
	 * Handle settings POST.
	 *
	 * @return void
	 */
	public function handle_save_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html( 'دسترسی غیرمجاز.' ), 403 );
		}

		check_admin_referer( 'wbop_save_settings', 'wbop_settings_nonce' );

		$raw = isset( $_POST['wbop_settings'] ) ? (array) wp_unslash( $_POST['wbop_settings'] ) : array();
		$clean = WBOP_Settings::sanitize( $raw );
		update_option( WBOP_Settings::OPTION, $clean, false );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'              => 'woocommerce-bulk-order-print',
					'tab'               => 'settings',
					'settings-updated'  => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
