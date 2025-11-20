<?php
/**
 * Webhook handler - sends user changes to remote sites
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUS_Webhook {

	private static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$settings = CUS_Settings::instance();
		if ( ! $settings->get( 'enabled' ) ) {
			return;
		}

		// Hook into user actions
		add_action( 'user_register', array( $this, 'on_user_created' ), 10, 1 );
		add_action( 'profile_update', array( $this, 'on_user_updated' ), 10, 2 );
		add_action( 'delete_user', array( $this, 'on_user_deleted' ), 10, 1 );
		add_action( 'set_user_role', array( $this, 'on_role_changed' ), 10, 3 );

		// AJAX handler for testing connections
		add_action( 'wp_ajax_cus_test_connections', array( $this, 'test_connections' ) );
	}

	public function on_user_created( $user_id ) {
		$this->sync_user( $user_id, 'create' );
	}

	public function on_user_updated( $user_id, $old_user_data ) {
		$this->sync_user( $user_id, 'update' );
	}

	public function on_user_deleted( $user_id ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$data = array(
			'action'     => 'delete',
			'user_login' => $user->user_login,
			'user_email' => $user->user_email,
		);

		$this->send_to_remote_sites( $data );
	}

	public function on_role_changed( $user_id, $role, $old_roles ) {
		$this->sync_user( $user_id, 'update' );
	}

	private function sync_user( $user_id, $action = 'update' ) {
		$user = get_userdata( $user_id );
		if ( ! $user ) {
			return;
		}

		$settings = CUS_Settings::instance();

		$data = array(
			'action'       => $action,
			'user_login'   => $user->user_login,
			'user_email'   => $user->user_email,
			'first_name'   => $user->first_name,
			'last_name'    => $user->last_name,
			'display_name' => $user->display_name,
		);

		// Include roles
		if ( $settings->get( 'sync_roles' ) ) {
			$data['roles'] = $user->roles;
		}

		// Include user meta
		if ( $settings->get( 'sync_meta' ) ) {
			$meta_keys = array(
				'nickname',
				'description',
				'billing_first_name',
				'billing_last_name',
				'billing_company',
				'billing_address_1',
				'billing_address_2',
				'billing_city',
				'billing_postcode',
				'billing_country',
				'billing_state',
				'billing_phone',
				'billing_email',
				'shipping_first_name',
				'shipping_last_name',
				'shipping_company',
				'shipping_address_1',
				'shipping_address_2',
				'shipping_city',
				'shipping_postcode',
				'shipping_country',
				'shipping_state',
			);

			$data['user_meta'] = array();
			foreach ( $meta_keys as $key ) {
				$value = get_user_meta( $user_id, $key, true );
				if ( ! empty( $value ) ) {
					$data['user_meta'][ $key ] = $value;
				}
			}
		}

		$this->send_to_remote_sites( $data );
	}

	private function send_to_remote_sites( $data ) {
		$settings = CUS_Settings::instance();
		$remote_sites = $settings->get( 'remote_sites', array() );

		if ( empty( $remote_sites ) ) {
			return;
		}

		// Encrypt data if encryption key is set
		$encryption_key = $settings->get( 'encryption_key' );
		if ( ! empty( $encryption_key ) ) {
			$encryption = new CUS_Encryption( $encryption_key );
			$encrypted = $encryption->encrypt( wp_json_encode( $data ) );
			$data = array( 'encrypted' => $encrypted );
		}

		foreach ( $remote_sites as $site ) {
			if ( empty( $site['url'] ) || empty( $site['api_key'] ) ) {
				continue;
			}

			$this->send_request( $site['url'], $site['api_key'], $data );
		}
	}

	/**
	 * Build endpoint URL, detecting index.php in permalink structure
	 */
	private function build_endpoint_url( $base_url, $endpoint_type ) {
		// Try to detect if remote site uses /index.php/ in URLs
		// First try without index.php
		$test_url = $base_url . '/wp-json/';
		$response = wp_remote_head( $test_url, array( 'timeout' => 5, 'redirection' => 0 ) );
		
		// If we get 404, try with index.php prefix
		if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) === 404 ) {
			$prefix = '/index.php/wp-json';
		} else {
			$prefix = '/wp-json';
		}
		
		return $base_url . $prefix . '/custom-user-sync/v1/' . $endpoint_type;
	}

	private function send_request( $url, $api_key, $data ) {
		// Handle subdirectory installations correctly
		$url = untrailingslashit( $url );
		$endpoint = $this->build_endpoint_url( $url, 'user' );
		$body = wp_json_encode( $data );

		$headers = array(
			'Content-Type' => 'application/json',
			'X-API-Key'    => $api_key,
		);

		// Add signature if verification is enabled
		$settings = CUS_Settings::instance();
		if ( $settings->get( 'verify_signature' ) ) {
			$timestamp = time();
			$signature = hash_hmac( 'sha256', $timestamp . $body, $api_key );
			$headers['X-Timestamp'] = $timestamp;
			$headers['X-Signature'] = $signature;
		}

		$response = wp_remote_post( $endpoint, array(
			'headers' => $headers,
			'body'    => $body,
			'timeout' => 30,
			'sslverify' => true, // Always verify SSL certificates
		) );

		if ( is_wp_error( $response ) ) {
			error_log( sprintf(
				'[Custom User Sync] Failed to send to %s: %s',
				$url,
				$response->get_error_message()
			) );
			return false;
		}

		$status = wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			$body = wp_remote_retrieve_body( $response );
			error_log( sprintf(
				'[Custom User Sync] HTTP %d from %s: %s',
				$status,
				$url,
				$body
			) );
			return false;
		}

		return true;
	}

	public function test_connections() {
		check_ajax_referer( 'cus_test_connections', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		$settings = CUS_Settings::instance();
		$remote_sites = $settings->get( 'remote_sites', array() );

		if ( empty( $remote_sites ) ) {
			wp_send_json_error( 'No remote sites configured' );
		}

		$results = array();

		foreach ( $remote_sites as $site ) {
			if ( empty( $site['url'] ) || empty( $site['api_key'] ) ) {
				$results[] = array(
					'url'     => $site['url'] ?? 'Unknown',
					'success' => false,
					'message' => 'Missing URL or API key',
				);
				continue;
			}

			$url = untrailingslashit( $site['url'] );
			$endpoint = $this->build_endpoint_url( $url, 'health' );

			$response = wp_remote_get( $endpoint, array(
				'headers' => array(
					'X-API-Key' => $site['api_key'],
				),
				'timeout' => 10,
			) );

			if ( is_wp_error( $response ) ) {
				$results[] = array(
					'url'     => $site['url'],
					'success' => false,
					'message' => $response->get_error_message(),
				);
				continue;
			}

			$status = wp_remote_retrieve_response_code( $response );
			$body = json_decode( wp_remote_retrieve_body( $response ), true );

			$results[] = array(
				'url'     => $site['url'],
				'success' => $status === 200,
				'message' => $status === 200 
					? sprintf( 'Connected (v%s)', $body['version'] ?? 'unknown' )
					: sprintf( 'HTTP %d: %s', $status, $body['message'] ?? 'Unknown error' ),
			);
		}

		wp_send_json_success( $results );
	}
}
