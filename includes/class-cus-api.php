<?php
/**
 * REST API endpoints
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUS_API {

	public static function register_routes() {
		// Receive user data from remote site
		register_rest_route( 'custom-user-sync/v1', '/user', array(
			'methods'             => 'POST',
			'callback'            => array( __CLASS__, 'receive_user' ),
			'permission_callback' => array( __CLASS__, 'verify_api_key' ),
		) );

		// Health check endpoint
		register_rest_route( 'custom-user-sync/v1', '/health', array(
			'methods'             => 'GET',
			'callback'            => array( __CLASS__, 'health_check' ),
			'permission_callback' => array( __CLASS__, 'verify_api_key' ),
		) );
	}

	public static function verify_api_key( $request ) {
		$settings = CUS_Settings::instance();

		// Check rate limiting
		if ( ! self::check_rate_limit( $request ) ) {
			return new WP_Error(
				'rate_limit_exceeded',
				__( 'Rate limit exceeded. Try again later.', 'custom-user-sync' ),
				array( 'status' => 429 )
			);
		}

		// Check IP whitelist
		$whitelist = $settings->get( 'ip_whitelist', array() );
		if ( ! empty( $whitelist ) ) {
			$client_ip = self::get_client_ip();
			if ( ! in_array( $client_ip, $whitelist, true ) ) {
				return new WP_Error(
					'ip_not_whitelisted',
					__( 'IP address not authorized', 'custom-user-sync' ),
					array( 'status' => 403 )
				);
			}
		}

		// Check API key
		$api_key = $request->get_header( 'X-API-Key' );
		
		if ( empty( $api_key ) ) {
			return new WP_Error(
				'missing_api_key',
				__( 'API key is required', 'custom-user-sync' ),
				array( 'status' => 401 )
			);
		}

		$stored_key = $settings->get( 'api_key' );

		if ( ! hash_equals( $stored_key, $api_key ) ) {
			return new WP_Error(
				'invalid_api_key',
				__( 'Invalid API key', 'custom-user-sync' ),
				array( 'status' => 403 )
			);
		}

		// Verify request signature if enabled
		$verify_signature = $settings->get( 'verify_signature', false );
		if ( $verify_signature ) {
			$signature = $request->get_header( 'X-Signature' );
			$timestamp = $request->get_header( 'X-Timestamp' );
			
			if ( empty( $signature ) || empty( $timestamp ) ) {
				return new WP_Error(
					'missing_signature',
					__( 'Request signature required', 'custom-user-sync' ),
					array( 'status' => 401 )
				);
			}

			// Check timestamp (prevent replay attacks)
			$time_diff = abs( time() - intval( $timestamp ) );
			if ( $time_diff > 300 ) { // 5 minutes
				return new WP_Error(
					'expired_request',
					__( 'Request expired', 'custom-user-sync' ),
					array( 'status' => 401 )
				);
			}

			// Verify signature
			$body = $request->get_body();
			$expected_signature = hash_hmac( 'sha256', $timestamp . $body, $stored_key );
			
			if ( ! hash_equals( $expected_signature, $signature ) ) {
				return new WP_Error(
					'invalid_signature',
					__( 'Invalid request signature', 'custom-user-sync' ),
					array( 'status' => 403 )
				);
			}
		}

		return true;
	}

	private static function check_rate_limit( $request ) {
		$settings = CUS_Settings::instance();
		$rate_limit = $settings->get( 'rate_limit', 100 ); // Default: 100 requests per minute
		
		if ( $rate_limit <= 0 ) {
			return true; // Rate limiting disabled
		}

		$client_ip = self::get_client_ip();
		$transient_key = 'cus_rate_limit_' . md5( $client_ip );
		$requests = get_transient( $transient_key );

		if ( $requests === false ) {
			set_transient( $transient_key, 1, MINUTE_IN_SECONDS );
			return true;
		}

		if ( $requests >= $rate_limit ) {
			return false;
		}

		set_transient( $transient_key, $requests + 1, MINUTE_IN_SECONDS );
		return true;
	}

	private static function get_client_ip() {
		$ip_headers = array(
			'HTTP_CF_CONNECTING_IP', // Cloudflare
			'HTTP_X_REAL_IP',
			'HTTP_X_FORWARDED_FOR',
			'REMOTE_ADDR',
		);

		foreach ( $ip_headers as $header ) {
			if ( ! empty( $_SERVER[ $header ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) );
				// Handle comma-separated IPs (X-Forwarded-For)
				if ( strpos( $ip, ',' ) !== false ) {
					$ips = explode( ',', $ip );
					$ip = trim( $ips[0] );
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}

		return '0.0.0.0';
	}

	public static function health_check( $request ) {
		return new WP_REST_Response( array(
			'status'  => 'ok',
			'version' => CUS_VERSION,
			'time'    => current_time( 'mysql' ),
		), 200 );
	}

	public static function receive_user( $request ) {
		$data = $request->get_json_params();

		// PREVENT CIRCULAR WEBHOOKS: Remove webhook hooks before processing
		$webhook = CUS_Webhook::instance();
		remove_action( 'user_register', array( $webhook, 'on_user_created' ), 10 );
		remove_action( 'profile_update', array( $webhook, 'on_user_updated' ), 10 );
		remove_action( 'delete_user', array( $webhook, 'on_user_deleted' ), 10 );
		remove_action( 'set_user_role', array( $webhook, 'on_role_changed' ), 10 );

		// Decrypt data FIRST if encryption key is set
		$settings = CUS_Settings::instance();
		if ( ! empty( $settings->get( 'encryption_key' ) ) && ! empty( $data['encrypted'] ) ) {
			$encryption = new CUS_Encryption( $settings->get( 'encryption_key' ) );
			$decrypted = $encryption->decrypt( $data['encrypted'] );
			if ( ! $decrypted ) {
				return new WP_Error(
					'decryption_failed',
					__( 'Failed to decrypt data', 'custom-user-sync' ),
					array( 'status' => 400 )
				);
			}
			$data = json_decode( $decrypted, true );
		}

		// Now validate the (decrypted) data
		if ( empty( $data['user_login'] ) || empty( $data['user_email'] ) ) {
			return new WP_Error(
				'invalid_data',
				__( 'user_login and user_email are required', 'custom-user-sync' ),
				array( 'status' => 400 )
			);
		}

		$action = $data['action'] ?? 'update';
		$user_login = sanitize_user( $data['user_login'] );
		$user_email = sanitize_email( $data['user_email'] );

		// Find existing user
		$user = get_user_by( 'login', $user_login );
		if ( ! $user ) {
			$user = get_user_by( 'email', $user_email );
		}

		$response = array();

		switch ( $action ) {
			case 'create':
			case 'update':
				$user_data = array(
					'user_login' => $user_login,
					'user_email' => $user_email,
				);

				// Optional fields
				if ( ! empty( $data['user_pass'] ) ) {
					$user_data['user_pass'] = $data['user_pass'];
				}
				if ( ! empty( $data['first_name'] ) ) {
					$user_data['first_name'] = sanitize_text_field( $data['first_name'] );
				}
				if ( ! empty( $data['last_name'] ) ) {
					$user_data['last_name'] = sanitize_text_field( $data['last_name'] );
				}
				if ( ! empty( $data['display_name'] ) ) {
					$user_data['display_name'] = sanitize_text_field( $data['display_name'] );
				}

				if ( $user ) {
					// Update existing user
					$user_data['ID'] = $user->ID;
					$result = wp_update_user( $user_data );
				} else {
					// Create new user
					$result = wp_insert_user( $user_data );
				}

				if ( is_wp_error( $result ) ) {
					return $result;
				}

				$user_id = is_int( $result ) ? $result : $user->ID;

				// Sync user meta
				if ( $settings->get( 'sync_meta' ) && ! empty( $data['user_meta'] ) ) {
					foreach ( $data['user_meta'] as $key => $value ) {
						update_user_meta( $user_id, $key, maybe_unserialize( $value ) );
					}
				}

				// Sync user roles
				if ( $settings->get( 'sync_roles' ) && ! empty( $data['roles'] ) ) {
					$user_obj = new WP_User( $user_id );
					$user_obj->set_role( '' ); // Remove all roles
					foreach ( (array) $data['roles'] as $role ) {
						$user_obj->add_role( $role );
					}
				}

				$response = array(
					'success' => true,
					'user_id' => $user_id,
					'action'  => $user ? 'updated' : 'created',
				);
				break;

			case 'delete':
				if ( $user ) {
					require_once ABSPATH . 'wp-admin/includes/user.php';
					$result = wp_delete_user( $user->ID );
					$response = array(
						'success' => $result,
						'action'  => 'deleted',
					);
				} else {
					$response = array(
						'success' => false,
						'message' => 'User not found',
					);
				}
				break;

			default:
				return new WP_Error(
					'invalid_action',
					__( 'Invalid action', 'custom-user-sync' ),
					array( 'status' => 400 )
				);
		}

		// Re-add webhook hooks after processing
		add_action( 'user_register', array( $webhook, 'on_user_created' ), 10, 1 );
		add_action( 'profile_update', array( $webhook, 'on_user_updated' ), 10, 2 );
		add_action( 'delete_user', array( $webhook, 'on_user_deleted' ), 10, 1 );
		add_action( 'set_user_role', array( $webhook, 'on_role_changed' ), 10, 3 );

		return new WP_REST_Response( $response, 200 );
	}
}
