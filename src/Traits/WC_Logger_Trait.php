<?php
namespace WAAP\Traits;

if ( ! defined( 'ABSPATH' ) ) {
  exit;
}

trait WC_Logger_Trait {

	protected $wc_logger_source = null;

	public function log( $data, $type = 'info' ) {
		if ( null == $this->wc_logger_source ) return;

		$logger = \wc_get_logger();

		if ( ! \method_exists( $logger, $type ) ) {
			throw new RuntimeExpection( "undefined \"$type\" log type" );
		}

		if ( ! \is_array( $data ) ) $data = [ $data ];

			$message = '';
		foreach( $data as $part ) {
			if ( null === $part ) {
				$message .= 'Null';
			} else if ( \is_bool( $part ) ) {
				$message .= $part ? 'True' : 'False';
			} else if ( ! \is_string( $part ) ) {
				$message .= \print_r( $part, true );
			} else {
				$message .= $part;
		}

			$message .= ' ';
		}

		$context = [
			'source' => $this->wc_logger_source,
		];

		$logger->$type( \trim( $message ), $context );
	}

	public function set_wc_logger_source( $source ) {
		$this->wc_logger_source = $source;
	}
}
