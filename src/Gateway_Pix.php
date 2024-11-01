<?php
namespace WAAP;

use Exception;
use WC_Aarin_Pix;
use WAAP\Traits\WC_Logger_Trait;
use WC_Payment_Gateway;

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

class Gateway_Pix extends WC_Payment_Gateway {

  	use WC_Logger_Trait;

	/**
	 * Constructor for the gateway.
	 */
	public function __construct() {
		// Setup general properties.
		$this->setup_properties();
		// Load the settings.
		$this->init_form_fields();
		$this->init_settings();
		// Get settings.
		$this->title                = $this->get_option( 'title' );
		$this->description          = $this->get_option( 'description' );
		$this->instructions         = $this->get_option( 'instructions' );
		$this->payment_instructions = $this->get_option( 'payment_instructions' );
		$this->expiration           = $this->get_option( 'expiration', 120 );
		$this->debug                = $this->get_option( 'debug', 'no' );
		$this->pix_key              = $this->get_option( 'pix_key' );
		$this->company_id           = $this->get_option( 'company_id' );
		$this->webhook_secret       = $this->get_option( 'webhook_secret' );
		$this->password             = $this->get_option( 'password' );
		$this->sandbox              = $this->get_option( 'sandbox', 'yes' );

		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, [ $this, 'process_admin_options' ] );

