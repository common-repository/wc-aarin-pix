<?php
namespace WAAP\Shortcodes;

use WC_Aarin_Pix;

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

class Pay_For_Order {

	function __construct() {
		add_shortcode( 'aarin_pay_for_order', [ $this, 'output' ] );
	}

	protected function get_formatted_notice( $message, $notice_type = 'error' ) {
		if ( is_admin() || ! function_exists( 'wc_get_notice_data_attr' ) ) {
			return $message;
		}

		ob_start();

		wc_get_template(
			"notices/{$notice_type}.php",
			[
				'notices'  => [
					[
					'notice' => $message,
					'data'   => [],
					],
				],
			]
		);

		return ob_get_clean();
	}


	public function output( $atts = [] ) {
		$id  = filter_input( INPUT_GET, 'id', FILTER_SANITIZE_NUMBER_INT );
		$key = filter_input( INPUT_GET, 'key', FILTER_SANITIZE_STRING );

		$atts = shortcode_atts(
			[
				'id'  => isset( $id ) ? $id : '',
				'key' => isset( $key ) ? $key : ''
			],
			$atts,
			'aarin_pay_for_order'
		);

		if ( empty( $atts['id'] ) || empty( $atts['key'] ) ) {
			return $this->get_formatted_notice( 'URl inválida.' );
		}

		$order_id = intval( $atts['id'] );
		$key      = esc_attr( $atts['key'] );
		$order    = wc_get_order( $order_id );

		if ( ! $order ) {
			return $this->get_formatted_notice( 'Pedido inválido.' );
		}

		$order_key = $order->get_order_key();

		if ( $key !== $order_key ) {
			return $this->get_formatted_notice( 'Sem permissões para visualizar este link.' );
		}

		if ( $order->has_status( [ 'cancelled', 'failed' ] ) ) {
			return $this->get_formatted_notice( 'Este pedido não pode ser pago. Por favor, faça uma nova compra.' );
		}

		if ( $order->is_paid() ) {
			return $this->get_formatted_notice( 'Este pedido já está pago.', 'success' );
		}

		$qr_code = $order->get_meta( 'wc_aarin_qr_code' );
		$emv     = $order->get_meta( 'wc_aarin_emv' );

		if ( ! $qr_code || ! $emv ) {
			return $this->get_formatted_notice( 'Dados inválidos. Entre em contato para obter assistência.' );
		}

		$emv = is_array( $emv ) ? array_shift( $emv ) : $emv;

		ob_start();

		wc_get_template(
			'thank-you-page.php',
			[
				'qr_code' => $qr_code,
				'emv'     => $emv,
			],
			'',
			WC_Aarin_Pix::get_templates_path()
		);

		return ob_get_clean();
	}
}
