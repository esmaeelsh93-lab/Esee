<?php
/**
 * Lightweight HTTP senders. One short request per message; no daemons, no VPS.
 *
 * @package Esee_Order_Messenger
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Esee_OM_Sender {

	const HOOK = 'esee_om_send_event';

	public function hooks() {
		add_action( 'woocommerce_checkout_order_processed', array( $this, 'queue_new_order' ), 30, 3 );
		add_action( 'woocommerce_order_status_completed', array( $this, 'queue_completed' ), 20, 2 );
		add_action( self::HOOK, array( $this, 'run_send' ), 10, 2 );
		add_action( 'add_meta_boxes', array( $this, 'metabox' ) );
		add_action( 'save_post_shop_order', array( $this, 'save_metabox' ) );
		add_action( 'woocommerce_process_shop_order_meta', array( $this, 'save_metabox' ) );
	}

	public function queue_new_order( $order_id, $posted_data, $order ) {
		unset( $posted_data );
		$this->queue( $order ? $order->get_id() : $order_id, 'new' );
	}

	public function queue_completed( $order_id, $order = null ) {
		unset( $order );
		$this->queue( $order_id, 'done' );
	}

	public function queue( $order_id, $kind ) {
		$settings = Esee_OM_Settings::get();
		if ( '1' !== $settings['enabled'] ) {
			return;
		}

		$order_id = absint( $order_id );
		if ( ! $order_id ) {
			return;
		}

		if ( function_exists( 'as_enqueue_async_action' ) ) {
			as_enqueue_async_action( self::HOOK, array( $order_id, $kind ), 'esee-om' );
			return;
		}

		wp_schedule_single_event( time() + 5, self::HOOK, array( $order_id, $kind ) );
	}

	public function run_send( $order_id, $kind ) {
		$order = wc_get_order( absint( $order_id ) );
		if ( ! $order ) {
			return;
		}

		$kind     = ( 'done' === $kind ) ? 'done' : 'new';
		$flag     = '_esee_om_sent_' . $kind;
		$settings = Esee_OM_Settings::get();

		if ( $order->get_meta( $flag ) ) {
			return;
		}

		$channels = Esee_OM_Utils::sanitize_channels( $order->get_meta( '_esee_om_channels' ) );
		if ( '1' === $settings['require_opt_in'] && empty( $channels ) ) {
			return;
		}

		if ( empty( $channels ) ) {
			$channels = array();
			if ( '1' === $settings['whatsapp_enabled'] ) {
				$channels[] = 'whatsapp';
			}
			if ( '1' === $settings['bale_enabled'] ) {
				$channels[] = 'bale';
			}
			if ( '1' === $settings['rubika_enabled'] ) {
				$channels[] = 'rubika';
			}
		}

		$text   = $this->build_text( $order, $kind, $settings );
		$phone  = $order->get_meta( '_esee_om_phone' );
		if ( ! $phone ) {
			$phone = Esee_OM_Utils::normalize_phone( $order->get_billing_phone() );
			$order->update_meta_data( '_esee_om_phone', $phone );
		}

		$log = array();
		foreach ( $channels as $channel ) {
			if ( 'whatsapp' === $channel && '1' === $settings['whatsapp_enabled'] ) {
				$result = $this->send_whatsapp( $settings, $phone, $text, $kind );
				$log[]  = 'whatsapp: ' . $result['message'];
				if ( ! empty( $result['not_on_whatsapp'] ) ) {
					$channels = array_values( array_diff( $channels, array( 'whatsapp' ) ) );
					$order->update_meta_data( '_esee_om_channels', $channels );
				}
			} elseif ( 'bale' === $channel && '1' === $settings['bale_enabled'] ) {
				$result = $this->send_bale( $settings, $order, $phone, $text );
				$log[]  = 'bale: ' . $result;
			} elseif ( 'rubika' === $channel && '1' === $settings['rubika_enabled'] ) {
				$result = $this->send_rubika( $settings, $order, $text );
				$log[]  = 'rubika: ' . $result;
			}
		}

		$order->update_meta_data( $flag, current_time( 'mysql' ) );
		$order->add_order_note( 'پیام‌رسان سفارش (' . $kind . '): ' . implode( ' | ', $log ) );
		$order->save();
	}

	public function metabox() {
		$screen = function_exists( 'wc_get_page_screen_id' ) ? wc_get_page_screen_id( 'shop-order' ) : 'shop_order';
		add_meta_box(
			'esee-om-order',
			'پیام‌رسان مشتری',
			array( $this, 'render_metabox' ),
			$screen,
			'side'
		);
		add_meta_box(
			'esee-om-order',
			'پیام‌رسان مشتری',
			array( $this, 'render_metabox' ),
			'shop_order',
			'side'
		);
	}

	public function render_metabox( $post_or_order ) {
		$order = $post_or_order instanceof WC_Order ? $post_or_order : wc_get_order( $post_or_order->ID );
		if ( ! $order ) {
			return;
		}

		$channels = Esee_OM_Utils::sanitize_channels( $order->get_meta( '_esee_om_channels' ) );
		wp_nonce_field( 'esee_om_order', 'esee_om_order_nonce' );
		foreach ( array( 'whatsapp' => 'واتساپ', 'rubika' => 'روبیکا', 'bale' => 'بله' ) as $key => $label ) {
			echo '<label><input type="checkbox" name="esee_om_admin_channels[]" value="' . esc_attr( $key ) . '" ' . checked( in_array( $key, $channels, true ), true, false ) . '> ' . esc_html( $label ) . '</label><br>';
		}
		echo '<p class="description">شماره نرمال‌شده: ' . esc_html( $order->get_meta( '_esee_om_phone' ) ) . '</p>';
		echo '<p class="description">chat_id بله: ' . esc_html( $order->get_meta( '_esee_om_bale_chat_id' ) ) . '</p>';
		echo '<p class="description">chat_id روبیکا: ' . esc_html( $order->get_meta( '_esee_om_rubika_chat_id' ) ) . '</p>';
	}

	public function save_metabox( $post_id ) {
		if ( empty( $_POST['esee_om_order_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['esee_om_order_nonce'] ) ), 'esee_om_order' ) ) {
			return;
		}

		$order = wc_get_order( $post_id );
		if ( ! $order ) {
			return;
		}

		$posted = isset( $_POST['esee_om_admin_channels'] ) ? wp_unslash( $_POST['esee_om_admin_channels'] ) : array();
		$order->update_meta_data( '_esee_om_channels', Esee_OM_Utils::sanitize_channels( $posted ) );
		$order->save();
	}

	private function build_text( $order, $kind, $settings ) {
		$items = array();
		foreach ( $order->get_items() as $item ) {
			$items[] = $item->get_name() . ' × ' . $item->get_quantity();
		}

		$template = ( 'done' === $kind ) ? $settings['template_done'] : $settings['template_new'];

		return Esee_OM_Utils::render_template(
			$template,
			array(
				'order_id'    => $order->get_order_number(),
				'first_name'  => $order->get_billing_first_name(),
				'last_name'   => $order->get_billing_last_name(),
				'phone'       => $order->get_billing_phone(),
				'total'       => wp_strip_all_tags( $order->get_formatted_order_total() ),
				'status'      => wc_get_order_status_name( $order->get_status() ),
				'items'       => implode( "\n", $items ),
				'custom_note' => $settings['custom_note'],
			)
		);
	}

	private function send_whatsapp( $settings, $phone, $text, $kind ) {
		if ( ! $settings['whatsapp_token'] || ! $settings['whatsapp_phone_id'] || ! $phone ) {
			return array( 'ok' => false, 'message' => 'تنظیمات یا شماره ناقص است', 'not_on_whatsapp' => false );
		}

		$template_name = ( 'done' === $kind ) ? $settings['whatsapp_template_done'] : $settings['whatsapp_template_new'];
		$url           = 'https://graph.facebook.com/v21.0/' . rawurlencode( $settings['whatsapp_phone_id'] ) . '/messages';

		if ( $template_name ) {
			$body = array(
				'messaging_product' => 'whatsapp',
				'to'                => $phone,
				'type'              => 'template',
				'template'          => array(
					'name'     => $template_name,
					'language' => array( 'code' => $settings['whatsapp_lang'] ? $settings['whatsapp_lang'] : 'fa' ),
				),
			);
		} else {
			$body = array(
				'messaging_product' => 'whatsapp',
				'to'                => $phone,
				'type'              => 'text',
				'text'              => array( 'body' => $text ),
			);
		}

		$response = $this->post_json(
			$url,
			$body,
			array( 'Authorization' => 'Bearer ' . $settings['whatsapp_token'] )
		);

		$code    = 0;
		$message = $response['raw'];
		if ( is_array( $response['json'] ) ) {
			if ( ! empty( $response['json']['messages'][0]['id'] ) ) {
				return array( 'ok' => true, 'message' => 'ارسال شد', 'not_on_whatsapp' => false );
			}
			if ( ! empty( $response['json']['error']['code'] ) ) {
				$code    = (int) $response['json']['error']['code'];
				$message = (string) $response['json']['error']['message'];
			}
		}

		// 131026: recipient cannot be delivered (often not on WhatsApp).
		$not_on = ( 131026 === $code );
		return array(
			'ok'              => false,
			'message'         => $message,
			'not_on_whatsapp' => $not_on,
		);
	}

	private function send_bale( $settings, $order, $phone, $text ) {
		if ( $settings['bale_safir_token'] && $settings['bale_safir_bot_id'] && $phone ) {
			$response = $this->post_json(
				'https://safir.bale.ai/api/v3/send_message',
				array(
					'bot_id'       => $settings['bale_safir_bot_id'],
					'phone_number' => $phone,
					'message_data' => array(
						'message' => array( 'text' => $text ),
					),
				),
				array( 'Authorization' => 'Bearer ' . $settings['bale_safir_token'] )
			);
			if ( $this->http_ok( $response ) && empty( $response['json']['error_data'] ) ) {
				return 'ارسال سفیر انجام شد';
			}
			$safir_msg = is_array( $response['json'] ) ? wp_json_encode( $response['json'] ) : $response['raw'];
			if ( ! $order->get_meta( '_esee_om_bale_chat_id' ) ) {
				return 'سفیر: ' . $safir_msg;
			}
		}

		$chat_id = $order->get_meta( '_esee_om_bale_chat_id' );
		if ( ! $chat_id || ! $settings['bale_token'] ) {
			return 'منتظر استارت بازو توسط مشتری (chat_id نیست)';
		}

		$response = $this->post_json(
			'https://tapi.bale.ai/bot' . $settings['bale_token'] . '/sendMessage',
			array(
				'chat_id' => $chat_id,
				'text'    => $text,
			)
		);

		if ( ! empty( $response['json']['ok'] ) ) {
			return 'ارسال شد';
		}

		return is_array( $response['json'] ) ? wp_json_encode( $response['json'] ) : $response['raw'];
	}

	private function send_rubika( $settings, $order, $text ) {
		$chat_id = $order->get_meta( '_esee_om_rubika_chat_id' );
		if ( ! $chat_id || ! $settings['rubika_token'] ) {
			return 'منتظر استارت بات توسط مشتری (chat_id نیست)';
		}

		$response = $this->post_json(
			'https://botapi.rubika.ir/v3/' . $settings['rubika_token'] . '/sendMessage',
			array(
				'chat_id' => $chat_id,
				'text'    => $text,
			)
		);

		if ( $this->http_ok( $response ) ) {
			return 'ارسال شد';
		}

		return is_array( $response['json'] ) ? wp_json_encode( $response['json'] ) : $response['raw'];
	}

	private function http_ok( $response ) {
		return $response['code'] >= 200 && $response['code'] < 300;
	}

	private function post_json( $url, $body, $headers = array() ) {
		$headers = array_merge(
			array( 'Content-Type' => 'application/json; charset=utf-8' ),
			$headers
		);

		$http = wp_remote_post(
			$url,
			array(
				'timeout'  => 8,
				'blocking' => true,
				'headers'  => $headers,
				'body'     => wp_json_encode( $body ),
			)
		);

		if ( is_wp_error( $http ) ) {
			return array(
				'code' => 0,
				'raw'  => $http->get_error_message(),
				'json' => null,
			);
		}

		$raw  = wp_remote_retrieve_body( $http );
		$code = (int) wp_remote_retrieve_response_code( $http );
		$json = json_decode( $raw, true );

		return array(
			'code' => $code,
			'raw'  => $raw ? $raw : ( 'HTTP ' . $code ),
			'json' => is_array( $json ) ? $json : null,
		);
	}
}