		if ( 'yes' === $this->enabled ) {
			add_filter( 'woocommerce_thankyou_order_received_text', [ $this, 'order_received_text' ], 10, 2 );
		}
		// Customer Emails.
		add_action( 'woocommerce_email_before_order_table', [ $this, 'email_instructions' ], 10, 3 );
	}

	/**
	 * Setup general properties for the gateway.
	 */
	protected function setup_properties() {
		$this->id                 = 'wc-aarin-pix';
		$this->method_title       = __( 'Pix Aarin', 'wc-aarin' );
		$this->method_description = __( 'Receba pagamentos instantâneos via Pix de seus clientes.', 'wc-aarin' );
		$this->has_fields         = false;
	}

	/**
	 * Initialise Gateway Settings Form Fields.
	 */
	public function init_form_fields() {
		$this->form_fields = [
			'enabled' => [
				'title'       => __( 'Ativar método', 'wc-aarin' ),
				'label'       => __( 'Habilitar recebimento via Pix', 'wc-aarin' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			],
			'title' => [
				'title'       => __( 'Título', 'wc-aarin' ),
				'type'        => 'text',
				'description' => __( 'Nome do método exibido aos clientes', 'wc-aarin' ),
				'default'     => __( 'Pagar com Pix', 'wc-aarin' ),
				'desc_tip'    => true,
			],
			'description' => [
				'title'       => __( 'Descrição', 'wc-aarin' ),
				'type'        => 'textarea',
				'description' => __( 'Instruções que o cliente verá na tela de pagamento.', 'wc-aarin' ),
				'default'     => __( 'Pagamento instantâneo com Pix.', 'wc-aarin' ),
				'desc_tip'    => true,
			],
			'instructions' => [
				'title'       => __( 'Instruções', 'wc-aarin' ),
				'type'        => 'textarea',
				'description' => __( 'Texto básico exibido na página de obrigado, onde há os dados de QR Code e Pix Copia e Cola', 'wc-aarin' ),
				'default'     => __( 'Faça a leitura do QR Code abaixo para realizar o pagamento.', 'wc-aarin' ),
				'desc_tip'    => true,
			],
			'payment_instructions' => [
				'title'       => __( 'Instruções do QR Code', 'wc-aarin' ),
				'type'        => 'textarea',
				'description' => __( 'Mensagem exibida ao pagador sobre a cobrança. Utilize {order_id} para o número do pedido.', 'wc-aarin' ),
				'default'     => __( 'Pagamento do pedido #{order_id}', 'wc-aarin' ),
				'desc_tip'    => true,
			],
			'expiration' => [
				'title'       => __( 'Tempo de expiração', 'wc-aarin' ),
				'type'        => 'text',
				'description' => __( 'Tempo que a cobrança deve permanecer válida, em minutos.', 'wc-aarin' ),
        		'default'     => __( '360', 'wc-aarin' ),
				'desc_tip'    => true,
			],
			'pix_key' => [
				'title'       => __( 'Chave Pix', 'wc-aarin' ),
				'type'        => 'text',
				'description' => __( 'A chave Pix que receberá os pagamentos.', 'wc-aarin' ),
				'desc_tip'    => true,
			],
			'company_id' => [
				'title'       => __( 'ID da empresa', 'wc-aarin' ),
				'type'        => 'text',
				'description' => __( 'ID da empresa na API da Aarin', 'wc-aarin' ),
				'desc_tip'    => true,
			],
			'password' => [
				'title'       => __( 'Senha', 'wc-aarin' ),
				'type'        => 'text',
				'description' => __( 'Senha para acesso à API da Aarin', 'wc-aarin' ),
				'desc_tip'    => true,
			],
			'webhook_secret' => [
				'title'       => __( 'Webhook secret', 'wc-aarin' ),
				'type'        => 'text',
				'description' => __( 'Secret para validar o recebimento de webhooks.', 'wc-aarin' ),
				'desc_tip'    => true,
			],
			'pay_page' => [
				'title'       => __( 'Página de pagamento', 'wc-aarin' ),
				'type'        => 'text',
				'description' => __( 'ID da página com o shortcode <code>[aarin_pay_for_order]</code>. O link é enviado ao cliente caso a imagem não carregue no e-mail.', 'wc-aarin' ),
				'default'     => get_option( '_wc_aarin_pay_pix_page_id' ),
				'desc_tip'    => false,
			],
			'webhook' => [
				'title'       => __( 'Webhook', 'wc-aarin' ),
				'type'        => 'webhook_button',
				'description' => __( 'Configure o webhook para receber notificações automáticas.', 'wc-aarin' ),
			],
			'debug' => [
				'title'       => __( 'Ativar log', 'wc-aarin' ),
				'label'       => __( 'Registrar eventos da API', 'wc-aarin' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'no',
			],
			'sandbox' => [
				'title'       => __( 'Sandbox', 'wc-aarin' ),
				'label'       => __( 'Usar API em Sandbox', 'wc-aarin' ),
				'type'        => 'checkbox',
				'description' => '',
				'default'     => 'yes',
			],
		];
	}

	/**
	 * Process the payment and return the result.
	 *
	 * @param int $order_id Order ID.
	 * @return array
	 */
	public function process_payment( $order_id ) {
    	$order = wc_get_order( $order_id );
    	$api   = new Api( $this );

		if ( 'yes' === $this->debug ) {
			$this->set_wc_logger_source( $this->id );
		}

		try {
			if ( $order->get_total() > 0 ) {
				$data = [
				// 'devedor' => [
				//   'nome' => sprintf(
				//     '%s %s',
				//     $order->get_billing_first_name(),
				//     $order->get_billing_last_name()
				//   ),
				//   'cpf'  => $api->helpers()->get_numbers( $order->get_meta( '_billing_cpf' ) ),
				//   'cnpj' => $api->helpers()->get_numbers( $order->get_meta( '_billing_cnpj' ) ),
				// ],
					'valor' => [
						'original' => wc_format_decimal( $this->get_order_total(), 2, false ),
					],
					'solicitacaoPagador' => str_replace(
						'{order_id}',
						$order->get_order_number(),
						$this->payment_instructions
					),
					'infoAdicionais' => [
						[
							'nome'  => 'wc_order_id',
							'valor' => $order->get_id(),
						],
						[
							'nome'  => 'wc_order_number',
							'valor' => $order->get_order_number(),
						],
					],
					'calendario' => [
						'expiracao' => intval( $this->expiration ) * 60,
					],
				];
				// pix key is required on sandbox
				if ( 'yes' === $this->sandbox ) {
					$data['chave'] = $this->pix_key;
				}
				// legal person!
				if ( ! empty( $data['devedor']['cnpj'] ) ) {
					unset( $data['devedor']['cpf'] );
				} else {
					unset( $data['devedor']['cnpj'] );
				}

				$this->log( 'Gerando cobrança para o pedido #' . $order->get_id() . ': ' . print_r( $data, true ) );

				$result = $api->create_pix_charge( $data );

				$order->add_meta_data( 'wc_aarin_qr_code', $result->links->linkQrCode, true );
				$order->add_meta_data( 'wc_aarin_emv', $result->links->emv, true );
				$order->add_meta_data( 'wc_aarin_expires_at', strtotime( $result->calendario->criacao ) + $result->calendario->expiracao, true );
				$order->add_meta_data( '_wc_aarin_id', $result->id, true );

				$order->set_transaction_id( $result->id );

				$order->save();

				$order->add_order_note( '<a href="' . $result->links->linkQrCode . '" target="_blank">Ver QR Code</a><br />Pix Copia e cola: <code>' . $result->links->emv . '</code>' );

				$order->update_status( 'on-hold', 'Pedido criado via Pix.' );

			} else {
				$order->payment_complete();
			}
			// Remove cart.
			WC()->cart->empty_cart();
			// Return thankyou redirect.
			return [
				'result'   => 'success',
				'redirect' => $this->get_return_url( $order ),
			];

		} catch (Exception $e) {
			$message = $e->getMessage();

			if ( $e->getCode() === 500 ) {
				$order->add_order_note( 'Ocorreu um erro na cobrança com Pix: ' . $message );
				$message = __( 'Ocorreu um erro interno. Tente novamente ou entre em contato para obter assistência.', 'wc-aarin' );
			}

			wc_add_notice( $message, 'error' );
		}
	}

	/**
	 * Return whether or not this gateway still requires setup to function.
	 *
	 * When this gateway is toggled on via AJAX, if this returns true a
	 * redirect will occur to the settings page instead.
	 *
	 * @since 3.4.0
	 * @return bool
	 */
	public function needs_setup() {
		return empty( $this->company_id ) || empty( $this->password );
	}

	/**
	 * Check if the gateway is available for use.
	 *
	 * @return bool
	 */
	public function is_available() {
		$is_available = parent::is_available();

		if ( empty( $this->company_id ) || empty( $this->password ) ) {
			$is_available = false;
		}

		return $is_available;
  	}

	/**
	 * Custom aarin order received text.
	 *
	 * @since 3.9.0
	 * @param string   $text Default text.
	 * @param WC_Order $order Order data.
	 * @return string
	 */
	public function order_received_text( $text, $order ) {
		if ( $order && $this->id === $order->get_payment_method() ) {
			$qr_code = $order->get_meta( 'wc_aarin_qr_code' );
			$emv     = $order->get_meta( 'wc_aarin_emv' );

			if ( ! $qr_code || ! $emv ) {
				return $text;
			}

			$emv  = is_array( $emv ) ? array_shift( $emv ) : $emv;
			$text = '</p>'; // fix wrapper tag

			ob_start();

			wc_get_template(
				'thank-you-page.php',
				[
					'qr_code' => $qr_code,
					'emv'     => $emv
				],
				'',
				WC_Aarin_Pix::get_templates_path()
			);

			$text .= ob_get_clean();
			$text .= '<p>'; // reset wrapper tag
		}

		return $text;
	}

	/**
	 * Add content to the WC emails.
	 *
	 * @param WC_Order $order Order object.
	 * @param bool     $sent_to_admin  Sent to admin.
	 * @param bool     $plain_text Email format: plain text or HTML.
	 */
	public function email_instructions( $order, $sent_to_admin, $plain_text = false ) {
		if ( $order->has_status( 'on-hold' ) && ! $sent_to_admin && $this->id === $order->get_payment_method() ) {

			$qr_code = $order->get_meta( 'wc_aarin_qr_code' );
			$emv     = $order->get_meta( 'wc_aarin_emv' );

			if ( ! $qr_code || ! $emv ) {
				return false;
			}

			$emv = is_array( $emv ) ? array_shift( $emv ) : $emv;

			printf( '<h3>%s</h3>
				<p>%s</p>
				<img style="width: 300px; height: 300px;" src="%s" alt="QR Code do Pix." />
				<h4>%s</h4>
				<p style="border: 1px solid #ccc; padding: 10px;">%s</p>',
				__( 'Aguardando sua transferência via Pix' ),
				__( 'Utilize Pix Copia e Cola ou o QR Code abaixo.' ),
				esc_attr( $qr_code ),
				__( 'Pix Copia e Cola' ),
				esc_attr( $emv )
			);

			if ( $page_id = get_option( '_wc_aarin_pay_pix_page_id' ) ) {
				echo '<p>Caso não consiga visualizar os dados acima, <a href="' . add_query_arg( [ 'id' => esc_attr( $order->get_id() ), 'key' => esc_attr( $order->get_order_key() ) ], esc_url( get_permalink( $page_id ) ) ) . '">clique aqui</a></p>';
			}
		}
	}

	public function process_admin_options() {
		parent::process_admin_options();
		$page_id = filter_input( INPUT_POST, 'woocommerce_wc-aarin-pix_pay_page', FILTER_SANITIZE_NUMBER_INT );

		if ( isset( $page_id ) ) {
			update_option( '_wc_aarin_pay_pix_page_id', $page_id );
		}
	}

	/**
	 * Generate Button Input HTML.
	 *
	 * @param string $key  Input key.
	 * @param array  $data Input data.
	 * @return string
	 */
	public function generate_webhook_button_html( $key, $data ) {
		$field_key = $this->get_field_key( $key );
		$defaults  = [
			'title'       => '',
			'desc_tip'    => false,
			'description' => '',
		];

		$data = wp_parse_args( $data, $defaults );

		ob_start();
		?>
		<tr valign="top">
			<th scope="row" class="titledesc">
				<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
				<?php echo $this->get_tooltip_html( $data ); ?>
			</th>
			<td class="forminp">
			<fieldset>
          		<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>

				<?php if ( !$this->company_id || !$this->password ) { ?>
					<p><strong><?php _e( 'Salve os dados acima e clique em salvar. Feito isso, você poderá criar o webhook', 'wc-aarin' ); ?></strong>.</p>
				<?php } else { ?>
            		<a href="<?php echo admin_url( 'admin-ajax.php?action=aarin_webhook_setup' ); ?>" class="button-secondary" id="<?php echo esc_attr( $field_key ); ?>" target="_blank">
			  		<?php echo 'yes' === get_option( '_aarin_pix_has_webhook' ) ? __( 'Recriar webhook', 'wc-aarin' ) : __( 'Criar webhook', 'wc-aarin' ); ?></a>
          		<?php } ?>
					<?php echo $this->get_description_html( $data ); ?>
			</fieldset>
			</td>
		</tr>
		<?php

		return ob_get_clean();
	}
}
