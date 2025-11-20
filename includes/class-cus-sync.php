<?php
/**
 * Manual sync functionality
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUS_Sync {

	private static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		// Future: Add bulk sync functionality
	}

	/**
	 * Manually sync a specific user to all remote sites
	 */
	public function sync_user_to_all( $user_id ) {
		$webhook = CUS_Webhook::instance();
		return $webhook->on_user_updated( $user_id, null );
	}

	/**
	 * Bulk sync all users to remote sites
	 */
	public function sync_all_users() {
		$users = get_users( array(
			'fields' => 'ID',
		) );

		$success = 0;
		$failed = 0;

		foreach ( $users as $user_id ) {
			if ( $this->sync_user_to_all( $user_id ) ) {
				$success++;
			} else {
				$failed++;
			}
		}

		return array(
			'success' => $success,
			'failed'  => $failed,
			'total'   => count( $users ),
		);
	}
}
