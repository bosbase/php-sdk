# Health API - PHP SDK Documentation

## Overview

The Health API provides a simple endpoint to check the health status of the server. It returns basic health information and, when authenticated as a superuser, provides additional diagnostic information about the server state.

**Key Features:**
- No authentication required for basic health check
- Superuser authentication provides additional diagnostic data
- Lightweight endpoint for monitoring and health checks
- Supports both GET and HEAD methods

**Backend Endpoints:**
- `GET /api/health` - Check health status
- `HEAD /api/health` - Check health status (HEAD method)

**Note**: The health endpoint is publicly accessible, but superuser authentication provides additional information.

## Authentication

Basic health checks do not require authentication:

```php
<?php
require_once 'vendor/autoload.php';

use BosBase\BosBase;

$pb = new BosBase('http://127.0.0.1:8090');

// Basic health check (no auth required)
$health = $pb->health->check();
```

For additional diagnostic information, authenticate as a superuser:

```php
// Authenticate as superuser for extended health data
$pb->collection('_superusers')->authWithPassword('admin@example.com', 'password');
$health = $pb->health->check();
```

## Health Check Response Structure

### Basic Response (Guest/Regular User)

```php
[
    'code' => 200,
    'message' => 'API is healthy.',
    'data' => []
]
```

### Superuser Response

```php
[
    'code' => 200,
    'message' => 'API is healthy.',
    'data' => [
        'canBackup' => true,           // Whether backup operations are allowed
        'realIP' => '192.168.1.100',   // Real IP address of the client
        'requireS3' => false,          // Whether S3 storage is required
        'possibleProxyHeader' => ''     // Detected proxy header (if behind reverse proxy)
    ]
]
```

## Check Health Status

Returns the health status of the API server.

### Basic Usage

```php
// Simple health check
$health = $pb->health->check();

echo $health['message']; // "API is healthy."
echo $health['code'];    // 200
```

### With Superuser Authentication

```php
// Authenticate as superuser first
$pb->collection('_superusers')->authWithPassword('admin@example.com', 'password');

// Get extended health information
$health = $pb->health->check();

echo $health['data']['canBackup'] ? 'true' : 'false';           // true/false
echo $health['data']['realIP'];              // "192.168.1.100"
echo $health['data']['requireS3'] ? 'true' : 'false';           // false
echo $health['data']['possibleProxyHeader']; // "" or header name
```

## Response Fields

### Common Fields (All Users)

| Field | Type | Description |
|-------|------|-------------|
| `code` | number | HTTP status code (always 200 for healthy server) |
| `message` | string | Health status message ("API is healthy.") |
| `data` | array | Health data (empty for non-superusers, populated for superusers) |

### Superuser-Only Fields (in `data`)

| Field | Type | Description |
|-------|------|-------------|
| `canBackup` | boolean | `true` if backup/restore operations can be performed, `false` if a backup/restore is currently in progress |
| `realIP` | string | The real IP address of the client (useful when behind proxies) |
| `requireS3` | boolean | `true` if S3 storage is required (local fallback disabled), `false` otherwise |
| `possibleProxyHeader` | string | Detected proxy header name (e.g., "X-Forwarded-For", "CF-Connecting-IP") if the server appears to be behind a reverse proxy, empty string otherwise |

## Use Cases

### 1. Basic Health Monitoring

```php
function checkServerHealth($pb) {
    try {
        $health = $pb->health->check();
        
        if ($health['code'] === 200 && $health['message'] === 'API is healthy.') {
            echo '✓ Server is healthy' . "\n";
            return true;
        } else {
            echo '✗ Server health check failed' . "\n";
            return false;
        }
    } catch (\Exception $error) {
        echo '✗ Health check error: ' . $error->getMessage() . "\n";
        return false;
    }
}

// Use in monitoring
// Note: In PHP, you'd typically use cron jobs or task schedulers for periodic checks
$isHealthy = checkServerHealth($pb);
if (!$isHealthy) {
    // Alert or take action
    echo 'Server health check failed!' . "\n";
}
```

### 2. Backup Readiness Check

```php
function canPerformBackup($pb) {
    try {
        // Authenticate as superuser
        $pb->collection('_superusers')->authWithPassword('admin@example.com', 'password');
        
        $health = $pb->health->check();
        
        if (isset($health['data']['canBackup']) && $health['data']['canBackup'] === false) {
            echo '⚠️ Backup operation is currently in progress' . "\n";
            return false;
        }
        
        echo '✓ Backup operations are allowed' . "\n";
        return true;
    } catch (\Exception $error) {
        echo 'Failed to check backup readiness: ' . $error->getMessage() . "\n";
        return false;
    }
}

// Use before creating backups
if (canPerformBackup($pb)) {
    $pb->backups->create('backup.zip');
}
```

### 3. Monitoring Dashboard

