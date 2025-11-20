<?php
/**
 * Settings management
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CUS_Settings {

	private static $instance = null;
	private $option_name = 'cus_settings';

	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	public function add_menu() {
		add_options_page(
			__( 'Custom User Sync', 'custom-user-sync' ),
			__( 'User Sync', 'custom-user-sync' ),
			'manage_options',
			'custom-user-sync',
			array( $this, 'render_page' )
		);
	}

	public function register_settings() {
		register_setting( 'cus_settings_group', $this->option_name );
	}

	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = $this->get_settings();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			
			<form method="post" action="options.php">
				<?php
				settings_fields( 'cus_settings_group' );
				?>
				
				<table class="form-table">
					<tr>
						<th scope="row">
							<label for="remote_sites"><?php _e( 'Remote Sites', 'custom-user-sync' ); ?></label>
						</th>
						<td>
							<textarea 
								id="remote_sites" 
								name="<?php echo esc_attr( $this->option_name ); ?>[remote_sites]" 
								rows="10" 
								class="large-text code"
								placeholder='[{"url":"https://example.com","api_key":"your-api-key"}]'
							><?php echo esc_textarea( wp_json_encode( $settings['remote_sites'] ?? [], JSON_PRETTY_PRINT ) ); ?></textarea>
							<p class="description">
								<?php _e( 'JSON array of remote sites to sync with. Each site needs url and api_key.', 'custom-user-sync' ); ?>
							</p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="api_key"><?php _e( 'API Key (This Site)', 'custom-user-sync' ); ?></label>
						</th>
						<td>
							<input 
								type="text" 
								id="api_key" 
								name="<?php echo esc_attr( $this->option_name ); ?>[api_key]" 
								value="<?php echo esc_attr( $settings['api_key'] ?? '' ); ?>" 
								class="regular-text"
								readonly
							/>
							<button type="button" class="button" onclick="document.getElementById('api_key').value = '<?php echo esc_js( wp_generate_password( 32, false ) ); ?>'">
								<?php _e( 'Generate New', 'custom-user-sync' ); ?>
							</button>
							<p class="description">
								<?php _e( 'Use this API key on remote sites to authenticate requests to this site.', 'custom-user-sync' ); ?>
							</p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="encryption_key"><?php _e( 'Encryption Key', 'custom-user-sync' ); ?></label>
						</th>
						<td>
							<input 
								type="text" 
								id="encryption_key" 
								name="<?php echo esc_attr( $this->option_name ); ?>[encryption_key]" 
								value="<?php echo esc_attr( $settings['encryption_key'] ?? '' ); ?>" 
								class="regular-text"
							/>
							<p class="description">
								<?php _e( 'Optional: Use the same encryption key on all sites for encrypted data transfer.', 'custom-user-sync' ); ?>
							</p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="sync_meta"><?php _e( 'Sync User Meta', 'custom-user-sync' ); ?></label>
						</th>
						<td>
							<label>
								<input 
									type="checkbox" 
									id="sync_meta" 
									name="<?php echo esc_attr( $this->option_name ); ?>[sync_meta]" 
									value="1"
									<?php checked( $settings['sync_meta'] ?? true ); ?>
								/>
								<?php _e( 'Synchronize user meta data', 'custom-user-sync' ); ?>
							</label>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="sync_roles"><?php _e( 'Sync User Roles', 'custom-user-sync' ); ?></label>
						</th>
						<td>
							<label>
								<input 
									type="checkbox" 
									id="sync_roles" 
									name="<?php echo esc_attr( $this->option_name ); ?>[sync_roles]" 
									value="1"
									<?php checked( $settings['sync_roles'] ?? true ); ?>
								/>
								<?php _e( 'Synchronize user roles', 'custom-user-sync' ); ?>
							</label>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="ip_whitelist"><?php _e( 'IP Whitelist', 'custom-user-sync' ); ?></label>
						</th>
						<td>
							<textarea 
								id="ip_whitelist" 
								name="<?php echo esc_attr( $this->option_name ); ?>[ip_whitelist]" 
								rows="3" 
								class="large-text code"
								placeholder="1.2.3.4&#10;5.6.7.8"
							><?php echo esc_textarea( implode( "\n", $settings['ip_whitelist'] ?? array() ) ); ?></textarea>
							<p class="description">
								<?php _e( 'Optional: One IP address per line. Only these IPs can send requests. Leave empty to allow all.', 'custom-user-sync' ); ?>
							</p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="rate_limit"><?php _e( 'Rate Limit', 'custom-user-sync' ); ?></label>
						</th>
						<td>
							<input 
								type="number" 
								id="rate_limit" 
								name="<?php echo esc_attr( $this->option_name ); ?>[rate_limit]" 
								value="<?php echo esc_attr( $settings['rate_limit'] ?? 100 ); ?>" 
								class="small-text"
								min="0"
							/>
							<span><?php _e( 'requests per minute (0 = unlimited)', 'custom-user-sync' ); ?></span>
							<p class="description">
								<?php _e( 'Maximum number of API requests per IP per minute. Prevents abuse.', 'custom-user-sync' ); ?>
							</p>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="verify_signature"><?php _e( 'Verify Signatures', 'custom-user-sync' ); ?></label>
						</th>
						<td>
							<label>
								<input 
									type="checkbox" 
									id="verify_signature" 
									name="<?php echo esc_attr( $this->option_name ); ?>[verify_signature]" 
									value="1"
									<?php checked( $settings['verify_signature'] ?? false ); ?>
								/>
								<?php _e( 'Require HMAC signature for incoming requests (prevents replay attacks)', 'custom-user-sync' ); ?>
							</label>
						</td>
					</tr>
					
					<tr>
						<th scope="row">
							<label for="enabled"><?php _e( 'Enable Sync', 'custom-user-sync' ); ?></label>
						</th>
						<td>
							<label>
								<input 
									type="checkbox" 
									id="enabled" 
									name="<?php echo esc_attr( $this->option_name ); ?>[enabled]" 
									value="1"
									<?php checked( $settings['enabled'] ?? false ); ?>
								/>
								<?php _e( 'Enable automatic user synchronization', 'custom-user-sync' ); ?>
							</label>
						</td>
					</tr>
				</table>
				
				<?php submit_button(); ?>
			</form>
			
			<hr>
			
			<h2><?php _e( 'API Endpoint', 'custom-user-sync' ); ?></h2>
			<p>
				<code><?php echo esc_html( rest_url( 'custom-user-sync/v1' ) ); ?></code>
			</p>
			
			<h2><?php _e( 'Test Connection', 'custom-user-sync' ); ?></h2>
			<button type="button" class="button" id="test-connection">
				<?php _e( 'Test Remote Connections', 'custom-user-sync' ); ?>
			</button>
			<div id="test-results" style="margin-top: 10px;"></div>
		</div>
		
		<script>
		jQuery(document).ready(function($) {
			$('#test-connection').on('click', function() {
				const button = $(this);
				button.prop('disabled', true).text('Testing...');
				$('#test-results').html('<p>Testing connections...</p>');
				
				$.post(ajaxurl, {
					action: 'cus_test_connections',
					nonce: '<?php echo wp_create_nonce( 'cus_test_connections' ); ?>'
				}, function(response) {
					if (response.success) {
						let html = '<div style="background: #fff; border: 1px solid #ccc; padding: 10px; margin-top: 10px;">';
						response.data.forEach(function(result) {
							const status = result.success ? '✅' : '❌';
							html += '<p>' + status + ' ' + result.url + ': ' + result.message + '</p>';
						});
						html += '</div>';
						$('#test-results').html(html);
					} else {
						$('#test-results').html('<p style="color: red;">Error: ' + response.data + '</p>');
					}
					button.prop('disabled', false).text('<?php _e( 'Test Remote Connections', 'custom-user-sync' ); ?>');
				});
			});
		});
		</script>
		<?php
	}

	public function get_settings() {
		$defaults = array(
			'remote_sites'     => array(),
			'api_key'          => wp_generate_password( 32, false ),
			'encryption_key'   => '',
			'sync_meta'        => true,
			'sync_roles'       => true,
			'ip_whitelist'     => array(),
			'rate_limit'       => 100,
			'verify_signature' => false,
			'enabled'          => false,
		);

		$settings = get_option( $this->option_name, array() );

		// Parse remote_sites if it's a JSON string
		if ( isset( $settings['remote_sites'] ) && is_string( $settings['remote_sites'] ) ) {
			$settings['remote_sites'] = json_decode( $settings['remote_sites'], true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				$settings['remote_sites'] = array();
			}
		}

		// Parse IP whitelist if it's a string
		if ( isset( $settings['ip_whitelist'] ) && is_string( $settings['ip_whitelist'] ) ) {
			$ips = array_filter( array_map( 'trim', explode( "\n", $settings['ip_whitelist'] ) ) );
			$settings['ip_whitelist'] = array_values( $ips );
		}

		return wp_parse_args( $settings, $defaults );
	}

	public function get( $key, $default = null ) {
		$settings = $this->get_settings();
		return $settings[ $key ] ?? $default;
	}
}
