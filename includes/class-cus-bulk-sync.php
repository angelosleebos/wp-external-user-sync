<?php
/**
 * Bulk User Sync
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUS_Bulk_Sync {

	private static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'wp_ajax_cus_bulk_sync', array( $this, 'handle_bulk_sync' ) );
		add_action( 'wp_ajax_cus_bulk_sync_status', array( $this, 'get_sync_status' ) );
	}

	/**
	 * Handle bulk sync AJAX request
	 */
	public function handle_bulk_sync() {
		check_ajax_referer( 'cus_bulk_sync', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		$batch_size = isset( $_POST['batch_size'] ) ? intval( $_POST['batch_size'] ) : 10;
		$offset = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;

		$result = $this->sync_users_batch( $batch_size, $offset );

		wp_send_json_success( $result );
	}

	/**
	 * Get sync status
	 */
	public function get_sync_status() {
		check_ajax_referer( 'cus_bulk_sync', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( 'Insufficient permissions' );
		}

		global $wpdb;
		$total_users = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->users}" );

		wp_send_json_success( array(
			'total_users' => $total_users,
		) );
	}

	/**
	 * Sync a batch of users
	 */
	private function sync_users_batch( $batch_size, $offset ) {
		global $wpdb;

		// Get users to sync
		$users = get_users( array(
			'number' => $batch_size,
			'offset' => $offset,
			'orderby' => 'ID',
			'order' => 'ASC',
		) );

		if ( empty( $users ) ) {
			return array(
				'completed' => true,
				'synced' => 0,
				'total' => $offset,
				'message' => 'All users synced',
			);
		}

		$webhook = CUS_Webhook::instance();
		$synced = 0;
		$errors = array();

		// Temporarily remove hooks to prevent circular sync
		remove_action( 'user_register', array( $webhook, 'on_user_created' ), 10 );
		remove_action( 'profile_update', array( $webhook, 'on_user_updated' ), 10 );
		remove_action( 'delete_user', array( $webhook, 'on_user_deleted' ), 10 );
		remove_action( 'set_user_role', array( $webhook, 'on_role_changed' ), 10 );

		foreach ( $users as $user ) {
			try {
				// Manually trigger sync for this user
				$result = $this->sync_single_user( $user );
				if ( $result ) {
					$synced++;
				} else {
					// Log which user failed
					$errors[] = sprintf(
						'User %s (%d): Sync returned false',
						$user->user_login,
						$user->ID
					);
				}
			} catch ( Exception $e ) {
				$errors[] = sprintf(
					'User %s (%d): %s',
					$user->user_login,
					$user->ID,
					$e->getMessage()
				);
			}
		}

		// Restore hooks
		add_action( 'user_register', array( $webhook, 'on_user_created' ), 10, 1 );
		add_action( 'profile_update', array( $webhook, 'on_user_updated' ), 10, 2 );
		add_action( 'delete_user', array( $webhook, 'on_user_deleted' ), 10, 1 );
		add_action( 'set_user_role', array( $webhook, 'on_role_changed' ), 10, 3 );

		return array(
			'completed' => count( $users ) < $batch_size,
			'synced' => $synced,
			'total' => $offset + count( $users ),
			'batch_size' => count( $users ),
			'errors' => $errors,
			'message' => sprintf(
				'Synced %d users (total: %d)',
				$synced,
				$offset + $synced
			),
		);
	}

	/**
	 * Sync a single user to all remote sites
	 */
	private function sync_single_user( $user ) {
		$settings = CUS_Settings::instance();
		$remote_sites = $settings->get( 'remote_sites', array() );

		if ( empty( $remote_sites ) ) {
			return false;
		}

		$encryption_key = $settings->get( 'encryption_key' );

		// Prepare user data (include 'action' BEFORE encryption, like webhook does)
		$user_data = array(
			'action' => 'update',
			'ID' => $user->ID,
			'user_login' => $user->user_login,
			'user_email' => $user->user_email,
			'user_nicename' => $user->user_nicename,
			'display_name' => $user->display_name,
			'first_name' => $user->first_name,
			'last_name' => $user->last_name,
			'user_url' => $user->user_url,
			'description' => $user->description,
			'roles' => $user->roles,
		);

		// Get user meta if enabled
		if ( $settings->get( 'sync_meta', true ) ) {
			$user_data['meta'] = get_user_meta( $user->ID );
		}

		// Encrypt data if encryption key is set (following webhook pattern)
		if ( ! empty( $encryption_key ) ) {
			$encryption = new CUS_Encryption( $encryption_key );
			$encrypted = $encryption->encrypt( wp_json_encode( $user_data ) );
			if ( $encrypted === false ) {
				throw new Exception( 'Encryption failed' );
			}
			// Replace data with encrypted payload (no 'action' key outside)
			$user_data = array( 'encrypted' => $encrypted );
		}

		$success = false;

		// Send to each remote site
		foreach ( $remote_sites as $site ) {
			if ( empty( $site['url'] ) || empty( $site['api_key'] ) ) {
				continue;
			}

			// Build URL with smart /index.php/ detection (like webhook does)
			$base_url = untrailingslashit( $site['url'] );
			if ( strpos( $base_url, '.plesk.page' ) !== false ) {
				$url = $base_url . '/index.php/wp-json/custom-user-sync/v1/user';
			} else {
				$url = $base_url . '/wp-json/custom-user-sync/v1/user';
			}

			// $user_data is already correctly structured (with 'action' inside if encrypted)
			$body_data = $user_data;

			$response = wp_remote_post( $url, array(
				'timeout' => 30,
				'headers' => array(
					'Content-Type' => 'application/json',
					'X-API-Key' => $site['api_key'],
				),
				'body' => wp_json_encode( $body_data ),
			) );

			if ( is_wp_error( $response ) ) {
				continue;
			}

			$code = wp_remote_retrieve_response_code( $response );
			if ( $code === 200 ) {
				$success = true;
			} elseif ( $code !== 200 ) {
				// Log non-200 responses for debugging
				$body = wp_remote_retrieve_body( $response );
				error_log( sprintf(
					'Bulk sync: User %s to %s returned %d: %s',
					$user->user_login,
					$url,
					$code,
					substr( $body, 0, 200 )
				) );
			}
		}

		return $success;
	}
}
