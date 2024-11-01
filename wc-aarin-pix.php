<?php
/**
 * Pagamento Pix da Aarin
 *
 * @package WAAP
 *
 * Plugin Name: Pagamento Pix da Aarin
 * Description: Gateway de pagamento Pix da Aarin para WooCommerce.
 * Version: 1.0.6
 * Author: Apiki
 * Author URI: https://apiki.com/
 * Text Domain: wc-aarin-pix
 * Domain Path: /languages
 * Requires PHP: 7.1
 * WC tested up to: 6.9.3
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class WC_Aarin_Pix {
	/**
	 * Version.
	 *
	 * @var float
	 */
	const VERSION = '1.0.6';

	/**
	 * Instance of this class.
	 *
	 * @var object
	 */
	protected static $instance = null;
	/**
	 * Initialize the plugin public actions.
	 */
	function __construct() {
		$this->init();
	}

	public function init() {
		require_once __DIR__ . '/vendor/autoload.php';

		new \WAAP\Main();
		new \WAAP\Shortcodes\Pay_For_Order();
		new \WAAP\Webhooks();

		if ( is_admin() ) {
			$this->admin_features();
		}
	}

	/**
	 *
	 * Admin includes
	 */
	public function admin_features() {}

	/**
	 * Return an instance of this class.
	 *
	 * @return object A single instance of this class.
	 */
	public static function get_instance() {
		if ( null == self::$instance ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Get main file.
	 *
	 * @return string
	 */
	public static function get_main_file() {
		return __FILE__;
	}

	/**
	 * Get plugin path.
	 *
	 * @return string
	 */
	public static function get_plugin_path() {
		return plugin_dir_path( __FILE__ );
	}

	/**
	 * Get the plugin url.
	 * @return string
	 */
	public static function plugin_url() {
		return untrailingslashit( plugins_url( '/', __FILE__ ) );
	}

	/**
	 * Get the plugin dir url.
	 * @return string
	 */
	public static function plugin_dir_url() {
		return plugin_dir_url( __FILE__ );
	}

	/**
	 * Get templates path.
	 *
	 * @return string
	 */
	public static function get_templates_path() {
		return self::get_plugin_path() . 'templates/';
	}

	/**
	 * WooCommerce missing notice.
	 */
	public static function woocommerce_missing_notice() {
		$plugin_name = str_replace( '_', ' ', __CLASS__ );
		include dirname( __FILE__ ) . '/includes/admin/views/html-notice-missing-woocommerce.php';
	}
}

add_action( 'plugins_loaded', array( 'WC_Aarin_Pix', 'get_instance' ) );

register_activation_hook( __FILE__, function() {
	if ( ! get_option( '_wc_aarin_pay_pix_page_id' ) ) {
		$page_data = [
			'post_status'    => 'publish',
			'post_type'      => 'page',
			'post_author'    => 1,
			'post_name'      => 'pay-using-pix',
			'post_title'     => __( 'Pagar com Pix', 'wc-aarin' ),
			'post_content'   => '[aarin_pay_for_order]',
			'comment_status' => 'closed',
		];

		$page_id = wp_insert_post( $page_data );

		update_option( '_wc_aarin_pay_pix_page_id', $page_id );
	}
} );
