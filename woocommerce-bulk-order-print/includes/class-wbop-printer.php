<?php
/**
 * Print document renderer.
 *
 * @package WooCommerce_Bulk_Order_Print
 */

defined( 'ABSPATH' ) || exit;

/**
 * Builds printable HTML for selected orders.
 */
class WBOP_Printer {

	/**
	 * Render a standalone printable document and exit.
	 *
	 * @param array $order_ids Order IDs.
	 * @return void
	 */
	public function render( $order_ids ) {
		$order_ids = $this->normalize_ids( $order_ids );
		if ( empty( $order_ids ) ) {
			wp_die(
				esc_html( 'لطفاً حداقل یک سفارش را انتخاب کنید.' ),
				esc_html( 'پرینت هوشمند شجاعی' ),
				array( 'response' => 400 )
			);
		}

		$orders = array();
		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order instanceof WC_Order ) {
				$orders[] = $order;
			}
		}

		if ( empty( $orders ) ) {
			wp_die(
				esc_html( 'هیچ سفارش معتبری برای چاپ یافت نشد.' ),
				esc_html( 'پرینت هوشمند شجاعی' ),
				array( 'response' => 404 )
			);
		}

		$printed_ids = array();
		foreach ( $orders as $printed_order ) {
			$printed_ids[] = $printed_order->get_id();
		}
		WBOP_Settings::mark_printed( $printed_ids );

		$settings = WBOP_Settings::get();
		$page_css = WBOP_Settings::page_size_css( $settings );

		nocache_headers();
		header( 'Content-Type: text/html; charset=UTF-8' );

		echo '<!DOCTYPE html><html lang="fa" dir="rtl"><head><meta charset="utf-8">';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
		echo '<title>' . esc_html( 'چاپ سفارش‌ها' ) . '</title>';
		echo '<style>' . $this->print_css( $page_css ) . '</style>';
		echo '</head><body class="wbop-print-body">';
		echo '<div class="wbop-print-toolbar no-print">';
		echo '<button type="button" onclick="window.print()">' . esc_html( 'چاپ' ) . '</button> ';
		echo '<a href="' . esc_url( admin_url( 'admin.php?page=woocommerce-bulk-order-print&tab=operations' ) ) . '">' . esc_html( 'بازگشت' ) . '</a>';
		echo '</div>';

		$total = count( $orders );
		$index = 0;
		foreach ( $orders as $order ) {
			$index++;
			$this->render_order( $order, $settings, $index, $total );
		}

		echo '<script>window.addEventListener("load",function(){setTimeout(function(){window.print();},250);});</script>';
		echo '</body></html>';
		exit;
	}

	/**
	 * Normalize and validate order IDs.
	 *
	 * @param array $order_ids Raw IDs.
	 * @return array
	 */
	private function normalize_ids( $order_ids ) {
		$clean = array();
		foreach ( (array) $order_ids as $id ) {
			$id = absint( $id );
			if ( $id > 0 ) {
				$clean[] = $id;
			}
		}
		return array_values( array_unique( $clean ) );
	}

	/**
	 * Compact black-and-white print CSS.
	 *
	 * @param string $page_css Safe @page rule.
	 * @return string
	 */
	private function print_css( $page_css ) {
		$css  = $page_css;
		$css .= 'html,body{margin:0;padding:0;background:#fff;color:#111;font-family:Tahoma,Arial,sans-serif;font-size:11px;line-height:1.35;}';
		$css .= '.wbop-print-body{-webkit-print-color-adjust:exact;print-color-adjust:exact;}';
		$css .= '.no-print{display:block;}';
		$css .= '.wbop-print-toolbar{padding:10px 12px;background:#f5f5f5;border-bottom:1px solid #999;margin-bottom:10px;}';
		$css .= '.wbop-print-toolbar button,.wbop-print-toolbar a{font-family:Tahoma,Arial,sans-serif;font-size:13px;margin-left:8px;}';
		$css .= '.wbop-print-order{break-inside:avoid;page-break-inside:avoid;border:1px solid #777;padding:8px;box-sizing:border-box;background:#fff;}';
		$css .= '.wbop-print-order:not(:last-child){break-after:page;page-break-after:always;margin-bottom:0;}';
		$css .= '.wbop-print-order:last-child{break-after:auto;page-break-after:auto;}';
		$css .= '.wbop-block{break-inside:avoid;page-break-inside:avoid;margin:0 0 6px;}';
		$css .= '.wbop-header{display:flex;align-items:center;justify-content:space-between;gap:8px;border-bottom:1px solid #777;padding-bottom:6px;margin-bottom:6px;}';
		$css .= '.wbop-header img{max-height:42px;max-width:160px;width:auto;height:auto;filter:grayscale(100%);-webkit-filter:grayscale(100%);}';
		$css .= '.wbop-title{font-size:14px;font-weight:700;margin:0;}';
		$css .= '.wbop-meta{color:#444;font-size:10px;}';
		$css .= '.wbop-grid{display:flex;gap:8px;}';
		$css .= '.wbop-grid > div{flex:1;border:1px solid #999;padding:5px 6px;}';
		$css .= '.wbop-label{font-weight:700;margin-bottom:2px;}';
		$css .= 'table.wbop-items{width:100%;border-collapse:collapse;margin:4px 0;}';
		$css .= 'table.wbop-items th,table.wbop-items td{border:1px solid #777;padding:3px 5px;text-align:right;vertical-align:top;}';
		$css .= 'table.wbop-items th{background:#f2f2f2;font-weight:700;}';
		$css .= 'table.wbop-items tr{break-inside:avoid;page-break-inside:avoid;}';
		$css .= '.wbop-totals{width:100%;border-collapse:collapse;margin-top:4px;}';
		$css .= '.wbop-totals td{padding:2px 4px;border:none;}';
		$css .= '.wbop-totals .wbop-total-row td{border-top:1px solid #777;font-weight:700;padding-top:4px;}';
		$css .= '.wbop-note{border:1px dashed #777;padding:5px 6px;background:#fff;color:#111;}';
		$css .= '.wbop-shipping{border:1px solid #999;padding:5px 6px;}';
		$css .= '@media print{.no-print{display:none!important;}.wbop-print-order{border-color:#000;}}';
		return $css;
	}

	/**
	 * Render one order section.
	 *
	 * @param WC_Order $order    Order object.
	 * @param array    $settings Plugin settings.
	 * @param int      $index    Current index.
	 * @param int      $total    Total orders.
	 * @return void
	 */
	private function render_order( $order, $settings, $index, $total ) {
		$billing_name = trim( $order->get_formatted_billing_full_name() );
		if ( '' === $billing_name ) {
			$billing_name = trim( $order->get_billing_first_name() . ' ' . $order->get_billing_last_name() );
		}

		$phone   = $order->get_billing_phone();
		$address = $this->format_address( $order );
		$note    = trim( (string) $order->get_customer_note() );
		$ship    = $this->shipping_method_label( $order );
		$header  = absint( $settings['header_image'] );
		$img     = $header ? wp_get_attachment_image_url( $header, 'medium' ) : '';

		echo '<section class="wbop-print-order">';

		echo '<div class="wbop-header wbop-block">';
		echo '<div>';
		echo '<p class="wbop-title">' . esc_html( 'فاکتور سفارش #' . $order->get_order_number() ) . '</p>';
		echo '<div class="wbop-meta">' . esc_html( 'تاریخ: ' . wc_format_datetime( $order->get_date_created(), 'Y/m/d H:i' ) );
		echo ' | ' . esc_html( 'برگه ' . $index . ' از ' . $total ) . '</div>';
		echo '</div>';
		if ( $img ) {
			echo '<img src="' . esc_url( $img ) . '" alt="' . esc_attr( 'سربرگ' ) . '">';
		}
		echo '</div>';

		echo '<div class="wbop-grid wbop-block">';
		echo '<div>';
		echo '<div class="wbop-label">' . esc_html( 'گیرنده' ) . '</div>';
		echo '<div>' . esc_html( $billing_name ) . '</div>';
		if ( $phone ) {
			echo '<div>' . esc_html( 'تلفن: ' . $phone ) . '</div>';
		}
		if ( $address ) {
			echo '<div>' . esc_html( $address ) . '</div>';
		}
		echo '</div>';
		echo '<div>';
		echo '<div class="wbop-label">' . esc_html( 'فرستنده' ) . '</div>';
		if ( ! empty( $settings['sender_name'] ) ) {
			echo '<div>' . esc_html( $settings['sender_name'] ) . '</div>';
		}
		if ( ! empty( $settings['sender_phone'] ) ) {
			echo '<div>' . esc_html( 'تلفن: ' . $settings['sender_phone'] ) . '</div>';
		}
		if ( ! empty( $settings['sender_address'] ) ) {
			echo '<div>' . esc_html( $settings['sender_address'] ) . '</div>';
		}
		echo '</div>';
		echo '</div>';

		if ( $ship ) {
			echo '<div class="wbop-shipping wbop-block">';
			echo '<span class="wbop-label">' . esc_html( 'نحوه ارسال' ) . ':</span> ';
			echo esc_html( $ship );
			echo '</div>';
		}

		if ( '' !== $note ) {
			echo '<div class="wbop-note wbop-block">';
			echo '<div class="wbop-label">' . esc_html( 'توضیحات مشتری' ) . '</div>';
			echo '<div>' . esc_html( $note ) . '</div>';
			echo '</div>';
		}

		echo '<table class="wbop-items wbop-block"><thead><tr>';
		echo '<th>' . esc_html( 'کالا' ) . '</th>';
		echo '<th>' . esc_html( 'تعداد' ) . '</th>';
		echo '<th>' . esc_html( 'مبلغ' ) . '</th>';
		echo '</tr></thead><tbody>';

		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof WC_Order_Item_Product ) {
				continue;
			}
			$name = $item->get_name();
			$qty  = $item->get_quantity();
			$total_item = $item->get_total();
			echo '<tr>';
			echo '<td>' . esc_html( $name ) . '</td>';
			echo '<td>' . esc_html( (string) $qty ) . '</td>';
			echo '<td>' . wp_kses_post( wc_price( $total_item, array( 'currency' => $order->get_currency() ) ) ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		echo '<table class="wbop-totals wbop-block">';
		echo '<tr><td>' . esc_html( 'جمع جزء' ) . '</td><td>' . wp_kses_post( wc_price( $order->get_subtotal(), array( 'currency' => $order->get_currency() ) ) ) . '</td></tr>';
		if ( (float) $order->get_shipping_total() > 0 ) {
			echo '<tr><td>' . esc_html( 'هزینه ارسال' ) . '</td><td>' . wp_kses_post( wc_price( $order->get_shipping_total(), array( 'currency' => $order->get_currency() ) ) ) . '</td></tr>';
		}
		if ( (float) $order->get_discount_total() > 0 ) {
			echo '<tr><td>' . esc_html( 'تخفیف' ) . '</td><td>' . wp_kses_post( wc_price( $order->get_discount_total(), array( 'currency' => $order->get_currency() ) ) ) . '</td></tr>';
		}
		echo '<tr class="wbop-total-row"><td>' . esc_html( 'مبلغ کل' ) . '</td><td>' . wp_kses_post( $order->get_formatted_order_total() ) . '</td></tr>';
		echo '</table>';

		$payment = $order->get_payment_method_title();
		if ( $payment ) {
			echo '<div class="wbop-meta wbop-block">' . esc_html( 'روش پرداخت: ' . $payment ) . '</div>';
		}

		echo '</section>';
	}

	/**
	 * Build a compact shipping address string.
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	private function format_address( $order ) {
		$parts = array_filter(
			array(
				$order->get_shipping_state() ? $order->get_shipping_state() : $order->get_billing_state(),
				$order->get_shipping_city() ? $order->get_shipping_city() : $order->get_billing_city(),
				$order->get_shipping_address_1() ? $order->get_shipping_address_1() : $order->get_billing_address_1(),
				$order->get_shipping_address_2() ? $order->get_shipping_address_2() : $order->get_billing_address_2(),
				$order->get_shipping_postcode() ? $order->get_shipping_postcode() : $order->get_billing_postcode(),
			)
		);

		return implode( '، ', $parts );
	}

	/**
	 * Shipping method label (not "actual shipping method").
	 *
	 * @param WC_Order $order Order.
	 * @return string
	 */
	private function shipping_method_label( $order ) {
		$titles = array();
		foreach ( $order->get_shipping_methods() as $method ) {
			$title = $method->get_name();
			if ( $title ) {
				$titles[] = $title;
			}
		}
		if ( ! empty( $titles ) ) {
			return implode( '، ', $titles );
		}

		$method = $order->get_shipping_method();
		return is_string( $method ) ? $method : '';
	}
}
