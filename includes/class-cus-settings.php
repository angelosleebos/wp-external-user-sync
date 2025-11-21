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
						<label><?php _e( 'Remote Sites', 'custom-user-sync' ); ?></label>
					</th>
					<td>
						<div id="remote-sites-container">
							<?php
							$remote_sites = $settings['remote_sites'] ?? array();
							if ( empty( $remote_sites ) ) {
								$remote_sites = array( array( 'url' => '', 'api_key' => '' ) );
							}
							foreach ( $remote_sites as $index => $site ) :
							?>
							<div class="remote-site-row" style="margin-bottom: 15px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">
								<div style="margin-bottom: 10px;">
									<label style="display: inline-block; width: 100px; font-weight: 600;"><?php _e( 'Site URL:', 'custom-user-sync' ); ?></label>
									<input 
										type="url" 
										name="<?php echo esc_attr( $this->option_name ); ?>[remote_sites][<?php echo $index; ?>][url]" 
										value="<?php echo esc_attr( $site['url'] ?? '' ); ?>" 
										class="regular-text"
										placeholder="https://example.com"
										style="width: 400px;"
									/>
								</div>
								<div style="margin-bottom: 10px;">
									<label style="display: inline-block; width: 100px; font-weight: 600;"><?php _e( 'API Key:', 'custom-user-sync' ); ?></label>
									<input 
										type="text" 
										name="<?php echo esc_attr( $this->option_name ); ?>[remote_sites][<?php echo $index; ?>][api_key]" 
										value="<?php echo esc_attr( $site['api_key'] ?? '' ); ?>" 
										class="regular-text"
										placeholder="Remote site's API key"
										style="width: 400px;"
									/>
								</div>
								<?php if ( $index > 0 ) : ?>
									<button type="button" class="button remove-site" style="color: #a00;"><?php _e( 'Remove', 'custom-user-sync' ); ?></button>
								<?php endif; ?>
							</div>
							<?php endforeach; ?>
						</div>
						<button type="button" class="button" id="add-remote-site"><?php _e( '+ Add Another Site', 'custom-user-sync' ); ?></button>
						<p class="description">
							<?php _e( 'Add remote WordPress sites to sync users with. Use their API key from their settings page.', 'custom-user-sync' ); ?>
						</p>
					</td>
				</tr>					<tr>
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
			
			<h2><?php _e( 'Bulk Sync Users', 'custom-user-sync' ); ?></h2>
			<p><?php _e( 'Synchronize all existing users to remote sites. This will send all users in batches.', 'custom-user-sync' ); ?></p>
			<button type="button" class="button button-primary" id="bulk-sync-start">
				<?php _e( 'Start Bulk Sync', 'custom-user-sync' ); ?>
			</button>
			<button type="button" class="button" id="bulk-sync-stop" style="display: none;">
				<?php _e( 'Stop', 'custom-user-sync' ); ?>
			</button>
			<div id="bulk-sync-progress" style="margin-top: 15px; display: none;">
				<div style="background: #f0f0f0; border: 1px solid #ccc; border-radius: 4px; height: 30px; position: relative; overflow: hidden;">
					<div id="bulk-sync-progress-bar" style="background: #0073aa; height: 100%; width: 0%; transition: width 0.3s;"></div>
					<div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; display: flex; align-items: center; justify-content: center; font-weight: 600; color: #333;">
						<span id="bulk-sync-progress-text">0%</span>
					</div>
				</div>
				<p id="bulk-sync-status" style="margin-top: 10px;"></p>
				<div id="bulk-sync-errors" style="color: red; margin-top: 10px;"></div>
			</div>
			
			<hr style="margin: 30px 0;">
			
			<h2><?php _e( 'Test Connection', 'custom-user-sync' ); ?></h2>
			<button type="button" class="button" id="test-connection">
				<?php _e( 'Test Remote Connections', 'custom-user-sync' ); ?>
			</button>
			<div id="test-results" style="margin-top: 10px;"></div>
		</div>
		
		<script>
		jQuery(document).ready(function($) {
			let siteIndex = <?php echo count( $remote_sites ); ?>;
			
			// Add new remote site
			$('#add-remote-site').on('click', function() {
				const html = '<div class="remote-site-row" style="margin-bottom: 15px; padding: 15px; background: #f9f9f9; border: 1px solid #ddd; border-radius: 4px;">' +
					'<div style="margin-bottom: 10px;">' +
					'<label style="display: inline-block; width: 100px; font-weight: 600;">Site URL:</label>' +
					'<input type="url" name="<?php echo esc_js( $this->option_name ); ?>[remote_sites][' + siteIndex + '][url]" class="regular-text" placeholder="https://example.com" style="width: 400px;" />' +
					'</div>' +
					'<div style="margin-bottom: 10px;">' +
					'<label style="display: inline-block; width: 100px; font-weight: 600;">API Key:</label>' +
					'<input type="text" name="<?php echo esc_js( $this->option_name ); ?>[remote_sites][' + siteIndex + '][api_key]" class="regular-text" placeholder="Remote site\'s API key" style="width: 400px;" />' +
					'</div>' +
					'<button type="button" class="button remove-site" style="color: #a00;">Remove</button>' +
					'</div>';
				$('#remote-sites-container').append(html);
				siteIndex++;
			});
			
			// Remove remote site
			$(document).on('click', '.remove-site', function() {
				$(this).closest('.remote-site-row').remove();
			});
			
			// Bulk sync functionality
			let bulkSyncRunning = false;
			let bulkSyncOffset = 0;
			let bulkSyncTotal = 0;
			
			$('#bulk-sync-start').on('click', function() {
				if (!confirm('<?php _e( 'Start synchronizing all users to remote sites? This may take a while.', 'custom-user-sync' ); ?>')) {
					return;
				}
				
				bulkSyncRunning = true;
				bulkSyncOffset = 0;
				$('#bulk-sync-start').hide();
				$('#bulk-sync-stop').show();
				$('#bulk-sync-progress').show();
				$('#bulk-sync-errors').html('');
				
				// Get total users first
				$.post(ajaxurl, {
					action: 'cus_bulk_sync_status',
					nonce: '<?php echo wp_create_nonce( 'cus_bulk_sync' ); ?>'
				}, function(response) {
					if (response.success) {
						bulkSyncTotal = response.data.total_users;
						processBulkSyncBatch();
					}
				});
			});
			
			$('#bulk-sync-stop').on('click', function() {
				bulkSyncRunning = false;
				$('#bulk-sync-start').show();
				$('#bulk-sync-stop').hide();
				$('#bulk-sync-status').html('<?php _e( 'Stopped by user', 'custom-user-sync' ); ?>');
			});
			
			function processBulkSyncBatch() {
				if (!bulkSyncRunning) return;
				
				$.post(ajaxurl, {
					action: 'cus_bulk_sync',
					nonce: '<?php echo wp_create_nonce( 'cus_bulk_sync' ); ?>',
					batch_size: 10,
					offset: bulkSyncOffset
				}, function(response) {
					if (!response.success) {
						$('#bulk-sync-status').html('<span style="color: red;">Error: ' + response.data + '</span>');
						bulkSyncRunning = false;
						$('#bulk-sync-start').show();
						$('#bulk-sync-stop').hide();
						return;
					}
					
					const data = response.data;
					bulkSyncOffset = data.total;
					
					const percent = bulkSyncTotal > 0 ? Math.round((bulkSyncOffset / bulkSyncTotal) * 100) : 0;
					$('#bulk-sync-progress-bar').css('width', percent + '%');
					$('#bulk-sync-progress-text').text(percent + '%');
					$('#bulk-sync-status').html(data.message + ' (' + bulkSyncOffset + ' / ' + bulkSyncTotal + ')');
					
					if (data.errors && data.errors.length > 0) {
						let errorHtml = '<strong>Errors:</strong><br>';
						data.errors.forEach(function(error) {
							errorHtml += error + '<br>';
						});
						$('#bulk-sync-errors').append(errorHtml);
					}
					
					if (data.completed) {
						bulkSyncRunning = false;
						$('#bulk-sync-start').show();
						$('#bulk-sync-stop').hide();
						$('#bulk-sync-status').html('<span style="color: green; font-weight: bold;">✓ ' + data.message + '</span>');
					} else {
						// Process next batch with 500ms delay for optimal performance
						setTimeout(processBulkSyncBatch, 500);
					}
				}).fail(function() {
					$('#bulk-sync-status').html('<span style="color: red;">Connection error</span>');
					bulkSyncRunning = false;
					$('#bulk-sync-start').show();
					$('#bulk-sync-stop').hide();
				});
			}
			
			// Test connections
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
							const icon = result.success ? '<span style="color: green;">✓</span>' : '<span style="color: red;">✗</span>';
							html += '<p>' + icon + ' <strong>' + result.url + '</strong>: ' + result.message + '</p>';
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

		// Parse remote_sites - can be JSON string or already array
		if ( isset( $settings['remote_sites'] ) ) {
			if ( is_string( $settings['remote_sites'] ) ) {
				$settings['remote_sites'] = json_decode( $settings['remote_sites'], true );
				if ( json_last_error() !== JSON_ERROR_NONE ) {
					$settings['remote_sites'] = array();
				}
			} elseif ( is_array( $settings['remote_sites'] ) ) {
				// Filter out empty sites
				$settings['remote_sites'] = array_values( array_filter( $settings['remote_sites'], function( $site ) {
					return ! empty( $site['url'] ) && ! empty( $site['api_key'] );
				} ) );
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