```php
class HealthMonitor {
    private $pb;
    private $isSuperuser = false;

    public function __construct($pb) {
        $this->pb = $pb;
    }

    public function authenticateAsSuperuser($email, $password) {
        try {
            $this->pb->collection('_superusers')->authWithPassword($email, $password);
            $this->isSuperuser = true;
            return true;
        } catch (\Exception $error) {
            echo 'Superuser authentication failed: ' . $error->getMessage() . "\n";
            return false;
        }
    }

    public function getHealthStatus() {
        try {
            $health = $this->pb->health->check();
            
            $status = [
                'healthy' => $health['code'] === 200,
                'message' => $health['message'],
                'timestamp' => date('c'),
            ];
            
            if ($this->isSuperuser && isset($health['data'])) {
                $status['diagnostics'] = [
                    'canBackup' => $health['data']['canBackup'] ?? null,
                    'realIP' => $health['data']['realIP'] ?? null,
                    'requireS3' => $health['data']['requireS3'] ?? null,
                    'behindProxy' => !empty($health['data']['possibleProxyHeader']),
                    'proxyHeader' => $health['data']['possibleProxyHeader'] ?? null,
                ];
            }
            
            return $status;
        } catch (\Exception $error) {
            return [
                'healthy' => false,
                'error' => $error->getMessage(),
                'timestamp' => date('c'),
            ];
        }
    }
}

// Usage
$monitor = new HealthMonitor($pb);
$monitor->authenticateAsSuperuser('admin@example.com', 'password');
$status = $monitor->getHealthStatus();
print_r($status);
```

### 4. Pre-Flight Checks

```php
function preFlightCheck($pb) {
    $checks = [
        'serverHealthy' => false,
        'canBackup' => false,
        'storageConfigured' => false,
        'issues' => [],
    ];
    
    try {
        // Basic health check
        $health = $pb->health->check();
        $checks['serverHealthy'] = $health['code'] === 200;
        
        if (!$checks['serverHealthy']) {
            $checks['issues'][] = 'Server health check failed';
            return $checks;
        }
        
        // Authenticate as superuser for extended checks
        try {
            $pb->collection('_superusers')->authWithPassword('admin@example.com', 'password');
            
            $detailedHealth = $pb->health->check();
            
            $checks['canBackup'] = ($detailedHealth['data']['canBackup'] ?? false) === true;
            $checks['storageConfigured'] = !($detailedHealth['data']['requireS3'] ?? false) || 
                                          ($detailedHealth['data']['requireS3'] ?? false) === false;
            
            if (!$checks['canBackup']) {
                $checks['issues'][] = 'Backup operations are currently unavailable';
            }
            
            if ($detailedHealth['data']['requireS3'] ?? false) {
                $checks['issues'][] = 'S3 storage is required but may not be configured';
            }
        } catch (\Exception $authError) {
            $checks['issues'][] = 'Superuser authentication failed - limited diagnostics available';
        }
    } catch (\Exception $error) {
        $checks['issues'][] = 'Health check error: ' . $error->getMessage();
    }
    
    return $checks;
}

// Use before critical operations
$checks = preFlightCheck($pb);
if (count($checks['issues']) > 0) {
    echo 'Pre-flight check issues: ' . json_encode($checks['issues']) . "\n";
    // Handle issues before proceeding
}
```

## Error Handling

```php
function safeHealthCheck($pb) {
    try {
        $health = $pb->health->check();
        return [
            'success' => true,
            'data' => $health,
        ];
    } catch (\Exception $error) {
        // Network errors, server down, etc.
        return [
            'success' => false,
            'error' => $error->getMessage(),
            'code' => method_exists($error, 'getStatus') ? $error->getStatus() : 0,
        ];
    }
}

// Handle different error scenarios
$result = safeHealthCheck($pb);
if (!$result['success']) {
    if ($result['code'] === 0) {
        echo 'Network error or server unreachable' . "\n";
    } else {
        echo 'Server returned error: ' . $result['code'] . "\n";
    }
}
```

## Best Practices

1. **Monitoring**: Use health checks for regular monitoring (e.g., every 30-60 seconds via cron)
2. **Load Balancers**: Configure load balancers to use the health endpoint for health checks
3. **Pre-flight Checks**: Check `canBackup` before initiating backup operations
4. **Error Handling**: Always handle errors gracefully as the server may be down
5. **Rate Limiting**: Don't poll the health endpoint too frequently (avoid spamming)
6. **Caching**: Consider caching health check results for a few seconds to reduce load
7. **Logging**: Log health check results for troubleshooting and monitoring
8. **Superuser Auth**: Only authenticate as superuser when you need diagnostic information
9. **Proxy Configuration**: Use `possibleProxyHeader` to detect and configure reverse proxy settings

## Response Codes

| Code | Meaning |
|------|---------|
| 200 | Server is healthy |
| Network Error | Server is unreachable or down |

## Limitations

- **No Detailed Metrics**: The health endpoint does not provide detailed performance metrics
- **Basic Status Only**: Returns basic status, not detailed system information
- **Superuser Required**: Extended diagnostics require superuser authentication
- **No Historical Data**: Only returns current status, no historical health data

## Related Documentation

- [Backups API](./BACKUPS_API.md) - Using `canBackup` to check backup readiness
- [Authentication](./AUTHENTICATION.md) - Superuser authentication

