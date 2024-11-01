<?php
namespace WAAP;

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

class Main {

	const SUPPORT_URL = 'https://apiki.com/aarin/';

	function __construct() {
		add_filter( 'woocommerce_payment_gateways', [ $this, 'register_gateways' ] );
		add_filter( 'plugin_action_links_wc-aarin-pix/wc-aarin-pix.php', [ $this, 'waap_plugin_links' ] );
		add_filter( 'plugin_row_meta', [ $this, 'waap_support_links' ], 10, 2 );
	}

	public function register_gateways( $gateways ) {
		$gateways[] = Gateway_Pix::class;

		return $gateways;
	}

	/**
	 * Add link settings page
	 *
	 * @since 1.0.4
	 * @param Array $links
	 * @return Array
	 */
	public function waap_plugin_links( $links ) {
		$waap_settings = [
			sprintf(
				'<a href="%s">%s</a>',
				'admin.php?page=wc-settings&tab=checkout&section=wc-aarin-pix',
				__( 'Configurações', 'wc-aarin' )
			)
		];

		return array_merge( $waap_settings, $links );
	}

	/**
	 * Add support link page
	 *
	 * @since 1.0.4
	 * @param Array $links
	 * @return Array
	 */
	public function waap_support_links( $links, $file ) {

		if ( strpos( $file, 'wc-aarin-pix.php' ) !== false ) {
			$new_links = [
				'support' => sprintf( '<a href="%s" target="_blank">%s</a>', self::SUPPORT_URL, __( 'Suporte da Apiki', 'wc-aarin' ) ),
			];

			$links = array_merge( $links, $new_links );
		}

		return $links;
	}
}
