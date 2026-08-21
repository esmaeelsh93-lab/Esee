<?php
/**
 * Capture chat_id when the customer starts the Bale or Rubika bot.
 *
 * @package Esee_Order_Messenger
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Esee_OM_Webhooks {

	public function hooks() {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes() {
		register_rest_route(
			'esee-om/v1',
			'/bale',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'bale' ),
				'permission_callback' => array( $this, 'check_secret' ),
			)
		);
		register_rest_route(
			'esee-om/v1',
			'/rubika',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'rubika' ),
				'permission_callback' => array( $this, 'check_secret' ),
			)
		);
	}

	public function check_secret( WP_REST_Request $request ) {
		$expected = (string) Esee_OM_Settings::get_field( 'webhook_secret' );
		$given    = (string) $request->get_param( 'secret' );
		return $expected && hash_equals( $expected, $given );
	}

	public function bale( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = $request->get_body_params();
		}

		$message = isset( $payload['message'] ) && is_array( $payload['message'] ) ? $payload['message'] : array();
		$chat_id = isset( $message['chat']['id'] ) ? (string) $message['chat']['id'] : '';
		$text    = isset( $message['text'] ) ? trim( (string) $message['text'] ) : '';
		$phone   = '';

		if ( isset( $message['contact']['phone_number'] ) ) {
			$phone = Esee_OM_Utils::normalize_phone( $message['contact']['phone_number'] );
		}

		$order_id = $this->order_id_from_start( $text );
		$order    = $this->find_order( $order_id, $phone );

		if ( $order && $chat_id ) {
			$order->update_meta_data( '_esee_om_bale_chat_id', $chat_id );
			$order->save();
			$sender = new Esee_OM_Sender();
			$sender->queue( $order->get_id(), 'new' );
		}

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	public function rubika( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = $request->get_body_params();
		}

		$update  = isset( $payload['update'] ) && is_array( $payload['update'] ) ? $payload['update'] : $payload;
		$chat_id = '';
		$text    = '';

		if ( isset( $update['new_message']['sender_id'] ) ) {
			$chat_id = (string) $update['new_message']['sender_id'];
		}
		if ( isset( $update['new_message']['text'] ) ) {
			$text = (string) $update['new_message']['text'];
		}
		if ( isset( $payload['inline_message']['chat_id'] ) ) {
			$chat_id = (string) $payload['inline_message']['chat_id'];
			$text    = isset( $payload['inline_message']['text'] ) ? (string) $payload['inline_message']['text'] : $text;
		}

		$order_id = $this->order_id_from_start( $text );
		$order    = $this->find_order( $order_id, '' );

		if ( $order && $chat_id ) {
			$order->update_meta_data( '_esee_om_rubika_chat_id', $chat_id );
			$order->save();
			$sender = new Esee_OM_Sender();
			$sender->queue( $order->get_id(), 'new' );
		}

		return new WP_REST_Response( array( 'ok' => true ), 200 );
	}

	private function order_id_from_start( $text ) {
		if ( preg_match( '/start[=:\s]+(\d+)/i', $text, $m ) ) {
			return absint( $m[1] );
		}
		if ( preg_match( '/^\/start\s+(\d+)/', $text, $m ) ) {
			return absint( $m[1] );
		}
		if ( preg_match( '/^\d+$/', $text ) ) {
			return absint( $text );
		}
		return 0;
	}

	private function find_order( $order_id, $phone ) {
		if ( $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				return $order;
			}
		}

		if ( ! $phone ) {
			return null;
		}

		$orders = wc_get_orders(
			array(
				'limit'      => 1,
				'orderby'    => 'date',
				'order'      => 'DESC',
				'meta_key'   => '_esee_om_phone',
				'meta_value' => $phone,
			)
		);

		return ! empty( $orders ) ? $orders[0] : null;
	}
}
