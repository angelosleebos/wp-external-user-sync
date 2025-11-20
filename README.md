# Custom User Sync

Modern WordPress user synchronization plugin using REST API and webhooks.

## Features

- **Real-time Sync** - Automatic user synchronization via webhooks
- **REST API Based** - Modern REST API endpoints for communication
- **Encrypted Transfer** - Optional AES-256 encryption for sensitive data
- **Role Sync** - Synchronize user roles across sites
- **Meta Sync** - Sync user meta data (WooCommerce billing/shipping)
- **Bi-directional** - Any site can send and receive user data
- **Health Check** - Test connections to remote sites
- **Secure** - API key authentication for all requests

## Installation

1. Upload the `custom-user-sync` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to Settings → User Sync to configure

## Configuration

### On Each Site:

1. **Generate API Key**: Click "Generate New" to create a unique API key
2. **Save Settings**: Save to activate your API key
3. **Add Remote Sites**: Add JSON configuration for sites to sync with:

```json
[
  {
    "url": "https://site1.example.com",
    "api_key": "their-api-key-from-site1"
  },
  {
    "url": "https://site2.example.com",
    "api_key": "their-api-key-from-site2"
  }
]
```

4. **Optional Encryption**: Set the same encryption key on all sites for encrypted transfers
5. **Enable Sync**: Check "Enable automatic user synchronization"
6. **Test Connection**: Click "Test Remote Connections" to verify

## How It Works

### When a user is created/updated/deleted on Site A:

1. WordPress fires action hook (`user_register`, `profile_update`, `delete_user`)
2. Plugin gathers user data (login, email, meta, roles)
3. Data is optionally encrypted with AES-256
4. HTTP POST request sent to Site B's REST API endpoint
5. Site B verifies API key and processes the user data
6. Site B creates/updates/deletes the user locally

### Synced Data:

- User login (username)
- Email address
- First name / Last name
- Display name
- User roles (optional)
- User meta (optional):
  - WooCommerce billing fields
  - WooCommerce shipping fields
  - Nickname, description

### Security:

- **API Key Authentication**: Each request requires valid API key in header
- **HTTPS Required**: Always use HTTPS in production
- **Encryption**: Optional AES-256-CBC encryption for data in transit
- **WordPress Nonces**: AJAX requests protected with nonces

## API Endpoints

### Health Check
```
GET /wp-json/custom-user-sync/v1/health
Header: X-API-Key: your-api-key

Response:
{
  "status": "ok",
  "version": "1.0.0",
  "time": "2025-11-20 22:00:00"
}
```

### Receive User Data
```
POST /wp-json/custom-user-sync/v1/user
Header: X-API-Key: your-api-key
Header: Content-Type: application/json

Body:
{
  "action": "create|update|delete",
  "user_login": "johndoe",
  "user_email": "john@example.com",
  "first_name": "John",
  "last_name": "Doe",
  "roles": ["customer"],
  "user_meta": {
    "billing_city": "Amsterdam"
  }
}

Response:
{
  "success": true,
  "user_id": 123,
  "action": "created"
}
```

## Advantages Over WP Remote Users Sync

1. **Modern Code**: PHP 8.0+, proper OOP structure
2. **Real-time**: Webhook-based instead of polling
3. **Better Security**: Proper API key verification with `hash_equals()`
4. **Encryption**: Built-in AES-256 encryption support
5. **Health Checks**: Test connections before going live
6. **Error Logging**: Detailed error logging for debugging
7. **WooCommerce Ready**: Syncs billing/shipping meta out of the box
8. **Actively Maintained**: New code, no legacy dependencies

## Troubleshooting

### Connection Test Fails

- Verify remote site URL is correct (include https://)
- Check API key matches on both sites
- Ensure WordPress REST API is accessible (not blocked)
- Check firewall/security plugins (disable REST API restrictions)

### Users Not Syncing

- Check "Enable automatic user synchronization" is checked
- Verify remote sites configuration is valid JSON
- Check PHP error logs for detailed error messages
- Test connection using "Test Remote Connections" button

### Encrypted Data Fails

- Ensure encryption key is exactly the same on all sites
- Verify OpenSSL PHP extension is installed
- Check error logs for decryption errors

## Requirements

- PHP 8.0 or higher
- WordPress 6.0 or higher
- OpenSSL PHP extension (for encryption)
- HTTPS (recommended for production)

## Development

### File Structure:
```
custom-user-sync/
├── custom-user-sync.php          # Main plugin file
├── README.md                      # Documentation
└── includes/
    ├── class-cus-settings.php     # Admin settings page
    ├── class-cus-api.php          # REST API endpoints
    ├── class-cus-webhook.php      # Webhook sender
    ├── class-cus-sync.php         # Manual sync functions
    └── class-cus-encryption.php   # Encryption helper
```

### Extending:

Add custom user meta keys to sync in `class-cus-webhook.php`:

```php
$meta_keys = array(
    'your_custom_meta_key',
    'another_meta_key',
);
```

## License

GPL v2 or later

## Support

For issues or questions, contact support@sleebos.it
