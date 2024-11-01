<?php
namespace WAAP;

if ( ! defined( 'ABSPATH' ) ) {
  exit; // Exit if accessed directly.
}

class Helpers {
	/**
	 * Extract numbers from a string.
	 *
	 * @param  string $string String where will be extracted numbers.
	 *
	 * @return string
	 */
	public function get_numbers( $string ) {
		return preg_replace( '([^0-9])', '', $string );
	}

	public function post( $key, $sanitize = 'FILTER_SANITIZE_STRING' ) {
		return filter_input( INPUT_POST, $key, $sanitize );
	}

	public function get( $key, $sanitize = 'FILTER_SANITIZE_STRING' ) {
		return filter_input( INPUT_GET, $key, $sanitize );
	}
}
