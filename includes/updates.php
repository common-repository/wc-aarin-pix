<?php
if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

class WC_Plugin_Base_Premium_Updates {
	function __construct() {
		add_action( 'init', array( $this, 'license' ) );
	}

	public function license() {
		include_once 'license/license.php';

		if ( is_admin() && class_exists( 'FA_Licensing_Framework_New' ) ) {
			new FA_Licensing_Framework_New(
				'wc-premium-plugin',
				'WC Etiquetas de Envio',
				WC_Plugin_Base_Premium::get_main_file()
			);
		}
	}
}

new WC_Plugin_Base_Premium_Updates();
