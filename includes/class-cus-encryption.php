<?php
/**
 * Encryption helper
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUS_Encryption {

	private $key;
	private $method = 'AES-256-CBC';

	public function __construct( $key ) {
		$this->key = hash( 'sha256', $key, true );
	}

	public function encrypt( $data ) {
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			return false;
		}

		$iv = openssl_random_pseudo_bytes( openssl_cipher_iv_length( $this->method ) );
		$encrypted = openssl_encrypt( $data, $this->method, $this->key, 0, $iv );

		if ( $encrypted === false ) {
			return false;
		}

		return base64_encode( $iv . $encrypted );
	}

	public function decrypt( $data ) {
		if ( ! function_exists( 'openssl_decrypt' ) ) {
			return false;
		}

		$data = base64_decode( $data );
		$iv_length = openssl_cipher_iv_length( $this->method );
		$iv = substr( $data, 0, $iv_length );
		$encrypted = substr( $data, $iv_length );

		return openssl_decrypt( $encrypted, $this->method, $this->key, 0, $iv );
	}
}
