<?php
namespace WAAP;

use Exception;
use WAAP\Traits\WC_Logger_Trait;

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

class Api {
	use WC_Logger_Trait;

	private $gateway;

	/**
	 * Instance the class
	 *
	 * @param Gateway_Pix $gateway Payment Gateway instance.
	 * @return void
	 */
	public function __construct( $gateway ) {
		$this->gateway = $gateway;

		if ( 'yes' === $this->gateway->debug ) {
			$this->set_wc_logger_source( $this->gateway->id );
		}
	}

	/**
	 * Get the checkout URL.
	 *
	 * @return string.
	 */
	protected function get_api_url( $endpoint ) {
		return sprintf(
			'https://pix%s.aarin.com.br/api/v1%s',
			'yes' === $this->gateway->sandbox ? '-h' : '',
			$endpoint,
		);
	}

	/**
	 * create_pix_charge
	 *
	 * @param mixed $data
	 * @return object|Exception
	 */
	public function create_pix_charge( $data ) {
		$response    = $this->do_request( '/cob', 'POST', $data );
		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = json_decode( wp_remote_retrieve_body( $response ) );

		if ( 200 === $status_code ) {
			$this->log( 'Cobrança processada com sucesso: ' . print_r( $body, true ) );
			return $body;
		}

		$this->log( 'Erro ao gerar cobrança: ' . print_r( $body, true ) );

		throw new \Exception( print_r( $body, true ), 500 );
	}

	/**
	 * Get current token data
	 *
	 * @return object|false
	 */
	protected function get_token_data() {
		return get_transient( '_aarin_access_token_data' );
	}

	/**
	 * Save current token
	 *
	 * @param object $data
	 * @return bool
	 */
	protected function set_token_data( $data ) {
		// convert to  timestamp
		$data->expiresAt = strtotime( $data->expiresAt );

		set_transient( '_aarin_access_token_data', $data, $data->expiresAt );

		return true;
	}

	protected function request_authorization_token( $refresh_token = '', $attempt = 1 ) {
		$endpoint = '/oauth/token';

		if ( $refresh_token ) {
			$endpoint .= '/refresh';
			$data = [
				'refreshToken' => $refresh_token,
			];
		} else {
			$data = [
				'empresaId' => $this->gateway->company_id,
				'senha'     => $this->gateway->password,
				'escopo'    => [
					'cob.write',
					'cob.read',
					'pix.write',
					'pix.read',
					'webhook.write',
					'webhook.read',
				],
			];
		}

		$response    = $this->do_request( $endpoint, 'POST', $data );
		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $refresh_token && 401 === $status_code ) {
			return $this->request_authorization_token();
		}

		if ( 200 === $status_code ) {
			$body = json_decode( wp_remote_retrieve_body( $response ) );

			$this->set_token_data( $body );

			return $body->accessToken;
		}

		if ( $attempt < 3 ) {
			$this->log( 'Erro ' . $status_code . ' ao gerar token, fazendo nova tentativa: ' . print_r( $response, true ) );

			sleep( 1 );

			return $this->request_authorization_token( $refresh_token, $attempt + 1 );
		}

		$this->log( 'Erro ' . $status_code . ' ao gerar token. Retentativas esgotadas: ' . print_r( $response, true ) );

		throw new Exception( __( 'Ocorreu um erro ao processar o token da sua solicitação. Tente novamente.', 'wc-aarin' ) );
	}

	protected function get_access_token() {
		$current_token_data = $this->get_token_data();
		$expires_in         = $current_token_data->expiresAt - time();

		// at least 60 seconds long
		if ( $current_token_data && $expires_in > 60 ) {
			return $current_token_data->accessToken;
		}

		$refresh_token = $expires_in > 0 ? $current_token_data->refreshToken : '';
		$new_token     = $this->request_authorization_token( $refresh_token );

		return $new_token;
	}

	public function create_webhook() {

		$response = $this->do_request( '/webhook', 'PUT', [
			'pixWebhookUrl' => WC()->api_request_url( 'aarin-pix-notifications' ),
		] );

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( 200 === $status_code ) {
			return true;
		}

		$this->log( 'Erro ' . print_r( $response, true ) );

		throw new Exception( 'Ocorreu um erro. Tente novamente.' );
	}

	/**
	 * Do requests in the Aarin API.
	 *
	 * @param  string $url      URL.
	 * @param  string $method   Request method.
	 * @param  array  $data     Request data.
	 * @param  array  $headers  Request headers.
	 *
	 * @return array            Request response.
	 */
	protected function do_request( $endpoint, $method = 'POST', $data = [], $headers = [] ) {
		$url = $this->get_api_url( $endpoint );

		$params = [
			'method'  => $method,
			'timeout' => 60,
			'headers' => [
				'content-type' => 'application/json',
			],
		];

		if ( in_array( $method, [ 'POST', 'PUT' ] ) && ! empty( $data ) ) {
			$params['body'] = json_encode( $data );
		}

		if ( ! empty( $headers ) ) {
			$params['headers'] = wp_parse_args( $headers, $params['headers'] );
		}

		if ( $endpoint !== '/oauth/token' ) {
			$token = $this->get_access_token();

			$params['headers']['Authorization'] = 'Bearer ' . $token;
		}

		$response = wp_safe_remote_post( $url, $params );

		if ( is_wp_error( $response ) ) {
			$this->log( 'WP_Error na solicitação ao endpoint ' . $endpoint . ': ' . $response->get_error_message() );
			throw new Exception( 'Ocorreu um erro interno. Por favor, tente novamente.' );
		}

		return $response;
	}

	public function helpers() {
		return new Helpers();
	}
}
