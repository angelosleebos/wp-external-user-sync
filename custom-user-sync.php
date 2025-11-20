<?php
/**
 * Plugin Name: Custom User Sync
 * Plugin URI: https://sleebos.it
 * Description: Real-time user synchronization between WordPress sites using REST API and webhooks
 * Version: 1.0.0
 * Author: Sleebos IT
 * Author URI: https://sleebos.it
 * License: GPL v2 or later
 * Text Domain: custom-user-sync
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'CUS_VERSION', '1.0.0' );
define( 'CUS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'CUS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load core classes
require_once CUS_PLUGIN_DIR . 'includes/class-cus-settings.php';
require_once CUS_PLUGIN_DIR . 'includes/class-cus-api.php';
require_once CUS_PLUGIN_DIR . 'includes/class-cus-webhook.php';
require_once CUS_PLUGIN_DIR . 'includes/class-cus-sync.php';
require_once CUS_PLUGIN_DIR . 'includes/class-cus-encryption.php';
require_once CUS_PLUGIN_DIR . 'includes/class-cus-bulk-sync.php';

/**
 * Main plugin class
 */
class Custom_User_Sync {

	private static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->init_hooks();
	}

	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'init' ) );
		add_action( 'rest_api_init', array( 'CUS_API', 'register_routes' ) );
	}

	public function init() {
		// Initialize components
		CUS_Settings::instance();
		CUS_Webhook::instance();
		CUS_Sync::instance();
		CUS_Bulk_Sync::instance();
	}
}

// Initialize the plugin
function custom_user_sync() {
	return Custom_User_Sync::instance();
}

custom_user_sync();
