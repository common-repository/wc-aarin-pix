<?php
namespace WAAP;

use Exception;
use WAAP\Traits\WC_Logger_Trait;

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

class Webhooks {

	use WC_Logger_Trait;

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		add_action( 'woocommerce_api_aarin-pix-notifications', [ $this, 'handle_request' ] );
		add_action( 'wp_ajax_aarin_webhook_setup', [ $this, 'handle_webhook_setup' ] );
	}


	public function handle_request() {
		header( 'Content-Type: application/json' );
		try {
			if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || 'POST' !== $_SERVER['REQUEST_METHOD'] ) {
				throw new Exception( 'Invalid request method', 400 );
			}

			$secret = $this->get_webhook_secret_header();

			if ( ! $secret ) {
				$this->log( 'Secret não informado na requisição' );
				throw new Exception( 'Webhook secret not found!', 400 );
			}

			$this->set_wc_logger_source( 'wc-aarin-webhooks' );

			$data = json_decode( file_get_contents('php://input') );

			if ( ! isset( $data->CobrancaId ) ) {
				throw new Exception( 'Invalid webhook. "CobrancaId" not found.', 400 );
			}

			$order_id = $this->get_order_id_from_charge( $data->CobrancaId );

			if ( ! $order_id ) {
				$this->log( 'Pedido não encontrado para este webhook: ' . print_r( $data, true ) );
				throw new Exception( 'Order ID not found.' );
			}

			$this->log( 'Webhook de pagamento recebido para o pedido #' . $order_id );

			$order = wc_get_order( $order_id );

			if ( ! $order ) {
				$this->log( 'Pedido #' . $order_id . ' não encontrado no WooCommerce.' );

				throw new Exception( 'Order not found.' );
			}

			$gateway = wc_get_payment_gateway_by_order( $order );

			if ( ! isset( $gateway->id ) || 'wc-aarin-pix' !== $gateway->id ) {
				$this->log( 'Pedido não foi feito com Pix Aarin. Método: ' . $gateway->id );

				throw new Exception( 'Invalid order payment method.' );
			}

			if ( ! $gateway->webhook_secret ) {
				$this->log( 'Secret do webhook não configurado. Por segurança, informe-o nas configurações.' );
			}

			if ( $gateway->webhook_secret && $gateway->webhook_secret !== $secret ) {
				$this->log( 'Secret informado é inválido. Secret do site: ' . $gateway->webhook_secret . '. Secret informado: ' . $secret );

				throw new Exception( 'Invalid secret', 400 );
			}

			if ( $order->is_paid() ) {
				$this->log( 'Pedido #' . $order_id . ' já está pago!' );

				throw new Exception( 'Order already paid.' );
			}

			$payment_total = floatval( $data->Valor );

			if ( $payment_total < $order->get_total() ) {
				$this->log( 'O valor do pagamento foi de ' . $payment_total . ', mas o total do pedido é ' . $order->get_total() );

				$order->add_order_note( 'Pagamento não confirmado. Total pago é menor que o valor pago.' );

				throw new Exception( 'Total inválido.' );
			}

				$order->add_meta_data( 'wc_aarin_payer', $data->Pagador, true );
				$order->add_meta_data( 'wc_aarin_payment_id', $data->Id, true );
				$order->add_order_note( 'Pagamento via Pix confirmado.' );
				$order->payment_complete();
				$order->save();

			$response = [
				'success' => true,
			];

		} catch (Exception $e) {
			status_header( $e->getCode() ? $e->getCode() : 200 );

			$response = [
				'success' => !! $e->getCode(),
				'error'   => $e->getMessage(),
			];
		}

		echo json_encode( $response );
		exit;
	}

	/**
	 * get_order_id_from_charge
	 *
	 * @param mixed $charge_id
	 * @return void
	 */
	private function get_order_id_from_charge( $charge_id ) {
		global $wpdb;

		$query = $wpdb->prepare(
			"SELECT
			`post_id`
			FROM
			`{$wpdb->postmeta}`
			WHERE
			`meta_value` = %s
			AND
			`meta_key` = '_wc_aarin_id'
			LIMIT 1
			",
			$charge_id
		);

		// $this->log( 'Query de buscar ID: ' . $query );

		return (int) $wpdb->get_var( $query );
	}

	/**
	 * Get the authorization header.
	 *
	 * On certain systems and configurations, the Authorization header will be
	 * stripped out by the server or PHP. Typically this is then used to
	 * generate `PHP_AUTH_USER`/`PHP_AUTH_PASS` but not passed on. We use
	 * `getallheaders` here to try and grab it out instead.
	 *
	 * @since 3.0.0
	 *
	 * @return string Authorization header if set.
	 */
	public function get_webhook_secret_header() {
		if ( ! empty( $_SERVER['HTTP_WEBHOOK_SECRET'] ) ) {
			return wp_unslash( $_SERVER['HTTP_WEBHOOK_SECRET'] ); // WPCS: sanitization ok.
		}

		if ( function_exists( 'getallheaders' ) ) {
			$headers = getallheaders();
			// Check for the authoization header case-insensitively.
			foreach ( $headers as $key => $value ) {
				if ( 'webhook-secret' === strtolower( $key ) ) {
					return $value;
				}
			}
		}

		return '';
	}

	public function handle_webhook_setup() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( 'Sem permissões' );
		}

		if ( WC()->payment_gateways() ) {
			$payment_gateways = WC()->payment_gateways()->payment_gateways();
		} else {
			$payment_gateways = [];
		}

		if ( ! isset( $payment_gateways['wc-aarin-pix'] ) ) {
			wp_die( 'Método não encontrado' );
		}

		$api = new Api( $payment_gateways['wc-aarin-pix'] );

		update_option( '_aarin_pix_has_webhook', 'yes' );

		try {
			$api->create_webhook();
		} catch (Exception $e) {
			update_option( '_aarin_pix_has_webhook', 'no' );
			wp_die( $e->getMessage() );
		}

		wp_die( 'Webhook criado. Agora você pode fechar esta página.' );
	}
}
