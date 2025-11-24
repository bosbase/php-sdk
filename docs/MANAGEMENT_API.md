# Management API Documentation - PHP SDK

This document covers the management API capabilities available in the PHP SDK, which correspond to the features available in the backend management UI.

> **Note**: All management API operations require superuser authentication (🔐).

## Table of Contents

- [Settings Service](#settings-service)
- [Backup Service](#backup-service)
- [Log Service](#log-service)
- [Cron Service](#cron-service)
- [Health Service](#health-service)
- [Collection Service](#collection-service)

## Settings Service

The Settings Service provides comprehensive management of application settings.

### Get Application Settings

```php
$settings = $pb->settings->getApplicationSettings();
// Returns: ['meta' => ..., 'trustedProxy' => ..., 'rateLimits' => ..., 'batch' => ...]
```

### Update Application Settings

```php
$pb->settings->updateApplicationSettings([
    'meta' => [
        'appName' => 'My App',
        'appURL' => 'https://example.com',
        'hideControls' => false,
    ],
    'trustedProxy' => [
        'headers' => ['X-Forwarded-For'],
        'useLeftmostIP' => true,
    ],
    'rateLimits' => [
        'enabled' => true,
        'rules' => [
            [
                'label' => 'api/users',
                'duration' => 3600,
                'maxRequests' => 100,
            ],
        ],
    ],
    'batch' => [
        'enabled' => true,
        'maxRequests' => 100,
        'interval' => 200,
    ],
]);
```

## Backup Service

See [Backups API](./BACKUPS_API.md) for detailed documentation.

## Log Service

See [Logs API](./LOGS_API.md) for detailed documentation.

## Cron Service

See [Crons API](./CRONS_API.md) for detailed documentation.

## Health Service

See [Health API](./HEALTH_API.md) for detailed documentation.

## Collection Service

See [Collection API](./COLLECTION_API.md) and [Collections](./COLLECTIONS.md) for detailed documentation.

## Related Documentation

- [Authentication](./AUTHENTICATION.md) - User authentication
- [Collection API](./COLLECTION_API.md) - Collection management

